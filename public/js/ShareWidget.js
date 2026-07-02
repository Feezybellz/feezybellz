/**
 * CustomShareWidget | A lightweight, customizable sharing library.
 * Supports Web Share API (navigator.share) and fallback to popular platforms.
 */

class ShareWidget {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        if (!this.element) {
            console.error('[ShareWidget] Target element not found:', element);
            return;
        }

        this.options = {
            title: options.title || document.title,
            text: options.text || '',
            url: options.url || window.location.href,
            image: options.image || null, // For Web Share API file support
            platforms: options.platforms || ['whatsapp', 'facebook', 'x', 'linkedin', 'telegram', 'copy'],
            onShare: options.onShare || null,
            ...options
        };

        this.init();
    }

    init() {
        this.element.style.cursor = 'pointer';
        this.element.addEventListener('click', (e) => {
            e.preventDefault();
            this.handleShare();
        });

        // Inject Styles
        if (!document.getElementById('share-widget-styles')) {
            const style = document.createElement('style');
            style.id = 'share-widget-styles';
            style.textContent = `
                .sw-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.4); backdrop-filter: blur(4px);
                    display: flex; align-items: center; justify-content: center;
                    z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;
                }
                .sw-overlay.active { opacity: 1; visibility: visible; }
                .sw-modal {
                    background: #fff; width: 90%; max-width: 400px; border-radius: 24px;
                    padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                    transform: translateY(20px); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                }
                .sw-overlay.active .sw-modal { transform: translateY(0); }
                .sw-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
                .sw-header h3 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #1a1a1a; }
                .sw-close { background: none; border: none; cursor: pointer; padding: 5px; color: #666; }
                .sw-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
                .sw-item {
                    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
                    text-decoration: none; color: #444; font-size: 0.75rem; font-weight: 500;
                    transition: transform 0.2s;
                }
                .sw-item:hover { transform: translateY(-3px); }
                .sw-icon {
                    width: 50px; height: 50px; border-radius: 15px;
                    display: flex; align-items: center; justify-content: center;
                    color: #fff; font-size: 24px;
                }
                .sw-whatsapp { background: #25D366; }
                .sw-facebook { background: #1877F2; }
                .sw-x { background: #000; }
                .sw-linkedin { background: #0077b5; }
                .sw-telegram { background: #0088cc; }
                .sw-copy { background: #6c757d; }
            `;
            document.head.appendChild(style);
        }
    }

    async handleShare() {
        // Try Native Share if available
        if (navigator.share) {
            try {
                const shareData = {
                    title: this.options.title,
                    text: this.options.text,
                    url: this.options.url,
                };

                // Handle Image/File if supported
                if (this.options.image && navigator.canShare && navigator.canShare({ files: [this.options.image] })) {
                    shareData.files = [this.options.image];
                }

                await navigator.share(shareData);
                if (this.options.onShare) this.options.onShare('native');
                return;
            } catch (err) {
                if (err.name !== 'AbortError') console.error('[ShareWidget] Native share failed:', err);
                // Fallback to custom UI if native share was cancelled or failed
            }
        }

        // Show Custom Fallback UI
        this.renderModal();
    }

    renderModal() {
        let overlay = document.querySelector('.sw-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sw-overlay';
            overlay.innerHTML = `
                <div class="sw-modal">
                    <div class="sw-header">
                        <h3>Share via</h3>
                        <button class="sw-close"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
                    </div>
                    <div class="sw-grid"></div>
                </div>
            `;
            document.body.appendChild(overlay);
            overlay.querySelector('.sw-close').onclick = () => overlay.classList.remove('active');
            overlay.onclick = (e) => { if (e.target === overlay) overlay.classList.remove('active'); };
        }

        const grid = overlay.querySelector('.sw-grid');
        grid.innerHTML = '';

        const title = encodeURIComponent(this.options.title);
        const text = encodeURIComponent(this.options.text);
        const url = encodeURIComponent(this.options.url);

        const platforms = {
            whatsapp: {
                name: 'WhatsApp',
                icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.396.015 12.03c0 2.12.554 4.189 1.605 6.006L0 24l6.117-1.605a11.803 11.803 0 005.925 1.586h.005c6.635 0 12.032-5.396 12.035-12.032a11.762 11.762 0 00-3.489-8.452z"/></svg>',
                url: `https://api.whatsapp.com/send?text=${text}%20${url}`
            },
            facebook: {
                name: 'Facebook',
                icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.791-4.667 4.53-4.667 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
                url: `https://www.facebook.com/sharer/sharer.php?u=${url}`
            },
            x: {
                name: 'X',
                icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
                url: `https://twitter.com/intent/tweet?text=${text}&url=${url}`
            },
            linkedin: {
                name: 'LinkedIn',
                icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
                url: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`
            },
            telegram: {
                name: 'Telegram',
                icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0C5.346 0 0 5.347 0 11.945c0 6.597 5.346 11.944 11.944 11.944 6.598 0 11.945-5.347 11.945-11.944C23.889 5.347 18.542 0 11.944 0zm5.204 8.163l-1.74 8.201c-.131.58-.475.723-.961.45l-2.651-1.954-1.278 1.23c-.142.142-.261.261-.534.261l.19-2.693 4.903-4.428c.213-.189-.046-.294-.33-.106l-6.059 3.815-2.608-.815c-.568-.178-.579-.568.118-.841l10.194-3.931c.472-.171.884.113.728.811z"/></svg>',
                url: `https://t.me/share/url?url=${url}&text=${text}`
            },
            copy: {
                name: 'Copy Link',
                icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>',
                url: '#'
            }
        };

        this.options.platforms.forEach(key => {
            const platform = platforms[key];
            if (!platform) return;

            const item = document.createElement('a');
            item.className = 'sw-item';
            item.href = platform.url;
            if (key !== 'copy') item.target = '_blank';
            
            item.innerHTML = `
                <div class="sw-icon sw-${key}">${platform.icon}</div>
                <span>${platform.name}</span>
            `;

            if (key === 'copy') {
                item.onclick = (e) => {
                    e.preventDefault();
                    navigator.clipboard.writeText(decodeURIComponent(url)).then(() => {
                        item.querySelector('span').textContent = 'Copied!';
                        setTimeout(() => { item.querySelector('span').textContent = 'Copy Link'; }, 2000);
                        if (this.options.onShare) this.options.onShare('copy');
                    });
                };
            } else {
                item.onclick = () => {
                    overlay.classList.remove('active');
                    if (this.options.onShare) this.options.onShare(key);
                };
            }

            grid.appendChild(item);
        });

        overlay.classList.add('active');
    }
}

/**
 * Factory function for easier usage
 */
window.CustomShareWidget = function(element, options) {
    return new ShareWidget(element, options);
};
