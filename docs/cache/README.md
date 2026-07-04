# Cache

Unified key-value caching over interchangeable backends.

## Drivers & configuration

Available drivers: **`file`** (default, `storage/cache/`), **`redis`**
(php-redis extension), **`memcached`**.

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Full options in `config/cache.php`.

## Basic usage

```php
use Framework\Core\Cache\Cache;

Cache::put('key', $value, 3600);        // store for 1 hour (set() is an alias)
$value = Cache::get('key');             // null if missing
$value = Cache::get('key', 'default');  // with fallback

Cache::has('key');                      // bool
Cache::forget('key');                   // delete one
Cache::flush();                         // delete everything
```

Values are serialized for you — arrays and objects round-trip fine.

## remember() — the cache-aside pattern in one call

```php
$users = Cache::remember('active_users', 60, function () {
    return DB::table('users')->where('active', '=', 1)->get();
});
// hit  → returns cached value, closure never runs
// miss → runs closure, stores result for 60s, returns it
```

## Counters

```php
Cache::increment('page_views');          // +1, returns new value
Cache::increment('page_views', 10);      // +10
Cache::decrement('stock:42');
```

Atomic on Redis/Memcached — safe for rate counters.

## Testing / swapping the driver

```php
Cache::setDriver($myDriverInstance);   // any CacheDriverInterface
Cache::reset();                        // drop the resolved driver;
                                       // next call re-reads config
```

`Cache::reset()` is called automatically between requests in
long-running workers via `State::resetPerRequest()`.

## Choosing a driver

- **file** — zero setup; fine for single-server apps and dev.
- **redis** — fast, shared across servers, atomic counters; the default
  choice in production.
- **memcached** — comparable to Redis for pure caching; pick it if it's
  what your infrastructure already runs.
