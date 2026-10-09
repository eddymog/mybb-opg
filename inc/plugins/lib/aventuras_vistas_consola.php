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
    $tabs = array(
        'solicitudes' => array('Solicitudes', 'fa-solid fa-clipboard-list'),
        'entregas' => array('Entregas', 'fa-solid fa-coins'),
        'narradores' => array('Narradores', 'fa-solid fa-users'),
    );
    $html = '<nav class="ca-tabs" id="ca-tabs" aria-label="Secciones de la consola"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
    foreach ($tabs as $clave => $datos) {
        list($titulo, $icono) = $datos;
        $href = OPG_AV_URL_CONSOLA . '?tab=' . $clave;
        $html .= '<a class="ca-tab' . ($activa === $clave ? ' ca-tab-activa' : '') . '" href="' . opg_av_h($href) . '" '
            . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'tab', array('tab' => $clave)) . ' hx-target="#ca-panel" hx-swap="innerHTML"'
            . ' hx-indicator="#ca-panel-cargando" hx-sync="#ca-panel:replace" hx-push-url="' . opg_av_h($href) . '" aria-controls="ca-panel"'
            . ($activa === $clave ? ' aria-current="page"' : '') . '><i class="' . $icono . '" aria-hidden="true"></i><span>' . opg_av_h($titulo) . '</span>'
            . ($clave === 'entregas' ? ' ' . opg_av_v_c_contador($n_pendientes) : '') . '</a>';
    }
    return $html . '</nav>';
}

/** Guía plegable de los flujos principales de la consola. */
function opg_av_v_c_guia()
{
    $secciones = array(
        array('fa-clipboard-list', 'Solicitudes', 'Abre en las En revisión. Filtra por estado, solicitud o personaje.', array(
            '<strong>Aprobar</strong> exige isla, día, tier, comentario público, narrador y participantes, y que nadie supere sus límites (3 solitarias y 4 en total). Corrige lo que falte con <em>Editar datos</em>.',
            '<strong>Denegar</strong> pide un motivo que verá el usuario; <strong>Finalizar</strong> cierra una aprobada sin recompensas.',
            '<em>Editar datos</em> cambia la nota de staff (privada), el comentario público, la isla, el día, el tier y el inframundo. <em>Historial</em> registra quién hizo qué.',
            '<strong>Eliminar</strong> solo está disponible para staff y solo en solicitudes pendientes de borrado. No puedes resolver una aventura en la que participas.',
        )),
        array('fa-coins', 'Entregas', 'Aventuras y crónicas autonarradas que esperan revisión o pago.', array(
            'Aventura: <em>Revisar y pagar</em> abre el tema y propone porcentajes por posts. Puedes ajustar tier, extra del narrador, participantes, quién cobra, reputación y variación de posts.',
            'Pagar es único por destinatario, cierra el tema y publica el mensaje de recompensas. Tope mensual de experiencia del narrador: 675.',
            '<strong>Rechazar</strong> con motivo devuelve la entrega al narrador para corregirla. Si un pago se interrumpe, se completa sin duplicar monedas.',
            'Autonarrada: se valida (propiedad, nivel, post único, 800 palabras, semana) y se paga con recompensa fija desde su tarjeta.',
        )),
        array('fa-users', 'Narradores', 'Todas las ofertas de narrador, vigentes o vencidas. Busca por persona o Discord.', array(
            'Una oferta dura 30 días y su dueño puede renovarla o retirarla.',
            'Retira una oferta solo por moderación. El motivo queda registrado y el usuario puede volver a ofrecerse.',
        )),
    );
    $html = '<details class="ca-guia"><summary><span><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Cómo funciona la consola</span>'
        . '<i class="fa-solid fa-chevron-down ta-guia-flecha" aria-hidden="true"></i></summary>'
        . '<div class="ta-guia-contenido"><p class="ta-guia-intro"><strong>La consola reúne la gestión del ciclo completo de una aventura:</strong> solicitud, narrador, tema, entrega y pago. Usa el contador de Entregas para localizar primero el trabajo pendiente.</p>'
        . '<div class="ta-guia-secciones">';
    foreach ($secciones as $sec) {
        $html .= '<section><h3><i class="fa-solid ' . $sec[0] . '" aria-hidden="true"></i> ' . $sec[1] . '</h3><p>' . $sec[2] . '</p><ul>';
        foreach ($sec[3] as $punto) {
            $html .= '<li>' . $punto . '</li>';
        }
        $html .= '</ul></section>';
    }
    return $html . '</div><p class="ta-guia-estados"><strong>Flujo recomendado:</strong> revisa la solicitud → corrige sus datos → resuelve su estado → procesa la entrega. Antes de una acción definitiva, abre el historial o el tema si necesitas contexto.</p>'
        . '</div></details>';
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

/** Estados de solicitudes como métricas filtrables, no como una fila de chips. */
function opg_av_v_c_estados($opciones, $conteos, $actual)
{
    $clases = array(
        'todas' => 'total',
        'creado' => 'creado',
        'pendiente-narrador' => 'pendiente-narrador',
        'aprobado' => 'aprobado',
        'denegado' => 'denegado',
        'finalizado' => 'finalizado',
        'pendiente-borrado' => 'pendiente-borrado',
    );
    $html = '<fieldset class="ca-estados-resumen"><legend>Estado</legend><div class="ca-estados-grid">';
    foreach ($opciones as $valor => $etiqueta) {
        $n = isset($conteos[$valor]) ? (int) $conteos[$valor] : 0;
        $clase = isset($clases[$valor]) ? $clases[$valor] : 'total';
        $html .= '<label class="ca-estado-metrica ca-estado-metrica-' . $clase . '"><input class="ca-radio" type="radio" name="estado" value="' . opg_av_h($valor) . '"'
            . ((string) $actual === (string) $valor ? ' checked' : '') . '><span><span class="ca-estado-etiqueta">' . opg_av_h($etiqueta) . '</span><strong>' . $n . '</strong></span></label>';
    }
    return $html . '</div></fieldset>';
}

/** Filtros de solicitudes con las mismas etiquetas de estado que el tablón. */
function opg_av_v_c_filtros($filtros, $conteos_estado)
{
    $estado = isset($filtros['estado']) ? $filtros['estado'] : 'creado';
    $etiquetas = opg_av_etiquetas_estadisticas();
    $opciones = array(
        'todas' => $etiquetas['total'],
        'creado' => $etiquetas['creado'],
        'pendiente-narrador' => $etiquetas['pendiente-narrador'],
        'aprobado' => $etiquetas['aprobado'],
        'denegado' => $etiquetas['denegado'],
        'finalizado' => $etiquetas['finalizado'],
        'pendiente-borrado' => $etiquetas['pendiente-borrado'],
    );
    $conteos = array('todas' => array_sum($conteos_estado));
    foreach (opg_av_estados() as $codigo => $info) {
        $conteos[$info['clave']] = $conteos_estado[$codigo];
    }
    $q = isset($filtros['q']) ? $filtros['q'] : '';
    return '<form class="ca-filtros" id="ca-filtros" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'listar')
        . ' hx-trigger="change, submit, solicitudCambiada from:body" hx-target="#ca-lista" hx-swap="innerHTML">'
        . opg_av_v_c_estados($opciones, $conteos, $estado)
        . '<div class="af-field ca-busqueda"><label for="ca-q">Solicitud o persona</label><span class="opg-buscar-caja"><input type="search" id="ca-q" name="q" autocomplete="off" placeholder="#850, UID o nombre" value="' . opg_av_h($q) . '" aria-controls="ca-personas" '
        . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'buscar_personas') . ' hx-trigger="input changed delay:250ms" hx-target="#ca-personas" hx-swap="innerHTML" hx-sync="this:replace"><div class="opg-resultados" id="ca-personas" aria-live="polite"></div></span></div>'
        . '<div class="af-field ca-orden"><label for="ca-orden">Orden</label><select id="ca-orden" name="orden"><option value="antiguas"' . ($filtros['orden'] === 'antiguas' ? ' selected' : '') . '>Más antiguas primero</option><option value="recientes"' . ($filtros['orden'] === 'recientes' ? ' selected' : '') . '>Más recientes primero</option></select></div>'
        . '<div class="ca-filtro-acciones"><button type="submit" class="opg-chip"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Aplicar</button></div></form>';
}

