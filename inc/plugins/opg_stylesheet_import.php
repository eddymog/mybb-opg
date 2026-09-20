<?php
/**
 * OPG - Importar hojas de estilo del tema (tid=3) desde archivos
 *
 * Complemento de opg_stylesheet_export.php, en la dirección contraria: al
 * ACTIVARLO desde Admin CP > Plugins, lee cada archivo de
 * templates/One_Piece_Gaiden_Templates/stylesheets/*.css (subido antes por
 * FTP) y actualiza mybb_themestylesheets + regenera cache/themes/theme3/ con
 * las funciones reales de MyBB (admin/inc/functions_themes.php) — mismo
 * mecanismo que import_stylesheets.php, pero disparado por "Activar" en vez
 * de por CLI/watch_templates.sh, para cuando no hay acceso SSH. Ver
 * docs/css-sync-design.md.
 *
 * Salvaguardas antes de escribir cualquier hoja: rechaza contenido vacío o
 * menor a la mitad del actual (archivo truncado o a medio subir por FTP),
 * guarda un backup del contenido anterior en templates/backups/, y deja un
 * registro en logs/css_sync.log. Ninguna reemplaza revisar el resultado
 * antes de subir el archivo por FTP.
 *
 * No es un plugin de verdad, no engancha ningún hook — solo corre al
 * activarse. Se puede repetir la sincronización desactivando y volviendo a
 * activar cuantas veces haga falta.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function opg_stylesheet_import_info()
{
    return array(
        "name"          => "OPG - Importar hojas de estilo (tid=3)",
        "description"   => "Al ACTIVARLO, sincroniza templates/One_Piece_Gaiden_Templates/stylesheets/*.css (subidos por FTP) contra la base y regenera el cache. Desactivar y volver a activar para repetir con archivos nuevos.",
        "website"       => "",
        "author"        => "Cascabelles",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "opg_stylesheet_import",
        "compatibility" => "*"
    );
}

function opg_stylesheet_import_activate()
{
    global $db;

    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';

    $tid = 3;
    $dir       = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets';
    $backupDir = MYBB_ROOT . 'templates/backups';
    $logFile   = MYBB_ROOT . 'logs/css_sync.log';
    $minRatio  = 0.5;

    if (!is_dir($dir)) {
        flash_message('No existe ' . htmlspecialchars($dir) . ' en el servidor — no hay nada para importar.', 'error');
        return;
    }

    $resumen  = array();
    $archivos = glob($dir . '/*.css');

    foreach ($archivos as $path) {
        $name    = basename($path);
        $content = file_get_contents($path);

        $row = $db->fetch_array($db->simple_select(
            'themestylesheets',
            'sid, stylesheet',
            "tid='" . (int) $tid . "' AND name='" . $db->escape_string($name) . "'"
        ));

        if (!$row) {
            $resumen[] = "AVISO: {$name} no tiene fila en mybb_themestylesheets (tid={$tid}) — crear una vez desde Admin CP. Se salta.";
            continue;
        }

        $oldLen = strlen($row['stylesheet']);
        $newLen = strlen($content);

        // Salvaguarda de contenido: nunca sincronizar un archivo vacío o
        // sospechosamente corto, aunque sea "distinto" del actual.
        if ($newLen === 0) {
            $resumen[] = "ERROR: {$name} está vacío. No se importa un archivo vacío. Se salta.";
            continue;
        }
        if ($oldLen > 0 && $newLen < $oldLen * $minRatio) {
            $resumen[] = "ERROR: {$name} bajó de {$oldLen} a {$newLen} bytes (más de " . (int) (100 - $minRatio * 100) . "% menos). Probable archivo truncado o a medio subir por FTP — revisar antes de reintentar. Se salta.";
            continue;
        }

        if ($row['stylesheet'] === $content) {
            $resumen[] = "{$name}: sin cambios.";
            continue;
        }

        // Backup del contenido ANTERIOR, antes de pisarlo.
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        @file_put_contents($backupDir . '/' . $name . '.pre_import_' . date('Ymd_His') . '.css', $row['stylesheet']);

        $db->update_query('themestylesheets', array('stylesheet' => $db->escape_string($content)), "sid='{$row['sid']}'");

        // Regenera cache/themes/theme{tid}/{name} y {name}.min.css de verdad —
        // resuelve {$theme[...]} y reescribe url(...) igual que Admin CP.
        if (cache_stylesheet($tid, $name, $content) === false) {
            $resumen[] = "ERROR: cache_stylesheet() falló para {$name} (¿permisos de escritura en cache/themes/theme{$tid}/?).";
            continue;
        }

        if (update_theme_stylesheet_list($tid) === false) {
            $resumen[] = "AVISO: update_theme_stylesheet_list() devolvió false para tid={$tid} — el cache se regeneró igual, conviene revisar el tema en Admin CP.";
        }

        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0755, true);
        }
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] {$name}: {$oldLen} -> {$newLen} bytes (sid={$row['sid']})\n", FILE_APPEND);

        $resumen[] = "OK: {$name} actualizado ({$oldLen} -> {$newLen} bytes, cache regenerado).";
    }

    if (empty($resumen)) {
        $resumen[] = "AVISO: no se encontró ningún .css en " . $dir;
    }

    $texto = "Import de hojas de estilo (tid={$tid}) desde templates/One_Piece_Gaiden_Templates/stylesheets/:<br>"
        . implode('<br>', array_map('htmlspecialchars', $resumen))
        . '<br><br>Para repetir la sincronización con archivos nuevos: subir los .css actualizados por FTP, desactivar este plugin y volver a activarlo.';

    flash_message($texto, 'success');
}

function opg_stylesheet_import_deactivate() {}
