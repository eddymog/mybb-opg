<?php
/**
 * Staff - Objetos de la ficha
 *
 * Añade o remueve objetos del inventario (mybb_op_inventario) de un
 * personaje puntual, a mano, en lote separado por comas.
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: el UID de la ficha, cada objeto_id y la razón se
 *    interpolaban sin escapar en SELECT/INSERT/UPDATE/DELETE.
 * 2. CSRF: el <form> no llevaba my_post_key.
 * 3. XSS: el nombre de la ficha se mostraba sin escapar, y el aviso final se
 *    imprimía dentro de `alert(`{$log_var}`)` con backticks — un backtick o
 *    `${...}` en el nombre del personaje o en el texto de un objeto rompía
 *    el script.
 * 4. No se validaba que el UID buscado corresponda a una ficha real antes de
 *    escribir en mybb_op_inventario — un UID inexistente creaba filas de
 *    inventario huérfanas en silencio.
 * 5. THIS_SCRIPT decía 'ficha_objetos.php'; el archivo real es
 *    'objetos_ficha.php' (no afecta funcionalidad, pero confunde logs).
 * 6. El reload post-guardado perdía el ?fid= (volvía a la pantalla de
 *    búsqueda en vez de quedarse en el personaje recién editado).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'objetos_ficha.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid)) {
    $redireccion = "No tienes permisos para ver esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

define('OBJETOS_FICHA_MSG_COOKIE', 'objetos_ficha_msg');

function objetos_ficha_url($fid = '', $accion = '')
{
    if ($fid === '') { return 'objetos_ficha.php'; }
    return 'objetos_ficha.php?fid=' . rawurlencode($fid) . ($accion !== '' ? '&accion=' . rawurlencode($accion) : '');
}

// ── POST: guardar ─────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $ficha_id = trim($mybb->get_input('ficha_id', MyBB::INPUT_STRING));
    $accion   = trim($mybb->get_input('accion', MyBB::INPUT_STRING));
    $objetos  = strtoupper(trim($mybb->get_input('objetos', MyBB::INPUT_STRING)));
    $razon    = trim($mybb->get_input('razon', MyBB::INPUT_STRING));

    $error = '';
    if ($ficha_id === '') { $error = 'Falta el UID del personaje.'; }
    elseif ($accion !== 'Añadir' && $accion !== 'Remover') { $error = 'Acción inválida.'; }
    elseif ($objetos === '') { $error = 'Escribe al menos un objeto.'; }
    elseif ($razon === '') { $error = 'La razón es obligatoria.'; }

    $f_var = null;
    if ($error === '') {
        $f_var = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($ficha_id) . "'"));
        if (!$f_var) { $error = 'Ese UID no tiene ficha.'; }
    }

    if ($error === '') {
        $objetos_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', $objetos);
        $cambios = array();
        $ficha_id_esc = $db->escape_string($ficha_id);

        foreach ($objetos_array as $obj) {
            $clean_obj = trim($obj);
            if ($clean_obj === '') { continue; }
            $clean_obj_esc = $db->escape_string($clean_obj);

            $actual = $db->fetch_array($db->query("SELECT cantidad FROM `mybb_op_inventario` WHERE uid='{$ficha_id_esc}' AND objeto_id='{$clean_obj_esc}'"));
            $tiene = (bool) $actual;
            $cantidad = $tiene ? (int) $actual['cantidad'] : 0;

            if ($accion === 'Añadir') {
                if ($tiene) {
                    $nueva = $cantidad + 1;
                    $db->query("UPDATE `mybb_op_inventario` SET `cantidad`='{$nueva}' WHERE objeto_id='{$clean_obj_esc}' AND uid='{$ficha_id_esc}'");
                } else {
                    $db->query("INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES ('{$clean_obj_esc}', '{$ficha_id_esc}', '1')");
                }
            } else { // Remover
                if ($tiene) {
                    $nueva = $cantidad - 1;
                    if ($nueva > 0) {
                        $db->query("UPDATE `mybb_op_inventario` SET `cantidad`='{$nueva}' WHERE objeto_id='{$clean_obj_esc}' AND uid='{$ficha_id_esc}'");
                    } else {
                        $db->query("DELETE FROM `mybb_op_inventario` WHERE objeto_id='{$clean_obj_esc}' AND uid='{$ficha_id_esc}'");
                    }
                }
            }
            $cambios[] = "{$accion} objeto ID: {$clean_obj}";
        }

        if ($cambios) {
            $log = "Cambios de objetos para ficha de UID: {$ficha_id} ({$f_var['nombre']}):\n-- " . implode("\n-- ", $cambios);
            log_audit($uid, $username, '[Objetos][Ficha]', $db->escape_string($log));
            $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                . "('{$uid}', '" . $db->escape_string($username) . "', '" . $db->escape_string($razon) . "', '" . $db->escape_string($log) . "')");
        }
    }

    my_setcookie(OBJETOS_FICHA_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Objetos actualizados.',
    ))), 15, true);
    header('Location: ' . objetos_ficha_url($ficha_id, $accion));
    exit;
}

// ── GET: formulario ────────────────────────────────────────────────────────────

$fid = trim($mybb->get_input('fid', MyBB::INPUT_STRING));
$accion = trim($mybb->get_input('accion', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[OBJETOS_FICHA_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[OBJETOS_FICHA_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(OBJETOS_FICHA_MSG_COOKIE);
}

$ficha = null;
if ($fid !== '') {
    $ficha = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($fid) . "'"));
}

$fid_esc = htmlspecialchars($fid, ENT_QUOTES, 'UTF-8');
$accion_esc = htmlspecialchars($accion, ENT_QUOTES, 'UTF-8');
$ficha_nombre_esc = htmlspecialchars($ficha ? $ficha['nombre'] : '', ENT_QUOTES, 'UTF-8');
$ficha_existe = $ficha ? 1 : 0;

eval("\$page = \"".$templates->get("staff_objetos_ficha")."\";");
output_page($page);
