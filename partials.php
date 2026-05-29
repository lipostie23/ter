<?php
if (!isset($config)) {
    require_once __DIR__ . '/config.php';
}

$c = $config['core'];
$l = $c['links'];

function render_head(string $title, string $extraCss = ''): void
{
    global $c;
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#cdd5d2">
    <title><?= htmlspecialchars($c['title']) ?> — <?= htmlspecialchars($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/duotone/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.55);
            --glass-bg-strong: rgba(255, 255, 255, 0.75);
            --glass-bg-soft: rgba(255, 255, 255, 0.32);
            --glass-border: rgba(255, 255, 255, 0.65);
            --glass-border-soft: rgba(255, 255, 255, 0.4);
            --glass-shadow: 0 12px 40px rgba(30, 40, 50, 0.07), inset 0 1px 0 rgba(255,255,255,0.7);
            --glass-shadow-lg: 0 24px 70px rgba(30, 40, 50, 0.10), inset 0 1px 0 rgba(255,255,255,0.85);
            --text-primary: #15181d;
            --text-secondary: #4a525c;
            --text-muted: #8a929c;
            --text-inverse: #ffffff;
            --accent: #15181d;
            --accent-soft: rgba(21, 24, 29, 0.06);
            --success: #1d9c5e;
            --success-soft: rgba(29, 156, 94, 0.12);
            --danger: #c93954;
            --danger-soft: rgba(201, 57, 84, 0.10);
            --warn: #b88128;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-primary);
            background:
                radial-gradient(900px 600px at 10% 0%, rgba(220, 226, 222, 0.7), transparent 60%),
                radial-gradient(800px 600px at 90% 10%, rgba(206, 215, 210, 0.55), transparent 60%),
                radial-gradient(900px 700px at 60% 100%, rgba(214, 220, 216, 0.6), transparent 60%),
                linear-gradient(135deg, #d4dcd8 0%, #c5cfca 50%, #d8e0dc 100%);
            background-attachment: fixed;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.5;
        }

        .bg-blobs {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            will-change: transform;
        }
        .blob.dark { background: radial-gradient(circle, #0e1117 0%, transparent 65%); opacity: 0.32; }
        .blob.light { background: radial-gradient(circle, rgba(255,255,255,0.95) 0%, transparent 65%); opacity: 0.85; }
        .blob-1 { width: 460px; height: 460px; top: -160px; left: -120px; animation: drift-a 22s ease-in-out infinite; }
        .blob-2 { width: 380px; height: 380px; bottom: 8%; right: 4%; animation: drift-b 26s ease-in-out infinite; }
        .blob-3 { width: 320px; height: 320px; top: 38%; right: -60px; animation: drift-c 18s ease-in-out infinite; }
        .blob-4 { width: 280px; height: 280px; bottom: -60px; left: 22%; animation: drift-d 24s ease-in-out infinite; }
        .blob-5 { width: 220px; height: 220px; top: 12%; left: 42%; animation: drift-b 30s ease-in-out infinite reverse; opacity: 0.18; }

        @keyframes drift-a { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(60px,40px) scale(1.08); } }
        @keyframes drift-b { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-50px,-30px) scale(0.94); } }
        @keyframes drift-c { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-40px,30px) scale(1.06); } }
        @keyframes drift-d { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(50px,-20px) scale(0.96); } }

        header.site-header {
            position: fixed; top: 14px; left: 50%; transform: translateX(-50%);
            width: calc(100% - 28px); max-width: 1200px;
            height: 62px;
            padding: 0 18px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            z-index: 1000;
            background: var(--glass-bg);
            backdrop-filter: blur(24px) saturate(160%);
            -webkit-backdrop-filter: blur(24px) saturate(160%);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
        }
        .site-header .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 16px; letter-spacing: -0.02em; justify-self: start; }
        .site-header .logo img { height: 28px; width: auto; display: block; }

        .nav-links {
            display: flex;
            gap: 2px;
            align-items: center;
            justify-self: center;
        }
        .nav-btn {
            position: relative;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 13px; font-weight: 500;
            padding: 9px 16px;
            border-radius: 11px;
            transition: color 0.25s, background 0.25s, transform 0.25s;
            white-space: nowrap;
        }
        .nav-btn:hover { color: var(--text-primary); background: rgba(255,255,255,0.45); }
        .nav-btn.active {
            color: var(--text-primary);
            background: rgba(255,255,255,0.7);
            font-weight: 600;
        }

        .header-right { display: flex; align-items: center; gap: 10px; justify-self: end; }
        .header-cta {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 16px;
            border-radius: 12px;
            background: var(--accent);
            color: var(--text-inverse);
            font-size: 13px; font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .header-cta:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(21, 24, 29, 0.22); }

        .burger-menu {
            display: none;
            width: 40px; height: 40px;
            border-radius: 12px;
            align-items: center; justify-content: center;
            background: rgba(255,255,255,0.45);
            border: 1px solid var(--glass-border-soft);
            color: var(--text-primary);
            font-size: 18px;
            cursor: pointer;
            transition: 0.2s;
        }
        .burger-menu:hover { background: rgba(255,255,255,0.7); }

        .tg-float {
            position: fixed; bottom: 22px; right: 22px;
            display: inline-flex; align-items: center; gap: 10px;
            padding: 12px 18px;
            background: var(--glass-bg-strong);
            backdrop-filter: blur(22px) saturate(160%);
            -webkit-backdrop-filter: blur(22px) saturate(160%);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            box-shadow: var(--glass-shadow-lg);
            color: var(--text-primary);
            font-size: 13px; font-weight: 600;
            text-decoration: none;
            z-index: 950;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .tg-float i { font-size: 20px; }
        .tg-float:hover { transform: translateY(-2px); box-shadow: 0 30px 70px rgba(30,40,50,0.18), inset 0 1px 0 rgba(255,255,255,0.85); }

        footer.site-footer {
            margin: 60px 14px 22px;
            padding: 26px 30px;
            background: var(--glass-bg);
            backdrop-filter: blur(22px) saturate(160%);
            -webkit-backdrop-filter: blur(22px) saturate(160%);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--glass-shadow);
            display: flex; flex-direction: column; align-items: center; gap: 18px;
        }
        .footer-links { display: flex; flex-wrap: wrap; gap: 22px; justify-content: center; }
        .footer-links a { color: var(--text-secondary); text-decoration: none; font-size: 13px; font-weight: 500; transition: color 0.2s; }
        .footer-links a:hover { color: var(--text-primary); }
        .socials { display: flex; gap: 10px; }
        .social-btn {
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px;
            background: rgba(255,255,255,0.5);
            border: 1px solid var(--glass-border-soft);
            color: var(--text-primary);
            font-size: 17px;
            text-decoration: none;
            transition: transform 0.25s, background 0.25s;
        }
        .social-btn:hover { transform: translateY(-2px); background: rgba(255,255,255,0.78); }
        .copyright { font-size: 12px; color: var(--text-muted); }

        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(22px) saturate(160%);
            -webkit-backdrop-filter: blur(22px) saturate(160%);
            border: 1px solid var(--glass-border);
            border-radius: 22px;
            box-shadow: var(--glass-shadow);
        }
        .glass-strong {
            background: var(--glass-bg-strong);
            backdrop-filter: blur(28px) saturate(170%);
            -webkit-backdrop-filter: blur(28px) saturate(170%);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: var(--glass-shadow-lg);
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 14px 26px;
            border: none;
            border-radius: 14px;
            font: 600 14px 'Inter', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            user-select: none;
            white-space: nowrap;
        }
        .btn-primary { background: var(--accent); color: var(--text-inverse); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 36px rgba(21, 24, 29, 0.22); }
        .btn-primary:active { transform: translateY(0); }
        .btn-ghost { background: rgba(255,255,255,0.55); color: var(--text-primary); border: 1px solid var(--glass-border-soft); }
        .btn-ghost:hover { background: rgba(255,255,255,0.78); transform: translateY(-2px); }
        .btn[disabled] { opacity: 0.55; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

        .reveal { opacity: 0; transform: translateY(18px); animation: reveal 0.7s cubic-bezier(.2,.7,.2,1) forwards; }
        .reveal.delay-1 { animation-delay: 0.06s; }
        .reveal.delay-2 { animation-delay: 0.12s; }
        .reveal.delay-3 { animation-delay: 0.18s; }
        .reveal.delay-4 { animation-delay: 0.24s; }
        .reveal.delay-5 { animation-delay: 0.30s; }
        @keyframes reveal { to { opacity: 1; transform: translateY(0); } }

        ::selection { background: rgba(21, 24, 29, 0.85); color: #fff; }

        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(21, 24, 29, 0.12); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(21, 24, 29, 0.22); }

        @media (max-width: 880px) {
            header.site-header {
                grid-template-columns: 1fr auto;
                padding: 0 14px;
                height: 58px;
            }
            .header-right { display: none; }
            .burger-menu { display: inline-flex; }
            .nav-links {
                position: absolute; top: calc(100% + 10px); left: 0; right: 0;
                flex-direction: column; align-items: stretch; gap: 4px;
                padding: 10px;
                background: var(--glass-bg-strong);
                backdrop-filter: blur(24px) saturate(170%);
                -webkit-backdrop-filter: blur(24px) saturate(170%);
                border: 1px solid var(--glass-border);
                border-radius: 18px;
                box-shadow: var(--glass-shadow-lg);
                opacity: 0; pointer-events: none;
                transform: translateY(-6px);
                transition: 0.25s;
            }
            .nav-links.open { opacity: 1; pointer-events: auto; transform: translateY(0); }
            .nav-btn { padding: 14px 16px; text-align: left; }
            .tg-float { bottom: 16px; right: 16px; padding: 12px; }
            .tg-float span { display: none; }
            footer.site-footer { padding: 22px 18px; margin: 48px 12px 18px; }
        }
    </style>
    <?php if ($extraCss): ?>
    <style><?= $extraCss ?></style>
    <?php endif; ?>
    <?php
}

