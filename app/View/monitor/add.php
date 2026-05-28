<?php
use App\Controller\UrlState;
use App\Controller\PostState;

require BASE_PATH . '/app/View/layout/header.php';
?>

<h1 class="text-2xl font-bold text-gray-900 mb-6">Monitor hinzufügen</h1>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Linke Spalte: Schritt-Anzeige -->
    <div class="lg:col-span-1">
        <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Schritte</h2>

            <?php
            $steps = [
                ['label' => '1. URL eingeben',       'done' => $urlState === UrlState::Valid],
                ['label' => '2. Auswahl treffen',    'done' => $postState === PostState::Valid],
                ['label' => '3. Speichern',          'done' => false],
            ];
            foreach ($steps as $i => $step):
                $active = ($i === 0 && $urlState !== UrlState::Valid)
                    || ($i === 1 && $urlState === UrlState::Valid && $postState !== PostState::Valid)
                    || ($i === 2 && $postState === PostState::Valid);
            ?>
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                    <?= $step['done'] ? 'bg-green-500 text-white' : ($active ? 'bg-green-100 text-green-700 ring-2 ring-green-400' : 'bg-gray-100 text-gray-400') ?>">
                    <?= $step['done'] ? '✓' : ($i + 1) ?>
                </div>
                <span class="text-sm <?= $step['done'] ? 'text-green-700 font-medium' : ($active ? 'text-gray-900 font-medium' : 'text-gray-400') ?>">
                    <?= $step['label'] ?>
                </span>
            </div>
            <?php endforeach; ?>

            <?php if ($urlState === UrlState::Valid): ?>
                <div class="pt-3 border-t border-gray-100">
                    <div class="text-xs text-gray-500 mb-1">Gewählte URL</div>
                    <div class="text-xs text-gray-700 break-all"><?= htmlspecialchars($currentUrl ?? '') ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rechte Spalte: Formulare -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Schritt 1: URL -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="font-semibold text-gray-800 mb-4">Webseite eingeben</h2>
            <form method="post" action="/add" class="space-y-4">
                <?php if ($urlError !== null): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
                        <?= htmlspecialchars($urlError) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                    <input type="url" name="url" id="url" required
                           placeholder="https://example.com"
                           value="<?= htmlspecialchars($currentUrl ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium
                                   hover:bg-green-700 transition-colors">
                        URL prüfen
                    </button>
                    <button type="button" onclick="document.getElementById('url').value=''; document.getElementById('url').focus();"
                            class="text-gray-400 hover:text-gray-600 text-sm px-3 py-2 border border-gray-200
                                   rounded-lg hover:border-gray-300 transition-colors">
                        ✕ Leeren
                    </button>
                    <?php if ($urlState === UrlState::Valid): ?>
                        <span class="text-green-600 text-sm font-medium">✓ Erreichbar</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Schritt 2: Auswahl (nur sichtbar wenn URL gesetzt) -->
        <?php if ($urlState === UrlState::Valid): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="font-semibold text-gray-800 mb-3">Textauswahl treffen</h2>
            <div class="flex items-center gap-3 mb-4 p-3 bg-green-100 border border-green-400 rounded-lg">
                <span class="text-sm text-green-900 truncate min-w-0 font-medium">
                    <?= htmlspecialchars($currentUrl ?? '') ?>
                </span>
                <a href="<?= htmlspecialchars($currentUrl ?? '') ?>" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 text-sm text-green-800 border border-green-500
                          bg-white px-3 py-1.5 rounded-lg hover:bg-green-50 transition-colors shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4
                                 M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Diese Seite öffnen
                </a>
            </div>
            <p class="text-sm text-gray-500 mb-4">
                Kopieren Sie den zu überwachenden Text aus der Webseite inklusive etwas Kontext davor und danach.
            </p>
            <form method="post" action="/add" class="space-y-4">
                <?php if ($selectionError !== null): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
                        <?= htmlspecialchars($selectionError) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="selection" class="block text-sm font-medium text-gray-700 mb-1">
                        Auswahltext (mit Umgebungstext)
                    </label>
                    <textarea name="selection" id="selection" rows="6" required
                              placeholder="surrounding text ... text I'd like to monitor ... surrounding text"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono
                                     focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    ><?= htmlspecialchars($currentSel ?? '') ?></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium
                                   hover:bg-green-700 transition-colors">
                        Auswahl prüfen
                    </button>
                    <button type="button" onclick="document.getElementById('selection').value=''; document.getElementById('selection').focus();"
                            class="text-gray-400 hover:text-gray-600 text-sm px-3 py-2 border border-gray-200
                                   rounded-lg hover:border-gray-300 transition-colors">
                        ✕ Leeren
                    </button>
                    <?php if ($postState === PostState::Valid): ?>
                        <span class="text-green-600 text-sm font-medium">✓ Gefunden</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Schritt 3: Speichern (nur sichtbar wenn Selection gültig) -->
        <?php if ($postState === PostState::Valid): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="font-semibold text-gray-800 mb-4">Speichern</h2>
            <form method="post" action="/add" class="space-y-4">
                <div>
                    <label for="label" class="block text-sm font-medium text-gray-700 mb-1">
                        Bezeichnung <span class="text-gray-400">(optional)</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" name="label" id="label"
                               placeholder="z.B. heise.de Startseite"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <button type="button" onclick="document.getElementById('label').value=''; document.getElementById('label').focus();"
                                class="text-gray-400 hover:text-gray-600 text-sm px-3 py-2 border border-gray-200
                                       rounded-lg hover:border-gray-300 transition-colors shrink-0">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" name="save" value="1"
                            class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm font-medium
                                   hover:bg-green-700 transition-colors">
                        Monitor speichern
                    </button>
                    <button type="submit" name="reset" value="1"
                            class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2 border border-gray-300
                                   rounded-lg hover:border-gray-400 transition-colors">
                        Zurücksetzen
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
