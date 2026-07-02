/**
 * Toast Notification System
 * Industry-standard, dynamic, stackable toast notifications with progress indicators.
 */
(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.Toast = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    
    const DEFAULTS = {
        duration: 4000,
        position: "top-right",
        maxToasts: 5,
        gap: 12,
        animationDuration: 400,
    };

    const ICONS = {
        success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
        error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
        warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        info: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
    };

    let stylesInjected = false;
    const containers = {};
    const activeToasts = [];

    function injectStyles() {
        if (stylesInjected) return;
        stylesInjected = true;

        const style = document.createElement("style");
        style.id = 'shelteer-toast-styles';
        style.textContent = `
            .toast-container {
                position: fixed;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                pointer-events: none;
                padding: 1.5rem;
                gap: ${DEFAULTS.gap}px;
            }
            .toast-container.top-right    { top: 0; right: 0; align-items: flex-end; }
            .toast-container.top-left     { top: 0; left: 0; align-items: flex-start; }
            .toast-container.top-center   { top: 0; left: 50%; transform: translateX(-50%); align-items: center; }
            .toast-container.bottom-right { bottom: 0; right: 0; align-items: flex-end; }
            .toast-container.bottom-left  { bottom: 0; left: 0; align-items: flex-start; }
            .toast-container.bottom-center{ bottom: 0; left: 50%; transform: translateX(-50%); align-items: center; }

            .toast {
                pointer-events: auto;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                min-width: 320px;
                max-width: 440px;
                padding: 16px;
                border-radius: 16px;
                background: #fff;
                border: 1px solid rgba(0,0,0,0.05);
                box-shadow: 0 10px 30px -5px rgba(0,0,0,0.1), 0 4px 10px -5px rgba(0,0,0,0.04);
                font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
                font-size: 0.875rem;
                line-height: 1.5;
                color: #1e293b;
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
                transition: opacity ${DEFAULTS.animationDuration}ms cubic-bezier(0.34, 1.56, 0.64, 1),
                            transform ${DEFAULTS.animationDuration}ms cubic-bezier(0.34, 1.56, 0.64, 1);
                will-change: opacity, transform;
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }
            .toast-container[class*="bottom"] .toast {
                transform: translateY(20px) scale(0.95);
            }
            .toast.show {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            .toast.hide {
                opacity: 0;
                transform: scale(0.9);
                transition: opacity 300ms ease, transform 300ms ease;
            }

            .toast-icon {
                flex-shrink: 0;
                width: 20px;
                height: 20px;
                margin-top: 1px;
            }
            .toast-icon svg { width: 100%; height: 100%; }

            .toast-body { flex: 1; min-width: 0; }
            .toast-title { font-weight: 800; font-size: 0.875rem; margin-bottom: 2px; }
            .toast-message { font-size: 0.8125rem; color: #64748b; font-weight: 500; }

            .toast-close {
                flex-shrink: 0;
                width: 18px;
                height: 18px;
                background: none;
                border: none;
                cursor: pointer;
                opacity: 0.3;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: opacity .2s;
                color: currentColor;
            }
            .toast-close:hover { opacity: 0.8; }
            .toast-close svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

            .toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                transition: width linear;
                opacity: 0.5;
            }

            .toast-success { border-left: 4px solid #10b981; }
            .toast-success .toast-icon { color: #10b981; }
            .toast-success .toast-progress { background: #10b981; }

            .toast-error { border-left: 4px solid #ef4444; }
            .toast-error .toast-icon { color: #ef4444; }
            .toast-error .toast-progress { background: #ef4444; }

            .toast-warning { border-left: 4px solid #f59e0b; }
            .toast-warning .toast-icon { color: #f59e0b; }
            .toast-warning .toast-progress { background: #f59e0b; }

            .toast-info { border-left: 4px solid #3b82f6; }
            .toast-info .toast-icon { color: #3b82f6; }
            .toast-info .toast-progress { background: #3b82f6; }

            @media (max-width: 480px) {
                .toast-container { padding: 1rem; width: 100%; }
                .toast { min-width: unset; width: 100%; }
            }
        `;
        document.head.appendChild(style);
    }

    function getContainer(position) {
        if (containers[position]) return containers[position];
        const el = document.createElement("div");
        el.className = `toast-container ${position}`;
        document.body.appendChild(el);
        containers[position] = el;
        return el;
    }

    function removeToast(toast, container) {
        toast.classList.remove("show");
        toast.classList.add("hide");

        setTimeout(() => {
            if (toast.parentNode === container) {
                container.removeChild(toast);
            }
            const idx = activeToasts.indexOf(toast);
            if (idx > -1) activeToasts.splice(idx, 1);
            if (container.children.length === 0) {
                container.remove();
                for (const pos in containers) {
                    if (containers[pos] === container) delete containers[pos];
                }
            }
        }, DEFAULTS.animationDuration);
    }

    function enforceMax() {
        while (activeToasts.length >= DEFAULTS.maxToasts) {
            const oldest = activeToasts[0];
            if (oldest) removeToast(oldest, oldest.parentNode);
        }
    }

    function show(options = {}) {
        injectStyles();
        const settings = typeof options === "string" ? { message: options } : options;
        const type = settings.type || "info";
        const duration = settings.duration !== undefined ? settings.duration : DEFAULTS.duration;
        const position = settings.position || DEFAULTS.position;

        enforceMax();

        const container = getContainer(position);
        const toast = document.createElement("div");
        toast.className = `toast toast-${type}`;

        const iconHTML = ICONS[type] ? `<div class="toast-icon">${ICONS[type]}</div>` : "";
        const titleHTML = settings.title ? `<div class="toast-title">${settings.title}</div>` : "";
        const progressHTML = (duration > 0) ? `<div class="toast-progress" style="width:100%;transition-duration:${duration}ms;"></div>` : "";

        toast.innerHTML = `
            ${iconHTML}
            <div class="toast-body">
                ${titleHTML}
                <div class="toast-message">${settings.message}</div>
            </div>
            <button class="toast-close"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            ${progressHTML}
        `;

        const closeBtn = toast.querySelector(".toast-close");
        closeBtn.onclick = (e) => { e.stopPropagation(); removeToast(toast, container); };
        toast.onclick = () => removeToast(toast, container);

        let timer = null;
        let remaining = duration;
        let startTime = null;
        const progressBar = toast.querySelector(".toast-progress");

        function startTimer() {
            if (duration <= 0) return;
            startTime = Date.now();
            timer = setTimeout(() => removeToast(toast, container), remaining);
            if (progressBar) {
                progressBar.style.transitionDuration = `${remaining}ms`;
                requestAnimationFrame(() => { progressBar.style.width = "0%"; });
            }
        }

        function pauseTimer() {
            if (timer) clearTimeout(timer);
            if (startTime) remaining -= Date.now() - startTime;
            if (progressBar) {
                const computed = getComputedStyle(progressBar).width;
                progressBar.style.transitionDuration = "0ms";
                progressBar.style.width = computed;
            }
        }

        toast.onmouseenter = pauseTimer;
        toast.onmouseleave = startTimer;

        container.appendChild(toast);
        activeToasts.push(toast);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.classList.add("show");
                startTimer();
            });
        });

        return { dismiss: () => removeToast(toast, container) };
    }

    return {
        success: (message, opts) => show({ ...opts, type: "success", message }),
        error: (message, opts) => {
            if (Array.isArray(message)) {
                const listHtml = `<ul class="toast-error-list" style="margin: 4px 0 0 0; padding: 0 0 0 16px; list-style-type: disc;">
                    ${message.map(err => `<li style="margin-bottom: 2px;">${err}</li>`).join('')}
                </ul>`;
                return show({ ...opts, type: "error", message: listHtml, duration: 6000 });
            }
            return show({ ...opts, type: "error", message });
        },
        warning: (message, opts) => show({ ...opts, type: "warning", message }),
        info: (message, opts) => show({ ...opts, type: "info", message }),
        show: show,
        clearAll: () => activeToasts.forEach(t => removeToast(t, t.parentNode))
    };
}));
