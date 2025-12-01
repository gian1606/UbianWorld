<?php
// api/submit_inquiry.php

// 1. Start session management
session_start();

// 2. Include database configuration and access check
require_once('../config.php');

// 3. Set header for JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Inquiry submission failed.'];

// 4. Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'student') {
    $response['message'] = 'Access denied. You must be a logged-in student to submit an inquiry.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// Get student's ID from the session
$student_user_id = $_SESSION['user_id'];

// 5. Check for POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get data from the POST request (front-end should send Subject and Description)
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // 6. Validation
    if (empty($subject) || empty($description)) {
        $response['message'] = 'Both the Subject and Description fields are required.';
        echo json_encode($response);
        exit;
    }
    
    // 7. Get the actual StudentID (PK) from the STUDENT table using the UserID (FK)
    $student_id = null;
    $sql_get_student_id = "SELECT StudentID FROM STUDENT WHERE UserID = ?";
    
    if ($stmt_id = mysqli_prepare($link, $sql_get_student_id)) {
        mysqli_stmt_bind_param($stmt_id, "i", $student_user_id);
        mysqli_stmt_execute($stmt_id);
        mysqli_stmt_bind_result($stmt_id, $student_id);
        mysqli_stmt_fetch($stmt_id);
        mysqli_stmt_close($stmt_id);
    }
    
    if (!$student_id) {
        $response['message'] = 'System error: Could not find matching student record.';
        echo json_encode($response);
        exit;
    }

    // 8. Insert Inquiry into the INQUIRY table
    // Status defaults to 'pending' in the database structure
    $sql_insert = "INSERT INTO INQUIRY (StudentID, Subject, Description) VALUES (?, ?, ?)";
    
    if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
        mysqli_stmt_bind_param($stmt_insert, "iss", $student_id, $subject, $description);
        
        if (mysqli_stmt_execute($stmt_insert)) {
            $response['success'] = true;
            $response['message'] = 'Your inquiry has been successfully submitted! An ISSO Staff member will review it shortly.';
        } else {
            $response['message'] = 'Database error: Failed to insert inquiry.';
        }
        mysqli_stmt_close($stmt_insert);
    } else {
        $response['message'] = 'Database error: Could not prepare inquiry insertion.';
    }
}

// 9. Close connection and output response
mysqli_close($link);
echo json_encode($response);
?>