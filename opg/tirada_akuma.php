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
define('THIS_SCRIPT', 'tirada_akuma.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./../op/functions/op_functions.php";
global $templates, $mybb;

$uid = $mybb->user['uid'];
$ficha = null;
$ficha_aprobada = false;

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


// if ($uid != '43') {
//     $mensaje_redireccion = "En mantenimiento.";
//     eval("\$page = \"".$templates->get("op_redireccion")."\";");
//     output_page($page);
//     return;
// }

$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$uid' "); 
while ($f = $db->fetch_array($query_ficha)) { $ficha = $f; $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true); }

// if ($ficha == null) {
//     $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
//     eval("\$page = \"".$templates->get("op_redireccion")."\";");
//     output_page($page);
//     return;
// }

$username = $mybb->user['username'];
$tirada_real = $_POST["tirada_real"];
$tirada_aleatoria = $_POST["tirada_aleatoria"];

$tirada_aleatoria_resultado = '';

$tiradas_totales = '0';
$tiradas_tier1 = '0';;
$tiradas_tier2 = '0';
$tiradas_tier3 = '0';
$tiradas_tier4 = '0';
$tiradas_tier5 = '0';
$tiradas_tier6 = '0';

$tiradas_totales_id = '0';
$tiradas_tier1_id = '0';;
$tiradas_tier2_id = '0';
$tiradas_tier3_id = '0';
$tiradas_tier4_id = '0';
$tiradas_tier5_id = '0';
$tiradas_tier6_id = '0';
$tiradas_distintas = '0';

$tiradas_aleatorias_array = array();
$tiradas_reales_array = array();

$tiradas_aleatorias_query = $db->query(" SELECT * FROM `mybb_op_tirada_akumas` WHERE `real`='0' ORDER BY ID DESC LIMIT 20; ");
$tiradas_reales_query = $db->query(" SELECT * FROM `mybb_op_tirada_akumas` WHERE `real`='1' ORDER BY ID DESC LIMIT 200; ");
$tiradas_distintas_query = $db->query(" SELECT count(DISTINCT(fruta)) as total FROM `mybb_op_tirada_akumas` WHERE uid='$uid' AND `real` = 0; ");

$tiradas_totales_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` ");
$tiradas_tier1_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=1; ");
$tiradas_tier2_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=2; ");
$tiradas_tier3_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=3; ");
$tiradas_tier4_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=4; ");
$tiradas_tier5_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=5; ");
$tiradas_tier6_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=6; ");

$tiradas_totales_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE uid='$uid' ");
$tiradas_tier1_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=1 AND uid='$uid'; ");
$tiradas_tier2_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=2 AND uid='$uid'; ");
$tiradas_tier3_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=3 AND uid='$uid'; ");
$tiradas_tier4_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=4 AND uid='$uid'; ");
$tiradas_tier5_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=5 AND uid='$uid'; ");
$tiradas_tier6_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_akumas` WHERE tier=6 AND uid='$uid'; ");

while ($q = $db->fetch_array($tiradas_totales_query)) { $tiradas_totales = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier1_query)) { $tiradas_tier1 = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier2_query)) { $tiradas_tier2 = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier3_query)) { $tiradas_tier3 = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier4_query)) { $tiradas_tier4 = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier5_query)) { $tiradas_tier5 = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier6_query)) { $tiradas_tier6 = $q['total']; }

while ($q = $db->fetch_array($tiradas_totales_id_query)) { $tiradas_totales_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier1_id_query)) { $tiradas_tier1_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier2_id_query)) { $tiradas_tier2_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier3_id_query)) { $tiradas_tier3_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier4_id_query)) { $tiradas_tier4_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier5_id_query)) { $tiradas_tier5_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_tier6_id_query)) { $tiradas_tier6_id = $q['total']; }

while ($q = $db->fetch_array($tiradas_distintas_query)) { $tiradas_distintas = $q['total']; }

while ($q = $db->fetch_array($tiradas_aleatorias_query)) { 
    array_push($tiradas_aleatorias_array, $q);
}

while ($q = $db->fetch_array($tiradas_reales_query)) { 
    array_push($tiradas_reales_array, $q);
}

$tiradas_aleatorias_json = json_encode($tiradas_aleatorias_array);
$tiradas_reales_json = json_encode($tiradas_reales_array);

$nivel = intval($ficha['nivel']);
$has_full_haki = false;
// $has_full_haki_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V029'; "); 
// while ($q = $db->fetch_array($has_full_haki_query)) { $has_full_haki = true; }
if ($ficha['camino'] == 'Haki') { $has_full_haki = true; }

if ($nivel >= 8) {
    $has_nivel = true;
}

$has_akuma = false;
if ($ficha['akuma'] != '') {
    $has_akuma = true;
}

if ($tirada_aleatoria == 'true') {

    $akuma_magica_query = $db->query(" SELECT * FROM `mybb_op_akumas` ORDER BY RAND() LIMIT 1; ");

    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    while ($q = $db->fetch_array($akuma_magica_query)) { 
        $fruta = $q['nombre'];
        $subnombre = $q['subnombre'];
        $descripcion = $q['descripcion'];
        $tier = $q['tier'];
        $categoria = $q['categoria'];

        $response[0] = array(
            'nombre' => $username,
            'fruta' => $fruta,
            'subnombre' => $subnombre,
            'descripcion' => $descripcion,
            'categoria' => $categoria,
            'tier' => $tier,
            'timestamp' => $timestamp
        );

        if ($uid != '0') {
            $db->query(" INSERT INTO `mybb_op_tirada_akumas`(`uid`, `nombre`, `tier`, `fruta`, `subnombre`, `real`, `timestamp`) VALUES 
            ('$uid','$username','$tier','$fruta','$subnombre','0','$timestamp')");
        }
    }

    echo json_encode($response); 
    return;
}

if ($tirada_real == 'true' && $has_nivel && !$has_akuma) {

    $akuma_magica_query = $db->query(" SELECT * FROM `mybb_op_akumas` WHERE ocupada = 0 ORDER BY RAND() LIMIT 1; ");
    
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    while ($q = $db->fetch_array($akuma_magica_query)) { 
        $fruta = $q['nombre'];
        $subnombre = $q['subnombre'];
        $descripcion = $q['descripcion'];
        $tier = $q['tier'];
        $categoria = $q['categoria'];

        $response[0] = array(
            'uid' => $uid,
            'nombre' => $username,
            'fruta' => $fruta,
            'subnombre' => $subnombre,
            'descripcion' => $descripcion,
            'categoria' => $categoria,
            'tier' => $tier,
            'timestamp' => $timestamp
        );

        // if ($uid != '10') {
        $db->query(" INSERT INTO `mybb_op_tirada_akumas`(`uid`, `nombre`, `tier`, `fruta`, `subnombre`, `real`, `timestamp`) VALUES 
            ('$uid','$username','$tier','$fruta','$subnombre','1','$timestamp')");
        $db->query(" UPDATE `mybb_op_fichas` SET `akuma`='$fruta', `akuma_subnombre`='$subnombre' WHERE fid='$uid'; ");
        $db->query(" UPDATE `mybb_op_akumas` SET `ocupada`='1', `uid`='$uid' WHERE nombre='$fruta' AND subnombre='$subnombre'; ");
        // }
        
    }

    echo json_encode($response); 
    return;
}

eval("\$page = \"".$templates->get("op_tirada_akuma")."\";");
output_page($page);
