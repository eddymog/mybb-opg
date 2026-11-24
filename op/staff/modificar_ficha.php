<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'modificar_ficha.lphp');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/sg_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$user_fid = $mybb->get_input('fid'); 
$user_accion = $mybb->get_input('accion'); 

$ficha_id = addslashes($_POST["ficha_id"]);
$nombre = $_POST["nombre"];
$especializacion = $_POST["especializacion"];
$estilo = $_POST["estilo"];
$maestria_primaria = $_POST["maestria_primaria"];
$maestria_secundaria = $_POST["maestria_secundaria"];
$primer_elemento = $_POST["primer_elemento"];
$segundo_elemento = $_POST["segundo_elemento"];
$tercer_elemento = $_POST["tercer_elemento"];
$cuarto_elemento = $_POST["cuarto_elemento"];
$quinto_elemento = $_POST["quinto_elemento"];
$renunciar_elemento = $_POST["renunciar_elemento"];
$invo_primaria = $_POST["invo_primaria"];
$invo_secundaria = $_POST["invo_secundaria"];
$rango = $_POST["rango"];
$edad = $_POST["edad"];
$limite_nivel = $_POST["limite_nivel"];
$villa = $_POST["villa"];
$slots = $_POST["slots"];
$pasiva_slot = $_POST["pasiva_slot"];
$notas = $_POST["notas"];


$staff = addslashes($_POST["staff"]);
$razon = addslashes($_POST["razon"]);

$reload_js = "<script>window.location.href = window.location.pathname;</script>";

// Ficha modificada (issue #22): esta pagina hace muchas escrituras por peticion. Se lee la fila antes de la primera y
// despues de la ultima, y se envia UN solo aviso al final con todos los campos visibles que cambiaron. Nunca falla.
$aviso_ficha_antes = null;
if ($staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid)) && is_file(MYBB_ROOT . 'inc/plugins/lib/ficha_avisos.php')) {
    require_once MYBB_ROOT . 'inc/plugins/lib/ficha_avisos.php';
    $aviso_ficha_antes = opg_ficha_leer('mybb_sg_sg_fichas', (int) $ficha_id, opg_ficha_campos_modificar());
}

if ($especializacion && $estilo && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET espe='$especializacion', espe_estilo='$estilo' WHERE `fid`='$ficha_id';
    ");

    $db->query(" 
        INSERT INTO `mybb_sg_sg_tec_aprendidas`(`tid`, `uid`) VALUES ('ESPE$especializacion','$ficha_id');
    ");

    $db->query(" 
        INSERT INTO `mybb_sg_sg_tec_aprendidas`(`tid`, `uid`) VALUES ('ESTI$estilo','$ficha_id');
    ");

    $log = "Agregar especialización $especializacion y con estilo $estilo para UID: $ficha_id ($nombre).";

    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($maestria_primaria && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {

    if ($maestria_primaria == 'quitar') {
        $db->query(" 
            UPDATE `mybb_sg_sg_fichas` SET maestria='' WHERE `fid`='$ficha_id';
        ");
    } else {
        $db->query(" 
            UPDATE `mybb_sg_sg_fichas` SET maestria='$maestria_primaria' WHERE `fid`='$ficha_id';
        ");

        $db->query(" 
            INSERT INTO `mybb_sg_sg_tec_aprendidas`(`tid`, `uid`) VALUES ('$maestria_primaria','$ficha_id');
        ");
    }

    $log = "Agregar maestria primaria $maestria_primaria para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($maestria_secundaria && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    if ($maestria_secundaria == 'quitar') {
        $db->query(" 
            UPDATE `mybb_sg_sg_fichas` SET maestria_secundaria='' WHERE `fid`='$ficha_id';
        ");
    } else {
        $db->query(" 
            UPDATE `mybb_sg_sg_fichas` SET maestria_secundaria='$maestria_secundaria' WHERE `fid`='$ficha_id';
        ");

        $db->query(" 
            INSERT INTO `mybb_sg_sg_tec_aprendidas`(`tid`, `uid`) VALUES ('$maestria_secundaria','$ficha_id');
        ");
    }

    $log = "Agregar maestria secundaria $maestria_secundaria para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($primer_elemento && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET elemento1='$primer_elemento' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar primer elemento $primer_elemento para UID: $ficha_id ($nombre).";
    $db->query(" 
        INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
        ('$staff', '$razon', '$log');
    ");

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($segundo_elemento && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET elemento2='$segundo_elemento' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar segundo elemento $segundo_elemento para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($tercer_elemento && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET elemento3='$tercer_elemento' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar tercer elemento $tercer_elemento para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($cuarto_elemento && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET elemento4='$cuarto_elemento' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar cuarto elemento $cuarto_elemento para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($quinto_elemento && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET elemento5='$quinto_elemento' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar quinto elemento $quinto_elemento para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if (($renunciar_elemento == '1' || $renunciar_elemento == '0') && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET renunciar_elemento='$renunciar_elemento' WHERE `fid`='$ficha_id';
    ");

    $log = "Renunciar elemento extra $renunciar_elemento para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($invo_primaria && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET invocacion='$invo_primaria' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar invocación primaria $invo_primaria para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($invo_secundaria && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET invocacion_secundaria='$invo_secundaria' WHERE `fid`='$ficha_id';
    ");

    $log = "Agregar invocación secundaria $invo_secundaria para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($rango && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET rango='$rango' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar rango: $rango para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($edad && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET edad='$edad' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar edad: $edad para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($limite_nivel && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET limite_nivel='$limite_nivel' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar limite de nivel: $limite_nivel para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($villa && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET villa='$villa' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar villa: $villa para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if (($slots || $slots == '0') && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET slots='$slots' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar slots: $slots para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($pasiva_slot && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid)))  {

    if ($pasiva_slot == 'quitar') { $pasiva_slot = ''; }

    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET pasiva_slot='$pasiva_slot' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar pasiva de slot: $pasiva_slot para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($notas && $staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {
    $db->query(" 
        UPDATE `mybb_sg_sg_fichas` SET notas='$notas' WHERE `fid`='$ficha_id';
    ");

    $log = "Modificar nota de ficha privada: $notas para UID: $ficha_id ($nombre).";
    if (is_staff($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola` (`staff`, `razon`, `log`) VALUES 
            ('$staff', '$razon', '$log');
        ");
    }

    if (is_mod($uid)) {
        $db->query(" 
            INSERT INTO `mybb_sg_sg_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
            ('$staff', '$username', '$razon', '$log');
        ");
    }

    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if ($aviso_ficha_antes !== null) {
    opg_ficha_avisar_cambios((int) $ficha_id, $aviso_ficha_antes,
        opg_ficha_leer('mybb_sg_sg_fichas', (int) $ficha_id, opg_ficha_campos_modificar()), opg_ficha_campos_modificar(), $uid);
}

if (is_mod($uid) || is_staff($uid)) { 
    if ($user_fid != '') {
        $query_ficha = $db->query("
            SELECT * FROM mybb_sg_sg_fichas WHERE fid='$user_fid'
        ");
        while ($f = $db->fetch_array($query_ficha)) {
            $f_var = $f;
            eval('$ficha = $f_var;');
        }
    }

    eval('$fid = $user_fid;');
    eval('$accion = $user_accion;');
    eval("\$page = \"".$templates->get("staff_modificar_ficha")."\";");
    output_page($page);
} else {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
}
