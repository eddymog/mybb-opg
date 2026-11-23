# AGENTS.md: One Piece Gaiden (OPG1)

## Project Overview

One Piece Gaiden is a Spanish-language roleplay forum built on **MyBB 1.8.36**. The core forum engine handles users, threads, and posts. On top of it, a fully custom RPG game system lives in `/op/` with character sheets, combat, crafting, training, economy, travel, and gacha mechanics.

## Tech Stack

- **Backend:** PHP (MySQLi), running on Apache with mod_rewrite
- **Forum engine:** MyBB 1.8.36 (class files in `/inc/`)
- **Database:** MySQL with `mybb_` table prefix. Custom game tables use `mybb_op_*` prefix
- **Frontend:** jQuery, SCEditor (rich text editor), custom JS modules in `/jscripts/`
- **External services:** Discord webhooks; the PunkRecords assistant (RAG over the guides, using Gemini, Supabase and Voyage, with keys in `inc/config.php` under `$config['punkrecords']`). VPN detection code is no longer in the repository (the `mybb_op_avisosvpn` table remains).

## Directory Structure

```
/                        → Root-level MyBB page scripts (index.php, showthread.php, member.php, etc.)
/op/                     → Custom game system (~116 PHP pages)
/op/personaje.php        → Main character sheet page (~196KB, with a large inline script). op/ficha.php is an obsolete 301 redirect to it
/op/staff/               → Staff/admin tools (~85 files)
/op/functions/           → Shared game functions (op_functions.php, security_monitor.php, punkrecords_*.php)
/op/punkrecords/         → Guides (Markdown) indexed by the PunkRecords assistant
/op/legacy/              → Old one-off migration scripts
/opg/                    → Gacha/roll systems (cofres, akumas, haki, rey)
/api/                    → REST API: router (index.php) plus auth, ficha, threads, thread, new_reply (JWT auth)
/inc/                    → MyBB core includes
/inc/plugins/            → MyBB plugins (op_*, opg_*, rt_*, newpoints, accountswitcher...) + 16 custom BBCode processors (BBCustom_*.php)
/inc/plugins/lib/        → Shared feature libraries (aventuras*, halloween2026*, periodico, panel_tactico, haki_canje, elemento_canje, arma_canje, ...)
/inc/datahandlers/       → Data validation classes (post, user, PM, etc.)
/inc/cachehandlers/      → Cache backend implementations
/inc/db_*.php            → Database driver abstractions
/jscripts/               → Client-side JS (maps, cards, calculators, character sheet modules in jscripts/ficha/)
/templates/              → Local copies of MyBB templates and stylesheets (see "Templates" in Working Conventions)
/tests/                  → Plain PHP/JS test scripts, one folder per area
/docs/                   → Requirements, design plans, guides and SQL migrations (gitignored, local only)
/images/                 → Static assets
/uploads/                → User uploads (gitignored)
/cache/                  → Template/data cache
/admin/                  → MyBB admin control panel
/archive/, /work/        → Old snapshots and scratch material, not part of the running site
```

## Request Lifecycle

Every page follows this flow:

1. Page script defines `IN_MYBB`, `THIS_SCRIPT`, and `$templatelist`
2. Includes `./global.php` → which includes `inc/init.php`
3. `init.php` bootstraps: error handler → core MyBB class → config → database → templates → cache → plugins → language
4. `global.php` initializes session via `$session->init()`, sets CSRF token via `$mybb->post_code`
5. Page logic runs using globals: `$mybb`, `$db`, `$cache`, `$templates`, `$plugins`, `$lang`
6. Templates rendered via `eval('$var = "'.$templates->get('template_name').'";')`
7. `output_page()` sends response, shutdown functions execute

Game pages in `/op/` follow a similar but simpler pattern:
```php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'filename.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
```

## Key Globals

- `$mybb`: Core MyBB instance. `$mybb->user` has current user, `$mybb->input` has sanitized request data, `$mybb->settings` has board config
- `$db`: Database instance. Methods: `query()`, `simple_select()`, `fetch_array()`, `insert_query()`, `update_query()`, `write_query()`
- `$templates`: Template engine. `$templates->get('name')` returns template HTML
- `$plugins`: Plugin system. `$plugins->run_hooks('hook_name')` triggers hook callbacks
- `$lang`: Language system
- `$cache`: Data cache system

