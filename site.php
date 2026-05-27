<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

define('TG_BOT_TOKEN', '8670784710:AAGCD5qSQt9hlksAQxg4qf8uCw9n-HnPaFw');
define('LOGI_GAME_INGEST_TOKEN', 'Lgi_g9K4mPvq2NwX8bRtz1YhFc0JsAe6UdBo7');

$admin_ids = [6394731003, 987654321];
$channels = ['@fear_dev'];


$sticker_emoji = [
    'zamok' => ['id' => '5443038326535759644', 'char' => "\u{1F512}"],
    'ogonek' => ['id' => '5409379647089567417', 'char' => "\u{1F525}"],
    'dva' => ['id' => '5359770464128347330', 'char' => "2\u{FE0F}\u{20E3}"],
    'tri' => ['id' => '5359608157314233276', 'char' => "3\u{FE0F}\u{20E3}"],
    'pravo' => ['id' => '5339061961483100987', 'char' => "\u{25B6}\u{FE0F}"],
    'ostorojno' => ['id' => '5447644880824181073', 'char' => "\u{26A0}\u{FE0F}"],
    'okey' => ['id' => '5206607081334906820', 'char' => "\u{1F44C}"],
    'pencil' => ['id' => '5395444784611480792', 'char' => "\u{270F}\u{FE0F}"],
    'telegram' => ['id' => '5330237710655306682', 'char' => "\u{2708}\u{FE0F}"],
    '!' => ['id' => '5274099962655816924', 'char' => "\u{2757}"],
    'bloknot' => ['id' => '5413879192267805083', 'char' => "\u{1F4D3}"],
];

function sticker_add(string $text): string
{
    global $sticker_emoji;
    foreach ($sticker_emoji as $name => $row) {
        $eid = htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8');
        $ch = htmlspecialchars((string) $row['char'], ENT_QUOTES, 'UTF-8');
        $text = str_replace('{' . $name . '}', '<tg-emoji emoji-id="' . $eid . '">' . $ch . '</tg-emoji>', $text);
    }
    return $text;
}

