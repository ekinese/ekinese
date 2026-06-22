# XGOUD – Bedienungsanleitung

Diese Anleitung erklärt das selbstgebaute XGOUD-System (WordPress-Theme „ekinese",
ohne Plugins außer Redis/FlyingCache/Cloudflare). Sie richtet sich an dich als
Betreiber/Admin.

---

## 1. Erstinstallation (Staging)

1. Theme `ekinese` in WordPress hochladen und aktivieren (Design → Themes).
2. Im Admin: **Werkzeuge → XGOUD setup** öffnen → **„Installeren / bijwerken"** klicken.
   - Legt automatisch **alle ~40 Seiten** an (Home, Edelmetalen, Dagprijzen, Service,
     Over ons, Rechtliches, „Mijn XGOUD", „Rit"), in korrekter Eltern/Kind-Struktur.
   - Setzt die **Startseite**.
   - Importiert **606 Produkte**, **45 Standorte**, das **Lexikon** und die **Uhren**.
   - Ist beliebig wiederholbar (keine Dubletten).
3. Einstellungen → Permalinks einmal speichern (Rewrite-Regeln aktualisieren).
4. Menüstruktur: Alles liegt gebündelt unter dem Hauptmenü **„XGOUD"** (links in der Sidebar).

---

## 2. Das XGOUD-Hauptmenü (Überblick)

Unter **XGOUD → Overzicht** findest du ein Dashboard mit Kennzahlen und Schnell-Links,
gruppiert in:

| Gruppe | Inhalt |
|---|---|
| Klanten & verkoop | Afspraken, KYC/Opkopersregister, Tickets, Zendingen, Routes, Chats |
| Catalogus | Producten, Horloges, Kantoren, Lexicon |
| Voorraad & partners | Voorraad (Inventory), Zakenpartners, Boekhouding |
| Loyaliteit & community | Punten, Loterijen, Portfolio, Marktplaats, Goede doelen |
| Marketing | Social media, Ads, Nieuwsbrief, Mail-log |
| HR | Medewerkers, Urenregistratie |

---

## 3. API-Schlüssel eintragen (nichts liegt im Code)

Alle Schlüssel werden **nur in der WP-Datenbank** gespeichert:

- **Instellingen → Integraties**: Google Tag (GA4) + reCAPTCHA v3.
- **Instellingen → IDEX prijzen**: Diamant-Live-Preise (Key + Secret + USD/EUR).
- **Instellingen → Social login**: Google/Apple/Facebook (Client-ID + Secret; die
  Redirect-URI steht jeweils dabei und muss beim Anbieter eingetragen werden).
- **Instellingen → SEO-indexering**: IndexNow-Schlüssel (wird automatisch erzeugt).
- **XGOUD → Social media → API-sleutels**: Instagram/Facebook/LinkedIn/Google
  Business/Yelp/X.
- **XGOUD → Ads → API-sleutels**: Google/Bing/Facebook/X Ads.

---

## 4. Tägliche Abläufe

