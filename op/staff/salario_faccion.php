<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'salario_faccion.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$reload_js = "<script>window.location.href = window.location.pathname;</script>";

$salario = $_POST["salario"];

if ($salario == '1' && (is_mod($uid) || is_staff($uid))) {

    // Sueldos configurables desde Configuración del foro → OPG Facciones
    // (columna sueldo_semanal de cada rango) — antes un array fijo aquí mismo.
    if (!function_exists('op_facciones_sueldos_semanales')) {
        eval('$log_var = "No se pudo repartir: el plugin OPG Facciones no está activo.";');
        eval("\$page = \"".$templates->get("staff_salario_faccion")."\";");
        output_page($page);
        exit;
    }
    $salarios = op_facciones_sueldos_semanales();

    $rangos = implode("','", array_keys($salarios));
    $query_rango = $db->query(" SELECT * FROM mybb_op_fichas WHERE rango IN ('$rangos'); ");

    while ($q = $db->fetch_array($query_rango)) {
        $user_fid = $q['fid'];
        $faccion  = $q['faccion'];
        $berries  = intval($q['berries']) + $salarios[$q['rango']];
        log_audit_currency($user_uid, $username, $user_fid, "[Salarios][$faccion]", 'berries', $berries);
    }

    $fechaHora = date("Y-m-d H:i:s");

    $log = "[Salario Rangos] Última vez entregados por $username ($uid) a las: " . $fechaHora;

    $db->query(" 
        INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
        ('$staff', '$username', '$razon', '$log');
    ");
    
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if (is_mod($uid) || is_staff($uid)) { 

    $salarios_entregados = '';

    $query_salarios = $db->query("
        SELECT * FROM mybb_op_audit_consola_mod WHERE log LIKE '%[Salario Rangos]%' ORDER BY tiempo DESC LIMIT 10;
    ");
    while ($q = $db->fetch_array($query_salarios)) {
        
        $salarios_entregados .= $q['log'];
        $salarios_entregados .= "</br>";
    }
    
    eval("\$page = \"".$templates->get("staff_salario_faccion")."\";");
    output_page($page);
} else {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
}
