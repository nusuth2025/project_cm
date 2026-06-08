# Fehleranalyse: Prüfintervalle werden nicht eingehalten

## Symptom

Monitore mit einem Prüfintervall von 15 Minuten liefen effektiv im 30-Minuten-Takt.
Konkret: Monitore, die um 22:30 geprüft wurden, wurden beim nächsten Cron-Lauf
um 23:00 korrekt erneut geprüft. Monitore, die um 22:45 geprüft wurden, wurden
hingegen erst um 23:15 geprüft — nicht wie erwartet um 23:00.

---

## Ursache

Die Fälligkeitsprüfung in `app/Cli/monitor.php` vergleicht Zeitstempel auf
Sekunden-Ebene:

```sql
DATE_ADD(last_checked_at, INTERVAL check_interval_minutes MINUTE) <= NOW()
```

Der Cron-Job wird um 22:45:00 gestartet. Die eigentliche Prüfung eines Monitors
beginnt erst nach einigen Sekunden (HTTP-Abruf, Datenbankzugriff), sodass
`last_checked_at` z. B. auf **22:45:18** gesetzt wird.

Beim nächsten Cron-Lauf um 23:00:00 ergibt sich:

```
22:45:18 + 15 Minuten = 23:00:18
23:00:18 <= 23:00:00  →  FALSE  →  Monitor gilt als NICHT fällig
```

Der Monitor wird übersprungen. Erst beim übernächsten Cron-Lauf um **23:15:00**
ist die Bedingung erfüllt:

```
22:45:18 + 15 Minuten = 23:00:18
23:00:18 <= 23:15:00  →  TRUE  →  Monitor wird geprüft
```

Statt 15 Minuten vergeht effektiv fast eine halbe Stunde — ein klassischer
**Off-by-a-few-seconds**-Fehler, der nur bei kurzen Intervallen sichtbar wird.

---

## Fix

Sekunden werden auf beiden Seiten des Vergleichs auf `00` gesetzt.
Der Vergleich findet dadurch auf Minuten-Ebene statt — konsistent mit dem
Cron-Modell, das ohnehin nur auf ganzen Minuten auslöst.

```sql
-- Vorher:
DATE_ADD(last_checked_at, INTERVAL check_interval_minutes MINUTE) <= NOW()

-- Nachher:
DATE_ADD(
    DATE_FORMAT(last_checked_at, '%Y-%m-%d %H:%i:00'),
    INTERVAL check_interval_minutes MINUTE
) <= DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00')
```

Mit dem Fix gilt für einen Monitor mit `last_checked_at = 22:45:18`:

```
22:45:00 + 15 Minuten = 23:00:00
23:00:00 <= 23:00:00  →  TRUE  →  Monitor wird korrekt geprüft
```

---

## Gibt es eine Warteschlange?

Eine explizite Warteschlange existiert nicht. Was das System stattdessen bietet:

**Natürliche Staffelung** — Monitore werden in der `foreach`-Schleife
sequenziell abgearbeitet. Da jeder HTTP-Abruf einige Sekunden dauert,
erhält jeder Monitor ein leicht unterschiedliches `last_checked_at`.
Nach wenigen Läufen driften die Zeitstempel von selbst auseinander.

**Konfigurierbare Startzeit** — der Erstlauf-Zeitpunkt ist pro Monitor über
`start_hour` einstellbar. Monitore, die zu verschiedenen Tageszeiten angelegt
werden, starten ihre Intervall-Ketten automatisch versetzt.

**Bewusste Designentscheidung:** Für ein persönliches Monitoring-Tool mit
einer überschaubaren Anzahl von Monitoren ist eine explizite Queue-Mechanik
nicht notwendig. Das Script ist zustandslos und terminiert nach jedem Lauf —
eine einfache, robuste Architektur. Bei einer deutlich größeren Anzahl von
Monitoren wäre ein `LIMIT N` in der SQL-Abfrage die naheliegendste Erweiterung,
um pro Cron-Lauf nur einen Teil der fälligen Monitore abzuarbeiten.
