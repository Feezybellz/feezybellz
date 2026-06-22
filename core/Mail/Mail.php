<?php

namespace Framework\Core\Mail;

use Framework\Core\Mail\Drivers\LogDriver;
use Framework\Core\Mail\Drivers\NativeDriver;
use Framework\Core\Mail\Drivers\SmtpDriver;
use Framework\Core\View;
use Framework\Core\Mail\Mailable;

/**
 * @property string $host
 * @property int    $port
 * @property string $username
 * @property string $password
 * @property string $encryption
 * @property string|array $to
 * @property string|array $from
 * @property string $subject
 * @property string $view
 * @property string $content    Raw email content
 * @property bool   $isHtml     Whether the content is HTML (default true)
 */
class Mail
{
    protected $driverType;
    protected $smtpConfig = [];
    protected $driverInstance = null;
    
    protected $to = [];
    protected $from = [];
    
    // Inline Email Properties
    protected $subject = '';
    protected $view = '';
    protected $content = '';
    protected $isHtml = true;
    protected $viewData = [];
    protected $attachments = [];
    protected $debug = false;
    protected $lastError = null;
    protected $shouldLog = false;

    public function __construct()
    {
        $config = function_exists('config') ? config('mail') : [];
        $this->driverType = $config['default'] ?? 'log';
        $this->from = $config['from'] ?? ['address' => 'system@localhost', 'name' => 'System'];
        $this->smtpConfig = $config['smtp'] ?? [];
        $this->debug = $config['debug'] ?? false;
    }

    // =========================================================
    // MAGIC METHODS
    // =========================================================

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __get($name)
    {
        return $this->$name ?? null;
    }

    // =========================================================
    // FLUENT API
    // =========================================================

    public static function to($to): self
    {
        $instance = new static();
        $instance->to = $to;
        return $instance;
    }

    public function from($address, $name = null): self
    {
        $this->from = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function debug(bool $value = true): self
    {
        $this->debug = $value;
        return $this;
    }

    public function logMail(bool $value = true): self
    {
        $this->shouldLog = $value;
        return $this;
    }

    public function driver(string $type): self
    {
        $this->driverType = $type;
        return $this;
    }

    public function smtpConfig(array $config): self
    {
        $this->smtpConfig = $config;
        $this->driverType = 'smtp';
        return $this;
    }

    /**
     * Get the last error encountered during send
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getDriverLogs(): array
    {
        if ($this->driverInstance && method_exists($this->driverInstance, 'getLogs')) {
            return $this->driverInstance->getLogs();
        }
        return [];
    }

    public function subject($subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function view($view, array $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;
        return $this;
    }

    public function html($content): self
    {
        $this->content = $content;
        $this->isHtml = true;
        return $this;
    }

    public function plain($content): self
    {
        $this->content = $content;
        $this->isHtml = false;
        return $this;
    }

    public function attach($path, array $options = []): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $options['as'] ?? basename($path),
            'mime' => $options['mime'] ?? null
        ];
        return $this;
    }

    // = =========================================================
    // EXECUTION
    // =========================================================

    /**
     * Render the email content (populated with data)
     * 
     * @return string
     */
    public function render(): string
    {
        // 1. Prepare Body (Render view if exists)
        $body = $this->content;
        if (!empty($this->view)) {
            $body = View::render($this->view, $this->viewData);
        }
        
        return $body;
    }

    public function send($mailable = null): bool
    {
        $this->lastError = null;

        if ($mailable instanceof Mailable) {
            $mailable->build();
            $this->subject = $mailable->subject;
            $this->view = $mailable->view;
            $this->viewData = $mailable->viewData;
            $this->content = $mailable->content;
            $this->isHtml = $mailable->isHtml;
            $this->attachments = $mailable->attachments;
            if (!empty($mailable->fromData)) {
                $this->from = $mailable->fromData;
            }
        }

        $this->driverInstance = $this->resolveDriver();

        // 1. Prepare Body
        $body = $this->render();

        // 2. Format Recipients
        $to = $this->formatRecipients($this->to);
        $from = $this->from;

        // 3. Delegate to Driver
        $result = false;
        try {
            $result = $this->driverInstance->send(
                $to,
                $this->subject,
                $body,
                ['From' => "{$from['name']} <{$from['address']}>"],
                $this->attachments,
                $this->isHtml
            );
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            if ($this->debug) {
                throw $e;
            }
            error_log("Mail Error: " . $this->lastError);
        }

        // Audit Log
        if ($this->shouldLog && function_exists('storage_path')) {
            $logDir = storage_path('logs');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }

            $logMsg = sprintf(
                "[%s] To: %s | Subject: %s | Result: %s %s\n",
                date('Y-m-d H:i:s'),
                implode(', ', $to),
                $this->subject,
                $result ? 'SUCCESS' : 'FAILURE',
                $this->lastError ? "({$this->lastError})" : ""
            );
            try {
                file_put_contents($logDir . '/mail_audit.log', $logMsg, FILE_APPEND);
            } catch (\Exception $e) {
                // Ignore audit log permission errors
            }
        }

        return $result;
    }

    public function queue($mailable = null): bool
    {
        if ($mailable instanceof Mailable) {
            $this->subject = $mailable->subject;
            $this->view = $mailable->view;
            $this->viewData = $mailable->viewData;
            $this->content = $mailable->content;
            $this->isHtml = $mailable->isHtml;
            $this->attachments = $mailable->attachments;
            if (!empty($mailable->fromData)) {
                $this->from = $mailable->fromData;
            }
        }

        // Integration with the new Queue system
        if (class_exists('\Framework\Core\Queue\Queue')) {
            // Because Queue closures/objects aren't easily json_encoded, we pass the data to a generic worker
            $payload = [
                'to' => $this->to,
                'from' => $this->from,
                'subject' => $this->subject,
                'view' => $this->view,
                'viewData' => $this->viewData,
                'content' => $this->content,
                'isHtml' => $this->isHtml,
                'attachments' => $this->attachments,
                'driverType' => $this->driverType
            ];

            return \Framework\Core\Queue\Queue::push([\Framework\Core\Mail\SendQueuedMailJob::class, 'handle'], [$payload]);
        }
        
        // Fallback to synchronous sending
        return $this->send($mailable);
    }

    // =========================================================
    // INTERNAL HELPERS
    // =========================================================

    protected function resolveDriver()
    {
        switch ($this->driverType) {
            case 'smtp':
                return new SmtpDriver($this->smtpConfig);
            case 'native':
                return new \Framework\Core\Mail\Drivers\NativeDriver();
            case 'mailgun':
                return new \Framework\Core\Mail\Drivers\MailgunDriver();
            case 'ses':
                return new \Framework\Core\Mail\Drivers\SesDriver();
            case 'postmark':
                return new \Framework\Core\Mail\Drivers\PostmarkDriver();
            case 'log':
            default:
                return new \Framework\Core\Mail\Drivers\LogDriver();
        }
    }

    protected function formatRecipients($recipients): array
    {
        if (is_string($recipients)) {
            return [$recipients];
        }

        $formatted = [];
        foreach ($recipients as $key => $value) {
            if (is_numeric($key)) {
                $formatted[] = $value;
            } else {
                // If it's a 'name' => 'email' array
                $formatted[] = $value;
            }
        }
        return $formatted;
    }
}
