<?php
/**
 * Migración única: historial de salarios de staff -> mybb_op_salario_staff_pagos
 *
 * Fuente: mybb_op_audit_general, categoria '[Kuros][Salario Roles +N]' (una fila por pago,
 * con fecha, quién pagó y a quién). Idempotente: no duplica filas ya migradas.
 * Los saldos del log viejo no son fiables (el código original los leía después del UPDATE),
 * por eso se guardan en 0 y el historial los muestra como "n/d". tipo = 'migrado'.
 *
 * Uso: abrir sin parámetros = simulación; añadir ?confirmar=1 para escribir.
 * Antes hay que abrir salario_staff.php una vez (crea la tabla).
 */
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'migrar_salario_historial.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

$uid = (int) $mybb->user['uid'];
if (!is_admin($uid)) { die("Acceso denegado."); }

$dry_run = !isset($_GET['confirmar']);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Migrar historial de salario de staff</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Migración: historial de salario de staff</h2>";
echo "<p class='info'>Copia los pagos de <code>mybb_op_audit_general</code> (categoría <code>[Kuros][Salario Roles +N]</code>) a <code>mybb_op_salario_staff_pagos</code>. No modifica kuros ni borra nada.</p>";

if (!$db->num_rows($db->query("SHOW TABLES LIKE 'mybb_op_salario_staff_pagos'"))) {
    die("<p class='err'>La tabla mybb_op_salario_staff_pagos no existe. Abre primero salario_staff.php (la crea) y vuelve aquí.</p></body></html>");
}

echo $dry_run
    ? "<p class='warn'>MODO SIMULACIÓN — no se escribe nada. Añade <code>?confirmar=1</code> a la URL para aplicar.</p>"
    : "<p class='ok'>MODO ESCRITURA — insertando en la base de datos.</p>";

$q = $db->query("
    SELECT a.id, a.tiempo, a.uid AS pagador_uid, a.username AS pagador, a.user_uid, a.categoria,
           f.nombre AS ficha_nombre, u.username AS user_nombre
    FROM mybb_op_audit_general a
    LEFT JOIN mybb_op_fichas f ON f.fid = a.user_uid
    LEFT JOIN mybb_users u ON u.uid = a.user_uid
    WHERE a.categoria LIKE '[Kuros][Salario Roles +%'
    ORDER BY a.id ASC
");

$total = $migrados = $duplicados = $invalidos = 0;
$muestra = array();

while ($row = $db->fetch_array($q)) {
    $total++;
    if (!preg_match('/^\[Kuros\]\[Salario Roles \+(\d+)\]$/', $row['categoria'], $m)) { $invalidos++; continue; }

    $monto = (int) $m[1];
    $fid = (int) $row['user_uid'];
    $pagador_uid = (int) $row['pagador_uid'];
    $tiempo = $db->escape_string($row['tiempo']);
    $nombre = $row['ficha_nombre'] !== null ? $row['ficha_nombre'] : ($row['user_nombre'] !== null ? $row['user_nombre'] : "UID {$fid}");

    $ya = $db->fetch_array($db->query("
        SELECT id FROM mybb_op_salario_staff_pagos
        WHERE tipo = 'migrado' AND uid = {$fid} AND monto = {$monto} AND pagador_uid = {$pagador_uid} AND tiempo = '{$tiempo}' LIMIT 1
    "));
    if ($ya) { $duplicados++; continue; }

    if (count($muestra) < 10) {
        $muestra[] = htmlspecialchars("{$row['tiempo']}  {$nombre} ({$fid})  +{$monto}  por {$row['pagador']}");
    }
    if (!$dry_run) {
        $db->query("
            INSERT INTO mybb_op_salario_staff_pagos (tiempo, pagador_uid, pagador, uid, nombre, monto, tipo, saldo_antes, saldo_despues)
            VALUES ('{$tiempo}', {$pagador_uid}, '" . $db->escape_string($row['pagador']) . "', {$fid}, '" . $db->escape_string($nombre) . "', {$monto}, 'migrado', 0, 0)
        ");
    }
    $migrados++;
}

echo "<p class='info'>Filas de salario encontradas en audit_general: <b>{$total}</b></p>";
echo "<p class='ok'>" . ($dry_run ? "Se migrarían" : "Migradas") . ": <b>{$migrados}</b></p>";
echo "<p class='warn'>Ya migradas antes (omitidas): <b>{$duplicados}</b> | Categoría no reconocida: <b>{$invalidos}</b></p>";
if ($muestra) {
    echo "<p class='info'>Primeras filas:</p><pre>" . implode("\n", $muestra) . "</pre>";
}
echo "</body></html>";
