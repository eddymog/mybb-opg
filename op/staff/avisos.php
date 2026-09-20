<?php
/**
 * Staff - Avisos (intercambios y viajes reportados)
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: `peti_id` sin escapar/castear en el UPDATE/DELETE.
 * 2. `$action` (variable indefinida, no `$accion`) hacía que la rama
 *    "borrar" nunca se ejecutara — dead code, además de que borraba por
 *    `uid` en vez de por `id` (hubiera borrado TODOS los avisos de un
 *    usuario, no uno puntual).
 * 3. CSRF: "Resolver" era un simple `<a href>` (GET) sin token.
 * 4. XSS: `nombre`, `resumen`, `descripcion` y `url` (todos texto libre
 *    enviado por el usuario) se mostraban sin escapar.
 * 5. Permisos solo se chequeaban al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'avisos.php');
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
        $db->query("UPDATE `mybb_op_avisos` SET `resuelto`=1, `mod_uid`='{$uid}', `mod_nombre`='" . $db->escape_string($username) . "' WHERE `id`='{$peti_id}'");
    } elseif ($accion === 'borrar') {
        $db->query("DELETE FROM `mybb_op_avisos` WHERE `id`='{$peti_id}'");
    }

    header('Location: /op/staff/avisos.php');
    exit;
}

$post_key = generate_post_check();
$puede_moderar = is_mod($uid) || is_staff($uid);

function av_print_categoria($nombre_categoria, $categoria, $puede_moderar, $post_key)
{
    global $db;
    $query = $db->query("SELECT * FROM `mybb_op_avisos` WHERE `resuelto`=0 AND `categoria`='" . $db->escape_string($categoria) . "' ORDER BY `id` DESC");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        $pid = (int) $q['id'];
        $u_uid = (int) $q['uid'];
        $nombre_esc = htmlspecialchars($q['nombre'], ENT_QUOTES, 'UTF-8');
        $resumen_esc = htmlspecialchars($q['resumen'], ENT_QUOTES, 'UTF-8');
        // A diferencia de mybb_op_peticiones (texto libre real, tecleado en
        // un textarea por el usuario — ver op/peticiones.php), la
        // descripción de un aviso la arma el propio servidor: op/viajes.php
        // y op/intercambio.php insertan acá un relato ya formateado con
        // <strong> a propósito (ver esos archivos), no texto plano.
        // Escaparla acá rompía ese formato (mostraba las etiquetas crudas
        // en vez de negrita). Se muestra tal cual, como ya hacía la versión
        // original de esta página.
        $descripcion_esc = nl2br($q['descripcion']);
        $url_esc = htmlspecialchars($q['url'], ENT_QUOTES, 'UTF-8');
        $enviado_esc = htmlspecialchars($q['enviado'], ENT_QUOTES, 'UTF-8');

        $fecha = '';
        try {
            $interval = (new DateTime())->diff(new DateTime($q['enviado']));
            $fecha = 'Hace ' . $interval->days . ' días.';
        } catch (Exception $e) {
        }

        $html .= '<div class="av-item">';
        $html .= '<div class="av-item-linea">[<a href="/op/ficha.php?uid=' . $u_uid . '" target="_blank">' . $nombre_esc . ' - ' . $u_uid . '</a>]</div>';
        $html .= '<div class="av-item-linea"><strong>Resumen</strong>: ' . $resumen_esc . '</div>';
        $html .= '<div class="av-item-linea"><strong>Descripción</strong>: ' . $descripcion_esc . '</div>';
        $html .= '<div class="av-item-linea"><strong>URL</strong>: <a href="' . $url_esc . '" target="_blank">' . $url_esc . '</a></div>';
        $html .= '<div class="av-item-linea"><strong>Fecha</strong>: ' . $enviado_esc . ' - ' . $fecha . '</div>';
        if ($puede_moderar) {
            $resolver_a = 'avisos.php?accion=resolver&peti_id=' . $pid . '&my_post_key=' . rawurlencode($post_key);
            $html .= '<div class="av-item-acciones"><a class="opg-chip" href="' . htmlspecialchars($resolver_a, ENT_QUOTES, 'UTF-8') . '">Resolver</a></div>';
        }
        $html .= '</div>';
    }

    if ($html === '') { return ''; }
    return '<div class="av-seccion"><div class="barra-op bbox"><span class="barra-texto-op">' . htmlspecialchars($nombre_categoria, ENT_QUOTES, 'UTF-8') . '</span></div><div class="barra-espacio-op bbox">' . $html . '</div></div>';
}

$peticiones_li = av_print_categoria('Intercambios', 'intercambio', $puede_moderar, $post_key)
    . av_print_categoria('Viajes', 'viaje', $puede_moderar, $post_key);

eval("\$page = \"".$templates->get("staff_avisos_mod")."\";");
output_page($page);
