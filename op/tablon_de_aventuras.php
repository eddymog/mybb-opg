<?php
$GLOBALS['ta_boot_fase'] = 'inicio';
register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
        return;
    }
    $fase = isset($GLOBALS['ta_boot_fase']) ? preg_replace('/[^a-z0-9_-]/i', '', $GLOBALS['ta_boot_fase']) : 'desconocida';
    $codigo = strtoupper(substr(sha1($fase . '|' . $error['message'] . '|' . $error['line']), 0, 8));
    error_log('[tablon_de_aventuras][' . $codigo . '][' . $fase . '] ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Error del tablón</title></head>'
        . '<body><main style="max-width:720px;margin:48px auto;padding:24px;font:16px/1.5 sans-serif">'
        . '<h1>No se pudo cargar el tablón</h1><p>Fase: <strong>' . htmlspecialchars($fase, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<br>Código: <strong>' . $codigo . '</strong></p></main></body></html>';
});

/**
 * Tablón de aventuras: Mis, Otras, Solicitar y Narradores & Narrados.
 *
 * Rediseño completo de la parte de jugadores de Calculadora_Tiers.php. La interfaz usa
 * HTMX: las acciones (?action=...) responden con fragmentos HTML. La gestión (aprobar,
 * denegar, finalizar, notas...) vive en op/staff/consola_aventuras.php y el pago de
 * recompensas en op/staff/aventuras_recompensas.php: esta página no atiende ninguna de
 * sus acciones.
 *
 * Documentos: docs/101_Requirements_Registro.md, docs/201_DesignPlan_Registro.md y
 * docs/301_ImplementationPlan_Registro.md.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tablon_de_aventuras.php');
$GLOBALS['ta_boot_fase'] = 'global';
require_once "./../global.php";
$GLOBALS['ta_boot_fase'] = 'config';
require "./../inc/config.php";
$GLOBALS['ta_boot_fase'] = 'funciones-op';
require_once "./functions/op_functions.php";
$GLOBALS['ta_boot_fase'] = 'dominio';
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
$GLOBALS['ta_boot_fase'] = 'recompensas';
require_once MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
$GLOBALS['ta_boot_fase'] = 'entregas';
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';
$GLOBALS['ta_boot_fase'] = 'vistas';
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras_vistas.php';
$GLOBALS['ta_boot_fase'] = 'controlador';

global $mybb, $db, $templates, $headerinclude, $header, $footer;
$uid = (int) $mybb->user['uid'];

// Acciones atendidas por esta página. Cualquier otra responde 404, sea cual sea el rol.
$ACCIONES_GET = array(
    'tab', 'buscar_fichas', 'buscar_modo_vista', 'elegir_ficha', 'buscar_islas', 'elegir_isla', 'mis_activas', 'listar', 'sin_narrador',
    'contar_propuestas', 'narradores_disponibles', 'form_oferta', 'ver', 'form_editar', 'form_postular', 'form_renunciar',
    'form_vincular', 'form_entrega', 'buscar_temas', 'elegir_tema', 'quitar_chip', 'quitar_isla', 'cerrar_modal', 'form_participante', 'quitar_narrador', 'quitar_tema', 'buscar_aventuras',
    'notificaciones', 'historial_usuario', 'validar_cupos',
);
$ACCIONES_POST = array(
    'crear', 'editar', 'postular_narrador', 'renunciar_narrador', 'pedir_borrado', 'vincular_aventura', 'enviar_entrega',
    'ofrecerme_narrador', 'renovar_oferta', 'retirar_oferta',
    'aceptar_propuesta', 'rechazar_propuesta', 'retirar_propuesta', 'leer_notificacion',
);

// ── Utilidades de respuesta ──────────────────────────────────────────────────

function ta_es_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

/** Envía un fragmento y termina. */
function ta_responder($html, $estado = 200, $cabeceras = array())
{
    http_response_code($estado);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('Vary: HX-Request');
    foreach ($cabeceras as $k => $v) {
        header($k . ': ' . $v);
    }
    echo $html;
    exit;
}

/** Entrada de la petición (POST antes que GET), como texto. */
function ta_in($clave, $defecto = '')
{
    if (isset($_POST[$clave]) && !is_array($_POST[$clave])) {
        return trim((string) $_POST[$clave]);
    }
    if (isset($_GET[$clave]) && !is_array($_GET[$clave])) {
        return trim((string) $_GET[$clave]);
    }
    return $defecto;
}

/** Entrada que puede ser una lista (participantes[]). */
function ta_in_lista($clave)
{
    if (isset($_POST[$clave]) && is_array($_POST[$clave])) {
        return $_POST[$clave];
    }
    if (isset($_GET[$clave]) && is_array($_GET[$clave])) {
        return $_GET[$clave];
    }
    return array();
}

/**
 * Error de una acción. Los de regla (422) y conflicto (409) se insertan en el contenedor
 * de avisos $retarget; los de permiso o inexistencia (403, 404...) los recoge la
 * configuración de HTMX en #ta-avisos.
 */
