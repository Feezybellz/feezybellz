<?php

namespace Framework\Core\Mail;

use Framework\Core\View;

abstract class Mailable
{
    public $subject = '';
    public $view = '';
    public $content = '';
    public $isHtml = true;
    
    public $viewData = [];
    public $fromData = [];
    public $attachments = [];

    abstract public function build(): self;

    public function from(string $address, $name = null): self
    {
        $this->fromData = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function view(string $view, array $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;
        $this->isHtml = true;
        return $this;
    }

    public function content(string $content, bool $isHtml = true): self
    {
        $this->content = $content;
        $this->isHtml = $isHtml;
        return $this;
    }

    public function attach(string $path, array $options = []): self
    {
        if (!file_exists($path)) {
            throw new \Exception("Mail Error: Attachment file not found at [{$path}]");
        }
        $this->attachments[] = [
            'path' => $path,
            'as'   => $options['as'] ?? basename($path),
            'mime' => $options['mime'] ?? (mime_content_type($path) ?: 'application/octet-stream')
        ];
        return $this;
    }

    public function render(): string
    {
        if (!empty($this->content)) {
            return $this->content;
        }

        if (empty($this->view)) return '';

        extract($this->viewData);
        ob_start();

        $viewInstance = new View();
        
        if ($viewInstance->exists($this->view)) {
            include $viewInstance->path($this->view);
        } else {
            throw new \Exception("Mail view [{$this->view}] not found.");
        }

        return ob_get_clean();
    }
}
