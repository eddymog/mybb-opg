<?php
/**
 * Staff - Técnicas en creación (cola de moderación del foro 8)
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: `tid` (ID de tema) se interpolaba sin escapar/castear
 *    en el UPDATE de "Abandonar" — un valor como `1 OR 1=1` movía TODOS
 *    los temas de foro en vez de uno solo.
 * 2. CSRF: "Abandonar" era un simple `<a href>` (GET) sin token.
 * 3. XSS: `subject` (título del tema) y `username` se mostraban sin
 *    escapar — el título de un tema es texto libre del usuario.
 * 4. Permisos solo se chequeaban al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_creacion.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid) && !is_user($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
$tid_input = (int) $mybb->get_input('tid', MyBB::INPUT_INT);

if ($accion === 'abandonar' && $tid_input > 0) {
    verify_post_check($mybb->get_input('my_post_key'));
    $db->query("UPDATE `mybb_threads` SET `fid`=85, `closed`=1 WHERE `tid`='{$tid_input}'");
    header('Location: /op/staff/tecnicas_creacion.php');
    exit;
}

$post_key = generate_post_check();

function tc_item($q)
{
    $tid = (int) $q['tid'];
    $subject_esc = htmlspecialchars($q['subject'], ENT_QUOTES, 'UTF-8');
    $username_esc = htmlspecialchars($q['username'], ENT_QUOTES, 'UTF-8');
    $uid_hilo = (int) $q['uid'];
    $lastpost = date('d/m/Y', (int) $q['lastpost']);
    $days_ago = floor((time() - (int) $q['lastpost']) / 86400);

    $html = '<div class="tc-item">';
    $html .= '<div class="tc-item-linea"><strong>Usuario</strong>: <a href="/op/ficha.php?uid=' . $uid_hilo . '" target="_blank">' . $username_esc . '</a></div>';
    $html .= '<div class="tc-item-linea"><strong>Título</strong>: <a href="/showthread.php?tid=' . $tid . '" target="_blank">' . $subject_esc . '</a></div>';
    $html .= '<div class="tc-item-linea"><strong>Fecha</strong>: ' . $lastpost . ' &mdash; hace ' . $days_ago . ' días.</div>';
    return array($html, $tid);
}

function print_tecnicas_sin_contestar()
{
    global $db;
    $query = $db->query("
        SELECT t.* FROM `mybb_threads` AS t
        INNER JOIN `mybb_forums` AS f ON t.fid = f.fid
        AND f.fid = 8 AND t.closed = 0
        AND t.uid = t.lastposteruid
        AND t.visible = 1
        AND t.replies = 0
        AND t.tid != 97
        ORDER BY t.lastpost ASC
    ");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        list($item_html) = tc_item($q);
        $html .= $item_html . '</div>';
    }
    if ($html === '') { return ''; }
    return '<div class="tc-seccion"><div class="barra-op bbox"><span class="barra-texto-op">Sin respuesta de moderación</span></div><div class="barra-espacio-op bbox">' . $html . '</div></div>';
}

function print_tecnicas_a_moderar()
{
    global $db;
    $query = $db->query("
        SELECT t.* FROM `mybb_threads` AS t
        INNER JOIN `mybb_forums` AS f ON t.fid = f.fid
        AND f.fid = 8 AND t.closed = 0
        AND t.uid = t.lastposteruid
        AND t.visible = 1
        AND t.replies > 0
        AND t.tid != 97
        ORDER BY t.lastpost ASC
    ");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        list($item_html) = tc_item($q);
        $html .= $item_html . '</div>';
    }
    if ($html === '') { return ''; }
    return '<div class="tc-seccion"><div class="barra-op bbox"><span class="barra-texto-op">El usuario respondió, a la espera de moderación</span></div><div class="barra-espacio-op bbox">' . $html . '</div></div>';
}

function print_tecnicas_no_moderar($post_key)
{
    global $db;
    $query = $db->query("
        SELECT t.* FROM `mybb_threads` AS t
        INNER JOIN `mybb_forums` AS f ON t.fid = f.fid
        AND f.fid = 8 AND t.closed = 0
        AND t.uid != t.lastposteruid
        AND t.visible = 1
        AND t.tid != 97
        ORDER BY t.lastpost DESC
    ");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        list($item_html, $tid) = tc_item($q);
        $abandonar_a = 'tecnicas_creacion.php?accion=abandonar&tid=' . $tid . '&my_post_key=' . rawurlencode($post_key);
        $item_html .= '<div class="tc-item-acciones"><a class="opg-chip" href="' . htmlspecialchars($abandonar_a, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'¿Abandonar este tema? El usuario debe responder, no toca moderar todavía.\');">Abandonar</a></div>';
        $html .= $item_html . '</div>';
    }
    if ($html === '') { return ''; }
    return '<div class="tc-seccion"><div class="barra-op bbox"><span class="barra-texto-op">A la espera del usuario &mdash; no toca moderar</span></div><div class="barra-espacio-op bbox">' . $html . '</div></div>';
}

$peticiones_li = print_tecnicas_sin_contestar() . print_tecnicas_a_moderar() . print_tecnicas_no_moderar($post_key);

eval("\$page = \"".$templates->get("staff_tecnicas_creacion")."\";");
output_page($page);
