<?php
// api/update_student_record.php

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

// Function to read raw JSON body for PUT/DELETE
function get_put_data() {
    parse_str(file_get_contents('php://input'), $data);
    return $data;
}
$data = get_put_data();

// Input Validation
$requiredFields = ['student_id', 'first_name', 'last_name', 'email', 'nationality'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        $response['message'] = "Missing required field: " . $field;
        echo json_encode($response);
        exit;
    }
}

$studentID = (int)$data['student_id'];
$firstName = trim($data['first_name']);
$lastName = trim($data['last_name']);
$email = trim($data['email']);
$nationality = trim($data['nationality']);

// Prepare the SQL Update
$sql = "UPDATE STUDENT SET FirstName = ?, LastName = ?, Email = ?, Nationality = ? WHERE StudentID = ?";
        
if ($stmt = mysqli_prepare($link, $sql)) {
    // Bind parameters: 4 strings, 1 integer
    mysqli_stmt_bind_param($stmt, "ssssi", $firstName, $lastName, $email, $nationality, $studentID);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $response['success'] = true;
            $response['message'] = "Student record for ID {$studentID} successfully updated.";
        } else {
            // Note: If no rows were affected, it means the ID was found but no changes were made.
            $response['success'] = true; 
            $response['message'] = "Student record for ID {$studentID} found, but no changes were made.";
        }
    } else {
        $response['message'] = 'Database execute error: Could not update student record. ' . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Database prepare error: Could not prepare update query.';
}

if (isset($link)) mysqli_close($link);
echo json_encode($response);
?>