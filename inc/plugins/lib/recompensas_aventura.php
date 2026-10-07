<?php
/**
 * Tablón de aventuras: análisis de un tema y cálculo de recompensas.
 *
 * Reproduce en el servidor la lógica de op/staff/recompensasAventuras.php (la
 * herramienta real de pago de aventuras), sin cambiar reglas ni montos:
 *  - quién narró: usuarios con al menos un post con la etiqueta [narrador];
 *  - posts válidos: los de 300 caracteres o más (strlen sin etiquetas, en bytes
 *    como hoy);
 *  - porcentaje de participación según los posts esperados del tier;
 *  - recompensas de narradores (por nivel) y de jugadores (oficios, inframundo,
 *    reputación) y tope mensual de experiencia del narrador (675; por el exceso se
 *    entregan kuros).
 *
 * Diferencia deliberada: los porcentajes los calcula SIEMPRE el servidor; la
 * página antigua se fiaba del valor que enviaba el navegador.
 *
 * Las tablas de recompensas se extrajeron por copia literal de las constantes
 * RECOMPENSAS_NARRADOR_* y RECOMPENSAS_JUGADOR de recompensasAventuras.php;
 * tests/aventuras/recompensas.php las compara con esa página.
 *
 * Requiere inc/plugins/lib/aventuras.php (utilidades de SQL) ya cargado.
 */

const OPG_REC_POSTS_TIER = array(1 => 4, 2 => 5, 3 => 6, 4 => 7, 5 => 8, 6 => 9, 7 => 10, 8 => 10, 9 => 11, 10 => 12);
const OPG_REC_MIN_CARACTERES = 300;
const OPG_REC_BASE_MAX = 150;
const OPG_REC_FINAL_MAX = 200;
const OPG_REC_EXTRA_JUGADORES = array(0, 10, 20, 30, 40, 50);
const OPG_REC_EXP_NARRADOR_MENSUAL = 675;      // el mismo tope que recompensasAventuras.php
const OPG_REC_NIVELES = array('Aprendiz' => 1, 'Estudioso' => 2, 'Ilustre' => 3, 'Erudito' => 4);
const OPG_REC_NIVEL_ETIQUETA = array(1 => 'Aprendiz', 2 => 'Estudioso', 3 => 'Ilustre', 4 => 'Erudito');
const OPG_REC_UID_PUBLICACION = 5;             // usuario que publica el mensaje, como hoy
const OPG_REC_MARCADOR = 'RECOMPENSAS PROCESADAS';
const OPG_REC_REPUTACIONES = array('positiva', 'negativa', 'ninguna');
const OPG_REC_MAX_VARIACION = 100;

