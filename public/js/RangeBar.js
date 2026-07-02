(function (root, factory) {
  if (typeof define === "function" && define.amd) {
    define([], factory);
  } else if (typeof exports === "object") {
    module.exports = factory();
  } else {
    root.RangeBar = factory();
  }
})(typeof self !== "undefined" ? self : this, function () {
  class RangeBar {
    static stylesInjected = false;

    static styles = `
      .range-bar-wrapper { width: 100%; padding: 25px 10px 15px; position: relative; user-select: none; }
      .range-bar-container { position: relative; height: 6px; background: #e2e8f0; border-radius: 10px; cursor: pointer; }
      .range-bar-progress { position: absolute; height: 100%; background: #2563eb; border-radius: 10px; transition: background 0.2s; }
      .range-bar-handle {
        position: absolute; top: 50%; width: 22px; height: 22px;
        background: #fff; border: 2px solid #2563eb; border-radius: 50%;
        transform: translate(-50%, -50%); cursor: grab; z-index: 10;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); transition: transform 0.1s, box-shadow 0.1s, border-color 0.2s;
      }
      .range-bar-handle:hover { border-color: #1d4ed8; }
      .range-bar-handle:active { cursor: grabbing; transform: translate(-50%, -50%) scale(1.15); box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3); }
      .range-bar-tooltip {
        position: absolute; bottom: calc(100% + 12px); left: 50%;
        transform: translateX(-50%); background: #1e293b; color: #fff;
        padding: 5px 10px; border-radius: 8px; font-size: 11px; font-weight: 800;
        white-space: nowrap; pointer-events: none; opacity: 0; transition: opacity 0.2s, transform 0.2s;
      }
      .range-bar-wrapper:hover .range-bar-tooltip, .range-bar-handle:active .range-bar-tooltip { opacity: 1; transform: translateX(-50%) translateY(-2px); }
      .range-bar-label { display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; margin-bottom: 12px; letter-spacing: 0.05em; }
      .range-bar-values { display: flex; justify-content: space-between; margin-top: 12px; font-size: 11px; font-weight: 700; color: #334155; }
    `;

    static injectStyles() {
      if (RangeBar.stylesInjected) return;
      const style = document.createElement("style");
      style.textContent = RangeBar.styles;
      document.head.appendChild(style);
      RangeBar.stylesInjected = true;
    }

    constructor(selector, config = {}) {
      RangeBar.injectStyles();
      this.container =
        typeof selector === "string"
          ? document.querySelector(selector)
          : selector;
      if (!this.container) return;

      this.config = {
        type: "dual", // 'dual' or 'single'
        min: 0,
        max: 100,
        step: 1,
        startMin: null,
        startMax: null,
        label: "",
        showValues: false,
        format: (val) => val.toLocaleString(),
        onDrag: null,
        ...config,
      };

      // Set initial values
      this.minValue =
        this.config.startMin !== null ? this.config.startMin : this.config.min;
      this.maxValue =
        this.config.startMax !== null ? this.config.startMax : this.config.max;

      if (this.config.type === "single") {
        this.minValue = this.config.min; // Locked for single
      }

      this.listeners = { change: [] };
      this.activeHandle = null;

      this.init();
    }

    init() {
      this.container.innerHTML = "";
      this.wrapper = document.createElement("div");
      this.wrapper.className = "range-bar-wrapper";

      if (this.config.label) {
        const label = document.createElement("label");
        label.className = "range-bar-label";
        label.textContent = this.config.label;
        this.wrapper.appendChild(label);
      }

      this.track = document.createElement("div");
      this.track.className = "range-bar-container";

      this.progress = document.createElement("div");
      this.progress.className = "range-bar-progress";
      this.track.appendChild(this.progress);

      if (this.config.type === "range") {
        this.minHandle = this.createHandle("min");
        this.track.appendChild(this.minHandle);
      }

      this.maxHandle = this.createHandle("max");
      this.track.appendChild(this.maxHandle);

      this.wrapper.appendChild(this.track);

      if (this.config.showValues) {
        this.valuesContainer = document.createElement("div");
        this.valuesContainer.className = "range-bar-values";
        this.minValDisplay = document.createElement("span");
        this.maxValDisplay = document.createElement("span");
        this.valuesContainer.appendChild(this.minValDisplay);
        this.valuesContainer.appendChild(this.maxValDisplay);
        this.wrapper.appendChild(this.valuesContainer);
      }

      this.container.appendChild(this.wrapper);

      this.updateUI();
      this.bindEvents();
    }

    createHandle(type) {
      const handle = document.createElement("div");
      handle.className = `range-bar-handle range-bar-handle-${type}`;
      handle.dataset.type = type;

      const tooltip = document.createElement("div");
      tooltip.className = "range-bar-tooltip";
      handle.appendChild(tooltip);

      return handle;
    }

    updateUI() {
      const minPercent =
        ((this.minValue - this.config.min) /
          (this.config.max - this.config.min)) *
        100;
      const maxPercent =
        ((this.maxValue - this.config.min) /
          (this.config.max - this.config.min)) *
        100;

      if (this.config.type === "range") {
        this.minHandle.style.left = `${minPercent}%`;
        this.minHandle.querySelector(".range-bar-tooltip").textContent =
          this.config.format(this.minValue);
      }

      this.maxHandle.style.left = `${maxPercent}%`;
      this.maxHandle.querySelector(".range-bar-tooltip").textContent =
        this.config.format(this.maxValue);

      this.progress.style.left = `${minPercent}%`;
      this.progress.style.width = `${maxPercent - minPercent}%`;

      if (this.config.showValues) {
        this.minValDisplay.textContent = this.config.format(this.minValue);
        this.maxValDisplay.textContent = this.config.format(this.maxValue);
      }
    }

    bindEvents() {
      const onMove = (e) => {
        if (!this.activeHandle) return;

        const rect = this.track.getBoundingClientRect();
        const clientX = e.type.includes("touch")
          ? e.touches[0].clientX
          : e.clientX;
        let percent = ((clientX - rect.left) / rect.width) * 100;
        percent = Math.max(0, Math.min(100, percent));

        let value =
          this.config.min +
          (percent / 100) * (this.config.max - this.config.min);
        value = Math.round(value / this.config.step) * this.config.step;

        const type = this.activeHandle.dataset.type;
        if (type === "min") {
          this.minValue = Math.min(value, this.maxValue - this.config.step);
        } else {
          const limit =
            this.config.type === "range"
              ? this.minValue + this.config.step
              : this.config.min;
          this.maxValue = Math.max(value, limit);
        }

        this.updateUI();
        if (this.config.onDrag)
          this.config.onDrag({ min: this.minValue, max: this.maxValue });
        this.trigger("change", { min: this.minValue, max: this.maxValue });
      };

      const onEnd = () => {
        if (!this.activeHandle) return;
        this.activeHandle = null;
        document.removeEventListener("mousemove", onMove);
        document.removeEventListener("mouseup", onEnd);
        document.removeEventListener("touchmove", onMove);
        document.removeEventListener("touchend", onEnd);
      };

      const onStart = (e) => {
        e.preventDefault();
        const handle = e.target.closest(".range-bar-handle");
        if (!handle) return;

        this.activeHandle = handle;
        document.addEventListener("mousemove", onMove);
        document.addEventListener("mouseup", onEnd);
        document.addEventListener("touchmove", onMove, { passive: false });
        document.addEventListener("touchend", onEnd);
      };

      // Click on track to jump
      this.track.addEventListener("mousedown", (e) => {
        if (e.target.closest(".range-bar-handle")) return;

        const rect = this.track.getBoundingClientRect();
        const percent = ((e.clientX - rect.left) / rect.width) * 100;
        let value =
          this.config.min +
          (percent / 100) * (this.config.max - this.config.min);
        value = Math.round(value / this.config.step) * this.config.step;

        if (this.config.type === "single") {
          this.maxValue = value;
        } else {
          // Jump closest handle
          const distMin = Math.abs(value - this.minValue);
          const distMax = Math.abs(value - this.maxValue);
          if (distMin < distMax) {
            this.minValue = Math.min(value, this.maxValue - this.config.step);
          } else {
            this.maxValue = Math.max(value, this.minValue + this.config.step);
          }
        }

        this.updateUI();
        this.trigger("change", { min: this.minValue, max: this.maxValue });
      });

      if (this.minHandle) {
        this.minHandle.addEventListener("mousedown", onStart);
        this.minHandle.addEventListener("touchstart", onStart, {
          passive: false,
        });
      }

      this.maxHandle.addEventListener("mousedown", onStart);
      this.maxHandle.addEventListener("touchstart", onStart, {
        passive: false,
      });
    }

    on(event, callback) {
      if (this.listeners[event]) {
        this.listeners[event].push(callback);
      }
      return this;
    }

    trigger(event, data) {
      if (this.listeners[event]) {
        this.listeners[event].forEach((cb) => cb(data));
      }
    }

    setValues(min, max) {
      if (this.config.type === "range") {
        this.minValue = Math.max(this.config.min, min);
      }
      this.maxValue = Math.min(this.config.max, max);
      this.updateUI();
    }

    getValues() {
      return { min: this.minValue, max: this.maxValue };
    }

    updateConfig(newConfig) {
      this.config = { ...this.config, ...newConfig };
      this.minValue = Math.max(this.config.min, this.minValue);
      this.maxValue = Math.min(this.config.max, this.maxValue);
      this.updateUI();
    }
  }

  return RangeBar;
});
