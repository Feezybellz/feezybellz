<?php

namespace Framework\Core\Mail\Drivers;

use Framework\Core\Mail\MailDriverInterface;

class MailgunDriver implements MailDriverInterface
{
    protected string $domain;
    protected string $secret;
    protected string $endpoint;

    public function __construct(array $config = [])
    {
        // Prefer explicit injected config; fall back to config('mail.mailgun.*').
        // env() is no longer read directly here — the mail config file is the
        // single source of truth. Tests inject $config to swap credentials.
        $cfg = $config + (array) (function_exists('config') ? config('mail.mailgun') : []);
        $this->domain   = (string) ($cfg['domain']   ?? '');
        $this->secret   = (string) ($cfg['secret']   ?? '');
        $this->endpoint = (string) ($cfg['endpoint'] ?? 'api.mailgun.net');
    }

    public function send($to, string $subject, string $body, array $headers = [], array $attachments = [], bool $isHtml = true): bool
    {
        if (empty($this->domain) || empty($this->secret)) {
            throw new \Exception("Mailgun API credentials (MAILGUN_DOMAIN, MAILGUN_SECRET) are missing.");
        }

        $url = "https://{$this->endpoint}/v3/{$this->domain}/messages";

        $postData = [
            'from'    => $headers['From']
                          ?? (function_exists('config') ? config('mail.from.address') : null)
                          ?? ('system@' . $this->domain),
            'to'      => is_array($to) ? implode(',', $to) : $to,
            'subject' => $subject,
        ];

        if ($isHtml) {
            $postData['html'] = $body;
        } else {
            $postData['text'] = $body;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$this->secret}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Handle attachments if needed
        if (!empty($attachments)) {
            // Convert to multipart/form-data for cURL
            foreach ($attachments as $i => $attachment) {
                if (file_exists($attachment['path'])) {
                    $postData["attachment[{$i}]"] = new \CURLFile($attachment['path'], $attachment['mime'], $attachment['name']);
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Mailgun Error: " . $response);
        }

        return true;
    }
}
