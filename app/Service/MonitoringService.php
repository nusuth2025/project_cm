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
     */
    public function fetchHtml(string $url): string
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false, // kein HTTP-Header im Body
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
            // HTML-Tags maskieren (→ gleich viele Leerzeichen) damit findPositions()
            // keine Wörter in Attributwerten wie href-URLs findet, sondern nur im sichtbaren Text.
            // Byte-Positionen bleiben identisch zu $searchHtml.
            $searchHtmlMasked = $this->searchService->maskHtmlTags($searchHtml);

            $outerPositions   = $this->searchService->findPositions($searchHtmlMasked, $page->selectionText);
            $fullOuterFound   = !empty($outerPositions); // nur true wenn ALLE Auswahl-Wörter gefunden

            // Fallback: der Außenbereich enthält selbst die fragliche Feinauswahl.
            // Feinauswahl-Wörter aus dem Außenbereich herausrechnen und nochmal suchen,
            // damit der stabile Kontext (z. B. Stadtname) trotzdem als Anker dient.
            // $fullOuterFound bleibt false — findInnerInOuterPositions darf dann NICHT verwendet
            // werden, da die Positionen eine andere (kürzere) Wortliste repräsentieren.
            if (!$fullOuterFound && $page->innerSelectionText !== null && $page->innerSelectionText !== '') {
                $outerPositions = $this->findReducedOuterPositions(
                    $searchHtmlMasked,
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
                    // Wenn die volle Außenauswahl gefunden wurde, können Feinauswahl-Positionen
                    // direkt aus dem Positions-Array der Außenauswahl abgelesen werden
                    // (Phase 1 hat den richtigen "198,00" bereits lokalisiert).
                    // Bei reduzierter Außensuche hat das Positions-Array eine andere Wortliste →
                    // findInnerInOuterPositions wäre falsch, direktes HTML-Suchen wird genutzt.
                    $innerPositions = $fullOuterFound
                        ? $this->findInnerInOuterPositions(
                            $outerPositions,
                            $this->searchService->tokenize($page->selectionText),
                            $this->searchService->tokenize($page->innerSelectionText)
                        )
                        : [];

                    // findInnerInOuterPositions gibt absolute Positionen in $searchHtml zurück.
                    // substr($outerRegion, ...) erwartet Positionen relativ zu $regionStart.
                    if (!empty($innerPositions)) {
                        for ($k = 0; $k < count($innerPositions); $k++) {
                            $innerPositions[$k] -= $regionStart;
                        }
                    }

                    $innerExactFound = false;

                    if (!empty($innerPositions)) {
                        // findInnerInOuterPositions hat Positionen geliefert (relativ zu $regionStart).
                        $matchedParts = [];
                        for ($i = 0; $i < count($innerPositions); $i += 2) {
                            $matchedParts[] = substr(
                                $outerRegion,
                                $innerPositions[$i],
                                $innerPositions[$i + 1] - $innerPositions[$i]
                            );
                        }
                        $checkedContent  = implode(' ', $matchedParts);
                        $compareContent  = $checkedContent;
                        $innerExactFound = true;
                    } else {
                        // Fallback: Suche im bereinigten Klartext — vermeidet Treffer in
                        // URL-Attributen, href-Werten oder anderen nicht sichtbaren HTML-Teilen.
                        $textPositions = $this->searchService->findPositions($outerText, $page->innerSelectionText);
                        if (!empty($textPositions)) {
                            $matchedParts = [];
                            for ($i = 0; $i < count($textPositions); $i += 2) {
                                // findPositions liefert Byte-Positionen (strpos) →
                                // substr verwenden, nicht mb_substr (Zeichen-Positionen)
                                $matchedParts[] = substr(
                                    $outerText,
                                    $textPositions[$i],
                                    $textPositions[$i + 1] - $textPositions[$i]
                                );
                            }
                            $checkedContent  = implode(' ', $matchedParts);
                            $compareContent  = $checkedContent;
                            $innerExactFound = true;
                        }
                    }

                    if ($innerExactFound) {
                        // Relative Position im Klartext-Umfeld speichern — als Referenz für
                        // den Positions-Fallback, falls die Feinauswahl künftig nicht mehr
                        // gefunden wird und auch die Mustererkennung scheitert.
                        if ($checkedContent !== '') {
                            $innerTextPos = mb_strpos($outerText, $checkedContent);
                            if ($innerTextPos !== false) {
                                DB::getInstance()->prepare(
                                    'UPDATE monitored_pages SET inner_selection_offsets = ? WHERE id = ?'
                                )->execute([json_encode([
                                    'start'     => $innerTextPos,
                                    'end'       => $innerTextPos + mb_strlen($checkedContent),
                                    'outer_len' => mb_strlen($outerText),
                                ]), $page->id]);
                            }
                        }
                    } else {
                        // Feinauswahl nicht mehr exakt gefunden.
                        // Fallback 1: Muster aus dem ursprünglichen Wert ableiten (z. B. "16:09" → \d+:\d+)
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
                            // Fallback 2: gespeicherte relative Position verwenden —
                            // skaliert auf die aktuelle Länge des Umfelds, ±5 Zeichen Kontext.
                            $posVal  = $this->extractByStoredOffsets($page, $outerText);
                            $prevVal = (string) $prevContent;

                            if ($posVal !== null) {
                                $checkedContent = $posVal;
                                $compareContent = $posVal;
                                if ($prevVal !== '' && $prevVal !== '__OUTER_NOT_FOUND__') {
                                    $changeNote = sprintf(
                                        "Feinauswahl nicht mehr gefunden — Wert an gespeicherter Position.\nVorher: »%s«\nUngefähr jetzt: »%s«",
                                        mb_substr($prevVal, 0, 100),
                                        $posVal
                                    );
                                } else {
                                    $changeNote = sprintf(
                                        "Feinauswahl nicht mehr gefunden — Wert an gespeicherter Position: »%s«",
                                        $posVal
                                    );
                                }
                            } else {
                                // Letzter Ausweg: gesamten Umfeld-Bereich ausgeben
                                $checkedContent = mb_substr($outerText, 0, 500);
                                $compareContent = $checkedContent;
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

        // Dump nur speichern wenn:
        //   a) Erster Lauf (kein Vorgänger) → Basislinie für spätere Vergleiche
        //   b) Änderung erkannt → Nachweis der Änderung
        // Unveränderte Folgeprüfungen werden verworfen, um die DB nicht zu überfüllen.
        $db     = DB::getInstance();
        $dumpId = 0;

        if ($previous === null || $changed) {
            $stmt = $db->prepare(
                'INSERT INTO monitoring_dumps (monitored_page_id, html_content, checked_content, changed)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$page->id, $htmlContent, $checkedContent, $changed ? 1 : 0]);
            $dumpId = (int) $db->lastInsertId();
        }

        $dump                         = new MonitoringDump();
        $dump->id                     = $dumpId;
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

    /**
     * Positions-Fallback: extrahiert Text an der gespeicherten relativen Position
     * der Feinauswahl im Umfeld-Klartext.
     *
     * Die gespeicherten Offsets werden auf die aktuelle Länge des Umfelds skaliert,
     * um Verschiebungen durch Seitenänderungen auszugleichen. Der extrahierte Bereich
     * wird um je 5 Zeichen erweitert, bleibt aber innerhalb des Umfelds.
     *
     * Gibt null zurück wenn keine Offsets gespeichert sind oder der Bereich leer ist.
     */
    private function extractByStoredOffsets(MonitoredPage $page, string $outerText): ?string
    {
        if ($page->innerSelectionOffsets === null) {
            return null;
        }

        $offsets = json_decode($page->innerSelectionOffsets, true);
        if (!is_array($offsets)
            || !isset($offsets['start'], $offsets['end'], $offsets['outer_len'])
            || $offsets['outer_len'] <= 0
        ) {
            return null;
        }

        $currentLen = mb_strlen($outerText);
        if ($currentLen === 0) {
            return null;
        }

        // Positionen auf aktuelle Länge skalieren
        $scale       = $currentLen / $offsets['outer_len'];
        $approxStart = (int) round($offsets['start'] * $scale);
        $approxEnd   = (int) round($offsets['end']   * $scale);

        // ±5 Zeichen Kontext, innerhalb des Umfelds begrenzen
        $margin   = 5;
        $extStart = max(0, $approxStart - $margin);
        $extEnd   = min($currentLen, $approxEnd + $margin);

        $extracted = trim(mb_substr($outerText, $extStart, $extEnd - $extStart));

        return $extracted !== '' ? $extracted : null;
    }

    private function findReducedOuterPositions(string $html, string $outerSelection, string $innerSelection): array
    {
        $innerWords   = preg_split('/\s+/', trim($innerSelection), -1, PREG_SPLIT_NO_EMPTY);
        $outerWords   = preg_split('/\s+/', trim($outerSelection), -1, PREG_SPLIT_NO_EMPTY);
        $reducedWords = [];
        foreach ($outerWords as $w) {
            if (!in_array($w, $innerWords, true) && mb_strlen($w) >= 4) {
                $reducedWords[] = $w;
            }
        }

        if (count($reducedWords) < 2) {
            return [];
        }

        return $this->searchService->findPositions($html, implode(' ', $reducedWords));
    }

    private function getPreviousDump(int $pageId): ?MonitoringDump
    {
        $stmt = DB::getInstance()->prepare(
            'SELECT * FROM monitoring_dumps WHERE monitored_page_id = ?
             ORDER BY found_at DESC LIMIT 1'
        );
        $stmt->execute([$pageId]);
        $row = $stmt->fetch();

        return $row ? MonitoringDump::fromRow($row) : null;
    }

    private function hasChanged(string $previous, string $current): bool
    {
        return $previous !== $current;
    }
}
