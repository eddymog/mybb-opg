<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'creacion.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;

$uid = $mybb->user['uid'];
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;
$time_now = time();
$accion = $mybb->get_input("accion"); 
$reload_js = "<script>window.location.href = window.location.href;</script>";

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($f = $db->fetch_array($query_ficha)) { 
    $ficha = $f;
    $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
    $ficha_existe = true;
}

function darObjeto($objeto_id) {
    global $db, $uid;
    $cantidadActual = '0';
    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objeto_id'");
    while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }

    if ($has_objeto) {
        $cantidadNueva = intval($cantidadActual) + 1;
        $db->query(" 
            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$objeto_id' AND uid='$uid'
        ");
    } else {
        $db->query(" 
            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('$objeto_id', '$uid', '1');
        ");
    }
}

$nikasUsuario = intval($ficha['nika']);
$puntosOficioUsuario = intval($ficha['puntos_oficio']);

// Única fuente de verdad para los costes de creación: sólo estos 4 tickets consolidados
// se pueden craftear a partir de ahora (ya no hay tiers T1-T10/T1-T5 nuevos). Los costes
// viven exclusivamente aquí, en el backend; el frontend sólo muestra el texto que le
// pasamos, nunca valida ni decide por su cuenta.
$creacion_costos_nuevos = array(
    'TTUN101' => array('nikas' => 25, 'dias' => 20, 'puntos_oficio' => 0,    'nombre_ticket' => 'Ticket de Técnica Única'),
    'TMJT101' => array('nikas' => 15, 'dias' => 10, 'puntos_oficio' => 0,    'nombre_ticket' => 'Ticket de Mejora de Técnica'),
    'RTO101'  => array('nikas' => 25, 'dias' => 20, 'puntos_oficio' => 1000, 'nombre_ticket' => 'Manual de Crafteo'),
    'NTC101'  => array('nikas' => 15, 'dias' => 10, 'puntos_oficio' => 500,  'nombre_ticket' => 'Notas de Crafteo'),
);

if ($accion == 'cancelar') {
    $hasCreacion = false;
    $query_creacion_usuario = $db->query(" SELECT * FROM mybb_op_creacion_usuarios WHERE uid='$uid' ");
    while ($q = $db->fetch_array($query_creacion_usuario)) { $creacion = $q; $hasCreacion = true; }
    
    if ($hasCreacion) {
        $ticket = $creacion['ticket'];
        $info = isset($creacion_costos_nuevos[$ticket]) ? $creacion_costos_nuevos[$ticket] : null;

        if ($info === null) {
            // Ticket de un tier antiguo ya retirado: no hay coste que reembolsar, sólo
            // se libera el slot de creación para no dejar al usuario atascado.
            $db->query(" DELETE FROM mybb_op_creacion_usuarios WHERE uid='$uid' ");
            echo("<script>alert('Creación cancelada.');window.location.href = 'https://onepiecegaiden.com/op/creacion.php';</script>");
            return;
        }

        $nikas         = $info['nikas'];
        $puntos_oficio = $info['puntos_oficio'];
        $nombre_ticket = $info['nombre_ticket'];

        $nikasNuevo = $nikasUsuario + $nikas;
        $puntosOficioNuevo = $puntosOficioUsuario + $puntos_oficio;

        if ($puntos_oficio == 0) {
            $db->query(" UPDATE `mybb_op_fichas` SET `nika`='$nikasNuevo' WHERE `fid`='$uid'; ");
            $log = "Creación de $nombre_ticket cancelado :( ...\nTienes ahora $nikasNuevo nikas. ($nikasUsuario + $nikas)";
        } else {
            $db->query(" UPDATE `mybb_op_fichas` SET `nika`='$nikasNuevo',`puntos_oficio`='$puntosOficioNuevo' WHERE `fid`='$uid'; ");
            $log = "Creación de $nombre_ticket cancelada :( ...\nTienes ahora $nikasNuevo nikas ($nikasUsuario + $nikas) y $puntosOficioNuevo puntos de oficio ($puntosOficioUsuario + $puntos_oficio).";
        }

        $db->query(" DELETE FROM mybb_op_creacion_usuarios WHERE uid='$uid' ");

        echo("<script>alert(`$log`); window.location.href = 'https://onepiecegaiden.com/op/creacion.php';</script>");
    }
}

if ($accion == 'crear') {
    $ticket = $mybb->get_input("ticket");

    // Sólo se puede empezar una creación de los 4 tickets consolidados; nada de tiers antiguos.
    if (!isset($creacion_costos_nuevos[$ticket])) {
        echo("<script>alert('Ticket no válido.');window.location.href = 'https://onepiecegaiden.com/op/creacion.php';</script>");
        return;
    }

    $info          = $creacion_costos_nuevos[$ticket];
    $nikas         = $info['nikas'];
    $dias          = $info['dias'];
    $puntos_oficio = $info['puntos_oficio'];
    $nombre_ticket = $info['nombre_ticket'];

    if ($nikasUsuario >= $nikas && $puntosOficioUsuario >= $puntos_oficio) {
        $duracion = $dias * 3600 * 24;
        $timestamp_end = time() + intval($duracion);
        $nikasNuevo = $nikasUsuario - $nikas;
        $puntosOficioNuevo = $puntosOficioUsuario - $puntos_oficio;
        $db->query(" INSERT INTO `mybb_op_creacion_usuarios` (`uid`, `nombre_ticket`, `ticket`, `timestamp_end`, `duracion`, `nikas_costo`) VALUES ('$uid','$nombre_ticket', '$ticket','$timestamp_end','$duracion','$nikas'); ");

        if ($puntos_oficio == 0) {
            $db->query(" UPDATE `mybb_op_fichas` SET `nika`='$nikasNuevo' WHERE `fid`='$uid'; ");
            $log = "¡Creación en proceso. Se procesará el $nombre_ticket ($ticket)\nTienes ahora $nikasNuevo nikas. ($nikasUsuario - $nikas)";
        } else {
            $db->query(" UPDATE `mybb_op_fichas` SET `nika`='$nikasNuevo',`puntos_oficio`='$puntosOficioNuevo' WHERE `fid`='$uid'; ");
            $log = "Receta en proceso. Se procesará la $nombre_ticket ($ticket)!\nTienes ahora $nikasNuevo nikas ($nikasUsuario - $nikas) y $puntosOficioNuevo puntos de oficio ($puntosOficioUsuario - $puntos_oficio).";
        }

        $db->query(" INSERT INTO `mybb_op_audit_creacion` (`uid`, `nombre`, `log`) VALUES ('$uid', '$nombre', '$log'); ");
        echo("<script>alert(`$log`);window.location.href = 'https://onepiecegaiden.com/op/creacion.php';</script>");
    } else {
        echo("<script>alert('No tienes suficientes nikas o puntos de oficio para crear este ticket.');window.location.href = 'https://onepiecegaiden.com/op/creacion.php';</script>");
    }
}

if ($accion == 'reclamar') {
    $hasCreacion = false;
    $creacion = null;
    $query_creacion_usuario = $db->query(" SELECT * FROM mybb_op_creacion_usuarios WHERE uid='$uid' ");
    while ($q = $db->fetch_array($query_creacion_usuario)) { $creacion = $q; $hasCreacion = true; }

    if ($hasCreacion && intval(time()) > intval($creacion['timestamp_end'])) {
        $ticket = $creacion['ticket'];
        $nombre_ticket = $creacion['nombre_ticket'];

        $db->query("DELETE FROM mybb_op_creacion_usuarios WHERE uid='$uid'");
        darObjeto($ticket);
    
        $log = "¡$nombre_ticket ($ticket) reclamado, felicidades!";
        echo("<script>alert('$log');window.location.href = 'https://onepiecegaiden.com/op/creacion.php';</script>");
        
    }
}

if ($ficha_existe == true && $ficha_aprobada == true) {

    // Imagen de cada ticket: se coge del propio objeto en mybb_op_objetos, igual que
    // el resto del juego (imagen_avatar si existe, si no imagen a tamaño completo).
    $creacion_imagenes = array();
    $ids_esc = "'" . implode("','", array_map(array($db, 'escape_string'), array_keys($creacion_costos_nuevos))) . "'";
    $q_img = $db->query("SELECT objeto_id, imagen, imagen_avatar FROM mybb_op_objetos WHERE objeto_id IN ($ids_esc)");
    while ($row_img = $db->fetch_array($q_img)) {
        $creacion_imagenes[$row_img['objeto_id']] = !empty($row_img['imagen_avatar']) ? $row_img['imagen_avatar'] : $row_img['imagen'];
    }

    // Texto de los botones de creación, construido en el backend a partir de la misma
    // tabla de costes que valida accion=crear (fuente única de verdad). El frontend sólo
    // pinta este HTML; no lleva su propia copia de los números.
    $g_creacion_botones = '';
    foreach ($creacion_costos_nuevos as $t_id => $t_info) {
        $costo_texto = $t_info['dias'] . ' días | ' . $t_info['nikas'] . ' Nikas';
        if ($t_info['puntos_oficio'] > 0) {
            $costo_texto .= ' | ' . $t_info['puntos_oficio'] . ' Puntos de Oficio';
        }
        $nombre_safe = htmlspecialchars($t_info['nombre_ticket'], ENT_QUOTES);
        $costo_safe  = htmlspecialchars($costo_texto, ENT_QUOTES);

        $imagen_html = '';
        if (!empty($creacion_imagenes[$t_id])) {
            $imagen_safe = htmlspecialchars($creacion_imagenes[$t_id], ENT_QUOTES);
            $imagen_html = '<img src="' . $imagen_safe . '" class="creacion-imagen" alt="' . $nombre_safe . '" />';
        }

        $g_creacion_botones .= '<div class="creacion-card" onclick="abrirModalCreacion(\'' . $t_id . '\', \'' . $nombre_safe . '\', \'' . $costo_safe . '\')">
            ' . $imagen_html . '
            <div class="creacion-nombre-hover">' . $nombre_safe . '</div>
            <div class="creacion-crear-bar">Crear</div>
        </div>';
    }

    $query_creacion_usuario2 = $db->query(" SELECT * FROM mybb_op_creacion_usuarios WHERE uid='$uid' ");

    $en_curso = false;
    $completo = false;
    $tiempo_left = 0;

    while ($m = $db->fetch_array($query_creacion_usuario2)) {
        $timestamp_end = intval($m['timestamp_end']);
        $tiempo_left = (($timestamp_end)) * 1000; 
        $duracion = $m['duracion'];
        $ticket = $m['ticket'];
        $nombre_ticket = $m['nombre_ticket'];

        if (time() > ($timestamp_end)) {
            $completo = true;
        } else {
            $en_curso = true;
        }
    }

    if ($en_curso) {
        eval("\$page = \"".$templates->get("op_creacion_en_curso")."\";");
        output_page($page);
    } else {     
        if ($completo) {
            eval("\$page = \"".$templates->get("op_creacion_completo")."\";");
        } else {            
            eval("\$page = \"".$templates->get("op_creacion")."\";");
        }
        output_page($page);
    }

} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

