<?php
/**
 * Staff - Snapshot diario de XP (newpoints) de quienes postearon en rol en las ultimas 2 semanas.
 * Los datos los guarda la tarea op_xp_snapshot (inc/tasks/op_xp_snapshot.php).
 * Muestra un dia a elegir, ordenable, con la XP ganada en 1, 3, 7 y 14 dias.
 */
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'xp_snapshot.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/lib/xp_snapshot.php';

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$uids_con_acceso_snapshot = array(1017);

if (!is_staff($uid) && !in_array($uid, $uids_con_acceso_snapshot, true)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

function xp_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function xp_num($n) { return number_format((float) $n, 2, ',', '.'); }
function xp_gan($g)
{
    if ($g === null) {
        return '<span class="xp-variacion xp-nd">n/d</span>';
    }
    $clase = $g > 0 ? 'xp-pos' : ($g < 0 ? 'xp-neg' : 'xp-cero');
    return '<span class="xp-variacion ' . $clase . '">' . ($g > 0 ? '+' : '') . xp_num($g) . '</span>';
}

opg_xp_crear_tabla();
$fechas = opg_xp_fechas();
$fecha = isset($_GET['fecha']) ? (string) $_GET['fecha'] : '';
if (!in_array($fecha, $fechas, true)) {
    $fecha = $fechas ? $fechas[0] : '';
}

$columnas = array('ranking' => 'Ranking', 'username' => 'Usuario', 'newpoints' => 'XP actual', 'posts' => 'Posts (14d)',
    'gan_1' => 'Ganada 1d', 'gan_3' => 'Ganada 3d', 'gan_7' => 'Ganada 7d', 'gan_14' => 'Ganada 14d');
$orden = isset($_GET['orden']) && isset($columnas[$_GET['orden']]) ? $_GET['orden'] : 'gan_7';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';

$filas = $fecha !== '' ? opg_xp_filas($fecha) : array();
$filas_ranking = $filas;
usort($filas_ranking, function ($a, $b) {
    $comparacion = $b['newpoints'] <=> $a['newpoints'];
    return $comparacion !== 0 ? $comparacion : strcasecmp($a['username'], $b['username']);
});
$ranking_por_uid = array();
$xp_anterior = null;
$ranking_actual = 0;
foreach ($filas_ranking as $indice => $fila_ranking) {
    if ($xp_anterior === null || $fila_ranking['newpoints'] != $xp_anterior) {
        $ranking_actual = $indice + 1;
        $xp_anterior = $fila_ranking['newpoints'];
    }
    $ranking_por_uid[(int) $fila_ranking['uid']] = $ranking_actual;
}
foreach ($filas as &$fila) {
    $fila['ranking'] = $ranking_por_uid[(int) $fila['uid']];
}
unset($fila);
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
    $activa = $orden === $clave;
    $flecha = $activa ? ($dir === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-wide-short') : 'fa-sort';
    $enlace = '?fecha=' . urlencode($fecha) . '&amp;orden=' . $clave . '&amp;dir=' . $nueva_dir;
    $clases = array();
    if ($clave !== 'username') { $clases[] = 'xp-num'; }
    if ($clave === 'ranking') { $clases[] = 'xp-ranking-col'; }
    if ($clave === 'username') { $clases[] = 'xp-usuario-col'; }
    $encabezados .= '<th scope="col"' . ($clases ? ' class="' . implode(' ', $clases) . '"' : '')
        . ($activa ? ' aria-sort="' . ($dir === 'desc' ? 'descending' : 'ascending') . '"' : '') . '>'
        . '<a class="xp-orden' . ($activa ? ' xp-orden--activo' : '') . '" href="' . $enlace . '"><span>'
        . xp_h($titulo) . '</span><i class="fa-solid ' . $flecha . '" aria-hidden="true"></i></a></th>';
}

$xp_filas = '';
$total_ganada_7 = 0.0;
foreach ($filas as $f) {
    $xp_filas .= '<tr><td class="xp-num xp-ranking-col"><span class="xp-ranking">#' . $f['ranking'] . '</span></td>'
        . '<th scope="row" class="xp-usuario-col"><a class="xp-usuario" href="/op/personaje.php?uid=' . $f['uid'] . '">' . xp_h($f['username']) . '</a> <small class="xp-uid">UID ' . $f['uid'] . '</small></th>'
        . '<td class="xp-num">' . xp_num($f['newpoints']) . '</td>'
        . '<td class="xp-num">' . $f['posts'] . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_1']) . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_3']) . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_7']) . '</td>'
        . '<td class="xp-num">' . xp_gan($f['gan_14']) . '</td></tr>';
    if ($f['gan_7'] !== null) { $total_ganada_7 += $f['gan_7']; }
}
if ($xp_filas === '') {
    $xp_filas = '<tr><td colspan="8" class="xp-vacio"><i class="fa-solid fa-camera" aria-hidden="true"></i><strong>Todavía no hay snapshots</strong><span>La tarea “OPG: Snapshot diario de XP” guardará una captura al ejecutarse.</span></td></tr>';
}

