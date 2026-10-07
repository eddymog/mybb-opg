<?php
/**
 * Tablón de aventuras: fragmentos HTML (vistas).
 *
 * Funciones que reciben datos ya normalizados (opg_av_normalizar_fila y similares)
 * y devuelven HTML. Todo texto de usuario pasa por opg_av_h(); los atributos hx-*
 * se arman con enteros y rutas fijas, nunca con texto de usuario.
 *
 * Reglas de HTMX (docs/201_DesignPlan_Registro.md, 5.1): sin hx-on ni filtros con
 * expresiones (allowEval desactivado). Todo el comportamiento son peticiones al
 * servidor y CSS.
 *
 * Requiere inc/plugins/lib/aventuras.php (constantes y utilidades).
 */

const OPG_AV_URL = 'tablon_de_aventuras.php';
const OPG_AV_URL_CONSOLA = 'consola_aventuras.php';
const OPG_AV_URL_RECOMPENSAS = 'aventuras_recompensas.php';

// ── Utilidades ───────────────────────────────────────────────────────────────

/** Escapa texto para HTML (contenido y atributos). */
function opg_av_h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** URL de una acción: base?action=...&clave=valor (sin escapar para HTML). */
function opg_av_url($base, $accion, $params = array())
{
    $q = array('action' => $accion);
    foreach ($params as $k => $v) {
        if ($v !== null && $v !== '') {
            $q[$k] = $v;
        }
    }
    return $base . '?' . http_build_query($q, '', '&');
}

/** Atributo hx-get / hx-post ya escapado: opg_av_hx('get', URL, 'listar', array(...)). */
function opg_av_hx($metodo, $base, $accion, $params = array())
{
    return 'hx-' . $metodo . '="' . opg_av_h(opg_av_url($base, $accion, $params)) . '"';
}

/** Fecha legible (usa my_date de MyBB si existe). */
function opg_av_fecha($ts, $con_hora = false)
{
    $ts = (int) $ts;
    if ($ts <= 0) {
        return '';
    }
    $formato = $con_hora ? 'd/m/Y H:i' : 'd/m/Y';
    return function_exists('my_date') ? my_date($formato, $ts) : date($formato, $ts);
}

/** Salto de línea visible en texto de usuario ya escapado. */
function opg_av_texto_html($texto)
{
    return nl2br(opg_av_h($texto), false);
}

/** Sufijo de ids para formularios de edición (varios pueden estar abiertos a la vez). */
function opg_av_sfx($id)
{
    return (int) $id > 0 ? '-' . (int) $id : '';
}

/** Acento de color (variable de opg-tokens.css) por estado de la solicitud. */
function opg_av_acento_estado($estado)
{
    $mapa = array(
        0 => 'var(--opg-naranja)',
        1 => 'var(--opg-verde-exito)',
        2 => 'var(--opg-rojo-error)',
        3 => 'var(--opg-gris-texto)',
        4 => 'var(--opg-morado)',
        5 => 'var(--opg-gris-bloqueado)',
    );
    return isset($mapa[(int) $estado]) ? $mapa[(int) $estado] : $mapa[0];
}

// ── Avisos y estados vacíos ──────────────────────────────────────────────────

/** Aviso de resultado: tipo 'ok' o 'err'; el mensaje puede ser texto o lista de textos. */
function opg_av_v_aviso($tipo, $mensajes)
{
    $mensajes = (array) $mensajes;
    $clase = $tipo === 'ok' ? 'ok' : 'err';
    $rol = $tipo === 'ok' ? 'status' : 'alert';
    if (count($mensajes) === 1) {
        return '<p class="aviso ' . $clase . '" role="' . $rol . '">' . opg_av_h(reset($mensajes)) . '</p>';
    }
    $items = '';
    foreach ($mensajes as $m) {
        $items .= '<li>' . opg_av_h($m) . '</li>';
    }
    return '<div class="aviso ' . $clase . '" role="' . $rol . '"><ul class="ta-lista-avisos">' . $items . '</ul></div>';
}

function opg_av_v_vacio($texto)
{
    return '<p class="opg-vacio">' . opg_av_h($texto) . '</p>';
}

// ── Typeahead: buscadores, resultados y chips ───────────────────────────────

/**
 * Cuadro de búsqueda con typeahead. $destino identifica el buscador: 'participante',
 * 'narrador', 'isla' o 'tema'. Al escribir, pide los resultados al servidor y los
 * pone en su lista (el destino de los resultados es el div .opg-resultados).
 */
function opg_av_v_buscador($destino, $etiqueta, $placeholder, $id_peticion = 0, $oob = false)
{
    $accion = array('participante' => 'buscar_fichas', 'narrador' => 'buscar_fichas', 'isla' => 'buscar_islas', 'tema' => 'buscar_temas');
    $params = array('destino' => $destino);
    if ($id_peticion) {
        $params['id'] = (int) $id_peticion;
    }
    $sfx = opg_av_sfx($id_peticion);
    $id = 'ta-buscar-' . opg_av_h($destino) . $sfx;
    $res = 'ta-res-' . opg_av_h($destino) . $sfx;
    $oob_attr = $oob ? ' hx-swap-oob="true"' : '';
    return '<div class="af-field ta-buscador" id="' . $id . '-campo"' . $oob_attr . '>'
        . '<label for="' . $id . '">' . opg_av_h($etiqueta) . '</label>'
        . '<span class="opg-buscar-caja">'
        . '<input type="search" id="' . $id . '" name="q" autocomplete="off" placeholder="' . opg_av_h($placeholder) . '"'
        . ' aria-controls="' . $res . '" '
        . opg_av_hx('get', OPG_AV_URL, $accion[$destino], $params)
        . ' hx-trigger="input changed delay:250ms, search" hx-target="#' . $res . '" hx-swap="innerHTML" hx-sync="this:replace">'
        . '<div class="opg-resultados" id="' . $res . '" aria-live="polite"></div>'
        . '</span></div>';
}

/** Buscador con las etiquetas estándar de cada destino (para formularios y para repintarlo vacío). */
function opg_av_v_buscador_std($destino, $id_peticion = 0, $oob = false)
{
    $textos = array(
        'participante' => array('Añadir participantes', 'Busca por nombre o apodo'),
        'narrador' => array('Tu narrador', 'Busca la ficha del narrador con el que ya te pusiste de acuerdo'),
        'isla' => array('Busca la isla', 'Escribe el nombre de la isla'),
        'tema' => array('Busca el tema', 'Escribe 3 letras del nombre, o pega el enlace o el número del tema'),
    );
    return opg_av_v_buscador($destino, $textos[$destino][0], $textos[$destino][1], $id_peticion, $oob);
}

function opg_av_v_selector_participante($id = 0, $abierto = false, $oob = false)
{
    $target = 'ta-selector-participante' . opg_av_sfx($id);
    return '<div class="ta-selector-participante' . ($abierto ? ' ta-selector-abierto' : '')
        . '" id="' . $target . '"' . ($oob ? ' hx-swap-oob="true"' : '') . '>'
        . '<button type="button" class="opg-chip ta-anadir" '
        . opg_av_hx('get', OPG_AV_URL, 'form_participante', array('id' => (int) $id))
        . ' hx-target="#' . $target . '" hx-swap="outerHTML"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Añadir participante</button>'
        . '<div class="ta-selector-busqueda">' . opg_av_v_buscador_std('participante', $id) . '</div></div>';
}

/** Resultados de una búsqueda de fichas (participante o narrador). */
function opg_av_v_resultados_fichas($fichas, $destino, $mensaje = '', $id_peticion = 0)
{
    if (!$fichas) {
        return '<div class="opg-resultado-vacio">' . opg_av_h($mensaje !== '' ? $mensaje : 'Sin resultados.') . '</div>';
    }
    $sfx = opg_av_sfx($id_peticion);
    $destino_seg = $destino === 'narrador' ? 'narrador' : 'participante';
    $target = $destino_seg === 'narrador' ? '#ta-narrador-slot' . $sfx : '#ta-participantes' . $sfx;
    $swap = $destino_seg === 'narrador' ? 'innerHTML' : 'beforeend';
    $html = '';
    foreach ($fichas as $f) {
        $apodo = $f['apodo'] !== '' ? '<span>' . opg_av_h($f['apodo']) . '</span>' : '';
        $html .= '<button type="button" class="opg-resultado" '
            . opg_av_hx('get', OPG_AV_URL, 'elegir_ficha', array('fid' => (int) $f['fid'], 'destino' => $destino_seg, 'id' => $id_peticion ? (int) $id_peticion : null))
            . ' hx-target="' . $target . '" hx-swap="' . $swap . '" hx-include="#ta-participantes' . $sfx . '">'
            . opg_av_h($f['nombre']) . $apodo . '</button>';
    }
    return $html;
}

