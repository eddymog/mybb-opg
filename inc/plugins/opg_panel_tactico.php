<?php
/** OPG - Panel tactico de temas. */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

if (!defined('OPG_PT_THEME_TID')) {
    define('OPG_PT_THEME_TID', 3);
}

require_once MYBB_ROOT . 'inc/plugins/lib/panel_tactico.php';

$plugins->add_hook('datahandler_post_insert_post_end', 'opg_pt_hook_insert', 100);
$plugins->add_hook('datahandler_post_insert_thread_end', 'opg_pt_hook_insert', 100);
$plugins->add_hook('datahandler_post_update_end', 'opg_pt_hook_update', 100);
$plugins->add_hook('showthread_end', 'opg_pt_hook_showthread');

function opg_panel_tactico_info()
{
    return array(
        'name' => 'OPG - Panel tactico',
        'description' => 'Organiza tecnicas, objetos y recursos registrados en un tema.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '0.2.0',
        'compatibility' => '18*',
    );
}

function opg_panel_tactico_is_installed()
{
    return opg_pt_tables_ready();
}

function opg_pt_template_set_sid()
{
    global $db;
    $query = $db->simple_select('templatesets', 'sid,title');
    while ($set = $db->fetch_array($query)) {
        if (preg_replace('/[^a-zA-Z0-9_\-]/', '_', $set['title']) === 'One_Piece_Gaiden_Templates') {
            return (int)$set['sid'];
        }
    }
    return 0;
}

function opg_pt_install_tables()
{
    global $db;
    $collation = $db->build_create_table_collation();

    if (!$db->table_exists('op_panel_tactico_posts')) {
        $table = $db->table_prefix . 'op_panel_tactico_posts';
        $db->write_query("CREATE TABLE `{$table}` (
            pid INT UNSIGNED NOT NULL,
            tid INT UNSIGNED NOT NULL,
            uid INT UNSIGNED NOT NULL DEFAULT 0,
            source_hash CHAR(64) NOT NULL,
            event_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            index_status VARCHAR(16) NOT NULL DEFAULT 'actualizado',
            error_message VARCHAR(500) NOT NULL DEFAULT '',
            indexed_at INT UNSIGNED NOT NULL,
            PRIMARY KEY (pid),
            KEY thread_status (tid, index_status),
            KEY indexed_at (indexed_at)
        ) ENGINE=InnoDB {$collation}");
    }

    if (!$db->table_exists('op_panel_tactico_eventos')) {
        $table = $db->table_prefix . 'op_panel_tactico_eventos';
        $db->write_query("CREATE TABLE `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tid INT UNSIGNED NOT NULL,
            pid INT UNSIGNED NOT NULL,
            uid INT UNSIGNED NOT NULL DEFAULT 0,
            author_name VARCHAR(120) NOT NULL DEFAULT '',
            post_order SMALLINT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            entity_id VARCHAR(255) NOT NULL DEFAULT '',
            data_json MEDIUMTEXT NULL,
            event_status VARCHAR(16) NOT NULL DEFAULT 'valido',
            visibility_scope VARCHAR(24) NOT NULL DEFAULT 'publico',
            visibility_ref INT UNSIGNED NOT NULL DEFAULT 0,
            source_start INT UNSIGNED NOT NULL DEFAULT 0,
            source_end INT UNSIGNED NOT NULL DEFAULT 0,
            source_hash CHAR(64) NOT NULL,
            post_dateline INT UNSIGNED NOT NULL,
            is_current TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            indexed_at INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_revision (pid, source_hash, post_order),
            KEY post_current (pid, is_current, post_order),
            KEY thread_type (tid, event_type, is_current),
            KEY user_thread (uid, tid, is_current)
        ) ENGINE=InnoDB {$collation}");
    }
}

