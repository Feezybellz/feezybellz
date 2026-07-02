/**
 * Shelteer ConfirmModal
 * A robust, customizable, promise-based confirmation library.
 */
(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.ConfirmModal = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    const ConfirmModal = {
        _container: null,
        _resolve: null,

        _init() {
            if (this._container) return;

            // Inject Styles
            const style = document.createElement('style');
            style.id = 'sv-confirm-styles';
            style.textContent = `
                .sv-confirm-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px);
                    display: flex; align-items: center; justify-content: center;
                    z-index: 11000; opacity: 0; visibility: hidden; transition: all 0.3s ease;
                }
                .sv-confirm-overlay.active { opacity: 1; visibility: visible; }
                .sv-confirm-modal {
                    background: #fff; width: 90%; max-width: 450px; border-radius: 2.5rem;
                    padding: 2.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    transform: scale(0.95); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                    text-align: center;
                }
                .sv-confirm-overlay.active .sv-confirm-modal { transform: scale(1); }
                .sv-confirm-icon {
                    width: 80px; height: 80px; border-radius: 2rem;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 1.5rem auto;
                }
                .sv-confirm-title { font-size: 1.5rem; font-weight: 900; color: #0f172a; margin-bottom: 0.75rem; tracking: -0.025em; }
                .sv-confirm-message { font-size: 1rem; font-weight: 500; color: #64748b; margin-bottom: 2rem; line-height: 1.6; }
                .sv-confirm-actions { display: flex; gap: 1rem; }
                .sv-confirm-btn {
                    flex: 1; padding: 1.25rem; border-radius: 1.25rem; font-weight: 800; font-size: 0.875rem;
                    text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s; cursor: pointer; border: none;
                }
                .sv-confirm-btn-cancel { background: #f1f5f9; color: #64748b; }
                .sv-confirm-btn-cancel:hover { background: #e2e8f0; color: #0f172a; }
                
                /* Types */
                .sv-confirm-danger .sv-confirm-icon { background: #fef2f2; color: #ef4444; }
                .sv-confirm-danger .sv-confirm-btn-confirm { background: #ef4444; color: #fff; box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.2); }
                .sv-confirm-danger .sv-confirm-btn-confirm:hover { background: #dc2626; }

                .sv-confirm-warning .sv-confirm-icon { background: #fffbeb; color: #f59e0b; }
                .sv-confirm-warning .sv-confirm-btn-confirm { background: #f59e0b; color: #fff; box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.2); }
                .sv-confirm-warning .sv-confirm-btn-confirm:hover { background: #d97706; }

                .sv-confirm-success .sv-confirm-icon { background: #f0fdf4; color: #22c55e; }
                .sv-confirm-success .sv-confirm-btn-confirm { background: #22c55e; color: #fff; box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.2); }
                .sv-confirm-success .sv-confirm-btn-confirm:hover { background: #16a34a; }

                .sv-confirm-info .sv-confirm-icon { background: #eff6ff; color: #3b82f6; }
                .sv-confirm-info .sv-confirm-btn-confirm { background: #0f172a; color: #fff; box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.2); }
                .sv-confirm-info .sv-confirm-btn-confirm:hover { background: #000; }
            `;
            document.head.appendChild(style);

            // Create DOM
            this._container = document.createElement('div');
            this._container.className = 'sv-confirm-overlay';
            this._container.innerHTML = `
                <div class="sv-confirm-modal">
                    <div class="sv-confirm-icon"><i data-lucide="help-circle"></i></div>
                    <h3 class="sv-confirm-title"></h3>
                    <p class="sv-confirm-message"></p>
                    <div class="sv-confirm-actions">
                        <button class="sv-confirm-btn sv-confirm-btn-cancel"></button>
                        <button class="sv-confirm-btn sv-confirm-btn-confirm"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(this._container);

            this._container.querySelector('.sv-confirm-btn-cancel').onclick = () => this._handleAction(false);
            this._container.querySelector('.sv-confirm-btn-confirm').onclick = () => this._handleAction(true);
        },

        /**
         * Show the confirmation modal
         * @param {Object} options 
         * @returns {Promise<boolean>}
         */
        show(options = {}) {
            this._init();

            return new Promise((resolve) => {
                this._resolve = resolve;
                const settings = {
                    title: 'Are you sure?',
                    message: 'This action cannot be undone.',
                    type: 'info', // info, success, warning, danger
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    icon: null,
                    ...options
                };

                const modal = this._container.querySelector('.sv-confirm-modal');
                const iconContainer = modal.querySelector('.sv-confirm-icon');
                
                // Reset classes
                modal.className = `sv-confirm-modal sv-confirm-${settings.type}`;
                
                // Set Icon
                let iconName = settings.icon;
                if (!iconName) {
                    switch(settings.type) {
                        case 'danger': iconName = 'alert-triangle'; break;
                        case 'warning': iconName = 'alert-circle'; break;
                        case 'success': iconName = 'check-circle'; break;
                        default: iconName = 'help-circle';
                    }
                }
                iconContainer.innerHTML = `<i data-lucide="${iconName}"></i>`;

                // Set Content
                modal.querySelector('.sv-confirm-title').innerText = settings.title;
                modal.querySelector('.sv-confirm-message').innerText = settings.message;
                modal.querySelector('.sv-confirm-btn-cancel').innerText = settings.cancelText;
                modal.querySelector('.sv-confirm-btn-confirm').innerText = settings.confirmText;

                this._container.classList.add('active');
                if (window.lucide) lucide.createIcons();
            });
        },

        _handleAction(value) {
            this._container.classList.remove('active');
            if (this._resolve) {
                this._resolve(value);
                this._resolve = null;
            }
        }
    };

    return ConfirmModal;
}));
