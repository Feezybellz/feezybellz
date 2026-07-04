# WebSocket

Real-time bidirectional messaging: a standalone WebSocket server
(`websocket:serve`), a browser client (`websocket.js`), a PHP client
(`WebSocketClient`), and a synchronous bridge (`WS`) for pushing events
from normal HTTP code into connected sockets.

## Starting the server

```bash
php console websocket:serve                          # ws://0.0.0.0:8080
php console websocket:serve --host=127.0.0.1 --port=9000
php console websocket:serve --ssl-cert=/path/fullchain.pem --ssl-key=/path/privkey.pem
php console websocket:serve --silent                 # background daemon (prints PID)
php console websocket:serve --ping-interval=25 --ping-timeout=10
```

Config (`config/app.php` → `websocket`, env-overridable):

```php
'websocket' => [
    'host'          => env('WS_HOST', '0.0.0.0'),
    'port'          => env('WS_PORT', 8080),
    'internal_port' => env('WS_INTERNAL_PORT', 8081),   // loopback-only trigger port

    // Keepalive — ping_interval MUST be shorter than any proxy idle
    // timeout in front of you (nginx proxy_read_timeout, LB idle).
    'ping_interval'     => (int) env('WS_PING_INTERVAL', 30),
    'ping_timeout'      => (int) env('WS_PING_TIMEOUT', 10),
    'handshake_timeout' => (int) env('WS_HANDSHAKE_TIMEOUT', 10),

    // Internal-trigger auth (see "Broadcasting from PHP" below).
    'internal_secret'            => env('WS_INTERNAL_SECRET', ''),   // '' = APP_KEY
    'require_internal_signature' => env('WS_REQUIRE_INTERNAL_SIGNATURE', true),
],
```

> **The server refuses to boot without a secret** (APP_KEY suffices)
> unless you explicitly set `require_internal_signature=false` — a
> dev-only opt-out.

## Server-side event handlers

Routes live in `routes/websocket.php` (loaded by `websocket:serve`).
Two registration styles:

### Style 1: global handlers (`$server->on`)

Fire for every client. Handler signature: `($data, $socket, $reply)`.

```php
$server->on('chat_message', function ($data, $socket, $reply) {
    // $data = ['clientId' => ..., 'data' => <client payload>, '_ackId' => ...]
    $socket->to('lobby')->emit('chat_message', [
        'from' => $socket->id,
        'text' => $data['data']['text'] ?? '',
    ]);

    if ($reply) $reply(['delivered' => true]);   // acknowledgment
});
```

### Style 2: per-socket handlers (Socket.IO style)

Registered inside the `connection` event; fire only for that client:

```php
$server->on('connection', function ($data, $socket) {
    $socket->emit('welcome', ['id' => $socket->id]);

    $socket->on('private_action', function ($payload, $socket, $reply) {
        // only THIS client triggers this handler
    });
});
```

### Rooms

```php
$socket->join('room-42');            $socket->leave('room-42');
$socket->to('room-42')->emit('event', $data);     // everyone else in room
$server->toRoom('room-42', 'event', $data);       // everyone in room
$server->broadcast('event', $data);               // every connected client
```

## Browser client (websocket.js)

Served at `/_framework/websocket.js` by the running server.

```html
<script src="/_framework/websocket.js"></script>
<script>
const socket = new WsClient({ port: 8080 });   // or new WsClient('ws://host:8080')

socket.on('connect',   () => socket.join('lobby'));
socket.on('chat_message', (msg) => render(msg));
socket.on('reconnecting', ({attempt}) => showBanner(attempt));

socket.emit('chat_message', { text: 'hello' });
socket.room('lobby').emit('message', 'hi all');
</script>
```

Built-in resilience (no code needed):

- **Auto-reconnect** with exponential backoff after any disconnect.
- **App-level heartbeat**: pings every 25s (`pingIntervalMs` option);
  the server always answers with a `pong` event.
- **Stale-link detection**: if nothing is heard for two ping intervals
  (half-dead TCP — mobile network switch, NAT timeout), the client
  force-closes and reconnects. Listen to `stale_connection` to observe.

## PHP client (WebSocketClient) — three usage styles

```php
use Framework\Core\WebSocket\WebSocketClient;

$client = new WebSocketClient('127.0.0.1', 8080);
// or: WebSocketClient::url('wss://chat.example.com/ws')
$client->connect();
```

### Style 1: emit-only producer

```php
// e.g. a cron/queue job pushing updates
$client->emit('metrics', ['cpu' => 0.42]);
```

Safe to leave idle: `emit()` transparently drains the socket first, so
server keepalive pings are answered even if you never call `listen()`.

### Style 2: request/response (ack)

```php
$users = $client->emitWithAck('get-users', ['room' => 'lobby'], timeout: 5.0);

// or callback form:
$client->emit('get-users', ['room' => 'lobby'], function ($reply) {
    echo count($reply['users']);
});
```

### Style 3: long-running consumer

```php
$client->on('order_created', fn ($data) => process($data));
$client->on('disconnected',  fn ($info) => Log::warn('WS lost', $info));

$client->loop();          // blocks forever
// or: $client->listen(0.5);  // pump for up to 0.5s, then return
// or: $data = $client->waitFor('welcome', 5.0);  // block for one event
```

Rooms mirror the JS client: `$client->join('lobby')`,
`$client->leave('lobby')`, `$client->toRoom('lobby', 'hi')`.

## Broadcasting from PHP (the `WS` facade)

Normal HTTP/console code can't hold a WebSocket — instead it pushes
through the server's loopback trigger port. **Every payload is
HMAC-signed** (secret defaults to `APP_KEY`) so other local processes
can't inject broadcasts:

```php
use Framework\Core\WebSocket\WS;

// to every connected client:
WS::broadcast('news_published', ['id' => $post->id, 'title' => $post->title]);

// to one room:
WS::to('order-'.$order->id, 'status_changed', ['status' => 'shipped']);
```

Fire-and-forget: if the WS server isn't running, the call is silently a
no-op (1s connect timeout). Rejected (unsigned/tampered/replayed)
payloads are logged by the server.

## Production checklist

- Terminate TLS at the WS server (`--ssl-cert/--ssl-key`) **or** proxy
  `wss://` through nginx — browsers on HTTPS pages require `wss://`.
- Set `ping_interval` below every intermediary's idle timeout.
- Keep the internal port firewalled to loopback (it binds `127.0.0.1`
  and payloads are signed, but defense in depth).
- Origin allowlist: `$server->setAllowedOrigins(['https://app.example.com'])`
  in your websocket routes file blocks cross-site WebSocket hijacking.
- Connection caps: `$server->setLimits($bufferBytes, $maxPendingFrames, $maxConnections)`.
