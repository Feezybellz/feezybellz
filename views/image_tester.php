<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antigravity Image Service Studio & Tester</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #090D16;
            --surface: rgba(15, 23, 42, 0.85);
            --surface-hover: rgba(30, 41, 59, 0.9);
            --border: rgba(255, 255, 255, 0.08);
            --primary: #38BDF8;
            --primary-gradient: linear-gradient(135deg, #06B6D4 0%, #3B82F6 100%);
            --accent: #EC4899;
            --success: #10B981;
            --text-main: #F8FAFC;
            --text-sub: #94A3B8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.12) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2rem 1.5rem;
            line-height: 1.5;
        }

        header {
            max-width: 1380px;
            margin: 0 auto 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1.5rem;
        }

        .brand-title {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(to right, #38BDF8, #A855F7, #EC4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
        }

        .brand-sub {
            color: var(--text-sub);
            font-size: 0.95rem;
            margin-top: 0.2rem;
        }

        .badge {
            background: rgba(56, 189, 248, 0.1);
            color: #38BDF8;
            border: 1px solid rgba(56, 189, 248, 0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .layout {
            max-width: 1380px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .layout { grid-template-columns: 1fr; }
        }

        .panel {
            background: var(--surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .panel-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
            color: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .section-group:last-child { border-bottom: none; margin-bottom: 0; }

        .section-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748B;
            font-weight: 700;
            margin-bottom: 0.8rem;
            display: block;
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.8rem;
        }

        .form-row > * { flex: 1; }

        label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-sub);
            margin-bottom: 0.3rem;
        }

        input[type="text"], input[type="number"], select, input[type="file"], textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: white;
            padding: 0.6rem 0.8rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        input[type="text"]:focus, input[type="number"]:focus, select:focus {
            outline: none;
            border-color: #38BDF8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        input[type="file"] {
            padding: 0.5rem;
            font-size: 0.8rem;
            color: #94A3B8;
        }
        input[type="file"]::file-selector-button {
            background: rgba(56, 189, 248, 0.15);
            color: #38BDF8;
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            margin-right: 0.8rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        input[type="file"]::file-selector-button:hover {
            background: rgba(56, 189, 248, 0.25);
        }

        .btn-submit {
            width: 100%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.9rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.3s;
            box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 20px -3px rgba(14, 165, 233, 0.4);
        }

        .btn-submit:active { transform: translateY(0); }

        /* Preview Canvas Area */
        .preview-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .canvas-card {
            background: #0B111E;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }

        .canvas-toolbar {
            background: rgba(30, 41, 59, 0.6);
            padding: 0.8rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stats-pills {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .stat-pill {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-family: 'JetBrains Mono', monospace;
            color: #E2E8F0;
        }

        .stat-pill span { color: #64748B; font-size: 0.75rem; margin-right: 4px; }

        .canvas-stage {
            flex: 1;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Checkerboard pattern for alpha transparency preview */
            background-image: 
                linear-gradient(45deg, #131B2E 25%, transparent 25%), 
                linear-gradient(-45deg, #131B2E 25%, transparent 25%), 
                linear-gradient(45deg, transparent 75%, #131B2E 75%), 
                linear-gradient(-45deg, transparent 75%, #131B2E 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            background-color: #0D1524;
            min-height: 360px;
        }

        .canvas-stage img {
            max-width: 100%;
            max-height: 600px;
            border-radius: 8px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            transition: all 0.3s;
            display: block;
        }

        .log-box {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            color: #38BDF8;
            overflow-x: auto;
            max-height: 220px;
        }

        .log-title {
            color: #94A3B8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .btn-download {
            background: #10B981;
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 0.4rem;
            transition: background 0.2s;
        }
        .btn-download:hover { background: #059669; }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: #E2E8F0;
            font-weight: 500;
        }
        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
    </style>
</head>
<body>

    <header>
        <div>
            <h1 class="brand-title">Antigravity Image Service</h1>
            <p class="brand-sub">Multi-source rendering studio, watermarking & custom pipeline tester</p>
        </div>
        <div class="badge">
            <span style="width: 8px; height: 8px; background: #38BDF8; border-radius: 50%; display: inline-block; box-shadow: 0 0 8px #38BDF8;"></span>
            PHP Driver Pattern (Imagick + GD)
        </div>
    </header>

    <div class="layout">
        <!-- Control Panel -->
        <div class="panel">
            <h2 class="panel-title">Studio Controls</h2>
            <form id="tester-form" enctype="multipart/form-data">
                
                <!-- Section: Source -->
                <div class="section-group">
                    <span class="section-label">1. Input Source</span>
                    <div class="form-row">
                        <div>
                            <label>Upload Image ($_FILES)</label>
                            <input type="file" name="image_file" accept="image/*">
                        </div>
                    </div>
                    <div>
                        <label>Or Remote URL (optional fallback)</label>
                        <input type="text" name="image_url" placeholder="https://example.com/photo.jpg">
                    </div>
                    <p style="font-size: 0.75rem; color: #64748B; margin-top: 0.4rem;">* Leave both blank to auto-generate an 800x500 presentation canvas.</p>
                </div>

                <!-- Section: Resize & Crop -->
                <div class="section-group">
                    <span class="section-label">2. Resize & Geometry</span>
                    <div class="form-row">
                        <div>
                            <label>Mode</label>
                            <select name="resize_mode">
                                <option value="none">Original Dimensions</option>
                                <option value="resize">Proportional Resize</option>
                                <option value="cover">Cover (Resize + Crop)</option>
                                <option value="fit">Fit (With Canvas Padding)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Width (px)</label>
                            <input type="number" name="width" value="600">
                        </div>
                        <div>
                            <label>Height (px)</label>
                            <input type="number" name="height" value="400">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Rotate (Degrees)</label>
                            <select name="rotate">
                                <option value="0">0° (No Rotation)</option>
                                <option value="90">90° Clockwise</option>
                                <option value="180">180° Inverted</option>
                                <option value="270">270° Clockwise</option>
                            </select>
                        </div>
                        <div>
                            <label>Flip Direction</label>
                            <select name="flip">
                                <option value="none">None</option>
                                <option value="horizontal">Horizontal (Mirror)</option>
                                <option value="vertical">Vertical</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section: Visual Filters -->
                <div class="section-group">
                    <span class="section-label">3. Color & Effects</span>
                    <div class="form-row">
                        <div style="flex: 2;">
                            <label>Visual Filter</label>
                            <select name="filter">
                                <option value="none">Normal (No Filter)</option>
                                <option value="grayscale">Grayscale / Black & White</option>
                                <option value="sharpen">HD Sharpen</option>
                                <option value="blur">Gaussian Blur</option>
                                <option value="brightness">Brightness Boost</option>
                                <option value="contrast">High Contrast</option>
                                <option value="invert">Color Inversion</option>
                                <option value="pixelate">Retro Pixelate</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label>Intensity</label>
                            <input type="number" name="filter_val" value="15">
                        </div>
                    </div>
                </div>

                <!-- Section: Watermarking -->
                <div class="section-group">
                    <span class="section-label">4. Watermarking & Branding</span>
                    
                    <div style="margin-bottom: 0.8rem;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="apply_logo_wm" value="1" checked>
                            Apply Logo / Badge Watermark
                        </label>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Upload Watermark ($_FILES)</label>
                            <input type="file" name="watermark_file" accept="image/*">
                        </div>
                    </div>
                    <div style="margin-bottom: 0.8rem;">
                        <label>Or Watermark URL / Local Server Path</label>
                        <input type="text" name="watermark_url" placeholder="https://example.com/logo.png or storage/logo.png">
                    </div>

                    <div class="form-row">
                        <div>
                            <label>Logo Position</label>
                            <select name="wm_position">
                                <option value="bottom-right">Bottom Right</option>
                                <option value="bottom-left">Bottom Left</option>
                                <option value="top-right">Top Right</option>
                                <option value="top-left">Top Left</option>
                                <option value="center">Center</option>
                            </select>
                        </div>
                        <div>
                            <label>Opacity (%)</label>
                            <input type="number" name="wm_opacity" value="85" min="1" max="100">
                        </div>
                    </div>

                    <div style="margin-top: 1rem;">
                        <label>Text Watermark Overlay</label>
                        <input type="text" name="watermark_text" value="CONFIDENTIAL - TEST BUILD" placeholder="Type overlay text here...">
                    </div>
                    <div class="form-row" style="margin-top: 0.5rem;">
                        <div>
                            <label>Text Position</label>
                            <select name="wm_text_pos">
                                <option value="top-left">Top Left</option>
                                <option value="top-right">Top Right</option>
                                <option value="bottom-left">Bottom Left</option>
                                <option value="bottom-right">Bottom Right</option>
                                <option value="center">Center</option>
                            </select>
                        </div>
                        <div>
                            <label>Color</label>
                            <input type="text" name="wm_text_color" value="#38BDF8">
                        </div>
                        <div>
                            <label>Size</label>
                            <input type="number" name="wm_text_size" value="16">
                        </div>
                    </div>
                </div>

                <!-- Section: Output & Custom Pipelines -->
                <div class="section-group">
                    <span class="section-label">5. Export & Driver Options</span>
                    <div class="form-row">
                        <div>
                            <label>Target Format</label>
                            <select name="format">
                                <option value="webp">WebP (Next-Gen High Perf)</option>
                                <option value="png">PNG (Lossless & Alpha)</option>
                                <option value="jpg">JPEG (Universal)</option>
                                <option value="gif">GIF</option>
                            </select>
                        </div>
                        <div>
                            <label>Quality (1-100)</label>
                            <input type="number" name="quality" value="90" min="1" max="100">
                        </div>
                        <div>
                            <label>Engine</label>
                            <select name="driver">
                                <option value="auto">Auto Detect</option>
                                <option value="gd">PHP GD</option>
                                <option value="imagick">Imagick</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="spinner" id="spinner"></span>
                    <span id="btn-label">Render & Execute Pipeline</span>
                </button>
            </form>
        </div>

        <!-- Preview Stage & Live Metrics -->
        <div class="preview-wrapper">
            <div class="canvas-card">
                <div class="canvas-toolbar">
                    <div class="stats-pills">
                        <div class="stat-pill"><span>RES:</span> <b id="stat-res">-- x --</b></div>
                        <div class="stat-pill"><span>FMT:</span> <b id="stat-fmt">--</b></div>
                        <div class="stat-pill"><span>SIZE:</span> <b id="stat-size">-- KB</b></div>
                        <div class="stat-pill"><span>LATENCY:</span> <b id="stat-ms" style="color: #10B981;">-- ms</b></div>
                    </div>
                    <a href="#" id="download-btn" class="btn-download" download="rendered_image.webp">
                        ↓ Download Image
                    </a>
                </div>

                <div class="canvas-stage" id="stage">
                    <span style="color: #64748B; font-weight: 500;">Click "Render & Execute Pipeline" to process image...</span>
                </div>
            </div>

            <!-- Custom Pipeline Execution Verification Log -->
            <div class="log-box">
                <div class="log-title">
                    <span style="color: #A855F7;">⚡</span> Custom Pipeline & In-Memory Stream Verification Log
                </div>
                <pre id="pipeline-log">Waiting for image pipeline execution...</pre>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('tester-form');
        const spinner = document.getElementById('spinner');
        const btnLabel = document.getElementById('btn-label');
        const stage = document.getElementById('stage');
        const downloadBtn = document.getElementById('download-btn');
        const pipelineLog = document.getElementById('pipeline-log');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            spinner.style.display = 'inline-block';
            btnLabel.innerText = 'Processing Image Stream...';
            form.querySelector('.btn-submit').disabled = true;

            const formData = new FormData(form);

            try {
                const response = await fetch('/image-tester', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const res = await response.json();

                if (res.success) {
                    // Render image on stage
                    stage.innerHTML = `<img src="${res.data_uri}" alt="Rendered Image" id="active-preview">`;
                    
                    // Update Stat Pills
                    document.getElementById('stat-res').innerText = `${res.width} x ${res.height} px`;
                    document.getElementById('stat-fmt').innerText = res.mime;
                    document.getElementById('stat-size').innerText = `${(res.size_bytes / 1024).toFixed(2)} KB`;
                    document.getElementById('stat-ms').innerText = `${res.execution_ms} ms`;

                    // Update Download Button
                    downloadBtn.style.display = 'inline-flex';
                    downloadBtn.href = res.data_uri;
                    downloadBtn.download = `watermarked_output.${res.format.toLowerCase()}`;

                    // Show pipeline verification details
                    pipelineLog.innerText = JSON.stringify({
                        status: "SUCCESS: Stream extracted & pipeline evaluated cleanly",
                        engine_used: res.pipeline.engine_core_class,
                        memory_stream_uri: res.pipeline.stream_temp_uri,
                        bytes_streamed: res.pipeline.generated_bytes,
                        evaluated_at: res.pipeline.timestamp,
                        custom_pipeline_ready: true
                    }, null, 2);
                } else {
                    stage.innerHTML = `<div style="color: #EF4444; background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 8px;">Error: ${res.error}</div>`;
                    pipelineLog.innerText = JSON.stringify(res, null, 2);
                }
            } catch (err) {
                stage.innerHTML = `<div style="color: #EF4444;">Network or Server Exception occurred: ${err.message}</div>`;
                pipelineLog.innerText = `Error: ${err.message}`;
            } finally {
                spinner.style.display = 'none';
                btnLabel.innerText = 'Render & Execute Pipeline';
                form.querySelector('.btn-submit').disabled = false;
            }
        });

        // Automatically trigger initial render on load to impress immediately!
        window.addEventListener('DOMContentLoaded', () => {
            form.dispatchEvent(new Event('submit'));
        });
    </script>

</body>
</html>
