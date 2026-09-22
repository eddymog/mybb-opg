<?php
/**
 * Puente temporal para instalaciones que aun tienen activo op_temas_tracker.
 * No registra hooks propios ni elimina las tablas compartidas.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/op_bitacora.php';

function op_temas_tracker_info()
{
    return array(
        'name' => 'OPG - Bitácora de rol (compatibilidad)',
        'description' => 'Puente temporal hacia op_bitacora. Puede desactivarse y eliminarse sin borrar seguimientos.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.0',
        'compatibility' => '18*',
    );
}

function op_temas_tracker_is_installed()
{
    global $db;
    return $db->table_exists('op_temas_seguidos')
        && $db->table_exists('op_temas_participantes');
}

function op_temas_tracker_install()
{
    // La instalacion y las tablas pertenecen ahora a op_bitacora.
}

function op_temas_tracker_activate()
{
    // Cargar este puente ya registra los hooks de op_bitacora.
}

function op_temas_tracker_deactivate()
{
    // No se eliminan datos ni recursos compartidos.
}

function op_temas_tracker_uninstall()
{
    // Intencionadamente no destructivo durante la migracion.
}
