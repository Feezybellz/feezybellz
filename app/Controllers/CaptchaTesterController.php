<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Captcha\Captcha;
use Framework\Core\Captcha\Exceptions\CaptchaException;
use Exception;

class CaptchaTesterController
{
    /**
     * Render the interactive Captcha studio UI.
     */
    public function index(Request $request): Response
    {
        return Response::view('captcha_tester');
    }

    /**
     * Demonstrate manual verification within a controller method.
     */
    public function verifyManual(Request $request): Response
    {
        try {
            Captcha::verifyOrFail($request, 'demo_form');

            return Response::json([
                'success' => true,
                'message' => '🎉 Security challenge verified successfully! The PoW calculation and behavioral entropy were confirmed, and the nonce has been burned into the Framework Cache to prevent replay attacks.',
                'mode' => 'Manual Controller Validation',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        } catch (CaptchaException $e) {
            return Response::setStatusCode(403)->json([
                'success' => false,
                'error' => 'Verification Failed',
                'message' => $e->getMessage(),
                'mode' => 'Manual Controller Validation',
            ]);
        } catch (Exception $e) {
            return Response::setStatusCode(500)->json([
                'success' => false,
                'error' => 'System Error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Demonstrate automatic route middleware protection.
     * Note: If this method executes, the captcha middleware already passed!
     */
    public function verifyMiddleware(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'message' => '🛡️ Successfully passed automatic Route Middleware (captcha:demo_form)! No verification boilerplate code was needed in the controller.',
            'mode' => 'Zero-Code Route Middleware',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate a fresh challenge ticket via AJAX for iterative studio testing.
     */
    public function generateChallenge(Request $request): Response
    {
        try {
            $difficulty = (int) ($request->input('difficulty') ?? 3);
            $difficulty = max(1, min(6, $difficulty)); // limit between 1 and 6 for UI safety
            
            $challenge = Captcha::challenge('demo_form', $difficulty, 600);

            return Response::json([
                'success' => true,
                'challenge' => $challenge,
                'message' => "Generated challenge for 'demo_form' with SHA-256 difficulty {$difficulty}",
            ]);
        } catch (Exception $e) {
            return Response::setStatusCode(500)->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dynamically render an interactive captcha widget by mode for the live UI studio.
     */
    public function renderField(Request $request): Response
    {
        try {
            $mode = $request->input('mode') ?? 'silent';
            $theme = $request->input('theme') ?? 'dark';
            $difficulty = (int) ($request->input('difficulty') ?? 3);
            $ttl = (int) ($request->input('ttl') ?? 600);
            
            $html = Captcha::captcha_field('demo_form', [
                'mode' => $mode,
                'theme' => $theme,
                'difficulty' => $difficulty,
                'ttl' => $ttl,
            ]);

            return Response::json([
                'success' => true,
                'html' => $html,
                'mode' => $mode,
                'message' => "Rendered widget for mode '{$mode}' in theme '{$theme}'",
            ]);
        } catch (Exception $e) {
            return Response::setStatusCode(500)->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
