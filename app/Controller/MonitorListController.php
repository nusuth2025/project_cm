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
                    mp.last_checked_at AS last_checked,
                    mp.check_count     AS dump_count,
                    (SELECT md.changed FROM monitoring_dumps md WHERE md.monitored_page_id = mp.id ORDER BY md.found_at DESC LIMIT 1) AS last_changed
             FROM monitored_pages mp
             WHERE mp.user_id = ?
             ORDER BY mp.created_at DESC'
        );
        $stmt->execute([$this->session->getUserId()]);
        $pages = $stmt->fetchAll();

        $this->render('monitor/list', ['pages' => $pages]);
    }
}
