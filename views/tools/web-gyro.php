<?php 
$title = "Gyroscope & Motion Sensor Tester"; 
include __DIR__ . '/partials/header.php'; 
?>
<style>
    .gyro-container {
        max-width: 900px;
        margin: 1.5rem auto 3rem;
        padding: 0 1rem;
    }

    .gyro-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .gyro-header h1 {
        font-size: 2.25rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        letter-spacing: -0.025em;
        background: linear-gradient(135deg, var(--text-main) 30%, var(--primary-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .gyro-header p {
        color: var(--text-muted);
        font-size: 1rem;
        max-width: 580px;
        margin: 0 auto;
    }

    /* Diagnostics Badge Bar */
    .status-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
        margin-bottom: 1.5rem;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        color: var(--text-sub);
    }

    .badge-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--text-muted);
    }

    .badge-dot.active {
        background: #10b981;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
    }

    .badge-dot.warning {
        background: #f59e0b;
        box-shadow: 0 0 8px rgba(245, 158, 11, 0.6);
    }

    .badge-dot.danger {
        background: #ef4444;
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
    }

    /* Action Card */
    .control-card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 1.25rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
    }

    .btn-action-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        justify-content: center;
    }

    .btn-sensor {
        background: var(--primary-color);
        color: var(--primary-text);
        border: none;
        padding: 0.85rem 1.75rem;
        font-size: 1rem;
        font-weight: 700;
        border-radius: 0.75rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: transform 0.15s ease, opacity 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 4px 14px rgba(0, 245, 155, 0.25);
    }

    .btn-sensor:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 245, 155, 0.4);
    }

    .btn-sensor:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: var(--bg-color);
        color: var(--text-main);
        border: 1px solid var(--border-color);
        padding: 0.85rem 1.25rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-radius: 0.75rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .btn-secondary:hover:not(:disabled) {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .control-status-text {
        margin-top: 1rem;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    /* Insecure Warning Box */
    .alert-insecure {
        display: none;
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-radius: 1rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        color: var(--text-main);
        text-align: left;
    }

    .alert-insecure h4 {
        color: #f59e0b;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .alert-insecure p, .alert-insecure ul {
        font-size: 0.875rem;
        color: var(--text-sub);
        line-height: 1.5;
    }

    .alert-insecure ul {
        margin-top: 0.5rem;
        padding-left: 1.25rem;
    }

    /* Grid Layout for Testers */
    .grid-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    @media (min-width: 820px) {
        .grid-layout {
            grid-template-columns: 1.15fr 0.85fr;
        }
    }

    .card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-main);
    }

    .card-title .tag {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        padding: 0.2rem 0.6rem;
        border-radius: 0.5rem;
        color: var(--text-muted);
        font-weight: 600;
    }

    /* 3D Visualizer Stage */
    .stage-wrapper {
        position: relative;
        width: 100%;
        height: 280px;
        background: radial-gradient(circle at center, rgba(0, 245, 155, 0.05) 0%, transparent 70%), var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        perspective: 800px;
        overflow: hidden;
    }

    .grid-lines {
        position: absolute;
        inset: 0;
        background-size: 24px 24px;
        background-image: 
            linear-gradient(to right, var(--border-color) 1px, transparent 1px),
            linear-gradient(to bottom, var(--border-color) 1px, transparent 1px);
        opacity: 0.4;
        pointer-events: none;
    }

    /* 3D Phone Model */
    #phone-pivot {
        width: 120px;
        height: 220px;
        transform-style: preserve-3d;
        transition: transform 50ms linear;
        position: relative;
    }

    .phone-body {
        width: 100%;
        height: 100%;
        background: #111827;
        border: 5px solid #374151;
        border-radius: 26px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), inset 0 0 4px rgba(255, 255, 255, 0.2);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 10px 8px;
        position: relative;
        overflow: hidden;
    }

    .phone-notch {
        width: 36px;
        height: 6px;
        background: #1f2937;
        border-radius: 4px;
        margin-top: 2px;
        z-index: 2;
    }

    .phone-screen {
        width: 100%;
        flex-grow: 1;
        background: #0f172a;
        border-radius: 16px;
        margin: 6px 0;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255,255,255,0.06);
    }

    .phone-horizon {
        position: absolute;
        width: 200%;
        height: 200%;
        top: -50%;
        left: -50%;
        background: linear-gradient(to bottom, #1e3a8a 50%, #065f46 50%);
        opacity: 0.45;
        transition: transform 60ms linear;
        pointer-events: none;
    }

    .phone-bubble {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #00f59b;
        box-shadow: 0 0 10px #00f59b;
        position: absolute;
        transition: transform 60ms linear;
        z-index: 2;
    }

    .phone-screen-text {
        position: relative;
        z-index: 3;
        font-size: 11px;
        font-weight: 700;
        color: #f8fafc;
        text-align: center;
        text-shadow: 0 1px 3px rgba(0,0,0,0.8);
        pointer-events: none;
    }

    .phone-home-bar {
        width: 44px;
        height: 4px;
        background: #4b5563;
        border-radius: 2px;
        margin-bottom: 2px;
        z-index: 2;
    }

    /* Metric Value Badges */
    .metric-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .metric-box {
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 0.85rem;
        padding: 0.85rem 0.5rem;
        text-align: center;
    }

    .metric-box.active-axis {
        border-color: var(--primary-color);
    }

    .metric-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }

    .metric-val {
        display: block;
        font-family: 'Fira Code', monospace;
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--text-main);
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }

    .metric-unit {
        display: block;
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 0.2rem;
    }

    /* Waveform Canvas */
    .canvas-container {
        position: relative;
        width: 100%;
        height: 160px;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        overflow: hidden;
        margin-top: 1rem;
    }

    #gyroCanvas {
        width: 100%;
        height: 100%;
        display: block;
    }

    .canvas-legend {
        position: absolute;
        top: 8px;
        right: 10px;
        display: flex;
        gap: 8px;
        font-size: 0.72rem;
        font-weight: 600;
        font-family: monospace;
        background: rgba(0, 0, 0, 0.4);
        padding: 4px 8px;
        border-radius: 6px;
        backdrop-filter: blur(4px);
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .legend-color {
        width: 8px;
        height: 8px;
        border-radius: 2px;
    }

    /* Bubble Level Crosshair Container */
    .bubble-level-wrap {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: var(--bg-color);
        border: 2px solid var(--border-color);
        position: relative;
        margin: 0 auto;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bubble-target-outer {
        width: 80px;
        height: 80px;
        border: 1px dashed var(--border-color);
        border-radius: 50%;
        position: absolute;
    }

    .bubble-target-center {
        width: 24px;
        height: 24px;
        border: 2px solid var(--border-color);
        border-radius: 50%;
        position: absolute;
    }

    .bubble-cross-h, .bubble-cross-v {
        position: absolute;
        background: var(--border-color);
    }

    .bubble-cross-h {
        width: 100%;
        height: 1px;
    }

    .bubble-cross-v {
        width: 1px;
        height: 100%;
    }

    .bubble-dot-float {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.6);
        position: absolute;
        transform: translate(0px, 0px);
        transition: transform 40ms linear, background 0.2s ease;
    }

    .bubble-dot-float.leveled {
        background: #00f59b;
        box-shadow: 0 0 16px #00f59b;
    }

    /* Diagnostics Table */
    .diag-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        margin-top: 0.5rem;
    }

    .diag-table tr {
        border-bottom: 1px solid var(--border-color);
    }

    .diag-table tr:last-child {
        border-bottom: none;
    }

    .diag-table td {
        padding: 0.6rem 0.25rem;
    }

    .diag-table td:first-child {
        color: var(--text-muted);
        font-weight: 500;
    }

    .diag-table td:last-child {
        text-align: right;
        font-family: 'Fira Code', monospace;
        font-weight: 600;
        color: var(--text-main);
    }

    .tag-ok {
        color: #10b981 !important;
    }
    .tag-warn {
        color: #f59e0b !important;
    }
    .tag-err {
        color: #ef4444 !important;
    }