// Recompensas por nivel de narrador (1 a 4) y por tier, y de jugador por tier.
// Copiadas de recompensasAventuras.php sin retipear.
const OPG_REC_NARRADOR = array(
    1 => array(
        1 => array('experiencia' => 50, 'nikas' => 6, 'cofre' => 'CFN002', 'cofre_cantidad' => 1),
        2 => array('experiencia' => 75, 'nikas' => 12, 'cofre' => 'CFN003', 'cofre_cantidad' => 1),
        3 => array('experiencia' => 120, 'nikas' => 22, 'cofre' => 'CFN003', 'cofre_cantidad' => 2),
        4 => array('experiencia' => 150, 'nikas' => 34, 'cofre' => 'CFN004', 'cofre_cantidad' => 1),
        5 => array('experiencia' => 200, 'nikas' => 45, 'cofre' => 'CFN004', 'cofre_cantidad' => 2),
        6 => array('experiencia' => 250, 'nikas' => 56, 'cofre' => 'CFN005', 'cofre_cantidad' => 1),
        7 => array('experiencia' => 300, 'nikas' => 67, 'cofre' => 'CFN005', 'cofre_cantidad' => 2),
        8 => array('experiencia' => 400, 'nikas' => 88, 'cofre' => 'CFN006', 'cofre_cantidad' => 1),
        9 => array('experiencia' => 560, 'nikas' => 109, 'cofre' => 'CFN007', 'cofre_cantidad' => 1),
        10 => array('experiencia' => 730, 'nikas' => 160, 'cofre' => 'CFN008', 'cofre_cantidad' => 1),
    ),
    2 => array(
        1 => array('experiencia' => 65, 'nikas' => 7, 'cofre' => 'CFN002', 'cofre_cantidad' => 1),
        2 => array('experiencia' => 85, 'nikas' => 13, 'cofre' => 'CFN003', 'cofre_cantidad' => 1),
        3 => array('experiencia' => 140, 'nikas' => 24, 'cofre' => 'CFN003', 'cofre_cantidad' => 2),
        4 => array('experiencia' => 170, 'nikas' => 35, 'cofre' => 'CFN004', 'cofre_cantidad' => 1),
        5 => array('experiencia' => 220, 'nikas' => 46, 'cofre' => 'CFN004', 'cofre_cantidad' => 2),
        6 => array('experiencia' => 265, 'nikas' => 57, 'cofre' => 'CFN005', 'cofre_cantidad' => 1),
        7 => array('experiencia' => 320, 'nikas' => 68, 'cofre' => 'CFN005', 'cofre_cantidad' => 2),
        8 => array('experiencia' => 400, 'nikas' => 89, 'cofre' => 'CFN006', 'cofre_cantidad' => 1),
        9 => array('experiencia' => 580, 'nikas' => 110, 'cofre' => 'CFN007', 'cofre_cantidad' => 1),
        10 => array('experiencia' => 750, 'nikas' => 161, 'cofre' => 'CFN008', 'cofre_cantidad' => 1),
    ),
    3 => array(
        1 => array('experiencia' => 80, 'nikas' => 8, 'cofre' => 'CFN002', 'cofre_cantidad' => 1),
        2 => array('experiencia' => 100, 'nikas' => 14, 'cofre' => 'CFN003', 'cofre_cantidad' => 1),
        3 => array('experiencia' => 155, 'nikas' => 25, 'cofre' => 'CFN003', 'cofre_cantidad' => 2),
        4 => array('experiencia' => 180, 'nikas' => 36, 'cofre' => 'CFN004', 'cofre_cantidad' => 1),
        5 => array('experiencia' => 235, 'nikas' => 47, 'cofre' => 'CFN004', 'cofre_cantidad' => 2),
        6 => array('experiencia' => 280, 'nikas' => 58, 'cofre' => 'CFN005', 'cofre_cantidad' => 1),
        7 => array('experiencia' => 350, 'nikas' => 69, 'cofre' => 'CFN005', 'cofre_cantidad' => 2),
        8 => array('experiencia' => 410, 'nikas' => 90, 'cofre' => 'CFN006', 'cofre_cantidad' => 1),
        9 => array('experiencia' => 600, 'nikas' => 111, 'cofre' => 'CFN007', 'cofre_cantidad' => 1),
        10 => array('experiencia' => 775, 'nikas' => 162, 'cofre' => 'CFN008', 'cofre_cantidad' => 1),
    ),
    4 => array(
        1 => array('experiencia' => 115, 'nikas' => 9, 'cofre' => 'CFN002', 'cofre_cantidad' => 1),
        2 => array('experiencia' => 130, 'nikas' => 15, 'cofre' => 'CFN003', 'cofre_cantidad' => 1),
        3 => array('experiencia' => 170, 'nikas' => 26, 'cofre' => 'CFN003', 'cofre_cantidad' => 2),
        4 => array('experiencia' => 190, 'nikas' => 37, 'cofre' => 'CFN004', 'cofre_cantidad' => 1),
        5 => array('experiencia' => 250, 'nikas' => 48, 'cofre' => 'CFN004', 'cofre_cantidad' => 2),
        6 => array('experiencia' => 300, 'nikas' => 59, 'cofre' => 'CFN005', 'cofre_cantidad' => 1),
        7 => array('experiencia' => 385, 'nikas' => 70, 'cofre' => 'CFN005', 'cofre_cantidad' => 2),
        8 => array('experiencia' => 430, 'nikas' => 91, 'cofre' => 'CFN006', 'cofre_cantidad' => 1),
        9 => array('experiencia' => 620, 'nikas' => 112, 'cofre' => 'CFN007', 'cofre_cantidad' => 1),
        10 => array('experiencia' => 800, 'nikas' => 163, 'cofre' => 'CFN008', 'cofre_cantidad' => 1),
    ),
);

const OPG_REC_JUGADOR = array(
    1 => array('experiencia' => 50, 'nikas' => 5, 'reputacion' => 10, 'berries' => 1000000),
    2 => array('experiencia' => 75, 'nikas' => 10, 'reputacion' => 20, 'berries' => 5000000),
    3 => array('experiencia' => 120, 'nikas' => 20, 'reputacion' => 50, 'berries' => 10000000),
    4 => array('experiencia' => 150, 'nikas' => 30, 'reputacion' => 80, 'berries' => 15000000),
    5 => array('experiencia' => 200, 'nikas' => 40, 'reputacion' => 120, 'berries' => 25000000),
    6 => array('experiencia' => 250, 'nikas' => 50, 'reputacion' => 160, 'berries' => 50000000),
    7 => array('experiencia' => 300, 'nikas' => 60, 'reputacion' => 200, 'berries' => 75000000),
    8 => array('experiencia' => 400, 'nikas' => 80, 'reputacion' => 250, 'berries' => 100000000),
    9 => array('experiencia' => 600, 'nikas' => 100, 'reputacion' => 300, 'berries' => 200000000),
    10 => array('experiencia' => 750, 'nikas' => 150, 'reputacion' => 500, 'berries' => 350000000),
);

