<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
$c = $config['core']; $l = $c['links'];
$yk = $config['platega'];

$packages = [
    ['amount' => 100,  'tag' => 'Старт',      'desc' => 'Первый шаг',          'badge' => null,     'discount' => 0],
    ['amount' => 250,  'tag' => 'Базовый',    'desc' => 'Лёгкое начало',       'badge' => null,     'discount' => 0],
    ['amount' => 500,  'tag' => 'Популярный', 'desc' => 'Чаще всего берут',    'badge' => 'best',   'discount' => 5],
    ['amount' => 1000, 'tag' => 'Премиум',    'desc' => 'Для опытных',         'badge' => 'profit', 'discount' => 10],
    ['amount' => 2500, 'tag' => 'VIP',        'desc' => 'Полный набор',        'badge' => null,     'discount' => 15],
    ['amount' => 5000, 'tag' => 'Легенда',    'desc' => 'Максимальный буст',   'badge' => null,     'discount' => 20],
];

$methods = [
    ['code' => 'sbp',     'name' => 'СБП',                'sub' => 'ОПЛАТА ПО QR-КОДУ', 'icon' => 'ph-qr-code',                'available' => true,  'fee' => 'Без комиссии · Мгновенно'],
    ['code' => 'card',    'name' => 'Банковская карта',   'sub' => 'Visa · MIR · MasterCard',     'icon' => 'ph-credit-card',            'available' => false,  'fee' => 'soon'],
    ['code' => 'card_uz', 'name' => 'Иностранные карты',  'sub' => 'UZ · KZ · BY',                'icon' => 'ph-globe-hemisphere-west', 'available' => false, 'fee' => 'soon'],
    ['code' => 'usdt',    'name' => 'USDT TRC20',         'sub' => 'Криптовалюта Tether',         'icon' => 'ph-coins',                  'available' => false, 'fee' => 'soon'],
];

