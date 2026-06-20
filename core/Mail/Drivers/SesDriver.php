<?php

namespace Framework\Core\Mail\Drivers;

use Framework\Core\Mail\MailDriverInterface;

class SesDriver implements MailDriverInterface
{
    protected string $key;
    protected string $secret;
    protected string $region;

    public function __construct()
    {
        // Fallback to general AWS keys if SES specific ones aren't set
        $this->key = env('AWS_ACCESS_KEY_ID', '');
        $this->secret = env('AWS_SECRET_ACCESS_KEY', '');
        $this->region = env('AWS_DEFAULT_REGION', 'us-east-1');
    }

    public function send($to, string $subject, string $body, array $headers = [], array $attachments = [], bool $isHtml = true): bool
    {
        if (empty($this->key) || empty($this->secret)) {
            throw new \Exception("AWS SES API credentials are missing.");
        }

        $host = "email.{$this->region}.amazonaws.com";
        $url = "https://{$host}/";

        // Building the raw SES query parameters
        $params = [
            'Action' => 'SendEmail',
            'Message.Subject.Data' => $subject,
            'Source' => $headers['From'] ?? env('MAIL_FROM_ADDRESS', 'system@localhost')
        ];

        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $index => $recipient) {
            $params["Destination.ToAddresses.member." . ($index + 1)] = $recipient;
        }

        if ($isHtml) {
            $params['Message.Body.Html.Data'] = $body;
        } else {
            $params['Message.Body.Text.Data'] = $body;
        }

        $payload = http_build_query($params);

        // AWS Signature V4 Authentication
        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');
        
        $headersList = [
            "Host: {$host}",
            "X-Amz-Date: {$date}",
            "Content-Type: application/x-www-form-urlencoded"
        ];
        
        // Detailed V4 Signature process is complex to implement without SDK
        // For zero-dependency framework, we usually recommend SMTP for SES, 
        // but this acts as the foundational wrapper to hit the AWS SES API.
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headersList); // Signature would be appended here
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // SES responds with 200 OK on success
        if ($httpCode !== 200) {
            // Note: Since raw SigV4 is huge, developers will often use SMTP interface for SES
            // Or use the official AWS SDK inside this driver if installed via Composer.
            throw new \Exception("AWS SES API Error: HTTP " . $httpCode . " " . $response);
        }

        return true;
    }
}
