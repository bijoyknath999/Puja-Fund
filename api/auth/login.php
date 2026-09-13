<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 400);
}

$body = getJsonBody();
$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if ($email === '' || $password === '') {
    jsonError('Email and password are required', 400);
}

$stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    jsonError('Invalid email or password', 401);
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

$ins = $conn->prepare("INSERT INTO api_tokens (token, user_id, expires_at) VALUES (?, ?, ?)");
$ins->bind_param('sis', $token, $user['id'], $expiresAt);
$ins->execute();

jsonSuccess([
    'token' => $token,
    'expires_at' => $expiresAt,
    'user' => formatUserRow($user),
], 201);
