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
define('THIS_SCRIPT', 'tienda.php');

global $templates, $mybb, $db;

require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$accion = $_POST["accion"];
$comprasTotal = 0;
$listaDeCompras = $_POST["listaDeCompras"];
$listaDeComprasKeys = $_POST["listaDeComprasKeys"];
$ficha = null;


if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($q = $db->fetch_array($query_ficha)) { $ficha = $q; }
$berries = $ficha['berries'];

// if ($uid != '17') {
//     $mensaje_redireccion = "En reparos técnicos, volveré pronto.";
//     eval("\$page = \"".$templates->get("op_redireccion")."\";");
//     output_page($page);
//     return;
// }

$has_pobre1 = false;
$has_pobre2 = false;
$has_pobre3 = false;
$query_pobre1 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE virtud_id='D021' AND uid='$uid' ");
$query_pobre2 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE virtud_id='D022' AND uid='$uid' ");
$query_pobre3 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE virtud_id='D023' AND uid='$uid' ");

while ($q = $db->fetch_array($query_pobre1)) { $has_pobre1 = true; }
while ($q = $db->fetch_array($query_pobre2)) { $has_pobre2 = true; }
while ($q = $db->fetch_array($query_pobre3)) { $has_pobre3 = true; }    

$query_objetos = $db->query(" SELECT * FROM `mybb_op_objetos` WHERE exclusivo='0' AND custom='0' AND tier <= 3 ORDER BY categoria, subcategoria, tier, nombre");

$objetos = array();
$objetos_array = array();

$oficio1 = $ficha['oficio1'];
$oficio2 = $ficha['oficio2'];

while ($q = $db->fetch_array($query_objetos)) { 
    $objeto_id = $q['objeto_id'];
    $key = "$objeto_id";
    if (!$objetos[$key]) { $objetos[$key] = array(); }
    array_push($objetos[$key], $q);
    array_push($objetos_array, $objeto_id);
}

$objetos_array_json = json_encode($objetos_array);
$objetos_json = json_encode($objetos);

if ($accion == 'comprar' && $uid != '0') {

    $should_cancel = false;

    foreach ($listaDeComprasKeys as $objKey) {
        if (intval($listaDeCompras[$objKey]['cantidad']) <= 0) { $should_cancel = true; }
    }
    if ($should_cancel == true) { return; }

    foreach ($listaDeComprasKeys as $objKey) {

        $costoBerries = intval($objetos[$objKey][0]['berries']);
        if ($has_pobre1) { $costoBerries = $costoBerries * 1.05; }
        if ($has_pobre2) { $costoBerries = $costoBerries * 1.1; }
        if ($has_pobre3) { $costoBerries = $costoBerries * 1.15; }
        
        $comprasTotal += ($costoBerries * $listaDeCompras[$objKey]['cantidad']);
    }

    echo($comprasTotal);

    $logObj = "";
    if ((intval($berries) >= $comprasTotal) && $comprasTotal != 0) {
        foreach ($listaDeComprasKeys as $objKey) {

            $cantidadActual = '0';
            $cantidadExtra = $listaDeCompras[$objKey]['cantidad'];
            $has_objeto = false;
            $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objKey'");
            while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }
    
            if ($has_objeto) {
                $cantidadNueva = intval($cantidadActual) + intval($cantidadExtra);
                $db->query(" 
                    UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$objKey' AND uid='$uid'
                ");
            } else {
                $db->query(" 
                    INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
                    ('$objKey', '$uid', '$cantidadExtra');
                ");
            }
    
            $logObj .= "$objKey: $cantidadExtra;";
            // echo $listaDeCompras[$objKey]['cantidad'];
        }
    
        $nuevosBerries = intval($berries) - intval($comprasTotal);
        log_audit($uid, $username, '[Tienda]', "Berries: $berries->$nuevosBerries (Gasto: $comprasTotal). $logObj");
        
            
        // $db->query(" UPDATE `mybb_op_fichas` SET berries='$nuevosBerries' WHERE `fid`='$uid'; ");
        log_audit_currency($uid, $username, $uid, '[Tienda][Berries]', 'berries', $nuevosBerries);
    }

    return;
}

$reload_js = "";

if (does_ficha_exist($uid)) {

    eval("\$page = \"".$templates->get("op_tienda")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}