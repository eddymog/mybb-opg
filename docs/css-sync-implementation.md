# Sync de hojas de estilo del tema: plan de implementación

> Diseño: [css-sync-design.md](css-sync-design.md)

**Objetivo:** crear `export_stylesheets.php` / `import_stylesheets.php`, engancharlos a `watch_templates.sh`, para que `templates/One_Piece_Gaiden_Templates/stylesheets/*.css` (recién movidos ahí desde `templates/global.css` y `templates/op_global.css`) queden sincronizados con `mybb_themestylesheets` y `cache/themes/theme3/` igual que ya pasa con los `.html` de esa misma carpeta.

**Requisitos previos:**
- ~~Confirmar `tid=3` contra la base real~~ — **hecho.** `opg_stylesheet_export.php` (ver Tarea 2b) ya se activó una vez en producción y trajo **18 hojas de estilo** para `tid=3` a `templates/One_Piece_Gaiden_Templates/stylesheets/` (`global.css`, `op_global.css`, `ficha.css`, `css3.css`, `mapaMundo.css`, `temas.css` y otras 12 más chicas) — confirma el `tid` y de paso muestra que el alcance real es bastante más grande que los dos archivos con los que arrancó este diseño. No cambia nada del diseño (el import ya recorre la carpeta entera con `glob()`, no una lista fija), pero si aparece una hoja nueva que nunca se tocó, vale la pena mirarla antes de asumir que un futuro `import` no le va a hacer nada raro.
- Acceso de escritura a `cache/themes/theme3/` desde PHP (ya lo necesita Admin CP hoy, así que debería estar dado).
- `templates/global.css` y `templates/op_global.css` ya se movieron a `templates/One_Piece_Gaiden_Templates/stylesheets/` (hecho — `git mv`, ver `git status`).

**Sin acceso SSH:** no hay forma de correr `export_stylesheets.php` ni `import_stylesheets.php` (ni el watcher) desde una terminal. La Tarea 2b agrega dos plugins de un solo uso (`opg_stylesheet_export.php` / `opg_stylesheet_import.php`) que hacen exactamente lo mismo disparados por el botón **Activar** de Admin CP > Plugins — todo lo que hace falta es poder subir un archivo por FTP y tener sesión de admin en el foro.

**Archivos:**

| Acción | Archivo |
|---|---|
| Crear | `export_stylesheets.php` (usado y borrado — ver Tarea 2) |
| Crear | `import_stylesheets.php` (Tarea 3, requiere CLI/`watch_templates.sh`) |
| Crear | `inc/plugins/opg_stylesheet_export.php` (Tarea 2b, alternativa sin CLI) |
| Crear, luego eliminar | `inc/plugins/opg_stylesheet_import.php` (Tarea 2b, alternativa sin CLI — **retirado**, ver nota en Tarea 2b) |
| Modificar | `watch_templates.sh` (agregar la llamada al import de la Tarea 3) |
| Genera en runtime | `logs/css_sync.log` (ya cubierto por `.gitignore`, patrón `*.log`) y `templates/backups/*.pre_import_*.css` (**no** está gitignorado — `templates/backups/` ya tiene contenido commiteado hoy, así que estos backups automáticos se van a sumar como archivos nuevos sin trackear; decidir aparte si conviene commitearlos, limpiarlos de a poco a mano, o sumar `templates/backups/*.pre_import_*.css` a `.gitignore`) |

No hay migraciones SQL — las tablas (`mybb_themestylesheets`, `mybb_themes`) ya existen, son core de MyBB.

---

## Tarea 1: confirmar el `tid` real antes de tocar nada

Contra la base de producción (no incluida en `rovddqmy_op.sql`, que solo trae esquema para las tablas core):

```sql
SELECT sid, tid, name, LENGTH(stylesheet) AS bytes, lastmodified
FROM mybb_themestylesheets
WHERE name IN ('global.css', 'op_global.css');
```

Si el `tid` de ambas filas es `3`, la constante `STYLESHEETS_TID` de las Tareas 2 y 4 es correcta tal cual. Si no, ajustarla ahí antes de seguir.

## Tarea 2: crear `export_stylesheets.php`

**Archivo:** `export_stylesheets.php` (raíz del repo, al lado de `export_templates.php`)

