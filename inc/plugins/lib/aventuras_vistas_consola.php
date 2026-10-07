<?php
/**
 * Tablón de aventuras: fragmentos HTML de la consola de gestión
 * (op/staff/consola_aventuras.php) y de la página de recompensas
 * (op/staff/aventuras_recompensas.php).
 *
 * Mismas reglas que aventuras_vistas.php: todo texto de usuario pasa por opg_av_h() y
 * no se usa hx-on ni nada que requiera eval. Requiere aventuras.php,
 * aventuras_entregas.php, recompensas_aventura.php y aventuras_vistas.php.
 */

// ── Pestañas, filtros y paginación de la consola ────────────────────────────

/** Contador de entregas pendientes junto a la pestaña Entregas. */
function opg_av_v_c_contador($n)
{
    return '<span class="opg-chip ca-contador' . ($n > 0 ? '' : ' ca-oculto') . '" id="ca-contador" aria-label="' . (int) $n . ' entregas pendientes">' . (int) $n . '</span>';
}

function opg_av_v_c_tabs($activa, $n_pendientes, $oob = false)
{
    $tabs = array('solicitudes' => 'Solicitudes', 'entregas' => 'Entregas', 'narradores' => 'Narradores disponibles');
    $html = '<nav class="ca-tabs" id="ca-tabs" aria-label="Secciones de la consola"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
    foreach ($tabs as $clave => $titulo) {
        $href = OPG_AV_URL_CONSOLA . '?tab=' . $clave;
        $html .= '<a class="ca-tab' . ($activa === $clave ? ' ca-tab-activa' : '') . '" href="' . opg_av_h($href) . '" '
            . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'tab', array('tab' => $clave)) . ' hx-target="#ca-panel" hx-swap="innerHTML"'
            . ' hx-push-url="' . opg_av_h($href) . '"' . ($activa === $clave ? ' aria-current="page"' : '') . '>' . opg_av_h($titulo)
            . ($clave === 'entregas' ? ' ' . opg_av_v_c_contador($n_pendientes) : '') . '</a>';
    }
    return $html . '</nav>';
}

/** Chips de estado con su conteo. $opciones: valor => etiqueta; $conteos: valor => n. */
function opg_av_v_c_grupo($titulo, $nombre, $opciones, $conteos, $actual)
{
    $html = '<fieldset class="ca-grupo"><legend>' . opg_av_h($titulo) . '</legend>';
    foreach ($opciones as $valor => $etiqueta) {
        $n = isset($conteos[$valor]) ? ' (' . (int) $conteos[$valor] . ')' : '';
        $html .= '<label class="ca-filtro"><input class="ca-radio" type="radio" name="' . opg_av_h($nombre) . '" value="' . opg_av_h($valor) . '"'
            . ((string) $actual === (string) $valor ? ' checked' : '') . '><span class="opg-chip">' . opg_av_h($etiqueta . $n) . '</span></label>';
    }
    return $html . '</fieldset>';
}

/** Filtros de la lista de solicitudes: estado con conteos y UID. Se abre en "Creadas". */
function opg_av_v_c_filtros($filtros, $conteos_estado)
{
    $estado = isset($filtros['estado']) ? $filtros['estado'] : 'creado';
    $opciones = array('todas' => 'Todas', 'creado' => 'Creadas', 'pendiente-narrador' => 'Pendientes de narrador', 'aprobado' => 'Aprobadas',
        'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas', 'pendiente-borrado' => 'Pendientes de borrado');
    $conteos = array('todas' => array_sum($conteos_estado));
    foreach (opg_av_estados() as $codigo => $info) {
        $conteos[$info['clave']] = $conteos_estado[$codigo];
    }
    return '<form class="ca-filtros" id="ca-filtros" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'listar')
        . ' hx-trigger="change, submit, solicitudCambiada from:body" hx-target="#ca-lista" hx-swap="innerHTML">'
        . opg_av_v_c_grupo('Estado', 'estado', $opciones, $conteos, $estado)
        . '<div class="af-field ca-uid"><label for="ca-uid">Buscar por UID</label><input type="number" id="ca-uid" name="uid" min="1" placeholder="Narrador o participante" value="'
        . opg_av_h(isset($filtros['uid']) ? $filtros['uid'] : '') . '"></div>'
        . '<button type="submit" class="opg-chip">Buscar</button></form>';
}

// ── Solicitudes ──────────────────────────────────────────────────────────────

