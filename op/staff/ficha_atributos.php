<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ficha_atributos.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$user_fid = $mybb->get_input('fid'); 
$reload_js = "<script>window.location.href = window.location.pathname;</script>";
//$reload_js = "<script></script>";

$nombre = $_POST["nombre"];
$berries = $_POST["berries"];
$edad = $_POST["edad"];
$altura = $_POST["altura"];
$peso = $_POST["peso"];
$nika = $_POST["nika"];
$kuro = $_POST["kuro"];
$puntos_oficio = $_POST["puntos_oficio"];

$akuma = $_POST["akuma"];
$akuma_subnombre = $_POST["akuma_subnombre"];
$akuma_origen = in_array($_POST["akuma_origen"] ?? '', ['', 'aventura']) ? ($_POST["akuma_origen"] ?? '') : '';
$nivelnarrador = $_POST["nivelnarrador"];

$sexo = $_POST["sexo"];
$faccion = $_POST["faccion"];
$temporada = $_POST["temporada"];
$raza = $_POST["raza"];

$rango = $_POST["rango"];
$rango_inframundo = $_POST["rango_inframundo"];
$movidoInframundo = max(0, (int)($_POST["movidoInframundo"] ?? 0));
$fama = $_POST["fama"];

$camino = $_POST["camino"];
$dia = $_POST["dia"];

$cronologia = $_POST["cronologia"];

$secret1 = $_POST["secret1"];

// $s1_nombre = $_POST["s1_nombre"];
// $s1_apodo = $_POST["s1_apodo"];
// $s1_faccion = $_POST["s1_faccion"];
// $s1_apariencia = $_POST["s1_apariencia"];
// $s1_personalidad = $_POST["s1_personalidad"];
// $s1_historia = $_POST["s1_historia"];
// $s1_extra = $_POST["s1_extra"];
// $s1_rango = $_POST["s1_rango"];
// $s1_avatar1 = $_POST["s1_avatar1"];
// $s1_avatar2 = $_POST["s1_avatar2"];

$oficio1 = $_POST["oficio1"];
$oficio2 = $_POST["oficio2"];
$belica1 = $_POST["belica1"];
$belica2 = $_POST["belica2"];
$belica3 = $_POST["belica3"];
$belica4 = $_POST["belica4"];
$belica5 = $_POST["belica5"];
$belica6 = $_POST["belica6"];
$belica7 = $_POST["belica7"];
$belica8 = $_POST["belica8"];
$belica9 = $_POST["belica9"];
$belica10 = $_POST["belica10"];
$belica11 = $_POST["belica11"];
$belica12 = $_POST["belica12"];

$fx = $_POST["fx"];
$wantedGuardado = $_POST["wantedGuardado"];
$wantedcustom   = max(0, (int)$_POST["wantedcustom"]);

$estilo1 = $_POST["estilo1"];
$estilo2 = $_POST["estilo2"];
$estilo3 = $_POST["estilo3"];
$estilo4 = $_POST["estilo4"];

$belicas = $_POST["belicas"];
$oficios = $_POST["oficios"];
$elementos = $_POST["elementos"];

$fisico_de_pj = $_POST["fisico_de_pj"];
$origen_de_pj = $_POST["origen_de_pj"];

$kenbun = $_POST["kenbun"];
$buso = $_POST["buso"];
$hao = $_POST["hao"];
$hao_chance = $_POST["hao_chance"];
$dominio_akuma = $_POST["dominio_akuma"];

$reputacion = $_POST["reputacion"];
$reputacion_positiva = $_POST["reputacion_positiva"];
$reputacion_negativa = $_POST["reputacion_negativa"];

$reputacion2 = $_POST["reputacion2"];
$reputacion_positiva2 = $_POST["reputacion_positiva2"];
$reputacion_negativa2 = $_POST["reputacion_negativa2"];

$wanted_repu = $_POST["wanted_repu"];

$wanted = $_POST["wanted"];
$muerto = $_POST["muerto"];

$puntos_experiencia = $_POST["puntos_experiencia"];
$puntos_estadistica = $_POST["puntos_estadistica"];
$nivel = $_POST["nivel"];

// $vitalidad = $_POST["vitalidad"];
// $energia = $_POST["energia"];
// $haki = $_POST["haki"];

