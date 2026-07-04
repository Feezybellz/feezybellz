# Mail

Fluent email sending over interchangeable drivers, with optional
Mailable classes and queue integration.

## Drivers & configuration

Supported (`config/mail.php`): **`smtp`**, **`log`** (writes to
`storage/logs/` — perfect for dev), **`native`** (PHP `mail()`),
**`mailgun`**, **`postmark`**, **`ses`**.

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

Each driver reads only its own config section — switching providers is
a one-line env change.

## Sending — three styles

### Style 1: fluent inline

```php
use Framework\Core\Mail\Mail;

Mail::to('user@example.com')
    ->subject('Welcome!')
    ->plain('Thanks for joining us.')
    ->send();

// HTML body from a view template:
Mail::to('user@example.com')
    ->from('billing@myapp.com', 'MyApp Billing')
    ->subject('Your invoice')
    ->view('emails.invoice', ['total' => 500, 'name' => 'Ada'])
    ->attach(storage_path('invoices/inv-42.pdf'))
    ->send();

// raw HTML string:
Mail::to($user->email)->subject('Hi')->html('<h1>Hello</h1>')->send();
```

`send()` returns `bool`; on failure inspect
`->getLastError()` / `->getDriverLogs()`.

### Style 2: Mailable classes (keeps controllers clean)

```php
<?php

namespace App\Mail;

use Framework\Core\Mail\Mailable;

class WelcomeEmail extends Mailable
{
    public function __construct(public $user) {}

    public function build()
    {
        return $this->subject('Welcome!')
                    ->view('emails.welcome', ['user' => $this->user])
                    ->attach(storage_path('guides/getting-started.pdf'));
    }
}
```

```php
Mail::to('ada@example.com')->send(new WelcomeEmail($user));
```

### Style 3: queued (don't block the HTTP response)

SMTP round-trips take seconds — push to the
[queue](../queue/README.md) instead:

```php
Mail::to('ada@example.com')->queue(new WelcomeEmail($user));
// requires a running worker: php console queue:work
```

## Per-message driver override

```php
Mail::to($addr)->driver('log')->subject('Test')->plain('...')->send();

// custom SMTP for one message (e.g. per-tenant SMTP):
Mail::to($addr)->smtpConfig([
    'host' => $tenant->smtp_host,
    'username' => $tenant->smtp_user,
    'password' => $tenant->smtp_pass,
])->subject('...')->send();
```

## Local development

Set `MAIL_MAILER=log` and every "sent" email lands in
`storage/logs/` for inspection — no SMTP server, no accidental sends.
