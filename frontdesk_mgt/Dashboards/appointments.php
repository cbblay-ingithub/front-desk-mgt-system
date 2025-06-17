<?php
// Include database connection
require_once '../dbConfig.php';
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include email functionality
require_once __DIR__ . '/emails.php';
require_once __DIR__ . '/mailTemplates.php';

// Update appointment statuses at the start of every request
updateAppointmentStatuses();

/**
 * Update appointment statuses based on current time
 */
function updateAppointmentStatuses() {
    global $conn;

    $sql = "UPDATE appointments 
            SET Status = 'Overdue' 
            WHERE Status = 'Upcoming' 
            AND AppointmentTime < NOW()";
    $conn->query($sql);

    $sql = "UPDATE appointments 
            SET Status = 'Cancelled', CancellationReason = 'No-Show' 
            WHERE Status = 'Overdue' 
            AND AppointmentTime < NOW() - INTERVAL 30 MINUTE";
    $conn->query($sql);
}

/**
 * Check if the appointment time is within allowed hours (9:30 AM–11:30 AM or 1:00 PM–4:30 PM)
 *
 * @param string $appointmentTime DateTime of appointment
 * @return bool True if within allowed hours, false otherwise
 */
function isValidAppointmentTime($appointmentTime) {
    $dt = new DateTime($appointmentTime);
    $time = $dt->format('H:i:s');
    return (($time >= '09:30:00' && $time <= '11:30:00') || ($time >= '13:00:00' && $time <= '16:30:00'));
}

/**
 * Get all appointments for a specific host based on the host's ID
 *
 * @param int $hostId The host ID
 * @return array Array of appointments
 */
function getHostAppointments($hostId): array
{
    global $conn;

    $sql = "SELECT a.*, v.Name, v.Email, v.Phone 
            FROM appointments a
            JOIN visitors v ON a.VisitorID = v.VisitorID
            WHERE a.HostID = ? 
            ORDER BY a.AppointmentTime";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    $stmt->close();
    return $appointments;
}

/**
 * Start an appointment session (change status to Ongoing)
 *
 * @param int $appointmentId Appointment ID
 * @return array Response with status and message
 */
function startAppointment($appointmentId): array
{
    global $conn;

    $sql = "SELECT Status FROM appointments WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ["status" => "error", "message" => "Appointment not found"];
    }

    $status = $result->fetch_assoc()['Status'];
    if ($status !== 'Upcoming') {
        return ["status" => "error", "message" => "Only upcoming appointments can be started"];
    }

    $sql = "UPDATE appointments SET Status = 'Ongoing' WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);

    if ($stmt->execute()) {
        return ["status" => "success", "message" => "Session started successfully"];
    } else {
        return ["status" => "error", "message" => "Failed to start session: " . $conn->error];
    }
}

/**
 * Schedule a new appointment
 *
 * @param string $appointmentTime DateTime of appointment
 * @param int $hostId Host ID
 * @param int $visitorId Visitor ID
 * @return array Response with status and message
 */
function scheduleAppointment($appointmentTime, $hostId, $visitorId): array
{
    global $conn;

    $currentTime = date('Y-m-d H:i:s');
    if ($appointmentTime <= $currentTime) {
        return ["status" => "error", "message" => "Appointment time cannot be in the past"];
    }

    if (date('N', strtotime($appointmentTime)) == 7) {
        return ["status" => "error", "message" => "Appointments cannot be scheduled on Sundays"];
    }

    if (!isValidAppointmentTime($appointmentTime)) {
        return ["status" => "error", "message" => "Appointment time is outside allowed hours (9:30 AM–11:30 AM or 1:00 PM–4:30 PM)"];
    }

    $startTime = date('Y-m-d H:i:s', strtotime($appointmentTime) - (45 * 60));
    $endTime = date('Y-m-d H:i:s', strtotime($appointmentTime) + (45 * 60));
    $sql = "SELECT COUNT(*) as count FROM appointments 
            WHERE HostID = ? 
            AND AppointmentTime BETWEEN ? AND ? 
            AND Status IN ('Upcoming', 'Overdue', 'Ongoing')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $hostId, $startTime, $endTime);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        return ["status" => "error", "message" => "You already have an appointment scheduled within 45 minutes of this time"];
    }

    $sql = "INSERT INTO appointments (AppointmentTime, Status, HostID, VisitorID) 
            VALUES (?, 'Upcoming', ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $appointmentTime, $hostId, $visitorId);

    if ($stmt->execute()) {
        $visitorInfo = getVisitorById($visitorId);
        $hostInfo = getHostById($hostId);

        if ($visitorInfo && $hostInfo) {
            $emailBody = getScheduledEmailTemplate(
                $visitorInfo['Name'],
                $hostInfo['Name'],
                $appointmentTime
            );

            sendAppointmentEmail(
                $visitorInfo['Email'],
                'Appointment Confirmation',
                $emailBody
            );
        }
        return ["status" => "success", "message" => "Appointment scheduled successfully", "id" => $conn->insert_id];
    } else {
        return ["status" => "error", "message" => "Failed to schedule appointment: " . $conn->error];
    }
}

