<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'migrar_pendiente_reset.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$dry_run = !isset($_GET['confirmar']);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Migración a pendiente_reset</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Migración masiva: fichas aprobadas → <code>pendiente_reset</code></h2>";
echo "<p class='info'>Marca todas las fichas actualmente aprobadas como <code>pendiente_reset</code>. ";
echo "El jugador podrá seguir viendo su ficha pero no hacer mejoras hasta usar su Ticket de Reset de Build (TNP001), ";
echo "donde sólo podrá elegir 3 estilos y 6 disciplinas.</p>";

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Añade <code>?confirmar=1</code> a la URL para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

$q = $db->query("SELECT fid, nombre, aprobada_por FROM mybb_op_fichas WHERE aprobada_por NOT IN ('sin_aprobar', 'pendiente_reset')");

$total = 0;
$migrated = 0;

while ($row = $db->fetch_array($q)) {
    $total++;
    $fid    = (int)$row['fid'];
    $nombre = htmlspecialchars($row['nombre']);
    $antes  = htmlspecialchars($row['aprobada_por']);

    if (!$dry_run) {
        $db->query("UPDATE mybb_op_fichas SET aprobada_por='pendiente_reset' WHERE fid='$fid'");
        echo "<p class='ok'>FID $fid ($nombre): aprobada_por '$antes' → 'pendiente_reset'.</p>";
    } else {
        echo "<p class='info'>FID $fid ($nombre): aprobada_por '$antes' → 'pendiente_reset' (simulado).</p>";
    }
    $migrated++;
}

echo "<hr><p>Total de fichas aprobadas encontradas: $total | Migradas: $migrated</p>";

if ($dry_run && $migrated > 0) {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios</a></p>";
} elseif ($total === 0) {
    echo "<p class='ok'>No se encontraron fichas aprobadas para migrar.</p>";
}

echo "</body></html>";