/** Resultados de una búsqueda de islas. */
function opg_av_v_resultados_islas($islas, $id_peticion = 0)
{
    if (!$islas) {
        return '<div class="opg-resultado-vacio">No hay islas con ese nombre.</div>';
    }
    $html = '';
    foreach ($islas as $i) {
        $html .= '<button type="button" class="opg-resultado" '
            . opg_av_hx('get', OPG_AV_URL, 'elegir_isla', array('fid' => (int) $i['fid'], 'id' => $id_peticion ? (int) $id_peticion : null))
            . ' hx-target="#ta-isla-campo' . opg_av_sfx($id_peticion) . '" hx-swap="outerHTML">' . opg_av_h($i['nombre']) . '</button>';
    }
    return $html;
}

/** Resultados de una búsqueda de temas de aventura. */
function opg_av_v_resultados_temas($temas, $mensaje, $id_peticion)
{
    if (!$temas) {
        return '<div class="opg-resultado-vacio">' . opg_av_h($mensaje) . '</div>';
    }
    $html = '';
    foreach ($temas as $t) {
        $fecha = trim($t['day'] . ' ' . $t['estacion'] . ' ' . $t['year']);
        $html .= '<button type="button" class="opg-resultado" ' . opg_av_hx('get', OPG_AV_URL, 'elegir_tema', array('id' => (int) $id_peticion, 'tid' => (int) $t['tid']))
            . ' hx-target="#ta-tema-slot' . opg_av_sfx($id_peticion) . '" hx-swap="innerHTML">' . opg_av_h($t['nombre'])
            . '<span>' . opg_av_h($t['foro'] . ($fecha !== '' ? ' · ' . $fecha : '')) . '</span></button>';
    }
    return $html;
}

/**
 * Chip de una ficha elegida. destino 'participante': lleva un campo oculto
 * participantes[] y un botón para quitarlo. 'narrador': campo oculto narrador_fid.
 * $fijo = true para el creador (sin campo oculto ni botón: el servidor lo añade).
 */
function opg_av_v_chip_ficha($ficha, $destino, $fijo = false, $id_peticion = 0)
{
    $nombre = opg_av_h($ficha['nombre']);
    if ($fijo) {
        return '<span class="opg-chip ta-chip ta-chip-fijo">' . $nombre . ' (tú)</span>';
    }
    $campo = $destino === 'narrador' ? 'narrador_fid' : 'participantes[]';
    $quitar = $destino === 'narrador'
        ? opg_av_hx('get', OPG_AV_URL, 'quitar_narrador', array('id' => (int) $id_peticion)) . ' hx-target="#ta-narrador-slot' . opg_av_sfx($id_peticion) . '" hx-swap="innerHTML"'
        : opg_av_hx('get', OPG_AV_URL, 'quitar_chip') . ' hx-target="closest .ta-chip" hx-swap="outerHTML"';
    return '<span class="opg-chip ta-chip">'
        . '<input type="hidden" name="' . $campo . '" value="' . (int) $ficha['fid'] . '">' . $nombre
        . ' <button type="button" class="ta-quitar" aria-label="Quitar a ' . $nombre . '" ' . $quitar . '>&times;</button></span>';
}

/** Isla elegida: campo oculto, nombre y enlace para cambiarla. */
function opg_av_v_slot_isla($isla, $id_peticion = 0)
{
    if (!$isla) {
        return '';
    }
    return '<span class="opg-chip ta-chip"><input type="hidden" name="isla_fid" value="' . (int) $isla['fid'] . '">'
        . opg_av_h($isla['nombre'])
        . ' <button type="button" class="ta-quitar" aria-label="Cambiar la isla" ' . opg_av_hx('get', OPG_AV_URL, 'quitar_isla', array('id' => (int) $id_peticion))
        . ' hx-target="#ta-isla-campo' . opg_av_sfx($id_peticion) . '" hx-swap="outerHTML">&times;</button></span>';
}

/** Selector exclusivo de isla: buscador sin isla, chip cuando ya se eligió una. */
function opg_av_v_campo_isla($isla, $id_peticion = 0)
{
    $sfx = opg_av_sfx($id_peticion);
    $html = '<div class="ta-isla" id="ta-isla-campo' . $sfx . '">';
    if (!$isla) {
        return $html . opg_av_v_buscador_std('isla', $id_peticion) . '</div>';
    }
    return $html . '<div class="ta-bloque"><span class="ta-rotulo">Isla</span><div class="ta-chips">'
        . opg_av_v_slot_isla($isla, $id_peticion) . '</div></div></div>';
}

/** Tema elegido (campo oculto con el tid) para vincular o entregar. */
function opg_av_v_slot_tema($tema, $id_peticion = 0)
{
    if (!$tema) {
        return opg_av_v_buscador_std('tema', $id_peticion);
    }
    $fecha = trim($tema['day'] . ' ' . $tema['estacion'] . ' ' . $tema['year']);
    return '<span class="opg-chip ta-chip"><input type="hidden" name="tema" value="' . (int) $tema['tid'] . '">'
        . opg_av_h($tema['nombre']) . '<small> · ' . opg_av_h($tema['foro'] . ($fecha !== '' ? ' · ' . $fecha : '')) . '</small>'
        . ' <button type="button" class="ta-quitar" aria-label="Cambiar el tema" ' . opg_av_hx('get', OPG_AV_URL, 'quitar_tema', array('id' => (int) $id_peticion))
        . ' hx-target="#ta-tema-slot' . opg_av_sfx($id_peticion) . '" hx-swap="innerHTML">&times;</button></span>';
}

// ── Formulario de solicitud (crear y editar) ────────────────────────────────

/** <select> de tier con los posts esperados por participante. */
function opg_av_v_select_tier($seleccionado, $id = 'ta-tier')
{
    $opts = '<option value="">Elige el tier</option>';
    for ($t = OPG_AV_TIER_MIN; $t <= OPG_AV_TIER_MAX; $t++) {
        $sel = ((int) $seleccionado === $t) ? ' selected' : '';
        $opts .= '<option value="' . $t . '"' . $sel . '>Tier ' . $t . ' (' . opg_av_posts_por_tier($t) . ' posts por participante)</option>';
    }
    return '<div class="af-field"><label for="' . $id . '">Tier de la aventura</label>'
        . '<select id="' . $id . '" name="tier" required>' . $opts . '</select></div>';
}

/**
 * Formulario de solicitud. $f: rol, participantes (lista de array(uid,nombre)),
 * tiene_narrador, narrador (array(uid,nombre)|null), idea, isla (array(fid,nombre)|null),
 * dia, tier, comentario, inframundo.
 * Modo 'crear': el usuario elige el rol. Modo 'editar' ($id_av): el rol no cambia.
 */
