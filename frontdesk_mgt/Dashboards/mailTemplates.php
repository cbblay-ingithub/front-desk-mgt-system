<?php
/**
 * Email template for appointment scheduling
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Date and time of the appointment
 * @return string HTML email body
 */
function getScheduledEmailTemplate($visitorName, $hostName, $appointmentTime): string
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
                
                <p>Your appointment with $hostName has been successfully scheduled for:</p>
                
                <p style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #e8f4fd; border-radius: 5px;">
                    $formattedDateTime
                </p>
                
                <p>Please make sure to arrive on time. If you need to reschedule or cancel, please contact us as soon as possible.</p>
                
                <p>Thank you for choosing our services.</p>
                
                <p>Best regards,<br>
                Hightel Consult Team</p>
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
 * @return string HTML email body
 */
function getRescheduledEmailTemplate($visitorName, $hostName, $oldTime, $newTime): string
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
                
                <p>Your appointment with $hostName has been rescheduled.</p>
                
                <div style="margin: 20px 0; padding: 15px; background-color: #fef5e7; border-radius: 5px;">
                    <p style="margin: 5px 0;"><strong>Previous date and time:</strong></p>
                    <p style="margin: 5px 0; text-decoration: line-through;">$formattedOldTime</p>
                    
                    <p style="margin: 15px 0 5px;"><strong>New date and time:</strong></p>
                    <p style="margin: 5px 0; font-weight: bold;">$formattedNewTime</p>
                </div>
                
                <p>If this new time doesn't work for you, please contact us as soon as possible to make alternative arrangements.</p>
                
                <p>Thank you for your understanding.</p>
                
                <p>Best regards,<br>
                Hightel Consult Team</p>
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
 * @return string HTML email body
 */
function getCancelledEmailTemplate($visitorName, $hostName, $appointmentTime): string
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
                
                <p>We regret to inform you that your appointment with $hostName scheduled for <strong>$formattedDateTime</strong> has been cancelled.</p>
                
                <p>If you would like to reschedule, please contact us at your earliest convenience.</p>
                
                <p>We apologize for any inconvenience this may cause.</p>
                
                <p>Best regards,<br>
                Hightel Consult Team</p>
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
 * @return string HTML email body
 */
function getReminderEmailTemplate($visitorName, $hostName, $appointmentTime): string
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
                <h2 style="color: #2980b9;">Appointment Reminder</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>This is a reminder for your upcoming appointment with $hostName, scheduled for:</p>
                
                <p style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #e8f4fd; border-radius: 5px;">
                    $formattedDateTime
                </p>
                
                <p>Please arrive on time to ensure a smooth experience. If you need to reschedule or cancel, please contact us as soon as possible.</p>
                
                <p>We look forward to seeing you!</p>
                
                <p>Best regards,<br>
                Hightel Consult Team</p>
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
 * @return string HTML email body
 */
function getOverdueEmailTemplate($visitorName, $hostName, $appointmentTime): string
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
                <h2 style="color: #d35400;">Appointment Overdue Notice</h2>
            </div>
            
            <div style="padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <p>Dear $visitorName,</p>
                
                <p>We noticed that you have not arrived for your appointment with $hostName, which was scheduled for:</p>
                
                <p style="font-weight: bold; font-size: 16px; text-align: center; padding: 15px; background-color: #fef5e7; border-radius: 5px;">
                    $formattedDateTime
                </p>
                
                <p>Please note that appointments are automatically cancelled 30 minutes after the scheduled time if the visitor does not arrive. To reschedule or discuss your appointment, please contact us immediately.</p>
                
                <p>We apologize for any inconvenience and look forward to assisting you.</p>
                
                <p>Best regards,<br>
                Hightel Consult Team</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}
/**
 * Get the email template for a cancelled appointment
 *
 * @param string $visitorName Visitor's name
 * @param string $hostName Host's name
 * @param string $appointmentTime Appointment time
 * @param string $CancellationReason Cancellation reason
 * @return string Email body
 */
function getCancelledByHostEmailTemplate($visitorName, $hostName, $appointmentTime, $CancellationReason) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Appointment Cancelled</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; padding: 20px 0;'>
                <h2 style='color: #c0392b;'>Appointment Cancelled</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f9f9f9; border-radius: 5px;'>
                <p>Dear $visitorName,</p>
                
                <p>We regret to inform you that your appointment with $hostName scheduled for <strong>$appointmentTime</strong> has been cancelled.</p>

                <p ><strong>Reason:</strong> $CancellationReason</p>
                
                <p>If you would like to reschedule, please contact us at your earliest convenience.</p>
                
                <p>We apologize for any inconvenience this may cause.</p>
                
                <p>Best regards,<br>
                Hightel Consult Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

?>