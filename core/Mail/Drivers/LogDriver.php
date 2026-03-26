<?php

namespace Framework\Core\Mail\Drivers;

use Framework\Core\Mail\MailDriverInterface;

class LogDriver implements MailDriverInterface
{
    public function send($to, string $subject, string $body, array $headers = [], array $attachments = [], bool $isHtml = true): bool
    {
        try{
            $recipients = is_array($to) ? implode(', ', $to) : $to;
            
            $output = "--- EMAIL LOG ---\n";
            $output .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $output .= "To: {$recipients}\n";
            $output .= "Subject: {$subject}\n";
            $output .= "Headers: " . json_encode($headers) . "\n";
            $output .= "Attachments: " . count($attachments) . "\n";
            $output .= "Body:\n{$body}\n";
            $output .= "-----------------\n\n";
    
            $logPath = storage_path('logs/mail.log'); 
            $directory = dirname($logPath);

            if (!is_dir($directory)) {
                // Check if we can even write to the parent storage folder
                if (!is_writable(dirname($directory))) {
                    throw new \Exception("The storage directory is not writable by the server.");
                }
                mkdir($directory, 0777, true);
            }
            
                
            return (bool) file_put_contents($logPath, $output, FILE_APPEND);
        }catch(\Exception $e){
            if(env('APP_DEBUG')){
                throw $e;
            }
            return false;
        }
    }
}