/**
 * Create a new visitor
 *
 * @param string $name Visitor name
 * @param string $email Visitor email
 * @param string|null $phone Visitor phone (optional)
 * @return array Response with visitor ID or error
 */
function createVisitor($name, $email, $phone = null): array
{
    global $conn;

    if (empty($name) || empty($email)) {
        return ["status" => "error", "message" => "Name and email are required"];
    }

    $sql = "SELECT VisitorID FROM visitors WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existingVisitor = $result->fetch_assoc();
        return ["status" => "existing", "message" => "Visitor already exists", "visitorId" => $existingVisitor['VisitorID']];
    }

    $sql = "INSERT INTO visitors (Name, Email, Phone) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $phone);

    if ($stmt->execute()) {
        $visitorId = $conn->insert_id;
        return ["status" => "success", "message" => "Visitor created successfully", "visitorId" => $visitorId];
    } else {
        return ["status" => "error", "message" => "Failed to create visitor: " . $conn->error];
    }
}

/**
 * Reschedule an existing appointment
 *
 * @param int $appointmentId Appointment ID
 * @param string $newTime New appointment time
 * @return array Response with status and message
 */
function rescheduleAppointment($appointmentId, $newTime): array
{
    global $conn;

    $oldAppointmentInfo = getAppointmentById($appointmentId);
    $oldTime = $oldAppointmentInfo['AppointmentTime'];

    $currentTime = date('Y-m-d H:i:s');
    if ($newTime <= $currentTime) {
        return ["status" => "error", "message" => "New appointment time cannot be in the past"];
    }

    if (date('N', strtotime($newTime)) == 7) {
        return ["status" => "error", "message" => "Appointments cannot be rescheduled to Sundays"];
    }

    if (!isValidAppointmentTime($newTime)) {
        return ["status" => "error", "message" => "New appointment time is outside allowed hours (9:30 AM–11:30 AM or 1:00 PM–4:30 PM)"];
    }

    $sql = "SELECT HostID FROM appointments WHERE AppointmentID = ? AND Status != 'Cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ["status" => "error", "message" => "Appointment not found or already cancelled"];
    }

    $hostId = $result->fetch_assoc()['HostID'];

    $startTime = date('Y-m-d H:i:s', strtotime($newTime) - (45 * 60));
    $endTime = date('Y-m-d H:i:s', strtotime($newTime) + (45 * 60));
    $sql = "SELECT COUNT(*) as count FROM appointments 
            WHERE HostID = ? 
            AND AppointmentTime BETWEEN ? AND ? 
            AND Status IN ('Upcoming', 'Overdue', 'Ongoing') 
            AND AppointmentID != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $hostId, $startTime, $endTime, $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        return ["status" => "error", "message" => "You already have another appointment within 45 minutes of this time"];
    }

    $sql = "UPDATE appointments SET AppointmentTime = ? WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $newTime, $appointmentId);

    if ($stmt->execute()) {
        $visitorId = $oldAppointmentInfo['VisitorID'];
        $hostId = $oldAppointmentInfo['HostID'];

        $visitorInfo = getVisitorById($visitorId);
        $hostInfo = getHostById($hostId);

        if ($visitorInfo && $hostInfo) {
            $emailBody = getRescheduledEmailTemplate(
                $visitorInfo['Name'],
                $hostInfo['Name'],
                $oldTime,
                $newTime
            );

            sendAppointmentEmail(
                $visitorInfo['Email'],
                'Appointment Rescheduled',
                $emailBody
            );
        }
        return ["status" => "success", "message" => "Appointment rescheduled successfully"];
    } else {
        return ["status" => "error", "message" => "Failed to reschedule appointment: " . $conn->error];
    }
}

/**
 * Cancel an appointment
 *
 * @param int $appointmentId Appointment ID
 * @return array Response with status and message
 */
