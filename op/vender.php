<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'vender.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(['error' => 'No autenticado']); exit; }

$objeto_id       = $db->escape_string($mybb->get_input('objeto'));
$cantidad_vender = max(1, (int)$mybb->get_input('cantidad_vender'));

if (!$objeto_id) { echo json_encode(['error' => 'Parámetro inválido']); exit; }

// Precio de venta según oficio Mercader
$q_ficha = $db->simple_select('op_fichas', 'berries, oficios', "fid='$uid'");
$ficha   = $db->fetch_array($q_ficha);
if (!$ficha) { echo json_encode(['error' => 'Ficha no encontrada']); exit; }

$oficios_obj    = json_decode($ficha['oficios']);
$precioVentaPct = 2.00000001;
if (isset($oficios_obj->{'Mercader'})) {
    $precioVentaPct = 1.66666667;
    if (isset($oficios_obj->{'Mercader'}->{'sub'}->{'Comerciante'})) {
        $nivel = $oficios_obj->{'Mercader'}->{'sub'}->{'Comerciante'};
        if ($nivel == 1) $precioVentaPct = 1.42857143;
        if ($nivel == 2) $precioVentaPct = 1.25;
        if ($nivel == 3) $precioVentaPct = 1.1111111117;
    }
}

// Verificar que el objeto existe y obtener su precio base
$q_obj  = $db->simple_select('op_objetos', 'nombre, berries', "objeto_id='$objeto_id'");
$objeto = $db->fetch_array($q_obj);
if (!$objeto) { echo json_encode(['error' => 'Objeto no encontrado']); exit; }

// Verificar que el usuario tiene el objeto en inventario
$q_inv  = $db->simple_select('op_inventario', 'cantidad', "uid='$uid' AND objeto_id='$objeto_id'");
$inv    = $db->fetch_array($q_inv);
if (!$inv) { echo json_encode(['error' => 'No tienes ese objeto en el inventario']); exit; }

$cantidad_actual = (int)$inv['cantidad'];
$cantidad_vender = min($cantidad_vender, $cantidad_actual);

// Actualizar inventario
if ($cantidad_actual > $cantidad_vender) {
    $nueva_cantidad = $cantidad_actual - $cantidad_vender;
    $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva_cantidad' WHERE objeto_id='$objeto_id' AND uid='$uid'");
} else {
    $db->query("DELETE FROM mybb_op_inventario WHERE objeto_id='$objeto_id' AND uid='$uid'");
}

// Calcular y acreditar berries
$precio_unitario  = intval(intval($objeto['berries']) / $precioVentaPct) + 1;
$ganancia         = $precio_unitario * $cantidad_vender;
$berries_actuales = intval($ficha['berries']);
$nuevos_berries   = $berries_actuales + $ganancia;

$db->query("UPDATE mybb_op_fichas SET berries='$nuevos_berries' WHERE fid='$uid'");

$nombre = $db->escape_string($objeto['nombre']);
log_audit($uid, $mybb->user['username'], '[Venta]', "Vendido: {$cantidad_vender}x $nombre ($objeto_id): $berries_actuales->$nuevos_berries (Ganancia: $ganancia).");
log_audit_currency($uid, $mybb->user['username'], $uid, '[Venta][Berries]', 'berries', $nuevos_berries);

echo json_encode([
    'ok'       => true,
    'ganancia' => $ganancia,
    'berries'  => $nuevos_berries,
], JSON_UNESCAPED_UNICODE);
