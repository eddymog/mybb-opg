<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'aniversario_gacha.php');
$templatelist = 'aniversario_gacha';
require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid = (int)$mybb->user['uid'];
if ($uid === 0) error_no_permission();

// Acceso: solo UID 850 antes del 07/07/2026 00:00 hora Madrid
$fecha_apertura  = new DateTime('2026-07-07 3:05:00', new DateTimeZone('Europe/Madrid'));
$ahora           = new DateTime('now', new DateTimeZone('Europe/Madrid'));
$abierto_publico = ($ahora >= $fecha_apertura);

if (!$abierto_publico && $uid !== 850) {
    error_no_permission();
}

$ficha = $db->fetch_array($db->query("SELECT berries, nika, kuro FROM mybb_op_fichas WHERE fid='$uid' AND aprobada_por NOT IN ('sin_aprobar','pendiente_reset') LIMIT 1"));
if (!$ficha) {
    error('Necesitas tener una ficha de personaje aprobada para participar en el gacha de aniversario.');
}

$g_berries = number_format((int)$ficha['berries'], 0, ',', '.');
$g_kuros   = number_format((int)$ficha['kuro'],    0, ',', '.');
$g_nikas   = number_format((int)$ficha['nika'],    0, ',', '.');

define('GACHA_ANIV_RESET', '2026-07-06 19:05:00'); // 03:05 Madrid = 19:05 hora BD (Madrid −8h)

// ── Coste dinámico banner Berries (×2% cada 10 tiradas) ──────────
$_row_tier    = $db->fetch_array($db->query("SELECT SUM(CASE WHEN log LIKE 'Gacha Aniversario [berries x1]%' THEN 1 WHEN log LIKE 'Gacha Aniversario [berries x10]%' THEN 10 ELSE 0 END) as cnt FROM mybb_op_audit_consola_mod WHERE staff='$uid' AND razon='gacha_aniversario' AND tiempo > '" . GACHA_ANIV_RESET . "'"));
$_tiradas_b   = (int)$_row_tier['cnt'];
$_tier_b      = (int)floor($_tiradas_b / 10);
$_costo_b_x1  = (int)round(10000000 * pow(1.115, $_tier_b));
$_prox_b      = min(10 - ($_tiradas_b % 10), 500 - $_tiradas_b);

// ── Descuento de último día banner Berries: lineal de 100% (tier 0) a 10% (tier 10); sin descuento desde tier 11 ──
$_ULTIMO_DIA_DESCUENTO_INICIO = new DateTime('2026-07-14 00:00:00', new DateTimeZone('Europe/Madrid'));
$_ULTIMO_DIA_DESCUENTO_FIN    = new DateTime('2026-07-15 00:00:00', new DateTimeZone('Europe/Madrid'));
function berriesDescuentoUltimoDiaPct($tier) {
    if ($tier >= 11) return 0;
    return 100 - 9 * $tier; // 100% en tier 0 ... 10% en tier 10
}
$_descuento_b_pct = 0;
if ($ahora >= $_ULTIMO_DIA_DESCUENTO_INICIO && $ahora < $_ULTIMO_DIA_DESCUENTO_FIN) {
    $_descuento_b_pct = berriesDescuentoUltimoDiaPct($_tier_b);
    if ($_descuento_b_pct > 0) {
        $_costo_b_x1 = (int)round($_costo_b_x1 * (1 - $_descuento_b_pct / 100));
    }
}
$_costo_b_x10 = (int)round($_costo_b_x1 * 10 * 0.9);
$g_costo_b_x1  = number_format($_costo_b_x1,  0, ',', '.');
$g_costo_b_x10 = number_format($_costo_b_x10, 0, ',', '.');
$g_prox_b      = $_prox_b;
$g_descuento_b_pct = $_descuento_b_pct;
$g_descuento_b_activo = ($_descuento_b_pct > 0) ? '1' : '0';

$g_descuento_b_badge = '';
$g_descuento_b_aviso = '';
if ($_descuento_b_pct > 0) {
    $g_descuento_b_badge = ' <span class="g-descuento g-descuento-ultimodia" style="background:#1fb35a;">&#8722;' . $_descuento_b_pct . '%</span>';
    $g_descuento_b_aviso = '<div class="g-descuento-aviso" style="font-size:11px;color:#3ef58a;font-weight:bold;margin-top:2px;">&#127881; ¡Último día! Descuento de ' . $_descuento_b_pct . '% en tu tramo actual</div>';
}

// El -10% de x10 se compone con el descuento de tramo (no se suman, se multiplican los factores restantes)
$_descuento_x10_pct = round(100 - (100 - $_descuento_b_pct) * 0.9, 1);
$g_descuento_x10_texto = (fmod($_descuento_x10_pct, 1) == 0.0)
    ? number_format($_descuento_x10_pct, 0)
    : number_format($_descuento_x10_pct, 1);

