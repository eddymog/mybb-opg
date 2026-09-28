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

// Tipos donde existe la figura del narrador (5.8 / diseño 6.5)
const CRON_TIPOS_NARRABLES = [3, 6]; // Aventura, Evento

// Crea la tabla de roles si hace falta. Es global (sin prefijo cron_): la
// pensó para que otras herramientas la reutilicen (diseño 200 §3.4).
function cron_asegurar_tabla_roles()
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    global $db;
    $db->write_query("CREATE TABLE IF NOT EXISTS `mybb_op_thread_roles` (
        `uid` INT UNSIGNED NOT NULL,
        `tid` INT UNSIGNED NOT NULL,
        `rol` ENUM('personaje','narrador') NOT NULL DEFAULT 'personaje',
        `dateline` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`uid`, `tid`),
        KEY `idx_tid_rol` (`tid`, `rol`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
}
cron_asegurar_tabla_roles();

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

// Etiqueta de rol (Personaje/Narrador): igual que la de tipo, solo con el filtro
// de rol en "Todos" y solo en temas donde el rol tiene sentido (Aventura/Evento).
function cron_rol_mostrar($prefijo)
{
    global $rol_filtro;
    return $rol_filtro === '' && in_array($prefijo, CRON_TIPOS_NARRABLES, true);
}

