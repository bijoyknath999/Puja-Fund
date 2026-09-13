<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);
requireManager($user);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 400);
}

$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-12-31');
$filterUserId = $_GET['user_id'] ?? '';
$filterType = $_GET['type'] ?? '';

$where = ["t.date BETWEEN ? AND ?"];
$params = [$from, $to];
$types = 'ss';

if ($filterUserId !== '') {
    $where[] = "t.added_by = ?";
    $params[] = intval($filterUserId);
    $types .= 'i';
}

if ($filterType && $filterType !== 'transfer') {
    $where[] = "t.type = ?";
    $params[] = $filterType;
    $types .= 's';
} elseif ($filterType === 'transfer') {
    // Only show outgoing transfers to avoid double counting (same as report.php)
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
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$totalCollection = 0;
$totalExpense = 0;
$transactions = [];
while ($row = $res->fetch_assoc()) {
    $transactions[] = formatTransactionRow($row);
    if ($row['type'] === 'collection') {
        $totalCollection += floatval($row['amount']);
    } elseif ($row['type'] === 'expense') {
        $totalExpense += floatval($row['amount']);
    }
}

jsonSuccess([
    'total_collection' => $totalCollection,
    'total_expense' => $totalExpense,
    'balance' => $totalCollection - $totalExpense,
    'transactions' => $transactions,
]);
