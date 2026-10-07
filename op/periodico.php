<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'periodico.php');

require_once "./../global.php";

require_once MYBB_ROOT . 'inc/plugins/lib/periodico.php';

$numero = $mybb->get_input('edicion', MyBB::INPUT_INT);
if (!$numero) { $numero = $mybb->get_input('numero', MyBB::INPUT_INT); }
$slug = $mybb->get_input('slug', MyBB::INPUT_STRING);
$edicion = opg_periodico_buscar_publico($db, $numero, $slug);

if (($numero > 0 || $slug !== '') && !$edicion) {
    error('No se encontró esa edición del periódico.');
}

$anio = $mybb->get_input('anio', MyBB::INPUT_INT);
$archivo_html = '';
foreach (opg_periodico_archivo_publico($db, $anio) as $archivo) {
    $imagen = $archivo['portada'] !== '' ? '<img src="' . opg_periodico_esc($archivo['portada']) . '" alt="">' : '';
    $archivo_html .= '<a class="periodico-archivo__item" href="periodico.php?edicion=' . (int) $archivo['numero'] . '">' . $imagen . '<span><small>Edición ' . (int) $archivo['numero'] . ' · ' . my_date('d/m/Y', strtotime($archivo['fecha_publicacion'])) . '</small><strong>' . opg_periodico_esc($archivo['titulo']) . '</strong></span></a>';
}
if ($archivo_html === '') { $archivo_html = '<p>No hay ediciones en este archivo.</p>'; }

if ($edicion) {
    $periodico_html = opg_periodico_render_edicion($edicion);
    $periodico_titulo = opg_periodico_esc($edicion['titulo']);
    eval("\$page = \"".$templates->get("op_periodico_edicion")."\";");
} else {
    eval("\$page = \"".$templates->get("op_periodico")."\";");
}
output_page($page);
