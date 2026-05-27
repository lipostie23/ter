<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials.php';
$c = $config['core']; $l = $c['links'];
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
        <p>Топ-20 самых обеспеченных персонажей сервера <?= htmlspecialchars($c['server']['name']) ?>. Список обновляется в реальном времени.</p>
    </div>

    <div class="podium" id="podium"></div>

    <div class="table-eyebrow reveal">остальные позиции</div>
    <div class="table-card glass reveal delay-1" id="table"></div>
</section>

<?php render_footer(); ?>

<?php render_common_js(); ?>
<script>
    const players = [
        { name: "Monarch",       money: "$ 999 999 999", skin: 230 },
        { name: "Rich_Man",      money: "$ 540 000 000", skin: 120 },
        { name: "Donater_Top",   money: "$ 320 000 000", skin: 46  },
        { name: "Gamer_Pro",     money: "$ 150 000 000", skin: 21  },
        { name: "Alex_Drift",    money: "$ 95 000 000",  skin: 2   },
        { name: "Mafia_Boss",    money: "$ 80 000 000",  skin: 111 },
        { name: "Cop_Killer",    money: "$ 75 000 000",  skin: 280 },
        { name: "Taxi_Driver",   money: "$ 60 000 000",  skin: 14  },
        { name: "Street_Racer",  money: "$ 55 000 000",  skin: 299 },
        { name: "Bizwar_King",   money: "$ 50 000 000",  skin: 124 },
        { name: "Farm_Worker",   money: "$ 45 000 000",  skin: 1   },
        { name: "Trucker_Joe",   money: "$ 40 000 000",  skin: 15  },
        { name: "Medic_Help",    money: "$ 38 000 000",  skin: 274 },
        { name: "News_Reporter", money: "$ 35 000 000",  skin: 187 },
        { name: "Army_General",  money: "$ 30 000 000",  skin: 287 },
        { name: "Gangster_007",  money: "$ 25 000 000",  skin: 102 },
        { name: "Hobo_Life",     money: "$ 20 000 000",  skin: 78  },
        { name: "Casino_Winner", money: "$ 15 000 000",  skin: 113 },
        { name: "Lucky_Guy",     money: "$ 10 000 000",  skin: 23  },
        { name: "New_Player",    money: "$ 5 000 000",   skin: 9   }
    ];

    const podium = document.getElementById('podium');
    const table  = document.getElementById('table');

    const placeholderSkin   = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='120' height='240'><rect width='100%' height='100%' fill='rgba(255,255,255,0.5)'/><text x='50%' y='50%' text-anchor='middle' fill='%238a929c' font-family='Inter' font-size='13'>skin</text></svg>";
    const placeholderAvatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='42' height='42'><rect width='100%' height='100%' fill='rgba(255,255,255,0.5)'/></svg>";

    /* Размещаем карточки в порядке 2 - 1 - 3, как настоящий подиум */
    const order = [
        { idx: 1, cls: 'silver glass',        label: '2', delay: 1 },
        { idx: 0, cls: 'gold glass-strong',   label: '1', delay: 2 },
        { idx: 2, cls: 'bronze glass',        label: '3', delay: 3 },
    ];
    order.forEach(o => {
        const p = players[o.idx];
        const card = document.createElement('div');
        card.className = `podium-card ${o.cls} reveal delay-${o.delay}`;
        card.innerHTML = `
            <div class="rank-badge">${o.label}</div>
            <div class="skin-frame"><img src="skins/${p.skin}.png" alt="" onerror="this.src='${placeholderSkin}'"></div>
            <div class="name">${p.name}</div>
            <div class="money"><i class="ph-fill ph-coin"></i> ${p.money}</div>
        `;
        podium.appendChild(card);
    });

    for (let i = 3; i < players.length; i++) {
        const p = players[i];
        const row = document.createElement('div');
        row.className = 'table-row';
        row.innerHTML = `
            <div class="rank">#${i + 1}</div>
            <div class="player">
                <img class="avatar" src="skins/${p.skin}.png" alt="" onerror="this.src='${placeholderAvatar}'">
                <span class="pname">${p.name}</span>
            </div>
            <div class="money">${p.money}</div>
        `;
        table.appendChild(row);
    }
</script>
</body>
</html>
