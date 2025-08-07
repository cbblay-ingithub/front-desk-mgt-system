<?php
require_once '../dbConfig.php';
require_once 'report_functions.php';
global $conn;

// Include mPDF library
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Autoload file not found at: $autoloadPath");
}
require_once $autoloadPath;

// Debug class existence
if (!class_exists('\Mpdf\Mpdf')) {
    die("mPDF class not found. Ensure mPDF 8.x is installed.");
}

// Create PDF instance
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'helvetica',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 25,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);
} catch (\Mpdf\MpdfException $e) {
    die("Error creating PDF: " . $e->getMessage());
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

if ($reportType === 'host') {
    $report = getHostReports($startDate, $endDate);
    $title = "Host Performance Report";
    $users = getUsersByRole('Host');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualHostMetrics($user['UserID'], $startDate, $endDate);
    }
} elseif ($reportType === 'support') {
    $report = getSupportReports($startDate, $endDate);
    $title = "Support Staff Performance Report";
    $users = getUsersByRole('Support Staff');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualSupportMetrics($user['UserID'], $startDate, $endDate);
    }
} elseif ($reportType === 'frontdesk') {
    $report = getFrontDeskReports($startDate, $endDate);
    $title = "Front Desk Performance Report";
    $users = getUsersByRole('Front Desk Staff');
    foreach ($users as $user) {
        $individualReports[$user['UserID']] = getIndividualFrontDeskMetrics($user['UserID'], $startDate, $endDate);
    }
} else {
    die("Invalid report type");
}

