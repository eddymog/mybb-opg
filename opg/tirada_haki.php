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
define('THIS_SCRIPT', 'tirada_haki.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./../op/functions/op_functions.php";
global $templates, $mybb;

$uid = $mybb->user['uid'];
$ficha = null;

// if ($uid == '315') {
//     $uid = '279';
// }

if ($uid == '0') {
    $mensaje_redireccion = "Debes estar registrado.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$uid' "); 
while ($f = $db->fetch_array($query_ficha)) { $ficha = $f; }

if ($ficha == null) {
    $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$nivel = intval($ficha['nivel']);

$username = $mybb->user['username'];

$choose_haki = $_POST["choose_haki"];
$tirada_real = $_POST["tirada_real"];
$tirada_aleatoria = $_POST["tirada_aleatoria"];

$tirada_aleatoria_resultado = '';

$tiradas_totales = '0';
$tiradas_buso = '0';
$tiradas_kenbun = '0';
$tiradas_hao = '0';

$tiradas_totales_id = '0';
$tiradas_buso_id = '0';;
$tiradas_kenbun_id = '0';
$tiradas_hao_id = '0';

$tiradas_aleatorias_array = array();
$tiradas_reales_array = array();

$tiradas_aleatorias_query = $db->query(" SELECT * FROM `mybb_op_tirada_haki` WHERE `real`='0' ORDER BY ID DESC LIMIT 20; ");
$tiradas_reales_query = $db->query(" SELECT * FROM `mybb_op_tirada_haki` WHERE `real`='1' ORDER BY ID DESC LIMIT 200; ");

$tiradas_totales_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` ");
$tiradas_buso_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE haki='buso'; ");
$tiradas_kenbun_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE haki='kenbun'; ");
$tiradas_hao_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE haki='hao'; ");

$tiradas_totales_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE uid='$uid' ");
$tiradas_buso_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE haki='buso' AND uid='$uid'; ");
$tiradas_kenbun_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE haki='kenbun' AND uid='$uid'; ");
$tiradas_hao_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_haki` WHERE haki='hao' AND uid='$uid'; ");

while ($q = $db->fetch_array($tiradas_totales_query)) { $tiradas_totales = $q['total']; }
while ($q = $db->fetch_array($tiradas_buso_query)) { $tiradas_buso = $q['total']; }
while ($q = $db->fetch_array($tiradas_kenbun_query)) { $tiradas_kenbun = $q['total']; }
while ($q = $db->fetch_array($tiradas_hao_query)) { $tiradas_hao = $q['total']; }

while ($q = $db->fetch_array($tiradas_totales_id_query)) { $tiradas_totales_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_buso_id_query)) { $tiradas_buso_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_kenbun_id_query)) { $tiradas_kenbun_id = $q['total']; }
while ($q = $db->fetch_array($tiradas_hao_id_query)) { $tiradas_hao_id = $q['total']; }

while ($q = $db->fetch_array($tiradas_aleatorias_query)) { 
    array_push($tiradas_aleatorias_array, $q);
}

while ($q = $db->fetch_array($tiradas_reales_query)) { 
    array_push($tiradas_reales_array, $q);
}



$tiradas_aleatorias_json = json_encode($tiradas_aleatorias_array);
$tiradas_reales_json = json_encode($tiradas_reales_array);

$has_full_haki = false;
if ($ficha['camino'] == 'Haki') { $has_full_haki = true; }

$has_kenbun = false;
$has_buso = false;
$hao_hao = false;

$select_kenbun = false;
$select_buso = false;
$select_hao = false;

$is_hao_option = false;

$chances_tiradas = 0;

$hao_chance = intval($ficha['hao_chance']);

$hakis_arr = array();

if (($has_full_haki && $nivel >= 10) || $nivel >= 15) { $chances_tiradas += 1; }
if (($has_full_haki && $nivel >= 15) || $nivel >= 20) { $chances_tiradas += 1; }
if (($has_full_haki && $nivel >= 20) || $nivel >= 25) { $chances_tiradas += 1; }

if (intval($ficha['buso']) >= 1) { $has_buso = true; }
if (intval($ficha['kenbun']) >= 1) { $has_kenbun = true; }
if (intval($ficha['hao']) >= 1) { $has_hao = true; }