/** Una fila de la consola. $ctx: uid (quien gestiona), puede_eliminar. */
function opg_av_v_c_fila($av, $ctx)
{
    $uid = (int) $ctx['uid'];
    $id = $av['id'];
    $puede_resolver = opg_av_puede_resolver($av, $uid);
    $entrega = $av['entrega'];
    $estado = $av['estado'];

    $html = '<div class="ca-fila" id="fila-' . $id . '">';
    $html .= '<div class="ca-fila-cab"><b class="ca-id">#' . $id . '</b>'
        . '<span class="ca-isla">' . opg_av_h($av['isla'] ? $av['isla']['nombre'] : 'Sin isla') . '</span>'
        . '<span class="ca-dia">' . opg_av_h($av['fecha_texto'] !== null ? $av['fecha_texto'] : 'Sin día') . '</span>'
        . ($av['tier'] ? '<span class="ca-tier">Tier ' . (int) $av['tier'] . '</span>' : '')
        . ($av['inframundo'] ? '<span class="opg-chip ca-chip-pequeno">Inframundo</span>' : '')
        . '<span class="ta-estado ta-estado-' . opg_av_h($av['estado_clave']) . '">' . opg_av_h($av['estado_etiqueta']) . '</span>'
        . ($av['editado_at'] ? '<span class="opg-chip ca-chip-pequeno ca-editada">Editada el ' . opg_av_h(opg_av_fecha($av['editado_at'])) . '</span>' : '')
        . '</div>';
    $html .= '<dl class="ca-datos"><div><dt>Creador</dt><dd>' . opg_av_h($av['creador_nombre']) . ' (' . (int) $av['uid'] . ')</dd></div>'
        . '<div><dt>Narrador</dt><dd>' . ($av['narrador_uid'] > 0 ? opg_av_h($av['narrador_nombre']) . ' (' . (int) $av['narrador_uid'] . ')' : '<i>Sin narrador</i>') . '</dd></div>'
        . '<div class="ca-dato-ancho"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div></dl>';
    foreach (array(
        array('Comentario público', $av['comentario_publico'], ''),
        array('Nota de staff', isset($av['nota_staff']) ? $av['nota_staff'] : '', 'ca-nota-staff'),
        array('Idea del creador', $av['idea_narrador'], ''),
        array('El narrador anterior se salió', $av['motivo_renuncia'], ''),
        array('Motivo de la denegación', $estado === 2 ? $av['motivo_denegacion'] : '', 'ca-nota-error'),
    ) as $n) {
        if ($n[1] !== '') {
            $html .= opg_av_v_bloque_texto($n[0], $n[1], $n[2]);
        }
    }
    if ($av['tid_vinculado'] > 0) {
        $html .= '<p class="ta-enlace"><a href="' . opg_av_h(opg_av_url_tema($av['tid_vinculado'])) . '" target="_blank" rel="noopener">Tema de la aventura &rarr;</a></p>';
    }
    if ($entrega) {
        $texto = $entrega['estado'] === 'pendiente' ? 'Entrega pendiente de revisión'
            : ($entrega['estado'] === 'rechazada' ? 'Entrega rechazada: ' . $entrega['motivo_rechazo'] : 'Recompensas entregadas el ' . opg_av_fecha($entrega['entregado_at']));
        $html .= '<p class="ca-entrega"><span class="opg-chip">' . opg_av_h($texto) . '</span></p>';
    }
    $html .= '<div class="ta-avisos" id="aviso-' . $id . '" aria-live="polite"></div>';

    // Acciones
    $acc = '';
    $fila = 'hx-target="#fila-' . $id . '" hx-swap="outerHTML"';
    $bloqueada = ' disabled aria-disabled="true"';
    if ($estado === 0) {
        $acc .= '<button type="button" class="opg-chip"' . ($puede_resolver ? ' ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'cambiar_estado', array('id' => $id, 'a' => 1)) . ' ' . $fila : $bloqueada) . '>Aprobar</button>';
    }
    if (in_array($estado, array(0, 1, 4), true)) {
        $acc .= '<button type="button" class="opg-chip chip-peligro"' . ($puede_resolver ? ' ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_denegar', array('id' => $id)) . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML"' : $bloqueada) . '>Denegar</button>';
    }
    if ($estado === 1 && (!$entrega || $entrega['estado'] !== 'pendiente')) {
        $acc .= '<button type="button" class="opg-chip"' . ($puede_resolver ? ' ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'cambiar_estado', array('id' => $id, 'a' => 3)) . ' ' . $fila . ' hx-confirm="¿Finalizar esta aventura sin recompensas? Si el narrador la va a entregar, espera a su entrega."' : $bloqueada) . '>Finalizar</button>';
    }
    $acc .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_editar', array('id' => $id)) . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML">Editar datos</button>';
    $acc .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'historial', array('id' => $id)) . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML">Historial</button>';
    if ($estado === 5 && !empty($ctx['puede_eliminar'])) {
        $acc .= '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'eliminar', array('id' => $id)) . ' ' . $fila . ' hx-confirm="¿Eliminar definitivamente esta solicitud? No se puede deshacer.">Eliminar</button>';
    }
    $html .= '<div class="ca-acciones">' . $acc . '</div>';
    if (!$puede_resolver && opg_av_puede_gestionar($uid) && in_array($estado, array(0, 1, 4), true)) {
        $html .= '<p class="ta-ayuda">No puedes resolver una aventura en la que participas.</p>';
    }
    return $html . '<div class="ca-extra" id="extra-' . $id . '"></div></div>';
}

/** Fila marcada como actualización fuera de banda. */
function opg_av_v_c_lista($res, $filtros, $ctx)
{
    if (!$res['items']) {
        return opg_av_v_vacio('No hay solicitudes con estos filtros.');
    }
    $html = '<div class="ca-filas">';
    foreach ($res['items'] as $av) {
        $html .= opg_av_v_c_fila($av, $ctx);
    }
    return $html . '</div>' . opg_av_v_paginador($res, array('estado' => $filtros['estado'], 'uid' => isset($filtros['uid']) ? $filtros['uid'] : ''), 'listar', OPG_AV_URL_CONSOLA, '#ca-lista');
}

function opg_av_v_c_panel_solicitudes($filtros, $res, $conteos, $ctx)
{
    return '<section class="ca-seccion" aria-labelledby="ca-t-sol"><h2 class="ca-titulo" id="ca-t-sol">Solicitudes</h2>'
        . opg_av_v_c_filtros($filtros, $conteos)
        . '<div class="ca-lista" id="ca-lista" aria-live="polite">' . opg_av_v_c_lista($res, $filtros, $ctx) . '</div></section>';
}

/** Formulario del motivo de una denegación (dentro de la fila). */
function opg_av_v_c_form_denegar($id, $errores = array())
{
    return '<form class="ca-form" ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'cambiar_estado', array('id' => (int) $id, 'a' => 2))
        . ' hx-target="#fila-' . (int) $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]">'
        . '<div class="ta-avisos" id="aviso-form-' . (int) $id . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="af-field"><label for="ca-motivo-' . (int) $id . '">Motivo de la denegación (obligatorio)</label>'
        . '<textarea id="ca-motivo-' . (int) $id . '" name="motivo" required maxlength="' . OPG_AV_MAX_MOTIVO . '" placeholder="Explica por qué se deniega: el usuario lo verá y podrá enviar otra solicitud."></textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_MOTIVO . ' caracteres.</small></div>'
        . '<div class="ca-acciones"><button type="submit" class="btn-op btn-op--sm btn-op--peligro">Confirmar denegación</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar') . ' hx-target="#extra-' . (int) $id . '" hx-swap="innerHTML">Cancelar</button></div></form>';
}

