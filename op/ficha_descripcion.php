<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ficha_descripcion.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;

/* For POST Request after submit */
$uid = $mybb->get_input('uid'); 
$accion = $mybb->get_input('accion'); 

$is_editado = $accion == 'editado';

$fid = $mybb->user['uid'];
$username = $mybb->user['username'];

$apodo = addslashes($_POST["apodo"]);
$apariencia = addslashes($_POST["apariencia"]);
$personalidad = addslashes($_POST["personalidad"]);
$biografia = addslashes($_POST["biografia"]);
$extra = addslashes($_POST["extra"]);
$frase = addslashes($_POST["frase"]);
$fisico_de_pj = addslashes($_POST["fisico_de_pj"]);
$banner = addslashes($_POST["banner"]);

$nombre = addslashes($_POST["nombre"]);
$edad = $_POST["edad"];
$altura = $_POST["altura"];
$sexo = $_POST["sexo"];
$peso = $_POST["peso"];
$faccion = $_POST["faccion"];
$raza = $_POST["raza"];
$orientacion = addslashes($_POST["orientacion"]);
$fisico_de_pj = addslashes($_POST["fisico_de_pj"]);
$notas = addslashes($_POST["notas"]);
$rasgos_positivos = addslashes($_POST["rasgos_positivos"]);
$rasgos_negativos = addslashes($_POST["rasgos_negativos"]);

$fuerza = $_POST["fuerza"];
$fuerza_pasiva = $_POST["fuerza_pasiva"];
$resistencia = $_POST["resistencia"];
$resistencia_pasiva = $_POST["resistencia_pasiva"];
$destreza = $_POST["destreza"];
$destreza_pasiva = $_POST["destreza_pasiva"];
$precision = $_POST["precision"];
$precision_pasiva = $_POST["precision_pasiva"];
$agilidad = $_POST["agilidad"];
$agilidad_pasiva = $_POST["agilidad_pasiva"];
$reflejos = $_POST["reflejos"];
$reflejos_pasiva = $_POST["reflejos_pasiva"];
$inteligencia = $_POST["inteligencia"];
$inteligencia_pasiva = $_POST["inteligencia_pasiva"];
$voluntad = $_POST["voluntad"];
$voluntad_pasiva = $_POST["voluntad_pasiva"];
$control_haki = $_POST["control_haki"];
$control_haki_pasiva = $_POST["control_haki_pasiva"];
$control_akuma = $_POST["control_akuma"];
$control_akuma_pasiva = $_POST["control_akuma_pasiva"];

$belica1 = $_POST["belica1"];
$belica2 = $_POST["belica2"];
$oficio1 = $_POST["oficio1"];
$oficio2 = $_POST["oficio2"];

$ficha = null;

