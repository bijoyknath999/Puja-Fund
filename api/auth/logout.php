<?php
require_once __DIR__ . '/../bootstrap.php';

$user = requireAuth($conn);

$token = getBearerToken();
$stmt = $conn->prepare("DELETE FROM api_tokens WHERE token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();

jsonSuccess(null, 200);
