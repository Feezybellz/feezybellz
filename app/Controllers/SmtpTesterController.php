<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Mail\Mail;
use Exception;

class SmtpTesterController
{
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleTest($request);
        }

        return Response::view('smtp_tester', [
            'logs' => [],
            'success' => null,
            'error' => null,
            'input' => []
        ]);
    }

    protected function handleTest(Request $request): Response
    {
        $input = $request->all();
        $logs = [];
        $success = false;
        $error = null;

        try {
            $mail = Mail::to($input['to'] ?? '')
                ->from($input['from_address'] ?? 'system@localhost', $input['from_name'] ?? 'System')
                ->subject($input['subject'] ?? 'SMTP Test')
                ->html($input['body'] ?? '<p>This is a test email.</p>')
                ->smtpConfig([
                    'host' => $input['host'] ?? '',
                    'port' => (int)($input['port'] ?? 587),
                    'username' => $input['username'] ?? '',
                    'password' => $input['password'] ?? '',
                    'encryption' => $input['encryption'] ?? ''
                ]);

            $success = $mail->send();
            $logs = $mail->getDriverLogs();
        } catch (Exception $e) {
            $success = false;
            $error = $e->getMessage();
            if (isset($mail)) {
                $logs = $mail->getDriverLogs();
            }
        }

        return Response::json([
            'success' => $success,
            'error' => $error,
            'logs' => $logs
        ]);
    }
}