function opg_av_v_c_resultados_personas($personas)
{
    if (!$personas) {
        return '<div class="opg-resultado-vacio">No hay fichas con ese nombre.</div>';
    }
    $html = '';
    foreach ($personas as $p) {
        $nombre = $p['nombre'] . (!empty($p['apodo']) ? ' (' . $p['apodo'] . ')' : '');
        $html .= '<button type="submit" class="opg-resultado" name="persona" value="' . (int) $p['fid'] . '">' . opg_av_h($nombre) . ' <small>UID ' . (int) $p['fid'] . '</small></button>';
    }
    return $html;
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

    $faltan = opg_av_datos_faltantes($av);
    $titulo = $av['aventura_nombre'] !== '' ? $av['aventura_nombre'] : 'Sin tema vinculado';
    $dias = max(0, (int) floor((opg_av_ahora() - (int) $av['created_at']) / 86400));
    $antiguedad = $dias === 0 ? 'Hoy' : ($dias === 1 ? 'Hace 1 día' : 'Hace ' . $dias . ' días');

    $html = '<article class="ca-fila" id="fila-' . $id . '" aria-labelledby="ca-fila-titulo-' . $id . '">';
    $html .= '<header class="ca-fila-cab"><div class="ca-identidad"><b class="ca-id">#' . $id . '</b><h3 id="ca-fila-titulo-' . $id . '">' . opg_av_h($titulo) . '</h3><span class="ca-antiguedad">' . opg_av_h($antiguedad) . '</span></div>'
        . '<span class="ta-estado ta-estado-' . opg_av_h($av['estado_clave']) . '">' . opg_av_h($av['estado_etiqueta']) . '</span></header>';
    $html .= '<div class="ca-meta-rapida"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' . opg_av_h($av['isla'] ? $av['isla']['nombre'] : 'Sin isla') . '</span>'
        . '<span><i class="fa-solid fa-calendar" aria-hidden="true"></i> ' . opg_av_h($av['fecha_texto'] !== null ? $av['fecha_texto'] : 'Sin día') . '</span>'
        . '<span><i class="fa-solid ' . (opg_av_es_autonarrada($av) ? 'fa-feather-pointed' : 'fa-layer-group') . '" aria-hidden="true"></i> '
        . (opg_av_es_autonarrada($av) ? 'Crónica autonarrada' : ($av['tier'] ? 'Tier ' . (int) $av['tier'] : 'Sin tier')) . '</span>'
        . ($av['inframundo'] ? '<span class="opg-chip ca-chip-pequeno">Inframundo</span>' : '')
        . ($av['editado_at'] ? '<span class="ca-editada">Editada ' . opg_av_h(opg_av_fecha($av['editado_at'])) . '</span>' : '') . '</div>';
    $html .= '<dl class="ca-datos"><div><dt>Creador</dt><dd>' . opg_av_h($av['creador_nombre']) . ' (' . (int) $av['uid'] . ')</dd></div>'
        . '<div><dt>Narrador</dt><dd>' . ($av['narrador_uid'] > 0 ? opg_av_h($av['narrador_nombre']) . ' (' . (int) $av['narrador_uid'] . ')' : '<i>Sin narrador</i>') . '</dd></div>'
        . '<div class="ca-dato-ancho"><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div></dl>';

    if ($faltan) {
        $html .= '<p class="ca-faltantes" role="status"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <b>Faltan datos:</b> ' . opg_av_h(implode(', ', array_values($faltan))) . '</p>';
    }

    $detalles = '';
    foreach (array(
        array('Comentario público', $av['comentario_publico'], ''),
        array('Nota de staff', isset($av['nota_staff']) ? $av['nota_staff'] : '', 'ca-nota-staff'),
        array('Idea del creador', $av['idea_narrador'], ''),
        array('El narrador anterior se salió', $av['motivo_renuncia'], ''),
        array('Motivo de la denegación', $estado === 2 ? $av['motivo_denegacion'] : '', 'ca-nota-error'),
    ) as $n) {
        if ($n[1] !== '') {
            $detalles .= opg_av_v_bloque_texto($n[0], $n[1], $n[2]);
        }
    }
    if ($av['tid_vinculado'] > 0) {
        $detalles .= '<p class="ta-enlace"><a href="' . opg_av_h(opg_av_url_tema($av['tid_vinculado'])) . '" target="_blank" rel="noopener">Abrir tema de la aventura &rarr;</a></p>';
    }
    if ($entrega) {
        $texto = $entrega['estado'] === 'pendiente' ? 'Entrega pendiente de revisión'
            : ($entrega['estado'] === 'rechazada' ? 'Entrega rechazada: ' . $entrega['motivo_rechazo'] : 'Recompensas entregadas el ' . opg_av_fecha($entrega['entregado_at']));
        $detalles .= '<p class="ca-entrega"><span class="opg-chip">' . opg_av_h($texto) . '</span></p>';
    }
    if ($detalles !== '') {
        $html .= '<details class="ca-detalles"><summary>Detalles de la solicitud</summary><div class="ca-detalles-cuerpo">' . $detalles . '</div></details>';
    }
    $html .= '<div class="ta-avisos" id="aviso-' . $id . '" aria-live="polite"></div>';

    $primaria = '';
    $secundarias = '';
    $fila = 'hx-target="#fila-' . $id . '" hx-swap="outerHTML"';
    $bloqueada = ' disabled aria-disabled="true"';
    if ($estado === 0) {
        if ($faltan) {
            $primaria = '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_editar', array('id' => $id)) . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML"><i class="fa-solid fa-pen" aria-hidden="true"></i> Completar datos</button>';
        } else {
            $primaria = '<button type="button" class="btn-op btn-op--primario"' . ($puede_resolver ? ' ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'cambiar_estado', array('id' => $id, 'a' => 1)) . ' ' . $fila . ' hx-disabled-elt="this"' : $bloqueada) . '><i class="fa-solid fa-check" aria-hidden="true"></i> Aprobar</button>';
        }
    }
    if (in_array($estado, array(0, 1, 4), true)) {
        $secundarias .= '<button type="button" class="opg-chip chip-peligro"' . ($puede_resolver ? ' ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_denegar', array('id' => $id)) . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML"' : $bloqueada) . '>Denegar</button>';
    }
    if ($estado === 1 && (!$entrega || $entrega['estado'] !== 'pendiente')) {
        $secundarias .= '<button type="button" class="opg-chip"' . ($puede_resolver ? ' ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'cambiar_estado', array('id' => $id, 'a' => 3)) . ' ' . $fila . ' hx-disabled-elt="this" hx-confirm="¿Finalizar esta aventura sin recompensas? Si el narrador la va a entregar, espera a su entrega."' : $bloqueada) . '>Finalizar</button>';
    }
    $secundarias .= '<button type="button" class="opg-chip" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_editar', array('id' => $id)) . ' hx-target="#extra-' . $id . '" hx-swap="innerHTML">Editar datos</button>';
    $secundarias .= '<button type="button" class="opg-chip ca-abrir-historial" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'historial', array('id' => $id)) . ' hx-target="#ca-overlay" hx-swap="innerHTML">Historial</button>';
    if ($estado === 5 && !empty($ctx['puede_eliminar'])) {
        $secundarias .= '<button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'eliminar', array('id' => $id)) . ' ' . $fila . ' hx-disabled-elt="this" hx-confirm="¿Eliminar definitivamente esta solicitud? No se puede deshacer.">Eliminar</button>';
    }
    $html .= '<div class="ca-acciones">' . $primaria . '<details class="ca-mas-acciones"><summary class="opg-chip"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i> Más acciones</summary><div class="ca-menu-acciones">' . $secundarias . '</div></details></div>';
    return $html . '<div class="ca-extra" id="extra-' . $id . '"></div></article>';
}

