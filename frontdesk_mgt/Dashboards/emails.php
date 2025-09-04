<?php
// Require PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';

// Email functionality for appointment system

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



/**
 * Send an email notification for appointment actions
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body content (HTML)
 * @param string $altBody Plain text alternative (optional)
 * @return bool True if email sent successfully, false otherwise
 */
function sendAppointmentEmail($to, $subject, $body, $altBody = '')
{
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.dreamhost.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'project@hightelconsult.com';
        $mail->Password = 'a@eaSwRQnQHU33xU';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Sender and recipient
        $mail->setFrom('project@hightelconsult.com', 'Hightel Consult');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Set plain text alternative if provided, otherwise generate from HTML
        if (empty($altBody)) {
            $altBody = strip_tags($body);
        }
        $mail->AltBody = $altBody;

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
/**
 * Send temporary password email using PHPMailer
 */
function sendTemporaryPasswordEmail($email, $name, $tempPassword,$expiryHours): bool
{
    try {
        $subject = "Your Temporary Password";
        $body = getTemporaryPasswordEmailTemplate($name, $tempPassword,$expiryHours);

        // Use the existing sendAppointmentEmail function
        return sendAppointmentEmail($email, $subject, $body);
    } catch (Exception $e) {
        error_log("Error sending temporary password email: " . $e->getMessage());
        return false;
    }
}
