# context.md: Technical Reference

## System Architecture

The application is a MyBB 1.8.36 forum with a custom RPG layer. Two codebases coexist:

1. **MyBB core** (`/inc/`, root-level PHP files): handles users, sessions, threads, posts, templates, plugins, caching
2. **Game system** (`/op/`, `/opg/`, `BBCustom_*.php` plugins): handles characters, combat, items, training, economy, travel

They share the same MySQL database and the same user session. Game pages include MyBB's `global.php` to bootstrap the full framework, then use `$db` and `$mybb->user` directly.

## How Major Systems Connect

```
User Request
    │
    ▼
Page Script (e.g., op/personaje.php)
    │
    ├── global.php ──► inc/init.php (bootstrap)
    │                     ├── class_core.php     ($mybb)
    │                     ├── db_mysqli.php       ($db)
    │                     ├── class_templates.php ($templates)
    │                     ├── class_datacache.php ($cache)
    │                     ├── class_plugins.php   ($plugins)
    │                     └── class_session.php   ($session)
    │
    ├── inc/config.php (DB credentials)
    ├── op/functions/op_functions.php (game helpers)
    │
    ▼
Game Logic
    ├── $db->query() / simple_select() ──► mybb_op_* tables
    ├── $mybb->user['uid'] ──► auth/permission checks
    ├── is_staff() / is_narra() ──► role gates
    └── output_page() or JSON response
```

### Character Sheet Flow
1. User creates character via `op/ficha_crear.php` → inserts into `mybb_op_fichas`
2. Staff approves via `op/staff/fichas_en_cola.php` → sets `aprobada_por` field (values `sin_aprobar` and `pendiente_reset` block upgrades and item redemptions)
3. Character displayed via `op/personaje.php?uid=X` (the old `op/ficha.php` is only a 301 redirect to it) → reads `mybb_op_fichas`, `mybb_op_inventario`, `mybb_op_virtudes_usuarios`, `mybb_op_tec_aprendidas`, `mybb_op_akumas` and the audit history. Its template is `op_personaje.html`, with sub-templates such as `op_ficha_inventario.html`, and its JS modules live in `jscripts/ficha/`
4. The same file serves the sheet's AJAX actions (all owner-only, answered as JSON): upgrades (`kenbun`, `buso`, `hao`, `control_akuma`, `limite_nivel`, styles, disciplines, trades), `ticket_reset` (build reset petition), and the item redemptions `canjear_haki`, `canjear_elemento` and `canjear_arma`
5. `[ficha]` BBCode in posts renders inline character data via `inc/plugins/BBCustom_ficha.php`
6. Data embedded in the page script (history lists, catalogs) goes through `op_json_para_plantilla_js()` because it is placed inside a JavaScript template literal

### Combat Flow
1. Players post in combat threads using `[personaje]`, `[ficha]`, `[npc=X]` markers
2. `op/api_combate.php` (JSON API) reads thread posts and extracts combat participants
3. Key functions: `getCombatData()`, `getCharacterData()`, `getMyTechniques()`, `getMyWeapons()`, `checkCombatReady()`
4. Damage and roll calculations handled server-side
5. Dice rolls via `[XdY]` BBCode processed by `BBCustom_dado.php`, results stored in `mybb_op_dados`

### Economy Flow
- Currency stored on `mybb_op_fichas`: `berries` (primary), `nika` (secondary), `kuro` (shop), `puntos_oficio` (craft points)
- `newpoints` on `mybb_users` tracks experience/role points via the NewPoints plugin
- Shops: `op/tienda.php`, `op/tienda_kuros.php`, `op/mercado_negro.php`
- Trading between players: `op/intercambio.php` → `mybb_op_intercambios`
- All currency changes logged via `log_audit_currency()` in `op_functions.php`

### Training & Progression Flow
- Stat training: `op/entrenamiento.php` → `mybb_op_entrenamientos_usuarios` (time-gated)
- Technique training: `op/entrenamiento_tecnicas.php` → `mybb_op_tecnicas_usuarios` (time-gated with `timestamp_end`)
- Upon completion, passive stat bonuses applied to `mybb_op_fichas` (e.g., `fuerza_pasiva += 5`)
- Crafting: `op/crafteo.php` → `mybb_op_crafteo_usuarios` (also time-gated)
- Experience limits per tier: `mybb_op_experiencia_limite`

