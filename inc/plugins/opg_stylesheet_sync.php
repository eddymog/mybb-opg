<?php

/**
 * OPG - Stylesheet File Sync
 * - Detects FTP-uploaded changes to templates/One_Piece_Gaiden_Templates/stylesheets/*.css
 *   and pushes them to mybb_themestylesheets + regenera cache/themes/theme3/ every ~10 seconds.
 * - Writes the stylesheet back to disk on every admin save (mirrors template_sync.php's
 *   other half, so an edit made from Admin CP doesn't drift from git).
 *
 * Mismo patrón que inc/plugins/template_sync.php (hook en global_start, throttle
 * por archivo de cache con el timestamp del último ciclo), pero para hojas de
 * estilo en vez de templates .html. A diferencia de los templates, una hoja de
 * estilo necesita pasar por cache_stylesheet()/update_theme_stylesheet_list()
 * (admin/inc/functions_themes.php) para que el CSS que sirve el foro
 * (cache/themes/theme3/*.css) se regenere de verdad — ver docs/css-sync-design.md.
 *
 * Mismas salvaguardas que opg_stylesheet_import.php / import_stylesheets.php:
 * rechaza contenido vacío o sospechosamente más corto que el actual, guarda un
 * backup del contenido anterior antes de pisarlo, y deja un registro en
 * logs/css_sync.log. Corre contra producción sin staging en el medio, así que
 * ninguna reemplaza revisar el diff antes de un guardado importante.
 *
 * Con esto activo, el plugin de un solo uso opg_stylesheet_import.php (Activar
 * para sincronizar a mano) queda redundante para el flujo normal — un archivo
 * subido por FTP se sincroniza solo dentro de los próximos ~10 segundos de
 * tráfico al foro. Se puede dejar como está por si hace falta forzar una
 * sincronización inmediata sin esperar tráfico.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

define('OPG_CSS_SYNC_INTERVAL',  10);
define('OPG_CSS_SYNC_TID',       3); // templates/One_Piece_Gaiden_Templates/ == tid 3
define('OPG_CSS_SYNC_DIR',       'templates/One_Piece_Gaiden_Templates/stylesheets');
define('OPG_CSS_SYNC_CACHE',     'cache/opg_css_sync_last.php');
define('OPG_CSS_SYNC_BACKUPDIR', 'templates/backups');
define('OPG_CSS_SYNC_LOGFILE',   'logs/css_sync.log');
define('OPG_CSS_SYNC_MINRATIO',  0.5);

function opg_stylesheet_sync_info()
{
    return [
        'name'          => 'OPG - Stylesheet File Sync',
        'description'   => 'Sincroniza templates/One_Piece_Gaiden_Templates/stylesheets/*.css contra mybb_themestylesheets: importa cambios subidos por FTP cada ~10 segundos, y escribe a disco cada guardado desde Admin CP. Ver docs/css-sync-design.md.',
        'author'        => 'OPG',
        'version'       => '1.0',
        'compatibility' => '18*',
    ];
}

function opg_stylesheet_sync_is_installed()
{
    return file_exists(MYBB_ROOT . OPG_CSS_SYNC_CACHE);
}

function opg_stylesheet_sync_install()
{
    // Marca todo lo que exista ahora como ya sincronizado — solo lo que se
    // suba/cambie después de este momento se importa.
    if (!is_dir(MYBB_ROOT . 'cache')) {
        @mkdir(MYBB_ROOT . 'cache', 0755, true);
    }
    file_put_contents(MYBB_ROOT . OPG_CSS_SYNC_CACHE, TIME_NOW);
}

function opg_stylesheet_sync_uninstall()
{
    @unlink(MYBB_ROOT . OPG_CSS_SYNC_CACHE);
}

// ── Hook: archivo → DB en cada request del foro (throttled) ────────────────

$plugins->add_hook('global_start', 'opg_stylesheet_sync_check_files');

function opg_stylesheet_sync_check_files()
{
    global $db;

    $cache_file = MYBB_ROOT . OPG_CSS_SYNC_CACHE;
    $last_sync  = file_exists($cache_file) ? (int) file_get_contents($cache_file) : 0;

    if (TIME_NOW - $last_sync < OPG_CSS_SYNC_INTERVAL) {
        return;
    }

    $now = TIME_NOW;
    $dir = MYBB_ROOT . OPG_CSS_SYNC_DIR;

    if (!is_dir($dir)) {
        file_put_contents($cache_file, $now);
        return;
    }

    $candidates = [];
    foreach (glob($dir . '/*.css') as $path) {
        if (filemtime($path) > $last_sync) {
            $candidates[] = $path;
        }
    }

    if (empty($candidates)) {
        file_put_contents($cache_file, $now);
        return;
    }

    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';

    foreach ($candidates as $path) {
        $name    = basename($path);
        $content = file_get_contents($path);

        $row = $db->fetch_array($db->simple_select(
            'themestylesheets',
            'sid, stylesheet',
            "tid='" . OPG_CSS_SYNC_TID . "' AND name='" . $db->escape_string($name) . "'"
        ));

        // No hay fila para esta hoja — no se crea una automáticamente, mismo
        // comportamiento que import_stylesheets.php / opg_stylesheet_import.php.
        if (!$row) {
            continue;
        }

        $oldLen = strlen($row['stylesheet']);
        $newLen = strlen($content);

        // Salvaguarda de contenido: nunca sincronizar un archivo vacío o
        // sospechosamente corto (archivo truncado o a medio subir por FTP).
        // No hay dónde avisar en este camino automático (no hay una terminal
        // ni una página de Admin CP escuchando) — se salta en silencio y se
        // reintenta solo en el próximo ciclo de 10s si el archivo se corrige.
        // logs/css_sync.log sigue siendo el lugar para confirmar qué pasó.
        if ($newLen === 0 || ($oldLen > 0 && $newLen < $oldLen * OPG_CSS_SYNC_MINRATIO)) {
            continue;
        }

        if ($row['stylesheet'] === $content) {
            continue;
        }

        // Backup del contenido ANTERIOR, antes de pisarlo.
        $backupDir = MYBB_ROOT . OPG_CSS_SYNC_BACKUPDIR;
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        @file_put_contents($backupDir . '/' . $name . '.pre_import_' . date('Ymd_His') . '.css', $row['stylesheet']);

        $db->update_query('themestylesheets', ['stylesheet' => $db->escape_string($content)], "sid='{$row['sid']}'");

        // Regenera cache/themes/theme3/{name} y {name}.min.css de verdad —
        // resuelve {$theme[...]} y reescribe url(...) igual que Admin CP.
        cache_stylesheet(OPG_CSS_SYNC_TID, $name, $content);
        update_theme_stylesheet_list(OPG_CSS_SYNC_TID);

        $logFile = MYBB_ROOT . OPG_CSS_SYNC_LOGFILE;
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0755, true);
        }
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] {$name}: {$oldLen} -> {$newLen} bytes (sid={$row['sid']}, auto)\n", FILE_APPEND);
    }

    file_put_contents($cache_file, $now);
}

// ── Hook: DB → archivo en cada guardado desde Admin CP ──────────────────────
//
// MyBB tiene dos editores de hoja de estilo (simple y avanzado), cada uno con
// su propio hook de "commit" — hay que engancharse a los dos para no perderse
// un guardado según qué editor haya usado el admin (admin/modules/style/
// themes.php:2141 y :2376). En vez de depender del nombre exacto de la
// variable local de cada rama (que difiere entre las dos: $new_stylesheet en
// la simple, $mybb->input['stylesheet'] en la avanzada), se relee la fila
// recién guardada por $sid — ya está actualizada en ese punto en las dos
// ramas, así que es la misma fuente de verdad sin depender de detalles
// internos de admin/modules/style/themes.php que podrían cambiar.

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_style_themes_edit_stylesheet_simple_commit', 'opg_stylesheet_sync_write_file');
    $plugins->add_hook('admin_style_themes_edit_stylesheet_advanced_commit', 'opg_stylesheet_sync_write_file');
}

function opg_stylesheet_sync_write_file()
{
    global $db, $sid;

    if (empty($sid)) {
        return;
    }

    $row = $db->fetch_array($db->simple_select(
        'themestylesheets',
        'tid, name, stylesheet',
        "sid='" . (int) $sid . "'"
    ));

    if (!$row || (int) $row['tid'] !== OPG_CSS_SYNC_TID) {
        return;
    }

    $dir = MYBB_ROOT . OPG_CSS_SYNC_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    file_put_contents($dir . '/' . $row['name'], $row['stylesheet']);
}
