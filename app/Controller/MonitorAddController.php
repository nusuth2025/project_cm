<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;
use App\Service\SessionService;
use App\Service\UrlCheckService;
use App\Service\SelectionSearchService;
use App\Service\MonitoringService;

class MonitorAddController extends AbstractController
{
    private UrlCheckService $urlChecker;
    private SelectionSearchService $searchService;
    private MonitoringService $monitoringService;

    public function __construct(SessionService $session)
    {
        parent::__construct($session);
        $this->urlChecker        = new UrlCheckService();
        $this->searchService     = new SelectionSearchService();
        $this->monitoringService = new MonitoringService($this->searchService);
    }

    public function handle(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            unset($_SESSION['S_URL'], $_SESSION['S_SELECTION'], $_SESSION['S_INNERSELECTION']);
        }

        $urlState        = UrlState::NotSet;
        $postState       = PostState::NotSet;
        $urlError        = null;
        $urlWarning      = null;
        $urlSuggestedUrl = null;
        $displayUrl      = null; // eingetippte URL bei fehlgeschlagenem Check
        $selectionError  = null;
        $missingWord     = null;

        // Schritt 1: URL-Formular abgeschickt
        if (isset($_POST['url'])) {
            // Rohe URL für cURL und Datenbank; htmlspecialchars nur für HTML-Ausgabe (im View)
            $submittedUrl = stripslashes(trim($_POST['url']));
            $urlResult    = $this->urlChecker->check($submittedUrl);

            if ($urlResult->isUsable) {
                // Immer die finale URL (nach Weiterleitungen) speichern
                $this->session->setUrl($urlResult->effectiveUrl);
                $urlState = UrlState::Valid;
                if ($urlResult->severity !== 'ok') {
                    $urlWarning = $urlResult->message;
                }
            } else {
                $urlState   = UrlState::NotWorking;
                $urlError   = $urlResult->message;
                $displayUrl = $submittedUrl; // eingetippte URL ins Feld zurückschreiben
                // Veraltete Session-URL löschen, damit kein alter Wert durchsickert
                unset($_SESSION['S_URL'], $_SESSION['S_SELECTION'], $_SESSION['S_INNERSELECTION']);
                if ($urlResult->wasRedirected($submittedUrl)) {
                    $urlSuggestedUrl = $urlResult->effectiveUrl;
                }
            }
        } elseif ($this->session->getUrl() !== null) {
            $urlState = UrlState::Valid;
        }

