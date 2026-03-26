<?php

namespace Framework\Core\Mail;

interface MailDriverInterface
{
    /**
     * Send an email with optional attachments and content-type flag.
     */
    public function send($to, string $subject, string $body, array $headers = [], array $attachments = [], bool $isHtml = true): bool;
}