# Auth

Authentication guards that are deliberately opinion-free about "user":
the framework never assumes a users table or credential shape. A guard
answers one question — *how does this request tell me who's here?* —
and you decide everything else.

## Guards & configuration

`config/auth.php` defines named guards; drivers available:

| Driver | Behavior |
|---|---|
| `session` | cookie-backed; session id rotates on login (fixation defence) |
| `jwt` | stateless bearer token; `login()` returns the token string |
| `callable` | you supply resolver/login/logout closures — API keys, basic auth, HMAC, anything |

```php
'default' => env('AUTH_DEFAULT_GUARD', 'web'),
'login_url' => env('AUTH_LOGIN_URL', '/login'),   // redirect target for HTML; APIs get 401

'guards' => [
    'web' => ['driver' => 'session'],
    'api' => ['driver' => 'jwt'],
],
```

## Core usage

```php
use Framework\Core\Auth\Auth;

Auth::check();          // bool — is anyone authenticated?
Auth::user();           // whatever payload was stashed at login (or null)
Auth::id();             // payload id if present
Auth::login($payload);  // stash payload; JWT guard returns the token
Auth::logout();

Auth::guard('api')->check();   // address a specific guard explicitly
```

The **payload is yours** — an array, a model, an id. Whatever you pass
to `login()` is what `user()` gives back.

## Login flows — two styles

### Style 1: `Auth::attempt()` (recommended)

Pass a verifier that returns the payload on success, `null`/`false` on
failure — `attempt` logs in and returns the guard's result:

```php
public function login(Request $request)
{
    $result = Auth::attempt(function () use ($request) {
        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user && Hash::check($request->input('password'), $user->password)) {
            return ['id' => $user->id, 'email' => $user->email];
        }
        return null;   // wrong credentials
    });

    if ($result === false) {
        return Response::json(['error' => 'Invalid credentials'], 401);
    }

    // session guard: $result is null, cookie is set
    // jwt guard:     $result is the token string
    return Response::json(['token' => $result]);
}
```

### Style 2: manual

```php
$user = /* verify however you like */;
if ($user) {
    $token = Auth::login(['id' => $user->id]);   // token for JWT, null for session
}
```

## Protecting routes

```php
Router::group(['middleware' => ['auth']], function () {
    Router::get('/dashboard', [DashboardController::class, 'index']);
});
```

Unauthenticated HTML requests redirect to `login_url`; JSON requests
get a 401.

## JWT specifics

```bash
php console jwt:generate    # writes JWT_SECRET to .env
```

Clients send `Authorization: Bearer <token>`; the `jwt` guard verifies
signature + expiry (with clock-skew leeway) and exposes the payload via
`Auth::user()`. Config (`config/jwt.php`) supports issuer/audience
claims and **key rotation** — old secrets keep verifying until their
tokens expire.

Low-level access when you need it:

```php
use Framework\Core\Auth\JWT;

$token   = JWT::encode(['sub' => 42], expiration: 3600);
$payload = JWT::decode($token);      // array, or null on ANY failure (never throws)
JWT::verify($token);                 // bool
JWT::refresh($token);                // re-mint with fresh iat/exp, or null
```

## Custom guard driver

```php
Auth::manager()->extend('apikey', function () {
    return new \Framework\Core\Auth\CallableGuard(
        resolve: function ($request) {
            $key = $request->header('X-Api-Key');
            return $key ? ApiKey::where('key', '=', hash('sha256', $key))->first() : null;
        }
    );
});
// then in config/auth.php: 'guards' => ['machine' => ['driver' => 'apikey']]
```

## Stateless helpers (no DB table needed)

```php
// "remember me" token, signed with APP_KEY:
$token = Auth::rememberSigned(['id' => $user->id], days: 30);
// (you set the cookie yourself)
$payload = Auth::verifyRemember($_COOKIE['remember'] ?? '');   // or null

// password-reset / magic-link:
$link  = Auth::signedResetLink(['id' => $user->id], ttl: 3600);
$who   = Auth::verifySignedLink($request->query('token'));     // or null
```

Both build on [`SignedToken`](../security/README.md#signed-tokens-stateless-expiring).
