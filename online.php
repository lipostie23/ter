<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=5');

$host = $config['core']['server']['host'];
$port = $config['core']['server']['port'];

/* ----------------------------------------------------------------------
 * Кэш на диске: повторные запросы в течение TTL не дёргают SAMP.
 * Защищает от DoS-amplification и снижает нагрузку на сокет.
 * -------------------------------------------------------------------- */
$cacheTtl  = 5; // секунд
$cacheDir  = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/online.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false && $cached !== '') {
        echo $cached;
        exit;
    }
}

function getSampOnline($host, $port, $timeout = 2) {
    $ip = gethostbyname($host);

    $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$socket) {
        return null;
    }

    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);
    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $timeout, 'usec' => 0]);

    $packet = 'SAMP';
    $parts = explode('.', $ip);
    foreach ($parts as $part) {
        $packet .= chr((int)$part);
    }
    $packet .= chr($port & 0xFF);
    $packet .= chr(($port >> 8) & 0xFF);
    $packet .= 'i';

    if (@socket_sendto($socket, $packet, strlen($packet), 0, $ip, $port) === false) {
        socket_close($socket);
        return null;
    }

    $response = '';
    if (@socket_recv($socket, $response, 512, 0) === false) {
        socket_close($socket);
        return null;
    }

    socket_close($socket);

    if (strlen($response) < 11) return null;

    $offset = 11;
    $offset += 1;
    $online = ord($response[$offset]) | (ord($response[$offset + 1]) << 8);

    return $online;
}

$online = getSampOnline($host, $port);

if ($online === null) {
    $payload = json_encode(['online' => 0, 'error' => 'no_response']);
    echo $payload;
    exit;
}

/* ----------------------------------------------------------------------
 *  Запись истории онлайна (только при изменении значения).
 *  flock на чтение+запись, чтобы избежать race condition.
 * -------------------------------------------------------------------- */
$logFile = __DIR__ . '/cache/online_log.json';
@mkdir(dirname($logFile), 0755, true);

$fp = @fopen($logFile, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $raw = stream_get_contents($fp);
    $log = $raw ? (json_decode($raw, true) ?? []) : [];

    $last = end($log);
    $lastOnline = $last ? (int) $last['online'] : -1;

    if ($online !== $lastOnline) {
        $log[] = [
            'online'    => $online,
            'change'    => $online - $lastOnline,
            'time'      => date('Y-m-d H:i:s'),
            'timestamp' => time(),
        ];
        if (count($log) > 10000) {
            $log = array_slice($log, -10000);
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    flock($fp, LOCK_UN);
    fclose($fp);
}

$payload = json_encode(['online' => $online]);

@mkdir($cacheDir, 0755, true);
@file_put_contents($cacheFile, $payload, LOCK_EX);

echo $payload;
