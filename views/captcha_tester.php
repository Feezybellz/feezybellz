<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antigravity Captcha Studio — Enterprise Security & Anti-Bot Shield</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0B0F19;
            --surface: #111827;
            --card: rgba(30, 41, 59, 0.7);
            --border: #334155;
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --accent: #0EA5E9;
            --accent-glow: rgba(14, 165, 233, 0.25);
            --success: #10B981;
            --success-glow: rgba(16, 185, 129, 0.25);
            --danger: #F43F5E;
            --warning: #F59E0B;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 15% 20%, rgba(14, 165, 233, 0.12) 0px, transparent 50%),
                radial-gradient(at 85% 80%, rgba(16, 185, 129, 0.12) 0px, transparent 50%);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            padding: 3rem 1.5rem;
            line-height: 1.6;
        }

        .container {
            max-width: 1150px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 2.8rem;
            position: relative;
        }

        .header-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            margin-bottom: 0.6rem;
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.8rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #FFF 0%, #38BDF8 50%, #10B981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1.12rem;
            max-width: 760px;
            margin: 0 auto;
        }

        /* Mode Selection Tabs */
        .mode-tabs {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }

        .mode-tab {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 0.75rem 1.4rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-family: inherit;
        }

        .mode-tab svg {
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .mode-tab:hover {
            color: #FFF;
            border-color: #475569;
            transform: translateY(-2px);
        }

        .mode-tab:hover svg {
            color: #38BDF8;
        }

        .mode-tab.active {
            background: linear-gradient(135deg, #0EA5E9 0%, #0284C7 100%);
            color: #FFF;
            border-color: transparent;
            box-shadow: 0 4px 20px -3px var(--accent-glow);
        }

        .mode-tab.active svg {
            color: #FFF;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 880px) {
            .grid { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 1.8rem;
            backdrop-filter: blur(14px);
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            transition: border-color 0.2s;
        }

        .card:hover { border-color: #475569; }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding-bottom: 0.8rem;
            gap: 0.5rem;
        }

        .title-left {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .badge {
            font-size: 0.72rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-cyan { background: rgba(14, 165, 233, 0.15); color: #38BDF8; border: 1px solid rgba(14, 165, 233, 0.4); }
        .badge-green { background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .badge-amber { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.4); }

        .telemetry-item {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.92rem;
        }

        .telemetry-label { color: var(--text-muted); }
        .telemetry-value { font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #60A5FA; display: flex; align-items: center; gap: 6px; }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        label { font-size: 0.86rem; font-weight: 600; color: #CBD5E1; }

        input[type="text"], input[type="email"], select {
            background: #0B0F19;
            border: 1px solid var(--border);
            color: #FFF;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus, input[type="email"]:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .btn {
            background: #1E293B;
            color: #FFF;
            border: 1px solid #475569;
            padding: 0.75rem 1.2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-family: inherit;
            text-decoration: none;
        }

        .btn svg { flex-shrink: 0; }
        .btn:hover { background: #334155; transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }

        .btn-primary {
            background: linear-gradient(135deg, #0EA5E9 0%, #0284C7 100%);
            border: none;
            box-shadow: 0 4px 15px -3px var(--accent-glow);
        }
        .btn-primary:hover { background: linear-gradient(135deg, #38BDF8 0%, #0369A1 100%); }

        .btn-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border: none;
            box-shadow: 0 4px 15px -3px var(--success-glow);
        }
        .btn-success:hover { background: linear-gradient(135deg, #34D399 0%, #047857 100%); }

        .btn-danger {
            background: rgba(244, 63, 94, 0.15);
            color: #FB7185;
            border: 1px solid rgba(244, 63, 94, 0.4);
        }
        .btn-danger:hover { background: rgba(244, 63, 94, 0.25); }

        .terminal {
            background: #05080E;
            border: 1px solid #1E293B;
            border-radius: 16px;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.86rem;
            color: #38BDF8;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .status-dot {
            height: 9px;
            width: 9px;
            background-color: #64748B;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-pulse-green {
            background-color: #10B981;
            box-shadow: 0 0 10px #10B981;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        #widget-container {
            background: rgba(11, 15, 25, 0.6);
            border: 1px dashed var(--border);
            border-radius: 16px;
            padding: 1.2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 110px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-brand">
                <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                <h1>Antigravity Captcha Studio</h1>
            </div>
            <p class="subtitle">Experience an enterprise-grade anti-bot security shield. Seamlessly toggle between silent background processing and intuitive interactive verification widgets.</p>
        </header>

        <!-- Mode Selector Tabs with Professional SVGs -->
        <div class="mode-tabs">
            <button class="mode-tab active" id="tab-silent" onclick="switchMode('silent', this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/></svg>
                <span>Invisible Silent Mode</span>
            </button>
            <button class="mode-tab" id="tab-slider" onclick="switchMode('slider', this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><circle cx="12" cy="12" r="3"/><polyline points="8 8 4 12 8 16"/><polyline points="16 8 20 12 16 16"/></svg>
                <span>Slide to Verify</span>
            </button>
            <button class="mode-tab" id="tab-turnstile" onclick="switchMode('turnstile', this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="4" ry="4"/><path d="m9 12 2 2 4-4"/></svg>
                <span>Turnstile Checkpoint</span>
            </button>
            <button class="mode-tab" id="tab-puzzle" onclick="switchMode('puzzle', this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 12.5 21 11V8a2 2 0 0 0-2-2h-3l-1.5 1.5a1.5 1.5 0 0 1-2.12 0L11 6H8a2 2 0 0 0-2 2v3l1.5 1.5a1.5 1.5 0 0 1 0 2.12L6 16v3a2 2 0 0 0 2 2h3l1.5-1.5a1.5 1.5 0 0 1 2.12 0L16 21h3a2 2 0 0 0 2-2v-3l-1.5-1.5a1.5 1.5 0 0 1 0-2.12Z"/></svg>
                <span>Visual Image Puzzle</span>
            </button>
        </div>

        <div class="grid">
            <!-- Left Column: Security Telemetry -->
            <div class="card">
                <div class="card-title">
                    <div class="title-left">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        <span>Live Security Telemetry</span>
                    </div>
                    <span id="pow-badge" class="badge badge-cyan">Mode: Silent</span>
                </div>
                
                <div class="form-group">
                    <label>PoW Difficulty Target (SHA-256 Leading Zeros)</label>
                    <div style="display: flex; gap: 0.6rem; margin-bottom: 0.8rem;">
                        <select id="select-difficulty" style="flex-grow: 1;" onchange="refreshCurrentMode()">
                            <option value="2">Level 2 (Fast ~15ms — Light)</option>
                            <option value="3" selected>Level 3 (Default ~80ms — Balanced)</option>
                            <option value="4">Level 4 (Strong ~800ms — High Security)</option>
                            <option value="5">Level 5 (Extreme ~8s — Stress Test)</option>
                        </select>
                        <button class="btn btn-primary" onclick="refreshCurrentMode()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                            <span>Reload</span>
                        </button>
                    </div>
                    <label>Token Challenge Expiry Window (TTL)</label>
                    <select id="select-ttl" style="width: 100%;" onchange="refreshCurrentMode()">
                        <option value="10">10 Seconds (Quick Live Expiry Test)</option>
                        <option value="30">30 Seconds (Short Test)</option>
                        <option value="600" selected>10 Minutes (Standard Production TTL)</option>
                    </select>
                </div>

                <div class="telemetry-item">
                    <span class="telemetry-label">Active Engine Mode</span>
                    <span class="telemetry-value" id="disp-mode" style="color:#10B981; text-transform:uppercase;">SILENT</span>
                </div>

                <div class="telemetry-item">
                    <span class="telemetry-label">Cryptographic Nonce</span>
                    <span class="telemetry-value" id="disp-nonce">---</span>
                </div>

                <div class="telemetry-item">
                    <span class="telemetry-label">PoW Computation State</span>
                    <span class="telemetry-value" id="disp-sol-status" style="color: #F59E0B;">Awaiting action...</span>
                </div>

                <div class="telemetry-item">
                    <span class="telemetry-label">Behavioral Entropy Shield</span>
                    <span id="disp-entropy" class="telemetry-value" style="color:#94A3B8;">
                        <span class="status-dot"></span>
                        <span>Waiting for interaction...</span>
                    </span>
                </div>
            </div>

            <!-- Right Column: Interactive Form Simulator -->
            <div class="card">
                <div class="card-title">
                    <div class="title-left">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                        <span>Interactive Form Simulator</span>
                    </div>
                    <span class="badge badge-cyan">Scope: demo_form</span>
                </div>

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="form_name" value="Alstroph Architect">
                </div>
                
                <div class="form-group" style="margin-top: 0.2rem;">
                    <label>Email Address</label>
                    <input type="email" id="form_email" value="developer@framework.net.ng">
                </div>

                <label style="margin-top: 0.6rem; display:block;">Live Rendered Captcha Challenge:</label>
                <div id="widget-container">
                    <span style="color:var(--text-muted); display:flex; align-items:center; gap:8px;">
                        <svg style="animation: cpt_spin_loader 0.8s linear infinite;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                        Loading dynamic widget from server...
                    </span>
                    <style>@keyframes cpt_spin_loader { 100% { transform: rotate(360deg); } }</style>
                </div>

                <div class="btn-group">
                    <button class="btn btn-primary" onclick="submitTest('middleware')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>Submit to Route Middleware (captcha:demo_form)</span>
                    </button>
                    <button class="btn btn-success" onclick="submitTest('manual')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                        <span>Submit to Manual Controller Verification</span>
                    </button>
                    <button class="btn btn-danger" onclick="submitReplayAttack()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
                        <span>Simulate Replay Attack (Resend consumed nonce)</span>
                    </button>
                    <button class="btn" style="background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.4);" onclick="simulateExpiry()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Simulate Token Expiry (Test Tap-to-Retry)</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Terminal Diagnostics Card -->
        <div class="card" style="padding: 1.5rem;">
            <div class="card-title" style="margin-bottom: 0;">
                <div class="title-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>
                    <span>Real-Time Security Diagnostics Log</span>
                </div>
                <button class="btn" style="padding: 0.35rem 0.85rem; font-size: 0.8rem;" onclick="clearLog()">Clear Log</button>
            </div>
            <div class="terminal" id="terminal-log">--- System initialized. Select an engine mode above to test interactive verification shields ---</div>
        </div>
    </div>

    <script>
        var currentMode = 'silent';
        var hasInteracted = false;

        function registerEntropy() {
            if (!hasInteracted) {
                hasInteracted = true;
                var el = document.getElementById('disp-entropy');
                el.innerHTML = '<span class="status-dot dot-pulse-green"></span><span>Verified Human Interaction</span>';
                el.style.color = "#10B981";
                log("Passive human entropy verified (DOM interaction event registered).", "info");
            }
        }
        window.addEventListener('pointermove', registerEntropy);
        window.addEventListener('keydown', registerEntropy);
        window.addEventListener('focus', registerEntropy);
        window.addEventListener('touchstart', registerEntropy);

        function log(msg, type = 'info') {
            var term = document.getElementById('terminal-log');
            var timestamp = new Date().toLocaleTimeString();
            var color = type === 'error' ? '#F43F5E' : (type === 'success' ? '#10B981' : '#38BDF8');
            var prefix = type === 'error' ? '[DENIED]' : (type === 'success' ? '[SUCCESS]' : '[TELEMETRY]');
            term.innerHTML += `<div style="color: ${color}; margin-top: 5px;">[${timestamp}] ${prefix} ${msg}</div>`;
            term.scrollTop = term.scrollHeight;
        }

        function clearLog() {
            document.getElementById('terminal-log').innerHTML = "--- Diagnostics cleared ---";
        }

        function switchMode(mode, btn) {
            currentMode = mode;
            document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
            if (btn) btn.classList.add('active');
            
            document.getElementById('pow-badge').innerText = "Mode: " + mode;
            document.getElementById('pow-badge').className = "badge badge-" + (mode === 'silent' ? 'cyan' : (mode === 'puzzle' ? 'amber' : 'green'));
            document.getElementById('disp-mode').innerText = mode.toUpperCase();
            
            refreshCurrentMode();
        }

        function refreshCurrentMode() {
            var diff = document.getElementById('select-difficulty').value;
            var ttlEl = document.getElementById('select-ttl');
            var ttlVal = ttlEl ? ttlEl.value : 600;
            var container = document.getElementById('widget-container');
            container.innerHTML = `<span style="color:var(--text-muted); display:flex; align-items:center; gap:8px;"><svg style="animation: cpt_spin_loader 0.8s linear infinite;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>Synthesizing and rendering ${currentMode} widget...</span>`;
            document.getElementById('disp-sol-status').innerText = "Generating...";
            document.getElementById('disp-sol-status').style.color = "#F59E0B";

            log(`Fetching challenge field for mode '${currentMode}' (Difficulty Target: Level ${diff}, TTL: ${ttlVal}s)...`, "info");

            fetch(`/captcha-tester/render?mode=${currentMode}&difficulty=${diff}&theme=dark&ttl=${ttlVal}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.html) {
                        var htmlContent = data.html.replace(/<script>([\s\S]*?)<\/script>/, "");
                        var match = data.html.match(/<script>([\s\S]*?)<\/script>/);
                        
                        container.innerHTML = htmlContent;
                        
                        if (match && match[1]) {
                            var scriptEl = document.createElement('script');
                            scriptEl.textContent = match[1];
                            document.body.appendChild(scriptEl);
                        }

                        setTimeout(() => {
                            var tok = document.querySelector('#widget-container input[name="captcha_token"]');
                            if (tok) {
                                document.getElementById('disp-nonce').innerText = "Token Loaded & Active";
                                log(`Rendered '${currentMode}' widget successfully. Challenge ticket live.`, "info");
                            }
                            
                            var sol = document.querySelector('#widget-container input[name="captcha_solution"]');
                            if (sol) {
                                var checkSol = setInterval(() => {
                                    if (sol && sol.value !== '') {
                                        document.getElementById('disp-sol-status').innerText = "PoW Resolved: " + sol.value;
                                        document.getElementById('disp-sol-status').style.color = "#10B981";
                                        log(`Proof-of-Work solved! Valid counter solution: ${sol.value}`, "success");
                                        clearInterval(checkSol);
                                    }
                                }, 200);
                            }
                        }, 50);

                    } else {
                        container.innerHTML = `<span style="color:#F43F5E;">Error loading widget: ${data.error || 'unknown'}</span>`;
                        log("Failed to render field: " + (data.error || "unknown"), "error");
                    }
                })
                .catch(err => {
                    container.innerHTML = `<span style="color:#F43F5E;">Network exception loading widget</span>`;
                    log("Network exception: " + err.message, "error");
                });
        }

        async function getPayloadFromWidget() {
            var tok = document.querySelector('#widget-container input[name="captcha_token"]');
            var sol = document.querySelector('#widget-container input[name="captcha_solution"]');
            var ent = document.querySelector('#widget-container input[name="captcha_entropy"]');
            var nm  = document.querySelector('#widget-container input[name="captcha_name"]');
            var pzx = document.querySelector('#widget-container input[name="captcha_puzzle_x"]');

            return {
                name: document.getElementById('form_name').value,
                email: document.getElementById('form_email').value,
                captcha_token: tok ? tok.value : '',
                captcha_solution: sol ? sol.value : '',
                captcha_entropy: (ent && ent.value !== '0') ? ent.value : (hasInteracted ? '1' : '0'),
                captcha_name: nm ? nm.value : 'demo_form',
                captcha_puzzle_x: pzx ? pzx.value : '0'
            };
        }

        async function sendPayload(endpoint, title) {
            var payload = await getPayloadFromWidget();
            log(`[${title}] Submitting verification payload -> ${endpoint}...`, "info");

            try {
                var resp = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                var res = await resp.json();
                
                if (resp.status === 200 && res.success) {
                    log(`${res.message}`, "success");
                } else {
                    log(`${res.error || 'Security Rejection'}: ${res.message}`, "error");
                    window.dispatchEvent(new CustomEvent('antigravity:captcha-error', { detail: { message: res.message || res.error || "Verification rejected" } }));
                }
            } catch(e) {
                log(`Network error: ${e.message}`, "error");
                window.dispatchEvent(new CustomEvent('antigravity:captcha-error', { detail: { message: e.message || "Network communication error" } }));
            }
        }

        function submitTest(mode) {
            var endpoint = mode === 'manual' ? '/captcha-tester/verify-manual' : '/captcha-tester/verify-middleware';
            sendPayload(endpoint, mode === 'manual' ? "Manual Verify" : "Route Middleware");
        }

        function submitReplayAttack() {
            log("Testing Replay Protection: Attempting to submit exact same consumed challenge token again without refreshing...", "info");
            sendPayload('/captcha-tester/verify-middleware', "Replay Attack Test");
        }

        function simulateExpiry() {
            log("Simulating challenge token expiration (time window elapsed)...", "info");
            window.dispatchEvent(new Event('antigravity:captcha-expire'));
        }

        window.addEventListener('load', () => refreshCurrentMode());
    </script>
</body>
</html>
