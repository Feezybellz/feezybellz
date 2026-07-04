# Logging

PSR-3-style logger writing to daily-rotated files in `storage/logs/`.

## Writing logs

```php
use Framework\Core\Logging\Log;

Log::debug('Stripe payload', ['amount' => 500, 'currency' => 'usd']);
Log::info('User 123 logged in');
Log::notice('Cache rebuilt');
Log::warning('Disk space low');
Log::error('Database connection failed', ['host' => $host]);
Log::critical('Payment double-charged', ['order' => $id]);
Log::alert('Certificate expires tomorrow');
Log::emergency('Site down');
```

The second argument is structured context — serialized into the line,
so log entries stay grep-able *and* machine-parseable.

## Level filtering

```env
LOG_LEVEL=warning
```

With `warning`, calls below it (`debug`, `info`, `notice`) become
no-ops — keep production logs small without touching call sites.
Severity order: `debug < info < notice < warning < error < critical
< alert < emergency`.

## Ambient context

Attach context once; every subsequent log line in the request includes
it. The Kernel already does this with a per-request `request_id`, so
all lines from one request are correlatable:

```php
Log::withContext(['tenant' => $tenantId]);   // merge into ambient context
Log::setContext([...]);                      // replace entirely
Log::getContext();
Log::clearContext();                         // called per-request by State reset
```

```php
Log::info('order created');
// [2026-07-04 ...] INFO: order created {"request_id":"...","tenant":"acme"}
```

## Rotation

Files rotate daily (`framework-2026-07-04.log`) automatically — no
cron needed, logs never grow unbounded.

## Testing / swapping the sink

```php
Log::setLogger($myLoggerInstance);   // capture logs in tests
Log::reset();                        // back to the configured default
```