function ta_error($codigo, $mensajes, $retarget = '')
{
    $cabeceras = array();
    if ($retarget !== '' && in_array((int) $codigo, array(409, 422), true)) {
        $cabeceras['HX-Retarget'] = $retarget;
        $cabeceras['HX-Reswap'] = 'innerHTML';
    }
    ta_responder(opg_av_v_aviso('err', $mensajes), (int) $codigo, $cabeceras);
}

/** Resultado de una función de la biblioteca: si falló, responde el error. */
function ta_exigir($r, $retarget = '')
{
    if (empty($r['ok'])) {
        ta_error(isset($r['codigo']) ? $r['codigo'] : 500, isset($r['errores']) ? $r['errores'] : array('No se pudo completar la acción.'), $retarget);
    }
    return $r;
}

function ta_tiene_ficha($uid)
{
    static $cache = array();
    if (!isset($cache[$uid])) {
        $cache[$uid] = $uid > 0 && does_ficha_exist($uid);
    }
    return $cache[$uid];
}

function ta_ctx($uid, $resaltar = 0)
{
    return array('uid' => $uid, 'tiene_ficha' => ta_tiene_ficha($uid), 'resaltar' => (int) $resaltar);
}

/**
 * Obtiene la plantilla de MyBB y usa la copia versionada si el registro no está
 * sincronizado todavía. El valor devuelto está preparado para el eval de MyBB.
 */
function ta_plantilla_pagina()
{
    global $templates;
    $plantilla = $templates->get('op_tablon_de_aventuras');
    $sin_envoltura = preg_replace(
        '/<!--\s*(?:start|end):\s*op_tablon_de_aventuras\s*-->/i',
        '',
        $plantilla
    );
    if (trim($sin_envoltura) !== '') {
        return $plantilla;
    }
    $ruta = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_tablon_de_aventuras.html';
    if (!is_readable($ruta)) {
        throw new RuntimeException('No se encontró la plantilla op_tablon_de_aventuras.');
    }
    $plantilla = file_get_contents($ruta);
    if ($plantilla === false || trim($plantilla) === '') {
        throw new RuntimeException('La plantilla op_tablon_de_aventuras está vacía.');
    }
    return str_replace("\\'", "'", addslashes($plantilla));
}

/** Datos mínimos del usuario para los formularios. */
function ta_yo($uid)
{
    $f = opg_av_ficha($uid);
    return array('uid' => $uid, 'nombre' => $f ? $f['nombre'] : '');
}

/** Inserta un aviso en la zona de avisos de una tarjeta ya renderizada. */
function ta_tarjeta_con_aviso($html, $id, $aviso)
{
    $vacio = '<div class="ta-avisos" id="aviso-' . (int) $id . '" aria-live="polite"></div>';
    return str_replace($vacio, '<div class="ta-avisos" id="aviso-' . (int) $id . '" aria-live="polite">' . $aviso . '</div>', $html);
}

/** Marca un fragmento <article> como actualización fuera de banda. */
function ta_oob_tarjeta($html)
{
    return preg_replace('/^<article /', '<article hx-swap-oob="true" ', $html, 1);
}

/** Aviso global fuera de banda (zona #ta-avisos de la página). */
function ta_oob_aviso($tipo, $mensaje)
{
    return '<div class="ta-avisos-globales" id="ta-avisos" aria-live="polite" hx-swap-oob="true">' . opg_av_v_aviso($tipo, $mensaje) . '</div>';
}

function ta_oob_novedades($tab, $uid)
{
    $contenido = $tab === 'mias'
        ? opg_av_v_novedades_panel(opg_av_notificaciones_listar($uid), opg_av_notificaciones_contar($uid))
        : '';
    return '<div id="ta-novedades-slot" hx-swap-oob="innerHTML">' . $contenido . '</div>';
}

// ── Filtros de listados ──────────────────────────────────────────────────────

/** Filtros de la petición, saneados. */
function ta_filtros($uid, $ambito)
{
    $estado = ta_in('estado');
    $clave_valida = opg_av_estado_desde_clave($estado) !== null ? $estado : '';
    $rol = ta_in('rol');
    $uid_f = ta_in('uid');
    $q = ta_in('q');
    $es_otras = $ambito === 'otras';
    $vista = ta_in('vista', $es_otras ? 'actuales' : 'activas');
    $vistas = $es_otras ? array('actuales', 'historial', 'todas') : array('activas', 'historial', 'todas');
    return array(
        'yo' => $uid,
        'ambito' => $es_otras ? 'otras' : 'mias',
        'vista' => in_array($vista, $vistas, true) ? $vista : $vistas[0],
        'estado' => $clave_valida,
        'rol' => in_array($rol, array('narrador', 'jugador'), true) ? $rol : '',
        'uid' => ctype_digit($uid_f) && (int) $uid_f > 0 ? (int) $uid_f : '',
        'q' => strlen($q) <= 80 ? $q : substr($q, 0, 80),
        'densidad' => ta_in('densidad') === 'expandida' ? 'expandida' : 'compacta',
    );
}

