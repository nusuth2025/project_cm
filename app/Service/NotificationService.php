<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\DB;
use App\Model\MonitoredPage;
use App\Model\MonitoringDump;

class NotificationService
{
    /**
     * @param array<array{page: MonitoredPage, dump: MonitoringDump}> $changes
     */
    public function sendChangedNotifications(array $changes): void
    {
        $byUser = [];
        foreach ($changes as $entry) {
            $uid = $entry['page']->userId;
            $byUser[$uid][] = $entry;
        }

        $db = DB::getInstance();
        foreach ($byUser as $userId => $items) {
            $stmt = $db->prepare('SELECT email, username FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                continue;
            }
            $this->sendEmail($user['email'], $user['username'], $items);
        }
    }

    /** @param array<array{page: MonitoredPage, dump: MonitoringDump}> $items */
    private function sendEmail(string $toEmail, string $username, array $items): bool
    {
        $count   = count($items);
        $subject = 'ContentMonitor: ' . $count . ' Änderung' . ($count !== 1 ? 'en' : '') . ' festgestellt';
        $body    = $this->buildHtmlBody($username, $items);

        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ContentMonitor <noreply@' . (defined('APP_MAIL_FROM_HOST') ? APP_MAIL_FROM_HOST : 'localhost') . '>',
            'X-Mailer: PHP/' . PHP_VERSION,
        ]);

        return mail($toEmail, $subject, $body, $headers);
    }

    /** @param array<array{page: MonitoredPage, dump: MonitoringDump}> $items */
    private function buildHtmlBody(string $username, array $items): string
    {
        $appUrl = defined('APP_URL') ? APP_URL : 'http://localhost';

        $rows = '';
        foreach ($items as ['page' => $page, 'dump' => $dump]) {
            $label     = htmlspecialchars($page->label ?? $page->url, ENT_QUOTES, 'UTF-8');
            $url       = htmlspecialchars($page->url, ENT_QUOTES, 'UTF-8');
            $foundAt   = $dump->foundAt->format('d.m.Y H:i');
            $detailUrl = $appUrl . '/monitor/' . $page->id;

            // Änderungsdetail aufbauen
            $detail = $this->buildChangeDetail($page, $dump);

            $rows .= "
            <tr>
                <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                    <a href='{$detailUrl}' style='color:#16a34a;text-decoration:none;font-weight:500;'>{$label}</a>
                    <div style='font-size:11px;color:#9ca3af;margin-top:3px;word-break:break-all;'>{$url}</div>
                </td>
                <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;white-space:nowrap;vertical-align:top;'>
                    {$foundAt}
                </td>
                <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;font-size:12px;vertical-align:top;max-width:280px;'>
                    {$detail}
                </td>
            </tr>";
        }

        $usernameEsc = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#111827;max-width:700px;margin:0 auto;padding:24px;">
  <div style="border-bottom:3px solid #16a34a;padding-bottom:12px;margin-bottom:20px;">
    <span style="font-size:20px;font-weight:700;color:#16a34a;">ContentMonitor</span>
  </div>
  <p style="margin:0 0 6px;">Hallo <strong>{$usernameEsc}</strong>,</p>
  <p style="margin:0 0 20px;color:#374151;">bei folgenden Seiten wurden Änderungen festgestellt:</p>
  <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
    <thead>
      <tr style="background:#f9fafb;">
        <th style="text-align:left;padding:10px 14px;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Monitor</th>
        <th style="text-align:left;padding:10px 14px;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Festgestellt</th>
        <th style="text-align:left;padding:10px 14px;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Änderung</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>
  <p style="margin-top:24px;font-size:11px;color:#9ca3af;">
    Sie erhalten diese E-Mail, weil Sie Monitore in ContentMonitor eingerichtet haben.
  </p>
</body>
</html>
HTML;
    }

    private function buildChangeDetail(MonitoredPage $page, MonitoringDump $dump): string
    {
        // Vordefinierte Erklärung aus dem Monitoring-Lauf (z. B. "Umfeld nicht gefunden")
        if ($dump->changeNote !== '') {
            $lines = array_map(
                fn($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'),
                explode("\n", $dump->changeNote)
            );

            // Erste Zeile = Überschrift, Rest = Details
            $title  = array_shift($lines);
            $detail = implode('<br>', $lines);

            $html = "<span style='color:#b45309;font-weight:600;'>{$title}</span>";
            if ($detail !== '') {
                $html .= "<div style='margin-top:4px;color:#374151;font-family:monospace;font-size:11px;"
                       . "background:#fefce8;border:1px solid #fef08a;border-radius:4px;"
                       . "padding:6px 8px;white-space:pre-wrap;word-break:break-all;'>{$detail}</div>";
            }
            return $html;
        }

        // Kein Kommentar (z. B. kein Umfeld gesetzt): generische Meldung
        return "<span style='color:#6b7280;'>Inhalt geändert</span>";
    }
}
