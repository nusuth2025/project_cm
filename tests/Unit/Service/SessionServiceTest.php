<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\SessionService;
use PHPUnit\Framework\TestCase;

final class SessionServiceTest extends TestCase
{
    private SessionService $service;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->service = new SessionService();
    }

    // --- ensureSessionId / getSessionId ---

    public function testEnsureSessionIdCreatesId(): void
    {
        $this->service->ensureSessionId();
        self::assertStringStartsWith('monitor', $this->service->getSessionId());
    }

    public function testEnsureSessionIdDoesNotOverwriteExistingId(): void
    {
        $_SESSION['S_ID'] = 'monitor_existing';
        $this->service->ensureSessionId();
        self::assertSame('monitor_existing', $this->service->getSessionId());
    }

    public function testGetSessionIdReturnsNullWhenNotSet(): void
    {
        self::assertNull($this->service->getSessionId());
    }

    // --- URL ---

    public function testSetAndGetUrl(): void
    {
        $this->service->setUrl('https://example.com');
        self::assertSame('https://example.com', $this->service->getUrl());
    }

    public function testGetUrlReturnsNullWhenNotSet(): void
    {
        self::assertNull($this->service->getUrl());
    }

    // --- Selection ---

    public function testSetAndGetSelection(): void
    {
        $this->service->setSelection('some selected text');
        self::assertSame('some selected text', $this->service->getSelection());
    }

    public function testGetSelectionReturnsNullWhenNotSet(): void
    {
        self::assertNull($this->service->getSelection());
    }

    // --- InnerSelection ---

    public function testSetAndGetInnerSelection(): void
    {
        $this->service->setInnerSelection('inner text');
        self::assertSame('inner text', $this->service->getInnerSelection());
    }

    public function testGetInnerSelectionReturnsNullWhenNotSet(): void
    {
        self::assertNull($this->service->getInnerSelection());
    }

    // --- UserId / isLoggedIn ---

    public function testSetAndGetUserId(): void
    {
        $this->service->setUserId(42);
        self::assertSame(42, $this->service->getUserId());
    }

    public function testGetUserIdReturnsNullWhenNotSet(): void
    {
        self::assertNull($this->service->getUserId());
    }

    public function testIsLoggedInReturnsTrueAfterSetUserId(): void
    {
        $this->service->setUserId(1);
        self::assertTrue($this->service->isLoggedIn());
    }

    public function testIsLoggedInReturnsFalseWhenNoUserId(): void
    {
        self::assertFalse($this->service->isLoggedIn());
    }

    // --- clearMonitorFlow ---

    public function testClearMonitorFlowRemovesMonitorKeys(): void
    {
        $this->service->ensureSessionId();
        $this->service->setUrl('https://example.com');
        $this->service->setSelection('text');
        $this->service->setInnerSelection('inner');
        $this->service->setUserId(5);

        $this->service->clearMonitorFlow();

        self::assertNull($this->service->getSessionId());
        self::assertNull($this->service->getUrl());
        self::assertNull($this->service->getSelection());
        self::assertNull($this->service->getInnerSelection());
        // user_id must survive clearMonitorFlow
        self::assertSame(5, $this->service->getUserId());
    }

    // --- reset ---

    public function testResetClearsEntireSession(): void
    {
        $this->service->setUserId(7);
        $this->service->setUrl('https://example.com');

        $this->service->reset();

        self::assertEmpty($_SESSION);
    }
}
