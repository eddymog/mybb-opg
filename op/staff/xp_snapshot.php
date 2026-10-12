<?php
/**
 * Staff - Snapshot diario de XP (newpoints) de quienes postearon en rol en las ultimas 2 semanas.
 * Los datos los guarda la tarea op_xp_snapshot (inc/tasks/op_xp_snapshot.php).
 * Muestra un dia a elegir, ordenable, con la XP ganada en 1, 7 y 14 dias.
 */
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'xp_snapshot.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/lib/xp_snapshot.php';

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

function xp_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function xp_num($n) { return number_format((float) $n, 2, ',', '.'); }
function xp_gan($g)
{
    if ($g === null) {
        return '<span class="xp-nd">n/d</span>';
    }
    $clase = $g > 0 ? 'xp-pos' : ($g < 0 ? 'xp-neg' : 'xp-cero');
    return '<span class="' . $clase . '">' . ($g > 0 ? '+' : '') . xp_num($g) . '</span>';
}

opg_xp_crear_tabla();
$fechas = opg_xp_fechas();
$fecha = isset($_GET['fecha']) ? (string) $_GET['fecha'] : '';
if (!in_array($fecha, $fechas, true)) {
    $fecha = $fechas ? $fechas[0] : '';
}

$columnas = array('username' => 'Usuario', 'newpoints' => 'XP actual', 'posts' => 'Posts (14d)',
    'gan_1' => 'Ganada 1d', 'gan_7' => 'Ganada 7d', 'gan_14' => 'Ganada 14d');
$orden = isset($_GET['orden']) && isset($columnas[$_GET['orden']]) ? $_GET['orden'] : 'gan_7';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';

$filas = $fecha !== '' ? opg_xp_filas($fecha) : array();
usort($filas, function ($a, $b) use ($orden, $dir) {
    $x = $a[$orden]; $y = $b[$orden];
    if ($x === null && $y === null) { return 0; }
    if ($x === null) { return 1; }       // sin dato siempre al final
    if ($y === null) { return -1; }
    $c = is_string($x) ? strcasecmp($x, $y) : ($x <=> $y);
    return $dir === 'asc' ? $c : -$c;
});

$encabezados = '';
foreach ($columnas as $clave => $titulo) {
    $nueva_dir = ($orden === $clave && $dir === 'desc') ? 'asc' : 'desc';
    $flecha = $orden === $clave ? ($dir === 'desc' ? ' &darr;' : ' &uarr;') : '';
    $enlace = '?fecha=' . urlencode($fecha) . '&amp;orden=' . $clave . '&amp;dir=' . $nueva_dir;
    $encabezados .= '<th' . ($clave === 'username' ? '' : ' class="xp-num"') . '><a href="' . $enlace . '">' . xp_h($titulo) . $flecha . '</a></th>';
}

$xp_filas = '';
$total_ganada_7 = 0.0;
foreach ($filas as $f) {
    $xp_filas .= '<tr><td><a href="/op/personaje.php?uid=' . $f['uid'] . '">' . xp_h($f['username']) . '</a> <small>(' . $f['uid'] . ')</small></td>'
        . '<td class="xp-num">' . xp_num($f['newpoints']) . '</td>'
        . '<td class="xp-num">' . $f['posts'] . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_1']) . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_7']) . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_14']) . '</td></tr>';
    if ($f['gan_7'] !== null) { $total_ganada_7 += $f['gan_7']; }
}
if ($xp_filas === '') {
    $xp_filas = '<tr><td colspan="6" class="xp-vacio">Todavía no hay snapshots. La tarea "OPG: Snapshot diario de XP" guarda uno al día (ACP &gt; Tareas programadas).</td></tr>';
}

$selector_fecha = '';
foreach ($fechas as $fch) {
    $selector_fecha .= '<option value="' . xp_h($fch) . '"' . ($fch === $fecha ? ' selected' : '') . '>' . xp_h($fch) . '</option>';
}
$xp_orden = xp_h($orden);
$xp_dir = xp_h($dir);
$xp_resumen = count($filas) . ' usuarios con posts en rol en las 2 semanas previas a ' . ($fecha !== '' ? xp_h($fecha) : 'la fecha') . ' · XP total ganada en 7d: ' . xp_num($total_ganada_7)
    . ' · ' . count($fechas) . ' día(s) de historial';

eval("\$page = \"".$templates->get("staff_xp_snapshot")."\";");
output_page($page);