/** Panel de edición de datos de la fila: nota, comentario, inframundo, isla y día. */
function opg_av_v_c_form_editar($av, $errores = array())
{
    $id = $av['id'];
    return '<form class="ca-form" ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'actualizar_datos', array('id' => $id))
        . ' hx-target="#fila-' . $id . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]">'
        . '<div class="ta-avisos" id="aviso-form-' . $id . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="ca-fila-campos">'
        . '<div class="af-field af-field--ancho-completo"><label for="ca-nota-' . $id . '">Nota de staff</label>'
        . '<textarea id="ca-nota-' . $id . '" name="nota_staff" maxlength="' . OPG_AV_MAX_NOTA_STAFF . '">' . opg_av_h(isset($av['nota_staff']) ? $av['nota_staff'] : '') . '</textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_NOTA_STAFF . ' caracteres. Solo la ve el staff.</small></div>'
        . '<div class="af-field af-field--ancho-completo"><label for="ca-com-' . $id . '">Comentario público</label>'
        . '<textarea id="ca-com-' . $id . '" name="comentario" maxlength="' . OPG_AV_MAX_COMENTARIO . '">' . opg_av_h($av['comentario_publico']) . '</textarea></div>'
        . '<div class="ta-isla"><span class="ta-rotulo">Isla</span><div class="ta-chips" id="ta-isla-slot-' . $id . '">' . opg_av_v_c_slot_isla($av['isla'], $id) . '</div>'
        . opg_av_v_c_buscador_isla($id) . '</div>'
        . '<div class="af-field"><label for="ca-dia-' . $id . '">Día (' . opg_av_h(OPG_AV_ESTACION . ' ' . OPG_AV_ANO) . ')</label>'
        . '<input type="number" id="ca-dia-' . $id . '" name="dia" min="1" max="' . OPG_AV_DIAS . '" value="' . opg_av_h($av['dia'] !== null ? $av['dia'] : '') . '"></div>'
        . '</div>'
        . '<label class="ta-casilla"><input type="hidden" name="inframundo" value="0"><input type="checkbox" name="inframundo" value="1"' . ($av['inframundo'] ? ' checked' : '') . '> Requerimiento de inframundo</label>'
        . '<div class="ca-acciones"><button type="submit" class="btn-op btn-op--primario">Guardar</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar') . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML">Cancelar</button></div></form>';
}

/** Isla elegida en el formulario de edición (la × vacía el hueco con una acción de la consola). */
function opg_av_v_c_slot_isla($isla, $id)
{
    if (!$isla) {
        return '';
    }
    return '<span class="opg-chip ta-chip"><input type="hidden" name="isla_fid" value="' . (int) $isla['fid'] . '">' . opg_av_h($isla['nombre'])
        . ' <button type="button" class="ta-quitar" aria-label="Cambiar la isla" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar')
        . ' hx-target="#ta-isla-slot-' . (int) $id . '" hx-swap="innerHTML">&times;</button></span>';
}

/** Buscador de isla de la consola (usa los endpoints propios de la consola). */
function opg_av_v_c_buscador_isla($id)
{
    $res = 'ta-res-isla-' . (int) $id;
    return '<div class="af-field ta-buscador" id="ta-buscar-isla-' . (int) $id . '-campo"><label for="ta-buscar-isla-' . (int) $id . '">Cambiar la isla</label>'
        . '<span class="opg-buscar-caja"><input type="search" id="ta-buscar-isla-' . (int) $id . '" name="q" autocomplete="off" placeholder="Escribe el nombre de la isla" aria-controls="' . $res . '" '
        . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'buscar_islas', array('id' => (int) $id))
        . ' hx-trigger="input changed delay:250ms, search" hx-target="#' . $res . '" hx-swap="innerHTML" hx-sync="this:replace">'
        . '<div class="opg-resultados" id="' . $res . '" aria-live="polite"></div></span></div>';
}

function opg_av_v_c_resultados_islas($islas, $id)
{
    if (!$islas) {
        return '<div class="opg-resultado-vacio">No hay islas con ese nombre.</div>';
    }
    $html = '';
    foreach ($islas as $i) {
        $html .= '<button type="button" class="opg-resultado" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'elegir_isla', array('fid' => (int) $i['fid'], 'id' => (int) $id))
            . ' hx-target="#ta-isla-slot-' . (int) $id . '" hx-swap="innerHTML">' . opg_av_h($i['nombre']) . '</button>';
    }
    return $html;
}

