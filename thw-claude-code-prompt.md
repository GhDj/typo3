# THW Self-Service Permission Registry — TYPO3 Extension — Phase 1

## Context

We are building a new TYPO3 extension from scratch. A legacy internal tool covers part of
this ground today, but we are not porting it — its architecture is irrelevant here and
should not influence any decision you make.

The app is a **self-service permission registry for THW Ortsverbände (OV)**. Helper master
data is imported from a central CSV export of Active Directory and is **read-only** inside
the app. On top of that master data, local OV admins assign roles, which resolve into
effective directory rights, and there is a request/approval workflow (Anträge) for two
special authorization groups.

This is a **frontend application**: the people using it authenticate as TYPO3 `fe_users`
(later via AD FS / OIDC SSO). It is **not** a TYPO3 backend module. TYPO3 backend access
is for developers and central admins only.

## Environment

- TYPO3 **v14 LTS** (released April 2026), Composer-based install, already running locally
- DDEV, PHP 8.x, MariaDB
- Extension key: `thw_selfservice`
- Local package path: `packages/thw_selfservice` (path repository in root `composer.json`)
- PHP namespace: `Thw\ThwSelfservice` — replace the vendor part if the repo already has a
  convention; check first.

## Ground rules

1. **Do not rely on your training data for TYPO3 v14 APIs.** v14 is a very recent release
   with a reworked backend and changes to schema/TCA handling. Before writing any TCA,
   service registration, or Extbase configuration, read the actual installed core in
   `vendor/typo3/cms-core/` and `vendor/typo3/cms-extbase/`, plus any bundled docs. If a
   pattern you remember from v12/v13 no longer exists, follow what the installed core does.
2. **Small steps.** Phase 1 is the data layer for two entities only. Do not scaffold the
   rest of the domain. Stop at the end of the task list and report.
3. **Offline deployment constraint.** The production target has no internet access. No CDN
   assets, no runtime HTTP calls, nothing that needs Composer at deploy time.
4. **Language.** Code, class names, and DB columns in English. User-facing labels via XLIFF
   with `de` as the primary locale and `en` as fallback. Keep German domain nouns as domain
   vocabulary in comments where it aids clarity (Ortsverband, Antrag, Rolle, Kontingent).
5. **Ask before inventing business rules.** If something is ambiguous, write the question
   into the report instead of guessing.
6. Run `ddev` for all commands. Do not modify anything outside `packages/thw_selfservice`
   and the root `composer.json` without asking.
7. **Write TYPO3-native code, not framework-flavoured PHP.** Do not import idioms from
   Laravel, Symfony full-stack, or any admin-panel builder: no Eloquent-style models, no
   facades, no service providers, no fluent query builder invented on top of TCA, no
   attempt to auto-generate CRUD screens from a resource class. TYPO3 has its own way of
   doing all of this and it is the way we want:
   - Persistence: TCA + Extbase domain models and repositories, or Doctrine DBAL
     `ConnectionPool`/`QueryBuilder` where Extbase is the wrong tool.
   - Configuration: TCA arrays in `Configuration/TCA/`, `ext_tables.sql` for schema.
   - Labels: XLIFF, never hardcoded strings.
   If you catch yourself reaching for a pattern because it was convenient elsewhere, stop
   and find the TYPO3 equivalent.

## Symfony components

TYPO3 ships a set of Symfony components and using them is correct and encouraged — they
are the TYPO3-native way to do these things:

- **Dependency injection** — constructor injection, `Configuration/Services.yaml` with
  autowiring and autoconfiguration enabled.
- **Console** — CLI commands registered the TYPO3 way, run via `ddev typo3 <command>`.
  This is what the CSV importer will be in a later phase.
- **Event Dispatcher** — PSR-14 events and listeners for anything hook-shaped.

Before using any other Symfony component, check `composer.lock` and `vendor/symfony/` in
this install to confirm the core actually ships it. Do not add a new Symfony package as a
direct dependency without asking me first — the production target is offline and every
added dependency is a deployment cost.

## Full domain picture (orientation only — most of this is NOT in Phase 1)

- **User** — helper master data from CSV/AD: username, display name, THW UID (`UID-010004`),
  OE, birth date, active flag, email, last import, portal access flag.
- **OrganizationalUnit (OE / Ortsverband)** — e.g. `OLDS` = OV Landsberg, `OMUC` = OV München.
- **Directory** — a network share, scoped per OE. The same logical directory (`AGT`,
  `Allgemein`, `Ausbildung`, `BFD`, `BP-Elektro`) exists once per OE as its own record.
- **Role (Rolle)** — e.g. `OV-User`, group `standard`, "Pflichtrolle für alle aktiven
  Helfer". A role bundles directory rights.
