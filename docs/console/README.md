# Console

The `php console` CLI: built-in commands for scaffolding, migrations,
queues, scheduling, and servers — plus a tiny API for writing your own
commands.

## Built-in commands

```bash
php console                      # list every registered command
```

| Group | Commands |
|---|---|
| Scaffolding | `make:controller`, `make:model`, `make:middleware`, `make:migration`, `make:seeder`, `make:job`, `make:event`, `make:listener`, `make:service`, `make:class`, `make:routes`, `make:env` |
| Database | `migrate`, `migrate:rollback`, `db:seed` |
| Routing | `route:cache`, `route:clear` |
| Queue | `queue:work`, `queue:table`, `queue:failed`, `queue:retry`, `queue:flush`, `queue:serve`, `queue:ui` |
| Scheduling | `schedule:run`, `schedule:work` |
| Servers | `serve`, `websocket:serve` |
| Testing | `test` |
| Security | `jwt:generate`, `push:vapid`, `system:permissions` |
| Misc | `setup`, `event`, `help` |

Each has its own doc where the subsystem is documented (e.g.
[queue/](../queue/README.md) for the `queue:*` family).

## Arguments and options — the CLI grammar

```bash
php console queue:work emails --tries=5 --once -v
#            └ command  └ argument └ option=value └ flag (true)
```

- Positional **arguments** come first, read by index.
- `--key=value` long options; `--flag` becomes `true`.
- `-x` short flags become `true`.
- `--silent` is honored by every command's output helpers.

## Writing your own command

### 1. Create the class

```php
<?php
// app/Console/Commands/GreetCommand.php

namespace App\Console\Commands;

use Framework\Core\Console\Command;

class GreetCommand extends Command
{
    protected string $signature = 'greet';
    protected string $description = 'Greet someone by name';

    public function execute(): void
    {
        $name = $this->argument(0, 'world');       // positional, with default
        $shout = (bool) $this->option('shout', false);

        $message = "Hello, {$name}!";
        if ($shout) {
            $message = strtoupper($message);
        }

        $this->success($message);
    }
}
```

> `execute()` takes **no parameters** and returns `void` — arguments
> arrive via `$this->argument($i)` / `$this->option($key)`, parsed from
> argv by the base class.

### 2. Register it

`app/Console/Kernel.php` returns a `name => class` map merged into the
built-in registry:

```php
<?php
// app/Console/Kernel.php

return [
    'greet' => \App\Console\Commands\GreetCommand::class,
];
```

```bash
php console greet Ada --shout
# HELLO, ADA!
```

### Output helpers

```php
$this->line('plain text');
$this->info('cyan');       $this->success('green');
$this->warn('yellow');     $this->error('red, prefixed "Error:"');
// all become no-ops when --silent is passed
```

## Invoking commands from code

Commands are plain classes constructed with an argv-shaped array
(`[script, commandName, ...args]`):

```php
$cmd = new \App\Console\Commands\GreetCommand(['console', 'greet', 'Ada', '--shout']);
$cmd->execute();
```

The [scheduler](../scheduling/README.md) does exactly this via
`$schedule->command(GreetCommand::class, ['Ada', '--shout'])`.

## Testing commands

The registry itself is covered by `tests/Unit/ConsoleCommandsTest.php`,
which asserts every registered name maps to a real `Command` subclass —
if you register a class that doesn't exist (or has an incompatible
`execute()` signature), the suite fails. Test your own command logic by
constructing it with argv and calling `execute()` (pass `--silent` to
mute output), or extract the interesting logic into a service class and
unit-test that.
