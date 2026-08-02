<?php

namespace Framework\Core\Captcha;

use Framework\Core\Auth\SignedToken;
use Framework\Core\Cache\Cache;
use Framework\Core\Captcha\Exceptions\CaptchaException;
use Framework\Core\Http\Request;
use Framework\Core\Image\Image;

class Captcha
{
    /**
     * Issue a cryptographic, stateless challenge ticket.
     *
     * @param string|null $name Form identifier scope (optional)
     * @param int|null $difficulty Override default SHA-256 leading zero target
     * @param int|null $ttl Override default time-to-live in seconds
     * @param int|null $puzzleX Optional target X coordinate for image alignment puzzle mode
     * @return array Challenge payload details
     */
    public static function challenge(?string $name = null, ?int $difficulty = null, ?int $ttl = null, ?int $puzzleX = null): array
    {
        $diff = $difficulty ?? (function_exists('config') && config('captcha.difficulty') !== null ? (int) config('captcha.difficulty') : 3);
        $timeToLive = $ttl ?? (function_exists('config') && config('captcha.ttl') !== null ? (int) config('captcha.ttl') : 600);

        $nonce = bin2hex(random_bytes(16));
        $iat = time();

        $payload = [
            'n' => $nonce,
            'd' => $diff,
            'm' => $name,
            'i' => $iat,
            't' => $timeToLive,
        ];

        if ($puzzleX !== null) {
            $payload['px'] = $puzzleX;
        }

        $token = SignedToken::issue($payload, $timeToLive);

        $res = [
            'token' => $token,
            'nonce' => $nonce,
            'difficulty' => $diff,
            'name' => $name,
            'iat' => $iat,
            'ttl' => $timeToLive,
        ];
        if ($puzzleX !== null) {
            $res['puzzleX'] = $puzzleX;
        }
        return $res;
    }

    /**
     * Render an intelligent HTML + inline JavaScript dynamic puzzle solver field.
     * Alias requested by user: Captcha::captcha_field($name, $options).
     *
     * Options array supports:
     *   - 'mode': 'silent' (default), 'slider', 'turnstile', 'puzzle'
     *   - 'theme': 'dark' (default), 'light'
     *   - 'difficulty': int
     *   - 'ttl': int
     */
    public static function captcha_field(?string $name = null, array $options = []): string
    {
        return self::field($name, $options);
    }

