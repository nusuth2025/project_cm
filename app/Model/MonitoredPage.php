<?php
declare(strict_types=1);

namespace App\Model;

class MonitoredPage
{
    public int $id;
    public int $userId;
    public string $url;
    public ?string $selectionText;
    public ?string $innerSelectionText;
    public ?string $label;
    public string $status; // 'active' | 'paused' | 'error'
    public int $checkIntervalMinutes; // Speicherformat; 1440 = 1 Tag
    public int $startHour;            // 0–23: Stunde des ersten Prüflaufs
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;

    public static function fromRow(array $row): self
    {
        $page = new self();
        $page->id                   = (int) $row['id'];
        $page->userId               = (int) $row['user_id'];
        $page->url                  = $row['url'];
        $page->selectionText        = $row['selection_text'];
        $page->innerSelectionText   = $row['inner_selection_text'];
        $page->label                = $row['label'];
        $page->status               = $row['status'];
        $page->checkIntervalMinutes = (int) ($row['check_interval_minutes'] ?? 1440);
        $page->startHour            = (int) ($row['start_hour']            ?? 8);
        $page->createdAt            = new \DateTimeImmutable($row['created_at']);
        $page->updatedAt            = new \DateTimeImmutable($row['updated_at']);
        return $page;
    }

    /** Tage-Anteil des Intervalls */
    public function intervalDays(): int
    {
        return intdiv($this->checkIntervalMinutes, 1440);
    }

    /** Stunden-Anteil (nach Abzug der vollen Tage) */
    public function intervalHours(): int
    {
        return intdiv($this->checkIntervalMinutes % 1440, 60);
    }

    /** Minuten-Anteil (nach Abzug der vollen Stunden) */
    public function intervalRemainingMinutes(): int
    {
        return $this->checkIntervalMinutes % 60;
    }

    /** Menschenlesbare Kurzform, z. B. "1T 3h 15m" */
    public function intervalLabel(): string
    {
        $parts = [];
        if ($this->intervalDays() > 0)              $parts[] = $this->intervalDays() . 'T';
        if ($this->intervalHours() > 0)             $parts[] = $this->intervalHours() . 'h';
        if ($this->intervalRemainingMinutes() > 0)  $parts[] = $this->intervalRemainingMinutes() . 'min';
        return $parts !== [] ? implode(' ', $parts) : '1min';
    }
}
