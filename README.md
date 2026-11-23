# 🔥 Wandthermostat (Wall Thermostat)

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/wandthermostat/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.0.20261106-orange.svg?style=flat-square)](https://github.com/Wilkware/WallThermostat)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/WallThermostat/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/WallThermostat/actions)

Das Modul synchronisiert das gewählte Heizprofil bzw. -modus mit den verknüpften Stellantrieben (Heizkörpern).  

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichtung](#user-content-4-einrichtung)
5. [Statusvariablen](#user-content-5-statusvariablen)
6. [Darstellungen](#user-content-6-darstellungen)
7. [Visualisierung](#user-content-7-visualisierung)
8. [Befehlsreferenz](#user-content-8-befehlsreferenz)
9. [Versionshistorie](#user-content-9-versionshistorie)

### 1. Funktionsumfang

* Schalten bzw. Abgleichen mit bis zu 8 Heizkörpern (Ventil-/Stellantrieben)
* Selektive Synchronisation von Profil und Modus
* Überwachung von zu starker Abkühlung bzw. Aufheizung
* Berücksichtigung der Heizungsschaltung
* Überwachung des Betriebszustandes der Stellantriebe (Radiatoren)
* Meldung nur beim Eintreten eines neuen Problems (keine Wiederholung bei jedem Prüflauf)
* Nur für HomeMatic Geräte derzeit geeignet!

### 2. Voraussetzungen

* Symcon ab Version 8.1
* Heizkörpersteuerung getestet mit HmIP-WTH2 und/oder eTRV(-2/c)

### 3. Installation

* Über den Modul Store das Modul _Wandthermostat_ installieren.
* Alternativ über das Modul Control folgende URL hinzufügen.  
`https://github.com/Wilkware/WallThermostat` oder `git://github.com/Wilkware/WallThermostat.git`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Wandthermostat_-Modul unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> 🌡️ Heizungssystem ...

Name                            | Beschreibung
------------------------------- | -----------------------------------------------------------------
(Wand)thermostat                | Steuerungskanal des führenden(übersteuernden) Thermostates (Kanal 1)
1.Heizkörper                    | Steuerungskanal des ersten Stellantriebs (Kanal 1)
2.Heizkörper                    | Steuerungskanal des zweiten Stellantriebs (Kanal 1)
3.Heizkörper                    | Steuerungskanal des dritten Stellantriebs (Kanal 1)
4.Heizkörper                    | Steuerungskanal des vierten Stellantriebs (Kanal 1)
5.Heizkörper                    | Steuerungskanal des fünften Stellantriebs (Kanal 1)
6.Heizkörper                    | Steuerungskanal des sechsten Stellantriebs (Kanal 1)
7.Heizkörper                    | Steuerungskanal des siebten Stellantriebs (Kanal 1)
8.Heizkörper                    | Steuerungskanal des achten Stellantriebs (Kanal 1)

> 🔬 Periodische Überprüfung ...

Name                     | Beschreibung
------------------------ | ------------------
Abkühlung                | Schalter zum Aktivieren der Prüfung auf zu starke Abkühlung des Raumes
Abweichung von Solltemperatur | Einstellung der Gradzahl (Negativ) um welche sich der Raum von der Solltemperatur abkühlen darf
Aufheizung               | Schalter zum Aktivieren der Prüfung auf zu starke Aufheizung des Raumes
Abweichung von Solltemperatur | Einstellung der Gradzahl (Positiv) um welche sich der Raum von der Solltemperatur aufheizen darf
Ventilzustand            | Schalter zum Aktivieren der Prüfung des Betriebszustandes der Stellantriebe an den Radiatoren
Heizungsschaltung bei Aufheizung bzw. Abkühlung beachten | Schalter zum Berücksichtigen der Heizungsschaltung (Modus)
Heizungsschaltung        | Variable, welche den Modus des Heizung beinhaltet
Typ                      | Auswahl des Variablentyps
Wert                     | Wert, bei welchem die Prüfung durchgeführt werden soll

> ⏲️ Zeitsteuerung ...

Name                     | Beschreibung
------------------------ | ------------------
Prüfintervall            | Zeit zwischen 2 Inspektionsläufen (Standard 120 Minuten), 0 deaktiviert die Prüfung
Zeitplan                 | Zeitraum in dem Prüfungen im angegebenen Intervall erfolgen sollen

> 📟 Meldungsverwaltung ...

Name                                      | Beschreibung
----------------------------------------- | -----------------------------------------------------------------
Meldung an Anzeige senden                 | Auswahl ob Eintrag in die Meldungsverwaltung erfolgen soll oder nicht (Ja/Nein)
Lebensdauer der Nachricht                 | Wie lange soll die Meldung dort angezeigt werden?
Nachricht an Visualisierung senden        | Auswahl ob Push-Nachricht gesendet werden soll oder nicht (Ja/Nein)
Raumname                                  | Text zur eindeutigen Zuordnung des Raums
Format der Textmitteilung (Abkühlung)     | Frei wählbares Format der Nachricht/Meldung beim Eintreten einer zu starken Raumabkühlung
Format der Textmitteilung (Aufheizung)    | Frei wählbares Format der Nachricht/Meldung beim Eintreten einer zu starken Raumaufheizung
Format der Textmitteilung (Ventilzustand) | Frei wählbares Format der Nachricht/Meldung beim Eintreten eines Problems bei den Stellantrieben
Visualisierungs-Instanz                   | ID der Visualisierung, an welche die Push-Nachrichten gesendet werden soll (WebFront oder TileVisu Instanz)
Titel der Nachricht                       | Titel der Push-Nachricht, darf maximal 32 Zeichen lang sein
Ton der Benachrichtigung                  | Ton der Push-Nachricht (WebFront und Kachel-Visu, Standard: buzzer)
Meldungsskript                            | Skript ID des Meldungsverwaltungsskripts
Meldungsschlüssel (optional)              | Leer = jede Meldung wird neu hinzugefügt (wie bisher). Mit Schlüssel wird eine bestehende Meldung gleichen Typs (Abkühlung, Aufheizung, Ventilzustand) und Schlüssels aktualisiert (Meldungsskript ab v8, ältere Skripte ignorieren den Schlüssel). Gleicher Schlüssel in mehreren Instanzen ergibt je Typ eine gemeinsame Meldung.

> ⚙️ Erweiterte Einstellungen ...

Name                                 | Beschreibung
------------------------------------ | -----------------------------------------------------------------
Checkbox Profilabgleich              | Erstellt ein Schalter zum Aktivieren bzw. Deaktivieren des Profilabgleichs
Checkbox Modusabgleich               | Erstellt ein Schalter zum Aktivieren bzw. Deaktivieren des Modusabgleichs

Aktionsbereich:

> 🧪 Test ...

Aktion                   | Beschreibung
------------------------ | ------------------------------------------------------------
Prüfung ausführen        | Führt die Überprüfung sofort aus und zeigt das Ergebnis als Popup an (Meldung nur bei einem neuen Problem)

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Ident        | Name   | Typ     | Beschreibung
------------ | ------ | ------- | ------------------------------
SyncProfile  | Profil | Boolean | Aktiviert bzw. deaktiviert den Profilabgleich (beim Anlegen: an)
SyncMode     | Modus  | Boolean | Aktiviert bzw. deaktiviert den Modusabgleich (beim Anlegen: an)

_Hinweis:_ Die Statusvariablen werden nur angelegt, wenn die entsprechende Checkbox unter 'Erweiterte Einstellungen' aktiviert ist. Ohne Variable wird immer abgeglichen.

### 6. Darstellungen

Variable     | Darstellung | Werte
------------ | ----------- | ------------------
Profil       | Schalter    | An / Aus
Modus        | Schalter    | An / Aus

### 7. Visualisierung

Die erzeugten Variablen können direkt in die Visualisierung verlinkt werden.  

### 8. Befehlsreferenz

Das Modul stellt keine direkten Funktionsaufrufe zur Verfügung.

### 9. Versionshistorie

v3.0.20261106

* _NEU_: Ton der Push-Benachrichtigung einstellbar
* _NEU_: Optionaler Meldungsschlüssel für das Meldungsskript (ab v8)
* _NEU_: Schaltfläche „Prüfung ausführen“ im Aktionsbereich
* _NEU_: Meldungen nur noch beim Eintreten eines neuen Problems
* _NEU_: Fehlerstatus für ungültige Geräte und Objekte
* _NEU_: Mindestversion Symcon 8.1
* _FIX_: Schalter für Profil- und Modusabgleich waren ohne Funktion
* _FIX_: Geräte-Variablen werden über Ident statt Name ermittelt
* _FIX_: Profil der Heizkörper wird per RequestAction geschaltet, Modus weiterhin über CONTROL_MODE
* _FIX_: Prüftimer wurde bei Konfigurationsänderungen nicht zuverlässig zurückgesetzt
* _FIX_: Boolean-Vergleich der Heizungsschaltung
* _FIX_: Initialisierung erst nach Systemstart
* _FIX_: WebFront-Push verwendete das Icon als Ton

v2.1.20250703

* _NEU_: Berücksichtigung der Heizungsschaltung (Modus) bei der Prüfung
* _FIX_: Interne Bibliotheken überarbeitet und vereinheitlicht

v2.0.20250209

* _NEU_: Periodische Prüfung auf zu starker Abkühlung und/oder Aufheizung
* _NEU_: Periodische Prüfung des Betriebszustandes der verbundenen Stellantriebe

v1.0.20240913

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