function render_bg(): void
{
    ?>
    <div class="bg-blobs" aria-hidden="true">
        <div class="blob dark blob-1"></div>
        <div class="blob light blob-2"></div>
        <div class="blob dark blob-3"></div>
        <div class="blob light blob-4"></div>
        <div class="blob dark blob-5"></div>
    </div>
    <?php
}

function render_header(string $active = ''): void
{
    global $c, $l;
    $items = [
        'main'     => ['Главная',  $l['main']],
        'forum'    => ['Форум',    $l['forum']],
        'donate'   => ['Донат',    $l['donate']],
        'roulette' => ['Рулетка',  $l['roulette']],
        'forbes'   => ['Forbes',   $l['forbes']],
    ];

    ?>
    <header class="site-header">
        <a href="<?= htmlspecialchars($l['main']) ?>" class="logo">
            <img src="logo.png" alt="" onerror="this.style.display='none'">
            <span><?= htmlspecialchars($c['title']) ?></span>
        </a>
        <nav class="nav-links" id="navLinks">
            <?php foreach ($items as $key => [$label, $href]): ?>
                <?php $extra = $key === 'forum' ? ' target="_blank" rel="noopener"' : ''; ?>
                <a href="<?= htmlspecialchars($href) ?>"<?= $extra ?> class="nav-btn<?= $active === $key ? ' active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <a href="<?= htmlspecialchars($l['donate']) ?>" class="header-cta">
                <i class="ph ph-coin"></i> Пополнить
            </a>
        </div>
        <button class="burger-menu" type="button" aria-label="Меню" onclick="document.getElementById('navLinks').classList.toggle('open')"><i class="ph ph-list"></i></button>
    </header>
    <?php
}

