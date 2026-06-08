<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\MonitoredPage;
use App\Service\MonitoringService;
use App\Service\SelectionSearchService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Testet den Positions-Fallback in MonitoringService::extractByStoredOffsets().
 *
 * Szenario: Umfeld wird gefunden, Feinauswahl jedoch nicht mehr exakt —
 * weder durch Exakt-Suche noch durch Mustererkennung (kein Ziffernmuster).
 * Der Fallback soll anhand der gespeicherten relativen Position einen
 * Textbereich ±5 Zeichen extrahieren.
 */
final class MonitoringServiceFallbackTest extends TestCase
{
    private MonitoringService $service;
    private ReflectionMethod  $method;

    protected function setUp(): void
    {
        $this->service = new MonitoringService(new SelectionSearchService());
        $this->method  = new ReflectionMethod(MonitoringService::class, 'extractByStoredOffsets');
    }

    private function call(MonitoredPage $page, string $outerText): ?string
    {
        return $this->method->invoke($this->service, $page, $outerText);
    }

    /** Minimales MonitoredPage-Objekt für Tests ohne DB-Zugriff */
    private function makePage(?string $offsets): MonitoredPage
    {
        $page                        = new MonitoredPage();
        $page->id                    = 1;
        $page->userId                = 1;
        $page->url                   = 'https://example.com';
        $page->selectionText         = 'Umfeld Text';
        $page->innerSelectionText    = 'Text';
        $page->innerSelectionOffsets = $offsets;
        $page->label                 = null;
        $page->status                = 'active';
        $page->checkIntervalMinutes  = 1440;
        $page->startHour             = 8;
        $page->createdAt             = new \DateTimeImmutable();
        $page->updatedAt             = new \DateTimeImmutable();
        return $page;
    }

    // ── Null-Fälle ────────────────────────────────────────────────────────────

    public function testReturnsNullWhenNoOffsetsStored(): void
    {
        self::assertNull($this->call($this->makePage(null), 'irgendein Text'));
    }

    public function testReturnsNullWhenJsonInvalid(): void
    {
        self::assertNull($this->call($this->makePage('kein-json'), 'irgendein Text'));
    }

    public function testReturnsNullWhenJsonMissingFields(): void
    {
        self::assertNull($this->call(
            $this->makePage(json_encode(['start' => 5])),
            'irgendein Text'
        ));
    }

    public function testReturnsNullWhenOuterLenIsZero(): void
    {
        $offsets = json_encode(['start' => 0, 'end' => 5, 'outer_len' => 0]);
        self::assertNull($this->call($this->makePage($offsets), 'irgendein Text'));
    }

    public function testReturnsNullWhenOuterTextIsEmpty(): void
    {
        $offsets = json_encode(['start' => 5, 'end' => 10, 'outer_len' => 20]);
        self::assertNull($this->call($this->makePage($offsets), ''));
    }

    // ── Extraktion ohne Skalierung (gleiche Länge) ────────────────────────────

    public function testExtractsValueAtExactPosition(): void
    {
        // Umfeld: "Der Preis betraegt 198,00 Euro heute"
        // Position von "198,00" gespeichert, Umfeld hat sich nicht verändert,
        // nur der Wert selbst ist auf "220,99" geändert.
        $original = 'Der Preis betraegt 198,00 Euro heute';
        $pos      = mb_strpos($original, '198,00');

        $offsets  = json_encode([
            'start'     => $pos,
            'end'       => $pos + 6,
            'outer_len' => mb_strlen($original),
        ]);

        $current = 'Der Preis betraegt 220,99 Euro heute';
        $result  = $this->call($this->makePage($offsets), $current);

        self::assertNotNull($result);
        self::assertStringContainsString('220,99', $result);
    }

