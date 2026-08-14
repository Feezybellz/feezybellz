<?php include __DIR__ . '/partials/header.php'; ?>
    <style>
        .pwd-container {
            max-width: 800px;
            margin: 2rem auto;
            background: var(--nav-bg);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .password-display-area {
            background: var(--bg-color);
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        #password-display {
            font-family: monospace;
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            word-break: break-all;
            margin-right: 1rem;
        }

        .btn-copy {
            background: var(--primary-color);
            color: var(--primary-text);
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-copy:hover {
            opacity: 0.9;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 600px) {
            .options-grid { grid-template-columns: 1fr; }
        }

        .option-group {
            background: var(--bg-color);
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
        }

        .option-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .option-row:last-child {
            margin-bottom: 0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            font-size: 1.1rem;
        }

        input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .slider-container {
            margin-top: 1.5rem;
        }

        input[type="range"] {
            width: 100%;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            outline: none;
            -webkit-appearance: none;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }

        .copy-toast {
            position: fixed;
            bottom: 20px; left: 50%;
            transform: translateX(-50%);
            background: var(--text-main);
            color: var(--bg-color);
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            font-weight: bold;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>

    <div class="pwd-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Secure Password Generator</h1>
            <p style="color: var(--text-muted);">Generate highly secure, totally random passwords directly on your device.</p>
        </div>

        <div class="password-display-area">
            <div id="password-display">Loading...</div>
            <button class="btn-copy" id="btn-copy" onclick="copyPassword()">
                <i data-lucide="copy"></i> Copy
            </button>
        </div>

        <div class="options-grid">
            <div class="option-group">
                <div class="option-row">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-upper" checked onchange="generatePassword()">
                        Uppercase (A-Z)
                    </label>
                </div>
                <div class="option-row">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-lower" checked onchange="generatePassword()">
                        Lowercase (a-z)
                    </label>
                </div>
                <div class="option-row">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-numbers" checked onchange="generatePassword()">
                        Numbers (0-9)
                    </label>
                </div>
                <div class="option-row">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-symbols" checked onchange="generatePassword()">
                        Symbols (!@#$%)
                    </label>
                </div>
            </div>

            <div class="option-group" style="display: flex; flex-direction: column; justify-content: center;">
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold;">
                    <span>Password Length</span>
                    <span id="length-val" style="color: var(--primary-color);">16</span>
                </div>
                <div class="slider-container">
                    <input type="range" id="length-slider" min="4" max="64" value="16" oninput="updateLength(this.value)">
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <button class="btn-copy" style="margin: 0 auto; background: var(--bg-color); border: 2px solid var(--border-color); color: var(--text-main);" onclick="generatePassword()">
                <i data-lucide="refresh-cw"></i> Generate New Password
            </button>
        </div>
    </div>
    
    <div class="copy-toast" id="copy-toast">Password copied to clipboard!</div>

    <script>
        lucide.createIcons();

        const pwdDisplay = document.getElementById('password-display');
        const lengthSlider = document.getElementById('length-slider');
        const lengthVal = document.getElementById('length-val');
        
        const chkUpper = document.getElementById('chk-upper');
        const chkLower = document.getElementById('chk-lower');
        const chkNumbers = document.getElementById('chk-numbers');
        const chkSymbols = document.getElementById('chk-symbols');
        
        const charsUpper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const charsLower = "abcdefghijklmnopqrstuvwxyz";
        const charsNumbers = "0123456789";
        const charsSymbols = "!@#$%^&*()_+~`|}{[]:;?><,./-=";

        function updateLength(val) {
            lengthVal.innerText = val;
            generatePassword();
        }

        function generatePassword() {
            let charset = "";
            if (chkUpper.checked) charset += charsUpper;
            if (chkLower.checked) charset += charsLower;
            if (chkNumbers.checked) charset += charsNumbers;
            if (chkSymbols.checked) charset += charsSymbols;

            if (charset === "") {
                pwdDisplay.innerText = "Select at least one option";
                pwdDisplay.style.color = "var(--error-color)";
                return;
            }

            pwdDisplay.style.color = "var(--primary-color)";
            let length = parseInt(lengthSlider.value);
            let password = "";
            
            // Use cryptographically secure random values
            const array = new Uint32Array(length);
            window.crypto.getRandomValues(array);
            
            for (let i = 0; i < length; i++) {
                password += charset[array[i] % charset.length];
            }
            
            pwdDisplay.innerText = password;
        }

        function copyPassword() {
            const pwd = pwdDisplay.innerText;
            if (pwd === "Select at least one option") return;
            
            navigator.clipboard.writeText(pwd).then(() => {
                const toast = document.getElementById('copy-toast');
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, 2000);
            });
        }

        // Generate on load
        generatePassword();
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
