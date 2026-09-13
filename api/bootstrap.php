<?php
// Shared bootstrap for every api/*.php endpoint:
// - permissive CORS headers (dev-friendly, so a Flutter web build can call the API)
// - short-circuits OPTIONS preflight requests with a bare 200
// - JSON response helpers using the standard envelope from API_SPEC.md
// - Bearer token authentication against the api_tokens table
// - role-gate helper for manager-only endpoints

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../year_helper.php';
require_once __DIR__ . '/../categories.php';

// ---- Response helpers -------------------------------------------------

function jsonSuccess($data, $status = 200, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => true, 'data' => $data], $extra));
    exit;
}

function jsonError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ---- Request helpers ---------------------------------------------------

function getBearerToken() {
    $header = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $header = $v;
                break;
            }
        }
    }
    if ($header && preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

function getJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ---- Auth ---------------------------------------------------------------

function requireAuth($conn) {
    $token = getBearerToken();
    if (!$token) {
        jsonError('Invalid or expired token', 401);
    }
    $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.role FROM api_tokens t JOIN users u ON t.user_id = u.id WHERE t.token = ? AND t.expires_at > NOW() LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        jsonError('Invalid or expired token', 401);
    }
    return $user;
}

function requireManager($user) {
    if ($user['role'] !== 'manager') {
        jsonError('Forbidden: manager role required', 403);
    }
}

// ---- Balance helpers (must mirror the web app's SQL exactly) ------------

// Pooled fund balance for a year: SUM(collection) - SUM(expense) WHERE YEAR(date) = ?
// Same query shape as index.php / transactions.php's "fundBalanceStmt".
function getFundBalanceForYear($conn, $year) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as balance
        FROM transactions
        WHERE YEAR(date) = ?
    ");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    return floatval($stmt->get_result()->fetch_assoc()['balance']);
}

// Per-user all-time running balance: collections - expenses + transfers_in - transfers_out.
// Same query as transactions.php's "balance_check".
function getUserTransferBalance($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'collection' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) +
            COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer from%' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'transfer' AND description LIKE '%Transfer to%' THEN amount ELSE 0 END), 0) as balance
        FROM transactions
        WHERE added_by = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return floatval($stmt->get_result()->fetch_assoc()['balance']);
}

function getUserNameById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['name'] : 'Unknown User';
}

// ---- Row formatters (ensure numeric JSON fields, not decimal strings) --

function formatTransactionRow($row) {
    return [
        'id' => intval($row['id']),
        'type' => $row['type'],
        'description' => $row['description'],
        'amount' => floatval($row['amount']),
        'date' => $row['date'],
        'category' => $row['category'],
        'added_by' => intval($row['added_by']),
        'added_by_name' => $row['added_by_name'] ?? null,
        'created_at' => $row['created_at'],
    ];
}

function formatTransferRow($row) {
    return [
        'id' => intval($row['id']),
        'from_user_id' => intval($row['from_user_id']),
        'from_user_name' => $row['from_user_name'] ?? null,
        'to_user_id' => intval($row['to_user_id']),
        'to_user_name' => $row['to_user_name'] ?? null,
        'amount' => floatval($row['amount']),
        'description' => $row['description'],
        'transfer_date' => $row['transfer_date'],
        'status' => $row['status'],
    ];
}

function formatUserRow($row) {
    return [
        'id' => intval($row['id']),
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role'],
    ];
}