/** Fila marcada como actualización fuera de banda. */
function opg_av_v_c_lista($res, $filtros, $ctx)
{
    $resumen = '<p class="ca-resultados" tabindex="-1">' . (int) $res['total'] . ' solicitudes · Página ' . (int) $res['pagina'] . ' de ' . (int) $res['paginas'] . '</p>';
    if (!$res['items']) {
        return $resumen . opg_av_v_vacio('No hay solicitudes con estos filtros.');
    }
    $html = $resumen . '<div class="ca-filas">';
    foreach ($res['items'] as $av) {
        $html .= opg_av_v_c_fila($av, $ctx);
    }
    $params = array('estado' => $filtros['estado'], 'uid' => isset($filtros['uid']) ? $filtros['uid'] : '', 'q' => $filtros['q'], 'orden' => $filtros['orden']);
    return $html . '</div>' . opg_av_v_paginador($res, $params, 'listar', OPG_AV_URL_CONSOLA, '#ca-lista');
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
        . '<textarea id="ca-com-' . $id . '" name="comentario" required maxlength="' . OPG_AV_MAX_COMENTARIO . '" placeholder="Describe brevemente de qué trata la aventura.">' . opg_av_h($av['comentario_publico']) . '</textarea></div>'
        . '<div class="ta-isla"><span class="ta-rotulo">Isla</span><div class="ta-chips" id="ta-isla-slot-' . $id . '">' . opg_av_v_c_slot_isla($av['isla'], $id) . '</div>'
        . opg_av_v_c_buscador_isla($id) . '</div>'
        . '<div class="af-field"><label for="ca-dia-' . $id . '">Día (' . opg_av_h(OPG_AV_ESTACION . ' ' . OPG_AV_ANO) . ')</label>'
        . '<input type="number" id="ca-dia-' . $id . '" name="dia" min="1" max="' . OPG_AV_DIAS . '" value="' . opg_av_h($av['dia'] !== null ? $av['dia'] : '') . '"></div>'
        . (opg_av_es_autonarrada($av) ? '' : '<div class="af-field"><label for="ca-tier-' . $id . '">Tier</label><input type="number" id="ca-tier-' . $id . '" name="tier" min="' . OPG_AV_TIER_MIN . '" max="' . OPG_AV_TIER_MAX . '" value="' . opg_av_h($av['tier'] !== null ? $av['tier'] : '') . '"></div>')
        . '</div>'
        . (opg_av_es_autonarrada($av) ? '' : '<label class="ta-casilla"><input type="hidden" name="inframundo" value="0"><input type="checkbox" name="inframundo" value="1"' . ($av['inframundo'] ? ' checked' : '') . '> Requerimiento de inframundo</label>')
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

/** Historial de una solicitud como diálogo único con línea temporal. */
function opg_av_v_c_historial($filas, $id)
{
    $nombres = array(
        'crear' => 'Creó la solicitud', 'editar' => 'La editó el creador', 'postular_narrador' => 'Un narrador se postuló',
        'renunciar_narrador' => 'El narrador se salió', 'cambiar_estado' => 'Cambió de estado', 'vincular' => 'Vinculó el tema',
        'pedir_borrado' => 'Pidió el borrado', 'actualizar_datos' => 'El staff actualizó datos', 'enviar_entrega' => 'Envió la entrega',
        'rechazar_entrega' => 'Rechazó la entrega', 'pagar_entrega' => 'Pagó las recompensas', 'xp_dudosa' => 'Resolvió una experiencia dudosa', 'eliminar' => 'Eliminó la solicitud',
    );
    $html = '<div class="ca-overlay"><button type="button" class="ca-overlay-fondo" aria-label="Cerrar historial" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar') . ' hx-target="#ca-overlay" hx-swap="innerHTML"></button>'
        . '<section class="ca-historial" role="dialog" aria-modal="true" aria-labelledby="ca-historial-titulo" tabindex="-1"><div class="ca-historial-cab"><h2 id="ca-historial-titulo">Historial de la solicitud #' . (int) $id . '</h2>'
        . '<button type="button" class="opg-chip ca-cerrar-historial" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'cerrar') . ' hx-target="#ca-overlay" hx-swap="innerHTML" aria-label="Cerrar historial"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>';
    if (!$filas) {
        return $html . opg_av_v_vacio('Sin historial anterior a la migración.') . '</section></div>';
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
    return $html . '</ul></section></div>';
}

// ── Entregas ─────────────────────────────────────────────────────────────────

function opg_av_v_c_filtros_entregas($estado, $conteos, $tipo = 'todas')
{
    $opciones = array('pendiente' => 'Pendientes', 'rechazada' => 'Rechazadas', 'entregada' => 'Entregadas');
    $html = '<form class="ca-filtros" id="ca-filtros-entregas" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'entregas')
        . ' hx-trigger="change, submit" hx-target="#ca-lista-entregas" hx-swap="innerHTML">'
        . opg_av_v_c_grupo('Estado de la entrega', 'estado', $opciones, $conteos, $estado)
        . opg_av_v_c_grupo('Tipo', 'tipo', array('todas' => 'Todas', 'aventuras' => 'Aventuras', 'autonarradas' => 'Autonarradas'), array(), $tipo) . '</form>';
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

function opg_av_v_c_fila_autonarrada($av, $estado, $tipo)
{
    $id = (int) $av['id'];
    $auto = $av['autonarrada'];
    $analisis = opg_av_autonarrada_analizar($id);
    $html = '<article class="ca-fila ca-auto-entrega" id="auto-' . $id . '"><div class="ca-fila-cab"><b class="ca-id">#' . $id . '</b>'
        . '<span class="opg-chip ca-chip-pequeno"><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i> Autonarrada</span>'
        . '<span class="ca-isla">' . opg_av_h($av['aventura_nombre'] !== '' ? $av['aventura_nombre'] : 'Tema #' . (int) $auto['tid']) . '</span>'
        . '<span class="ta-estado">' . opg_av_h($auto['estado_pago'] === 'aplicado' ? 'Entregada' : 'Pendiente') . '</span></div>'
        . '<dl class="ca-datos"><div><dt>Personaje</dt><dd>' . opg_av_h($av['creador_nombre']) . ' (' . (int) $av['uid'] . ')</dd></div>'
        . '<div><dt>Palabras</dt><dd>' . (int) (isset($analisis['palabras']) ? $analisis['palabras'] : $auto['palabras']) . '</dd></div>'
        . '<div><dt>Semana</dt><dd>' . opg_av_h(opg_av_fecha($auto['semana_inicio'])) . '</dd></div>'
        . '<div><dt>Reputación declarada</dt><dd>' . opg_av_h(ucfirst($auto['reputacion'])) . '</dd></div>'
        . '<div><dt>Entregada</dt><dd>' . opg_av_h(opg_av_fecha($auto['entregada_at'])) . '</dd></div></dl>'
        . '<p class="ta-enlace"><a href="' . opg_av_h(opg_av_url_tema($auto['tid'])) . '" target="_blank" rel="noopener">Ver el tema &rarr;</a></p>'
        . '<div id="auto-aviso-' . $id . '">';
    if (empty($analisis['ok'])) {
        $html .= opg_av_v_aviso('err', isset($analisis['errores']) ? $analisis['errores'] : 'No se pudo validar la crónica.');
    }
    $html .= '</div>';
    if ($auto['estado_pago'] !== 'aplicado') {
        $html .= '<form class="ca-auto-pago" ' . opg_av_hx('post', OPG_AV_URL_CONSOLA, 'pagar_autonarrada', array('id' => $id, 'estado' => $estado, 'tipo' => $tipo))
            . ' hx-target="#ca-lista-entregas" hx-swap="innerHTML" hx-confirm="¿Entregar la recompensa fija y cerrar esta crónica?">'
            . '<span><b>Recompensa fija:</b> ' . OPG_AV_AUTO_XP . ' XP · ' . OPG_AV_AUTO_NIKAS . ' Nikas · '
            . OPG_AV_AUTO_REPUTACION . ' reputación · ' . number_format(OPG_AV_AUTO_BERRIES, 0, ',', '.') . ' Berries</span>'
            . '<button class="btn-op btn-op--primario" type="submit"' . (empty($analisis['ok']) ? ' disabled' : '') . '><i class="fa-solid fa-coins" aria-hidden="true"></i> '
            . ($auto['estado_pago'] === 'dudoso' ? 'Completar recompensa' : 'Pagar y cerrar') . '</button></form>';
    }
    return $html . '</article>';
}

function opg_av_v_c_lista_entregas($res, $estado, $autos = null, $tipo = 'todas')
{
    $autos = is_array($autos) ? $autos : array('items' => array(), 'total' => 0, 'pagina' => 1, 'paginas' => 1);
    if (!$res['items'] && !$autos['items']) {
        return opg_av_v_vacio($estado === 'pendiente' ? 'No hay entregas pendientes.' : 'No hay entregas en este estado.');
    }
    $html = '<div class="ca-filas">';
    foreach ($res['items'] as $it) {
        $html .= opg_av_v_c_fila_entrega($it);
    }
    foreach ($autos['items'] as $av) {
        $html .= opg_av_v_c_fila_autonarrada($av, $estado, $tipo);
    }
    return $html . '</div>' . opg_av_v_paginador($res['total'] ? $res : $autos, array('estado' => $estado, 'tipo' => $tipo), 'entregas', OPG_AV_URL_CONSOLA, '#ca-lista-entregas');
}

function opg_av_v_c_panel_entregas($estado, $res, $conteos, $tipo = 'todas', $autos = null)
{
    return '<section class="ca-seccion" aria-labelledby="ca-t-ent"><h2 class="ca-titulo" id="ca-t-ent">Entregas</h2>'
        . opg_av_v_c_filtros_entregas($estado, $conteos, $tipo)
        . '<div class="ca-lista" id="ca-lista-entregas" aria-live="polite">' . opg_av_v_c_lista_entregas($res, $estado, $autos, $tipo) . '</div></section>';
}

// ── Narradores disponibles ──────────────────────────────────────────────────

function opg_av_v_c_fila_oferta($o)
{
    $uid = (int) $o['uid'];
    $frecuencias = opg_av_frecuencias_narrador();
    $frecuencia = isset($frecuencias[$o['frecuencia']]) ? $frecuencias[$o['frecuencia']] : 'Sin frecuencia indicada';
    $nombre = $o['nombre'] !== '' ? $o['nombre'] : 'Usuario ' . $uid;
    if (!empty($o['apodo'])) {
        $nombre .= ' (' . $o['apodo'] . ')';
    }
    return '<article class="ca-fila ca-oferta" id="oferta-' . $uid . '" aria-labelledby="ca-oferta-t-' . $uid . '"><header class="ca-fila-cab"><h3 id="ca-oferta-t-' . $uid . '">' . opg_av_h($nombre) . ' <small>UID ' . $uid . '</small></h3>'
        . ($o['vencida'] ? '<span class="ta-estado ta-estado-vencida">Vencida</span>' : '<span class="ta-estado ta-estado-aprobado">Vigente</span>') . '</header>'
        . '<div class="ca-oferta-chips"><span class="opg-chip"><i class="fa-solid fa-spinner" aria-hidden="true"></i> En curso: ' . (int) $o['en_curso'] . '</span><span class="opg-chip"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Finalizadas: ' . (int) $o['finalizadas'] . '</span><span class="opg-chip"><i class="fa-solid fa-clock" aria-hidden="true"></i> ' . opg_av_h($frecuencia) . '</span>'
        . ($o['tiers'] !== '' ? '<span class="opg-chip">Tiers ' . opg_av_h($o['tiers']) . '</span>' : '') . ($o['estilo'] !== '' ? '<span class="opg-chip">' . opg_av_h($o['estilo']) . '</span>' : '') . '</div>'
        . '<p class="ta-discord"><b>Discord:</b> <span class="ta-copiable">' . opg_av_h($o['discord']) . '</span></p>'
        . ($o['mensaje'] !== '' ? opg_av_v_bloque_texto('Mensaje', $o['mensaje']) : '')
        . '<p class="ta-meta">Ofrecida el ' . opg_av_h(opg_av_fecha($o['desde'])) . ' · Renovada el ' . opg_av_h(opg_av_fecha($o['renovada'])) . '</p>'
        . '<div class="ta-avisos" id="aviso-oferta-' . $uid . '" aria-live="polite"></div>'
        . '<div class="ca-acciones"><button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'form_retirar_oferta', array('uid' => $uid))
        . ' hx-target="#extra-oferta-' . $uid . '" hx-swap="innerHTML">Retirar oferta</button></div>'
        . '<div class="ca-extra" id="extra-oferta-' . $uid . '"></div></article>';
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

function opg_av_v_c_filtros_narradores($filtros)
{
    $vigencia = isset($filtros['vigencia']) ? $filtros['vigencia'] : 'todas';
    return '<form class="ca-filtros" id="ca-filtros-narradores" ' . opg_av_hx('get', OPG_AV_URL_CONSOLA, 'narradores') . ' hx-trigger="change, submit" hx-target="#ca-lista-narradores" hx-swap="innerHTML">'
        . '<div class="af-field ca-busqueda"><label for="ca-q-narradores">Buscar narrador</label><input type="search" id="ca-q-narradores" name="q" placeholder="UID o nombre" value="' . opg_av_h(isset($filtros['q']) ? $filtros['q'] : '') . '"></div>'
        . '<div class="af-field"><label for="ca-vigencia">Vigencia</label><select id="ca-vigencia" name="vigencia"><option value="todas"' . ($vigencia === 'todas' ? ' selected' : '') . '>Todas</option><option value="vigentes"' . ($vigencia === 'vigentes' ? ' selected' : '') . '>Vigentes</option><option value="vencidas"' . ($vigencia === 'vencidas' ? ' selected' : '') . '>Vencidas</option></select></div>'
        . '<button type="submit" class="opg-chip">Aplicar</button></form>';
}

function opg_av_v_c_lista_narradores($res, $filtros)
{
    $resumen = '<p class="ca-resultados" tabindex="-1">' . (int) $res['total'] . ' ofertas · Página ' . (int) $res['pagina'] . ' de ' . (int) $res['paginas'] . '</p>';
    if (!$res['items']) {
        return $resumen . opg_av_v_vacio('No hay ofertas de narrador.');
    }
    $html = $resumen . '<div class="ca-filas ca-ofertas">';
    foreach ($res['items'] as $o) {
        $html .= opg_av_v_c_fila_oferta($o);
    }
    return $html . '</div>' . opg_av_v_paginador($res, array('vigencia' => $filtros['vigencia'], 'q' => $filtros['q']), 'narradores', OPG_AV_URL_CONSOLA, '#ca-lista-narradores');
}

function opg_av_v_c_panel_narradores($res, $filtros)
{
    return '<section class="ca-seccion" aria-labelledby="ca-t-narr"><h2 class="ca-titulo" id="ca-t-narr">Narradores disponibles</h2>'
        . '<p class="ta-ayuda">Todas las ofertas, vigentes y vencidas. Puedes retirar una oferta con un motivo.</p>'
        . opg_av_v_c_filtros_narradores($filtros)
        . '<div class="ca-lista" id="ca-lista-narradores" aria-live="polite">' . opg_av_v_c_lista_narradores($res, $filtros) . '</div></section>';
}

// ── Página de recompensas (aventuras_recompensas.php) ───────────────────────

function opg_av_v_r_numero($n)
{
    return number_format((float) $n, 0, ',', '.');
}

/** Resumen escaneable de lo que recibe un destinatario. */
function opg_av_v_r_calculo($c)
{
    if (!is_array($c)) {
        return '<i>Sin cálculo</i>';
    }
    $items = array();
    if (isset($c['rol']) && $c['rol'] === 'narrador') {
        $xp = opg_av_v_r_numero($c['experiencia']);
        if ($c['excedente'] > 0) {
            $xp .= '<small>Calculada: ' . opg_av_v_r_numero($c['experiencia_final']) . '</small>';
        }
        $items['Experiencia'] = $xp;
        $items['Nikas'] = opg_av_v_r_numero($c['nikas']);
        $items['Cofre'] = opg_av_h($c['cofre']) . ' x' . (int) $c['cofre_cantidad'];
        if ($c['excedente'] > 0) {
            $items['Kuros'] = opg_av_v_r_numero($c['kuros']);
        }
        $items['Tope mensual'] = opg_av_v_r_numero($c['exp_mensual_despues']) . ' / ' . OPG_REC_EXP_NARRADOR_MENSUAL;
    } else {
        $items['Experiencia'] = opg_av_v_r_numero($c['experiencia']);
        $items['Nikas'] = opg_av_v_r_numero($c['nikas']);
        $items['Reputación'] = $c['reputacion_tipo'] === 'ninguna' ? 'Ninguna' : opg_av_v_r_numero($c['reputacion']) . ' (' . opg_av_h($c['reputacion_tipo']) . ')';
        $items['Berries'] = opg_av_v_r_numero($c['berries']) . ($c['infra'] ? '<small>Inframundo</small>' : '');
    }
    $html = '<dl class="ar-recompensas-grid">';
    foreach ($items as $etiqueta => $valor) {
        $html .= '<div><dt>' . opg_av_h($etiqueta) . '</dt><dd>' . $valor . '</dd></div>';
    }
    return $html . '</dl>';
}

/** Cabecera común: datos de la solicitud y de la entrega, y el resumen del narrador. */
function opg_av_v_r_cabecera($e, $av)
{
    $estado_clase = $e['estado'] === 'entregada' ? 'aprobado' : ($e['estado'] === 'rechazada' ? 'denegado' : 'pendiente-narrador');
    $estado_texto = $e['estado'] === 'entregada' ? 'Recompensas entregadas' : ($e['estado'] === 'rechazada' ? 'Rechazada' : 'Pendiente de revisión');
    $narrador = opg_ent_nombre_narrador($e['narrador_uid'], $av);
    $html = '<section class="ar-entrega"><div class="ar-cab"><div><p class="ar-sobretitulo">Entrega #' . (int) $e['id'] . ' · Solicitud #' . (int) $e['peticion_id'] . '</p>'
        . '<h2><a href="' . opg_av_h(opg_av_url_tema($e['tid'])) . '" target="_blank" rel="noopener">' . opg_av_h($e['tema_nombre']) . ' <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></h2></div>'
        . '<span class="ta-estado ta-estado-' . $estado_clase . '">' . opg_av_h($estado_texto) . '</span></div>';
    $html .= '<dl class="ar-meta">';
    if ($av) {
        $html .= '<div><dt>Narrador de la entrega</dt><dd>' . opg_av_h($narrador) . '</dd></div>'
            . '<div><dt>Isla y día</dt><dd>' . opg_av_h(($av['isla'] ? $av['isla']['nombre'] : 'Sin isla') . ' · ' . ($av['fecha_texto'] !== null ? $av['fecha_texto'] : 'sin día')) . '</dd></div>'
            . '<div><dt>Tier pedido</dt><dd>' . ($av['tier'] ? (int) $av['tier'] : 'Sin tier') . ($av['inframundo'] ? ' · Inframundo' : '') . '</dd></div>'
            . '<div><dt>Participantes</dt><dd>' . opg_av_v_nombres($av['jugadores']) . '</dd></div>';
    }
    $html .= '<div><dt>Enviada</dt><dd>' . opg_av_h(opg_av_fecha($e['enviado_at'], true)) . ($e['intentos'] > 1 ? ' (intento ' . (int) $e['intentos'] . ')' : '') . '</dd></div>'
        . '<div><dt>Reputación sugerida</dt><dd>' . opg_av_h($e['reputacion']) . '</dd></div></dl>';
    $html .= '<div class="ar-resumen"><strong>Resumen del narrador</strong><p>' . nl2br(opg_av_h($e['resumen'])) . '</p></div>';
    if ($e['estado'] === 'rechazada' && $e['motivo_rechazo'] !== '') {
        $html .= opg_av_v_bloque_texto('Motivo del rechazo', $e['motivo_rechazo'], 'ca-nota-error');
    }
    return $html . '</section>';
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
    $html = '<tr class="' . ($d['incluir'] ? '' : 'ar-excluido') . '"><td data-etiqueta="Destinatario">'
        . ($dis ? '' : '<input type="hidden" name="' . $n . '[incluir]" value="0">')
        . '<label class="ta-casilla"><input type="checkbox" name="' . $n . '[incluir]" value="1"' . ($d['incluir'] ? ' checked' : '') . $dis
        . '> <span><b>' . opg_av_h($d['username']) . '</b><small>UID ' . $uid . '</small></span></label>'
        . '<span class="ar-rol">' . opg_av_h(ucfirst($d['rol'])) . '</span>';
    foreach ($d['avisos'] as $a) {
        $html .= '<small class="ar-aviso-fila">' . opg_av_h($a) . '</small>';
    }
    $pct = opg_av_h(rtrim(rtrim(number_format($d['porcentaje'], 2, ',', ''), '0'), ','));
    $html .= '</td><td data-etiqueta="Actividad"><strong>' . (int) $d['validos'] . ' de ' . (int) $d['posts'] . '</strong><small>posts válidos</small><span class="ar-porcentaje">' . $pct . '%</span></td>'
        . '<td data-etiqueta="Ajuste de posts"><input type="number" class="ar-num" name="' . $n . '[variacion]" min="-' . OPG_REC_MAX_VARIACION . '" max="' . OPG_REC_MAX_VARIACION . '" value="' . (int) $d['variacion']
        . '" aria-label="Ajuste de posts de ' . opg_av_h($d['username']) . '"' . $dis . '></td>';
    if ($d['rol'] === 'jugador') {
        $html .= '<td data-etiqueta="Reputación">' . opg_av_v_r_select($n . '[rep]', 'ar-rep-' . $uid, array('positiva' => 'Positiva', 'negativa' => 'Negativa', 'ninguna' => 'Ninguna'), $d['rep'], (bool) $dis) . '</td>';
    } else {
        $html .= '<td data-etiqueta="Reputación">&mdash;</td>';
    }
    $html .= '<td data-etiqueta="Vista previa">' . ($d['tiene_ficha'] ? opg_av_v_r_calculo($d['calculo']) : '<i>Sin ficha</i>')
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

    $html = '<form class="ar-form" id="ar-form" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'analizar', array('id' => $e['id']))
        . ' hx-trigger="submit, change delay:250ms" hx-target="#ar-panel" hx-swap="innerHTML" hx-sync="this:replace" hx-disabled-elt="find button">';
    if ($bloqueado) {
        $html .= '<p class="ta-ayuda">Ya se empezó a pagar esta entrega: los ajustes quedaron fijados para no cambiar los montos. Usa "Completar entrega" para terminar.</p>';
    }
    $html .= '<section class="ar-seccion" aria-labelledby="ar-criterios"><div class="ar-seccion-cab"><span class="ar-paso">1</span><div><h3 id="ar-criterios">Criterios y actividad</h3><p>Ajusta solo lo que difiera de la solicitud.</p></div></div>'
        . '<div class="ar-criterios">'
        . '<div class="af-field"><label for="ar-tier">Tier de la aventura</label>' . opg_av_v_r_select('tier', 'ar-tier', $tiers, $aj['tier'], $bloqueado) . '</div>'
        . '<div class="af-field"><label for="ar-bono">Extra del narrador</label>' . opg_av_v_r_select('extra_jugadores', 'ar-bono', $extras, $aj['extra_jugadores'], $bloqueado) . '</div>'
        . '<div class="af-field"><label for="ar-n">Participantes para el mínimo</label><input type="number" id="ar-n" name="n_participantes" min="1" max="20" value="' . (int) $aj['n_participantes'] . '"' . $dis . '></div></div>';

    if ($a['minimos']) {
        $m = $a['minimos'];
        $html .= '<div class="ar-metricas"><div><span>Posts del tema</span><strong>' . (int) $a['analisis']['total_posts'] . '</strong></div>'
            . '<div><span>Posts válidos</span><strong>' . (int) $m['validos'] . '</strong></div><div><span>Mínimo requerido</span><strong>' . (int) $m['minimo'] . '</strong></div>'
            . '<div class="' . ($m['cumple'] ? 'ar-cumple' : 'ar-no-cumple') . '"><span>Resultado</span><strong>' . ($m['cumple'] ? 'Cumple' : 'No cumple') . '</strong></div></div>';
        if (!$m['cumple']) {
            $html .= '<label class="ta-casilla ar-confirmar-minimos"><input type="hidden" name="confirma_minimos" value="0"><input type="checkbox" name="confirma_minimos" value="1"' . ($aj['confirma_minimos'] ? ' checked' : '') . $dis
                . '> Pagar igualmente aunque no se cumpla el mínimo</label>';
        }
    }
    $html .= '</section>';

    if ($a['destinatarios']) {
        $html .= '<section class="ar-seccion" aria-labelledby="ar-destinatarios"><div class="ar-seccion-cab"><span class="ar-paso">2</span><div><h3 id="ar-destinatarios">Destinatarios</h3><p>Revisa actividad, ajustes y recompensa prevista.</p></div></div>'
            . '<div class="ar-tabla-caja"><table class="ar-tabla"><thead><tr><th>Destinatario</th><th>Actividad</th><th>Ajuste</th><th>Reputación</th><th>Vista previa</th></tr></thead><tbody>';
        foreach ($a['destinatarios'] as $d) {
            $html .= opg_av_v_r_fila_destinatario($d, $bloqueado);
        }
        $html .= '</tbody></table></div></section>';
    }
    $html .= '<section class="ar-seccion ar-validacion" aria-labelledby="ar-validacion"><div class="ar-seccion-cab"><span class="ar-paso">3</span><div><h3 id="ar-validacion">Validación</h3><p>'
        . ($a['bloqueos'] ? count($a['bloqueos']) . ' asuntos impiden completar el pago.' : 'La entrega está lista para confirmar.') . '</p></div></div>';
    if ($a['analisis']['posts_no_validos'] > 0) {
        $a['advertencias'][] = (int) $a['analisis']['posts_no_validos'] . ' posts no cuentan por tener menos de ' . OPG_REC_MIN_CARACTERES . ' caracteres.';
    }
    if ($a['advertencias'] || $a['bloqueos']) {
        $html .= '<ul class="ar-validacion-lista">';
        foreach ($a['advertencias'] as $w) {
            $html .= '<li><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span><b>Revisar</b>' . opg_av_h($w) . '</span></li>';
        }
        foreach ($a['bloqueos'] as $w) {
            $html .= '<li class="ar-bloqueo"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i><span><b>Impide pagar</b>' . opg_av_h($w) . '</span></li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<p class="ar-listo"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Sin bloqueos.</p>';
    }
    $html .= '</section>';
    $incluidos = count(array_filter($a['destinatarios'], function ($d) { return !empty($d['incluir']); }));
    $html .= '<div class="ar-acciones-fijas"><div><button type="button" class="opg-chip chip-peligro" ' . opg_av_hx('get', OPG_AV_URL_RECOMPENSAS, 'form_rechazar', array('id' => $e['id']))
        . ' hx-target="#ar-extra" hx-swap="innerHTML"><i class="fa-solid fa-ban" aria-hidden="true"></i> Rechazar</button></div><div class="ar-acciones-principales">'
        . '<button type="submit" class="opg-chip"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Actualizar vista previa</button>'
        . '<button type="button" class="btn-op btn-op--primario" data-ar-abrir-confirmacion' . ($a['puede_pagar'] ? '' : ' disabled aria-disabled="true" aria-describedby="ar-pago-bloqueado"') . '>Pagar y cerrar</button>';
    if ($bloqueado) {
        $html .= '<button type="button" class="btn-op btn-op--primario" ' . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'completar', array('id' => $e['id']))
            . ' hx-target="#ar-panel" hx-swap="innerHTML">Completar entrega</button>';
    }
    $html .= '</div>' . ($a['puede_pagar'] ? '' : '<small id="ar-pago-bloqueado">Resuelve los bloqueos indicados antes de pagar.</small>') . '</div>'
        . '<dialog class="ar-dialogo" id="ar-confirmacion" aria-labelledby="ar-confirmacion-titulo" aria-describedby="ar-confirmacion-texto"><div class="ar-dialogo-icono"><i class="fa-solid fa-coins" aria-hidden="true"></i></div><h3 id="ar-confirmacion-titulo">Confirmar recompensas</h3>'
        . '<p id="ar-confirmacion-texto">Se pagará a <b>' . $incluidos . '</b> destinatarios y se cerrará el tema. Esta acción no se puede deshacer.</p><div class="ar-dialogo-acciones">'
        . '<button type="button" class="opg-chip" data-ar-cerrar-confirmacion>Volver a revisar</button><button type="button" class="btn-op btn-op--primario" '
        . opg_av_hx('post', OPG_AV_URL_RECOMPENSAS, 'pagar', array('id' => $e['id'])) . ' hx-target="#ar-panel" hx-swap="innerHTML" hx-include="#ar-form"'
        . ($a['puede_pagar'] ? '' : ' disabled aria-disabled="true"') . '>Confirmar pago</button></div></dialog></form>';
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
