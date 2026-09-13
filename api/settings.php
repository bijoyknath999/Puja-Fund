<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);
requireManager($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 400);
}

$body = getJsonBody();
if (!isset($body['active_year']) || !is_numeric($body['active_year'])) {
    jsonError('active_year is required', 400);
}

$year = intval($body['active_year']);
setActiveYear($conn, $year);

jsonSuccess(['active_year' => $year]);
