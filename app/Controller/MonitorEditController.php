<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;
use App\Service\SessionService;

class MonitorEditController extends AbstractController
{
    public function __construct(SessionService $session, private readonly int $pageId)
    {
        parent::__construct($session);
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

        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $label  = htmlspecialchars(trim($_POST['label'] ?? ''));
            $status = $_POST['status'] ?? '';

            if (!in_array($status, ['active', 'paused'], true)) {
                $errors[] = 'Ungültiger Status.';
            }

            if (empty($errors)) {
                $db->prepare('UPDATE monitored_pages SET label = ?, status = ? WHERE id = ?')
                   ->execute([$label !== '' ? $label : null, $status, $this->pageId]);
                $this->redirect('/monitor/' . $this->pageId);
            }
        }

        $this->render('monitor/edit', ['page' => $page, 'errors' => $errors]);
    }
}
