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

    .page-eyebrow { text-align: center; font-size: 11px; letter-spacing: 0.32em; text-transform: uppercase; color: var(--text-secondary); }
    .page-title { text-align: center; font-family: \'Space Grotesk\', sans-serif; font-size: clamp(32px, 6vw, 60px); font-weight: 600; letter-spacing: -0.03em; color: var(--text-primary); margin: 14px 0 8px; }
    .page-sub { text-align: center; color: var(--text-secondary); font-size: 14px; margin-bottom: 44px; }

    .podium { display: grid; grid-template-columns: 1fr 1.15fr 1fr; gap: 14px; align-items: end; margin-bottom: 32px; }
    .podium-card { padding: 22px 18px 24px; text-align: center; transition: transform 0.35s; position: relative; }
    .podium-card:hover { transform: translateY(-4px); }
    .podium-card .rank-badge {
        position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
        width: 38px; height: 38px; border-radius: 50%;
        background: var(--accent); color: var(--text-inverse);
        display: flex; align-items: center; justify-content: center;
        font-family: \'Space Grotesk\', sans-serif; font-size: 15px; font-weight: 700;
        border: 3px solid #fff;
        box-shadow: 0 8px 20px rgba(0,0,0,0.4);
    }
    .podium-card.gold .rank-badge { background: linear-gradient(140deg, #fbbf24, #d97706); color: #1a1a1a; }
    .podium-card.silver .rank-badge { background: linear-gradient(140deg, #e5e7eb, #9ca3af); color: #1a1a1a; }
    .podium-card.bronze .rank-badge { background: linear-gradient(140deg, #f97316, #c2410c); color: #fff; }

    .podium-card .skin-frame {
        width: 100%; height: 200px;
        margin: 12px 0 14px;
        border-radius: 14px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--glass-border);
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
    }
    .podium-card.gold .skin-frame { height: 240px; }
    .podium-card .skin-frame img { width: 100%; height: 100%; object-fit: contain; object-position: bottom; filter: drop-shadow(0 6px 14px rgba(0,0,0,0.4)); transition: transform 0.4s; }
    .podium-card:hover .skin-frame img { transform: scale(1.04) translateY(-4px); }

    .podium-card .name { font-family: \'Space Grotesk\', sans-serif; font-size: 17px; font-weight: 600; letter-spacing: -0.01em; color: var(--text-primary); margin-bottom: 8px; }
    .podium-card .money { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); font-size: 12.5px; font-weight: 600; color: var(--text-primary); }
    .podium-card.gold .money { background: rgba(251,191,36,0.14); border-color: rgba(251,191,36,0.35); color: #fbbf24; }

    .table-card { padding: 10px; }
    .table-row { display: grid; grid-template-columns: 56px 1fr 150px; align-items: center; padding: 12px 16px; border-radius: 12px; transition: background 0.2s; }
    .table-row:hover { background: rgba(255,255,255,0.04); }
    .table-row .rank { font-family: \'Space Grotesk\', sans-serif; font-size: 13.5px; font-weight: 600; color: var(--text-muted); }
    .table-row .player { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .table-row .avatar { width: 40px; height: 40px; border-radius: 11px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); object-fit: cover; object-position: top; flex-shrink: 0; }
    .table-row .pname { font-weight: 600; color: var(--text-primary); font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .table-row .money { text-align: right; font-weight: 600; color: var(--text-primary); font-size: 13.5px; font-family: \'Space Grotesk\', sans-serif; }

    @media (max-width: 760px) {
        .podium { grid-template-columns: 1fr; }
        .podium-card .skin-frame { height: 200px; }
        .podium-card.gold .skin-frame { height: 220px; }
        .table-row { grid-template-columns: 32px 1fr 92px; padding: 11px 12px; gap: 10px; }
        .table-row .avatar { width: 34px; height: 34px; }
        .table-row .pname { font-size: 13px; }
        .table-row .money { font-size: 12.5px; }
        .table-row .rank { font-size: 12px; }
    }
'); ?>
</head>
<body>

<?php render_bg(); ?>
<?php render_header('forbes'); ?>
<?php render_tg_float(); ?>

<section class="forbes-page">
    <div class="page-eyebrow reveal">Рейтинг</div>
    <h1 class="page-title reveal delay-1">Богатейшие игроки</h1>
    <p class="page-sub reveal delay-2">Топ-20 самых обеспеченных персонажей сервера</p>

    <div class="podium" id="podium"></div>
    <div class="table-card glass reveal delay-3" id="table"></div>
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
    const table = document.getElementById('table');

    const placeholderSkin = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='120' height='240'><rect width='100%' height='100%' fill='%23eef1ee'/><text x='50%' y='50%' text-anchor='middle' fill='%238a929c' font-family='Inter' font-size='14'>skin</text></svg>";
    const placeholderAvatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='40' height='40'><rect width='100%' height='100%' fill='%23eef1ee'/></svg>";

    const order = [
        { idx: 1, cls: 'silver glass', label: '2' },
        { idx: 0, cls: 'gold glass-strong', label: '1' },
        { idx: 2, cls: 'bronze glass', label: '3' },
    ];
    order.forEach((o, i) => {
        const p = players[o.idx];
        const card = document.createElement('div');
        card.className = `podium-card ${o.cls} reveal delay-${i + 1}`;
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
