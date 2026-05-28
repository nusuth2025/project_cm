<?php
declare(strict_types=1);

// Nur als CLI-Script erlaubt
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only' . PHP_EOL);
}

require_once dirname(__DIR__, 2) . '/app/config.php';

use App\Model\DB;
use App\Model\MonitoredPage;
use App\Service\SelectionSearchService;
use App\Service\MonitoringService;

$opts     = getopt('', ['page-id:', 'all', 'dry-run']);
$isDryRun = isset($opts['dry-run']);

if ($isDryRun) {
    echo '[DRY RUN] Keine Änderungen werden in der DB gespeichert.' . PHP_EOL;
}

$searchService     = new SelectionSearchService();
$monitoringService = new MonitoringService($searchService);
$db                = DB::getInstance();

if (isset($opts['page-id'])) {
    $pageId = (int) $opts['page-id'];
    $stmt   = $db->prepare('SELECT * FROM monitored_pages WHERE id = ? AND status = ?');
    $stmt->execute([$pageId, 'active']);
    $pages = $stmt->fetchAll();
} elseif (isset($opts['all'])) {
    $stmt = $db->prepare('SELECT * FROM monitored_pages WHERE status = ?');
    $stmt->execute(['active']);
    $pages = $stmt->fetchAll();
} else {
    echo 'Verwendung: php monitor.php [--page-id=X | --all] [--dry-run]' . PHP_EOL;
    exit(1);
}

if (empty($pages)) {
    echo 'Keine aktiven Monitore gefunden.' . PHP_EOL;
    exit(0);
}

foreach ($pages as $row) {
    $page = MonitoredPage::fromRow($row);
    echo "Prüfe #{$page->id}: {$page->url}" . PHP_EOL;

    try {
        if ($isDryRun) {
            $html = $monitoringService->fetchHtml($page->url);
            echo '  [DRY RUN] HTML geladen (' . strlen($html) . ' Bytes).' . PHP_EOL;
            continue;
        }

        $dump = $monitoringService->runCheck($page);

        if ($dump->changed) {
            echo "  ÄNDERUNG festgestellt — Dump #{$dump->id} gespeichert." . PHP_EOL;
        } else {
            echo '  Keine Änderung.' . PHP_EOL;
        }
    } catch (\Throwable $e) {
        echo '  FEHLER: ' . $e->getMessage() . PHP_EOL;

        if (!$isDryRun) {
            $db->prepare('UPDATE monitored_pages SET status = ? WHERE id = ?')
               ->execute(['error', $page->id]);
        }
    }
}

echo 'Fertig.' . PHP_EOL;
exit(0);
