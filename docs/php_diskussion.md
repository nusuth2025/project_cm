# Sprachdiskussion: Warum PHP für dieses Projekt?

Analyse auf Basis des aktuellen Projekts:
PHP 8.4 · Kein Framework · Eigenes MVC · PDO/MariaDB · Cron-CLI · PHPUnit

---

## Was das Projekt technisch tut

- **Web-Frontend**: Login, Monitor anlegen/bearbeiten/löschen, Verlauf anzeigen
- **Cron-Script**: URL abrufen (cURL), Inhalt vergleichen, Dump speichern, Mail senden
- **Kein API-Tier**, kein Message-Broker, keine Echtzeit-Anforderungen
- **Deployment**: klassischer Linux-Webserver mit Apache/Nginx + PHP-FPM

---

## Pro: PHP behalten

**PHP ist genau für diesen Stack gebaut**
Webserver + Datenbank + HTML-Templates: PHP entstand für genau diesen Anwendungsfall.
Kein zusätzlicher Application-Server, kein Prozess-Manager, kein separater
Template-Renderer — der Webserver lädt PHP direkt.

**Zero-Dependency-Betrieb**
`composer.json` hat eine einzige Produktionsabhängigkeit: PHP selbst.
Kein Framework, keine externe Bibliothek im Produktivcode. Das ist kein Zufall,
sondern eine Stärke: nichts kann veralten, nichts muss aktualisiert werden,
kein Supply-Chain-Risiko.

**cURL, PDO, mail() — alles eingebaut**
Der Monitoring-Core braucht drei Dinge: HTTP-Requests, Datenbankzugriff und
E-Mail. Alle drei sind PHP-Bordmittel. In Python, Node oder Go müsste man dafür
externe Pakete einbinden und verwalten.

**CLI und Web aus demselben Code**
`app/Cli/monitor.php` teilt sich Services, Models und Autoloading mit dem
Web-Frontend. Keine doppelte Codebasis, kein IPC, keine separate Sprache für
den Hintergrundprozess. Der Cron-Einstiegspunkt ist schlicht ein PHP-Script.

**PHP 8.4 ist modernes PHP**
Readonly Properties, Named Arguments, Match-Expressions, Fibers,
`#[\Override]`, property hooks (8.4) — das Projekt nutzt bereits
`declare(strict_types=1)`, Constructor Promotion und readonly. Die Sprache
hat sich erheblich entwickelt; der Ruf aus den 2000ern hinkt der Realität weit hinterher.

**Deployment ist trivial**
`git pull` + ggf. `composer install` — fertig. Kein Build-Schritt, kein
Dockerimage, kein Transpiler, keine Laufzeitumgebung installieren. Für ein
Projekt dieser Größe ein echter Vorteil.

**PHPUnit ist erstklassig**
Unit-Tests für Services und Models laufen mit PHPUnit out-of-the-box.
Der bestehende Test-Stack (`tests/Unit/`) ist ohne Zusatzaufwand integriert.

---

## Contra: PHP behalten / Pro Alternative

**PHP hat keinen nativen Async-Mechanismus für Monitoring-Tasks**
Wenn man hunderte Monitore gleichzeitig prüfen will, muss man auf
Workarounds zurückgreifen (parallele Prozesse, `pcntl_fork`, ReactPHP,
Swoole). Python mit `asyncio` oder Node.js mit async/await lösen das
sprachlich eleganter.

**HTML-Templating direkt in `.php`-Dateien ist fehleranfällig**
Die Views mischen PHP-Kontrollstrukturen mit HTML. Das ist wartbar, aber
ohne konsequentes `htmlspecialchars()` anfällig für XSS. Dedizierte
Template-Engines (Twig, Blade) oder moderne Frontend-Frameworks erzwingen
Auto-Escaping.

**Kein eingebautes Typsystem für komplexe Datenstrukturen**
Arrays sind in PHP universell — `$row['last_checked_at']` kann String,
null oder false sein. TypeScript oder Python mit Type Hints + Mypy
würden Typfehler zur Kompilierzeit abfangen. Der Overhead von
`(int)($row['check_count'] ?? 0)` zieht sich durch den gesamten Code.

**Ökosystem-Wahrnehmung**
PHP hat in der modernen Entwickler-Community ein Image-Problem, auch wenn
das technisch nicht gerechtfertigt ist. Das kann bei Teamwachstum oder
Hiring relevant werden.

