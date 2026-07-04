# Events

A lightweight event dispatcher: define an event class, register
listeners, dispatch. Listeners can run synchronously or be pushed to
the queue.

## Defining an event

An event is any plain object. Its **public properties are the payload**
— keep them JSON-safe (scalars/arrays) if any listener will be queued:

```php
<?php

namespace App\Events;

class UserRegistered
{
    public int $userId;
    public string $email;

    public function __construct(int $userId, string $email)
    {
        $this->userId = $userId;
        $this->email = $email;
    }
}
```

## Registering listeners — three styles

### Style 1: closure (quick, inline)

```php
use Framework\Core\Events\Dispatcher;
use App\Events\UserRegistered;

Dispatcher::listen(UserRegistered::class, function (UserRegistered $event) {
    Log::info("New user: {$event->email}");
});
```

### Style 2: listener class (recommended)

The class is resolved through the DI container, so constructor
dependencies are injected:

```php
<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\Mailer;

class SendWelcomeEmail
{
    public function __construct(private Mailer $mailer) {}

    public function handle(UserRegistered $event): void
    {
        $this->mailer->welcome($event->email);
    }
}

// registration:
Dispatcher::listen(UserRegistered::class, \App\Listeners\SendWelcomeEmail::class);
```

### Style 3: bulk map (e.g. config/events.php)

```php
Dispatcher::register([
    \App\Events\UserRegistered::class => [
        \App\Listeners\SendWelcomeEmail::class,
        \App\Listeners\NotifyAdmins::class,
    ],
    \App\Events\OrderShipped::class => [
        \App\Listeners\SendTrackingEmail::class,
    ],
]);
```

## Dispatching

```php
use Framework\Core\Events\Dispatcher;

Dispatcher::dispatch(new UserRegistered($user->id, $user->email));
```

Listeners run in registration order. Events nobody listens to are a
no-op.

There is also a console utility for firing events by hand:

```bash
php console event App\\Events\\UserRegistered
```

## Queued listeners — don't block the request

A listener that implements the `ShouldQueue` marker interface is pushed
to the [queue](../queue/README.md) instead of running inline:

```php
use Framework\Core\Events\ShouldQueue;

class SendWelcomeEmail implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        // runs on a queue worker, not in the HTTP request
    }
}
```

**Conditional queueing** (legacy duck-typed style, still supported) —
implement `shouldQueue(): bool` instead of the interface:

```php
class SendWelcomeEmail
{
    public function shouldQueue(): bool
    {
        return config('app.env') === 'production';   // inline in dev, queued in prod
    }

    public function handle(UserRegistered $event): void { /* ... */ }
}
```

### What queued listeners must know

The event travels to the worker as JSON and is **rebuilt from its
public properties** (the constructor is bypassed). Therefore:

- Public properties must be JSON-safe — no PDO handles, no closures,
  no resource objects. Pass IDs, re-fetch models in the listener.
- The listener itself is resolved through the container on the worker,
  so constructor DI works there too.
- A worker must be running: `php console queue:work`.

## Resetting (long-running workers / tests)

`Dispatcher::flush()` drops all listeners. `State::resetPerRequest()`
calls it automatically between requests in persistent runtimes
(Swoole/RoadRunner), so per-request `listen()` calls don't stack up.
In tests, call `Dispatcher::flush()` in `setUp()`/`tearDown()`.
