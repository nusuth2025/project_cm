<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\DB;
use App\Model\MonitoredPage;
use App\Model\MonitoringDump;

class MonitoringService
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0';

    public function __construct(
        private readonly SelectionSearchService $searchService
    ) {}

    /**
     * Lädt den HTML-Body einer URL per cURL.
     * Bug-Fix gegenüber ProcessSelection: CURLOPT_HEADER ist false,
     * damit keine HTTP-Header in den gespeicherten Inhalt gelangen.
     */
    public function fetchHtml(string $url): string
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false, // Bug-Fix: kein HTTP-Header im Body
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_ENCODING       => '',    // akzeptiert alle Encodings automatisch
        ]);

        $html = curl_exec($curl);
        // curl_close() ist in PHP 8.4 deprecated (Handle wird per GC freigegeben)
        if (PHP_VERSION_ID < 80400) {
            curl_close($curl);
        }

        return is_string($html) ? $html : '';
    }

    /**
     * Vollständiger Monitoring-Zyklus: HTML abrufen → Auswahl suchen → vergleichen → speichern.
     */
    public function runCheck(MonitoredPage $page): MonitoringDump
    {
        $htmlContent = $this->fetchHtml($page->url);
        // Entities dekodieren, dann <script>/<style>-Blöcke entfernen,
        // damit findPositions() nur sichtbaren Seiteninhalt trifft und nicht
        // JSON-LD, Inline-JS oder CSS-Regeln als Anker verwendet.
        $searchHtml = preg_replace(
            ['/<script[^>]*>[\s\S]*?<\/script>/i', '/<style[^>]*>[\s\S]*?<\/style>/i'],
            '',
            html_entity_decode($htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        $checkedContent = null;
        $compareContent = $htmlContent;
        $changeNote     = '';

        // Vorherigen Dump früh laden — wird für kontextreiche Änderungsmeldungen benötigt
        $previous    = $this->getPreviousDump($page->id);
        $prevContent = $previous?->checkedContent ?? $previous?->htmlContent ?? '';

        if ($page->selectionText !== null) {
            $outerPositions = $this->searchService->findPositions($searchHtml, $page->selectionText);

            // Fallback: der Außenbereich enthält selbst die volatile Feinauswahl.
            // Feinauswahl-Wörter aus dem Außenbereich herausrechnen und nochmal suchen,
            // damit der stabile Kontext (z. B. Stadtname) trotzdem als Anker dient.
            if (empty($outerPositions) && $page->innerSelectionText !== null && $page->innerSelectionText !== '') {
                $outerPositions = $this->findReducedOuterPositions(
                    $searchHtml,
                    $page->selectionText,
                    $page->innerSelectionText
                );
            }

            if (empty($outerPositions)) {
                $checkedContent = '__OUTER_NOT_FOUND__';
                $compareContent = '__OUTER_NOT_FOUND__';
                $changeNote     = 'Das überwachte Umfeld konnte auf der Seite nicht mehr gefunden werden.';
            } else {
                $regionStart = $outerPositions[0];
                $regionEnd   = $outerPositions[count($outerPositions) - 1];
                $outerRegion = substr($searchHtml, $regionStart, $regionEnd - $regionStart);
                // $searchHtml ist bereits von <script>/<style> bereinigt,
                // daher reicht hier direktes strip_tags
                $outerText = trim(preg_replace('/\s+/', ' ', strip_tags($outerRegion)));

                if ($page->innerSelectionText !== null && $page->innerSelectionText !== '') {
                    // Strategie: Feinauswahl-Wörter als Teilfolge in den Außen-Wörtern finden.
                    // Phase 1 hat bereits ALLE äußeren Wörter an den korrekten Positionen gefunden
                    // (inkl. "198,00" an der richtigen Stelle bei "2 Varianten ab 198,00 €").
                    // Wir suchen die letzte Übereinstimmung der Feinauswahl-Wörter in den Außen-Wörtern
                    // und übernehmen die dort gespeicherten Positionen — statt nochmals im HTML zu suchen.
                    $innerPositions = $this->findInnerInOuterPositions(
                        $outerPositions,
                        $this->searchService->tokenize($page->selectionText),
                        $this->searchService->tokenize($page->innerSelectionText)
                    );
                    // Fallback: Phrase direkt im HTML suchen
                    if (empty($innerPositions)) {
                        $innerPositions = $this->searchService->findPositions($outerRegion, $page->innerSelectionText);
                    }

                    if (!empty($innerPositions)) {
                        // Feinauswahl exakt gefunden: tatsächlich gefundene Wörter extrahieren
                        $matchedParts = [];
                        for ($i = 0; $i < count($innerPositions); $i += 2) {
                            $matchedParts[] = substr(
                                $outerRegion,
                                $innerPositions[$i],
                                $innerPositions[$i + 1] - $innerPositions[$i]
                            );
                        }
                        $checkedContent = implode(' ', $matchedParts);
                        $compareContent = $checkedContent;
                    } else {
                        // Feinauswahl nicht mehr exakt gefunden.
                        // Muster aus dem ursprünglichen Wert ableiten (z. B. "16:09" → \d+:\d+)
                        // und damit den aktuellen Wert im Seitentext suchen.
                        $currentVal = $this->extractCurrentValue($outerText, $page->innerSelectionText);

                        if ($currentVal !== null) {
                            $checkedContent = $currentVal;
                            $compareContent = $currentVal;
                            $prevVal = (string) $prevContent;
                            if ($prevVal !== '' && $prevVal !== '__OUTER_NOT_FOUND__') {
                                $changeNote = sprintf(
                                    "Feinauswahl geändert.\nVorher: »%s«\nJetzt:  »%s«",
                                    mb_substr($prevVal, 0, 100),
                                    $currentVal
                                );
                            }
                        } else {
                            // Kein passender Wert im Bereich — Kontext als Fallback
                            $checkedContent = mb_substr($outerText, 0, 500);
                            $compareContent = $checkedContent;
                            $prevVal = (string) $prevContent;
                            if ($prevVal !== '' && $prevVal !== '__OUTER_NOT_FOUND__') {
                                $changeNote = sprintf(
                                    "Feinauswahl nicht mehr gefunden.\nVorher: »%s«\nAktueller Bereich: »%s«",
                                    mb_substr($prevVal, 0, 100),
                                    mb_substr($outerText, 0, 300)
                                );
                            } else {
                                $changeNote = sprintf(
                                    "Feinauswahl nicht mehr gefunden.\nAktueller Bereich: »%s«",
                                    mb_substr($outerText, 0, 300)
                                );
                            }
                        }
                    }
                } else {
                    $checkedContent = mb_substr($outerText, 0, 1000);
                    $compareContent = $checkedContent;
                }
            }
        }

        $changed = $previous !== null && $this->hasChanged($prevContent, $compareContent);

        if ($changed && $changeNote === '') {
            $prev = mb_substr((string) $prevContent, 0, 200);
            $curr = mb_substr((string) $checkedContent, 0, 200);
            $changeNote = $page->innerSelectionText !== null && $page->innerSelectionText !== ''
                ? sprintf("Feinauswahl geändert.\nVorher: »%s«\nJetzt:  »%s«", $prev, $curr)
                : sprintf("Überwachter Bereich geändert.\nVorher: »%s«\nJetzt:  »%s«", $prev, $curr);
        }

        $db   = DB::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO monitoring_dumps (monitored_page_id, html_content, checked_content, changed)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$page->id, $htmlContent, $checkedContent, $changed ? 1 : 0]);

        $dump                         = new MonitoringDump();
        $dump->id                     = (int) $db->lastInsertId();
        $dump->monitoredPageId        = $page->id;
        $dump->htmlContent            = $htmlContent;
        $dump->checkedContent         = $checkedContent;
        $dump->foundAt                = new \DateTimeImmutable();
        $dump->changed                = $changed;
        $dump->changeNote             = $changeNote;
        $dump->previousCheckedContent = $previous?->checkedContent;

        return $dump;
    }

    /**
     * Findet die Positionen der Feinauswahl-Wörter, indem sie als Teilfolge in den
     * äußeren Wörtern gesucht werden. Phase 1 hat alle äußeren Positionen bereits
     * korrekt berechnet — keine erneute HTML-Suche nötig.
     *
     * Gibt die letzten (= am weitesten rechts liegenden) passenden äußeren Positionen zurück.
     *
     * @param  int[]    $outerPositions  [start, end, …]
     * @param  string[] $outerWords
     * @param  string[] $innerWords
     * @return int[]
     */
    private function findInnerInOuterPositions(array $outerPositions, array $outerWords, array $innerWords): array
    {
        $nInner = count($innerWords);
        $nOuter = count($outerWords);

        if ($nInner === 0 || $nOuter < $nInner) {
            return [];
        }

        for ($i = $nOuter - $nInner; $i >= 0; $i--) {
            $match = true;
            for ($j = 0; $j < $nInner; $j++) {
                if ($outerWords[$i + $j] !== $innerWords[$j]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $result = [];
                for ($j = 0; $j < $nInner; $j++) {
                    $result[] = $outerPositions[($i + $j) * 2];
                    $result[] = $outerPositions[($i + $j) * 2 + 1];
                }
                return $result;
            }
        }

        return [];
    }

    /**
     * Leitet aus dem ursprünglichen Feinauswahl-Wert ein flexibles Muster ab
     * und sucht damit nach dem AKTUELLEN Wert im übergebenen Text.
     *
     * Beispiele:
     *   "16:09"  → Muster \d+:\d+  → findet "17:42"
     *   "198,00" → Muster \d+,\d+  → findet "220,99"
     *   "v2.3.1" → Muster v\d+\.\d+\.\d+ → findet "v2.4.0"
     *
     * Gibt null zurück wenn kein passender Wert gefunden wird.
     */
    private function extractCurrentValue(string $text, string $innerTemplate): ?string
    {
        // Sonderzeichen escapen, dann Ziffernfolgen durch \d+ ersetzen
        $pattern = preg_replace('/\d+/', '\\d+', preg_quote($innerTemplate, '/'));

        // Nur fortfahren wenn das Muster mindestens eine Zifferngruppe enthält
        if (!str_contains($pattern, '\\d+')) {
            return null;
        }

        if (preg_match('/' . $pattern . '/u', $text, $match)) {
            return $match[0];
        }

        return null;
    }

    private function findReducedOuterPositions(string $html, string $outerSelection, string $innerSelection): array
    {
        $innerWords   = preg_split('/\s+/', trim($innerSelection), -1, PREG_SPLIT_NO_EMPTY);
        $outerWords   = preg_split('/\s+/', trim($outerSelection), -1, PREG_SPLIT_NO_EMPTY);
        $reducedWords = array_values(array_filter(
            $outerWords,
            fn($w) => !in_array($w, $innerWords, true) && mb_strlen($w) >= 4
        ));

        if (count($reducedWords) < 2) {
            return [];
        }

        return $this->searchService->findPositions($html, implode(' ', $reducedWords));
    }

    public function getPreviousDump(int $pageId): ?MonitoringDump
    {
        $stmt = DB::getInstance()->prepare(
            'SELECT * FROM monitoring_dumps WHERE monitored_page_id = ?
             ORDER BY found_at DESC LIMIT 1'
        );
        $stmt->execute([$pageId]);
        $row = $stmt->fetch();

        return $row ? MonitoringDump::fromRow($row) : null;
    }

    public function hasChanged(string $previous, string $current): bool
    {
        return $previous !== $current;
    }
}
