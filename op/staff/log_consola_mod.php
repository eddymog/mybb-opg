<?php
/**
 * Staff - Log de consola
 *
 * Historial de mybb_op_audit_consola_mod: acciones registradas por las
 * herramientas de staff/narración (crear objeto, editar ficha, salarios...).
 * Es de solo lectura, no hay ningún POST que cambie datos acá.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'log_consola_mod.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int)$mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('LOG_CONSOLA_POR_PAGINA', 50);

$q = trim($mybb->get_input('q'));

$where = '';
if ($q !== '') {
    // Wildcards de LIKE escapados aparte de las comillas: sin esto, buscar "50%"
    // se comportaría como comodín en vez de buscar el texto literal.
    $like = $db->escape_string(addcslashes($q, '%_'));
    $where = " WHERE `username` LIKE '%{$like}%' OR `razon` LIKE '%{$like}%' OR `log` LIKE '%{$like}%'";
}

$total   = (int)$db->fetch_field($db->query("SELECT COUNT(*) AS total FROM `mybb_op_audit_consola_mod`{$where}"), 'total');
$paginas = max(1, (int)ceil($total / LOG_CONSOLA_POR_PAGINA));
$pagina  = min(max(1, $mybb->get_input('pagina', MyBB::INPUT_INT)), $paginas);
$offset  = ($pagina - 1) * LOG_CONSOLA_POR_PAGINA;

$query_log = $db->query("
    SELECT * FROM `mybb_op_audit_consola_mod`{$where}
    ORDER BY `id` DESC
    LIMIT {$offset}, " . LOG_CONSOLA_POR_PAGINA . "
");

$logs = '';
while ($l = $db->fetch_array($query_log)) {
    $tiempo = htmlspecialchars(my_date('relative', strtotime($l['tiempo'])), ENT_QUOTES, 'UTF-8');
    $logs .= '<div class="log-fila">'
        . '<div class="log-cabecera">'
        . '<span class="log-id">#' . (int)$l['id'] . '</span>'
        . '<span class="log-staff">' . htmlspecialchars($l['username'], ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="log-razon">' . htmlspecialchars($l['razon'], ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="log-tiempo">' . $tiempo . '</span>'
        . '</div>'
        . '<div class="log-detalle">' . nl2br(htmlspecialchars($l['log'], ENT_QUOTES, 'UTF-8')) . '</div>'
        . '</div>';
}
if ($logs === '') {
    $logs = '<p class="opg-vacio">' . ($q !== '' ? 'Ninguna acción coincide con la búsqueda.' : 'Todavía no hay acciones registradas.') . '</p>';
}

$log_total   = $total;
$log_q       = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');
$logs_li     = $logs;
$log_limpiar = $q !== '' ? '<a class="opg-chip" href="log_consola_mod.php">Limpiar</a>' : '';

$log_paginacion = '';
if ($paginas > 1) {
    $qs = $q !== '' ? '&q=' . rawurlencode($q) : '';
    $log_paginacion = '<div class="paginacion">'
        . ($pagina > 1 ? '<a class="opg-chip" href="log_consola_mod.php?pagina=' . ($pagina - 1) . $qs . '">&larr; Más recientes</a>' : '<span></span>')
        . '<span class="paginacion-estado">Página ' . $pagina . ' de ' . $paginas . '</span>'
        . ($pagina < $paginas ? '<a class="opg-chip" href="log_consola_mod.php?pagina=' . ($pagina + 1) . $qs . '">Más antiguas &rarr;</a>' : '<span></span>')
        . '</div>';
}

eval("\$page = \"".$templates->get("staff_log_consola")."\";");
output_page($page);