function ta_url_listado($filtros, $pagina)
{
    $tab = $filtros['ambito'] === 'otras' ? 'otras' : 'mias';
    $params = array('tab' => $tab) + opg_av_params_filtros($filtros);
    unset($params['ambito']);
    if ((int) $pagina > 1) { $params['pagina'] = (int) $pagina; }
    return OPG_AV_URL . '?' . http_build_query($params, '', '&');
}

/** Pestaña en la que cae una solicitud para un usuario (para abrir enlaces ?id=N). */
function ta_pestana_de($id, $uid)
{
    $av = opg_av_obtener($id);
    if (!$av || $av['estado'] === 5) {
        return 'mias';
    }
    if (opg_av_es_parte($av, $uid)) {
        return 'mias';
    }
    return $av['estado'] === 4 ? 'narradores' : 'otras';
}

// ── Paneles ──────────────────────────────────────────────────────────────────

function ta_panel($tab, $uid, $resaltar = 0)
{
    $ctx = ta_ctx($uid, $resaltar);
    if ($tab === 'solicitud') {
        $form = opg_av_form_vacio();
        $form['autonarrada_disponibilidad'] = opg_av_autonarrada_disponibilidad($uid);
        return opg_av_v_panel_solicitud($form, ta_yo($uid), array(), ta_tiene_ficha($uid), opg_av_contar_activas($uid, 0));
    }
    if ($tab === 'narradores') {
        $sin = opg_av_sin_narrador_listar($uid, 1);
        if ($resaltar) {
            $av = opg_av_obtener($resaltar);
            if ($av && $av['estado'] === 4 && !opg_av_es_parte($av, $uid)) {
                $antes = opg_av_uno('SELECT COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras')
                    . ' pa WHERE pa.estado = 4 AND ' . opg_av_sql_no_mia($uid)
                    . ' AND (pa.created_at < ' . opg_av_sql($av['created_at'])
                    . ' OR (pa.created_at = ' . opg_av_sql($av['created_at']) . ' AND pa.id < ' . (int) $resaltar . '))');
                $sin = opg_av_sin_narrador_listar($uid, (int) floor($antes['n'] / OPG_AV_POR_PAGINA_SIN_NARRADOR) + 1);
            }
        }
        $pagina_narradores = max(1, (int) ta_in('pagina_narradores', ta_in('pagina', '1')));
        return '<div class="ta-mercado">' . opg_av_v_propuestas_narrador(opg_av_propuestas_listar($uid), $uid)
            . opg_av_v_bloque_narradores(opg_av_oferta_obtener($uid), opg_av_narradores_disponibles($pagina_narradores, $uid), ta_tiene_ficha($uid))
            . opg_av_v_bloque_sin_narrador($sin, $ctx) . '</div>';
    }
    if ($tab === 'otras') {
        $filtros = ta_filtros($uid, 'otras');
        $pagina = $resaltar ? opg_av_pagina_de($resaltar, $filtros) : max(1, (int) ta_in('pagina', '1'));
        $res = opg_av_listar($filtros, $pagina);
        return opg_av_v_panel_otras($filtros, $res, $ctx, opg_av_estadisticas($filtros));
    }
    $filtros = ta_filtros($uid, 'mias');
    $pagina = $resaltar ? opg_av_pagina_de($resaltar, $filtros) : max(1, (int) ta_in('pagina', '1'));
    $estadisticas = opg_av_estadisticas($filtros);
    return opg_av_v_panel_mias($filtros, opg_av_listar($filtros, $pagina), $ctx, !empty($estadisticas['hay_alguna']), $estadisticas, opg_av_activas_listar($uid));
}

function ta_panel_modo_vista($ficha)
{
    $fid = (int) $ficha['fid'];
    $nombre = trim((string) $ficha['nombre']);
    $nombre = $nombre !== '' ? $nombre : 'Personaje #' . $fid;
    return '<section class="ta-modo-vista" aria-labelledby="ta-modo-vista-titulo">'
        . '<header class="ta-modo-vista-cabecera"><div><span><i class="fa-solid fa-eye" aria-hidden="true"></i> Modo vista</span>'
        . '<h2 id="ta-modo-vista-titulo">Aventuras de ' . opg_av_h($nombre) . '</h2>'
        . '<p>Consulta de solo lectura de sus aventuras activas.</p></div>'
        . '<a class="btn-op btn-op--secundario" href="' . opg_av_h(OPG_AV_URL) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Mis aventuras</a></header>'
        . opg_av_v_mis_activas(opg_av_activas_listar($fid), $nombre, false) . '</section>';
}

// ── Página completa ──────────────────────────────────────────────────────────

$accion = isset($_GET['action']) ? (string) $_GET['action'] : '';
if (isset($_POST['action'])) {
    $accion = (string) $_POST['action'];
}

