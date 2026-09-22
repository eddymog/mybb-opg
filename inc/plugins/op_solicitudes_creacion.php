<?php
/**
 * OPG - Solicitudes de creacion.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/op_solicitudes_creacion/functions.php';

$plugins->add_hook('global_intermediate', 'op_solicitudes_creacion_hook_header');

function op_solicitudes_creacion_info()
{
    return array(
        'name' => 'OPG - Solicitudes de creación',
        'description' => 'Centraliza las solicitudes de creación, sus contadores y su moderación.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.1',
        'compatibility' => '18*',
    );
}

function op_solicitudes_creacion_instalar_tabla()
{
    global $db;

    if ($db->table_exists('op_solicitudes_creacion_resoluciones')) {
        return;
    }

    $table = $db->table_prefix . 'op_solicitudes_creacion_resoluciones';
    $collation = $db->build_create_table_collation();
    $db->write_query("CREATE TABLE `{$table}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tid INT UNSIGNED NOT NULL,
        perfil_fid INT UNSIGNED NOT NULL,
        accion VARCHAR(10) NOT NULL,
        moderador_uid INT UNSIGNED NOT NULL,
        resuelto_en INT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY solicitud (tid),
        KEY moderador (moderador_uid),
        KEY perfil_accion (perfil_fid, accion)
    ) ENGINE=InnoDB {$collation}");
}

function op_solicitudes_creacion_template_set_sid()
{
    global $db;

    $query = $db->simple_select('templatesets', 'sid,title');
    while ($set = $db->fetch_array($query)) {
        $directory = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $set['title']);
        if ($directory === 'One_Piece_Gaiden_Templates') {
            return (int)$set['sid'];
        }
    }

    return 0;
}

function op_solicitudes_creacion_instalar_plantilla()
{
    global $db;

    $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/staff_solicitudes_creacion.html';
    if (!is_file($path)) {
        return false;
    }

    $content = rtrim((string)file_get_contents($path));
    if ($content === '') {
        return false;
    }

    $sids = array(-2);
    $themeSid = op_solicitudes_creacion_template_set_sid();
    if ($themeSid > 0) {
        $sids[] = $themeSid;
    }

    foreach ($sids as $sid) {
        $title = 'staff_solicitudes_creacion';
        $escapedTitle = $db->escape_string($title);
        $query = $db->simple_select(
            'templates',
            'tid',
            "title='{$escapedTitle}' AND sid='" . (int)$sid . "'",
            array('limit' => 1)
        );
        $templateId = (int)$db->fetch_field($query, 'tid');
        $data = array(
            'title' => $escapedTitle,
            'template' => $db->escape_string($content),
            'sid' => (int)$sid,
            'version' => '1800',
            'status' => '',
            'dateline' => TIME_NOW,
        );
        if ($templateId > 0) {
            unset($data['title'], $data['sid']);
            $db->update_query('templates', $data, "tid='{$templateId}'");
        } else {
            $db->insert_query('templates', $data);
        }
    }

    return true;
}

function op_solicitudes_creacion_is_installed()
{
    global $db;

    $query = $db->simple_select(
        'templates',
        'tid',
        "title='staff_solicitudes_creacion'",
        array('limit' => 1)
    );
    return (int)$db->fetch_field($query, 'tid') > 0;
}

function op_solicitudes_creacion_install()
{
    op_solicitudes_creacion_instalar_tabla();
    op_solicitudes_creacion_instalar_plantilla();
}

function op_solicitudes_creacion_activate()
{
    op_solicitudes_creacion_instalar_tabla();
    op_solicitudes_creacion_instalar_plantilla();
}

function op_solicitudes_creacion_deactivate()
{
    // El header se mantiene manualmente y la plantilla conserva sus datos.
}

function op_solicitudes_creacion_uninstall()
{
    global $db;
    $db->delete_query('templates', "title='staff_solicitudes_creacion'");
    if ($db->table_exists('op_solicitudes_creacion_resoluciones')) {
        $db->drop_table('op_solicitudes_creacion_resoluciones');
    }
}

function op_solicitudes_creacion_hook_header()
{
    global $mybb, $op_solicitudes_creacion_pendientes;

    $op_solicitudes_creacion_pendientes = 0;
    $uid = (int)($mybb->user['uid'] ?? 0);
    if (!op_solicitudes_creacion_es_staff($uid)) {
        return;
    }

    $conteo = op_solicitudes_creacion_contar_pendientes();
    $op_solicitudes_creacion_pendientes = (int)$conteo['sin_responder']
        + (int)$conteo['reabiertas'];
}
