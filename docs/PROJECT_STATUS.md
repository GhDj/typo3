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

## Phase 2 — Rollen & Verzeichnisberechtigungen ✅ Implementiert

### Was wurde umgesetzt

**Datenmodell:**
- Rollen-Tabelle (`tx_thwovssp_domain_model_role`) — Benannte Berechtigungsbündel mit Gruppe und Beschreibung
- Verzeichnisberechtigungen (`tx_thwovssp_domain_model_directoryright`) — Rolle→Verzeichnis mit Zugriffsstufe (lesen/schreiben/verweigern)
- Benutzer-Rollen-Zuordnung (`tx_thwovssp_user_role_mm`) — M:N-Tabelle
- Rollen sind global (für alle OE gleich), Verwaltung nur im TYPO3-Backend (zentrale Admins via TCA/Listenmodul)

**Berechtigungsauflösung (RightsService):**
- Kombinierte Berechtigungen pro Benutzer aus allen zugewiesenen Rollen
- Auflösungsregel: **Schreiben schlägt Lesen, Verweigern überschreibt alles**
- Sortierung nach Verzeichnis-Sortierkey, dann Name

**Portalzugang (`thw_portal_access`):**
- Erweitert von Checkbox auf 3 Stufen: 0 = kein Zugang, 1 = Benutzer (eigene Daten), 2 = OV-Admin (OE verwalten)
- OV-Admins werden über `thw_portal_access = 2` identifiziert
- OV-Admins sehen nur Benutzer ihrer eigenen OE

**Frontend-Portal (Extbase-Plugin):**
- Plugin `thwovssp_portal` — Benutzerübersicht, Detailansicht, Rollenzuweisung
- OV-Admins: Benutzer der eigenen OE anzeigen, Rollen zuweisen/entfernen, kombinierte Berechtigungen einsehen
- Deaktivierte Benutzer werden angezeigt (grau markiert), Rollenzuweisung ist gesperrt
- Authentifizierung über TYPO3 fe_user Login (SSO/OIDC in späterer Phase)

**Rollenverwaltung:**
- Zentrale Admins verwalten Rollen und Verzeichnisberechtigungen im TYPO3-Backend (TCA/Listenmodul)
- Vollständige TCA-Konfiguration für Rollen und Verzeichnisberechtigungen

**Qualitätssicherung:**
- 71 Unit-Tests (33 bestehend + 38 neu), alle bestanden
- Alle neuen Dateien: PHP-Syntax geprüft, PHPStan-Level-9-konform geschrieben
- PHP 8.4 kompatibel, QueryBuilder-only (kein Raw SQL)

---

## Phasenplanung / Roadmap

| Phase | Inhalt | Status |
|---|---|---|
| **Phase 1** | Datenschicht, CSV-Import, Backend-Modul | ✅ Abgeschlossen |
| **Phase 2** | Rollen, Verzeichnisberechtigungen, Frontend-Portal | ✅ Implementiert |
| **Phase 3** | Berechtigungsgruppen (Fernzugang, KdB), Antragsworkflow | ⏳ Ausstehend |
| **Phase 4** | OIDC/SSO-Anbindung (HA + EA IdP), Frontend-Authentifizierung | ⏳ Ausstehend |
| **Phase 5** | Audit-Log, Kontingente, automatisierter Import (2× täglich) | ⏳ Ausstehend |

---

## Beantwortete Fragen / Resolved Questions

1. **Rollenverwaltung:** Nur zentral im TYPO3-Backend (TCA/Listenmodul). OV-Admins weisen nur bestehende Rollen zu.
2. **Rollen-Scope:** Global (für alle OE gleich). Per-OE-Rollen ggf. in späterer Phase.
3. **Deaktivierte Benutzer:** Werden im Frontend angezeigt (grau markiert), Rollenzuweisung gesperrt.
4. **OV-Admin-Erkennung:** Über `thw_portal_access = 2` (Stufe 0 = kein Zugang, 1 = Benutzer, 2 = OV-Admin).