## Permission & Auth System

- `$mybb->user['uid']`: Current user ID (0 = guest)
- `is_staff($uid)`: Checks hardcoded UID whitelist + usergroup 4, 6, 14, 16 (defined in `op/functions/op_functions.php`)
- `is_narra($uid)`: Narrator role check (group 15 in `additionalgroups`)
- `is_mod($uid)`: Moderator check
- `is_user($uid)`: Admin check (UIDs 1, 3, 6, 7, 9)
- `does_ficha_exist($uid)`: Checks if user has an approved character
- CSRF protection via `generate_post_check()` / `verify_post_check()`

## Database Conventions

> Full schema with every column, index, and relationship is in **DATABASE.md**. The SQL dump is `rovddqmy_op.sql`.

- **72 `mybb_op_*` tables in the SQL dump** (`rovddqmy_op.sql`, a March 2026 schema). Newer features add tables through the migrations in `docs/*.sql` (aventuras, halloween2026, periodico, panel_tactico, bitacora, tripulaciones, punkrecords, temas_tracker, afiliados, cofres_repartos...). Mixed InnoDB and MyISAM engines. No foreign key constraints: all relationships enforced by application code.
- Character ID (`fid`) equals user ID (`uid`): one character per account
- Audit tables: `mybb_op_audit_*` for logging actions (general, stats, crafting, training, missions, rewards, etc.)
- **Two database triggers:**
  - `u_fichas_triggers`: AFTER UPDATE on `mybb_op_fichas` → snapshots stats/currencies/skills into `mybb_audit_op_fichas`
  - `u_users_triggers`: AFTER UPDATE on `mybb_users` → logs `newpoints` changes into `mybb_audit_users`
- Technique IDs: `D***` (defects), `V***` (virtues), `T***` (techniques)
- Custom item IDs: `{base_id}-{uid}-{count}` (e.g., `Sword-123-1`)
- JSON columns: `oficios`, `belicas`, `estilos`, `elementos`, `equipamiento` store skill trees and loadouts as native MySQL JSON
- Time-gated systems use `timestamp_end` (Unix timestamp) for training/crafting queues
- Currency columns on `mybb_op_fichas`: `berries`, `nika`, `kuro`, `puntos_oficio`, `puntos_estadistica`
- Custom columns on core MyBB tables: `mybb_posts.faccionsecreta` (secret faction override), `mybb_threads.prefix` (thread type: 3=Aventura, 10=MT, 1=Común, 6=Evento, 9=Autonarrada, 14=Requerimiento), `mybb_threads.year`/`estacion`/`day` (in-game date)

## Files/Directories to NEVER Modify

- `inc/config.php`: Database credentials (gitignored, never commit)
- `inc/class_core.php`, `inc/class_session.php`, `inc/class_plugins.php`, `inc/class_templates.php`: Core MyBB engine classes
- `inc/db_*.php`: Database drivers
- `inc/datahandlers/`: MyBB data handlers
- `cache/`: Auto-generated cache files
- `uploads/`: User-uploaded content
- `admin/`: MyBB admin panel (unless specifically asked)

## Files That Contain Secrets (Do Not Commit)

- `inc/config.php`: DB credentials
- `$config['punkrecords'][...]` in `inc/config.php`: Gemini, Supabase and Voyage keys for the assistant
- `api/index.php`: JWT secret (currently hardcoded as `'test'`)
- The production server login (SSH) and the database password: never print or store them (see Production Access)

## Known Gotchas

