<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fichas_en_cola.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$action = $mybb->get_input('action');
$fid = $mybb->get_input('fid');
$faccion = $mybb->get_input('faccion');

$reload_js = "<script>window.location.href = window.location.pathname;</script>";

if ($action == 'aprobar' && $fid && $faccion) {
    // El grupo de MyBB asociado a cada facción se gestiona ahora desde
    // Admin CP → Configuración del foro → OPG Facciones (columna
    // "usergroup" de mybb_op_facciones). Si la tabla no existiera por lo
    // que sea, cae al mapeo fijo de siempre para no dejar de funcionar.
    $usergroup = 0;
    if ($db->table_exists('op_facciones')) {
        $usergroup = (int)$db->fetch_field($db->simple_select('op_facciones', 'usergroup', "nombre='".$db->escape_string($faccion)."'"), 'usergroup');
    }
    if ($usergroup <= 0) {
        $legacy_usergroups = array(
            'Pirata' => 8, 'Marina' => 9, 'CipherPol' => 11,
            'Revolucionario' => 12, 'Cazadores' => 10, 'Civil' => 13,
        );
        $usergroup = isset($legacy_usergroups[$faccion]) ? $legacy_usergroups[$faccion] : 2;
    }

    $db->query("
        UPDATE `mybb_op_fichas` SET `aprobada_por`='$username' WHERE aprobada_por='sin_aprobar' AND fid='$fid'
    ");
    $db->query(" 
        UPDATE `mybb_users` SET `usergroup`='$usergroup' WHERE uid=$fid;
    ");
    eval('$reload_script = $reload_js;');
} else if ($action == 'borrar' && $fid) {
    $db->query(" DELETE FROM mybb_op_fichas WHERE aprobada_por='sin_aprobar' AND fid='$fid'; ");
    $db->query(" DELETE FROM `mybb_op_fichas_secret` WHERE fid='$fid'; ");
    $db->query(" DELETE FROM `mybb_op_inventario` WHERE uid='$fid'; ");
    $db->query(" DELETE FROM `mybb_op_tec_aprendidas` WHERE uid='$fid'; ");
    $db->query(" DELETE FROM `mybb_op_virtudes_usuarios` WHERE uid='$fid'; ");

    eval('$reload_script = $reload_js;');
}

if (is_mod($uid) || is_staff($uid) || is_user($uid)) { 
    $fichas_li = "";
    $query_fichas = $db->query("
        SELECT * FROM mybb_op_fichas WHERE aprobada_por='sin_aprobar'
    ");
    while ($f = $db->fetch_array($query_fichas)) {
        $fid = $f['fid'];
        $nombre = $f['nombre'];
        $faccion = $f['faccion'];
        $url = "./fichas_en_cola.php";
        $aprobar_a = "$url?action=aprobar&fid=$fid&faccion=$faccion";
        $borrar_a = "$url?action=borrar&fid=$fid";
        $fichas_li .= "<li>";
        $fichas_li .= "UID: <span><a href='/member.php?action=profile&uid=$fid' target='_blank'>$fid</a></span> ||| Cuenta: $nombre ||| ";
        $fichas_li .= "<span><a href='./../ficha.php?uid=$fid' target='_blank'>Link de la ficha</a></span> ||| ";
        if (is_mod($uid) || is_staff($uid)) {
            $fichas_li .= "<span><a href='$aprobar_a' target='_blank'>Aprobar</a></span> ||| ";
            $fichas_li .= "<span><a href='$borrar_a' target='_blank'>Borrar</a></span>";
        }
        $fichas_li .= "</li>";
    }
    eval('$li_fichas = $fichas_li;');
    eval("\$page = \"".$templates->get("staff_fichas_en_cola")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}
