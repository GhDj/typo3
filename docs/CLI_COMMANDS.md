# OV-SSP CLI Commands

## ovssp:setup-demo

Creates a complete demo environment for development and testing in a single command.

### Usage

```bash
bin/typo3 ovssp:setup-demo [--base-url=URL]
```

### Options

| Option | Default | Description |
|---|---|---|
| `--base-url` | `/` | Site base URL (e.g., `https://thw-ovssp.ddev.site/`) |

### What it creates

| Step | Description |
|---|---|
| **Page tree** | Root page "THW OV-SSP" with child pages: Home, Portal, Login, Datenspeicher (SysFolder) |
| **Site configuration** | `config/sites/ov-ssp/config.yaml` with root page ID and site set dependency |
| **TypoScript** | `config/sites/ov-ssp/setup.typoscript` with page rendering and plugin configuration |
| **CSS assets** | Copies `portal.css` to `public/assets/ovssp/` |
| **fe_group** | "Portal Users" frontend user group |
| **OrgUnits** | 2 demo OrgUnits: OV Musterstadt (OAAC), OV Beispielburg (OAAD) |
| **Directories** | 3 demo directories: Allgemein, Verwaltung, Ausbildung |
| **Users** | 4 demo fe_users with proper password hashes (argon2id) |
| **Roles** | 2 demo roles: OV-User, OV-Leitung |
| **DirectoryRights** | 5 demo directory rights linking roles to directories |
| **Login plugin** | OV-SSP Login content element on the Login page |
| **Portal plugin** | OV-SSP Portal content element on the Portal page |
| **Home page** | Simple welcome content with link to login |

### Demo users

| Username | Password | Role | OE |
|---|---|---|---|
| `admin.test` | `Test1234!` | OV-Admin (portal_access=2) | OAAC |
| `helfer.eins` | `Test1234!` | User (portal_access=1) | OAAC |
| `helfer.zwei` | `Test1234!` | No access (portal_access=0) | OAAC |
| `helfer.drei` | `Test1234!` | User (portal_access=1) | OAAD |

### Idempotency

The command is safe to re-run. Each step checks if its data already exists and skips if so. The TypoScript and CSS files are always overwritten to apply updates.

### Example: fresh setup

```bash
bin/typo3 extension:setup          # Create database tables
bin/typo3 ovssp:setup-demo         # Create demo environment
bin/typo3 cache:flush              # Clear all caches
```

Then open `/login` in your browser and sign in with `admin.test` / `Test1234!`.

---

## ovssp:reset-demo

Deletes all demo data so `ovssp:setup-demo` can be re-run cleanly. Prompts for confirmation before executing.

### Usage

```bash
bin/typo3 ovssp:reset-demo
```

### What it deletes

| Step | Description |
|---|---|
| **Extension tables** | DROPs `tx_thwovssp_user_role_mm`, `tx_thwovssp_domain_model_directoryright`, `tx_thwovssp_domain_model_role`, `tx_thwovssp_domain_model_directory`, `tx_thwovssp_domain_model_orgunit` |
| **fe_users** | Deletes ALL fe_users records |
| **fe_groups** | Deletes the "Portal Users" group |
| **Content & pages** | Deletes all tt_content on demo pages, then deletes the page tree |
| **Site configuration** | Removes `config/sites/ov-ssp/` (config.yaml + setup.typoscript) |

### Important notes

- This command **drops tables entirely**. You must run `bin/typo3 extension:setup` after reset to recreate them before running `ovssp:setup-demo` again.
- The command deletes **all fe_users**, not just demo users. Do not run on environments with real user data.
- Requires interactive confirmation (type `yes` to proceed).

### Example: full reset and rebuild

```bash
bin/typo3 ovssp:reset-demo         # Delete everything (confirm with "yes")
bin/typo3 extension:setup          # Recreate database tables
bin/typo3 ovssp:setup-demo         # Recreate demo environment
bin/typo3 cache:flush              # Clear all caches
```
