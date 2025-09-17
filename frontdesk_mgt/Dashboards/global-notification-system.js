/**
 * Global Admin Notification System
 * Works across all admin pages with real-time notifications
 */
class GlobalNotificationSystem {
    constructor() {
        this.lastNotificationCount = 0;
        this.isInitialized = false;
        this.pollingInterval = null;
        this.connectionRetries = 0;
        this.maxRetries = 5;
        this.pollingFrequency = 15000; // 15 seconds
        this.audioEnabled = true;
        this.browserNotificationsEnabled = false;

        this.init();
    }

    async init() {
        if (this.isInitialized) return;

        console.log('Initializing Global Notification System...');

        // Request browser notification permission
        await this.requestNotificationPermission();

        // Load user preferences
        this.loadUserPreferences();

        // Initial notification check
        this.checkForNotifications();

        // Start polling
        this.startPolling();

        // Bind events
        this.bindEvents();

        // Handle page visibility changes
        this.handleVisibilityChange();

        this.isInitialized = true;
        console.log('Global Notification System initialized successfully');
    }

    async requestNotificationPermission() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            this.browserNotificationsEnabled = permission === 'granted';
            console.log('Browser notifications:', this.browserNotificationsEnabled ? 'enabled' : 'disabled');
        }
    }

    loadUserPreferences() {
        // Load from localStorage or server
        this.audioEnabled = localStorage.getItem('notificationAudio') !== 'false';
        this.pollingFrequency = parseInt(localStorage.getItem('notificationPolling')) || 15000;
    }

    bindEvents() {
        // Handle page unload
        $(window).on('beforeunload', () => {
            this.cleanup();
        });

        // Handle focus/blur for different polling frequencies
        $(window).on('focus', () => {
            this.pollingFrequency = 15000; // More frequent when focused
            this.restartPolling();
        });

        $(window).on('blur', () => {
            this.pollingFrequency = 60000; // Less frequent when not focused
            this.restartPolling();
        });
    }

    handleVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page is hidden, reduce polling frequency
                this.pollingFrequency = 60000;
                this.restartPolling();
            } else {
                // Page is visible, increase polling frequency
                this.pollingFrequency = 15000;
                this.restartPolling();
                // Check immediately when page becomes visible
                this.checkForNotifications();
            }
        });
    }

    startPolling() {
        this.stopPolling(); // Clear any existing interval

        this.pollingInterval = setInterval(() => {
            this.checkForNotifications();
        }, this.pollingFrequency);

        console.log(`Notification polling started (${this.pollingFrequency}ms interval)`);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    restartPolling() {
        this.stopPolling();
        this.startPolling();
    }

    async checkForNotifications() {
        try {
            const response = await $.ajax({
                url: 'fetch-notifications.php',
                method: 'GET',
                data: {
                    count_only: true,
                    last_check: localStorage.getItem('lastNotificationCheck') || 0
                },
                dataType: 'json',
                timeout: 10000
            });

            if (response.success) {
                this.handleNotificationResponse(response);
                this.connectionRetries = 0; // Reset retry counter on success
            } else {
                console.error('Notification check failed:', response.error);
                this.handleConnectionError();
            }

        } catch (error) {
            console.error('Failed to check notifications:', error);
            this.handleConnectionError();
        }
    }

    handleNotificationResponse(response) {
        const currentCount = response.unread_count || 0;
        const newNotifications = response.new_notifications || [];

        // Update badge
        this.updateNotificationBadge(currentCount);

        // Check for new notifications
        if (newNotifications.length > 0) {
            this.handleNewNotifications(newNotifications);
        }

        // Update last check timestamp
        localStorage.setItem('lastNotificationCheck', Date.now());
        this.lastNotificationCount = currentCount;
    }

    handleNewNotifications(notifications) {
        console.log(`${notifications.length} new notification(s) received`);

        notifications.forEach((notification, index) => {
            // Delay each notification slightly to avoid overwhelming the user
            setTimeout(() => {
                this.showNotificationAlert(notification);
            }, index * 1000);
        });

        // Play notification sound (once for all notifications)
        if (this.audioEnabled) {
            this.playNotificationSound();
        }

        // Show toast notification
        this.showToastNotification(notifications);

        // Update page title with notification count
        this.updatePageTitle();

        // Trigger custom event for other parts of the application
        $(document).trigger('newNotifications', [notifications]);
    }

    showNotificationAlert(notification) {
        // Check permission status again before showing notification
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                this.browserNotificationsEnabled = true;
            } else if (Notification.permission !== 'denied') {
                // Request permission if not already set
                this.requestNotificationPermission().then(() => {
                    this.showBrowserNotification(notification);
                });
                return;
            }
        }

        // Browser notification - only show if page is hidden
        if (this.browserNotificationsEnabled && document.hidden) {
            this.showBrowserNotification(notification);
        }

        // In-app notification (floating toast)
        this.showFloatingNotification(notification);
    }

    showBrowserNotification(notification) {
        new Notification(notification.title, {
            body: notification.message,
            icon: '/path/to/notification-icon.png',
            tag: notification.id,
            requireInteraction: false
        });
    }


    showFloatingNotification(notification) {
        const toastHtml = `
            <div class="notification-toast" data-id="${notification.id}">
                <div class="toast-content">
                    <div class="toast-icon">
                        <i class="fas ${this.getNotificationIcon(notification.type)}"></i>
                    </div>
                    <div class="toast-body">
                        <div class="toast-title">${this.escapeHtml(notification.title)}</div>
                        <div class="toast-message">${this.escapeHtml(notification.message)}</div>
                    </div>
                    <button class="toast-close" onclick="globalNotificationSystem.dismissToast(${notification.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="toast-progress"></div>
            </div>
        `;

        // Add to toast container (create if doesn't exist)
        if (!$('#notificationToastContainer').length) {
            $('body').append(`
                <div id="notificationToastContainer" class="notification-toast-container"></div>
            `);
        }

        const $toast = $(toastHtml);
        $('#notificationToastContainer').append($toast);

        // Animate in
        setTimeout(() => $toast.addClass('show'), 100);

        // Auto dismiss after 5 seconds
        setTimeout(() => this.dismissToast(notification.id), 5000);

        // Make clickable
        $toast.on('click', () => {
            this.handleNotificationClick(notification);
            this.dismissToast(notification.id);
        });
    }

    dismissToast(notificationId) {
        const $toast = $(`.notification-toast[data-id="${notificationId}"]`);
        $toast.removeClass('show');
        setTimeout(() => $toast.remove(), 300);
    }

    showToastNotification(notifications) {
        const count = notifications.length;
        let message = '';

        if (count === 1) {
            message = notifications[0].title;
        } else {
            message = `${count} new notifications`;
        }

        // Show bootstrap toast or custom toast
        this.showSystemToast('New Notifications', message, 'info');
    }

    showSystemToast(title, message, type = 'info') {
        const toastHtml = `
            <div class="toast system-toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        // Add to system toast container
        if (!$('#systemToastContainer').length) {
            $('body').append(`
                <div id="systemToastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1060;"></div>
            `);
        }

        const $toast = $(toastHtml);
        $('#systemToastContainer').append($toast);

        // Initialize Bootstrap toast
        const bsToast = new bootstrap.Toast($toast[0], {
            autohide: true,
            delay: 4000
        });
        bsToast.show();

        // Remove from DOM after it's hidden
        $toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }

    updateNotificationBadge(count) {
        const $badge = $('#notificationBadge, .notification-badge');

        if (count > 0) {
            $badge.text(count > 99 ? '99+' : count).show();

            // Add pulsing animation for new notifications
            if (count > this.lastNotificationCount) {
                $badge.addClass('pulse');
                setTimeout(() => $badge.removeClass('pulse'), 2000);
            }
        } else {
            $badge.hide();
        }

        // Update any other notification indicators
        $('.notification-count').text(count);
    }

    updatePageTitle() {
        const currentTitle = document.title;
        const unreadCount = this.lastNotificationCount;

        // Remove existing notification count from title
        const cleanTitle = currentTitle.replace(/^\(\d+\) /, '');

        if (unreadCount > 0) {
            document.title = `(${unreadCount}) ${cleanTitle}`;
        } else {
            document.title = cleanTitle;
        }
    }

    playNotificationSound() {
        if (!this.audioEnabled) return;

        try {
            // Create a more pleasant notification sound
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();

            // Create a pleasant two-tone notification sound
            const frequencies = [800, 600];
            const duration = 0.15;

            frequencies.forEach((freq, index) => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = freq;
                oscillator.type = 'sine';

                const startTime = audioContext.currentTime + (index * 0.2);
                gainNode.gain.setValueAtTime(0, startTime);
                gainNode.gain.linearRampToValueAtTime(0.1, startTime + 0.05);
                gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + duration);

                oscillator.start(startTime);
                oscillator.stop(startTime + duration);
            });

        } catch (error) {
            console.log('Audio notification not supported:', error);

            // Fallback: try to play a system sound
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEcBjuOzfTCdyIGJ3bA9N+WQwsbVK7n6qpZFgxMoe/vsGEfCkGEyPUV');
                audio.volume = 0.3;
                audio.play();
            } catch (e) {
                console.log('Fallback audio also failed:', e);
            }
        }
    }

    handleConnectionError() {
        this.connectionRetries++;

        if (this.connectionRetries >= this.maxRetries) {
            console.warn('Max notification retries reached. Switching to less frequent polling.');
            this.pollingFrequency = 60000; // Switch to 1 minute polling
            this.restartPolling();
            this.connectionRetries = 0; // Reset for next cycle
        }
    }

    handleNotificationClick(notification) {
        // Navigate to relevant page based on notification type
        const entityType = notification.related_entity_type;
        const entityId = notification.related_entity_id;

        let targetUrl = '';

        switch (entityType) {
            case 'user':
                targetUrl = `user_management.php?user_id=${entityId}`;
                break;
            case 'ticket':
                targetUrl = `help_desk.php?ticket_id=${entityId}`;
                break;
            case 'appointment':
                targetUrl = `FD_frontend_dash.php?appointment_id=${entityId}`;
                break;
            case 'visitor':
                targetUrl = `visitor-mgt.php?visitor_id=${entityId}`;
                break;
            case 'lost_item':
                targetUrl = `lost_and_found.php?item_id=${entityId}`;
                break;
            default:
                targetUrl = 'admin-dashboard.php';
                break;
        }

        if (targetUrl) {
            window.location.href = targetUrl;
        }
    }

    getNotificationIcon(type) {
        const icons = {
            user: 'fa-user',
            ticket: 'fa-ticket-alt',
            system: 'fa-cog',
            appointment: 'fa-calendar-check',
            visitor: 'fa-user-friends',
            lost_item: 'fa-search',
            security: 'fa-shield-alt',
            default: 'fa-bell'
        };
        return icons[type] || icons.default;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Public methods for controlling the system
    toggleAudio() {
        this.audioEnabled = !this.audioEnabled;
        localStorage.setItem('notificationAudio', this.audioEnabled);
        console.log('Notification audio:', this.audioEnabled ? 'enabled' : 'disabled');
    }

    setPollingFrequency(frequency) {
        this.pollingFrequency = frequency;
        localStorage.setItem('notificationPolling', frequency);
        this.restartPolling();
    }

    forceCheck() {
        this.checkForNotifications();
    }

    cleanup() {
        this.stopPolling();
        console.log('Global Notification System cleaned up');
    }

    // Debug methods
    testNotification() {
        const testNotification = {
            id: Date.now(),
            title: 'Test Notification',
            message: 'This is a test notification to verify the system is working.',
            type: 'system',
            created_at: new Date().toISOString()
        };
        this.showNotificationAlert(testNotification);
    }

    getStatus() {
        return {
            initialized: this.isInitialized,
            polling: !!this.pollingInterval,
            frequency: this.pollingFrequency,
            audioEnabled: this.audioEnabled,
            browserNotifications: this.browserNotificationsEnabled,
            lastCount: this.lastNotificationCount,
            retries: this.connectionRetries
        };
    }
}

// Initialize the global notification system
$(document).ready(function() {
    // Initialize on all pages
    window.globalNotificationSystem = new GlobalNotificationSystem();

    // Make it available globally for debugging
    window.testNotification = () => window.globalNotificationSystem.testNotification();
    window.notificationStatus = () => console.log(window.globalNotificationSystem.getStatus());
});