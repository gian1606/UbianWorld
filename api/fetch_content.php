<?php
// api/fetch_content.php

// Include database configuration
require_once('../config.php');

// Set header for JSON response
header('Content-Type: application/json');

// Define the response array
$response = ['success' => false, 'data' => [], 'message' => 'Error fetching content.'];

// 1. Get the content type from the URL query parameters
$contentType = $_GET['type'] ?? '';

// 2. Validate the content type
$validTypes = ['Announcement', 'Guide', 'FAQ'];

if (!in_array($contentType, $validTypes)) {
    // If no type is specified or it's invalid, fetch all content (or handle as an error)
    // For this case, we'll return an error if no specific type is requested.
    $response['message'] = 'Invalid or missing content type parameter (e.g., ?type=Announcement).';
    echo json_encode($response);
    exit;
}

// 3. Prepare the SQL query
// We order by DatePosted descending to get the newest content first.
$sql = "SELECT ResourceID, Title, Type, ContentText, ContentURL, DatePosted 
        FROM CONTENT 
        WHERE Type = ? 
        ORDER BY DatePosted DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    // Bind the content type parameter
    mysqli_stmt_bind_param($stmt, "s", $contentType);

    // Execute the statement
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $contentData = [];

        // Fetch all resulting rows
        while ($row = mysqli_fetch_assoc($result)) {
            // Format the date for better client-side display
            $row['DatePostedFormatted'] = date('M j, Y', strtotime($row['DatePosted']));
            $contentData[] = $row;
        }

        $response['success'] = true;
        $response['data'] = $contentData;
        $response['message'] = 'Content fetched successfully.';
    } else {
        $response['message'] = 'Database error during execution.';
    }

    // Close statement
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Database error: Could not prepare statement.';
}

// Close connection and output response
mysqli_close($link);
echo json_encode($response);
?>