function opg_pt_install_template()
{
    global $db;
    $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_panel_tactico.html';
    if (!is_file($path)) {
        return;
    }
    $content = rtrim((string)file_get_contents($path));
    $sids = array(-2);
    $themeSid = opg_pt_template_set_sid();
    if ($themeSid > 0) {
        $sids[] = $themeSid;
    }
    foreach ($sids as $sid) {
        $query = $db->simple_select('templates', 'tid', "title='op_panel_tactico' AND sid='" . (int)$sid . "'", array('limit' => 1));
        $templateId = (int)$db->fetch_field($query, 'tid');
        $data = array(
            'template' => $db->escape_string($content),
            'version' => '1800',
            'status' => '',
            'dateline' => TIME_NOW,
        );
        if ($templateId > 0) {
            $db->update_query('templates', $data, "tid='{$templateId}'");
        } else {
            $data['title'] = 'op_panel_tactico';
            $data['sid'] = (int)$sid;
            $db->insert_query('templates', $data);
        }
    }
}

function opg_pt_install_stylesheet()
{
    global $db;
    $name = 'op_panel_tactico.css';
    $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets/' . $name;
    if (!is_file($path)) {
        return;
    }
    $content = (string)file_get_contents($path);
    $query = $db->simple_select('themestylesheets', 'sid', "tid='" . OPG_PT_THEME_TID . "' AND name='{$name}'", array('limit' => 1));
    $sid = (int)$db->fetch_field($query, 'sid');
    $data = array(
        'stylesheet' => $db->escape_string($content),
        'attachedto' => '',
        'cachefile' => $name,
        'lastmodified' => TIME_NOW,
    );
    if ($sid > 0) {
        $db->update_query('themestylesheets', $data, "sid='{$sid}'");
    } else {
        $data['name'] = $name;
        $data['tid'] = OPG_PT_THEME_TID;
        $db->insert_query('themestylesheets', $data);
    }
    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';
    cache_stylesheet(OPG_PT_THEME_TID, $name, $content);
    update_theme_stylesheet_list(OPG_PT_THEME_TID, false, true);
}

function opg_pt_install_showthread_placeholder()
{
    global $db;
    $query = $db->simple_select('templates', 'tid,template', "title='showthread'");
    while ($template = $db->fetch_array($query)) {
        if (strpos($template['template'], '{$op_panel_tactico_link}') !== false) {
            continue;
        }
        $updated = preg_replace(
            '/' . preg_quote('{$newreply}', '/') . '/',
            '{$op_panel_tactico_link}' . "\n\t\t" . '{$newreply}',
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

function opg_panel_tactico_install()
{
    opg_pt_install_tables();
    opg_pt_install_template();
    opg_pt_install_stylesheet();
    opg_pt_install_showthread_placeholder();
}

function opg_panel_tactico_activate()
{
    opg_pt_install_tables();
    opg_pt_install_template();
    opg_pt_install_stylesheet();
    opg_pt_install_showthread_placeholder();
}

function opg_panel_tactico_deactivate()
{
    // El indice se conserva para poder reactivar sin reconstruirlo.
}

function opg_panel_tactico_uninstall()
{
    // Deliberadamente no destructivo: no se eliminan datos ni recursos.
}

function opg_pt_hook_insert(&$handler)
{
    $pid = (int)($handler->return_values['pid'] ?? 0);
    if ($pid > 0) {
        opg_pt_index_post($pid);
    }
}

function opg_pt_hook_update(&$handler)
{
    $pid = (int)($handler->pid ?? 0);
    if ($pid > 0) {
        opg_pt_index_post($pid);
    }
}

function opg_pt_hook_showthread()
{
    global $op_panel_tactico_link, $mybb, $tid;
    $op_panel_tactico_link = '';
    if (!opg_pt_tables_ready() || empty($tid)) {
        return;
    }
    $url = rtrim($mybb->settings['bburl'], '/') . '/op/panel_tactico.php?tid=' . (int)$tid;
    $op_panel_tactico_link = '<a href="' . opg_pt_escape($url) . '" class="button pt-thread-button bradius">'
        . '<span><i class="fa-solid fa-compass-drafting" aria-hidden="true"></i> PANEL TACTICO</span></a>';
}
