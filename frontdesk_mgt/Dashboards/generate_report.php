<?php
require_once '../dbConfig.php';
require_once 'report_functions.php';
global $conn;

// Include TCPDF library via Composer
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Autoload file not found at: $autoloadPath");
}
require_once $autoloadPath;

// Debug class existence
if (!class_exists('TCPDF')) {
    die("TCPDF class not found. Ensure TCPDF is installed via composer.");
}

// Get report parameters
$reportType = $_POST['report_type'] ?? null;
$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;

// Get report data and individual metrics
$report = [];
$individualReports = [];
$users = [];
$title = "";
$subtitle = "";

if ($reportType === 'host') {
    $report = getHostReports($startDate, $endDate);
    $title = "Host Performance Report";
    $subtitle = "Appointment & Visitor Management Analysis";
    $users = getUsersByRole('Host');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualHostMetrics($user['UserID'], $startDate, $endDate);
    }
} elseif ($reportType === 'support') {
    $report = getSupportReports($startDate, $endDate);
    $title = "Support Staff Performance Report";
    $subtitle = "Ticket Resolution & Customer Service Analysis";
    $users = getUsersByRole('Support Staff');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualSupportMetrics($user['UserID'], $startDate, $endDate);
    }
} elseif ($reportType === 'frontdesk') {
    $report = getFrontDeskReports($startDate, $endDate);
    $title = "Front Desk Performance Report";
    $subtitle = "Scheduling & Customer Service Analysis";
    $users = getUsersByRole('Front Desk Staff');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualFrontDeskMetrics($user['UserID'], $startDate, $endDate);
    }
} else {
    die("Invalid report type");
}

// Create PDF instance with enhanced settings
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Performance Report System v2.0');
    $pdf->SetAuthor('Performance Analytics Team');
    $pdf->SetTitle($title);
    $pdf->SetSubject($subtitle);

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);

    // Set default font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage();

} catch (Exception $e) {
    die("Error creating PDF: " . $e->getMessage());
}

