<?php
/**
 * OPG Image Version
 * Expone $GLOBALS['op_img_ver'] en cada página para cache-busting de imágenes de islas.
 * El valor se actualiza automáticamente al subir imágenes desde op/staff/upload.php.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("global_start", "op_imgver_set");

function op_imgver_info()
{
    return [
        "name"          => "OPG Image Version",
        "description"   => "Cache-busting para imágenes de islas (?v=timestamp). Se actualiza al subir imágenes desde el panel de staff.",
        "website"       => "",
        "author"        => "One Piece Gaiden",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "op_imgver",
        "compatibility" => "18*",
    ];
}

function op_imgver_set()
{
    $ver_file = MYBB_ROOT . "images/op/uploads/_ver";
    $GLOBALS['op_img_ver'] = file_exists($ver_file) ? trim(file_get_contents($ver_file)) : '1';
}