$selector_fecha = '';
foreach ($fechas as $fch) {
    $selector_fecha .= '<option value="' . xp_h($fch) . '"' . ($fch === $fecha ? ' selected' : '') . '>' . xp_h(date('d/m/Y', strtotime($fch))) . '</option>';
}
if ($selector_fecha === '') {
    $selector_fecha = '<option value="">Sin snapshots disponibles</option>';
}
$xp_orden = xp_h($orden);
$xp_dir = xp_h($dir);
$xp_total_usuarios = count($filas);
$xp_total_ganada_7 = xp_num($total_ganada_7);
$xp_total_fechas = count($fechas);
$xp_fecha_legible = $fecha !== '' ? xp_h(date('d/m/Y', strtotime($fecha))) : 'Sin fecha disponible';
$xp_selector_disabled = $fechas ? '' : ' disabled';
$xp_resumen = $xp_total_usuarios . ' usuarios con actividad de rol en las dos semanas anteriores al ' . $xp_fecha_legible . '.';

// Frecuencia real del snapshot: se lee de la tarea programada (ACP > Tareas programadas).
$xp_frecuencia = 'La tarea "OPG: Snapshot diario de XP" no está registrada: desactiva y vuelve a activar el plugin "OPG - Tareas en segundo plano" en el ACP.';
$xp_tarea_estado = 'alerta';
$tarea = $db->fetch_array($db->simple_select('tasks', 'minute, hour, day, month, weekday, enabled, lastrun, nextrun', "file='op_xp_snapshot'", array('limit' => 1)));
if ($tarea) {
    $diaria = ctype_digit($tarea['minute']) && ctype_digit($tarea['hour']) && $tarea['day'] === '*' && $tarea['month'] === '*' && $tarea['weekday'] === '*';
    $cuando = $diaria
        ? 'una vez al día, a las ' . str_pad($tarea['hour'], 2, '0', STR_PAD_LEFT) . ':' . str_pad($tarea['minute'], 2, '0', STR_PAD_LEFT) . ' (hora del servidor, zona ' . date_default_timezone_get() . '; ahora son las ' . date('H:i') . ')'
        : 'según la programación ' . $tarea['minute'] . ' ' . $tarea['hour'] . ' ' . $tarea['day'] . ' ' . $tarea['month'] . ' ' . $tarea['weekday'] . ' (minuto, hora, día, mes, día de la semana)';
    $xp_frecuencia = 'El snapshot se guarda ' . $cuando . '.'
        . ((int) $tarea['enabled'] !== 1 ? ' La tarea está DESACTIVADA.' : '')
        . ((int) $tarea['lastrun'] > 0 ? ' Última ejecución: ' . my_date('d/m/Y H:i', (int) $tarea['lastrun']) . '.' : ' Aún no se ha ejecutado.')
        . ((int) $tarea['enabled'] === 1 && (int) $tarea['nextrun'] > 0 ? ' Próxima: ' . my_date('d/m/Y H:i', (int) $tarea['nextrun']) . '.' : '');
    $xp_tarea_estado = (int) $tarea['enabled'] === 1 ? 'activa' : 'alerta';
}
$xp_frecuencia = xp_h($xp_frecuencia);

eval("\$page = \"".$templates->get("staff_xp_snapshot")."\";");
output_page($page);
