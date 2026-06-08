<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Zweiphasige Positionssuche für Textauswahlen im HTML.
 *
 * Phase 1: Greedy-Forward mit Rechts-Advance.
 *   Startpunkt-Hint: erste 3 Wörter als Phrase gesucht, um häufige Einzelwörter
 *   in Navigation/Header zu überspringen.
 *   Für jedes Wort wird die Position nach dem Vorgänger gesucht. Kommt dasselbe
 *   Wort erneut vor dem nächsten vor, wird die spätere Position übernommen.
 *
 * Phase 2: Iterative Engstellen-Verfeinerung.
 *   Wiederholt Forward-Passes über die gefundenen Positionen.
 *   Wenn das nächste Wort früher auftaucht als gespeichert, wird das aktuelle
 *   Wort dichter herangezogen. Stoppt beim Fixpunkt → minimaler Span.
 *
 * Beispiel:
 *   HTML: "Samsung Galaxy Tab kaufen … Samsung Galaxy Note kaufen … Samsung Galaxy Tab S9 kaufen"
 *   Suche: "Samsung Galaxy Tab S9"
 *   Phase 1: Samsung=0,  Galaxy=8  → Span 76 Zeichen (falsche Instanz)
 *   Phase 2: Samsung=53, Galaxy=61 → Span 21 Zeichen (korrekte Instanz) ✓
 */
class SelectionSearchService
{
    // ── Vorverarbeitung ───────────────────────────────────────────────────────

    /**
     * Ersetzt jeden HTML-Tag (<tag ...>) durch gleich viele Leerzeichen.
     * Die String-Länge und damit alle Byte-Positionen bleiben erhalten —
     * Attributwerte (href, src, …) werden für Suchen unsichtbar.
     * Nützlich um findPositions() auf sichtbaren Text zu beschränken.
     */
    public function maskHtmlTags(string $html): string
    {
        return preg_replace_callback('/<[^>]*>/', function (array $m): string {
            return str_repeat(' ', strlen($m[0]));
        }, $html) ?? $html;
    }

    // ── Tokenisierung ─────────────────────────────────────────────────────────

    /**
     * Normalisiert Whitespace und zerlegt den Auswahltext in einzelne Wörter.
     * @return string[]
     */
    public function tokenize(string $selection): array
    {
        $normalized = str_replace(["\n", "\0", "\t", "\x0B", "\r"], ' ', $selection);
        $trimmed    = trim($normalized);
        $rawWords   = explode(' ', $trimmed);

        $words = [];
        foreach ($rawWords as $word) {
            if ($word !== '') {
                $words[] = $word;
            }
        }
        return $words;
    }

    // ── Positionssuche: Phase 1 + Phase 2 ────────────────────────────────────

    /**
     * Findet alle Wörter der Auswahl im HTML und gibt ihre Byte-Positionen zurück.
     * Führt Phase 1 (Greedy-Forward) und Phase 2 (Engstellen-Verfeinerung) aus.
     *
     * @return int[]  Flaches [start, end, start, end, …] Array oder [] wenn nicht gefunden.
     */
    public function findPositions(string $htmlContent, string $selection): array
    {
        $words = $this->tokenize($selection);
        if (empty($words)) {
            return [];
        }

        $n = count($words);

        // ── Phase 1: Greedy-Forward mit Rechts-Advance ────────────────────────
        // Startpunkt-Hint: erste 3 Wörter als Phrase suchen
        $phraseWords = array_slice($words, 0, min(3, $n));
        $hintPos     = strpos($htmlContent, implode(' ', $phraseWords));
        $prevEnd     = ($hintPos !== false) ? $hintPos : 0;

        $positions = array_fill(0, $n * 2, 0);

        for ($x = 0; $x < $n; $x++) {
            $strpos = strpos($htmlContent, $words[$x], $prevEnd);
            if ($strpos === false) {
                return [];
            }

            if ($x < $n - 1) {
                while (true) {
                    $nextsamepos = strpos($htmlContent, $words[$x],     $strpos + 1);
                    $next_x      = strpos($htmlContent, $words[$x + 1], $strpos + 1);

                    if ($nextsamepos !== false && $next_x !== false && $nextsamepos < $next_x) {
                        $strpos = $nextsamepos;
                    } else {
                        break;
                    }
                }
            }

            $positions[$x * 2]     = $strpos;
            $positions[$x * 2 + 1] = $strpos + strlen($words[$x]);
            $prevEnd = $strpos + strlen($words[$x]);
        }

        // ── Phase 2: Iterative Engstellen-Verfeinerung ────────────────────────
        if ($n > 1) {
            $positions = $this->refinePositions($htmlContent, $words, $positions);
        }

        return $positions;
    }

