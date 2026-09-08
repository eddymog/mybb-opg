<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'berries_masivo.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$cantidad_raw = $mybb->get_input('cantidad');
$tiene_cantidad = ($cantidad_raw !== '' && $cantidad_raw !== null);
$cantidad = (int)$cantidad_raw;
$dry_run = !isset($_GET['confirmar']);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Añadir berries a todas las fichas</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}";
echo "input[type=number]{font-family:monospace;font-size:1em;padding:6px;background:#222;color:#eee;border:1px solid #444;}";
echo "input[type=submit]{font-family:monospace;font-size:1em;padding:6px 14px;background:#28ce26;color:#000;border:none;cursor:pointer;}</style></head><body>";
echo "<h2>Añadir una cantidad custom de berries a todas las fichas</h2>";
echo "<p class='info'>Suma (o resta, si el número es negativo) la cantidad indicada al campo <code>berries</code> de TODAS las fichas de <code>mybb_op_fichas</code>, sin excepción.</p>";

echo "<form method='GET' action='/op/staff/berries_masivo.php'>";
echo "Cantidad de berries a añadir a cada ficha: <input type='number' name='cantidad' value='".htmlspecialchars($cantidad_raw ?? '')."' required> ";
echo "<input type='submit' value='Simular'>";
echo "</form><br>";

if (!$tiene_cantidad) {
    echo "<p class='warn'>Introduce una cantidad arriba para ver la simulación.</p>";
    echo "</body></html>";
    exit;
}

if ($cantidad === 0) {
    echo "<p class='err'>La cantidad no puede ser 0.</p>";
    echo "</body></html>";
    exit;
}

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Confirma abajo para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

$q = $db->query("SELECT fid, nombre, berries FROM mybb_op_fichas");

$total = 0;
$log_lines = [];
$signo = $cantidad >= 0 ? '+' : '';

while ($row = $db->fetch_array($q)) {
    $total++;
    $fid = (int)$row['fid'];
    $nombre = htmlspecialchars($row['nombre']);
    $berries_actuales = (int)$row['berries'];
    $berries_nuevos = max(0, $berries_actuales + $cantidad);

    if (!$dry_run) {
        $db->query("UPDATE mybb_op_fichas SET berries='$berries_nuevos' WHERE fid='$fid'");
        log_audit_currency($uid, $username, $fid, '[Migración][Berries Masivo]', 'berries', $berries_nuevos);
        echo "<p class='ok'>FID $fid ($nombre): berries $berries_actuales → $berries_nuevos ({$signo}{$cantidad}).</p>";
        $log_lines[] = "FID {$fid} ({$row['nombre']}): berries {$berries_actuales} -> {$berries_nuevos} ({$signo}{$cantidad})";
    } else {
        echo "<p class='info'>FID $fid ($nombre): berries $berries_actuales → $berries_nuevos ({$signo}{$cantidad}) (simulado).</p>";
    }
}

echo "<hr><p>Fichas afectadas: $total | Cantidad aplicada por ficha: {$signo}{$cantidad}</p>";

if (!$dry_run && !empty($log_lines)) {
    $log_filename = 'berries_masivo_log_' . date('Ymd_His') . '.txt';
    $log_path = __DIR__ . '/' . $log_filename;
    $log_header = "Migración: berries masivo ({$signo}{$cantidad} a todas las fichas)\n"
        . "Ejecutado por: $username (uid $uid) el " . date('Y-m-d H:i:s') . "\n"
        . "Fichas afectadas: $total\n"
        . str_repeat('-', 70) . "\n";
    file_put_contents($log_path, $log_header . implode("\n", $log_lines) . "\n");
    echo "<p class='ok'>Log guardado en <code>op/staff/$log_filename</code>.</p>";
}

if ($dry_run && $total > 0) {
    $confirm_url = '?cantidad=' . urlencode($cantidad) . '&confirmar=1';
    echo "<p><a href='".htmlspecialchars($confirm_url)."' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios a las $total fichas</a></p>";
} elseif ($total === 0) {
    echo "<p class='warn'>No se encontró ninguna ficha en mybb_op_fichas.</p>";
}

echo "</body></html>";
