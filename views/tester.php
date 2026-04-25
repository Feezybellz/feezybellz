<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTTP & File Upload Tester</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
        }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--text); background-color: var(--bg); margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        header { margin-bottom: 2rem; }
        h1 { font-size: 1.875rem; font-weight: 700; color: #0f172a; margin: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
        
        .card { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 20px; }
        .card-title { font-size: 1.125rem; font-weight: 600; margin-bottom: 1.25rem; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"], input[type="url"], select, textarea { 
            width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 0.875rem; 
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .method-select { width: 120px !important; margin-right: 8px; }
        .url-input-group { display: flex; gap: 8px; }
        
        button { 
            background-color: var(--primary); color: white; padding: 0.625rem 1.25rem; border: none; border-radius: 6px; cursor: pointer; 
            font-size: 0.875rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        button:hover { background-color: var(--primary-hover); }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        button.secondary { background-color: white; color: var(--text); border: 1px solid var(--border); }
        button.secondary:hover { background-color: #f1f5f9; }
        button.danger { background-color: transparent; color: var(--danger); border: 1px solid #fee2e2; padding: 0.5rem; }
        button.danger:hover { background-color: #fef2f2; }

        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-top: 0.5rem; }
        .checkbox-group input { width: 16px; height: 16px; margin: 0; }
        
        .tabs { display: flex; border-bottom: 1px solid var(--border); margin-bottom: 1rem; }
        .tab { padding: 0.5rem 1rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: var(--text-light); border-bottom: 2px solid transparent; }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        
        .response-meta { display: flex; gap: 16px; margin-bottom: 12px; font-size: 0.75rem; color: var(--text-light); }
        .status-badge { padding: 2px 8px; border-radius: 9999px; font-weight: 600; }
        .status-success { background: #dcfce7; color: #166534; }
        .status-error { background: #fee2e2; color: #991b1b; }
        
        pre { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 8px; overflow: auto; font-size: 0.8125rem; max-height: 500px; margin: 0; }
        
        .header-row { display: flex; gap: 8px; margin-bottom: 8px; }
        .header-row input { flex: 1; }
        
        .file-preview { margin-top: 1rem; border: 1px dashed var(--border); border-radius: 8px; padding: 12px; display: none; }
        .preview-img { max-width: 100%; max-height: 200px; border-radius: 4px; display: block; margin: 0 auto; }
        
        .loading-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .hidden { display: none; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .hint { font-size: 0.75rem; color: var(--text-light); margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>HTTP & File Tester (Backend Powered)</h1>
            <p style="color: var(--text-light); margin-top: 4px;">Requests are processed on the server using PHP CURL.</p>
        </header>

        <div class="grid">
            <div class="request-side">
                <div class="card">
                    <div class="card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        Request Configuration
                    </div>
                    
                    <form id="tester-form">
                        <div class="form-group">
                            <label>Method & URL</label>
                            <div class="url-input-group">
                                <select id="method" class="method-select">
                                    <option value="GET">GET</option>
                                    <option value="POST" selected>POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="PATCH">PATCH</option>
                                    <option value="DELETE">DELETE</option>
                                </select>
                                <input type="text" id="url" value="https://httpbin.org/post" placeholder="Enter Target URL">
                            </div>
                        </div>

                        <div class="tabs">
                            <div class="tab active" data-tab="body-tab">Body</div>
                            <div class="tab" data-tab="files-tab">Files</div>
                            <div class="tab" data-tab="headers-tab">Headers</div>
                        </div>

                        <div id="body-tab" class="tab-content">
                            <div class="form-group">
                                <label>Body Type</label>
                                <select id="body-type" class="form-control">
                                    <option value="none">None</option>
                                    <option value="json" selected>JSON (application/json)</option>
                                    <option value="form-data">Form Data (multipart/form-data)</option>
                                    <option value="x-www-form-urlencoded">Form URL Encoded</option>
                                    <option value="binary">Binary (Raw File)</option>
                                    <option value="raw">Raw Text</option>
                                </select>
                            </div>
                            <div class="form-group" id="body-container">
                                <label id="body-label">Request Body</label>
                                <textarea id="body" rows="6" placeholder='{ "key": "value" }'></textarea>
                                <div id="binary-hint" class="hint hidden">The file selected in the 'Files' tab will be sent as the raw request body.</div>
                            </div>
                        </div>

                        <div id="files-tab" class="tab-content hidden">
                            <div class="form-group">
                                <label>File Field Name</label>
                                <input type="text" id="file-field-name" value="file" placeholder="e.g. avatar, document">
                                <div class="hint">Used when Body Type is 'Form Data'</div>
                            </div>
                            <div class="form-group">
                                <label>Upload File</label>
                                <input type="file" id="file-input">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="convert_blob" value="1">
                                    <label for="convert_blob" style="margin:0">Client-side: Convert to Blob/Preview</label>
                                </div>
                            </div>
                            <div id="file-preview" class="file-preview">
                                <div id="preview-info" style="font-size: 0.75rem; margin-bottom: 8px;"></div>
                                <img id="preview-img" class="preview-img hidden">
                                <textarea id="preview-base64" rows="4" class="hidden" style="margin-top:8px; font-size: 10px;" readonly></textarea>
                            </div>
                        </div>

                        <div id="headers-tab" class="tab-content hidden">
                            <div class="flex-between" style="margin-bottom: 12px;">
                                <label style="margin:0">Headers</label>
                                <button type="button" class="secondary" id="add-header" style="padding: 4px 8px; font-size: 12px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Header
                                </button>
                            </div>
                            <div id="headers-list">
                                <div class="header-row">
                                    <input type="text" placeholder="Header Name" class="header-key" value="X-Custom-Header">
                                    <input type="text" placeholder="Value" class="header-value" value="Framework-Tester">
                                    <button type="button" class="danger remove-header">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <button type="submit" id="submit-btn" style="width: 100%;">
                                <span id="btn-spinner" class="loading-spinner hidden"></span>
                                <span id="btn-text">Send via Backend Proxy</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="response-side">
                <div class="card" style="min-height: 400px; display: flex; flex-direction: column;">
                    <div class="card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Response
                    </div>

                    <div id="response-empty" style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 0.875rem; flex-direction: column; gap: 12px;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.2;"><path d="M21 15V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/><path d="m15 19 2 2 4-4"/></svg>
                        No request sent yet
                    </div>

                    <div id="response-content" class="hidden">
                        <div class="response-meta">
                            <span id="status-badge" class="status-badge"></span>
                            <span id="response-time"></span>
                            <span id="response-size"></span>
                        </div>
                        <div class="tabs">
                            <div class="tab active" data-tab="resp-body-tab">Body</div>
                            <div class="tab" data-tab="resp-headers-tab">Headers</div>
                        </div>
                        <div id="resp-body-tab" class="resp-tab-content">
                            <pre id="response-body"></pre>
                        </div>
                        <div id="resp-headers-tab" class="resp-tab-content hidden">
                            <pre id="response-headers"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab Switching Logic
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const parent = tab.parentElement;
                const container = parent.parentElement;
                
                parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                const tabId = tab.getAttribute('data-tab');
                if (tabId.startsWith('resp-')) {
                    container.querySelectorAll('.resp-tab-content').forEach(c => c.classList.add('hidden'));
                } else {
                    container.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                }
                document.getElementById(tabId).classList.remove('hidden');
            });
        });

        // Dynamic Headers Logic
        const headersList = document.getElementById('headers-list');
        const addHeaderBtn = document.getElementById('add-header');

        addHeaderBtn.addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'header-row';
            row.innerHTML = `
                <input type="text" placeholder="Header Name" class="header-key">
                <input type="text" placeholder="Value" class="header-value">
                <button type="button" class="danger remove-header">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
            `;
            headersList.appendChild(row);
        });

        headersList.addEventListener('click', (e) => {
            if (e.target.closest('.remove-header')) {
                e.target.closest('.header-row').remove();
            }
        });

        // Body Type Logic
        const bodyType = document.getElementById('body-type');
        const bodyContainer = document.getElementById('body-container');
        const bodyInput = document.getElementById('body');
        const binaryHint = document.getElementById('binary-hint');

        bodyType.addEventListener('change', () => {
            binaryHint.classList.add('hidden');
            bodyInput.classList.remove('hidden');
            
            if (bodyType.value === 'none') {
                bodyContainer.classList.add('hidden');
            } else {
                bodyContainer.classList.remove('hidden');
                if (bodyType.value === 'json') {
                    bodyInput.placeholder = '{ "key": "value" }';
                } else if (bodyType.value === 'form-data' || bodyType.value === 'x-www-form-urlencoded') {
                    bodyInput.placeholder = 'key1=value1\nkey2=value2';
                } else if (bodyType.value === 'binary') {
                    bodyInput.classList.add('hidden');
                    binaryHint.classList.remove('hidden');
                } else {
                    bodyInput.placeholder = 'Enter raw text...';
                }
            }
        });

        // File Preview Logic
        const fileInput = document.getElementById('file-input');
        const convertBlob = document.getElementById('convert_blob');
        const filePreview = document.getElementById('file-preview');
        const previewInfo = document.getElementById('preview-info');
        const previewImg = document.getElementById('preview-img');
        const previewBase64 = document.getElementById('preview-base64');

        fileInput.addEventListener('change', handleFileSelect);
        convertBlob.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (!file) {
                filePreview.style.display = 'none';
                return;
            }

            filePreview.style.display = 'block';
            previewInfo.textContent = `${file.name} (${formatBytes(file.size)}) - ${file.type}`;

            if (convertBlob.checked) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        previewImg.src = e.target.result;
                        previewImg.classList.remove('hidden');
                        previewBase64.classList.add('hidden');
                    } else {
                        previewImg.classList.add('hidden');
                        previewBase64.value = e.target.result;
                        previewBase64.classList.remove('hidden');
                    }
                };
                reader.readAsDataURL(file);
            } else {
                previewImg.classList.add('hidden');
                previewBase64.classList.add('hidden');
            }
        }

        // Form Submission Logic
        document.getElementById('tester-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const spinner = document.getElementById('btn-spinner');
            const responseEmpty = document.getElementById('response-empty');
            const responseContent = document.getElementById('response-content');
            
            const method = document.getElementById('method').value;
            const targetUrl = document.getElementById('url').value;
            const bodyContent = bodyInput.value;
            const selectedBodyType = bodyType.value;
            const file = fileInput.files[0];
            const fileFieldName = document.getElementById('file-field-name').value || 'file';

            btn.disabled = true;
            spinner.classList.remove('hidden');
            btnText.textContent = 'Processing via Backend...';
            
            try {
                // We ALWAYS send to /tester/handle which acts as our proxy
                const formData = new FormData();
                formData.append('proxy_url', targetUrl);
                formData.append('proxy_method', method);
                formData.append('proxy_body_type', selectedBodyType);
                formData.append('proxy_body', bodyContent);
                formData.append('proxy_file_field', fileFieldName);

                // Add headers to form data
                document.querySelectorAll('.header-row').forEach((row, index) => {
                    const key = row.querySelector('.header-key').value.trim();
                    const val = row.querySelector('.header-value').value.trim();
                    if (key) {
                        formData.append(`proxy_headers[${key}]`, val);
                    }
                });

                if (file) {
                    formData.append('file', file);
                }

                const response = await fetch('/tester/handle', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.error) {
                    throw new Error(result.error);
                }

                // Update UI
                responseEmpty.classList.add('hidden');
                responseContent.classList.remove('hidden');
                
                const statusBadge = document.getElementById('status-badge');
                statusBadge.textContent = `${result.status}`;
                statusBadge.className = 'status-badge ' + (result.status >= 200 && result.status < 300 ? 'status-success' : 'status-error');
                
                document.getElementById('response-time').textContent = `${result.time_ms}ms`;
                document.getElementById('response-size').textContent = result.size_readable;
                
                let bodyToShow = result.body;
                if (result.is_json) {
                    try {
                        bodyToShow = JSON.stringify(JSON.parse(result.body), null, 2);
                    } catch (e) {}
                }
                
                document.getElementById('response-body').textContent = bodyToShow;
                document.getElementById('response-headers').textContent = JSON.stringify(result.headers, null, 2);

            } catch (error) {
                console.error('Error:', error);
                responseEmpty.classList.add('hidden');
                responseContent.classList.remove('hidden');
                document.getElementById('status-badge').textContent = 'Error';
                document.getElementById('status-badge').className = 'status-badge status-error';
                document.getElementById('response-body').textContent = error.message;
            } finally {
                btn.disabled = false;
                spinner.classList.add('hidden');
                btnText.textContent = 'Send via Backend Proxy';
            }
        });

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
