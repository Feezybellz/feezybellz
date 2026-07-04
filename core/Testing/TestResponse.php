<?php

namespace Framework\Core\Testing;

use Framework\Core\Http\Response;

/**
 * A thin, fluent wrapper around a {@see Response} for expressive HTTP
 * assertions:
 *
 *   $this->get('/health')
 *        ->assertOk()
 *        ->assertJson(['status' => 'up'])
 *        ->assertHeader('Content-Type', 'application/json');
 *
 * Every assertion delegates to the owning {@see TestCase} so failures
 * throw the same {@see AssertionFailedException} and the test's assertion
 * counter stays accurate. Assertions return `$this` for chaining.
 */
class TestResponse
{
    private Response $response;
    private TestCase $test;

    public function __construct(Response $response, TestCase $test)
    {
        $this->response = $response;
        $this->test = $test;
    }

    // ── Accessors ───────────────────────────────────────────────────────

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function content(): string
    {
        return $this->response->getContent();
    }

    public function header(string $key): ?string
    {
        return $this->response->getHeader($key);
    }

    /**
     * The response body decoded as a JSON array (or null if not JSON).
     */
    public function json(?string $key = null)
    {
        $decoded = json_decode($this->content(), true);
        if ($key === null) {
            return $decoded;
        }
        return is_array($decoded) ? ($decoded[$key] ?? null) : null;
    }

    /** Escape hatch to the underlying Response. */
    public function baseResponse(): Response
    {
        return $this->response;
    }

    // ── Assertions ──────────────────────────────────────────────────────

    public function assertStatus(int $expected): self
    {
        $this->test->assertSame(
            $expected,
            $this->status(),
            "Expected HTTP status {$expected}, got {$this->status()}."
        );
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertRedirect(?string $uri = null): self
    {
        $status = $this->status();
        $this->test->assertTrue(
            in_array($status, [301, 302, 303, 307, 308], true),
            "Expected a redirect status, got {$status}."
        );
        if ($uri !== null) {
            $this->test->assertSame(
                $uri,
                $this->header('Location'),
                "Expected redirect to '{$uri}', got '" . ($this->header('Location') ?? 'null') . "'."
            );
        }
        return $this;
    }

    public function assertHeader(string $key, ?string $value = null): self
    {
        $actual = $this->header($key);
        $this->test->assertNotNull($actual, "Expected header '{$key}' to be present.");
        if ($value !== null) {
            $this->test->assertSame(
                $value,
                $actual,
                "Expected header '{$key}' to equal '{$value}', got '" . ($actual ?? 'null') . "'."
            );
        }
        return $this;
    }

    public function assertSee(string $needle): self
    {
        $this->test->assertStringContainsString(
            $needle,
            $this->content(),
            "Expected response body to contain '{$needle}'."
        );
        return $this;
    }

    public function assertDontSee(string $needle): self
    {
        $this->test->assertNotContains(
            true,
            [str_contains($this->content(), $needle)],
            "Expected response body NOT to contain '{$needle}'."
        );
        return $this;
    }

    /**
     * Assert the response is JSON containing (at least) the given
     * key/value pairs. Subset match, not exact.
     */
    public function assertJson(array $expected): self
    {
        $actual = $this->json();
        $this->test->assertTrue(
            is_array($actual),
            'Expected response body to be a JSON object/array.'
        );
        foreach ($expected as $key => $value) {
            $this->test->assertTrue(
                is_array($actual) && array_key_exists($key, $actual),
                "Expected JSON to contain key '{$key}'."
            );
            $this->test->assertSame(
                $value,
                $actual[$key] ?? null,
                "Expected JSON['{$key}'] to equal " . json_encode($value)
                    . ', got ' . json_encode($actual[$key] ?? null) . '.'
            );
        }
        return $this;
    }

    /**
     * Assert the decoded JSON body equals exactly the given array.
     */
    public function assertExactJson(array $expected): self
    {
        $this->test->assertEquals(
            $expected,
            $this->json(),
            'Response JSON did not match exactly.'
        );
        return $this;
    }
}
