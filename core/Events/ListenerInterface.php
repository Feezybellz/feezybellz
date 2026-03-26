<?php

namespace Framework\Core\Events;

interface ListenerInterface
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void;
}