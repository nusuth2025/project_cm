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

        // Frischer GET-Aufruf (kein POST, kein laufender Flow) → Session leeren
        // damit das Formular immer mit Placeholder startet
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            unset($_SESSION['S_URL'], $_SESSION['S_SELECTION'], $_SESSION['S_INNERSELECTION']);
        }

        $urlState       = UrlState::NotSet;
        $postState      = PostState::NotSet;
        $urlError       = null;
        $selectionError = null;
        $missingWord    = null;

        // Schritt 1: URL-Formular abgeschickt
        if (isset($_POST['url'])) {
            $submittedUrl = htmlspecialchars(stripslashes(trim($_POST['url'])));
            if ($this->urlChecker->isWorkingUrl($submittedUrl)) {
                $this->session->setUrl($submittedUrl);
                $urlState = UrlState::Valid;
            } else {
                $urlState = UrlState::NotWorking;
                $urlError = 'Diese Adresse ist nicht erreichbar oder liefert keinen HTML-Inhalt.';
            }
        } elseif ($this->session->getUrl() !== null) {
            $urlState = UrlState::Valid;
        }

        // Schritt 2: Auswahl-Formular abgeschickt
        $failedSelection = null;
        if (isset($_POST['selection']) && $urlState === UrlState::Valid) {
            $selection = htmlspecialchars(stripslashes(trim($_POST['selection'])));
            $html      = $this->monitoringService->fetchHtml($this->session->getUrl());
            $positions = $this->searchService->findPositions($html, $selection);

            if (empty($positions)) {
                $missingWord     = $this->searchService->findMissingWord($html, $selection);
                $postState       = PostState::Problem;
                $failedSelection = $selection; // zurück ins Formular schreiben
                $selectionError  = $missingWord !== null
                    ? 'Das Wort "' . htmlspecialchars($missingWord['word']) . '" wurde nicht gefunden.'
                    : 'Die Auswahl wurde im Seiteninhalt nicht gefunden.';
            } else {
                $this->session->setSelection($selection);
                $postState = PostState::Valid;
            }
        } elseif ($this->session->getSelection() !== null && $urlState === UrlState::Valid) {
            $postState = PostState::Valid;
        }

        // Schritt 3: Speichern
        if (isset($_POST['save']) && $urlState === UrlState::Valid && $postState === PostState::Valid) {
            $db   = DB::getInstance();
            $stmt = $db->prepare(
                'INSERT INTO monitored_pages (user_id, url, selection_text, label, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->session->getUserId(),
                $this->session->getUrl(),
                $this->session->getSelection(),
                isset($_POST['label']) && $_POST['label'] !== '' ? htmlspecialchars($_POST['label']) : null,
                'active',
            ]);
            $this->session->clearMonitorFlow();
            $this->redirect('/list');
        }

        // Schritt 4: Zurücksetzen
        if (isset($_POST['reset'])) {
            $this->session->clearMonitorFlow();
            $this->redirect('/add');
        }

        $this->render('monitor/add', [
            'urlState'       => $urlState,
            'postState'      => $postState,
            'currentUrl'     => $this->session->getUrl(),
            'currentSel'     => $failedSelection ?? $this->session->getSelection(),
            'urlError'       => $urlError,
            'selectionError' => $selectionError,
            'missingWord'    => $missingWord,
        ]);
    }
}