$query_ficha = $db->query("
    SELECT * FROM mybb_op_fichas WHERE fid='$uid'
");
    
while ($f = $db->fetch_array($query_ficha)) {
    $aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
    $ficha_existe = true;
    $ficha = $f;
}

$has_post_data = $apariencia && $personalidad && $biografia && $fisico_de_pj && ($uid == $fid || is_mod($fid));

if ($has_post_data && $is_editado) {

    if ($uid == $fid) {
        $db->query(" 
            INSERT INTO `mybb_op_audit_descripcion` (`fid`, `apariencia`, `personalidad`, `biografia`, `extra`, `frase`, `apodo`, `fisico_de_pj`) VALUES 
            ('$uid', '$apariencia', '$personalidad', '$biografia', '$extra', '$frase', '$apodo', '$fisico_de_pj');
        ");
    }

    $db->query(" 
        UPDATE `mybb_op_fichas` SET `apariencia`='$apariencia',`personalidad`='$personalidad',`biografia`='$biografia',`extra`='$extra',`frase`='$frase',`apodo`='$apodo',`fisico_de_pj`='$fisico_de_pj',`banner`='$banner',`notas`='$notas' WHERE `fid`='$uid';
    ");

    $log = "Cambios en ficha para usuario $uid: ";

    if ($nombre && $nombre != $ficha['nombre']) { $db->query(" UPDATE `mybb_op_fichas` SET `nombre`='$nombre' WHERE `fid`='$uid'; "); $log .= "Nombre: $nombre - "; }
    if ($edad && $edad != $ficha['edad']) { $db->query(" UPDATE `mybb_op_fichas` SET `edad`='$edad' WHERE `fid`='$uid'; "); $log .= "Edad: $edad - "; }
    if ($altura && $altura != $ficha['altura']) { $db->query(" UPDATE `mybb_op_fichas` SET `altura`='$altura' WHERE `fid`='$uid'; "); $log .= "Altura: $altura - "; }
    if ($sexo && $sexo != $ficha['sexo']) { $db->query(" UPDATE `mybb_op_fichas` SET `sexo`='$sexo' WHERE `fid`='$uid'; "); $log .= "Sexo: $sexo - "; }
    if ($peso && $peso != $ficha['peso']) { $db->query(" UPDATE `mybb_op_fichas` SET `peso`='$peso' WHERE `fid`='$uid'; "); $log .= "Peso: $peso - "; }
    if ($faccion  && $faccion != $ficha['faccion']) { $db->query(" UPDATE `mybb_op_fichas` SET `faccion`='$faccion' WHERE `fid`='$uid'; "); $log .= "Faccion: $faccion - "; }
    if ($raza && $raza != $ficha['raza']) { $db->query(" UPDATE `mybb_op_fichas` SET `raza`='$raza' WHERE `fid`='$uid'; "); $log .= "Raza: $raza - "; }

    if ($orientacion && $orientacion != $ficha['orientacion']) { $db->query(" UPDATE `mybb_op_fichas` SET `orientacion`='$orientacion' WHERE `fid`='$uid'; "); $log .= "Orientacion: $nombre - "; }
    if ($fisico_de_pj && $fisico_de_pj != $ficha['fisico_de_pj']) { $db->query(" UPDATE `mybb_op_fichas` SET `fisico_de_pj`='$fisico_de_pj' WHERE `fid`='$uid'; "); $log .= "Fisico de PJ: $nombre - "; }
    if ($rasgos_positivos && $rasgos_positivos != $ficha['rasgos_positivos']) { $db->query(" UPDATE `mybb_op_fichas` SET `rasgos_positivos`='$rasgos_positivos' WHERE `fid`='$uid'; "); $log .= "Rasgos Positivos: $rasgos_positivos - "; }
    if ($rasgos_negativos && $rasgos_negativos != $ficha['rasgos_negativos']) { $db->query(" UPDATE `mybb_op_fichas` SET `rasgos_negativos`='$rasgos_negativos' WHERE `fid`='$uid'; "); $log .= "Rasgos Negativos: $rasgos_negativos - "; }
    
    if ($belica1 && $belica1 != $ficha['belica1']) { $db->query(" UPDATE `mybb_op_fichas` SET `belica1`='$belica1' WHERE `fid`='$uid'; "); $log .= "Belica1: $nombre - "; }
    if ($belica2 && $belica2 != $ficha['belica2']) { $db->query(" UPDATE `mybb_op_fichas` SET `belica2`='$belica2' WHERE `fid`='$uid'; "); $log .= "Belica2: $nombre - "; }
    if ($oficio1 && $oficio1 != $ficha['oficio1']) { $db->query(" UPDATE `mybb_op_fichas` SET `oficio1`='$oficio1' WHERE `fid`='$uid'; "); $log .= "Oficio1: $nombre - "; }
    if ($oficio2 && $oficio2 != $ficha['oficio2']) { $db->query(" UPDATE `mybb_op_fichas` SET `oficio2`='$oficio2' WHERE `fid`='$uid'; "); $log .= "Oficio2: $nombre - "; }

    if ($fuerza && $fuerza != $ficha['fuerza']) { $db->query(" UPDATE `mybb_op_fichas` SET `fuerza`='$fuerza' WHERE `fid`='$uid'; "); $log .= "fuerza: $fuerza - "; }
    if ($resistencia && $resistencia != $ficha['resistencia']) { $db->query(" UPDATE `mybb_op_fichas` SET `resistencia`='$resistencia' WHERE `fid`='$uid'; "); $log .= "resistencia: $resistencia - "; }
    if ($destreza && $destreza != $ficha['destreza']) { $db->query(" UPDATE `mybb_op_fichas` SET `destreza`='$destreza' WHERE `fid`='$uid'; "); $log .= "destreza: $destreza - "; }
    if ($precision && $precision != $ficha['precision']) { $db->query(" UPDATE `mybb_op_fichas` SET `precision`='$precision' WHERE `fid`='$uid'; "); $log .= "precision: $precision - "; }
    if ($agilidad && $agilidad != $ficha['agilidad']) { $db->query(" UPDATE `mybb_op_fichas` SET `agilidad`='$agilidad' WHERE `fid`='$uid'; "); $log .= "agilidad: $agilidad - "; }
    if ($reflejos && $reflejos != $ficha['reflejos']) { $db->query(" UPDATE `mybb_op_fichas` SET `reflejos`='$reflejos' WHERE `fid`='$uid'; "); $log .= "reflejos: $reflejos - "; }
    if ($inteligencia && $inteligencia != $ficha['inteligencia']) { $db->query(" UPDATE `mybb_op_fichas` SET `inteligencia`='$inteligencia' WHERE `fid`='$uid'; "); $log .= "inteligencia: $inteligencia - "; }
    if ($voluntad && $voluntad != $ficha['voluntad']) { $db->query(" UPDATE `mybb_op_fichas` SET `voluntad`='$voluntad' WHERE `fid`='$uid'; "); $log .= "voluntad: $voluntad - "; }
    if ($control_akuma && $control_akuma != $ficha['control_akuma']) { $db->query(" UPDATE `mybb_op_fichas` SET `control_akuma`='$control_akuma' WHERE `fid`='$uid'; "); $log .= "control_akuma: $control_akuma - "; }
    if ($control_haki && $control_haki != $ficha['control_haki']) { $db->query(" UPDATE `mybb_op_fichas` SET `control_haki`='$control_haki' WHERE `fid`='$uid'; "); $log .= "control_haki: $control_haki - "; }

    if ($fuerza_pasiva && $fuerza_pasiva != $ficha['fuerza_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='$fuerza_pasiva' WHERE `fid`='$uid'; "); $log .= "fuerza_pasiva: $fuerza_pasiva - "; }
    if ($resistencia_pasiva && $resistencia_pasiva != $ficha['resistencia_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `resistencia_pasiva`='$resistencia_pasiva' WHERE `fid`='$uid'; "); $log .= "resistencia_pasiva: $resistencia_pasiva - "; }
    if ($destreza_pasiva && $destreza_pasiva != $ficha['destreza_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `destreza_pasiva`='$destreza_pasiva' WHERE `fid`='$uid'; "); $log .= "destreza_pasiva: $destreza_pasiva - "; }
    if ($precision_pasiva && $precision_pasiva != $ficha['precision_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `precision_pasiva`='$precision_pasiva' WHERE `fid`='$uid'; "); $log .= "precision_pasiva: $precision_pasiva - "; }
    if ($agilidad_pasiva && $agilidad_pasiva != $ficha['agilidad_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='$agilidad_pasiva' WHERE `fid`='$uid'; "); $log .= "agilidad_pasiva: $agilidad_pasiva - "; }

    if ($reflejos_pasiva && $reflejos_pasiva != $ficha['reflejos_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `reflejos_pasiva`='$reflejos_pasiva' WHERE `fid`='$uid'; "); $log .= "reflejos_pasiva: $reflejos_pasiva - "; }
    if ($inteligencia_pasiva && $inteligencia_pasiva != $ficha['inteligencia_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `inteligencia_pasiva`='$inteligencia_pasiva' WHERE `fid`='$uid'; "); $log .= "inteligencia_pasiva: $inteligencia_pasiva - "; }
    if ($voluntad_pasiva && $voluntad_pasiva != $ficha['voluntad_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`='$voluntad_pasiva' WHERE `fid`='$uid'; "); $log .= "voluntad_pasiva: $voluntad_pasiva - "; }
    if ($control_akuma_pasiva && $control_akuma_pasiva_pasiva != $ficha['control_akuma_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `control_akuma_pasiva`='$control_akuma_pasiva' WHERE `fid`='$uid'; "); $log .= "control_akuma_pasiva: $control_akuma_pasiva - "; }
    if ($control_haki_pasiva && $control_haki_pasiva != $ficha['control_haki_pasiva']) { $db->query(" UPDATE `mybb_op_fichas` SET `control_haki_pasiva`='$control_haki_pasiva' WHERE `fid`='$uid'; "); $log .= "control_haki_pasiva: $control_haki_pasiva - "; }

    // if (($fuerza && $nombre != $ficha['nombre']) || $resistencia || $destreza || $precision || $agilidad || 
    //     $reflejos || $inteligencia || $voluntad || $control_akuma || $control_haki) {
    //     $db->query(" 
    //         UPDATE `mybb_op_fichas` SET `fuerza`='$fuerza',`resistencia`='$resistencia',`destreza`='$destreza',
    //             `precision`='$precision',`agilidad`='$agilidad',`reflejos`='$reflejos',`inteligencia`='$inteligencia',
    //             `voluntad`='$voluntad',`control_akuma`='$control_akuma',`control_haki`='$control_haki' WHERE `fid`='$uid';
    //     ");
    // }

    // if ($fuerza_pasiva || $resistencia_pasiva || $destreza_pasiva || $precision_pasiva || $agilidad_pasiva || 
    //     $reflejos_pasiva || $inteligencia_pasiva || $voluntad_pasiva || $control_akuma_pasiva || $control_haki_pasiva) {
    //     $db->query(" 
    //     UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='$fuerza_pasiva',`resistencia_pasiva`='$resistencia_pasiva',`destreza_pasiva`='$destreza_pasiva',`precision_pasiva`='$precision_pasiva',`agilidad_pasiva`='$agilidad_pasiva',
    //         `reflejos_pasiva`='$reflejos_pasiva',`inteligencia_pasiva`='$inteligencia_pasiva',`voluntad_pasiva`='$voluntad_pasiva',`control_akuma_pasiva`='$control_akuma_pasiva',`control_haki_pasiva`='$control_haki_pasiva' WHERE `fid`='$uid';
    //     ");
    // }

    if (is_mod($fid)) {
        $db->query(" 
            INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('', '$username', '', '$log');
        ");
    }

    $mensaje_redireccion = "¡La ficha ha sido edita exitosamente!";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
} else if ($is_editado) {
    $mensaje_redireccion = "La ficha no pudo ser editada por alguna razón. Intenta editar otra vez. Si esto sigue ocurriendo, avisar al Staff por Discord.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

/* Page */ 
if (!$is_editado) {
    $ficha_existe = false;
    $aprobada = false;
    
    $query_ficha = $db->query("
        SELECT * FROM mybb_op_fichas WHERE fid='$uid'
    ");
    
    while ($f = $db->fetch_array($query_ficha)) {
        $aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
        $ficha_existe = true;
    }
    
    if ($ficha_existe == true && ((($fid == $uid)) || (is_mod($fid) && $ficha_existe == true))) {
        $ficha = null;
    
        $query_fichas = $db->query("
            SELECT * FROM mybb_op_fichas WHERE fid='$uid'
        ");
    
        while ($f = $db->fetch_array($query_fichas)) {
            $ficha = $f;
        }
    
        eval("\$page = \"".$templates->get("op_ficha_editar_descripcion")."\";");
        output_page($page);
    } else {
        $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
    }
}
