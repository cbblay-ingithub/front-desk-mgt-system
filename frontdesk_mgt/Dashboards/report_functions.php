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

    // Check if we have any tickets for this support person
    $checkSql = "SELECT COUNT(*) as ticket_count FROM Help_Desk WHERE AssignedTo = ?";
    if ($startDate && $endDate) {
        $checkSql .= " AND CreatedDate BETWEEN ? AND ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("iss", $supportId, $startDate, $endDate);
    } else {
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("i", $supportId);
    }
    $stmt->execute();
    $ticketCount = $stmt->get_result()->fetch_assoc()['ticket_count'];

    // Debug log
    error_log("Support ID $supportId has $ticketCount tickets");

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

    // Get reopened tickets count and rate
    $reopenedSql = "SELECT COUNT(*) AS reopened_count
    FROM Help_Desk 
    WHERE AssignedTo = ? AND Status = 'reopened'";
    if ($startDate && $endDate) {
        $reopenedSql .= " AND CreatedDate BETWEEN ? AND ?";
    }

    $stmt = $conn->prepare($reopenedSql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $supportId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $supportId);
    }
    $stmt->execute();
    $reopenedData = $stmt->get_result()->fetch_assoc();
    $reopenedCount = $reopenedData['reopened_count'] ?? 0;

    // Get total tickets count separately
    $totalSql = "SELECT COUNT(*) AS total_tickets 
    FROM Help_Desk 
    WHERE AssignedTo = ?";
    if ($startDate && $endDate) {
        $totalSql .= " AND CreatedDate BETWEEN ? AND ?";
    }

    $stmt = $conn->prepare($totalSql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $supportId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $supportId);
    }
    $stmt->execute();
    $totalData = $stmt->get_result()->fetch_assoc();
    $totalTickets = $totalData['total_tickets'] ?? 0;

    $reopenedRate = $totalTickets > 0 ? round(($reopenedCount / $totalTickets) * 100, 2) : 0;

    return [
        'status_breakdown' => $statusBreakdown,
        'avg_resolution_hours' => $avgResolutionHours,
        'tickets_created' => $ticketsCreated,
        'reopened_tickets' => $reopenedCount,
        'reopened_rate' => $reopenedRate,
        'total_tickets' => $totalTickets
    ];
}

// Get Individual Front Desk Metrics
function getIndividualFrontDeskMetrics($frontDeskId, $startDate = null, $endDate = null): array
{
    global $conn;

    // Basic appointment metrics
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
        WHERE ScheduledBy = ? AND CheckInTime IS NOT NULL";
    $checkInParams = [$frontDeskId];
    if ($startDate && $endDate) {
        $checkInSql .= " AND AppointmentTime BETWEEN ? AND ?";
        $checkInParams[] = $startDate;
        $checkInParams[] = $endDate;
    }
    $stmt = $conn->prepare($checkInSql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $frontDeskId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $frontDeskId);
    }
    $stmt->execute();
    $checkIn = $stmt->get_result()->fetch_assoc();
    $metrics['avg_checkin_time'] = $checkIn['avg_checkin_time'] ? round($checkIn['avg_checkin_time'], 2) : 0;

    // Error/recorrection rate - using cancelled appointments as a proxy
    $errorSql = "SELECT 
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN Status = 'Cancelled' THEN 1 ELSE 0 END) AS error_count
    FROM Appointments
    WHERE ScheduledBy = ?";
    $errorParams = [$frontDeskId];
    if ($startDate && $endDate) {
        $errorSql .= " AND AppointmentTime BETWEEN ? AND ?";
        $errorParams[] = $startDate;
        $errorParams[] = $endDate;
    }
    $stmt = $conn->prepare($errorSql);
    if ($startDate && $endDate) {
        $stmt->bind_param("iss", $frontDeskId, $startDate, $endDate);
    } else {
        $stmt->bind_param("i", $frontDeskId);
    }
    $stmt->execute();
    $errorData = $stmt->get_result()->fetch_assoc();
    $metrics['error_rate'] = $errorData['total_appointments'] > 0 ?
        round(($errorData['error_count'] / $errorData['total_appointments']) * 100, 2) : 0;

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

    // Initialize arrays
    $statusBreakdown = [];
    $categoryBreakdown = [];

    // Total tickets count
    $totalSql = "SELECT COUNT(*) AS total_tickets FROM Help_Desk";
    $params = [];
    $types = "";

    if ($startDate && $endDate) {
        $totalSql .= " WHERE CreatedDate BETWEEN ? AND ?";
        $types = "ss";
        $params = [$startDate, $endDate];
    }

    $stmt = $conn->prepare($totalSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalTickets = $stmt->get_result()->fetch_assoc()['total_tickets'];

    // Debug: Check if we have any tickets at all
    error_log("Total tickets found: " . $totalTickets);

    // Resolution times
    $resTimeSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, CreatedDate, ResolvedDate)) AS avg_resolution_hours
                   FROM Help_Desk WHERE Status = 'resolved'";
    if ($startDate && $endDate) {
        $resTimeSql .= " AND CreatedDate BETWEEN ? AND ?";
    }

    $stmt = $conn->prepare($resTimeSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resolutionTimes = $stmt->get_result()->fetch_assoc();

    // Ticket status breakdown - More comprehensive approach
    $statusSql = "SELECT Status, COUNT(*) AS count FROM Help_Desk";
    if ($startDate && $endDate) {
        $statusSql .= " WHERE CreatedDate BETWEEN ? AND ?";
    }
    $statusSql .= " GROUP BY Status";

    $stmt = $conn->prepare($statusSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Normalize status names to handle case variations
        $normalizedStatus = strtolower(trim($row['Status']));
        switch ($normalizedStatus) {
            case 'open':
            case 'pending':
                $statusBreakdown['open'] = ($statusBreakdown['open'] ?? 0) + $row['count'];
                break;
            case 'in progress':
            case 'in-progress':
            case 'in_progress':
            case 'working':
                $statusBreakdown['in-progress'] = ($statusBreakdown['in-progress'] ?? 0) + $row['count'];
                break;
            case 'resolved':
            case 'completed':
                $statusBreakdown['resolved'] = ($statusBreakdown['resolved'] ?? 0) + $row['count'];
                break;
            case 'closed':
            case 'finished':
                $statusBreakdown['closed'] = ($statusBreakdown['closed'] ?? 0) + $row['count'];
                break;
            default:
                // Handle any other status
                $statusBreakdown[$row['Status']] = $row['count'];
        }
    }

    // Debug: Log status breakdown
    error_log("Status breakdown: " . print_r($statusBreakdown, true));

    // Category breakdown - Check if CategoryID column exists and handle missing categories
    // First, check if we have a categories table
    $checkCategoriesTable = "SHOW TABLES LIKE 'TicketCategories'";
    $result = $conn->query($checkCategoriesTable);

    if ($result && $result->num_rows > 0) {
        // Table exists, check if tickets have categories
        $catSql = "SELECT 
            COALESCE(c.CategoryName, 'Uncategorized') AS category,
            COUNT(*) AS count
        FROM Help_Desk t
        LEFT JOIN TicketCategories c ON t.CategoryID = c.CategoryID";

        if ($startDate && $endDate) {
            $catSql .= " WHERE t.CreatedDate BETWEEN ? AND ?";
        }
        $catSql .= " GROUP BY c.CategoryID, c.CategoryName ORDER BY count DESC";

        $stmt = $conn->prepare($catSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $categoryBreakdown[] = $row;
            }
        }
    } else {
        // No categories table, or check if CategoryID column exists in Help_Desk
        $checkCategoryColumn = "SHOW COLUMNS FROM Help_Desk LIKE 'CategoryID'";
        $result = $conn->query($checkCategoryColumn);

        if ($result && $result->num_rows > 0) {
            // Column exists but no categories table, group by CategoryID
            $catSql = "SELECT 
                COALESCE(CategoryID, 'Uncategorized') AS category,
                COUNT(*) AS count
            FROM Help_Desk";

            if ($startDate && $endDate) {
                $catSql .= " WHERE CreatedDate BETWEEN ? AND ?";
            }
            $catSql .= " GROUP BY CategoryID ORDER BY count DESC";

            $stmt = $conn->prepare($catSql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $categoryBreakdown[] = [
                        'category' => $row['category'] ?: 'Uncategorized',
                        'count' => $row['count']
                    ];
                }
            }
        } else {
            // No category system at all, create a single "General" category
            if ($totalTickets > 0) {
                $categoryBreakdown[] = [
                    'category' => 'General',
                    'count' => $totalTickets
                ];
            }
        }
    }

    // Debug: Log category breakdown
    error_log("Category breakdown: " . print_r($categoryBreakdown, true));

    return [
        'total_tickets' => $totalTickets,
        'resolution_times' => $resolutionTimes,
        'status_breakdown' => $statusBreakdown,
        'category_breakdown' => $categoryBreakdown
    ];
}