/** Texto del resumen de un cambio de la auditoría. */
function opg_av_v_c_resumen_cambio($antes, $despues)
{
    $partes = array();
    $claves = array();
    foreach (array((array) $antes, (array) $despues) as $arr) {
        foreach (array_keys($arr) as $k) {
            $claves[$k] = true;
        }
    }
    foreach (array_keys($claves) as $k) {
        $a = is_array($antes) && array_key_exists($k, $antes) ? $antes[$k] : null;
        $d = is_array($despues) && array_key_exists($k, $despues) ? $despues[$k] : null;
        $fmt = function ($v) {
            if ($v === null) { return 'vacío'; }
            if (is_bool($v)) { return $v ? 'sí' : 'no'; }
            if (is_array($v)) { return json_encode($v, JSON_UNESCAPED_UNICODE); }
            $t = (string) $v;
            return opg_av_longitud($t) > 200 ? mb_substr($t, 0, 200, 'UTF-8') . '…' : $t;
        };
        $partes[] = opg_av_h($k) . ': ' . opg_av_h($fmt($a)) . ' &rarr; ' . opg_av_h($fmt($d));
    }
    return implode('; ', $partes);
}

/** Historial de una solicitud (auditoría), dentro de la fila. */
function opg_av_v_c_historial($filas, $id)
{
    $nombres = array(
        'crear' => 'Creó la solicitud', 'editar' => 'La editó el creador', 'postular_narrador' => 'Un narrador se postuló',
        'renunciar_narrador' => 'El narrador se salió', 'cambiar_estado' => 'Cambió de estado', 'vincular' => 'Vinculó el tema',
        'pedir_borrado' => 'Pidió el borrado', 'actualizar_datos' => 'El staff actualizó datos', 'enviar_entrega' => 'Envió la entrega',
        'rechazar_entrega' => 'Rechazó la entrega', 'pagar_entrega' => 'Pagó las recompensas', 'xp_dudosa' => 'Resolvió una experiencia dudosa', 'eliminar' => 'Eliminó la solicitud',
    );
    $html = '<div class="ca-historial"><div class="ca-historial-cab"><b>Historial de la solicitud #' . (int) $id . '</b>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar') . ' hx-target="#extra-' . (int) $id . '" hx-swap="innerHTML">Cerrar</button></div>';
    if (!$filas) {
        return $html . opg_av_v_vacio('Sin historial anterior a la migración.') . '</div>';
    }
    $html .= '<ul class="ca-historial-lista">';
    foreach ($filas as $f) {
        $accion = isset($nombres[$f['accion']]) ? $nombres[$f['accion']] : $f['accion'];
        $html .= '<li><span class="ca-hist-fecha">' . opg_av_h(opg_av_fecha($f['fecha'], true)) . '</span> '
            . '<b>' . opg_av_h($f['usuario'] !== '' ? $f['usuario'] : 'Usuario ' . $f['uid']) . '</b>: ' . opg_av_h($accion);
        $detalle = opg_av_v_c_resumen_cambio($f['antes'], $f['despues']);
        if ($detalle !== '') {
            $html .= '<br><small>' . $detalle . '</small>';
        }
        $html .= '</li>';
    }
    return $html . '</ul></div>';
}

// ── Entregas ─────────────────────────────────────────────────────────────────

function opg_av_v_c_filtros_entregas($estado, $conteos)
{
    $opciones = array('pendiente' => 'Pendientes', 'rechazada' => 'Rechazadas', 'entregada' => 'Entregadas');
    $html = '<form class="ca-filtros" id="ca-filtros-entregas" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'entregas')
        . ' hx-trigger="change, submit" hx-target="#ca-lista-entregas" hx-swap="innerHTML">'
        . opg_av_v_c_grupo('Estado de la entrega', 'estado', $opciones, $conteos, $estado) . '</form>';
    return $html;
}

/** Fila de una entrega: resumen y enlace a la página de recompensas. */
function opg_av_v_c_fila_entrega($it)
{
    $e = $it['entrega'];
    $av = $it['av'];
    $html = '<div class="ca-fila" id="entrega-' . $e['id'] . '"><div class="ca-fila-cab"><b class="ca-id">#' . (int) $e['peticion_id'] . '</b>'
        . '<span class="ca-isla">' . opg_av_h($e['tema_nombre']) . '</span>'
        . ($av && $av['tier'] ? '<span class="ca-tier">Tier ' . (int) $av['tier'] . '</span>' : '')
        . ($av && $av['inframundo'] ? '<span class="opg-chip ca-chip-pequeno">Inframundo</span>' : '')
        . ($e['intentos'] > 1 ? '<span class="opg-chip ca-chip-pequeno">Intento ' . (int) $e['intentos'] . '</span>' : '')
        . '<span class="ta-estado">' . opg_av_h(ucfirst($e['estado'])) . '</span></div>';
    if ($av) {
        $html .= '<dl class="ca-datos"><div><dt>Narrador</dt><dd>' . opg_av_h($av['narrador_nombre']) . '</dd></div>'
            . '<div class="ca-dato-ancho"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div></dl>';
    }
    $html .= '<p class="ta-enlace"><a href="' . opg_av_h(opg_av_url_tema($e['tid'])) . '" target="_blank" rel="noopener">Ver el tema &rarr;</a> · Enviada el ' . opg_av_h(opg_av_fecha($e['enviado_at'])) . '</p>';
    if ($e['estado'] === 'rechazada' && $e['motivo_rechazo'] !== '') {
        $html .= opg_av_v_bloque_texto('Motivo del rechazo', $e['motivo_rechazo'], 'ca-nota-error');
    }
    $href = OPG_AV_URL_RECOMPENSAS . '?id=' . (int) $e['id'];
    $texto = $e['estado'] === 'pendiente' ? 'Revisar y pagar' : 'Ver detalle';
    return $html . '<div class="ca-acciones"><a class="opg-chip" href="' . opg_av_h($href) . '">' . $texto . '</a></div></div>';
}

