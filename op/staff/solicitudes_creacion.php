<?php
/**
 * Staff - Moderacion centralizada de solicitudes de creacion.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'solicitudes_creacion.php');
require_once './../../global.php';
require_once './../functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/op_solicitudes_creacion/functions.php';

global $templates, $mybb, $db, $header, $headerinclude, $footer;

$uid = (int)$mybb->user['uid'];
if (!op_solicitudes_creacion_es_staff($uid)) {
    eval("\$page = \"" . $templates->get('sin_permisos') . "\";");
    output_page($page);
    exit;
}

function op_solicitudes_creacion_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function op_solicitudes_creacion_url_personaje($uid, $nombre)
{
    $uid = (int)$uid;
    $nombre = op_solicitudes_creacion_html($nombre !== '' ? $nombre : 'Usuario #' . $uid);
    if ($uid <= 0) {
        return $nombre;
    }

    return '<a href="/op/personaje.php?uid=' . $uid . '" target="_blank" rel="noopener">' . $nombre . '</a>';
}

function op_solicitudes_creacion_render_acciones($tema, $postKey, $filtroFid)
{
    $tid = (int)$tema['tid'];
    $hidden = '<input type="hidden" name="my_post_key" value="' . op_solicitudes_creacion_html($postKey) . '">'
        . '<input type="hidden" name="tid" value="' . $tid . '">';
    if ($filtroFid !== null) {
        $hidden .= '<input type="hidden" name="perfil" value="' . (int)$filtroFid . '">';
    }

    $html = '<div class="sc-card__acciones">'
        . '<form method="post" action="/op/staff/solicitudes_creacion.php">' . $hidden
        . '<input type="hidden" name="accion" value="completar">'
        . '<button type="submit" class="btn-op btn-op--sm btn-op--exito" onclick="return confirm(\'¿Marcar esta solicitud como completada?\');">'
        . '<i class="fa-solid fa-check" aria-hidden="true"></i> Completada</button></form>'
        . '<form method="post" action="/op/staff/solicitudes_creacion.php">' . $hidden
        . '<input type="hidden" name="accion" value="cancelar">'
        . '<button type="submit" class="btn-op btn-op--sm btn-op--peligro" onclick="return confirm(\'¿Cancelar esta solicitud?\');">'
        . '<i class="fa-solid fa-ban" aria-hidden="true"></i> Cancelada</button></form>';

    return $html . '</div>';
}

function op_solicitudes_creacion_render_tarjeta($tema, array $posteadores, $postKey, $filtroFid)
{
    $tid = (int)$tema['tid'];
    $perfil = $tema['perfil'] ?? array('nombre' => 'Sin perfil');
    $moderadores = '';
    foreach ($posteadores as $moderador) {
        $moderadores .= '<li>' . op_solicitudes_creacion_url_personaje(
            (int)$moderador['uid'],
            (string)$moderador['username']
        ) . '</li>';
    }
    if ($moderadores === '') {
        $moderadores = '<li class="sc-card__sin-respuestas">Nadie respondió todavía.</li>';
    }

    $lastpost = (int)$tema['lastpost'];
    $dias = max(0, (int)floor((TIME_NOW - $lastpost) / 86400));
    $fecha = my_date('d/m/Y H:i', $lastpost);

    return '<article class="sc-card">'
        . '<div class="sc-card__cinta"><span class="sc-perfil">'
        . op_solicitudes_creacion_html($perfil['nombre'] ?? 'Sin perfil') . '</span><span>TID ' . $tid . '</span></div>'
        . '<div class="sc-card__cuerpo"><h3><a href="/showthread.php?tid=' . $tid . '&amp;action=lastpost" target="_blank" rel="noopener">'
        . op_solicitudes_creacion_html($tema['subject']) . '</a></h3>'
        . '<dl class="sc-card__datos">'
        . '<div><dt><i class="fa-solid fa-user" aria-hidden="true"></i> Solicitante</dt><dd>'
        . op_solicitudes_creacion_url_personaje((int)$tema['uid'], (string)$tema['username']) . '</dd></div>'
        . '<div><dt><i class="fa-solid fa-clock" aria-hidden="true"></i> Última actividad</dt><dd>'
        . op_solicitudes_creacion_html($fecha) . ' · hace ' . $dias . ($dias === 1 ? ' día' : ' días') . '</dd></div>'
        . '</dl>'
        . '<div class="sc-card__moderadores"><strong><i class="fa-solid fa-comments" aria-hidden="true"></i> Participaron</strong>'
        . '<ul>' . $moderadores . '</ul></div>'
        . op_solicitudes_creacion_render_acciones($tema, $postKey, $filtroFid)
        . '</div></article>';
}

function op_solicitudes_creacion_render_seccion($titulo, $descripcion, $icono, $grupo, array $temas, array $posteadores, $postKey, $filtroFid)
{
    $tarjetas = '';
    foreach ($temas as $tema) {
        $tarjetas .= op_solicitudes_creacion_render_tarjeta(
            $tema,
            $posteadores[(int)$tema['tid']] ?? array(),
            $postKey,
            $filtroFid
        );
    }
    if ($tarjetas === '') {
        $tarjetas = '<p class="opg-vacio sc-vacio"><i class="fa-solid fa-check" aria-hidden="true"></i> No hay solicitudes en este estado.</p>';
    }

    $grupoClase = str_replace('_', '-', $grupo);
    return '<section class="sc-seccion sc-seccion--' . op_solicitudes_creacion_html($grupoClase) . '">'
        . '<header class="sc-seccion__cabecera"><span class="sc-seccion__icono"><i class="fa-solid fa-' . op_solicitudes_creacion_html($icono) . '" aria-hidden="true"></i></span>'
        . '<div><h2>' . op_solicitudes_creacion_html($titulo) . ' <span>' . count($temas) . '</span></h2><p>'
        . op_solicitudes_creacion_html($descripcion) . '</p></div></header>'
        . '<div class="sc-grid">' . $tarjetas . '</div></section>';
}

function op_solicitudes_creacion_render_estadisticas(array $estadisticas)
{
    $filas = '';
    $totalCompletadas = 0;
    $totalCanceladas = 0;
    foreach (OP_SOLICITUDES_CREACION_PERFILES as $fid => $perfil) {
        $completadas = (int)($estadisticas[(int)$fid]['completadas'] ?? 0);
        $canceladas = (int)($estadisticas[(int)$fid]['canceladas'] ?? 0);
        $totalCompletadas += $completadas;
        $totalCanceladas += $canceladas;
        $filas .= '<tr><th scope="row">' . op_solicitudes_creacion_html($perfil['nombre']) . '</th>'
            . '<td>' . $completadas . '</td><td>' . $canceladas . '</td><td>' . ($completadas + $canceladas) . '</td></tr>';
    }

    return '<div class="sc-tabla-wrap"><table class="sc-tabla"><thead><tr><th>Perfil</th><th>Completadas</th><th>Canceladas</th><th>Total</th></tr></thead>'
        . '<tbody>' . $filas . '</tbody><tfoot><tr><th scope="row">Total</th><td>' . $totalCompletadas . '</td><td>'
        . $totalCanceladas . '</td><td>' . ($totalCompletadas + $totalCanceladas) . '</td></tr></tfoot></table></div>';
}

function op_solicitudes_creacion_render_distribucion(array $distribucion)
{
    $filas = '';
    $totalSinRespuesta = 0;
    $totalUsuarioRespondio = 0;
    foreach (OP_SOLICITUDES_CREACION_PERFILES as $fid => $perfil) {
        $sinRespuesta = (int)($distribucion[(int)$fid]['sin_respuesta'] ?? 0);
        $usuarioRespondio = (int)($distribucion[(int)$fid]['usuario_respondio'] ?? 0);
        $totalSinRespuesta += $sinRespuesta;
        $totalUsuarioRespondio += $usuarioRespondio;
        $filas .= '<tr><th scope="row">' . op_solicitudes_creacion_html($perfil['nombre']) . '</th>'
            . '<td>' . $sinRespuesta . '</td><td>' . $usuarioRespondio . '</td></tr>';
    }

    return '<div class="sc-tabla-wrap"><table class="sc-tabla"><thead><tr><th>Perfil</th>'
        . '<th>Sin respuesta de moderación</th><th>El usuario respondió</th></tr></thead>'
        . '<tbody>' . $filas . '</tbody><tfoot><tr><th scope="row">Total</th><td>'
        . $totalSinRespuesta . '</td><td>' . $totalUsuarioRespondio . '</td></tr></tfoot></table></div>';
}

function op_solicitudes_creacion_render_actividad(array $actividad)
{
    if (empty($actividad)) {
        return '<p class="opg-vacio">Todavía no hay peticiones resueltas registradas.</p>';
    }

    $filas = '';
    foreach ($actividad as $moderador) {
        $desglose = '';
        foreach (OP_SOLICITUDES_CREACION_PERFILES as $fid => $perfil) {
            $desglose .= '<td>' . (int)($moderador['perfiles'][(int)$fid] ?? 0) . '</td>';
        }
        $filas .= '<tr><th scope="row">'
            . op_solicitudes_creacion_url_personaje((int)$moderador['uid'], (string)$moderador['username']) . '</th>'
            . '<td><strong>' . (int)$moderador['total'] . '</strong></td>'
            . '<td>' . (int)$moderador['completadas'] . '</td>'
            . '<td>' . (int)$moderador['canceladas'] . '</td>'
            . $desglose . '</tr>';
    }

    $encabezadosPerfiles = '';
    foreach (OP_SOLICITUDES_CREACION_PERFILES as $perfil) {
        $encabezadosPerfiles .= '<th>' . op_solicitudes_creacion_html($perfil['nombre']) . '</th>';
    }

    return '<div class="sc-tabla-wrap"><table class="sc-tabla sc-tabla--actividad"><thead><tr>'
        . '<th>Moderador</th><th>Total</th><th>Completadas</th><th>Canceladas</th>'
        . $encabezadosPerfiles
        . '</tr></thead><tbody>' . $filas . '</tbody></table></div>';
}

$filtroInput = $mybb->get_input('perfil', MyBB::INPUT_INT);
$filtroFid = isset(OP_SOLICITUDES_CREACION_PERFILES[$filtroInput]) ? (int)$filtroInput : null;
$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && in_array($accion, array('completar', 'cancelar'), true)) {
    verify_post_check($mybb->get_input('my_post_key', MyBB::INPUT_STRING));
    $resultado = op_solicitudes_creacion_resolver(
        $mybb->get_input('tid', MyBB::INPUT_INT),
        $accion,
        $uid
    );

    $params = array($resultado['ok'] ? 'resultado' : 'error' => (string)$resultado['code']);
    if ($filtroFid !== null) {
        $params['perfil'] = $filtroFid;
    }
    header('Location: /op/staff/solicitudes_creacion.php?' . http_build_query($params));
    exit;
}

$pendientes = op_solicitudes_creacion_listar_pendientes($filtroFid);
$todosTemas = array_merge(
    $pendientes['sin_respuesta'],
    $pendientes['reabiertas'],
    $pendientes['esperando_usuario']
);
$tids = array();
$autores = array();
foreach ($todosTemas as $tema) {
    $tids[] = (int)$tema['tid'];
    $autores[(int)$tema['tid']] = (int)$tema['uid'];
}
$posteadores = op_solicitudes_creacion_cargar_posteadores($tids, $autores);
$distribucionPendientes = op_solicitudes_creacion_distribucion_pendientes();
$estadisticas = op_solicitudes_creacion_estadisticas();
$actividadModeradores = op_solicitudes_creacion_actividad_moderadores();
$postKey = generate_post_check();

$sc_filtro_options = '<option value="">Todos los perfiles</option>';
foreach (OP_SOLICITUDES_CREACION_PERFILES as $fid => $perfil) {
    $selected = $filtroFid === (int)$fid ? ' selected' : '';
    $sc_filtro_options .= '<option value="' . (int)$fid . '"' . $selected . '>'
        . op_solicitudes_creacion_html($perfil['nombre']) . '</option>';
}

$sc_sin_respuesta = op_solicitudes_creacion_render_seccion(
    'Sin respuesta de moderación',
    'Solicitudes nuevas que todavía no recibieron respuesta.',
    'inbox',
    'sin_respuesta',
    $pendientes['sin_respuesta'],
    $posteadores,
    $postKey,
    $filtroFid
);
$sc_reabiertas = op_solicitudes_creacion_render_seccion(
    'El usuario respondió',
    'El solicitante volvió a publicar y espera una nueva revisión.',
    'rotate-right',
    'reabiertas',
    $pendientes['reabiertas'],
    $posteadores,
    $postKey,
    $filtroFid
);
$sc_esperando_usuario = op_solicitudes_creacion_render_seccion(
    'A la espera del usuario',
    'Moderación ya respondió; el siguiente paso corresponde al solicitante.',
    'hourglass-half',
    'esperando_usuario',
    $pendientes['esperando_usuario'],
    $posteadores,
    $postKey,
    $filtroFid
);
$sc_estadisticas = op_solicitudes_creacion_render_estadisticas($estadisticas);
$sc_distribucion = op_solicitudes_creacion_render_distribucion($distribucionPendientes);
$sc_actividad_moderadores = op_solicitudes_creacion_render_actividad($actividadModeradores);
$sc_total_moderar = count($pendientes['sin_respuesta']) + count($pendientes['reabiertas']);
$sc_total_esperando = count($pendientes['esperando_usuario']);

$sc_aviso = '';
$mensajesOk = array(
    'completada' => 'La solicitud se archivó como completada.',
    'cancelada' => 'La solicitud se archivó como cancelada.',
);
$mensajesError = array(
    'solicitud_invalida' => 'La solicitud indicada no existe o la acción no es válida.',
    'perfil_invalido' => 'El tema ya no pertenece a un perfil de solicitudes.',
    'estado_invalido' => 'La solicitud ya no está abierta o visible.',
    'destino_invalido' => 'No se pudo determinar el foro de destino.',
    'solicitud_actualizada' => 'La solicitud cambió antes de completar la operación. Recarga la página.',
    'registro_no_disponible' => 'Reactiva el plugin para habilitar el historial de moderaciones antes de resolver solicitudes.',
);
$resultadoCode = $mybb->get_input('resultado', MyBB::INPUT_STRING);
$errorCode = $mybb->get_input('error', MyBB::INPUT_STRING);
if (isset($mensajesOk[$resultadoCode])) {
    $sc_aviso = '<p class="aviso ok" role="status">' . op_solicitudes_creacion_html($mensajesOk[$resultadoCode]) . '</p>';
} elseif (isset($mensajesError[$errorCode])) {
    $sc_aviso = '<p class="aviso err" role="alert">' . op_solicitudes_creacion_html($mensajesError[$errorCode]) . '</p>';
}

add_breadcrumb('Solicitudes de creación', '/op/staff/solicitudes_creacion.php');
eval("\$page = \"" . $templates->get('staff_solicitudes_creacion') . "\";");
output_page($page);