```php
<?php
/**
 * MyBB Stylesheet Exporter
 * Exporta las hojas de estilo del tema tid=3 (mybb_themestylesheets) a
 * templates/One_Piece_Gaiden_Templates/stylesheets/*.css.
 * Ver docs/css-sync-design.md. Correr cuando algo se edite por error desde
 * Admin CP y haga falta traer ese cambio de vuelta a git.
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

// templates/One_Piece_Gaiden_Templates/ == tid 3 (confirmado en la Tarea 1).
const STYLESHEETS_TID = 3;
const STYLESHEETS_DIR = __DIR__ . '/templates/One_Piece_Gaiden_Templates/stylesheets';

if (!is_dir(STYLESHEETS_DIR)) {
    mkdir(STYLESHEETS_DIR, 0755, true);
}

$res = $db->query("SELECT name, stylesheet FROM {$prefix}themestylesheets WHERE tid = " . STYLESHEETS_TID);

$count = 0;
while ($row = $res->fetch_assoc()) {
    file_put_contents(STYLESHEETS_DIR . '/' . $row['name'], $row['stylesheet'] ?? '');
    echo "Exportado {$row['name']} (" . strlen($row['stylesheet'] ?? '') . " bytes).\n";
    $count++;
}

echo "Listo: {$count} hoja(s) exportada(s) a " . STYLESHEETS_DIR . "\n";
```

**Verificación:** `php export_stylesheets.php` desde CLI. Trae **todas** las hojas de estilo de tid=3, no solo `global.css`/`op_global.css` — si el tema tiene alguna otra hoja no trackeada todavía, aparece acá y se puede sumar a git o ignorar según convenga.

**Estado:** ya se corrió una vez en producción y se borró después, como indica su propio docblock — trajo las 18 hojas de `tid=3` (ver nota en "Requisitos previos" arriba). Si hace falta repetirlo, recrear el archivo con el código de arriba.

## Tarea 2b: alternativa sin CLI — plugins de un solo uso

> **Actualización:** `opg_stylesheet_import.php` (descrito en esta tarea) se retiró del repo. `opg_stylesheet_sync.php` — un plugin real enganchado a `global_start`, mismo patrón que `inc/plugins/template_sync.php` — sincroniza automáticamente cada ~10 segundos de tráfico al foro, sin necesitar Desactivar/Activar a mano. Ver "Flujo (camino automático)" en `css-sync-design.md`. `opg_stylesheet_export.php` sigue como está, descrito abajo.

Mismo par de operaciones (export / import) que las Tareas 2 y 3, pero disparadas por el botón **Activar** de Admin CP en vez de por una terminal — para cuando no hay SSH ni phpMyAdmin, solo FTP y sesión de admin en el foro. No son plugins de verdad: no enganchan ningún hook, `_activate()` es lo único que hacen, y se pueden desactivar/volver a activar cuantas veces haga falta para repetir la sincronización.

**Archivo:** `inc/plugins/opg_stylesheet_export.php` — mismo `SELECT ... WHERE tid=3` que `export_stylesheets.php`, pero escribiendo a `MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets/'` (una ruta real en el servidor) y devolviendo el resumen con `flash_message()` en vez de `echo`, porque `_activate()` no tiene una terminal mirando su salida — el mensaje aparece en la propia página de Plugins después de activar.

**Archivo:** `inc/plugins/opg_stylesheet_import.php` — mismas tres salvaguardas que `import_stylesheets.php` (rechazo de contenido vacío/muy corto, backup automático en `templates/backups/`, log en `logs/css_sync.log`) y las mismas llamadas a `cache_stylesheet()`/`update_theme_stylesheet_list()`, corriendo sobre los archivos que ya estén subidos por FTP a `templates/One_Piece_Gaiden_Templates/stylesheets/` en el servidor.

**Flujo de uso real:**
1. Editar un `.css` en la copia local del repo.
2. Subirlo por FTP a `templates/One_Piece_Gaiden_Templates/stylesheets/` en el servidor (mismo lugar donde ya se sincronizan los `.html`).
3. Admin CP > Plugins > si `opg_stylesheet_import` está activo, **Desactivar** y volver a **Activar** (o activarlo por primera vez si es la primera corrida) — el mensaje verde de la página siguiente dice qué hoja cambió, cuál se rechazó y por qué.
4. Repetir el paso 3 cada vez que se suba un `.css` nuevo por FTP — no hay watcher automático en este camino, la activación es el disparador.

