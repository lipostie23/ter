<?php
require_once 'config.php';

$nickname    = trim($_POST['nickname']    ?? '');
$amount      = (int)($_POST['amount']    ?? 0);
$server_name = trim($_POST['server_name'] ?? $config['core']['server']['name']);
$method      = trim($_POST['method']     ?? 'sbp');

if (!$nickname || $amount < 10 || $amount > 100000) {
    header('Location: donate.php?error=invalid');
    exit;
}

$pt = $config['platega'];

$orderId = 'order_' . time() . '_' . bin2hex(random_bytes(4));

session_start();
$_SESSION['pending_payment'] = [
    'order_id'   => $orderId,
    'nickname'   => $nickname,
    'server'     => $server_name,
    'amount'     => $amount,
    'coins'      => $amount * $pt['rate'],
    'method'     => $method,
    'created_at' => date('Y-m-d H:i:s'),
];

$paymentMethodId = 2;

$payload = [
    'paymentMethod'  => $paymentMethodId,
    'paymentDetails' => [
        'amount'   => $amount,
        'currency' => $pt['currency'],
    ],
    'description' => "Донат {$nickname} · {$server_name} · {$amount}₽",
    'return'      => $pt['return_url'],
    'failedUrl'   => $pt['failed_url'],
    'payload'     => json_encode([
        'order_id' => $orderId,
        'nickname' => $nickname,
        'server'   => $server_name,
        'coins'    => $amount * $pt['rate'],
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
} else {
    $logDir = __DIR__ . '/logs';
    @mkdir($logDir, 0755, true);
    file_put_contents(
        $logDir . '/payment_errors.log',
        date('Y-m-d H:i:s') . " | HTTP {$httpCode} | cURL: {$curlError} | " . $response . "\n",
        FILE_APPEND | LOCK_EX
    );

    header('Location: donate_failed.php?reason=error');
    exit;
}
