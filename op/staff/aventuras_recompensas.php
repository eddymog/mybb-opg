<?php
/**
 * Staff - Recompensas de una aventura entregada.
 *
 * Revisión y pago de la entrega que un narrador envía desde el tablón de aventuras
 * (?id=ENTREGA, o ?tid=TEMA). Sigue la lógica de op/staff/recompensasAventuras.php
 * (mismos cálculos, tope mensual del narrador de 675), con un pago único por
 * destinatario y cierre del tema. La interfaz usa HTMX; las acciones (?action=...)
 * responden con fragmentos HTML.
 *
 * Documentos: docs/101_Requirements_Registro.md, docs/201_DesignPlan_Registro.md y
 * docs/301_ImplementationPlan_Registro.md.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'aventuras_recompensas.php');
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

$ACCIONES_GET = array('form_rechazar', 'cerrar');
$ACCIONES_POST = array('analizar', 'pagar', 'rechazar', 'completar', 'xp_dudosa');

// ── Utilidades de respuesta ──────────────────────────────────────────────────

function ar_es_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

function ar_responder($html, $estado = 200, $cabeceras = array())
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

function ar_in($clave, $defecto = '')
{
    if (isset($_POST[$clave]) && !is_array($_POST[$clave])) {
        return trim((string) $_POST[$clave]);
    }
    if (isset($_GET[$clave]) && !is_array($_GET[$clave])) {
        return trim((string) $_GET[$clave]);
    }
    return $defecto;
}

/** 409 y 422 se insertan en el contenedor $retarget; el resto lo recoge #ta-avisos. */
function ar_error($codigo, $mensajes, $retarget = '#ar-aviso')
{
    $cabeceras = array();
    if ($retarget !== '' && in_array((int) $codigo, array(409, 422), true)) {
        $cabeceras['HX-Retarget'] = $retarget;
        $cabeceras['HX-Reswap'] = 'innerHTML';
    }
    ar_responder(opg_av_v_aviso('err', $mensajes), (int) $codigo, $cabeceras);
}

function ar_exigir($r, $retarget = '#ar-aviso')
{
    if (empty($r['ok'])) {
        ar_error(isset($r['codigo']) ? $r['codigo'] : 500, isset($r['errores']) ? $r['errores'] : array('No se pudo completar la acción.'), $retarget);
    }
    return $r;
}

// ── Datos de la petición ─────────────────────────────────────────────────────

/** Ajustes enviados por el formulario (sin validar: lo hace opg_rec_normalizar_ajustes). */
function ar_ajustes()
{
    $a = array();
    foreach (array('tier', 'extra_jugadores', 'n_participantes', 'confirma_minimos') as $k) {
        if (isset($_POST[$k]) && !is_array($_POST[$k])) {
            $a[$k] = trim((string) $_POST[$k]);
        }
    }
    if (isset($_POST['d']) && is_array($_POST['d'])) {
        $a['destinatarios'] = array();
        foreach ($_POST['d'] as $u => $d) {
            if (!is_array($d)) {
                continue;
            }
            $fila = array();
            foreach (array('incluir', 'variacion', 'rep', 'rol') as $k) {
                if (isset($d[$k]) && !is_array($d[$k])) {
                    $fila[$k] = trim((string) $d[$k]);
                }
            }
            $a['destinatarios'][(int) $u] = $fila;
        }
    }
    return $a;
}

/** Entrega pedida por ?id o ?tid (la vigente del tema, o la última si no hay). */
function ar_entrega_pedida()
{
    $id = ar_in('id');
    if (ctype_digit($id) && (int) $id > 0) {
        return opg_ent_obtener_por_id((int) $id);
    }
    $tid = ar_in('tid');
    if (ctype_digit($tid) && (int) $tid > 0) {
        $vigente = opg_ent_obtener_por_tid((int) $tid);
        if ($vigente) {
            return $vigente;
        }
        $f = opg_av_uno('SELECT * FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE tid = ' . (int) $tid . ' ORDER BY id DESC LIMIT 1');
        return $f ? opg_ent_normalizar($f) : null;
    }
    return null;
}

/** Panel de una entrega (analiza si está pendiente). $ajustes: los del formulario. */
function ar_panel($entrega_id, $ajustes, $aviso_html = '')
{
    $e = opg_ent_obtener_por_id($entrega_id);
    if (!$e) {
        return opg_av_v_vacio('La entrega no existe.');
    }
    $av = opg_av_obtener($e['peticion_id'], true);
    $a = $e['estado'] === 'pendiente' ? opg_ent_analizar($e['id'], $ajustes) : null;
    return opg_av_v_r_panel($e, $av, $a, $aviso_html);
}

