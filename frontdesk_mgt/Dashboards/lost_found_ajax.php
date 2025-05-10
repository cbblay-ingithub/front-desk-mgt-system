<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

global $conn;
header('Content-Type: application/json');
session_start();
require_once '../dbConfig.php'; // Provides $conn
require_once 'lost_found_functions.php';

/**
 * Sends a JSON response and exits.
 * @param bool $success
 * @param string $message
 * @param string $error
 * @param array $data Optional data to include
 */
function sendResponse($success, $message = '', $error = '', $data = []) {
    ob_clean();
    $response = [
        'success' => $success,
        'message' => $message,
        'error' => $error
    ];
    if (!empty($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    sendResponse(false, '', 'User not authenticated');
}

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_item':
        // Handle form submission with file upload
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = createItem($conn, $_POST, $_FILES);
            sendResponse($result['success'], $result['message'] ?? '', $result['error'] ?? '');
        } else {
            sendResponse(false, '', 'Invalid request method');
        }
        break;

    case 'get_item':
        // Fetch item details by ID
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $itemId = (int)$_GET['id'];
            $sql = "
                SELECT 
                    lf.ItemID,
                    lf.Description,
                    lf.Location,
                    lf.Status,
                    lf.LocationStored,
                    lf.PhotoPath,
                    ic.CategoryName
                FROM Lost_And_Found lf
                LEFT JOIN ItemCategories ic ON lf.CategoryID = ic.CategoryID
                WHERE lf.ItemID = ?
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("get_item: Failed to prepare statement: " . $conn->error);
                sendResponse(false, '', 'Database error');
            }
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($item = $result->fetch_assoc()) {
                error_log("get_item: Item fetched: " . print_r($item, true));
                sendResponse(true, '', '', $item);
            } else {
                error_log("get_item: Item not found for ID $itemId");
                sendResponse(false, '', 'Item not found');
            }
            $stmt->close();
        } else {
            error_log("get_item: Invalid item ID");
            sendResponse(false, '', 'Invalid item ID');
        }
        break;

    case 'update_item':
        // Update item details
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $itemId = isset($_POST['itemId']) ? (int)$_POST['itemId'] : 0;
            $description = $_POST['description'] ?? '';
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $location = $_POST['location'] ?? '';

            if ($itemId <= 0 || empty($description)) {
                sendResponse(false, '', 'Required fields are missing');
            }

            $sql = "
                UPDATE Lost_And_Found
                SET Description = ?, CategoryID = ?, Location = ?
                WHERE ItemID = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $description, $categoryId, $location, $itemId);
            if ($stmt->execute()) {
                sendResponse(true, 'Item updated successfully');
            } else {
                error_log("Failed to update item: " . $stmt->error);
                sendResponse(false, '', 'Failed to update item');
            }
            $stmt->close();
        } else {
            sendResponse(false, '', 'Invalid request method');
        }
        break;

    case 'resolve_item':
        // Resolve/claim an item
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $itemId = isset($_POST['itemId']) ? (int)$_POST['itemId'] : 0;
            $claimantName = $_POST['claimantName'] ?? '';
            $claimantContact = $_POST['claimantContact'] ?? '';
            $claimantId = $_POST['claimantId'] ?? '';

            if ($itemId <= 0 || empty($claimantName) || empty($claimantContact) || empty($claimantId)) {
                sendResponse(false, '', 'Required fields are missing');
            }

            $sql = "
                UPDATE Lost_And_Found
                SET Status = 'resolved', ClaimantName = ?, ClaimantContact = ?, ClaimantID = ?, DateResolved = NOW()
                WHERE ItemID = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $claimantName, $claimantContact, $claimantId, $itemId);
            if ($stmt->execute()) {
                sendResponse(true, 'Item resolved successfully');
            } else {
                error_log("Failed to resolve item: " . $stmt->error);
                sendResponse(false, '', 'Failed to resolve item');
            }
            $stmt->close();
        } else {
            sendResponse(false, '', 'Invalid request method');
        }
        break;

    case 'dispose_item':
        // Dispose an item
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $itemId = (int)$_GET['id'];
            $sql = "UPDATE Lost_And_Found SET Status = 'disposed' WHERE ItemID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $itemId);
            if ($stmt->execute()) {
                sendResponse(true, 'Item disposed successfully');
            } else {
                error_log("Failed to dispose item: " . $stmt->error);
                sendResponse(false, '', 'Failed to dispose item');
            }
            $stmt->close();
        } else {
            sendResponse(false, '', 'Invalid item ID');
        }
        break;

    default:
        sendResponse(false, '', 'Invalid action');
}

// Close database connection
$conn->close();
?>