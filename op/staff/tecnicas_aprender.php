<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * Staff: añadir o remover técnicas para aprender de una ficha.
 * Endurecido en la issue #30: clave CSRF (verify_post_check), entradas escapadas y staff tomado de
 * la sesión. La lógica vive en inc/plugins/lib/tecnicas_aprender_staff.php (probada en
 * tests/tecnicas/aprender_staff_seguridad.php).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_aprender.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . "inc/plugins/lib/posesiones_avisos.php";
require_once MYBB_ROOT . "inc/plugins/lib/tecnicas_aprender_staff.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$user_fid = trim($mybb->get_input('fid'));
$user_accion = $mybb->get_input('accion');

$reload_js = "<script>window.location.href = window.location.pathname;</script>";
$es_staff = is_mod($uid) || is_staff($uid);

// Logica de añadir o remover técnicas para aprender de una ficha como Staff
if ($es_staff && $mybb->request_method == 'post') {
    $entrada = opg_tec_aprender_entrada($_POST);
    if ($entrada !== null) {
        // Sin clave CSRF valida, verify_post_check muestra el error de MyBB y termina sin escribir.
        verify_post_check($mybb->get_input('my_post_key'));
        $res = opg_tec_aprender_procesar($entrada, true, $uid, $db);
        $f_var = $res['f_var'];
        $log = $res['log'];
        // Para el aviso al dueño (issue #21): solo lo que la escritura realmente cambio.
        $avisar_ids = $res['avisar_ids'];
        $avisar_huellas = $res['avisar_huellas'];
        $accion = $entrada['accion'];

        // Un solo aviso con todo el lote; si falla, no afecta al cambio (nunca lanza).
        if (!empty($f_var)) {
            opg_pos_avisar('tecnica', (int) $f_var['fid'], opg_pos_accion($accion), $avisar_ids, $avisar_huellas, $uid, 'por_aprender');
        }

        // La plantilla muestra el log dentro de alert(`...`): se escapa para ese literal.
        $log_var = opg_tec_aprender_js_literal($log);
        $reload_script = $reload_js;
    }
}

if ($es_staff) {
    if ($user_fid != '') {
        $query_ficha = $db->query("
            SELECT * FROM mybb_op_fichas WHERE fid='" . $db->escape_string($user_fid) . "'
        ");
        while ($f = $db->fetch_array($query_ficha)) {
            $f_var = $f;
            $ficha = $f_var;
        }
    }

    $fid = htmlspecialchars($user_fid, ENT_QUOTES, 'UTF-8');
    $accion = htmlspecialchars($user_accion, ENT_QUOTES, 'UTF-8');
    eval("\$page = \"".$templates->get("staff_tecnicas_aprender")."\";");
    // La plantilla no trae la clave CSRF: se añade aqui a sus formularios POST, sin tocar la plantilla.
    $page = opg_tec_aprender_inyectar_post_key($page, generate_post_check());
    output_page($page);
} else {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
}
