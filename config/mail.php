<?php

/**
 * Mail configuration.
 *
 * Every field goes through the env() helper so `make:env` can discover the
 * complete set of MAIL_* variables the app reads.
 *
 * The `default` transport picks which sub-section drives sending. Each
 * driver reads its own sub-section only, so switching from smtp to mailgun
 * to postmark is a single-line env change without disturbing the others.
 */

return [

    /*
    | Which mail transport to use. Reads config for its concrete settings.
    | Supported: 'smtp', 'log', 'native', 'mailgun', 'postmark', 'ses'.
    */
    'default' => env('MAIL_MAILER', 'log'),

    /*
    | From-address applied when a Mailable doesn't set one explicitly.
    | MAIL_FROM_NAME supports env interpolation like ${APP_NAME}.
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Framework'),
    ],

    /*
    | Optional application-wide reply-to. Blank = no Reply-To header.
    */
    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS', ''),
        'name'    => env('MAIL_REPLY_TO_NAME', ''),
    ],

    /*
    | SMTP driver settings. Used when MAIL_MAILER=smtp.
    */
    'smtp' => [
        'host'         => env('MAIL_HOST', '127.0.0.1'),
        'port'         => (int) env('MAIL_PORT', 2525),
        'username'     => env('MAIL_USERNAME', null),
        'password'     => env('MAIL_PASSWORD', null),
        'encryption'   => env('MAIL_ENCRYPTION', 'tls'),   // tls | ssl | null
        'timeout'      => (int) env('MAIL_TIMEOUT', 10),
        'local_domain' => env('MAIL_EHLO_DOMAIN', ''),
    ],

    /*
    | Mailgun API. Used when MAIL_MAILER=mailgun.
    */
    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN', ''),
        'secret'   => env('MAILGUN_SECRET', ''),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    /*
    | Postmark API. Used when MAIL_MAILER=postmark.
    */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN', ''),
    ],

    /*
    | Amazon SES. Uses top-level AWS credentials by default; region and
    | endpoint can be overridden here.
    */
    'ses' => [
        'key'      => env('AWS_ACCESS_KEY_ID', ''),
        'secret'   => env('AWS_SECRET_ACCESS_KEY', ''),
        'region'   => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'endpoint' => env('AWS_SES_ENDPOINT', ''),
    ],

    /*
    | Log driver — writes rendered emails to a log channel instead of
    | delivering them. Useful in development and CI.
    */
    'log' => [
        'channel' => env('MAIL_LOG_CHANNEL', 'default'),
    ],

];
