<?php
// ram/db.php - Database Configuration for XAMPP

// Database configuration
$host = 'localhost';
$username = 'u743928828_lead_user';  // Default XAMPP username
$password = 'Admin_66666';      // Default XAMPP password (empty)
$database = 'u743928828_lead_db';  // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Success message (optional - remove in production)
// echo "Connected successfully to database: " . $database;

?>