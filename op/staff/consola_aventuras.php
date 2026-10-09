<?php
/**
 * Staff - Consola de aventuras.
 *
 * Gestión de las solicitudes del tablón de aventuras: aprobar, denegar, finalizar,
 * corregir datos, historial, eliminar (solo staff), entregas pendientes de pago y
 * moderación de las ofertas de narrador. La interfaz usa HTMX; las acciones
 * (?action=...) responden con fragmentos HTML. El pago de las recompensas se hace en
 * aventuras_recompensas.php.
 *
 * Documentos: docs/101_Requirements_Registro.md, docs/201_DesignPlan_Registro.md y
 * docs/301_ImplementationPlan_Registro.md.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'consola_aventuras.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require_once MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras_vistas.php';
require_once MYBB_ROOT . 'inc/plugins/lib/aventuras_vistas_consola.php';

global $mybb, $db, $templates, $headerinclude, $header, $footer;
$uid = (int) $mybb->user['uid'];

$ACCIONES_GET = array('tab', 'listar', 'entregas', 'narradores', 'buscar_personas', 'form_denegar', 'form_editar', 'historial', 'cerrar', 'buscar_islas', 'elegir_isla', 'form_retirar_oferta');
$ACCIONES_POST = array('cambiar_estado', 'actualizar_datos', 'eliminar', 'retirar_oferta', 'pagar_autonarrada');

// ── Utilidades de respuesta ──────────────────────────────────────────────────

function ca_es_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

function ca_responder($html, $estado = 200, $cabeceras = array())
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

function ca_in($clave, $defecto = '')
{
    if (isset($_POST[$clave]) && !is_array($_POST[$clave])) {
        return trim((string) $_POST[$clave]);
    }
    if (isset($_GET[$clave]) && !is_array($_GET[$clave])) {
        return trim((string) $_GET[$clave]);
    }
    return $defecto;
}

function ca_url($tab, $params = array())
{
    $limpios = array('tab' => $tab);
    foreach ($params as $clave => $valor) {
        if ($valor !== '' && $valor !== null && $valor !== false) {
            $limpios[$clave] = $valor;
        }
    }
    return OPG_AV_URL_CONSOLA . '?' . http_build_query($limpios, '', '&');
}

/**
 * Error de una acción. 409 y 422 se insertan en el contenedor $retarget; el resto
 * (403, 404...) los recoge la configuración de HTMX en #ta-avisos.
 */
function ca_error($codigo, $mensajes, $retarget = '')
{
    $cabeceras = array();
    if ($retarget !== '' && in_array((int) $codigo, array(409, 422), true)) {
        $cabeceras['HX-Retarget'] = $retarget;
        $cabeceras['HX-Reswap'] = 'innerHTML';
    }
    ca_responder(opg_av_v_aviso('err', $mensajes), (int) $codigo, $cabeceras);
}

function ca_exigir($r, $retarget = '')
{
    if (empty($r['ok'])) {
        ca_error(isset($r['codigo']) ? $r['codigo'] : 500, isset($r['errores']) ? $r['errores'] : array('No se pudo completar la acción.'), $retarget);
    }
    return $r;
}

// ── Filtros y paneles ────────────────────────────────────────────────────────

/** Filtros validados de la cola de solicitudes. */
function ca_filtros_ui()
{
    $estado = ca_in('estado', 'creado');
    if ($estado !== 'todas' && opg_av_estado_desde_clave($estado) === null) {
        $estado = 'creado';
    }
    $u = ca_in('persona', ca_in('uid'));
    $orden = ca_in('orden', 'recientes');
    return array(
        'estado' => $estado,
        'uid' => ctype_digit($u) && (int) $u > 0 ? (int) $u : '',
        'q' => ca_in('q'),
        'orden' => $orden === 'antiguas' ? 'antiguas' : 'recientes',
    );
}

function ca_listar($filtros_ui, $pagina)
{
    $filtros = $filtros_ui;
    $filtros['estado'] = $filtros_ui['estado'] === 'todas' ? '' : $filtros_ui['estado'];
    return opg_av_listar($filtros, $pagina, true);
}

function ca_ctx($uid)
{
    return array('uid' => $uid, 'puede_eliminar' => opg_av_puede_eliminar($uid));
}

function ca_estado_entregas()
{
    $e = ca_in('estado', 'pendiente');
    return in_array($e, array('pendiente', 'rechazada', 'entregada'), true) ? $e : 'pendiente';
}

function ca_tipo_entregas()
{
    $tipo = ca_in('tipo', 'todas');
    return in_array($tipo, array('todas', 'aventuras', 'autonarradas'), true) ? $tipo : 'todas';
}

function ca_filtros_narradores()
{
    $vigencia = ca_in('vigencia', 'todas');
    return array(
        'vigencia' => in_array($vigencia, array('todas', 'vigentes', 'vencidas'), true) ? $vigencia : 'todas',
        'q' => ca_in('q'),
    );
}

