<?php
declare(strict_types=1);

namespace App\Service;

class SessionService
{
    public function ensureSessionId(): void
    {
        if (!isset($_SESSION['S_ID'])) {
            $_SESSION['S_ID'] = 'monitor' . time();
        }
    }

    public function getSessionId(): ?string
    {
        return $_SESSION['S_ID'] ?? null;
    }

    public function setUrl(string $url): void
    {
        $_SESSION['S_URL'] = $url;
    }

    public function getUrl(): ?string
    {
        return $_SESSION['S_URL'] ?? null;
    }

    public function setSelection(string $selection): void
    {
        $_SESSION['S_SELECTION'] = $selection;
    }

    public function getSelection(): ?string
    {
        return $_SESSION['S_SELECTION'] ?? null;
    }

    public function setInnerSelection(string $innerSelection): void
    {
        $_SESSION['S_INNERSELECTION'] = $innerSelection;
    }

    public function getInnerSelection(): ?string
    {
        return $_SESSION['S_INNERSELECTION'] ?? null;
    }

    public function setUserId(int $id): void
    {
        $_SESSION['user_id'] = $id;
    }

    public function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Löscht nur die Monitor-Flow-Keys (S_ID, S_URL, S_SELECTION, S_INNERSELECTION).
     * Login-State (user_id, username) bleibt erhalten.
     */
    public function clearMonitorFlow(): void
    {
        unset(
            $_SESSION['S_ID'],
            $_SESSION['S_URL'],
            $_SESSION['S_SELECTION'],
            $_SESSION['S_INNERSELECTION']
        );
    }

    /**
     * Löscht die gesamte Session (für Logout).
     */
    public function reset(): void
    {
        session_unset();
    }
}
