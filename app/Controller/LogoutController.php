<?php
declare(strict_types=1);

namespace App\Controller;

class LogoutController extends AbstractController
{
    public function handle(): void
    {
        $this->session->reset();
        session_destroy();
        $this->redirect('/login');
    }
}
