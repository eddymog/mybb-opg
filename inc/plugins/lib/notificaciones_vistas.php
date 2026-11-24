<?php
/**
 * Sistema global de notificaciones: fragmentos HTML de la pagina /op/notificaciones.php (issue #7).
 *
 * Documentos: docs/108_Requirements_Notificaciones.md (apartado 5.2) y docs/208_DesignPlan_Notificaciones.md (apartado 8).
 * Las acciones Visto y Marcar todas (issue #8) responden con estos mismos fragmentos mas la campana fuera de banda.
 * Requiere lib/notificaciones.php (lectura) y lib/notificaciones_catalogo.php (textos, iconos y enlaces).
 *
 * Organizacion del archivo:
 *   1. Ayudantes puros: escape, fechas relativas, filtros de la peticion, enlaces y bloques de la lista.
 *   2. Vistas puras: reciben filas ya leidas (opg_notif_fila_lista) y devuelven HTML.
 *   3. Comprobacion de destinos (con base de datos): que enlaces apuntan a algo que ya no existe (requisito 16).
 *
 * Reglas (las del Tablon, lib/aventuras_vistas.php):
 *  - Todo texto sale escapado. El texto del catalogo ya llega escapado de opg_notif_texto(); el resto pasa por opg_notif_v_h().
 *  - Los atributos hx-* se arman con valores de listas fijas y enteros, nunca con texto del usuario.
 *  - Sin hx-on ni expresiones (allowEval desactivado): todo son peticiones al servidor y CSS.
 *  - Nada de esto marca notificaciones como leidas: solo los botones Visto y Marcar todas, que hacen POST con la clave
 *    CSRF de la cabecera X-Post-Key (requisitos 15 y 48). Los enlaces de las filas son enlaces normales: seguirlos no
 *    marca nada.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Ruta de la pagina, relativa a /op/ (las acciones HTMX van a la misma pagina). */
const OPG_NOTIF_V_URL = 'notificaciones.php';

// ── 1. Ayudantes puros ──────────────────────────────────────────────────────────────────────────────────────────────

/** Escapa texto para HTML (contenido y atributos). */
function opg_notif_v_h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Etiquetas de las categorias del filtro, en el orden en que se muestran ('' = Todas). */
function opg_notif_v_categorias()
{
    return array(
        '' => 'Todas',
        'aventuras' => 'Aventuras',
        'temas' => 'Temas y rol',
        'ficha' => 'Ficha',
        'recompensas' => 'Recompensas',
        'tecnicas' => 'Técnicas y objetos',
        'peticiones' => 'Peticiones',
    );
}

/** Etiquetas del filtro de origen (requisito 12). 'sistema' no tiene filtro propio: sale en Todas. */
function opg_notif_v_origenes()
{
    return array('' => 'Todas', 'gestion' => 'De gestión', 'usuario' => 'De usuarios');
}

/**
 * Filtros de la peticion (categoria y origen), validados contra las listas fijas. Un valor desconocido se toma como
 * "Todas": la pagina nunca falla por un enlace viejo o manipulado, y nada que no este en la lista llega a SQL.
 */
function opg_notif_v_filtros_peticion($entrada)
{
    $entrada = is_array($entrada) ? $entrada : array();
    $filtros = array('categoria' => '', 'origen' => '');
    foreach (array('categoria' => opg_notif_v_categorias(), 'origen' => opg_notif_v_origenes()) as $clave => $lista) {
        $valor = isset($entrada[$clave]) && is_string($entrada[$clave]) ? $entrada[$clave] : '';
        $filtros[$clave] = $valor !== '' && isset($lista[$valor]) ? $valor : '';
    }
    return $filtros;
}

/** Parametros de consulta de unos filtros (solo los activos). */
function opg_notif_v_params($filtros, $extra = array())
{
    $params = array();
    foreach (array('categoria', 'origen') as $clave) {
        if (isset($filtros[$clave]) && $filtros[$clave] !== '') {
            $params[$clave] = $filtros[$clave];
        }
    }
    return $params + $extra;
}

/** URL de la pagina o de una accion, sin escapar para HTML. */
function opg_notif_v_url($params = array())
{
    return OPG_NOTIF_V_URL . ($params ? '?' . http_build_query($params, '', '&') : '');
}

/**
 * Bloques de la lista, en orden (requisito 10): importantes sin leer arriba, luego el resto de pendientes y despues el
 * historial. Cada bloque es una consulta de opg_notif_listar con su propio cursor; no se solapan.
 */
