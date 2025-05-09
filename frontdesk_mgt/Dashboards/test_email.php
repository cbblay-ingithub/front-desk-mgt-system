<?php
// Test file for email functionality
// Include necessary files
require_once './emails.php';
require_once './mailTemplates.php';

// Test email sending function
$result = sendAppointmentEmail(
    'youremail@example.com', // Replace with your email to test
    'Email System Test',
    '<h1>Test Email</h1><p>This is a test email from the appointment system.</p>'
);

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>Email sent successfully!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Email sending failed. Check server logs for more details.</p>";
}

// Test email templates
echo "<h2>Email Templates Preview</h2>";

// Test scheduled email template
echo "<h3>Scheduled Email Template:</h3>";
$scheduledTemplate = getScheduledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00'
);
echo $scheduledTemplate;

// Test rescheduled email template
echo "<h3>Rescheduled Email Template:</h3>";
$rescheduledTemplate = getRescheduledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00',
    '2025-05-03 16:00:00'
);
echo $rescheduledTemplate;

// Test cancelled email template
echo "<h3>Cancelled Email Template:</h3>";
$cancelledTemplate = getCancelledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00'
);
echo $cancelledTemplate;