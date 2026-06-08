<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SessionService;

abstract class AbstractController
{
    public function __construct(
        protected readonly SessionService $session
    ) {}

    abstract public function handle(): void;

    /**
     * Rendert ein PHP-View-Template.
     * $view ist relativ zu app/View/, z. B. 'home' oder 'monitor/list'.
     * Alle $data-Schlüssel werden als Variablen im Template verfügbar gemacht.
     */
    protected function render(string $view, array $data = []): void
    {
        $viewPath = BASE_PATH . '/app/View/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: $viewPath");
        }
        extract($data, EXTR_SKIP);
        require $viewPath;
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    protected function requireLogin(): void
    {
        if (!$this->session->isLoggedIn()) {
            $this->redirect('/login');
        }
    }
}