function opg_av_v_c_lista_entregas($res, $estado)
{
    if (!$res['items']) {
        return opg_av_v_vacio($estado === 'pendiente' ? 'No hay entregas pendientes.' : 'No hay entregas en este estado.');
    }
    $html = '<div class="ca-filas">';
    foreach ($res['items'] as $it) {
        $html .= opg_av_v_c_fila_entrega($it);
    }
    return $html . '</div>' . opg_av_v_paginador($res, array('estado' => $estado), 'entregas', OPG_AV_URL_CONSOLA, '#ca-lista-entregas');
}

function opg_av_v_c_panel_entregas($estado, $res, $conteos)
{
    return '<section class="ca-seccion" aria-labelledby="ca-t-ent"><h2 class="ca-titulo" id="ca-t-ent">Entregas</h2>'
        . opg_av_v_c_filtros_entregas($estado, $conteos)
        . '<div class="ca-lista" id="ca-lista-entregas" aria-live="polite">' . opg_av_v_c_lista_entregas($res, $estado) . '</div></section>';
}

// ── Narradores disponibles ──────────────────────────────────────────────────

function opg_av_v_c_fila_oferta($o)
{
    $uid = (int) $o['uid'];
    return '<div class="ca-fila" id="oferta-' . $uid . '"><div class="ca-fila-cab"><b>' . opg_av_h($o['nombre'] !== '' ? $o['nombre'] : 'Usuario ' . $uid) . '</b> (' . $uid . ')'
        . ($o['vencida'] ? '<span class="ta-estado ta-estado-vencida">Vencida</span>' : '<span class="ta-estado ta-estado-aprobado">Vigente</span>') . '</div>'
        . '<p class="ta-discord"><b>Discord:</b> <span class="ta-copiable">' . opg_av_h($o['discord']) . '</span></p>'
        . ($o['mensaje'] !== '' ? opg_av_v_bloque_texto('Mensaje', $o['mensaje']) : '')
        . '<p class="ta-meta">Ofrecida el ' . opg_av_h(opg_av_fecha($o['desde'])) . ' · Renovada el ' . opg_av_h(opg_av_fecha($o['renovada'])) . '</p>'
        . '<div class="ta-avisos" id="aviso-oferta-' . $uid . '" aria-live="polite"></div>'
        . '<div class="ca-acciones"><button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_retirar_oferta', array('uid' => $uid))
        . ' hx-target="#extra-oferta-' . $uid . '" hx-swap="innerHTML">Retirar oferta</button></div>'
        . '<div class="ca-extra" id="extra-oferta-' . $uid . '"></div></div>';
}

function opg_av_v_c_form_retirar_oferta($uid, $errores = array())
{
    return '<form class="ca-form" ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'retirar_oferta', array('uid' => (int) $uid))
        . ' hx-target="#oferta-' . (int) $uid . '" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]">'
        . '<div class="ta-avisos" id="aviso-form-oferta-' . (int) $uid . '" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="af-field"><label for="ca-motivo-oferta-' . (int) $uid . '">Motivo (obligatorio)</label>'
        . '<textarea id="ca-motivo-oferta-' . (int) $uid . '" name="motivo" required maxlength="' . OPG_AV_MAX_MOTIVO_OFERTA . '" placeholder="Por qué se retira la oferta."></textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_MOTIVO_OFERTA . ' caracteres. Queda en la auditoría; el usuario puede volver a ofrecerse.</small></div>'
        . '<div class="ca-acciones"><button type="submit" class="btn-op btn-op--sm btn-op--peligro">Confirmar retirada</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar') . ' hx-target="#extra-oferta-' . (int) $uid . '" hx-swap="innerHTML">Cancelar</button></div></form>';
}

function opg_av_v_c_panel_narradores($res)
{
    $html = '<section class="ca-seccion" aria-labelledby="ca-t-narr"><h2 class="ca-titulo" id="ca-t-narr">Narradores disponibles</h2>'
        . '<p class="ta-ayuda">Todas las ofertas, vigentes y vencidas. Puedes retirar una oferta con un motivo.</p>';
    if (!$res['items']) {
        return $html . opg_av_v_vacio('No hay ofertas de narrador.') . '</section>';
    }
    $html .= '<div class="ca-filas">';
    foreach ($res['items'] as $o) {
        $html .= opg_av_v_c_fila_oferta($o);
    }
    return $html . '</div></section>';
}

// ── Página de recompensas (aventuras_recompensas.php) ───────────────────────

function opg_av_v_r_numero($n)
{
    return number_format((float) $n, 0, ',', '.');
}

