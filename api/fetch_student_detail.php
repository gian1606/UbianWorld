<?php
// api/fetch_student_detail.php

session_start();
require_once('../config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Access denied or Invalid Request.'];

// Authentication and Role Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(401); 
    echo json_encode($response);
    exit;
}

// Input validation
if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    $response['message'] = 'Missing or invalid Student ID.';
    echo json_encode($response);
    exit;
}

$studentID = (int) $_GET['student_id'];

// SQL Query to get student and linked user data
$sql = "SELECT 
            s.StudentID, 
            s.FirstName, 
            s.LastName, 
            s.Email, 
            s.Nationality, 
            u.Username AS StudentUsername 
        FROM 
            STUDENT s
        JOIN 
            USER u ON s.UserID = u.UserID
        WHERE 
            s.StudentID = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $studentID);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($student = mysqli_fetch_assoc($result)) {
            $response['success'] = true;
            $response['data'] = $student;
            $response['message'] = 'Student details fetched successfully.';
        } else {
            $response['message'] = 'Student not found.';
        }
        mysqli_free_result($result);
    } else {
        $response['message'] = 'Database execute error: ' . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Database prepare error: Could not prepare query.';
}

if (isset($link)) mysqli_close($link);
echo json_encode($response);
?>