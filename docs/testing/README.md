# Testing

The framework ships its own zero-dependency test framework — no PHPUnit.
Tests are plain PHP classes; assertions throw on failure; a runner
discovers and executes them with proper CI exit codes.

## Running tests

```bash
php console test                          # everything under tests/
php console test tests/Unit               # one directory
php console test tests/Unit/JwtTest.php   # one file
php console test --filter=Encryption      # only Class::method matching a string
php console test --stop-on-failure        # halt on first failure/error
php console test --no-color               # plain output (CI logs)
composer test                              # alias for `php console test`
```

Exit code is `0` when everything passes, `1` otherwise — safe to gate
CI or git hooks on.

## Writing a unit test

Create `tests/Unit/MyTest.php`. The runner picks up any concrete class
extending `TestCase` in a file ending `Test.php`; test methods are
`public` and start with `test`.

```php
<?php

namespace Tests\Unit;

use Framework\Core\Testing\TestCase;

class MyTest extends TestCase
{
    public function test_addition_works(): void
    {
        $this->assertSame(4, 2 + 2);
    }
}
```

### Lifecycle

`setUp()` runs before each test method, `tearDown()` after (even on
failure). A **fresh instance is created per test method**, so instance
state never leaks between tests.

```php
protected function setUp(): void
{
    parent::setUp();   // boots the Application — omit the parent call
                       // (empty override) for pure-logic tests that
                       // don't need config/env; they run much faster
}

protected function tearDown(): void
{
    // cleanup — runs even if the test failed
}
```

### Assertions

All assertions throw `AssertionFailedException` on failure and count
toward the test's assertion total:

```php
$this->assertTrue($x);            $this->assertFalse($x);
$this->assertNull($x);            $this->assertNotNull($x);
$this->assertEquals($a, $b);      // loose ==
$this->assertSame($a, $b);        // strict ===
$this->assertEmpty($x);           $this->assertNotEmpty($x);
$this->assertCount(3, $array);
$this->assertContains($needle, $arrayOrString);
$this->assertStringContainsString('foo', $haystack);
$this->assertArrayHasKey('k', $array);
$this->assertInstanceOf(User::class, $x);
$this->assertMatchesRegExp('/^v\d+$/', $s);
$this->assertGreaterThan(5, $x);  $this->assertLessThan(5, $x);
$this->assertJson($string);
$this->fail('reason');            // unconditional failure
$this->markTestSkipped('why');    // reported as skipped, never a failure
```

### Expecting exceptions

```php
public function test_invalid_payload_is_rejected(): void
{
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('MAC is invalid');   // substring match

    Encryption::decrypt($tampered);
}
```

The test fails if the exception is *not* thrown, and any non-matching
exception is reported as an error.

## HTTP / feature tests — two styles

### Style 1: test your app's real routes

Extend `HttpTestCase` and just make requests — the application's actual
`routes/` files are loaded:

```php
<?php

namespace Tests\Feature;

use Framework\Core\Testing\HttpTestCase;

class HealthTest extends HttpTestCase
{
    public function test_health_endpoint(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['status' => 'up']);
    }
}
```

### Style 2: isolated test-local routes

Override `routes()` — the app's route files are then **not** loaded, so
the test is fully self-contained. Good for testing middleware, response
helpers, or framework behavior itself:

```php
class RedirectTest extends HttpTestCase
{
    protected function routes(): void
    {
        Router::get('/go', fn () => Response::redirect('/target'));
        Router::post('/echo', fn () => Response::json(['ok' => true], 201));
    }

    public function test_redirect(): void
    {
        $this->get('/go')->assertRedirect('/target');
    }

    public function test_created(): void
    {
        $this->post('/echo', ['name' => 'Ada'])->assertStatus(201);
    }
}
```

### Verbs and the fluent response

```php
$this->get($uri, $headers);
$this->post($uri, $data, $headers);
$this->put($uri, $data);   $this->patch($uri, $data);
$this->delete($uri);       $this->postJson($uri, $data);
```

Every verb returns a `TestResponse`:

```php
$response
    ->assertStatus(200) ->assertOk() ->assertNotFound()
    ->assertRedirect('/login')
    ->assertHeader('X-Frame-Options', 'DENY')
    ->assertSee('Welcome') ->assertDontSee('Error')
    ->assertJson(['user' => 'ada'])      // subset match
    ->assertExactJson([...]);            // exact match

$response->status();     // int
$response->content();    // raw body
$response->json();       // decoded array
$response->json('key');  // one key
```

The simulated host defaults to your configured `APP_DOMAIN` apex so
global routes match; pass a `Host` header to override.

## Fixtures and shared helpers

Reusable non-test classes live in `tests/Fixtures/` (PSR-4:
`Tests\Fixtures\...`, mapped via `autoload-dev`). Examples that ship
with the framework:

- `WithAppKey` — trait that injects a deterministic `APP_KEY` and
  reloads config, for testing key-signing subsystems.
- `ArrayQueueDriver` — full in-memory queue driver for worker tests.
- `FlakyJob`, `RecordingListener`, `MarkerCommand` — behavior probes.

```php
use Tests\WithAppKey;

class SignedTokenTest extends TestCase
{
    use WithAppKey;

    protected function setUp(): void
    {
        $this->bootWithAppKey();   // app boots with a test APP_KEY
    }
}
```

## Runner internals worth knowing

- Discovery is by **class**, not by path→FQCN guessing: after including
  each `*Test.php`, the runner diffs `get_declared_classes()` and keeps
  concrete `TestCase` subclasses — your namespace layout can't break it.
- Failures (assertion), errors (unexpected exception), and skips are
  reported separately, each with the `file:line` of the offending test.
- A test that makes zero assertions still passes but contributes 0 to
  the assertion count — watch for those in review.
