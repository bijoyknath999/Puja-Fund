<?php
// The current user's own personal totals (collections/expenses/transfers/
// balance) for a year or date range - mirrors profile.php's exact balance
// calculation (see $balanceWhereClause there). Any authenticated role.

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$activeYear = getActiveYear($conn);
$year = isset($_GET['year']) ? intval($_GET['year']) : $activeYear;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = "WHERE added_by = ?";
$params = [$user['id']];
$types = 'i';

if ($from !== '') {
    $where .= " AND date >= ?";
    $params[] = $from;
    $types .= 's';
}
if ($to !== '') {
    $where .= " AND date <= ?";
    $params[] = $to;
    $types .= 's';
} else {
    // No explicit end date - scope by year, same as profile.php.
    $where .= " AND YEAR(date) = ?";
    $params[] = $year;
    $types .= 'i';
}

function sumFor($conn, $where, $types, $params, $extra) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions $where AND $extra");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return floatval($stmt->get_result()->fetch_assoc()['total']);
}

$totalCollections = sumFor($conn, $where, $types, $params, "type = 'collection'");
$totalExpenses = sumFor($conn, $where, $types, $params, "type = 'expense'");
$transferIn = sumFor($conn, $where, $types, $params, "type = 'transfer' AND description LIKE '%Transfer from%'");
$transferOut = sumFor($conn, $where, $types, $params, "type = 'transfer' AND description LIKE '%Transfer to%'");

$balance = $totalCollections - $totalExpenses + $transferIn - $transferOut;

jsonSuccess([
    'year' => $year,
    'total_collections' => $totalCollections,
    'total_expenses' => $totalExpenses,
    'transfer_in' => $transferIn,
    'transfer_out' => $transferOut,
    'balance' => $balance,
]);
