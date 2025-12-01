<?php
// api/generate_report.php

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

$reportData = [];

// --- Metric 1: Total Student Count ---
$sql_students = "SELECT COUNT(StudentID) AS TotalStudents FROM STUDENT";
if ($result = mysqli_query($link, $sql_students)) {
    $row = mysqli_fetch_assoc($result);
    $reportData['TotalStudents'] = (int)$row['TotalStudents'];
    mysqli_free_result($result);
} else {
    $reportData['TotalStudents'] = 'N/A';
}

// --- Metric 2: Inquiry Status Summary (Open/Closed) ---
$sql_inquiries = "SELECT Status, COUNT(InquiryID) AS Count FROM INQUIRY GROUP BY Status";
$inquirySummary = [];
$totalOpenInquiries = 0;

if ($result = mysqli_query($link, $sql_inquiries)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $inquirySummary[$row['Status']] = (int)$row['Count'];
        if ($row['Status'] !== 'Closed') {
            $totalOpenInquiries += (int)$row['Count'];
        }
    }
    mysqli_free_result($result);
}
$reportData['InquirySummary'] = $inquirySummary;
$reportData['TotalOpenInquiries'] = $totalOpenInquiries;


// --- Metric 3: Document Review Status Summary ---
$sql_documents = "SELECT ReviewStatus, COUNT(DocumentID) AS Count FROM DOCUMENT GROUP BY ReviewStatus";
$documentSummary = [];
$totalPendingDocuments = 0;

if ($result = mysqli_query($link, $sql_documents)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $documentSummary[$row['ReviewStatus']] = (int)$row['Count'];
        if ($row['ReviewStatus'] === 'Pending') {
            $totalPendingDocuments += (int)$row['Count'];
        }
    }
    mysqli_free_result($result);
}
$reportData['DocumentSummary'] = $documentSummary;
$reportData['TotalPendingDocuments'] = $totalPendingDocuments;


// --- Final Response ---
if (!empty($reportData)) {
    $response['success'] = true;
    $response['data'] = $reportData;
    $response['message'] = 'Report data fetched successfully.';
} else {
    $response['message'] = 'Could not generate report data.';
    http_response_code(500); 
}

if (isset($link)) mysqli_close($link);
echo json_encode($response);
?>