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
define('THIS_SCRIPT', 'intercambio.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
global $templates, $mybb;

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;

// if (!($uid == '17' || $uid == '3' || $uid == '92')) {

//     $mensaje_redireccion = "Resolviendo bugs. Pido paciencia.";
//     eval("\$page = \"".$templates->get("op_redireccion")."\";");
//     output_page($page);
//     return;
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
while ($f = $db->fetch_array($query_ficha)) { $ficha = $f; $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true); }



if ($ficha == null || $ficha_aprobada == false) {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$username = $mybb->user['username'];
$intercambio = $_POST["intercambio"];
$berries = addslashes($_POST["berries"]);

if ($berries == '') {
    $berries = '0';
}

$razon = addslashes($_POST["razon"]);
$id = addslashes($_POST["id"]);
$r_uid = addslashes($_POST["r_id"]);
$tid = addslashes($_POST["tid"]);
$items = addslashes($_POST["items"]);
$accion = addslashes($_POST["accion"]);
$intercambio_realizado = false;

$intercambios_array = array();
$intercambios_query = $db->query(" SELECT * FROM `mybb_op_intercambios` ORDER BY ID DESC LIMIT 200; ");
while ($q = $db->fetch_array($intercambios_query)) {
    
    array_push($intercambios_array, $q);
}
$intercambios_json = json_encode($intercambios_array);

$mensaje_log = '';

if ($tid && $accion == 'chequear_tid') {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    $tema_existe = '0';
    $personajesPostearon = '0';
    $is_tripu = '0';

    $query_threads = $db->query(" 
        SELECT * FROM mybb_threads as t 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        WHERE tid='$tid'
        AND (f.parentlist LIKE '10,%' OR f.fid = '9'); "); 
    while ($q = $db->fetch_array($query_threads)) { $tema_existe = '1'; }

    $query_threads_tripu = $db->query(" 
        SELECT * FROM mybb_threads as t 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        WHERE tid='$tid'
        AND f.fid = '9'; "); 
    while ($q = $db->fetch_array($query_threads_tripu)) { $is_tripu = '1'; }

    $query_ficha_r = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$r_uid' "); 
    $ficha_r = null;
    while ($f = $db->fetch_array($query_ficha_r)) { $ficha_r = $f; }

    $query_usuarios_posts = $db->query(" 
        SELECT p.uid
        FROM mybb_posts AS p
        INNER JOIN mybb_threads AS t ON p.tid = t.tid
        WHERE t.tid = '$tid' AND p.uid IN ('$uid', '$r_uid')
        GROUP BY p.uid;
    "); 

    $count = 0;
    while ($f = $db->fetch_array($query_usuarios_posts)) { $count = $count + 1; }

    if ($count == 2 || $is_tripu == '1') {
        $personajesPostearon = '1';
    }


    $response[0] = array(
        'timestamp' => $timestamp,
        'tema_existe' => $tema_existe,
        'r_faccion' => $ficha_r['faccion'],
        'personajesPostearon' => $personajesPostearon,
        'is_tripu' => $is_tripu
    );

    echo json_encode($response); 
    return;

}

if ($intercambio == 'true' && ($berries != '' && intval($berries) >= 0) ) {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    $r_nombre = '';
    $r_ficha_existe = false;

    $objetos_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim($items));
    $objetos_array_name = array();
    $objetos_array_name_text = '';

    $query_ficha_r = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$r_uid' "); 
    $ficha_r = null;
    while ($f = $db->fetch_array($query_ficha_r)) { $ficha_r = $f; $r_nombre = $f['nombre']; $r_ficha_existe = true; }

    $nuevos_berries_u = intval($ficha['berries']) - intval($berries);
    $nuevos_berries_r = intval($ficha_r['berries']) + intval($berries);
    $faccion = $ficha['faccion'];
    $r_faccion = $ficha_r['faccion'];


    if ($r_ficha_existe) {
        if ($berries > 0) {
            if ($nuevos_berries_u < 0) {
                $mensaje_log = "No tienes tantos Berries. ¡Igual te han robado!\n";
                
            } else {
                $db->query(" 
                    UPDATE `mybb_op_fichas` SET `berries`='$nuevos_berries_u' WHERE fid='$uid'
                ");
                $db->query(" 
                    UPDATE `mybb_op_fichas` SET `berries`='$nuevos_berries_r' WHERE fid='$r_uid'
                ");
                
                log_audit_currency($uid, $username, $r_uid, '[Intercambio][Berries]', 'berries', $nuevos_berries_r);
                log_audit_currency($uid, $username, $uid, '[Intercambio][Berries]', 'berries', $nuevos_berries_u);
                
                $mensaje_log = "¡$berries Berries transferidos!\n";
                $intercambio_realizado = true;
            }
        }
    
        foreach ($objetos_array as $obj) {
            $clean_obj = trim($obj);
            if ($clean_obj != "") {
    
                array_push($objetos_array_name);
    
                $objeto_actual_query = $db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id='$clean_obj'");
    
                while ($q = $db->fetch_array($objeto_actual_query)) { 
                    $objeto_actual = $q;
                }
    
                $objeto_tier = intval($objeto_actual['tier']);
                $objeto_comerciable = intval($objeto_actual['comerciable']);
                $objeto_nombre = $objeto_actual['nombre'];
                $objeto_subcategoria = strtolower($objeto_actual['subcategoria'] ?? '');

                if ($objeto_comerciable < 1 && ($objeto_tier <= 5 || $objeto_subcategoria === 'AKUMA NO MI')) {
                    $has_objeto_r = false;
                    $has_objeto_u = false;
        
                    $inventario_actual_r = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$r_uid' AND objeto_id='$clean_obj'");
                    $inventario_actual_u = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$clean_obj'");
        
                    $imagen_obj = '';
                    $apodo = '';
                    $autor = '';
                    $autor_id = '';
        
                    // Añadir lo que se envia
                    while ($q = $db->fetch_array($inventario_actual_r)) {
                        $has_objeto_r = true;
                        $cantidad_r = $q['cantidad'];
                    }
                    
                    $bautizado_u = 0;
                    while ($q = $db->fetch_array($inventario_actual_u)) {
                        $has_objeto_u = true;
                        $cantidad_u = $q['cantidad'];
                        $imagen_obj = $q['imagen'];
                        $apodo = $q['apodo'];
                        $autor = $q['autor'];
                        $autor_uid = $q['autor_uid'];
                        $editado = $q['editado'];
                        $especial = $q['especial'];
                        $oficios = json_decode($q['oficios']);
                        $bautizado_u = (int)$q['bautizado'];
                    }
        
                    if ($has_objeto_u && intval($cantidad_u) > 1) {
                        $nueva_cantidad_u = intval($cantidad_u) - 1;
                        $db->query(" 
                            UPDATE `mybb_op_inventario` SET `cantidad`='$nueva_cantidad_u' WHERE objeto_id='$clean_obj' AND uid='$uid'
                        ");
                    } else if ($has_objeto_u && intval($cantidad_u) == 1) {
                        $db->query(" DELETE FROM `mybb_op_inventario` WHERE objeto_id='$clean_obj' AND uid='$uid'; ");
                    }
    
                    if ($has_objeto_r && $has_objeto_u) {
                        $nueva_cantidad_r = intval($cantidad_r) + 1;
    
                        $db->query(" 
                            UPDATE `mybb_op_inventario` SET `cantidad`='$nueva_cantidad_r' WHERE objeto_id='$clean_obj' AND apodo='$apodo' AND uid='$r_uid'
                        ");
                    } else if ($has_objeto_u) { // imagen, apodo, autor, autor_uid
    
                        $oficios = json_encode($oficios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
                        $db->query("
                            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`, `imagen`, `apodo`, `autor`, `autor_uid`, `especial`, `editado`, `oficios`, `bautizado`) VALUES
                            ('$clean_obj', '$r_uid', '1', '$imagen_obj', '$apodo', '$autor', '$autor_uid', '$especial', '$editado', '$oficios', '$bautizado_u');
                        ");
                    }
    
                    if ($has_objeto_u) {
    
                        if ($apodo != '') {
                            $apodo_total = "$apodo ($objeto_nombre)";
                            array_push($objetos_array_name, $apodo_total);
                        } else {
                            array_push($objetos_array_name, $objeto_nombre);
                        }
                        
                        // Si es un barco bautizado, transferir propiedad en tablas de barco
                        if (strtolower($objeto_actual['subcategoria'] ?? '') === 'barcos' && $bautizado_u === 1) {
                            $barco_transfer_id = $db->escape_string($clean_obj);
                            $barco_old_owner   = (int)$uid;
                            $barco_new_owner   = (int)$r_uid;
                            $barco_tables = array('op_barco_tripulacion', 'op_barco_estado', 'op_barco_salas', 'op_barco_npcs');
                            foreach ($barco_tables as $btbl) {
                                $db->update_query($btbl, array('owner_uid' => $barco_new_owner), "barco_id='$barco_transfer_id' AND owner_uid='$barco_old_owner'");
                            }
                            // Expulsar al antiguo dueño de la tripulación
                            $db->delete_query('op_barco_tripulacion', "barco_id='$barco_transfer_id' AND owner_uid='$barco_new_owner' AND miembro_uid='$barco_old_owner'");
                        }

                        $intercambio_realizado = true;
                    }
                    
                    
                }
    
            }
        }
        
        if ($intercambio_realizado == true) {
            $objetos_array_name_text = join(", ", $objetos_array_name);
            $db->query(" INSERT INTO `mybb_op_intercambios`(`uid`, `nombre`, `r_uid`, `r_nombre`, `tid`, `dinero`, `objetos`, `objetos_nombre`, `timestamp`, `razon`, `faccion`, `r_faccion`) VALUES 
                ('$uid','$username','$r_uid','$r_nombre','$tid','$berries','$items','$objetos_array_name_text','$timestamp', '$razon', '$faccion', '$r_faccion')");
            $mensaje_log .= "Intercambio de objetos realizado. ¡Bravo!";

            if (
                ($faccion == 'Pirata' && ($r_faccion == 'Marina' || $r_faccion == 'CipherPol' || $r_faccion == 'Cazadores')) ||
                ($faccion == 'Marina' && ($r_faccion == 'Pirata' || $r_faccion == 'Revolucionario')) ||
                ($faccion == 'CipherPol' && ($r_faccion == 'Pirata' || $r_faccion == 'Revolucionario')) ||
                ($faccion == 'Revolucionario' && ($r_faccion == 'Marina' || $r_faccion == 'CipherPol' || $r_faccion == 'Cazadores')) ||
                ($faccion == 'Cazadores' && ($r_faccion == 'Pirata' || $r_faccion == 'Revolucionario'))
               ) {
                $descripcion = "El usuario $username ($uid - $faccion) ha hecho un intercambio $r_nombre ($r_uid - $r_faccion). Verificar si es una transacción valida. El TID es: $tid y la razón ha sido: $razon.";
                $db->query(" 
                    INSERT INTO `mybb_op_avisos` (`uid`, `nombre`, `categoria`, `resumen`, `descripcion`, `url`) VALUES ('$uid','$username','intercambio','Intercambio de Facciones Rivales','$descripcion','')
                ");
        
           }


        } else {
            $mensaje_log .= "No se pudo realizar el intercambio de objetos. ¡Revisa que no te hayan robado o hayas enviado un objeto equivocado!";
        }
    } else {
        $mensaje_log .= "¡La ficha del usuario al que envías no existe!";
    }

    $response[0] = array(
        'timestamp' => $timestamp,
        'mensaje_log' => $mensaje_log
    );

    echo json_encode($response); 
    return;
}


eval("\$page = \"".$templates->get("op_intercambio")."\";");
output_page($page);
