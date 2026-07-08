<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'wanted.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
global $templates, $mybb, $db;

// $query_npcs = $db->query(" SELECT * FROM `mybb_op_npcs` WHERE wanted='1' ORDER BY `mybb_op_npcs`.`reputacion`, `mybb_op_npcs`.`nombre` ASC; ");
// $query_ficha = $db->query(" SELECT f.*, u.avatar  FROM `mybb_op_fichas` as f INNER JOIN `mybb_users` as u ON f.fid=u.uid WHERE f.wanted='1' ORDER BY f.`reputacion`, f.`nombre` ASC; ");

// $npcs = array();

// $npc_input_id = $mybb->get_input('npc_id');
// $wanted = $_POST["wanted"];

// // SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=10 AND virtud_id='V028';

// if ($wanted == 'true') {
//     $db->query(" UPDATE mybb_op_fichas SET wantedGuardado = reputacion WHERE wanted='1'; ");
//     return;
// }

// while ($npc = $db->fetch_array($query_npcs)) {

//     if (substr($npc['avatar2'], 0, 3) == "???") { $npc['avatar2'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png'; }
//     if (substr($npc['reputacion'], 0, 3) == "???") { $npc['reputacion'] = '???'; }

//     $npc = array(
//         "nombre"=>$npc['nombre'],
//         "avatar2"=>$npc['avatar2'],
//         "reputacion"=>$npc['reputacion'] * 1000000,
//         "faccion"=>$npc['faccion'],
//         "npc"=>"1",
//         "npc_id"=>$npc['npc_id'],
//         "muerto"=>"0",
//     );

//     array_push($npcs, $npc);
// }

// while ($npc = $db->fetch_array($query_ficha)) {

//     $fid = $npc['fid'];
//     $wanted_repu = floatval($npc['wanted_repu']);

//     if ($npc['avatar'] == '') { $npc['avatar'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png'; }

//     if ($npc['faccion'] == 'Pirateria') { $npc['faccion'] = 'Pirata';  }
//     if ($npc['faccion'] == 'CipherPol') { $npc['faccion'] = 'Gobierno';  }
//     // if ($npc['faccion'] == 'CipherPol') { $npc['faccion'] = 'Gobierno';  }
//     // if ($npc['faccion'] == 'CipherPol') { $npc['faccion'] = 'Gobierno';  }

//     $has_mejora_reputacion = false; // V017 Fama +10%
//     $has_mejora_wanted = false;     // V018 El Mas Buscado, +10% Wanted
//     $has_baja_reputacion = false;   // D013 Don Nadie -10%
//     $has_baja_wanted = false;       // V010 Desapercibido,  -10% Wanted

//     $has_pasado_maldito1 = false;
//     $has_pasado_maldito2 = false;
    
//     $query_virtud1 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=$fid AND virtud_id='V017'; ");
//     $query_virtud2 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=$fid AND virtud_id='V018'; ");
//     $query_virtud3 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=$fid AND virtud_id='D013'; ");
//     $query_virtud4 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=$fid AND virtud_id='V010'; ");
//     $query_virtud5 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=$fid AND virtud_id='V022'; ");
//     $query_virtud6 = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid=$fid AND virtud_id='V023'; ");

//     while ($q = $db->fetch_array($query_virtud1)) { $has_mejora_reputacion = true; }
//     while ($q = $db->fetch_array($query_virtud2)) { $has_mejora_wanted = true; }
//     while ($q = $db->fetch_array($query_virtud3)) { $has_baja_reputacion = true; }
//     while ($q = $db->fetch_array($query_virtud4)) { $has_baja_wanted = true; }
//     while ($q = $db->fetch_array($query_virtud5)) { $has_pasado_maldito1 = true; }
//     while ($q = $db->fetch_array($query_virtud6)) { $has_pasado_maldito2 = true; }


//     // Obtener reputación positiva y negativa
//     $reputacion_positiva = floatval($npc['reputacion_positiva']);
//     $reputacion_negativa = floatval($npc['reputacion_negativa']);
    
//     // Aplicar modificadores de virtudes a las reputaciones individuales
//     if ($has_mejora_reputacion) { 
//         $reputacion_positiva = $reputacion_positiva * 1.10;
//         $reputacion_negativa = $reputacion_negativa * 1.10;
//     }
//     if ($has_baja_reputacion) { 
//         $reputacion_positiva = $reputacion_positiva * 0.90;
//         $reputacion_negativa = $reputacion_negativa * 0.90;
//     }

