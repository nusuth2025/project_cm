<?php require BASE_PATH . '/app/View/layout/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Meine Monitore</h1>
    <a href="/add"
       class="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium">
        + Hinzufügen
    </a>
</div>

<?php if (empty($pages)): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-4">📋</div>
        <p class="text-lg">Noch keine Monitore angelegt.</p>
        <a href="/add" class="mt-4 inline-block text-green-600 hover:text-green-800 font-medium">
            Ersten Monitor hinzufügen →
        </a>
    </div>
<?php else: ?>
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Label / URL</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden md:table-cell">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden lg:table-cell">Letzte Prüfung</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 hidden sm:table-cell">Prüfungen</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($pages as $page): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">
                            <?= htmlspecialchars($page['label'] ?? $page['url']) ?>
                        </div>
                        <?php if ($page['label'] !== null): ?>
                            <div class="text-gray-400 text-xs truncate max-w-xs">
                                <?= htmlspecialchars($page['url']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <?php
                        $statusClasses = match($page['status']) {
                            'active' => 'bg-green-100 text-green-700',
                            'paused' => 'bg-yellow-100 text-yellow-700',
                            'error'  => 'bg-red-100 text-red-700',
                            default  => 'bg-gray-100 text-gray-600',
                        };
                        $statusLabels = ['active' => 'Aktiv', 'paused' => 'Pausiert', 'error' => 'Fehler'];
                        ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusClasses ?>">
                            <?= $statusLabels[$page['status']] ?? $page['status'] ?>
                        </span>
                        <?php if ($page['last_changed']): ?>
                            <span class="inline-flex ml-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                Geändert
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
                        <?= $page['last_checked'] ? htmlspecialchars(substr($page['last_checked'], 0, 16)) : '–' ?>
                    </td>
                    <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">
                        <?= (int)$page['dump_count'] ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 justify-end">
                            <a href="/monitor/<?= (int)$page['id'] ?>"
                               class="text-green-600 hover:text-green-800 font-medium">Anzeigen</a>
                            <a href="/edit/<?= (int)$page['id'] ?>"
                               class="text-gray-500 hover:text-gray-700">Bearbeiten</a>
                            <form method="post" action="/delete/<?= (int)$page['id'] ?>"
                                  onsubmit="return confirm('Monitor wirklich löschen?')">
                                <button type="submit"
                                        class="text-red-500 hover:text-red-700 font-medium">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
