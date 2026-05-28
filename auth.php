<?php
declare(strict_types=1);

/**
 * Auth + кабинет: сессии, CSRF, проверка пароля игрового аккаунта,
 * лимит попыток входа, таблица бонус-кодов и операции с ней.
 */

if (!isset($config)) {
    require_once __DIR__ . '/config.php';
}

/* =========================================================================
 *  Сессия
 * ========================================================================= */

function auth_start(): void
{
    static $started = false;
    if ($started) return;

    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    $started = true;

    /* Авто-выход по бездействию */
    global $config;
    $maxIdle = (int) ($config['auth']['session_max_idle'] ?? 86400);
    if (!empty($_SESSION['auth']['logged_at']) && (time() - (int) $_SESSION['auth']['logged_at']) > $maxIdle) {
        auth_logout();
    } elseif (!empty($_SESSION['auth'])) {
        $_SESSION['auth']['logged_at'] = time();
    }
}

function auth_user(): ?array
{
    auth_start();
    if (empty($_SESSION['auth']['nickname'])) return null;
    return $_SESSION['auth'];
}

function auth_admin_level(): int
{
    $u = auth_user();
    return $u ? (int) ($u['admin_level'] ?? 0) : 0;
}

function auth_is_admin(): bool
{
    global $config;
    $min = (int) ($config['auth']['admin_min_level'] ?? 100);
    return auth_admin_level() > $min;
}

function auth_login(string $nickname, int $adminLevel): void
{
    auth_start();
    session_regenerate_id(true);
    $_SESSION['auth'] = [
        'nickname'    => $nickname,
        'admin_level' => $adminLevel,
        'logged_at'   => time(),
    ];
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/* =========================================================================
 *  CSRF
 * ========================================================================= */

function csrf_token(): string
{
    auth_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    auth_start();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* =========================================================================
 *  Flash-сообщения (между POST → redirect → GET)
 * ========================================================================= */

function flash_set(string $kind, string $message): void
{
    auth_start();
    $_SESSION['flash'] = ['kind' => $kind, 'message' => $message];
}

function flash_pop(): ?array
{
    auth_start();
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/* =========================================================================
 *  Безопасный валидатор для имён колонок и таблиц
 * ========================================================================= */

function auth_safe_ident(string $s): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $s)) {
        throw new RuntimeException('Auth: invalid identifier "' . $s . '"');
    }
    return $s;
}

/* =========================================================================
 *  Проверка пароля игрового аккаунта
 * ========================================================================= */

/**
 * Сравнивает введённый пароль со значением, как оно хранится в players.password_col.
 * Поддерживаются распространённые в SAMP-сборках варианты — формат задаётся в config.
 */
function auth_verify_password(string $stored, string $given, string $algo): bool
{
    $stored = (string) $stored;
    $given  = (string) $given;
    if ($stored === '') return false;

    switch (strtolower($algo)) {
        case 'bcrypt':
            return password_verify($given, $stored);

        case 'md5':
            return hash_equals(strtolower($stored), strtolower(md5($given)));

        case 'sha1':
            return hash_equals(strtolower($stored), strtolower(sha1($given)));

        case 'sha256':
            return hash_equals(strtolower($stored), strtolower(hash('sha256', $given)));

        case 'whirlpool':
            return hash_equals(strtolower($stored), strtolower(hash('whirlpool', $given)));

        case 'plain':
            return hash_equals($stored, $given);

        default:
            throw new RuntimeException('Auth: unknown password_hash "' . $algo . '"');
    }
}

/**
 * Тянет из players: ник (с правильным регистром), захэш. пароль, уровень админа.
 * Возвращает null, если игрока с таким ником нет.
 */