    /**
     * Render an intelligent HTML + inline JavaScript dynamic puzzle solver field.
     */
    public static function field(?string $name = null, array $options = []): string
    {
        $mode = $options['mode'] ?? 'silent';
        $theme = $options['theme'] ?? 'dark';

        // Prepare puzzle coordinates if mode is puzzle
        $puzzleX = null;
        $bgDataUri = '';
        $pieceDataUri = '';
        $pieceY = 35;

        if ($mode === 'puzzle') {
            $puzzleX = rand(50, 210);
            $puzzleData = self::generatePuzzleAssets($puzzleX, $pieceY);
            $bgDataUri = $puzzleData['bg'];
            $pieceDataUri = $puzzleData['piece'];
        }

        $ch = self::challenge($name, $options['difficulty'] ?? null, $options['ttl'] ?? null, $puzzleX);
        $uid = 'cpt_' . bin2hex(random_bytes(5));
        $nameVal = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
        $tokenVal = htmlspecialchars($ch['token'], ENT_QUOTES, 'UTF-8');
        $nonceVal = htmlspecialchars($ch['nonce'], ENT_QUOTES, 'UTF-8');
        $diffVal = (int) $ch['difficulty'];
        $ttlVal = (int) ($ch['ttl'] ?? config('captcha.expires_in', 600));
        $pxVal = (int) ($puzzleX ?? 0);

        // Color theme palettes
        $bgCol = $theme === 'light' ? '#F8FAFC' : '#1E293B';
        $borderCol = $theme === 'light' ? '#CBD5E1' : '#334155';
        $textCol = $theme === 'light' ? '#0F172A' : '#F8FAFC';
        $textMuted = $theme === 'light' ? '#64748B' : '#94A3B8';
        $trackBg = $theme === 'light' ? '#E2E8F0' : '#0F172A';

        // Base hidden inputs
        $hiddenInputs = <<<HTML
    <input type="hidden" name="captcha_token" value="{$tokenVal}" id="{$uid}_token">
    <input type="hidden" name="captcha_solution" value="" id="{$uid}_solution">
    <input type="hidden" name="captcha_entropy" value="0" id="{$uid}_entropy">
    <input type="hidden" name="captcha_name" value="{$nameVal}">
    <input type="hidden" name="captcha_puzzle_x" value="0" id="{$uid}_puzzlex">
HTML;

        // SVG Icons Collection
        $svgArrowRight = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
        $svgShield = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>';
        $svgCheckWhite = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        $svgCheckGreen = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        $svgSpinner = '<svg style="animation: cpt_spin_' . $uid . ' 0.8s linear infinite;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0EA5E9" stroke-width="2.6" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
        $svgPuzzle = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><path d="M19.5 12.5 21 11V8a2 2 0 0 0-2-2h-3l-1.5 1.5a1.5 1.5 0 0 1-2.12 0L11 6H8a2 2 0 0 0-2 2v3l1.5 1.5a1.5 1.5 0 0 1 0 2.12L6 16v3a2 2 0 0 0 2 2h3l1.5-1.5a1.5 1.5 0 0 1 2.12 0L16 21h3a2 2 0 0 0 2-2v-3l-1.5-1.5a1.5 1.5 0 0 1 0-2.12Z"/></svg>';
        $svgArrowsHorizontal = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 7-5 5 5 5"/><path d="m16 7 5 5-5 5"/><path d="M3 12h18"/></svg>';
        $svgLock = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

        // Expiry & retry wrapper HTML
        $expiryBanner = <<<HTML
<div id="{$uid}_expiry_banner" style="display: none; align-items: center; justify-content: space-between; padding: 0.65rem 0.9rem; background: rgba(245, 158, 11, 0.08); border: 1px dashed #F59E0B; border-radius: 12px; margin: 0.7rem 0; max-width: 380px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <span style="color: #F59E0B; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 0.45rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Verification expired
    </span>
    <button type="button" id="{$uid}_btn_retry" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 8px; padding: 0.4rem 0.85rem; font-size: 0.82rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; transition: all 0.2s;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
        <span id="{$uid}_retry_txt">Tap to Retry</span>
    </button>
</div>
HTML;

        // Render UI based on mode
        $widgetHtml = '';
        $extraCss = "<style>@keyframes cpt_spin_{$uid} { 100% { transform: rotate(360deg); } } @keyframes cpt_shake_{$uid} { 0%, 100% { transform: translateX(0); } 15%, 45%, 75% { transform: translateX(-7px); } 30%, 60%, 90% { transform: translateX(7px); } }</style>";

        if ($mode === 'silent') {
            $widgetHtml = <<<HTML
<div id="{$uid}_box" class="antigravity-captcha-container" style="display:none;" data-mode="silent">
{$hiddenInputs}
</div>
HTML;
        } elseif ($mode === 'slider') {
            $widgetHtml = <<<HTML
{$extraCss}
<div id="{$uid}_box" class="antigravity-captcha-container" style="margin: 0.8rem 0; max-width: 380px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;" data-mode="slider">
{$hiddenInputs}
    <div style="background: {$bgCol}; border: 1px solid {$borderCol}; border-radius: 14px; padding: 0.85rem; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.15);">
        <div id="{$uid}_track" style="position: relative; height: 44px; background: {$trackBg}; border-radius: 22px; overflow: hidden; display: flex; align-items: center; justify-content: center; user-select: none; border: 1px solid rgba(255,255,255,0.08); box-shadow: inset 0 2px 4px rgba(0,0,0,0.25);">
            <div id="{$uid}_fill" style="position: absolute; left: 0; top: 0; bottom: 0; width: 0%; background: linear-gradient(90deg, rgba(14, 165, 233, 0.4), rgba(16, 185, 129, 0.4)); transition: background 0.3s; pointer-events: none;"></div>
            <span id="{$uid}_text" style="color: {$textMuted}; font-size: 0.86rem; font-weight: 600; z-index: 2; transition: color 0.3s; pointer-events: none;">Slide to verify</span>
            <div id="{$uid}_handle" style="position: absolute; left: 3px; top: 3px; height: 36px; width: 48px; background: linear-gradient(135deg, #0EA5E9, #0284C7); border-radius: 18px; box-shadow: 0 2px 8px rgba(14, 165, 233, 0.4); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 3; color: #FFF; transition: transform 0.1s, background 0.2s;">{$svgArrowRight}</div>
        </div>
    </div>
</div>
HTML;
        } elseif ($mode === 'turnstile') {
            $widgetHtml = <<<HTML
{$extraCss}
<div id="{$uid}_box" class="antigravity-captcha-container" style="margin: 0.8rem 0; max-width: 350px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; cursor: pointer; user-select: none;" data-mode="turnstile">
{$hiddenInputs}
    <div style="background: {$bgCol}; border: 1px solid {$borderCol}; border-radius: 12px; padding: 0.75rem 1.1rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.15); transition: border-color 0.2s;" id="{$uid}_ts_card">
        <div style="display: flex; align-items: center; gap: 0.9rem;">
            <div id="{$uid}_ts_box" style="width: 24px; height: 24px; border: 2px solid #64748B; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; background: {$trackBg};"></div>
            <div>
                <div id="{$uid}_ts_label" style="color: {$textCol}; font-weight: 600; font-size: 0.92rem;">Verify you are human</div>
                <div style="color: {$textMuted}; font-size: 0.73rem; letter-spacing: 0.02em;">Antigravity Security Shield</div>
            </div>
        </div>
        <div>{$svgShield}</div>
    </div>
</div>
HTML;
        } elseif ($mode === 'puzzle') {
            $widgetHtml = <<<HTML
{$extraCss}
<div id="{$uid}_box" class="antigravity-captcha-container" style="margin: 0.8rem 0; max-width: 320px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;" data-mode="puzzle">
{$hiddenInputs}
    <div style="background: {$bgCol}; border: 1px solid {$borderCol}; border-radius: 14px; padding: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.25);">
        <div style="color: {$textCol}; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.6rem; display: flex; justify-content: space-between; align-items: center;">
            <span id="{$uid}_p_title">{$svgPuzzle}Drag piece to complete puzzle</span>
            <span style="font-size:0.75rem; color:#10B981; font-weight:700; display:none;" id="{$uid}_p_badge">VERIFIED</span>
        </div>
        <div style="position: relative; width: 280px; height: 130px; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 0.8rem; background: #0F172A;">
            <img src="{$bgDataUri}" style="width: 280px; height: 130px; display: block;">
            <img src="{$pieceDataUri}" id="{$uid}_p_piece" style="position: absolute; left: 10px; top: {$pieceY}px; width: 45px; height: 45px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.6)); pointer-events: none; transition: left 0.05s linear;">
        </div>
        <div id="{$uid}_p_track" style="position: relative; height: 44px; background: {$trackBg}; border-radius: 22px; display: flex; align-items: center; justify-content: center; user-select: none; border: 1px solid rgba(255,255,255,0.08); box-shadow: inset 0 2px 4px rgba(0,0,0,0.25); overflow: hidden;">
            <span id="{$uid}_p_text" style="color: {$textMuted}; font-size: 0.85rem; font-weight: 600; z-index: 1; pointer-events: none;">Slide to align puzzle</span>
            <div id="{$uid}_p_handle" style="position: absolute; left: 3px; top: 3px; height: 36px; width: 48px; background: linear-gradient(135deg, #0EA5E9, #0284C7); border-radius: 18px; box-shadow: 0 2px 8px rgba(14, 165, 233, 0.4); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 2; color: #FFF; transition: background 0.2s;">{$svgArrowsHorizontal}</div>
        </div>
    </div>
</div>
HTML;
        }

        $fullContainer = <<<HTML
<div id="{$uid}_wrapper" class="antigravity-captcha-wrapper">
{$widgetHtml}
{$expiryBanner}
</div>
HTML;

        // Inline solver, expiration logic, and UX handler JavaScript
        $js = <<<JS
<script>
(function() {
    var solutionInput = document.getElementById('{$uid}_solution');
    var entropyInput = document.getElementById('{$uid}_entropy');
    var puzzleXInput = document.getElementById('{$uid}_puzzlex');
    var nonce = "{$nonceVal}";
    var difficulty = {$diffVal};
    var mode = "{$mode}";
    var ttl = {$ttlVal};
    var targetPuzzleX = {$pxVal};

    var iconSpinner = '{$svgSpinner}';
    var iconCheckGreen = '{$svgCheckGreen}';
    var iconCheckWhite = '{$svgCheckWhite}';
    var iconLock = '{$svgLock}';

    // 1. Expiration & Asynchronous In-Place Refresh Handling
    var expired = false;
    var wrapper = document.getElementById('{$uid}_wrapper');
    var box = document.getElementById('{$uid}_box');
    var banner = document.getElementById('{$uid}_expiry_banner');
    var retryBtn = document.getElementById('{$uid}_btn_retry');

    var triggerExpire = function() {
        if (expired) return;
        expired = true;
        if (mode === 'silent') {
            doRefresh();
        } else {
            if (box) box.style.display = 'none';
            if (banner) banner.style.display = 'flex';
            var term = document.getElementById('terminal-log');
            if (term && typeof log === 'function') {
                log("Captcha security token expired (TTL elapsed). Awaiting user retry...", "error");
            }
        }
    };

    var doRefresh = function() {
        var txt = document.getElementById('{$uid}_retry_txt');
        if (txt) txt.innerText = 'Reloading...';
        if (typeof log === 'function') log("Fetching refreshed cryptographic challenge token via seamless AJAX...", "info");
        
        var url = '/__captcha/refresh?name=' + encodeURIComponent("{$nameVal}") + '&mode=' + mode + '&theme=' + "{$theme}" + '&difficulty=' + difficulty + '&ttl=' + ttl;
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.html) {
                    var htmlContent = data.html.replace(/<script>([\s\S]*?)<\/script>/, "");
                    var match = data.html.match(/<script>([\s\S]*?)<\/script>/);
                    if (wrapper) wrapper.outerHTML = htmlContent;
                    if (match && match[1]) {
                        var scriptEl = document.createElement('script');
                        scriptEl.textContent = match[1];
                        document.body.appendChild(scriptEl);
                    }
                    if (typeof log === 'function') log("Challenge token re-issued successfully without page reload!", "success");
                } else {
                    if (txt) txt.innerText = 'Error Reloading';
                }
            })
            .catch(function(e) {
                if (txt) txt.innerText = 'Network Error - Retry';
            });
    };

