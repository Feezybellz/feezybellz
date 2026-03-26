class WsClient {
    constructor(config = {}) {
        if (typeof config === 'string') config = { url: config };

        this.url = this._buildUrl(config);
        this.ws = null;
        this.listeners = {};
        
        this.autoConnect = config.autoConnect !== false;
        this.reconnect = config.reconnect !== false;
        this.maxReconnectDelay = config.maxReconnectDelay || 10000;
        this.pingIntervalMs = config.pingIntervalMs || 25000;
        
        this.reconnectAttempts = 0;
        this.pingInterval = null;
        this.intentionallyDisconnected = false;
        
        if (this.autoConnect) this.connect();
    }

    _buildUrl(config) {
        if (config.url) return config.url;
        const secure = config.secure !== undefined ? config.secure : window.location.protocol === 'https:';
        const protocol = secure ? 'wss' : 'ws';
        const host = config.host || config.domain || window.location.hostname || 'localhost';
        const port = config.port ? `:${config.port}` : '';
        const path = config.path ? (config.path.startsWith('/') ? config.path : `/${config.path}`) : '';
        return `${protocol}://${host}${port}${path}`;
    }

    connect() {
        this.intentionallyDisconnected = false;
        try {
            this.ws = new WebSocket(this.url);
            
            this.ws.onopen = (event) => {
                this.reconnectAttempts = 0; 
                this.startHeartbeat();
                this._trigger('connect', event);
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const parsed = JSON.parse(event.data);
                    if (parsed && parsed.event) this._trigger(parsed.event, parsed.data);
                } catch (e) {
                    this._trigger('raw_message', event.data);
                }
            };
            
            this.ws.onclose = (event) => {
                this.stopHeartbeat();
                this._trigger('disconnect', event);
                if (this.reconnect && !this.intentionallyDisconnected) this.attemptReconnect();
            };
            
            this.ws.onerror = (error) => this._trigger('error', error);
            
        } catch (e) {
            this._trigger('error', e);
            if (this.reconnect && !this.intentionallyDisconnected) this.attemptReconnect();
        }
    }

    disconnect() {
        this.intentionallyDisconnected = true;
        this.stopHeartbeat();
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
    }

    on(event, callback) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(callback);
    }

    emit(event, data = null) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({ event, data }));
        } else {
            console.warn(`Cannot emit '${event}'. WebSocket is currently disconnected.`);
        }
    }

    // ==========================================
    // 🔌 NEW SOCKET.IO STYLE HELPERS
    // ==========================================

    /**
     * Join a specific room
     * @param {string} roomName 
     */
    join(roomName) {
        this.emit('join', { room: roomName });
        return this; // Make it chainable
    }

    /**
     * Leave a specific room
     * @param {string} roomName 
     */
    leave(roomName) {
        this.emit('leave', { room: roomName });
        return this;
    }

    /**
     * Target a specific room for a message
     * @example socket.room('staff').emit('message', 'Hello team!');
     * @param {string} roomName 
     */
    room(roomName) {
        return {
            emit: (eventName, data) => {
                // If standard message, map it to your PHP backend's 'room_message' event structure
                if (eventName === 'message') {
                    this.emit('room_message', { room: roomName, message: data });
                } else {
                    // For any custom future events you build in PHP
                    this.emit(eventName, { room: roomName, data: data });
                }
            }
        };
    }

    /**
     * Alias for .room() (Socket.IO uses .to('roomName'))
     */
    to(roomName) {
        return this.room(roomName);
    }

    // ==========================================
    // ⚙️ INTERNAL METHODS
    // ==========================================

    _trigger(event, data) {
        if (this.listeners[event]) this.listeners[event].forEach(cb => cb(data));
    }

    startHeartbeat() {
        this.stopHeartbeat();
        this.pingInterval = setInterval(() => this.emit('ping', { timestamp: Date.now() }), this.pingIntervalMs);
    }

    stopHeartbeat() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
    }

    attemptReconnect() {
        let delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), this.maxReconnectDelay);
        this.reconnectAttempts++;
        this._trigger('reconnecting', { attempt: this.reconnectAttempts, delay: delay });
        setTimeout(() => this.connect(), delay);
    }
}