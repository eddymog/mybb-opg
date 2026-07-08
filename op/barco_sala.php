<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco_sala.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(['error' => 'No autenticado']); exit; }

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(['error' => 'Token CSRF inválido']); exit;
}

$action       = $mybb->get_input('action');
$barco_id_raw = trim($mybb->get_input('barco_id'));
$owner_uid    = (int)$mybb->get_input('owner_uid');

$barco_id = $db->escape_string($barco_id_raw);
$q_sub = $db->simple_select('op_objetos', 'subcategoria', "objeto_id='$barco_id'");
$row_sub = $db->fetch_array($q_sub);
if (!$row_sub || strtolower($row_sub['subcategoria']) !== 'barcos') {
    echo json_encode(['error' => 'ID de barco inválido']); exit;
}

// Solo usuarios con oficio Carpintero pueden gestionar salas (o UID 850)
$can_manage_salas = ($uid === 850);
if (!$can_manage_salas && (int)$uid === $owner_uid) {
    // Capitán: solo necesita oficio Carpintero
    $q_carp = $db->query("
        SELECT 1 FROM mybb_op_fichas f
        WHERE f.fid = '$uid'
          AND (f.oficio1 = 'Carpintero' OR f.oficio2 = 'Carpintero')
        LIMIT 1
    ");
    if ($db->fetch_array($q_carp)) { $can_manage_salas = true; }
} elseif (!$can_manage_salas) {
    // Tripulante: necesita rango Carpintero en este barco + oficio Carpintero
    $q_carp = $db->query("
        SELECT 1 FROM mybb_op_barco_tripulacion t
        JOIN mybb_op_fichas f ON f.fid = t.miembro_uid
        WHERE t.barco_id = '$barco_id' AND t.owner_uid = '$owner_uid'
          AND t.miembro_uid = '$uid'
          AND FIND_IN_SET('Carpintero', t.rango) > 0
          AND (f.oficio1 = 'Carpintero' OR f.oficio2 = 'Carpintero')
        LIMIT 1
    ");
    if ($db->fetch_array($q_carp)) { $can_manage_salas = true; }
}
// NPC con rol Carpintero perteneciente al usuario actual
if (!$can_manage_salas) {
    $q_npc_carp = $db->query("
        SELECT 1 FROM mybb_op_barco_npcs
        WHERE barco_id='$barco_id' AND owner_uid='$owner_uid'
          AND tipo='npc' AND rol='Carpintero'
          AND ref_id LIKE '" . (int)$uid . "-%'
        LIMIT 1
    ");
    if ($db->fetch_array($q_npc_carp)) { $can_manage_salas = true; }
}
if (!$can_manage_salas) {
    echo json_encode(['error' => 'Necesitas el oficio de Carpintero para gestionar las salas']); exit;
}

// Verify ownership
$q = $db->simple_select('op_inventario', 'id', "objeto_id='$barco_id' AND uid='$owner_uid'");
if (!$db->fetch_array($q)) { echo json_encode(['error' => 'No posees este barco']); exit; }

// Get max rooms
$q     = $db->query("
    SELECT COALESCE(b.espacios_mejora, 0) AS espacios_mejora,
           COALESCE(b.espacios, 0) AS espacios_trip,
           o.descripcion
    FROM mybb_op_objetos o
    LEFT JOIN mybb_op_barcos b ON b.barco_id = o.objeto_id
    WHERE o.objeto_id = '$barco_id'
    LIMIT 1
");
$bdata             = $db->fetch_array($q);
$max_salas         = $bdata ? (int)$bdata['espacios_mejora'] : 0;
$max_espacios_trip = $bdata ? (int)$bdata['espacios_trip']   : 0;
if (!$max_salas && $bdata && $bdata['descripcion']) {
    if (preg_match('/Espacios\s+de\s+Mejoras\s*:\s*(\d+)/i', $bdata['descripcion'], $m)) {
        $max_salas = (int)$m[1];
    }
}

// ─── Catálogo de tipos de sala ────────────────────────────────────────────────
// [nombre, costo_mejora, costo_trip, berries]
$TIPOS_SALA = [
    'mapa'        => ['nombre' => 'Sala de Mapas',  'mejora' => 1, 'trip' => 5,  'berries' => 200000000,
                      'bonus'  => 'Cartógrafo: crafteo -25% · Timonel: viaje -12h'],
    'cocina'      => ['nombre' => 'Cocina',          'mejora' => 1, 'trip' => 5,  'berries' => 100000000,
                      'bonus'  => 'Chef: +25% efectividad en platos'],
    'taller'      => ['nombre' => 'Taller',          'mejora' => 2, 'trip' => 10, 'berries' => 400000000,
                      'bonus'  => 'Modista / Astillero / Constructor / Ingeniero: crafteo -25%'],
    'enfermeria'  => ['nombre' => 'Enfermería',      'mejora' => 1, 'trip' => 5,  'berries' => 100000000,
                      'bonus'  => 'Farmacólogo: crafteos de fármacos x2'],
    'quirofano'   => ['nombre' => 'Quirófano',       'mejora' => 1, 'trip' => 5,  'berries' => 200000000,
                      'bonus'  => 'Doctor: crafteos x2 eficacia · Biólogo: implantes -1 Espacio'],
    'archivos'    => ['nombre' => 'Archivos',        'mejora' => 2, 'trip' => 10, 'berries' => 400000000,
                      'bonus'  => 'Arqueólogo / Periodista / Contrabandista: crafteo -25% · Comerciante: +10% NPC'],
    'invernadero' => ['nombre' => 'Invernadero',     'mejora' => 1, 'trip' => 5,  'berries' => 300000000,
                      'bonus'  => 'Mayorista / Agreste: Pop Green x2 · Aprovisionador: +1 persona alimentada'],
    'forja'       => ['nombre' => 'Forja',           'mejora' => 1, 'trip' => 5,  'berries' => 100000000,
                      'bonus'  => 'Herrero: crafteo -25%'],
    'corral'      => ['nombre' => 'Corral',          'mejora' => 1, 'trip' => 5,  'berries' => 200000000,
                      'bonus'  => 'Cazador: mascota +5 atributos/Tier · Domador: entrenar +2 por temporada'],
];

// ─── construir ────────────────────────────────────────────────────────────────
if ($action === 'construir') {

    $tipo_raw = trim($mybb->get_input('tipo'));
    if (!isset($TIPOS_SALA[$tipo_raw])) {
        echo json_encode(['error' => 'Tipo de sala inválido']); exit;
    }
    $tipo      = $db->escape_string($tipo_raw);
    $sala_data = $TIPOS_SALA[$tipo_raw];

    // Check not already built
    $q = $db->simple_select('op_barco_salas', 'id',
             "barco_id='$barco_id' AND owner_uid='$owner_uid' AND tipo='$tipo'");
    if ($db->fetch_array($q)) {
        echo json_encode(['error' => 'Ya tienes esta sala construida en tu barco']); exit;
    }

    // Check mejora budget
    $q_all        = $db->simple_select('op_barco_salas', 'tipo',
                        "barco_id='$barco_id' AND owner_uid='$owner_uid'");
    $mejora_usada = 0;
    $trip_usada   = 0;
    while ($r = $db->fetch_array($q_all)) {
        if (isset($TIPOS_SALA[$r['tipo']])) {
            $mejora_usada += (int)$TIPOS_SALA[$r['tipo']]['mejora'];
            $trip_usada   += (int)$TIPOS_SALA[$r['tipo']]['trip'];
        }
    }
    $mejora_nueva = $mejora_usada + (int)$sala_data['mejora'];
    if ($mejora_nueva > $max_salas) {
        echo json_encode(['error' => "No tienes suficientes espacios de mejora (necesitas {$sala_data['mejora']}, disponibles " . ($max_salas - $mejora_usada) . ")"]); exit;
    }

    // Check crew space (trip cost of new sala)
    $trip_nueva = (int)$sala_data['trip'];
    if ($trip_nueva > 0 && $max_espacios_trip > 0) {
        $calc_esp = function($h) {
            $h = max(0, (int)$h);
            if (!$h)       return 1.0;
            if ($h <= 50)  return 0.25;
            if ($h <= 100) return 0.5;
            if ($h <= 300) return 1.0;
            return 2.0 + (int)ceil(($h - 300) / 200);
        };
        $crew_esp = 0.0;
        $q_ow = $db->simple_select('op_fichas', 'altura', "fid='$owner_uid'");
        $ow   = $db->fetch_array($q_ow);
        $crew_esp += $calc_esp($ow ? $ow['altura'] : 0);
        $q_tr = $db->query("SELECT COALESCE(f.altura,0) AS altura FROM mybb_op_barco_tripulacion t LEFT JOIN mybb_op_fichas f ON f.fid=t.miembro_uid WHERE t.barco_id='$barco_id' AND t.owner_uid='$owner_uid'");
        while ($rtr = $db->fetch_array($q_tr)) { $crew_esp += $calc_esp($rtr['altura']); }
        $q_np = $db->query("SELECT CAST(COALESCE(np.altura,'0') AS UNSIGNED) AS altura FROM mybb_op_barco_npcs n LEFT JOIN mybb_op_npcs np ON np.npc_id=n.ref_id WHERE n.barco_id='$barco_id' AND n.owner_uid='$owner_uid'");
        while ($rnp = $db->fetch_array($q_np)) { $crew_esp += $calc_esp($rnp['altura']); }
        if ($crew_esp + $trip_usada + $trip_nueva > $max_espacios_trip) {
            $disp     = max(0.0, $max_espacios_trip - $trip_usada - $crew_esp);
            $disp_str = rtrim(rtrim(number_format($disp, 2), '0'), '.');
            echo json_encode(['error' => "Sin espacio para esta sala: requiere {$trip_nueva} espacios de tripulación, disponibles: {$disp_str}"]); exit;
        }
    }

    // Check and deduct berries from cofre
    $berries_necesarios = (int)$sala_data['berries'];
    $q_cofre = $db->simple_select('op_barco_estado', 'berries',
                   "barco_id='$barco_id' AND owner_uid='$owner_uid'");
    $cofre   = $db->fetch_array($q_cofre);
    $berries_cofre = $cofre ? (int)$cofre['berries'] : 0;
    if ($berries_cofre < $berries_necesarios) {
        $falta = number_format($berries_necesarios - $berries_cofre, 0, ',', '.');
        echo json_encode(['error' => "Berries insuficientes en el cofre (faltan {$falta} berries)"]); exit;
    }

    $nuevos_berries = $berries_cofre - $berries_necesarios;
    if ($cofre) {
        $db->update_query('op_barco_estado', ['berries' => $nuevos_berries],
            "barco_id='$barco_id' AND owner_uid='$owner_uid'");
    } else {
        $db->insert_query('op_barco_estado', [
            'barco_id'  => $barco_id,
            'owner_uid' => $owner_uid,
            'berries'   => 0,
        ]);
    }

    // Auto-assign next slot
    $q_slot  = $db->query("SELECT COALESCE(MAX(slot),0)+1 AS next_slot FROM mybb_op_barco_salas WHERE barco_id='$barco_id' AND owner_uid='$owner_uid'");
    $sl      = $db->fetch_array($q_slot);
    $next_slot = $sl ? (int)$sl['next_slot'] : 1;

    $nombre_safe = $db->escape_string($sala_data['nombre']);
    $bonus_safe  = $db->escape_string($sala_data['bonus']);

    $db->insert_query('op_barco_salas', [
        'barco_id'    => $barco_id,
        'owner_uid'   => $owner_uid,
        'slot'        => $next_slot,
        'tipo'        => $tipo,
        'nombre'      => $nombre_safe,
        'descripcion' => $bonus_safe,
        'imagen'      => '',
    ]);

    echo json_encode([
        'ok'            => true,
        'berries_cofre' => $nuevos_berries,
        'sala'          => [
            'slot'        => $next_slot,
            'tipo'        => $tipo_raw,
            'nombre'      => $sala_data['nombre'],
            'descripcion' => $sala_data['bonus'],
            'imagen'      => '',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── demoler ──────────────────────────────────────────────────────────────────
if ($action === 'demoler' || $action === 'eliminar') {

    $tipo_raw = trim($mybb->get_input('tipo'));
    if ($tipo_raw && !isset($TIPOS_SALA[$tipo_raw])) {
        echo json_encode(['error' => 'Tipo de sala inválido']); exit;
    }

    if ($tipo_raw) {
        $tipo = $db->escape_string($tipo_raw);
        $db->delete_query('op_barco_salas',
            "barco_id='$barco_id' AND owner_uid='$owner_uid' AND tipo='$tipo'");
    } else {
        // Legacy: delete by slot
        $slot = (int)$mybb->get_input('slot');
        if ($slot < 1) { echo json_encode(['error' => 'Parámetros inválidos']); exit; }
        $db->delete_query('op_barco_salas',
            "barco_id='$barco_id' AND owner_uid='$owner_uid' AND slot='$slot'");
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
