<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
$c = $config['core']; $l = $c['links'];

$reason = $_GET['reason'] ?? 'cancelled';
$messages = [
    'cancelled' => 'Платёж отменён. Деньги не списаны',
    'rejected'  => 'Банк отклонил операцию. Попробуйте другой способ',
    'timeout'   => 'Время ожидания истекло. Попробуйте ещё раз',
    'error'     => 'Ошибка платёжной системы. Попробуйте позже',
];
$msg = $messages[$reason] ?? $messages['cancelled'];
$displayOrder = 'TX-' . strtoupper(bin2hex(random_bytes(3)));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Платёж не прошёл', '
    .fail-page {
        min-height: 100vh;
        padding: 110px 14px 30px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .receipt-wrap { width: 100%; max-width: 420px; }

    .receipt {
        position: relative;
        background: #fafbfa;
        padding: 0;
        box-shadow: 0 30px 80px rgba(20, 30, 40, 0.18), 0 8px 24px rgba(20,30,40,0.08);
        font-family: \'Inter\', sans-serif;
        animation: paper-in 0.7s cubic-bezier(.2,.7,.2,1) both;
    }
    @keyframes paper-in {
        from { opacity: 0; transform: translateY(40px) rotate(1.5deg); }
        to { opacity: 1; transform: translateY(0) rotate(0); }
    }
    .receipt::before, .receipt::after {
        content: \'\'; position: absolute; left: 0; right: 0; height: 14px;
        background-image: radial-gradient(circle at 8px 7px, transparent 6px, #fafbfa 7px);
        background-size: 16px 14px;
        background-repeat: repeat-x;
    }
    .receipt::before { top: -7px; background-position: 0 -7px; }
    .receipt::after { bottom: -7px; background-position: 0 7px; transform: rotate(180deg); }

    .receipt-inner { padding: 30px 28px 22px; }

    .stamp {
        position: absolute; top: 20px; right: 18px;
        padding: 6px 12px;
        border: 2px solid var(--danger);
        color: var(--danger);
        border-radius: 6px;
        font-family: \'Space Grotesk\', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        transform: rotate(-8deg);
        opacity: 0;
        animation: stamp-in 0.5s 0.6s cubic-bezier(.34,1.56,.64,1) forwards;
    }
    @keyframes stamp-in {
        from { opacity: 0; transform: rotate(-20deg) scale(2); }
        to { opacity: 0.92; transform: rotate(-8deg) scale(1); }
    }

    .x-mark {
        width: 64px; height: 64px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: linear-gradient(140deg, #d65371, #b62b48);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 12px 30px rgba(201, 57, 84, 0.3);
        animation: pop-in 0.55s 0.2s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes pop-in { 0% { transform: scale(0); } 100% { transform: scale(1); } }

    .x-svg { width: 30px; height: 30px; }
    .x-svg line {
        stroke: #fff; stroke-width: 6; stroke-linecap: round;
        stroke-dasharray: 50; stroke-dashoffset: 50;
        animation: draw-x 0.4s 0.55s ease-out forwards;
    }
    .x-svg line:nth-child(2) { animation-delay: 0.75s; }
    @keyframes draw-x { to { stroke-dashoffset: 0; } }

    .r-head { text-align: center; padding-bottom: 18px; border-bottom: 1.5px dashed rgba(0,0,0,0.13); }
    .r-head .logo-row { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 6px; padding: 6px 14px; border-radius: 999px; background: rgba(0,0,0,0.04); font-family: \'Space Grotesk\', sans-serif; font-weight: 700; font-size: 13px; color: var(--text-primary); }
    .r-head h2 { font-family: \'Space Grotesk\', sans-serif; font-size: 22px; font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin-bottom: 4px; }
    .r-head .order { display: inline-block; font-family: \'JetBrains Mono\', monospace; font-size: 11px; color: var(--text-muted); letter-spacing: 0.05em; }

    .r-message { padding: 18px 0; border-bottom: 1.5px dashed rgba(0,0,0,0.13); text-align: center; }
    .r-message .lbl { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .r-message .txt { font-size: 14px; color: var(--text-primary); font-weight: 500; line-height: 1.55; }

    .r-info { padding: 16px 0; }
    .r-row { display: flex; justify-content: space-between; align-items: baseline; padding: 6px 0; gap: 12px; }
    .r-row .k { color: var(--text-secondary); font-size: 12.5px; }
    .r-row .v { color: var(--text-primary); font-weight: 600; font-family: \'JetBrains Mono\', monospace; font-size: 12.5px; text-align: right; }
    .r-row .v.danger { color: var(--danger); }

    .r-foot { text-align: center; padding-top: 14px; border-top: 1px dashed rgba(0,0,0,0.10); }
    .r-foot .barcode { display: flex; justify-content: center; gap: 1.5px; margin-bottom: 8px; opacity: 0.5; }
    .r-foot .barcode span { display: block; height: 32px; background: var(--text-primary); }
    .r-foot .barcode-label { font-family: \'JetBrains Mono\', monospace; font-size: 10.5px; color: var(--text-muted); letter-spacing: 0.18em; }

    .r-actions { margin-top: 22px; display: grid; grid-template-columns: 1fr 1.4fr; gap: 10px; animation: slide-up 0.5s 0.7s both; }
    @keyframes slide-up { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    .r-actions a { padding: 14px; }

    .help-line { margin-top: 16px; text-align: center; font-size: 12px; color: var(--text-secondary); }
    .help-line a { color: var(--text-primary); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }

    @media (max-width: 480px) {
        .receipt-inner { padding: 26px 22px 20px; }
        .stamp { top: 16px; right: 14px; padding: 5px 10px; font-size: 10px; }
        .r-actions { grid-template-columns: 1fr; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('donate'); ?>
<?php render_tg_float(); ?>

<section class="fail-page">
    <div class="receipt-wrap">
        <div class="receipt">
            <div class="receipt-inner">
                <div class="stamp">Отменено</div>

                <div class="x-mark">
                    <svg class="x-svg" viewBox="0 0 52 52">
                        <line x1="16" y1="16" x2="36" y2="36"></line>
                        <line x1="36" y1="16" x2="16" y2="36"></line>
                    </svg>
                </div>

                <div class="r-head">
                    <div class="logo-row"><i class="ph-fill ph-coin"></i> <?= htmlspecialchars($c['title']) ?></div>
                    <h2>Платёж не прошёл</h2>
                    <span class="order"><?= htmlspecialchars($displayOrder) ?> · <?= htmlspecialchars(date('d.m.Y H:i')) ?></span>
                </div>

                <div class="r-message">
                    <div class="lbl">Причина</div>
                    <div class="txt"><?= htmlspecialchars($msg) ?></div>
                </div>

                <div class="r-info">
                    <div class="r-row"><span class="k">Статус</span><span class="v danger">Не выполнен</span></div>
                    <div class="r-row"><span class="k">Списание</span><span class="v">Не произошло</span></div>
                    <div class="r-row"><span class="k">Действие</span><span class="v">Возврат не требуется</span></div>
                </div>

                <div class="r-foot">
                    <div class="barcode" id="barcode"></div>
                    <div class="barcode-label">VOID · <?= htmlspecialchars($displayOrder) ?></div>
                </div>
            </div>
        </div>

        <div class="r-actions">
            <a href="<?= htmlspecialchars($l['main']) ?>" class="btn btn-ghost"><i class="ph ph-house"></i> Главная</a>
            <a href="<?= htmlspecialchars($l['donate']) ?>" class="btn btn-primary"><i class="ph ph-arrow-counter-clockwise"></i> Попробовать снова</a>
        </div>

        <p class="help-line">Если проблема повторяется — <a href="<?= htmlspecialchars($l['vk_support']) ?>" target="_blank" rel="noopener">напишите в поддержку</a></p>
    </div>
</section>

<?php render_footer(); ?>

<?php render_common_js(); ?>
<script>
    const barcode = document.getElementById('barcode');
    const seed = Math.random();
    const widths = [];
    for (let i = 0; i < 56; i++) widths.push(1 + Math.floor(((seed * 9301 + i * 49297) % 233280) / 233280 * 4));
    barcode.innerHTML = widths.map(w => `<span style="width:${w}px"></span>`).join('');
</script>
</body>
</html>