function opg_av_v_form_solicitud($f, $modo = 'crear', $id_av = 0, $errores = array(), $yo = array())
{
    $edicion = $modo === 'editar';
    $rol = $f['rol'] === 'narrador' ? 'narrador' : 'jugador';
    $idn = $edicion ? (int) $id_av : 0;       // id de la solicitud (0 al crear)
    $sfx = opg_av_sfx($idn);
    $id_form = $edicion ? 'av-' . $idn : 'ta-form';
    $accion = $edicion ? 'editar' : 'crear';
    $params = $edicion ? array('id' => $idn) : array();
    $nombre_yo = isset($yo['nombre']) ? $yo['nombre'] : '';
    $uid_yo = isset($yo['uid']) ? (int) $yo['uid'] : 0;

    $html = '<form class="ta-form" id="' . $id_form . '" ' . opg_av_hx('post', OPG_AV_URL, $accion, $params)
        . ' hx-target="#' . $id_form . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-naranja)">';
    if ($edicion) {
        $html .= '<div class="opg-card__cabecera"><span class="opg-card__titulo">Editar la solicitud #' . $idn . '</span></div>';
    }
    $html .= '<div class="ta-avisos" id="aviso-form-' . ($edicion ? $idn : 'crear') . '" aria-live="polite">'
        . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>';

    $html .= '<div class="ta-switch">';
    if ($edicion) {
        $html .= '<input type="hidden" name="rol" value="' . $rol . '">';
    } else {
        $html .= '<fieldset class="ta-grupo"><legend>¿Cuál es tu papel?</legend>'
            . '<input class="ta-radio" type="radio" name="rol" id="ta-rol-j" value="jugador"' . ($rol === 'jugador' ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-rol-j">Soy jugador</label>'
            . '<input class="ta-radio" type="radio" name="rol" id="ta-rol-n" value="narrador"' . ($rol === 'narrador' ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-rol-n">Soy el narrador</label></fieldset>';
    }

    // Participantes
    $chips = '';
    if (!$edicion || $rol === 'jugador') {
        $chips .= opg_av_v_chip_ficha(array('fid' => 0, 'nombre' => $nombre_yo), 'participante', true);
    }
    foreach ($f['participantes'] as $p) {
        if ($rol === 'jugador' && $uid_yo > 0 && (int) $p['uid'] === $uid_yo) {
            continue;
        }
        $chips .= opg_av_v_chip_ficha(array('fid' => $p['uid'], 'nombre' => $p['nombre']), 'participante', false, $idn);
    }
    $html .= '<div class="ta-bloque">'
        . '<span class="ta-rotulo">Participantes</span>'
        . '<div class="ta-chips ta-participantes" id="ta-participantes' . $sfx . '">' . $chips . '</div>'
        . opg_av_v_selector_participante($idn, $edicion && $rol === 'narrador' && !$f['participantes'])
        . '</div>';

    // Narrador (solo el rol jugador)
    if (!$edicion || $rol === 'jugador') {
        $tiene = !empty($f['tiene_narrador']);
        $html .= '<div class="ta-solo-jugador">'
            . '<fieldset class="ta-grupo"><legend>¿Tienes narrador?</legend>'
            . '<input class="ta-radio" type="radio" name="tiene_narrador" id="ta-tn-1' . $sfx . '" value="1"' . ($tiene ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-tn-1' . $sfx . '">Tengo narrador</label>'
            . '<input class="ta-radio" type="radio" name="tiene_narrador" id="ta-tn-0' . $sfx . '" value="0"' . (!$tiene ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-tn-0' . $sfx . '">No tengo narrador</label>'
            . '<div class="ta-con-narrador"><div class="ta-chips" id="ta-narrador-slot' . $sfx . '">'
            . ($f['narrador'] ? opg_av_v_chip_ficha(array('fid' => $f['narrador']['uid'], 'nombre' => $f['narrador']['nombre']), 'narrador', false, $idn) : opg_av_v_buscador_std('narrador', $idn))
            . '</div>'
            . '</div>'
            . '<div class="ta-sin-narrador af-field"><label for="ta-idea' . $sfx . '">Vende tu idea a los posibles narradores</label>'
            . '<textarea id="ta-idea' . $sfx . '" name="idea" maxlength="' . OPG_AV_MAX_IDEA . '" placeholder="¿Cómo quieres que te narren? ¿Qué te gustaría que pasara? ¿Qué tipo de narración o narrador buscas? ¿Con qué frecuencia piensas postear?">'
            . opg_av_h($f['idea']) . '</textarea></div>'
            . '</fieldset></div>';
    }
    $html .= '</div>';   // .ta-switch

    // Isla, día y tier
    $html .= '<div class="ta-fila">'
        . opg_av_v_campo_isla($f['isla'], $idn)
        . '<div class="af-field"><label for="ta-dia' . $sfx . '">Día (' . opg_av_h(OPG_AV_ESTACION . ' ' . OPG_AV_ANO) . ')</label>'
        . '<input type="number" id="ta-dia' . $sfx . '" name="dia" min="1" max="' . OPG_AV_DIAS . '" placeholder="1-90" required value="' . opg_av_h($f['dia']) . '"></div>'
        . opg_av_v_select_tier($f['tier'], 'ta-tier' . $sfx)
        . '</div>';

    $html .= '<div class="af-field af-field--ancho-completo"><label for="ta-comentario' . $sfx . '">Comentario público</label>'
        . '<textarea id="ta-comentario' . $sfx . '" name="comentario" required maxlength="' . OPG_AV_MAX_COMENTARIO . '" placeholder="Describe brevemente de qué trata la aventura (hasta ' . OPG_AV_MAX_COMENTARIO . ' caracteres).">'
        . opg_av_h($f['comentario']) . '</textarea></div>';
    $html .= '<label class="ta-casilla"><input type="checkbox" name="inframundo" value="1"' . (!empty($f['inframundo']) ? ' checked' : '') . '> Requerimiento de inframundo</label>';

    $html .= '<div class="ta-acciones">';
    if ($edicion) {
        $html .= '<button type="submit" class="btn-op btn-op--primario">Guardar cambios</button>'
            . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $idn)) . ' hx-target="#' . $id_form . '" hx-swap="outerHTML">Cancelar</button>';
    } else {
        $html .= '<button type="submit" class="btn-op btn-op--primario" id="ta-enviar">Enviar solicitud</button>';
    }
    $html .= '</div></form>';
    return $html;
}

/** Datos vacíos de un formulario de solicitud. */
function opg_av_form_vacio()
{
    return array('rol' => 'jugador', 'participantes' => array(), 'tiene_narrador' => false, 'narrador' => null, 'idea' => '',
        'isla' => null, 'dia' => '', 'tier' => '', 'comentario' => '', 'inframundo' => false);
}

/** Datos de formulario a partir de lo que llegó por POST (para repintar con errores). */
function opg_av_form_desde_entrada($entrada, $uid)
{
    $f = opg_av_form_vacio();
    $f['rol'] = isset($entrada['rol']) && $entrada['rol'] === 'narrador' ? 'narrador' : 'jugador';
    $f['participantes'] = array();
    $uids = opg_av_enteros(isset($entrada['participantes']) && is_array($entrada['participantes']) ? $entrada['participantes'] : array());
    $nar = isset($entrada['narrador_fid']) ? (int) $entrada['narrador_fid'] : 0;
    $fichas = opg_av_fichas_por_fids(array_merge($uids, $nar ? array($nar) : array()));
    foreach ($uids as $u) {
        if (isset($fichas[$u])) {
            $f['participantes'][] = array('uid' => $u, 'nombre' => $fichas[$u]['nombre']);
        }
    }
    $f['tiene_narrador'] = isset($entrada['tiene_narrador']) && opg_av_verdadero($entrada['tiene_narrador']);
    $f['narrador'] = ($nar && isset($fichas[$nar])) ? array('uid' => $nar, 'nombre' => $fichas[$nar]['nombre']) : null;
    $f['idea'] = isset($entrada['idea']) ? (string) $entrada['idea'] : '';
    $f['isla'] = opg_av_isla_valida(isset($entrada['isla_fid']) ? $entrada['isla_fid'] : 0);
    $f['dia'] = isset($entrada['dia']) ? (string) $entrada['dia'] : '';
    $f['tier'] = isset($entrada['tier']) ? (string) $entrada['tier'] : '';
    $f['comentario'] = isset($entrada['comentario']) ? (string) $entrada['comentario'] : '';
    $f['inframundo'] = isset($entrada['inframundo']) && opg_av_verdadero($entrada['inframundo']);
    return $f;
}

/** Datos de formulario a partir de una solicitud existente (para editar). */
function opg_av_form_desde_av($av)
{
    $f = opg_av_form_vacio();
    $f['rol'] = ($av['narrador_uid'] === $av['uid']) ? 'narrador' : 'jugador';
    foreach ($av['jugadores'] as $j) {
        if ($j['uid'] !== null) {
            $f['participantes'][] = array('uid' => $j['uid'], 'nombre' => $j['nombre']);
        }
    }
    $f['tiene_narrador'] = $av['narrador_uid'] > 0;
    $f['narrador'] = ($av['narrador_uid'] > 0 && $f['rol'] === 'jugador') ? array('uid' => $av['narrador_uid'], 'nombre' => $av['narrador_nombre']) : null;
    $f['idea'] = $av['idea_narrador'];
    $f['isla'] = $av['isla'];
    $f['dia'] = $av['dia'] !== null ? (string) $av['dia'] : '';
    $f['tier'] = $av['tier'] !== null ? (string) $av['tier'] : '';
    $f['comentario'] = $av['comentario_publico'];
    $f['inframundo'] = $av['inframundo'];
    return $f;
}

/**
 * Indicador de aventuras activas del usuario y, fuera de banda, el botón de envío.
 * El div lleva sus propios atributos hx para volver a refrescarse tras crear una solicitud.
 */
function opg_av_v_indicador_activas($solitarias, $total)
{
    $lleno_sol = $solitarias >= OPG_AV_MAX_SOLITARIAS;
    $lleno_total = $total >= OPG_AV_MAX_TOTAL;
    $html = '<div class="ta-activas" id="ta-activas" aria-live="polite" ' . opg_av_hx('get', OPG_AV_URL, 'mis_activas')
        . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-target="this" hx-swap="outerHTML">'
        . '<span class="ta-cifra' . ($lleno_sol ? ' ta-cifra-tope' : '') . '"><b>' . (int) $solitarias . ' / ' . OPG_AV_MAX_SOLITARIAS . '</b> solitarias activas</span>'
        . '<span class="ta-cifra' . ($lleno_total ? ' ta-cifra-tope' : '') . '"><b>' . (int) $total . ' / ' . OPG_AV_MAX_TOTAL . '</b> en total</span>';
    if ($lleno_total) {
        $html .= '<small class="ta-ayuda">Ya tienes el máximo de aventuras activas: no puedes solicitar otra como jugador hasta terminar alguna.</small>';
    } elseif ($lleno_sol) {
        $html .= '<small class="ta-ayuda">Ya tienes ' . OPG_AV_MAX_SOLITARIAS . ' aventuras solitarias activas: la siguiente tiene que ser grupal.</small>';
    }
    $html .= '</div>';
    return $html;
}

// ── Pestañas ─────────────────────────────────────────────────────────────────

/** Contador de aventuras sin narrador junto a la pestaña Otras aventuras. */
function opg_av_v_contador($n)
{
    return '<span class="opg-chip ta-contador' . ($n > 0 ? '' : ' ta-oculto') . '" id="ta-contador" '
        . opg_av_hx('get', OPG_AV_URL, 'contar_sin_narrador')
        . ' hx-trigger="aventuraCambiada from:body, aventuraCreada from:body" hx-target="this" hx-swap="outerHTML"'
        . ' aria-label="' . (int) $n . ' aventuras sin narrador">' . (int) $n . '</span>';
}

/** Barra de pestañas. $oob = true para enviarla como actualización fuera de banda. */
function opg_av_v_tabs($activa, $n_sin_narrador, $oob = false)
{
    $tabs = array('mias' => 'Mis aventuras', 'otras' => 'Otras aventuras', 'solicitud' => 'Solicitar aventura', 'narradores' => 'Narradores & Narrados');
    $html = '<nav class="ta-tabs" id="ta-tabs" aria-label="Secciones"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
    foreach ($tabs as $clave => $titulo) {
        $href = OPG_AV_URL . '?tab=' . $clave;
        $html .= '<a class="ta-tab' . ($activa === $clave ? ' ta-tab-activa' : '') . '" href="' . opg_av_h($href) . '" '
            . opg_av_hx('get', OPG_AV_URL, 'tab', array('tab' => $clave)) . ' hx-target="#ta-panel" hx-swap="innerHTML"'
            . ' hx-push-url="' . opg_av_h($href) . '"' . ($activa === $clave ? ' aria-current="page"' : '') . '>' . opg_av_h($titulo);
        if ($clave === 'narradores') {
            $html .= ' ' . opg_av_v_contador($n_sin_narrador);
        }
        $html .= '</a>';
    }
    return $html . '</nav>';
}

// ── Tarjeta de una solicitud ─────────────────────────────────────────────────

/** Nombres de los participantes separados por comas. */
function opg_av_v_nombres($jugadores)
{
    $n = array();
    foreach ($jugadores as $j) {
        $n[] = opg_av_h($j['nombre'] !== '' ? $j['nombre'] : 'Sin nombre');
    }
    return $n ? implode(', ', $n) : 'Ninguno';
}

/** Bloque con etiqueta (idea, motivos...). */
function opg_av_v_bloque_texto($etiqueta, $texto, $clase = '')
{
    return '<div class="ta-nota ' . $clase . '"><b>' . opg_av_h($etiqueta) . '</b><p>' . opg_av_texto_html($texto) . '</p></div>';
}

/**
 * Tarjeta de una solicitud. $ctx: uid (quien mira), tiene_ficha, resaltar (id).
 * Las acciones dependen del rol de quien mira y del estado.
 */
function opg_av_v_tarjeta($av, $ctx)
{
    $uid = isset($ctx['uid']) ? (int) $ctx['uid'] : 0;
    $id = $av['id'];
    $es_creador = $uid > 0 && $av['uid'] === $uid;
    $es_narrador = $uid > 0 && $av['narrador_uid'] === $uid;
    $es_parte = opg_av_es_parte($av, $uid);
    $entrega = $av['entrega'];
    $estado = $av['estado'];

    $titulo = '#' . $id . ' · ' . ($av['isla'] ? $av['isla']['nombre'] : 'Isla sin indicar');
    $html = '<article class="opg-card ta-card' . (!empty($ctx['resaltar']) && (int) $ctx['resaltar'] === $id ? ' ta-resaltada' : '') . '" id="av-' . $id . '" style="--opg-card-acento: ' . opg_av_acento_estado($estado) . '">';
    $html .= '<header class="opg-card__cabecera ta-cab"><span class="opg-card__titulo">' . opg_av_h($titulo) . '</span>'
        . '<span class="ta-estado ta-estado-' . opg_av_h($av['estado_clave']) . '">' . opg_av_h($av['estado_etiqueta']) . '</span></header>';

    $meta = array(opg_av_h($av['fecha_texto'] !== null ? $av['fecha_texto'] : 'Sin día'));
    if ($av['tier']) {
        $meta[] = 'Tier ' . (int) $av['tier'];
    }
    $html .= '<p class="opg-card__desc ta-meta">' . implode(' · ', $meta) . ($av['inframundo'] ? ' <span class="opg-chip ta-inframundo">Inframundo</span>' : '') . '</p>';

    $yo = array();
    if ($es_creador) { $yo[] = 'Creaste esta solicitud'; }
    if ($es_narrador) { $yo[] = 'Eres el narrador'; }
    elseif ($es_parte && !$es_creador) { $yo[] = 'Participas'; }
    elseif ($es_parte) { $yo[] = 'Participas'; }
    if ($yo) {
        $html .= '<p class="ta-yo">' . opg_av_h(implode(' · ', array_unique($yo))) . '</p>';
    }

    $html .= '<dl class="ta-datos">'
        . '<div><dt>Creador</dt><dd>' . opg_av_h($av['creador_nombre'] !== '' ? $av['creador_nombre'] : 'Desconocido') . '</dd></div>'
        . '<div><dt>Narrador</dt><dd>' . ($av['narrador_uid'] > 0 ? opg_av_h($av['narrador_nombre']) : '<i>Sin narrador</i>') . '</dd></div>'
        . '<div class="ta-dato-ancho"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div>'
        . '</dl>';

    if ($av['comentario_publico'] !== '') {
        $html .= opg_av_v_bloque_texto('Comentario', $av['comentario_publico']);
    }
    if ($av['idea_narrador'] !== '') {
        $html .= opg_av_v_bloque_texto('Idea', $av['idea_narrador'], 'ta-nota-idea');
    }
    if ($av['motivo_renuncia'] !== '') {
        $html .= opg_av_v_bloque_texto('El narrador anterior se salió', $av['motivo_renuncia'], 'ta-nota-renuncia');
    }
    if ($estado === 2 && $av['motivo_denegacion'] !== '') {
        $html .= opg_av_v_bloque_texto('Motivo de la denegación', $av['motivo_denegacion'], 'ta-nota-error');
    }
    if ($av['tid_vinculado'] > 0) {
        $html .= '<p class="ta-enlace"><a href="' . opg_av_h(opg_av_url_tema($av['tid_vinculado'])) . '" target="_blank" rel="noopener">Ver la aventura en el foro &rarr;</a></p>';
    }

    // Estado de la entrega
    if ($entrega) {
        if ($entrega['estado'] === 'pendiente') {
            $html .= '<p class="ta-entrega"><span class="opg-chip">Entrega pendiente de revisión</span></p>';
        } elseif ($entrega['estado'] === 'rechazada') {
            $html .= opg_av_v_bloque_texto('Entrega rechazada', $entrega['motivo_rechazo'] !== '' ? $entrega['motivo_rechazo'] : 'Sin motivo.', 'ta-nota-error');
        } elseif ($entrega['estado'] === 'entregada') {
            $html .= '<p class="ta-entrega"><span class="opg-chip">Recompensas entregadas el ' . opg_av_h(opg_av_fecha($entrega['entregado_at'])) . '</span></p>';
        }
    }

    $html .= '<div class="ta-avisos" id="aviso-' . $id . '" aria-live="polite"></div>';

    // Acciones según rol y estado
    $acciones = '';
    $objetivo = 'hx-target="#av-' . $id . '" hx-swap="outerHTML"';
    $puede_entregar = $es_narrador && $estado === 1 && (!$entrega || $entrega['estado'] === 'rechazada');
    if (opg_av_puede_vincular($av, $uid)) {
        $acciones .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_vincular', array('id' => $id)) . ' ' . $objetivo . '>Vincular aventura</button>';
    }
    if ($puede_entregar) {
        $acciones .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_entrega', array('id' => $id)) . ' ' . $objetivo . '>'
            . ($entrega ? 'Corregir y reenviar' : 'Entregar aventura') . '</button>';
    }
    if ($es_narrador && in_array($estado, array(0, 1), true)) {
        $acciones .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_renunciar', array('id' => $id)) . ' hx-target="#ta-modal" hx-swap="innerHTML">Dejar de narrar</button>';
    }
    if ($es_creador && in_array($estado, OPG_AV_ESTADOS_EDITABLES, true)) {
        $acciones .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_editar', array('id' => $id)) . ' ' . $objetivo . '>Editar</button>';
    }
    if ($estado === 4 && !$es_parte && !empty($ctx['tiene_ficha'])) {
        $acciones .= '<button type="button" class="opg-chip ta-postular" ' . opg_av_hx('get', OPG_AV_URL, 'form_postular', array('id' => $id)) . ' hx-target="#ta-modal" hx-swap="innerHTML">Postularme como narrador</button>';
    }
    if ($es_creador && $estado !== 5) {
        $acciones .= '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('post', OPG_AV_URL, 'pedir_borrado', array('id' => $id)) . ' ' . $objetivo
            . ' hx-confirm="¿Seguro que quieres pedir el borrado de esta solicitud? Dejará de contar como aventura activa y el staff decidirá si la elimina.">Pedir borrado</button>';
    }
    if ($acciones !== '') {
        $html .= '<div class="ta-acciones">' . $acciones . '</div>';
    }
    return $html . '</article>';
}

// ── Listas, filtros y paginación ────────────────────────────────────────────

/** Parámetros de filtro que se conservan al paginar. */
function opg_av_params_filtros($filtros)
{
    $p = array();
    foreach (array('ambito', 'estado', 'rol', 'uid', 'q') as $k) {
        if (isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null) {
            $p[$k] = $filtros[$k];
        }
    }
    return $p;
}

/** Grupo de chips (radios) de un filtro. */
function opg_av_v_grupo_filtro($titulo, $nombre, $opciones, $actual)
{
    $html = '<fieldset class="ta-grupo ta-filtro-grupo"><legend>' . opg_av_h($titulo) . '</legend>';
    foreach ($opciones as $valor => $etiqueta) {
        $html .= '<label class="ta-filtro"><input class="ta-radio" type="radio" name="' . opg_av_h($nombre) . '" value="' . opg_av_h($valor) . '"'
            . ((string) $actual === (string) $valor ? ' checked' : '') . '><span class="opg-chip">' . opg_av_h($etiqueta) . '</span></label>';
    }
    return $html . '</fieldset>';
}

/** Formulario de filtros de Mis aventuras o del listado de Otras aventuras. */
function opg_av_v_filtros($filtros)
{
    $ambito = (isset($filtros['ambito']) && $filtros['ambito'] === 'otras') ? 'otras' : 'mias';
    $estado = isset($filtros['estado']) ? $filtros['estado'] : '';
    $estados = $ambito === 'mias'
        ? array('' => 'Todas', 'creado' => 'Creadas', 'pendiente-narrador' => 'Pendiente', 'aprobado' => 'Aprobadas', 'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas')
        : array('' => 'Todas', 'creado' => 'Creadas', 'aprobado' => 'Aprobadas', 'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas');
    $html = '<form class="ta-filtros" id="ta-filtros-' . $ambito . '" ' . opg_av_hx('get', OPG_AV_URL, 'listar')
        . ' hx-trigger="change, input changed delay:250ms, submit" hx-target="#ta-lista-' . $ambito . '" hx-swap="innerHTML">'
        . '<input type="hidden" name="ambito" value="' . $ambito . '">'
        . opg_av_v_grupo_filtro('Estado', 'estado', $estados, $estado);
    if ($ambito === 'mias') {
        $html .= opg_av_v_grupo_filtro('Tu papel', 'rol', array('' => 'Todas', 'narrador' => 'Soy narrador', 'jugador' => 'Soy jugador'), isset($filtros['rol']) ? $filtros['rol'] : '');
        $html .= '<div class="af-field ta-filtro-busqueda"><label for="ta-q-mias">Buscar solicitud</label>'
            . '<input type="search" id="ta-q-mias" name="q" inputmode="numeric" autocomplete="off" placeholder="#850" value="' . opg_av_h(isset($filtros['q']) ? $filtros['q'] : '') . '"></div>';
    } else {
        $html .= '<div class="af-field ta-filtro-busqueda"><label for="ta-q-otras">Buscar por ID o personaje</label>'
            . '<input type="search" id="ta-q-otras" name="q" autocomplete="off" placeholder="#850 o nombre del personaje" value="' . opg_av_h(isset($filtros['q']) ? $filtros['q'] : '') . '"></div>';
    }
    return $html . '</form>';
}

/** Paginador (Anterior / Siguiente) que conserva los filtros. */
function opg_av_v_paginador($res, $filtros, $accion = 'listar', $base = OPG_AV_URL, $destino = null)
{
    if ($res['paginas'] <= 1) {
        return '';
    }
    $ambito = (isset($filtros['ambito']) && $filtros['ambito'] === 'otras') ? 'otras' : 'mias';
    $destino = $destino !== null ? $destino : '#ta-lista-' . $ambito;
    $params = opg_av_params_filtros($filtros);
    $html = '<nav class="ta-paginador" aria-label="Páginas">';
    if ($res['pagina'] > 1) {
        $html .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', $base, $accion, $params + array('pagina' => $res['pagina'] - 1)) . ' hx-target="' . opg_av_h($destino) . '" hx-swap="innerHTML">Anterior</button>';
    }
    $html .= '<span class="ta-pagina">Página ' . (int) $res['pagina'] . ' de ' . (int) $res['paginas'] . '</span>';
    if ($res['pagina'] < $res['paginas']) {
        $html .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', $base, $accion, $params + array('pagina' => $res['pagina'] + 1)) . ' hx-target="' . opg_av_h($destino) . '" hx-swap="innerHTML">Siguiente</button>';
    }
    return $html . '</nav>';
}

/** Contenido de la lista de tarjetas (sin el contenedor). */
function opg_av_v_lista($res, $filtros, $ctx, $texto_vacio = 'No hay aventuras con estos filtros.')
{
    $resumen = '<p class="ta-resultados-total">' . (int) $res['total'] . ' aventuras · Página '
        . (int) $res['pagina'] . ' de ' . (int) $res['paginas'] . '</p>';
    if (!$res['items']) {
        return $resumen . opg_av_v_vacio($texto_vacio);
    }
    $html = $resumen . '<div class="ta-tarjetas">';
    foreach ($res['items'] as $av) {
        $html .= opg_av_v_tarjeta($av, $ctx);
    }
    return $html . '</div>' . opg_av_v_paginador($res, $filtros);
}

/** Recomendación para quien todavía no tiene ninguna aventura. */
function opg_av_v_sin_aventuras()
{
    $href = OPG_AV_URL . '?tab=solicitud';
    return '<div class="opg-card ta-card" style="--opg-card-acento: var(--opg-naranja)">'
        . '<span class="opg-card__titulo">Todavía no participas en ninguna aventura</span>'
        . '<p class="opg-card__desc">¡Pide una! Cuéntanos con quién juegas, dónde y cuándo, y busca narrador si todavía no tienes.</p>'
        . '<div class="ta-acciones"><a class="btn-op btn-op--primario" href="' . opg_av_h($href) . '" ' . opg_av_hx('get', OPG_AV_URL, 'tab', array('tab' => 'solicitud'))
        . ' hx-target="#ta-panel" hx-swap="innerHTML" hx-push-url="' . opg_av_h($href) . '">Solicitar una aventura</a></div></div>';
}

// ── Paneles de las pestañas ─────────────────────────────────────────────────

function opg_av_v_estadisticas($datos, $ambito, $oob = false)
{
    $labels = array('total' => 'Total', 'estado_0' => 'Creadas', 'estado_4' => 'Pendientes',
        'estado_1' => 'Aprobadas', 'estado_2' => 'Denegadas', 'estado_3' => 'Finalizadas',
        'narrador' => 'Como narrador', 'jugador' => 'Como jugador');
    $html = '<div class="ta-estadisticas" id="ta-estadisticas-' . opg_av_h($ambito)
        . '" aria-label="Estadísticas del listado" aria-live="polite"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
    $grupos = array('estados' => array_slice($labels, 0, 6, true), 'roles' => array_slice($labels, 6, null, true));
    foreach ($grupos as $grupo => $campos) {
        $html .= '<dl class="ta-estadisticas-' . $grupo . '" aria-label="'
            . ($grupo === 'estados' ? 'Por estado' : 'Por papel') . '">';
        foreach ($campos as $clave => $label) {
            $html .= '<div class="ta-estadistica ta-estadistica-' . $clave . '"><dt>' . $label
                . '</dt><dd>' . (isset($datos[$clave]) ? (int) $datos[$clave] : 0) . '</dd></div>';
        }
        $html .= '</dl>';
    }
    return $html . '</div>';
}

function opg_av_v_mis_activas($items)
{
    $grupos = array('Solitarias' => array(), 'Grupales' => array());
    $narradas = array();
    foreach ($items as $av) {
        if (!empty($av['es_narrador'])) {
            $narradas[] = $av;
            continue;
        }
        $grupos[count($av['jugadores']) === 1 ? 'Solitarias' : 'Grupales'][] = $av;
    }
    $html = '<section class="ta-mis-activas" id="ta-mis-activas" '
        . opg_av_hx('get', OPG_AV_URL, 'mis_activas', array('detalle' => 1))
        . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-target="this" hx-swap="outerHTML" aria-live="polite">'
        . '<h3 class="ta-subtitulo">Activas de mi personaje <span>' . (count($items) - count($narradas)) . ' / ' . OPG_AV_MAX_TOTAL . ' en total</span></h3>'
        . '<div class="ta-activas-grupos">';
    $grupos['Narrando'] = $narradas;
    foreach ($grupos as $nombre => $avs) {
        if ($nombre === 'Narrando') {
            $html .= '</div><h3 class="ta-subtitulo ta-narradas-titulo">Aventuras que narro <span>'
                . count($avs) . (count($avs) === 1 ? ' activa' : ' activas') . ' · Sin límite</span></h3><div class="ta-activas-grupos ta-narradas">';
        }
        $html .= '<div><h4>' . $nombre . ' <span>' . count($avs)
            . ($nombre === 'Solitarias' ? ' / ' . OPG_AV_MAX_SOLITARIAS : '') . '</span></h4><ul>';
        foreach ($avs as $av) {
            $nombre = !empty($av['aventura_nombre']) ? $av['aventura_nombre'] : 'Sin tema vinculado';
            $fecha = opg_av_fecha($av['created_at']);
            $html .= '<li><div class="ta-activa-identidad"><a href="' . opg_av_h(OPG_AV_URL . '?id=' . $av['id']) . '">#' . $av['id']
                . ' · ' . opg_av_h($nombre) . '</a>'
                . '<span class="ta-activa-fecha">Registrada: ' . opg_av_h($fecha !== '' ? $fecha : 'Sin fecha')
                . ($av['fecha_texto'] ? ' · ' . opg_av_h($av['fecha_texto']) : '') . '</span></div>'
                . '<span class="ta-estado ta-estado-' . opg_av_h($av['estado_clave']) . '">'
                . opg_av_h($av['estado_etiqueta']) . '</span></li>';
        }
        if (!$avs) {
            $html .= '<li class="ta-ayuda">Ninguna activa</li>';
        }
        $html .= '</ul></div>';
    }
    return $html . '</div></section>';
}

function opg_av_v_panel_mias($filtros, $res, $ctx, $tiene_aventuras, $estadisticas = array(), $activas = array())
{
    $filtros['ambito'] = 'mias';
    $html = opg_av_v_estadisticas($estadisticas, 'mias') . opg_av_v_mis_activas($activas)
        . '<section class="ta-seccion" aria-labelledby="ta-t-mias"><h2 class="ta-titulo" id="ta-t-mias">Mis aventuras</h2>';
    if (!$tiene_aventuras) {
        return $html . opg_av_v_sin_aventuras() . '</section>';
    }
    $html .= opg_av_v_filtros($filtros)
        . '<div class="ta-lista" id="ta-lista-mias" aria-live="polite" ' . opg_av_hx('get', OPG_AV_URL, 'listar', opg_av_params_filtros($filtros) + array('pagina' => $res['pagina']))
        . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-include="#ta-filtros-mias">' . opg_av_v_lista($res, $filtros, $ctx) . '</div>';
    return $html . '</section>';
}

/** Bloque destacado "Aventuras sin narrador". */
function opg_av_v_bloque_sin_narrador($sin, $ctx)
{
    $n = (int) $sin['total'];
    $html = '<section class="ta-sin-bloque" id="ta-sin-narrador" aria-labelledby="ta-t-sin" ' . opg_av_hx('get', OPG_AV_URL, 'sin_narrador')
        . ' hx-trigger="aventuraCambiada from:body, aventuraCreada from:body" hx-target="this" hx-swap="outerHTML">'
        . '<header class="ta-sin-cabecera"><h2 id="ta-t-sin">Aventuras sin narrador</h2><span class="ta-sin-cifra" aria-label="' . $n . ' esperando narrador">' . $n . '</span></header>'
        . '<div class="ta-sin-cuerpo">';
    if (!$sin['items']) {
        $html .= opg_av_v_vacio('Ahora mismo todas las aventuras tienen narrador.');
    } else {
        $html .= '<div class="ta-tarjetas">';
        foreach ($sin['items'] as $av) {
            $html .= opg_av_v_tarjeta($av, $ctx);
        }
        $html .= '</div>' . opg_av_v_ver_mas_sin_narrador($sin);
    }
    return $html . '</div></section>';
}

/** Botón "Ver más" del bloque de sin narrador. */
function opg_av_v_ver_mas_sin_narrador($sin)
{
    if (empty($sin['hay_mas'])) {
        return '';
    }
    return '<div class="ta-ver-mas"><button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'sin_narrador', array('pagina' => $sin['pagina'] + 1, 'mas' => 1))
        . ' hx-target="closest .ta-ver-mas" hx-swap="outerHTML">Ver más</button></div>';
}

/** Tarjetas adicionales (y el siguiente "Ver más") cuando se pulsa "Ver más". */
function opg_av_v_sin_narrador_mas($sin, $ctx)
{
    $html = '';
    foreach ($sin['items'] as $av) {
        $html .= opg_av_v_tarjeta($av, $ctx);
    }
    return $html . opg_av_v_ver_mas_sin_narrador($sin);
}

// ── Mercado de narradores ───────────────────────────────────────────────────

/** Tarjeta de un narrador disponible. */
function opg_av_v_tarjeta_narrador($n)
{
    $nombre = $n['nombre'] !== '' ? $n['nombre'] : 'Narrador';
    $html = '<article class="opg-card ta-card ta-narrador" style="--opg-card-acento: var(--opg-verde-exito)">'
        . '<header class="opg-card__cabecera"><span class="opg-card__titulo">' . opg_av_h($nombre) . '</span></header>';
    if ($n['mensaje'] !== '') {
        $html .= '<p class="opg-card__desc">' . opg_av_texto_html($n['mensaje']) . '</p>';
    }
    $html .= '<p class="ta-meta">Disponible desde ' . opg_av_h(opg_av_fecha($n['desde'])) . ' · En curso: ' . (int) $n['en_curso'] . ' · Finalizadas: ' . (int) $n['finalizadas'] . '</p>'
        . '<p class="ta-discord"><b>Discord:</b> <span class="ta-copiable">' . opg_av_h($n['discord']) . '</span></p>'
        . '</article>';
    return $html;
}

/** Zona con la oferta propia: botón para ofrecerse o tarjeta con renovar, editar y retirar. */
function opg_av_v_mi_oferta($oferta, $tiene_ficha)
{
    $html = '<div class="ta-mi-oferta" id="ta-mi-oferta">';
    if (!$tiene_ficha) {
        return $html . opg_av_v_vacio('Necesitas una ficha aprobada para ofrecerte como narrador.') . '</div>';
    }
    if (!$oferta) {
        $html .= '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('get', OPG_AV_URL, 'form_oferta') . ' hx-target="#ta-mi-oferta" hx-swap="innerHTML">Ofrecerme como narrador</button>';
        return $html . '</div>';
    }
    $obj = 'hx-target="#ta-narradores" hx-swap="outerHTML"';
    $html .= '<div class="opg-card ta-card" style="--opg-card-acento: ' . ($oferta['vencida'] ? 'var(--opg-gris-bloqueado)' : 'var(--opg-verde-exito)') . '">'
        . '<header class="opg-card__cabecera"><span class="opg-card__titulo">Tu oferta de narrador</span>'
        . '<span class="ta-estado ' . ($oferta['vencida'] ? 'ta-estado-vencida' : 'ta-estado-aprobado') . '">' . ($oferta['vencida'] ? 'Vencida' : 'Vigente') . '</span></header>'
        . '<p class="ta-discord"><b>Discord:</b> <span class="ta-copiable">' . opg_av_h($oferta['discord']) . '</span></p>';
    if ($oferta['mensaje'] !== '') {
        $html .= '<p class="opg-card__desc">' . opg_av_texto_html($oferta['mensaje']) . '</p>';
    }
    $html .= '<p class="ta-meta">' . ($oferta['vencida'] ? 'Tu oferta venció: renuévala para volver a aparecer en la lista.'
        : 'Vence en ' . (int) $oferta['dias_restantes'] . ($oferta['dias_restantes'] === 1 ? ' día.' : ' días.')) . '</p>'
        . '<div class="ta-acciones">'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL, 'renovar_oferta') . ' ' . $obj . '>Renovar</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_oferta') . ' hx-target="#ta-mi-oferta" hx-swap="innerHTML">Editar</button>'
        . '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('post', OPG_AV_URL, 'retirar_oferta') . ' ' . $obj . ' hx-confirm="¿Retirar tu oferta de narrador?">Retirar oferta</button>'
        . '</div></div></div>';
    return $html;
}

