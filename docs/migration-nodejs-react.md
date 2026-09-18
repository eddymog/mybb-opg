# Migrating One Piece Gaiden from MyBB/PHP to Node.js + React

A staged plan for replacing the PHP stack with Node.js and React without ever taking the forum down — and an honest account of which parts are worth migrating at all.

---

## 1. Verdict first

**Migrate the game system. Leave the forum on MyBB.**

The `/op/` game system is custom, differentiated, and the source of your maintenance pain. It is worth moving to Node + React.

The MyBB core — threads, posts, PMs, moderation, permissions, attachments, subscriptions, admin CP — is a solved problem you currently get maintained for free by upstream. Rewriting it consumes years and produces nothing a single member of your forum will ever notice. If you rewrite it, you also inherit its entire security-patch burden.

This document lays out the full path anyway, including the forum core, because you asked for it. But the recommendation is to execute **Phases 0–4 and stop**. That captures roughly 90% of the benefit for roughly 20% of the work.

---

## 2. What the codebase actually looks like

Measured, not estimated:

| Metric | Value | Why it matters |
|---|---|---|
| MyBB version | 1.8.36 | Still receiving upstream patches |
| PHP modules in `/op/` | 103 | The migration surface |
| …coupled to `$templates->get()` | **81 (79%)** | **The real cost driver** |
| …using `$db->` | 74 | Portable — it's just MySQL |
| …using `$mybb->user` | 65 | Easily solved (see Phase 1) |
| …using `$plugins->run_hooks()` | 1 | Non-issue |
| Theme templates | 1,251 | No mechanical path to Node |
| MyBB plugins installed | 41 | Each needs port / drop / stay |
| Custom BBCode processors | 15 | Part of the parser problem |
| `inc/class_parser.php` | 2,072 lines | Must render historical posts identically |
| Tables | 160 (100 MyISAM / 60 InnoDB) | No transactions on the MyISAM half |
| Existing JSON API | 6 endpoints in `/api/` | **Your beachhead already exists** |

Two facts dominate everything below:

1. **79% of game pages render through MyBB's `eval()`'d, DB-stored templates.** Node cannot reuse them. Every migrated page needs new views *and* must reproduce the forum chrome. This — not business logic — is what makes migration expensive.

2. **You are on shared hosting.** The Newfold rules in `.htaccess` and the `/home4/rovddqmy/public_html` paths in your error logs confirm it. Shared hosting will not run a persistent Node process. This is a hard blocker, and it is Phase 0.

---

## 3. The architecture: strangler fig behind a reverse proxy

The pattern is well-established. Put a reverse proxy in front of everything. Route by path: a growing list of paths goes to Node; everything else falls through to PHP. Both talk to the same MySQL. You move routes one at a time, and the forum is never down.

```
                    ┌──────────────────────────┐
                    │   Reverse proxy (Nginx)  │
   Browser ────────▶│   routes by path         │
                    └───────┬──────────┬───────┘
                            │          │
         /api/v2/*          │          │   everything else
         /juego/*           │          │   (forum, admin CP,
                            ▼          ▼    unmigrated /op/)
                 ┌────────────────┐  ┌──────────────────┐
                 │ Node + Express │  │  PHP-FPM / MyBB  │
                 │ + React        │  │                  │
                 └───────┬────────┘  └────────┬─────────┘
                         │                    │
                         └────────┬───────────┘
                                  ▼
                          ┌───────────────┐
                          │     MySQL     │
                          │ mybb_* tables │
                          └───────────────┘
```

Nginx config, roughly:

```nginx
# Migrated routes → Node
location ~ ^/(api/v2|juego)/ {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

# Everything else → MyBB, unchanged
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Adding a route to the migrated list is a one-line change. Rolling one back is deleting that line. That reversibility is the whole point of the pattern — use it.

---

## 4. Phases

### Phase 0 — Foundations (no Node yet)

Nothing else can start until these are done. None of it is glamorous and all of it is gating.

- **Move to a VPS.** Shared hosting cannot run Node. Budget for the box plus the ops surface you're taking on: process supervision (systemd or PM2), deploys, TLS renewal, backups, monitoring. This is a real, permanent change in how you operate the site.
- **Stand up a staging environment with a real database copy.** You cannot do this migration safely without one. This is non-negotiable — you will be running two codebases against one schema, and you need somewhere to be wrong.
- **Fix the API auth.** `api/index.php` currently has `const JWT_SECRET = 'test'` with a 7-day TTL and CORS that reflects the caller's origin. Anyone can mint a token for any `uid`, including staff, from any website. Move the secret into `inc/config.php` as a long random value and pin CORS to your own domain. This is not unrelated housekeeping — that auth seam is the literal foundation the Node service will stand on.
- **Convert MyISAM → InnoDB.** 100 tables need it. Node will want transactions, and MyISAM's table-level locking is your current concurrency ceiling. Do it table by table, hottest first (`mybb_op_fichas`, inventory, audit tables). Re-verify `u_fichas_triggers` and `u_users_triggers` afterward, and note the `FULLTEXT` index on `mybb_posts.message` — see §5.4.
- **Rotate logs and fix the top errors.** `op/errorz.log` and `op/staff/errorz.log` total 212MB. The dominant entries are ~11,500 `Undefined array key`, ~9,200 `Trying to access array offset on value of type null`, and ~6,600 `Undefined variable $ficha`, heavily concentrated in `op/staff/ficha_atributos.php`. These matter here specifically: each one marks a place where PHP silently tolerates a null that a stricter Node port will turn into a hard failure. Cleaning them up now is reconnaissance for the migration.

**Exit criteria:** Node process runs on the VPS, proxy is in front, staging mirrors production, error volume is down to near-zero.

---

### Phase 1 — The seam: shared identity

The linchpin. Node must answer "who is this request?" identically to PHP, or nothing else is possible.

**Step A — Node reads MyBB's sessions (read-only, zero risk).**

`mybb_sessions` is a flat `sid → uid` lookup. Node reads the cookie and resolves the user. PHP remains the sole authority on login; Node just trusts it.

```js
// Resolve the current user from MyBB's own session cookie.
async function currentUser(req, db) {
  const sid = req.cookies.sid;
  if (!sid) return null;

  const [rows] = await db.query(
    `SELECT u.uid, u.username, u.usergroup, u.additionalgroups
       FROM mybb_sessions s
       JOIN mybb_users u ON u.uid = s.uid
      WHERE s.sid = ? AND s.uid > 0
      LIMIT 1`,
    [sid]
  );
  return rows[0] ?? null;
}
```

Port the role checks from `op/functions/op_functions.php` alongside it — `is_staff()`, `is_narra()`, `is_mod()`. They are UID whitelists plus usergroup membership; keep the two implementations in one shared source of truth (a JSON config both sides read) so they cannot drift.

**Step B — Node owns login, without forcing a password reset.**

MyBB's scheme is `md5(md5(salt) . md5(password))` (`inc/functions_user.php:203`). That is trivially portable, which means Node can verify existing passwords directly:

```js
import { createHash, timingSafeEqual } from 'node:crypto';
import argon2 from 'argon2';

const md5 = (s) => createHash('md5').update(s, 'utf8').digest('hex');
const mybbHash = (password, salt) => md5(md5(salt) + md5(password));