// ── Análisis del tema ────────────────────────────────────────────────────────

/**
 * Lee los posts de un tema y cuenta, por usuario, los totales y los válidos.
 * Devuelve array('ok', 'errores', 'tid', 'total_posts', 'usuarios', 'narradores',
 * 'posts_no_validos', 'bloqueos'). Un tema sin posts es un error; un tema sin
 * [narrador] se analiza igual pero deja un bloqueo para el pago.
 */
function opg_rec_analizar_tema($tid)
{
    $tid = (int) $tid;
    $posts = opg_av_todos(
        'SELECT pid, uid, username, message FROM ' . opg_av_t('posts') . ' WHERE tid = ' . $tid . ' ORDER BY dateline ASC, pid ASC'
    );
    if (!$posts) {
        return array('ok' => false, 'errores' => array('El tema no tiene posts.'), 'bloqueos' => array());
    }

    $usuarios = array();
    $narradores = array();
    $no_validos = 0;
    foreach ($posts as $p) {
        $uid = (int) $p['uid'];
        if (!isset($usuarios[$uid])) {
            $usuarios[$uid] = array('uid' => $uid, 'username' => (string) $p['username'], 'posts' => 0, 'validos' => 0, 'es_narrador' => false);
        }
        $usuarios[$uid]['posts']++;
        if (strlen(strip_tags((string) $p['message'])) >= OPG_REC_MIN_CARACTERES) {
            $usuarios[$uid]['validos']++;
        } else {
            $no_validos++;
        }
        if (stripos((string) $p['message'], '[narrador]') !== false) {
            $narradores[$uid] = true;
        }
    }
    foreach ($narradores as $uid => $_) {
        $usuarios[$uid]['es_narrador'] = true;
    }

    $bloqueos = array();
    if (!$narradores) {
        $bloqueos[] = 'No se detectó ningún narrador: ningún post lleva la etiqueta [narrador].';
    }
    return array(
        'ok' => true,
        'errores' => array(),
        'tid' => $tid,
        'total_posts' => count($posts),
        'usuarios' => $usuarios,
        'narradores' => array_keys($narradores),
        'posts_no_validos' => $no_validos,
        'bloqueos' => $bloqueos,
    );
}

/**
 * Porcentaje base de un usuario: proporción de posts válidos sobre los esperados;
 * por encima de lo esperado, cada post extra vale la mitad. Limitado a 0..150.
 */
function opg_rec_porcentaje_base($validos, $esperados)
{
    if ($esperados <= 0) {
        return 100.0;
    }
    if ($validos <= $esperados) {
        $p = ($validos / $esperados) * 100;
    } else {
        $extra = $validos - $esperados;
        $valor_normal = 100 / $esperados;
        $valor_extra = $valor_normal / 2;
        $p = 100 + ($extra * $valor_extra);
    }
    return max(0.0, min((float) OPG_REC_BASE_MAX, (float) $p));
}

/** Valida y completa los ajustes del staff. Devuelve array('ajustes' => ..., 'errores' => [...]). */
function opg_rec_normalizar_ajustes($ajustes, $tier_por_defecto, $reputacion_por_defecto, $n_detectados)
{
    $errores = array();
    $ajustes = is_array($ajustes) ? $ajustes : array();

    $tier = isset($ajustes['tier']) && $ajustes['tier'] !== '' ? opg_av_validar_tier($ajustes['tier']) : (int) $tier_por_defecto;
    if (!$tier) {
        $errores[] = 'El tier debe ser un número de ' . OPG_AV_TIER_MIN . ' a ' . OPG_AV_TIER_MAX . '.';
        $tier = (int) $tier_por_defecto;
    }
    $extra = 0;
    if (isset($ajustes['extra_jugadores']) && $ajustes['extra_jugadores'] !== '') {
        $e = $ajustes['extra_jugadores'];
        if (is_string($e) && ctype_digit($e)) {
            $e = (int) $e;
        }
        if (is_int($e) && in_array($e, OPG_REC_EXTRA_JUGADORES, true)) {
            $extra = $e;
        } else {
            $errores[] = 'El extra por jugadores debe ser 0, 10, 20, 30, 40 o 50.';
        }
    }
    $n = (int) $n_detectados;
    if (isset($ajustes['n_participantes']) && $ajustes['n_participantes'] !== '') {
        $v = $ajustes['n_participantes'];
        if ((is_int($v) || (is_string($v) && ctype_digit($v))) && (int) $v >= 1 && (int) $v <= 20) {
            $n = (int) $v;
        } else {
            $errores[] = 'El número de participantes debe estar entre 1 y 20.';
        }
    }
    $dest = array();
    if (isset($ajustes['destinatarios']) && is_array($ajustes['destinatarios'])) {
        foreach ($ajustes['destinatarios'] as $uid => $d) {
            $uid = (int) $uid;
            if ($uid <= 0 || !is_array($d)) {
                continue;
            }
            $var = 0;
            if (isset($d['variacion']) && $d['variacion'] !== '') {
                $x = $d['variacion'];
                if (is_string($x) && preg_match('/^-?\d+$/', $x)) {
                    $x = (int) $x;
                }
                if (is_int($x) && abs($x) <= OPG_REC_MAX_VARIACION) {
                    $var = $x;
                } else {
                    $errores[] = 'La variación de posts debe estar entre -' . OPG_REC_MAX_VARIACION . ' y ' . OPG_REC_MAX_VARIACION . '.';
                }
            }
            $rep = $reputacion_por_defecto;
            if (isset($d['rep']) && $d['rep'] !== '') {
                if (in_array($d['rep'], OPG_REC_REPUTACIONES, true)) {
                    $rep = $d['rep'];
                } else {
                    $errores[] = 'La reputación debe ser positiva, negativa o ninguna.';
                }
            }
            $dest[$uid] = array(
                'incluir' => !isset($d['incluir']) || opg_av_verdadero($d['incluir']),
                'variacion' => $var,
                'rep' => $rep,
            );
        }
    }
    return array(
        'ajustes' => array(
            'tier' => $tier,
            'extra_jugadores' => $extra,
            'n_participantes' => $n,
            'confirma_minimos' => isset($ajustes['confirma_minimos']) && opg_av_verdadero($ajustes['confirma_minimos']),
            'destinatarios' => $dest,
            'reputacion_defecto' => $reputacion_por_defecto,
        ),
        'errores' => array_values(array_unique($errores)),
    );
}

