<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;

class LoginController extends AbstractController
{
    public function handle(): void
    {
        if ($this->session->isLoggedIn()) {
            $this->redirect('/list');
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $stmt = DB::getInstance()->prepare(
                'SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1'
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $this->session->setUserId((int) $user['id']);
                $_SESSION['username'] = $user['username'];
                $this->redirect('/list');
            }

            $error = 'Benutzername oder Passwort falsch.';
        }

        $this->render('auth/login', ['error' => $error]);
    }
}
