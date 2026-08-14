<?php include __DIR__ . '/partials/header.php'; ?>
    <style>
        .gif-container { max-width: 900px; margin: 2rem auto; background: var(--nav-bg); border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border: none; border-radius: 0.375rem; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-primary { background-color: var(--primary-color); color: var(--primary-text, #fff); }
        .btn-primary:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .upload-area { border: 2px dashed var(--border-color); border-radius: 0.5rem; padding: 3rem 2rem; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg-color); }
        .upload-area:hover, .upload-area.dragover { border-color: var(--primary-color); background: rgba(var(--primary-rgb), 0.05); }
        .icon-large { width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem; }
        
        .controls-bar { display: flex; gap: 1rem; margin: 2rem 0; padding: 1.5rem; background: var(--bg-color); border-radius: 0.5rem; align-items: center; flex-wrap: wrap; }
        .control-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 150px; }
        .control-group label { font-weight: 500; font-size: 0.875rem; }
        .control-group input { padding: 0.5rem; border-radius: 0.375rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit; }
        
        #preview-area { margin-top: 2rem; text-align: center; display: none; }
        #preview-img { max-width: 100%; border-radius: 0.5rem; border: 1px solid var(--border-color); }
    </style>

    <div class="gif-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Video to GIF Maker</h1>
            <p style="color: var(--text-muted);">Convert any short MP4 video into an animated GIF right in your browser.</p>
        </div>

        <div class="upload-area" id="drop-zone" onclick="document.getElementById('file-input').click()">
            <i data-lucide="film" class="icon-large"></i>
            <h3 id="upload-text">Click or drag a video here</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Supports MP4, WebM (Max 15 seconds recommended)</p>
            <input type="file" id="file-input" accept="video/mp4, video/webm" style="display: none;">
        </div>

        <div class="controls-bar" style="display: none;" id="controls-bar">
            <div class="control-group">
                <label>GIF Width (px)</label>
                <input type="number" id="gif-width" value="480">
            </div>
            <div class="control-group">
                <label>Frames Per Second</label>
                <input type="number" id="gif-fps" value="10" min="1" max="30">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button class="btn btn-primary" id="btn-convert" style="height: 42px;">Convert to GIF</button>
            </div>
        </div>

        <div id="preview-area">
            <h3 style="margin-bottom: 1rem;">Your GIF</h3>
            <img id="preview-img" src="" alt="Generated GIF">
            <div style="margin-top: 1rem;">
                <a id="btn-download" class="btn btn-primary" href="#" download="converted.gif">Download GIF</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gifshot/0.3.2/gifshot.min.js"></script>
    <script>
        lucide.createIcons();
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const controlsBar = document.getElementById('controls-bar');
        
        let videoFile = null;
        let videoUrl = null;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false));
        ['dragenter', 'dragover'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false));
        ['dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false));

        dropZone.addEventListener('drop', e => handleFile(e.dataTransfer.files[0]));
        fileInput.addEventListener('change', function() { handleFile(this.files[0]); });

        function handleFile(file) {
            if (!file || !file.type.startsWith('video/')) return;
            videoFile = file;
            videoUrl = URL.createObjectURL(file);
            document.getElementById('upload-text').innerText = file.name;
            dropZone.style.padding = '1.5rem';
            controlsBar.style.display = 'flex';
            document.getElementById('preview-area').style.display = 'none';
        }

        document.getElementById('btn-convert').addEventListener('click', () => {
            if (!videoUrl) return;
            
            const btn = document.getElementById('btn-convert');
            btn.innerText = 'Converting... (This may take a minute)';
            btn.disabled = true;

            const width = parseInt(document.getElementById('gif-width').value) || 480;
            const fps = parseInt(document.getElementById('gif-fps').value) || 10;
            
            // Get video duration to know how many frames to extract
            const videoElement = document.createElement('video');
            videoElement.src = videoUrl;
            videoElement.onloadedmetadata = () => {
                const duration = Math.min(videoElement.duration, 15); // limit to 15 seconds
                const numFrames = Math.floor(duration * fps);
                
                gifshot.createGIF({
                    video: [videoUrl],
                    numFrames: numFrames,
                    frameDuration: 10, // 10 = 100ms = 10fps
                    gifWidth: width,
                    sampleInterval: 10,
                    progressCallback: function(captureProgress) {
                        btn.innerText = `Rendering: ${Math.round(captureProgress * 100)}%`;
                    }
                }, function(obj) {
                    if(!obj.error) {
                        const image = obj.image;
                        document.getElementById('preview-img').src = image;
                        document.getElementById('btn-download').href = image;
                        
                        const ogName = videoFile.name.split('.').slice(0, -1).join('.');
                        document.getElementById('btn-download').download = `${ogName}.gif`;
                        
                        document.getElementById('preview-area').style.display = 'block';
                        btn.innerText = 'Convert to GIF';
                        btn.disabled = false;
                    } else {
                        alert("Error generating GIF");
                        btn.innerText = 'Convert to GIF';
                        btn.disabled = false;
                    }
                });
            };
        });
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
