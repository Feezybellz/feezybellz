(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof exports === 'object') {
    module.exports = factory();
  } else {
    root.CustomSelect = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  class CustomSelect {
    static stylesInjected = false;
    static instances = new Set();

    static styles = `
    :root {
      --cs-primary: #2563eb;
      --cs-primary-hover: #1d4ed8;
      --cs-bg: #f8fafc;
      --cs-bg-hover: #f1f5f9;
      --cs-border: #e2e8f0;
      --cs-text: #0f172a;
      --cs-text-light: #64748b;
      --cs-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --cs-radius: 1rem;
      --cs-focus-ring: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }
    .custom-select-wrapper { position: relative; width: 100%; font-family: inherit; font-size: 0.875rem; color: var(--cs-text); box-sizing: border-box; }
    .custom-select-wrapper * { box-sizing: border-box; }
    .cs-trigger { display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 56px; padding: 0.75rem 1.25rem; background-color: #f8fafc; border: 2px solid transparent; border-radius: var(--cs-radius); cursor: pointer; transition: all 0.2s ease; user-select: none; min-width: 0; font-weight: 600; }
    .custom-select-wrapper.is-disabled .cs-trigger { background-color: #f1f5f9; color: var(--cs-text-light); cursor: not-allowed; opacity: 0.7; }
    .cs-trigger:hover:not(.is-disabled) { background-color: #f1f5f9; }
    .cs-trigger:focus, .custom-select-wrapper.is-open .cs-trigger { border-color: var(--cs-primary); background-color: #fff; outline: none; box-shadow: var(--cs-focus-ring); }
    .cs-selected-value { flex: 1 1 0%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .cs-selected-value.is-placeholder { color: #94a3b8; font-weight: 500; }
    .cs-arrow { display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; margin-left: 8px; color: #94a3b8; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .custom-select-wrapper.is-open .cs-arrow { transform: rotate(180deg); color: var(--cs-primary); }
    .cs-tags { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
    .cs-tag { display: inline-flex; align-items: center; background-color: var(--cs-primary); border-radius: 8px; padding: 4px 10px; font-size: 0.75rem; color: #fff; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
    .cs-tag-remove { background: none; border: none; color: rgba(255,255,255,0.8); cursor: pointer; padding: 0 0 0 6px; font-size: 1.2em; line-height: 1; display: flex; align-items: center; transition: color 0.2s; }
    .cs-tag-remove:hover { color: #fff; }
    .cs-dropdown { position: absolute; top: calc(100% + 8px); left: 0; min-width: 100%; width: 100%; z-index: 9999; background: #fff; border: 1px solid var(--cs-border); border-radius: var(--cs-radius); box-shadow: var(--cs-shadow); opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); max-height: 320px; display: flex; flex-direction: column; overflow: hidden; }
    .custom-select-wrapper.is-open .cs-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
    .cs-search-container { padding: 12px; border-bottom: 1px solid var(--cs-border); background: #f8fafc; }
    .cs-search-input { width: 100%; padding: 10px 16px; border: 2px solid var(--cs-border); border-radius: 12px; font-size: 0.875rem; font-family: inherit; outline: none; transition: all 0.2s; font-weight: 600; }
    .cs-search-input:focus { border-color: var(--cs-primary); background-color: #fff; box-shadow: var(--cs-focus-ring); }
    .cs-options { overflow-y: auto; padding: 8px; margin: 0; list-style: none; flex: 1; scrollbar-width: thin; scrollbar-color: var(--cs-border) transparent; }
    .cs-option { padding: 12px 16px; margin-bottom: 4px; border-radius: 12px; cursor: pointer; transition: all 0.15s ease; display: flex; align-items: center; justify-content: space-between; font-weight: 600; color: var(--cs-text-light); }
    .cs-option:hover { background-color: var(--cs-bg-hover); color: var(--cs-text); }
    .cs-option.is-selected { background-color: rgba(37, 99, 235, 0.08); color: var(--cs-primary); }
    .cs-option-check { display: none; width: 18px; height: 18px; color: var(--cs-primary); }
    .cs-option.is-selected .cs-option-check { display: block; }
    .cs-no-results { padding: 20px; text-align: center; color: var(--cs-text-light); font-size: 0.875rem; font-weight: 500; font-style: italic; }
    .cs-options::-webkit-scrollbar { width: 6px; }
    .cs-options::-webkit-scrollbar-thumb { background: var(--cs-border); border-radius: 10px; }
    .cs-options::-webkit-scrollbar-track { background: transparent; }
  `;

    static injectStyles() {
      if (CustomSelect.stylesInjected) return;
      const style = document.createElement('style');
      style.textContent = CustomSelect.styles;
      document.head.appendChild(style);
      CustomSelect.stylesInjected = true;
    }

    constructor(selector, config = {}) {
      CustomSelect.injectStyles();
      this.originalSelect = typeof selector === 'string' ? document.querySelector(selector) : selector;

      if (!this.originalSelect || this.originalSelect.tagName !== 'SELECT') {
        console.warn('CustomSelect requires a valid <select> element.');
        return;
      }

      // Attach instance to original element for developer access
      this.originalSelect.CustomSelect = this;

      this.config = {
        data: null,
        multiple: this.originalSelect.hasAttribute('multiple'),
        placeholder: this.originalSelect.getAttribute('placeholder') || 'Select an option...',
        searchable: true,
        disabled: this.originalSelect.disabled || false,
        sortOrder: null, // 'ASC', 'DESC' or null
        sortBy: 'text',  // 'text' or 'value'
        ...config
      };

      this.options = [];
      this.selectedValues = new Set();
      this.isOpen = false;
      this.listeners = { change: [] };

      this.init();
      CustomSelect.instances.add(this);
    }

    _ensureProperties() {
      if (!this.selectedValues) this.selectedValues = new Set();
      if (!this.listeners) this.listeners = { change: [] };
    }

    init() {
      this._ensureProperties();
      this.loadOptions();
      this.buildUI();
      this.bindEvents();
      this.syncFromNative();
      if (this.config.disabled) {
        this.setDisabled(true);
      }
    }

    setDisabled(disabled) {
      this.config.disabled = disabled;
      if (disabled) {
        this.wrapper.classList.add('is-disabled');
        this.originalSelect.disabled = true;
      } else {
        this.wrapper.classList.remove('is-disabled');
        this.originalSelect.disabled = false;
      }
    }

    loadOptions() {
      this.options = [];
      if (this.config.data && Array.isArray(this.config.data)) {
        this.config.data.forEach(item => {
          if (typeof item === 'object') {
            this.options.push({ value: String(item.value), text: item.text || item.name || String(item.value) });
          } else {
            this.options.push({ value: String(item), text: String(item) });
          }
        });
      } else {
        Array.from(this.originalSelect.options).forEach(opt => {
          if (opt.value !== "") {
            this.options.push({ value: opt.value, text: opt.textContent });
          }
        });
      }
      this.sortOptions();
    }

    sortOptions() {
      if (!this.config.sortOrder) return;

      const order = this.config.sortOrder.toUpperCase();
      const by = this.config.sortBy || 'text';

      this.options.sort((a, b) => {
        const valA = String(a[by]).toLowerCase();
        const valB = String(b[by]).toLowerCase();

        if (order === 'ASC') {
          return valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' });
        } else {
          return valB.localeCompare(valA, undefined, { numeric: true, sensitivity: 'base' });
        }
      });
    }

    buildUI() {
      if (this.originalSelect.parentNode && this.originalSelect.parentNode.classList.contains('custom-select-wrapper')) {
        this.wrapper = this.originalSelect.parentNode;
        const prevTrigger = this.wrapper.querySelector('.cs-trigger');
        const prevDropdown = this.wrapper.querySelector('.cs-dropdown');
        if (prevTrigger) prevTrigger.remove();
        if (prevDropdown) prevDropdown.remove();
      } else {
        this.originalSelect.style.display = 'none';
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'custom-select-wrapper';
        this.originalSelect.parentNode.insertBefore(this.wrapper, this.originalSelect);
        this.wrapper.appendChild(this.originalSelect);
      }

      this.trigger = document.createElement('div');
      this.trigger.className = 'cs-trigger';
      this.trigger.tabIndex = 0;

      this.triggerContent = document.createElement('div');
      this.triggerContent.className = 'cs-selected-value is-placeholder';
      this.triggerContent.textContent = this.config.placeholder;
      this.trigger.appendChild(this.triggerContent);

      const arrow = document.createElement('div');
      arrow.className = 'cs-arrow';
      arrow.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>`;
      this.trigger.appendChild(arrow);
      this.wrapper.appendChild(this.trigger);

      this.dropdown = document.createElement('div');
      this.dropdown.className = 'cs-dropdown';

      if (this.config.searchable) {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'cs-search-container';
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'cs-search-input';
        this.searchInput.placeholder = 'Search...';
        searchContainer.appendChild(this.searchInput);
        this.dropdown.appendChild(searchContainer);
      }

      this.optionsList = document.createElement('ul');
      this.optionsList.className = 'cs-options';
      this.dropdown.appendChild(this.optionsList);

      this.wrapper.appendChild(this.dropdown);
      this.renderOptions(this.options);
    }

    renderOptions(optionsToRender) {
      this._ensureProperties();
      if (!this.optionsList) return;
      this.optionsList.innerHTML = '';
      if (optionsToRender.length === 0) {
        const li = document.createElement('li');
        li.className = 'cs-no-results';
        li.textContent = 'No results found';
        this.optionsList.appendChild(li);
        return;
      }

      optionsToRender.forEach(opt => {
        const li = document.createElement('li');
        li.className = 'cs-option';
        if (this.selectedValues.has(String(opt.value))) {
          li.classList.add('is-selected');
        }
        li.dataset.value = opt.value;

        const textSpan = document.createElement('span');
        textSpan.textContent = opt.text;
        li.appendChild(textSpan);

        const check = document.createElement('div');
        check.className = 'cs-option-check';
        check.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
        li.appendChild(check);

        li.addEventListener('click', (e) => {
          e.stopPropagation();
          this.toggleOption(opt.value, opt.text);
        });
        this.optionsList.appendChild(li);
      });
    }

    bindEvents() {
      this.trigger.addEventListener('click', () => this.toggleDropdown());
      this.trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
          e.preventDefault();
          this.openDropdown();
        }
      });

      this.clickOutsideHandler = (e) => {
        if (this.wrapper && !this.wrapper.contains(e.target)) {
          this.closeDropdown();
        }
      };
      document.addEventListener('click', this.clickOutsideHandler);

      if (this.searchInput) {
        this.searchInput.addEventListener('input', (e) => {
          const query = e.target.value.toLowerCase();
          const filtered = this.options.filter(opt => opt.text.toLowerCase().includes(query));
          this.renderOptions(filtered);
        });
        this.searchInput.addEventListener('click', (e) => e.stopPropagation());
      }

      // Listen for changes on native select to update custom UI (two-way sync)
      this.originalSelect.addEventListener('change', (e) => {
        if (e.detail && e.detail.isCustomSelectInternal) return;
        this.syncFromNative();
      });
    }

    toggleDropdown() {
      if (this.config.disabled) return;
      this.isOpen ? this.closeDropdown() : this.openDropdown();
    }

    openDropdown() {
      if (this.config.disabled || !this.wrapper) return;

      CustomSelect.instances.forEach(instance => {
        if (instance !== this && typeof instance.closeDropdown === 'function') {
          instance.closeDropdown();
        }
      });

      this.isOpen = true;
      this.wrapper.classList.add('is-open');

      const rect = this.trigger.getBoundingClientRect();
      const spaceBelow = window.innerHeight - rect.bottom;
      const dropdownHeight = 300;

      if (spaceBelow < dropdownHeight && rect.top > dropdownHeight) {
        this.dropdown.style.top = 'auto';
        this.dropdown.style.bottom = 'calc(100% + 8px)';
        this.dropdown.style.transform = 'translateY(10px)';
        setTimeout(() => {
          if (this.isOpen && this.dropdown) this.dropdown.style.transform = 'translateY(0)';
        }, 10);
      } else {
        this.dropdown.style.top = 'calc(100% + 8px)';
        this.dropdown.style.bottom = 'auto';
        this.dropdown.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          if (this.isOpen && this.dropdown) this.dropdown.style.transform = 'translateY(0)';
        }, 10);
      }

      if (this.searchInput) {
        setTimeout(() => {
          if (this.isOpen && this.searchInput) this.searchInput.focus();
        }, 100);
      }
    }

    closeDropdown() {
      this.isOpen = false;
      if (!this.wrapper) return;
      this.wrapper.classList.remove('is-open');
      if (this.searchInput) {
        this.searchInput.value = '';
        this.renderOptions(this.options);
      }
    }

    toggleOption(value, text) {
      this._ensureProperties();
      const stringValue = String(value);
      if (this.config.multiple) {
        if (this.selectedValues.has(stringValue)) {
          this.selectedValues.delete(stringValue);
        } else {
          this.selectedValues.add(stringValue);
        }
      } else {
        this.selectedValues.clear();
        this.selectedValues.add(stringValue);
        this.closeDropdown();
      }
      this.updateUI();
      this.syncToNative();
    }

    updateUI() {
      this._ensureProperties();
      if (!this.triggerContent) return;

      if (this.selectedValues.size === 0) {
        this.triggerContent.innerHTML = '';
        this.triggerContent.textContent = this.config.placeholder;
        this.triggerContent.classList.add('is-placeholder');
      } else {
        this.triggerContent.classList.remove('is-placeholder');
        this.triggerContent.innerHTML = '';
        if (this.config.multiple) {
          const tagsContainer = document.createElement('div');
          tagsContainer.className = 'cs-tags';
          this.selectedValues.forEach(val => {
            const opt = this.options.find(o => String(o.value) === val);
            if (!opt) return;
            const tag = document.createElement('span');
            tag.className = 'cs-tag';
            tag.textContent = opt.text;
            const removeBtn = document.createElement('button');
            removeBtn.className = 'cs-tag-remove';
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', (e) => {
              e.stopPropagation();
              this.toggleOption(val, opt.text);
            });
            tag.appendChild(removeBtn);
            tagsContainer.appendChild(tag);
          });
          this.triggerContent.appendChild(tagsContainer);
        } else {
          const val = Array.from(this.selectedValues)[0];
          const opt = this.options.find(o => String(o.value) === val);
          this.triggerContent.textContent = opt ? opt.text : this.config.placeholder;
        }
      }
      const currentQuery = this.searchInput ? this.searchInput.value.toLowerCase() : '';
      const filtered = this.options.filter(opt => opt.text.toLowerCase().includes(currentQuery));
      this.renderOptions(filtered);
    }

    syncToNative() {
      this._ensureProperties();
      const valuesArray = Array.from(this.selectedValues);
      
      // Update Native Select
      Array.from(this.originalSelect.options).forEach(opt => {
        opt.selected = this.selectedValues.has(String(opt.value));
      });

      // If options changed, we might need to recreate them
      if (this.config.data && this.originalSelect) {
        this.originalSelect.innerHTML = '';
        this.options.forEach(opt => {
          const nativeOpt = document.createElement('option');
          nativeOpt.value = opt.value;
          nativeOpt.textContent = opt.text;
          if (this.selectedValues.has(String(opt.value))) {
            nativeOpt.selected = true;
          }
          this.originalSelect.appendChild(nativeOpt);
        });
      }

      this.originalSelect.dispatchEvent(new CustomEvent('change', { 
        bubbles: true, 
        detail: { isCustomSelectInternal: true } 
      }));
      this.originalSelect.dispatchEvent(new CustomEvent('input', { 
        bubbles: true, 
        detail: { isCustomSelectInternal: true } 
      }));
      this.triggerCustomEvent('change', valuesArray);
    }

    syncFromNative() {
      this._ensureProperties();
      this.selectedValues.clear();
      Array.from(this.originalSelect.options).forEach(opt => {
        if (opt.selected && opt.value !== "") {
          this.selectedValues.add(String(opt.value));
        }
      });
      this.updateUI();
    }

    setValue(valueOrValues) {
      this._ensureProperties();
      this.selectedValues.clear();
      const arr = Array.isArray(valueOrValues) ? valueOrValues : [valueOrValues];
      arr.forEach(val => {
        if (val !== null && val !== undefined && val !== '') {
          this.selectedValues.add(String(val));
        }
      });
      this.updateUI();
      this.syncToNative();
    }

    getValue() {
      this._ensureProperties();
      return this.config.multiple ? Array.from(this.selectedValues) : Array.from(this.selectedValues)[0] || null;
    }

    setData(newData) {
      this._ensureProperties();
      this.config.data = newData;
      this.loadOptions();
      
      // Maintain selection if it exists in new data
      const newSelected = new Set();
      this.selectedValues.forEach(val => {
        if (this.options.find(o => String(o.value) === val)) {
          newSelected.add(val);
        }
      });
      this.selectedValues = newSelected;
      
      this.renderOptions(this.options);
      this.updateUI();
      this.syncToNative();
    }

    destroy() {
      CustomSelect.instances.delete(this);
      if (this.clickOutsideHandler) {
        document.removeEventListener('click', this.clickOutsideHandler);
      }
      this.originalSelect.style.display = '';
      if (this.wrapper && this.wrapper.parentNode) {
        this.wrapper.parentNode.insertBefore(this.originalSelect, this.wrapper);
        this.wrapper.remove();
      }
      this.wrapper = null;
    }

    on(event, callback) {
      this._ensureProperties();
      if (!this.listeners[event]) this.listeners[event] = [];
      this.listeners[event].push(callback);
    }

    triggerCustomEvent(event, data) {
      this._ensureProperties();
      if (this.listeners && this.listeners[event]) {
        this.listeners[event].forEach(cb => cb(data));
      }
    }
  }

  return CustomSelect;
}));
