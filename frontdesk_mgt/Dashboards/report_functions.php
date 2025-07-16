<?php
require_once '../dbConfig.php';
global $conn;

// Get Host Reports
function getHostReports($startDate = null, $endDate = null) {
    global $conn;
    $where = "";
    $apptWhere = "";
    $ticketWhere = "";

    if ($startDate && $endDate) {
        $apptWhere = "WHERE AppointmentTime BETWEEN '$startDate' AND '$endDate'";
        $ticketWhere = "WHERE CreatedDate BETWEEN '$startDate' AND '$endDate'";
    }

    // Appointment metrics
    $sql = "SELECT 
        COUNT(*) AS total_appointments,
        SUM(Status = 'Completed') AS completed,
        SUM(Status = 'Cancelled') AS cancelled,
        SUM(Status = 'Upcoming') AS upcoming,
        SUM(Status = 'Ongoing') AS ongoing
    FROM Appointments $apptWhere";

    $result = $conn->query($sql);
    $appointmentMetrics = $result->fetch_assoc();

    // Helpdesk resolution rates
    $where = "WHERE AssignedTo IN (SELECT UserID FROM Users WHERE Role = 'Host')";
    if (!empty($ticketWhere)) {
        $where = "$ticketWhere AND AssignedTo IN (SELECT UserID FROM Users WHERE Role = 'Host')";
    }

    $sql = "SELECT 
    COUNT(*) AS total_tickets,
    SUM(Status = 'resolved') AS resolved
    FROM Help_Desk
    $where";

    $result = $conn->query($sql);
    $resolutionRates = $result->fetch_assoc();

    return [
        'appointment_metrics' => $appointmentMetrics,
        'resolution_rates' => $resolutionRates
    ];
}

// Get Support Staff Reports
function getSupportReports($startDate = null, $endDate = null) {
    global $conn;
    $where = "";
    if ($startDate && $endDate) {
        $where = "WHERE CreatedDate BETWEEN '$startDate' AND '$endDate'";
    }

    // Ticket volume
    $sql = "SELECT 
        COUNT(*) AS total_tickets,
        Priority,
        Status
    FROM Help_Desk $where
    GROUP BY Priority, Status";

    $result = $conn->query($sql);
    $ticketVolume = [];
    while ($row = $result->fetch_assoc()) {
        $ticketVolume[] = $row;
    }

    // Resolution times
    $sql = "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) AS avg_resolution_hours
    FROM Help_Desk 
    WHERE Status = 'resolved'";

    $result = $conn->query($sql);
    $resolutionTimes = $result->fetch_assoc();

    // Category breakdown
    $sql = "SELECT 
        c.CategoryName AS category,
        COUNT(*) AS count
    FROM Help_Desk t
    JOIN TicketCategories c ON t.CategoryID = c.CategoryID
    $where
    GROUP BY c.CategoryID";

    $result = $conn->query($sql);
    $categoryBreakdown = [];
    while ($row = $result->fetch_assoc()) {
        $categoryBreakdown[] = $row;
    }

    return [
        'ticket_volume' => $ticketVolume,
        'resolution_times' => $resolutionTimes,
        'category_breakdown' => $categoryBreakdown
    ];
}

// Get Front Desk Reports
function getFrontDeskReports($startDate = null, $endDate = null) {
    global $conn;
    $apptWhere = "";
    $lostWhere = "";

    if ($startDate && $endDate) {
        $apptWhere = "WHERE AppointmentTime BETWEEN '$startDate' AND '$endDate'";
        $lostWhere = "WHERE DateReported BETWEEN '$startDate' AND '$endDate'";
    }

    // Appointment scheduling
    $sql = "SELECT 
        COUNT(*) AS total_appointments,
        SUM(Status = 'Completed') AS completed,
        SUM(Status = 'Cancelled') AS cancelled
    FROM Appointments $apptWhere";

    $result = $conn->query($sql);
    $schedulingMetrics = $result->fetch_assoc();

    // Visitor management
    $sql = "SELECT 
        COUNT(*) AS visitors_checked_in,
        AVG(TIMESTAMPDIFF(MINUTE, AppointmentTime, CheckInTime)) AS avg_checkin_delay
    FROM Appointments
    WHERE CheckInTime IS NOT NULL";

    $result = $conn->query($sql);
    $visitorMetrics = $result->fetch_assoc();

    // Lost & found
    $sql = "SELECT 
        COUNT(*) AS total_items,
        SUM(Status = 'claimed') AS claimed,
        SUM(Status = 'found') AS unclaimed
    FROM Lost_And_Found $lostWhere";

    $result = $conn->query($sql);
    $lostFoundMetrics = $result->fetch_assoc();

    return [
        'scheduling_metrics' => $schedulingMetrics,
        'visitor_metrics' => $visitorMetrics,
        'lost_found_metrics' => $lostFoundMetrics
    ];
}
?>