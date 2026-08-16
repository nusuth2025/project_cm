# Deployment & Sicherheit — Öffentlicher Server

## Kurzfazit

Der Aufwand ist überschaubar. Die kritischsten Punkte (HTTPS, Verzeichnisschutz,
Session-Härtung) sind in 1–2 Stunden erledigt. CSRF-Schutz erfordert etwas mehr
Aufwand im Code, ist aber für eine privat genutzte Anwendung mit Login-Pflicht
ein kalkulierbares Risiko.

---

## 1. HTTPS — notwendig, einfach

**Was fehlt:** Aktuell kein TLS, kein HTTPS-Redirect, `APP_URL` zeigt auf `http://localhost`.

**Lösung:** Let's Encrypt + Certbot (kostenlos, automatisch erneuerbar)

```bash
# Beispiel Apache
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d deine-domain.de
```

Certbot konfiguriert Apache automatisch und richtet den HTTP→HTTPS-Redirect ein.

**Aufwand:** ~30 Minuten, gut dokumentiert, keine Code-Änderungen nötig.

---

## 2. `.htaccess` — fehlt komplett

Aktuell gibt es keine `.htaccess`. Ohne sie sind alle Verzeichnisse direkt erreichbar
(`/app/`, `/db/`, `/vendor/`). Zwingend notwendig:

```apache
# Alle Anfragen durch index.php leiten (Front-Controller)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# HTTP → HTTPS erzwingen
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Sensible Verzeichnisse sperren
<DirectoryMatch "^.*(app|db|vendor|tests|ignorethis)">
    Require all denied
</DirectoryMatch>

# Konfigurationsdateien sperren
<FilesMatch "\.(sql|json|lock|xml|md|log)$">
    Require all denied
</FilesMatch>

# Verzeichnis-Listing deaktivieren
Options -Indexes
```

**Aufwand:** ~20 Minuten.

---

## 3. Session-Härtung — 3 Zeilen Code oder 3 Zeilen `.htaccess`

Aktuell wird `session_start()` ohne Sicherheitsoptionen aufgerufen. Auf einem
öffentlichen Server müssen Session-Cookies gesichert werden. Drei Varianten stehen
zur Wahl:

**Variante A — `app/config.php`** (empfohlen für dieses Projekt)

`session_set_cookie_params()` muss vor `session_start()` aufgerufen werden.
Da `config.php` in `index.php` als erstes per `require_once` eingebunden wird
und `session_start()` erst danach folgt, ist die Reihenfolge korrekt:

```php
// app/config.php — am Ende der Datei, nach den defines
session_set_cookie_params([
    'lifetime' => 0,
    'secure'   => true,        // nur über HTTPS
    'httponly' => true,        // kein JavaScript-Zugriff
    'samesite' => 'Strict',    // kein Cross-Site-Zugriff
]);
```

**Variante B — `index.php`**

Alternativ direkt im Einstiegspunkt, unmittelbar vor `session_start()`:

```php
// index.php — vor session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
```

**Variante C — `.htaccess`** (kein Code-Eingriff nötig)

Die Parameter lassen sich auch auf Apache-Ebene setzen. Vorteil: keine
Änderung am PHP-Code, gilt für den gesamten VHost:

```apache
php_flag  session.cookie_secure   On
php_flag  session.cookie_httponly On
php_value session.cookie_samesite Strict
```

Alternativ in `php.ini` für eine serverweite Einstellung:

```ini
session.cookie_secure   = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.cookie_lifetime = 0
```

**Aufwand:** 5 Minuten.

---

## 4. `config.php` — Produktionswerte setzen

```php
define('APP_ENV', 'prod');                        // war: 'dev'
define('APP_URL', 'https://deine-domain.de');     // war: 'http://localhost'
```

DB-Zugangsdaten sollten nicht im Code stehen, sondern aus Umgebungsvariablen kommen:

```php
define('DB_PASS', $_ENV['DB_PASS'] ?? 'changeme');
```

Diese werden z. B. in `/etc/environment` oder einer `.env`-Datei außerhalb des
Web-Roots gesetzt (nie im Webroot ablegen!).

**Aufwand:** 15 Minuten.

---

## 5. PHP-Produktionskonfiguration (`php.ini`)

```ini
display_errors = Off     ; keine Fehler im Browser anzeigen
log_errors     = On      ; stattdessen ins Log schreiben
expose_php     = Off     ; PHP-Version nicht im HTTP-Header verraten
```

**Aufwand:** 5 Minuten.

---

## 6. CSRF-Schutz — Code-Aufwand, aber kalkulierbar

**Was fehlt:** Alle POST-Formulare haben keinen CSRF-Token. Ein Angreifer könnte
einen eingeloggten Nutzer durch eine präparierte Seite dazu bringen, ungewollte
Aktionen auszuführen (z. B. Monitore löschen oder das Passwort ändern).

**Wie ein Angriff aussieht:**

1. Nutzer ist auf `contentmonitor.example.de` eingeloggt — der Session-Cookie liegt im Browser.
2. Nutzer besucht eine beliebige andere Seite, die folgendes enthält:
   ```html
   <img src="https://contentmonitor.example.de/monitor/delete" style="display:none">
   <!-- oder ein auto-submit-Formular via JavaScript -->
   ```
3. Der Browser schickt die Anfrage automatisch mit dem Session-Cookie — der Server
   sieht eine scheinbar legitime Anfrage vom eingeloggten Nutzer.

**Risikobewertung:** Niedrig bis mittel — die Anwendung erfordert Login, ist also
kein öffentliches Formular. Bei `SameSite=Strict` (siehe Abschnitt 3) sind
einfache GET-basierte Angriffe bereits blockiert. POST-Formulare bleiben aber
angreifbar, wenn der Nutzer direkt auf einen Link klickt, der zu einer
Angreifer-Seite führt.