/** Texto de lo que recibe un destinatario, a partir del cálculo guardado o previsto. */
function opg_av_v_r_calculo($c)
{
    if (!is_array($c)) {
        return '<i>Sin cálculo</i>';
    }
    if (isset($c['rol']) && $c['rol'] === 'narrador') {
        $t = 'Experiencia ' . opg_av_v_r_numero($c['experiencia']);
        if ($c['excedente'] > 0) {
            $t .= ' (calculada ' . opg_av_v_r_numero($c['experiencia_final']) . '; el exceso del tope mensual da ' . opg_av_v_r_numero($c['kuros']) . ' kuros)';
        }
        $t .= ' · Nikas ' . opg_av_v_r_numero($c['nikas']) . ' · Cofre ' . opg_av_h($c['cofre']) . ' ×' . (int) $c['cofre_cantidad']
            . ' · Tope mensual ' . opg_av_v_r_numero($c['exp_mensual_antes']) . ' → ' . opg_av_v_r_numero($c['exp_mensual_despues']) . ' de ' . OPG_REC_EXP_NARRADOR_MENSUAL;
        return $t;
    }
    $t = 'Experiencia ' . opg_av_v_r_numero($c['experiencia']) . ' · Nikas ' . opg_av_v_r_numero($c['nikas']);
    $t .= ' · Reputación ' . ($c['reputacion_tipo'] === 'ninguna' ? 'ninguna' : opg_av_v_r_numero($c['reputacion']) . ' (' . opg_av_h($c['reputacion_tipo']) . ')');
    $t .= ' · Berries ' . opg_av_v_r_numero($c['berries']);
    if ($c['infra']) {
        $t .= ' (inframundo)';
    }
    return $t;
}

/** Cabecera común: datos de la solicitud y de la entrega, y el resumen del narrador. */
function opg_av_v_r_cabecera($e, $av)
{
    $html = '<div class="ar-cab"><h2 class="ca-titulo">Entrega #' . (int) $e['id'] . ' de la solicitud #' . (int) $e['peticion_id'] . '</h2>'
        . '<span class="ta-estado ta-estado-' . ($e['estado'] === 'entregada' ? 'aprobado' : ($e['estado'] === 'rechazada' ? 'denegado' : 'pendiente-narrador')) . '">' . opg_av_h(ucfirst($e['estado'])) . '</span></div>';
    $html .= '<dl class="ca-datos"><div class="ca-dato-ancho"><dt>Tema</dt><dd><a href="' . opg_av_h(opg_av_url_tema($e['tid'])) . '" target="_blank" rel="noopener">'
        . opg_av_h($e['tema_nombre']) . ' &rarr;</a></dd></div>';
    if ($av) {
        $html .= '<div><dt>Narrador asignado</dt><dd>' . opg_av_h($av['narrador_nombre']) . '</dd></div>'
            . '<div><dt>Isla y día</dt><dd>' . opg_av_h(($av['isla'] ? $av['isla']['nombre'] : 'Sin isla') . ' · ' . ($av['fecha_texto'] !== null ? $av['fecha_texto'] : 'sin día')) . '</dd></div>'
            . '<div><dt>Tier pedido</dt><dd>' . ($av['tier'] ? (int) $av['tier'] : 'Sin tier') . ($av['inframundo'] ? ' · Inframundo' : '') . '</dd></div>'
            . '<div class="ca-dato-ancho"><dt>Participantes de la solicitud</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div>';
    }
    $html .= '<div><dt>Enviada</dt><dd>' . opg_av_h(opg_av_fecha($e['enviado_at'], true)) . ($e['intentos'] > 1 ? ' (intento ' . (int) $e['intentos'] . ')' : '') . '</dd></div>'
        . '<div><dt>Reputación sugerida</dt><dd>' . opg_av_h($e['reputacion']) . '</dd></div></dl>';
    $html .= opg_av_v_bloque_texto('Resumen del narrador', $e['resumen']);
    if ($e['estado'] === 'rechazada' && $e['motivo_rechazo'] !== '') {
        $html .= opg_av_v_bloque_texto('Motivo del rechazo', $e['motivo_rechazo'], 'ca-nota-error');
    }
    return $html;
}

/** Pagos con experiencia en duda: el staff decide si ya estaba aplicada o se aplica ahora. */
function opg_av_v_r_xp_dudosa($entrega_id, $resultado)
{
    $html = '';
    foreach ($resultado as $r) {
        if (!in_array($r['xp_estado'], array('dudoso', 'aplicando'), true)) {
            continue;
        }
        $params = array('id' => (int) $entrega_id, 'uid' => (int) $r['uid']);
        $html .= '<li><b>' . opg_av_h($r['username']) . '</b>: no se pudo confirmar si se sumó su experiencia. '
            . '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'xp_dudosa', $params + array('decision' => 'aplicada'))
            . ' hx-target="#ar-panel" hx-swap="innerHTML" hx-confirm="¿Confirmas que ya tiene la experiencia?">Ya la tiene</button> '
            . '<button type="button" class="opg-chip" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'xp_dudosa', $params + array('decision' => 'aplicar'))
            . ' hx-target="#ar-panel" hx-swap="innerHTML" hx-confirm="¿Sumarle la experiencia ahora?">Sumarla ahora</button></li>';
    }
    if ($html === '') {
        return '';
    }
    return '<div class="aviso err" role="alert"><b>Experiencia en duda</b><ul class="ta-lista-avisos">' . $html . '</ul></div>';
}