/**
 * Porcentaje de participación de cada usuario del tema con los ajustes dados.
 * Narradores: base de (válidos + variación) más el extra por jugadores, límite 200.
 * Jugadores: base de (válidos + variación), límite 200. Se redondea a 2 decimales,
 * como el valor que la página antigua enviaba desde el navegador (toFixed(2)).
 * Devuelve array(uid => array(rol, validos, variacion, ajustados, base, porcentaje)).
 */
function opg_rec_calcular_porcentajes($analisis, $ajustes)
{
    $esperados = isset(OPG_REC_POSTS_TIER[$ajustes['tier']]) ? OPG_REC_POSTS_TIER[$ajustes['tier']] : 0;
    $salida = array();
    foreach ($analisis['usuarios'] as $uid => $u) {
        $variacion = isset($ajustes['destinatarios'][$uid]) ? (int) $ajustes['destinatarios'][$uid]['variacion'] : 0;
        $ajustados = $u['validos'] + $variacion;
        $base = opg_rec_porcentaje_base($ajustados, $esperados);
        $es_narrador = !empty($u['es_narrador']);
        $final = $es_narrador ? ($base + (float) $ajustes['extra_jugadores']) : $base;
        $final = max(0.0, min((float) OPG_REC_FINAL_MAX, $final));
        $salida[$uid] = array(
            'rol' => $es_narrador ? 'narrador' : 'jugador',
            'validos' => $u['validos'],
            'variacion' => $variacion,
            'ajustados' => $ajustados,
            'base' => round($base, 2),
            'porcentaje' => round($final, 2),
        );
    }
    return $salida;
}

/** Posts mínimos: esperados del tier por número de participantes. */
function opg_rec_minimos($analisis, $ajustes)
{
    $esperados = isset(OPG_REC_POSTS_TIER[$ajustes['tier']]) ? OPG_REC_POSTS_TIER[$ajustes['tier']] : 0;
    $minimo = $esperados * (int) $ajustes['n_participantes'];
    $validos = 0;
    foreach ($analisis['usuarios'] as $u) {
        $validos += $u['validos'];
    }
    return array('esperados_por_participante' => $esperados, 'minimo' => $minimo, 'validos' => $validos, 'cumple' => $validos >= $minimo);
}

// ── Recompensas ──────────────────────────────────────────────────────────────

/** Nivel de narrador (1 a 4) de una ficha. Devuelve array(nivel, conocido). */
function opg_rec_nivel_narrador($ficha)
{
    $etiqueta = isset($ficha['nivelnarrador']) ? trim((string) $ficha['nivelnarrador']) : '';
    if (isset(OPG_REC_NIVELES[$etiqueta])) {
        return array(OPG_REC_NIVELES[$etiqueta], true);
    }
    return array(1, false);
}

/**
 * Recompensas de un narrador. Aplica el tope mensual de experiencia: lo que lo
 * excede no se entrega y por cada 2 puntos de exceso se entrega 1 kuro.
 * Devuelve las cantidades, los incrementos de ficha (relativos, como hoy), la
 * experiencia para mybb_users y el texto del log.
 */
