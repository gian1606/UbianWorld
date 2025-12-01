<?php
// api/check_session.php

// Start the session (must be the very first thing)
session_start();

// Set header for JSON response
header('Content-Type: application/json');

// Check if the user is currently logged in (based on the 'loggedin' session variable)
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    
    // User is logged in. Return their role, username, and ID.
    $response = [
        'isLoggedIn' => true,
        'role' => $_SESSION['role'],
        'username' => $_SESSION['username'],
        'user_id' => $_SESSION['user_id']
    ];
} else {
    // User is not logged in.
    $response = [
        'isLoggedIn' => false,
        'role' => null
    ];
}

echo json_encode($response);
// No need to close DB connection as this script doesn't open one.
?>
