<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';

/* =========================================================================
 *  JSON API: /roulette.php?result=ORDER_ID
 *  Страница опрашивает этот эндпоинт после возврата с Platega,
 *  пока вебхук не сохранит результат в cache/roulette_results/{order_id}.json
 * ========================================================================= */
if (isset($_GET['result'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $orderId = preg_replace('/[^A-Za-z0-9_]/', '', (string) $_GET['result']);
    $file = __DIR__ . '/cache/roulette_results/' . $orderId . '.json';

    if ($orderId !== '' && is_file($file)) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (is_array($data)) {
            echo json_encode(['ok' => true, 'status' => 'ready', 'data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    echo json_encode(['ok' => true, 'status' => 'pending'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================================
 *  HTML-страница
 * ========================================================================= */
$c  = $config['core'];
$l  = $c['links'];
$rc = $config['roulette'];

$prizes = $rc['prizes'] ?? [];
$price  = (int) ($rc['price'] ?? 150);

$totalWeight = 0;
foreach ($prizes as $p) {
    $totalWeight += max(0, (int) ($p['weight'] ?? 0));
}

$spinOrderId = isset($_GET['spin']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $_GET['spin']) : '';
$paymentError = isset($_GET['error']);

/* Способы оплаты — синхронизировано с donate.php */
$methods = [
    ['code' => 'sbp',     'name' => 'СБП',                'sub' => 'ОПЛАТА ПО QR-КОДУ',           'icon' => 'ph-qr-code',                'available' => true,  'fee' => 'Без комиссии · Мгновенно'],
    ['code' => 'card',    'name' => 'Банковская карта',   'sub' => 'Visa · MIR · MasterCard',     'icon' => 'ph-credit-card',            'available' => false, 'fee' => 'soon'],
    ['code' => 'card_uz', 'name' => 'Иностранные карты',  'sub' => 'UZ · KZ · BY',                'icon' => 'ph-globe-hemisphere-west',  'available' => false, 'fee' => 'soon'],
    ['code' => 'usdt',    'name' => 'USDT TRC20',         'sub' => 'Криптовалюта Tether',         'icon' => 'ph-coins',                  'available' => false, 'fee' => 'soon'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Рулетка', '
    .roulette-page { max-width: 920px; margin: 0 auto; padding: 110px 14px 30px; }

    .r-hero { text-align: center; margin-bottom: 26px; padding: 30px 26px; }
    .r-hero .ic-big { width: 60px; height: 60px; margin: 0 auto 14px; border-radius: 16px; background: var(--accent); color: var(--text-inverse); display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .r-hero .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .r-hero h1 { font-family: \'Space Grotesk\', sans-serif; font-size: clamp(28px, 5vw, 44px); font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin: 12px 0 8px; }
    .r-hero p { color: var(--text-secondary); font-size: 14px; line-height: 1.55; max-width: 480px; margin: 0 auto; }

    /* ---------- КОЛЕСО ---------- */
    .wheel-card { padding: 22px 18px 28px; }
    .wheel {
        position: relative;
        height: 130px;
        overflow: hidden;
        border-radius: 14px;
        background: rgba(255,255,255,0.45);
        border: 1px solid var(--glass-border-soft);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
        -webkit-mask-image: linear-gradient(to right, transparent, black 9%, black 91%, transparent);
                mask-image: linear-gradient(to right, transparent, black 9%, black 91%, transparent);
    }
    .strip {
        display: flex;
        height: 100%;
        will-change: transform;
        transform: translate3d(0,0,0);
    }
    .cell {
        flex: 0 0 130px;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background: rgba(255,255,255,0.55);
        border-right: 1px solid rgba(20,30,40,0.06);
    }
    .cell .ci { font-size: 26px; color: var(--text-secondary); }
    .cell .ct { font-size: 12px; font-weight: 600; color: var(--text-primary); letter-spacing: -0.01em; text-align: center; padding: 0 6px; }

    .cell.tier-rare      { background: linear-gradient(180deg, rgba(70,140,210,0.20), rgba(255,255,255,0.5)); }
    .cell.tier-rare      .ci { color: #2c5fcc; }
    .cell.tier-epic      { background: linear-gradient(180deg, rgba(160,90,200,0.22), rgba(255,255,255,0.5)); }
    .cell.tier-epic      .ci { color: #7434c3; }
    .cell.tier-legendary { background: linear-gradient(180deg, rgba(220,170,40,0.32), rgba(255,255,255,0.5)); }
    .cell.tier-legendary .ci { color: #c89417; }

    .pointer {
        position: absolute; left: 50%; transform: translateX(-50%);
        width: 0; height: 0; z-index: 5; pointer-events: none;
        filter: drop-shadow(0 2px 4px rgba(20,30,40,0.25));
    }
    .pointer-top    { top: -1px;  border-left: 9px solid transparent; border-right: 9px solid transparent; border-top: 12px solid var(--accent); }
    .pointer-bottom { bottom: -1px; border-left: 9px solid transparent; border-right: 9px solid transparent; border-bottom: 12px solid var(--accent); }

    .wheel-status {
        text-align: center; margin-top: 14px;
        font-size: 12px; color: var(--text-muted);
        letter-spacing: 0.04em;
        min-height: 16px;
    }
    .wheel-status.busy { color: var(--text-secondary); font-weight: 500; }

    /* ---------- СЕТКА ПРИЗОВ ---------- */
    .prize-eyebrow {
        font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase;
        color: var(--text-secondary); margin: 30px 4px 12px; text-align: center;
    }
    .prize-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
    }
    .prize-card {
        padding: 18px 10px;
        text-align: center;
        transition: transform 0.25s ease;
    }
    .prize-card:hover { transform: translateY(-2px); }
    .prize-card .pic {
        width: 42px; height: 42px; margin: 0 auto 8px;
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        background: var(--accent-soft); color: var(--text-primary);
    }
    .prize-card.tier-rare      .pic { background: rgba(70,140,210,0.14);  color: #2c5fcc; }
    .prize-card.tier-epic      .pic { background: rgba(160,90,200,0.16);  color: #7434c3; }
    .prize-card.tier-legendary .pic { background: rgba(220,170,40,0.20);  color: #c89417; }
    .prize-card .pl { font-size: 13px; font-weight: 600; color: var(--text-primary); letter-spacing: -0.01em; }
    .prize-card .pc { font-size: 11px; color: var(--text-muted); margin-top: 4px; letter-spacing: 0.04em; font-family: \'JetBrains Mono\', monospace; }

    /* ---------- ФОРМА ---------- */
    .spin-form { margin-top: 22px; padding: 22px; }
    .spin-form .form-eyebrow {
        font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase;
        color: var(--text-secondary); margin-bottom: 12px; text-align: center;
    }
    .nick-wrap {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px;
        background: rgba(255,255,255,0.5);
        border: 1px solid var(--glass-border-soft);
        border-radius: 12px;
        margin-bottom: 14px;
    }
    .nick-wrap i { font-size: 18px; color: var(--text-secondary); flex-shrink: 0; }
    .nick-wrap input {
        flex: 1; min-width: 0;
        background: transparent; border: none; outline: none;
        font: 600 16px \'Inter\', sans-serif;
        color: var(--text-primary);
    }
    .nick-wrap input::placeholder { color: var(--text-muted); font-weight: 500; }

    .spin-btn {
        width: 100%; padding: 16px;
        border: none; border-radius: 14px;
        background: var(--accent);
        color: var(--text-inverse);
        font: 600 14px \'Inter\', sans-serif;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; gap: 10px;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .spin-btn:hover:not([disabled]) { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(20,30,40,0.22); }
    .spin-btn[disabled] { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }
    .spin-btn .price { background: rgba(255,255,255,0.18); padding: 4px 10px; border-radius: 999px; font-size: 12.5px; font-family: \'Space Grotesk\', sans-serif; }

    .error-box {
        display: none; align-items: center; gap: 10px;
        padding: 12px 14px;
        background: var(--danger-soft); border: 1px solid rgba(201, 57, 84, 0.25);
        border-radius: 10px; color: var(--danger); font-size: 12.5px; font-weight: 500;
        margin-top: 12px;
    }
    .error-box.show { display: flex; animation: shake 0.45s ease; }
    .error-box i { font-size: 16px; }
    @keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }

    /* ---------- MODAL: ОПЛАТА ---------- */
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(15, 20, 25, 0.45);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
        z-index: 2000; opacity: 0; pointer-events: none; transition: opacity 0.3s;
        display: flex; align-items: flex-end; justify-content: center;
        padding: 20px;
    }
    .modal-backdrop.open { opacity: 1; pointer-events: auto; }
    .modal-card {
        width: 100%; max-width: 460px;
        background: #fbfcfa; border-radius: 28px;
        padding: 26px 24px;
        box-shadow: 0 -30px 80px rgba(20,30,40,0.25);
        transform: translateY(40px);
        transition: transform 0.4s cubic-bezier(.2,.7,.2,1);
        max-height: 92vh; overflow-y: auto;
    }
    .modal-backdrop.open .modal-card { transform: translateY(0); }

    .modal-grip { width: 40px; height: 4px; background: rgba(0,0,0,0.12); border-radius: 2px; margin: 0 auto 16px; }
    .modal-head { text-align: center; margin-bottom: 20px; position: relative; }
    .modal-head .back-btn { position: absolute; left: 0; top: 0; width: 36px; height: 36px; border-radius: 50%; border: none; background: rgba(0,0,0,0.04); color: var(--text-primary); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; transition: 0.2s; }
    .modal-head .back-btn:hover { background: rgba(0,0,0,0.08); }
    .modal-head h3 { font-family: \'Space Grotesk\', sans-serif; font-size: 22px; font-weight: 600; letter-spacing: -0.02em; color: var(--text-primary); }
    .modal-head p { font-size: 13px; color: var(--text-secondary); margin-top: 6px; max-width: 280px; margin-left: auto; margin-right: auto; }

    .modal-cat { font-size: 11px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase; color: var(--text-muted); margin: 18px 0 10px; }
    .modal-cat:first-of-type { margin-top: 0; }

    .method-tile {
        position: relative; width: 100%;
        padding: 14px 16px;
        background: rgba(0,0,0,0.03);
        border: 1.5px solid transparent;
        border-radius: 16px;
        cursor: pointer;
        transition: 0.22s;
        display: flex; align-items: center; gap: 14px;
        font-family: inherit; color: inherit; text-align: left;
        margin-bottom: 8px;
    }
    .method-tile:hover:not(.disabled) { background: rgba(0,0,0,0.06); }
    .method-tile.selected { background: #fff; border-color: var(--text-primary); box-shadow: 0 6px 20px rgba(20,30,40,0.10); }
    .method-tile.disabled { opacity: 0.45; cursor: not-allowed; }
    .method-tile .ic-box { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(140deg, #25282d, #15181d); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
    .method-tile .ic-box.sbp { background: linear-gradient(140deg, #1d9c5e, #15814b); }
    .method-tile .info { flex: 1; min-width: 0; }
    .method-tile .name { font-size: 14.5px; font-weight: 600; color: var(--text-primary); }
    .method-tile .sub { font-size: 11.5px; color: var(--text-muted); }
    .method-tile .fee { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
    .method-tile .radio { width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid rgba(0,0,0,0.16); display: flex; align-items: center; justify-content: center; transition: 0.2s; flex-shrink: 0; }
    .method-tile .radio::after { content: \'\'; width: 10px; height: 10px; border-radius: 50%; background: var(--text-primary); transform: scale(0); transition: 0.2s; }
    .method-tile.selected .radio { border-color: var(--text-primary); }
    .method-tile.selected .radio::after { transform: scale(1); }

    .modal-actions { margin-top: 16px; display: grid; grid-template-columns: 1fr 1.4fr; gap: 10px; }
    .modal-cancel { padding: 14px; border-radius: 14px; background: rgba(0,0,0,0.05); color: var(--text-primary); border: none; font: 600 13.5px \'Inter\', sans-serif; cursor: pointer; transition: 0.2s; }
    .modal-cancel:hover { background: rgba(0,0,0,0.1); }
    .modal-confirm { padding: 14px; border-radius: 14px; background: var(--text-primary); color: #fff; border: none; font: 600 13.5px \'Inter\', sans-serif; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
    .modal-confirm:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(20,30,40,0.22); }
    .modal-confirm[disabled] { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
    .modal-confirm .spinner-sm { display: none; animation: spin 0.9s linear infinite; }
    .modal-confirm.loading .ic-go { display: none; }
    .modal-confirm.loading .spinner-sm { display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .secure-row { margin-top: 14px; display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 10px; background: rgba(29,156,94,0.08); }
    .secure-row i { color: var(--success); font-size: 16px; }
    .secure-row .t { font-size: 11.5px; color: var(--text-secondary); line-height: 1.4; }
    .secure-row .t b { color: var(--text-primary); font-weight: 600; }

    /* ---------- MODAL: РЕЗУЛЬТАТ ---------- */
    .result-modal {
        position: fixed; inset: 0;
        background: rgba(15,20,25,0.55);
        backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        z-index: 2100;
        opacity: 0; pointer-events: none;
        display: flex; align-items: center; justify-content: center;
        transition: opacity 0.35s;
        padding: 20px;
    }
    .result-modal.open { opacity: 1; pointer-events: auto; }
    .result-card {
        background: #fbfcfa;
        border-radius: 26px;
        padding: 36px 28px 28px;
        max-width: 380px; width: 100%;
        text-align: center;
        transform: scale(0.85);
        transition: transform 0.45s cubic-bezier(.2,.8,.2,1);
        box-shadow: 0 30px 80px rgba(20,30,40,0.35);
    }
    .result-modal.open .result-card { transform: scale(1); }
    .result-card .ic-big { width: 76px; height: 76px; border-radius: 24px; margin: 0 auto 18px; display: flex; align-items: center; justify-content: center; font-size: 40px; background: var(--accent-soft); color: var(--text-primary); }
    .result-card.tier-rare      .ic-big { background: rgba(70,140,210,0.14);  color: #2c5fcc; }
    .result-card.tier-epic      .ic-big { background: rgba(160,90,200,0.16);  color: #7434c3; }
    .result-card.tier-legendary .ic-big { background: rgba(220,170,40,0.22);  color: #c89417; }
    .result-card .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .result-card h3 { font-family: \'Space Grotesk\', sans-serif; font-size: 26px; font-weight: 600; letter-spacing: -0.025em; margin: 8px 0; color: var(--text-primary); }
    .result-card .prize-name { font-size: 18px; color: var(--text-primary); font-weight: 600; margin-bottom: 4px; }
    .result-card .prize-note { font-size: 12.5px; color: var(--text-muted); margin-bottom: 22px; }
    .result-card .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .result-card .actions .btn { padding: 12px; font-size: 13px; }

    @media (max-width: 760px) {
        .prize-grid { grid-template-columns: repeat(2, 1fr); }
        .cell { flex: 0 0 110px; }
        .modal-actions { grid-template-columns: 1fr; }
        .modal-actions .modal-cancel { order: 2; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('roulette'); ?>
<?php render_tg_float(); ?>

<section class="roulette-page">
    <div class="r-hero glass-strong reveal">
        <div class="ic-big"><i class="ph-fill ph-game-controller"></i></div>
        <div class="eyebrow">Рулетка</div>
        <h1>Крути колесо удачи</h1>
        <p>Один прокрут — <b><?= $price ?> ₽</b>. Награда зачисляется автоматически на твой аккаунт сразу после оплаты.</p>
    </div>

    <div class="wheel-card glass reveal delay-1">
        <div class="wheel">
            <div class="pointer pointer-top"></div>
            <div class="pointer pointer-bottom"></div>
            <div class="strip" id="strip"></div>
        </div>
        <div class="wheel-status" id="wheelStatus"></div>
    </div>

    <div class="prize-eyebrow reveal delay-2">что можно выиграть</div>
    <div class="prize-grid reveal delay-2">
        <?php foreach ($prizes as $p): ?>
            <?php $chance = $totalWeight > 0 ? round($p['weight'] / $totalWeight * 100, 1) : 0; ?>
            <div class="prize-card glass tier-<?= htmlspecialchars($p['tier']) ?>">
                <div class="pic"><i class="ph-fill <?= htmlspecialchars($p['icon']) ?>"></i></div>
                <div class="pl"><?= htmlspecialchars($p['label']) ?></div>
                <div class="pc"><?= $chance ?>%</div>
            </div>
        <?php endforeach; ?>
    </div>

    <form class="spin-form glass reveal delay-3" id="spinForm" action="create_payment.php" method="POST" novalidate>
        <div class="form-eyebrow">введите ник для прокрута</div>
        <div class="nick-wrap">
            <i class="ph ph-user"></i>
            <input type="text" name="nickname" id="nickname" placeholder="Ivan_Ivanov" autocomplete="off" required>
        </div>

        <input type="hidden" name="purpose" value="roulette">
        <input type="hidden" name="server_name" value="<?= htmlspecialchars($c['server']['name']) ?>">
        <input type="hidden" name="method" id="methodInput" value="sbp">

        <button type="button" class="spin-btn" id="openMethodBtn">
            <i class="ph-fill ph-game-controller"></i>
            Крутить
            <span class="price"><?= $price ?> ₽</span>
        </button>

        <div class="error-box" id="errorBox">
            <i class="ph ph-warning-circle"></i>
            <span id="errorText"></span>
        </div>
    </form>
</section>

<!-- Модалка выбора способа оплаты — копия с donate.php -->
<div class="modal-backdrop" id="methodModal">
    <div class="modal-card">
        <div class="modal-grip"></div>
        <div class="modal-head">
            <button type="button" class="back-btn" onclick="closeModal()" aria-label="Назад"><i class="ph ph-arrow-left"></i></button>
            <h3>Способ оплаты</h3>
            <p>Выберите удобный способ — деньги поступят моментально</p>
        </div>

        <div class="modal-cat">Быстрая оплата</div>
        <?php foreach ($methods as $i => $m): if (!$m['available']) continue; ?>
            <button type="button"
                    class="method-tile<?= $i === 0 ? ' selected' : '' ?>"
                    data-method="<?= htmlspecialchars($m['code']) ?>"
                    data-name="<?= htmlspecialchars($m['name']) ?>"
                    onclick="selectMethod(this)">
                <div class="ic-box <?= htmlspecialchars($m['code']) ?>"><i class="ph <?= htmlspecialchars($m['icon']) ?>"></i></div>
                <div class="info">
                    <div class="name"><?= htmlspecialchars($m['name']) ?></div>
                    <div class="sub"><?= htmlspecialchars($m['sub']) ?></div>
                    <div class="fee"><?= htmlspecialchars($m['fee']) ?></div>
                </div>
                <div class="radio"></div>
            </button>
        <?php endforeach; ?>

        <div class="modal-cat">Скоро будут доступны</div>
        <?php foreach ($methods as $m): if ($m['available']) continue; ?>
            <button type="button" class="method-tile disabled" disabled>
                <div class="ic-box"><i class="ph <?= htmlspecialchars($m['icon']) ?>"></i></div>
                <div class="info">
                    <div class="name"><?= htmlspecialchars($m['name']) ?></div>
                    <div class="sub"><?= htmlspecialchars($m['sub']) ?></div>
                    <div class="fee"><?= htmlspecialchars($m['fee']) ?></div>
                </div>
                <div class="radio"></div>
            </button>
        <?php endforeach; ?>

        <div class="secure-row">
            <i class="ph-fill ph-lock-key"></i>
            <span class="t">Безопасная оплата через <b>Platega</b>. Данные карты не сохраняются.</span>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-cancel" onclick="closeModal()">Отмена</button>
            <button type="button" class="modal-confirm" id="confirmPayBtn" onclick="confirmPay()">
                <i class="ph ph-arrow-right ic-go"></i>
                <i class="ph ph-circle-notch spinner-sm"></i>
                <span class="label">Оплатить <?= $price ?> ₽</span>
            </button>
        </div>
    </div>
</div>

<!-- Модалка результата прокрутки -->
<div class="result-modal" id="resultModal">
    <div class="result-card" id="resultCard">
        <div class="ic-big"><i class="ph-fill ph-coin" id="resultIcon"></i></div>
        <div class="eyebrow">Поздравляем!</div>
        <h3>Вы выиграли</h3>
        <div class="prize-name" id="resultLabel">—</div>
        <div class="prize-note" id="resultNote">Награда зачислена на ваш аккаунт.</div>
        <div class="actions">
            <a href="roulette.php" class="btn btn-ghost">Ещё разок</a>
            <a href="<?= htmlspecialchars($l['main']) ?>" class="btn btn-primary">На главную</a>
        </div>
    </div>
</div>

<?php render_footer(); ?>
<?php render_common_js(); ?>

<script>
    const PRIZES      = <?= json_encode($prizes, JSON_UNESCAPED_UNICODE) ?>;
    const PRICE       = <?= $price ?>;
    const SPIN_ORDER  = <?= json_encode($spinOrderId, JSON_UNESCAPED_UNICODE) ?>;
    const PAYMENT_ERR = <?= $paymentError ? 'true' : 'false' ?>;

    /* ---------- Колесо ---------- */
    const strip = document.getElementById('strip');
    const wheelEl = document.querySelector('.wheel');
    const wheelStatus = document.getElementById('wheelStatus');
    const CELL_W = 130;
    const CELL_W_MOBILE = 110;
    const TOTAL_CELLS = 60;
    const TARGET_INDEX = 56;

    function cellWidth() {
        return window.innerWidth <= 760 ? CELL_W_MOBILE : CELL_W;
    }

    function pickWeightedIndex() {
        const total = PRIZES.reduce((s, p) => s + Number(p.weight || 0), 0);
        if (total <= 0) return 0;
        let r = Math.random() * total;
        for (let i = 0; i < PRIZES.length; i++) {
            r -= Number(PRIZES[i].weight || 0);
            if (r <= 0) return i;
        }
        return 0;
    }

    function renderStrip(targetPrizeIdx) {
        const html = [];
        for (let i = 0; i < TOTAL_CELLS; i++) {
            const idx = i === TARGET_INDEX ? targetPrizeIdx : pickWeightedIndex();
            const p = PRIZES[idx];
            html.push(
                '<div class="cell tier-' + p.tier + '">' +
                    '<i class="ph-fill ' + p.icon + ' ci"></i>' +
                    '<span class="ct">' + p.label + '</span>' +
                '</div>'
            );
        }
        strip.style.transition = 'none';
        strip.style.transform = 'translate3d(0,0,0)';
        strip.innerHTML = html.join('');
        // force reflow
        void strip.offsetHeight;
    }

    function spinTo(prizeIdx) {
        renderStrip(prizeIdx);
        const w = wheelEl.clientWidth;
        const cw = cellWidth();
        const jitter = (Math.random() * (cw * 0.55) - cw * 0.275);
        const targetCenter = TARGET_INDEX * cw + cw / 2;
        const offset = -(targetCenter - w / 2 + jitter);

        requestAnimationFrame(() => {
            strip.style.transition = 'transform 6.4s cubic-bezier(0.05, 0.5, 0.1, 1)';
            strip.style.transform = 'translate3d(' + offset.toFixed(2) + 'px, 0, 0)';
        });

        wheelStatus.textContent = 'Прокрутка…';
        wheelStatus.classList.add('busy');

        setTimeout(() => {
            wheelStatus.textContent = '';
            wheelStatus.classList.remove('busy');
            showResult(PRIZES[prizeIdx]);
        }, 6700);
    }

    /* Демо-старт без прокрутки — заполняем колесо случайной лентой */
    renderStrip(pickWeightedIndex());

    /* ---------- Модалка результата ---------- */
    const resultModal  = document.getElementById('resultModal');
    const resultCard   = document.getElementById('resultCard');
    const resultIcon   = document.getElementById('resultIcon');
    const resultLabel  = document.getElementById('resultLabel');
    const resultNote   = document.getElementById('resultNote');

    function showResult(prize) {
        resultCard.className = 'result-card tier-' + (prize.tier || 'common');
        resultIcon.className = 'ph-fill ' + (prize.icon || 'ph-coin');
        resultLabel.textContent = prize.label || '—';

        const amount = Number(prize.amount || prize.coins || 0);
        if (amount > 0) {
            const formatted = amount.toLocaleString('ru-RU');
            if (prize.kind === 'money') {
                resultNote.textContent = '+ ' + formatted + ' ₽ зачислено в Cash.';
            } else {
                resultNote.textContent = '+ ' + formatted + ' доната зачислено в Cash_Donate.';
            }
        } else {
            resultNote.textContent = 'Награда зачислена на ваш аккаунт.';
        }

        resultModal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    resultModal.addEventListener('click', (e) => {
        if (e.target === resultModal) {
            resultModal.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    /* ---------- Возврат с Platega: ?spin=ORDER_ID ---------- */
    function findPrizeIndexByLabel(label) {
        for (let i = 0; i < PRIZES.length; i++) {
            if (PRIZES[i].label === label) return i;
        }
        return 0;
    }

    function pollResult(orderId, attempts) {
        if (attempts <= 0) {
            wheelStatus.textContent = 'Не удалось получить результат. Свяжитесь с поддержкой.';
            wheelStatus.classList.remove('busy');
            document.getElementById('openMethodBtn').removeAttribute('disabled');
            return;
        }
        fetch('roulette.php?result=' + encodeURIComponent(orderId), { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (data && data.status === 'ready' && data.data && data.data.prize) {
                    const idx = findPrizeIndexByLabel(data.data.prize.label);
                    spinTo(idx);
                } else {
                    setTimeout(() => pollResult(orderId, attempts - 1), 2000);
                }
            })
            .catch(() => setTimeout(() => pollResult(orderId, attempts - 1), 2000));
    }

    if (SPIN_ORDER) {
        wheelStatus.textContent = 'Подтверждаем оплату…';
        wheelStatus.classList.add('busy');
        document.getElementById('openMethodBtn').setAttribute('disabled', 'disabled');
        pollResult(SPIN_ORDER, 30);
    }

    /* ---------- Форма + payment-модалка (как на donate) ---------- */
    const nickInput  = document.getElementById('nickname');
    const errorBox   = document.getElementById('errorBox');
    const errorText  = document.getElementById('errorText');
    const methodInput= document.getElementById('methodInput');
    const openBtn    = document.getElementById('openMethodBtn');
    const modal      = document.getElementById('methodModal');
    const confirmBtn = document.getElementById('confirmPayBtn');
    const methodTiles = document.querySelectorAll('.method-tile:not(.disabled)');

    function showError(msg) {
        errorText.textContent = msg;
        errorBox.classList.add('show');
        clearTimeout(showError._t);
        showError._t = setTimeout(() => errorBox.classList.remove('show'), 4500);
    }

    function validateForm() {
        const nick = nickInput.value.trim();
        if (!nick) { showError('Укажите никнейм'); return false; }
        if (!/^[A-Za-z0-9_]{2,24}$/.test(nick)) { showError('Никнейм: латиница, цифры, "_", 2–24 символа'); return false; }
        return true;
    }

    function selectMethod(el) {
        methodTiles.forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        methodInput.value = el.dataset.method;
    }

    function openModal() {
        if (!validateForm()) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    openBtn.addEventListener('click', openModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

    function confirmPay() {
        if (!validateForm()) return;
        confirmBtn.classList.add('loading');
        confirmBtn.querySelector('.label').textContent = 'Создание платежа...';
        confirmBtn.setAttribute('disabled', 'disabled');
        setTimeout(() => document.getElementById('spinForm').submit(), 220);
    }

    if (PAYMENT_ERR) {
        showError('Платёж не был завершён. Попробуйте ещё раз.');
    }
</script>
</body>
</html>
