<?php
/**
 * Aventuras por personaje
 *
 * Listado de solo lectura de todas las peticiones de la Calculadora de Tiers,
 * con filtro opcional por ficha participante.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'aventuras_personaje.php');
require_once "./../global.php";

global $templates, $mybb, $db;

define('AVENTURAS_PERSONAJE_POR_PAGINA', 30);

function aventuras_e($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function aventuras_url_segura($url)
{
    $url = trim((string)$url);
    $partes = $url !== '' ? parse_url($url) : false;
    if (!is_array($partes) || empty($partes['scheme']) || !in_array(strtolower($partes['scheme']), array('http', 'https'), true)) {
        return '';
    }
    return aventuras_e($url);
}

function aventuras_jugadores($json)
{
    $jugadores = json_decode((string)$json, true);
    if (!is_array($jugadores)) {
        return array();
    }

    $resultado = array();
    foreach ($jugadores as $jugador) {
        if (!is_array($jugador)) {
            continue;
        }
        $nombre = trim((string)($jugador['nombre'] ?? ''));
        $fid = (int)($jugador['uid'] ?? 0);
        $resultado[] = array(
            'fid' => $fid,
            'nombre' => $nombre !== '' ? $nombre : 'Ficha #' . $fid,
            'nivel' => isset($jugador['nivel']) ? (int)$jugador['nivel'] : 0,
        );
    }
    return $resultado;
}

$tablaAventuras = $db->table_prefix . 'op_peticionAventuras';
$tablaUsuarios = $db->table_prefix . 'users';
$tablaFichas = $db->table_prefix . 'op_fichas';

// Typeahead de fichas. El texto requiere 3 caracteres; un ID puede buscarse directamente.
if ($mybb->get_input('action') === 'buscar_personajes') {
    $termino = trim($mybb->get_input('q', MyBB::INPUT_STRING));
    if ($termino === '') {
        $termino = trim($mybb->get_input('personaje', MyBB::INPUT_STRING));
    }
    if (mb_strlen($termino) < 3 && !ctype_digit($termino)) {
        header('Content-Type: text/html; charset=utf-8');
        exit;
    }

    $like = $db->escape_string(addcslashes($termino, '%_'));
    $exacto = $db->escape_string($termino);
    $porId = ctype_digit($termino) ? ' OR f.`fid` = ' . (int)$termino : '';
    $consulta = $db->query("SELECT f.`fid`, f.`nombre`, f.`apodo`
        FROM `{$tablaFichas}` f
        WHERE f.`nombre` LIKE '%{$like}%'
           OR f.`apodo` LIKE '%{$like}%'
           {$porId}
        ORDER BY CASE
            WHEN f.`nombre` = '{$exacto}' OR f.`apodo` = '{$exacto}' THEN 0
            ELSE 1
        END, f.`nombre` ASC
        LIMIT 12");

    $items = '';
    while ($ficha = $db->fetch_array($consulta)) {
        $nombre = trim((string)$ficha['nombre']);
        $apodo = trim((string)$ficha['apodo']);
        $label = $nombre . ($apodo !== '' && $apodo !== $nombre ? ' · ' . $apodo : '') . ' (#' . (int)$ficha['fid'] . ')';
        $items .= '<button type="button" class="opg-resultado personaje-opcion" role="option"'
            . ' data-fid="' . (int)$ficha['fid'] . '" data-label="' . aventuras_e($label) . '">'
            . aventuras_e($label) . '</button>';
    }

    header('Content-Type: text/html; charset=utf-8');
    echo $items;
    exit;
}

$personajeTexto = trim($mybb->get_input('personaje', MyBB::INPUT_STRING));
$personajeFid = max(0, $mybb->get_input('fid', MyBB::INPUT_INT));
$vistaSolicitada = $mybb->get_input('vista', MyBB::INPUT_STRING);
$vista = in_array($vistaSolicitada, array('lista', 'autonarradas', 'top'), true) ? $vistaSolicitada : 'lista';
$personaje = null;

if ($personajeFid > 0) {
    $consultaFicha = $db->simple_select('op_fichas', 'fid,nombre,apodo', 'fid=' . $personajeFid, array('limit' => 1));
    $personaje = $db->fetch_array($consultaFicha);
} elseif ($personajeTexto !== '') {
    $exacto = $db->escape_string($personajeTexto);
    $porId = ctype_digit($personajeTexto) ? ' OR fid=' . (int)$personajeTexto : '';
    $consultaFicha = $db->simple_select(
        'op_fichas',
        'fid,nombre,apodo',
        "nombre='{$exacto}' OR apodo='{$exacto}'{$porId}",
        array('limit' => 1)
    );
    $personaje = $db->fetch_array($consultaFicha);
    if ($personaje) {
        $personajeFid = (int)$personaje['fid'];
    }
}

$filtroPersonaje = '';
if ($personajeFid > 0 && $personaje) {
    $filtroPersonaje = "JSON_CONTAINS(pa.`jugadores_json`, JSON_OBJECT('uid', {$personajeFid}))";
}

$condicionAutonarrada = "COALESCE(pa.`narrador_fid`, 0) > 0
    AND pa.`tier_seleccionado` BETWEEN 1 AND 3
    AND JSON_LENGTH(pa.`jugadores_json`) = 1
    AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(pa.`jugadores_json`, '$[0].uid')) AS UNSIGNED), 0) = pa.`narrador_fid`";
$whereRegular = ' WHERE NOT (' . $condicionAutonarrada . ')';
$whereAutonarrada = ' WHERE ' . $condicionAutonarrada;
if ($filtroPersonaje !== '') {
    $whereRegular .= ' AND ' . $filtroPersonaje;
    $whereAutonarrada .= ' AND ' . $filtroPersonaje;
}

$totalQuery = $db->query("SELECT COUNT(*) AS total FROM `{$tablaAventuras}` pa{$whereRegular}");
$total = (int)$db->fetch_field($totalQuery, 'total');
$paginas = max(1, (int)ceil($total / AVENTURAS_PERSONAJE_POR_PAGINA));
$pagina = min(max(1, $mybb->get_input('pagina', MyBB::INPUT_INT)), $paginas);
$offset = ($pagina - 1) * AVENTURAS_PERSONAJE_POR_PAGINA;

$query = $db->query("SELECT pa.*, u.username AS solicitante
    FROM `{$tablaAventuras}` pa
    LEFT JOIN `{$tablaUsuarios}` u ON u.uid = pa.uid
    {$whereRegular}
    ORDER BY pa.`created_at` DESC, pa.`id` DESC
    LIMIT {$offset}, " . AVENTURAS_PERSONAJE_POR_PAGINA);

$estados = array(
    0 => array('clave' => 'enviada', 'texto' => 'Enviada', 'conteo' => 'Aventuras enviadas'),
    1 => array('clave' => 'progreso', 'texto' => 'En progreso', 'conteo' => 'Aventuras en progreso'),
    2 => array('clave' => 'denegada', 'texto' => 'Denegada', 'conteo' => 'Aventuras denegadas'),
    3 => array('clave' => 'finalizada', 'texto' => 'Finalizada', 'conteo' => 'Aventuras finalizadas'),
    4 => array('clave' => 'sin-narrador', 'texto' => 'Pendiente de narrador', 'conteo' => 'Pendientes de narrador'),
    5 => array('clave' => 'borrado', 'texto' => 'Pendiente de borrado', 'conteo' => 'Pendientes de borrado'),
);

$conteoEstados = array_fill(0, 6, 0);
$conteoTiers = array_fill(1, 10, 0);
$queryConteos = $db->query("SELECT pa.`estado`, pa.`tier_seleccionado`, COUNT(*) AS total
    FROM `{$tablaAventuras}` pa
    {$whereRegular}
    GROUP BY pa.`estado`, pa.`tier_seleccionado`");
while ($conteo = $db->fetch_array($queryConteos)) {
    $estadoConteo = (int)$conteo['estado'];
    $tierConteo = (int)$conteo['tier_seleccionado'];
    $cantidad = (int)$conteo['total'];
    if (array_key_exists($estadoConteo, $conteoEstados)) {
        $conteoEstados[$estadoConteo] += $cantidad;
    }
    if ($tierConteo >= 1 && $tierConteo <= 10) {
        $conteoTiers[$tierConteo] += $cantidad;
    }
}

$aventuras_conteo_estados = '';
foreach ($estados as $codigo => $estado) {
    $aventuras_conteo_estados .= '<div class="aventuras-conteo__item">'
        . '<span class="aventuras-conteo__valor">' . $conteoEstados[$codigo] . '</span>'
        . '<span class="aventuras-conteo__etiqueta">' . aventuras_e($estado['conteo']) . '</span>'
        . '</div>';
}

$aventuras_conteo_tiers = '';
foreach ($conteoTiers as $numeroTier => $cantidad) {
    $aventuras_conteo_tiers .= '<div class="aventuras-tier-conteo">'
        . '<span>T' . $numeroTier . '</span><strong>' . $cantidad . '</strong>'
        . '</div>';
}

$conteoTiersAutonarradas = array_fill(1, 3, 0);
$queryConteosAutonarradas = $db->query("SELECT pa.`tier_seleccionado`, COUNT(*) AS total
    FROM `{$tablaAventuras}` pa
    {$whereAutonarrada}
    GROUP BY pa.`tier_seleccionado`");
while ($conteo = $db->fetch_array($queryConteosAutonarradas)) {
    $tierConteo = (int)$conteo['tier_seleccionado'];
    if (isset($conteoTiersAutonarradas[$tierConteo])) {
        $conteoTiersAutonarradas[$tierConteo] = (int)$conteo['total'];
    }
}
$aventuras_autonarradas_conteo_tiers = '';
foreach ($conteoTiersAutonarradas as $numeroTier => $cantidad) {
    $aventuras_autonarradas_conteo_tiers .= '<div class="aventuras-tier-conteo">'
        . '<span>T' . $numeroTier . '</span><strong>' . $cantidad . '</strong>'
        . '</div>';
}

// Ranking global: se calcula con los participantes de aventuras aprobadas/en
// progreso (1) y finalizadas (3), sin depender del filtro de la lista principal.
$ranking = array();
$queryRanking = $db->query("SELECT pa.`estado`, pa.`jugadores_json`
    FROM `{$tablaAventuras}` pa
    WHERE pa.`estado` IN (1, 3)
      AND NOT ({$condicionAutonarrada})");
while ($filaRanking = $db->fetch_array($queryRanking)) {
    $jugadoresRanking = aventuras_jugadores($filaRanking['jugadores_json'] ?? '[]');
    $vistos = array();
    foreach ($jugadoresRanking as $jugadorRanking) {
        $fidRanking = (int)$jugadorRanking['fid'];
        if ($fidRanking <= 0 || isset($vistos[$fidRanking])) {
            continue;
        }
        $vistos[$fidRanking] = true;
        if (!isset($ranking[$fidRanking])) {
            $ranking[$fidRanking] = array(
                'fid' => $fidRanking,
                'nombre' => $jugadorRanking['nombre'],
                'progreso' => 0,
                'finalizadas' => 0,
            );
        }
        if ((int)$filaRanking['estado'] === 1) {
            $ranking[$fidRanking]['progreso']++;
        } else {
            $ranking[$fidRanking]['finalizadas']++;
        }
    }
}

if ($ranking) {
    $fidsRanking = implode(',', array_map('intval', array_keys($ranking)));
    $queryNombresRanking = $db->query("SELECT `fid`, `nombre`, `apodo`
        FROM `{$tablaFichas}`
        WHERE `fid` IN ({$fidsRanking})");
    while ($fichaRanking = $db->fetch_array($queryNombresRanking)) {
        $fidRanking = (int)$fichaRanking['fid'];
        $nombreRanking = trim((string)$fichaRanking['nombre']);
        $apodoRanking = trim((string)$fichaRanking['apodo']);
        if ($apodoRanking !== '' && $apodoRanking !== $nombreRanking) {
            $nombreRanking .= ' · ' . $apodoRanking;
        }
        $ranking[$fidRanking]['nombre'] = $nombreRanking;
    }
}

$ranking = array_values($ranking);
usort($ranking, function ($a, $b) {
    $totalA = $a['progreso'] + $a['finalizadas'];
    $totalB = $b['progreso'] + $b['finalizadas'];
    if ($totalA !== $totalB) {
        return $totalB <=> $totalA;
    }
    if ($a['finalizadas'] !== $b['finalizadas']) {
        return $b['finalizadas'] <=> $a['finalizadas'];
    }
    return strcasecmp($a['nombre'], $b['nombre']);
});
$ranking = array_slice($ranking, 0, 20);

$aventuras_top_20 = '';
foreach ($ranking as $indice => $puesto) {
    $totalPuesto = $puesto['progreso'] + $puesto['finalizadas'];
    $fichaUrl = '/op/personaje.php?uid=' . (int)$puesto['fid'];
    $aventuras_top_20 .= '<div class="ranking-fila">'
        . '<span class="ranking-puesto">' . ($indice + 1) . '</span>'
        . '<a class="ranking-personaje" href="' . $fichaUrl . '">' . aventuras_e($puesto['nombre']) . ' <small>#' . (int)$puesto['fid'] . '</small></a>'
        . '<span class="ranking-cifra"><strong>' . (int)$puesto['progreso'] . '</strong><small>En progreso</small></span>'
        . '<span class="ranking-cifra"><strong>' . (int)$puesto['finalizadas'] . '</strong><small>Finalizadas</small></span>'
        . '<span class="ranking-total"><strong>' . $totalPuesto . '</strong><small>Total</small></span>'
        . '</div>';
}
if ($aventuras_top_20 === '') {
    $aventuras_top_20 = '<p class="opg-vacio">Todavía no hay aventuras en progreso o finalizadas.</p>';
}

function aventuras_renderizar_fila($aventura, $estados, $personajeFid)
{
    $jugadores = aventuras_jugadores($aventura['jugadores_json'] ?? '[]');
    $jugadoresHtml = '';
    foreach ($jugadores as $jugador) {
        $nivel = $jugador['nivel'] > 0 ? '<span class="aventura-nivel">Nv. ' . $jugador['nivel'] . '</span>' : '';
        $destacado = $personajeFid > 0 && $jugador['fid'] === $personajeFid ? ' aventura-jugador--seleccionado' : '';
        if ($jugador['fid'] > 0) {
            $fichaUrl = '/op/personaje.php?uid=' . (int)$jugador['fid'];
            $jugadoresHtml .= '<a class="aventura-jugador' . $destacado . '" href="' . $fichaUrl . '">'
                . aventuras_e($jugador['nombre']) . $nivel . '</a>';
        } else {
            $jugadoresHtml .= '<span class="aventura-jugador">' . aventuras_e($jugador['nombre']) . $nivel . '</span>';
        }
    }
    if ($jugadoresHtml === '') {
        $jugadoresHtml = '<span class="aventura-sin-dato">Sin participantes registrados</span>';
    }

    $narrador = trim((string)($aventura['narrador_nombre'] ?? ''));
    $narrador = $narrador !== '' ? $narrador : 'Sin narrador registrado';
    $narradorFid = (int)($aventura['narrador_fid'] ?? 0);
    $narradorHtml = $narradorFid > 0
        ? '<a class="aventura-personaje-link" href="/op/personaje.php?uid=' . $narradorFid . '">' . aventuras_e($narrador) . '</a>'
        : aventuras_e($narrador);
    $descripcion = trim((string)($aventura['descripcion_tier'] ?? ''));
    $comentario = trim((string)($aventura['comentario_publico'] ?? ''));
    $detalle = '';
    if ($descripcion !== '' || $comentario !== '') {
        $detalle = '<details class="aventura-detalle"><summary>Ver detalles</summary>';
        if ($descripcion !== '') {
            $detalle .= '<div><strong>Descripción:</strong> ' . nl2br(aventuras_e($descripcion)) . '</div>';
        }
        if ($comentario !== '') {
            $detalle .= '<div><strong>Comentario:</strong> ' . nl2br(aventuras_e($comentario)) . '</div>';
        }
        $detalle .= '</details>';
    }

    $url = aventuras_url_segura($aventura['aventura_url'] ?? '');
    $enlace = $url !== ''
        ? '<a class="btn-op btn-op--sm btn-op--primario" href="' . $url . '" target="_blank" rel="noopener">Abrir tema</a>'
        : '<span class="aventura-sin-dato">Sin enlace</span>';

    $estadoCodigo = (int)($aventura['estado'] ?? 0);
    $estado = $estados[$estadoCodigo] ?? $estados[0];
    $fecha = my_date('d/m/Y', (int)$aventura['created_at']);
    $infra = !empty($aventura['inframundo']) ? '<span class="aventura-etiqueta aventura-etiqueta--infra">Inframundo</span>' : '';
    $tier = min(10, max(1, (int)$aventura['tier_seleccionado']));

    return '<article class="aventura-fila">'
        . '<div class="aventura-principal">'
        . '<div class="aventura-identidad"><span class="aventura-id">#' . (int)$aventura['id'] . '</span>'
        . '<span class="aventura-tier">T' . $tier . '</span>'
        . '<span class="aventura-estado aventura-estado--' . aventuras_e($estado['clave']) . '">' . aventuras_e($estado['texto']) . '</span>'
        . $infra . '</div>'
        . '<div class="aventura-meta"><span><strong>Narrador:</strong> ' . $narradorHtml . '</span>'
        . '<span><strong>Solicitante:</strong> ' . aventuras_e($aventura['solicitante'] ?? 'Desconocido') . '</span>'
        . '<span><strong>Enviada:</strong> ' . aventuras_e($fecha) . '</span></div>'
        . '</div>'
        . '<div class="aventura-jugadores">' . $jugadoresHtml . '</div>'
        . $detalle
        . '<div class="aventura-accion">' . $enlace . '</div>'
        . '</article>';
}

$aventuras_lista = '';
while ($aventura = $db->fetch_array($query)) {
    $aventuras_lista .= aventuras_renderizar_fila($aventura, $estados, $personajeFid);
}
if ($aventuras_lista === '') {
    $aventuras_lista = '<p class="opg-vacio">No hay aventuras convencionales registradas en esta selección.</p>';
}

$totalAutonarradasQuery = $db->query("SELECT COUNT(*) AS total FROM `{$tablaAventuras}` pa{$whereAutonarrada}");
$totalAutonarradas = (int)$db->fetch_field($totalAutonarradasQuery, 'total');
$paginasAutonarradas = max(1, (int)ceil($totalAutonarradas / AVENTURAS_PERSONAJE_POR_PAGINA));
$paginaAutonarradas = min(max(1, $mybb->get_input('pagina_auto', MyBB::INPUT_INT)), $paginasAutonarradas);
$offsetAutonarradas = ($paginaAutonarradas - 1) * AVENTURAS_PERSONAJE_POR_PAGINA;
$queryAutonarradas = $db->query("SELECT pa.*, u.username AS solicitante
    FROM `{$tablaAventuras}` pa
    LEFT JOIN `{$tablaUsuarios}` u ON u.uid = pa.uid
    {$whereAutonarrada}
    ORDER BY pa.`created_at` DESC, pa.`id` DESC
    LIMIT {$offsetAutonarradas}, " . AVENTURAS_PERSONAJE_POR_PAGINA);

$aventuras_autonarradas_lista = '';
while ($aventura = $db->fetch_array($queryAutonarradas)) {
    $aventuras_autonarradas_lista .= aventuras_renderizar_fila($aventura, $estados, $personajeFid);
}
if ($aventuras_autonarradas_lista === '') {
    $aventuras_autonarradas_lista = '<p class="opg-vacio">No hay aventuras autonarradas registradas en esta selección.</p>';
}

$queryArgs = array();
if ($personajeFid > 0) $queryArgs['fid'] = $personajeFid;
if ($personajeTexto !== '') $queryArgs['personaje'] = $personajeTexto;
$queryString = $queryArgs ? '&' . http_build_query($queryArgs) : '';

$aventuras_paginacion = '';
if ($paginas > 1) {
    $aventuras_paginacion = '<nav class="paginacion" aria-label="Paginación">'
        . ($pagina > 1 ? '<a class="opg-chip" href="?pagina=' . ($pagina - 1) . $queryString . '">&larr; Más recientes</a>' : '<span></span>')
        . '<span>Página ' . $pagina . ' de ' . $paginas . '</span>'
        . ($pagina < $paginas ? '<a class="opg-chip" href="?pagina=' . ($pagina + 1) . $queryString . '">Más antiguas &rarr;</a>' : '<span></span>')
        . '</nav>';
}

$aventuras_autonarradas_paginacion = '';
if ($paginasAutonarradas > 1) {
    $aventuras_autonarradas_paginacion = '<nav class="paginacion" aria-label="Paginación de aventuras autonarradas">'
        . ($paginaAutonarradas > 1 ? '<a class="opg-chip" href="?vista=autonarradas&amp;pagina_auto=' . ($paginaAutonarradas - 1) . $queryString . '#aventuras-autonarradas">&larr; Más recientes</a>' : '<span></span>')
        . '<span>Página ' . $paginaAutonarradas . ' de ' . $paginasAutonarradas . '</span>'
        . ($paginaAutonarradas < $paginasAutonarradas ? '<a class="opg-chip" href="?vista=autonarradas&amp;pagina_auto=' . ($paginaAutonarradas + 1) . $queryString . '#aventuras-autonarradas">Más antiguas &rarr;</a>' : '<span></span>')
        . '</nav>';
}

$personajeEtiqueta = '';
if ($personaje) {
    $personajeEtiqueta = trim((string)$personaje['nombre']);
    $apodo = trim((string)$personaje['apodo']);
    if ($apodo !== '' && $apodo !== $personajeEtiqueta) {
        $personajeEtiqueta .= ' · ' . $apodo;
    }
    $personajeEtiqueta .= ' (#' . (int)$personaje['fid'] . ')';
} elseif ($personajeTexto !== '') {
    $personajeEtiqueta = $personajeTexto;
}

$aventuras_personaje = aventuras_e($personajeEtiqueta);
$aventuras_fid = $personaje ? (int)$personaje['fid'] : 0;
$aventuras_resultado = $personaje
        ? 'Aventuras de ' . aventuras_e($personajeEtiqueta) . '. Hay ' . $total . ' resultados en todos los estados.'
        : 'Todas las aventuras registradas. Hay ' . $total . ' resultados.';
$aventuras_autonarradas_resultado = $personaje
    ? 'Aventuras autonarradas de ' . aventuras_e($personajeEtiqueta) . '. Hay ' . $totalAutonarradas . ' resultados.'
    : 'Todas las aventuras autonarradas. Hay ' . $totalAutonarradas . ' resultados.';
$aventuras_limpiar = ($personajeTexto !== '' || $personajeFid > 0)
    ? '<a class="opg-chip" href="aventuras_personaje.php">Ver todas</a>'
    : '';
$aventuras_vista_inicial = $vista;
$aventuras_url_top = 'aventuras_personaje.php?vista=top#top-aventureros';
$aventuras_url_autonarradas = 'aventuras_personaje.php?vista=autonarradas';
$aventuras_url_lista = 'aventuras_personaje.php';
if ($queryArgs) {
    $aventuras_url_lista .= '?' . http_build_query($queryArgs) . '#lista-aventuras';
    $aventuras_url_autonarradas .= '&' . http_build_query($queryArgs) . '#aventuras-autonarradas';
} else {
    $aventuras_url_lista .= '#lista-aventuras';
    $aventuras_url_autonarradas .= '#aventuras-autonarradas';
}

eval("\$page = \"".$templates->get("op_aventuras_personaje")."\";");
output_page($page);
