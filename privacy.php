<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
$c = $config['core']; $l = $c['links'];

$sections = [
    [
        'icon' => 'ph-file-text',
        'title' => 'Общие положения',
        'paragraphs' => [
            'Настоящая Политика конфиденциальности регулирует порядок обработки и защиты информации, которую Пользователь передаёт при использовании сервиса.',
            'Используя Сервис, Пользователь подтверждает своё согласие с условиями Политики. Если Пользователь не согласен с условиями — он обязан прекратить использование Сервиса.',
        ],
    ],
    [
        'icon' => 'ph-database',
        'title' => 'Сбор информации',
        'paragraphs' => ['Сервис может собирать следующие типы данных:'],
        'list' => [
            'идентификаторы аккаунта (логин, ID, никнейм и т.п.);',
            'техническую информацию (IP-адрес, данные о браузере, устройстве и операционной системе);',
            'историю взаимодействий с Сервисом.',
        ],
        'after' => 'Сервис не требует от Пользователя предоставления паспортных данных, документов, фотографий или другой личной информации, кроме минимально необходимой для работы.',
    ],
    [
        'icon' => 'ph-gear-six',
        'title' => 'Использование информации',
        'paragraphs' => ['Сервис может использовать полученную информацию исключительно для:'],
        'list' => [
            'обеспечения работы функционала;',
            'связи с Пользователем (в том числе для уведомлений и поддержки);',
            'анализа и улучшения работы Сервиса.',
        ],
    ],
    [
        'icon' => 'ph-share-network',
        'title' => 'Передача информации третьим лицам',
        'paragraphs' => ['Администрация не передаёт полученные данные третьим лицам, за исключением случаев:'],
        'list' => [
            'если это требуется по закону;',
            'если это необходимо для исполнения обязательств перед Пользователем (например, при работе с платёжными системами);',
            'если Пользователь сам дал на это согласие.',
        ],
    ],
    [
        'icon' => 'ph-lock-key',
        'title' => 'Хранение и защита данных',
        'paragraphs' => [
            'Данные хранятся в течение срока, необходимого для достижения целей обработки.',
            'Администрация принимает разумные меры для защиты данных, но не гарантирует абсолютную безопасность информации при передаче через интернет.',
        ],
    ],
    [
        'icon' => 'ph-warning',
        'title' => 'Отказ от ответственности',
        'paragraphs' => [
            'Пользователь понимает и соглашается, что передача информации через интернет всегда сопряжена с рисками.',
            'Администрация не несёт ответственности за утрату, кражу или раскрытие данных, если это произошло по вине третьих лиц или самого Пользователя.',
        ],
    ],
    [
        'icon' => 'ph-arrows-clockwise',
        'title' => 'Изменения в Политике',
        'paragraphs' => [
            'Администрация вправе изменять условия Политики без предварительного уведомления.',
            'Продолжение использования Сервиса после внесения изменений означает согласие Пользователя с новой редакцией Политики.',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Политика конфиденциальности', '
    .legal-page { max-width: 820px; margin: 0 auto; padding: 110px 14px 30px; }

    .legal-hero { text-align: center; margin-bottom: 32px; padding: 32px 26px; }
    .legal-hero .ic-big { width: 60px; height: 60px; margin: 0 auto 14px; border-radius: 16px; background: var(--accent); color: var(--text-inverse); display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .legal-hero .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .legal-hero h1 { font-family: \'Space Grotesk\', sans-serif; font-size: clamp(26px, 4.5vw, 40px); font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin: 12px 0; }
    .legal-hero p { color: var(--text-secondary); font-size: 14px; line-height: 1.6; max-width: 560px; margin: 0 auto; }

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

    .back-bar { margin-top: 24px; display: flex; justify-content: center; }

    @media (max-width: 600px) {
        .legal-section { padding: 22px 18px; }
        .legal-hero { padding: 26px 20px; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header(); ?>
<?php render_tg_float(); ?>

<section class="legal-page">
    <div class="legal-hero glass-strong reveal">
        <div class="ic-big"><i class="ph ph-shield-check"></i></div>
        <div class="eyebrow">Документ</div>
        <h1>Политика конфиденциальности</h1>
        <p>Документ регулирует сбор, использование и защиту информации пользователей сервиса <?= htmlspecialchars($c['title']) ?>. Используя сервис, вы подтверждаете согласие с его условиями.</p>
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

    <div class="back-bar reveal delay-5">
        <a href="<?= htmlspecialchars($l['main']) ?>" class="btn btn-primary"><i class="ph ph-arrow-left"></i> На главную</a>
    </div>
</section>

<?php render_footer(); ?>

<?php render_common_js(); ?>
</body>
</html>
