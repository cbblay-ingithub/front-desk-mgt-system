<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../dbConfig.php'; // Assumes dbConfig.php provides getDbConnection()

/**
 * Fetches all lost and found items with related user and category details.
 *
 * @param mysqli $conn Database connection object
 * @return array List of items or empty array on failure
 */
function getItems($conn) {
    $sql = "
        SELECT lf.*, u.Name AS ReportedByName, ic.CategoryName 
        FROM Lost_And_Found lf
        LEFT JOIN Users u ON lf.ReportedBy = u.UserID
        LEFT JOIN ItemCategories ic ON lf.CategoryID = ic.CategoryID
    ";
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Failed to fetch items: " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fetches all item categories.
 *
 * @param mysqli $conn Database connection object
 * @return array List of categories or empty array on failure
 */
function getItemCategories($conn) {
    $sql = "SELECT CategoryID, CategoryName FROM ItemCategories";
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Failed to fetch categories: " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Creates a new lost or found item, including optional photo upload.
 *
 * @param mysqli $conn Database connection object
 * @param array $data Form data (status, description, location, etc.)
 * @param array $file Uploaded file data (photo)
 * @return array Result with success status and message
 */
function createItem($conn, $data, $file) {
    error_log("createItem: Starting");

    // Validate required fields
    if (empty($data['status']) || empty($data['description']) || empty($data['location'])) {
        error_log("createItem: Required fields are missing");
        return ['success' => false, 'error' => 'Required fields are missing'];
    }

    // Handle photo upload if provided
    $photoPath = null;
    if (!empty($file['photo']['tmp_name'])) {
        error_log("createItem: Photo upload detected");

        $uploadDir = __DIR__ . '/../../Uploads/lost_found';
        error_log("createItem: Upload directory: $uploadDir");

        if (!is_dir($uploadDir)) {
            error_log("createItem: Upload directory does not exist: $uploadDir");
            return ['success' => false, 'error' => 'Upload directory does not exist: ' . $uploadDir];
        }

        if (!is_writable($uploadDir)) {
            error_log("createItem: Upload directory is not writable: $uploadDir");
            return ['success' => false, 'error' => 'Upload directory is not writable: ' . $uploadDir];
        }

        $fileName = basename($file['photo']['name']);
        $uploadPath = $uploadDir . '/' . $fileName;
        error_log("createItem: Attempting to move file to: $uploadPath");

        if (move_uploaded_file($file['photo']['tmp_name'], $uploadPath)) {
            error_log("createItem: File moved successfully to $uploadPath");
            $photoPath = '/Uploads/lost_found/' . $fileName; // Store relative path for web access
        } else {
            error_log("createItem: Failed to move file to $uploadPath");
            return ['success' => false, 'error' => 'Failed to upload file'];
        }
    } else {
        error_log("createItem: No photo uploaded");
    }

    // Prepare SQL insert statement
    $sql = "
        INSERT INTO Lost_And_Found (
            DateReported, ReportedBy, Description, CategoryID, Location, Status, PhotoPath, LocationStored
        ) VALUES (
            NOW(), ?, ?, ?, ?, ?, ?, ?
        )
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("createItem: Failed to prepare statement: " . $conn->error);
        return ['success' => false, 'error' => 'Database error: ' . $conn->error];
    }

    // Bind parameters
    $reportedBy = isset($data['reported_by']) ? (int)$data['reported_by'] : 0;
    $description = $data['description'];
    $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
    $location = $data['location'];
    $status = $data['status'];
    $locationStored = !empty($data['location_stored']) ? $data['location_stored'] : null;

    if ($reportedBy === 0) {
        error_log("createItem: Invalid user ID");
        return ['success' => false, 'error' => 'Invalid user ID'];
    }

    $stmt->bind_param("ississs", $reportedBy, $description, $categoryId, $location, $status, $photoPath, $locationStored);

    if ($stmt->execute()) {
        error_log("createItem: Item logged successfully");
        return ['success' => true, 'message' => 'Item logged successfully'];
    } else {
        error_log("createItem: Failed to insert item: " . $stmt->error);
        return ['success' => false, 'error' => 'Failed to log item: ' . $stmt->error];
    }
}
?>