// ── Datos para el modal de información de premios ────────────────
$_pool_b = [
    ['type'=>'objeto','id'=>'KSP001',  'w'=>50],
    ['type'=>'objeto','id'=>'KK006',   'w'=>20],
    ['type'=>'objeto','id'=>'TNP001',  'w'=>5],
    ['type'=>'objeto','id'=>'THR001',  'w'=>5],
    ['type'=>'objeto','id'=>'PEESP001','w'=>20],
    ['type'=>'objeto','id'=>'INV001',  'w'=>100],
    ['type'=>'objeto','id'=>'INV002',  'w'=>50],
    ['type'=>'objeto','id'=>'INV003',  'w'=>30],
    ['type'=>'objeto','id'=>'INV004',  'w'=>15],
    ['type'=>'objeto','id'=>'INV005',  'w'=>5],
    ['type'=>'objeto','id'=>'CFD002',  'w'=>60],
    ['type'=>'objeto','id'=>'CFD003',  'w'=>30],
    ['type'=>'objeto','id'=>'CFD004',  'w'=>10],
    ['type'=>'objeto','id'=>'CFR001',  'w'=>500],
    ['type'=>'objeto','id'=>'CFR002',  'w'=>300],
    ['type'=>'objeto','id'=>'CFR003',  'w'=>100],
    ['type'=>'objeto','id'=>'CFR004',  'w'=>50],
    ['type'=>'objeto','id'=>'CFR005',  'w'=>20],
    ['type'=>'objeto','id'=>'CFR006',  'w'=>5],
    ['type'=>'objeto','id'=>'CFR007',  'w'=>1],
    ['type'=>'objeto','id'=>'BARC401', 'w'=>24],
    ['type'=>'berries','nombre'=>'1.000.000 Berries',   'w'=>3000],
    ['type'=>'berries','nombre'=>'10.000.000 Berries',  'w'=>2600],
    ['type'=>'berries','nombre'=>'50.000.000 Berries',  'w'=>76],
    ['type'=>'berries','nombre'=>'200.000.000 Berries', 'w'=>30],
    ['type'=>'kuro',   'nombre'=>'100 Kuros',           'w'=>200],
    ['type'=>'nika',   'nombre'=>'10 Nikas',            'w'=>300],
];
$_pool_k = [
    ['type'=>'objeto','id'=>'KSP001',  'w'=>200],
    ['type'=>'objeto','id'=>'KK100',   'w'=>20],
    ['type'=>'objeto','id'=>'MCH005',  'w'=>200],
    ['type'=>'objeto','id'=>'ARMO004', 'w'=>200],
    ['type'=>'objeto','id'=>'BARC501', 'w'=>50],
    ['type'=>'objeto','id'=>'ANM00?',  'w'=>10],
    ['type'=>'objeto','id'=>'INV003',  'w'=>60],
    ['type'=>'objeto','id'=>'INV004',  'w'=>30],
    ['type'=>'objeto','id'=>'INV005',  'w'=>10],
    ['type'=>'objeto','id'=>'CFR004',  'w'=>500],
    ['type'=>'objeto','id'=>'CFR005',  'w'=>300],
    ['type'=>'objeto','id'=>'CFR006',  'w'=>100],
    ['type'=>'objeto','id'=>'CFR007',  'w'=>50],
    ['type'=>'objeto','id'=>'CFR008',  'w'=>20],
    ['type'=>'objeto','id'=>'CFR009',  'w'=>5],
    ['type'=>'objeto','id'=>'CFR010',  'w'=>1],
    ['type'=>'berries','nombre'=>'100.000.000 Berries', 'w'=>2500],
    ['type'=>'berries','nombre'=>'200.000.000 Berries', 'w'=>320],
    ['type'=>'berries','nombre'=>'500.000.000 Berries', 'w'=>160],
    ['type'=>'kuro',   'nombre'=>'50 Kuros',            'w'=>1500],
    ['type'=>'kuro',   'nombre'=>'100 Kuros',           'w'=>500],
    ['type'=>'kuro',   'nombre'=>'200 Kuros',           'w'=>100],
    ['type'=>'kuro',   'nombre'=>'500 Kuros',           'w'=>44],
    ['type'=>'nika',   'nombre'=>'10 Nikas',            'w'=>600],
    ['type'=>'nika',   'nombre'=>'20 Nikas',            'w'=>400],
];

$_obj_ids = [];
foreach (array_merge($_pool_b, $_pool_k) as $_p) {
    if ($_p['type'] === 'objeto') $_obj_ids["'" . $db->escape_string($_p['id']) . "'"] = true;
}
$_nombres_obj = [];
if ($_obj_ids) {
    $_q = $db->query("SELECT objeto_id, nombre FROM mybb_op_objetos WHERE objeto_id IN (" . implode(',', array_keys($_obj_ids)) . ")");
    while ($_row = $db->fetch_array($_q)) $_nombres_obj[$_row['objeto_id']] = $_row['nombre'];
}

