# THW OV-SSP — Projektstatus / Project Status

## Projektübersicht / Project Overview

Das OV-SSP (Ortsverband Self-Service-Portal) ist eine TYPO3-Erweiterung für die Verwaltung von Benutzerberechtigungen auf Netzlaufwerken der THW-Ortsverbände. Helferdaten werden aus dem Active Directory per CSV importiert. OV-Administratoren verwalten über das Portal Rollen und Verzeichnisberechtigungen.

*The OV-SSP is a TYPO3 extension for managing user permissions on THW chapter network shares. Helper data is imported from Active Directory via CSV. Chapter admins manage roles and directory permissions through the portal.*

---

## Phase 1 — Datenschicht & CSV-Import ✅ Abgeschlossen

### Was wurde umgesetzt

**Datenmodell:**
- Organisationseinheiten (OE/Ortsverbände) — Tabelle mit OE-Kennung, Kürzel, Name, E-Mail, Regionalbereich, Landesverband
- Verzeichnisse — Globale Masterliste der Netzlaufwerk-Basisverzeichnisse (~30 Einträge)
- Benutzer — Erweiterung der TYPO3 `fe_users`-Tabelle mit THW-spezifischen Feldern (THW-Kennung, OE-Zuordnung, AD-Bereich)

**CSV-Import (Backend-Modul):**
- Administrations-Modul im TYPO3-Backend unter "Admin → OV-SSP Import"
- Upload von CSV-Dateien für Organisationseinheiten, Verzeichnisse und Benutzer
- Validierung der CSV-Daten: ungültige Zeilen werden übersprungen und gemeldet, gültige Zeilen importiert
- Idempotenter Import: Reimport aktualisiert bestehende Datensätze
- Deaktivierung fehlender Benutzer: Nicht mehr in der CSV enthaltene Benutzer werden deaktiviert, bei erneutem Erscheinen reaktiviert
- Listenansichten mit Paginierung für alle drei Datentypen

**Qualitätssicherung:**
- 33 Unit-Tests, alle bestanden
- PHPStan Level 9 (maximale Strenge): keine Fehler
- PHP CS Fixer: konform
- PHP 8.4 kompatibel

### Technische Eckdaten
| Eigenschaft | Wert |
|---|---|
| TYPO3 Version | v14.3.7 LTS |
| PHP Version | 8.4 |
| Datenbank | MariaDB |
| Extension Key | `thw_ovssp` |
| Namespace | `Init\Thw\Ovssp` |
| Benutzer-Bestand | ~91.000 fe_users (HA + EA) |

---

## Phase 2 — Rollen & Verzeichnisberechtigungen 🔄 In Planung

### Geplanter Umfang

**Rollen (Rollen-Tabelle):**
- Benannte Berechtigungsbündel, z.B. "OV-User" (Pflichtrolle für alle aktiven Helfer)
- Jede Rolle enthält eine Menge von Verzeichnisberechtigungen (lesen/schreiben/verweigern)
- Rollen werden von zentralen Administratoren im TYPO3-Backend angelegt

**Verzeichnisberechtigungen:**
- Verknüpfung Rolle → Verzeichnis mit Zugriffsstufe (lesen, schreiben, verweigern)
- Auflösungsregel: **Schreiben schlägt Lesen, Verweigern überschreibt alles**
- Kombinierte Berechtigungen werden pro Benutzer aus allen zugewiesenen Rollen berechnet

**Rollenzuweisung:**
- OV-Administratoren weisen Benutzern Rollen zu / entfernen sie
- Ein Benutzer kann mehrere Rollen haben

**Frontend-Portal:**
- Frontend-Plugin (Extbase) für OV-Administratoren
- Benutzerübersicht der eigenen OE
- Rollenzuweisung und kombinierte Berechtigungsansicht
- Authentifizierung zunächst über TYPO3 fe_user Login (SSO/OIDC in späterer Phase)

---

## Phasenplanung / Roadmap

| Phase | Inhalt | Status |
|---|---|---|
| **Phase 1** | Datenschicht, CSV-Import, Backend-Modul | ✅ Abgeschlossen |
| **Phase 2** | Rollen, Verzeichnisberechtigungen, Frontend-Portal | 🔄 In Planung |
| **Phase 3** | Berechtigungsgruppen (Fernzugang, KdB), Antragsworkflow | ⏳ Ausstehend |
| **Phase 4** | OIDC/SSO-Anbindung (HA + EA IdP), Frontend-Authentifizierung | ⏳ Ausstehend |
| **Phase 5** | Audit-Log, Kontingente, automatisierter Import (2× täglich) | ⏳ Ausstehend |

---

## Offene Fragen

1. Soll die Rollenverwaltung (Anlegen/Bearbeiten von Rollen) nur zentral im TYPO3-Backend erfolgen, oder auch im Frontend-Portal?
2. Sind Rollen global (für alle OE gleich) oder OE-spezifisch (jede OE kann eigene Rollen definieren)?
3. Sollen deaktivierte Benutzer weiterhin im Frontend-Portal angezeigt werden?
4. Welche fe_users sind OV-Administratoren? Wird dies über eine Benutzergruppe gesteuert?
