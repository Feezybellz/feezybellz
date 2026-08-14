<?php
$title = $title ?? 'Tools Directory';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/js/DialogBox.js"></script>
    <style>
        /* Portfolio Theme variables */
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
            --nav-dock-bg: rgba(255, 255, 255, 0.82);
            --nav-dock-border: rgba(0, 0, 0, 0.08);
            --problem-icon-color: #4F46E5;
            --success-color: #10B981;
            --error-color: #EF4444;
            --warning-color: #F59E0B;
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
            --nav-dock-bg: rgba(16, 21, 30, 0.85);
            --nav-dock-border: rgba(255, 255, 255, 0.1);
            --problem-icon-color: #00E5FF;
        }
        
        /* Map Portfolio variables to Tool variables */
        :root, [data-theme="light"], [data-theme="dark"] {
            --bg-color: var(--bg-page);
            --surface-color: var(--bg-card);
            --border-color: var(--border-card);
            --primary-color: var(--btn-primary-bg);
            --primary-text: var(--btn-primary-text);
            --primary-hover: var(--problem-icon-color);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            padding: 100px 20px 50px;
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.3s ease;
            position: relative;
        }

        /* Floating Nav Dock */
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
        }

        .theme-toggle-btn:hover {
            background: var(--btn-secondary-bg);
            color: var(--text-main);
            transform: rotate(15deg) scale(1.08);
        }
    </style>
</head>
<body>
    <div class="nav-dock-wrapper">
        <div class="nav-dock">
            <a href="/" class="dock-item" title="Portfolio Home">
                <i data-lucide="home"></i>
            </a>
            <a href="/tools" class="dock-item" title="Tools Directory">
                <i data-lucide="grid"></i>
            </a>
            <div class="dock-divider"></div>
            <button class="theme-toggle-btn" onclick="toggleTheme()" aria-label="Toggle theme">
                <i data-lucide="sun" id="theme-icon-sun" style="display: none;"></i>
                <i data-lucide="moon" id="theme-icon-moon"></i>
            </button>
        </div>
    </div>
