<?php
$config = [
    'core' => [
        'title' => 'CORE BONUS',
        'copyright_year' => date('Y'),
        'links' => [
            'main' => 'index',
            'forum' => '#',
            'donate' => 'donate',
            'roulette' => 'roulette',
            'forbes' => 'forbes',
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

    'db' => [
        'host_remote' => '80.242.59.112',
        'host_local'  => '127.0.0.1',
        'port'        => 3306,
        'name'        => 'gs339375',
        'user'        => 'gs339375',
        'pass'        => 'jVe4sI57zjfS',
        'charset'     => 'utf8mb4',
        'timeout'     => 10,
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

    /**
     * Настройки рейтинга «Forbes».
     *
     *  money_cols          — суммируются (COALESCE(col,0) + ...). Одна колонка — оставь один элемент.
     *  admin_cols          — игрок попадает в рейтинг, только если сумма этих колонок = 0.
     *                        Здесь перечисли всё, что отличает админа/хелпера/лидера от игрока.
     *                        Пустой массив = не фильтровать.
     *  exclude_nicknames   — точные ники, которые никогда не появятся в рейтинге.
     */
    'forbes' => [
        'table'             => 'players',
        'name_col'          => 'NickName',
        'skin_col'          => 'Skin',
        'money_cols'        => ['Cash', 'Bank'],
        'admin_cols'        => ['AdminLevel'],
        'exclude_nicknames' => [],
        'limit'             => 20,
        'cache_sec'         => 60,
    ],

    /**
     * Рулетка призов.
     *  price   — стоимость одного прокрута в рублях.
     *  prizes  — массив призов:
     *    label  — что показать в UI
     *    coins  — сколько монет начислить в players.Cash_Donate при выпадении
     *    weight — относительный вес. Шанс = weight / sum(weights).
     *    tier   — common | rare | epic | legendary (визуальный окрас)
     *    icon   — phosphor-icon (без префикса ph-)
     */
    'roulette' => [
        'price'  => 150,
        'prizes' => [
            ['label' => '50 монет',     'coins' => 50,    'weight' => 60, 'tier' => 'common',    'icon' => 'ph-coin'],
            ['label' => '150 монет',    'coins' => 150,   'weight' => 25, 'tier' => 'common',    'icon' => 'ph-coins'],
            ['label' => '500 монет',    'coins' => 500,   'weight' => 10, 'tier' => 'rare',      'icon' => 'ph-coins'],
            ['label' => '1 500 монет',  'coins' => 1500,  'weight' => 4,  'tier' => 'epic',      'icon' => 'ph-diamond'],
            ['label' => '10 000 монет', 'coins' => 10000, 'weight' => 1,  'tier' => 'legendary', 'icon' => 'ph-crown'],
        ],
    ],
];

/**
 * Создаёт PDO-соединение с БД. Сначала пробует удалённый хост, при неудаче — локальный.
 * Используется во всех местах, где нужен доступ к базе.
 */
if (!function_exists('db_connect_from_config')) {
    function db_connect_from_config(): PDO
    {
        global $config;
        $db = $config['db'];

        $hosts = [$db['host_remote'], $db['host_local']];
        $last  = null;

        foreach ($hosts as $host) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $host,
                    (int) $db['port'],
                    $db['name'],
                    $db['charset']
                );
                return new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => (int) ($db['timeout'] ?? 10),
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$db['charset']}",
                ]);
            } catch (PDOException $e) {
                $last = $e;
            }
        }

        throw $last ?? new RuntimeException('DB connect failed');
    }
}

if (!function_exists('db_pdo')) {
    function db_pdo(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            $pdo = db_connect_from_config();
        }
        return $pdo;
    }
}