</style>

<div class="gyro-container">
    <!-- Header -->
    <div class="gyro-header">
        <h1>Gyroscope & Motion Tester</h1>
        <p>Test real-time angular velocity, device orientation angles, tilt axes, and acceleration from your mobile device.</p>
    </div>

    <!-- Status Badges -->
    <div class="status-bar">
        <div class="badge" id="badge-secure">
            <span class="badge-dot" id="dot-secure"></span>
            <span id="text-secure">Checking Protocol...</span>
        </div>
        <div class="badge" id="badge-sensor">
            <span class="badge-dot" id="dot-sensor"></span>
            <span id="text-sensor">Sensors Inactive</span>
        </div>
        <div class="badge" id="badge-rate">
            <span class="badge-dot active"></span>
            <span id="text-rate">0.0 Hz</span>
        </div>
        <div class="badge" id="badge-wakelock" style="display:none;">
            <span class="badge-dot active"></span>
            <span>Screen Wake Lock: Active</span>
        </div>
    </div>

    <!-- Insecure Context Warning (shown if not HTTPS or localhost) -->
    <div class="alert-insecure" id="alertInsecure">
        <h4><i data-lucide="shield-alert"></i> Insecure Context Detected (HTTP)</h4>
        <p>Modern mobile browsers (Chrome, Safari, Firefox) restrict motion & gyroscope APIs strictly to <strong>HTTPS</strong> or <strong>localhost</strong>.</p>
        <ul>
            <li>Access via your HTTPS domain: <code>https://feezybellz.net.ng/tools/web-gyro</code></li>
            <li>Or test over USB with Chrome Remote Debugging port forwarding to <code>localhost</code>.</li>
            <li>Or enable "Simulate Motion" below to preview all features on desktop.</li>
        </ul>
    </div>

    <!-- Controls Card -->
    <div class="control-card">
        <div class="btn-action-group">
            <button class="btn-sensor" id="btnStart">
                <i data-lucide="play" id="btnStartIcon"></i>
                <span id="btnStartText">Enable Motion Sensors</span>
            </button>
            <button class="btn-secondary" id="btnTare" disabled title="Set current phone position as zero">
                <i data-lucide="crosshair"></i>
                <span>Tare / Zero Position</span>
            </button>
            <button class="btn-secondary" id="btnSimulate">
                <i data-lucide="mouse-pointer"></i>
                <span id="btnSimText">Simulate Motion (Preview)</span>
            </button>
            <button class="btn-secondary" id="btnHaptic" title="Test vibration motor">
                <i data-lucide="vibrate"></i>
                <span>Test Haptic</span>
            </button>
        </div>
        <p class="control-status-text" id="statusMsg">Tap "Enable Motion Sensors" to request permissions and start reading data.</p>
    </div>

    <!-- Main Grid: 3D Visualizer & Orientation -->
    <div class="grid-layout">
        <!-- 3D Phone Model Card -->
        <div class="card">
            <div class="card-title">
                <span><i data-lucide="smartphone"></i> Live 3D Device Attitude</span>
                <span class="tag" id="modeTag">Euler 3D</span>
            </div>

            <div class="stage-wrapper" id="stageWrapper">
                <div class="grid-lines"></div>
                <div id="phone-pivot">
                    <div class="phone-body">
                        <div class="phone-notch"></div>
                        <div class="phone-screen">
                            <div class="phone-horizon" id="phoneHorizon"></div>
                            <div class="phone-bubble" id="phoneBubble"></div>
                            <div class="phone-screen-text">
                                <span id="screenAngle">0°</span><br>
                                <small id="screenMode">FLAT</small>
                            </div>
                        </div>
                        <div class="phone-home-bar"></div>
                    </div>
                </div>
            </div>

            <!-- Orientation Values -->
            <div class="metric-grid">
                <div class="metric-box">
                    <span class="metric-label">Alpha (Yaw • Z)</span>
                    <span class="metric-val" id="valAlpha">0.0°</span>
                    <span class="metric-unit">Compass (0° to 360°)</span>
                </div>
                <div class="metric-box">
                    <span class="metric-label">Beta (Pitch • X)</span>
                    <span class="metric-val" id="valBeta">0.0°</span>
                    <span class="metric-unit">Front/Back (-180°..180°)</span>
                </div>
                <div class="metric-box">
                    <span class="metric-label">Gamma (Roll • Y)</span>
                    <span class="metric-val" id="valGamma">0.0°</span>
                    <span class="metric-unit">Left/Right (-90°..90°)</span>
                </div>
            </div>
        </div>

        <!-- Inclinometer & Level Card -->
        <div class="card">
            <div class="card-title">
                <span><i data-lucide="compass"></i> Inclinometer & Level</span>
                <span class="tag" id="levelState">UNLEVEL</span>
            </div>

            <div class="bubble-level-wrap">
                <div class="bubble-cross-h"></div>
                <div class="bubble-cross-v"></div>
                <div class="bubble-target-outer"></div>
                <div class="bubble-target-center"></div>
                <div class="bubble-dot-float" id="bubbleFloat"></div>
            </div>

            <div style="text-align:center; margin-top: 1rem;">
                <p style="font-size:0.85rem; color:var(--text-sub); margin-bottom: 0.25rem;">Total Tilt Angle</p>
                <h2 style="font-family:'Fira Code', monospace; font-size:1.75rem; color:var(--text-main);" id="valTotalTilt">0.0°</h2>
                <span style="font-size:0.75rem; color:var(--text-muted);" id="tiltDirection">Device is flat</span>
            </div>
        </div>
    </div>

    <!-- Gyroscope Angular Velocity & Waveform Grid -->
    <div class="grid-layout">
        <!-- Raw Gyroscope Card -->
        <div class="card">
            <div class="card-title">
                <span><i data-lucide="activity"></i> Raw Gyroscope (Angular Velocity)</span>
                <span class="tag" id="gyroSourceTag">Standby</span>
            </div>

            <!-- Waveform Canvas -->
            <div class="canvas-container">
                <canvas id="gyroCanvas" width="600" height="160"></canvas>
                <div class="canvas-legend">
                    <div class="legend-item"><span class="legend-color" style="background:#f43f5e;"></span> X (Pitch)</div>
                    <div class="legend-item"><span class="legend-color" style="background:#06b6d4;"></span> Y (Roll)</div>
                    <div class="legend-item"><span class="legend-color" style="background:#10b981;"></span> Z (Yaw)</div>
                </div>
            </div>

            <!-- Gyro Metric Grid (deg/s and rad/s) -->
            <div class="metric-grid">
                <div class="metric-box">
                    <span class="metric-label">Rot X (ωx)</span>
                    <span class="metric-val" id="valRotX">0.0</span>
                    <span class="metric-unit">°/s <span id="valRotXRad">(0.0 rad/s)</span></span>
                </div>
                <div class="metric-box">
                    <span class="metric-label">Rot Y (ωy)</span>
                    <span class="metric-val" id="valRotY">0.0</span>
                    <span class="metric-unit">°/s <span id="valRotYRad">(0.0 rad/s)</span></span>
                </div>
                <div class="metric-box">
                    <span class="metric-label">Rot Z (ωz)</span>
                    <span class="metric-val" id="valRotZ">0.0</span>
                    <span class="metric-unit">°/s <span id="valRotZRad">(0.0 rad/s)</span></span>
                </div>
            </div>

            <p style="font-size:0.78rem; color:var(--text-muted); margin-top:0.75rem;" id="gyroStatusDetail">
                Waiting for sensor data...
            </p>
        </div>

        <!-- Linear Acceleration & System Diagnostics -->
        <div class="card">
            <div class="card-title">
                <span><i data-lucide="cpu"></i> Acceleration & Diagnostics</span>
                <span class="tag">System</span>
            </div>

            <!-- Acceleration metrics -->
            <div class="metric-grid" style="margin-top: 0; margin-bottom: 1rem;">
                <div class="metric-box">
                    <span class="metric-label">Accel X</span>
                    <span class="metric-val" id="valAccX">0.00</span>
                    <span class="metric-unit">m/s²</span>
                </div>
                <div class="metric-box">
                    <span class="metric-label">Accel Y</span>
                    <span class="metric-val" id="valAccY">0.00</span>
                    <span class="metric-unit">m/s²</span>
                </div>
                <div class="metric-box">
                    <span class="metric-label">Accel Z</span>
                    <span class="metric-val" id="valAccZ">0.00</span>
                    <span class="metric-unit">m/s²</span>
                </div>
            </div>

            <table class="diag-table">
                <tr>
                    <td>Secure Context (HTTPS)</td>
                    <td id="diagSecure">Checking...</td>
                </tr>
                <tr>
                    <td>DeviceOrientation API</td>
                    <td id="diagOrientation">Checking...</td>
                </tr>
                <tr>
                    <td>DeviceMotion API</td>
                    <td id="diagMotion">Checking...</td>
                </tr>
                <tr>
                    <td>W3C Generic Gyroscope</td>
                    <td id="diagGenericGyro">Checking...</td>
                </tr>
                <tr>
                    <td>Active Sensor Engine</td>
                    <td id="diagActiveEngine">None</td>
                </tr>
                <tr>
                    <td>Total G-Force (inc. gravity)</td>
                    <td id="diagGForce">1.00 g</td>
                </tr>
                <tr>
                    <td>Peak Angular Velocity</td>
                    <td id="diagPeakRot">0.0 °/s</td>
                </tr>
            </table>
        </div>
    </div>
