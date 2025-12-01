<?php
// api/login.php

// 1. Start session management
session_start();

// 2. Include database configuration
require_once('../config.php');

// 3. Set header for JSON response
header('Content-Type: application/json');

// Define the response array
$response = ['success' => false, 'message' => 'Invalid request method.'];

/**
 * Helper function to fetch the user's first name AND primary key ID (StudentID or StaffID)
 * from the specific table (STUDENT or STAFF).
 * Returns an array: ['firstName' => string, 'pk_id' => int|null]
 */
function fetch_user_details($link, $user_id, $role) {
    $details = ['firstName' => 'User', 'pk_id' => null];

    if ($role === 'student') {
        // SELECT StudentID and FirstName
        $sql = "SELECT StudentID, FirstName FROM STUDENT WHERE UserID = ?";
    } elseif ($role === 'admin') {
        // SELECT StaffID and FirstName
        $sql = "SELECT StaffID, FirstName FROM STAFF WHERE UserID = ?";
    } else {
        return $details;
    }

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // FIX: Ensure two variables are bound to match the two columns selected (ID, Name)
            $fetched_pk_id = null;
            $fetched_name = null;
            mysqli_stmt_bind_result($stmt, $fetched_pk_id, $fetched_name);
            
            if (mysqli_stmt_fetch($stmt)) {
                $details['firstName'] = $fetched_name;
                $details['pk_id'] = $fetched_pk_id;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $details;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get raw POST data for modern Fetch API handling
    $input = file_get_contents('php://input');
    // The front-end uses URLSearchParams (key=value&key2=value2), so we parse it
    parse_str($input, $data);
    
    $username = $data['student_id'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'Please enter both username/ID and password.';
        echo json_encode($response);
        exit;
    }

    // Prepare a SQL statement to prevent SQL Injection
    $sql = "SELECT UserID, PasswordHash, Role FROM USERS WHERE Username = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind the username parameter
        mysqli_stmt_bind_param($stmt, "s", $param_username);
        $param_username = $username;

        // Execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 1) {
                // Bind result variables
                mysqli_stmt_bind_result($stmt, $user_id, $hashed_password, $role);
                mysqli_stmt_fetch($stmt);

                // Verify password
                if (password_verify($password, $hashed_password)) {
                    
                    // Password is correct, start a new session
                    session_regenerate_id();
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                    
                    // Fetch user's details (First Name and their specific PK ID)
                    $userDetails = fetch_user_details($link, $user_id, $role);
                    $firstName = $userDetails['firstName'];
                    $pk_id = $userDetails['pk_id'];
                    
                    // Set the specific primary key ID in the session (CRITICAL for other scripts)
                    if ($role === 'student' && $pk_id !== null) {
                        $_SESSION['student_id'] = $pk_id;
                    } elseif ($role === 'admin' && $pk_id !== null) {
                        $_SESSION['staff_id'] = $pk_id;
                    }

                    $response['success'] = true;
                    $response['message'] = "Login successful!";
                    $response['role'] = $role;
                    $response['firstName'] = $firstName;
                } else {
                    $response['message'] = 'Invalid password.';
                }
            } else {
                $response['message'] = 'Username not found.';
            }
        } else {
            $response['message'] = 'Database error during execution.';
        }

        // Close statement
        mysqli_stmt_close($stmt);
    }
}

// Close connection
mysqli_close($link);

// Output the final JSON response
echo json_encode($response);

?>