if ($accion === '') {
    if ($uid <= 0) {
        error_no_permission();
    }
    try {
        $vista_uid = ctype_digit(ta_in('modo_vista')) ? (int) ta_in('modo_vista') : 0;
        $ficha_vista = $vista_uid > 0 && $vista_uid !== $uid ? opg_av_ficha($vista_uid) : null;
        if ($ficha_vista) {
            $panel_html = ta_panel_modo_vista($ficha_vista);
            $tabs_html = '';
            $guia_html = '';
            $novedades_html = '';
            $selector_vista_html = opg_av_v_buscar_modo_vista($vista_uid);
        } else {
            $resaltar = ctype_digit(ta_in('id')) ? (int) ta_in('id') : 0;
            $tab = ta_in('tab');
            if (!in_array($tab, array('mias', 'otras', 'solicitud', 'narradores'), true)) {
                $tab = $resaltar ? ta_pestana_de($resaltar, $uid) : 'mias';
            }
            $panel_html = ta_panel($tab, $uid, $tab === 'solicitud' ? 0 : $resaltar);
            $tabs_html = opg_av_v_tabs($tab, opg_av_propuestas_pendientes_contar($uid));
            $guia_html = opg_av_v_guia();
            $novedades_html = $tab === 'mias' ? opg_av_v_novedades_panel(
                opg_av_notificaciones_listar($uid), opg_av_notificaciones_contar($uid)
            ) : '';
            $selector_vista_html = opg_av_v_buscar_modo_vista();
        }
        $post_key = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');
        $bbname_html = htmlspecialchars($mybb->settings['bbname'], ENT_QUOTES, 'UTF-8');
        $asset_url = htmlspecialchars(rtrim($mybb->settings['bburl'], '/'), ENT_QUOTES, 'UTF-8');
        $htmx_url = htmlspecialchars(rtrim($mybb->settings['bburl'], '/') . '/jscripts/htmx/htmx.min.js?v=2.0.4', ENT_QUOTES, 'UTF-8');
        eval("\$page = \"" . ta_plantilla_pagina() . "\";");
        if (!isset($page) || trim($page) === '') {
            throw new RuntimeException('El tablón se renderizó sin contenido.');
        }
        output_page($page);
    } catch (Throwable $e) {
        error_log('[tablon_de_aventuras] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        $mensaje = '<div class="error"><p>El tablón de aventuras no pudo cargarse. El error quedó registrado para su revisión.</p></div>';
        output_page('<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Tablón de aventuras</title>'
            . $headerinclude . '</head><body>' . $header . '<main style="max-width:900px;margin:40px auto;padding:20px">'
            . $mensaje . '</main>' . $footer . '</body></html>');
    }
    exit;
}

// ── Contrato de las acciones HTMX ────────────────────────────────────────────

$es_post = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!in_array($accion, $es_post ? $ACCIONES_POST : $ACCIONES_GET, true) || !ta_es_htmx()) {
    ta_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
}
if ($uid <= 0) {
    ta_responder('', 200, array('HX-Redirect' => rtrim($mybb->settings['bburl'], '/') . '/member.php?action=login'));
}
if ($es_post) {
    $clave = isset($_SERVER['HTTP_X_POST_KEY']) ? (string) $_SERVER['HTTP_X_POST_KEY'] : '';
    if (!verify_post_check($clave, true)) {
        ta_error(403, 'La sesión caducó. Recarga la página e inténtalo de nuevo.');
    }
}

$id = ctype_digit(ta_in('id')) ? (int) ta_in('id') : 0;
$ctx = ta_ctx($uid);

/** Solicitud por id o error 404. */
function ta_av($id)
{
    $av = $id > 0 ? opg_av_obtener($id) : null;
    if (!$av) {
        ta_error(404, 'La solicitud no existe.');
    }
    return $av;
}

