# Implementación: sección de Afiliados para One Piece Gaiden (OPG)

Este documento aterriza la guía portable [`afiliados_prompt_portable.md`](afiliados_prompt_portable.md)
a **este** foro (mybb-opg). Ya no hay placeholders: todos los prefijos, rutas,
archivos y patrones están resueltos contra la estructura real del repo.

> **Origen:** la guía portable se escribió pensando en un foro "de estructura
> similar a este". OPG **es** ese foro — de hecho los nombres públicos que la
> guía ya traía confirmados (Almirante / Capitanes / Piratas) son temática One
> Piece y encajan directo acá.

---

## 0. Mapeo de placeholders → valores reales de OPG

| Placeholder de la guía | Valor real en OPG |
|---|---|
| `{{PREFIX}}` | `op` → tablas `mybb_op_*`, funciones `op_afiliados_*` |
| `{{PREFIX_UPPER}}` | `OP` → constantes `OP_AFILIADOS_*` |
| `{{FUNCTIONS_FILE}}` | [`op/functions/op_functions.php`](../op/functions/op_functions.php) (se `require_once`a en cada request de `/op/`) |
| `{{ADMIN_DIR}}` | [`op/staff/`](../op/staff/) |
| `{{RUTA}}` (páginas públicas) | `/op/` |
| `{{PETICIONES_SISTEMA}}` | **YA EXISTE** → tabla `mybb_op_peticiones` + consola [`op/staff/peticiones_admin.php`](../op/staff/peticiones_admin.php). **Se reusa** (ver §6). |

### Cabeceras reales de este foro (copiar tal cual)

Página pública en `/op/` (ej. `op/peticion_afiliados.php`):
```php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticion_afiliados.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
```

Herramienta de Staff en `/op/staff/` (ej. `op/staff/gestionar_afiliados.php`) —
ojo, **dos niveles arriba**:
```php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'gestionar_afiliados.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";
```

---

## 0.1 Decisiones de producto (adoptadas de la guía; confirmar antes de codear)

La guía traía estas decisiones ya cerradas para un foro One-Piece-like. Encajan
con OPG, así que las **adopto como default** — pero son elecciones de producto,
no técnicas: confirmalas antes de implementar.

1. **Tope duro** (no mínimo a completar): **4** espacios `grande`, **24**
   `pequeno`. Si hay más activos que el tope, los sobrantes no se muestran
   (`array_slice`, §3). El nivel `hermano` es 1 solo espacio.
2. `hermano` **es editable** (imagen y nombre) igual que los otros niveles.
3. El nivel deseado va **libre en el mensaje** del formulario público (sin
   selector).
4. **No** se sube imagen en el formulario público (puro texto; el logo se
   coordina después con Staff).
5. Nombres públicos: **Afiliado Almirante** (`hermano`), **Afiliados Capitanes**
   (`grande`), **Afiliados Piratas** (`pequeno`).
6. La solicitud pública **es una petición más**: entra a `mybb_op_peticiones`
   con `categoria='afiliados'` y Staff la revisa desde la consola existente. Sin
   acción automática sobre `mybb_op_afiliados`.

---

## 1. Qué se construye

Sección de afiliados en el índice del foro, con **3 niveles internos** (`tipo`
fijo en DB/código; el nombre público es solo texto):

| `tipo` interno | Nombre público | Tope | Muestra |
|---|---|---|---|
| `hermano` | **Afiliado Almirante** | 1 | solo imagen |
| `grande`  | **Afiliados Capitanes** | 4 | solo imagen (tile más grande, 16:9) |
| `pequeno` | **Afiliados Piratas** | 24 | solo imagen (grid de cuadritos) |

- Los 3 niveles van **en una fila, 3 columnas** side-by-side, con **la misma
  altura** las tres.
- Espacios vacíos → placeholder "+" que linkea al formulario público.
- Staff gestiona todo (alta / **edición in-place** / activar-desactivar /
  eliminar) desde una consola propia.

