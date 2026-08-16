# Testdokumentation — Content Monitor

## Übersicht

Das Projekt verwendet **PHPUnit 11** als Test-Framework. Alle Tests befinden sich im Verzeichnis `tests/Unit/` und sind als reine **Unit-Tests** angelegt — sie benötigen weder Datenbank noch Netzwerk.

### Verzeichnisstruktur

```
tests/
└── Unit/
    ├── Model/
    │   ├── MonitoredPageTest.php      (5 Tests)
    │   ├── MonitoringDumpTest.php     (4 Tests)
    │   └── UserTest.php              (2 Tests)
    └── Service/
        ├── SelectionSearchServiceTest.php      (14 Tests)
        ├── MonitoringServiceFallbackTest.php    (11 Tests)
        └── SessionServiceTest.php              (15 Tests)
```

Insgesamt: **51 Tests**.

---

## Tests ausführen

Alle Tests starten:

```bash
php vendor/bin/phpunit
```

Lesbarer Output mit Testdox-Format (Testname als Satz):

```bash
php vendor/bin/phpunit --testdox
```

Nur eine bestimmte Testklasse:

```bash
php vendor/bin/phpunit tests/Unit/Service/SelectionSearchServiceTest.php
```

Nur einen einzelnen Test:

```bash
php vendor/bin/phpunit --filter testFindPositionsSingleWordFound
```

### Wo finde ich die Testauswertung?

PHPUnit gibt die Ergebnisse **direkt auf der Konsole (stdout)** aus. Es gibt keine separate Datei oder Web-Oberfläche. Die Ausgabe sieht so aus:

```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

...................................................               51 / 51 (100%)

Time: 00:00.093, Memory: 10.00 MB

OK (51 tests, 83 assertions)
```

- `.` = Test bestanden
- `F` = Test fehlgeschlagen (Assertion nicht erfüllt)
- `E` = Fehler (Exception)

Mit `--testdox` wird jeder Test als lesbarer Satz ausgegeben, z. B.:

```
Selection Search Service (Tests\Unit\Service\SelectionSearchService)
 ✔ Find positions single word found
 ✔ Find positions multiple words found
 ✔ Find positions word not found returns empty
```

---

## Konfiguration

Die Datei `phpunit.xml` im Projektstamm definiert:

| Einstellung      | Wert                   |
|------------------|------------------------|
| Bootstrap         | `vendor/autoload.php`  |
| Testsuite „Unit"  | `tests/Unit/`          |
| Quellcode-Scope   | `app/` (ohne `app/View` und `app/Cli`) |

---

## Was wird getestet?

### 1. Model-Tests — Hydration aus Datenbankzeilen

Die Model-Klassen (`MonitoredPage`, `MonitoringDump`, `User`) besitzen eine statische `fromRow()`-Methode, die ein assoziatives Array (wie von PDO) in ein typisiertes Objekt umwandelt. Die Tests prüfen:

- **Vollständige Hydration**: Alle Felder werden korrekt befüllt.
- **Typ-Casting**: String-IDs aus der DB werden zu `int` konvertiert.
- **Nullable Felder**: `null`-Werte werden korrekt durchgereicht.
- **Default-Werte**: Fehlende optionale Felder erhalten Standardwerte.
- **Datumskonvertierung**: String-Timestamps werden zu `DateTimeImmutable`.

**Beispiel** (`MonitoredPageTest`):

```php
public function testFromRowHydratesAllFields(): void
{
    $row = [
        'id'       => '7',       // String aus der DB
        'user_id'  => '3',
        'url'      => 'https://example.com',
        // ...
    ];

    $page = MonitoredPage::fromRow($row);

    self::assertSame(7, $page->id);           // int, nicht string
    self::assertSame(3, $page->userId);
    self::assertSame('https://example.com', $page->url);
}
```

### 2. SelectionSearchService — Textsuche im HTML

Der `SelectionSearchService` ist die Kernlogik für die Suche von Textauswahlen in HTML-Inhalten. Die Tests decken drei Methoden ab:

#### `tokenize()` — Aufspaltung in Wörter

Normalisiert Whitespace (Tabs, Zeilenumbrüche, Mehrfach-Leerzeichen) und gibt ein Array einzelner Wörter zurück.

```php
$service->tokenize('foo   bar');       // → ['foo', 'bar']
$service->tokenize("foo\nbar");        // → ['foo', 'bar']
$service->tokenize('  foo  ');         // → ['foo']
$service->tokenize('');                // → []
```

#### `findPositions()` — Positions-Ermittlung

Sucht jedes Wort der Auswahl im HTML und gibt die Byte-Positionen als flaches Array `[start, end, start, end, …]` zurück. Verwendet einen zweiphasigen Algorithmus:

