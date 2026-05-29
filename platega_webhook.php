<?php
require_once 'config.php';
require_once 'action_log.php';
require_once 'pending_payment.php';

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

/* Fallback: если Platega почему-то не вернула payload (или часть полей в нём
   потеряна), достаём метаданные по order_id из таблицы pending_payments,
   куда мы их положили в create_payment.php. */
if ($orderId !== 'unknown') {
    $pending = pending_payment_get($orderId);
    if ($pending) {
        if ($nickname === 'unknown' || $nickname === '') $nickname = (string) $pending['nickname'];
        if ($server   === 'unknown' || $server   === '') $server   = (string) $pending['server'];
        if ($coins   <= 0) $coins   = (int) $pending['coins'];
        if ($purpose === 'donate') $purpose = (string) ($pending['purpose'] ?? 'donate');
    }
}

/* Сумма в рублях из callback'а или, если её нет, из нашего pending. */
if ($amount <= 0 && !empty($pending['amount'])) {
    $amount = (float) $pending['amount'];
}

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

/* === Логирование в action_logs ===
   Запись формата:
   "Игрок Ivan_Ivanov пополнил донат-счет на 500 рублей через Platega,
    ID ордера: order_xxx, статус сохранения в БД: true." */
$paymentSystem = 'Platega';
$amountForLog  = (int) round((float) $amount);
$logMessage = sprintf(
    'Игрок %s пополнил донат-счет на %d рублей через %s, ID ордера: %s, статус сохранения в БД: %s.',
    $nickname !== '' ? $nickname : 'unknown',
    $amountForLog,
    $paymentSystem,
    $orderId,
    $isGiven ? 'true' : 'false'
);
action_log_write(
    'donate',
    $logMessage,
    $nickname,
    [
        'order_id'      => $orderId,
        'payment_id'    => $paymentId,
        'amount_rub'    => $amountForLog,
        'coins'         => $coins,
        'currency'      => $currency,
        'payment_system'=> $paymentSystem,
        'server'        => $server,
        'method'        => ($meta['method'] ?? null) ?: ($pending['method'] ?? ''),
        'db_saved'      => $isGiven,
    ]
);

/* Помечаем pending как закрытый — чтобы потом было видно историю успешных. */
pending_payment_mark_confirmed($orderId);

