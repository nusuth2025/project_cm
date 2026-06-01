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
                ['label' => '1. URL eingeben',      'done' => $urlState === UrlState::Valid],
                ['label' => '2. Umfeld definieren', 'done' => $postState === PostState::Valid],
                ['label' => '3. Feinauswahl',        'done' => ($innerState ?? 'hidden') === 'done'],
                ['label' => '4. Zeitintervall & Speichern', 'done' => false],
            ];
            foreach ($steps as $i => $step):
                $active = ($i === 0 && $urlState !== UrlState::Valid)
                    || ($i === 1 && $urlState === UrlState::Valid && $postState !== PostState::Valid)
                    || ($i === 2 && $postState === PostState::Valid && ($innerState ?? 'hidden') === 'pending')
                    || ($i === 3 && ($innerState ?? 'hidden') === 'done');
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
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm space-y-2">
                        <div class="flex items-start gap-2">
                            <span class="shrink-0 font-bold">✕</span>
                            <span><?= htmlspecialchars($urlError) ?></span>
                        </div>
                        <?php if ($urlSuggestedUrl !== null): ?>
                            <div class="pl-5">
                                <button type="button"
                                        onclick="document.getElementById('url').value = <?= json_encode($urlSuggestedUrl) ?>; document.getElementById('url').focus();"
                                        class="text-xs underline text-red-700 hover:text-red-900">
                                    Stattdessen verwenden: <?= htmlspecialchars($urlSuggestedUrl) ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($urlWarning !== null): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm space-y-1">
                        <div class="flex items-start gap-2">
                            <span class="shrink-0">⚠</span>
                            <span><?= htmlspecialchars($urlWarning) ?></span>
                        </div>
                        <?php if ($currentUrl !== null): ?>
                            <div class="pl-5 text-xs text-amber-700 break-all">
                                Verwendete Adresse: <strong><?= htmlspecialchars($currentUrl) ?></strong>
                            </div>
                        <?php endif; ?>
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
                    <a href="/add"
                       class="text-gray-400 hover:text-gray-600 text-sm px-3 py-2 border border-gray-200
                              rounded-lg hover:border-gray-300 transition-colors">
                        ✕ Leeren
                    </a>
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

        <!-- Schritt 3: Feinauswahl -->
        <?php if ($postState === PostState::Valid && ($innerState ?? 'hidden') === 'pending'): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="font-semibold text-gray-800 mb-1">
                Feinauswahl
                <span class="text-gray-400 font-normal text-sm ml-1">(optional)</span>
            </h2>

            <!-- Anleitung -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5 text-sm text-blue-800 leading-relaxed">
                <p class="font-semibold mb-2">So funktioniert die Feinauswahl:</p>
                <ul class="space-y-1 list-disc list-inside text-blue-700">
                    <li>Klicken Sie auf die <strong>Wörter oder Zahlen</strong> im Umfeld-Text, die Sie genau beobachten möchten
                        (z.&nbsp;B. einen Preis, ein Datum, eine Versionsnummer).</li>
                    <li>Ein <strong>zweiter Klick</strong> auf ein markiertes Wort hebt die Auswahl wieder auf.</li>
                    <li>Nur diese Wörter werden bei jedem Prüflauf verglichen &mdash; das reduziert Fehlalarme
                        durch Änderungen außerhalb des relevanten Bereichs.</li>
                </ul>
                <p class="mt-2 text-blue-600 text-xs">
                    Sie können diesen Schritt auch <strong>überspringen</strong> &mdash; dann wird das gesamte Umfeld auf Änderungen überwacht.
                </p>
            </div>

            <!-- Klickbare Wörter -->
            <div id="word-container"
                 class="bg-gray-50 border border-gray-200 rounded-lg p-4 leading-loose min-h-16">
                <?php
                $tokens = preg_split('/\s+/', trim($currentSel ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($tokens as $idx => $word): ?>
                    <span class="word-token inline-block cursor-pointer rounded px-1.5 py-0.5 mr-0.5 mb-1
                                 border border-transparent text-sm text-gray-800 select-none
                                 hover:bg-green-50 hover:border-green-300 transition-all"
                          data-word="<?= htmlspecialchars($word, ENT_QUOTES) ?>"
                          data-idx="<?= $idx ?>">
                        <?= htmlspecialchars($word) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <!-- Vorschau gewählter Wörter -->
            <div class="mt-3 flex items-center gap-2 text-sm text-gray-500 min-h-6">
                <span class="shrink-0">Ausgewählt:</span>
                <span id="sel-preview" class="font-medium text-gray-900">–</span>
                <button type="button" id="btn-clear-sel"
                        class="ml-auto text-xs text-gray-400 hover:text-red-500 transition-colors hidden">
                    Auswahl leeren
                </button>
            </div>

            <!-- Formular -->
            <form method="post" action="/add" class="mt-5">
                <input type="hidden" name="inner_selection" id="inner_selection" value="">
                <div class="flex items-center gap-3 flex-wrap">
                    <button type="submit" name="apply_inner" value="1"
                            class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium
                                   hover:bg-green-700 transition-colors">
                        Feinauswahl übernehmen
                    </button>
                    <button type="submit" name="skip_inner" value="1"
                            class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2 border border-gray-300
                                   rounded-lg hover:border-gray-400 transition-colors">
                        Überspringen
                    </button>
                    <button type="submit" name="reset" value="1"
                            class="text-gray-400 hover:text-gray-600 text-sm px-3 py-2 transition-colors ml-auto">
                        ✕ Alles zurücksetzen
                    </button>
                </div>
            </form>
        </div>

        <style>
        .word-token.word-selected {
            background-color: #bbf7d0;
            border-color: #16a34a;
            color: #14532d;
            font-weight: 600;
        }
        </style>
        <script>
        (function () {
            var tokens    = document.querySelectorAll('.word-token');
            var input     = document.getElementById('inner_selection');
            var preview   = document.getElementById('sel-preview');
            var clearBtn  = document.getElementById('btn-clear-sel');

            tokens.forEach(function (t) {
                t.addEventListener('click', function () {
                    t.classList.toggle('word-selected');
                    update();
                });
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    tokens.forEach(function (t) { t.classList.remove('word-selected'); });
                    update();
                });
            }

            function update() {
                var words = Array.from(document.querySelectorAll('.word-token.word-selected'))
                                 .map(function (t) { return t.dataset.word; });
                input.value          = words.join(' ');
                preview.textContent  = words.length > 0 ? words.join(' ') : '–';
                clearBtn.classList.toggle('hidden', words.length === 0);
            }
        }());
        </script>
        <?php endif; ?>

        <!-- Schritt 4: Speichern -->
        <?php if ($postState === PostState::Valid && ($innerState ?? 'hidden') === 'done'): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="font-semibold text-gray-800 mb-4">Speichern</h2>

            <!-- Feinauswahl-Zusammenfassung -->
            <?php if (!empty($currentInnerSel)): ?>
                <div class="flex items-start gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 mb-4 text-sm">
                    <span class="text-green-600 shrink-0 font-medium">Feinauswahl:</span>
                    <span class="text-green-900 font-medium break-all"><?= htmlspecialchars($currentInnerSel) ?></span>
                </div>
            <?php else: ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mb-4 text-sm text-gray-400">
                    Keine Feinauswahl &mdash; das gesamte Umfeld wird auf Änderungen verglichen.
                </div>
            <?php endif; ?>

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

                <div>
                    <label for="start_hour" class="block text-sm font-medium text-gray-700 mb-1">
                        Erste Prüfung um
                    </label>
                    <select name="start_hour" id="start_hour"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <?php for ($h = 0; $h < 24; $h++): ?>
                            <option value="<?= $h ?>" <?= $h === 8 ? 'selected' : '' ?>>
                                <?= sprintf('%02d:00 Uhr', $h) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">
                        Startzeitpunkt des ersten Prüflaufs. Liegt diese Uhrzeit heute
                        bereits in der Vergangenheit, beginnt der erste Lauf morgen.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prüfintervall</label>
                    <div class="flex gap-3 flex-wrap">
                        <div>
                            <input type="number" name="interval_days" min="0" max="365" value="0"
                                   class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center
                                          focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <div class="mt-1 text-xs text-center text-gray-400">Tage</div>
                        </div>
                        <div>
                            <input type="number" name="interval_hours" min="0" max="23" value="0"
                                   class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center
                                          focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <div class="mt-1 text-xs text-center text-gray-400">Stunden</div>
                        </div>
                        <div>
                            <input type="number" name="interval_minutes" min="0" max="59" value="15"
                                   class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center
                                          focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <div class="mt-1 text-xs text-center text-gray-400">Minuten</div>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        Mindestens 15&nbsp;Minuten. Folgeprüfungen laufen in diesem Abstand nach
                        dem ersten Lauf. Beispiel: 4&nbsp;Tage 3&nbsp;Stunden 35&nbsp;Minuten.
                    </p>
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
