<?php

namespace Framework\Core\Mail\Drivers;

use Framework\Core\Mail\MailDriverInterface;
use Exception;

class SmtpDriver implements MailDriverInterface
{
    private $config;
    private $socket;
    private $logs = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    private function log(string $message): void
    {
        $this->logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }

    public function send($to, string $subject, string $body, array $headers = [], array $attachments = [], bool $isHtml = true): bool
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 2525;
        $encryption = $this->config['encryption'] ?? '';

        $this->log("Connecting to $host:$port (Encryption: $encryption)");

        if ($encryption === 'ssl') $host = 'ssl://' . $host;

        $this->socket = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$this->socket) {
            $this->log("Connection failed: $errstr ($errno)");
            throw new Exception("SMTP Error: $errstr ($errno)");
        }

        $this->readResponse(); 
        $this->sendCommand("EHLO " . gethostname(), 250);

        if ($encryption === 'tls') {
            $this->sendCommand("STARTTLS", 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->log("TLS encryption failed");
                throw new Exception("SMTP Error: Failed to enable TLS encryption");
            }
            $this->sendCommand("EHLO " . gethostname(), 250);
        }

        if (!empty($this->config['username'])) {
            $this->sendCommand("AUTH LOGIN", 334);
            $this->sendCommand(base64_encode($this->config['username']), 334);
            $this->sendCommand(base64_encode($this->config['password']), 235);
        }

        // Determine "From"
        preg_match('/<([^>]+)>/', $headers['From'], $matches);
        $fromEmail = $matches[1] ?? 'system@localhost';
        $this->sendCommand("MAIL FROM:<{$fromEmail}>", 250);

        // Handle Recipients
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            $this->sendCommand("RCPT TO:<{$recipient}>", 250);
        }

        $this->sendCommand("DATA", 354);

        // Pass $isHtml to the payload builder
        $payload = $this->buildMultipartPayload($subject, implode(', ', $recipients), $headers, $body, $attachments, $isHtml);

        $this->log("Sending data...");
        fwrite($this->socket, $payload . "\r\n.\r\n");
        $this->readResponse(250);

        $this->sendCommand("QUIT", 221);
        fclose($this->socket);
        $this->log("Connection closed.");

        return true;
    }

    private function buildMultipartPayload(string $subject, string $toList, array $headers, string $body, array $attachments, bool $isHtml): string
    {
        $contentType = $isHtml ? 'text/html' : 'text/plain';

        $headerString = "Subject: {$subject}\r\nTo: {$toList}\r\n";
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }

        if (empty($attachments)) {
            return $headerString . "Content-Type: {$contentType}; charset=UTF-8\r\n\r\n" . $body;
        }

        $boundary = md5(uniqid(microtime(), true));
        $headerString .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        
        $payload = $headerString . "--{$boundary}\r\n";
        $payload .= "Content-Type: {$contentType}; charset=UTF-8\r\n\r\n{$body}\r\n";

        foreach ($attachments as $att) {
            $content = chunk_split(base64_encode(file_get_contents($att['path'])));
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: {$att['mime']}; name=\"{$att['as']}\"\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n";
            $payload .= "Content-Disposition: attachment; filename=\"{$att['as']}\"\r\n\r\n";
            $payload .= "{$content}\r\n";
        }

        return $payload . "--{$boundary}--\r\n";
    }

    private function sendCommand(string $cmd, int $code): void {
        $this->log("> " . $this->redactCommandForLogs($cmd));
        fwrite($this->socket, $cmd . "\r\n");
        $this->readResponse($code);
    }

    private function redactCommandForLogs(string $cmd): string
    {
        if (stripos($cmd, 'AUTH ') === 0) {
            return 'AUTH [REDACTED]';
        }

        if (preg_match('/^[A-Za-z0-9+\/=]{12,}$/', $cmd)) {
            return '[REDACTED BASE64 PAYLOAD]';
        }

        return $cmd;
    }

    private function readResponse(int $code = null): string {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $this->log("< " . trim($response));
        if ($code && (int)substr($response, 0, 3) !== $code) {
            $this->log("Error: Expected $code, got $response");
            throw new Exception("SMTP Error: Expected $code, got $response");
        }
        return $response;
    }
}