//     // Calcular reputación total para determinar el rango
//     $reputacion_total = $reputacion_positiva + $reputacion_negativa;

//     // Calcular wanted según los rangos establecidos
//     if ($reputacion_total >= 0 && $reputacion_total <= 150) {
//         // Reputación Negativa x 200.000 + Reputación Positiva x 100.000
//         $wanted_repu = ($reputacion_negativa * 200000) + ($reputacion_positiva * 100000);
//     } else if ($reputacion_total >= 151 && $reputacion_total <= 300) {
//         // Reputación Negativa x 300.000 + Reputación Positiva x 150.000
//         $wanted_repu = ($reputacion_negativa * 300000) + ($reputacion_positiva * 150000);
//     } else if ($reputacion_total >= 301 && $reputacion_total <= 450) {
//         // Reputación Negativa x 400.000 + Reputación Positiva x 200.000
//         $wanted_repu = ($reputacion_negativa * 400000) + ($reputacion_positiva * 200000);
//     } else if ($reputacion_total >= 451 && $reputacion_total <= 600) {
//         // Reputación Negativa x 500.000 + Reputación Positiva x 250.000
//         $wanted_repu = ($reputacion_negativa * 500000) + ($reputacion_positiva * 250000);
//     } else if ($reputacion_total >= 601 && $reputacion_total <= 900) {
//         // Reputación Negativa x 600.000 + Reputación Positiva x 300.000
//         $wanted_repu = ($reputacion_negativa * 600000) + ($reputacion_positiva * 300000);
//     } else if ($reputacion_total >= 901 && $reputacion_total <= 1200) {
//         // Reputación Negativa x 700.000 + Reputación Positiva x 350.000
//         $wanted_repu = ($reputacion_negativa * 700000) + ($reputacion_positiva * 350000);
//     } else if ($reputacion_total >= 1201 && $reputacion_total <= 1500) {
//         // Reputación Negativa x 850.000 + Reputación Positiva x 425.000
//         $wanted_repu = ($reputacion_negativa * 850000) + ($reputacion_positiva * 425000);
//     } else if ($reputacion_total >= 1501) {
//         // Reputación Negativa x 1.000.000 + Reputación Positiva x 500.000
//         $wanted_repu = ($reputacion_negativa * 1000000) + ($reputacion_positiva * 500000);
//     }

//     if ($has_mejora_wanted) { $wanted_repu = $wanted_repu * 1.10; }
//     if ($has_baja_wanted) { $wanted_repu = $wanted_repu * 0.90; }

//     if ($has_pasado_maldito1) { $wanted_repu += 15000000; }
//     if ($has_pasado_maldito2) { $wanted_repu += 30000000; }

//     $npc = array(
//         "nombre"=>$npc['nombre'],
//         "avatar2"=>$npc['avatar'],
//         "reputacion"=>$wanted_repu,
//         "faccion"=>$npc['faccion'],
//         "npc"=>"0",
//         "npc_id"=>$fid,
//         "muerto"=>$npc['muerto'],
//     );

//     array_push($npcs, $npc);
// }


// $npcs_json = json_encode($npcs);

// eval("\$page = \"".$templates->get("op_wanted")."\";");
// output_page($page);

/**
 * Calcula el wanted “en vivo” para una fila de mybb_op_fichas (con avatar/user).
 * NO escribe en BD; solo devuelve el número calculado.
 */
