<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);

$activeYear = getActiveYear($conn);
$availableYears = array_map('intval', getAvailableYears($conn, $activeYear));

jsonSuccess([
    'active_year' => $activeYear,
    'available_years' => $availableYears,
]);