    if (retryBtn) {
        retryBtn.addEventListener('click', doRefresh);
    }

    var timerDelay = Math.max(5000, (ttl * 1000) - 1000);
    setTimeout(triggerExpire, timerDelay);
    window.addEventListener('antigravity:captcha-expire', triggerExpire);

    var triggerError = function(e) {
        var reason = (e && e.detail && e.detail.message) ? e.detail.message : "Verification rejected or challenge already consumed";
        if (expired) return;
        if (mode === 'silent') {
            doRefresh();
            return;
        }
        var elToShake = (mode === 'turnstile' ? document.getElementById('{$uid}_ts_card') : (box ? box.querySelector('div') : null));
        if (elToShake) {
            elToShake.style.transition = 'border-color 0.2s, box-shadow 0.2s';
            elToShake.style.borderColor = '#F43F5E';
            elToShake.style.boxShadow = '0 0 20px rgba(244, 63, 94, 0.7)';
        }
        if (box) {
            box.style.animation = 'cpt_shake_{$uid} 0.6s ease-in-out';
        }
        setTimeout(function() {
            expired = true;
            if (box) box.style.display = 'none';
            var titleEl = banner ? banner.querySelector('span') : null;
            if (titleEl) {
                titleEl.innerText = 'Error, please retry';
                titleEl.style.color = '#FB7185';
            }
            if (banner) {
                banner.style.borderColor = '#F43F5E';
                banner.style.boxShadow = '0 0 15px rgba(244, 63, 94, 0.25)';
                banner.style.display = 'flex';
            }
            if (typeof log === 'function') {
                log("Security shield engaged: challenge token already consumed or invalid (" + reason + "). Switching to Retry Interface.", "error");
            }
        }, 650);
    };
    window.addEventListener('antigravity:captcha-error', triggerError);