$fuerza = $_POST["fuerza"];
$resistencia = $_POST["resistencia"];
$destreza = $_POST["destreza"];
$voluntad = $_POST["voluntad"];
$punteria = $_POST["punteria"];
$agilidad = $_POST["agilidad"];
$reflejos = $_POST["reflejos"];
$control_akuma = $_POST["control_akuma"];

$vitalidad_pasiva = $_POST["vitalidad_pasiva"];
$energia_pasiva = $_POST["energia_pasiva"];
$haki_pasiva = $_POST["haki_pasiva"];

$fuerza_pasiva = $_POST["fuerza_pasiva"];
$resistencia_pasiva = $_POST["resistencia_pasiva"];
$destreza_pasiva = $_POST["destreza_pasiva"];
$voluntad_pasiva = $_POST["voluntad_pasiva"];
$punteria_pasiva = $_POST["punteria_pasiva"];
$agilidad_pasiva = $_POST["agilidad_pasiva"];
$reflejos_pasiva = $_POST["reflejos_pasiva"];
$control_akuma_pasiva = $_POST["control_akuma_pasiva"];


$ranuras = $_POST["ranuras"];
$implantes = $_POST["implantes"];
$equipamiento_espacio = $_POST["equipamiento-espacio"];
$equipamiento = $_POST["equipamiento"];

$staff = $uid;
$razon = $_POST["razon"];
$ficha_id = $_POST["ficha_id"];