function cancelAppointment($appointmentId): array
{
    global $conn;

    $appointmentInfo = getAppointmentById($appointmentId);

    $sql = "SELECT Status FROM appointments WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ["status" => "error", "message" => "Appointment not found"];
    }

    $status = $result->fetch_assoc()['Status'];
    if ($status === 'Cancelled') {
        return ["status" => "error", "message" => "Appointment is already cancelled"];
    }

    // Set the cancellation reason to 'Host Cancelled' for host-initiated cancellations
    $cancellationReason = 'Host Cancelled';
    $sql = "UPDATE appointments SET Status = 'Cancelled', CancellationReason = ? WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $cancellationReason, $appointmentId);

    if ($stmt->execute()) {
        $visitorId = $appointmentInfo['VisitorID'];
        $hostId = $appointmentInfo['HostID'];

        $visitorInfo = getVisitorById($visitorId);
        $hostInfo = getHostById($hostId);

        if ($visitorInfo && $hostInfo) {
            $emailBody = getCancelledByHostEmailTemplate(
                $visitorInfo['Name'],
                $hostInfo['Name'],
                $appointmentInfo['AppointmentTime'],
                $cancellationReason,
            );

            sendAppointmentEmail(
                $visitorInfo['Email'],
                'Appointment Cancelled',
                $emailBody
            );
        }
        return ["status" => "success", "message" => "Appointment cancelled successfully"];
    } else {
        return ["status" => "error", "message" => "Failed to cancel appointment: " . $conn->error];
    }
}

/**
 * End an appointment session (change status to 'Completed')
 *
 * @param int $appointmentId Appointment ID
 * @return array Response with status and message
 */
function endAppointment($appointmentId): array
{
    global $conn;

    $sql = "SELECT Status FROM appointments WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ["status" => "error", "message" => "Appointment not found"];
    }

    $status = $result->fetch_assoc()['Status'];
    if ($status !== 'Ongoing') {
        return ["status" => "error", "message" => "Only ongoing sessions can be ended"];
    }

    $sql = "UPDATE appointments SET Status = 'Completed' WHERE AppointmentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);

    if ($stmt->execute()) {
        return ["status" => "success", "message" => "Session completed successfully"];
    } else {
        return ["status" => "error", "message" => "Failed to complete session: " . $conn->error];
    }
}

/**
 * Get visitor list for populating the appointment form
 *
 * @return array Array of visitors
 */
function getVisitorsList(): array
{
    global $conn;

    $sql = "SELECT VisitorID, Name, Email FROM visitors ORDER BY Name";
    $result = $conn->query($sql);

    $visitors = [];
    while ($row = $result->fetch_assoc()) {
        $visitors[] = $row;
    }

    return $visitors;
}

/**
 * Get appointment details by ID
 *
 * @param int $appointmentId Appointment ID
 * @return array|null Appointment details or null if not found
 */
function getAppointmentById($appointmentId) {
    global $conn;

    $sql = "SELECT a.*, v.Name, v.Email 
            FROM appointments a
            JOIN visitors v ON a.VisitorID = v.VisitorID
            WHERE a.AppointmentID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

// Helper function to get visitor information by ID
function getVisitorById($visitorId) {
    global $conn;

    $sql = "SELECT * FROM visitors WHERE VisitorID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $visitorId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

// Helper function to get host information by ID
function getHostById($hostId) {
    global $conn;

    $sql = "SELECT * FROM users WHERE userID = ? AND role = 'Host'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'schedule':
                $appointmentTime = $_POST['appointmentTime'];
                $hostId = $_POST['hostId'];

                if (!empty($_POST['newVisitorName'])) {
                    $visitorResult = createVisitor(
                        $_POST['newVisitorName'],
                        $_POST['newVisitorEmail'],
                        $_POST['newVisitorPhone'] ?? null
                    );

                    if ($visitorResult['status'] === 'error') {
                        echo json_encode($visitorResult);
                        break;
                    }

                    $visitorId = $visitorResult['visitorId'];
                } else {
                    $visitorId = $_POST['visitorId'];
                }

                echo json_encode(scheduleAppointment($appointmentTime, $hostId, $visitorId));
                break;

            case 'reschedule':
                $appointmentId = $_POST['appointmentId'];
                $newTime = $_POST['newTime'];
                echo json_encode(rescheduleAppointment($appointmentId, $newTime));
                break;

            case 'cancel':
                $appointmentId = $_POST['appointmentId'];
                echo json_encode(cancelAppointment($appointmentId));
                break;

            case 'startSession':
                $appointmentId = $_POST['appointmentId'];
                echo json_encode(startAppointment($appointmentId));
                break;

            case 'endSession':
                $appointmentId = $_POST['appointmentId'];
                echo json_encode(endAppointment($appointmentId));
                break;

            case 'getAppointment':
                $appointmentId = $_POST['appointmentId'];
                echo json_encode(getAppointmentById($appointmentId));
                break;

            case 'getVisitors':
                echo json_encode(getVisitorsList());
                break;

            default:
                echo json_encode(["status" => "error", "message" => "Invalid action"]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
    }
    exit;
}
?>