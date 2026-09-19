# Sync de hojas de estilo del tema: plan de implementación

> Diseño: [css-sync-design.md](css-sync-design.md)

**Objetivo:** crear `export_stylesheets.php` / `import_stylesheets.php`, engancharlos a `watch_templates.sh`, y usar el export para capturar de una vez qué es lo que realmente sirve `cache/themes/theme3/` para `global.css` y `op_global.css`, antes de confiar en el sync automático.

**Requisitos previos:**
- Confirmar `tid` contra la base real (Tarea 1) — el diseño asume `tid=3` por convención de nombre de carpeta, sin poder verificarlo contra datos reales.
- Acceso de escritura a `cache/themes/theme3/` desde PHP (ya lo necesita Admin CP hoy, así que debería estar dado).

**Archivos:**

| Acción | Archivo |
|---|---|
| Crear | `export_stylesheets.php` |
| Crear | `import_stylesheets.php` |
| Modificar | `watch_templates.sh` (agregar la llamada al import de arriba) |

No hay migraciones SQL — las tablas (`mybb_themestylesheets`, `mybb_themes`) ya existen, son core de MyBB.

---

## Tarea 1: confirmar el `tid` real antes de tocar nada

Contra la base de producción (no incluida en `rovddqmy_op.sql`, que solo trae esquema para las tablas core):

```sql
SELECT sid, tid, name, LENGTH(stylesheet) AS bytes, lastmodified
FROM mybb_themestylesheets
WHERE name IN ('global.css', 'op_global.css');
```

Si el `tid` de ambas filas es `3`, el mapeo `$CSS_TID` de las Tareas 2 y 4 es correcto tal cual. Si no, ajustar el valor ahí antes de seguir. Si `LENGTH(stylesheet)` de `op_global.css` es notablemente más chico que `templates/op_global.css` (906 líneas) — esperable, dado el drift ya confirmado — es la prueba de que el export de la Tarea 3 va a traer contenido distinto al que hay en git ahora mismo, no un bug del script.

## Tarea 2: crear `export_stylesheets.php`

**Archivo:** `export_stylesheets.php` (raíz del repo, al lado de `export_templates.php`)

```php
<?php
/**
 * MyBB Stylesheet Exporter
 * Exporta hojas de estilo de mybb_themestylesheets a /templates/*.css.
 * Ver docs/css-sync-design.md. Correr una vez para capturar lo que hay
 * REALMENTE en producción (que puede no coincidir con lo que ya está en git),
 * y después cada vez que algo se edite por error desde Admin CP.
 * DELETE o mover este archivo después de usarlo — expone credenciales de DB
 * vía config.php (mismo aviso que export_templates.php).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'export_stylesheets.php');
require_once "./inc/config.php";

$db = new mysqli(
    str_replace('localhost', '127.0.0.1', $config['database']['hostname']),
    $config['database']['username'],
    $config['database']['password'],
    $config['database']['database']
);

if ($db->connect_error) {
    die("DB connection failed: " . $db->connect_error);
}

$prefix = $config['database']['table_prefix'] ?? 'mybb_';

// nombre => tid (ver docs/css-sync-design.md "Mapeo archivo → tema").
// Confirmado contra la base real en la Tarea 1 antes de correr esto.
$CSS_TID = [
    'global.css'    => 3,
    'op_global.css' => 3,
];

$count = 0;
foreach ($CSS_TID as $name => $tid) {
    $nameEsc = $db->real_escape_string($name);
    $res = $db->query("SELECT stylesheet FROM {$prefix}themestylesheets WHERE name = '{$nameEsc}' AND tid = {$tid}");
    if (!$res || $res->num_rows === 0) {
        echo "AVISO: no hay fila en {$prefix}themestylesheets para name='{$name}' tid={$tid} — no se exportó.\n";
        continue;
    }
    $row = $res->fetch_assoc();
    file_put_contents(__DIR__ . '/templates/' . $name, $row['stylesheet'] ?? '');
    echo "Exportado {$name} (" . strlen($row['stylesheet'] ?? '') . " bytes).\n";
    $count++;
}

echo "Listo: {$count} hoja(s) exportada(s) a /templates/\n";
```

**Verificación:** `php export_stylesheets.php` desde CLI (o por navegador si hace falta, aunque no lleva el gate de password de los otros tres scripts — correrlo una sola vez y borrarlo después, como ya advierte su docblock).

## Tarea 3: correr el export del día 0 y decidir qué hacer con el drift

1. Antes de correr nada, guardar una copia de los `templates/*.css` actuales (mismo hábito que ya existe en `templates/backups/`):
   ```bash
   cp templates/op_global.css templates/backups/op_global.css.pre_export_$(date +%Y%m%d_%H%M)
   cp templates/global.css templates/backups/global.css.pre_export_$(date +%Y%m%d_%H%M)
   ```
