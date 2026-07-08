<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco_tripulacion.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');


$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(array('error' => 'No autenticado')); exit; }

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(array('error' => 'Token CSRF invalido')); exit;
}

$action    = trim($mybb->get_input('action'));
$barco_id  = trim($mybb->get_input('barco_id'));
$owner_uid = (int)$mybb->get_input('owner_uid');

if (!$barco_id || !$owner_uid) {
    echo json_encode(array('error' => 'Faltan parametros')); exit;
}
$barco_id_safe = $db->escape_string($barco_id);

$q_sub = $db->simple_select('op_objetos', 'subcategoria', "objeto_id='$barco_id_safe'");
$row_sub = $db->fetch_array($q_sub);
if (!$row_sub || strtolower($row_sub['subcategoria']) !== 'barcos') {
    echo json_encode(array('error' => 'ID de barco invalido')); exit;
}

$q = $db->simple_select('op_inventario', 'id', "objeto_id='$barco_id_safe' AND uid='$owner_uid'");
if (!$db->fetch_array($q)) {
    echo json_encode(array('error' => 'Barco no encontrado')); exit;
}

$is_vice = false;
if ($uid !== $owner_uid) {
    $q = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid' AND FIND_IN_SET('Vicecapitan', rango) > 0");
    if ($db->fetch_array($q)) $is_vice = true;
}

$is_superadmin = ($uid === 850);