---

## Mögliche Alternativen im Überblick

### Python (Flask / FastAPI)

**Pro:**
- `asyncio` + `aiohttp` für paralleles URL-Abrufen nativ
- Sehr ausdrucksstarke String-Verarbeitung (reguläre Ausdrücke, BeautifulSoup)
- Type Hints + Mypy für statische Analyse
- Beliebt für Scraping/Monitoring-Tools

**Contra:**
- Braucht WSGI/ASGI-Server (gunicorn, uvicorn) zusätzlich zum Webserver
- Virtualenv/pip-Verwaltung ist Aufwand
- Kein CLI+Web aus einer Codebasis ohne bewussten Architekturentscheid
- Mehr Abhängigkeiten für das gleiche Ergebnis

**Fazit für dieses Projekt:** Überlegung wert, wenn paralleles Prüfen von
hunderten Monitoren gefordert wird. Für den aktuellen Umfang kein Vorteil.

---

### Node.js (Express / Fastify)

**Pro:**
- Async/await nativ, kein Threading nötig
- npm-Ökosystem riesig (cheerio für HTML-Parsing)
- TypeScript als Aufsatz möglich → starkes Typsystem

**Contra:**
- JavaScript/TypeScript für serverseitiges Web: zwei Sprachen (TS + HTML), Build-Step nötig
- `node_modules` mit tausenden transitiven Abhängigkeiten für ein einfaches Projekt
- Kein nativer cron-Mechanismus — externer Scheduler oder eigenes Daemon-Skript
- Mehr Infrastruktur (PM2 o.ä.) als PHP-FPM

**Fazit für dieses Projekt:** Klarer Overengineering. Node glänzt bei
Echtzeit (WebSockets, SSE) — davon hat dieses Projekt nichts.

---

### Go

**Pro:**
- Compiliertes Binary: sehr schnell, kein Laufzeit-Overhead
- Goroutinen machen paralleles URL-Abrufen trivial
- Starkes Typsystem, null-safety durch Konvention

**Contra:**
- Kein nativer HTML-Template-Standard für dynamische Web-Views
- Kein ORM, kein eingebautes Migrations-Tool — alles selbst bauen oder externe Pakete
- Deutlich mehr Boilerplate für CRUD-Web-Apps als PHP
- Deployment als Binary: Cross-Compilation, kein einfaches `git pull`

**Fazit für dieses Projekt:** Gut für den Monitoring-Core (Crawler), aber
für das Web-Frontend ein erheblicher Mehraufwand ohne Gewinn.

---

### Ruby on Rails

**Pro:**
- Convention over Configuration: CRUD-Apps in sehr wenig Code
- ActiveRecord als ausgereifte ORM-Lösung mit Migrationen
- Sehr produktiv für genau diesen Anwendungsfall (Web + DB + Mailer)

**Contra:**
- Framework-Lock-in: ohne Rails macht Ruby wenig Sinn
- Schwergewichtig: Bootzeit, Memory-Footprint, Abhängigkeitsbaum
- Geringere Verbreitung auf klassischen Shared-/Managed-Hosting-Umgebungen

**Fazit für dieses Projekt:** Philosophisch ähnlich wie das aktuelle Projekt
(Convention + MVC), aber mit dem Rails-Overhead für ein Projekt, das bewusst
framework-frei gebaut wurde, kein Gewinn.

---

## Gesamtfazit

**PHP 8.4 ist für dieses Projekt die optimale Wahl** — nicht trotz, sondern
wegen seiner Einfachheit.

Das Projekt hat keine Anforderungen, die PHP nicht erfüllen kann: kein
Echtzeit-Bedarf, keine hunderte gleichzeitiger Crawls, keine komplexe
Datenverarbeitung. Was es hat, ist ein klassischer Web+DB+Cron-Stack,
für den PHP seit 30 Jahren gebaut und optimiert wird.

Der bewusste Verzicht auf ein Framework ist dabei eine Stärke: 35 PHP-Dateien,
eine Produktions-Abhängigkeit, trivialer Betrieb. Jede Alternative würde das
komplizierter machen, ohne einen messbaren Mehrwert zu liefern.

Wenn das Projekt stark wächst (viele parallele Crawls, komplexe
Differenz-Analyse, öffentliche API), wäre ein Python-Microservice für den
Crawling-Core die natürlichste Ergänzung — das PHP-Frontend könnte dabei
unangetastet bleiben.
