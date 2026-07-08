<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'oficios.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;

$entrenar = $_POST["entrenar"];
$cancelar = $_POST["cancelar"];
$culminar = $_POST["culminar"];
$oficioNumber = $_POST["oficio"];

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$uid = $mybb->user['uid'];
$tecnica_en_curso = false;
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;
$efecto_coste = 1;
$time_now = time();
$oficio1 = '';
$oficio2 = '';
$puntos_oficio = 0;

$has_sin_oficio = false; // D024
$has_estudioso = false; // V035
$has_polivalente = false; // V036
$has_erudito = false; // V028
$has_trabajador = false; 

$has_sin_oficio_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='D024'; "); 
$has_estudioso_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V035'; "); 
$has_polivalente_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V036'; "); 
$has_erudito_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V028'; "); 
$has_trabajador_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V052'; "); 

while ($q = $db->fetch_array($has_sin_oficio_query)) { $has_sin_oficio = true; }
while ($q = $db->fetch_array($has_estudioso_query)) { $has_estudioso = true; }
while ($q = $db->fetch_array($has_polivalente_query)) { $has_polivalente = true; }
while ($q = $db->fetch_array($has_erudito_query)) { $has_erudito = true; }
while ($q = $db->fetch_array($has_trabajador_query)) { $has_trabajador = true; }

$reload_js = "<script>window.location.href = window.location.href;</script>";

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($f = $db->fetch_array($query_ficha)) {
    $ficha = $f;
    $ficha_aprobada = $f['aprobada_por'] != 'sin_aprobar';
    $ficha_existe = true;
    $puntos_oficio = $f['puntos_oficio'];
    $nivel = $ficha['nivel'];
}


if ($has_sin_oficio) {
    $mensaje_redireccion = "Aquellos que poseen el defecto 'Sin Oficio' no tienen la capacidad de ganar puntos de oficio. Haber estudiao.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$duracion_horas = 24.0;
$puntos_oficio_recompensa = 100 + (5 * intval($nivel));

if ($ficha['raza'] == 'Kobito') { $puntos_oficio_recompensa = $puntos_oficio_recompensa * 1.1; }
if ($has_polivalente) { $puntos_oficio_recompensa = $puntos_oficio_recompensa + (5 * intval($nivel)); }
if ($has_erudito) { $puntos_oficio_recompensa = $puntos_oficio_recompensa + (5 * intval($nivel)); }
if ($has_trabajador) { $puntos_oficio_recompensa = $puntos_oficio_recompensa * 1.5; }

$duracion = $duracion_horas * 3600; // en segundos

if ($g_entrenamiento_en_curso || $g_entrenamiento_completo) {
    echo "<script>alert('¡Vaya! Parece que ya tienes un entrenamiento activo, así que tendrás que esperar a terminarlo antes de ejercer un oficio. ¡GANBARE!'); window.location.href = './../index.php';</script>";
    exit;
}

if ($cancelar) {
    $db->query("
        DELETE FROM mybb_op_oficios_usuarios WHERE uid='$uid'
    ");
    eval('$reload_script = $reload_js;');
}

if ($entrenar) {
    $now = time();
    $chk_entreno = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    $chk_oficio  = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_oficios_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    if ($chk_entreno['n'] > 0 || $chk_oficio['n'] > 0) {
        $mensaje_redireccion = "Ya tienes un entrenamiento o lección de oficio activo. Espera a que termine antes de iniciar otro.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        exit;
    }
    $timestamp_end = $now + floatval($duracion);
    $db->query("
        INSERT INTO `mybb_op_oficios_usuarios` (`uid`,`nombre`,`oficio`,`timestamp_end`, `duracion`, `experiencia`) VALUES ('$uid','oficio','1','$timestamp_end', '$duracion', '$puntos_oficio_recompensa');
    ");
    eval('$reload_script = $reload_js;');
}

if ($culminar) {

    $entreno = null;

    $query_entreno_usuario = $db->query("
        SELECT * FROM mybb_op_oficios_usuarios WHERE uid='$uid'
    ");

    while ($q = $db->fetch_array($query_entreno_usuario)) {
        $entreno = $q;
    }

    $nombre = $ficha['nombre'];
    $experienciaDeEntreno = $entreno['experiencia'];
    $old_exp = $ficha['puntos_oficio'];
    $new_exp = floatval($old_exp) + (floatval($experienciaDeEntreno));

    // $db->query(" 
    //     UPDATE `mybb_op_fichas` SET `puntos_oficio`='$new_exp' WHERE `fid`='$uid';
    // ");

    log_audit_currency($uid, $username, $uid, '[Entrenamiento][Puntos oficio]', 'puntos_oficio', $new_exp);

    $db->query(" 
        INSERT INTO `mybb_op_audit_oficios` (`fid`, `nombre`, `oficio`, `experiencia`, `progreso`) VALUES 
        ('$uid', '$nombre', 'oficio', '$experienciaDeEntreno', '$old_exp->$new_exp');
    ");

    $log = "Lección finalizada. \nPuntos de oficio: $old_exp->$new_exp\n";
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
    $db->query("DELETE FROM mybb_op_oficios_usuarios WHERE uid='$uid'");
}

if ($ficha_existe == true && $ficha_aprobada == true) {
    
    $query_entreno_usuario2 = $db->query("
        SELECT * FROM mybb_op_oficios_usuarios WHERE uid='$uid'
    ");
    $oficio_en_curso = false;
    $oficio_completo = false;
    $tiempo_left = 0;

    while ($m = $db->fetch_array($query_entreno_usuario2)) {

        $efecto = 1;
        $time_now = time();

        $experienciaActual = $ficha['puntos_oficio'];
        $codigo_usuario = get_obj_from_query($db->query("
            SELECT * FROM mybb_op_codigos_usuarios WHERE uid='$uid' AND expiracion > $time_now
        "));
        if ($codigo_usuario) {
            $codigo_admin = select_one_query_with_id('mybb_op_codigos_admin', 'codigo', $codigo_usuario['codigo']);

            $categoria = $codigo_admin['categoria'];

            if ($categoria == 'entrenamientoX2') {
                $efecto = 2;
            } else if ($categoria == 'entrenamientoX3') {
                $efecto = 3;
            } else if ($categoria == 'entrenamientoX1.2') {
                $efecto = 1.2;
            } else if ($categoria == 'entrenamientoX1.5') {
                $efecto = 1.5;
            }      
        }
        $recompensa = $m['experiencia'];
        $oficio_duracion = intval($m['duracion']); // <--- esto se saca de la base de
        $extra_time = $oficio_duracion - ($oficio_duracion * (1 / $efecto)); 
        $timestamp_end = intval($m['timestamp_end']);
        $tiempo_left = (($timestamp_end) - $extra_time) * 1000; // needed for template

        if (time() > ($timestamp_end - $extra_time)) {
            $oficio_completo = true;
        } else {
            $oficio_en_curso = true;
        }
    
    }       

    if ($oficio_en_curso) {
        eval("\$page = \"".$templates->get("op_oficios_en_curso")."\";");
        output_page($page);
    } else {     
        if ($oficio_completo) {
            eval("\$page = \"".$templates->get("op_oficios_completo")."\";");
        } else {            
            eval("\$page = \"".$templates->get("op_oficios")."\";");
        }
        output_page($page);
    }

} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

