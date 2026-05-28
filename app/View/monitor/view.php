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
            <?= $statusLabels[$page['status']] ?? $page['status'] ?>
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
    <?php if ($page['selection_text'] !== null): ?>
    <div class="sm:col-span-2">
        <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Überwachter Textausschnitt</div>
        <pre class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs overflow-auto max-h-32 whitespace-pre-wrap"><?= htmlspecialchars($page['selection_text']) ?></pre>
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
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden sm:table-cell">Größe</th>
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
                    <td class="px-4 py-3 text-gray-400 hidden sm:table-cell text-xs">
                        <?= number_format((int)($dump['html_bytes'] ?? 0) / 1024, 1) ?> KB
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
