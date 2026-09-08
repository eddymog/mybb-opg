<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'migrar_quitar_estilos.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$dry_run = !isset($_GET['confirmar']);

// Precios antiguos de desbloqueo (lo que realmente se pagó por cada slot).
// Estilo 1 siempre fue gratuito, por eso no lleva reembolso.
$reembolso_por_slot = ['estilo1' => 0, 'estilo2' => 25, 'estilo3' => 50, 'estilo4' => 75];

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Retirar los 4 estilos (reembolso a precios antiguos)</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Migración: retirar estilo1-4 de todas las fichas + reembolso a precios antiguos</h2>";
echo "<p class='info'>Bloquea los 4 slots de estilo (vuelven a 'bloqueado') en TODAS las fichas y devuelve las nikas invertidas ";
echo "según los precios antiguos: Estilo 2 = 25✦, Estilo 3 = 50✦, Estilo 4 = 75✦ (Estilo 1 era gratis, sin reembolso). ";
echo "El estado de aprobación de la ficha (<code>aprobada_por</code>) no se toca — si estaba en <code>pendiente_reset</code>, sigue estándolo.</p>";

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Añade <code>?confirmar=1</code> a la URL para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

$q = $db->query("SELECT fid, nombre, estilo1, estilo2, estilo3, estilo4, nika FROM mybb_op_fichas");

$total = 0;
$migrated = 0;

while ($row = $db->fetch_array($q)) {
    $total++;
    $fid         = (int)$row['fid'];
    $nombre      = htmlspecialchars($row['nombre']);
    $nika_actual = (int)$row['nika'];

    $reembolso = 0;
    $detalle = [];
    foreach (['estilo1', 'estilo2', 'estilo3', 'estilo4'] as $slot) {
        $val = $row[$slot] ?? 'bloqueado';
        if ($val !== 'bloqueado' && $val !== '' && $val !== null) {
            $monto = $reembolso_por_slot[$slot];
            $reembolso += $monto;
            $detalle[] = "$slot:'".htmlspecialchars($val)."'→bloqueado (+{$monto}✦)";
        }
    }

    if (empty($detalle)) {
        continue; // ya no tiene ningún estilo asignado, nada que hacer
    }

    $migrated++;
    $nika_nuevo = $nika_actual + $reembolso;
    $detalle_str = implode(', ', $detalle);

    if (!$dry_run) {
        $db->query("UPDATE mybb_op_fichas SET estilo1='bloqueado', estilo2='bloqueado', estilo3='bloqueado', estilo4='bloqueado' WHERE fid='$fid'");
        log_audit_currency($uid, $username, $fid, '[Migración][Retiro Estilos]', 'nikas', $nika_nuevo);
        echo "<p class='ok'>FID $fid ($nombre): $detalle_str | nikas $nika_actual → $nika_nuevo.</p>";
    } else {
        echo "<p class='info'>FID $fid ($nombre): $detalle_str | nikas $nika_actual → $nika_nuevo (simulado).</p>";
    }
}

echo "<hr><p>Total de fichas revisadas: $total | Migradas (con al menos 1 estilo activo): $migrated</p>";

if ($dry_run && $migrated > 0) {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios</a></p>";
} elseif ($migrated === 0) {
    echo "<p class='ok'>No se encontraron fichas con algún estilo activo. ¡Todo limpio!</p>";
}

echo "</body></html>";