1. **`op/personaje.php` is ~196KB**: the character sheet page, mixing PHP, AJAX endpoints and a large inline script. Read it by sections. Its template (`op_personaje.html`) embeds data in JavaScript, so see the JSON rule in Working Conventions.
2. **`op/ficha.php` is an obsolete 301 redirect** to `op/personaje.php`. Do not add logic there.
3. **`member.php` is ~100KB**: heavily customized beyond standard MyBB.
4. **`op/crafteo.php` has `THIS_SCRIPT = 'viajes.php'`**: copy-paste error in the define, does not affect functionality.
5. **SQL queries use string interpolation**: not parameterized; escape or validate every value when modifying queries.
6. **Templates use `eval()`**: standard MyBB behavior, not a bug.
7. **JWT secret is `'test'`** in `/api/index.php`: insecure, intended for development only.
8. **CORS reflects any `Origin`** in the API: no domain restriction.
9. **`op/db_extra.php`** connects to an external database at `bdopg.iceiy.com`.
10. **Production and templates are separate from the repo**: PHP files go to the server with the deploy the user already uses, templates reach the database through `template_sync.php` or the import script, and SQL migrations are run by the user.

## Plugin System

Plugins register hooks via `$plugins->add_hook('hook_name', 'callback_function', $priority)`. Hooks fire at defined points in the request lifecycle (e.g., `global_start`, `index_start`, `parse_message_start`, `pre_output_page`).

Custom BBCode processors (`BBCustom_*.php`, 16 of them) hook into the parser to handle game-specific tags like `[ficha]`, `[dado]`, `[consumir]`, `[npc=ID]`, `[tecnica=TID]`, `[objeto=ID]`, `[tiempo=horas]`, etc.

Feature plugins follow the `op_*` / `opg_*` naming and keep their logic in `inc/plugins/lib/`: `op_bitacora`, `op_temas_tracker`, `op_modo_espejo`, `op_niveles`, `op_fama`, `op_facciones`, `op_solicitudes_creacion`, `opg_halloween2026`, `opg_panel_tactico`, plus the chat (`rt_chat`) and the NewPoints currency plugin.

## Main Feature Areas

Where the larger systems live, so a task can start in the right place.

| Area | Main files | Notes |
|---|---|---|
| Aventuras (tablón and console) | `op/tablon_de_aventuras.php`, `inc/plugins/lib/aventuras*.php`, `inc/plugins/lib/recompensas_aventura.php` | Has real-MySQL tests in `tests/aventuras/`; its own notifications table |
| Halloween 2026 event | `op/halloween2026.php`, `inc/plugins/opg_halloween2026.php`, `inc/plugins/lib/halloween2026*.php`, `op/ajuste_indice.php`, `op/descubrir.php` | Hidden-secrets system with anti-cheat; operations guide in `docs/106_Operacion_Halloween.md` |
| Periódico | `op/periodico.php`, `inc/plugins/lib/periodico.php`, `op/staff/periodico*.php` | Newspaper editions and editor |
| Panel táctico | `op/panel_tactico.php`, `inc/plugins/opg_panel_tactico.php`, `inc/plugins/lib/panel_tactico.php` | Combat helper panel |
| Bitácora and thread tracking | `inc/plugins/op_bitacora.php`, `inc/plugins/op_temas_tracker.php` | `op_temas_tracker.php` is the model for header hooks |
| Item redemptions from the sheet inventory | `inc/plugins/lib/haki_canje.php`, `elemento_canje.php`, `arma_canje.php`; endpoints `canjear_haki`, `canjear_elemento`, `canjear_arma` in `op/personaje.php`; button in `op_ficha_inventario.html` | Consume one item atomically, apply the reward, refund on failure; tests in `tests/*_canje/` |
| Resurrection (character farewell) | `op/staff/resurreciones.php`, guide draft `docs/Guia_Resurreccion_BBCode.md` | The tool still has the old 5-level table; the new points, Legado and cooldown design is not implemented yet |
| Notifications | `docs/108_Requirements_Notificaciones.md`, `docs/208_DesignPlan_Notificaciones.md` | Design only; nothing global is implemented. The Tablón has its own table (`mybb_op_aventuras_notificaciones`) |
| PunkRecords assistant | `op/punkrecords.php`, `op/functions/punkrecords_*.php`, `op/punkrecords/guias/` | RAG over the official guides; keys in `inc/config.php` |
| Other libraries | `tecnicas_cambios.php`, `xp_snapshot.php`, `banda_sonora.php`, `belico_calculos.php`, `spoiler.php` | In `inc/plugins/lib/` |

