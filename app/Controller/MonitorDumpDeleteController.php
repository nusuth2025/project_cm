<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;
use App\Service\SessionService;

class MonitorDumpDeleteController extends AbstractController
{
    public function __construct(
        SessionService $session,
        private readonly int $pageId,
        private readonly int $dumpId
    ) {
        parent::__construct($session);
    }

    public function handle(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/monitor/' . $this->pageId);
        }

        $db = DB::getInstance();

        // Sicherstellen dass der Dump zum Monitor des eingeloggten Users gehört
        $stmt = $db->prepare(
            'SELECT d.id FROM monitoring_dumps d
             JOIN monitored_pages mp ON mp.id = d.monitored_page_id
             WHERE d.id = ? AND mp.id = ? AND mp.user_id = ?'
        );
        $stmt->execute([$this->dumpId, $this->pageId, $this->session->getUserId()]);

        if (!$stmt->fetch()) {
            $this->redirect('/monitor/' . $this->pageId);
        }

        // Initialen Dump (ältesten) schützen — dieser dient als Basislinie
        $firstStmt = $db->prepare(
            'SELECT id FROM monitoring_dumps WHERE monitored_page_id = ?
             ORDER BY found_at ASC LIMIT 1'
        );
        $firstStmt->execute([$this->pageId]);
        $firstRow = $firstStmt->fetch();

        if ($firstRow && (int)$firstRow['id'] === $this->dumpId) {
            $this->redirect('/monitor/' . $this->pageId);
        }

        $db->prepare('DELETE FROM monitoring_dumps WHERE id = ?')
           ->execute([$this->dumpId]);

        $this->redirect('/monitor/' . $this->pageId);
    }
}
