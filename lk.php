<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials.php';

$c = $config['core'];
$l = $c['links'];

/* =========================================================================
 *  POST handlers
 * ========================================================================= */

function lk_redirect(string $tab = 'profile'): void
{
    $tab = preg_replace('/[^a-z_]/', '', $tab) ?: 'profile';
    header('Location: lk.php?tab=' . $tab, true, 303);
    exit;
}

function lk_handle_login(): void
{
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Сессия устарела. Обновите страницу и попробуйте ещё раз.');
        lk_redirect('login');
    }

    if (!auth_throttle_check(5, 15)) {
        flash_set('error', 'Слишком много неудачных попыток. Подожди 15 минут.');
        lk_redirect('login');
    }

    $nick = trim((string) ($_POST['nickname'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{2,24}$/', $nick) || $pass === '') {
        auth_throttle_record(false);
        flash_set('error', 'Неверный никнейм или пароль.');
        lk_redirect('login');
    }

    try {
        $row = auth_fetch_player_row($nick);
    } catch (\Throwable $e) {
        flash_set('error', 'Сервер временно недоступен.');
        lk_redirect('login');
    }

    global $config;
    $algo = (string) $config['auth']['password_hash'];

    if (!$row || !auth_verify_password((string) $row['pass'], $pass, $algo)) {
        auth_throttle_record(false);
        // Намеренно одинаковая ошибка для несуществующего ника и неправильного пароля,
        // чтобы не давать enumeration
        flash_set('error', 'Неверный никнейм или пароль.');
        lk_redirect('login');
    }

    auth_throttle_record(true);
    auth_login((string) $row['nick'], (int) $row['admin_level']);
    flash_set('success', 'С возвращением, ' . $row['nick'] . '!');
    lk_redirect('profile');
}

function lk_handle_logout(): void
{
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        lk_redirect('login');
    }
    auth_logout();
    flash_set('success', 'Вы вышли из аккаунта.');
    lk_redirect('login');
}

function lk_handle_redeem(): void
{
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Сессия устарела.');
        lk_redirect('profile');
    }
    $u = auth_user();
    if (!$u) {
        lk_redirect('login');
    }

    $code = bonus_normalize_code((string) ($_POST['code'] ?? ''));
    if ($code === '') {
        flash_set('error', 'Введите код.');
        lk_redirect('profile');
    }

    $r = bonus_redeem($code, (string) $u['nickname']);
    if ($r['ok']) {
        flash_set('success', 'Код активирован: ' . $r['reward'] . '. ' . $r['message']);
    } else {
        flash_set('error', $r['message']);
    }
    lk_redirect('profile');
}

function lk_handle_create_code(): void
{
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Сессия устарела.');
        lk_redirect('admin_codes');
    }
    if (!auth_is_admin()) {
        http_response_code(403);
        exit('Forbidden');
    }

    global $config;
    $defaultLen = (int) ($config['auth']['bonus_code_len'] ?? 10);

    $rawCode = (string) ($_POST['code'] ?? '');
    $code    = $rawCode === '' ? bonus_generate_code($defaultLen) : bonus_normalize_code($rawCode);

    /* Принимаем массив призов: prize_type[], prize_value[], item_id[], item_qty[].
       Берём только заполненные слоты, до BONUS_PRIZES_MAX штук. */
    $types = (array) ($_POST['prize_type']  ?? []);
    $vals  = (array) ($_POST['prize_value'] ?? []);
    $itIds = (array) ($_POST['item_id']     ?? []);
    $itQty = (array) ($_POST['item_qty']    ?? []);

    $prizes = [];
    $count  = max(count($types), count($vals), count($itIds), count($itQty));
    for ($i = 0; $i < $count && count($prizes) < BONUS_PRIZES_MAX; $i++) {
        $t = (int) ($types[$i] ?? 0);
        if ($t <= 0) continue;

        if ($t === BONUS_PRIZE_ITEM) {
            $iid = (int) ($itIds[$i] ?? 0);
            $iq  = (int) ($itQty[$i] ?? 0);
            if ($iid <= 0 || $iq <= 0) continue;
            $prizes[] = ['type' => $t, 'value' => $iid, 'extra' => $iq];
        } else {
            $v = (int) ($vals[$i] ?? 0);
            if ($v <= 0) continue;
            $prizes[] = ['type' => $t, 'value' => $v, 'extra' => 0];
        }
    }

    if (empty($prizes)) {
        flash_set('error', 'Заполните хотя бы один приз.');
        lk_redirect('admin_codes');
    }

    $limit   = (int) ($_POST['usage_limit'] ?? 1);
    $expRaw  = trim((string) ($_POST['expires_at'] ?? ''));
    $expiresAt = $expRaw === '' ? null : $expRaw;

    try {
        bonus_create($code, $prizes, $limit, $expiresAt, (string) auth_user()['nickname']);

        $describe = bonus_prizes_describe(
            array_column($prizes, 'type'),
            array_map(static fn($p) => $p['type'] === BONUS_PRIZE_ITEM ? $p['value'] : 0, $prizes),
            array_map(static fn($p) => $p['type'] === BONUS_PRIZE_ITEM ? $p['extra'] : $p['value'], $prizes)
        );
        flash_set('success', 'Код создан: ' . $code . ' (' . $describe . ')');
    } catch (\Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    lk_redirect('admin_codes');
}