function opg_notif_v_bloques()
{
    return array(
        'importantes' => array('titulo' => 'Importantes sin leer', 'icono' => 'fa-triangle-exclamation',
            'filtros' => array('estado' => 'sin_leer', 'importancia' => 'importante')),
        'pendientes' => array('titulo' => 'Pendientes', 'icono' => 'fa-bell',
            'filtros' => array('estado' => 'sin_leer', 'importancia' => 'normal')),
        'historial' => array('titulo' => 'Historial reciente', 'icono' => 'fa-clock-rotate-left',
            'filtros' => array('estado' => 'leidas')),
    );
}

/** Filtros completos de un bloque para opg_notif_listar, o null si el bloque no existe. */
function opg_notif_v_filtros_bloque($bloque, $filtros)
{
    $bloques = opg_notif_v_bloques();
    if (!is_string($bloque) || !isset($bloques[$bloque])) {
        return null;
    }
    return opg_notif_v_params($filtros) + $bloques[$bloque]['filtros'];
}

/** Fecha y hora completas (my_date de MyBB si existe). */
function opg_notif_v_fecha($ts)
{
    $ts = (int) $ts;
    return function_exists('my_date') ? my_date('d/m/Y H:i', $ts) : date('d/m/Y H:i', $ts);
}

/** Fecha relativa: "ahora mismo", "hace 5 minutos", "hace 3 horas", "ayer", "hace 4 días" o la fecha (desde 7 días). */
function opg_notif_v_fecha_relativa($ts, $ahora)
{
    $ts = (int) $ts;
    $seg = max(0, (int) $ahora - $ts);
    if ($seg < 60) {
        return 'ahora mismo';
    }
    if ($seg < 3600) {
        $n = (int) floor($seg / 60);
        return 'hace ' . $n . ($n === 1 ? ' minuto' : ' minutos');
    }
    if ($seg < 86400) {
        $n = (int) floor($seg / 3600);
        return 'hace ' . $n . ($n === 1 ? ' hora' : ' horas');
    }
    $dias = (int) floor($seg / 86400);
    if ($dias === 1) {
        return 'ayer';
    }
    if ($dias < 7) {
        return 'hace ' . $dias . ' días';
    }
    return function_exists('my_date') ? my_date('d/m/Y', $ts) : date('d/m/Y', $ts);
}

/** Enlace de una fila: el guardado si es una ruta interna (prevalece, 208 apartado 6) o el del catalogo; '' si no hay. */
function opg_notif_v_url_fila($f)
{
    if (isset($f['url']) && is_string($f['url']) && $f['url'] !== '' && opg_notif_url_interna($f['url'])) {
        return $f['url'];
    }
    return opg_notif_url(isset($f['tipo']) ? $f['tipo'] : '', isset($f['datos']) ? $f['datos'] : array());
}

/** Href para la pagina: las rutas se guardan relativas a la raiz del foro y la pagina vive en /op/. */
function opg_notif_v_href($url, $raiz)
{
    return strpos($url, '/') === 0 ? $url : rtrim((string) $raiz, '/') . '/' . $url;
}

/**
 * Objeto al que apunta un enlace, para saber si sigue existiendo: array('tema', tid), array('aventura', id) o null si el
 * enlace no depende de nada borrable (la Bitacora, la pestana de ofertas...).
 */
function opg_notif_v_destino($url)
{
    $url = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('@^/?showthread\.php\?(?:[^#]*&)?tid=([0-9]{1,10})(?:[&#]|$)@', $url, $m)) {
        return array('tema', (int) $m[1]);
    }
    if (preg_match('@^/?op/tablon_de_aventuras\.php\?(?:[^#]*&)?id=([0-9]{1,10})(?:[&#]|$)@', $url, $m)) {
        return array('aventura', (int) $m[1]);
    }
    return null;
}

/** Clase de Font Awesome segura para un atributo (las del catalogo lo son; cualquier otra cae en la generica). */
function opg_notif_v_icono($tipo)
{
    $icono = opg_notif_icono($tipo);
    return preg_match('/^fa-[a-z0-9-]{1,40}$/D', $icono) ? $icono : OPG_NOTIF_ICONO_GENERICO;
}

/** Nombre de quien causo el aviso, si aplica: hay actor y su nombre quedo guardado en los datos (sin uniones). */
function opg_notif_v_actor($f)
{
    if ((int) $f['actor_uid'] <= 0) {
        return '';
    }
    return opg_notif_dato(isset($f['datos']) ? $f['datos'] : array(), 'actor');
}

// ── 2. Vistas ───────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Una notificacion (requisito 11): icono, texto, actor si aplica, fecha relativa y enlace.
 * $ctx: 'raiz' (bburl), 'ahora' (hora de referencia) y 'borrados' (destinos que ya no existen, de opg_notif_destinos_borrados).
 * Si el enlace apunta a algo borrado, el texto sale sin enlace (requisito 16).
 */
