<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticiones_usuario.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$user_id = $mybb->get_input('user_id');

$reload_js = "<script>window.location.href = window.location.pathname;</script>";

$peticiones_li = "";

$url_staff = "";

if ($user_id != "" && is_staff($uid)) {
    $uid = $user_id;
}

if ($user_id == "messi" && is_staff($uid)) {
    $uid = 'messi';
}

// BEGIN-PURE (la prueba tests/peticiones/escape.php carga este bloque sin MyBB)
function op_peticion_url_segura($url) {
    $url = (string)$url;
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '';
    }
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://[^\s]+$#i', $url)) {
        return $url;
    }
    // Ruta interna: empieza por una sola barra (no "//", que seria otro dominio)
    if ($url[0] === '/' && substr($url, 0, 2) !== '//' && strpos($url, '\\') === false) {
        return $url;
    }
    return '';
}

function op_peticion_li($categoria2, $resumen, $descripcion, $url, $enviado, $fecha) {
    $esc = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $url_ok = op_peticion_url_segura($url);
    if ($url_ok !== '') {
        $enlace = "<a target='_blank' rel='noopener noreferrer' href='" . $esc($url_ok) . "'>" . $esc($url_ok) . "</a>";
    } else {
        $enlace = $esc($url);
    }
    return "<li><strong>Categoría</strong>: " . $esc($categoria2)
        . " <br> <strong>Resumen</strong>: " . $esc($resumen)
        . " <br> <strong>Descripción</strong>: " . nl2br($esc($descripcion))
        . " <br> <strong>URL</strong>: " . $enlace
        . "<br> <strong>Fecha</strong>: " . $esc($enviado) . " - " . $esc($fecha) . " <br></li><br>";
}
// END-PURE

function print_peticion($uid) {
    global $db;

    if ($uid == 'messi') {
        $query_peticion = $db->query("
            SELECT * FROM mybb_op_peticiones
            ORDER BY enviado DESC
            LIMIT 100;
        ");
    } else {
        $query_peticion = $db->query("
            SELECT * FROM mybb_op_peticiones
            WHERE uid='$uid'
            ORDER BY enviado DESC
            LIMIT 100;
        ");
    }



    $peticiones_li = "";
    while ($q = $db->fetch_array($query_peticion)) {
        $pid = $q['id'];
        $categoria = $q['categoria'];
        $categoria2 = "";
        $resumen = $q['resumen'];
        $descripcion = $q['descripcion'];
        $url = $q['url'];
        $nombre = $q['nombre'];
        $u_uid = $q['uid'];

        $enviado = $q['enviado'];
        $categoria2 = "";
        $givenDate = new DateTime($enviado);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($givenDate);
        $fecha = "Hace " . $interval->days . " días.";

        if ($categoria == 'ficha') { $categoria2 = "Ajustes de Ficha y Recursos"; }
        if ($categoria == 'tema') { $categoria2 = "Petición de Narración"; }
        if ($categoria == 'combate') { $categoria2 = "Moderación de Combate"; }
        if ($categoria == 'tecnica') { $categoria2 = "Técnicas, Akumas y Estilos"; }
        if ($categoria == 'programacion') { $categoria2 = "Errores de Programación"; }

        
        $peticiones_li .= op_peticion_li($categoria2, $resumen, $descripcion, $url, $enviado, $fecha);

    }

    return "$peticiones_li <br>";
}


if ($uid != "0") {
    $peticiones_li .= print_peticion($uid);

    eval("\$page = \"".$templates->get("staff_peticiones_mod")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}