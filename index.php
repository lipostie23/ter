<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
$c = $config['core']; $l = $c['links'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Главная', '
    .hero {
        min-height: 100vh;
        padding: 130px 18px 60px;
        display: flex; flex-direction: column; align-items: center;
        text-align: center;
    }
    .hero-pager {
        display: inline-flex; align-items: center; gap: 12px;
        padding: 9px 16px; margin-bottom: 28px;
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--glass-border);
        border-radius: 999px;
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }
    .hero-pager .dot { width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,0.18); transition: 0.3s; }
    .hero-pager .dot.active { background: var(--accent); width: 8px; height: 8px; }
    .hero-pager .corner { font-size: 14px; color: var(--text-muted); }

    h1.hero-title {
        font-family: \'Space Grotesk\', sans-serif;
        font-weight: 600;
        font-size: clamp(40px, 7.5vw, 92px);
        line-height: 0.96;
        letter-spacing: -0.04em;
        max-width: 1100px;
        color: var(--text-primary);
    }
    .hero-title em { font-style: normal; font-weight: 500; background: linear-gradient(180deg, #15181d, #6c7480); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }

    .hero-divider { width: 100%; max-width: 880px; margin: 56px auto 0; display: flex; align-items: center; gap: 22px; }
    .hero-divider .line { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.14), transparent); }
    .hero-divider .plus {
        width: 44px; height: 44px; border-radius: 50%;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--glass-border);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: var(--text-primary);
    }

    .hero-meta { width: 100%; max-width: 880px; margin: 24px auto 0; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; text-align: center; }
    .hero-meta .meta-item { padding: 6px; font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); font-weight: 500; }

    .hero-cta { margin-top: 48px; display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }

    .stats-row { width: 100%; max-width: 1080px; margin: 56px auto 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .stat-card { padding: 24px 22px; display: flex; flex-direction: column; align-items: flex-start; gap: 12px; transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-4px); }
    .stat-card .ic { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); font-size: 20px; color: var(--text-primary); }
    .stat-card .num { font-family: \'Space Grotesk\', sans-serif; font-weight: 600; font-size: 30px; letter-spacing: -0.02em; color: var(--text-primary); }
    .stat-card .lbl { font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: var(--text-secondary); }
    .stat-card .live { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: var(--success); margin-left: auto; }
    .stat-card .live::before { content: \'\'; width: 7px; height: 7px; border-radius: 50%; background: var(--success); animation: pulse-i 1.6s ease-in-out infinite; }
    @keyframes pulse-i { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.6); opacity: 0.45; } }

    .downloads { max-width: 1080px; margin: 80px auto 0; padding: 0 18px; display: flex; flex-direction: column; gap: 24px; }
    .section-head { text-align: center; margin-bottom: 4px; }
    .section-head .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 12px; }
    .section-head h2 { font-family: \'Space Grotesk\', sans-serif; font-weight: 600; font-size: clamp(26px, 4vw, 40px); letter-spacing: -0.02em; color: var(--text-primary); }

    .download-card { padding: 26px; display: flex; align-items: center; gap: 22px; transition: transform 0.3s; }
    .download-card:hover { transform: translateY(-3px); }
    .download-card .ic-big { width: 60px; height: 60px; border-radius: 16px; background: rgba(255,255,255,0.06); border: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--text-primary); flex-shrink: 0; }
    .download-card h3 { font-family: \'Space Grotesk\', sans-serif; font-size: 21px; font-weight: 600; letter-spacing: -0.02em; margin-bottom: 6px; }
    .download-card p { color: var(--text-secondary); font-size: 13.5px; }
    .download-card .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: var(--success-soft); color: var(--success); font-size: 10.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 8px; }
    .download-card .badge.alt { background: var(--warn-soft); color: var(--warn); }
    .download-card .grow { flex: 1; min-width: 0; }
    .download-card .arrow-btn { display: inline-flex; align-items: center; justify-content: center; width: 50px; height: 50px; border-radius: 14px; background: var(--accent); color: var(--text-inverse); font-size: 22px; transition: 0.25s; flex-shrink: 0; text-decoration: none; }
    .download-card .arrow-btn:hover { transform: rotate(-12deg) scale(1.05); box-shadow: 0 12px 24px rgba(243, 245, 247, 0.18); }

    .alt-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .alt-card { padding: 24px; display: flex; flex-direction: column; gap: 12px; transition: transform 0.3s; }
    .alt-card:hover { transform: translateY(-3px); }
    .alt-card .ic { width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--text-primary); }
    .alt-card h4 { font-family: \'Space Grotesk\', sans-serif; font-size: 16px; font-weight: 600; letter-spacing: -0.01em; }
    .alt-card p { font-size: 12.5px; color: var(--text-secondary); flex: 1; }
    .alt-card a.dl { align-self: flex-start; padding: 10px 16px; border-radius: 10px; background: rgba(255,255,255,0.06); border: 1px solid var(--glass-border); color: var(--text-primary); font-size: 12.5px; font-weight: 600; text-decoration: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .alt-card a.dl:hover { background: var(--accent); color: var(--text-inverse); border-color: var(--accent); }

    .scroll-hint {
        margin-top: 56px;
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 18px;
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--glass-border);
        border-radius: 999px;
        cursor: pointer; color: var(--text-secondary); font-size: 11.5px; letter-spacing: 0.18em; text-transform: uppercase; font-weight: 500;
        transition: 0.25s;
    }
    .scroll-hint:hover { background: rgba(255,255,255,0.08); color: var(--text-primary); }
    .scroll-hint i { animation: bounce 1.8s ease-in-out infinite; }
    @keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(4px); } }

    @media (max-width: 768px) {
        .hero { padding-top: 100px; padding-bottom: 40px; }
        .hero-cta { width: 100%; }
        .hero-cta .btn { flex: 1; min-width: 140px; }
        .stats-row { grid-template-columns: 1fr; gap: 10px; }
        .download-card { flex-direction: column; align-items: stretch; gap: 16px; }
        .download-card .ic-big { width: 48px; height: 48px; font-size: 24px; }
        .download-card .arrow-btn { width: 100%; height: 46px; }
        .alt-row { grid-template-columns: 1fr; }
        .downloads { margin-top: 50px; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('main'); ?>
<?php render_tg_float(); ?>

<section class="hero">
    <div class="hero-pager reveal">
        <span class="dot active"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="corner"><i class="ph ph-square"></i></span>
    </div>

    <h1 class="hero-title reveal delay-1">Добро пожаловать<br><em>на <?= htmlspecialchars($c['title']) ?></em></h1>

    <div class="hero-divider reveal delay-2">
        <div class="line"></div>
        <div class="plus"><i class="ph ph-plus"></i></div>
        <div class="line"></div>
    </div>


    <div class="hero-cta reveal delay-4">
        <button class="btn btn-primary" type="button" onclick="scrollToStart()">
            <i class="ph ph-play-circle"></i> Начать играть
        </button>
        <a href="<?= htmlspecialchars($l['donate']) ?>" class="btn btn-ghost">
            <i class="ph ph-coin"></i> Донат
        </a>
    </div>

    <div class="stats-row">
        <div class="stat-card glass reveal delay-2">
            <div class="ic"><i class="ph ph-users-three"></i></div>
            <div style="display: flex; align-items: baseline; gap: 12px; width: 100%;">
                <span class="num" id="server-online">0</span>
                <span class="lbl" style="margin-left: 0;">/ <?= htmlspecialchars((string)$c['server']['max_online']) ?></span>
                <span class="live">live</span>
            </div>
            <span class="lbl">Игроков онлайн</span>
        </div>
        <div class="stat-card glass reveal delay-3">
            <div class="ic"><i class="ph ph-clock-counter-clockwise"></i></div>
            <span class="num">24/7</span>
            <span class="lbl">Стабильная работа</span>
        </div>
        <div class="stat-card glass reveal delay-4">
            <div class="ic"><i class="ph ph-identification-badge"></i></div>
            <span class="num"><?= htmlspecialchars($c['server']['total_accounts']) ?></span>
            <span class="lbl">Аккаунтов создано</span>
        </div>
    </div>

    <div class="scroll-hint reveal delay-5" onclick="scrollToStart()">
        <span>как начать</span><i class="ph ph-caret-down"></i>
    </div>
</section>

<section class="downloads" id="start">
    <div class="section-head">
        <div class="eyebrow reveal">Подключение</div>
        <h2 class="reveal delay-1">Два способа войти в игру</h2>
    </div>

    <div class="download-card glass reveal delay-2">
        <div class="ic-big"><i class="ph ph-rocket-launch"></i></div>
        <div class="grow">
            <span class="badge"><i class="ph-fill ph-check-circle" style="font-size:11px;"></i> Рекомендуем</span>
            <h3>Наш лаунчер</h3>
            <p>Автоматическая установка, голосовой чат и обновления одним кликом.</p>
        </div>
        <a href="<?= htmlspecialchars($l['launcher_download']) ?>" class="arrow-btn" aria-label="Скачать"><i class="ph ph-arrow-down"></i></a>
    </div>

    <div class="alt-row">
        <div class="alt-card glass reveal delay-3">
            <div class="ic"><i class="ph ph-game-controller"></i></div>
            <span class="badge alt"><i class="ph-fill ph-lightning" style="font-size:11px;"></i> Быстро</span>
            <h4>Лаунчер Radmir</h4>
            <p>Если у вас уже установлена игра.</p>
            <a href="<?= htmlspecialchars($l['radmir_download']) ?>" target="_blank" rel="noopener" class="dl">Скачать <i class="ph ph-arrow-up-right"></i></a>
        </div>
        <div class="alt-card glass reveal delay-4">
            <div class="ic"><i class="ph ph-puzzle-piece"></i></div>
            <span class="badge alt"><i class="ph-fill ph-lightning" style="font-size:11px;"></i> Плагин</span>
            <h4>Connect.asi</h4>
            <p>Плагин для подключения к серверу.</p>
            <a href="<?= htmlspecialchars($l['connect_download']) ?>" target="_blank" rel="noopener" class="dl">Скачать <i class="ph ph-arrow-up-right"></i></a>
        </div>
    </div>
</section>

<?php render_footer(); ?>

<?php render_common_js(); ?>
<script>
    function scrollToStart() { document.getElementById('start').scrollIntoView({ behavior: 'smooth' }); }

    const MAX_ONLINE = <?= (int)$c['server']['max_online'] ?>;
    const onlineEl = document.getElementById('server-online');
    let lastOnline = 0;

    function animateNumber(el, from, to) {
        const start = performance.now();
        const dur = 700;
        function tick(now) {
            const t = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(from + (to - from) * eased);
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    function updateOnline() {
        fetch('online.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                const next = data.online ?? 0;
                animateNumber(onlineEl, lastOnline, next);
                lastOnline = next;
            })
            .catch(() => {});
    }

    updateOnline();
    setInterval(updateOnline, 30000);
</script>
</body>
</html>