function opg_notif_v_fila($f, $ctx)
{
    $leida = !empty($f['leida']);
    $importante = $f['importancia'] === 'importante';
    $url = opg_notif_v_url_fila($f);
    $destino = $url !== '' ? opg_notif_v_destino($url) : null;
    if ($destino && !empty($ctx['borrados'][$destino[0] . ':' . $destino[1]])) {
        $url = '';
    }
    $texto = opg_notif_texto($f['tipo'], $f['datos'], $f['cantidad']);
    $clases = 'nt-fila ' . ($leida ? 'nt-fila--leida' : 'nt-fila--sin-leer') . ($importante ? ' nt-fila--importante' : '');

    $html = '<li class="' . $clases . '" id="nt-n-' . (int) $f['id'] . '">'
        . '<span class="nt-fila-icono" aria-hidden="true"><i class="fa-solid ' . opg_notif_v_icono($f['tipo']) . '"></i></span>'
        . '<div class="nt-fila-cuerpo">'
        . '<p class="nt-fila-texto">' . ($leida ? '' : '<span class="nt-solo-lector">Sin leer: </span>')
        . ($url !== '' ? '<a href="' . opg_notif_v_h(opg_notif_v_href($url, isset($ctx['raiz']) ? $ctx['raiz'] : '')) . '">' . $texto . '</a>' : $texto)
        . '</p><p class="nt-fila-meta">';
    $actor = opg_notif_v_actor($f);
    if ($actor !== '') {
        $html .= '<span class="nt-fila-actor"><i class="fa-solid fa-user" aria-hidden="true"></i> ' . opg_notif_v_h($actor) . '</span>';
    }
    $ahora = isset($ctx['ahora']) ? (int) $ctx['ahora'] : time();
    $html .= '<time datetime="' . opg_notif_v_h(gmdate('Y-m-d\TH:i:s\Z', (int) $f['created_at'])) . '" title="'
        . opg_notif_v_h(opg_notif_v_fecha($f['created_at'])) . '">' . opg_notif_v_h(opg_notif_v_fecha_relativa($f['created_at'], $ahora)) . '</time>';
    if ($importante && !$leida) {
        $html .= '<span class="nt-etiqueta nt-etiqueta--importante">Importante</span>';
    }
    $html .= '</p></div>';
    if (!$leida) {
        $html .= opg_notif_v_boton_visto((int) $f['id']);
    }
    return $html . '</li>';
}

/**
 * Boton Visto de una fila sin leer (requisito 10): reemplaza la fila por la version leida que devuelve la accion `visto`.
 * Una fila leida no tiene boton ni hay accion para volver a no leida (requisito 15).
 */
function opg_notif_v_boton_visto($id)
{
    $id = (int) $id;
    $url = opg_notif_v_url(array('action' => 'visto', 'id' => $id));
    return '<button type="button" class="opg-chip nt-visto" hx-post="' . opg_notif_v_h($url) . '" hx-target="#nt-n-' . $id . '"'
        . ' hx-swap="outerHTML" hx-disabled-elt="this"><i class="fa-solid fa-check" aria-hidden="true"></i> Visto</button>';
}

/**
 * Boton Marcar todas como vistas: marca las no leidas del filtro activo (o todas sin filtro) y recarga #nt-contenido.
 * Pide confirmacion porque no se puede deshacer.
 */
function opg_notif_v_boton_todas($filtros)
{
    $filtrado = $filtros['categoria'] !== '' || $filtros['origen'] !== '';
    $url = opg_notif_v_url(opg_notif_v_params($filtros, array('action' => 'todas')));
    $pregunta = $filtrado ? '¿Marcar como vistas todas las notificaciones de este filtro?' : '¿Marcar como vistas todas tus notificaciones?';
    return '<div class="nt-acciones"><button type="button" class="btn-op btn-op--sm btn-op--primario nt-todas" hx-post="' . opg_notif_v_h($url) . '"'
        . ' hx-target="#nt-contenido" hx-swap="innerHTML" hx-disabled-elt="this" hx-confirm="' . opg_notif_v_h($pregunta) . '">'
        . '<i class="fa-solid fa-check-double" aria-hidden="true"></i> Marcar todas como vistas'
        . ($filtrado ? '<span class="nt-todas-filtro"> (solo este filtro)</span>' : '') . '</button></div>';
}

