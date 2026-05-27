<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = $config['core']['server']['host'];
$port = $config['core']['server']['port'];

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
    echo json_encode(['online' => 0, 'error' => 'no_response']);
} else {
    $logFile = __DIR__ . '/online_log.json';

    $log = [];
    if (file_exists($logFile)) {
        $log = json_decode(file_get_contents($logFile), true) ?? [];
    }

    $last = end($log);
    $lastOnline = $last ? $last['online'] : -1;

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

        file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    echo json_encode(['online' => $online]);
}