    // 2. Behavioral Entropy Tracker
    var interacted = false;
    var markEntropy = function() {
        if (!interacted) {
            interacted = true;
            if (entropyInput) entropyInput.value = "1";
        }
    };
    window.addEventListener('pointermove', markEntropy);
    window.addEventListener('keydown', markEntropy);
    window.addEventListener('focus', markEntropy);
    window.addEventListener('touchstart', markEntropy);

    // 3. Web Worker SHA-256 Proof-of-Work Solver
    var runPoWSolver = function(onDone) {
        if (window.Worker && window.Blob && window.URL && window.crypto && window.crypto.subtle) {
            var workerScript = "self.onmessage = async function(e) { " +
                "var nonce = e.data.nonce; var diff = e.data.diff; var target = '0'.repeat(diff); var counter = 0; " +
                "var encoder = new TextEncoder(); " +
                "while(true) { " +
                    "var str = nonce + counter; " +
                    "var buf = await crypto.subtle.digest('SHA-256', encoder.encode(str)); " +
                    "var arr = Array.from(new Uint8Array(buf)); " +
                    "var hex = arr.map(function(b){ return b.toString(16).padStart(2,'0') }).join(''); " +
                    "if(hex.startsWith(target)) { self.postMessage(counter); break; } " +
                    "counter++; " +
                "} " +
            "};";
            var blob = new Blob([workerScript], { type: 'application/javascript' });
            var worker = new Worker(URL.createObjectURL(blob));
            worker.onmessage = function(e) {
                if (solutionInput) solutionInput.value = e.data;
                worker.terminate();
                if (onDone) onDone(e.data);
            };
            worker.postMessage({ nonce: nonce, diff: difficulty });
        } else {
            if (solutionInput) solutionInput.value = "999";
            if (onDone) onDone("999");
        }
    };

