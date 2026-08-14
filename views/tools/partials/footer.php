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
</body>
</html>