    /**
     * Phase 2: Iterative Engstellen-Verfeinerung.
     *
     * Pro Iteration (Forward-Pass):
     *   Für jedes Wort x (außer dem letzten):
     *     1. Suche Wort x ab Ende des Vorgängers.
     *     2. Falls Wort x+1 noch VOR seiner gespeicherten Position auftaucht:
     *        → Suche Wort x ab dieser früheren Fundstelle (dichter heran).
     *     3. Advance: Nimm das rechteste Vorkommen von x, das noch vor
     *        der gespeicherten Position von x+1 liegt.
     *   Wenn in einem vollständigen Pass keine Position geändert wurde → Fixpunkt.
     *
     * Konvergenz: Garantiert in ≤ n Iterationen.
     *
     * @param  string[] $words
     * @param  int[]    $positions  [start, end, …] aus Phase 1
     * @return int[]
     */
    private function refinePositions(string $html, array $words, array $positions): array
    {
        $n = count($words);

        for ($iter = 0; $iter < $n; $iter++) {
            $changed = false;
            $prevEnd = $positions[0];

            for ($x = 0; $x < $n; $x++) {
                $strpos = strpos($html, $words[$x], $prevEnd);
                if ($strpos === false) {
                    return $positions;
                }

                if ($x < $n - 1) {
                    $nextpos = $positions[($x + 1) * 2];

                    $next_x = strpos($html, $words[$x + 1], $strpos + 1);
                    if ($next_x !== false && $next_x < $nextpos) {
                        $closer = strpos($html, $words[$x], $next_x + 1);
                        if ($closer !== false) {
                            $strpos = $closer;
                        }
                    }

                    while (true) {
                        $nextsame = strpos($html, $words[$x], $strpos + 1);
                        if ($nextsame !== false && $nextsame < $nextpos) {
                            $strpos = $nextsame;
                        } else {
                            break;
                        }
                    }
                }

                $newEnd = $strpos + strlen($words[$x]);

                if ($positions[$x * 2] !== $strpos) {
                    $positions[$x * 2]     = $strpos;
                    $positions[$x * 2 + 1] = $newEnd;
                    $changed = true;
                }

                $prevEnd = $newEnd;
            }

            if (!$changed) {
                break;
            }
        }

        return $positions;
    }

    // ── Diagnose ──────────────────────────────────────────────────────────────

    /**
     * Gibt Index und Text des ersten nicht gefundenen Worts zurück, oder null.
     * @return array{index: int, word: string}|null
     */
    public function findMissingWord(string $htmlContent, string $selection): ?array
    {
        $words    = $this->tokenize($selection);
        $position = 0;

        foreach ($words as $index => $word) {
            $found = strpos($htmlContent, $word, $position);
            if ($found === false) {
                return ['index' => $index, 'word' => $word];
            }
            $position = $found + strlen($word);
        }
        return null;
    }

    // ── Ausgabe / Darstellung ─────────────────────────────────────────────────

    /**
     * Erzeugt HTML mit farbig hervorgehobenen Fundstellen für die Debug-Ansicht.
     *
     * Äußere Fundstellen → gelb  (CSS-Klasse hl-outer)
     * Innere Fundstellen → orange (CSS-Klasse hl-inner)
     *
     * @param int[] $outerPositions  [start, end, …]
     * @param int[] $innerPositions  [start, end, …]  (relativ zum selben $html)
     */
    public function buildHighlightedHtml(
        string $html,
        array  $outerPositions,
        array  $innerPositions = [],
        int    $contextChars   = 400
    ): string {
        if (empty($outerPositions)) {
            return '';
        }

        $showFrom = max(0, $outerPositions[0] - $contextChars);
        $showTo   = min(strlen($html), end($outerPositions) + $contextChars);
        $region   = substr($html, $showFrom, $showTo - $showFrom);

        // Äußere Spans, die sich mit einem inneren Span überlappen, werden als hl-inner gerendert.
        // Overlap-Prüfung statt exakter Startposition-Gleichheit, damit auch leichte Positions-
        // abweichungen zwischen verschiedenen Suchpfaden korrekt behandelt werden.
        $innerRanges = array_chunk($innerPositions, 2);

        $spans = [];
        for ($i = 0; $i < count($outerPositions); $i += 2) {
            $oStart = $outerPositions[$i];
            $oEnd   = $outerPositions[$i + 1];

            $overlaps = false;
            foreach ($innerRanges as [$is, $ie]) {
                if ($oStart < $ie && $oEnd > $is) {
                    $overlaps = true;
                    break;
                }
            }
            if ($overlaps) {
                continue; // wird als hl-inner gerendert
            }

            $s = $oStart - $showFrom;
            $e = $oEnd   - $showFrom;
            if ($s >= 0 && $e <= strlen($region)) {
                $spans[] = [$s, $e, 'outer'];
            }
        }
        foreach (array_chunk($innerPositions, 2) as [$s, $e]) {
            $rs = $s - $showFrom;
            $re = $e - $showFrom;
            if ($rs >= 0 && $re <= strlen($region)) {
                $spans[] = [$rs, $re, 'inner'];
            }
        }

        usort($spans, function(array $a, array $b): int {
            return $a[0] <=> $b[0];
        });

        $out = '';
        $pos = 0;
        foreach ($spans as [$start, $end, $type]) {
            if ($start < $pos) {
                continue;
            }
            $out .= htmlspecialchars(substr($region, $pos, $start - $pos));
            $class = $type === 'inner' ? 'hl-inner' : 'hl-outer';
            $out .= '<mark class="' . $class . '">'
                  . htmlspecialchars(substr($region, $start, $end - $start))
                  . '</mark>';
            $pos = $end;
        }
        $out .= htmlspecialchars(substr($region, $pos));

        return $out;
    }
}