---

## 2. Paso 1 — Esquema de base de datos

Convención de OPG: motor mixto InnoDB/MyISAM, prefijo `mybb_op_`, sin foreign
keys (relaciones por código). Este esquema sigue el resto de tablas del juego.

```sql
CREATE TABLE `mybb_op_afiliados` (
  `id` int(11) NOT NULL,
  `tipo` enum('hermano','grande','pequeno') COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `agregado_por` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_afiliados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_orden` (`tipo`, `orden`);

ALTER TABLE `mybb_op_afiliados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Rate-limit del formulario público (no exige login).
CREATE TABLE `mybb_op_afiliados_rate_limit` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_afiliados_rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_tiempo` (`ip`, `tiempo`);

ALTER TABLE `mybb_op_afiliados_rate_limit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
```

Una sola tabla para los 3 niveles (misma forma, distinto `tipo`) para no
triplicar CRUD ni queries. **No** necesitás una tabla de solicitudes: se reusa
`mybb_op_peticiones` (§6).

> Registrá estas tablas también en `DATABASE.md` cuando las crees, para no
> romper la convención de "toda tabla documentada".

---

## 3. Paso 2 — Funciones backend

Agregar a [`op/functions/op_functions.php`](../op/functions/op_functions.php).
Correr `php -l` después.

```php
define('OP_AFILIADOS_SLOTS_GRANDE', 4);    // tope duro (§0.1)
define('OP_AFILIADOS_SLOTS_PEQUENO', 24);  // tope duro (§0.1)

// Render de una tarjeta pública. Los 3 niveles muestran SOLO imagen (decisión
// confirmada); el nombre queda en el title del link. El $tier solo cambia la
// clase CSS (tamaño/proporción del tile por nivel).
function op_afil_card_html($a, $tier) {
    $nombre = htmlspecialchars($a['nombre'], ENT_QUOTES);
    $url    = htmlspecialchars($a['url'], ENT_QUOTES);
    $img    = htmlspecialchars($a['imagen'], ENT_QUOTES);

    return '<a class="afil-card afil-card--' . $tier . '" href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow" title="' . $nombre . '">'
        . '<img class="afil-card__img" src="' . $img . '" alt="' . $nombre . '" loading="lazy" onerror="this.style.opacity=0.2">'
        . '</a>';
}

// Cuadro vacío → link a la solicitud pública (sin parámetro de nivel).
function op_afil_slot_vacio_html($tier, $bburl) {
    $url = htmlspecialchars($bburl . '/op/peticion_afiliados.php', ENT_QUOTES);
    return '<a class="afil-card afil-card--vacio afil-card--' . $tier . '" href="' . $url . '" title="Solicitar afiliación">'
        . '<span class="afil-slot-plus">+</span>'
        . '</a>';
}

// HTML completo de la sección para el índice.
function op_afiliados_index_html() {
    global $db, $mybb;
    $bburl = isset($mybb->settings['bburl']) ? $mybb->settings['bburl'] : '';

    $filas = array('hermano' => array(), 'grande' => array(), 'pequeno' => array());
    $q = $db->query("SELECT * FROM `mybb_op_afiliados` WHERE activo=1 ORDER BY tipo, orden, id");
    while ($r = $db->fetch_array($q)) {
        if (isset($filas[$r['tipo']])) { $filas[$r['tipo']][] = $r; }
    }

    if (!empty($filas['hermano'])) {
        $hermano_html = op_afil_card_html($filas['hermano'][0], 'hermano');
    } else {
        $hermano_html = '<div class="afil-empty">Todavía no hay afiliado hermano.</div>';
    }

    // Tope duro: array_slice corta a la constante ANTES de armar el HTML.
    $grande_visibles = array_slice($filas['grande'], 0, OP_AFILIADOS_SLOTS_GRANDE);
    $grandes_html = '';
    foreach ($grande_visibles as $a) { $grandes_html .= op_afil_card_html($a, 'grande'); }
    $faltan_grande = max(0, OP_AFILIADOS_SLOTS_GRANDE - count($grande_visibles));
    for ($i = 0; $i < $faltan_grande; $i++) { $grandes_html .= op_afil_slot_vacio_html('grande', $bburl); }

    $pequeno_visibles = array_slice($filas['pequeno'], 0, OP_AFILIADOS_SLOTS_PEQUENO);
    $pequenos_html = '';
    foreach ($pequeno_visibles as $a) { $pequenos_html .= op_afil_card_html($a, 'pequeno'); }
    $faltan_pequeno = max(0, OP_AFILIADOS_SLOTS_PEQUENO - count($pequeno_visibles));
    for ($i = 0; $i < $faltan_pequeno; $i++) { $pequenos_html .= op_afil_slot_vacio_html('pequeno', $bburl); }

    return '
    <div class="afil-columns">
      <section class="afil-section afil-col afil-col--hermano">
        <div class="afil-section__head"><span class="afil-section__title">Afiliado Almirante</span></div>
        <div class="afil-hermano">' . $hermano_html . '</div>
      </section>
      <section class="afil-section afil-col afil-col--grande">
        <div class="afil-section__head"><span class="afil-section__title">Afiliados Capitanes</span></div>
        <div class="afil-grid afil-grid--grande">' . $grandes_html . '</div>
      </section>
      <section class="afil-section afil-col afil-col--pequeno">
        <div class="afil-section__head"><span class="afil-section__title">Afiliados Piratas</span></div>
        <div class="afil-grid afil-grid--pequeno">' . $pequenos_html . '</div>
      </section>
    </div>
    ';
}

// Rate-limit por IP (form sin login): 1 solicitud cada $minutos. Limpia >1 día.
function op_afiliados_rate_limit_ok($ip, $minutos = 10) {
    global $db;
    $ip_esc = $db->escape_string($ip);

    $db->query("DELETE FROM `mybb_op_afiliados_rate_limit` WHERE tiempo < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    $q = $db->query("SELECT id FROM `mybb_op_afiliados_rate_limit` WHERE ip='$ip_esc' AND tiempo > DATE_SUB(NOW(), INTERVAL $minutos MINUTE) LIMIT 1");
    if ($db->fetch_array($q)) { return false; }

    $db->query("INSERT INTO `mybb_op_afiliados_rate_limit` (`ip`) VALUES ('$ip_esc')");
    return true;
}
```

