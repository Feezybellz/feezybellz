/**
 * CustomModal.js
 * A robust, dynamic, and premium modal library supporting UMD.
 *
 * Automatically injects its base styles into the document head to ensure
 * a beautiful glassmorphism aesthetic without needing external CSS.
 */

(function (root, factory) {
  if (typeof define === "function" && define.amd) {
    define([], factory);
  } else if (typeof exports === "object") {
    module.exports = factory();
  } else {
    root.CustomModal = factory();
  }
})(typeof self !== "undefined" ? self : this, function () {
  class CustomModal {
    static initialized = false;
    static activeModals = [];

    /**
     * Inject base styles into the document head
     */
    static initStyles() {
      if (this.initialized) return;

      const styleId = "custom-modal-styles";
      if (!document.getElementById(styleId)) {
        const style = document.createElement("style");
        style.id = styleId;
        style.innerHTML = `
                    .custom-modal-overlay {
                        position: fixed;
                        top: 0; left: 0; width: 100vw; height: 100vh;
                        background: rgba(0, 0, 0, 0.4);
                        backdrop-filter: blur(4px);
                        -webkit-backdrop-filter: blur(4px);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                        opacity: 0;
                        visibility: hidden;
                        transition: opacity 0.3s ease, visibility 0.3s ease;
                    }
                    
                    .custom-modal-overlay.cm-show {
                        opacity: 1;
                        visibility: visible;
                    }
                    
                    .custom-modal-container {
                        background: var(--color-surface, #ffffff);
                        color: var(--color-text, #1E293B);
                        border-radius: 16px;
                        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                        width: 90%;
                        max-width: 500px;
                        max-height: 90vh;
                        display: flex;
                        flex-direction: column;
                        transform: translateY(20px) scale(0.95);
                        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                        overflow: hidden;
                        position: relative;
                    }
                    
                    .dark .custom-modal-container {
                        background: var(--color-surface, #1E293B);
                        color: var(--color-text, #F1F5F9);
                        border: 1px solid rgba(255, 255, 255, 0.1);
                    }
                    
                    .custom-modal-overlay.cm-show .custom-modal-container {
                        transform: translateY(0) scale(1);
                    }
                    
                    .custom-modal-header {
                        padding: 1.25rem 1.5rem;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        border-bottom: 1px solid var(--color-border, #E2E8F0);
                    }

                    .dark .custom-modal-header {
                        border-bottom-color: var(--color-border, #334155);
                    }
                    
                    .custom-modal-title {
                        margin: 0;
                        font-size: 1.25rem;
                        font-weight: 700;
                        color: var(--color-primary, inherit);
                    }
                    
                    .custom-modal-close {
                        background: transparent;
                        border: none;
                        font-size: 1.5rem;
                        line-height: 1;
                        color: #64748B;
                        cursor: pointer;
                        padding: 0.25rem;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: background 0.2s, color 0.2s;
                    }
                    
                    .custom-modal-close:hover {
                        background: rgba(0, 0, 0, 0.05);
                        color: #0F172A;
                    }
                    
                    .dark .custom-modal-close:hover {
                        background: rgba(255, 255, 255, 0.1);
                        color: #F1F5F9;
                    }
                    
                    .custom-modal-body {
                        padding: 1.5rem;
                        overflow-y: auto;
                        flex: 1;
                    }
                    
                    .custom-modal-footer {
                        padding: 1rem 1.5rem;
                        border-top: 1px solid var(--color-border, #E2E8F0);
                        display: flex;
                        justify-content: flex-end;
                        gap: 0.75rem;
                    }

                    .dark .custom-modal-footer {
                        border-top-color: var(--color-border, #334155);
                    }
                    
                    /* Responsive Adjustments for Mobile */
                    @media (max-width: 640px) {
                        .custom-modal-container {
                            width: 100%;
                            max-width: none;
                            max-height: 100vh;
                            border-radius: 16px 16px 0 0; /* Bottom-sheet style on mobile */
                            align-self: flex-end; /* Align to bottom */
                            transform: translateY(100%);
                        }
                        
                        .custom-modal-overlay.cm-show .custom-modal-container {
                            transform: translateY(0);
                        }
                        
                        .custom-modal-header {
                            padding: 1rem 1.25rem;
                        }
                        
                        .custom-modal-body {
                            padding: 1.25rem;
                        }
                        
                        .custom-modal-footer {
                            padding: 1rem 1.25rem;
                            flex-direction: column;
                        }
                        
                        .custom-modal-footer button {
                            width: 100%;
                            margin-bottom: 0.5rem;
                        }
                        .custom-modal-footer button:last-child {
                            margin-bottom: 0;
                        }
                    }
                `;
        document.head.appendChild(style);
      }
      this.initialized = true;
    }

    /**
     * Create the base DOM structure for a modal
     */
    static createOverlay(options = {}) {
      this.initStyles();

      const overlay = document.createElement("div");
      overlay.className = "custom-modal-overlay";
      if (options.overlayClass)
        overlay.classList.add(...options.overlayClass.split(" "));

      // Close on backdrop click unless prevented
      if (options.closeOnBackdrop !== false) {
        overlay.addEventListener("mousedown", (e) => {
          if (e.target === overlay) {
            this.close(overlay, options.onClose);
          }
        });
      }

      return overlay;
    }

    /**
     * Build and show a dynamic modal from scratch
     *
     * @param {Object} options
     * @param {string} options.title - Modal title
     * @param {string|HTMLElement} options.body - Modal content (HTML string or DOM Element)
     * @param {string} [options.width] - Custom width (e.g. '600px')
     * @param {string} [options.height] - Custom height (e.g. '80vh')
     * @param {string} [options.containerClass] - Extra CSS classes to append to the container for responsive tweaks (e.g. 'md:w-1/2')
     * @param {boolean} [options.closeOnBackdrop] - Whether clicking outside closes the modal
     * @param {boolean} [options.showCloseBtn] - Show the 'x' button in header
     * @param {Function} [options.onOpen] - Callback when modal opens
     * @param {Function} [options.onClose] - Callback when modal closes
     * @param {Array} [options.buttons] - Array of button objects { text, class, onClick }
     * @returns {HTMLElement} The created overlay element
     */
    static build(options = {}) {
      const overlay = this.createOverlay(options);

      const container = document.createElement("div");
      container.className = "custom-modal-container";
      if (options.containerClass)
        container.classList.add(...options.containerClass.split(" "));

      if (options.width) container.style.maxWidth = options.width;
      if (options.height) container.style.height = options.height;
      if (options.customContainerStyle)
        Object.assign(container.style, options.customContainerStyle);

      // Header
      if (options.title || options.showCloseBtn !== false) {
        const header = document.createElement("div");
        header.className = "custom-modal-header";

        const title = document.createElement("h3");
        title.className = "custom-modal-title";
        title.innerHTML = options.title || "";
        header.appendChild(title);

        if (options.showCloseBtn !== false) {
          const closeBtn = document.createElement("button");
          closeBtn.className = "custom-modal-close";
          closeBtn.innerHTML = "&times;";
          closeBtn.onclick = () => this.close(overlay, options.onClose);
          header.appendChild(closeBtn);
        }

        container.appendChild(header);
      }

      // Body
      const body = document.createElement("div");
      body.className = "custom-modal-body";
      if (options.body instanceof HTMLElement) {
        body.appendChild(options.body);
      } else {
        body.innerHTML = options.body || "";
      }
      container.appendChild(body);

      // Footer (Buttons)
      if (options.buttons && options.buttons.length > 0) {
        const footer = document.createElement("div");
        footer.className = "custom-modal-footer";

        options.buttons.forEach((btnConfig) => {
          const btn = document.createElement("button");
          btn.className = btnConfig.class || "";
          if (!btnConfig.class) {
            btn.style.padding = "0.5rem 1rem";
            btn.style.borderRadius = "8px";
            btn.style.border = "1px solid var(--color-border, #ccc)";
            btn.style.background = "transparent";
            btn.style.cursor = "pointer";
          }
          btn.innerHTML = btnConfig.text || "Button";

          btn.onclick = (e) => {
            if (typeof btnConfig.onClick === "function") {
              btnConfig.onClick(e, overlay, () =>
                this.close(overlay, options.onClose),
              );
            } else if (btnConfig.closeOnClick !== false) {
              this.close(overlay, options.onClose);
            }
          };

          footer.appendChild(btn);
        });

        container.appendChild(footer);
      }

      overlay.appendChild(container);
      document.body.appendChild(overlay);

      // Animate in
      requestAnimationFrame(() => {
        overlay.classList.add("cm-show");
        if (typeof options.onOpen === "function") options.onOpen(overlay);
      });

      this.activeModals.push(overlay);
      this.setupKeyboardAccess();

      return overlay;
    }

    /**
     * Wrap and show an existing DOM element as a modal.
     * The element can be a selector string or a DOM node.
     *
     * @param {string|HTMLElement} target
     * @param {Object} options
     * @param {string} [options.width] - Custom width (e.g. '600px')
     * @param {string} [options.height] - Custom height (e.g. '80vh')
     * @param {string} [options.containerClass] - Extra CSS classes to append to the container
     * @returns {HTMLElement} The created overlay wrapper
     */
    static show(target, options = {}) {
      let el =
        typeof target === "string" ? document.querySelector(target) : target;

      if (!el) {
        console.error("CustomModal: Target element not found.");
        return null;
      }

      const overlay = this.createOverlay(options);

      const placeholder = document.createComment("CustomModal Placeholder");
      el.parentNode.insertBefore(placeholder, el);

      const originalDisplay = el.style.display;
      if (window.getComputedStyle(el).display === "none") {
        el.style.display = "block";
      }

      overlay._cm_restoreInfo = {
        placeholder,
        element: el,
        originalDisplay,
      };

      let container = el;
      if (!el.classList.contains("custom-modal-container") && !options.noWrap) {
        container = document.createElement("div");
        container.className = "custom-modal-container";
        if (options.containerClass)
          container.classList.add(...options.containerClass.split(" "));

        if (options.width) container.style.maxWidth = options.width;
        if (options.height) container.style.height = options.height;
        if (options.customContainerStyle)
          Object.assign(container.style, options.customContainerStyle);

        if (options.title || options.showCloseBtn !== false) {
          const header = document.createElement("div");
          header.className = "custom-modal-header";

          const title = document.createElement("h3");
          title.className = "custom-modal-title";
          title.innerHTML = options.title || "";
          header.appendChild(title);

          if (options.showCloseBtn !== false) {
            const closeBtn = document.createElement("button");
            closeBtn.className = "custom-modal-close";
            closeBtn.innerHTML = "&times;";
            closeBtn.onclick = () => this.close(overlay, options.onClose);
            header.appendChild(closeBtn);
          }

          container.appendChild(header);
        }

        const body = document.createElement("div");
        body.className = "custom-modal-body";
        body.appendChild(el);
        container.appendChild(body);

        overlay.appendChild(container);
      } else {
        overlay.appendChild(el);
      }

      document.body.appendChild(overlay);

      requestAnimationFrame(() => {
        overlay.classList.add("cm-show");
        if (typeof options.onOpen === "function") options.onOpen(overlay);
      });

      this.activeModals.push(overlay);
      this.setupKeyboardAccess();

      return overlay;
    }

    /**
     * Close a specific modal.
     */
    static close(overlay, onCloseCallback = null) {
      if (!overlay || !overlay.classList.contains("cm-show")) return;

      overlay.classList.remove("cm-show");

      setTimeout(() => {
        if (overlay._cm_restoreInfo) {
          const { placeholder, element, originalDisplay } =
            overlay._cm_restoreInfo;
          element.style.display = originalDisplay;
          placeholder.parentNode.insertBefore(element, placeholder);
          placeholder.parentNode.removeChild(placeholder);
        }

        if (overlay.parentNode) {
          overlay.parentNode.removeChild(overlay);
        }

        this.activeModals = this.activeModals.filter((m) => m !== overlay);

        if (typeof onCloseCallback === "function") {
          onCloseCallback();
        }
      }, 300);
    }

    /**
     * Close the most recently opened modal.
     */
    static closeTop() {
      if (this.activeModals.length > 0) {
        const topModal = this.activeModals[this.activeModals.length - 1];
        this.close(topModal);
      }
    }

    /**
     * Close all active modals.
     */
    static closeAll() {
      while (this.activeModals.length > 0) {
        this.closeTop();
      }
    }

    /**
     * Listen for Escape key to close the top modal
     */
    static setupKeyboardAccess() {
      if (this._keyboardListenerAttached) return;

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          this.closeTop();
        }
      });

      this._keyboardListenerAttached = true;
    }
  }

  return CustomModal;
});

