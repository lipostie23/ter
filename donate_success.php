<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
session_start();

$c = $config['core']; $l = $c['links'];

$payment = $_SESSION['pending_payment'] ?? null;
unset($_SESSION['pending_payment']);

$displayOrder = $payment['order_id'] ?? ('TX-' . strtoupper(bin2hex(random_bytes(3))));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Чек оплаты', '
    .success-page {
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
        from { opacity: 0; transform: translateY(40px) rotate(-1.5deg); }
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
        border: 2px solid var(--success);
        color: var(--success);
        border-radius: 6px;
        font-family: \'Space Grotesk\', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        transform: rotate(8deg);
        opacity: 0;
        animation: stamp-in 0.5s 0.6s cubic-bezier(.34,1.56,.64,1) forwards;
    }
    @keyframes stamp-in {
        from { opacity: 0; transform: rotate(20deg) scale(2); }
        to { opacity: 0.9; transform: rotate(8deg) scale(1); }
    }

    .check-mark {
        width: 64px; height: 64px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: linear-gradient(140deg, #1d9c5e, #15814b);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 12px 30px rgba(29, 156, 94, 0.3);
        animation: pop-in 0.55s 0.2s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes pop-in { 0% { transform: scale(0); } 100% { transform: scale(1); } }

    .check-svg { width: 30px; height: 30px; }
    .check-svg path {
        fill: none; stroke: #fff; stroke-width: 6; stroke-linecap: round; stroke-linejoin: round;
        stroke-dasharray: 80; stroke-dashoffset: 80;
        animation: draw-check 0.5s 0.55s ease-out forwards;
    }
    @keyframes draw-check { to { stroke-dashoffset: 0; } }

    .r-head { text-align: center; padding-bottom: 18px; border-bottom: 1.5px dashed rgba(0,0,0,0.13); }
    .r-head .logo-row { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 6px; padding: 6px 14px; border-radius: 999px; background: rgba(0,0,0,0.04); font-family: \'Space Grotesk\', sans-serif; font-weight: 700; font-size: 13px; color: var(--text-primary); }
    .r-head h2 { font-family: \'Space Grotesk\', sans-serif; font-size: 22px; font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin-bottom: 4px; }
    .r-head .order { display: inline-block; font-family: \'JetBrains Mono\', monospace; font-size: 11px; color: var(--text-muted); letter-spacing: 0.05em; }

    .r-table { padding: 18px 0 6px; }
    .r-row { display: flex; justify-content: space-between; align-items: baseline; padding: 6px 0; font-size: 13px; gap: 12px; }
    .r-row .k { color: var(--text-secondary); font-size: 12.5px; }
    .r-row .v { color: var(--text-primary); font-weight: 600; font-family: \'JetBrains Mono\', monospace; font-size: 12.5px; text-align: right; word-break: break-all; }

    .r-divider { padding: 12px 0; border-top: 1.5px dashed rgba(0,0,0,0.13); border-bottom: 1.5px dashed rgba(0,0,0,0.13); margin: 14px 0; }
    .r-divider .total-row { display: flex; justify-content: space-between; align-items: baseline; }
    .r-divider .total-row .k { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
    .r-divider .total-row .v { font-family: \'Space Grotesk\', sans-serif; font-size: 26px; font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); }
    .r-divider .coins-row { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; }
    .r-divider .coins-row .k { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
    .r-divider .coins-row .v {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 11px; border-radius: 999px;
        background: var(--success-soft); color: var(--success);
        font-size: 13px; font-weight: 700;
    }

    .r-thanks { text-align: center; padding: 14px 0 18px; }
    .r-thanks .big { font-family: \'Space Grotesk\', sans-serif; font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
    .r-thanks .sub { font-size: 12px; color: var(--text-muted); }

    .r-foot { text-align: center; padding-top: 12px; border-top: 1px dashed rgba(0,0,0,0.10); }
    .r-foot .barcode { display: flex; justify-content: center; gap: 1.5px; margin-bottom: 8px; }
    .r-foot .barcode span { display: block; height: 32px; background: var(--text-primary); }
    .r-foot .barcode-label { font-family: \'JetBrains Mono\', monospace; font-size: 10.5px; color: var(--text-muted); letter-spacing: 0.18em; }

    .r-actions { margin-top: 22px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; animation: slide-up 0.5s 0.7s both; }
    @keyframes slide-up { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    .r-actions a { padding: 14px; }

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

<section class="success-page">
    <div class="receipt-wrap">
        <div class="receipt">
            <div class="receipt-inner">
                <div class="stamp">Оплачено</div>

                <div class="check-mark">
                    <svg class="check-svg" viewBox="0 0 52 52"><path d="M14 27 L23 36 L40 17"></path></svg>
                </div>

                <div class="r-head">
                    <div class="logo-row"><i class="ph-fill ph-coin"></i> <?= htmlspecialchars($c['title']) ?></div>
                    <h2>Оплата прошла</h2>
                    <span class="order"><?= htmlspecialchars($displayOrder) ?> · <?= htmlspecialchars(date('d.m.Y H:i')) ?></span>
                </div>

                <div class="r-table">
                    <?php if ($payment): ?>
                        <div class="r-row"><span class="k">Игрок</span><span class="v"><?= htmlspecialchars($payment['nickname']) ?></span></div>
                        <div class="r-row"><span class="k">Сервер</span><span class="v"><?= htmlspecialchars($payment['server']) ?></span></div>
                        <div class="r-row"><span class="k">Способ</span><span class="v"><?= htmlspecialchars(strtoupper($payment['method'] ?? 'sbp')) ?></span></div>
                    <?php else: ?>
                        <div class="r-row"><span class="k">Статус</span><span class="v">Подтверждён</span></div>
                        <div class="r-row"><span class="k">Время</span><span class="v"><?= htmlspecialchars(date('H:i')) ?></span></div>
                    <?php endif; ?>
                </div>

                <div class="r-divider">
                    <div class="total-row">
                        <span class="k">Сумма</span>
                        <span class="v"><?= $payment ? number_format((float)$payment['amount'], 0, '.', ' ') : '—' ?> ₽</span>
                    </div>
                    <?php if ($payment): ?>
                    <div class="coins-row">
                        <span class="k">Зачислено</span>
                        <span class="v"><i class="ph-fill ph-coin"></i> +<?= number_format((float)$payment['coins'], 0, '.', ' ') ?> монет</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="r-thanks">
                    <div class="big">Спасибо за поддержку!</div>
                    <div class="sub">Монеты зачисляются автоматически в течение нескольких минут</div>
                </div>

                <div class="r-foot">
                    <div class="barcode" id="barcode"></div>
                    <div class="barcode-label">PAID · <?= htmlspecialchars($displayOrder) ?></div>
                </div>
            </div>
        </div>

        <div class="r-actions">
            <a href="<?= htmlspecialchars($l['main']) ?>" class="btn btn-ghost"><i class="ph ph-house"></i> На главную</a>
            <a href="<?= htmlspecialchars($l['donate']) ?>" class="btn btn-primary"><i class="ph ph-arrow-clockwise"></i> Ещё</a>
        </div>
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
