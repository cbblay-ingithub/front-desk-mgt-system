<?php
/**
 * Function to get appointment details by ID (MySQLi version)
 * Use this to get the badge number after appointment creation
 */
if (!function_exists('getMailTemplateAppointmentDetails')) {
    function getMailTemplateAppointmentDetails($conn, $appointmentId): ?array
    {
        try {
            $sql = "SELECT a.*, v.Name as VisitorName, v.Email as VisitorEmail, v.Phone as VisitorPhone,
                           u.Name as HostName, u.Email as HostEmail
                    FROM appointments a
                    LEFT JOIN visitors v ON a.VisitorID = v.VisitorID  
                    LEFT JOIN users u ON a.HostID = u.UserID
                    WHERE a.AppointmentID = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $stmt->close();

            return $appointment ?: null;
        } catch (Exception $e) {
            error_log("Error fetching appointment details: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Function to get appointment details by badge number (MySQLi version)
 *
 * @param mysqli $conn Database connection
 * @param string $badgeNumber Badge number to search for
 * @return array|null Appointment details or null if not found
 */
function getAppointmentByBadge($conn, $badgeNumber): ?array
{
    try {
        $sql = "SELECT a.*, v.Name as VisitorName, v.Email as VisitorEmail, v.Phone as VisitorPhone,
                       u.Name as HostName, u.Email as HostEmail
                FROM appointments a
                LEFT JOIN visitors v ON a.VisitorID = v.VisitorID  
                LEFT JOIN users u ON a.HostID = u.UserID
                WHERE a.BadgeNumber = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $badgeNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();

        return $appointment ?: null;
    } catch (Exception $e) {
        error_log("Error fetching appointment by badge: " . $e->getMessage());
        return null;
    }
}

/**
 * Function to check if you already have a badge number in an existing appointment
 *
 * @param mysqli $conn Database connection
 * @param int $appointmentId Appointment ID
 * @return string|null Existing badge number or null
 */
function getExistingBadgeNumber($conn, $appointmentId): ?string
{
    try {
        $sql = "SELECT BadgeNumber FROM appointments WHERE AppointmentID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? $row['BadgeNumber'] : null;
    } catch (Exception $e) {
        error_log("Error getting existing badge number: " . $e->getMessage());
        return null;
    }
}

/**
 * Function to validate badge number format
 *
 * @param string $badgeNumber Badge number to validate
 * @return bool True if valid format
 */
function isValidBadgeNumber($badgeNumber): bool
{
    // Format: B-000001 (based on your trigger)
    $pattern = '/^B-\d{6}$/';
    return preg_match($pattern, $badgeNumber) === 1;
}

/**
 * Email template for appointment scheduling
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Date and time of the appointment
 * @param string $badgeNumber Unique badge number
 * @param string $visitorEmail Visitor's email
 * @param string $hostEmail Host's email (optional)
 * @return string HTML email body
 */
function getScheduledEmailTemplate($visitorName, $hostName, $appointmentTime, $badgeNumber, $visitorEmail = '', $hostEmail = ''): string
{
    // Format the date for better readability
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Confirmation</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #2a5885;">Appointment Confirmation</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>Your appointment with <strong>$hostName</strong> has been successfully scheduled for:</p>
                
                <div style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #e8f4fd; border-radius: 5px; margin: 20px 0;">
                    $formattedDateTime
                </div>
                
                <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #155724;">
                        <i style="margin-right: 8px;">🏷️</i>Your Badge Number
                    </h3>
                    <div style="font-size: 24px; font-weight: bold; color: #155724; text-align: center; font-family: 'Courier New', monospace; letter-spacing: 2px;">
                        $badgeNumber
                    </div>
                    <p style="margin-bottom: 0; font-size: 14px; color: #155724;">
                        <strong>Important:</strong> Please bring this badge number when checking in for your appointment.
                    </p>
                </div>
                
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">Appointment Details:</h4>
                    <ul style="color: #856404; margin-bottom: 0;">
                        <li><strong>Host:</strong> $hostName</li>
                        <li><strong>Date & Time:</strong> $formattedDateTime</li>
                        <li><strong>Badge Number:</strong> $badgeNumber</li>
                    </ul>
                </div>
                
                <p><strong>What to bring:</strong></p>
                <ul>
                    <li>Valid government-issued ID</li>
                    <li>This badge number: <strong>$badgeNumber</strong></li>
                    <li>Any relevant documents for your meeting</li>
                </ul>
                
                <p>Please arrive 10-15 minutes early for check-in. If you need to reschedule or cancel, please contact us as soon as possible.</p>
                
                <p>Thank you for choosing our services.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for rescheduled appointments
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $oldTime Original appointment time
 * @param string $newTime New appointment time
 * @param string $badgeNumber Badge number (remains same for rescheduled appointments)
 * @return string HTML email body
 */
function getRescheduledEmailTemplate($visitorName, $hostName, $oldTime, $newTime, $badgeNumber): string
{
    // Format dates for better readability
    $formattedOldTime = date('l, F j, Y \a\t g:i A', strtotime($oldTime));
    $formattedNewTime = date('l, F j, Y \a\t g:i A', strtotime($newTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Rescheduled</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #e67e22;">Appointment Rescheduled</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>Your appointment with <strong>$hostName</strong> has been rescheduled.</p>
                
                <div style="margin: 20px 0; padding: 15px; background-color: #fef5e7; border-radius: 5px;">
                    <p style="margin: 5px 0;"><strong>Previous date and time:</strong></p>
                    <p style="margin: 5px 0; text-decoration: line-through; color: #6c757d;">$formattedOldTime</p>
                    
                    <p style="margin: 15px 0 5px;"><strong>New date and time:</strong></p>
                    <p style="margin: 5px 0; font-weight: bold; font-size: 16px; color: #e67e22;">$formattedNewTime</p>
                </div>
                
                <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h4 style="margin-top: 0; color: #155724;">
                        <i style="margin-right: 8px;">🏷️</i>Your Badge Number (Unchanged)
                    </h4>
                    <div style="font-size: 20px; font-weight: bold; color: #155724; text-align: center; font-family: 'Courier New', monospace; letter-spacing: 2px;">
                        $badgeNumber
                    </div>
                    <p style="margin-bottom: 0; font-size: 14px; color: #155724;">
                        Your badge number remains the same for the rescheduled appointment.
                    </p>
                </div>
                
                <p>If this new time doesn't work for you, please contact us as soon as possible to make alternative arrangements.</p>
                
                <p>Thank you for your understanding.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for cancelled appointments
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Original appointment time
 * @param string $badgeNumber Badge number for reference
 * @return string HTML email body
 */
function getCancelledEmailTemplate($visitorName, $hostName, $appointmentTime, $badgeNumber): string
{
    // Format the date for better readability
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Cancelled</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #c0392b;">Appointment Cancelled</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>We regret to inform you that your appointment with <strong>$hostName</strong> scheduled for <strong>$formattedDateTime</strong> has been cancelled.</p>
                
                <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <h4 style="margin-top: 0; color: #721c24;">
                        <i style="margin-right: 8px;">🏷️</i>Cancelled Badge Number
                    </h4>
                    <div style="font-size: 18px; font-weight: bold; color: #721c24; text-align: center; font-family: 'Courier New', monospace; letter-spacing: 2px;">
                        $badgeNumber
                    </div>
                    <p style="margin-bottom: 0; font-size: 14px; color: #721c24;">
                        This badge number is no longer valid for check-in.
                    </p>
                </div>
                
                <p>If you would like to reschedule, please contact us at your earliest convenience. A new badge number will be assigned for your new appointment.</p>
                
                <p>We apologize for any inconvenience this may cause.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for 30-minute appointment reminder
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Date and time of the appointment
 * @param string $badgeNumber Badge number for check-in
 * @return string HTML email body
 */
function getReminderEmailTemplate($visitorName, $hostName, $appointmentTime, $badgeNumber): string
{
    // Format the date for better readability
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Reminder</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #2980b9;">🔔 Appointment Reminder</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>This is a reminder for your upcoming appointment with <strong>$hostName</strong>, scheduled for:</p>
                
                <div style="font-weight: bold; font-size: 18px; text-align: center; padding: 15px; background-color: #e8f4fd; border-radius: 5px; margin: 20px 0;">
                    $formattedDateTime
                </div>
                
                <div style="background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                    <h4 style="margin-top: 0; color: #0c5460;">
                        <i style="margin-right: 8px;">🏷️</i>Your Badge Number
                    </h4>
                    <div style="font-size: 22px; font-weight: bold; color: #0c5460; text-align: center; font-family: 'Courier New', monospace; letter-spacing: 2px;">
                        $badgeNumber
                    </div>
                    <p style="margin-bottom: 0; font-size: 14px; color: #0c5460;">
                        Please have this badge number ready for quick check-in.
                    </p>
                </div>
                
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #856404;">⏰ Quick Checklist:</h4>
                    <ul style="color: #856404; margin-bottom: 0;">
                        <li>✓ Arrive 10-15 minutes early</li>
                        <li>✓ Bring valid ID</li>
                        <li>✓ Have your badge number ready: <strong>$badgeNumber</strong></li>
                        <li>✓ Bring any required documents</li>
                    </ul>
                </div>
                
                <p>Please arrive on time to ensure a smooth experience. If you need to reschedule or cancel, please contact us as soon as possible.</p>
                
                <p>We look forward to seeing you!</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for overdue appointments
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Date and time of the missed appointment
 * @param string $badgeNumber Badge number for reference
 * @return string HTML email body
 */
function getOverdueEmailTemplate($visitorName, $hostName, $appointmentTime, $badgeNumber): string
{
    // Format the date for better readability
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Overdue Notice</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #d35400;">⚠️ Appointment Overdue Notice</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>We noticed that you have not arrived for your appointment with <strong>$hostName</strong>, which was scheduled for:</p>
                
                <div style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #fef5e7; border-radius: 5px; margin: 20px 0;">
                    $formattedDateTime
                </div>
                
                <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <h4 style="margin-top: 0; color: #721c24;">
                        <i style="margin-right: 8px;">🏷️</i>Your Badge Number
                    </h4>
                    <div style="font-size: 18px; font-weight: bold; color: #721c24; text-align: center; font-family: 'Courier New', monospace; letter-spacing: 2px;">
                        $badgeNumber
                    </div>
                    <p style="margin-bottom: 0; font-size: 14px; color: #721c24;">
                        This badge number may expire soon if the appointment is not attended.
                    </p>
                </div>
                
                <p><strong>Please note:</strong> Appointments are automatically cancelled 30 minutes after the scheduled time if the visitor does not arrive. To reschedule or discuss your appointment, please contact us immediately.</p>
                
                <p>We apologize for any inconvenience and look forward to assisting you.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for appointments cancelled by host with reason
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Appointment time
 * @param string $cancellationReason Cancellation reason
 * @param string $badgeNumber Badge number for reference
 * @return string Email body
 */
function getCancelledByHostEmailTemplate($visitorName, $hostName, $appointmentTime, $cancellationReason, $badgeNumber): string
{
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Appointment Cancelled</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; padding: 20px 0;'>
                <h2 style='color: #c0392b;'>Appointment Cancelled by Host</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f9f9f9; border-radius: 5px;'>
                <p>Dear $visitorName,</p>
                
                <p>We regret to inform you that your appointment with <strong>$hostName</strong> scheduled for <strong>$formattedDateTime</strong> has been cancelled.</p>

                <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4 style='margin-top: 0; color: #856404;'>Reason for Cancellation:</h4>
                    <p style='margin-bottom: 0; color: #856404; font-style: italic;'>$cancellationReason</p>
                </div>
                
                <div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                    <h4 style='margin-top: 0; color: #721c24;'>
                        <i style='margin-right: 8px;'>🏷️</i>Cancelled Badge Number
                    </h4>
                    <div style='font-size: 18px; font-weight: bold; color: #721c24; text-align: center; font-family: "Courier New", monospace; letter-spacing: 2px;'>
                        $badgeNumber
                    </div>
                    <p style='margin-bottom: 0; font-size: 14px; color: #721c24;'>
                        This badge number is no longer valid for check-in.
                    </p>
                </div>
                
                <p>If you would like to reschedule, please contact us at your earliest convenience. A new badge number will be assigned for your new appointment.</p>
                
                <p>We apologize for any inconvenience this may cause.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for hosts when an appointment is scheduled
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Date and time of the appointment
 * @param string $badgeNumber Badge number assigned to visitor
 * @param string $visitorEmail Visitor's email
 * @return string HTML email body
 */
function getHostScheduledEmailTemplate($visitorName, $hostName, $appointmentTime, $badgeNumber, $visitorEmail = ''): string
{
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>New Appointment Scheduled</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #2a5885;">📅 New Appointment Scheduled</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $hostName,</p>
                
                <p>You have a new appointment with <strong>$visitorName</strong> scheduled for:</p>
                
                <div style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #e8f4fd; border-radius: 5px; margin: 20px 0;">
                    $formattedDateTime
                </div>
                
                <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h4 style="margin-top: 0; color: #155724;">👤 Visitor Information:</h4>
                    <ul style="color: #155724; margin-bottom: 0;">
                        <li><strong>Name:</strong> $visitorName</li>
                        <li><strong>Badge Number:</strong> $badgeNumber</li>
                        <li><strong>Email:</strong> $visitorEmail</li>
                        <li><strong>Appointment Time:</strong> $formattedDateTime</li>
                    </ul>
                </div>
                
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #856404;">📋 Reminder:</h4>
                    <p style="margin-bottom: 0; color: #856404;">
                        The visitor will use badge number <strong>$badgeNumber</strong> for check-in. 
                        Please prepare accordingly and ensure you're available at the scheduled time.
                    </p>
                </div>
                
                <p>Please prepare accordingly for this meeting.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for hosts when an appointment is rescheduled
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $newTime New appointment time
 * @param string $badgeNumber Badge number (remains same)
 * @return string HTML email body
 */
function getHostRescheduledEmailTemplate($visitorName, $hostName, $newTime, $badgeNumber): string
{
    $formattedNewTime = date('l, F j, Y \a\t g:i A', strtotime($newTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Rescheduled</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #e67e22;">🔄 Appointment Rescheduled</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $hostName,</p>
                
                <p>Your appointment with <strong>$visitorName</strong> has been rescheduled to:</p>
                
                <div style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #fef5e7; border-radius: 5px; margin: 20px 0;">
                    $formattedNewTime
                </div>
                
                <div style="background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                    <h4 style="margin-top: 0; color: #0c5460;">📋 Updated Details:</h4>
                    <ul style="color: #0c5460; margin-bottom: 0;">
                        <li><strong>Visitor:</strong> $visitorName</li>
                        <li><strong>Badge Number:</strong> $badgeNumber (unchanged)</li>
                        <li><strong>New Time:</strong> $formattedNewTime</li>
                    </ul>
                </div>
                
                <p>Please update your schedule accordingly.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

/**
 * Email template for hosts when an appointment is cancelled
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Original appointment time
 * @param string $badgeNumber Badge number for reference
 * @return string HTML email body
 */
function getHostCancelledEmailTemplate($visitorName, $hostName, $appointmentTime, $badgeNumber): string
{
    $formattedDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentTime));

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Cancelled</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0;">
                <h2 style="color: #c0392b;">❌ Appointment Cancelled</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $hostName,</p>
                
                <p>Your appointment with <strong>$visitorName</strong> on <strong>$formattedDateTime</strong> has been cancelled.</p>
                
                <div style="background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <h4 style="margin-top: 0; color: #721c24;">📋 Cancelled Appointment Details:</h4>
                    <ul style="color: #721c24; margin-bottom: 0;">
                        <li><strong>Visitor:</strong> $visitorName</li>
                        <li><strong>Badge Number:</strong> $badgeNumber</li>
                        <li><strong>Original Time:</strong> $formattedDateTime</li>
                    </ul>
                </div>
                
                <p>Please adjust your schedule accordingly. The visitor's badge number <strong>$badgeNumber</strong> is no longer valid for check-in.</p>
                
                <p>Best regards,<br>
                <strong>Hightel Consult Team</strong></p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

?>