/** Elemento "Cargar más" de un bloque (ultimo <li> de su lista): pide las 30 siguientes por cursor (requisito 13). */
function opg_notif_v_mas($bloque, $cursor, $filtros)
{
    $url = opg_notif_v_url(opg_notif_v_params($filtros, array('action' => 'mas', 'bloque' => $bloque, 'antes' => $cursor)));
    return '<li class="nt-mas"><button type="button" class="opg-chip nt-mas-boton" hx-get="' . opg_notif_v_h($url) . '"'
        . ' hx-target="closest li" hx-swap="outerHTML"><i class="fa-solid fa-angles-down" aria-hidden="true"></i> Cargar más</button></li>';
}

/** Filas de una pagina de un bloque, mas el "Cargar más" si hay otra. Es tambien la respuesta de la accion `mas`. */
function opg_notif_v_filas($bloque, $res, $filtros, $ctx)
{
    $html = '';
    foreach ($res['filas'] as $f) {
        $html .= opg_notif_v_fila($f, $ctx);
    }
    if (!empty($res['hay_mas']) && $res['cursor'] !== null) {
        $html .= opg_notif_v_mas($bloque, $res['cursor'], $filtros);
    }
    return $html;
}

/** Un bloque con su titulo y su lista; vacio si no tiene filas. */
function opg_notif_v_bloque($bloque, $res, $filtros, $ctx)
{
    if (empty($res['filas'])) {
        return '';
    }
    $def = opg_notif_v_bloques();
    $def = $def[$bloque];
    return '<section class="nt-bloque nt-bloque--' . $bloque . '" aria-labelledby="nt-t-' . $bloque . '">'
        . '<h2 class="nt-bloque-titulo" id="nt-t-' . $bloque . '"><i class="fa-solid ' . $def['icono'] . '" aria-hidden="true"></i> '
        . opg_notif_v_h($def['titulo']) . '</h2>'
        . '<ul class="nt-lista" id="nt-lista-' . $bloque . '">' . opg_notif_v_filas($bloque, $res, $filtros, $ctx) . '</ul></section>';
}

/**
 * Barra de filtros (requisito 12): categoria y origen. Cada opcion es un enlace normal (funciona sin JavaScript) que con
 * HTMX solo reemplaza #nt-contenido; la campana del header no se vuelve a pedir.
 */
function opg_notif_v_barra_filtros($filtros)
{
    $grupos = array('categoria' => array('Categoría', opg_notif_v_categorias()), 'origen' => array('Origen', opg_notif_v_origenes()));
    $html = '<nav class="nt-filtros" aria-label="Filtrar notificaciones">';
    foreach ($grupos as $clave => $grupo) {
        $html .= '<div class="nt-filtro-grupo" role="group" aria-labelledby="nt-f-' . $clave . '"><span class="nt-filtro-rotulo" id="nt-f-' . $clave . '">'
            . $grupo[0] . '</span><div class="nt-filtro-opciones">';
        foreach ($grupo[1] as $valor => $etiqueta) {
            $nuevos = $filtros;
            $nuevos[$clave] = $valor;
            $href = opg_notif_v_url(opg_notif_v_params($nuevos));
            $activo = $filtros[$clave] === $valor;
            $html .= '<a class="opg-chip nt-filtro' . ($activo ? ' nt-filtro--activo' : '') . '" href="' . opg_notif_v_h($href) . '"'
                . ' hx-get="' . opg_notif_v_h(opg_notif_v_url(opg_notif_v_params($nuevos, array('action' => 'listar')))) . '"'
                . ' hx-target="#nt-contenido" hx-swap="innerHTML" hx-push-url="' . opg_notif_v_h($href) . '"'
                . ($activo ? ' aria-current="true"' : '') . '>' . opg_notif_v_h($etiqueta) . '</a>';
        }
        $html .= '</div></div>';
    }
    return $html . '</nav>';
}

/**
 * Contenido de #nt-contenido: filtros, Marcar todas (si hay alguna sin leer) y los tres bloques, o el estado vacio
 * (requisito 14).
 * $resultados: array(bloque => resultado de opg_notif_listar) para cada bloque de opg_notif_v_bloques().
 */
function opg_notif_v_contenido($filtros, $resultados, $ctx)
{
    $html = opg_notif_v_barra_filtros($filtros);
    $sin_leer = !empty($resultados['importantes']['filas']) || !empty($resultados['pendientes']['filas']);
    if ($sin_leer) {
        $html .= opg_notif_v_boton_todas($filtros);
    }
    $listas = '';
    foreach (array_keys(opg_notif_v_bloques()) as $bloque) {
        if (isset($resultados[$bloque])) {
            $listas .= opg_notif_v_bloque($bloque, $resultados[$bloque], $filtros, $ctx);
        }
    }
    if ($listas === '') {
        $filtrado = $filtros['categoria'] !== '' || $filtros['origen'] !== '';
        $listas = '<div class="nt-vacio"><i class="fa-solid fa-bell-slash" aria-hidden="true"></i><p class="opg-vacio">No tienes notificaciones</p>'
            . ($filtrado ? '<small>No hay ninguna con este filtro. Prueba con «Todas».</small>' : '') . '</div>';
    }
    return $html . '<div class="nt-listas">' . $listas . '</div>';
}

