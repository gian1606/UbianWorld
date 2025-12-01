<?php
// api/download_document.php

session_start();
require_once('../config.php');

// 1. Authentication Check: Must be a logged-in admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Access denied. You must be logged in as an ISSO Staff member.']));
}

// 2. Input Validation
if (!isset($_GET['document_id']) || !is_numeric($_GET['document_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing or invalid Document ID.']));
}

$documentID = (int) $_GET['document_id'];
$uploadDir = '../uploads/documents/';

// 3. Query the database for the file information
$sql = "SELECT FileName, FilePath FROM DOCUMENT WHERE DocumentID = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $documentID);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $originalFileName, $storedFileName);
        
        if (mysqli_stmt_fetch($stmt)) {
            // Document record found
            $fullPath = $uploadDir . $storedFileName;

            // 4. Check if the file exists on the server
            if (file_exists($fullPath)) {
                // 5. Set HTTP headers for file download
                header('Content-Description: File Transfer');
                // Use a generic type to ensure browser downloads the file
                header('Content-Type: application/octet-stream'); 
                header('Content-Disposition: attachment; filename="' . basename($originalFileName) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fullPath));
                
                // 6. Output the file contents
                ob_clean();
                flush();
                readfile($fullPath);
                
                mysqli_stmt_close($stmt);
                mysqli_close($link);
                exit;
            } else {
                http_response_code(404);
                die(json_encode(['success' => false, 'message' => 'Error: File not found on the server.']));
            }
        } else {
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'Document ID not found in database.']));
        }
    } else {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database execution error: ' . mysqli_error($link)]));
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database prepare error.']));
}

mysqli_close($link);
?>