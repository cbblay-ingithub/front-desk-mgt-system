<?php
require_once '../dbConfig.php';
global $conn;

// Helper function to get users by role
function getUsersByRole($role) {
    global $conn;
    $stmt = $conn->prepare("SELECT UserID, Name FROM Users WHERE Role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get Individual Host Metrics
function getIndividualHostMetrics($hostId, $startDate = null, $endDate = null): array
{
    global $conn;
    $sql = "SELECT 
        COUNT(*) AS total_appointments,
        SUM(Status = 'Completed') AS completed,
        SUM(Status = 'Cancelled') AS cancelled,
        SUM(Status = 'Upcoming') AS upcoming
    FROM Appointments
    WHERE HostID = ?";
    if ($startDate && $endDate) {
        $sql .= " AND AppointmentTime BETWEEN ? AND ?";
    }
    $stmt = $conn->prepare($sql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $hostId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $hostId);
    }
    $stmt->execute();
    $metrics = $stmt->get_result()->fetch_assoc();


    // No-Show Rate: Past appointments without check-in
    $noShowSql = "SELECT 
        COUNT(*) AS total_past,
        SUM(CASE WHEN CheckInTime IS NULL AND AppointmentTime < NOW() THEN 1 ELSE 0 END) AS no_shows
    FROM Appointments
    WHERE HostID = ? AND AppointmentTime BETWEEN ? AND ? AND AppointmentTime < NOW() AND Status != 'cancelled'";
    $stmt = $conn->prepare($noShowSql);
    $start = $startDate ?: '1970-01-01';
    $end = $endDate ?: '9999-12-31';
    $stmt->bind_param("iss", $hostId, $start, $end);
    $stmt->execute();
    $noShowData = $stmt->get_result()->fetch_assoc();
    $metrics['no_show_rate'] = $noShowData['total_past'] > 0 ? round(($noShowData['no_shows'] / $noShowData['total_past']) * 100, 2) : 0;

    // Peak Hours Analysis
    $peakSql = "SELECT HOUR(AppointmentTime) AS peak_hour, COUNT(*) AS count
        FROM Appointments
        WHERE HostID = ? AND AppointmentTime BETWEEN ? AND ?
        GROUP BY HOUR(AppointmentTime)
        ORDER BY count DESC LIMIT 1";
    $stmt = $conn->prepare($peakSql);
    $start = $startDate ?: '1970-01-01';
    $end = $endDate ?: '9999-12-31';
    $stmt->bind_param("iss", $hostId, $start, $end);
    $stmt->execute();
    $peak = $stmt->get_result()->fetch_assoc();
    $metrics['peak_hour'] = $peak['peak_hour'] ?? null;

    // Monthly Trends
    $trendSql = "SELECT YEAR(AppointmentTime) AS year, MONTH(AppointmentTime) AS month, COUNT(*) AS completed
        FROM Appointments
        WHERE HostID = ? AND Status = 'Completed' AND AppointmentTime BETWEEN ? AND ?
        GROUP BY YEAR(AppointmentTime), MONTH(AppointmentTime)
        ORDER BY year, month";
    $stmt = $conn->prepare($trendSql);
    $start = $startDate ?: '1970-01-01';
    $end = $endDate ?: '9999-12-31';
    $stmt->bind_param("iss", $hostId, $start, $end);
    $stmt->execute();
    $trends = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $trends[] = $row;
    }
    $metrics['monthly_trends'] = $trends;

    return $metrics;
}

// Get Individual Support Staff Metrics
function getIndividualSupportMetrics($supportId, $startDate = null, $endDate = null): array
{
    global $conn;
    // Fetch status breakdown of tickets assigned to the support staff
    $sql = "SELECT 
        Status,
        COUNT(*) AS count
    FROM Help_Desk
    WHERE AssignedTo = ?";
    if ($startDate && $endDate) {
        $sql .= " AND CreatedDate BETWEEN ? AND ?";
    }
    $sql .= " GROUP BY Status";
    $stmt = $conn->prepare($sql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $supportId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $supportId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $statusBreakdown = [];
    while ($row = $result->fetch_assoc()) {
        $statusBreakdown[$row['Status']] = $row['count'];
    }
    // Fetch average resolution time for resolved tickets
    $resTimeSql = "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) AS avg_resolution_hours
    FROM Help_Desk 
    WHERE AssignedTo = ? AND Status = 'resolved'";
    if ($startDate && $endDate) {
        $resTimeSql .= " AND CreatedDate BETWEEN ? AND ?";
    }
    $stmt = $conn->prepare($resTimeSql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $supportId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $supportId);
    }
    $stmt->execute();
    $resolutionTime = $stmt->get_result()->fetch_assoc();
    $avgResolutionHours = $resolutionTime['avg_resolution_hours'] ? round($resolutionTime['avg_resolution_hours'], 2) : null;

    // Fetch tickets created by the support staff
    $createdSql = "SELECT COUNT(*) AS tickets_created 
    FROM Help_Desk 
    WHERE CreatedBy = ?";
    if ($startDate && $endDate) {
        $createdSql .= " AND CreatedDate BETWEEN ? AND ?";
    }
    $stmt = $conn->prepare($createdSql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $supportId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $supportId);
    }
    $stmt->execute();
    $ticketsCreated = $stmt->get_result()->fetch_assoc()['tickets_created'];

    // Return all metrics in an array
    return [
        'status_breakdown' => $statusBreakdown,
        'avg_resolution_hours' => $avgResolutionHours,
        'tickets_created' => $ticketsCreated
    ];
}