/**
 * Campana del header para una respuesta HTMX (208 apartado 8): el HTML de opg_notificaciones_campana_html sin su bloque
 * <style> (que en un fragmento se insertaria como contenido normal; la hoja ya esta en la pagina) y con hx-swap-oob, para
 * que htmx reemplace la campana del header ademas del destino de la accion. '' si no hay campana.
 */
function opg_notif_v_campana_oob($html)
{
    $html = trim((string) preg_replace('@<style\b[^>]*>.*?</style>@is', '', (string) $html));
    if ($html === '' || strpos($html, 'id="op-notif-campana"') === false) {
        return '';
    }
    if (strpos($html, 'hx-swap-oob=') === false) {
        $html = preg_replace('/id="op-notif-campana"/', 'id="op-notif-campana" hx-swap-oob="outerHTML:#op-notif-campana"', $html, 1);
    }
    return $html;
}

/** Aviso de error para la zona #nt-avisos. */
function opg_notif_v_aviso($mensaje)
{
    return '<div class="aviso err nt-aviso" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ' . opg_notif_v_h($mensaje) . '</div>';
}

// ── 3. Con base de datos: una fila y los destinos que ya no existen ─────────────────────────────────────────────────

/**
 * Una notificacion del usuario por id, como opg_notif_fila_lista, para devolver la fila tras la accion `visto`. Filtra
 * por el uid de la sesion: una ajena o inexistente da null, igual que un error (registrado).
 */
function opg_notif_v_leer_fila($uid, $id)
{
    $uid = opg_notif_uid($uid);
    $id = opg_notif_uid($id);
    if ($uid <= 0 || $id <= 0) {
        return null;
    }
    try {
        $filas = opg_notif_filas('SELECT id, tipo, categoria, origen, importancia, agrupable, actor_uid, url, cantidad, datos_json, created_at, leida_en FROM '
            . opg_notif_t('op_notificaciones') . ' WHERE id = ' . $id . ' AND uid = ' . $uid);
    } catch (Throwable $e) {
        opg_notif_log_lectura('opg_notif_v_leer_fila', $e);
        return null;
    }
    return $filas ? opg_notif_fila_lista($filas[0]) : null;
}

/**
 * Destinos borrados entre los enlaces de unas filas (requisito 16), como array("tema:5" => true, "aventura:9" => true).
 * Como mucho dos consultas por pagina, por clave primaria y sin uniones: los temas que no existen o no estan visibles
 * (borrados o sin aprobar) y las solicitudes del Tablon que no existen o estan borradas (estado 5). Ante un error (o una
 * tabla que falta) no se marca nada como borrado: es mejor mostrar un enlace de mas que esconder uno valido.
 */
function opg_notif_destinos_borrados($filas)
{
    $ids = array('tema' => array(), 'aventura' => array());
    foreach ($filas as $f) {
        $url = opg_notif_v_url_fila($f);
        $destino = $url !== '' ? opg_notif_v_destino($url) : null;
        if ($destino && $destino[1] > 0) {
            $ids[$destino[0]][$destino[1]] = true;
        }
    }
    $consultas = array(
        'tema' => array('tid', 'threads', 'visible = 1'),
        'aventura' => array('id', 'op_peticionAventuras', 'estado <> 5'),
    );
    $borrados = array();
    foreach ($consultas as $clase => $c) {
        if (!$ids[$clase]) {
            continue;
        }
        $lista = array_keys($ids[$clase]);
        try {
            $existen = array();
            foreach (opg_notif_filas('SELECT ' . $c[0] . ' AS id FROM ' . opg_notif_t($c[1]) . ' WHERE ' . $c[0] . ' IN ('
                . implode(',', array_map('intval', $lista)) . ') AND ' . $c[2]) as $fila) {
                $existen[(int) $fila['id']] = true;
            }
        } catch (Throwable $e) {
            opg_notif_log_lectura('opg_notif_destinos_borrados', $e);
            continue;
        }
        foreach ($lista as $id) {
            if (!isset($existen[$id])) {
                $borrados[$clase . ':' . $id] = true;
            }
        }
    }
    return $borrados;
}
