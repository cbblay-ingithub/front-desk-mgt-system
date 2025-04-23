<?php
// Include your database configuration
global $conn;
require_once '../dbConfig.php';

// Include ticket functions
require_once 'ticket_functions.php';
require_once 'view_ticket.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'html' => ''];

if (isset($_GET['action']) && isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
    $ticketId = $_GET['ticket_id'];
    $action = $_GET['action'];

    $ticketDetail = getTicketDetails($conn, $ticketId);

    if ($ticketDetail) {
        $response['success'] = true;

        if ($action == 'get_ticket_details') {
            $response['html'] = generateTicketDetailsHTML($ticketDetail);
        } else if ($action == 'get_ticket_print') {
            $response['html'] = generateTicketPrintHTML($ticketDetail);
        }
    } else {
        $response['message'] = 'Ticket not found';
    }
} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
$conn->close();
?>