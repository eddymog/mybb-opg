<?php
/**
 * OPG - Bitacora de rol.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

if (!defined('OP_TEMAS_THEME_TID')) {
    define('OP_TEMAS_THEME_TID', 3);
}
if (!defined('OP_TEMAS_HEADER_ENABLED')) {
    define('OP_TEMAS_HEADER_ENABLED', true);
}

require_once MYBB_ROOT . 'inc/plugins/op_bitacora/functions.php';

$plugins->add_hook('datahandler_post_insert_post_end', 'op_bitacora_hook_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'op_bitacora_hook_post');
$plugins->add_hook('class_moderation_approve_posts', 'op_bitacora_hook_approve_posts');
$plugins->add_hook('class_moderation_approve_threads', 'op_bitacora_hook_approve_threads');
$plugins->add_hook('global_intermediate', 'op_bitacora_hook_header');

function op_bitacora_info()
{
    return array(
        'name' => 'OPG - Bitácora de rol',
        'description' => 'Sigue rondas de rol por personaje y muestra cuando debe responder.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.0',
        'compatibility' => '18*',
    );
}

function op_bitacora_is_installed()
{
    global $db;
    return $db->table_exists('op_temas_seguidos')
        && $db->table_exists('op_temas_participantes');
}

function op_bitacora_template_set_sid()
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

function op_bitacora_instalar_plantillas()
{
    global $db;
    $sourceDir = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/';
    $themeSid = op_bitacora_template_set_sid();
    $sids = array(-2);
    if ($themeSid > 0) {
        $sids[] = $themeSid;
    }

    foreach (op_bitacora_template_definitions() as $title => $file) {
        $path = $sourceDir . $file;
        if (!is_file($path)) {
            continue;
        }
        $content = rtrim((string)file_get_contents($path));
        if ($content === '') {
            continue;
        }

        foreach ($sids as $sid) {
            $escapedTitle = $db->escape_string($title);
            $query = $db->simple_select('templates', 'tid', "title='{$escapedTitle}' AND sid='" . (int)$sid . "'", array('limit' => 1));
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
    }
}

function op_bitacora_instalar_stylesheet()
{
    global $db;
    $name = 'op_bitacora.css';
    $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets/' . $name;
    if (!is_file($path)) {
        return;
    }
    $content = (string)file_get_contents($path);
    if ($content === '') {
        return;
    }

    $escapedName = $db->escape_string($name);
    $query = $db->simple_select(
        'themestylesheets',
        'sid',
        "tid='" . OP_TEMAS_THEME_TID . "' AND name='{$escapedName}'",
        array('limit' => 1)
    );
    $sid = (int)$db->fetch_field($query, 'sid');
    $data = array(
        'name' => $escapedName,
        'tid' => OP_TEMAS_THEME_TID,
        'attachedto' => '',
        'stylesheet' => $db->escape_string($content),
        'cachefile' => $escapedName,
        'lastmodified' => TIME_NOW,
    );
    if ($sid > 0) {
        unset($data['name'], $data['tid']);
        $db->update_query('themestylesheets', $data, "sid='{$sid}'");
    } else {
        $db->insert_query('themestylesheets', $data);
    }

    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';
    cache_stylesheet(OP_TEMAS_THEME_TID, $name, $content);
    update_theme_stylesheet_list(OP_TEMAS_THEME_TID, false, true);
}

function op_bitacora_limpiar_recursos_anteriores()
{
    global $db;

    $legacyTemplates = array('op_temas', 'op_temas_tracker', 'op_temas_header');
    $escaped = array();
    foreach ($legacyTemplates as $title) {
        $escaped[] = "'" . $db->escape_string($title) . "'";
    }
    $db->delete_query('templates', 'title IN (' . implode(',', $escaped) . ')');
    $db->delete_query(
        'themestylesheets',
        "name='op_temas_tracker.css' AND tid='" . OP_TEMAS_THEME_TID . "'"
    );

    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';
    update_theme_stylesheet_list(OP_TEMAS_THEME_TID, false, true);
}

function op_bitacora_retirar_registro_anterior()
{
    global $cache;

    $pluginCache = $cache->read('plugins');
    if (!is_array($pluginCache)
        || empty($pluginCache['active'])
        || !isset($pluginCache['active']['op_temas_tracker'])) {
        return;
    }

    unset($pluginCache['active']['op_temas_tracker']);
    $cache->update('plugins', $pluginCache);
}

function op_bitacora_install()
{
    global $db;
    $collation = $db->build_create_table_collation();

    if (!$db->table_exists('op_temas_seguidos')) {
        $table = $db->table_prefix . 'op_temas_seguidos';
        $db->write_query("CREATE TABLE `{$table}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            personaje_uid INT UNSIGNED NOT NULL,
            tid INT UNSIGNED NOT NULL,
            ronda_inicio_pid INT UNSIGNED NOT NULL DEFAULT 0,
            override_estado VARCHAR(12) NOT NULL DEFAULT 'auto',
            narrador_uid INT UNSIGNED NOT NULL DEFAULT 0,
            creado_en INT UNSIGNED NOT NULL,
            actualizado_en INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY personaje_tema (personaje_uid, tid),
            KEY tema (tid),
            KEY personaje_actualizado (personaje_uid, actualizado_en)
        ) ENGINE=InnoDB {$collation}");
    }

    if (!$db->table_exists('op_temas_participantes')) {
        $table = $db->table_prefix . 'op_temas_participantes';
        $db->write_query("CREATE TABLE `{$table}` (
            seguimiento_id INT UNSIGNED NOT NULL,
            participante_uid INT UNSIGNED NOT NULL,
            origen VARCHAR(10) NOT NULL DEFAULT 'auto',
            creado_en INT UNSIGNED NOT NULL,
            PRIMARY KEY (seguimiento_id, participante_uid),
            KEY participante (participante_uid)
        ) ENGINE=InnoDB {$collation}");
    }

    op_bitacora_actualizar_esquema();

    op_bitacora_instalar_plantillas();
    op_bitacora_instalar_stylesheet();
}

function op_bitacora_insertar_placeholder_header()
{
    global $db;
    $query = $db->simple_select('templates', 'tid,template', "title='header'");
    while ($template = $db->fetch_array($query)) {
        if (strpos($template['template'], '{$op_temas_header}') !== false) {
            continue;
        }
        $updated = preg_replace(
            '/(<if \$g_is_staff then>)/',
            "{\$op_temas_header}\n$1",
            $template['template'],
            1
        );
        if ($updated !== $template['template']) {
            $db->update_query('templates', array(
                'template' => $db->escape_string($updated),
                'dateline' => TIME_NOW,
            ), "tid='" . (int)$template['tid'] . "'");
        }
    }
}

function op_bitacora_activate()
{
    op_bitacora_actualizar_esquema();
    op_bitacora_instalar_plantillas();
    op_bitacora_instalar_stylesheet();
    op_bitacora_insertar_placeholder_header();
    op_bitacora_limpiar_recursos_anteriores();
    op_bitacora_retirar_registro_anterior();
}

function op_bitacora_deactivate()
{
    // El placeholder queda inerte para convivir con template_sync.
}

function op_bitacora_uninstall()
{
    global $db;
    if ($db->table_exists('op_temas_participantes')) {
        $db->drop_table('op_temas_participantes');
    }
    if ($db->table_exists('op_temas_seguidos')) {
        $db->drop_table('op_temas_seguidos');
    }

    $titles = array_merge(
        array_keys(op_bitacora_template_definitions()),
        array('op_temas', 'op_temas_tracker', 'op_temas_header')
    );
    $escaped = array();
    foreach ($titles as $title) {
        $escaped[] = "'" . $db->escape_string($title) . "'";
    }
    $db->delete_query('templates', 'title IN (' . implode(',', $escaped) . ')');
    $db->delete_query('themestylesheets', "name IN ('op_bitacora.css','op_temas_tracker.css') AND tid='" . OP_TEMAS_THEME_TID . "'");

    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';
    update_theme_stylesheet_list(OP_TEMAS_THEME_TID, false, true);
}

function op_bitacora_hook_post(&$handler)
{
    $pid = (int)($handler->return_values['pid'] ?? 0);
    if ($pid > 0) {
        op_bitacora_procesar_post($pid);
    }
}

function op_bitacora_hook_approve_posts($pids)
{
    if (!is_array($pids)) {
        $pids = array($pids);
    }
    $pids = array_values(array_unique(array_filter(array_map('intval', $pids))));
    sort($pids, SORT_NUMERIC);
    foreach ($pids as $pid) {
        op_bitacora_procesar_post($pid);
    }
}

function op_bitacora_hook_approve_threads($tids)
{
    global $db;
    if (!is_array($tids)) {
        $tids = array($tids);
    }
    $tids = array_values(array_unique(array_filter(array_map('intval', $tids))));
    if (empty($tids)) {
        return;
    }

    $query = $db->simple_select(
        'posts',
        'pid',
        'visible=1 AND tid IN (' . implode(',', $tids) . ')',
        array('order_by' => 'pid', 'order_dir' => 'ASC')
    );
    while ($pid = $db->fetch_field($query, 'pid')) {
        op_bitacora_procesar_post((int)$pid);
    }
}

function op_bitacora_hook_header()
{
    global $mybb, $op_temas_header;
    $op_temas_header = '';

    if (!OP_TEMAS_HEADER_ENABLED || empty($mybb->user['uid'])) {
        return;
    }

    $op_temas_header = op_bitacora_render_header((int)$mybb->user['uid']);
}