2. Correr `php export_stylesheets.php`.
3. `git diff templates/op_global.css templates/global.css` — para `op_global.css` se espera ver desaparecer la reescritura a `var(--opg-*, fallback)` (vuelve a los hex sueltos, porque eso es lo que hay *de verdad* en `cache/themes/theme3/` hoy). Confirma el drift, no lo repara.
4. **Decisión aparte, no parte de esta tarea:** si se quiere que la reescritura a tokens vuelva a estar, se rehace ahora sobre este contenido recién exportado y se commitea como su propio cambio — así el commit "arreglar el mecanismo de sync" no queda mezclado con "cambiar qué CSS se sirve".
5. Borrar (o mover fuera del repo) `export_stylesheets.php` si no va a usarse de nuevo pronto, siguiendo el mismo criterio que `export_templates.php`.

## Tarea 4: crear `import_stylesheets.php`

**Archivo:** `import_stylesheets.php` (raíz del repo, al lado de `import_templates.php`)

```php
<?php
/**
 * MyBB Stylesheet Importer
 * Lee /templates/*.css y actualiza mybb_themestylesheets + regenera los
 * archivos reales que sirve el foro (cache/themes/theme{tid}/*.css y
 * *.min.css), usando las funciones propias de MyBB (admin/inc/functions_themes.php)
 * en vez de reimplementar minificación o reescritura de url(). Ver
 * docs/css-sync-design.md.
 *
 * A diferencia de import_templates.php, este script bootstrapea MyBB
 * completo (no una conexión mysqli mínima): cache_stylesheet() necesita
 * $mybb/$db/$cache reales.
 *
 * Run manually or triggered by a file watcher (watch_templates.sh).
 */

define('IMPORT_PASSWORD', 'changeme');

if (PHP_SAPI !== 'cli') {
    $given = $_GET['password'] ?? $_POST['password'] ?? '';
    if (!hash_equals(IMPORT_PASSWORD, $given)) {
        http_response_code(403);
        die('Forbidden');
    }
}

// --dry-run (CLI) o ?dry_run=1 (HTTP): informa qué haría, sin escribir nada.
// Corrido a mano la primera vez, antes de confiar en watch_templates.sh.
$dryRun = (PHP_SAPI === 'cli' && in_array('--dry-run', $argv, true))
    || (PHP_SAPI !== 'cli' && !empty($_GET['dry_run']));

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'import_stylesheets.php');
require_once "./global.php";
require_once "./admin/inc/functions_themes.php";

global $db;

// nombre => tid (ver docs/css-sync-design.md "Mapeo archivo → tema").
$CSS_TID = [
    'global.css'    => 3,
    'op_global.css' => 3,
];

$updated = 0;
$skipped = 0;

foreach ($CSS_TID as $name => $tid) {
    $path = __DIR__ . '/templates/' . $name;
    if (!is_file($path)) {
        echo "Aviso: {$path} no existe, se salta.\n";
        $skipped++;
        continue;
    }
    $content = file_get_contents($path);

    $query = $db->simple_select('themestylesheets', 'sid, stylesheet', "tid='" . (int) $tid . "' AND name='" . $db->escape_string($name) . "'");
    $row = $db->fetch_array($query);

    if (!$row) {
        echo "Aviso: no hay fila en mybb_themestylesheets para name='{$name}' tid={$tid}. Crearla una vez desde Admin CP antes de sincronizarla. Se salta.\n";
        $skipped++;
        continue;
    }

    if ($row['stylesheet'] === $content) {
        echo "{$name}: sin cambios.\n";
        continue;
    }

    if ($dryRun) {
        echo "{$name}: cambiaría (dry-run, no se escribió nada). sid={$row['sid']} tid={$tid}\n";
        $updated++;
        continue;
    }

    $db->update_query('themestylesheets', array('stylesheet' => $db->escape_string($content)), "sid='{$row['sid']}'");

    // Regenera cache/themes/theme{tid}/{name} y {name}.min.css de verdad —
    // resuelve {$theme[...]} y reescribe url(...) igual que Admin CP.
    if (cache_stylesheet($tid, $name, $content) === false) {
        echo "ERROR: cache_stylesheet() falló para {$name} (¿permisos de escritura en cache/themes/theme{$tid}/?).\n";
        continue;
    }

    update_theme_stylesheet_list($tid);

    echo "{$name}: actualizado (fila + cache/themes/theme{$tid}/{$name}" . ($dryRun ? '' : ' + .min.css') . ").\n";
    $updated++;
}

echo "Listo: {$updated} actualizada(s), {$skipped} salteada(s)." . ($dryRun ? ' (dry-run: nada se escribió)' : '') . "\n";
```

