<?php
// config.php

// Database configuration settings
define('DB_SERVER', 'fdb1032.awardspace.net');
define('DB_USERNAME', '4674277_ubiandb'); // CHANGE THIS TO YOUR ACTUAL DB USERNAME
define('DB_PASSWORD', 'admin1234567!'); // CHANGE THIS TO YOUR ACTUAL DB PASSWORD
define('DB_NAME', '4674277_ubiandb');

// Attempt to establish a connection to the database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    // Return a JSON error response if the database connection fails
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    // Stop script execution
    exit();
}

// Ensure the connection is set to UTF-8
mysqli_set_charset($link, "utf8");

// IMPORTANT: In a production environment, you would use prepared statements
// everywhere to prevent SQL Injection.
?>