function render_tg_float(): void
{
    global $l;
    ?>
    <a href="<?= htmlspecialchars($l['telegram']) ?>" target="_blank" rel="noopener" class="tg-float" aria-label="Telegram">
        <i class="ph ph-telegram-logo"></i>
        <span>Telegram-канал</span>
    </a>
    <?php
}

function render_footer(): void
{
    global $c, $l;
    ?>
    <footer class="site-footer">
        <div class="footer-links">
            <a href="<?= htmlspecialchars($l['vk_support']) ?>" target="_blank" rel="noopener">Поддержка</a>
            <a href="<?= htmlspecialchars($l['privacy']) ?>">Политика конфиденциальности</a>
            <a href="<?= htmlspecialchars($l['terms']) ?>">Пользовательское соглашение</a>
        </div>
        <div class="socials">
            <a href="<?= htmlspecialchars($l['vk_main']) ?>" target="_blank" rel="noopener" class="social-btn" aria-label="VK"><i class="ph ph-circles-three"></i></a>
            <a href="<?= htmlspecialchars($l['telegram']) ?>" target="_blank" rel="noopener" class="social-btn" aria-label="Telegram"><i class="ph ph-telegram-logo"></i></a>
        </div>
        <p class="copyright">© <?= htmlspecialchars((string)$c['copyright_year']) ?> <?= htmlspecialchars($c['title']) ?>. Все права защищены.</p>
    </footer>
    <?php
}

function render_common_js(): void
{
    ?>
    <script>
        document.addEventListener('click', (e) => {
            const nav = document.getElementById('navLinks');
            const burger = document.querySelector('.burger-menu');
            if (!nav || !burger) return;
            if (window.innerWidth > 880) return;
            if (!nav.contains(e.target) && !burger.contains(e.target)) {
                nav.classList.remove('open');
            }
        });
    </script>
    <?php
}
