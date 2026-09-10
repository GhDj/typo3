# Phase 1 Report — THW OV-SSP Extension

## What was built

### 1.1 Extension skeleton
- `composer.json` with PSR-4 autoload for `Thw\ThwSso`
- `ext_emconf.php` targeting TYPO3 14.x
- `Configuration/Services.yaml` with autowire, autoconfigure, and console command registration

### 1.2 OrgUnit table (`tx_thwsso_domain_model_orgunit`)
- Fields: `thw_oe_uid` (unique, immutable), `oe_code` (unique, 4-char), `name`, `mail_address`, `regionalbereich_code` (indexed), `landesverband_code` (indexed), `active`
- Full TCA, editable in backend list module
- `thw_oe_uid` and `oe_code` marked readOnly — only the importer writes them
- RB and LV stored as plain 4-char codes with indexes for filtering

### 1.3 Directory table (`tx_thwsso_domain_model_directory`)
- Fields: `name` (unique), `allows_read`, `allows_write`, `sort_key` (nullable), `description`
- TCA default sort: `sort_key ASC, name ASC`
- Fully editable in backend — imported once, then maintained by hand
- Global table (~30 rows), not materialised per OE

### 1.4 fe_users extension
- Five new fields: `thw_uid` (int, unique), `thw_orgunit` (FK), `thw_realm` (HA/EA), `thw_last_import`, `thw_import_missing_since`
- Grouped in a "THW" tab, all readOnly
- Composite unique index on `(thw_realm, username)`
- Uses core `username`, `first_name`, `last_name` — no duplication

### 1.5 Extbase models and repositories
- `OrgUnit`, `Directory`, `User` (extends `FrontendUser`)
- Persistence mapping for User → `fe_users` in `Configuration/Extbase/Persistence/Classes.php`
- All repositories extend `\TYPO3\CMS\Extbase\Persistence\Repository`

### 1.6 CSV importer (`sso:import`)
- Symfony Console command: `ddev typo3 sso:import --type=users|orgunits|directories --file=<path> [--realm=HA|EA] [--dry-run] [--pid=N]`
- **OrgUnit import:** validates header and `thw_oe_uid` format, upserts by `thw_oe_uid`, reports created/updated/unchanged
- **Directory import:** validates header, upserts by `name`, handles nullable `sort_key`
- **User import (performance-critical):**
  - Two-pass: full validation first, then batched upserts (500 rows per batch)
  - `INSERT … ON DUPLICATE KEY UPDATE` on the `thw_uid` unique index
  - Streams CSV line-by-line, never loads entire file into memory
  - Pre-loads OrgUnit map (`thw_oe_uid` → TYPO3 `uid`) for FK resolution
  - Handles BOM, semicolon delimiter, UTF-8
  - Deactivation: after upsert, sets `disable = 1` and `thw_import_missing_since` on users whose `thw_last_import` is older than the current run; re-enables users who reappear
  - New users get `password = '!'` (invalid hash — login only via OIDC)
  - `--dry-run` reports would-create / would-update / would-deactivate counts
- Validates before writing: rejects entire run on header mismatch, non-numeric IDs, or unknown OrgUnit references

### 1.6a DirectoryNameService
- Single-point derivation: `{oe_code}-{directory_name}` (e.g. `OAAC-Allgemein`)

### 1.7 Fixtures
- `Tests/Fixtures/` directory ready for the three CSV files
- Place the CSVs there and run:
  ```
  ddev typo3 sso:import -t orgunits -f packages/thw_sso/Tests/Fixtures/20260907_Testdaten_OV-SSP_OrgUnits_V2.csv --pid=1
  ddev typo3 sso:import -t directories -f packages/thw_sso/Tests/Fixtures/20260907_Grunddaten_OV-SSP_Verzeichnisse_Master.csv --pid=1
  ddev typo3 sso:import -t users -f packages/thw_sso/Tests/Fixtures/20260907_Testdaten_OV-SSP_User_V2.csv --realm=EA --pid=1
  ```

## v14 API verification needed

> Before running `extension:setup`, verify against the installed v14 core:

1. **TCA `type` => `number`** — v14 introduced `type: number` replacing `type: input` + `eval: int`. Verify in `vendor/typo3/cms-core/Configuration/TCA/`.
2. **TCA `type` => `email`** — v14 has a dedicated email type. Verify it exists.
3. **TCA `type` => `datetime`** — check if `format` key is needed and whether storage is int or native datetime.
4. **TCA select `items` format** — using `['label' => '...', 'value' => ...]`. Confirm this is v14 format (not the old numeric array).
5. **`Configuration/Extbase/Persistence/Classes.php`** — confirm this is still the v14 persistence mapping mechanism.
6. **`FrontendUser` model** — verify `\TYPO3\CMS\Extbase\Domain\Model\FrontendUser` still exists and check which properties it exposes.
7. **Console command registration** — verify `console.command` tag in `Services.yaml` is still the mechanism (vs `Configuration/Commands.php` or `#[AsCommand]` attribute).
8. **`ext_tables.sql` control fields** — v14 may auto-create `uid`, `pid`, `tstamp`, `crdate`, `deleted`, `hidden`, `sorting` from TCA `ctrl`. If so, remove them from `ext_tables.sql` to avoid schema compare diffs.
9. **`readOnly` in TCA config** — confirm this key is still at the column config level.
10. **`ConnectionPool` / `Connection`** — verify the DBAL API hasn't changed in v14.

## Decisions made

1. **OrgUnit import is row-by-row** (~669 rows) — DBAL `insert()`/`update()` is fine at this scale.
2. **User import uses raw SQL upsert** — batched `INSERT … ON DUPLICATE KEY UPDATE` for 91k-row performance.
3. **Password for imported users** is `'!'` — an invalid hash that cannot match any login attempt; OIDC handles auth.
4. **Deactivation uses `thw_last_import` timestamp** — after processing all CSV rows, any user in the same realm with an older `thw_last_import` is marked `disable = 1`. `thw_import_missing_since` preserves when the user first went missing (uses `COALESCE` to not overwrite).
5. **Import order matters** — OrgUnits must be imported before Users (the user command validates FK references).
6. **`thw_uid = 0` records are ignored** in deactivation query (WHERE `thw_uid > 0`) to avoid touching non-imported fe_users.

## Import timings

> Not yet measured — need the CSV fixtures and a running DDEV instance. Expected: 91k user import should complete in under 30 seconds with batched upserts (network-local MariaDB, 500-row batches = ~182 statements).

## Open questions

1. **No status/active column in user CSV.** Is absence from the file really the only deactivation signal? How long should disabled accounts be retained before hard deletion (if ever)?

2. **Email and birth date** appeared in the mock UI but are absent from the real CSV. Are they coming in a future CSV revision, or does the UI drop them?

3. **HA vs EA as separate files.** Are they always delivered as two separate files with identical schema? Can the same `thw_uid` appear in both (a person who is both hauptamtlich and ehrenamtlich)?

4. **OIDC claim for identity binding.** Which claim carries `thw_uid` or `username`? This determines how a login session binds to an imported record.

5. **Regionalbereich / Landesverband names.** Will master data (display names) for RB and LV be supplied, or do we only ever have the 4-char codes? If names come later, a lookup table will be needed.

6. **`lesen`/`schreiben` semantics.** Confirm these flags mean "a corresponding AD permission group exists for this directory" rather than a default grant to all users.

7. **Composite unique index `(thw_realm, username)`.** If a person exists in both HA and EA with the same username, they would be two separate fe_users records. Is that the intended behaviour?

8. **Storage PID for imported records.** The importer defaults to pid=0. What sysfolder should be used in production?
