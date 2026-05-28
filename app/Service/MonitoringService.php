<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\DB;
use App\Model\MonitoredPage;
use App\Model\MonitoringDump;

class MonitoringService
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0';

    public function __construct(
        private readonly SelectionSearchService $searchService
    ) {}

    /**
     * Lädt den HTML-Body einer URL per cURL.
     * Bug-Fix gegenüber ProcessSelection: CURLOPT_HEADER ist false,
     * damit keine HTTP-Header in den gespeicherten Inhalt gelangen.
     */
    public function fetchHtml(string $url): string
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false, // Bug-Fix: kein HTTP-Header im Body
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_ENCODING       => '',    // akzeptiert alle Encodings automatisch
        ]);

        $html = curl_exec($curl);
        curl_close($curl);

        return is_string($html) ? $html : '';
    }

    /**
     * Vollständiger Monitoring-Zyklus: HTML abrufen → Auswahl suchen → vergleichen → speichern.
     */
    public function runCheck(MonitoredPage $page): MonitoringDump
    {
        $htmlContent    = $this->fetchHtml($page->url);
        $checkedContent = null;
        $changed        = false;

        if ($page->selectionText !== null) {
            $positions = $this->searchService->findPositions($htmlContent, $page->selectionText);
            if (!empty($positions)) {
                $checkedContent = $this->searchService->buildMarkedContent($htmlContent, $positions);
            }
        }

        $previous = $this->getPreviousDump($page->id);
        if ($previous !== null) {
            $changed = $this->hasChanged($previous->htmlContent, $htmlContent);
        }

        $db   = DB::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO monitoring_dumps (monitored_page_id, html_content, checked_content, changed)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$page->id, $htmlContent, $checkedContent, $changed ? 1 : 0]);

        $dump                  = new MonitoringDump();
        $dump->id              = (int) $db->lastInsertId();
        $dump->monitoredPageId = $page->id;
        $dump->htmlContent     = $htmlContent;
        $dump->checkedContent  = $checkedContent;
        $dump->foundAt         = new \DateTimeImmutable();
        $dump->changed         = $changed;

        return $dump;
    }

    public function getPreviousDump(int $pageId): ?MonitoringDump
    {
        $stmt = DB::getInstance()->prepare(
            'SELECT * FROM monitoring_dumps WHERE monitored_page_id = ?
             ORDER BY found_at DESC LIMIT 1'
        );
        $stmt->execute([$pageId]);
        $row = $stmt->fetch();

        return $row ? MonitoringDump::fromRow($row) : null;
    }

    public function hasChanged(string $previous, string $current): bool
    {
        return $previous !== $current;
    }
}
