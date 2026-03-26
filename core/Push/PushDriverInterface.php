<?php

namespace Framework\Core\Push;

interface PushDriverInterface
{
    /**
     * Send a push notification to one or multiple device tokens.
     */
    public function send($tokens, string $title, string $body, array $data = []): bool;
}