### Item/Object System
- Item definitions: `mybb_op_objetos`: 30+ columns including `objeto_id`, `nombre`, `categoria`, `subcategoria`, `tier`, `comerciable`, `negro`, `negro_berries`, `berries`, `berriesCrafteo`, `dano`, `bloqueo`, `alcance`, `efecto`, `espacios`, `imagen`, `custom`, `editable`, `fusion_tipo`, `engastes`, `oficio`, `nivel`, `tiempo_creacion`, `requisitos`, `escalado`
- User inventory: `mybb_op_inventario`: unique key on `(objeto_id, uid)`. Columns: `objeto_id`, `uid`, `cantidad`, `imagen`, `apodo`, `autor`, `autor_uid`, `oficios` (JSON), `especial`, `editado`, `usado`, `vendidoReciente`
- Custom items created by users get ID format: `{base_id}-{uid}-{count}` with `custom=1` in `mybb_op_objetos`
- Equipment snapshots per combat thread: `mybb_op_equipamiento_personaje`: stores `equipamiento` as JSON keyed by `(tid, pid, uid)`
- Crafting recipe unlocks: `mybb_op_inventario_crafteo`: tracks `objeto_id`, `nombre`, `uid`, `desbloqueado`
- Item consumption via `[consumir=ID]` BBCode → `BBCustom_consumir.php` → stored in `mybb_op_consumir` with `(tid, pid, uid, counter, objeto_id, content)`
- Underworld auction house: `mybb_Objetos_Inframundo`: bid/buy-now system with `estado` enum (activa, vendida, cancelada, finalizada_sin_venta)

## Feature Systems Added in 2026

Each one keeps its logic in `inc/plugins/lib/`, its pages in `op/`, its tables in a migration under `docs/`, and its design in `docs/1xx`, `2xx` and `3xx` documents.

### Aventuras (tablón and console)
- **Pages:** `op/tablon_de_aventuras.php` (users and narrators) and the staff console. Libraries: `aventuras.php`, `aventuras_entregas.php`, `aventuras_vistas.php`, `aventuras_vistas_consola.php`, `recompensas_aventura.php`.
- **Flow:** a user opens an adventure request (`mybb_op_peticionAventuras`), narrators offer or are proposed, staff approves, the adventure runs in a thread (`mybb_threads.prefix` 3), the narrator delivers (`mybb_op_aventuras_entregas`) and rewards are paid (`..._entregas_pagos`). Self-narrated adventures have their own table (`mybb_op_aventuras_autonarradas`).
- **Notifications:** its own table, `mybb_op_aventuras_notificaciones` (a global system is only designed, see below).
- **Tests:** `tests/aventuras/` includes real-MySQL tests.

### Halloween 2026 event
- **Pieces:** plugin `inc/plugins/opg_halloween2026.php`, libraries `halloween2026*.php`, page `op/halloween2026.php`, staff console `op/staff/halloween2026.php`, secret endpoints `op/ajuste_indice.php` and `op/descubrir.php`.
- **Data:** config row with the earning window in UTC (`earn_start_utc`, `earn_end_utc`), a Calabazas ledger (`saldos`, `movimientos`), a store (`tienda_items`, `compras`), daily bonuses, and a hidden-secrets catalog with first-discoverer rewards and an alert table for decoys.
- **Hooks:** `pre_output_page` injects a small inline script; `rt_chat_commit_message_insert` detects chat secrets.
- **Dates:** the event end date comes from the database row, not from the code. Operations guide: `docs/106_Operacion_Halloween.md`.

### Periódico
- Public page `op/periodico.php`, staff editor `op/staff/periodico*.php`, library `periodico.php`. Tables: `mybb_op_periodicos`, `..._paginas`, `..._bloques`, `..._auditoria`.

### Panel táctico
- Combat helper panel: `op/panel_tactico.php`, plugin `opg_panel_tactico.php`, library `panel_tactico.php`. Tables: `mybb_op_panel_tactico_posts` and `..._eventos`. Design: `docs/211_DesignPlan_PanelTactico.md`.

### Bitácora and thread tracking
- Plugins `op_bitacora.php` and `op_temas_tracker.php` follow threads a user takes part in and show a counter in the header (injected with `global_intermediate`, never by editing `global.php`). Tables: `mybb_op_temas_seguidos`, `..._participantes`, `mybb_op_bitacora_eventos`, `..._lecturas`, `..._header_cache`.

