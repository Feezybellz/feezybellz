export default class WsClient {
    constructor(config = {}) {
        if (typeof config === 'string') config = { url: config };

        this.url = this._buildUrl(config);
        this.ws = null;
        this.listeners = {};
        this.ackCallbacks = {};
        this.ackCounter = 0;
        this.queue = [];
        
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
        let secure = config.secure !== undefined ? config.secure : window.location.protocol === 'https:';
        
        // Allow forcing unsecure even on HTTPS pages (usually blocked by browser, but useful for local dev)
        if (config.forceUnsecure) secure = false;

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
                
                // Drain queue
                while (this.queue.length > 0) {
                    const { event, data, callback } = this.queue.shift();
                    this.emit(event, data, callback);
                }
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const parsed = JSON.parse(event.data);
                    if (!parsed) return;

                    // Handle acknowledgments
                    if (parsed.event === '__ack__' && parsed.data && parsed.data._ackId) {
                        const callback = this.ackCallbacks[parsed.data._ackId];
                        if (callback) {
                            callback(parsed.data.data);
                            delete this.ackCallbacks[parsed.data._ackId];
                        }
                        return;
                    }

                    if (parsed.event) this._trigger(parsed.event, parsed.data);
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

    emit(event, data = null, callback = null) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            const payload = { event, data };

            if (callback && typeof callback === 'function') {
                const ackId = `ack_${++this.ackCounter}_${Date.now()}`;
                this.ackCallbacks[ackId] = callback;
                payload._ackId = ackId;

                // Timeout for ack (15s)
                setTimeout(() => {
                    if (this.ackCallbacks[ackId]) {
                        delete this.ackCallbacks[ackId];
                        callback({ status: 'error', message: 'Acknowledgment timeout' });
                    }
                }, 15000);
            }

            this.ws.send(JSON.stringify(payload));
        } else {
            // Buffer if still connecting
            if (!this.ws || this.ws.readyState === WebSocket.CONNECTING) {
                this.queue.push({ event, data, callback });
            } else {
                console.warn(`Cannot emit '${event}'. WebSocket is currently disconnected.`);
                if (callback) callback({ status: 'error', message: 'WebSocket disconnected' });
            }
        }
    }

    // ==========================================
    // 🔌 NEW SOCKET.IO STYLE HELPERS
    // ==========================================

    join(roomName) {
        this.emit('join', { room: roomName });
        return this;
    }

    leave(roomName) {
        this.emit('leave', { room: roomName });
        return this;
    }

    room(roomName) {
        return {
            emit: (eventName, data, callback = null) => {
                if (eventName === 'message') {
                    this.emit('room_message', { room: roomName, message: data }, callback);
                } else {
                    this.emit(eventName, { room: roomName, data: data }, callback);
                }
            }
        };
    }

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

export { WsClient};

if (typeof window !== 'undefined') {
    window.WsClient = WsClient;
}
