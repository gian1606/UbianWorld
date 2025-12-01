<?php
// api/logout.php

// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Set header for JSON response
header('Content-Type: application/json');

// Return a success message
echo json_encode([
    'success' => true,
    'message' => 'Logout successful.'
]);

// Note: The front-end (script.js) must handle the redirection after receiving this success response.
?>