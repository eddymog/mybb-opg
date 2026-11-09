<?php

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'periodico_editar.php');
require_once './../../global.php';
require_once './../functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/periodico.php';

global $db, $mybb, $templates;

if (!opg_periodico_puede_editar((int) $mybb->user['uid'])) {
    eval("\$page = \"" . $templates->get('sin_permisos') . "\";");
    output_page($page);
    exit;
}

$id = $mybb->get_input('id', MyBB::INPUT_INT);
$edicion = opg_periodico_cargar($db, $id);
if (!$edicion) { error('No se encontró esa edición.'); }
$publicacion = opg_periodico_publicacion($edicion['publicacion'] ?? 'periodico');
$publicacion_nombre = opg_periodico_publicacion_nombre($publicacion);
$publicacion_clase = 'publicacion-' . str_replace('_', '-', $publicacion);

$errores = array();
if ($mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
    if ($accion === 'guardar' || $accion === 'publicar' || $accion === 'preview') {
        $json = $mybb->get_input('edicion_json', MyBB::INPUT_STRING);
        $payload = json_decode($json, true);
        if (is_array($payload)) {
            // El destino almacenado manda aunque el navegador conserve un JS anterior.
            $payload['publicacion'] = $publicacion;
        }
        $datos = opg_periodico_validar_payload($payload, $errores);
        if ($datos) {
            $datos['publicacion'] = $publicacion;
        }
        if ($datos && opg_periodico_conflicto($db, $datos, $id)) {
            $errores[] = 'El número o la dirección web ya pertenece a otra edición de esta publicación.';
        } elseif ($datos) {
            if ($accion === 'preview') {
                $edicion_preview = array_merge($edicion, $datos);
                $periodico_html = opg_periodico_render_edicion($edicion_preview);
                eval("\$page = \"" . $templates->get('staff_periodico_preview') . "\";");
                output_page($page);
                exit;
            }
            opg_periodico_guardar($db, $id, $datos, (int) $mybb->user['uid']);
            if ($accion === 'publicar') { opg_periodico_cambiar_estado($db, $id, 'publicado', (int) $mybb->user['uid']); }
            redirect('periodico_editar.php?id=' . $id, $accion === 'publicar' ? 'Edición publicada.' : 'Cambios guardados.');
        }
    } elseif ($accion === 'archivar') {
        opg_periodico_cambiar_estado($db, $id, 'archivado', (int) $mybb->user['uid']);
        redirect('periodico_editar.php?id=' . $id, 'Edición archivada.');
    }
}

$editor = array(
    'publicacion' => opg_periodico_publicacion($edicion['publicacion'] ?? 'periodico'),
    'numero' => (int) $edicion['numero'], 'titulo' => $edicion['titulo'], 'subtitulo' => $edicion['subtitulo'],
    'slug' => $edicion['slug'], 'portada' => $edicion['portada'], 'tema' => $edicion['tema'],
    'firma_visible' => $edicion['firma_visible'], 'paginas' => $edicion['paginas']
);
$periodico_editor_json = json_encode($editor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$errores_html = $errores ? '<div class="periodico-alerta">' . implode('<br>', array_map('opg_periodico_esc', $errores)) . '</div>' : '';
$post_key = generate_post_check();

eval("\$page = \"" . $templates->get('staff_periodico_editor') . "\";");
output_page($page);
