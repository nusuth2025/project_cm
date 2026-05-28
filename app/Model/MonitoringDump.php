<?php
declare(strict_types=1);

namespace App\Model;

class MonitoringDump
{
    public int $id;
    public int $monitoredPageId;
    public string $htmlContent;
    public ?string $checkedContent;
    public \DateTimeImmutable $foundAt;
    public bool $changed;

    public static function fromRow(array $row): self
    {
        $dump = new self();
        $dump->id               = (int) $row['id'];
        $dump->monitoredPageId  = (int) $row['monitored_page_id'];
        $dump->htmlContent      = $row['html_content'];
        $dump->checkedContent   = $row['checked_content'];
        $dump->foundAt          = new \DateTimeImmutable($row['found_at']);
        $dump->changed          = (bool) $row['changed'];
        return $dump;
    }
}
