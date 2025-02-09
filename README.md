# Wandthermostat (Wall Thermostat)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.0.20250209-orange.svg?style=flat-square)](https://github.com/Wilkware/WallThermostat)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/WallThermostat/style.yml?branch=main&label=CheckStyle&style=flat-square)](https://github.com/Wilkware/WallThermostat/actions)

Das Modul synchronisiert das gewählte Heizprofil bzw. -modus mit den verknüpften Stellantrieben (Heizkörpern).  

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

* Schalten bzw. Abgleichen mit bis zu 8 Heizkörpern (Ventil-/Stellantrieben)
* Selektive Synchronisation von Profil und Modus
* Überwachung von zu starker Abkühlung bzw. Aufheizung
* Überwachung des Beriebszustandes der Stellantriebe (Radiatoren)
* Nur für Homatic Geräte derzeit geeignet!

### 2. Voraussetzungen

* IP-Symcon ab Version 6.4
* Heizkörpersteuerung getestet mit HmIP-WTH2 und/oder eTRV(-2/c)

### 3. Installation

* Über den Modul Store das Modul _Wandthermostat_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/WallThermostat` oder `git://github.com/Wilkware/WallThermostat.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das _Wandthermostat_-Modul unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Heizungssystem ...

Name                            | Beschreibung
------------------------------- | -----------------------------------------------------------------
(Wand)thermostat                | Steuerungskanal des führenden(übersteuernden) Thermostates (Kanal 1)
1.Heizkörper                    | Steuerungskanal des ersten Stellantriebs (Kanal 1)
2.Heizkörper                    | Steuerungskanal des zweiten Stellantriebs (Kanal 1)
3.Heizkörper                    | Steuerungskanal des drittem Stellantriebs (Kanal 1)
4.Heizkörper                    | Steuerungskanal des vierten Stellantriebs (Kanal 1)
5.Heizkörper                    | Steuerungskanal des fünften Stellantriebs (Kanal 1)
6.Heizkörper                    | Steuerungskanal des sechsten Stellantriebs (Kanal 1)
7.Heizkörper                    | Steuerungskanal des siebten Stellantriebs (Kanal 1)
8.Heizkörper                    | Steuerungskanal des achten Stellantriebs (Kanal 1)

> Periodische Überprüfung ...

Name                     | Beschreibung
------------------------ | ------------------
Abkühlung                | Schalter zum Aktivieren der Prüfung auf zu starke Abkühlung des Raumes
Abweichung von Solltemperatur | Einstellung der Gradzahl (Negativ) um welche sich der Raum von der Solltemperatur abkühlen darf
Aufheizung               | Schalter zum Aktivieren der Prüfung auf zu starke Aufheizung des Raumes
Abweichung von Solltemperatur | Einstellung der Gradzahl (Positiv) um welche sich der Raum von der Solltemperatur aufheizen darf
Ventilzustand            | Schalter zum Aktivieren der Prüfung des Betriebszustandes der Stellantriebe an den Radiatoren

> Zeitsteuerung ...

Name                     | Beschreibung
------------------------ | ------------------
Prüfintervall            | Zeit zwischen 2 Inspektionsläufen (Standard 120 Minuten), 0 deaktiviert die Prüfung
Zeitplan                 | Zeitraum in dem Prüfungen im angeegebenen Intervall erfolgen sollen

> Meldungsverwaltung ...

Name                                      | Beschreibung
----------------------------------------- | -----------------------------------------------------------------
Meldung an Anzeige senden                 | Auswahl ob Eintrag in die Meldungsverwaltung erfolgen soll oder nicht (Ja/Nein)
Lebensdauer der Nachricht                 | Wie lange soll die Meldung dort angezeigt werden?
Nachricht an Visualisierung senden        | Auswahl ob Push-Nachricht gesendet werden soll oder nicht (Ja/Nein)
Raumname                                  | Text zur eindeutigen Zuordnung des Raums
Format der Textmitteilung (Abkühlung)     | Frei wählbares Format der Nachricht/Meldung beim Eintreten einer zu starken Raumabkühlung
Format der Textmitteilung (Aufheizung)    | Frei wählbares Format der Nachricht/Meldung beim Eintreten einer zu starken Raumaufheizung
Format der Textmitteilung (Ventilzustand) | Frei wählbares Format der Nachricht/Meldung beim Eintreten eines Problems bei den Stellantrieben
Visualisierungs-Instanz                   | ID der Visualisierung, an welches die Push-Nachrichten gesendet werden soll (WebFront oder TileVisu Instanz)
Titel der Nachricht                       | Titel der Push-Nachricht, darf maximal 32 Zeichen lang sein
Meldsungsskript                           | Skript ID des Meldungsverwaltungsskripts

> Erweiterte Einstellungrn ...

Name                                 | Beschreibung
------------------------------------ | -----------------------------------------------------------------
Checkpox  Profilabgleich             | Erstellt ein Schalter zum Aktivieren bzw. Deaktivieren des Profilabgleichs
Checkpox  Modusabgleich              | Erstellt ein Schalter zum Aktivieren bzw. Deaktivieren des Modusabgleichs

### 5. Statusvariablen und Profile

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
Profil               | Boolean   | Schalter mit Variablenprofil ~Switch (Standard: true)
Modus                | Boolean   | Schalter mit Variablenprofil ~Switch (Standard: true)

### 6. Visualisierung

Die erzeugten Variablen können direkt in die Visualisierung verlingt werden.  

### 7. PHP-Befehlsreferenz

Das Modul stellt keine direkten Funktionsaufrufe zur Verfügung.

### 8. Versionshistorie

v2.0.20250209

* _NEU_: Periodische Prüfung auf zu starker Abkühlung und/oder Aufheizung
* _NEU_: Periodische Prüfung des Betriebszudtandes der verbundenen Stellantriebe

v1.0.20240913

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
