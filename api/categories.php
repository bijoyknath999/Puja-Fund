<?php
require_once __DIR__ . '/bootstrap.php';

$user = requireAuth($conn);

$categories = [];
foreach (getCategories() as $key => $translations) {
    $categories[] = [
        'key' => $key,
        'en' => $translations['en'],
        'bn' => $translations['bn'],
    ];
}

jsonSuccess(['categories' => $categories]);
