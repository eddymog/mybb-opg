<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'censo.php');
require_once "./../global.php";
require "./../inc/config.php";

global $templates, $mybb, $db;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];

$categoria = addslashes($_POST["categoria"]);
$resumen = addslashes($_POST["resumen"]);
$descripcion = addslashes($_POST["descripcion"]);
$url = addslashes($_POST["url"]);

if ($categoria && $resumen && $uid != '0') {
    $mensaje_redireccion = "¡Tu petición ha sido enviada exitosamente y la estaremos revisando! Muchas gracias crack, nunca cambies.";

    $db->query(" 
        INSERT INTO `mybb_op_peticiones` (`uid`, `nombre`, `categoria`, `resumen`, `descripcion`, `url`) VALUES ('$uid','$username','$categoria','$resumen','$descripcion','$url')
    ");

    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
} else {
    
    if ($uid == '0') {
        $mensaje_redireccion = "Tienes que estar logueado para enviar peticiones administrativas, crack.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
    } else {
        $peticiones_txt = "";
        $pet_counter = 1;
        $query_peticion = $db->query("
            SELECT * FROM mybb_op_peticiones
            WHERE uid='$uid'
            AND resuelto=0
            ORDER BY enviado ASC;
        ");
    
        while ($q = $db->fetch_array($query_peticion)) {
            $resumen = $q['resumen'];
            $descripcion = $q['descripcion'];
            $categoria = $q['categoria'];
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
            if ($categoria == 'reset') { $categoria2 = "Ticket de Reset de Build"; }

            $peticiones_txt .= "<strong>$pet_counter. [$categoria2] <br> Fecha: $enviado - $fecha<br>Resumen:</strong> $resumen <br> <strong>Descripción:</strong> $descripcion<br><br>";
            $pet_counter += 1;
        }
    
        eval("\$page = \"".$templates->get("op_peticiones")."\";");
        output_page($page);
    }


}


// Output the result in days
// echo $interval->days . " days ago";

// op_peticiones
// mybb_op_peticiones