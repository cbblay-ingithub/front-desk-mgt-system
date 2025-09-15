<?php
global $conn;
session_start();
require_once('../dbConfig.php');
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
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d');      // Default to today
$reportType = $_GET['report_type'] ?? 'detailed';   // detailed or summary

// Create new PDF document
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Visitor Management System');
$pdf->SetAuthor('Front Desk');
$pdf->SetTitle('Visitor Logs Report');
$pdf->SetSubject('Visitor Activity Report');

// Set margins
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Set image scale
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 15, 'VISITOR LOGS REPORT', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Period: ' . date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate)), 0, 1, 'C');
$pdf->Ln(5);

// Fetch visitor logs data
$sql = "SELECT 
            v.Name,
            v.Email,
            v.Phone,
            v.IDType,
            vl.CheckInTime,
            vl.CheckOutTime,
            vl.Visit_Purpose,
            vl.Status,
            TIMESTAMPDIFF(MINUTE, vl.CheckInTime, COALESCE(vl.CheckOutTime, NOW())) as DurationMinutes,
            u.Name as HostName
        FROM visitor_logs vl
        JOIN visitors v ON vl.VisitorID = v.VisitorID
        LEFT JOIN users u ON vl.HostID = u.UserID
        WHERE DATE(vl.CheckInTime) BETWEEN ? AND ?
        ORDER BY vl.CheckInTime DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

if ($reportType === 'summary') {
    generateSummaryReport($pdf, $result, $startDate, $endDate);
} else {
    generateDetailedReport($pdf, $result, $startDate, $endDate);
}

// Close and output PDF
$pdf->Output('visitor_report_' . date('Ymd_His') . '.pdf', 'D'); // D for download

// Function to generate detailed report
function generateDetailedReport($pdf, $result, $startDate, $endDate) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Detailed Visitor Logs', 0, 1);
    $pdf->Ln(2);

    // Table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 9);
    $header = array('Name', 'Check-In', 'Check-Out', 'Duration', 'Purpose', 'Status', 'Host');
    $widths = array(40, 30, 30, 20, 40, 25, 40);

    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Table data
    $pdf->SetFont('helvetica', '', 8);
    $fill = false;

    while ($row = $result->fetch_assoc()) {
        $duration = $row['CheckOutTime'] ?
            round($row['DurationMinutes'] / 60, 1) . ' hrs' :
            'Still in';

        $pdf->Cell($widths[0], 6, $row['Name'], 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[1], 6, date('M j, H:i', strtotime($row['CheckInTime'])), 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, $row['CheckOutTime'] ? date('M j, H:i', strtotime($row['CheckOutTime'])) : '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[3], 6, $duration, 'LR', 0, 'C', $fill);
        $pdf->Cell($widths[4], 6, substr($row['Visit_Purpose'], 0, 30), 'LR', 0, 'L', $fill);
        $pdf->Cell($widths[5], 6, $row['Status'], 'LR', 0, 'C', $fill);
        $pdf->Cell($widths[6], 6, $row['HostName'] ?? 'N/A', 'LR', 0, 'L', $fill);
        $pdf->Ln();

        $fill = !$fill;
    }

    // Closing line
    $pdf->Cell(array_sum($widths), 0, '', 'T');
}

// Function to generate summary report
function generateSummaryReport($pdf, $result, $startDate, $endDate) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Summary Report', 0, 1);
    $pdf->Ln(2);

    // Calculate statistics
    $totalVisits = 0;
    $totalDuration = 0;
    $currentCheckins = 0;
    $visitPurposes = [];

    while ($row = $result->fetch_assoc()) {
        $totalVisits++;

        if ($row['CheckOutTime']) {
            $totalDuration += $row['DurationMinutes'];
        } else {
            $currentCheckins++;
        }

        $purpose = $row['Visit_Purpose'] ?: 'General Visit';
        if (!isset($visitPurposes[$purpose])) {
            $visitPurposes[$purpose] = 0;
        }
        $visitPurposes[$purpose]++;
    }

    $avgDuration = $totalVisits > 0 ? round(($totalDuration / 60) / ($totalVisits - $currentCheckins), 1) : 0;

    // Display statistics
    $pdf->SetFont('helvetica', '', 10);

    $pdf->Cell(60, 8, 'Total Visits:', 0, 0, 'L');
    $pdf->Cell(0, 8, $totalVisits, 0, 1);

    $pdf->Cell(60, 8, 'Currently Checked In:', 0, 0, 'L');
    $pdf->Cell(0, 8, $currentCheckins, 0, 1);

    $pdf->Cell(60, 8, 'Average Visit Duration:', 0, 0, 'L');
    $pdf->Cell(0, 8, $avgDuration . ' hours', 0, 1);

    $pdf->Cell(60, 8, 'Report Period:', 0, 0, 'L');
    $pdf->Cell(0, 8, date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate)), 0, 1);

    $pdf->Ln(5);

    // Visit purposes breakdown
    if (!empty($visitPurposes)) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Visit Purposes Breakdown:', 0, 1);
        $pdf->SetFont('helvetica', '', 9);

        arsort($visitPurposes);
        foreach ($visitPurposes as $purpose => $count) {
            $percentage = round(($count / $totalVisits) * 100, 1);
            $pdf->Cell(100, 7, substr($purpose, 0, 50), 0, 0, 'L');
            $pdf->Cell(30, 7, $count . ' visits', 0, 0, 'R');
            $pdf->Cell(0, 7, $percentage . '%', 0, 1);
        }
    }
}
?>