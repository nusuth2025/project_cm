-- Migration: inner_selection_offsets in monitored_pages
-- Speichert die letzte bekannte relative Position der Feinauswahl innerhalb des
-- Umfeld-Textes als JSON {"start": int, "end": int, "outer_len": int}.
-- Wird als dritter Fallback genutzt wenn weder Exakt-Suche noch Mustererkennung
-- einen Wert liefern: Position auf aktuelle Länge skalieren, ±5 Zeichen extrahieren.

USE contentmonitor;

ALTER TABLE monitored_pages
    ADD COLUMN inner_selection_offsets JSON NULL
        COMMENT 'Relative Zeichenposition der Feinauswahl im Umfeld-Text (letzter erfolgreicher Fund)'
        AFTER inner_selection_text;
