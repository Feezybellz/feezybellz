<?php 
$title = "PDF Signature Editor - Tools"; 
include __DIR__ . '/partials/header.php'; 
?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
    
    <style>
        .header { text-align: center; margin-bottom: 2rem; margin-top: 1rem; }
        .header h1 { font-size: 2.2rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: -0.025em; }
        .header p { color: var(--text-muted); font-size: 1.1rem; }

        .app-container {
            width: 100%; max-width: 1200px; margin: 0 auto;
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 1rem; overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            display: flex; flex-direction: column;
            min-height: 80vh;
        }

        /* Upload Screen */
        .upload-screen {
            flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 4rem; text-align: center;
        }
        .upload-box {
            border: 2px dashed var(--border-color); border-radius: 1rem; padding: 4rem 2rem; width: 100%; max-width: 600px;
            cursor: pointer; transition: all 0.2s; background-color: var(--bg-color);
        }
        .upload-box:hover { border-color: var(--primary-color); }
        .upload-box svg { width: 48px; height: 48px; color: var(--primary-color); margin-bottom: 1rem; }

        /* Editor Workspace */
        .editor-workspace { display: none; flex-direction: column; height: 100%; flex: 1; }
        
        .toolbar {
            display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem;
            background-color: var(--bg-color); border-bottom: 1px solid var(--border-color);
        }
        .toolbar-group { display: flex; gap: 0.5rem; }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.6rem 1.2rem; font-size: 0.95rem; font-weight: 500; border-radius: 0.5rem;
            cursor: pointer; border: none; transition: all 0.2s;
        }
        .btn-primary { background-color: var(--primary-color); color: var(--primary-text); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-outline { background-color: transparent; border: 1px solid var(--border-color); color: var(--text-main); }
        .btn-outline:hover { background-color: var(--surface-color); border-color: var(--primary-color); }

        .pdf-viewer {
            flex: 1; overflow: auto; padding: 2rem; background-color: var(--bg-page);
            text-align: center; position: relative;
        }

        .pdf-page {
            position: relative; background: #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: inline-block; text-align: left; margin: 0 auto 2rem auto;
        }
        
        .pdf-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10;
        }

        /* Draggable items */
        .draggable-item {
            position: absolute; cursor: move; user-select: none; z-index: 20;
            border: 1px solid transparent; padding: 2px;
        }
        .draggable-item:hover, .draggable-item.active { border: 1px dashed var(--primary-color); }
        .draggable-item .delete-btn {
            position: absolute; top: -10px; right: -10px; background: var(--error-color); color: #fff;
            border-radius: 50%; width: 20px; height: 20px; display: none; align-items: center; justify-content: center;
            font-size: 12px; cursor: pointer; z-index: 30; line-height: 1;
        }
        .draggable-item:hover .delete-btn, .draggable-item.active .delete-btn { display: flex; }
        
        .text-item { font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #000; min-width: 50px; min-height: 20px; white-space: pre-wrap; outline: none; }
        .signature-item img { max-width: 200px; pointer-events: none; }

        /* Modals */
        .modal-backdrop {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal {
            background: var(--surface-color); border: 1px solid var(--border-color); border-radius: 1rem;
            width: 100%; max-width: 600px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
            display: flex; flex-direction: column; overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; }
        .modal-body { padding: 1.5rem; }
        
        /* Signature Tabs */
        .sig-tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 1rem; }
        .sig-tab { padding: 0.75rem 1.5rem; cursor: pointer; color: var(--text-muted); border-bottom: 2px solid transparent; font-weight: 500; }
        .sig-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .sig-pane { display: none; }
        .sig-pane.active { display: block; }
        
        #sig-canvas { border: 1px solid var(--border-color); border-radius: 0.5rem; background: #fff; width: 100%; height: 200px; cursor: crosshair; }
        
        .saved-sigs { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
        .saved-sig-item { border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.5rem; cursor: pointer; background: #fff; position: relative; }
        .saved-sig-item:hover { border-color: var(--primary-color); }
        .saved-sig-item img { height: 60px; }
        .saved-sig-del { position: absolute; top: -8px; right: -8px; background: var(--error-color); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; }

        @media (max-width: 768px) {
            .hidden-mobile { display: none; }
            .toolbar-group { gap: 0.2rem; }
            .btn { padding: 0.4rem 0.6rem; }
        }

    </style>

    <div class="header">
        <h1>PDF Signature Editor</h1>
        <p>A powerful, browser-based PDF editor. Sign documents securely, add text, and save signatures locally.</p>
    </div>

    <div class="app-container">
        <!-- Upload Screen -->
        <div class="upload-screen" id="uploadScreen">
            <input type="file" id="pdfInput" accept="application/pdf" style="display: none;">
            <div class="upload-box" onclick="document.getElementById('pdfInput').click()">
                <i data-lucide="upload-cloud"></i>
                <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Upload PDF Document</h3>
                <p style="color: var(--text-muted);">Click to browse or drag and drop a PDF file here.</p>
                <p style="color: var(--success-color); margin-top: 1rem; font-size: 0.9rem;">
                    <i data-lucide="shield-check" style="width: 16px; height: 16px; display: inline; vertical-align: text-bottom;"></i>
                    Files are processed entirely in your browser. No data is sent to our servers.
                </p>
            </div>
        </div>

        <!-- Editor Workspace -->
        <div class="editor-workspace" id="editorWorkspace">
            <div class="toolbar">
                <div class="toolbar-group">
                    <button class="btn btn-outline" id="btn-add-text" title="Add Text"><i data-lucide="type"></i> <span class="hidden-mobile">Text</span></button>
                    <button class="btn btn-outline" id="btn-add-image" title="Add Image"><i data-lucide="image"></i> <span class="hidden-mobile">Image</span></button>
                    <button class="btn btn-outline" id="btn-sign" title="Sign"><i data-lucide="pen-tool"></i> <span class="hidden-mobile">Sign</span></button>
                    <button class="btn btn-outline" id="btn-whiteout" title="Whiteout"><i data-lucide="eraser"></i> <span class="hidden-mobile">Whiteout</span></button>
                    
                    <!-- Additional Sejda Tools (Stubbed) -->
                    <button class="btn btn-outline" id="btn-links" title="Links (Coming Soon)" onclick="DialogBox.show({ title: 'Pro Feature', message: 'Link editing is coming in a future update.', type: 'info', confirmText: 'Got It', showCancel: false })"><i data-lucide="link"></i></button>
                    <button class="btn btn-outline" id="btn-forms" title="Forms (Coming Soon)" onclick="DialogBox.show({ title: 'Pro Feature', message: 'Form creation is coming in a future update.', type: 'info', confirmText: 'Got It', showCancel: false })"><i data-lucide="list"></i></button>
                    <button class="btn btn-outline" id="btn-shapes" title="Shapes (Coming Soon)" onclick="DialogBox.show({ title: 'Pro Feature', message: 'Shapes are coming in a future update.', type: 'info', confirmText: 'Got It', showCancel: false })"><i data-lucide="square"></i></button>

                    <div style="width: 1px; height: 24px; background: var(--border-color); margin: 0 0.5rem;"></div>
                    
                    <button class="btn btn-outline" id="btn-undo" title="Undo (Ctrl+Z)"><i data-lucide="undo"></i></button>
                    <button class="btn btn-outline" id="btn-redo" title="Redo (Ctrl+Y)"><i data-lucide="redo"></i></button>
                </div>
                <div class="toolbar-group">
                    <button class="btn btn-primary" id="btn-export"><i data-lucide="download"></i> Apply & Download</button>
                </div>
            </div>
            <div class="text-formatting-bar" id="textFormattingBar" style="display: none; position: fixed; z-index: 2000; padding: 0.5rem 1.5rem; background-color: var(--nav-dock-bg); backdrop-filter: blur(8px); border: 1px solid var(--border-color); border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); align-items: center; transition: none; gap: 0.5rem;">
                <!-- Font & Size & Color -->
                <div style="display: flex; gap: 0.5rem; align-items: center; border-right: 1px solid var(--border-color); padding-right: 0.5rem;">
                    <select id="fmt-font" class="fmt-control" style="padding: 0.3rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit;">
                        <option value="Helvetica">Helvetica</option>
                        <option value="Times-Roman">Times Roman</option>
                        <option value="Courier">Courier</option>
                    </select>
                    <input type="number" id="fmt-size" class="fmt-control" value="16" min="8" max="72" style="width: 70px; padding: 0.3rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit;">
                    <input type="color" id="fmt-color" class="fmt-control" value="#000000" style="padding: 0; width: 30px; height: 30px; border: none; background: transparent; cursor: pointer;">
                </div>

                <!-- Text Style -->
                <div style="display: flex; gap: 0.2rem; align-items: center; border-right: 1px solid var(--border-color); padding-right: 0.5rem;">
                    <button class="btn btn-outline" id="fmt-bold" title="Bold" style="padding: 0.4rem; border: none; background: transparent;"><i data-lucide="bold" style="width: 18px; height: 18px;"></i></button>
                    <button class="btn btn-outline" id="fmt-italic" title="Italic" style="padding: 0.4rem; border: none; background: transparent;"><i data-lucide="italic" style="width: 18px; height: 18px;"></i></button>
                    <button class="btn btn-outline" id="fmt-underline" title="Underline" style="padding: 0.4rem; border: none; background: transparent;"><i data-lucide="underline" style="width: 18px; height: 18px;"></i></button>
                    <button class="btn btn-outline" id="fmt-strike" title="Strikethrough" style="padding: 0.4rem; border: none; background: transparent;"><i data-lucide="strikethrough" style="width: 18px; height: 18px;"></i></button>
                </div>
                
                <!-- Actions -->
                <div style="display: flex; gap: 0.2rem; align-items: center;">
                    <button class="btn btn-outline" id="fmt-duplicate" title="Duplicate" style="padding: 0.4rem; border: none; background: transparent;"><i data-lucide="copy" style="width: 18px; height: 18px;"></i></button>
                    <button class="btn btn-outline" id="fmt-delete" title="Delete" style="padding: 0.4rem; border: none; background: transparent; color: var(--error-color);"><i data-lucide="trash-2" style="width: 18px; height: 18px;"></i></button>
                </div>
            </div>
            <div class="pdf-viewer" id="pdfViewer">
                <!-- PDF Pages will be injected here -->
            </div>
        </div>
    </div>

    <!-- Signature Modal -->
    <div class="modal-backdrop" id="sigModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Create Signature</h3>
                <button class="modal-close" onclick="document.getElementById('sigModal').style.display='none'"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <div class="sig-tabs">
                    <div class="sig-tab active" onclick="switchSigTab('draw')">Draw</div>
                    <div class="sig-tab" onclick="switchSigTab('saved')">Saved</div>
                </div>
                
                <div class="sig-pane active" id="pane-draw">
                    <canvas id="sig-canvas"></canvas>
                    <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-outline" onclick="signaturePad.clear(); sigHistory=[]; sigRedoStack=[];" style="padding: 0.4rem 0.8rem;" title="Clear">Clear</button>
                            <button class="btn btn-outline" id="sig-undo" onclick="undoSignature()" style="padding: 0.4rem;" title="Undo (Ctrl+Z)"><i data-lucide="undo"></i></button>
                            <button class="btn btn-outline" id="sig-redo" onclick="redoSignature()" style="padding: 0.4rem;" title="Redo (Ctrl+Y)"><i data-lucide="redo"></i></button>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <label style="font-size: 0.9rem; cursor: pointer; color: var(--text-muted);">
                                <input type="checkbox" id="saveSigCheck" checked> Save signature
                            </label>
                            <button class="btn btn-primary" onclick="useDrawnSignature()">Use Signature</button>
                        </div>
                    </div>
                </div>

                <div class="sig-pane" id="pane-saved">
                    <div class="saved-sigs" id="savedSigsContainer">
                        <!-- Saved signatures loaded here -->
                    </div>
                    <p id="noSavedSigsMsg" style="color: var(--text-muted); display: none;">No saved signatures found.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // State
        let pdfDoc = null;
        let pdfBytes = null;
        let currentPage = 1;
        let scale = 1.5;
        let signaturePad = null;
        let sigHistory = [];
        let sigRedoStack = [];
        let pdfHistory = [];
        let pdfHistoryIndex = -1;

        // Elements
        const pdfInput = document.getElementById('pdfInput');
        const uploadScreen = document.getElementById('uploadScreen');
        const editorWorkspace = document.getElementById('editorWorkspace');
        const pdfViewer = document.getElementById('pdfViewer');
        const sigModal = document.getElementById('sigModal');
        const canvasSig = document.getElementById('sig-canvas');
        const formattingBar = document.getElementById('textFormattingBar');
        let activeTextItem = null;
        let pendingTool = null;
        
        function updateFormattingBarPosition() {
            if (!activeTextItem || formattingBar.style.display === 'none') return;
            const rect = activeTextItem.getBoundingClientRect();
            formattingBar.style.left = Math.max(10, rect.left) + 'px';
            formattingBar.style.top = Math.max(10, rect.top - formattingBar.offsetHeight - 10) + 'px';
        }

        function updateFormattingButtons(el) {
            document.getElementById('fmt-bold').style.background = el.style.fontWeight === 'bold' ? 'var(--border-color)' : 'transparent';
            document.getElementById('fmt-italic').style.background = el.style.fontStyle === 'italic' ? 'var(--border-color)' : 'transparent';
            document.getElementById('fmt-underline').style.background = (el.style.textDecoration || '').includes('underline') ? 'var(--border-color)' : 'transparent';
            document.getElementById('fmt-strike').style.background = (el.style.textDecoration || '').includes('line-through') ? 'var(--border-color)' : 'transparent';
        }

        function activateItem(item) {
            document.querySelectorAll('.draggable-item').forEach(el => el.classList.remove('active'));
            if (item) item.classList.add('active');
            
            if (item && item.classList.contains('text-item')) {
                activeTextItem = item;
                formattingBar.style.display = 'flex';
                // Update controls to match item
                const textDiv = activeTextItem.querySelector('div[contenteditable]');
                document.getElementById('fmt-font').value = textDiv.dataset.font || 'Helvetica';
                document.getElementById('fmt-size').value = parseInt(textDiv.style.fontSize) || 16;
                
                let col = textDiv.style.color || '#000000';
                if (col.startsWith('rgb')) {
                    const rgb = col.match(/\d+/g);
                    col = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
                }
                document.getElementById('fmt-color').value = col;
                
                updateFormattingButtons(textDiv);
                updateFormattingBarPosition();
            } else {
                activeTextItem = null;
                formattingBar.style.display = 'none';
            }
        }

        window.addEventListener('scroll', updateFormattingBarPosition, true);
        window.addEventListener('resize', updateFormattingBarPosition);

        // Initialize Signature Pad
        function initSignaturePad() {
            const canvas = document.getElementById('sig-canvas');
            // Adjust canvas size
            canvas.width = canvas.parentElement.offsetWidth;
            canvas.height = 200;
            if (signaturePad) signaturePad.off();
            signaturePad = new SignaturePad(canvas, {
                penColor: 'rgb(0, 0, 0)',
                backgroundColor: 'rgba(0,0,0,0)'
            });
            signaturePad.addEventListener("endStroke", () => {
                sigHistory.push(signaturePad.toData());
                sigRedoStack = [];
            });
            sigHistory = [];
            sigRedoStack = [];
        }

        function undoSignature() {
            if (sigHistory.length > 0) {
                sigRedoStack.push(sigHistory.pop());
                const data = sigHistory.length > 0 ? sigHistory[sigHistory.length - 1] : [];
                signaturePad.fromData(data);
            }
        }
        function redoSignature() {
            if (sigRedoStack.length > 0) {
                const data = sigRedoStack.pop();
                sigHistory.push(data);
                signaturePad.fromData(data);
            }
        }

        // PDF Undo/Redo Logic
        function savePDFState() {
            const state = [];
            document.querySelectorAll('.pdf-page').forEach(page => {
                const overlay = page.querySelector('.pdf-overlay');
                state.push(overlay ? overlay.innerHTML : '');
            });
            
            if (pdfHistoryIndex < pdfHistory.length - 1) {
                pdfHistory = pdfHistory.slice(0, pdfHistoryIndex + 1);
            }
            pdfHistory.push(state);
            pdfHistoryIndex = pdfHistory.length - 1;
        }

        function undoPDF() {
            if (pdfHistoryIndex > 0) {
                pdfHistoryIndex--;
                restorePDFState(pdfHistory[pdfHistoryIndex]);
            }
        }

        function redoPDF() {
            if (pdfHistoryIndex < pdfHistory.length - 1) {
                pdfHistoryIndex++;
                restorePDFState(pdfHistory[pdfHistoryIndex]);
            }
        }

        function restorePDFState(state) {
            document.querySelectorAll('.pdf-page').forEach((page, i) => {
                const overlay = page.querySelector('.pdf-overlay');
                if (overlay && state[i] !== undefined) {
                    overlay.innerHTML = state[i];
                }
            });
            activeTextItem = null;
            formattingBar.style.display = 'none';
        }

        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
            const cmdOrCtrl = isMac ? e.metaKey : e.ctrlKey;
            
            if (cmdOrCtrl && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                const isSigOpen = sigModal.style.display === 'flex';
                if (e.shiftKey) {
                    isSigOpen ? redoSignature() : redoPDF();
                } else {
                    isSigOpen ? undoSignature() : undoPDF();
                }
            }
            if (cmdOrCtrl && e.key.toLowerCase() === 'y') {
                e.preventDefault();
                const isSigOpen = sigModal.style.display === 'flex';
                isSigOpen ? redoSignature() : redoPDF();
            }
        });

        // Handle File Upload
        pdfInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file && file.type === 'application/pdf') {
                const arrayBuffer = await file.arrayBuffer();
                pdfBytes = arrayBuffer.slice(0); // clone for pdf-lib later
                
                uploadScreen.style.display = 'none';
                editorWorkspace.style.display = 'flex';
                
                const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                pdfDoc = await loadingTask.promise;
                
                renderAllPages();
            }
        });

        // Render PDF pages
        async function renderAllPages() {
            pdfViewer.innerHTML = '';
            for (let num = 1; num <= pdfDoc.numPages; num++) {
                const page = await pdfDoc.getPage(num);
                const viewport = page.getViewport({ scale: scale });
                
                // Container
                const pageContainer = document.createElement('div');
                pageContainer.className = 'pdf-page';
                pageContainer.dataset.page = num;
                pageContainer.style.width = viewport.width + 'px';
                pageContainer.style.height = viewport.height + 'px';
                
                // Canvas
                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                pageContainer.appendChild(canvas);
                
                // Overlay
                const overlay = document.createElement('div');
                overlay.className = 'pdf-overlay';
                pageContainer.appendChild(overlay);
                
                pdfViewer.appendChild(pageContainer);
                
                const renderContext = {
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                };
                await page.render(renderContext).promise;
            }
            initInteract();
            savePDFState(); // Save initial blank state
        }

        // Draggable Logic using Interact.js
        function initInteract() {
            interact('.draggable-item').draggable({
                inertia: true,
                modifiers: [
                    interact.modifiers.restrictRect({
                        restriction: 'parent',
                        endOnly: true
                    })
                ],
                autoScroll: true,
                listeners: {
                    move(event) {
                        const target = event.target;
                        const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
                        const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;

                        target.style.transform = `translate(${x}px, ${y}px)`;
                        target.setAttribute('data-x', x);
                        target.setAttribute('data-y', y);
                        
                        if (target === activeTextItem) {
                            updateFormattingBarPosition();
                        }
                    }
                }
            }).on('down', function (event) {
                if (event.currentTarget !== activeTextItem) {
                    activateItem(event.currentTarget);
                }
            }).on('up', function (event) {
                savePDFState(); // Save state after drag ends
            });

            // Handle contenteditable blur for saving state
            document.addEventListener('blur', (e) => {
                if (e.target.hasAttribute('contenteditable')) {
                    savePDFState();
                }
            }, true);

            // Handle item activation globally on mousedown (faster response than click)
            document.addEventListener('mousedown', (e) => {
                // If clicking a tool in the toolbar, don't hide the active item yet
                if (e.target.closest('.toolbar') || e.target.closest('.text-formatting-bar') || e.target.closest('.delete-btn')) {
                    return;
                }
                
                const item = e.target.closest('.draggable-item');
                if (item && item !== activeTextItem) {
                    activateItem(item);
                } else if (!item) {
                    activateItem(null);
                }
            });

            // Delete item button logic
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('delete-btn') || e.target.closest('.delete-btn')) {
                    const item = e.target.closest('.draggable-item');
                    if (item) {
                        if (item === activeTextItem) {
                            activateItem(null);
                        }
                        item.remove();
                        savePDFState();
                    }
                }
            });
            
            // Formatting Controls Event Listeners
            document.getElementById('fmt-font').addEventListener('change', (e) => {
                if (activeTextItem) {
                    const textDiv = activeTextItem.querySelector('div[contenteditable]');
                    textDiv.dataset.font = e.target.value;
                    let cssFont = 'Helvetica, sans-serif';
                    if (e.target.value === 'Times-Roman') cssFont = '"Times New Roman", serif';
                    if (e.target.value === 'Courier') cssFont = '"Courier New", monospace';
                    textDiv.style.fontFamily = cssFont;
                }
            });
            document.getElementById('fmt-size').addEventListener('input', (e) => {
                if (activeTextItem) {
                    activeTextItem.querySelector('div[contenteditable]').style.fontSize = e.target.value + 'px';
                }
            });
            document.getElementById('fmt-color').addEventListener('input', (e) => {
                if (activeTextItem) {
                    activeTextItem.querySelector('div[contenteditable]').style.color = e.target.value;
                }
            });
            
            document.getElementById('fmt-bold').addEventListener('click', () => {
                if(activeTextItem) {
                    const el = activeTextItem.querySelector('div[contenteditable]');
                    el.style.fontWeight = el.style.fontWeight === 'bold' ? 'normal' : 'bold';
                    updateFormattingButtons(el);
                    savePDFState();
                }
            });
            document.getElementById('fmt-italic').addEventListener('click', () => {
                if(activeTextItem) {
                    const el = activeTextItem.querySelector('div[contenteditable]');
                    el.style.fontStyle = el.style.fontStyle === 'italic' ? 'normal' : 'italic';
                    updateFormattingButtons(el);
                    savePDFState();
                }
            });
            document.getElementById('fmt-underline').addEventListener('click', () => {
                if(activeTextItem) {
                    const el = activeTextItem.querySelector('div[contenteditable]');
                    const isSet = (el.style.textDecoration || '').includes('underline');
                    let newDec = (el.style.textDecoration || '').replace('underline', '').trim();
                    if (!isSet) newDec += ' underline';
                    el.style.textDecoration = newDec.trim();
                    updateFormattingButtons(el);
                    savePDFState();
                }
            });
            document.getElementById('fmt-strike').addEventListener('click', () => {
                if(activeTextItem) {
                    const el = activeTextItem.querySelector('div[contenteditable]');
                    const isSet = (el.style.textDecoration || '').includes('line-through');
                    let newDec = (el.style.textDecoration || '').replace('line-through', '').trim();
                    if (!isSet) newDec += ' line-through';
                    el.style.textDecoration = newDec.trim();
                    updateFormattingButtons(el);
                    savePDFState();
                }
            });
            document.getElementById('fmt-delete').addEventListener('click', () => {
                if(activeTextItem) {
                    activeTextItem.remove();
                    activeTextItem = null;
                    formattingBar.style.display = 'none';
                    savePDFState();
                }
            });
            document.getElementById('fmt-duplicate').addEventListener('click', () => {
                if(activeTextItem) {
                    const clone = activeTextItem.cloneNode(true);
                    const currentX = parseFloat(clone.getAttribute('data-x')) || 0;
                    const currentY = parseFloat(clone.getAttribute('data-y')) || 0;
                    const newX = currentX + 20;
                    const newY = currentY + 20;
                    
                    clone.setAttribute('data-x', newX);
                    clone.setAttribute('data-y', newY);
                    clone.style.transform = `translate(${newX}px, ${newY}px)`;
                    clone.classList.remove('active');
                    
                    activeTextItem.parentElement.appendChild(clone);
                    lucide.createIcons();
                    savePDFState();
                    
                    // Activate clone
                    setTimeout(() => clone.dispatchEvent(new Event('mousedown')), 50);
                }
            });

            document.getElementById('fmt-color').addEventListener('change', (e) => savePDFState());
            document.getElementById('fmt-font').addEventListener('change', (e) => savePDFState());
            document.getElementById('fmt-size').addEventListener('change', (e) => savePDFState());
        }

        function setPendingTool(type, contentHTML) {
            pendingTool = { type, contentHTML };
            document.querySelectorAll('.pdf-overlay').forEach(el => el.style.cursor = 'crosshair');
        }

        document.getElementById('pdfViewer').addEventListener('mousedown', (e) => {
            if (pendingTool && e.target.classList.contains('pdf-overlay')) {
                const overlay = e.target;
                const rect = overlay.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                createDraggableItemAt(overlay, pendingTool.contentHTML, pendingTool.type, x, y);
                
                pendingTool = null;
                document.querySelectorAll('.pdf-overlay').forEach(el => el.style.cursor = '');
            }
        });

        function createDraggableItemAt(overlay, contentHTML, type, x, y) {
            const item = document.createElement('div');
            item.className = `draggable-item ${type}-item active`;
            item.innerHTML = contentHTML;
            item.setAttribute('data-type', type);
            item.setAttribute('data-x', x);
            item.setAttribute('data-y', y);
            item.style.transform = `translate(${x}px, ${y}px)`;
            
            const delBtn = document.createElement('div');
            delBtn.className = 'delete-btn';
            delBtn.innerHTML = '<i data-lucide="x"></i>';
            item.appendChild(delBtn);
            
            overlay.appendChild(item);
            lucide.createIcons();
            savePDFState();

            if (type === 'text') {
                setTimeout(() => {
                    const event = new Event('mousedown');
                    item.dispatchEvent(event);
                    const editable = item.querySelector('div[contenteditable]');
                    if(editable) editable.focus();
                }, 50);
            }
        }

        // Add Text
        document.getElementById('btn-add-text').addEventListener('click', () => {
            const content = `<div contenteditable="true" data-font="Helvetica" style="padding: 5px; min-width: 100px; outline: none; line-height: 1.2; font-family: Helvetica, sans-serif; font-size: 16px; color: #000000;">Type text here</div>`;
            setPendingTool('text', content);
        });

        document.getElementById('btn-add-image').addEventListener('click', () => {
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*';
            fileInput.onchange = (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        const img = new Image();
                        img.onload = () => {
                            const cvs = document.createElement('canvas');
                            cvs.width = img.width; cvs.height = img.height;
                            cvs.getContext('2d').drawImage(img, 0, 0);
                            const content = `<img src="${cvs.toDataURL('image/png')}" style="width: 150px; height: auto;">`;
                            setPendingTool('signature', content);
                        };
                        img.src = ev.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            };
            fileInput.click();
        });

        document.getElementById('btn-whiteout').addEventListener('click', () => {
            const content = `<div style="width: 150px; height: 30px; background-color: #ffffff;"></div>`;
            setPendingTool('whiteout', content);
        });

        document.getElementById('btn-undo').addEventListener('click', undoPDF);
        document.getElementById('btn-redo').addEventListener('click', redoPDF);

        // Sign Modal
        document.getElementById('btn-sign').addEventListener('click', () => {
            sigModal.style.display = 'flex';
            setTimeout(initSignaturePad, 50);
            loadSavedSignatures();
        });

        function switchSigTab(tab) {
            document.querySelectorAll('.sig-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sig-pane').forEach(p => p.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('pane-' + tab).classList.add('active');
        }

        function useDrawnSignature() {
            if (signaturePad.isEmpty()) return;
            const dataUrl = signaturePad.toDataURL('image/png');
            
            if (document.getElementById('saveSigCheck').checked) {
                saveSignatureLocal(dataUrl);
            }
            
            addSignatureToPDF(dataUrl);
            sigModal.style.display = 'none';
        }

        function addSignatureToPDF(dataUrl) {
            const content = `<img src="${dataUrl}" style="width: 150px; height: auto;">`;
            setPendingTool('signature', content);
        }

        // Local Storage Signatures
        function saveSignatureLocal(dataUrl) {
            let sigs = JSON.parse(localStorage.getItem('saved_signatures') || '[]');
            sigs.push(dataUrl);
            localStorage.setItem('saved_signatures', JSON.stringify(sigs));
        }

        function loadSavedSignatures() {
            let sigs = JSON.parse(localStorage.getItem('saved_signatures') || '[]');
            const container = document.getElementById('savedSigsContainer');
            const msg = document.getElementById('noSavedSigsMsg');
            
            container.innerHTML = '';
            if (sigs.length === 0) {
                msg.style.display = 'block';
            } else {
                msg.style.display = 'none';
                sigs.forEach((sig, index) => {
                    const div = document.createElement('div');
                    div.className = 'saved-sig-item';
                    div.innerHTML = `
                        <img src="${sig}">
                        <div class="saved-sig-del" onclick="deleteSavedSig(${index}, event)"><i data-lucide="trash-2"></i></div>
                    `;
                    div.onclick = (e) => {
                        if (e.target.closest('.saved-sig-del')) return;
                        addSignatureToPDF(sig);
                        sigModal.style.display = 'none';
                    };
                    container.appendChild(div);
                });
                lucide.createIcons();
            }
        }

        window.deleteSavedSig = function(index, e) {
            e.stopPropagation();
            let sigs = JSON.parse(localStorage.getItem('saved_signatures') || '[]');
            sigs.splice(index, 1);
            localStorage.setItem('saved_signatures', JSON.stringify(sigs));
            loadSavedSignatures();
        }

        // Export PDF using pdf-lib
        document.getElementById('btn-export').addEventListener('click', async () => {
            if (!pdfBytes) return;
            
            const btn = document.getElementById('btn-export');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Processing...';
            btn.disabled = true;

            try {
                const pdfLibDoc = await PDFLib.PDFDocument.load(pdfBytes);
                const pages = pdfLibDoc.getPages();
                const font = await pdfLibDoc.embedFont(PDFLib.StandardFonts.Helvetica);

                // Iterate over all page containers
                document.querySelectorAll('.pdf-page').forEach(async (pageContainer) => {
                    const pageNum = parseInt(pageContainer.dataset.page);
                    const pdfPage = pages[pageNum - 1];
                    const { width, height } = pdfPage.getSize();
                    
                    // Scaling factor between rendered DOM and actual PDF
                    const containerWidth = pageContainer.offsetWidth;
                    const containerHeight = pageContainer.offsetHeight;
                    const scaleX = width / containerWidth;
                    const scaleY = height / containerHeight;

                    // Find all items on this page
                    const items = pageContainer.querySelectorAll('.draggable-item');
                    for (let item of items) {
                        const type = item.getAttribute('data-type');
                        const transform = item.style.transform;
                        const match = transform.match(/translate\(([^px]+)px, ([^px]+)px\)/);
                        let x = 0, y = 0;
                        if (match) {
                            x = parseFloat(match[1]);
                            y = parseFloat(match[2]);
                        }
                        
                        if (type === 'text') {
                            const textDiv = item.querySelector('div[contenteditable]');
                            const text = textDiv.innerText;
                            const sizeVal = parseFloat(textDiv.style.fontSize) || 16;
                            const fontSize = sizeVal * scaleY;
                            
                            // Parse color
                            let r = 0, g = 0, b = 0;
                            let col = textDiv.style.color || 'rgb(0, 0, 0)';
                            if (col.startsWith('rgb')) {
                                const rgb = col.match(/\d+/g);
                                r = parseInt(rgb[0]) / 255;
                                g = parseInt(rgb[1]) / 255;
                                b = parseInt(rgb[2]) / 255;
                            } else if (col.startsWith('#')) {
                                r = parseInt(col.substr(1,2), 16) / 255;
                                g = parseInt(col.substr(3,2), 16) / 255;
                                b = parseInt(col.substr(5,2), 16) / 255;
                            }
                            
                            const isBold = textDiv.style.fontWeight === 'bold';
                            const isItalic = textDiv.style.fontStyle === 'italic';
                            const isUnderline = (textDiv.style.textDecoration || '').includes('underline');
                            const isStrike = (textDiv.style.textDecoration || '').includes('line-through');

                            // Load selected font
                            let fontType = PDFLib.StandardFonts.Helvetica;
                            const fontName = textDiv.dataset.font || 'Helvetica';
                            
                            if (fontName === 'Helvetica') {
                                if (isBold && isItalic) fontType = PDFLib.StandardFonts.HelveticaBoldOblique;
                                else if (isBold) fontType = PDFLib.StandardFonts.HelveticaBold;
                                else if (isItalic) fontType = PDFLib.StandardFonts.HelveticaOblique;
                            } else if (fontName === 'Times-Roman') {
                                if (isBold && isItalic) fontType = PDFLib.StandardFonts.TimesRomanBoldItalic;
                                else if (isBold) fontType = PDFLib.StandardFonts.TimesRomanBold;
                                else if (isItalic) fontType = PDFLib.StandardFonts.TimesRomanItalic;
                            } else if (fontName === 'Courier') {
                                if (isBold && isItalic) fontType = PDFLib.StandardFonts.CourierBoldOblique;
                                else if (isBold) fontType = PDFLib.StandardFonts.CourierBold;
                                else if (isItalic) fontType = PDFLib.StandardFonts.CourierOblique;
                            }
                            const pdfFont = await pdfLibDoc.embedFont(fontType);

                            // PDF Y is from bottom left
                            const pdfY = height - (y * scaleY) - fontSize;
                            const pdfX = x * scaleX;
                            
                            pdfPage.drawText(text, {
                                x: pdfX,
                                y: pdfY,
                                size: fontSize,
                                font: pdfFont,
                                color: PDFLib.rgb(r, g, b),
                            });

                            const textWidth = pdfFont.widthOfTextAtSize(text, fontSize);
                            if (isUnderline) {
                                pdfPage.drawLine({
                                    start: { x: pdfX, y: pdfY - 2 },
                                    end: { x: pdfX + textWidth, y: pdfY - 2 },
                                    thickness: Math.max(1, fontSize * 0.05),
                                    color: PDFLib.rgb(r, g, b)
                                });
                            }
                            if (isStrike) {
                                pdfPage.drawLine({
                                    start: { x: pdfX, y: pdfY + fontSize * 0.35 },
                                    end: { x: pdfX + textWidth, y: pdfY + fontSize * 0.35 },
                                    thickness: Math.max(1, fontSize * 0.05),
                                    color: PDFLib.rgb(r, g, b)
                                });
                            }
                        } else if (type === 'signature') {
                            const img = item.querySelector('img');
                            const imgWidth = img.offsetWidth * scaleX;
                            const imgHeight = img.offsetHeight * scaleY;
                            
                            const pdfY = height - (y * scaleY) - imgHeight;
                            const pdfX = x * scaleX;
                            
                            const imgBytes = await fetch(img.src).then(res => res.arrayBuffer());
                            const pdfImage = await pdfLibDoc.embedPng(imgBytes);
                            
                            pdfPage.drawImage(pdfImage, {
                                x: pdfX,
                                y: pdfY,
                                width: imgWidth,
                                height: imgHeight,
                            });
                        } else if (type === 'whiteout') {
                            const rect = item.querySelector('div');
                            const rectWidth = rect.offsetWidth * scaleX;
                            const rectHeight = rect.offsetHeight * scaleY;
                            const pdfY = height - (y * scaleY) - rectHeight;
                            const pdfX = x * scaleX;
                            
                            pdfPage.drawRectangle({
                                x: pdfX,
                                y: pdfY,
                                width: rectWidth,
                                height: rectHeight,
                                color: PDFLib.rgb(1, 1, 1),
                            });
                        }
                    }
                });

                const pdfBytesModified = await pdfLibDoc.save();
                
                // Trigger download
                const blob = new Blob([pdfBytesModified], { type: "application/pdf" });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = "signed_document.pdf";
                link.click();
                
            } catch (err) {
                console.error(err);
                if (typeof DialogBox !== 'undefined') {
                    DialogBox.show({ title: 'Error', message: 'Failed to generate PDF.', type: 'danger', confirmText: 'Close', showCancel: false });
                } else {
                    alert('Failed to generate PDF.');
                }
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>

