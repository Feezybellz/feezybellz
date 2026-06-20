<?php

namespace Framework\Core\Mail;

class SendQueuedMailJob
{
    /**
     * Reconstruct and send the queued email
     */
    public static function handle(array $payload): void
    {
        $mailer = new Mail();
        
        // Reconstruct the Mailer state from the queued payload
        $mailer->to($payload['to']);
        
        if (!empty($payload['from'])) {
            $mailer->from($payload['from']['address'] ?? null, $payload['from']['name'] ?? null);
        }

        if (!empty($payload['driverType'])) {
            $mailer->driver($payload['driverType']);
        }

        $mailer->subject($payload['subject']);
        
        if (!empty($payload['view'])) {
            $mailer->view($payload['view'], $payload['viewData'] ?? []);
        } elseif ($payload['isHtml']) {
            $mailer->html($payload['content']);
        } else {
            $mailer->plain($payload['content']);
        }

        foreach ($payload['attachments'] ?? [] as $attachment) {
            $mailer->attach($attachment['path'], [
                'as' => $attachment['name'],
                'mime' => $attachment['mime']
            ]);
        }

        // Send it synchronously now that we are inside the Queue Worker process
        $mailer->send();
    }
}
