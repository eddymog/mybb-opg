<?php
/**
 * Staff - Log de entregas
 *
 * Historial de mybb_op_audit_general filtrado a categoria LIKE '%[Entregas]%'
 * (recompensas de autonarradas, aventuras de narrador y aventuras de usuario).
 * Es de solo lectura, no hay ningún POST que cambie datos acá.
 *
 * El campo `log` se guarda con HTML propio (negritas, <br>) armado por las
 * páginas de entrega, no texto plano: a diferencia de log_consola_mod.php,
 * acá NO se escapa al pintarlo — es el mismo contrato que ya tenía esta
 * página, solo con paginado y filtro encima.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'log_entregas.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int)$mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid) && !is_narra($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('LOG_ENTREGAS_POR_PAGINA', 50);

/**
 * Subcategorías reales que hay hoy en la tabla (p. ej. "[Entregas][Aventura Usuario]"),
 * para el filtro — se leen de la tabla en vez de hardcodearlas, así que una
 * categoría nueva aparece sola sin tocar este archivo.
 */
$categorias = array();
$query_cat = $db->query("
    SELECT DISTINCT `categoria` FROM `mybb_op_audit_general`
    WHERE `categoria` LIKE '%[Entregas]%'
    ORDER BY `categoria`
");
while ($c = $db->fetch_array($query_cat)) {
    $categorias[] = $c['categoria'];
}

function log_entregas_etiqueta($categoria)
{
    // "[Entregas][Aventura Usuario]" -> "Aventura Usuario"; si no matchea el
    // patrón esperado, se muestra tal cual en vez de ocultarla.
    return preg_match('/^\[Entregas\]\[(.+)\]$/', $categoria, $m) ? $m[1] : $categoria;
}

$cat = trim($mybb->get_input('cat'));
$q   = trim($mybb->get_input('q'));

$where = " WHERE `categoria` LIKE '%[Entregas]%'";
if ($cat !== '' && in_array($cat, $categorias, true)) {
    $where .= " AND `categoria` = '" . $db->escape_string($cat) . "'";
}
if ($q !== '') {
    // Wildcards de LIKE escapados aparte de las comillas: sin esto, buscar "50%"
    // se comportaría como comodín en vez de buscar el texto literal.
    $like = $db->escape_string(addcslashes($q, '%_'));
    $where .= " AND (`username` LIKE '%{$like}%' OR `log` LIKE '%{$like}%')";
}

$total   = (int)$db->fetch_field($db->query("SELECT COUNT(*) AS total FROM `mybb_op_audit_general`{$where}"), 'total');
$paginas = max(1, (int)ceil($total / LOG_ENTREGAS_POR_PAGINA));
$pagina  = min(max(1, $mybb->get_input('pagina', MyBB::INPUT_INT)), $paginas);
$offset  = ($pagina - 1) * LOG_ENTREGAS_POR_PAGINA;

$query_log = $db->query("
    SELECT * FROM `mybb_op_audit_general`{$where}
    ORDER BY `id` DESC
    LIMIT {$offset}, " . LOG_ENTREGAS_POR_PAGINA . "
");

$logs = '';
while ($l = $db->fetch_array($query_log)) {
    $tiempo   = htmlspecialchars(my_date('relative', strtotime($l['tiempo'])), ENT_QUOTES, 'UTF-8');
    $etiqueta = htmlspecialchars(log_entregas_etiqueta($l['categoria']), ENT_QUOTES, 'UTF-8');
    $logs .= '<div class="log-fila">'
        . '<div class="log-cabecera">'
        . '<span class="log-id">#' . (int)$l['id'] . '</span>'
        . '<span class="log-staff">' . htmlspecialchars($l['username'], ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="log-categoria">' . $etiqueta . '</span>'
        . '<span class="log-tiempo">' . $tiempo . '</span>'
        . '</div>'
        . '<div class="log-detalle">' . $l['log'] . '</div>'
        . '</div>';
}
if ($logs === '') {
    $logs = '<p class="opg-vacio">' . ($q !== '' || $cat !== '' ? 'Ninguna entrega coincide con el filtro.' : 'Todavía no hay entregas registradas.') . '</p>';
}

$log_total   = $total;
$log_q       = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');
$logs_li     = $logs;
$log_limpiar = ($q !== '' || $cat !== '') ? '<a class="opg-chip" href="log_entregas.php">Limpiar</a>' : '';

$log_categorias = '<option value="">Todas las categorías</option>';
foreach ($categorias as $c) {
    $sel = $c === $cat ? ' selected' : '';
    $log_categorias .= '<option value="' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
        . htmlspecialchars(log_entregas_etiqueta($c), ENT_QUOTES, 'UTF-8') . '</option>';
}

$log_paginacion = '';
if ($paginas > 1) {
    $qs = ($q !== '' ? '&q=' . rawurlencode($q) : '') . ($cat !== '' ? '&cat=' . rawurlencode($cat) : '');
    $log_paginacion = '<div class="paginacion">'
        . ($pagina > 1 ? '<a class="opg-chip" href="log_entregas.php?pagina=' . ($pagina - 1) . $qs . '">&larr; Más recientes</a>' : '<span></span>')
        . '<span class="paginacion-estado">Página ' . $pagina . ' de ' . $paginas . '</span>'
        . ($pagina < $paginas ? '<a class="opg-chip" href="log_entregas.php?pagina=' . ($pagina + 1) . $qs . '">Más antiguas &rarr;</a>' : '<span></span>')
        . '</div>';
}

eval("\$page = \"".$templates->get("staff_log_entregas")."\";");
output_page($page);