if ($isGiven) {
    // Создаем метку, что платеж обработан успешно
    file_put_contents($dupFile, time());

    // Пишем в общий лог донатов с flock'ом, чтобы параллельные вебхуки не теряли записи.
    $logFile = __DIR__ . '/logs/donations.json';
    @mkdir(dirname($logFile), 0755, true);
    $fp = @fopen($logFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $log = $raw ? (json_decode($raw, true) ?? []) : [];

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

        if (count($log) > 5000) { // Лимит, чтобы JSON не разрастался до десятков МБ
            $log = array_slice($log, -5000);
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

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
    /* Старое имя — оставляем как тонкую обёртку, чтобы не ломать вызовы из других мест. */
    return givePrizeToPlayer($nickname, $coins, 'donate');
}

/**
 * Зачисляет приз игроку в произвольную «копилку» — донат или игровые деньги.
 *
 * @param string $kind 'donate' → players.Cash_Donate, 'money' → players.Cash
 */
function givePrizeToPlayer(string $nickname, int $amount, string $kind = 'donate'): bool
{
    global $config;

    /* Имена таблицы и колонок берём из config — не хардкодим. */
    $p       = $config['players'] ?? [];
    $tbl     = (string) ($p['player_table']    ?? 'players');
    $nickCol = (string) ($p['player_nick_col'] ?? 'NickName');

    $col = $kind === 'money'
        ? (string) ($p['money_col']  ?? 'Cash')
        : (string) ($p['donate_col'] ?? 'Cash_Donate');

    /* Защищаемся от мусорных идентификаторов (если в config кто-то залил странное). */
    $tbl     = preg_replace('/[^A-Za-z0-9_]/', '', $tbl);
    $nickCol = preg_replace('/[^A-Za-z0-9_]/', '', $nickCol);
    $col     = preg_replace('/[^A-Za-z0-9_]/', '', $col);

    /* Не пытаемся ничего зачислять, если данные явно битые. */
    if ($amount <= 0 || $nickname === '' || $nickname === 'unknown') {
        writeLog([
            'event'    => 'skip_invalid',
            'nickname' => $nickname,
            'amount'   => $amount,
            'kind'     => $kind,
            'date'     => date('Y-m-d H:i:s'),
        ], 'give_log.json');
        return false;
    }

    try {
        $pdo = db_pdo();

        /* LOWER() с обеих сторон — на случай, если в БД ник в смешанном регистре,
           а пользователь ввёл его иначе. На индекс не сядет — но на 1 строку это ок. */
        $stmt = $pdo->prepare(
            "UPDATE `{$tbl}`
             SET `{$col}` = `{$col}` + :amt
             WHERE LOWER(`{$nickCol}`) = LOWER(:nick)
             LIMIT 1"
        );
        $stmt->execute([':amt' => $amount, ':nick' => $nickname]);

        $affected = $stmt->rowCount();

        writeLog([
            'event'    => $affected > 0 ? 'prize_given' : 'player_not_found',
            'nickname' => $nickname,
            'amount'   => $amount,
            'kind'     => $kind,
            'column'   => $col,
            'rows'     => $affected,
            'table'    => $tbl,
            'date'     => date('Y-m-d H:i:s'),
        ], 'give_log.json');

        return $affected > 0;

    } catch (\Throwable $e) {
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

    $fp = @fopen($logFile, 'c+');
    if (!$fp) return;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return; }

    $raw = stream_get_contents($fp);
    $log = $raw ? (json_decode($raw, true) ?? []) : [];
    $log[] = $entry;
    if (count($log) > 2000) {
        $log = array_slice($log, -2000);
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    flock($fp, LOCK_UN);
    fclose($fp);
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
        return ['index' => 0, 'label' => 'Утешительный приз', 'kind' => 'donate', 'amount' => 0, 'tier' => 'common', 'icon' => 'ph-coin', 'weight' => 1];
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

    $prize       = roulette_pick_prize();
    $prizeKind   = (string) ($prize['kind']   ?? 'donate');
    $prizeAmount = (int)    ($prize['amount'] ?? $prize['coins'] ?? 0); // coins — для совместимости со старым форматом

    $given = $prizeAmount > 0 ? givePrizeToPlayer($nickname, $prizeAmount, $prizeKind) : true;

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
                'kind'       => $prizeKind,
                'amount'     => $prizeAmount,
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
        'event'        => $given ? 'spin_done' : 'spin_done_player_not_found',
        'payment_id'   => $paymentId,
        'order_id'     => $orderId,
        'nickname'     => $nickname,
        'server'       => $server,
        'prize_label'  => $prize['label'] ?? '?',
        'prize_kind'   => $prizeKind,
        'prize_amount' => $prizeAmount,
        'amount_rub'   => $amount,
        'currency'     => $currency,
        'date'         => $paidAt,
        'timestamp'    => time(),
    ], 'roulette_log.json');

    // Уведомление в Telegram
    $tg = $config['telegram_bot'];
    $tier = $prize['tier'] ?? 'common';
    $tierIcon = ['common' => '🪙', 'rare' => '💠', 'epic' => '💎', 'legendary' => '👑'][$tier] ?? '🎰';
    $kindLabel = $prizeKind === 'money' ? 'Cash (₽)' : 'Cash_Donate (донат)';
    $message = "<b>🎰 Прокрут рулетки</b>\n\n"
             . "Игрок: <code>{$nickname}</code>\n"
             . "Сервер: {$server}\n"
             . "Сумма: {$amount} {$currency}\n"
             . "Приз: {$tierIcon} <b>" . htmlspecialchars((string) ($prize['label'] ?? '?')) . "</b>\n"
             . "Зачислено в: {$kindLabel}\n"
             . "Сумма приза: " . number_format($prizeAmount, 0, '.', ' ') . "\n"
             . ($given ? '' : "<i>⚠ Игрок с этим ником не найден в БД — приз не зачислен, выдай вручную.</i>\n")
             . "Время: {$paidAt}\n"
             . "ID платежа: <code>{$paymentId}</code>\n"
             . "Order ID: <code>{$orderId}</code>";
    sendTelegram($tg['token'], $tg['chat_id'], $message);
}
