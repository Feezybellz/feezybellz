# Service Container

Dependency-injection container with reflection-based auto-wiring. Most
of the time you never touch it — type-hint and receive.

## Auto-wiring (the zero-config path)

Type-hint dependencies in controller methods, route closures, event
listeners, or constructors — the container builds the whole graph:

```php
namespace App\Controllers;

use Framework\Core\Http\Request;
use App\Services\PaymentGateway;

class CheckoutController
{
    // Request, PaymentGateway (and ITS constructor deps) are resolved
    // automatically; $userId comes from the route parameter {userId}.
    public function process(Request $request, PaymentGateway $gateway, $userId)
    {
        $gateway->charge($request->input('amount'));
        return "Charged user {$userId}";
    }
}
```

## Binding — three styles

Register bindings in a service provider (e.g.
`App\Providers\AppServiceProvider`):

```php
use Framework\Core\Container\Container;

$container = Container::getInstance();

// ── Style 1: bind — fresh instance per resolution
$container->bind(PaymentGateway::class, function () {
    return new StripeGateway(config('payment.stripe_key'));
});

// ── Style 2: singleton — built once, shared for the request
$container->singleton(ReportCache::class, function () {
    return new ReportCache(storage_path('reports'));
});

// ── Style 3: instance — you already have the object
$container->instance(Clock::class, new FrozenClock('2026-01-01'));
```

`bind(Interface::class, Concrete::class)` also accepts a class-string
concrete — useful for interface→implementation swaps without a closure.

## Resolving manually

```php
$gateway = Container::getInstance()->make(PaymentGateway::class);

// with explicit constructor parameters:
$report = Container::getInstance()->make(Report::class, ['year' => 2026]);
```

Unbound concrete classes are still resolvable — the container reflects
the constructor and recursively builds every type-hinted dependency.

## Testing

Swap the global instance to isolate a test, restore after:

```php
$original = Container::setInstance(new Container());
// ... bind fakes, run code under test ...
Container::setInstance($original);
```

## Note on `Application`

The `Application` object extends the container, so `$app->make()`,
`$app->bind()`, `$app->singleton()` are the same operations shown
above.
