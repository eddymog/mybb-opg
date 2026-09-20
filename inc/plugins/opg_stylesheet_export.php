<?php
/**
 * OPG - Exportar hojas de estilo del tema (tid=3) a archivos
 *
 * Herramienta de un solo uso, no un plugin de verdad: no engancha ningún
 * hook. Al ACTIVARLO desde Admin CP > Plugins, escribe el contenido actual
 * de cada hoja de estilo de mybb_themestylesheets con tid=3 a
 * templates/One_Piece_Gaiden_Templates/stylesheets/{nombre}, en el propio
 * servidor. Pensado para bajar esos archivos por FTP y llevarlos al repo en
 * git, sin necesitar SSH ni phpMyAdmin — ver docs/css-sync-design.md.
 *
 * Después de usarlo: Desactivar y borrar este archivo. No hace nada útil
 * estando activo, solo corre una vez al momento de la activación.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function opg_stylesheet_export_info()
{
    return array(
        "name"          => "OPG - Exportar hojas de estilo (tid=3)",
        "description"   => "Un solo uso: al ACTIVARLO escribe las hojas de estilo del tema tid=3 a templates/One_Piece_Gaiden_Templates/stylesheets/. Desactivar y borrar después de usarlo.",
        "website"       => "",
        "author"        => "Cascabelles",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "opg_stylesheet_export",
        "compatibility" => "*"
    );
}

function opg_stylesheet_export_activate()
{
    global $db;

    // templates/One_Piece_Gaiden_Templates/ == tid 3 (ver docs/css-sync-design.md).
    $tid = 3;
    $dir = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $query = $db->simple_select('themestylesheets', 'name, stylesheet, lastmodified', "tid='" . (int) $tid . "'", array('order_by' => 'name'));

    $resumen = array();
    while ($row = $db->fetch_array($query)) {
        $ok = @file_put_contents($dir . '/' . $row['name'], $row['stylesheet']);
        $fecha = $row['lastmodified'] ? my_date('Y-m-d H:i:s', $row['lastmodified']) : '(sin fecha)';
        $resumen[] = ($ok !== false ? 'OK' : 'ERROR') . ": {$row['name']} (" . strlen($row['stylesheet']) . " bytes, lastmodified {$fecha})";
    }

    if (empty($resumen)) {
        $resumen[] = "AVISO: no se encontró ninguna hoja de estilo con tid={$tid}. Confirmar el tid antes de asumir que el tema no tiene hojas.";
    }

    $texto = "Export de hojas de estilo (tid={$tid}) a templates/One_Piece_Gaiden_Templates/stylesheets/ en el servidor:<br>"
        . implode('<br>', array_map('htmlspecialchars', $resumen))
        . '<br><br>Bajar esos archivos por FTP a la copia local del repo. Al terminar, desactivar y borrar este plugin — ya cumplió su función y no hace nada más estando activo.';

    flash_message($texto, 'success');
}

function opg_stylesheet_export_deactivate() {}