/** Resultado de una entrega ya pagada (o con pagos hechos): lo que recibió cada destinatario. */
function opg_av_v_r_resultado($resultado)
{
    if (!$resultado) {
        return '';
    }
    $html = '<h3 class="ar-subtitulo">Pagos registrados</h3><div class="ar-tabla-caja"><table class="ar-tabla"><thead><tr><th>Usuario</th><th>Rol</th><th>%</th><th>Estado</th><th>Recompensas</th></tr></thead><tbody>';
    foreach ($resultado as $r) {
        $estado = !$r['pagado'] ? 'Sin pagar' : ($r['xp_estado'] === 'aplicado' ? 'Pagado' : 'Pagado, experiencia en revisión');
        $html .= '<tr><td>' . opg_av_h($r['username'] !== '' ? $r['username'] : 'Usuario ' . $r['uid']) . ' (' . (int) $r['uid'] . ')</td><td>' . opg_av_h(ucfirst($r['rol']))
            . '</td><td>' . opg_av_h(rtrim(rtrim(number_format($r['porcentaje'], 2, ',', ''), '0'), ',')) . '%</td><td>' . opg_av_h($estado)
            . '</td><td>' . ($r['calculo'] ? opg_av_v_r_calculo($r['calculo']) : '') . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

/** Selector de un número entre dos valores. */
function opg_av_v_r_select($nombre, $id, $opciones, $actual, $deshabilitado)
{
    $html = '<select name="' . opg_av_h($nombre) . '" id="' . opg_av_h($id) . '"' . ($deshabilitado ? ' disabled' : '') . '>';
    foreach ($opciones as $valor => $etiqueta) {
        $html .= '<option value="' . opg_av_h($valor) . '"' . ((string) $actual === (string) $valor ? ' selected' : '') . '>' . opg_av_h($etiqueta) . '</option>';
    }
    return $html . '</select>';
}

/** Fila de un destinatario con sus ajustes y la recompensa prevista. */
function opg_av_v_r_fila_destinatario($d, $bloqueado)
{
    $uid = (int) $d['uid'];
    $dis = ($bloqueado || $d['pago'] !== null) ? ' disabled' : '';
    $n = 'd[' . $uid . ']';
    $html = '<tr class="' . ($d['incluir'] ? '' : 'ar-excluido') . '"><td data-etiqueta="Pagar a">'
        . ($dis ? '' : '<input type="hidden" name="' . $n . '[incluir]" value="0">')
        . '<label class="ta-casilla"><input type="checkbox" name="' . $n . '[incluir]" value="1"' . ($d['incluir'] ? ' checked' : '') . $dis
        . '> <b>' . opg_av_h($d['username']) . '</b> (' . $uid . ')</label>'
        . '<span class="ar-rol">' . opg_av_h(ucfirst($d['rol'])) . '</span>';
    foreach ($d['avisos'] as $a) {
        $html .= '<small class="ar-aviso-fila">' . opg_av_h($a) . '</small>';
    }
    $html .= '</td><td data-etiqueta="Posts">' . (int) $d['validos'] . ' válidos de ' . (int) $d['posts'] . '</td>'
        . '<td data-etiqueta="Ajuste de posts"><input type="number" class="ar-num" name="' . $n . '[variacion]" min="-' . OPG_REC_MAX_VARIACION . '" max="' . OPG_REC_MAX_VARIACION . '" value="' . (int) $d['variacion']
        . '" aria-label="Ajuste de posts de ' . opg_av_h($d['username']) . '"' . $dis . '></td>'
        . '<td data-etiqueta="%">' . opg_av_h(rtrim(rtrim(number_format($d['porcentaje'], 2, ',', ''), '0'), ',')) . '%</td>';
    if ($d['rol'] === 'jugador') {
        $html .= '<td data-etiqueta="Reputación">' . opg_av_v_r_select($n . '[rep]', 'ar-rep-' . $uid, array('positiva' => 'Positiva', 'negativa' => 'Negativa', 'ninguna' => 'Ninguna'), $d['rep'], (bool) $dis) . '</td>';
    } else {
        $html .= '<td data-etiqueta="Reputación">&mdash;</td>';
    }
    $html .= '<td data-etiqueta="Recibirá">' . ($d['tiene_ficha'] ? opg_av_v_r_calculo($d['calculo']) : '<i>Sin ficha</i>')
        . ($d['pago'] !== null ? '<br><small>Ya cobró en un intento anterior.</small>' : '') . '</td></tr>';
    return $html;
}

/** Formulario de ajustes y vista previa de una entrega pendiente. */
function opg_av_v_r_formulario($a, $e)
{
    $aj = $a['ajustes'];
    $bloqueado = is_array($e['ajustes']);
    $dis = $bloqueado ? ' disabled' : '';
    $tiers = array();
    foreach (OPG_REC_POSTS_TIER as $t => $posts) {
        $tiers[$t] = 'Tier ' . $t . ' (' . $posts . ' posts por participante)';
    }
    $extras = array();
    foreach (OPG_REC_EXTRA_JUGADORES as $x) {
        $extras[$x] = $x === 0 ? 'Sin extra' : '+' . $x . '%';
    }

    $html = '<form class="ca-form ar-form" id="ar-form" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'analizar', array('id' => $e['id']))
        . ' hx-target="#ar-panel" hx-swap="innerHTML" hx-disabled-elt="find button">';
    if ($bloqueado) {
        $html .= '<p class="ta-ayuda">Ya se empezó a pagar esta entrega: los ajustes quedaron fijados para no cambiar los montos. Usa "Completar entrega" para terminar.</p>';
    }
    $html .= '<div class="ca-fila-campos">'
        . '<div class="af-field"><label for="ar-tier">Tier de la aventura</label>' . opg_av_v_r_select('tier', 'ar-tier', $tiers, $aj['tier'], $bloqueado) . '</div>'
        . '<div class="af-field"><label for="ar-bono">Extra del narrador por jugadores</label>' . opg_av_v_r_select('extra_jugadores', 'ar-bono', $extras, $aj['extra_jugadores'], $bloqueado) . '</div>'
        . '<div class="af-field"><label for="ar-n">Participantes para el mínimo de posts</label><input type="number" id="ar-n" name="n_participantes" min="1" max="20" value="' . (int) $aj['n_participantes'] . '"' . $dis . '></div></div>';

    if ($a['minimos']) {
        $m = $a['minimos'];
        $html .= '<p class="ar-minimos ' . ($m['cumple'] ? 'ar-cumple' : 'ar-no-cumple') . '">Posts válidos: <b>' . (int) $m['validos'] . '</b> de un mínimo de <b>' . (int) $m['minimo']
            . '</b> (' . (int) $m['esperados_por_participante'] . ' × ' . (int) $aj['n_participantes'] . ').</p>';
        if (!$m['cumple']) {
            $html .= '<label class="ta-casilla"><input type="hidden" name="confirma_minimos" value="0"><input type="checkbox" name="confirma_minimos" value="1"' . ($aj['confirma_minimos'] ? ' checked' : '') . $dis
                . '> Pagar igualmente aunque no se cumpla el mínimo</label>';
        }
    }

    if ($a['destinatarios']) {
        $html .= '<div class="ar-tabla-caja"><table class="ar-tabla"><thead><tr><th>Destinatario</th><th>Posts</th><th>Ajuste</th><th>%</th><th>Reputación</th><th>Recibirá</th></tr></thead><tbody>';
        foreach ($a['destinatarios'] as $d) {
            $html .= opg_av_v_r_fila_destinatario($d, $bloqueado);
        }
        $html .= '</tbody></table></div>';
    }
    if ($a['analisis']['posts_no_validos'] > 0) {
        $html .= '<p class="ta-ayuda">' . (int) $a['analisis']['posts_no_validos'] . ' posts no cuentan por tener menos de ' . OPG_REC_MIN_CARACTERES . ' caracteres.</p>';
    }
    if ($a['advertencias']) {
        $html .= '<ul class="ar-advertencias">';
        foreach ($a['advertencias'] as $w) {
            $html .= '<li>' . opg_av_h($w) . '</li>';
        }
        $html .= '</ul>';
    }
    if ($a['bloqueos']) {
        $html .= opg_av_v_aviso('err', $a['bloqueos']);
    }
    $html .= '<div class="ca-acciones"><button type="submit" class="opg-chip">Recalcular</button>'
        . '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'pagar', array('id' => $e['id']))
        . ' hx-target="#ar-panel" hx-swap="innerHTML" hx-confirm="¿Pagar las recompensas? Se cierra el tema y no se puede deshacer."' . ($a['puede_pagar'] ? '' : ' disabled aria-disabled="true"') . '>Pagar y cerrar el tema</button>';
    if ($bloqueado) {
        $html .= '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'completar', array('id' => $e['id']))
            . ' hx-target="#ar-panel" hx-swap="innerHTML">Completar entrega</button>';
    }
    $html .= '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('get', OPG_AV_URL_RECOMPENSAS, 'form_rechazar', array('id' => $e['id']))
        . ' hx-target="#ar-extra" hx-swap="innerHTML">Rechazar</button></div></form>';
    return $html;
}

/** Formulario del motivo de rechazo de una entrega. */
function opg_av_v_r_form_rechazar($id, $errores = array())
{
    return '<form class="ca-form" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'rechazar', array('id' => (int) $id))
        . ' hx-target="#ar-panel" hx-swap="innerHTML" hx-disabled-elt="find button[type=submit]">'
        . '<div class="ta-avisos" id="ar-aviso-rechazo" aria-live="polite">' . ($errores ? opg_av_v_aviso('err', $errores) : '') . '</div>'
        . '<div class="af-field"><label for="ar-motivo">Motivo del rechazo (obligatorio)</label>'
        . '<textarea id="ar-motivo" name="motivo" required maxlength="' . OPG_AV_MAX_MOTIVO . '" placeholder="Qué debe corregir el narrador. Podrá enviar la entrega otra vez."></textarea>'
        . '<small>Hasta ' . OPG_AV_MAX_MOTIVO . ' caracteres.</small></div>'
        . '<div class="ca-acciones"><button type="submit" class="btn-op btn-op--sm btn-op--peligro">Confirmar rechazo</button>'
        . '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_RECOMPENSAS, 'cerrar') . ' hx-target="#ar-extra" hx-swap="innerHTML">Cancelar</button></div></form>';
}

/**
 * Contenido de #ar-panel. $a es el análisis (solo entregas pendientes) o un fallo;
 * $aviso_html se muestra arriba (resultado de una acción).
 */
function opg_av_v_r_panel($e, $av, $a = null, $aviso_html = '')
{
    $html = '<p class="ta-enlace"><a href="' . opg_av_h(OPG_AV_URL_CONSOLA . '?tab=entregas') . '">&larr; Volver a las entregas</a></p>';
    $html .= '<div class="ta-avisos" id="ar-aviso" aria-live="polite">' . $aviso_html . '</div>';
    $html .= opg_av_v_r_cabecera($e, $av);
    $resultado = opg_ent_resultado($e['id']);
    $html .= opg_av_v_r_xp_dudosa($e['id'], $resultado);

    if ($e['estado'] === 'pendiente') {
        if (is_array($a) && !empty($a['ok'])) {
            $html .= opg_av_v_r_formulario($a, $e);
        } else {
            $errores = is_array($a) && !empty($a['errores']) ? $a['errores'] : array('No se pudo analizar el tema.');
            $html .= opg_av_v_aviso('err', $errores)
                . '<div class="ca-acciones"><button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('get', OPG_AV_URL_RECOMPENSAS, 'form_rechazar', array('id' => $e['id']))
                . ' hx-target="#ar-extra" hx-swap="innerHTML">Rechazar</button></div>';
        }
        $html .= opg_av_v_r_resultado($resultado);
    } else {
        $html .= opg_av_v_r_resultado($resultado);
    }
    return $html . '<div class="ca-extra" id="ar-extra"></div>';
}
