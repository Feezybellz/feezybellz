<?php

namespace Framework\Core\Scheduling;

class Event
{
    protected $callback;
    protected $expression = '* * * * *'; // Default: every minute
    protected $name = null; // New property for the identifier
    protected $id = null; // New property for the identifier
    protected $description = '';

    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    /**
     * Set the raw cron expression.
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Set a unique id for the task
     */
    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the task id
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set a unique name for the task
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the task name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    public function everyMinute(): self { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): self { return $this->cron('*/5 * * * *'); }
    public function hourly(): self { return $this->cron('0 * * * *'); }
    public function daily(): self { return $this->cron('0 0 * * *'); }
    public function dailyAt(string $time): self 
    {
        // $time format: "14:30"
        [$hour, $minute] = explode(':', $time);
        return $this->cron("{$minute} {$hour} * * *");
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?: 'Unnamed Task';
    }

    /**
     * Determine if the event is due to run based on the current time.
     */
    public function isDue(): bool
    {
        $currentDate = getdate();
        $parts = explode(' ', $this->expression);

        if (count($parts) !== 5) {
            return false; // Invalid cron expression
        }

        return $this->match($parts[0], $currentDate['minutes'])
            && $this->match($parts[1], $currentDate['hours'])
            && $this->match($parts[2], $currentDate['mday'])
            && $this->match($parts[3], $currentDate['mon'])
            && $this->match($parts[4], $currentDate['wday']);
    }

    /**
     * Match a cron expression part against the current time value.
     */
    protected function match(string $expression, int $value): bool
    {
        if ($expression === '*') {
            return true;
        }

        // Handle step values like */5
        if (strpos($expression, '*/') === 0) {
            $step = (int) substr($expression, 2);
            return $value % $step === 0;
        }

        // Handle exact matches
        return (int) $expression === $value;
    }

    /**
     * Execute the task.
     */
    public function run()
    {
        return call_user_func($this->callback);
    }
}
