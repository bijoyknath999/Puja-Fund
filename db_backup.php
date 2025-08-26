<?php
// Database configuration - Update these values for your hosting environment
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // Update with your database password
$DB_NAME = 'puja_fund';

// Create connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to handle Bengali text properly
$conn->set_charset('utf8mb4');
?>