**Notas:**
- Se llama a `cache_stylesheet()` directo, no a `resync_stylesheet()` — esa función de MyBB solo regenera el cache si el archivo *no existe todavía* ("reparar cache faltante"); acá el archivo va a existir casi siempre y hay que pisarlo con el contenido nuevo. Ver docs/css-sync-design.md.
- El chequeo `$row['stylesheet'] === $content` evita reescribir el cache (y bajarle el `lastmodified`) cuando no cambió nada — importante porque `watch_templates.sh` va a llamar a este script en cada guardado de *cualquier* archivo bajo `templates/`, no solo cuando cambia un `.css`.
- `--dry-run` / `?dry_run=1` corre todo el chequeo (incluida la comparación de contenido) pero no escribe ni la fila ni el cache — para el primer uso real, antes de confiar en que quede enganchado al watcher.

**Verificación:**
1. `php -l import_stylesheets.php` → `No syntax errors detected`.
2. `php import_stylesheets.php --dry-run` → debería reportar "cambiaría" para `op_global.css` (por el drift ya confirmado) y "sin cambios" para `global.css` si no se tocó desde el export de la Tarea 3.
3. `php import_stylesheets.php` (sin dry-run) → confirmar en el navegador (con cache de Cloudflare purgada o en una pestaña privada) que una página de `/op/` se sigue viendo igual.

## Tarea 5: enganchar al watcher

**Archivo:** `watch_templates.sh`

Antes:
```bash
fswatch -o "$SCRIPT_DIR/templates/" | while read -r event; do
    echo "[$(date '+%H:%M:%S')] Change detected, syncing..."
    php "$SCRIPT_DIR/import_templates.php"
done
```

Después:
```bash
fswatch -o "$SCRIPT_DIR/templates/" | while read -r event; do
    echo "[$(date '+%H:%M:%S')] Change detected, syncing..."
    php "$SCRIPT_DIR/import_templates.php"
    php "$SCRIPT_DIR/import_stylesheets.php"
done
```

Se llama siempre a los dos, aunque el cambio detectado haya sido en un solo archivo — mismo criterio que ya usa `import_templates.php` (reprocesa todos los templates en cada disparo). El chequeo de "sin cambios" de la Tarea 4 hace que reprocesar los `.css` en cada guardado de un `.html` sea barato (dos lecturas de archivo y dos comparaciones de string, nada más).

**Verificación:** con `./watch_templates.sh` corriendo, editar `templates/op_global.css` (un cambio trivial, como un comentario) y confirmar en la terminal que aparecen las líneas de `import_templates.php` **y** de `import_stylesheets.php` para ese guardado.

## Tarea 6: probar en el servidor

1. **`global.css` no se rompió:** abrir cualquier página del foro (no solo `/op/`) después de correr el import real — layout, botones, el header — nada corrido ni sin estilo.
2. **`op_global.css` no se rompió:** abrir una página con el marco de tres fondos (p. ej. `op/staff/consola_mod.php`) y comparar visualmente contra antes del import.
3. **`url()` relativos:** confirmar en las DevTools (pestaña Red) que las imágenes de fondo de `global.css` (`images/flatty/header-logo.png`, `images/flatty/arrow-down.png`, etc.) siguen cargando con 200, no 404 — es la prueba de que `fix_css_urls_callback` reescribió las rutas relativas a `cache/themes/theme3/` correctamente.
4. **Archivos de cache actualizados:** `stat cache/themes/theme3/op_global.css cache/themes/theme3/op_global.min.css` — la fecha de modificación tiene que ser la del import, no la vieja (`Feb 5 2026` según lo visto antes de este cambio).
5. **Fila de la base actualizada:**
   ```sql
   SELECT sid, tid, name, LENGTH(stylesheet) AS bytes, lastmodified FROM mybb_themestylesheets WHERE name IN ('global.css','op_global.css');
   ```
   `lastmodified` tiene que reflejar el momento del import.
6. **Idempotencia:** correr `php import_stylesheets.php` una segunda vez sin cambiar nada → las dos líneas dicen "sin cambios", no se vuelve a escribir el cache.

## Tarea 7: commit

```bash
git add export_stylesheets.php import_stylesheets.php watch_templates.sh
git commit -m "Sync de hojas de estilo del tema (export/import), igual que ya existe para templates"
```

`export_stylesheets.php` se agrega o no al commit según lo decidido en la Tarea 3 (si se va a dejar en el repo para el próximo drift, o se borra después de usarlo una vez).

---

## Rollback

- Dejar de correr `import_stylesheets.php` (sacar la línea de `watch_templates.sh`) no revierte nada por sí solo: la fila de `mybb_themestylesheets` y los archivos de `cache/themes/theme3/` quedan con el último contenido sincronizado.
- Para volver al estado de antes de este cambio, restaurar desde Admin CP (pegar el contenido viejo en el editor de la hoja de estilo) o desde el backup tomado en la Tarea 3 (`templates/backups/*.pre_export_*`), y guardarlo ahí para que Admin CP regenere el cache.
- Borrar `export_stylesheets.php` / `import_stylesheets.php` no afecta a `export_templates.php` / `import_templates.php` — son pares independientes.
