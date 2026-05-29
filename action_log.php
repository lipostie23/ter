<?php
/**
 * Универсальное логирование действий пользователей в БД.
 *
 * Таблица `action_logs`:
 *   - `id`         INT AUTO_INCREMENT — первичный ключ
 *   - `nickname`   VARCHAR(64)        — кто инициировал действие (или '' если неизвестно)
 *   - `action`     VARCHAR(64)        — короткий тип события (donate, bonus_redeem, ...)
 *   - `message`    TEXT               — человекочитаемое описание (полная фраза)
 *   - `meta`       TEXT               — дополнительные данные в JSON (order_id, amount, ...)
 *   - `created_at` DATETIME           — когда записано
 *
 * Подключается через config.php; функции глобальные, чтобы можно было дёрнуть из
 * любого скрипта без require_once в каждом.
 */

require_once __DIR__ . '/config.php';

if (!function_exists('action_log_ensure_table')) {
    function action_log_ensure_table(): void
    {
        static $ready = false;
        if ($ready) return;
        try {
            db_pdo()->exec(
                'CREATE TABLE IF NOT EXISTS `action_logs` (
                    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nickname`   VARCHAR(64) NOT NULL DEFAULT \'\',
                    `action`     VARCHAR(64) NOT NULL DEFAULT \'\',
                    `message`    TEXT NOT NULL,
                    `meta`       TEXT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_nickname` (`nickname`),
                    KEY `idx_action` (`action`),
                    KEY `idx_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $ready = true;
        } catch (\Throwable $e) {
            /* нет прав CREATE — пишем только в файловый fallback */
        }
    }
}

if (!function_exists('action_log_write')) {
    /**
     * Записывает событие в `action_logs`. Если БД недоступна — пишет в logs/action_logs.log
     * как fallback, чтобы событие гарантированно осталось.
     *
     * @param string $action   короткий код события (например 'donate', 'bonus_redeem')
     * @param string $message  готовая фраза для журнала
     * @param string $nickname игрок-инициатор (или '' если нет)
     * @param array  $meta     любая структура — упакуется в JSON
     * @return bool true если запись успешно положена в БД
     */
    function action_log_write(string $action, string $message, string $nickname = '', array $meta = []): bool
    {
        action_log_ensure_table();
        try {
            $stmt = db_pdo()->prepare(
                "INSERT INTO `action_logs` (`nickname`, `action`, `message`, `meta`, `created_at`)
                 VALUES (:nick, :act, :msg, :meta, NOW())"
            );
            $stmt->execute([
                ':nick' => mb_substr($nickname, 0, 64),
                ':act'  => mb_substr($action, 0, 64),
                ':msg'  => $message,
                ':meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
            return true;
        } catch (\Throwable $e) {
            /* fallback: файловый журнал, чтобы запись не потерялась */
            $line = sprintf(
                "[%s] %s | %s | %s | %s\n",
                date('Y-m-d H:i:s'),
                $action,
                $nickname,
                $message,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : ''
            );
            $dir = __DIR__ . '/logs';
            @mkdir($dir, 0755, true);
            @file_put_contents($dir . '/action_logs.log', $line, FILE_APPEND | LOCK_EX);
            return false;
        }
    }
}
