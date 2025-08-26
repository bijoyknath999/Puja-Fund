<?php
// Centralized category management system
// Add/modify categories here and they will automatically update across the entire application

function getCategories() {
    return [
        'prothima' => ['en' => 'Prothima', 'bn' => 'প্রতিমা'],
        'prothima_stage' => ['en' => 'Prothima Stage', 'bn' => 'প্রতিমা স্টেজ'],
        'radhuni' => ['en' => 'Radhuni', 'bn' => 'রাঁধুনি'],
        'brahmon' => ['en' => 'Brahmon', 'bn' => 'ব্রাহ্মণ'],
        'puja_bazar' => ['en' => 'Puja Bazar', 'bn' => 'পূজা বাজার'],
        'prashad_bazar' => ['en' => 'Prashad Bazar', 'bn' => 'প্রসাদ বাজার'],
        'decoration' => ['en' => 'Decoration', 'bn' => 'সাজসজ্জা'],
        'sound' => ['en' => 'Sound', 'bn' => 'সাউন্ড'],
        'mic' => ['en' => 'Mic', 'bn' => 'মাইক']
    ];
}

function renderCategoryOptions($selectedCategory = '', $lang = 'en', $selectText = 'Select category') {
    $categories = getCategories();
    $options = '<option value="">' . $selectText . '</option>';
    
    foreach ($categories as $key => $translations) {
        $selected = ($selectedCategory === $key) ? 'selected' : '';
        $label = $translations[$lang] ?? $translations['en'];
        $options .= '<option value="' . htmlspecialchars($key) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    
    return $options;
}

function getCategoryName($categoryKey, $lang = 'en') {
    $categories = getCategories();
    if (isset($categories[$categoryKey])) {
        return $categories[$categoryKey][$lang] ?? $categories[$categoryKey]['en'];
    }
    return $categoryKey; // Return key if not found
}
?>