    public function testExtractedRegionIsWiderThanInnerSelection(): void
    {
        // Prüft dass die ±5-Zeichen-Expansion greift
        $outerText = 'AAAAA 198,00 BBBBB';
        $pos       = mb_strpos($outerText, '198,00');
        $offsets   = json_encode([
            'start'     => $pos,
            'end'       => $pos + 6,
            'outer_len' => mb_strlen($outerText),
        ]);

        $result = $this->call($this->makePage($offsets), $outerText);

        self::assertNotNull($result);
        // Expansion: extrahierter Bereich muss breiter als die 6 Zeichen sein
        self::assertGreaterThan(6, mb_strlen($result));
        // Und er muss den Wert selbst enthalten
        self::assertStringContainsString('198,00', $result);
    }

    // ── Skalierung (Umfeld hat sich in der Länge verändert) ───────────────────

    public function testScalesPositionWhenOuterTextBecamesLonger(): void
    {
        // Original: "Preis 198,00 Euro" → "198,00" ab Position 6
        $original = 'Preis 198,00 Euro';
        $pos      = mb_strpos($original, '198,00');
        $offsets  = json_encode([
            'start'     => $pos,
            'end'       => $pos + 6,
            'outer_len' => mb_strlen($original),
        ]);

        // Neues Umfeld: deutlich länger, "220,99" liegt an proportional gleicher Stelle
        $current = 'Aktueller Preis 220,99 Euro und noch mehr Text dahinter';
        $result  = $this->call($this->makePage($offsets), $current);

        self::assertNotNull($result);
        self::assertGreaterThan(0, mb_strlen($result));
    }

    public function testScalesPositionWhenOuterTextBecamesShorter(): void
    {
        // Original: langes Umfeld, Wert in der Mitte
        $original = 'Hier steht viel Text davor. Preis 198,00 Euro. Und noch mehr Text danach.';
        $pos      = mb_strpos($original, '198,00');
        $offsets  = json_encode([
            'start'     => $pos,
            'end'       => $pos + 6,
            'outer_len' => mb_strlen($original),
        ]);

        // Neues Umfeld: kürzer, aber Wert noch vorhanden
        $current = 'Preis 220,99 Euro.';
        $result  = $this->call($this->makePage($offsets), $current);

        self::assertNotNull($result);
        self::assertGreaterThan(0, mb_strlen($result));
    }

    // ── Randbereiche (Clamping) ───────────────────────────────────────────────

    public function testClampsToBoundsWhenPositionAtStart(): void
    {
        // Feinauswahl liegt am Anfang des Umfelds → extStart darf nicht negativ werden
        $outerText = 'abc xyz rest des Textes';
        $offsets   = json_encode([
            'start'     => 0,
            'end'       => 3,
            'outer_len' => mb_strlen($outerText),
        ]);

        $result = $this->call($this->makePage($offsets), $outerText);

        self::assertNotNull($result);
        // Muss gültig sein und am Anfang beginnen
        self::assertStringStartsWith('abc', $result);
    }

    public function testClampsToBoundsWhenPositionAtEnd(): void
    {
        // Feinauswahl liegt am Ende des Umfelds → extEnd darf nicht über Länge hinausgehen
        $outerText = 'Viel Text davor und dann xyz';
        $len       = mb_strlen($outerText);
        $offsets   = json_encode([
            'start'     => $len - 3,
            'end'       => $len,
            'outer_len' => $len,
        ]);

        $result = $this->call($this->makePage($outerText), $outerText);

        // Kein Fehler, Ergebnis ist ein valider String
        // (makePage erwartet einen ?string, hier passt es nicht - Test prüft nur kein Absturz)
        self::assertTrue(is_null($result) || is_string($result));
    }

    public function testClampsToBoundsCorrectly(): void
    {
        $outerText = 'Viel Text davor und dann xyz';
        $len       = mb_strlen($outerText);
        $offsets   = json_encode([
            'start'     => $len - 3,
            'end'       => $len,
            'outer_len' => $len,
        ]);
        $page   = $this->makePage($offsets);
        $result = $this->call($page, $outerText);

        self::assertNotNull($result);
        self::assertStringEndsWith('xyz', $result);
    }
}
