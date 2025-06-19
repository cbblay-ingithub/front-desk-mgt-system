<?php
require_once 'emails.php';
require_once 'mailTemplates.php';

// Test email sending
$test = sendAppointmentEmail(
    'your-email@example.com',
    'Test Email',
    '<h1>This is a test</h1>'
);

var_dump($test); // Should return true if successful