<?php

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'coliseo_ficha.php');

require_once "./../global.php";
require_once "./functions/op_functions.php";
require_once MYBB_ROOT."inc/plugins/lib/spoiler.php";

// ─── Auth ────────────────────────────────────────────────────────────────────
$user_uid  = $mybb->user['uid'];
$query_uid = intval($mybb->get_input('uid')) ?: $user_uid;

if ($user_uid == 0) {
    redirect($mybb->settings['bburl'] . '/member.php?action=login');
    exit;
}

$torneo_id = intval($mybb->get_input('torneo_id'));
$accion    = $mybb->get_input('accion');
$is_owner  = ($user_uid == $query_uid);
$fileVersion = rand();

// ─── Helper: presupuesto de stats para un nivel dado ─────────────────────────
// Cada nivel da 10 pts, los niveles 10/20/30/40/50/60/70/80/90/100 dan 20.
function calcularPresupuestoStats(int $nivel): int {
    $bonus_niveles = [10 => 20, 20 => 20, 30 => 20, 40 => 20, 50 => 20, 60 => 20, 70 => 20, 80 => 20, 90 => 20, 100 => 20];
    $total = 0;
    for ($nv = 2; $nv <= $nivel; $nv++) {
        $total += $bonus_niveles[$nv] ?? 10;
    }
    return $total;
}

// ─── Cargar torneo y su ruleset ───────────────────────────────────────────────
$torneo  = null;
$ruleset = [
    'nivel'          => ['tipo' => 'real',  'valor' => null, 'min' => null, 'max' => null],
    'akuma'          => ['permitido' => true],
    'haki'           => ['permitido' => true, 'tipos' => ['kenbun','buso','hao'], 'tier_max' => 8],
    'estilos'        => ['tipo' => 'todos', 'lista' => []],
    'belicas'        => ['max_disciplinas' => 12],
    'raciales'       => ['permitidas' => true],
    'tecnicas_unicas'=> ['permitidas' => true],
];

if ($torneo_id > 0) {
    $q_torneo = $db->query("SELECT * FROM mybb_op_coliseo_torneos WHERE id='$torneo_id'");
    while ($t = $db->fetch_array($q_torneo)) {
        $torneo = $t;
        $ruleset_decoded = json_decode($t['ruleset'], true);
        if ($ruleset_decoded) {
            $ruleset = array_merge($ruleset, $ruleset_decoded);
        }
    }
}

// ─── Nivel efectivo para la ficha ─────────────────────────────────────────────
// Se calcula aquí para no repetirlo en el template.
// El JS también lo recibe como variable global para usarlo en la UI.
function nivelEfectivo(array $ruleset, int $nivel_real): int {
    $tipo = $ruleset['nivel']['tipo'] ?? 'real';
    if ($tipo === 'fijo')  return intval($ruleset['nivel']['valor'] ?? $nivel_real);
    if ($tipo === 'rango') {
        $min = intval($ruleset['nivel']['min'] ?? 1);
        $max = intval($ruleset['nivel']['max'] ?? 100);
        return max($min, min($max, $nivel_real));
    }
    return $nivel_real;
}

// ─── Ficha real (bio + avatar) ────────────────────────────────────────────────
$ficha   = null;
$usuario = null;
$avatar  = '/images/default_avatar.png';

$q_usuario = $db->query("SELECT * FROM mybb_users WHERE uid='$query_uid'");
while ($u = $db->fetch_array($q_usuario)) {
    $usuario = $u;
    $avatar  = $u['avatar'] ?: '/images/default_avatar.png';
}

$q_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$query_uid'");
while ($f = $db->fetch_array($q_ficha)) { $ficha = $f; }

