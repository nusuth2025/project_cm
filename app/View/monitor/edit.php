<?php require BASE_PATH . '/app/View/layout/header.php'; ?>

<div class="max-w-lg">
    <a href="/monitor/<?= (int)$page['id'] ?>" class="text-sm text-gray-500 hover:text-gray-700 mb-4 inline-block">
        ← Zurück zum Monitor
    </a>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Monitor bearbeiten</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-5 text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/edit/<?= (int)$page['id'] ?>"
          class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
            <div class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 break-all">
                <?= htmlspecialchars($page['url']) ?>
            </div>
        </div>

        <div>
            <label for="label" class="block text-sm font-medium text-gray-700 mb-1">Bezeichnung</label>
            <input type="text" name="label" id="label"
                   value="<?= htmlspecialchars($page['label'] ?? '') ?>"
                   placeholder="z.B. heise.de Startseite"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" id="status"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="active"  <?= $page['status'] === 'active'  ? 'selected' : '' ?>>Aktiv</option>
                <option value="paused"  <?= $page['status'] === 'paused'  ? 'selected' : '' ?>>Pausiert</option>
            </select>
        </div>

        <?php
            $im     = (int)($page['check_interval_minutes'] ?? 1440);
            $iDays  = intdiv($im, 1440);
            $iHours = intdiv($im % 1440, 60);
            $iMins  = $im % 60;
            $iStart = (int)($page['start_hour'] ?? 8);
        ?>

        <div>
            <label for="start_hour" class="block text-sm font-medium text-gray-700 mb-1">
                Erste Prüfung um
            </label>
            <select name="start_hour" id="start_hour"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm
                           focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <option value="<?= $h ?>" <?= $h === $iStart ? 'selected' : '' ?>>
                        <?= sprintf('%02d:00 Uhr', $h) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <p class="mt-1 text-xs text-gray-400">
                Gilt nur für den nächsten anstehenden Erstersrtlauf (falls noch kein Dump vorhanden).
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Prüfintervall</label>
            <div class="flex gap-3 flex-wrap">
                <div>
                    <input type="number" name="interval_days" min="0" max="365"
                           value="<?= $iDays ?>"
                           class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center
                                  focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <div class="mt-1 text-xs text-center text-gray-400">Tage</div>
                </div>
                <div>
                    <input type="number" name="interval_hours" min="0" max="23"
                           value="<?= $iHours ?>"
                           class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center
                                  focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <div class="mt-1 text-xs text-center text-gray-400">Stunden</div>
                </div>
                <div>
                    <input type="number" name="interval_minutes" min="0" max="45" step="15"
                           value="<?= $iMins ?>"
                           class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center
                                  focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <div class="mt-1 text-xs text-center text-gray-400">Minuten</div>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-400">
                Mindestens 15&nbsp;Minuten. Folgeprüfungen laufen in diesem Abstand nach dem ersten Lauf.
            </p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium
                           hover:bg-green-700 transition-colors">
                Speichern
            </button>
            <a href="/monitor/<?= (int)$page['id'] ?>"
               class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2 border border-gray-300
                      rounded-lg hover:border-gray-400 transition-colors">
                Abbrechen
            </a>
        </div>
    </form>
</div>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