/* =====================================================================
 * USAGE EXAMPLES
 * =====================================================================
 *
 * --- Method 1: Building a Modal Dynamically ---
 *
 * CustomModal.build({
 *     title: "Delete Account",
 *     body: "<p>Are you sure you want to permanently delete your account?</p>",
 *     width: "400px",
 *     buttons: [
 *         {
 *             text: "Cancel",
 *             class: "btn-secondary",
 *             onClick: (e, modal, closeFunc) => closeFunc()
 *         },
 *         {
 *             text: "Delete",
 *             class: "btn-danger",
 *             onClick: async (e, modal, closeFunc) => {
 *                 e.target.innerText = "Deleting...";
 *                 await api.delete();
 *                 closeFunc();
 *             }
 *         }
 *     ],
 *     onOpen: () => console.log('Opened!'),
 *     onClose: () => console.log('Closed!')
 * });
 *
 *
 * --- Method 2: Showing an existing DOM element ---
 *
 * // Assuming you have <div id="my-form" style="display:none;">...</div>
 *
 * CustomModal.show('#my-form', {
 *     title: "Submit Form",
 *     width: "600px",
 *     onClose: () => {
 *         // #my-form is automatically put back into its original place in the DOM!
 *         console.log("Form modal closed");
 *     }
 * });
 *
 * --- Methods for Closing ---
 * CustomModal.closeTop(); // Closes the topmost active modal (also triggered by ESC key)
 * CustomModal.closeAll(); // Closes all open modals
 * =====================================================================
 */
