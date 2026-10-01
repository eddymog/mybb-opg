# CLAUDE.md — One Piece Gaiden (OPG1)

## Project Overview

One Piece Gaiden is a Spanish-language roleplay forum built on **MyBB 1.8.36**. The core forum engine handles users, threads, and posts. On top of it, a fully custom RPG game system lives in `/op/` with character sheets, combat, crafting, training, economy, travel, and gacha mechanics.

## Tech Stack

- **Backend:** PHP (MySQLi), running on Apache with mod_rewrite
- **Forum engine:** MyBB 1.8.36 (class files in `/inc/`)
- **Database:** MySQL with `mybb_` table prefix. Custom game tables use `mybb_op_*` prefix
- **Frontend:** jQuery, SCEditor (rich text editor), custom JS modules in `/jscripts/`
- **External services:** Discord webhooks, VPN detection APIs (VPNApi.io, ProxyCheck.io, IPHub.info)

## Directory Structure

```
/                        → Root-level MyBB page scripts (index.php, showthread.php, etc.)
/op/                     → Custom game system (~90 PHP modules)
/op/staff/               → Staff/admin tools (~57 files)
/op/functions/            → Shared game functions (op_functions.php, security_monitor.php)
/opg/                    → Gacha/roll systems (cofres, akumas, haki)
/api/                    → REST API (JWT auth, 6 endpoints)
/inc/                    → MyBB core includes
/inc/plugins/            → MyBB plugins + 14 custom BBCode processors (BBCustom_*.php)
/inc/datahandlers/       → Data validation classes (post, user, PM, etc.)
/inc/cachehandlers/      → Cache backend implementations
/inc/db_*.php            → Database driver abstractions
/jscripts/               → Client-side JS (maps, cards, calculators, character sheets)
/images/                 → Static assets
/uploads/                → User uploads (gitignored)
/cache/                  → Template/data cache
/admin/                  → MyBB admin control panel
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

- `$mybb` — Core MyBB instance. `$mybb->user` has current user, `$mybb->input` has sanitized request data, `$mybb->settings` has board config
- `$db` — Database instance. Methods: `query()`, `simple_select()`, `fetch_array()`, `insert_query()`, `update_query()`, `write_query()`
- `$templates` — Template engine. `$templates->get('name')` returns template HTML
- `$plugins` — Plugin system. `$plugins->run_hooks('hook_name')` triggers hook callbacks
- `$lang` — Language system
- `$cache` — Data cache system

## Permission & Auth System

- `$mybb->user['uid']` — Current user ID (0 = guest)
- `is_staff($uid)` — Checks hardcoded UID whitelist + usergroup 4, 6, 14, 16 (defined in `op/functions/op_functions.php`)
- `is_narra($uid)` — Narrator role check (group 15 in `additionalgroups`)
- `is_mod($uid)` — Moderator check
- `is_user($uid)` — Admin check (UIDs 1, 3, 6, 7, 9)
- `does_ficha_exist($uid)` — Checks if user has an approved character
- CSRF protection via `generate_post_check()` / `verify_post_check()`

## Database Conventions

> Full schema with every column, index, and relationship is in **DATABASE.md**. The SQL dump is `rovddqmy_op.sql`.

- **69 custom game tables**, all prefixed `mybb_op_*`. Mixed InnoDB and MyISAM engines. No foreign key constraints — all relationships enforced by application code.
- Character ID (`fid`) equals user ID (`uid`) — one character per account
- Audit tables: `mybb_op_audit_*` for logging actions (general, stats, crafting, training, missions, rewards, etc.)
- **Two database triggers:**
  - `u_fichas_triggers` — AFTER UPDATE on `mybb_op_fichas` → snapshots stats/currencies/skills into `mybb_audit_op_fichas`
  - `u_users_triggers` — AFTER UPDATE on `mybb_users` → logs `newpoints` changes into `mybb_audit_users`
- Technique IDs: `D***` (defects), `V***` (virtues), `T***` (techniques)
- Custom item IDs: `{base_id}-{uid}-{count}` (e.g., `Sword-123-1`)
- JSON columns: `oficios`, `belicas`, `estilos`, `elementos`, `equipamiento` store skill trees and loadouts as native MySQL JSON
- Time-gated systems use `timestamp_end` (Unix timestamp) for training/crafting queues
- Currency columns on `mybb_op_fichas`: `berries`, `nika`, `kuro`, `puntos_oficio`, `puntos_estadistica`
- Custom columns on core MyBB tables: `mybb_posts.faccionsecreta` (secret faction override), `mybb_threads.prefix` (thread type: 3=Aventura, 10=MT, 1=Común, 6=Evento, 9=Autonarrada, 14=Requerimiento), `mybb_threads.year`/`estacion`/`day` (in-game date)

## Files/Directories to NEVER Modify

- `inc/config.php` — Database credentials (gitignored, never commit)
- `inc/class_core.php`, `inc/class_session.php`, `inc/class_plugins.php`, `inc/class_templates.php` — Core MyBB engine classes
- `inc/db_*.php` — Database drivers
- `inc/datahandlers/` — MyBB data handlers
- `cache/` — Auto-generated cache files
- `uploads/` — User-uploaded content
- `admin/` — MyBB admin panel (unless specifically asked)

## Files That Contain Secrets (Do Not Commit)

- `inc/config.php` — DB credentials
- `gemini_proxy.php` — Google Gemini API key (hardcoded)
- `api/index.php` — JWT secret (currently hardcoded as `'test'`)
- VPN API keys are embedded in `index.php`, `newthread.php`, and `op/ficha.php`

## Known Gotchas

1. **`howl.php` is 360KB** — Extremely large single file, be careful with full reads
2. **`member.php` is 98KB** — Heavily customized beyond standard MyBB
3. **`op/crafteo.php` has `THIS_SCRIPT = 'viajes.php'`** — Copy-paste error in the define, does not affect functionality
4. **SQL queries use string interpolation** — Not parameterized; be careful about SQL injection when modifying queries
5. **Templates use `eval()`** — This is standard MyBB behavior, not a bug
6. **JWT secret is `'test'`** in `/api/index.php` — Insecure, intended for development only
7. **CORS allows all origins** in the API — No domain restriction
8. **`op/db_extra.php`** connects to an external database at `bdopg.iceiy.com`

## Plugin System

Plugins register hooks via `$plugins->add_hook('hook_name', 'callback_function', $priority)`. Hooks fire at defined points in the request lifecycle (e.g., `global_start`, `index_start`, `parse_message_start`, `pre_output_page`).

Custom BBCode processors (`BBCustom_*.php`) hook into the parser to handle game-specific tags like `[ficha]`, `[dado]`, `[consumir]`, `[npc=ID]`, `[tecnica=TID]`, `[objeto=ID]`, etc.

## Common Tasks

### Adding a new game page in `/op/`
Follow the existing pattern: define `IN_MYBB`, require `global.php`, `config.php`, and `op_functions.php`. Use `$db` for queries and check `$mybb->user['uid']` for auth.

### Adding a new staff tool
Create a file in `/op/staff/`. Check `is_staff($uid)` at the top. Follow existing patterns in files like `objetos_crear.php` or `tecnicas_crear.php`.

### Adding a new BBCode
Create `inc/plugins/BBCustom_name.php`. Hook into `parse_message` or `parse_message_start`. Use regex to find/replace your tag in the post content. See `BBCustom_dado.php` (308 lines) for a clean example.

### Modifying character sheet data
The main character table is `mybb_op_fichas`. The display logic is in `op/ficha.php` (79KB). Creation logic is in `op/ficha_crear.php`. Stats are columns directly on the fichas table. **Warning:** Any UPDATE on `mybb_op_fichas` fires the `u_fichas_triggers` database trigger, which writes a snapshot row to `mybb_audit_op_fichas`.

### Working with the API
Endpoints are in `/api/`. Router is `api/index.php`. Auth uses JWT with `bearer_uid()` to get the current user. Responses are JSON.

### Understanding the database
See `DATABASE.md` for the complete schema with every column, type, default, index, and relationship. The source SQL dump is `rovddqmy_op.sql`.
