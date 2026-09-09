# Phase 1 Report — THW Self-Service Extension

## What was built

### 1.1 Extension skeleton
- `composer.json` with PSR-4 autoload for `Thw\ThwSelfservice`
- `ext_emconf.php` targeting TYPO3 14.x
- `Configuration/Services.yaml` with autowire + autoconfigure

### 1.2 OrganizationalUnit
- Table `tx_thwselfservice_domain_model_organizationalunit` with fields: `title`, `code` (unique), `parent` (self-reference), `active`
- Full TCA with backend list module editing, XLIFF labels in DE (primary) and EN (fallback)

### 1.3 fe_users extension
- Seven new fields added via TCA Override: `thw_uid`, `thw_ou`, `thw_birthdate`, `thw_active`, `thw_portal_access`, `thw_sso_subject`, `thw_last_import`
- Grouped in a dedicated "THW" tab
- Master data fields (thw_uid, thw_ou, thw_birthdate, thw_active, thw_last_import) marked `readOnly`
- `thw_portal_access` and `thw_sso_subject` are editable

### 1.4 Extbase models
- `OrganizationalUnit` model + repository
- `User` model extending `FrontendUser` + repository
- Persistence mapping in `Configuration/Extbase/Persistence/Classes.php`

## v14 API verification needed

> **Important:** These files were written based on known TYPO3 conventions. Before running
> `extension:setup`, verify the following against the actual installed v14 core:

1. **TCA `type` => `datetime`** — v14 may have changed the `format` sub-key or `dbType` handling. Check `vendor/typo3/cms-core/Configuration/TCA/` for examples.
2. **TCA `select` items format** — v14 uses `['label' => '...', 'value' => ...]` (not the old numeric array `[0 => 'label', 1 => 'value']`). Verify this is still current.
3. **`Configuration/Extbase/Persistence/Classes.php`** — confirm this is still the v14 mechanism for table mapping by checking `vendor/typo3/cms-extbase/`.
4. **`FrontendUser` model location** — verify `\TYPO3\CMS\Extbase\Domain\Model\FrontendUser` still exists in v14.
5. **`ext_tables.sql` implicit fields** — v14 may auto-create `uid`, `pid`, `tstamp`, `crdate`, `deleted`, `hidden`, `sorting`. If so, they should NOT be in `ext_tables.sql`. Check core extension examples.
6. **`readOnly` in TCA config** — verify this is still the correct key (not moved to `behaviour` or similar).

## Decisions made

- Read-only CSV fields on User model have getters only (no setters), except `thwPortalAccess` and `thwSsoSubject` which are editable
- OE `code` field uses `eval: trim,upper` to enforce uppercase codes
- `thw_sso_subject` has a unique index — empty strings may conflict; consider nullable column or conditional unique index if needed
- Sanity check data (OLDS, OMUC, bauer) documented as manual steps below

## Sanity check — manual test data

Create in the TYPO3 backend List module (any sysfolder page):

1. **OE record:** Title = "OV Landsberg", Code = "OLDS", Active = yes
2. **OE record:** Title = "OV München", Code = "OMUC", Active = yes
3. **fe_user record:** Username = "bauer", First Name = "Maria", Last Name = "Bauer", THW tab: THW UID = "UID-010004", OE = "OV Landsberg", Birth Date = 30.07.1995, Active (THW) = yes

## Open questions

1. **`thw_sso_subject` unique index on empty string:** If most users initially have an empty `thw_sso_subject`, the unique index on `''` will cause constraint violations. Options: (a) make the column truly nullable with `DEFAULT NULL`, (b) remove the unique index and enforce uniqueness in application logic, (c) use a partial/conditional index if MariaDB version supports it.
2. **OE `parent` field usage:** The spec says "future Landesverband hierarchy" — is the parent always another OE in the same table, or will there be a separate table for Landesverbände?
3. **`thw_birthdate` storage format:** TYPO3 `type => datetime` with `format => date` may store as Unix timestamp internally rather than SQL `date`. Need to verify against v14 core behavior and adjust `ext_tables.sql` column type if needed.

## Root composer.json changes needed

Add to your root `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/*"
        }
    ],
    "require": {
        "thw/thw-selfservice": "@dev"
    }
}
```

Then run: `ddev composer require thw/thw-selfservice:@dev`
