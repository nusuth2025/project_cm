<?php
declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\MonitoringDump;
use PHPUnit\Framework\TestCase;

final class MonitoringDumpTest extends TestCase
{
    private array $row;

    protected function setUp(): void
    {
        $this->row = [
            'id'                => '12',
            'monitored_page_id' => '5',
            'html_content'      => '<html><body>Test</body></html>',
            'checked_content'   => '<html>|#|Test|##|</html>',
            'found_at'          => '2025-06-01 09:30:00',
            'changed'           => '1',
        ];
    }

    public function testFromRowHydratesAllFields(): void
    {
        $dump = MonitoringDump::fromRow($this->row);

        self::assertSame(12, $dump->id);
        self::assertSame(5, $dump->monitoredPageId);
        self::assertSame('<html><body>Test</body></html>', $dump->htmlContent);
        self::assertSame('<html>|#|Test|##|</html>', $dump->checkedContent);
        self::assertTrue($dump->changed);
        self::assertInstanceOf(\DateTimeImmutable::class, $dump->foundAt);
        self::assertSame('2025-06-01', $dump->foundAt->format('Y-m-d'));
    }

    public function testFromRowCheckedContentCanBeNull(): void
    {
        $this->row['checked_content'] = null;
        $dump = MonitoringDump::fromRow($this->row);

        self::assertNull($dump->checkedContent);
    }

    public function testFromRowChangedFlagFalseWhenZero(): void
    {
        $this->row['changed'] = '0';
        $dump = MonitoringDump::fromRow($this->row);

        self::assertFalse($dump->changed);
    }

    public function testFromRowCastsIdsToInt(): void
    {
        $dump = MonitoringDump::fromRow($this->row);

        self::assertIsInt($dump->id);
        self::assertIsInt($dump->monitoredPageId);
    }
}
