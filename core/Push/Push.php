<?php

namespace Framework\Core\Push;

use Framework\Core\Push\Drivers\FcmDriver;
use Framework\Core\Push\Drivers\WebPushDriver; 

/**
 * @property string|array $to
 * @property string $title
 * @property string $body
 * @property array  $data
 */
class Push
{
    protected $driverType;
    protected $config = [];
    
    protected $to = [];
    protected $title = '';
    protected $body = '';
    protected $data = [];

    public function __construct()
    {
        $globalConfig = function_exists('config') ? config('push') : [];
        $this->driverType = $globalConfig['default'] ?? 'fcm';
        $this->config = $globalConfig['fcm'] ?? [];
    }

    public function __set(string $name, $value)
    {
        if ($name === 'to') $this->to = $value;
        elseif ($name === 'title') $this->title = $value;
        elseif ($name === 'body') $this->body = $value;
        elseif ($name === 'data') $this->data = $value;
    }

    public static function to($tokens): self
    {
        $instance = new self();
        $instance->to = $tokens;
        return $instance;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function data(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function send(): bool
    {
        switch ($this->driverType) {
            case 'fcm':
                $driver = new FcmDriver($this->config);
                break;
            case 'web':
                $driver = new WebPushDriver(function_exists('config') ? config('push.web') : []);
                break;
            default:
                throw new \Exception("Unsupported Push Driver");
        }

        return $driver->send($this->to, $this->title, $this->body, $this->data);
    }
}
