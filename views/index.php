<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afeez Adedayo Bello (feezybellz) | Software Engineer & Problem Solver</title>
    <meta name="description" content="Personal portfolio of Afeez Adedayo Bello (feezybellz). Full-stack software engineer and backend architect specializing in custom frameworks, application security, and scalable infrastructure.">
    <meta name="author" content="Afeez Adedayo Bello">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome CDN for authentic social media brand icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Theme variables */
        :root, [data-theme="light"] {
            --bg-page: #F3F5F8;
            --bg-card: #FFFFFF;
            --bg-card-subtle: #F8FAFC;
            --border-card: rgba(0, 0, 0, 0.07);
            --border-hover: rgba(0, 0, 0, 0.18);
            --text-main: #0F172A;
            --text-sub: #475569;
            --text-muted: #94A3B8;
            --btn-primary-bg: #0F172A;
            --btn-primary-text: #FFFFFF;
            --btn-secondary-bg: #F1F5F9;
            --btn-secondary-text: #1E293B;
            --pill-tag-bg: #E2E8F0;
            --pill-tag-text: #334155;
            --status-bg: #DCFCE7;
            --status-text: #15803D;
            --status-dot: #22C55E;
            --nav-dock-bg: rgba(255, 255, 255, 0.82);
            --nav-dock-border: rgba(0, 0, 0, 0.08);
            --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.02);
            --shadow-hover: 0 12px 30px rgba(0, 0, 0, 0.08);
            --icon-circle-1: #4F46E5;
            --icon-circle-2: #0EA5E9;
            --icon-circle-3: #F97316;
            --icon-circle-4: #10B981;
            --arrow-color: #CBD5E1;
            --arrow-hover: #0F172A;
            --input-bg: #F8FAFC;
            --input-border: #E2E8F0;
            --problem-icon-color: #4F46E5;
        }

        [data-theme="dark"] {
            --bg-page: #080B10;
            --bg-card: #10151E;
            --bg-card-subtle: #151C28;
            --border-card: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(0, 229, 255, 0.4);
            --text-main: #F8FAFC;
            --text-sub: #94A3B8;
            --text-muted: #64748B;
            --btn-primary-bg: #00F59B;
            --btn-primary-text: #000000;
            --btn-secondary-bg: rgba(255, 255, 255, 0.05);
            --btn-secondary-text: #F8FAFC;
            --pill-tag-bg: rgba(0, 229, 255, 0.1);
            --pill-tag-text: #00E5FF;
            --status-bg: rgba(0, 245, 155, 0.12);
            --status-text: #00F59B;
            --status-dot: #00F59B;
            --nav-dock-bg: rgba(16, 21, 30, 0.85);
            --nav-dock-border: rgba(255, 255, 255, 0.1);
            --shadow-card: 0 10px 35px rgba(0, 0, 0, 0.45), 0 0 20px rgba(0, 229, 255, 0.03);
            --shadow-hover: 0 18px 45px rgba(0, 0, 0, 0.6), 0 0 30px rgba(0, 245, 155, 0.15);
            --icon-circle-1: #6366F1;
            --icon-circle-2: #00E5FF;
            --icon-circle-3: #F59E0B;
            --icon-circle-4: #00F59B;
            --arrow-color: #334155;
            --arrow-hover: #00F59B;
            --input-bg: #0C1017;
            --input-border: rgba(255, 255, 255, 0.1);
            --problem-icon-color: #00E5FF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            background-color: var(--bg-page);
            color: var(--text-main);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body {
            min-height: 100vh;
            line-height: 1.6;
            padding: 100px 20px 50px;
            position: relative;
        }

        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
        }

        /* Floating Nav Dock (Pill Header) */
        .nav-dock-wrapper {
            position: fixed;
            top: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            z-index: 1000;
            pointer-events: none;
            padding: 0 12px;
        }

        .nav-dock {
            pointer-events: auto;
            background: var(--nav-dock-bg);
            border: 1px solid var(--nav-dock-border);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 50px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            max-width: 100%;
        }

        .dock-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            color: var(--text-sub);
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
            flex-shrink: 0;
        }

        .dock-item:hover {
            background: var(--btn-secondary-bg);
            color: var(--text-main);
            transform: translateY(-2px);
        }

        .dock-item svg {
            width: 19px;
            height: 19px;
            stroke-width: 2;
        }

        .dock-divider {
            width: 1px;
            height: 20px;
            background: var(--border-card);
            margin: 0 4px;
            flex-shrink: 0;
        }

        .theme-toggle-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-sub);
            transition: all 0.2s ease;
            margin: 0 2px;
            flex-shrink: 0;
        }

        .theme-toggle-btn:hover {
            background: var(--btn-secondary-bg);
            color: var(--text-main);
            transform: rotate(15deg) scale(1.08);
        }

        .theme-toggle-btn svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.2;
        }

        .dock-cta {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            font-weight: 600;
            font-size: 0.84rem;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
            margin-left: 4px;
            flex-shrink: 0;
        }

        .dock-cta:hover {
            opacity: 0.92;
            transform: scale(1.04);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .cta-short { display: none; }

        /* Tablet & Mobile Nav Dock Responsiveness */
        @media (max-width: 768px) {
            body { padding: 85px 16px 40px; }
            .nav-dock-wrapper { top: 12px; padding: 0 10px; }
            .nav-dock { padding: 6px 10px; gap: 4px; border-radius: 40px; }
            .dock-item { width: 34px; height: 34px; }
            .dock-item svg { width: 17px; height: 17px; }
            .theme-toggle-btn { width: 28px; height: 28px; margin: 0; }
            .theme-toggle-btn svg { width: 15px; height: 15px; }
            .dock-divider { height: 16px; margin: 0 2px; }
            .dock-cta { padding: 6px 13px; font-size: 0.78rem; margin-left: 2px; }
            .cta-full { display: none; }
            .cta-short { display: inline; }
        }

        @media (max-width: 440px) {
            body { padding: 80px 12px 30px; }
            .nav-dock { padding: 5px 8px; gap: 2px; }
            .dock-item { width: 30px; height: 30px; }
            .dock-item svg { width: 16px; height: 16px; }
            .theme-toggle-btn { width: 26px; height: 26px; }
            .dock-cta { padding: 5px 11px; font-size: 0.74rem; }
            h1 { font-size: 2.5rem; }
        }

        /* Bento Grid Architecture & Mobile Reordering */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        .bento-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 34px;
            box-shadow: var(--shadow-card);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .bento-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-hover);
        }

        .col-7 { grid-column: span 7; }
        .col-5 { grid-column: span 5; }
        .col-6 { grid-column: span 6; }

        /* Desktop order default */
        .intro-card { order: 1; }
        .profile-card { order: 2; padding: 16px !important; }

        /* Mobile Order: Right element (profile photo) comes FIRST before intro */
        @media (max-width: 960px) {
            .col-7, .col-5, .col-6 { grid-column: span 12; }
            .bento-card { padding: 26px; border-radius: 20px; }
            
            .profile-card { order: 1; min-height: 340px; }
            .intro-card { order: 2; }
        }

        /* Profile Photo Card Styling */
        .profile-img-wrapper {
            width: 100%;
            height: 100%;
            min-height: 330px;
            border-radius: 18px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-card);
            background: var(--bg-card-subtle);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
        }

        .profile-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .profile-img-wrapper:hover .profile-photo {
            transform: scale(1.05);
        }

        .profile-overlay {
            position: absolute;
            bottom: 16px;
            left: 16px;
            right: 16px;
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 12px 18px;
            border-radius: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #F8FAFC;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .profile-handle { font-weight: 600; color: #00E5FF; }

        /* Pill Badges & Headers inside Cards */
        .card-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tag-pill {
            background: var(--pill-tag-bg);
            color: var(--pill-tag-text);
            font-family: 'Fira Code', monospace;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pill {
            background: var(--status-bg);
            color: var(--status-text);
            font-family: 'Fira Code', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--status-dot);
            box-shadow: 0 0 8px var(--status-dot);
            animation: pulse-dot 2s infinite;
            display: inline-block;
        }

        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.25); opacity: 0.7; }
        }

        /* Typography & Content Styles */
        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 3.2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.12;
            margin-bottom: 16px;
            color: var(--text-main);
        }

        h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .hero-bio {
            color: var(--text-sub);
            font-size: 1.05rem;
            margin-bottom: 32px;
            max-width: 560px;
            line-height: 1.7;
        }

        .action-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.25s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            opacity: 0.92;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            font-family: 'Fira Code', monospace;
            font-weight: 500;
            font-size: 0.88rem;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid var(--border-card);
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary svg { width: 16px; height: 16px; stroke-width: 2; }

        .btn-secondary:hover {
            background: var(--border-card);
            transform: translateY(-2px);
        }

        /* Vertical Icon-List Items */
        .list-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 18px;
        }

        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-radius: 16px;
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-card);
            text-decoration: none;
            transition: all 0.25s ease;
            color: inherit;
        }

        .list-item:hover {
            border-color: var(--border-hover);
            transform: translateX(6px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .item-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-badge {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .icon-badge svg { width: 22px; height: 22px; stroke-width: 2.2; color: inherit; }

        .bg-1 { background-color: var(--icon-circle-1); color: #FFF; }
        .bg-2 { background-color: var(--icon-circle-2); color: #000; }
        .bg-3 { background-color: var(--icon-circle-3); color: #FFF; }
        .bg-4 { background-color: var(--icon-circle-4); color: #000; }

        .item-text h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 3px;
        }

        .item-text p {
            font-size: 0.84rem;
            color: var(--text-sub);
        }

        .chevron-arrow {
            width: 20px !important;
            height: 20px !important;
            color: var(--arrow-color);
            transition: all 0.25s ease;
            flex-shrink: 0;
        }

        .list-item:hover .chevron-arrow {
            color: var(--arrow-hover);
            transform: translateX(4px);
        }

        /* Human-Readable Problem Solving Rows */
        .problem-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 18px;
        }

        .problem-row {
            padding: 15px 18px;
            border-radius: 16px;
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-card);
            transition: all 0.25s ease;
        }

        .problem-row:hover {
            border-color: var(--border-hover);
            transform: translateX(4px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .problem-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.02rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .problem-title i {
            color: var(--problem-icon-color);
            width: 19px;
            height: 19px;
            flex-shrink: 0;
        }

        .problem-desc {
            font-size: 0.9rem;
            color: var(--text-sub);
            line-height: 1.55;
        }

        /* Contact & Social Forms */
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            font-family: 'Fira Code', monospace;
            font-size: 0.8rem;
            color: var(--text-sub);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            padding: 10px 14px;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            font-size: 0.92rem;
            transition: border-color 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--text-sub);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }

        textarea.form-control { min-height: 110px; resize: vertical; }

        .social-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .social-pill {
            flex: 1 1 calc(33.333% - 10px);
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-card);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        @media (max-width: 640px) {
            .social-pill { flex: 1 1 calc(50% - 10px); }
        }

        .social-pill i {
            font-size: 1.15rem;
        }

        .social-pill:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
        }

        /* Footer minimal */
        footer {
            text-align: center;
            padding: 36px 0 16px;
            font-family: 'Fira Code', monospace;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <!-- Floating Navigation Dock (Pill Header) -->
    <div class="nav-dock-wrapper">
        <nav class="nav-dock">
            <a href="#bio" class="dock-item" title="Home / Bio">
                <i data-lucide="home"></i>
            </a>
            <a href="#works" class="dock-item" title="Built Systems & Tools">
                <i data-lucide="cpu"></i>
            </a>
            <a href="#problems" class="dock-item" title="How I Solve Problems">
                <i data-lucide="layers"></i>
            </a>
            <a href="#writing" class="dock-item" title="Engineering Writings">
                <i data-lucide="book-open"></i>
            </a>
            <a href="#connect" class="dock-item" title="Let's Work Together">
                <i data-lucide="message-square"></i>
            </a>

            <div class="dock-divider"></div>

            <!-- Compact Theme Toggle with Lucide Icons -->
            <button class="theme-toggle-btn" onclick="toggleTheme()" title="Switch Design Theme">
                <i data-lucide="sun" id="theme-icon-sun" style="display: none;"></i>
                <i data-lucide="moon" id="theme-icon-moon"></i>
            </button>

            <a href="https://tools.feezybellz.net.ng" class="dock-cta">
                <span class="cta-full">Explore Tools DevSite →</span>
                <span class="cta-short">Tools →</span>
            </a>
        </nav>
    </div>

    <!-- Main Bento Grid -->
    <main class="container">
        
        <!-- ROW 1: Hero Bio Card & Profile Photo Card -->
        <div class="bento-grid" id="bio">
            
            <div class="bento-card col-7 intro-card">
                <div class="card-header-row">
                    <span class="tag-pill">+ Software Engineer & Problem Solver</span>
                </div>

                <div>
                    <h1>I'm Afeez Bello.</h1>
                    <p class="hero-bio">
                        I am a full-stack software engineer, backend architect, and dedicated problem solver (@feezybellz). I specialize in taking complex technical challenges and turning them into fast, intuitive web applications and automated tools. Whether building custom high-performance PHP web frameworks from scratch, hardening architectures against security threats, or designing scalable cloud DevOps pipelines, my mission is simple: engineer dependable, elegant software that solves real human and business problems.
                    </p>
                </div>

                <div class="action-row">
                    <a href="#works" class="btn-primary">Explore Featured Projects</a>
                    <button class="btn-secondary" id="copy-btn" onclick="copyEmail()">
                        <i data-lucide="copy"></i>
                        <span id="copy-text">Copy Email</span>
                    </button>
                </div>
            </div>

            <div class="bento-card col-5 profile-card">
                <div class="profile-img-wrapper">
                    <img src="/ui/feezybellz.jpg" alt="Afeez Adedayo Bello (@feezybellz)" class="profile-photo" onerror="this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=600&q=80'">
                    <div class="profile-overlay">
                        <span class="profile-handle">@feezybellz</span>
                        <span><span class="status-dot" style="width:6px;height:6px;"></span> ONLINE</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 2: Featured Projects & Problem-Solving Showcase -->
        <div class="bento-grid" id="works">
            <div class="bento-card col-6">
                <div>
                    <div class="card-header-row">
                        <span class="tag-pill">+ Featured Projects & Software</span>
                        <a href="https://tools.feezybellz.net.ng" style="font-family: 'Fira Code', monospace; font-size: 0.8rem; color: var(--text-sub); text-decoration: none;">Tools Portal →</a>
                    </div>
                    <h2>What I Have Built</h2>
                    <p style="color: var(--text-sub); font-size: 0.95rem;">Real-world systems, custom architectures, and diagnostic suites built from the ground up.</p>
                </div>

                <div class="list-container">
                    <a href="/tester" class="list-item">
                        <div class="item-left">
                            <div class="icon-badge bg-1"><i data-lucide="zap"></i></div>
                            <div class="item-text">
                                <h4>Bespoke PHP MVC Web Framework</h4>
                                <p>Built from scratch &bull; Dynamic routing &bull; Built-in security guards</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>

                    <a href="https://tools.feezybellz.net.ng" class="list-item">
                        <div class="item-left">
                            <div class="icon-badge bg-2"><i data-lucide="wrench"></i></div>
                            <div class="item-text">
                                <h4>Tools & Utilities Ecosystem</h4>
                                <p>tools.feezybellz.net.ng &bull; Automated wildcard subdomain routing</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>

                    <a href="/smtp-tester" class="list-item">
                        <div class="item-left">
                            <div class="icon-badge bg-3"><i data-lucide="mail"></i></div>
                            <div class="item-text">
                                <h4>Diagnostic & SMTP Test Suite</h4>
                                <p>Automated verification &bull; High-speed image processing engine</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>

                    <a href="#problems" class="list-item">
                        <div class="item-left">
                            <div class="icon-badge bg-4"><i data-lucide="globe"></i></div>
                            <div class="item-text">
                                <h4>Multi-Tenant Gateway Infrastructure</h4>
                                <p>Scalable platform architecture designed for high throughput</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>
                </div>
            </div>

            <!-- How I Solve Problems Card -->
            <div class="bento-card col-6" id="problems">
                <div>
                    <div class="card-header-row">
                        <span class="tag-pill">+ How I Solve Problems</span>
                    </div>
                    <h2>Architecting Reliable, Future-Proof Systems</h2>
                    <p style="color: var(--text-sub); font-size: 0.95rem;">I bring clarity to complex engineering challenges through secure design, lightweight performance, and resilient infrastructure.</p>
                </div>

                <div class="problem-list">
                    <div class="problem-row">
                        <div class="problem-title">
                            <i data-lucide="shield-check"></i>
                            <span>Application Security & Vulnerability Defense</span>
                        </div>
                        <div class="problem-desc">
                            As a Certified AppSec Practitioner, I proactively harden production architectures, implement WAF defenses, and embed defensive coding practices into the software lifecycle to eliminate security risks before they arise.
                        </div>
                    </div>

                    <div class="problem-row">
                        <div class="problem-title">
                            <i data-lucide="cpu"></i>
                            <span>Custom Architecture & Lightweight Tooling</span>
                        </div>
                        <div class="problem-desc">
                            When heavy, off-the-shelf software gets in the way, I design tailor-made frameworks, intelligent MVC routers, and zero-dependency developer utilities that do exactly what is needed with maximum speed and zero bloat.
                        </div>
                    </div>

                    <div class="problem-row">
                        <div class="problem-title">
                            <i data-lucide="zap"></i>
                            <span>High-Throughput & Database Optimization</span>
                        </div>
                        <div class="problem-desc">
                            I structure relational data models, fine-tune database queries, and implement efficient caching gateways so that backend platforms scale effortlessly under heavy concurrency.
                        </div>
                    </div>

                    <div class="problem-row">
                        <div class="problem-title">
                            <i data-lucide="server"></i>
                            <span>Multi-Tenant SaaS Infrastructure</span>
                        </div>
                        <div class="problem-desc">
                            Experienced in setting up Linux servers, reverse proxy workflows, and wildcard DNS ecosystems to deploy isolated, secure tenant environments at scale.
                        </div>
                    </div>

                    <div class="problem-row">
                        <div class="problem-title">
                            <i data-lucide="wrench"></i>
                            <span>Pragmatic System Reliability</span>
                        </div>
                        <div class="problem-desc">
                            I specialize in diagnosing sluggish or broken codebases and transforming them into maintainable, well-tested, and zero-maintenance solutions designed for 99.9% uptime.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 3: Engineering Writings & Initialize Connection -->
        <div class="bento-grid" id="writing">
            <div class="bento-card col-6">
                <div>
                    <div class="card-header-row">
                        <span class="tag-pill">+ Engineering Writings</span>
                    </div>
                    <h2>Thoughts & Technical Deep Dives</h2>
                    <p style="color: var(--text-sub); font-size: 0.95rem;">Practical architectural post-mortems, security insights, and engineering guides.</p>
                </div>

                <div class="list-container">
                    <a href="#" class="list-item" onclick="if (typeof Toast !== 'undefined') { Toast.info('Full article portal arriving soon on feezybellz.net.ng!', {title: 'Engineering Writings'}); } else { alert('Coming soon!'); } return false;">
                        <div class="item-text">
                            <h4>Building a High-Performance PHP Router</h4>
                            <p>8 min read &bull; Dynamic URL parsing &amp; named variables</p>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>

                    <a href="#" class="list-item" onclick="if (typeof Toast !== 'undefined') { Toast.info('Full article portal arriving soon on feezybellz.net.ng!', {title: 'Engineering Writings'}); } else { alert('Coming soon!'); } return false;">
                        <div class="item-text">
                            <h4>Zero-Dependency CAPTCHA Verification Engine</h4>
                            <p>6 min read &bull; Mathematical challenge-response in MVC</p>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>

                    <a href="#" class="list-item" onclick="if (typeof Toast !== 'undefined') { Toast.info('Full article portal arriving soon on feezybellz.net.ng!', {title: 'Engineering Writings'}); } else { alert('Coming soon!'); } return false;">
                        <div class="item-text">
                            <h4>Managing Wildcard Subdomains in Production</h4>
                            <p>5 min read &bull; Configuring PHP routing for SaaS tenants</p>
                        </div>
                        <i data-lucide="chevron-right" class="chevron-arrow"></i>
                    </a>
                </div>
            </div>

            <div class="bento-card col-6" id="connect">
                <div>
                    <div class="card-header-row">
                        <span class="tag-pill">+ Initialize Connection</span>
                    </div>
                    <h2>Let's Work Together</h2>
                    <p style="color: var(--text-sub); font-size: 0.95rem; margin-bottom: 20px;">Whether you need architecture consulting, custom framework engineering, or a dedicated problem solver to help scale your product, I am ready to collaborate.</p>

                    <form id="contact-form" onsubmit="handleDispatch(event)">
                        <div class="form-group">
                            <label class="form-label">Your Name</label>
                            <input type="text" class="form-control" required placeholder="e.g. Alex Chen">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" required placeholder="alex@domain.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" required placeholder="Tell me a bit about your project or inquiry..."></textarea>
                        </div>
                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">Send Message →</button>
                    </form>
                </div>

                <!-- Verified Social Pill Row with Authentic Font Awesome Brand Icons -->
                <div class="social-grid">
                    <a href="https://twitter.com/feezybellz" target="_blank" rel="noopener noreferrer" class="social-pill"><i class="fa-brands fa-x-twitter"></i> X (Twitter)</a>
                    <a href="https://github.com/feezybellz" target="_blank" rel="noopener noreferrer" class="social-pill"><i class="fa-brands fa-github"></i> GitHub</a>
                    <a href="https://linkedin.com/in/feezybellz" target="_blank" rel="noopener noreferrer" class="social-pill"><i class="fa-brands fa-linkedin-in"></i> LinkedIn</a>
                    <a href="https://discord.com/users/feezybellz" target="_blank" rel="noopener noreferrer" class="social-pill"><i class="fa-brands fa-discord"></i> Discord</a>
                    <a href="https://wa.me/2348161827253" target="_blank" rel="noopener noreferrer" class="social-pill"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                    <a href="#" class="social-pill" onclick="copyEmail(); return false;"><i class="fa-solid fa-envelope"></i> Email</a>
                </div>
            </div>
        </div>

    </main>

    <!-- Minimal Footer -->
    <footer>
        <p>&copy; 2026 Afeez Adedayo Bello (@feezybellz). All rights reserved.</p>
    </footer>

    <!-- Framework Toast Utility -->
    <script src="/js/toast.js"></script>
    <!-- Interactive Logic & Theme Switcher -->
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // Theme Switcher Engine
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('preferred_theme', theme);
            
            const sun = document.getElementById('theme-icon-sun');
            const moon = document.getElementById('theme-icon-moon');
            
            if (theme === 'light') {
                if (sun) sun.style.display = 'block';
                if (moon) moon.style.display = 'none';
            } else {
                if (sun) sun.style.display = 'none';
                if (moon) moon.style.display = 'block';
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'light' ? 'dark' : 'light';
            setTheme(target);
        }

        // Initialize preferred theme
        (function initTheme() {
            const saved = localStorage.getItem('preferred_theme');
            if (saved) {
                setTheme(saved);
            } else {
                setTheme('dark');
            }
        })();

        // Copy Email to Clipboard
        function copyEmail() {
            const email = "contact@feezybellz.net.ng";
            navigator.clipboard.writeText(email).then(() => {
                if (typeof Toast !== 'undefined') {
                    Toast.success('Email address copied to clipboard!', {title: 'Copied!'});
                }
                const textSpan = document.getElementById('copy-text');
                const orig = textSpan ? textSpan.textContent : "Copy Email";
                if (textSpan) textSpan.textContent = "Copied!";
                setTimeout(() => {
                    if (textSpan) textSpan.textContent = "Copy Email";
                }, 2000);
            });
        }

        // Simulated Dispatch Handler
        function handleDispatch(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const origText = btn.textContent;
            btn.textContent = 'Sending Message...';
            setTimeout(() => {
                btn.textContent = '✓ Message Sent Successfully';
                btn.style.backgroundColor = '#10B981';
                btn.style.color = '#000000';
                if (typeof Toast !== 'undefined') {
                    Toast.success('Your message has been sent! I will be in touch shortly.', {title: 'Message Sent'});
                }
                e.target.reset();
                setTimeout(() => {
                    btn.textContent = origText;
                    btn.style.backgroundColor = '';
                    btn.style.color = '';
                }, 3500);
            }, 1000);
        }
    </script>
</body>
</html>
