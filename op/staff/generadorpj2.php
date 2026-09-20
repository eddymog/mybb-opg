<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'generadorpj2.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid) && !is_user($uid) && !is_narra($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

// JSON_HEX_TAG/AMP/APOS/QUOT: estos catálogos se embeben directo en un
// <script> del template (var x = {$x_json};). Sin estos flags, una
// descripción con "</script>" literal (staff copia/pega texto con HTML)
// cortaría el bloque de script — mismo tipo de bug que un XSS, aunque acá
// la fuente sea contenido de staff, no de un usuario público.
const GENERADORPJ2_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$query_virtudes = $db->query(" SELECT * FROM `mybb_op_virtudes` WHERE virtud_id LIKE 'V%' ORDER BY `mybb_op_virtudes`.`nombre` ASC; ");
$query_defectos = $db->query(" SELECT * FROM `mybb_op_virtudes` WHERE virtud_id LIKE 'D%' ORDER BY `mybb_op_virtudes`.`nombre` ASC; ");

$virtudes = array();
$defectos = array();

while ($virtud = $db->fetch_array($query_virtudes)) { array_push($virtudes, $virtud); }
while ($defecto = $db->fetch_array($query_defectos)) { array_push($defectos, $defecto); }

$virtudes_json = json_encode($virtudes, GENERADORPJ2_JSON_FLAGS);
$defectos_json = json_encode($defectos, GENERADORPJ2_JSON_FLAGS);

$query_objetos = $db->query(" SELECT * FROM `mybb_op_objetos` WHERE custom='0' ORDER BY categoria, subcategoria, tier, nombre ");
$objetos = array();
$objetos_array = array();

while ($q = $db->fetch_array($query_objetos)) {
    $objeto_id = $q['objeto_id'];
    $key = "$objeto_id";
    if (!$objetos[$key]) { $objetos[$key] = array(); }
    array_push($objetos[$key], $q);
    array_push($objetos_array, $objeto_id);
}

$objetos_array_json = json_encode($objetos_array, GENERADORPJ2_JSON_FLAGS);
$objetos_json = json_encode($objetos, GENERADORPJ2_JSON_FLAGS);

$query_tecnicas = $db->query(" SELECT * FROM mybb_op_tecnicas ORDER BY rama ASC ");
$tecnicas = array();
$tecnicas_array = array();

while ($q = $db->fetch_array($query_tecnicas)) {
    $tid = $q['tid'];
    $key = "$tid";
    if (!$tecnicas[$key]) { $tecnicas[$key] = array(); }
    array_push($tecnicas[$key], $q);
    array_push($tecnicas_array, $tid);
}

$tecnicas_array_json = json_encode($tecnicas_array, GENERADORPJ2_JSON_FLAGS);
$tecnicas_json = json_encode($tecnicas, GENERADORPJ2_JSON_FLAGS);

eval("\$page = \"".$templates->get("staff_generadorpj2")."\";");
output_page($page);
