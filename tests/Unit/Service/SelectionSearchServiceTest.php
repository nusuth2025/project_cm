<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\SelectionSearchService;
use PHPUnit\Framework\TestCase;

final class SelectionSearchServiceTest extends TestCase
{
    private SelectionSearchService $service;

    protected function setUp(): void
    {
        $this->service = new SelectionSearchService();
    }

    // --- tokenize ---

    public function testTokenizeEmptyStringReturnsEmptyArray(): void
    {
        self::assertSame([], $this->service->tokenize(''));
    }

    public function testTokenizeWhitespaceOnlyReturnsEmptyArray(): void
    {
        self::assertSame([], $this->service->tokenize("   \t\n  "));
    }

    public function testTokenizeSingleWord(): void
    {
        self::assertSame(['Hallo'], $this->service->tokenize('Hallo'));
    }

    public function testTokenizeMultipleWordsWithSpaces(): void
    {
        self::assertSame(['foo', 'bar', 'baz'], $this->service->tokenize('foo bar baz'));
    }

    public function testTokenizeCollapsesMultipleSpaces(): void
    {
        self::assertSame(['foo', 'bar'], $this->service->tokenize('foo   bar'));
    }

    public function testTokenizeNormalizesNewlineAndTab(): void
    {
        self::assertSame(['foo', 'bar'], $this->service->tokenize("foo\nbar"));
        self::assertSame(['foo', 'bar'], $this->service->tokenize("foo\tbar"));
        self::assertSame(['foo', 'bar'], $this->service->tokenize("foo\rbar"));
    }

    public function testTokenizeTrimsLeadingAndTrailingWhitespace(): void
    {
        self::assertSame(['foo'], $this->service->tokenize('  foo  '));
    }

    // --- findPositions ---

    public function testFindPositionsReturnsEmptyArrayForEmptySelection(): void
    {
        self::assertSame([], $this->service->findPositions('some html', ''));
    }

    public function testFindPositionsSingleWordFound(): void
    {
        $html = 'Hello World Test';
        $pos  = $this->service->findPositions($html, 'World');

        self::assertSame([6, 11], $pos);
    }

    public function testFindPositionsMultipleWordsFound(): void
    {
        $html = 'The quick brown fox';
        $pos  = $this->service->findPositions($html, 'quick brown');

        // "quick" starts at 4, ends at 9; "brown" starts at 10, ends at 15
        self::assertSame([4, 9, 10, 15], $pos);
    }

    public function testFindPositionsWordNotFoundReturnsEmpty(): void
    {
        self::assertSame([], $this->service->findPositions('Hello World', 'notexistent'));
    }

    public function testFindPositionsSecondWordMissingReturnsEmpty(): void
    {
        // First word found, second missing → whole result is empty
        self::assertSame([], $this->service->findPositions('Hello World', 'Hello missing'));
    }

    public function testFindPositionsRespectsOrder(): void
    {
        // "bar" appears before "foo" in the string — greedy forward can't find them in selection order
        $html = 'bar foo';
        self::assertSame([], $this->service->findPositions($html, 'foo bar'));
    }

    // --- findMissingWord ---

    public function testFindMissingWordReturnsNullWhenAllFound(): void
    {
        self::assertNull($this->service->findMissingWord('Hello World', 'Hello World'));
    }

    public function testFindMissingWordReturnsFirstMissingWord(): void
    {
        $result = $this->service->findMissingWord('Hello World', 'Hello missing World');

        self::assertNotNull($result);
        self::assertSame(1, $result['index']);
        self::assertSame('missing', $result['word']);
    }

    public function testFindMissingWordFirstWordMissing(): void
    {
        $result = $this->service->findMissingWord('Hello World', 'nope World');

        self::assertNotNull($result);
        self::assertSame(0, $result['index']);
        self::assertSame('nope', $result['word']);
    }

    // --- buildMarkedContent ---

    public function testBuildMarkedContentWithEmptyPositionsReturnsOriginal(): void
    {
        $html = '<p>unchanged</p>';
        self::assertSame($html, $this->service->buildMarkedContent($html, []));
    }

    public function testBuildMarkedContentMarksSingleWord(): void
    {
        $html   = 'Hello World Test';
        $pos    = $this->service->findPositions($html, 'World');
        $marked = $this->service->buildMarkedContent($html, $pos);

        self::assertStringContainsString('|#|World|##|', $marked);
    }

    public function testBuildMarkedContentPreservesTextBeforeFirstWord(): void
    {
        $html   = 'Hello World';
        $pos    = $this->service->findPositions($html, 'World');
        $marked = $this->service->buildMarkedContent($html, $pos);

        self::assertStringStartsWith('Hello ', $marked);
    }

    public function testBuildMarkedContentMarksMultipleWords(): void
    {
        $html   = 'The quick brown fox';
        $pos    = $this->service->findPositions($html, 'quick brown');
        $marked = $this->service->buildMarkedContent($html, $pos);

        self::assertStringContainsString('|#|quick|##|', $marked);
        self::assertStringContainsString('|#|brown|##|', $marked);
    }

    public function testBuildMarkedContentRoundTrip(): void
    {
        // findPositions + buildMarkedContent must agree on where words are
        $html      = '<div class="content">LibreOffice Writer ist großartig</div>';
        $selection = 'Writer ist';
        $pos       = $this->service->findPositions($html, $selection);

        self::assertNotEmpty($pos);

        $marked = $this->service->buildMarkedContent($html, $pos);

        self::assertStringContainsString('|#|Writer|##|', $marked);
        self::assertStringContainsString('|#|ist|##|', $marked);
    }
}
