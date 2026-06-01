<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quelltext-Prüfung – <?= htmlspecialchars($page['label'] ?? $page['url']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }
        header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        header h1 { font-size: 15px; font-weight: 600; color: #f1f5f9; }
        header .url { font-size: 12px; color: #64748b; word-break: break-all; }

        .legend {
            display: flex;
            gap: 16px;
            padding: 10px 20px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            font-size: 12px;
            flex-wrap: wrap;
        }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .swatch {
            width: 16px; height: 16px; border-radius: 3px; display: inline-block; flex-shrink: 0;
        }
        .swatch-outer { background: #fef08a; }
        .swatch-inner { background: #fb923c; }

        .status-bar {
            padding: 8px 20px;
            font-size: 12px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            background: #1e293b;
            border-bottom: 1px solid #334155;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-ok  { background: #166534; color: #bbf7d0; }
        .badge-err { background: #7f1d1d; color: #fca5a5; }
        .badge-warn{ background: #78350f; color: #fed7aa; }

        .notice {
            margin: 12px 20px;
            padding: 10px 14px;
            background: #78350f;
            border: 1px solid #92400e;
            border-radius: 6px;
            font-size: 13px;
            color: #fed7aa;
        }

        .toolbar {
            padding: 8px 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .toolbar label { font-size: 12px; color: #94a3b8; }
        .toolbar input[type=text] {
            background: #1e293b;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            width: 220px;
        }
        .toolbar button {
            background: #1e293b;
            border: 1px solid #334155;
            color: #94a3b8;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        .toolbar button:hover { background: #334155; color: #e2e8f0; }

        #source-container {
            padding: 20px;
            overflow: auto;
        }
        pre#source {
            font-family: 'Fira Code', 'Cascadia Code', 'Consolas', monospace;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
            color: #cbd5e1;
            tab-size: 2;
        }
        mark.hl-outer {
            background: #fef9c3;
            color: #78350f;
            border-radius: 2px;
            padding: 1px 0;
            font-weight: 500;
        }
        mark.hl-inner {
            background: #fb923c;
            color: #1c1917;
            border-radius: 2px;
            padding: 1px 2px;
            font-weight: 700;
            outline: 2px solid #f97316;
            outline-offset: 1px;
        }
        .not-found {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }
    </style>
</head>
<body>

<header>
    <div>
        <h1><?= htmlspecialchars($page['label'] ?? $page['url']) ?></h1>
        <div class="url"><?= htmlspecialchars($page['url']) ?></div>
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <?php
        $baseUrl  = '/monitor/' . (int)$page['id'] . '/quelle';
        $liveUrl  = $baseUrl;
        $dumpUrl  = $baseUrl . '?quelle=dump';
        ?>
        <a href="<?= $liveUrl ?>"
           style="font-size:12px;padding:5px 12px;border-radius:6px;text-decoration:none;
                  <?= !$useDump
                      ? 'background:#16a34a;color:#fff;font-weight:600;'
                      : 'background:#1e293b;color:#64748b;border:1px solid #334155;' ?>">
            🌐 Live abrufen
        </a>
        <a href="<?= $dumpUrl ?>"
           style="font-size:12px;padding:5px 12px;border-radius:6px;text-decoration:none;
                  <?= $useDump
                      ? 'background:#b45309;color:#fff;font-weight:600;'
                      : 'background:#1e293b;color:#64748b;border:1px solid #334155;' ?>">
            💾 Letzter Dump<?= $dumpFoundAt ? ' (' . substr($dumpFoundAt, 0, 16) . ')' : '' ?>
        </a>
    </div>
</header>

<div class="legend">
    <div class="legend-item">
        <span class="swatch swatch-outer"></span>
        <span>Umfeld-Wörter (äußere Auswahl)</span>
    </div>
    <?php if (!empty($page['inner_selection_text'])): ?>
    <div class="legend-item">
        <span class="swatch swatch-inner"></span>
        <span>Feinauswahl-Wörter — aktuell überwachter Wert</span>
    </div>
    <?php endif; ?>
</div>

<div class="status-bar">
    <span class="badge <?= $outerFound ? 'badge-ok' : 'badge-err' ?>">
        <?= $outerFound ? '✓' : '✕' ?> Umfeld <?= $outerFound ? 'gefunden' : 'nicht gefunden' ?>
    </span>
    <?php if (!empty($page['inner_selection_text'])): ?>
    <span class="badge <?= $innerFound ? 'badge-ok' : 'badge-warn' ?>">
        <?= $innerFound ? '✓' : '~' ?> Feinauswahl <?= $innerFound ? 'gefunden' : 'nicht gefunden (Muster-Fallback aktiv)' ?>
    </span>
    <?php if ($innerValue !== null): ?>
    <span style="font-size:12px;color:#94a3b8;">
        Aktueller Wert: <strong style="color:#fb923c;"><?= htmlspecialchars($innerValue) ?></strong>
    </span>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($outerSpan !== null): ?>
    <span style="font-size:12px;color:#475569;margin-left:auto;">
        Fundbereich: <?= number_format($outerSpan[0]) ?> – <?= number_format($outerSpan[1]) ?> Bytes
        &nbsp;|&nbsp; Spanne: <?= number_format($outerSpan[1] - $outerSpan[0]) ?> Bytes
    </span>
    <?php endif; ?>
</div>

<?php if ($error !== null): ?>
    <div class="notice"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($highlightedHtml)): ?>
<div class="toolbar">
    <label>Suchen:</label>
    <input type="text" id="search-box" placeholder="Text suchen …" oninput="doSearch(this.value)">
    <button onclick="jumpTo('prev')">↑ Vorh.</button>
    <button onclick="jumpTo('next')">↓ Nächste</button>
    <span id="match-count" style="font-size:11px;color:#64748b;"></span>
    <button onclick="document.querySelectorAll('.hl-inner')[0]?.scrollIntoView({behavior:'smooth',block:'center'})"
            style="margin-left:auto;">
        ▶ Zur Feinauswahl
    </button>
    <button onclick="document.querySelectorAll('.hl-outer')[0]?.scrollIntoView({behavior:'smooth',block:'center'})">
        ▶ Zum Umfeld
    </button>
</div>
<div id="source-container">
    <pre id="source"><?= $highlightedHtml ?></pre>
</div>
<?php else: ?>
    <div class="not-found">
        <?= $error ? htmlspecialchars($error) : 'Kein Auswahltext gesetzt — nichts zu markieren.' ?>
    </div>
<?php endif; ?>

<script>
// Einfache Suchfunktion im Quelltext
let searchMatches = [];
let matchIndex    = -1;

function doSearch(term) {
    // Alte Highlights entfernen
    document.querySelectorAll('.search-hl').forEach(el => {
        el.outerHTML = el.textContent;
    });
    searchMatches = [];
    matchIndex = -1;
    document.getElementById('match-count').textContent = '';

    if (term.length < 2) return;

    const pre = document.getElementById('source');
    const walker = document.createTreeWalker(pre, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);

    const termLower = term.toLowerCase();
    textNodes.forEach(node => {
        const text = node.textContent;
        const lower = text.toLowerCase();
        let idx = 0;
        while ((idx = lower.indexOf(termLower, idx)) !== -1) {
            const range = document.createRange();
            range.setStart(node, idx);
            range.setEnd(node, idx + term.length);
            const span = document.createElement('span');
            span.className = 'search-hl';
            span.style.background = '#7c3aed';
            span.style.color = '#fff';
            span.style.borderRadius = '2px';
            range.surroundContents(span);
            searchMatches.push(span);
            idx += term.length;
        }
    });

    document.getElementById('match-count').textContent =
        searchMatches.length > 0 ? searchMatches.length + ' Treffer' : 'Kein Treffer';
    if (searchMatches.length > 0) jumpTo('next');
}

function jumpTo(dir) {
    if (searchMatches.length === 0) return;
    if (dir === 'next') {
        matchIndex = (matchIndex + 1) % searchMatches.length;
    } else {
        matchIndex = (matchIndex - 1 + searchMatches.length) % searchMatches.length;
    }
    searchMatches[matchIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('match-count').textContent =
        (matchIndex + 1) + ' / ' + searchMatches.length;
}

// Direkt zur Feinauswahl scrollen wenn vorhanden
window.addEventListener('load', () => {
    const first = document.querySelector('.hl-inner') || document.querySelector('.hl-outer');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
</body>
</html>
