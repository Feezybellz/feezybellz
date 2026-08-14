<?php include __DIR__ . '/partials/header.php'; ?>
    <style>
        .jwt-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: var(--nav-bg);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .textarea-input {
            width: 100%;
            height: 150px;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            color: var(--text-main);
            font-family: monospace;
            resize: vertical;
            font-size: 1rem;
            line-height: 1.5;
        }

        .textarea-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .jwt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .jwt-grid { grid-template-columns: 1fr; }
        }

        .json-box {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .json-header {
            background: rgba(0,0,0,0.2);
            padding: 0.75rem 1rem;
            font-weight: bold;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .json-header.header-box { color: #ec4899; }
        .json-header.payload-box { color: #a855f7; }

        .json-content {
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            color: var(--text-main);
            overflow-x: auto;
            min-height: 150px;
        }

        .syntax-string { color: #10b981; }
        .syntax-number { color: #f59e0b; }
        .syntax-boolean { color: #3b82f6; }
        .syntax-null { color: #ef4444; }
        .syntax-key { color: var(--text-main); font-weight: bold; }
    </style>

    <div class="jwt-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">JWT Decoder</h1>
            <p style="color: var(--text-muted);">Securely decode JSON Web Tokens directly in your browser without sending them anywhere.</p>
        </div>

        <div>
            <h3 style="margin-bottom: 0.5rem;">Encoded Token <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(Paste your JWT here)</span></h3>
            <textarea id="jwt-input" class="textarea-input" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."></textarea>
        </div>

        <div id="error-msg" style="color: var(--error-color); margin-top: 1rem; font-weight: bold; display: none;">Invalid JWT Format</div>

        <div class="jwt-grid">
            <div class="json-box">
                <div class="json-header header-box">
                    <span>Header</span>
                    <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-muted);">Algorithm & Token Type</span>
                </div>
                <div class="json-content" id="header-output">{}</div>
            </div>

            <div class="json-box">
                <div class="json-header payload-box">
                    <span>Payload</span>
                    <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-muted);">Data</span>
                </div>
                <div class="json-content" id="payload-output">{}</div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const jwtInput = document.getElementById('jwt-input');
        const headerOutput = document.getElementById('header-output');
        const payloadOutput = document.getElementById('payload-output');
        const errorMsg = document.getElementById('error-msg');

        jwtInput.addEventListener('input', decodeJWT);

        function decodeJWT() {
            const token = jwtInput.value.trim();
            if (!token) {
                headerOutput.innerHTML = '{}';
                payloadOutput.innerHTML = '{}';
                errorMsg.style.display = 'none';
                return;
            }

            const parts = token.split('.');
            if (parts.length !== 3) {
                errorMsg.style.display = 'block';
                return;
            }

            try {
                const header = JSON.parse(b64DecodeUnicode(parts[0]));
                const payload = JSON.parse(b64DecodeUnicode(parts[1]));
                
                headerOutput.innerHTML = syntaxHighlight(JSON.stringify(header, null, 4));
                payloadOutput.innerHTML = syntaxHighlight(JSON.stringify(payload, null, 4));
                errorMsg.style.display = 'none';
            } catch (e) {
                errorMsg.style.display = 'block';
            }
        }

        // properly handle unicode in base64url
        function b64DecodeUnicode(str) {
            str = str.replace(/-/g, '+').replace(/_/g, '/');
            switch (str.length % 4) {
                case 0: break;
                case 2: str += "=="; break;
                case 3: str += "="; break;
                default: throw "Illegal base64url string!";
            }
            return decodeURIComponent(Array.prototype.map.call(atob(str), function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
        }

        function syntaxHighlight(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                let cls = 'syntax-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'syntax-key';
                    } else {
                        cls = 'syntax-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'syntax-boolean';
                } else if (/null/.test(match)) {
                    cls = 'syntax-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
        }
    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
