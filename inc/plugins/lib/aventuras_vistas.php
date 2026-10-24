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
        . ' hx-trigger="input changed delay:250ms, search" hx-include="this" hx-target="#' . $res . '" hx-swap="innerHTML" hx-sync="this:replace">'
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
        'tema' => array('TID del tema', 'Escribe el TID del tema o pega su enlace'),
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
function opg_av_v_chip_ficha($ficha, $destino, $fijo = false, $id_peticion = 0, $cupo = null)
{
    $nombre = opg_av_h($ficha['nombre']);
    $estado_cupo = '';
    if ($destino === 'participante' && is_array($cupo)) {
        $tope_sol = OPG_AV_LIMITES_ACTIVOS ? '/' . OPG_AV_MAX_SOLITARIAS : '';
        $tope_total = OPG_AV_LIMITES_ACTIVOS ? '/' . OPG_AV_MAX_TOTAL : '';
        $estado_cupo = ' <small class="ta-chip-cupo' . (!empty($cupo['bloqueado']) ? ' ta-chip-cupo-lleno' : '') . '">'
            . (int) $cupo['solitarias'] . $tope_sol . ' solitarias · '
            . (int) $cupo['total'] . $tope_total . ' total</small>';
    }
    if ($fijo) {
        return '<span class="opg-chip ta-chip ta-chip-fijo">' . $nombre . ' (tú)' . $estado_cupo . '</span>';
    }
    $campo = $destino === 'narrador' ? 'narrador_fid' : 'participantes[]';
    $quitar = $destino === 'narrador'
        ? opg_av_hx('get', OPG_AV_URL, 'quitar_narrador', array('id' => (int) $id_peticion)) . ' hx-target="#ta-narrador-slot' . opg_av_sfx($id_peticion) . '" hx-swap="innerHTML"'
        : opg_av_hx('get', OPG_AV_URL, 'quitar_chip', array('fid' => (int) $ficha['fid'], 'id' => (int) $id_peticion))
            . ' hx-target="closest .ta-chip" hx-swap="outerHTML" hx-include="closest form"';
    return '<span class="opg-chip ta-chip">'
        . '<input type="hidden" name="' . $campo . '" value="' . (int) $ficha['fid'] . '">' . $nombre . $estado_cupo
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
    $tipo = isset($f['tipo']) && $f['tipo'] === OPG_AV_TIPO_AUTONARRADA ? OPG_AV_TIPO_AUTONARRADA : OPG_AV_TIPO_NORMAL;
    $disp_auto = isset($f['autonarrada_disponibilidad']) ? $f['autonarrada_disponibilidad'] : null;
    $puede_elegir_auto = !$edicion && $disp_auto && !empty($disp_auto['disponible']);
    if (!$edicion && !$puede_elegir_auto) {
        $tipo = OPG_AV_TIPO_NORMAL;
    }

    $clase_tipo = $tipo === OPG_AV_TIPO_AUTONARRADA ? ' ta-es-auto' : '';
    $html = '<form class="ta-form' . $clase_tipo . '" id="' . $id_form . '" ' . opg_av_hx('post', OPG_AV_URL, $accion, $params)
        . ' hx-target="#' . $id_form . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-naranja)">';
    if ($edicion) {
        $html .= '<div class="opg-card__cabecera"><span class="opg-card__titulo">Editar la solicitud #' . $idn . '</span></div>';
    }
    $html .= '<div class="ta-avisos" id="aviso-form-' . ($edicion ? $idn : 'crear') . '" aria-live="polite">'
        . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>';

    if ($edicion) {
        $html .= '<input type="hidden" name="tipo" value="' . opg_av_h($tipo) . '">';
    } elseif ($puede_elegir_auto) {
        $html .= '<fieldset class="ta-grupo ta-tipo-solicitud"><legend>Tipo de solicitud</legend>'
            . '<input class="ta-radio" type="radio" name="tipo" id="ta-tipo-normal" value="normal"' . ($tipo === OPG_AV_TIPO_NORMAL ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-tipo-normal"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Aventura</label>'
            . '<input class="ta-radio" type="radio" name="tipo" id="ta-tipo-auto" value="autonarrada"' . ($tipo === OPG_AV_TIPO_AUTONARRADA ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-tipo-auto"><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i> Crónica autonarrada</label>'
            . '</fieldset>';
    } else {
        $html .= '<input type="hidden" name="tipo" value="normal">';
    }

    if ($puede_elegir_auto || ($edicion && $tipo === OPG_AV_TIPO_AUTONARRADA)) {
        $html .= '<div class="ta-auto-reglas"><strong>Crónica autonarrada</strong><span>Un único post propio de al menos '
            . OPG_AV_AUTO_PALABRAS_MIN . ' palabras. Un cupo por semana. Recompensa fija: ' . OPG_AV_AUTO_XP . ' XP, '
            . OPG_AV_AUTO_NIKAS . ' Nikas, ' . OPG_AV_AUTO_REPUTACION . ' de reputación y '
            . number_format(OPG_AV_AUTO_BERRIES, 0, ',', '.') . ' Berries.</span></div>';
    }

    $rol_html = '';
    if ($edicion) {
        $html .= '<input type="hidden" name="rol" value="' . $rol . '">';
    } else {
        $rol_html = '<fieldset class="ta-grupo ta-campo-rol"><legend>¿Cuál es tu papel?</legend>'
            . '<input class="ta-radio" type="radio" name="rol" id="ta-rol-j" value="jugador"' . ($rol === 'jugador' ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-rol-j">Soy jugador</label>'
            . '<input class="ta-radio" type="radio" name="rol" id="ta-rol-n" value="narrador"' . ($rol === 'narrador' ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-rol-n">Soy el narrador</label></fieldset>';
    }

    // Participantes y cupos anticipados.
    $uids_cupo = array();
    if ($rol === 'jugador' && $uid_yo > 0) { $uids_cupo[] = $uid_yo; }
    foreach ($f['participantes'] as $p) { $uids_cupo[] = (int) $p['uid']; }
    $uids_cupo = array_values(array_unique(array_filter($uids_cupo)));
    $cupos = opg_av_cupos_participantes($uids_cupo, count($uids_cupo), $idn);
    $bloqueado = false;
    foreach ($cupos as $cupo) { if (!empty($cupo['bloqueado'])) { $bloqueado = true; break; } }

    $chips = '';
    if (!$edicion || $rol === 'jugador') {
        $chips .= opg_av_v_chip_ficha(array('fid' => $uid_yo, 'nombre' => $nombre_yo), 'participante', true, $idn, isset($cupos[$uid_yo]) ? $cupos[$uid_yo] : null);
    }
    foreach ($f['participantes'] as $p) {
        if ($rol === 'jugador' && $uid_yo > 0 && (int) $p['uid'] === $uid_yo) {
            continue;
        }
        $chips .= opg_av_v_chip_ficha(array('fid' => $p['uid'], 'nombre' => $p['nombre']), 'participante', false, $idn, isset($cupos[$p['uid']]) ? $cupos[$p['uid']] : null);
    }
    $participantes_html = '<div class="ta-bloque ta-campo-participantes">'
        . '<span class="ta-rotulo">Participantes</span>'
        . '<div class="ta-chips ta-participantes" id="ta-participantes' . $sfx . '">' . $chips . '</div>'
        . opg_av_v_selector_participante($idn, $edicion && $rol === 'narrador' && !$f['participantes'])
        . '</div>';

    // Narrador (solo el rol jugador)
    $narrador_html = '';
    $puede_buscar_narrador = !$edicion || $rol === 'jugador';
    if ($puede_buscar_narrador) {
        $tiene = !empty($f['tiene_narrador']);
        $narrador_html = '<div class="ta-solo-jugador ta-campo-narrador">'
            . '<fieldset class="ta-grupo"><legend>¿Tienes narrador?</legend>'
            . '<input class="ta-radio" type="radio" name="tiene_narrador" id="ta-tn-1' . $sfx . '" value="1"' . ($tiene ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-tn-1' . $sfx . '">Tengo narrador</label>'
            . '<input class="ta-radio" type="radio" name="tiene_narrador" id="ta-tn-0' . $sfx . '" value="0"' . (!$tiene ? ' checked' : '') . '>'
            . '<label class="opg-chip ta-etiqueta-radio" for="ta-tn-0' . $sfx . '">No tengo narrador</label>'
            . '<div class="ta-con-narrador"><div class="ta-chips" id="ta-narrador-slot' . $sfx . '">'
            . ($f['narrador'] ? opg_av_v_chip_ficha(array('fid' => $f['narrador']['uid'], 'nombre' => $f['narrador']['nombre']), 'narrador', false, $idn) : opg_av_v_buscador_std('narrador', $idn))
            . '</div>'
            . '<label class="ta-casilla ta-confirma-narrador"><input type="checkbox" name="confirma_narrador" value="1"' . (!empty($f['confirma_narrador']) ? ' checked' : '')
            . '> Ya hablé con este narrador y aceptó narrar mi aventura.</label>'
            . '</div>'
            . '</fieldset></div>';
    }

    $html .= '<div class="ta-normal-only"><section class="ta-form-seccion ta-form-personas" aria-labelledby="ta-form-personas' . $sfx . '">'
        . '<h3 id="ta-form-personas' . $sfx . '"><i class="fa-solid fa-user-group" aria-hidden="true"></i> Personas</h3>'
        . '<div class="ta-switch"><div class="ta-personas-grid">' . $rol_html . $narrador_html . '</div>' . $participantes_html . '</div></section></div>';

    // Isla, día y tier
    $html .= '<section class="ta-form-seccion ta-form-datos" aria-labelledby="ta-form-datos' . $sfx . '">'
        . '<h3 id="ta-form-datos' . $sfx . '"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> Datos de la aventura</h3>'
        . '<div class="ta-fila">'
        . opg_av_v_campo_isla($f['isla'], $idn)
        . '<div class="af-field ta-auto-only"><label for="ta-ano' . $sfx . '">Año</label>'
        . '<input type="number" id="ta-ano' . $sfx . '" name="ano" min="' . OPG_AV_ANO_MIN . '" max="' . OPG_AV_ANO . '" placeholder="' . OPG_AV_ANO_MIN . '-' . OPG_AV_ANO . '" required value="' . opg_av_h(isset($f['ano']) && $f['ano'] !== '' ? $f['ano'] : OPG_AV_ANO) . '"></div>'
        . '<div class="af-field ta-auto-only"><label for="ta-estacion' . $sfx . '">Estación</label>'
        . opg_av_v_select_estacion('ta-estacion' . $sfx, isset($f['estacion']) && $f['estacion'] !== '' ? $f['estacion'] : strtolower(OPG_AV_ESTACION)) . '</div>'
        . '<div class="af-field"><label for="ta-dia' . $sfx . '">Día<span class="ta-normal-only"> (' . opg_av_h(OPG_AV_ESTACION . ' ' . OPG_AV_ANO) . ')</span></label>'
        . '<input type="number" id="ta-dia' . $sfx . '" name="dia" min="1" max="' . OPG_AV_DIAS . '" placeholder="1-90" required value="' . opg_av_h($f['dia']) . '"></div>'
        . '<div class="ta-normal-only">' . opg_av_v_select_tier($f['tier'], 'ta-tier' . $sfx) . '</div>'
        . '</div>'
        . '<label class="ta-casilla ta-normal-only"><input type="checkbox" name="inframundo" value="1"' . (!empty($f['inframundo']) ? ' checked' : '') . '> Requerimiento de inframundo</label></section>';

    $html .= '<section class="ta-form-seccion ta-form-descripcion" aria-labelledby="ta-form-descripcion' . $sfx . '">'
        . '<h3 id="ta-form-descripcion' . $sfx . '"><i class="fa-solid fa-align-left" aria-hidden="true"></i> Descripción</h3>'
        . '<div class="af-field af-field--ancho-completo"><label for="ta-comentario' . $sfx . '">Comentario público</label>'
        . '<textarea id="ta-comentario' . $sfx . '" name="comentario" required maxlength="' . OPG_AV_MAX_COMENTARIO . '" placeholder="Describe brevemente de qué trata la aventura (hasta ' . OPG_AV_MAX_COMENTARIO . ' caracteres).">'
        . opg_av_h($f['comentario']) . '</textarea></div>';
    if ($puede_buscar_narrador) {
        $html .= '<div class="ta-normal-only"><div class="ta-propuesta af-field"><label for="ta-idea' . $sfx . '">Propuesta para posibles narradores</label>'
            . '<textarea id="ta-idea' . $sfx . '" name="idea" maxlength="' . OPG_AV_MAX_IDEA . '" placeholder="¿Cómo quieres que te narren? ¿Qué te gustaría que pasara? ¿Qué tipo de narración o narrador buscas? ¿Con qué frecuencia piensas postear?">'
            . opg_av_h($f['idea']) . '</textarea></div></div>';
    }
    $html .= '</section>';

    $html .= '<div class="ta-normal-only">' . opg_av_v_estado_cupos($cupos, $rol, $idn, $bloqueado) . '</div>';
    $html .= '<div class="ta-acciones ta-form-pie">';
    if ($edicion) {
        $html .= '<button type="submit" class="btn-op btn-op--primario" id="ta-enviar' . $sfx . '"' . ($bloqueado && $rol === 'jugador' ? ' disabled' : '') . '>Guardar cambios</button>'
            . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $idn)) . ' hx-target="#' . $id_form . '" hx-swap="outerHTML">Cancelar</button>';
    } else {
        $html .= '<button type="submit" class="btn-op btn-op--primario" id="ta-enviar"' . ($bloqueado && $rol === 'jugador' ? ' disabled' : '') . '>Enviar solicitud</button>';
    }
    $html .= '</div></form>';
    return $html;
}

function opg_av_v_estado_cupos($cupos, $rol, $id_peticion = 0, $bloqueado = null, $oob = false)
{
    if (!OPG_AV_LIMITES_ACTIVOS) {
        $texto = 'No hay límite de aventuras activas por personaje.';
        $tipo = '';
        $bloqueado = false;
    } elseif ($rol === 'narrador') {
        $texto = 'Narrar no consume el límite de tu personaje.';
        $tipo = '';
        $bloqueado = false;
    } else {
        $nombres = array();
        foreach ((array) $cupos as $cupo) {
            if (!empty($cupo['bloqueado'])) { $nombres[] = $cupo['motivo']; }
        }
        $bloqueado = $bloqueado === null ? (bool) $nombres : $bloqueado;
        $texto = $bloqueado ? implode(' ', array_unique($nombres)) : 'Todos los participantes tienen cupo para esta aventura.';
        $tipo = $bloqueado ? ' ta-cupos-bloqueados' : '';
    }
    $sfx = opg_av_sfx($id_peticion);
    $html = '<div class="ta-estado-cupos' . $tipo . '" id="ta-estado-cupos' . $sfx . '" aria-live="polite"'
        . ($oob ? ' hx-swap-oob="true"' : '') . '>' . opg_av_h($texto) . '</div>';
    if ($oob) {
        $html .= '<button type="submit" class="btn-op btn-op--primario" id="ta-enviar' . $sfx
            . '" hx-swap-oob="outerHTML"' . ($bloqueado ? ' disabled' : '') . '>'
            . ($id_peticion ? 'Guardar cambios' : 'Enviar solicitud') . '</button>';
    }
    return $html;
}

/** Selector de estación (crónicas autonarradas). */
function opg_av_v_select_estacion($id, $actual)
{
    $html = '<select id="' . opg_av_h($id) . '" name="estacion" required>';
    foreach (opg_av_estaciones() as $slug => $nombre) {
        $html .= '<option value="' . opg_av_h($slug) . '"' . ($slug === $actual ? ' selected' : '') . '>' . opg_av_h($nombre) . '</option>';
    }
    return $html . '</select>';
}

/** Datos vacíos de un formulario de solicitud. */
function opg_av_form_vacio()
{
    return array('tipo' => OPG_AV_TIPO_NORMAL, 'rol' => 'jugador', 'participantes' => array(), 'tiene_narrador' => false, 'confirma_narrador' => false, 'narrador' => null, 'idea' => '',
        'isla' => null, 'dia' => '', 'ano' => '', 'estacion' => '', 'tier' => '', 'comentario' => '', 'inframundo' => false);
}

/** Datos de formulario a partir de lo que llegó por POST (para repintar con errores). */
function opg_av_form_desde_entrada($entrada, $uid)
{
    $f = opg_av_form_vacio();
    $f['tipo'] = isset($entrada['tipo']) && $entrada['tipo'] === OPG_AV_TIPO_AUTONARRADA ? OPG_AV_TIPO_AUTONARRADA : OPG_AV_TIPO_NORMAL;
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
    $f['confirma_narrador'] = isset($entrada['confirma_narrador']) && opg_av_verdadero($entrada['confirma_narrador']);
    $f['narrador'] = ($nar && isset($fichas[$nar])) ? array('uid' => $nar, 'nombre' => $fichas[$nar]['nombre']) : null;
    $f['idea'] = isset($entrada['idea']) ? (string) $entrada['idea'] : '';
    $f['isla'] = opg_av_isla_valida(isset($entrada['isla_fid']) ? $entrada['isla_fid'] : 0);
    $f['dia'] = isset($entrada['dia']) ? (string) $entrada['dia'] : '';
    $f['ano'] = isset($entrada['ano']) ? (string) $entrada['ano'] : '';
    $f['estacion'] = isset($entrada['estacion']) ? (string) $entrada['estacion'] : '';
    $f['tier'] = isset($entrada['tier']) ? (string) $entrada['tier'] : '';
    $f['comentario'] = isset($entrada['comentario']) ? (string) $entrada['comentario'] : '';
    $f['inframundo'] = isset($entrada['inframundo']) && opg_av_verdadero($entrada['inframundo']);
    return $f;
}

/** Datos de formulario a partir de una solicitud existente (para editar). */
function opg_av_form_desde_av($av)
{
    $f = opg_av_form_vacio();
    $f['tipo'] = isset($av['tipo']) ? $av['tipo'] : OPG_AV_TIPO_NORMAL;
    $f['rol'] = ($av['narrador_uid'] === $av['uid']) ? 'narrador' : 'jugador';
    foreach ($av['jugadores'] as $j) {
        if ($j['uid'] !== null) {
            $f['participantes'][] = array('uid' => $j['uid'], 'nombre' => $j['nombre']);
        }
    }
    $f['tiene_narrador'] = $av['narrador_uid'] > 0;
    $f['confirma_narrador'] = $av['narrador_uid'] > 0;
    $f['narrador'] = ($av['narrador_uid'] > 0 && $f['rol'] === 'jugador') ? array('uid' => $av['narrador_uid'], 'nombre' => $av['narrador_nombre']) : null;
    $f['idea'] = $av['idea_narrador'];
    $f['isla'] = $av['isla'];
    $f['dia'] = $av['dia'] !== null ? (string) $av['dia'] : '';
    $f['ano'] = isset($av['ano']) ? (string) $av['ano'] : '';
    $f['estacion'] = isset($av['estacion']) ? (string) $av['estacion'] : '';
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
    $lleno_sol = OPG_AV_LIMITES_ACTIVOS && $solitarias >= OPG_AV_MAX_SOLITARIAS;
    $lleno_total = OPG_AV_LIMITES_ACTIVOS && $total >= OPG_AV_MAX_TOTAL;
    $tope_sol = OPG_AV_LIMITES_ACTIVOS ? ' / ' . OPG_AV_MAX_SOLITARIAS : '';
    $tope_total = OPG_AV_LIMITES_ACTIVOS ? ' / ' . OPG_AV_MAX_TOTAL : '';
    $html = '<div class="ta-activas" id="ta-activas" aria-live="polite" ' . opg_av_hx('get', OPG_AV_URL, 'mis_activas')
        . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-target="this" hx-swap="outerHTML">'
        . '<span class="ta-cifra' . ($lleno_sol ? ' ta-cifra-tope' : '') . '"><b>' . (int) $solitarias . $tope_sol . '</b> solitarias activas</span>'
        . '<span class="ta-cifra' . ($lleno_total ? ' ta-cifra-tope' : '') . '"><b>' . (int) $total . $tope_total . '</b> en total</span>';
    if ($lleno_total) {
        $html .= '<small class="ta-ayuda">Ya tienes el máximo de aventuras activas: no puedes solicitar otra como jugador hasta terminar alguna.</small>';
    } elseif ($lleno_sol) {
        $html .= '<small class="ta-ayuda">Ya tienes ' . OPG_AV_MAX_SOLITARIAS . ' aventuras solitarias activas: la siguiente tiene que ser grupal.</small>';
    }
    $html .= '</div>';
    return $html;
}

// ── Pestañas ─────────────────────────────────────────────────────────────────

/** Guía plegable y breve de los flujos principales del tablón. */
function opg_av_v_guia()
{
    // Una sección por pestaña: para qué sirve y lo principal que se hace en ella (no es un manual de todas las funciones).
    $secciones = array(
        array('fa-book-open', 'Mis aventuras', 'Tu panel: las aventuras que juegas y las que narras.', array(
            'Cuando una aventura está aprobada, vincula su tema del foro y, al terminar, entrégala para que el staff pague las recompensas.',
            'Puede vincularla y entregarla cualquiera del grupo, jugadores o narrador.',
            'Novedades te avisa cuando algo cambia en tus aventuras.',
        )),
        array('fa-circle-plus', 'Solicitar aventura', 'Pide una aventura nueva: quién juega, quién narra, isla, día y tier.', array(
            'Si ya tienes narrador, elígelo y recibirá una invitación. Si no, déjala sin narrador y los narradores se ofrecerán.',
            'El staff la revisa antes de aprobarla. Si la deniega, verás el motivo y podrás enviar otra.',
            'Con nivel 1 a ' . OPG_AV_AUTO_NIVEL_MAX . ' también puedes pedir una crónica autonarrada: una por semana.',
        )),
        array('fa-user-group', 'Narradores &amp; Narrados', 'El punto de encuentro entre jugadores y narradores.', array(
            'Narradores: publica tu oferta u ofrécete a narrar una solicitud que aún no tiene narrador.',
            'Jugadores: busca un narrador disponible o espera las candidaturas a tu solicitud.',
            'Nadie queda como narrador sin aceptarlo.',
        )),
        array('fa-compass', 'Otras aventuras', 'Mira lo que están jugando los demás.', array(
            'Busca por número de solicitud o por nombre de personaje.',
            '<strong>Ver otro personaje</strong> muestra sus aventuras activas, solo para consultar.',
        )),
    );
    $html = '<details class="ta-guia"><summary><span><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Cómo funciona el tablón</span>'
        . '<i class="fa-solid fa-chevron-down ta-guia-flecha" aria-hidden="true"></i></summary>'
        . '<div class="ta-guia-contenido"><p class="ta-guia-intro"><strong>Aquí pides aventuras, las sigues hasta el final y cobras sus recompensas.</strong> Cada pestaña sirve para una cosa.</p>'
        . '<p class="ta-guia-intro"><strong>El camino de una aventura:</strong> 1. La solicitas · 2. El staff la aprueba · 3. La juegas y vinculas su tema · 4. La entregas · 5. El staff paga y la cierra.</p>'
        . '<div class="ta-guia-secciones">';
    foreach ($secciones as $sec) {
        $html .= '<section><h3><i class="fa-solid ' . $sec[0] . '" aria-hidden="true"></i> ' . $sec[1] . '</h3><p>' . $sec[2] . '</p><ul>';
        foreach ($sec[3] as $punto) {
            $html .= '<li>' . $punto . '</li>';
        }
        $html .= '</ul></section>';
    }
    return $html . '</div><p class="ta-guia-estados"><strong>Estados:</strong> En revisión espera al staff · Buscando narrador espera a quien narre · Aprobada ya se puede jugar · Denegada indica el motivo · Finalizada ya se entregó y se pagó.</p>'
        . '</div></details>';
}

/** Contador de propuestas que requieren una decisión del usuario. */
function opg_av_v_contador($n)
{
    return '<span class="opg-chip ta-contador' . ($n > 0 ? '' : ' ta-oculto') . '" id="ta-contador" '
        . opg_av_hx('get', OPG_AV_URL, 'contar_propuestas')
        . ' hx-trigger="aventuraCambiada from:body, aventuraCreada from:body" hx-target="this" hx-swap="outerHTML"'
        . ' aria-label="' . (int) $n . ' propuestas pendientes">' . (int) $n . '</span>';
}

/** Barra de pestañas. $oob = true para enviarla como actualización fuera de banda. */
function opg_av_v_tabs($activa, $n_sin_narrador, $oob = false)
{
    $tabs = array(
        'mias' => array('Mis aventuras', 'fa-book-open'),
        'solicitud' => array('Solicitar aventura', 'fa-circle-plus'),
        'narradores' => array('Narradores & Narrados', 'fa-user-group'),
        'otras' => array('Otras aventuras', 'fa-compass'),
    );
    $html = '<nav class="ta-tabs" id="ta-tabs" aria-label="Secciones"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
    foreach ($tabs as $clave => $tab) {
        list($titulo, $icono) = $tab;
        $href = OPG_AV_URL . '?tab=' . $clave;
        $html .= '<a class="ta-tab' . ($activa === $clave ? ' ta-tab-activa' : '') . '" href="' . opg_av_h($href) . '" '
            . opg_av_hx('get', OPG_AV_URL, 'tab', array('tab' => $clave)) . ' hx-target="#ta-panel" hx-swap="innerHTML"'
            . ' hx-indicator="#ta-panel-cargando" hx-sync="#ta-panel:replace" hx-push-url="' . opg_av_h($href) . '" aria-controls="ta-panel"'
            . ($activa === $clave ? ' aria-current="page"' : '') . '>'
            . '<i class="fa-solid ' . opg_av_h($icono) . '" aria-hidden="true"></i><span>' . opg_av_h($titulo) . '</span>';
        if ($clave === 'narradores') {
            $html .= ' ' . opg_av_v_contador($n_sin_narrador);
        }
        $html .= '</a>';
    }
    return $html . '</nav>';
}

function opg_av_v_novedad_fila($n, $permitir_leer)
{
    $etiquetas = array(
        'invitacion_nueva' => 'Te invitaron a narrar', 'candidatura_nueva' => 'Nueva candidatura de narrador',
        'propuesta_aceptada' => 'Propuesta aceptada', 'propuesta_rechazada' => 'Propuesta rechazada',
        'propuesta_retirada' => 'Propuesta retirada', 'propuesta_cancelada' => 'Se asignó otro narrador',
        'propuesta_vencida' => 'Propuesta vencida',
        'aventura_aprobada' => 'Aventura aprobada', 'aventura_denegada' => 'Aventura denegada',
        'narrador_salio' => 'El narrador dejó la aventura', 'entrega_rechazada' => 'Entrega rechazada',
        'recompensas_entregadas' => 'Recompensas entregadas', 'oferta_por_vencer' => 'Tu oferta vence en 3 días',
    );
    $texto = isset($etiquetas[$n['tipo']]) ? $etiquetas[$n['tipo']] : 'Actualización de aventura';

    // Frase descriptiva: qué pasó, con qué aventura y quién lo hizo.
    $av = isset($n['aventura']) ? $n['aventura'] : null;
    $nombre_av = $av && $av['tema'] !== '' ? '«' . $av['tema'] . '»' : 'la aventura #' . (int) $n['peticion_id'];
    $lugar = array();
    if ($av && $av['isla'] !== '') {
        $lugar[] = $av['isla'];
    }
    if ($av && $av['tier'] > 0) {
        $lugar[] = 'Tier ' . $av['tier'];
    }
    $actor = !empty($n['actor']) ? $n['actor'] : 'El staff';
    $narr = !empty($n['narrador_propuesta']) ? $n['narrador_propuesta'] : 'un narrador';
    $frases = array(
        'invitacion_nueva' => ($actor !== 'El staff' ? $actor : 'El creador') . ' te invitó a narrar ' . $nombre_av . '. Acepta o rechaza la invitación en Narradores.',
        'candidatura_nueva' => $narr . ' se ofrece a narrar ' . $nombre_av . '. Acepta o rechaza su candidatura en Mis aventuras.',
        'propuesta_aceptada' => 'Se aceptó la propuesta de narrador de ' . $nombre_av . ' (' . $narr . '). La solicitud pasa a revisión del staff.',
        'propuesta_rechazada' => ($actor !== 'El staff' ? $actor : 'La otra parte') . ' rechazó la propuesta de narrador (' . $narr . ') para ' . $nombre_av . '.',
        'propuesta_retirada' => ($actor !== 'El staff' ? $actor : 'Quien la envió') . ' retiró su propuesta de narrador (' . $narr . ') para ' . $nombre_av . '.',
        'propuesta_cancelada' => 'Se asignó otro narrador a ' . $nombre_av . ', así que tu propuesta quedó cancelada.',
        'propuesta_vencida' => 'La propuesta de narrador (' . $narr . ') para ' . $nombre_av . ' venció sin respuesta.',
        'aventura_aprobada' => $actor . ' aprobó la solicitud de ' . $nombre_av . '. Ya puede jugarse.',
        'aventura_denegada' => $actor . ' denegó la solicitud de ' . $nombre_av . '. El creador puede corregirla y reenviarla desde el tablón.',
        'narrador_salio' => $actor . ' dejó de narrar ' . $nombre_av . '. La solicitud vuelve a buscar narrador.',
        'entrega_rechazada' => $actor . ' rechazó la entrega de ' . $nombre_av . '. Corrígela y vuelve a enviarla desde el tablón.',
        'recompensas_entregadas' => $actor . ' entregó las recompensas de ' . $nombre_av . '. Revisa tu ficha.',
        'oferta_por_vencer' => 'Tu oferta como narrador vence en 3 días. Renuévala para seguir apareciendo.',
    );
    $html = '<li class="ta-notificacion' . ($n['leida'] ? ' ta-notificacion-leida' : '') . '"><div><b>' . opg_av_h($texto) . '</b>';
    if (isset($frases[$n['tipo']])) {
        $html .= '<p class="ta-notificacion-texto">' . opg_av_h($frases[$n['tipo']]) . ($lugar ? ' <small>(' . opg_av_h(implode(' · ', $lugar)) . ')</small>' : '') . '</p>';
    }
    $html .= '<span>';
    if ($n['peticion_id']) {
        $html .= '<a href="' . opg_av_h(OPG_AV_URL . '?id=' . $n['peticion_id']) . '">Ver solicitud #' . (int) $n['peticion_id'] . '</a>';
    } elseif ($n['tipo'] === 'oferta_por_vencer') {
        $html .= '<a href="' . opg_av_h(OPG_AV_URL . '?tab=narradores') . '">Renovar oferta</a>';
    }
    $html .= '<time>' . opg_av_h(opg_av_fecha($n['created_at'], true)) . '</time></span>';
    if (in_array($n['tipo'], array('aventura_denegada', 'entrega_rechazada', 'narrador_salio'), true) && !empty($n['datos']['motivo'])) {
        $html .= '<small class="ta-notificacion-motivo">Motivo: ' . opg_av_h($n['datos']['motivo']) . '</small>';
    }
    $html .= '</div>';
    if ($permitir_leer && !$n['leida']) {
        $html .= '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL, 'leer_notificacion', array('notificacion_id' => $n['id']))
            . ' hx-target="#ta-novedades" hx-swap="outerHTML"><i class="fa-solid fa-check" aria-hidden="true"></i> Marcar leída</button>';
    }
    return $html . '</li>';
}

/** Panel persistente de novedades, inspirado en el resumen de Bitácora. */
function opg_av_v_novedades_panel($items, $cantidad)
{
    $pendientes = array();
    $historial = array();
    foreach ((array) $items as $n) {
        if ($n['leida']) { $historial[] = $n; } else { $pendientes[] = $n; }
    }
    $cantidad = (int) $cantidad;
    $clase = $cantidad > 0 ? ' ta-novedades--pendientes' : '';
    $resumen = $cantidad > 0
        ? $cantidad . ($cantidad === 1 ? ' actualización pendiente' : ' actualizaciones pendientes')
        : 'No tienes novedades pendientes.';
    $html = '<section class="ta-novedades' . $clase . '" id="ta-novedades" aria-labelledby="ta-t-novedades">'
        . '<header><span><i class="fa-solid fa-bell" aria-hidden="true"></i><span><strong id="ta-t-novedades">Novedades</strong>'
        . '<small>' . opg_av_h($resumen) . '</small></span></span>'
        . ($cantidad > 0 ? '<span class="ta-novedades-cifra" aria-label="' . $cantidad . ' sin leer">' . $cantidad . '</span>' : '')
        . ($cantidad > 0 ? '<button type="button" class="opg-chip ta-novedades-todas" ' . opg_av_hx('post', OPG_AV_URL, 'leer_todas_notificaciones')
            . ' hx-target="#ta-novedades" hx-swap="outerHTML"><i class="fa-solid fa-check-double" aria-hidden="true"></i> Marcar todas como leídas</button>' : '')
        . '</header>';
    if ($pendientes) {
        $html .= '<ul class="ta-notificaciones ta-notificaciones-pendientes">';
        foreach ($pendientes as $n) { $html .= opg_av_v_novedad_fila($n, true); }
        $html .= '</ul>';
    }
    if ($historial) {
        $html .= '<details class="ta-novedades-historial"><summary><span>Historial reciente</span><small>' . count($historial)
            . (count($historial) === 1 ? ' leída' : ' leídas') . '</small></summary><ul class="ta-notificaciones">';
        foreach ($historial as $n) { $html .= opg_av_v_novedad_fila($n, false); }
        $html .= '</ul></details>';
    }
    return $html . '</section>';
}

// ── Tarjeta de una solicitud ─────────────────────────────────────────────────

/** Nombres de los participantes separados por comas. */
function opg_av_v_nombres($jugadores)
{
    $n = array();
    foreach ($jugadores as $j) {
        $n[] = opg_av_v_persona(isset($j['uid']) ? $j['uid'] : 0, $j['nombre'] !== '' ? $j['nombre'] : 'Sin nombre');
    }
    return $n ? implode(', ', $n) : 'Ninguno';
}

function opg_av_v_persona($uid, $nombre)
{
    $nombre = $nombre !== '' ? $nombre : 'Desconocido';
    if ((int) $uid <= 0) {
        return opg_av_h($nombre);
    }
    return '<a href="/op/personaje.php?uid=' . (int) $uid . '">' . opg_av_h($nombre) . '</a>';
}

/** Bloque con etiqueta (idea, motivos...). */
function opg_av_v_bloque_texto($etiqueta, $texto, $clase = '')
{
    return '<div class="ta-nota ' . $clase . '"><b>' . opg_av_h($etiqueta) . '</b><p>' . opg_av_texto_html($texto) . '</p></div>';
}

/** Comentario público compacto, expandible sin JavaScript. */
function opg_av_v_descripcion($id, $texto)
{
    $control = 'ta-descripcion-' . (int) $id;
    return '<div class="ta-descripcion"><input class="ta-descripcion-control" type="checkbox" id="' . $control . '">'
        . '<div class="ta-nota ta-nota-comentario"><b>Comentario</b><p id="' . $control . '-texto">' . opg_av_texto_html($texto) . '</p></div>'
        . '<label class="ta-descripcion-toggle" for="' . $control . '" aria-controls="' . $control . '-texto">'
        . '<span class="ta-descripcion-mas">Ver descripción</span><span class="ta-descripcion-menos">Ocultar descripción</span></label></div>';
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

    $titulo = '#' . $id . ' · ' . ($av['aventura_nombre'] !== '' ? $av['aventura_nombre'] : 'Sin tema vinculado');
    $html = '<article class="opg-card ta-card' . (!empty($ctx['resaltar']) && (int) $ctx['resaltar'] === $id ? ' ta-resaltada' : '') . '" id="av-' . $id . '" style="--opg-card-acento: ' . opg_av_acento_estado($estado) . '">';
    $html .= '<header class="opg-card__cabecera ta-cab"><span class="opg-card__titulo">' . opg_av_h($titulo) . '</span>'
        . '<span class="ta-estado ta-estado-' . opg_av_h($av['estado_clave']) . '">' . opg_av_h($av['estado_etiqueta']) . '</span></header>';

    $meta = array();
    $faltan = array();
    if ($av['isla']) {
        $meta[] = '<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' . opg_av_h($av['isla']['nombre']) . '</span>';
    } else {
        $faltan[] = 'isla';
    }
    if ($av['fecha_texto'] !== null) {
        $meta[] = '<span><i class="fa-solid fa-calendar-day" aria-hidden="true"></i> ' . opg_av_h($av['fecha_texto']) . '</span>';
    } else {
        $faltan[] = 'día';
    }
    if (opg_av_es_autonarrada($av)) {
        $meta[] = '<span class="opg-chip ta-tipo-auto"><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i> Crónica autonarrada</span>';
        if (!empty($av['autonarrada']['palabras'])) {
            $meta[] = '<span><i class="fa-solid fa-align-left" aria-hidden="true"></i> ' . (int) $av['autonarrada']['palabras'] . ' palabras</span>';
        }
    } elseif ($av['tier']) {
        $meta[] = '<span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Tier ' . (int) $av['tier'] . '</span>';
    } else {
        $faltan[] = 'tier';
    }
    if ($av['inframundo']) {
        $meta[] = '<span class="opg-chip ta-inframundo">Inframundo</span>';
    }
    if ($faltan) {
        $texto_faltan = 'Falta ' . implode(', ', $faltan);
        $meta[] = '<span class="opg-chip ta-datos-incompletos" title="' . opg_av_h($texto_faltan) . '"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Datos incompletos<span class="ta-solo-lector">: ' . opg_av_h($texto_faltan) . '</span></span>';
    }
    $html .= '<div class="opg-card__desc ta-meta">' . implode('', $meta) . '</div>';

    $yo = array();
    if ($es_creador) { $yo[] = 'Creaste esta solicitud'; }
    if ($es_narrador) { $yo[] = 'Eres el narrador'; }
    elseif ($es_parte && !$es_creador) { $yo[] = 'Participas'; }
    elseif ($es_parte) { $yo[] = 'Participas'; }
    if ($yo) {
        $html .= '<p class="ta-yo">' . opg_av_h(implode(' · ', array_unique($yo))) . '</p>';
    }

    $html .= '<dl class="ta-datos">'
        . '<div class="ta-dato-creador"><dt>Creador</dt><dd>' . opg_av_v_persona($av['uid'], $av['creador_nombre']) . '</dd></div>'
        . '<div class="ta-dato-narrador"><dt>Narrador</dt><dd>' . ($av['narrador_uid'] > 0 ? opg_av_v_persona($av['narrador_uid'], $av['narrador_nombre']) : '<i>Sin narrador</i>') . '</dd></div>'
        . '<div class="ta-dato-ancho ta-dato-participantes"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div>'
        . '</dl>';

    if ($av['comentario_publico'] !== '') {
        $html .= opg_av_v_descripcion($id, $av['comentario_publico']);
    }
    if ($av['idea_narrador'] !== '') {
        $idea = opg_av_v_bloque_texto('Idea para narradores', $av['idea_narrador'], 'ta-nota-idea');
        $html .= $estado === 4 ? $idea : '<details class="ta-detalles"><summary>Idea para narradores</summary>' . $idea . '</details>';
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
    if (opg_av_es_autonarrada($av) && !empty($av['autonarrada']['entregada_at'])) {
        if ($av['autonarrada']['estado_pago'] === 'aplicado') {
            $html .= '<p class="ta-entrega"><span class="opg-chip">Recompensas entregadas'
                . (!empty($av['autonarrada']['pagada_at']) ? ' el ' . opg_av_h(opg_av_fecha($av['autonarrada']['pagada_at'])) : '')
                . '</span></p>';
        } else {
            $html .= '<p class="ta-entrega"><span class="opg-chip">Autonarrada entregada · Reputación '
                . opg_av_h($av['autonarrada']['reputacion']) . ' · Pendiente de revisión</span></p>';
        }
    }

    $html .= '<div class="ta-avisos" id="aviso-' . $id . '" aria-live="polite"></div>';

    // Acciones según rol y estado
    $principal = '';
    $secundarias = array();
    $objetivo = 'hx-target="#av-' . $id . '" hx-swap="outerHTML"';
    $puede_vincular = opg_av_puede_vincular($av, $uid);
    $ya_vinculada = $av['tid_vinculado'] > 0;
    $puede_entregar = !opg_av_es_autonarrada($av) && opg_ent_puede_enviar($av, $uid, $entrega);
    $puede_entregar_auto = opg_av_es_autonarrada($av) && $es_creador && $estado === 1
        && $av['tid_vinculado'] > 0 && empty($av['autonarrada']['entregada_at'])
        && $av['autonarrada']['estado_pago'] === 'pendiente';
    if ($puede_entregar) {
        $principal = '<button type="button" class="btn-op btn-op--primario ta-accion-principal" ' . opg_av_hx('get', OPG_AV_URL, 'form_entrega', array('id' => $id)) . ' ' . $objetivo . '><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> '
            . ($entrega ? 'Corregir y reenviar' : 'Entregar aventura') . '</button>';
    } elseif ($puede_entregar_auto) {
        $principal = '<button type="button" class="btn-op btn-op--primario ta-accion-principal" '
            . opg_av_hx('get', OPG_AV_URL, 'form_entrega', array('id' => $id)) . ' ' . $objetivo
            . '><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Entregar autonarrada</button>';
    } elseif ($puede_vincular && !$ya_vinculada) {
        $principal = '<button type="button" class="btn-op btn-op--primario ta-accion-principal" ' . opg_av_hx('get', OPG_AV_URL, 'form_vincular', array('id' => $id)) . ' ' . $objetivo . '><i class="fa-solid fa-link" aria-hidden="true"></i> Vincular aventura</button>';
    }
    if ($puede_vincular && $ya_vinculada) {
        $secundarias[] = '<button type="button" class="opg-chip" '
            . opg_av_hx('get', OPG_AV_URL, 'form_vincular', array('id' => $id)) . ' ' . $objetivo
            . '><i class="fa-solid fa-link" aria-hidden="true"></i> Revincular aventura</button>';
    } elseif ($puede_vincular && !$ya_vinculada && $principal !== '' && strpos($principal, 'form_vincular') === false) {
        // Quien puede entregar (o editar) tiene ese botón como principal: Vincular pasa a las acciones secundarias.
        $secundarias[] = '<button type="button" class="opg-chip" '
            . opg_av_hx('get', OPG_AV_URL, 'form_vincular', array('id' => $id)) . ' ' . $objetivo
            . '><i class="fa-solid fa-link" aria-hidden="true"></i> Vincular aventura</button>';
    }
    if (!opg_av_es_autonarrada($av) && $es_narrador && in_array($estado, array(0, 1), true)) {
        $secundarias[] = '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_renunciar', array('id' => $id)) . ' hx-target="#ta-modal" hx-swap="innerHTML"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i> Dejar de narrar</button>';
    }
    if ($es_creador && in_array($estado, OPG_AV_ESTADOS_EDITABLES, true)) {
        $editar = '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_editar', array('id' => $id)) . ' ' . $objetivo . '><i class="fa-solid fa-pen" aria-hidden="true"></i> ' . ($estado === 2 ? 'Corregir y reenviar' : 'Editar') . '</button>';
        if ($principal === '') {
            $principal = str_replace('class="opg-chip"', 'class="btn-op btn-op--primario ta-accion-principal"', $editar);
        } else {
            $secundarias[] = $editar;
        }
    }
    if (opg_av_puede_completar_fecha($av, $uid)) {
        $completar = '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_completar', array('id' => $id)) . ' ' . $objetivo . '><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Completar isla y día</button>';
        if ($principal === '') {
            $principal = str_replace('class="opg-chip"', 'class="btn-op btn-op--primario ta-accion-principal"', $completar);
        } else {
            $secundarias[] = $completar;
        }
    }
    $propuesta_propia = false;
    foreach ((array) (isset($av['propuestas']) ? $av['propuestas'] : array()) as $p) {
        if ($uid > 0 && (int) $p['narrador_uid'] === $uid) {
            $propuesta_propia = $p['tipo'];
        }
    }
    if ($estado === 4 && !$es_parte && !empty($ctx['tiene_ficha']) && !$propuesta_propia) {
        $principal = '<button type="button" class="btn-op btn-op--primario ta-accion-principal ta-postular" ' . opg_av_hx('get', OPG_AV_URL, 'form_postular', array('id' => $id)) . ' hx-target="#ta-modal" hx-swap="innerHTML"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Me interesa narrar</button>';
    }
    if ($es_creador && !empty($av['propuestas'])) {
        $propuestas = '<div class="ta-propuestas-tarjeta"><b>Propuestas de narrador</b>';
        foreach ($av['propuestas'] as $p) {
            $propuestas .= '<div><span>' . opg_av_v_persona($p['narrador_uid'], $p['narrador_usuario']) . '</span>';
            if (!empty($p['mensaje'])) {
                $propuestas .= '<small>' . opg_av_h($p['mensaje']) . '</small>';
            }
            $propuestas .= '<span class="ta-acciones"><button type="button" class="opg-chip" '
                . opg_av_hx('post', OPG_AV_URL, 'aceptar_propuesta', array('propuesta_id' => (int) $p['id'], 'tab' => 'mias'))
                . ' hx-target="#ta-panel" hx-swap="innerHTML">Aceptar</button><button type="button" class="opg-chip" '
                . opg_av_hx('post', OPG_AV_URL, 'rechazar_propuesta', array('propuesta_id' => (int) $p['id'], 'tab' => 'mias'))
                . ' hx-target="#ta-panel" hx-swap="innerHTML">Rechazar</button></span></div>';
        }
        $html .= $propuestas . '</div>';
    }
    if ($es_creador && $estado !== 5) {
        $secundarias[] = '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('post', OPG_AV_URL, 'pedir_borrado', array('id' => $id)) . ' ' . $objetivo
            . ' hx-confirm="¿Seguro que quieres pedir el borrado de esta solicitud? Dejará de contar como aventura activa y el staff decidirá si la elimina."><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Pedir borrado</button>';
    }
    if ($es_parte) {
        $secundarias[] = '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'historial_usuario', array('id' => $id))
            . ' hx-target="#ta-modal" hx-swap="innerHTML"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Historial</button>';
    }
    if ($principal !== '' || $secundarias) {
        $html .= '<div class="ta-acciones">' . $principal;
        if ($secundarias) {
            $html .= '<details class="ta-mas-acciones"><summary><i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Más acciones</summary><div>' . implode('', $secundarias) . '</div></details>';
        }
        $html .= '</div>';
    }
    return $html . '</article>';
}