- **Phase 1 (Greedy-Forward)**: Sucht jedes Wort sequentiell von links nach rechts.
- **Phase 2 (Engstellen-Verfeinerung)**: Zieht die Positionen iterativ enger zusammen, um den minimalen Textbereich zu finden.

**Beispiel: Einzelnes Wort gefunden**

```php
$html = 'Hello World Test';
$pos  = $service->findPositions($html, 'World');
// → [6, 11]
//    ^    ^
//    |    Position 11: Ende von "World"
//    Position 6: Anfang von "World"
```

**Beispiel: Mehrere Wörter gefunden**

```php
$html = 'The quick brown fox';
$pos  = $service->findPositions($html, 'quick brown');
// → [4, 9, 10, 15]
//    ↑  ↑   ↑   ↑
//    |  |   |   Ende von "brown" (Position 15)
//    |  |   Anfang von "brown" (Position 10)
//    |  Ende von "quick" (Position 9)
//    Anfang von "quick" (Position 4)
```

**Beispiel: Wort nicht vorhanden**

```php
$service->findPositions('Hello World', 'notexistent');
// → []   (leeres Array, Wort nicht gefunden)
```

**Beispiel: Reihenfolge wird respektiert**

```php
$html = 'bar foo';
$service->findPositions($html, 'foo bar');
// → []   (leeres Array, weil "foo" VOR "bar" stehen müsste,
//         im HTML aber "bar" zuerst kommt)
```

**Beispiel: Zweites Wort fehlt**

```php
$service->findPositions('Hello World', 'Hello missing');
// → []   (wenn ein Wort fehlt, scheitert die gesamte Suche)
```

#### `findMissingWord()` — Diagnose fehlender Wörter

Gibt das erste Wort zurück, das im HTML nicht gefunden wurde (mit Index und Text), oder `null` wenn alle vorhanden sind.

```php
$service->findMissingWord('Hello World', 'Hello missing World');
// → ['index' => 1, 'word' => 'missing']

$service->findMissingWord('Hello World', 'Hello World');
// → null   (alle Wörter gefunden)
```

### 3. MonitoringServiceFallbackTest — Positions-Fallback

Testet die private Methode `extractByStoredOffsets()` über Reflection. Diese Methode greift, wenn die exakte Textsuche fehlschlägt und stattdessen gespeicherte relative Positionen verwendet werden.

**Getestete Szenarien:**

| Szenario | Erwartung |
|----------|-----------|
| Keine Offsets gespeichert | `null` |
| Ungültiges JSON | `null` |
| JSON ohne benötigte Felder | `null` |
| `outer_len` = 0 | `null` |
| Leerer Outer-Text | `null` |
| Exakte Position (Umfeld unverändert) | Extraktion mit ±5 Zeichen Puffer |
| Umfeld wurde länger | Positionen werden proportional skaliert |
| Umfeld wurde kürzer | Positionen werden proportional skaliert |
| Position am Textanfang | Clamping auf 0 (kein negativer Index) |
| Position am Textende | Clamping auf Textlänge |

**Beispiel: Exakte Extraktion bei unverändertem Umfeld**

```php
// Ursprünglich gespeichert: "198,00" bei Position 19 im Text
// "Der Preis betraegt 198,00 Euro heute"
//                     ^^^^^^
//                     Position 19-25

// Aktueller Text: Preis hat sich geändert
$current = 'Der Preis betraegt 220,99 Euro heute';

// Ergebnis: ein Textbereich um Position 19-25 (±5 Zeichen),
// der "220,99" enthält
```

### 4. SessionServiceTest — Session-Verwaltung

Testet das Setzen/Lesen von Session-Werten (`url`, `selection`, `innerSelection`, `userId`), den Login-Status und zwei Aufräummethoden:

- `clearMonitorFlow()` — löscht Monitor-Daten, behält aber `userId` (Login bleibt aktiv).
- `reset()` — leert die gesamte Session.

---

## Testmuster und Konventionen

| Konvention | Beschreibung |
|------------|-------------|
| Framework | PHPUnit 11, `PHPUnit\Framework\TestCase` |
| Namespace | `Tests\Unit\Model\*`, `Tests\Unit\Service\*` |
| Klassen | `final class`, eine Testklasse pro Produktivklasse |
| Methoden | `testBeschreibungDesVerhaltens()` (camelCase) |
| Assertions | Bevorzugt `assertSame()` (strenger Typenvergleich) |
| Setup | `setUp()` initialisiert Testdaten, kein Konstruktor |
| Isolation | Kein I/O, keine DB, keine externen Abhängigkeiten |
| Private Methoden | Zugriff über `ReflectionMethod` (siehe FallbackTest) |
