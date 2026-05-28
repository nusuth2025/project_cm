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
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;

    public static function fromRow(array $row): self
    {
        $page = new self();
        $page->id                 = (int) $row['id'];
        $page->userId             = (int) $row['user_id'];
        $page->url                = $row['url'];
        $page->selectionText      = $row['selection_text'];
        $page->innerSelectionText = $row['inner_selection_text'];
        $page->label              = $row['label'];
        $page->status             = $row['status'];
        $page->createdAt          = new \DateTimeImmutable($row['created_at']);
        $page->updatedAt          = new \DateTimeImmutable($row['updated_at']);
        return $page;
    }
}
