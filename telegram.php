<?php
require_once __DIR__ . '/config.php';

$links = $config['core']['links'] ?? [];
$url   = $links['telegram'] ?? '';
if ($url === '' && !empty($links['telegramlink'])) {
    $url = 'https://' . ltrim($links['telegramlink'], '/');
}
if ($url === '') {
    $url = 'https://t.me/';
}

$safeAttr = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$safeJs   = json_encode($url, JSON_UNESCAPED_SLASHES);

header('Location: ' . $url, true, 302);
?>
<!DOCTYPE html>
<meta charset="UTF-8">
<title>Redirecting…</title>
<script>window.location.href = <?= $safeJs ?>;</script>
<p>Если перенаправление не сработало — <a href="<?= $safeAttr ?>" target="_blank" rel="noopener">откройте Telegram</a>.</p>