/** Agregados compartidos por cabecera, pestañas y paneles en una respuesta. */
function ca_resumen()
{
    $resumen = opg_av_resumen_consola();
    $resumen['entregas_pendientes'] = (int) $resumen['entregas']['pendiente'];
    return $resumen;
}

function ca_tabs_oob($tab)
{
    $resumen = ca_resumen();
    return opg_av_v_c_tabs($tab, $resumen['entregas_pendientes'], true);
}

function ca_panel($tab, $uid, $resumen = null)
{
    $resumen = $resumen === null ? ca_resumen() : $resumen;
    if ($tab === 'entregas') {
        $estado = ca_estado_entregas();
        $tipo = ca_tipo_entregas();
        $pagina = max(1, (int) ca_in('pagina', '1'));
        return opg_av_v_c_panel_entregas($estado,
            $tipo === 'autonarradas' ? array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1) : opg_ent_listar($estado, $pagina),
            $resumen['entregas'], $tipo,
            $tipo === 'aventuras' ? array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1) : opg_av_autonarradas_entregas_listar($estado, $pagina));
    }
    if ($tab === 'narradores') {
        $filtros = ca_filtros_narradores();
        return opg_av_v_c_panel_narradores(opg_av_ofertas_admin_listar(max(1, (int) ca_in('pagina', '1')), $filtros), $filtros);
    }
    $filtros = ca_filtros_ui();
    return opg_av_v_c_panel_solicitudes($filtros, ca_listar($filtros, max(1, (int) ca_in('pagina', '1'))), $resumen['estados'], ca_ctx($uid));
}

// ── Acceso y página completa ─────────────────────────────────────────────────

$accion = isset($_GET['action']) ? (string) $_GET['action'] : '';
if (isset($_POST['action'])) {
    $accion = (string) $_POST['action'];
}

if (!opg_av_puede_gestionar($uid)) {
    if ($accion !== '') {
        ca_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
    }
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

if ($accion === '') {
    $tab = ca_in('tab');
    if (!in_array($tab, array('solicitudes', 'entregas', 'narradores'), true)) {
        $tab = 'solicitudes';
    }
    $resumen = ca_resumen();
    $panel_html = ca_panel($tab, $uid, $resumen);
    $tabs_html = opg_av_v_c_tabs($tab, $resumen['entregas_pendientes']);
    $guia_html = opg_av_v_c_guia();
    $post_key = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');
    $bbname_html = htmlspecialchars($mybb->settings['bbname'], ENT_QUOTES, 'UTF-8');
    $asset_url = htmlspecialchars(rtrim($mybb->settings['bburl'], '/'), ENT_QUOTES, 'UTF-8');
    $htmx_url = htmlspecialchars(rtrim($mybb->settings['bburl'], '/') . '/jscripts/htmx/htmx.min.js?v=2.0.4', ENT_QUOTES, 'UTF-8');
    eval("\$page = \"" . $templates->get('staff_consola_aventuras') . "\";");
    output_page($page);
    exit;
}

// ── Contrato de las acciones HTMX ────────────────────────────────────────────

$es_post = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!in_array($accion, $es_post ? $ACCIONES_POST : $ACCIONES_GET, true) || !ca_es_htmx()) {
    ca_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
}
if ($es_post) {
    $clave = isset($_SERVER['HTTP_X_POST_KEY']) ? (string) $_SERVER['HTTP_X_POST_KEY'] : '';
    if (!verify_post_check($clave, true)) {
        ca_error(403, 'La sesión caducó. Recarga la página e inténtalo de nuevo.');
    }
}

$id = ctype_digit(ca_in('id')) ? (int) ca_in('id') : 0;
$ctx = ca_ctx($uid);

/** Solicitud (con datos de consola) o error 404. */
function ca_av($id)
{
    $av = $id > 0 ? opg_av_obtener($id, true) : null;
    if (!$av) {
        ca_error(404, 'La solicitud no existe.');
    }
    return $av;
}

