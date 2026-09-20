<?php
/**
 * Directorio de Tripulaciones
 *
 * Listado público (cualquier usuario logueado, no hace falta ficha) de
 * todas las tripulaciones, con filtro por facción, por "buscando miembros"
 * y por cantidad mínima de miembros. Ver docs/200_Design_Tripulacion.md,
 * sección "Página 1". Solo lectura — la gestión vive en
 * op/tripulacion.php?id=X.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tripulaciones.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if ($uid === 0) {
    $mensaje_redireccion = "Tienes que estar logueado para ver esta página.";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

// Mismas 5 facciones confirmadas que usa tripulacion_solicitar.php.
$TRIPU_FACCIONES = array('Pirata', 'Marina', 'CipherPol', 'Revolucionario', 'Cazadores');

$f_faccion  = trim($mybb->get_input('faccion', MyBB::INPUT_STRING));
$f_buscando = $mybb->get_input('buscando', MyBB::INPUT_INT) ? 1 : 0;
$f_min      = $mybb->get_input('min_miembros', MyBB::INPUT_INT);

$where = array("estado != 2"); // Disueltas no se listan; Activas e Inactivas sí.
if (in_array($f_faccion, $TRIPU_FACCIONES, true)) {
    $where[] = "faccion='" . $db->escape_string($f_faccion) . "'";
}
if ($f_buscando) {
    $where[] = "buscando_miembros=1";
}

$tripulaciones = array();
$query = $db->query("
    SELECT t.*, (SELECT COUNT(*) FROM `mybb_op_tripulaciones_miembros` m WHERE m.tripulacion_id = t.id) AS cantidad_miembros
    FROM `mybb_op_tripulaciones` t
    WHERE " . implode(' AND ', $where) . "
    ORDER BY t.nombre ASC
    LIMIT 100
");
while ($t = $db->fetch_array($query)) {
    if ($f_min > 0 && (int) $t['cantidad_miembros'] < $f_min) {
        continue; // Filtro por cantidad: más simple hacerlo acá que con HAVING sobre una subquery.
    }
    $tripulaciones[] = $t;
}

// ── Variables para el template ────────────────────────────────────────────

$bbname_html = htmlspecialchars($mybb->settings['bbname']);

$ESTADOS = array('Activa', 'Inactiva', 'Disuelta');

$faccion_options_html = '<option value="">Todas</option>';
foreach ($TRIPU_FACCIONES as $f) {
    $sel = $f_faccion === $f ? ' selected' : '';
    $faccion_options_html .= '<option value="' . htmlspecialchars($f) . '"' . $sel . '>' . htmlspecialchars($f) . '</option>';
}

$buscando_checked = $f_buscando ? ' checked' : '';
$min_miembros_val = $f_min > 0 ? (int) $f_min : '';

$tarjetas_html = '';
foreach ($tripulaciones as $t) {
    $logo_html = !empty($t['logo'])
        ? '<img src="/' . htmlspecialchars($t['logo']) . '" alt="" class="td-logo">'
        : '<div class="td-logo td-logo-vacio"><i class="fa-solid fa-flag"></i></div>';

    $estado_int = (int) $t['estado'];
    $estado_html = $estado_int !== 0
        ? '<span class="td-badge td-badge-estado">' . htmlspecialchars($ESTADOS[$estado_int] ?? '') . '</span>'
        : '';

    $mensaje_html = !empty($t['mensaje_publico'])
        ? '<p class="td-mensaje">' . htmlspecialchars($t['mensaje_publico']) . '</p>'
        : '';

    $tarjetas_html .= '<a class="opg-card td-tarjeta" href="tripulacion.php?id=' . (int) $t['id'] . '" style="--opg-card-acento: var(--opg-naranja);">'
        . $logo_html
        . '<span class="opg-card__titulo">' . htmlspecialchars($t['nombre']) . '</span>'
        . '<span class="td-badges">'
        . '<span class="td-badge">' . htmlspecialchars($t['faccion']) . '</span>'
        . '<span class="td-badge">' . (int) $t['cantidad_miembros'] . ' miembro' . ((int) $t['cantidad_miembros'] === 1 ? '' : 's') . '</span>'
        . $estado_html
        . '</span>'
        . $mensaje_html
        . '</a>';
}

if ($tarjetas_html === '') {
    $tarjetas_html = '<p class="opg-vacio">Ninguna tripulación coincide con estos filtros.</p>';
}

eval("\$page = \"" . $templates->get("op_tripulaciones") . "\";");
output_page($page);
