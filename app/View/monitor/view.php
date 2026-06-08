<?php require BASE_PATH . '/app/View/layout/header.php'; ?>

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="/list" class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block">← Zurück zur Liste</a>
        <h1 class="text-2xl font-bold text-gray-900">
            <?= htmlspecialchars($page['label'] ?? $page['url']) ?>
        </h1>
        <?php if ($page['label'] !== null): ?>
            <div class="text-gray-500 text-sm mt-1"><?= htmlspecialchars($page['url']) ?></div>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if ($page['selection_text'] !== null): ?>
        <a href="/monitor/<?= (int)$page['id'] ?>/quelle" target="_blank"
           class="text-sm border border-amber-300 text-amber-700 px-3 py-1.5 rounded-lg
                  hover:bg-amber-50 hover:border-amber-400 transition-colors"
           title="Quelltext der aktuellen Seite mit markierten Fundstellen anzeigen">
            🔍 Quelltext prüfen
        </a>
        <?php endif; ?>
        <a href="/edit/<?= (int)$page['id'] ?>"
           class="text-sm border border-gray-300 px-3 py-1.5 rounded-lg hover:border-gray-400 transition-colors">
            Bearbeiten
        </a>
        <?php
        $statusClasses = match($page['status']) {
            'active' => 'bg-green-100 text-green-700',
            'paused' => 'bg-yellow-100 text-yellow-700',
            'error'  => 'bg-red-100 text-red-700',
            default  => 'bg-gray-100 text-gray-600',
        };
        $statusLabels = ['active' => 'Aktiv', 'paused' => 'Pausiert', 'error' => 'Fehler'];
        ?>
        <span class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $statusClasses ?>">
            <?= htmlspecialchars($statusLabels[$page['status']] ?? $page['status']) ?>
        </span>
    </div>
</div>

<!-- Details -->
<div class="bg-white border border-gray-200 rounded-xl p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
    <div>
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">URL</div>
        <a href="<?= htmlspecialchars($page['url']) ?>" target="_blank" rel="noopener"
           class="text-green-700 hover:underline break-all">
            <?= htmlspecialchars($page['url']) ?>
        </a>
    </div>
    <div>
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Angelegt am</div>
        <div><?= htmlspecialchars(substr($page['created_at'], 0, 16)) ?></div>
    </div>
    <div>
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Prüfintervall</div>
        <div>
            <?php
            $im = (int)($page['check_interval_minutes'] ?? 1440);
            $parts = [];
            if ($d = intdiv($im, 1440))       $parts[] = $d . ' Tag'   . ($d !== 1 ? 'e' : '');
            if ($h = intdiv($im % 1440, 60))  $parts[] = $h . ' Std.';
            if ($m = $im % 60)                $parts[] = $m . ' Min.';
            echo htmlspecialchars($parts ? implode(' ', $parts) : '1 Min.');
            ?>
        </div>
    </div>
    <div>
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Startzeit (Erstlauf)</div>
        <div><?= sprintf('%02d:00 Uhr', (int)($page['start_hour'] ?? 8)) ?></div>
    </div>
    <div>
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Letzte Prüfung</div>
        <div><?= $page['last_checked_at'] ? htmlspecialchars(substr($page['last_checked_at'], 0, 16)) : '–' ?></div>
    </div>
    <div>
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Prüfungen gesamt</div>
        <div><?= (int)($page['check_count'] ?? 0) ?></div>
    </div>
    <?php if (!empty($page['inner_selection_text'])): ?>
    <div class="sm:col-span-2">
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Feinauswahl</div>
        <div class="flex flex-wrap gap-1.5">
            <?php foreach (preg_split('/\s+/', trim($page['inner_selection_text']), -1, PREG_SPLIT_NO_EMPTY) as $w): ?>
                <span class="inline-block bg-amber-100 text-amber-900 border border-amber-300
                             text-sm font-semibold px-2.5 py-0.5 rounded-full">
                    <?= htmlspecialchars($w) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($page['selection_text'] !== null): ?>
    <div class="sm:col-span-2">
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Überwachter Textausschnitt</div>
        <?php
        $outerText = $page['selection_text'];
        $innerText = $page['inner_selection_text'] ?? '';

        if ($innerText !== '') {
            // Feinauswahl-Wörter im Außenbereich hervorheben
            $innerWords = preg_split('/\s+/', trim($innerText), -1, PREG_SPLIT_NO_EMPTY);
            $patterns = [];
            foreach ($innerWords as $w) {
                $patterns[] = preg_quote($w, '/');
            }
            $regex      = '/(' . implode('|', $patterns) . ')/';
            $parts      = preg_split($regex, $outerText, -1, PREG_SPLIT_DELIM_CAPTURE);
            $rendered   = '';
            foreach ($parts as $i => $part) {
                $rendered .= ($i % 2 === 1)
                    ? '<mark class="bg-amber-200 text-amber-900 rounded px-0.5 font-bold not-italic">'
                      . htmlspecialchars($part) . '</mark>'
                    : htmlspecialchars($part);
            }
        } else {
            $rendered = htmlspecialchars($outerText);
        }
        ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs overflow-auto max-h-48
                    whitespace-pre-wrap font-mono leading-relaxed">
            <?= $rendered ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Dump-Historie -->
