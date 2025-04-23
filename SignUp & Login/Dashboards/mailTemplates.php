// emailTemplates.php
<?php

function getScheduledEmailTemplate($visitorName, $hostName, $appointmentTime) {
    $formattedDate = date('l, F j, Y', strtotime($appointmentTime));
    $formattedTime = date('g:i A', strtotime($appointmentTime));

    return "
    <html>
    <body>
        <h2>Appointment Confirmation</h2>
        <p>Dear $visitorName,</p>
        <p>Your appointment with $hostName has been scheduled for:</p>
        <p><strong>Date:</strong> $formattedDate<br>
        <strong>Time:</strong> $formattedTime</p>
        <p>If you need to cancel or reschedule, please contact us as soon as possible.</p>
        <p>Thank you,<br>
        Appointment Management System</p>
    </body>
    </html>
    ";
}

function getRescheduledEmailTemplate($visitorName, $hostName, $oldTime, $newTime) {
    $oldFormattedDate = date('l, F j, Y', strtotime($oldTime));
    $oldFormattedTime = date('g:i A', strtotime($oldTime));
    $newFormattedDate = date('l, F j, Y', strtotime($newTime));
    $newFormattedTime = date('g:i A', strtotime($newTime));

    return "
    <html>
    <body>
        <h2>Appointment Rescheduled</h2>
        <p>Dear $visitorName,</p>
        <p>Your appointment with $hostName has been rescheduled:</p>
        <p><strong>From:</strong> $oldFormattedDate at $oldFormattedTime<br>
        <strong>To:</strong> $newFormattedDate at $newFormattedTime</p>
        <p>If this new time doesn't work for you, please contact us as soon as possible.</p>
        <p>Thank you,<br>
        Appointment Management System</p>
    </body>
    </html>
    ";
}

function getCancelledEmailTemplate($visitorName, $hostName, $appointmentTime) {
    $formattedDate = date('l, F j, Y', strtotime($appointmentTime));
    $formattedTime = date('g:i A', strtotime($appointmentTime));

    return "
    <html>
    <body>
        <h2>Appointment Cancelled</h2>
        <p>Dear $visitorName,</p>
        <p>Your appointment with $hostName scheduled for $formattedDate at $formattedTime has been cancelled.</p>
        <p>If you would like to schedule a new appointment, please contact us.</p>
        <p>Thank you,<br>
        Appointment Management System</p>
    </body>
    </html>
    ";
}