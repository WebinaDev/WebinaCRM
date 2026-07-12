/**
 * WebinoCRM WebSocket Client
 * Handles real-time notifications using WebSocket connections
 */

(function($) {
    'use strict';

    class WebinoWebSocket {
        constructor() {
            this.ws = null;
            this.reconnectAttempts = 0;
            this.maxReconnectAttempts = 5;
            this.reconnectDelay = 3000;
            this.heartbeatInterval = null;
            this.notificationHandlers = [];
            
            this.init();
        }

        init() {
            if (!window.webinoWS) {
                console.error('WebinoCRM: WebSocket configuration not found');
                return;
            }

            this.connect();
            this.setupEventListeners();
        }

        connect() {
            try {
                this.ws = new WebSocket(webinoWS.url);
                
                this.ws.onopen = () => this.onOpen();
                this.ws.onmessage = (event) => this.onMessage(event);
                this.ws.onerror = (error) => this.onError(error);
                this.ws.onclose = () => this.onClose();
                
            } catch (error) {
                console.error('WebinoCRM WebSocket connection failed:', error);
                this.scheduleReconnect();
            }
        }

        onOpen() {
            console.log('WebinoCRM WebSocket connected');
            this.reconnectAttempts = 0;
            
            // Authenticate
            this.send({
                type: 'auth',
                user_id: webinoWS.user_id,
                token: webinoWS.token
            });

            // Start heartbeat
            this.startHeartbeat();
            
            // Trigger custom event
            $(document).trigger('webinocrm:ws:connected');
        }

        onMessage(event) {
            try {
                const data = JSON.parse(event.data);
                
                switch(data.type) {
                    case 'notification':
                        this.handleNotification(data);
                        break;
                    case 'pong':
                        // Heartbeat response
                        break;
                    case 'auth_success':
                        console.log('WebSocket authentication successful');
                        break;
                    case 'auth_failed':
                        console.error('WebSocket authentication failed');
                        this.ws.close();
                        break;
                    default:
                        console.log('Unknown message type:', data.type);
                }
                
                // Trigger custom event for all messages
                $(document).trigger('webinocrm:ws:message', [data]);
                
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        }

        onError(error) {
            console.error('WebinoCRM WebSocket error:', error);
            $(document).trigger('webinocrm:ws:error', [error]);
        }

        onClose() {
            console.log('WebinoCRM WebSocket disconnected');
            this.stopHeartbeat();
            $(document).trigger('webinocrm:ws:disconnected');
            
            // Attempt to reconnect
            this.scheduleReconnect();
        }

        scheduleReconnect() {
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                console.log(`Reconnecting... (Attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
                
                setTimeout(() => {
                    this.connect();
                }, this.reconnectDelay * this.reconnectAttempts);
            } else {
                console.error('Max reconnection attempts reached. Please refresh the page.');
                this.showReconnectionError();
            }
        }

        send(data) {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify(data));
            } else {
                console.warn('WebSocket is not connected. Message not sent:', data);
            }
        }

        startHeartbeat() {
            this.heartbeatInterval = setInterval(() => {
                this.send({ type: 'ping' });
            }, 30000); // Every 30 seconds
        }

        stopHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
        }

        handleNotification(data) {
            // Display notification
            this.displayNotification(data);
            
            // Update notification counter
            this.updateNotificationCounter();
            
            // Call registered handlers
            this.notificationHandlers.forEach(handler => handler(data));
            
            // Trigger custom event
            $(document).trigger('webinocrm:notification', [data]);
        }

        displayNotification(data) {
            // Check if browser notifications are supported and permitted
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(data.title || 'WebinoCRM', {
                    body: data.message,
                    icon: data.icon || webinoWS.plugin_url + '/assets/images/logo.png',
                    tag: 'webinocrm-' + (data.id || Date.now()),
                    requireInteraction: data.important || false
                });
            }

            // Also show in-app notification
            this.showInAppNotification(data);
        }

        showInAppNotification(data) {
            const notificationType = data.notification_type || 'info';
            const iconMap = {
                'success': 'fa-check-circle',
                'error': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            };

            const notification = $(`
                <div class="webinocrm-notification webinocrm-notification-${notificationType}" 
                     data-id="${data.id || ''}" 
                     style="display:none;">
                    <div class="notification-icon">
                        <i class="fas ${iconMap[notificationType] || iconMap.info}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${data.title || ''}</div>
                        <div class="notification-message">${data.message || ''}</div>
                    </div>
                    <button class="notification-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);

            // Append to notification container or create one
            let container = $('.webinocrm-notifications-container');
            if (container.length === 0) {
                container = $('<div class="webinocrm-notifications-container"></div>');
                $('body').append(container);
            }

            container.append(notification);
            notification.slideDown(300);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.slideUp(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Close button
            notification.find('.notification-close').on('click', function() {
                notification.slideUp(300, function() {
                    $(this).remove();
                });
            });
        }

        updateNotificationCounter() {
            // Update badge count
            $.ajax({
                url: webinocrm_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'webinocrm_get_unread_count',
                    nonce: webinocrm_ajax_obj.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const count = response.data.count;
                        const badge = $('.webinocrm-notification-badge');
                        
                        if (count > 0) {
                            badge.text(count > 99 ? '99+' : count).show();
                        } else {
                            badge.hide();
                        }
                    }
                }
            });
        }

        showReconnectionError() {
            const errorMsg = $(`
                <div class="webinocrm-connection-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>اتصال برقرار نشد. لطفاً صفحه را رفرش کنید.</span>
                    <button onclick="location.reload()">رفرش</button>
                </div>
            `);
            
            $('body').append(errorMsg);
        }

        // Public method to register notification handler
        onNotification(callback) {
            if (typeof callback === 'function') {
                this.notificationHandlers.push(callback);
            }
        }

        setupEventListeners() {
            // Request notification permission on first user interaction
            $(document).one('click', () => {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            });
        }

        // Public method to close connection
        disconnect() {
            if (this.ws) {
                this.stopHeartbeat();
                this.ws.close();
            }
        }
    }

    // Initialize WebSocket when document is ready
    $(document).ready(function() {
        if (webinoWS && webinoWS.user_id) {
            window.WebinoWebSocket = new WebinoWebSocket();
        }
    });

})(jQuery);

