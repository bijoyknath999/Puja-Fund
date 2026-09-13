<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 400);
}

$activeYear = getActiveYear($conn);
$year = isset($_GET['year']) ? intval($_GET['year']) : $activeYear;

$totColStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='collection' AND YEAR(date) = ?");
$totColStmt->bind_param('i', $year);
$totColStmt->execute();
$totCol = floatval($totColStmt->get_result()->fetch_assoc()['total']);

$totExpStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='expense' AND YEAR(date) = ?");
$totExpStmt->bind_param('i', $year);
$totExpStmt->execute();
$totExp = floatval($totExpStmt->get_result()->fetch_assoc()['total']);

$balance = $totCol - $totExp;

// Recent transactions: manager sees all, member sees only their own (same as web).
// Only outgoing ("Transfer to") rows are shown for transfers, matching index.php's recentStmt.
if ($user['role'] === 'manager') {
    $recentStmt = $conn->prepare("
        SELECT t.*, u.name as added_by_name
        FROM transactions t
        JOIN users u ON t.added_by = u.id
        WHERE YEAR(t.date) = ?
        AND (t.type != 'transfer' OR (t.type = 'transfer' AND t.description LIKE '%Transfer to%'))
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $recentStmt->bind_param('i', $year);
} else {
    $recentStmt = $conn->prepare("
        SELECT t.*, u.name as added_by_name
        FROM transactions t
        JOIN users u ON t.added_by = u.id
        WHERE t.added_by = ? AND YEAR(t.date) = ?
        AND (t.type != 'transfer' OR (t.type = 'transfer' AND t.description LIKE '%Transfer to%'))
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $recentStmt->bind_param('ii', $user['id'], $year);
}
$recentStmt->execute();
$res = $recentStmt->get_result();

$recent = [];
while ($row = $res->fetch_assoc()) {
    $recent[] = formatTransactionRow($row);
}

jsonSuccess([
    'year' => $year,
    'total_collections' => $totCol,
    'total_expenses' => $totExp,
    'balance' => $balance,
    'recent_transactions' => $recent,
]);