function gacha_aniv_build_info($pool, $nombres_obj) {
    $total = array_sum(array_column($pool, 'w'));
    $out = [];
    foreach ($pool as $p) {
        $out[] = [
            'type'   => $p['type'],
            'nombre' => ($p['type'] === 'objeto') ? ($nombres_obj[$p['id']] ?? $p['id']) : $p['nombre'],
            'pct'    => round($p['w'] / $total * 100, 4),
        ];
    }
    usort($out, function($a, $b) { return $b['pct'] <=> $a['pct']; });
    return $out;
}

$g_info_berries = base64_encode(json_encode(gacha_aniv_build_info($_pool_b, $_nombres_obj)));
$g_info_kuros   = base64_encode(json_encode(gacha_aniv_build_info($_pool_k, $_nombres_obj)));

// ── Historial de tiradas ─────────────────────────────────────────
$db->write_query("CREATE TABLE IF NOT EXISTS mybb_op_gacha_aniversario_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid INT UNSIGNED NOT NULL DEFAULT 0,
    username VARCHAR(120) NOT NULL DEFAULT '',
    banner ENUM('berries','kuros') NOT NULL,
    premio_type VARCHAR(20) NOT NULL DEFAULT '',
    premio_nombre VARCHAR(255) NOT NULL DEFAULT '',
    fecha INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_banner_id (banner, id),
    KEY idx_uid_banner (uid, banner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function gacha_aniv_fetch_hist($db, $banner, $uid = null, $limit = 25) {
    $b = $db->escape_string($banner);
    if ($uid !== null) {
        $q = $db->query("SELECT premio_type, premio_nombre, fecha FROM mybb_op_gacha_aniversario_log WHERE banner='$b' AND uid='" . (int)$uid . "' ORDER BY id DESC LIMIT $limit");
    } else {
        $q = $db->query("SELECT username, premio_type, premio_nombre, fecha FROM mybb_op_gacha_aniversario_log WHERE banner='$b' ORDER BY id DESC LIMIT $limit");
    }
    $rows = [];
    while ($row = $db->fetch_array($q)) $rows[] = $row;
    return $rows;
}

$g_hist_gb = base64_encode(json_encode(gacha_aniv_fetch_hist($db, 'berries')));
$g_hist_pb = base64_encode(json_encode(gacha_aniv_fetch_hist($db, 'berries', $uid)));
$g_hist_gk = base64_encode(json_encode(gacha_aniv_fetch_hist($db, 'kuros')));
$g_hist_pk = base64_encode(json_encode(gacha_aniv_fetch_hist($db, 'kuros', $uid)));
$g_cur_username = base64_encode($mybb->user['username']);

// ── Tiradas gratuitas de kuros: una ventana de 24h el 11/07/2026 y otra el 12/07/2026 (hora Madrid), una vez por usuario y ventana ──
$_GRATIS_VENTANAS = [
    ['inicio' => '2026-07-11 00:00:00', 'fin' => '2026-07-12 00:00:00', 'tabla' => 'mybb_op_gacha_aniversario_gratis'],
    ['inicio' => '2026-07-12 00:00:00', 'fin' => '2026-07-13 00:00:00', 'tabla' => 'mybb_op_gacha_aniversario_gratis_2'],
];

$_ventana_gratis_actual = null;
foreach ($_GRATIS_VENTANAS as $_v) {
    $_ini = new DateTime($_v['inicio'], new DateTimeZone('Europe/Madrid'));
    $_fin = new DateTime($_v['fin'], new DateTimeZone('Europe/Madrid'));
    if ($ahora >= $_ini && $ahora < $_fin) { $_ventana_gratis_actual = $_v; break; }
}

$g_gratis_boton = '';
if ($_ventana_gratis_actual !== null) {
    $_tabla_gratis = $_ventana_gratis_actual['tabla'];
    $db->write_query("CREATE TABLE IF NOT EXISTS `$_tabla_gratis` (
        uid INT UNSIGNED NOT NULL,
        fecha INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (uid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $_gratis_reclamada = (bool)$db->fetch_array($db->query("SELECT uid FROM `$_tabla_gratis` WHERE uid='$uid' LIMIT 1"));

    if (!$_gratis_reclamada) {
        $g_gratis_boton = '<button class="btn-gacha btn-gacha-gratis" data-banner="kuros" data-cantidad="1" data-gratis="1">&#127873; Tirada Gratis (hoy)</button>';
    } else {
        $g_gratis_boton = '<button class="btn-gacha btn-gacha-gratis" disabled>&#10004; Tirada gratis ya reclamada</button>';
    }
}

eval("\$page = \"".$templates->get("aniversario_gacha")."\";");
output_page($page);
