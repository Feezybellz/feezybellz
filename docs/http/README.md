# HTTP — Request & Response

Every request is wrapped in a `Request` object; handlers return a
`Response` (or something the router converts into one).

## The Request object

Inject it into any controller method or route closure:

```php
use Framework\Core\Http\Request;

public function store(Request $request) { ... }
```

### Reading input — pick the right scope

```php
$request->input('name');    // query string + POST + JSON body (most common)
$request->query('page');    // ?page=2 only
$request->post('title');    // form POST body only
$request->route('id');      // URL parameter from /users/{id}
$request->param('id');      // alias of route()
$request->all();            // merged query + POST + JSON as array
$request->has('email');     // bool
```

JSON request bodies are parsed automatically — `input()`/`all()` see
JSON keys exactly like form fields.

### Network / headers

```php
$request->ip();               // client IP (trusted-proxy aware)
$request->subdomain();        // "acme" from acme.myapp.com, or null
$request->header('User-Agent');
$request->getBearerToken();   // from "Authorization: Bearer <token>"
$request->isMethod('POST');
$request->method();           // 'GET', 'POST', ...
$request->uri();
```

### Validation

```php
$clean = $request->validate(['email' => 'required|email']);
// throws ValidationException (rendered as 422) on failure
```

See [validation/](../validation/README.md).

### File uploads

```php
if ($request->hasFile('avatar')) {
    $file = $request->file('avatar');       // UploadedFile
    $file->move('uploads', 'avatar42.jpg'); // into public/uploads
}
```

## Responses — three styles

### Style 1: return plain values (simplest)

The router wraps what your handler returns:

```php
Router::get('/hello', fn () => 'Hello');            // 200 text
Router::get('/api/user', fn () => ['id' => 1]);     // 200 JSON automatically
```

### Style 2: the fluent Response class

Every static call creates a **fresh instance** (no shared state), and
every method chains:

```php
use Framework\Core\Http\Response;

return Response::json(['created' => true], 201);

return Response::html('<h1>Hi</h1>');

return Response::view('dashboard', ['user' => $user]);

return Response::setStatusCode(404)->json(['error' => 'not found']);

return Response::json($data)->header('X-Request-Id', $rid);

return Response::redirect('/login');            // 302 + Location
return Response::redirect('/gone', 301);
```

### Style 3: helpers

```php
return redirect('/dashboard');    // sends a 302 and exits
```

> There is **no global `response()` helper** — use the `Response` class
> directly.

### File streaming

```php
Response::file(public_path('assets/app.js'), 'application/javascript');
// streams with ETag/Cache-Control; handles 304 Not Modified
```

## Middleware

Middleware are classes with a `handle($request, $next)` method:

```php
<?php

namespace App\Middleware;

class EnsureAdmin
{
    public function handle($request, $next)
    {
        if (!Auth::user()?->isAdmin()) {
            return Response::json(['error' => 'forbidden'], 403);
        }
        return $next($request);   // continue down the pipeline
    }
}
```

Attach by class, by alias (see [security/](../security/README.md) for
the built-in aliases), inline on a route, or on a group:

```php
Router::get('/admin', [AdminController::class, 'index'])
    ->middleware(\App\Middleware\EnsureAdmin::class);

Router::group(['middleware' => ['auth', 'throttle:60,1']], function () {
    // ...
});
```

## Multi-domain support

`APP_DOMAIN` accepts a comma-separated list, so local + production +
alias domains all route identically:

```env
APP_DOMAIN=localhost,myapp.com,staging.myapp.com
```

`http://acme.localhost/` and `https://acme.myapp.com/` both resolve the
subdomain `acme` — no routing changes between environments. See
[routing/](../routing/README.md) for subdomain routing itself.