function getFrontDeskReports($startDate = null, $endDate = null): array
{
    global $conn;

    // Scheduling metrics
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

    // Visitor check-in metrics
    $visitorSql = "SELECT 
        COUNT(*) AS visitors_checked_in,
        AVG(TIMESTAMPDIFF(MINUTE, AppointmentTime, CheckInTime)) AS avg_checkin_time
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

    // New vs returning clients
    $clientSql = "SELECT
        SUM(CASE WHEN visit_count = 1 THEN 1 ELSE 0 END) AS new_clients,
        SUM(CASE WHEN visit_count > 1 THEN 1 ELSE 0 END) AS returning_clients
    FROM (
        SELECT 
            v.VisitorID, 
            COUNT(*) AS visit_count
        FROM Appointments a
        JOIN Visitors v ON a.VisitorID = v.VisitorID
        WHERE a.Status = 'Completed'";
    if ($startDate && $endDate) {
        $clientSql .= " AND a.AppointmentTime BETWEEN ? AND ?";
    }
    $clientSql .= " GROUP BY v.VisitorID
    ) AS visitor_visits";
    $stmt = $conn->prepare($clientSql);
    if (!empty($apptParams)) {
        $stmt->bind_param("ss", ...$apptParams);
    }
    $stmt->execute();
    $clientMetrics = $stmt->get_result()->fetch_assoc();

    // Peak traffic hours
    $peakSql = "SELECT 
        HOUR(CheckInTime) AS peak_hour, 
        COUNT(*) AS visitor_count
    FROM Appointments
    WHERE CheckInTime IS NOT NULL";
    if ($startDate && $endDate) {
        $peakSql .= " AND CheckInTime BETWEEN ? AND ?";
    }
    $peakSql .= " GROUP BY HOUR(CheckInTime) ORDER BY visitor_count DESC LIMIT 1";
    $stmt = $conn->prepare($peakSql);
    if (!empty($visitorParams)) {
        $stmt->bind_param("ss", ...$visitorParams);
    }
    $stmt->execute();
    $peakHour = $stmt->get_result()->fetch_assoc();

    return [
        'scheduling_metrics' => $schedulingMetrics,
        'visitor_metrics' => $visitorMetrics,
        'client_metrics' => $clientMetrics,
        'peak_hour' => $peakHour['peak_hour'] ?? null
    ];
}
?>