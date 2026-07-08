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
define('THIS_SCRIPT', 'inventario.php');

global $templates, $mybb, $db;

require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid = $mybb->get_input('uid'); 
$uid_user = $mybb->user['uid'];
$username = $mybb->user['username'];

$accion = $mybb->get_input('accion'); 
$objeto_vender = $mybb->get_input('objeto'); 

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


if (!$uid) {
    $uid = $mybb->user['uid'];
}

$is_same_user = $uid == $uid_user;

$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$uid' "); 
$ficha = null;
while ($f = $db->fetch_array($query_ficha)) { 
    $ficha = $f; 
}

$oficios = json_decode($ficha['oficios']);
$nivelMaxComerciante = 0;

if (isset($oficios->{'Mercader'}->{'sub'}->{'Comerciante'})) {
    $nivelMaxComerciante = $oficios->{'Mercader'}->{'sub'}->{'Comerciante'};
}


$fuerza_completa = intval($ficha['fuerza']) + intval($ficha['fuerza_pasiva']);
$resistencia_completa = intval($ficha['resistencia']) + intval($ficha['resistencia_pasiva']);
$destreza_completa = intval($ficha['destreza']) + intval($ficha['destreza_pasiva']);
$punteria_completa = intval($ficha['punteria']) + intval($ficha['punteria_pasiva']);
$agilidad_completa = intval($ficha['agilidad']) + intval($ficha['agilidad_pasiva']);
$reflejos_completa = intval($ficha['reflejos']) + intval($ficha['reflejos_pasiva']);
$voluntad_completa = intval($ficha['voluntad']) + intval($ficha['voluntad_pasiva']);
$control_akuma_completa = intval($ficha['control_akuma']) + intval($ficha['control_akuma_pasiva']);

$objetos_html = '';

$edit_objeto_id = $_POST["edit_objeto_id"];
$edit_nombre = addslashes($_POST["edit_nombre"]);
$edit_imagen = addslashes($_POST["edit_imagen"]);


