/**
 * Shelteer IconPicker
 * A searchable icon selection library for Lucide Icons & FontAwesome.
 */
class IconPicker {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        this.options = {
            onSelect: options.onSelect || null,
            placeholder: 'Search from icons...',
            ...options
        };

        IconPicker.init(); // Ensure assets are injected
        this.iconList = [];
        this._loadIcons();

        this._initLibrary();
    }

    static init() {
        // Inject Assets
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const fa = document.createElement("link");
            fa.rel = "stylesheet";
            fa.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css";
            document.head.appendChild(fa);
        }
        
        const initLucide = () => {
            if (window.lucide && typeof window.lucide.createIcons === 'function' && !window.lucide.__stub) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
                } else {
                    lucide.createIcons();
                }
            }
        };

        if (!window.lucide && !document.querySelector('script[src*="lucide"]')) {
            window.lucide = {
                __stub: true,
                createIcons: function() {
                    window.__lucide_create_icons_queued = true;
                }
            };

            const lucideScript = document.createElement("script");
            lucideScript.src = "https://unpkg.com/lucide@latest";
            lucideScript.onload = () => {
                if (window.__lucide_create_icons_queued && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
                initLucide();
            };
            document.head.appendChild(lucideScript);
        } else {
            initLucide();
        }

        // Inject Styles
        if (!document.getElementById('sv-icon-picker-styles')) {
            const style = document.createElement('style');
            style.id = 'sv-icon-picker-styles';
            style.textContent = `
                .ip-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px);
                    display: flex; align-items: center; justify-content: center;
                    z-index: 10000; opacity: 0; visibility: hidden; transition: all 0.3s ease;
                }
                .ip-overlay.active { opacity: 1; visibility: visible; }
                .ip-modal {
                    background: #fff; width: 90%; max-width: 550px; border-radius: 2rem;
                    padding: 2.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    transform: scale(0.95); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                    display: flex; flex-direction: column; max-height: 85vh;
                }
                .ip-overlay.active .ip-modal { transform: scale(1); }
                .ip-header { margin-bottom: 2rem; }
                .ip-header h3 { margin: 0 0 0.75rem 0; font-size: 1.5rem; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: -0.025em; }
                .ip-search-container { position: relative; }
                .ip-search { width: 100%; padding: 1.25rem 1.5rem; background: #f8fafc; border: 2px solid #f1f5f9; border-radius: 1.25rem; outline: none; font-weight: 700; font-size: 1rem; transition: all 0.2s; color: #0f172a; }
                .ip-search:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
                .ip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 1rem; overflow-y: auto; padding: 0.5rem; margin-top: 0.5rem; flex: 1; scrollbar-width: thin; }
                .ip-grid::-webkit-scrollbar { width: 6px; }
                .ip-grid::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
                .ip-item {
                    display: flex; flex-direction: column; align-items: center; justify-content: center;
                    aspect-ratio: 1; border-radius: 1.25rem; border: 2px solid #f1f5f9;
                    cursor: pointer; transition: all 0.2s; color: #64748b; background: #f8fafc;
                }
                .ip-item:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.1); }
                .ip-item i { width: 28px; height: 24px; }
                .ip-no-results { grid-column: 1 / -1; text-align: center; padding: 4rem 0; color: #94a3b8; font-weight: 700; font-size: 1rem; }
            `;
            document.head.appendChild(style);
        }
    }

    _initLibrary() {
        if (this.element) {
            this.element.style.cursor = 'pointer';
            this.element.addEventListener('click', (e) => {
                e.preventDefault();
                this.openPicker();
            });
        }
    }

    _loadIcons() {
        const fontAwesomeIcons = [
            'fa-brands fa-facebook', 'fa-brands fa-instagram', 'fa-brands fa-twitter', 
            'fa-brands fa-x-twitter', 'fa-brands fa-linkedin', 'fa-brands fa-youtube',
            'fa-brands fa-tiktok', 'fa-brands fa-github'
        ];

        if (window.lucide && lucide.icons) {
            this.iconList = [...Object.keys(lucide.icons), ...fontAwesomeIcons];
        } else {
            this.iconList = ['home', 'shield', 'zap', 'globe', ...fontAwesomeIcons];
            console.warn('[IconPicker] Lucide icons not found on window. Using limited list.');
        }
    }

    openPicker() {
        this._loadIcons();

        // Always remove old overlay to ensure fresh bindings for this specific instance
        const oldOverlay = document.querySelector(".ip-overlay");
        if (oldOverlay) {
            oldOverlay.remove();
        }

        const overlay = document.createElement("div");
        overlay.className = "ip-overlay";
        overlay.innerHTML = `
            <div class="ip-modal">
                <div class="ip-header">
                    <h3>Select Icon</h3>
                    <div class="ip-search-container">
                        <input type="text" class="ip-search" placeholder="${this.options.placeholder}">
                    </div>
                </div>
                <div class="ip-grid"></div>
            </div>
        `;
        document.body.appendChild(overlay);

        overlay.onclick = (e) => {
            if (e.target === overlay) this.closePicker();
        };

        const searchInput = overlay.querySelector(".ip-search");
        // Clear input on open
        searchInput.value = "";
        searchInput.oninput = (e) => this.filterIcons(e.target.value);

        // Focus search on open
        setTimeout(() => searchInput.focus(), 100);

        this.renderIcons(this.iconList);
        overlay.classList.add("active");

        if (window.lucide) lucide.createIcons();
    }

    closePicker() {
        const overlay = document.querySelector(".ip-overlay");
        if (overlay) overlay.classList.remove("active");
    }

    filterIcons(query) {
        const q = query.toLowerCase();
        const filtered = this.iconList.filter((icon) =>
            icon.toLowerCase().includes(q),
        );
        this.renderIcons(filtered);
    }

    renderIcons(list) {
        const grid = document.querySelector(".ip-grid");
        if (!grid) return;

        grid.innerHTML = "";

        if (list.length === 0) {
            grid.innerHTML =
                '<div class="ip-no-results">No icons match your search</div>';
            return;
        }

        const displayList = list.slice(0, 300);

        displayList.forEach((iconName) => {
            const item = document.createElement("div");
            item.className = "ip-item";
            item.title = iconName;

            if (iconName.startsWith("fa-")) {
                item.innerHTML = `<i class="${iconName}"></i>`;
            } else {
                item.innerHTML = `<i data-lucide="${iconName}"></i>`;
            }

            item.onclick = () => {
                if (this.options.onSelect) this.options.onSelect(iconName);
                this.closePicker();
            };
            grid.appendChild(item);
        });

        if (window.lucide) lucide.createIcons();
    }
}

window.IconPicker = IconPicker;
IconPicker.init();
