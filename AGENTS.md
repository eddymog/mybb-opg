# AGENTS.md: One Piece Gaiden (OPG1)

## Project Overview

One Piece Gaiden is a Spanish-language roleplay forum built on **MyBB 1.8.36**. The core forum engine handles users, threads, and posts. On top of it, a fully custom RPG game system lives in `/op/` with character sheets, combat, crafting, training, economy, travel, and gacha mechanics.

## Tech Stack

- **Backend:** PHP (MySQLi), running on Apache with mod_rewrite
- **Forum engine:** MyBB 1.8.36 (class files in `/inc/`)
- **Database:** MySQL with `mybb_` table prefix. Custom game tables use `mybb_op_*` prefix
- **Frontend:** jQuery, SCEditor (rich text editor), custom JS modules in `/jscripts/`
- **External services:** Discord webhooks; the PunkRecords assistant (RAG over the guides, using Gemini, Supabase and Voyage, with keys in `inc/config.php` under `$config['punkrecords']`). VPN detection code is no longer in the repository (the `mybb_op_avisosvpn` table remains).

## Repository Map

```
/                      MyBB page scripts (index.php, showthread.php, member.php...)
/op/                   Custom game pages (~116); /op/staff/ staff tools (~85); /op/functions/ shared helpers
/op/personaje.php      Character sheet page (~196KB, inline script). op/ficha.php is only a 301 redirect to it
/opg/                  Gacha and roll systems (cofres, akumas, haki, rey)
/api/                  REST API (router + auth, ficha, threads, thread, new_reply; JWT)
/inc/                  MyBB core; /inc/plugins/ plugins and BBCode processors; /inc/plugins/lib/ feature libraries
/jscripts/             Client JS (character sheet modules in jscripts/ficha/)
/templates/            Local copies of MyBB templates and stylesheets
/tests/                One folder per area; tests/run_all.sh is the green gate
/docs/agents/          This handbook: process.md, roles/, context.md, DATABASE.md (the rest of /docs/ is ignored by git)
```

## Where to Read More

Read these only when the task needs them. Searching them (for example by table or section name) is better than loading them whole.

| Need | Read |
|---|---|
| Request lifecycle, globals, permissions, auth flow, entry points, BBCode, plugins | `docs/agents/context.md` |
| Where a feature lives, recipes for common tasks, the full list of gotchas | `docs/agents/context.md` (sections "Feature Systems Added in 2026", "Common Tasks", "All Known Gotchas") |
| Tables, columns, indexes, relationships | `docs/agents/DATABASE.md` (very long: search by table) |
| Workflow, roles, issue template, lanes | `docs/agents/process.md` and `docs/agents/roles/` |
| Visual design: any page, template or CSS (typography, palette, tokens, components, checklist) | `docs/style.md` (read it before touching UI; the UI Designer role owns it) |

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

## Critical Gotchas

The full list is in `docs/agents/context.md`. These are the ones that cause the most damage:

1. **`op/personaje.php` is ~196KB** (PHP, AJAX endpoints and a large inline script). Read it by sections. Data embedded in its template goes through `op_json_para_plantilla_js()` (see Working Conventions).
2. **`op/ficha.php` is an obsolete 301 redirect** to `op/personaje.php`. Do not add logic there.
3. **Any UPDATE on `mybb_op_fichas` fires the `u_fichas_triggers` trigger**, which writes a snapshot row to `mybb_audit_op_fichas`.
4. **SQL queries use string interpolation**: escape or validate every value when touching a query.
5. **Templates reach the database through `template_sync.php` or the import script**, and SQL migrations are run by the user. Agents never deploy either.
6. **Character ID (`fid`) equals user ID (`uid`)**: one character per account. No foreign keys exist; relationships live in application code.

## Agent Team and Process

Work on this project follows a small team model, whether it is done by one agent or several.

- Roles live in `docs/agents/roles/`: `orchestrator.md`, `program-manager.md`, `game-designer.md`, `ui-designer.md`, `software-developer.md` and `qa-tester.md`. The Orchestrator organizes `process.md` and the other roles. The Game Designer owns game rules and player guides and only joins features that include game rules.
- The end-to-end workflow (requirements, design, GitHub issues, development loop, QA gate, release) is in `docs/agents/process.md`.
- This file is tool-agnostic. Claude Code reads it through the root `CLAUDE.md`, which imports it; Codex reads `AGENTS.md` directly and does not follow imports, so keep this file self-contained and under about 30 KB. Tool-specific notes go in `CLAUDE.md` or in section 9 of `docs/agents/process.md`.
- Every agent reads this file first, then its own role file, then `docs/agents/context.md` and `docs/agents/DATABASE.md` when the task touches architecture or data, then the documents of the feature it works on.

### Tasks are GitHub issues

