<?php
// Start output buffering and error handling at the very beginning
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Include your database configuration
global $conn;
require_once '../dbConfig.php';

// Include ticket functions
require_once 'ticket_functions.php';
require_once 'ticket_ops.php';
require_once 'view_ticket.php';

// Start session to access user data
session_start();

// Set JSON header immediately
header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => 'Invalid request', 'html' => ''];

try {
    // Clear any output that might have been generated
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Log session data for debugging
    error_log("Session data in ticket_ajax.php: " . json_encode([
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'userID' => $_SESSION['userID'] ?? 'not set',
            'role' => $_SESSION['role'] ?? 'not set'
        ]));

    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get_ticket_details':
                    if (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
                        $ticketDetail = getTicketDetails($conn, $_GET['ticket_id']);
                        if ($ticketDetail) {
                            $response['success'] = true;
                            $response['html'] = generateTicketDetailsHTML($ticketDetail);
                        } else {
                            $response['message'] = 'Ticket not found';
                        }
                    }
                    break;

                case 'get_ticket_print':
                    if (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
                        $ticketDetail = getTicketDetails($conn, $_GET['ticket_id']);
                        if ($ticketDetail) {
                            $response['success'] = true;
                            $response['html'] = generateTicketPrintHTML($ticketDetail);
                        } else {
                            $response['message'] = 'Ticket not found';
                        }
                    }
                    break;

                case 'get_assign_modal':
                    if (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
                        $users = getUsers($conn);
                        $response['success'] = true;
                        $response['html'] = generateAssignTicketModalHTML($_GET['ticket_id'], $users);
                    }
                    break;

                case 'get_resolve_modal':
                    if (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
                        $response['success'] = true;
                        $response['html'] = generateResolveTicketModalHTML($_GET['ticket_id']);
                    }
                    break;
            }
        }
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'close_ticket':
                    if (isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id'])) {
                        $ticketId = $_POST['ticket_id'];

                        // Update ticket status to "closed"
                        $sql = "UPDATE Help_Desk SET Status = 'closed', LastUpdated = NOW() WHERE TicketID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $ticketId);

                        if ($stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Ticket #$ticketId has been closed!";
                        } else {
                            $response['message'] = "Error closing ticket: " . $conn->error;
                        }
                        $stmt->close();
                    }
                    break;

                case 'assign_ticket':
                    if (isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id']) && isset($_POST['assigned_to'])) {
                        $ticketId = $_POST['ticket_id'];
                        $assignedTo = $_POST['assigned_to'];

                        // Update ticket assignment and status
                        $sql = "UPDATE Help_Desk SET AssignedTo = ?, Status = 'in-progress', LastUpdated = NOW() WHERE TicketID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $assignedTo, $ticketId);

                        if ($stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Ticket #$ticketId has been assigned and marked as in-progress!";
                        } else {
                            $response['message'] = "Error assigning ticket: " . $conn->error;
                        }
                        $stmt->close();
                    }
                    break;

                case 'resolve_ticket':
                    if (isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id']) && isset($_POST['resolution'])) {
                        $ticketId = $_POST['ticket_id'];
                        $resolution = $_POST['resolution'];

                        // Update ticket status to "resolved" and set TimeSpent
                        $sql = "UPDATE Help_Desk 
                                SET Status = 'resolved', 
                                    ResolutionNotes = ?, 
                                    ResolvedDate = NOW(), 
                                    TimeSpent = TIMESTAMPDIFF(MINUTE, CreatedDate, NOW()) 
                                WHERE TicketID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $resolution, $ticketId);

                        if ($stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Ticket #$ticketId has been resolved!";
                        } else {
                            $response['message'] = "Error resolving ticket: " . $conn->error;
                        }
                        $stmt->close();
                    }
                    break;

                case 'reopen_ticket':
                    if (isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id'])) {
                        $ticketId = $_POST['ticket_id'];
                        error_log("Reopen ticket attempt: ticket_id=$ticketId");

                        // Try both session variables to find user ID
                        $userId = $_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0;
                        error_log("Using user_id: $userId");

                        if ($userId == 0) {
                            $response['success'] = false;
                            $response['message'] = 'User not authenticated';
                            $response['error'] = 'User not authenticated';
                        } else {
                            // Call the reopen function
                            $result = reopenTicket($conn, $ticketId, $userId);

                            // Ensure consistent response format
                            $response['success'] = $result['success'] ?? false;
                            if ($response['success']) {
                                $response['message'] = $result['message'] ?? 'Ticket reopened successfully and assignment reset';
                            } else {
                                $response['message'] = $result['error'] ?? $result['message'] ?? 'Unknown error occurred';
                                $response['error'] = $result['error'] ?? $result['message'] ?? 'Unknown error occurred';
                            }

                            error_log("Reopen ticket result: " . json_encode($result));
                        }
                    } else {
                        $response['success'] = false;
                        $response['message'] = 'Invalid ticket ID';
                        $response['error'] = 'Invalid ticket ID';
                    }
                    break;
            }
        }
    }

} catch (Exception $e) {
    error_log("Exception in ticket_ajax.php: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'Server error occurred';
    $response['error'] = $e->getMessage();
} finally {
    // Ensure clean output
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers again to be sure
    header('Content-Type: application/json');

    // Output the JSON response
    echo json_encode($response);

    // Close database connection if it exists
    if (isset($conn) && $conn) {
        $conn->close();
    }

    exit;
}
?>