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
        /* ... existing styles ... */
        .individual-section {
            margin-top: 40px;
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
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .individual-metric {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9f9f9;
        }
        .metric-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-value {
            font-size: 18px;
        }
        .trend-list {
            padding-left: 20px;
            margin-top: 10px;
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
if ($reportType === 'host') {
    $html .= '<div class="metric-container">
        <div class="metric-card">
            <div class="metric-title">Total Appointments</div>
            <div class="metric-value">'.$report['appointment_metrics']['total_appointments'].'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Completed Appointments</div>
            <div class="metric-value">'.$report['appointment_metrics']['completed'].'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Resolution Rate</div>
            <div class="metric-value">';

    if ($report['resolution_rates']['total_tickets'] > 0) {
        $html .= round(($report['resolution_rates']['resolved'] / $report['resolution_rates']['total_tickets']) * 100).'%';
    } else {
        $html .= '0%';
    }

    $html .= '</div>
        </div>
    </div>';

} elseif ($reportType === 'support') {
    $html .= '<div class="metric-container">
        <div class="metric-card">
            <div class="metric-title">Total Tickets</div>
            <div class="metric-value">'.$report['total_tickets'].'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Avg. Resolution Time</div>
            <div class="metric-value">'.round($report['resolution_times']['avg_resolution_hours']).' hours</div>
        </div>
    </div>
    
    <div class="table-container">
        <h3 class="text-center">Category Breakdown</h3>
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

    $html .= '</tbody>
        </table>
    </div>';

} elseif ($reportType === 'frontdesk') {
    $html .= '<div class="metric-container">
        <div class="metric-card">
            <div class="metric-title">Total Appointments</div>
            <div class="metric-value">'.$report['scheduling_metrics']['total_appointments'].'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Visitors Checked In</div>
            <div class="metric-value">'.$report['visitor_metrics']['visitors_checked_in'].'</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-title">Lost Items Claimed</div>
            <div class="metric-value">
                '.$report['lost_found_metrics']['claimed'].' / '.$report['lost_found_metrics']['total_items'].'
            </div>
        </div>
    </div>
    
    <div class="metric-card full-width">
        <div class="metric-title">Average Check-in Delay</div>
        <div class="metric-value">
            '.round($report['visitor_metrics']['avg_checkin_delay']).' minutes
        </div>
    </div>';
}

// Individual Performance Section
if (!empty($users)) {
    $html .= '<div class="individual-section">
        <div class="individual-header">
            <h2>Individual Performance Metrics</h2>
        </div>
        <div class="metric-grid">';

    foreach ($users as $user) {
        $metrics = $individualReports[$user['UserID']];

        $html .= '<div class="individual-metric">
            <h3>'.$user['Name'].'</h3>';

        if ($reportType === 'host') {
            $html .= '<div class="metric-title">Total Appointments</div>
                <div class="metric-value">'.($metrics['total_appointments'] ?? 0).'</div>
                
                <div class="metric-title">Completed</div>
                <div class="metric-value">'.($metrics['completed'] ?? 0).'</div>
                
                <div class="metric-title">No-Show Rate</div>
                <div class="metric-value">'.($metrics['no_show_rate'] ?? 0).'%</div>
                
                <div class="metric-title">Cancelled</div>
                <div class="metric-value">'.($metrics['cancelled'] ?? 0).'</div>
                
                <div class="metric-title">Upcoming</div>
                <div class="metric-value">'.($metrics['upcoming'] ?? 0).'</div>
                
                <div class="metric-title">Peak Hour</div>
                <div class="metric-value">'.($metrics['peak_hour'] ? $metrics['peak_hour'].':00' : 'N/A').'</div>
                
                <div class="metric-title">Monthly Trends</div>
                <ul class="trend-list">';

            if (!empty($metrics['monthly_trends'])) {
                foreach ($metrics['monthly_trends'] as $trend) {
                    $html .= '<li>'.date('M Y', mktime(0,0,0,$trend['month'],1,$trend['year'])).': '.($trend['completed'] ?? 0).'</li>';
                }
            } else {
                $html .= '<li>No trends data available</li>';
            }

            $html .= '</ul>';

        } elseif ($reportType === 'support') {
            $statusBreakdown = $metrics['status_breakdown'] ?? [];

            $html .= '<div class="metric-title">Open Tickets</div>
                <div class="metric-value">'.($statusBreakdown['open'] ?? 0).'</div>
                
                <div class="metric-title">In Progress</div>
                <div class="metric-value">'.($statusBreakdown['in-progress'] ?? 0).'</div>
                
                <div class="metric-title">Resolved</div>
                <div class="metric-value">'.($statusBreakdown['resolved'] ?? 0).'</div>
                
                <div class="metric-title">Closed</div>
                <div class="metric-value">'.($statusBreakdown['closed'] ?? 0).'</div>
                
                <div class="metric-title">Avg. Resolution Time</div>
                <div class="metric-value">'.($metrics['avg_resolution_hours'] !== null ? round($metrics['avg_resolution_hours']) : 'N/A').' hrs</div>
                
                <div class="metric-title">Tickets Created</div>
                <div class="metric-value">'.($metrics['tickets_created'] ?? 0).'</div>';

        } elseif ($reportType === 'frontdesk') {
            $html .= '<div class="metric-title">Appointments Scheduled</div>
                <div class="metric-value">'.($metrics['total_appointments_scheduled'] ?? 0).'</div>
                
                <div class="metric-title">Avg. Check-In Time</div>
                <div class="metric-value">'.($metrics['avg_checkin_time'] !== null ? round($metrics['avg_checkin_time'], 2) : 'N/A').' mins</div>';
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