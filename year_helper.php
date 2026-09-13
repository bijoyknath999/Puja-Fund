<?php
// Centralized yearly active-year management
// The "active year" is the year new pages default to and new entries are dated into.

function getActiveYear($conn) {
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'active_year' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return intval($row['setting_value']);
    }
    return intval(date('Y'));
}

function setActiveYear($conn, $year) {
    $year = intval($year);
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('active_year', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $yearStr = (string) $year;
    $stmt->bind_param('ss', $yearStr, $yearStr);
    return $stmt->execute();
}

// Years that have data, plus the active year so a freshly-started year is always selectable
function getAvailableYears($conn, $activeYear) {
    $years = [];
    $result = $conn->query("
        SELECT YEAR(date) as year FROM transactions
        UNION
        SELECT YEAR(transfer_date) as year FROM transfers
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $years[intval($row['year'])] = true;
        }
    }
    $years[intval($activeYear)] = true;

    $years = array_keys($years);
    rsort($years);
    return $years;
}

// Today's month/day, transplanted into the given year (for defaulting new-entry date inputs)
function getDefaultDateForYear($year) {
    $month = intval(date('n'));
    $day = intval(date('j'));
    if ($month == 2 && $day == 29 && !checkdate(2, 29, $year)) {
        $day = 28;
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function renderYearOptions($years, $selectedYear) {
    $options = '';
    foreach ($years as $year) {
        $selected = ($year == $selectedYear) ? 'selected' : '';
        $options .= '<option value="' . intval($year) . '" ' . $selected . '>' . intval($year) . '</option>';
    }
    return $options;
}
?>
