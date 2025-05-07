<?php
// This file should be run as a separate process using a tool like Supervisor
require_once '../dbConfig.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server as SocketServer;

class NotificationServer implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $userConnections = [];
    protected $conn;

    public function __construct() {
        global $conn;
        $this->clients = new \SplObjectStorage;
        $this->conn = $conn;
        echo "Notification server started\n";
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        // Handle user authentication/registration
        if (isset($data['type']) && $data['type'] === 'register') {
            $userId = $data['userId'];
            $this->userConnections[$userId] = $from;
            echo "User {$userId} registered\n";

            // Send any unread notifications to the user
            $this->sendUnreadNotifications($userId);
        }
    }

    public function sendUnreadNotifications($userId) {
        // Query for unread notifications
        $sql = "SELECT n.NotificationID, n.TicketID, n.Type, n.Payload, n.CreatedAt, 
                h.Description as TicketDescription
                FROM Notifications n
                JOIN Help_Desk h ON n.TicketID = h.TicketID 
                WHERE n.UserID = ? AND n.IsRead = FALSE";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        // Send notifications if user is connected
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send(json_encode([
                'type' => 'unread_notifications',
                'notifications' => $notifications
            ]));
        }
    }

    public function notifyUser($userId, $notification) {
        // Send notification if user is connected
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send(json_encode([
                'type' => 'notification',
                'notification' => $notification
            ]));
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);

        // Remove user connection mapping
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Create WebSocket server
$server = new NotificationServer();

$loop = Factory::create();
$webSocket = new SocketServer('0.0.0.0:8080', $loop);
$webServer = new IoServer(
    new HttpServer(
        new WsServer($server)
    ),
    $webSocket
);

// Create a file to hold the server instance for external access
$serverInstance = serialize($server);
file_put_contents('server_instance.dat', $serverInstance);

echo "WebSocket server running on port 8080\n";
$loop->run();