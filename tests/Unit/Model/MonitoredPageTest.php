<?php
declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\MonitoredPage;
use PHPUnit\Framework\TestCase;

final class MonitoredPageTest extends TestCase
{
    private array $row;

    protected function setUp(): void
    {
        $this->row = [
            'id'                    => '7',
            'user_id'               => '3',
            'url'                   => 'https://example.com',
            'selection_text'        => 'some selection',
            'inner_selection_text'  => 'inner',
            'label'                 => 'My Page',
            'status'                => 'active',
            'check_interval_minutes' => '1440',
            'start_hour'            => '8',
            'created_at'            => '2025-01-01 10:00:00',
            'updated_at'            => '2025-06-01 12:00:00',
        ];
    }

    public function testFromRowHydratesAllFields(): void
    {
        $page = MonitoredPage::fromRow($this->row);

        self::assertSame(7, $page->id);
        self::assertSame(3, $page->userId);
        self::assertSame('https://example.com', $page->url);
        self::assertSame('some selection', $page->selectionText);
        self::assertSame('inner', $page->innerSelectionText);
        self::assertSame('My Page', $page->label);
        self::assertSame('active', $page->status);
        self::assertSame(1440, $page->checkIntervalMinutes);
        self::assertSame(8, $page->startHour);
        self::assertInstanceOf(\DateTimeImmutable::class, $page->createdAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $page->updatedAt);
        self::assertSame('2025-01-01', $page->createdAt->format('Y-m-d'));
        self::assertSame('2025-06-01', $page->updatedAt->format('Y-m-d'));
    }

    public function testFromRowAcceptsNullableFields(): void
    {
        $this->row['selection_text']       = null;
        $this->row['inner_selection_text'] = null;
        $this->row['label']                = null;

        $page = MonitoredPage::fromRow($this->row);

        self::assertNull($page->selectionText);
        self::assertNull($page->innerSelectionText);
        self::assertNull($page->label);
    }

    public function testFromRowCastsIdsToInt(): void
    {
        $page = MonitoredPage::fromRow($this->row);

        self::assertIsInt($page->id);
        self::assertIsInt($page->userId);
    }

    public function testFromRowUsesDefaultIntervalWhenMissing(): void
    {
        unset($this->row['check_interval_minutes']);
        $page = MonitoredPage::fromRow($this->row);

        self::assertSame(1440, $page->checkIntervalMinutes);
    }

    public function testFromRowUsesDefaultStartHourWhenMissing(): void
    {
        unset($this->row['start_hour']);
        $page = MonitoredPage::fromRow($this->row);

        self::assertSame(8, $page->startHour);
    }
}
