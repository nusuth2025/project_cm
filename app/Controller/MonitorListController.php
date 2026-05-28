<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;

class MonitorListController extends AbstractController
{
    public function handle(): void
    {
        $this->requireLogin();

        $stmt = DB::getInstance()->prepare(
            'SELECT mp.*,
                    (SELECT COUNT(*) FROM monitoring_dumps md WHERE md.monitored_page_id = mp.id) AS dump_count,
                    (SELECT MAX(md2.found_at)  FROM monitoring_dumps md2 WHERE md2.monitored_page_id = mp.id) AS last_checked,
                    (SELECT md3.changed FROM monitoring_dumps md3 WHERE md3.monitored_page_id = mp.id ORDER BY md3.found_at DESC LIMIT 1) AS last_changed
             FROM monitored_pages mp
             WHERE mp.user_id = ?
             ORDER BY mp.created_at DESC'
        );
        $stmt->execute([$this->session->getUserId()]);
        $pages = $stmt->fetchAll();

        $this->render('monitor/list', ['pages' => $pages]);
    }
}