// Determine if viewer is the current captain (owner's rangos or a member's rango)
$is_capitan = false;
if ($uid === $owner_uid) {
    $q_cap = $db->simple_select('op_barco_estado', 'owner_rangos',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    $cap_row = $db->fetch_array($q_cap);
    if ($cap_row && strpos(',' . $cap_row['owner_rangos'] . ',', ',Capitan,') !== false) {
        $is_capitan = true;
    }
} else {
    $q_cap = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid' AND FIND_IN_SET('Capitan', rango) > 0");
    if ($db->fetch_array($q_cap)) $is_capitan = true;
}

// Whether any captain currently exists for this ship
$current_cap_exists = $is_capitan;
if (!$current_cap_exists) {
    $q_cap2 = $db->simple_select('op_barco_estado', 'owner_rangos',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    $cap_row2 = $db->fetch_array($q_cap2);
    if ($cap_row2 && strpos(',' . $cap_row2['owner_rangos'] . ',', ',Capitan,') !== false) {
        $current_cap_exists = true;
    }
}
if (!$current_cap_exists) {
    $q_cap3 = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND FIND_IN_SET('Capitan', rango) > 0");
    if ($db->fetch_array($q_cap3)) $current_cap_exists = true;
}

// Who can assign the Capitán role: superadmin, current captain, or owner when no captain exists yet
$can_assign_cap = $is_superadmin || $is_capitan || ($uid === $owner_uid && !$current_cap_exists);

// ── Acciones del dueño del barco ──────────────────────────────────────────

if ($action === 'invitar') {
    if ($uid !== $owner_uid && !$is_vice) { echo json_encode(array('error' => 'Sin permiso para invitar')); exit; }

    $miembro_uid = (int)$mybb->get_input('miembro_uid');
    if (!$miembro_uid) { echo json_encode(array('error' => 'UID invalido')); exit; }
    if ($miembro_uid === $uid) { echo json_encode(array('error' => 'No puedes invitarte a ti mismo')); exit; }

    $q = $db->simple_select('users', 'uid, username', "uid='$miembro_uid'");
    $usuario = $db->fetch_array($q);
    if (!$usuario) { echo json_encode(array('error' => 'Usuario no encontrado')); exit; }

    // Ya en la tripulación
    $q = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$miembro_uid'");
    if ($db->fetch_array($q)) { echo json_encode(array('error' => 'Este usuario ya es tripulante')); exit; }

    // Ya tiene invitación pendiente
    $q = $db->simple_select('op_barco_invitaciones', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$miembro_uid'");
    if ($db->fetch_array($q)) { echo json_encode(array('error' => 'Ya existe una invitacion pendiente')); exit; }

    // ── Comprobación de espacio de tripulación ──
    {
        $q_bmax  = $db->query("SELECT COALESCE(b.espacios,0) AS espacios FROM mybb_op_barcos b WHERE b.barco_id='$barco_id_safe' LIMIT 1");
        $bmax    = $db->fetch_array($q_bmax);
        $max_esp = $bmax ? (int)$bmax['espacios'] : 0;
        if ($max_esp > 0) {
            $calc_esp = function($h) {
                $h = max(0, (int)$h);
                if (!$h)       return 1.0;
                if ($h <= 50)  return 0.25;
                if ($h <= 100) return 0.5;
                if ($h <= 300) return 1.0;
                return 2.0 + (int)ceil(($h - 300) / 200);
            };
            $q_ah      = $db->simple_select('op_fichas', 'altura', "fid='$miembro_uid'");
            $ah        = $db->fetch_array($q_ah);
            $esp_nuevo = $calc_esp($ah ? $ah['altura'] : 0);
            $q_ow      = $db->simple_select('op_fichas', 'altura', "fid='$owner_uid'");
            $ow        = $db->fetch_array($q_ow);
            $crew_esp  = $calc_esp($ow ? $ow['altura'] : 0);
            $q_ow_aus = $db->simple_select('op_barco_estado', 'owner_ausente', "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
            $ow_aus_row = $db->fetch_array($q_ow_aus);
            if ($ow_aus_row && !empty($ow_aus_row['owner_ausente'])) { $crew_esp = 0; }
            $q_tr = $db->query("SELECT COALESCE(f.altura,0) AS altura, COALESCE(t.ausente,0) AS ausente FROM mybb_op_barco_tripulacion t LEFT JOIN mybb_op_fichas f ON f.fid=t.miembro_uid WHERE t.barco_id='$barco_id_safe' AND t.owner_uid='$owner_uid'");
            while ($rtr = $db->fetch_array($q_tr)) { if (!$rtr['ausente']) { $crew_esp += $calc_esp($rtr['altura']); } }
            $q_np = $db->query("SELECT CAST(COALESCE(np.altura,'0') AS UNSIGNED) AS altura, COALESCE(n.ausente,0) AS ausente FROM mybb_op_barco_npcs n LEFT JOIN mybb_op_npcs np ON np.npc_id=n.ref_id WHERE n.barco_id='$barco_id_safe' AND n.owner_uid='$owner_uid'");
            while ($rnp = $db->fetch_array($q_np)) { if (!$rnp['ausente']) { $crew_esp += $calc_esp($rnp['altura']); } }
            $TRIP_MAP   = ['mapa'=>5,'cocina'=>5,'taller'=>10,'enfermeria'=>5,'quirofano'=>5,'archivos'=>10,'invernadero'=>5,'forja'=>5,'corral'=>5];
            $trip_usada = 0;
            $q_sl = $db->simple_select('op_barco_salas', 'tipo', "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
            while ($rsl = $db->fetch_array($q_sl)) {
                if (isset($TRIP_MAP[$rsl['tipo']])) { $trip_usada += $TRIP_MAP[$rsl['tipo']]; }
            }
            if ($crew_esp + $esp_nuevo + $trip_usada > $max_esp) {
                $disp     = max(0.0, $max_esp - $trip_usada - $crew_esp);
                $disp_str = rtrim(rtrim(number_format($disp, 2), '0'), '.');
                echo json_encode(array('error' => "Sin espacio en el barco para este tripulante (necesita {$esp_nuevo} espacio(s), disponibles: {$disp_str})")); exit;
            }
        }
    }

    $db->insert_query('op_barco_invitaciones', array(
        'barco_id'    => $barco_id_safe,
        'owner_uid'   => $owner_uid,
        'miembro_uid' => $miembro_uid,
        'fecha'       => TIME_NOW,
    ));

    echo json_encode(array(
        'ok'       => true,
        'username' => $usuario['username'],
    ), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'expulsar') {
    if ($uid !== $owner_uid && !$is_vice) { echo json_encode(array('error' => 'Sin permiso para expulsar')); exit; }

    $trip_id = (int)$mybb->get_input('trip_id');
    if (!$trip_id) { echo json_encode(array('error' => 'ID invalido')); exit; }

    if ($is_vice) {
        $q = $db->simple_select('op_barco_tripulacion', 'rango',
            "id='$trip_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        $target_exp = $db->fetch_array($q);
        if (!$target_exp) { echo json_encode(array('error' => 'Tripulante no encontrado')); exit; }
        if (strpos(',' . $target_exp['rango'] . ',', ',Vicecapitan,') !== false) {
            echo json_encode(array('error' => 'El Vicecapitan no puede expulsar a otro Vicecapitan')); exit;
        }
    }

    $db->delete_query('op_barco_tripulacion',
        "id='$trip_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");

    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

// ── Acciones del invitado ─────────────────────────────────────────────────

} elseif ($action === 'aceptar') {
    $inv_id = (int)$mybb->get_input('inv_id');
    if (!$inv_id) { echo json_encode(array('error' => 'ID invalido')); exit; }

    $q = $db->simple_select('op_barco_invitaciones', 'id',
        "id='$inv_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid'");
    if (!$db->fetch_array($q)) { echo json_encode(array('error' => 'Invitacion no encontrada')); exit; }

    $db->insert_query('op_barco_tripulacion', array(
        'barco_id'    => $barco_id_safe,
        'owner_uid'   => $owner_uid,
        'miembro_uid' => $uid,
        'fecha'       => TIME_NOW,
        'ausente'     => 1,
    ));
    $db->delete_query('op_barco_invitaciones', "id='$inv_id'");

    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'rechazar') {
    $inv_id = (int)$mybb->get_input('inv_id');
    if (!$inv_id) { echo json_encode(array('error' => 'ID invalido')); exit; }

    $db->delete_query('op_barco_invitaciones',
        "id='$inv_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid'");

    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'abandonar') {
    if ($uid === $owner_uid) { echo json_encode(array('error' => 'El dueno no puede abandonar su propio barco')); exit; }

    $q = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid'");
    if (!$db->fetch_array($q)) { echo json_encode(array('error' => 'No eres tripulante de este barco')); exit; }

    $db->delete_query('op_barco_tripulacion',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid'");

    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'asignar_rango') {
    if ($uid !== $owner_uid && !$is_vice && !$is_capitan && !$is_superadmin) { echo json_encode(array('error' => 'Sin permiso para asignar rangos')); exit; }

    $trip_id    = (int)$mybb->get_input('trip_id');
    $rangos_raw = trim($mybb->get_input('rangos'));
    if (!$trip_id) { echo json_encode(array('error' => 'ID invalido')); exit; }

    $rangos_validos = array('Capitan', 'Vicecapitan', 'Tesorero', 'Navegante', 'Carpintero');
    $rangos = array();
    if ($rangos_raw !== '') {
        foreach (explode(',', $rangos_raw) as $r) {
            $r = trim($r);
            if ($r !== '' && in_array($r, $rangos_validos, true)) {
                $rangos[] = $r;
            }
        }
        $rangos = array_unique($rangos);
    }

    // Vicecapitan implica Tesorero: no guardar ambos
    if (in_array('Vicecapitan', $rangos)) {
        $rangos = array_values(array_filter($rangos, function ($r) { return $r !== 'Tesorero'; }));
    }

    $q = $db->simple_select('op_barco_tripulacion', 'id, rango, miembro_uid',
        "id='$trip_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    $target_trip = $db->fetch_array($q);
    if (!$target_trip) { echo json_encode(array('error' => 'Tripulante no encontrado')); exit; }

    if (in_array('Vicecapitan', $rangos) && $is_capitan && !$is_superadmin && (int)$target_trip['miembro_uid'] === $uid) {
        echo json_encode(array('error' => 'El Capitán no puede asignarse el rango de Vicecapitán a sí mismo')); exit;
    }

    // Capitan: check permission and auto-transfer from previous holder
    if (in_array('Capitan', $rangos)) {
        if (!$can_assign_cap) {
            echo json_encode(array('error' => 'Sin permiso para asignar el rango de Capitán')); exit;
        }
        // Remove Capitan from owner's rangos if present
        $q_est = $db->simple_select('op_barco_estado', 'owner_rangos',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        $est_row = $db->fetch_array($q_est);
        if ($est_row) {
            $new_owner_r = implode(',', array_values(array_filter(explode(',', $est_row['owner_rangos']), function ($r) { return $r !== 'Capitan' && $r !== ''; })));
            $db->update_query('op_barco_estado', array('owner_rangos' => $new_owner_r),
                "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        }
        // Remove Capitan from any other member
        $q_others = $db->simple_select('op_barco_tripulacion', 'id, rango',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND id!='$trip_id' AND FIND_IN_SET('Capitan', rango) > 0");
        while ($other = $db->fetch_array($q_others)) {
            $new_r = implode(',', array_values(array_filter(explode(',', $other['rango']), function ($r) { return $r !== 'Capitan' && $r !== ''; })));
            $db->update_query('op_barco_tripulacion', array('rango' => $new_r), "id='" . (int)$other['id'] . "'");
        }
    }

    if ($is_vice) {
        if (in_array('Vicecapitan', $rangos)) {
            echo json_encode(array('error' => 'El Vicecapitan no puede asignar ese rango')); exit;
        }
        if (strpos(',' . $target_trip['rango'] . ',', ',Vicecapitan,') !== false) {
            echo json_encode(array('error' => 'No puedes modificar los rangos de un Vicecapitan')); exit;
        }
    }

    if (in_array('Vicecapitan', $rangos)) {
        $q = $db->simple_select('op_barco_tripulacion', 'id',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND FIND_IN_SET('Vicecapitan', rango) > 0 AND id!='$trip_id'");
        if ($db->fetch_array($q)) { echo json_encode(array('error' => 'Ya hay un Vicecapitán en este barco')); exit; }
        $q_ow = $db->simple_select('op_barco_estado', 'owner_rangos',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        $ow_row = $db->fetch_array($q_ow);
        if ($ow_row && strpos(',' . $ow_row['owner_rangos'] . ',', ',Vicecapitan,') !== false) {
            echo json_encode(array('error' => 'Ya hay un Vicecapitán en este barco')); exit;
        }
    }

    if (in_array('Navegante', $rangos)) {
        $q = $db->simple_select('op_barco_tripulacion', 'id',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND FIND_IN_SET('Navegante', rango) > 0 AND id!='$trip_id'");
        if ($db->fetch_array($q)) { echo json_encode(array('error' => 'Ya hay un Navegante en este barco')); exit; }
        // Comprobar también si el capitán ya es Navegante
        $q_cap = $db->simple_select('op_barco_estado', 'owner_rangos',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        $cap_estado = $db->fetch_array($q_cap);
        if ($cap_estado && strpos(',' . $cap_estado['owner_rangos'] . ',', ',Navegante,') !== false) {
            echo json_encode(array('error' => 'Ya hay un Navegante en este barco')); exit;
        }
    }

    $rango_str  = implode(',', $rangos);
    $rango_safe = $db->escape_string($rango_str);
    $db->update_query('op_barco_tripulacion', array('rango' => $rango_safe),
        "id='$trip_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");

    echo json_encode(array('ok' => true, 'rango' => $rango_str), JSON_UNESCAPED_UNICODE);

// ── Roles del capitán (Navegante / Carpintero) ─────────────────────────────

} elseif ($action === 'asignar_rango_capitan') {
    if ($uid !== $owner_uid && !$is_superadmin && !$is_capitan && !$is_vice) { echo json_encode(array('error' => 'Sin permiso')); exit; }

    $rangos_raw = trim($mybb->get_input('rangos'));
    $rangos_validos_cap = array('Capitan', 'Vicecapitan', 'Navegante', 'Carpintero');
    $rangos = array();
    if ($rangos_raw !== '') {
        foreach (explode(',', $rangos_raw) as $r) {
            $r = trim($r);
            if ($r !== '' && in_array($r, $rangos_validos_cap, true)) {
                $rangos[] = $r;
            }
        }
        $rangos = array_unique($rangos);
    }

    // Vicecapitan: unicidad — no puede haber otro en la tripulación
    if (in_array('Vicecapitan', $rangos)) {
        $q_vc = $db->simple_select('op_barco_tripulacion', 'id',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND FIND_IN_SET('Vicecapitan', rango) > 0");
        if ($db->fetch_array($q_vc)) {
            echo json_encode(array('error' => 'Ya hay un Vicecapitán en este barco')); exit;
        }
    }

    // Capitan: check permission and auto-transfer from any current member
    if (in_array('Capitan', $rangos)) {
        if (!$can_assign_cap) {
            echo json_encode(array('error' => 'Sin permiso para asignar el rango de Capitán')); exit;
        }
        // Remove Capitan from any member who currently holds it
        $q_others = $db->simple_select('op_barco_tripulacion', 'id, rango',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND FIND_IN_SET('Capitan', rango) > 0");
        while ($other = $db->fetch_array($q_others)) {
            $new_r = implode(',', array_values(array_filter(explode(',', $other['rango']), function ($r) { return $r !== 'Capitan' && $r !== ''; })));
            $db->update_query('op_barco_tripulacion', array('rango' => $new_r), "id='" . (int)$other['id'] . "'");
        }
    }

    if (in_array('Navegante', $rangos)) {
        $q = $db->simple_select('op_barco_tripulacion', 'id',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND FIND_IN_SET('Navegante', rango) > 0");
        if ($db->fetch_array($q)) { echo json_encode(array('error' => 'Ya hay un Navegante en este barco')); exit; }
    }

    $rango_str  = implode(',', $rangos);
    $rango_safe = $db->escape_string($rango_str);

    $q = $db->simple_select('op_barco_estado', 'berries',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    if ($db->fetch_array($q)) {
        $db->update_query('op_barco_estado', array('owner_rangos' => $rango_safe),
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    } else {
        $db->insert_query('op_barco_estado', array(
            'barco_id'     => $barco_id_safe,
            'owner_uid'    => $owner_uid,
            'berries'      => 0,
            'owner_rangos' => $rango_safe,
        ));
    }

    echo json_encode(array('ok' => true, 'rango' => $rango_str), JSON_UNESCAPED_UNICODE);

// ── Acciones del dueño del NPC/Mascota ───────────────────────────────────

} elseif ($action === 'add_npc') {
    $ref_id = trim($mybb->get_input('ref_id'));
    if (!preg_match('/^([0-9]+)-(NPC|PET)[A-Z0-9]+$/i', $ref_id, $m)) {
        echo json_encode(array('error' => 'ID de NPC/Mascota invalido')); exit;
    }
    $npc_owner_uid = (int)$m[1];
    $tipo = strtolower($m[2]);

    if ($uid !== $npc_owner_uid) {
        echo json_encode(array('error' => 'Solo el dueno del NPC/Mascota puede añadirlo')); exit;
    }
    if ($uid !== $owner_uid) {
        $q = $db->simple_select('op_barco_tripulacion', 'id',
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND miembro_uid='$uid'");
        if (!$db->fetch_array($q)) {
            echo json_encode(array('error' => 'No eres tripulante de este barco')); exit;
        }
    }

    $ref_safe = $db->escape_string($ref_id);
    $q = $db->simple_select('op_npcs', 'npc_id, nombre', "npc_id='$ref_safe'");
    $npc = $db->fetch_array($q);
    if (!$npc) { echo json_encode(array('error' => 'NPC/Mascota no encontrado')); exit; }

    $q = $db->simple_select('op_barco_npcs', 'id',
        "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND ref_id='$ref_safe'");
    if ($db->fetch_array($q)) { echo json_encode(array('error' => 'Ya esta en el barco')); exit; }

    $db->insert_query('op_barco_npcs', array(
        'barco_id'  => $barco_id_safe,
        'owner_uid' => $owner_uid,
        'ref_id'    => $ref_safe,
        'tipo'      => $tipo,
    ));

    echo json_encode(array(
        'ok'  => true,
        'npc' => array(
            'id'     => (int)$db->insert_id(),
            'ref_id' => $ref_id,
            'tipo'   => $tipo,
            'nombre' => $npc['nombre'],
            'rol'    => '',
        ),
    ), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'remove_npc') {
    $entry_id = (int)$mybb->get_input('entry_id');
    if (!$entry_id) { echo json_encode(array('error' => 'ID invalido')); exit; }

    $q = $db->simple_select('op_barco_npcs', 'ref_id',
        "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    $entry = $db->fetch_array($q);
    if (!$entry) { echo json_encode(array('error' => 'Entrada no encontrada')); exit; }

    $npc_owner = 0;
    if (preg_match('/^([0-9]+)-/', $entry['ref_id'], $m)) {
        $npc_owner = (int)$m[1];
    }
    if ($uid !== $owner_uid && $uid !== $npc_owner) {
        echo json_encode(array('error' => 'Sin permiso para retirar este NPC/Mascota')); exit;
    }

    $db->delete_query('op_barco_npcs',
        "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");

    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'set_npc_rol') {
    $entry_id = (int)$mybb->get_input('entry_id');
    $rol_raw  = trim($mybb->get_input('rol'));

    if (!$entry_id) { echo json_encode(array('error' => 'ID inválido')); exit; }

    $roles_validos = array('', 'Carpintero', 'Navegante');
    if (!in_array($rol_raw, $roles_validos, true)) {
        echo json_encode(array('error' => 'Rol inválido')); exit;
    }

    // Obtener la entrada y verificar que es un NPC (no mascota) y pertenece al usuario
    $q = $db->simple_select('op_barco_npcs', 'ref_id, tipo',
        "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
    $entry = $db->fetch_array($q);
    if (!$entry) { echo json_encode(array('error' => 'NPC no encontrado en este barco')); exit; }
    if ($entry['tipo'] !== 'npc') { echo json_encode(array('error' => 'Solo los NPCs pueden tener este rol')); exit; }

    $npc_owner = 0;
    if (preg_match('/^([0-9]+)-/', $entry['ref_id'], $m)) { $npc_owner = (int)$m[1]; }
    if ($uid !== $npc_owner) {
        echo json_encode(array('error' => 'Solo el dueño del NPC puede asignarle un rol')); exit;
    }

    // Si se asigna un rol funcional, retirar ese mismo rol de cualquier otro NPC del usuario en este barco
    if ($rol_raw !== '') {
        $rol_safe = $db->escape_string($rol_raw);
        $db->update_query('op_barco_npcs',
            array('rol' => ''),
            "barco_id='$barco_id_safe' AND owner_uid='$owner_uid' AND rol='$rol_safe' AND ref_id LIKE '" . (int)$uid . "-%'"
        );
    }

    $db->update_query('op_barco_npcs', array('rol' => $rol_raw), "id='$entry_id'");

    echo json_encode(array('ok' => true, 'rol' => $rol_raw), JSON_UNESCAPED_UNICODE);

} elseif ($action === 'toggle_ausente') {

    $tipo          = trim($mybb->get_input('tipo')); // 'trip' | 'npc' | 'owner'
    $entry_id      = (int)$mybb->get_input('entry_id');
    $nuevo_ausente = $mybb->get_input('ausente') === 'true' ? 1 : 0;

    if ($tipo === 'owner') {
        if ($uid !== $owner_uid && !$is_capitan && !$is_vice) { echo json_encode(array('error' => 'Sin permiso')); exit; }
        if (!$nuevo_ausente) {
            $q_cross = $db->simple_select('op_barco_estado', 'barco_id', "owner_uid='$owner_uid' AND owner_ausente=0 AND barco_id!='$barco_id_safe'");
            if ($cross = $db->fetch_array($q_cross)) {
                echo json_encode(array('ok' => false, 'error' => 'Ya estás disponible en el barco ' . $cross['barco_id'] . '. Márcate como ausente allí primero.'), JSON_UNESCAPED_UNICODE); exit;
            }
        }
        $q = $db->simple_select('op_barco_estado', 'berries', "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        if ($db->fetch_array($q)) {
            $db->update_query('op_barco_estado', array('owner_ausente' => $nuevo_ausente), "barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        } else {
            $db->insert_query('op_barco_estado', array('barco_id' => $barco_id_safe, 'owner_uid' => $owner_uid, 'berries' => 0, 'owner_rangos' => '', 'owner_ausente' => $nuevo_ausente));
        }

    } elseif ($tipo === 'trip') {
        if (!$entry_id) { echo json_encode(array('error' => 'ID invalido')); exit; }
        $q   = $db->simple_select('op_barco_tripulacion', 'miembro_uid', "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        $row = $db->fetch_array($q);
        if (!$row) { echo json_encode(array('error' => 'Tripulante no encontrado')); exit; }
        if ($uid !== $owner_uid && !$is_vice && !$is_capitan && $uid !== (int)$row['miembro_uid']) {
            echo json_encode(array('error' => 'Sin permiso')); exit;
        }
        if (!$nuevo_ausente) {
            $miembro_uid_cross = (int)$row['miembro_uid'];
            $q_cross = $db->simple_select('op_barco_tripulacion', 'barco_id', "miembro_uid='$miembro_uid_cross' AND ausente=0 AND NOT (id='$entry_id')");
            if ($cross = $db->fetch_array($q_cross)) {
                echo json_encode(array('ok' => false, 'error' => 'Este tripulante ya está disponible en el barco ' . $cross['barco_id'] . '. Márcalo como ausente allí primero.'), JSON_UNESCAPED_UNICODE); exit;
            }
        }
        $db->update_query('op_barco_tripulacion', array('ausente' => $nuevo_ausente), "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");

    } elseif ($tipo === 'npc') {
        if (!$entry_id) { echo json_encode(array('error' => 'ID invalido')); exit; }
        $q   = $db->simple_select('op_barco_npcs', 'ref_id', "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");
        $row = $db->fetch_array($q);
        if (!$row) { echo json_encode(array('error' => 'NPC no encontrado')); exit; }
        $npc_owner = 0;
        if (preg_match('/^([0-9]+)-/', $row['ref_id'], $m)) { $npc_owner = (int)$m[1]; }
        if ($uid !== $owner_uid && !$is_vice && $uid !== $npc_owner) {
            echo json_encode(array('error' => 'Sin permiso')); exit;
        }
        $db->update_query('op_barco_npcs', array('ausente' => $nuevo_ausente), "id='$entry_id' AND barco_id='$barco_id_safe' AND owner_uid='$owner_uid'");

    } else {
        echo json_encode(array('error' => 'Tipo invalido')); exit;
    }

    echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);

} else {
    echo json_encode(array('error' => 'Accion desconocida'));
}
