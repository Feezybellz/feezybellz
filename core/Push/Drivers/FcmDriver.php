<?php

namespace Framework\Core\Push\Drivers;

use Framework\Core\Push\PushDriverInterface;
use Exception;

class FcmDriver implements PushDriverInterface
{
    private $serverKey;

    public function __construct(array $config)
    {
        $this->serverKey = $config['server_key'] ?? '';
    }

    public function send($tokens, string $title, string $body, array $data = []): bool
    {
        if (empty($this->serverKey)) {
            throw new Exception("Push Error: FCM Server Key is missing.");
        }

        $tokens = is_array($tokens) ? $tokens : [$tokens];

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
            ],
            'data' => $data // Hidden payload for your app to process silently
        ];

        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("FCM cURL Error: " . $error);
        }

        curl_close($ch);

        return $httpCode === 200;
    }
}
