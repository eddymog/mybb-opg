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
define('THIS_SCRIPT', 'ficha_estadisticas.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;

$uid = $mybb->get_input('uid'); 
$accion = $mybb->get_input('accion'); 

$fuerza = $_POST["fuerza"];
$resistencia = $_POST["resistencia"];
$reflejos = $_POST["reflejos"];
$punteria = $_POST["punteria"];
$voluntad = $_POST["voluntad"];
$agilidad = $_POST["agilidad"];
$destreza = $_POST["destreza"];

$vitalidad = $_POST["vitalidad"];
$energia = $_POST["energia"];
$haki = $_POST["haki"];
$puntos_estadistica = $_POST["puntos_estadistica"];

$is_editado = $accion == 'editado';
$has_post_data = (($puntos_estadistica || $puntos_estadistica == '0') && ($uid == $mybb->user['uid'] || $g_is_staff));
 
if ($has_post_data && $is_editado) {
    $ficha = select_one_query_with_id('mybb_op_fichas', 'fid', $uid);

    function sum_stats($fue, $res, $ref, $pun, $vol, $agi, $des) {
        $sum_stats = intval($fue) + intval($res) + intval($ref) + intval($pun) + intval($vol) + intval($agi) + intval($des);
        return $sum_stats;
    }

    $current_stats = sum_stats($ficha['fuerza'], $ficha['resistencia'], $ficha['reflejos'], $ficha['punteria'], $ficha['voluntad'], $ficha['agilidad'], $ficha['destreza']);
    $new_stats = sum_stats($fuerza, $resistencia, $reflejos, $punteria, $voluntad, $agilidad, $destreza);

    if (((intval($ficha['puntos_estadistica']) + $current_stats) != (intval($puntos_estadistica) + $new_stats)) && ((intval($ficha['puntos_estadistica']) + $current_stats + 30) != (intval($puntos_estadistica) + $new_stats))) {
        $mensaje_redireccion = "¡Ey tíol! ¿¡Pero qué haces!? ¡NO PUEDES EDITAR ESO, CHAVAL! ¡Que mal rollo! ¡Más te vale no intentar hacer trampas! ¡Flipo contigo!";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
    } else {
        // $db->query(" 
        //     INSERT INTO `mybb_op_audit_stats` (`fid`, `puntos_estadistica`, `fuerza`, `resistencia`, `reflejos`, `punteria`, `voluntad`, 
        //          `agilidad`,`destreza`) VALUES 
        //     ('$uid', '$puntos_estadistica', '$fuerza', '$resistencia', '$reflejos', '$punteria', '$voluntad', 
        //      '$agilidad', '$destreza');
        // ");
        $db->query(" 
            UPDATE `mybb_op_fichas` SET `vitalidad`='$vitalidad',`energia`='$energia',`haki`='$haki',`puntos_estadistica`='$puntos_estadistica',
            `fuerza`='$fuerza',`resistencia`='$resistencia',`reflejos`='$reflejos',`punteria`='$punteria',`voluntad`='$voluntad',
            `agilidad`='$agilidad',`destreza`='$destreza' WHERE `fid`='$uid';
        ");

        $mensaje_redireccion = "Las estadísticas han sido asignadas exitosamente.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
    }

} else if ($is_editado) {
    $mensaje_redireccion = "La ficha no pudo ser editada por alguna razón. Intenta editar otra vez. Si esto sigue ocurriendo, avisa al Staff por Discord.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

/* Page */ 
if (!$is_editado) {
    $ficha_existe = false;
    $aprobada_por = false;
    $vitalidad_extra = 0;
    $energia_extra = 0;
    $haki_extra = 0;

    $query_ficha = $db->query("
        SELECT * FROM mybb_op_fichas WHERE fid='$uid'
    ");

    while ($f = $db->fetch_array($query_ficha)) {
        $aprobada_por = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
        $ficha_existe = true;
    }

    if ($ficha_existe == true && $aprobada_por == true && ($uid == $mybb->user['uid'] || $g_is_staff)) {
        $query_ficha = $db->query("
            SELECT * FROM mybb_op_fichas WHERE fid='$uid'
        ");

        $ficha = null;

        while ($q = $db->fetch_array($query_ficha)) {
            $ficha = $q;
        
            $vitalidad_extra = ((intval($q['fuerza_pasiva']) * 6) + (intval($q['resistencia_pasiva']) * 15) + (intval($q['destreza_pasiva']) * 4) +
                (intval($ficha['agilidad_pasiva']) * 3) + (intval($q['voluntad_pasiva']) * 1) + (intval($q['punteria_pasiva']) * 2) + (intval($q['reflejos_pasiva']) * 1));

            if (intval($q['fuerza_pasiva'])) {
                $energia_extra += (intval($q['fuerza_pasiva']) * 2);
            }

            if (intval($q['resistencia_pasiva'])) {
                $energia_extra += (intval($q['resistencia_pasiva']) * 4);
            }

            if (intval($q['punteria_pasiva'])) {
                $energia_extra += (intval($q['punteria_pasiva']) * 5);
            }

            if (intval($q['destreza_pasiva'])) {
                $energia_extra += (intval($q['destreza_pasiva']) * 4);
            }

            if (intval($q['agilidad_pasiva'])) {
                $energia_extra += (intval($q['agilidad_pasiva']) * 5);
            }

            if (intval($q['reflejos_pasiva'])) {
                $energia_extra += (intval($q['reflejos_pasiva']) * 1);
            }

            if (intval($q['voluntad_pasiva'])) {
                $energia_extra += (intval($q['voluntad_pasiva']) * 1);
                $haki_extra += (intval($q['voluntad_pasiva']) * 10);
            }

        }

        eval('$op_ficha_asignar_estadisticas_script = "'.$templates->get('op_ficha_asignar_estadisticas_script').'";');
        eval("\$page = \"".$templates->get("op_ficha_asignar_estadisticas")."\";");
        output_page($page);
    } else {
        $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
    }

}