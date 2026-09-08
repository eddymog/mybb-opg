<?php
/**
 * barco_viajes.php — AJAX endpoint for ship travel (Viajes tab in barco interior)
 * Actions:
 *   get_data  (GET/POST) — returns crew nav-levels, maps, compasses, route hours, history
 *   viajar    (POST)     — performs the travel dice roll and logs to DB
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'barco_viajes.php');
require_once './../global.php';
require_once './functions/op_functions.php';

header('Content-Type: application/json');

$uid = (int)$mybb->user['uid'];
if (!$uid) {
    echo json_encode(['error' => 'Debes estar autenticado.']); exit;
}

$action    = isset($_REQUEST['action'])    ? (string)$_REQUEST['action']    : '';
$barco_id  = isset($_REQUEST['barco_id'])  ? $db->escape_string(trim((string)$_REQUEST['barco_id']))  : '';
$owner_uid = isset($_REQUEST['owner_uid']) ? (int)$_REQUEST['owner_uid'] : 0;

$_bv_solo  = ($action === 'get_horas_solo' || $action === 'viajar_solo');

if (!$_bv_solo && (!$barco_id || !$owner_uid)) {
    echo json_encode(['error' => 'Parámetros inválidos.']); exit;
}

/* ── Validate user has access to this barco ── */
function _bv_can_access($uid, $barco_id, $owner_uid) {
    global $db;
    if ((int)$uid === (int)$owner_uid) return true;
    $q = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='" . $db->escape_string($barco_id) . "' AND owner_uid='" . (int)$owner_uid . "' AND miembro_uid='" . (int)$uid . "'");
    return (bool)$db->fetch_array($q);
}

if (!$_bv_solo && !_bv_can_access($uid, $barco_id, $owner_uid)) {
    echo json_encode(['error' => 'No tienes acceso a este barco.']); exit;
}

