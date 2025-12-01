<?php
// api/update_inquiry_status.php

// 1. Start session management
session_start();

// 2. Include database configuration
require_once('../config.php');

// 3. Set header for JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid Request.'];

// 4. Authentication and Role Check
// Only a logged-in Admin (ISSO Staff) should be able to update statuses
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Access denied. You must be logged in as an ISSO Staff member.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// 5. Input Validation
if (!isset($_POST['inquiry_id']) || !isset($_POST['status'])) {
    $response['message'] = 'Missing Inquiry ID or Status.';
    echo json_encode($response);
    exit;
}

$inquiryID = (int)$_POST['inquiry_id'];
$newStatus = trim($_POST['status']);
$validStatuses = ['Pending', 'In-Progress', 'Resolved'];

if (!in_array($newStatus, $validStatuses)) {
    $response['message'] = 'Invalid status value.';
    echo json_encode($response);
    exit;
}

// 6. Prepare the SQL Update
// We are updating the Status based on the InquiryID provided.
// (In a full system, we would also update the StaffID to log who took the action.)
$sql = "UPDATE INQUIRY SET Status = ? WHERE InquiryID = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "si", $newStatus, $inquiryID);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $response['success'] = true;
            $response['message'] = "Inquiry #{$inquiryID} status successfully updated to '{$newStatus}'.";
        } else {
            $response['message'] = "Inquiry #{$inquiryID} not found or status is already '{$newStatus}'.";
        }
    } else {
        $response['message'] = 'Database execute error: ' . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Database prepare error: ' . mysqli_error($link);
}

// 7. Close connection and output response
mysqli_close($link);
echo json_encode($response);
?>