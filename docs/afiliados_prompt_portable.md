# Prompt: sección de Afiliados para un foro MyBB

Este documento es una **guía/prompt autocontenida** para implementar en otro foro
MyBB (de estructura similar a este) la misma sección de "Afiliados" que se
construyó acá. Pegá este documento completo como prompt/contexto en el otro
proyecto y pedí que se implemente siguiendo estos pasos.

No es una copia mecánica de archivos: son los **mismos criterios de diseño y
las mismas trampas ya resueltas**, adaptados con placeholders para que apliquen
a cualquier capa custom sobre MyBB parecida a esta ("sg/", `sg_functions.php`,
tablas `mybb_sg_sg_*`, patrón `eval()` + `$templates->get()`, admin AJAX con
`verify_post_check`, etc).

## 0. Antes de empezar: adaptá estos placeholders

- `{{PREFIX}}` — prefijo de funciones/tablas custom del otro foro (acá es `sg`;
  las funciones quedan `sg_afiliados_index_html()`, las tablas
  `mybb_sg_sg_afiliados`, etc.). Si el otro foro no tiene un prefijo propio,
  elegí uno corto y usalo consistentemente.
- `{{FUNCTIONS_FILE}}` — el archivo central de funciones custom (acá es
  `sg/functions/sg_functions.php`). Si no existe uno, creá uno y asegurate de
  que esté `require_once`d en cada request (en este foro lo hace `global.php`).
- `{{ADMIN_DIR}}` — carpeta de herramientas de Staff (acá `sg/admin/`).
- `{{PETICIONES_SISTEMA}}` — si el otro foro **ya tiene** un sistema genérico
  de "peticiones/solicitudes" administrables por Staff (con `categoria`,
  bandeja de revisión, etc.), reusalo igual que acá (ver §6). Si no existe,
  hay que crear una tabla mínima propia solo para esto — está marcado abajo.
- Textos de ejemplo (nombre del foro, Discord, a quién contactar por
  defecto) son placeholders — reemplazalos por los reales del otro foro.

## 0.1 Decisiones ya confirmadas para este foro destino

Respuestas al checklist de §8, ya cerradas — no hace falta volver a
preguntarlas al implementar:

1. **Tope duro** (no un mínimo a completar): **4** espacios para `grande`,
   **24** para `pequeno`. Si hay más afiliados activos que el tope, los
   sobrantes simplemente no se muestran (no hay overflow ni paginado) — ver
   el cambio de lógica en §3 (`array_slice`).
2. `hermano` **es editable** (imagen y nombre), igual que los otros niveles.
3. El nivel deseado queda **libre en el mensaje** del formulario público, sin
   selector dedicado.
4. **No** se permite subir imagen en el formulario público (puro texto).
5. Nombres públicos: **Afiliado Almirante** (`hermano`), **Afiliados
   Capitanes** (`grande`), **Afiliados Piratas** (`pequeno`) — temática
   pirata/One Piece.
6. La solicitud pública **es una petición más**: Staff la revisa desde la
   consola de peticiones ya existente, sin ninguna acción automática sobre
   `mybb_{{PREFIX}}_afiliados`.

## 1. Qué se construye (resumen funcional)

Una sección de afiliados en el índice del foro, con **3 niveles internos**
(el nombre interno no tiene que coincidir con lo que se muestra al público):

| `tipo` (interno, fijo en código/DB) | Nombre público (confirmado, ver §0.1) |
|---|---|
| `hermano` | **Afiliado Almirante** — mismo staff/creador, 1 solo espacio, sin versión vacía solicitable |
| `grande`  | **Afiliados Capitanes** — tope duro de 4, con nombre + descripción corta |
| `pequeno` | **Afiliados Piratas** — tope duro de 24, grid de cuadritos (imagen sola, sin texto visible) |

Puntos de diseño confirmados en esta implementación (repetilos salvo que el
dueño del otro foro pida otra cosa):
- Los 3 niveles van **en una sola fila, en 3 columnas** side-by-side (no
  apilados), con **la misma altura total** las tres columnas.