function normalizeTelegramNickname(string $nickname): string
{
    $nickname = trim($nickname);
    $nickname = ltrim($nickname, '@');
    return $nickname;
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

function db_ensure_telegram_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `Telegram` (
            `ID_Telegram` VARCHAR(32) NOT NULL,
            `NickName_Telegram` VARCHAR(64) NOT NULL DEFAULT \'\',
            `Code` VARCHAR(16) NOT NULL DEFAULT \'\',
            PRIMARY KEY (`ID_Telegram`),
            KEY `idx_code` (`Code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $ready = true;
}

function db_connect(): PDO
{
    global $config;
    $cfg = $config['db'];

    $hosts = [
        ['host' => $cfg['host_remote'], 'port' => (int) $cfg['port']],
        ['host' => $cfg['host_local'],  'port' => (int) $cfg['port']],
    ];
    $last = null;
    foreach ($hosts as $row) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $row['host'],
                $row['port'],
                $cfg['name'],
                $cfg['charset']
            );
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => (int) ($cfg['timeout'] ?? 10),
            ]);
            db_ensure_telegram_table($pdo);
            return $pdo;
        } catch (PDOException $e) {
            $last = $e;
            write_log_file('db_connect.log', date('Y-m-d H:i:s') . " host={$row['host']} err=" . $e->getMessage() . "\n");
        }
    }
    throw $last ?? new RuntimeException('DB connect failed');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect();
    }
    return $pdo;
}

function telegram_chat_id($chatId): string
{
    return is_numeric($chatId) ? (string) (int) $chatId : (string) $chatId;
}

function log_line(string $type, string $nickname, $chatId, $code): void
{
    $line = date('[d.m.Y H:i:s] ') . "type={$type} nick={$nickname} chatId={$chatId} code={$code}\n";
    write_log_file('debug.log', $line);
}

function server_log_line(string $request, string $response): void
{
    $line = date('[d.m.Y H:i:s] ') . "request={$request} response={$response}\n";
    write_log_file('server.log', $line);
}

function tg_log_send($chatId, int $attempt, $rawResponse, string $curlErr): void
{
    $tail = $rawResponse !== false && $rawResponse !== null
        ? substr(preg_replace('/\s+/', ' ', (string) $rawResponse), 0, 500)
        : '';
    $line = date('Y-m-d H:i:s') . " | chat_id={$chatId} | try={$attempt} | curl_err=" . ($curlErr ?: '-') . " | api={$tail}\n";
    write_log_file('tg_api.log', $line);
}

function write_log_file(string $filename, string $line): void
{
    $primary = __DIR__ . '/' . $filename;
    $ok = @file_put_contents($primary, $line, FILE_APPEND | LOCK_EX);
    if ($ok !== false) {
        return;
    }

    $fallback = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'core_bonus_' . $filename;
    @file_put_contents($fallback, $line, FILE_APPEND | LOCK_EX);
}

function sticker_plain_fallback(string $text): string
{
    $text = preg_replace('/<tg-emoji[^>]*>[\s\S]*?<\/tg-emoji>/iu', '', $text);
    $text = preg_replace('/\{[a-z0-9!?]+\}/i', '', $text);
    return trim($text);
}

function tg_json_flags(): int
{
    $f = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $f |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    return $f;
}

function tg_api_post(string $method, array $fields): string
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/' . $method;
    $payload = json_encode($fields, tg_json_flags());
    if ($payload === false) {
        return '';
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return (string) $r;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json; charset=UTF-8\r\nContent-Length: " . strlen($payload),
            'content' => $payload,
            'timeout' => 30,
        ],
        'ssl' => ['verify_peer' => false],
    ]);
    $r = @file_get_contents($url, false, $ctx);
    return $r !== false ? (string) $r : '';
}

function tg_answerCallbackQuery(?string $callbackQueryId, string $text = '', bool $showAlert = false): void
{
    if ($callbackQueryId === null || $callbackQueryId === '') {
        return;
    }
    $fields = ['callback_query_id' => $callbackQueryId];
    if ($text !== '') {
        $fields['text'] = $text;
        $fields['show_alert'] = $showAlert;
    }
    tg_api_post('answerCallbackQuery', $fields);
}

function sub_check_log(string $line): void
{
    write_log_file('sub_check.log', date('Y-m-d H:i:s') . ' ' . $line . "\n");
}

function tg_webhook_log(string $line): void
{
    write_log_file('webhook.log', date('Y-m-d H:i:s') . ' ' . $line . "\n");
}

function runIoDebug(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $targets = [
        '__DIR__' => __DIR__,
        'sys_get_temp_dir' => sys_get_temp_dir(),
        'cwd' => (string) getcwd(),
    ];

    $result = [
        'php_version' => PHP_VERSION,
        'open_basedir' => ini_get('open_basedir'),
        'db' => ['ok' => false, 'error' => ''],
        'targets' => [],
    ];

    try {
        $pdo = db();
        $pdo->query('SELECT 1');
        $result['db']['ok'] = true;
    } catch (Throwable $e) {
        $result['db']['error'] = $e->getMessage();
    }

    foreach ($targets as $name => $dir) {
        $dir = rtrim((string) $dir, '/\\');
        $testFile = $dir . DIRECTORY_SEPARATOR . 'io_debug_test.log';
        $row = date('Y-m-d H:i:s') . " io_debug {$name}\n";
        $write = @file_put_contents($testFile, $row, FILE_APPEND | LOCK_EX);
        $result['targets'][$name] = [
            'path' => $dir,
            'is_dir' => is_dir($dir),
            'is_writable' => is_writable($dir),
            'write_ok' => $write !== false,
            'write_error' => $write === false ? (error_get_last()['message'] ?? 'unknown') : '',
            'test_file' => $testFile,
        ];
    }

    echo json_encode($result, tg_json_flags());
    exit;
}

function tg_sendMessage_raw($chatId, string $text, ?array $keyboard, string $parse_mode): array
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendMessage';
    $cid = is_numeric($chatId) ? (int) $chatId : $chatId;
    $body = ['chat_id' => $cid, 'text' => $text];
    if ($parse_mode !== '') {
        $body['parse_mode'] = $parse_mode;
    }
    if ($keyboard !== null) {
        $body['reply_markup'] = json_encode($keyboard, tg_json_flags());
    }
    $payload = json_encode($body, tg_json_flags());
    if ($payload === false) {
        return ['ok' => false, 'raw' => '', 'curl' => 'json_encode_fail'];
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $cerr = (string) curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json; charset=UTF-8\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => 30,
            ],
            'ssl' => ['verify_peer' => false],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $cerr = $raw === false ? 'file_get_contents_fail' : '';
        $raw = $raw !== false ? (string) $raw : '';
    }
    $j = json_decode((string) $raw, true);
    $ok = is_array($j) && !empty($j['ok']);
    return ['ok' => $ok, 'raw' => (string) $raw, 'curl' => $cerr];
}

function tg_sendMessage($chatId, string $text, ?array $keyboard = null, string $parse_mode = 'HTML')
{
    $attempt = 0;
    $withStickers = sticker_add($text);
    $variants = [
        [$withStickers, $parse_mode],
        [strip_tags($withStickers), ''],
    ];
    $lastRaw = '';
    $lastCurl = '';
    foreach ($variants as $pair) {
        $attempt++;
        $t = $pair[0];
        $pm = $pair[1];
        $r = tg_sendMessage_raw($chatId, $t, $keyboard, $pm);
        $lastRaw = $r['raw'];
        $lastCurl = $r['curl'];
        tg_log_send($chatId, $attempt, $r['raw'], $r['curl']);
        if (!empty($r['ok'])) {
            return $r['raw'];
        }
    }
    return $lastRaw !== '' ? $lastRaw : false;
}

function tg_sendPhoto($chatId, string $photo, string $caption = '', ?array $keyboard = null)
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendPhoto';
    $params = [
        'chat_id' => $chatId,
        'photo' => $photo,
        'caption' => sticker_add($caption),
        'parse_mode' => 'HTML',
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode($keyboard, tg_json_flags());
    }
    $options = ['http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($params),
        'timeout' => 30,
    ], 'ssl' => ['verify_peer' => false]];
    return @file_get_contents($url, false, stream_context_create($options));
}

function tg_sendDocument($chatId, string $document, string $caption = '', ?array $keyboard = null)
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendDocument';
    $params = [
        'chat_id' => $chatId,
        'document' => $document,
        'caption' => sticker_add($caption),
        'parse_mode' => 'HTML',
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode($keyboard, tg_json_flags());
    }
    $options = ['http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($params),
        'timeout' => 30,
    ], 'ssl' => ['verify_peer' => false]];
    return @file_get_contents($url, false, stream_context_create($options));
}

function tg_sendAnimation($chatId, string $animation, string $caption = '', ?array $keyboard = null)
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendAnimation';
    $params = [
        'chat_id' => $chatId,
        'animation' => $animation,
        'caption' => sticker_add($caption),
        'parse_mode' => 'HTML',
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode($keyboard, tg_json_flags());
    }
    $options = ['http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($params),
        'timeout' => 30,
    ], 'ssl' => ['verify_peer' => false]];
    return @file_get_contents($url, false, stream_context_create($options));
}

function tg_removeKeyboard($chatId, $messageId): void
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/editMessageReplyMarkup';
    $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode(new stdClass())];
    $options = ['http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($params),
        'timeout' => 15,
    ], 'ssl' => ['verify_peer' => false]];
    @file_get_contents($url, false, stream_context_create($options));
}

function tg_deleteMessage($chatId, $messageId): void
{
    $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/deleteMessage';
    $params = ['chat_id' => $chatId, 'message_id' => $messageId];
    $options = ['http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($params),
        'timeout' => 15,
    ], 'ssl' => ['verify_peer' => false]];
    @file_get_contents($url, false, stream_context_create($options));
}

function isSubscribed($userId): bool
{
    global $channels;
    $userId = (int) $userId;
    foreach ($channels as $channel) {
        $q = http_build_query(['chat_id' => $channel, 'user_id' => $userId]);
        $url = 'https://api.telegram.org/bot' . TG_BOT_TOKEN . '/getChatMember?' . $q;
        $raw = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 15],
            'ssl' => ['verify_peer' => false],
        ]));
        $res = json_decode((string) $raw, true);
        if (!is_array($res)) {
            sub_check_log("channel={$channel} user={$userId} decode_fail raw=" . substr((string) $raw, 0, 200));
            return false;
        }
        if (empty($res['ok'])) {
            $desc = isset($res['description']) ? (string) $res['description'] : '';
            sub_check_log("channel={$channel} user={$userId} api_fail {$desc}");
            return false;
        }
        $status = $res['result']['status'] ?? '';
        $memberOk = in_array($status, ['member', 'creator', 'administrator'], true);
        if ($status === 'restricted' && !empty($res['result']['is_member'])) {
            $memberOk = true;
        }
        if (!$memberOk) {
            sub_check_log("channel={$channel} user={$userId} status={$status}");
            return false;
        }
    }
    return true;
}

function sendStartMessage($chatId): void
{
    global $channels;
    $channelsText = implode(', ', $channels);
    $text = "{telegram} При помощи телеграм-помощника вы сможете обезопасить аккаунт от взлома и восстановить аккаунт в случае утраты пароля.\n\n"
        . "Для привязки игрового аккаунта, воспользуйтесь кнопкой <b>«Получить код»</b>.\n\n"
        . "Перед началом взаимодействия, не забудьте подписаться на наш новостной канал {$channelsText}";
    $keyboard = ['inline_keyboard' => [[['text' => 'Получить код', 'callback_data' => 'get_code']]]];
    tg_sendMessage($chatId, $text, $keyboard);
}

function sendSubscribeMessage($chatId): void
{
    global $channels;
    $channelsText = implode(', ', $channels);
    $text = "{ostorojno} Для начала взаимодействия с помощником, вам необходимо подписаться на наш новостной канал.\n{pravo} Нажмите для быстрого перехода: {$channelsText}";
    $keyboard = ['inline_keyboard' => [
        [['text' => 'Подписаться 1', 'url' => 'https://t.me/fear_dev']],
        [['text' => 'Проверить подписку', 'callback_data' => 'check_sub']],
    ]];
    tg_sendMessage($chatId, $text, $keyboard);
}

function generateCodeForChat($chatId, string $nickname): int
{
    $pdo = db();
    $chatKey = telegram_chat_id($chatId);
    $nickname = normalizeTelegramNickname($nickname);
    $code = random_int(100000, 999999);
    $stmt = $pdo->prepare('DELETE FROM `Telegram` WHERE `ID_Telegram`=?');
    $stmt->execute([$chatKey]);
    $stmt = $pdo->prepare('INSERT INTO `Telegram` (`ID_Telegram`,`NickName_Telegram`,`Code`) VALUES (?,?,?)');
    $stmt->execute([$chatKey, $nickname, (string) $code]);
    return $code;
}

function sendGeneratedCode($chatId, string $username = '', $userId = 0): void
{
    try {
        $code = random_int(100000, 999999);
        $pdo = db();
        $nickname = normalizeTelegramNickname($username);
        if ($nickname === '') {
            $nickname = (string) $userId;
        }
        $chatKey = telegram_chat_id($chatId);
        $stmt = $pdo->prepare('DELETE FROM `Telegram` WHERE `ID_Telegram`=?');
        $stmt->execute([$chatKey]);
        $stmt = $pdo->prepare('INSERT INTO `Telegram` (`ID_Telegram`,`NickName_Telegram`,`Code`) VALUES (?,?,?)');
        $stmt->execute([$chatKey, $nickname, (string) $code]);
        $text = "{okey} Ваш проверочный код - <b>{$code}</b>\n\n"
            . "<b>1</b>. Выполните вход в свой игровой аккаунт.\n"
            . "<b>2</b>. В меню персонажа (/mn) выберите пункт настройки.\n"
            . "<b>3</b>. В настройках выберите пункт «Привязать Telegram».\n"
            . "<b>4</b>. Введите проверочный код и нажмите на кнопку «Подтвердить».\n"
            . "<b>5</b>. Переключите уведомления c Почты на Telegram.\n\n"
            . 'Если вы привязали аккаунт корректно, помощник пришлет сообщение об успешной привязке.';
        tg_sendMessage($chatId, $text);
    } catch (Throwable $e) {
        write_log_file('tg_code_error.log', date('Y-m-d H:i:s') . ' chat=' . $chatId . ' err=' . $e->getMessage() . "\n");
        tg_sendMessage($chatId, '{ostorojno} Ошибка генерации кода, попробуйте позже или напишите администрации.');
    }
}

function sendBindSuccess($chatId, string $nickname): void
{
    $text = "{okey} Аккаунт <b>{$nickname}</b> успешно привязан к Телеграм помощнику.";
    tg_sendMessage($chatId, $text);
}

function sendUnbindCode($chatId, string $nickname, $code): void
{
    $text = "Отвязка Telegram\nДля отвязки аккаунта {$nickname} используйте код: {$code}";
    tg_sendMessage($chatId, $text);
}

function sendAccountLinked($chatId, string $nickname): void
{
    $text = "{!} В Ваш аккаунт <b>{$nickname}</b> совершен подозрительный вход.\n"
        . 'При нажатии кнопки «Это не я» мы заблокируем аккаунт, чтобы злоумышленник не завладел Вашим игровым имуществом.';
    tg_sendMessage($chatId, $text);
}

function sendLoginCode($chatId, string $nickname, $code): void
{
    $text = "{ostorojno} С Вашего аккаунта <b>{$nickname}</b> на сервере поступил запрос на действие «Авторизация в игре». Код подтверждения: <b>{$code}</b>\n"
        . 'Никому не передавайте этот код, даже администрации проекта. Если Вы не запрашивали это действие, срочно обратитесь в техническую поддержку.';
    tg_sendMessage($chatId, $text);
}

function getAllChatIds(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT DISTINCT `ID_Telegram` FROM `Telegram` WHERE `ID_Telegram` IS NOT NULL');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function sendMessageToAll($adminId, string $message, ?array $media = null): void
{
    global $admin_ids;
    if (!in_array($adminId, $admin_ids, true)) {
        return;
    }
    $chatIds = getAllChatIds();
    if (empty($chatIds)) {
        tg_sendMessage($adminId, 'Нет пользователей для рассылки.');
        return;
    }
    $successCount = 0;
    $failCount = 0;
    foreach ($chatIds as $cid) {
        try {
            if ($media !== null) {
                $mediaType = $media['type'] ?? 'text';
                $mediaFile = $media['file'] ?? '';
                switch ($mediaType) {
                    case 'photo':
                        tg_sendPhoto($cid, $mediaFile, $message);
                        break;
                    case 'document':
                        tg_sendDocument($cid, $mediaFile, $message);
                        break;
                    case 'animation':
                        tg_sendAnimation($cid, $mediaFile, $message);
                        break;
                    default:
                        tg_sendMessage($cid, $message);
                }
            } else {
                tg_sendMessage($cid, $message);
            }
            $successCount++;
        } catch (Exception $e) {
            $failCount++;
            log_line('send_fail', '', $cid, $e->getMessage());
        }
        usleep(50000);
    }
    $report = "{bloknot} <i>Рассылка завершена!</i>\n{okey} Успешно: {$successCount}\n{ostorojno} Ошибок: {$failCount}";
    tg_sendMessage($adminId, $report);
}

function handleLogIngestRequest(): bool
{
    if (!isset($_GET['params'])) {
        return false;
    }
    $encoded = (string) $_GET['params'];
    $decoded = rawurldecode($encoded);
    if ($decoded === $encoded) {
        $decoded = urldecode($encoded);
    }
    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        http_response_code(400);
        $response = json_encode(['success' => false, 'error' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
        server_log_line($decoded, $response);
        echo $response;
        return true;
    }
    $desc = isset($payload['desc']) ? (string) $payload['desc'] : '';
    if (preg_match('/^\d+\|/', $desc)) {
        return false;
    }
    $token = $payload['token'] ?? '';
    if ($token !== LOGI_GAME_INGEST_TOKEN) {
        http_response_code(403);
        $response = json_encode(['success' => false, 'error' => 'invalid_token'], JSON_UNESCAPED_UNICODE);
        server_log_line($decoded, $response);
        echo $response;
        return true;
    }
    $type = isset($payload['type']) ? (int) $payload['type'] : 0;
    $line = date('[d.m.Y H:i:s] ') . "type={$type} desc={$desc}\n";
    file_put_contents(__DIR__ . '/sendlog.log', $line, FILE_APPEND);
    http_response_code(200);
    $response = json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    server_log_line($decoded, $response);
    echo $response;
    return true;
}

function handleParamsGameFromMod(): bool
{
    if (!isset($_GET['params'])) {
        return false;
    }
    $encoded = (string) $_GET['params'];
    $decoded = rawurldecode($encoded);
    if ($decoded === $encoded) {
        $decoded = urldecode($encoded);
    }
    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        return false;
    }
    $token = $payload['token'] ?? '';
    if ($token !== LOGI_GAME_INGEST_TOKEN) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'err:token';
        return true;
    }
    $desc = isset($payload['desc']) ? (string) $payload['desc'] : '';
    if (!preg_match('/^(\d+)\|/', $desc)) {
        return false;
    }
    $parts = explode('|', $desc, 3);
    $save = $_GET;
    $_GET['type'] = (int) ($payload['type'] ?? 0);
    $_GET['chatId'] = trim($parts[0] ?? '');
    $_GET['nickname'] = trim($parts[1] ?? '');
    $_GET['code_verify'] = (string) ($parts[2] ?? '');
    handleGameRequest();
    $_GET = $save;
    return true;
}

function handleGameRequest(): void
{
    $type = isset($_GET['type']) ? (int) $_GET['type'] : null;
    $chatId = $_GET['chatId'] ?? null;
    $nickname = $_GET['nickname'] ?? null;
    $codeVerify = $_GET['code_verify'] ?? null;

    if ($type === null || $chatId === null || $nickname === null) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Missing parameters';
        return;
    }

    switch ($type) {
        case 0:
            sendBindSuccess($chatId, (string) $nickname);
            log_line('0', (string) $nickname, $chatId, 0);
            break;
        case 1:
            if ($codeVerify === '' || $codeVerify === null) {
                $codeVerify = generateCodeForChat($chatId, (string) $nickname);
            }
            sendUnbindCode($chatId, (string) $nickname, $codeVerify);
            log_line('1', (string) $nickname, $chatId, $codeVerify);
            break;
        case 2:
            if ($codeVerify === '' || $codeVerify === null) {
                $codeVerify = generateCodeForChat($chatId, (string) $nickname);
            }
            sendLoginCode($chatId, (string) $nickname, (string) $codeVerify);
            log_line('2', (string) $nickname, $chatId, $codeVerify);
            break;
        case 3:
            sendAccountLinked($chatId, (string) $nickname);
            log_line('3', (string) $nickname, $chatId, 0);
            break;
        default:
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Unknown type';
            return;
    }
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
}

function handleTelegramWebhook(string $rawInput): void
{
    $update = json_decode($rawInput, true);
    if (!$update) {
        tg_webhook_log('invalid_update_json raw=' . substr($rawInput, 0, 500));
        return;
    }
    $message = $update['message'] ?? null;
    $callback = $update['callback_query'] ?? null;

    if ($message) {
        $chatId = $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $userId = $message['from']['id'];
        $username = $message['from']['username'] ?? '';

        $firstToken = $text !== '' ? explode(' ', $text, 2)[0] : '';
        $command = strtolower((string) preg_replace('/@.*/', '', $firstToken));
        tg_webhook_log("message chat_id={$chatId} user_id={$userId} username={$username} command={$command} text=" . substr($text, 0, 200));

        if ($command === '/start') {
            $isSub = isSubscribed($message['from']['id']);
            tg_webhook_log("start chat_id={$chatId} user_id={$userId} subscribed=" . ($isSub ? '1' : '0'));
            if ($isSub) {
                sendStartMessage($chatId);
                tg_webhook_log("start_reply chat_id={$chatId} action=start_message");
            } else {
                sendSubscribeMessage($chatId);
                tg_webhook_log("start_reply chat_id={$chatId} action=subscribe_message");
            }
            return;
        }

        if ($command !== '' && $command[0] === '/') {
            tg_webhook_log("ignored_command chat_id={$chatId} user_id={$userId} command={$command}");
        }
    }

    if ($callback) {
        $cqid = $callback['id'] ?? '';
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $userId = $callback['from']['id'];
        $username = $callback['from']['username'] ?? '';
        $data = $callback['data'] ?? '';
        tg_webhook_log("callback chat_id={$chatId} user_id={$userId} username={$username} data={$data}");

        if ($data === 'get_code' || $data === 'check_sub') {
            tg_answerCallbackQuery($cqid);
            tg_deleteMessage($chatId, $messageId);
            if (isSubscribed($userId)) {
                sendGeneratedCode($chatId, (string) $username, (int) $userId);
                tg_webhook_log("callback_reply chat_id={$chatId} user_id={$userId} action=send_code");
            } else {
                sendSubscribeMessage($chatId);
                tg_webhook_log("callback_reply chat_id={$chatId} user_id={$userId} action=subscribe_message");
            }
        }
    }
}

$rawInput = file_get_contents('php://input') ?: '';
tg_webhook_log('request method=' . ($_SERVER['REQUEST_METHOD'] ?? '-') . ' query=' . ($_SERVER['QUERY_STRING'] ?? '-') . ' raw_len=' . strlen($rawInput));

if (isset($_GET['debug_io']) && $_GET['debug_io'] === '1') {
    runIoDebug();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    handleTelegramWebhook($rawInput);
    echo 'ok';
    exit;
}

if (!handleParamsGameFromMod()) {
    if (!handleLogIngestRequest()) {
        handleGameRequest();
    }
}
