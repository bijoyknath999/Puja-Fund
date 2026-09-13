<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);

jsonSuccess(formatUserRow($user));
