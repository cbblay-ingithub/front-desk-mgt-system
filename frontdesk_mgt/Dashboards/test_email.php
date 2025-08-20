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
    '2025-05-01 14:30:00',
    'B-0000945'

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

// Test reminder email template
echo "<h3>Reminder Email Template:</h3>";
$reminderTemplate = getReminderEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00'
);
echo $reminderTemplate;

// Test overdue email template
echo "<h3>Overdue Email Template:</h3>";
$overdueTemplate = getOverdueEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00'
);
echo $overdueTemplate;

// Test cancelled email template
echo "<h3>Cancelled Email Template:</h3>";
$cancelledTemplate = getCancelledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00'
);
echo $cancelledTemplate;
echo "<h3> Host Cancelled Email Template:</h3>";
$cancelledByHostTemplate = getCancelledByHostEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00',
    'Host Cancelled'
);
echo $cancelledByHostTemplate;

// Test scheduled host email template
echo "<h3>Scheduled Email Template:</h3>";
$hostScheduledTemplate = getHostScheduledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00',
);
echo $hostScheduledTemplate;

// Test rescheduled host email template
echo "<h3>Rescheduled Email Template:</h3>";
$hostRescheduledTemplate = getHostRescheduledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00',
);
echo $hostRescheduledTemplate;

// Test cancelled host email template
echo "<h3>Cancelled Email Template:</h3>";
$hostCancelledTemplate = getHostCancelledEmailTemplate(
    'John Doe',
    'Dr. Smith',
    '2025-05-01 14:30:00',
    'Scheduling Conflict'
);
echo $hostCancelledTemplate;
