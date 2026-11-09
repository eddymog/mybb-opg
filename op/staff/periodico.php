<?php

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'periodico.php');
require_once './../../global.php';
require_once './../functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/periodico.php';

global $db, $mybb, $templates;

if (!opg_periodico_puede_editar((int) $mybb->user['uid'])) {
    eval("\$page = \"" . $templates->get('sin_permisos') . "\";");
    output_page($page);
    exit;
}

$errores = array();
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'crear') {
    verify_post_check($mybb->get_input('my_post_key'));
    $publicacion_nueva = opg_periodico_publicacion($mybb->get_input('publicacion', MyBB::INPUT_STRING));
    $datos = opg_periodico_edicion_vacia($publicacion_nueva);
    $datos['numero'] = (int) $mybb->get_input('numero', MyBB::INPUT_INT);
    $datos['titulo'] = trim($mybb->get_input('titulo', MyBB::INPUT_STRING));
    $datos['slug'] = trim($mybb->get_input('slug', MyBB::INPUT_STRING));
    $validado = opg_periodico_validar_payload($datos, $errores);
    if ($validado && opg_periodico_conflicto($db, $validado)) {
        $errores[] = 'El número o la dirección web ya pertenece a otra edición de esta publicación.';
    } elseif ($validado) {
        $id = opg_periodico_guardar($db, 0, $validado, (int) $mybb->user['uid']);
        redirect('periodico_editar.php?id=' . $id, 'Edición creada.');
    }
}

$filtro = $mybb->get_input('estado', MyBB::INPUT_STRING);
$filtro_publicacion = $mybb->get_input('publicacion', MyBB::INPUT_STRING);
$filtro_publicacion = in_array($filtro_publicacion, array('periodico', 'prensa_rosa'), true) ? $filtro_publicacion : '';
$filas = '';
foreach (opg_periodico_listar($db, $filtro, $filtro_publicacion) as $edicion) {
    $estado = opg_periodico_esc($edicion['estado']);
    $publicacion = opg_periodico_publicacion($edicion['publicacion'] ?? 'periodico');
    $publicacion_nombre = opg_periodico_publicacion_nombre($publicacion);
    $fecha = $edicion['fecha_publicacion'] ? my_date('d/m/Y', strtotime($edicion['fecha_publicacion'])) : 'Sin publicar';
    $filas .= '<article class="opg-card periodico-lista__item periodico-lista__item--' . str_replace('_', '-', $publicacion) . '">'
        . '<div><span class="periodico-publicacion periodico-publicacion--' . str_replace('_', '-', $publicacion) . '">' . opg_periodico_esc($publicacion_nombre) . '</span><span class="periodico-estado periodico-estado--' . $estado . '">' . ucfirst($estado) . '</span><strong>Edición ' . (int) $edicion['numero'] . '</strong><h2>' . opg_periodico_esc($edicion['titulo']) . '</h2><p>' . opg_periodico_esc($edicion['subtitulo']) . '</p><small>' . $fecha . '</small></div>'
        . '<a class="btn-op btn-op--secundario btn-op--sm" href="periodico_editar.php?id=' . (int) $edicion['id'] . '">Editar</a>'
        . '</article>';
}
if ($filas === '') { $filas = '<p class="opg-vacio">No hay ediciones para este filtro.</p>'; }
$errores_html = $errores ? '<div class="periodico-alerta">' . implode('<br>', array_map('opg_periodico_esc', $errores)) . '</div>' : '';
$post_key = generate_post_check();
$filtro_publicacion_query = $filtro_publicacion !== '' ? '&amp;publicacion=' . opg_periodico_esc($filtro_publicacion) : '';
$filtro_todas_clase = $filtro === '' && $filtro_publicacion === '' ? ' periodico-filtro-activo' : '';
$filtro_periodico_clase = $filtro_publicacion === 'periodico' ? ' periodico-filtro-activo' : '';
$filtro_prensa_rosa_clase = $filtro_publicacion === 'prensa_rosa' ? ' periodico-filtro-activo periodico-filtro-activo--rosa' : '';
$filtro_borrador_clase = $filtro === 'borrador' ? ' periodico-filtro-activo' : '';
$filtro_publicado_clase = $filtro === 'publicado' ? ' periodico-filtro-activo' : '';
$filtro_archivado_clase = $filtro === 'archivado' ? ' periodico-filtro-activo' : '';

eval("\$page = \"" . $templates->get('staff_periodico_listado') . "\";");
output_page($page);