function opg_rec_calcular_narrador($ficha, $porcentaje, $tier, $exp_mensual_actual)
{
    list($nivel, $conocido) = opg_rec_nivel_narrador($ficha);
    $base = OPG_REC_NARRADOR[$nivel][$tier];
    $exp_final = (int) round($base['experiencia'] * ($porcentaje / 100));
    $nikas = (int) round($base['nikas'] * ($porcentaje / 100));

    $exp_mensual_actual = (float) $exp_mensual_actual;
    $disponible = max(0, OPG_REC_EXP_NARRADOR_MENSUAL - $exp_mensual_actual);
    $exp_a_entregar = (int) min($exp_final, $disponible);
    $excedente = $exp_final - $exp_a_entregar;
    $kuros = $excedente > 0 ? (int) floor($excedente / 2) : 0;

    $sets = array('nika = nika + ' . $nikas);
    if ($exp_a_entregar > 0) {
        $sets[] = 'expNarradorMensualActual = expNarradorMensualActual + ' . $exp_a_entregar;
    }
    if ($kuros > 0) {
        $sets[] = 'kuro = kuro + ' . $kuros;
    }
    return array(
        'rol' => 'narrador',
        'nivel' => $nivel,
        'nivel_etiqueta' => OPG_REC_NIVEL_ETIQUETA[$nivel],
        'nivel_conocido' => $conocido,
        'porcentaje' => $porcentaje,
        'experiencia_final' => $exp_final,
        'experiencia' => $exp_a_entregar,
        'excedente' => (int) $excedente,
        'kuros' => $kuros,
        'nikas' => $nikas,
        'cofre' => $base['cofre'],
        'cofre_cantidad' => (int) $base['cofre_cantidad'],
        'exp_mensual_antes' => $exp_mensual_actual,
        'exp_mensual_despues' => $exp_mensual_actual + $exp_a_entregar,
        'sets_ficha' => $sets,
        'xp' => $exp_a_entregar,
    );
}

/**
 * Recompensas de un jugador. Oficios: Contrabandista (nivel 0 a 3 multiplica
 * las berries por 1,25, 1,50, 1,75 o 2,00) y Arqueólogo (nivel 1: x1,10 de
 * experiencia; nivel 2: +1 nika). Inframundo multiplica las berries por 1,25.
 */
function opg_rec_calcular_jugador($ficha, $porcentaje, $tier, $infra, $rep_tipo)
{
    $base = OPG_REC_JUGADOR[$tier];
    $oficios = (isset($ficha['oficios']) && $ficha['oficios'] !== '' && $ficha['oficios'] !== null) ? json_decode((string) $ficha['oficios'], true) : null;
    $oficios = is_array($oficios) ? $oficios : array();

    $nivel_contra = -1;
    if (isset($oficios['Mercader']['sub']['Contrabandista']) && is_numeric($oficios['Mercader']['sub']['Contrabandista'])) {
        $nivel_contra = (int) $oficios['Mercader']['sub']['Contrabandista'];
    }
    $mult_contra = 1;
    if ($nivel_contra === 0) { $mult_contra = 1.25; }
    elseif ($nivel_contra === 1) { $mult_contra = 1.50; }
    elseif ($nivel_contra === 2) { $mult_contra = 1.75; }
    elseif ($nivel_contra === 3) { $mult_contra = 2.00; }

    $nivel_arq = -1;
    if (isset($oficios['Investigador']['sub']['Arqueólogo']) && is_numeric($oficios['Investigador']['sub']['Arqueólogo'])) {
        $nivel_arq = (int) $oficios['Investigador']['sub']['Arqueólogo'];
    }
    $arq_xp = 1;
    $arq_nikas = 0;
    if ($nivel_arq === 0) { $arq_xp = 1.00; }
    elseif ($nivel_arq === 1) { $arq_xp = 1.10; }
    elseif ($nivel_arq === 2) { $arq_nikas = 1; }

    $exp = (int) round($base['experiencia'] * (($porcentaje * $arq_xp) / 100));
    $nikas = (int) round(($base['nikas'] + $arq_nikas) * (($porcentaje) / 100));
    $rep = ($rep_tipo === 'ninguna') ? 0 : (int) round($base['reputacion'] * ($porcentaje / 100));
    $berries_base = (int) round($base['berries'] * ($porcentaje / 100));
    if ($infra) {
        $berries = (int) round($base['berries'] * ($porcentaje / 100) * $mult_contra * 1.25);
    } else {
        $berries = (int) round($base['berries'] * ($porcentaje / 100) * $mult_contra);
    }

    $sets = array('nika = nika + ' . $nikas);
    if ($rep_tipo === 'positiva') {
        $sets[] = 'reputacion = reputacion + ' . $rep;
        $sets[] = 'reputacion_positiva = reputacion_positiva + ' . $rep;
    } elseif ($rep_tipo === 'negativa') {
        $sets[] = 'reputacion = reputacion + ' . $rep;
        $sets[] = 'reputacion_negativa = reputacion_negativa + ' . $rep;
    }
    $sets[] = 'berries = berries + ' . $berries;
    if ($infra) {
        $sets[] = 'movidoInframundo = COALESCE(movidoInframundo, 0) + ' . $berries;
    }
    return array(
        'rol' => 'jugador',
        'porcentaje' => $porcentaje,
        'experiencia' => $exp,
        'nikas' => $nikas,
        'reputacion' => $rep,
        'reputacion_tipo' => $rep_tipo,
        'berries' => $berries,
        'berries_base' => $berries_base,
        'infra' => (bool) $infra,
        'nivel_contrabandista' => $nivel_contra,
        'mult_contrabandista' => $mult_contra,
        'nivel_arqueologo' => $nivel_arq,
        'mult_xp_arqueologo' => $arq_xp,
        'nikas_extra_arqueologo' => $arq_nikas,
        'sets_ficha' => $sets,
        'xp' => $exp,
    );
}