<h2 class="text-lg font-semibold text-gray-800 mb-3">Monitoring-Verlauf</h2>

<?php if (empty($dumps)): ?>
    <div class="text-gray-400 text-sm py-6 text-center border border-dashed border-gray-200 rounded-xl">
        Noch keine Prüfungen durchgeführt.
        Starten Sie den CLI-Monitor: <code class="bg-gray-100 px-1 rounded">php app/Cli/monitor.php --page-id=<?= (int)$page['id'] ?></code>
    </div>
<?php else: ?>
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Zeitpunkt</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <?php if (!empty($page['inner_selection_text'])): ?>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Wert</th>
                    <?php endif; ?>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden sm:table-cell">Größe</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($dumps as $dump): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-700">
                        <?= htmlspecialchars(substr($dump['found_at'], 0, 16)) ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($dump['changed']): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                Geändert
                            </span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                Unverändert
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php if (!empty($page['inner_selection_text'])): ?>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <?php
                        $val = $dump['checked_content'];
                        if ($val !== null && $val !== '' && $val !== '__OUTER_NOT_FOUND__') {
                            echo '<span class="font-mono text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">'
                                . htmlspecialchars(mb_substr($val, 0, 80))
                                . '</span>';
                        } else {
                            echo '<span class="text-gray-300 text-xs">–</span>';
                        }
                        ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-gray-400 hidden sm:table-cell text-xs">
                        <?= number_format((int)($dump['html_bytes'] ?? 0) / 1024, 1) ?> KB
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                        <?php if ($page['selection_text'] !== null): ?>
                        <a href="/monitor/<?= (int)$page['id'] ?>/quelle?dump_id=<?= (int)$dump['id'] ?>"
                           target="_blank"
                           class="text-xs text-amber-600 hover:text-amber-800 border border-amber-200
                                  hover:border-amber-400 rounded px-2 py-0.5 transition-colors"
                           title="Diesen Dump in der Quelltext-Ansicht betrachten">
                            🔍
                        </a>
                        <?php endif; ?>
                        <?php if ((int)$dump['id'] !== $initialDumpId): ?>
                        <form method="post"
                              action="/monitor/<?= (int)$page['id'] ?>/dump/<?= (int)$dump['id'] ?>/delete"
                              onsubmit="return confirm('Dump löschen?')">
                            <button type="submit"
                                    class="text-xs text-red-400 hover:text-red-600 border border-red-200
                                           hover:border-red-400 rounded px-2 py-0.5 transition-colors"
                                    title="Diesen Dump löschen">
                                ✕
                            </button>
                        </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
