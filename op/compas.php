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
define('THIS_SCRIPT', 'compas.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
global $templates, $mybb;

$query_npcs = $db->query(" SELECT * FROM `mybb_op_npcs` ORDER BY `mybb_op_npcs`.`nombre` ASC ");
// $query_npcs_usuarios = $db->query(" SELECT * FROM `mybb_op_npcs_usuarios` ORDER BY `mybb_op_npcs_usuarios`.`nombre` ASC ");
// $query_mascotas = $db->query(" SELECT * FROM `mybb_op_mascotas` ORDER BY `mybb_op_mascotas`.`nombre` ASC ");

$npcs = array();
// $npcs_usuarios = array();
// $mascotas = array();

$get_akuma = $_POST["get_akuma"];
$akuma_uid = $_POST["akuma_uid"];
$npc_input_id = $mybb->get_input('npc_id');
$action = $mybb->get_input('action');

// API para obtener datos de un NPC específico
if ($action == 'get_npc' && $npc_input_id) {
    header('Content-type: application/json');
    $npc_id = $db->escape_string($npc_input_id);
    $query_npc = $db->query("SELECT * FROM `mybb_op_npcs` WHERE `npc_id` = '$npc_id' LIMIT 1");
    
    if ($db->num_rows($query_npc) > 0) {
        $npc_data = $db->fetch_array($query_npc);
        
        // No mostrar información oculta (???)
        if (substr($npc_data['avatar1'], 0, 3) == "???") {
            $npc_data['avatar'] = '/images/op/misc/AvatarOculto_One_Piece_Gaiden_Foro_Rol.png';
        } else {
            $npc_data['avatar'] = $npc_data['avatar1'];
        }
        
        $response = array(
            'npc_id' => $npc_data['npc_id'],
            'nombre' => $npc_data['nombre'],
            'avatar' => $npc_data['avatar'],
            'color' => $npc_data['color'],
            'faccion' => $npc_data['faccion'],
            'rango' => $npc_data['rango'],
            'nivel' => $npc_data['nivel']
        );
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(array('error' => 'NPC no encontrado'));
    }
    return;
}

if ($get_akuma == 'true') {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();
    $akuma_post = null;

    if (substr($akuma_uid, 0, 3) == "???") {
        $response[0] = array(
            'nombre' => '¿?',
            'imagen' => '/images/op/uploads/AkumaMisteriosa_One_Piece_Gaiden_Foro_Rol.png',
            'timestamp' => $timestamp
        );
    } else {
        $query_akuma_post = $db->query(" SELECT * FROM mybb_op_akumas WHERE akuma_id='$akuma_uid' "); 
        while ($f = $db->fetch_array($query_akuma_post)) { $akuma_post = $f; }
    
        $response[0] = array(
            'nombre' => $akuma_post['nombre'],
            'imagen' => $akuma_post['imagen'],
            'timestamp' => $timestamp
        );
    }

    echo json_encode($response); 
    return;
}

// while ($q = $db->fetch_array($query_npcs_usuarios)) {
//     array_push($npcs_usuarios, $q);
//     array_push($npcs, $q);
// }

// while ($q = $db->fetch_array($query_mascotas)) {
//     array_push($mascotas, $q);
//     array_push($npcs, $q);
// }

while ($npc = $db->fetch_array($query_npcs)) {

    // if (substr($npc['faccion'], 0, 3) == "???") { $npc['faccion'] = '???'; }
    if (substr($npc['raza'], 0, 3) == "???") { $npc['raza'] = '???'; }
    if (substr($npc['altura'], 0, 3) == "???") { $npc['altura'] = '???'; }
    if (substr($npc['peso'], 0, 3) == "???") { $npc['peso'] = '???'; }
    if (substr($npc['sexo'], 0, 3) == "???") { $npc['sexo'] = '???'; }
    if (substr($npc['edad'], 0, 3) == "???") { $npc['edad'] = '???'; }
    if (substr($npc['temporada'], 0, 3) == "???") { $npc['temporada'] = '???'; }
    if (substr($npc['sangre'], 0, 3) == "???") { $npc['sangre'] = '???'; }
    if (substr($npc['rango'], 0, 3) == "???") { $npc['rango'] = '???'; }
    if (substr($npc['extra'], 0, 3) == "???") { $npc['extra'] = '???'; }

    if (substr($npc['nivel'], 0, 3) == "???") { $npc['nivel'] = '?'; }
    if (substr($npc['vitalidad'], 0, 3) == "???") { $npc['vitalidad'] = '???'; }
    if (substr($npc['energia'], 0, 3) == "???") { $npc['energia'] = '???'; }
    if (substr($npc['haki'], 0, 3) == "???") { $npc['haki'] = '???'; }


    if (substr($npc['avatar1'], 0, 3) == "???") { $npc['avatar1'] = '/images/op/misc/AvatarOculto_One_Piece_Gaiden_Foro_Rol.png'; }
    if (substr($npc['avatar2'], 0, 3) == "???") { $npc['avatar2'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png'; }

    if (substr($npc['apariencia'], 0, 3) == "???") { $npc['apariencia'] = '???'; }
    if (substr($npc['personalidad'], 0, 3) == "???") { $npc['personalidad'] = '???'; }
    if (substr($npc['historia1'], 0, 3) == "???") { $npc['historia1'] = '???'; }
    if (substr($npc['historia2'], 0, 3) == "???") { $npc['historia2'] = '???'; }
    if (substr($npc['historia3'], 0, 3) == "???") { $npc['historia3'] = '???'; }

    if (substr($npc['resistencia'], 0, 3) == "???") { $npc['resistencia'] = '???'; }
    if (substr($npc['fuerza'], 0, 3) == "???") { $npc['fuerza'] = '???'; }
    if (substr($npc['destreza'], 0, 3) == "???") { $npc['destreza'] = '???'; }
    if (substr($npc['agilidad'], 0, 3) == "???") { $npc['agilidad'] = '???'; }
    if (substr($npc['voluntad'], 0, 3) == "???") { $npc['voluntad'] = '???'; }
    if (substr($npc['control_akuma'], 0, 3) == "???") { $npc['control_akuma'] = '???'; }
    if (substr($npc['reflejos'], 0, 3) == "???") { $npc['reflejos'] = '???'; }
    if (substr($npc['punteria'], 0, 3) == "???") { $npc['punteria'] = '???'; }
    if (substr($npc['vitalidad'], 0, 3) == "???") { $npc['vitalidad'] = '???'; }
    if (substr($npc['energia'], 0, 3) == "???") { $npc['energia'] = '???'; }
    if (substr($npc['haki'], 0, 3) == "???") { $npc['haki'] = '???'; }

    if (substr($npc['hao'], 0, 3) == "???") { $npc['hao'] = '???'; }
    if (substr($npc['kenbun'], 0, 3) == "???") { $npc['kenbun'] = '???'; }
    if (substr($npc['buso'], 0, 3) == "???") { $npc['buso'] = '???'; }
    if (substr($npc['estilo_combate'], 0, 3) == "???") { $npc['estilo_combate'] = '???'; }

    array_push($npcs, $npc);
}

$npcs_json = json_encode($npcs);

// Verificar si el usuario tiene NPCs/mascotas propios (npc_id comienza con {uid}-)
$escaped_uid = $db->escape_string($g_uid);
$query_own_npc = $db->query("SELECT npc_id FROM mybb_op_npcs WHERE npc_id LIKE '{$escaped_uid}-%' LIMIT 1");
$g_has_own_npc = ($g_uid > 0 && $db->num_rows($query_own_npc) > 0);
// $npcs_usuarios_json = json_encode($npcs_usuarios);

$debugFile = dirname(__FILE__) . '/error_compas_debug.log';

function compas_debug_log($templateName, $templateContent, $e, $debugFile) {
    $errLine = $e->getLine();
    $lines = explode("\n", $templateContent);
    $start = max(0, $errLine - 4);
    $end   = min(count($lines) - 1, $errLine + 2);
    $snippet = '';
    for ($i = $start; $i <= $end; $i++) {
        $marker = ($i === $errLine - 1) ? ' >>> ' : '     ';
        $snippet .= $marker . ($i + 1) . ': ' . $lines[$i] . "\n";
    }
    $log = "[" . date('c') . "] TEMPLATE: {$templateName}\n"
         . "ERROR: " . $e->getMessage() . "\n"
         . "SNIPPET:\n" . $snippet . "\n";
    @file_put_contents($debugFile, $log, FILE_APPEND);
}

// Catálogo de disciplinas (BD, ver OPG Catálogos) para que op_compas_script.html
// genere su propia rejilla de disciplinas dinámicamente, igual que
// op/personaje.php hace para op_ficha_belico.html.
$op_disciplinas_catalogo_json = function_exists('op_ficha_disciplinas_catalogo')
    ? json_encode(op_ficha_disciplinas_catalogo(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '[]';

// Evaluar en el scope principal para que {$op_compas_css} y {$op_compas_script}
// estén disponibles cuando se evalúe op_compas.
try {
    $_tpl_css = $templates->get("op_compas_css");
    eval("\$op_compas_css = \"" . $_tpl_css . "\";");
} catch (Throwable $e) {
    compas_debug_log('op_compas_css', $_tpl_css, $e, $debugFile);
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    echo 'Internal Server Error.'; exit;
}

try {
    $_tpl_script = $templates->get("op_compas_script");
    eval("\$op_compas_script = \"" . $_tpl_script . "\";");
} catch (Throwable $e) {
    compas_debug_log('op_compas_script', $_tpl_script, $e, $debugFile);
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    echo 'Internal Server Error.'; exit;
}

try {
    $_tpl_compas = $templates->get("op_compas");
    eval("\$page = \"" . $_tpl_compas . "\";");
    output_page($page);
} catch (Throwable $e) {
    compas_debug_log('op_compas', $_tpl_compas, $e, $debugFile);
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    echo 'Internal Server Error.'; exit;
} 

// if ($g_is_staff) {
//     eval("\$page = \"".$templates->get("op_npcs")."\";");
//     output_page($page); 
// } else {
//     $mensaje_redireccion = "No puedes acceder a esta página aún.";
//     eval("\$page = \"".$templates->get("op_redireccion")."\";");
//     output_page($page);
// }


