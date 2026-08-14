<?php 
$title = "Gemini Watermark Remover - Tools"; 
include __DIR__ . '/partials/header.php'; 
?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <style>
        .header {
            text-align: center;
            margin-bottom: 3rem;
            margin-top: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.125rem;
        }

        .container {
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .dropzone {
            border: 2px dashed var(--border-color);
            border-radius: 0.75rem;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .dropzone:hover, .dropzone.dragover {
            border-color: var(--primary-color);
            background-color: rgba(56, 189, 248, 0.05);
        }

        .dropzone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .dropzone svg {
            width: 3rem;
            height: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            transition: color 0.2s ease;
        }

        .dropzone:hover svg, .dropzone.dragover svg {
            color: var(--primary-color);
        }

        .dropzone-text {
            font-size: 1rem;
            color: var(--text-muted);
        }

        .dropzone-text span {
            color: var(--primary-color);
            font-weight: 500;
        }

        .file-list {
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            background-color: rgba(0,0,0,0.2);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.75rem;
            transition: all 0.2s ease;
        }

        .file-item:hover {
            border-color: #475569;
        }

        .file-thumb {
            width: 48px;
            height: 48px;
            border-radius: 0.25rem;
            object-fit: cover;
            margin-right: 1rem;
            background-color: var(--surface-color);
        }

        .file-info {
            flex-grow: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 500;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 0.25rem;
        }

        .file-status {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--text-muted);
        }
        
        .status-dot.pending { background-color: var(--warning-color); }
        .status-dot.processing { background-color: var(--primary-color); animation: pulse 1.5s infinite; }
        .status-dot.success { background-color: var(--success-color); }
        .status-dot.error { background-color: var(--error-color); }

        .file-actions {
            margin-left: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .icon-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .icon-btn:hover {
            background-color: var(--border-color);
            color: var(--text-main);
        }

        .icon-btn.download {
            color: var(--primary-color);
            background-color: rgba(56, 189, 248, 0.1);
        }
        
        .icon-btn.download:hover {
            background-color: var(--primary-color);
            color: var(--primary-text);
        }

        .icon-btn.remove:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            color: var(--primary-text);
            background-color: var(--primary-color);
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn:hover:not(:disabled) {
            background-color: var(--primary-hover);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn.download-all {
            background-color: var(--success-color);
            margin-top: 1rem;
        }

        .btn.download-all:hover:not(:disabled) {
            background-color: #059669;
        }

        .loader {
            display: inline-block;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(56, 189, 248, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }

    </style>
</head>
<body>

    <div class="header">
        <h1>Gemini Watermark Remover</h1>
        <p>Automatically crop out the subtle gem watermark from Gemini / Imagen generated images.</p>
    </div>

    <div class="container">
        <div class="dropzone" id="dropzone">
            <input type="file" id="file-input" accept="image/jpeg, image/png, image/webp, image/gif" multiple>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p class="dropzone-text">Drag & drop your images here, or <span>browse</span></p>
        </div>

        <div class="crop-controls" style="margin-top: 1.5rem; margin-bottom: 0.5rem; display: flex; flex-direction: column; gap: 1rem; background: var(--surface-color); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; flex-direction: column;">
                    <label for="removal-method" style="font-weight: 500; font-size: 0.95rem; color: var(--text-main);">Removal Method</label>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">How should the watermark be removed?</span>
                </div>
                <select id="removal-method" style="padding: 0.5rem; border-radius: 0.375rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit; font-size: 0.95rem; min-width: 150px;">
                    <option value="crop">Crop Bottom Edge</option>
                    <option value="fill_mirror" selected>Mirror Texture Fill (Best)</option>
                    <option value="fill_stretch">Edge Stretch Fill</option>
                    <option value="fill_color">Solid Corner Fill (Average Color)</option>
                    <option value="blur">Corner Blur</option>
                </select>
            </div>
            <div style="width: 100%; height: 1px; background: var(--border-color);"></div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; flex-direction: column;">
                    <label for="crop-amount" style="font-weight: 500; font-size: 0.95rem; color: var(--text-main);">Watermark Size / Crop Height</label>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">Adjust this value if the watermark is still partially visible.</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="number" id="crop-amount" value="68" min="0" max="500" style="width: 80px; padding: 0.5rem; border-radius: 0.375rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit; font-size: 1rem;">
                    <span style="color: var(--text-muted); font-size: 0.875rem;">px</span>
                </div>
            </div>
        </div>

        <div class="file-list" id="file-list">
            <!-- File items will be injected here -->
        </div>

        <button type="button" class="btn" id="process-btn" style="display: none;">
            <span id="btn-icon" style="margin-right: 0.5rem; display: flex;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </span>
            <div class="loader" id="loader" style="display: none;"></div>
            <span id="btn-text">Process Images</span>
        </button>

        <button type="button" class="btn download-all" id="download-all-btn" style="display: none;">
            <span style="margin-right: 0.5rem; display: flex;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            </span>
            <span>Download All as ZIP</span>
        </button>
    </div>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file-input');
        const fileListContainer = document.getElementById('file-list');
        const processBtn = document.getElementById('process-btn');
        const downloadAllBtn = document.getElementById('download-all-btn');
        const loader = document.getElementById('loader');
        const btnText = document.getElementById('btn-text');
        const btnIcon = document.getElementById('btn-icon');

        // State to keep track of files and their statuses
        let filesData = [];

        // Drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
        });

        dropzone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            handleFiles(dt.files);
        }

        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
            // Reset input so the same files can be selected again if needed
            this.value = '';
        });

        document.addEventListener('paste', function(e) {
            const items = (e.clipboardData || window.clipboardData).items;
            const files = [];
            for (let index in items) {
                const item = items[index];
                if (item.kind === 'file' && item.type.startsWith('image/')) {
                    files.push(item.getAsFile());
                }
            }
            if (files.length > 0) {
                e.preventDefault();
                handleFiles(files);
            }
        });

        function handleFiles(files) {
            if (files.length === 0) return;

            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;

                // Generate a unique ID for the file
                const id = Math.random().toString(36).substring(2, 11);
                
                const fileRecord = {
                    id: id,
                    file: file,
                    status: 'pending', // pending, processing, success, error
                    dataUri: null,
                    filename: file.name
                };

                filesData.push(fileRecord);
                
                // Generate thumbnail
                const reader = new FileReader();
                reader.onload = (e) => {
                    fileRecord.thumb = e.target.result;
                    renderFileList();
                };
                reader.readAsDataURL(file);
            });
            
            renderFileList();
        }

        function removeFile(id) {
            filesData = filesData.filter(f => f.id !== id);
            renderFileList();
        }

        function downloadFile(dataUri, filename) {
            const a = document.createElement('a');
            a.href = dataUri;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function renderFileList() {
            fileListContainer.innerHTML = '';
            
            if (filesData.length === 0) {
                processBtn.style.display = 'none';
                downloadAllBtn.style.display = 'none';
                return;
            }

            // Check statuses
            const hasPending = filesData.some(f => f.status === 'pending');
            const isProcessing = filesData.some(f => f.status === 'processing');
            const hasSuccess = filesData.some(f => f.status === 'success');
            const allSuccess = filesData.length > 0 && filesData.every(f => f.status === 'success' || f.status === 'error');
            
            if (allSuccess && hasSuccess) {
                processBtn.style.display = 'none';
                downloadAllBtn.style.display = 'inline-flex';
            } else {
                downloadAllBtn.style.display = 'none';
                processBtn.style.display = 'inline-flex';
                processBtn.disabled = isProcessing || !hasPending;
                
                if (!hasPending && !isProcessing) {
                    btnText.textContent = 'All Done!';
                    btnIcon.style.display = 'flex';
                    btnIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
                } else {
                    btnText.textContent = 'Process Images';
                    btnIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>`;
                }
            }

            filesData.forEach(fileData => {
                const item = document.createElement('div');
                item.className = 'file-item';
                
                // Get status label
                let statusLabel = 'Ready to process';
                if (fileData.status === 'processing') statusLabel = 'Removing watermark...';
                if (fileData.status === 'success') statusLabel = `Watermark removed &bull; <a href="#" onclick="openGlobalPreview('${fileData.processedDataUri}'); return false;" style="color: var(--primary-color); text-decoration: underline;">Preview</a>`;
                if (fileData.status === 'error') statusLabel = 'Processing failed';

                let actionsHtml = '';
                
                if (fileData.status === 'success') {
                    actionsHtml = `
                        <button class="icon-btn download" onclick="downloadFile('${fileData.dataUri}', '${fileData.filename}')" title="Download clean image">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </button>
                    `;
                } else if (fileData.status === 'pending' || fileData.status === 'error') {
                    actionsHtml = `
                        <button class="icon-btn remove" onclick="removeFile('${fileData.id}')" title="Remove from list">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    `;
                }
                
                // If currently processing, show no actions
                if (fileData.status === 'processing') {
                    actionsHtml = '';
                }

                item.innerHTML = `
                    <img src="${fileData.thumb || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='}" alt="${fileData.file.name}" class="file-thumb">
                    <div class="file-info">
                        <div class="file-name" title="${fileData.file.name}">${fileData.file.name}</div>
                        <div class="file-status">
                            <span class="status-dot ${fileData.status}"></span>
                            ${statusLabel}
                        </div>
                    </div>
                    <div class="file-actions">
                        ${actionsHtml}
                    </div>
                `;
                
                fileListContainer.appendChild(item);
            });
        }

        processBtn.addEventListener('click', async () => {
            const pendingFiles = filesData.filter(f => f.status === 'pending' || f.status === 'error');
            if (pendingFiles.length === 0) return;

            // Update UI State
            processBtn.disabled = true;
            loader.style.display = 'inline-block';
            btnIcon.style.display = 'none';
            btnText.textContent = 'Processing...';

            // Process sequentially to not overload the server
            for (const fileData of pendingFiles) {
                fileData.status = 'processing';
                renderFileList();

                const cropValue = parseInt(document.getElementById('crop-amount').value || '68', 10);
                const method = document.getElementById('removal-method').value;

                try {
                    const dataUri = await processImageInBrowser(fileData.file, cropValue, method);
                    
                    fileData.status = 'success';
                    fileData.dataUri = dataUri;
                    fileData.filename = `cleaned_${fileData.file.name}`;
                } catch (error) {
                    fileData.status = 'error';
                    console.error('Error processing file:', error);
                }
                
                renderFileList();
            }

            // Restore UI State
            loader.style.display = 'none';
            btnIcon.style.display = 'inline-flex';
            renderFileList(); // Final render to update button state
        });

        downloadAllBtn.addEventListener('click', () => {
            const successFiles = filesData.filter(f => f.status === 'success' && f.dataUri);
            if (successFiles.length === 0) return;

            // Change button text temporarily
            const originalText = downloadAllBtn.innerHTML;
            downloadAllBtn.innerHTML = `
                <div class="loader" style="display:inline-block; border-top-color: #fff;"></div>
                <span>Zipping...</span>
            `;
            downloadAllBtn.disabled = true;

            setTimeout(() => {
                try {
                    const zip = new JSZip();
                    
                    successFiles.forEach(file => {
                        // Extract base64 part of the data URI (data:image/jpeg;base64,xxxx...)
                        const base64Data = file.dataUri.split(',')[1];
                        if (base64Data) {
                            zip.file(file.filename, base64Data, {base64: true});
                        }
                    });

                    zip.generateAsync({type: "blob"}).then(function(content) {
                        // Trigger download
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(content);
                        a.download = "cleaned_images.zip";
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        
                        // Restore button
                        downloadAllBtn.innerHTML = originalText;
                        downloadAllBtn.disabled = false;
                    });
                } catch (err) {
                    console.error(err);
                    alert("Failed to generate ZIP file.");
                    downloadAllBtn.innerHTML = originalText;
                    downloadAllBtn.disabled = false;
                }
            }, 100);
        });

        function processImageInBrowser(file, amount, method) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    if (method === 'crop') {
                        canvas.width = img.width;
                        canvas.height = Math.max(1, img.height - amount);
                        ctx.drawImage(img, 0, 0);
                    } else {
                        canvas.width = img.width;
                        canvas.height = img.height;
                        ctx.drawImage(img, 0, 0);
                        
                        const wmSize = amount;
                        // Gemini watermark is always bottom right
                        const startX = Math.max(0, img.width - wmSize);
                        const startY = Math.max(0, img.height - wmSize);
                        
                        if (method === 'fill_color') {
                            // Sample pixels outside the watermark box
                            let r = 0, g = 0, b = 0, count = 0;
                            
                            if (startY > 4) {
                                const topStrip = ctx.getImageData(startX, startY - 4, wmSize, 4);
                                for (let i = 0; i < topStrip.data.length; i += 4) {
                                    r += topStrip.data[i];
                                    g += topStrip.data[i+1];
                                    b += topStrip.data[i+2];
                                    count++;
                                }
                            }
                            if (startX > 4) {
                                const leftStrip = ctx.getImageData(startX - 4, startY, 4, wmSize);
                                for (let i = 0; i < leftStrip.data.length; i += 4) {
                                    r += leftStrip.data[i];
                                    g += leftStrip.data[i+1];
                                    b += leftStrip.data[i+2];
                                    count++;
                                }
                            }
                            
                            if (count > 0) {
                                r = Math.round(r / count);
                                g = Math.round(g / count);
                                b = Math.round(b / count);
                                ctx.fillStyle = `rgb(${r}, ${g}, ${b})`;
                                ctx.fillRect(startX, startY, wmSize, wmSize);
                            } else {
                                ctx.fillStyle = '#FFFFFF';
                                ctx.fillRect(startX, startY, wmSize, wmSize);
                            }
                        } else if (method === 'fill_mirror') {
                            // Mirror Texture Fill - Horizontally flip the patch of pixels to the left of the watermark
                            ctx.save();
                            // Move origin to the right edge of the patch we are pasting
                            ctx.translate(startX * 2, 0); 
                            ctx.scale(-1, 1);
                            // Draw the area to the left into the watermark box, perfectly mirrored
                            ctx.drawImage(img, startX - wmSize, startY, wmSize, wmSize, startX - wmSize, startY, wmSize, wmSize);
                            ctx.restore();
                        } else if (method === 'fill_stretch') {
                            // Edge Stretch Fill - Stretch the 1px border across the box
                            // 1. Stretch left edge horizontally
                            ctx.drawImage(img, startX - 1, startY, 1, wmSize, startX, startY, wmSize, wmSize);
                            // 2. Stretch top edge vertically with 50% opacity to blend
                            ctx.globalAlpha = 0.5;
                            ctx.drawImage(img, startX, startY - 1, wmSize, 1, startX, startY, wmSize, wmSize);
                            ctx.globalAlpha = 1.0;
                        } else if (method === 'blur') {
                            // Quick CSS blur overlay hack for canvas
                            ctx.filter = 'blur(16px)';
                            ctx.drawImage(canvas, startX, startY, wmSize, wmSize, startX, startY, wmSize, wmSize);
                            ctx.filter = 'none';
                        }
                    }
                    
                    // toDataURL automatically strips EXIF/AI metadata because it reconstructs the bitmap
                    resolve(canvas.toDataURL(file.type || 'image/jpeg', 1.0));
                };
                img.onerror = () => reject(new Error("Failed to load image"));
                img.src = URL.createObjectURL(file);
            });
        }
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
