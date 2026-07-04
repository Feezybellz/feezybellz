# Exceptions & Error Handling

Uncaught exceptions (and fatals) are captured by the framework's
Handler: an interactive debugger in development, safe error pages in
production, JSON for APIs.

## Debug vs production

```env
APP_DEBUG=true    # development ONLY
```

- **`true`** — interactive HTML debugger: failing line, stack trace,
  request variables.
- **`false`** — clean "500 Server Error" page; no file paths, no
  credentials, nothing sensitive leaks. Details still go to the log.

Never ship `APP_DEBUG=true` — it exposes source and request internals
to anyone who can trigger an error.

## JSON error responses

Exceptions during API requests are rendered as structured JSON instead
of HTML when either:

- the request sends `Accept: application/json`, or
- `APP_FORCE_JSON=true` is set (API-only apps).

## Custom error pages

Drop views into `views/errors/` and the Handler uses them
automatically in production:

```
views/errors/404.php    # Not Found
views/errors/403.php    # Forbidden
views/errors/500.php    # Server Error
```

They're normal [views](../view/README.md) — full PHP, helpers
available.

## Throwing useful errors from your code

```php
// Validation failures render as 422 automatically:
throw new \Framework\Core\Exceptions\ValidationException($validator->errors());

// For everything else, throw normally and let the Handler render it —
// or catch at the boundary and shape your own response:
try {
    $gateway->charge($amount);
} catch (GatewayTimeout $e) {
    Log::error('charge timeout', ['order' => $orderId]);
    return Response::json(['error' => 'Payment service unavailable'], 503);
}
```

## Testing error behavior

Use the test framework's exception assertions
([testing/](../testing/README.md)):

```php
$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('MAC is invalid');
Encryption::decrypt($tampered);
```