if (intval($ficha['buso']) == 0) { $select_buso = true; }
if (intval($ficha['kenbun']) == 0) { $select_kenbun = true; }
if (intval($ficha['hao']) == 0) { $select_hao = true; }

if ($select_kenbun) { array_push($hakis_arr, "kenbun"); }
if ($select_buso) { array_push($hakis_arr, "buso"); }
if ($select_hao) { array_push($hakis_arr, "hao"); }

$hakis_arr_count = count($hakis_arr);

if ($select_hao || $has_hao) {
    $chances_tiradas = $chances_tiradas - (3 - $hakis_arr_count);
} else {
    $chances_tiradas = $chances_tiradas - (2 - $hakis_arr_count);
}

if ($chances_tiradas > $hakis_arr_count) { $chances_tiradas = $hakis_arr_count; }
if ($chances_tiradas < 0) { $chances_tiradas = 0; }

if ($tirada_aleatoria == 'true') {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    $tipo_haki = '';
    $tirada_random = rand(1, 3);

    if ($tirada_random == 1) { $tipo_haki = 'buso'; } 
    else if ($tirada_random == 2) { $tipo_haki = 'kenbun'; } 
    else if ($tirada_random == 3) { $tipo_haki = 'hao'; } 

    $response[0] = array(
        'nombre' => $username,
        'haki' => $tipo_haki,
        'timestamp' => $timestamp
    );

    $db->query(" INSERT INTO `mybb_op_tirada_haki`(`uid`, `nombre`, `haki`, `subnombre`, `real`, `timestamp`) VALUES 
        ('$uid','$username','$tipo_haki','','0','$timestamp')");

    echo json_encode($response); 
    return;
}

if ($choose_haki != '' && $has_full_haki) {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    if (!in_array($choose_haki, $hakis_arr)) {
        echo json_encode(['success' => false, 'mensaje' => 'Ese haki no está disponible.']);
        return;
    }

    $response[0] = array(
        'nombre' => $username,
        'haki' => $choose_haki,
        'timestamp' => $timestamp
    );

    $db->query(" INSERT INTO `mybb_op_tirada_haki`(`uid`, `nombre`, `haki`, `subnombre`, `real`, `timestamp`) VALUES 
        ('$uid','$username','$choose_haki','','1','$timestamp')");

    if ($choose_haki == 'buso') {
        $db->query(" UPDATE `mybb_op_fichas` SET `buso`='1' WHERE fid='$uid'; ");
    }

    if ($choose_haki == 'kenbun') {
        $db->query(" UPDATE `mybb_op_fichas` SET `kenbun`='1' WHERE fid='$uid'; ");
    }

    if ($select_hao) {
        if ($choose_haki == 'hao') {
            $db->query(" UPDATE `mybb_op_fichas` SET `hao`='1' WHERE fid='$uid'; ");
        }
    }

    echo json_encode($response); 
    return;
}

if ($tirada_real == 'true') {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    if ($hakis_arr_count <= 0 || $chances_tiradas <= 0) {
        echo json_encode(['success' => false, 'mensaje' => 'No cumples los requisitos para esta tirada.']);
        return;
    }

    $tipo_haki = '';
    $tirada_random = rand(0, $hakis_arr_count - 1);

    $tipo_haki = $hakis_arr[$tirada_random];

    $response[0] = array(
        'nombre' => $username,
        'haki' => $tipo_haki,
        'timestamp' => $timestamp
    );

    $db->query(" INSERT INTO `mybb_op_tirada_haki`(`uid`, `nombre`, `haki`, `subnombre`, `real`, `timestamp`) VALUES 
        ('$uid','$username','$tipo_haki','','1','$timestamp')");

    if ($tipo_haki == 'buso') {
        $db->query(" UPDATE `mybb_op_fichas` SET `buso`='1' WHERE fid='$uid'; ");
    }

    if ($tipo_haki == 'kenbun') {
        $db->query(" UPDATE `mybb_op_fichas` SET `kenbun`='1' WHERE fid='$uid'; ");
    }

    if ($tipo_haki == 'hao') {
        $db->query(" UPDATE `mybb_op_fichas` SET `hao`='1' WHERE fid='$uid'; ");
    }

    echo json_encode($response); 
    return;
}

eval("\$page = \"".$templates->get("op_tirada_haki")."\";");
output_page($page);
