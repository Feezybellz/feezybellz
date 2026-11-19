<?php 
$title = "3D Room Scanner & Floorplan Extruder"; 
include __DIR__ . '/partials/header.php'; 
?>
<!-- Three.js + OrbitControls + GLTFExporter -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/exporters/GLTFExporter.js"></script>

<style>
    .scanner-container {
        max-width: 1100px;
        margin: 1.5rem auto 3rem;
        padding: 0 1rem;
    }

    .scanner-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .scanner-header h1 {
        font-size: 2.25rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        letter-spacing: -0.025em;
        background: linear-gradient(135deg, var(--text-main) 30%, var(--primary-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .scanner-header p {
        color: var(--text-muted);
        font-size: 1rem;
        max-width: 650px;
        margin: 0 auto;
    }

    /* Tab switcher */
    .view-tabs {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .tab-btn {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        color: var(--text-sub);
        padding: 0.65rem 1.4rem;
        border-radius: 9999px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .tab-btn:hover {
        color: var(--text-main);
        border-color: var(--primary-color);
    }

    .tab-btn.active {
        background: var(--primary-color);
        color: var(--primary-text);
        border-color: var(--primary-color);
        box-shadow: 0 4px 14px rgba(0, 245, 155, 0.25);
    }

    /* Main Grid */
    .scanner-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    @media (min-width: 880px) {
        .scanner-grid {
            grid-template-columns: 1.15fr 0.85fr;
        }
    }

    .card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 1.25rem;
        padding: 1.25rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .card-title {
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-main);
    }

    /* Camera Viewfinder */
    .camera-wrapper {
        position: relative;
        width: 100%;
        height: 380px;
        background: #000;
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #cameraFeed {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .camera-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at center, rgba(16, 185, 129, 0.08) 0%, #080B10 80%);
        color: var(--text-muted);
        text-align: center;
        padding: 1.5rem;
        gap: 0.75rem;
    }

    /* Camera Reticle Overlay */
    .reticle-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .crosshair-target {
        width: 60px;
        height: 60px;
        border: 2px dashed rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: all 0.15s ease;
    }

    .crosshair-target.valid-aim {
        border-color: #00f59b;
        box-shadow: 0 0 15px rgba(0, 245, 155, 0.5);
    }

    .crosshair-center {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #fff;
    }

    .crosshair-target.valid-aim .crosshair-center {
        background: #00f59b;
    }

    .hud-stat-pill {
        position: absolute;
        bottom: 12px;
        left: 12px;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(8px);
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-family: 'Fira Code', monospace;
        color: #fff;
        display: flex;
        gap: 10px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .hud-tilt-pill {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(8px);
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-family: 'Fira Code', monospace;
        color: #00f59b;
        border: 1px solid rgba(0, 245, 155, 0.3);
    }

    /* Minimap Canvas */
    .minimap-wrapper {
        position: relative;
        width: 100%;
        height: 380px;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        overflow: hidden;
    }

    #minimapCanvas {
        width: 100%;
        height: 100%;
        display: block;
        cursor: crosshair;
    }

    .minimap-hint {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        pointer-events: none;
        backdrop-filter: blur(4px);
    }

    /* Action bar */
    .actions-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 1rem;
        justify-content: center;
    }

    .btn-primary {
        background: var(--primary-color);
        color: var(--primary-text);
        border: none;
        padding: 0.85rem 1.6rem;
        font-size: 1rem;
        font-weight: 700;
        border-radius: 0.75rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: transform 0.15s, box-shadow 0.2s;
        box-shadow: 0 4px 14px rgba(0, 245, 155, 0.25);
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 245, 155, 0.4);
    }

    .btn-primary:disabled {
        opacity: 0.5;
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

    /* 3D Viewport Area */
    .viewport-3d-wrapper {
        position: relative;
        width: 100%;
        height: 480px;
        background: radial-gradient(circle at center, rgba(16, 185, 129, 0.05) 0%, transparent 80%), #0b0f17;
        border-radius: 1rem;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    #threeCanvas {
        width: 100%;
        height: 100%;
        display: block;
    }

    .viewport-controls-bar {
        position: absolute;
        top: 12px;
        right: 12px;
        display: flex;
        gap: 6px;
        z-index: 10;
    }

    .btn-viewport-icon {
        background: rgba(16, 21, 30, 0.85);
        border: 1px solid var(--border-color);
        color: #fff;
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 0.75rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        backdrop-filter: blur(8px);
        transition: all 0.2s;
    }

    .btn-viewport-icon:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    /* Metric summary cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .stat-card {
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 0.85rem;
        padding: 0.85rem 0.6rem;
        text-align: center;
    }

    .stat-label {
        display: block;
        font-size: 0.72rem;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .stat-value {
        display: block;
        font-family: 'Fira Code', monospace;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .stat-unit {
        display: block;
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
    }

    /* Configuration Panel */
    .config-box {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 0.85rem;
        padding: 1rem;
        margin-top: 1rem;
    }

    .config-item {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .config-item label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-sub);
    }

    .config-item input, .config-item select {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.88rem;
        outline: none;
    }

    .config-item input:focus, .config-item select:focus {
        border-color: var(--primary-color);
    }

    .corner-list {
        max-height: 140px;
        overflow-y: auto;
        font-size: 0.8rem;
        font-family: 'Fira Code', monospace;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        padding: 0.5rem;
        margin-top: 0.75rem;
    }

    .corner-item {
        display: flex;
        justify-content: space-between;
        padding: 0.3rem 0.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .corner-item:last-child {
        border-bottom: none;
    }
</style>

<div class="scanner-container">
    <!-- Header -->
    <div class="scanner-header">
        <h1>3D Room Scanner & Floorplan</h1>
        <p>Measure room corners with your phone's camera & gyroscope. Extrudes clean 3D CAD walls and floorplans purely in your browser.</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="view-tabs">
        <button class="tab-btn active" id="tabScan" onclick="switchTab('scan')">
            <i data-lucide="scan"></i>
            <span>1. Capture & Corners</span>
        </button>
        <button class="tab-btn" id="tab3D" onclick="switchTab('3d')">
            <i data-lucide="box"></i>
            <span>2. 3D Model Viewport</span>
        </button>
        <button class="tab-btn" id="tabExport" onclick="switchTab('export')">
            <i data-lucide="download"></i>
            <span>3. Export (.OBJ / .GLB)</span>
        </button>
    </div>

    <!-- STEP 1: SCAN & CORNERS VIEW -->
    <div id="viewStep1">
        <div class="scanner-grid">
            <!-- Camera Viewfinder Card -->
            <div class="card">
                <div class="card-title">
                    <span><i data-lucide="camera"></i> Augmented Camera Viewfinder</span>
                    <span id="cameraStatus" style="font-size:0.75rem; color:var(--text-muted);">Camera Inactive</span>
                </div>

                <div class="camera-wrapper">
                    <video id="cameraFeed" autoplay playsinline muted></video>

                    <div class="camera-placeholder" id="cameraPlaceholder">
                        <i data-lucide="camera-off" style="width:40px; height:40px;"></i>
                        <p style="font-weight:600;">Camera is off</p>
                        <button class="btn-primary" onclick="startCameraAndSensors()" style="padding:0.6rem 1.2rem; font-size:0.9rem;">
                            <i data-lucide="play"></i> Start Camera & Sensors
                        </button>
                    </div>

                    <!-- Reticle & Overlays -->
                    <div class="reticle-overlay" id="reticleOverlay" style="display:none;">
                        <div class="crosshair-target" id="crosshairTarget">
                            <div class="crosshair-center"></div>
                        </div>
                        <div class="hud-tilt-pill" id="hudTilt">Pitch: 0.0°</div>
                        <div class="hud-stat-pill" id="hudStat">
                            <span id="hudDistance">Aim at corner base</span>
                            <span id="hudYaw">Heading: 0°</span>
                        </div>
                    </div>
                </div>

                <!-- Primary Capture Actions -->
                <div class="actions-bar">
                    <button class="btn-primary" id="btnMarkCorner" onclick="markCurrentCorner()" disabled>
                        <i data-lucide="map-pin"></i>
                        <span>Mark Corner (<span id="cornerCountText">0</span>)</span>
                    </button>
                    <button class="btn-secondary" id="btnUndoCorner" onclick="undoCorner()" disabled title="Undo last corner">
                        <i data-lucide="undo-2"></i> Undo
                    </button>
                    <button class="btn-secondary" onclick="clearCorners()" title="Clear all points">
                        <i data-lucide="trash-2"></i> Clear
                    </button>
                </div>
            </div>

            <!-- 2D Minimap & Live Geometry Card -->
            <div class="card">
                <div class="card-title">
                    <span><i data-lucide="layout"></i> 2D Floorplan Minimap</span>
                    <span style="font-size:0.72rem; color:var(--text-muted);">Click to plot manually</span>
                </div>

                <div class="minimap-wrapper">
                    <canvas id="minimapCanvas" width="450" height="380"></canvas>
                    <div class="minimap-hint">Grid: 1m squares • Blue = You • Green = Aim</div>
                </div>

                <!-- Corner Coordinates List -->
                <div class="corner-list" id="cornerList">
                    <div style="color:var(--text-muted); text-align:center; padding:10px;">No corners marked yet. Stand in the room and aim at floor corners!</div>
                </div>

                <!-- Quick Presets for instant testing -->
                <div style="display:flex; gap:0.5rem; justify-content:center; margin-top:0.75rem; flex-wrap:wrap;">
                    <button class="btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.75rem;" onclick="loadSamplePreset('bedroom')">
                        <i data-lucide="sparkles"></i> Preset: 4×3m Bedroom
                    </button>
                    <button class="btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.75rem;" onclick="loadSamplePreset('lshape')">
                        <i data-lucide="sparkles"></i> Preset: L-Shaped Room
                    </button>
                </div>
            </div>
        </div>

        <!-- Room Dimensions Config Card -->
        <div class="card">
            <div class="card-title">
                <span><i data-lucide="sliders"></i> Room & Calibration Settings</span>
            </div>
            <div class="config-box">
                <div class="config-item">
                    <label>Holding Height (Eye/Chest)</label>
                    <input type="number" id="inputEyeHeight" value="1.40" step="0.05" min="0.5" max="2.5" onchange="updateHeightSettings()">
                </div>
                <div class="config-item">
                    <label>Ceiling Height</label>
                    <input type="number" id="inputCeilingHeight" value="2.60" step="0.1" min="1.8" max="6.0" onchange="rebuild3DModel()">
                </div>
                <div class="config-item">
                    <label>Units</label>
                    <select id="selectUnits" onchange="updateUnits()">
                        <option value="meters" selected>Meters (m / m²)</option>
                        <option value="feet">Feet (ft / sq ft)</option>
                    </select>
                </div>
                <div class="config-item" style="justify-content: flex-end;">
                    <button class="btn-primary" id="btnBuild3D" onclick="buildAndGoTo3D()" disabled style="padding:0.55rem 1rem; font-size:0.9rem;">
                        <i data-lucide="box"></i> Build 3D Room (<span id="buildCornerCount">0</span> pts)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: 3D MODEL VIEWPORT -->
    <div id="viewStep2" style="display:none;">
        <div class="card">
            <div class="card-title">
                <span><i data-lucide="box"></i> Interactive 3D Room Model</span>
                <span style="font-size:0.75rem; color:var(--text-muted);">Drag to orbit • Scroll to zoom</span>
            </div>

            <div class="viewport-3d-wrapper">
                <canvas id="threeCanvas"></canvas>
                <div class="viewport-controls-bar">
                    <button class="btn-viewport-icon" onclick="reset3DCamera('perspective')">
                        <i data-lucide="camera"></i> 3D Perspective
                    </button>
                    <button class="btn-viewport-icon" onclick="reset3DCamera('top')">
                        <i data-lucide="layout"></i> Top Down
                    </button>
                    <button class="btn-viewport-icon" id="btnToggleCeiling" onclick="toggleCeiling()">
                        <i data-lucide="eye-off"></i> Hide Ceiling
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-label">Floor Area</span>
                    <span class="stat-value" id="valArea">0.0</span>
                    <span class="stat-unit" id="unitArea">m²</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Wall Perimeter</span>
                    <span class="stat-value" id="valPerimeter">0.0</span>
                    <span class="stat-unit" id="unitPerimeter">meters</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Ceiling Height</span>
                    <span class="stat-value" id="valCeiling">2.60</span>
                    <span class="stat-unit" id="unitCeiling">meters</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Room Volume</span>
                    <span class="stat-value" id="valVolume">0.0</span>
                    <span class="stat-unit" id="unitVolume">m³</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Corners</span>
                    <span class="stat-value" id="valCorners">0</span>
                    <span class="stat-unit">vertices</span>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 3: EXPORT VIEW -->
    <div id="viewStep3" style="display:none;">
        <div class="card" style="text-align:center; padding: 2.5rem 1.5rem;">
            <i data-lucide="check-circle" style="width:50px; height:50px; color:#10b981; margin:0 auto 1rem;"></i>
            <h2 style="margin-bottom:0.5rem;">3D CAD Model Ready</h2>
            <p style="color:var(--text-muted); max-width:550px; margin:0 auto 1.5rem;">
                Your room geometry was generated 100% on your device. Download it in universal CAD formats compatible with Blender, AutoCAD, SketchUp, Unity, Unreal, and 3D printing.
            </p>

            <div class="actions-bar" style="margin-top:0.5rem;">
                <button class="btn-primary" onclick="exportOBJ()">
                    <i data-lucide="file-down"></i>
                    <span>Download Wavefront (.OBJ)</span>
                </button>
                <button class="btn-primary" style="background:#4F46E5;" onclick="exportGLB()">
                    <i data-lucide="box"></i>
                    <span>Download Binary 3D (.GLB)</span>
                </button>
                <button class="btn-secondary" onclick="exportJSON()">
                    <i data-lucide="code"></i>
                    <span>Download Floorplan (.JSON)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // State management
    const state = {
        corners: [], // array of {x, z} in meters
        holdingHeight: 1.40, // meters from ground
        ceilingHeight: 2.60, // meters
        unit: 'meters', // 'meters' or 'feet'
        cameraActive: false,
        videoStream: null,
        pitch: 0, // degrees
        yaw: 0,   // degrees (compass)
        roll: 0,  // degrees
        currentAimDistance: null,
        currentAimCoord: null, // {x, z}
        showCeiling: true
    };

    const $ = id => document.getElementById(id);

    // Tab switcher
    function switchTab(tab) {
        $('tabScan').classList.toggle('active', tab === 'scan');
        $('tab3D').classList.toggle('active', tab === '3d');
        $('tabExport').classList.toggle('active', tab === 'export');

        $('viewStep1').style.display = tab === 'scan' ? 'block' : 'none';
        $('viewStep2').style.display = tab === '3d' ? 'block' : 'none';
        $('viewStep3').style.display = tab === 'export' ? 'block' : 'none';

        if (tab === '3d') {
            rebuild3DModel();
            onWindowResize();
        }
        lucide.createIcons();
    }

    function buildAndGoTo3D() {
        if (state.corners.length < 3) return;
        switchTab('3d');
    }

    // Camera & Sensors initialization
    async function startCameraAndSensors() {
        try {
            // Request camera stream (environment / rear camera)
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            state.videoStream = stream;
            $('cameraFeed').srcObject = stream;
            $('cameraPlaceholder').style.display = 'none';
            $('reticleOverlay').style.display = 'flex';
            $('cameraStatus').textContent = 'Camera Active (Rear)';
            $('cameraStatus').style.color = '#10b981';
            state.cameraActive = true;

            // iOS 13+ sensor permissions
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                try {
                    const perm = await DeviceOrientationEvent.requestPermission();
                    if (perm === 'granted') {
                        window.addEventListener('deviceorientation', handleOrientation);
                    }
                } catch(e) {
                    console.warn('Orientation permission denied:', e);
                }
            } else {
                window.addEventListener('deviceorientation', handleOrientation);
            }

            $('btnMarkCorner').disabled = false;

        } catch (err) {
            alert('Camera access error: ' + err.message + '\nYou can still use presets or tap on the 2D grid to draw corners manually!');
        }
    }

    // Orientation handler
    function handleOrientation(e) {
        state.pitch = e.beta || 0;  // Front/back tilt
        state.roll = e.gamma || 0;  // Left/right tilt
        state.yaw = e.alpha || 0;   // Compass

        $('hudTilt').textContent = `Pitch: ${state.pitch.toFixed(1)}°`;
        $('hudYaw').textContent = `Heading: ${state.yaw.toFixed(0)}°`;

        // Trigonometric Raycast to Floor:
        // When pointing down at the floor, pitch is typically between 15° and 80°
        const tiltDown = Math.abs(state.pitch);

        const target = $('crosshairTarget');
        if (tiltDown > 10 && tiltDown < 85) {
            target.classList.add('valid-aim');

            // Distance across floor = height / tan(tiltAngle)
            const rad = (tiltDown * Math.PI) / 180;
            const dist = state.holdingHeight / Math.tan(rad);
            state.currentAimDistance = dist;

            // Compass yaw in radians: 0 deg = North (+Z), 90 deg = East (+X)
            const yawRad = (state.yaw * Math.PI) / 180;
            const targetX = dist * Math.sin(yawRad);
            const targetZ = -dist * Math.cos(yawRad);

            state.currentAimCoord = { x: targetX, z: targetZ };

            $('hudDistance').textContent = `Floor Dist: ${formatDist(dist)}`;
            $('btnMarkCorner').disabled = false;

        } else {
            target.classList.remove('valid-aim');
            state.currentAimDistance = null;
            state.currentAimCoord = null;
            $('hudDistance').textContent = tiltDown <= 10 ? 'Tilt camera down towards floor' : 'Too steep angle';
        }

        drawMinimap();
    }

    // Mark current aimed corner
    function markCurrentCorner() {
        let pt = state.currentAimCoord;

        // If no camera/sensor reading (desktop), project point forward by 2.5m
        if (!pt) {
            const angle = (state.corners.length * Math.PI) / 2;
            pt = { x: Math.sin(angle) * 2.5, z: Math.cos(angle) * 2.5 };
        }

        state.corners.push({ x: Number(pt.x.toFixed(2)), z: Number(pt.z.toFixed(2)) });

        if ('vibrate' in navigator) navigator.vibrate(40);

        updateCornerUI();
        drawMinimap();
    }

    function undoCorner() {
        if (state.corners.length === 0) return;
        state.corners.pop();
        updateCornerUI();
        drawMinimap();
    }

    function clearCorners() {
        state.corners = [];
        updateCornerUI();
        drawMinimap();
    }

    function updateCornerUI() {
        const count = state.corners.length;
        $('cornerCountText').textContent = count;
        $('buildCornerCount').textContent = count;
        $('btnUndoCorner').disabled = count === 0;
        $('btnBuild3D').disabled = count < 3;
        $('valCorners').textContent = count;

        const list = $('cornerList');
        if (count === 0) {
            list.innerHTML = '<div style="color:var(--text-muted); text-align:center; padding:10px;">No corners marked yet. Stand in the room and aim at floor corners!</div>';
            return;
        }

        let html = '';
        state.corners.forEach((c, idx) => {
            const nextIdx = (idx + 1) % count;
            let distStr = '';
            if (count > 1) {
                const nxt = state.corners[nextIdx];
                const segLen = Math.hypot(nxt.x - c.x, nxt.z - c.z);
                distStr = `Wall to P${nextIdx + 1}: ${formatDist(segLen)}`;
            }
            html += `<div class="corner-item">
                <span><b>P${idx + 1}</b> (${formatDist(c.x)}, ${formatDist(c.z)})</span>
                <span style="color:var(--primary-color);">${distStr}</span>
            </div>`;
        });
        list.innerHTML = html;
    }

    // Units and formatting
    function formatDist(meters) {
        if (state.unit === 'feet') {
            return (meters * 3.28084).toFixed(2) + ' ft';
        }
        return meters.toFixed(2) + ' m';
    }

    function updateUnits() {
        state.unit = $('selectUnits').value;
        updateCornerUI();
        drawMinimap();
        if ($('viewStep2').style.display === 'block') {
            rebuild3DModel();
        }
    }

    function updateHeightSettings() {
        state.holdingHeight = parseFloat($('inputEyeHeight').value) || 1.40;
        state.ceilingHeight = parseFloat($('inputCeilingHeight').value) || 2.60;
    }

    // 2D Floorplan Minimap Canvas
    const mCanvas = $('minimapCanvas');
    const mCtx = mCanvas.getContext('2d');

    // Click on minimap to place corner manually
    mCanvas.addEventListener('click', e => {
        const rect = mCanvas.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        // Convert canvas pixel to world coordinates in meters
        const scale = 35; // 35 px per meter
        const centerX = mCanvas.width / 2;
        const centerY = mCanvas.height / 2;

        const worldX = (clickX - centerX) / scale;
        const worldZ = (clickY - centerY) / scale;

        state.corners.push({ x: Number(worldX.toFixed(2)), z: Number(worldZ.toFixed(2)) });
        updateCornerUI();
        drawMinimap();
    });

    function drawMinimap() {
        const w = mCanvas.width;
        const h = mCanvas.height;
        const midX = w / 2;
        const midY = h / 2;
        const scale = 35; // 35 pixels = 1 meter

        mCtx.clearRect(0, 0, w, h);

        // Draw meter grid
        mCtx.strokeStyle = 'rgba(255, 255, 255, 0.06)';
        mCtx.lineWidth = 1;

        for (let x = midX % scale; x < w; x += scale) {
            mCtx.beginPath();
            mCtx.moveTo(x, 0);
            mCtx.lineTo(x, h);
            mCtx.stroke();
        }
        for (let y = midY % scale; y < h; y += scale) {
            mCtx.beginPath();
            mCtx.moveTo(0, y);
            mCtx.lineTo(w, y);
            mCtx.stroke();
        }

        // Draw Center origin cross (User location)
        mCtx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
        mCtx.beginPath();
        mCtx.moveTo(midX - 8, midY);
        mCtx.lineTo(midX + 8, midY);
        mCtx.moveTo(midX, midY - 8);
        mCtx.lineTo(midX, midY + 8);
        mCtx.stroke();

        // Draw User position & heading cone
        mCtx.save();
        mCtx.translate(midX, midY);
        mCtx.rotate((state.yaw * Math.PI) / 180);

        // Heading FOV cone
        mCtx.fillStyle = 'rgba(0, 245, 155, 0.12)';
        mCtx.beginPath();
        mCtx.moveTo(0, 0);
        mCtx.arc(0, 0, 70, -Math.PI / 6 - Math.PI/2, Math.PI / 6 - Math.PI/2);
        mCtx.closePath();
        mCtx.fill();

        // User icon dot
        mCtx.fillStyle = '#00f59b';
        mCtx.beginPath();
        mCtx.arc(0, 0, 6, 0, Math.PI * 2);
        mCtx.fill();
        mCtx.restore();

        // Draw current aimed point (if valid)
        if (state.currentAimCoord) {
            const aimPx = midX + state.currentAimCoord.x * scale;
            const aimPy = midY + state.currentAimCoord.z * scale;

            mCtx.fillStyle = 'rgba(16, 185, 129, 0.7)';
            mCtx.beginPath();
            mCtx.arc(aimPx, aimPy, 5, 0, Math.PI * 2);
            mCtx.fill();

            // Line from user to aim
            mCtx.strokeStyle = 'rgba(16, 185, 129, 0.35)';
            mCtx.setLineDash([4, 4]);
            mCtx.beginPath();
            mCtx.moveTo(midX, midY);
            mCtx.lineTo(aimPx, aimPy);
            mCtx.stroke();
            mCtx.setLineDash([]);
        }

        // Draw Pinned Corners & Connecting Walls
        if (state.corners.length > 0) {
            // Fill polygon if 3 or more corners
            if (state.corners.length >= 3) {
                mCtx.fillStyle = 'rgba(0, 245, 155, 0.08)';
                mCtx.beginPath();
                state.corners.forEach((c, i) => {
                    const cx = midX + c.x * scale;
                    const cy = midY + c.z * scale;
                    if (i === 0) mCtx.moveTo(cx, cy);
                    else mCtx.lineTo(cx, cy);
                });
                mCtx.closePath();
                mCtx.fill();
            }

            // Draw Wall lines
            mCtx.strokeStyle = '#00f59b';
            mCtx.lineWidth = 2.5;
            mCtx.beginPath();
            state.corners.forEach((c, i) => {
                const cx = midX + c.x * scale;
                const cy = midY + c.z * scale;
                if (i === 0) mCtx.moveTo(cx, cy);
                else mCtx.lineTo(cx, cy);
            });
            if (state.corners.length >= 3) {
                // Close back to first corner
                mCtx.lineTo(midX + state.corners[0].x * scale, midY + state.corners[0].z * scale);
            }
            mCtx.stroke();

            // Draw Corner Nodes & Labels
            state.corners.forEach((c, i) => {
                const cx = midX + c.x * scale;
                const cy = midY + c.z * scale;

                mCtx.fillStyle = '#f43f5e';
                mCtx.beginPath();
                mCtx.arc(cx, cy, 6, 0, Math.PI * 2);
                mCtx.fill();
                mCtx.strokeStyle = '#fff';
                mCtx.lineWidth = 1.5;
                mCtx.stroke();

                // Corner label
                mCtx.fillStyle = '#fff';
                mCtx.font = '10px monospace';
                mCtx.fillText(`P${i + 1}`, cx + 8, cy - 8);
            });
        }
    }

    // Sample Presets for desktop testing
    function loadSamplePreset(type) {
        if (type === 'bedroom') {
            state.corners = [
                { x: -2.0, z: -1.5 },
                { x: 2.0, z: -1.5 },
                { x: 2.0, z: 1.5 },
                { x: -2.0, z: 1.5 }
            ];
        } else if (type === 'lshape') {
            state.corners = [
                { x: -2.5, z: -2.0 },
                { x: 1.5, z: -2.0 },
                { x: 1.5, z: 0.0 },
                { x: 2.5, z: 0.0 },
                { x: 2.5, z: 2.0 },
                { x: -2.5, z: 2.0 }
            ];
        }
        updateCornerUI();
        drawMinimap();
        buildAndGoTo3D();
    }

    // THREE.JS 3D VIEWPORT ENGINE
    let scene, camera, renderer, controls;
    let roomMeshGroup = null;

    function init3D() {
        const container = $('threeCanvas').parentElement;
        const width = container.clientWidth;
        const height = container.clientHeight;

        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0a0e17);

        camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 100);
        camera.position.set(0, 5, 7);

        renderer = new THREE.WebGLRenderer({ canvas: $('threeCanvas'), antialias: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.shadowMap.enabled = true;

        controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.maxPolarAngle = Math.PI / 2 + 0.1; // allow seeing floor level

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        scene.add(ambientLight);

        const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
        dirLight.position.set(5, 10, 7);
        dirLight.castShadow = true;
        scene.add(dirLight);

        const fillLight = new THREE.DirectionalLight(0x00f59b, 0.2);
        fillLight.position.set(-5, 6, -5);
        scene.add(fillLight);

        // Ground grid
        const gridHelper = new THREE.GridHelper(20, 20, 0x1f2937, 0x111827);
        gridHelper.position.y = -0.01;
        scene.add(gridHelper);

        // Animation loop
        function animate() {
            requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
        }
        animate();
    }

    function onWindowResize() {
        if (!renderer || !camera) return;
        const container = $('threeCanvas').parentElement;
        const w = container.clientWidth;
        const h = container.clientHeight;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    }
    window.addEventListener('resize', onWindowResize);

    // Build the 3D Room from Corners
    function rebuild3DModel() {
        if (!scene) init3D();
        if (state.corners.length < 3) return;

        state.ceilingHeight = parseFloat($('inputCeilingHeight').value) || 2.60;

        if (roomMeshGroup) scene.remove(roomMeshGroup);
        roomMeshGroup = new THREE.Group();

        const corners = state.corners;
        const numCorners = corners.length;
        const H = state.ceilingHeight;

        // 1. Compute Floor Polygon Shape
        const shape = new THREE.Shape();
        corners.forEach((c, idx) => {
            if (idx === 0) shape.moveTo(c.x, -c.z);
            else shape.lineTo(c.x, -c.z);
        });
        shape.closePath();

        // Floor Mesh
        const floorGeo = new THREE.ShapeGeometry(shape);
        const floorMat = new THREE.MeshStandardMaterial({
            color: 0x1e293b,
            roughness: 0.8,
            metalness: 0.1,
            side: THREE.DoubleSide
        });
        const floorMesh = new THREE.Mesh(floorGeo, floorMat);
        floorMesh.rotation.x = Math.PI / 2; // lie flat on XZ plane
        floorMesh.position.y = 0;
        floorMesh.receiveShadow = true;
        roomMeshGroup.add(floorMesh);

        // Ceiling Mesh
        const ceilingMat = new THREE.MeshStandardMaterial({
            color: 0x0f172a,
            roughness: 0.9,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.85
        });
        const ceilingMesh = new THREE.Mesh(floorGeo, ceilingMat);
        ceilingMesh.rotation.x = Math.PI / 2;
        ceilingMesh.position.y = H;
        ceilingMesh.name = 'ceilingMesh';
        ceilingMesh.visible = state.showCeiling;
        roomMeshGroup.add(ceilingMesh);

        // 2. Build Walls (Extruded Quads between consecutive corners)
        let totalPerimeter = 0;
        const wallMat = new THREE.MeshStandardMaterial({
            color: 0x334155,
            roughness: 0.6,
            metalness: 0.05,
            side: THREE.DoubleSide
        });

        for (let i = 0; i < numCorners; i++) {
            const c1 = corners[i];
            const c2 = corners[(i + 1) % numCorners];

            const dx = c2.x - c1.x;
            const dz = c2.z - c1.z;
            const wallLength = Math.hypot(dx, dz);
            totalPerimeter += wallLength;

            // Geometry for single vertical wall quad
            const wallGeo = new THREE.PlaneGeometry(wallLength, H);
            const wallMesh = new THREE.Mesh(wallGeo, wallMat);

            // Position at midpoint between corners, elevated to H/2
            const midX = (c1.x + c2.x) / 2;
            const midZ = (c1.z + c2.z) / 2;
            wallMesh.position.set(midX, H / 2, midZ);

            // Rotate along wall angle
            const angle = Math.atan2(dz, dx);
            wallMesh.rotation.y = -angle;
            wallMesh.castShadow = true;
            wallMesh.receiveShadow = true;

            // Wireframe edge highlight
            const edgeGeo = new THREE.EdgesGeometry(wallGeo);
            const edgeLine = new THREE.LineSegments(edgeGeo, new THREE.LineBasicMaterial({ color: 0x00f59b, linewidth: 1.5 }));
            wallMesh.add(edgeLine);

            roomMeshGroup.add(wallMesh);
        }

        scene.add(roomMeshGroup);

        // Calculate Polygon Area using Shoelace Formula
        let area = 0;
        for (let i = 0; i < numCorners; i++) {
            const j = (i + 1) % numCorners;
            area += corners[i].x * corners[j].z;
            area -= corners[j].x * corners[i].z;
        }
        area = Math.abs(area) / 2.0;
        const volume = area * H;

        // Update statistics display
        if (state.unit === 'feet') {
            $('valArea').textContent = (area * 10.7639).toFixed(1);
            $('unitArea').textContent = 'sq ft';
            $('valPerimeter').textContent = (totalPerimeter * 3.28084).toFixed(1);
            $('unitPerimeter').textContent = 'ft';
            $('valCeiling').textContent = (H * 3.28084).toFixed(1);
            $('unitCeiling').textContent = 'ft';
            $('valVolume').textContent = (volume * 35.3147).toFixed(1);
            $('unitVolume').textContent = 'cu ft';
        } else {
            $('valArea').textContent = area.toFixed(2);
            $('unitArea').textContent = 'm²';
            $('valPerimeter').textContent = totalPerimeter.toFixed(2);
            $('unitPerimeter').textContent = 'meters';
            $('valCeiling').textContent = H.toFixed(2);
            $('unitCeiling').textContent = 'meters';
            $('valVolume').textContent = volume.toFixed(2);
            $('unitVolume').textContent = 'm³';
        }
    }

    function toggleCeiling() {
        state.showCeiling = !state.showCeiling;
        const c = roomMeshGroup ? roomMeshGroup.getObjectByName('ceilingMesh') : null;
        if (c) c.visible = state.showCeiling;
        $('btnToggleCeiling').innerHTML = state.showCeiling 
            ? '<i data-lucide="eye-off"></i> Hide Ceiling' 
            : '<i data-lucide="eye"></i> Show Ceiling';
        lucide.createIcons();
    }

    function reset3DCamera(view) {
        if (!controls || !camera) return;
        if (view === 'top') {
            camera.position.set(0, 10, 0.01);
            controls.target.set(0, 0, 0);
        } else {
            camera.position.set(0, 5, 7);
            controls.target.set(0, 1, 0);
        }
        controls.update();
    }

    // CLIENT-SIDE 3D EXPORT FUNCTIONS
    function exportOBJ() {
        if (state.corners.length < 3) {
            alert('Mark at least 3 corners before exporting!');
            return;
        }

        const corners = state.corners;
        const num = corners.length;
        const H = state.ceilingHeight;

        let obj = `# 3D Room Model Export\n# Generated by Antigravity Room Scanner\n# Units: Meters\n\no Room\n\n`;

        // Floor Vertices 1..num
        corners.forEach(c => {
            obj += `v ${c.x.toFixed(4)} 0.0000 ${c.z.toFixed(4)}\n`;
        });

        // Ceiling Vertices num+1..2*num
        corners.forEach(c => {
            obj += `v ${c.x.toFixed(4)} ${H.toFixed(4)} ${c.z.toFixed(4)}\n`;
        });

        obj += `\n# Floor Face\nf `;
        for (let i = num; i >= 1; i--) obj += `${i} `;
        obj += `\n\n# Ceiling Face\nf `;
        for (let i = 1; i <= num; i++) obj += `${num + i} `;
        obj += `\n\n# Wall Faces\n`;

        for (let i = 0; i < num; i++) {
            const v1 = i + 1;
            const v2 = ((i + 1) % num) + 1;
            const v3 = num + v2;
            const v4 = num + v1;
            obj += `f ${v1} ${v2} ${v3} ${v4}\n`;
        }

        downloadBlob(obj, 'room-model.obj', 'text/plain');
    }

    function exportGLB() {
        if (!roomMeshGroup) {
            alert('Generate the 3D model first!');
            return;
        }

        const exporter = new THREE.GLTFExporter();
        exporter.parse(roomMeshGroup, gltf => {
            const blob = new Blob([gltf], { type: 'model/gltf-binary' });
            downloadUrl(URL.createObjectURL(blob), 'room-model.glb');
        }, { binary: true });
    }

    function exportJSON() {
        const data = {
            metadata: {
                timestamp: new Date().toISOString(),
                generator: "Antigravity 3D Room Scanner",
                holdingHeightMeters: state.holdingHeight,
                ceilingHeightMeters: state.ceilingHeight
            },
            corners: state.corners,
            measurements: {
                floorAreaSqMeters: parseFloat($('valArea').textContent),
                perimeterMeters: parseFloat($('valPerimeter').textContent),
                ceilingMeters: state.ceilingHeight
            }
        };

        const jsonStr = JSON.stringify(data, null, 2);
        downloadBlob(jsonStr, 'floorplan.json', 'application/json');
    }

    function downloadBlob(content, filename, type) {
        const blob = new Blob([content], { type: type });
        downloadUrl(URL.createObjectURL(blob), filename);
    }

    function downloadUrl(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 1500);
    }

    // Initial render
    drawMinimap();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>