<?php
/**
 * Staff - Virtudes de la ficha
 *
 * Añade o remueve virtudes/defectos (mybb_op_virtudes_usuarios) de un
 * personaje puntual, a mano, en lote separado por comas.
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: el UID de la ficha, cada virtud ID y la razón se
 *    interpolaban sin escapar en SELECT/INSERT/DELETE.
 * 2. CSRF: el <form> no llevaba my_post_key.
 * 3. XSS: el nombre de la ficha se mostraba sin escapar, y el aviso final se
 *    imprimía dentro de un `alert()` de JavaScript escrito entre comillas
 *    invertidas — una comilla invertida en el nombre del personaje o en el
 *    texto de una virtud rompía el script.
 * 4. `staff` era un campo de texto libre que el propio staff completaba a
 *    mano (`<input name="staff" required>`) en vez de tomarse de la sesión
 *    — cualquiera podía escribir cualquier nombre ahí, así que el registro
 *    de "quién hizo el cambio" no era confiable. Ahora es siempre el uid de
 *    quien tiene la sesión.
 * 5. No quedaba ningún registro de auditoría: `$log_short` se armaba pero
 *    nunca se insertaba en ninguna tabla. Ahora se guarda en
 *    `mybb_op_audit_consola_mod`, igual que op/staff/objetos_ficha.php.
 * 6. No se validaba que el UID buscado corresponda a una ficha real antes
 *    de escribir en mybb_op_virtudes_usuarios.
 * 7. El reload post-guardado perdía el ?fid= (volvía a la pantalla de
 *    búsqueda en vez de quedarse en el personaje recién editado).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'virtudes_ficha.php');
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

define('VIRTUDES_FICHA_MSG_COOKIE', 'virtudes_ficha_msg');

function virtudes_ficha_url($fid = '', $accion = '')
{
    if ($fid === '') { return 'virtudes_ficha.php'; }
    return 'virtudes_ficha.php?fid=' . rawurlencode($fid) . ($accion !== '' ? '&accion=' . rawurlencode($accion) : '');
}

// ── POST: guardar ─────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $ficha_id = trim($mybb->get_input('ficha_id', MyBB::INPUT_STRING));
    $accion   = trim($mybb->get_input('accion', MyBB::INPUT_STRING));
    $virtudes = trim($mybb->get_input('virtudes', MyBB::INPUT_STRING));
    $razon    = trim($mybb->get_input('razon', MyBB::INPUT_STRING));

    $error = '';
    if ($ficha_id === '') { $error = 'Falta el UID del personaje.'; }
    elseif ($accion !== 'Añadir' && $accion !== 'Remover') { $error = 'Acción inválida.'; }
    elseif ($virtudes === '') { $error = 'Escribe al menos una virtud.'; }
    elseif ($razon === '') { $error = 'La razón es obligatoria.'; }

    $f_var = null;
    if ($error === '') {
        $f_var = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($ficha_id) . "'"));
        if (!$f_var) { $error = 'Ese UID no tiene ficha.'; }
    }

    if ($error === '') {
        $virtudes_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', $virtudes);
        $cambios = array();
        $ficha_id_esc = $db->escape_string($ficha_id);

        foreach ($virtudes_array as $virtud) {
            $clean_virtud = trim($virtud);
            if ($clean_virtud === '') { continue; }
            $clean_virtud_esc = $db->escape_string($clean_virtud);

            if ($accion === 'Añadir') {
                $db->query("INSERT INTO `mybb_op_virtudes_usuarios` (`virtud_id`, `uid`) VALUES ('{$clean_virtud_esc}', '{$ficha_id_esc}')");
            } else { // Remover
                $db->query("DELETE FROM `mybb_op_virtudes_usuarios` WHERE virtud_id='{$clean_virtud_esc}' AND uid='{$ficha_id_esc}'");
            }
            $cambios[] = "{$accion} virtud ID: {$clean_virtud}";
        }

        if ($cambios) {
            $log = "Cambios de virtudes para ficha de UID: {$ficha_id} ({$f_var['nombre']}):\n-- " . implode("\n-- ", $cambios);
            log_audit($uid, $username, '[Virtudes][Ficha]', $db->escape_string($log));
            $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                . "('{$uid}', '" . $db->escape_string($username) . "', '" . $db->escape_string($razon) . "', '" . $db->escape_string($log) . "')");
        }
    }

    my_setcookie(VIRTUDES_FICHA_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Virtudes actualizadas.',
    ))), 15, true);
    header('Location: ' . virtudes_ficha_url($ficha_id, $accion));
    exit;
}

// ── GET: formulario ────────────────────────────────────────────────────────────

$fid = trim($mybb->get_input('fid', MyBB::INPUT_STRING));
$accion = trim($mybb->get_input('accion', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[VIRTUDES_FICHA_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[VIRTUDES_FICHA_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(VIRTUDES_FICHA_MSG_COOKIE);
}

$ficha = null;
if ($fid !== '') {
    $ficha = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($fid) . "'"));
}

$fid_esc = htmlspecialchars($fid, ENT_QUOTES, 'UTF-8');
$accion_esc = htmlspecialchars($accion, ENT_QUOTES, 'UTF-8');
$ficha_nombre_esc = htmlspecialchars($ficha ? $ficha['nombre'] : '', ENT_QUOTES, 'UTF-8');
$ficha_existe = $ficha ? 1 : 0;

eval("\$page = \"".$templates->get("staff_virtudes_ficha")."\";");
output_page($page);
