<?php
// api/fetch_students.php

// 1. Start session management
session_start();

// 2. Include database configuration
require_once('../config.php');

// 3. Set header for JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Access denied or Invalid Request.'];

// 4. Authentication and Role Check
// Only a logged-in Admin (ISSO Staff) should be able to view student records
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// 5. Build the SQL Query
// Join STUDENT table with the USER table to get username (Student ID) and associated data.
// Assumption: StudentID in STUDENT table links to a UserID in the USER table, OR the StudentID itself is the username. 
// Based on the ER Diagram: STUDENT (PK StudentID, FK UserID). USER (PK UserID, Username).
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
        ORDER BY 
            s.LastName, s.FirstName";

$students = [];

// 6. Execute the Query
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
        $response['success'] = true;
        $response['data'] = $students;
        $response['count'] = count($students);
        $response['message'] = 'Student records fetched successfully.';
    } else {
        $response['message'] = 'No international student records found.';
        $response['count'] = 0;
        $response['data'] = [];
        $response['success'] = true; // Still a success if the list is empty
    }
    mysqli_free_result($result);
} else {
    $response['message'] = 'Database query failed: ' . mysqli_error($link);
    http_response_code(500); // Internal Server Error
}


// 7. Close connection and output response
if (isset($link)) mysqli_close($link);
echo json_encode($response);
?>