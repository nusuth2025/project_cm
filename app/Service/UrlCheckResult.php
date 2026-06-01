<?php
declare(strict_types=1);

namespace App\Service;

final class UrlCheckResult
{
    public function __construct(
        public readonly bool   $isUsable,
        public readonly int    $statusCode,
        public readonly string $effectiveUrl,
        public readonly string $message,
        public readonly string $severity, // 'ok' | 'info' | 'warning' | 'error'
    ) {}

    public function wasRedirected(string $submittedUrl): bool
    {
        return rtrim($this->effectiveUrl, '/') !== rtrim($submittedUrl, '/');
    }
}
