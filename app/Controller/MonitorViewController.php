<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;
use App\Service\SessionService;

class MonitorViewController extends AbstractController
{
    public function __construct(SessionService $session, private readonly int $pageId)
    {
        parent::__construct($session);
    }

    public function handle(): void
    {
        $this->requireLogin();

        $db   = DB::getInstance();
        $stmt = $db->prepare(
            'SELECT * FROM monitored_pages WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$this->pageId, $this->session->getUserId()]);
        $page = $stmt->fetch();

        if (!$page) {
            http_response_code(404);
            echo '<!DOCTYPE html><html lang="de"><body><h1>404 – Monitor nicht gefunden</h1></body></html>';
            return;
        }

        $stmt2 = $db->prepare(
            'SELECT id, found_at, changed,
                    LENGTH(html_content) AS html_bytes
             FROM monitoring_dumps
             WHERE monitored_page_id = ?
             ORDER BY found_at DESC
             LIMIT 20'
        );
        $stmt2->execute([$this->pageId]);
        $dumps = $stmt2->fetchAll();

        $this->render('monitor/view', ['page' => $page, 'dumps' => $dumps]);
    }
}
