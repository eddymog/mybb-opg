<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Copia de opg/tirada_cofre.php con 3 cambios puntuales
 * (docs/200_DesignPlan_Cofres.md §4, docs/300_ImplementationPlan_Cofres.md
 * Tarea 9) — el resto del archivo es idéntico a propósito:
 *
 * 1. Validación de cofre_id dinámica (cofreIdValido()) en vez de la condición
 *    larga de ORs hardcodeados — un cofre nuevo creado desde
 *    op/staff/cofres_gestion.php ya es abrible sin tocar este archivo.
 * 2. procesarCustomRecompensa() lee custom_data (JSON) en vez de un switch de
 *    más de 60 casos de texto.
 * 3. Se quitó el bloque action=dar_cofre_masivo — se mudó a
 *    op/staff/cofres_repartir.php (herramienta de staff aparte, con
 *    is_admin() real en vez de dos UID hardcodeados).
 *
 * opg/tirada_cofre.php (el original) NO se toca mientras este archivo se
 * prueba — ver 100_Requirements_Cofres.md §2.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tirada_rey.php');

// ---- DEBUG TEMPORAL (quitar cuando se resuelva el error) ----
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_exception_handler(function($e) {
    if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
    echo "[EXCEPCION] " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " Linea: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    return false; // dejar que PHP también lo maneje
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
        echo "[FATAL] " . $err['message'] . "\n";
        echo "Archivo: " . $err['file'] . " Linea: " . $err['line'];
    }
});
// ---- FIN DEBUG ----

require_once "./../global.php";
require "./../inc/config.php";
require_once "./../op/functions/op_functions.php";
global $templates, $mybb;

$uid = $mybb->user['uid'];
$ficha = null;
$ficha_aprobada = false;

if ($uid == '0') {
    $mensaje_redireccion = "Debes estar registrado.";
    eval('$page = "' . $templates->get('op_redireccion') . '";');
    output_page($page);
    return;
}

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval('$page = "' . $templates->get('op_redireccion') . '";');
    output_page($page);
    return;
}

$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$uid' ");
while ($f = $db->fetch_array($query_ficha)) { $ficha = $f; $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true); }

$experienciaActual = 0;
$query_users = $db->query(" SELECT * FROM mybb_users WHERE uid='$uid' ");
while ($q = $db->fetch_array($query_users)) { $experienciaActual = $q['newpoints']; }

if ($ficha == null) {
    $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
    eval('$page = "' . $templates->get('op_redireccion') . '";');
    output_page($page);
    return;
}

$username = $mybb->user['username'];
$is_staff  = intval($mybb->usergroup['cancp']) ? 'true' : 'false';
$tirada_real  = $_POST['tirada_real']  ?? '';
$tirada_cofre = $_POST['tirada_cofre'] ?? '';
$cofre_id     = strtoupper($_POST['cofre_id'] ?? '');

// ── Máquina de estados del cofre bromista CFF010 ─────────────────────────────
// Cada estado mapea al siguiente y al nombre visible que se muestra como "recompensa"
$cff010_chain = [
    'CFF010'    => ['next' => 'CFF010-2',  'nombre_next' => 'Cofre Decente'],
    'CFF010-2'  => ['next' => 'CFF010-3',  'nombre_next' => 'Cofre Gigante'],
    'CFF010-3'  => ['next' => 'CFF010-4',  'nombre_next' => 'Cofre Cobrizo'],
    'CFF010-4'  => ['next' => 'CFF010-5',  'nombre_next' => 'Cofre Argénteo'],
    'CFF010-5'  => ['next' => 'CFF010-6',  'nombre_next' => 'Cofre Áureo'],
    'CFF010-6'  => ['next' => 'CFF010-7',  'nombre_next' => 'Cofre Épico'],
    'CFF010-7'  => ['next' => 'CFF010-8',  'nombre_next' => 'Cofre Legendario'],
    'CFF010-8'  => ['next' => 'CFF010-9',  'nombre_next' => 'Cofre Majestuoso'],
    'CFF010-9'  => ['next' => 'CFF010-10', 'nombre_next' => 'Cofre Diamantino'],
    'CFF010-10' => ['next' => 'CFF010-9D', 'nombre_next' => 'Cofre Majestuoso'],
    'CFF010-9D' => ['next' => 'CFF010-8D', 'nombre_next' => 'Cofre Legendario'],
    'CFF010-8D' => ['next' => 'CFF010-7D', 'nombre_next' => 'Cofre Épico'],
    'CFF010-7D' => ['next' => 'CFF010-6D', 'nombre_next' => 'Cofre Áureo'],
    'CFF010-6D' => ['next' => 'CFF010-5D', 'nombre_next' => 'Cofre Argénteo'],
    'CFF010-5D' => ['next' => 'CFF010-4D', 'nombre_next' => 'Cofre Cobrizo'],
    'CFF010-4D' => ['next' => 'CFF010-3D', 'nombre_next' => 'Cofre Gigante'],
    'CFF010-3D' => ['next' => 'CFF010-2D', 'nombre_next' => 'Cofre Decente'],
    'CFF010-2D' => ['next' => 'CFF010-1D', 'nombre_next' => 'Cofre Básico'],
    'CFF010-1D' => ['next' => null,         'nombre_next' => null],
];
// $cofre_id = 'CFR001';
// $tirada_cofre = 'true';

