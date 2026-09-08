<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'aniversario_gacha_spin.php');
require_once "./../global.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if ($uid === 0) { echo json_encode(['error' => 'No autenticado']); exit; }

// Ficha aprobada
$ficha = $db->fetch_array($db->query("SELECT berries, nika, kuro FROM mybb_op_fichas WHERE fid='$uid' AND aprobada_por NOT IN ('sin_aprobar','pendiente_reset') LIMIT 1"));
if (!$ficha) { echo json_encode(['error' => 'Necesitas una ficha aprobada.']); exit; }

// Acceso: hasta 07/07/2026 00:00 Madrid solo UID 850
$fecha_apertura = new DateTime('2026-07-07 3:05:00', new DateTimeZone('Europe/Madrid'));
$ahora          = new DateTime('now', new DateTimeZone('Europe/Madrid'));
if ($ahora < $fecha_apertura && $uid !== 850) {
    echo json_encode(['error' => 'El gacha aún no está disponible.']); exit;
}

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

$banner   = $mybb->get_input('banner');
$cantidad = intval($mybb->get_input('cantidad'));
$gratis   = ($mybb->get_input('gratis') === '1');
if (!in_array($banner, ['berries', 'kuros'], true)) { echo json_encode(['error' => 'Banner inválido.']); exit; }
if (!in_array($cantidad, [1, 10], true)) { $cantidad = 1; }

// ── Tiradas gratuitas de kuros: una ventana de 24h el 11/07/2026 y otra el 12/07/2026 (hora Madrid), una vez por usuario y ventana ──
$GRATIS_VENTANAS = [
    ['inicio' => '2026-07-11 00:00:00', 'fin' => '2026-07-12 00:00:00', 'tabla' => 'mybb_op_gacha_aniversario_gratis'],
    ['inicio' => '2026-07-12 00:00:00', 'fin' => '2026-07-13 00:00:00', 'tabla' => 'mybb_op_gacha_aniversario_gratis_2'],
];