// ── Acceso y página completa ─────────────────────────────────────────────────

$accion = isset($_GET['action']) ? (string) $_GET['action'] : '';
if (isset($_POST['action'])) {
    $accion = (string) $_POST['action'];
}

if (!opg_av_puede_gestionar($uid)) {
    if ($accion !== '') {
        ar_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
    }
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

if ($accion === '') {
    $e = ar_entrega_pedida();
    if ($e) {
        $panel_html = ar_panel($e['id'], array());
    } else {
        $panel_html = '<p class="ta-enlace"><a href="' . opg_av_h(OPG_AV_URL_CONSOLA . '?tab=entregas') . '">&larr; Volver a las entregas</a></p>'
            . opg_av_v_vacio(ar_in('id') !== '' || ar_in('tid') !== '' ? 'No hay ninguna entrega con ese número o ese tema.' : 'Elige una entrega desde la consola de aventuras.');
    }
    $post_key = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');
    $bbname_html = htmlspecialchars($mybb->settings['bbname'], ENT_QUOTES, 'UTF-8');
    $asset_url = htmlspecialchars(rtrim($mybb->settings['bburl'], '/'), ENT_QUOTES, 'UTF-8');
    $htmx_url = htmlspecialchars(rtrim($mybb->settings['bburl'], '/') . '/jscripts/htmx/htmx.min.js?v=2.0.4', ENT_QUOTES, 'UTF-8');
    eval("\$page = \"" . $templates->get('staff_aventuras_recompensas') . "\";");
    output_page($page);
    exit;
}

// ── Contrato de las acciones HTMX ────────────────────────────────────────────

$es_post = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!in_array($accion, $es_post ? $ACCIONES_POST : $ACCIONES_GET, true) || !ar_es_htmx()) {
    ar_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
}
if ($es_post) {
    $clave = isset($_SERVER['HTTP_X_POST_KEY']) ? (string) $_SERVER['HTTP_X_POST_KEY'] : '';
    if (!verify_post_check($clave, true)) {
        ar_error(403, 'La sesión caducó. Recarga la página e inténtalo de nuevo.');
    }
}

if ($accion === 'cerrar') {
    ar_responder('');
}

$id = ctype_digit(ar_in('id')) ? (int) ar_in('id') : 0;
if (!$id || !opg_ent_obtener_por_id($id)) {
    ar_error(404, 'La entrega no existe.');
}

switch ($accion) {

    case 'form_rechazar':
        ar_responder(opg_av_v_r_form_rechazar($id));

    case 'analizar':
        $a = opg_ent_analizar($id, ar_ajustes());
        if (empty($a['ok']) && in_array((int) $a['codigo'], array(404, 409), true)) {
            ar_error($a['codigo'], $a['errores']);
        }
        $e = opg_ent_obtener_por_id($id);
        ar_responder(opg_av_v_r_panel($e, opg_av_obtener($e['peticion_id'], true), $a));

    case 'pagar':
    case 'completar':
        $r = $accion === 'pagar' ? opg_ent_pagar($id, ar_ajustes(), $uid) : opg_ent_completar($id, $uid);
        if (empty($r['ok']) && !empty($r['parcial'])) {
            // Se pagó a parte de los destinatarios: se muestra el estado real y el motivo de la pausa.
            ar_responder(ar_panel($id, array(), opg_av_v_aviso('err', array_merge(
                array('El pago quedó a medias (pagados ahora: ' . (int) $r['pagados'] . '). Nadie cobrará dos veces.'),
                $r['errores'],
                array('Corrige el problema y pulsa "Completar entrega".')
            ))));
        }
        ar_exigir($r);
        ar_responder(ar_panel($id, array(), opg_av_v_aviso('ok', 'Recompensas entregadas y tema cerrado.')));

    case 'rechazar':
        ar_exigir(opg_ent_rechazar($id, ar_in('motivo'), $uid), '#ar-aviso-rechazo');
        ar_responder(ar_panel($id, array(), opg_av_v_aviso('ok', 'Entrega rechazada. El narrador puede corregirla y enviarla otra vez.')));

    case 'xp_dudosa':
        ar_exigir(opg_ent_xp_dudosa($id, (int) ar_in('uid'), ar_in('decision'), $uid));
        ar_responder(ar_panel($id, array(), opg_av_v_aviso('ok', 'Experiencia resuelta.')));
}

ar_responder(opg_av_v_aviso('err', 'No encontrado.'), 404);
