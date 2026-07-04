# Routing

Maps URLs to closures or controllers. Supports parameters with regex
constraints, wildcards, groups, middleware, subdomain routing, and a
production route cache.

Routes live in `routes/` (e.g. `routes/web.php`, `routes/api.php`) —
every PHP file in the directory is loaded.

## Basics — two handler styles

```php
use Framework\Core\Routing\Router;
use App\Controllers\UserController;

// Style 1: closure
Router::get('/', function () {
    return 'Welcome Home!';
});

// Style 2: controller
Router::get('/users', [UserController::class, 'index']);
```

All verbs: `Router::get/post/put/patch/delete/options/any`.

## Route parameters

```php
Router::get('/users/{id}', function (Request $request, $id) {
    // $id injected by name — the DI engine matches parameter names
    return "Showing user: {$id}";
});

// or read it off the request:
$id = $request->route('id');
```

**Regex constraints** — inline after a colon:

```php
Router::get('/users/{id:[0-9]+}', 'UserController@show');   // numeric only
```

**Catch-all wildcard** — SPAs, asset passthrough:

```php
Router::get('/app/*', fn () => view('spa'));   // matches /app/anything/here
```

Literal routes win over parameterized ones, which win over wildcards —
registration order doesn't matter.

## Groups

Share a prefix, middleware, or subdomain:

```php
Router::group(['prefix' => '/api/v1', 'middleware' => ['throttle:60,1', 'waf']], function () {
    Router::get('/posts', [ApiController::class, 'index']);
    Router::post('/posts', [ApiController::class, 'store']);
});
```

## Subdomain routing

Driven by `APP_DOMAIN` in `.env` (comma-separated list supported —
localhost and production behave identically):

```php
// Static: api.myapp.com
Router::group(['subdomain' => 'api'], function () {
    Router::get('/users', fn () => 'API');
});

// Wildcard (multi-tenancy): {tenant}.myapp.com
Router::group(['subdomain' => '{tenant}'], function () {
    // subdomain AND route params are both injected by name
    Router::get('/users/{id}', function (Request $request, $tenant, $id) {
        return "Tenant {$tenant}, user {$id}";
    });
});
```

When `APP_DOMAIN` is set, **global routes only serve the apex/www
host** — a wildcard subdomain can't accidentally reach routes meant for
the main site ("subdomain bleeding" protection). See
[database/tenancy.md](../database/tenancy.md) for switching databases
per tenant.

## Rate limiting

```php
Router::middleware('throttle:60,1', ...);   // 60 requests per 1 minute, per IP
```

Programmatic access:

```php
use Framework\Core\Routing\RateLimiter;

RateLimiter::hit('login:' . $request->ip());   // returns hit count
RateLimiter::clear('login:' . $request->ip());
```

## Route caching (production)

Compiling the route table once removes per-request `require` overhead:

```bash
php console route:cache    # writes bootstrap/cache/routes.php
php console route:clear    # back to loading routes/ per request
```

Run `route:cache` in your deploy script, **after** the code is in
place. Closures can't be cached — routes using closure handlers or
object middleware make the command refuse; convert them to
`[Controller::class, 'method']` form first. The cache file is
gitignored by design.

## Named routes / URL generation

```php
Router::get('/users/{id}', [UserController::class, 'show'])->name('users.show');

$url = route('users.show', ['id' => 42]);   // "/users/42"
```
