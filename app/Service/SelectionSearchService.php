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
     * Sucht die Wörter der Auswahl sequenziell im HTML-Inhalt (Greedy-Forward-Algorithmus).
     *
     * Gibt ein flaches Array aus [start, end, start, end, ...] Byte-Positionen zurück,
     * oder ein leeres Array wenn mindestens ein Wort nicht gefunden wurde.
     *
     * Warum kein Konvergenz-Algorithmus (zweiter Pass): Der ursprüngliche Zweipass-Ansatz
     * terminierte nicht zuverlässig bei Auswahlen mit wiederholten Wörtern (z. B. "LibreOffice
     * ... LibreOffice"), da die Konvergenzschleife zwischen zwei Vorkommen endlos oszillierte.
     * Der Greedy-Ansatz ist korrekt und terminiert garantiert in O(n·m).
     *
     * @return int[]
     */
    public function findPositions(string $htmlContent, string $selection): array
    {
        $words = $this->tokenize($selection);
        if (empty($words)) {
            return [];
        }

        $positions    = [];
        $searchOffset = 0;

        foreach ($words as $word) {
            $wordStart = strpos($htmlContent, $word, $searchOffset);
            if ($wordStart === false) {
                return [];
            }
            $wordEnd        = $wordStart + strlen($word);
            $positions[]    = $wordStart;
            $positions[]    = $wordEnd;
            $searchOffset   = $wordEnd;
        }

        return $positions;
    }

    /**
     * Gibt Index und Text des ersten nicht gefundenen Wortes zurück, oder null.
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
     * Identische Logik wie PostSelection::showSelectionInTmp().
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
}
