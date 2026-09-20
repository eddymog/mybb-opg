<?php
/**
 * Staff - Peticiones de errores de programación
 *
 * Antes compartía template (staff_peticiones_mod) con
 * op/staff/peticiones_admin.php, pero ese template tiene hardcodeados los
 * links/JS de "Resets de Build", "Peticiones resueltas" y el
 * asignar-mod/notas apuntando a peticiones_admin.php — en esta página
 * jamás funcionaban bien (apuntaban a la página equivocada) y esta página
 * tampoco genera los botones de asignar-mod/notas, así que ese JS quedaba
 * sin nada que hacer. Ahora tiene su propio template dedicado.
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: `peti_id` sin escapar/castear.
 * 2. `$action` (variable indefinida, no `$accion`) hacía que la rama
 *    "borrar" nunca se ejecutara — dead code, además de que borraba por
 *    `uid` en vez de por `id`.
 * 3. CSRF: "Resolver" era un simple `<a href>` (GET) sin token.
 * 4. XSS: `nombre`, `resumen`, `descripcion` y `url` (texto libre del
 *    usuario) se mostraban sin escapar.
 * 5. Permisos solo se chequeaban al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticiones_bugs.php');
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

$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
$peti_id = (int) $mybb->get_input('peti_id', MyBB::INPUT_INT);

if ($accion !== '' && $peti_id > 0 && (is_mod($uid) || is_staff($uid))) {
    verify_post_check($mybb->get_input('my_post_key'));

    if ($accion === 'resolver') {
        $db->query("UPDATE `mybb_op_peticiones` SET `resuelto`=1, `mod_uid`='{$uid}', `mod_nombre`='" . $db->escape_string($username) . "' WHERE `id`='{$peti_id}'");
    } elseif ($accion === 'borrar') {
        $db->query("DELETE FROM `mybb_op_peticiones` WHERE `id`='{$peti_id}'");
    }

    header('Location: /op/staff/peticiones_bugs.php');
    exit;
}

$post_key = generate_post_check();
$puede_moderar = is_mod($uid) || is_staff($uid);

function pb_print_categoria($nombre_categoria, $categoria, $puede_moderar, $post_key)
{
    global $db;
    $query = $db->query("SELECT * FROM `mybb_op_peticiones` WHERE `resuelto`=0 AND `categoria`='" . $db->escape_string($categoria) . "' ORDER BY `id` DESC");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        $pid = (int) $q['id'];
        $u_uid = (int) $q['uid'];
        $nombre_esc = htmlspecialchars($q['nombre'], ENT_QUOTES, 'UTF-8');
        $resumen_esc = htmlspecialchars($q['resumen'], ENT_QUOTES, 'UTF-8');
        $descripcion_esc = nl2br(htmlspecialchars($q['descripcion'], ENT_QUOTES, 'UTF-8'));
        $url_esc = htmlspecialchars($q['url'], ENT_QUOTES, 'UTF-8');
        $enviado_esc = htmlspecialchars($q['enviado'], ENT_QUOTES, 'UTF-8');

        $fecha = '';
        try {
            $interval = (new DateTime())->diff(new DateTime($q['enviado']));
            $fecha = 'Hace ' . $interval->days . ' días.';
        } catch (Exception $e) {
        }

        $html .= '<div class="pb-item">';
        $html .= '<div class="pb-item-linea">[<a href="/op/ficha.php?uid=' . $u_uid . '" target="_blank">' . $nombre_esc . ' - ' . $u_uid . '</a>]</div>';
        $html .= '<div class="pb-item-linea"><strong>Resumen</strong>: ' . $resumen_esc . '</div>';
        $html .= '<div class="pb-item-linea"><strong>Descripción</strong>: ' . $descripcion_esc . '</div>';
        $html .= '<div class="pb-item-linea"><strong>URL</strong>: <a href="' . $url_esc . '" target="_blank">' . $url_esc . '</a></div>';
        $html .= '<div class="pb-item-linea"><strong>Fecha</strong>: ' . $enviado_esc . ' - ' . $fecha . '</div>';
        if ($puede_moderar) {
            $resolver_a = 'peticiones_bugs.php?accion=resolver&peti_id=' . $pid . '&my_post_key=' . rawurlencode($post_key);
            $html .= '<div class="pb-item-acciones"><a class="opg-chip" href="' . htmlspecialchars($resolver_a, ENT_QUOTES, 'UTF-8') . '">Resolver</a></div>';
        }
        $html .= '</div>';
    }

    if ($html === '') { return ''; }
    return '<div class="pb-seccion"><div class="barra-op bbox"><span class="barra-texto-op">' . htmlspecialchars($nombre_categoria, ENT_QUOTES, 'UTF-8') . '</span></div><div class="barra-espacio-op bbox">' . $html . '</div></div>';
}

$peticiones_li = pb_print_categoria('Errores de Programación', 'programacion', $puede_moderar, $post_key);

eval("\$page = \"".$templates->get("staff_peticiones_bugs")."\";");
output_page($page);