// ── Listas, filtros y paginación ────────────────────────────────────────────

/** Parámetros de filtro que se conservan al paginar. */
function opg_av_params_filtros($filtros)
{
    $p = array();
    foreach (array('ambito', 'vista', 'estado', 'rol', 'uid', 'q', 'densidad', 'incompletas', 'resolubles', 'orden', 'vigencia') as $k) {
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
    $vista = isset($filtros['vista']) ? $filtros['vista'] : ($ambito === 'mias' ? 'activas' : 'actuales');
    $vistas = $ambito === 'mias'
        ? array('activas' => 'Activas', 'historial' => 'Historial', 'todas' => 'Todas')
        : array('actuales' => 'Actuales', 'historial' => 'Historial', 'todas' => 'Todas');
    if ($vista === 'historial') {
        $estados = array('' => 'Todos', 'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas');
    } elseif ($vista === 'todas') {
        $estados = $ambito === 'mias'
            ? array('' => 'Todos', 'creado' => 'En revisión', 'pendiente-narrador' => 'Buscando narrador', 'aprobado' => 'Aprobadas', 'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas')
            : array('' => 'Todos', 'creado' => 'En revisión', 'aprobado' => 'Aprobadas', 'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas');
    } else {
        $estados = $ambito === 'mias'
            ? array('' => 'Todos', 'creado' => 'En revisión', 'pendiente-narrador' => 'Buscando narrador', 'aprobado' => 'Aprobadas')
            : array('' => 'Todos', 'creado' => 'En revisión', 'aprobado' => 'Aprobadas');
    }
    $html = '<form class="ta-filtros" id="ta-filtros-' . $ambito . '" ' . opg_av_hx('get', OPG_AV_URL, 'listar')
        . ' hx-trigger="change, input changed delay:250ms, submit" hx-target="#ta-panel" hx-swap="innerHTML"'
        . ' hx-include=".ta-densidad input:checked">'
        . '<input type="hidden" name="ambito" value="' . $ambito . '">'
        . '<input type="hidden" name="panel" value="1">'
        . opg_av_v_grupo_filtro($ambito === 'mias' ? 'Vista' : 'Periodo', 'vista', $vistas, $vista)
        . opg_av_v_grupo_filtro('Estado', 'estado', $estados, $estado);
    if ($ambito === 'mias') {
        $html .= opg_av_v_grupo_filtro('Tu papel', 'rol', array('' => 'Todas', 'narrador' => 'Soy narrador', 'jugador' => 'Soy jugador'), isset($filtros['rol']) ? $filtros['rol'] : '');
        $html .= '<div class="af-field ta-filtro-busqueda"><label for="ta-q-mias">Buscar solicitud</label>'
            . '<input type="search" id="ta-q-mias" name="q" inputmode="numeric" autocomplete="off" placeholder="#850" value="' . opg_av_h(isset($filtros['q']) ? $filtros['q'] : '') . '"></div>';
    } else {
        $html .= '<div class="af-field ta-filtro-busqueda"><label for="ta-q-otras">Buscar por ID o personaje</label>'
            . '<input type="search" id="ta-q-otras" name="q" autocomplete="off" placeholder="#850 o nombre del personaje" value="' . opg_av_h(isset($filtros['q']) ? $filtros['q'] : '') . '"></div>';
    }
    if (!empty($filtros['estado']) || !empty($filtros['rol']) || !empty($filtros['q'])) {
        $limpios = array('ambito' => $ambito, 'vista' => $vista,
            'densidad' => isset($filtros['densidad']) ? $filtros['densidad'] : 'compacta', 'panel' => 1);
        $html .= '<button type="button" class="opg-chip ta-limpiar-filtros" '
            . opg_av_hx('get', OPG_AV_URL, 'listar', $limpios)
            . ' hx-target="#ta-panel" hx-swap="innerHTML"><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Limpiar filtros</button>';
    }
    return $html . '</form>';
}

/** Control de densidad persistido en la URL y compartido con los filtros. */
function opg_av_v_densidad($ambito, $actual = 'compacta')
{
    $ambito = $ambito === 'otras' ? 'otras' : 'mias';
    $actual = $actual === 'expandida' ? 'expandida' : 'compacta';
    $hx = function ($densidad) use ($ambito) {
        return opg_av_hx('get', OPG_AV_URL, 'listar', array('ambito' => $ambito, 'panel' => 1))
            . ' hx-trigger="change" hx-target="#ta-panel" hx-swap="innerHTML" hx-include="#ta-filtros-' . $ambito . '"';
    };
    return '<fieldset class="ta-densidad"><legend>Densidad</legend>'
        . '<label><input class="ta-densidad-compacta" type="radio" name="densidad" value="compacta"'
        . ($actual === 'compacta' ? ' checked' : '') . ' ' . $hx('compacta') . '><span><i class="fa-solid fa-bars" aria-hidden="true"></i> Compacta</span></label>'
        . '<label><input class="ta-densidad-expandida" type="radio" name="densidad" value="expandida"'
        . ($actual === 'expandida' ? ' checked' : '') . ' ' . $hx('expandida') . '><span><i class="fa-solid fa-align-justify" aria-hidden="true"></i> Expandida</span></label></fieldset>';
}

/** Paginador con extremos y ventana de páginas que conserva los filtros. */
function opg_av_v_paginador($res, $filtros, $accion = 'listar', $base = OPG_AV_URL, $destino = null, $swap = 'innerHTML')
{
    if ($res['paginas'] <= 1) {
        return '';
    }
    $ambito = (isset($filtros['ambito']) && $filtros['ambito'] === 'otras') ? 'otras' : 'mias';
    $destino = $destino !== null ? $destino : '#ta-lista-' . $ambito;
    $params = opg_av_params_filtros($filtros);
    $html = '<nav class="ta-paginador" aria-label="Páginas">';
    $pagina = (int) $res['pagina'];
    $paginas = (int) $res['paginas'];
    $boton = function ($p, $texto, $actual = false) use ($base, $accion, $params, $destino, $swap) {
        return '<button type="button" class="opg-chip' . ($actual ? ' ta-pagina-activa' : '') . '" '
            . opg_av_hx('get', $base, $accion, $params + array('pagina' => $p)) . ' hx-target="' . opg_av_h($destino)
            . '" hx-swap="' . opg_av_h($swap) . '"' . ($actual ? ' aria-current="page"' : '') . '>' . $texto . '</button>';
    };
    if ($pagina > 1) {
        $html .= $boton(1, '<span aria-hidden="true">«</span><span class="ta-solo-lector">Primera</span>')
            . $boton($pagina - 1, '<span aria-hidden="true">‹</span><span class="ta-solo-lector">Anterior</span>');
    }
    $desde = max(1, $pagina - 2);
    $hasta = min($paginas, $desde + 4);
    $desde = max(1, $hasta - 4);
    for ($p = $desde; $p <= $hasta; $p++) {
        $html .= $boton($p, (string) $p, $p === $pagina);
    }
    if ($pagina < $paginas) {
        $html .= $boton($pagina + 1, '<span aria-hidden="true">›</span><span class="ta-solo-lector">Siguiente</span>')
            . $boton($paginas, '<span aria-hidden="true">»</span><span class="ta-solo-lector">Última</span>');
    }
    return $html . '</nav>';
}

/** Contenido de la lista de tarjetas (sin el contenedor). */
function opg_av_v_lista($res, $filtros, $ctx, $texto_vacio = 'No hay aventuras con estos filtros.')
{
    $resumen = '<p class="ta-resultados-total" tabindex="-1">' . (int) $res['total'] . ' aventuras · Página '
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

function opg_av_v_estadisticas($datos, $ambito, $oob = false, $filtros = array())
{
    $estados = opg_av_etiquetas_estadisticas();
    $labels = array('total' => $estados['total'], 'estado_0' => $estados['creado'], 'estado_4' => $estados['pendiente-narrador'],
        'estado_1' => $estados['aprobado'], 'estado_2' => $estados['denegado'], 'estado_3' => $estados['finalizado'],
        'narrador' => 'Como narrador', 'jugador' => 'Como jugador');
    $mostrar_roles = $ambito === 'mias';
    $html = '<div class="ta-estadisticas' . ($mostrar_roles ? '' : ' ta-estadisticas-solo-estados') . '" id="ta-estadisticas-' . opg_av_h($ambito)
        . '" aria-label="Estadísticas del listado" aria-live="polite"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
    $vista = isset($filtros['vista']) ? $filtros['vista'] : ($ambito === 'mias' ? 'activas' : 'actuales');
    $claves_estado = $vista === 'historial' ? array('total', 'estado_2', 'estado_3')
        : ($vista === 'todas' ? array('total', 'estado_0', 'estado_4', 'estado_1', 'estado_2', 'estado_3')
            : ($ambito === 'mias' ? array('total', 'estado_0', 'estado_4', 'estado_1') : array('total', 'estado_0', 'estado_1')));
    $estados_visibles = array();
    foreach ($claves_estado as $clave) { $estados_visibles[$clave] = $labels[$clave]; }
    $grupos = array('estados' => $estados_visibles);
    if ($mostrar_roles) {
        $grupos['roles'] = array_slice($labels, 6, null, true);
    }
    foreach ($grupos as $grupo => $campos) {
        $html .= '<div class="ta-estadisticas-' . $grupo . '" aria-label="'
            . ($grupo === 'estados' ? 'Por estado' : 'Por papel') . '">';
        foreach ($campos as $clave => $label) {
            $html .= '<div class="ta-estadistica ta-estadistica-' . $clave . '"><span>' . $label
                . '</span><b>' . (isset($datos[$clave]) ? (int) $datos[$clave] : 0) . '</b></div>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

function opg_av_v_buscar_modo_vista($vista_uid = 0)
{
    return '<details class="ta-selector-vista">'
        . '<summary><i class="fa-solid fa-eye" aria-hidden="true"></i> Ver otro personaje</summary>'
        . '<div class="ta-selector-vista-panel"><label class="ta-solo-lector" for="ta-buscar-modo-vista">Buscar personaje</label>'
        . '<div class="ta-selector-vista-busqueda">'
        . '<div class="opg-buscar-caja"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>'
        . '<input type="search" id="ta-buscar-modo-vista" name="q" autocomplete="off" placeholder="Buscar por nombre o FID"'
        . ' aria-autocomplete="list" aria-controls="ta-resultados-modo-vista"'
        . ' ' . opg_av_hx('get', OPG_AV_URL, 'buscar_modo_vista', array('modo_vista' => (int) $vista_uid))
        . ' hx-trigger="keyup changed delay:250ms, search" hx-target="#ta-resultados-modo-vista" hx-sync="this:replace"></div>'
        . '<small>Nombre desde 3 letras o FID directo</small>'
        . '<div id="ta-resultados-modo-vista" class="ta-resultados-vista" role="listbox"></div></div></div></details>';
}

function opg_av_v_resultados_modo_vista($fichas, $excluir_uid = 0)
{
    $html = '';
    foreach ($fichas as $ficha) {
        $fid = (int) $ficha['fid'];
        if ($fid <= 0 || $fid === (int) $excluir_uid) {
            continue;
        }
        $nombre = trim((string) $ficha['nombre']);
        $html .= '<a class="opg-resultado ta-resultado-vista" role="option" href="' . opg_av_h(OPG_AV_URL . '?modo_vista=' . $fid) . '">'
            . opg_av_h($nombre !== '' ? $nombre : 'Personaje #' . $fid) . '<span>FID #' . $fid . '</span></a>';
    }
    return $html !== '' ? $html : '<p class="opg-resultado-vacio">No se encontraron personajes.</p>';
}

function opg_av_v_mis_activas($items, $nombre_personaje = '', $reactiva = true)
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
    $atributos = '';
    if ($reactiva) {
        $atributos = ' ' . opg_av_hx('get', OPG_AV_URL, 'mis_activas', array('detalle' => 1))
            . ' hx-trigger="aventuraCreada from:body, aventuraCambiada from:body" hx-target="this" hx-swap="outerHTML"';
    }
    $titulo = $nombre_personaje !== '' ? 'Activas de ' . opg_av_h($nombre_personaje) : 'Activas de mi personaje';
    $html = '<section class="ta-mis-activas" id="ta-mis-activas"' . $atributos . ' aria-live="polite">'
        . '<h3 class="ta-subtitulo">' . $titulo . ' <span>' . (count($items) - count($narradas)) . (OPG_AV_LIMITES_ACTIVOS ? ' / ' . OPG_AV_MAX_TOTAL : '') . ' en total</span></h3>'
        . '<div class="ta-activas-grupos">';
    $grupos['Narrando'] = $narradas;
    foreach ($grupos as $nombre => $avs) {
        $es_narrando = $nombre === 'Narrando';
        if ($es_narrando) {
            $html .= '</div><h3 class="ta-subtitulo ta-narradas-titulo">Aventuras que narro <span>'
                . count($avs) . (count($avs) === 1 ? ' activa' : ' activas') . ' · Sin límite</span></h3><div class="ta-activas-grupos ta-narradas">';
        }
        $html .= '<div class="ta-activas-grupo ta-activas-grupo-' . ($es_narrando ? 'narradas' : strtolower($nombre)) . '">';
        if (!$es_narrando) {
            $html .= '<h4>' . $nombre . ' <span>' . count($avs)
                . (OPG_AV_LIMITES_ACTIVOS && $nombre === 'Solitarias' ? ' / ' . OPG_AV_MAX_SOLITARIAS : '') . '</span></h4>';
        }
        $html .= '<ul>';
        foreach ($avs as $av) {
            $nombre = !empty($av['aventura_nombre']) ? $av['aventura_nombre'] : 'Sin tema vinculado';
            $fecha = opg_av_fecha($av['created_at']);
            $identidad = '#' . (int) $av['id'] . ' · ' . opg_av_h($nombre);
            $tid = isset($av['tid_vinculado']) ? (int) $av['tid_vinculado'] : 0;
            if ($tid > 0 && !empty($av['aventura_nombre'])) {
                $identidad = '<a href="' . opg_av_h(opg_av_url_tema($tid)) . '" target="_blank" rel="noopener">' . $identidad . '</a>';
            } else {
                $identidad = '<span class="ta-activa-titulo">' . $identidad . '</span>';
            }
            $html .= '<li><div class="ta-activa-identidad">' . $identidad
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

/** Experiencia de narrador del mes (tope mensual) con las mismas cifras del indicador de activas. $r viene de opg_rec_exp_narrador_mensual(). */
function opg_av_v_exp_narrador($r)
{
    if (!$r) {
        return '';
    }
    $num = function ($v) { return rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','); };
    $lleno = $r['restante'] <= 0;
    $html = '<div class="ta-activas" id="ta-exp-narrador" aria-live="polite">'
        . '<span class="ta-cifra' . ($lleno ? ' ta-cifra-tope' : '') . '"><b>' . $num($r['exp']) . ' / ' . $num($r['limite']) . '</b> XP de narrador este mes</span>';
    if ($r['nivel'] !== '') {
        $html .= '<span class="ta-cifra">Nivel de narrador <b>' . opg_av_h($r['nivel']) . '</b></span>';
    }
    $html .= '<small class="ta-ayuda">' . ($lleno
            ? 'Has llegado al tope del mes: lo que te corresponda de más se convierte en Kuros (1 por cada 2 de experiencia).'
            : 'Te quedan ' . $num($r['restante']) . ' de experiencia por cobrar narrando este mes; lo que pase del tope se convierte en Kuros (1 por cada 2).')
        . ' Se reinicia el ' . opg_av_h($r['reinicio']) . '.</small></div>';
    return $html;
}

function opg_av_v_panel_mias($filtros, $res, $ctx, $tiene_aventuras, $estadisticas = array(), $activas = array(), $exp_narrador = null)
{
    $filtros['ambito'] = 'mias';
    $autos = !empty($ctx['uid']) ? opg_av_autonarradas_listar((int) $ctx['uid']) : array();
    $auto_html = '';
    if ($autos) {
        $auto_html = '<section class="ta-autonarradas-resumen"><h3 class="ta-subtitulo">Crónicas autonarradas <span>' . count($autos) . '</span></h3><ul>';
        foreach ($autos as $auto) {
            $nombre = $auto['aventura_nombre'] !== '' ? $auto['aventura_nombre'] : 'Sin tema vinculado';
            $identidad = '#' . (int) $auto['id'] . ' · ' . opg_av_h($nombre);
            if ($auto['tid_vinculado'] > 0 && $auto['aventura_nombre'] !== '') {
                $identidad = '<a href="' . opg_av_h(opg_av_url_tema($auto['tid_vinculado'])) . '" target="_blank" rel="noopener">' . $identidad . '</a>';
            }
            $auto_html .= '<li><span>' . $identidad . '<small>Semana del ' . opg_av_h(opg_av_fecha($auto['autonarrada']['semana_inicio']))
                . (!empty($auto['autonarrada']['palabras']) ? ' · ' . (int) $auto['autonarrada']['palabras'] . ' palabras' : '')
                . '</small></span><span class="ta-estado ta-estado-' . opg_av_h($auto['estado_clave']) . '">' . opg_av_h($auto['estado_etiqueta']) . '</span></li>';
        }
        $auto_html .= '</ul></section>';
    }
    $html = opg_av_v_estadisticas($estadisticas, 'mias', false, $filtros) . opg_av_v_mis_activas($activas) . $auto_html . opg_av_v_exp_narrador($exp_narrador)
        . '<section class="ta-seccion" aria-labelledby="ta-t-mias"><h2 class="ta-titulo" id="ta-t-mias">Mis aventuras</h2>';
    if (!$tiene_aventuras) {
        return $html . opg_av_v_sin_aventuras() . '</section>';
    }
    $html .= opg_av_v_densidad('mias', isset($filtros['densidad']) ? $filtros['densidad'] : 'compacta') . opg_av_v_filtros($filtros)
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
        . '<header class="ta-sin-cabecera"><h2 id="ta-t-sin">Aventuras sin narrador</h2><span class="opg-chip ta-sin-cifra" aria-label="' . $n . ' esperando narrador">' . $n . ' esperando narrador</span></header>'
        . '<div class="ta-sin-cuerpo">';
    if (!$sin['items']) {
        $html .= opg_av_v_vacio('Ahora mismo todas las aventuras tienen narrador.');
    } else {
        $html .= '<div class="ta-sin-lista">';
        foreach ($sin['items'] as $av) {
            $html .= opg_av_v_fila_sin_narrador($av, $ctx);
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
        $html .= opg_av_v_fila_sin_narrador($av, $ctx);
    }
    return $html . opg_av_v_ver_mas_sin_narrador($sin);
}

/** Fila de inventario para elegir de un vistazo qué aventura narrar. */
function opg_av_v_fila_sin_narrador($av, $ctx)
{
    $id = (int) $av['id'];
    $titulo = $av['aventura_nombre'] !== '' ? $av['aventura_nombre'] : 'Solicitud #' . $id;
    $titulo_html = opg_av_h($titulo);
    if ($av['tid_vinculado'] > 0) {
        $titulo_html = '<a href="' . opg_av_h(opg_av_url_tema($av['tid_vinculado'])) . '" target="_blank" rel="noopener">' . $titulo_html . '</a>';
    }

    $meta = array();
    if ($av['tier']) {
        $meta[] = '<span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Tier ' . (int) $av['tier'] . '</span>';
    }
    if ($av['isla']) {
        $meta[] = '<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' . opg_av_h($av['isla']['nombre']) . '</span>';
    }
    if ($av['fecha_texto'] !== null) {
        $meta[] = '<span><i class="fa-solid fa-calendar-day" aria-hidden="true"></i> ' . opg_av_h($av['fecha_texto']) . '</span>';
    }

    $resaltada = !empty($ctx['resaltar']) && (int) $ctx['resaltar'] === $id ? ' ta-resaltada' : '';
    $html = '<article class="opg-card ta-sin-fila' . $resaltada . '" id="av-' . $id . '"><div class="ta-sin-contenido">'
        . '<header class="ta-sin-fila-cab"><h3>' . $titulo_html . '</h3><span class="ta-estado ta-estado-pendiente-narrador">Buscando narrador</span></header>'
        . ($meta ? '<div class="ta-sin-meta">' . implode('', $meta) . '</div>' : '')
        . '<dl class="ta-sin-personas"><div><dt>Creador</dt><dd>' . opg_av_v_persona($av['uid'], $av['creador_nombre']) . '</dd></div>'
        . '<div><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div></dl>';
    if ($av['comentario_publico'] !== '') {
        $comentario = opg_av_texto_html($av['comentario_publico']);
        if (opg_av_longitud($av['comentario_publico']) > 180) {
            $control = 'ta-sin-descripcion-' . $id;
            $html .= '<div class="ta-sin-descripcion"><input class="ta-sin-descripcion-control" type="checkbox" id="' . $control . '">'
                . '<p class="ta-sin-comentario"><span>' . $comentario . '</span></p>'
                . '<label for="' . $control . '"><span class="ta-sin-descripcion-mas">Ver más...</span>'
                . '<span class="ta-sin-descripcion-menos">Ver menos</span></label></div>';
        } else {
            $html .= '<p class="ta-sin-comentario"><span>' . $comentario . '</span></p>';
        }
    }
    if ($av['motivo_renuncia'] !== '') {
        $html .= '<p class="ta-sin-aviso"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i> <b>El narrador anterior se salió:</b> '
            . opg_av_texto_html($av['motivo_renuncia']) . '</p>';
    }
    $html .= '</div><div class="ta-sin-accion">';
    $html .= '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('get', OPG_AV_URL, 'form_postular', array('id' => $id))
        . ' hx-target="#ta-modal" hx-swap="innerHTML"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Me interesa narrar</button>';
    return $html . '</div></article>';
}

function opg_av_v_propuestas_narrador($items, $uid)
{
    if (!$items) {
        return '';
    }
    $estados = array('pendiente' => 'Pendiente', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada',
        'retirada' => 'Retirada', 'cancelada' => 'Cancelada', 'vencida' => 'Vencida');
    $tarjeta = function ($p) use ($uid, $estados) {
        $pendiente = $p['estado'] === 'pendiente';
        $recibida = $pendiente && (($p['tipo'] === 'invitacion' && (int) $p['narrador_uid'] === (int) $uid)
            || ($p['tipo'] === 'candidatura' && (int) $p['peticion_uid'] === (int) $uid));
        $html = '<article class="opg-card ta-card"><div><b>#' . (int) $p['peticion_id'] . ' · '
            . ($p['tipo'] === 'invitacion' ? 'Invitación' : 'Candidatura') . '</b><span class="ta-estado ta-estado-'
            . opg_av_h($p['estado']) . '">' . opg_av_h(isset($estados[$p['estado']]) ? $estados[$p['estado']] : $p['estado']) . '</span></div>'
            . '<small>Narrador propuesto: ' . opg_av_v_persona($p['narrador_uid'], isset($p['narrador_usuario']) ? (string) $p['narrador_usuario'] : '') . '</small>'
            . '<small>' . ($pendiente ? 'Vence ' : 'Creada ') . opg_av_h(opg_av_fecha($pendiente ? $p['expires_at'] : $p['created_at'], true)) . '</small>';
        if ($p['mensaje'] !== '') { $html .= '<p>' . opg_av_texto_html($p['mensaje']) . '</p>'; }
        $html .= '<div class="ta-acciones">';
        if ($recibida) {
            $html .= '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('post', OPG_AV_URL, 'aceptar_propuesta', array('propuesta_id' => $p['id'], 'tab' => 'narradores')) . ' hx-target="#ta-panel" hx-swap="innerHTML">Aceptar</button>'
                . '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL, 'rechazar_propuesta', array('propuesta_id' => $p['id'], 'tab' => 'narradores')) . ' hx-target="#ta-panel" hx-swap="innerHTML">Rechazar</button>';
        } elseif ($pendiente && (int) $p['iniciada_por_uid'] === (int) $uid) {
            $html .= '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL, 'retirar_propuesta', array('propuesta_id' => $p['id'], 'tab' => 'narradores')) . ' hx-target="#ta-panel" hx-swap="innerHTML">Retirar</button>';
        }
        return $html . '</div></article>';
    };

    // Lo pendiente (lo que pide acción) a la vista; lo resuelto, plegado como historial.
    $pendientes = array();
    $resueltas = array();
    foreach ($items as $p) {
        if ($p['estado'] === 'pendiente') { $pendientes[] = $p; } else { $resueltas[] = $p; }
    }
    $html = '<section class="ta-seccion ta-propuestas" id="ta-propuestas" aria-labelledby="ta-t-propuestas">';
    if ($pendientes) {
        $html .= '<h2 class="ta-titulo" id="ta-t-propuestas">Propuestas para narrar</h2><div class="ta-propuestas-lista">';
        foreach ($pendientes as $p) { $html .= $tarjeta($p); }
        $html .= '</div>';
    }
    if ($resueltas) {
        $html .= '<details class="ta-propuestas-historial"><summary' . (!$pendientes ? ' id="ta-t-propuestas"' : '') . '>Historial de propuestas <small>' . count($resueltas)
            . (count($resueltas) === 1 ? ' resuelta' : ' resueltas') . '</small></summary><div class="ta-propuestas-lista">';
        foreach ($resueltas as $p) { $html .= $tarjeta($p); }
        $html .= '</div></details>';
    }
    return $html . '</section>';
}

function opg_av_v_historial_usuario($id, $items)
{
    $html = '<ol class="ta-historial-usuario">';
    if (!$items) { $html .= '<li>Sin historial anterior a la migración.</li>'; }
    foreach ($items as $evento) {
        $html .= '<li><b>' . opg_av_h($evento['texto']) . '</b><span>' . opg_av_h($evento['usuario']) . ' · '
            . opg_av_h(opg_av_fecha($evento['fecha'], true)) . '</span></li>';
    }
    return opg_av_v_modal('Historial de la solicitud #' . (int) $id, $html . '</ol><div class="ta-acciones"><button type="button" class="opg-chip" '
        . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML">Cerrar</button></div>');
}

// ── Mercado de narradores ───────────────────────────────────────────────────

/** Etiquetas comparables de disponibilidad, ritmo, tiers, estilo y experiencia. */
/** Estilo narrativo: texto libre, así que se muestra como línea y no como etiqueta. */
function opg_av_v_estilo_narrador($n)
{
    return !empty($n['estilo'])
        ? '<p class="ta-estilo-narrador"><i class="fa-solid fa-masks-theater" aria-hidden="true"></i> <b>Estilo:</b> ' . opg_av_texto_html($n['estilo']) . '</p>' : '';
}

/** "5,6,7,8,10" -> "5 a 8, 10". */
function opg_av_v_tiers_texto($csv)
{
    $tiers = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $csv)))));
    sort($tiers);
    $partes = array();
    $n = count($tiers);
    for ($i = 0; $i < $n; $i++) {
        $ini = $tiers[$i];
        while ($i + 1 < $n && $tiers[$i + 1] === $tiers[$i] + 1) {
            $i++;
        }
        $fin = $tiers[$i];
        $partes[] = $fin - $ini >= 2 ? $ini . ' a ' . $fin : ($fin === $ini ? (string) $ini : $ini . ', ' . $fin);
    }
    return implode(', ', $partes);
}

/**
 * Datos del narrador en líneas discretas: ritmo y tiers, y sus aventuras en curso y finalizadas (sin valorar su disponibilidad).
 * Sustituye a la fila de etiquetas: así pesa menos y se lee de un vistazo.
 */
function opg_av_v_etiquetas_narrador($n, $incluir_disponible = true)
{
    $datos = array();
    $frecuencias = opg_av_frecuencias_narrador();
    if (!empty($n['frecuencia']) && isset($frecuencias[$n['frecuencia']])) {
        $datos[] = '<span><i class="fa-solid fa-clock" aria-hidden="true"></i> ' . opg_av_h($frecuencias[$n['frecuencia']]) . '</span>';
    }
    if (!empty($n['tiers'])) {
        $datos[] = '<span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Tiers ' . opg_av_h(opg_av_v_tiers_texto($n['tiers'])) . '</span>';
    }
    $html = $datos ? '<p class="ta-narrador-datos">' . implode('<span class="ta-sep" aria-hidden="true">·</span>', $datos) . '</p>' : '';
    if (isset($n['en_curso'])) {
        $en_curso = (int) $n['en_curso'];
        $fin = isset($n['finalizadas']) ? (int) $n['finalizadas'] : 0;
        $html .= '<p class="ta-narrador-carga"><small>' . $en_curso . ' en curso · ' . $fin . ($fin === 1 ? ' finalizada' : ' finalizadas') . '</small></p>';
    }
    return $html;
}

/** Tarjeta de un narrador disponible. */
function opg_av_v_tarjeta_narrador($n)
{
    $nombre = $n['nombre'] !== '' ? $n['nombre'] : 'Narrador';
    $html = '<article class="opg-card ta-card ta-narrador" style="--opg-card-acento: var(--opg-verde-exito)">'
        . '<header class="opg-card__cabecera"><span class="opg-card__titulo">' . opg_av_h($nombre) . '</span>'
        . '<span class="ta-estado ta-estado-disponible">Disponible</span></header>'
        . opg_av_v_etiquetas_narrador($n, false) . opg_av_v_estilo_narrador($n);
    if ($n['mensaje'] !== '') {
        $html .= '<p class="opg-card__desc">' . opg_av_texto_html($n['mensaje']) . '</p>';
    }
    $html .= '<footer class="ta-narrador-pie">'
        . '<span class="ta-meta">Desde ' . opg_av_h(opg_av_fecha($n['desde'])) . '</span>'
        . '<p class="ta-discord"><b>Discord:</b> <span class="ta-copiable">' . opg_av_h($n['discord']) . '</span></p>'
        . '</footer>'
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
    $estado = $oferta['vencida'] ? 'Vencida' : 'Vigente · ' . (int) $oferta['dias_restantes'] . ($oferta['dias_restantes'] === 1 ? ' día' : ' días');
    $html .= '<div class="opg-card ta-card ta-mi-oferta-card" style="--opg-card-acento: ' . ($oferta['vencida'] ? 'var(--opg-gris-bloqueado)' : 'var(--opg-verde-exito)') . '">'
        . '<header class="opg-card__cabecera"><span class="opg-card__titulo">Tu oferta</span>'
        . '<span class="ta-estado ' . ($oferta['vencida'] ? 'ta-estado-vencida' : 'ta-estado-aprobado') . '">' . $estado . '</span></header>'
        . '<div class="ta-mi-oferta-resumen"><div class="ta-mi-oferta-info">'
        . '<p class="ta-discord"><b>Discord:</b> <span class="ta-copiable">' . opg_av_h($oferta['discord']) . '</span></p>';
    if ($oferta['mensaje'] !== '') {
        $html .= '<p class="opg-card__desc">' . opg_av_texto_html($oferta['mensaje']) . '</p>';
    }
    $html .= opg_av_v_etiquetas_narrador($oferta, false) . opg_av_v_estilo_narrador($oferta) . '</div>'
        . '<div class="ta-acciones">'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL, 'renovar_oferta') . ' ' . $obj . '><i class="fa-solid fa-rotate" aria-hidden="true"></i> Renovar</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'form_oferta') . ' hx-target="#ta-mi-oferta" hx-swap="innerHTML"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</button>'
        . '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('post', OPG_AV_URL, 'retirar_oferta') . ' ' . $obj . ' hx-confirm="¿Retirar tu oferta de narrador?"><i class="fa-solid fa-trash" aria-hidden="true"></i> Retirar</button>'
        . '</div></div></div></div>';
    return $html;
}

/** Formulario de la oferta: Discord (obligatorio) y mensaje (opcional). */
function opg_av_v_form_oferta($oferta, $errores = array())
{
    $frecuencia = $oferta ? $oferta['frecuencia'] : '';
    $tiers_actuales = $oferta && $oferta['tiers'] !== '' ? array_map('intval', explode(',', $oferta['tiers'])) : array();
    $opciones_frecuencia = '<option value="">Indica tu ritmo habitual</option>';
    foreach (opg_av_frecuencias_narrador() as $valor => $etiqueta) {
        $opciones_frecuencia .= '<option value="' . opg_av_h($valor) . '"' . ($frecuencia === $valor ? ' selected' : '') . '>' . opg_av_h($etiqueta) . '</option>';
    }
    $tiers = '';
    for ($tier = OPG_AV_TIER_MIN; $tier <= OPG_AV_TIER_MAX; $tier++) {
        $tiers .= '<label class="ta-tier-preferido"><input type="checkbox" name="tiers[]" value="' . $tier . '"' . (in_array($tier, $tiers_actuales, true) ? ' checked' : '') . '><span>Tier ' . $tier . '</span></label>';
    }
    return '<form class="opg-card ta-card ta-form" id="ta-form-oferta" ' . opg_av_hx('post', OPG_AV_URL, 'ofrecerme_narrador')
        . ' hx-target="#ta-narradores" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-verde-exito)">'
        . '<span class="opg-card__titulo">Ofrecerme como narrador</span>'
        . '<div class="ta-avisos" id="aviso-oferta" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="ta-oferta-campos">'
        . '<div class="af-field ta-oferta-discord"><label for="ta-discord">Tu usuario de Discord</label>'
        . '<input type="text" id="ta-discord" name="discord" required maxlength="' . OPG_AV_MAX_DISCORD . '" value="' . opg_av_h($oferta ? $oferta['discord'] : '') . '">'
        . '<small>Es como te contactarán quienes busquen narrador.</small></div>'
        . '<div class="af-field ta-oferta-frecuencia"><label for="ta-frecuencia">Frecuencia de publicación</label><select id="ta-frecuencia" name="frecuencia">' . $opciones_frecuencia . '</select></div>'
        . '<div class="af-field ta-oferta-estilo"><label for="ta-estilo">Estilo narrativo</label><input type="text" id="ta-estilo" name="estilo" maxlength="' . OPG_AV_MAX_ESTILO_NARRADOR . '" placeholder="Misterio, combate, exploración..." value="' . opg_av_h($oferta ? $oferta['estilo'] : '') . '"></div>'
        . '<div class="af-field ta-oferta-mensaje"><label for="ta-mensaje-oferta">Mensaje (opcional)</label>'
        . '<textarea id="ta-mensaje-oferta" name="mensaje" maxlength="' . OPG_AV_MAX_MENSAJE_OFERTA . '" placeholder="Qué tipo de aventura prefieres o cuándo estás disponible.">'
        . opg_av_h($oferta ? $oferta['mensaje'] : '') . '</textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_MENSAJE_OFERTA . ' caracteres. Tu oferta vence a los ' . OPG_AV_OFERTA_DIAS . ' días si no la renuevas.</small></div>'
        . '</div><fieldset class="ta-tiers-preferidos"><legend>Tiers preferidos (opcional)</legend><div>' . $tiers . '</div></fieldset>'
        . '<div class="ta-acciones">'
        . '<button type="submit" class="btn-op btn-op--primario">Publicar mi oferta</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'narradores_disponibles') . ' hx-target="#ta-narradores" hx-swap="outerHTML">Cancelar</button>'
        . '</div></form>';
}

/** Bloque completo "Narradores disponibles". */
function opg_av_v_bloque_narradores($oferta_propia, $lista, $tiene_ficha)
{
    $html = '<section class="ta-seccion ta-narradores" id="ta-narradores" aria-labelledby="ta-t-narr" ' . opg_av_hx('get', OPG_AV_URL, 'narradores_disponibles')
        . ' hx-trigger="ofertaCambiada from:body" hx-target="this" hx-swap="outerHTML">'
        . '<header class="ta-narradores-cabecera"><div><h2 class="ta-titulo" id="ta-t-narr">Narradores disponibles</h2>'
        . '<p class="ta-ayuda">Contacto por Discord · Ofertas vigentes durante 30 días</p></div>'
        . '<span class="opg-chip ta-narradores-total">' . (int) $lista['total'] . ((int) $lista['total'] === 1 ? ' disponible' : ' disponibles') . '</span></header>'
        . opg_av_v_mi_oferta($oferta_propia, $tiene_ficha);
    if (!$lista['items']) {
        $html .= opg_av_v_vacio('Nadie se ha ofrecido como narrador todavía.');
    } else {
        $html .= '<div class="ta-tarjetas ta-grilla ta-narradores-lista">';
        foreach ($lista['items'] as $n) {
            $html .= opg_av_v_tarjeta_narrador($n);
        }
        $html .= '</div>' . opg_av_v_paginador($lista, array(), 'narradores_disponibles', OPG_AV_URL, '#ta-narradores', 'outerHTML');
    }
    return $html . '</section>';
}

function opg_av_v_panel_otras($filtros, $res, $ctx, $estadisticas)
{
    $filtros['ambito'] = 'otras';
    return opg_av_v_estadisticas($estadisticas, 'otras', false, $filtros)
        . '<section class="ta-seccion" aria-labelledby="ta-t-otras"><h2 class="ta-titulo" id="ta-t-otras">Otras aventuras</h2>'
        . opg_av_v_densidad('otras', isset($filtros['densidad']) ? $filtros['densidad'] : 'compacta') . opg_av_v_filtros($filtros)
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
        . '<button type="button" class="ta-velo-cerrar" tabindex="-1" aria-label="Cerrar" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML"></button>'
        . '<div class="ta-modal" role="dialog" aria-modal="true" aria-labelledby="ta-modal-titulo" tabindex="-1">'
        . '<h3 id="ta-modal-titulo">' . opg_av_h($titulo) . '</h3>' . $cuerpo . '</div></div>';
}

/** Ventana para crear una candidatura de narrador. */
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
        . '<div class="af-field"><label for="ta-mensaje-candidatura">Mensaje para el creador (opcional)</label><textarea id="ta-mensaje-candidatura" name="mensaje" maxlength="' . OPG_AV_MAX_IDEA . '" placeholder="Cuéntale cómo te gustaría narrar esta aventura y cuál es tu disponibilidad."></textarea></div>'
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Enviar candidatura</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'cerrar_modal') . ' hx-target="#ta-modal" hx-swap="innerHTML">Cancelar</button></div></form>';
    return opg_av_v_modal('Me interesa narrar', '<p>El creador recibirá tu candidatura y decidirá si te asigna como narrador.</p>' . $cuerpo);
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
        '<p>La aventura #' . (int) $id . ' volverá a quedar sin narrador. Los participantes recibirán una notificación.</p>' . $cuerpo);
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
    $verbo = $av['tid_vinculado'] > 0 ? 'Revincular' : 'Vincular';
    return '<form class="opg-card ta-card ta-form" id="av-' . $id . '" ' . opg_av_hx('post', OPG_AV_URL, 'vincular_aventura', array('id' => $id))
        . ' hx-target="#av-' . $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-verde-exito)">'
        . '<span class="opg-card__titulo">' . $verbo . ' la aventura #' . (int) $id . '</span>'
        . '<div class="ta-avisos" id="aviso-form-' . $id . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . opg_av_v_campo_tema($id, $tema)
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">' . $verbo . '</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $id)) . ' hx-target="#av-' . $id . '" hx-swap="outerHTML">Cancelar</button></div></form>';
}

