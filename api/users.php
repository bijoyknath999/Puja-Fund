<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);
requireManager($user);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $activeYear = getActiveYear($conn);
    $year = isset($_GET['year']) ? intval($_GET['year']) : $activeYear;

    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.role,
               COALESCE(stats.transaction_count, 0) as transaction_count,
               COALESCE(stats.total_collections, 0) as total_collections,
               COALESCE(stats.total_expenses, 0) as total_expenses
        FROM users u
        LEFT JOIN (
            SELECT
                t.added_by,
                COUNT(CASE WHEN t.type != 'transfer' AND YEAR(t.date) = ? THEN t.id END) as transaction_count,
                SUM(CASE WHEN t.type = 'collection' AND YEAR(t.date) = ? THEN t.amount ELSE 0 END) as total_collections,
                SUM(CASE WHEN t.type = 'expense' AND YEAR(t.date) = ? THEN t.amount ELSE 0 END) as total_expenses
            FROM transactions t
            GROUP BY t.added_by
        ) stats ON u.id = stats.added_by
        ORDER BY u.created_at DESC
    ");
    $stmt->bind_param('iii', $year, $year, $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $users = [];
    while ($row = $res->fetch_assoc()) {
        $users[] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'transaction_count' => intval($row['transaction_count']),
            'total_collections' => floatval($row['total_collections']),
            'total_expenses' => floatval($row['total_expenses']),
        ];
    }

    jsonSuccess(['users' => $users]);
} elseif ($method === 'POST') {
    $body = getJsonBody();
    $name = trim($body['name'] ?? '');
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $role = $body['role'] ?? 'member';

    if ($name === '' || $email === '' || $password === '') {
        jsonError('Name, email and password are required', 400);
    }
    if (!in_array($role, ['member', 'manager'], true)) {
        jsonError('Invalid role', 400);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $hashed, $role);

    if (!$stmt->execute()) {
        if ($conn->errno === 1062) {
            jsonError('Email already exists', 400);
        }
        jsonError('Error creating user', 500);
    }

    $newId = $conn->insert_id;
    jsonSuccess(['user' => [
        'id' => $newId,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ]], 201);
} elseif ($method === 'PUT') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonError('id is required', 400);
    }
    if ($id === intval($user['id'])) {
        jsonError('You cannot change your own role', 400);
    }

    $body = getJsonBody();
    $role = $body['role'] ?? '';
    if (!in_array($role, ['member', 'manager'], true)) {
        jsonError('Invalid role', 400);
    }

    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    if (!$target) {
        jsonError('User not found', 404);
    }

    $u = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $u->bind_param('si', $role, $id);
    $u->execute();

    jsonSuccess(['user' => [
        'id' => intval($target['id']),
        'name' => $target['name'],
        'email' => $target['email'],
        'role' => $role,
    ]]);
} elseif ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonError('id is required', 400);
    }
    if ($id === intval($user['id'])) {
        jsonError('You cannot delete your own account', 400);
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        jsonError('User not found', 404);
    }

    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param('i', $id);
    $del->execute();

    jsonSuccess(null, 200);
} else {
    jsonError('Method not allowed', 400);
}