Los strings **"Afiliado Almirante / Capitanes / Piratas"** son solo el texto
público. El `tipo` interno (`hermano`/`grande`/`pequeno`) no cambia nunca: si el
naming temático cambia, se editan estos 3 strings acá y listo.

> **Consistencia con OPG:** el resto del código del foro arma queries por
> interpolación de strings (ver "Known Gotchas" en CLAUDE.md). Acá se hace igual
> pero cuidando el input: `escape_string()` en el rate-limit y validación
> server-side en la consola (§6). No introduzcas SQL con input crudo del form.

---

## 4. Paso 3 — Enganchar la sección al índice

El índice de este foro termina en [index.php:518-519](../index.php#L518-L519):

```php
eval('$index = "'.$templates->get('index').'";');
output_page($index);
```

> **Opción elegida para OPG: Opción B (editar el core).** Se prefiere por ser más
> simple de deployar y debuggear. La Opción A queda documentada abajo solo como
> alternativa; no se implementa.

**✅ Opción B — editar el core (ELEGIDA).** Insertar justo **antes** de la
línea 518:

```php
$index_afiliados = function_exists('op_afiliados_index_html') ? op_afiliados_index_html() : '';

eval('$index = "'.$templates->get('index').'";');
output_page($index);
```

Y en el template `index` (editable desde `templates/mybb_templates/index.html` +
reimportar, ver abajo), un placeholder donde quieras que aparezca la sección:

```html
<div class="op-index-hook">{$index_afiliados}</div>
```

> **Trade-off consciente:** `index.php` es core de MyBB. Un update de MyBB podría
> pisarlo. Dado que OPG ya edita el core en varios lados, es aceptable — pero
> **anotalo** (acá y en un comentario en `index.php`) para reaplicarlo tras un
> update.

**Opción A — hook de plugin (alternativa, NO elegida).** Registrar un plugin en
`inc/plugins/` que enganche a `index_end` y setee la variable. Es más idiomático
y no toca el core, pero se descartó para OPG a favor de la simplicidad de deploy
de la Opción B. Riesgo conocido si algún día se usa: si no aparece nada, casi
siempre es **opcache/deploy stale** (el `op_functions.php` del servidor todavía no
tiene la función nueva) — verificá con un comentario HTML de diagnóstico en el
hook antes de asumir que el CSS está mal.

### ⚠️ Flujo de templates de OPG (no te saltees esto)

Este foro **no renderiza desde `templates/` en runtime** — MyBB sirve los
templates desde la tabla `mybb_templates`. Editar `templates/mybb_templates/index.html`
**no** cambia nada hasta importarlo. Usá el flujo del repo:

- editás el `.html` en `templates/`
- corrés [`import_templates.php`](../import_templates.php) (o `watch_templates.sh`)
  para subirlo a `mybb_templates`
- (o editás el template directo desde el ACP)

Ver [`docs/CLAUDE.md`](CLAUDE.md) §"Punto clave sobre templates/".

---

## 5. Paso 4 — CSS (3 columnas de igual alto)

Va dentro del `<style>` del template del índice (o en la hoja del theme). **Dos
bugs reales ya resueltos** están marcados — no los quites:

```css
.op-index-hook:empty { display: none; }

.afil-section { margin-bottom: 22px; }
.afil-section:last-child { margin-bottom: 0; }

/* Flex A PROPÓSITO: align-items:stretch (default) iguala el alto de las 3
   columnas sin declarar nada extra. */
.afil-columns { display: flex; gap: 20px; }
.afil-columns .afil-section { margin-bottom: 0; }

/* min-width:0 OBLIGATORIO: sin esto una <img> grande revienta el ancho de su
   columna e ignora el layout de 3 iguales (bug clásico de flex con contenido
   reemplazado). Pasó de verdad. */
.afil-columns > .afil-col { display: flex; flex-direction: column; flex: 1 1 0; min-width: 0; }

.afil-section__head { flex: 0 0 auto; display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.afil-section__title { font-weight: 900; letter-spacing: 1.8px; text-transform: uppercase; }

.afil-hermano { display: flex; flex: 1 1 auto; min-height: 0; }
.afil-card {
  position: relative; display: flex; text-decoration: none;
  border: 0.5px solid var(--border); background: var(--bg3);
  transition: border-color 0.15s ease, transform 0.12s ease;
}
.afil-card:hover { transform: translateY(-2px); }

.afil-card--hermano { width: 100%; min-width: 0; overflow: hidden; }
.afil-card--hermano .afil-card__img { width: 100%; height: 100%; min-width: 0; object-fit: cover; display: block; }

.afil-grid { display: grid; gap: 10px; flex: 1 1 auto; min-height: 0; align-content: start; }
.afil-grid--grande { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
.afil-grid--pequeno { grid-template-columns: repeat(auto-fill, 45px); justify-content: center; align-content: start; gap: 8px; }

/* grande: solo imagen, tile 16:9 (sin nombre/descripción visibles). */
.afil-card--grande { aspect-ratio: 16 / 9; overflow: hidden; }
.afil-card--grande .afil-card__img { width: 100%; height: 100%; object-fit: cover; display: block; }

.afil-card--pequeno { width: 45px; height: 45px; padding: 0; overflow: hidden; flex-shrink: 0; }
.afil-card__img { width: 45px; height: 45px; object-fit: cover; display: block; }

.afil-card--vacio { align-items: center; justify-content: center; border-style: dashed; background: transparent; }
.afil-card--vacio.afil-card--grande { aspect-ratio: 16 / 9; }
.afil-slot-plus { font-weight: 700; }

.afil-empty { padding: 18px; text-align: center; border: 0.5px dashed var(--border); }

@media (max-width: 640px) {
  .afil-columns { flex-direction: column; gap: 22px; }
  .afil-columns > .afil-col { flex: 0 0 auto; }
  .afil-card--hermano .afil-card__img { height: auto; aspect-ratio: 16 / 9; }
  .afil-grid--grande { grid-template-columns: repeat(2, 1fr); }
}
```

> **Variables de color:** reemplazá `var(--border)` / `var(--bg3)` por las custom
> properties reales del theme de OPG. Buscá los nombres en el CSS compilado del
> tema (en `cache/themes/`, que es **salida generada** — mirá pero no edites ahí
> como fuente); si el tema no expone custom properties, poné los hex directo.

---

## 6. Paso 5 — Consola de Staff (alta / edición / toggle / eliminar)

Crear `op/staff/gestionar_afiliados.php` + template `staff_gestionar_afiliados`.
Seguí el patrón de los CRUD que ya existen (p. ej.
[`op/staff/objetos_crear.php`](../op/staff/objetos_crear.php)):

- Gate al inicio: `if (!is_staff($uid) && !is_mod($uid)) { ... salir ... }`
- CSRF en cada acción: `verify_post_check($mybb->get_input('my_post_key'), true)`
  (mismo patrón que [`op/staff/peticiones_admin.php:151`](../op/staff/peticiones_admin.php#L151)).
- Un solo endpoint que responde JSON según `accion`:
  **`agregar`, `editar`, `toggle`, `eliminar`**. La edición in-place es requisito
  real (cambiar imagen/nombre/orden sin borrar y recrear).

Patrón de edición:
- Reusar el form de "Agregar" para editar: `<input type="hidden" name="id">` — si
  trae valor, el submit dispara `accion=editar`.
- Cada tarjeta lleva sus datos en `data-*` (`data-nombre`, `data-url`,
  `data-imagen`, `data-descripcion`, `data-orden`) para precargar el form sin otra
  request.
- Al guardar edición, `replaceChild` de la tarjeta en el DOM (no duplicar).
- El **preview del thumbnail** en el admin debe respetar la proporción real por
  nivel (16:9 para hermano/grande, cuadrado chico para pequeno) o Staff sube
  imágenes con el recorte equivocado.

Validaciones server-side (repetir en `agregar` y `editar`): `nombre` obligatorio
(≤120), `url` obligatoria (≤255), `imagen` obligatoria (≤255 — se pega la URL, no
se sube archivo acá), `descripcion` opcional (≤255).

> **Nota:** como los 3 niveles ahora muestran **solo imagen** (§1), la columna
> `descripcion` **ya no se renderiza en el índice**. La dejamos en la tabla y en
> el form como nota interna de Staff (referencia del afiliado), no como texto
> público. Si no la querés ni siquiera para Staff, podés omitir el campo del form
> — la columna es `DEFAULT NULL`, así que no rompe nada dejarla vacía.

**Aviso del tope duro:** con cap fijo, si hay más activos que espacios (ej. 6
`grande` con tope 4), los sobrantes no aparecen en el índice y la consola no lo
grita sola. Mostrá el conteo `activos vs. tope` por nivel para que Staff note
cuándo reordenar/desactivar.

---

## 7. Paso 6 — Formulario público de solicitud (sin login)

Crear `op/peticion_afiliados.php` + template propio, **sin gate de sesión**.
Campos: nombre del foro, URL, contacto, mensaje libre. **Sin selector de nivel**
y **sin subida de imágenes** (§0.1). Antispam sin librerías:

- **Honeypot:** campo oculto por CSS (`position:absolute; left:-9999px;`). Si un
  bot lo llena, respondé el mismo mensaje de éxito pero **no insertes** (no
  delates el filtro).
- **Rate-limit por IP:** `op_afiliados_rate_limit_ok($ip)` (§3), 1 cada 10 min.

### Reuso del sistema de peticiones existente (recomendado)

OPG **ya tiene** `mybb_op_peticiones` con consola en
[`op/staff/peticiones_admin.php`](../op/staff/peticiones_admin.php). Reusalo — no
crees bandeja nueva:

1. **Insertar la solicitud** con `uid='0'` (no hay login) y
   `categoria='afiliados'`. Mapeo de columnas: `nombre` = contacto, `resumen` =
   nombre del foro, `descripcion` = mensaje libre, `url` = URL del foro.

   > **Ojo:** `op/peticiones.php` exige `uid != '0'` para insertar, así que **no
   > pases por ahí** — insertá directo desde `peticion_afiliados.php`:
   > ```php
   > $db->query("INSERT INTO `mybb_op_peticiones`
   >   (`uid`,`nombre`,`categoria`,`resumen`,`descripcion`,`url`)
   >   VALUES ('0','$contacto','afiliados','$nombre_foro','$mensaje','$url_foro')");
   > ```
   > (con los valores ya escapados).

2. **Mostrarla en la consola:** agregar una línea junto a las otras categorías en
   [`op/staff/peticiones_admin.php`](../op/staff/peticiones_admin.php#L295-L299):
   ```php
   $peticiones_li .= print_peticion('Solicitudes de Afiliación', 'afiliados', $uid, $resuelto);
   ```

### ⚠️ Gotcha real del reuso: acciones por `uid` vs `id`

`peticiones_admin.php` en algunos paths resuelve/borra por **`uid`**
(p. ej. `... WHERE uid='{$peti_id}'`), no por `id`. Eso está pensado para
peticiones de usuarios logueados (uid único). Con solicitudes de afiliados
insertadas todas como **`uid=0`**, una acción "resolver/eliminar por uid=0"
afectaría **a todas las solicitudes de afiliados de una** (y a cualquier otra
fila con uid=0).

**Antes de exponer la categoría 'afiliados' en la consola**, verificá que las
acciones de resolver/eliminar sobre estas filas operen por **`id`** (la columna
PK única), no por `uid`. Si el path de tu categoría usa `id`, estás bien; si
reusás un path que usa `uid`, ajustalo para 'afiliados'.

El mensaje de confirmación al enviar debe decir explícitamente que Staff revisará
la solicitud y cómo coordinar el logo/nivel — nada es automático.

---

## 8. Checklist de implementación

- [ ] Decisiones de §0.1 confirmadas con quien pidió la feature (no asumidas)
- [ ] Tablas `mybb_op_afiliados` y `mybb_op_afiliados_rate_limit` creadas (§2) y
      anotadas en `DATABASE.md`
- [ ] Funciones agregadas a `op/functions/op_functions.php` (§3); `php -l` OK
- [ ] Sección enganchada al índice (§4) — hook realmente corre y la función
      existe en el servidor (cuidado con opcache/deploy stale)
- [ ] Template `index` con el placeholder `{$index_afiliados}` **reimportado** a
      `mybb_templates` (no basta editar el `.html` local)
- [ ] CSS agregado con los dos `min-width:0` puestos (§5) y variables de color del
      theme reales
- [ ] Consola `op/staff/gestionar_afiliados.php` con agregar/editar/toggle/
      eliminar + gate `is_staff/is_mod` + `verify_post_check` (§6)
- [ ] Form público `op/peticion_afiliados.php` con honeypot + rate-limit, sin
      selector de nivel (§7)
- [ ] Categoría `'afiliados'` visible en `peticiones_admin.php` y acciones
      operando por `id`, no por `uid` (§7 gotcha)
```