**Diferencia real con el camino de CLI:** ninguna en el resultado final (misma tabla, mismo cache regenerado, mismas salvaguardas) — solo en qué dispara la sincronización: un evento de filesystem (`watch_templates.sh`) contra un clic manual en Admin CP. Si en algún momento hay acceso SSH, tiene sentido migrar al camino de la Tarea 3/4 y borrar estos dos plugins; mientras tanto, conviven sin problema (comparten `logs/css_sync.log` y `templates/backups/`, así que el historial no se parte en dos).

**Verificación:** activar `opg_stylesheet_export`, confirmar el mensaje verde con la lista de hojas y bytes, desactivarlo. Después activar `opg_stylesheet_import` sin haber tocado ningún `.css` todavía → todas las líneas tienen que decir "sin cambios" (confirma que el import no reescribe nada quieto). Recién ahí probar con un cambio real.

## Tarea 3: crear `import_stylesheets.php`

**Archivo:** `import_stylesheets.php` (raíz del repo, al lado de `import_templates.php`)

```php
<?php
/**
 * MyBB Stylesheet Importer
 * Lee templates/One_Piece_Gaiden_Templates/stylesheets/*.css y actualiza
 * mybb_themestylesheets + regenera los archivos reales que sirve el foro
 * (cache/themes/theme{tid}/*.css y *.min.css), usando las funciones propias
 * de MyBB (admin/inc/functions_themes.php) en vez de reimplementar
 * minificación o reescritura de url(). Ver docs/css-sync-design.md.
 *
 * Corre contra producción sin staging en el medio, así que antes de escribir
 * nada: (1) rechaza contenido vacío o sospechosamente más corto que el
 * actual (archivo truncado o a medio guardar), (2) guarda un backup del
 * contenido anterior, (3) deja un registro en logs/css_sync.log de cada
 * cambio real. Ninguna de las tres reemplaza mirar el diff antes de un
 * guardado importante — son una red debajo, no una validación de CSS.
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

// templates/One_Piece_Gaiden_Templates/ == tid 3 (confirmado en la Tarea 1).
const STYLESHEETS_TID = 3;
const STYLESHEETS_DIR = __DIR__ . '/templates/One_Piece_Gaiden_Templates/stylesheets';
const BACKUP_DIR       = __DIR__ . '/templates/backups';
const LOG_FILE         = __DIR__ . '/logs/css_sync.log';
// Si el archivo nuevo mide menos que esta fracción del actual, se rechaza en
// vez de sincronizarlo — probable archivo truncado o a medio guardar, no una
// edición normal. No es validación de sintaxis CSS, solo un chequeo barato
// de "esto no se parece en nada a lo de antes".
const MIN_SIZE_RATIO = 0.5;

function css_sync_log($linea)
{
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0755, true);
    }
    file_put_contents(LOG_FILE, '[' . date('Y-m-d H:i:s') . "] {$linea}\n", FILE_APPEND);
}

$updated = 0;
$skipped = 0;

foreach (glob(STYLESHEETS_DIR . '/*.css') as $path) {
    $name = basename($path);
    $content = file_get_contents($path);

    $query = $db->simple_select('themestylesheets', 'sid, stylesheet', "tid='" . STYLESHEETS_TID . "' AND name='" . $db->escape_string($name) . "'");
    $row = $db->fetch_array($query);

    if (!$row) {
        echo "Aviso: no hay fila en mybb_themestylesheets para name='{$name}' tid=" . STYLESHEETS_TID . ". Crearla una vez desde Admin CP antes de sincronizarla. Se salta.\n";
        $skipped++;
        continue;
    }

    $oldLen = strlen($row['stylesheet']);
    $newLen = strlen($content);

    // Salvaguarda de contenido: nunca sincronizar un archivo vacío o
    // sospechosamente corto, aunque sea "distinto" del actual.
    if ($newLen === 0) {
        echo "ERROR: {$name} está vacío. No se importa un archivo vacío. Se salta.\n";
        $skipped++;
        continue;
    }
    if ($oldLen > 0 && $newLen < $oldLen * MIN_SIZE_RATIO) {
        echo "ERROR: {$name} bajó de {$oldLen} a {$newLen} bytes (más de " . (int) (100 - MIN_SIZE_RATIO * 100) . "% menos). Probable archivo truncado o a medio guardar — revisar a mano antes de reintentar. Se salta.\n";
        $skipped++;
        continue;
    }

    if ($row['stylesheet'] === $content) {
        echo "{$name}: sin cambios.\n";
        continue;
    }

    if ($dryRun) {
        echo "{$name}: cambiaría (dry-run, no se escribió nada). sid={$row['sid']}, {$oldLen} -> {$newLen} bytes.\n";
        $updated++;
        continue;
    }

    // Backup automático del contenido ANTERIOR, antes de pisarlo.
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }
    file_put_contents(BACKUP_DIR . '/' . $name . '.pre_import_' . date('Ymd_His') . '.css', $row['stylesheet']);

    $db->update_query('themestylesheets', array('stylesheet' => $db->escape_string($content)), "sid='{$row['sid']}'");

    // Regenera cache/themes/theme3/{name} y {name}.min.css de verdad —
    // resuelve {$theme[...]} y reescribe url(...) igual que Admin CP.
    if (cache_stylesheet(STYLESHEETS_TID, $name, $content) === false) {
        echo "ERROR: cache_stylesheet() falló para {$name} (¿permisos de escritura en cache/themes/theme" . STYLESHEETS_TID . "/?).\n";
        continue;
    }

    if (update_theme_stylesheet_list(STYLESHEETS_TID) === false) {
        echo "AVISO: update_theme_stylesheet_list() devolvió false para tid=" . STYLESHEETS_TID . " — el archivo de cache se regeneró igual, pero conviene revisar el tema en Admin CP.\n";
    }

    css_sync_log("{$name}: {$oldLen} -> {$newLen} bytes (sid={$row['sid']})");
    echo "{$name}: actualizado (fila + cache/themes/theme" . STYLESHEETS_TID . "/{$name} + .min.css).\n";
    $updated++;
}

echo "Listo: {$updated} actualizada(s), {$skipped} salteada(s)." . ($dryRun ? ' (dry-run: nada se escribió)' : '') . "\n";
```

