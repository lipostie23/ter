<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['id'], $data['status'])) {
    http_response_code(400);
    exit('Bad Request');
}

$pt             = $config['platega'];
$incomingMerch  = $_SERVER['HTTP_X_MERCHANTID'] ?? '';
$incomingSecret = $_SERVER['HTTP_X_SECRET']     ?? '';

if ($incomingMerch !== $pt['merchant_id'] || $incomingSecret !== $pt['secret']) {
    http_response_code(403);
    exit('Forbidden');
}

$status = $data['status'] ?? '';

if ($status !== 'CONFIRMED') {
    writeLog([
        'event'      => 'non_confirmed',
        'status'     => $status,
        'payment_id' => $data['id'],
        'date'       => date('Y-m-d H:i:s'),
    ], 'donations.json');

    http_response_code(200);
    exit('OK');
}

$paymentId = $data['id'];
$amount    = (float)($data['amount']   ?? 0);
$currency  = $data['currency']          ?? 'RUB';
$paidAt    = date('d.m.Y H:i:s');

$meta     = json_decode($data['payload'] ?? '{}', true) ?? [];
$orderId  = $meta['order_id'] ?? 'unknown';
$purpose  = ($meta['purpose'] ?? 'donate') === 'roulette' ? 'roulette' : 'donate';
$nickname = $meta['nickname'] ?? 'unknown';
$server   = $meta['server']   ?? 'unknown';
$coins    = (int)($meta['coins'] ?? 0);

// --- ОПТИМИЗАЦИЯ ПРОВЕРКИ ДУБЛИКАТОВ ---
// Чтобы не грузить 50 000 записей в память для проверки одного ID, 
// используем быстрый отдельный файл-индекс для дубликатов в целях производительности.
$dupDir = __DIR__ . '/logs/payments';
@mkdir($dupDir, 0755, true);
$dupFile = $dupDir . '/' . md5($paymentId) . '.chk';

if (file_exists($dupFile)) {
    http_response_code(200);
    exit('OK (duplicate)');
}

// =============================================================
//  Роутинг по типу платежа
// =============================================================
if ($purpose === 'roulette') {
    handleRouletteWin($paymentId, $orderId, $nickname, $server, (float)$amount, $currency, $paidAt, $dupFile);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Сначала ЖЕСТКО выдаем монеты игроку, чтобы никакие проблемы с файлами не мешали транзакции!
$isGiven = giveCoinsToPlayer($nickname, $coins);

if ($isGiven) {
    // Создаем метку, что платеж обработан успешно
    file_put_contents($dupFile, time());

    // Пишем в общий лог донатов (теперь, если он упадет, игрок всё равно УЖЕ получил монеты)
    $logFile = __DIR__ . '/logs/donations.json';
    $log = [];
    if (file_exists($logFile)) {
        $log = json_decode(@file_get_contents($logFile), true) ?? [];
    }
    
    $log[] = [
        'payment_id' => $paymentId,
        'order_id'   => $orderId,
        'nickname'   => $nickname,
        'server'     => $server,
        'amount_rub' => $amount,
        'coins'      => $coins,
        'currency'   => $currency,
        'date'       => $paidAt,
        'timestamp'  => time(),
    ];

    if (count($log) > 5000) { // Снизили лимит до 5000, чтобы JSON не весил по 10+ МБ
        $log = array_slice($log, -5000);
    }
    @file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    // Отправка уведомления в Telegram
    $tg = $config['telegram_bot'];
    $message = "<b>✅ Новый донат зачислен!</b>\n\n"
             . "Никнейм: <code>{$nickname}</code>\n"
             . "Сервер: {$server}\n"
             . "Сумма: {$amount} {$currency}\n"
             . "Монет: {$coins}\n"
             . "Время: {$paidAt}\n"
             . "ID платежа: <code>{$paymentId}</code>\n"
             . "Order ID: <code>{$orderId}</code>";

    sendTelegram($tg['token'], $tg['chat_id'], $message);

    http_response_code(200);
    echo 'OK';
} else {
    // Если база данных не смогла выдать монеты или игрок не найден
    http_response_code(500);
    echo 'Error allocating coins';
}

function giveCoinsToPlayer(string $nickname, int $coins): bool
{
    try {
        // Подключение берётся из config.php (db_pdo), хосты remote/local + fallback внутри.
        $pdo = db_pdo();

        // Приводим к нижнему регистру саму переменную, чтобы разгрузить SQL-запрос
        $lowercaseNick = mb_strtolower($nickname, 'UTF-8');

        $stmt = $pdo->prepare(
            "UPDATE `players`
             SET `Cash_Donate` = `Cash_Donate` + :coins
             WHERE LOWER(`NickName`) = :nick
             LIMIT 1"
        );
        $stmt->execute([':coins' => $coins, ':nick' => $lowercaseNick]);

        $affected = $stmt->rowCount();

        writeLog([
            'event'    => $affected > 0 ? 'coins_given' : 'player_not_found',
            'nickname' => $nickname,
            'coins'    => $coins,
            'rows'     => $affected,
            'date'     => date('Y-m-d H:i:s'),
        ], 'give_log.json');

        // Если affected rows = 0, значит игрока с таким ником просто нет в бд
        return $affected > 0;

    } catch (\Exception $e) {
        writeLog([
            'event'    => 'db_error',
            'nickname' => $nickname,
            'coins'    => $coins,
            'error'    => $e->getMessage(),
            'date'     => date('Y-m-d H:i:s'),
        ], 'errors.json');
        
        return false;
    }
}

function writeLog(array $entry, string $filename): void
{
    $logDir  = __DIR__ . '/logs';
    $logFile = $logDir . '/' . $filename;
    @mkdir($logDir, 0755, true);

    $log = [];
    if (file_exists($logFile)) {
        $log = json_decode(@file_get_contents($logFile), true) ?? [];
    }
    $log[] = $entry;
    if (count($log) > 2000) {
        $log = array_slice($log, -2000);
    }
    @file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function sendTelegram(string $token, string $chatId, string $text): void
{
    if (!$token || !$chatId) return;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_TIMEOUT => 5,
    ]);
    @curl_exec($ch);
    @curl_close($ch);
}


