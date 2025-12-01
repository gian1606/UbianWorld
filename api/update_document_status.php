<?php
// api/upload_document.php

session_start();
require_once('../config.php');
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Document upload failed.'];

// 1. Authentication Check: Must be a logged-in student
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'student') {
    $response['message'] = 'Access denied. You must be a logged-in student to upload documents.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$student_user_id = $_SESSION['user_id'];
// IMPORTANT: Ensure this directory exists one level above 'api/'
$uploadDir = '../uploads/documents/'; 

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 2. Check for POST Request and File Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    
    $file = $_FILES['document_file'];
    $originalFileName = basename($file['name']);
    $fileType = $file['type'];
    $fileSize = $file['size'];
    $tempFilePath = $file['tmp_name'];
    $uploadError = $file['error'];

    // Basic file validation (5MB limit)
    if ($uploadError !== UPLOAD_ERR_OK) {
        $response['message'] = 'File upload error: ' . $uploadError;
        echo json_encode($response);
        exit;
    }

    if ($fileSize > 5 * 1024 * 1024) { 
        $response['message'] = 'File size exceeds the 5MB limit (5MB max).';
        echo json_encode($response);
        exit;
    }
    
    // Generate a unique, secure filename for server storage
    $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $safeFileName = uniqid('doc_', true) . '.' . $extension;
    $targetFilePath = $uploadDir . $safeFileName;

    // 3. Get StudentID from UserID
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

    // Start Transaction for atomic file/DB operation
    mysqli_begin_transaction($link);
    
    try {
        // 4. Move the file
        if (!move_uploaded_file($tempFilePath, $targetFilePath)) {
            throw new Exception('Failed to move uploaded file to target directory.');
        }

        // 5. Insert document record. FilePath is the unique name on the server.
        $sql_insert = "INSERT INTO DOCUMENT (StudentID, FileName, FileType, FilePath, ReviewStatus) VALUES (?, ?, ?, ?, 'Pending')";
        
        if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
            mysqli_stmt_bind_param($stmt_insert, "isss", $student_id, $originalFileName, $fileType, $safeFileName);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                mysqli_commit($link);
                $response['success'] = true;
                $response['message'] = "Document '{$originalFileName}' successfully uploaded and submitted for review.";
            } else {
                throw new Exception('Database error: Failed to insert document record. ' . mysqli_error($link));
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            throw new Exception('Database error: Could not prepare insertion statement.');
        }
    } catch (Exception $e) {
        mysqli_rollback($link);
        // Attempt to clean up the file if it was moved but DB insert failed
        if (file_exists($targetFilePath)) {
            unlink($targetFilePath);
        }
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid file upload request.';
}

mysqli_close($link);
echo json_encode($response);