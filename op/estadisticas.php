<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'estadisticas.php');
require_once "./../global.php";
require "./../inc/config.php";

global $templates, $mybb, $db;
$uid = (int)$mybb->user['uid'];
$mostrarActividadPersonaje = $uid === 10;
$username = $mybb->user['username'];
$tiempo = 14;
$accion = 'censo';

if ($mybb->get_input('tiempo')) {
    $tiempo = $mybb->get_input('tiempo', MyBB::INPUT_INT);
}

if ($mybb->get_input('accion')) {
    $accion = $mybb->get_input('accion');
}

if ($accion === 'aventureros') {
    header('Location: /op/aventuras_personaje.php');
    exit;
}

$accionesPermitidas = array('censo', 'mas-posts', 'mas-experiencia', 'mas-temas', 'mas-reputacion', 'comparar', 'consultar-ranking');
if (!in_array($accion, $accionesPermitidas, true)) {
    $accion = 'censo';
}

if (!in_array($tiempo, array(1, 2, 7, 14, 28), true)) {
    $tiempo = 14;
}
$estadisticasAhora = defined('TIME_NOW') ? (int)TIME_NOW : time();

// Cuentas que no aparecen ni cuentan en ninguna pagina de estadisticas (rankings, censo, totales y buscador).
define('ESTADISTICAS_UIDS_OCULTOS', '974, 973, 258, 5');

function estadisticasCensoFacciones() {
    return array(
        'Pirata' => array('slug' => 'pirata', 'nombre' => 'Piratas', 'color' => '#a3180b', 'logo' => '/images/op/uploads/PirataLogo_One_Piece_Gaiden_Foro_Rol.png'),
        'Marina' => array('slug' => 'marina', 'nombre' => 'Marines', 'color' => '#0055bb', 'logo' => '/images/op/uploads/MarineLogo_One_Piece_Gaiden_Foro_Rol.png'),
        'CipherPol' => array('slug' => 'cipher-pol', 'nombre' => 'Cipher Pol', 'color' => '#252b63', 'logo' => '/images/op/uploads/GobiernoLogo_One_Piece_Gaiden_Foro_Rol.png'),
        'Revolucionario' => array('slug' => 'revolucionario', 'nombre' => 'Revolucionarios', 'color' => '#9a5c18', 'logo' => '/images/op/uploads/RevolucionarioLogo_One_Piece_Gaiden_Foro_Rol.png'),
        'Cazadores' => array('slug' => 'cazadores', 'nombre' => 'Cazadores', 'color' => '#237a3b', 'logo' => '/images/op/uploads/CazadorLogo_One_Piece_Gaiden_Foro_Rol.png'),
        'Civil' => array('slug' => 'civil', 'nombre' => 'Civiles', 'color' => '#7a3d91', 'logo' => '/images/op/uploads/CivilLogo_One_Piece_Gaiden_Foro_Rol.png'),
        'Otros' => array('slug' => 'otros', 'nombre' => 'Otros', 'color' => '#5e5e5e', 'logo' => ''),
    );
}

function estadisticasCensoClaveFaccion($faccion) {
    $facciones = estadisticasCensoFacciones();
    return isset($facciones[$faccion]) ? $faccion : 'Otros';
}

