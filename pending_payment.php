<?php
/**
 * Сохранение pending-платежей в БД.
 *
 * Раньше pending_payment лежал только в `$_SESSION` пользователя,
 * но webhook от Platega приходит из стороннего сервиса БЕЗ нашей сессии.
 * Поэтому, если Platega почему-то не вернёт `payload`, мы теряем
 * `nickname` / `coins` / `order_id`. Чтобы такого не было, дублируем
 * данные в таблицу `pending_payments` и в webhook'е достаём их по order_id.
 *
 * Таблица `pending_payments`:
 *   - `order_id`   VARCHAR(64) PK — наш сгенерированный идентификатор
 *   - `purpose`    VARCHAR(32)    — donate / roulette
 *   - `nickname`   VARCHAR(64)
 *   - `server`     VARCHAR(64)
 *   - `amount`     INT             — сумма в рублях
 *   - `coins`      INT             — сколько монет нужно зачислить
 *   - `method`     VARCHAR(32)
 *   - `created_at` DATETIME
 *   - `confirmed`  TINYINT(1)      — 1 после успешного callback'а
 *   - `confirmed_at` DATETIME NULL
 */

require_once __DIR__ . '/config.php';

if (!function_exists('pending_payment_ensure_table')) {
    function pending_payment_ensure_table(): void
    {
        static $ready = false;
        if ($ready) return;
        try {
            db_pdo()->exec(
                'CREATE TABLE IF NOT EXISTS `pending_payments` (
                    `order_id`     VARCHAR(64) NOT NULL,
                    `purpose`      VARCHAR(32) NOT NULL DEFAULT \'donate\',
                    `nickname`     VARCHAR(64) NOT NULL DEFAULT \'\',
                    `server`       VARCHAR(64) NOT NULL DEFAULT \'\',
                    `amount`       INT NOT NULL DEFAULT 0,
                    `coins`        INT NOT NULL DEFAULT 0,
                    `method`       VARCHAR(32) NOT NULL DEFAULT \'\',
                    `created_at`   DATETIME NOT NULL,
                    `confirmed`    TINYINT(1) NOT NULL DEFAULT 0,
                    `confirmed_at` DATETIME NULL,
                    PRIMARY KEY (`order_id`),
                    KEY `idx_nick` (`nickname`),
                    KEY `idx_confirmed` (`confirmed`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $ready = true;
        } catch (\Throwable $e) {
            /* без таблицы pending_payments сайт не падает — просто упадёт fallback */
        }
    }
}

if (!function_exists('pending_payment_save')) {
    function pending_payment_save(array $row): bool
    {
        pending_payment_ensure_table();
        try {
            $stmt = db_pdo()->prepare(
                "INSERT INTO `pending_payments`
                    (`order_id`, `purpose`, `nickname`, `server`, `amount`, `coins`, `method`, `created_at`)
                 VALUES
                    (:oid, :p, :n, :s, :a, :c, :m, NOW())
                 ON DUPLICATE KEY UPDATE
                    `nickname` = VALUES(`nickname`),
                    `amount`   = VALUES(`amount`),
                    `coins`    = VALUES(`coins`)"
            );
            $stmt->execute([
                ':oid' => (string) $row['order_id'],
                ':p'   => (string) ($row['purpose']  ?? 'donate'),
                ':n'   => (string) ($row['nickname'] ?? ''),
                ':s'   => (string) ($row['server']   ?? ''),
                ':a'   => (int)    ($row['amount']   ?? 0),
                ':c'   => (int)    ($row['coins']    ?? 0),
                ':m'   => (string) ($row['method']   ?? ''),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('pending_payment_get')) {
    function pending_payment_get(string $orderId): ?array
    {
        pending_payment_ensure_table();
        try {
            $stmt = db_pdo()->prepare(
                "SELECT * FROM `pending_payments` WHERE `order_id` = :oid LIMIT 1"
            );
            $stmt->execute([':oid' => $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('pending_payment_mark_confirmed')) {
    function pending_payment_mark_confirmed(string $orderId): void
    {
        try {
            $stmt = db_pdo()->prepare(
                "UPDATE `pending_payments` SET `confirmed` = 1, `confirmed_at` = NOW()
                 WHERE `order_id` = :oid LIMIT 1"
            );
            $stmt->execute([':oid' => $orderId]);
        } catch (\Throwable $e) { /* не критично */ }
    }
}
