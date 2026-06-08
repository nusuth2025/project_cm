<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\DB;

class UserSettingsController extends AbstractController
{
    public function handle(): void
    {
        $this->requireLogin();

        $db     = DB::getInstance();
        $userId = $this->session->getUserId();

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $emailSuccess    = null;
        $emailError      = null;
        $passwordSuccess = null;
        $passwordError   = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // ── E-Mail ändern ─────────────────────────────────────────────────
            if (isset($_POST['change_email'])) {
                $newEmail  = trim($_POST['new_email']  ?? '');
                $pwConfirm = $_POST['password_confirm'] ?? '';

                if ($newEmail === '') {
                    $emailError = 'Bitte eine E-Mail-Adresse eingeben.';
                } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $emailError = 'Keine gültige E-Mail-Adresse.';
                } elseif (!password_verify($pwConfirm, $user['password_hash'])) {
                    $emailError = 'Passwort ist nicht korrekt.';
                } else {
                    $check = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                    $check->execute([$newEmail, $userId]);
                    if ($check->fetch()) {
                        $emailError = 'Diese E-Mail-Adresse wird bereits verwendet.';
                    } else {
                        $db->prepare('UPDATE users SET email = ? WHERE id = ?')
                           ->execute([$newEmail, $userId]);
                        $user['email'] = $newEmail;
                        $emailSuccess  = 'E-Mail-Adresse wurde aktualisiert.';
                    }
                }
            }

            // ── Passwort ändern ───────────────────────────────────────────────
            if (isset($_POST['change_password'])) {
                $currentPw = $_POST['current_password'] ?? '';
                $newPw     = $_POST['new_password']     ?? '';
                $confirmPw = $_POST['confirm_password'] ?? '';

                if (!password_verify($currentPw, $user['password_hash'])) {
                    $passwordError = 'Aktuelles Passwort ist nicht korrekt.';
                } elseif (strlen($newPw) < 8) {
                    $passwordError = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
                } elseif ($newPw !== $confirmPw) {
                    $passwordError = 'Die neuen Passwörter stimmen nicht überein.';
                } else {
                    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                       ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);
                    $passwordSuccess = 'Passwort wurde erfolgreich geändert.';
                }
            }
        }

        $this->render('user/settings', [
            'user'            => $user,
            'emailSuccess'    => $emailSuccess,
            'emailError'      => $emailError,
            'passwordSuccess' => $passwordSuccess,
            'passwordError'   => $passwordError,
        ]);
    }
}
