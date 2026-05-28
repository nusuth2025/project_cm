<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;
use App\Service\SessionService;

class MonitorDeleteController extends AbstractController
{
    public function __construct(SessionService $session, private readonly int $pageId)
    {
        parent::__construct($session);
    }

    public function handle(): void
    {
        $this->requireLogin();

        // Nur POST erlaubt (verhindert versehentliches Löschen per Link)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/list');
        }

        // Ownership-Check: nur eigene Einträge löschen
        DB::getInstance()
            ->prepare('DELETE FROM monitored_pages WHERE id = ? AND user_id = ?')
            ->execute([$this->pageId, $this->session->getUserId()]);

        $this->redirect('/list');
    }
}