async function verifyPassword(user, password) {
  // Prefer the modern hash once it exists.
  if (user.password_v2) {
    return argon2.verify(user.password_v2, password);
  }

  const expected = Buffer.from(user.password, 'utf8');
  const actual = Buffer.from(mybbHash(password, user.salt), 'utf8');
  if (expected.length !== actual.length) return false;
  if (!timingSafeEqual(expected, actual)) return false;

  // Correct password against the legacy hash — transparently upgrade.
  await db.query('UPDATE mybb_users SET password_v2 = ? WHERE uid = ?', [
    await argon2.hash(password, { type: argon2.argon2id }),
    user.uid,
  ]);
  return true;
}
```

**Write the upgrade to a new `password_v2` column, never over `password`.** If you overwrite the original, PHP login breaks instantly and you have taken the forum down. With a separate column both sides keep working: PHP reads `password`, Node prefers `password_v2` and falls back. You drop the legacy column only after PHP login is retired for good.

**Exit criteria:** Node can identify any logged-in user and reproduce every permission check. Nothing user-visible has changed.

---

### Phase 2 — Read-only Node surface

Node serves JSON and React components, but PHP still renders pages and still owns every write. Risk is close to zero because there is nothing to corrupt.

Two delivery shapes, both valid:

- **JSON endpoints** under `/api/v2/`, consumed by existing jQuery or new React.
- **React islands** — a `<div id="...">` dropped into an existing MyBB template, with the bundle mounted into it. The PHP page still renders the chrome; React owns one region.

Islands are the key trick, because they sidestep the chrome problem entirely for as long as you need them to.

Pick targets from the least template-coupled end of `/op/`: the calculators (`op/Calculadora_Tiers.php`), the maps, the gacha roll UI, the ficha builder. `jscripts/ficha_script2.js` is 252KB of untyped jQuery state management and is the single strongest candidate in the codebase — it is already a client-side application, just an unmaintainable one.

**Exit criteria:** at least one real feature is served by Node in production, and you have a deploy pipeline you trust.

---

### Phase 3 — Node owns whole features, including writes

Now it gets real. Node takes ownership of a feature end to end.

**The rule that keeps this safe: migrate whole features, never half of one. For any given table, writes live on exactly one side.**

Your DB triggers (`u_fichas_triggers`, `u_users_triggers`) are database-level, so they fire correctly regardless of which language wrote the row — that part is fine. What is *not* fine is business invariants split across two codebases. If PHP and Node can both mutate `berries`, you have two implementations of your economy rules and eventually they disagree. That is how duplication bugs are born.

Migrate the game system, not the forum, and go feature by feature: crafting, then training, then travel, then the economy. Each one moves fully, with its route flipped in the proxy, and stays rollback-able for a couple of weeks before you delete the PHP.

**Exit criteria:** a meaningful share of `/op/` runs on Node, and you have rolled at least one migration back and forward again to prove the process works.

---

### Phase 4 — React properly

Once Node owns routes rather than fragments, it can render whole pages, and React stops being islands and becomes the front end.

The obstacle is the chrome. Your header, footer, navigation, and user panel are MyBB templates living in the database. Node-rendered pages need them, and there is no import path. Solve it once, deliberately: extract the theme's shell into a single React layout component, and accept that from then on it has to be maintained in two places until PHP is gone. Budget for that duplication — it is the standing tax of the transition.

Stack: Express plus Vite for the bundle, React with TypeScript, and a typed client generated from your API schema. Resist adding SSR until you actually need it; these are logged-in game pages, not content you need indexed.

**Exit criteria:** `/op/` is a React application served by Node. The forum is still MyBB, and still fine.

---

### Phase 5 — The forum core (not recommended)

If you go on anyway, this is what is in front of you: threads, posts, replies, quoting, editing, pagination, private messages, moderation queues, reports, warnings, bans, the permission matrix, attachments, subscriptions, notifications, search, user profiles, registration, email, and the admin control panel. Plus 41 plugins and the parser.

Sequence it read-before-write — serve thread and post *views* from Node while PHP still handles submission, then move posting last, since that is where the parser and the permission rules concentrate.

Realistically this is multi-year work for one person maintaining a live community at the same time.

---

## 5. The genuinely hard problems

These are the parts that sink migrations. None are unsolvable; all need a plan before you start, not after.

### 5.1 The parser

`inc/class_parser.php` is 2,072 lines, plus 15 custom `BBCustom_*` processors, plus MyCode rules stored in the database. Every post ever written on your forum is stored as BBCode and must render **identically** after the migration. Years of roleplay threads break visibly if it drifts — and your custom tags (`[ficha]`, `[dado]`, `[tecnica=TID]`, `[objeto=ID]`, `[npc=ID]`) are game mechanics, not formatting.

Two viable strategies:

- **Keep PHP as a rendering service.** Node posts raw BBCode to a small internal PHP endpoint and gets HTML back. Ugly, and it means PHP never fully dies — but it is correct by construction and costs days instead of months. For a solo maintainer this is usually the right answer.
- **Port it against a golden corpus.** Export tens of thousands of real posts, render each through both parsers, diff, and iterate until the diff is empty. Only then switch.

```js
// Golden-corpus harness — the only responsible way to port the parser.
const rows = await db.query(
  'SELECT pid, message FROM mybb_posts ORDER BY RAND() LIMIT 20000'
);

