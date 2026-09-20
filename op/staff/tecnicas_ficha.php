<?php
/**
 * Staff - Técnicas de la ficha
 *
 * Añade o remueve técnicas aprendidas (mybb_op_tec_aprendidas) de un
 * personaje puntual, a mano, en lote separado por comas.
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: el UID de la ficha, cada técnica ID y la razón se
 *    interpolaban sin escapar en SELECT/INSERT/DELETE.
 * 2. CSRF: el <form> no llevaba my_post_key.
 * 3. XSS: el nombre de la ficha se mostraba sin escapar, y el aviso final se
 *    imprimía dentro de un `alert()` de JavaScript escrito entre comillas
 *    invertidas — una comilla invertida en el nombre del personaje o en el
 *    texto de una técnica rompía el script.
 * 4. No se validaba que el UID buscado corresponda a una ficha real antes de
 *    escribir en mybb_op_tec_aprendidas.
 * 5. La rama "Remover" escribía el log en `mybb_op_audit_consola_tec`
 *    (sin "_mod"), una tabla distinta de la que usan "Añadir" acá mismo y
 *    las dos ramas de op/tecnicas_aprender.php / op/staff/tecnicas_aprender.php
 *    (`mybb_op_audit_consola_tec_mod`) — quedaba un historial partido en dos
 *    tablas sin ningún motivo real. Ahora las dos ramas escriben en la misma.
 * 6. El reload post-guardado perdía el ?fid= (volvía a la pantalla de
 *    búsqueda en vez de quedarse en el personaje recién editado).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_ficha.php');
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

define('TECNICAS_FICHA_MSG_COOKIE', 'tecnicas_ficha_msg');

function tecnicas_ficha_url($fid = '', $accion = '')
{
    if ($fid === '') { return 'tecnicas_ficha.php'; }
    return 'tecnicas_ficha.php?fid=' . rawurlencode($fid) . ($accion !== '' ? '&accion=' . rawurlencode($accion) : '');
}

// ── POST: guardar ─────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $ficha_id = trim($mybb->get_input('ficha_id', MyBB::INPUT_STRING));
    $accion   = trim($mybb->get_input('accion', MyBB::INPUT_STRING));
    $tecnicas = trim($mybb->get_input('tecnicas', MyBB::INPUT_STRING));
    $razon    = trim($mybb->get_input('razon', MyBB::INPUT_STRING));

    $error = '';
    if ($ficha_id === '') { $error = 'Falta el UID del personaje.'; }
    elseif ($accion !== 'Añadir' && $accion !== 'Remover') { $error = 'Acción inválida.'; }
    elseif ($tecnicas === '') { $error = 'Escribe al menos una técnica.'; }
    elseif ($razon === '') { $error = 'La razón es obligatoria.'; }

    $f_var = null;
    if ($error === '') {
        $f_var = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($ficha_id) . "'"));
        if (!$f_var) { $error = 'Ese UID no tiene ficha.'; }
    }

    if ($error === '') {
        $tecnicas_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', $tecnicas);
        $cambios = array();
        $ficha_id_esc = $db->escape_string($ficha_id);

        foreach ($tecnicas_array as $tec) {
            $clean_tec = trim($tec);
            if ($clean_tec === '') { continue; }
            $clean_tec_esc = $db->escape_string($clean_tec);

            if ($accion === 'Añadir') {
                $db->query("INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES ('{$clean_tec_esc}', '{$ficha_id_esc}')");
            } else { // Remover
                $db->query("DELETE FROM `mybb_op_tec_aprendidas` WHERE tid='{$clean_tec_esc}' AND uid='{$ficha_id_esc}'");
            }
            $cambios[] = "{$accion} técnica ID: {$clean_tec}";
        }

        if ($cambios) {
            $log = "Cambios de técnicas para ficha de UID: {$ficha_id} ({$f_var['nombre']}):\n-- " . implode("\n-- ", $cambios);
            $db->query("INSERT INTO `mybb_op_audit_consola_tec_mod` (`staff`, `razon`, `log`) VALUES "
                . "('{$uid}', '" . $db->escape_string($razon) . "', '" . $db->escape_string($log) . "')");
        }
    }

    my_setcookie(TECNICAS_FICHA_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Técnicas actualizadas.',
    ))), 15, true);
    header('Location: ' . tecnicas_ficha_url($ficha_id, $accion));
    exit;
}

// ── GET: formulario ────────────────────────────────────────────────────────────

$fid = trim($mybb->get_input('fid', MyBB::INPUT_STRING));
$accion = trim($mybb->get_input('accion', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[TECNICAS_FICHA_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[TECNICAS_FICHA_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(TECNICAS_FICHA_MSG_COOKIE);
}

$ficha = null;
if ($fid !== '') {
    $ficha = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($fid) . "'"));
}

$fid_esc = htmlspecialchars($fid, ENT_QUOTES, 'UTF-8');
$accion_esc = htmlspecialchars($accion, ENT_QUOTES, 'UTF-8');
$ficha_nombre_esc = htmlspecialchars($ficha ? $ficha['nombre'] : '', ENT_QUOTES, 'UTF-8');
$ficha_existe = $ficha ? 1 : 0;

eval("\$page = \"".$templates->get("staff_tecnicas_ficha")."\";");
output_page($page);