/** Formulario de la oferta: Discord (obligatorio) y mensaje (opcional). */
function opg_av_v_form_oferta($oferta, $errores = array())
{
    return '<form class="opg-card ta-card ta-form" id="ta-form-oferta" ' . opg_av_hx('post', OPG_AV_URL, 'ofrecerme_narrador')
        . ' hx-target="#ta-narradores" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-verde-exito)">'
        . '<span class="opg-card__titulo">Ofrecerme como narrador</span>'
        . '<div class="ta-avisos" id="aviso-oferta" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="ta-fila">'
        . '<div class="af-field"><label for="ta-discord">Tu usuario de Discord</label>'
        . '<input type="text" id="ta-discord" name="discord" required maxlength="' . OPG_AV_MAX_DISCORD . '" value="' . opg_av_h($oferta ? $oferta['discord'] : '') . '">'
        . '<small>Es como te contactarán quienes busquen narrador.</small></div>'
        . '<div class="af-field"><label for="ta-mensaje-oferta">Mensaje (opcional)</label>'
        . '<textarea id="ta-mensaje-oferta" name="mensaje" maxlength="' . OPG_AV_MAX_MENSAJE_OFERTA . '" placeholder="Qué tipo de aventura prefieres o cuándo estás disponible.">'
        . opg_av_h($oferta ? $oferta['mensaje'] : '') . '</textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_MENSAJE_OFERTA . ' caracteres. Tu oferta vence a los ' . OPG_AV_OFERTA_DIAS . ' días si no la renuevas.</small></div>'
        . '</div><div class="ta-acciones">'
        . '<button type="submit" class="btn-op btn-op--primario">Publicar mi oferta</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'narradores_disponibles') . ' hx-target="#ta-narradores" hx-swap="outerHTML">Cancelar</button>'
        . '</div></form>';
}

