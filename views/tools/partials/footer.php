    <!-- Theme Switcher Engine -->
    <script>
        lucide.createIcons();
        
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('preferred_theme', theme);
            
            const sun = document.getElementById('theme-icon-sun');
            const moon = document.getElementById('theme-icon-moon');
            
            if (theme === 'light') {
                if (sun) sun.style.display = 'block';
                if (moon) moon.style.display = 'none';
            } else {
                if (sun) sun.style.display = 'none';
                if (moon) moon.style.display = 'block';
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'light' ? 'dark' : 'light';
            setTheme(target);
        }

        (function initTheme() {
            const saved = localStorage.getItem('preferred_theme');
            if (saved) {
                setTheme(saved);
            } else {
                setTheme('dark');
            }
        })();
    </script>
    <!-- Global Image Preview Modal -->
    <style>
        .global-preview-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            backdrop-filter: blur(5px);
        }
        .global-preview-modal.show { display: flex; }
        .global-preview-modal img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .global-preview-close {
            position: absolute;
            top: 1.5rem; right: 1.5rem;
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            width: 40px; height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .global-preview-close:hover { background: rgba(255,255,255,0.2); }
    </style>
    <div id="global-preview-modal" class="global-preview-modal" onclick="closeGlobalPreview(event)">
        <button class="global-preview-close" onclick="closeGlobalPreview(event)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <img id="global-preview-img" src="" alt="Preview">
    </div>
    <script>
        function openGlobalPreview(src) {
            document.getElementById('global-preview-img').src = src;
            document.getElementById('global-preview-modal').classList.add('show');
        }
        function closeGlobalPreview(e) {
            if (e.target.id === 'global-preview-modal' || e.target.closest('.global-preview-close')) {
                document.getElementById('global-preview-modal').classList.remove('show');
                document.getElementById('global-preview-img').src = '';
            }
        }
    </script>
</body>
</html>