// Enhanced HTML content without JavaScript charts
$html = '<!DOCTYPE html>
<html>
<head>
    <title>'.$title.'</title>
    <style>
        body {
            font-family: helvetica, arial, sans-serif;
            font-size: 10px;
            color: #2c3e50;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        /* Header Styles */
        .report-header {
            background-color: #667eea;
            color: white;
            padding: 15px;
            margin: -15px -15px 15px -15px;
            text-align: center;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .report-subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin: 0 0 10px 0;
            font-weight: normal;
        }
        
        .date-range {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 12px;
            display: inline-block;
            font-size: 9px;
            font-weight: 500;
        }
        
        /* Section Styles */
        .section {
            margin-bottom: 15px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .section-header {
            background-color: #f8f9fa;
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        
        .section-content {
            padding: 12px;
        }
        
        /* Metrics Grid */
        .metrics-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 12px;
        }
        
        .metric-card {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            vertical-align: top;
        }
        
        .metric-title {
            font-size: 9px;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .metric-value {
            font-size: 18px;
            color: #2c3e50;
            font-weight: bold;
            margin: 0;
            line-height: 1;
        }
        
        .metric-unit {
            font-size: 10px;
            color: #6c757d;
            font-weight: normal;
        }
        
        /* Status Colors */
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-danger { color: #dc3545; }
        .status-info { color: #17a2b8; }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 9px;
        }
        
        .data-table thead {
            background-color: #667eea;
            color: white;
        }
        
        .data-table th {
            padding: 8px 6px;
            font-weight: 600;
            font-size: 9px;
            text-align: left;
        }
        
        .data-table td {
            padding: 6px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        /* Individual Performance */
        .individual-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
        }
        
        .individual-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 12px;
            vertical-align: top;
        }
        
        .user-name {
            font-size: 12px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .user-metrics {
            width: 100%;
            border-collapse: collapse;
        }
        
        .user-metric-label {
            font-size: 9px;
            color: #6c757d;
            font-weight: 600;
            padding: 3px 0;
            text-align: left;
        }
        
        .user-metric-value {
            font-size: 11px;
            color: #2c3e50;
            font-weight: bold;
            text-align: right;
            padding: 3px 0;
        }
        
        /* Footer */
        .report-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #6c757d;
            font-size: 8px;
        }
        
        .generated-info {
            background: #f8f9fa;
            padding: 6px;
            border-radius: 3px;
        }
        
        /* Chart placeholders */
        .chart-placeholder {
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: 5px;
            padding: 20px;
            margin: 10px 0;
            text-align: center;
            color: #6c757d;
            font-size: 10px;
        }
    </style>
</head>
<body>';

// Header Section
$html .= '<div class="report-header">
    <h1 class="report-title">'.$title.'</h1>
    <p class="report-subtitle">'.$subtitle.'</p>';

if ($startDate || $endDate) {
    $html .= '<div class="date-range">
        '.($startDate ? date('M j, Y', strtotime($startDate)) : 'Start').' 
        to 
        '.($endDate ? date('M j, Y', strtotime($endDate)) : 'Present').'
    </div>';
}

$html .= '</div>';

// Team Performance Overview Section
$html .= '<div class="section">
    <div class="section-header">
        <h2 class="section-title">Executive Dashboard</h2>
    </div>
    <div class="section-content">';

if ($reportType === 'host') {
    $totalAppts = $report['appointment_metrics']['total_appointments'] ?? 0;
    $completed = $report['appointment_metrics']['completed'] ?? 0;
    $cancelled = $report['appointment_metrics']['cancelled'] ?? 0;
    $upcoming = $report['appointment_metrics']['upcoming'] ?? 0;
    $completionRate = $totalAppts > 0 ? round(($completed / $totalAppts) * 100, 1) : 0;

    $html .= '<table class="metrics-grid">
        <tr>
            <td width="25%" class="metric-card">
                <div class="metric-title">Total Appointments</div>
                <div class="metric-value">'.number_format($totalAppts).'</div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Completion Rate</div>
                <div class="metric-value status-good">'.$completionRate.'<span class="metric-unit">%</span></div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Cancelled</div>
                <div class="metric-value status-warning">'.number_format($cancelled).'</div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Upcoming</div>
                <div class="metric-value status-info">'.number_format($upcoming).'</div>
            </td>
        </tr>
    </table>';

    // Chart placeholders
    $html .= '<div class="chart-placeholder">
        📊 Appointment Status Distribution Chart<br>
        <small>Interactive charts available in web version</small>
    </div>';

    $html .= '<div class="chart-placeholder">
        📊 Visitor Types Chart<br>
        <small>Interactive charts available in web version</small>
    </div>';

    // Cancellation Reasons Table
    if (!empty($report['cancellation_reasons'])) {
        $html .= '<h3 style="margin:15px 0 8px 0; font-size:11px; color:#2c3e50;">Cancellation Reasons</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reason</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($report['cancellation_reasons'] as $reason) {
            $percentage = $report['appointment_metrics']['cancelled'] > 0 ?
                round(($reason['count'] / $report['appointment_metrics']['cancelled']) * 100, 2) : 0;

            $html .= '<tr>
                <td>'.($reason['CancellationReason'] ?: 'No reason given').'</td>
                <td>'.$reason['count'].'</td>
                <td>'.$percentage.'%</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

} elseif ($reportType === 'support') {
    $totalTickets = $report['total_tickets'] ?? 0;
    $resolved = $report['status_breakdown']['resolved'] ?? 0;
    $resolutionRate = $totalTickets > 0 ? round(($resolved / $totalTickets) * 100, 1) : 0;
    $avgResolution = $report['resolution_times']['avg_resolution_hours'] ?? 0;
    $activeTickets = ($report['status_breakdown']['open'] ?? 0) + ($report['status_breakdown']['in-progress'] ?? 0);

    $html .= '<table class="metrics-grid">
        <tr>
            <td width="25%" class="metric-card">
                <div class="metric-title">Total Tickets</div>
                <div class="metric-value">'.number_format($totalTickets).'</div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Resolution Rate</div>
                <div class="metric-value status-good">'.$resolutionRate.'<span class="metric-unit">%</span></div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Avg Resolution</div>
                <div class="metric-value">'.round($avgResolution, 1).'<span class="metric-unit">h</span></div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Active Tickets</div>
                <div class="metric-value status-warning">'.number_format($activeTickets).'</div>
            </td>
        </tr>
    </table>';

    // Chart placeholders
    $html .= '<div class="chart-placeholder">
        📊 Ticket Status Distribution Chart<br>
        <small>Interactive charts available in web version</small>
    </div>';

    // Ticket Status Breakdown
    if (!empty($report['status_breakdown'])) {
        $html .= '<h3 style="margin:15px 0 8px 0; font-size:11px; color:#2c3e50;">Ticket Status Breakdown</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Open</td>
                    <td>'.($report['status_breakdown']['open'] ?? 0).'</td>
                    <td>'.($totalTickets > 0 ? round(($report['status_breakdown']['open'] / $totalTickets) * 100, 1) : 0).'%</td>
                </tr>
                <tr>
                    <td>In Progress</td>
                    <td>'.($report['status_breakdown']['in-progress'] ?? 0).'</td>
                    <td>'.($totalTickets > 0 ? round(($report['status_breakdown']['in-progress'] / $totalTickets) * 100, 1) : 0).'%</td>
                </tr>
                <tr>
                    <td>Resolved</td>
                    <td>'.($report['status_breakdown']['resolved'] ?? 0).'</td>
                    <td>'.($totalTickets > 0 ? round(($report['status_breakdown']['resolved'] / $totalTickets) * 100, 1) : 0).'%</td>
                </tr>
                <tr>
                    <td>Closed</td>
                    <td>'.($report['status_breakdown']['closed'] ?? 0).'</td>
                    <td>'.($totalTickets > 0 ? round(($report['status_breakdown']['closed'] / $totalTickets) * 100, 1) : 0).'%</td>
                </tr>
            </tbody>
        </table>';
    }

} elseif ($reportType === 'frontdesk') {
    $totalAppts = $report['scheduling_metrics']['total_appointments'] ?? 0;
    $completed = $report['scheduling_metrics']['completed'] ?? 0;
    $completionRate = $totalAppts > 0 ? round(($completed / $totalAppts) * 100, 1) : 0;
    $avgCheckin = $report['visitor_metrics']['avg_checkin_time'] ?? 0;
    $peakHour = $report['peak_hour'] ?? 'N/A';

    $html .= '<table class="metrics-grid">
        <tr>
            <td width="25%" class="metric-card">
                <div class="metric-title">Total Appointments</div>
                <div class="metric-value">'.number_format($totalAppts).'</div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Completion Rate</div>
                <div class="metric-value status-good">'.$completionRate.'<span class="metric-unit">%</span></div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Avg Check-In</div>
                <div class="metric-value">'.round($avgCheckin, 1).'<span class="metric-unit">min</span></div>
            </td>
            <td width="25%" class="metric-card">
                <div class="metric-title">Peak Hour</div>
                <div class="metric-value">'.$peakHour.':00</div>
            </td>
        </tr>
    </table>';

    // Chart placeholders
    $html .= '<div class="chart-placeholder">
        📊 Client Types Chart<br>
        <small>Interactive charts available in web version</small>
    </div>';
}

$html .= '</div></div>';

// Individual Performance Section
if (!empty($users)) {
    $html .= '<div class="section">
        <div class="section-header">
            <h2 class="section-title">Individual Performance Analysis</h2>
        </div>
        <div class="section-content">
            <table class="individual-grid">';

    $html .= '<tr>';
    $count = 0;
    foreach ($users as $user) {
        $metrics = $individualReports[$user['UserID']] ?? [];

        if ($count % 2 == 0 && $count > 0) {
            $html .= '</tr><tr>';
        }

        $html .= '<td width="50%" class="individual-card">
            <div class="user-name">'.$user['Name'].'</div>
            <table class="user-metrics">';

        if ($reportType === 'host') {
            $totalAppts = $metrics['total_appointments'] ?? 0;
            $completed = $metrics['completed'] ?? 0;
            $noShowRate = $metrics['no_show_rate'] ?? 0;
            $peakHour = $metrics['peak_hour'] ? date('g A', strtotime($metrics['peak_hour'].':00')) : 'N/A';

            $html .= '<tr>
                <td class="user-metric-label">Total Appointments</td>
                <td class="user-metric-value">'.number_format($totalAppts).'</td>
            </tr>
            <tr>
                <td class="user-metric-label">Completed</td>
                <td class="user-metric-value status-good">'.number_format($completed).'</td>
            </tr>
            <tr>
                <td class="user-metric-label">No-Show Rate</td>
                <td class="user-metric-value '.($noShowRate > 15 ? 'status-danger' : ($noShowRate > 10 ? 'status-warning' : 'status-good')).'">'.$noShowRate.'%</td>
            </tr>
            <tr>
                <td class="user-metric-label">Peak Hour</td>
                <td class="user-metric-value">'.$peakHour.'</td>
            </tr>';

        } elseif ($reportType === 'support') {
            $resolved = $metrics['status_breakdown']['resolved'] ?? 0;
            $total = $metrics['total_tickets'] ?? 1;
            $resolutionRate = round(($resolved / $total) * 100, 1);
            $avgResolution = $metrics['avg_resolution_hours'] !== null ? round($metrics['avg_resolution_hours'], 1) : 'N/A';
            $reopenedRate = $metrics['reopened_rate'] ?? 0;

            $html .= '<tr>
                <td class="user-metric-label">Tickets Resolved</td>
                <td class="user-metric-value">'.number_format($resolved).'</td>
            </tr>
            <tr>
                <td class="user-metric-label">Resolution Rate</td>
                <td class="user-metric-value '.($resolutionRate >= 90 ? 'status-good' : ($resolutionRate >= 75 ? 'status-warning' : 'status-danger')).'">'.$resolutionRate.'%</td>
            </tr>
            <tr>
                <td class="user-metric-label">Avg Resolution</td>
                <td class="user-metric-value">'.$avgResolution.'h</td>
            </tr>
            <tr>
                <td class="user-metric-label">Reopened Rate</td>
                <td class="user-metric-value '.($reopenedRate < 5 ? 'status-good' : ($reopenedRate < 10 ? 'status-warning' : 'status-danger')).'">'.$reopenedRate.'%</td>
            </tr>';

        } elseif ($reportType === 'frontdesk') {
            $apptScheduled = $metrics['total_appointments_scheduled'] ?? 0;
            $avgCheckin = $metrics['avg_checkin_time'] !== null ? round($metrics['avg_checkin_time'], 1) : 'N/A';
            $errorRate = $metrics['error_rate'] ?? 0;

            $html .= '<tr>
                <td class="user-metric-label">Appointments Scheduled</td>
                <td class="user-metric-value">'.number_format($apptScheduled).'</td>
            </tr>
            <tr>
                <td class="user-metric-label">Avg Check-In</td>
                <td class="user-metric-value">'.$avgCheckin.' min</td>
            </tr>
            <tr>
                <td class="user-metric-label">Error Rate</td>
                <td class="user-metric-value '.($errorRate < 2 ? 'status-good' : ($errorRate < 5 ? 'status-warning' : 'status-danger')).'">'.$errorRate.'%</td>
            </tr>';
        }

        $html .= '</table></td>';
        $count++;
    }

    // Add empty cell if odd number of users
    if ($count % 2 == 1) {
        $html .= '<td width="50%"></td>';
    }

    $html .= '</tr></table></div></div>';
}

// Footer
$html .= '<div class="report-footer">
    <div class="generated-info">
        Report generated on '.date('F j, Y \a\t g:i A').' | Performance Analytics System
    </div>
</div>';

$html .= '</body></html>';

// Write HTML to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$filename = str_replace(' ', '_', $title) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');

exit;
?>