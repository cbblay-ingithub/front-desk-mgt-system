<?php
global $conn;
require_once '../dbConfig.php';
require('../fpdf/fpdf.php');
$pdf = new FPDF();
$pdf->AddPage();

// Set built-in Courier font (no AddFont needed)
$pdf->SetFont('Courier', 'B', 14);
$pdf->Cell(0, 10, 'Visitor Logs Report', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(20, 10, 'Log ID', 1);
$pdf->Cell(40, 10, 'Check-In Time', 1);
$pdf->Cell(40, 10, 'Check-Out Time', 1);
$pdf->Cell(30, 10, 'Visitor ID', 1);
$pdf->Cell(60, 10, 'Purpose', 1);
$pdf->Ln();

$pdf->SetFont('Courier', '', 10);
$result = $conn->query("SELECT LogID, CheckInTime, CheckOutTime, VisitorID, Visit_Purpose FROM visitor_Logs");
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(20, 10, $row['LogID'], 1);
    $pdf->Cell(40, 10, $row['CheckInTime'], 1);
    $pdf->Cell(40, 10, isset($row['CheckOutTime']) ? $row['CheckOutTime'] : 'N/A', 1);
    $pdf->Cell(30, 10, $row['VisitorID'], 1);
    $pdf->Cell(60, 10, $row['Visit_Purpose'], 1);
    $pdf->Ln();
}

$pdf->Output();
?>
