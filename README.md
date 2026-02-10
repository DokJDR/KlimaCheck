# KlimaCheck Wolfratshausen

Ein WordPress-Plugin, das einen **Voting Advice Application (VAA)** bereitstellt – ein interaktives Tool, mit dem Wähler herausfinden können, welche Kandidaten ihre Positionen zu Klima- und Nachhaltigkeitsthemen teilen. Entwickelt für die Kommunalwahl in Wolfratshausen.

## Funktionen

### Wähler-Quiz (Frontend)
- 10 Fragen zu Klima- und Nachhaltigkeitsthemen
- Antwortmöglichkeiten: Ja / Teilweise / Nein
- Gewichtung einzelner Fragen (doppeltes Gewicht für besonders wichtige Themen)
- Fortschrittsanzeige und Navigation (Vor/Zurück)
- Ergebnisseite mit prozentualer Übereinstimmung pro Kandidat
- Detailansicht mit Frage-für-Frage-Vergleich
- Zwei Darstellungsoptionen: Karten-Ansicht und Tabellen-Ansicht
- Modalfenster für vollständige Kandidaten-Statements
- Tooltips für lange Antworttexte

### Admin-Bereich (WordPress Backend)
- Verwaltung von Kandidaten (Name, Partei, Foto, Statement, Antworten)
- Bearbeitung der 10 Fragen inkl. „Warum ist das wichtig?"-Erklärungen
- Zeichenzähler (max. 500 Zeichen pro Antwortbegründung)
- Generierung eindeutiger Vorschau-Links für Kandidaten (Token-basiert)
- Rich-Text-Editor für vollständige Statements

### Datenschutz
- Alle Berechnungen erfolgen clientseitig im Browser
- Keine Datenübertragung an externe Server
- Keine Cookies oder Tracking

## Technologie

| Kategorie | Technologie |
|-----------|-------------|
| Sprachen | PHP, JavaScript (Vanilla), HTML5, CSS3 |
| CMS | WordPress |
| Datenspeicherung | WordPress Options API (MySQL/MariaDB) |
| Externe Abhängigkeiten | Keine |
| Build-Tools | Keine (kein npm, kein Webpack) |

## Installation

1. **Plugin-Dateien kopieren** in das WordPress-Plugin-Verzeichnis:
   ```
   wp-content/plugins/klimacheck-wolfratshausen/
   ├── klimacheck-admin.php
   └── klimacheck-shortcode.php
   ```

2. **Plugin aktivieren** im WordPress-Admin unter *Plugins*.

3. **Fragen konfigurieren** unter *KlimaCheck → Fragen* im Admin-Menü.

4. **Kandidaten anlegen** unter *KlimaCheck → Kandidat hinzufügen*.

5. **Shortcode einbinden** auf einer beliebigen Seite oder einem Beitrag:
   ```
   [klima_check_wolfratshausen]
   ```

## Voraussetzungen

- WordPress 5.x oder höher
- PHP 7.x oder höher
- Moderner Webbrowser (CSS Grid / Flexbox Support)

## Projektstruktur

```
├── klimacheck-admin.php       # Admin-Oberfläche & Datenverwaltung
├── klimacheck-shortcode.php   # Frontend-VAA (Quiz, Ergebnisse, Vergleich)
├── template.html              # HTML-Template
├── .gitignore
└── README.md
```

## Matching-Algorithmus

Der Übereinstimmungsgrad wird wie folgt berechnet:

1. Jede Antwort wird als Zahlenwert abgebildet: Ja = 1, Teilweise = 0,5, Nein = 0
2. Pro Frage wird die absolute Differenz zwischen Nutzer- und Kandidatenantwort ermittelt
3. Bei als „wichtig" markierten Fragen wird die Differenz doppelt gewichtet
4. Endergebnis: `(1 − Σ gewichtete Differenzen / Σ Gewichte) × 100 %`

## Sicherheit

- Nonce-Überprüfung auf allen Formularen (CSRF-Schutz)
- Eingabevalidierung mit `sanitize_text_field()`, `sanitize_textarea_field()`, `esc_url_raw()`
- Ausgabe-Escaping mit `esc_html()`, `esc_attr()`, `esc_url()`
- Rich-Text-Filterung mit `wp_kses_post()`
- Berechtigungsprüfung (`manage_options`) im Admin-Bereich

## Lizenz

© 2026 Wolfratshausen4Future. Alle Rechte vorbehalten.