const mismatches = [];
for (const { pid, message } of rows) {
  const fromPhp  = await renderWithPhp(message);
  const fromNode = renderWithNode(message);
  if (normalize(fromPhp) !== normalize(fromNode)) {
    mismatches.push({ pid, fromPhp, fromNode });
  }
}
console.log(`${mismatches.length} / ${rows.length} mismatched`);
```

Do not attempt the port without this harness. "It looked right on the posts I tried" is how you discover three months later that every `[quote]` inside a `[spoiler]` has been broken since June.

### 5.2 Templates

1,251 templates, stored in the database, rendered with `eval()`. There is no mechanical translation to React, and no partial-credit path: a page is either MyBB-rendered or Node-rendered. This is precisely why the 79% coupling figure is the cost driver, and why React islands (Phase 2) are so valuable — they let you deliver real value while deferring this problem.

### 5.3 Sessions and auth

Covered in Phase 1. Genuinely the easiest of the hard problems, and it must be first because everything else depends on it. The one trap is the password column — write to `password_v2`, never over `password`.

### 5.4 Search

`mybb_posts.message` carries a `FULLTEXT` index under MyISAM. Your Phase 0 InnoDB conversion changes FULLTEXT behavior, and neither engine gives you search a modern front end deserves. Treat search as its own project with its own timeline: index posts into Meilisearch or Typesense, keep it updated on write, and serve it from Node. Do not let it ride along inside another phase.

### 5.5 Plugins

41 installed. Inventory them early and sort each into port / drop / leave-on-PHP. A meaningful number are probably dead or nearly so, and every one you drop is work you never have to do. Note that only one file in `/op/` uses `$plugins->run_hooks()`, so the *game system* has almost no hook coupling — the exposure is concentrated in the forum, which is another argument for leaving the forum alone.

---

## 6. Rules that keep this from failing

1. **Always shippable.** There is never a moment where production is half-migrated and broken. Every phase ends with a working forum.
2. **One writer per table.** Reads can come from anywhere. Writes for a given table live on exactly one side, always.
3. **Features, not files.** "Migrate `crafteo.php`" is the wrong unit. "Migrate crafting" is the right one.
4. **Every route is reversible.** If flipping a route back to PHP is not a one-line change, you have built the seam wrong.
5. **Golden tests before the parser.** No exceptions.
6. **Shared permission logic.** `is_staff()` and friends must have one definition both runtimes read, or they will drift and you will ship a privilege bug.

---

## 7. The honest risk

The common failure mode for "slowly transition" migrations run by one person is **two systems forever**: game logic split across PHP and Node, neither complete, every new feature requiring a decision about which side it belongs on, and total maintenance cost higher than when you started. The migration doesn't fail loudly — it just never finishes, and you spend years paying the tax.

Guard against it by setting the kill criteria *now*, while you are optimistic:

- If Phase 0 hasn't shipped in **2 months**, the infrastructure change is bigger than the payoff. Stop.
- If Phase 1 hasn't shipped in **1 month** after that, the auth coupling is worse than measured. Stop.
- If after **6 months** fewer than three real features run on Node, stop and consolidate back onto PHP rather than leaving the codebase split.

Stopping early is not failure. Ending up with a permanently forked codebase is.

---

## 8. Recommendation

Do **Phase 0 through Phase 4**. Move the game system to Node and React, keep the forum on MyBB indefinitely, and keep the PHP parser as a rendering service so you never have to fight §5.1.

That path gets you typed, testable, maintainable game logic — the code you actually spend your time in — without a multi-year rewrite of software that already works and that upstream maintains for you.

The first three things to do, in order:

1. Fix `JWT_SECRET` and the CORS policy in `api/index.php`. Do this regardless of whether you ever migrate anything.
2. Get onto a VPS with a staging database.
3. Build the Phase 1 session seam and prove Node can identify your users.

Nothing after step 3 is committed. Those three are worth doing on their own merits, which makes them a cheap way to find out whether you want the rest.