if (!$ficha) {
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$nivel_real     = intval($ficha['nivel']);
$nivel_efectivo = nivelEfectivo($ruleset, $nivel_real);
$presupuesto    = calcularPresupuestoStats($nivel_efectivo);

// ─── Ficha del Coliseo (build específico del jugador para este torneo) ────────
$coliseo_ficha = null;

if ($torneo_id > 0) {
    $q_cf = $db->query("
        SELECT * FROM mybb_op_coliseo_fichas
        WHERE torneo_id='$torneo_id' AND uid='$query_uid'
    ");
    while ($cf = $db->fetch_array($q_cf)) { $coliseo_ficha = $cf; }
}

// ─── AJAX: guardar build del coliseo ─────────────────────────────────────────
if ($is_owner && $accion === 'guardar_build' && $torneo_id > 0) {
    header('Content-Type: application/json');
    $stats_input      = $db->escape_string($mybb->get_input('stats'));
    $estilos_input    = $db->escape_string($mybb->get_input('estilos_elegidos'));
    $belicas_input    = $db->escape_string($mybb->get_input('belicas_elegidas'));

    // Validar JSON
    $stats_decoded    = json_decode($stats_input,   true);
    $estilos_decoded  = json_decode($estilos_input, true);
    $belicas_decoded  = json_decode($belicas_input, true);

    if (!$stats_decoded || !is_array($stats_decoded)) {
        echo json_encode(['success' => false, 'message' => 'Stats no válidas.']);
        exit;
    }

    // Validar que el gasto total no supere el presupuesto
    $gastado = array_sum(array_map('intval', $stats_decoded));
    if ($gastado > $presupuesto) {
        echo json_encode(['success' => false, 'message' => "Has gastado $gastado pts pero el límite es $presupuesto."]);
        exit;
    }

    $stats_json   = json_encode($stats_decoded,  JSON_UNESCAPED_UNICODE);
    $estilos_json = json_encode($estilos_decoded, JSON_UNESCAPED_UNICODE);
    $belicas_json = json_encode($belicas_decoded, JSON_UNESCAPED_UNICODE);

    if ($coliseo_ficha) {
        $db->query("
            UPDATE mybb_op_coliseo_fichas
            SET stats='$stats_json', estilos_elegidos='$estilos_json', belicas_elegidas='$belicas_json',
                nivel_efectivo='$nivel_efectivo'
            WHERE torneo_id='$torneo_id' AND uid='$query_uid'
        ");
    } else {
        $db->query("
            INSERT INTO mybb_op_coliseo_fichas
                (torneo_id, uid, stats, estilos_elegidos, belicas_elegidas, nivel_efectivo)
            VALUES
                ('$torneo_id','$query_uid','$stats_json','$estilos_json','$belicas_json','$nivel_efectivo')
        ");
    }
    echo json_encode(['success' => true, 'message' => 'Build guardado correctamente.']);
    exit;
}

// ─── Técnicas aprendidas (filtradas por el ruleset) ──────────────────────────
$is_staff_tec = in_array($mybb->user['usergroup'], [3, 4, 6]);
$tecnicas_html = [];

$q_tecs = $db->query("
    SELECT t.*, ta.uid FROM mybb_op_tecnicas t
    INNER JOIN mybb_op_tec_aprendidas ta ON t.tid = ta.tid
    WHERE ta.uid = '$query_uid'
    ORDER BY t.tid, t.rama
");

$tecs_por_rama = [];
while ($tec = $db->fetch_array($q_tecs)) {
    $rama   = $tec['rama'];
    $estilo = $tec['estilo'];

    // Filtrar Akuma si el ruleset no lo permite
    if (!$ruleset['akuma']['permitido'] && $rama === 'Akuma') continue;

    // Filtrar Raciales si el ruleset no lo permite
    if (!($ruleset['raciales']['permitidas'] ?? true) && $rama === 'Racial') continue;

    // Filtrar Técnicas Únicas si el ruleset no lo permite
    if (!($ruleset['tecnicas_unicas']['permitidas'] ?? true) && $rama === 'Única') continue;

    // Filtrar por estilos permitidos
    if ($ruleset['estilos']['tipo'] === 'especificos' && !empty($ruleset['estilos']['lista'])) {
        $lista = $ruleset['estilos']['lista'];
        if (!in_array($estilo, $lista) && !in_array($rama, ['Akuma','Racial','Única','Especial','Personaje','Haki'])) continue;
    }

    if (!isset($tecs_por_rama[$rama])) { $tecs_por_rama[$rama] = []; }
    $tec['descripcion'] = nl2br($tec['descripcion']);
    $tecs_por_rama[$rama][] = $tec;
}

foreach ($tecs_por_rama as $rama => $tecs_array) {
    $content = '';
    foreach ($tecs_array as $tec) {
        $content .= create_technique_card($tec, $is_staff_tec, false, $query_uid);
    }
    $style = get_technique_container_style();
    $style['open'] = true;
    $tecnicas_html[$rama] = create_custom_spoiler($rama, $content, $style);
}
$tecnicas_html_json = json_encode($tecnicas_html);

// ─── Datos de akuma (solo si el ruleset lo permite) ───────────────────────────
$akuma       = null;
$akuma_imagen = '';

if ($ruleset['akuma']['permitido'] && $ficha['akuma'] != '') {
    $akuma_nombre    = $ficha['akuma'];
    $akuma_subnombre = $ficha['akuma_subnombre'];
    $q_akuma = $db->query("
        SELECT * FROM mybb_op_akumas
        WHERE nombre='$akuma_nombre' AND subnombre='$akuma_subnombre'
    ");
    while ($a = $db->fetch_array($q_akuma)) { $akuma = $a; $akuma_imagen = $a['imagen']; }
}

// ─── Stats efectivas y presupuesto ────────────────────────────────────────────
// Si hay un build guardado, lo usamos; si no, los stats reales como punto de partida.
$build_stats = null;
if ($coliseo_ficha && $coliseo_ficha['stats']) {
    $build_stats = json_decode($coliseo_ficha['stats'], true);
}

$stats_cols = ['fuerza','resistencia','reflejos','punteria','voluntad','agilidad','destreza'];
$stats_efectivas = [];
foreach ($stats_cols as $col) {
    $stats_efectivas[$col] = $build_stats[$col] ?? intval($ficha[$col]);
}

// Pasivas siguen siendo las reales (son permanentes de la ficha)
$vitalidad_completa   = intval($ficha['vitalidad'])   + intval($ficha['vitalidad_pasiva']);
$energia_completa     = intval($ficha['energia'])      + intval($ficha['energia_pasiva']);
$haki_completo        = intval($ficha['haki'])         + intval($ficha['haki_pasiva']);

// Haki: aplicar tier_max del ruleset
$haki_tier_max = intval($ruleset['haki']['tier_max'] ?? 8);
$haki_kenbun   = $ruleset['haki']['permitido'] ? min(intval($ficha['kenbun']), $haki_tier_max) : 0;
$haki_buso     = $ruleset['haki']['permitido'] ? min(intval($ficha['buso']),   $haki_tier_max) : 0;
$haki_hao      = in_array('hao', $ruleset['haki']['tipos'] ?? []) ? min(intval($ficha['hao']), $haki_tier_max) : 0;

// ─── Colores de facción (mismo sistema que personaje.php) ─────────────────────
$faccion = $ficha['faccion'];
$faccion_colors_full = op_faccion_colors($faccion);
$faccionColor = $faccion_colors_full[0];
$rangoColor   = $faccion_colors_full[3];
$borderColor  = $faccion_colors_full[4];

$apariencia   = nl2br($ficha['apariencia']);
$personalidad = nl2br($ficha['personalidad']);
$historia     = nl2br($ficha['historia']);
$extra        = nl2br($ficha['extra']);

$ruleset_json         = json_encode($ruleset);
$stats_efectivas_json = json_encode($stats_efectivas);
$pestana              = $mybb->get_input('pestana');

// ─── Render templates ─────────────────────────────────────────────────────────
eval("\$op_coliseo_ficha_css     = \"".$templates->get("op_coliseo_ficha_css")."\";");
eval("\$op_coliseo_ficha_bio     = \"".$templates->get("op_coliseo_ficha_bio")."\";");
eval("\$op_coliseo_ficha_stats   = \"".$templates->get("op_coliseo_ficha_stats")."\";");
eval("\$op_coliseo_ficha_combate = \"".$templates->get("op_coliseo_ficha_combate")."\";");

eval("\$page = \"".$templates->get("op_coliseo_ficha")."\";");
output_page($page);