## Common Tasks

### Adding a new game page in `/op/`
Follow the existing pattern: define `IN_MYBB`, require `global.php`, `config.php`, and `op_functions.php`. Use `$db` for queries and check `$mybb->user['uid']` for auth.

### Adding a new staff tool
Create a file in `/op/staff/`. Check `is_staff($uid)` at the top. Follow existing patterns in files like `objetos_crear.php` or `tecnicas_crear.php`.

### Adding a new BBCode
Create `inc/plugins/BBCustom_name.php`. Hook into `parse_message` or `parse_message_start`. Use regex to find/replace your tag in the post content. See `BBCustom_dado.php` (308 lines) for a clean example.

### Modifying character sheet data
The main character table is `mybb_op_fichas`. The display logic and the sheet's AJAX actions are in `op/personaje.php`, with its JavaScript modules in `jscripts/ficha/`. Creation logic is in `op/ficha_crear.php`. Stats are columns directly on the fichas table. **Warning:** Any UPDATE on `mybb_op_fichas` fires the `u_fichas_triggers` database trigger, which writes a snapshot row to `mybb_audit_op_fichas`.

### Working with the API
Endpoints are in `/api/`. Router is `api/index.php`. Auth uses JWT with `bearer_uid()` to get the current user. Responses are JSON.

### Adding an item redemption (canje) to the sheet inventory
Copy the pattern of `haki_canje`, `elemento_canje` or `arma_canje`: a pure library in `inc/plugins/lib/` with its test, an AJAX action in `op/personaje.php` (CSRF check, atomic consumption, refund on failure, audit entry), a modal built in PHP, and a button in `templates/One_Piece_Gaiden_Templates/op_ficha_inventario.html` (remember the template must be uploaded to MyBB).

### Adding a feature library
Put the logic in `inc/plugins/lib/<feature>.php` as pure functions where possible, add `tests/<feature>/` in the same change, keep the page or plugin thin, and add a migration in `docs/<feature>_migration.sql` if it needs tables.

### Understanding the database
See `DATABASE.md` for the complete schema with every column, type, default, index, and relationship. The source SQL dump is `rovddqmy_op.sql`.

## Agent Team and Process

Work on this project follows a small team model, whether it is done by one agent or several.

- Roles live in `docs/agents/roles/`: `orchestrator.md`, `program-manager.md`, `game-designer.md`, `software-developer.md` and `qa-tester.md`. The Orchestrator organizes `process.md` and the other roles. The Game Designer owns game rules and player guides and only joins features that include game rules.
- The end-to-end workflow (requirements, design, GitHub issues, development loop, QA gate, release) is in `docs/agents/process.md`.
- Every agent reads this file first, then its own role file, then `docs/agents/context.md` and `docs/agents/DATABASE.md` when the task touches architecture or data, then the documents of the feature it works on.

### Tasks are GitHub issues

- Tasks are GitHub issues, one at a time.
- Read the acceptance criteria before starting and before closing.
- Commit regularly: small commits, tests passing, issue number in the message (`Área: what was done (#n)`).
- Only the QA role approves, and an issue is closed only after approval. Do not push or merge to the main branch unless the user authorizes it.
- Every issue has a lane (trivial, normal or large) that sets how much of the process applies, and a rollback plan when it touches templates, SQL or data. See `docs/agents/process.md`.
- Issue template, labels and `gh` commands are in `docs/agents/process.md`. The repository is `eddymog/mybb-opg`.

## Working Conventions

