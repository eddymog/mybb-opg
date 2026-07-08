<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco_mejora.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(['error' => 'No autenticado']); exit; }

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(['error' => 'Token CSRF inválido']); exit;
}

$barco_id  = trim($mybb->get_input('barco_id'));
$owner_uid = (int)$mybb->get_input('owner_uid');
$tipo      = trim($mybb->get_input('tipo'));

$tipos_validos = ['tripulacion', 'vitalidad', 'resistencia', 'ruptura'];
if (!$barco_id || !$owner_uid || !in_array($tipo, $tipos_validos)) {
    echo json_encode(['error' => 'Parámetros inválidos']); exit;
}

$barco_id_safe  = $db->escape_string($barco_id);
$owner_uid_safe = (int)$owner_uid;

// Verificar que el barco existe en el inventario del propietario
$q = $db->simple_select('op_inventario', 'id', "objeto_id='$barco_id_safe' AND uid='$owner_uid_safe'");
if (!$db->fetch_array($q)) {
    echo json_encode(['error' => 'Barco no encontrado']); exit;
}

// Verificar que el usuario tiene oficio Carpintero + rol Carpintero en el barco
$es_carpintero = ($uid === 850);
if (!$es_carpintero) {
    // Primero comprobar oficio en ficha
    $q_ficha = $db->query("SELECT 1 FROM mybb_op_fichas WHERE fid='$uid' AND (oficio1='Carpintero' OR oficio2='Carpintero') LIMIT 1");
    if ($db->fetch_array($q_ficha)) {
        // Luego comprobar rol Carpintero en el barco
        if ((int)$uid === $owner_uid_safe) {
            // Dueño: rol en owner_rangos de barco_estado
            $q_rango = $db->simple_select('op_barco_estado', 'owner_rangos', "barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe'");
            $rango_row = $db->fetch_array($q_rango);
            if ($rango_row && in_array('Carpintero', array_map('trim', explode(',', $rango_row['owner_rangos'])))) {
                $es_carpintero = true;
            }
        } else {
            // Tripulante: rango Carpintero en barco_tripulacion
            $q_trip = $db->query("SELECT 1 FROM mybb_op_barco_tripulacion WHERE barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe' AND miembro_uid='$uid' AND FIND_IN_SET('Carpintero', rango) > 0 LIMIT 1");
            if ($db->fetch_array($q_trip)) { $es_carpintero = true; }
        }
    }
}
// NPC con rol Carpintero y oficio Carpintero perteneciente al usuario actual
if (!$es_carpintero) {
    $q_npc = $db->query("
        SELECT 1 FROM mybb_op_barco_npcs bn
        JOIN mybb_op_npcs n ON n.npc_id = bn.ref_id
        WHERE bn.barco_id='$barco_id_safe' AND bn.owner_uid='$owner_uid_safe'
          AND bn.tipo='npc' AND bn.rol='Carpintero'
          AND bn.ref_id LIKE '" . (int)$uid . "-%'
          AND n.oficios LIKE '%Carpintero%'
        LIMIT 1
    ");
    if ($db->fetch_array($q_npc)) { $es_carpintero = true; }
}
if (!$es_carpintero) {
    echo json_encode(['error' => 'Se requiere oficio Carpintero y rol Carpintero en el barco']); exit;
}

// Obtener stats y estado de mejoras del barco
$q = $db->query("
    SELECT b.vitalidad, b.espacios, b.resistencia, b.ruputura,
           b.mejora_tripulacion, b.mejora_vitalidad, b.mejora_resistencia, b.mejora_ruptura,
           COALESCE(o.tier, 0) AS tier
    FROM mybb_op_barcos b
    LEFT JOIN mybb_op_objetos o ON o.objeto_id = b.barco_id COLLATE utf8_unicode_ci
    WHERE b.barco_id = '$barco_id_safe'
    LIMIT 1
");
$barco = $db->fetch_array($q);
if (!$barco) { echo json_encode(['error' => 'Datos del barco no encontrados']); exit; }

$tier    = max(1, min(5, (int)$barco['tier']));
$tierIdx = $tier - 1;

// Verificar que la mejora no está ya aplicada
$col_mejora = 'mejora_' . $tipo;
if ((int)$barco[$col_mejora]) {
    echo json_encode(['error' => 'Esta mejora ya ha sido aplicada a este barco']); exit;
}

// Requisitos previos para ruptura
if ($tipo === 'ruptura') {
    if (!(int)$barco['mejora_vitalidad'] || !(int)$barco['mejora_resistencia']) {
        echo json_encode(['error' => 'Se requiere haber aplicado Mejora de Vitalidad y Mejora de Resistencia primero']); exit;
    }
}

// Costes por tier
$costes = [
    'tripulacion' => [50000, 2500000, 16000000, 70000000, 220000000],
    'vitalidad'   => [75000, 4200000, 24000000, 110000000, 330000000],
    'resistencia' => [60000, 3500000, 20000000, 90000000, 275000000],
    'ruptura'     => [100000, 5500000, 35000000, 140000000, 450000000],
];
$coste = $costes[$tipo][$tierIdx];

// Verificar berries en el cofre del barco
$q_estado = $db->simple_select('op_barco_estado', 'berries', "barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe'");
$estado = $db->fetch_array($q_estado);
$berries_cofre = $estado ? (int)$estado['berries'] : 0;

if ($berries_cofre < $coste) {
    echo json_encode(['error' => 'El cofre del barco no tiene suficientes berries (necesita ' . number_format($coste, 0, ',', '.') . ')']); exit;
}

// Calcular incremento de estadística
$bonus_tripulacion = [2, 4, 6, 8, 12];
$bonus_ruptura     = [1, 1, 2, 2, 3];

switch ($tipo) {
    case 'tripulacion':
        $stat_campo    = 'espacios';
        $incremento    = $bonus_tripulacion[$tierIdx];
        $valor_actual  = (int)$barco['espacios'];
        break;
    case 'vitalidad':
        $stat_campo    = 'vitalidad';
        $incremento    = (int)floor(0.4 * (int)$barco['vitalidad']);
        $valor_actual  = (int)$barco['vitalidad'];
        break;
    case 'resistencia':
        $stat_campo    = 'resistencia';
        $incremento    = (int)floor(0.1 * (int)$barco['resistencia'] + 50);
        $valor_actual  = (int)$barco['resistencia'];
        break;
    case 'ruptura':
        $stat_campo    = 'ruputura';
        $incremento    = $bonus_ruptura[$tierIdx];
        $valor_actual  = (int)$barco['ruputura'];
        break;
}

$valor_nuevo = $valor_actual + $incremento;

// Aplicar mejora
$db->update_query('op_barcos', [
    $stat_campo  => $valor_nuevo,
    $col_mejora  => 1,
], "barco_id='$barco_id_safe'");

// Descontar berries del cofre
$db->update_query('op_barco_estado', [
    'berries' => $berries_cofre - $coste,
], "barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe'");

echo json_encode([
    'ok'           => true,
    'tipo'         => $tipo,
    'stat_campo'   => $stat_campo,
    'valor_nuevo'  => $valor_nuevo,
    'incremento'   => $incremento,
    'berries_cofre'=> $berries_cofre - $coste,
], JSON_UNESCAPED_UNICODE);
