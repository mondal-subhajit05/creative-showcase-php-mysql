<?php
// Database configuration
$host = 'localhost';
$dbname = 'creative_showcase';
$username = 'root';
$password = '';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start session
session_start();

// Set timezone
date_default_timezone_set('UTC');
?>