function calcular_wanted(array $row, $db): float
{
    $fid = (int)$row['fid'];

    // Flags de virtudes (todo en un select)
    $virtudes = [];
    $res = $db->query("
        SELECT virtud_id
        FROM mybb_op_virtudes_usuarios
        WHERE uid = {$fid}
          AND virtud_id IN ('V017','V018','D013','V010','V022','V023')
    ");
    while ($v = $db->fetch_array($res)) {
        $virtudes[$v['virtud_id']] = true;
    }

    $has_mejora_reputacion = !empty($virtudes['V017']); // +10% rep
    $has_mejora_wanted     = !empty($virtudes['V018']); // +10% wanted
    $has_baja_reputacion   = !empty($virtudes['D013']); // -10% rep
    $has_baja_wanted       = !empty($virtudes['V010']); // -10% wanted
    $has_pasado_maldito1   = !empty($virtudes['V022']); // +15M
    $has_pasado_maldito2   = !empty($virtudes['V023']); // +30M

    // Reputaciones base
    $reputacion_positiva = (float)$row['reputacion_positiva'];
    $reputacion_negativa = (float)$row['reputacion_negativa'];

    // Modificadores a reputaciones
    if ($has_mejora_reputacion) {
        $reputacion_positiva *= 1.10;
        $reputacion_negativa *= 1.10;
    }
    if ($has_baja_reputacion) {
        $reputacion_positiva *= 0.90;
        $reputacion_negativa *= 0.90;
    }

    $reputacion_total = $reputacion_positiva + $reputacion_negativa;

    // Tramos
    if ($reputacion_total <= 150) {
        $wanted = ($reputacion_negativa * 60000) + ($reputacion_positiva * 30000);
    } elseif ($reputacion_total <= 300) {
        $wanted = ($reputacion_negativa * 90000) + ($reputacion_positiva * 45000);
    } elseif ($reputacion_total <= 450) {
        $wanted = ($reputacion_negativa * 120000) + ($reputacion_positiva * 60000);
    } elseif ($reputacion_total <= 600) {
        $wanted = ($reputacion_negativa * 150000) + ($reputacion_positiva * 75000);
    } elseif ($reputacion_total <= 900) {
        $wanted = ($reputacion_negativa * 180000) + ($reputacion_positiva * 90000);
    } elseif ($reputacion_total <= 1200) {
        $wanted = ($reputacion_negativa * 210000) + ($reputacion_positiva * 105000);
    } elseif ($reputacion_total <= 1500) {
        $wanted = ($reputacion_negativa * 255000) + ($reputacion_positiva * 127500);
    } elseif ($reputacion_total <= 1800) {
        $wanted = ($reputacion_negativa * 300000) + ($reputacion_positiva * 150000);
    } elseif ($reputacion_total <= 2100) {
        $wanted = ($reputacion_negativa * 330000) + ($reputacion_positiva * 165000);
    } elseif ($reputacion_total <= 2400) {
        $wanted = ($reputacion_negativa * 360000) + ($reputacion_positiva * 180000);
    } elseif ($reputacion_total <= 2700) {
        $wanted = ($reputacion_negativa * 390000) + ($reputacion_positiva * 195000);
    } elseif ($reputacion_total <= 3000) {
        $wanted = ($reputacion_negativa * 420000) + ($reputacion_positiva * 210000);
    } elseif ($reputacion_total <= 3300) {
        $wanted = ($reputacion_negativa * 450000) + ($reputacion_positiva * 225000);
    } elseif ($reputacion_total <= 3600) {
        $wanted = ($reputacion_negativa * 480000) + ($reputacion_positiva * 240000);
    } elseif ($reputacion_total <= 3900) {
        $wanted = ($reputacion_negativa * 510000) + ($reputacion_positiva * 255000);
    } elseif ($reputacion_total <= 4200) {
        $wanted = ($reputacion_negativa * 540000) + ($reputacion_positiva * 270000);
    } elseif ($reputacion_total <= 4500) {
        $wanted = ($reputacion_negativa * 570000) + ($reputacion_positiva * 285000);
    } elseif ($reputacion_total <= 4800) {
        $wanted = ($reputacion_negativa * 600000) + ($reputacion_positiva * 300000);
    } elseif ($reputacion_total <= 5100) {
        $wanted = ($reputacion_negativa * 630000) + ($reputacion_positiva * 315000);
    } elseif ($reputacion_total <= 5400) {
        $wanted = ($reputacion_negativa * 660000) + ($reputacion_positiva * 330000);
    } elseif ($reputacion_total <= 5700) {
        $wanted = ($reputacion_negativa * 690000) + ($reputacion_positiva * 345000);
    } elseif ($reputacion_total <= 6000) {
        $wanted = ($reputacion_negativa * 720000) + ($reputacion_positiva * 360000);
    } else { // 6001 en adelante
        $wanted = ($reputacion_negativa * 750000) + ($reputacion_positiva * 375000);
    }

    // Modificadores de wanted
    if ($has_mejora_wanted)  { $wanted *= 1.10; }
    if ($has_baja_wanted)    { $wanted *= 0.90; }
    if ($has_pasado_maldito1){ $wanted += 15000000; }
    if ($has_pasado_maldito2){ $wanted += 30000000; }

    return (float)$wanted;
}

// ---------------------------------------------------------------------
// Permisos
// ---------------------------------------------------------------------
$g_is_staff = is_staff($mybb->user['uid']);

// ---------------------------------------------------------------------
// Actualización masiva si llega el flag wanted=true (sin transacción)
// ---------------------------------------------------------------------
$wanted = $mybb->get_input('wanted'); // lee de GET/POST indistinto

if ($wanted === 'true') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $q = $db->query("
            SELECT f.*, u.avatar
            FROM mybb_op_fichas AS f
            INNER JOIN mybb_users AS u ON f.fid = u.uid
            WHERE f.wanted = '1'
        ");

        $updated = 0;
        while ($row = $db->fetch_array($q)) {
            $calc  = calcular_wanted($row, $db);
            $bonus = max(0, (int)($row['wantedcustom'] ?? 0));
            $valor = (int)round(max(0, $calc + $bonus));
            // OJO: aquí SIN prefijo
            $db->update_query('op_fichas', ['wantedGuardado' => $valor], "fid=".(int)$row['fid']);
            $updated++;
        }

        echo json_encode(['ok' => true, 'updated' => $updated]);
        exit;
    } catch (Throwable $e) {
        // Log útil para ver el error real en Apache/Nginx/PHP
        error_log('WANTED UPDATE ERROR: '.$e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Update failed']);
        exit;
    }
}

// ---------------------------------------------------------------------
// Recálculo por UIDs específicas (staff)
// ---------------------------------------------------------------------
if ($wanted === 'recalcular_uid') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$g_is_staff) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sin permisos']);
        exit;
    }

    $uids_raw = trim($mybb->get_input('uids'));
    if ($uids_raw === '') {
        echo json_encode(['ok' => false, 'error' => 'No se proporcionaron UIDs']);
        exit;
    }

    $uids = array_values(array_filter(array_map('intval', explode(',', $uids_raw))));
    if (empty($uids)) {
        echo json_encode(['ok' => false, 'error' => 'UIDs inválidas']);
        exit;
    }

    $results = [];
    foreach ($uids as $target_uid) {
        $q = $db->query("
            SELECT f.*, u.avatar
            FROM mybb_op_fichas AS f
            INNER JOIN mybb_users AS u ON f.fid = u.uid
            WHERE f.fid = '$target_uid'
            LIMIT 1
        ");
        $row = $db->fetch_array($q);
        if (!$row) {
            $results[] = ['uid' => $target_uid, 'ok' => false, 'error' => 'Ficha no encontrada'];
            continue;
        }
        $calc  = calcular_wanted($row, $db);
        $bonus = max(0, (int)($row['wantedcustom'] ?? 0));
        $valor = (int)round(max(0, $calc + $bonus));
        $db->update_query('op_fichas', ['wantedGuardado' => $valor], "fid=$target_uid");
        $results[] = [
            'uid'    => $target_uid,
            'ok'     => true,
            'nombre' => $row['nombre'],
            'formula' => (int)round($calc),
            'bonus'   => $bonus,
            'wanted'  => $valor,
        ];
    }

    echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------
// Render: construir JSON devolviendo SIEMPRE wantedGuardado
// ---------------------------------------------------------------------

$query_npcs = $db->query("
  SELECT *
  FROM {$db->table_prefix}op_npcs
  WHERE wanted='1'
  ORDER BY reputacion, nombre ASC
");

$query_ficha = $db->query("
  SELECT f.*, u.avatar
  FROM mybb_op_fichas AS f
  INNER JOIN mybb_users AS u ON f.fid=u.uid
  WHERE f.wanted='1'
  ORDER BY f.reputacion, f.nombre ASC
");

// Consulta adicional para TODOS los Marines (independiente de wanted)
$query_marines = $db->query("
  SELECT f.*, u.avatar
  FROM mybb_op_fichas AS f
  INNER JOIN mybb_users AS u ON f.fid=u.uid
  WHERE f.faccion='Marina'
  ORDER BY f.rango DESC, f.nombre ASC
");

// Consulta adicional para NPCs Marines
$query_npcs_marines = $db->query("
  SELECT *
  FROM {$db->table_prefix}op_npcs
  WHERE faccion='Marina'
  ORDER BY rango DESC, nombre ASC
");

$npcs = [];

// NPCs
while ($npc = $db->fetch_array($query_npcs)) {
    if (substr((string)$npc['avatar2'], 0, 3) === '???') {
        $npc['avatar2'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png';
    }
    if (substr((string)$npc['reputacion'], 0, 3) === '???') {
        $npc['reputacion'] = '???';
    }

    $npcs[] = [
        'nombre'     => $npc['nombre'],
        'avatar2'    => $npc['avatar2'],
        'reputacion' => (float)$npc['reputacion'] * 1000000,
        'faccion'    => $npc['faccion'],
        'rango'      => isset($npc['rango']) ? $npc['rango'] : '',
        'npc'        => '1',
        'npc_id'     => $npc['npc_id'],
        'muerto'     => '0',
    ];
}

// Usuarios (usar siempre lo guardado)
while ($row = $db->fetch_array($query_ficha)) {
    $fid = (int)$row['fid'];

    if (empty($row['avatar'])) {
        $row['avatar'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png';
    }

    if ($row['faccion'] === 'Pirateria') { $row['faccion'] = 'Pirata'; }
    elseif ($row['faccion'] === 'CipherPol') { $row['faccion'] = 'Gobierno'; }

    $wanted_stored = isset($row['wantedGuardado']) ? (float)$row['wantedGuardado'] : 0.0;

    $npcs[] = [
        'nombre'     => $row['nombre'],
        'avatar2'    => $row['avatar'],
        'reputacion' => $wanted_stored, // <- SIEMPRE el guardado
        'faccion'    => $row['faccion'],
        'rango'      => isset($row['rango']) ? $row['rango'] : '',
        'npc'        => '0',
        'npc_id'     => $fid,
        'muerto'     => $row['muerto'],
    ];
}

// NPCs Marines (todos, independiente de wanted)
while ($npc = $db->fetch_array($query_npcs_marines)) {
    // Evitar duplicados si ya se agregó por wanted
    $existe = false;
    foreach ($npcs as $existing) {
        if ($existing['npc'] === '1' && $existing['npc_id'] === $npc['npc_id']) {
            $existe = true;
            break;
        }
    }
    
    if (!$existe) {
        if (substr((string)$npc['avatar2'], 0, 3) === '???') {
            $npc['avatar2'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png';
        }
        if (substr((string)$npc['reputacion'], 0, 3) === '???') {
            $npc['reputacion'] = '0';
        }

        $npcs[] = [
            'nombre'     => $npc['nombre'],
            'avatar2'    => $npc['avatar2'],
            'reputacion' => (float)$npc['reputacion'] * 1000000,
            'faccion'    => $npc['faccion'],
            'rango'      => isset($npc['rango']) ? $npc['rango'] : '',
            'npc'        => '1',
            'npc_id'     => $npc['npc_id'],
            'muerto'     => '0',
        ];
    }
}

// Usuarios Marines (todos, independiente de wanted)
while ($row = $db->fetch_array($query_marines)) {
    $fid = (int)$row['fid'];
    
    // Evitar duplicados si ya se agregó por wanted
    $existe = false;
    foreach ($npcs as $existing) {
        if ($existing['npc'] === '0' && $existing['npc_id'] === $fid) {
            $existe = true;
            break;
        }
    }
    
    if (!$existe) {
        if (empty($row['avatar'])) {
            $row['avatar'] = '/images/op/misc/WantePerfilOculto_One_Piece_Gaiden_Foro_Rol.png';
        }

        $wanted_stored = isset($row['wantedGuardado']) ? (float)$row['wantedGuardado'] : 0.0;

        $npcs[] = [
            'nombre'     => $row['nombre'],
            'avatar2'    => $row['avatar'],
            'reputacion' => $wanted_stored,
            'faccion'    => $row['faccion'],
            'rango'      => isset($row['rango']) ? $row['rango'] : '',
            'npc'        => '0',
            'npc_id'     => $fid,
            'muerto'     => $row['muerto'],
        ];
    }
}

$npcs_json = json_encode($npcs, JSON_UNESCAPED_UNICODE);

eval('$page = "' . $templates->get('op_wanted') . '";');
output_page($page);