// Get Individual Front Desk Metrics
function getIndividualFrontDeskMetrics($frontDeskId, $startDate = null, $endDate = null): array
{
    global $conn;
    $sql = "SELECT 
        COUNT(*) AS total_appointments_scheduled
    FROM Appointments
    WHERE ScheduledBy = ?";
    $params = [$frontDeskId];
    if ($startDate && $endDate) {
        $sql .= " AND AppointmentTime BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }
    $stmt = $conn->prepare($sql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $frontDeskId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $frontDeskId);
    }
    $stmt->execute();
    $metrics = $stmt->get_result()->fetch_assoc();

    // Average Check-In Time
    $checkInSql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, AppointmentTime, CheckInTime)) AS avg_checkin_time
        FROM Appointments
        WHERE ScheduledBy = ? AND CheckInTime IS NOT NULL AND AppointmentTime BETWEEN ? AND ?";
    $stmt = $conn->prepare($checkInSql);
    $start = $startDate ?: '1970-01-01';
    $end = $endDate ?: '9999-12-31';
    $stmt->bind_param("iss", $frontDeskId, $start, $end);
    $stmt->execute();
    $checkIn = $stmt->get_result()->fetch_assoc();
    $metrics['avg_checkin_time'] = $checkIn['avg_checkin_time'] ? round($checkIn['avg_checkin_time'], 2) : 0;

    return $metrics;
}

// Aggregate Functions
function getHostReports($startDate = null, $endDate = null): array
{
    global $conn;
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
    $appointmentMetrics = $stmt->get_result()->fetch_assoc();

    // Get cancellation reasons
    $cancelReasonSql = "SELECT 
        CancellationReason, 
        COUNT(*) AS count
    FROM Appointments
    WHERE Status = 'Cancelled'";
    if ($startDate && $endDate) {
        $cancelReasonSql .= " AND AppointmentTime BETWEEN ? AND ?";
    }
    $cancelReasonSql .= " GROUP BY CancellationReason";
    $stmt = $conn->prepare($cancelReasonSql);
    if (!empty($apptParams)) {
        $stmt->bind_param("ss", ...$apptParams);
    }
    $stmt->execute();
    $cancelReasons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get new vs returning visitors
    $visitorSql = "SELECT
        SUM(CASE WHEN visit_count = 1 THEN 1 ELSE 0 END) AS new_visitors,
        SUM(CASE WHEN visit_count > 1 THEN 1 ELSE 0 END) AS returning_visitors
    FROM (
        SELECT 
            v.VisitorID, 
            COUNT(*) AS visit_count
        FROM Appointments a
        JOIN Visitors v ON a.VisitorID = v.VisitorID
        WHERE a.Status = 'Completed'";
    if ($startDate && $endDate) {
        $visitorSql .= " AND a.AppointmentTime BETWEEN ? AND ?";
    }
    $visitorSql .= " GROUP BY v.VisitorID
    ) AS visitor_visits";
    $stmt = $conn->prepare($visitorSql);
    if (!empty($apptParams)) {
        $stmt->bind_param("ss", ...$apptParams);
    }
    $stmt->execute();
    $visitorMetrics = $stmt->get_result()->fetch_assoc();

    $ticketSql = "SELECT 
        COUNT(*) AS total_tickets,
        SUM(Status = 'Resolved') AS resolved
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
    $resolutionRates = $stmt->get_result()->fetch_assoc();

    return [
        'appointment_metrics' => $appointmentMetrics,
        'cancellation_reasons' => $cancelReasons,
        'visitor_metrics' => $visitorMetrics,
        'resolution_rates' => $resolutionRates
    ];
}
function getSupportReports($startDate = null, $endDate = null): array
{
    global $conn;
    $totalSql = "SELECT COUNT(*) AS total_tickets FROM Help_Desk";
    $params = [];
    if ($startDate && $endDate) {
        $totalSql .= " WHERE CreatedDate BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    }
    $stmt = $conn->prepare($totalSql);
    if (!empty($params)) {
        $stmt->bind_param("ss", ...$params);
    }
    $stmt->execute();
    $totalTickets = $stmt->get_result()->fetch_assoc()['total_tickets'];

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
    $resolutionTimes = $stmt->get_result()->fetch_assoc();

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
    $categoryBreakdown = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categoryBreakdown[] = $row;
    }

    return [
        'total_tickets' => $totalTickets,
        'resolution_times' => $resolutionTimes,
        'category_breakdown' => $categoryBreakdown
    ];
}

function getFrontDeskReports($startDate = null, $endDate = null): array
{
    global $conn;
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
    $schedulingMetrics = $stmt->get_result()->fetch_assoc();

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
    $visitorMetrics = $stmt->get_result()->fetch_assoc();

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
    $lostFoundMetrics = $stmt->get_result()->fetch_assoc();

    return [
        'scheduling_metrics' => $schedulingMetrics,
        'visitor_metrics' => $visitorMetrics,
        'lost_found_metrics' => $lostFoundMetrics
    ];
}
?>