function lk_handle_delete_code(): void
{
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Сессия устарела.');
        lk_redirect('admin_codes');
    }
    if (!auth_is_admin()) {
        http_response_code(403);
        exit('Forbidden');
    }
    $code = bonus_normalize_code((string) ($_POST['code'] ?? ''));
    if ($code !== '' && bonus_delete($code)) {
        flash_set('success', 'Код «' . $code . '» удалён.');
    } else {
        flash_set('error', 'Не удалось удалить код.');
    }
    lk_redirect('admin_codes');
}

/* Роутинг POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    switch ($action) {
        case 'login':         lk_handle_login();        break;
        case 'logout':        lk_handle_logout();       break;
        case 'redeem':        lk_handle_redeem();       break;
        case 'create_code':   lk_handle_create_code();  break;
        case 'delete_code':   lk_handle_delete_code();  break;
        default:              lk_redirect('profile');
    }
}

/* =========================================================================
 *  GET render
 * ========================================================================= */

$user = auth_user();

$tab = $_GET['tab'] ?? ($user ? 'profile' : 'login');
$tab = preg_replace('/[^a-z_]/', '', $tab);

if (!$user) {
    $tab = 'login';
} else {
    if (in_array($tab, ['admin', 'admin_codes'], true) && !auth_is_admin()) {
        $tab = 'profile';
    }
    if (!in_array($tab, ['profile', 'admin', 'admin_codes'], true)) {
        $tab = 'profile';
    }
}

$flash      = flash_pop();
$authedNick = $user ? (string) $user['nickname'] : '';

