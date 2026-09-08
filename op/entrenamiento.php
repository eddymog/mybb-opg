<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'entrenamiento.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;

$entrenar = $_POST["entrenar"];
$cancelar = $_POST["cancelar"];
$culminar = $_POST["culminar"];
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];

$tecnica_en_curso = false;
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;
$efecto_coste = 1;
$exp_usuario = '0';
$time_now = time();

$reload_js = "<script>window.location.href = window.location.href;</script>";

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$query_usuario = $db->query("SELECT * FROM mybb_users WHERE uid='$uid'");
while ($u = $db->fetch_array($query_usuario)) { $exp_usuario = $u['newpoints']; }

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($f = $db->fetch_array($query_ficha)) { 
    $ficha = $f;
    $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
    $ficha_existe = true;
}

$nivel = intval($ficha['nivel']);
$experiencia = $nivel / 2;
$horas = 24;

$has_intensivo = false; // V042

$has_intensivo_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V042'; "); 
while ($q = $db->fetch_array($has_intensivo_query)) { $has_intensivo = true; }

if ($ficha['raza'] == 'Kobito') { $experiencia = $experiencia * 1.1; }

if ($has_intensivo) {
    $experiencia = $experiencia * 1.25;
}

if ($g_oficio_en_curso || $g_oficio_completo) {
    echo "<script>alert('¡Vaya! Parece que ya tienes un oficio activo, así que tendrás que esperar a terminarlo antes de realizar este entrenamiento. ¡GANBARE!'); window.location.href = './../index.php';</script>";
    exit;
}

if ($cancelar) {
    $db->query("
        DELETE FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
    ");
    eval('$reload_script = $reload_js;');
}

if ($entrenar) {
    $now = time();
    $chk_oficio  = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_oficios_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    $chk_entreno = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    if ($chk_oficio['n'] > 0 || $chk_entreno['n'] > 0) {
        $mensaje_redireccion = "Ya tienes un entrenamiento o lección de oficio activo. Espera a que termine antes de iniciar otro.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        exit;
    }
    $nombre = $ficha['nombre'];
    $duracion = $horas * 3600; // en segundos
    // $duracion = '64000'; // en segundos
    $timestamp_end = $now + intval($duracion);
    $db->query("
        INSERT INTO `mybb_op_entrenamientos_usuarios` (`uid`,`nombre`,`timestamp_end`, `duracion`, `costo_pr`, `recompensa`) VALUES ('$uid','$nombre','$timestamp_end', '$duracion', '0', '$experiencia');
    ");
    eval('$reload_script = $reload_js;');
}

// if ($culminar) { // temporary

//     $entreno = null;

//     $query_entreno_usuario = $db->query("
//         SELECT * FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
//     ");

//     while ($q = $db->fetch_array($query_entreno_usuario)) {
//         $entreno = $q;
//     }

//     $nombre = $ficha['nombre'];
//     $old_exp = $ficha['kuro'];
//     $new_exp = floatval($old_exp) + (floatval($entreno['recompensa']));
//     // $db->query(" 
//     //     UPDATE `mybb_op_fichas` SET `puntos_estadistica`='$new_pe' WHERE `fid`='$uid';
//     // ");
//     $db->query(" 
//         UPDATE `mybb_op_fichas` SET `kuro`='$new_exp' WHERE `fid`='$uid';
//     ");
    
//     $db->query(" 
//         INSERT INTO `mybb_op_audit_entrenamientos` (`fid`, `nombre`, `puntos_estadistica`, `pr`) VALUES 
//         ('$uid', '$nombre', '2', '$old_exp->$new_exp');
//     ");

//     $log = "Entrenamiento finalizado. \nKuros nuevos: $old_exp->$new_exp\n";
//     eval('$log_var = $log;');
//     eval('$reload_script = $reload_js;');
//     $db->query("DELETE FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'");
// }

if ($culminar) {

    $entreno = null;

    $query_entreno_usuario = $db->query("
        SELECT * FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
    ");

    while ($q = $db->fetch_array($query_entreno_usuario)) {
        $entreno = $q;
    }

    $nombre = $ficha['nombre'];
    $old_exp = $exp_usuario;
    $new_exp = floatval($old_exp) + (floatval($entreno['recompensa']));
    // $db->query(" 
    //     UPDATE `mybb_op_fichas` SET `puntos_estadistica`='$new_pe' WHERE `fid`='$uid';
    // ");
    // $db->query(" 
    //     UPDATE `mybb_users` SET `newpoints`='$new_exp' WHERE `uid`='$uid';
    // ");

    log_audit_currency($uid, $username, $uid, '[Entrenamiento][Experiencia]', 'experiencia', $new_exp);
    
    $db->query(" 
        INSERT INTO `mybb_op_audit_entrenamientos` (`fid`, `nombre`, `puntos_estadistica`, `pr`) VALUES 
        ('$uid', '$nombre', '2', '$old_exp->$new_exp');
    ");

    $log = "Entrenamiento finalizado. \nPuntos de experiencia: $old_exp->$new_exp\n";
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
    $db->query("DELETE FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'");
}

if ($ficha_existe == true && $ficha_aprobada == true) {
    
    $query_entreno_usuario = $db->query("
        SELECT * FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
    ");
    $mision_en_curso = false;
    $mision_completa = false;
    $tiempo_left = 0;

    while ($m = $db->fetch_array($query_entreno_usuario)) {

        $efecto = 1;
        $time_now = time();
        $codigo_usuario = get_obj_from_query($db->query("
            SELECT * FROM mybb_op_codigos_usuarios WHERE uid='$uid' AND expiracion > $time_now
        "));

        $recompensa = $m['recompensa'];
        $entrenamiento_duracion = intval($m['duracion']); // <--- esto se saca de la base de
        $extra_time = $entrenamiento_duracion - ($entrenamiento_duracion * (1 / $efecto)); 
        $timestamp_end = intval($m['timestamp_end']);
        $tiempo_left = (($timestamp_end) - $extra_time) * 1000; // needed for template

        if (time() > ($timestamp_end - $extra_time)) {
            $mision_completa = true;
        } else {
            $mision_en_curso = true;
        }
    
    }       

    if ($mision_en_curso) {
        // $recompensa = 10;
        eval("\$page = \"".$templates->get("op_entrenamiento_en_curso")."\";");
        output_page($page);
    } else {     

        if ($mision_completa) {
            eval("\$page = \"".$templates->get("op_entrenamiento_completo")."\";");
        } else {            
            eval("\$page = \"".$templates->get("op_entrenamiento")."\";");
        }
        output_page($page);
    }

} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