if ($gratis) {
    if ($banner !== 'kuros') { echo json_encode(['error' => 'La tirada gratuita solo aplica al banner de kuros.']); exit; }
    $cantidad = 1;

    $ventana_gratis = null;
    foreach ($GRATIS_VENTANAS as $v) {
        $ini = new DateTime($v['inicio'], new DateTimeZone('Europe/Madrid'));
        $fin = new DateTime($v['fin'], new DateTimeZone('Europe/Madrid'));
        if ($ahora >= $ini && $ahora < $fin) { $ventana_gratis = $v; break; }
    }
    if ($ventana_gratis === null) {
        echo json_encode(['error' => 'La tirada gratuita de kuros solo está disponible hoy.']); exit;
    }

    $tabla_gratis = $ventana_gratis['tabla'];
    $db->write_query("CREATE TABLE IF NOT EXISTS `$tabla_gratis` (
        uid INT UNSIGNED NOT NULL,
        fecha INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (uid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Reserva atómica de la tirada gratuita: la clave primaria en `uid` impide reclamarla dos veces
    // aunque lleguen peticiones simultáneas (evita condición de carrera de doble clic).
    $db->query("INSERT IGNORE INTO `$tabla_gratis` (uid, fecha) VALUES ('$uid', " . time() . ")");
    if ($db->affected_rows() === 0) {
        echo json_encode(['error' => 'Ya has usado tu tirada gratuita de kuros de hoy.']); exit;
    }
}

define('GACHA_ANIV_RESET', '2026-07-06 19:05:00'); // 03:05 Madrid = 19:05 hora BD (Madrid −8h)

// ── Descuento de último día banner Berries: lineal de 100% (tier 0) a 10% (tier 10); sin descuento desde tier 11 ──
$ULTIMO_DIA_DESCUENTO_INICIO = new DateTime('2026-07-14 00:00:00', new DateTimeZone('Europe/Madrid'));
$ULTIMO_DIA_DESCUENTO_FIN    = new DateTime('2026-07-15 00:00:00', new DateTimeZone('Europe/Madrid'));
function berriesDescuentoUltimoDiaPct($tier) {
    if ($tier >= 11) return 0;
    return 100 - 9 * $tier; // 100% en tier 0 ... 10% en tier 10
}

$COSTO_KUROS = 100;
if ($banner === 'berries') {
    $_row_t = $db->fetch_array($db->query("SELECT SUM(CASE WHEN log LIKE 'Gacha Aniversario [berries x1]%' THEN 1 WHEN log LIKE 'Gacha Aniversario [berries x10]%' THEN 10 ELSE 0 END) as cnt FROM mybb_op_audit_consola_mod WHERE staff='$uid' AND razon='gacha_aniversario' AND tiempo > '" . GACHA_ANIV_RESET . "'"));
    $_tiradas_actual = (int)$_row_t['cnt'];
    if ($_tiradas_actual + $cantidad > 500) {
        $restantes = max(0, 500 - $_tiradas_actual);
        echo json_encode(['error' => "Has alcanzado el límite de 500 tiradas del banner de berries. Te quedan $restantes tiradas."]); exit;
    }
    $_tier  = (int)floor($_tiradas_actual / 10);
    $COSTO_BERRIES = (int)round(10000000 * pow(1.115, $_tier));

    if ($ahora >= $ULTIMO_DIA_DESCUENTO_INICIO && $ahora < $ULTIMO_DIA_DESCUENTO_FIN) {
        $_descuento_pct = berriesDescuentoUltimoDiaPct($_tier);
        if ($_descuento_pct > 0) {
            $COSTO_BERRIES = (int)round($COSTO_BERRIES * (1 - $_descuento_pct / 100));
        }
    }
} else {
    $COSTO_BERRIES = 10000000;
}
$costo_base  = ($banner === 'berries') ? $COSTO_BERRIES : $COSTO_KUROS;
$costo_total = ($cantidad === 10) ? (int)round($costo_base * 10 * 0.9) : $costo_base;
if ($gratis) { $costo_total = 0; }

if (!$gratis && $banner === 'berries' && (int)$ficha['berries'] < $costo_total) {
    echo json_encode(['error' => 'No tienes suficientes berries.']); exit;
}
if (!$gratis && $banner === 'kuros' && (int)$ficha['kuro'] < $costo_total) {
    echo json_encode(['error' => 'No tienes suficientes kuros.']); exit;
}

// ── Pools de premios ─────────────────────────────────────────────
// Cada entrada: type='objeto'|'berries'|'kuro'|'nika', w=peso
// Pesos en enteros (×100) para evitar errores de precisión flotante
$pool_berries = [
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
    ['type'=>'berries','cant'=>1000000,   'nombre'=>'1.000.000 Berries',   'w'=>3000],
    ['type'=>'berries','cant'=>10000000,  'nombre'=>'10.000.000 Berries',  'w'=>2600],
    ['type'=>'berries','cant'=>50000000,  'nombre'=>'50.000.000 Berries',  'w'=>76],
    ['type'=>'berries','cant'=>200000000, 'nombre'=>'200.000.000 Berries', 'w'=>30],
    ['type'=>'kuro',   'cant'=>100,       'nombre'=>'100 Kuros',           'w'=>200],
    ['type'=>'nika',   'cant'=>10,        'nombre'=>'10 Nikas',            'w'=>300],
];

$pool_kuros = [
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
    ['type'=>'berries','cant'=>100000000, 'nombre'=>'100.000.000 Berries', 'w'=>2500],
    ['type'=>'berries','cant'=>200000000, 'nombre'=>'200.000.000 Berries', 'w'=>320],
    ['type'=>'berries','cant'=>500000000, 'nombre'=>'500.000.000 Berries', 'w'=>160],
    ['type'=>'kuro',   'cant'=>50,        'nombre'=>'50 Kuros',            'w'=>1500],
    ['type'=>'kuro',   'cant'=>100,       'nombre'=>'100 Kuros',           'w'=>500],
    ['type'=>'kuro',   'cant'=>200,       'nombre'=>'200 Kuros',           'w'=>100],
    ['type'=>'kuro',   'cant'=>500,       'nombre'=>'500 Kuros',           'w'=>44],
    ['type'=>'nika',   'cant'=>10,        'nombre'=>'10 Nikas',            'w'=>600],
    ['type'=>'nika',   'cant'=>20,        'nombre'=>'20 Nikas',            'w'=>400],
];

$pool       = ($banner === 'berries') ? $pool_berries : $pool_kuros;
$peso_total = array_sum(array_column($pool, 'w'));

function gacha_tirar($pool, $peso_total) {
    $rand = random_int(0, $peso_total - 1);
    $acum = 0;
    foreach ($pool as $p) {
        $acum += $p['w'];
        if ($rand < $acum) return $p;
    }
    return end($pool);
}

// Precargar nombres de objetos
$obj_ids_pool = [];
foreach ($pool as $p) {
    if ($p['type'] === 'objeto') $obj_ids_pool[] = "'" . $db->escape_string($p['id']) . "'";
}
$nombres_obj = [];
if ($obj_ids_pool) {
    $q = $db->query("SELECT objeto_id, nombre FROM mybb_op_objetos WHERE objeto_id IN (" . implode(',', $obj_ids_pool) . ")");
    while ($row = $db->fetch_array($q)) $nombres_obj[$row['objeto_id']] = $row['nombre'];
}

// Realizar tiradas
$resultados  = [];
$berries_sum = 0;
$kuros_sum   = 0;
$nikas_sum   = 0;

for ($i = 0; $i < $cantidad; $i++) {
    $p = gacha_tirar($pool, $peso_total);
    if ($p['type'] === 'objeto') {
        $p['nombre'] = $nombres_obj[$p['id']] ?? $p['id'];
    }
    $resultados[] = $p;
    if ($p['type'] === 'berries') $berries_sum += $p['cant'];
    if ($p['type'] === 'kuro')    $kuros_sum   += $p['cant'];
    if ($p['type'] === 'nika')    $nikas_sum   += $p['cant'];
}

// ── Transacción ──────────────────────────────────────────────────
$db->query("START TRANSACTION");

// Descontar coste (guardia contra race condition: solo si el saldo es suficiente)
// La tirada gratuita y el 100% de descuento de tramo 0 dejan costo_total=0: si se ejecutara
// el UPDATE igualmente, "berries=berries-0" no cambia el valor y MySQL reporta 0 filas
// afectadas (sin CLIENT_FOUND_ROWS), lo que dispararía el rollback como si faltara saldo.
if (!$gratis && $costo_total > 0) {
    if ($banner === 'berries') {
        $db->query("UPDATE mybb_op_fichas SET berries=berries-$costo_total WHERE fid='$uid' AND berries>=$costo_total");
    } else {
        $db->query("UPDATE mybb_op_fichas SET kuro=kuro-$costo_total WHERE fid='$uid' AND kuro>=$costo_total");
    }
    if ($db->affected_rows() === 0) {
        $db->query("ROLLBACK");
        echo json_encode(['error' => 'No tienes suficientes ' . ($banner === 'berries' ? 'berries' : 'kuros') . '.']); exit;
    }
}

// Entregar monedas
if ($berries_sum > 0) $db->query("UPDATE mybb_op_fichas SET berries=berries+$berries_sum WHERE fid='$uid'");
if ($kuros_sum   > 0) $db->query("UPDATE mybb_op_fichas SET kuro=kuro+$kuros_sum WHERE fid='$uid'");
if ($nikas_sum   > 0) $db->query("UPDATE mybb_op_fichas SET nika=nika+$nikas_sum WHERE fid='$uid'");

// Entregar objetos — agrupados por ID para evitar conflictos de duplicado en x10
$uname_esc  = $db->escape_string($mybb->user['username']);
$obj_counts = [];
foreach ($resultados as $p) {
    if ($p['type'] !== 'objeto') continue;
    $obj_counts[$p['id']] = ($obj_counts[$p['id']] ?? 0) + 1;
}
foreach ($obj_counts as $raw_oid => $count) {
    $oid     = $db->escape_string($raw_oid);
    $row_inv = $db->fetch_array($db->query("SELECT id FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$oid' LIMIT 1"));
    if ($row_inv) {
        $db->query("UPDATE mybb_op_inventario SET cantidad=cantidad+$count WHERE uid='$uid' AND objeto_id='$oid'");
    } else {
        $db->query("INSERT INTO mybb_op_inventario (objeto_id, uid, cantidad, autor, autor_uid, oficios, especial) VALUES ('$oid','$uid','$count','$uname_esc','$uid','null','0') ON DUPLICATE KEY UPDATE cantidad=cantidad+$count");
    }
}

// Log de auditoría (una fila resumen)
$log_nombres = implode(', ', array_map(function($p){ return $p['nombre']; }, $resultados));
$log_sufijo  = $gratis ? ' [GRATIS]' : '';
$log_esc     = $db->escape_string("Gacha Aniversario [$banner x$cantidad]$log_sufijo: $log_nombres");
$db->query("INSERT INTO mybb_op_audit_consola_mod (staff, username, razon, log) VALUES ('$uid','$uname_esc','gacha_aniversario','$log_esc')");

// Log individual por premio — un único INSERT con múltiples VALUES
$log_fecha  = time();
$banner_esc = $db->escape_string($banner);
$log_values = [];
foreach ($resultados as $p) {
    $ptype_esc   = $db->escape_string($p['type']);
    $pnombre_esc = $db->escape_string($p['nombre']);
    $log_values[] = "('$uid','$uname_esc','$banner_esc','$ptype_esc','$pnombre_esc','$log_fecha')";
}
$db->write_query("INSERT INTO mybb_op_gacha_aniversario_log (uid, username, banner, premio_type, premio_nombre, fecha) VALUES " . implode(',', $log_values));

$db->query("COMMIT");

// Balances y nuevo coste tras la tirada
$ficha_new = $db->fetch_array($db->query("SELECT berries, nika, kuro FROM mybb_op_fichas WHERE fid='$uid' LIMIT 1"));

// Recalcular coste berries actualizado para el frontend
$_row_t2     = $db->fetch_array($db->query("SELECT SUM(CASE WHEN log LIKE 'Gacha Aniversario [berries x1]%' THEN 1 WHEN log LIKE 'Gacha Aniversario [berries x10]%' THEN 10 ELSE 0 END) as cnt FROM mybb_op_audit_consola_mod WHERE staff='$uid' AND razon='gacha_aniversario' AND tiempo > '" . GACHA_ANIV_RESET . "'"));
$_tier2      = (int)floor((int)$_row_t2['cnt'] / 10);
$_nuevo_x1   = (int)round(10000000 * pow(1.115, $_tier2));
$_descuento_pct2 = 0;
if ($ahora >= $ULTIMO_DIA_DESCUENTO_INICIO && $ahora < $ULTIMO_DIA_DESCUENTO_FIN) {
    $_descuento_pct2 = berriesDescuentoUltimoDiaPct($_tier2);
    if ($_descuento_pct2 > 0) {
        $_nuevo_x1 = (int)round($_nuevo_x1 * (1 - $_descuento_pct2 / 100));
    }
}
$_nuevo_x10  = (int)round($_nuevo_x1 * 10 * 0.9);
$_prox2      = 10 - ((int)$_row_t2['cnt'] % 10);

$premios_out = array_map(function($p) {
    $o = ['type' => $p['type'], 'nombre' => $p['nombre']];
    if ($p['type'] === 'objeto') $o['id'] = $p['id'];
    return $o;
}, $resultados);

echo json_encode([
    'ok'          => true,
    'gratis'      => $gratis,
    'premios'     => $premios_out,
    'balances'    => [
        'berries' => (int)$ficha_new['berries'],
        'kuro'    => (int)$ficha_new['kuro'],
        'nika'    => (int)$ficha_new['nika'],
    ],
    'costo_berries' => [
        'x1'        => $_nuevo_x1,
        'x10'       => $_nuevo_x10,
        'prox'      => $_prox2,
        'descuento' => $_descuento_pct2,
    ],
]);
