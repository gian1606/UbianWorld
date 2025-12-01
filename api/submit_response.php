<?php
// api/submit_response.php

session_start();
require_once('../config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid Request.'];

// 1. Authentication and Role Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Access denied. Must be a logged-in admin.';
    http_response_code(401); 
    echo json_encode($response);
    exit;
}

$staffID = $_SESSION['staff_id'] ?? 1; // Get Admin's StaffID from session

// 2. Get data from POST request (use file_get_contents for PUT/POST payload)
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    parse_str(file_get_contents('php://input'), $data);
} else {
    echo json_encode($response);
    exit;
}

// 3. Input Validation
$requiredFields = ['inquiry_id', 'response_text', 'new_status'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        $response['message'] = "Missing required field: " . $field;
        echo json_encode($response);
        exit;
    }
}

$inquiryID = (int)$data['inquiry_id'];
$responseText = trim($data['response_text']);
$newStatus = trim($data['new_status']);
$validStatuses = ['Pending', 'In-Progress', 'Resolved'];

if (!in_array($newStatus, $validStatuses)) {
    $response['message'] = 'Invalid status value.';
    echo json_encode($response);
    exit;
}

// 4. Start Transaction
$link->begin_transaction();

try {
    // A. Update the INQUIRY table (Status and assign StaffID)
    $sql_update_inquiry = "UPDATE INQUIRY SET StaffID = ?, Status = ? WHERE InquiryID = ?";
    if ($stmt = mysqli_prepare($link, $sql_update_inquiry)) {
        mysqli_stmt_bind_param($stmt, "isi", $staffID, $newStatus, $inquiryID);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to update inquiry status.');
        }
        mysqli_stmt_close($stmt);
    } else {
        throw new Exception('Failed to prepare inquiry update statement.');
    }

    // B. Insert/Update the INQUIRY_RESPONSE table (Response Text)
    // We use REPLACE INTO: if a response exists for this InquiryID, it updates it. Otherwise, it inserts a new one.
    $sql_response = "REPLACE INTO INQUIRY_RESPONSE (InquiryID, StaffID, ResponseText) VALUES (?, ?, ?)";
    if ($stmt_resp = mysqli_prepare($link, $sql_response)) {
        mysqli_stmt_bind_param($stmt_resp, "iis", $inquiryID, $staffID, $responseText);
        if (!mysqli_stmt_execute($stmt_resp)) {
            throw new Exception('Failed to insert/update response.');
        }
        mysqli_stmt_close($stmt_resp);
    } else {
        throw new Exception('Failed to prepare response statement.');
    }

    // 5. Commit Transaction
    $link->commit();
    $response['success'] = true;
    $response['message'] = "Inquiry #{$inquiryID} resolved, status updated to '{$newStatus}', and response sent.";

} catch (Exception $e) {
    // Rollback on any error
    $link->rollback();
    $response['message'] = 'Transaction failed: ' . $e->getMessage();
}

// 6. Close connection and output response
mysqli_close($link);
echo json_encode($response);
?>