// ── Reinicio mensual del tope de experiencia ───────────────────────────────

/**
 * Como hoy: si el mes (zona Europe/Madrid) difiere del último reinicio guardado en
 * el ajuste exp_narrador_ultimo_reset, pone expNarradorMensualActual a 0 en todas
 * las fichas y guarda el mes. Devuelve true si reinició.
 */
function opg_rec_reset_mensual()
{
    $zona = new DateTimeZone('Europe/Madrid');
    $mes = (new DateTime('now', $zona))->format('Y-m');
    $actual = opg_av_uno('SELECT value FROM ' . opg_av_t('settings') . " WHERE name = 'exp_narrador_ultimo_reset' LIMIT 1");
    if ($actual) {
        if ((string) $actual['value'] === $mes) {
            return false;
        }
        // El UPDATE condicionado evita que dos pagos simultáneos reinicien dos veces.
        opg_av_w('UPDATE ' . opg_av_t('settings') . ' SET value = ' . opg_av_sql($mes) . " WHERE name = 'exp_narrador_ultimo_reset' AND value <> " . opg_av_sql($mes));
        if (opg_av_afectadas() === 0) {
            return false;
        }
    } else {
        opg_av_w(
            'INSERT INTO ' . opg_av_t('settings') . ' (name, title, description, optionscode, value, disporder, gid) VALUES ('
            . "'exp_narrador_ultimo_reset', 'Último reset de experiencia mensual de narradores', "
            . "'Guarda el mes (YYYY-MM) del último reinicio automático', 'text', " . opg_av_sql($mes) . ', 0, 0)'
        );
    }
    opg_av_w('UPDATE ' . opg_av_t('op_fichas') . ' SET expNarradorMensualActual = 0 WHERE expNarradorMensualActual > 0');
    return true;
}

// ── Tema pagado, mensaje y cierre ──────────────────────────────────────────

/** ¿El tema ya tiene el mensaje de recompensas? (sin mirar si está cerrado) */
function opg_rec_tema_tiene_marcador($tid)
{
    global $db;
    $p = opg_av_uno(
        'SELECT pid FROM ' . opg_av_t('posts') . ' WHERE tid = ' . (int) $tid
        . " AND message LIKE '%" . $db->escape_string_like(OPG_REC_MARCADOR) . "%' LIMIT 1"
    );
    return (bool) $p;
}

/**
 * ¿El tema ya fue pagado por cualquiera de las dos herramientas? Verdadero si
 * está cerrado o ya tiene el mensaje de recompensas (las dos comprobaciones de
 * recompensasAventuras.php). Devuelve array(pagado, motivo).
 */
function opg_rec_tema_pagado($tid)
{
    global $db;
    $t = opg_av_uno('SELECT subject, fid, closed FROM ' . opg_av_t('threads') . ' WHERE tid = ' . (int) $tid . ' LIMIT 1');
    if (!$t) {
        return array(true, 'El tema no existe.');
    }
    if ((int) $t['closed'] === 1) {
        return array(true, 'El tema está cerrado: puede que ya se haya pagado.');
    }
    $p = opg_av_uno(
        'SELECT pid FROM ' . opg_av_t('posts') . ' WHERE tid = ' . (int) $tid
        . " AND message LIKE '%" . $db->escape_string_like(OPG_REC_MARCADOR) . "%' LIMIT 1"
    );
    if ($p) {
        return array(true, 'El tema ya tiene un registro de pago procesado.');
    }
    return array(false, '');
}

/**
 * Mensaje BBCode "RECOMPENSAS PROCESADAS", con el mismo formato que publica
 * recompensasAventuras.php.
 *
 * $datos: tier, staff_uid, staff_nombre, narradores (lista), jugadores (lista),
 * extra_jugadores. Cada destinatario lleva uid, username y su cálculo.
 */