if ($edit_nombre != '' && $edit_imagen != '' && $edit_objeto_id != '') {



    $obj_custom = null;
    $inventario_custom = null;
    $objeto_custom_query = $db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id='$edit_objeto_id'");
    while ($q = $db->fetch_array($objeto_custom_query)) {  $obj_custom = $q; }

    $inventario_custom_query = $db->query("SELECT * FROM mybb_op_inventario WHERE objeto_id='$edit_objeto_id' AND uid='$uid'; ");
    while ($q = $db->fetch_array($inventario_custom_query)) {  $inventario_custom = $q; }

    $count_id = 0;
    $objeto_count = $db->query("SELECT count(*) as count FROM mybb_op_objetos WHERE objeto_id LIKE '%$objeto_id-$uid%'");
    while ($q = $db->fetch_array($objeto_count)) { $count_id = intval($q['count']) + 1; }
    
    $new_obj_exists = false;

    $already_custom = false;

    if (strpos($edit_objeto_id, '-') != false) {
        $already_custom = true;
        $new_nombre = "$edit_objeto_id";
    } else {
        $new_nombre = "$edit_objeto_id-$uid-$count_id";
    }

    $categoria = $obj_custom['categoria']; 
    $subcategoria = $obj_custom['subcategoria'];
    $nombre = $obj_custom['nombre'];
    $tier = $obj_custom['tier'];
    $imagen_id = $obj_custom['imagen_id'];
    $imagen_avatar = $obj_custom['imagen_avatar'];
    $berries = $obj_custom['berries'];
    $cantidadMaxima = $obj_custom['cantidadMaxima'];
    $dano = $obj_custom['dano'];
    $efecto = $obj_custom['efecto'];
    $exclusivo = $obj_custom['exclusivo'];
    $espacios = $obj_custom['espacios'];
    $imagen = $obj_custom['imagen'];
    $desbloquear = $obj_custom['desbloquear'];
    $oficio = $obj_custom['oficio'];
    $nivel = $obj_custom['nivel'];
    $requisitos = $obj_custom['requisitos'];
    $escalado = $obj_custom['escalado'];
    $editable = '1';
    $custom = '1';
    $descripcion = $obj_custom['descripcion'];

    $autor = $inventario_custom['autor'];
    $autor_id = $inventario_custom['autor_id'];
    $obj_inv_oficios = $inventario_custom['oficios'];

    if ($already_custom) {
        $db->query(" UPDATE `mybb_op_inventario` SET `imagen`='$edit_imagen',`apodo`='$edit_nombre',`editado`='1' WHERE objeto_id='$edit_objeto_id' AND uid='$uid' ");

    
    } else {        
        $inventarioCantidad = intval($inventario_custom['cantidad']);
    
        if ($inventarioCantidad > 0) {
            $db->query(" INSERT INTO `mybb_op_objetos`(`objeto_id`, `categoria`, `subcategoria`, `nombre`, `tier`, `imagen_id`, `imagen_avatar`, `berries`, `cantidadMaxima`, `dano`, `efecto`, `exclusivo`, `espacios`, `imagen`, `desbloquear`, `oficio`, `nivel`, `requisitos`, `escalado`, `editable`, `custom`, `descripcion`) VALUES 
            ('$new_nombre','$categoria','$subcategoria','$nombre','$tier','$imagen_id','$imagen_avatar','$berries','$cantidadMaxima','$dano','$efecto','$exclusivo','$espacios','$imagen','$desbloquear','$oficio','$nivel','$requisitos','$escalado','$editable','$custom','$descripcion'); ");  
            $db->query(" INSERT INTO mybb_op_inventario(`objeto_id`, `uid`, `cantidad`, `imagen`, `apodo`, `especial`, `editado`, `bautizado`) VALUES ('$new_nombre','$uid','1','$edit_imagen','$edit_nombre','1','1','0'); ");
    
            if ($inventarioCantidad > 1) {
                $nueva_cantidad = intval($inventario_custom['cantidad']) - 1;
                $db->query(" 
                    UPDATE `mybb_op_inventario` SET `cantidad`='$nueva_cantidad' WHERE objeto_id='$edit_objeto_id' AND uid='$uid'
                ");
            } else if ($inventarioCantidad == 1) {
                $db->query(" DELETE FROM `mybb_op_inventario` WHERE objeto_id='$edit_objeto_id' AND uid='$uid'; ");
            }
        }
    }


    
    return;
}


if (does_ficha_exist($uid)) {

    // $oficios = $ficha['oficios'];

    $oficios = json_decode($ficha['oficios']);
    $precioVentaPct = 2.00000001;

    if (isset($oficios->{'Mercader'})) {
        
        if (isset($oficios->{'Mercader'}->{'sub'}->{'Comerciante'})) {
            $caminoNivel = $oficios->{'Mercader'}->{'sub'}->{'Comerciante'};

            if ($caminoNivel == 1) { $precioVentaPct = 1.6666666667; }
            if ($caminoNivel == 2) { $precioVentaPct = 1.3333333333; }
            if ($caminoNivel == 3) { $precioVentaPct = 1.1111111117; }

        }
        
    }

    if ($accion == 'vender' && $objeto_vender && $is_same_user) {
        $has_objeto = false;
        $objeto_actual_query = $db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id='$objeto_vender'");
        while ($q = $db->fetch_array($objeto_actual_query)) {  $objeto_actual = $q; }

        $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objeto_vender'");
        while ($q = $db->fetch_array($inventario_actual)) {
            $has_objeto = true;
            $cantidad = $q['cantidad'];
        }

        if ($has_objeto) {

            if (intval($cantidad) > 1) {
                $nueva_cantidad = intval($cantidad) - 1;
                $db->query(" 
                    UPDATE `mybb_op_inventario` SET `cantidad`='$nueva_cantidad' WHERE objeto_id='$objeto_vender' AND uid='$uid'
                ");
            } else if (intval($cantidad) == 1) {
                $db->query(" DELETE FROM `mybb_op_inventario` WHERE objeto_id='$objeto_vender' AND uid='$uid'; ");
            }    

            $berries_actuales = intval($ficha['berries']);
            $precioVenta = intval(intval($objeto_actual['berries']) / $precioVentaPct) + 1;
            $nuevos_berries = intval($berries_actuales + $precioVenta);
            $objeto_nombre = $objeto_actual['nombre'];

            log_audit($uid_user, $username, '[Venta]', "Vendido: $objeto_nombre ($objeto_vender): $berries_actuales->$nuevos_berries (Ganancia: $precioVenta).");
            $db->query(" UPDATE `mybb_op_fichas` SET `berries`='$nuevos_berries' WHERE fid='$uid' ");
            $log = "¡Has vendido $objeto_nombre por $precioVenta berries!\nTienes ahora $nuevos_berries berries\n($berries_actuales berries + $precioVenta berries)";

            echo("<script>alert(`$log`);window.location.href = 'https://onepiecegaiden.com/op/inventario.php';</script>");
        }
 
    }

    $query_inventario = $db->query("
        SELECT * FROM `mybb_op_objetos` 
        INNER JOIN `mybb_op_inventario` 
        ON `mybb_op_objetos`.`objeto_id`=`mybb_op_inventario`.`objeto_id` 
        WHERE `mybb_op_inventario`.`uid`='$uid'
        ORDER BY `mybb_op_objetos`.categoria, `mybb_op_objetos`.subcategoria, `mybb_op_objetos`.tier, `mybb_op_objetos`.nombre
    ");

    $objetos = array();
    $objetos_array = array();

    while ($q = $db->fetch_array($query_inventario)) { 
        $objeto_id = $q['objeto_id'];
        $key = "$objeto_id";
        if (!$objetos[$key]) { $objetos[$key] = array(); }
        array_push($objetos[$key], $q);
        array_push($objetos_array, $objeto_id);
    }

    $objetos_array_json = json_encode($objetos_array);
    $objetos_json = json_encode($objetos);

    // eval("\$page = \"".$templates->get("op_inventario")."\";");
    // output_page($page);

} else {
    $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
    // eval("\$page = \"".$templates->get("op_redireccion")."\";");
    // output_page($page);
}