### Item redemptions (canjes) from the sheet inventory
- Libraries `haki_canje.php` (Tirada de Haki del Rey, item `THR001`), `elemento_canje.php` (Pergamino de Estilo Elemental, `PEESP001`) and `arma_canje.php` (Tickets de Arma `TARM001` to `TARM005`, where the number is the weapon tier and the weapon id is the base id with the tier digit changed).
- Each one: validate the choice against a fixed list, consume one inventory unit atomically, apply the reward, undo the consumption if that fails, write an audit entry.
- The button is added in `op_ficha_inventario.html` and the modal is built in `op/personaje.php`.

### Resurrection (character farewell)
- `op/staff/resurreciones.php` moves a share of the dead character's resources to the new one and marks the old one `muerto = 1`. It still uses the original five-level percentage table. The new design (points score, four farewell levels, Legado points, cooldown) is written in `docs/Guia_Resurreccion_BBCode.md` and is not implemented.

### PunkRecords assistant
- Retrieval-augmented assistant over the official guides: `op/punkrecords.php`, `op/functions/punkrecords_*.php`, guides in `op/punkrecords/guias/` (allow-list in `punkrecords_config.php`). Uses Gemini and Voyage, with vectors in a Supabase table (`punkrecords_fragmentos`); keys live in `inc/config.php` under `$config['punkrecords']`. MySQL tables: `mybb_op_punkrecords_cache`, `..._cuota`, `..._log`.

### Crews and affiliates
- Tables `mybb_op_tripulaciones` and `..._solicitudes` (crews), `mybb_op_afiliados` and `..._rate_limit` (affiliates; staff tool `gestionar_afiliados.php`).

### Notifications (design only)
- `docs/108_Requirements_Notificaciones.md` and `docs/208_DesignPlan_Notificaciones.md` describe one global system (table, `opg_notificar()` library, `/op/notificaciones.php`, header counter). Nothing global exists yet.

## Templates and Deployment

- MyBB renders templates from the `mybb_templates` table. `templates/One_Piece_Gaiden_Templates/` holds local copies and `templates/.../stylesheets/` the stylesheets.
- `inc/plugins/template_sync.php` saves templates to disk when saved in the admin panel and imports files uploaded to that folder by FTP into the database (about every 10 seconds). `export_templates.php`, `import_templates.php` and `watch_templates.sh` do it on demand.
- PHP files are deployed to the Bluehost account. SQL migrations in `docs/*.sql` are run by the user (idempotent).
- Production is reachable only through SSH (port forwarding is blocked). Read-only scripts are run on the server by piping PHP to `ssh <account> php`, loading the server's own `inc/config.php`. Never print or store credentials.

## Tests

Plain scripts in `tests/<area>/`, run with `php`. Real-MySQL tests use the throwaway MySQL 5.7 container described in `tests/aventuras/bootstrap_db.php`. See `AGENTS.md` for the rules.

## Database Schema

> **Complete schema reference is in `DATABASE.md`**: every table, column, type, default, index, trigger, and entity relationship. Source dump: `rovddqmy_op.sql` (MySQL 5.7).

### Quick Reference

- **72 `mybb_op_*` tables in the March 2026 dump**, plus the tables created by the migrations in `docs/*.sql` (listed in `DATABASE.md`, section "Tables Added by Migrations"). Mixed InnoDB/MyISAM, no foreign key constraints
- Character ID (`fid`) equals user ID (`uid`): one character per account
- Audit tables `mybb_op_audit_*` log actions (general, stats, crafting, training, missions, rewards, etc.)
- Technique IDs: `D***` (defects), `V***` (virtues), `T***` (techniques)
- JSON columns `oficios`, `belicas`, `estilos`, `elementos` and `equipamiento` on `mybb_op_fichas` store skill trees and loadouts as native MySQL JSON
- Time-gated systems use `timestamp_end` (Unix timestamp) for training and crafting queues
- **2 database triggers:** `u_fichas_triggers` (snapshots character changes → `mybb_audit_op_fichas`) and `u_users_triggers` (logs `newpoints` changes → `mybb_audit_users`)
- **Custom columns on MyBB core tables:**
  - `mybb_posts.faccionsecreta` (varchar 80): secret faction override per post
  - `mybb_threads.prefix`: thread type codes: 3=Aventura, 10=MT, 1=Común, 6=Evento, 9=Autonarrada, 14=Requerimiento
  - `mybb_threads.year` / `estacion` / `day`: in-game date fields
  - `mybb_users.newpoints` (decimal 16,2): experience/role points (trigger-monitored)
  - `mybb_users.as_*` fields: Account switcher plugin columns

