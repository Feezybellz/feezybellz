<?php

namespace Framework\Core\Support;

use DateTime;
use DateTimeZone;

class Date extends DateTime
{
    public static function now(string $timezone = 'UTC'): self
    {
        return new self('now', new DateTimeZone($timezone));
    }

    public function addDays(int $days): self
    {
        $this->modify("+{$days} days");
        return $this;
    }

    public function subMonths(int $months): self
    {
        $this->modify("-{$months} months");
        return $this;
    }

    public function diffInDays(Date $otherDate): int
    {
        return (int) $this->diff($otherDate)->format('%a');
    }

    public function toSql(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
    
    public function isPast(): bool
    {
        return $this < new self();
    }
}