$adminCodes = ($tab === 'admin_codes' && auth_is_admin()) ? bonus_list(100) : [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head($user ? 'Кабинет' : 'Вход', '
    .lk-page { max-width: 980px; margin: 0 auto; padding: 110px 14px 30px; }

    .lk-hero { text-align: center; margin-bottom: 26px; padding: 28px 24px; }
    .lk-hero .ic-big { width: 60px; height: 60px; margin: 0 auto 14px; border-radius: 16px; background: var(--accent); color: var(--text-inverse); display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .lk-hero .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .lk-hero h1 { font-family: \'Space Grotesk\', sans-serif; font-size: clamp(26px, 4.4vw, 38px); font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin: 12px 0 6px; }
    .lk-hero p { color: var(--text-secondary); font-size: 13.5px; line-height: 1.55; max-width: 480px; margin: 0 auto; }

    /* ---------- Flash ---------- */
    .flash { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; font-size: 13px; font-weight: 500; margin-bottom: 18px; }
    .flash i { font-size: 16px; flex-shrink: 0; }
    .flash.success { background: var(--success-soft); color: var(--success); border: 1px solid rgba(29,156,94,0.25); }
    .flash.error   { background: var(--danger-soft);  color: var(--danger);  border: 1px solid rgba(201,57,84,0.25); }

    /* ---------- Login card ---------- */
    .login-wrap { max-width: 420px; margin: 0 auto; padding: 28px 26px; }
    .login-wrap .form-eyebrow { text-align: center; font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 18px; }

    .field { margin-bottom: 12px; }
    .field-label { font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; padding-left: 14px; }
    .field-input {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px;
        background: rgba(255,255,255,0.5);
        border: 1px solid var(--glass-border-soft);
        border-radius: 12px;
        transition: border-color 0.2s, background 0.2s;
    }
    .field-input:focus-within { background: rgba(255,255,255,0.7); border-color: var(--text-secondary); }
    .field-input i { font-size: 17px; color: var(--text-secondary); flex-shrink: 0; }
    .field-input input {
        flex: 1; min-width: 0;
        background: transparent; border: none; outline: none;
        font: 600 15px \'Inter\', sans-serif;
        color: var(--text-primary);
    }
    .field-input input::placeholder { color: var(--text-muted); font-weight: 500; }

    .field-input select {
        flex: 1; min-width: 0;
        background: transparent; border: none; outline: none;
        font: 600 14px \'Inter\', sans-serif;
        color: var(--text-primary);
        appearance: none;
        cursor: pointer;
    }
    .field-input select option { color: #111; }

    .pwd-toggle {
        background: none; border: none; cursor: pointer;
        color: var(--text-muted); font-size: 16px; padding: 4px;
        transition: color 0.2s;
    }
    .pwd-toggle:hover { color: var(--text-primary); }

    .submit-btn {
        width: 100%; padding: 14px;
        margin-top: 14px;
        border: none; border-radius: 14px;
        background: var(--accent); color: var(--text-inverse);
        font: 600 14px \'Inter\', sans-serif;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; gap: 10px;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(20,30,40,0.22); }
    .submit-btn.secondary { background: rgba(0,0,0,0.05); color: var(--text-primary); }
    .submit-btn.secondary:hover { background: rgba(0,0,0,0.08); box-shadow: none; }
    .submit-btn.danger { background: var(--danger); }
    .submit-btn.danger:hover { box-shadow: 0 14px 30px rgba(201,57,84,0.30); }

    .login-hint { font-size: 12px; color: var(--text-muted); text-align: center; margin-top: 14px; line-height: 1.5; }
    .login-hint b { color: var(--text-primary); font-weight: 600; }

    /* ---------- Tabs ---------- */
    .lk-tabs {
        display: flex; gap: 6px;
        padding: 6px;
        margin-bottom: 18px;
        overflow-x: auto;
    }
    .lk-tab {
        flex-shrink: 0;
        text-decoration: none;
        color: var(--text-secondary);
        padding: 10px 16px;
        border-radius: 11px;
        font-size: 13px; font-weight: 500;
        display: inline-flex; align-items: center; gap: 8px;
        transition: 0.2s;
        white-space: nowrap;
    }
    .lk-tab:hover { color: var(--text-primary); background: rgba(255,255,255,0.5); }
    .lk-tab.active { background: var(--accent); color: var(--text-inverse); font-weight: 600; }
    .lk-tab .badge { font-size: 9.5px; font-weight: 700; padding: 2px 7px; border-radius: 999px; background: rgba(245,196,83,0.85); color: #5a3d04; letter-spacing: 0.04em; }
    .lk-tab.active .badge { background: rgba(255,255,255,0.22); color: #fff; }

    /* ---------- Profile ---------- */
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .profile-card { padding: 24px 22px; }
    .profile-card .pcard-eyebrow { font-size: 10.5px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
    .profile-card .pcard-title { font-family: \'Space Grotesk\', sans-serif; font-size: 17px; font-weight: 600; color: var(--text-primary); margin-bottom: 14px; }

    .info-row { display: flex; justify-content: space-between; align-items: baseline; padding: 8px 0; border-bottom: 1px dashed rgba(0,0,0,0.07); font-size: 13.5px; }
    .info-row:last-child { border-bottom: none; }
    .info-row .k { color: var(--text-secondary); }
    .info-row .v { font-weight: 600; color: var(--text-primary); }
    .badge-admin { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; background: rgba(245,196,83,0.20); color: #8a6512; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; }

    .redeem-input-wrap { display: flex; gap: 10px; }
    .redeem-input-wrap .field-input { flex: 1; }
    .redeem-input-wrap input { font-family: \'JetBrains Mono\', monospace; letter-spacing: 0.06em; text-transform: uppercase; }
    .redeem-input-wrap .submit-btn { width: auto; margin-top: 0; padding: 0 22px; flex-shrink: 0; }

    .logout-row { display: flex; justify-content: flex-end; margin-top: 16px; }
    .logout-row form { display: inline-flex; }
    .logout-btn { padding: 10px 16px; border-radius: 11px; background: rgba(0,0,0,0.05); color: var(--text-primary); border: none; cursor: pointer; font: 500 13px \'Inter\', sans-serif; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
    .logout-btn:hover { background: rgba(201,57,84,0.10); color: var(--danger); }

    /* ---------- Admin: codes ---------- */
    .admin-grid { display: grid; grid-template-columns: 380px 1fr; gap: 16px; align-items: start; }
    .admin-card { padding: 22px; }
    .admin-card .acard-eyebrow { font-size: 10.5px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
    .admin-card h3 { font-family: \'Space Grotesk\', sans-serif; font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

    .codes-table { padding: 6px; }
    .codes-empty { text-align: center; padding: 30px 18px; color: var(--text-muted); font-size: 13px; }
    .codes-empty i { font-size: 26px; display: block; margin-bottom: 8px; }

    .code-row {
        display: grid;
        grid-template-columns: minmax(120px, 1fr) 100px 90px 100px 70px;
        gap: 10px;
        padding: 10px 12px;
        align-items: center;
        border-radius: 10px;
        transition: background 0.2s;
    }
    .code-row:hover { background: rgba(255,255,255,0.55); }
    .code-row + .code-row { border-top: 1px dashed rgba(0,0,0,0.07); }
    .code-row .c-code { font-family: \'JetBrains Mono\', monospace; font-size: 13.5px; font-weight: 700; color: var(--text-primary); letter-spacing: 0.05em; }
    .code-row .c-coins { font-family: \'Space Grotesk\', sans-serif; font-weight: 600; color: var(--text-primary); font-size: 13.5px; }
    .code-row .c-limit { font-size: 12.5px; color: var(--text-secondary); }
    .code-row .c-meta { font-size: 11.5px; color: var(--text-muted); }
    .code-row .c-act button { background: rgba(201,57,84,0.10); color: var(--danger); border: none; width: 32px; height: 32px; border-radius: 9px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }
    .code-row .c-act button:hover { background: var(--danger); color: #fff; }

    .codes-head {
        display: grid;
        grid-template-columns: minmax(120px, 1fr) 100px 90px 100px 70px;
        gap: 10px;
        padding: 8px 12px 12px;
        font-size: 10px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--text-muted);
        border-bottom: 1.5px dashed rgba(0,0,0,0.10);
        margin-bottom: 4px;
    }

    .codes-list-empty { padding: 36px 18px; text-align: center; color: var(--text-muted); font-size: 13px; }
    .codes-list-empty i { font-size: 28px; display: block; margin-bottom: 10px; }

    /* ---------- Prizes block ---------- */
    .prizes-block { margin-bottom: 14px; }
    .prizes-head {
        display: flex; align-items: center; justify-content: space-between;
        font-size: 11px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 10px; padding: 0 4px;
    }
    .prize-add {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(0,0,0,0.05);
        color: var(--text-primary);
        border: none; border-radius: 9px;
        padding: 6px 10px;
        font: 600 11px \'Inter\', sans-serif; letter-spacing: 0.04em; text-transform: uppercase;
        cursor: pointer;
        transition: 0.2s;
    }
    .prize-add:hover:not(:disabled) { background: var(--accent); color: var(--text-inverse); }
    .prize-add:disabled { cursor: not-allowed; }

    .prize-row {
        position: relative;
        padding: 14px 14px 4px;
        border: 1px dashed rgba(0,0,0,0.10);
        border-radius: 12px;
        background: rgba(255,255,255,0.35);
        margin-bottom: 12px;
    }
    .prize-row-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 6px;
    }
    .prize-row-num {
        font: 700 10.5px \'Inter\', sans-serif;
        letter-spacing: 0.18em; text-transform: uppercase;
        color: var(--text-muted);
    }
    .prize-del {
        background: rgba(201,57,84,0.10); color: var(--danger);
        border: none; border-radius: 8px;
        width: 26px; height: 26px;
        display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 12px;
        transition: 0.2s;
    }
    .prize-del:hover { background: var(--danger); color: #fff; }

    .copy-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

    @media (max-width: 760px) {
        .profile-grid { grid-template-columns: 1fr; }
        .admin-grid { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
        .code-row, .codes-head { grid-template-columns: 1fr 70px 70px; }
        .code-row .c-meta { display: none; }
        .codes-head .h-meta { display: none; }
        .redeem-input-wrap { flex-direction: column; }
        .redeem-input-wrap .submit-btn { width: 100%; padding: 14px; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('cabinet'); ?>
<?php render_tg_float(); ?>

<section class="lk-page">

<?php if ($flash): ?>
    <div class="flash <?= htmlspecialchars($flash['kind']) ?> reveal">
        <i class="ph-fill <?= $flash['kind'] === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?>"></i>
        <span><?= htmlspecialchars($flash['message']) ?></span>
    </div>
<?php endif; ?>

<?php if ($tab === 'login'): ?>
    <!-- ============================================================== -->
    <!--  ВХОД                                                          -->
    <!-- ============================================================== -->
    <div class="lk-hero glass-strong reveal">
        <div class="ic-big"><i class="ph-fill ph-user"></i></div>
        <div class="eyebrow">Личный кабинет</div>
        <h1>Вход в аккаунт</h1>
        <p>Используй ник и пароль от своего игрового аккаунта на сервере <?= htmlspecialchars($c['server']['name']) ?>.</p>
    </div>

    <div class="login-wrap glass reveal delay-1">
        <div class="form-eyebrow">авторизация</div>
        <form method="POST" action="lk.php" autocomplete="off">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="field">
                <div class="field-label">Никнейм</div>
                <div class="field-input">
                    <i class="ph ph-user"></i>
                    <input type="text" name="nickname" placeholder="Ivan_Ivanov" required pattern="[A-Za-z0-9_]{2,24}" autocomplete="username">
                </div>
            </div>

            <div class="field">
                <div class="field-label">Пароль</div>
                <div class="field-input">
                    <i class="ph ph-lock-key"></i>
                    <input type="password" name="password" id="passInput" placeholder="••••••••" required minlength="1" autocomplete="current-password">
                    <button type="button" class="pwd-toggle" onclick="togglePass()" tabindex="-1" aria-label="Показать/скрыть пароль">
                        <i class="ph ph-eye" id="pwdEye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="ph-fill ph-sign-in"></i> Войти
            </button>

            <div class="login-hint">
                Используется тот же пароль, что и в игре. Никаких новых регистраций — только игроки сервера.
            </div>
        </form>
    </div>

    <script>
        function togglePass() {
            const i = document.getElementById('passInput');
            const e = document.getElementById('pwdEye');
            if (i.type === 'password') { i.type = 'text';  e.className = 'ph ph-eye-slash'; }
            else                       { i.type = 'password'; e.className = 'ph ph-eye'; }
        }
    </script>

<?php else: ?>
    <!-- ============================================================== -->
    <!--  АВТОРИЗОВАН                                                   -->
    <!-- ============================================================== -->

    <div class="lk-hero glass-strong reveal">
        <div class="ic-big"><i class="ph-fill ph-user"></i></div>
        <div class="eyebrow">Личный кабинет</div>
        <h1><?= htmlspecialchars($user['nickname']) ?></h1>
        <p>
            <?php if (auth_is_admin()): ?>
                У тебя есть доступ к админ-панели. Уровень: <?= (int) $user['admin_level'] ?>.
            <?php else: ?>
                Привет! Здесь можно активировать бонус-коды и следить за аккаунтом.
            <?php endif; ?>
        </p>
    </div>

    <nav class="lk-tabs glass reveal delay-1">
        <a href="lk.php?tab=profile"
           class="lk-tab<?= $tab === 'profile' ? ' active' : '' ?>">
            <i class="ph-fill ph-identification-card"></i> Профиль
        </a>
        <?php if (auth_is_admin()): ?>
            <a href="lk.php?tab=admin"
               class="lk-tab<?= $tab === 'admin' ? ' active' : '' ?>">
                <i class="ph-fill ph-shield-star"></i> Админ-панель
                <span class="badge">Admin</span>
            </a>
            <a href="lk.php?tab=admin_codes"
               class="lk-tab<?= $tab === 'admin_codes' ? ' active' : '' ?>">
                <i class="ph-fill ph-ticket"></i> Бонус-коды
            </a>
        <?php endif; ?>
    </nav>

    <?php if ($tab === 'profile'): ?>
        <!-- ============== ПРОФИЛЬ ============== -->
        <div class="profile-grid reveal delay-2">
            <div class="profile-card glass">
                <div class="pcard-eyebrow">Аккаунт</div>
                <div class="pcard-title">Информация</div>
                <div class="info-row">
                    <span class="k">Никнейм</span>
                    <span class="v"><?= htmlspecialchars($user['nickname']) ?></span>
                </div>
                <div class="info-row">
                    <span class="k">Уровень админа</span>
                    <span class="v">
                        <?php if (auth_is_admin()): ?>
                            <span class="badge-admin"><i class="ph-fill ph-shield-star"></i> <?= (int) $user['admin_level'] ?></span>
                        <?php elseif ((int) $user['admin_level'] > 0): ?>
                            <?= (int) $user['admin_level'] ?>
                        <?php else: ?>
                            Игрок
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="k">Сервер</span>
                    <span class="v"><?= htmlspecialchars($c['server']['name']) ?></span>
                </div>

                <div class="logout-row">
                    <form method="POST" action="lk.php">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <button type="submit" class="logout-btn">
                            <i class="ph ph-sign-out"></i> Выйти
                        </button>
                    </form>
                </div>
            </div>

            <div class="profile-card glass">
                <div class="pcard-eyebrow">Бонусы</div>
                <div class="pcard-title">Активировать код</div>
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 14px;">
                    Введи бонус-код — монеты сразу зачислятся на твой игровой аккаунт.
                </p>
                <form method="POST" action="lk.php">
                    <input type="hidden" name="action" value="redeem">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="redeem-input-wrap">
                        <div class="field-input">
                            <i class="ph ph-ticket"></i>
                            <input type="text" name="code" placeholder="ABCD-1234" required maxlength="32">
                        </div>
                        <button type="submit" class="submit-btn">
                            <i class="ph ph-arrow-right"></i> Активировать
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($tab === 'admin'): ?>
        <!-- ============== АДМИН: главная ============== -->
        <div class="profile-card glass reveal delay-2" style="text-align:center; padding: 40px 28px;">
            <div style="font-size:42px; color:var(--accent); margin-bottom:14px;">
                <i class="ph-fill ph-shield-star"></i>
            </div>
            <h2 style="font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:24px; letter-spacing:-0.02em; margin-bottom:8px;">Добро пожаловать, админ</h2>
            <p style="color:var(--text-secondary); max-width:480px; margin:0 auto 20px; font-size:14px;">
                Здесь будут собираться все админ-инструменты. Сейчас доступен раздел «Бонус-коды».
            </p>
            <a href="lk.php?tab=admin_codes" class="btn btn-primary">
                <i class="ph-fill ph-ticket"></i> Перейти к бонус-кодам
            </a>
        </div>

    <?php elseif ($tab === 'admin_codes'): ?>
        <!-- ============== АДМИН: бонус-коды ============== -->
        <div class="admin-grid reveal delay-2">

            <!-- Левый столбец: форма создания -->
            <div class="admin-card glass">
                <div class="acard-eyebrow">Новый код</div>
                <h3>Создать бонус-код</h3>

                <form method="POST" action="lk.php" autocomplete="off" id="codeForm">
                    <input type="hidden" name="action" value="create_code">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

                    <div class="field">
                        <div class="field-label">Код (или оставь пустым)</div>
                        <div class="field-input">
                            <i class="ph ph-hash"></i>
                            <input type="text" name="code" placeholder="ABCD-1234"
                                   pattern="[A-Za-z0-9_-]{1,32}" maxlength="32" id="codeInput">
                            <button type="button" class="pwd-toggle" onclick="genCode()" tabindex="-1" title="Сгенерировать"><i class="ph ph-shuffle"></i></button>
                        </div>
                        <div class="copy-hint">Пусто = сгенерируется автоматически (длина из конфига).</div>
                    </div>

                    <div class="prizes-block">
                        <div class="prizes-head">
                            <span>Призы (до <?= BONUS_PRIZES_MAX ?>)</span>
                            <button type="button" class="prize-add" onclick="addPrize()" id="addPrizeBtn">
                                <i class="ph ph-plus"></i> Ещё приз
                            </button>
                        </div>
                        <div id="prizesList"></div>
                    </div>

                    <!-- Шаблон одного приза, клонируется в JS -->
                    <template id="prizeTpl">
                        <div class="prize-row">
                            <div class="prize-row-head">
                                <span class="prize-row-num">Приз #<span data-num>1</span></span>
                                <button type="button" class="prize-del" data-del title="Удалить">
                                    <i class="ph ph-x"></i>
                                </button>
                            </div>

                            <div class="field">
                                <div class="field-label">Тип приза</div>
                                <div class="field-input">
                                    <i class="ph ph-gift"></i>
                                    <select name="prize_type[]" data-type required>
                                        <?php foreach (bonus_prize_types() as $pid => $plabel): ?>
                                            <option value="<?= (int) $pid ?>"<?= $pid === BONUS_PRIZE_DONATE ? ' selected' : '' ?>>
                                                <?= htmlspecialchars($plabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="field" data-value-field>
                                <div class="field-label" data-value-label>Количество</div>
                                <div class="field-input">
                                    <i class="ph-fill ph-coin"></i>
                                    <input type="number" name="prize_value[]" data-value
                                           min="1" max="1000000000" step="1" placeholder="500">
                                </div>
                            </div>

                            <div class="form-row" data-item-fields style="display:none;">
                                <div class="field">
                                    <div class="field-label">ID предмета</div>
                                    <div class="field-input">
                                        <i class="ph ph-package"></i>
                                        <input type="number" name="item_id[]" data-itemid
                                               min="1" max="100000" step="1" placeholder="15">
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="field-label">Количество</div>
                                    <div class="field-input">
                                        <i class="ph ph-stack"></i>
                                        <input type="number" name="item_qty[]" data-itemqty
                                               min="1" max="1000000" step="1" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="form-row">
                        <div class="field">
                            <div class="field-label">Лимит использований</div>
                            <div class="field-input">
                                <i class="ph ph-users-three"></i>
                                <input type="number" name="usage_limit" required min="1" max="1000000" step="1" value="1">
                            </div>
                        </div>
                        <div class="field">
                            <div class="field-label">Истекает (необязательно)</div>
                            <div class="field-input">
                                <i class="ph ph-clock"></i>
                                <input type="datetime-local" name="expires_at">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="ph-fill ph-plus-circle"></i> Создать код
                    </button>
                </form>
            </div>

            <!-- Правый столбец: таблица -->
            <div class="codes-table glass">
                <?php if (empty($adminCodes)): ?>
                    <div class="codes-list-empty">
                        <i class="ph-fill ph-ticket"></i>
                        Пока нет ни одного бонус-кода. Создай первый слева!
                    </div>
                <?php else: ?>
                    <div class="codes-head">
                        <div>Код</div>
                        <div>Приз</div>
                        <div>Лимит</div>
                        <div class="h-meta">Создан</div>
                        <div></div>
                    </div>
                    <?php foreach ($adminCodes as $bc): ?>
                        <?php
                        $expired = $bc['expires_at'] !== null && strtotime($bc['expires_at']) < time();
                        $isFull  = (int) $bc['used_count'] >= (int) $bc['usage_limit'];
                        $bcCode  = (string) $bc['code'];
                        $allDescr = bonus_prizes_describe(
                            $bc['_types'] ?? [$bc['prize_type'] ?? 0],
                            $bc['_vals']  ?? [$bc['prize_type'] === BONUS_PRIZE_ITEM ? ($bc['prize_value'] ?? 0) : 0],
                            $bc['_amts']  ?? [$bc['prize_type'] === BONUS_PRIZE_ITEM ? ($bc['prize_extra'] ?? 0) : ($bc['prize_value'] ?? 0)]
                        );
                        $prizeCount = 0;
                        foreach (($bc['_types'] ?? []) as $tt) { if ((int)$tt > 0) $prizeCount++; }
                        if ($prizeCount === 0) $prizeCount = 1;
                        ?>
                        <div class="code-row">
                            <div>
                                <div class="c-code"><?= htmlspecialchars($bcCode) ?></div>
                                <div class="c-meta">
                                    <?php if ($expired): ?>
                                        <span style="color:var(--danger);">истёк</span>
                                    <?php elseif ($isFull): ?>
                                        <span style="color:var(--warn);">исчерпан</span>
                                    <?php elseif ($bc['expires_at']): ?>
                                        до <?= htmlspecialchars(date('d.m.Y', strtotime($bc['expires_at']))) ?>
                                    <?php else: ?>
                                        бессрочный
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="c-coins" title="<?= htmlspecialchars($allDescr) ?>">
                                <?= htmlspecialchars($allDescr) ?>
                                <?php if ($prizeCount > 1): ?>
                                    <div class="c-meta"><?= $prizeCount ?> приз<?= $prizeCount >= 2 && $prizeCount <= 4 ? 'а' : 'ов' ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="c-limit"><?= (int) $bc['used_count'] ?> / <?= (int) $bc['usage_limit'] ?></div>
                            <div class="c-meta">
                                <?= htmlspecialchars(date('d.m.Y', strtotime($bc['created_at']))) ?><br>
                                <?= htmlspecialchars($bc['created_by']) ?>
                            </div>
                            <div class="c-act">
                                <form method="POST" action="lk.php" onsubmit="return confirm('Удалить код «<?= htmlspecialchars($bcCode) ?>»?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_code">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="code" value="<?= htmlspecialchars($bcCode) ?>">
                                    <button type="submit" title="Удалить"><i class="ph-fill ph-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <script>
            const PRIZE_LABELS = {
                1: 'Сумма (деньги)',
                2: 'Сумма (донат)',
                3: 'Кол-во EXP',
                5: 'Кол-во слотов'
            };
            const PRIZE_TYPE_ITEM = 4;
            const PRIZES_MAX = <?= (int) BONUS_PRIZES_MAX ?>;

            const list   = document.getElementById('prizesList');
            const tpl    = document.getElementById('prizeTpl');
            const addBtn = document.getElementById('addPrizeBtn');

            function refreshNumbers() {
                const rows = list.querySelectorAll('.prize-row');
                rows.forEach((row, idx) => {
                    row.querySelector('[data-num]').textContent = String(idx + 1);
                    /* Удаление разрешено только если строк больше одной */
                    row.querySelector('[data-del]').style.display = rows.length > 1 ? '' : 'none';
                });
                addBtn.disabled = rows.length >= PRIZES_MAX;
                addBtn.style.opacity = rows.length >= PRIZES_MAX ? '0.45' : '';
            }

            function syncPrizeRow(row) {
                const type   = parseInt(row.querySelector('[data-type]').value, 10);
                const vField = row.querySelector('[data-value-field]');
                const vInput = row.querySelector('[data-value]');
                const vLabel = row.querySelector('[data-value-label]');
                const iFields= row.querySelector('[data-item-fields]');
                const iId    = row.querySelector('[data-itemid]');
                const iQty   = row.querySelector('[data-itemqty]');

                if (type === PRIZE_TYPE_ITEM) {
                    vField.style.display = 'none';
                    iFields.style.display = '';
                    vInput.required = false;
                    vInput.value = '';
                    iId.required = true;
                    iQty.required = true;
                } else {
                    vField.style.display = '';
                    iFields.style.display = 'none';
                    vInput.required = true;
                    iId.required = false;
                    iQty.required = false;
                    vLabel.textContent = PRIZE_LABELS[type] || 'Количество';
                }
            }

            function addPrize() {
                if (list.children.length >= PRIZES_MAX) return;
                const node = tpl.content.firstElementChild.cloneNode(true);

                node.querySelector('[data-type]').addEventListener('change', () => syncPrizeRow(node));
                node.querySelector('[data-del]').addEventListener('click', () => {
                    node.remove();
                    refreshNumbers();
                });

                list.appendChild(node);
                syncPrizeRow(node);
                refreshNumbers();
            }

            /* Стартовый приз */
            addPrize();

            function genCode() {
                const al = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
                let c = '';
                for (let i = 0; i < <?= (int) ($config['auth']['bonus_code_len'] ?? 10) ?>; i++) {
                    c += al[Math.floor(Math.random() * al.length)];
                }
                document.getElementById('codeInput').value = c;
            }
        </script>

    <?php endif; ?>
<?php endif; ?>

</section>

<?php render_footer(); ?>
<?php render_common_js(); ?>
</body>
</html>