function opg_rec_mensaje_resumen($datos)
{
    $m = "[align=center][img]https://en.onepiece-cardgame.com/renewal/images/beginners/index/ico_howto_current.webp[/img][/align]\n\n";
    $m .= "[align=center][size=x-large][b][color=#27ae60]" . OPG_REC_MARCADOR . "[/color][/b][/size][/align]\n\n";
    $m .= "[hr]\n\n";
    $m .= "[b][color=#3498db]Información de la Aventura[/color][/b]\n";
    $m .= "[list]\n";
    $m .= "[*][b]Tier:[/b] [color=#ff7b00]Tier {$datos['tier']}[/color]\n";
    $m .= "[*][b]Fecha de pago:[/b] " . date('d/m/Y H:i') . "\n";
    $m .= "[*][b]Procesado por:[/b] ID: {$datos['staff_uid']} - {$datos['staff_nombre']}\n";
    $m .= "[/list]\n\n";

    if (!empty($datos['narradores'])) {
        $m .= "[b][color=#9b59b6]Narradores[/color][/b]\n\n";
        foreach ($datos['narradores'] as $n) {
            $c = $n['calculo'];
            $m .= "[quote]";
            $m .= "[b]ID:{$n['uid']} - {$n['username']}[/b] - [i]Tier Narrador: {$c['nivel']}[/i]\n\n";
            $m .= "[b]Recompensas recibidas:[/b]\n";
            $m .= "[list]\n";
            if ($c['excedente'] > 0) {
                $m .= "[*][b]Experiencia:[/b] [color=#3498db]{$c['experiencia']} EXP[/color] [color=#e74c3c](⚠ Límite mensual: {$c['excedente']} EXP extra → +{$c['kuros']} kuros)[/color]\n";
                $m .= "[*][b]Exp mensual:[/b] " . $c['exp_mensual_despues'] . " / " . OPG_REC_EXP_NARRADOR_MENSUAL . "\n";
                $m .= "[*][b]Kuros:[/b] [color=#8e44ad]+{$c['kuros']} kuros[/color] [i](mitad del excedente)[/i]\n";
            } else {
                $m .= "[*][b]Experiencia:[/b] [color=#3498db]{$c['experiencia']} EXP[/color]\n";
                $m .= "[*][b]Exp mensual:[/b] " . $c['exp_mensual_despues'] . " / " . OPG_REC_EXP_NARRADOR_MENSUAL . "\n";
            }
            $m .= "[*][b]Nikas:[/b] [color=#f39c12]{$c['nikas']} NK[/color]\n";
            $m .= "[*][b]Cofres:[/b] [color=#9b59b6]{$c['cofre_cantidad']}x {$c['cofre']}[/color]\n";
            $m .= "[/list]\n\n";
            $m .= "[b]Bonificaciones:[/b]\n";
            $m .= "[list]\n";
            if ($datos['extra_jugadores'] > 0) {
                $m .= "[*][b]Jugadores extra:[/b] +{$datos['extra_jugadores']}%\n";
            }
            if ($n['variacion'] != 0) {
                $signo = $n['variacion'] > 0 ? '+' : '';
                $m .= "[*][b]Variación de post:[/b] {$signo}{$n['variacion']}%\n";
            }
            $m .= "[*][b]Porcentaje total aplicado:[/b] [color=#27ae60]{$c['porcentaje']}%[/color]\n";
            $m .= "[/list]";
            $m .= "[/quote]\n\n";
        }
    }

    if (!empty($datos['jugadores'])) {
        $m .= "[b][color=#16a085]Jugadores[/color][/b]\n\n";
        foreach ($datos['jugadores'] as $j) {
            $c = $j['calculo'];
            if ($c['reputacion_tipo'] === 'positiva') {
                $emoji = '✨'; $txt = 'Positiva';
            } elseif ($c['reputacion_tipo'] === 'negativa') {
                $emoji = '💀'; $txt = 'Negativa';
            } else {
                $emoji = '🚫'; $txt = 'Ninguna';
            }
            $m .= "[quote]";
            $m .= "[b]ID:{$j['uid']} - {$j['username']}[/b]\n\n";
            $m .= "[b]Recompensas recibidas:[/b]\n";
            $m .= "[list]\n";
            $m .= "[*][b]Experiencia:[/b] [color=#3498db]{$c['experiencia']} EXP[/color]\n";
            $m .= "[*][b]Nikas:[/b] [color=#f39c12]{$c['nikas']} NK[/color]\n";
            $m .= "[*][b]Reputación ({$emoji} {$txt}):[/b] [color=#e74c3c]{$c['reputacion']} REP[/color]\n";
            if ($c['infra'] && $c['nivel_contrabandista'] > -1) {
                $m .= "[*][b]Berries:[/b] [color=#27ae60]{$c['berries']} ฿[/color] (Base: {$c['berries_base']} + Contrabandista x{$c['mult_contrabandista']} + Inframundo 25%)\n";
            } elseif ($c['infra']) {
                $m .= "[*][b]Berries:[/b] [color=#27ae60]{$c['berries']} ฿[/color] (Base: {$c['berries_base']} + Bono inframundo 25%)\n";
            } elseif ($c['nivel_contrabandista'] > -1) {
                $m .= "[*][b]Berries:[/b] [color=#27ae60]{$c['berries']} ฿[/color] (Base: {$c['berries_base']} + Contrabandista x{$c['mult_contrabandista']})\n";
            } else {
                $m .= "[*][b]Berries:[/b] [color=#27ae60]{$c['berries']} ฿[/color]\n";
            }
            $m .= "[/list]\n\n";
            if ($j['variacion'] != 0 || $c['nivel_contrabandista'] > -1 || $c['nivel_arqueologo'] > -1) {
                $signo = $j['variacion'] > 0 ? '+' : '';
                $m .= "[b]Bonificaciones:[/b]\n";
                $m .= "[list]\n";
                if ($j['variacion'] != 0) {
                    $m .= "[*][b]Variación de post:[/b] {$signo}{$j['variacion']}%\n";
                }
                if ($c['nivel_contrabandista'] > -1) {
                    $m .= "[*][b]Contrabandista (Nivel {$c['nivel_contrabandista']}):[/b] x{$c['mult_contrabandista']} berries\n";
                }
                if ($c['nivel_arqueologo'] > -1) {
                    if ($c['nikas_extra_arqueologo'] > 0) {
                        $m .= "[*][b]Arqueologo (Nivel {$c['nivel_arqueologo']}):[/b] +{$c['nikas_extra_arqueologo']} nika extra\n";
                    } else {
                        $m .= "[*][b]Arqueologo (Nivel {$c['nivel_arqueologo']}):[/b] x{$c['mult_xp_arqueologo']} XP\n";
                    }
                }
                $m .= "[*][b]Porcentaje total aplicado:[/b] [color=#27ae60]{$c['porcentaje']}%[/color]\n";
                $m .= "[/list]";
            }
            $m .= "[/quote]\n\n";
        }
    }

    $m .= "[hr]\n\n";
    $m .= "[align=center][size=small][i]✅ Pago procesado automáticamente por el sistema de recompensas de One Piece Gaiden[/i][/size][/align]";
    return $m;
}

