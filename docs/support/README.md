# Support — Helpers & Utility Classes

Global helper functions (no `use` needed) plus the `Str`, `Collection`,
and `Date` utility classes.

## Global helpers

### Configuration & environment

```php
env('STRIPE_KEY', 'fallback');        // .env value (shell env wins over file)
config('app.name');                   // config/app.php → 'name'
config('db.connections.default');     // dot notation, any depth
```

Prefer `config()` in application code; reserve `env()` for inside
`config/*.php` files — config is cacheable and mockable, raw env isn't.

### Paths

```php
base_path('routes/web.php');    // absolute path from project root
app_path('Services');           // app/ subpath
storage_path('uploads/a.jpg');  // storage/ subpath
public_path('assets/app.css');  // public/ subpath
```

### HTTP & views

```php
view('dashboard', ['user' => $user]);   // render a template → string
redirect('/login');                      // send 302 and exit
route('users.show', ['id' => 42]);       // URL for a named route
session('key', 'default');               // session read
csrf_token();  csrf_field();             // CSRF helpers (see security/)
e($userInput);                           // HTML-escape for output
```

> There is no `response()` helper — use the `Response` class
> ([http/](../http/README.md)).

### Debugging

```php
dd($value, $another);   // pretty-dump and die (masks sensitive keys);
                        // refuses to run in production unless forced
dump_safe($value);      // dump without dying
db();                   // quick handle on the default DB connection
```

## `Str` — string utilities

```php
use Framework\Core\Support\Str;

Str::snake('userName');          // user_name
Str::camel('user_name');         // userName
Str::slug('Hello World! 2026');  // hello-world-2026
Str::uuid();                     // RFC-4122 v4 UUID
Str::random(32);                 // random alphanumeric string
```

## `Collection` — fluent array pipeline

Wraps an array with chainable transforms. Implements `ArrayAccess`,
`Countable`, `IteratorAggregate`, and `JsonSerializable`, so it drops
into `foreach`, `count()`, and `json_encode()` unchanged.

```php
use Framework\Core\Support\Collection;

$names = Collection::make($users)
    ->filter(fn ($u) => $u['active'])
    ->map(fn ($u) => $u['name'])
    ->reverse()
    ->all();                          // back to a plain array

$total  = Collection::make($items)->reduce(fn ($sum, $i) => $sum + $i['price'], 0);
$emails = Collection::make($users)->pluck('email', 'id');   // id => email
$first  = Collection::make($rows)->first(fn ($r) => $r['id'] > 10, $default);

$c = new Collection(['a']);
$c->push('b')->merge(['c', 'd'])->count();   // 4
$c->isEmpty();  $c->isNotEmpty();  $c->last();  $c->toArray();
```

## `Date` — fluent date arithmetic

```php
use Framework\Core\Support\Date;

$now = Date::now();                       // UTC by default
$now = Date::now('Africa/Lagos');

$due = Date::now()->addDays(14);
$ago = Date::now()->subMonths(3);

$days = $due->diffInDays($ago);           // int
$due->isPast();                           // bool
$due->toSql();                            // 'Y-m-d H:i:s' for DB writes
```
