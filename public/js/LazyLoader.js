/**
 * Shelteer LazyLoader
 * A lightweight, dependency-free lazy loading library with built-in styles and fallback support.
 * 
 * USAGE:
 * 1. Initialize:
 *    const lazy = LazyLoader.run({
 *        selector: '.sv-lazy',
 *        fallback: 'https://example.com/placeholder.png'
 *    });
 * 
 * 2. HTML Markup:
 *    <img class="sv-lazy" data-src="real-image.jpg" />
 *    <div class="sv-lazy" data-src="bg-image.jpg"></div>
 * 
 * 3. Dynamic Observation (e.g., after AJAX):
 *    lazy.observe('.new-elements');
 */
class LazyLoader {
    constructor(options = {}) {
        // Internal SVG fallback (base64) for absolute reliability
        this.internalFallback = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
                <rect width="800" height="600" fill="#f8fafc"/>
                <path d="M400 250c-33.1 0-60 26.9-60 60s26.9 60 60 60 60-26.9 60-60-26.9-60-60-60zm0 100c-22.1 0-40-17.9-40-40s17.9-40 40-40 40 17.9 40 40-17.9 40-40 40z" fill="#cbd5e1"/>
                <path d="M400 200c-60.8 0-110 49.2-110 110s49.2 110 110 110 110-49.2 110-110-49.2-110-110-110zm0 200c-49.6 0-90-40.4-90-90s40.4-90 90-90 90 40.4 90 90-40.4 90-90 90z" fill="#cbd5e1"/>
                <text x="50%" y="450" font-family="sans-serif" font-size="24" font-weight="bold" fill="#94a3b8" text-anchor="middle">SHELTEER</text>
                <text x="50%" y="485" font-family="sans-serif" font-size="14" font-weight="medium" fill="#cbd5e1" text-anchor="middle">Image Unavailable</text>
            </svg>
        `)}`;

        this.settings = {
            selector: '.sv-lazy',
            fallback: options.fallback || this.internalFallback,
            threshold: 0.1,
            rootMargin: '0px 0px 200px 0px',
            loadedClass: 'sv-lazy-loaded',
            errorClass: 'sv-lazy-error',
            loadingClass: 'sv-lazy-loading',
            transition: 'opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)',
            transformStart: 'scale(1.02) translateY(10px)',
            transformEnd: 'scale(1) translateY(0)',
            ...options
        };

        this.observer = null;
        this._init();
    }

    /**
     * Initialize the library
     * @private
     */
    _init() {
        this._injectStyles();
        this._createObserver();
        
        // Initial observation
        if (this.settings.selector) {
            this.observe(this.settings.selector);
        }
    }

    /**
     * Injects the necessary CSS into the head tag
     * @private
     */
    _injectStyles() {
        if (document.getElementById('sv-lazyloader-styles')) return;

        const style = document.createElement('style');
        style.id = 'sv-lazyloader-styles';
        style.textContent = `
            ${this.settings.selector} {
                opacity: 0;
                transform: ${this.settings.transformStart};
                transition: ${this.settings.transition};
                will-change: opacity, transform;
            }
            .${this.settings.loadingClass} {
                filter: blur(5px);
            }
            .${this.settings.loadedClass} {
                opacity: 1 !important;
                transform: ${this.settings.transformEnd} !important;
                filter: blur(0) !important;
            }
            .${this.settings.errorClass} {
                filter: grayscale(1) opacity(0.6) !important;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Creates the IntersectionObserver instance
     * @private
     */
    _createObserver() {
        if (!('IntersectionObserver' in window)) {
            console.warn('LazyLoader: IntersectionObserver not supported. Falling back to immediate load.');
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this._loadElement(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: this.settings.threshold,
            rootMargin: this.settings.rootMargin
        });
    }

    /**
     * Loads a specific element
     * @param {Element} el 
     * @private
     */
    _loadElement(el) {
        const src = el.getAttribute('data-src') || el.getAttribute('data-lazy');
        if (!src) return;

        el.classList.add(this.settings.loadingClass);

        if (el.tagName === 'IMG') {
            this._handleImage(el, src);
        } else {
            this._handleBackground(el, src);
        }
    }

    /**
     * Handles image elements
     * @private
     */
    _handleImage(el, src) {
        const img = new Image();
        img.src = src;

        img.onload = () => {
            el.src = src;
            this._markAsLoaded(el);
        };

        img.onerror = () => {
            this._applyFallback(el);
        };
    }

    /**
     * Handles background images for div/section etc.
     * @private
     */
    _handleBackground(el, src) {
        const img = new Image();
        img.src = src;

        img.onload = () => {
            el.style.backgroundImage = `url('${src}')`;
            this._markAsLoaded(el);
        };

        img.onerror = () => {
            this._applyFallback(el, true);
        };
    }

    /**
     * Applies fallback and handles fallback failure
     * @private
     */
    _applyFallback(el, isBackground = false) {
        const tryInternal = () => {
            if (isBackground) {
                el.style.backgroundImage = `url('${this.internalFallback}')`;
            } else {
                el.src = this.internalFallback;
            }
            el.classList.add(this.settings.errorClass);
            this._markAsLoaded(el);
        };

        if (this.settings.fallback && this.settings.fallback !== this.internalFallback) {
            const fallbackImg = new Image();
            fallbackImg.src = this.settings.fallback;
            fallbackImg.onload = () => {
                if (isBackground) {
                    el.style.backgroundImage = `url('${this.settings.fallback}')`;
                } else {
                    el.src = this.settings.fallback;
                }
                el.classList.add(this.settings.errorClass);
                this._markAsLoaded(el);
            };
            fallbackImg.onerror = tryInternal;
        } else {
            tryInternal();
        }
    }

    /**
     * Marks an element as loaded and cleans up classes
     * @private
     */
    _markAsLoaded(el) {
        el.classList.remove(this.settings.loadingClass);
        // Small timeout to ensure transition triggers properly
        requestAnimationFrame(() => {
            el.classList.add(this.settings.loadedClass);
        });
    }

    /**
     * Public method to observe new elements
     * @param {String|Element|Array|NodeList} target 
     */
    observe(target) {
        let elements = [];

        if (typeof target === 'string') {
            elements = Array.from(document.querySelectorAll(target));
        } else if (target instanceof Element) {
            elements = [target];
        } else if (Array.isArray(target)) {
            elements = target;
        } else if (target instanceof NodeList) {
            elements = Array.from(target);
        }

        if (this.observer) {
            elements.forEach(el => this.observer.observe(el));
        } else {
            // Fallback for no IntersectionObserver support
            elements.forEach(el => this._loadElement(el));
        }
    }

    /**
     * Static helper for global initialization
     */
    static run(options = {}) {
        return new LazyLoader(options);
    }
}

// Export for module systems or attach to window
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LazyLoader;
} else {
    window.LazyLoader = LazyLoader;
}