### 4.1 Ankauf / Termin
1. Kunde bucht über **/afspraak/** (Rechner → Termin) oder du legst unter
   **Afspraken → Neu** einen Termin an.
2. Bei Ankauf: **KYC / Opkopersregister → Neu** anlegen.
   - Pflichtfelder/Dokumente erscheinen automatisch je nach Betrag/Zahlart:
     - ID-Pflicht ab **€3.000** Ankauf, Verkauf ab **€250**.
     - Bar **≥ €10.000** → Warnung „verschärfte Prüfung + Meldung FIU-NL".
     - **Unter 18 → roter Warnhinweis, Ankauf nicht erlaubt.**
   - ID-Scan + Belege hochladen (nicht öffentlich, 5 Jahre aufbewahrt).
3. Nach Abschluss Termin auf **„afgerond/uitbetaald"** setzen → Kunde bekommt
   automatisch **Treuepunkte**.

### 4.2 Fahrer / Abholung (PWA)
- Jede Nacht um **00:00** plant das System automatisch die Route für den nächsten
  Tag (alle Thuisbezoek-/Ophaalservice-Termine, geordnet ab Hauptsitz).
- **XGOUD → Routes** öffnen → die **Routecode** kopieren.
- Der Fahrer öffnet **/rit/** auf dem **iPhone**, gibt die Routecode ein:
  - sieht die geordnete Route + „Navigeren"-Links,
  - sendet automatisch alle 60 Sek. **Live-GPS**,
  - setzt pro Stopp Status (Aangekomen/Opgehaald/Afgeleverd) und schreibt
    **Pickup-/Delivery-Notizen**.
- **AirTag**: optional die AirTag-ID bei der Zending eintragen (Diebstahlschutz
  der Ware, Ortung über „Wo ist?"/Find My).
- Manuell neu planen: **Routes → Nu plannen**.

### 4.3 Inventory & Abnehmer
1. Gekaufte Ware unter **Voorraad → Neu** erfassen (Metall, Gewicht, Einkaufspreis).
2. Status durchschalten: op voorraad → toegewezen → verkocht → verzonden.
3. Abnehmer (Großhandel) unter **Zakenpartners** pflegen (mit Konditionen) und der
   Voorraad-Position zuweisen.

### 4.4 Buchhaltung
- **Boekhouding → Neu**: Rechnung (eingehend = Kosten / ausgehend = Umsatz),
  Scan hochladen → Kategorie wird automatisch vorgeschlagen.
- **Boekhouding → Overzicht**: Umsatz, Kosten, Ergebnis und Kosten je Kategorie.

### 4.5 HR
- **Medewerkers**: Mitarbeiter + HR-Daten (Funktion, Stundenlohn, IBAN).
- **Urenregistratie**: Stunden je Mitarbeiter/Tag. Auf der Mitarbeiterseite siehst
  du Monats-/Gesamtstunden + Anzahl Termine.

---

## 5. Marketing & SEO

- **SEO-indexering** schickt 3×/Tag die Preisseiten an IndexNow (Bing/Yandex) und
  hält die Sitemap frisch. Manuell: „Nu indienen".
- **Nieuwsportaal**: RSS-Feeds (Edelmetalle/Edelsteine/Uhren) + eigene News.
  Feeds pflegen unter **Nieuwsportaal**.
- **Social media**: Posts/Reels planen; der Bot beantwortet Fragen/Reviews mit
  den Seiten-Daten.
- **Ads**: Kampagnen anlegen (Google/Bing/FB/X).
- **Nieuwsbrief**: Abonnenten + Bulk-Versand. Willkommensmail automatisch.

---

## 6. Community-Module

- **Punten (Rewards)**: Punkte für abgeschlossene Deals, Teilen, Empfehlungen.
- **Loterijen**: monatliche Verlosung; Teilnahme kostet Punkte; Gewinner per Klick
  auslosen.
- **Portfolio/Wishlist**: Kunden verfolgen ihren Bestand (Wert + Gewinn/Verlust)
  und setzen Preisalarme.
- **Marktplaats**: Wunsch trifft fremden Bestand → XGOUD vermittelt als **Treuhänder**
  (Käufer/Verkäufer sehen sich nie). Ablauf unter **Marktplaats** + Provision unter
  **Marktplaats → Instellingen**.

---

## 7. Kundenkonto „Mijn XGOUD"

- Passwortlos: Kunde gibt E-Mail ein → **Magic-Link** per Mail, oder **Social Login**
  (Google/Apple/Facebook).
- Zeigt: Punkte, Portfolio-Wert + Gewinn/Verlust, laufende Loterijen, Termine,
  Tickets, Zendingen, Preisalarme, Empfehlungslink.
- **Onboarding-Tour**: Beim ersten Besuch erscheint eine geführte Tooltip-Tour.

---

## 8. Sprachen & Dark Mode

- Oben rechts: Sprache (8), Währung, Tag/Nacht-Umschalter.
- **Mehrsprachig serverseitig**: eigene URLs `/en/`, `/fr/` … mit `hreflang` →
  indexierbar. Der **Chatbot antwortet in der gewählten Sprache**.
- Alle Chats werden gespeichert (XGOUD → Chats) – nutzbar für Training/Analyse.

---

## 9. Performance & Caching

- Produktdaten + Aggregate liegen im **Redis-Objektcache** + Transients.
- Die schwere Produktliste lädt **nur auf Rechner-Seiten**.
- Nicht-kritische Skripte sind **defer**; auf NL wird die Übersetzungs-JS nicht geladen.
- Seiten-Cache via **Cloudflare/FlyingCache**.

---

## 10. Wichtige Seiten-URLs

| Zweck | URL |
|---|---|
| Startseite | `/` |
| Rechner / Termin | `/afspraak/` |
| Dagprijzen | `/dagprijzen/` (+ `/goudprijs/` …) |
| Inkoopprijzen | `/inkoopprijzen/` |
| Kundenkonto | `/mijn-xgoud/` |
| Fahrer-App | `/rit/?token=ROUTECODE` |
| Sitemap | `/sitemap.xml` |

---

*Bei Fragen: Dieses System ist vollständig self-built – jede Funktion liegt als
eigenes Modul unter `inc/` und ist im Admin steuerbar.*