function estadisticasCensoActividad($ahora) {
    global $db;
    $ahora = (int)$ahora;
    $inicios = array();
    foreach (array(1, 2, 7, 14, 28) as $dias) {
        $inicios[$dias] = $ahora - ($dias * 24 * 3600);
    }
    return $db->query("SELECT fi.fid,fi.nombre,fi.apodo,fi.faccion,u.username,u.avatar,
        SUM(CASE WHEN p.dateline >= {$inicios[1]} THEN 1 ELSE 0 END) AS posts_1,
        SUM(CASE WHEN p.dateline >= {$inicios[2]} THEN 1 ELSE 0 END) AS posts_2,
        SUM(CASE WHEN p.dateline >= {$inicios[7]} THEN 1 ELSE 0 END) AS posts_7,
        SUM(CASE WHEN p.dateline >= {$inicios[14]} THEN 1 ELSE 0 END) AS posts_14,
        COUNT(p.pid) AS posts_28,
        COUNT(DISTINCT p.tid) AS temas_28,
        MAX(p.dateline) AS ultimo_post
        FROM mybb_posts p
        INNER JOIN mybb_threads t ON t.tid=p.tid
        INNER JOIN mybb_forums fo ON fo.fid=t.fid
        INNER JOIN mybb_op_fichas fi ON fi.fid=p.uid
        INNER JOIN mybb_users u ON u.uid=fi.fid
        WHERE p.dateline >= {$inicios[28]} AND p.dateline <= {$ahora}
          AND p.visible='1' AND t.visible='1'
          AND (fo.fid='10' OR FIND_IN_SET(10, fo.parentlist))
          AND fi.aprobada_por NOT IN ('sin_aprobar','pendiente_reset')
          AND (fi.muerto IS NULL OR fi.muerto='0')
          AND p.uid NOT IN (" . ESTADISTICAS_UIDS_OCULTOS . ")
        GROUP BY fi.fid,fi.nombre,fi.apodo,fi.faccion,u.username,u.avatar");
}

function estadisticasCensoTotales($ahora) {
    global $db;
    $ahora = (int)$ahora;
    $inicios = array();
    foreach (array(1, 2, 7, 14, 28) as $dias) {
        $inicios[$dias] = $ahora - ($dias * 24 * 3600);
    }
    $rolValido = "(fo.fid='10' OR FIND_IN_SET(10, fo.parentlist))
        AND fi.fid IS NOT NULL
        AND fi.aprobada_por NOT IN ('sin_aprobar','pendiente_reset')
        AND (fi.muerto IS NULL OR fi.muerto='0')";
    $query = $db->query("SELECT
        SUM(CASE WHEN p.dateline >= {$inicios[1]} AND {$rolValido} THEN 1 ELSE 0 END) AS rol_1,
        SUM(CASE WHEN p.dateline >= {$inicios[2]} AND {$rolValido} THEN 1 ELSE 0 END) AS rol_2,
        SUM(CASE WHEN p.dateline >= {$inicios[7]} AND {$rolValido} THEN 1 ELSE 0 END) AS rol_7,
        SUM(CASE WHEN p.dateline >= {$inicios[14]} AND {$rolValido} THEN 1 ELSE 0 END) AS rol_14,
        SUM(CASE WHEN {$rolValido} THEN 1 ELSE 0 END) AS rol_28,
        COUNT(DISTINCT CASE WHEN {$rolValido} THEN p.tid END) AS temas_28,
        SUM(CASE WHEN p.dateline >= {$inicios[1]} THEN 1 ELSE 0 END) AS foro_1,
        SUM(CASE WHEN p.dateline >= {$inicios[2]} THEN 1 ELSE 0 END) AS foro_2,
        SUM(CASE WHEN p.dateline >= {$inicios[7]} THEN 1 ELSE 0 END) AS foro_7,
        SUM(CASE WHEN p.dateline >= {$inicios[14]} THEN 1 ELSE 0 END) AS foro_14,
        COUNT(p.pid) AS foro_28
        FROM mybb_posts p
        INNER JOIN mybb_threads t ON t.tid=p.tid
        INNER JOIN mybb_forums fo ON fo.fid=t.fid
        LEFT JOIN mybb_op_fichas fi ON fi.fid=p.uid
        WHERE p.dateline >= {$inicios[28]} AND p.dateline <= {$ahora}
          AND p.visible='1' AND t.visible='1'
          AND p.uid NOT IN (" . ESTADISTICAS_UIDS_OCULTOS . ")");
    return $db->fetch_array($query);
}

function estadisticasEscapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function estadisticasNombrePersonaje($fila) {
    $nombre = trim((string)($fila['nombre'] ?? ''));
    $apodo = trim((string)($fila['apodo'] ?? ''));
    if ($nombre === '') {
        $nombre = (string)($fila['username'] ?? 'Personaje desconocido');
    }
    if ($apodo !== '' && $apodo !== $nombre) {
        $nombre .= ' · ' . $apodo;
    }
    return $nombre;
}

function estadisticasValor($valor, $tipo) {
    if ($tipo === 'experiencia') {
        $numero = (float)$valor;
        return number_format($numero, $numero == floor($numero) ? 0 : 2, ',', '.');
    }
    return my_number_format((int)$valor);
}

function estadisticasConstruirRanking($query, $tipo, $sufijo, $uidActual) {
    global $db;

    $primeros = array();
    $todas = array();
    $filaPersonal = null;
    $indice = 0;
    $posicion = 0;
    $valorAnterior = null;
    $clasificados = 0;
    $totalAcumulado = 0;
    $avatarDefecto = '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png';

    while ($fila = $db->fetch_array($query)) {
        $indice++;
        $valor = $tipo === 'experiencia' ? (float)$fila['valor'] : (int)$fila['valor'];
        if ($valorAnterior === null || $valor != $valorAnterior) {
            $posicion = $indice;
            $valorAnterior = $valor;
        }

        $fila['posicion'] = $posicion;
        $fila['valor_formateado'] = estadisticasValor($valor, $tipo);
        $fila['valor_numerico'] = $valor;
        $fila['avatar'] = trim((string)($fila['avatar'] ?? '')) ?: $avatarDefecto;
        $fila['nombre_completo'] = estadisticasNombrePersonaje($fila);
        $totalAcumulado += $valor;
        if ($valor > 0) {
            $clasificados++;
        }
        if ((int)$fila['uid'] === $uidActual) {
            $filaPersonal = $fila;
        }
        $todas[(int)$fila['uid']] = $fila;

        if ($indice <= 20) {
            $primeros[] = $fila;
        }
    }

    $podio = '';
    $filas = '';
    $ordenPodio = 0;
    foreach ($primeros as $fila) {
        $nombre = estadisticasEscapar($fila['nombre_completo']);
        $claseActual = (int)$fila['uid'] === $uidActual ? ' ranking-fila--actual' : '';
        if ($ordenPodio < 3) {
            $ordenPodio++;
            $podio .= '<a class="ranking-podio__puesto ranking-podio__puesto--' . $ordenPodio . $claseActual . '" href="/op/personaje.php?uid=' . (int)$fila['uid'] . '">'
                . '<span class="ranking-podio__numero">#' . (int)$fila['posicion'] . '</span>'
                . '<img src="' . estadisticasEscapar($fila['avatar']) . '" alt="" loading="lazy" decoding="async">'
                . '<span class="ranking-podio__nombre">' . $nombre . '</span>'
                . '<strong>' . $fila['valor_formateado'] . ' <small>' . estadisticasEscapar($sufijo) . '</small></strong>'
                . '</a>';
            continue;
        }
        $filas .= '<div class="ranking-fila' . $claseActual . '">'
            . '<span class="ranking-posicion">#' . (int)$fila['posicion'] . '</span>'
            . '<img class="ranking-avatar" src="' . estadisticasEscapar($fila['avatar']) . '" alt="" loading="lazy" decoding="async">'
            . '<a class="ranking-nombre" href="/op/personaje.php?uid=' . (int)$fila['uid'] . '">' . $nombre . '</a>'
            . '<strong class="ranking-valor">' . $fila['valor_formateado'] . ' <small>' . estadisticasEscapar($sufijo) . '</small></strong>'
            . '</div>';
    }

    if ($podio === '') {
        $podio = '<p class="ranking-vacio">Todavía no hay datos para formar el podio.</p>';
    }
    if ($filas === '') {
        $filas = '<p class="ranking-vacio">Todavía no hay más posiciones clasificadas.</p>';
    }

    if ($uidActual <= 0) {
        $personal = '<div class="ranking-personal ranking-personal--vacio">Inicia sesión para consultar tu posición.</div>';
    } elseif (!$filaPersonal) {
        $personal = '<div class="ranking-personal ranking-personal--vacio">Tu personaje todavía no figura en este ranking.</div>';
    } else {
        $nombrePersonal = estadisticasEscapar(estadisticasNombrePersonaje($filaPersonal));
        $progreso = '';
        $valores = array_values($todas);
        $indicePersonal = array_search((int)$filaPersonal['uid'], array_map(function ($fila) {
            return (int)$fila['uid'];
        }, $valores), true);
        $superior = null;
        $inferior = null;
        if ($indicePersonal !== false) {
            for ($i = $indicePersonal - 1; $i >= 0; $i--) {
                if ($valores[$i]['valor_numerico'] > $filaPersonal['valor_numerico']) {
                    $superior = $valores[$i];
                    break;
                }
            }
            for ($i = $indicePersonal + 1, $totalValores = count($valores); $i < $totalValores; $i++) {
                if ($valores[$i]['valor_numerico'] < $filaPersonal['valor_numerico']) {
                    $inferior = $valores[$i];
                    break;
                }
            }
        }
        if ((int)$filaPersonal['posicion'] === 1) {
            $siguiente = null;
            foreach ($valores as $filaRanking) {
                if ($filaRanking['valor_numerico'] < $filaPersonal['valor_numerico']) {
                    $siguiente = $filaRanking;
                    break;
                }
            }
            $empatados = 0;
            foreach ($valores as $filaRanking) {
                if ($filaRanking['valor_numerico'] == $filaPersonal['valor_numerico']) $empatados++;
            }
            $progreso = $empatados > 1
                ? 'Estás empatado en el primer puesto. Necesitas 1 ' . estadisticasEscapar($sufijo) . ' para liderar en solitario.'
                : ($siguiente
                    ? 'Lideras con una ventaja de ' . estadisticasValor($filaPersonal['valor_numerico'] - $siguiente['valor_numerico'], $tipo) . ' ' . estadisticasEscapar($sufijo) . '.'
                    : 'Estás en el primer puesto.');
        } elseif ($indicePersonal !== false) {
            if ($superior) {
                $faltan = $superior['valor_numerico'] - $filaPersonal['valor_numerico'];
                $progreso = 'Necesitas ' . estadisticasValor($faltan, $tipo) . ' ' . estadisticasEscapar($sufijo)
                    . ' para alcanzar el puesto #' . (int)$superior['posicion'] . ' y '
                    . estadisticasValor($faltan + 1, $tipo) . ' para superarlo.';
            }
        }
        $rivales = '<div class="ranking-rivales">';
        if ($superior) {
            $rivales .= '<span><small>Próximo rival</small><a href="/op/personaje.php?uid=' . (int)$superior['uid'] . '">'
                . estadisticasEscapar($superior['nombre_completo']) . '</a><b>#' . (int)$superior['posicion'] . ' · '
                . $superior['valor_formateado'] . ' ' . estadisticasEscapar($sufijo) . '</b></span>';
        } else {
            $rivales .= '<span><small>Próximo rival</small><em>Nadie por encima</em></span>';
        }
        if ($inferior) {
            $rivales .= '<span><small>Detrás de ti</small><a href="/op/personaje.php?uid=' . (int)$inferior['uid'] . '">'
                . estadisticasEscapar($inferior['nombre_completo']) . '</a><b>#' . (int)$inferior['posicion'] . ' · '
                . $inferior['valor_formateado'] . ' ' . estadisticasEscapar($sufijo) . '</b></span>';
        } else {
            $rivales .= '<span><small>Detrás de ti</small><em>Nadie por debajo</em></span>';
        }
        $rivales .= '</div>';
        $personal = '<div class="ranking-personal"><span>Tu posición</span>'
            . '<strong>#' . (int)$filaPersonal['posicion'] . '</strong>'
            . '<a href="/op/personaje.php?uid=' . (int)$filaPersonal['uid'] . '">' . $nombrePersonal . '</a>'
            . '<b>' . $filaPersonal['valor_formateado'] . ' ' . estadisticasEscapar($sufijo) . '</b>'
            . ($progreso !== '' ? '<small class="ranking-progreso">' . $progreso . '</small>' : '')
            . $rivales . '</div>';
    }

    $meta = my_number_format($clasificados) . ' personajes clasificados · '
        . estadisticasValor($totalAcumulado, $tipo) . ' ' . estadisticasEscapar($sufijo) . ' acumulados';

    return array('podio' => $podio, 'lista' => $filas, 'personal' => $personal, 'meta' => $meta, 'todos' => $todas);
}

function estadisticasTarjetaPersonal($ranking, $fid, $etiqueta, $sufijo, $tipo, $icono, $color) {
    if (!isset($ranking['todos'][$fid])) {
        return '';
    }

    $actual = $ranking['todos'][$fid];
    $valores = array_values($ranking['todos']);
    $superior = null;
    foreach ($valores as $fila) {
        if ($fila['valor_numerico'] > $actual['valor_numerico']) {
            $superior = $fila;
        } else {
            break;
        }
    }

    $valorActual = (float)$actual['valor_numerico'];
    $estado = '';
    $rival = '';
    $porcentaje = 100;
    if ($superior) {
        $diferenciaIgualar = max(0, (float)$superior['valor_numerico'] - $valorActual);
        $diferenciaSuperar = $diferenciaIgualar + 1;
        $porcentaje = $superior['valor_numerico'] > 0
            ? max(2, min(100, (int)round(($valorActual / $superior['valor_numerico']) * 100)))
            : 0;
        $estado = '<strong>Te faltan ' . estadisticasValor($diferenciaSuperar, $tipo) . ' ' . estadisticasEscapar($sufijo) . '</strong>'
            . '<span>para superar el puesto #' . (int)$superior['posicion'] . '</span>';
        $rival = '<div class="consulta-ranking__rival"><img src="' . estadisticasEscapar($superior['avatar']) . '" alt="" loading="lazy" decoding="async">'
            . '<div><small>Próximo rival</small><a href="/op/estadisticas.php?accion=consultar-ranking&amp;consultar_fid=' . (int)$superior['uid'] . '">'
            . estadisticasEscapar($superior['nombre_completo']) . '</a><span>#' . (int)$superior['posicion'] . ' · '
            . $superior['valor_formateado'] . ' ' . estadisticasEscapar($sufijo) . '</span></div></div>';
    } else {
        $empatados = 0;
        foreach ($valores as $fila) {
            if ($fila['valor_numerico'] == $actual['valor_numerico']) $empatados++;
        }
        if ($empatados > 1) {
            $estado = '<strong>Te falta 1 ' . estadisticasEscapar($sufijo) . '</strong><span>para liderar en solitario</span>';
        } else {
            $estado = '<strong>Lideras la clasificación</strong><span>No tienes rivales por delante</span>';
        }
        $rival = '<div class="consulta-ranking__rival consulta-ranking__rival--lider"><i class="fa fa-trophy" aria-hidden="true"></i>'
            . '<div><small>Próximo rival</small><b>Nadie por encima</b><span>Defiende el primer puesto</span></div></div>';
    }

    return '<article class="consulta-ranking" style="--ranking-acento:' . estadisticasEscapar($color) . '">'
        . '<header><i class="fa ' . estadisticasEscapar($icono) . '" aria-hidden="true"></i><span>' . estadisticasEscapar($etiqueta) . '</span>'
        . '<strong>#' . (int)$actual['posicion'] . '</strong></header>'
        . '<div class="consulta-ranking__valor"><span>Tu marca</span><strong>' . $actual['valor_formateado'] . '</strong><small>' . estadisticasEscapar($sufijo) . '</small></div>'
        . '<div class="consulta-ranking__progreso"><div><i style="width:' . $porcentaje . '%"></i></div><p>' . $estado . '</p></div>'
        . $rival . '</article>';
}

function estadisticasRankingAventurasDesdeConteos($rankingBase, $conteos) {
    $filas = array();
    foreach (($rankingBase['todos'] ?? array()) as $fid => $base) {
        $fid = (int)$fid;
        $conteo = $conteos[$fid] ?? array();
        $fila = $base;
        $fila['valor_numerico'] = (int)($conteo['total'] ?? 0);
        $fila['valor_formateado'] = estadisticasValor($fila['valor_numerico'], 'entero');
        $fila['finalizadas'] = (int)($conteo['finalizadas'] ?? 0);
        $filas[] = $fila;
    }

    usort($filas, function ($a, $b) {
        if ($a['valor_numerico'] !== $b['valor_numerico']) {
            return $b['valor_numerico'] <=> $a['valor_numerico'];
        }
        if ($a['finalizadas'] !== $b['finalizadas']) {
            return $b['finalizadas'] <=> $a['finalizadas'];
        }
        $nombre = strcasecmp($a['nombre_completo'], $b['nombre_completo']);
        return $nombre !== 0 ? $nombre : ((int)$a['uid'] <=> (int)$b['uid']);
    });

    $todas = array();
    $posicion = 0;
    $valorAnterior = null;
    foreach ($filas as $indice => $fila) {
        if ($valorAnterior === null || $fila['valor_numerico'] !== $valorAnterior) {
            $posicion = $indice + 1;
            $valorAnterior = $fila['valor_numerico'];
        }
        $fila['posicion'] = $posicion;
        $todas[(int)$fila['uid']] = $fila;
    }

    return array('todos' => $todas);
}

if ($mybb->get_input('ajax') === 'buscar-personajes') {
    $termino = trim($mybb->get_input('q', MyBB::INPUT_STRING));
    header('Content-Type: application/json; charset=utf-8');
    if (mb_strlen($termino) < 3 && !ctype_digit($termino)) {
        echo '[]';
        exit;
    }
    $like = $db->escape_string(addcslashes($termino, '%_'));
    $porId = ctype_digit($termino) ? ' OR f.fid=' . (int)$termino : '';
    $queryBusqueda = $db->query("SELECT f.fid, f.nombre, f.apodo
        FROM mybb_op_fichas f
        WHERE (f.nombre LIKE '%{$like}%' OR f.apodo LIKE '%{$like}%'{$porId})
        AND f.fid NOT IN (" . ESTADISTICAS_UIDS_OCULTOS . ")
        ORDER BY f.nombre ASC LIMIT 10");
    $resultados = array();
    while ($fichaBusqueda = $db->fetch_array($queryBusqueda)) {
        $resultados[] = array(
            'fid' => (int)$fichaBusqueda['fid'],
            'label' => estadisticasNombrePersonaje($fichaBusqueda) . ' (#' . (int)$fichaBusqueda['fid'] . ')',
        );
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}


// function queryTop10Posts() {
//     global $db, $timestamp;
//     return $db->query("
//         SELECT p.username, COUNT(p.username) as numPosts FROM mybb_posts as p 
//         INNER JOIN mybb_threads as t ON p.tid = t.tid 
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         WHERE p.dateline > $timestamp
//         AND f.parentlist LIKE '10,%'
//         GROUP BY p.username
//         ORDER BY numPosts DESC
//         LIMIT 10
//     ");
// }

// function queryTop10PostsTotal() {
//     global $db, $timestamp;
//     return $db->query("
//         SELECT p.username, COUNT(p.username) as numPosts FROM mybb_posts as p 
//         INNER JOIN mybb_threads as t ON p.tid = t.tid 
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         AND f.parentlist LIKE '10,%'
//         GROUP BY p.username
//         ORDER BY numPosts DESC
//         LIMIT 10
//     ");
// }

// function queryTop10Temas() {
//     global $db, $timestamp;
//     return $db->query("
//         SELECT t.username, COUNT(t.username) as numThreads FROM mybb_threads as t
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         WHERE t.dateline > $timestamp
//         AND f.parentlist LIKE '10,%'
//         GROUP BY t.username
//         ORDER BY numThreads DESC
//         LIMIT 10
//     ");
// }

// function queryTop10TemasTotal() {
//     global $db, $timestamp;
//     return $db->query("
//         SELECT t.username, COUNT(t.username) as numThreads FROM mybb_threads as t
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         AND f.parentlist LIKE '10,%'
//         GROUP BY t.username
//         ORDER BY numThreads DESC
//         LIMIT 10
//     ");
// }

// function queryTop10Prs() {
//     global $db, $dateformat;
//     return $db->query("
//         SELECT *, ROUND(SUM(puntos_rol), 2) as sumPuntosRol FROM `mybb_newpoints_log`
//         WHERE date > '$dateformat'
//         GROUP BY username
//         ORDER BY sumPuntosRol DESC
//         LIMIT 10
//     ");
// }

// function queryTop10PrsTotal() {
//     global $db, $dateformat;
//     return $db->query("
//         SELECT *, ROUND(SUM(puntos_rol), 2) as sumPuntosRol FROM `mybb_newpoints_log`
//         GROUP BY username
//         ORDER BY sumPuntosRol DESC
//         LIMIT 10
//     ");
// }

// function queryTopPosts() {
//     global $db, $timestamp;
//     return $db->query("
//         SELECT p.username, COUNT(p.username) as numPosts FROM mybb_posts as p 
//         INNER JOIN mybb_threads as t ON p.tid = t.tid 
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         WHERE p.dateline > $timestamp
//         AND f.parentlist LIKE '10,%'
//         GROUP BY p.username
//         ORDER BY numPosts DESC
//     ");
// }

// function queryTopPrs() {
//     global $db, $dateformat;
//     return $db->query("
//         SELECT *, ROUND(SUM(puntos_rol), 2) as sumPuntosRol FROM `mybb_newpoints_log`
//         WHERE date > '$dateformat'
//         GROUP BY username
//         ORDER BY sumPuntosRol DESC
//     ");
// }

// function queryTopRecompensasActuales() {
//     global $db, $timestamp;
//     return $db->query("
//         SELECT * FROM `mybb_op_recompensas_usuarios` 
//         WHERE tiempo > '$timestamp'
//         ORDER BY dia DESC
//         LIMIT 10
//     ");
// }

// function queryTopRecompensasReclamadas() {
//     global $db;
//     return $db->query("
//         SELECT COUNT(*) as total_recompensas FROM `mybb_op_audit_recompensas`
//     ");
// }

// function queryTopRecompensasTotal() {
//     global $db;
//     return $db->query("
//         SELECT a.id, a.tiempo_completado, a.uid, a.nombre, a.dia, a.audit
//         FROM mybb_op_audit_recompensas a
//         INNER JOIN (
//             SELECT uid, MAX(dia) dia
//             FROM mybb_op_audit_recompensas
//             GROUP BY uid
//         ) b ON a.uid = b.uid AND a.dia = b.dia
//         ORDER BY dia DESC
//         LIMIT 10
//     ");
// }

$pjNombresTop10Posts = "";
$pjNombresTop10Prs = "";
$pjNombresTop10Temas = "";
$pjNombresTop10PostsTotal = "";
$pjNombresTop10PrsTotal = "";
$pjNombresTop10TemasTotal = "";
$pjNombresTopPosts = "";
$pjNombresTopPrs = "";
$topRecompensasActual = "";
$totalRecompensasReclamadas = "";
$topUsuariosRecompensas = "";
$texto_extra = "";

// if ($accion == 'top-10') {
//     $query_top_10_posts = queryTop10Posts();
//     $query_top_10_prs = queryTop10Prs();
//     $query_top_10_temas = queryTop10Temas();

//     while ($q = $db->fetch_array($query_top_10_posts)) {
//         $nombrePj = $q['username'];
//         $numPosts = $q['numPosts'];
//         $pjNombresTop10Posts .= "<h4>$nombrePj - $numPosts posts</h4>";
//     }
//     while ($q = $db->fetch_array($query_top_10_prs)) {
//         $nombrePj = $q['username'];
//         $sumPuntosRol = $q['sumPuntosRol'];
//         $pjNombresTop10Prs .= "<h4>$nombrePj - $sumPuntosRol PR</h4>";
//     }
//     while ($q = $db->fetch_array($query_top_10_temas)) {
//         $nombrePj = $q['username'];
//         $numThreads = $q['numThreads'];
//         $pjNombresTop10Temas .= "<h4>$nombrePj - $numThreads temas</h4>";
//     }
// }

// if ($accion == 'top-10-historico') {
//     $query_top_10_posts_total = queryTop10PostsTotal();
//     $query_top_10_prs_total = queryTop10PrsTotal();
//     $query_top_10_temas_total = queryTop10TemasTotal();

//     while ($q = $db->fetch_array($query_top_10_posts_total)) {
//         $nombrePj = $q['username'];
//         $numPosts = $q['numPosts'];
//         $pjNombresTop10PostsTotal .= "<h4>$nombrePj - $numPosts posts</h4>";
//     }
//     while ($q = $db->fetch_array($query_top_10_prs_total)) {
//         $nombrePj = $q['username'];
//         $sumPuntosRol = $q['sumPuntosRol'];
//         $pjNombresTop10PrsTotal .= "<h4>$nombrePj - $sumPuntosRol PR</h4>";
//     }
//     while ($q = $db->fetch_array($query_top_10_temas_total)) {
//         $nombrePj = $q['username'];
//         $numThreads = $q['numThreads'];
//         $pjNombresTop10TemasTotal .= "<h4>$nombrePj - $numThreads temas</h4>";
//     }
// }

// if ($accion == 'posts') {
//     $query_top_posts = queryTopPosts();
//     while ($q = $db->fetch_array($query_top_posts)) {
//         $nombrePj = $q['username'];
//         $numPosts = $q['numPosts'];
//         $pjNombresTopPosts .= "<h4>$nombrePj - $numPosts posts</h4>";
//     }
// }

// if ($accion == 'puntos-rol') {
//     $query_top_prs = queryTopPrs();
//     while ($q = $db->fetch_array($query_top_prs)) {
//         $nombrePj = $q['username'];
//         $sumPuntosRol = $q['sumPuntosRol'];
//         $pjNombresTopPrs .= "<h4>$nombrePj - $sumPuntosRol PR</h4>";
//     }
// }

// if ($accion == 'recompensas') {
//     $query_top_recompensas_actuales = queryTopRecompensasActuales();
//     $query_top_recompensas_reclamadas = queryTopRecompensasReclamadas();
//     $query_top_recompensas_total = queryTopRecompensasTotal();

//     while ($q = $db->fetch_array($query_top_recompensas_actuales)) {
//         $nombrePj = $q['nombre'];
//         $dia = $q['dia'];
//         $topRecompensasActual .= "<h4>$nombrePj - $dia días</h4>";
//     }
//     while ($q = $db->fetch_array($query_top_recompensas_reclamadas)) {
//         $totalRecompensas = $q['total_recompensas'];
//         $totalRecompensasReclamadas .= "<span>$totalRecompensas</span>";
//     }
//     while ($q = $db->fetch_array($query_top_recompensas_total)) {
//         $topUsuarioUid = $q['nombre'];
//         $topUsuarioDia = $q['dia'];
//         $topUsuariosRecompensas .= "<h4>$topUsuarioUid - $topUsuarioDia días</h4>";
//     }
// }

// if ($accion == 'personales') {
//     $texto_extra = "<span><strong>$username, has:</strong></span><br>";

//     $total_posts = $db->query("SELECT COUNT(*) as total FROM mybb_posts as p 
//         INNER JOIN mybb_threads as t ON p.tid = t.tid 
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         AND f.parentlist LIKE '10,%'
//         WHERE p.uid = '$uid'");

//     $total_temas = $db->query("SELECT COUNT(*) as total FROM mybb_threads as t
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         AND f.parentlist LIKE '10,%'
//         WHERE t.uid = '$uid'");

//     $total_pr = $db->query("SELECT ROUND(SUM(puntos_rol), 2) as total FROM `mybb_newpoints_log` WHERE uid='196'");

//     // $total_horas_entrenadas = 0;
//     // $total_horas_entrenadas_query = $db->query("SELECT * FROM `mybb_op_audit_entrenamientos` WHERE fid=196");
//     // while ($q = $db->fetch_array($total_horas_entrenadas_query)) { 
//     //     $tiempo_iniciado = intval($q['tiempo_iniciado']);
//     //     $tiempo_finaliza = intval($q['tiempo_finaliza']);

//     //     if ($tiempo_finaliza > $tiempo_iniciado && (($tiempo_finaliza - $tiempo_iniciado) < 864000)) {
//     //         $total_horas_entrenadas += $tiempo_finaliza - $tiempo_iniciado;
//     //     }
//     // }

//     $total_entrenos = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_entrenamientos` WHERE fid='$uid'");
//     $total_misiones = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_misiones` WHERE fid='$uid'");
//     $total_recompensas = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_recompensas` WHERE uid='$uid'");
//     $total_hides = $db->query("SELECT COUNT(*) as total FROM `mybb_op_hide` WHERE uid='$uid'");

//     $total_stats = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_stats` WHERE fid='$uid'");
//     $total_codigos = $db->query("SELECT COUNT(*) as total FROM `mybb_op_codigos_usuarios` WHERE uid='$uid'");
//     $total_personaje = $db->query("SELECT COUNT(*) as total FROM `mybb_op_thread_personaje` WHERE uid='$uid'");
//     $total_peticiones = $db->query("SELECT COUNT(*) as total FROM `mybb_op_peticiones` WHERE uid='$uid'");


//     while ($q = $db->fetch_array($total_pr)) { 
//         $total = $q['total']; 
//         $caracteres = strval(intval($total) * 400);
//         $palabras = strval($caracteres / 5);
//         $texto_extra .= "- Acumulado un total de $total puntos de rol posteando. <br>- Escrito apróximadamente $palabras palabras o $caracteres carácteres.<br>";
//     }
//     while ($q = $db->fetch_array($total_posts)) { $total = $q['total']; $texto_extra .= "- Escrito $total posts de rol en el foro<br>"; }
//     while ($q = $db->fetch_array($total_temas)) { $total = $q['total']; $texto_extra .= "- Creado $total temas de rol en el foro <br>"; }
//     while ($q = $db->fetch_array($total_entrenos)) { $total = $q['total']; $texto_extra .= "- Entrenado $total técnicas<br>"; }
//     while ($q = $db->fetch_array($total_misiones)) { $total = $q['total']; $texto_extra .= "- Realizado $total misiones automáticas<br>"; }
//     while ($q = $db->fetch_array($total_recompensas)) { $total = $q['total']; $texto_extra .= "- Cobrado $total recompensas diarias<br>"; }
//     while ($q = $db->fetch_array($total_hides)) { $total = $q['total']; $texto_extra .= "- Hecho $total hides<br>"; }
//     while ($q = $db->fetch_array($total_stats)) { $total = $q['total']; $texto_extra .= "- Actualizado $total veces las estadísticas<br>"; }
//     while ($q = $db->fetch_array($total_codigos)) { $total = $q['total']; $texto_extra .= "- Agregado $total códigos promocionales<br>"; }
//     while ($q = $db->fetch_array($total_personaje)) { $total = $q['total']; $texto_extra .= "- Utilizado $total veces el código de [personaje]<br>"; }
//     while ($q = $db->fetch_array($total_peticiones)) { $total = $q['total']; $texto_extra .= "- Enviado $total peticiones administrativas<br>"; }
// }

// if ($accion == 'extras') {
//     $texto_extra = '<span><strong>Los usuarios han:</strong></span><br>';

//     $total_posts = $db->query("SELECT COUNT(*) as total FROM mybb_posts as p 
//         INNER JOIN mybb_threads as t ON p.tid = t.tid 
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         AND f.parentlist LIKE '10,%'");

//     $total_temas = $db->query("SELECT COUNT(*) as total FROM mybb_threads as t
//         INNER JOIN mybb_forums as f ON t.fid = f.fid 
//         AND f.parentlist LIKE '10,%'");

//     $total_pr = $db->query("SELECT ROUND(SUM(puntos_rol), 2) as total FROM `mybb_newpoints_log`");

//     $total_entrenos = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_entrenamientos`");
//     $total_misiones = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_misiones`");
//     $total_recompensas = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_recompensas`");
//     $total_hides = $db->query("SELECT COUNT(*) as total FROM `mybb_op_hide`");
    
//     $total_entrenos_actual = $db->query("SELECT COUNT(*) as total FROM `mybb_op_entrenamientos_usuarios` WHERE tiempo_finaliza > $last_two_weeks");
//     $total_misiones_actual = $db->query("SELECT COUNT(*) as total FROM `mybb_op_misiones_usuarios` WHERE tiempo_finaliza > $last_two_weeks");
    
//     $total_stats = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_stats`");
//     $total_fichas = $db->query("SELECT COUNT(*) as total FROM `mybb_op_fichas`");
//     $total_codigos = $db->query("SELECT COUNT(*) as total FROM `mybb_op_codigos_usuarios`");
//     $total_personaje = $db->query("SELECT COUNT(*) as total FROM `mybb_op_thread_personaje`");
//     $total_peticiones = $db->query("SELECT COUNT(*) as total FROM `mybb_op_peticiones`");

//     while ($q = $db->fetch_array($total_pr)) { 
//         $total = $q['total']; 
//         $caracteres = strval(intval($total) * 400);
//         $palabras = strval($caracteres / 5);
//         $texto_extra .= "- Acumulado un total de $total puntos de rol posteando. <br>- Escrito apróximadamente $palabras palabras o $caracteres carácteres.<br>";
//     }
//     while ($q = $db->fetch_array($total_posts)) { $total = $q['total']; $texto_extra .= "- Escrito $total posts de rol en el foro<br>"; }
//     while ($q = $db->fetch_array($total_temas)) { $total = $q['total']; $texto_extra .= "- Creado $total temas de rol en el foro <br>"; }
//     while ($q = $db->fetch_array($total_entrenos)) { $total = $q['total']; $texto_extra .= "- Entrenado $total técnicas<br>"; }
//     while ($q = $db->fetch_array($total_misiones)) { $total = $q['total']; $texto_extra .= "- Realizado $total misiones automáticas<br>"; }
//     while ($q = $db->fetch_array($total_recompensas)) { $total = $q['total']; $texto_extra .= "- Cobrado $total recompensas diarias<br>"; }
//     while ($q = $db->fetch_array($total_hides)) { $total = $q['total']; $texto_extra .= "- Hecho $total hides<br>"; }
//     while ($q = $db->fetch_array($total_stats)) { $total = $q['total']; $texto_extra .= "- Actualizado $total veces las estadísticas<br>"; }
//     while ($q = $db->fetch_array($total_fichas)) { $total = $q['total']; $texto_extra .= "- Creado $total fichas<br>"; }
//     while ($q = $db->fetch_array($total_codigos)) { $total = $q['total']; $texto_extra .= "- Agregado $total códigos promocionales<br>"; }
//     while ($q = $db->fetch_array($total_personaje)) { $total = $q['total']; $texto_extra .= "- Utilizado $total veces el código de [personaje]<br>"; }
//     while ($q = $db->fetch_array($total_peticiones)) { $total = $q['total']; $texto_extra .= "- Enviado $total peticiones administrativas<br>"; }

//     $texto_extra .= '<br><span><strong>Actualmente los usuarios están:</strong></span><br>';
//     while ($q = $db->fetch_array($total_entrenos_actual)) { $total = $q['total']; $texto_extra .= "- Entrenando $total técnicas<br>"; }
//     while ($q = $db->fetch_array($total_misiones_actual)) { $total = $q['total']; $texto_extra .= "- Realizando $total misiones<br>"; }    
// }

$censoPersonajesHtml = '';
$censoFiltrosFaccionHtml = '';
$pirataUsuarios = $marineUsuarios = $cipherPolUsuarios = $revoUsuarios = $cazarrecompensasUsuarios = $civilUsuarios = 0;
$pirataUsuariosStr = $marineUsuariosStr = $cipherPolUsuariosStr = $revoUsuariosStr = $cazarrecompensasUsuariosStr = $civilUsuariosStr = '';
$totalNumPostsPirata = $totalNumPostsMarine = $totalNumPostsCP = $totalNumPostsRevo = $totalNumPostsCaza = $totalNumPostsCivil = 0;
$numeroPosts = $numeroPostsTotal = $totalPjs = 0;
$progresoCenso = '';

if ($accion === 'censo') {
    $faccionesConfig = estadisticasCensoFacciones();
    $faccionesDatos = array();
    foreach ($faccionesConfig as $clave => $config) {
        $faccionesDatos[$clave] = array(
            'personajes' => 0,
            'posts_28' => 0,
        );
    }

    $filas = array();
    $filasPorUid = array();
    $queryCenso = estadisticasCensoActividad($estadisticasAhora);
    while ($fila = $db->fetch_array($queryCenso)) {
        $fila['fid'] = (int)$fila['fid'];
        foreach (array(1, 2, 7, 14, 28) as $dias) {
            $fila['posts_' . $dias] = (int)$fila['posts_' . $dias];
        }
        $fila['temas_28'] = (int)$fila['temas_28'];
        $fila['ultimo_post'] = (int)$fila['ultimo_post'];
        $fila['faccion_clave'] = estadisticasCensoClaveFaccion((string)$fila['faccion']);
        $filas[] = $fila;
        $filasPorUid[$fila['fid']] = $fila;

        $clave = $fila['faccion_clave'];
        $faccionesDatos[$clave]['personajes']++;
        $faccionesDatos[$clave]['posts_28'] += $fila['posts_28'];
    }

    $totales = estadisticasCensoTotales($estadisticasAhora);
    foreach (array('rol_1', 'rol_2', 'rol_7', 'rol_14', 'rol_28', 'temas_28', 'foro_1', 'foro_2', 'foro_7', 'foro_14', 'foro_28') as $campoTotal) {
        $totales[$campoTotal] = (int)($totales[$campoTotal] ?? 0);
    }

    usort($filas, function ($a, $b) {
        if ($a['posts_28'] !== $b['posts_28']) return $b['posts_28'] - $a['posts_28'];
        if ($a['ultimo_post'] !== $b['ultimo_post']) return $b['ultimo_post'] - $a['ultimo_post'];
        return $a['fid'] - $b['fid'];
    });

    $posicionesFaccion = array();
    foreach ($filas as $indice => &$personaje) {
        $personaje['posicion'] = $indice + 1;
        $clave = $personaje['faccion_clave'];
        $posicionesFaccion[$clave] = ($posicionesFaccion[$clave] ?? 0) + 1;
        $personaje['posicion_faccion'] = $posicionesFaccion[$clave];
        $filasPorUid[$personaje['fid']] = $personaje;
    }
    unset($personaje);

    $campoPeriodo = 'posts_' . $tiempo;
    $numeroPosts = $totales['rol_' . $tiempo];
    $numeroPostsTotal = $totales['foro_' . $tiempo];
    $tarjetas = array('Pirata' => '', 'Marina' => '', 'CipherPol' => '', 'Revolucionario' => '', 'Cazadores' => '', 'Civil' => '');
    $conteos = $postsFaccion = $alternancia = array_fill_keys(array_keys($tarjetas), 0);
    $filasTarjetas = $filas;
    usort($filasTarjetas, function ($a, $b) {
        return strcasecmp((string)$a['username'], (string)$b['username']);
    });
    $totalPjs = count(array_filter($filasTarjetas, function ($fila) use ($campoPeriodo) {
        return (int)$fila[$campoPeriodo] > 0;
    }));
    foreach ($filasTarjetas as $personajeTarjeta) {
        $clave = $personajeTarjeta['faccion_clave'];
        $postsPeriodo = (int)$personajeTarjeta[$campoPeriodo];
        if (!isset($tarjetas[$clave]) || $postsPeriodo <= 0) continue;
        $conteos[$clave]++;
        $postsFaccion[$clave] += $postsPeriodo;
        $colorFila = ($alternancia[$clave]++ % 2 === 0) ? '#9152de' : '#a776ff';
        $avatar = trim((string)$personajeTarjeta['avatar']) !== '' ? $personajeTarjeta['avatar'] : '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png';
        $tarjetas[$clave] .= "<div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>"
            . "<div><a href='/op/ficha.php?uid=" . (int)$personajeTarjeta['fid'] . "' target='_blank'><img src='" . estadisticasEscapar($avatar) . "' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>"
            . "<div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: {$colorFila}; '>" . estadisticasEscapar($personajeTarjeta['nombre']) . " <br />(" . my_number_format($postsPeriodo) . ")</div></div>";
    }
    $pirataUsuarios = $conteos['Pirata'];
    $marineUsuarios = $conteos['Marina'];
    $cipherPolUsuarios = $conteos['CipherPol'];
    $revoUsuarios = $conteos['Revolucionario'];
    $cazarrecompensasUsuarios = $conteos['Cazadores'];
    $civilUsuarios = $conteos['Civil'];
    $pirataUsuariosStr = $tarjetas['Pirata'];
    $marineUsuariosStr = $tarjetas['Marina'];
    $cipherPolUsuariosStr = $tarjetas['CipherPol'];
    $revoUsuariosStr = $tarjetas['Revolucionario'];
    $cazarrecompensasUsuariosStr = $tarjetas['Cazadores'];
    $civilUsuariosStr = $tarjetas['Civil'];
    $totalNumPostsPirata = $postsFaccion['Pirata'];
    $totalNumPostsMarine = $postsFaccion['Marina'];
    $totalNumPostsCP = $postsFaccion['CipherPol'];
    $totalNumPostsRevo = $postsFaccion['Revolucionario'];
    $totalNumPostsCaza = $postsFaccion['Cazadores'];
    $totalNumPostsCivil = $postsFaccion['Civil'];

    $rankingPeriodo = array_values(array_filter($filas, function ($fila) use ($campoPeriodo) {
        return (int)$fila[$campoPeriodo] > 0;
    }));
    usort($rankingPeriodo, function ($a, $b) use ($campoPeriodo) {
        if ($a[$campoPeriodo] !== $b[$campoPeriodo]) return $b[$campoPeriodo] - $a[$campoPeriodo];
        return $a['fid'] - $b['fid'];
    });
    if ($rankingPeriodo && $uid > 0) {
        $lider = (int)$rankingPeriodo[0][$campoPeriodo];
        $postsPersonales = isset($filasPorUid[$uid]) ? (int)$filasPorUid[$uid][$campoPeriodo] : 0;
        $lideres = count(array_filter($rankingPeriodo, function ($fila) use ($campoPeriodo, $lider) {
            return (int)$fila[$campoPeriodo] === $lider;
        }));
        if ($postsPersonales >= $lider) {
            $mensajeProgreso = $lideres > 1
                ? 'Estás empatado en el primer puesto. Un post más te permitiría liderar en solitario.'
                : 'Eres quien más ha posteado durante este periodo.';
        } else {
            $mensajeProgreso = 'Necesitas ' . my_number_format($lider - $postsPersonales + 1) . ' posts más para superar al líder del censo.';
        }
        $progresoCenso = '<div class="censo-progreso"><span class="censo-progreso__titulo"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Tu actividad</span>'
            . '<span class="censo-progreso__contador"><strong>' . my_number_format($postsPersonales) . '</strong><small>posts en este periodo</small></span>'
            . '<span class="censo-progreso__mensaje">' . $mensajeProgreso . '</span></div>';
    }

    if ($mostrarActividadPersonaje) {
        foreach ($faccionesConfig as $clave => $config) {
            $datos = $faccionesDatos[$clave];
            $censoFiltrosFaccionHtml .= '<button type="button" class="censo-filtro" data-censo-filter="' . $config['slug'] . '" aria-pressed="false" style="--faccion-color:' . $config['color'] . '"'
                . ' title="' . my_number_format($datos['posts_28']) . ' posts durante los últimos 28 días">'
                . '<i aria-hidden="true"></i><span>' . estadisticasEscapar($config['nombre']) . '</span><small>'
                . my_number_format($datos['personajes']) . '</small></button>';
        }

        foreach ($filas as $personaje) {
            $config = $faccionesConfig[$personaje['faccion_clave']];
            $avatar = trim((string)$personaje['avatar']) !== '' ? $personaje['avatar'] : '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png';
            $nombre = estadisticasNombrePersonaje($personaje);
            $buscar = $nombre . ' ' . $personaje['username'] . ' ' . $config['nombre'];
            $ultima = $personaje['ultimo_post'] > 0 ? my_date('relative', $personaje['ultimo_post']) : 'Sin actividad';
            $censoPersonajesHtml .= '<tr data-censo-row data-faction="' . $config['slug'] . '" data-name="' . estadisticasEscapar($buscar) . '"'
                . ' data-posts-1="' . (int)$personaje['posts_1'] . '" data-posts-2="' . (int)$personaje['posts_2'] . '"'
                . ' data-posts-7="' . (int)$personaje['posts_7'] . '" data-posts-14="' . (int)$personaje['posts_14'] . '"'
                . ' data-posts-28="' . (int)$personaje['posts_28'] . '" data-recent="' . (int)$personaje['ultimo_post'] . '">'
                . '<td data-label="Puesto"><strong class="censo-puesto">#' . (int)$personaje['posicion'] . '</strong></td>'
                . '<td data-label="Personaje"><a class="censo-personaje" href="/op/ficha.php?uid=' . (int)$personaje['fid'] . '"><img src="' . estadisticasEscapar($avatar) . '" alt="" loading="lazy" decoding="async">'
                . '<span><strong>' . estadisticasEscapar($nombre) . '</strong><small>@' . estadisticasEscapar($personaje['username']) . '</small></span></a></td>'
                . '<td data-label="Facción"><span class="censo-faccion-chip" style="--faccion-color:' . $config['color'] . '">' . estadisticasEscapar($config['nombre']) . '</span></td>'
                . '<td data-label="24 h"><strong>' . my_number_format($personaje['posts_1']) . '</strong></td>'
                . '<td data-label="48 h"><strong>' . my_number_format($personaje['posts_2']) . '</strong></td>'
                . '<td data-label="7 días"><strong>' . my_number_format($personaje['posts_7']) . '</strong></td>'
                . '<td data-label="14 días"><strong>' . my_number_format($personaje['posts_14']) . '</strong></td>'
                . '<td data-label="28 días"><strong>' . my_number_format($personaje['posts_28']) . '</strong></td>'
                . '<td data-label="Última actividad"><time datetime="' . date('c', $personaje['ultimo_post']) . '">' . estadisticasEscapar($ultima) . '</time></td></tr>';
        }
        if ($censoPersonajesHtml === '') {
            $censoPersonajesHtml = '<tr class="censo-vacio"><td colspan="9">No hay actividad de rol registrada durante los últimos 28 días.</td></tr>';
        }
    }
}

$topPostsRol = '';
$podioPostsRol = '';
$posicionPostsRol = '';
$metaPostsRol = '';
$topExperiencia = '';
$podioExperiencia = '';
$posicionExperiencia = '';
$metaExperiencia = '';
$topTemasRol = '';
$podioTemasRol = '';
$posicionTemasRol = '';
$metaTemasRol = '';
$topReputacion = '';
$podioReputacion = '';
$posicionReputacion = '';
$metaReputacion = '';
$rankingAventureros = array('todos' => array());
$rankingNarradores = array('todos' => array());

if (in_array($accion, array('mas-posts', 'comparar', 'consultar-ranking'), true)) {
    $queryRankingPosts = $db->query("
    SELECT u.uid, u.username, u.avatar, fi.nombre, fi.apodo, COALESCE(rp.valor, 0) AS valor
    FROM mybb_users u
    INNER JOIN mybb_op_fichas fi ON fi.fid = u.uid
    LEFT JOIN (
        SELECT p.uid, COUNT(p.pid) AS valor
        FROM mybb_posts p
        INNER JOIN mybb_threads t ON t.tid = p.tid
        INNER JOIN mybb_forums fo ON fo.fid = t.fid
        WHERE fo.parentlist LIKE '10,%'
        GROUP BY p.uid
    ) rp ON rp.uid = u.uid
    WHERE u.uid NOT IN (" . ESTADISTICAS_UIDS_OCULTOS . ")
    ORDER BY valor DESC, fi.nombre ASC, u.uid ASC
");
    $rankingPosts = estadisticasConstruirRanking($queryRankingPosts, 'entero', 'posts', (int)$uid);
    $podioPostsRol = $rankingPosts['podio'];
    $topPostsRol = $rankingPosts['lista'];
    $posicionPostsRol = $rankingPosts['personal'];
    $metaPostsRol = $rankingPosts['meta'];
}
if (in_array($accion, array('mas-experiencia', 'comparar', 'consultar-ranking'), true)) {
    $queryRankingExperiencia = $db->query("
    SELECT u.uid, u.username, u.avatar, u.newpoints AS valor, fi.nombre, fi.apodo
    FROM mybb_users u
    INNER JOIN mybb_op_fichas fi ON fi.fid = u.uid
    WHERE u.uid NOT IN (92, 966, 152, 121, 850, " . ESTADISTICAS_UIDS_OCULTOS . ")
    ORDER BY u.newpoints DESC, fi.nombre ASC, u.uid ASC
");
    $rankingExperiencia = estadisticasConstruirRanking($queryRankingExperiencia, 'experiencia', 'EXP', (int)$uid);
    $podioExperiencia = $rankingExperiencia['podio'];
    $topExperiencia = $rankingExperiencia['lista'];
    $posicionExperiencia = $rankingExperiencia['personal'];
    $metaExperiencia = $rankingExperiencia['meta'];
}
if (in_array($accion, array('mas-temas', 'comparar', 'consultar-ranking'), true)) {
    $queryRankingTemas = $db->query("
    SELECT u.uid, u.username, u.avatar, fi.nombre, fi.apodo, COALESCE(rt.valor, 0) AS valor
    FROM mybb_users u
    INNER JOIN mybb_op_fichas fi ON fi.fid = u.uid
    LEFT JOIN (
        SELECT t.uid, COUNT(t.tid) AS valor
        FROM mybb_threads t
        INNER JOIN mybb_forums fo ON fo.fid = t.fid
        WHERE fo.parentlist LIKE '10,%'
        GROUP BY t.uid
    ) rt ON rt.uid = u.uid
    WHERE u.uid NOT IN (" . ESTADISTICAS_UIDS_OCULTOS . ")
    ORDER BY valor DESC, fi.nombre ASC, u.uid ASC
");
    $rankingTemas = estadisticasConstruirRanking($queryRankingTemas, 'entero', 'temas', (int)$uid);
    $podioTemasRol = $rankingTemas['podio'];
    $topTemasRol = $rankingTemas['lista'];
    $posicionTemasRol = $rankingTemas['personal'];
    $metaTemasRol = $rankingTemas['meta'];
}

if (in_array($accion, array('mas-reputacion', 'consultar-ranking'), true)) {
    // Misma reputacion que muestra la ficha (personaje.php): V017 la sube un 10% y D013 la baja un 10%.
    // TRUNCATE(x, 0) equivale al intval() de la ficha. Mismas cuentas excluidas que en el ranking de experiencia.
    $queryRankingReputacion = $db->query("
    SELECT u.uid, u.username, u.avatar, fi.nombre, fi.apodo,
        TRUNCATE(CASE
            WHEN EXISTS (SELECT 1 FROM mybb_op_virtudes_usuarios v WHERE v.uid = u.uid AND v.virtud_id = 'V017') THEN fi.reputacion * 1.10
            WHEN EXISTS (SELECT 1 FROM mybb_op_virtudes_usuarios v WHERE v.uid = u.uid AND v.virtud_id = 'D013') THEN fi.reputacion * 0.9
            ELSE fi.reputacion
        END, 0) AS valor
    FROM mybb_users u
    INNER JOIN mybb_op_fichas fi ON fi.fid = u.uid
    WHERE u.uid NOT IN (92, 966, 152, 121, 850, " . ESTADISTICAS_UIDS_OCULTOS . ")
    ORDER BY valor DESC, fi.nombre ASC, u.uid ASC
");
    $rankingReputacion = estadisticasConstruirRanking($queryRankingReputacion, 'entero', 'rep', (int)$uid);
    $podioReputacion = $rankingReputacion['podio'];
    $topReputacion = $rankingReputacion['lista'];
    $posicionReputacion = $rankingReputacion['personal'];
    $metaReputacion = $rankingReputacion['meta'];
}

if ($accion === 'consultar-ranking') {
    $tablaAventuras = $db->table_prefix . 'op_peticionAventuras';
    $conteosAventureros = array();
    $queryAventureros = $db->query("SELECT `estado`, `jugadores_json`
        FROM `{$tablaAventuras}`
        WHERE `estado` IN (1, 3)
          AND NOT (`tipo` = 'autonarrada')");
    while ($aventura = $db->fetch_array($queryAventureros)) {
        $jugadores = json_decode((string)($aventura['jugadores_json'] ?? '[]'), true);
        if (!is_array($jugadores)) {
            continue;
        }
        $vistos = array();
        foreach ($jugadores as $jugador) {
            if (!is_array($jugador)) {
                continue;
            }
            $fidJugador = (int)($jugador['uid'] ?? 0);
            if ($fidJugador <= 0 || isset($vistos[$fidJugador])) {
                continue;
            }
            $vistos[$fidJugador] = true;
            if (!isset($conteosAventureros[$fidJugador])) {
                $conteosAventureros[$fidJugador] = array('total' => 0, 'finalizadas' => 0);
            }
            $conteosAventureros[$fidJugador]['total']++;
            if ((int)$aventura['estado'] === 3) {
                $conteosAventureros[$fidJugador]['finalizadas']++;
            }
        }
    }
    $rankingAventureros = estadisticasRankingAventurasDesdeConteos($rankingPosts, $conteosAventureros);

    $conteosNarradores = array();
    $queryNarradores = $db->query("SELECT `narrador_fid` AS fid,
            COUNT(*) AS total,
            SUM(CASE WHEN `estado` = 3 THEN 1 ELSE 0 END) AS finalizadas
        FROM `{$tablaAventuras}`
        WHERE `estado` IN (1, 3)
          AND `narrador_fid` IS NOT NULL
          AND `narrador_fid` > 0
          AND NOT (`tipo` = 'autonarrada')
        GROUP BY `narrador_fid`");
    while ($narrador = $db->fetch_array($queryNarradores)) {
        $conteosNarradores[(int)$narrador['fid']] = array(
            'total' => (int)$narrador['total'],
            'finalizadas' => (int)$narrador['finalizadas'],
        );
    }
    $rankingNarradores = estadisticasRankingAventurasDesdeConteos($rankingPosts, $conteosNarradores);
}

$comparacionResultado = '';
$consultaRankingResultado = '';
$compararTexto = '';
$consultarTexto = '';
$compararFid = max(0, $mybb->get_input('comparar_fid', MyBB::INPUT_INT));
$consultarFid = max(0, $mybb->get_input('consultar_fid', MyBB::INPUT_INT));
if ($accion === 'consultar-ranking' && $consultarFid === 0 && $uid > 0) {
    $consultarFid = $uid;
}

if ($accion === 'comparar') {
    if ((int)$uid <= 0) {
        $comparacionResultado = '<p class="ranking-vacio">Inicia sesión para comparar tu personaje con otro.</p>';
    } elseif ($compararFid > 0) {
        $categorias = array(
            array('Posts de rol', $rankingPosts, 'posts', 'entero'),
            array('Experiencia', $rankingExperiencia, 'EXP', 'experiencia'),
            array('Temas de rol', $rankingTemas, 'temas', 'entero'),
        );
        if (!isset($rankingPosts['todos'][$compararFid])) {
            $comparacionResultado = '<p class="ranking-vacio">No se encontró el personaje seleccionado.</p>';
        } else {
            $objetivo = $rankingPosts['todos'][$compararFid];
            $personajePropio = $rankingPosts['todos'][(int)$uid];
            $compararTexto = estadisticasEscapar($objetivo['nombre_completo'] . ' (#' . $compararFid . ')');
            $filasDuelo = '';
            $victoriasPropias = 0;
            $victoriasObjetivo = 0;
            $empates = 0;
            foreach ($categorias as $categoria) {
                list($etiqueta, $rankingCategoria, $sufijo, $tipo) = $categoria;
                $propio = $rankingCategoria['todos'][(int)$uid] ?? null;
                $otro = $rankingCategoria['todos'][$compararFid] ?? null;
                if (!$propio || !$otro) continue;
                $diferencia = $propio['valor_numerico'] - $otro['valor_numerico'];
                if ($diferencia > 0) {
                    $victoriasPropias++;
                    $clasePropia = ' duelo-valor--ganador';
                    $claseObjetivo = '';
                } elseif ($diferencia < 0) {
                    $victoriasObjetivo++;
                    $clasePropia = '';
                    $claseObjetivo = ' duelo-valor--ganador';
                } else {
                    $empates++;
                    $clasePropia = $claseObjetivo = ' duelo-valor--empate';
                }
                $maximo = max(1, $propio['valor_numerico'], $otro['valor_numerico']);
                $porcentajePropio = max(3, (int)round(($propio['valor_numerico'] / $maximo) * 100));
                $porcentajeObjetivo = max(3, (int)round(($otro['valor_numerico'] / $maximo) * 100));
                $filasDuelo .= '<div class="duelo-estadistica">'
                    . '<div class="duelo-estadistica__cabecera"><span class="duelo-valor' . $clasePropia . '"><small>#' . (int)$propio['posicion'] . '</small>'
                    . $propio['valor_formateado'] . '</span><strong>' . estadisticasEscapar($etiqueta) . '</strong>'
                    . '<span class="duelo-valor duelo-valor--derecha' . $claseObjetivo . '"><small>#' . (int)$otro['posicion'] . '</small>'
                    . $otro['valor_formateado'] . '</span></div>'
                    . '<div class="duelo-barra"><span class="duelo-barra__lado duelo-barra__lado--propio"><i style="width:' . $porcentajePropio . '%"></i></span>'
                    . '<span class="duelo-barra__lado duelo-barra__lado--rival"><i style="width:' . $porcentajeObjetivo . '%"></i></span></div>'
                    . '<small class="duelo-estadistica__unidad">' . estadisticasEscapar($sufijo) . '</small></div>';
            }
            $resultadoDuelo = $victoriasPropias > $victoriasObjetivo
                ? 'Ventaja para ti'
                : ($victoriasObjetivo > $victoriasPropias ? 'Ventaja para tu rival' : 'Duelo empatado');
            $comparacionResultado = '<div class="duelo"><div class="duelo-arena">'
                . '<a class="duelo-luchador duelo-luchador--propio" href="/op/personaje.php?uid=' . (int)$uid . '"><span>Tú</span><img src="'
                . estadisticasEscapar($personajePropio['avatar']) . '" alt="" loading="lazy" decoding="async"><strong>'
                . estadisticasEscapar($personajePropio['nombre_completo']) . '</strong></a>'
                . '<div class="duelo-marcador"><span>' . $victoriasPropias . '</span><b>VS</b><span>' . $victoriasObjetivo . '</span><small>'
                . estadisticasEscapar($resultadoDuelo) . ($empates > 0 ? ' · ' . $empates . ' empate' . ($empates > 1 ? 's' : '') : '') . '</small></div>'
                . '<a class="duelo-luchador duelo-luchador--rival" href="/op/personaje.php?uid=' . $compararFid . '"><span>Rival</span><img src="'
                . estadisticasEscapar($objetivo['avatar']) . '" alt="" loading="lazy" decoding="async"><strong>'
                . estadisticasEscapar($objetivo['nombre_completo']) . '</strong></a>'
                . '</div><div class="duelo-estadisticas">' . $filasDuelo . '</div></div>';
        }
    }
}

if ($accion === 'consultar-ranking') {
    if ($consultarFid <= 0) {
        $consultaRankingResultado = '<p class="ranking-vacio">Inicia sesión para abrir tu expediente de clasificación.</p>';
    } else {
        $base = $rankingPosts['todos'][$consultarFid] ?? null;
        if (!$base) {
            $consultaRankingResultado = '<p class="ranking-vacio">No se encontró el personaje seleccionado.</p>';
        } else {
            $consultarTexto = estadisticasEscapar($base['nombre_completo'] . ' (#' . $consultarFid . ')');
            $categoriasPersonales = array(
                array('Más posts de rol', $rankingPosts, 'posts', 'entero', 'fa-comment', '#ff8500'),
                array('Más experiencia', $rankingExperiencia, 'EXP', 'experiencia', 'fa-bolt', '#8f59f7'),
                array('Más temas de rol', $rankingTemas, 'temas', 'entero', 'fa-book', '#00a8d6'),
                array('Más reputación', $rankingReputacion, 'rep', 'entero', 'fa-star', '#b3305d'),
                array('Ranking de aventurero', $rankingAventureros, 'aventuras', 'entero', 'fa-compass', '#27895a'),
                array('Ranking de narrador', $rankingNarradores, 'aventuras narradas', 'entero', 'fa-book-open', '#6844a3'),
            );
            $tarjetasPersonales = '';
            $mejorPosicion = null;
            foreach ($categoriasPersonales as $categoriaPersonal) {
                list($etiqueta, $rankingPersonal, $sufijo, $tipo, $icono, $color) = $categoriaPersonal;
                $tarjetasPersonales .= estadisticasTarjetaPersonal($rankingPersonal, $consultarFid, $etiqueta, $sufijo, $tipo, $icono, $color);
                if (isset($rankingPersonal['todos'][$consultarFid])) {
                    $posicionCategoria = (int)$rankingPersonal['todos'][$consultarFid]['posicion'];
                    $mejorPosicion = $mejorPosicion === null ? $posicionCategoria : min($mejorPosicion, $posicionCategoria);
                }
            }
            $mejorPosicionTexto = $mejorPosicion === null ? 'Sin clasificación' : 'Mejor puesto: #' . $mejorPosicion;
            $consultaRankingResultado = '<div class="consulta-expediente"><header class="consulta-personaje">'
                . '<img src="' . estadisticasEscapar($base['avatar']) . '" alt="" loading="lazy" decoding="async">'
                . '<div><small>Expediente de clasificación</small><h2><a href="/op/personaje.php?uid=' . $consultarFid . '">'
                . estadisticasEscapar($base['nombre_completo']) . '</a></h2><span>FID #' . $consultarFid . ' · ' . estadisticasEscapar($mejorPosicionTexto) . '</span></div>'
                . '<a class="consulta-personaje__ficha" href="/op/personaje.php?uid=' . $consultarFid . '">Ver ficha <i class="fa fa-arrow-right" aria-hidden="true"></i></a>'
                . '</header><div class="consulta-rankings">' . $tarjetasPersonales . '</div></div>';
        }
    }
}

eval("\$page = \"".$templates->get("op_estadisticas")."\";");
output_page($page);
