<?php
// api/fetch_inquiries.php

// 1. Start session management
session_start();

// 2. Include database configuration
require_once('../config.php');

// 3. Set header for JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'data' => [], 'message' => 'Error fetching inquiries.'];

// 4. Authentication and Role Check
// Only a logged-in Admin (ISSO Staff) should be able to access this data
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Access denied. You must be logged in as an ISSO Staff member.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// 5. Prepare the SQL Query
// Joins INQUIRY with STUDENT and USER to get the student's name and username (student ID)
$sql = "SELECT 
            I.InquiryID, 
            I.Subject, 
            I.Description, 
            I.DateSubmitted, 
            I.Status, 
            I.StaffID,
            S.FirstName, 
            S.LastName, 
            U.Username AS StudentUsername 
        FROM INQUIRY I
        JOIN STUDENT S ON I.StudentID = S.StudentID
        JOIN USER U ON S.UserID = U.UserID
        ORDER BY I.DateSubmitted DESC"; // Newest inquiries first

if ($result = mysqli_query($link, $sql)) {
    $inquiriesData = [];

    // Fetch all resulting rows
    while ($row = mysqli_fetch_assoc($result)) {
        // Format the date for better client-side display
        $row['DateSubmittedFormatted'] = date('M j, Y H:i A', strtotime($row['DateSubmitted']));
        $inquiriesData[] = $row;
    }

    // Free result set
    mysqli_free_result($result);

    $response['success'] = true;
    $response['data'] = $inquiriesData;
    $response['message'] = 'Inquiries fetched successfully.';
} else {
    $response['message'] = 'Database error: Could not execute query.';
}

// 6. Close connection and output response
mysqli_close($link);
echo json_encode($response);
?>