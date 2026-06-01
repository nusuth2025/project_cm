<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\MonitoringService;
use App\Service\SelectionSearchService;
use PHPUnit\Framework\TestCase;

final class MonitoringServiceTest extends TestCase
{
    private MonitoringService $service;

    protected function setUp(): void
    {
        $this->service = new MonitoringService(new SelectionSearchService());
    }

    // --- hasChanged ---

    public function testHasChangedReturnsFalseForIdenticalContent(): void
    {
        self::assertFalse($this->service->hasChanged('<p>same</p>', '<p>same</p>'));
    }

    public function testHasChangedReturnsTrueWhenContentDiffers(): void
    {
        self::assertTrue($this->service->hasChanged('<p>old</p>', '<p>new</p>'));
    }

    public function testHasChangedReturnsFalseForTwoEmptyStrings(): void
    {
        self::assertFalse($this->service->hasChanged('', ''));
    }

    public function testHasChangedReturnsTrueWhenOnlyWhitespaceDiffers(): void
    {
        // Strict comparison — even whitespace differences count
        self::assertTrue($this->service->hasChanged('<p>text</p>', '<p>text</p> '));
    }

    public function testHasChangedIsCaseSensitive(): void
    {
        self::assertTrue($this->service->hasChanged('<p>Hello</p>', '<p>hello</p>'));
    }
}