switch ($accion) {

    case 'tab':
        $tab = ca_in('tab');
        $tab = in_array($tab, array('solicitudes', 'entregas', 'narradores'), true) ? $tab : 'solicitudes';
        $resumen = ca_resumen();
        ca_responder(ca_panel($tab, $uid, $resumen) . opg_av_v_c_tabs($tab, $resumen['entregas_pendientes'], true));

    case 'cerrar':
        ca_responder('');

    case 'listar':
        $filtros = ca_filtros_ui();
        $pagina = max(1, (int) ca_in('pagina', '1'));
        $url = ca_url('solicitudes', array('estado' => $filtros['estado'], 'persona' => $filtros['uid'], 'q' => $filtros['q'],
            'orden' => $filtros['orden'], 'pagina' => $pagina > 1 ? $pagina : ''));
        ca_responder(opg_av_v_c_lista(ca_listar($filtros, $pagina), $filtros, $ctx), 200, array('HX-Push-Url' => $url));

    case 'entregas':
        $estado = ca_estado_entregas();
        $tipo = ca_tipo_entregas();
        $pagina = max(1, (int) ca_in('pagina', '1'));
        $normales = $tipo === 'autonarradas' ? array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1) : opg_ent_listar($estado, $pagina);
        $autos = $tipo === 'aventuras' ? array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1) : opg_av_autonarradas_entregas_listar($estado, $pagina);
        ca_responder(opg_av_v_c_lista_entregas($normales, $estado, $autos, $tipo), 200,
            array('HX-Push-Url' => ca_url('entregas', array('estado' => $estado, 'tipo' => $tipo, 'pagina' => $pagina > 1 ? $pagina : ''))));

    case 'pagar_autonarrada':
        $peticion_id = (int) ca_in('id');
        $r = opg_av_autonarrada_pagar($peticion_id, $uid);
        ca_exigir($r, '#auto-aviso-' . $peticion_id);
        $estado = ca_estado_entregas();
        $tipo = ca_tipo_entregas();
        $normales = $tipo === 'autonarradas' ? array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1) : opg_ent_listar($estado, 1);
        $autos = $tipo === 'aventuras' ? array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1) : opg_av_autonarradas_entregas_listar($estado, 1);
        ca_responder(opg_av_v_aviso('ok', !empty($r['aviso']) ? $r['aviso'] : 'Recompensa entregada y crónica finalizada.')
            . opg_av_v_c_lista_entregas($normales, $estado, $autos, $tipo) . ca_tabs_oob('entregas'));

    case 'narradores':
        $filtros = ca_filtros_narradores();
        $pagina = max(1, (int) ca_in('pagina', '1'));
        ca_responder(opg_av_v_c_lista_narradores(opg_av_ofertas_admin_listar($pagina, $filtros), $filtros), 200,
            array('HX-Push-Url' => ca_url('narradores', array('vigencia' => $filtros['vigencia'], 'q' => $filtros['q'], 'pagina' => $pagina > 1 ? $pagina : ''))));

    case 'buscar_personas':
        ca_responder(opg_av_v_c_resultados_personas(opg_av_fichas_buscar(ca_in('q'))));

    case 'form_denegar':
        $av = ca_av($id);
        if (!opg_av_puede_resolver($av, $uid)) {
            ca_error(403, 'Sin permisos.');
        }
        ca_responder(opg_av_v_c_form_denegar($id));

    case 'cambiar_estado':
        $av = ca_av($id);
        $a = (int) ca_in('a');
        if ($a === 5 || !in_array($a, array(1, 2, 3), true)) {
            ca_error(422, 'Estado no válido.');
        }
        if (!opg_av_puede_resolver($av, $uid)) {
            ca_error(403, 'Sin permisos.');
        }
        ca_exigir(opg_av_cambiar_estado($id, $a, $uid, ca_in('motivo')), $a === 2 ? '#aviso-form-' . $id : '#aviso-' . $id);
        ca_responder(opg_av_v_c_fila(ca_av($id), $ctx) . ca_tabs_oob('solicitudes'));

    case 'form_editar':
        ca_responder(opg_av_v_c_form_editar(ca_av($id)));

    case 'actualizar_datos':
        ca_av($id);
        $campos = array(
            'nota_staff' => ca_in('nota_staff'),
            'comentario' => ca_in('comentario'),
            'inframundo' => ca_in('inframundo', '0'),
            'dia' => ca_in('dia'),
            'tier' => ca_in('tier'),
        );
        if (ca_in('isla_fid') !== '') {
            $campos['isla_fid'] = ca_in('isla_fid');
        }
        if ($campos['dia'] === '') {
            unset($campos['dia']);
        }
        if ($campos['tier'] === '') {
            unset($campos['tier']);
        }
        ca_exigir(opg_av_actualizar_datos($id, $campos, $uid), '#aviso-form-' . $id);
        ca_responder(opg_av_v_c_fila(ca_av($id), $ctx));

    case 'buscar_islas':
        ca_responder(opg_av_v_c_resultados_islas(opg_av_islas_buscar(ca_in('q')), $id));

    case 'elegir_isla':
        $isla = opg_av_isla_valida((int) ca_in('fid'));
        ca_responder($isla ? opg_av_v_c_slot_isla($isla, $id) : '');

    case 'historial':
        ca_av($id);
        ca_responder(opg_av_v_c_historial(opg_av_historial($id), $id));

    case 'eliminar':
        ca_av($id);
        ca_exigir(opg_av_eliminar($id, $uid), '#aviso-' . $id);
        ca_responder('');

    case 'form_retirar_oferta':
        ca_responder(opg_av_v_c_form_retirar_oferta((int) ca_in('uid')));

    case 'retirar_oferta':
        $uid_oferta = (int) ca_in('uid');
        ca_exigir(opg_av_oferta_retirar_admin($uid_oferta, $uid, ca_in('motivo')), '#aviso-form-oferta-' . $uid_oferta);
        ca_responder('');
}

ca_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
