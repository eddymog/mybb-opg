<?php
/**
 * Staff - Crear / modificar islas
 *
 * Datos de mybb_op_islas (mapa del mundo). Permite mod/staff/narrador, a
 * diferencia de la mayoría de /op/staff/ que es solo mod/staff.
 *
 * Reescrito por 3 problemas reales que tenía:
 * 1. Inyección SQL: `isla_id` (de la URL) se usaba crudo en el WHERE, sin
 *    escapar ni siquiera con addslashes(), y esa consulta corría ANTES del
 *    chequeo de permisos — cualquiera, sin sesión, podía inyectar SQL con
 *    solo visitar la URL. El resto de los campos (nombre, gobierno...) solo
 *    pasaban por addslashes(), no por un escape real de SQL.
 * 2. CSRF: el <form> no llevaba my_post_key y el PHP nunca lo pedía.
 * 3. El campo "ID de Isla" (nuevo_isla_id) para renombrar una isla existente
 *    no hacía nada: el UPDATE nunca tocaba la columna isla_id, así que
 *    cambiarlo no tenía ningún efecto — quedaba silenciosamente ignorado.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'islas_modificar.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid) && !is_narra($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('ISLAS_MSG_COOKIE', 'islas_msg');
$ISLAS_CAMPOS = array('gobierno', 'faccion', 'tamano', 'comercio', 'habitantes', 'zonas');

// ── Typeahead: busca por isla_id o nombre, de solo lectura ───────────────────
if ($mybb->get_input('buscar_isla', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_isla', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $resultados = array();
    $query = $db->query("SELECT isla_id, nombre FROM `mybb_op_islas` WHERE isla_id LIKE '%{$like}%' OR nombre LIKE '%{$like}%' ORDER BY nombre LIMIT 20");
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['isla_id'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

function islas_url($id = '')
{
    return 'islas_modificar.php' . ($id !== '' ? '?isla_id=' . rawurlencode($id) : '');
}

// ── POST: crear/modificar ────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $isla_id_old = trim($mybb->get_input('isla_id_old', MyBB::INPUT_STRING));
    $isla_id_new = trim($mybb->get_input('isla_id', MyBB::INPUT_STRING));
    $nombre      = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));

    $error = '';
    if ($isla_id_new === '') { $error = 'El isla ID es obligatorio.'; }
    elseif ($nombre === '') { $error = 'El nombre es obligatorio.'; }

    if ($error === '') {
        $campos = array('nombre' => $nombre);
        foreach ($ISLAS_CAMPOS as $campo) {
            $campos[$campo] = trim($mybb->get_input($campo, MyBB::INPUT_STRING));
        }

        $existe = (bool) $db->fetch_field($db->query("SELECT isla_id FROM `mybb_op_islas` WHERE isla_id='" . $db->escape_string($isla_id_new) . "'"), 'isla_id');
        $es_rename = $isla_id_old !== '' && $isla_id_old !== $isla_id_new
            && $db->fetch_field($db->query("SELECT isla_id FROM `mybb_op_islas` WHERE isla_id='" . $db->escape_string($isla_id_old) . "'"), 'isla_id');

        $set = array();
        foreach ($campos as $col => $val) {
            $set[] = "`{$col}`='" . $db->escape_string($val) . "'";
        }
        $set[] = "`isla_id`='" . $db->escape_string($isla_id_new) . "'";

        if ($existe || $es_rename) {
            $id_where = $es_rename ? $isla_id_old : $isla_id_new;
            $db->query("UPDATE `mybb_op_islas` SET " . implode(', ', $set) . " WHERE `isla_id`='" . $db->escape_string($id_where) . "'");
        } else {
            $cols = array('isla_id');
            $vals = array("'" . $db->escape_string($isla_id_new) . "'");
            foreach ($campos as $col => $val) {
                $cols[] = $col;
                $vals[] = "'" . $db->escape_string($val) . "'";
            }
            $db->query("INSERT INTO `mybb_op_islas` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ")");
        }

        $log_texto = "Isla {$isla_id_new} (" . ($existe || $es_rename ? 'modificada' : 'creada') . " por {$mybb->user['username']}): nombre={$nombre}.";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . (int) $uid . "', '" . $db->escape_string($mybb->user['username']) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(ISLAS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Isla guardada.',
    ))), 15, true);
    header('Location: ' . islas_url($error !== '' ? $isla_id_old : $isla_id_new));
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$isla_id = trim($mybb->get_input('isla_id', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[ISLAS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[ISLAS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(ISLAS_MSG_COOKIE);
}

$isla = null;
if ($isla_id !== '') {
    $isla = $db->fetch_array($db->query("SELECT * FROM `mybb_op_islas` WHERE isla_id='" . $db->escape_string($isla_id) . "'"));
}

$ISLAS_CAMPOS_FORM = array_merge(array('nombre'), $ISLAS_CAMPOS);
$isla_esc = array();
foreach ($ISLAS_CAMPOS_FORM as $campo) {
    $isla_esc[$campo] = htmlspecialchars($isla ? (string) $isla[$campo] : '', ENT_QUOTES, 'UTF-8');
}
$isla_id_esc = htmlspecialchars($isla_id, ENT_QUOTES, 'UTF-8');
$isla_link = $isla_id !== '' ? htmlspecialchars($mybb->settings['bburl'] . '/op/isla.php?isla_id=' . $isla_id, ENT_QUOTES, 'UTF-8') : '';

eval("\$page = \"".$templates->get("staff_islas_modificar")."\";");
output_page($page);
