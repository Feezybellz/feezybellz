<?php include __DIR__ . '/partials/header.php'; ?>
    <style>
        .converter-container {
            max-width: 900px;
            margin: 2rem auto;
            background: var(--nav-bg);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--primary-text, #fff);
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .btn-outline:hover {
            background-color: var(--surface-color);
            border-color: var(--primary-color);
        }
        
        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--bg-color);
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(var(--primary-rgb), 0.05);
        }
        
        .icon-large {
            width: 48px;
            height: 48px;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .controls-bar {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            padding: 1rem;
            background: var(--bg-color);
            border-radius: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .files-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-preview {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            object-fit: cover;
            background: var(--border-color);
        }

        .file-status {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
    </style>

    <div class="converter-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Universal Image Converter</h1>
            <p style="color: var(--text-muted);">Convert images between WebP, PNG, JPEG, and more entirely in your browser.</p>
        </div>

        <div class="upload-area" id="drop-zone" onclick="document.getElementById('file-input').click()">
            <i data-lucide="upload-cloud" class="icon-large"></i>
            <h3>Click or drag images here</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Supports PNG, JPG, WEBP, GIF, SVG</p>
            <input type="file" id="file-input" multiple accept="image/*" style="display: none;">
        </div>

        <div class="controls-bar" style="display: none;" id="controls-bar">
            <div style="flex: 1; display: flex; align-items: center; gap: 1rem;">
                <label style="font-weight: 500;">Convert to:</label>
                <select id="target-format" style="padding: 0.5rem; border-radius: 0.375rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit;">
                    <option value="image/webp">WEBP (Recommended)</option>
                    <option value="image/jpeg">JPEG</option>
                    <option value="image/png">PNG</option>
                </select>
                
                <label style="font-weight: 500; margin-left: 1rem;">Quality:</label>
                <input type="range" id="quality" min="0.1" max="1.0" step="0.1" value="0.9" style="width: 100px;">
                <span id="quality-val" style="color: var(--text-muted); font-size: 0.875rem;">90%</span>
            </div>
            
            <button class="btn btn-primary" id="btn-convert-all">Convert All</button>
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
        const targetFormat = document.getElementById('target-format');
        
        let pendingFiles = [];

        // Quality slider update
        qualitySlider.addEventListener('input', (e) => {
            document.getElementById('quality-val').innerText = Math.round(e.target.value * 100) + '%';
        });

        // Drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', (e) => {
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });

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
            if (pendingFiles.length > 0) {
                controlsBar.style.display = 'flex';
                dropZone.style.display = 'none';
            } else {
                controlsBar.style.display = 'none';
                dropZone.style.display = 'block';
            }

            filesList.innerHTML = '';
            pendingFiles.forEach((item, index) => {
                const sizeStr = (item.file.size / 1024).toFixed(1) + ' KB';
                const el = document.createElement('div');
                el.className = 'file-item';
                
                const objectUrl = URL.createObjectURL(item.file);
                
                el.innerHTML = `
                    <div class="file-info">
                        <img src="${objectUrl}" class="file-preview">
                        <div>
                            <div style="font-weight: 500;">${item.file.name}</div>
                            <div class="file-status">${sizeStr} • <span id="status-${item.id}">${item.status}</span></div>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-outline" style="padding: 0.3rem 0.5rem; color: var(--text-muted);" onclick="removeFile(${index})">
                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                        </button>
                    </div>
                `;
                filesList.appendChild(el);
            });
            lucide.createIcons();
        }

        window.removeFile = function(index) {
            pendingFiles.splice(index, 1);
            renderFiles();
        };

        // Conversion Logic (100% Client Side using Canvas)
        document.getElementById('btn-convert-all').addEventListener('click', async () => {
            if (pendingFiles.length === 0) return;
            
            const btn = document.getElementById('btn-convert-all');
            const ogText = btn.innerText;
            btn.innerText = 'Converting...';
            btn.disabled = true;

            const format = targetFormat.value;
            const quality = parseFloat(qualitySlider.value);
            const ext = format.split('/')[1];
            
            const zip = new JSZip();

            for (let i = 0; i < pendingFiles.length; i++) {
                const item = pendingFiles[i];
                document.getElementById(`status-${item.id}`).innerText = 'converting...';
                
                try {
                    const dataUrl = await convertImage(item.file, format, quality);
                    const base64Data = dataUrl.split(',')[1];
                    
                    const originalName = item.file.name.split('.').slice(0, -1).join('.');
                    zip.file(`${originalName}.${ext}`, base64Data, {base64: true});
                    
                    document.getElementById(`status-${item.id}`).innerHTML = `done &bull; <a href="#" onclick="openGlobalPreview(this.dataset.src); return false;" data-src="${dataUrl}" style="color: var(--primary-color); text-decoration: underline; cursor: pointer;">Preview</a>`;
                    // Remove the previous hardcoded styling
                    // document.getElementById(`status-${item.id}`).style.color = 'var(--primary-color)';
                } catch (err) {
                    console.error(err);
                    document.getElementById(`status-${item.id}`).innerText = 'error';
                }
            }

            if (pendingFiles.length === 1) {
                // If only 1 file, just trigger direct download
                const firstFileName = Object.keys(zip.files)[0];
                const content = await zip.file(firstFileName).async("blob");
                const a = document.createElement('a');
                a.href = URL.createObjectURL(content);
                a.download = firstFileName;
                a.click();
            } else {
                // Bulk zip
                const content = await zip.generateAsync({type: "blob"});
                const a = document.createElement('a');
                a.href = URL.createObjectURL(content);
                a.download = `converted_images.zip`;
                a.click();
            }
            
            btn.innerText = ogText;
            btn.disabled = false;
        });

        function convertImage(file, format, quality) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    
                    // Fill white background just in case converting PNG to JPEG
                    if (format === 'image/jpeg') {
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                    }
                    
                    ctx.drawImage(img, 0, 0);
                    resolve(canvas.toDataURL(format, quality));
                };
                img.onerror = reject;
                img.src = URL.createObjectURL(file);
            });
        }
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
