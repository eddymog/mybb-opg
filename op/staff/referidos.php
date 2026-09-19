<?php
/**
 * Staff - Referidos
 *
 * Registrar/quitar quién trajo a un usuario nuevo (mybb_users.referrer +
 * contador mybb_users.referrals de quien refirió). No hay ninguna recompensa
 * automática ligada a esto en el código — es puramente el registro que el
 * staff usa para entregar premios de referidos a mano (ver
 * images/op/uploads/GuiaReferidos_One_Piece_Gaiden_Foro_Rol.jpg).
 *
 * Reescrito por 2 problemas reales que tenía:
 * 1. Inyección SQL: `fid` (de la URL) y `ficha_id`/`ref_id` (del POST) se
 *    usaban crudos en los WHERE/UPDATE, sin escapar nada — y la búsqueda por
 *    ?fid= corría antes del chequeo de permisos, así que cualquiera sin
 *    sesión podía inyectar SQL con solo visitar la URL.
 * 2. CSRF: el <form> no llevaba my_post_key y el PHP nunca lo pedía — sobre
 *    una acción que suma/resta el contador de referidos de una cuenta.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'referidos.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('REFERIDOS_MSG_COOKIE', 'referidos_msg');
$REFERIDOS_ACCIONES = array('Añadir', 'Remover');

function referidos_url($fid = 0, $accion = '')
{
    $url = 'referidos.php';
    if ($fid > 0) { $url .= '?fid=' . $fid . ($accion !== '' ? '&accion=' . rawurlencode($accion) : ''); }
    return $url;
}

// ── POST: añadir/remover ─────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $ficha_id = (int) $mybb->get_input('ficha_id', MyBB::INPUT_INT);
    $ref_id   = (int) $mybb->get_input('ref_id', MyBB::INPUT_INT);
    $accion   = $mybb->get_input('accion', MyBB::INPUT_STRING);

    $error = '';
    if ($ficha_id <= 0) { $error = 'El User ID es obligatorio.'; }
    elseif ($ref_id <= 0) { $error = 'El Referido ID es obligatorio.'; }
    elseif (!in_array($accion, $REFERIDOS_ACCIONES, true)) { $error = 'Acción inválida.'; }

    if ($error === '') {
        if ($accion === 'Añadir') {
            $db->query("UPDATE `mybb_users` SET `referrer` = {$ficha_id} WHERE uid = {$ref_id}");
            $db->query("UPDATE `mybb_users` SET `referrals` = `referrals` + 1 WHERE uid = {$ficha_id}");
        } else {
            $db->query("UPDATE `mybb_users` SET `referrer` = 0 WHERE uid = {$ref_id}");
            $db->query("UPDATE `mybb_users` SET `referrals` = GREATEST(`referrals` - 1, 0) WHERE uid = {$ficha_id}");
        }

        $log_texto = "[Referidos] {$accion} para ficha UID {$ficha_id}. Referido: {$ref_id}. Por {$username} ({$uid}).";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Referidos', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(REFERIDOS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : ($accion === 'Añadir' ? "Referido {$ref_id} agregado a {$ficha_id}." : "Referido {$ref_id} quitado."),
    ))), 15, true);
    header('Location: ' . referidos_url($ficha_id, $accion));
    exit;
}

// ── GET: formulario + listado ────────────────────────────────────────────────

$user_fid = (int) $mybb->get_input('fid', MyBB::INPUT_INT);
$accion   = $mybb->get_input('accion', MyBB::INPUT_STRING);
if (!in_array($accion, $REFERIDOS_ACCIONES, true)) { $accion = ''; }
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[REFERIDOS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[REFERIDOS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(REFERIDOS_MSG_COOKIE);
}

$ficha = null;
if ($user_fid > 0) {
    $ficha = $db->fetch_array($db->query("SELECT fid, nombre FROM `mybb_op_fichas` WHERE fid = {$user_fid}"));
}
$ficha_nombre_esc = htmlspecialchars($ficha ? $ficha['nombre'] : '', ENT_QUOTES, 'UTF-8');
$accion_esc = htmlspecialchars($accion, ENT_QUOTES, 'UTF-8');

$referidos = '';
if ($user_fid > 0) {
    $query_referrer = $db->query("SELECT uid, username FROM `mybb_users` WHERE referrer = {$user_fid}");
    while ($q = $db->fetch_array($query_referrer)) {
        $r_uid = (int) $q['uid'];
        $referidos .= '<div class="rf-fila"><a href="/op/ficha.php?uid=' . $r_uid . '">#' . $r_uid . '</a> '
            . htmlspecialchars($q['username'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
if ($referidos === '') {
    $referidos = '<p class="opg-vacio">' . ($user_fid > 0 ? 'Todavía no tiene referidos.' : 'Buscar un User ID para ver sus referidos.') . '</p>';
}

eval("\$page = \"".$templates->get("staff_referidos")."\";");
output_page($page);
