<?php
/**
 * Staff - Fichas en cola (aprobar personajes nuevos)
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: `fid` y `faccion` se interpolaban sin escapar en los
 *    UPDATE/DELETE.
 * 2. CSRF: "Aprobar"/"Borrar" eran simples `<a href>` (GET) sin token — un
 *    link o imagen ajena podía hacer que un staff logueado aprobara o
 *    borrara una ficha sin querer. Ahora llevan `my_post_key` en la URL y
 *    se valida con `verify_post_check()`.
 * 3. XSS: el nombre de cuenta se mostraba sin escapar.
 * 4. `faccion` se metía sin `urlencode()` en la URL de "Aprobar" — un
 *    nombre de facción con espacio o `&` rompía el link.
 * 5. Permisos solo se chequeaban al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fichas_en_cola.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid) && !is_user($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

$action = $mybb->get_input('action', MyBB::INPUT_STRING);
$fid = (int) $mybb->get_input('fid', MyBB::INPUT_INT);
$faccion = trim($mybb->get_input('faccion', MyBB::INPUT_STRING));

if ($action !== '' && $fid > 0 && (is_mod($uid) || is_staff($uid))) {
    verify_post_check($mybb->get_input('my_post_key'));

    if ($action === 'aprobar' && $faccion !== '') {
        // El grupo de MyBB asociado a cada facción se gestiona ahora desde
        // Admin CP → Configuración del foro → OPG Facciones (columna
        // "usergroup" de mybb_op_facciones). Si la tabla no existiera por lo
        // que sea, cae al mapeo fijo de siempre para no dejar de funcionar.
        $usergroup = 0;
        if ($db->table_exists('op_facciones')) {
            $usergroup = (int) $db->fetch_field($db->simple_select('op_facciones', 'usergroup', "nombre='" . $db->escape_string($faccion) . "'"), 'usergroup');
        }
        if ($usergroup <= 0) {
            $legacy_usergroups = array(
                'Pirata' => 8, 'Marina' => 9, 'CipherPol' => 11,
                'Revolucionario' => 12, 'Cazadores' => 10, 'Civil' => 13,
            );
            $usergroup = $legacy_usergroups[$faccion] ?? 2;
        }

        $db->query("UPDATE `mybb_op_fichas` SET `aprobada_por`='" . $db->escape_string($username) . "' WHERE `aprobada_por`='sin_aprobar' AND `fid`='{$fid}'");
        $db->query("UPDATE `mybb_users` SET `usergroup`='" . (int) $usergroup . "' WHERE `uid`='{$fid}'");
    } elseif ($action === 'borrar') {
        $db->query("DELETE FROM `mybb_op_fichas` WHERE `aprobada_por`='sin_aprobar' AND `fid`='{$fid}'");
        $db->query("DELETE FROM `mybb_op_fichas_secret` WHERE `fid`='{$fid}'");
        $db->query("DELETE FROM `mybb_op_inventario` WHERE `uid`='{$fid}'");
        $db->query("DELETE FROM `mybb_op_tec_aprendidas` WHERE `uid`='{$fid}'");
        $db->query("DELETE FROM `mybb_op_virtudes_usuarios` WHERE `uid`='{$fid}'");
    }

    header('Location: /op/staff/fichas_en_cola.php');
    exit;
}

$post_key = generate_post_check();
$puede_moderar = is_mod($uid) || is_staff($uid);

$fichas_li = '';
$query_fichas = $db->query("SELECT * FROM `mybb_op_fichas` WHERE `aprobada_por`='sin_aprobar' ORDER BY `fid` DESC");
while ($f = $db->fetch_array($query_fichas)) {
    $fid_fila = (int) $f['fid'];
    $nombre_esc = htmlspecialchars($f['nombre'], ENT_QUOTES, 'UTF-8');
    $faccion_esc = htmlspecialchars($f['faccion'], ENT_QUOTES, 'UTF-8');

    $fichas_li .= '<div class="fc-item">';
    $fichas_li .= '<div class="fc-item-linea"><strong>UID</strong>: <a href="/member.php?action=profile&uid=' . $fid_fila . '" target="_blank">' . $fid_fila . '</a> &nbsp;|&nbsp; <strong>Cuenta</strong>: ' . $nombre_esc . ' &nbsp;|&nbsp; <strong>Facción</strong>: ' . $faccion_esc . '</div>';
    $fichas_li .= '<div class="fc-item-acciones">';
    $fichas_li .= '<a class="opg-chip" href="/op/ficha.php?uid=' . $fid_fila . '" target="_blank">Ver ficha</a>';
    if ($puede_moderar) {
        $aprobar_a = 'fichas_en_cola.php?action=aprobar&fid=' . $fid_fila . '&faccion=' . rawurlencode($f['faccion']) . '&my_post_key=' . rawurlencode($post_key);
        $borrar_a = 'fichas_en_cola.php?action=borrar&fid=' . $fid_fila . '&my_post_key=' . rawurlencode($post_key);
        $fichas_li .= '<a class="opg-chip" href="' . htmlspecialchars($aprobar_a, ENT_QUOTES, 'UTF-8') . '">Aprobar</a>';
        $fichas_li .= '<a class="opg-chip chip-peligro" href="' . htmlspecialchars($borrar_a, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'¿Borrar esta ficha en cola?\');">Borrar</a>';
    }
    $fichas_li .= '</div></div>';
}

eval("\$page = \"".$templates->get("staff_fichas_en_cola")."\";");
output_page($page);
