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

// Get report parameters
$reportType = $_POST['report_type'] ?? null;
$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;


// Get report data
$report = [];
if ($reportType === 'host') {
    $report = getHostReports($startDate, $endDate);
    $title = "Host Performance Report";
} elseif ($reportType === 'support') {
    $report = getSupportReports($startDate, $endDate);
    $title = "Support Staff Performance Report";
} elseif ($reportType === 'frontdesk') {
    $report = getFrontDeskReports($startDate, $endDate);
    $title = "Front Desk Performance Report";
} else {
    die("Invalid report type");
}

// Generate HTML content
$html = '<!DOCTYPE html>
<html>
<head>
    <title>'.$title.'</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; }
        .report-title { 
            text-align: center; 
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 24px;
        }
        .date-range {
            text-align: center;
            margin-bottom: 30px;
            font-style: italic;
            color: #7f8c8d;
        }
        .metric-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .metric-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            box-sizing: border-box;
            width: calc(33.33% - 20px);
            min-width: 200px;
        }
        .metric-title {
            font-size: 16px;
            color: #3498db;
            margin-bottom: 10px;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .table-container {
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #3498db;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 10px;
            border: 1px solid #e0e0e0;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .full-width {
            width: 100%;
        }
        .text-center {
            text-align: center;
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
            <div class="metric-value">'.count($report['ticket_volume']).'</div>
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