### Key Tables by Domain

| Domain | Tables | Notes |
|--------|--------|-------|
| Character | `fichas`, `fichas_secret`, `fichas_guardar`, `fichas_audit` | `fichas.fid` = `users.uid` (1:1). Trigger writes audit on every UPDATE |
| Items | `objetos`, `inventario`, `inventario_crafteo`, `cambioid`, `Objetos_Inframundo` | `inventario` has unique key on `(objeto_id, uid)`. Auction house in `Objetos_Inframundo` |
| Techniques | `tecnicas`, `tec_aprendidas`, `tec_para_aprender`, `tecnicas_usuarios`, `tecnicas_mantenidas` | `tecnicas.tid` is a string ID (e.g., `"DCO001"`). Training queue uses `tiempo_iniciado`/`tiempo_finaliza` |
| Combat | `thread_personaje`, `dados`, `equipamiento_personaje`, `mantenidas_html`, `tiradanaval`, `consumir` | `thread_personaje` snapshots all stats at combat entry. `dados` stores BBCode dice results |
| Powers | `akumas`, `virtudes`, `virtudes_usuarios` | `akumas.akuma_id` is string PK. `virtud_id` uses `V***`/`D***` prefix |
| NPCs | `npcs`, `npcs_usuarios`, `mascotas` | NPC has full stat block. `npcs_usuarios`/`mascotas` link NPCs to owners |
| Economy | `kuros`, `intercambios`, `codigos_admin`, `codigos_usuarios`, `recompensas_usuarios` | Currencies live on `fichas` (berries, nika, kuro). `intercambios` tracks both sender and receiver |
| Training | `entrenamientos_usuarios`, `crafteo_usuarios`, `crafteo_npcs`, `creacion_usuarios`, `oficios_usuarios` | All time-gated with `timestamp_end`. `entrenamientos_usuarios` has unique `uid` (one active per user) |
| World | `islas`, `isla_eventos`, `viajes`, `razas`, `barcos`, `mapa_posiciones`, `misiones_lista` | `isla_eventos` has season enum. `barcos` defines ship stats. `mapa_posiciones` stores % coordinates |
| Gacha | `tirada_akumas`, `tirada_cofre`, `tirada_haki`, `tirada_rey`, `cofres` | `cofres` is the loot table (weighted by `peso`). Roll results in `tirada_*` tables |
| Adventures | `peticionAventuras`, `peticionAventuras_meta`, `narradores_disponibles`, `aventuras_entregas`, `aventuras_entregas_pagos`, `aventuras_autonarradas`, `aventuras_participantes`, `aventuras_propuestas_narrador`, `aventuras_notificaciones`, `audit_aventuras` | `estado`: 0=created, 1=approved, 2=denied, 3=finished, 4=pending narrator, 5=deletion request. Newer tables come from migrations |
| Halloween 2026 | `halloween2026_config`, `_saldos`, `_movimientos`, `_publicaciones`, `_bonos_diarios`, `_tienda_items`, `_compras`, `_compra_lineas`, `_secretos`, `_secretos_alertas` | Event window and flags in `_config`; currency ledger in `_movimientos` |
| Newspaper | `periodicos`, `periodico_paginas`, `periodico_bloques`, `periodico_auditoria` | Editions, pages, blocks and an audit trail |
| Thread tracking | `temas_seguidos`, `temas_participantes`, `bitacora_eventos`, `bitacora_lecturas`, `bitacora_header_cache`, `thread_roles` | Bitácora and chronology support |
| Tactical panel | `panel_tactico_posts`, `panel_tactico_eventos` | Combat helper panel |
| Assistant | `punkrecords_cache`, `punkrecords_cuota`, `punkrecords_log` | RAG assistant bookkeeping (vectors live in Supabase) |
| Crews | `tripulaciones`, `tripulaciones_solicitudes`, `afiliados`, `afiliados_rate_limit` | Crew membership and affiliate links |
| Content | `sabiasque`, `likes`, `leidos`, `hide`, `hentai`, `adviento_abiertos`, `avisos`, `peticiones` | `likes` tracks who liked which post. `hentai` is adult content opt-in per user |
| Audit | 14 audit tables | `audit_general` is the catch-all. Specialized audits for stats, crafting, training, rewards, mod actions, AI detection |