        // Schritt 2: Auswahl-Formular abgeschickt
        $failedSelection = null;
        if (isset($_POST['selection']) && $urlState === UrlState::Valid) {
            // Roher Text aus der Textarea — kein htmlspecialchars, sonst wird ' zu &#039;
            // und passt nicht mehr auf &rsquo;/&#8217; im HTML. Ausgabe im View ist via
            // htmlspecialchars($currentSel) abgesichert.
            $selection  = stripslashes(trim($_POST['selection']));
            $rawHtml    = $this->monitoringService->fetchHtml($this->session->getUrl());
            // Entities dekodieren damit &#039;, &rsquo;, &#8217; etc. alle als ' verglichen werden
            $searchHtml = html_entity_decode($rawHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $positions  = $this->searchService->findPositions($searchHtml, $selection);

            if (empty($positions)) {
                $missingWord     = $this->searchService->findMissingWord($searchHtml, $selection);
                $postState       = PostState::Problem;
                $failedSelection = $selection;
                $selectionError  = $missingWord !== null
                    ? 'Das Wort "' . htmlspecialchars($missingWord['word']) . '" wurde nicht gefunden. Möglicherweise fehlt nur ein Leerzeichen zwischen den Worten.'
                    : 'Die Auswahl wurde im Seiteninhalt nicht gefunden.';
            } else {
                $this->session->setSelection($selection);
                // Feinauswahl zurücksetzen, wenn das Umfeld neu gesetzt wird
                $this->session->clearInnerSelection();
                $postState = PostState::Valid;
            }
        } elseif ($this->session->getSelection() !== null && $urlState === UrlState::Valid) {
            $postState = PostState::Valid;
        }

        // Schritt 3: Feinauswahl
        if ($postState === PostState::Valid) {
            if (isset($_POST['apply_inner'])) {
                // Ausgewählte Wörter übernehmen (leer = kein Wort gewählt = wie Überspringen)
                $this->session->setInnerSelection(trim($_POST['inner_selection'] ?? ''));
            } elseif (isset($_POST['skip_inner'])) {
                $this->session->setInnerSelection('');
            }
        }

        // Feinauswahl-Status aus Session ableiten
        // 'hidden'  → Außenauswahl noch nicht gültig
        // 'pending' → Außenauswahl gültig, Feinauswahl noch nicht bearbeitet
        // 'done'    → Feinauswahl gesetzt oder übersprungen
        $innerState = 'hidden';
        if ($postState === PostState::Valid) {
            $innerState = ($this->session->getInnerSelection() === null) ? 'pending' : 'done';
        }

        // Schritt 4: Speichern
        if (isset($_POST['save']) && $urlState === UrlState::Valid
            && $postState === PostState::Valid && $innerState === 'done'
        ) {
            $db           = DB::getInstance();
            $days         = max(0, (int) ($_POST['interval_days']    ?? 0));
            $hours        = max(0, min(23, (int) ($_POST['interval_hours']   ?? 0)));
            $mins         = (int) round(max(0, min(45, (int) ($_POST['interval_minutes'] ?? 0))) / 15) * 15;
            $totalMinutes = max(15, $days * 1440 + $hours * 60 + $mins);
            $startHour    = max(0, min(23, (int) ($_POST['start_hour'] ?? 8)));

            $rawInner     = $this->session->getInnerSelection();
            $innerSelText = ($rawInner !== null && $rawInner !== '') ? $rawInner : null;

            $stmt = $db->prepare(
                'INSERT INTO monitored_pages
                     (user_id, url, selection_text, inner_selection_text, label,
                      check_interval_minutes, start_hour, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->session->getUserId(),
                $this->session->getUrl(),
                $this->session->getSelection(),
                $innerSelText,
                isset($_POST['label']) && $_POST['label'] !== '' ? trim($_POST['label']) : null,
                $totalMinutes,
                $startHour,
                'active',
            ]);
            $newId = (int) $db->lastInsertId();

            // Initialen Dump sofort erzeugen — damit die Quelltext-Ansicht
            // direkt nach dem Speichern nutzbar ist.
            try {
                $pageRow = $db->prepare('SELECT * FROM monitored_pages WHERE id = ?');
                $pageRow->execute([$newId]);
                $page = \App\Model\MonitoredPage::fromRow($pageRow->fetch());
                $this->monitoringService->runCheck($page);
                $db->prepare('UPDATE monitored_pages SET last_checked_at = NOW(), check_count = 1 WHERE id = ?')
                   ->execute([$newId]);
            } catch (\Throwable) {
                // Netzwerkfehler o.ä.: Monitor wurde gespeichert, Dump folgt beim nächsten Cron-Lauf
            }

            $this->session->clearMonitorFlow();
            $this->redirect('/monitor/' . $newId);
        }

        // Zurücksetzen
        if (isset($_POST['reset'])) {
            $this->session->clearMonitorFlow();
            $this->redirect('/add');
        }

        $this->render('monitor/add', [
            'urlState'        => $urlState,
            'postState'       => $postState,
            'innerState'      => $innerState,
            'currentUrl'      => $displayUrl ?? $this->session->getUrl(),
            'currentSel'      => $failedSelection ?? $this->session->getSelection(),
            'currentInnerSel' => $this->session->getInnerSelection(),
            'urlError'        => $urlError,
            'urlWarning'      => $urlWarning,
            'urlSuggestedUrl' => $urlSuggestedUrl,
            'selectionError'  => $selectionError,
            'missingWord'     => $missingWord,
        ]);
    }
}
