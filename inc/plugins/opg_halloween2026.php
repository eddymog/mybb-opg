<?php
/** OPG - La Cosecha de Calabazas Malditas 2026. */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

if (!defined('OPG_HLW_THEME_TID')) {
    define('OPG_HLW_THEME_TID', 3);
}

require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_secretos.php';

$plugins->add_hook('datahandler_post_insert_post_end', 'opg_hlw_hook_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'opg_hlw_hook_post');
$plugins->add_hook('class_moderation_approve_posts', 'opg_hlw_hook_posts');
$plugins->add_hook('class_moderation_restore_posts', 'opg_hlw_hook_posts');
$plugins->add_hook('class_moderation_approve_threads', 'opg_hlw_hook_threads');
$plugins->add_hook('class_moderation_restore_threads', 'opg_hlw_hook_threads');
$plugins->add_hook('class_moderation_unapprove_posts', 'opg_hlw_hook_reverse_posts');
$plugins->add_hook('class_moderation_soft_delete_posts', 'opg_hlw_hook_reverse_posts');
$plugins->add_hook('class_moderation_delete_post_start', 'opg_hlw_hook_reverse_post');
$plugins->add_hook('class_moderation_unapprove_threads', 'opg_hlw_hook_reverse_threads');
$plugins->add_hook('class_moderation_soft_delete_threads', 'opg_hlw_hook_reverse_threads');
$plugins->add_hook('class_moderation_delete_thread_start', 'opg_hlw_hook_reverse_thread');
$plugins->add_hook('class_moderation_move_simple', 'opg_hlw_hook_move');
$plugins->add_hook('class_moderation_move_thread_redirect', 'opg_hlw_hook_move');
$plugins->add_hook('class_moderation_move_threads', 'opg_hlw_hook_move');
$plugins->add_hook('pre_output_page', 'opg_hlw_hook_secret_script');
$plugins->add_hook('rt_chat_commit_message_insert', 'opg_hlw_hook_chat_secret');

/**
 * Secretos ocultos: en las paginas que tengan alguno pendiente para este usuario, escribe al final un pequeño script en la propia
 * pagina (no un archivo aparte) con un billete opaco. La configuracion (codigos y selectores) no va en el HTML: el script la pide
 * al servidor cuando alguien interactua con la pagina, y llega cifrada. Solo se añade a quien puede descubrir y aun tiene algo.
 */
function opg_hlw_hook_secret_script($page)
{
    global $mybb;
    $uid = (int)$mybb->user['uid'];
    $position = opg_hlw_secret_insert_position($page);
    // Solo paginas HTML, y no peticiones que no sean un GET normal.
    if ($position === false || $uid <= 0 || !defined('THIS_SCRIPT') || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return $page;
    }
    $pageEntries = opg_hlw_secret_page_entries(THIS_SCRIPT);
    // Atajo sin consultas: si lo unico que queda en esta pagina son secretos globales que el navegador ya dice tener, no hay nada que hacer.
    if (!opg_hlw_secret_pending_by_cookie($pageEntries)) {
        return $page;
    }
    if (!opg_hlw_secrets_ready()) {
        return $page;
    }
    $config = opg_hlw_config();
    if (!opg_hlw_secrets_open($config, $uid) || !opg_hlw_eligible_character($uid)) {
        return $page;
    }
    $found = opg_hlw_secret_mine($uid);
    // Recuerda en el navegador los secretos ya descubiertos (para el atajo de arriba), solo si ha cambiado.
    $numbers = array();
    foreach (opg_hlw_secret_catalog() as $key => $secret) {
        if (isset($found[$key])) {
            $numbers[] = (int)$secret['numero'];
        }
    }
    sort($numbers);
    if ($numbers !== opg_hlw_secret_cookie_found() && !headers_sent()) {
        setcookie('ei_g', implode('.', $numbers), array('expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax'));
    }
    // ¿Queda algo pendiente en esta pagina? Si el usuario ya descubrio todo lo de aqui, no se añade nada.
    if (array_diff_key($pageEntries, $found) === array()) {
        return $page;
    }
    require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_cliente.php';
    $ticket = opg_hlw_secret_ticket($uid, THIS_SCRIPT, (string)($_SERVER['SCRIPT_NAME'] ?? ''), opg_hlw_secret_condition_params());
    return substr_replace($page, opg_hlw_client_script($ticket, (string)$mybb->post_code), $position, 0);
}

/**
 * Secreto del chat: cuando RT Chat acaba de guardar un mensaje publico con la palabra, se reclama el secreto en el
 * servidor y el resultado se deja en una cookie de 90 s que el script del indice recoge para mostrar la felicitacion.
 * Un fallo aqui nunca debe romper el chat.
 */
function opg_hlw_hook_chat_secret(&$data)
{
    global $mybb;
    try {
        $uid = (int)($data['uid'] ?? 0);
        if ($uid <= 0 || $uid !== (int)$mybb->user['uid'] || $mybb->get_input('to_uid', MyBB::INPUT_INT) !== 0) {
            return;
        }
        opg_hlw_secret_set_result_cookie(opg_hlw_secret_claim_chat($uid, (string)($data['message'] ?? '')));
    } catch (Throwable $e) {
        // Silencioso: el chat sigue funcionando.
    }
}

function opg_halloween2026_info()
{
    return array(
        'name' => 'OPG - Halloween 2026',
        'description' => 'Actividad, Calabazas Malditas, tienda y estadisticas de Halloween 2026.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.0.0',
        'compatibility' => '18*',
    );
}

function opg_halloween2026_is_installed()
{
    return opg_hlw_tables_ready();
}

function opg_halloween2026_install()
{
    if (!opg_hlw_tables_ready()) {
        flash_message('Ejecuta docs/halloween2026_migration.sql antes de instalar el plugin.', 'error');
        admin_redirect('index.php?module=config-plugins');
    }
    opg_hlw_install_resources();
}

function opg_halloween2026_activate()
{
    global $db;
    opg_hlw_install_resources();
    $table = opg_hlw_table('config');
    $db->write_query("UPDATE `{$table}` SET config_json=JSON_SET(COALESCE(config_json,JSON_OBJECT()),
        '$.store_enabled',false) WHERE id=1 AND JSON_CONTAINS_PATH(COALESCE(config_json,JSON_OBJECT()),'one','$.store_enabled')=0");
}

function opg_halloween2026_deactivate()
{
    // Datos y recursos se conservan para el archivo historico.
}

function opg_halloween2026_uninstall()
{
    // Deliberadamente no destructivo: el historial economico nunca se elimina.
}

function opg_hlw_template_definitions()
{
    return array(
        'op_halloween2026' => 'op_halloween2026.html',
        'staff_halloween2026' => 'staff_halloween2026.html',
    );
}

function opg_hlw_template_set_sid()
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

function opg_hlw_install_resources()
{
    global $db;
    $sids = array(-2);
    $themeSid = opg_hlw_template_set_sid();
    if ($themeSid > 0) {
        $sids[] = $themeSid;
    }
    foreach (opg_hlw_template_definitions() as $title => $file) {
        $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/' . $file;
        if (!is_file($path)) {
            continue;
        }
        $content = rtrim((string)file_get_contents($path));
        foreach ($sids as $sid) {
            $titleSql = $db->escape_string($title);
            $query = $db->simple_select('templates', 'tid', "title='{$titleSql}' AND sid='" . (int)$sid . "'", array('limit' => 1));
            $tid = (int)$db->fetch_field($query, 'tid');
            $data = array(
                'template' => $db->escape_string($content),
                'version' => '1800',
                'status' => '',
                'dateline' => TIME_NOW,
            );
            if ($tid > 0) {
                $db->update_query('templates', $data, "tid='{$tid}'");
            } else {
                $data['title'] = $titleSql;
                $data['sid'] = (int)$sid;
                $db->insert_query('templates', $data);
            }
        }
    }

    $name = 'op_halloween2026.css';
    $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets/' . $name;
    if (!is_file($path)) {
        return;
    }
    $content = (string)file_get_contents($path);
    $nameSql = $db->escape_string($name);
    $query = $db->simple_select('themestylesheets', 'sid', "tid='" . OPG_HLW_THEME_TID . "' AND name='{$nameSql}'", array('limit' => 1));
    $sid = (int)$db->fetch_field($query, 'sid');
    $data = array(
        'stylesheet' => $db->escape_string($content),
        'attachedto' => '',
        'cachefile' => $nameSql,
        'lastmodified' => TIME_NOW,
    );
    if ($sid > 0) {
        $db->update_query('themestylesheets', $data, "sid='{$sid}'");
    } else {
        $data['name'] = $nameSql;
        $data['tid'] = OPG_HLW_THEME_TID;
        $db->insert_query('themestylesheets', $data);
    }
    require_once MYBB_ROOT . 'admin/inc/functions_themes.php';
    cache_stylesheet(OPG_HLW_THEME_TID, $name, $content);
    update_theme_stylesheet_list(OPG_HLW_THEME_TID, false, true);
}

function opg_hlw_hook_post(&$handler)
{
    $pid = (int)($handler->return_values['pid'] ?? 0);
    if ($pid > 0) {
        opg_hlw_reconcile_post($pid, 'insert');
    }
}

function opg_hlw_normalize_ids($ids)
{
    if (!is_array($ids)) {
        $ids = array($ids);
    }
    return array_values(array_unique(array_filter(array_map('intval', $ids))));
}

function opg_hlw_hook_posts($pids)
{
    foreach (opg_hlw_normalize_ids($pids) as $pid) {
        opg_hlw_reconcile_post($pid, 'approval');
    }
}

function opg_hlw_hook_threads($tids)
{
    foreach (opg_hlw_normalize_ids($tids) as $tid) {
        opg_hlw_reconcile_thread($tid, 'thread_approval');
    }
}

function opg_hlw_hook_reverse_posts($pids)
{
    foreach (opg_hlw_normalize_ids($pids) as $pid) {
        opg_hlw_reverse_post($pid, 'post_hidden');
    }
}

function opg_hlw_hook_reverse_post($pid)
{
    opg_hlw_reverse_post((int)$pid, 'post_deleted');
}

function opg_hlw_hook_reverse_threads($tids)
{
    global $db;
    foreach (opg_hlw_normalize_ids($tids) as $tid) {
        $query = $db->simple_select('op_halloween2026_publicaciones', 'pid', "tid='{$tid}'");
        while ($pid = $db->fetch_field($query, 'pid')) {
            opg_hlw_reverse_post((int)$pid, 'thread_hidden');
        }
    }
}

function opg_hlw_hook_reverse_thread($tid)
{
    opg_hlw_hook_reverse_threads(array($tid));
}

function opg_hlw_hook_move($arguments)
{
    if (!is_array($arguments)) {
        return;
    }
    $tids = array();
    foreach (array('tid', 'mergetid', 'destination_tid') as $key) {
        if (!empty($arguments[$key])) {
            $tids[] = (int)$arguments[$key];
        }
    }
    if (!empty($arguments['tids'])) {
        $tids = array_merge($tids, (array)$arguments['tids']);
    }
    foreach (opg_hlw_normalize_ids($tids) as $tid) {
        opg_hlw_reconcile_thread($tid, 'thread_moved');
    }
}
