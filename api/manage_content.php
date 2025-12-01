<?php
// api/manage_content.php

// 1. Start session management
session_start();

// 2. Include database configuration
require_once('../config.php');

// 3. Set header for JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid Request Method or Content Type.'];

// 4. Authentication and Role Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Access denied. You must be logged in as an ISSO Staff member.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Function to read raw body for PUT/DELETE requests
function get_put_delete_data() {
    // Reads URL-encoded data from the request body (for PUT/DELETE via fetch API)
    parse_str(file_get_contents('php://input'), $data);
    return $data;
}

// --- FIX APPLIED HERE ---
// Get the correct StaffID from the session, which should be set by login.php
$staffID = $_SESSION['staff_id'] ?? 1; // Use staff_id, fallback to 1 as a safety measure for testing

// =================================================================================
// POST: CREATE NEW CONTENT (DFD 4.1: Log new News/Events/Guide/FAQ)
// =================================================================================
if ($method === 'POST') {
    
    // 5. Input Validation (Using $_POST for standard form submission)
    $requiredFields = ['title', 'type', 'content_text'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $response['message'] = "Missing required field: " . $field;
            echo json_encode($response);
            exit;
        }
    }
    
    $title = trim($_POST['title']);
    $type = trim($_POST['type']);
    $contentText = trim($_POST['content_text']);
    $contentURL = trim($_POST['content_url'] ?? null);

    // 6. Check for valid Type
    $validTypes = ['Announcement', 'Guide', 'FAQ'];
    if (!in_array($type, $validTypes)) {
        $response['message'] = 'Invalid content type provided.';
        echo json_encode($response);
        exit;
    }
    
    // 7. Insert the new content
    $sql = "INSERT INTO CONTENT (StaffID, Title, Type, ContentText, ContentURL) VALUES (?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "issss", $staffID, $title, $type, $contentText, $contentURL);
        
        if (mysqli_stmt_execute($stmt)) {
            $response['success'] = true;
            $response['resourceID'] = mysqli_insert_id($link);
            $response['message'] = "New {$type} posted successfully.";
        } else {
            $response['message'] = 'Database error: Could not insert new content. ' . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Database error: Could not prepare insert statement.';
    }

} 

// =================================================================================
// PUT: UPDATE EXISTING CONTENT (DFD 4.2: Edit News/Events/Guide/FAQ)
// =================================================================================
elseif ($method === 'PUT') {
    
    $data = get_put_delete_data();
    
    // 5. Input Validation
    $requiredFields = ['resource_id', 'title', 'type', 'content_text'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $response['message'] = "Missing required field for update: " . $field;
            echo json_encode($response);
            exit;
        }
    }
    
    $resourceID = (int)$data['resource_id'];
    $title = trim($data['title']);
    $type = trim($data['type']);
    $contentText = trim($data['content_text']);
    $contentURL = trim($data['content_url'] ?? null);
    
    $validTypes = ['Announcement', 'Guide', 'FAQ'];
    if (!in_array($type, $validTypes)) {
        $response['message'] = 'Invalid content type provided for update.';
        echo json_encode($response);
        exit;
    }

    // 6. Update the content
    // Note: StaffID is not updated here, as the original poster remains logged. DatePosted is not changed.
    $sql = "UPDATE CONTENT SET Title = ?, Type = ?, ContentText = ?, ContentURL = ? WHERE ResourceID = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssssi", $title, $type, $contentText, $contentURL, $resourceID);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $response['success'] = true;
                $response['message'] = "Content ID: {$resourceID} successfully updated.";
            } else {
                 $response['success'] = true; // Still a success, just no change
                $response['message'] = "Content ID: {$resourceID} found, but no changes were made.";
            }
        } else {
            $response['message'] = 'Database error: Could not update content. ' . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Database error: Could not prepare update statement.';
    }
}


// =================================================================================
// DELETE: DELETE CONTENT (DFD 4.3: Remove News/Events/Guide/FAQ)
// =================================================================================
elseif ($method === 'DELETE') {
    $data = get_put_delete_data();

    // 5. Input Validation
    if (!isset($data['resource_id']) || !is_numeric($data['resource_id'])) {
        $response['message'] = 'Missing or invalid Resource ID for deletion.';
        echo json_encode($response);
        exit;
    }

    $resourceID = (int)$data['resource_id'];
    
    // 6. Get content info before deleting to check if it's a 'Guide' with a local file
    $info_sql = "SELECT Type, ContentURL FROM CONTENT WHERE ResourceID = ?";
    $type = null;
    $contentURL = null;

    if ($stmt_info = mysqli_prepare($link, $info_sql)) {
        mysqli_stmt_bind_param($stmt_info, "i", $resourceID);
        mysqli_stmt_execute($stmt_info);
        mysqli_stmt_bind_result($stmt_info, $type, $contentURL);
        mysqli_stmt_fetch($stmt_info);
        mysqli_stmt_close($stmt_info);
    }
    
    if (!$type) {
        $response['message'] = "Content ID: {$resourceID} not found or error retrieving data.";
        echo json_encode($response);
        exit;
    }

    // 7. Delete the content record
    $sql = "DELETE FROM CONTENT WHERE ResourceID = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $resourceID);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {

                // 8. If it was a Guide/Form, attempt to remove the local file if the URL is relative
                if ($type === 'Guide' && !empty($contentURL) && strpos($contentURL, '://') === false) {
                    $filePath = '../' . $contentURL; 
                    if (file_exists($filePath)) {
                        if (@unlink($filePath)) { // Use @ to suppress file not found errors
                            $response['message'] = "Content ID: {$resourceID} deleted. Associated file also removed.";
                        } else {
                            $response['message'] = "Content ID: {$resourceID} deleted. WARNING: Could not delete associated file.";
                        }
                    } else {
                        $response['message'] = "Content ID: {$resourceID} deleted. File path found, but file did not exist on server.";
                    }
                } else {
                    $response['message'] = "Content ID: {$resourceID} successfully deleted.";
                }
                
                $response['success'] = true;

            } else {
                $response['message'] = "Content ID: {$resourceID} not found.";
            }
        } else {
            $response['message'] = 'Database execute error: Could not delete content. ' . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Database prepare error: Could not prepare delete query.';
    }
}


// 7. Close connection and output response
if (isset($link)) mysqli_close($link);
echo json_encode($response);