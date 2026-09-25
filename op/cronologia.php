<?php
/**
 * Cronología de personajes: calendario de estaciones con los temas de rol en
 * los que ha participado un personaje, según year/estacion/day de mybb_threads.
 *
 * Diseño: docs/200_DesignPlan_Cronologia.md
 * Requisitos: docs/100_Requirements_Cronologia.md
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'cronologia.php');

require_once "./../global.php";
require_once "./functions/op_functions.php";

// ─── Constantes del calendario ───────────────────────────────────────────────
const CRON_ANO_MIN      = 700;
const CRON_ANO_MAX      = 9999;
const CRON_ANO_DEFECTO  = 725;
const CRON_DIAS         = 90;
const CRON_MAX_OTROS    = 5;
const CRON_POR_PAGINA   = 100;   // temas por página en el modo lista global

$CRON_ESTACIONES = [
    'primavera' => 'Primavera',
    'verano'    => 'Verano',
    'otono'     => 'Otoño',
    'invierno'  => 'Invierno',
];
$CRON_SLUGS = array_keys($CRON_ESTACIONES);

// mybb_threads.prefix → tipos que se pueden filtrar. "Diario" no tiene un ID fijo
// en el repositorio: se busca por nombre en mybb_threadprefixes (como newreply.php).
$CRON_TIPOS = [
    3 => 'Aventura',
    1 => 'Común',
    6 => 'Evento',
    9 => 'Autonarrada',
];
$q_diario = $db->simple_select('threadprefixes', 'pid', "prefix = 'Diario'", ['limit' => 1]);
$prefijo_diario = (int)$db->fetch_field($q_diario, 'pid');
if ($prefijo_diario > 0) {
    $CRON_TIPOS[$prefijo_diario] = 'Diario';
}

// Nombre mostrado de cada tipo. MT y Requerimiento ya no se ofrecen como filtro,
// pero los temas que los tengan siguen apareciendo con su nombre.
$CRON_ETIQUETAS = $CRON_TIPOS + [10 => 'MT', 14 => 'Requerimiento'];

// Clase CSS del color de cada tipo (cron-tipo--aventura, --comun...). Sin tipo conocido: --otro.
function cron_tipo_clase($prefijo)
{
    global $CRON_ETIQUETAS;
    if (!isset($CRON_ETIQUETAS[$prefijo])) {
        return 'otro';
    }
    return strtr(mb_strtolower($CRON_ETIQUETAS[$prefijo], 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
}

// ─── Acceso: sesión + ficha propia ───────────────────────────────────────────
$viewer_uid = (int)$mybb->user['uid'];
if ($viewer_uid <= 0) {
    error_no_permission();
}

function cron_ficha($uid)
{
    global $db;
    $uid = (int)$uid;
    $q = $db->query("SELECT fid, nombre, faccion FROM mybb_op_fichas WHERE fid={$uid} LIMIT 1");
    $f = $db->fetch_array($q);
    return $f ? $f : null;
}

if (cron_ficha($viewer_uid) === null) {
    error_no_permission();
}

// ─── Buscador de personajes (typeahead de la cabecera) ───────────────────────
if ($mybb->get_input('action') === 'buscar_personajes') {
    $termino = trim($mybb->get_input('q'));
    header('Content-Type: text/html; charset=utf-8');
    if (mb_strlen($termino) < 3 && !ctype_digit($termino)) {
        exit;
    }

    $like    = $db->escape_string(addcslashes($termino, '%_'));
    $exacto  = $db->escape_string($termino);
    $por_id  = ctype_digit($termino) ? ' OR f.fid = ' . (int)$termino : '';
    $sin_staff = is_staff($viewer_uid) ? '' : " AND f.faccion <> 'Staff'";
    $q = $db->query("
        SELECT f.fid, f.nombre, f.apodo
        FROM mybb_op_fichas f
        WHERE (f.nombre LIKE '%{$like}%' OR f.apodo LIKE '%{$like}%'{$por_id}){$sin_staff}
        ORDER BY CASE WHEN f.nombre = '{$exacto}' OR f.apodo = '{$exacto}' THEN 0 ELSE 1 END, f.nombre ASC
        LIMIT 12
    ");
    while ($r = $db->fetch_array($q)) {
        $nombre = trim((string)$r['nombre']);
        $apodo  = trim((string)$r['apodo']);
        $label  = $nombre . ($apodo !== '' && $apodo !== $nombre ? ' · ' . $apodo : '') . ' (#' . (int)$r['fid'] . ')';
        echo '<button type="button" class="opg-resultado cron-opcion" role="option" data-fid="' . (int)$r['fid'] . '">'
            . htmlspecialchars_uni($label) . '</button>';
    }
    exit;
}

// ─── Personaje objetivo ──────────────────────────────────────────────────────
$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
if ($uid <= 0) {
    $uid = $viewer_uid;
}
$ficha = cron_ficha($uid);
// Las fichas de la facción Staff solo las ve el staff (igual que personaje.php)
if ($ficha === null || ($ficha['faccion'] === 'Staff' && !is_staff($viewer_uid))) {
    error('Personaje no encontrado.');
}
$nombre_personaje = htmlspecialchars_uni($ficha['nombre']);

// ─── Parámetros ──────────────────────────────────────────────────────────────
$vista_input = $mybb->get_input('vista');
$vista = in_array($vista_input, ['anio', 'lista'], true) ? $vista_input : 'estacion';
// Modo lista global: todo el historial, recientes primero por defecto
$alcance_global = ($vista === 'lista' && $mybb->get_input('alcance') === 'global');
$orden_asc = ($mybb->get_input('orden') === 'asc');
$pagina = max(1, $mybb->get_input('pagina', MyBB::INPUT_INT));

$tipo = $mybb->get_input('tipo', MyBB::INPUT_INT);
if (!isset($CRON_TIPOS[$tipo])) {
    $tipo = 0;
}
$ocultar_cerrados = $mybb->get_input('cerrados') === '0';

$y_input = $mybb->get_input('y', MyBB::INPUT_INT);
$t_input = strtolower((string)$mybb->get_input('t'));
if (!isset($CRON_ESTACIONES[$t_input])) {
    $t_input = '';
}

// ─── Normalización de la fecha de un tema ────────────────────────────────────
function cron_normalizar_fecha($year, $estacion, $day)
{
    $year = trim((string)$year);
    $day  = trim((string)$day);
    $est  = strtr(mb_strtolower(trim((string)$estacion), 'UTF-8'), ['ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

    if (!ctype_digit($year) || !ctype_digit($day)) {
        return null;
    }
    $year = (int)$year;
    $day  = (int)$day;
    if ($year < CRON_ANO_MIN || $year > CRON_ANO_MAX || $day < 1 || $day > CRON_DIAS) {
        return null;
    }
    if (!in_array($est, ['primavera', 'verano', 'otono', 'invierno'], true)) {
        return null;
    }
    return [$year, $est, $day];
}

// ─── Consulta principal: temas donde el personaje ha publicado ───────────────
$sql_inaccesibles = '';
$inaccesibles = get_unviewable_forums(true);
if ($inaccesibles !== '' && preg_match('/^[0-9,]+$/', $inaccesibles)) {
    $sql_inaccesibles = "AND t.fid NOT IN ({$inaccesibles})";
}

$query = $db->query("
    SELECT t.tid, t.subject, t.year, t.estacion, t.day, t.prefix, t.closed, t.dateline, t.fid, f.parentlist, f.parent_isla, COUNT(*) AS posts
    FROM mybb_posts p
    JOIN mybb_threads t ON t.tid = p.tid
    JOIN mybb_forums f ON f.fid = t.fid
    WHERE p.uid = {$uid}
      AND p.visible = 1
      AND t.visible = 1
      AND f.parentlist LIKE '10,%'
      {$sql_inaccesibles}
    GROUP BY t.tid, t.subject, t.year, t.estacion, t.day, t.prefix, t.closed, t.dateline, t.fid, f.parentlist, f.parent_isla
");

$temas = [];
$hay_temas_fechados = false;
while ($row = $db->fetch_array($query)) {
    $fecha = cron_normalizar_fecha($row['year'], $row['estacion'], $row['day']);
    if ($fecha === null) {
        continue; // sin fecha válida: se omite
    }
    $hay_temas_fechados = true;
    $cerrado = ((string)$row['closed'] === '1');
    if ($ocultar_cerrados && $cerrado) {
        continue;
    }
    $prefijo = (int)$row['prefix'];
    if ($tipo !== 0 && $prefijo !== $tipo) {
        continue;
    }
    $temas[] = [
        'tid'      => (int)$row['tid'],
        'titulo'   => (string)$row['subject'],
        'year'     => $fecha[0],
        'estacion' => $fecha[1],
        'dia'      => $fecha[2],
        'prefijo'  => $prefijo,
        'cerrado'  => $cerrado,
        'posts'    => (int)$row['posts'],
        'dateline' => (int)$row['dateline'],
        'fid'      => (int)$row['fid'],
        'parentlist' => (string)$row['parentlist'],
        'parent_isla' => (int)$row['parent_isla'],
    ];
}

// ─── Isla de cada tema (a partir de la estructura de foros) ──────────────────
// Isla = foro con isla_rol = 1: el del tema o su ancestro más cercano (parentlist);
// si no hay, parent_isla; si tampoco, el nombre del foro del tema como lugar sin enlace.
$ids_foros = [];
foreach ($temas as $tm) {
    foreach (explode(',', $tm['parentlist']) as $id) {
        if (ctype_digit($id)) {
            $ids_foros[(int)$id] = true;
        }
    }
    if ($tm['parent_isla'] > 0) {
        $ids_foros[$tm['parent_isla']] = true;
    }
}
$foros_isla = [];
if ($ids_foros) {
    $q = $db->query("SELECT fid, name, isla_rol FROM mybb_forums WHERE fid IN (" . implode(',', array_keys($ids_foros)) . ")");
    while ($r = $db->fetch_array($q)) {
        $foros_isla[(int)$r['fid']] = ['nombre' => (string)$r['name'], 'isla' => ((int)$r['isla_rol'] === 1)];
    }
}

function cron_isla_de_tema(array $tema, array $foros)
{
    $ids = array_reverse(array_filter(explode(',', $tema['parentlist']), 'ctype_digit'));
    foreach ($ids as $id) {
        $id = (int)$id;
        if (!empty($foros[$id]['isla'])) {
            return [$foros[$id]['nombre'], '/op/isla.php?isla_id=' . $id];
        }
    }
    $pi = $tema['parent_isla'];
    if ($pi > 0 && isset($foros[$pi])) {
        return [$foros[$pi]['nombre'], '/op/isla.php?isla_id=' . $pi];
    }
    $f = $foros[$tema['fid']] ?? null;
    return [$f ? $f['nombre'] : '', ''];
}

foreach ($temas as $i => $tm) {
    list($temas[$i]['isla'], $temas[$i]['isla_url']) = cron_isla_de_tema($tm, $foros_isla);
}

// ─── Último tema (por fecha in-game) ─────────────────────────────────────────
$indice_estacion = array_flip($CRON_SLUGS);
$ultimo = null;
foreach ($temas as $tema) {
    $clave = [$tema['year'], $indice_estacion[$tema['estacion']], $tema['dia'], $tema['dateline']];
    if ($ultimo === null || $clave > $ultimo['clave']) {
        $ultimo = ['clave' => $clave, 'year' => $tema['year'], 'estacion' => $tema['estacion']];
    }
}

// ─── Año y estación mostrados ────────────────────────────────────────────────
if ($y_input > 0) {
    $y = $y_input;
    $t = $t_input !== '' ? $t_input : 'primavera';
} else {
    $y = $ultimo ? $ultimo['year'] : CRON_ANO_DEFECTO;
    $t = $t_input !== '' ? $t_input : ($ultimo ? $ultimo['estacion'] : 'primavera');
}
$y = max(CRON_ANO_MIN, min(CRON_ANO_MAX, $y));
$si = $indice_estacion[$t];

// ─── URLs ────────────────────────────────────────────────────────────────────
function cron_url(array $extra = [])
{
    global $uid, $viewer_uid, $vista, $tipo, $ocultar_cerrados, $alcance_global, $orden_asc;
    $params = [];
    if ($uid !== $viewer_uid) {
        $params['uid'] = $uid;
    }
    if ($vista !== 'estacion') {
        $params['vista'] = $vista;
    }
    if ($alcance_global) {
        $params['alcance'] = 'global';
        if ($orden_asc) {
            $params['orden'] = 'asc';
        }
    }
    if ($tipo !== 0) {
        $params['tipo'] = $tipo;
    }
    if ($ocultar_cerrados) {
        $params['cerrados'] = 0;
    }
    foreach ($extra as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return 'cronologia.php' . ($params ? '?' . htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8') : '');
}

function cron_url_tema($tid)
{
    global $mybb;
    return rtrim($mybb->settings['bburl'], '/') . '/' . get_thread_link((int)$tid);
}

// ─── Temas por día de la estación mostrada / conteo por día del año ──────────
$por_dia = [];       // estacion actual: dia => [temas]
$conteo_anio = [];   // estacion => dia => cantidad
foreach ($temas as $tema) {
    if ($tema['year'] !== $y) {
        continue;
    }
    $conteo_anio[$tema['estacion']][$tema['dia']] = ($conteo_anio[$tema['estacion']][$tema['dia']] ?? 0) + 1;
    if ($tema['estacion'] === $t) {
        $por_dia[$tema['dia']][] = $tema;
    }
}
foreach ($por_dia as &$lista) {
    usort($lista, function ($a, $b) {
        return [$a['dateline'], $a['tid']] <=> [$b['dateline'], $b['tid']];
    });
}
unset($lista);

// ─── Otros participantes ─────────────────────────────────────────────────────
function cron_cargar_otros(array $tids)
{
    global $db, $uid;
    $otros = [];
    if (!$tids) {
        return $otros;
    }
    $tids_sql = implode(',', array_map('intval', $tids));
    $q = $db->query("
        SELECT DISTINCT p.tid, p.uid, COALESCE(fi.nombre, p.username) AS nombre
        FROM mybb_posts p
        LEFT JOIN mybb_op_fichas fi ON fi.fid = p.uid
        WHERE p.tid IN ({$tids_sql}) AND p.uid <> {$uid} AND p.visible = 1
        ORDER BY p.tid, nombre
    ");
    while ($r = $db->fetch_array($q)) {
        $otros[(int)$r['tid']][] = $r['nombre'];
    }
    return $otros;
}

// Estación y lista de estación: temas de la estación mostrada. La lista global carga los suyos por página.
$otros = [];
if ($por_dia && ($vista === 'estacion' || ($vista === 'lista' && !$alcance_global))) {
    $tids = [];
    foreach ($por_dia as $lista) {
        foreach ($lista as $tema) {
            $tids[] = $tema['tid'];
        }
    }
    $otros = cron_cargar_otros($tids);
}

// ─── Título y navegación ─────────────────────────────────────────────────────
if ($si > 0) {
    $prev = [$y, $CRON_SLUGS[$si - 1]];
} elseif ($y > CRON_ANO_MIN) {
    $prev = [$y - 1, 'invierno'];
} else {
    $prev = null;
}
if ($si < 3) {
    $next = [$y, $CRON_SLUGS[$si + 1]];
} elseif ($y < CRON_ANO_MAX) {
    $next = [$y + 1, 'primavera'];
} else {
    $next = null;
}

// En la vista de año, anterior/siguiente mueven de año completo.
if ($vista === 'anio') {
    $prev = $y > CRON_ANO_MIN ? [$y - 1, $t] : null;
    $next = $y < CRON_ANO_MAX ? [$y + 1, $t] : null;
    $titulo = 'Año ' . $y;
} elseif ($alcance_global) {
    $titulo = 'Historial completo';
} else {
    $titulo = $CRON_ESTACIONES[$t] . ' ' . $y;
}

$btn_prev = $prev
    ? '<a class="btn-op btn-op--sm btn-op--primario" href="' . cron_url(['y' => $prev[0], 't' => $prev[1]]) . '" rel="prev" aria-label="Anterior">&larr;</a>'
    : '<span class="btn-op btn-op--sm btn-op--primario" aria-disabled="true">&larr;</span>';
$btn_next = $next
    ? '<a class="btn-op btn-op--sm btn-op--primario" href="' . cron_url(['y' => $next[0], 't' => $next[1]]) . '" rel="next" aria-label="Siguiente">&rarr;</a>'
    : '<span class="btn-op btn-op--sm btn-op--primario" aria-disabled="true">&rarr;</span>';

$opciones_t = '';
foreach ($CRON_ESTACIONES as $slug => $label) {
    $opciones_t .= '<option value="' . $slug . '"' . ($slug === $t ? ' selected' : '') . '>' . $label . '</option>';
}
$opciones_tipo = '<option value="0">Todos</option>';
foreach ($CRON_TIPOS as $id => $label) {
    $opciones_tipo .= '<option value="' . $id . '"' . ($id === $tipo ? ' selected' : '') . '>' . $label . '</option>';
}

$chip_estacion = '<a class="opg-chip cron-chip' . ($vista === 'estacion' ? ' cron-chip--on' : '') . '" href="' . cron_url(['vista' => null, 'alcance' => null, 'orden' => null, 'y' => $y, 't' => $t]) . '">Estación</a>';
$chip_lista    = '<a class="opg-chip cron-chip' . ($vista === 'lista' ? ' cron-chip--on' : '') . '" href="' . cron_url(['vista' => 'lista', 'alcance' => null, 'orden' => null, 'y' => $y, 't' => $t]) . '">Lista</a>';
$chip_anio     = '<a class="opg-chip cron-chip' . ($vista === 'anio' ? ' cron-chip--on' : '') . '" href="' . cron_url(['vista' => 'anio', 'alcance' => null, 'orden' => null, 'y' => $y, 't' => $t]) . '">Año</a>';
$chip_ultimo   = ($ultimo && !$alcance_global)
    ? '<a class="opg-chip cron-chip" href="' . cron_url(['y' => $ultimo['year'], 't' => $ultimo['estacion']]) . '">Ir al último tema</a>'
    : '';

// Sub-modos de la lista: esta estación / todo el historial (+ orden en el global)
$subvistas = '';
if ($vista === 'lista') {
    $subvistas = '<div class="cron-vistas cron-subvistas">'
        . '<a class="opg-chip cron-chip' . (!$alcance_global ? ' cron-chip--on' : '') . '" href="' . cron_url(['alcance' => null, 'orden' => null, 'y' => $y, 't' => $t]) . '">Esta estación</a>'
        . '<a class="opg-chip cron-chip' . ($alcance_global ? ' cron-chip--on' : '') . '" href="' . cron_url(['alcance' => 'global', 'y' => null, 't' => null]) . '">Todo el historial</a>'
        . ($alcance_global
            ? '<a class="opg-chip cron-chip" href="' . cron_url(['orden' => $orden_asc ? null : 'asc']) . '">'
                . ($orden_asc ? 'Ver más recientes primero' : 'Ver más antiguos primero') . '</a>'
            : '')
        . '</div>';
}

$hidden_uid   = $uid !== $viewer_uid ? '<input type="hidden" name="uid" value="' . $uid . '">' : '';
$hidden_vista = $vista !== 'estacion' ? '<input type="hidden" name="vista" value="' . $vista . '">' : '';
if ($alcance_global) {
    $hidden_vista .= '<input type="hidden" name="alcance" value="global">' . ($orden_asc ? '<input type="hidden" name="orden" value="asc">' : '');
}
$nav_html = $alcance_global
    ? '<div class="cron-nav"><h2 class="cron-periodo">' . htmlspecialchars_uni($titulo) . '</h2></div>'
    : '<div class="cron-nav">' . $btn_prev . '<h2 class="cron-periodo">' . htmlspecialchars_uni($titulo) . '</h2>' . $btn_next . '</div>';
$campos_fecha = $alcance_global ? '' : '
    <div class="af-field cron-campo-corto"><label for="cron-y">Año</label>
        <input id="cron-y" name="y" type="number" min="' . CRON_ANO_MIN . '" max="' . CRON_ANO_MAX . '" value="' . $y . '"></div>
    <div class="af-field"><label for="cron-t">Estación</label>
        <select id="cron-t" name="t">' . $opciones_t . '</select></div>';

$controles = '
<div class="cron-controles">
    ' . $nav_html . '
    <div class="cron-vistas">' . $chip_estacion . $chip_lista . $chip_anio . $chip_ultimo . '</div>
</div>
' . $subvistas . '
<form method="get" action="cronologia.php" class="cron-filtros">
    ' . $hidden_uid . $hidden_vista . $campos_fecha . '
    <div class="af-field"><label for="cron-tipo">Tipo de tema</label>
        <select id="cron-tipo" name="tipo">' . $opciones_tipo . '</select></div>
    <label class="cron-check"><input type="checkbox" name="cerrados" value="0"' . ($ocultar_cerrados ? ' checked' : '') . '> Ocultar cerrados</label>
    <button class="btn-op btn-op--sm btn-op--primario" type="submit">Aplicar</button>
</form>';

// ─── Vista de estación ───────────────────────────────────────────────────────
function cron_fecha_texto($dia, $t, $y)
{
    global $CRON_ESTACIONES;
    return 'Día ' . $dia . ' de ' . $CRON_ESTACIONES[$t] . ' ' . $y;
}

$json_dias = [];
$grid_html = '';
$vista_html = '';
if ($vista === 'estacion') {
    for ($d = 1; $d <= CRON_DIAS; $d++) {
        $lista = $por_dia[$d] ?? [];
        $n = count($lista);
        if ($n === 0) {
            $grid_html .= '<div class="cron-dia"><span class="cron-dia-num">' . $d . '</span></div>';
            continue;
        }

        $eventos = '';
        foreach (array_slice($lista, 0, 2) as $tema) {
            $nombre_tipo = $CRON_ETIQUETAS[$tema['prefijo']] ?? 'Sin tipo';
            // Con el filtro en "Todos" el tipo no es evidente: se antepone al título
            $etiqueta_tipo = $tipo === 0
                ? '<span class="cron-tipo cron-tipo--' . cron_tipo_clase($tema['prefijo']) . '">' . htmlspecialchars_uni($nombre_tipo) . '</span> '
                : '';
            $eventos .= '<a class="cron-evento" href="' . htmlspecialchars(cron_url_tema($tema['tid']), ENT_QUOTES, 'UTF-8')
                . '" title="' . htmlspecialchars_uni($nombre_tipo . ' · ' . $tema['titulo'] . ($tema['isla'] !== '' ? ' — ' . $tema['isla'] : '')) . '">'
                . $etiqueta_tipo . htmlspecialchars_uni($tema['titulo']) . '</a>';
        }
        if ($n > 2) {
            $eventos .= '<span class="cron-mas">+' . ($n - 2) . ' más</span>';
        }

        $clases = 'cron-dia cron-dia--con' . ($n > 1 ? ' cron-dia--multi' : '');
        $grid_html .= '<div class="' . $clases . '" data-dia="' . $d . '" tabindex="0" role="button" aria-label="'
            . htmlspecialchars(cron_fecha_texto($d, $t, $y) . ': ' . $n . ($n === 1 ? ' tema' : ' temas'), ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="cron-dia-num">' . $d . '</span>'
            . ($n > 1 ? '<span class="cron-badge" title="Varios temas el mismo día">' . $n . '</span>' : '')
            . '<span class="cron-eventos">' . $eventos . '</span>'
            . '<span class="cron-punto"></span></div>';

        foreach ($lista as $tema) {
            $lista_otros = $otros[$tema['tid']] ?? [];
            $json_dias[$d][] = [
                'titulo'   => $tema['titulo'],
                'url'      => cron_url_tema($tema['tid']),
                'isla'     => $tema['isla'],
                'islaUrl'  => $tema['isla_url'],
                'tipo'     => $CRON_ETIQUETAS[$tema['prefijo']] ?? 'Sin tipo',
                'tipoClase' => cron_tipo_clase($tema['prefijo']),
                'cerrado'  => $tema['cerrado'],
                'posts'    => $tema['posts'],
                'otros'    => array_slice($lista_otros, 0, CRON_MAX_OTROS),
                'masOtros' => max(0, count($lista_otros) - CRON_MAX_OTROS),
            ];
        }
    }
    $etiquetas_dias = [];
    foreach (array_keys($json_dias) as $d) {
        $etiquetas_dias[$d] = cron_fecha_texto($d, $t, $y);
    }
    $vista_html = '<div class="cron-cuadro"><div class="cron-barra">' . htmlspecialchars_uni($titulo) . '</div>'
        . '<div class="cron-cuerpo"><div class="cron-grid" id="cron-grid" data-dias="'
        . htmlspecialchars(json_encode(['temas' => $json_dias, 'fechas' => $etiquetas_dias], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
        . '">' . $grid_html . '</div></div></div>'
        . '<div id="cron-overlay" class="cron-overlay" hidden role="dialog" aria-modal="false" aria-label="Temas del día"></div>';
}

function cron_isla_html($nombre, $url)
{
    if ($nombre === '') {
        return '';
    }
    $n = htmlspecialchars_uni($nombre);
    return $url !== ''
        ? '<a class="cron-isla" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $n . '</a>'
        : '<span class="cron-isla">' . $n . '</span>';
}

// Una fila de la vista de lista (estación y global)
function cron_fila_html(array $tema, array $otros)
{
    global $CRON_ETIQUETAS;
    $lista_otros = $otros[$tema['tid']] ?? [];
    $otros_html = '';
    if ($lista_otros) {
        $otros_html = '<div class="cron-tema-otros">Con: ' . htmlspecialchars_uni(implode(', ', array_slice($lista_otros, 0, CRON_MAX_OTROS)))
            . (count($lista_otros) > CRON_MAX_OTROS ? ' +' . (count($lista_otros) - CRON_MAX_OTROS) : '') . '</div>';
    }
    return '<div class="cron-tema cron-fila"><span class="cron-fila-dia">Día ' . $tema['dia'] . '</span><div class="cron-fila-cuerpo">'
        . '<a class="cron-tema-titulo" href="' . htmlspecialchars(cron_url_tema($tema['tid']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars_uni($tema['titulo']) . '</a>'
        . '<div class="cron-tema-meta">' . cron_isla_html($tema['isla'], $tema['isla_url'])
        . '<span class="cron-tag cron-tipo cron-tipo--' . cron_tipo_clase($tema['prefijo']) . '">' . htmlspecialchars_uni($CRON_ETIQUETAS[$tema['prefijo']] ?? 'Sin tipo') . '</span>'
        . '<span class="cron-tag ' . ($tema['cerrado'] ? 'cron-tag--cerrado' : 'cron-tag--abierto') . '">' . ($tema['cerrado'] ? 'Cerrado' : 'Abierto') . '</span>'
        . '<span class="cron-tema-posts">' . $tema['posts'] . ($tema['posts'] === 1 ? ' post' : ' posts') . '</span></div>'
        . $otros_html . '</div></div>';
}

// Números de página con "…" en los rangos largos
function cron_paginacion_html($pagina, $paginas, $desde, $hasta, $total)
{
    if ($paginas <= 1) {
        return '<div class="cron-paginacion"><span class="cron-pag-texto">Temas ' . $desde . '–' . $hasta . ' de ' . $total . '</span></div>';
    }
    $mostrar = [];
    foreach ([1, 2, $pagina - 1, $pagina, $pagina + 1, $paginas - 1, $paginas] as $n) {
        if ($n >= 1 && $n <= $paginas) {
            $mostrar[$n] = true;
        }
    }
    ksort($mostrar);
    $html = '<div class="cron-paginacion"><span class="cron-pag-texto">Temas ' . $desde . '–' . $hasta . ' de ' . $total . '</span><span class="cron-pag-botones">';
    $html .= $pagina > 1
        ? '<a class="opg-chip cron-chip" href="' . cron_url(['pagina' => $pagina - 1]) . '" rel="prev">&laquo; Anterior</a>'
        : '<span class="opg-chip cron-chip cron-chip--off">&laquo; Anterior</span>';
    $previo = 0;
    foreach (array_keys($mostrar) as $n) {
        if ($previo && $n > $previo + 1) {
            $html .= '<span class="cron-pag-puntos">…</span>';
        }
        $html .= $n === $pagina
            ? '<span class="opg-chip cron-chip cron-chip--on" aria-current="page">' . $n . '</span>'
            : '<a class="opg-chip cron-chip" href="' . cron_url(['pagina' => $n]) . '">' . $n . '</a>';
        $previo = $n;
    }
    $html .= $pagina < $paginas
        ? '<a class="opg-chip cron-chip" href="' . cron_url(['pagina' => $pagina + 1]) . '" rel="next">Siguiente &raquo;</a>'
        : '<span class="opg-chip cron-chip cron-chip--off">Siguiente &raquo;</span>';
    return $html . '</span></div>';
}

// ─── Vista de lista ──────────────────────────────────────────────────────────
if ($vista === 'lista' && !$alcance_global) {
    ksort($por_dia);
    $filas = '';
    foreach ($por_dia as $lista) {
        foreach ($lista as $tema) {
            $filas .= cron_fila_html($tema, $otros);
        }
    }
    $vista_html = '<div class="cron-cuadro"><div class="cron-barra">' . htmlspecialchars_uni($titulo) . '</div>'
        . '<div class="cron-cuerpo cron-lista">' . ($filas !== '' ? $filas : '<p class="opg-vacio cron-lista-vacia">Sin temas en esta estación.</p>') . '</div></div>';
}

// ─── Modo lista global: todo el historial, paginado ──────────────────────────
if ($alcance_global) {
    if (!$temas) {
        $vista_html = '';   // el mensaje de "sin temas" ya lo da $mensaje_vacio
    } else {
        $ordenados = $temas;
        usort($ordenados, function ($a, $b) use ($indice_estacion) {
            return [$a['year'], $indice_estacion[$a['estacion']], $a['dia'], $a['dateline'], $a['tid']]
                <=> [$b['year'], $indice_estacion[$b['estacion']], $b['dia'], $b['dateline'], $b['tid']];
        });
        if (!$orden_asc) {
            $ordenados = array_reverse($ordenados);
        }
        $total = count($ordenados);
        $paginas = (int)ceil($total / CRON_POR_PAGINA);
        $pagina = min($pagina, $paginas);
        $tramo = array_slice($ordenados, ($pagina - 1) * CRON_POR_PAGINA, CRON_POR_PAGINA);
        $desde = ($pagina - 1) * CRON_POR_PAGINA + 1;
        $hasta = $desde + count($tramo) - 1;

        // Total de temas por año y estación (del conjunto completo, no del tramo)
        $por_estacion = [];
        foreach ($temas as $tm) {
            $clave = $tm['year'] . '|' . $tm['estacion'];
            $por_estacion[$clave] = ($por_estacion[$clave] ?? 0) + 1;
        }

        $otros = cron_cargar_otros(array_column($tramo, 'tid'));

        $filas = '';
        $clave_actual = '';
        foreach ($tramo as $tema) {
            $clave = $tema['year'] . '|' . $tema['estacion'];
            if ($clave !== $clave_actual) {
                $clave_actual = $clave;
                $n = $por_estacion[$clave];
                $filas .= '<div class="cron-grupo season-' . $tema['estacion'] . '">' . $CRON_ESTACIONES[$tema['estacion']] . ' ' . $tema['year']
                    . ' <small>' . $n . ($n === 1 ? ' tema' : ' temas') . '</small></div>';
            }
            $filas .= cron_fila_html($tema, $otros);
        }

        $pag = cron_paginacion_html($pagina, $paginas, $desde, $hasta, $total);
        $vista_html = $pag . '<div class="cron-cuadro"><div class="cron-barra">' . htmlspecialchars_uni($titulo)
            . ($orden_asc ? ' · más antiguos primero' : ' · más recientes primero') . '</div>'
            . '<div class="cron-cuerpo cron-lista">' . $filas . '</div></div>' . $pag;
    }
}

// ─── Vista de año ────────────────────────────────────────────────────────────
if ($vista === 'anio') {
    $vista_html = '<div class="cron-anio">';
    foreach ($CRON_ESTACIONES as $slug => $label) {
        $celdas = '';
        for ($d = 1; $d <= CRON_DIAS; $d++) {
            $n = $conteo_anio[$slug][$d] ?? 0;
            $celdas .= '<span class="cron-mini' . ($n > 1 ? ' cron-mini--multi' : ($n === 1 ? ' cron-mini--on' : '')) . '"'
                . ($n ? ' title="Día ' . $d . ': ' . $n . ($n === 1 ? ' tema' : ' temas') . '"' : '') . '></span>';
        }
        $total = array_sum($conteo_anio[$slug] ?? []);
        $vista_html .= '<a class="cron-estacion season-' . $slug . '" href="' . cron_url(['vista' => null, 'y' => $y, 't' => $slug]) . '">'
            . '<span class="cron-estacion-titulo">' . $label . '</span>'
            . '<span class="cron-estacion-total">' . $total . ($total === 1 ? ' tema' : ' temas') . '</span>'
            . '<span class="cron-minigrid">' . $celdas . '</span></a>';
    }
    $vista_html .= '</div>';
}

// ─── Mensaje si no hay temas que mostrar ─────────────────────────────────────
$mensaje_vacio = '';
if (!$temas) {
    $mensaje_vacio = $hay_temas_fechados
        ? '<p class="opg-vacio">Ningún tema coincide con los filtros.</p>'
        : '<p class="opg-vacio">Sin temas registrados para este personaje.</p>';
}

$chip_mia = $uid !== $viewer_uid
    ? '<a class="opg-chip cron-chip" href="cronologia.php">Ver mi cronología</a>'
    : '';

$calendar_html = '
<header class="cron-cabecera">
    <h1 class="cron-titulo">Cronología de ' . $nombre_personaje . '</h1>
    <div class="cron-buscar opg-buscar-caja">
        <input type="search" id="cron-buscar" name="q" autocomplete="off" placeholder="Buscar otro personaje" aria-label="Buscar otro personaje"
            aria-autocomplete="list" aria-controls="cron-resultados"
            hx-get="cronologia.php" hx-trigger="keyup changed delay:250ms" hx-target="#cron-resultados" hx-vals=\'{"action":"buscar_personajes"}\'>
        <div class="opg-resultados cron-resultados" id="cron-resultados" role="listbox"></div>
    </div>
    <div class="cron-cabecera-links">' . $chip_mia . '<a class="opg-volver cron-volver" href="personaje.php?uid=' . $uid . '">&larr; Ver ficha</a></div>
</header>
' . $controles . $mensaje_vacio . $vista_html;

// En el modo global no hay una estación única: acento neutro (naranja) para la barra y los chips de día
$cron_clase = $alcance_global ? '' : 'season-' . $t;

$cronologia_script = '<script>' . <<<'JS'
(function () {
    var campo = document.getElementById('cron-buscar');
    var lista = document.getElementById('cron-resultados');
    if (!campo || !lista) { return; }

    function ir(fid) { window.location.href = 'cronologia.php?uid=' + encodeURIComponent(fid); }

    lista.addEventListener('click', function (e) {
        var op = e.target.closest('.cron-opcion');
        if (op) { ir(op.getAttribute('data-fid')); }
    });
    campo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var primera = lista.querySelector('.cron-opcion');
            if (primera) { ir(primera.getAttribute('data-fid')); }
        } else if (e.key === 'Escape') {
            lista.textContent = '';
        }
    });
    campo.addEventListener('input', function () {
        if (campo.value.trim().length < 3) { lista.textContent = ''; }
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.cron-buscar')) { lista.textContent = ''; }
    });
})();
JS
    . '</script>';

if ($vista === 'estacion') {
    $cronologia_script .= '<script>' . <<<'JS'
(function () {
    var grid = document.getElementById('cron-grid');
    var overlay = document.getElementById('cron-overlay');
    if (!grid || !overlay) { return; }
    var datos;
    try { datos = JSON.parse(grid.getAttribute('data-dias')); } catch (e) { return; }

    var fijado = null;     // celda fijada con clic
    var temporizador = null;

    function el(tag, clase, texto) {
        var n = document.createElement(tag);
        if (clase) { n.className = clase; }
        if (texto !== undefined) { n.textContent = texto; }
        return n;
    }

    function pintar(dia, conBoton) {
        var temas = datos.temas[dia];
        if (!temas) { return false; }
        overlay.textContent = '';
        var barra = el('div', 'cron-barra cron-overlay-barra');
        barra.appendChild(el('span', '', datos.fechas[dia]));
        if (conBoton) {
            var cerrar = el('button', 'cron-overlay-cerrar', '×');
            cerrar.type = 'button';
            cerrar.setAttribute('aria-label', 'Cerrar');
            barra.appendChild(cerrar);
        }
        overlay.appendChild(barra);

        var cuerpo = el('div', 'cron-cuerpo cron-lista');
        temas.forEach(function (t) {
            var fila = el('div', 'cron-tema');
            var enlace = el('a', 'cron-tema-titulo', t.titulo);
            enlace.href = t.url;
            fila.appendChild(enlace);

            var meta = el('div', 'cron-tema-meta');
            if (t.isla) {
                var isla = el(t.islaUrl ? 'a' : 'span', 'cron-isla', t.isla);
                if (t.islaUrl) { isla.href = t.islaUrl; }
                meta.appendChild(isla);
            }
            meta.appendChild(el('span', 'cron-tag cron-tipo cron-tipo--' + t.tipoClase, t.tipo));
            meta.appendChild(el('span', 'cron-tag ' + (t.cerrado ? 'cron-tag--cerrado' : 'cron-tag--abierto'), t.cerrado ? 'Cerrado' : 'Abierto'));
            meta.appendChild(el('span', 'cron-tema-posts', t.posts + (t.posts === 1 ? ' post' : ' posts')));
            fila.appendChild(meta);

            if (t.otros.length) {
                fila.appendChild(el('div', 'cron-tema-otros', 'Con: ' + t.otros.join(', ') + (t.masOtros ? ' +' + t.masOtros : '')));
            }
            cuerpo.appendChild(fila);
        });
        overlay.appendChild(cuerpo);
        return true;
    }

    function ocultar() {
        clearTimeout(temporizador);
        overlay.hidden = true;
        overlay.classList.remove('cron-overlay--fijo');
    }

    function soltar() {
        if (fijado) { fijado.classList.remove('cron-dia--sel'); }
        fijado = null;
        ocultar();
    }

    function mostrarHover(celda) {
        if (fijado) { return; }
        clearTimeout(temporizador);
        temporizador = setTimeout(function () {
            if (pintar(celda.getAttribute('data-dia'), false)) { overlay.hidden = false; }
        }, 150);
    }

    function fijar(celda) {
        clearTimeout(temporizador);
        if (fijado) { fijado.classList.remove('cron-dia--sel'); }
        if (!pintar(celda.getAttribute('data-dia'), true)) { return; }
        fijado = celda;
        celda.classList.add('cron-dia--sel');
        overlay.classList.add('cron-overlay--fijo');
        overlay.hidden = false;
    }

    grid.addEventListener('mouseover', function (e) {
        var celda = e.target.closest('.cron-dia--con');
        if (celda) { mostrarHover(celda); }
    });
    grid.addEventListener('mouseout', function (e) {
        var celda = e.target.closest('.cron-dia--con');
        if (!celda || fijado) { return; }
        if (e.relatedTarget && celda.contains(e.relatedTarget)) { return; }
        ocultar();
    });
    grid.addEventListener('focusin', function (e) {
        var celda = e.target.closest('.cron-dia--con');
        if (celda) { mostrarHover(celda); }
    });
    grid.addEventListener('focusout', function () { if (!fijado) { ocultar(); } });

    grid.addEventListener('click', function (e) {
        if (e.target.closest('a')) { return; }
        var celda = e.target.closest('.cron-dia--con');
        if (celda) { fijar(celda); }
    });
    grid.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }
        var celda = e.target.closest('.cron-dia--con');
        if (celda) { e.preventDefault(); fijar(celda); }
    });

    overlay.addEventListener('click', function (e) {
        if (e.target.closest('.cron-overlay-cerrar')) { soltar(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { soltar(); } });
    document.addEventListener('click', function (e) {
        if (!fijado) { return; }
        if (e.target.closest('#cron-overlay') || e.target.closest('.cron-dia--con')) { return; }
        soltar();
    });
})();
JS
    . '</script>';
}

add_breadcrumb('Cronología', 'cronologia.php');

global $templates, $headerinclude, $header, $footer;
$cronologia = '';
eval('$cronologia = "' . $templates->get('op_cronologia') . '";');

output_page($cronologia);
