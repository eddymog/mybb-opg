<?php
/** Historial público de cambios del catálogo de técnicas. */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'historial_tecnicas.php');
require_once './../global.php';
require_once MYBB_ROOT . 'inc/plugins/lib/tecnicas_cambios.php';

global $templates, $mybb, $db;

define('OPG_CAMBIOS_TECNICAS_POR_PAGINA', 25);

function ct_h($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function ct_valor($valor)
{
    $valor = trim((string) $valor);
    return $valor === '' ? '<span class="ct-vacio">Sin valor</span>' : nl2br(ct_h($valor));
}

function ct_enum($valor, $permitidos, $predeterminado)
{
    return in_array($valor, $permitidos, true) ? $valor : $predeterminado;
}

function ct_contiene($texto, $busqueda)
{
    return function_exists('mb_stripos')
        ? mb_stripos((string) $texto, (string) $busqueda, 0, 'UTF-8') !== false
        : stripos((string) $texto, (string) $busqueda) !== false;
}

function ct_opciones($valores, $seleccionado, $primera_etiqueta)
{
    $html = '<option value="">' . ct_h($primera_etiqueta) . '</option>';
    foreach ($valores as $valor => $etiqueta) {
        if (is_int($valor)) {
            $valor = $etiqueta;
        }
        $html .= '<option value="' . ct_h($valor) . '"' . ((string) $seleccionado === (string) $valor ? ' selected' : '') . '>' . ct_h($etiqueta) . '</option>';
    }
    return $html;
}

function ct_url($parametros)
{
    unset($parametros['pagina']);
    foreach ($parametros as $clave => $valor) {
        if ($valor === '' || $valor === null) {
            unset($parametros[$clave]);
        }
    }
    $query = http_build_query($parametros, '', '&');
    return '/op/historial_tecnicas.php' . ($query !== '' ? '?' . str_replace('&', '&amp;', $query) : '');
}

function ct_fecha_grupo($fecha_sql)
{
    $marca = strtotime($fecha_sql);
    $dia = date('Y-m-d', $marca);
    if ($dia === date('Y-m-d')) {
        return 'Hoy';
    }
    if ($dia === date('Y-m-d', TIME_NOW - 86400)) {
        return 'Ayer';
    }
    return my_date('d/m/Y', $marca);
}

$q = trim($mybb->get_input('q', MyBB::INPUT_STRING));
$q = function_exists('mb_substr') ? mb_substr($q, 0, 120, 'UTF-8') : substr($q, 0, 120);
$periodo = ct_enum($mybb->get_input('periodo', MyBB::INPUT_STRING), array('7', '30', '90', 'todo'), '90');
$accion = ct_enum($mybb->get_input('accion', MyBB::INPUT_STRING), array('todas', 'creada', 'modificada'), 'todas');
$orden = ct_enum($mybb->get_input('orden', MyBB::INPUT_STRING), array('recientes', 'antiguos'), 'recientes');
$campo = ct_enum($mybb->get_input('campo', MyBB::INPUT_STRING), array('', 'efectos', 'costes', 'enfriamiento', 'descripcion', 'requisitos', 'clasificacion'), '');
$formato = ct_enum($mybb->get_input('formato', MyBB::INPUT_STRING), array('todos', 'estructurado', 'legacy'), 'todos');
$alcance = ct_enum($mybb->get_input('alcance', MyBB::INPUT_STRING), array('todas', 'mias', 'mis_estilos'), 'todas');
$autor = trim($mybb->get_input('autor', MyBB::INPUT_STRING));
$estilo = trim($mybb->get_input('estilo', MyBB::INPUT_STRING));
$rama = trim($mybb->get_input('rama', MyBB::INPUT_STRING));
$tier = trim($mybb->get_input('tier', MyBB::INPUT_STRING));
$clase = trim($mybb->get_input('clase', MyBB::INPUT_STRING));
$tipo = trim($mybb->get_input('tipo', MyBB::INPUT_STRING));
$pagina = max(1, $mybb->get_input('pagina', MyBB::INPUT_INT));
$uid = (int) $mybb->user['uid'];
if ($uid <= 0) {
    $alcance = 'todas';
}

// Catálogo vigente: enriquece logs antiguos y valida filtros manipulables.
$catalogo = array();
$valores_catalogo = array('estilo' => array(), 'rama' => array(), 'tier' => array(), 'clase' => array(), 'tipo' => array());
$q_catalogo = $db->query('SELECT `tid`, `nombre`, `estilo`, `rama`, `tier`, `clase`, `tipo` FROM `mybb_op_tecnicas` ORDER BY `nombre`');
while ($tecnica = $db->fetch_array($q_catalogo)) {
    $catalogo[$tecnica['tid']] = $tecnica;
    foreach ($valores_catalogo as $columna => &$valores) {
        $valor = trim((string) $tecnica[$columna]);
        if ($valor !== '' && $valor !== '-') {
            $valores[$valor] = $valor;
        }
    }
    unset($valores);
}
foreach ($valores_catalogo as &$valores) {
    natcasesort($valores);
}
unset($valores);

foreach (array('estilo', 'rama', 'tier', 'clase', 'tipo') as $nombre_filtro) {
    if ($$nombre_filtro !== '' && !isset($valores_catalogo[$nombre_filtro][$$nombre_filtro])) {
        $$nombre_filtro = '';
    }
}

// Técnicas y estilos del personaje conectado.
$aprendidas = array();
$estilos_aprendidos = array();
$ramas_aprendidas = array();
if ($uid > 0) {
    $q_aprendidas = $db->query('SELECT `tid` FROM `mybb_op_tec_aprendidas` WHERE `uid`=' . $uid);
    while ($aprendida = $db->fetch_array($q_aprendidas)) {
        $tid_aprendido = (string) $aprendida['tid'];
        $aprendidas[$tid_aprendido] = true;
        if (isset($catalogo[$tid_aprendido])) {
            $tec_aprendida = $catalogo[$tid_aprendido];
            if ($tec_aprendida['estilo'] !== '') { $estilos_aprendidos[$tec_aprendida['estilo']] = true; }
            if ($tec_aprendida['rama'] !== '') { $ramas_aprendidas[$tec_aprendida['rama']] = true; }
        }
    }
}

$patrones = array(
    "`log` LIKE '" . $db->escape_string_like(OPG_TECNICAS_CAMBIO_MARCA) . "%'",
    "`log` LIKE 'Cambio a técnica ID %'",
    "`log` LIKE 'Técnica % (modificada%'",
    "`log` LIKE 'Técnica % (creada%'",
    "`log` LIKE 'Técnicas creadas:%'",
);
$where = '(' . implode(' OR ', $patrones) . ')';
if ($periodo !== 'todo') {
    $where .= ' AND `tiempo` >= DATE_SUB(NOW(), INTERVAL ' . (int) $periodo . ' DAY)';
}
$direccion_sql = $orden === 'antiguos' ? 'ASC' : 'DESC';
$query = $db->query("SELECT `id`, `tiempo`, `username`, `log` FROM `mybb_op_audit_consola_mod` WHERE {$where} ORDER BY `id` {$direccion_sql}");

$candidatos = array();
$autores = array();
while ($fila = $db->fetch_array($query)) {
    $cambio = opg_tecnicas_interpretar_log($fila['log']);
    if (!$cambio) {
        continue;
    }
    $cambio['id'] = (int) $fila['id'];
    $cambio['tiempo'] = $fila['tiempo'];
    $cambio['username'] = (string) $fila['username'];
    $cambio['actual'] = $cambio['tid'] !== '' && isset($catalogo[$cambio['tid']]) ? $catalogo[$cambio['tid']] : null;
    $cambio['nombre_resuelto'] = $cambio['nombre'] !== '' ? $cambio['nombre'] : ($cambio['actual'] ? $cambio['actual']['nombre'] : ($cambio['tid'] !== '' ? 'Técnica ' . $cambio['tid'] : 'Varias técnicas'));
    $cambio['campos_detectados'] = opg_tecnicas_cambio_campos_detectados($cambio);
    $cambio['aprendida'] = $cambio['tid'] !== '' && isset($aprendidas[$cambio['tid']]);
    $candidatos[] = $cambio;
    if ($cambio['username'] !== '') {
        $autores[$cambio['username']] = $cambio['username'];
    }
}
natcasesort($autores);
if ($autor !== '' && !isset($autores[$autor])) {
    $autor = '';
}

$total_reconocidos = count($candidatos);
$entradas = array();
foreach ($candidatos as $entrada) {
    $actual = $entrada['actual'];
    if ($accion !== 'todas' && $entrada['accion'] !== $accion) { continue; }
    if ($formato !== 'todos' && $entrada['formato'] !== $formato) { continue; }
    if ($autor !== '' && $entrada['username'] !== $autor) { continue; }
    if ($campo !== '' && !opg_tecnicas_cambio_coincide_grupo($entrada['campos_detectados'], $campo)) { continue; }
    if ($estilo !== '' && (!$actual || $actual['estilo'] !== $estilo)) { continue; }
    if ($rama !== '' && (!$actual || $actual['rama'] !== $rama)) { continue; }
    if ($tier !== '' && (!$actual || (string) $actual['tier'] !== $tier)) { continue; }
    if ($clase !== '' && (!$actual || $actual['clase'] !== $clase)) { continue; }
    if ($tipo !== '' && (!$actual || $actual['tipo'] !== $tipo)) { continue; }
    if ($alcance === 'mias' && !$entrada['aprendida']) { continue; }
    if ($alcance === 'mis_estilos' && !opg_tecnicas_coincide_estilos_personaje($actual, $estilos_aprendidos, $ramas_aprendidas)) { continue; }
    if ($q !== '') {
        $texto_busqueda = $entrada['tid'] . "\n" . $entrada['nombre_resuelto'] . "\n" . $entrada['detalle'];
        if ($actual) { $texto_busqueda .= "\n" . implode("\n", $actual); }
        foreach ($entrada['cambios'] as $diferencia) {
            if (is_array($diferencia)) { $texto_busqueda .= "\n" . implode("\n", $diferencia); }
        }
        if (!ct_contiene($texto_busqueda, $q)) { continue; }
    }
    $entradas[] = $entrada;
}

$total_filtrado = count($entradas);
$paginas = max(1, (int) ceil($total_filtrado / OPG_CAMBIOS_TECNICAS_POR_PAGINA));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * OPG_CAMBIOS_TECNICAS_POR_PAGINA;
$entradas_pagina = array_slice($entradas, $offset, OPG_CAMBIOS_TECNICAS_POR_PAGINA);

$etiquetas = opg_tecnicas_cambio_campos();
$cambios_tecnicas_filas = '';
$ultimo_dia = '';
foreach ($entradas_pagina as $entrada) {
    $actual = $entrada['actual'];
    $dia = date('Y-m-d', strtotime($entrada['tiempo']));
    if ($dia !== $ultimo_dia) {
        $ultimo_dia = $dia;
        $cambios_tecnicas_filas .= '<h2 class="ct-grupo-fecha"><span>' . ct_h(ct_fecha_grupo($entrada['tiempo'])) . '</span></h2>';
    }
    $fecha = my_date('d/m/Y', strtotime($entrada['tiempo']));
    $hora = my_date('H:i', strtotime($entrada['tiempo']));
    $accion_texto = $entrada['accion'] === 'creada' ? 'Nueva técnica' : 'Técnica actualizada';
    $clase_accion = $entrada['accion'] === 'creada' ? 'ct-accion--nueva' : 'ct-accion--cambio';

    $meta = '';
    if ($entrada['tid'] !== '') { $meta .= '<span class="ct-chip ct-chip--id">' . ct_h($entrada['tid']) . '</span>'; }
    if ($actual) {
        if ($actual['estilo'] !== '') { $meta .= '<span class="ct-chip">' . ct_h($actual['estilo']) . '</span>'; }
        if ($actual['rama'] !== '' && $actual['rama'] !== $actual['estilo']) { $meta .= '<span class="ct-chip">' . ct_h($actual['rama']) . '</span>'; }
        if ($actual['tier'] !== '') { $meta .= '<span class="ct-chip">Tier ' . ct_h($actual['tier']) . '</span>'; }
    }
    if ($entrada['aprendida']) { $meta .= '<span class="ct-chip ct-chip--aprendida"><i class="fa-solid fa-check" aria-hidden="true"></i> Aprendida</span>'; }
    if ($entrada['formato'] === 'legacy') { $meta .= '<span class="ct-chip ct-chip--historico">Registro histórico</span>'; }

    $campos_html = '';
    $campos_visibles = array_slice($entrada['campos_detectados'], 0, 4);
    foreach ($campos_visibles as $campo_detectado) {
        $campos_html .= '<span>' . ct_h(isset($etiquetas[$campo_detectado]) ? $etiquetas[$campo_detectado] : ucfirst(str_replace('_', ' ', $campo_detectado))) . '</span>';
    }
    $campos_restantes = count($entrada['campos_detectados']) - count($campos_visibles);
    if ($campos_restantes > 0) { $campos_html .= '<span>+' . $campos_restantes . '</span>'; }
    if ($campos_html === '') { $campos_html = '<span>Detalle general</span>'; }

    $detalle = '';
    if ($entrada['formato'] === 'estructurado') {
        foreach ($entrada['cambios'] as $diferencia) {
            if (!is_array($diferencia) || empty($diferencia['campo'])) { continue; }
            $campo_interno = (string) $diferencia['campo'];
            $etiqueta = isset($etiquetas[$campo_interno]) ? $etiquetas[$campo_interno] : ucfirst(str_replace('_', ' ', $campo_interno));
            $antes = isset($diferencia['antes']) ? $diferencia['antes'] : '';
            $despues = isset($diferencia['despues']) ? $diferencia['despues'] : '';
            $detalle .= '<div class="ct-diferencia"><strong>' . ct_h($etiqueta) . '</strong>';
            if ($entrada['accion'] === 'creada') {
                $detalle .= '<div class="ct-valor ct-valor--nuevo">' . ct_valor($despues) . '</div>';
            } else {
                $detalle .= '<div class="ct-comparacion"><div><span>Antes</span><div class="ct-valor ct-valor--antes">' . ct_valor($antes) . '</div></div>'
                    . '<i class="fa-solid fa-arrow-right" aria-hidden="true"></i><div><span>Ahora</span><div class="ct-valor ct-valor--nuevo">' . ct_valor($despues) . '</div></div></div>';
            }
            $detalle .= '</div>';
        }
    } else {
        $detalle = '<div class="ct-nota-historica"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Este registro es anterior al sistema de comparación. Muestra únicamente la información conservada en aquel momento.</span></div>'
            . '<div class="ct-legacy">' . nl2br(ct_h(preg_replace('/^Los cambios son:\s*/iu', '', $entrada['detalle']))) . '</div>';
    }
    if ($detalle === '') { $detalle = '<p class="ct-legacy">No hay más detalles disponibles para este registro.</p>'; }

    $acciones = '<a href="#cambio-' . $entrada['id'] . '"><i class="fa-solid fa-link" aria-hidden="true"></i> Enlace permanente</a>';
    if ($actual) {
        $acciones = '<a href="/op/tecnicas_buscar.php?nombre=' . rawurlencode($entrada['tid']) . '"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Ver técnica actual</a>' . $acciones;
    }

    $cambios_tecnicas_filas .= '<article class="ct-entrada" id="cambio-' . $entrada['id'] . '">'
        . '<header class="ct-entrada__cabecera"><div><span class="ct-accion ' . $clase_accion . '">' . $accion_texto . '</span>'
        . '<h3>' . ct_h($entrada['nombre_resuelto']) . '</h3><div class="ct-meta">' . $meta . '</div><div class="ct-campos" aria-label="Campos afectados">' . $campos_html . '</div></div>'
        . '<div class="ct-fecha"><strong>' . ct_h($fecha) . '</strong><span>' . ct_h($hora) . '</span></div></header>'
        . '<details class="ct-detalles"><summary><span><i class="fa-solid fa-code-compare" aria-hidden="true"></i> Ver cambios específicos</span><i class="fa-solid fa-chevron-down ct-chevron" aria-hidden="true"></i></summary>'
        . '<div class="ct-detalles__cuerpo">' . $detalle . '<footer class="ct-entrada__pie"><span>Actualizado por ' . ct_h($entrada['username']) . '</span><nav>' . $acciones . '</nav></footer></div></details>'
        . '</article>';
}

$parametros = array(
    'q' => $q, 'periodo' => $periodo, 'accion' => $accion, 'orden' => $orden,
    'estilo' => $estilo, 'rama' => $rama, 'tier' => $tier, 'clase' => $clase,
    'tipo' => $tipo, 'campo' => $campo, 'autor' => $autor, 'formato' => $formato,
    'alcance' => $alcance,
);
$filtros_activos = array();
if ($q !== '') { $filtros_activos['q'] = 'Búsqueda: ' . $q; }
if ($periodo !== '90') { $filtros_activos['periodo'] = $periodo === 'todo' ? 'Todo el historial' : 'Últimos ' . $periodo . ' días'; }
if ($accion !== 'todas') { $filtros_activos['accion'] = $accion === 'creada' ? 'Nuevas técnicas' : 'Técnicas modificadas'; }
if ($orden !== 'recientes') { $filtros_activos['orden'] = 'Más antiguos primero'; }
foreach (array('estilo' => 'Estilo', 'rama' => 'Rama', 'tier' => 'Tier', 'clase' => 'Clase', 'tipo' => 'Tipo', 'autor' => 'Autor') as $clave => $etiqueta_filtro) {
    if ($$clave !== '') { $filtros_activos[$clave] = $etiqueta_filtro . ': ' . $$clave; }
}
$etiquetas_campo = array('efectos' => 'Efectos', 'costes' => 'Costes', 'enfriamiento' => 'Enfriamiento', 'descripcion' => 'Descripción', 'requisitos' => 'Requisitos', 'clasificacion' => 'Clasificación');
if ($campo !== '') { $filtros_activos['campo'] = 'Campo: ' . $etiquetas_campo[$campo]; }
if ($formato !== 'todos') { $filtros_activos['formato'] = $formato === 'estructurado' ? 'Comparación moderna' : 'Registro histórico'; }
if ($alcance !== 'todas') { $filtros_activos['alcance'] = $alcance === 'mias' ? 'Mis técnicas' : 'Mis estilos'; }

$cambios_tecnicas_chips = '';
foreach ($filtros_activos as $clave => $etiqueta_filtro) {
    $sin = $parametros;
    unset($sin[$clave]);
    $cambios_tecnicas_chips .= '<a class="ct-filtro-chip" href="' . ct_url($sin) . '"><span>' . ct_h($etiqueta_filtro) . '</span><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>';
}
if ($cambios_tecnicas_chips !== '') { $cambios_tecnicas_chips .= '<a class="ct-limpiar" href="/op/historial_tecnicas.php">Limpiar todos</a>'; }

if ($cambios_tecnicas_filas === '') {
    $cambios_tecnicas_filas = '<div class="ct-sin-resultados"><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i><strong>Sin cambios encontrados</strong><span>Prueba eliminando algún filtro o consulta todo el historial.</span><a class="ct-btn ct-btn--primario" href="/op/historial_tecnicas.php">Limpiar filtros</a></div>';
}

$cambios_tecnicas_paginacion = '';
if ($paginas > 1) {
    $base_params = array_filter($parametros, function ($valor) { return $valor !== ''; });
    $url_pagina = function ($numero) use ($base_params) {
        $params = $base_params;
        $params['pagina'] = $numero;
        return '/op/historial_tecnicas.php?' . str_replace('&', '&amp;', http_build_query($params, '', '&'));
    };
    $anterior_html = $pagina > 1 ? '<a class="ct-btn ct-btn--secundario" href="' . $url_pagina($pagina - 1) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Anterior</a>' : '<span></span>';
    $siguiente_html = $pagina < $paginas ? '<a class="ct-btn ct-btn--secundario" href="' . $url_pagina($pagina + 1) . '">Siguiente <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>' : '<span></span>';
    $cambios_tecnicas_paginacion = '<nav class="ct-paginacion" aria-label="Páginas del historial">' . $anterior_html . '<span>Página ' . $pagina . ' de ' . $paginas . '</span>' . $siguiente_html . '</nav>';
}

$cambios_tecnicas_q = ct_h($q);
$ct_periodo_7 = $periodo === '7' ? ' selected' : '';
$ct_periodo_30 = $periodo === '30' ? ' selected' : '';
$ct_periodo_90 = $periodo === '90' ? ' selected' : '';
$ct_periodo_todo = $periodo === 'todo' ? ' selected' : '';
$ct_accion_todas = $accion === 'todas' ? ' selected' : '';
$ct_accion_creada = $accion === 'creada' ? ' selected' : '';
$ct_accion_modificada = $accion === 'modificada' ? ' selected' : '';
$ct_orden_recientes = $orden === 'recientes' ? ' selected' : '';
$ct_orden_antiguos = $orden === 'antiguos' ? ' selected' : '';
$ct_opciones_estilo = ct_opciones($valores_catalogo['estilo'], $estilo, 'Todos los estilos');
$ct_opciones_rama = ct_opciones($valores_catalogo['rama'], $rama, 'Todas las ramas');
$ct_opciones_tier = ct_opciones($valores_catalogo['tier'], $tier, 'Todos los tiers');
$ct_opciones_clase = ct_opciones($valores_catalogo['clase'], $clase, 'Todas las clases');
$ct_opciones_tipo = ct_opciones($valores_catalogo['tipo'], $tipo, 'Todos los tipos');
$ct_opciones_campo = ct_opciones($etiquetas_campo, $campo, 'Cualquier campo');
$ct_opciones_autor = ct_opciones($autores, $autor, 'Cualquier autor');
$ct_formato_todos = $formato === 'todos' ? ' selected' : '';
$ct_formato_estructurado = $formato === 'estructurado' ? ' selected' : '';
$ct_formato_legacy = $formato === 'legacy' ? ' selected' : '';
$ct_alcance_todas = $alcance === 'todas' ? ' selected' : '';
$ct_alcance_mias = $alcance === 'mias' ? ' selected' : '';
$ct_alcance_estilos = $alcance === 'mis_estilos' ? ' selected' : '';
$ct_campo_personal = $uid > 0 ? '<div class="ct-campo"><label for="ct-alcance">Relación con mi personaje</label><select id="ct-alcance" name="alcance"><option value="todas"' . $ct_alcance_todas . '>Todas las técnicas</option><option value="mias"' . $ct_alcance_mias . '>Mis técnicas aprendidas</option><option value="mis_estilos"' . $ct_alcance_estilos . '>Mis estilos y ramas</option></select></div>' : '';
$ct_avanzados_open = ($estilo !== '' || $rama !== '' || $tier !== '' || $clase !== '' || $tipo !== '' || $campo !== '' || $autor !== '' || $formato !== 'todos' || $alcance !== 'todas') ? ' open' : '';
$cambios_tecnicas_resumen = $total_filtrado . ' resultado' . ($total_filtrado === 1 ? '' : 's') . ' de ' . $total_reconocidos . ' cambio' . ($total_reconocidos === 1 ? '' : 's') . ' en el periodo';
$ct_controles_resultados = $total_filtrado > 0 ? '<div class="ct-controles"><button type="button" class="ct-control" data-ct-details="open"><i class="fa-solid fa-angles-down" aria-hidden="true"></i> Expandir todos</button><button type="button" class="ct-control" data-ct-details="close"><i class="fa-solid fa-angles-up" aria-hidden="true"></i> Contraer todos</button></div>' : '';

eval("\$page = \"" . $templates->get('op_historial_tecnicas') . "\";");
output_page($page);
