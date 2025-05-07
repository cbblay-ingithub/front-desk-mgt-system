// Notification System JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const notificationBell = document.getElementById('notificationBell');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');
    const markAllReadBtn = document.getElementById('markAllReadBtn');

    // Global variables
    let ws = null;
    let notifications = [];
    let unreadCount = 0;

    // Get user ID from session (you might need to adjust this)
    const userId = document.body.getAttribute('data-user-id');

    // Initialize WebSocket connection
    function initWebSocket() {
        ws = new WebSocket('ws://localhost:8080');

        ws.onopen = function() {
            console.log('WebSocket connection established');

            // Register user with WebSocket server
            if (userId) {
                ws.send(JSON.stringify({
                    type: 'register',
                    userId: userId
                }));
            }
        };

        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);

            if (data.type === 'notification') {
                // Add new notification
                addNotification(data.notification);
                updateNotificationCount(unreadCount + 1);
                playNotificationSound();
            } else if (data.type === 'unread_notifications') {
                // Process unread notifications
                notifications = data.notifications;
                updateNotificationList();
                updateNotificationCount(notifications.length);
            }
        };

        ws.onclose = function() {
            console.log('WebSocket connection closed');
            // Try to reconnect after a delay
            setTimeout(initWebSocket, 5000);
        };

        ws.onerror = function(error) {
            console.error('WebSocket error:', error);
        };
    }

    // Initialize the notification system
    function init() {
        // Initialize WebSocket connection
        initWebSocket();

        // Load initial notifications
        loadNotifications();

        // Setup event listeners
        notificationBell.addEventListener('click', toggleNotificationPanel);
        markAllReadBtn.addEventListener('click', markAllAsRead);

        // Close panel when clicking outside
        document.addEventListener('click', function(event) {
            if (!notificationPanel.contains(event.target) &&
                event.target !== notificationBell &&
                !notificationBell.contains(event.target)) {
                notificationPanel.style.display = 'none';
            }
        });

        // Periodically check for notifications if WebSocket fails
        setInterval(loadNotificationCount, 60000); // Every minute
    }

    // Toggle notification panel visibility
    function toggleNotificationPanel() {
        if (notificationPanel.style.display === 'block') {
            notificationPanel.style.display = 'none';
        } else {
            notificationPanel.style.display = 'block';
            loadNotifications();
        }
    }

    // Load notifications from server via AJAX
    function loadNotifications() {
        fetch('notif_ajax.php?action=get_notifications')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notifications = data.notifications;
                    unreadCount = data.unread_count;
                    updateNotificationList();
                    updateNotificationCount(unreadCount);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    // Load just the notification count
    function loadNotificationCount() {
        fetch('notif_ajax.php?action=get_notification_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationCount(data.unread_count);
                }
            })
            .catch(error => console.error('Error loading notification count:', error));
    }

    // Update notification list in the UI
    function updateNotificationList() {
        notificationList.innerHTML = '';

        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="empty-notification">No notifications</div>';
            return;
        }

        notifications.forEach(notification => {
            const notificationItem = document.createElement('div');
            notificationItem.className = 'notification-item';
            if (!notification.IsRead) {
                notificationItem.classList.add('unread');
            }

            const payload = typeof notification.Payload === 'string'
                ? JSON.parse(notification.Payload)
                : notification.Payload;

            // Format notification based on type
            // Format notification based on type
            let title = '';
            switch (notification.Type) {
                case 'assignment':
                    title = `Ticket Assigned: #${notification.TicketID}`;
                    break;
                case 'info_request':
                    title = `Information Requested: #${notification.TicketID}`;
                    break;
                case 'resolution':
                    title = `Ticket Resolved: #${notification.TicketID}`;
                    break;
                case 'closure':
                    title = `Ticket Closed: #${notification.TicketID}`;
                    break;
                case 'auto_closure':
                    title = `Ticket Auto-Closed: #${notification.TicketID}`;
                    break;
                default:
                    title = `Notification for Ticket #${notification.TicketID}`;
            }
            // Format time
            const createdAt = new Date(notification.CreatedAt);
            const timeString = formatTimeAgo(createdAt);

            notificationItem.innerHTML = `
                <div class="notification-title">${title}</div>
                <div class="notification-description">${notification.TicketDescription}</div>
                <div class="notification-time">${timeString}</div>
            `;

            // Add click event to view ticket and mark as read
            notificationItem.addEventListener('click', () => {
                markAsRead(notification.NotificationID);
                window.location.href = `help_desk.php?view_ticket=${notification.TicketID}`;
            });

            notificationList.appendChild(notificationItem);
        });
    }

    // Update notification count in the UI
    function updateNotificationCount(count) {
        unreadCount = count;
        notificationCount.textContent = count > 0 ? count : '';
    }

    // Mark a notification as read
    function markAsRead(notificationId) {
        fetch('notif_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_as_read',
                notification_id: notificationId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local notification state
                    notifications = notifications.map(notification => {
                        if (notification.NotificationID === notificationId) {
                            notification.IsRead = true;
                        }
                        return notification;
                    });
                    updateNotificationList();
                    updateNotificationCount(data.unread_count);
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
    }

    // Mark all notifications as read
    function markAllAsRead() {
        fetch('notif_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_all_as_read'
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local notification state
                    notifications = notifications.map(notification => {
                        notification.IsRead = true;
                        return notification;
                    });
                    updateNotificationList();
                    updateNotificationCount(0);
                }
            })
            .catch(error => console.error('Error marking all notifications as read:', error));
    }

    // Add a new notification to the list
    function addNotification(notification) {
        notifications.unshift(notification);
        updateNotificationList();
    }

    // Play notification sound
    function playNotificationSound() {
        // Create audio element for notification sound
        const audio = new Audio('notification.mp3');
        audio.play().catch(e => console.log('Audio play failed:', e));
    }

    // Format time ago (e.g., "5 minutes ago")
    function formatTimeAgo(date) {
        const now = new Date();
        const diff = Math.floor((now - date) / 1000); // seconds

        if (diff < 60) {
            return 'Just now';
        } else if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diff / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    }

    // Initialize system when DOM is ready
    init();
});