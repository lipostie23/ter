<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
$c = $config['core']; $l = $c['links'];

$sections = [
    [
        'icon' => 'ph-handshake',
        'title' => 'Принятие условий',
        'paragraphs' => [
            'Регистрируясь и используя сервис ' . $c['title'] . ', вы подтверждаете, что ознакомились с настоящим Соглашением и принимаете его условия в полном объёме.',
            'Если вы не согласны с любым из пунктов — прекратите использование сервиса. Дальнейшее использование расценивается как согласие.',
            'Соглашение распространяется на всех посетителей сайта, игроков сервера и пользователей телеграм-помощника.',
        ],
    ],
    [
        'icon' => 'ph-game-controller',
        'title' => 'Игровые услуги',
        'paragraphs' => [
            'Сервис предоставляет доступ к многопользовательскому игровому серверу и сопутствующим цифровым услугам.',
            'Игровая валюта, предметы, привилегии и иной игровой контент являются виртуальными объектами и не имеют материальной ценности вне рамок игры.',
            'Администрация вправе изменять игровой баланс, добавлять и удалять контент, а также корректировать правила без предварительного уведомления.',
        ],
    ],
    [
        'icon' => 'ph-user-circle',
        'title' => 'Аккаунт пользователя',
        'paragraphs' => ['При создании аккаунта пользователь обязуется:'],
        'list' => [
            'указывать достоверные данные при регистрации;',
            'хранить пароль и средства восстановления доступа в тайне;',
            'не передавать аккаунт третьим лицам и не использовать чужие учётные записи;',
            'самостоятельно нести ответственность за действия, совершённые с аккаунта.',
        ],
        'after' => 'Администрация не возмещает ущерб, возникший из-за компрометации аккаунта по вине пользователя.',
    ],
    [
        'icon' => 'ph-prohibit-inset',
        'title' => 'Запрещённые действия',
        'paragraphs' => ['Пользователю запрещается:'],
        'list' => [
            'использовать сторонние программы (читы, макросы, эксплойты), дающие нечестное преимущество;',
            'намеренно нарушать работу сервиса, проводить DDoS-атаки и иные технические атаки;',
            'публиковать оскорбления, угрозы, разжигание розни, нелегальный контент;',
            'вести коммерческую деятельность от имени сервиса без письменного согласия Администрации;',
            'продавать, обменивать или дарить игровые ценности за реальные деньги вне официальных каналов.',
        ],
        'after' => 'Нарушение влечёт ограничение доступа к сервису без возврата средств.',
    ],
    [
        'icon' => 'ph-credit-card',
        'title' => 'Платежи и пополнение баланса',
        'paragraphs' => [
            'Пополнение игрового баланса проходит через платёжного провайдера Platega. Администрация не хранит платёжные реквизиты пользователя.',
            'Стоимость и условия пакетов отображаются на странице оплаты до момента подтверждения платежа.',
            'После успешной оплаты внутриигровая валюта зачисляется автоматически в течение нескольких минут. При задержке свыше 30 минут необходимо обратиться в поддержку.',
        ],
    ],
    [
        'icon' => 'ph-arrow-u-down-left',
        'title' => 'Возврат средств',
        'paragraphs' => [
            'В связи с цифровой природой услуги возврат средств после зачисления валюты не производится.',
            'Возврат возможен в исключительных случаях:',
        ],
        'list' => [
            'двойное списание по вине платёжного провайдера;',
            'оплата произведена, но валюта не была зачислена в течение 24 часов и техподдержка не смогла устранить проблему;',
            'списание совершено мошенническим способом без ведома владельца карты (требуется заявление в банк).',
        ],
        'after' => 'Заявка на возврат рассматривается до 14 рабочих дней. Инициирование chargeback в обход поддержки приводит к блокировке аккаунта.',
    ],
    [
        'icon' => 'ph-shield-warning',
        'title' => 'Ограничение ответственности',
        'paragraphs' => [
            'Сервис предоставляется по принципу «как есть». Администрация не гарантирует бесперебойную и безошибочную работу.',
            'Администрация не несёт ответственности за временные технические сбои, действия третьих лиц, форс-мажорные обстоятельства и потерю игрового прогресса по техническим причинам.',
            'Совокупная ответственность Администрации в любом случае ограничена суммой, фактически уплаченной пользователем за последние 30 дней.',
        ],
    ],
    [
        'icon' => 'ph-copyright',
        'title' => 'Интеллектуальная собственность',
        'paragraphs' => [
            'Все материалы сервиса (код, графика, тексты, аудио, торговые марки) принадлежат Администрации или используются с согласия правообладателей.',
            'Копирование, распространение и модификация материалов без письменного разрешения запрещены.',
            'Контент, создаваемый пользователями (никнеймы, сообщения), остаётся их собственностью, но Администрации предоставляется право использовать его в рамках работы сервиса.',
        ],
    ],
    [
        'icon' => 'ph-prohibit',
        'title' => 'Блокировка аккаунта',
        'paragraphs' => ['Администрация вправе ограничить или заблокировать аккаунт в случае:'],
        'list' => [
            'нарушения положений настоящего Соглашения или внутренних правил сервера;',
            'выявления злоупотреблений в платёжной системе и попыток обхода правил;',
            'требований законодательства или платёжных провайдеров;',
            'отсутствия активности более 12 месяцев — с правом архивации данных.',
        ],
        'after' => 'Блокировка не освобождает пользователя от обязательств, возникших до момента её применения.',
    ],
    [
        'icon' => 'ph-arrows-clockwise',
        'title' => 'Изменение условий',
        'paragraphs' => [
            'Администрация вправе вносить изменения в Соглашение в одностороннем порядке.',
            'Актуальная версия публикуется на этой странице. Существенные изменения сопровождаются уведомлением в Telegram-канале сервиса.',
            'Продолжение использования сервиса после публикации изменений означает согласие с новой редакцией.',
        ],
    ],
    [
        'icon' => 'ph-headset',
        'title' => 'Поддержка и контакты',
        'paragraphs' => [
            'По всем вопросам и спорным ситуациям обращайтесь в службу поддержки. Срок рассмотрения обращений — до 72 часов в рабочие дни.',
            'Перед обращением рекомендуется проверить раздел частых вопросов и текущие объявления сервиса.',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Пользовательское соглашение', '
    .legal-page { max-width: 820px; margin: 0 auto; padding: 110px 14px 30px; }

    .legal-hero { text-align: center; margin-bottom: 32px; padding: 32px 26px; }
    .legal-hero .ic-big { width: 60px; height: 60px; margin: 0 auto 14px; border-radius: 16px; background: var(--accent); color: var(--text-inverse); display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .legal-hero .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .legal-hero h1 { font-family: \'Space Grotesk\', sans-serif; font-size: clamp(26px, 4.5vw, 40px); font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin: 12px 0; }
    .legal-hero p { color: var(--text-secondary); font-size: 14px; line-height: 1.6; max-width: 560px; margin: 0 auto; }
    .legal-hero .meta { display: inline-flex; align-items: center; gap: 8px; margin-top: 14px; padding: 5px 12px; border-radius: 999px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); font-size: 11px; color: var(--text-muted); font-family: \'JetBrains Mono\', monospace; }

    .legal-section { padding: 26px 30px; margin-bottom: 12px; transition: transform 0.3s; }
    .legal-section:hover { transform: translateY(-2px); }
    .legal-section .head { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
    .legal-section .head .ic { width: 38px; height: 38px; border-radius: 11px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 19px; color: var(--text-primary); }
    .legal-section .head .num { font-family: \'JetBrains Mono\', monospace; font-size: 11px; color: var(--text-muted); padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); margin-left: auto; }
    .legal-section h2 { font-family: \'Space Grotesk\', sans-serif; font-size: 18px; font-weight: 600; letter-spacing: -0.01em; color: var(--text-primary); }
    .legal-section p { color: var(--text-secondary); font-size: 13.5px; line-height: 1.75; margin-bottom: 10px; }
    .legal-section p:last-child { margin-bottom: 0; }
    .legal-section ul { list-style: none; padding: 0; margin: 8px 0 12px; }
    .legal-section ul li { padding: 8px 0 8px 22px; color: var(--text-secondary); font-size: 13.5px; line-height: 1.6; position: relative; }
    .legal-section ul li::before { content: \'\'; position: absolute; left: 4px; top: 17px; width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,0.45); }

    .accept-card { padding: 24px 28px; margin: 14px 0; text-align: center; }
    .accept-card p { color: var(--text-secondary); font-size: 13.5px; line-height: 1.7; }
    .accept-card strong { color: var(--text-primary); font-weight: 700; padding: 2px 8px; background: rgba(255,255,255,0.08); border-radius: 6px; border: 1px solid var(--glass-border); font-family: \'JetBrains Mono\', monospace; }

    .back-bar { margin-top: 24px; display: flex; justify-content: center; }

    @media (max-width: 600px) {
        .legal-section { padding: 22px 18px; }
        .legal-hero { padding: 26px 20px; }
        .accept-card { padding: 22px 18px; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header(); ?>
<?php render_tg_float(); ?>

<section class="legal-page">
    <div class="legal-hero glass-strong reveal">
        <div class="ic-big"><i class="ph ph-file-text"></i></div>
        <div class="eyebrow">Документ</div>
        <h1>Пользовательское соглашение</h1>
        <p>Соглашение регулирует порядок использования сервиса <?= htmlspecialchars($c['title']) ?>, права и обязанности сторон.</p>
        <div class="meta"><i class="ph ph-clock"></i> Редакция от <?= htmlspecialchars(date('d.m.Y')) ?></div>
    </div>

    <?php foreach ($sections as $i => $s): ?>
        <article class="legal-section glass reveal delay-<?= min(5, $i + 1) ?>">
            <div class="head">
                <div class="ic"><i class="ph <?= htmlspecialchars($s['icon']) ?>"></i></div>
                <h2><?= htmlspecialchars($s['title']) ?></h2>
                <span class="num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            </div>
            <?php foreach (($s['paragraphs'] ?? []) as $p): ?>
                <p><?= htmlspecialchars($p) ?></p>
            <?php endforeach; ?>
            <?php if (!empty($s['list'])): ?>
                <ul>
                    <?php foreach ($s['list'] as $li): ?>
                        <li><?= htmlspecialchars($li) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($s['after'])): ?>
                <p><?= htmlspecialchars($s['after']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <div class="accept-card glass reveal delay-5">
        <p>Регистрируясь на сервере и/или производя оплату, вы подтверждаете принятие условий настоящего Соглашения. Команда <strong>/agree</strong> в игре также фиксирует согласие.</p>
    </div>

    <div class="back-bar reveal delay-5">
        <a href="<?= htmlspecialchars($l['main']) ?>" class="btn btn-primary"><i class="ph ph-arrow-left"></i> На главную</a>
    </div>
</section>

<?php render_footer(); ?>

<?php render_common_js(); ?>
</body>
</html>
