<?php
require_once 'config.php';
require_once 'pending_payment.php';

$nickname    = trim($_POST['nickname']    ?? '');
$server_name = trim($_POST['server_name'] ?? $config['core']['server']['name']);
$method      = trim($_POST['method']      ?? 'sbp');
$purpose     = ($_POST['purpose'] ?? 'donate') === 'roulette' ? 'roulette' : 'donate';

if (!$nickname || !preg_match('/^[A-Za-z0-9_]{2,24}$/', $nickname)) {
    header('Location: ' . ($purpose === 'roulette' ? 'roulette.php?error=invalid' : 'donate.php?error=invalid'));
    exit;
}

$pt = $config['platega'];

if ($purpose === 'roulette') {
    // Цену для рулетки задаёт сервер — клиент её не подменит.
    $amount = (int) ($config['roulette']['price'] ?? 150);
    $coins  = 0; // монеты определит призовая логика в вебхуке
    $description = "Рулетка · {$nickname} · {$server_name} · {$amount} {$pt['currency']}";
} else {
    $amount = (int) ($_POST['amount'] ?? 0);
    if ($amount < 10 || $amount > 100000) {
        header('Location: donate.php?error=invalid');
        exit;
    }
    $coins = $amount * (int) $pt['rate'];
    $description = "Донат {$nickname} · {$server_name} · {$amount}₽";
}

$orderId = 'order_' . time() . '_' . bin2hex(random_bytes(4));

session_start();
$pendingPayment = [
    'order_id'   => $orderId,
    'purpose'    => $purpose,
    'nickname'   => $nickname,
    'server'     => $server_name,
    'amount'     => $amount,
    'coins'      => $coins,
    'method'     => $method,
    'created_at' => date('Y-m-d H:i:s'),
];
$_SESSION['pending_payment'] = $pendingPayment;

/* Параллельно сохраняем в БД, чтобы webhook от Platega (у него нашей сессии нет)
   мог достать nickname/coins по order_id, даже если Platega не вернёт payload. */
pending_payment_save($pendingPayment);

$paymentMethodId = 2;

$returnUrl = $purpose === 'roulette'
    ? 'https://core-bonus.ru/roulette.php?spin=' . rawurlencode($orderId)
    : $pt['return_url'];

$payload = [
    'paymentMethod'  => $paymentMethodId,
    'paymentDetails' => [
        'amount'   => $amount,
        'currency' => $pt['currency'],
    ],
    'description' => $description,
    'return'      => $returnUrl,
    'failedUrl'   => $pt['failed_url'],
    'payload'     => json_encode([
        'order_id' => $orderId,
        'purpose'  => $purpose,
        'nickname' => $nickname,
        'server'   => $server_name,
        'coins'    => $coins,
        'method'   => $method,
    ]),
];

$ch = curl_init('https://app.platega.io/transaction/process');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-MerchantId: ' . $pt['merchant_id'],
        'X-Secret: '     . $pt['secret'],
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT    => 15,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['redirect'])) {
    $_SESSION['pending_payment']['transaction_id'] = $data['transactionId'] ?? '';
    header('Location: ' . $data['redirect']);
    exit;
}

$logDir = __DIR__ . '/logs';
@mkdir($logDir, 0755, true);
file_put_contents(
    $logDir . '/payment_errors.log',
    date('Y-m-d H:i:s') . " | purpose={$purpose} | HTTP {$httpCode} | cURL: {$curlError} | " . $response . "\n",
    FILE_APPEND | LOCK_EX
);

header('Location: donate_failed.php?reason=error');
exit;