$tirada_cofre_resultado = '';

$cofres_abiertos = '0';
$cofre_basico = '0';
$cofre_decente = '0';
$cofre_gigante = '0';
$cofre_cobrizo = '0';
$cofre_argenteo = '0';
$cofre_aureo = '0';
$cofre_epico = '0';
$cofre_legendario = '0';
$cofre_majestuoso = '0';
$cofre_diamantino = '0';
$cofre_jackpot = '0';
$cofre_increase = '0';
$cofre_decrease = '0';

$cofres_abiertos_id = '0';
$cofre_basico_id = '0';
$cofre_decente_id = '0';
$cofre_gigante_id = '0';
$cofre_cobrizo_id = '0';
$cofre_argenteo_id = '0';
$cofre_aureo_id = '0';
$cofre_epico_id = '0';
$cofre_legendario_id = '0';
$cofre_majestuoso_id = '0';
$cofre_diamantino_id = '0';
$cofre_jackpot_id = '0';
$cofre_increase_id = '0';
$cofre_decrease_id = '0';

$cofres_globales_array = array();
$cofres_propios_array = array();

$cofres_globales_query = $db->query(" SELECT * FROM `mybb_op_tirada_cofre` WHERE `uid` <> 850 ORDER BY ID DESC LIMIT 200; ");
$cofres_propios_query = $db->query(" SELECT * FROM `mybb_op_tirada_cofre` WHERE `uid`='$uid' ORDER BY ID DESC LIMIT 50; ");

