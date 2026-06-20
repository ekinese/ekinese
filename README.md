# Ekinese – FSE Block Theme

Ein WordPress Full-Site-Editing-Theme. Header, Footer und alle Seitenlayouts
werden über den Gutenberg Site-Editor gesteuert. Ausgelegt auf viele
Inhaltsseiten (300+), die zentral über wiederverwendbare Blaupausen gepflegt
werden.

## Struktur

```
ekinese/
├── style.css                  # Theme-Header (Pflicht)
├── theme.json                 # Globale Styles, Farben, Typo, Spacing, Custom-Templates
├── functions.php              # lädt inc/* (optional in Block-Themes)
├── parts/
│   ├── header.html            # ← dein fertiger Header
│   └── footer.html            # ← dein fertiger Footer
├── templates/
│   ├── index.html             # Fallback-Template
│   ├── category.html          # Kategorie-Seite (nutzt die Blaupause)
│   ├── archive.html           # andere Archive
│   ├── single.html            # Einzelbeitrag
│   ├── page.html              # Standard-Seite
│   ├── page-blueprint.html    # Seite mit Kategorie-Blaupause (für die 300+ Seiten)
│   ├── page-fullwidth.html    # Seite volle Breite
│   ├── search.html            # Suchergebnisse
│   └── 404.html               # Fehlerseite
├── patterns/
│   └── blueprint-category.php # ← ZENTRALE BLAUPAUSE: einmal pflegen, überall nutzen
├── inc/
│   ├── setup.php              # Theme-Supports, Assets
│   ├── patterns.php           # Pattern-Kategorie "Ekinese"
│   └── block-styles.php       # eigene Block-Styles (Karte, abgerundet …)
└── assets/css/                # ergänzende Styles (main.css, editor.css)
```

## Deine vorhandenen Teile einsetzen

1. **Header/Footer**: Inhalt von `parts/header.html` bzw. `parts/footer.html`
   durch deinen Block-Markup ersetzen (oder im Site-Editor unter
   *Design → Editor → Vorlagenteile* bearbeiten).
2. **Kategorie-Layout (Blaupause)**: Dein fertiges Layout in
   `patterns/blueprint-category.php` einfügen. Den Block-Markup bekommst du im
   Editor über *Optionen (⋮) → „Als HTML kopieren"*.

## Per Gutenberg steuern

Alles ist im **Site-Editor** (`Design → Editor`) editierbar:

- **Vorlagenteile** → Header & Footer
- **Vorlagen** → Kategorie, Seite, Single, 404 …
- **Muster (Patterns)** → die Blaupause unter Kategorie „Ekinese"

Wichtig: Bearbeitest du eine Vorlage im Editor, überschreibt WordPress sie in
der Datenbank. Die HTML-Dateien hier sind der **Auslieferungszustand**. Für ein
sauberes Git-Workflow kannst du Änderungen aus dem Editor per
*Werkzeuge → Vorlagen exportieren* zurück in diese Dateien übernehmen.

---

## Beratung: Die 300+ Seiten als CMS pflegen

Du hast drei realistische Wege. Empfehlung hängt davon ab, wie **gleichartig**
die Seiten sind.

### Option A – Eine Blaupause + normale WordPress-Seiten (empfohlen für den Start)
Jede der 300 Seiten ist eine normale `page`. Das Layout kommt aus dem Pattern
`blueprint-category` bzw. dem Template `page-blueprint.html`. Redakteure füllen
nur den Inhalt; das Gerüst bleibt zentral.
- ✅ Kein Plugin nötig, reines Gutenberg/FSE.
- ✅ Layout-Änderung an *einer* Stelle (Pattern) wirkt überall.
- ⚠️ Inhalte sind „frei" im Editor – weniger strukturiert/erzwungen.
- Gut, wenn die Seiten **optisch gleich, inhaltlich frei** sind.

### Option B – Custom Post Type + strukturierte Felder (skaliert am besten)
Wenn die 300 Seiten denselben Datentyp beschreiben (z. B. Produkte, Orte,
Ärzte, Projekte) mit festen Feldern (Titel, Bild, Preis, 3 Textblöcke …):
- Eigener **Custom Post Type** + Felder (ACF, Meta Box oder Core-Block-Bindings).
- Layout rendert automatisch aus den Feldern → 300 Seiten, **null** manuelles
  Layouten, garantiert konsistent.
- ✅ Massenimport (CSV/WP-CLI) möglich – ideal bei 300+ Datensätzen.
- ✅ Felder sind validiert, Redakteure können das Layout nicht „kaputt" machen.
- ⚠️ Etwas Setup (CPT registrieren, Felder definieren, Template binden).
- **Meine Empfehlung, wenn die Seiten datengetrieben sind.**

### Option C – Synced Patterns (früher „Reusable Blocks")
Ein synchronisiertes Pattern wird auf allen Seiten eingebettet; Änderung am
Pattern ändert alle Instanzen. Gut für wiederkehrende Bausteine (CTA, Kontakt-
Box), aber **nicht** als Träger für 300 individuelle Inhalte geeignet.

### Kurz-Empfehlung
- Sind die 300 Seiten **strukturierte Datensätze** (gleiche Felder)? → **Option B (CPT + Felder)**, ggf. mit Block-Bindings, damit es FSE-nativ bleibt.
- Sind sie **optisch gleich, aber inhaltlich frei** (klassische Landing-/Infoseiten)? → **Option A (Blaupause-Pattern + page-blueprint Template)**.
- In beiden Fällen ist die hier angelegte Blaupause die Basis.

Sag mir, welcher der drei Fälle auf deine 300 Seiten zutrifft (z. B. „es sind
Standorte mit Adresse/Öffnungszeiten" vs. „freie Ratgeber-Artikel"), dann baue
ich dir den passenden Teil konkret aus – inkl. CPT-Registrierung, Feldern und
automatischem Layout-Rendering, falls Option B.