/**
 * Выбирает приз с учётом весов (weight) из $config['roulette']['prizes'].
 * Возвращает сам приз + индекс в массиве (нужен фронту для прокрутки колеса).
 */
function roulette_pick_prize(): array
{
    global $config;
    $prizes = $config['roulette']['prizes'] ?? [];
    if (empty($prizes)) {
        return ['index' => 0, 'label' => 'Утешительный приз', 'coins' => 0, 'tier' => 'common', 'icon' => 'ph-coin', 'weight' => 1];
    }

    $totalWeight = 0;
    foreach ($prizes as $p) {
        $totalWeight += max(0, (int) ($p['weight'] ?? 0));
    }
    if ($totalWeight <= 0) {
        $first = $prizes[0];
        $first['index'] = 0;
        return $first;
    }

    $r = random_int(1, $totalWeight);
    $sum = 0;
    foreach ($prizes as $i => $p) {
        $sum += max(0, (int) ($p['weight'] ?? 0));
        if ($r <= $sum) {
            $p['index'] = $i;
            return $p;
        }
    }
    $first = $prizes[0];
    $first['index'] = 0;
    return $first;
}

/**
 * Обработка успешного платежа за прокрут рулетки:
 *  1. Берём случайный приз с учётом весов.
 *  2. Зачисляем монеты приза на ник в players.Cash_Donate.
 *  3. Сохраняем результат в cache/roulette_results/{order_id}.json
 *     (страница roulette.php?spin=ORDERID забирает его и крутит колесо).
 *  4. Логируем в logs/roulette_log.json и в Telegram.
 */
function handleRouletteWin(string $paymentId, string $orderId, string $nickname, string $server, float $amount, string $currency, string $paidAt, string $dupFile): void
{
    global $config;

    $prize = roulette_pick_prize();
    $coins = (int) ($prize['coins'] ?? 0);

    $given = $coins > 0 ? giveCoinsToPlayer($nickname, $coins) : true;

    // Сохраняем результат в защищённый каталог (для polling-эндпоинта на странице рулетки)
    $resultsDir = __DIR__ . '/cache/roulette_results';
    if (!is_dir($resultsDir)) {
        @mkdir($resultsDir, 0755, true);
        // Запрещаем прямой доступ к json-файлам через web — их читает только roulette.php
        @file_put_contents($resultsDir . '/.htaccess', "Require all denied\nDeny from all\n");
    }

    // Безопасное имя файла: order_id уже валидируется регуляркой на чтении, но подстрахуемся
    $safeOrder = preg_replace('/[^A-Za-z0-9_]/', '', $orderId);
    if ($safeOrder !== '') {
        $resultFile = $resultsDir . '/' . $safeOrder . '.json';
        @file_put_contents(
            $resultFile,
            json_encode([
                'order_id'   => $orderId,
                'nickname'   => $nickname,
                'server'     => $server,
                'prize'      => $prize,
                'coins'      => $coins,
                'given'      => (bool) $given,
                'amount_rub' => $amount,
                'currency'   => $currency,
                'paid_at'    => $paidAt,
                'timestamp'  => time(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    // Метка дубликата — после того, как результат сохранён
    file_put_contents($dupFile, time());

    // История прокрутов
    writeLog([
        'event'      => $given ? 'spin_done' : 'spin_done_player_not_found',
        'payment_id' => $paymentId,
        'order_id'   => $orderId,
        'nickname'   => $nickname,
        'server'     => $server,
        'prize'      => $prize['label'] ?? '?',
        'coins'      => $coins,
        'amount_rub' => $amount,
        'currency'   => $currency,
        'date'       => $paidAt,
        'timestamp'  => time(),
    ], 'roulette_log.json');

    // Уведомление в Telegram
    $tg = $config['telegram_bot'];
    $tier = $prize['tier'] ?? 'common';
    $tierIcon = ['common' => '🪙', 'rare' => '💠', 'epic' => '💎', 'legendary' => '👑'][$tier] ?? '🎰';
    $message = "<b>🎰 Прокрут рулетки</b>\n\n"
             . "Игрок: <code>{$nickname}</code>\n"
             . "Сервер: {$server}\n"
             . "Сумма: {$amount} {$currency}\n"
             . "Приз: {$tierIcon} <b>" . htmlspecialchars((string) ($prize['label'] ?? '?')) . "</b>\n"
             . "Монет начислено: {$coins}\n"
             . ($given ? '' : "<i>⚠ Игрок с этим ником не найден в БД — монеты не зачислены, выдай вручную.</i>\n")
             . "Время: {$paidAt}\n"
             . "ID платежа: <code>{$paymentId}</code>\n"
             . "Order ID: <code>{$orderId}</code>";
    sendTelegram($tg['token'], $tg['chat_id'], $message);
}