/**
 * Publica el mensaje en el tema como el usuario OPG_REC_UID_PUBLICACION y cierra el
 * tema, igual que recompensasAventuras.php. Devuelve array('ok', 'pid'|'error').
 */
function opg_rec_publicar_y_cerrar($tid, $mensaje)
{
    $tid = (int) $tid;
    $tema = opg_av_uno('SELECT subject, fid FROM ' . opg_av_t('threads') . ' WHERE tid = ' . $tid . ' LIMIT 1');
    if (!$tema) {
        return array('ok' => false, 'error' => 'No se encontró el tema.');
    }
    $usuario = opg_av_uno('SELECT username FROM ' . opg_av_t('users') . ' WHERE uid = ' . OPG_REC_UID_PUBLICACION . ' LIMIT 1');
    if (!$usuario) {
        return array('ok' => false, 'error' => 'No se encontró el usuario que publica el mensaje.');
    }
    if (!class_exists('PostDataHandler', false)) {
        if (!defined('MYBB_ROOT') || !file_exists(MYBB_ROOT . 'inc/datahandlers/post.php')) {
            return array('ok' => false, 'error' => 'No se encuentra el manejador de posts.');
        }
        require_once MYBB_ROOT . 'inc/datahandlers/post.php';
    }
    if (!class_exists('PostDataHandler')) {
        return array('ok' => false, 'error' => 'La clase PostDataHandler no está disponible.');
    }
    $posthandler = new PostDataHandler('insert');
    $post = array(
        'tid' => $tid,
        'fid' => (int) $tema['fid'],
        'uid' => OPG_REC_UID_PUBLICACION,
        'username' => $usuario['username'],
        'subject' => 'RE: ' . $tema['subject'],
        'message' => $mensaje,
        'ipaddress' => get_ip(),
        'posthash' => md5(OPG_REC_UID_PUBLICACION . random_str()),
        'options' => array('signature' => 0, 'subscriptionmethod' => '', 'disablesmilies' => 0),
    );
    $posthandler->action = 'post';
    $posthandler->set_data($post);
    if (!$posthandler->validate_post()) {
        return array('ok' => false, 'error' => 'Error al crear el post: ' . implode(', ', $posthandler->get_friendly_errors()));
    }
    $info = $posthandler->insert_post();
    opg_av_w('UPDATE ' . opg_av_t('threads') . ' SET closed = 1 WHERE tid = ' . $tid);
    return array('ok' => true, 'pid' => (int) $info['pid']);
}