function auth_fetch_player_row(string $nickname): ?array
{
    global $config;
    $a = $config['auth'];

    $tbl   = auth_safe_ident((string) $a['player_table']);
    $nickC = auth_safe_ident((string) $a['player_nick_col']);
    $passC = auth_safe_ident((string) $a['player_password_col']);
    $admC  = auth_safe_ident((string) $a['player_admin_col']);

    $stmt = db_pdo()->prepare(
        "SELECT `{$nickC}` AS nick, `{$passC}` AS pass, `{$admC}` AS admin_level
         FROM `{$tbl}`
         WHERE LOWER(`{$nickC}`) = LOWER(:n)
         LIMIT 1"
    );
    $stmt->execute([':n' => $nickname]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* =========================================================================
 *  Лимит попыток входа (per IP)
 * ========================================================================= */

function auth_ensure_login_attempts_table(): void
{
    static $ready = false;
    if ($ready) return;
    db_pdo()->exec(
        'CREATE TABLE IF NOT EXISTS `LkLoginAttempts` (
            `ip` VARCHAR(45) NOT NULL,
            `attempted_at` DATETIME NOT NULL,
            `success` TINYINT(1) NOT NULL DEFAULT 0,
            KEY `idx_ip_time` (`ip`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ready = true;
}

function auth_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * @return bool true — можно пробовать, false — заблокирован.
 */
function auth_throttle_check(int $maxFailed = 5, int $windowMin = 15): bool
{
    auth_ensure_login_attempts_table();
    $stmt = db_pdo()->prepare(
        "SELECT COUNT(*) FROM `LkLoginAttempts`
         WHERE `ip` = :ip AND `success` = 0
           AND `attempted_at` > (NOW() - INTERVAL :win MINUTE)"
    );
    $stmt->bindValue(':ip',  auth_client_ip(), PDO::PARAM_STR);
    $stmt->bindValue(':win', $windowMin, PDO::PARAM_INT);
    $stmt->execute();
    return ((int) $stmt->fetchColumn()) < $maxFailed;
}

function auth_throttle_record(bool $success): void
{
    auth_ensure_login_attempts_table();
    $stmt = db_pdo()->prepare(
        "INSERT INTO `LkLoginAttempts` (`ip`, `attempted_at`, `success`)
         VALUES (:ip, NOW(), :s)"
    );
    $stmt->bindValue(':ip', auth_client_ip(), PDO::PARAM_STR);
    $stmt->bindValue(':s',  $success ? 1 : 0,  PDO::PARAM_INT);
    $stmt->execute();

    // Сборка мусора: периодически чистим записи старше 24ч
    if (random_int(1, 50) === 1) {
        db_pdo()->exec("DELETE FROM `LkLoginAttempts` WHERE `attempted_at` < (NOW() - INTERVAL 24 HOUR)");
    }
}

/* =========================================================================
 *  Бонус-коды
 * ========================================================================= */

/* Типы призов — должны совпадать со значениями в селекте на форме создания */
const BONUS_PRIZE_MONEY        = 1; // Деньги
const BONUS_PRIZE_DONATE       = 2; // Донат (Cash_Donate)
const BONUS_PRIZE_EXP          = 3; // EXP / Score
const BONUS_PRIZE_ITEM         = 4; // Предмет (id + количество)
const BONUS_PRIZE_HOUSE_SLOT   = 5; // Слот на имущество

function bonus_prize_label(int $type): string
{
    switch ($type) {
        case BONUS_PRIZE_MONEY:      return 'Деньги';
        case BONUS_PRIZE_DONATE:     return 'Донат';
        case BONUS_PRIZE_EXP:        return 'EXP';
        case BONUS_PRIZE_ITEM:       return 'Предмет';
        case BONUS_PRIZE_HOUSE_SLOT: return 'Слот на имущество';
    }
    return 'Неизвестно';
}

function bonus_prize_types(): array
{
    return [
        BONUS_PRIZE_MONEY      => 'Деньги',
        BONUS_PRIZE_DONATE     => 'Донат',
        BONUS_PRIZE_EXP        => 'EXP',
        BONUS_PRIZE_ITEM       => 'Предмет',
        BONUS_PRIZE_HOUSE_SLOT => 'Слот на имущество',
    ];
}

/**
 * Человекочитаемое описание приза для UI: "1 500 монет (Донат)" или "Предмет ID 15 ×5".
 */
function bonus_prize_describe(int $type, int $value, int $extra): string
{
    if ($type === BONUS_PRIZE_ITEM) {
        return 'Предмет ID ' . $value . ' ×' . $extra;
    }
    $num = number_format($value, 0, '.', ' ');
    return $num . ' — ' . bonus_prize_label($type);
}

function auth_ensure_bonus_codes_table(): void
{
    static $ready = false;
    if ($ready) return;

    $pdo = db_pdo();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `BonusCodes` (
            `code`        VARCHAR(32) NOT NULL,
            `coins`       INT NOT NULL DEFAULT 0,
            `usage_limit` INT NOT NULL DEFAULT 1,
            `used_count`  INT NOT NULL DEFAULT 0,
            `created_by`  VARCHAR(64) NOT NULL DEFAULT \'\',
            `created_at`  DATETIME NOT NULL,
            `expires_at`  DATETIME NULL,
            PRIMARY KEY (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Миграция: добавляем колонки типа приза, если их ещё нет.
       MySQL до 8.0.29 не понимает IF NOT EXISTS у ADD COLUMN, поэтому ловим ошибку. */
    $alters = [
        "ALTER TABLE `BonusCodes` ADD COLUMN `prize_type`  TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER `coins`",
        "ALTER TABLE `BonusCodes` ADD COLUMN `prize_value` INT NOT NULL DEFAULT 0 AFTER `prize_type`",
        "ALTER TABLE `BonusCodes` ADD COLUMN `prize_extra` INT NOT NULL DEFAULT 0 AFTER `prize_value`",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (\Throwable $e) { /* колонка уже есть */ }
    }

    /* Перенос legacy-данных: для старых кодов prize_value пуст, но coins>0 — это донат */
    try {
        $pdo->exec(
            "UPDATE `BonusCodes`
             SET `prize_value` = `coins`, `prize_type` = " . BONUS_PRIZE_DONATE . "
             WHERE `prize_value` = 0 AND `coins` > 0"
        );
    } catch (\Throwable $e) { /* не критично */ }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `BonusCodeUsage` (
            `code` VARCHAR(32) NOT NULL,
            `nickname` VARCHAR(64) NOT NULL,
            `used_at` DATETIME NOT NULL,
            PRIMARY KEY (`code`, `nickname`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Очередь предметов: сюда складываются награды типа «Предмет», игровой мод их вычитывает. */
    global $config;
    $itemsTable = auth_safe_ident((string) ($config['auth']['bonus_items_table'] ?? 'BonusPendingItems'));
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$itemsTable}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nickname` VARCHAR(64) NOT NULL,
            `item_id` INT NOT NULL,
            `quantity` INT NOT NULL,
            `source` VARCHAR(64) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            `delivered` TINYINT(1) NOT NULL DEFAULT 0,
            `delivered_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_nick_pending` (`nickname`, `delivered`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ready = true;
}

function bonus_generate_code(int $len = 10): string
{
    // без 0/O/I/1/L — чтобы не перепутать
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max  = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function bonus_normalize_code(string $code): string
{
    return strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]/', '', $code) ?? ''));
}

function bonus_list(int $limit = 100): array
{
    auth_ensure_bonus_codes_table();
    $limit = max(1, min(500, $limit));
    return db_pdo()->query(
        "SELECT * FROM `BonusCodes` ORDER BY `created_at` DESC LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function bonus_get(string $code): ?array
{
    auth_ensure_bonus_codes_table();
    $stmt = db_pdo()->prepare("SELECT * FROM `BonusCodes` WHERE `code` = :c LIMIT 1");
    $stmt->execute([':c' => $code]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Создаёт бонус-код с произвольным типом приза.
 *
 *  $prizeType  — одна из констант BONUS_PRIZE_*
 *  $prizeValue — для денег/доната/exp/слотов это «количество», для предмета это item ID
 *  $prizeExtra — используется только для предмета (количество); для остальных — игнорируется
 *
 * @throws RuntimeException
 */
function bonus_create(
    string $code,
    int $prizeType,
    int $prizeValue,
    int $prizeExtra,
    int $usageLimit,
    ?string $expiresAt,
    string $admin
): void {
    auth_ensure_bonus_codes_table();

    if ($code === '' || strlen($code) > 32) {
        throw new RuntimeException('Код должен быть от 1 до 32 символов.');
    }
    if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
        throw new RuntimeException('Код содержит недопустимые символы. Разрешены A–Z, 0–9, _, -.');
    }
    if (!array_key_exists($prizeType, bonus_prize_types())) {
        throw new RuntimeException('Неизвестный тип приза.');
    }

    if ($prizeType === BONUS_PRIZE_ITEM) {
        if ($prizeValue < 1 || $prizeValue > 100000) {
            throw new RuntimeException('ID предмета должен быть в диапазоне 1–100000.');
        }
        if ($prizeExtra < 1 || $prizeExtra > 1000000) {
            throw new RuntimeException('Количество предметов — от 1 до 1 000 000.');
        }
    } else {
        if ($prizeValue < 1 || $prizeValue > 1000000000) {
            throw new RuntimeException('Количество должно быть от 1 до 1 000 000 000.');
        }
        $prizeExtra = 0;
    }

    if ($usageLimit < 1 || $usageLimit > 1000000) {
        throw new RuntimeException('Лимит использований — от 1 до 1 000 000.');
    }
    if ($expiresAt !== null && strtotime($expiresAt) === false) {
        throw new RuntimeException('Неверный формат даты истечения.');
    }
    if (bonus_get($code) !== null) {
        throw new RuntimeException('Код уже существует.');
    }

    /* coins оставляем для обратной совместимости с UI/старыми скриптами:
       для типа «Донат» дублируем туда prize_value, для остальных — 0. */
    $coinsLegacy = $prizeType === BONUS_PRIZE_DONATE ? $prizeValue : 0;

    $stmt = db_pdo()->prepare(
        "INSERT INTO `BonusCodes`
            (`code`, `coins`, `prize_type`, `prize_value`, `prize_extra`,
             `usage_limit`, `used_count`, `created_by`, `created_at`, `expires_at`)
         VALUES
            (:c, :coins, :ptype, :pval, :pextra,
             :lim, 0, :admin, NOW(), :exp)"
    );
    $stmt->execute([
        ':c'      => $code,
        ':coins'  => $coinsLegacy,
        ':ptype'  => $prizeType,
        ':pval'   => $prizeValue,
        ':pextra' => $prizeExtra,
        ':lim'    => $usageLimit,
        ':admin'  => $admin,
        ':exp'    => $expiresAt,
    ]);
}

function bonus_delete(string $code): bool
{
    auth_ensure_bonus_codes_table();
    $stmt = db_pdo()->prepare("DELETE FROM `BonusCodes` WHERE `code` = :c");
    $stmt->execute([':c' => $code]);
    return $stmt->rowCount() > 0;
}

/**
 * Внутренний дispatch выдачи приза. Возвращает true при успехе.
 * Для предметов кладёт запись в очередь BonusPendingItems — игровой мод её разберёт.
 */
function bonus_apply_prize(PDO $pdo, string $nickname, int $type, int $value, int $extra, string $code): bool
{
    global $config;

    if ($type === BONUS_PRIZE_ITEM) {
        $itemsTable = auth_safe_ident((string) ($config['auth']['bonus_items_table'] ?? 'BonusPendingItems'));
        $ins = $pdo->prepare(
            "INSERT INTO `{$itemsTable}` (`nickname`, `item_id`, `quantity`, `source`, `created_at`)
             VALUES (:n, :iid, :qty, :src, NOW())"
        );
        $ins->execute([
            ':n'   => $nickname,
            ':iid' => $value,
            ':qty' => $extra,
            ':src' => 'bonus_code:' . $code,
        ]);
        return $ins->rowCount() > 0;
    }

    /* Все остальные типы — это обычный UPDATE одной колонки в players */
    $colKey = [
        BONUS_PRIZE_MONEY      => 'bonus_money_col',
        BONUS_PRIZE_DONATE     => 'bonus_coins_col',
        BONUS_PRIZE_EXP        => 'bonus_exp_col',
        BONUS_PRIZE_HOUSE_SLOT => 'bonus_house_slots_col',
    ][$type] ?? null;

    if ($colKey === null) return false;

    $colName = (string) ($config['auth'][$colKey] ?? '');
    if ($colName === '') {
        throw new RuntimeException('В конфиге не задана колонка ' . $colKey . '.');
    }

    $col   = auth_safe_ident($colName);
    $tbl   = auth_safe_ident((string) $config['auth']['player_table']);
    $nickC = auth_safe_ident((string) $config['auth']['player_nick_col']);

    $upd = $pdo->prepare(
        "UPDATE `{$tbl}`
         SET `{$col}` = `{$col}` + :v
         WHERE LOWER(`{$nickC}`) = LOWER(:n)
         LIMIT 1"
    );
    $upd->execute([':v' => $value, ':n' => $nickname]);
    return $upd->rowCount() > 0;
}

/**
 * Активация: проверяет код, выдаёт приз согласно prize_type, отмечает использование.
 *
 * Возвращает: [
 *   'ok'      => bool,
 *   'message' => string,
 *   'reward'  => string,        // человекочитаемое описание для UI
 *   'type'    => int,           // BONUS_PRIZE_*
 *   'value'   => int,
 *   'extra'   => int,
 * ]
 */
function bonus_redeem(string $code, string $nickname): array
{
    auth_ensure_bonus_codes_table();

    $row = bonus_get($code);
    if (!$row) {
        return ['ok' => false, 'message' => 'Код не найден.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
    }
    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'Срок действия кода истёк.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
    }
    if ((int) $row['used_count'] >= (int) $row['usage_limit']) {
        return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
    }

    /* Один и тот же ник не может активировать тот же код дважды */
    $check = db_pdo()->prepare(
        "SELECT 1 FROM `BonusCodeUsage` WHERE `code` = :c AND LOWER(`nickname`) = LOWER(:n) LIMIT 1"
    );
    $check->execute([':c' => $code, ':n' => $nickname]);
    if ($check->fetchColumn()) {
        return ['ok' => false, 'message' => 'Вы уже активировали этот код.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
    }

    $type  = (int) ($row['prize_type']  ?? BONUS_PRIZE_DONATE);
    $value = (int) ($row['prize_value'] ?? 0);
    $extra = (int) ($row['prize_extra'] ?? 0);

    /* Legacy: если старый код без prize_type, fallback на coins как «Донат» */
    if ($value === 0 && (int) ($row['coins'] ?? 0) > 0) {
        $type  = BONUS_PRIZE_DONATE;
        $value = (int) $row['coins'];
        $extra = 0;
    }

    $pdo = db_pdo();
    $pdo->beginTransaction();
    try {
        /* Атомарно инкрементим used_count, ещё раз проверяя лимит */
        $upd = $pdo->prepare(
            "UPDATE `BonusCodes`
             SET `used_count` = `used_count` + 1
             WHERE `code` = :c AND `used_count` < `usage_limit`"
        );
        $upd->execute([':c' => $code]);
        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
        }

        $usage = $pdo->prepare(
            "INSERT INTO `BonusCodeUsage` (`code`, `nickname`, `used_at`) VALUES (:c, :n, NOW())"
        );
        $usage->execute([':c' => $code, ':n' => $nickname]);

        if (!bonus_apply_prize($pdo, $nickname, $type, $value, $extra, $code)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Игровой аккаунт не найден.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
        }

        $pdo->commit();

        $reward = bonus_prize_describe($type, $value, $extra);
        $msg    = $type === BONUS_PRIZE_ITEM
            ? 'Предмет добавлен в очередь выдачи. Зайди в игру.'
            : 'Зачислено!';

        return ['ok' => true, 'message' => $msg, 'reward' => $reward, 'type' => $type, 'value' => $value, 'extra' => $extra];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => 'Ошибка БД, попробуйте ещё раз.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
    }
}
