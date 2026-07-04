# Scheduling

Cron-style task scheduling in PHP: define tasks fluently, then let a
single system cron entry (or the long-running `schedule:work` process)
fire whatever is due.

## Defining the schedule

`schedule:run` auto-discovers schedule classes from
**`app/Console/Schedule/`**: every class there with a
`build(Scheduler $scheduler)` method contributes tasks.

```php
<?php
// app/Console/Schedule/MaintenanceSchedule.php

namespace App\Console\Schedule;

use Framework\Core\Scheduling\Scheduler;
use Framework\Core\Database\DB;

class MaintenanceSchedule
{
    public function build(Scheduler $schedule): void
    {
        // ── Style 1: closures
        $schedule->call(function () {
            DB::table('sessions')->where('expires_at', '<', time())->delete();
        })->hourly()->name('purge-sessions');

        // ── Style 2: console commands (optional CLI-style args)
        $schedule->command(
            \Framework\Core\Console\Commands\QueueWorkCommand::class,
            ['emails', '--once']
        )->everyFiveMinutes();
    }
}
```

## Frequency — fluent helpers or raw cron

```php
$schedule->call($fn)->everyMinute();          // * * * * *
$schedule->call($fn)->everyFiveMinutes();     // */5 * * * *
$schedule->call($fn)->hourly();               // 0 * * * *
$schedule->call($fn)->daily();                // 0 0 * * *
$schedule->call($fn)->dailyAt('14:30');       // 30 14 * * *
$schedule->call($fn)->cron('15 3 * * 1');     // raw cron expression
```

Supported cron syntax per field: `*`, exact numbers, and `*/n` steps.

## Overlap protection

For tasks that might outlive their interval, `withoutOverlapping()`
takes a file lock so a second run exits immediately while the first is
still going:

```php
$schedule->call(fn () => rebuildSearchIndex())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->id('search-rebuild');   // stable id => stable lock file
```

Give overlapping-protected tasks a stable `->id()` or `->name()` —
otherwise the lock is keyed to the object instance and won't survive
process restarts.

## Running the scheduler — two modes

### Mode 1: system cron (recommended for production)

One crontab line fires the framework every minute; the framework runs
whatever is due:

```cron
* * * * * cd /var/www/app && php console schedule:run >> /dev/null 2>&1
```

### Mode 2: long-running worker (no crontab access)

```bash
php console schedule:work    # loops forever, ticking at each minute boundary
```

Useful in containers where adding a crontab is awkward; supervise it
like any daemon.

### Running one task by hand

```bash
php console schedule:run --name=purge-sessions   # by ->name()
php console schedule:run --id=search-rebuild     # by ->id()
```

Both bypass the "is it due?" check — handy for testing a task
immediately.

## Inspecting programmatically

```php
$schedule->getEvents();   // all registered events
$schedule->dueEvents();   // the subset due right now
$event->isDue();          // bool
$event->run();            // execute (respects withoutOverlapping)
```

## Notes & limits

- Cron matching uses the **server's timezone** (`date.timezone` in
  php.ini). Per-event timezones are not yet supported.
- A throwing task does not stop other due tasks in the same tick, but
  there is no `onFailure()` hook yet — wrap risky callbacks in
  try/catch + `Log::error` yourself.
