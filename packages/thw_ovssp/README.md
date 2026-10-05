# THW OV-SSP — Self-Service-Portal Extension

TYPO3 v14 extension for managing THW Ortsverband helper data, directory permissions, and authorization workflows.

## Requirements

- TYPO3 v14.x LTS
- PHP 8.4+
- MariaDB

## Installation

```bash
# Add path repository to root composer.json
composer config repositories.local path "packages/*"

# Require the extension
composer require thw/thw-ovssp:@dev

# Activate and create database schema
ddev typo3 extension:setup
ddev typo3 database:updateschema
ddev typo3 cache:flush
```

## What It Does

### Data Model

| Table | Purpose |
|---|---|
| `tx_thwovssp_domain_model_orgunit` | Organizational units (Ortsverbände, with Regionalbereich and Landesverband codes) |
| `tx_thwovssp_domain_model_directory` | Network share base directories (global master list, ~30 entries) |
| `fe_users` (extended) | Helper accounts imported from Active Directory CSV exports |

### Backend Module

Located under **Admin → OV-SSP Import** with four tabs:

- **Import** — Upload CSV files for OrgUnits, Directories, or Users
- **Organizational Units** — Browse imported OrgUnits with pagination
- **Directories** — Browse imported directories with pagination
- **Users** — Browse imported users with OrgUnit association and pagination

### CSV Import

Three CSV types (semicolon-delimited, UTF-8):

**OrgUnits:**
```
thw_oe_uid;oe_code;name;mail_address;Regionalbereich;Landesverband
```

**Directories:**
```
Verzeichnis;lesen;schreiben;SortKey;Beschreibung
```

**Users:**
```
thw_uid;username;first_name;last_name;oe_code
```

Import order: OrgUnits first, then Directories, then Users (users reference OrgUnits by `oe_code`).

**Import behavior:**
- OrgUnits/Directories: validates all rows, aborts on any error
- Users: skips invalid rows (non-numeric IDs, unknown OrgUnit codes like `#NV`), imports valid ones
- Re-import is idempotent: existing records are updated, not duplicated
- Missing users (not in latest CSV) are deactivated, not deleted
- Users reappearing in a later import are automatically re-enabled

### Identity Model

- `thw_uid` — permanent person identifier (9-digit int), globally unique, never changes
- `username` — AD login name, can change (renamed in place on import)
- `oe_code` — 4-character OrgUnit code (e.g. `OAAC`), links users to OrgUnits
- `thw_realm` — which Active Directory the user belongs to: `HA` (Hauptamt) or `EA` (Ehrenamt)

## Development

### Run Tests

```bash
ddev exec vendor/bin/phpunit packages/thw_ovssp/Tests/Unit/
```

### Static Analysis

```bash
# PHPStan (level 9)
ddev exec vendor/bin/phpstan analyse -c packages/thw_ovssp/phpstan.neon

# PHP CS Fixer
ddev php vendor/bin/php-cs-fixer fix packages/thw_ovssp/ --dry-run --diff
```

### Code Coverage

```bash
ddev xdebug on
ddev exec XDEBUG_MODE=coverage php vendor/bin/phpunit \
  -c packages/thw_ovssp/Tests/phpunit.xml --coverage-text
ddev xdebug off
```

## File Structure

```
packages/thw_ovssp/
├── Classes/
│   ├── Controller/ImportController.php
│   ├── Domain/Model/ (OrgUnit, Directory, User)
│   ├── Domain/Repository/ (OrgUnit, Directory, User)
│   └── Service/ (ImportService, DirectoryNameService)
├── Configuration/
│   ├── Backend/Modules.php
│   ├── Extbase/Persistence/Classes.php
│   ├── Services.yaml
│   └── TCA/
├── Resources/Private/
│   ├── Language/ (DE primary, EN fallback)
│   ├── Partials/ (Navigation, Pagination)
│   └── Templates/
├── Tests/
│   ├── Fixtures/ (CSV test data)
│   └── Unit/ (33 tests)
├── ext_emconf.php
├── ext_tables.sql
└── phpstan.neon
```
