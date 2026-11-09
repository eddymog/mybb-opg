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
define('THIS_SCRIPT', 'tienda_nika.php');

global $templates, $mybb, $db;

require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$accion = isset($_POST["accion"]) ? $_POST["accion"] : '';
$comprasTotal = 0;
$listaDeCompras = isset($_POST["listaDeCompras"]) && is_array($_POST["listaDeCompras"]) ? $_POST["listaDeCompras"] : array();
$listaDeComprasKeys = isset($_POST["listaDeComprasKeys"]) && is_array($_POST["listaDeComprasKeys"]) ? $_POST["listaDeComprasKeys"] : array();
$ficha = null;

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($q = $db->fetch_array($query_ficha)) { $ficha = $q; }
$kuros = $ficha['kuro'];

$query_objetos = $db->query(" SELECT * FROM `mybb_op_kuros` ORDER BY berries ASC");
$objetos = array();
$objetos_array = array();

while ($q = $db->fetch_array($query_objetos)) { 
    $objeto_id = $q['objeto_id'];
    $key = "$objeto_id";
    if (!$objetos[$key]) { $objetos[$key] = array(); }
    array_push($objetos[$key], $q);
    array_push($objetos_array, $objeto_id);
}

$objetos_array_json = json_encode($objetos_array);
$objetos_json = json_encode($objetos);

function tiendaKurosPeriodoMensual() {
    $madrid = new DateTimeZone('Europe/Madrid');
    $ahora = new DateTimeImmutable('now', $madrid);
    $inicio = $ahora->modify('first day of this month')->setTime(0, 0, 0);
    $fin = $inicio->modify('+1 month');

    return array(
        'inicio' => $inicio,
        'fin' => $fin,
        'inicio_timestamp' => $inicio->getTimestamp(),
        'fin_timestamp' => $fin->getTimestamp(),
    );
}

function tiendaKurosComprasMensuales($uid, $periodo) {
    global $db;

    $conteos = array(
        'CFR001' => 0,
        'CFR002' => 0,
        'CFR003' => 0,
        'CFR004' => 0,
    );
    $uid = (int)$uid;
    $inicio = (int)$periodo['inicio_timestamp'];
    $fin = (int)$periodo['fin_timestamp'];
    $query = $db->query("SELECT log
        FROM mybb_op_audit_general
        WHERE categoria = '[Kuros]'
          AND user_uid = '{$uid}'
          AND tiempo >= FROM_UNIXTIME({$inicio})
          AND tiempo < FROM_UNIXTIME({$fin})
          AND log REGEXP 'CFR00[1-4]:[[:space:]]*[0-9]+'");

    while ($fila = $db->fetch_array($query)) {
        if (preg_match_all('/\\b(CFR00[1-4]):\\s*(\\d+)\\s*;/', $fila['log'], $coincidencias, PREG_SET_ORDER)) {
            foreach ($coincidencias as $coincidencia) {
                $conteos[$coincidencia[1]] += (int)$coincidencia[2];
            }
        }
    }

    return $conteos;
}

function tiendaKurosResponder($success, $message) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('success' => (bool)$success, 'message' => $message));
    exit;
}

$periodoCofres = tiendaKurosPeriodoMensual();
$comprasMensualesCofres = tiendaKurosComprasMensuales($uid, $periodoCofres);

if ($accion == 'comprar' && $uid != '0') {

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        tiendaKurosResponder(false, 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.');
    }

    $should_cancel = false;
    $logObj = "";

    $listaDeComprasKeys = array_values(array_unique($listaDeComprasKeys));

    foreach ($listaDeComprasKeys as $objKey) {
        if (!isset($objetos[$objKey][0], $listaDeCompras[$objKey]['cantidad'])) {
            $should_cancel = true;
            continue;
        }
        $cantidad = (int)$listaDeCompras[$objKey]['cantidad'];
        if ($cantidad <= 0 || $cantidad > 10) { $should_cancel = true; }
    }
    if ($should_cancel == true || empty($listaDeComprasKeys)) {
        tiendaKurosResponder(false, 'La lista de compra no es válida. Recarga la página e inténtalo de nuevo.');
    }

    $nombresCofres = array(
        'CFR001' => 'básicos',
        'CFR002' => 'decentes',
        'CFR003' => 'gigantes',
        'CFR004' => 'cobrizos',
    );
    foreach ($nombresCofres as $cofreId => $cofreNombre) {
        $cantidadSolicitada = isset($listaDeCompras[$cofreId]['cantidad']) ? (int)$listaDeCompras[$cofreId]['cantidad'] : 0;
        if ($cantidadSolicitada > 0 && $comprasMensualesCofres[$cofreId] + $cantidadSolicitada > 5) {
            tiendaKurosResponder(false, "Solo puedes comprar un máximo de 5 cofres {$cofreNombre} durante este mes.");
        }
    }

    foreach ($listaDeComprasKeys as $objKey) {
        $comprasTotal += (intval($objetos[$objKey][0]['berries']) * $listaDeCompras[$objKey]['cantidad']);
    }

    if (intval($kuros) >= $comprasTotal && $comprasTotal != 0) {
        foreach ($listaDeComprasKeys as $objKey) {

        
            $cantidadExtra = $listaDeCompras[$objKey]['cantidad'];
            if ($objKey != 'NIKAS' && $objKey != 'BERRIES' && $objKey != 'OFICIOS') {
                
                $logObj .= "$objKey: $cantidadExtra;";
                darObjeto($objKey, $cantidadExtra);
    
            } else {
    
                if ($objKey == 'NIKAS') {
                    darNikas($cantidadExtra);
                    $logObj .= "Nikas: $cantidadExtra;";
                }
    
                if ($objKey == 'BERRIES') {
                    darBerries(intval(intval($cantidadExtra) * 50000));
                    $logObj .= "Berries: $cantidadExtra;";
                }

                if ($objKey == 'OFICIOS') {
                    darOficios(intval(intval($cantidadExtra) * 100));
                    $logObj .= "Puntos de Oficio: $cantidadExtra;";
                }
            }
         
        }
    
        $nuevosKuros = intval($kuros) - intval($comprasTotal);
        log_audit($uid, $username, '[Kuros]', "Kuros: $kuros->$nuevosKuros (Gasto: $comprasTotal). $logObj");
        // $db->query(" UPDATE `mybb_op_fichas` SET kuro='$nuevosKuros' WHERE `fid`='$uid'; ");

        log_audit_currency($uid, $username, $uid, '[Tienda Kuros][Kuros]', 'kuros', $nuevosKuros);
        tiendaKurosResponder(true, '¡Compra realizada!');
    }

    tiendaKurosResponder(false, 'No tienes suficientes Kuros para completar esta compra.');
}