/** Bloque completo "Narradores disponibles". */
function opg_av_v_bloque_narradores($oferta_propia, $lista, $tiene_ficha)
{
    $html = '<section class="ta-seccion ta-narradores" id="ta-narradores" aria-labelledby="ta-t-narr" ' . opg_av_hx('get', OPG_AV_URL, 'narradores_disponibles')
        . ' hx-trigger="ofertaCambiada from:body" hx-target="this" hx-swap="outerHTML">'
        . '<h2 class="ta-titulo" id="ta-t-narr">Narradores disponibles</h2>'
        . '<p class="ta-ayuda">Contacto por Discord · Ofertas vigentes durante 30 días</p>'
        . opg_av_v_mi_oferta($oferta_propia, $tiene_ficha);
    if (!$lista['items']) {
        $html .= opg_av_v_vacio('Nadie se ha ofrecido como narrador todavía.');
    } else {
        $html .= '<div class="ta-tarjetas ta-grilla">';
        foreach ($lista['items'] as $n) {
            $html .= opg_av_v_tarjeta_narrador($n);
        }
        $html .= '</div>';
    }
    return $html . '</section>';
}

function opg_av_v_panel_otras($filtros, $res, $ctx, $estadisticas)
{
    $filtros['ambito'] = 'otras';
    return opg_av_v_estadisticas($estadisticas, 'otras')
        . '<section class="ta-seccion" aria-labelledby="ta-t-otras"><h2 class="ta-titulo" id="ta-t-otras">Otras aventuras</h2>'
        . opg_av_v_filtros($filtros)
        . '<div class="ta-lista" id="ta-lista-otras" aria-live="polite" ' . opg_av_hx('get', OPG_AV_URL, 'listar', opg_av_params_filtros($filtros) + array('pagina' => $res['pagina']))
        . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-include="#ta-filtros-otras">' . opg_av_v_lista($res, $filtros, $ctx) . '</div></section>';
}

