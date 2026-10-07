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
    $datos = opg_periodico_edicion_vacia();
    $datos['numero'] = (int) $mybb->get_input('numero', MyBB::INPUT_INT);
    $datos['titulo'] = trim($mybb->get_input('titulo', MyBB::INPUT_STRING));
    $datos['slug'] = trim($mybb->get_input('slug', MyBB::INPUT_STRING));
    $validado = opg_periodico_validar_payload($datos, $errores);
    if ($validado && opg_periodico_conflicto($db, $validado)) {
        $errores[] = 'El número o la dirección web ya pertenece a otra edición.';
    } elseif ($validado) {
        $id = opg_periodico_guardar($db, 0, $validado, (int) $mybb->user['uid']);
        redirect('periodico_editar.php?id=' . $id, 'Edición creada.');
    }
}

$filtro = $mybb->get_input('estado', MyBB::INPUT_STRING);
$filas = '';
foreach (opg_periodico_listar($db, $filtro) as $edicion) {
    $estado = opg_periodico_esc($edicion['estado']);
    $fecha = $edicion['fecha_publicacion'] ? my_date('d/m/Y', strtotime($edicion['fecha_publicacion'])) : 'Sin publicar';
    $filas .= '<article class="opg-card periodico-lista__item">'
        . '<div><span class="periodico-estado periodico-estado--' . $estado . '">' . ucfirst($estado) . '</span><strong>Edición ' . (int) $edicion['numero'] . '</strong><h2>' . opg_periodico_esc($edicion['titulo']) . '</h2><p>' . opg_periodico_esc($edicion['subtitulo']) . '</p><small>' . $fecha . '</small></div>'
        . '<a class="btn-op btn-op--secundario btn-op--sm" href="periodico_editar.php?id=' . (int) $edicion['id'] . '">Editar</a>'
        . '</article>';
}
if ($filas === '') { $filas = '<p class="opg-vacio">No hay ediciones para este filtro.</p>'; }
$errores_html = $errores ? '<div class="periodico-alerta">' . implode('<br>', array_map('opg_periodico_esc', $errores)) . '</div>' : '';
$post_key = generate_post_check();

eval("\$page = \"" . $templates->get('staff_periodico_listado') . "\";");
output_page($page);
