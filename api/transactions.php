<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet($conn, $user);
} elseif ($method === 'POST') {
    handlePost($conn, $user);
} elseif ($method === 'PUT') {
    handlePut($conn, $user);
} elseif ($method === 'DELETE') {
    handleDelete($conn, $user);
} else {
    jsonError('Method not allowed', 400);
}

// GET /api/transactions.php?year=&from=&to=&user_id=&type=
// Same filter semantics as transactions.php: from+to override year.
// Members only ever see their own transactions; managers can filter by user_id.
function handleGet($conn, $user) {
    $activeYear = getActiveYear($conn);
    $year = isset($_GET['year']) ? intval($_GET['year']) : $activeYear;
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $filterUserId = $_GET['user_id'] ?? '';
    $filterType = $_GET['type'] ?? '';

    $where = [];
    $params = [];
    $types = '';

    if ($from && $to) {
        $where[] = "t.date BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types .= 'ss';
    } else {
        $where[] = "YEAR(t.date) = ?";
        $params[] = $year;
        $types .= 'i';
    }

    if ($user['role'] !== 'manager') {
        $where[] = "t.added_by = ?";
        $params[] = $user['id'];
        $types .= 'i';
    } elseif ($filterUserId !== '') {
        $where[] = "t.added_by = ?";
        $params[] = intval($filterUserId);
        $types .= 'i';
    }

    if ($filterType && $filterType !== 'transfer') {
        $where[] = "t.type = ?";
        $params[] = $filterType;
        $types .= 's';
    } elseif ($filterType === 'transfer') {
        // Only show outgoing transfers to avoid showing duplicates (same as transactions.php)
        $where[] = "t.type = 'transfer' AND t.description LIKE '%Transfer to%'";
    }

    $sql = "
        SELECT t.*, u.name as added_by_name
        FROM transactions t
        JOIN users u ON u.id = t.added_by
        WHERE (t.type != 'transfer' OR (t.type = 'transfer' AND t.description LIKE '%Transfer to%'))
    ";
    if ($where) {
        $sql .= " AND " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY t.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $transactions = [];
    while ($row = $res->fetch_assoc()) {
        $transactions[] = formatTransactionRow($row);
    }

    jsonSuccess(['transactions' => $transactions]);
}

// POST /api/transactions.php
function handlePost($conn, $user) {
    $body = getJsonBody();
    $type = $body['type'] ?? '';
    $description = trim($body['description'] ?? '');
    $amount = isset($body['amount']) ? floatval($body['amount']) : 0;
    $date = $body['date'] ?? '';
    $category = $body['category'] ?? null;

    if (!in_array($type, ['collection', 'expense', 'transfer'], true)) {
        jsonError('Invalid transaction type', 400);
    }
    if ($amount <= 0 || $date === '' || $description === '') {
        jsonError('Description, amount and date are required', 400);
    }

    if ($type === 'transfer') {
        $transferUserId = isset($body['transfer_user_id']) ? intval($body['transfer_user_id']) : 0;
        if (!$transferUserId) {
            jsonError('transfer_user_id is required for transfers', 400);
        }

        // Per-user transfer balance check (all-time, unchanged from transactions.php's balance_check)
        $currentBalance = getUserTransferBalance($conn, $user['id']);
        if ($currentBalance < $amount) {
            jsonError(
                'Insufficient balance. Your current balance is ৳' . number_format($currentBalance, 2) .
                ' but you are trying to transfer ৳' . number_format($amount, 2),
                400
            );
        }

        // Creates a pending transfer request; only visible in transactions after manager approval.
        $stmt = $conn->prepare("INSERT INTO transfers (from_user_id, to_user_id, amount, description, transfer_date, status, created_by) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param('iidssi', $user['id'], $transferUserId, $amount, $description, $date, $user['id']);
        $stmt->execute();
        $transferId = $conn->insert_id;

        $row = fetchTransferById($conn, $transferId);
        jsonSuccess(['transfer' => formatTransferRow($row)], 201);
    } else {
        $stmt = $conn->prepare("INSERT INTO transactions (type, description, amount, date, category, added_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdssi', $type, $description, $amount, $date, $category, $user['id']);
        $stmt->execute();
        $txId = $conn->insert_id;

        $row = fetchTransactionById($conn, $txId);
        $extra = [];
        if ($type === 'expense') {
            $expenseYear = intval(substr($date, 0, 4));
            $newBalance = getFundBalanceForYear($conn, $expenseYear);
            if ($newBalance < 0) {
                $extra['warning'] = "The fund balance for {$expenseYear} is now negative (৳" . number_format($newBalance, 2) . ").";
            }
        }
        jsonSuccess(['transaction' => formatTransactionRow($row)], 201, $extra);
    }
}

// PUT /api/transactions.php?id=123 (owner or manager)
function handlePut($conn, $user) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonError('id is required', 400);
    }

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    if (!$tx) {
        jsonError('Transaction not found', 404);
    }

    if ($user['role'] !== 'manager' && intval($tx['added_by']) !== intval($user['id'])) {
        jsonError('Forbidden', 403);
    }

    // Transfers cannot be edited, matching the web app.
    if ($tx['type'] === 'transfer') {
        jsonError('Transfer transactions cannot be edited', 400);
    }

    $body = getJsonBody();
    $type = $body['type'] ?? $tx['type'];
    $description = trim($body['description'] ?? '');
    $amount = isset($body['amount']) ? floatval($body['amount']) : 0;
    $date = $body['date'] ?? '';
    $category = $body['category'] ?? null;

    if ($amount <= 0 || $date === '' || $description === '') {
        jsonError('Description, amount and date are required', 400);
    }

    $u = $conn->prepare("UPDATE transactions SET type=?, description=?, amount=?, date=?, category=? WHERE id=?");
    $u->bind_param('ssdssi', $type, $description, $amount, $date, $category, $id);
    $u->execute();

    $row = fetchTransactionById($conn, $id);
    $extra = [];
    if ($type === 'expense') {
        $expenseYear = intval(substr($date, 0, 4));
        $newBalance = getFundBalanceForYear($conn, $expenseYear);
        if ($newBalance < 0) {
            $extra['warning'] = "The fund balance for {$expenseYear} is now negative (৳" . number_format($newBalance, 2) . ").";
        }
    }
    jsonSuccess(['transaction' => formatTransactionRow($row)], 200, $extra);
}

// DELETE /api/transactions.php?id=123 (manager only)
function handleDelete($conn, $user) {
    requireManager($user);

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonError('id is required', 400);
    }

    $stmt = $conn->prepare("SELECT id FROM transactions WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        jsonError('Transaction not found', 404);
    }

    $del = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $del->bind_param('i', $id);
    $del->execute();

    jsonSuccess(null, 200);
}

function fetchTransactionById($conn, $id) {
    $stmt = $conn->prepare("SELECT t.*, u.name as added_by_name FROM transactions t JOIN users u ON u.id = t.added_by WHERE t.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchTransferById($conn, $id) {
    $stmt = $conn->prepare("SELECT t.*, u1.name as from_user_name, u2.name as to_user_name FROM transfers t JOIN users u1 ON t.from_user_id = u1.id JOIN users u2 ON t.to_user_id = u2.id WHERE t.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
