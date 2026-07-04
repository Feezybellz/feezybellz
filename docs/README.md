# Framework Documentation

Native PHP framework with zero runtime dependencies. Each folder below
documents one subsystem: what it does, how to use it (including the
different usage styles where a tool supports more than one), and working
example code.

## Getting started

```bash
git clone <repo> myapp && cd myapp
composer install
cp .env.example .env
php console make:env --generate-key --force   # sets APP_KEY
php console serve                              # dev server
```

## Subsystems

| Folder | Covers |
|---|---|
| [auth/](auth/README.md) | Session & JWT guards, `Auth` facade, signed tokens |
| [cache/](cache/README.md) | `Cache` facade and drivers |
| [console/](console/README.md) | CLI commands, writing your own commands |
| [container/](container/README.md) | Dependency injection container |
| [database/](database/README.md) | `DB`, query builder, models (ORM), migrations, seeding |
| [database/tenancy.md](database/tenancy.md) | Multi-tenant database switching |
| [events/](events/README.md) | Event dispatcher, listeners, queued listeners |
| [exceptions/](exceptions/README.md) | Error handling, custom exception rendering |
| [http/](http/README.md) | Request, Response, middleware |
| [logging/](logging/README.md) | `Log` facade, channels, context |
| [mail/](mail/README.md) | `Mail` facade, mailables, drivers |
| [push/](push/README.md) | Web-push notifications (VAPID) |
| [queue/](queue/README.md) | Background jobs: drivers, worker, retries, failed jobs |
| [routing/](routing/README.md) | Router, route parameters, groups, rate limiting |
| [scheduling/](scheduling/README.md) | Cron-style task scheduler |
| [security/](security/README.md) | Encryption, hashing, WAF, CSRF, CORS, security headers |
| [storage/](storage/README.md) | Filesystem abstraction, disks, uploads |
| [support/](support/README.md) | `Str`, `Collection`, `Date`, helpers |
| [testing/](testing/README.md) | The built-in test framework and runner |
| [validation/](validation/README.md) | Validator rules and usage styles |
| [view/](view/README.md) | Templates and rendering |
| [websocket/](websocket/README.md) | WebSocket server, PHP & JS clients, broadcasting |

## Conventions used in these docs

- Code samples assume the framework is bootstrapped (web request or
  `php console`); standalone scripts must `require 'vendor/autoload.php'`
  and construct `new Application(__DIR__)` first.
- `config('a.b')` refers to the `b` key in `config/a.php`. Most config
  reads environment variables via `env()` — prefer editing `.env`.
- Where a tool supports **multiple usage styles** (static facade vs
  instance, fluent vs array, etc.) the doc shows each style side by side
  and says when to prefer which.
