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
define('THIS_SCRIPT', 'viajes.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
global $templates, $mybb;

$uid = $mybb->user['uid'];
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;

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

function sucesoNaval($dado) {

    $suceso = 'BUG. Nada ocurre. Reporta al Staff.';

    if ($dado <= 90) { $suceso = 'Todo está en calma.'; }
    if ($dado >= 91 && $dado <= 92) { $suceso = 'Aparece un navío pirata.'; }
    if ($dado >= 93 && $dado <= 94) { $suceso = 'Aparece un buque marine.'; }
    if ($dado >= 95 && $dado <= 96) { $suceso = 'Navío de facción predominante.'; }
    if ($dado >= 97 && $dado <= 98) { $suceso = 'Basura/Naufragio/Iceberg.'; }

    if ($dado == 99) {
        $jefe_dado = rand(1, 4);
        $jefes = [
            1 => 'Shichibukai',
            2 => 'Vicealmirante',
            3 => 'Comandante',
            4 => 'CP9'
        ];
        $suceso = "Encuentro con {$jefes[$jefe_dado]}";
    }

    if ($dado == 100) {
        $jefe_dado = rand(1, 4);
        $jefes = [
            1 => 'Yonkou',
            2 => 'Almirante',
            3 => 'CP0',
            4 => 'Bestia Marina'
        ];
        $suceso = "Encuentro con {$jefes[$jefe_dado]}";
    }

    return $suceso;
}



$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$uid' "); 
while ($f = $db->fetch_array($query_ficha)) { $ficha = $f; $ficha_aprobada = $f['aprobada_por'] != 'sin_aprobar'; }

if ($ficha == null || $ficha_aprobada == false) {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$tipo_viaje = $mybb->get_input("tipo_viaje"); 
$mar = $mybb->get_input("mar"); 
$partida = $mybb->get_input("partida"); 
$llegada = $mybb->get_input("llegada"); 
$id_viajeros = $mybb->get_input("id_viajeros");

// Variables específicas para viajes solitarios
$mar_solitario = $mybb->get_input("mar_solitario");
$partida_solitario = $mybb->get_input("partida_solitario");
$llegada_solitario = $mybb->get_input("llegada_solitario");

// $barcos = null;
// $mapas = null;
// $brujulas = null;

$barcos = array();
$barcos_array = array();
$mapas = array();
$mapas_array = array();
$brujulas = array();
$brujulas_array = array();
$usuarios = array();
$usuarios_array = array();

$navegante_nivel = 0;
$timonel_nivel = 0;
$cartografo_nivel = 0;






if ($tipo_viaje && $mar && $partida && $llegada && $id_viajeros) {

    // split comma delimited string to an array
    $id_viajeros_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim($id_viajeros));

    $query_inv = "SELECT * FROM `mybb_op_inventario` WHERE ";
    $where_uids = "";
    $where_fids = "";

    foreach ($id_viajeros_array as $idv) {
        $idv = trim($idv);
        $where_uids = $where_uids . "uid='" . $idv . "' || ";
        $where_fids = $where_fids . "fid='" . $idv . "' || ";
    }

    $where_uids = $where_uids . "0"; 
    $where_fids = $where_fids . "0"; 

    $query_usuarios = $db->query(" 
        SELECT fid, nombre, oficio1, oficios FROM `mybb_op_fichas` WHERE oficios LIKE '%Navegante%' AND ($where_fids);
    "); 

    $query_barcos = $db->query(" 
        SELECT DISTINCT inv.objeto_id as objeto_id, obj.nombre, obj.alcance
        FROM `mybb_op_inventario` as inv
        INNER JOIN `mybb_op_objetos` as obj ON obj.objeto_id = inv.objeto_id
        WHERE subcategoria='barcos' && ($where_uids); 
    "); 

    $query_mapas = $db->query(" 
        SELECT DISTINCT inv.objeto_id as objeto_id, nombre, inv.oficios as oficios
        FROM `mybb_op_inventario` as inv
        INNER JOIN `mybb_op_objetos` as obj ON obj.objeto_id = inv.objeto_id
        WHERE subcategoria='mapas' && ($where_uids); 
    "); 

    $query_brujulas = $db->query(" 
        SELECT DISTINCT inv.objeto_id as objeto_id, nombre
        FROM `mybb_op_inventario` as inv
        INNER JOIN `mybb_op_objetos` as obj ON obj.objeto_id = inv.objeto_id
        WHERE subcategoria='log poses' && ($where_uids); 
    "); 

    while ($q = $db->fetch_array($query_barcos)) { 
        $objeto_id = $q['objeto_id'];
        $key = "$objeto_id";
        if (!$barcos[$key]) { $barcos[$key] = array(); }
        array_push($barcos[$key], $q);
        array_push($barcos_array, $objeto_id);
    
    }

    while ($q = $db->fetch_array($query_mapas)) { 
        $objeto_id = $q['objeto_id'];
        $key = "$objeto_id";
        if (!$mapas[$key]) { $mapas[$key] = array(); }
        array_push($mapas[$key], $q);
        array_push($mapas_array, $objeto_id);
    }

    while ($q = $db->fetch_array($query_brujulas)) { 
        $objeto_id = $q['objeto_id'];
        $key = "$objeto_id";
        if (!$brujulas[$key]) { $brujulas[$key] = array(); }
        array_push($brujulas[$key], $q);
        array_push($brujulas_array, $objeto_id);

    }

    while ($q = $db->fetch_array($query_usuarios)) { 
        $objeto_id = $q['fid'];
        $key = "$objeto_id";
        if (!$usuarios[$key]) { $usuarios[$key] = array(); }
        array_push($usuarios[$key], $q);
        array_push($usuarios_array, $objeto_id);

    }

} elseif ($tipo_viaje == 'solitario' && $mar_solitario && $partida_solitario && $llegada_solitario) {
    // Manejo específico para viajes solitarios - mapas y brújulas del usuario actual
    $query_mapas_solitario = $db->query(" 
        SELECT DISTINCT inv.objeto_id as objeto_id, nombre, inv.oficios as oficios
        FROM `mybb_op_inventario` as inv
        INNER JOIN `mybb_op_objetos` as obj ON obj.objeto_id = inv.objeto_id
        WHERE subcategoria='mapas' && uid='$uid'; 
    "); 
    
    $query_brujulas_solitario = $db->query(" 
        SELECT DISTINCT inv.objeto_id as objeto_id, nombre
        FROM `mybb_op_inventario` as inv
        INNER JOIN `mybb_op_objetos` as obj ON obj.objeto_id = inv.objeto_id
        WHERE subcategoria='log poses' && uid='$uid'; 
    "); 

    while ($q = $db->fetch_array($query_mapas_solitario)) { 
        $objeto_id = $q['objeto_id'];
        $key = "$objeto_id";
        if (!$mapas[$key]) { $mapas[$key] = array(); }
        array_push($mapas[$key], $q);
        array_push($mapas_array, $objeto_id);
    }

    while ($q = $db->fetch_array($query_brujulas_solitario)) { 
        $objeto_id = $q['objeto_id'];
        $key = "$objeto_id";
        if (!$brujulas[$key]) { $brujulas[$key] = array(); }
        array_push($brujulas[$key], $q);
        array_push($brujulas_array, $objeto_id);
    }

    $verbo_transporte = "viaja";
    $metodo_transporte = "viajando";

    $ht = strtolower($habilidad_transporte_p);
    if (strpos($ht, 'nado') !== false || strpos($ht, 'ningyo') !== false || strpos($ht, 'gyojin') !== false || strpos($ht, 'marino') !== false) {
        $verbo_transporte = "nada";
        $metodo_transporte = "nadando";
    } elseif (strpos($ht, 'vuelo') !== false) {
        $verbo_transporte = "vuela";
        $metodo_transporte = "volando";
    } elseif (strpos($ht, 'levit') !== false) { // cubre 'levitación'/'levitacion'
        $verbo_transporte = "levita";
        $metodo_transporte = "levitando";
    }
}

$barcos_array_json = json_encode($barcos_array);
$barcos_json = json_encode($barcos);

$mapas_array_json = json_encode($mapas_array);
$mapas_json = json_encode($mapas);

$brujulas_array_json = json_encode($brujulas_array);
$brujulas_json = json_encode($brujulas);

$usuarios_array_json = json_encode($usuarios_array);
$usuarios_json = json_encode($usuarios);

$accion = addslashes($_POST["accion"]);

function darObjetoFid($objeto_id, $fid) {   
    global $db;
    $cantidadActual = '0';
    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$fid' AND objeto_id='$objeto_id'");
    while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }

    if ($has_objeto) {
        $cantidadNueva = intval($cantidadActual) + 1;
        $db->query(" 
            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$objeto_id' AND uid='$fid'
        ");
    } else {
        $db->query(" 
            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('$objeto_id', '$fid', '1');
        ");
    }
}

if ($accion == 'viajar') {
    $mar_p = addslashes($_POST["mar"]);
    $partida_p = addslashes($_POST["partida"]);
    $llegada_p = addslashes($_POST["llegada"]);
    $dificultad_p = addslashes($_POST["dificultad"]);
    $modificador_p = addslashes($_POST["modificador"]);
    $tiempo_p = addslashes($_POST["tiempo"]);
    $tiempo_original = addslashes($_POST["tiempo_original"]); 
    $tiempo_minimo = floatval($tiempo_original) * 0.20;
        if (floatval($tiempo_p) < $tiempo_minimo) {
            $tiempo_p = strval($tiempo_minimo);
        }
    $id_viajeros_p = addslashes($_POST["id_viajeros"]);
    $mapa_p = addslashes($_POST["mapa"]);
    $barco_p = addslashes($_POST["barco"]);
    $navegante_npc_p = addslashes($_POST["navegante_npc"]);
    $fecha_salida_p = addslashes($_POST["fecha_salida"]);
    $fecha_llegada_p = addslashes($_POST["fecha_llegada"]);
    $temporada_p = addslashes($_POST["temporada"]);
    $post_inicio_viaje_p = addslashes($_POST["post_inicio_viaje"]);


    $islandsEastBlue = [
        "Isla de Rudra",
        "Isla de Dawn",
        "Refugio de Goat",
        "Islas Organ",
        "Isla Momobami",
        "DemonTooth",
        "Tequila Wolf",
        "Isla Kilombo",
        // "Baratie",
        "Islas Gecko",
        "Reino de Oykot",
        "Loguetown",
        "Sabana de Cozia",
        "Conomi Islands"
    ];

    $islandsNorthBlue = [
        "Isla de Korinaru",
        "Isla Tortuga",
        "Isla Swallow",
        "Libertalia",
        "Cliff Island",
        "Isla de Kuen",
        "Isla del Silencio",
        "Reino de Lvneel",
        "Baratie",
        "Flevance",
        "Isla de Rakesh",
        "Isla de Ivansk",
        "Skjodheilm",
        "Polo Norte"
    ];
    
    $islandsSouthBlue = [
        "Cliff",
        "Bawic",
        "Reino Black Drum",
        "Isla Kutsukku",
        "Reino de Sorbet",
        "Korinaru",
        "Rubeck",
        "Briss",
        "Libertalia"
    ];
    
  
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    $response[0] = array(
        'timestamp' => $timestamp
    );

    $uid_nombre = $ficha['nombre'];

    $tirada_random = rand(1, 20);
    $dado_naval = rand(1, 100);
    $dado_naval_str = strval($dado_naval);

    // $tirada_random = 20;

    if ($mar_p == 'East Blue') {
        $islands = $islandsEastBlue;

        $islands = array_diff($islands, [$partida_p]);
        $islands = array_diff($islands, [$llegada_p]);

        $tirada_islas = rand(1, 13);
    } else if ($mar_p == 'North Blue') {
        $islands = $islandsNorthBlue;

        $islands = array_diff($islands, [$partida_p]);
        $islands = array_diff($islands, [$llegada_p]);

        $tirada_islas = rand(1, 14);
    } else if ($mar_p == 'South Blue') {
        $islands = $islandsSouthBlue;

        $islands = array_diff($islands, [$partida_p]);
        $islands = array_diff($islands, [$llegada_p]);

        $tirada_islas = rand(1, 9);
    }

    if ($tirada_random == 1) {
        
        $nombre_isla_random = $islands[$tirada_islas];
        $resultado = "¡Ay, ay, ay, ay, ay! ¡Pifia! Con suerte has llegado a <strong>$nombre_isla_random</strong>.";
    } else if ($tirada_random == 20) {
        $resultado = '¡Has sacado un crítico. No solo llegas a la isla, pero todos en la tripulación reciben un Cofre Decente!';        
    } else if (($tirada_random + intval($modificador_p)) >= 10) {
        $resultado = '¡Sobreviviste y llegaste sano y salvo!';
    } else {
        $nombre_isla_random = $islands[$tirada_islas];
        $resultado = "No lograste llegar a tu destino, pero has sobrevivido. Pero por coincidencia llegaste a <strong>$nombre_isla_random</strong>.";
    }

    // Separar el string en un array
    $id_viajeros_array = explode(",", $id_viajeros_p);

    // Crear la base de la consulta SQL
    $query_ids = "SELECT * FROM `mybb_op_fichas` WHERE ";

    // Verificar si hay valores numéricos válidos
    $conditions = [];
    foreach ($id_viajeros_array as $fid) {
        if (is_numeric($fid)) { // Verificar si el valor es numérico

            if ($tirada_random == 20) {
                darObjetoFid('CFR002', $fid);
            }

            $query_recolectores = $db->query(" SELECT fid, nombre, oficio1, oficios FROM `mybb_op_fichas` WHERE oficios REGEXP '\"Recolector\": (1|2|3)' AND fid='$fid'; "); 
            while ($q = $db->fetch_array($query_recolectores)) { darObjetoFid('CRM003', $fid); }

            $conditions[] = "fid=" . intval($fid); // Convertir a entero y agregar a las condiciones
        }
    }

    if (!empty($conditions)) {
        // Si hay condiciones válidas, construir la consulta
        $query_ids .= implode(" OR ", $conditions);
    } 

    $query_ids_q = $db->query("$query_ids"); 

    $id_enteras = "";

    while ($q = $db->fetch_array($query_ids_q)) { 
        $nombre_q = $q['nombre'];
        $fid_q = $q['fid'];

        $id_enteras .= "$nombre_q ($fid_q), ";
        
    }
    $id_enteras = preg_replace('/,\s*$/', '', $id_enteras);

    $tiempo_viajado = date("d-m-Y H:i", $timestamp);
    
    $mapa_txt = '';
    if ($mapa_p) {
        $mapa_txt .= "Tienen un <strong>$mapa_p</strong>. ";
    }
    
    $navegante_npc_txt = '';
    if ($navegante_npc_p == 'Si') {
        $navegante_npc_txt = "Tienen un <strong>Navegante NPC</strong>. ";
    }

    $suceso = sucesoNaval($dado_naval);

    $log = "
        [Tirada de viaje realizada el: $tiempo_viajado] <br>
        <strong>$id_enteras</strong> navegan por el mar del <strong>$mar_p</strong> desde <strong>$partida</strong> hasta <strong>$llegada</strong>. <br>
        Salen de puerto el <strong>Día $fecha_salida_p de $temporada_p</strong> y llegan el <strong>Día $fecha_llegada_p de $temporada_p</strong>. <br>
        Están en un barco <strong>$barco_p</strong>. $mapa_txt$navegante_npc_txt<br>
        El viaje toma un transcurso de <strong>$tiempo_p</strong> horas. <br>
        La ventaja del modificador es <strong>+$modificador_p</strong> y la tirada de dado 1d20 ha sido <strong>$tirada_random</strong>. <br>
        $resultado <br>
        <strong>Suceso</strong>: $suceso ($dado_naval) <br>
        <strong>Post de inicio de viaje:</strong> $post_inicio_viaje_p";

    $db->query(" 
        INSERT INTO `mybb_op_avisos` (`uid`, `nombre`, `categoria`, `resumen`, `descripcion`, `url`) 
        VALUES ('$uid','$uid_nombre','viaje','Viaje Naval','$log','')
    ");

    $db->query(" 
    INSERT INTO `mybb_op_viajes`(`uid_viaje`, `nombre`, `mar`, `partida`, `llegada`, `dificultad`, `modificador`, `horas`, `viajeros`, 
    `fecha_salida`, `fecha_llegada`, `temporada`, `timestamp`, `log`, `postViaje`, `dado_naval`)
    VALUES ('$uid','$uid_nombre','$mar_p','$partida_p','$llegada_p','$dificultad_p','$modificador_p','$tiempo_p','$id_viajeros_p', 
    '$fecha_salida_p', '$fecha_llegada_p', '$temporada_p', '$timestamp', '$log', '$post_inicio_viaje_p', '$dado_naval')");

    echo json_encode($response); 
    return;
}

if ($accion == 'viajar_solitario') {
    $mar_p = addslashes($_POST["mar"]);
    $partida_p = addslashes($_POST["partida"]);
    $llegada_p = addslashes($_POST["llegada"]);
    $dificultad_p = addslashes($_POST["dificultad"]);
    $modificador_p = addslashes($_POST["modificador"]);
    $tiempo_p = addslashes($_POST["tiempo"]);
    $tiempo_original = addslashes($_POST["tiempo_original"]); 
    $tiempo_minimo = floatval($tiempo_original) * 0.20;
        if (floatval($tiempo_p) < $tiempo_minimo) {
            $tiempo_p = strval($tiempo_minimo);
        }
    $fecha_salida_p = addslashes($_POST["fecha_salida"]);
    $fecha_llegada_p = addslashes($_POST["fecha_llegada"]);
    $temporada_p = addslashes($_POST["temporada"]);
    $habilidad_transporte_p = addslashes($_POST["habilidad_transporte"]);
    $mapa_p = addslashes($_POST["mapa"]);
    $brujula_p = addslashes($_POST["brujula"]);
    $post_inicio_viaje_p = addslashes($_POST["post_inicio_viaje"]);

    $islandsEastBlue = [
        "Isla de Rudra", "Isla de Dawn", "Refugio de Goat", "Islas Organ", "Isla Momobami",
        "DemonTooth", "Tequila Wolf", "Isla Kilombo", "Islas Gecko", "Reino de Oykot",
        "Loguetown", "Sabana de Cozia", "Conomi Islands"
    ];

    $islandsNorthBlue = [
        "Isla de Korinaru", "Isla Tortuga", "Isla Swallow", "Libertalia", "Cliff Island",
        "Isla de Kuen", "Isla del Silencio", "Reino de Lvneel", "Baratie", "Flevance",
        "Isla de Rakesh", "Isla de Ivansk", "Skjodheilm", "Polo Norte"
    ];
    
    $islandsSouthBlue = [
        "Cliff", "Bawic", "Reino Black Drum", "Isla Kutsukku", "Reino de Sorbet",
        "Korinaru", "Rubeck", "Briss", "Libertalia"
    ];
    
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    $response[0] = array(
        'timestamp' => $timestamp
    );

    $uid_nombre = $ficha['nombre'];
    $tirada_random = rand(1, 20);

    if ($mar_p == 'East Blue') {
        $islands = $islandsEastBlue;
        $islands = array_diff($islands, [$partida_p]);
        $islands = array_diff($islands, [$llegada_p]);
        $tirada_islas = rand(0, count($islands) - 1);
    } else if ($mar_p == 'North Blue') {
        $islands = $islandsNorthBlue;
        $islands = array_diff($islands, [$partida_p]);
        $islands = array_diff($islands, [$llegada_p]);
        $tirada_islas = rand(0, count($islands) - 1);
    } else if ($mar_p == 'South Blue') {
        $islands = $islandsSouthBlue;
        $islands = array_diff($islands, [$partida_p]);
        $islands = array_diff($islands, [$llegada_p]);
        $tirada_islas = rand(0, count($islands) - 1);
    }

    if ($tirada_random == 1) {
        $islands_values = array_values($islands);
        $nombre_isla_random = $islands_values[$tirada_islas];
        if (strpos($habilidad_transporte_p, 'Nado') !== false || strpos($habilidad_transporte_p, 'ningyo') !== false || strpos($habilidad_transporte_p, 'gyojin') !== false || strpos($habilidad_transporte_p, 'marino') !== false) {
            $resultado = "¡Ay, ay, ay, ay, ay! ¡Pifia nadando! Te has perdido y con suerte has llegado a <strong>$nombre_isla_random</strong>.";
        } elseif (strpos($habilidad_transporte_p, 'Vuelo') !== false) {
            $resultado = "¡Ay, ay, ay, ay, ay! ¡Pifia volando! Has perdido el rumbo y con suerte has aterrizado en <strong>$nombre_isla_random</strong>.";
        } elseif (strpos($habilidad_transporte_p, 'Levitacion') !== false) {
            $resultado = "¡Ay, ay, ay, ay, ay! ¡Pifia levitando! Has perdido el control y con suerte has caído en <strong>$nombre_isla_random</strong>.";
        }
    } else if ($tirada_random == 20) {
        if (strpos($habilidad_transporte_p, 'Nado') !== false || strpos($habilidad_transporte_p, 'ningyo') !== false || strpos($habilidad_transporte_p, 'gyojin') !== false || strpos($habilidad_transporte_p, 'marino') !== false) {
            $resultado = '¡Has sacado un crítico nadando!';
        } elseif (strpos($habilidad_transporte_p, 'Vuelo') !== false) {
            $resultado = '¡Has sacado un crítico volando! ';
        } elseif (strpos($habilidad_transporte_p, 'Levitacion') !== false) {
            $resultado = '¡Has sacado un crítico levitando! ';
        }
        darObjetoFid('CFR002', $uid);        
    } else if (($tirada_random + intval($modificador_p)) >= 10) {
        if (strpos($habilidad_transporte_p, 'Nado') !== false || strpos($habilidad_transporte_p, 'ningyo') !== false || strpos($habilidad_transporte_p, 'gyojin') !== false || strpos($habilidad_transporte_p, 'marino') !== false) {
            $resultado = '¡Has nadado exitosamente y llegaste sano y salvo a tu destino!';
        } elseif (strpos($habilidad_transporte_p, 'Vuelo') !== false) {
            $resultado = '¡Has volado exitosamente y llegaste sano y salvo a tu destino!';
        } elseif (strpos($habilidad_transporte_p, 'Levitacion') !== false) {
            $resultado = '¡Has levitado exitosamente y llegaste sano y salvo a tu destino!';
        }
    } else {
        $islands_values = array_values($islands);
        $nombre_isla_random = $islands_values[$tirada_islas];
        if (strpos($habilidad_transporte_p, 'Nado') !== false || strpos($habilidad_transporte_p, 'ningyo') !== false || strpos($habilidad_transporte_p, 'gyojin') !== false || strpos($habilidad_transporte_p, 'marino') !== false) {
            $resultado = "No lograste llegar nadando a tu destino, pero has sobrevivido. Las corrientes te llevaron a <strong>$nombre_isla_random</strong>.";
        } elseif (strpos($habilidad_transporte_p, 'Vuelo') !== false) {
            $resultado = "No lograste llegar volando a tu destino, pero has sobrevivido. Los vientos te llevaron a <strong>$nombre_isla_random</strong>.";
        } elseif (strpos($habilidad_transporte_p, 'Levitacion') !== false) {
            $resultado = "No lograste llegar levitando a tu destino, pero has sobrevivido. Has caído en <strong>$nombre_isla_random</strong>.";
        }
    }

    // Dar recompensa de recolector si tiene el oficio
    $query_recolectores = $db->query(" SELECT fid, nombre, oficio1, oficios FROM `mybb_op_fichas` WHERE oficios REGEXP '\"Recolector\": (1|2|3)' AND fid='$uid'; "); 
    while ($q = $db->fetch_array($query_recolectores)) { 
        darObjetoFid('CRM003', $uid); 
    }

    $tiempo_viajado = date("d-m-Y H:i", $timestamp);
    
    $habilidad_txt = '';
    if ($habilidad_transporte_p && $habilidad_transporte_p != 'Nado normal') {
        $habilidad_txt = "Usando su habilidad de transporte: <strong>$habilidad_transporte_p</strong>. ";
    }
    
    $brujula_txt = '';
    if ($brujula_p && $brujula_p != 'Sin brújula') {
        $brujula_txt = "Con ayuda de: <strong>$brujula_p</strong>. ";
    }
    
    $mapa_txt = '';
    if ($mapa_p && $mapa_p != 'Sin mapa') {
        $mapa_txt = "Usando mapa: <strong>$mapa_p</strong>. ";
    }

    // Determinar el verbo de transporte según la habilidad
    $verbo_transporte = "viaja";
    $metodo_transporte = "viajando";
    
    if (strpos($habilidad_transporte_p, 'Nado') !== false || strpos($habilidad_transporte_p, 'ningyo') !== false || strpos($habilidad_transporte_p, 'gyojin') !== false || strpos($habilidad_transporte_p, 'marino') !== false) {
        $verbo_transporte = "nada";
        $metodo_transporte = "nadando";
    } elseif (strpos($habilidad_transporte_p, 'Vuelo') !== false) {
        $verbo_transporte = "vuela";
        $metodo_transporte = "volando";
    } elseif (strpos($habilidad_transporte_p, 'Levitacion') !== false) {
        $verbo_transporte = "levita";
        $metodo_transporte = "levitando";
    }

    $suceso = sucesoNaval($dado_naval);

    $log = "
        [Tirada de viaje solitario realizada el: $tiempo_viajado] <br>
        <strong>$uid_nombre</strong> $verbo_transporte por el mar del <strong>$mar_p</strong> desde <strong>$partida_p</strong> hasta <strong>$llegada_p</strong>. <br>
        Sale $metodo_transporte el <strong>Día $fecha_salida_p de $temporada_p</strong> y llega el <strong>Día $fecha_llegada_p de $temporada_p</strong>. <br>
        $habilidad_txt $mapa_txt $brujula_txt <br>
        El viaje $metodo_transporte toma un transcurso de <strong>$tiempo_p</strong> horas. <br>
        La ventaja del modificador es <strong>+$modificador_p</strong> y la tirada de dado 1d20 ha sido <strong>$tirada_random</strong>. <br>
        $resultado <br>
        <strong>Suceso</strong>: $suceso ($dado_naval)<br>
        <strong>Post de inicio de viaje:</strong> $post_inicio_viaje_p";

    $db->query(" 
        INSERT INTO `mybb_op_avisos` (`uid`, `nombre`, `categoria`, `resumen`, `descripcion`, `url`) VALUES ('$uid','$uid_nombre','viaje','Viaje Solitario','$log','')
    ");

    $db->query(" INSERT INTO `mybb_op_viajes`(`uid_viaje`, `nombre`, `mar`, `partida`, `llegada`, `dificultad`, `modificador`, `horas`, `viajeros`, `fecha_salida`, `fecha_llegada`, `temporada`, `timestamp`, `log`, `postViaje`, `dado_naval`) VALUES 
        ('$uid','$uid_nombre','$mar_p','$partida_p','$llegada_p','$dificultad_p','$modificador_p','$tiempo_p','$uid', '$fecha_salida_p', '$fecha_llegada_p', '$temporada_p', '$timestamp', '$log', '$post_inicio_viaje_p', '$dado_naval')");

    echo json_encode($response); 
    return;
}

$viajes_array = array();
$viajes_query = $db->query(" SELECT * FROM `mybb_op_viajes` ORDER BY ID DESC LIMIT 20; ");
while ($q = $db->fetch_array($viajes_query)) {
    
    array_push($viajes_array, $q);
}
$viajes_json = json_encode($viajes_array);


eval('$op_viajes_script .= "'.$templates->get('op_viajes_script').'";');
eval("\$page = \"".$templates->get("op_viajes")."\";");
output_page($page);