/** Formulario para rellenar la isla y el día que faltan en una solicitud antigua. */
function opg_av_v_form_completar($av, $errores = array())
{
    $id = $av['id'];
    $faltan = opg_av_datos_faltantes($av);
    $html = '<form class="opg-card ta-card ta-form" id="av-' . $id . '" ' . opg_av_hx('post', OPG_AV_URL, 'completar', array('id' => $id))
        . ' hx-target="#av-' . $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-naranja)">'
        . '<span class="opg-card__titulo">Completar la aventura #' . (int) $id . '</span>'
        . '<div class="ta-avisos" id="aviso-form-' . $id . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<p class="ta-ayuda">Esta solicitud es antigua y le faltan datos. Rellénalos para poder entregar la aventura; lo que ya está escrito no se cambia.</p><div class="ta-fila">';
    if (isset($faltan['isla'])) {
        $html .= opg_av_v_campo_isla(null, $id);
    }
    if (isset($faltan['dia'])) {
        $html .= '<div class="af-field"><label for="ta-dia-' . $id . '">Día (' . opg_av_h(OPG_AV_ESTACION . ' ' . OPG_AV_ANO) . ')</label>'
            . '<input type="number" id="ta-dia-' . $id . '" name="dia" min="1" max="' . OPG_AV_DIAS . '" placeholder="1-90" required></div>';
    }
    return $html . '</div><div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Guardar</button>'
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
        . '<textarea id="ta-resumen-' . $id . '" name="resumen" required maxlength="' . OPG_ENT_MAX_RESUMEN . '" placeholder="Cuenta cómo terminó la aventura (hasta ' . OPG_ENT_MAX_RESUMEN . ' caracteres). Además del resumen, indica detalles que sean relevantes saber. Por ejemplo, si posteó más de un narrador, quién debería llevarse la recompensa. El staff lo revisará.">' . opg_av_h($resumen) . '</textarea></div>'
        . '<fieldset class="ta-grupo"><legend>Reputación de la aventura</legend>'
        . '<input class="ta-radio" type="radio" name="reputacion" id="ta-rep-p-' . $id . '" value="positiva"' . ($rep === 'positiva' ? ' checked' : '') . ' required>'
        . '<label class="opg-chip ta-etiqueta-radio" for="ta-rep-p-' . $id . '">Positiva</label>'
        . '<input class="ta-radio" type="radio" name="reputacion" id="ta-rep-n-' . $id . '" value="negativa"' . ($rep === 'negativa' ? ' checked' : '') . '>'
        . '<label class="opg-chip ta-etiqueta-radio" for="ta-rep-n-' . $id . '">Negativa</label></fieldset>'
        . '<label class="ta-casilla"><input type="checkbox" name="confirmar_grupo" id="ta-confirmar-grupo-' . $id . '" value="1" required' . (!empty($entrada['confirmar_grupo']) ? ' checked' : '') . '> '
        . 'He confirmado con el resto del grupo (jugadores y narrador) que entregamos esta aventura.</label>'
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Enviar entrega</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $id)) . ' hx-target="#av-' . $id . '" hx-swap="outerHTML">Cancelar</button></div></form>';
    return $html;
}

