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
 *  Бонус-коды (формат Promocodes игрового мода)
 *
 *  Хранение: одна строка в `Promocodes` на код.
 *      Name        — сам код
 *      Data_0      — CSV из 10 типов призов  (например "1,2,4,0,0,0,0,0,0,0,")
 *      Data_1      — CSV из 10 ID предметов (для типа 4) / 0 для остальных
 *      Data_2      — CSV из 10 количеств / сумм
 *      Activation  — лимит активаций
 *      Minutes     — время жизни в минутах (контролируется игровым модом)
 *
 *  Учёт использования — в таблице `Promocodes_Used` (имя/колонки в config).
 * ========================================================================= */

const BONUS_PRIZE_NONE       = 0;
const BONUS_PRIZE_MONEY      = 1; // Деньги
const BONUS_PRIZE_DONATE     = 2; // Донат (Cash_Donate)
const BONUS_PRIZE_EXP        = 3; // EXP / Score
const BONUS_PRIZE_ITEM       = 4; // Предмет (id + количество)
const BONUS_PRIZE_HOUSE_SLOT = 5; // Слот на имущество

const BONUS_PRIZE_MAX_SLOTS  = 10;

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

/** Описание одного приза для UI/flash: «1 500 — Донат» или «Предмет ID 15 ×5». */
function bonus_prize_describe(int $type, int $value, int $extra): string
{
    if ($type === BONUS_PRIZE_ITEM) {
        return 'Предмет ID ' . $value . ' ×' . $extra;
    }
    return number_format($value, 0, '.', ' ') . ' — ' . bonus_prize_label($type);
}

/** Объединяет описания всех призов в одну строку. */
function bonus_prizes_describe(array $prizes): string
{
    if (empty($prizes)) return '—';
    $parts = [];
    foreach ($prizes as $p) {
        $parts[] = bonus_prize_describe((int) $p['type'], (int) $p['value'], (int) $p['extra']);
    }
    return implode('; ', $parts);
}

/** Парсит "1,2,4,0,0,..." → массив 10 int (с допиской нулей). */
function bonus_parse_csv_slots(string $csv): array
{
    $parts = explode(',', $csv);
    $out = [];
    for ($i = 0; $i < BONUS_PRIZE_MAX_SLOTS; $i++) {
        $out[] = isset($parts[$i]) && $parts[$i] !== '' ? (int) $parts[$i] : 0;
    }
    return $out;
}

/** Превращает массив 10 int → "1,2,4,0,0,0,0,0,0,0," (с trailing-запятой как в Pawn-варианте). */
function bonus_build_csv_slots(array $slots): string
{
    $padded = $slots;
    while (count($padded) < BONUS_PRIZE_MAX_SLOTS) $padded[] = 0;
    $padded = array_slice($padded, 0, BONUS_PRIZE_MAX_SLOTS);
    return implode(',', array_map('intval', $padded)) . ',';
}

/**
 * Декодирует Data_0/1/2 одной строки Promocodes в массив призов:
 *   [ ['type'=>1,'value'=>500,'extra'=>0], ['type'=>4,'value'=>15,'extra'=>5], ... ].
 */
function bonus_decode_prizes(array $row): array
{
    $d0 = bonus_parse_csv_slots((string) ($row['Data_0'] ?? ''));
    $d1 = bonus_parse_csv_slots((string) ($row['Data_1'] ?? ''));
    $d2 = bonus_parse_csv_slots((string) ($row['Data_2'] ?? ''));

    $prizes = [];
    for ($i = 0; $i < BONUS_PRIZE_MAX_SLOTS; $i++) {
        $type = $d0[$i];
        if ($type <= 0) continue;
        if ($type === BONUS_PRIZE_ITEM) {
            $prizes[] = ['type' => $type, 'value' => $d1[$i], 'extra' => $d2[$i]];
        } else {
            // у не-предметов количество лежит в Data_2, Data_1 = 0
            $prizes[] = ['type' => $type, 'value' => $d2[$i], 'extra' => 0];
        }
    }
    return $prizes;
}

/**
 * Сериализует массив значений в формат `Promocodes.Data_X`: ровно 10 чисел через
 * запятую, c обязательной завершающей запятой ("1,4,4,4,0,0,0,0,0,0,").
 * Если массив короче 10 — добивается нулями. Если длиннее — обрезается.
 */