function cron_rol_texto($rol)
{
    return $rol === 'narrador' ? 'Narrador' : 'Personaje';
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
// "Marcar roles" (5.8/4.1): el propio usuario sobre su ficha, o cualquier staff sobre otra
$puede_editar_roles = ($uid === $viewer_uid) || is_staff($viewer_uid);

// ─── Parámetros ──────────────────────────────────────────────────────────────
$vista_input = $mybb->get_input('vista');
$vista = in_array($vista_input, ['anio', 'lista', 'roles'], true) ? $vista_input : 'estacion';
// Modo lista global: todo el historial, recientes primero por defecto
$alcance_global = ($vista === 'lista' && $mybb->get_input('alcance') === 'global');
$orden_asc = ($mybb->get_input('orden') === 'asc');
$pagina = max(1, $mybb->get_input('pagina', MyBB::INPUT_INT));

$tipo = $mybb->get_input('tipo', MyBB::INPUT_INT);
if (!isset($CRON_TIPOS[$tipo])) {
    $tipo = 0;
}
$rol_filtro = $mybb->get_input('rol');
if (!in_array($rol_filtro, ['personaje', 'narrador'], true)) {
    $rol_filtro = '';
}
$ocultar_cerrados = $mybb->get_input('cerrados') === '0';

if ($vista === 'roles' && !$puede_editar_roles) {
    error_no_permission();
}

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

// $temas_todos: todo tema fechado y de la zona de rol, SIN aplicar todavía
// los filtros de cerrados/tipo/rol (los necesita tal cual la pestaña "Marcar
// roles" y el recálculo de tids válidos al guardar, diseño 6.5).
$temas_todos = [];
$hay_temas_fechados = false;
while ($row = $db->fetch_array($query)) {
    $fecha = cron_normalizar_fecha($row['year'], $row['estacion'], $row['day']);
    if ($fecha === null) {
        continue; // sin fecha válida: se omite
    }
    $hay_temas_fechados = true;
    $temas_todos[] = [
        'tid'      => (int)$row['tid'],
        'titulo'   => (string)$row['subject'],
        'year'     => $fecha[0],
        'estacion' => $fecha[1],
        'dia'      => $fecha[2],
        'prefijo'  => (int)$row['prefix'],
        'cerrado'  => ((string)$row['closed'] === '1'),
        'posts'    => (int)$row['posts'],
        'dateline' => (int)$row['dateline'],
        'fid'      => (int)$row['fid'],
        'parentlist' => (string)$row['parentlist'],
        'parent_isla' => (int)$row['parent_isla'],
        'rol'      => 'personaje',
    ];
}

// ─── Rol propio (personaje/narrador) en cada tema ─────────────────────────────
// Sin fila en la tabla, el rol efectivo es 'personaje' (diseño 6.5.1).
if ($temas_todos) {
    $tids_propios = implode(',', array_column($temas_todos, 'tid'));
    $q = $db->query("SELECT tid, rol FROM mybb_op_thread_roles WHERE uid = {$uid} AND tid IN ({$tids_propios})");
    $mapa_rol_propio = [];
    while ($r = $db->fetch_array($q)) {
        $mapa_rol_propio[(int)$r['tid']] = (string)$r['rol'];
    }
    foreach ($temas_todos as $i => $tm) {
        if (isset($mapa_rol_propio[$tm['tid']])) {
            $temas_todos[$i]['rol'] = $mapa_rol_propio[$tm['tid']];
        }
    }
    unset($mapa_rol_propio);
}

// ─── Guardado de roles (pestaña "Marcar roles", diseño 6.5.2) ─────────────────
$roles_guardados = false;
if ($mybb->request_method === 'post' && $mybb->get_input('action') === 'guardar_roles') {
    if (!$puede_editar_roles) {
        error_no_permission();
    }
    if (!verify_post_check($mybb->get_input('my_post_key', MyBB::INPUT_STRING), true)) {
        error_no_permission();
    }
    // Recalcular en servidor los tid válidos (Aventura/Evento donde este uid participó)
    // y su rol actual: nunca se confía en la lista de tid que manda el formulario. El
    // formulario manda el radio de cada fila aunque no se haya tocado, así que se
    // compara contra el rol ya guardado para no escribir lo que no cambió.
    $tids_validos = [];
    foreach ($temas_todos as $tm) {
        if (in_array($tm['prefijo'], CRON_TIPOS_NARRABLES, true)) {
            $tids_validos[$tm['tid']] = $tm['rol'];
        }
    }
    $roles_post = $mybb->get_input('roles', MyBB::INPUT_ARRAY);
    if ($roles_post && $tids_validos) {
        $algo_cambio = false;
        foreach ($roles_post as $tid_post => $rol_post) {
            $tid_post = (int)$tid_post;
            if (!isset($tids_validos[$tid_post]) || !in_array($rol_post, ['personaje', 'narrador'], true)) {
                continue;
            }
            if ($tids_validos[$tid_post] === $rol_post) {
                continue; // sin cambios: no hace falta escribir
            }
            $db->write_query("
                INSERT INTO mybb_op_thread_roles (uid, tid, rol, dateline)
                VALUES ({$uid}, {$tid_post}, '{$rol_post}', " . TIME_NOW . ")
                ON DUPLICATE KEY UPDATE rol = VALUES(rol), dateline = VALUES(dateline)
            ");
            foreach ($temas_todos as $i => $tm) {
                if ($tm['tid'] === $tid_post) {
                    $temas_todos[$i]['rol'] = $rol_post;
                }
            }
            $algo_cambio = true;
        }
        $roles_guardados = $algo_cambio;
    }
}

// ─── Filtros de estación/lista/año: cerrados, tipo y rol ──────────────────────
$temas = [];
foreach ($temas_todos as $tm) {
    if ($ocultar_cerrados && $tm['cerrado']) {
        continue;
    }
    if ($tipo !== 0 && $tm['prefijo'] !== $tipo) {
        continue;
    }
    if ($rol_filtro !== '' && $tm['rol'] !== $rol_filtro) {
        continue;
    }
    $temas[] = $tm;
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
    global $uid, $viewer_uid, $vista, $tipo, $rol_filtro, $ocultar_cerrados, $alcance_global, $orden_asc;
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
    if ($rol_filtro !== '') {
        $params['rol'] = $rol_filtro;
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

// ─── Narrador(es) marcados de cada tema (diseño 6.5.1) ────────────────────────
// Global: se muestra igual en la cronología de cualquiera que también esté en el tema.
function cron_cargar_narradores(array $tids)
{
    global $db;
    $narradores = [];
    if (!$tids) {
        return $narradores;
    }
    $tids_sql = implode(',', array_map('intval', $tids));
    $q = $db->query("
        SELECT r.tid, COALESCE(fi.nombre, r.uid) AS nombre
        FROM mybb_op_thread_roles r
        LEFT JOIN mybb_op_fichas fi ON fi.fid = r.uid
        WHERE r.tid IN ({$tids_sql}) AND r.rol = 'narrador'
        ORDER BY r.tid, nombre
    ");
    while ($r = $db->fetch_array($q)) {
        $narradores[(int)$r['tid']][] = $r['nombre'];
    }
    return $narradores;
}

// Estación y lista de estación: temas de la estación mostrada. La lista global carga los suyos por página.
$otros = [];
$narradores = [];
if ($por_dia && ($vista === 'estacion' || ($vista === 'lista' && !$alcance_global))) {
    $tids = [];
    foreach ($por_dia as $lista) {
        foreach ($lista as $tema) {
            $tids[] = $tema['tid'];
        }
    }
    $otros = cron_cargar_otros($tids);
    $narradores = cron_cargar_narradores($tids);
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
if ($vista === 'roles') {
    $titulo = 'Marcar roles';
} elseif ($vista === 'anio') {
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
$chip_roles    = $puede_editar_roles
    ? '<a class="opg-chip cron-chip' . ($vista === 'roles' ? ' cron-chip--on' : '') . '" href="' . cron_url(['vista' => 'roles', 'alcance' => null, 'orden' => null, 'tipo' => null, 'rol' => null, 'cerrados' => null]) . '">Marcar roles</a>'
    : '';
$chip_ultimo   = ($ultimo && !$alcance_global && $vista !== 'roles')
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
$opciones_rol = '<option value="">Todos</option>'
    . '<option value="personaje"' . ($rol_filtro === 'personaje' ? ' selected' : '') . '>Personaje</option>'
    . '<option value="narrador"' . ($rol_filtro === 'narrador' ? ' selected' : '') . '>Narrador</option>';

// "Marcar roles" no participa de la navegación por estación/año ni de los filtros (diseño 7.3.3)
if ($vista === 'roles') {
    $controles = '
<div class="cron-controles">
    <div class="cron-nav"><h2 class="cron-periodo">' . htmlspecialchars_uni($titulo) . '</h2></div>
    <div class="cron-vistas">' . $chip_estacion . $chip_lista . $chip_anio . $chip_roles . '</div>
</div>';
} else {
    $controles = '
<div class="cron-controles">
    ' . $nav_html . '
    <div class="cron-vistas">' . $chip_estacion . $chip_lista . $chip_anio . $chip_roles . $chip_ultimo . '</div>
</div>
' . $subvistas . '
<form method="get" action="cronologia.php" class="cron-filtros">
    ' . $hidden_uid . $hidden_vista . $campos_fecha . '
    <div class="af-field"><label for="cron-tipo">Tipo de tema</label>
        <select id="cron-tipo" name="tipo">' . $opciones_tipo . '</select></div>
    <div class="af-field"><label for="cron-rol">Rol</label>
        <select id="cron-rol" name="rol">' . $opciones_rol . '</select></div>
    <label class="cron-check"><input type="checkbox" name="cerrados" value="0"' . ($ocultar_cerrados ? ' checked' : '') . '> Ocultar cerrados</label>
    <button class="btn-op btn-op--sm btn-op--primario" type="submit">Aplicar</button>
</form>';
}

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
            $mostrar_rol = cron_rol_mostrar($tema['prefijo']);
            // Con el filtro en "Todos" el tipo (y, si aplica, el rol) no son evidentes: se anteponen al título
            $etiqueta_tipo = $tipo === 0
                ? '<span class="cron-tipo cron-tipo--' . cron_tipo_clase($tema['prefijo']) . '">' . htmlspecialchars_uni($nombre_tipo) . '</span> '
                : '';
            $etiqueta_tipo .= $mostrar_rol
                ? '<span class="cron-rol cron-rol--' . $tema['rol'] . '">' . cron_rol_texto($tema['rol']) . '</span> '
                : '';
            $etiqueta_tipo .= '<span class="cron-tid">#' . $tema['tid'] . '</span> ';
            $titulo_extra = $mostrar_rol ? ' · ' . cron_rol_texto($tema['rol']) : '';
            $eventos .= '<a class="cron-evento" hx-boost="false" href="' . htmlspecialchars(cron_url_tema($tema['tid']), ENT_QUOTES, 'UTF-8')
                . '" title="' . htmlspecialchars_uni($nombre_tipo . $titulo_extra . ' · #' . $tema['tid'] . ' · ' . $tema['titulo'] . ($tema['isla'] !== '' ? ' — ' . $tema['isla'] : '')) . '">'
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
                'tid'      => $tema['tid'],
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
                'narradores' => $narradores[$tema['tid']] ?? [],
                'rolMostrar' => cron_rol_mostrar($tema['prefijo']),
                'rol'      => $tema['rol'],
                'rolTexto' => cron_rol_texto($tema['rol']),
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
        ? '<a class="cron-isla" hx-boost="false" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $n . '</a>'
        : '<span class="cron-isla">' . $n . '</span>';
}

// Una fila de la vista de lista (estación y global)
function cron_fila_html(array $tema, array $otros, array $narradores = [])
{
    global $CRON_ETIQUETAS;
    $lista_otros = $otros[$tema['tid']] ?? [];
    $otros_html = '';
    if ($lista_otros) {
        $otros_html = '<div class="cron-tema-otros">Con: ' . htmlspecialchars_uni(implode(', ', array_slice($lista_otros, 0, CRON_MAX_OTROS)))
            . (count($lista_otros) > CRON_MAX_OTROS ? ' +' . (count($lista_otros) - CRON_MAX_OTROS) : '') . '</div>';
    }
    $lista_narradores = $narradores[$tema['tid']] ?? [];
    $narradores_html = $lista_narradores
        ? '<div class="cron-tema-narra">Narra: ' . htmlspecialchars_uni(implode(', ', $lista_narradores)) . '</div>'
        : '';
    $rol_html = cron_rol_mostrar($tema['prefijo'])
        ? '<span class="cron-tag cron-rol cron-rol--' . $tema['rol'] . '">' . cron_rol_texto($tema['rol']) . '</span>'
        : '';
    return '<div class="cron-tema cron-fila"><span class="cron-fila-dia">Día ' . $tema['dia'] . '</span><div class="cron-fila-cuerpo">'
        . '<a class="cron-tema-titulo" hx-boost="false" href="' . htmlspecialchars(cron_url_tema($tema['tid']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars_uni($tema['titulo']) . '</a>'
        . '<div class="cron-tema-meta"><span class="cron-tid">#' . $tema['tid'] . '</span>' . cron_isla_html($tema['isla'], $tema['isla_url'])
        . '<span class="cron-tag cron-tipo cron-tipo--' . cron_tipo_clase($tema['prefijo']) . '">' . htmlspecialchars_uni($CRON_ETIQUETAS[$tema['prefijo']] ?? 'Sin tipo') . '</span>'
        . $rol_html
        . '<span class="cron-tag ' . ($tema['cerrado'] ? 'cron-tag--cerrado' : 'cron-tag--abierto') . '">' . ($tema['cerrado'] ? 'Cerrado' : 'Abierto') . '</span>'
        . '<span class="cron-tema-posts">' . $tema['posts'] . ($tema['posts'] === 1 ? ' post' : ' posts') . '</span></div>'
        . $otros_html . $narradores_html . '</div></div>';
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
            $filas .= cron_fila_html($tema, $otros, $narradores);
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
        $narradores = cron_cargar_narradores(array_column($tramo, 'tid'));

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
            $filas .= cron_fila_html($tema, $otros, $narradores);
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

// ─── Vista "Marcar roles" (diseño 7.3.3) ──────────────────────────────────────
if ($vista === 'roles') {
    $temas_editables = array_values(array_filter($temas_todos, function ($tm) {
        return in_array($tm['prefijo'], CRON_TIPOS_NARRABLES, true);
    }));
    usort($temas_editables, function ($a, $b) use ($indice_estacion) {
        return [$b['year'], $indice_estacion[$b['estacion']], $b['dia'], $b['dateline']]
            <=> [$a['year'], $indice_estacion[$a['estacion']], $a['dia'], $a['dateline']];
    });

    $aviso_guardado = $roles_guardados ? '<p class="cron-roles-aviso">Cambios guardados.</p>' : '';

    if (!$temas_editables) {
        $vista_html = $aviso_guardado . '<p class="opg-vacio">Este personaje no tiene temas de Aventura o Evento para marcar.</p>';
    } else {
        // Paginación de 100 en 100, igual que el modo lista global (diseño 7.3.2)
        $total_editables = count($temas_editables);
        $paginas_editables = max(1, (int)ceil($total_editables / CRON_POR_PAGINA));
        $pagina = min($pagina, $paginas_editables);
        $tramo_editables = array_slice($temas_editables, ($pagina - 1) * CRON_POR_PAGINA, CRON_POR_PAGINA);
        $desde_editables = ($pagina - 1) * CRON_POR_PAGINA + 1;
        $hasta_editables = $desde_editables + count($tramo_editables) - 1;

        $filas_roles = '';
        foreach ($tramo_editables as $tm) {
            $es_narrador = $tm['rol'] === 'narrador';
            $filas_roles .= '<div class="cron-tema cron-fila-rol"><div class="cron-fila-rol-info">'
                . '<a class="cron-tema-titulo" hx-boost="false" href="' . htmlspecialchars(cron_url_tema($tm['tid']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars_uni($tm['titulo']) . '</a>'
                . '<div class="cron-tema-meta"><span class="cron-tid">#' . $tm['tid'] . '</span>'
                . '<span>' . htmlspecialchars_uni(cron_fecha_texto($tm['dia'], $tm['estacion'], $tm['year'])) . '</span></div></div>'
                . '<div class="cron-rol-selector">'
                . '<label><input type="radio" name="roles[' . $tm['tid'] . ']" value="personaje"' . (!$es_narrador ? ' checked' : '') . '> Personaje</label>'
                . '<label><input type="radio" name="roles[' . $tm['tid'] . ']" value="narrador"' . ($es_narrador ? ' checked' : '') . '> Narrador</label>'
                . '</div></div>';
        }
        $pag_roles = cron_paginacion_html($pagina, $paginas_editables, $desde_editables, $hasta_editables, $total_editables);
        $vista_html = $aviso_guardado . $pag_roles . '<form method="post" hx-boost="false" action="'
            . cron_url(['vista' => 'roles', 'tipo' => null, 'rol' => null, 'cerrados' => null, 'pagina' => $pagina > 1 ? $pagina : null]) . '" class="cron-roles-form">'
            . '<input type="hidden" name="action" value="guardar_roles">'
            . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8') . '">'
            . $hidden_uid
            . '<div class="cron-roles-guardar"><button class="btn-op btn-op--sm btn-op--primario" type="submit">Guardar cambios</button></div>'
            . '<div class="cron-cuadro"><div class="cron-barra">' . htmlspecialchars_uni($titulo) . '</div>'
            . '<div class="cron-cuerpo cron-lista">' . $filas_roles . '</div></div>'
            . '<div class="cron-roles-guardar"><button class="btn-op btn-op--sm btn-op--primario" type="submit">Guardar cambios</button></div>'
            . '</form>' . $pag_roles;
    }
}

// ─── Mensaje si no hay temas que mostrar ─────────────────────────────────────
$mensaje_vacio = '';
if ($vista !== 'roles' && !$temas) {
    $mensaje_vacio = $hay_temas_fechados
        ? '<p class="opg-vacio">Ningún tema coincide con los filtros.</p>'
        : '<p class="opg-vacio">Sin temas registrados para este personaje.</p>';
}

$chip_mia = $uid !== $viewer_uid
    ? '<a class="opg-chip cron-chip" href="cronologia.php">Ver mi cronología</a>'
    : '';

// ─── Guía plegable "Cómo funciona la cronología" (mismo formato que la Bitácora) ─
// id + hx-preserve: htmx conserva el bloque (y si está abierto o cerrado) al cambiar de vista.
$cron_guia = <<<'HTML'
<details class="cron-guia" id="cron-guia" hx-preserve="true">
    <summary>
        <span><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Cómo funciona la cronología</span>
        <i class="fa-solid fa-chevron-down cron-guia__flecha" aria-hidden="true"></i>
    </summary>
    <div class="cron-guia__contenido">
        <p class="cron-guia__intro"><strong>La Cronología ubica en un calendario los temas de rol de un personaje,</strong> según su fecha (año, estación, día). Abre en la estación de su último tema; te movés por el tiempo sin recargar.</p>

        <div class="cron-guia__secciones">
            <section>
                <h3><span>1</span> Qué aparece</h3>
                <ul>
                    <li>Temas de la zona de rol donde el personaje publicó, con fecha válida (año ≥ 700, día 1-90). Sin fecha, no aparece.</li>
                    <li>Solo ves los que tu cuenta puede abrir en el foro.</li>
                </ul>
            </section>

            <section>
                <h3><span>2</span> El calendario</h3>
                <ul>
                    <li>90 días por estación, 4 estaciones por año. Sin semanas.</li>
                    <li>Los días con temas se resaltan; con varios, un contador y orden por fecha de creación.</li>
                    <li>La fecha la pone quien crea el tema; el staff la corrige si hace falta.</li>
                </ul>
            </section>

            <section>
                <h3><span>3</span> Las vistas</h3>
                <ul>
                    <li><strong>Estación:</strong> los 90 días. <strong>Año:</strong> las 4 estaciones de un vistazo.</li>
                    <li><strong>Lista:</strong> títulos completos, de esta estación o de todo el historial (por año y estación, paginado de 100, orden invertible).</li>
                    <li><strong>Marcar roles:</strong> en tus Aventuras/Evento, marca si participaste con tu personaje o como narrador (el staff puede corregir el de otros).</li>
                </ul>
            </section>

            <section>
                <h3><span>4</span> Ventana del día</h3>
                <ul>
                    <li>Hover o clic en un día con temas los muestra; clic lo fija con enlaces activos. Se cierra con la X, Escape o clic afuera.</li>
                    <li>Cada tema: <strong>#tid</strong>, isla, tipo, estado, posts, otros participantes y, si hay narrador, quién narra.</li>
                </ul>
            </section>

            <section>
                <h3><span>5</span> Filtros y personajes</h3>
                <ul>
                    <li>Filtra por <strong>tipo</strong>, por <strong>rol</strong> (con "Todos" se ve el color de cada uno) y oculta cerrados.</li>
                    <li>El buscador de la cabecera abre la cronología de otro personaje por nombre, apodo o ID.</li>
                    <li><strong>Ver mi cronología</strong> vuelve a la tuya; <strong>Ver ficha</strong> abre la del personaje.</li>
                </ul>
            </section>
        </div>

        <p class="cron-guia__nota"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> <strong>En resumen:</strong> se arma sola con tus temas y su fecha; nada que configurar. Si un tema no aparece, revisa que tenga fecha válida.</p>
    </div>
</details>
HTML;

$calendar_html = '
<header class="cron-cabecera">
    <h1 class="cron-titulo">Cronología de ' . $nombre_personaje . '</h1>
    <div class="cron-buscar opg-buscar-caja">
        <input type="search" id="cron-buscar" name="q" autocomplete="off" placeholder="Buscar otro personaje" aria-label="Buscar otro personaje"
            aria-autocomplete="list" aria-controls="cron-resultados"
            hx-get="cronologia.php" hx-trigger="keyup changed delay:250ms" hx-target="#cron-resultados" hx-select="unset" hx-swap="innerHTML" hx-push-url="false" hx-indicator="closest .cron-buscar" hx-vals=\'{"action":"buscar_personajes"}\'>
        <div class="opg-resultados cron-resultados" id="cron-resultados" role="listbox"></div>
    </div>
    <div class="cron-cabecera-links">' . $chip_mia . '<a class="opg-volver cron-volver" hx-boost="false" href="personaje.php?uid=' . $uid . '">&larr; Ver ficha</a></div>
</header>
' . $cron_guia . $controles . $mensaje_vacio . $vista_html;

// En el modo global no hay una estación única: acento neutro (naranja) para la barra y los chips de día
$cron_clase = $alcance_global ? '' : 'season-' . $t;

// Un solo script, siempre presente y fuera de #cron-raiz: usa delegación de eventos
// sobre document porque htmx reemplaza los elementos de la cronología en cada cambio.
$cronologia_script = '<script>' . <<<'JS'
(function () {
    var fijado = null;        // casilla fijada con clic
    var temporizador = null;  // retraso del hover

    function el(tag, clase, texto) {
        var n = document.createElement(tag);
        if (clase) { n.className = clase; }
        if (texto !== undefined) { n.textContent = texto; }
        return n;
    }
    function cerca(e, selector) { return e.target && e.target.closest ? e.target.closest(selector) : null; }
    function overlay() { return document.getElementById('cron-overlay'); }
    function datos() {
        var g = document.getElementById('cron-grid');
        if (!g) { return null; }
        try { return JSON.parse(g.getAttribute('data-dias')); } catch (e) { return null; }
    }

    // ── Overlay del día ─────────────────────────────────────────────────────
    function pintar(dia, conBoton) {
        var d = datos(), ov = overlay();
        if (!d || !ov || !d.temas[dia]) { return false; }
        ov.textContent = '';
        var barra = el('div', 'cron-barra cron-overlay-barra');
        barra.appendChild(el('span', '', d.fechas[dia]));
        if (conBoton) {
            var cerrar = el('button', 'cron-overlay-cerrar', '×');
            cerrar.type = 'button';
            cerrar.setAttribute('aria-label', 'Cerrar');
            barra.appendChild(cerrar);
        }
        ov.appendChild(barra);

        var cuerpo = el('div', 'cron-cuerpo cron-lista');
        d.temas[dia].forEach(function (t) {
            var fila = el('div', 'cron-tema');
            var enlace = el('a', 'cron-tema-titulo', t.titulo);
            enlace.href = t.url;
            enlace.setAttribute('hx-boost', 'false');
            fila.appendChild(enlace);

            var meta = el('div', 'cron-tema-meta');
            meta.appendChild(el('span', 'cron-tid', '#' + t.tid));
            if (t.isla) {
                var isla = el(t.islaUrl ? 'a' : 'span', 'cron-isla', t.isla);
                if (t.islaUrl) { isla.href = t.islaUrl; isla.setAttribute('hx-boost', 'false'); }
                meta.appendChild(isla);
            }
            meta.appendChild(el('span', 'cron-tag cron-tipo cron-tipo--' + t.tipoClase, t.tipo));
            if (t.rolMostrar) {
                meta.appendChild(el('span', 'cron-tag cron-rol cron-rol--' + t.rol, t.rolTexto));
            }
            meta.appendChild(el('span', 'cron-tag ' + (t.cerrado ? 'cron-tag--cerrado' : 'cron-tag--abierto'), t.cerrado ? 'Cerrado' : 'Abierto'));
            meta.appendChild(el('span', 'cron-tema-posts', t.posts + (t.posts === 1 ? ' post' : ' posts')));
            fila.appendChild(meta);

            if (t.otros.length) {
                fila.appendChild(el('div', 'cron-tema-otros', 'Con: ' + t.otros.join(', ') + (t.masOtros ? ' +' + t.masOtros : '')));
            }
            if (t.narradores && t.narradores.length) {
                fila.appendChild(el('div', 'cron-tema-narra', 'Narra: ' + t.narradores.join(', ')));
            }
            cuerpo.appendChild(fila);
        });
        ov.appendChild(cuerpo);
        return true;
    }

    function ocultar() {
        clearTimeout(temporizador);
        var ov = overlay();
        if (ov) { ov.hidden = true; ov.classList.remove('cron-overlay--fijo'); }
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
            var ov = overlay();
            if (ov && pintar(celda.getAttribute('data-dia'), false)) { ov.hidden = false; }
        }, 150);
    }
    function fijar(celda) {
        clearTimeout(temporizador);
        if (fijado) { fijado.classList.remove('cron-dia--sel'); }
        var ov = overlay();
        if (!ov || !pintar(celda.getAttribute('data-dia'), true)) { return; }
        fijado = celda;
        celda.classList.add('cron-dia--sel');
        ov.classList.add('cron-overlay--fijo');
        ov.hidden = false;
    }

    document.addEventListener('mouseover', function (e) {
        var c = cerca(e, '.cron-dia--con');
        if (c) { mostrarHover(c); }
    });
    document.addEventListener('mouseout', function (e) {
        var c = cerca(e, '.cron-dia--con');
        if (!c || fijado) { return; }
        if (e.relatedTarget && c.contains(e.relatedTarget)) { return; }
        ocultar();
    });
    document.addEventListener('focusin', function (e) {
        var c = cerca(e, '.cron-dia--con');
        if (c) { mostrarHover(c); }
    });
    document.addEventListener('focusout', function (e) {
        if (!fijado && cerca(e, '.cron-dia--con')) { ocultar(); }
    });

    // ── Clics y teclado (overlay y buscador) ────────────────────────────────
    function ir(fid) { window.location.href = 'cronologia.php?uid=' + encodeURIComponent(fid); }

    document.addEventListener('click', function (e) {
        var op = cerca(e, '.cron-opcion');
        if (op) { ir(op.getAttribute('data-fid')); return; }

        if (cerca(e, '.cron-overlay-cerrar')) { soltar(); return; }
        if (cerca(e, 'a')) { return; }
        var c = cerca(e, '.cron-dia--con');
        if (c) { fijar(c); return; }

        if (fijado && !cerca(e, '#cron-overlay')) { soltar(); }
        var lista = document.getElementById('cron-resultados');
        if (lista && !cerca(e, '.cron-buscar')) { lista.textContent = ''; }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            soltar();
            var l = document.getElementById('cron-resultados');
            if (l) { l.textContent = ''; }
            return;
        }
        if (e.target && e.target.id === 'cron-buscar' && e.key === 'Enter') {
            e.preventDefault();
            var primera = document.querySelector('#cron-resultados .cron-opcion');
            if (primera) { ir(primera.getAttribute('data-fid')); }
            return;
        }
        if (e.key === 'Enter' || e.key === ' ') {
            var c = cerca(e, '.cron-dia--con');
            if (c) { e.preventDefault(); fijar(c); }
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'cron-buscar' && e.target.value.trim().length < 3) {
            var l = document.getElementById('cron-resultados');
            if (l) { l.textContent = ''; }
        }
    });

    // ── htmx: cambios de contenido sin recargar la página ───────────────────
    document.addEventListener('htmx:beforeSwap', function (e) {
        var d = e.detail;
        if (!d || !d.target || d.target.id !== 'cron-raiz') { return; }
        // Sin #cron-raiz en la respuesta (sesión caducada, error de MyBB): navegación normal
        if (typeof d.serverResponse === 'string' && d.serverResponse.indexOf('id="cron-raiz"') === -1) {
            d.shouldSwap = false;
            window.location.href = d.pathInfo.finalRequestPath || d.pathInfo.requestPath;
        }
    });
    document.addEventListener('htmx:afterSettle', function (e) {
        var d = e.detail;
        if (!d || !d.target || d.target.id !== 'cron-raiz') { return; }   // no para el typeahead
        clearTimeout(temporizador);
        fijado = null;
        var t = document.querySelector('#cron-raiz .cron-periodo');
        if (t) { t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); }
    });
})();
JS
    . '</script>';

add_breadcrumb('Cronología', 'cronologia.php');

global $templates, $headerinclude, $header, $footer;
$cronologia = '';
eval('$cronologia = "' . $templates->get('op_cronologia') . '";');

output_page($cronologia);