</div>

<script>
    // State management
    const state = {
        active: false,
        simulating: false,
        engine: 'none', // 'generic-gyro' or 'devicemotion' or 'orientation' or 'simulation'
        alpha: 0,
        beta: 0,
        gamma: 0,
        tareAlpha: 0,
        tareBeta: 0,
        tareGamma: 0,
        rotX: 0,
        rotY: 0,
        rotZ: 0,
        accX: 0,
        accY: 0,
        accZ: 0,
        gForce: 1.0,
        peakRot: 0,
        eventCount: 0,
        lastFpsTime: performance.now(),
        fps: 0,
        wakeLock: null,
        historyX: new Array(60).fill(0),
        historyY: new Array(60).fill(0),
        historyZ: new Array(60).fill(0)
    };

    const $ = id => document.getElementById(id);

    // Initial Security & API Diagnostics Check
    function checkDiagnostics() {
        const isSecure = window.isSecureContext;
        const hasOrientation = 'DeviceOrientationEvent' in window;
        const hasMotion = 'DeviceMotionEvent' in window;
        const hasGenericGyro = 'Gyroscope' in window;

        // Badge update
        if (isSecure) {
            $('dot-secure').className = 'badge-dot active';
            $('text-secure').textContent = 'Secure Context (HTTPS)';
            $('diagSecure').innerHTML = '<span class="tag-ok">YES (Secure)</span>';
            $('alertInsecure').style.display = 'none';
        } else {
            $('dot-secure').className = 'badge-dot danger';
            $('text-secure').textContent = 'Insecure Context (HTTP)';
            $('diagSecure').innerHTML = '<span class="tag-err">NO (Insecure)</span>';
            $('alertInsecure').style.display = 'block';
        }

        $('diagOrientation').innerHTML = hasOrientation 
            ? '<span class="tag-ok">Supported</span>' 
            : '<span class="tag-warn">Not Supported</span>';

        $('diagMotion').innerHTML = hasMotion 
            ? '<span class="tag-ok">Supported</span>' 
            : '<span class="tag-warn">Not Supported</span>';

        $('diagGenericGyro').innerHTML = hasGenericGyro 
            ? '<span class="tag-ok">Supported</span>' 
            : '<span class="tag-muted">Fallback to Motion</span>';
    }

    // Tare / Zero offset
    function tareSensors() {
        state.tareAlpha = state.alpha;
        state.tareBeta = state.beta;
        state.tareGamma = state.gamma;
        $('statusMsg').textContent = 'Current orientation calibrated as zero reference point.';
    }

    // Wake Lock to keep phone screen alive during sensor test
    async function requestWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                state.wakeLock = await navigator.wakeLock.request('screen');
                $('badge-wakelock').style.display = 'inline-flex';
                state.wakeLock.addEventListener('release', () => {
                    $('badge-wakelock').style.display = 'none';
                });
            }
        } catch (err) {
            console.warn('Wake Lock error:', err);
        }
    }

    // Orientation event listener
    function handleOrientation(e) {
        if (!state.active && !state.simulating) return;

        state.eventCount++;

        // Raw angles
        const rawAlpha = e.alpha || 0;
        const rawBeta = e.beta || 0;
        const rawGamma = e.gamma || 0;

        state.alpha = rawAlpha;
        state.beta = rawBeta;
        state.gamma = rawGamma;

        // Apply tare calibration
        let relBeta = rawBeta - state.tareBeta;
        let relGamma = rawGamma - state.tareGamma;
        let relAlpha = (rawAlpha - state.tareAlpha + 360) % 360;

        // Display formatted numbers
        $('valAlpha').textContent = rawAlpha.toFixed(1) + '°';
        $('valBeta').textContent = rawBeta.toFixed(1) + '°';
        $('valGamma').textContent = rawGamma.toFixed(1) + '°';

        // 3D Phone Transform
        // In phone coordinate system:
        // beta is pitch (tilt front/back), gamma is roll (tilt left/right)
        const clampPitch = Math.max(-90, Math.min(90, relBeta));
        const clampRoll = Math.max(-90, Math.min(90, relGamma));
        
        $('phone-pivot').style.transform = `rotateX(${-clampPitch}deg) rotateY(${clampRoll}deg)`;

        // Inclinometer / Bubble Level target
        // Outer radius 70px max displacement
        const maxRange = 45; // 45 deg max travel
        const bubbleX = Math.max(-70, Math.min(70, (relGamma / maxRange) * 70));
        const bubbleY = Math.max(-70, Math.min(70, (relBeta / maxRange) * 70));

        $('bubbleFloat').style.transform = `translate(${bubbleX}px, ${bubbleY}px)`;
        $('phoneBubble').style.transform = `translate(${Math.max(-40, Math.min(40, (relGamma / maxRange) * 40))}px, ${Math.max(-40, Math.min(40, (relBeta / maxRange) * 40))}px)`;
        $('phoneHorizon').style.transform = `rotate(${-relGamma}deg) translateY(${relBeta * 1.5}px)`;

        // Total tilt angle = sqrt(beta^2 + gamma^2)
        const totalTilt = Math.sqrt(relBeta * relBeta + relGamma * relGamma);
        $('valTotalTilt').textContent = totalTilt.toFixed(1) + '°';
        $('screenAngle').textContent = totalTilt.toFixed(0) + '°';

        if (totalTilt <= 1.5) {
            $('levelState').textContent = 'PERFECT LEVEL';
            $('levelState').style.color = '#00f59b';
            $('bubbleFloat').classList.add('leveled');
            $('tiltDirection').textContent = 'Device is perfectly horizontal';
            $('screenMode').textContent = 'LEVEL';
        } else {
            $('levelState').textContent = 'TILTED';
            $('levelState').style.color = 'var(--text-muted)';
            $('bubbleFloat').classList.remove('leveled');
            
            let dir = [];
            if (relBeta > 2) dir.push('Back');
            if (relBeta < -2) dir.push('Forward');
            if (relGamma > 2) dir.push('Right');
            if (relGamma < -2) dir.push('Left');
            $('tiltDirection').textContent = 'Tilted ' + (dir.join(' + ') || 'slightly');
            $('screenMode').textContent = 'TILTED';
        }
    }

    // Motion event listener (Raw Gyro rotationRate + Accelerometer)
    function handleMotion(e) {
        if (!state.active && !state.simulating) return;

        state.eventCount++;

        // Rotation rate (Gyroscope)
        if (e.rotationRate) {
            const rx = e.rotationRate.beta || 0;  // pitch rate (°/s)
            const ry = e.rotationRate.gamma || 0; // roll rate (°/s)
            const rz = e.rotationRate.alpha || 0; // yaw rate (°/s)

            state.rotX = rx;
            state.rotY = ry;
            state.rotZ = rz;

            $('valRotX').textContent = rx.toFixed(1);
            $('valRotY').textContent = ry.toFixed(1);
            $('valRotZ').textContent = rz.toFixed(1);

            // Radian equivalents
            $('valRotXRad').textContent = `(${(rx * Math.PI / 180).toFixed(2)} rad/s)`;
            $('valRotYRad').textContent = `(${(ry * Math.PI / 180).toFixed(2)} rad/s)`;
            $('valRotZRad').textContent = `(${(rz * Math.PI / 180).toFixed(2)} rad/s)`;

            const currentPeak = Math.max(Math.abs(rx), Math.abs(ry), Math.abs(rz));
            if (currentPeak > state.peakRot) {
                state.peakRot = currentPeak;
                $('diagPeakRot').textContent = state.peakRot.toFixed(1) + ' °/s';
            }

            if (state.engine === 'none') {
                state.engine = 'devicemotion';
                $('gyroSourceTag').textContent = 'devicemotion';
                $('diagActiveEngine').textContent = 'DeviceMotion (W3C)';
                $('gyroStatusDetail').textContent = 'Receiving real-time angular velocity from DeviceMotion rotationRate.';
            }
        }

        // Acceleration (linear, without gravity)
        if (e.acceleration) {
            state.accX = e.acceleration.x || 0;
            state.accY = e.acceleration.y || 0;
            state.accZ = e.acceleration.z || 0;

            $('valAccX').textContent = state.accX.toFixed(2);
            $('valAccY').textContent = state.accY.toFixed(2);
            $('valAccZ').textContent = state.accZ.toFixed(2);
        }

        // Acceleration with gravity (G-force calculation)
        if (e.accelerationIncludingGravity) {
            const gx = e.accelerationIncludingGravity.x || 0;
            const gy = e.accelerationIncludingGravity.y || 0;
            const gz = e.accelerationIncludingGravity.z || 0;

            const totalG = Math.sqrt(gx*gx + gy*gy + gz*gz) / 9.80665;
            state.gForce = totalG;
            $('diagGForce').textContent = totalG.toFixed(2) + ' g';
        }
    }

    // Try Generic Sensor API (Gyroscope)
    function setupGenericGyroscope() {
        if (!('Gyroscope' in window)) return false;

        try {
            const gyro = new Gyroscope({ frequency: 60 });
            gyro.addEventListener('reading', () => {
                state.eventCount++;
                // Generic sensor Gyroscope outputs radians/second
                const radX = gyro.x || 0;
                const radY = gyro.y || 0;
                const radZ = gyro.z || 0;

                const degX = radX * (180 / Math.PI);
                const degY = radY * (180 / Math.PI);
                const degZ = radZ * (180 / Math.PI);

                state.rotX = degX;
                state.rotY = degY;
                state.rotZ = degZ;

                $('valRotX').textContent = degX.toFixed(1);
                $('valRotY').textContent = degY.toFixed(1);
                $('valRotZ').textContent = degZ.toFixed(1);

                $('valRotXRad').textContent = `(${radX.toFixed(2)} rad/s)`;
                $('valRotYRad').textContent = `(${radY.toFixed(2)} rad/s)`;
                $('valRotZRad').textContent = `(${radZ.toFixed(2)} rad/s)`;

                state.engine = 'generic-gyro';
                $('gyroSourceTag').textContent = 'Generic Sensor';
                $('diagActiveEngine').textContent = 'W3C Gyroscope API';
                $('gyroStatusDetail').textContent = 'Receiving 60Hz readings via W3C Gyroscope API.';
            });

            gyro.addEventListener('error', err => {
                console.warn('Generic Gyroscope error:', err);
                // Fallback automatically handled by devicemotion
            });

            gyro.start();
            return true;
        } catch (e) {
            console.warn('Generic Gyroscope not started:', e);
            return false;
        }
    }

    // Start all sensors
    async function startSensors() {
        const btn = $('btnStart');
        const status = $('statusMsg');
        btn.disabled = true;

        try {
            // Check iOS 13+ permission request
            let permGranted = true;

            // DeviceOrientation permission (iOS)
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                const orientationRes = await DeviceOrientationEvent.requestPermission();
                if (orientationRes !== 'granted') {
                    throw new Error('DeviceOrientation permission was denied.');
                }
            }

            // DeviceMotion permission (iOS)
            if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') {
                const motionRes = await DeviceMotionEvent.requestPermission();
                if (motionRes !== 'granted') {
                    throw new Error('DeviceMotion permission was denied.');
                }
            }

            // Register standard listeners
            window.addEventListener('deviceorientation', handleOrientation);
            window.addEventListener('devicemotion', handleMotion);

            // Attempt Generic Gyroscope API (works on Android Chrome when enabled)
            setupGenericGyroscope();

            // Request Screen Wake Lock so screen doesn't turn black
            requestWakeLock();

            // UI updates
            state.active = true;
            $('dot-sensor').className = 'badge-dot active';
            $('text-sensor').textContent = 'Sensors Live';
            btn.innerHTML = '<i data-lucide="check-circle"></i><span>Sensors Active</span>';
            btn.style.background = '#10b981';
            $('btnTare').disabled = false;
            status.textContent = 'Sensors are active! Tilt, rotate, and move your phone to see real-time updates.';
            lucide.createIcons();

        } catch (err) {
            status.textContent = 'Error: ' + err.message;
            $('dot-sensor').className = 'badge-dot danger';
            $('text-sensor').textContent = 'Permission Denied / Error';
            btn.disabled = false;
        }
    }

    // Simulation Mode for Desktop Testing
    let isDragging = false;
    let dragStartX = 0;
    let dragStartY = 0;

    function toggleSimulation() {
        state.simulating = !state.simulating;
        const btn = $('btnSimulate');

        if (state.simulating) {
            btn.style.borderColor = 'var(--primary-color)';
            $('btnSimText').textContent = 'Stop Simulation';
            $('statusMsg').textContent = 'Simulation Mode Active: Click and drag on the 3D phone stage to simulate tilting and rotation!';
            $('dot-sensor').className = 'badge-dot active';
            $('text-sensor').textContent = 'Simulating Motion';
            $('btnTare').disabled = false;
            state.engine = 'simulation';
            $('gyroSourceTag').textContent = 'Simulated';
            $('diagActiveEngine').textContent = 'Mouse Simulation';
            $('gyroStatusDetail').textContent = 'Simulated motion generated from pointer interaction.';
        } else {
            btn.style.borderColor = '';
            $('btnSimText').textContent = 'Simulate Motion (Preview)';
            $('statusMsg').textContent = 'Simulation stopped.';
            if (!state.active) {
                $('dot-sensor').className = 'badge-dot';
                $('text-sensor').textContent = 'Sensors Inactive';
            }
        }
    }

    // Pointer events for simulation
    const stage = $('stageWrapper');

    stage.addEventListener('mousedown', e => {
        if (!state.simulating) return;
        isDragging = true;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
    });

    window.addEventListener('mousemove', e => {
        if (!state.simulating || !isDragging) return;
        const dx = e.clientX - dragStartX;
        const dy = e.clientY - dragStartY;

        const fakeGamma = Math.max(-60, Math.min(60, dx * 0.4));
        const fakeBeta = Math.max(-60, Math.min(60, dy * 0.4));
        const fakeAlpha = (dx * 0.8 + 360) % 360;

        handleOrientation({
            alpha: fakeAlpha,
            beta: fakeBeta,
            gamma: fakeGamma
        });

        handleMotion({
            rotationRate: {
                alpha: fakeAlpha * 0.5,
                beta: fakeBeta * 1.5,
                gamma: fakeGamma * 1.5
            },
            acceleration: {
                x: dx * 0.05,
                y: dy * 0.05,
                z: 0.1
            },
            accelerationIncludingGravity: {
                x: (fakeGamma / 60) * 9.8,
                y: (fakeBeta / 60) * 9.8,
                z: 9.8
            }
        });
    });

    window.addEventListener('mouseup', () => {
        isDragging = false;
    });

    // Gyroscope Live Waveform Oscilloscope Canvas
    const canvas = $('gyroCanvas');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = canvas.parentElement.clientWidth * window.devicePixelRatio;
        canvas.height = canvas.parentElement.clientHeight * window.devicePixelRatio;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    function drawWaveform() {
        requestAnimationFrame(drawWaveform);

        // Update history buffer
        state.historyX.push(state.rotX);
        state.historyX.shift();
        state.historyY.push(state.rotY);
        state.historyY.shift();
        state.historyZ.push(state.rotZ);
        state.historyZ.shift();

        // Canvas dimensions
        const w = canvas.width;
        const h = canvas.height;
        const midY = h / 2;

        ctx.clearRect(0, 0, w, h);

        // Center baseline grid
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(0, midY);
        ctx.lineTo(w, midY);
        ctx.stroke();

        const step = w / (state.historyX.length - 1);
        const scale = (h / 2) / 120; // 120 deg/s full scale

        // Draw Channel helper
        function renderChannel(data, strokeStyle) {
            ctx.beginPath();
            ctx.strokeStyle = strokeStyle;
            ctx.lineWidth = 2 * window.devicePixelRatio;
            ctx.lineJoin = 'round';

            for (let i = 0; i < data.length; i++) {
                const x = i * step;
                const y = midY - (data[i] * scale);
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.stroke();
        }

        renderChannel(state.historyX, '#f43f5e'); // X Pitch (Rose)
        renderChannel(state.historyY, '#06b6d4'); // Y Roll (Cyan)
        renderChannel(state.historyZ, '#10b981'); // Z Yaw (Emerald)
    }
    requestAnimationFrame(drawWaveform);

    // Refresh rate (Hz) tracker loop
    setInterval(() => {
        const now = performance.now();
        const deltaSec = (now - state.lastFpsTime) / 1000;
        state.fps = (state.eventCount / deltaSec);
        $('text-rate').textContent = state.fps.toFixed(1) + ' Hz';
        state.eventCount = 0;
        state.lastFpsTime = now;
    }, 1000);

    // Haptics test
    $('btnHaptic').addEventListener('click', () => {
        if ('vibrate' in navigator) {
            navigator.vibrate([40, 60, 80]);
            $('statusMsg').textContent = 'Haptic vibration triggered!';
        } else {
            $('statusMsg').textContent = 'Haptic feedback (navigator.vibrate) not supported on this browser/OS.';
        }
    });

    // Event Listeners
    $('btnStart').addEventListener('click', startSensors);
    $('btnTare').addEventListener('click', tareSensors);
    $('btnSimulate').addEventListener('click', toggleSimulation);

    // Initial load
    checkDiagnostics();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>