/** Pestaña Solicitar aventura: formulario e indicador de activas. */
function opg_av_v_panel_solicitud($f, $yo, $errores, $tiene_ficha, $activas)
{
    $html = '<section class="ta-seccion" aria-labelledby="ta-t-sol"><h2 class="ta-titulo" id="ta-t-sol">Solicitar aventura</h2>';
    if (!$tiene_ficha) {
        return $html . opg_av_v_vacio('Necesitas una ficha aprobada para solicitar una aventura.') . '</section>';
    }
    $html .= opg_av_v_indicador_activas($activas[0], $activas[1]);
    $html .= '<div id="ta-solicitud">' . opg_av_v_form_solicitud($f, 'crear', 0, $errores, $yo) . '</div>';
    return $html . '</section>';
}

/** Éxito al crear una solicitud. */
function opg_av_v_solicitud_creada($id, $estado)
{
    $href = OPG_AV_URL . '?tab=mias&id=' . (int) $id;
    return '<div class="opg-card ta-card" id="ta-form" style="--opg-card-acento: var(--opg-verde-exito)">'
        . opg_av_v_aviso('ok', 'Solicitud #' . (int) $id . ' enviada' . ($estado === 4 ? ': quedó pendiente de narrador.' : '.'))
        . '<div class="ta-acciones"><a class="btn-op btn-op--primario" href="' . opg_av_h($href) . '" ' . opg_av_hx('get', OPG_AV_URL, 'tab', array('tab' => 'mias', 'id' => (int) $id))
        . ' hx-target="#ta-panel" hx-swap="innerHTML" hx-push-url="' . opg_av_h($href) . '">Ver mis aventuras</a>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'tab', array('tab' => 'solicitud')) . ' hx-target="#ta-panel" hx-swap="innerHTML">Enviar otra</button></div></div>';
}

