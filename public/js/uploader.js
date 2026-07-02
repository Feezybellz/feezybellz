/**
 * SimpleUpload
 * 
 * An aesthetic, industry-standard, standalone AJAX uploader with built-in Drag & Drop UI.
 * Features: Multi-file support, individual progress tracking, file type filtering,
 * and built-in cancel/remove functionality with server callbacks.
 */

window.SimpleUpload = class {
    /**
     * @param {Object} options 
     */
    constructor(options = {}) {
        this.options = {
            element: null,      // The container element or selector
            endpoint: '',       // Upload URL
            fieldName: 'file',  // Form field name
            headers: {},        // Custom headers
            extraData: {},      // Extra POST data
            multi: true,        // Allow multiple files
            autoUpload: true,   // Start upload immediately
            accept: '*',        // Accepted file types (e.g. 'image/*', '.pdf,.doc')
            useUI: true,        // Generate built-in D&D UI
            
            theme: {
                primaryColor: '#2563eb', 
                secondaryColor: '#64748b',
                backgroundColor: '#f8fafc',
                borderColor: '#e2e8f0',
                textColor: '#0f172a',
                borderRadius: '1.25rem',
                fontFamily: 'inherit'
            },
            
            labels: {
                title: 'Drag & Drop files here',
                subtitle: 'or click to browse from your device',
                uploading: 'Uploading...',
                success: 'Complete',
                error: 'Failed',
                remove: 'Remove'
            },
            
            // Callbacks
            onStart: (fileObj) => {},
            onProgress: (pct, fileObj) => {},
            onSuccess: (response, fileObj) => {},
            onError: (error, fileObj) => {},
            onRemove: (fileObj) => {}, // Triggered when 'x' is clicked after upload
            ...options
        };

        this.queue = []; // Array of file objects {id, file, progress, status, xhr, response}
        this.ui = null;
        
        if (this.options.useUI) {
            this._injectStyles();
            this._initUI();
        } else if (this.options.element) {
            this._initMinimal();
        }
    }

    _injectStyles() {
        if (document.getElementById('simple-upload-styles')) return;

        const style = document.createElement('style');
        style.id = 'simple-upload-styles';
        const t = this.options.theme;

        style.textContent = `
            .su-uploader { font-family: ${t.fontFamily}; color: ${t.textColor}; width: 100%; }
            .su-dropzone {
                border: 2px dashed ${t.borderColor}; border-radius: ${t.borderRadius};
                background: ${t.backgroundColor}; padding: 2.5rem 2rem; text-align: center;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
                position: relative; overflow: hidden;
            }
            .su-dropzone:hover, .su-dropzone.su-drag-over {
                border-color: ${t.primaryColor}; background: ${t.primaryColor}08;
                transform: translateY(-1px);
            }
            .su-title { display: block; font-weight: 800; font-size: 1.1rem; margin-bottom: 0.25rem; }
            .su-subtitle { display: block; font-size: 0.875rem; color: ${t.secondaryColor}; font-weight: 500; }
            
            .su-preview {
                margin-top: 1.5rem; display: grid;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem;
            }
            .su-item {
                position: relative; border-radius: 1rem; background: #fff;
                border: 1px solid ${t.borderColor}; overflow: hidden;
                box-shadow: 0 4px 12px rgba(0,0,0,0.03); transition: transform 0.2s;
            }
            .su-item:hover { transform: scale(1.02); }
            
            .su-item-thumb {
                height: 100px; background: #f1f5f9; display: flex;
                align-items: center; justify-content: center; overflow: hidden;
            }
            .su-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
            .su-item-thumb svg { width: 40px; height: 40px; color: ${t.secondaryColor}; }
            
            .su-item-info { padding: 0.75rem; border-top: 1px solid ${t.borderColor}; }
            .su-item-name { 
                font-size: 0.7rem; font-weight: 700; white-space: nowrap; 
                overflow: hidden; text-overflow: ellipsis; color: ${t.textColor};
            }
            
            .su-item-progress-container {
                height: 4px; background: ${t.borderColor}; border-radius: 99px;
                margin-top: 0.5rem; overflow: hidden;
            }
            .su-item-progress-bar {
                height: 100%; background: ${t.primaryColor}; width: 0%;
                transition: width 0.2s ease;
            }
            
            .su-item-status {
                margin-top: 0.4rem; font-size: 0.6rem; font-weight: 800;
                text-transform: uppercase; letter-spacing: 0.05em;
            }
            .su-status-pending { color: ${t.secondaryColor}; }
            .su-status-uploading { color: ${t.primaryColor}; }
            .su-status-success { color: #10b981; }
            .su-status-error { color: #ef4444; }
            
            .su-remove-btn {
                position: absolute; top: 5px; right: 5px; width: 22px; height: 22px;
                background: rgba(0,0,0,0.5); border-radius: 50%; color: #fff;
                display: flex; align-items: center; justify-content: center;
                cursor: pointer; border: none; transition: background 0.2s; z-index: 10;
            }
            .su-remove-btn:hover { background: #ef4444; }
            .su-remove-btn svg { width: 12px; height: 12px; }
        `;
        document.head.appendChild(style);
    }

    _initUI() {
        const container = typeof this.options.element === 'string' 
            ? document.querySelector(this.options.element) 
            : this.options.element;
        if (!container) return;

        container.innerHTML = `
            <div class="su-uploader">
                <div class="su-dropzone">
                    <input type="file" class="su-file-input" style="display:none" 
                           ${this.options.multi ? 'multiple' : ''} 
                           accept="${this.options.accept}">
                    <div class="su-dropzone-content">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" 
                             style="width:40px;height:40px;margin:0 auto 1.25rem;color:${this.options.theme.primaryColor}">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <span class="su-title">${this.options.labels.title}</span>
                        <span class="su-subtitle">${this.options.labels.subtitle}</span>
                    </div>
                </div>
                <div class="su-preview"></div>
            </div>
        `;

        this.ui = {
            dropzone: container.querySelector('.su-dropzone'),
            input: container.querySelector('.su-file-input'),
            preview: container.querySelector('.su-preview')
        };

        this._bindEvents();
    }

    _initMinimal() {
        const el = typeof this.options.element === 'string' ? document.querySelector(this.options.element) : this.options.element;
        if (!el) return;
        if (el.tagName === 'INPUT' && el.type === 'file') {
            el.addEventListener('change', (e) => this._handleFiles(e.target.files));
        } else {
            el.addEventListener('click', () => {
                const tmp = document.createElement('input');
                tmp.type = 'file'; tmp.accept = this.options.accept;
                if (this.options.multi) tmp.multiple = true;
                tmp.onchange = (e) => this._handleFiles(e.target.files);
                tmp.click();
            });
        }
    }

    _bindEvents() {
        this.ui.dropzone.addEventListener('click', () => this.ui.input.click());
        this.ui.input.addEventListener('change', (e) => this._handleFiles(e.target.files));
        this.ui.dropzone.addEventListener('dragover', (e) => { e.preventDefault(); this.ui.dropzone.classList.add('su-drag-over'); });
        ['dragleave', 'dragend', 'drop'].forEach(v => this.ui.dropzone.addEventListener(v, () => this.ui.dropzone.classList.remove('su-drag-over')));
        this.ui.dropzone.addEventListener('drop', (e) => { e.preventDefault(); this._handleFiles(e.dataTransfer.files); });
    }

    _handleFiles(fileList) {
        const newFiles = Array.from(fileList);
        if (newFiles.length === 0) return;

        newFiles.forEach(file => {
            const fileObj = {
                id: Math.random().toString(36).substr(2, 9),
                file: file,
                progress: 0,
                status: 'pending',
                xhr: null,
                response: null,
                el: null
            };
            this.queue.push(fileObj);
            if (this.options.useUI) this._renderFileItem(fileObj);
            if (this.options.autoUpload) this._uploadFile(fileObj);
        });
        
        if (this.ui && this.ui.input) {
            this.ui.input.value = ''; // Reset input
        }
    }

    _renderFileItem(fileObj) {
        const item = document.createElement('div');
        item.className = 'su-item';
        item.id = `su-item-${fileObj.id}`;
        
        const isImage = fileObj.file.type.startsWith('image/');
        const isAudio = fileObj.file.type.startsWith('audio/');
        
        let thumbContent = '';
        if (isImage) {
            thumbContent = `<img src="${URL.createObjectURL(fileObj.file)}">`;
        } else if (isAudio) {
            thumbContent = `
                <div style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #1e293b; height: 100%;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#e2e8f0" stroke-width="2" style="width: 24px; height: 24px; margin-bottom: 8px;"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="15" cy="16" r="3"></circle></svg>
                    <audio controls src="${URL.createObjectURL(fileObj.file)}" style="width: 90%; height: 25px;"></audio>
                </div>
            `;
        } else {
            thumbContent = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>`;
        }

        item.innerHTML = `
            <button class="su-remove-btn" title="${this.options.labels.remove}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="su-item-thumb">${thumbContent}</div>
            <div class="su-item-info">
                <div class="su-item-name" title="${fileObj.file.name}">${fileObj.file.name}</div>
                <div class="su-item-progress-container"><div class="su-item-progress-bar"></div></div>
                <div class="su-item-status su-status-pending">Pending</div>
            </div>
        `;

        item.querySelector('.su-remove-btn').onclick = (e) => {
            e.stopPropagation();
            this._removeFile(fileObj);
        };

        fileObj.el = item;
        this.ui.preview.appendChild(item);
    }

    _updateItemUI(fileObj) {
        if (!fileObj.el) return;
        const bar = fileObj.el.querySelector('.su-item-progress-bar');
        const status = fileObj.el.querySelector('.su-item-status');
        
        bar.style.width = `${fileObj.progress}%`;
        status.textContent = this.options.labels[fileObj.status] || fileObj.status;
        status.className = `su-item-status su-status-${fileObj.status}`;
    }

    _uploadFile(fileObj) {
        if (fileObj.status === 'success' || fileObj.status === 'uploading') return;

        const formData = new FormData();
        formData.append(this.options.fieldName, fileObj.file);
        for (const [k, v] of Object.entries(this.options.extraData)) formData.append(k, v);

        const xhr = new XMLHttpRequest();
        fileObj.xhr = xhr;
        fileObj.status = 'uploading';
        this._updateItemUI(fileObj);
        this.options.onStart(fileObj);

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                fileObj.progress = Math.round((e.loaded / e.total) * 100);
                this._updateItemUI(fileObj);
                this.options.onProgress(fileObj.progress, fileObj);
            }
        });

        xhr.onload = () => {
            let resp = xhr.responseText;
            try { resp = JSON.parse(xhr.responseText); } catch(e) {}
            fileObj.response = resp;

            if (xhr.status >= 200 && xhr.status < 300) {
                fileObj.status = 'success';
                fileObj.progress = 100;
                this.options.onSuccess(resp, fileObj);
            } else {
                fileObj.status = 'error';
                this.options.onError(resp, fileObj);
            }
            this._updateItemUI(fileObj);
        };

        xhr.onerror = () => {
            fileObj.status = 'error';
            this._updateItemUI(fileObj);
            this.options.onError('Network error', fileObj);
        };

        xhr.open('POST', this.options.endpoint);
        for (const [k, v] of Object.entries(this.options.headers)) xhr.setRequestHeader(k, v);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.send(formData);
    }

    _removeFile(fileObj) {
        // 1. Abort if uploading
        if (fileObj.xhr && fileObj.status === 'uploading') {
            fileObj.xhr.abort();
        }

        // 2. Trigger callback if already uploaded
        if (fileObj.status === 'success') {
            this.options.onRemove(fileObj);
        }

        // 3. Remove from UI
        if (fileObj.el) fileObj.el.remove();

        // 4. Remove from queue
        this.queue = this.queue.filter(f => f.id !== fileObj.id);
    }

    /**
     * Public method to trigger upload manually if autoUpload is false
     */
    uploadAll() {
        this.queue.forEach(f => this._uploadFile(f));
    }

    /**
     * Public method to manually add and upload a single file
     * @param {File|Blob} file 
     */
    upload(file) {
        if (!file) return;
        this._handleFiles([file]);
    }
};

/**
 * USAGE GUIDE & EXAMPLES
 * ----------------------
 * 
 * 1. Professional Multi-upload with Server Sync:
 *    const gallery = new SimpleUpload({
 *        element: '#gallery-container',
 *        endpoint: '/api/listings/gallery',
 *        fieldName: 'image',
 *        multi: true,
 *        accept: 'image/*,video/*',
 *        onSuccess: (response, file) => {
 *            file.serverId = response.id; 
 *        },
 *        onRemove: (file) => {
 *            if (file.serverId) {
 *                fetch(`/api/media/${file.serverId}`, { method: 'DELETE' });
 *            }
 *        }
 *    });
 * 
 * 2. Aesthetic Custom Theming:
 *    new SimpleUpload({
 *        element: '#uploader',
 *        theme: {
 *            primaryColor: '#f43f5e',
 *            borderRadius: '0.5rem',
 *            backgroundColor: '#fff1f2'
 *        },
 *        labels: {
 *            title: 'Upload Portfolio',
 *            subtitle: 'Drag your best work here'
 *        }
 *    });
 * 
 * 3. Minimal / Headless Mode:
 *    const uploader = new SimpleUpload({
 *        useUI: false,
 *        endpoint: '/api/upload',
 *        onProgress: (pct, file) => console.log(`${file.file.name}: ${pct}%`)
 *    });
 *    uploader.upload(myFile);
 * 
 * 4. Full Options Documentation:
 *    {
 *        element: null,      // (string|HTMLElement) Container for UI or target for click
 *        endpoint: '',       // (string) Target URL
 *        fieldName: 'file',  // (string) Multipart form field name
 *        headers: {},        // (object) Custom request headers
 *        extraData: {},      // (object) Additional POST parameters
 *        multi: true,        // (boolean) Allow multiple files
 *        autoUpload: true,   // (boolean) Start upload immediately on selection
 *        accept: '*',        // (string) Accepted file types (e.g. 'image/*')
 *        useUI: true,        // (boolean) Generate built-in Drag & Drop interface
 *        
 *        theme: {
 *            primaryColor: '#2563eb',
 *            secondaryColor: '#64748b',
 *            backgroundColor: '#f8fafc',
 *            borderColor: '#e2e8f0',
 *            textColor: '#0f172a',
 *            borderRadius: '1.25rem',
 *            fontFamily: 'inherit'
 *        },
 * 
 *        labels: {
 *            title: 'Drag & Drop files here',
 *            subtitle: 'or click to browse from your device',
 *            uploading: 'Uploading...',
 *            success: 'Complete',
 *            error: 'Failed',
 *            remove: 'Remove'
 *        },
 * 
 *        // Callbacks
 *        onStart: (fileObj) => {},              // Triggered when a file starts uploading
 *        onProgress: (pct, fileObj) => {},      // Triggered during upload
 *        onSuccess: (response, fileObj) => {}, // Triggered on successful upload
 *        onError: (error, fileObj) => {},       // Triggered on upload error
 *        onRemove: (fileObj) => {}              // Triggered when clicking 'x' on an uploaded file
 *    }
 * 
 * NOTE: 
 * SimpleUpload is completely standalone. It handles each file independently 
 * with its own progress bar and 'x' button. The 'x' button automatically 
 * aborts active network requests or triggers the onRemove callback for 
 * synced backend deletion.
 */