    if (mode === 'silent') {
        runPoWSolver();
    }

    // 4. UX Mode Handlers
    if (mode === 'slider') {
        var handle = document.getElementById('{$uid}_handle');
        var track = document.getElementById('{$uid}_track');
        var fill = document.getElementById('{$uid}_fill');
        var text = document.getElementById('{$uid}_text');
        var isDragging = false;
        var solved = false;

        var onStart = function(e) {
            if (solved || expired) return;
            isDragging = true;
            markEntropy();
        };
        var onMove = function(e) {
            if (!isDragging || solved || expired) return;
            var clientX = e.touches ? e.touches[0].clientX : e.clientX;
            var rect = track.getBoundingClientRect();
            var maxLeft = rect.width - handle.offsetWidth - 4;
            var pos = Math.max(2, Math.min(clientX - rect.left - handle.offsetWidth / 2, maxLeft));
            handle.style.left = pos + 'px';
            var pct = (pos / maxLeft) * 100;
            fill.style.width = pct + '%';

            if (pct >= 95) {
                solved = true;
                isDragging = false;
                handle.style.left = maxLeft + 'px';
                fill.style.width = '100%';
                handle.innerHTML = iconSpinner;
                text.innerText = 'Verifying...';
                runPoWSolver(function() {
                    if (entropyInput && entropyInput.value === "0" && !interacted) {
                        handle.style.background = '#F43F5E';
                        text.innerText = 'Suspicious Automated Action';
                        triggerError({ detail: { message: "Zero human interaction entropy detected" } });
                    } else {
                        handle.innerHTML = iconCheckGreen;
                        handle.style.background = '#10B981';
                        text.innerText = 'Security Verified';
                        text.style.color = '#FFF';
                        text.style.textShadow = '0 0 8px rgba(0,0,0,0.8)';
                    }
                });
            }
        };
        var onEnd = function() {
            if (!solved && isDragging) {
                isDragging = false;
                handle.style.transition = 'left 0.2s';
                fill.style.transition = 'width 0.2s';
                handle.style.left = '2px';
                fill.style.width = '0%';
                setTimeout(function(){ handle.style.transition = ''; fill.style.transition = ''; }, 200);
            }
        };

        handle.addEventListener('mousedown', onStart);
        handle.addEventListener('touchstart', onStart, { passive: true });
        window.addEventListener('mousemove', onMove);
        window.addEventListener('touchmove', onMove, { passive: true });
        window.addEventListener('mouseup', onEnd);
        window.addEventListener('touchend', onEnd);
    }

    if (mode === 'turnstile') {
        var box = document.getElementById('{$uid}_ts_box');
        var label = document.getElementById('{$uid}_ts_label');
        var card = document.getElementById('{$uid}_ts_card');
        var tsSolved = false;
        var tsWorking = false;

        card.addEventListener('click', function() {
            if (tsSolved || tsWorking || expired) return;
            tsWorking = true;
            markEntropy();
            box.style.border = '3px solid transparent';
            box.style.borderTopColor = '#0EA5E9';
            box.style.borderRadius = '50%';
            box.style.animation = 'cpt_spin_{$uid} 0.7s linear infinite';
            label.innerText = "Checking browser proof...";
            
            runPoWSolver(function() {
                tsWorking = false;
                tsSolved = true;
                box.style.animation = '';
                box.style.border = 'none';
                box.style.background = '#10B981';
                box.innerHTML = iconCheckWhite;
                label.innerText = "Verification complete";
                card.style.borderColor = '#10B981';
            });
        });
    }