if ($staff && $razon && $ficha_id && (is_mod($uid) || is_staff($uid))) {

    $query_ficha = $db->query("
        SELECT * FROM mybb_op_fichas WHERE fid='$ficha_id'
    ");
    while ($f = $db->fetch_array($query_ficha)) {
        $f_var = $f;
    }
    $query_usuario = $db->query("
        SELECT * FROM mybb_users WHERE uid='$ficha_id'
    ");
    while ($u = $db->fetch_array($query_usuario)) {
        $u_var = $u;
    }

    $log = "Cambios de atributos para ficha de UID: $ficha_id (" . $f_var['nombre'] . "):\n";

    if ($nombre != $f_var['nombre']) {
        $log .= "-- De ".$f_var['nombre']." a $nombre nombre.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET nombre='$nombre' WHERE `fid`='$ficha_id'; ");
    }

    if ($berries != $f_var['berries']) {
        $log .= "-- De ".$f_var['berries']." a $berries berries.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET berries='$berries' WHERE `fid`='$ficha_id'; ");

        log_audit_currency($uid, $username, $ficha_id, '[Modificación de berries]', 'berries', $berries);
    }

    if ($edad != $f_var['edad']) {
        $log .= "-- De ".$f_var['edad']." a $edad edad.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET edad='$edad' WHERE `fid`='$ficha_id'; ");
    }

    if ($altura != $f_var['altura']) {
        $log .= "-- De ".$f_var['altura']." a $altura altura.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET altura='$altura' WHERE `fid`='$ficha_id'; ");
    }

    if ($peso != $f_var['peso']) {
        $log .= "-- De ".$f_var['peso']." a $peso peso.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET peso='$peso' WHERE `fid`='$ficha_id'; ");
    }
    
    if ($nika != $f_var['nika']) {
        $log .= "-- De ".$f_var['nika']." a $nika nika.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET nika='$nika' WHERE `fid`='$ficha_id'; ");

        log_audit_currency($uid, $username, $ficha_id, '[Modificación de nikas]', 'nika', $nika);
    }

    if ($kuro != $f_var['kuro']) {
        $log .= "-- De ".$f_var['kuro']." a $kuro kuro.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET kuro='$kuro' WHERE `fid`='$ficha_id'; ");
        
        log_audit_currency($uid, $username, $ficha_id, '[Modificación de kuros]', 'kuro', $kuro);
    }

    if ($puntos_oficio != $f_var['puntos_oficio']) {
        $log .= "-- De ".$f_var['puntos_oficio']." a $puntos_oficio puntos_oficio.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET puntos_oficio='$puntos_oficio' WHERE `fid`='$ficha_id'; ");

        log_audit_currency($uid, $username, $ficha_id, '[Modificación de puntos de oficio]', 'puntos_oficio', $puntos_oficio);
    }

    if ($akuma != $f_var['akuma']) {
        $log .= "-- De ".$f_var['akuma']." a $akuma akuma.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET akuma='$akuma' WHERE `fid`='$ficha_id'; ");
    }

    if ($akuma_subnombre != $f_var['akuma_subnombre']) {
        $log .= "-- De ".$f_var['akuma_subnombre']." a $akuma_subnombre akuma_subnombre.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET akuma_subnombre='$akuma_subnombre' WHERE `fid`='$ficha_id'; ");
    }

    if ($akuma_origen != ($f_var['akuma_origen'] ?? '')) {
        $log .= "-- De ".($f_var['akuma_origen'] ?? '')." a $akuma_origen akuma_origen.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET akuma_origen='$akuma_origen' WHERE `fid`='$ficha_id'; ");
    }

    if ($nivelnarrador != $f_var['nivelnarrador']) {
        $log .="-- De " .$f_var['nivelnarrador']." a $nivelnarrador nivelnarrador.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET nivelnarrador='$nivelnarrador' WHERE `fid`='$ficha_id'; ");
    }

    if ($rango != $f_var['rango']) {
        $log .= "-- De ".$f_var['rango']." a $rango rango.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET rango='$rango' WHERE `fid`='$ficha_id'; ");
    }

    if ($rango_inframundo != $f_var['rango_inframundo']) {
        $log .= "-- De ".$f_var['rango_inframundo']." a $rango_inframundo rango_inframundo.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET rango_inframundo='$rango_inframundo' WHERE `fid`='$ficha_id'; ");
    }

    if ($movidoInframundo != (int)($f_var['movidoInframundo'] ?? 0)) {
        $log .= "-- De ".($f_var['movidoInframundo'] ?? 0)." a $movidoInframundo movidoInframundo.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET movidoInframundo='$movidoInframundo' WHERE `fid`='$ficha_id'; ");
        log_audit_currency($uid, $username, $ficha_id, '[Modificación de movidoInframundo]', 'movidoInframundo', $movidoInframundo);
    }

    if ($fama != $f_var['fama']) {
        $log .= "-- De ".$f_var['fama']." a $fama fama.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET fama='$fama' WHERE `fid`='$ficha_id'; ");
    }

    if ($cronologia != $f_var['cronologia']) {
        $log .= "-- De ".$f_var['cronologia']." a $cronologia cronologia.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET cronologia='$cronologia' WHERE `fid`='$ficha_id'; ");
    }

    if ($sexo != $f_var['sexo']) {
        $log .= "-- De ".$f_var['sexo']." a $sexo sexo.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET sexo='$sexo' WHERE `fid`='$ficha_id'; ");
    }

    if ($faccion != $f_var['faccion']) {
        $log .= "-- De ".$f_var['faccion']." a $faccion faccion.\n";

        // El grupo de MyBB asociado a cada facción se gestiona ahora desde
        // Admin CP → Configuración del foro → OPG Facciones (columna
        // "usergroup" de mybb_op_facciones). Si la tabla no existiera por lo
        // que sea, cae al mapeo fijo de siempre para no dejar de funcionar.
        $faccion_usergroup = 0;
        if ($db->table_exists('op_facciones')) {
            $faccion_usergroup = (int)$db->fetch_field($db->simple_select('op_facciones', 'usergroup', "nombre='".$db->escape_string($faccion)."'"), 'usergroup');
        } else {
            $legacy_usergroups = array(
                'Pirata' => 8, 'Marina' => 9, 'CipherPol' => 11,
                'Revolucionario' => 12, 'Cazadores' => 10, 'Civil' => 13,
            );
            if (isset($legacy_usergroups[$faccion])) {
                $faccion_usergroup = $legacy_usergroups[$faccion];
            }
        }

        if ($faccion_usergroup > 0) {
            $db->query(" UPDATE `mybb_users` SET usergroup='{$faccion_usergroup}' WHERE `uid`='$ficha_id'; ");
        }

        $db->query(" UPDATE `mybb_op_fichas` SET faccion='$faccion' WHERE `fid`='$ficha_id'; ");
    }

    if ($temporada != $f_var['temporada']) {
        $log .= "-- De ".$f_var['temporada']." a $temporada temporada.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET temporada='$temporada' WHERE `fid`='$ficha_id'; ");
    }

    if ($camino != $f_var['camino']) {
        $log .= "-- De ".$f_var['camino']." a $camino camino.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET camino='$camino' WHERE `fid`='$ficha_id'; ");
    }

    if ($dia != $f_var['dia']) {
        $log .= "-- De ".$f_var['dia']." a $dia dia.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET dia='$dia' WHERE `fid`='$ficha_id'; ");
    }

    if ($raza != $f_var['raza']) {
        $log .= "-- De ".$f_var['raza']." a $raza raza.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET raza='$raza' WHERE `fid`='$ficha_id'; ");
    }
    

    if ($kenbun != $f_var['kenbun']) {
        $log .= "-- De ".$f_var['kenbun']." a $kenbun kenbun.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET kenbun='$kenbun' WHERE `fid`='$ficha_id'; ");
    }

    if ($buso != $f_var['buso']) {
        $log .= "-- De ".$f_var['buso']." a $buso buso.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET buso='$buso' WHERE `fid`='$ficha_id'; ");
    }

    if ($hao != $f_var['hao']) {
        $log .= "-- De ".$f_var['hao']." a $hao hao.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET hao='$hao' WHERE `fid`='$ficha_id'; ");
    }

    if ($dominio_akuma != $f_var['dominio_akuma']) {
        $log .= "-- De ".$f_var['dominio_akuma']." a $dominio_akuma dominio_akuma.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET dominio_akuma='$dominio_akuma' WHERE `fid`='$ficha_id'; ");
    }

    if ($hao_chance != $f_var['hao_chance']) {
        $log .= "-- De ".$f_var['hao_chance']." a $hao_chance hao_chance.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET hao_chance='$hao_chance' WHERE `fid`='$ficha_id'; ");
        echo $f_var['hao_chance'];
    }

    if ($wanted != $f_var['wanted']) {
        $log .= "-- De ".$f_var['wanted']." a $wanted wanted.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET wanted='$wanted' WHERE `fid`='$ficha_id'; ");
    }

    if ($reputacion != $f_var['reputacion']) {
        $log .= "-- De ".$f_var['reputacion']." a $reputacion reputacion.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reputacion='$reputacion' WHERE `fid`='$ficha_id'; ");
    }

    if ($reputacion_positiva != $f_var['reputacion_positiva']) {
        $log .= "-- De ".$f_var['reputacion_positiva']." a $reputacion_positiva reputacion_positiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reputacion_positiva='$reputacion_positiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($reputacion_negativa != $f_var['reputacion_negativa']) {
        $log .= "-- De ".$f_var['reputacion_negativa']." a $reputacion_negativa reputacion_negativa.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reputacion_negativa='$reputacion_negativa' WHERE `fid`='$ficha_id'; ");
    }

    if ($reputacion2 != $f_var['reputacion2']) {
        $log .= "-- De ".$f_var['reputacion2']." a $reputacion2 reputacion2.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reputacion2='$reputacion2' WHERE `fid`='$ficha_id'; ");
    }

    if ($reputacion_positiva2 != $f_var['reputacion_positiva2']) {
        $log .= "-- De ".$f_var['reputacion_positiva2']." a $reputacion_positiva2 reputacion_positiva2.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reputacion_positiva2='$reputacion_positiva2' WHERE `fid`='$ficha_id'; ");
    }

    if ($reputacion_negativa2 != $f_var['reputacion_negativa2']) {
        $log .= "-- De ".$f_var['reputacion_negativa2']." a $reputacion_negativa2 reputacion_negativa2.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reputacion_negativa2='$reputacion_negativa2' WHERE `fid`='$ficha_id'; ");
    }

    if ($wanted_repu != $f_var['wanted_repu']) {
        $log .= "-- De ".$f_var['wanted_repu']." a $wanted_repu wanted_repu.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET wanted_repu='$wanted_repu' WHERE `fid`='$ficha_id'; ");
    }

    if ($secret1 != $f_var['secret1']) {
        $log .= "-- De ".$f_var['secret1']." a $secret1 secret1.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET secret1='$secret1' WHERE `fid`='$ficha_id'; ");
    }

    if ($oficio1 != $f_var['oficio1']) {

        //if ($oficio1 == 'sin_oficio') { $oficio1 = ''; }
        $log .= "-- De ".$f_var['oficio1']." a $oficio1 oficio1.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET oficio1='$oficio1' WHERE `fid`='$ficha_id'; ");
    }

    if ($fisico_de_pj != $f_var['fisico_de_pj']) {
        $log .= "-- De ".$f_var['fisico_de_pj']." a $fisico_de_pj fisico_de_pj.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET fisico_de_pj='$fisico_de_pj' WHERE `fid`='$ficha_id'; ");
    }

    if ($origen_de_pj != $f_var['origen_de_pj']) {
        $log .= "-- De ".$f_var['origen_de_pj']." a $origen_de_pj origen_de_pj.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET origen_de_pj='$origen_de_pj' WHERE `fid`='$ficha_id'; ");
    }

    if ($oficio2 != $f_var['oficio2']) {
        //if ($oficio2 == 'sin_oficio') { $oficio2 = ''; }
        $log .= "-- De ".$f_var['oficio2']." a $oficio2 oficio2.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET oficio2='$oficio2' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica1 != $f_var['belica1']) {
        $log .= "-- De ".$f_var['belica1']." a $belica1 belica1.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica1='$belica1' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica2 != $f_var['belica2']) {
        $log .= "-- De ".$f_var['belica2']." a $belica2 belica2.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica2='$belica2' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica3 != $f_var['belica3']) {
        $log .= "-- De ".$f_var['belica3']." a $belica3 belica3.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica3='$belica3' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica4 != $f_var['belica4']) {
        $log .= "-- De ".$f_var['belica4']." a $belica4 belica4.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica4='$belica4' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica5 != $f_var['belica5']) {
        $log .= "-- De ".$f_var['belica5']." a $belica5 belica5.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica5='$belica5' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica6 != $f_var['belica6']) {
        $log .= "-- De ".$f_var['belica6']." a $belica6 belica6.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica6='$belica6' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica7 != $f_var['belica7']) {
        $log .= "-- De ".$f_var['belica7']." a $belica7 belica7.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica7='$belica7' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica8 != $f_var['belica8']) {
        $log .= "-- De ".$f_var['belica8']." a $belica8 belica8.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica8='$belica8' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica9 != $f_var['belica9']) {
        $log .= "-- De ".$f_var['belica9']." a $belica9 belica9.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica9='$belica9' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica10 != $f_var['belica10']) {
        $log .= "-- De ".$f_var['belica10']." a $belica10 belica10.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica10='$belica10' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica11 != $f_var['belica11']) {
        $log .= "-- De ".$f_var['belica11']." a $belica11 belica11.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica11='$belica11' WHERE `fid`='$ficha_id'; ");
    }

    if ($belica12 != $f_var['belica12']) {
        $log .= "-- De ".$f_var['belica12']." a $belica12 belica12.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belica12='$belica12' WHERE `fid`='$ficha_id'; ");
    }

    if ($estilo1 != $f_var['estilo1']) {
        $log .= "-- De ".$f_var['estilo1']." a $estilo1 estilo1.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET estilo1='$estilo1' WHERE `fid`='$ficha_id'; ");
    }

    if ($estilo2 != $f_var['estilo2']) {
        $log .= "-- De ".$f_var['estilo2']." a $estilo2 estilo2.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET estilo2='$estilo2' WHERE `fid`='$ficha_id'; ");
    }

    if ($estilo3 != $f_var['estilo3']) {
        $log .= "-- De ".$f_var['estilo3']." a $estilo3 estilo3.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET estilo3='$estilo3' WHERE `fid`='$ficha_id'; ");
    }
    
    if ($estilo4 != $f_var['estilo4']) {
        $log .= "-- De ".$f_var['estilo4']." a $estilo4 estilo4.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET estilo4='$estilo4' WHERE `fid`='$ficha_id'; ");
    }

    if ($oficios != $f_var['oficios']) {
        // Limpiar especializaciones vacías de la estructura JSON
        $oficios_decoded = json_decode($oficios, true);
        if ($oficios_decoded !== null && is_array($oficios_decoded)) {
            foreach ($oficios_decoded as $oficio_key => &$oficio_data) {
                if (isset($oficio_data['espe1']) && $oficio_data['espe1'] === '') {
                    unset($oficio_data['espe1']);
                }
                if (isset($oficio_data['espe2']) && $oficio_data['espe2'] === '') {
                    unset($oficio_data['espe2']);
                }
                if (array_key_exists('sub', $oficio_data) && is_array($oficio_data['sub'])) {
                    $oficio_data['sub'] = (object)$oficio_data['sub'];
                }
            }
            $oficios = json_encode($oficios_decoded, JSON_UNESCAPED_UNICODE);
        }
        
        $log .= "-- De ".$f_var['oficios']." a $oficios oficios.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET oficios='".$db->escape_string($oficios)."' WHERE `fid`='$ficha_id'; ");
    }

    if ($belicas != $f_var['belicas']) {
        // Limpiar especializaciones vacías de la estructura JSON
        $belicas_decoded = json_decode($belicas, true);
        if ($belicas_decoded !== null && is_array($belicas_decoded)) {
            foreach ($belicas_decoded as $belica_key => &$belica_data) {
                if (isset($belica_data['espe1']) && $belica_data['espe1'] === '') {
                    unset($belica_data['espe1']);
                }
                if (isset($belica_data['espe2']) && $belica_data['espe2'] === '') {
                    unset($belica_data['espe2']);
                }
            }
            $belicas = json_encode($belicas_decoded, JSON_UNESCAPED_UNICODE);
        }
        
        $log .= "-- De ".$f_var['belicas']." a $belicas belicas.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET belicas='".$db->escape_string($belicas)."' WHERE `fid`='$ficha_id'; ");
    }

    if ($elementos != $f_var['elementos']) {
        $log .= "-- De ".$f_var['elementos']." a $elementos elementos.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET elementos='$elementos' WHERE `fid`='$ficha_id'; ");
    }

    if ($puntos_estadistica != $f_var['puntos_estadistica']) {
        $log .= "-- De ".$f_var['puntos_estadistica']." a $puntos_estadistica puntos_estadistica.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET puntos_estadistica='$puntos_estadistica' WHERE `fid`='$ficha_id'; ");
    }

    if ($nivel != $f_var['nivel']) {
        $log .= "-- De ".$f_var['nivel']." a $nivel nivel.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET nivel='$nivel' WHERE `fid`='$ficha_id'; ");
    }

    if ($ranuras != $f_var['ranuras']) {
        $log .= "-- De ".$f_var['ranuras']." a $ranuras ranuras.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET ranuras='$ranuras' WHERE `fid`='$ficha_id'; ");
    }

    if ($implantes != $f_var['implantes']) {
        $log .= "-- De ".$f_var['implantes']." a $implantes implantes.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET implantes='$implantes' WHERE `fid`='$ficha_id'; ");
    }

    if ($fx != $f_var['fx']) {
        $log .= "-- De ".$f_var['fx']." a $fx fx.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET fx='$fx' WHERE `fid`='$ficha_id'; ");
    }

    if ($wantedGuardado != $f_var['wantedGuardado']) {
        $log .= "-- De ".$f_var['wantedGuardado']." a $wantedGuardado wantedGuardado.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET wantedGuardado='$wantedGuardado' WHERE `fid`='$ficha_id'; ");
    }

    if ($wantedcustom != (int)$f_var['wantedcustom']) {
        $log .= "-- De ".(int)$f_var['wantedcustom']." a $wantedcustom wantedcustom.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET wantedcustom='$wantedcustom' WHERE `fid`='$ficha_id'; ");
    }

    // Valor base: lo que está en la ficha sin bonus
    $base_espacios = $f_var['equipamiento_espacio'];

    if ($equipamiento_espacio != $f_var['equipamiento_espacio']) {
        $log .= "-- De ".$f_var['equipamiento_espacio']." a $equipamiento_espacio equipamiento_espacio.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET equipamiento_espacio='$equipamiento_espacio' WHERE `fid`='$ficha_id'; ");
    }

    if ($equipamiento != $f_var['equipamiento']) {
        $log .= "-- De ".$f_var['equipamiento']." a $equipamiento equipamiento.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET equipamiento='$equipamiento' WHERE `fid`='$ficha_id'; ");
    }

    if ($muerto != $f_var['muerto']) {
        $log .= "-- De ".$f_var['muerto']." a $muerto muerto.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET muerto='$muerto' WHERE `fid`='$ficha_id'; ");
    }

    if ($puntos_experiencia != $u_var['newpoints']) {
        $log .= "-- De ".$u_var['newpoints']." a $puntos_experiencia experiencia.\n";
        $db->query(" UPDATE `mybb_users` SET newpoints='$puntos_experiencia' WHERE `uid`='$ficha_id'; "); 
        
        log_audit_currency($uid, $username, $ficha_id, '[Modificación de experiencia]', 'experiencia', $puntos_experiencia);
    }

    // if ($vitalidad != $f_var['vitalidad']) {
    //     $log .= "-- De ".$f_var['vitalidad']." a $vitalidad vitalidad.\n";
    //     $db->query(" UPDATE `mybb_op_fichas` SET vitalidad='$vitalidad' WHERE `fid`='$ficha_id'; ");
    // }

    // if ($energia != $f_var['energia']) {
    //     $log .= "-- De ".$f_var['energia']." a $energia energia.\n";
    //     $db->query(" UPDATE `mybb_op_fichas` SET energia='$energia' WHERE `fid`='$ficha_id'; ");
    // }

    // if ($haki != $f_var['haki']) {
    //     $log .= "-- De ".$f_var['haki']." a $haki haki.\n";
    //     $db->query(" UPDATE `mybb_op_fichas` SET haki='$haki' WHERE `fid`='$ficha_id'; ");
    // }

    if ($vitalidad_pasiva != $f_var['vitalidad_pasiva']) {
        $log .= "-- De ".$f_var['vitalidad_pasiva']." a $vitalidad_pasiva vitalidad_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET vitalidad_pasiva='$vitalidad_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($energia_pasiva != $f_var['energia_pasiva']) {
        $log .= "-- De ".$f_var['energia_pasiva']." a $energia_pasiva energia_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET energia_pasiva='$energia_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($haki_pasiva != $f_var['haki_pasiva']) {
        $log .= "-- De ".$f_var['haki_pasiva']." a $haki_pasiva haki_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET haki_pasiva='$haki_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($fuerza != $f_var['fuerza']) {
        $log .= "-- De ".$f_var['fuerza']." a $fuerza fuerza.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET fuerza='$fuerza' WHERE `fid`='$ficha_id'; ");
    }

    if ($resistencia != $f_var['resistencia']) {
        $log .= "-- De ".$f_var['resistencia']." a $resistencia resistencia.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET resistencia='$resistencia' WHERE `fid`='$ficha_id'; ");
    }

    if ($destreza != $f_var['destreza']) {
        $log .= "-- De ".$f_var['destreza']." a $destreza destreza.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET destreza='$destreza' WHERE `fid`='$ficha_id'; ");
    }

    if ($voluntad != $f_var['voluntad']) {
        $log .= "-- De ".$f_var['voluntad']." a $voluntad voluntad.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET voluntad='$voluntad' WHERE `fid`='$ficha_id'; ");
    }

    if ($punteria != $f_var['punteria']) {
        $log .= "-- De ".$f_var['punteria']." a $punteria punteria.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET punteria='$punteria' WHERE `fid`='$ficha_id'; ");
    }

    if ($agilidad != $f_var['agilidad']) {
        $log .= "-- De ".$f_var['agilidad']." a $agilidad agilidad.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET agilidad='$agilidad' WHERE `fid`='$ficha_id'; ");
    }

    if ($reflejos != $f_var['reflejos']) {
        $log .= "-- De ".$f_var['reflejos']." a $reflejos reflejos.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reflejos='$reflejos' WHERE `fid`='$ficha_id'; ");
    }

    if ($control_akuma != $f_var['control_akuma']) {
        $log .= "-- De ".$f_var['control_akuma']." a $control_akuma control_akuma.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET control_akuma='$control_akuma' WHERE `fid`='$ficha_id'; ");
    }


    if ($fuerza_pasiva != $f_var['fuerza_pasiva']) {
        $log .= "-- De ".$f_var['fuerza_pasiva']." a $fuerza_pasiva fuerza_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET fuerza_pasiva='$fuerza_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($resistencia_pasiva != $f_var['resistencia_pasiva']) {
        $log .= "-- De ".$f_var['resistencia_pasiva']." a $resistencia_pasiva resistencia_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET resistencia_pasiva='$resistencia_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($destreza_pasiva != $f_var['destreza_pasiva']) {
        $log .= "-- De ".$f_var['destreza_pasiva']." a $destreza_pasiva destreza_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET destreza_pasiva='$destreza_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($voluntad_pasiva != $f_var['voluntad_pasiva']) {
        $log .= "-- De ".$f_var['voluntad_pasiva']." a $voluntad_pasiva voluntad_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET voluntad_pasiva='$voluntad_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($punteria_pasiva != $f_var['punteria_pasiva']) {
        $log .= "-- De ".$f_var['punteria_pasiva']." a $punteria_pasiva punteria_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET punteria_pasiva='$punteria_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($agilidad_pasiva != $f_var['agilidad_pasiva']) {
        $log .= "-- De ".$f_var['agilidad_pasiva']." a $agilidad_pasiva agilidad_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET agilidad_pasiva='$agilidad_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($reflejos_pasiva != $f_var['reflejos_pasiva']) {
        $log .= "-- De ".$f_var['reflejos_pasiva']." a $reflejos_pasiva reflejos_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET reflejos_pasiva='$reflejos_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    if ($control_akuma_pasiva != $f_var['control_akuma_pasiva']) {
        $log .= "-- De ".$f_var['control_akuma_pasiva']." a $control_akuma_pasiva control_akuma_pasiva.\n";
        $db->query(" UPDATE `mybb_op_fichas` SET control_akuma_pasiva='$control_akuma_pasiva' WHERE `fid`='$ficha_id'; ");
    }

    $db->query(" 
        INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES 
        ('$staff', '$username', '$razon', '$log');
    ");
    
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

if (is_mod($uid) || is_staff($uid)) { 
    if ($user_fid != '') {
        $query_ficha = $db->query("
            SELECT * FROM mybb_op_fichas WHERE fid='$user_fid'
        ");
    
        while ($f = $db->fetch_array($query_ficha)) {
            $f_var = $f;
            eval('$ficha = $f_var;');
            $rango_narrador = $ficha['nivelnarrador'];
        }

        $query_usuario = $db->query("
            SELECT * FROM mybb_users WHERE uid='$user_fid'
        ");
        while ($u = $db->fetch_array($query_usuario)) {
            $puntos_experiencia = $u['newpoints'];
        }
    }

    eval('$fid = $user_fid;');

    // Desplegables de facción y rango: antes eran listas fijas dentro de
    // staff_ficha_atributos.html / staff_ficha_atributos1.html, ahora se
    // generan desde mybb_op_facciones / mybb_op_facciones_rangos, gestionables
    // desde Admin CP → Configuración del foro → OPG Facciones.
    $faccion_select_options = '';
    $rango_select_options = '';

    if ($db->table_exists('op_facciones')) {
        $query_facciones = $db->simple_select('op_facciones', '*', '', array('order_by' => 'orden'));
        while ($f_row = $db->fetch_array($query_facciones)) {
            $selected = (isset($ficha['faccion']) && $ficha['faccion'] == $f_row['nombre']) ? ' selected' : '';
            $faccion_select_options .= '<option value="'.htmlspecialchars_uni($f_row['nombre']).'"'.$selected.'>'.htmlspecialchars_uni($f_row['nombre']).'</option>';
        }

        if (!empty($ficha['faccion']) && $db->table_exists('op_facciones_rangos')) {
            $query_rangos = $db->simple_select('op_facciones_rangos', '*', "faccion='".$db->escape_string($ficha['faccion'])."'", array('order_by' => 'orden'));
            while ($r_row = $db->fetch_array($query_rangos)) {
                $selected = (isset($ficha['rango']) && $ficha['rango'] == $r_row['valor']) ? ' selected' : '';
                $rango_select_options .= '<option value="'.htmlspecialchars_uni($r_row['valor']).'"'.$selected.'>'.htmlspecialchars_uni($r_row['nombre_visible']).'</option>';
            }
        }
    }

    eval("\$staff_ficha_atributos1 = \"".$templates->get("staff_ficha_atributos1")."\";");
    // eval("\$staff_ficha_atributos2 = \"".$templates->get("staff_ficha_atributos2")."\";");
    eval("\$page = \"".$templates->get("staff_ficha_atributos")."\";");
    
    output_page($page);
} else {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
}
