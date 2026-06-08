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
            $label   = trim($_POST['label'] ?? '');
            $status  = $_POST['status'] ?? '';

            $days         = max(0, (int) ($_POST['interval_days']    ?? 0));
            $hours        = max(0, min(23, (int) ($_POST['interval_hours']   ?? 0)));
            $mins         = (int) round(max(0, min(45, (int) ($_POST['interval_minutes'] ?? 0))) / 15) * 15;
            $totalMinutes = $days * 1440 + $hours * 60 + $mins;
            $startHour    = max(0, min(23, (int) ($_POST['start_hour'] ?? 8)));

            if (!in_array($status, ['active', 'paused'], true)) {
                $errors[] = 'Ungültiger Status.';
            }
            if ($totalMinutes < 15) {
                $errors[] = 'Das Prüfintervall muss mindestens 15 Minuten betragen.';
            }

            if (empty($errors)) {
                $db->prepare(
                    'UPDATE monitored_pages
                     SET label = ?, status = ?, check_interval_minutes = ?, start_hour = ?
                     WHERE id = ?'
                )->execute([$label !== '' ? $label : null, $status, $totalMinutes, $startHour, $this->pageId]);
                $this->redirect('/monitor/' . $this->pageId);
            }
        }

        $this->render('monitor/edit', ['page' => $page, 'errors' => $errors]);
    }
}
