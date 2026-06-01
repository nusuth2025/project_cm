<?php
declare(strict_types=1);

namespace App\Service;

class SelectionSearchService
{
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

    /**
     * Findet alle Wörter der Auswahl im HTML und gibt ihre Byte-Positionen zurück.
     *
     * Algorithmus — Phase 1 der ursprünglichen checkIfWorkingSelection():
     *
     *   Startpunkt-Hint: Die ersten 3 Wörter werden als Phrase gesucht.
     *   Ist diese Phrase direkt im HTML vorhanden (z. B. "Samsung Galaxy M35"
     *   im Produkttitel), wird die Suche von dort aus gestartet. Das verhindert,
     *   dass häufige Einzelwörter (wie "Samsung" in der Navigation) einen falschen
     *   Startanker setzen.
     *
     *   Greedy-Forward mit Rechts-Advance:
     *   Für jedes Wort wird die erste Position nach dem Vorgänger gesucht.
     *   Kommt dasselbe Wort erneut vor dem nächsten Wort vor, wird diese spätere
     *   Position übernommen. So landet z. B. das kurze "ab" vor "198,00 €" direkt
     *   an der richtigen Stelle und nicht irgendwo früher im Text.
     *
     *   Terminiert garantiert in O(n · k): n = Wörteranzahl, k = max. Wiederholungen
     *   des häufigsten Worts im Abstand zum jeweils nächsten.
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

        // Startpunkt-Hint: erste 3 Wörter als Phrase suchen
        // Ist "Samsung Galaxy M35" als zusammenhängender Text findbar (z. B. im <h1>),
        // starten wir direkt dort, statt bei der ersten "Samsung"-Erwähnung in der Nav.
        $phraseWords = array_slice($words, 0, min(3, $n));
        $phrase      = implode(' ', $phraseWords);
        $hintPos     = strpos($htmlContent, $phrase);
        $startFrom   = ($hintPos !== false) ? $hintPos : 0;

        // ── Greedy-Forward mit Rechts-Advance ─────────────────────────────────
        $positions = array_fill(0, $n * 2, 0);
        $prevEnd   = $startFrom;

        for ($x = 0; $x < $n; $x++) {
            $strpos = strpos($htmlContent, $words[$x], $prevEnd);
            if ($strpos === false) {
                return [];
            }

            // Advance: wenn das gleiche Wort nochmal vor dem nächsten kommt,
            // nehme die spätere Position (näher am Nachfolger)
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

        return $positions;
    }

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

    /**
     * Baut einen mit |#|Wort|##| markierten String aus dem Positionsarray auf.
     *
     * @param int[] $positions
     */
    public function buildMarkedContent(string $htmlContent, array $positions): string
    {
        if (empty($positions)) {
            return $htmlContent;
        }

        $result = substr($htmlContent, 0, $positions[0]);

        for ($i = 0; $i < count($positions); $i += 2) {
            $start = $positions[$i];
            $end   = $positions[$i + 1];

            $between = '';
            if ($i > 0 && $start > $positions[$i - 1] + 1) {
                $between = substr($htmlContent, $positions[$i - 1] + 1, $start - ($positions[$i - 1] + 1));
            }

            $result .= $between;
            $result .= '|#|' . substr($htmlContent, $start, $end - $start) . '|##|';
        }

        return $result;
    }

    /**
     * Erzeugt HTML mit farbig hervorgehobenen Fundstellen für die Debug-Ansicht.
     *
     * Äußere Fundstellen  → gelb  (CSS-Klasse hl-outer)
     * Innere Fundstellen  → orange (CSS-Klasse hl-inner)
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

        $toRelative = fn(int $p): int => $p - $showFrom;

        $spans = [];
        for ($i = 0; $i < count($outerPositions); $i += 2) {
            $s = $toRelative($outerPositions[$i]);
            $e = $toRelative($outerPositions[$i + 1]);
            if ($s >= 0 && $e <= strlen($region)) {
                $spans[] = [$s, $e, 'outer'];
            }
        }
        foreach (array_chunk($innerPositions, 2) as [$s, $e]) {
            $rs = $toRelative($s);
            $re = $toRelative($e);
            if ($rs >= 0 && $re <= strlen($region)) {
                $spans[] = [$rs, $re, 'inner'];
            }
        }

        usort($spans, fn($a, $b) => $a[0] <=> $b[0]);

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