**Notas:**
- `glob(STYLESHEETS_DIR . '/*.css')` recorre la carpeta entera — no hace falta listar los archivos a mano ni mantener un mapeo, porque todo lo que vive ahí es tid=3.
- Se llama a `cache_stylesheet()` directo, no a `resync_stylesheet()` — esa función de MyBB solo regenera el cache si el archivo *no existe todavía* ("reparar cache faltante"); acá el archivo va a existir casi siempre y hay que pisarlo con el contenido nuevo. Ver docs/css-sync-design.md.
- El chequeo `$row['stylesheet'] === $content` evita reescribir el cache (y bajarle el `lastmodified`) cuando no cambió nada — importante porque `watch_templates.sh` va a llamar a este script en cada guardado de *cualquier* archivo bajo `templates/`, no solo cuando cambia un `.css`.
- La salvaguarda de tamaño (`MIN_SIZE_RATIO`) corre **antes** de la comparación de "sin cambios" y también en modo `--dry-run` — un dry-run tiene que poder avisar "esto se rechazaría" sin necesidad de sacarlo para descubrirlo.
- El backup (`templates/backups/{name}.pre_import_<timestamp>.css`) y el log (`logs/css_sync.log`, ya cubierto por el `*.log` de `.gitignore`) solo se escriben en una actualización real — nunca en `--dry-run`, nunca en "sin cambios".
- `--dry-run` / `?dry_run=1` corre todo el chequeo (contenido y comparación) pero no escribe fila, cache, backup ni log — para el primer uso real, antes de confiar en que quede enganchado al watcher.

**Verificación:**
1. `php -l import_stylesheets.php` → `No syntax errors detected`.
2. `php import_stylesheets.php --dry-run` → como `global.css`/`op_global.css` ya están sincronizados (confirmado por el usuario), lo esperable es "sin cambios" en las dos. Si alguna dice "cambiaría", vale la pena mirar el diff antes de sacar el dry-run, no asumir que el script está mal.
3. Para probar el camino real sin arriesgar nada: agregar un espacio en blanco inofensivo a `op_global.css`, correr sin `--dry-run`, confirmar el mensaje "actualizado", revisar que apareció el backup en `templates/backups/` y la línea en `logs/css_sync.log`, y revertir el espacio (con su propio import después) para dejar todo como estaba.
4. **Probar la salvaguarda de tamaño a propósito:** vaciar temporalmente una copia de prueba de `op_global.css` (o truncarla a la mitad) y confirmar que el script la rechaza con el mensaje de error, sin tocar la fila ni el cache — antes de confiar en que protege de verdad, verlo fallar de la forma esperada.

