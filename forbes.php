<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';

/* =========================================================================
 *  Forbes backend
 * ========================================================================= */

function forbes_safe_ident(string $s): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $s)) {
        throw new RuntimeException('Forbes: invalid identifier "' . $s . '"');
    }
    return $s;
}

function forbes_format_money(int $v): string
{
    return '$ ' . number_format($v, 0, '.', ' ');
}

/**
 * Запрашивает топ-N игроков из БД по сумме денежных колонок.
 *  @return array<int,array{name:string,skin:int,total:int}>
 */
function forbes_load_top(): array
{
    global $config;
    $f = $config['forbes'];

    $table  = forbes_safe_ident((string) $f['table']);
    $nameC  = forbes_safe_ident((string) $f['name_col']);
    $skinC  = forbes_safe_ident((string) $f['skin_col']);
    $cols   = array_values(array_map('forbes_safe_ident', (array) $f['money_cols']));
    $limit  = max(1, min(100, (int) $f['limit']));

    if (empty($cols)) {
        $sumExpr = '0';
    } else {
        $parts = [];
        foreach ($cols as $c) {
            $parts[] = "COALESCE(`{$c}`, 0)";
        }
        $sumExpr = implode(' + ', $parts);
    }

    $sql = "SELECT `{$nameC}` AS name, `{$skinC}` AS skin, ({$sumExpr}) AS total
            FROM `{$table}`
            WHERE `{$nameC}` IS NOT NULL AND `{$nameC}` <> ''
            ORDER BY total DESC
            LIMIT {$limit}";

    $rows = db_pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'name'  => (string) $r['name'],
            'skin'  => (int) $r['skin'],
            'total' => (int) $r['total'],
        ];
    }
    return $out;
}

/**
 * Кэшированная обёртка над forbes_load_top().
 *  - Свежий кэш моложе TTL — отдаём как есть.
 *  - Кэш просрочен — пытаемся обновить; если БД упала, возвращаем последний валидный кэш.
 *  - Если кэша вообще нет и БД упала — пробрасываем исключение.
 */
function forbes_top_cached(): array
{
    global $config;
    $ttl       = (int) ($config['forbes']['cache_sec'] ?? 60);
    $cacheDir  = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/forbes_top.json';

    if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    try {
        $data = forbes_load_top();
        @mkdir($cacheDir, 0755, true);
        @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $data;
    } catch (\Throwable $e) {
        if (is_file($cacheFile)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        throw $e;
    }
}

/* =========================================================================
 *  JSON API:  /forbes.php?json=1
 * ========================================================================= */

if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=30');
    try {
        echo json_encode(
            ['ok' => true, 'players' => forbes_top_cached()],
            JSON_UNESCAPED_UNICODE
        );
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(
            ['ok' => false, 'error' => 'Forbes load failed'],
            JSON_UNESCAPED_UNICODE
        );
    }
    exit;
}

/* =========================================================================
 *  HTML-страница
 * ========================================================================= */

$c = $config['core'];
$l = $c['links'];

$players  = [];
$loadErr  = null;
try {
    $players = forbes_top_cached();
} catch (\Throwable $e) {
    $loadErr = $e->getMessage();
}