// ── Ventanas de confirmación ────────────────────────────────────────────────

/** Estructura común de una ventana modal sin JavaScript (velo + diálogo). */
function opg_av_v_modal($titulo, $cuerpo)
{
    return '<div class="ta-modal-velo">'
        . '<button type="button" class="ta-velo-cerrar" aria-label="Cerrar" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML"></button>'
        . '<div class="ta-modal" role="dialog" aria-modal="true" aria-labelledby="ta-modal-titulo">'
        . '<h3 id="ta-modal-titulo">' . opg_av_h($titulo) . '</h3>' . $cuerpo . '</div></div>';
}

/** Ventana de confirmación de "Postularme como narrador". */
function opg_av_v_modal_postular($av, $errores = array())
{
    $id = $av['id'];
    $cuerpo = '<dl class="ta-datos"><div><dt>Creó la solicitud</dt><dd>' . opg_av_h($av['creador_nombre']) . '</dd></div>'
        . '<div><dt>Isla y día</dt><dd>' . opg_av_h(($av['isla'] ? $av['isla']['nombre'] : 'Isla sin indicar') . ' · ' . ($av['fecha_texto'] !== null ? $av['fecha_texto'] : 'Sin día')) . '</dd></div>'
        . '<div class="ta-dato-ancho"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div></dl>';
    if ($av['idea_narrador'] !== '') {
        $cuerpo .= opg_av_v_bloque_texto('Idea', $av['idea_narrador'], 'ta-nota-idea');
    }
    $cuerpo .= '<form ' . opg_av_hx('post', OPG_AV_URL, 'postular_narrador', array('id' => $id)) . ' hx-target="#ta-modal" hx-swap="innerHTML" hx-disabled-elt="find button[type=submit]">'
        . '<div class="ta-avisos" id="aviso-modal" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<label class="ta-casilla"><input type="checkbox" name="confirmo" value="1" required> Confirmo que me puse de acuerdo con ' . opg_av_h($av['creador_nombre']) . ' y los participantes para narrarles esta aventura.</label>'
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Confirmar postulación</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML">Cancelar</button></div></form>';
    return opg_av_v_modal('¿Quieres narrar a este usuario?', '<p>Confirma que te has puesto de acuerdo con él para narrarle: habla con quien pidió la aventura antes de postularte.</p>' . $cuerpo);
}

