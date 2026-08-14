<?php 
$title = "Pro QR Code Generator - Tools"; 
include __DIR__ . '/partials/header.php'; 
?>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <style>
        .header {
            text-align: center;
            padding: 3rem 1rem;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--surface-color);
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.125rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        @media (min-width: 992px) {
            .container {
                grid-template-columns: 2fr 1fr;
            }
        }

        .card {
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-title svg {
            color: var(--primary-color);
        }

        /* Type Selector */
        .type-selector-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .type-selector {
            display: flex;
            gap: 0.5rem;
            overflow: hidden;
            flex-wrap: nowrap;
            flex-grow: 1;
        }

        .type-btn {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .type-btn:hover {
            border-color: var(--text-main);
            color: var(--text-main);
        }

        .type-btn.active {
            background-color: rgba(56, 189, 248, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .more-dropdown-container {
            position: relative;
            display: none;
        }

        .more-dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            z-index: 50;
            min-width: 150px;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0.5rem;
        }

        .more-dropdown-content.show {
            display: flex;
        }

        .more-dropdown-content .type-btn {
            width: 100%;
            border: none;
            background: transparent;
            justify-content: flex-start;
        }

        .more-dropdown-content .type-btn:hover {
            background-color: var(--bg-color);
        }

        .more-dropdown-content .type-btn.active {
            background-color: rgba(56, 189, 248, 0.1);
        }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.2em;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }
        .form-row > * {
            flex: 1;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Suggestions Dropdown */
        .suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
            z-index: 60;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            flex-direction: column;
            margin-top: 0.25rem;
        }

        .suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            color: var(--text-main);
            transition: background-color 0.2s;
            line-height: 1.3;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background-color: var(--bg-color);
            color: var(--primary-color);
        }

        /* Design Grid */
        .design-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .color-picker-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--bg-color);
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }

        .color-picker {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            padding: 0;
            background: none;
        }
        
        .color-picker::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        .color-picker::-webkit-color-swatch {
            border: none;
            border-radius: 4px;
        }

        /* Preview Sidebar */
        .preview-sidebar {
            position: sticky;
            top: 2rem;
        }

        .qr-preview-container {
            background-color: #ffffff;
            border-radius: 1rem;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            color: var(--primary-text);
            background-color: var(--primary-color);
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .btn-outline:hover {
            background-color: var(--bg-color);
            border-color: var(--text-main);
        }

    </style>
</head>
<body>

    <div class="header">
        <h1>Pro QR Code Generator</h1>
        <p>Create beautiful, customized QR codes for URLs, WiFi, vCards, and more.</p>
    </div>

    <div class="container">
        
        <div class="left-col">
            <!-- Data Section -->
            <div class="card">
                <h2 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Data Content
                </h2>
                
                <div class="type-selector-container" id="typeSelectorContainer">
                    <div class="type-selector" id="typeSelector">
                        <button class="type-btn active" data-target="tab-url">URL / Link</button>
                        <button class="type-btn" data-target="tab-text">Text</button>
                        <button class="type-btn" data-target="tab-email">Email</button>
                        <button class="type-btn" data-target="tab-phone">Phone / SMS</button>
                        <button class="type-btn" data-target="tab-wifi">WiFi</button>
                        <button class="type-btn" data-target="tab-vcard">vCard</button>
                        <button class="type-btn" data-target="tab-crypto">Crypto</button>
                        <button class="type-btn" data-target="tab-location">Location</button>
                    </div>
                    <div class="more-dropdown-container" id="moreDropdownContainer">
                        <button class="type-btn" id="moreBtn">
                            More 
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div class="more-dropdown-content" id="moreDropdownContent">
                            <!-- Overflowing buttons will be injected here -->
                        </div>
                    </div>
                </div>

                <!-- URL Tab -->
                <div id="tab-url" class="tab-content active">
                    <div class="form-group">
                        <label>Website URL (or Social Media link)</label>
                        <input type="url" id="val-url" class="form-control data-trigger" value="https://antigravity.dev" placeholder="https://example.com">
                    </div>
                </div>

                <!-- Text Tab -->
                <div id="tab-text" class="tab-content">
                    <div class="form-group">
                        <label>Message / Text</label>
                        <textarea id="val-text" class="form-control data-trigger" placeholder="Hello World!"></textarea>
                    </div>
                </div>

                <!-- Email Tab -->
                <div id="tab-email" class="tab-content">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="val-email" class="form-control data-trigger" placeholder="hello@example.com">
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" id="val-email-sub" class="form-control data-trigger" placeholder="Optional subject">
                    </div>
                    <div class="form-group">
                        <label>Body</label>
                        <textarea id="val-email-body" class="form-control data-trigger" placeholder="Optional message body"></textarea>
                    </div>
                </div>

                <!-- Phone / SMS Tab -->
                <div id="tab-phone" class="tab-content">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Action</label>
                            <select id="val-phone-type" class="form-control data-trigger">
                                <option value="tel">Make a Call</option>
                                <option value="sms">Send an SMS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" id="val-phone" class="form-control data-trigger" placeholder="+1234567890">
                        </div>
                    </div>
                    <div class="form-group" id="sms-body-group" style="display:none;">
                        <label>SMS Message</label>
                        <textarea id="val-sms-body" class="form-control data-trigger" placeholder="Message content"></textarea>
                    </div>
                </div>

                <!-- WiFi Tab -->
                <div id="tab-wifi" class="tab-content">
                    <div class="form-group">
                        <label>Network Name (SSID)</label>
                        <input type="text" id="val-wifi-ssid" class="form-control data-trigger" placeholder="MyWiFiNetwork">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password</label>
                            <input type="text" id="val-wifi-pass" class="form-control data-trigger" placeholder="SecretPassword">
                        </div>
                        <div class="form-group">
                            <label>Encryption</label>
                            <select id="val-wifi-type" class="form-control data-trigger">
                                <option value="WPA">WPA/WPA2</option>
                                <option value="WEP">WEP</option>
                                <option value="nopass">None</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" id="val-wifi-hidden" class="data-trigger">
                            Hidden Network
                        </label>
                    </div>
                </div>

                <!-- vCard Tab -->
                <div id="tab-vcard" class="tab-content">
                    <div class="form-group">
                        <label>vCard Version</label>
                        <select id="val-vcard-version" class="form-control data-trigger">
                            <option value="3.0">Version 3.0</option>
                            <option value="2.1">Version 2.1</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" id="val-vcard-fn" class="form-control data-trigger" placeholder="John">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" id="val-vcard-ln" class="form-control data-trigger" placeholder="Doe">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Organization</label>
                            <input type="text" id="val-vcard-org" class="form-control data-trigger" placeholder="Acme Inc">
                        </div>
                        <div class="form-group">
                            <label>Position (Work)</label>
                            <input type="text" id="val-vcard-title" class="form-control data-trigger" placeholder="Manager">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone (Mobile)</label>
                            <input type="tel" id="val-vcard-cell" class="form-control data-trigger" placeholder="+1234567890">
                        </div>
                        <div class="form-group">
                            <label>Phone (Work)</label>
                            <input type="tel" id="val-vcard-work" class="form-control data-trigger" placeholder="+1987654321">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone (Private)</label>
                            <input type="tel" id="val-vcard-home" class="form-control data-trigger" placeholder="+1122334455">
                        </div>
                        <div class="form-group">
                            <label>Fax (Work)</label>
                            <input type="tel" id="val-vcard-fax-work" class="form-control data-trigger" placeholder="+1112223333">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fax (Private)</label>
                            <input type="tel" id="val-vcard-fax-home" class="form-control data-trigger" placeholder="+4445556666">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="val-vcard-email" class="form-control data-trigger" placeholder="john@example.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" id="val-vcard-url" class="form-control data-trigger" placeholder="https://example.com">
                    </div>
                    
                    <h3 style="font-size: 1rem; margin: 1.5rem 0 1rem 0; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Address</h3>
                    
                    <div class="form-group">
                        <label>Street</label>
                        <input type="text" id="val-vcard-street" class="form-control data-trigger" placeholder="123 Main St">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" id="val-vcard-city" class="form-control data-trigger" placeholder="New York">
                        </div>
                        <div class="form-group">
                            <label>State / Province</label>
                            <input type="text" id="val-vcard-state" class="form-control data-trigger" placeholder="NY">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Zipcode</label>
                            <input type="text" id="val-vcard-zip" class="form-control data-trigger" placeholder="10001">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" id="val-vcard-country" class="form-control data-trigger" placeholder="USA">
                        </div>
                    </div>
                </div>

                <!-- Crypto Tab -->
                <div id="tab-crypto" class="tab-content">
                    <div class="form-group">
                        <label>Currency</label>
                        <select id="val-crypto-type" class="form-control data-trigger">
                            <option value="bitcoin">Bitcoin (BTC)</option>
                            <option value="ethereum">Ethereum (ETH)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Wallet Address</label>
                        <input type="text" id="val-crypto-addr" class="form-control data-trigger" placeholder="bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh">
                    </div>
                    <div class="form-group">
                        <label>Amount (Optional)</label>
                        <input type="number" step="0.0001" id="val-crypto-amount" class="form-control data-trigger" placeholder="0.05">
                    </div>
                </div>

                <!-- Location Tab -->
                <div id="tab-location" class="tab-content">
                    <div class="form-group" style="position: relative;">
                        <label>Search Address</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="val-loc-address" class="form-control" placeholder="1600 Pennsylvania Avenue NW, Washington, DC" autocomplete="off">
                            <button id="btn-loc-search" class="btn" style="width: auto; margin-bottom: 0;">Search</button>
                        </div>
                        <div id="loc-suggestions" class="suggestions-dropdown"></div>
                        <small id="loc-search-status" style="color: var(--text-muted); display: none; margin-top: 0.5rem;"></small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Latitude</label>
                            <input type="number" step="any" id="val-loc-lat" class="form-control data-trigger" placeholder="40.7128">
                        </div>
                        <div class="form-group">
                            <label>Longitude</label>
                            <input type="number" step="any" id="val-loc-lng" class="form-control data-trigger" placeholder="-74.0060">
                        </div>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">This will open the map application on the scanner's phone at these precise coordinates.</p>
                </div>
            </div>

            <!-- Customization Section -->
            <div class="card">
                <h2 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                    Design & Colors
                </h2>
                
                <div class="design-grid">
                    <div class="form-group">
                        <label>Dot Style</label>
                        <select id="opt-dots" class="form-control style-trigger">
                            <option value="square">Square</option>
                            <option value="dots">Dots</option>
                            <option value="rounded">Rounded</option>
                            <option value="extra-rounded">Extra Rounded</option>
                            <option value="classy">Classy</option>
                            <option value="classy-rounded">Classy Rounded</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Corner Square Style</label>
                        <select id="opt-corners" class="form-control style-trigger">
                            <option value="square">Square</option>
                            <option value="dot">Dot</option>
                            <option value="extra-rounded">Extra Rounded</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Foreground Color</label>
                        <div class="color-picker-wrap">
                            <input type="color" id="opt-color-fg" class="color-picker style-trigger" value="#000000">
                            <input type="text" id="opt-color-fg-text" class="form-control style-trigger" value="#000000" style="flex:1;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <div class="color-picker-wrap">
                            <input type="color" id="opt-color-bg" class="color-picker style-trigger" value="#ffffff">
                            <input type="text" id="opt-color-bg-text" class="form-control style-trigger" value="#ffffff" style="flex:1;">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label>Add Logo (Optional)</label>
                    <input type="file" id="opt-logo" accept="image/*" class="form-control style-trigger" style="padding-bottom: 0.5rem;">
                </div>
            </div>

        </div>

        <div class="right-col">
            <div class="preview-sidebar">
                <div class="qr-preview-container" id="qr-container">
                    <!-- QR Code will be rendered here -->
                </div>
                
                <button id="btn-dl-png" class="btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download PNG
                </button>
                <button id="btn-dl-svg" class="btn btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download SVG
                </button>
            </div>
        </div>

    </div>

    <script>
        // Setup QR Code instance
        const qrCode = new QRCodeStyling({
            width: 300,
            height: 300,
            type: "svg",
            data: "https://antigravity.dev",
            image: "",
            dotsOptions: {
                color: "#000000",
                type: "square"
            },
            backgroundOptions: {
                color: "#ffffff",
            },
            cornersSquareOptions: {
                type: "square"
            },
            imageOptions: {
                crossOrigin: "anonymous",
                margin: 5
            }
        });

        // Initial Render
        qrCode.append(document.getElementById("qr-container"));

        // Tab Switching Logic
        let activeTab = 'tab-url';
        
        function handleTabClick(e) {
            const targetBtn = e.currentTarget;
            if (!targetBtn.classList.contains('type-btn') || targetBtn.id === 'moreBtn') return;

            document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            targetBtn.classList.add('active');
            
            // Also sync active state if clicked from dropdown
            const dataTarget = targetBtn.dataset.target;
            document.querySelectorAll(`.type-btn[data-target="${dataTarget}"]`).forEach(b => b.classList.add('active'));

            activeTab = dataTarget;
            document.getElementById(activeTab).classList.add('active');
            
            // Close dropdown if open
            document.getElementById('moreDropdownContent').classList.remove('show');
            
            updateQRData();
        }

        document.querySelectorAll('.type-btn').forEach(btn => {
            if(btn.id !== 'moreBtn') btn.addEventListener('click', handleTabClick);
        });

        // Responsive Tabs Logic
        const selectorContainer = document.getElementById('typeSelectorContainer');
        const selector = document.getElementById('typeSelector');
        const moreContainer = document.getElementById('moreDropdownContainer');
        const moreBtn = document.getElementById('moreBtn');
        const moreContent = document.getElementById('moreDropdownContent');

        moreBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            moreContent.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!moreContainer.contains(e.target)) {
                moreContent.classList.remove('show');
            }
        });

        function adjustTabs() {
            // Move all items back to main selector first
            while (moreContent.firstChild) {
                selector.appendChild(moreContent.firstChild);
            }
            moreContainer.style.display = 'none';

            // Check if overflow happens
            let isOverflowing = selector.scrollWidth > selector.clientWidth;
            if (isOverflowing) {
                moreContainer.style.display = 'block';
                // Subtract moreBtn width
                const availableWidth = selectorContainer.clientWidth - moreBtn.clientWidth - 10;
                
                let currentWidth = 0;
                const buttons = Array.from(selector.children);
                
                for (let i = 0; i < buttons.length; i++) {
                    const btn = buttons[i];
                    currentWidth += btn.offsetWidth + 8; // width + gap
                    if (currentWidth > availableWidth) {
                        // Move this and all subsequent buttons to dropdown
                        for (let j = i; j < buttons.length; j++) {
                            moreContent.appendChild(buttons[j]);
                        }
                        break;
                    }
                }
            }
        }

        window.addEventListener('resize', adjustTabs);
        // Initial adjust
        setTimeout(adjustTabs, 100);

        // Phone/SMS toggle
        document.getElementById('val-phone-type').addEventListener('change', (e) => {
            document.getElementById('sms-body-group').style.display = e.target.value === 'sms' ? 'block' : 'none';
        });

        // Color sync
        document.getElementById('opt-color-fg').addEventListener('input', (e) => document.getElementById('opt-color-fg-text').value = e.target.value);
        document.getElementById('opt-color-fg-text').addEventListener('input', (e) => document.getElementById('opt-color-fg').value = e.target.value);
        document.getElementById('opt-color-bg').addEventListener('input', (e) => document.getElementById('opt-color-bg-text').value = e.target.value);
        document.getElementById('opt-color-bg-text').addEventListener('input', (e) => document.getElementById('opt-color-bg').value = e.target.value);

        // File upload for Logo
        let currentLogo = "";
        document.getElementById('opt-logo').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    currentLogo = event.target.result;
                    updateQRDesign();
                };
                reader.readAsDataURL(file);
            } else {
                currentLogo = "";
                updateQRDesign();
            }
        });

        // Data string compiler
        function compileQRData() {
            let data = "";
            switch (activeTab) {
                case 'tab-url':
                    data = document.getElementById('val-url').value || 'https://antigravity.dev';
                    break;
                case 'tab-text':
                    data = document.getElementById('val-text').value || 'Hello';
                    break;
                case 'tab-email':
                    const em = document.getElementById('val-email').value;
                    const sub = encodeURIComponent(document.getElementById('val-email-sub').value);
                    const body = encodeURIComponent(document.getElementById('val-email-body').value);
                    data = `mailto:${em}?subject=${sub}&body=${body}`;
                    break;
                case 'tab-phone':
                    const ph = document.getElementById('val-phone').value;
                    if (document.getElementById('val-phone-type').value === 'sms') {
                        const msg = document.getElementById('val-sms-body').value;
                        data = `smsto:${ph}:${msg}`;
                    } else {
                        data = `tel:${ph}`;
                    }
                    break;
                case 'tab-wifi':
                    const ssid = document.getElementById('val-wifi-ssid').value;
                    const pass = document.getElementById('val-wifi-pass').value;
                    const enc = document.getElementById('val-wifi-type').value;
                    const hid = document.getElementById('val-wifi-hidden').checked ? 'true' : 'false';
                    data = `WIFI:T:${enc};S:${ssid};P:${pass};H:${hid};;`;
                    break;
                case 'tab-vcard':
                    const version = document.getElementById('val-vcard-version').value;
                    const fn = document.getElementById('val-vcard-fn').value;
                    const ln = document.getElementById('val-vcard-ln').value;
                    const org = document.getElementById('val-vcard-org').value;
                    const title = document.getElementById('val-vcard-title').value;
                    const cell = document.getElementById('val-vcard-cell').value;
                    const work = document.getElementById('val-vcard-work').value;
                    const home = document.getElementById('val-vcard-home').value;
                    const faxWork = document.getElementById('val-vcard-fax-work').value;
                    const faxHome = document.getElementById('val-vcard-fax-home').value;
                    const email = document.getElementById('val-vcard-email').value;
                    const url = document.getElementById('val-vcard-url').value;
                    
                    const street = document.getElementById('val-vcard-street').value;
                    const city = document.getElementById('val-vcard-city').value;
                    const state = document.getElementById('val-vcard-state').value;
                    const zip = document.getElementById('val-vcard-zip').value;
                    const country = document.getElementById('val-vcard-country').value;

                    let vcard = `BEGIN:VCARD\nVERSION:${version}\n`;
                    vcard += `N:${ln};${fn};;;\n`;
                    vcard += `FN:${fn} ${ln}\n`;
                    
                    if (org) vcard += `ORG:${org}\n`;
                    if (title) vcard += `TITLE:${title}\n`;
                    
                    if (version === '3.0') {
                        if (cell) vcard += `TEL;TYPE=CELL,VOICE:${cell}\n`;
                        if (work) vcard += `TEL;TYPE=WORK,VOICE:${work}\n`;
                        if (home) vcard += `TEL;TYPE=HOME,VOICE:${home}\n`;
                        if (faxWork) vcard += `TEL;TYPE=WORK,FAX:${faxWork}\n`;
                        if (faxHome) vcard += `TEL;TYPE=HOME,FAX:${faxHome}\n`;
                        if (email) vcard += `EMAIL;TYPE=PREF,INTERNET:${email}\n`;
                        if (street || city || state || zip || country) {
                            vcard += `ADR;TYPE=WORK:;;${street};${city};${state};${zip};${country}\n`;
                        }
                    } else {
                        // Version 2.1
                        if (cell) vcard += `TEL;CELL;VOICE:${cell}\n`;
                        if (work) vcard += `TEL;WORK;VOICE:${work}\n`;
                        if (home) vcard += `TEL;HOME;VOICE:${home}\n`;
                        if (faxWork) vcard += `TEL;WORK;FAX:${faxWork}\n`;
                        if (faxHome) vcard += `TEL;HOME;FAX:${faxHome}\n`;
                        if (email) vcard += `EMAIL;PREF;INTERNET:${email}\n`;
                        if (street || city || state || zip || country) {
                            vcard += `ADR;WORK:;;${street};${city};${state};${zip};${country}\n`;
                        }
                    }
                    
                    if (url) vcard += `URL:${url}\n`;
                    
                    vcard += `END:VCARD`;
                    data = vcard;
                    break;
                case 'tab-crypto':
                    const curr = document.getElementById('val-crypto-type').value;
                    const addr = document.getElementById('val-crypto-addr').value;
                    const amt = document.getElementById('val-crypto-amount').value;
                    data = `${curr}:${addr}${amt ? '?amount='+amt : ''}`;
                    break;
                case 'tab-location':
                    const lat = document.getElementById('val-loc-lat').value;
                    const lng = document.getElementById('val-loc-lng').value;
                    const addressQuery = document.getElementById('val-loc-address').value.trim();
                    
                    if (lat && lng && lat !== '0' && lng !== '0') {
                        data = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
                    } else if (addressQuery) {
                        data = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressQuery)}`;
                    } else {
                        data = `https://www.google.com/maps`;
                    }
                    break;
            }
            return data;
        }

        function updateQRData() {
            qrCode.update({
                data: compileQRData()
            });
        }

        function updateQRDesign() {
            qrCode.update({
                dotsOptions: {
                    color: document.getElementById('opt-color-fg-text').value,
                    type: document.getElementById('opt-dots').value
                },
                backgroundOptions: {
                    color: document.getElementById('opt-color-bg-text').value,
                },
                cornersSquareOptions: {
                    type: document.getElementById('opt-corners').value
                },
                image: currentLogo
            });
        }

        // Attach listeners
        document.querySelectorAll('.data-trigger').forEach(el => el.addEventListener('input', updateQRData));
        document.querySelectorAll('.style-trigger').forEach(el => el.addEventListener('input', updateQRDesign));

        // Address Geocoding & Suggestions (OpenStreetMap Nominatim)
        let geocodeTimeout;
        const addressInput = document.getElementById('val-loc-address');
        const suggestionsBox = document.getElementById('loc-suggestions');
        const statusEl = document.getElementById('loc-search-status');

        addressInput.addEventListener('input', (e) => {
            clearTimeout(geocodeTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 3) {
                suggestionsBox.style.display = 'none';
                return;
            }

            // Show searching status
            statusEl.style.display = 'block';
            statusEl.style.color = 'var(--text-muted)';
            statusEl.textContent = 'Typing...';

            geocodeTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=5`, {
                        headers: { 'Accept-Language': 'en-US,en;q=0.9' }
                    });
                    const data = await response.json();
                    
                    suggestionsBox.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.textContent = item.display_name;
                            div.addEventListener('click', () => {
                                addressInput.value = item.display_name;
                                document.getElementById('val-loc-lat').value = item.lat;
                                document.getElementById('val-loc-lng').value = item.lon;
                                suggestionsBox.style.display = 'none';
                                statusEl.style.color = 'var(--success-color)';
                                statusEl.textContent = 'Selected: ' + item.display_name;
                                updateQRData();
                            });
                            suggestionsBox.appendChild(div);
                        });
                        suggestionsBox.style.display = 'flex';
                        statusEl.textContent = 'Found ' + data.length + ' suggestions.';
                    } else {
                        suggestionsBox.style.display = 'none';
                        statusEl.style.color = 'var(--warning-color)';
                        statusEl.textContent = 'No coordinates found.';
                        
                        Swal.fire({
                            title: 'Coordinates Not Found',
                            text: 'We couldn\'t find exact coordinates for this address. Would you like to use the raw address text to map to Google Maps instead?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: 'var(--primary-color)',
                            cancelButtonColor: 'var(--text-muted)',
                            confirmButtonText: 'Yes, use address',
                            cancelButtonText: 'Cancel',
                            background: 'var(--bg-card)',
                            color: 'var(--text-main)'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('val-loc-lat').value = '';
                                document.getElementById('val-loc-lng').value = '';
                                updateQRData();
                                statusEl.style.color = 'var(--success-color)';
                                statusEl.textContent = 'Using raw address text for map link.';
                            }
                        });
                    }
                } catch (err) {
                    console.error("Geocoding error", err);
                    suggestionsBox.style.display = 'none';
                    statusEl.style.color = 'var(--error-color)';
                    statusEl.textContent = 'Search failed. Please try again.';
                    
                    Swal.fire({
                        title: 'Search Failed',
                        text: 'The map service is currently unavailable. Would you like to use the raw address text to map to Google Maps instead?',
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary-color)',
                        cancelButtonColor: 'var(--text-muted)',
                        confirmButtonText: 'Yes, use address',
                        background: 'var(--bg-card)',
                        color: 'var(--text-main)'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.getElementById('val-loc-lat').value = '';
                            document.getElementById('val-loc-lng').value = '';
                            updateQRData();
                        }
                    });
                }
            }, 500); // 500ms debounce
        });

        // Hide suggestions on outside click
        document.addEventListener('click', (e) => {
            if (!addressInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
            }
        });

        // Manual search button fallback
        document.getElementById('btn-loc-search').addEventListener('click', async () => {
            // Trigger the suggestion logic manually for the first item
            const firstSuggestion = suggestionsBox.querySelector('.suggestion-item');
            if (firstSuggestion) {
                firstSuggestion.click();
            } else if (addressInput.value.trim().length >= 3) {
                // Force an immediate API call for exact match if nothing is open
                addressInput.dispatchEvent(new Event('input'));
            }
        });

        // Downloads
        document.getElementById('btn-dl-png').addEventListener('click', () => {
            qrCode.download({ name: "qr-code", extension: "png" });
        });
        document.getElementById('btn-dl-svg').addEventListener('click', () => {
            qrCode.download({ name: "qr-code", extension: "svg" });
        });

    </script>
<?php include __DIR__ . '/partials/footer.php'; ?>
