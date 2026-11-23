<?php
/**
 * Notificaciones: la pagina de lectura del sistema global de notificaciones (issue #7).
 *
 * Es lo unico que carga las notificaciones (requisito 9). Sigue las convenciones HTMX del Tablon de aventuras
 * (op/tablon_de_aventuras.php): acciones por ?action=, respuestas en fragmentos y el `uid` siempre de la sesion. El modo
 * espejo y el cambio de personaje no tienen tratamiento especial: ya dejan en $mybb->user la cuenta activa (requisitos
 * 51 y 52).
 *
 *   (sin accion)  GET   pagina completa: filtros, importantes sin leer, pendientes e historial
 *   listar        GET   fragmento de #nt-contenido con los filtros de categoria y origen (no toca la campana)
 *   mas           GET   las 30 siguientes de un bloque, por cursor `antes=FECHA_ID`
 *
 * Abrir la pagina solo lee: no marca nada ni toca el contador (requisito 15). Las acciones Visto y Marcar todas (POST,
 * con clave CSRF) son de la issue #8.
 *
 * Documentos: docs/108_Requirements_Notificaciones.md (5.2 y 10) y docs/208_DesignPlan_Notificaciones.md (apartado 8).
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'notificaciones.php');
require_once "./../global.php";
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_vistas.php';

global $mybb, $templates, $headerinclude, $header, $footer;
$uid = (int) $mybb->user['uid'];
$raiz = rtrim($mybb->settings['bburl'], '/');

$ACCIONES_GET = array('listar', 'mas');

// ── Utilidades de respuesta ──────────────────────────────────────────────────

function nt_es_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

/** Envia un fragmento y termina. */
function nt_responder($html, $estado = 200, $cabeceras = array())
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

/** Parametro GET como texto ('' si falta o es una lista). */
function nt_in($clave)
{
    return isset($_GET[$clave]) && is_string($_GET[$clave]) ? trim($_GET[$clave]) : '';
}

/** Contexto de las vistas: raiz del foro, hora de referencia y destinos borrados de las filas que se van a mostrar. */
function nt_ctx($filas)
{
    global $raiz;
    return array('raiz' => $raiz, 'ahora' => opg_notif_ahora(), 'borrados' => opg_notif_destinos_borrados($filas));
}

/**
 * Lee los tres bloques del usuario con los filtros dados. Devuelve array(resultados, filas de todos) o null si alguna
 * lectura fallo (el error ya quedo registrado por la libreria).
 */
function nt_leer_bloques($uid, $filtros)
{
    $resultados = array();
    $filas = array();
    foreach (array_keys(opg_notif_v_bloques()) as $bloque) {
        $res = opg_notif_listar($uid, opg_notif_v_filtros_bloque($bloque, $filtros), null, OPG_NOTIF_LIMITE);
        if ($res === false) {
            return null;
        }
        $resultados[$bloque] = $res;
        $filas = array_merge($filas, $res['filas']);
    }
    return array($resultados, $filas);
}

/** HTML de #nt-contenido, o null si la lectura fallo. */
function nt_contenido($uid, $filtros)
{
    $leido = nt_leer_bloques($uid, $filtros);
    if ($leido === null) {
        return null;
    }
    return opg_notif_v_contenido($filtros, $leido[0], nt_ctx($leido[1]));
}

/** Plantilla de la pagina; usa la copia de referencia si la base aun no la tiene (igual que el Tablon). */
function nt_plantilla_pagina()
{
    global $templates;
    $plantilla = $templates->get('op_notificaciones');
    if (trim(preg_replace('/<!--\s*(?:start|end):\s*op_notificaciones\s*-->/i', '', $plantilla)) !== '') {
        return $plantilla;
    }
    $ruta = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_notificaciones.html';
    $plantilla = is_readable($ruta) ? file_get_contents($ruta) : false;
    if ($plantilla === false || trim($plantilla) === '') {
        throw new RuntimeException('No se encontró la plantilla op_notificaciones.');
    }
    return str_replace("\\'", "'", addslashes($plantilla));
}

$accion = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';

// ── Pagina completa ──────────────────────────────────────────────────────────

if ($accion === '') {
    // Invitado: al inicio de sesion, sin consultar nada.
    if ($uid <= 0) {
        header('Location: ' . $raiz . '/member.php?action=login');
        exit;
    }
    try {
        $filtros = opg_notif_v_filtros_peticion($_GET);
        $contenido_html = nt_contenido($uid, $filtros);
        if ($contenido_html === null) {
            $contenido_html = opg_notif_v_barra_filtros($filtros) . opg_notif_v_aviso('No se pudieron cargar las notificaciones. Inténtalo de nuevo en un momento.');
        }
        $post_key = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');
        $bbname_html = htmlspecialchars($mybb->settings['bbname'], ENT_QUOTES, 'UTF-8');
        $asset_url = htmlspecialchars($raiz, ENT_QUOTES, 'UTF-8');
        $htmx_url = htmlspecialchars($raiz . '/jscripts/htmx/htmx.min.js?v=2.0.4', ENT_QUOTES, 'UTF-8');
        eval("\$page = \"" . nt_plantilla_pagina() . "\";");
        output_page($page);
    } catch (Throwable $e) {
        error_log('[notificaciones] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        output_page('<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Notificaciones</title>' . $headerinclude
            . '</head><body>' . $header . '<main style="max-width:900px;margin:40px auto;padding:20px"><div class="error"><p>'
            . 'Las notificaciones no pudieron cargarse. El error quedó registrado para su revisión.</p></div></main>' . $footer . '</body></html>');
    }
    exit;
}

// ── Acciones HTMX (solo lectura) ─────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !in_array($accion, $ACCIONES_GET, true) || !nt_es_htmx()) {
    nt_responder(opg_notif_v_aviso('No encontrado.'), 404);
}
if ($uid <= 0) {
    nt_responder('', 200, array('HX-Redirect' => $raiz . '/member.php?action=login'));
}

$filtros = opg_notif_v_filtros_peticion($_GET);

switch ($accion) {
    case 'listar':
        $html = nt_contenido($uid, $filtros);
        if ($html === null) {
            nt_responder(opg_notif_v_aviso('No se pudieron cargar las notificaciones. Inténtalo de nuevo en un momento.'), 500);
        }
        nt_responder($html);

    case 'mas':
        $bloque = nt_in('bloque');
        $filtros_bloque = opg_notif_v_filtros_bloque($bloque, $filtros);
        $antes = nt_in('antes');
        if ($filtros_bloque === null || opg_notif_cursor($antes) === null || opg_notif_cursor($antes) === false) {
            nt_responder(opg_notif_v_aviso('No encontrado.'), 404);
        }
        $res = opg_notif_listar($uid, $filtros_bloque, $antes, OPG_NOTIF_LIMITE);
        if ($res === false) {
            nt_responder(opg_notif_v_aviso('No se pudieron cargar más notificaciones. Inténtalo de nuevo en un momento.'), 500);
        }
        nt_responder(opg_notif_v_filas($bloque, $res, $filtros, nt_ctx($res['filas'])));
}

nt_responder(opg_notif_v_aviso('No encontrado.'), 404);