- **DirectoryRight** — `read` / `write` / `deny` on a directory. Resolution rule stated in
  the UI: **write beats read, deny overrides everything.**
- **AuthorizationGroup** — `Fernzugang OV`, `Kaufhaus des Bundes (KdB)`. Requestable, not
  directly assignable.
- **Application (Antrag)** — type `assignment` or `revocation`, applicant, OE, approve/refuse.
- **HistoryEntry** — append-only audit log: timestamp, action, description, actor, OE.
- **Quota (Kontingent)** — per-OE seat limits, presumably on Fernzugang / KdB.

## Decisions already made — implement these, don't re-litigate

- **Users are extended `fe_users`, not a separate table.** Rationale: SSO will bind to
  `fe_users`, and the frontend auth stack works out of the box. Helpers who exist in the
  master data but have no portal access are still `fe_users` records, flagged via
  `thw_portal_access = 0` and `disable = 1`.
- **OE is its own table.** `fe_users` gets a single-select foreign key to it. We will
  mirror OEs into `fe_groups` later for frontend access control — not in this phase.
- **Master data fields are read-only in the TYPO3 backend.** The CSV importer is the only
  writer. Mark them `readOnly` in TCA so nobody edits them by hand and gets overwritten on
  the next import.

## Phase 1 task list

### 1.1 — Extension skeleton
Create `packages/thw_selfservice` with `composer.json`, `ext_emconf.php`, `ext_localconf.php`
(only if actually needed), `Configuration/`, `Classes/`, `Resources/Private/Language/`.
Wire it as a path repository and require it in the root `composer.json`.

### 1.2 — OrganizationalUnit
Table `tx_thwselfservice_domain_model_organizationalunit`:

| field | type | notes |
|---|---|---|
| `title` | varchar | `OV Landsberg` |
| `code` | varchar(16) | `OLDS`, unique index |
| `parent` | int | self-reference, nullable — future Landesverband hierarchy |
| `active` | tinyint | default 1 |

Plus the standard TYPO3 control fields (`uid`, `pid`, `tstamp`, `crdate`, `deleted`,
`hidden`, `sorting`). Full TCA, editable in the backend list module, XLIFF labels.

### 1.3 — `fe_users` extension
Via `Configuration/TCA/Overrides/fe_users.php` and additive `ext_tables.sql`:

| field | type | notes |
|---|---|---|
| `thw_uid` | varchar(32) | `UID-010004`, unique index, readOnly |
| `thw_ou` | int | FK to organizationalunit, select single, readOnly |
| `thw_birthdate` | date | readOnly |
| `thw_active` | tinyint | from CSV, readOnly |
| `thw_portal_access` | tinyint | default 0 |
| `thw_sso_subject` | varchar(255) | nullable, AD FS claim subject, unique when set |
| `thw_last_import` | datetime | nullable, readOnly |

Group them into a dedicated "THW" tab in the `fe_users` edit form. Do not remove or
repurpose existing `fe_users` fields — `username` will hold the AD `sAMAccountName`.

Leave a clear `// TODO Phase 2:` comment where the AD claim → CSV identifier matching will
plug in, since that mapping is still an open question on the project.

### 1.4 — Extbase models
- `Classes/Domain/Model/OrganizationalUnit.php` + repository
- `Classes/Domain/Model/User.php` extending the core `FrontendUser` model + repository
- Register the `fe_users` class mapping in `Configuration/Extbase/Persistence/Classes.php`
  (verify this is still the v14 mechanism before using it)

### 1.5 — Sanity check
Create two OEs (`OLDS`, `OMUC`) and one fe_user matching the screenshot data
(`bauer` / Maria Bauer / `UID-010004` / OLDS / 30.07.1995 / active) as a small
`Configuration/TCA` fixture or a documented manual step — your call, whichever is cleaner.

## Acceptance criteria

- `ddev composer install` and `ddev typo3 extension:setup` run clean
- Schema applies without errors; re-running the compare produces no pending changes
- OE records are creatable and editable in the backend list module, labels render in German
- `fe_users` edit form shows a THW tab with the new fields, master data fields non-editable
- Both Extbase repositories resolve and return records from a throwaway CLI command or test
- No deprecation entries in `var/log/`
- A `REPORT.md` in the extension root listing: what you built, every v14 API you had to
  verify against core, decisions you made, and open questions for me

## Explicitly out of scope for Phase 1

Roles, directory rights, authorization groups, Anträge workflow, history/audit log,
quotas, the CSV importer, OIDC/AD FS wiring, `fe_groups` mirroring, and any frontend
plugin, controller, or Fluid template. We will do those in later phases.