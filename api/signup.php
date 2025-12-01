<?php
// api/signup.php

// 1. Include database configuration
require_once('../config.php');

// 2. Set header for JSON response
header('Content-Type: application/json');

// Define the response array
$response = ['success' => false, 'message' => 'Registration failed.'];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Use $_POST directly since the front-end script.js uses FormData
    $username = trim($_POST['student_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');

    // 3. Basic Validation
    if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $response['message'] = 'All required fields must be filled out.';
        echo json_encode($response);
        exit;
    }
    
    // --- Security Step: Hash the password ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 4. Check if Username (Student ID) or Email already exists
    $check_sql = "SELECT UserID FROM USERS WHERE Username = ? OR Email = ?";
    
    if ($stmt = mysqli_prepare($link, $check_sql)) {
        // FIX: The original was likely checking $username twice. Now it checks $username AND $email.
        mysqli_stmt_bind_param($stmt, "ss", $username, $email); 
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $response['message'] = 'User ID or Email already registered.';
                mysqli_stmt_close($stmt);
                echo json_encode($response);
                exit;
            }
        }
        mysqli_stmt_close($stmt);
    }

    // 5. Begin Transaction
    $link->begin_transaction();

    // 6. Insert into USERS table
    $user_sql = "INSERT INTO USERS (Username, Email, PasswordHash, Role) VALUES (?, ?, ?, 'student')";
    
    if ($stmt_users = mysqli_prepare($link, $user_sql)) {
        mysqli_stmt_bind_param($stmt_users, "sss", $username, $email, $hashed_password);
        
        if (mysqli_stmt_execute($stmt_users)) {
            $new_user_id = mysqli_insert_id($link);

            // Insert into STUDENT table
            $student_sql = "INSERT INTO STUDENT (UserID, FirstName, LastName, Email, Nationality) VALUES (?, ?, ?, ?, ?)";
            
            if ($stmt_student = mysqli_prepare($link, $student_sql)) {
                // Binding: i (UserID), s (FirstName), s (LastName), s (Email), s (Nationality)
                mysqli_stmt_bind_param($stmt_student, "issss", $new_user_id, $firstName, $lastName, $email, $nationality);
                
                if (mysqli_stmt_execute($stmt_student)) {
                    // Both inserts succeeded
                    $link->commit();
                    $response['success'] = true;
                    $response['message'] = 'Registration successful! You can now log in.';
                } else {
                    // Student insert failed, roll back the USERS insert
                    $link->rollback();
                    $response['message'] = 'Registration failed during student data insertion: ' . mysqli_error($link);
                }
                mysqli_stmt_close($stmt_student);
            } else {
                // Prepared statement failed for STUDENT
                $link->rollback();
                $response['message'] = 'Database error: Could not prepare student insertion.';
            }
        } else {
            // USERS insert failed
            $response['message'] = 'Database error: Could not register user ID: ' . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_users);
    } else {
        $response['message'] = 'Database error: Could not prepare user insertion.';
    }

}

// 7. Close connection and output response
mysqli_close($link);
echo json_encode($response);

?>