$cofres_abiertos_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` ");
$cofre_basico_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR001'; ");
$cofre_decente_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR002'; ");
$cofre_gigante_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR003'; ");
$cofre_cobrizo_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR004'; ");
$cofre_argenteo_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR005'; ");
$cofre_aureo_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR006'; ");
$cofre_epico_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR007'; ");
$cofre_legendario_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR008'; ");
$cofre_majestuoso_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR009'; ");
$cofre_diamantino_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR010'; ");
$cofre_jackpot_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `objeto_id`='JACKPOT'; ");
$cofre_increase_query = $db->query("
    SELECT count(*) AS total FROM `mybb_op_tirada_cofre`
    WHERE
    (`tier`='CFR001' AND objeto_id='CFR002') OR
    (`tier`='CFR002' AND objeto_id='CFR003') OR
    (`tier`='CFR003' AND objeto_id='CFR004') OR
    (`tier`='CFR004' AND objeto_id='CFR005') OR
    (`tier`='CFR005' AND objeto_id='CFR006') OR
    (`tier`='CFR006' AND objeto_id='CFR007') OR
    (`tier`='CFR007' AND objeto_id='CFR008') OR
    (`tier`='CFR008' AND objeto_id='CFR009') OR
    (`tier`='CFR009' AND objeto_id='CFR010'); ");
$cofre_decrease_query = $db->query("
    SELECT count(*) AS total FROM `mybb_op_tirada_cofre`
    WHERE
    (`tier`='CFR002' AND objeto_id='CFR001') OR
    (`tier`='CFR003' AND objeto_id='CFR002') OR
    (`tier`='CFR004' AND objeto_id='CFR003') OR
    (`tier`='CFR005' AND objeto_id='CFR004') OR
    (`tier`='CFR006' AND objeto_id='CFR005') OR
    (`tier`='CFR007' AND objeto_id='CFR006') OR
    (`tier`='CFR008' AND objeto_id='CFR007') OR
    (`tier`='CFR009' AND objeto_id='CFR008') OR
    (`tier`='CFR010' AND objeto_id='CFR009'); ");

$cofres_abiertos_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE uid='$uid' ");
$cofre_basico_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR001' AND uid='$uid'; ");
$cofre_decente_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR002' AND uid='$uid'; ");
$cofre_gigante_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR003' AND uid='$uid'; ");
$cofre_cobrizo_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR004' AND uid='$uid'; ");
$cofre_argenteo_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR005' AND uid='$uid'; ");
$cofre_aureo_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR006' AND uid='$uid'; ");
$cofre_epico_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR007' AND uid='$uid'; ");
$cofre_legendario_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR008' AND uid='$uid'; ");
$cofre_majestuoso_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR009' AND uid='$uid'; ");
$cofre_diamantino_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `tier`='CFR010' AND uid='$uid'; ");
$cofre_jackpot_id_query = $db->query(" SELECT count(*) AS total FROM `mybb_op_tirada_cofre` WHERE `objeto_id`='JACKPOT' AND uid='$uid'; ");
$cofre_increase_id_query = $db->query("
    SELECT count(*) AS total FROM `mybb_op_tirada_cofre`
    WHERE uid='$uid' AND
    ((`tier`='CFR001' AND objeto_id='CFR002') OR
    (`tier`='CFR002' AND objeto_id='CFR003') OR
    (`tier`='CFR003' AND objeto_id='CFR004') OR
    (`tier`='CFR004' AND objeto_id='CFR005') OR
    (`tier`='CFR005' AND objeto_id='CFR006') OR
    (`tier`='CFR006' AND objeto_id='CFR007') OR
    (`tier`='CFR007' AND objeto_id='CFR008') OR
    (`tier`='CFR008' AND objeto_id='CFR009') OR
    (`tier`='CFR009' AND objeto_id='CFR010')); ");
$cofre_decrease_id_query = $db->query("
    SELECT count(*) AS total FROM `mybb_op_tirada_cofre`
    WHERE uid='$uid' AND
    ((`tier`='CFR002' AND objeto_id='CFR001') OR
    (`tier`='CFR003' AND objeto_id='CFR002') OR
    (`tier`='CFR004' AND objeto_id='CFR003') OR
    (`tier`='CFR005' AND objeto_id='CFR004') OR
    (`tier`='CFR006' AND objeto_id='CFR005') OR
    (`tier`='CFR007' AND objeto_id='CFR006') OR
    (`tier`='CFR008' AND objeto_id='CFR007') OR
    (`tier`='CFR009' AND objeto_id='CFR008') OR
    (`tier`='CFR010' AND objeto_id='CFR009')); ");

while ($q = $db->fetch_array($cofres_abiertos_query)) { $cofres_abiertos = $q['total']; }
while ($q = $db->fetch_array($cofre_basico_query)) { $cofre_basico = $q['total']; }
while ($q = $db->fetch_array($cofre_decente_query)) { $cofre_decente = $q['total']; }
while ($q = $db->fetch_array($cofre_gigante_query)) { $cofre_gigante = $q['total']; }
while ($q = $db->fetch_array($cofre_cobrizo_query)) { $cofre_cobrizo = $q['total']; }
while ($q = $db->fetch_array($cofre_argenteo_query)) { $cofre_argenteo = $q['total']; }
while ($q = $db->fetch_array($cofre_aureo_query)) { $cofre_aureo = $q['total']; }
while ($q = $db->fetch_array($cofre_epico_query)) { $cofre_epico = $q['total']; }
while ($q = $db->fetch_array($cofre_legendario_query)) { $cofre_legendario = $q['total']; }
while ($q = $db->fetch_array($cofre_majestuoso_query)) { $cofre_majestuoso = $q['total']; }
while ($q = $db->fetch_array($cofre_diamantino_query)) { $cofre_diamantino = $q['total']; }

while ($q = $db->fetch_array($cofre_jackpot_query)) { $cofre_jackpot = $q['total']; }
while ($q = $db->fetch_array($cofre_increase_query)) { $cofre_increase = $q['total']; }
while ($q = $db->fetch_array($cofre_decrease_query)) { $cofre_decrease = $q['total']; }

while ($q = $db->fetch_array($cofres_abiertos_id_query)) { $cofres_abiertos_id = $q['total']; }
while ($q = $db->fetch_array($cofre_basico_id_query)) { $cofre_basico_id = $q['total']; }
while ($q = $db->fetch_array($cofre_decente_id_query)) { $cofre_decente_id = $q['total']; }
while ($q = $db->fetch_array($cofre_gigante_id_query)) { $cofre_gigante_id = $q['total']; }
while ($q = $db->fetch_array($cofre_cobrizo_id_query)) { $cofre_cobrizo_id = $q['total']; }
while ($q = $db->fetch_array($cofre_argenteo_id_query)) { $cofre_argenteo_id = $q['total']; }
while ($q = $db->fetch_array($cofre_aureo_id_query)) { $cofre_aureo_id = $q['total']; }
while ($q = $db->fetch_array($cofre_epico_id_query)) { $cofre_epico_id = $q['total']; }
while ($q = $db->fetch_array($cofre_legendario_id_query)) { $cofre_legendario_id = $q['total']; }
while ($q = $db->fetch_array($cofre_majestuoso_id_query)) { $cofre_majestuoso_id = $q['total']; }
while ($q = $db->fetch_array($cofre_diamantino_id_query)) { $cofre_diamantino_id = $q['total']; }

while ($q = $db->fetch_array($cofre_jackpot_id_query)) { $cofre_jackpot_id = $q['total']; }
while ($q = $db->fetch_array($cofre_increase_id_query)) { $cofre_increase_id = $q['total']; }
while ($q = $db->fetch_array($cofre_decrease_id_query)) { $cofre_decrease_id = $q['total']; }

while ($q = $db->fetch_array($cofres_globales_query)) {
    array_push($cofres_globales_array, $q);
}

while ($q = $db->fetch_array($cofres_propios_query)) {
    array_push($cofres_propios_array, $q);
}

$cofres_globales_json = json_encode($cofres_globales_array);
$cofres_propios_json = json_encode($cofres_propios_array);

// Inventario de cofres del usuario para el frontend
$cff010_ids_sql  = "'" . implode("','", array_keys($cff010_chain)) . "'";
$cofres_ids_list = "'CFR001','CFR002','CFR003','CFR004','CFR005','CFR006','CFR007','CFR008','CFR009','CFR010',$cff010_ids_sql,'INV001','INV002','INV003','INV004','INV005','CFD002','CFD003','CFD004','CRM003','CFN002','CFN003','CFN004','CFN005','CFN006','CFN007','CFN008','CFN009','KTC001'";
$query_inventario_cofres = $db->query("
    SELECT o.*, i.cantidad FROM `mybb_op_objetos` o
    INNER JOIN `mybb_op_inventario` i ON o.`objeto_id` = i.`objeto_id`
    WHERE i.`uid`='$uid'
    AND o.`objeto_id` IN ($cofres_ids_list)
    ORDER BY o.`tier`, o.`nombre`
");
$objetos = array();
$objetos_array = array();
while ($q = $db->fetch_array($query_inventario_cofres)) {
    $key = $q['objeto_id'];
    if (!isset($objetos[$key])) { $objetos[$key] = array(); }
    array_push($objetos[$key], $q);
    array_push($objetos_array, $key);
}
$objetos_json       = json_encode($objetos);
$objetos_array_json = json_encode($objetos_array);

$kuro_accion = $mybb->get_input('kuro_accion');

// Resuelve IDs del tipo 'CFRxxxX2' -> ['CFRxxx', 2]
// Devuelve [$objeto_id_real, $cantidad_real]
function resolverObjetoId($objeto_id, $cantidad_base) {
    if (preg_match('/^(.+)X(\d+)$/i', $objeto_id, $m)) {
        return [$m[1], intval($m[2])];
    }
    return [$objeto_id, intval($cantidad_base)];
}

/**
 * Valida un cofre_id contra lo que existe de verdad: el cofre bromista
 * (caso especial, fuera de la tabla) o cualquier cofre_id que tenga al menos
 * una fila en mybb_op_cofres — ya no una lista fija escrita a mano, así un
 * cofre nuevo creado desde op/staff/cofres_gestion.php es abrible sin tocar
 * este archivo (docs/200_DesignPlan_Cofres.md §4.1).
 */
function cofreIdValido($db, $cofre_id, $cff010_chain) {
    if (isset($cff010_chain[$cofre_id])) {
        return true;
    }
    $safe = $db->escape_string($cofre_id);
    return (bool) $db->fetch_field(
        $db->query("SELECT cofre_id FROM `mybb_op_cofres` WHERE cofre_id='{$safe}' LIMIT 1"),
        'cofre_id'
    );
}

function darObjetoFid($objeto_id, $fid) {
    global $db, $uid;
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

function darObjeto($objeto_id, $cantidadNueva) {
    global $db, $uid;
    $cantidadActual = '0';
    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objeto_id'");
    while ($q = $db->fetch_array($inventario_actual)) { $has_objeto = true; $cantidadActual = $q['cantidad']; }

    if ($has_objeto) {
        $cantidadNuevaNueva = intval($cantidadActual) + intval($cantidadNueva);
        $db->query("
            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNuevaNueva' WHERE objeto_id='$objeto_id' AND uid='$uid'
        ");
    } else {
        $db->query("
            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES
            ('$objeto_id', '$uid', '1');
        ");
    }
}

function darBerries($berriesNuevo) {
    global $db, $uid, $ficha, $username;
    $berriesActual = intval($ficha['berries']);
    $berries = $berriesActual + $berriesNuevo;
    $ficha['berries'] = $berries; // actualizar para acumular correctamente con llamadas posteriores
    log_audit($uid, $username, '[Cofre]', "Berries: $berriesActual->$berries (Extra: $berriesNuevo).");
    log_audit_currency($uid, $username, $uid, '[Cofre][Berries]', 'berries', $berries);
}

function darNikas($nikasNuevo) {
    global $db, $uid, $ficha, $username;

    $nikasActual = intval($ficha['nika']);
    $nikas = $nikasActual + $nikasNuevo;
    $ficha['nika'] = $nikas; // actualizar para acumular correctamente con llamadas posteriores
    log_audit($uid, $username, '[Cofre]', "Nikas: $nikasActual->$nikas (Extra: $nikasNuevo).");
    // $db->query(" UPDATE `mybb_op_fichas` SET `nika`='$nikas' WHERE fid='$uid' ");
    log_audit_currency($uid, $username, $uid, '[Cofre][Nikas]', 'nikas', $nikas);
}

function darExp($expNueva) {
    global $db, $uid, $experienciaActual, $username;

    $expeActual = floatval($experienciaActual);
    $experiencia = $expeActual + $expNueva;
    $experienciaActual = $experiencia; // actualizar para acumular correctamente con llamadas posteriores

    log_audit($uid, $username, '[Cofre]', "Experiencia: $expeActual->$experiencia (Extra: $expNueva).");
    // $db->query(" UPDATE `mybb_users` SET `newpoints`='$experiencia' WHERE uid='$uid' ");
    log_audit_currency($uid, $username, $uid, '[Cofre][Experiencia]', 'experiencia', $experiencia);
}

function darOficio($puntosOficio) {
    global $db, $uid, $ficha, $has_sin_oficio, $experienciaActual, $username;

    if ($has_sin_oficio) {
        $expeActual = floatval($experienciaActual);
        $experiencia = $expeActual + ($puntosOficio / 10);
        $experienciaActual = $experiencia; // actualizar para acumular correctamente con llamadas posteriores

        log_audit($uid, $username, '[Cofre]', "Sin Oficio Expe: $expeActual->$experiencia (Extra: $puntosOficio).");
        // $db->query(" UPDATE `mybb_users` SET `newpoints`='$experiencia' WHERE uid='$uid' ");
        log_audit_currency($uid, $username, $uid, '[Cofre][Experiencia]', 'experiencia', $experiencia);
    } else {
        $puntosActual = intval($ficha['puntos_oficio']);
        $puntosOficioNuevo = $puntosActual + $puntosOficio;
        $ficha['puntos_oficio'] = $puntosOficioNuevo; // actualizar para acumular correctamente con llamadas posteriores
        log_audit($uid, $username, '[Cofre]', "Puntos de Oficio: $puntosActual->$puntosOficioNuevo (Extra: $puntosOficio).");
        // $db->query(" UPDATE `mybb_op_fichas` SET `puntos_oficio`='$puntosOficioNuevo' WHERE fid='$uid' ");
        log_audit_currency($uid, $username, $uid, '[Cofre][Puntos oficio]', 'puntos_oficio', $puntosOficioNuevo);
    }
}

// ======================================================================
// Procesa una recompensa de tipo 'Custom' a partir de su custom_data (JSON)
// — reemplaza el switch de más de 60 códigos de texto del archivo original
// (docs/200_DesignPlan_Cofres.md §4.2). $custom_data_json es la columna tal
// cual viene de mybb_op_cofres (string JSON o null).
// ======================================================================
function procesarCustomRecompensa($custom_data_json) {
    $datos = json_decode((string) $custom_data_json, true);
    if (!is_array($datos)) { return; }

    if (!empty($datos['nikas']))         { darNikas((float) $datos['nikas']); }
    if (!empty($datos['berries']))       { darBerries((int) $datos['berries']); }
    if (!empty($datos['experiencia']))   { darExp((float) $datos['experiencia']); }
    if (!empty($datos['puntos_oficio'])) { darOficio((int) $datos['puntos_oficio']); }
    if (!empty($datos['objeto_bonus'])) {
        darObjeto($datos['objeto_bonus'], intval($datos['objeto_bonus_cantidad'] ?? 1));
    }
}

// ======================================================================
// Jackpot: da todos los items del cofre (excluyendo el propio jackpot)
// ======================================================================
function procesarJackpotCofre($cofre_id) {
    global $db;
    $safe_id = $db->escape_string($cofre_id);
    $items_q = $db->query("SELECT * FROM mybb_op_cofres WHERE cofre_id='$safe_id' AND tipo != 'Jackpot'");
    while ($item = $db->fetch_array($items_q)) {
        if ($item['tipo'] === 'Objeto') {
            [$rid, $rcant] = resolverObjetoId($item['objeto_id'], $item['cantidad']);
            darObjeto($rid, $rcant);
        } elseif ($item['tipo'] === 'Custom') {
            procesarCustomRecompensa($item['custom_data']);
        }
    }
}


$has_sin_oficio = false;
$has_sin_oficio_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='D024'; ");
while ($q = $db->fetch_array($has_sin_oficio_query)) { $has_sin_oficio = true; }

if ($tirada_cofre == 'true') {
    header('Content-type: application/json');
    $response = array();
    $timestamp = time();

    // $tirada_random = rand(1, 10);

    $cantidadActual = '0';
    $cantidadExtra = '1';

    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$cofre_id'");
    while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }

    // $has_objeto = false;

    $objeto = 'hello'; $objeto_id = 'null';

    if ($has_objeto && cofreIdValido($db, $cofre_id, $cff010_chain)) {

        $cantidadNueva = intval($cantidadActual) - intval($cantidadExtra);

        if ($cantidadNueva == 0) {
            $db->query("
                DELETE FROM `mybb_op_inventario` WHERE objeto_id='$cofre_id' AND uid='$uid'
            ");
        } else {
            $db->query("
                UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$cofre_id' AND uid='$uid'
            ");
        }

        // Cofre bromista: transiciona al siguiente estado en lugar de dar recompensa real
        if (isset($cff010_chain[$cofre_id])) {
            $estado    = $cff010_chain[$cofre_id];
            $siguiente = $estado['next'];
            $safe_username = $db->escape_string($username);

            if ($siguiente !== null) {
                darObjeto($siguiente, 1);
                $safe_nombre = $db->escape_string($estado['nombre_next']);
                $db->query("INSERT INTO `mybb_op_tirada_cofre` (`uid`, `tier`, `objeto_id`, `timestamp`, `nombre`, `objeto`) VALUES ('$uid', '$cofre_id', '$siguiente', '$timestamp', '$safe_username', '$safe_nombre')");
                $response['success']   = true;
                $response['objeto']    = $estado['nombre_next'];
                $response['objeto_id'] = $siguiente;
            } else {
                $db->query("INSERT INTO `mybb_op_tirada_cofre` (`uid`, `tier`, `objeto_id`, `timestamp`, `nombre`, `objeto`) VALUES ('$uid', '$cofre_id', 'DESTRUIDO', '$timestamp', '$safe_username', 'De verdad que estáis desesperados por cofres, ¿eh?')");
                $response['success']   = true;
                $response['objeto']    = 'De verdad que estáis desesperados por cofres, ¿eh?';
                $response['objeto_id'] = 'DESTRUIDO';
            }
            echo json_encode($response);
            return;
        }

        $cofre = null;

        $cofre_random = 1000;
        $cofre_random_query =  $db->query("SELECT FLOOR(1 + RAND() * (SELECT SUM(peso) FROM mybb_op_cofres WHERE cofre_id = '$cofre_id')) as random_cofre_weight;");

        while ($q = $db->fetch_array($cofre_random_query)) { $cofre_random = intval($q['random_cofre_weight']); }

        $cofre_query = $db->query("
            SELECT
                objeto_id,
                nombre,
                tipo,
                peso,
                cantidad,
                custom_data,
                cumulative_weight,
                @random_weight AS random_weight
            FROM (
                SELECT
                    objeto_id,
                    nombre,
                    tipo,
                    peso,
                    cantidad,
                    custom_data,
                    @cumulative_weight := @cumulative_weight + peso AS cumulative_weight
                FROM mybb_op_cofres
                CROSS JOIN (SELECT @cumulative_weight := 0) AS init
                WHERE cofre_id = '$cofre_id'
                ORDER BY cumulative_weight
            ) AS weighted_items
            WHERE cumulative_weight >= $cofre_random
            ORDER BY weighted_items.cumulative_weight ASC
            LIMIT 1;
        ");

        while ($q = $db->fetch_array($cofre_query)) { $cofre = $q; }

        if ($cofre === null) {
            $response['success'] = false;
            $response['mensaje'] = 'Error interno: no se pudo determinar el resultado del cofre.';
            echo json_encode($response);
            return;
        }

        $obj_id      = $cofre['objeto_id'];
        $obj_cantidad = $cofre['cantidad'];

        // ---- Procesado unificado: sin bloques por cofre ----
        if ($cofre['tipo'] == 'Objeto') {
            [$rid, $rcant] = resolverObjetoId($obj_id, $obj_cantidad);
            darObjeto($rid, $rcant);
        } elseif ($cofre['tipo'] == 'Custom') {
            procesarCustomRecompensa($cofre['custom_data']);
        } elseif ($cofre['tipo'] == 'Jackpot') {
            procesarJackpotCofre($cofre_id);
        }

        $objeto    = $cofre['nombre'];
        $objeto_id = $cofre['objeto_id'];

        $safe_objeto    = $db->escape_string($objeto);
        $safe_username  = $db->escape_string($username);
        $db->query("INSERT INTO `mybb_op_tirada_cofre` (`uid`, `tier`, `objeto_id`, `timestamp`, `nombre`, `objeto`) VALUES ('$uid', '$cofre_id', '$objeto_id', '$timestamp', '$safe_username', '$safe_objeto')");

        $response['success']   = true;
        $response['objeto']    = $objeto;
        $response['objeto_id'] = $objeto_id;
        echo json_encode($response);
        return;
    }

    $response['success'] = false;
    $response['mensaje'] = 'No tienes ese cofre o no es válido.';
    echo json_encode($response);
    return;
}

eval('$page = "' . $templates->get('op_tirada_cofre') . '";');
output_page($page);