/** Formulario reducido: el autor solo declara el signo de la reputacion. */
function opg_av_v_form_entrega_autonarrada($av, $errores = array(), $entrada = array())
{
    $id = (int) $av['id'];
    $rep = isset($entrada['reputacion']) ? (string) $entrada['reputacion'] : '';
    return '<form class="opg-card ta-card ta-form" id="av-' . $id . '" '
        . opg_av_hx('post', OPG_AV_URL, 'enviar_entrega', array('id' => $id))
        . ' hx-target="#av-' . $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]" style="--opg-card-acento: var(--opg-naranja)">'
        . '<span class="opg-card__titulo">Entregar la crónica autonarrada #' . $id . '</span>'
        . '<div class="ta-avisos" id="aviso-form-' . $id . '" aria-live="polite">'
        . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<p>El Staff comprobará que el tema tenga un único post propio de al menos ' . OPG_AV_AUTO_PALABRAS_MIN
        . ' palabras. La recompensa fija es de ' . OPG_AV_AUTO_XP . ' XP, ' . OPG_AV_AUTO_NIKAS . ' Nikas, '
        . OPG_AV_AUTO_REPUTACION . ' de reputación y ' . number_format(OPG_AV_AUTO_BERRIES, 0, ',', '.') . ' Berries.</p>'
        . '<fieldset class="ta-grupo"><legend>Reputación de la crónica</legend>'
        . '<input class="ta-radio" type="radio" name="reputacion" id="ta-rep-p-' . $id . '" value="positiva"'
        . ($rep === 'positiva' ? ' checked' : '') . ' required>'
        . '<label class="opg-chip ta-etiqueta-radio" for="ta-rep-p-' . $id . '">Positiva</label>'
        . '<input class="ta-radio" type="radio" name="reputacion" id="ta-rep-n-' . $id . '" value="negativa"'
        . ($rep === 'negativa' ? ' checked' : '') . '>'
        . '<label class="opg-chip ta-etiqueta-radio" for="ta-rep-n-' . $id . '">Negativa</label></fieldset>'
        . '<div class="ta-acciones"><button type="submit" class="btn-op btn-op--primario">Enviar al Staff</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL, 'ver', array('id' => $id))
        . ' hx-target="#av-' . $id . '" hx-swap="outerHTML">Cancelar</button></div></form>';
}

