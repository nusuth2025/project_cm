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
    // Fällig-Logik basiert auf last_checked_at (Zeit der letzten tatsächlichen Prüfung).
    // last_dump_at aus monitoring_dumps wäre falsch, seit Dumps nur noch bei Änderungen
    // gespeichert werden — dann würde das Intervall nie zurückgesetzt.
    //
    // Erstmalige Prüfung (last_checked_at IS NULL):
    //   Fällig, sobald die konfigurierte Startzeit heute erreicht ist.
    //   Liegt sie bereits in der Vergangenheit, ist der Monitor sofort fällig.
    //
    // Folgeprüfung (last_checked_at IS NOT NULL):
    //   Fällig, wenn letzte Prüfung + check_interval_minutes <= NOW().
    //
    //   Sekunden werden auf beiden Seiten auf 0 gesetzt (DATE_FORMAT auf HH:MM:00),
    //   damit ein Monitor der um XX:45:18 geprüft wurde nicht erst beim
    //   übernächsten Cron-Lauf (XX+30 min statt XX+15 min) als fällig gilt.
    $stmt = $db->prepare(
        'SELECT * FROM monitored_pages
         WHERE status = ?
           AND (
               (last_checked_at IS NULL
                AND NOW() >= DATE_ADD(CURDATE(), INTERVAL start_hour HOUR))
               OR (last_checked_at IS NOT NULL
                   AND DATE_ADD(
                       DATE_FORMAT(last_checked_at, \'%Y-%m-%d %H:%i:00\'),
                       INTERVAL check_interval_minutes MINUTE
                   ) <= DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:00\'))
           )'
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

        // Prüfzeitpunkt und Zähler immer aktualisieren — unabhängig davon ob ein Dump gespeichert wurde.
        $db->prepare('UPDATE monitored_pages SET last_checked_at = NOW(), check_count = check_count + 1 WHERE id = ?')
           ->execute([$page->id]);

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