function darObjeto($objeto_id, $cantidadExtra) {
    global $db, $uid;
    $cantidadActual = '0';

    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objeto_id'");
    while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }

    if ($has_objeto) {
        $cantidadNueva = intval($cantidadActual) + intval($cantidadExtra);
        $db->query(" 
            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$objeto_id' AND uid='$uid'
        ");
    } else {
        $db->query(" 
            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('$objeto_id', '$uid', '$cantidadExtra');
        ");
    }
}

function darBerries($berriesNuevo) {
    global $db, $uid, $ficha, $username;
    $berries = intval($ficha['berries']) + $berriesNuevo;
    // $db->query(" UPDATE `mybb_op_fichas` SET `berries`='$berries' WHERE fid='$uid' ");

    log_audit_currency($uid, $username, $uid, '[Tienda Kuros][Berries]', 'berries', $berries);
}

function darNikas($nikasNuevo) {
    global $db, $uid, $ficha, $username;
    $nikas = intval($ficha['nika']) + $nikasNuevo;
    // $db->query(" UPDATE `mybb_op_fichas` SET `nika`='$nikas' WHERE fid='$uid' ");

    log_audit_currency($uid, $username, $uid, '[Tienda Kuros][Nikas]', 'nikas', $nikas);
}

function darOficios($oficiosNuevo) {
    global $db, $uid, $ficha, $username;
    $oficios = intval($ficha['puntos_oficio']) + $oficiosNuevo;
    // $db->query(" UPDATE `mybb_op_fichas` SET `puntos_oficio`='$oficios' WHERE fid='$uid' ");

    log_audit_currency($uid, $username, $uid, '[Tienda Kuros][Oficios]', 'puntos_oficio', $oficios);
}


function darExp($expNueva) {
    global $db, $uid, $experienciaActual, $username;
    $experiencia = floatval($experienciaActual) + $expNueva;
    // $db->query(" UPDATE `mybb_users` SET `newpoints`='$experiencia' WHERE uid='$uid' ");
    log_audit_currency($uid, $username, $uid, '[Tienda Kuros][Experiencia]', 'experiencia', $experiencia);
}


$reload_js = "";

$cofre1_count = (int)$comprasMensualesCofres['CFR001'];
$cofre2_count = (int)$comprasMensualesCofres['CFR002'];
$cofre3_count = (int)$comprasMensualesCofres['CFR003'];
$cofre4_count = (int)$comprasMensualesCofres['CFR004'];
$cofresMensualesTotal = $cofre1_count + $cofre2_count + $cofre3_count + $cofre4_count;

$mesesEspanol = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
$ultimoDiaPeriodo = $periodoCofres['fin']->modify('-1 day');
$cofresPeriodoTexto = $periodoCofres['inicio']->format('j') . '–' . $ultimoDiaPeriodo->format('j') . ' de ' . $mesesEspanol[(int)$periodoCofres['inicio']->format('n')] . ' de ' . $periodoCofres['inicio']->format('Y');
$cofresReinicioTexto = $periodoCofres['fin']->format('j') . ' de ' . $mesesEspanol[(int)$periodoCofres['fin']->format('n')] . ' a las 00:00';

$cofresNombres = array(
    'CFR001' => 'Cofre básico',
    'CFR002' => 'Cofre decente',
    'CFR003' => 'Cofre gigante',
    'CFR004' => 'Cofre cobrizo',
);
$cofresMensualesHtml = '';
foreach ($cofresNombres as $cofreId => $cofreNombre) {
    $comprados = (int)$comprasMensualesCofres[$cofreId];
    $disponibles = max(0, 5 - $comprados);
    $porcentaje = min(100, $comprados * 20);
    $agotadoClase = $disponibles === 0 ? ' is-agotado' : '';
    $cofresMensualesHtml .= '<div class="kuros-cupo__fila' . $agotadoClase . '">'
        . '<div class="kuros-cupo__nombre"><strong>' . $cofreNombre . '</strong><span>' . $comprados . ' de 5 comprados</span></div>'
        . '<div class="kuros-cupo__barra" role="progressbar" aria-label="' . $cofreNombre . '" aria-valuemin="0" aria-valuemax="5" aria-valuenow="' . $comprados . '"><i style="width:' . $porcentaje . '%"></i></div>'
        . '<div class="kuros-cupo__disponibles"><strong>' . $disponibles . '</strong><span>disponibles</span></div>'
        . '</div>';
}



if (does_ficha_exist($uid)) {

    // $mensaje_redireccion = "Tienda de Kuros cerrada temporalmente. Mientras tanto, toca ahorrar que esos cofres no se compran solos.";
    // eval("\$page = \"".$templates->get("op_redireccion")."\";");
    // output_page($page);

    eval("\$page = \"".$templates->get("op_tienda_kuros")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}
