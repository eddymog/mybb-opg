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
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$tiempo = 14;
$last_two_weeks = time() - (14 * 24 * 3600);
$accion = 'censo';
$dateformat = '';

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

$accionesPermitidas = array('censo', 'mas-posts', 'mas-experiencia', 'mas-temas', 'comparar', 'consultar-ranking');
if (!in_array($accion, $accionesPermitidas, true)) {
    $accion = 'censo';
}

if (!in_array($tiempo, array(1, 2, 7, 14, 28), true)) {
    $tiempo = 14;
}
$timestamp = time() - ($tiempo * 24 * 3600);
$dateformat = date("Y-m-d H:i:s", $timestamp);

function queryUsersFaccion($faccion) {
    global $db, $timestamp;

    return $db->query("
        SELECT * FROM (SELECT DISTINCT fichas.faccion, p.username, p.uid, fichas.nombre, users.avatar, count(*) as posts FROM mybb_posts as p 
        INNER JOIN mybb_threads as t ON p.tid = t.tid 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        INNER JOIN mybb_op_fichas as fichas ON p.uid = fichas.fid 
        INNER JOIN mybb_users as users ON users.uid = fichas.fid
        WHERE p.dateline > $timestamp
        AND fichas.faccion = '$faccion'                                                             
        AND f.parentlist LIKE '10,%'
        GROUP BY fichas.nombre
        ORDER BY p.username) t;
    ");
}

function queryNumeroPostsTotal() {
    global $db, $timestamp;
    return $db->query("
        SELECT COUNT(*) as numeroPosts FROM mybb_posts as p 
        INNER JOIN mybb_threads as t ON p.tid = t.tid 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        WHERE p.dateline > $timestamp
    ");
}

function queryNumeroPosts() {
    global $db, $timestamp;
    return $db->query("
        SELECT COUNT(*) as numeroPosts FROM mybb_posts as p 
        INNER JOIN mybb_threads as t ON p.tid = t.tid 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        WHERE p.dateline > $timestamp
        AND f.parentlist LIKE '10,%'
    ");
}

function queryNumeroUsuarios() {
    global $db, $timestamp;
    return $db->query("
        SELECT p.username, count(p.username) FROM mybb_posts as p 
        INNER JOIN mybb_threads as t ON p.tid = t.tid 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        WHERE p.dateline > $timestamp
        AND f.parentlist LIKE '10,%'
        GROUP BY p.username;
    ");
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
        WHERE f.nombre LIKE '%{$like}%' OR f.apodo LIKE '%{$like}%'{$porId}
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

$pirataUsuarios = 0;
$pirataUsuariosStr = "";
$marineUsuarios = 0;
$marineUsuariosStr = "";
$cipherPolUsuarios = 0;
$cipherPolUsuariosStr = "";
$revoUsuarios = 0;
$revoUsuariosStr = "";
$cazarrecompensasUsuarios = 0;
$cazarrecompensasUsuariosStr = "";
$civilUsuarios = 0;
$civilUsuariosStr = "";

$numeroPosts = 0;
$totalPjs = 0;

$totalNumPostsPirata = 0;
$totalNumPostsMarine = 0;
$totalNumPostsCP = 0;
$totalNumPostsRevo = 0;
$totalNumPostsCaza = 0;
$totalNumPostsCivil = 0;
$progresoCenso = '';

if ($accion == 'censo') {
    $query_posts_total = queryNumeroPostsTotal();
    $query_posts = queryNumeroPosts();
    $query_numero_usuarios = queryNumeroUsuarios();
    $query_pirata = queryUsersFaccion('Pirata');
    $query_marine = queryUsersFaccion('Marina');
    $query_cipher_pol = queryUsersFaccion('CipherPol');
    $query_revo = queryUsersFaccion('Revolucionario');
    $query_cazarrecompensas = queryUsersFaccion('Cazadores');
    $query_civil = queryUsersFaccion('Civil');

    if ((int)$uid > 0) {
        $queryCensoRanking = $db->query("SELECT p.uid, COUNT(*) AS valor
            FROM mybb_posts p
            INNER JOIN mybb_threads t ON t.tid = p.tid
            INNER JOIN mybb_forums f ON f.fid = t.fid
            INNER JOIN mybb_op_fichas fi ON fi.fid = p.uid
            WHERE p.dateline > {$timestamp} AND f.parentlist LIKE '10,%'
            GROUP BY p.uid ORDER BY valor DESC, p.uid ASC");
        $liderCenso = 0;
        $lideresCenso = 0;
        $postsPersonalesCenso = 0;
        while ($filaCenso = $db->fetch_array($queryCensoRanking)) {
            if ($liderCenso === 0) $liderCenso = (int)$filaCenso['valor'];
            if ((int)$filaCenso['valor'] === $liderCenso) $lideresCenso++;
            if ((int)$filaCenso['uid'] === (int)$uid) $postsPersonalesCenso = (int)$filaCenso['valor'];
        }
        if ($liderCenso > 0) {
            if ($postsPersonalesCenso >= $liderCenso) {
                $textoProgresoCenso = $lideresCenso > 1
                    ? 'Estás empatado en el primer puesto. Un post más te permitiría liderar en solitario.'
                    : 'Eres quien más ha posteado durante este periodo.';
            } else {
                $faltanCenso = $liderCenso - $postsPersonalesCenso + 1;
                $textoProgresoCenso = 'Necesitas ' . my_number_format($faltanCenso) . ' posts más para superar al líder del censo.';
            }
            $progresoCenso = '<div class="censo-progreso">'
                . '<span class="censo-progreso__titulo"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Tu actividad</span>'
                . '<span class="censo-progreso__contador"><strong>' . my_number_format($postsPersonalesCenso) . '</strong><small>posts en este periodo</small></span>'
                . '<span class="censo-progreso__mensaje">' . $textoProgresoCenso . '</span>'
                . '</div>';
        }
    }

    $oddEvenCivil = 0;
    $oddEvenCaza  = 0;
    $oddEvenRevo  = 0;
    $oddEvenCP = 0;
    $oddEvenMarine  = 0;
    $oddEvenPirata = 0;

    while ($q = $db->fetch_array($query_posts_total)) {
        $numeroPostsTotal = $q['numeroPosts'];
    }

    while ($q = $db->fetch_array($query_posts)) {
        $numeroPosts = $q['numeroPosts'];
    }

    while ($q = $db->fetch_array($query_numero_usuarios)) {
        $totalPjs += 1;
    }
    
    while ($q = $db->fetch_array($query_pirata)) {

        if ($q['posts'] != '0') {
            $nombre = $q['nombre'];
            $player_uid = $q['uid'];
            $pirataUsuarios += 1;

            $avatar = $q['avatar'];
            if ($avatar == '') { $avatar = "/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png"; }

            $userNumPosts = $q['posts'];
            $totalNumPostsPirata = $totalNumPostsPirata + intval($q['posts']);

            if (fmod($oddEvenPirata, 2) == 0) {
                $backgroundColor = '#9152de';
            } else {
                $backgroundColor = '#a776ff';
            }
            $oddEvenPirata = $oddEvenPirata + 1;

            $pirataUsuariosStr = $pirataUsuariosStr . "
                <div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>
                    <div><a href='/op/ficha.php?uid=$player_uid' target='_blank'><img src='$avatar' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>
                    <div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: $backgroundColor; '>$nombre <br />($userNumPosts)</div>
                </div>
            ";
        }
    }

    while ($q = $db->fetch_array($query_marine)) {

        if ($q['posts'] != '0') {
            $nombre = $q['nombre'];
            $player_uid = $q['uid'];
            $marineUsuarios += 1;
    
            $avatar = $q['avatar'];
            if ($avatar == '') { $avatar = "/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png"; }

            $userNumPosts = $q['posts'];
            $totalNumPostsMarine = $totalNumPostsMarine + intval($q['posts']);

            if (fmod($oddEvenMarine, 2) == 0) {
                $backgroundColor = '#9152de';
            } else {
                $backgroundColor = '#a776ff';
            }
            $oddEvenMarine = $oddEvenMarine + 1;
    
            $marineUsuariosStr .= "
                <div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>
                    <div><a href='/op/ficha.php?uid=$player_uid' target='_blank'><img src='$avatar' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>
                    <div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: $backgroundColor; '>$nombre <br />($userNumPosts)</div>
                </div>
            ";
        } 
    }

    while ($q = $db->fetch_array($query_cipher_pol)) {

        if ($q['posts'] != '0') {
            $nombre = $q['nombre'];
            $player_uid = $q['uid'];
            $avatar = $q['avatar'];
            if ($avatar == '') { $avatar = "/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png"; }
            $userNumPosts = $q['posts'];
            $totalNumPostsCP = $totalNumPostsCP + intval($q['posts']);
            $cipherPolUsuarios += 1;

            if (fmod($oddEvenCP, 2) == 0) {
                $backgroundColor = '#9152de';
            } else {
                $backgroundColor = '#a776ff';
            }

            $oddEvenCP = $oddEvenCP + 1;
            $cipherPolUsuariosStr .= "
                <div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>
                    <div><a href='/op/ficha.php?uid=$player_uid' target='_blank'><img src='$avatar' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>
                    <div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: $backgroundColor; '>$nombre <br />($userNumPosts)</div>
                </div>
            ";
        }
    }

    while ($q = $db->fetch_array($query_revo)) {

        if ($q['posts'] != '0') {
            $nombre = $q['nombre'];
            $player_uid = $q['uid'];
            $revoUsuarios += 1;

            $avatar = $q['avatar'];
            if ($avatar == '') { $avatar = "/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png"; }
            $userNumPosts = $q['posts'];
            $totalNumPostsRevo = $totalNumPostsRevo + intval($q['posts']);

            if (fmod($oddEvenRevo, 2) == 0) {
                $backgroundColor = '#9152de';
            } else {
                $backgroundColor = '#a776ff';
            }
            $oddEvenRevo = $oddEvenRevo + 1;

            $revoUsuariosStr .= "
                <div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>
                    <div><a href='/op/ficha.php?uid=$player_uid' target='_blank'><img src='$avatar' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>
                    <div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: $backgroundColor; '>$nombre <br />($userNumPosts)</div>
                </div>
            ";
        }
    }

    while ($q = $db->fetch_array($query_cazarrecompensas)) {

        if ($q['posts'] != '0') {
            $nombre = $q['nombre'];
            $player_uid = $q['uid'];
            $cazarrecompensasUsuarios += 1;

            $avatar = $q['avatar'];
            if ($avatar == '') { $avatar = "/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png"; }
            $userNumPosts = $q['posts'];
            $totalNumPostsCaza = $totalNumPostsCaza + intval($q['posts']);

            if (fmod($oddEvenCaza, 2) == 0) {
                $backgroundColor = '#9152de';
            } else {
                $backgroundColor = '#a776ff';
            }
            $oddEvenCaza = $oddEvenCaza + 1;

            $cazarrecompensasUsuariosStr .= "
                <div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>
                    <div><a href='/op/ficha.php?uid=$player_uid' target='_blank'><img src='$avatar' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>
                    <div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: $backgroundColor; '>$nombre <br />($userNumPosts)</div>
                </div>
            ";
        }
    }

    while ($q = $db->fetch_array($query_civil)) {

        if ($q['posts'] != '0') {
            $nombre = $q['nombre'];
            $player_uid = $q['uid'];
            $civilUsuarios += 1;

            $avatar = $q['avatar'];
            if ($avatar == '') { $avatar = "/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png"; }

            $userNumPosts = $q['posts'];
            $totalNumPostsCivil = $totalNumPostsCivil + intval($q['posts']);

            if (fmod($oddEvenCivil, 2) == 0) {
                $backgroundColor = '#9152de';
            } else {
                $backgroundColor = '#a776ff';
            }
            $oddEvenCivil = $oddEvenCivil + 1;

            $civilUsuariosStr .= "
                <div style='display: flex;flex-direction: row;border-left: 1px solid black;border-right: 1px solid black;'>
                    <div><a href='/op/ficha.php?uid=$player_uid' target='_blank'><img src='$avatar' loading='lazy' decoding='async' style=' width: 60px; height: 60px; border: 1px solid white; box-sizing: border-box; '></a></div>
                    <div style=' text-align: center; width: 100%; font-size: 21px; font-family: InterRegular; color: black; background-color: $backgroundColor; '>$nombre <br />($userNumPosts)</div>
                </div>
            ";
        }
    }
    
    // while ($q = $db->fetch_array($query_marine)) {
    //     $nombreClan = ucwords($q['nombreClan']);
    //     $numeroPjs = $q['numeroPjs'];
    //     $clanesKiri = $clanesKiri + $numeroPjs;
    //     $clanesKiriStr .= "<h6>$nombreClan - $numeroPjs</h6>";
    // }
    
    // while ($q = $db->fetch_array($query_cipher_pol)) {
    //     $nombreClan = ucwords($q['nombreClan']);
    //     $numeroPjs = $q['numeroPjs'];
    //     $clanesIwa = $clanesIwa + $numeroPjs;
    //     $clanesIwaStr .= "<h6>$nombreClan - $numeroPjs</h6>";
    // }
    
    // while ($q = $db->fetch_array($query_civil)) {
    //     $nombreClan = ucwords($q['nombreClan']);
    //     $numeroPjs = $q['numeroPjs'];
    //     $clanesKumo = $clanesKumo + $numeroPjs;
    //     $clanesKumoStr .= "<h6>$nombreClan - $numeroPjs</h6>";
    // }
    
    // while ($q = $db->fetch_array($query_cazarrecompensas)) {
    //     $nombreClan = ucwords($q['nombreClan']);
    //     $numeroPjs = $q['numeroPjs'];
    //     $clanesSinAldea = $clanesSinAldea + $numeroPjs;
    //     $clanesSinAldeaStr .= "<h6>$nombreClan - $numeroPjs</h6>";
    // }
    
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
    WHERE u.uid NOT IN (92, 966, 152, 121, 850)
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
    ORDER BY valor DESC, fi.nombre ASC, u.uid ASC
");
    $rankingTemas = estadisticasConstruirRanking($queryRankingTemas, 'entero', 'temas', (int)$uid);
    $podioTemasRol = $rankingTemas['podio'];
    $topTemasRol = $rankingTemas['lista'];
    $posicionTemasRol = $rankingTemas['personal'];
    $metaTemasRol = $rankingTemas['meta'];
}

$comparacionResultado = '';
$consultaRankingResultado = '';
$compararTexto = '';
$consultarTexto = '';
$compararFid = max(0, $mybb->get_input('comparar_fid', MyBB::INPUT_INT));
$consultarFid = max(0, $mybb->get_input('consultar_fid', MyBB::INPUT_INT));

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

if ($accion === 'consultar-ranking' && $consultarFid > 0) {
    $base = $rankingPosts['todos'][$consultarFid] ?? null;
    if (!$base) {
        $consultaRankingResultado = '<p class="ranking-vacio">No se encontró el personaje seleccionado.</p>';
    } else {
        $consultarTexto = estadisticasEscapar($base['nombre_completo'] . ' (#' . $consultarFid . ')');
        $consultaRankingResultado = '<div class="consulta-personaje"><img src="' . estadisticasEscapar($base['avatar'])
            . '" alt="" loading="lazy" decoding="async"><div><a href="/op/personaje.php?uid=' . $consultarFid . '">'
            . estadisticasEscapar($base['nombre_completo']) . '</a><small>FID #' . $consultarFid . '</small></div></div><div class="consulta-rankings">';
        $resumenes = array(
            array('Más Posts', $rankingPosts, 'posts'),
            array('Más Experiencia', $rankingExperiencia, 'EXP'),
            array('Más Temas', $rankingTemas, 'temas'),
        );
        foreach ($resumenes as $resumen) {
            list($etiqueta, $rankingResumen, $sufijo) = $resumen;
            $filaResumen = $rankingResumen['todos'][$consultarFid] ?? null;
            if (!$filaResumen) continue;
            $consultaRankingResultado .= '<div><span>' . estadisticasEscapar($etiqueta) . '</span><strong>#'
                . (int)$filaResumen['posicion'] . '</strong><small>' . $filaResumen['valor_formateado'] . ' '
                . estadisticasEscapar($sufijo) . '</small></div>';
        }
        $consultaRankingResultado .= '</div>';
    }
}

eval("\$page = \"".$templates->get("op_estadisticas")."\";");
output_page($page);
