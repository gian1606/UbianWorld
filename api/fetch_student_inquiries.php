<?php
// api/fetch_student_inquiries.php

session_start();
require_once('../config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'data' => [], 'message' => 'Error fetching inquiries.'];

// 1. Authentication and Role Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'student') {
    $response['message'] = 'Access denied. You must be a logged-in student.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

$studentID = $_SESSION['student_id']; // Get StudentID from the session

// 2. Prepare the SQL Query
// Joins INQUIRY with INQUIRY_RESPONSE and STAFF to get the admin's name and response, if available.
$sql = "SELECT 
            I.InquiryID, 
            I.Subject, 
            I.Description, 
            I.DateSubmitted, 
            I.Status, 
            R.ResponseText,
            S.FirstName AS StaffFirstName,
            S.LastName AS StaffLastName
        FROM INQUIRY I
        LEFT JOIN INQUIRY_RESPONSE R ON I.InquiryID = R.InquiryID
        LEFT JOIN STAFF S ON R.StaffID = S.StaffID
        WHERE I.StudentID = ?
        ORDER BY I.DateSubmitted DESC"; // Newest inquiries first

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $studentID);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $inquiriesData = [];

        while ($row = mysqli_fetch_assoc($result)) {
            // Format the date for better client-side display
            $row['DateSubmittedFormatted'] = date('M j, Y H:i A', strtotime($row['DateSubmitted']));
            $inquiriesData[] = $row;
        }

        $response['success'] = true;
        $response['data'] = $inquiriesData;
        $response['message'] = 'Your inquiries fetched successfully.';
    } else {
        $response['message'] = 'Database error during execution.';
    }

    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Database error: Could not prepare statement.';
}

// Close connection and output response
mysqli_close($link);
echo json_encode($response);
?>