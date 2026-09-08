# DATABASE.md — One Piece Gaiden Schema Reference

Generated from `rovddqmy_op.sql` (phpMyAdmin dump, MySQL 5.7.44, 2026-03-25).

## Overview

- **Database:** `rovddqmy_op`
- **Engine:** MySQL 5.7 via MySQLi
- **Table prefix:** `mybb_` (standard MyBB) + `mybb_op_` (custom game)
- **Encoding:** utf8 / utf8mb4
- **Total tables:** ~120 (MyBB core + custom game)

## Database Triggers

Two AFTER UPDATE triggers exist:

1. **`u_fichas_triggers`** on `mybb_op_fichas` — On every UPDATE, inserts a full snapshot of key character fields (stats, currencies, skills, haki, death status) into `mybb_audit_op_fichas`. This is the primary audit mechanism for character data changes.

2. **`u_users_triggers`** on `mybb_users` — When `newpoints` changes, inserts a row into `mybb_audit_users` with `uid`, `username`, and the new `newpoints` value.

---

## Custom Game Tables

All prefixed with `mybb_op_`. Primary key is the first column listed in the PK row for each table.

### Character System

#### `mybb_op_fichas` — Main character sheet (1 per user)
PK: `fid` (= user's `uid`, one character per account)

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `fid` | int(10) | — | Character/user ID |
| `nombre` | varchar(50) | — | Character name |
| `apodo` | varchar(50) | — | Nickname/alias |
| `faccion` | varchar(20) | — | Faction (Pirata, Marina, Civil, etc.) |
| `raza` | varchar(20) | — | Race |
| `edad` | int(2) | — | Age |
| `altura` | int(4) | — | Height |
| `peso` | int(5) | — | Weight |
| `sexo` | varchar(20) | — | Gender |
| `temporada` | varchar(255) | `''` | Season created |
| `dia` | int(10) | `0` | In-game day |
| **Stats** | | | |
| `puntos_estadistica` | int(11) | `60` | Unspent stat points |
| `nivel` | int(11) | `1` | Character level |
| `limite_nivel` | int(20) | `20` | Level cap |
| `fuerza` / `fuerza_pasiva` | int(3) | `0` | Strength (base / passive bonus) |
| `resistencia` / `resistencia_pasiva` | int(3) | `0` | Resistance |
| `destreza` / `destreza_pasiva` | int(3) | `0` | Dexterity |
| `voluntad` / `voluntad_pasiva` | int(3) | `0` | Willpower |
| `punteria` / `punteria_pasiva` | int(3) | `0` | Accuracy |
| `agilidad` / `agilidad_pasiva` | int(3) | `0` | Agility |
| `reflejos` / `reflejos_pasiva` | int(3) | `0` | Reflexes |
| `control_akuma` / `control_akuma_pasiva` | int(3) | `0` | Devil Fruit control |
| **Resources** | | | |
| `vitalidad` / `vitalidad_pasiva` | int(11) | `0` | HP |
| `energia` / `energia_pasiva` | int(11) | `0` | Energy |
| `haki` / `haki_pasiva` | int(11) | `0` | Haki points |
| **Currency** | | | |
| `berries` | int(11) | `0` | Primary currency |
| `nika` | int(11) | `0` | Premium currency |
| `kuro` | int(10) | `0` | Shop currency |
| `puntos_oficio` | int(10) | `0` | Craft skill points |
| **Narrative** | | | |
| `apariencia` | mediumtext | — | Appearance description |
| `personalidad` | mediumtext | — | Personality description |
| `historia` | mediumtext | — | Backstory |
| `rasgos_positivos` | text | — | Positive traits |
| `rasgos_negativos` | text | — | Negative traits |
| `extra` | text | — | Extra info |
| `frase` | text | — | Character quote |
| `notas` | text | — | Staff notes |
| **Reputation** | | | |
| `reputacion` | int(11) | `0` | Net reputation |
| `reputacion_positiva` | int(11) | `0` | Positive reputation |
| `reputacion_negativa` | int(11) | `0` | Negative reputation |
| `reputacion2` / `_positiva2` / `_negativa2` | int(11) | `0` | Secondary reputation track |
| `rango` | varchar(255) | `'Novato'` | Rank title |
| `fama` | varchar(255) | `'Desconocido'` | Fame level |
| `wanted` | int(10) | `0` | Wanted poster value |
| `wanted_repu` | int(11) | `0` | Reputation-based wanted |
| **Combat Skills (JSON)** | | | |
| `belicas` | json | `null` | Combat mastery tree (JSON object) |
| `oficios` | json | `null` | Trade skill tree (JSON object) |
| `estilos` | json | `null` | Combat styles (JSON) |
| `elementos` | json | `null` | Elemental affinities |
| `equipamiento` | json | `null` | Equipped items |
| `belica1`–`belica12` | varchar(255) | `''` | Individual combat discipline slots |
| `oficio1`, `oficio2` | varchar(255) | `''` | Trade skill names |
| `oficio1nivel`, `oficio2nivel` | int(10) | `1` / `0` | Trade skill levels |
| `oficio2exp` | int(11) | `0` | Secondary trade XP |
| `estilo1`–`estilo4` | varchar(255) | `'bloqueado'` | Combat style slots |
| **Devil Fruit & Haki** | | | |
| `akuma` | varchar(255) | `''` | Devil Fruit ID |
| `akuma_subnombre` | varchar(255) | `''` | Devil Fruit sub-name |
| `dominio_akuma` | varchar(255) | `'0'` | Devil Fruit mastery level |
| `hao` | int(5) | `-1` | Haoshoku haki (-1=not awakened) |
| `hao_chance` | int(5) | `1` | Hao awakening chance |
| `kenbun` | int(5) | `0` | Kenbunshoku haki level |
| `buso` | int(5) | `0` | Busoshoku haki level |
| `sangre` | varchar(255) | — | Blood type |
| **Visual** | | | |
| `avatar1`–`avatar4` | varchar(255) | default images | Character portrait images |
| `banner` | text | — | Profile banner |
| `fisico_de_pj` | varchar(255) | — | Physical reference (anime character) |
| `origen_de_pj` | varchar(255) | — | Character origin/series |
| `banda_sonora` | varchar(500) | `''` | Soundtrack URL |
| `fx` | varchar(255) | `''` | Visual effects |
| **Meta** | | | |
| `aprobada_por` | varchar(20) | `'sin_aprobar'` | Approval status / staff name |
| `muerto` | int(10) | `0` | Death flag (0=alive) |
| `tiempo_creacion` | timestamp | CURRENT_TIMESTAMP | Creation timestamp |
| `espacios` | int(100) | `0` | Inventory space |
| `equipamiento_espacio` | int(11) | `5` | Equipment slots |
| `implantes` | text | — | Implants |
| `ranuras` | varchar(255) | `'0 / 6'` | Slot count string |
| `camino` | varchar(255) | `''` | Progression path |
| `secret1` | int(11) | `0` | Has secret identity |
| `cronologia` | varchar(255) | `''` | Timeline reference |
| `como_nos_conociste` | varchar(255) | — | How player found forum |
| `orientacion` | varchar(255) | — | Character orientation |
| **Adventure & Narrator** | | | |
| `aventurasActivas` | int(10) unsigned | `0` | Active adventures count |
| `slotAventuras` | int(10) unsigned | `3` | Max adventure slots |
| `expNarradorMensualActual` | int(11) | — | Monthly narrator XP (max 675) |
| `nivelnarrador` | varchar(80) | `'Aprendiz'` | Narrator rank |
| `rango_inframundo` | varchar(255) | `''` | Underworld rank |
| `movidoInframundo` | bigint(20) | `0` | Underworld transfer timestamp |
| `wantedGuardado` | varchar(255) | `''` | Saved wanted value |

#### `mybb_op_fichas_secret` — Secret alternate identity
PK: `id` (auto-increment)

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | int(10) | — | Auto-increment ID |
| `secret_number` | int(10) | `1` | Secret identity number |
| `fid` | int(10) | — | Owner character ID |
| `nombre` | varchar(50) | `'Sin Nombre'` | Secret name |
| `apodo` | varchar(50) | `''` | Secret alias |
| `faccion` | varchar(20) | `'Civil'` | Secret faction |
| `apariencia` | mediumtext | — | Appearance |
| `personalidad` | mediumtext | — | Personality |
| `historia` | mediumtext | — | Backstory |
| `extra` | text | — | Extra info |
| `rango` | varchar(255) | `'Novato'` | Secret rank |
| `avatar1`, `avatar2` | varchar(255) | default images | Portrait images |
| `es_visible` | int(11) | `0` | Visibility toggle |

#### `mybb_op_fichas_guardar` — Saved character creation drafts
PK: `fid`

Stores in-progress character creation form data (nombre, apodo, raza, faccion, apariencia, personalidad, historia, etc.) so users can save and resume later.

#### `mybb_op_fichas_audit` — Full character snapshot audit (trigger-populated)
PK: `audit_id`  |  Indexes: `idx_fid`, `idx_changed_at`

Complete mirror of `mybb_op_fichas` columns, written by the `u_fichas_triggers` trigger on every UPDATE. Includes `changed_at` timestamp.

#### `mybb_audit_op_fichas` — Lightweight character stats audit (trigger-populated)
PK: `id`

Subset of character fields (stats, currencies, skills, haki, death status) with a `Fecha` timestamp. Also populated by the `u_fichas_triggers` trigger.

---

### Items & Inventory

#### `mybb_op_objetos` — Item/object definitions (master catalog)
PK: `id`  |  Unique: none (uses `objeto_id` as logical key)

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | int(10) | — | Auto-increment |
| `objeto_id` | varchar(255) | — | Logical ID (e.g., `"Katana"`, `"Katana-123-1"` for custom) |
| `categoria` | text | — | Category |
| `subcategoria` | text | — | Subcategory |
| `nombre` | text | — | Display name |
| `tier` | int(10) | `0` | Power tier (0–5) |
| `berries` | int(11) | `0` | Price in berries |
| `dano` | text | — | Damage value |
| `bloqueo` | text | — | Block value |
| `efecto` | text | — | Effect description |
| `alcance` | text | — | Range |
| `espacios` | int(10) | `0` | Inventory slots consumed |
| `imagen` | text | — | Item image URL |
| `imagen_avatar` | text | — | Avatar-size image |
| `imagen_id` | int(10) | `0` | Image ID reference |
| `cantidadMaxima` | int(4) | `1` | Max stack size |
| `exclusivo` | tinyint(1) | `0` | Shop exclusive |
| `invisible` | int(11) | `0` | Hidden from item list |
| `desbloquear` | int(20) | `10000` | Unlock cost |
| `desbloqueado` | int(10) | `1` | Unlocked status |
| `oficio` | varchar(255) | `''` | Required trade skill |
| `nivel` | int(10) | `0` | Required craft level |
| `tiempo_creacion` | int(100) | `10000` | Craft time (seconds) |
| `requisitos` | text | — | Craft material requirements |
| `escalado` | text | — | Stat scaling |
| `editable` | int(10) | `0` | User can customize |
| `custom` | int(10) | `0` | User-created item flag |
| `descripcion` | text | — | Description |
| `comerciable` | int(1) | `0` | Tradeable flag |
| `crafteo_usuarios` | varchar(255) | `''` | User crafting data |
| `negro` | int(10) | `0` | Available on black market |
| `negro_berries` | int(100) | `9999999` | Black market price |
| `fusion_tipo` | varchar(255) | `''` | Fusion type |
| `engastes` | int(10) | `0` | Gem socket count |
| `berriesCrafteo` | bigint(20) | `0` | Craft berry cost |
| `fecha_creacion` | timestamp | CURRENT_TIMESTAMP | When item was defined |

#### `mybb_op_inventario` — User inventory
PK: `id`  |  Unique: `(objeto_id, uid)`

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | int(3) | — | Auto-increment |
| `objeto_id` | varchar(255) | — | Item ID (references `mybb_op_objetos.objeto_id`) |
| `uid` | int(10) | — | Owner user ID |
| `cantidad` | int(10) | — | Quantity |
| `tiempo` | timestamp | CURRENT_TIMESTAMP | Last updated |
| `imagen` | varchar(500) | `''` | Custom image override |
| `apodo` | varchar(255) | `''` | Custom name |
| `autor` | varchar(255) | `''` | Crafter name |
| `autor_uid` | varchar(255) | `''` | Crafter UID |
| `oficios` | json | `null` | Trade skill bonuses |
| `especial` | int(10) | `0` | Special item flag |
| `editado` | int(10) | `0` | Has been customized |
| `usado` | int(11) | `0` | Used/consumed flag |
| `vendidoReciente` | timestamp(6) | `0000...` | Recent sale timestamp |

#### `mybb_op_inventario_crafteo` — Crafting recipe unlocks
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `objeto_id` | varchar(255) | Item ID |
| `nombre` | varchar(255) | Item name |
| `uid` | int(10) | User ID |
| `desbloqueado` | int(10) | Unlock status |

#### `mybb_op_equipamiento_personaje` — Combat equipment snapshots
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | int(5) | Thread ID (combat thread) |
| `pid` | int(5) | Post ID |
| `uid` | int(10) | User ID |
| `equipamiento` | json | Equipment loadout (JSON) |
| `fecha` | timestamp | When snapshot was taken |

#### `mybb_op_cambioid` — Object ID migration/rename table
PK: `id`

Same schema as `mybb_op_objetos`. Used for bulk ID changes on items.

#### `mybb_Objetos_Inframundo` — Underworld auction house
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `objeto_id` | varchar(255) | Item being auctioned |
| `vendedor_uid` | int(11) | Seller |
| `ultimo_ofertante_uid` | int(11) | Last bidder |
| `comprador_uid` | int(11) | Buyer (after sale) |
| `precio_minimo` | int(11) | Starting price |
| `precio_actual` | int(11) | Current bid |
| `precio_compra` | int(11) | Buy-now price |
| `fecha_final_subasta` | datetime | Auction end time |
| `estado` | enum | `'activa'`, `'vendida'`, `'cancelada'`, `'finalizada_sin_venta'` |
| `cantidad` | int(11) | Quantity |

---

### Techniques & Combat

#### `mybb_op_tecnicas` — Technique definitions
PK: `tid` (string ID, e.g., `"DCO001"`, `"T001"`)

| Column | Type | Description |
|--------|------|-------------|
| `tid` | varchar(255) | Technique ID |
| `nombre` | varchar(255) | Name |
| `estilo` | varchar(255) | Combat style |
| `clase` | varchar(255) | Class/type |
| `tier` | varchar(255) | Power tier |
| `rama` | varchar(255) | Skill tree branch |
| `tipo` | varchar(255) | Type (attack, defense, etc.) |
| `exclusiva` | tinyint(1) | Exclusive technique |
| `energia` | varchar(255) | Energy cost |
| `energia_turno` | varchar(255) | Energy per turn |
| `haki` | varchar(255) | Haki cost |
| `haki_turno` | varchar(255) | Haki per turn |
| `enfriamiento` | varchar(255) | Cooldown |
| `efectos` | varchar(511) | Effects description |
| `requisitos` | varchar(255) | Requirements |
| `descripcion` | mediumtext | Full description |

#### `mybb_op_tec_aprendidas` — Learned techniques
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | varchar(255) | Technique ID |
| `uid` | int(3) | User who learned it |
| `tiempo` | timestamp | When learned |

#### `mybb_op_tec_para_aprender` — Pending technique queue (staff-granted)
PK: `id`

Same schema as `tec_aprendidas`. Techniques queued for learning.

#### `mybb_op_tecnicas_usuarios` — Active technique training queue
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | varchar(255) | Technique being trained |
| `uid` | int(3) | User ID |
| `nombre` | varchar(100) | User name |
| `tiempo_iniciado` | int(100) | Unix timestamp started |
| `tiempo_finaliza` | int(100) | Unix timestamp completion |
| `duracion` | int(100) | Duration in seconds |
| `costo_pr` | int(100) | Stat point cost |
| `recompensa` | int(100) | Stat point reward |

#### `mybb_op_tecnicas_mantenidas` — Active sustained techniques in combat
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(11) | User ID |
| `tid` | int(11) | Thread ID |
| `tecnica_id` | varchar(100) | Technique ID |
| `pid_inicio` | int(11) | Post ID where activated |
| `turnos` | int(11) | Turns active (default 1) |
| `activa` | tinyint(1) | Currently active |

#### `mybb_op_thread_personaje` — Combat thread stat snapshots
PK: `id`

Stores character stats at the moment they enter a combat thread. All 8 base stats + passives + resources + level.

| Column | Type | Description |
|--------|------|-------------|
| `tid` | int(5) | Thread ID |
| `pid` | int(5) | Post ID |
| `uid` | int(10) | User ID |
| `fuerza`…`control_akuma` | int(3) | Base stats snapshot |
| `*_pasiva` | int(3) | Passive bonus snapshot |
| `vitalidad`, `energia`, `haki` | int(5) | Resource snapshot |
| `nombre` | text | Character name |
| `nivel` | int(11) | Level at time of entry |

#### `mybb_op_dados` — Dice roll history
PK: `did`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | int(11) | Thread ID |
| `pid` | int(11) | Post ID |
| `uid` | int(11) | Roller user ID |
| `dado_counter` | int(11) | Roll index within post |
| `dado_content` | text | Roll result content (HTML) |

---

### Devil Fruits & Powers

#### `mybb_op_akumas` — Devil Fruit definitions
PK: `akuma_id` (string, e.g., `"gomu"`)

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `akuma_id` | varchar(20) | — | Fruit ID |
| `nombre` | varchar(50) | — | Fruit name |
| `subnombre` | varchar(50) | — | Sub-name |
| `categoria` | varchar(100) | — | Category (Paramecia, Zoan, Logia) |
| `tier` | int(5) | — | Power tier |
| `descripcion` | text | — | Description |
| `uid` | varchar(20) | `'1'` | Owner user ID |
| `es_npc` | varchar(255) | `'0'` | NPC-owned flag |
| `es_oculta` | varchar(255) | `'0'` | Hidden from registry |
| `ocupada` | tinyint(1) | `0` | Currently assigned |
| `imagen` | varchar(255) | mystery image | Fruit image |
| `detalles` | text | — | Extra details |
| `dominio1`–`dominio3` | text | — | Mastery level descriptions |
| `pasiva1`–`pasiva3` | text | — | Passive ability descriptions |
| `reservas` | varchar(255) | `''` | Reservation UIDs |
| `reservasFecha` | varchar(255) | `''` | Reservation dates |

#### `mybb_op_virtudes` — Virtue/defect definitions
PK: `virtud_id` (string, e.g., `"V001"`, `"D024"`)

| Column | Type | Description |
|--------|------|-------------|
| `virtud_id` | varchar(30) | Virtue/defect ID |
| `nombre` | varchar(50) | Name |
| `puntos` | int(5) | Point cost (positive=virtue, negative=defect) |
| `requisito` | tinyint(1) | Is a prerequisite |
| `descripcion` | text | Description |

#### `mybb_op_virtudes_usuarios` — User virtues/defects
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `virtud_id` | varchar(255) | Virtue/defect ID |
| `uid` | int(3) | User ID |

---

### NPCs & Companions

#### `mybb_op_npcs` — NPC definitions
PK: `npc_id` (string)

Full character sheet for NPCs including: name, faction, race, all 8 stats + resources, appearance/personality/history, combat styles (`belica1`–`belica8`, `estilo1`–`estilo4`), trades (`oficio1`, `oficio2`), haki (`buso`, `kenbun`, `haoshoku`), and a `info` JSON field. Stores `etiqueta` (label) and `reputacion`.

#### `mybb_op_npcs_usuarios` — User-owned NPC companions
PK: `id`

Same structure as `mybb_op_mascotas` (below). Links an NPC to a user with custom combat/trade skills.

#### `mybb_op_mascotas` — Pet/companion records
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `npc_id` | varchar(50) | NPC template ID |
| `nombre` | varchar(50) | Pet name |
| `avatar1` | varchar(255) | Image |
| `usuario` | varchar(255) | Owner username |
| `etiqueta` | varchar(255) | Tag/label |
| `estilo1`–`estilo4` | varchar(255) | Combat styles |
| `belica1`–`belica8` | varchar(255) | Combat disciplines |
| `belicas`, `oficios`, `estilos` | text | JSON-ish skill data |
| `oficio1`, `oficio2` | varchar(255) | Trade skills |

---

### Economy & Trading

#### `mybb_op_intercambios` — Player-to-player trades
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` / `nombre` / `faccion` | — | Sender |
| `r_uid` / `r_nombre` / `r_faccion` | — | Receiver |
| `tid` | int(10) | Thread where trade occurred |
| `objetos` | varchar(255) | Item IDs transferred |
| `objetos_nombre` | varchar(1000) | Item names |
| `razon` | text | Trade reason |
| `dinero` | int(100) | Berries transferred |
| `timestamp` | int(100) | Unix timestamp |

#### `mybb_op_kuros` — Kuro shop catalog
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `objeto_id` | varchar(255) | Item ID |
| `nombre` | text | Item name |
| `berries` | int(11) | Kuro price |
| `cantidadMaxima` | int(4) | Max purchase |
| `exclusivo` | tinyint(1) | Exclusive item |

#### `mybb_op_codigos_admin` — Promotional codes (admin-created)
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `codigo` | varchar(255) | Code string |
| `expiracion_codigo` | int(100) | Expiry timestamp |
| `duracion` | int(100) | Duration |
| `categoria` | varchar(100) | Reward category |
| `uso_unico` | tinyint(1) | Single-use code |
| `usado` | tinyint(1) | Already redeemed |

#### `mybb_op_codigos_usuarios` — Redeemed codes per user
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(3) | User ID |
| `codigo` | varchar(100) | Code redeemed |
| `categoria` | varchar(100) | Reward category |
| `expiracion` | int(100) | Expiry |

#### `mybb_op_recompensas_usuarios` — Seasonal reward tracking
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(11) | User ID |
| `nombre` | varchar(255) | Username |
| `dia` | int(11) | In-game day |
| `season` | int(11) | Season number |
| `tiempo` | int(11) | Claim timestamp |

---

### Training & Progression (Time-Gated)

All training tables use `timestamp_end` (Unix timestamp) for completion time.

#### `mybb_op_entrenamientos_usuarios` — Stat training queue
PK: `id`  |  Unique: `uid` (one active training per user)

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(3) | User ID |
| `nombre` | varchar(100) | Username |
| `timestamp_end` | int(100) | Completion time |
| `duracion` | int(100) | Duration in seconds |
| `costo_pr` | int(100) | Stat point cost |
| `recompensa` | int(100) | Stat point reward |

#### `mybb_op_crafteo_usuarios` — Player crafting queue
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(3) | User ID |
| `objeto_id` | varchar(100) | Item being crafted |
| `nombre` | varchar(100) | Item name |
| `material_id` | varchar(255) | Material used |
| `timestamp_end` | int(100) | Completion time |
| `duracion` | int(100) | Duration |
| `costo` | int(100) | Berry cost |

#### `mybb_op_crafteo_npcs` — NPC crafting queue
PK: `id`  |  Indexes: `uid`, `npc_id`

Same as `crafteo_usuarios` but for NPC-initiated crafting. Adds `npc_id` field.

#### `mybb_op_creacion_usuarios` — Item creation queue (ticket-based)
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(3) | User ID |
| `ticket` | varchar(100) | Creation ticket ID |
| `nombre_ticket` | varchar(100) | Ticket name |
| `timestamp_end` | int(100) | Completion time |
| `duracion` | int(100) | Duration |
| `nikas_costo` | int(100) | Nika cost |

#### `mybb_op_oficios_usuarios` — Trade skill training queue
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(3) | User ID |
| `oficio` | varchar(100) | Trade skill name |
| `timestamp_end` | int(100) | Completion time |
| `duracion` | int(100) | Duration |
| `experiencia` | int(100) | XP reward |

#### `mybb_op_experiencia_limite` — Weekly XP caps
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(10) | User ID |
| `semana` | int(10) | Week number |
| `experiencia_semanal` | float | XP earned this week |

---

### World & Travel

#### `mybb_op_islas` — Island definitions
PK: `isla_id` (string)

| Column | Type | Description |
|--------|------|-------------|
| `isla_id` | varchar(50) | Island ID |
| `nombre` | varchar(50) | Island name |
| `gobierno` | text | Government type |
| `faccion` | text | Controlling faction |
| `comercio` | text | Commerce info |
| `tamano` | text | Size |
| `zonas` | text | Zones/areas |
| `habitantes` | text | Inhabitants |
| `facilities` | text | Conqueror-built structures |

#### `mybb_op_isla_eventos` — Island historical events
PK: `evento_id`  |  Indexes: `isla_id`, `staff_uid`, `(ano, estacion, dia)`

| Column | Type | Description |
|--------|------|-------------|
| `isla_id` | int(10) unsigned | Island ID (forum fid) |
| `titulo` | varchar(255) | Event title |
| `descripcion` | text | Event description |
| `ano` | int(11) | In-game year |
| `estacion` | enum | `'Primavera'`, `'Verano'`, `'Otoño'`, `'Invierno'` |
| `dia` | tinyint(2) | Day of season (1–90) |
| `staff_uid` | int(10) | Staff who created event |

#### `mybb_op_viajes` — Travel/sailing records
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid_viaje` | int(10) | Travel organizer |
| `nombre` | varchar(50) | Organizer name |
| `mar` | varchar(50) | Sea/ocean |
| `partida` | varchar(1000) | Departure location |
| `llegada` | varchar(50) | Destination |
| `dificultad` | varchar(1000) | Difficulty data |
| `modificador` | varchar(50) | Difficulty modifier |
| `horas` | text | Travel hours |
| `temporada` | varchar(255) | Season |
| `fecha_salida` / `fecha_llegada` | varchar(255) | Departure/arrival dates |
| `viajeros` | varchar(1000) | Comma-separated traveler UIDs |
| `log` | text | Travel event log |
| `postViaje` | text | Post-travel narrative |
| `dado_naval` | int(11) | Naval event die roll (-1=none) |

#### `mybb_op_barcos` — Ship definitions
PK: `barco_id` (string)

| Column | Type | Description |
|--------|------|-------------|
| `barco_id` | varchar(80) | Ship ID |
| `nombre_barco` | varchar(80) | Ship name |
| `vitalidad` | int(11) | Hull HP |
| `espacios` | int(11) | Cargo slots |
| `velocidad` | int(11) | Speed |
| `tiempo_viaje` | int(11) | Travel time modifier |
| `resistencia` | int(11) | Armor |
| `espacios_mejora` | int(11) | Upgrade slots |
| `ruputura` | int(11) | Break threshold |

#### `mybb_op_razas` — Race definitions
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `nombre` | varchar(255) | Race name |
| `descripcion` | text | Description |
| `caracteristicas` | text | Racial traits |

#### `mybb_op_mapa_posiciones` — Interactive map positions
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | int(10) | Thread ID |
| `uid` | int(10) | User ID |
| `fid` | int(10) | Forum ID |
| `x_percent` | decimal(6,3) | X position (%) |
| `y_percent` | decimal(6,3) | Y position (%) |

---

### Gacha / Roll Systems

#### `mybb_op_tirada_akumas` — Devil Fruit gacha results
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(10) | User ID |
| `nombre` | varchar(20) | Username |
| `tier` | int(10) | Fruit tier rolled |
| `fruta` | varchar(200) | Fruit name |
| `subnombre` | varchar(200) | Fruit sub-name |
| `real` | int(10) | Was it a real fruit (vs. fake) |

#### `mybb_op_tirada_cofre` — Chest gacha results
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid`, `nombre` | — | User |
| `tier` | varchar(200) | Chest tier |
| `objeto` | varchar(200) | Item won |
| `objeto_id` | varchar(200) | Item ID |
| `cofre_random` | varchar(255) | Random seed |

#### `mybb_op_tirada_haki` — Haki awakening roll results
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid`, `nombre` | — | User |
| `haki` | varchar(200) | Haki type |
| `subnombre` | varchar(200) | Haki sub-type |
| `real` | int(10) | Success flag |

#### `mybb_op_tirada_rey` — Haoshoku (King) roll results
PK: `id`

Same as `tirada_haki` plus `tirada_random` (varchar, default `'21'`).

#### `mybb_op_tiradanaval` — Naval encounter rolls (in-thread)
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | int(11) | Thread ID |
| `pid` | int(11) | Post ID |
| `uid` | int(11) | Roller |
| `counter` | int(11) | Roll index |
| `content` | text | Roll result HTML |

#### `mybb_op_cofres` — Chest loot tables
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `cofre_id` | varchar(255) | Chest type ID |
| `objeto_id` | varchar(255) | Possible item |
| `nombre` | varchar(255) | Item name |
| `tipo` | varchar(255) | Item type |
| `peso` | int(11) | Drop weight (probability) |
| `cantidad` | int(11) | Quantity awarded |

---

### Adventures & Missions

#### `mybb_op_peticionAventuras` — Adventure requests
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(10) | Requesting user |
| `narrador_uid` / `narrador_fid` / `narrador_nombre` | — | Assigned narrator |
| `tier_seleccionado` | tinyint(3) | Adventure tier |
| `nivel_promedio` | tinyint(3) | Average party level |
| `num_jugadores` | tinyint(3) | Player count |
| `dificultad_texto` | varchar(20) | Difficulty label |
| `dificultad_color` | varchar(7) | Difficulty color hex |
| `ratio_poder` | decimal(10,4) | Power ratio |
| `jugadores_json` | mediumtext | Player list (JSON) |
| `enemigos_json` | mediumtext | Enemy list (JSON) |
| `detalles_json` | mediumtext | Extra details (JSON) |
| `estado` | int(10) | Status: 0=created, 1=approved, 2=denied, 3=finished, 4=pending narrator, 5=deletion request |
| `aventura_url` | varchar(255) | Thread URL |
| `inframundo` | tinyint(1) | Underworld adventure flag |

#### `mybb_op_peticionAventuras_meta` — Adventure request metadata
PK: `peticion_id`

Staff notes, public comments, approval status (`'pendiente'`, `'aprobada'`, `'denegada'`).

#### `mybb_op_misiones_lista` — Mission catalog
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `cod` | int(100) | Unique mission code |
| `rango` | text | Required rank |
| `niv` | int(100) | Required level |
| `title` | text | Mission title |
| `descripcion` | text | Mission description |
| `ryos` | int(100) | Currency reward |
| `expt` | int(100) | XP reward |
| `time` | int(100) | Duration |
| `coste` | int(10) | Entry cost |

---

### BBCode & In-Thread Systems

#### `mybb_op_consumir` — Item consumption events (from `[consumir=ID]` BBCode)
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tid` | int(11) | Thread ID |
| `pid` | int(11) | Post ID |
| `uid` | int(11) | User who consumed |
| `counter` | int(11) | Consumption index in post |
| `objeto_id` | varchar(255) | Item consumed |
| `content` | text | Display content |

#### `mybb_op_hide` — Hidden content blocks (from `[hide]` BBCode)
PK: `hid`

| Column | Type | Description |
|--------|------|-------------|
| `tid`, `pid`, `uid` | int | Thread, post, author |
| `hide_counter` | int(11) | Hide index in post |
| `show_hide` | int(1) | Visibility toggle |
| `hide_uids` | varchar(500) | UIDs that can see |
| `hide_content` | text | Hidden content |

#### `mybb_op_mantenidas_html` — Cached HTML for sustained techniques
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `pid` | int(11) | Post ID |
| `html_content` | text | Pre-rendered HTML |

---

### Social & Content

#### `mybb_op_likes` — Post like system
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `pid` | int(10) | Post ID |
| `tid`, `fid` | — | Thread/forum IDs |
| `uid`, `username` | — | Post author |
| `liked_by_uid`, `liked_by_username` | — | Who liked |
| `liked_by_timestamp` | timestamp | When liked |

#### `mybb_op_leidos` — Post read tracking
PK: `id`  |  Unique: `(pid, uid)`

Simple `pid` + `uid` join table.

#### `mybb_op_hentai` — Adult content preference
PK: `uid`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(10) | User ID |
| `enable_hentai` | tinyint(1) | Opt-in flag |

#### `mybb_op_sabiasque` — "Did you know?" facts
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `tipo` | int(3) | Fact type/category |
| `texto` | varchar(1000) | Fact text |
| `autor` | varchar(100) | Author |

#### `mybb_op_adviento_abiertos` — Advent calendar tracking
PK: `id`  |  Unique: `(uid, dia, anio)`

Tracks which advent calendar days each user has opened.

---

### Moderation & Notifications

#### `mybb_op_peticiones` — User requests/petitions
PK: `id`

| Column | Type | Description |
|--------|------|-------------|
| `uid` | int(11) | Requesting user |
| `nombre` | varchar(20) | Username |
| `categoria` | varchar(100) | Request category |
| `resumen` | varchar(255) | Summary |
| `descripcion` | text | Full description |
| `url` | varchar(255) | Related URL |
| `resuelto` | tinyint(1) | Resolved flag |
| `mod_uid` / `mod_nombre` | — | Assigned moderator |
| `atendidoPor` | varchar(255) | Handled by |
| `notasMod` | varchar(255) | Moderator notes |

#### `mybb_op_avisos` — System warnings/notifications
PK: `id`

Same structure as peticiones (uid, categoria, resumen, descripcion, url, resuelto, mod fields).

#### `mybb_op_avisosvpn` — VPN detection alerts
No PK (append-only)

| Column | Type | Description |
|--------|------|-------------|
| `usuario` | text | Username |
| `uid` | int(11) | User ID |
| `fechaConexion` | datetime | Connection time |
| `ip` | text | Detected IP |

---

### Audit & Logging Tables

All audit tables are append-only logs. Most have an auto-increment `id` PK and a timestamp column.

| Table | Tracks | Key Columns |
|-------|--------|-------------|
| `audit_general` | All major game actions | `uid`, `username`, `user_uid`, `categoria`, `log` |
| `audit_stats` | Stat point changes | `fid`, all 8 stats + `control_haki` + `control_akuma` |
| `audit_crafteo` | Crafting completions | `uid`, `nombre`, `log` |
| `audit_creacion` | Item creation completions | `uid`, `nombre`, `log` |
| `audit_entrenamientos` | Stat training completions | `fid`, `puntos_estadistica`, `pr` |
| `audit_entrenamiento_tecnicas` | Technique training | `fid`, `tid`, `tiempo_iniciado`, `tiempo_finaliza` |
| `audit_oficios` | Trade skill training | `fid`, `oficio`, `progreso`, `experiencia` |
| `audit_recompensas` | Reward distribution | `uid`, `dia`, `season`, `audit` |
| `audit_descripcion` | Character description edits | `fid`, `apariencia`, `personalidad`, `biografia` |
| `audit_consola_mod` | Moderator console actions | `staff`, `username`, `razon`, `log` |
| `audit_consola_tec` | Technique console actions | `staff`, `razon`, `log` |
| `audit_consola_tec_mod` | Technique modification | `staff`, `razon`, `log` |
| `auditoria_posts_ia` | AI-flagged posts | `pid`, `uid`, `mensaje_original`, `indicadores` |

---

### Key MyBB Core Tables (used by game system)

#### `mybb_users` — User accounts
PK: `uid`

Key game-relevant columns beyond standard MyBB:
- `newpoints` (decimal 16,2) — Experience/role points (monitored by trigger)
- `avatar` / `avatar2` (varchar 200) — Profile images
- `usergroup` (smallint) — Primary group
- `additionalgroups` (varchar 200) — Secondary groups (checked for roles: group 15 = narrator)
- `as_uid`, `as_share`, `as_shareuid`, `as_sec`, `as_privacy`, `as_buddyshare`, `as_secreason` — Account switcher plugin fields

#### `mybb_posts` — Forum posts
PK: `pid`

Key game-relevant columns:
- `faccionsecreta` (varchar 80) — Secret faction override for this post (custom field)
- Standard: `tid`, `fid`, `uid`, `username`, `message`, `dateline`, `visible`

#### `mybb_threads` — Forum threads
PK: `tid`

Key game-relevant columns:
- `prefix` (smallint) — Thread type: 3=Aventura, 10=MT, 1=Común, 6=Evento, 9=Autonarrada, 14=Requerimiento
- `year` (varchar 255) — In-game year
- `estacion` (varchar 255) — In-game season
- `day` (varchar 255) — In-game day

---

### Plugin Tables

#### `mybb_newpoints_*` — NewPoints currency plugin
- `mybb_newpoints_forumrules` — Per-forum point rules
- `mybb_newpoints_grouprules` — Per-group point rules
- `mybb_newpoints_log` — Point transaction log
- `mybb_newpoints_settings` — Plugin settings

#### `mybb_rtchat` / `mybb_rtchat_bans` — Real-time chat
- `mybb_rtchat` — Chat messages
- `mybb_rtchat_bans` — Chat bans

#### `mybb_rt_discord_webhooks` / `_logs` — Discord integration
- `mybb_rt_discord_webhooks` — Webhook configurations
- `mybb_rt_discord_webhooks_logs` — Webhook execution logs (stores Discord message_id, channel_id)

---

### Backup Tables

- `back_up_audit_recompensas` — Backup of reward audit data
- `back_up_recompensas` — Backup of reward tracking data

---

## Entity Relationships

```
mybb_users.uid ─────────────── mybb_op_fichas.fid (1:1)
                │
                ├── mybb_op_inventario.uid (1:N)
                ├── mybb_op_tec_aprendidas.uid (1:N)
                ├── mybb_op_tec_para_aprender.uid (1:N)
                ├── mybb_op_tecnicas_usuarios.uid (1:N, training queue)
                ├── mybb_op_virtudes_usuarios.uid (1:N)
                ├── mybb_op_entrenamientos_usuarios.uid (1:1, active training)
                ├── mybb_op_crafteo_usuarios.uid (1:N)
                ├── mybb_op_creacion_usuarios.uid (1:N)
                ├── mybb_op_oficios_usuarios.uid (1:N)
                ├── mybb_op_fichas_secret.fid (1:N)
                ├── mybb_op_hentai.uid (1:1)
                ├── mybb_op_experiencia_limite.uid (1:N per week)
                ├── mybb_op_codigos_usuarios.uid (1:N)
                ├── mybb_op_recompensas_usuarios.uid (1:N)
                ├── mybb_op_intercambios.uid / r_uid (N:N)
                ├── mybb_op_tirada_*.uid (1:N, all gacha tables)
                └── mybb_op_audit_general.uid (1:N)

mybb_op_objetos.objeto_id ──── mybb_op_inventario.objeto_id (1:N)
                │
                ├── mybb_op_crafteo_usuarios.objeto_id (1:N)
                ├── mybb_op_consumir.objeto_id (1:N)
                ├── mybb_op_cofres.objeto_id (1:N)
                └── mybb_Objetos_Inframundo.objeto_id (1:N)

mybb_op_tecnicas.tid ──────── mybb_op_tec_aprendidas.tid (1:N)
                │
                ├── mybb_op_tec_para_aprender.tid (1:N)
                ├── mybb_op_tecnicas_usuarios.tid (1:N)
                └── mybb_op_tecnicas_mantenidas.tecnica_id (1:N)

mybb_op_virtudes.virtud_id ── mybb_op_virtudes_usuarios.virtud_id (1:N)

mybb_op_akumas.akuma_id ───── mybb_op_fichas.akuma (1:1)

mybb_op_npcs.npc_id ───────── mybb_op_npcs_usuarios.npc_id (1:N)
                └──────────── mybb_op_mascotas.npc_id (1:N)

mybb_threads.tid ──────────── mybb_op_thread_personaje.tid (1:N)
                │
                ├── mybb_op_dados.tid (1:N)
                ├── mybb_op_consumir.tid (1:N)
                ├── mybb_op_hide.tid (1:N)
                ├── mybb_op_tecnicas_mantenidas.tid (1:N)
                ├── mybb_op_tiradanaval.tid (1:N)
                └── mybb_op_viajes (referenced via forum threads)

mybb_op_islas.isla_id ─────── mybb_op_isla_eventos.isla_id (1:N)

mybb_op_cofres.cofre_id ───── (grouped by cofre_id, multiple loot rows per chest type)
```

## Notes

- **No foreign keys are enforced** — All relationships are logical, maintained by application code. There are no `FOREIGN KEY` constraints in the schema.
- **Mixed engines** — Some tables use InnoDB, others MyISAM. The InnoDB tables support transactions; MyISAM tables do not.
- **ID patterns** — `objeto_id` uses string IDs. Custom user items append `-{uid}-{count}` to the base ID. Technique IDs use prefixed codes (`DCO001`, `T001`, etc.). Virtue IDs use `V***`/`D***` format.
- **JSON columns** — `fichas.belicas`, `fichas.oficios`, `fichas.estilos`, `fichas.elementos`, `fichas.equipamiento`, and `npcs.info` store structured data as native JSON columns.
- **Timestamps** — Some tables use `int` Unix timestamps, others use `timestamp` MySQL type. No consistency enforced.
- **`mybb_op_fichas` has a trigger** that creates a row in `mybb_audit_op_fichas` on every UPDATE. This means frequent writes to the audit table on any character change.
