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
use App\Service\NotificationService;

$opts     = getopt('', ['page-id:', 'all', 'dry-run']);
$isDryRun = isset($opts['dry-run']);

if ($isDryRun) {
    echo '[DRY RUN] Keine Änderungen werden in der DB gespeichert.' . PHP_EOL;
}

$searchService     = new SelectionSearchService();
$monitoringService = new MonitoringService($searchService);
$db                = DB::getInstance();

if (isset($opts['page-id'])) {
    // Manuelle Einzelprüfung: Intervall wird ignoriert
    $pageId = (int) $opts['page-id'];
    $stmt   = $db->prepare('SELECT * FROM monitored_pages WHERE id = ? AND status = ?');
    $stmt->execute([$pageId, 'active']);
    $pages = $stmt->fetchAll();
} elseif (isset($opts['all'])) {
    // Fällig-Logik:
    //
    // Erstmalige Prüfung (last_dump_at IS NULL):
    //   Fällig, sobald die konfigurierte Startzeit heute erreicht ist.
    //   Liegt sie bereits in der Vergangenheit, ist der Monitor sofort fällig.
    //
    // Folgeprüfung (last_dump_at IS NOT NULL):
    //   Fällig, wenn letzter Dump + check_interval_minutes <= NOW().
    $stmt = $db->prepare(
        'SELECT mp.*,
            (SELECT MAX(found_at) FROM monitoring_dumps WHERE monitored_page_id = mp.id) AS last_dump_at
         FROM monitored_pages mp
         WHERE mp.status = ?
         HAVING
             (last_dump_at IS NULL
              AND NOW() >= DATE_ADD(CURDATE(), INTERVAL mp.start_hour HOUR))
             OR (last_dump_at IS NOT NULL
                 AND DATE_ADD(last_dump_at, INTERVAL mp.check_interval_minutes MINUTE) <= NOW())'
    );
    $stmt->execute(['active']);
    $pages = $stmt->fetchAll();
} else {
    echo 'Verwendung: php monitor.php [--page-id=X | --all] [--dry-run]' . PHP_EOL;
    exit(1);
}

if (empty($pages)) {
    echo 'Keine fälligen Monitore gefunden.' . PHP_EOL;
    exit(0);
}

$changedEntries = []; // Für Benachrichtigungen sammeln

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
            $changedEntries[] = ['page' => $page, 'dump' => $dump];
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

// E-Mail-Benachrichtigungen: eine Mail pro User, gebündelt
if (!$isDryRun && !empty($changedEntries)) {
    echo PHP_EOL . 'Sende Benachrichtigungen ...' . PHP_EOL;
    $notificationService = new NotificationService();
    $notificationService->sendChangedNotifications($changedEntries);
    echo 'Benachrichtigungen gesendet.' . PHP_EOL;
}

echo 'Fertig.' . PHP_EOL;
exit(0);