## Authentication & Session Flow

1. User logs in via `member.php` → `LoginDataHandler` validates credentials
2. MyBB creates session in `mybb_sessions` table, sets `mybbuser` cookie with `uid_loginkey`
3. On each request, `$session->init()` reads cookie, calls `load_user($uid, $loginkey)`, queries `mybb_users` + `mybb_userfields`
4. `$mybb->user` populated with full user record; `$mybb->usergroup` has permissions
5. CSRF: `generate_post_check()` creates token from user salt + timestamp; `verify_post_check()` validates on POST
6. `$mybb->user['uid']` is the current user (0 = guest). The permission helpers (`is_staff`, `is_narra`, `is_mod`, `is_user`, `does_ficha_exist`) are in "Shared Game Functions".

### API Authentication (separate)
- `/api/index.php` implements JWT auth
- Login via POST to `?route=auth/login` with username/password → returns JWT token
- Subsequent requests send `Authorization: Bearer <token>` header
- `bearer_uid()` decodes JWT and returns `uid`
- `jwt_sign()` and `jwt_payload()` handle token creation/validation
- Endpoints are in `/api/`: the router `api/index.php` plus `auth`, `ficha`, `threads`, `thread` and `new_reply`. Responses are JSON.

## Key Entry Points

| URL Pattern | File | Purpose |
|-------------|------|---------|
| `/index.php` | `index.php` | Forum homepage |
| `/showthread.php?tid=X` | `showthread.php` | View thread |
| `/newthread.php?fid=X` | `newthread.php` | Create thread |
| `/newreply.php?tid=X` | `newreply.php` | Post reply |
| `/member.php?action=login` | `member.php` | Login |
| `/member.php?action=profile&uid=X` | `member.php` | User profile |
| `/op/personaje.php?uid=X` | `op/personaje.php` | Character sheet (`/op/ficha.php` redirects here) |
| `/op/ficha_crear.php` | `op/ficha_crear.php` | Create character |
| `/op/inventario.php` | `op/inventario.php` | View inventory |
| `/op/mercado_negro.php` | `op/mercado_negro.php` | Black market shop |
| `/op/crafteo.php` | `op/crafteo.php` | Crafting system |
| `/op/entrenamiento.php` | `op/entrenamiento.php` | Stat training |
| `/op/entrenamiento_tecnicas.php` | `op/entrenamiento_tecnicas.php` | Technique training |
| `/op/viajes.php` | `op/viajes.php` | Travel/sailing |
| `/op/api_combate.php` | `op/api_combate.php` | Combat JSON API |
| `/op/tablon_de_aventuras.php` | `op/tablon_de_aventuras.php` | Adventure board |
| `/op/halloween2026.php` | `op/halloween2026.php` | Halloween 2026 event page |
| `/op/periodico.php` | `op/periodico.php` | Newspaper |
| `/op/panel_tactico.php` | `op/panel_tactico.php` | Tactical panel |
| `/op/punkrecords.php` | `op/punkrecords.php` | Guide assistant |
| `/op/staff/*` | `op/staff/*.php` | Staff admin tools (~85) |
| `/api/index.php?route=X` | `api/index.php` | REST API |
| `/xmlhttp.php?ext=rt_chat` | `xmlhttp.php` | Real-time chat AJAX |

## Custom BBCode Processors

All 16 live in `inc/plugins/` and hook into the post parser:

| Tag | File | What It Does |
|-----|------|-------------|
| `[ficha]` | `BBCustom_ficha.php` | Renders inline character sheet from `mybb_op_fichas` |
| `[fichasecreta]` | `BBCustom_fichasecreta.php` | Renders secret character sheet from `mybb_op_fichas_secret` |
| `[dado]` / `[XdY]` | `BBCustom_dado.php` | Dice roller: rolls and stores results in `mybb_op_dados` |
| `[objeto=ID]` | `BBCustom_objeto.php` | Displays item card from `mybb_op_objetos` |
| `[tecnica=TID]` | `BBCustom_tecnica.php` | Displays technique card from `mybb_op_tecnicas` |
| `[npc=ID]` | `BBCustom_npc.php` | Renders NPC data from `mybb_op_npcs` |
| `[consumir=ID]` | `BBCustom_consumir.php` | Consumes item from inventory |
| `[mantenida=ID]` / `[fin=ID]` | `BBCustom_mantenida.php` | Activates/deactivates sustained techniques |
| `[recursos=X]` | `BBCustom_recursos.php` | Displays HP/energy/haki bars |
| `[personajesecreto]` | `BBCustom_personajesecreto.php` | Switches to secret identity |
| `[hide]` | `BBCustom_hide.php` | Content visible only to staff/author |
| `[cerrado]` | `BBCustom_cerrado.php` | Closes the thread |
| `[spoiler]` | `BBCustom_spoiler.php` | Collapsible spoiler box |
| `[akuma=ID]` | `BBCustom_akuma.php` | Displays a Devil Fruit card from `mybb_op_akumas` |
| `[tabla]` / `[tr]` / `[td]` | `BBCustom_tabla.php` | Renders tables (width, height and alignment parameters) |
| `[tiempo=horas]` | `BBCustom_tiempo.php` | Live countdown card counted from the post's `dateline`, informational only |

## External Service Integrations

### Discord Webhooks
- **Plugin:** `inc/plugins/rt_discord_webhooks.php`
- **Tables:** `mybb_rt_discord_webhooks`, `mybb_rt_discord_webhooks_logs`
- **API function:** `fetch_api()` in `inc/plugins/rt/src/DiscordWebhooks/functions.php`
- **Triggers:** New threads, new replies, user registrations, post edits
- Converts BBCode to Discord Markdown automatically

### VPN/Proxy Detection
- The code that called VPNApi.io, ProxyCheck.io and IPHub.info is no longer in the repository. The `mybb_op_avisosvpn` table still exists in the database.

### Real-Time Chat
- **Plugin:** `inc/plugins/rt_chat.php`
- **Frontend:** `jscripts/rt_chat.js` (uses Fetch API)
- **Endpoint:** `/xmlhttp.php?ext=rt_chat&action=insert_update_message|load_messages|delete_message`
- Zero-database design: uses Redis/Memcache for storage

### PunkRecords (AI assistant)
- **Services:** Gemini (answers), Voyage (embeddings) and a Supabase Postgres table of guide fragments (`punkrecords_fragmentos`, defined in `docs/punkrecords_supabase.sql`).
- **Keys:** `inc/config.php`, under `$config['punkrecords']` (`gemini_key`, `supabase_url`, `supabase_key`, `voyage_key`). Never print them.
- **Code:** `op/punkrecords.php` and `op/functions/punkrecords_*.php`. Only the guides listed in `PR_GUIAS_PERMITIDAS` are indexed.

### External Database
- `op/db_extra.php` connects to an external database at `bdopg.iceiy.com`
- Used for data that lives outside the main forum database (exact tables not defined in the local SQL dump)

## Shared Game Functions

**File: `op/functions/op_functions.php`**

| Function | Purpose |
|----------|---------|
| `does_ficha_exist($uid)` | Returns character if exists and approved |
| `select_one_query_with_id($table, $id_name, $id)` | Generic single-row SELECT |
| `get_obj_from_query($query)` | Extract single result from query |
| `log_audit($uid, $username, $categoria, $log)` | Log to `mybb_op_audit_general` |
| `log_audit_currency($uid, $username, $user_uid, $cat, $currency, $amount)` | Log currency changes |
| `is_staff($uid)` | Staff check (hardcoded UIDs + groups 4, 6, 14, 16) |
| `is_narra($uid)` | Narrator check (group 15 in `additionalgroups`) |
| `is_mod($uid)` | Moderator check |
| `is_user($uid)` | Admin check (UIDs 1, 3, 6, 7, 9) |
| `is_peti_mod($uid)` | Petition moderator (UID 1 only) |
| `log_security_event($tipo, $endpoint, $extra_info)` | Log to `mybb_op_security_log` |

**Feature libraries in `inc/plugins/lib/`** (pure logic where possible, each with tests in `tests/`): `aventuras*.php`, `recompensas_aventura.php`, `halloween2026*.php`, `periodico.php`, `panel_tactico.php`, `haki_canje.php`, `elemento_canje.php`, `arma_canje.php`, `tecnicas_cambios.php`, `xp_snapshot.php`, `banda_sonora.php`, `belico_calculos.php`, `spoiler.php`.