switch ($accion) {

    // ── Navegación ──────────────────────────────────────────────────────────
    case 'tab':
        $tab = ta_in('tab');
        $tab = in_array($tab, array('mias', 'otras', 'solicitud', 'narradores'), true) ? $tab : 'mias';
        ta_responder(ta_panel($tab, $uid, $tab === 'solicitud' ? 0 : $id)
            . opg_av_v_tabs($tab, opg_av_propuestas_pendientes_contar($uid), true) . ta_oob_novedades($tab, $uid));

    case 'cerrar_modal':
        ta_responder('');

    case 'quitar_chip':
        $participantes = opg_av_enteros(ta_in_lista('participantes'));
        $quitar = (int) ta_in('fid');
        $participantes = array_values(array_filter($participantes, function ($fid) use ($quitar) { return $fid !== $quitar; }));
        if (ta_in('rol') !== 'narrador') { $participantes[] = $uid; }
        $participantes = array_values(array_unique($participantes));
        $cupos = opg_av_cupos_participantes($participantes, count($participantes), $id);
        ta_responder(opg_av_v_estado_cupos($cupos, ta_in('rol') === 'narrador' ? 'narrador' : 'jugador', $id, null, true));

    // Buscador de newthread.php: las aventuras que el usuario narra y aún no tienen tema.
    case 'buscar_aventuras':
        ta_responder(opg_av_v_resultados_aventuras(opg_av_aventuras_de_narrador($uid, ta_in('q'), (int) ta_in('prefijo'))));

    case 'form_participante':
        ta_responder(opg_av_v_selector_participante($id, true));

    case 'quitar_narrador':
        ta_responder(opg_av_v_buscador_std('narrador', $id));

    case 'quitar_isla':
        ta_responder(opg_av_v_campo_isla(null, $id));

    case 'ver':
        $av = ta_av($id);
        if ($av['estado'] === 5 && $av['uid'] !== $uid) {
            ta_error(404, 'La solicitud no existe.');
        }
        ta_responder(opg_av_v_tarjeta($av, $ctx));

    // ── Listados y mercado ─────────────────────────────────────────────────
    case 'listar':
        $ambito = ta_in('ambito') === 'otras' ? 'otras' : 'mias';
        $filtros = ta_filtros($uid, $ambito);
        $res = opg_av_listar($filtros, max(1, (int) ta_in('pagina', '1')));
        $push = ta_url_listado($filtros, $res['pagina']);
        if (ta_in('panel') === '1') {
            $estadisticas = opg_av_estadisticas($filtros);
            $panel = $ambito === 'otras'
                ? opg_av_v_panel_otras($filtros, $res, $ctx, $estadisticas)
                : opg_av_v_panel_mias($filtros, $res, $ctx, !empty($estadisticas['hay_alguna']), $estadisticas, opg_av_activas_listar($uid));
            ta_responder($panel, 200, array('HX-Push-Url' => $push));
        }
        ta_responder('<div class="ta-lista" id="ta-lista-' . $ambito . '" '
            . opg_av_hx('get', OPG_AV_URL, 'listar', opg_av_params_filtros($filtros) + array('pagina' => $res['pagina']))
            . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-include="#ta-filtros-' . $ambito . '" aria-live="polite">'
            . opg_av_v_lista($res, $filtros, $ctx) . '</div>'
            . opg_av_v_estadisticas(opg_av_estadisticas($filtros), $ambito, true, $filtros), 200,
            array('HX-Reswap' => 'outerHTML', 'HX-Push-Url' => $push));

    case 'sin_narrador':
        $pagina = max(1, (int) ta_in('pagina', '1'));
        $sin = opg_av_sin_narrador_listar($uid, $pagina);
        ta_responder(ta_in('mas') === '1' ? opg_av_v_sin_narrador_mas($sin, $ctx) : opg_av_v_bloque_sin_narrador(opg_av_sin_narrador_listar($uid, 1), $ctx));

    case 'contar_propuestas':
        ta_responder(opg_av_v_contador(opg_av_propuestas_pendientes_contar($uid)));

    case 'narradores_disponibles':
        $pagina = max(1, (int) ta_in('pagina', '1'));
        ta_responder(opg_av_v_bloque_narradores(opg_av_oferta_obtener($uid), opg_av_narradores_disponibles($pagina, $uid), ta_tiene_ficha($uid)));

    case 'notificaciones':
        ta_responder(opg_av_v_novedades_panel(opg_av_notificaciones_listar($uid), opg_av_notificaciones_contar($uid)));

    case 'historial_usuario':
        $historial = ta_exigir(opg_av_historial_usuario($id, $uid));
        ta_responder(opg_av_v_historial_usuario($id, $historial['items']));

    case 'form_oferta':
        if (!ta_tiene_ficha($uid)) {
            ta_error(403, 'Necesitas una ficha aprobada para ofrecerte como narrador.');
        }
        ta_responder(opg_av_v_form_oferta(opg_av_oferta_obtener($uid)));

    case 'ofrecerme_narrador':
        ta_exigir(opg_av_oferta_guardar($uid, ta_in('discord'), ta_in('mensaje'), ta_in('frecuencia'), ta_in_lista('tiers'), ta_in('estilo')), '#aviso-oferta');
        ta_responder(opg_av_v_bloque_narradores(opg_av_oferta_obtener($uid), opg_av_narradores_disponibles(1, $uid), true));

    case 'renovar_oferta':
        ta_exigir(opg_av_oferta_renovar($uid));
        ta_responder(opg_av_v_bloque_narradores(opg_av_oferta_obtener($uid), opg_av_narradores_disponibles(1, $uid), ta_tiene_ficha($uid)));

    case 'retirar_oferta':
        ta_exigir(opg_av_oferta_retirar($uid));
        ta_responder(opg_av_v_bloque_narradores(null, opg_av_narradores_disponibles(1, $uid), ta_tiene_ficha($uid)));

    // ── Typeaheads ──────────────────────────────────────────────────────────
    case 'buscar_fichas':
        if (!ta_tiene_ficha($uid)) {
            ta_responder('');
        }
        $q = ta_in('q');
        $destino = ta_in('destino') === 'narrador' ? 'narrador' : 'participante';
        if (opg_av_longitud($q) < 2) {
            ta_responder($q === '' ? '' : opg_av_v_resultados_fichas(array(), $destino, 'Escribe al menos 2 letras.', $id));
        }
        ta_responder(opg_av_v_resultados_fichas(opg_av_fichas_buscar($q), $destino, 'No hay fichas con ese nombre.', $id));

    case 'buscar_modo_vista':
        $q = ta_in('q');
        if (!ctype_digit($q) && opg_av_longitud($q) < 3) {
            ta_responder('');
        }
        $fichas = ctype_digit($q) ? array_filter(array(opg_av_ficha((int) $q))) : opg_av_fichas_buscar($q);
        $excluir = ctype_digit(ta_in('modo_vista')) ? (int) ta_in('modo_vista') : $uid;
        ta_responder(opg_av_v_resultados_modo_vista($fichas, $excluir));

    case 'elegir_ficha':
        $fid = (int) ta_in('fid');
        $destino = ta_in('destino') === 'narrador' ? 'narrador' : 'participante';
        $ficha = ta_tiene_ficha($uid) ? opg_av_ficha($fid) : null;
        $limpiar = $destino === 'participante' ? opg_av_v_buscador_std($destino, $id, true) : '';
        if (!$ficha) {
            ta_responder($limpiar);
        }
        if ($destino === 'participante') {
            $ya = opg_av_enteros(ta_in_lista('participantes'));
            if ($fid === $uid || in_array($fid, $ya, true)) {
                ta_responder($limpiar);
            }
        }
        $extra = '';
        $cupo = null;
        if ($destino === 'participante') {
            $participantes = array_values(array_unique(array_merge($ya, array($fid))));
            if (ta_in('rol') !== 'narrador') { $participantes[] = $uid; }
            $participantes = array_values(array_unique($participantes));
            $cupos = opg_av_cupos_participantes($participantes, count($participantes), $id);
            $cupo = isset($cupos[$fid]) ? $cupos[$fid] : null;
            $extra = opg_av_v_selector_participante($id, false, true)
                . opg_av_v_estado_cupos($cupos, ta_in('rol') === 'narrador' ? 'narrador' : 'jugador', $id, null, true);
        }
        ta_responder(opg_av_v_chip_ficha($ficha, $destino, false, $id, $cupo) . $extra);

    case 'buscar_islas':
        $q = ta_in('q');
        ta_responder(opg_av_v_resultados_islas(opg_av_islas_buscar($q), $id));

    case 'elegir_isla':
        $isla = opg_av_isla_valida((int) ta_in('fid'));
        ta_responder(opg_av_v_campo_isla($isla, $id));

    case 'mis_activas':
        if (ta_in('detalle') === '1') {
            ta_responder(opg_av_v_mis_activas(opg_av_activas_listar($uid)));
        }
        list($sol, $total) = opg_av_contar_activas($uid, 0);
        ta_responder(opg_av_v_indicador_activas($sol, $total));

    case 'validar_cupos':
        $participantes = opg_av_enteros(ta_in_lista('participantes'));
        $rol = ta_in('rol') === 'narrador' ? 'narrador' : 'jugador';
        if ($rol === 'jugador') { $participantes[] = $uid; }
        $participantes = array_values(array_unique($participantes));
        ta_responder(opg_av_v_estado_cupos(opg_av_cupos_participantes($participantes, count($participantes), $id), $rol, $id));

    // ── Solicitar y editar ──────────────────────────────────────────────────
    case 'crear':
        if (!ta_tiene_ficha($uid)) {
            ta_error(403, 'Necesitas una ficha aprobada para solicitar una aventura.');
        }
        $entrada = array(
            'tipo' => ta_in('tipo', OPG_AV_TIPO_NORMAL),
            'rol' => ta_in('rol'), 'participantes' => ta_in_lista('participantes'), 'tiene_narrador' => ta_in('tiene_narrador'),
            'narrador_fid' => ta_in('narrador_fid'), 'idea' => ta_in('idea'), 'isla_fid' => ta_in('isla_fid'), 'dia' => ta_in('dia'),
            'tier' => ta_in('tier'), 'comentario' => ta_in('comentario'), 'inframundo' => ta_in('inframundo'),
        );
        $v = opg_av_validar_solicitud($entrada, $uid);
        if ($v['errores']) {
            ta_error(422, $v['errores'], '#aviso-form-crear');
        }
        $r = ta_exigir(opg_av_crear($v['datos'], $uid), '#aviso-form-crear');
        ta_responder(opg_av_v_solicitud_creada($r['id'], (int) $v['datos']['estado']), 200, array('HX-Trigger' => 'aventuraCreada'));

    case 'form_editar':
        $av = ta_av($id);
        if (!opg_av_puede_editar($av, $uid)) {
            ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta($av, $ctx), $id, opg_av_v_aviso('err', $av['uid'] === $uid ? 'Esta solicitud ya no se puede editar.' : 'Solo el creador puede editarla.')));
        }
        ta_responder(opg_av_v_form_solicitud(opg_av_form_desde_av($av), 'editar', $id, array(), ta_yo($uid)));

    case 'editar':
        $entrada = array(
            'tipo' => ta_in('tipo', OPG_AV_TIPO_NORMAL),
            'rol' => ta_in('rol'), 'participantes' => ta_in_lista('participantes'), 'tiene_narrador' => ta_in('tiene_narrador'),
            'narrador_fid' => ta_in('narrador_fid'), 'idea' => ta_in('idea'), 'isla_fid' => ta_in('isla_fid'), 'dia' => ta_in('dia'),
            'tier' => ta_in('tier'), 'comentario' => ta_in('comentario'), 'inframundo' => ta_in('inframundo'),
        );
        $r = opg_av_editar($id, $entrada, $uid);
        if (empty($r['ok'])) {
            if ($r['codigo'] === 409) {
                $av = ta_av($id);
                ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta($av, $ctx), $id, opg_av_v_aviso('err', $r['errores'])), 200, array('HX-Retarget' => '#av-' . $id, 'HX-Reswap' => 'outerHTML'));
            }
            ta_error($r['codigo'], $r['errores'], '#aviso-form-' . $id);
        }
        ta_responder(opg_av_v_tarjeta(ta_av($id), $ctx), 200, array('HX-Trigger' => 'aventuraCambiada'));

    case 'pedir_borrado':
        $r = opg_av_pedir_borrado($id, $uid);
        if (empty($r['ok'])) {
            ta_error($r['codigo'], $r['errores'], '#aviso-' . $id);
        }
        ta_responder(ta_oob_aviso('ok', 'Pediste el borrado de la solicitud #' . $id . '. El staff decidirá si la elimina.'), 200, array('HX-Trigger' => 'aventuraCambiada'));

    // ── Postularse y salirse ────────────────────────────────────────────────
    case 'form_postular':
        $av = ta_av($id);
        if (!ta_tiene_ficha($uid) || opg_av_es_parte($av, $uid) || $av['estado'] !== 4 || $av['narrador_uid'] > 0) {
            ta_responder(opg_av_v_modal('No puedes postularte', opg_av_v_aviso('err', 'Esta solicitud ya no está pendiente de narrador, o no puedes narrarla.')
                . '<div class="ta-acciones"><button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML">Cerrar</button></div>'));
        }
        ta_responder(opg_av_v_modal_postular($av));

    case 'postular_narrador':
        $r = opg_av_postular_narrador($id, $uid, true, ta_in('mensaje'));
        if (empty($r['ok'])) {
            ta_error($r['codigo'], $r['errores'], '#aviso-modal');
        }
        ta_responder('<div id="ta-modal"></div>'
            . ta_oob_aviso('ok', 'Candidatura enviada para la solicitud #' . $id . '. El creador debe aceptarla.'), 200,
            array('HX-Trigger' => 'aventuraCambiada'));

    case 'aceptar_propuesta':
    case 'rechazar_propuesta':
    case 'retirar_propuesta':
        $propuesta_id = (int) ta_in('propuesta_id');
        if ($accion === 'aceptar_propuesta') {
            $r = opg_av_propuesta_aceptar($propuesta_id, $uid);
        } else {
            $r = opg_av_propuesta_resolver($propuesta_id, $uid, $accion === 'rechazar_propuesta' ? 'rechazar' : 'retirar');
        }
        ta_exigir($r);
        ta_responder(ta_panel(ta_in('tab') === 'narradores' ? 'narradores' : 'mias', $uid), 200,
            array('HX-Trigger' => 'aventuraCambiada'));

    case 'leer_notificacion':
        ta_exigir(opg_av_notificacion_leer((int) ta_in('notificacion_id'), $uid));
        ta_responder(opg_av_v_novedades_panel(opg_av_notificaciones_listar($uid), opg_av_notificaciones_contar($uid)));

    case 'form_renunciar':
        $av = ta_av($id);
        if ($av['narrador_uid'] !== $uid || !in_array($av['estado'], array(0, 1), true)) {
            ta_responder(opg_av_v_modal('No puedes dejar de narrar', opg_av_v_aviso('err', 'Solo el narrador asignado puede dejar de narrar una aventura creada o aprobada.')
                . '<div class="ta-acciones"><button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML">Cerrar</button></div>'));
        }
        ta_responder(opg_av_v_modal_renunciar($av));

    case 'renunciar_narrador':
        $r = opg_av_renunciar_narrador($id, $uid, ta_in('confirmo') === '1', ta_in('motivo'));
        if (empty($r['ok'])) {
            ta_error($r['codigo'], $r['errores'], '#aviso-modal');
        }
        $av = opg_av_obtener($id);
        $salida = '';
        if ($av && opg_av_es_parte($av, $uid)) {
            $salida .= ta_oob_tarjeta(opg_av_v_tarjeta($av, $ctx));       // sigue siendo suya (era también la creadora)
        } else {
            $salida .= '<div id="av-' . $id . '" hx-swap-oob="delete"></div>';
        }
        ta_responder($salida . ta_oob_aviso('ok', 'Has dejado de narrar la solicitud #' . $id . '. Los participantes tienen un aviso en el tablón.'),
            200, array('HX-Trigger' => 'aventuraCambiada'));

    // ── Vincular y entregar ─────────────────────────────────────────────────
    case 'form_vincular':
    case 'form_entrega':
        $av = ta_av($id);
        $entrega = opg_ent_obtener($id);
        $puede_auto = opg_av_es_autonarrada($av) && $av['uid'] === $uid && $av['estado'] === 1
            && $av['tid_vinculado'] > 0 && empty($av['autonarrada']['entregada_at'])
            && $av['autonarrada']['estado_pago'] === 'pendiente';
        $puede = $accion === 'form_vincular' ? opg_av_puede_vincular($av, $uid)
            : ($puede_auto || (!opg_av_es_autonarrada($av) && $av['narrador_uid'] === $uid
                && $av['estado'] === 1 && (!$entrega || $entrega['estado'] === 'rechazada')));
        if (!$puede) {
            ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta($av, $ctx), $id, opg_av_v_aviso('err', 'Ya no puedes vincular ni entregar esta aventura.')));
        }
        if ($accion === 'form_vincular') {
            $tema = null;
            if ($av['tid_vinculado'] > 0) {
                $v = opg_ent_tema_valido($av['tid_vinculado'], $av, $uid);
                $tema = $v['ok'] ? $v['tema'] : null;
            }
            ta_responder(opg_av_v_form_vincular($av, $tema));
        }
        if (opg_av_es_autonarrada($av)) {
            ta_responder(opg_av_v_form_entrega_autonarrada($av));
        }
        $d = opg_ent_form($id, $uid);
        ta_responder(opg_av_v_form_entrega($d));

    case 'buscar_temas':
        $av = ta_av($id);
        if (!opg_av_puede_vincular($av, $uid)) {
            ta_responder('');
        }
        $q = ta_in('q');
        $tid = opg_av_tid_de_entrada($q);
        if ($tid <= 0) {
            ta_responder(opg_av_v_resultados_temas(array(), $q === '' ? '' : 'Escribe el TID del tema o pega su enlace.', $id));
        }
        // La validación comprueba que sea un tema de tipo Aventura (o Autonarrada), visible, de la isla y libre.
        $v = opg_ent_tema_valido($tid, $av, $uid);
        if (!$v['ok']) {
            ta_responder(opg_av_v_resultados_temas(array(), implode(' ', $v['errores']), $id));
        }
        ta_responder(opg_av_v_resultados_temas(array($v['tema']), '', $id));

    case 'quitar_tema':
        $av = ta_av($id);
        if (!opg_av_puede_vincular($av, $uid)) {
            ta_error(403, 'No puedes cambiar el tema de esta aventura.');
        }
        ta_responder(opg_av_v_slot_tema(null, $id));

    case 'elegir_tema':
        $av = ta_av($id);
        if (!opg_av_puede_vincular($av, $uid)) {
            ta_error(403, 'Solo el narrador asignado o un participante de una aventura aprobada puede elegir el tema.');
        }
        $v = opg_ent_tema_valido((int) ta_in('tid'), $av, $uid);
        ta_responder($v['ok'] ? opg_av_v_slot_tema($v['tema'], $id)
            : opg_av_v_aviso('err', $v['errores']) . opg_av_v_slot_tema(null, $id));

    case 'vincular_aventura':
        $av = ta_av($id);
        $r = opg_av_vincular($id, ta_in('tema'), $uid);
        if (empty($r['ok'])) {
            ta_error($r['codigo'], $r['errores'], '#aviso-form-' . $id);
        }
        ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta(ta_av($id), $ctx), $id, opg_av_v_aviso('ok', 'Aventura vinculada: ' . $r['tema']['nombre'])), 200, array('HX-Trigger' => 'aventuraCambiada'));

    case 'enviar_entrega':
        $av = ta_av($id);
        if (opg_av_es_autonarrada($av)) {
            $r = opg_av_autonarrada_entregar($id, ta_in('reputacion'), $uid);
            if (empty($r['ok'])) {
                if ($r['codigo'] === 409 || $r['codigo'] === 403) {
                    ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta(ta_av($id), $ctx), $id, opg_av_v_aviso('err', $r['errores'])), 200,
                        array('HX-Retarget' => '#av-' . $id, 'HX-Reswap' => 'outerHTML'));
                }
                ta_error($r['codigo'], $r['errores'], '#aviso-form-' . $id);
            }
            ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta(ta_av($id), $ctx), $id,
                opg_av_v_aviso('ok', 'Autonarrada entregada: queda pendiente de revisión del Staff.')), 200,
                array('HX-Trigger' => 'aventuraCambiada'));
        }
        $r = opg_ent_enviar($id, array('tema' => ta_in('tema'), 'resumen' => ta_in('resumen'), 'reputacion' => ta_in('reputacion')), $uid);
        if (empty($r['ok'])) {
            if ($r['codigo'] === 409 || $r['codigo'] === 403) {
                $av = ta_av($id);
                ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta($av, $ctx), $id, opg_av_v_aviso('err', $r['errores'])), 200, array('HX-Retarget' => '#av-' . $id, 'HX-Reswap' => 'outerHTML'));
            }
            ta_error($r['codigo'], $r['errores'], '#aviso-form-' . $id);
        }
        ta_responder(ta_tarjeta_con_aviso(opg_av_v_tarjeta(ta_av($id), $ctx), $id, opg_av_v_aviso('ok', 'Entrega enviada: queda pendiente de revisión del staff.')), 200, array('HX-Trigger' => 'aventuraCambiada'));
}

ta_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
