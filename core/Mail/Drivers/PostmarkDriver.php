<?php

namespace Framework\Core\Mail\Drivers;

use Framework\Core\Mail\MailDriverInterface;

class PostmarkDriver implements MailDriverInterface
{
    protected string $token;

    public function __construct(array $config = [])
    {
        // Same pattern as MailgunDriver: explicit config wins, then
        // config('mail.postmark.*'), then empty. env() is not read here.
        $cfg = $config + (array) (function_exists('config') ? config('mail.postmark') : []);
        $this->token = (string) ($cfg['token'] ?? '');
    }

    public function send($to, string $subject, string $body, array $headers = [], array $attachments = [], bool $isHtml = true): bool
    {
        if (empty($this->token)) {
            throw new \Exception("Postmark API token (POSTMARK_TOKEN) is missing.");
        }

        $url = 'https://api.postmarkapp.com/email';

        $payload = [
            'From' => $headers['From']
                       ?? (function_exists('config') ? config('mail.from.address') : null)
                       ?? 'system@localhost',
            'To' => is_array($to) ? implode(',', $to) : $to,
            'Subject' => $subject,
        ];

        if ($isHtml) {
            $payload['HtmlBody'] = $body;
        } else {
            $payload['TextBody'] = $body;
        }

        // Handle Attachments
        if (!empty($attachments)) {
            $payload['Attachments'] = [];
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $payload['Attachments'][] = [
                        'Name' => $attachment['name'],
                        'Content' => base64_encode(file_get_contents($attachment['path'])),
                        'ContentType' => $attachment['mime'] ?? mime_content_type($attachment['path'])
                    ];
                }
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headersList = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $this->token
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headersList);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Postmark Error: " . $response);
        }

        return true;
    }
}
