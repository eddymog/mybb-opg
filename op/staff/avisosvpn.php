<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'avisosvpn.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$accion = $mybb->get_input('accion');
$aviso_id = $mybb->get_input('aviso_id');

$reload_js = "<script>window.location.href = window.location.pathname;</script>";

if (
    $uid == 304 || 
    $uid == 850 ||
    $uid == 279 ||
    $uid == 268 ||
    $uid == 23  ||
    $uid == 10  ||
    $uid == 7
   ) {
    $avisos_li = "";

    $query_avisos = $db->query("
        SELECT * FROM mybb_op_avisosvpn
        ORDER BY fechaConexion DESC
    ");

    while ($q = $db->fetch_array($query_avisos)) {
        $id = $q['id'];
        $usuario = $q['usuario'];
        $u_uid = $q['uid'];
        $ip = $q['ip'];
        $fechaConexion = $q['fechaConexion'];

        $givenDate = new DateTime($fechaConexion);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($givenDate);
        $fecha = "Hace " . $interval->days . " días";
        if ($interval->days == 0) {
            if ($interval->h > 0) {
                $fecha = "Hace " . $interval->h . " horas";
            } else {
                $fecha = "Hace " . $interval->i . " minutos";
            }
        }
        
        $avisos_li .= "<li>";
        $avisos_li .= "[<a target='_blank' href='/op/ficha.php?uid=$u_uid'>$usuario - $u_uid</a>] <br>";
        $avisos_li .= "<strong>IP de conexión</strong>: $ip <br>";
        $avisos_li .= "<strong>Fecha de conexión</strong>: $fechaConexion - $fecha <br>";
        
        if (is_staff($uid)) {
            $url_page = "/op/staff/avisosvpn.php";
            $avisos_li .= "<span><a href='$borrar_a'>Borrar</a></span>";
        }
        $avisos_li .= "</li><br>";
    }

    if ($avisos_li == "") {
        $avisos_li = "<p>No hay avisos de VPN registrados.</p>";
    } else {
        $avisos_li = "<h3>Avisos de VPN detectados</h3><ul>" . $avisos_li . "</ul>";
    }

    eval("\$page = \"".$templates->get("op_staff_avisosvpn")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}