/** Ventana de confirmación de "Dejar de narrar", con motivo obligatorio. */
function opg_av_v_modal_renunciar($av, $errores = array())
{
    $id = $av['id'];
    $cuerpo = '<form ' . opg_av_hx('post', OPG_AV_URL, 'renunciar_narrador', array('id' => $id)) . ' hx-target="#ta-modal" hx-swap="innerHTML" hx-disabled-elt="find button[type=submit]">'
        . '<div class="ta-avisos" id="aviso-modal" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="af-field"><label for="ta-motivo-renuncia">Motivo (obligatorio)</label>'
        . '<textarea id="ta-motivo-renuncia" name="motivo" required maxlength="' . OPG_AV_MAX_MOTIVO . '" placeholder="Cuéntales a los participantes y a los futuros narradores por qué te sales."></textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_MOTIVO . ' caracteres. Se mostrará mientras la aventura esté sin narrador.</small></div>'
        . '<label class="ta-casilla"><input type="checkbox" name="confirmo" value="1" required> Entiendo que la aventura volverá a la lista de aventuras sin narrador'
        . ($av['estado'] === 1 ? ' y perderá su aprobación' : '') . '.</label>'
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--peligro">Dejar de narrar</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML">Cancelar</button></div></form>';
    return opg_av_v_modal('¿Quieres dejar de narrar esta aventura?',
        '<p>La aventura #' . (int) $id . ' volverá a quedar sin narrador. No avisamos a los participantes: coméntaselo por Discord.</p>' . $cuerpo);
}

// ── Vincular y entregar ─────────────────────────────────────────────────────

/** Campo de tema: buscador, tema elegido y ayuda. */
function opg_av_v_campo_tema($av_id, $tema, $etiqueta = 'Tema de la aventura')
{
    return '<div class="ta-bloque"><span class="ta-rotulo">' . opg_av_h($etiqueta) . '</span>'
        . '<div class="ta-chips" id="ta-tema-slot' . opg_av_sfx($av_id) . '">' . opg_av_v_slot_tema($tema, $av_id) . '</div>'
        . '<small class="ta-ayuda">Solo temas del tipo Aventura, abiertos y de la isla de la solicitud.</small></div>';
}

/** Formulario "Vincular aventura" (reemplaza la tarjeta). */
function opg_av_v_form_vincular($av, $tema, $errores = array())
{
    $id = $av['id'];
    return '<form class="opg-card ta-card ta-form" id="av-' . $id . '" ' . opg_av_hx('post', OPG_AV_URL, 'vincular_aventura', array('id' => $id))
        . ' hx-target="#av-' . $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-verde-exito)">'
        . '<span class="opg-card__titulo">Vincular la aventura #' . (int) $id . '</span>'
        . '<div class="ta-avisos" id="aviso-form-' . $id . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . opg_av_v_campo_tema($id, $tema)
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Vincular</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $id)) . ' hx-target="#av-' . $id . '" hx-swap="outerHTML">Cancelar</button></div></form>';
}

/** Formulario "Entregar aventura" (reemplaza la tarjeta). $d viene de opg_ent_form(). */
function opg_av_v_form_entrega($d, $errores = array(), $entrada = array())
{
    $av = $d['av'];
    $id = $av['id'];
    $resumen = isset($entrada['resumen']) ? $entrada['resumen'] : $d['resumen'];
    $rep = isset($entrada['reputacion']) ? $entrada['reputacion'] : $d['reputacion'];
    $html = '<form class="opg-card ta-card ta-form" id="av-' . $id . '" ' . opg_av_hx('post', OPG_AV_URL, 'enviar_entrega', array('id' => $id))
        . ' hx-target="#av-' . $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-naranja)">'
        . '<span class="opg-card__titulo">Entregar la aventura #' . (int) $id . '</span>';
    if ($d['entrega'] && $d['entrega']['motivo_rechazo'] !== '') {
        $html .= opg_av_v_bloque_texto('Entrega rechazada', $d['entrega']['motivo_rechazo'], 'ta-nota-error');
    }
    $html .= '<div class="ta-avisos" id="aviso-form-' . $id . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<dl class="ta-datos"><div><dt>Petición</dt><dd>#' . (int) $id . '</dd></div>'
        . '<div><dt>Narrador</dt><dd>' . opg_av_h($av['narrador_nombre']) . ($d['nivel_narrador'] !== '' ? ' · nivel ' . opg_av_h($d['nivel_narrador']) : '') . '</dd></div>'
        . '<div><dt>Tier</dt><dd>' . ($av['tier'] ? 'Tier ' . (int) $av['tier'] : 'Sin tier') . '</dd></div>'
        . '<div class="ta-dato-ancho"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div></dl>';
    if ($d['tema_error']) {
        $html .= '<p class="ta-ayuda">El tema vinculado ya no sirve: ' . opg_av_h($d['tema_error']) . ' Elige otro.</p>';
    }
    $html .= opg_av_v_campo_tema($id, $d['tema'])
        . '<div class="af-field af-field--ancho-completo"><label for="ta-resumen-' . $id . '">Resumen: el resultado final de la aventura</label>'
        . '<textarea id="ta-resumen-' . $id . '" name="resumen" required maxlength="' . OPG_ENT_MAX_RESUMEN . '" placeholder="Cuenta cómo terminó la aventura (hasta ' . OPG_ENT_MAX_RESUMEN . ' caracteres).">' . opg_av_h($resumen) . '</textarea></div>'
        . '<fieldset class="ta-grupo"><legend>Reputación de la aventura</legend>'
        . '<input class="ta-radio" type="radio" name="reputacion" id="ta-rep-p-' . $id . '" value="positiva"' . ($rep === 'positiva' ? ' checked' : '') . ' required>'
        . '<label class="opg-chip ta-etiqueta-radio" for="ta-rep-p-' . $id . '">Positiva</label>'
        . '<input class="ta-radio" type="radio" name="reputacion" id="ta-rep-n-' . $id . '" value="negativa"' . ($rep === 'negativa' ? ' checked' : '') . '>'
        . '<label class="opg-chip ta-etiqueta-radio" for="ta-rep-n-' . $id . '">Negativa</label></fieldset>'
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Enviar entrega</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $id)) . ' hx-target="#av-' . $id . '" hx-swap="outerHTML">Cancelar</button></div></form>';
    return $html;
}