**File: `op/functions/security_monitor.php`**
- Rate limiting and security monitoring
- Logs to `mybb_op_security_log`

## Reference: Structure, Lifecycle and Recipes

Consulted on demand (`AGENTS.md` keeps only the short version).

### Full Directory Structure

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

### Request Lifecycle

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

### Key Globals

- `$mybb`: Core MyBB instance. `$mybb->user` has current user, `$mybb->input` has sanitized request data, `$mybb->settings` has board config
- `$db`: Database instance. Methods: `query()`, `simple_select()`, `fetch_array()`, `insert_query()`, `update_query()`, `write_query()`
- `$templates`: Template engine. `$templates->get('name')` returns template HTML
- `$plugins`: Plugin system. `$plugins->run_hooks('hook_name')` triggers hook callbacks
- `$lang`: Language system
- `$cache`: Data cache system

### All Known Gotchas

Deployment details are in "Templates and Deployment" and the external database is under "External Service Integrations".

1. **`op/personaje.php` is ~196KB**: the character sheet page, mixing PHP, AJAX endpoints and a large inline script. Read it by sections. Its template (`op_personaje.html`) embeds data in JavaScript, so see the JSON rule in Working Conventions.
2. **`op/ficha.php` is an obsolete 301 redirect** to `op/personaje.php`. Do not add logic there.
3. **`member.php` is ~100KB**: heavily customized beyond standard MyBB.
4. **`op/crafteo.php` has `THIS_SCRIPT = 'viajes.php'`**: copy-paste error in the define, does not affect functionality.
5. **SQL queries use string interpolation**: not parameterized; escape or validate every value when modifying queries.
6. **Templates use `eval()`**: standard MyBB behavior, not a bug.
7. **JWT secret is `'test'`** in `/api/index.php`: insecure, intended for development only.
8. **CORS reflects any `Origin`** in the API: no domain restriction.

### Plugin System

Plugins register hooks via `$plugins->add_hook('hook_name', 'callback_function', $priority)`. Hooks fire at defined points in the request lifecycle (e.g., `global_start`, `index_start`, `parse_message_start`, `pre_output_page`).

Feature plugins follow the `op_*` / `opg_*` naming and keep their logic in `inc/plugins/lib/`: `op_bitacora`, `op_temas_tracker`, `op_modo_espejo`, `op_niveles`, `op_fama`, `op_facciones`, `op_solicitudes_creacion`, `opg_halloween2026`, `opg_panel_tactico`, plus the chat (`rt_chat`) and the NewPoints currency plugin.

### Common Tasks

#### Adding a new game page in `/op/`
Follow the existing pattern: define `IN_MYBB`, require `global.php`, `config.php`, and `op_functions.php`. Use `$db` for queries and check `$mybb->user['uid']` for auth.

#### Adding a new staff tool
Create a file in `/op/staff/`. Check `is_staff($uid)` at the top. Follow existing patterns in files like `objetos_crear.php` or `tecnicas_crear.php`.

#### Adding a new BBCode
Create `inc/plugins/BBCustom_name.php`. Hook into `parse_message` or `parse_message_start`. Use regex to find/replace your tag in the post content. See `BBCustom_dado.php` (308 lines) for a clean example.

#### Modifying character sheet data
The main character table is `mybb_op_fichas`. The display logic and the sheet's AJAX actions are in `op/personaje.php`, with its JavaScript modules in `jscripts/ficha/`. Creation logic is in `op/ficha_crear.php`. Stats are columns directly on the fichas table. **Warning:** Any UPDATE on `mybb_op_fichas` fires the `u_fichas_triggers` database trigger, which writes a snapshot row to `mybb_audit_op_fichas`.

#### Adding an item redemption (canje) to the sheet inventory
Copy the pattern of `haki_canje`, `elemento_canje` or `arma_canje`: a pure library in `inc/plugins/lib/` with its test, an AJAX action in `op/personaje.php` (CSRF check, atomic consumption, refund on failure, audit entry), a modal built in PHP, and a button in `templates/One_Piece_Gaiden_Templates/op_ficha_inventario.html` (remember the template must be uploaded to MyBB).

#### Adding a feature library
Put the logic in `inc/plugins/lib/<feature>.php` as pure functions where possible, add `tests/<feature>/` in the same change, keep the page or plugin thin, and add a migration in `docs/<feature>_migration.sql` if it needs tables.
