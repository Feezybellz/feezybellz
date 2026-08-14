<?php include __DIR__ . '/partials/header.php'; ?>
    <style>
        .extractor-container { max-width: 900px; margin: 2rem auto; background: var(--nav-bg); border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .upload-area { border: 2px dashed var(--border-color); border-radius: 0.5rem; padding: 3rem 2rem; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg-color); }
        .upload-area:hover, .upload-area.dragover { border-color: var(--primary-color); background: rgba(var(--primary-rgb), 0.05); }
        .icon-large { width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem; }
        
        .result-area { display: none; margin-top: 2rem; }
        .preview-img-container { text-align: center; margin-bottom: 2rem; }
        #preview-img { max-width: 100%; max-height: 400px; border-radius: 0.5rem; border: 1px solid var(--border-color); }
        
        .palette-container { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; }
        .color-card { flex: 1; min-width: 120px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 0.5rem; overflow: hidden; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.1s; }
        .color-card:hover { transform: translateY(-2px); }
        .color-swatch { height: 100px; width: 100%; }
        .color-info { padding: 1rem; }
        .color-hex { font-weight: bold; font-family: monospace; font-size: 1.1rem; margin-bottom: 0.25rem; }
        .color-rgb { font-size: 0.8rem; color: var(--text-muted); font-family: monospace; }
        
        .copy-toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--text-main); color: var(--bg-color); padding: 0.75rem 1.5rem; border-radius: 2rem; font-weight: bold; display: none; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>

    <div class="extractor-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Color Palette Extractor</h1>
            <p style="color: var(--text-muted);">Upload a photo and instantly extract its dominant colors.</p>
        </div>

        <div class="upload-area" id="drop-zone" onclick="document.getElementById('file-input').click()">
            <i data-lucide="aperture" class="icon-large"></i>
            <h3>Click or drag an image here</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Supports PNG, JPG, WEBP</p>
            <input type="file" id="file-input" accept="image/*" style="display: none;">
        </div>

        <div class="result-area" id="result-area">
            <div class="preview-img-container">
                <img id="preview-img" src="" alt="Preview">
            </div>
            
            <h3 style="text-align: center; margin-bottom: 1.5rem;">Dominant Palette (Click to Copy)</h3>
            <div class="palette-container" id="palette-container"></div>
        </div>
    </div>
    
    <div class="copy-toast" id="copy-toast">Copied to clipboard!</div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/color-thief/2.3.0/color-thief.umd.js"></script>
    <script>
        lucide.createIcons();
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const resultArea = document.getElementById('result-area');
        const previewImg = document.getElementById('preview-img');
        const paletteContainer = document.getElementById('palette-container');
        const copyToast = document.getElementById('copy-toast');
        
        const colorThief = new ColorThief();

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false));
        ['dragenter', 'dragover'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false));
        ['dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false));

        dropZone.addEventListener('drop', e => handleFile(e.dataTransfer.files[0]));
        fileInput.addEventListener('change', function() { handleFile(this.files[0]); });

        function handleFile(file) {
            if (!file || !file.type.startsWith('image/')) return;
            
            const url = URL.createObjectURL(file);
            previewImg.src = url;
            
            // Wait for image to load to extract colors
            previewImg.onload = () => {
                resultArea.style.display = 'block';
                dropZone.style.display = 'none';
                
                try {
                    // Get dominant color + palette of 5 colors
                    const palette = colorThief.getPalette(previewImg, 6);
                    renderPalette(palette);
                } catch (e) {
                    console.error(e);
                    alert("Error extracting colors. The image might be corrupted or too small.");
                }
            };
        }
        
        function rgbToHex(r, g, b) {
            return "#" + (1 << 24 | r << 16 | g << 8 | b).toString(16).slice(1).toUpperCase();
        }

        function renderPalette(colors) {
            paletteContainer.innerHTML = '';
            colors.forEach(rgb => {
                const r = rgb[0], g = rgb[1], b = rgb[2];
                const hex = rgbToHex(r, g, b);
                const rgbStr = `rgb(${r}, ${g}, ${b})`;
                
                const card = document.createElement('div');
                card.className = 'color-card';
                card.onclick = () => copyText(hex);
                
                card.innerHTML = `
                    <div class="color-swatch" style="background-color: ${hex};"></div>
                    <div class="color-info">
                        <div class="color-hex">${hex}</div>
                        <div class="color-rgb">${rgbStr}</div>
                    </div>
                `;
                paletteContainer.appendChild(card);
            });
        }
        
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                copyToast.innerText = `${text} copied!`;
                copyToast.style.display = 'block';
                setTimeout(() => { copyToast.style.display = 'none'; }, 2000);
            });
        }
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
