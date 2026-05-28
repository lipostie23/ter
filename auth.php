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

function auth_ensure_bonus_codes_table(): void
{
    static $ready = false;
    if ($ready) return;
    db_pdo()->exec(
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
    db_pdo()->exec(
        'CREATE TABLE IF NOT EXISTS `BonusCodeUsage` (
            `code` VARCHAR(32) NOT NULL,
            `nickname` VARCHAR(64) NOT NULL,
            `used_at` DATETIME NOT NULL,
            PRIMARY KEY (`code`, `nickname`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
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
 * @throws RuntimeException
 */
function bonus_create(string $code, int $coins, int $usageLimit, ?string $expiresAt, string $admin): void
{
    auth_ensure_bonus_codes_table();

    if ($code === '' || strlen($code) > 32) {
        throw new RuntimeException('Код должен быть от 1 до 32 символов.');
    }
    if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
        throw new RuntimeException('Код содержит недопустимые символы. Разрешены A–Z, 0–9, _, -.');
    }
    if ($coins < 1 || $coins > 1000000) {
        throw new RuntimeException('Сумма монет должна быть от 1 до 1 000 000.');
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

    $stmt = db_pdo()->prepare(
        "INSERT INTO `BonusCodes` (`code`, `coins`, `usage_limit`, `used_count`, `created_by`, `created_at`, `expires_at`)
         VALUES (:c, :coins, :lim, 0, :admin, NOW(), :exp)"
    );
    $stmt->execute([
        ':c'     => $code,
        ':coins' => $coins,
        ':lim'   => $usageLimit,
        ':admin' => $admin,
        ':exp'   => $expiresAt,
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
 * Активация: проверяет код, начисляет монеты в players.bonus_coins_col по нику пользователя,
 * создаёт запись в BonusCodeUsage, инкрементит used_count.
 * Возвращает массив [ok=>bool, message=>string, coins=>int].
 */
function bonus_redeem(string $code, string $nickname): array
{
    global $config;
    auth_ensure_bonus_codes_table();

    $row = bonus_get($code);
    if (!$row) {
        return ['ok' => false, 'message' => 'Код не найден.', 'coins' => 0];
    }
    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'Срок действия кода истёк.', 'coins' => 0];
    }
    if ((int) $row['used_count'] >= (int) $row['usage_limit']) {
        return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'coins' => 0];
    }

    /* Один и тот же ник не может активировать тот же код дважды */
    $check = db_pdo()->prepare(
        "SELECT 1 FROM `BonusCodeUsage` WHERE `code` = :c AND LOWER(`nickname`) = LOWER(:n) LIMIT 1"
    );
    $check->execute([':c' => $code, ':n' => $nickname]);
    if ($check->fetchColumn()) {
        return ['ok' => false, 'message' => 'Вы уже активировали этот код.', 'coins' => 0];
    }

    $coins   = (int) $row['coins'];
    $coinCol = auth_safe_ident((string) $config['auth']['bonus_coins_col']);
    $tbl     = auth_safe_ident((string) $config['auth']['player_table']);
    $nickCol = auth_safe_ident((string) $config['auth']['player_nick_col']);

    $pdo = db_pdo();
    $pdo->beginTransaction();
    try {
        /* Атомарно инкрементим used_count, проверяя лимит ещё раз */
        $upd = $pdo->prepare(
            "UPDATE `BonusCodes`
             SET `used_count` = `used_count` + 1
             WHERE `code` = :c AND `used_count` < `usage_limit`"
        );
        $upd->execute([':c' => $code]);
        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'coins' => 0];
        }

        $usage = $pdo->prepare(
            "INSERT INTO `BonusCodeUsage` (`code`, `nickname`, `used_at`) VALUES (:c, :n, NOW())"
        );
        $usage->execute([':c' => $code, ':n' => $nickname]);

        $give = $pdo->prepare(
            "UPDATE `{$tbl}`
             SET `{$coinCol}` = `{$coinCol}` + :coins
             WHERE LOWER(`{$nickCol}`) = LOWER(:n)
             LIMIT 1"
        );
        $give->execute([':coins' => $coins, ':n' => $nickname]);
        if ($give->rowCount() === 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Игровой аккаунт не найден.', 'coins' => 0];
        }

        $pdo->commit();
        return ['ok' => true, 'message' => 'Зачислено!', 'coins' => $coins];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => 'Ошибка БД, попробуйте ещё раз.', 'coins' => 0];
    }
}