/** Resultados del buscador de aventuras de newthread.php (el JS lee data-aventura y data-etiqueta). */
function opg_av_v_resultados_aventuras($items)
{
    if (!$items) {
        return '<div class="opg-resultado-vacio">No tienes solicitudes de aventura. Puedes crear el tema igualmente.</div>';
    }
    $html = '';
    foreach ($items as $av) {
        $nombres = array();
        foreach ($av['jugadores'] as $j) {
            $nombres[] = $j['nombre'];
        }
        $etiqueta = '#' . (int) $av['id'] . ' · ' . ($av['isla'] ? $av['isla']['nombre'] : 'Sin isla')
            . ($av['fecha_texto'] !== null ? ' · ' . $av['fecha_texto'] : '') . ($av['tier'] ? ' · Tier ' . (int) $av['tier'] : '')
            . ($nombres ? ' · ' . implode(', ', $nombres) : '');
        if (opg_av_es_autonarrada($av)) {
            $etiqueta = '#' . (int) $av['id'] . ' · Crónica autonarrada · ' . ($av['isla'] ? $av['isla']['nombre'] : 'Sin isla')
                . ($av['fecha_texto'] !== null ? ' · ' . $av['fecha_texto'] : '');
        } elseif ((int) $av['estado'] !== 1) {
            // Se puede vincular sin estar aprobada: se avisa del estado para que no haya dudas.
            $etiqueta .= ' · ' . $av['estado_etiqueta'];
        }
        if (!opg_av_es_autonarrada($av) && $av['tid_vinculado'] > 0) {
            $etiqueta .= ' · ya tiene tema (se revinculará)';
        }
        if (function_exists('mb_strlen') && mb_strlen($etiqueta, 'UTF-8') > 160) {
            $etiqueta = mb_substr($etiqueta, 0, 159, 'UTF-8') . '…';
        }
        $html .= '<button type="button" class="opg-resultado" data-aventura="' . (int) $av['id'] . '" data-etiqueta="' . opg_av_h($etiqueta) . '">' . opg_av_h($etiqueta) . '</button>';
    }
    return $html;
}
