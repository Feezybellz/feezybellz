class OTPInput {
    constructor(containerSelectorOrElement, options = {}) {
        if (typeof containerSelectorOrElement === 'string') {
            this.container = document.querySelector(containerSelectorOrElement);
        } else {
            this.container = containerSelectorOrElement;
        }

        if (!this.container) {
            throw new Error(`OTPInput: Container not found.`);
        }

        this.options = {
            length: options.length || 6,
            type: options.type || 'number', // 'number', 'alphabet', 'mixed'
            initialValue: options.initialValue || '',
            mask: options.mask || false, // true to mask character immediately after typing
            onComplete: options.onComplete || null,
            onChange: options.onChange || null,
            inputClass: options.inputClass || 'otp-input',
            containerClass: options.containerClass || 'otp-container',
            styles: options.styles || {}
        };

        this.inputs = [];
        this.valueArray = Array(this.options.length).fill('');

        this.init();
    }

    init() {
        this.injectStyles();
        this.render();
        this.setupListeners();

        if (this.options.initialValue) {
            this.setValue(this.options.initialValue);
        }
    }

    injectStyles() {
        if (document.getElementById('otp-input-styles')) return;

        const style = document.createElement('style');
        style.id = 'otp-input-styles';
        
        // Default styles that can be overridden by options.styles
        const defaultInputStyles = `
            width: 3rem;
            height: 3.5rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 900;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            outline: none;
            transition: all 0.2s ease-in-out;
            color: #0f172a;
            font-family: inherit;
        `;

        const defaultContainerStyles = `
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        `;

        style.innerHTML = `
            .otp-container {
                ${defaultContainerStyles}
            }
            .otp-input {
                ${defaultInputStyles}
            }
            .otp-input:focus {
                border-color: #2563eb;
                box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
                background-color: #ffffff;
            }
        `;
        document.head.appendChild(style);
    }

    render() {
        this.container.innerHTML = '';
        this.inputs = [];
        
        // Add container class, keeping existing classes if needed
        if (!this.container.classList.contains(this.options.containerClass)) {
            this.container.classList.add(this.options.containerClass);
        }

        // Apply custom container styles if provided
        if (this.options.styles.container) {
            Object.assign(this.container.style, this.options.styles.container);
        }

        for (let i = 0; i < this.options.length; i++) {
            const input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 1;
            input.className = this.options.inputClass;
            input.dataset.index = i;
            
            // Apply custom input styles if provided
            if (this.options.styles.input) {
                Object.assign(input.style, this.options.styles.input);
            }

            this.inputs.push(input);
            this.container.appendChild(input);
        }
    }

    setupListeners() {
        this.container.addEventListener('input', (e) => this.handleInput(e));
        this.container.addEventListener('keydown', (e) => this.handleKeydown(e));
        this.container.addEventListener('paste', (e) => this.handlePaste(e));
    }

    updateMasking(input, value) {
        if (!this.options.mask) return;
        if (value) {
            input.type = 'password';
        } else {
            input.type = 'text';
        }
    }

    filterValue(val) {
        if (this.options.type === 'number') {
            return val.replace(/[^0-9]/g, '');
        } else if (this.options.type === 'alphabet') {
            return val.replace(/[^a-zA-Z]/g, '');
        } else if (this.options.type === 'mixed') {
            return val.replace(/[^a-zA-Z0-9]/g, '');
        }
        return val;
    }

    handleInput(e) {
        if (e.target.tagName !== 'INPUT') return;
        
        const index = parseInt(e.target.dataset.index);
        let val = e.target.value;
        
        val = this.filterValue(val);
        
        e.target.value = val;
        this.valueArray[index] = val;

        this.updateMasking(e.target, val);

        if (val && index < this.options.length - 1) {
            this.inputs[index + 1].focus();
        }

        this.triggerChange();
    }

    handleKeydown(e) {
        if (e.target.tagName !== 'INPUT') return;
        
        const index = parseInt(e.target.dataset.index);

        if (e.key === 'Backspace') {
            if (!this.valueArray[index] && index > 0) {
                const prevInput = this.inputs[index - 1];
                prevInput.focus();
                prevInput.value = '';
                this.valueArray[index - 1] = '';
                this.updateMasking(prevInput, '');
                e.preventDefault();
            } else {
                this.valueArray[index] = '';
                this.updateMasking(e.target, '');
            }
            this.triggerChange();
        } else if (e.key === 'ArrowLeft' && index > 0) {
            this.inputs[index - 1].focus();
            e.preventDefault();
        } else if (e.key === 'ArrowRight' && index < this.options.length - 1) {
            this.inputs[index + 1].focus();
            e.preventDefault();
        }
    }

    handlePaste(e) {
        e.preventDefault();
        let pastedData = e.clipboardData.getData('text');
        
        pastedData = this.filterValue(pastedData).slice(0, this.options.length);
        
        if (pastedData) {
            this.setValue(pastedData);
        }
    }

    setValue(value) {
        const strVal = this.filterValue(String(value)).slice(0, this.options.length);
        const arr = strVal.split('');
        
        for (let i = 0; i < this.options.length; i++) {
            const val = arr[i] || '';
            this.valueArray[i] = val;
            this.inputs[i].value = val;
            this.updateMasking(this.inputs[i], val);
        }

        if (arr.length < this.options.length) {
            this.inputs[arr.length].focus();
        } else {
            this.inputs[this.options.length - 1].focus();
        }

        this.triggerChange();
    }

    getValue() {
        return this.valueArray.join('');
    }

    clear() {
        this.setValue('');
    }

    triggerChange() {
        const code = this.getValue();
        if (this.options.onChange) {
            this.options.onChange(code);
        }
        if (code.length === this.options.length && this.options.onComplete) {
            this.options.onComplete(code);
        }
    }
}

// Export for module bundlers or global attach
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OTPInput;
} else {
    window.OTPInput = OTPInput;
}