- `hermano` y `pequeno` muestran **solo la imagen** (sin nombre/descripción
  visibles; el nombre queda en el `title` del link). `grande` sí muestra
  nombre + descripción corta debajo de la imagen.
- Los espacios "vacíos" (cuando hay menos afiliados activos que el tope
  configurado) se rellenan con un cuadro placeholder "+" que linkea al
  formulario público de solicitud. Acá el tope es **duro** (confirmado en
  §0.1): si hay más activos que el tope, los que sobran no se muestran — a
  diferencia de este foro (mybb-sg), donde es un mínimo y se muestran todos
  los que haya.
- El formulario público **no distingue nivel** (grande/pequeño/etc. es
  nomenclatura interna de la herramienta de Staff nada más); quien quiera un
  nivel puntual lo aclara en el mensaje libre.
- Staff puede crear/editar/activar-desactivar/eliminar afiliados desde una
  consola propia, con **edición in-place** de los ya guardados (no solo
  alta/baja).

## 2. Paso 1 — Esquema de base de datos

Adaptá el prefijo de tabla a `{{PREFIX}}`. Este es el esquema usado acá:

```sql
CREATE TABLE `mybb_{{PREFIX}}_afiliados` (
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

ALTER TABLE `mybb_{{PREFIX}}_afiliados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_orden` (`tipo`, `orden`);

ALTER TABLE `mybb_{{PREFIX}}_afiliados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Rate-limit del formulario público (1 solicitud cada X minutos por IP;
-- necesario porque el form no exige login).
CREATE TABLE `mybb_{{PREFIX}}_afiliados_rate_limit` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_{{PREFIX}}_afiliados_rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_tiempo` (`ip`, `tiempo`);

ALTER TABLE `mybb_{{PREFIX}}_afiliados_rate_limit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
```

Una sola tabla para los 3 niveles (misma forma, distinto `tipo`) en vez de 3
tablas separadas: evita triplicar CRUD y queries.

## 3. Paso 2 — Funciones backend (`{{FUNCTIONS_FILE}}`)

Constantes de espacios por nivel. **Para este foro son tope duro** (§0.1):
si hay más afiliados activos que la constante, los que sobran no se
muestran — la función de abajo ya implementa eso con `array_slice`. (Si en
otro caso quisieras que fuera un mínimo a completar en vez de un tope, sacá
el `array_slice` y usá directamente `$filas['grande']` / `$filas['pequeno']`
completas.)

```php
define('{{PREFIX_UPPER}}_AFILIADOS_SLOTS_GRANDE', 4);   // tope duro confirmado
define('{{PREFIX_UPPER}}_AFILIADOS_SLOTS_PEQUENO', 24); // tope duro confirmado
```

Render de una tarjeta pública. `pequeno` y `hermano` son **solo imagen**
(nombre en el `title`); `grande` muestra nombre + descripción corta:

```php
function {{PREFIX}}_afil_card_html($a, $tier) {
    $nombre = htmlspecialchars($a['nombre'], ENT_QUOTES);
    $url    = htmlspecialchars($a['url'], ENT_QUOTES);
    $img    = htmlspecialchars($a['imagen'], ENT_QUOTES);

    if ($tier === 'pequeno' || $tier === 'hermano') {
        return '<a class="afil-card afil-card--' . $tier . '" href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow" title="' . $nombre . '">'
            . '<img class="afil-card__img" src="' . $img . '" alt="' . $nombre . '" loading="lazy" onerror="this.style.opacity=0.2">'
            . '</a>';
    }

    $desc = trim((string) $a['descripcion']);
    $desc_html = $desc !== '' ? '<p class="afil-card__desc">' . nl2br(htmlspecialchars($desc)) . '</p>' : '';

    return '<a class="afil-card afil-card--' . $tier . '" href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">'
        . '<span class="afil-card__thumb"><img src="' . $img . '" alt="' . $nombre . '" loading="lazy" onerror="this.style.opacity=0.2"></span>'
        . '<span class="afil-card__body"><span class="afil-card__name">' . $nombre . '</span>' . $desc_html . '</span>'
        . '</a>';
}

