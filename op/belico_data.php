<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'belico_data.php');
ob_start();
require_once "./../global.php";
require_once "./functions/op_functions.php";
ob_clean();

header('Content-Type: application/json; charset=utf-8');

$uid = intval($mybb->user['uid']);
if ($uid === 0) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$tid = isset($mybb->input['tid']) ? intval($mybb->input['tid']) : 0;

$cols = "nivel, fuerza, fuerza_pasiva, agilidad, agilidad_pasiva,
         punteria, punteria_pasiva, destreza, destreza_pasiva,
         resistencia, resistencia_pasiva, reflejos, reflejos_pasiva,
         voluntad, voluntad_pasiva,
         vitalidad, energia, haki, vitalidad_pasiva, energia_pasiva, haki_pasiva";

$ficha = null;

// If replying to a thread, use the stats from the first post in that thread
if ($tid > 0) {
    $qtp = $db->query("SELECT $cols FROM mybb_op_thread_personaje WHERE tid='$tid' AND uid='$uid' ORDER BY pid ASC LIMIT 1");
    $ficha = $db->fetch_array($qtp);
}

if (!$ficha) {
    $qf = $db->query("SELECT $cols FROM mybb_op_fichas WHERE fid='$uid'");
    $ficha = $db->fetch_array($qf);
}

if (!$ficha) {
    echo json_encode(['error' => 'no_ficha']);
    exit;
}

$nivel                = intval($ficha['nivel']);
$fuerza_completa      = intval($ficha['fuerza'])      + intval($ficha['fuerza_pasiva']);
$agilidad_completa    = intval($ficha['agilidad'])    + intval($ficha['agilidad_pasiva']);
$punteria_completa    = intval($ficha['punteria'])    + intval($ficha['punteria_pasiva']);
$destreza_completa    = intval($ficha['destreza'])    + intval($ficha['destreza_pasiva']);
$resistencia_completa = intval($ficha['resistencia']) + intval($ficha['resistencia_pasiva']);
$reflejos_completa    = intval($ficha['reflejos'])    + intval($ficha['reflejos_pasiva']);
$voluntad_completa    = intval($ficha['voluntad'])    + intval($ficha['voluntad_pasiva']);

// Compute resource maximums — same formula as BBCustom_ficha_calc_stats
$vida_max = intval($ficha['vitalidad']) + intval($ficha['vitalidad_pasiva']) +
    (int)floor(intval($ficha['fuerza_pasiva'])*6  + intval($ficha['resistencia_pasiva'])*15 +
               intval($ficha['destreza_pasiva'])*4  + intval($ficha['agilidad_pasiva'])*3 +
               intval($ficha['punteria_pasiva'])*2  + intval($ficha['reflejos_pasiva'])*1 +
               intval($ficha['voluntad_pasiva'])*1);

$energia_max = intval($ficha['energia']) + intval($ficha['energia_pasiva']) +
    (int)floor(intval($ficha['destreza_pasiva'])*4  + intval($ficha['agilidad_pasiva'])*5 +
               intval($ficha['fuerza_pasiva'])*2    + intval($ficha['resistencia_pasiva'])*4 +
               intval($ficha['punteria_pasiva'])*5  + intval($ficha['reflejos_pasiva'])*1 +
               intval($ficha['voluntad_pasiva'])*1);

$haki_max = intval($ficha['haki']) + intval($ficha['haki_pasiva']) +
    (int)floor(intval($ficha['voluntad_pasiva']) * 10);

// Virtue bonuses (highest tier wins — check from highest to lowest)
foreach (['V039' => 20, 'V038' => 15, 'V037' => 10] as $vid => $bonus) {
    $qv = $db->query("SELECT COUNT(*) AS c FROM mybb_op_virtudes_usuarios WHERE uid='$uid' AND virtud_id='$vid'");
    if ((int)($db->fetch_array($qv)['c']) > 0) { $vida_max += $nivel * $bonus; break; }
}
foreach (['V041' => 15, 'V040' => 10] as $vid => $bonus) {
    $qv = $db->query("SELECT COUNT(*) AS c FROM mybb_op_virtudes_usuarios WHERE uid='$uid' AND virtud_id='$vid'");
    if ((int)($db->fetch_array($qv)['c']) > 0) { $energia_max += $nivel * $bonus; break; }
}
foreach (['V059' => 10, 'V058' => 5] as $vid => $bonus) {
    $qv = $db->query("SELECT COUNT(*) AS c FROM mybb_op_virtudes_usuarios WHERE uid='$uid' AND virtud_id='$vid'");
    if ((int)($db->fetch_array($qv)['c']) > 0) { $haki_max += $nivel * $bonus; break; }
}

$tec_aprendidas = ['todo' => []];
$qt = $db->query("
    SELECT t.tid, t.nombre, t.rama, t.estilo, t.clase, t.tipo, t.tier, t.efectos
    FROM mybb_op_tecnicas t
    INNER JOIN mybb_op_tec_aprendidas ta ON t.tid = ta.tid
    WHERE ta.uid = '$uid'
    ORDER BY t.tid, t.rama
");
while ($tec = $db->fetch_array($qt)) {
    array_push($tec_aprendidas['todo'], $tec);
}

$objetos       = [];
$objetos_array = [];
$qi = $db->query("
    SELECT o.*, i.objeto_id
    FROM mybb_op_objetos o
    INNER JOIN mybb_op_inventario i ON o.objeto_id = i.objeto_id
    WHERE i.uid = '$uid'
    ORDER BY o.categoria, o.nombre
");
while ($row = $db->fetch_array($qi)) {
    $key = $row['objeto_id'];
    if (!isset($objetos[$key])) { $objetos[$key] = []; }
    array_push($objetos[$key], $row);
    if (!in_array($key, $objetos_array)) { $objetos_array[] = $key; }
}

echo json_encode([
    'nivel'                => $nivel,
    'fuerza_completa'      => $fuerza_completa,
    'agilidad_completa'    => $agilidad_completa,
    'punteria_completa'    => $punteria_completa,
    'destreza_completa'    => $destreza_completa,
    'resistencia_completa' => $resistencia_completa,
    'reflejos_completa'    => $reflejos_completa,
    'voluntad_completa'    => $voluntad_completa,
    'vida_max'             => $vida_max,
    'energia_max'          => $energia_max,
    'haki_max'             => $haki_max,
    'tec_aprendidas'       => $tec_aprendidas,
    'objetos'              => $objetos,
    'objetos_array'        => $objetos_array,
]);
