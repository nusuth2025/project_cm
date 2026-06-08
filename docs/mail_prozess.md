# Mail-Prozess: Wie Benachrichtigungen verschickt werden

## Übersicht

Benachrichtigungs-Mails werden **nicht** durch eine Benutzeraktion ausgelöst, sondern automatisch am Ende eines Cron-Laufs, wenn Änderungen gefunden wurden.

---

## Ablauf Schritt für Schritt

```
Cron → app/Cli/monitor.php → MonitoringService::runCheck() → Änderung?
                                                                  └── ja → $changedEntries[] sammeln
                                                                  └── nein → weiter

Am Ende des Laufs:
NotificationService::sendChangedNotifications($changedEntries)
  └── gruppiert nach User-ID
  └── pro User: eine Mail mit allen geänderten Seiten gebündelt
  └── PHP mail() → /usr/sbin/sendmail → Postfix (lokal)
```

### 1. CLI-Script (`app/Cli/monitor.php`)

Das Script wird per Cron aufgerufen (`--all`). Es sammelt alle Monitore, die laut `last_checked_at + check_interval_minutes` fällig sind, und führt für jeden `MonitoringService::runCheck()` aus.

Alle Einträge mit `$dump->changed === true` werden in `$changedEntries` gesammelt.

Am Ende — wenn kein `--dry-run` und mindestens eine Änderung — wird `NotificationService::sendChangedNotifications()` aufgerufen.

### 2. `NotificationService` (`app/Service/NotificationService.php`)

- Gruppiert die geänderten Einträge nach `userId`
- Pro User: lädt `email` und `username` aus der `users`-Tabelle
- Ruft `sendEmail()` auf — **eine Mail pro User, alle Änderungen gebündelt**

### 3. Mail-Inhalt

- **Betreff:** `ContentMonitor: N Änderung(en) festgestellt`
- **Format:** HTML-Mail mit Tabelle (Monitor-Name, Zeitpunkt, Änderungsdetail)
- **Absender:** `noreply@<APP_MAIL_FROM_HOST>` (Wert aus `app/config.php`, aktuell `localhost`)
- **Links** im Body: basieren auf `APP_URL` (aktuell `http://localhost`)

### 4. Versand

PHP `mail()` → `/usr/sbin/sendmail -t -i` → **Postfix** (läuft lokal, aktiv seit Systemstart)

Kein SMTP-Relay konfiguriert — Postfix stellt lokal zu.

---

## Wo landen die Mails?

Da kein externer Empfänger-Mailserver erreichbar ist (Entwicklungsumgebung, `APP_URL = localhost`), landen alle Mails im **lokalen Postfach des System-Users**:

```
/var/mail/susev
```

Das ist eine standard Unix-Mbox-Datei. Lesen mit:

```bash
# kompaktes Lesen mit mail-Befehl
mail -f /var/mail/susev

# oder direkt (HTML-Rohdaten):
cat /var/mail/susev | less

# nur die letzten Mails anzeigen (letzte 100 Zeilen):
tail -100 /var/mail/susev
```

> Die Datei ist aktuell ~930 KB groß und enthält mehrere Cron-Läufe.

---

## Konfiguration

| Konstante | Datei | Aktueller Wert | Bedeutung |
|---|---|---|---|
| `APP_URL` | `app/config.php` | `http://localhost` | Basis-URL für Links im Mail-Body |
| `APP_MAIL_FROM_HOST` | `app/config.php` | `localhost` | Absender-Domain (`noreply@localhost`) |

Für eine Produktivumgebung beide Werte auf die echte Domain setzen. Dann liefert Postfix an echte Empfänger-Adressen aus (die E-Mail-Adresse kommt aus dem `users.email`-Feld in der Datenbank).

---

## Dry-Run (kein Mailversand)

```bash
php app/Cli/monitor.php --all --dry-run
```

Im Dry-Run werden weder DB-Änderungen gespeichert noch Mails versandt.
