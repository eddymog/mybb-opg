<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'migrar_quitar_disciplinas_extra.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$dry_run = !isset($_GET['confirmar']);

// Coste de desbloqueo por slot (slot 1 es gratuito/inicial).
$belica_unlock = ['belica2'=>10,'belica3'=>20,'belica4'=>35,'belica5'=>50,'belica6'=>65,
                   'belica7'=>80,'belica8'=>95,'belica9'=>110,'belica10'=>125,'belica11'=>140,'belica12'=>155];
// Coste de caminos/especializaciones, indexado por número de slot (no por disciplina).
$costes_espe1 = [1=>0,2=>20,3=>30,4=>45,5=>60,6=>75,7=>90,8=>105,9=>120,10=>135,11=>150,12=>165];
$costes_espe2 = [1=>15,2=>30,3=>40,4=>60,5=>75,6=>90,7=>105,8=>120,9=>135,10=>150,11=>165,12=>180];
$costes_spec  = [1=>45,2=>60,3=>75,4=>100,5=>125,6=>150,7=>175,8=>200,9=>225,10=>250,11=>275,12=>300];
$costes_maest = [1=>90,2=>120,3=>150,4=>200,5=>250,6=>300,7=>350,8=>400,9=>450,10=>500,11=>550,12=>600];

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Retirar disciplinas por encima de 6</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Migración: retirar disciplinas bélicas por encima de las 6 permitidas</h2>";
echo "<p class='info'>Las disciplinas se asignan siempre en orden de slot (belica1 → belica12), así que cualquier slot ";
echo "7-12 con contenido está, por definición, por encima del límite de 6. Se limpia ese slot (y su entrada en <code>belicas</code> JSON, ";
echo "incluyendo caminos/especializaciones) y se reembolsan las nikas invertidas en desbloquear el slot + sus caminos.</p>";

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Añade <code>?confirmar=1</code> a la URL para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

$q = $db->query("SELECT fid, nombre, nika, belicas,
    belica1, belica2, belica3, belica4, belica5, belica6,
    belica7, belica8, belica9, belica10, belica11, belica12
    FROM mybb_op_fichas");

$total = 0;
$migrated = 0;

while ($row = $db->fetch_array($q)) {
    $total++;
    $fid         = (int)$row['fid'];
    $nombre      = htmlspecialchars($row['nombre']);
    $nika_actual = (int)$row['nika'];
    $belicas_json = json_decode($row['belicas'] ?? '{}', true) ?: [];

    $reembolso = 0;
    $detalle = [];
    $set_clauses = [];

    for ($i = 7; $i <= 12; $i++) {
        $disc = trim($row["belica$i"] ?? '');
        if ($disc === '') continue;

        $slot_cost = $belica_unlock["belica$i"];
        $reembolso += $slot_cost;
        $partes = ["slot(+{$slot_cost}✦)"];

        $dd = $belicas_json[$disc] ?? null;
        if ($dd) {
            $e1 = $dd['espe1'] ?? '';
            if ($e1 !== '') {
                $monto = $costes_espe1[$i];
                $lvl   = (int)($dd['sub'][$e1] ?? 0);
                if ($lvl >= 2) $monto += $costes_spec[$i];
                if ($lvl >= 3) $monto += $costes_maest[$i];
                $reembolso += $monto;
                $partes[] = "espe1:'$e1'(+{$monto}✦)";
            }
            $e2 = $dd['espe2'] ?? '';
            if ($e2 !== '') {
                $monto = $costes_espe2[$i];
                $lvl   = (int)($dd['sub'][$e2] ?? 0);
                if ($lvl >= 2) $monto += $costes_spec[$i];
                if ($lvl >= 3) $monto += $costes_maest[$i];
                $reembolso += $monto;
                $partes[] = "espe2:'$e2'(+{$monto}✦)";
            }
            unset($belicas_json[$disc]);
        }

        $detalle[] = "belica{$i}:'".htmlspecialchars($disc)."' [".implode(', ', $partes)."]→∅";
        $set_clauses[] = "belica$i=''";
    }

    if (empty($detalle)) {
        continue; // ya tiene 6 o menos disciplinas, nada que hacer
    }

    $migrated++;
    $nika_nuevo = $nika_actual + $reembolso;
    $detalle_str = implode(' | ', $detalle);

    if (!$dry_run) {
        $belicas_esc = $db->escape_string(json_encode($belicas_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $set_sql = implode(', ', $set_clauses) . ", belicas='$belicas_esc'";
        $db->query("UPDATE mybb_op_fichas SET $set_sql WHERE fid='$fid'");
        log_audit_currency($uid, $username, $fid, '[Migración][Retiro Disciplinas Extra]', 'nikas', $nika_nuevo);
        echo "<p class='ok'>FID $fid ($nombre): $detalle_str | nikas $nika_actual → $nika_nuevo.</p>";
    } else {
        echo "<p class='info'>FID $fid ($nombre): $detalle_str | nikas $nika_actual → $nika_nuevo (simulado).</p>";
    }
}

echo "<hr><p>Total de fichas revisadas: $total | Migradas (con más de 6 disciplinas): $migrated</p>";

if ($dry_run && $migrated > 0) {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios</a></p>";
} elseif ($migrated === 0) {
    echo "<p class='ok'>No se encontraron fichas con más de 6 disciplinas. ¡Todo limpio!</p>";
}

echo "</body></html>";
