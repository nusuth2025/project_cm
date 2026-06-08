<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;
use App\Service\SessionService;
use App\Service\SelectionSearchService;
use App\Service\MonitoringService;

/**
 * Debug-Ansicht: Zeigt den aktuellen Seitenquelltext mit farbig markierten
 * Fundstellen der Umfeld- und Feinauswahl. Nützlich um zu prüfen, ob der
 * Such-Algorithmus die beabsichtigte Stelle trifft.
 */
class MonitorDebugController extends AbstractController
{
    private SelectionSearchService $searchService;
    private MonitoringService      $monitoringService;

    public function __construct(SessionService $session, private readonly int $pageId)
    {
        parent::__construct($session);
        $this->searchService     = new SelectionSearchService();
        $this->monitoringService = new MonitoringService($this->searchService);
    }

    public function handle(): void
    {
        $this->requireLogin();

        $db   = DB::getInstance();
        $stmt = $db->prepare('SELECT * FROM monitored_pages WHERE id = ? AND user_id = ?');
        $stmt->execute([$this->pageId, $this->session->getUserId()]);
        $page = $stmt->fetch();

        if (!$page) {
            http_response_code(404);
            echo '<!DOCTYPE html><html lang="de"><body><h1>404 – Monitor nicht gefunden</h1></body></html>';
            return;
        }

        $error           = null;
        $highlightedHtml = '';
        $outerFound      = false;
        $innerFound      = false;
        $outerSpan       = null;
        $innerValue      = null;
        $sourceLabel     = '';
        $dumpFoundAt     = null;
        $currentDumpId   = null;

        // Alle verfügbaren Dumps laden (für Navigation in der Ansicht)
        $dumpListStmt = $db->prepare(
            'SELECT id, found_at, changed FROM monitoring_dumps
             WHERE monitored_page_id = ? AND html_content != ""
             ORDER BY found_at DESC'
        );
        $dumpListStmt->execute([$this->pageId]);
        $availableDumps = $dumpListStmt->fetchAll();

        // Quelle bestimmen:
        //   ?dump_id=X  → konkreter Dump
        //   ?quelle=dump → letzter Dump (Rückwärtskompatibilität)
        //   sonst       → Live-Abruf
        $dumpIdParam = isset($_GET['dump_id']) ? (int)$_GET['dump_id'] : null;
        $useDump     = $dumpIdParam !== null || ($_GET['quelle'] ?? '') === 'dump';

        if ($page['selection_text'] === null) {
            $error = 'Für diesen Monitor ist kein Umfeld-Text gesetzt.';
        } else {
            if ($useDump) {
                if ($dumpIdParam !== null) {
                    $dumpStmt = $db->prepare(
                        'SELECT id, html_content, found_at FROM monitoring_dumps
                         WHERE id = ? AND monitored_page_id = ? AND html_content != ""'
                    );
                    $dumpStmt->execute([$dumpIdParam, $this->pageId]);
                } else {
                    $dumpStmt = $db->prepare(
                        'SELECT id, html_content, found_at FROM monitoring_dumps
                         WHERE monitored_page_id = ? AND html_content != ""
                         ORDER BY found_at DESC LIMIT 1'
                    );
                    $dumpStmt->execute([$this->pageId]);
                }
                $dumpRow = $dumpStmt->fetch();

                if (!$dumpRow || $dumpRow['html_content'] === '') {
                    $error   = 'Kein gespeicherter Dump vorhanden (oder HTML-Inhalt leer). Bitte zuerst einen Monitor-Lauf starten.';
                    $rawHtml = '';
                } else {
                    $rawHtml       = $dumpRow['html_content'];
                    $dumpFoundAt   = $dumpRow['found_at'];
                    $currentDumpId = (int)$dumpRow['id'];
                }
                $sourceLabel = 'Dump';
            } else {
                // Live-Abruf
                $rawHtml     = $this->monitoringService->fetchHtml($page['url']);
                $sourceLabel = 'Live';
            }
            $searchHtml = preg_replace(
                ['/<script[^>]*>[\s\S]*?<\/script>/i', '/<style[^>]*>[\s\S]*?<\/style>/i'],
                '',
                html_entity_decode($rawHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            );

            // HTML-Tags maskieren damit findPositions() keine Wörter in Attributwerten findet.
            // Byte-Positionen bleiben identisch zu $searchHtml.
            $searchHtmlMasked = $this->searchService->maskHtmlTags($searchHtml);

            // Umfeld suchen
            $outerPositions = $this->searchService->findPositions($searchHtmlMasked, $page['selection_text']);

            // Feinauswahl-Positionen aus den äußeren Positionen ableiten
            // (identische Strategie wie MonitoringService::findInnerInOuterPositions)
            $innerAbsolute = [];
            if (!empty($outerPositions) && !empty($page['inner_selection_text'])) {
                $outerWords = $this->searchService->tokenize($page['selection_text']);
                $innerWords = $this->searchService->tokenize($page['inner_selection_text']);
                $nInner     = count($innerWords);
                $nOuter     = count($outerWords);

                for ($i = $nOuter - $nInner; $i >= 0; $i--) {
                    $match = true;
                    for ($j = 0; $j < $nInner; $j++) {
                        if ($outerWords[$i + $j] !== $innerWords[$j]) { $match = false; break; }
                    }
                    if ($match) {
                        for ($j = 0; $j < $nInner; $j++) {
                            $innerAbsolute[] = $outerPositions[($i + $j) * 2];
                            $innerAbsolute[] = $outerPositions[($i + $j) * 2 + 1];
                        }
                        break;
                    }
                }

                // Fallback: direkte Suche im bereits tag-maskierten HTML
                if (empty($innerAbsolute)) {
                    $regionStart  = $outerPositions[0];
                    $regionEnd    = $outerPositions[count($outerPositions) - 1];
                    $maskedRegion = substr($searchHtmlMasked, $regionStart, $regionEnd - $regionStart);

                    $relPositions = $this->searchService->findPositions($maskedRegion, $page['inner_selection_text']);
                    foreach (array_chunk($relPositions, 2) as [$s, $e]) {
                        $innerAbsolute[] = $regionStart + $s;
                        $innerAbsolute[] = $regionStart + $e;
                    }
                }
            }

            $outerFound = !empty($outerPositions);
            $innerFound = !empty($innerAbsolute);

            if ($outerFound) {
                $outerSpan = [$outerPositions[0], end($outerPositions)];
                // Extrahierten Wert der Feinauswahl für Anzeige
                if ($innerFound) {
                    $parts = [];
                    foreach (array_chunk($innerAbsolute, 2) as [$s, $e]) {
                        $parts[] = substr($searchHtml, $s, $e - $s);
                    }
                    $innerValue = implode(' ', $parts);
                }
            }

            if (!$outerFound) {
                // Auch Fallback mit reduzierter Auswahl versuchen
                if (!empty($page['inner_selection_text'])) {
                    $innerWords  = preg_split('/\s+/', trim($page['inner_selection_text']), -1, PREG_SPLIT_NO_EMPTY);
                    $outerWords  = preg_split('/\s+/', trim($page['selection_text']),       -1, PREG_SPLIT_NO_EMPTY);
                    $reducedWords = [];
                    foreach ($outerWords as $w) {
                        if (!in_array($w, $innerWords, true) && mb_strlen($w) >= 4) {
                            $reducedWords[] = $w;
                        }
                    }
                    if (count($reducedWords) >= 2) {
                        $outerPositions = $this->searchService->findPositions(
                            $searchHtmlMasked,
                            implode(' ', $reducedWords)
                        );
                        if (!empty($outerPositions)) {
                            $outerFound = true;
                            $outerSpan  = [$outerPositions[0], end($outerPositions)];
                            $error = 'Umfeld nur mit reduzierter Suche gefunden (Feinauswahl-Wörter wurden herausgefiltert).';
                        }
                    }
                }
                if (!$outerFound) {
                    $sourceHint = $useDump ? 'im gespeicherten Dump' : 'im aktuellen Seiteninhalt';
                    $error = "Das Umfeld wurde $sourceHint nicht gefunden.";
                }
            }

            $highlightedHtml = $this->searchService->buildHighlightedHtml(
                $searchHtml,
                $outerPositions,
                $innerAbsolute,
                600
            );
        }

        $this->render('monitor/debug', [
            'page'            => $page,
            'error'           => $error,
            'highlightedHtml' => $highlightedHtml,
            'outerFound'      => $outerFound,
            'innerFound'      => $innerFound,
            'outerSpan'       => $outerSpan,
            'innerValue'      => $innerValue,
            'useDump'         => $useDump,
            'sourceLabel'     => $sourceLabel,
            'dumpFoundAt'     => $dumpFoundAt,
            'currentDumpId'   => $currentDumpId,
            'availableDumps'  => $availableDumps,
        ]);
    }
}