- Tasks are GitHub issues, one at a time.
- Read the acceptance criteria before starting and before closing.
- Commit regularly: small commits, tests passing, issue number in the message (`Área: what was done (#n)`).
- Only the QA role approves, and an issue is closed only after approval. Developers commit on their issue branch and never push to `main` themselves; after the QA approves and `sh tests/run_all.sh` is green, the Orchestrator merges into `main` and pushes (standing permission from the user). Never force-push. Deployment follows the section "Deployment After Code Changes".
- Every issue has a lane (trivial, normal or large) that sets how much of the process applies, and a rollback plan when it touches templates, SQL or data. See `docs/agents/process.md`.
- The PM creates, grooms and reorders issues on its own, without asking for approval (standing permission from the user).
- Issue template, labels and `gh` commands are in `docs/agents/process.md`. The repository is `eddymog/mybb-opg` (public: never put credentials or private data in an issue).

## Working Conventions

- **Language:** user-facing text and documents are in neutral Spanish (never voseo: write "usar/revisar", not "usá/revisá"). Code identifiers stay as they are in the codebase.
- **Punctuation:** never use em dashes in any written output. Use commas, periods, parentheses or restructure the sentence.
- **End of turn:** whenever a turn creates, edits or deletes files, close with the list of changed files.
- **Never modify `global.php`.** Add behavior with a plugin hook (for example `global_intermediate`). The model to copy is `inc/plugins/op_temas_tracker.php`.
- **Templates:** the `.html` files in `templates/One_Piece_Gaiden_Templates/` are reference copies. MyBB renders templates from the database. The plugin `inc/plugins/template_sync.php` writes templates to disk on admin saves and imports files uploaded to that folder by FTP into the database (about every 10 seconds), and `export_templates.php` / `import_templates.php` do the same on demand. In practice the user must upload or import every changed template. Say so explicitly after changing one. (`op_halloween2026.html` is the exception: it is read from the file at runtime.)
- **Docs:** `/docs/` is gitignored (local only). Numbering: `1xx` requirements, `2xx` design plans, `3xx` implementation plans, plus operational guides and SQL migrations. This file and `CLAUDE.md` live in the repository root; the rest of the agent handbook (`process.md`, `roles/`, `context.md`, `DATABASE.md`) lives in `docs/agents/`, which is versioned even though the rest of `/docs/` is ignored. Because `docs/` is not in git, put acceptance criteria in the GitHub issue itself.
- **SQL migrations:** idempotent files in `docs/*.sql`. Agents write them; the user runs them. Agents never run a migration against production.
- **Shared logic goes in libraries** under `inc/plugins/lib/` (pure functions where possible) so it can be tested without a database. Page scripts and plugins stay thin.
- **Embedding JSON in templates:** when a template wraps JSON in a JavaScript template literal (a `JSON.parse` call with the data between backticks), pass the data through `op_json_para_plantilla_js()` (see `op/personaje.php`). A plain `json_encode` breaks on quotes, backslashes and backticks.

## Testing

- **The green gate is `sh tests/run_all.sh`.** It checks that no forbidden file was touched, runs `php -l` on changed PHP files, scans added lines for credentials, warns about em dashes, and runs every test that does not need a database. `--db` adds the real-MySQL tests, `--area NAME` runs only the tests of `tests/NAME/`, `--rapido` skips the tests, `--ayuda` lists the options. The Tablón database tests (`tests/aventuras/`) were already red before the gate existed (see issue #27), so for notification work use `--db --area notificaciones`. Work is not ready for review until it ends in VERDE.
- `tests/known_failures.txt` lists tests that already failed before the gate existed. They show as a known failure and do not block, but never add to that list to get around a new failure without the user's explicit permission.
- Tests are plain PHP scripts in `tests/<area>/`; run them with `php tests/<area>/<file>.php`. A passing script exits with status 0 (most also print an `OK ...` line); a failing one prints the failures and exits with a non-zero status.
- Check syntax of every PHP file you touch with `php -l <file>`.
- New pure logic gets a test in `tests/<area>/` in the same change (examples: `tests/haki_canje/`, `tests/elemento_canje/`, `tests/arma_canje/`).
- Some areas have their own runner, for example `tests/banda_sonora/run.sh`. Look inside the area folder first.
- Anything that depends on real MySQL behavior (`affected_rows`, concurrency, transactions) needs a real database. A throwaway MySQL 5.7 container (the production version) is already set up for the Tablón tests: see the header of `tests/aventuras/bootstrap_db.php` for the exact `docker run` command (port 33307, `--tmpfs` data, no production data), run `php tests/aventuras/<file>.php`, then remove the container. New database tests should reuse that bootstrap. Never test against production.

## Deployment After Code Changes

- After completing and validating any code-level change, run `scripts/deploy_changed.sh` so the changed files are uploaded to the SFTP server.
- Review the complete file list printed by the script before confirming the upload. Its exclusions come from `.vscode/sftp.json`.
- Never add remote deletion behavior to this deployment flow. A documentation-only change does not require deployment.
- In the issue loop, only the Orchestrator deploys, after the QA approved and the work was merged. Run the script with `--dry-run` first to review the list, then publish with `--yes` passing the issue's changed files explicitly (after a commit, the script's automatic detection finds nothing).
- **Hold the deployment and tell the user** when the issue has coupled steps: templates (the template sync imports an uploaded template into the database within about 10 seconds, so uploading it activates it), SQL migrations, plugin activation, a data-migration script, or an issue that must go live together with another one. The user decides when and in which order.

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
