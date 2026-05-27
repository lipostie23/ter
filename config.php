<?php
$config = [
    'core' => [
        'title' => 'CORE BONUS',
        'copyright_year' => date('Y'),
        'links' => [
            'main' => 'index',
            'forum' => '#',
            'donate' => 'donate',
            'forbes' => '#',
            'telegram' => 'https://t.me/corebonu',
            'telegramlink' => 't.me/corebonus',
            'vk_support' => 'https://t.me/faggotvetmo',
            'vk_main' => 'https://vk.com/corebonus',
            'privacy' => 'privacy',
            'terms'   => 'terms',
            'launcher_download' => '#',
            'connect_download' => 'connect_core.zip',
            'radmir_download' => 'https://dl.hasslecdn.com/RADMIRLauncherSetup.exe',
        ],
        'server' => [
            'name' => 'CORE BONUS #1',
            'host' => 's1.core-bonus.ru',
            'port' => 7777,
            'max_online' => 1000,
            'total_accounts' => '1,000',
        ],
        'colors' => [
            'primary_blue' => '#0056b3',
            'accent_cyan'  => '#00d4ff',
            'btn_gradient' => '#007bff',
            'bg_color' => '#111',
            'text_white' => '#ffffff',
            'footer_bg' => '#08090b'
        ]
    ],

    'platega' => [
        'merchant_id'   => '661e120e-48cb-4292-83c3-bc8d96e1b6d5',
        'secret'        => 'MFknNG5SU3gBppoQurc0DDZ3DErB5QaoCxjOL617OfKDnqQZ6U0j4GYIzYGbOwHDGuaDu5OQTPAHka21xvKPEB4FngN8N9SZLyYo',
        'return_url'    => 'https://core-bonus.ru/donate_success.php',
        'failed_url'    => 'https://core-bonus.ru/donate_failed.php?reason=rejected',
        'callback_url'  => 'https://core-bonus.ru/platega_webhook.php',
        'rate'          => 3,
        'currency'      => 'RUB',
    ],

    'telegram_bot' => [
        'token'   => '8700184412:AAEsondY4TWqcE0m0l2jRmv6fsO6_zK4HEc',
        'chat_id' => '7696259360',
    ],
];