---

### Umsetzung in 3 Schritten

**Schritt 1 — Token erzeugen und prüfen im `AbstractController`**

```php
// app/Controller/AbstractController.php

protected function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

protected function verifyCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Ungültige Anfrage (CSRF).');
    }
}
```

`hash_equals()` verhindert Timing-Angriffe (konstante Vergleichszeit unabhängig
davon, wie viele Zeichen übereinstimmen).

---

**Schritt 2 — Token in jedes Formular als Hidden-Field einfügen**

In allen View-Templates (`monitor/edit.php`, `monitor/add.php`, `monitor/list.php`,
`auth/login.php`, `user/settings.php` usw.) innerhalb des `<form>`-Tags:

```php
<input type="hidden" name="csrf_token"
       value="<?= htmlspecialchars($this->generateCsrfToken()) ?>">
```

Da Views über `AbstractController::render()` aufgerufen werden und `$this`
dort nicht direkt verfügbar ist, übergibt man den Token als Template-Variable:

```php
// Im Controller, vor dem render()-Aufruf:
$this->render('monitor/edit', [
    'csrf_token' => $this->generateCsrfToken(),
    // ... weitere Daten
]);
```

```php
<!-- Im Template: -->
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
```

---

**Schritt 3 — Token bei jeder POST-Verarbeitung prüfen**

In jedem Controller, der POST-Daten verarbeitet (aktuell: `MonitorDeleteController`,
`MonitorEditController`, `UserSettingsController` und weitere), ganz am Anfang
des POST-Zweigs:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $this->verifyCsrfToken();   // ← neu, vor jeder weiteren Verarbeitung
    // ... bisheriger Code
}
```

---

**Aufwand:** 2–3 Stunden (alle Formulare und POST-Controller müssen angepasst werden).

---

## 7. Login-Rate-Limiting — optional, aber empfohlen

Aktuell gibt es keinen Schutz vor Brute-Force-Angriffen auf das Login-Formular.
Einfache Lösung: nach N Fehlversuchen pro IP für X Minuten sperren (z. B. über
`$_SESSION` oder eine DB-Tabelle).

**Aufwand:** 1–2 Stunden.

---

## 8. E-Mail-Versand

Die Anwendung nutzt PHPs `mail()`. Auf den meisten Cloud-/Root-Servern ist `mail()`
entweder nicht konfiguriert oder landet direkt im Spam. Empfehlung: PHPMailer oder
Symfony Mailer mit einem echten SMTP-Anbieter (z. B. Mailgun, Postmark, Gmail SMTP).

**Aufwand:** 1–3 Stunden je nach SMTP-Anbieter.

---

## Prioritätsliste

| Priorität | Maßnahme | Aufwand | Ohne diese Maßnahme |
|---|---|---|---|
| 🔴 Kritisch | HTTPS (Let's Encrypt) | 30 min | Passwörter im Klartext übertragen |
| 🔴 Kritisch | `.htaccess` (Verzeichnisschutz) | 20 min | DB-Schema, Quellcode öffentlich lesbar |
| 🔴 Kritisch | Session-Cookie `secure/httponly` | 5 min | Session-Hijacking möglich |
| 🟠 Wichtig | `config.php` Produktionswerte | 15 min | Debug-Infos sichtbar, falsche URLs |
| 🟠 Wichtig | `php.ini` Produktionsconfig | 5 min | PHP-Fehler im Browser sichtbar |
| 🟡 Empfohlen | CSRF-Schutz | 2–3 h | Cross-Site-Angriffe möglich |
| 🟡 Empfohlen | Login-Rate-Limiting | 1–2 h | Brute-Force möglich |
| 🟢 Optional | SMTP statt `mail()` | 1–3 h | E-Mails landen im Spam |

**Gesamtaufwand für die kritischen Punkte:** ca. 1–2 Stunden.
**Gesamtaufwand inkl. CSRF und Rate-Limiting:** ca. 1 Arbeitstag.

---

## Begründung im Kontext der Abschlussarbeit

Die oben beschriebenen Maßnahmen sind bewusst nicht Teil des realisierten Projekts.
Der Projektantrag definiert die Anwendung als **lokal betriebene Software**. Damit
entfällt die Notwendigkeit für HTTPS, CSRF-Schutz und Rate-Limiting im Rahmen
des definierten Projektumfangs.

**Formulierungsvorschlag für die Dokumentation (Abschnitt „Abgrenzung"):**

> Die Anwendung ist gemäß Projektantrag als lokal betriebene Software konzipiert.
> Maßnahmen für den Betrieb auf einem öffentlich erreichbaren Server (HTTPS/TLS,
> CSRF-Schutz, Rate-Limiting) sind daher bewusst nicht Bestandteil dieser Arbeit
> und würden den definierten Projektumfang überschreiten.

**Formulierungsvorschlag für den Abschnitt „Sicherheit":**

> Für den lokalen Betrieb im Intranet oder auf einem Entwicklungsrechner sind die
> implementierten Sicherheitsmaßnahmen — darunter Passwort-Hashing mit `password_hash()`,
> Session-Regenerierung nach Login, konsequentes Output-Escaping gegen XSS sowie
> eigentumsbasierte Datenbankabfragen — ausreichend. Eine Erweiterung um HTTPS,
> CSRF-Schutz und Login-Rate-Limiting wäre für einen Produktivbetrieb im Internet
> notwendig und ist als nächster Ausbauschritt in diesem Dokument beschrieben.
