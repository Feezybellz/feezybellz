<?php include __DIR__ . '/partials/header.php'; ?>
    <style>
        .compressor-container {
            max-width: 900px;
            margin: 2rem auto;
            background: var(--nav-bg);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border: none; border-radius: 0.375rem; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-primary { background-color: var(--primary-color); color: var(--primary-text, #fff); }
        .btn-primary:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-outline { background-color: transparent; border: 1px solid var(--border-color); color: var(--text-main); }
        .btn-outline:hover { background-color: var(--surface-color); border-color: var(--primary-color); }

        .upload-area { border: 2px dashed var(--border-color); border-radius: 0.5rem; padding: 3rem 2rem; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg-color); }
        .upload-area:hover, .upload-area.dragover { border-color: var(--primary-color); background: rgba(var(--primary-rgb), 0.05); }
        .icon-large { width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem; }

        .controls-bar { display: flex; gap: 1rem; margin: 2rem 0; padding: 1.5rem; background: var(--bg-color); border-radius: 0.5rem; align-items: center; flex-wrap: wrap; }
        .control-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 150px; }
        .control-group label { font-weight: 500; font-size: 0.875rem; }
        .control-group input { padding: 0.5rem; border-radius: 0.375rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit; }

        .files-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .file-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 0.5rem; }
        .file-info { display: flex; align-items: center; gap: 1rem; }
        .file-preview { width: 40px; height: 40px; border-radius: 4px; object-fit: cover; background: var(--border-color); }
        .file-status { font-size: 0.875rem; color: var(--text-muted); }
    </style>

    <div class="compressor-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Image Compressor & Resizer</h1>
            <p style="color: var(--text-muted);">Bulk optimize and resize your images on your device before uploading anywhere.</p>
        </div>

        <div class="upload-area" id="drop-zone" onclick="document.getElementById('file-input').click()">
            <i data-lucide="minimize" class="icon-large"></i>
            <h3>Click or drag images here</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Supports PNG, JPG, WEBP</p>
            <input type="file" id="file-input" multiple accept="image/png, image/jpeg, image/webp" style="display: none;">
        </div>

        <div class="controls-bar" style="display: none;" id="controls-bar">
            <div class="control-group">
                <label>Max Width (px)</label>
                <input type="number" id="max-width" value="1920" placeholder="Auto">
            </div>
            <div class="control-group">
                <label>Max Height (px)</label>
                <input type="number" id="max-height" value="" placeholder="Auto">
            </div>
            <div class="control-group">
                <label>Quality (<span id="quality-val">80%</span>)</label>
                <input type="range" id="quality" min="0.1" max="1.0" step="0.1" value="0.8">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button class="btn btn-primary" id="btn-compress-all" style="height: 42px;">Compress & Download</button>
            </div>
        </div>

        <div class="files-list" id="files-list"></div>
    </div>

    <!-- JSZip for bulk download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    
    <script>
        lucide.createIcons();

        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const filesList = document.getElementById('files-list');
        const controlsBar = document.getElementById('controls-bar');
        const qualitySlider = document.getElementById('quality');
        
        let pendingFiles = [];

        qualitySlider.addEventListener('input', (e) => {
            document.getElementById('quality-val').innerText = Math.round(e.target.value * 100) + '%';
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false));
        ['dragenter', 'dragover'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false));
        ['dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false));

        dropZone.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
        fileInput.addEventListener('change', function() { handleFiles(this.files); });

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    pendingFiles.push({
                        id: Math.random().toString(36).substr(2, 9),
                        file: file,
                        status: 'ready'
                    });
                }
            });
            renderFiles();
        }

        function renderFiles() {
            if (pendingFiles.length > 0) { controlsBar.style.display = 'flex'; dropZone.style.display = 'none'; } 
            else { controlsBar.style.display = 'none'; dropZone.style.display = 'block'; }

            filesList.innerHTML = '';
            pendingFiles.forEach((item, index) => {
                const sizeStr = (item.file.size / 1024).toFixed(1) + ' KB';
                const el = document.createElement('div'); el.className = 'file-item';
                const objectUrl = URL.createObjectURL(item.file);
                
                el.innerHTML = `
                    <div class="file-info">
                        <img src="${objectUrl}" class="file-preview">
                        <div>
                            <div style="font-weight: 500;">${item.file.name}</div>
                            <div class="file-status">${sizeStr} • <span id="status-${item.id}">${item.status}</span></div>
                        </div>
                    </div>
                    <button class="btn btn-outline" style="padding: 0.3rem 0.5rem; color: var(--text-muted);" onclick="removeFile(${index})">
                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                    </button>
                `;
                filesList.appendChild(el);
            });
            lucide.createIcons();
        }

        window.removeFile = function(index) {
            pendingFiles.splice(index, 1);
            renderFiles();
        };

        document.getElementById('btn-compress-all').addEventListener('click', async () => {
            if (pendingFiles.length === 0) return;
            
            const btn = document.getElementById('btn-compress-all');
            const ogText = btn.innerText;
            btn.innerText = 'Compressing...'; btn.disabled = true;

            const quality = parseFloat(qualitySlider.value);
            const maxWidth = parseInt(document.getElementById('max-width').value) || 0;
            const maxHeight = parseInt(document.getElementById('max-height').value) || 0;
            
            const zip = new JSZip();

            for (let i = 0; i < pendingFiles.length; i++) {
                const item = pendingFiles[i];
                document.getElementById(`status-${item.id}`).innerText = 'compressing...';
                
                try {
                    const result = await compressImage(item.file, maxWidth, maxHeight, quality);
                    const base64Data = result.dataUrl.split(',')[1];
                    
                    const originalName = item.file.name.split('.').slice(0, -1).join('.');
                    const ext = result.format.split('/')[1];
                    const newSize = (result.blob.size / 1024).toFixed(1);
                    const oldSize = (item.file.size / 1024).toFixed(1);
                    
                    zip.file(`${originalName}_optimized.${ext}`, base64Data, {base64: true});
                    
                    document.getElementById(`status-${item.id}`).innerHTML = `Reduced from ${oldSize}KB to ${newSize}KB &bull; <a href="#" onclick="openGlobalPreview(this.dataset.src); return false;" data-src="${result.dataUrl}" style="color: var(--primary-color); text-decoration: underline; cursor: pointer;">Preview</a>`;
                } catch (err) {
                    console.error(err);
                    document.getElementById(`status-${item.id}`).innerText = 'error';
                }
            }

            if (pendingFiles.length === 1) {
                const firstFileName = Object.keys(zip.files)[0];
                const content = await zip.file(firstFileName).async("blob");
                const a = document.createElement('a'); a.href = URL.createObjectURL(content); a.download = firstFileName; a.click();
            } else {
                const content = await zip.generateAsync({type: "blob"});
                const a = document.createElement('a'); a.href = URL.createObjectURL(content); a.download = `compressed_images.zip`; a.click();
            }
            
            btn.innerText = ogText; btn.disabled = false;
        });

        function compressImage(file, maxWidth, maxHeight, quality) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;

                    if (maxWidth && width > maxWidth) {
                        height = Math.round(height * (maxWidth / width));
                        width = maxWidth;
                    }
                    if (maxHeight && height > maxHeight) {
                        width = Math.round(width * (maxHeight / height));
                        height = maxHeight;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    
                    let format = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
                    if (format === 'image/jpeg') {
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                    }
                    
                    ctx.drawImage(img, 0, 0, width, height);
                    const dataUrl = canvas.toDataURL(format, quality);
                    
                    fetch(dataUrl).then(res => res.blob()).then(blob => {
                        resolve({ dataUrl, blob, format });
                    });
                };
                img.onerror = reject;
                img.src = URL.createObjectURL(file);
            });
        }
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