function bonus_pack_data(array $values): string
{
    $out = [];
    for ($i = 0; $i < 10; $i++) {
        $out[] = (int) ($values[$i] ?? 0);
    }
    return implode(',', $out) . ',';
}

/**
 * Разбирает строку вида "1,4,4,4,0,0,0,0,0,0," в массив из 10 целых чисел.
 */
function bonus_unpack_data(?string $csv): array
{
    $csv = (string) $csv;
    $parts = array_values(array_filter(
        array_map('trim', explode(',', $csv)),
        static fn($v) => $v !== ''
    ));
    $out = [];
    for ($i = 0; $i < 10; $i++) {
        $out[] = (int) ($parts[$i] ?? 0);
    }
    return $out;
}

function auth_ensure_bonus_codes_table(): void
{
    static $ready = false;
    if ($ready) return;

    global $config;
    $pdo = db_pdo();

    $pTable     = auth_safe_ident((string) ($config['auth']['promo_table']      ?? 'Promocodes'));
    $usedTable  = auth_safe_ident((string) ($config['auth']['promo_used_table'] ?? 'Promocodes_Used'));
    $usedName   = auth_safe_ident((string) ($config['auth']['promo_used_name_col'] ?? 'Name'));
    $usedNick   = auth_safe_ident((string) ($config['auth']['promo_used_nick_col'] ?? 'NickName'));
    $usedDateRaw = (string) ($config['auth']['promo_used_date_col'] ?? '');

    /* Таблица бонус-кодов — создаётся только если её ещё нет; реальную игровую не трогаем. */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$pTable}` (
            `ID`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `Name`       VARCHAR(32)  NOT NULL,
            `Data_0`     VARCHAR(64)  NOT NULL DEFAULT '',
            `Data_1`     VARCHAR(128) NOT NULL DEFAULT '',
            `Data_2`     VARCHAR(255) NOT NULL DEFAULT '',
            `Activation` INT          NOT NULL DEFAULT 1,
            `Minutes`    INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (`ID`),
            UNIQUE KEY `uk_name` (`Name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    /* Дополнительная колонка `Created` нужна сайту, чтобы показывать «истёк/осталось N минут».
       Если её добавить нельзя (нет прав / уже есть) — просто игнорируем. */
    try { $pdo->exec("ALTER TABLE `{$pTable}` ADD COLUMN `Created` DATETIME NULL DEFAULT NULL"); }
    catch (\Throwable $e) { /* ok */ }

    /* Таблица учёта активаций. Колонки берём из конфига, т.к. у разных модов схема может отличаться. */
    if ($usedDateRaw !== '') {
        $usedDate = auth_safe_ident($usedDateRaw);
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `{$usedTable}` (
                `{$usedName}` VARCHAR(32) NOT NULL,
                `{$usedNick}` VARCHAR(64) NOT NULL,
                `{$usedDate}` DATETIME    NOT NULL,
                PRIMARY KEY (`{$usedName}`, `{$usedNick}`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `{$usedTable}` (
                `{$usedName}` VARCHAR(32) NOT NULL,
                `{$usedNick}` VARCHAR(64) NOT NULL,
                PRIMARY KEY (`{$usedName}`, `{$usedNick}`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
    /* Основная таблица — её читает игровой мод. Создаём только если её ещё нет
       (например на чистой БД); схему оставляем такой же, как в существующей БД. */
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `Promocodes` (
            `ID`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `Name`       VARCHAR(32) NOT NULL,
            `Data_0`     VARCHAR(64) NOT NULL DEFAULT \'\',
            `Data_1`     VARCHAR(128) NOT NULL DEFAULT \'\',
            `Data_2`     VARCHAR(128) NOT NULL DEFAULT \'\',
            `Activation` INT NOT NULL DEFAULT 1,
            `Minutes`    INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`ID`),
            UNIQUE KEY `uniq_name` (`Name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Сайд-таблица с метаданными, которые нужны только сайту (кто создал, когда,
       и до какого времени активен в дополнение к Minutes). Не трогается модом. */
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `BonusCodesMeta` (
            `code`       VARCHAR(32) NOT NULL,
            `created_by` VARCHAR(64) NOT NULL DEFAULT \'\',
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME NULL,
            PRIMARY KEY (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Учёт активаций по нику — чтобы один игрок не активировал код дважды через сайт. */
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `BonusCodeUsage` (
            `code` VARCHAR(32) NOT NULL,
            `nickname` VARCHAR(64) NOT NULL,
            `used_at` DATETIME NOT NULL,
            PRIMARY KEY (`code`, `nickname`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Очередь предметов (тип 4) — сайт пишет, игровой мод вычитывает. */
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

/**
 * Превращает строку из Promocodes в массив, совместимый с прежним форматом
 * BonusCodes (поля code, prize_type, prize_value, prize_extra, usage_limit,
 * used_count, expires_at, created_at, created_by, coins). Это нужно, чтобы
 * шаблон lk.php продолжал работать без изменений.
 *
 * $row     — строка из Promocodes (Name, Data_0..Data_2, Activation, Minutes)
 * $meta    — строка из BonusCodesMeta (created_at, created_by, expires_at) или null
 * $usedCnt — сколько раз код активирован через сайт (из BonusCodeUsage)
 */
function bonus_row_to_legacy(array $row, ?array $meta, int $usedCnt): array
{
    $types  = bonus_unpack_data($row['Data_0'] ?? '');
    $vals   = bonus_unpack_data($row['Data_1'] ?? '');
    $amts   = bonus_unpack_data($row['Data_2'] ?? '');

    /* В админке сайт работает с одним призом в слоте [0]. */
    $type  = $types[0];
    $itemId = $vals[0];
    $amount = $amts[0];

    if ($type === BONUS_PRIZE_ITEM) {
        $prizeValue = $itemId;
        $prizeExtra = $amount;
        $coinsLegacy = 0;
    } else {
        $prizeValue = $amount;
        $prizeExtra = 0;
        $coinsLegacy = $type === BONUS_PRIZE_DONATE ? $amount : 0;
    }

    /* expires_at: приоритет — из Meta; иначе считаем из Minutes относительно created_at. */
    $createdAt = $meta['created_at'] ?? null;
    $expiresAt = $meta['expires_at'] ?? null;
    if ($expiresAt === null && (int) ($row['Minutes'] ?? 0) > 0 && $createdAt) {
        $ts = strtotime($createdAt);
        if ($ts !== false) {
            $expiresAt = date('Y-m-d H:i:s', $ts + ((int) $row['Minutes']) * 60);
        }
    }

    return [
        'code'        => (string) $row['Name'],
        'prize_type'  => $type ?: BONUS_PRIZE_DONATE,
        'prize_value' => $prizeValue,
        'prize_extra' => $prizeExtra,
        'coins'       => $coinsLegacy,
        'usage_limit' => (int) ($row['Activation'] ?? 1),
        'used_count'  => $usedCnt,
        'created_by'  => (string) ($meta['created_by'] ?? ''),
        'created_at'  => $createdAt ?: date('Y-m-d H:i:s'),
        'expires_at'  => $expiresAt,
        /* сырые поля — пригодятся при выдаче приза */
        '_types'      => $types,
        '_vals'       => $vals,
        '_amts'       => $amts,
        '_minutes'    => (int) ($row['Minutes'] ?? 0),
    ];
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
    global $config;
    $pTable = auth_safe_ident((string) ($config['auth']['promo_table'] ?? 'Promocodes'));
    $limit  = max(1, min(500, $limit));

    $rows = db_pdo()->query("SELECT * FROM `{$pTable}` ORDER BY `ID` DESC LIMIT {$limit}")
        ->fetchAll(PDO::FETCH_ASSOC);

    /* подсчитываем количество активаций пачкой через один JOIN */
    if (!empty($rows)) {
        $usedTable = auth_safe_ident((string) ($config['auth']['promo_used_table'] ?? 'Promocodes_Used'));
        $usedName  = auth_safe_ident((string) ($config['auth']['promo_used_name_col'] ?? 'Name'));

        $names = array_column($rows, 'Name');
        $place = implode(',', array_fill(0, count($names), '?'));
        $stmt  = db_pdo()->prepare(
            "SELECT `{$usedName}` AS n, COUNT(*) AS c FROM `{$usedTable}`
             WHERE `{$usedName}` IN ({$place}) GROUP BY `{$usedName}`"
        );
        try {
            $stmt->execute($names);
            $usedMap = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $usedMap[(string) $r['n']] = (int) $r['c'];
            }
        } catch (\Throwable $e) {
            $usedMap = [];
        }

        foreach ($rows as &$row) {
            $row['_prizes']     = bonus_decode_prizes($row);
            $row['_used_count'] = $usedMap[(string) $row['Name']] ?? 0;
        }
        unset($row);
    }

    return $rows;
    $limit = max(1, min(500, $limit));

    $rows = db_pdo()->query(
        "SELECT p.*,
                m.`created_by`, m.`created_at` AS meta_created_at, m.`expires_at`,
                (SELECT COUNT(*) FROM `BonusCodeUsage` u WHERE u.`code` = p.`Name`) AS used_count
           FROM `Promocodes` p
      LEFT JOIN `BonusCodesMeta` m ON m.`code` = p.`Name`
       ORDER BY COALESCE(m.`created_at`, NOW()) DESC, p.`ID` DESC
          LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $meta = $r['meta_created_at'] === null && $r['created_by'] === null ? null : [
            'created_by' => (string) ($r['created_by'] ?? ''),
            'created_at' => (string) ($r['meta_created_at'] ?? ''),
            'expires_at' => $r['expires_at'],
        ];
        $out[] = bonus_row_to_legacy($r, $meta, (int) ($r['used_count'] ?? 0));
    }
    return $out;
}

function bonus_get(string $code): ?array
{
    auth_ensure_bonus_codes_table();
    global $config;
    $pTable = auth_safe_ident((string) ($config['auth']['promo_table'] ?? 'Promocodes'));

    $stmt = db_pdo()->prepare("SELECT * FROM `{$pTable}` WHERE `Name` = :c LIMIT 1");

    $stmt = db_pdo()->prepare(
        "SELECT p.*,
                m.`created_by`, m.`created_at` AS meta_created_at, m.`expires_at`,
                (SELECT COUNT(*) FROM `BonusCodeUsage` u WHERE u.`code` = p.`Name`) AS used_count
           FROM `Promocodes` p
      LEFT JOIN `BonusCodesMeta` m ON m.`code` = p.`Name`
          WHERE p.`Name` = :c
          LIMIT 1"
    );
    $stmt->execute([':c' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $row['_prizes'] = bonus_decode_prizes($row);
    return $row;
}

/** Считает текущее количество активаций кода в Promocodes_Used. */
function bonus_used_count(string $code): int
{
    global $config;
    $usedTable = auth_safe_ident((string) ($config['auth']['promo_used_table'] ?? 'Promocodes_Used'));
    $usedName  = auth_safe_ident((string) ($config['auth']['promo_used_name_col'] ?? 'Name'));

    $stmt = db_pdo()->prepare("SELECT COUNT(*) FROM `{$usedTable}` WHERE `{$usedName}` = :c");
    try {
        $stmt->execute([':c' => $code]);
        return (int) $stmt->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
    $meta = $row['meta_created_at'] === null && $row['created_by'] === null ? null : [
        'created_by' => (string) ($row['created_by'] ?? ''),
        'created_at' => (string) ($row['meta_created_at'] ?? ''),
        'expires_at' => $row['expires_at'],
    ];
    return bonus_row_to_legacy($row, $meta, (int) ($row['used_count'] ?? 0));
}

/**
 * Создаёт бонус-код в `Promocodes`.
 *
 *  $prizes — массив (1..10 элементов), каждый: ['type'=>1..5, 'value'=>int, 'extra'=>int]
 *            value у не-предметов это «количество», у предмета — ID; extra — кол-во предметов.
 *
 * @throws RuntimeException
 */
function bonus_create(string $code, array $prizes, int $activation, int $minutes, string $admin): void
{
    auth_ensure_bonus_codes_table();
    global $config;

    if ($code === '' || strlen($code) > 32) {
        throw new RuntimeException('Код должен быть от 1 до 32 символов.');
    }
    if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
        throw new RuntimeException('Код содержит недопустимые символы. Разрешены A–Z, 0–9, _, -.');
    }
    if ($activation < 1 || $activation > 1000000) {
        throw new RuntimeException('Лимит активаций — от 1 до 1 000 000.');
    }
    if ($minutes < 0 || $minutes > 525600) {
        throw new RuntimeException('Время жизни — от 0 до 525 600 минут (год).');
    }

    /* нормализуем призы и валидируем */
    $clean = [];
    foreach ($prizes as $p) {
        $type  = (int) ($p['type']  ?? 0);
        if ($type === BONUS_PRIZE_NONE) continue;
        if (!array_key_exists($type, bonus_prize_types())) {
            throw new RuntimeException('Неизвестный тип приза.');
        }
        $value = (int) ($p['value'] ?? 0);
        $extra = (int) ($p['extra'] ?? 0);

        if ($type === BONUS_PRIZE_ITEM) {
            if ($value < 1 || $value > 100000)  throw new RuntimeException('ID предмета должен быть 1–100000.');
            if ($extra < 1 || $extra > 1000000) throw new RuntimeException('Количество предметов — 1–1 000 000.');
        } else {
            if ($value < 1 || $value > 1000000000) throw new RuntimeException('Количество должно быть 1–1 000 000 000.');
            $extra = 0;
        }
        $clean[] = ['type' => $type, 'value' => $value, 'extra' => $extra];
        if (count($clean) > BONUS_PRIZE_MAX_SLOTS) {
            throw new RuntimeException('Максимум ' . BONUS_PRIZE_MAX_SLOTS . ' призов.');
        }
    }
    if (empty($clean)) {
        throw new RuntimeException('Добавь хотя бы один приз.');
    }
    if (bonus_get($code) !== null) {
        throw new RuntimeException('Код уже существует.');
    }

    /* собираем Data_0/1/2 в формате как у Pawn-мода */
    $d0 = $d1 = $d2 = [];
    foreach ($clean as $p) {
        $d0[] = $p['type'];
        if ($p['type'] === BONUS_PRIZE_ITEM) {
            $d1[] = $p['value'];   // ID предмета
            $d2[] = $p['extra'];   // количество
        } else {
            $d1[] = 0;
            $d2[] = $p['value'];
        }
    }

    $data0 = bonus_build_csv_slots($d0);
    $data1 = bonus_build_csv_slots($d1);
    $data2 = bonus_build_csv_slots($d2);

    $pTable = auth_safe_ident((string) ($config['auth']['promo_table'] ?? 'Promocodes'));

    /* Created колонка может быть, а может и не быть — пробуем INSERT с ней, фоллбэк без неё. */
    $params = [
        ':n'   => $code,
        ':d0'  => $data0,
        ':d1'  => $data1,
        ':d2'  => $data2,
        ':act' => $activation,
        ':min' => $minutes,
    ];

    try {
        $stmt = db_pdo()->prepare(
            "INSERT INTO `{$pTable}` (`Name`, `Data_0`, `Data_1`, `Data_2`, `Activation`, `Minutes`, `Created`)
             VALUES (:n, :d0, :d1, :d2, :act, :min, NOW())"
        );
        $stmt->execute($params);
    } catch (\Throwable $e) {
        $stmt = db_pdo()->prepare(
            "INSERT INTO `{$pTable}` (`Name`, `Data_0`, `Data_1`, `Data_2`, `Activation`, `Minutes`)
             VALUES (:n, :d0, :d1, :d2, :act, :min)"
        );
        $stmt->execute($params);
    /* Раскладываем приз в формат Promocodes (10 слотов).
       Слот 0 = текущий приз; остальные 9 = нули. */
    if ($prizeType === BONUS_PRIZE_ITEM) {
        $data0 = bonus_pack_data([$prizeType]);
        $data1 = bonus_pack_data([$prizeValue]);   // ID предмета
        $data2 = bonus_pack_data([$prizeExtra]);   // количество
    } else {
        $data0 = bonus_pack_data([$prizeType]);
        $data1 = bonus_pack_data([0]);
        $data2 = bonus_pack_data([$prizeValue]);   // сумма / кол-во
    }

    /* Minutes: если задан expires_at — переводим в минуты от текущего момента,
       чтобы игровой мод тоже знал срок жизни. 0 = бессрочно. */
    $minutes = 0;
    if ($expiresAt !== null) {
        $delta = strtotime($expiresAt) - time();
        $minutes = $delta > 0 ? (int) ceil($delta / 60) : 0;
    }

    $pdo = db_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO `Promocodes`
                (`Name`, `Data_0`, `Data_1`, `Data_2`, `Activation`, `Minutes`)
             VALUES
                (:n, :d0, :d1, :d2, :act, :min)"
        );
        $stmt->execute([
            ':n'   => $code,
            ':d0'  => $data0,
            ':d1'  => $data1,
            ':d2'  => $data2,
            ':act' => $usageLimit,
            ':min' => $minutes,
        ]);

        $meta = $pdo->prepare(
            "INSERT INTO `BonusCodesMeta` (`code`, `created_by`, `created_at`, `expires_at`)
             VALUES (:c, :a, NOW(), :exp)"
        );
        $meta->execute([
            ':c'   => $code,
            ':a'   => $admin,
            ':exp' => $expiresAt,
        ]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bonus_delete(string $code): bool
{
    auth_ensure_bonus_codes_table();
    global $config;
    $pTable    = auth_safe_ident((string) ($config['auth']['promo_table']      ?? 'Promocodes'));
    $usedTable = auth_safe_ident((string) ($config['auth']['promo_used_table'] ?? 'Promocodes_Used'));
    $usedName  = auth_safe_ident((string) ($config['auth']['promo_used_name_col'] ?? 'Name'));

    $pdo = db_pdo();
    $del = $pdo->prepare("DELETE FROM `{$pTable}` WHERE `Name` = :c");
    $del->execute([':c' => $code]);
    $ok = $del->rowCount() > 0;

    /* чистим учёт активаций — как и Pawn делает при пересоздании */
    try {
        $cleanup = $pdo->prepare("DELETE FROM `{$usedTable}` WHERE `{$usedName}` = :c");
        $cleanup->execute([':c' => $code]);
    } catch (\Throwable $e) { /* не критично */ }

    return $ok;
    $pdo = db_pdo();
    $stmt = $pdo->prepare("DELETE FROM `Promocodes` WHERE `Name` = :c");
    $stmt->execute([':c' => $code]);
    $deleted = $stmt->rowCount() > 0;

    /* Подчищаем сайд-таблицы. */
    $pdo->prepare("DELETE FROM `BonusCodesMeta` WHERE `code` = :c")->execute([':c' => $code]);
    $pdo->prepare("DELETE FROM `BonusCodeUsage` WHERE `code` = :c")->execute([':c' => $code]);

    return $deleted;
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
 * Активация: парсит призы из CSV-полей `Promocodes`, выдаёт каждый,
 * пишет запись в `Promocodes_Used` (с учётом конфигурируемых имён колонок).
 *
 * Возвращает: ['ok'=>bool,'message'=>string,'reward'=>string,'prizes'=>array]
 */
function bonus_redeem(string $code, string $nickname): array
{
    auth_ensure_bonus_codes_table();
    global $config;

    $row = bonus_get($code);
    if (!$row) {
        return ['ok' => false, 'message' => 'Код не найден.', 'reward' => '', 'prizes' => []];
    }

    /* Срок действия — если есть колонка Created и Minutes>0 */
    $minutes = (int) ($row['Minutes'] ?? 0);
    if ($minutes > 0 && !empty($row['Created'])) {
        $createdTs = strtotime((string) $row['Created']);
        if ($createdTs && time() > $createdTs + $minutes * 60) {
            return ['ok' => false, 'message' => 'Срок действия кода истёк.', 'reward' => '', 'prizes' => []];
        }
    }

    $usedTable = auth_safe_ident((string) ($config['auth']['promo_used_table'] ?? 'Promocodes_Used'));
    $usedName  = auth_safe_ident((string) ($config['auth']['promo_used_name_col'] ?? 'Name'));
    $usedNick  = auth_safe_ident((string) ($config['auth']['promo_used_nick_col'] ?? 'NickName'));
    $usedDateRaw = (string) ($config['auth']['promo_used_date_col'] ?? '');

    /* лимит активаций */
    $usedCount = bonus_used_count($code);
    if ($usedCount >= (int) $row['Activation']) {
        return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'reward' => '', 'prizes' => []];
    }

    /* один и тот же ник не может активировать тот же код дважды */
    $check = db_pdo()->prepare(
        "SELECT 1 FROM `{$usedTable}`
         WHERE `{$usedName}` = :c AND LOWER(`{$usedNick}`) = LOWER(:n) LIMIT 1"
    );
    try {
        $check->execute([':c' => $code, ':n' => $nickname]);
        if ($check->fetchColumn()) {
            return ['ok' => false, 'message' => 'Вы уже активировали этот код.', 'reward' => '', 'prizes' => []];
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'Ошибка БД: ' . $e->getMessage(), 'reward' => '', 'prizes' => []];
    }

    $prizes = $row['_prizes'] ?? [];
    if (empty($prizes)) {
        return ['ok' => false, 'message' => 'У этого кода нет призов.', 'reward' => '', 'prizes' => []];
    /* Собираем все непустые слоты призов. */
    $types = $row['_types'];
    $vals  = $row['_vals'];
    $amts  = $row['_amts'];

    $prizes = [];
    for ($i = 0; $i < 10; $i++) {
        $t = (int) $types[$i];
        if ($t <= 0) continue;
        if ($t === BONUS_PRIZE_ITEM) {
            $prizes[] = [$t, (int) $vals[$i], (int) $amts[$i]];
        } else {
            $prizes[] = [$t, (int) $amts[$i], 0];
        }
    }

    if (empty($prizes)) {
        return ['ok' => false, 'message' => 'У кода нет призов.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
    }

    $pdo = db_pdo();
    $pdo->beginTransaction();
    try {
        /* Лочим строку учёта на конкурентность через INSERT (PK защищает от двойной активации) */
        if ($usedDateRaw !== '') {
            $usedDate = auth_safe_ident($usedDateRaw);
            $ins = $pdo->prepare(
                "INSERT INTO `{$usedTable}` (`{$usedName}`, `{$usedNick}`, `{$usedDate}`)
                 VALUES (:c, :n, NOW())"
            );
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO `{$usedTable}` (`{$usedName}`, `{$usedNick}`) VALUES (:c, :n)"
            );
        /* Атомарно проверяем, что лимит ещё не исчерпан, через INSERT с уникальным ключом. */
        $usage = $pdo->prepare(
            "INSERT INTO `BonusCodeUsage` (`code`, `nickname`, `used_at`) VALUES (:c, :n, NOW())"
        );
        $usage->execute([':c' => $code, ':n' => $nickname]);

        /* Перепроверяем лимит уже после вставки. */
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM `BonusCodeUsage` WHERE `code` = :c");
        $cnt->execute([':c' => $code]);
        if ((int) $cnt->fetchColumn() > (int) $row['usage_limit']) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'reward' => '', 'type' => 0, 'value' => 0, 'extra' => 0];
        }
        $ins->execute([':c' => $code, ':n' => $nickname]);

        /* Перепроверяем лимит ещё раз — race condition */
        if (bonus_used_count($code) > (int) $row['Activation']) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Лимит использований исчерпан.', 'reward' => '', 'prizes' => []];
        }

        $applied = 0;
        foreach ($prizes as $p) {
            if (bonus_apply_prize($pdo, $nickname, (int) $p['type'], (int) $p['value'], (int) $p['extra'], $code)) {
                $applied++;
            }
        }
        if ($applied === 0) {
        $appliedAny = false;
        $rewardParts = [];
        foreach ($prizes as [$pType, $pVal, $pExtra]) {
            if (bonus_apply_prize($pdo, $nickname, $pType, $pVal, $pExtra, $code)) {
                $appliedAny = true;
            }
            $rewardParts[] = bonus_prize_describe($pType, $pVal, $pExtra);
        }

        if (!$appliedAny) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Игровой аккаунт не найден.', 'reward' => '', 'prizes' => []];
        }

        $pdo->commit();
        return [
            'ok'      => true,
            'message' => 'Зачислено!',
            'reward'  => bonus_prizes_describe($prizes),
            'prizes'  => $prizes,

        $reward = implode(' + ', $rewardParts);
        $first  = $prizes[0];
        $hasItem = false;
        foreach ($prizes as $p) { if ($p[0] === BONUS_PRIZE_ITEM) { $hasItem = true; break; } }
        $msg = $hasItem
            ? 'Часть награды отправлена в очередь предметов — зайди в игру.'
            : 'Зачислено!';

        return [
            'ok'      => true,
            'message' => $msg,
            'reward'  => $reward,
            'type'    => $first[0],
            'value'   => $first[1],
            'extra'   => $first[2],
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => 'Ошибка: ' . $e->getMessage(), 'reward' => '', 'prizes' => []];
    }
}