- **Language:** user-facing text and documents are in neutral Spanish (never voseo: write "usar/revisar", not "usá/revisá"). Code identifiers stay as they are in the codebase.
- **Punctuation:** never use em dashes in any written output. Use commas, periods, parentheses or restructure the sentence.
- **End of turn:** whenever a turn creates, edits or deletes files, close with the list of changed files.
- **Never modify `global.php`.** Add behavior with a plugin hook (for example `global_intermediate`). The model to copy is `inc/plugins/op_temas_tracker.php`.
- **Templates:** the `.html` files in `templates/One_Piece_Gaiden_Templates/` are reference copies. MyBB renders templates from the database. The plugin `inc/plugins/template_sync.php` writes templates to disk on admin saves and imports files uploaded to that folder by FTP into the database (about every 10 seconds), and `export_templates.php` / `import_templates.php` do the same on demand. In practice the user must upload or import every changed template. Say so explicitly after changing one. (`op_halloween2026.html` is the exception: it is read from the file at runtime.)
- **Docs:** `/docs/` is gitignored (local only). Numbering: `1xx` requirements, `2xx` design plans, `3xx` implementation plans, plus operational guides and SQL migrations. The agent handbook (this file, `CLAUDE.md`, `process.md`, `roles/`, `context.md`, `DATABASE.md`) lives in `docs/agents/`. Because `docs/` is not in git, put acceptance criteria in the GitHub issue itself.
- **SQL migrations:** idempotent files in `docs/*.sql`. Agents write them; the user runs them. Agents never run a migration against production.
- **Shared logic goes in libraries** under `inc/plugins/lib/` (pure functions where possible) so it can be tested without a database. Page scripts and plugins stay thin.
- **Embedding JSON in templates:** when a template wraps JSON in a JavaScript template literal (a `JSON.parse` call with the data between backticks), pass the data through `op_json_para_plantilla_js()` (see `op/personaje.php`). A plain `json_encode` breaks on quotes, backslashes and backticks.

## Testing

- **The green gate is `sh tests/run_all.sh`.** It checks that no forbidden file was touched, runs `php -l` on changed PHP files, scans added lines for credentials, warns about em dashes, and runs every test that does not need a database. `--db` adds the real-MySQL tests, `--rapido` skips the tests, `--ayuda` lists the options. Work is not ready for review until it ends in VERDE.
- `tests/known_failures.txt` lists tests that already failed before the gate existed. They show as a known failure and do not block, but never add to that list to get around a new failure without the user's explicit permission.
- Tests are plain PHP scripts in `tests/<area>/`; run them with `php tests/<area>/<file>.php`. A passing script exits with status 0 (most also print an `OK ...` line); a failing one prints the failures and exits with a non-zero status.
- Check syntax of every PHP file you touch with `php -l <file>`.
- New pure logic gets a test in `tests/<area>/` in the same change (examples: `tests/haki_canje/`, `tests/elemento_canje/`, `tests/arma_canje/`).
- Some areas have their own runner, for example `tests/banda_sonora/run.sh`. Look inside the area folder first.
- Anything that depends on real MySQL behavior (`affected_rows`, concurrency, transactions) needs a real database. A throwaway MySQL 5.7 container (the production version) is already set up for the Tablón tests: see the header of `tests/aventuras/bootstrap_db.php` for the exact `docker run` command (port 33307, `--tmpfs` data, no production data), run `php tests/aventuras/<file>.php`, then remove the container. New database tests should reuse that bootstrap. Never test against production.

## Security Rules for New Code

- State-changing AJAX actions verify the CSRF key (`verify_post_check`) and take the user from the session (`$mybb->user['uid']`), never from the request.
- Validate every choice against a fixed list before it reaches a query. Escape with `$db->escape_string()` anything that is not provably safe.
- Escape all output (`htmlspecialchars`). Never store pre-rendered HTML in the database.
- Consuming or granting items, currencies or levels must be atomic (`UPDATE ... SET x = x - 1 WHERE x > 0`, then check `affected_rows`) and undo the consumption if the later step fails.
- Write an audit entry (`log_audit`) for actions that change a character.

## Production Access

- The production database is only reachable through SSH to the hosting account; port forwarding is blocked. Run read-only PHP scripts on the server by piping them to `ssh <account> php`, and let them load the server's own `inc/config.php`.
- Read-only means `SELECT` only. Never write to production without explicit approval from the user for that specific action.
- Never print, log, store or commit credentials. Do not hand production access to automated loops or sub-agents. They use a local throwaway database instead.