// Generate HTML content
$html = '<!DOCTYPE html>
<html>
<head>
    <title>'.$title.'</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .report-title {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }
        .date-range {
            text-align: center;
            margin-bottom: 20px;
            font-style: italic;
        }
        .section-title {
            background-color: #f8f9fa;
            padding: 8px;
            border-left: 4px solid #2c3e50;
            margin: 20px 0 10px 0;
        }
        .metric-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .metric-card {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            flex: 1 1 200px;
            min-width: 200px;
            background-color: #f9f9f9;
        }
        .full-width {
            flex: 1 1 100%;
        }
        .metric-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .metric-value {
            font-size: 18px;
            color: #0d6efd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .individual-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .individual-header {
            background-color: #2c3e50;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .individual-metric {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9f9f9;
        }
        .trend-list {
            padding-left: 20px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <h1 class="report-title">'.$title.'</h1>';

if ($startDate || $endDate) {
    $html .= '<div class="date-range">
        Date Range: 
        '.($startDate ? date('M d, Y', strtotime($startDate)) : 'Start').' 
        to 
        '.($endDate ? date('M d, Y', strtotime($endDate)) : 'End').'
    </div>';
}

// Team metrics section
$html .= '<h3 class="section-title">Team Performance Overview</h3>';

if ($reportType === 'host') {
    $html .= '<div class="metric-container">
        <div class="metric-card">
            <div class="metric-title">Total Appointments</div>
            <div class="metric-value">'.($report['appointment_metrics']['total_appointments'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Completed</div>
            <div class="metric-value">'.($report['appointment_metrics']['completed'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Cancelled</div>
            <div class="metric-value">'.($report['appointment_metrics']['cancelled'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Upcoming</div>
            <div class="metric-value">'.($report['appointment_metrics']['upcoming'] ?? 0).'</div>
        </div>
    </div>';

    // Cancellation Reasons
    if (!empty($report['cancellation_reasons'])) {
        $html .= '<h3 class="section-title">Cancellation Reasons</h3>
        <table>
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

    // Visitor Metrics
    if (!empty($report['visitor_metrics'])) {
        $html .= '<h3 class="section-title">Visitor Metrics</h3>
        <div class="metric-container">
            <div class="metric-card">
                <div class="metric-title">New Visitors</div>
                <div class="metric-value">'.($report['visitor_metrics']['new_visitors'] ?? 0).'</div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Returning Visitors</div>
                <div class="metric-value">'.($report['visitor_metrics']['returning_visitors'] ?? 0).'</div>
            </div>
        </div>';
    }

} elseif ($reportType === 'support') {
    $html .= '<div class="metric-container">
        <div class="metric-card">
            <div class="metric-title">Total Tickets</div>
            <div class="metric-value">'.($report['total_tickets'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Resolved</div>
            <div class="metric-value">'.($report['status_breakdown']['resolved'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Avg. Resolution Time</div>
            <div class="metric-value">'.(isset($report['resolution_times']['avg_resolution_hours']) ? round($report['resolution_times']['avg_resolution_hours']) : 'N/A').'h</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Reopened Rate</div>
            <div class="metric-value">'.(isset($individualReports) ? round(array_sum(array_column($individualReports, 'reopened_rate')) / count($individualReports)) : 0).'%</div>
        </div>
    </div>';

    // Ticket Status Breakdown
    if (!empty($report['status_breakdown'])) {
        $html .= '<h3 class="section-title">Ticket Status Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Open</td>
                    <td>'.($report['status_breakdown']['open'] ?? 0).'</td>
                </tr>
                <tr>
                    <td>In Progress</td>
                    <td>'.($report['status_breakdown']['in-progress'] ?? 0).'</td>
                </tr>
                <tr>
                    <td>Resolved</td>
                    <td>'.($report['status_breakdown']['resolved'] ?? 0).'</td>
                </tr>
                <tr>
                    <td>Closed</td>
                    <td>'.($report['status_breakdown']['closed'] ?? 0).'</td>
                </tr>
            </tbody>
        </table>';
    }

    // Category Breakdown
    if (!empty($report['category_breakdown'])) {
        $html .= '<h3 class="section-title">Issue Categories</h3>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($report['category_breakdown'] as $category) {
            $html .= '<tr>
                <td>'.$category['category'].'</td>
                <td>'.$category['count'].'</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

} elseif ($reportType === 'frontdesk') {
    $html .= '<div class="metric-container">
        <div class="metric-card">
            <div class="metric-title">Appointments</div>
            <div class="metric-value">'.($report['scheduling_metrics']['total_appointments'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Completed</div>
            <div class="metric-value">'.($report['scheduling_metrics']['completed'] ?? 0).'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Avg Check-In</div>
            <div class="metric-value">'.($report['visitor_metrics']['avg_checkin_time'] ? round($report['visitor_metrics']['avg_checkin_time']) : 'N/A').' min</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Peak Hour</div>
            <div class="metric-value">'.($report['peak_hour'] ? $report['peak_hour'].':00' : 'N/A').'</div>
        </div>
    </div>';

    // Client Metrics
    if (!empty($report['client_metrics'])) {
        $newClients = $report['client_metrics']['new_clients'] ?? 0;
        $returningClients = $report['client_metrics']['returning_clients'] ?? 0;
        $totalClients = $newClients + $returningClients;
        $newPercentage = $totalClients > 0 ? round(($newClients / $totalClients) * 100) : 0;
        $returningPercentage = $totalClients > 0 ? round(($returningClients / $totalClients) * 100) : 0;

        $html .= '<h3 class="section-title">Client Types</h3>
        <div class="metric-container">
            <div class="metric-card">
                <div class="metric-title">New Clients</div>
                <div class="metric-value">'.$newClients.' ('.$newPercentage.'%)</div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Returning Clients</div>
                <div class="metric-value">'.$returningClients.' ('.$returningPercentage.'%)</div>
            </div>
        </div>';
    }

    // Appointment Status
    if (!empty($report['scheduling_metrics'])) {
        $html .= '<h3 class="section-title">Appointment Status</h3>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Completed</td>
                    <td>'.($report['scheduling_metrics']['completed'] ?? 0).'</td>
                    <td>'.($report['scheduling_metrics']['total_appointments'] > 0 ? round(($report['scheduling_metrics']['completed'] / $report['scheduling_metrics']['total_appointments']) * 100) : 0).'%</td>
                </tr>
                <tr>
                    <td>Cancelled</td>
                    <td>'.($report['scheduling_metrics']['cancelled'] ?? 0).'</td>
                    <td>'.($report['scheduling_metrics']['total_appointments'] > 0 ? round(($report['scheduling_metrics']['cancelled'] / $report['scheduling_metrics']['total_appointments']) * 100) : 0).'%</td>
                </tr>
            </tbody>
        </table>';
    }
}

// Individual Performance Section
if (!empty($users)) {
    $html .= '<div class="individual-section">
        <h3 class="section-title">Individual Performance</h3>
        <div class="metric-grid">';

    foreach ($users as $user) {
        $metrics = $individualReports[$user['UserID']] ?? [];

        $html .= '<div class="individual-metric">
            <h4>'.$user['Name'].'</h4>';

        if ($reportType === 'host') {
            $html .= '<div class="metric-title">Total Appointments</div>
                <div class="metric-value">'.($metrics['total_appointments'] ?? 0).'</div>
                
                <div class="metric-title">Completed</div>
                <div class="metric-value">'.($metrics['completed'] ?? 0).'</div>
                
                <div class="metric-title">No-Show Rate</div>
                <div class="metric-value">'.($metrics['no_show_rate'] ?? 0).'%</div>
                
                <div class="metric-title">Peak Hour</div>
                <div class="metric-value">'.($metrics['peak_hour'] ? date('g A', strtotime($metrics['peak_hour'].':00')) : 'N/A').'</div>';

        } elseif ($reportType === 'support') {
            $resolved = $metrics['status_breakdown']['resolved'] ?? 0;
            $total = $metrics['total_tickets'] ?? 1;
            $resolutionRate = round(($resolved / $total) * 100);

            $html .= '<div class="metric-title">Resolved Tickets</div>
                <div class="metric-value">'.$resolved.'</div>
                
                <div class="metric-title">Resolution Rate</div>
                <div class="metric-value">'.$resolutionRate.'%</div>
                
                <div class="metric-title">Avg. Resolution Time</div>
                <div class="metric-value">'.($metrics['avg_resolution_hours'] !== null ? round($metrics['avg_resolution_hours']) : 'N/A').'h</div>
                
                <div class="metric-title">Reopened Rate</div>
                <div class="metric-value">'.($metrics['reopened_rate'] ?? 0).'%</div>';

        } elseif ($reportType === 'frontdesk') {
            $html .= '<div class="metric-title">Appointments Scheduled</div>
                <div class="metric-value">'.($metrics['total_appointments_scheduled'] ?? 0).'</div>
                
                <div class="metric-title">Avg Check-In Time</div>
                <div class="metric-value">'.($metrics['avg_checkin_time'] !== null ? round($metrics['avg_checkin_time'], 1) : 'N/A').' min</div>
                
                <div class="metric-title">Error Rate</div>
                <div class="metric-value">'.($metrics['error_rate'] ?? 0).'%</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div></div>';
}

$html .= '<div class="footer">
        Report generated on '.date('M d, Y H:i').'
    </div>
</body>
</html>';

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Output PDF
$mpdf->Output($title.'.pdf', 'D');

exit;