## Tarea 4: enganchar al watcher

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

Se llama siempre a los dos, aunque el cambio detectado haya sido en un solo archivo — mismo criterio que ya usa `import_templates.php` (reprocesa todos los templates en cada disparo). El chequeo de "sin cambios" de la Tarea 3 hace que reprocesar los `.css` en cada guardado de un `.html` sea barato (una lectura de archivo y una comparación de string por cada `.css`, nada más).

**Verificación:** con `./watch_templates.sh` corriendo, editar `templates/One_Piece_Gaiden_Templates/stylesheets/op_global.css` (un cambio trivial, como un comentario) y confirmar en la terminal que aparecen las líneas de `import_templates.php` **y** de `import_stylesheets.php` para ese guardado.

## Tarea 5: probar en el servidor

1. **`global.css` no se rompió:** abrir cualquier página del foro (no solo `/op/`) después de correr el import real — layout, botones, el header — nada corrido ni sin estilo.
2. **`op_global.css` no se rompió:** abrir una página con el marco de tres fondos (p. ej. `op/staff/consola_mod.php`) y comparar visualmente contra antes del import.
3. **`url()` relativos:** confirmar en las DevTools (pestaña Red) que las imágenes de fondo de `global.css` (`images/flatty/header-logo.png`, `images/flatty/arrow-down.png`, etc.) siguen cargando con 200, no 404 — es la prueba de que `fix_css_urls_callback` reescribió las rutas relativas a `cache/themes/theme3/` correctamente.
4. **Archivos de cache actualizados:** `stat cache/themes/theme3/op_global.css cache/themes/theme3/op_global.min.css` — la fecha de modificación tiene que ser la del import de prueba de la Tarea 3, punto 3.
5. **Fila de la base actualizada:**
   ```sql
   SELECT sid, tid, name, LENGTH(stylesheet) AS bytes, lastmodified FROM mybb_themestylesheets WHERE tid = 3;
   ```
   `lastmodified` tiene que reflejar el momento del import de prueba.
6. **Idempotencia:** correr `php import_stylesheets.php` una segunda vez sin cambiar nada → todas las líneas dicen "sin cambios", no se vuelve a escribir el cache.

## Tarea 6: commit

```bash
git add templates/One_Piece_Gaiden_Templates/stylesheets/ export_stylesheets.php import_stylesheets.php watch_templates.sh
git commit -m "Sync de hojas de estilo del tema (export/import) + mover global.css/op_global.css a One_Piece_Gaiden_Templates/stylesheets/"
```

Los `git mv` de `templates/global.css`/`templates/op_global.css` ya están hechos; este commit los incluye junto con los scripts nuevos.

`export_stylesheets.php` se agrega o no al commit según si se lo quiere dejar en el repo para la próxima vez que haga falta (mismo criterio que `export_templates.php`, que sigue en el repo hoy) o borrarlo después de este uso.

---

## Rollback

- Dejar de correr `import_stylesheets.php` (sacar la línea de `watch_templates.sh`) no revierte nada por sí solo: la fila de `mybb_themestylesheets` y los archivos de `cache/themes/theme3/` quedan con el último contenido sincronizado.
- **Para deshacer el último import de una hoja puntual:** el backup automático en `templates/backups/{name}.pre_import_<timestamp>.css` tiene el contenido de justo antes de ese import — copiarlo de vuelta a `stylesheets/{name}` y volver a correr `import_stylesheets.php` (sin `--dry-run`) lo sincroniza de nuevo, sin pasar por Admin CP. `logs/css_sync.log` dice cuándo fue cada cambio real, para encontrar el backup correcto si hay varios.
- Si hace falta ir más atrás de lo que cubre el backup automático (o el problema es previo a tener esta herramienta), restaurar desde Admin CP (pegar el contenido viejo en el editor de la hoja de estilo) y guardarlo ahí para que Admin CP regenere el cache.
- El `git mv` de los `.css` se puede deshacer con `git mv templates/One_Piece_Gaiden_Templates/stylesheets/global.css templates/global.css` (e igual para `op_global.css`) si se decide no seguir con este mecanismo.
- Borrar `export_stylesheets.php` / `import_stylesheets.php` no afecta a `export_templates.php` / `import_templates.php` — son pares independientes.