/* ── Check Navegante rango ── */
function _bv_is_navegante($uid, $barco_id, $owner_uid) {
    global $db;
    if ((int)$uid === (int)$owner_uid) {
        $q = $db->simple_select('op_barco_estado', 'owner_rangos',
            "barco_id='" . $db->escape_string($barco_id) . "' AND owner_uid='" . (int)$owner_uid . "'");
        $row = $db->fetch_array($q);
        if ($row && $row['owner_rangos']) {
            $parts = array_filter(array_map('trim', explode(',', $row['owner_rangos'])));
            return in_array('Navegante', $parts);
        }
        return false;
    }
    $q = $db->query("
        SELECT 1 FROM mybb_op_barco_tripulacion t
        WHERE t.barco_id = '" . $db->escape_string($barco_id) . "'
          AND t.owner_uid = '" . (int)$owner_uid . "'
          AND t.miembro_uid = '" . (int)$uid . "'
          AND FIND_IN_SET('Navegante', t.rango) > 0
        LIMIT 1
    ");
    return (bool)$db->fetch_array($q);
}

if (!$_bv_solo && !_bv_is_navegante($uid, $barco_id, $owner_uid)) {
    echo json_encode(['error' => 'Solo el Navegante puede realizar viajes desde el barco.']); exit;
}

/* ── Island times lookup table ── */
$_BV_ISLAS_TIEMPOS = [
    // East Blue
    'Conomi Islands->Isla de Dawn' => 96,     'Isla de Dawn->Conomi Islands' => 96,
    'Conomi Islands->Refugio de Goat' => 120, 'Refugio de Goat->Conomi Islands' => 120,
    'Conomi Islands->Islas Organ' => 48,      'Islas Organ->Conomi Islands' => 48,
    'Conomi Islands->Tequila Wolf' => 72,     'Tequila Wolf->Conomi Islands' => 72,

    'Isla de Dawn->Refugio de Goat' => 48,    'Refugio de Goat->Isla de Dawn' => 48,
    'Isla de Dawn->Islas Organ' => 36,        'Islas Organ->Isla de Dawn' => 36,
    'Isla de Dawn->Tequila Wolf' => 72,       'Tequila Wolf->Isla de Dawn' => 72,
    'Isla de Dawn->Loguetown' => 144,         'Loguetown->Isla de Dawn' => 144,
    'Isla de Dawn->Sabana de Cozia' => 6,     'Sabana de Cozia->Isla de Dawn' => 6,

    'Refugio de Goat->Islas Organ' => 72,     'Islas Organ->Refugio de Goat' => 72,
    'Refugio de Goat->Tequila Wolf' => 72,    'Tequila Wolf->Refugio de Goat' => 72,
    'Refugio de Goat->Loguetown' => 180,
    'Refugio de Goat->Sabana de Cozia' => 72, 'Sabana de Cozia->Refugio de Goat' => 72,

    'Islas Organ->Tequila Wolf' => 42,        'Tequila Wolf->Islas Organ' => 42,
    'Islas Organ->Loguetown' => 90,           'Loguetown->Islas Organ' => 90,
    'Islas Organ->Sabana de Cozia' => 30,     'Sabana de Cozia->Islas Organ' => 30,

    'Tequila Wolf->Loguetown' => 120,         'Loguetown->Tequila Wolf' => 120,
    'Tequila Wolf->Sabana de Cozia' => 84,    'Sabana de Cozia->Tequila Wolf' => 84,

    'Loguetown->Conomi Islands' => 72,
    'Loguetown->Sabana de Cozia' => 72,       'Sabana de Cozia->Loguetown' => 72,
    'Loguetown->Refugio de Goat' => 48,       // reverse above: Refugio->Loguetown=180

    // North Blue
    'Isla Swallow->Reino de Lvneel' => 36,    'Reino de Lvneel->Isla Swallow' => 36,
    'Isla Swallow->Isla de Ivansk' => 84,     'Isla de Ivansk->Isla Swallow' => 84,
    'Isla Swallow->Skjodheilm' => 96,         'Skjodheilm->Isla Swallow' => 96,
    'Isla Swallow->Polo Norte' => 90,         'Polo Norte->Isla Swallow' => 90,

    'Reino de Lvneel->Isla de Ivansk' => 48,  'Isla de Ivansk->Reino de Lvneel' => 48,
    'Reino de Lvneel->Skjodheilm' => 48,      'Skjodheilm->Reino de Lvneel' => 48,
    'Reino de Lvneel->Polo Norte' => 36,      'Polo Norte->Reino de Lvneel' => 36,

    'Isla de Ivansk->Skjodheilm' => 18,       'Skjodheilm->Isla de Ivansk' => 18,
    'Isla de Ivansk->Polo Norte' => 36,       'Polo Norte->Isla de Ivansk' => 36,

    'Skjodheilm->Polo Norte' => 18,           'Polo Norte->Skjodheilm' => 18,

    // South Blue
    'Cliff->Bawic' => 96,                     'Bawic->Cliff' => 96,
    'Cliff->Reino de Sorbet' => 356,          'Reino de Sorbet->Cliff' => 356,
    'Cliff->Korinaru' => 184,                 'Korinaru->Cliff' => 184,
    'Cliff->Briss' => 160,                    'Briss->Cliff' => 160,
    'Cliff->Libertalia' => 242,               'Libertalia->Cliff' => 242,

    'Bawic->Reino de Sorbet' => 218,          'Reino de Sorbet->Bawic' => 286,
    'Bawic->Korinaru' => 108,                 'Korinaru->Bawic' => 108,
    'Bawic->Briss' => 236,                    'Briss->Bawic' => 236,
    'Bawic->Libertalia' => 262,               'Libertalia->Bawic' => 262,

    'Reino de Sorbet->Korinaru' => 180,       'Korinaru->Reino de Sorbet' => 180,
    'Reino de Sorbet->Briss' => 404,          'Briss->Reino de Sorbet' => 404,
    'Reino de Sorbet->Libertalia' => 290,     'Libertalia->Reino de Sorbet' => 290,

    'Korinaru->Briss' => 276,                 'Briss->Korinaru' => 276,
    'Korinaru->Libertalia' => 234,            'Libertalia->Korinaru' => 234,

    'Briss->Libertalia' => 162,               'Libertalia->Briss' => 162,

    // West Blue
    'Las Camps->Ohara' => 104,                'Ohara->Las Camps' => 104,
    'Las Camps->Ballywood' => 216,            'Ballywood->Las Camps' => 216,
    'Las Camps->Archipielago Tako' => 142,    'Archipielago Tako->Las Camps' => 142,
    'Las Camps->Kano' => 284,                 'Kano->Las Camps' => 284,
    'Las Camps->Baratie' => 232,              'Baratie->Las Camps' => 232,

    'Ohara->Ballywood' => 114,                'Ballywood->Ohara' => 114,
    'Ohara->Archipielago Tako' => 108,        'Archipielago Tako->Ohara' => 108,
    'Ohara->Kano' => 188,                     'Kano->Ohara' => 188,
    'Ohara->Baratie' => 242,                  'Baratie->Ohara' => 242,

    'Ballywood->Archipielago Tako' => 174,    'Archipielago Tako->Ballywood' => 174,
    'Ballywood->Kano' => 120,                 'Kano->Ballywood' => 120,
    'Ballywood->Baratie' => 312,              'Baratie->Ballywood' => 312,

    'Archipielago Tako->Kano' => 178,         'Kano->Archipielago Tako' => 178,
    'Archipielago Tako->Baratie' => 140,      'Baratie->Archipielago Tako' => 140,

    'Kano->Baratie' => 282,                   'Baratie->Kano' => 282,
];

function _bv_get_tiempo_ruta($partida, $llegada) {
    global $_BV_ISLAS_TIEMPOS;
    $key = "$partida->$llegada";
    if (isset($_BV_ISLAS_TIEMPOS[$key])) return (int)$_BV_ISLAS_TIEMPOS[$key];
    $keyInv = "$llegada->$partida";
    if (isset($_BV_ISLAS_TIEMPOS[$keyInv])) return (int)$_BV_ISLAS_TIEMPOS[$keyInv];
    return 48; // default fallback
}

function _bv_suceso_naval($dado) {
    if ($dado <= 90) return 'Todo está en calma.';
    if ($dado <= 92) return 'Aparece un navío pirata.';
    if ($dado <= 94) return 'Aparece un buque marine.';
    if ($dado <= 96) return 'Navío de facción predominante.';
    if ($dado <= 98) return 'Basura/Naufragio/Iceberg.';
    if ($dado == 99) {
        $jefes = [1=>'Shichibukai',2=>'Vicealmirante',3=>'Comandante',4=>'CP9'];
        return 'Encuentro con ' . $jefes[rand(1,4)];
    }
    $jefes = [1=>'Yonkou',2=>'Almirante',3=>'CP0',4=>'Bestia Marina'];
    return 'Encuentro con ' . $jefes[rand(1,4)];
}

function _bv_dar_objeto_fid($objeto_id, $fid) {
    global $db;
    $obj = $db->escape_string($objeto_id);
    $fid = (int)$fid;
    $q = $db->query("SELECT cantidad FROM mybb_op_inventario WHERE uid='$fid' AND objeto_id='$obj'");
    $row = $db->fetch_array($q);
    if ($row) {
        $nueva = (int)$row['cantidad'] + 1;
        $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva' WHERE objeto_id='$obj' AND uid='$fid'");
    } else {
        $db->query("INSERT INTO mybb_op_inventario (objeto_id, uid, cantidad) VALUES ('$obj','$fid','1')");
    }
}

/* ══════════════════════════════════════════════════════════════
   ACTION: get_data
   Returns crew navigator data, maps, compasses, route hours
   ══════════════════════════════════════════════════════════════ */
if ($action === 'get_data') {

    // Collect all crew UIDs
    $crew_uids = [(int)$owner_uid];
    $q = $db->query("SELECT miembro_uid FROM mybb_op_barco_tripulacion WHERE barco_id='$barco_id' AND owner_uid='$owner_uid'");
    while ($r = $db->fetch_array($q)) { $crew_uids[] = (int)$r['miembro_uid']; }

    $crew_ids_sql = implode(',', $crew_uids);

    // Navigator levels
    $maxNavegante = 0; $maxTimonel = 0; $maxCartografo = 0;
    $q = $db->query("SELECT fid, oficios FROM mybb_op_fichas WHERE oficios LIKE '%Navegante%' AND fid IN ($crew_ids_sql)");
    while ($r = $db->fetch_array($q)) {
        if (!$r['oficios']) continue;
        $of = @json_decode($r['oficios'], true);
        if (!$of || !isset($of['Navegante'])) continue;
        $navNivel  = (int)($of['Navegante']['nivel'] ?? 0);
        $timNivel  = (int)($of['Navegante']['sub']['Timonel']      ?? 0);
        $cartNivel = (int)($of['Navegante']['sub']['Cart\u00f3grafo'] ?? ($of['Navegante']['sub']['Cartógrafo'] ?? 0));
        if ($navNivel  > $maxNavegante)  $maxNavegante  = $navNivel;
        if ($timNivel  > $maxTimonel)    $maxTimonel    = $timNivel;
        if ($cartNivel > $maxCartografo) $maxCartografo = $cartNivel;
    }

    // Bonificador de oficio (d20 roll bonus)
    $bonificadorOficio = 0;
    if ($maxNavegante == 1)      $bonificadorOficio = 6;
    elseif ($maxNavegante == 2)  $bonificadorOficio = 12;
    elseif ($maxNavegante == 3)  $bonificadorOficio = 18;
    elseif ($maxNavegante == 4)  $bonificadorOficio = 24;
    elseif ($maxNavegante >= 5)  $bonificadorOficio = 32;

    // Time reduction percentage from oficio
    $porcentajeOficio = 0.0;
    if ($maxCartografo == 3) {
        if ($maxNavegante >= 5)  $porcentajeOficio = 0.50;
        elseif ($maxTimonel == 3) $porcentajeOficio = 0.40;
        elseif ($maxTimonel == 2) $porcentajeOficio = 0.30;
        elseif ($maxTimonel == 1) $porcentajeOficio = 0.20;
        else                     $porcentajeOficio = 0.05;
    } elseif ($maxCartografo >= 1 || $maxNavegante >= 1 || $maxTimonel >= 1) {
        if ($maxNavegante >= 5)  $porcentajeOficio = 0.50;
        elseif ($maxTimonel == 3) $porcentajeOficio = 0.40;
        elseif ($maxTimonel == 2) $porcentajeOficio = 0.30;
        elseif ($maxTimonel == 1) $porcentajeOficio = 0.20;
        else                     $porcentajeOficio = 0.05;
    }

    // Maps in ship's chest
    $mar_req     = isset($_REQUEST['mar'])     ? $db->escape_string(trim((string)$_REQUEST['mar']))     : '';
    $llegada_req = isset($_REQUEST['llegada']) ? trim((string)$_REQUEST['llegada'])                     : '';
    $mapas = [];
    $extra_cart = 0;
    if ($maxCartografo == 1) $extra_cart = 2;
    elseif ($maxCartografo >= 2) $extra_cart = 4;

    // Extract all known island names from the routes table (for island-scope detection)
    $_bv_all_islands = [];
    foreach (array_keys($_BV_ISLAS_TIEMPOS) as $_route) {
        foreach (explode('->', $_route) as $_isl) {
            $_bv_all_islands[trim($_isl)] = true;
        }
    }
    $_bv_all_islands = array_keys($_bv_all_islands);

    // Sea keyword → canonical sea name (case-insensitive word boundary)
    $_bv_sea_map = [
        'south' => 'South Blue',
        'north' => 'North Blue',
        'east'  => 'East Blue',
        'west'  => 'West Blue',
    ];

    $q = $db->query("
        SELECT DISTINCT c.objeto_id, COALESCE(o.nombre, c.objeto_id) AS nombre, COALESCE(o.efecto, '') AS efecto
        FROM mybb_op_barco_cofre c
        INNER JOIN mybb_op_objetos o ON o.objeto_id = c.objeto_id
        WHERE c.barco_id = '$barco_id' AND c.owner_uid = '$owner_uid'
          AND o.subcategoria = 'mapas' AND c.cantidad >= 1
    ");
    while ($r = $db->fetch_array($q)) {
        $obj_id = $r['objeto_id'];
        $efecto = $r['efecto'] ?? '';

        // Parse raw bonuses from efecto
        $efectoBonus    = 0;
        $horasReduccion = 0;
        if ($efecto) {
            if (preg_match('/\+(\d+)/u',                                    $efecto, $m)) $efectoBonus    = (int)$m[1];
            if (preg_match('/reducci[oó]n\s+de\s+(\d+)\s+horas?/iu',       $efecto, $m)) $horasReduccion = (int)$m[1];
        }

        // Determine scope: sea-specific → island-specific → universal
        $applies = true;
        if ($efecto) {
            $foundSea = '';
            foreach ($_bv_sea_map as $keyword => $seaName) {
                if (preg_match('/\b' . $keyword . '\b/iu', $efecto)) { $foundSea = $seaName; break; }
            }

            if ($foundSea !== '') {
                // Sea-scoped: only applies if traveling in that sea
                $applies = ($mar_req !== '' && strcasecmp($mar_req, $foundSea) === 0);
            } else {
                // Island-scoped: check if any known island name appears in efecto
                $foundIsland = '';
                foreach ($_bv_all_islands as $_isl) {
                    if (stripos($efecto, $_isl) !== false) { $foundIsland = $_isl; break; }
                }
                if ($foundIsland !== '') {
                    // Applies only if destination matches the island mentioned
                    $applies = ($llegada_req !== '' && strcasecmp($llegada_req, $foundIsland) === 0);
                }
                // else: no sea or island keyword → universal, $applies stays true
            }
        }

        // Apply bonuses only if in scope
        $mapaBonus      = 0;
        $horasReduccionFinal = 0;
        if ($applies) {
            if ($efectoBonus > 0) {
                $mapaBonus = $efectoBonus + $extra_cart;
            } else {
                // Fallback: ID prefix determines sea, case-insensitive
                // MSB* → South Blue, MNB* → North Blue, MWB* → West Blue, MI* → East Blue
                $obj_lower = strtolower($obj_id);
                $idSea = '';
                if      (strncasecmp($obj_id, 'msb', 3) === 0) $idSea = 'South Blue';
                elseif  (strncasecmp($obj_id, 'mnb', 3) === 0) $idSea = 'North Blue';
                elseif  (strncasecmp($obj_id, 'mwb', 3) === 0) $idSea = 'West Blue';
                elseif  (strncasecmp($obj_id, 'mi',  2) === 0 && strncasecmp($obj_id, 'mips', 4) !== 0) $idSea = 'East Blue';

                if ($idSea !== '' && $mar_req !== '' && strcasecmp($mar_req, $idSea) === 0) {
                    $mapaBonus = 4 + $extra_cart;
                }
            }
            $horasReduccionFinal = $horasReduccion;
        }

        $mapas[] = ['objeto_id' => $obj_id, 'nombre' => $r['nombre'], 'mapaBonus' => $mapaBonus, 'horasReduccion' => $horasReduccionFinal];
    }

    // Compasses in ship's chest
    $brujulas = [];
    $q = $db->query("
        SELECT DISTINCT c.objeto_id, COALESCE(o.nombre, c.objeto_id) AS nombre, COALESCE(o.efecto, '') AS efecto
        FROM mybb_op_barco_cofre c
        INNER JOIN mybb_op_objetos o ON o.objeto_id = c.objeto_id
        WHERE c.barco_id = '$barco_id' AND c.owner_uid = '$owner_uid'
          AND o.subcategoria = 'log poses' AND c.cantidad >= 1
    ");
    while ($r = $db->fetch_array($q)) {
        $efectoBonus    = 0;
        $horasReduccion = 0;
        if ($r['efecto']) {
            if (preg_match('/\+(\d+)/u', $r['efecto'], $m))                           $efectoBonus    = (int)$m[1];
            if (preg_match('/(\d+)\s+horas?/iu', $r['efecto'], $m))                   $horasReduccion = (int)$m[1];
        }
        $brujulas[] = ['objeto_id' => $r['objeto_id'], 'nombre' => $r['nombre'], 'efectoBonus' => $efectoBonus, 'horasReduccion' => $horasReduccion];
    }

    // Barco info
    $q = $db->simple_select('op_objetos', 'nombre, alcance', "objeto_id='$barco_id'");
    $barco_info = $db->fetch_array($q);
    $barcoNombre  = $barco_info ? $barco_info['nombre']  : $barco_id;
    $barcoAlcance = $barco_info ? (string)$barco_info['alcance'] : '';

    // Parse alcance to decimal
    $barcoAlcancePct = 0.0;
    if ($barcoAlcance !== '') {
        $v = trim(str_replace(['%', ','], ['', '.'], $barcoAlcance));
        $num = (float)$v;
        if ($num > 1) $num = $num / 100;
        $barcoAlcancePct = $num;
    }

    // Route hours
    $partida_req = isset($_REQUEST['partida']) ? trim((string)$_REQUEST['partida']) : '';
    $llegada_req = isset($_REQUEST['llegada']) ? trim((string)$_REQUEST['llegada']) : '';
    $horasBase = 48;
    if ($partida_req && $llegada_req && $partida_req !== $llegada_req) {
        $horasBase = _bv_get_tiempo_ruta($partida_req, $llegada_req);
    }
    $horasConBarco = $barcoAlcancePct > 0 ? (int)round($horasBase * (1 - $barcoAlcancePct)) : $horasBase;
    // Apply oficio time reduction
    if ($porcentajeOficio > 0) {
        $horasConBarco = max(1, (int)round($horasConBarco * (1 - $porcentajeOficio)));
    }

    // History: last 10 naval voyages from this barco's crew
    $historial = [];
    $q = $db->query("
        SELECT id, uid_viaje, nombre, mar, partida, llegada, horas, fecha_salida, fecha_llegada, temporada, timestamp, log, postViaje, dado_naval
        FROM mybb_op_viajes
        WHERE uid_viaje IN ($crew_ids_sql)
        ORDER BY id DESC LIMIT 10
    ");
    while ($r = $db->fetch_array($q)) { $historial[] = $r; }

    // Bonus fruta: shipu_shipu otorga +10 a tiradas de viaje en barco
    $bonus_fruta = 0;
    $q_fruta = $db->query("SELECT 1 FROM mybb_op_fichas WHERE fid IN ($crew_ids_sql) AND akuma = 'shipu_shipu' LIMIT 1");
    if ($db->fetch_array($q_fruta)) { $bonus_fruta = 10; }

    echo json_encode([
        'ok'               => true,
        'maxNavegante'     => $maxNavegante,
        'maxTimonel'       => $maxTimonel,
        'maxCartografo'    => $maxCartografo,
        'bonificadorOficio'=> $bonificadorOficio,
        'bonusFruta'       => $bonus_fruta,
        'horasBase'        => $horasBase,
        'horasConBarco'    => $horasConBarco,
        'mapas'            => $mapas,
        'brujulas'         => $brujulas,
        'barcoNombre'      => $barcoNombre,
        'barcoAlcancePct'  => $barcoAlcancePct,
        'crewUids'         => implode(',', $crew_uids),
        'historial'        => $historial,
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   ACTION: viajar
   Performs the travel dice roll and logs to DB
   ══════════════════════════════════════════════════════════════ */
if ($action === 'viajar') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Método no permitido.']); exit;
    }

    verify_post_check($_POST['post_code'] ?? '');

    $mar_p              = $db->escape_string(trim($_POST['mar']              ?? ''));
    $partida_p          = $db->escape_string(trim($_POST['partida']          ?? ''));
    $llegada_p          = $db->escape_string(trim($_POST['llegada']          ?? ''));
    $dificultad_p       = $db->escape_string(trim($_POST['dificultad']       ?? '10'));
    $modificador_p      = (int)($_POST['modificador'] ?? 0);
    $tiempo_p           = $db->escape_string(trim($_POST['tiempo']           ?? '48'));
    $id_viajeros_p      = $db->escape_string(trim($_POST['id_viajeros']      ?? ''));
    $barco_p            = $db->escape_string(trim($_POST['barco_nombre']     ?? $barco_id));
    $navegante_npc_p    = ($db->escape_string(trim($_POST['navegante_npc']   ?? 'No')) === 'Si') ? 'Si' : 'No';
    $fecha_salida_p     = $db->escape_string(trim($_POST['fecha_salida']     ?? '1'));
    $fecha_llegada_p    = $db->escape_string(trim($_POST['fecha_llegada']    ?? '1'));
    $temporada_p        = $db->escape_string(trim($_POST['temporada']        ?? 'Verano'));
    $mapa_p             = $db->escape_string(trim($_POST['mapa_nombre']      ?? ''));
    $post_inicio_p      = $db->escape_string(trim($_POST['post_inicio_viaje'] ?? ''));

    if (!$mar_p || !$partida_p || !$llegada_p) {
        echo json_encode(['error' => 'Faltan parámetros obligatorios (mar, partida, llegada).']); exit;
    }
    if ($partida_p === $llegada_p) {
        echo json_encode(['error' => '¡No puedes viajar a la misma isla!']); exit;
    }
    if (!$post_inicio_p) {
        echo json_encode(['error' => 'Debes proporcionar el link del post de inicio del viaje.']); exit;
    }

    // Fetch current user's character name
    $q = $db->simple_select('op_fichas', 'nombre', "fid='$uid'");
    $ficha_row  = $db->fetch_array($q);
    $uid_nombre = $ficha_row ? $db->escape_string($ficha_row['nombre']) : 'UID ' . $uid;

    // Bonus fruta: shipu_shipu — verificar en tripulación completa
    $crew_uids_v = [(int)$owner_uid];
    $q_crew = $db->query("SELECT miembro_uid FROM mybb_op_barco_tripulacion WHERE barco_id='$barco_id' AND owner_uid='$owner_uid'");
    while ($r_crew = $db->fetch_array($q_crew)) { $crew_uids_v[] = (int)$r_crew['miembro_uid']; }
    $crew_ids_v = implode(',', $crew_uids_v);
    $q_fruta = $db->query("SELECT 1 FROM mybb_op_fichas WHERE fid IN ($crew_ids_v) AND akuma = 'shipu_shipu' LIMIT 1");
    if ($db->fetch_array($q_fruta)) { $modificador_p += 10; }

    $islandsMap = [
        'East Blue'  => ['Isla de Dawn','Refugio de Goat','Islas Organ','Tequila Wolf','Loguetown','Sabana de Cozia','Conomi Islands'],
        'North Blue' => ['Isla Swallow','Reino de Lvneel','Isla de Ivansk','Skjodheilm','Polo Norte'],
        'South Blue' => ['Cliff','Bawic','Reino de Sorbet','Korinaru','Briss','Libertalia'],
        'West Blue'  => ['Las Camps','Ohara','Archipielago Tako','Ballywood','Kano','Baratie'],
    ];

    $timestamp       = time();
    $tirada_random   = rand(1, 20);
    $dado_naval      = rand(1, 100);
    $tiempo_viajado  = date('d-m-Y H:i', $timestamp);

    $islands = isset($islandsMap[$mar_p]) ? $islandsMap[$mar_p] : $islandsMap['East Blue'];
    $islands = array_values(array_filter($islands, function($i) use ($partida_p, $llegada_p) {
        return $i !== $partida_p && $i !== $llegada_p;
    }));
    $nombre_isla_random = !empty($islands) ? $islands[rand(0, count($islands) - 1)] : 'una isla desconocida';

    if ($tirada_random == 1) {
        $resultado = "¡Ay, ay, ay, ay, ay! ¡Pifia! Con suerte has llegado a <strong>$nombre_isla_random</strong>.";
    } elseif ($tirada_random == 20) {
        $resultado = '¡Has sacado un crítico! No solo llegáis a la isla, sino que toda la tripulación recibe un Cofre Decente.';
    } elseif (($tirada_random + $modificador_p) >= 10) {
        $resultado = '¡Sobrevivisteis y llegasteis sanos y salvos!';
    } else {
        $resultado = "No lograsteis llegar a vuestro destino, pero sobrevivisteis. Por coincidencia llegasteis a <strong>$nombre_isla_random</strong>.";
    }

    // Process travelers for rewards
    $id_viajeros_array = array_filter(explode(',', $id_viajeros_p), 'is_numeric');
    $id_enteras = '';
    foreach ($id_viajeros_array as $fid_raw) {
        $fid_v = (int)$fid_raw;
        if ($tirada_random == 20) {
            _bv_dar_objeto_fid('CFR002', $fid_v);
        }
        $q_rec = $db->query("SELECT JSON_EXTRACT(oficios, '$.Recolector.sub.Mayorista') AS mayorista FROM mybb_op_fichas WHERE fid='$fid_v'");
        $rec_row = $db->fetch_array($q_rec);
        if ($rec_row && (int)$rec_row['mayorista'] >= 1) {
            _bv_dar_objeto_fid('CRM003', $fid_v);
            if ((int)$rec_row['mayorista'] >= 2) { _bv_dar_objeto_fid('CRM003', $fid_v); }
        }
        $q_n = $db->simple_select('op_fichas', 'nombre', "fid='$fid_v'");
        $n_row = $db->fetch_array($q_n);
        if ($n_row) { $id_enteras .= $db->escape_string($n_row['nombre']) . " ($fid_v), "; }
    }
    $id_enteras = rtrim($id_enteras, ', ');

    $mapa_txt = $mapa_p ? "Tienen un <strong>$mapa_p</strong>. " : '';
    $npc_txt  = $navegante_npc_p === 'Si' ? 'Tienen un <strong>Navegante NPC</strong>. ' : '';
    $suceso   = _bv_suceso_naval($dado_naval);

    $log = "[Tirada de viaje realizada el: $tiempo_viajado] <br>
<strong>$id_enteras</strong> navegan por el mar del <strong>$mar_p</strong> desde <strong>$partida_p</strong> hasta <strong>$llegada_p</strong>. <br>
Salen de puerto el <strong>Día $fecha_salida_p de $temporada_p</strong> y llegan el <strong>Día $fecha_llegada_p de $temporada_p</strong>. <br>
Están en un barco <strong>$barco_p</strong>. $mapa_txt$npc_txt<br>
El viaje toma un transcurso de <strong>$tiempo_p</strong> horas. <br>
La ventaja del modificador es <strong>+$modificador_p</strong> y la tirada de dado 1d20 ha sido <strong>$tirada_random</strong>. <br>
$resultado <br>
<strong>Suceso</strong>: $suceso ($dado_naval) <br>
<strong>Post de inicio de viaje:</strong> $post_inicio_p";

    $db->query("
        INSERT INTO mybb_op_avisos (uid, nombre, categoria, resumen, descripcion, url)
        VALUES ('$uid','$uid_nombre','viaje','Viaje Naval','$log','')
    ");
    $db->query("
        INSERT INTO mybb_op_viajes (uid_viaje, nombre, barco_id, mar, partida, llegada, dificultad, modificador, horas, viajeros,
            fecha_salida, fecha_llegada, temporada, timestamp, log, postViaje, dado_naval)
        VALUES ('$uid','$uid_nombre','$barco_id','$mar_p','$partida_p','$llegada_p','$dificultad_p','$modificador_p','$tiempo_p',
            '$id_viajeros_p','$fecha_salida_p','$fecha_llegada_p','$temporada_p','$timestamp','$log','$post_inicio_p','$dado_naval')
    ");

    echo json_encode([
        'ok'             => true,
        'tirada'         => $tirada_random,
        'dado_naval'     => $dado_naval,
        'resultado'      => $resultado,
        'suceso'         => $suceso,
        'log'            => $log,
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   ACTION: get_horas_solo
   Returns base route hours for a given partida→llegada (no ship)
   ══════════════════════════════════════════════════════════════ */
if ($action === 'get_horas_solo') {
    $partida = trim((string)($_REQUEST['partida'] ?? ''));
    $llegada = trim((string)($_REQUEST['llegada'] ?? ''));
    if (!$partida || !$llegada || $partida === $llegada) {
        echo json_encode(['error' => 'Ruta inválida.']); exit;
    }
    $horasBase = _bv_get_tiempo_ruta($partida, $llegada);
    $horas = $horasBase * 2;
    echo json_encode(['ok' => true, 'horas' => $horas]);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   ACTION: viajar_solo
   Solo travel (no ship, no crew) — anyone can use this
   ══════════════════════════════════════════════════════════════ */
if ($action === 'viajar_solo') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Método no permitido.']); exit;
    }

    verify_post_check($_POST['post_code'] ?? '');

    $mar_p         = $db->escape_string(trim($_POST['mar']              ?? ''));
    $partida_p     = $db->escape_string(trim($_POST['partida']          ?? ''));
    $llegada_p     = $db->escape_string(trim($_POST['llegada']          ?? ''));
    $tiempo_p      = max(1, (int)($_POST['tiempo'] ?? 48));
    $fecha_salida_p  = $db->escape_string(trim($_POST['fecha_salida']   ?? '1'));
    $fecha_llegada_p = $db->escape_string(trim($_POST['fecha_llegada']  ?? '1'));
    $temporada_p   = $db->escape_string(trim($_POST['temporada']        ?? 'Verano'));
    $post_inicio_p = $db->escape_string(trim($_POST['post_inicio_viaje'] ?? ''));

    if (!$mar_p || !$partida_p || !$llegada_p) {
        echo json_encode(['error' => 'Faltan parámetros obligatorios (mar, partida, llegada).']); exit;
    }
    if ($partida_p === $llegada_p) {
        echo json_encode(['error' => '¡No puedes viajar a la misma isla!']); exit;
    }
    if (!$post_inicio_p) {
        echo json_encode(['error' => 'Debes proporcionar el link del post de inicio del viaje.']); exit;
    }

    $q = $db->simple_select('op_fichas', 'nombre', "fid='$uid'");
    $ficha_row  = $db->fetch_array($q);
    $uid_nombre = $ficha_row ? $db->escape_string($ficha_row['nombre']) : 'UID ' . $uid;

    $timestamp      = time();
    $tirada_random  = rand(1, 20);
    $dado_naval     = rand(1, 100);
    $tiempo_viajado = date('d-m-Y H:i', $timestamp);

    $islandsMap = [
        'East Blue'  => ['Isla de Dawn','Refugio de Goat','Islas Organ','Tequila Wolf','Loguetown','Sabana de Cozia','Conomi Islands'],
        'North Blue' => ['Isla Swallow','Reino de Lvneel','Isla de Ivansk','Skjodheilm','Polo Norte'],
        'South Blue' => ['Cliff','Bawic','Reino de Sorbet','Korinaru','Briss','Libertalia'],
        'West Blue'  => ['Las Camps','Ohara','Archipielago Tako','Ballywood','Kano','Baratie'],
    ];
    $islands = isset($islandsMap[$mar_p]) ? $islandsMap[$mar_p] : $islandsMap['East Blue'];
    $islands = array_values(array_filter($islands, function($i) use ($partida_p, $llegada_p) {
        return $i !== $partida_p && $i !== $llegada_p;
    }));
    $nombre_isla_random = !empty($islands) ? $islands[rand(0, count($islands) - 1)] : 'una isla desconocida';

    if ($tirada_random == 1) {
        $resultado = "¡Ay, ay, ay, ay, ay! ¡Pifia! Con suerte has llegado a <strong>$nombre_isla_random</strong>.";
    } elseif ($tirada_random == 20) {
        $resultado = '¡Has sacado un crítico! Llegas a tu destino y recibes un Cofre Decente.';
    } elseif (($tirada_random) >= 10) {
        $resultado = '¡Llegaste sano y salvo!';
    } else {
        $resultado = "No lograste llegar a tu destino, pero sobreviviste. Por coincidencia llegaste a <strong>$nombre_isla_random</strong>.";
    }

    if ($tirada_random == 20) {
        _bv_dar_objeto_fid('CFR002', $uid);
    }
    $q_rec = $db->query("SELECT JSON_EXTRACT(oficios, '$.Recolector.sub.Mayorista') AS mayorista FROM mybb_op_fichas WHERE fid='$uid'");
    $rec_row = $db->fetch_array($q_rec);
    if ($rec_row && (int)$rec_row['mayorista'] >= 1) {
        _bv_dar_objeto_fid('CRM003', $uid);
        if ((int)$rec_row['mayorista'] >= 2) { _bv_dar_objeto_fid('CRM003', $uid); }
    }

    $suceso = _bv_suceso_naval($dado_naval);

    $log = "[Tirada de viaje realizada el: $tiempo_viajado] <br>
<strong>$uid_nombre</strong> viaja en solitario por el mar del <strong>$mar_p</strong> desde <strong>$partida_p</strong> hasta <strong>$llegada_p</strong>. <br>
Sale de puerto el <strong>Día $fecha_salida_p de $temporada_p</strong> y llega el <strong>Día $fecha_llegada_p de $temporada_p</strong>. <br>
El viaje toma un transcurso de <strong>$tiempo_p</strong> horas. <br>
La tirada de dado 1d20 ha sido <strong>$tirada_random</strong>. <br>
$resultado <br>
<strong>Suceso</strong>: $suceso ($dado_naval) <br>
<strong>Post de inicio de viaje:</strong> $post_inicio_p";

    $db->query("
        INSERT INTO mybb_op_avisos (uid, nombre, categoria, resumen, descripcion, url)
        VALUES ('$uid','$uid_nombre','viaje','Viaje Solitario','$log','')
    ");
    $db->query("
        INSERT INTO mybb_op_viajes (uid_viaje, nombre, barco_id, mar, partida, llegada, dificultad, modificador, horas, viajeros,
            fecha_salida, fecha_llegada, temporada, timestamp, log, postViaje, dado_naval)
        VALUES ('$uid','$uid_nombre','','$mar_p','$partida_p','$llegada_p','10','0','$tiempo_p',
            '$uid','$fecha_salida_p','$fecha_llegada_p','$temporada_p','$timestamp','$log','$post_inicio_p','$dado_naval')
    ");

    echo json_encode([
        'ok'         => true,
        'tirada'     => $tirada_random,
        'dado_naval' => $dado_naval,
        'resultado'  => $resultado,
        'suceso'     => $suceso,
        'log'        => $log,
    ]);
    exit;
}

// Unknown action
echo json_encode(['error' => 'Acción no reconocida.']);
