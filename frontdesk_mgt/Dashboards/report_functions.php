<?php
require_once '../dbConfig.php';
global $conn;

// Get Host Reports
function getHostReports($startDate = null, $endDate = null) {
    global $conn;

    // Appointment metrics
    $apptSql = "SELECT 
        COUNT(*) AS total_appointments,
        SUM(Status = 'Completed') AS completed,
        SUM(Status = 'Cancelled') AS cancelled,
        SUM(Status = 'Upcoming') AS upcoming,
        SUM(Status = 'Ongoing') AS ongoing
    FROM Appointments";

    $apptParams = [];
    if ($startDate && $endDate) {
        $apptSql .= " WHERE AppointmentTime BETWEEN ? AND ?";
        $apptParams = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($apptSql);
    if (!empty($apptParams)) {
        $stmt->bind_param("ss", ...$apptParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $appointmentMetrics = $result->fetch_assoc();

    // Helpdesk resolution rates
    $ticketSql = "SELECT 
        COUNT(*) AS total_tickets,
        SUM(Status = 'resolved') AS resolved
    FROM Help_Desk
    WHERE AssignedTo IN (SELECT UserID FROM Users WHERE Role = 'Host')";

    $ticketParams = [];
    if ($startDate && $endDate) {
        $ticketSql .= " AND CreatedDate BETWEEN ? AND ?";
        $ticketParams = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($ticketSql);
    if (!empty($ticketParams)) {
        $stmt->bind_param("ss", ...$ticketParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $resolutionRates = $result->fetch_assoc();

    return [
        'appointment_metrics' => $appointmentMetrics,
        'resolution_rates' => $resolutionRates
    ];
}

// Get Support Staff Reports
function getSupportReports($startDate = null, $endDate = null) {
    global $conn;

    // Ticket volume
    $ticketSql = "SELECT 
        COUNT(*) AS total_tickets,
        Priority,
        Status
    FROM Help_Desk";

    $params = [];
    if ($startDate && $endDate) {
        $ticketSql .= " WHERE CreatedDate BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    }
    $ticketSql .= " GROUP BY Priority, Status";

    $stmt = $conn->prepare($ticketSql);
    if (!empty($params)) {
        $stmt->bind_param("ss", ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $ticketVolume = [];
    while ($row = $result->fetch_assoc()) {
        $ticketVolume[] = $row;
    }

    // Resolution times
    $resTimeSql = "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) AS avg_resolution_hours
    FROM Help_Desk 
    WHERE Status = 'resolved'";

    $params = [];
    if ($startDate && $endDate) {
        $resTimeSql .= " AND CreatedDate BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($resTimeSql);
    if (!empty($params)) {
        $stmt->bind_param("ss", ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $resolutionTimes = $result->fetch_assoc();

    // Category breakdown
    $catSql = "SELECT 
        c.CategoryName AS category,
        COUNT(*) AS count
    FROM Help_Desk t
    JOIN TicketCategories c ON t.CategoryID = c.CategoryID";

    $params = [];
    if ($startDate && $endDate) {
        $catSql .= " WHERE t.CreatedDate BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    }
    $catSql .= " GROUP BY c.CategoryID";

    $stmt = $conn->prepare($catSql);
    if (!empty($params)) {
        $stmt->bind_param("ss", ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
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

    // Appointment scheduling
    $apptSql = "SELECT 
        COUNT(*) AS total_appointments,
        SUM(Status = 'Completed') AS completed,
        SUM(Status = 'Cancelled') AS cancelled
    FROM Appointments";

    $apptParams = [];
    if ($startDate && $endDate) {
        $apptSql .= " WHERE AppointmentTime BETWEEN ? AND ?";
        $apptParams = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($apptSql);
    if (!empty($apptParams)) {
        $stmt->bind_param("ss", ...$apptParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $schedulingMetrics = $result->fetch_assoc();

    // Visitor management
    $visitorSql = "SELECT 
        COUNT(*) AS visitors_checked_in,
        AVG(TIMESTAMPDIFF(MINUTE, AppointmentTime, CheckInTime)) AS avg_checkin_delay
    FROM Appointments
    WHERE CheckInTime IS NOT NULL";

    $visitorParams = [];
    if ($startDate && $endDate) {
        $visitorSql .= " AND AppointmentTime BETWEEN ? AND ?";
        $visitorParams = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($visitorSql);
    if (!empty($visitorParams)) {
        $stmt->bind_param("ss", ...$visitorParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $visitorMetrics = $result->fetch_assoc();

    // Lost & found
    $lostSql = "SELECT 
        COUNT(*) AS total_items,
        SUM(Status = 'claimed') AS claimed,
        SUM(Status = 'found') AS unclaimed
    FROM Lost_And_Found";

    $lostParams = [];
    if ($startDate && $endDate) {
        $lostSql .= " WHERE DateReported BETWEEN ? AND ?";
        $lostParams = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($lostSql);
    if (!empty($lostParams)) {
        $stmt->bind_param("ss", ...$lostParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $lostFoundMetrics = $result->fetch_assoc();

    return [
        'scheduling_metrics' => $schedulingMetrics,
        'visitor_metrics' => $visitorMetrics,
        'lost_found_metrics' => $lostFoundMetrics
    ];
}
?>