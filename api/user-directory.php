<?php
// Lightweight id/name directory for any authenticated user - used to populate the
// transfer-recipient picker. Mirrors the web app's transactions.php, where the
// "SELECT id, name FROM users ORDER BY name" dropdown is available to every logged-in
// user (not just managers) - GET /api/users.php stays manager-only for the full stats
// list, this is the non-sensitive subset everyone needs.

require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$result = $conn->query("SELECT id, name FROM users ORDER BY name");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = ['id' => intval($row['id']), 'name' => $row['name']];
}

jsonSuccess(['users' => $users]);
