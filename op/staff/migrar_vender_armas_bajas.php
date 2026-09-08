<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'migrar_vender_armas_bajas.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$dry_run = !isset($_GET['confirmar']);

// Mismo filtro que ya usa op/staff/creador_objetos.php para listar armas WAZ/MT aparte,
// más el rango de tier pedido (1 a 3). "armas" y "WAZ"/"MT" son case-insensitive por el
// collation de las columnas, no hace falta LOWER().
$where_armas = "
    o.categoria = 'armas'
    AND o.tier BETWEEN 1 AND 3
    AND o.objeto_id NOT LIKE '%WAZ%'
    AND o.objeto_id NOT LIKE '%MT%'
";

// Mismo cálculo de precio de venta que op/vender.php (depende del oficio Mercader/Comerciante
// de cada usuario).
function precio_venta_pct($oficios_json) {
    $pct = 2.00000001;
    $oficios_obj = json_decode($oficios_json);
    if (isset($oficios_obj->{'Mercader'})) {
        $pct = 1.66666667;
        if (isset($oficios_obj->{'Mercader'}->{'sub'}->{'Comerciante'})) {
            $nivel = $oficios_obj->{'Mercader'}->{'sub'}->{'Comerciante'};
            if ($nivel == 1) $pct = 1.42857143;
            if ($nivel == 2) $pct = 1.25;
            if ($nivel == 3) $pct = 1.1111111117;
        }
    }
    return $pct;
}

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Vender armas tier 1-3 (no WAZ/MT)</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Migración: vender automáticamente armas tier 1-3 (excluye WAZ y MT)</h2>";
echo "<p class='info'>Para cada usuario, busca en su inventario armas (<code>categoria='armas'</code>) de tier 1 a 3 cuyo ";
echo "<code>objeto_id</code> no contenga <code>WAZ</code> ni <code>MT</code>, las vende al mismo precio que el botón ";
echo "\"Vender\" normal (según su oficio Mercader/Comerciante) y las elimina del inventario.</p>";

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Añade <code>?confirmar=1</code> a la URL para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

$q_uids = $db->query("
    SELECT DISTINCT i.uid FROM mybb_op_inventario i
    INNER JOIN mybb_op_objetos o ON o.objeto_id = i.objeto_id
    WHERE $where_armas
    ORDER BY i.uid
");
$uids = [];
while ($ur = $db->fetch_array($q_uids)) { $uids[] = (int)$ur['uid']; }

$total_usuarios = 0;
$total_items = 0;
$total_ganancia_global = 0;
$log_lines = [];

foreach ($uids as $target_uid) {
    $q_ficha = $db->query("SELECT nombre, berries, oficios FROM mybb_op_fichas WHERE fid='$target_uid' LIMIT 1");
    $ficha = $db->fetch_array($q_ficha);
    if (!$ficha) continue; // uid sin ficha (no debería pasar, pero por seguridad)

    $pct = precio_venta_pct($ficha['oficios'] ?? '{}');

    $q_items = $db->query("
        SELECT i.objeto_id, i.cantidad, o.nombre, o.berries, o.tier
        FROM mybb_op_inventario i
        INNER JOIN mybb_op_objetos o ON o.objeto_id = i.objeto_id
        WHERE i.uid='$target_uid' AND $where_armas
    ");

    $detalle = [];
    $detalle_raw = [];
    $ganancia_uid = 0;
    $objeto_ids_uid = [];
    while ($it = $db->fetch_array($q_items)) {
        $cantidad = (int)$it['cantidad'];
        $precio_unitario = intval(intval($it['berries']) / $pct) + 1;
        $ganancia_item = $precio_unitario * $cantidad;
        $ganancia_uid += $ganancia_item;
        $objeto_ids_uid[] = $it['objeto_id'];
        $detalle[] = htmlspecialchars($it['nombre'])." (".htmlspecialchars($it['objeto_id']).") x{$cantidad} T{$it['tier']} → +{$ganancia_item}₿";
        $detalle_raw[] = "{$it['nombre']} ({$it['objeto_id']}) x{$cantidad} T{$it['tier']} -> +{$ganancia_item} berries";
    }

    if (empty($detalle)) continue;

    $total_usuarios++;
    $total_items += count($detalle);
    $total_ganancia_global += $ganancia_uid;

    $nombre_ficha = htmlspecialchars($ficha['nombre']);
    $berries_actuales = (int)$ficha['berries'];
    $berries_nuevos = $berries_actuales + $ganancia_uid;
    $detalle_str = implode(', ', $detalle);

    if (!$dry_run) {
        $esc_ids = implode("','", array_map([$db, 'escape_string'], $objeto_ids_uid));
        $db->query("DELETE FROM mybb_op_inventario WHERE uid='$target_uid' AND objeto_id IN ('$esc_ids')");
        $db->query("UPDATE mybb_op_fichas SET berries='$berries_nuevos' WHERE fid='$target_uid'");
        log_audit($target_uid, $username, '[Migración][Venta Armas Bajas]', "Vendidas ".count($detalle)." armas: $detalle_str. Berries: $berries_actuales->$berries_nuevos (+{$ganancia_uid}).");
        log_audit_currency($target_uid, $username, $target_uid, '[Migración][Venta Armas Bajas]', 'berries', $berries_nuevos);
        echo "<p class='ok'>UID $target_uid ($nombre_ficha): $detalle_str | berries $berries_actuales → $berries_nuevos.</p>";
        $log_lines[] = "UID {$target_uid} ({$ficha['nombre']}): berries {$berries_actuales} -> {$berries_nuevos} (+{$ganancia_uid})";
        foreach ($detalle_raw as $dr) { $log_lines[] = "    - $dr"; }
    } else {
        echo "<p class='info'>UID $target_uid ($nombre_ficha): $detalle_str | berries $berries_actuales → $berries_nuevos (simulado).</p>";
    }
}

echo "<hr><p>Usuarios afectados: $total_usuarios | Objetos vendidos: $total_items | Berries totales generados: $total_ganancia_global</p>";

if (!$dry_run && !empty($log_lines)) {
    $log_filename = 'venta_armas_bajas_log_' . date('Ymd_His') . '.txt';
    $log_path = __DIR__ . '/' . $log_filename;
    $log_header = "Migración: venta automática de armas tier 1-3 (excluye WAZ/MT)\n"
        . "Ejecutado por: $username (uid $uid) el " . date('Y-m-d H:i:s') . "\n"
        . "Usuarios afectados: $total_usuarios | Objetos vendidos: $total_items | Berries totales generados: $total_ganancia_global\n"
        . str_repeat('-', 70) . "\n";
    file_put_contents($log_path, $log_header . implode("\n", $log_lines) . "\n");
    echo "<p class='ok'>Log guardado en <code>op/staff/$log_filename</code>.</p>";
}

if ($dry_run && $total_usuarios > 0) {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios</a></p>";
} elseif ($total_usuarios === 0) {
    echo "<p class='ok'>No se encontraron armas tier 1-3 (no WAZ/MT) en ningún inventario. ¡Todo limpio!</p>";
}

echo "</body></html>";