// Cuadro vacío: placeholder + link a la solicitud pública. El formulario no
// pregunta nivel, así que el link no lleva parámetro alguno.
function {{PREFIX}}_afil_slot_vacio_html($tier, $bburl) {
    $url = htmlspecialchars($bburl . '/{{RUTA}}/peticion_afiliados.php', ENT_QUOTES);
    return '<a class="afil-card afil-card--vacio afil-card--' . $tier . '" href="' . $url . '" title="Solicitar afiliación">'
        . '<span class="afil-slot-plus">+</span>'
        . '</a>';
}

// Arma el HTML completo de la sección para el índice: hermano + grande +
// pequeño en 3 columnas dentro de un mismo <div class="afil-columns">.
function {{PREFIX}}_afiliados_index_html() {
    global $db, $mybb;
    $bburl = isset($mybb->settings['bburl']) ? $mybb->settings['bburl'] : '';

    $filas = array('hermano' => array(), 'grande' => array(), 'pequeno' => array());
    $q = $db->query("SELECT * FROM `mybb_{{PREFIX}}_afiliados` WHERE activo=1 ORDER BY tipo, orden, id");
    while ($r = $db->fetch_array($q)) {
        if (isset($filas[$r['tipo']])) { $filas[$r['tipo']][] = $r; }
    }

    if (!empty($filas['hermano'])) {
        $hermano_html = {{PREFIX}}_afil_card_html($filas['hermano'][0], 'hermano');
    } else {
        $hermano_html = '<div class="afil-empty">Todavía no hay afiliado hermano.</div>';
    }

    // Tope duro (§0.1): array_slice corta a la constante ANTES de armar el
    // HTML, así los que sobran del orden establecido simplemente no entran.
    $grande_visibles = array_slice($filas['grande'], 0, {{PREFIX_UPPER}}_AFILIADOS_SLOTS_GRANDE);
    $grandes_html = '';
    foreach ($grande_visibles as $a) { $grandes_html .= {{PREFIX}}_afil_card_html($a, 'grande'); }
    $faltan_grande = max(0, {{PREFIX_UPPER}}_AFILIADOS_SLOTS_GRANDE - count($grande_visibles));
    for ($i = 0; $i < $faltan_grande; $i++) { $grandes_html .= {{PREFIX}}_afil_slot_vacio_html('grande', $bburl); }

    $pequeno_visibles = array_slice($filas['pequeno'], 0, {{PREFIX_UPPER}}_AFILIADOS_SLOTS_PEQUENO);
    $pequenos_html = '';
    foreach ($pequeno_visibles as $a) { $pequenos_html .= {{PREFIX}}_afil_card_html($a, 'pequeno'); }
    $faltan_pequeno = max(0, {{PREFIX_UPPER}}_AFILIADOS_SLOTS_PEQUENO - count($pequeno_visibles));
    for ($i = 0; $i < $faltan_pequeno; $i++) { $pequenos_html .= {{PREFIX}}_afil_slot_vacio_html('pequeno', $bburl); }

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

// Rate-limit por IP para el formulario público sin login: máximo 1 solicitud
// cada $minutos. Limpia filas viejas (>1 día) para no crecer sin límite.
function {{PREFIX}}_afiliados_rate_limit_ok($ip, $minutos = 10) {
    global $db;
    $ip_esc = $db->escape_string($ip);

    $db->query("DELETE FROM `mybb_{{PREFIX}}_afiliados_rate_limit` WHERE tiempo < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    $reciente = false;
    $q = $db->query("SELECT id FROM `mybb_{{PREFIX}}_afiliados_rate_limit` WHERE ip='$ip_esc' AND tiempo > DATE_SUB(NOW(), INTERVAL $minutos MINUTE) LIMIT 1");
    while ($db->fetch_array($q)) { $reciente = true; }
    if ($reciente) { return false; }

    $db->query("INSERT INTO `mybb_{{PREFIX}}_afiliados_rate_limit` (`ip`) VALUES ('$ip_esc')");
    return true;
}
```

"Afiliado Almirante" / "Afiliados Capitanes" / "Afiliados Piratas" son
**solo el texto que ve el público** (confirmado en §0.1) — el `tipo` interno
(`hermano`/`grande`/`pequeno`) queda igual en código y DB pase lo que pase
con el naming público, así que si más adelante cambia el nombre temático de
nuevo, alcanza con editar estos 3 strings acá adentro — no hace falta tocar
queries ni columnas.

## 4. Paso 3 — Enganchar la sección al índice

**Recomendación: probá primero con un hook de plugin** (`$plugins->add_hook`)
si el core de ese foro ya tiene uno cerca del final de `index.php` (o un
placeholder de template ya armado tipo `{$index_section_final}`). Es más
"MyBB-idiomático" y no toca archivos del core.

**Pero ojo con esta trampa real que nos pasó acá:** si el hook no muestra
nada y todo parece bien configurado (plugin activo, tabla creada, template
reimportado), puede ser un problema de **deploy/opcache** — el archivo de
funciones que subiste al servidor no tiene la función nueva todavía (caché
de opcode, o subiste una versión vieja). Antes de asumir que el diseño está
mal, verificá con un comentario HTML de diagnóstico en el propio hook (algo
que se vea en el "ver código fuente" del navegador) para confirmar en qué
paso se corta la cadena.

Si eso se vuelve muy difícil de debuggear en un hosting sin acceso de shell,
la alternativa que terminamos usando acá es **editar `index.php` del core
directamente**, justo antes del `eval()`/`output_page()` final:

```php
// ... código existente de index.php ...

$index_section_final = function_exists('{{PREFIX}}_afiliados_index_html') ? {{PREFIX}}_afiliados_index_html() : '';

eval('$index = "'.$templates->get('index').'";');
output_page($index);
```

Y en el template `index` (o el que corresponda), un placeholder tipo:

```html
<div class="{{PREFIX}}-index-hook">{$index_section_final}</div>
```

Esto es un **archivo del core de MyBB** — un update de MyBB podría
sobrescribirlo. Es un trade-off consciente (simplicidad de deploy y
debugging vs. "no tocar el core"); documentalo en el repo para que quien
actualice MyBB después sepa que hay que reaplicar este cambio.

## 5. Paso 4 — CSS del índice (3 columnas de igual alto)

Agregar dentro del `<style>` del template del índice. Reproducido tal cual
se terminó usando acá (ya con las dos correcciones de bugs reales, ver
notas):

```css
/* Oculto solo si está vacío (por si el hook no tiene contenido todavía). */
.{{PREFIX}}-index-hook:empty { display: none; }

.afil-section { margin-bottom: 22px; }
.afil-section:last-child { margin-bottom: 0; }

/* Flex (no grid) A PROPÓSITO: align-items:stretch (default de flex) hace
   que las 3 columnas siempre midan lo mismo de alto (la más alta manda),
   sin importar cuántos ítems tenga cada nivel. Con CSS Grid esto también
   se puede lograr, pero flex lo da gratis sin declarar nada extra. */
.afil-columns { display: flex; gap: 20px; }
.afil-columns .afil-section { margin-bottom: 0; }

/* min-width:0 es OBLIGATORIO acá: sin esto, una imagen grande dentro de
   .afil-card--hermano "revienta" el ancho de su columna e ignora el layout
   de 3 columnas iguales. Es el bug clásico de flex/grid con contenido
   reemplazado (<img>): los items tienen min-width:auto por defecto, que
   ignora cualquier width:100% del hijo. Pasó de verdad en esta
   implementación — no es una precaución teórica. */
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

/* Hermano: solo imagen, alto completo de la fila (igualado con las otras 2
   columnas) — sin aspect-ratio fijo, se recorta con object-fit:cover. */
.afil-card--hermano { width: 100%; min-width: 0; overflow: hidden; }
.afil-card--hermano .afil-card__img { width: 100%; height: 100%; min-width: 0; object-fit: cover; display: block; }

.afil-grid { display: grid; gap: 10px; flex: 1 1 auto; min-height: 0; align-content: start; }
.afil-grid--grande { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
.afil-grid--pequeno { grid-template-columns: repeat(auto-fill, 45px); justify-content: center; align-content: start; gap: 8px; }

.afil-card--grande { flex-direction: column; }
.afil-card--grande .afil-card__thumb { width: 100%; aspect-ratio: 16 / 9; overflow: hidden; }
.afil-card--grande .afil-card__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.afil-card--grande .afil-card__body { padding: 8px 10px 10px; }

.afil-card--pequeno { width: 45px; height: 45px; padding: 0; overflow: hidden; flex-shrink: 0; }
.afil-card__img { width: 45px; height: 45px; object-fit: cover; display: block; }

.afil-card--vacio { align-items: center; justify-content: center; border-style: dashed; background: transparent; }
.afil-card--vacio.afil-card--grande { aspect-ratio: 16 / 9; }
.afil-slot-plus { font-weight: 700; }

.afil-empty { padding: 18px; text-align: center; border: 0.5px dashed var(--border); }

/* Apiladas en mobile: ahí no tiene sentido igualar alturas entre columnas
   que ya no están lado a lado. */
@media (max-width: 640px) {
  .afil-columns { flex-direction: column; gap: 22px; }
  .afil-columns > .afil-col { flex: 0 0 auto; }
  .afil-card--hermano .afil-card__img { height: auto; aspect-ratio: 16 / 9; }
  .afil-grid--grande { grid-template-columns: repeat(2, 1fr); }
}
```

Reemplazá los `var(--border)`, `var(--bg3)`, etc. por las custom properties
de color que use el theme del otro foro.

## 6. Paso 5 — Consola de Staff (alta / edición / activar-desactivar / eliminar)

Página tipo `{{ADMIN_DIR}}/gestionar_afiliados.php` + template
`staff_gestionar_afiliados`. Mismo patrón que cualquier CRUD admin-AJAX que
ya tenga el otro foro (`verify_post_check($mybb->post_code, true)` para CSRF,
un solo endpoint que responde JSON según `accion`).

Acciones necesarias: **`agregar`, `editar`, `toggle`, `eliminar`** — no te
quedes solo con alta/baja, la edición in-place de un afiliado ya guardado es
un requisito real (cambiar imagen/nombre/orden sin borrar y recrear).

Puntos clave del patrón de edición:
- Reusar el mismo formulario de "Agregar" para editar: un campo oculto
  `<input type="hidden" name="id" value="">` que, si tiene valor, hace que
  el submit dispare `accion=editar` en vez de `accion=agregar`.
- Cada tarjeta lleva sus datos crudos en atributos `data-*`
  (`data-nombre`, `data-url`, `data-imagen`, `data-descripcion`,
  `data-orden`) para poder precargar el form al apretar "Editar" sin otra
  request.
- Al guardar una edición, reemplazar la tarjeta existente en el DOM
  (`replaceChild`), no duplicarla ni tocar los contadores.
- El thumbnail de preview en el admin **debe respetar la misma proporción**
  que se usa públicamente por nivel (16:9 para hermano/grande, cuadrado
  chico para pequeño) — si no, Staff sube imágenes con el recorte
  equivocado porque el preview del admin no se parece a cómo queda en el
  índice.
- **Importante por el tope duro (§0.1):** con cap fijo, si hay más
  afiliados activos que espacios (ej. 6 `grande` activos con tope 4), los
  que sobran del `orden` simplemente no aparecen en el índice — no hay
  overflow visible en la consola de Staff avisando esto. Vale la pena
  mostrar el conteo total de activos vs. el tope en la consola (ya existe
  `{$afgGrandeCount}` etc. en el patrón de este foro) para que Staff note
  cuándo se pasó del límite y tenga que reordenar o desactivar alguno.

Validaciones server-side (repetir en `agregar` y `editar`): nombre
obligatorio (máx. 120), URL obligatoria (máx. 255), imagen obligatoria (máx.
255 — se sube antes con el uploader de imágenes que ya tenga el foro, acá
solo se pega la URL resultante), descripción opcional (máx. 255).

## 7. Paso 6 — Formulario público de solicitud (sin login)

Página `{{RUTA}}/peticion_afiliados.php` + template propio, **sin gate de
sesión**. Campos: nombre del foro, URL, contacto, mensaje libre opcional.
**Sin selector de nivel** (ver §1) y **sin subida de imágenes** (puro texto;
las imágenes se coordinan después por contacto directo con Staff).

Antispam mínimo, sin librerías externas:
- **Honeypot**: un campo oculto vía CSS (`position:absolute; left:-9999px;`)
  que un humano nunca completa mecánicamente. Si un bot lo llena, respondé
  el mismo mensaje de éxito pero sin insertar nada — no delates el filtro.
- **Rate-limit por IP** (`{{PREFIX}}_afiliados_rate_limit_ok()`, §3): 1
  solicitud cada 10 minutos por IP.

Qué hacer con la solicitud al enviarla — dos caminos según lo que tenga el
otro foro:

- **Si {{PETICIONES_SISTEMA}} ya existe** (una tabla genérica de
  "peticiones" con `categoria`, revisada por Staff desde una consola ya
  armada): reusala. Insertá con `uid='0'` (no hay usuario logueado) y una
  `categoria` nueva tipo `'afiliados'`. Cambios mínimos en lo existente:
  agregar la nueva categoría a la función que mapea categoría → label, y una
  línea más en la consola de Staff que ya lista las demás categorías. **No
  crees una tabla ni una bandeja nueva** si esto ya existe — sería
  duplicar infraestructura para lo mismo.
- **Si no existe nada parecido**: creá una tabla mínima propia
  (`mybb_{{PREFIX}}_afiliados_solicitudes`: id, nombre_foro, url, contacto,
  mensaje, ip, tiempo, revisada) y una página simple de listado para Staff.

El mensaje de confirmación debe decir explícitamente que Staff va a revisar
la solicitud y cómo contactarse para coordinar detalles (logo, nivel
deseado, etc.) — no prometas nada automático, esto es 100% manual.

## 8. Decisiones a confirmar con el dueño del foro ANTES de implementar

**Para este foro ya están todas respondidas — ver §0.1.** Se deja el
checklist igual por si este mismo documento se reutiliza para un cuarto
foro más adelante. No asumas — son elecciones de producto, no técnicas:

1. ¿Cuántos espacios mínimos por nivel (`grande`, `pequeno`)? ¿Es un mínimo
   a completar con placeholders o un tope duro?
2. ¿El nivel "hermano" es editable (imagen/nombre) o realmente fijo?
3. ¿El formulario público pide nivel deseado, o queda libre en el mensaje?
4. ¿Se permite subir imagen en el formulario público, o es puro texto +
   coordinación manual?
5. ¿Cómo se llaman públicamente los 3 niveles? (el `tipo` interno no tiene
   que coincidir)
6. ¿La solicitud pública necesita aprobación que dispare algo automático, o
   es "una petición más" que Staff revisa y actúa manualmente afuera del
   sistema?

## 9. Checklist de implementación

- [ ] Tabla(s) creadas (§2)
- [ ] Funciones agregadas a `{{FUNCTIONS_FILE}}` (§3), `php -l` sin errores
- [ ] Sección enganchada al índice (§4) — probado que el hook realmente
      corre y la función realmente existe en el servidor (cuidado con
      opcache/deploy stale, ver §4)
- [ ] CSS agregado al template del índice (§5), con los dos `min-width:0`
      puestos donde corresponde
- [ ] Template del índice reimportado vía ACP
- [ ] Consola de Staff con alta/edición/activar-desactivar/eliminar (§6)
- [ ] Formulario público con honeypot + rate-limit, sin selector de nivel
      (§7)
- [ ] Decisiones de producto (§8) confirmadas con quien pidió la feature,
      no asumidas
