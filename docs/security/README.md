# Security

Encryption, password hashing, the WAF, CSRF, CORS, and security
headers. Each ships as a middleware alias and/or a facade-style class.

## Middleware aliases (core/Http/Kernel.php)

| Alias | Middleware | Purpose |
|---|---|---|
| `auth` | Authenticate | require a logged-in user — see [auth/](../auth/README.md) |
| `csrf` | CsrfMiddleware | token check on state-changing requests |
| `cors` | CorsMiddleware | cross-origin response headers |
| `security` | SecurityHeadersMiddleware | X-Frame-Options, Permissions-Policy, … |
| `throttle` | ThrottleRequests | rate limiting (`throttle:60,1` = 60 req/min) |
| `waf` | WafMiddleware | request scanning (XSS/SQLi/LFI/RCE patterns) |

```php
Router::group(['prefix' => '/api', 'middleware' => ['throttle:60,1', 'waf']], function () {
    Router::post('/orders', [OrderController::class, 'store']);
});
```

## Encryption (`Framework\Core\Security\Encryption`)

Authenticated symmetric encryption (AES-256-CBC + HMAC over a
versioned envelope). Requires `APP_KEY`.

```php
use Framework\Core\Security\Encryption;

$cipher = Encryption::encrypt('secret payload');   // opaque base64 string
$plain  = Encryption::decrypt($cipher);            // throws on tampering

// deterministic keyed hash (for lookups, not passwords):
$hash = Encryption::generateHash('user@example.com');
```

- Ciphertext is non-deterministic (random IV per call).
- `decrypt()` **throws** on any tamper/version/cipher mismatch — wrap in
  try/catch when the input is user-supplied.
- Not a KMS: for short payloads (cookies, signed URLs, identifiers).

## Password hashing (`Hash`)

```php
use Framework\Core\Security\Hash;

$stored = Hash::make($plainPassword);            // bcrypt/argon per config/hashing.php

if (Hash::check($input, $stored)) {
    if (Hash::needsRehash($stored)) {            // params upgraded since?
        $user->password = Hash::make($input);    // opportunistic upgrade
        $user->save();
    }
    // login ok
}
```

Never use `Encryption` for passwords — hashing is one-way by design.

## WAF — three usage styles

### Style 1: middleware alias (typical)

```php
Router::post('/login', [AuthController::class, 'login'])->middleware('waf');
```

Detected payloads get a 403 (HTML for web, JSON for APIs) and the IP is
blocked for `block_duration` (default 1 hour).

### Style 2: middleware with parameters

```php
Router::post('/admin', 'AdminController@index')->middleware('waf:db');          // store blocks in DB
Router::post('/admin', 'AdminController@index')->middleware('waf:block=86400'); // custom duration
Router::post('/checkout', 'C@store')->middleware('waf:db,block=7200');          // combined
```

The `db` driver uses the `blocked_ips` table — run `php console migrate`
after enabling it.

### Style 3: manual scan in a controller

```php
use Framework\Core\Security\WAF;

if (!WAF::scan($request)) {                    // static or instance — both work
    Log::alert('WAF blocked: ' . WAF::getMessage());
    return Response::json(['error' => 'forbidden'], 403);
}
```

Patterns, trusted proxies, scanned content types, and the block driver
live in `config/waf.php`. Only requests with form/JSON content types
get a full body scan; others get a query/cookie scan.

## CSRF

```php
Router::post('/settings', [SettingsController::class, 'save'])->middleware('csrf');
```

In views:

```php
<form method="POST" action="/settings">
    <?= csrf_field() ?>            <!-- hidden input -->
    ...
</form>

<!-- or for JS clients: -->
<meta name="csrf-token" content="<?= csrf_token() ?>">
```

Config: `config/csrf.php`.

## CORS

```php
Router::group(['middleware' => ['cors']], function () { /* API routes */ });
```

Origins/methods/headers in `config/cors.php`. Never use a public
wildcard origin on credentialed endpoints.

## Security headers

```php
Router::middleware('security', function () {
    Router::get('/dashboard', [DashboardController::class, 'index']);
});

// strict variant: Permissions-Policy blocks camera/mic/geolocation outright
Router::get('/api/transfer', 'security:strict', $handler);
```

Header values: `config/security_headers.php`.

## Signed tokens (stateless, expiring)

For email verification / magic links / one-time actions — no DB table:

```php
use Framework\Core\Auth\SignedToken;

$token = SignedToken::issue(['user' => $user->id, 'action' => 'verify'], ttl: 3600);

// later, from the link:
$payload = SignedToken::verify($token);   // original payload, or null if
                                          // expired/tampered — never throws
```

Signed with `APP_KEY`; see [auth/](../auth/README.md) for JWT-based
API auth.
