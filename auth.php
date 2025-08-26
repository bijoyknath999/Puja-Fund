<?php
session_start();

// Check if installation is needed
if (!file_exists('db.php') || filesize('db.php') < 100) {
    header('Location: installation.php');
    exit;
}

// Check if database has users
try {
    include 'db.php';
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result && $result->fetch_assoc()['count'] == 0) {
        header('Location: installation.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: installation.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>