    if (mode === 'puzzle') {
        var pHandle = document.getElementById('{$uid}_p_handle');
        var pTrack = document.getElementById('{$uid}_p_track');
        var pPiece = document.getElementById('{$uid}_p_piece');
        var pText = document.getElementById('{$uid}_p_text');
        var pBadge = document.getElementById('{$uid}_p_badge');
        var pDragging = false;
        var pSolved = false;

        var onPStart = function(e) {
            if (pSolved || expired) return;
            pDragging = true;
            markEntropy();
        };
        var onPMove = function(e) {
            if (!pDragging || pSolved || expired) return;
            var clientX = e.touches ? e.touches[0].clientX : e.clientX;
            var rect = pTrack.getBoundingClientRect();
            var maxLeft = rect.width - pHandle.offsetWidth - 6;
            var pos = Math.max(3, Math.min(clientX - rect.left - pHandle.offsetWidth / 2, maxLeft));
            pHandle.style.left = pos + 'px';

            var pieceX = (pos / maxLeft) * (280 - 45);
            pPiece.style.left = Math.round(pieceX) + 'px';
        };
        var onPEnd = function() {
            if (pDragging && !pSolved) {
                pDragging = false;
                var currentX = parseInt(pPiece.style.left, 10) || 0;
                if (puzzleXInput) puzzleXInput.value = currentX;

                pText.innerText = "Verifying position...";
                pHandle.innerHTML = iconSpinner;
                runPoWSolver(function() {
                    var delta = Math.abs(currentX - targetPuzzleX);
                    if (delta <= 9) {
                        pSolved = true;
                        pTrack.style.background = 'rgba(16, 185, 129, 0.2)';
                        pTrack.style.borderColor = '#10B981';
                        pHandle.innerHTML = iconLock;
                        pHandle.style.background = '#10B981';
                        pText.innerText = 'Verified & Locked';
                        pText.style.color = '#FFF';
                        if (pBadge) pBadge.style.display = 'inline-block';
                        if (typeof log === 'function') log("Visual puzzle alignment verified successfully! (Delta: " + delta + "px within ±9px tolerance)", "success");
                    } else {
                        pTrack.style.background = 'rgba(244, 63, 94, 0.2)';
                        pTrack.style.borderColor = '#F43F5E';
                        pHandle.style.background = '#F43F5E';
                        pText.innerText = 'Alignment mismatch';
                        pText.style.color = '#FB7185';
                        if (typeof log === 'function') log("Visual puzzle alignment verification failed! (Off by " + delta + "px, tolerance is ±9px). Switching to retry interface...", "error");
                        triggerError({ detail: { message: "Puzzle alignment incorrect (off by " + delta + "px)" } });
                    }
                });
            }
        };

        pHandle.addEventListener('mousedown', onPStart);
        pHandle.addEventListener('touchstart', onPStart, { passive: true });
        window.addEventListener('mousemove', onPMove);
        window.addEventListener('touchmove', onPMove, { passive: true });
        window.addEventListener('mouseup', onPEnd);
        window.addEventListener('touchend', onPEnd);
    }
})();
</script>
JS;

        return $fullContainer . "\n" . $js;

    }

    /**
     * Synthesize visual image puzzle assets using the framework Image Service.
     */
    protected static function generatePuzzleAssets(int $targetX, int $targetY): array
    {
        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            try {
                $im = imagecreatetruecolor(280, 130);
                // Allocate rich color palette
                $bg = imagecolorallocate($im, 15, 23, 42);         // #0F172A
                $c1 = imagecolorallocatealpha($im, 14, 165, 233, 50); // Cyan #0EA5E9
                $c2 = imagecolorallocatealpha($im, 16, 185, 129, 50); // Green #10B981
                $c3 = imagecolorallocatealpha($im, 244, 63, 94, 60);  // Rose #F43F5E
                $cavityCol = imagecolorallocate($im, 5, 8, 14);       // Deep Dark cavity
                $cavityBorder = imagecolorallocate($im, 56, 189, 248);// Sky Blue border

                // Fill background
                imagefilledrectangle($im, 0, 0, 280, 130, $bg);

                // Draw rich geometric abstract patterns for visual alignment entropy
                imagefilledrectangle($im, 20, 15, 140, 115, $c1);
                imagefilledrectangle($im, 90, 25, 250, 105, $c2);
                imagefilledellipse($im, 160, 65, 90, 90, $c3);

                // Draw grid lines for alignment precision
                for ($x = 0; $x < 280; $x += 20) {
                    imageline($im, $x, 0, $x, 130, $c1);
                }
                for ($y = 0; $y < 130; $y += 20) {
                    imageline($im, 0, $y, 280, $y, $c2);
                }

                // Extract puzzle piece cut-out before rendering cavity
                $pieceIm = imagecreatetruecolor(45, 45);
                imagecopy($pieceIm, $im, 0, 0, $targetX, $targetY, 45, 45);

                // Add highlight border around the puzzle piece
                $pieceBorder = imagecolorallocate($pieceIm, 56, 189, 248);
                imagerectangle($pieceIm, 0, 0, 44, 44, $pieceBorder);

                // Darken target cavity on main background
                imagefilledrectangle($im, $targetX, $targetY, $targetX + 44, $targetY + 44, $cavityCol);
                imagerectangle($im, $targetX, $targetY, $targetX + 44, $targetY + 44, $cavityBorder);

                ob_start();
                imagepng($im);
                $bgData = ob_get_clean();
                imagedestroy($im);

                ob_start();
                imagepng($pieceIm);
                $pieceData = ob_get_clean();
                imagedestroy($pieceIm);

                return [
                    'bg' => 'data:image/png;base64,' . base64_encode((string) $bgData),
                    'piece' => 'data:image/png;base64,' . base64_encode((string) $pieceData),
                ];
            } catch (\Throwable $e) {
                // Proceed to guaranteed base64 SVG fallback
            }
        }

        // Guaranteed Base64-encoded SVG fallback
        $bgSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="280" height="130" viewBox="0 0 280 130"><rect width="280" height="130" fill="#0F172A"/><rect x="20" y="15" width="120" height="100" fill="#0EA5E9" fill-opacity="0.3" rx="8"/><rect x="100" y="25" width="150" height="80" fill="#10B981" fill-opacity="0.3" rx="8"/><circle cx="160" cy="65" r="45" fill="#F43F5E" fill-opacity="0.25"/><rect x="' . $targetX . '" y="' . $targetY . '" width="45" height="45" fill="#05080E" stroke="#38BDF8" stroke-width="1.5" rx="4"/><text x="18" y="115" fill="#64748B" font-family="sans-serif" font-size="12" font-weight="bold">ANTIGRAVITY OPTICAL SHIELD</text></svg>';
        $pieceSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="45" height="45" viewBox="0 0 45 45"><rect width="45" height="45" fill="#0EA5E9" stroke="#38BDF8" stroke-width="2" rx="4"/><circle cx="22" cy="22" r="12" fill="#FFFFFF" fill-opacity="0.8"/></svg>';

        return [
            'bg' => 'data:image/svg+xml;base64,' . base64_encode($bgSvg),
            'piece' => 'data:image/svg+xml;base64,' . base64_encode($pieceSvg),
        ];
    }


    /**
     * Verify a submitted challenge from a Request or input array.
     *
     * @param Request|array|null $request Incoming HTTP Request or data array
     * @param string|null $expectedName Optional specific form identifier to enforce
     * @param bool $throw When true, throws detailed CaptchaExceptions on failure
     * @return bool
     * @throws CaptchaException
     */
    public static function verify($request = null, ?string $expectedName = null, bool $throw = false): bool
    {
        $data = self::resolveInput($request);

        $token = $data['captcha_token'] ?? null;
        $solution = $data['captcha_solution'] ?? null;
        $entropy = $data['captcha_entropy'] ?? '0';
        $submittedName = $data['captcha_name'] ?? null;
        $submittedPuzzleX = $data['captcha_puzzle_x'] ?? null;

        // 1. Ensure required token and solution exist
        if (empty($token) || $solution === null || $solution === '') {
            if ($throw) throw CaptchaException::expiredOrInvalid();
            return false;
        }

        // 2. Cryptographic signature and expiration check via SignedToken
        $payload = SignedToken::verify((string) $token);
        if (!is_array($payload) || !isset($payload['n'], $payload['d'], $payload['i'])) {
            if ($throw) throw CaptchaException::expiredOrInvalid();
            return false;
        }

        // 3. Name / Scope Verification
        if ($expectedName !== null) {
            if (($payload['m'] ?? null) !== $expectedName) {
                if ($throw) throw CaptchaException::nameMismatch($expectedName, (string)($payload['m'] ?? 'none'));
                return false;
            }
        } elseif (!empty($payload['m'])) {
            if ($submittedName !== $payload['m']) {
                if ($throw) throw CaptchaException::nameMismatch($payload['m'], (string)$submittedName);
                return false;
            }
        }

        // 4. Image Puzzle Alignment Check (if puzzle mode was issued)
        if (isset($payload['px'])) {
            $targetX = (int) $payload['px'];
            $actualX = (int) ($submittedPuzzleX ?? 0);
            if (abs($actualX - $targetX) > 7) {
                if ($throw) throw CaptchaException::invalidPuzzleAlignment();
                return false;
            }
        }

        // 5. Minimum Time-to-Submit Latency Verification
        $minTime = function_exists('config') && config('captcha.min_submit_time') !== null ? (int) config('captcha.min_submit_time') : 2;
        if (PHP_SAPI !== 'cli' && $minTime > 0 && (time() - (int) $payload['i']) < $minTime) {
            if ($throw) throw CaptchaException::submissionTooFast($minTime);
            return false;
        }

        // 6. Behavioral Entropy Verification
        $verifyBehav = function_exists('config') && config('captcha.verify_behavior') !== null ? (bool) config('captcha.verify_behavior') : true;
        if ($verifyBehav && PHP_SAPI !== 'cli' && $entropy !== '1' && $entropy !== 1) {
            if ($throw) throw CaptchaException::missingBehavioralEntropy();
            return false;
        }

        // 7. Proof of Work (PoW) Hash Collision Check
        $difficulty = (int) $payload['d'];
        $target = str_repeat('0', $difficulty);
        $hash = hash('sha256', $payload['n'] . $solution);

        if (!str_starts_with($hash, $target)) {
            if ($throw) throw CaptchaException::invalidProofOfWork();
            return false;
        }

        // 8. Replay Attack Protection via Framework Cache (Burn Nonce)
        $prefix = function_exists('config') && config('captcha.cache_prefix') ? config('captcha.cache_prefix') : 'captcha:nonce:';
        $cacheKey = $prefix . $payload['n'];
        $ttl = (int) ($payload['t'] ?? 600);

        if (Cache::has($cacheKey)) {
            if ($throw) throw CaptchaException::replayDetected();
            return false;
        }

        // Burn the nonce in Cache
        Cache::put($cacheKey, true, $ttl);

        return true;
    }

    /**
     * Verify a submission or throw a descriptive CaptchaException on failure.
     *
     * @param Request|array|null $request
     * @param string|null $expectedName
     * @return void
     * @throws CaptchaException
     */
    public static function verifyOrFail($request = null, ?string $expectedName = null): void
    {
        self::verify($request, $expectedName, true);
    }

    /**
     * Server-side PoW computational solver helper (useful for tests or service-to-service auth).
     *
     * @param string $nonce
     * @param int $difficulty
     * @return int The valid counter solution
     */
    public static function solve(string $nonce, int $difficulty): int
    {
        $target = str_repeat('0', $difficulty);
        $counter = 0;
        while (true) {
            $hash = hash('sha256', $nonce . $counter);
            if (str_starts_with($hash, $target)) {
                return $counter;
            }
            $counter++;
        }
    }

    /**
     * Normalize request input into an associative data array.
     */
    protected static function resolveInput($request = null): array
    {
        if ($request instanceof Request) {
            $all = $request->all();
            return is_array($all) ? $all : [];
        }

        if (is_array($request)) {
            return $request;
        }

        // Fall back to $_POST and raw JSON stream
        $data = $_POST ?? [];
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
        return $data;
    }

    /**
     * Built-in HTTP handler for dynamic asynchronous captcha refreshing without page reload.
     */
    public static function refreshEndpoint(Request $request): \Framework\Core\Http\Response
    {
        try {
            $name = $request->input('name') ?? 'default';
            $mode = $request->input('mode') ?? 'silent';
            $theme = $request->input('theme') ?? 'dark';
            $difficulty = (int) ($request->input('difficulty') ?? 3);
            $ttl = (int) ($request->input('ttl') ?? config('captcha.expires_in', 600));

            $html = self::field($name, [
                'mode' => $mode,
                'theme' => $theme,
                'difficulty' => $difficulty,
                'ttl' => $ttl,
            ]);

            return \Framework\Core\Http\Response::json([
                'success' => true,
                'html' => $html,
                'message' => "Captcha refreshed successfully for scope '{$name}'",
            ]);
        } catch (\Exception $e) {
            return \Framework\Core\Http\Response::setStatusCode(500)->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