$paymentError = isset($_GET['error']) && $_GET['error'] === 'payment_failed';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Пополнение', '
    .donate-page { padding: 110px 14px 30px; min-height: 100vh; display: flex; flex-direction: column; align-items: center; }

    .receipt-wrap { width: 100%; max-width: 460px; }
    .receipt-eyebrow { text-align: center; font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px; }
    .receipt-title { text-align: center; font-family: \'Space Grotesk\', sans-serif; font-size: 26px; font-weight: 600; letter-spacing: -0.025em; margin-bottom: 4px; }
    .receipt-sub { text-align: center; color: var(--text-secondary); font-size: 13px; margin-bottom: 24px; }

    .receipt {
        position: relative;
        background: #fafbfa;
        border-radius: 0;
        padding: 0;
        box-shadow: 0 30px 80px rgba(20, 30, 40, 0.18), 0 8px 24px rgba(20,30,40,0.08);
        font-family: \'Inter\', sans-serif;
    }
    .receipt::before, .receipt::after {
        content: \'\'; position: absolute; left: 0; right: 0; height: 14px;
        background-image: radial-gradient(circle at 8px 7px, transparent 6px, #fafbfa 7px);
        background-size: 16px 14px;
        background-repeat: repeat-x;
    }
    .receipt::before { top: -7px; background-position: 0 -7px; }
    .receipt::after { bottom: -7px; background-position: 0 7px; transform: rotate(180deg); }

    .receipt-inner { padding: 28px 28px 22px; }

    .r-head { text-align: center; padding-bottom: 18px; border-bottom: 1.5px dashed rgba(0,0,0,0.13); }
    .r-head .logo-row { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 10px; padding: 6px 14px; border-radius: 999px; background: rgba(0,0,0,0.04); font-family: \'Space Grotesk\', sans-serif; font-weight: 700; font-size: 13px; color: var(--text-primary); letter-spacing: -0.01em; }
    .r-head .logo-row i { font-size: 14px; }
    .r-head h2 { font-family: \'Space Grotesk\', sans-serif; font-size: 22px; font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); }
    .r-head .order { display: inline-block; margin-top: 6px; font-family: \'JetBrains Mono\', monospace; font-size: 11px; color: var(--text-muted); letter-spacing: 0.05em; }

    .r-section { padding: 16px 0; border-bottom: 1px dashed rgba(0,0,0,0.10); }
    .r-section:last-child { border-bottom: none; padding-bottom: 6px; }

    .r-label { font-size: 10.5px; font-weight: 600; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }

    .r-input-wrap { display: flex; align-items: center; gap: 10px; padding: 4px 0; }
    .r-input-wrap i.r-ic { font-size: 18px; color: var(--text-secondary); flex-shrink: 0; }
    .r-input {
        flex: 1; min-width: 0;
        background: transparent; border: none; outline: none;
        font: 600 16px \'Inter\', sans-serif;
        color: var(--text-primary);
        padding: 6px 0;
        border-bottom: 1.5px dashed rgba(0,0,0,0.18);
        transition: border-color 0.2s;
    }
    .r-input:focus { border-bottom-color: var(--text-primary); border-bottom-style: solid; }
    .r-input::placeholder { color: var(--text-muted); font-weight: 500; }
    .r-input[readonly] { color: var(--text-secondary); cursor: default; font-weight: 500; }

    .r-pkg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .r-pkg {
        position: relative;
        padding: 12px;
        background: rgba(0,0,0,0.025);
        border: 1.5px solid transparent;
        border-radius: 12px;
        cursor: pointer;
        text-align: left;
        transition: 0.2s;
        font-family: inherit;
        color: inherit;
    }
    .r-pkg:hover { background: rgba(0,0,0,0.05); }
    .r-pkg .p-tag { font-size: 9.5px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--text-muted); }
    .r-pkg .p-amt { font-family: \'Space Grotesk\', sans-serif; font-size: 20px; font-weight: 600; letter-spacing: -0.02em; color: var(--text-primary); margin: 4px 0 2px; }
    .r-pkg .p-desc { font-size: 11px; color: var(--text-secondary); }
    .r-pkg .p-disc { display: inline-block; margin-top: 6px; font-size: 9.5px; padding: 2px 7px; border-radius: 5px; background: var(--success-soft); color: var(--success); font-weight: 700; letter-spacing: 0.04em; }
    .r-pkg .p-check { position: absolute; top: 10px; right: 10px; width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid rgba(0,0,0,0.18); display: flex; align-items: center; justify-content: center; font-size: 9px; color: transparent; transition: 0.25s; }
    .r-pkg.selected { background: var(--text-primary); }
    .r-pkg.selected .p-tag { color: rgba(255,255,255,0.65); }
    .r-pkg.selected .p-amt { color: #fff; }
    .r-pkg.selected .p-desc { color: rgba(255,255,255,0.78); }
    .r-pkg.selected .p-check { background: #fff; border-color: #fff; color: var(--text-primary); }
    .r-pkg.selected .p-disc { background: rgba(255,255,255,0.18); color: #fff; }
    .p-flag { position: absolute; top: -8px; left: 10px; padding: 3px 8px; border-radius: 5px; font-size: 9px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
    .p-flag.best { background: #15181d; color: #fff; }
    .p-flag.profit { background: var(--success); color: #fff; }

    .r-amount-row { display: flex; align-items: baseline; gap: 8px; }
    .r-amount-row .r-input { font-size: 22px; font-family: \'Space Grotesk\', sans-serif; font-weight: 600; letter-spacing: -0.02em; }
    .r-amount-row .currency { font-size: 14px; color: var(--text-muted); font-weight: 600; }
    .r-amount-hint { font-size: 11.5px; color: var(--success); margin-top: 4px; opacity: 0; height: 0; transition: 0.25s; display: flex; align-items: center; gap: 5px; }
    .r-amount-hint.show { opacity: 1; height: 16px; margin-top: 8px; }

    .r-divider-line { display: flex; align-items: center; gap: 10px; padding: 14px 0 8px; }
    .r-divider-line .l { flex: 1; height: 1px; background: rgba(0,0,0,0.08); }
    .r-divider-line span { font-size: 10px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-muted); font-weight: 500; }

    .r-summary { padding: 14px 0 8px; }
    .r-sum-row { display: flex; justify-content: space-between; align-items: baseline; padding: 4px 0; font-size: 13px; }
    .r-sum-row .k { color: var(--text-secondary); }
    .r-sum-row .v { color: var(--text-primary); font-weight: 600; font-family: \'JetBrains Mono\', monospace; font-size: 12.5px; }
    .r-sum-row .v.green { color: var(--success); }
    .r-total {
        margin-top: 10px; padding: 14px 0 0;
        border-top: 1.5px dashed rgba(0,0,0,0.13);
        display: flex; justify-content: space-between; align-items: baseline;
    }
    .r-total .k { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
    .r-total .v { font-family: \'Space Grotesk\', sans-serif; font-size: 28px; font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); }

    .r-pay-btn {
        width: 100%; margin-top: 16px;
        padding: 16px;
        background: var(--text-primary);
        color: #fff;
        border: none;
        border-radius: 14px;
        font: 600 14px \'Inter\', sans-serif;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; gap: 10px;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .r-pay-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(20,30,40,0.25); }

    .r-foot { padding-top: 14px; text-align: center; }
    .r-foot .barcode { display: flex; justify-content: center; gap: 1.5px; margin-bottom: 10px; }
    .r-foot .barcode span { display: block; height: 30px; background: var(--text-primary); }
    .r-foot .barcode-label { font-family: \'JetBrains Mono\', monospace; font-size: 11px; color: var(--text-muted); letter-spacing: 0.18em; }

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

    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(15, 20, 25, 0.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
        z-index: 2000; opacity: 0; pointer-events: none; transition: opacity 0.3s;
        display: flex; align-items: flex-end; justify-content: center;
        padding: 20px;
    }
    .modal-backdrop.open { opacity: 1; pointer-events: auto; }
    .modal-card {
        width: 100%; max-width: 460px;
        background: #fbfcfa;
        border-radius: 28px;
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
        position: relative;
        width: 100%;
        padding: 14px 16px;
        background: rgba(0,0,0,0.03);
        border: 1.5px solid transparent;
        border-radius: 16px;
        cursor: pointer;
        transition: 0.22s;
        display: flex; align-items: center; gap: 14px;
        font-family: inherit; color: inherit;
        text-align: left;
        margin-bottom: 8px;
    }
    .method-tile:hover:not(.disabled) { background: rgba(0,0,0,0.06); }
    .method-tile.selected { background: #fff; border-color: var(--text-primary); box-shadow: 0 6px 20px rgba(20,30,40,0.10); }
    .method-tile.disabled { opacity: 0.45; cursor: not-allowed; }
    .method-tile .ic-box { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(140deg, #25282d, #15181d); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
    .method-tile .info { flex: 1; min-width: 0; }
    .method-tile .name { font-size: 14.5px; font-weight: 600; color: var(--text-primary); }
    .method-tile .sub { font-size: 11.5px; color: var(--text-muted); }
    .method-tile .fee { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
    .method-tile .radio { width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid rgba(0,0,0,0.16); display: flex; align-items: center; justify-content: center; transition: 0.2s; flex-shrink: 0; }
    .method-tile .radio::after { content: \'\'; width: 10px; height: 10px; border-radius: 50%; background: var(--text-primary); transform: scale(0); transition: 0.2s; }
    .method-tile.selected .radio { border-color: var(--text-primary); }
    .method-tile.selected .radio::after { transform: scale(1); }

    .method-tile .ic-box.sbp { background: linear-gradient(140deg, #1d9c5e, #15814b); }
    .method-tile .ic-box.card { background: linear-gradient(140deg, #2c5fcc, #1f47a3); }

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

    @media (max-width: 560px) {
        .donate-page { padding: 95px 10px 24px; }
        .receipt-inner { padding: 24px 20px 20px; }
        .r-pkg-grid { grid-template-columns: 1fr 1fr; }
        .r-pkg .p-amt { font-size: 18px; }
        .modal-card { padding: 22px 18px; }
        .modal-actions { grid-template-columns: 1fr; }
        .modal-actions .modal-cancel { order: 2; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('donate'); ?>
<?php render_tg_float(); ?>

<section class="donate-page">
    <div class="receipt-wrap reveal">
        <div class="receipt-eyebrow">Чек на пополнение</div>
        <h1 class="receipt-title">Поддержать проект</h1>
        <p class="receipt-sub">Заполните чек ниже — зачисление автоматическое</p>

        <div class="receipt">
            <div class="receipt-inner">
                <div class="r-head">
                    <div class="logo-row">
                        <i class="ph-fill ph-coin"></i> <?= htmlspecialchars($c['title']) ?>
                    </div>
                    <h2>Donate Receipt</h2>
                    <span class="order">№ <span id="orderNum">000000</span> · <?= htmlspecialchars(date('d.m.Y')) ?></span>
                </div>

                <form id="donateForm" action="create_payment.php" method="POST" novalidate>

                    <div class="r-section">
                        <div class="r-label">Никнейм</div>
                        <div class="r-input-wrap">
                            <i class="ph ph-user r-ic"></i>
                            <input type="text" class="r-input" name="nickname" id="nickname" placeholder="Ivan_Ivanov" autocomplete="off" required>
                        </div>
                    </div>

                    <div class="r-section">
                        <div class="r-label">Сервер</div>
                        <div class="r-input-wrap">
                            <i class="ph ph-hard-drives r-ic"></i>
                            <input type="text" class="r-input" name="server" value="<?= htmlspecialchars($c['server']['name']) ?>" readonly>
                        </div>
                    </div>

                    <div class="r-section">
                        <div class="r-label">Готовые пакеты</div>
                        <div class="r-pkg-grid">
                            <?php foreach ($packages as $p): ?>
                                <button type="button" class="r-pkg" data-amount="<?= $p['amount'] ?>" data-discount="<?= (int)$p['discount'] ?>" onclick="selectPackage(this)">
                                    <?php if ($p['badge']): ?>
                                        <span class="p-flag <?= htmlspecialchars($p['badge']) ?>">
                                            <?php if ($p['badge'] === 'best'): ?><i class="ph-fill ph-star" style="font-size:8px;"></i> Лучший выбор
                                            <?php else: ?><i class="ph-fill ph-trend-up" style="font-size:8px;"></i> Выгодно
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="p-check"><i class="ph-fill ph-check"></i></div>
                                    <div class="p-tag"><?= htmlspecialchars($p['tag']) ?></div>
                                    <div class="p-amt"><?= number_format($p['amount'], 0, '.', ' ') ?> ₽</div>
                                    <div class="p-desc"><?= htmlspecialchars($p['desc']) ?></div>
                                    <?php if ($p['discount']): ?>
                                        <span class="p-disc">+<?= (int)$p['discount'] ?>%</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="r-divider-line">
                        <div class="l"></div><span>или своя сумма</span><div class="l"></div>
                    </div>

                    <div class="r-section">
                        <div class="r-label">Сумма</div>
                        <div class="r-input-wrap r-amount-row">
                            <input type="number" class="r-input" name="amount" id="amountInput" placeholder="0" min="10" max="100000" step="1" inputmode="numeric" required>
                            <span class="currency">₽</span>
                        </div>
                        <div class="r-amount-hint" id="coinsHint"><i class="ph-fill ph-coin"></i> <span id="coinsHintText"></span></div>
                    </div>

                    <div class="r-summary">
                        <div class="r-sum-row">
                            <span class="k">Игрок</span>
                            <span class="v" id="sumNick">—</span>
                        </div>
                        <div class="r-sum-row">
                            <span class="k">Базовая сумма</span>
                            <span class="v" id="sumBase">0,00</span>
                        </div>
                        <div class="r-sum-row" id="sumDiscRow" style="display:none;">
                            <span class="k">Бонус пакета</span>
                            <span class="v green" id="sumDisc">+0%</span>
                        </div>
                        <div class="r-total">
                            <span class="k">Итого монет</span>
                            <span class="v"><span id="sumCoins">0</span></span>
                        </div>
                    </div>

                    <input type="hidden" name="server_name" value="<?= htmlspecialchars($c['server']['name']) ?>">
                    <input type="hidden" name="method" id="methodInput" value="sbp">

                    <button type="button" class="r-pay-btn" id="openMethodBtn">
                        <i class="ph ph-arrow-right"></i> Перейти к оплате
                    </button>

                    <div class="error-box" id="errorBox">
                        <i class="ph ph-warning-circle"></i>
                        <span id="errorText"></span>
                    </div>
                </form>

                <div class="r-foot">
                    <div class="barcode" id="barcode"></div>
                    <div class="barcode-label">SCAN · 4 8 0 2 9 1 7 3 6 5</div>
                </div>
            </div>
        </div>
    </div>
</section>

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
                <span class="label">Оплатить <span id="confirmAmount"></span></span>
            </button>
        </div>
    </div>
</div>

<?php render_footer(); ?>

<?php render_common_js(); ?>
<script>
    const RATE = <?= (int)$yk['rate'] ?>;
    const pkgBtns = document.querySelectorAll('.r-pkg');
    const methodTiles = document.querySelectorAll('.method-tile:not(.disabled)');
    const amountInput = document.getElementById('amountInput');
    const nickInput = document.getElementById('nickname');
    const sumNick = document.getElementById('sumNick');
    const sumBase = document.getElementById('sumBase');
    const sumCoins = document.getElementById('sumCoins');
    const sumDisc = document.getElementById('sumDisc');
    const sumDiscRow = document.getElementById('sumDiscRow');
    const coinsHint = document.getElementById('coinsHint');
    const coinsHintText = document.getElementById('coinsHintText');
    const errorBox = document.getElementById('errorBox');
    const errorText = document.getElementById('errorText');
    const methodInput = document.getElementById('methodInput');
    const orderNum = document.getElementById('orderNum');
    const openBtn = document.getElementById('openMethodBtn');
    const modal = document.getElementById('methodModal');
    const confirmBtn = document.getElementById('confirmPayBtn');
    const confirmAmount = document.getElementById('confirmAmount');
    const barcode = document.getElementById('barcode');

    orderNum.textContent = String(Math.floor(Math.random() * 900000 + 100000));

    const seed = Math.random();
    const widths = [];
    for (let i = 0; i < 60; i++) widths.push(1 + Math.floor(((seed * 9301 + i * 49297) % 233280) / 233280 * 4));
    barcode.innerHTML = widths.map(w => `<span style="width:${w}px"></span>`).join('');

    let currentDiscount = 0;
    const fmt = (n) => Math.round(n).toLocaleString('ru-RU');
    const fmtR = (n) => n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function updateSummary() {
        const nick = nickInput.value.trim();
        const amount = parseInt(amountInput.value) || 0;
        const baseCoins = amount * RATE;
        const bonus = Math.floor(baseCoins * currentDiscount / 100);
        const totalCoins = baseCoins + bonus;

        sumNick.textContent = nick || '—';
        sumBase.textContent = fmtR(amount) + ' ₽';
        sumCoins.textContent = fmt(totalCoins);
        confirmAmount.textContent = amount > 0 ? fmt(amount) + ' ₽' : '';

        if (bonus > 0) {
            sumDisc.textContent = '+' + currentDiscount + '% (+' + fmt(bonus) + ')';
            sumDiscRow.style.display = 'flex';
        } else {
            sumDiscRow.style.display = 'none';
        }

        if (amount >= 10) {
            coinsHintText.textContent = '+ ' + fmt(totalCoins) + ' монет' + (bonus > 0 ? ' (с бонусом)' : '');
            coinsHint.classList.add('show');
        } else {
            coinsHint.classList.remove('show');
        }
    }

    function selectPackage(el) {
        pkgBtns.forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        amountInput.value = el.dataset.amount;
        currentDiscount = parseInt(el.dataset.discount) || 0;
        updateSummary();
    }

    function selectMethod(el) {
        methodTiles.forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        methodInput.value = el.dataset.method;
    }

    amountInput.addEventListener('input', () => {
        let matched = false;
        pkgBtns.forEach(b => {
            const isMatch = b.dataset.amount === amountInput.value;
            b.classList.toggle('selected', isMatch);
            if (isMatch) { currentDiscount = parseInt(b.dataset.discount) || 0; matched = true; }
        });
        if (!matched) currentDiscount = 0;
        updateSummary();
    });
    nickInput.addEventListener('input', updateSummary);

    function showError(msg) {
        errorText.textContent = msg;
        errorBox.classList.add('show');
        clearTimeout(showError._t);
        showError._t = setTimeout(() => errorBox.classList.remove('show'), 4500);
    }

    function validateForm() {
        const nick = nickInput.value.trim();
        const amount = parseInt(amountInput.value);
        if (!nick) { showError('Укажите никнейм'); return false; }
        if (!/^[A-Za-z0-9_]{2,24}$/.test(nick)) { showError('Никнейм: латиница, цифры, "_", 2–24 символа'); return false; }
        if (!amount || amount < 10) { showError('Минимальная сумма — 10 ₽'); return false; }
        if (amount > 100000) { showError('Максимальная сумма — 100 000 ₽'); return false; }
        return true;
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
        setTimeout(() => document.getElementById('donateForm').submit(), 220);
    }

    <?php if ($paymentError): ?>
    showError('Платёж не был завершён. Попробуйте ещё раз.');
    <?php endif; ?>

    updateSummary();
</script>
</body>
</html>
