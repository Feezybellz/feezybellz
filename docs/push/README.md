# Push Notifications

Native push notifications to browsers (Web Push / VAPID) and mobile
devices (Firebase Cloud Messaging) behind one fluent facade.

## Drivers & configuration

`config/push.php` — supported: **`web`** (VAPID Web Push, default) and
**`fcm`** (Firebase):

```env
PUSH_DRIVER=web
```

### Web Push setup

```bash
php console push:vapid      # generates VAPID keys into .env
                            # (push:generate is an alias)
```

This writes `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`. The public key
goes to the browser during subscription; the private key signs sends.

### FCM setup

Put your Firebase credentials in `config/push.php` under `fcm` and set
`PUSH_DRIVER=fcm`.

## Subscribing users (frontend, Web Push)

The browser flow: register a service worker → ask permission →
`pushManager.subscribe()` with your VAPID public key → POST the
resulting subscription JSON to your backend → store it per user:

```js
const reg = await navigator.serviceWorker.register('/sw.js');
const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: '<VAPID_PUBLIC_KEY>',
});
await fetch('/push/subscribe', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(sub),      // save this JSON server-side, per user
});
```

```php
// backend: store it
public function subscribe(Request $request)
{
    DB::table('push_subscriptions')->insert([
        'user_id'      => Auth::id(),
        'subscription' => json_encode($request->all()),
    ]);
}
```

## Sending

```php
use Framework\Core\Push\Push;

$subscription = DB::table('push_subscriptions')
    ->where('user_id', '=', $userId)
    ->first()['subscription'];

$ok = Push::to($subscription)          // subscription JSON (web) or device token (fcm)
    ->title('New message')
    ->body('You have a new message from Ada.')
    ->data(['url' => '/messages/42'])  // custom payload for your service worker
    ->send();
```

`to()` accepts a single target or an array of targets. The `data`
array reaches your service worker's `push` event — use it to route
clicks.

## Testing from the terminal

```bash
php console push:send    # prompts for a subscription JSON + message
```

Useful for verifying keys and the service worker before wiring the UI.
