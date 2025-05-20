<?php
// Include your database configuration
global $conn;
require_once '../dbConfig.php';

// Include ticket functions
require_once 'ticket_functions.php';
require_once 'ticket_ops.php';
require_once 'view_ticket.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid request', 'html' => ''];
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
        }
    }
}

// Return JSON response
echo json_encode($response);
// Not an AJAX request, redirect to help desk
$conn->close();
exit;
?>