$top3 = array_slice($players, 0, 3);
$rest = array_slice($players, 3);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<?php render_head('Forbes', '
    .forbes-page { max-width: 1100px; margin: 0 auto; padding: 110px 14px 30px; }

    .forbes-hero { text-align: center; margin-bottom: 36px; padding: 30px 26px; }
    .forbes-hero .ic-big { width: 60px; height: 60px; margin: 0 auto 14px; border-radius: 16px; background: var(--accent); color: var(--text-inverse); display: flex; align-items: center; justify-content: center; font-size: 28px; }
    .forbes-hero .eyebrow { font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .forbes-hero h1 { font-family: \'Space Grotesk\', sans-serif; font-size: clamp(28px, 5vw, 46px); font-weight: 600; letter-spacing: -0.025em; color: var(--text-primary); margin: 12px 0 8px; }
    .forbes-hero p { color: var(--text-secondary); font-size: 14px; line-height: 1.6; max-width: 520px; margin: 0 auto; }

    /* Сообщение об ошибке/пустом списке */
    .forbes-state { text-align: center; padding: 38px 26px; color: var(--text-secondary); font-size: 14px; }
    .forbes-state .ic { font-size: 28px; color: var(--text-muted); margin-bottom: 10px; }
    .forbes-state b { color: var(--text-primary); font-weight: 600; }

    /* ---------- ПОДИУМ ---------- */
    .podium {
        display: grid;
        grid-template-columns: 1fr 1.18fr 1fr;
        gap: 14px;
        align-items: end;
        margin: 26px 0 18px;
    }
    .podium-card {
        position: relative;
        padding: 28px 18px 22px;
        text-align: center;
        transition: transform 0.35s ease;
    }
    .podium-card:hover { transform: translateY(-4px); }

    .podium-card .rank-badge {
        position: absolute; top: -18px; left: 50%; transform: translateX(-50%);
        width: 44px; height: 44px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-family: \'Space Grotesk\', sans-serif; font-size: 16px; font-weight: 700;
        background: var(--accent); color: var(--text-inverse);
        border: 4px solid #f1f5f3;
        box-shadow: 0 12px 26px rgba(20, 30, 40, 0.18);
    }
    .podium-card.gold   .rank-badge { background: linear-gradient(140deg, #f5c453, #c89417); color: #fff; }
    .podium-card.silver .rank-badge { background: linear-gradient(140deg, #d8dde2, #a3aab2); color: #15181d; }
    .podium-card.bronze .rank-badge { background: linear-gradient(140deg, #e08643, #b65b1d); color: #fff; }

    .podium-card .skin-frame {
        width: 100%;
        height: 210px;
        margin: 14px 0 16px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.55);
        border: 1px solid var(--glass-border-soft);
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
    }
    .podium-card.gold .skin-frame { height: 250px; }
    .podium-card .skin-frame img {
        width: 100%; height: 100%;
        object-fit: contain; object-position: bottom;
        filter: drop-shadow(0 6px 14px rgba(20, 30, 40, 0.22));
        transition: transform 0.4s ease;
    }
    .podium-card:hover .skin-frame img { transform: scale(1.04) translateY(-4px); }

    .podium-card .name {
        font-family: \'Space Grotesk\', sans-serif;
        font-size: 17px; font-weight: 600;
        letter-spacing: -0.01em;
        color: var(--text-primary);
        margin-bottom: 10px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .podium-card .money {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 14px;
        border-radius: 999px;
        background: var(--accent-soft);
        border: 1px solid rgba(0,0,0,0.06);
        font-size: 12.5px; font-weight: 700;
        color: var(--text-primary);
        font-family: \'Space Grotesk\', sans-serif;
        letter-spacing: -0.01em;
    }
    .podium-card.gold   .money { background: rgba(245, 196, 83, 0.18); border-color: rgba(200, 148, 23, 0.35); color: #8a6512; }
    .podium-card.silver .money { background: rgba(180, 188, 196, 0.20); border-color: rgba(140, 150, 160, 0.30); color: #4a525c; }
    .podium-card.bronze .money { background: rgba(224, 134, 67, 0.18); border-color: rgba(182, 91, 29, 0.30); color: #8a4515; }

    /* ---------- ТАБЛИЦА ---------- */
    .table-eyebrow {
        font-size: 11px;
        letter-spacing: 0.32em;
        text-transform: uppercase;
        color: var(--text-secondary);
        margin: 30px 4px 12px;
        text-align: center;
    }

    .table-card { padding: 10px; }

    .table-row {
        display: grid;
        grid-template-columns: 64px 1fr 170px;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 14px;
        transition: background 0.2s ease;
    }
    .table-row + .table-row { border-top: 1px dashed rgba(20, 30, 40, 0.07); }
    .table-row:hover { background: rgba(255,255,255,0.55); }

    .table-row .rank {
        font-family: \'Space Grotesk\', sans-serif;
        font-size: 14px; font-weight: 600;
        color: var(--text-muted);
    }
    .table-row .player {
        display: flex; align-items: center; gap: 12px;
        min-width: 0;
    }
    .table-row .avatar {
        width: 42px; height: 42px;
        border-radius: 12px;
        background: rgba(255,255,255,0.55);
        border: 1px solid var(--glass-border-soft);
        object-fit: cover; object-position: top;
        flex-shrink: 0;
    }
    .table-row .pname {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .table-row .money {
        text-align: right;
        font-family: \'Space Grotesk\', sans-serif;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
        letter-spacing: -0.01em;
    }

    .updated-at {
        text-align: center;
        margin-top: 20px;
        font-size: 11px;
        color: var(--text-muted);
        letter-spacing: 0.04em;
    }

    @media (max-width: 760px) {
        .podium {
            grid-template-columns: 1fr;
            gap: 28px;
        }
        .podium-card { order: 2; }
        .podium-card.gold   { order: 1; }
        .podium-card.silver { order: 2; }
        .podium-card.bronze { order: 3; }
        .podium-card .skin-frame,
        .podium-card.gold .skin-frame { height: 220px; }
        .table-row {
            grid-template-columns: 36px 1fr 100px;
            padding: 11px 12px;
            gap: 10px;
        }
        .table-row .avatar { width: 36px; height: 36px; }
        .table-row .pname { font-size: 13px; }
        .table-row .money { font-size: 12.5px; }
        .table-row .rank  { font-size: 12.5px; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('forbes'); ?>
<?php render_tg_float(); ?>

<section class="forbes-page">
    <div class="forbes-hero glass-strong reveal">
        <div class="ic-big"><i class="ph-fill ph-trophy"></i></div>
        <div class="eyebrow">Рейтинг</div>
        <h1>Богатейшие игроки</h1>
        <p>Топ-<?= (int) $config['forbes']['limit'] ?> самых обеспеченных персонажей сервера <?= htmlspecialchars($c['server']['name']) ?>. Список обновляется каждую минуту.</p>
    </div>

<?php if ($loadErr !== null && empty($players)): ?>
    <div class="glass reveal forbes-state">
        <div class="ic"><i class="ph-fill ph-warning-circle"></i></div>
        <b>Рейтинг временно недоступен.</b>
        <div style="margin-top:6px;">Зайди чуть позже — данные подтянутся автоматически.</div>
    </div>
<?php elseif (empty($players)): ?>
    <div class="glass reveal forbes-state">
        <div class="ic"><i class="ph ph-users-three"></i></div>
        <b>Пока что в рейтинге пусто.</b>
        <div style="margin-top:6px;">Будь первым — заходи на сервер и зарабатывай!</div>
    </div>
<?php else: ?>

    <?php
    /* Подиум: показываем 2-1-3 (или меньше, если игроков меньше 3) */
    $podiumOrder = [
        ['idx' => 1, 'cls' => 'silver glass',      'label' => '2', 'delay' => 1],
        ['idx' => 0, 'cls' => 'gold glass-strong', 'label' => '1', 'delay' => 2],
        ['idx' => 2, 'cls' => 'bronze glass',      'label' => '3', 'delay' => 3],
    ];
    ?>
    <div class="podium">
    <?php foreach ($podiumOrder as $o): ?>
        <?php if (!isset($top3[$o['idx']])) continue; ?>
        <?php $p = $top3[$o['idx']]; ?>
        <div class="podium-card <?= $o['cls'] ?> reveal delay-<?= $o['delay'] ?>">
            <div class="rank-badge"><?= $o['label'] ?></div>
            <div class="skin-frame">
                <img src="skins/<?= (int) $p['skin'] ?>.png"
                     alt=""
                     onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22240%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22rgba(255,255,255,0.5)%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22%238a929c%22 font-family=%22Inter%22 font-size=%2213%22>skin</text></svg>'">
            </div>
            <div class="name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="money">
                <i class="ph-fill ph-coin"></i>
                <?= htmlspecialchars(forbes_format_money($p['total'])) ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if (!empty($rest)): ?>
        <div class="table-eyebrow reveal">остальные позиции</div>
        <div class="table-card glass reveal delay-1">
        <?php foreach ($rest as $i => $p): ?>
            <div class="table-row">
                <div class="rank">#<?= $i + 4 ?></div>
                <div class="player">
                    <img class="avatar"
                         src="skins/<?= (int) $p['skin'] ?>.png"
                         alt=""
                         onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2242%22 height=%2242%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22rgba(255,255,255,0.5)%22/></svg>'">
                    <span class="pname"><?= htmlspecialchars($p['name']) ?></span>
                </div>
                <div class="money"><?= htmlspecialchars(forbes_format_money($p['total'])) ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="updated-at reveal">обновлено: <?= date('H:i') ?></div>

<?php endif; ?>
</section>

<?php render_footer(); ?>
<?php render_common_js(); ?>
</body>
</html>
