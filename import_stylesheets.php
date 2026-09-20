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
