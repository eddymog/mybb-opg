<?php
/**
 * Calculadora Bélica — complemento para escribir posts bélicos.
 *
 * Todo el código vive aquí a propósito (100_Requirements_Belico.md §2): sin
 * plantilla, sin eval(). Las partes fijas de HTML/CSS/JS van en nowdoc
 * (<<<'...'), que PHP no interpreta — mismo recurso que op/cronologia.php
 * (líneas 923 y 1000) — así el JS puede usar $ de jQuery sin romper nada.
 *
 * ?tid= y ?fid= son opcionales. Ver 200_DesignPlan_Belico.md.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'belico.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";
require_once MYBB_ROOT."/inc/plugins/lib/belico_calculos.php";

global $mybb, $db, $headerinclude, $header, $footer;

// ───────────────────────────────────────────────────────────────────────────
// Reglas de la Guía Bélica, todas en un solo lugar (200_DesignPlan §6)
// ───────────────────────────────────────────────────────────────────────────

function belico_reglas()
{
    static $reglas = null;
    if ($reglas === null) {
        $reglas = array(
            // Guía b01_guia_belica.md, líneas citadas en 100_Requirements_Belico.md §3.3
            'distancia_ta'          => array('cada_metros' => 20, 'penalizacion' => -5),                          // 309
            'sorpresa_ref'          => -20,                                                                       // 310
            'bloque_sin_cortar'     => 5,                                                                         // 96
            'mantenida_minima'      => -45, // texto de la línea 311 (no el ejemplo) — 100_Requirements §3.4 nota 2
            'regla_efectos_divisor' => 3,   // ceil(daño/3): con 200 de daño exige 67, no 66 — 317
            'caida_por_metro'       => 15,                                                                        // 374
            'asfixia_cada'          => 20,                                                                        // 396
            // Tabla de reacciones, de mayor a menor diferencia — 100_Requirements §3.4 (imagen pasada por el usuario)
            'reacciones' => array(
                array('min' => 31,              'max' => PHP_INT_MAX, 'tipo' => 'Esquiva Absoluta', 'pct' => 100),
                array('min' => 21,              'max' => 30,          'tipo' => 'Esquiva Efectiva',  'pct' => 75),
                array('min' => 11,              'max' => 20,          'tipo' => 'Esquiva Torpe',     'pct' => 50),
                array('min' => 0,               'max' => 10,          'tipo' => 'Choque Absoluto',   'pct' => 100),
                array('min' => -15,             'max' => -1,          'tipo' => 'Choque Efectivo',   'pct' => 75),
                array('min' => -30,             'max' => -16,         'tipo' => 'Choque Torpe',      'pct' => 50),
                array('min' => -45,             'max' => -31,         'tipo' => 'Bloqueo Absoluto',  'pct' => 100),
                array('min' => -60,             'max' => -46,         'tipo' => 'Bloqueo Efectivo',  'pct' => 75),
                array('min' => -75,             'max' => -61,         'tipo' => 'Bloqueo Torpe',     'pct' => 50),
                array('min' => PHP_INT_MIN,     'max' => -76,         'tipo' => 'Sin Reacción',      'pct' => 0),
            ),
        );
    }
    return $reglas;
}

// ───────────────────────────────────────────────────────────────────────────
// Carga de datos (belico_cargar_*) — portado de op/belico_data.php,
// cambiando $uid por $fid (100_Requirements_Belico.md §3.1)
// ───────────────────────────────────────────────────────────────────────────

function belico_cargar_ficha($db, $fid, $tid)
{
    $fid = (int) $fid;
    $tid = (int) $tid;

    $cols = "nivel, fuerza, fuerza_pasiva, agilidad, agilidad_pasiva,
             punteria, punteria_pasiva, destreza, destreza_pasiva,
             resistencia, resistencia_pasiva, reflejos, reflejos_pasiva,
             voluntad, voluntad_pasiva,
             vitalidad, energia, haki, vitalidad_pasiva, energia_pasiva, haki_pasiva";

    $ficha  = null;
    $origen = 'actual';
    $pid    = null;

    if ($tid > 0) {
        $qtp   = $db->query("SELECT {$cols}, pid, nombre FROM mybb_op_thread_personaje WHERE tid='{$tid}' AND uid='{$fid}' ORDER BY pid ASC LIMIT 1");
        $ficha = $db->fetch_array($qtp);
        if ($ficha) {
            $origen = 'hilo';
            $pid    = (int) $ficha['pid'];
        }
    }

    if (!$ficha) {
        $qf    = $db->query("SELECT {$cols}, nombre FROM mybb_op_fichas WHERE fid='{$fid}'");
        $ficha = $db->fetch_array($qf);
        $origen = 'actual';
    }

    if (!$ficha) {
        return null;
    }

    return array(
        'stats'  => $ficha,
        'origen' => $origen,
        'pid'    => $pid,
        'nombre' => $ficha['nombre'] !== '' ? $ficha['nombre'] : ('Personaje #' . $fid),
    );
}

function belico_calc_atributos_finales($stats)
{
    return array(
        'NIV' => (int) $stats['nivel'],
        'FUE' => (int) $stats['fuerza']      + (int) $stats['fuerza_pasiva'],
        'AGI' => (int) $stats['agilidad']    + (int) $stats['agilidad_pasiva'],
        'PUN' => (int) $stats['punteria']    + (int) $stats['punteria_pasiva'],
        'DES' => (int) $stats['destreza']    + (int) $stats['destreza_pasiva'],
        'RES' => (int) $stats['resistencia'] + (int) $stats['resistencia_pasiva'],
        'REF' => (int) $stats['reflejos']    + (int) $stats['reflejos_pasiva'],
        'VOL' => (int) $stats['voluntad']    + (int) $stats['voluntad_pasiva'],
    );
}

// Mismas fórmulas que belico_data.php:52-80, con $fid en vez de $uid.
function belico_calc_recursos_maximos($db, $fid, $stats)
{
    $fid   = (int) $fid;
    $nivel = (int) $stats['nivel'];

    $vida_max = (int) $stats['vitalidad'] + (int) $stats['vitalidad_pasiva'] +
        (int) floor((int) $stats['fuerza_pasiva'] * 6 + (int) $stats['resistencia_pasiva'] * 15 +
                    (int) $stats['destreza_pasiva'] * 4 + (int) $stats['agilidad_pasiva'] * 3 +
                    (int) $stats['punteria_pasiva'] * 2 + (int) $stats['reflejos_pasiva'] * 1 +
                    (int) $stats['voluntad_pasiva'] * 1);

    $energia_max = (int) $stats['energia'] + (int) $stats['energia_pasiva'] +
        (int) floor((int) $stats['destreza_pasiva'] * 4 + (int) $stats['agilidad_pasiva'] * 5 +
                    (int) $stats['fuerza_pasiva'] * 2    + (int) $stats['resistencia_pasiva'] * 4 +
                    (int) $stats['punteria_pasiva'] * 5  + (int) $stats['reflejos_pasiva'] * 1 +
                    (int) $stats['voluntad_pasiva'] * 1);

    $haki_max = (int) $stats['haki'] + (int) $stats['haki_pasiva'] +
        (int) floor((int) $stats['voluntad_pasiva'] * 10);

    foreach (array('V039' => 20, 'V038' => 15, 'V037' => 10) as $vid => $bonus) {
        $qv = $db->query("SELECT COUNT(*) AS c FROM mybb_op_virtudes_usuarios WHERE uid='{$fid}' AND virtud_id='{$vid}'");
        if ((int) $db->fetch_field($qv, 'c') > 0) { $vida_max += $nivel * $bonus; break; }
    }
    foreach (array('V041' => 15, 'V040' => 10) as $vid => $bonus) {
        $qv = $db->query("SELECT COUNT(*) AS c FROM mybb_op_virtudes_usuarios WHERE uid='{$fid}' AND virtud_id='{$vid}'");
        if ((int) $db->fetch_field($qv, 'c') > 0) { $energia_max += $nivel * $bonus; break; }
    }
    foreach (array('V059' => 10, 'V058' => 5) as $vid => $bonus) {
        $qv = $db->query("SELECT COUNT(*) AS c FROM mybb_op_virtudes_usuarios WHERE uid='{$fid}' AND virtud_id='{$vid}'");
        if ((int) $db->fetch_field($qv, 'c') > 0) { $haki_max += $nivel * $bonus; break; }
    }

    return array('vida_max' => $vida_max, 'energia_max' => $energia_max, 'haki_max' => $haki_max);
}

function belico_cargar_tecnicas($db, $fid)
{
    $fid      = (int) $fid;
    $tecnicas = array();
    $q = $db->query("
        SELECT t.tid, t.nombre, t.rama, t.estilo, t.clase, t.tipo, t.tier, t.efectos, t.energia, t.energia_turno, t.haki, t.haki_turno, t.enfriamiento, t.descripcion
        FROM mybb_op_tecnicas t
        INNER JOIN mybb_op_tec_aprendidas ta ON t.tid = ta.tid
        WHERE ta.uid = '{$fid}'
        ORDER BY t.tid, t.rama
    ");
    while ($row = $db->fetch_array($q)) {
        $row['grupo']   = belico_grupo_tecnica($row);
        $row['calcula'] = belico_tecnica_calcula($row['efectos'], $row['descripcion']);
        $tecnicas[$row['tid']] = $row;
    }
    return $tecnicas;
}

// Grupo del selector de técnica. null = pasiva (tiene su propia pestaña, no se elige aquí).
function belico_grupo_tecnica($tecnica)
{
    $t = mb_strtolower((string) $tecnica['clase'] . ' ' . (string) $tecnica['tipo'], 'UTF-8');
    if (strpos($t, 'pasiva') !== false)    { return null; }
    if (strpos($t, 'utilidad') !== false)  { return 'Utilidad'; }
    if (strpos($t, 'ofensiva') !== false)  { return 'Ofensivas'; }
    if (strpos($t, 'defensiva') !== false) { return 'Defensivas'; }
    if (strpos($t, 'ambiental') !== false) { return 'Ambientales'; }
    return 'Otras';
}

// ¿Algún efecto de la técnica depende del personaje (Golpe/Bloqueo básico, Nivel o atributos)?
// No depende de los valores, así que se evalúa con ceros.
function belico_tecnica_calcula($efectos, $descripcion = '')
{
    $cero  = array('NIV' => 0, 'FUE' => 0, 'AGI' => 0, 'PUN' => 0, 'DES' => 0, 'RES' => 0, 'REF' => 0, 'VOL' => 0);
    $vacio = array('valor' => 0, 'tipo' => '', 'pasos' => array(), 'configuracion' => '');
    return !empty(opg_belico_calcular_efectos_contextuales((string) $efectos, $cero, $vacio, $vacio))
        || opg_belico_hay_formulas_sueltas($efectos) || opg_belico_hay_formulas_sueltas($descripcion);
}

function belico_etiqueta_tecnica($tecnica)
{
    $tier = trim((string) $tecnica['tier']);
    return ($tecnica['calcula'] ? '◆ ' : '') . '[' . $tecnica['tid'] . '] ' . $tecnica['nombre'] . ($tier !== '' ? ' · Tier ' . $tier : '');
}

function belico_orden_tier($tecnica)
{
    return (int) preg_replace('/\D+/', '', (string) $tecnica['tier']);
}

function belico_meta_tecnica($tecnica)
{
    $partes = array();
    $tipo = trim((string) $tecnica['clase'] . ' · ' . (string) $tecnica['tipo'], ' ·');
    if ($tipo !== '') { $partes[] = $tipo; }
    if (trim((string) $tecnica['tier']) !== '') { $partes[] = 'Tier ' . trim((string) $tecnica['tier']); }
    foreach (array('energia' => 'Energía', 'energia_turno' => 'Energía por turno', 'haki' => 'Haki', 'haki_turno' => 'Haki por turno', 'enfriamiento' => 'Enfriamiento') as $campo => $nombre) {
        $valor = trim((string) $tecnica[$campo]);
        if ($valor !== '' && $valor !== '0') { $partes[] = $nombre . ' ' . $valor; }
    }
    return implode(' · ', $partes);
}

// Solo armas (categoria='armas'), sin LLST001. Misma consulta que usa tecnicas_show.php
// (incluye cantidad y apodo del inventario).
function belico_cargar_armas($db, $fid)
{
    return opg_belico_cargar_armas_usuario($db, $fid);
}

function belico_cargar_hilo($tid)
{
    $tid = (int) $tid;
    if ($tid <= 0) {
        return null;
    }
    $thread = get_thread($tid);
    if (!$thread) {
        return null;
    }
    $perms = forum_permissions((int) $thread['fid']);
    return array(
        'tid'       => $tid,
        'subject'   => $thread['subject'],
        'fid'       => (int) $thread['fid'],
        'puede_ver' => !empty($perms['canview']),
    );
}

// ───────────────────────────────────────────────────────────────────────────
// Cálculos (belico_calc_*) — sin $db, sin HTML, portados de calc.js donde
// corresponde (200_DesignPlan_Belico.md §5)
// ───────────────────────────────────────────────────────────────────────────

// Estadísticas bélicas — mismas fórmulas y mismo redondeo que
// calc.js::_actualizarEstadisticas() (100_Requirements_Belico.md §3.2).
function belico_calc_estadisticas($atributos)
{
    $FUE = $atributos['FUE']; $AGI = $atributos['AGI']; $RES = $atributos['RES'];
    $DES = $atributos['DES']; $VOL = $atributos['VOL']; $NIV = $atributos['NIV'];
    $mov = (int) floor($AGI / 2 + $RES / 4);
    return array(
        'defensa_pasiva'       => $RES,
        'fortaleza_espiritual' => $VOL,
        'regen_energia'        => $NIV,
        'regen_haki'           => (int) ceil($NIV / 2),
        'concentracion'        => $VOL + $RES,
        'umbral_dolor'         => ($VOL + $RES) * 5,
        'movimiento'           => $mov,
        'salto'                => (int) round(1 + ($FUE + $AGI) / 5),
        'trepar'               => (int) round(1 + ($AGI + $DES) / 10),
        'nadar'                => (int) round($mov / 2),
        'dist_lanzamiento'     => (int) floor($FUE / 2),
        'limite_caida'         => (int) round(2 + ($FUE + $RES) / 4),
    );
}

// Tabla de reacciones (100_Requirements_Belico.md §3.4). $sorpresa:
// 'no' | 'detectado' | 'sin_detectar'.
function belico_calc_reaccion($ref, $ta, $metros, $sorpresa, $bloques_sin_cortar, $mantenida_ref)
{
    $reglas = belico_reglas();

    if ($sorpresa === 'sin_detectar') {
        return array('reaccion' => 'Sin Reacción', 'porcentaje' => 0, 'diferencia' => null,
                      'nota' => 'Ataque sorpresa no detectado: no hay reacción posible.');
    }

    // La penalización es negativa (-5): la distancia reduce la TA de la ofensiva, no la sube.
    $ta_ajustada = $ta + $reglas['distancia_ta']['penalizacion'] * (int) floor($metros / $reglas['distancia_ta']['cada_metros']);

    $ref_ajustado = $ref + $reglas['bloque_sin_cortar'] * $bloques_sin_cortar;
    if ($sorpresa === 'detectado') {
        $ref_ajustado += $reglas['sorpresa_ref'];
    }

    $diferencia_sin_mantenida = $ref_ajustado - $ta_ajustada;
    $diferencia               = $diferencia_sin_mantenida;
    $mantenida_aplicada       = false;
    if ($mantenida_ref > 0 && $diferencia_sin_mantenida >= $reglas['mantenida_minima']) {
        $diferencia         = ($ref_ajustado + $mantenida_ref) - $ta_ajustada;
        $mantenida_aplicada = true;
    }

    foreach ($reglas['reacciones'] as $fila) {
        if ($diferencia >= $fila['min'] && $diferencia <= $fila['max']) {
            return array(
                'reaccion'           => $fila['tipo'],
                'porcentaje'         => $fila['pct'],
                'diferencia'         => $diferencia,
                'mantenida_aplicada' => $mantenida_aplicada,
                'ta_ajustada'        => $ta_ajustada,
                'ref_ajustado'       => $ref_ajustado,
            );
        }
    }
    return array('reaccion' => 'Sin Reacción', 'porcentaje' => 0, 'diferencia' => $diferencia);
}

// Efectos por tipo de daño (Guía 226-234). Interpretación propia donde la
// Guía no es explícita sobre cómo combinar varios tipos a la vez — ver
// 200_DesignPlan_Belico.md.
function belico_calc_efectos_por_tipo($tipo, $danio_tipo, $vida_max)
{
    $efectos = array();
    switch ($tipo) {
        case 'cortante':
            $n = (int) floor($danio_tipo / 300);
            if ($n > 0) { $efectos[] = "Hemorragia nivel {$n}"; }
            if ($vida_max > 0 && $danio_tipo >= $vida_max * 0.5) { $efectos[] = 'Amputación'; }
            break;
        case 'contundente':
            $n = (int) floor($danio_tipo / 200);
            if ($n > 0) { $efectos[] = "Entumecimiento {$n} turno(s)"; }
            if ($vida_max > 0 && $danio_tipo >= $vida_max * 0.4) { $efectos[] = 'Fractura'; }
            break;
        case 'fuego':
            $n = (int) floor($danio_tipo / 250);
            if ($n > 0) { $efectos[] = "Quemadura nivel {$n}"; }
            break;
        case 'agua':
            $n = (int) floor($danio_tipo / 250);
            if ($n > 0) { $efectos[] = "Daño Interno nivel {$n}"; }
            break;
        case 'viento':
            $n = (int) floor($danio_tipo / 250);
            if ($n > 0) { $efectos[] = "Fatiga nivel {$n}"; }
            break;
        case 'hielo':
            $n = (int) floor($danio_tipo / 250);
            if ($n > 0) { $efectos[] = "Congelación nivel {$n}"; }
            break;
        case 'rayo':
            $n = (int) floor($danio_tipo / 250);
            if ($n > 0) { $efectos[] = "Electroshock nivel {$n}"; }
            break;
        case 'sonico':
            if ($vida_max > 0 && $danio_tipo >= $vida_max * 0.4) {
                $efectos[] = 'Vértigo';
            } else {
                $n = (int) floor($danio_tipo / 200);
                if ($n > 0) { $efectos[] = "Desorientación {$n} turno(s)"; }
            }
            break;
    }
    return $efectos;
}

// Mitigación + Regla de los Efectos + efectos por tipo (Guía 219-240, 317-319).
function belico_calc_danio_recibido($danio_entrante, $tipos, $danio_verdadero, $mitigacion_pasiva, $mitigacion_espiritual, $vida_max)
{
    $reglas          = belico_reglas();
    $danio_verdadero = max(0, min($danio_verdadero, $danio_entrante));
    $danio_mitigable = $danio_entrante - $danio_verdadero;

    $tipos     = array_values(array_filter($tipos));
    $num_tipos = max(1, count($tipos));
    $por_tipo  = $danio_mitigable / $num_tipos;

    $mitigado_total = 0;
    $efectos        = array();
    foreach ($tipos as $tipo) {
        $mit = ($tipo === 'espiritual') ? $mitigacion_espiritual : $mitigacion_pasiva;
        if ($tipo === 'perforante') {
            // Ignora 25 de Defensa Pasiva por cada 100 de daño causado — Guía 228.
            $mit = max(0, $mitigacion_pasiva - 25 * floor($por_tipo / 100));
        }
        $mitigado        = min($por_tipo, $mit);
        $mitigado_total += $mitigado;
        $danio_tipo_final = $por_tipo - $mitigado;
        $efectos = array_merge($efectos, belico_calc_efectos_por_tipo($tipo, $danio_tipo_final, $vida_max));
    }

    $danio_final    = max(0, $danio_mitigable - $mitigado_total) + $danio_verdadero;
    $umbral_efectos = (int) ceil($danio_entrante / $reglas['regla_efectos_divisor']);
    $aplica_efectos = $danio_final >= $umbral_efectos;

    return array(
        'danio_final'    => (int) round($danio_final),
        'umbral_efectos' => $umbral_efectos,
        'aplica_efectos' => $aplica_efectos,
        'efectos'        => $aplica_efectos ? $efectos : array(),
    );
}

function belico_calc_caida($altura, $fue, $res)
{
    $reglas = belico_reglas();
    $limite = (int) round(2 + ($fue + $res) / 4);
    $exceso = max(0, $altura - $limite);
    return array('limite' => $limite, 'exceso' => $exceso, 'danio' => $exceso * $reglas['caida_por_metro']);
}

function belico_calc_asfixia($res)
{
    $reglas = belico_reglas();
    return array('turnos' => (int) floor($res / $reglas['asfixia_cada']));
}

function belico_calc_lanzamiento($fue, $es_arma_no_arrojadiza, $danio_base_arma)
{
    return array(
        'danio'   => $fue + ($es_arma_no_arrojadiza ? $danio_base_arma : 0),
        'alcance' => (int) floor($fue / 2),
    );
}

function belico_calc_agarre($fue_agresor, $fue_defensor, $turnos_forcejeo)
{
    $fue_defensor_ajustada = $fue_defensor + 10 * $turnos_forcejeo;
    return array('libre' => $fue_defensor_ajustada > $fue_agresor, 'fue_defensor_ajustada' => $fue_defensor_ajustada);
}

// Guía 412-432: mismo esquema para Vida, Energía y Haki.
function belico_calc_estado_recurso($actual, $vol, $res)
{
    if ($actual > 0) { return 'consciente'; }
    $neg      = abs($actual);
    $umbral1  = $vol + $res;
    $umbral2  = $umbral1 * 3;
    if ($neg > $umbral2) { return 'muerte'; }
    if ($neg > $umbral1) { return 'inconsciente'; }
    return 'derrotado_consciente';
}

// ───────────────────────────────────────────────────────────────────────────
// Lectura de entradas
// ───────────────────────────────────────────────────────────────────────────

function belico_leer_modificadores($mybb)
{
    $mods = array();
    foreach (array('niv', 'fue', 'agi', 'pun', 'des', 'res', 'ref', 'vol') as $c) {
        $mods[strtoupper($c)] = (int) $mybb->get_input('mod_' . $c, MyBB::INPUT_INT);
    }
    return $mods;
}

function belico_aplicar_modificadores($atributos, $mods)
{
    $out = array();
    foreach ($atributos as $k => $v) {
        $out[$k] = $v + (isset($mods[$k]) ? $mods[$k] : 0);
    }
    return $out;
}

function belico_es_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']);
}

// ───────────────────────────────────────────────────────────────────────────
// Render (belico_render_*) — arman HTML, nunca calculan (200_DesignPlan §4)
// ───────────────────────────────────────────────────────────────────────────

function belico_render_aviso($msg, $tipo = 'err')
{
    $clase = $tipo === 'ok' ? 'ok' : 'err';
    return '<p class="aviso ' . $clase . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
}

function belico_render_campos_ocultos($tid, $fid)
{
    return '<input type="hidden" name="tid" value="' . (int) $tid . '"><input type="hidden" name="fid" value="' . (int) $fid . '">';
}

function belico_render_cabecera($mybb, $fid, $tid, $ficha, $hilo)
{
    $nombre_esc = htmlspecialchars($ficha['nombre'], ENT_QUOTES, 'UTF-8');
    $link_ficha = $mybb->settings['bburl'] . '/op/personaje.php?uid=' . (int) $fid;

    if ($ficha['origen'] === 'hilo') {
        $titulo_hilo = ($hilo && $hilo['puede_ver']) ? htmlspecialchars($hilo['subject'], ENT_QUOTES, 'UTF-8') : ('hilo #' . (int) $tid);
        $link_post   = $mybb->settings['bburl'] . '/showthread.php?pid=' . (int) $ficha['pid'] . '#pid' . (int) $ficha['pid'];
        $origen_html = '<span class="belico-origen">Estadísticas fijadas en <a href="' . $link_post . '">' . $titulo_hilo . '</a></span>';
    } else {
        $origen_html = '<span class="belico-origen">Estadísticas actuales de la ficha</span>';
        if ((int) $tid > 0) {
            $origen_html .= belico_render_aviso('Este personaje no tiene estadísticas fijadas en este hilo; se muestran las actuales.', 'err');
        }
    }

    $html  = '<header class="belico-cabecera">';
    $html .= '<div class="belico-cabecera-texto"><p class="belico-sobretitulo">Herramienta de combate</p><h1>Calculadora bélica</h1>';
    $html .= '<p class="belico-personaje">' . $nombre_esc . '</p>';
    $html .= $origen_html;
    $html .= '</div><a class="opg-chip" href="' . $link_ficha . '">Ver ficha</a></header>';
    $html .= '<form method="get" action="belico.php" class="belico-contexto af-row">';
    $html .= '<div class="af-field"><label for="belico-fid">UID del personaje</label><input type="number" id="belico-fid" name="fid" min="1" value="' . (int) $fid . '"></div>';
    $html .= '<div class="af-field"><label for="belico-tid">ID del hilo</label><input type="number" id="belico-tid" name="tid" min="0" value="' . (int) $tid . '"></div>';
    $html .= '<button type="submit" class="btn-op btn-op--primario btn-op--sm">Cargar personaje</button>';
    $html .= '</form>';
    return $html;
}

function belico_render_atributos($atributos_base, $mod, $tid, $fid)
{
    $claves = array('NIV', 'FUE', 'AGI', 'PUN', 'DES', 'RES', 'REF', 'VOL');
    $html  = '<section class="belico-modulo" id="belico-atributos-seccion"><h2>Atributos</h2>';
    $html .= '<form id="belico-atributos" class="af-row belico-atributos-grid">';
    $html .= belico_render_campos_ocultos($tid, $fid);
    foreach ($claves as $k) {
        $base  = (int) $atributos_base[$k];
        $m     = isset($mod[$k]) ? (int) $mod[$k] : 0;
        $final = $base + $m;
        $html .= '<div class="af-field"><label for="belico-mod-' . strtolower($k) . '">' . $k . ' <span class="belico-base">Base ' . $base . '</span></label>';
        $html .= '<input type="number" id="belico-mod-' . strtolower($k) . '" name="mod_' . strtolower($k) . '" value="' . $m . '" data-belico-base="' . $base . '" aria-label="Modificador ' . $k . '">';
        $html .= '<small>Ajuste + modificador · Final: <strong data-belico-final>' . $final . '</strong></small>';
        $html .= '</div>';
    }
    $html .= '</form></section>';
    return $html;
}

// Atributo de un bloque de resultado htmx: se recalcula al cargar la página
// y cada vez que cambia algo en #belico-atributos.
function belico_attrs_htmx($accion, $incluir_extra = '', $disparador_extra = '')
{
    $incluir = '#belico-atributos' . ($incluir_extra !== '' ? ',' . $incluir_extra : '');
    $disparador = 'load, change from:#belico-atributos, input from:#belico-atributos delay:300ms';
    if ($disparador_extra !== '') { $disparador .= ', ' . $disparador_extra; }
    return 'hx-get="belico.php" hx-vals=\'{"accion":"' . $accion . '"}\' hx-include="' . $incluir . '" '
         . 'hx-trigger="' . $disparador . '" hx-swap="innerHTML"';
}

function belico_render_estadisticas($est)
{
    $filas = array(
        'Movimiento' => array($est['movimiento'] . ' m', 'AGI / 2 + RES / 4, redondeado hacia abajo.'),
        'Salto' => array($est['salto'] . ' m', '1 + (FUE + AGI) / 5, redondeado al entero más cercano.'),
        'Trepar' => array($est['trepar'] . ' m/s', '1 + (AGI + DES) / 10, redondeado al entero más cercano.'),
        'Nadar' => array($est['nadar'] . ' m', 'Movimiento / 2, redondeado al entero más cercano.'),
        'Distancia de lanzamiento' => array($est['dist_lanzamiento'] . ' m', 'FUE / 2, redondeado hacia abajo.'),
        'Límite de caída' => array($est['limite_caida'] . ' m', '2 + (FUE + RES) / 4, redondeado al entero más cercano.'),
        'Defensa Pasiva' => array($est['defensa_pasiva'], 'RES. Mitiga daño no espiritual.'),
        'Fortaleza Espiritual' => array($est['fortaleza_espiritual'], 'VOL. Mitiga daño espiritual.'),
        'Regen. Energía' => array($est['regen_energia'], 'NIV por turno.'),
        'Regen. Haki' => array($est['regen_haki'], 'NIV / 2, redondeado hacia arriba, por turno.'),
        'Concentración' => array($est['concentracion'], 'VOL + RES.'),
        'Umbral de Dolor' => array($est['umbral_dolor'], '5 × (VOL + RES).'),
    );
    $html = '<dl class="belico-estadisticas">';
    foreach ($filas as $label => $dato) {
        $valor = $dato[0];
        $formula = $dato[1];
        $accesible = $label . '. Fórmula: ' . $formula;
        $html .= '<div class="belico-estadistica" tabindex="0" aria-label="' . htmlspecialchars($accesible, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<dt>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '<span class="belico-estadistica-info" aria-hidden="true">?</span></dt>';
        $html .= '<dd>' . htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') . '</dd>';
        $html .= '<span class="belico-estadistica-ayuda" role="tooltip">Fórmula: ' . htmlspecialchars($formula, ENT_QUOTES, 'UTF-8') . '</span></div>';
    }
    $html .= '</dl>';
    return $html;
}

function belico_render_bloque_estadisticas($atributos, $tid, $fid)
{
    $html  = '<section class="belico-modulo" id="belico-estadisticas-seccion"><h2>Estadísticas bélicas</h2>';
    $html .= '<div id="res-estadisticas" aria-live="polite" ' . belico_attrs_htmx('estadisticas') . '>';
    $html .= belico_render_estadisticas(belico_calc_estadisticas($atributos));
    $html .= '</div></section>';
    return $html;
}

function belico_render_bloque_estado($atributos, $recursos, $tid, $fid)
{
    $html  = '<section class="belico-modulo" id="belico-recursos"><h2>Recursos</h2>';
    $html .= '<form id="belico-estado-form" class="af-row belico-recursos-grid">';
    $html .= belico_render_campos_ocultos($tid, $fid);
    $html .= '<div class="af-field"><label for="belico-vida">Vida actual <span class="belico-base">/ ' . (int) $recursos['vida_max'] . '</span></label><input type="number" id="belico-vida" name="vida_actual" value="' . (int) $recursos['vida_max'] . '"></div>';
    $html .= '<div class="af-field"><label for="belico-energia">Energía actual <span class="belico-base">/ ' . (int) $recursos['energia_max'] . '</span></label><input type="number" id="belico-energia" name="energia_actual" value="' . (int) $recursos['energia_max'] . '"></div>';
    $html .= '<div class="af-field"><label for="belico-haki">Haki actual <span class="belico-base">/ ' . (int) $recursos['haki_max'] . '</span></label><input type="number" id="belico-haki" name="haki_actual" value="' . (int) $recursos['haki_max'] . '"></div>';
    $html .= '</form>';
    $html .= '<div id="res-estado" class="belico-estado-resultado" aria-live="polite" ' . belico_attrs_htmx('estado', '#belico-estado-form') . '>';
    $html .= belico_render_resultado_estado_html($atributos, $recursos['vida_max'], $recursos['energia_max'], $recursos['haki_max']);
    $html .= '</div></section>';
    return $html;
}

function belico_estado_texto($estado)
{
    $textos = array(
        'consciente'           => 'Consciente',
        'derrotado_consciente' => 'Derrotado pero Consciente',
        'inconsciente'         => 'Inconsciente',
        'muerte'               => 'Muerte',
    );
    return isset($textos[$estado]) ? $textos[$estado] : $estado;
}

function belico_render_resultado_estado_html($atributos, $vida, $energia, $haki)
{
    $vol = $atributos['VOL']; $res = $atributos['RES'];
    $html  = '<p><span>Vida</span><strong>' . belico_estado_texto(belico_calc_estado_recurso($vida, $vol, $res)) . '</strong></p>';
    $html .= '<p><span>Energía</span><strong>' . belico_estado_texto(belico_calc_estado_recurso($energia, $vol, $res)) . '</strong></p>';
    $html .= '<p><span>Haki</span><strong>' . belico_estado_texto(belico_calc_estado_recurso($haki, $vol, $res)) . '</strong></p>';
    return $html;
}

function belico_render_resultado_estado($mybb, $atributos)
{
    $vida    = (int) $mybb->get_input('vida_actual', MyBB::INPUT_INT);
    $energia = (int) $mybb->get_input('energia_actual', MyBB::INPUT_INT);
    $haki    = (int) $mybb->get_input('haki_actual', MyBB::INPUT_INT);
    return belico_render_resultado_estado_html($atributos, $vida, $energia, $haki);
}

// ── Ataque ───────────────────────────────────────────────────────────────

function belico_leer_seleccion_armas($mybb, $armas)
{
    return opg_belico_seleccionar_armas($armas, $mybb->get_input('contexto_arma', MyBB::INPUT_STRING), array(
        $mybb->get_input('arma_uno', MyBB::INPUT_STRING),
        $mybb->get_input('arma_dos', MyBB::INPUT_STRING),
        $mybb->get_input('arma_tres', MyBB::INPUT_STRING),
    ));
}

function belico_render_selector_arma($armas, $campo, $etiqueta, $placeholder, $seleccionado)
{
    $html  = '<div class="af-field"><label for="belico-' . $campo . '">' . $etiqueta . '</label><select id="belico-' . $campo . '" name="' . $campo . '">';
    $html .= '<option value="">' . $placeholder . '</option>';
    foreach ($armas as $objeto_id => $arma) {
        $sel = ($objeto_id === $seleccionado) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($objeto_id, ENT_QUOTES, 'UTF-8') . '" data-tipos="' . implode(',', belico_tipos_arma($arma)) . '"' . $sel . '>' . htmlspecialchars(opg_belico_nombre_arma($arma), ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html . '</select></div>';
}

function belico_render_bloque_ataque($mybb, $armas, $tecnicas, $tid, $fid, $atributos = null, $recursos = null)
{
    $sel = belico_leer_seleccion_armas($mybb, $armas);
    // Se muestra la configuración pedida (no la final) para que elegir "Una arma" no vuelva a "Sin arma" antes de escoger el arma.
    $config = $sel['solicitada'];
    $cantidad = $sel['cantidad'];

    $opciones_config = array('sin_arma' => 'Sin arma', 'una_mano' => 'Una arma', 'dos_manos' => 'Una arma con ambas manos', 'dos_armas' => 'Dos armas', 'tres_armas' => 'Tres armas');

    $html  = '<section class="belico-modulo belico-modulo--principal" id="belico-ataque"><h2>Ataque</h2>';
    $html .= belico_render_vista_toggle();
    $html .= '<form id="belico-ataque-form" class="af-row">';
    $html .= belico_render_campos_ocultos($tid, $fid);
    $html .= '<div class="af-field"><label for="belico-contexto-arma">Configuración</label><select id="belico-contexto-arma" name="contexto_arma" hx-get="belico.php" hx-vals=\'{"accion":"ataque_form"}\' hx-include="#belico-ataque-form,#belico-atributos" hx-target="#belico-ataque" hx-trigger="change consume" hx-swap="outerHTML">';
    foreach ($opciones_config as $valor => $texto) {
        $html .= '<option value="' . $valor . '"' . ($valor === $config ? ' selected' : '') . '>' . $texto . '</option>';
    }
    $html .= '</select></div>';
    if ($cantidad >= 1) { $html .= belico_render_selector_arma($armas, 'arma_uno', 'Arma 1', 'Selecciona un arma', trim((string) $mybb->get_input('arma_uno', MyBB::INPUT_STRING))); }
    if ($cantidad >= 2) { $html .= belico_render_selector_arma($armas, 'arma_dos', 'Arma 2', 'Selecciona una segunda arma', trim((string) $mybb->get_input('arma_dos', MyBB::INPUT_STRING))); }
    if ($cantidad >= 3) { $html .= belico_render_selector_arma($armas, 'arma_tres', 'Arma 3', 'Selecciona una tercera arma', trim((string) $mybb->get_input('arma_tres', MyBB::INPUT_STRING))); }
    $html .= '<div class="af-field"><label for="belico-distancia">Distancia al objetivo (m)</label><input type="number" id="belico-distancia" name="distancia" min="0" step="1" value="' . max(0, (int) $mybb->get_input('distancia', MyBB::INPUT_INT)) . '"><small>-5 de TA por cada 20 m</small></div>';
    $html .= belico_render_campo_tecnica($tecnicas, trim((string) $mybb->get_input('tecnica', MyBB::INPUT_STRING)));
    $html .= '</form>';
    $html .= '<div id="res-ataque" aria-live="polite" ' . belico_attrs_htmx('ataque', '#belico-ataque-form', 'change from:#belico-ataque-form, input from:#belico-distancia delay:300ms') . '>';
    $html .= $atributos ? belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos, $recursos) : '';
    $html .= '</div>';
    $html .= '</section>';
    return $html;
}

function belico_render_vista_toggle()
{
    // "Con cálculo" = valores calculados con tu personaje (vista por defecto); "Sin cálculo" = la fórmula tal cual está escrita.
    return '<div class="belico-vista" role="group" aria-label="Vista de cálculos">'
        . '<button type="button" data-belico-vista="codigos" aria-pressed="false">Sin cálculo</button>'
        . '<button type="button" data-belico-vista="calculos" aria-pressed="true">Con cálculo</button></div>';
}

// Los tipos de arma viven en la librería (los usa también el cálculo del bono a dos manos).
function belico_tipos_arma($arma) { return opg_belico_tipos_arma($arma); }
function belico_etiquetas_tipos_arma() { return opg_belico_etiquetas_tipos_arma(); }

// Tipos de arma que exige o menciona una pasiva en su texto ("Siempre que use un arma de tensión...").
function belico_condicion_arma_pasiva($tecnica)
{
    $patrones = array(
        'tension'     => '/arma[s]?\s+(?:de\s+)?tensi[oó]n/iu',
        'asalto'      => '/arma[s]?\s+(?:de\s+)?asalto/iu',
        'filo'        => '/arma[s]?\s+(?:de\s+)?filo/iu',
        'asta'        => '/arma[s]?\s+(?:de\s+)?asta\b/iu',
        'fuego'       => '/arma[s]?\s+(?:de\s+)?fuego/iu',
        'contundente' => '/arma[s]?\s+contundente[s]?/iu',
        'corporal'    => '/arma[s]?\s+corporal(?:es)?/iu',
        'flexible'    => '/arma[s]?\s+flexible[s]?/iu',
        'exotica'     => '/arma[s]?\s+ex[oó]tica[s]?/iu',
        'arrojadiza'  => '/arma[s]?\s+arrojadiza[s]?/iu',
        'defensiva'   => '/arma[s]?\s+defensiva[s]?|escudo/iu',
        'amuleto'     => '/amuleto[s]?/iu',
        'musical'     => '/instrumento[s]?|musical(?:es)?/iu',
    );
    $texto = (string) $tecnica['descripcion'] . ' ' . (string) $tecnica['efectos'];
    $tipos = array();
    foreach ($patrones as $tipo => $patron) {
        if (preg_match($patron, $texto)) { $tipos[] = $tipo; }
    }

    // Enumeraciones: "Armas de Tensión, de Fuego, de Asalto o Arrojadizas" menciona cuatro tipos, no solo el primero.
    $nombres = 'tensi[oó]n|asalto|filo|asta|fuego|contundentes?|corporales?|flexibles?|ex[oó]ticas?|arrojadizas?|defensivas?';
    $palabras = array(
        'tension' => '/tensi[oó]n/iu', 'asalto' => '/asalto/iu', 'filo' => '/\bfilo\b/iu', 'asta' => '/\basta\b/iu', 'fuego' => '/fuego/iu',
        'contundente' => '/contundent/iu', 'corporal' => '/corporal/iu', 'flexible' => '/flexible/iu', 'exotica' => '/ex[oó]tic/iu',
        'arrojadiza' => '/arrojadiz/iu', 'defensiva' => '/defensiv/iu',
    );
    if (preg_match_all('/arma[s]?\s+(?:de\s+)?(?:' . $nombres . ')(?:\s*(?:,|\by\b|\bo\b)\s*(?:(?:de|armas?)\s+)*(?:' . $nombres . '))*/iu', $texto, $listas)) {
        foreach ($listas[0] as $lista) {
            foreach ($palabras as $tipo => $patron) {
                if (preg_match($patron, $lista) && !in_array($tipo, $tipos, true)) { $tipos[] = $tipo; }
            }
        }
    }
    return $tipos;
}

// Selector de técnica: buscador + filtro "solo con cálculo" (los maneja belico_js_ataque) y opciones
// agrupadas por tipo, ordenadas por tier. Sin JS sigue funcionando como un select normal.
function belico_render_campo_tecnica($tecnicas, $seleccionada)
{
    $orden  = array('Ofensivas', 'Defensivas', 'Ambientales', 'Utilidad', 'Otras');
    $grupos = array_fill_keys($orden, array());
    foreach ($tecnicas as $tecnica) {
        if ($tecnica['grupo'] !== null) { $grupos[$tecnica['grupo']][] = $tecnica; }
    }

    $html  = '<div class="af-field belico-campo-tecnica"><label for="belico-tecnica">Técnica</label>';
    $html .= '<div class="belico-tecnica-filtros"><input type="search" id="belico-tecnica-buscar" placeholder="Buscar por nombre o código..." autocomplete="off" aria-label="Buscar técnica">';
    $html .= '<label class="belico-tecnica-solo"><input type="checkbox" id="belico-tecnica-solo-calculo"> Solo con cálculo</label></div>';
    $html .= '<select id="belico-tecnica" name="tecnica"><option value="">Sin técnica</option>';
    foreach ($orden as $grupo) {
        if (empty($grupos[$grupo])) { continue; }
        usort($grupos[$grupo], function ($a, $b) {
            $d = belico_orden_tier($a) - belico_orden_tier($b);
            return $d !== 0 ? $d : strcmp((string) $a['tid'], (string) $b['tid']);
        });
        $html .= '<optgroup label="' . $grupo . '">';
        foreach ($grupos[$grupo] as $tecnica) {
            $sel = $seleccionada === $tecnica['tid'] ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($tecnica['tid'], ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars(belico_etiqueta_tecnica($tecnica), ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select><small>◆ = tiene valores que se calculan con tu personaje.</small></div>';
    return $html;
}

// Texto de los efectos: solo la fórmula se reemplaza por su valor ("+[Nivel] de Daño" pasa a "+29 de Daño"),
// con el desglose al pasar el cursor. Se incluyen las dos versiones y el interruptor "Sin cálculo / Con cálculo" muestra una.
function belico_render_efectos_inline($efectos, $sustituciones)
{
    $texto  = (string) $efectos;
    $marcas = array();
    foreach ($sustituciones as $i => $sustitucion) {
        if (strpos($texto, $sustitucion['expresion']) === false) { continue; }
        $marca = "\x01" . $i . "\x01";
        $texto = str_replace($sustitucion['expresion'], $marca, $texto);
        $marcas[$marca] = '<span class="belico-codigo">' . htmlspecialchars($sustitucion['expresion'], ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="belico-calculo-inline"><strong class="belico-calculo belico-calculo--chip" tabindex="0" data-opg-calculo="' . htmlspecialchars(implode("\n", $sustitucion['pasos']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($sustitucion['numero'], ENT_QUOTES, 'UTF-8') . '</strong></span>';
    }
    return strtr(nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')), $marcas);
}

// Casilla de resultado con desglose al pasar el cursor (mismo recurso que tecnicas_show.php).
function belico_render_resultado_calculo($titulo, $valor, $detalle, $pasos, $clave = '')
{
    $desglose = htmlspecialchars(implode("\n", $pasos), ENT_QUOTES, 'UTF-8');
    return '<div' . ($clave !== '' ? ' data-belico-tile="' . $clave . '"' : '') . '><span>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<strong class="belico-calculo" tabindex="0" data-belico-valor="' . (int) $valor . '" data-opg-calculo="' . $desglose . '">' . (int) $valor . '</strong>'
        . '<small>' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . '</small></div>';
}

// Tarjeta por arma elegida: tipo, tier, alcance, daño y bloqueo base, efecto y cuánto aporta a la configuración.
function belico_render_tarjetas_armas($sel, $atributos)
{
    if (empty($sel['armas'])) {
        return '<p class="belico-sin-arma"><small>Sin arma: el golpe y el bloqueo usan tu Fuerza y la TA tu Agilidad.</small></p>';
    }
    $etiquetas = opg_belico_etiquetas_tipos_arma();
    $cantidad = count($sel['armas']);
    $multiplicador = $sel['configuracion'] === 'dos_manos' ? 1.5 : ($cantidad === 2 ? 0.75 : ($cantidad > 2 ? 0.6 : 1));
    $html = '<div class="belico-armas-elegidas">';
    foreach ($sel['armas'] as $arma) {
        $dano = opg_belico_calcular_arma($arma, $atributos, false);
        $bloqueo = opg_belico_calcular_bloqueo_arma($arma, $atributos, false);
        $tipos = array();
        foreach (opg_belico_tipos_arma($arma) as $tipo) { $tipos[] = $etiquetas[$tipo]; }
        $meta = array();
        if ($tipos) { $meta[] = ucfirst(implode(', ', $tipos)); }
        if ((int) $arma['tier'] > 0) { $meta[] = 'Tier ' . (int) $arma['tier']; }
        if (trim((string) $arma['alcance']) !== '') { $meta[] = trim((string) $arma['alcance']); }
        $html .= '<article class="belico-arma-tarjeta"><h4>' . htmlspecialchars(opg_belico_nombre_arma($arma), ENT_QUOTES, 'UTF-8') . '</h4>'
            . '<small>' . htmlspecialchars(implode(' · ', $meta), ENT_QUOTES, 'UTF-8') . '</small>'
            . '<p>Daño base <strong>' . (int) $dano['valor'] . '</strong> · Bloqueo base <strong>' . (int) $bloqueo['valor'] . '</strong></p>';
        if ($multiplicador !== 1) {
            $sin_bono = $sel['configuracion'] === 'dos_manos' && array_intersect(opg_belico_tipos_arma($arma), opg_belico_tipos_sin_bono_dos_manos());
            if ($sel['configuracion'] === 'dos_manos') {
                $html .= '<p><small>' . ($sin_bono ? 'Sin bono de dos manos.' : 'A dos manos (+50%): ' . (int) ceil($dano['valor'] * 1.5) . ' de daño y ' . (int) ceil($bloqueo['valor'] * 1.5) . ' de bloqueo.') . '</small></p>';
            } else {
                $html .= '<p><small>Aporta al ' . (int) round($multiplicador * 100) . '% en esta configuración: ' . (int) ceil($dano['valor'] * $multiplicador) . ' de daño y ' . (int) ceil($bloqueo['valor'] * $multiplicador) . ' de bloqueo.</small></p>';
            }
        }
        if (trim((string) $arma['efecto']) !== '') {
            $html .= '<p><small>' . htmlspecialchars(trim((string) $arma['efecto']), ENT_QUOTES, 'UTF-8') . '</small></p>';
        }
        $html .= '</article>';
    }
    return $html . '</div>';
}

// "9 de 12" / "supera tu máximo": coste de la técnica frente a los recursos máximos del personaje.
function belico_texto_coste($nombre, $valor, $maximo)
{
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '0') { return array('', false); }
    $texto = $nombre . ' ' . $valor;
    $excede = false;
    if ($maximo !== null && is_numeric($valor)) {
        $texto .= ' (máx. ' . (int) $maximo . ')';
        $excede = (int) $valor > (int) $maximo;
    }
    return array($texto, $excede);
}

function belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos, $recursos = null)
{
    $sel       = belico_leer_seleccion_armas($mybb, $armas);
    $distancia = max(0, (int) $mybb->get_input('distancia', MyBB::INPUT_INT));
    $golpe     = opg_belico_calcular_golpe_basico($sel['armas'], $atributos, $sel['configuracion']);
    $bloqueo   = opg_belico_calcular_bloqueo_basico($sel['armas'], $atributos, $sel['configuracion']);
    $ta        = opg_belico_calcular_tasa_acierto($sel['armas'], $atributos, $distancia);

    $tid_tec = trim((string) $mybb->get_input('tecnica', MyBB::INPUT_STRING));
    $tec = ($tid_tec !== '' && isset($tecnicas[$tid_tec])) ? $tecnicas[$tid_tec] : null;

    // Avisos: configuración, requisitos del arma y requisito de arma de la técnica.
    $avisos = opg_belico_avisos_armas($sel, $atributos);
    if ($tec) {
        $requeridos = opg_belico_tipos_requeridos_tecnica($tec);
        $tipos_elegidos = array();
        foreach ($sel['armas'] as $arma) { $tipos_elegidos = array_merge($tipos_elegidos, opg_belico_tipos_arma($arma)); }
        $tipos_elegidos = opg_belico_tipos_con_comodin($tipos_elegidos);
        $etiquetas = opg_belico_etiquetas_tipos_arma();
        $estilo_tec = trim((string) $tec['rama']) !== '' && mb_strtolower((string) $tec['rama'], 'UTF-8') !== 'única' ? $tec['rama'] : $tec['estilo'];
        if ($requeridos === array() && $sel['armas']) {
            $avisos[] = array('nivel' => 'aviso', 'texto' => 'Esta técnica (' . $estilo_tec . ') no se usa con armas.');
        } elseif ($requeridos && $sel['armas'] && !array_intersect($requeridos, $tipos_elegidos)) {
            $nombres_req = array();
            foreach ($requeridos as $tipo) { $nombres_req[] = $etiquetas[$tipo]; }
            $avisos[] = array('nivel' => 'aviso', 'texto' => 'Esta técnica (' . $estilo_tec . ') requiere armas de ' . implode(', ', $nombres_req) . ' y tu arma no es de ese tipo.');
        } elseif ($requeridos && !$sel['armas'] && !in_array('corporal', $requeridos, true)) {
            $nombres_req = array();
            foreach ($requeridos as $tipo) { $nombres_req[] = $etiquetas[$tipo]; }
            $avisos[] = array('nivel' => 'info', 'texto' => 'Esta técnica (' . $estilo_tec . ') requiere armas de ' . implode(', ', $nombres_req) . ': elige una en la configuración.');
        }
    }

    $html = '';
    if ($avisos) {
        $html .= '<ul class="belico-avisos-ataque">';
        foreach ($avisos as $aviso) {
            $html .= '<li class="belico-aviso-' . $aviso['nivel'] . '"><span aria-hidden="true">' . ($aviso['nivel'] === 'aviso' ? '⚠' : 'ℹ') . '</span> ' . htmlspecialchars($aviso['texto'], ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html .= '</ul>';
    }
    $html .= belico_render_tarjetas_armas($sel, $atributos);

    $html .= '<div class="belico-resultados belico-resultados--ataque">';
    $html .= belico_render_resultado_calculo('Golpe básico', $golpe['valor'], 'daño ' . opg_belico_normalizar_tipo($golpe['tipo']) . ' · ' . $golpe['configuracion'],
        array_merge($golpe['pasos'], array('Total: ' . $golpe['valor'] . ' de daño ' . opg_belico_normalizar_tipo($golpe['tipo']))), 'golpe');
    $html .= belico_render_resultado_calculo('Bloqueo básico', $bloqueo['valor'], 'mitigación · ' . $bloqueo['configuracion'],
        array_merge($bloqueo['pasos'], array('Total: ' . $bloqueo['valor'] . ' de mitigación')), 'bloqueo');
    $html .= belico_render_resultado_calculo('Tasa de acierto', $ta['valor'], 'según tus armas' . ($distancia ? ' · a ' . $distancia . ' m' : ''), array_merge($ta['pasos'], array('Total: ' . $ta['valor'])), 'ta');
    $html .= '</div>';

    // Resumen de la ofensiva: daño por tipo (técnica o golpe básico), TA y coste. Las pasivas marcadas se suman en el navegador.
    $resultados = $tec ? opg_belico_calcular_efectos_contextuales($tec['efectos'], $atributos, $golpe, $bloqueo) : array();
    $danios = array();
    foreach ($resultados as $resultado) {
        if (!empty($resultado['es_danio'])) {
            $tipo = (string) $resultado['tipo'];
            $danios[$tipo] = (isset($danios[$tipo]) ? $danios[$tipo] : 0) + (int) $resultado['valor'];
        }
    }
    $origen_danio = 'de la técnica';
    if (empty($danios)) {
        $danios[opg_belico_normalizar_tipo($golpe['tipo'])] = (int) $golpe['valor'];
        $origen_danio = $tec ? 'golpe básico (la técnica no inflige daño calculable)' : 'golpe básico';
    }
    $base_comun = 0;
    $base_verdadero = 0;
    $detalle_tipos = array();
    foreach ($danios as $tipo => $valor) {
        if (stripos($tipo, 'verdadero') !== false) { $base_verdadero += $valor; } else { $base_comun += $valor; }
        $detalle_tipos[] = $valor . ' ' . ($tipo !== '' ? $tipo : 'daño');
    }
    $html .= '<div class="belico-resumen" id="belico-resumen" data-base-dano="' . $base_comun . '" data-base-verdadero="' . $base_verdadero . '" data-base-ta="' . (int) $ta['valor'] . '">';
    $html .= '<h3>Resumen de la ofensiva</h3>';
    $html .= '<div class="belico-resumen-fila"><span>Daño</span><strong>' . $base_comun . '</strong><small>' . htmlspecialchars($origen_danio . ': ' . implode(' + ', $detalle_tipos), ENT_QUOTES, 'UTF-8') . '</small><span class="belico-con-pasivas" data-belico-resumen="dano"></span></div>';
    $html .= '<div class="belico-resumen-fila" data-belico-fila="verdadero"' . ($base_verdadero ? '' : ' hidden') . '><span>Daño verdadero</span><strong>' . $base_verdadero . '</strong><span class="belico-con-pasivas" data-belico-resumen="verdadero"></span></div>';
    $html .= '<div class="belico-resumen-fila"><span>Tasa de acierto</span><strong>' . (int) $ta['valor'] . '</strong><small>' . ($distancia ? 'a ' . $distancia . ' m' : 'sin penalización de distancia') . '</small><span class="belico-con-pasivas" data-belico-resumen="ta"></span></div>';
    if ($tec) {
        $partes = array();
        $excede = array();
        $max_e = $recursos && isset($recursos['energia_max']) ? $recursos['energia_max'] : null;
        $max_h = $recursos && isset($recursos['haki_max']) ? $recursos['haki_max'] : null;
        foreach (array(array('Energía', $tec['energia'], $max_e), array('Energía por turno', $tec['energia_turno'], $max_e), array('Haki', $tec['haki'], $max_h), array('Haki por turno', $tec['haki_turno'], $max_h)) as $coste) {
            list($texto, $sobrepasa) = belico_texto_coste($coste[0], $coste[1], $coste[2]);
            if ($texto !== '') { $partes[] = $texto . ($sobrepasa ? ' ⚠ supera tu máximo' : ''); }
        }
        if (trim((string) $tec['enfriamiento']) !== '' && trim((string) $tec['enfriamiento']) !== '0') { $partes[] = 'Enfriamiento ' . trim((string) $tec['enfriamiento']) . ' turnos'; }
        $html .= '<div class="belico-resumen-fila"><span>Coste</span><strong class="belico-resumen-coste">' . htmlspecialchars($partes ? implode(' · ', $partes) : 'Sin coste', ENT_QUOTES, 'UTF-8') . '</strong></div>';
    }
    $bloques_pasos = array('Golpe básico' => $golpe['pasos'], 'Bloqueo básico' => $bloqueo['pasos'], 'Tasa de acierto' => $ta['pasos']);
    foreach ($resultados as $resultado) {
        $info = opg_belico_detalle_resultado($resultado, $golpe, $bloqueo);
        $bloques_pasos['Técnica: ' . $info['etiqueta']] = $info['pasos'];
    }
    $html .= '<details class="belico-pasos"><summary>Ver cálculo paso a paso</summary>';
    foreach ($bloques_pasos as $titulo => $pasos) {
        $html .= '<h4>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h4><ol>';
        foreach ($pasos as $paso) { $html .= '<li>' . htmlspecialchars($paso, ENT_QUOTES, 'UTF-8') . '</li>'; }
        $html .= '</ol>';
    }
    $html .= '</details></div>';

    if ($tec) {
        $tid_esc = htmlspecialchars($tid_tec, ENT_QUOTES, 'UTF-8');
        $sust_efectos = opg_belico_sustituciones_texto($tec['efectos'], $atributos, $golpe, $bloqueo);
        $sust_desc    = opg_belico_sustituciones_texto($tec['descripcion'], $atributos, $golpe, $bloqueo);
        $html .= '<div class="belico-resultado-texto belico-efectos"><p><strong>[' . $tid_esc . '] ' . htmlspecialchars($tec['nombre'], ENT_QUOTES, 'UTF-8') . '</strong></p>';
        $meta = belico_meta_tecnica($tec);
        if ($meta !== '') { $html .= '<p><small>' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</small></p>'; }
        $html .= '<p>' . belico_render_efectos_inline($tec['efectos'], $sust_efectos) . '</p>';
        if (trim((string) $tec['descripcion']) !== '') {
            $html .= '<details class="belico-pasiva-desc"><summary>Descripción</summary><p>' . belico_render_efectos_inline($tec['descripcion'], $sust_desc) . '</p></details>';
        }
        if (empty($sust_efectos) && empty($sust_desc)) {
            $html .= '<p><small>Esta técnica no tiene valores que calcular con tu personaje.</small></p>';
        }
        if ($tec['grupo'] !== null) {
            $html .= '<p><button type="button" class="opg-chip" data-belico-usar-en-turno="' . $tid_esc . '">Añadir al turno</button></p>';
        }
        $html .= '</div>';
    }
    return $html;
}

function belico_render_resultado_ataque($mybb, $armas, $tecnicas, $atributos, $recursos = null)
{
    return belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos, $recursos);
}

// ── Reacción ─────────────────────────────────────────────────────────────

function belico_render_bloque_reaccion($atributos, $tid, $fid)
{
    $html  = '<section class="belico-modulo" id="belico-reaccion"><h2>Reacción</h2>';
    $html .= '<form id="belico-reaccion-form" class="af-row" hx-get="belico.php" hx-vals=\'{"accion":"reaccion"}\' hx-include="#belico-atributos" hx-target="#res-reaccion" hx-trigger="input changed delay:300ms, change" hx-swap="innerHTML">';
    $html .= belico_render_campos_ocultos($tid, $fid);
    $html .= '<div class="af-field"><label for="belico-ref">Reflejos (defensor)</label><input type="number" id="belico-ref" name="ref" value="' . (int) $atributos['REF'] . '"></div>';
    $html .= '<div class="af-field"><label for="belico-ta-rival">Tasa de Acierto (atacante)</label><input type="number" id="belico-ta-rival" name="ta" value="0"></div>';
    $html .= '<div class="af-field"><label for="belico-metros">Distancia (m)</label><input type="number" id="belico-metros" name="metros" value="0"></div>';
    $html .= '<div class="af-field"><label for="belico-sorpresa">Sorpresa</label><select id="belico-sorpresa" name="sorpresa"><option value="no">No</option><option value="detectado">Detectada</option><option value="sin_detectar">Sin detectar</option></select></div>';
    $html .= '<div class="af-field"><label for="belico-bloques">Bloques sin cortar</label><input type="number" id="belico-bloques" name="bloques" value="0"></div>';
    $html .= '<div class="af-field"><label for="belico-mantenida">Reflejos de mantenida (este turno)</label><input type="number" id="belico-mantenida" name="mantenida" value="0"></div>';
    $html .= '</form>';
    $html .= '<p class="belico-nota-pasivas" id="belico-nota-reflejos" hidden></p>';
    $html .= '<div id="res-reaccion" class="belico-resultado-texto" aria-live="polite">';
    $html .= belico_render_resultado_reaccion_html((int) $atributos['REF'], 0, 0, 'no', 0, 0);
    $html .= '</div></section>';
    return $html;
}

function belico_render_resultado_reaccion_html($ref, $ta, $metros, $sorpresa, $bloques, $mantenida)
{
    $r = belico_calc_reaccion($ref, $ta, $metros, $sorpresa, $bloques, $mantenida);
    $html = '<p class="belico-resultado-destacado"><span>Reacción</span><strong>' . htmlspecialchars($r['reaccion'], ENT_QUOTES, 'UTF-8') . '</strong><em>' . (int) $r['porcentaje'] . '%</em></p>';
    if (isset($r['diferencia']) && $r['diferencia'] !== null) {
        $html .= '<p>Diferencia Reflejos − TA: ' . (int) $r['diferencia'] . (!empty($r['mantenida_aplicada']) ? ' (con la mantenida aplicada)' : '') . '</p>';
    }
    if (!empty($r['nota'])) {
        $html .= '<p>' . htmlspecialchars($r['nota'], ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if (strpos($r['reaccion'], 'Esquiva') === 0) {
        $html .= '<p><small>Para esquivar hace falta poder desplazarse más que el área del ataque.</small></p>';
    }
    return $html;
}

function belico_render_resultado_reaccion($mybb)
{
    $ref       = (int) $mybb->get_input('ref', MyBB::INPUT_INT);
    $ta        = (int) $mybb->get_input('ta', MyBB::INPUT_INT);
    $metros    = (int) $mybb->get_input('metros', MyBB::INPUT_INT);
    $sorpresa  = $mybb->get_input('sorpresa', MyBB::INPUT_STRING);
    $bloques   = (int) $mybb->get_input('bloques', MyBB::INPUT_INT);
    $mantenida = (int) $mybb->get_input('mantenida', MyBB::INPUT_INT);
    if (!in_array($sorpresa, array('no', 'detectado', 'sin_detectar'), true)) { $sorpresa = 'no'; }
    return belico_render_resultado_reaccion_html($ref, $ta, $metros, $sorpresa, $bloques, $mantenida);
}

// ── Daño recibido ────────────────────────────────────────────────────────

function belico_render_bloque_danio($atributos, $recursos, $tid, $fid)
{
    $html  = '<section class="belico-modulo" id="belico-danio"><h2>Daño recibido</h2>';
    $html .= '<form id="belico-danio-form" class="af-row" hx-get="belico.php" hx-vals=\'{"accion":"danio"}\' hx-include="#belico-atributos" hx-target="#res-danio" hx-trigger="input changed delay:300ms, change" hx-swap="innerHTML">';
    $html .= belico_render_campos_ocultos($tid, $fid);
    $html .= '<div class="af-field"><label for="belico-danio-entrante">Daño entrante</label><input type="number" id="belico-danio-entrante" name="danio" value="0"></div>';
    $html .= '<div class="af-field"><label for="belico-tipo-1">Tipo 1</label><select id="belico-tipo-1" name="tipo1">' . belico_opciones_tipo_danio() . '</select></div>';
    $html .= '<div class="af-field"><label for="belico-tipo-2">Tipo 2 (opcional)</label><select id="belico-tipo-2" name="tipo2">' . belico_opciones_tipo_danio(true) . '</select></div>';
    $html .= '<div class="af-field"><label for="belico-danio-verdadero">Parte Daño Verdadero</label><input type="number" id="belico-danio-verdadero" name="verdadero" value="0"></div>';
    $html .= '</form>';
    $html .= '<div id="res-danio" class="belico-resultado-texto" aria-live="polite">';
    $html .= belico_render_resultado_danio_html(0, array(), 0, (int) $atributos['RES'], (int) $atributos['VOL'], (int) $recursos['vida_max']);
    $html .= '</div></section>';
    return $html;
}

function belico_opciones_tipo_danio($con_vacio = false)
{
    $tipos = array('cortante' => 'Cortante', 'contundente' => 'Contundente', 'perforante' => 'Perforante',
        'fuego' => 'Fuego', 'agua' => 'Agua', 'viento' => 'Viento', 'hielo' => 'Hielo', 'rayo' => 'Rayo',
        'sonico' => 'Sónico', 'espiritual' => 'Espiritual');
    $html = $con_vacio ? '<option value="">Ninguno</option>' : '';
    foreach ($tipos as $v => $t) {
        $html .= '<option value="' . $v . '">' . $t . '</option>';
    }
    return $html;
}

function belico_render_resultado_danio_html($danio, $tipos, $verdadero, $res, $vol, $vida_max)
{
    $r = belico_calc_danio_recibido($danio, $tipos, $verdadero, $res, $vol, $vida_max);
    $html  = '<p class="belico-resultado-destacado"><span>Daño final</span><strong>' . $r['danio_final'] . '</strong></p>';
    $html .= '<p>Regla de los Efectos: hace falta recibir ' . $r['umbral_efectos'] . ' o más — ' . ($r['aplica_efectos'] ? 'se aplican los efectos.' : 'no se aplican efectos.') . '</p>';
    if (!empty($r['efectos'])) {
        $html .= '<ul>';
        foreach ($r['efectos'] as $e) { $html .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>'; }
        $html .= '</ul>';
    }
    if ($r['danio_final'] > $vol + $res) {
        $html .= '<p><strong>Se interrumpe Concentración/Canalización/Mantenida</strong> (supera ' . ($vol + $res) . ').</p>';
    }
    if ($r['danio_final'] > 5 * ($vol + $res)) {
        $html .= '<p><strong>Se supera el Umbral de Dolor</strong> (' . (5 * ($vol + $res)) . ') — se interrumpe la acción del turno.</p>';
    }
    return $html;
}

function belico_render_resultado_danio($mybb, $recursos, $atributos)
{
    $danio     = (int) $mybb->get_input('danio', MyBB::INPUT_INT);
    $tipo1     = $mybb->get_input('tipo1', MyBB::INPUT_STRING);
    $tipo2     = $mybb->get_input('tipo2', MyBB::INPUT_STRING);
    $verdadero = (int) $mybb->get_input('verdadero', MyBB::INPUT_INT);
    $tipos     = array_filter(array($tipo1, $tipo2));
    return belico_render_resultado_danio_html($danio, $tipos, $verdadero, (int) $atributos['RES'], (int) $atributos['VOL'], (int) $recursos['vida_max']);
}

// ── Utilidades ───────────────────────────────────────────────────────────

function belico_render_bloque_utilidades($atributos, $tid, $fid)
{
    $oculto = belico_render_campos_ocultos($tid, $fid);
    $html = '<section class="belico-modulo" id="belico-utilidades"><h2>Utilidades</h2><div class="belico-utilidades-grid">';

    $html .= '<div class="belico-utilidad"><h3>Caída</h3><form class="af-row" hx-get="belico.php" hx-vals=\'{"accion":"caida"}\' hx-target="#res-caida" hx-trigger="input changed delay:300ms" hx-swap="innerHTML">' . $oculto;
    $html .= '<div class="af-field"><label>Caída: altura (m)</label><input type="number" name="altura" value="0"></div></form>';
    $html .= '<div id="res-caida">' . belico_render_resultado_caida_html(0, (int) $atributos['FUE'], (int) $atributos['RES']) . '</div></div>';

    $html .= '<div class="belico-utilidad"><h3>Asfixia</h3><p>Resiste <strong>' . (int) floor($atributos['RES'] / belico_reglas()['asfixia_cada']) . '</strong> turno(s).</p></div>';

    $html .= '<div class="belico-utilidad"><h3>Lanzamiento</h3><form class="af-row" hx-get="belico.php" hx-vals=\'{"accion":"lanzamiento"}\' hx-target="#res-lanzamiento" hx-trigger="input changed delay:300ms" hx-swap="innerHTML">' . $oculto;
    $html .= '<div class="af-field"><label>Lanzamiento: ¿Es un arma no arrojadiza?</label><select name="es_arma"><option value="0">No</option><option value="1">Sí</option></select></div>';
    $html .= '<div class="af-field"><label>Daño base del arma (si aplica)</label><input type="number" name="danio_base" value="0"></div></form>';
    $html .= '<div id="res-lanzamiento">' . belico_render_resultado_lanzamiento_html(0, (int) $atributos['FUE']) . '</div></div>';

    $html .= '<div class="belico-utilidad"><h3>Agarre</h3><form class="af-row" hx-get="belico.php" hx-vals=\'{"accion":"agarre"}\' hx-target="#res-agarre" hx-trigger="input changed delay:300ms" hx-swap="innerHTML">' . $oculto;
    $html .= '<div class="af-field"><label>Agarre: Fuerza del agresor</label><input type="number" name="fue_agresor" value="0"></div>';
    $html .= '<div class="af-field"><label>Turnos de forcejeo</label><input type="number" name="turnos" value="0"></div></form>';
    $html .= '<div id="res-agarre">' . belico_render_resultado_agarre_html(0, (int) $atributos['FUE'], 0) . '</div></div>';

    $html .= '</div></section>';
    return $html;
}

// Las "Senda" son los bonos fijos de atributo de cada disciplina: ya están sumados a la ficha, no se marcan por turno.
function belico_es_senda($tecnica)
{
    return mb_stripos((string) $tecnica['nombre'], 'senda', 0, 'UTF-8') !== false;
}

function belico_es_pasiva($tecnica)
{
    return stripos((string) $tecnica['clase'] . ' ' . (string) $tecnica['tipo'], 'pasiva') !== false && !belico_es_senda($tecnica);
}

function belico_origen_pasiva($tecnica)
{
    $estilo = mb_strtolower(trim((string) $tecnica['estilo']), 'UTF-8');
    if ($estilo === 'racial') { return 'Raciales'; }
    if ($estilo === 'especial') { return 'Especiales'; }
    $disciplinas = array('arquero', 'artillero', 'artista', 'artista marcial', 'asesino', 'combatiente', 'elemental', 'escudero', 'espadachín', 'guerrero', 'pícaro', 'tecnicista', 'tirador');
    return in_array($estilo, $disciplinas, true) ? 'Disciplinas' : 'Estilos y únicas';
}

// Condiciones que el texto menciona (solo informativas: la página no las comprueba, ver la descripción).
function belico_condiciones_texto($tecnica)
{
    $t = (string) $tecnica['descripcion'] . ' ' . (string) $tecnica['efectos'];
    $etiquetas = array();
    if (preg_match('/(umbral|\d+\s*%)[^.]{0,60}(vitalidad|vida)|(vitalidad|vida)[^.]{0,60}(umbral|\d+\s*%)/iu', $t)) { $etiquetas[] = 'Vitalidad'; }
    if (preg_match('/m[aá]s de \d+\s*metros|desde[^.]{0,20}\d+\s*metros|a distancia/iu', $t)) { $etiquetas[] = 'Distancia'; }
    if (preg_match('/\b(salto|desplazamiento|carga|embestida|impulso)\b/iu', $t)) { $etiquetas[] = 'Movimiento previo'; }
    if (preg_match('/si el (adversario|enemigo|objetivo)|quemadura|derribo|sangrado|aturd|asfixia|congel|veneno|hemorragia/iu', $t)) { $etiquetas[] = 'Estado del objetivo'; }
    if (preg_match('/resto del combate/iu', $t)) { $etiquetas[] = 'Dura el resto del combate'; }
    if (preg_match('/por turno|cada turno/iu', $t)) { $etiquetas[] = 'Por turno'; }
    $hay_max = preg_match('/m[aá]ximo de (\d+)/iu', $t, $max);
    if ($hay_max || preg_match('/acumul/iu', $t)) {
        $etiquetas[] = $hay_max ? 'Acumulable (máx. ' . $max[1] . ')' : 'Acumulable';
    }
    if (preg_match('/dado de/iu', $t)) { $etiquetas[] = 'Dado'; }
    return $etiquetas;
}

// Lista de pasivas agrupada por origen, con el efecto calculado con tus atributos y la descripción plegable.
// $marcadas: tid => true (se conservan al volver a dibujar la lista cuando cambian los atributos).
function belico_render_lista_pasivas($tecnicas, $atributos, $marcadas)
{
    $orden  = array('Disciplinas', 'Estilos y únicas', 'Raciales', 'Especiales');
    $grupos = array_fill_keys($orden, array());
    foreach ($tecnicas as $tecnica) {
        if (belico_es_pasiva($tecnica)) { $grupos[belico_origen_pasiva($tecnica)][] = $tecnica; }
    }
    $total = 0;
    foreach ($grupos as $lista) { $total += count($lista); }
    if ($total === 0) {
        return '<div class="opg-vacio"><p>No hay técnicas pasivas aprendidas para seleccionar.</p></div>';
    }

    $vacio_golpe = array('valor' => 0, 'tipo' => '', 'pasos' => array(), 'configuracion' => '');
    $html = '';
    foreach ($orden as $origen) {
        if (empty($grupos[$origen])) { continue; }
        usort($grupos[$origen], function ($a, $b) {
            $c = strcmp((string) $a['estilo'], (string) $b['estilo']);
            if ($c !== 0) { return $c; }
            $d = belico_orden_tier($a) - belico_orden_tier($b);
            return $d !== 0 ? $d : strcmp((string) $a['tid'], (string) $b['tid']);
        });
        $con_marcadas = false;
        $items = '';
        foreach ($grupos[$origen] as $tecnica) {
            $tid = (string) $tecnica['tid'];
            $tid_esc = htmlspecialchars($tid, ENT_QUOTES, 'UTF-8');
            $nombre = htmlspecialchars((string) $tecnica['nombre'], ENT_QUOTES, 'UTF-8');
            $marcada = isset($marcadas[$tid]);
            if ($marcada) { $con_marcadas = true; }

            $sust_efectos = opg_belico_sustituciones_texto((string) $tecnica['efectos'], $atributos, $vacio_golpe, $vacio_golpe);
            $sust_desc    = opg_belico_sustituciones_texto((string) $tecnica['descripcion'], $atributos, $vacio_golpe, $vacio_golpe);
            // Para copiar al post: el efecto con los valores puestos (solo si hay algo calculado).
            $texto_calculado = $sust_efectos ? trim(preg_replace('/\s+/', ' ', opg_belico_aplicar_valores((string) $tecnica['efectos'], $sust_efectos))) : '';

            $meta = array();
            if (trim((string) $tecnica['estilo']) !== '') { $meta[] = trim((string) $tecnica['estilo']); }
            if (trim((string) $tecnica['tier']) !== '') { $meta[] = 'Tier ' . trim((string) $tecnica['tier']); }
            if (preg_match('/^(?:RAC|TRI)\d+$/', $tid)) { $meta[] = 'bono de atributos ya aplicado'; }
            $buscar = htmlspecialchars(mb_strtolower($tecnica['nombre'] . ' ' . $tid . ' ' . $tecnica['estilo'], 'UTF-8'), ENT_QUOTES, 'UTF-8');

            $armas_req = belico_condicion_arma_pasiva($tecnica);
            $bonos = opg_belico_extraer_bonos((string) $tecnica['efectos'], $atributos);
            $json_bonos = htmlspecialchars(json_encode($bonos, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');
            $items .= '<div class="opg-card belico-pasiva-item" data-buscar="' . $buscar . '" data-bonos="' . $json_bonos . '"' . (($sust_efectos || $bonos) ? ' data-valor="1"' : '') . ($armas_req ? ' data-armas="' . implode(',', $armas_req) . '"' : '') . '>'
                . '<label class="belico-pasiva-marca"><input type="checkbox" name="pasivas[]" value="' . $tid_esc . '" data-belico-pasiva data-belico-pasiva-nombre="' . $nombre . '" data-belico-pasiva-origen="' . htmlspecialchars($origen, ENT_QUOTES, 'UTF-8') . '" data-belico-pasiva-efecto="' . htmlspecialchars($texto_calculado, ENT_QUOTES, 'UTF-8') . '"' . ($marcada ? ' checked' : '') . '>'
                . '<span class="belico-pasiva-control" aria-hidden="true"></span><span><strong>' . $nombre . '</strong><small>' . htmlspecialchars(implode(' · ', $meta), ENT_QUOTES, 'UTF-8') . '</small></span></label>';
            if (trim((string) $tecnica['efectos']) !== '') {
                $items .= '<p class="belico-pasiva-efecto">' . belico_render_efectos_inline($tecnica['efectos'], $sust_efectos) . '</p>';
            }
            if ($armas_req) {
                $etiquetas_arma = belico_etiquetas_tipos_arma();
                $nombres_arma = array();
                foreach ($armas_req as $tipo_arma) { $nombres_arma[] = $etiquetas_arma[$tipo_arma]; }
                $items .= '<p class="belico-pasiva-arma">Arma: ' . htmlspecialchars(implode(', ', $nombres_arma), ENT_QUOTES, 'UTF-8') . ' <span class="belico-arma-estado"></span></p>';
            }
            $condiciones = belico_condiciones_texto($tecnica);
            if ($condiciones) {
                $items .= '<p class="belico-pasiva-condiciones" title="Detectado por el texto de la pasiva; revisa la descripción para confirmarlo.">';
                foreach ($condiciones as $condicion) { $items .= '<span class="belico-etiqueta">' . htmlspecialchars($condicion, ENT_QUOTES, 'UTF-8') . '</span>'; }
                $items .= '</p>';
            }
            if (trim((string) $tecnica['descripcion']) !== '') {
                $items .= '<details class="belico-pasiva-desc"><summary>Descripción</summary><p>' . belico_render_efectos_inline((string) $tecnica['descripcion'], $sust_desc) . '</p></details>';
            }
            $items .= '</div>';
        }
        // Raciales son muchas: van cerradas salvo que tengan alguna marcada.
        $abierto = ($origen !== 'Raciales' || $con_marcadas) ? ' open' : '';
        $html .= '<details class="belico-pasiva-grupo"' . $abierto . '><summary>' . htmlspecialchars($origen, ENT_QUOTES, 'UTF-8') . ' (' . count($grupos[$origen]) . ')</summary>'
            . '<div class="belico-pasivas-lista">' . $items . '</div></details>';
    }
    return $html;
}

function belico_render_bloque_pasivas($tecnicas, $atributos, $armas)
{
    $html = '<section class="belico-modulo belico-pasivas" id="belico-pasivas"><h2>Pasivas del turno</h2>';
    $html .= '<p class="belico-turno-intro">Marca únicamente las pasivas que estén aplicando en este turno y copia el bloque para incluirlo en tu post. Los valores se calculan con tus atributos. Las "Senda" no aparecen: sus bonos ya están sumados a tu ficha.</p>';
    $html .= '<div class="belico-pasivas-controles">' . belico_render_vista_toggle()
        . '<div class="belico-vista" role="group" aria-label="Densidad de la lista"><button type="button" data-belico-densidad="tarjetas" aria-pressed="true">Tarjetas</button><button type="button" data-belico-densidad="compacta" aria-pressed="false">Compacta</button></div></div>';

    // Tipo de arma que llevas este turno (independiente de Ataque y del Constructor). Solo salen los tipos
    // de las armas de tu inventario; con eso se sabe qué pasivas con condición de arma aplican.
    $por_tipo = array();
    foreach ($armas as $arma) {
        foreach (belico_tipos_arma($arma) as $tipo_arma) { $por_tipo[$tipo_arma][] = opg_belico_nombre_arma($arma); }
    }
    $etiquetas_arma = belico_etiquetas_tipos_arma();
    $html .= '<div class="belico-pasivas-armas" role="group" aria-label="Tipo de arma que llevas este turno"><span class="belico-pasivas-armas-titulo">Arma en mano:</span>';
    $hay_tipos = false;
    foreach ($etiquetas_arma as $tipo_arma => $etiqueta_arma) {
        if (empty($por_tipo[$tipo_arma])) { continue; }
        $hay_tipos = true;
        $lista = implode(', ', array_unique($por_tipo[$tipo_arma]));
        $html .= '<label class="belico-arma-chip" title="' . htmlspecialchars($lista, ENT_QUOTES, 'UTF-8') . '"><input type="checkbox" value="' . $tipo_arma . '" data-belico-pasivas-tipo><span>' . htmlspecialchars(ucfirst($etiqueta_arma), ENT_QUOTES, 'UTF-8') . ' (' . count($por_tipo[$tipo_arma]) . ')</span></label>';
    }
    if (!$hay_tipos) {
        $html .= '<span class="belico-pasivas-armas-vacio">no tienes armas con un tipo reconocido en el inventario</span>';
    }
    $html .= '<button type="button" class="opg-chip" id="belico-marcar-aplicables" title="Marca las pasivas con condición de arma que aplican a los tipos elegidos">Marcar las que aplican</button>'
        . '<button type="button" class="opg-chip" id="belico-deshacer-pasivas" hidden>Deshacer</button>';
    $html .= '</div>';

    // Conjuntos guardados en el navegador (selección con nombre que se aplica de un clic).
    $html .= '<div class="belico-pasivas-conjuntos"><span class="belico-pasivas-armas-titulo">Conjuntos:</span>'
        . '<select id="belico-conjuntos" aria-label="Conjuntos de pasivas guardados"><option value="">(ninguno guardado)</option></select>'
        . '<button type="button" class="opg-chip" id="belico-conjunto-aplicar">Aplicar</button>'
        . '<button type="button" class="opg-chip" id="belico-conjunto-guardar">Guardar selección como...</button>'
        . '<button type="button" class="opg-chip" id="belico-conjunto-borrar">Borrar</button></div>';

    $html .= '<div class="belico-pasivas-buscador"><input type="search" id="belico-pasivas-buscar" placeholder="Buscar pasiva por nombre, código o estilo..." autocomplete="off" aria-label="Buscar pasiva">'
        . '<label class="belico-tecnica-solo"><input type="checkbox" id="belico-pasivas-solo-aplican"> Solo las que aplican con mi arma</label>'
        . '<label class="belico-tecnica-solo"><input type="checkbox" id="belico-pasivas-solo-marcadas"> Solo marcadas</label>'
        . '<label class="belico-tecnica-solo"><input type="checkbox" id="belico-pasivas-solo-valor"> Solo con valor calculado</label></div>';

    // Barra fija con lo que llevas marcado, los bonos sumados y las acciones.
    $html .= '<div class="belico-pasivas-barra" id="belico-pasivas-barra">'
        . '<div class="belico-pasivas-barra-fila"><span id="belico-pasivas-resumen" aria-live="polite">0 pasivas seleccionadas</span>'
        . '<span class="belico-pasivas-acciones"><button type="button" class="opg-chip" id="belico-limpiar-pasivas">Turno nuevo</button>'
        . '<button type="button" class="btn-op btn-op--secundario btn-op--sm" id="belico-copiar-pasivas">Copiar pasivas</button>'
        . '<span id="belico-pasivas-aviso" class="belico-pasivas-aviso" aria-live="polite"></span></span></div>'
        . '<div id="belico-pasivas-chips" class="belico-pasivas-chips"></div>'
        . '<div id="belico-pasivas-bonos" class="belico-pasivas-bonos"></div></div>';

    $html .= '<div id="belico-pasivas-contenido" hx-get="belico.php" hx-vals=\'{"accion":"pasivas"}\' hx-include="#belico-atributos,[data-belico-pasiva]:checked" hx-trigger="change from:#belico-atributos, input from:#belico-atributos delay:300ms" hx-swap="innerHTML">';
    $html .= belico_render_lista_pasivas($tecnicas, $atributos, array());
    $html .= '</div>';
    $html .= '<details class="belico-pasivas-previa"><summary>Texto que se copia al post</summary><pre id="belico-pasivas-previa">Selecciona al menos una pasiva.</pre></details>';
    $html .= '<textarea id="belico-pasivas-textarea" hidden aria-label="Pasivas para copiar al post"></textarea>';
    $html .= '</section>';
    return $html;
}

function belico_render_resultado_caida_html($altura, $fue, $res)
{
    $r = belico_calc_caida($altura, $fue, $res);
    return '<p>Límite de caída: ' . $r['limite'] . ' m. Exceso: ' . $r['exceso'] . ' m. Daño: <strong>' . $r['danio'] . ' Contundente</strong>.</p>';
}
function belico_render_resultado_caida($mybb, $atributos)
{
    return belico_render_resultado_caida_html((int) $mybb->get_input('altura', MyBB::INPUT_INT), (int) $atributos['FUE'], (int) $atributos['RES']);
}

function belico_render_resultado_asfixia($mybb, $atributos)
{
    $r = belico_calc_asfixia((int) $atributos['RES']);
    return '<p>Resiste <strong>' . $r['turnos'] . '</strong> turno(s) de Asfixia.</p>';
}

function belico_render_resultado_lanzamiento_html($es_arma, $fue, $danio_base = 0)
{
    $r = belico_calc_lanzamiento($fue, (bool) $es_arma, $danio_base);
    return '<p>Daño: <strong>' . $r['danio'] . '</strong>. Alcance: <strong>' . $r['alcance'] . ' m</strong>. TA = Puntería.</p>';
}
function belico_render_resultado_lanzamiento($mybb, $atributos)
{
    return belico_render_resultado_lanzamiento_html(
        (int) $mybb->get_input('es_arma', MyBB::INPUT_INT),
        (int) $atributos['FUE'],
        (int) $mybb->get_input('danio_base', MyBB::INPUT_INT)
    );
}

function belico_render_resultado_agarre_html($fue_agresor, $fue_defensor, $turnos)
{
    $r = belico_calc_agarre($fue_agresor, $fue_defensor, $turnos);
    return '<p>Fuerza del defensor ajustada: <strong>' . $r['fue_defensor_ajustada'] . '</strong> — ' . ($r['libre'] ? 'se libera.' : 'sigue agarrado.') . '</p>';
}
function belico_render_resultado_agarre($mybb, $atributos)
{
    return belico_render_resultado_agarre_html(
        (int) $mybb->get_input('fue_agresor', MyBB::INPUT_INT),
        (int) $atributos['FUE'],
        (int) $mybb->get_input('turnos', MyBB::INPUT_INT)
    );
}

// ── Turno: cálculo en el servidor ────────────────────────────────────────

// Normaliza el turno que llega del navegador: solo ids conocidos, números acotados y límites de tamaño.
function belico_normalizar_turno($crudo, $tecnicas)
{
    $t = is_array($crudo) ? $crudo : array();
    $entero = function ($valor, $maximo) { return max(0, min($maximo, (int) $valor)); };
    $tids = function ($lista, $filtro = null) use ($tecnicas) {
        $salida = array();
        if (is_array($lista)) {
            foreach ($lista as $tid) {
                if (!is_scalar($tid)) { continue; }
                $tid = (string) $tid;
                if (isset($tecnicas[$tid]) && ($filtro === null || $filtro($tecnicas[$tid])) && !in_array($tid, $salida, true)) { $salida[] = $tid; }
            }
        }
        return $salida;
    };
    $acciones = opg_belico_acciones_turno();
    $texto = function ($valor) { return is_scalar($valor) ? (string) $valor : ''; };
    $recursos = isset($t['recursos']) && is_array($t['recursos']) ? $t['recursos'] : array();
    $equipo = isset($t['equipo']) && is_array($t['equipo']) ? $t['equipo'] : array();
    // El nombre va dentro de [spoiler=...]: sin corchetes ni signos que rompan la etiqueta.
    $nombre = mb_substr(trim(str_replace(array('[', ']', '='), array('(', ')', '-'), $texto(isset($t['nombre']) ? $t['nombre'] : ''))), 0, 60, 'UTF-8');
    $norm = array(
        'nombre' => $nombre !== '' ? $nombre : 'Turno',
        'equipo' => array(
            'configuracion' => $texto(isset($equipo['configuracion']) ? $equipo['configuracion'] : 'sin_arma'),
            'armas' => array_slice(array_map('strval', array_filter(isset($equipo['armas']) && is_array($equipo['armas']) ? $equipo['armas'] : array(), 'is_scalar')), 0, 3),
        ),
        'pasivas' => $tids(isset($t['pasivas']) ? $t['pasivas'] : array(), 'belico_es_pasiva'),
        'recursos' => array(
            'vida' => $entero(isset($recursos['vida']) ? $recursos['vida'] : 0, 999999),
            'energia' => $entero(isset($recursos['energia']) ? $recursos['energia'] : 0, 999999),
            'haki' => $entero(isset($recursos['haki']) ? $recursos['haki'] : 0, 999999),
        ),
        'adicionales_extra' => $entero(isset($t['adicionales_extra']) ? $t['adicionales_extra'] : 0, 20),
        'enfriamientos' => array(),
        'continuan' => $tids(isset($t['continuan']) ? $t['continuan'] : array()),
        'instantaneas' => $tids(isset($t['instantaneas']) ? $t['instantaneas'] : array()),
        'bloques' => array(),
    );
    if (isset($t['enfriamientos']) && is_array($t['enfriamientos'])) {
        foreach ($t['enfriamientos'] as $tid => $turnos) {
            $tid = (string) $tid;
            if (isset($tecnicas[$tid]) && is_scalar($turnos) && $entero($turnos, 99) > 0) { $norm['enfriamientos'][$tid] = $entero($turnos, 99); }
        }
    }
    $lista_bloques = isset($t['bloques']) && is_array($t['bloques']) ? array_slice($t['bloques'], 0, 30) : array();
    foreach ($lista_bloques as $b) {
        if (!is_array($b)) { continue; }
        $pasos = array();
        $lista_pasos = isset($b['pasos']) && is_array($b['pasos']) ? array_slice($b['pasos'], 0, 12) : array();
        foreach ($lista_pasos as $p) {
            if (!is_array($p)) { continue; }
            if (isset($p['accion']) && is_scalar($p['accion'])) {
                if (isset($acciones[(string) $p['accion']])) { $pasos[] = array('t' => 'accion', 'accion' => (string) $p['accion']); }
            } elseif (isset($p['tid']) && is_scalar($p['tid']) && isset($tecnicas[(string) $p['tid']]) && !belico_es_pasiva($tecnicas[(string) $p['tid']])) {
                $pasos[] = array('t' => 'tecnica', 'tid' => (string) $p['tid'], 'aplicar_bonos' => !isset($p['aplicar_bonos']) || (bool) $p['aplicar_bonos']);
            }
        }
                $detalle = str_replace(array('[', ']'), array('(', ')'), mb_substr(trim($texto(isset($b['detalle']) ? $b['detalle'] : '')), 0, 300, 'UTF-8'));
        $reaccion = isset($b['reaccion']) && is_array($b['reaccion']) ? $b['reaccion'] : array();
        $sorpresa = $texto(isset($reaccion['sorpresa']) ? $reaccion['sorpresa'] : 'no');
        $norm['bloques'][] = array(
            'anterior' => !empty($b['anterior']),
            'interrumpido' => !empty($b['interrumpido']),
            'reaccion' => array(
                'ta' => $entero(isset($reaccion['ta']) ? $reaccion['ta'] : 0, 9999),
                'distancia' => $entero(isset($reaccion['distancia']) ? $reaccion['distancia'] : 0, 9999),
                'sorpresa' => in_array($sorpresa, array('no', 'detectado', 'sin_detectar'), true) ? $sorpresa : 'no',
                'ref_extra' => $entero(isset($reaccion['ref_extra']) ? $reaccion['ref_extra'] : 0, 9999),
                'corto' => !empty($reaccion['corto']),
            ),
            'mov_previo' => $entero(isset($b['mov_previo']) ? $b['mov_previo'] : 0, 9999),
            'mov_posterior' => $entero(isset($b['mov_posterior']) ? $b['mov_posterior'] : 0, 9999),
            'pasos' => $pasos,
            'pasivas_mas' => $tids(isset($b['pasivas_mas']) ? $b['pasivas_mas'] : array(), 'belico_es_pasiva'),
            'pasivas_menos' => $tids(isset($b['pasivas_menos']) ? $b['pasivas_menos'] : array()),
            'detalle' => $detalle,
        );
    }
    return $norm;
}

function belico_texto_coste_turno($coste)
{
    $partes = array();
    if ($coste['energia'] > 0) { $partes[] = '-' . $coste['energia'] . ' de energía'; }
    if ($coste['haki'] > 0) { $partes[] = '-' . $coste['haki'] . ' de haki'; }
    return implode(', ', $partes);
}

/**
 * Calcula el turno completo: costes y recursos bloque a bloque, daño, TA, límites de la guía (como avisos) y el
 * texto para el post. No guarda nada. Devuelve la estructura que el navegador pinta.
 */
function belico_calcular_turno($estado, $tecnicas, $armas, $atributos, $maximos = array())
{
    $max_energia = isset($maximos['energia_max']) ? (int) $maximos['energia_max'] : null;
    $max_haki = isset($maximos['haki_max']) ? (int) $maximos['haki_max'] : null;
    $esc = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
    $etiquetas_acciones = opg_belico_acciones_turno();
    $sel = opg_belico_seleccionar_armas($armas, $estado['equipo']['configuracion'], $estado['equipo']['armas']);
    $golpe = opg_belico_calcular_golpe_basico($sel['armas'], $atributos, $sel['configuracion']);
    $bloqueo = opg_belico_calcular_bloqueo_basico($sel['armas'], $atributos, $sel['configuracion']);
    $ta = opg_belico_calcular_tasa_acierto($sel['armas'], $atributos, 0);
    $tipo_golpe = opg_belico_normalizar_tipo($golpe['tipo']);
    $tipos_equipo = array();
    foreach ($sel['armas'] as $arma) { $tipos_equipo = array_merge($tipos_equipo, opg_belico_tipos_arma($arma)); }
    $tipos_equipo = opg_belico_tipos_con_comodin($tipos_equipo);
    $estadisticas = belico_calc_estadisticas($atributos);
    $max_movimiento = (int) $estadisticas['movimiento'];
    $limite_adicionales = 3 + $estado['adicionales_extra'];

    $avisos = array();
    foreach (opg_belico_avisos_armas($sel, $atributos) as $aviso) { $avisos[] = $aviso['texto']; }

    // Bonos numéricos de una lista de pasivas, descartando las que no aplican con el arma del equipo.
    $bonos_de = function ($tids) use ($tecnicas, $tipos_equipo, $atributos) {
        $suma = array();
        foreach ($tids as $tid) {
            $requeridos = belico_condicion_arma_pasiva($tecnicas[$tid]);
            if ($requeridos && !array_intersect($requeridos, $tipos_equipo)) { continue; }
            foreach (opg_belico_extraer_bonos((string) $tecnicas[$tid]['efectos'], $atributos) as $bono) {
                $clave = $bono['k'] . ($bono['p'] ? '%' : '');
                $suma[$clave] = (isset($suma[$clave]) ? $suma[$clave] : 0) + $bono['v'];
            }
        }
        return $suma;
    };
    $nombre_tec = function ($tid) use ($tecnicas, $esc) { return '[' . $esc($tid) . '] ' . $esc($tecnicas[$tid]['nombre']); };

    $energia = $estado['recursos']['energia'];
    $haki = $estado['recursos']['haki'];
    $usadas = array();
    $mantenidas_siguiente = array();
    $enfriamientos = array();
    $falta_recursos = false;

    // Inicio del turno: enfriamientos que arrastras, mantenidas que continúan (coste por turno) e instantáneas de inicio.
    $lineas_inicio = array();
    foreach ($estado['enfriamientos'] as $tid => $turnos) {
        $lineas_inicio[] = 'En enfriamiento: ' . $nombre_tec($tid) . ' (' . $turnos . ' turno' . ($turnos === 1 ? '' : 's') . ')';
    }
    $bb_continuan = array();
    $bb_instantaneas = array();
    foreach ($estado['continuan'] as $tid) {
        $coste = opg_belico_coste_tecnica($tecnicas[$tid], true);
        $energia -= $coste['energia'];
        $haki -= $coste['haki'];
        $mantenidas_siguiente[$tid] = true;
        $texto = belico_texto_coste_turno($coste);
        $lineas_inicio[] = 'Continúa ' . $nombre_tec($tid) . ($texto !== '' ? ' (' . $texto . ' por turno)' : '');
        $bb_continuan[] = '[tecnica=' . $tid . ']' . ($texto !== '' ? ' (' . $texto . ' por turno)' : '');
    }
    foreach ($estado['instantaneas'] as $tid) {
        $coste = opg_belico_coste_tecnica($tecnicas[$tid], false);
        $energia -= $coste['energia'];
        $haki -= $coste['haki'];
        $usadas[$tid] = 'inicio';
        if (opg_belico_es_mantenida($tecnicas[$tid])) { $mantenidas_siguiente[$tid] = true; }
        if ($coste['enfriamiento'] > 0) { $enfriamientos[$tid] = $coste['enfriamiento']; }
        $texto = belico_texto_coste_turno($coste);
        $lineas_inicio[] = 'Instantánea ' . $nombre_tec($tid) . ($texto !== '' ? ' (' . $texto . ')' : '');
        foreach ($coste['avisos'] as $a) { $avisos[] = $tecnicas[$tid]['nombre'] . ': ' . $a; }
        $bb_instantaneas[] = '[tecnica=' . $tid . ']' . ($texto !== '' ? ' (' . $texto . ')' : '');
    }
    if ($energia < 0 || $haki < 0) { $avisos[] = 'Los costes del inicio del turno superan tus recursos.'; $falta_recursos = true; }

    $bloques = array();
    $texto_bloques = array();
    $mov_total = 0;
    $adicionales = 0;
    $n_anterior = 0;
    $n_actual = 0;
    $sin_cortar = 0;
    $hay_actual = false;
    foreach ($estado['bloques'] as $i => $bloque) {
        $n = $i + 1;
        $es_anterior = !empty($bloque['anterior']);
        if ($es_anterior) { $n_anterior++; $etiqueta_bloque = 'Reacción ' . $n_anterior . ' (turno anterior)'; }
        else { $n_actual++; $hay_actual = true; $etiqueta_bloque = 'Bloque ' . $n_actual; }
        $instantanea_estado = array($energia, $haki, $usadas, $mantenidas_siguiente, $enfriamientos, $adicionales, $mov_total);
        $av = array();
        if ($es_anterior && $hay_actual) { $av[] = 'Las reacciones al turno anterior van antes de los bloques de este turno.'; }
        $pasivas_bloque = array_values(array_diff(array_unique(array_merge($estado['pasivas'], $bloque['pasivas_mas'])), $bloque['pasivas_menos']));
        $bonos = $bonos_de($pasivas_bloque);
        $reflejos_pasivas = isset($bonos['reflejos']) ? $bonos['reflejos'] : 0;

        $principales = 0; $utilidades = 0; $acciones_otras = 0; $bloqueos = 0; $acciones_n = 0;
        $bonos_utilidad = array();
        foreach ($bloque['pasos'] as $paso) {
            if ($paso['t'] === 'accion') {
                $acciones_n++;
                if ($paso['accion'] === 'bloqueo') { $bloqueos++; } else { $acciones_otras++; }
                continue;
            }
            $tec = $tecnicas[$paso['tid']];
            if (opg_belico_es_instantanea($tec)) { continue; }
            $rol = opg_belico_rol_tecnica($tec);
            if ($rol === 'principal') { $principales++; }
            elseif ($rol === 'utilidad') {
                $utilidades++;
                if ($paso['aplicar_bonos']) {
                    foreach (opg_belico_extraer_bonos((string) $tec['efectos'], $atributos) as $bono) {
                        $clave = $bono['k'] . ($bono['p'] ? '%' : '');
                        $bonos_utilidad[$clave] = (isset($bonos_utilidad[$clave]) ? $bonos_utilidad[$clave] : 0) + $bono['v'];
                    }
                }
            } else { $av[] = '"' . $tec['nombre'] . '" no es una técnica que se use en un bloque de acción.'; }
        }
        foreach ($bonos_utilidad as $clave => $valor) { $bonos[$clave] = (isset($bonos[$clave]) ? $bonos[$clave] : 0) + $valor; }
        $bono_danio = isset($bonos['dano']) ? $bonos['dano'] : 0;
        $bono_verdadero = isset($bonos['dano verdadero']) ? $bonos['dano verdadero'] : 0;
        $bono_ta = isset($bonos['tasa de acierto']) ? $bonos['tasa de acierto'] : 0;

        // Tipo de bloque (se deduce de lo que contiene) y reglas de la guía como avisos.
        if ($principales > 0) {
            $tipo = 'con técnica';
            if ($principales > 1) { $av[] = 'Un bloque con técnica admite una única técnica ofensiva o defensiva.'; }
            if ($utilidades + $acciones_n > 1) { $av[] = 'Junto a la técnica solo cabe una utilidad o una acción adicional que la afecte.'; }
        } elseif ($utilidades > 0) {
            $tipo = 'de utilidad';
            if ($utilidades > 2) { $av[] = 'Un bloque de utilidad admite hasta dos técnicas.'; }
            if ($acciones_otras > 0 || $bloqueos > 1) { $av[] = 'Un bloque de utilidad solo admite, además de las técnicas, un bloqueo básico.'; }
        } elseif ($acciones_n > 0) {
            $tipo = 'sin técnica';
            if ($acciones_n > 2) { $av[] = 'Un bloque sin técnica admite hasta dos acciones.'; }
        } else {
            if (count($bloque['pasos']) > 0) { $tipo = 'de instantáneas'; }
            elseif ($bloque['mov_previo'] || $bloque['mov_posterior']) { $tipo = 'sin técnica'; } // moverse solo es un bloque válido (la acción adicional es opcional)
            else { $tipo = 'vacío'; $av[] = 'El bloque está vacío.'; }
        }
        $adicionales += $acciones_n;
        $mov_total += $bloque['mov_previo'] + $bloque['mov_posterior'];

        $lineas = array();
        $bb = array();
        if ($bloque['mov_previo']) { $lineas[] = 'Movimiento previo: ' . $bloque['mov_previo'] . ' m'; $bb[] = 'Movimiento ' . $bloque['mov_previo'] . ' m'; }
        if ($es_anterior) {
            // Reacción: Reflejos (con pasivas y extra) más +5 por cada bloque del rival que no se cortó, contra su TA.
            $r = $bloque['reaccion'];
            $ref = (int) $atributos['REF'] + (int) $reflejos_pasivas + $r['ref_extra'];
            $res = belico_calc_reaccion($ref, $r['ta'], $r['distancia'], $r['sorpresa'], $sin_cortar, 0);
            $detalle_ref = 'Reflejos ' . ($ref + 5 * $sin_cortar) . ($sin_cortar ? ' (incluye +' . (5 * $sin_cortar) . ' por ' . $sin_cortar . ' bloque' . ($sin_cortar === 1 ? '' : 's') . ' sin cortar)' : '') . ' contra TA ' . $r['ta'] . ($r['distancia'] ? ' a ' . $r['distancia'] . ' m' : '');
            $pct = isset($res['porcentaje']) && $res['porcentaje'] > 0 ? ' (' . $res['porcentaje'] . '%)' : '';
            $lineas[] = 'Reacción: <strong>' . $esc($res['reaccion']) . $pct . '</strong> · ' . $esc($detalle_ref);
            $bb[] = 'Reacción: ' . $res['reaccion'] . $pct . ' (' . $detalle_ref . ')';
            $sin_cortar = $r['corto'] ? 0 : $sin_cortar + 1;
        }
        $energia_antes = $energia; $haki_antes = $haki;
        foreach ($bloque['pasos'] as $paso) {
            if ($paso['t'] === 'accion') {
                $accion = $paso['accion'];
                $etiqueta = $etiquetas_acciones[$accion][0];
                if (!$etiquetas_acciones[$accion][1]) {
                    if ($bloque['mov_previo'] || ($accion !== 'soltarse' && $bloque['mov_posterior'])) { $av[] = $etiqueta . ' no admite movimiento' . ($accion === 'soltarse' ? ' previo' : ' en el mismo bloque') . '.'; }
                }
                if ($accion === 'golpe') {
                    $total = $golpe['valor'] + $bono_danio;
                    $linea = 'Golpe básico: ' . $total . ' de daño ' . $tipo_golpe . ' · TA ' . ($ta['valor'] + $bono_ta);
                    $bb[] = 'Golpe básico (' . $total . ' de daño ' . $tipo_golpe . ', TA ' . ($ta['valor'] + $bono_ta) . ')';
                } elseif ($accion === 'bloqueo') {
                    $linea = 'Bloqueo básico: ' . $bloqueo['valor'] . ' de mitigación';
                    $bb[] = 'Bloqueo básico (' . $bloqueo['valor'] . ' de mitigación)';
                } else {
                    $linea = $etiqueta;
                    $bb[] = $etiqueta;
                }
                $lineas[] = $linea . ' <small>(acción adicional)</small>';
                continue;
            }
            $tid = $paso['tid'];
            $tec = $tecnicas[$tid];
            $coste = opg_belico_coste_tecnica($tec, false);
            $energia -= $coste['energia'];
            $haki -= $coste['haki'];
            if (isset($estado['enfriamientos'][$tid])) {
                $av[] = $tec['nombre'] . ' sigue en enfriamiento: faltan ' . $estado['enfriamientos'][$tid] . ' turno' . ($estado['enfriamientos'][$tid] === 1 ? '' : 's') . '.';
            }
            if ($coste['enfriamiento'] > 0) {
                if (isset($usadas[$tid])) { $av[] = $tec['nombre'] . ' ya se usó en este turno y tiene enfriamiento de ' . $coste['enfriamiento'] . ' turnos.'; }
                $enfriamientos[$tid] = $coste['enfriamiento'];
            }
            $usadas[$tid] = $n;
            foreach ($coste['avisos'] as $a) { $av[] = $tec['nombre'] . ': ' . $a; }
            if (opg_belico_es_mantenida($tec)) { $mantenidas_siguiente[$tid] = true; }
            $texto_coste = belico_texto_coste_turno($coste);
            $sufijo_coste = $texto_coste !== '' ? ' · ' . $texto_coste : '';
            $bb_coste = $texto_coste !== '' ? ' (' . $texto_coste . ')' : '';
            if (opg_belico_es_instantanea($tec)) {
                $lineas[] = $nombre_tec($tid) . ' <small>(instantánea' . (opg_belico_es_mantenida($tec) ? ', mantenida nueva' : '') . ')</small>' . $sufijo_coste;
                $bb[] = '[tecnica=' . $tid . ']' . $bb_coste;
                continue;
            }
            $rol = opg_belico_rol_tecnica($tec);
            if ($rol === 'principal') {
                $comun = 0; $verdadero = 0; $tipos = array();
                foreach (opg_belico_calcular_efectos_contextuales((string) $tec['efectos'], $atributos, $golpe, $bloqueo) as $resultado) {
                    if (empty($resultado['es_danio'])) { continue; }
                    if (stripos((string) $resultado['tipo'], 'verdadero') !== false) { $verdadero += (int) $resultado['valor']; }
                    else { $comun += (int) $resultado['valor']; $tipos[] = (string) $resultado['tipo']; }
                }
                $partes = array();
                $nombres_tipos = trim(implode('/', array_unique(array_filter($tipos))));
                if ($comun > 0) { $partes[] = ($comun + $bono_danio) . ' de daño' . ($nombres_tipos !== '' ? ' ' . $nombres_tipos : ''); }
                if ($verdadero > 0 || ($comun + $verdadero > 0 && $bono_verdadero)) { $partes[] = ($verdadero + $bono_verdadero) . ' de daño verdadero'; }
                $texto_danio = $partes ? rtrim(implode(' + ', $partes)) : 'sin daño calculable';
                $lineas[] = $nombre_tec($tid) . ': ' . $esc($texto_danio) . ($partes ? ' · TA ' . ($ta['valor'] + $bono_ta) : '') . $sufijo_coste;
                $bb[] = '[tecnica=' . $tid . ']' . ($partes ? ': ' . $texto_danio . ', TA ' . ($ta['valor'] + $bono_ta) : '') . $bb_coste;
            } else {
                $tiene_bonos = $paso['aplicar_bonos'] ? opg_belico_extraer_bonos((string) $tec['efectos'], $atributos) : array();
                $texto_bonos = array();
                foreach ($tiene_bonos as $bono) { $texto_bonos[] = ($bono['v'] > 0 ? '+' : '') . $bono['v'] . ($bono['p'] ? '%' : '') . ' ' . $bono['l']; }
                $lineas[] = $nombre_tec($tid) . ' <small>(' . (opg_belico_es_mantenida($tec) ? 'mantenida nueva' : 'utilidad') . ($texto_bonos ? ': ' . $esc(implode(', ', $texto_bonos)) : '') . ')</small>' . $sufijo_coste;
                $bb[] = '[tecnica=' . $tid . ']' . $bb_coste;
            }
        }
        if ($bloque['mov_posterior']) { $lineas[] = 'Movimiento posterior: ' . $bloque['mov_posterior'] . ' m'; $bb[] = 'Movimiento ' . $bloque['mov_posterior'] . ' m'; }

        if (!empty($bloque['interrumpido'])) {
            // Bloque interrumpido: no se hizo, así que no cuesta recursos, movimiento ni acciones y no deja mantenidas ni enfriamientos.
            list($energia, $haki, $usadas, $mantenidas_siguiente, $enfriamientos, $adicionales, $mov_total) = $instantanea_estado;
            $tipo .= ', interrumpido';
            array_unshift($lineas, '<strong>Interrumpido:</strong> sus costes no se cuentan.');
            $etiqueta_bloque .= ' (interrumpido)';
        }
        if (($energia < 0 || $haki < 0) && !$falta_recursos) {
            $av[] = 'No te alcanza ' . ($energia < 0 ? 'la energía' : 'el haki') . ' en este bloque.';
            $falta_recursos = true;
        }
        $texto_bb = '[b]' . $etiqueta_bloque . '[/b] (' . $tipo . '): ' . ($bb ? implode(' → ', $bb) : 'sin acciones') . '.';
        if ($bloque['detalle'] !== '') { $texto_bb .= ' ' . $bloque['detalle']; }
        $texto_bloques[] = $texto_bb;
        $bloques[] = array(
            'tipo' => $tipo,
            'html' => implode('<br>', $lineas) . '<div class="belico-t-recursos">Tras el bloque: energía ' . $energia . ' · haki ' . $haki . ($energia_antes !== $energia || $haki_antes !== $haki ? ' <small>(' . ($energia_antes - $energia) . ' de energía, ' . ($haki_antes - $haki) . ' de haki en el bloque)</small>' : '') . '</div>',
            'avisos' => $av,
        );
    }

    if ($mov_total > $max_movimiento) { $avisos[] = 'Movimiento total ' . $mov_total . ' m, por encima de tu máximo de ' . $max_movimiento . ' m.'; }
    if ($adicionales > $limite_adicionales) { $avisos[] = 'Usas ' . $adicionales . ' acciones adicionales y tienes ' . $limite_adicionales . ' por turno.'; }

    // Texto para el post.
    $etiquetas_config = array('una_mano' => 'una mano', 'dos_manos' => 'ambas manos', 'dos_armas' => 'dos armas', 'tres_armas' => 'tres armas');
    $linea_equipo = 'Equipo: ' . ($sel['armas'] ? implode(', ', array_map('opg_belico_nombre_arma', $sel['armas'])) . ' (' . $etiquetas_config[$sel['configuracion']] . ')' : 'sin arma') . '.';
    $texto = array('[spoiler=' . $estado['nombre'] . ']', $linea_equipo);
    if ($estado['pasivas']) {
        $partes = array();
        foreach ($estado['pasivas'] as $tid) {
            $efecto = trim(preg_replace('/\s+/', ' ', opg_belico_aplicar_valores((string) $tecnicas[$tid]['efectos'], opg_belico_sustituciones_texto((string) $tecnicas[$tid]['efectos'], $atributos, $golpe, $bloqueo))));
            $partes[] = '[tecnica=' . $tid . ']' . ($efecto !== '' ? ' (' . $efecto . ')' : '');
        }
        $texto[] = 'Pasivas del turno: ' . implode(', ', $partes) . '.';
    }
    if ($bb_continuan) { $texto[] = 'Mantenidas que continúan: ' . implode(', ', $bb_continuan) . '.'; }
    if ($bb_instantaneas) { $texto[] = 'Instantáneas: ' . implode(', ', $bb_instantaneas) . '.'; }
    foreach ($texto_bloques as $linea) { $texto[] = $linea; }
    $texto[] = 'Recursos tras el turno: Energía ' . $energia . ($max_energia !== null ? '/' . $max_energia : '') . ' · Haki ' . $haki . ($max_haki !== null ? '/' . $max_haki : '') . '. Movimiento ' . $mov_total . '/' . $max_movimiento . ' m. Acciones adicionales ' . $adicionales . '/' . $limite_adicionales . '.';
    $texto[] = '[/spoiler]';

    $resumen = '<div class="belico-t-resumen-fila"><span>Recursos tras el turno</span><strong>Energía ' . $energia . ($max_energia !== null ? ' de ' . $max_energia : '') . ' · Haki ' . $haki . ($max_haki !== null ? ' de ' . $max_haki : '') . '</strong></div>'
        . '<div class="belico-t-resumen-fila"><span>Movimiento</span><strong>' . $mov_total . ' de ' . $max_movimiento . ' m</strong></div>'
        . '<div class="belico-t-resumen-fila"><span>Acciones adicionales</span><strong>' . $adicionales . ' de ' . $limite_adicionales . '</strong></div>';
    if ($enfriamientos) {
        $partes = array();
        foreach ($enfriamientos as $tid => $turnos) { $partes[] = $esc($tecnicas[$tid]['nombre']) . ' (' . $turnos . ')'; }
        $resumen .= '<div class="belico-t-resumen-fila"><span>Quedan en enfriamiento</span><strong>' . implode(', ', $partes) . '</strong></div>';
    }
    if ($mantenidas_siguiente) {
        $partes = array();
        foreach (array_keys($mantenidas_siguiente) as $tid) { $partes[] = $esc($tecnicas[$tid]['nombre']); }
        $resumen .= '<div class="belico-t-resumen-fila"><span>Mantenidas para el siguiente turno</span><strong>' . implode(', ', $partes) . '</strong></div>';
    }

    // Enfriamiento N: la técnica queda bloqueada durante los N turnos siguientes al uso. Los que ya arrastrabas bajan uno.
    $enfriamientos_siguiente = array();
    foreach ($estado['enfriamientos'] as $tid => $turnos) {
        if ($turnos - 1 > 0) { $enfriamientos_siguiente[$tid] = $turnos - 1; }
    }
    foreach ($enfriamientos as $tid => $turnos) { $enfriamientos_siguiente[$tid] = $turnos; }

    return array(
        'inicio_html' => implode('<br>', $lineas_inicio),
        'bloques' => $bloques,
        'avisos' => $avisos,
        'resumen_html' => $resumen,
        'bbcode' => implode("\n", $texto),
        'energia_final' => $energia,
        'haki_final' => $haki,
        'siguiente' => array(
            'enfriamientos' => $enfriamientos_siguiente,
            'continuan' => array_values(array_unique(array_merge($estado['continuan'], array_keys($mantenidas_siguiente)))),
            'energia' => max(0, $energia),
            'haki' => max(0, $haki),
        ),
    );
}

// ── Turno: bloque de la página ────────────────────────────────────────────

function belico_render_bloque_turno($atributos, $recursos, $armas)
{
    $movimiento = (int) belico_calc_estadisticas($atributos)['movimiento'];
    $html  = '<section class="belico-modulo belico-turno" id="belico-turno"><h2>Turno</h2>';
    $html .= '<p class="belico-turno-intro">Arma todo tu turno aquí: equipo, recursos, mantenidas y bloques en el orden en que los escribirás. Cada bloque solo te deja poner lo que permite la guía. Los daños, costes y avisos se calculan solos y al final copias el turno completo. Movimiento máximo: <strong>' . $movimiento . ' m</strong>.</p>';
    $html .= belico_render_vista_toggle();

    $html .= '<div class="belico-pasivas-conjuntos"><span class="belico-pasivas-armas-titulo">Plantillas:</span>'
        . '<select id="belico-turno-plantillas" aria-label="Plantillas de turno guardadas"><option value="">(ninguna guardada)</option></select>'
        . '<button type="button" class="opg-chip" id="belico-plantilla-aplicar">Aplicar</button>'
        . '<button type="button" class="opg-chip" id="belico-plantilla-guardar">Guardar este turno como...</button>'
        . '<button type="button" class="opg-chip" id="belico-plantilla-borrar">Borrar</button></div>';
    $html .= '<div class="belico-t-cabecera af-row">';
    $html .= '<div class="af-field"><label for="belico-turno-config">Equipo</label><select id="belico-turno-config"><option value="sin_arma">Sin arma</option><option value="una_mano">Una arma</option><option value="dos_manos">Una arma con ambas manos</option><option value="dos_armas">Dos armas</option><option value="tres_armas">Tres armas</option></select></div>';
    $html .= belico_render_selector_arma($armas, 'turno_arma_uno', 'Arma 1', 'Selecciona un arma', '');
    $html .= belico_render_selector_arma($armas, 'turno_arma_dos', 'Arma 2', 'Selecciona una segunda arma', '');
    $html .= belico_render_selector_arma($armas, 'turno_arma_tres', 'Arma 3', 'Selecciona una tercera arma', '');
    $html .= '</div>';

    $html .= '<div class="belico-t-cabecera af-row">';
    $html .= '<div class="af-field"><label for="belico-turno-vida">Vida actual</label><input type="number" id="belico-turno-vida" min="0" value="' . (int) $recursos['vida_max'] . '"><small>máx. ' . (int) $recursos['vida_max'] . '</small></div>';
    $html .= '<div class="af-field"><label for="belico-turno-energia">Energía actual</label><input type="number" id="belico-turno-energia" min="0" value="' . (int) $recursos['energia_max'] . '"><small>máx. ' . (int) $recursos['energia_max'] . '</small></div>';
    $html .= '<div class="af-field"><label for="belico-turno-haki">Haki actual</label><input type="number" id="belico-turno-haki" min="0" value="' . (int) $recursos['haki_max'] . '"><small>máx. ' . (int) $recursos['haki_max'] . '</small></div>';
    $html .= '<div class="af-field"><label for="belico-turno-extra">Acciones adicionales extra</label><input type="number" id="belico-turno-extra" min="0" max="20" value="0"><small>Base 3; suma lo que den tus pasivas</small></div>';
    $html .= '</div>';

    $html .= '<div class="belico-t-listas">';
    $html .= '<div class="belico-t-lista"><h3>Pasivas del turno</h3><div id="belico-turno-pasivas" class="belico-t-chips"></div><button type="button" class="opg-chip" data-belico-ir="pasivas">Elegir pasivas</button></div>';
    $html .= '<div class="belico-t-lista"><h3>Mantenidas que continúan</h3><small>Siguen del turno anterior: no ocupan bloque y pagan su coste por turno.</small><div id="belico-turno-continuan" class="belico-t-chips"></div><select id="belico-turno-continuan-add" aria-label="Añadir mantenida que continúa"></select></div>';
    $html .= '<div class="belico-t-lista"><h3>Instantáneas al inicio</h3><small>Haki y recubrimientos elementales: gastan su recurso y no ocupan bloque.</small><div id="belico-turno-instantaneas" class="belico-t-chips"></div><select id="belico-turno-instantaneas-add" aria-label="Añadir instantánea"></select></div>';
    $html .= '<div class="belico-t-lista"><h3>Técnicas en enfriamiento</h3><small>Quedan bloqueadas los turnos que indica su enfriamiento. Se arrastran solas al pasar de turno.</small><div id="belico-turno-enfriamientos" class="belico-t-chips"></div><select id="belico-turno-enfriamientos-add" aria-label="Añadir técnica en enfriamiento"></select></div>';
    $html .= '<div id="belico-turno-inicio" class="belico-t-inicio"></div>';
    $html .= '</div>';

    $html .= '<h3 class="belico-t-titulo">Bloques de acción</h3>';
    $html .= '<div id="belico-turno-bloques" class="belico-t-bloques"></div>';
    $html .= '<div class="belico-t-agregar" role="group" aria-label="Añadir bloque"><span>Añadir:</span>'
        . '<button type="button" class="opg-chip" data-belico-turno-nuevo="tecnica" title="Una técnica ofensiva o defensiva y un único complemento">Bloque con técnica</button>'
        . '<button type="button" class="opg-chip" data-belico-turno-nuevo="sin_tecnica" title="Hasta dos acciones que no son técnicas">Bloque sin técnica</button>'
        . '<button type="button" class="opg-chip" data-belico-turno-nuevo="utilidad" title="Hasta dos técnicas de utilidad o mantenidas y un bloqueo básico">Bloque de utilidad</button>'
        . '<button type="button" class="opg-chip" data-belico-turno-nuevo="reaccion" title="Para responder a bloques del rival del turno anterior">Reaccionar al turno anterior</button>'
        . '<button type="button" class="opg-chip" data-belico-turno-nuevo="golpe">Golpe básico</button>'
        . '<button type="button" class="opg-chip" data-belico-turno-nuevo="mover">Moverse</button></div>';

    $html .= '<div id="belico-turno-avisos" class="belico-t-avisos" aria-live="polite"></div>';
    $html .= '<div id="belico-turno-resumen" class="belico-t-resumen"></div>';
    $html .= '<details class="belico-pasivas-previa" open><summary>Texto que se copia al post (puedes editarlo)</summary>'
        . '<textarea id="belico-turno-texto" rows="10" aria-label="Texto del turno para copiar"></textarea>'
        . '<button type="button" class="opg-chip" id="belico-turno-regenerar" hidden>Volver al texto generado</button></details>';
    $html .= '<div class="belico-t-acciones"><button type="button" class="btn-op btn-op--secundario btn-op--sm" id="belico-turno-copiar">Copiar turno</button>'
        . '<button type="button" class="opg-chip" id="belico-turno-siguiente" title="Pasa tus recursos finales y las mantenidas al turno nuevo">Pasar al siguiente turno</button>'
        . '<button type="button" class="opg-chip" id="belico-turno-limpiar">Turno nuevo</button><span id="belico-turno-aviso" class="belico-pasivas-aviso" aria-live="polite"></span></div>';
    $html .= '</section>';
    return $html;
}

// ───────────────────────────────────────────────────────────────────────────
// CSS y JS fijos (nowdoc — PHP no los interpreta)
// ───────────────────────────────────────────────────────────────────────────

function belico_css()
{
    return <<<'CSS'
.belico-pagina, .belico-pagina * { box-sizing: border-box; }
.belico-pagina {
  width: min(100%, 1280px);
  margin: 0 auto;
  color: var(--opg-marron);
  font-family: var(--opg-fuente-cuerpo);
}
.belico-pagina .mainBackground {
  width: 100%;
  margin: 0 0 var(--opg-espacio-6);
  padding: clamp(16px, 3vw, 32px);
  border: 2px solid #ba3c00;
  border-top: 0;
  border-radius: 0;
  background: var(--opg-textura-grano, none), var(--opg-crema-cuerpo);
  text-align: left;
}
.belico-pagina .belico-cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--opg-espacio-4);
  padding: 0 0 var(--opg-espacio-5);
  border-bottom: 3px solid var(--opg-tinta);
}
.belico-pagina .belico-cabecera-texto { min-width: 0; padding-left: var(--opg-espacio-4); border-left: 5px solid var(--opg-naranja); }
.belico-pagina .belico-sobretitulo { margin: 0 0 4px; color: var(--opg-morado-texto); font: 13px/1.2 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina h1 { margin: 0; color: var(--opg-marron); font: 28px/1.15 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-personaje { margin: 8px 0 4px; font: 18px/1.25 InterMedium, var(--opg-fuente-cuerpo); overflow-wrap: anywhere; }
.belico-pagina .belico-origen { display: block; color: var(--opg-gris-texto); font-size: 13px; line-height: 1.45; }
.belico-pagina a:not(.btn-op):not(.opg-chip) { color: var(--opg-morado-texto); }
.belico-pagina .belico-cabecera > .opg-chip { flex: none; background: var(--opg-crema-clara); }
.belico-pagina .belico-contexto {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: var(--opg-espacio-3);
  padding: var(--opg-espacio-4) 0 var(--opg-espacio-5);
  border-bottom: 1px solid var(--opg-tinta);
}
.belico-pagina .belico-contexto .af-field { flex: 0 1 190px; }
.belico-pagina .belico-contexto .btn-op { min-height: 38px; }
.belico-pagina .belico-workspace { display: grid; grid-template-columns: minmax(260px, 320px) minmax(0, 1fr); gap: var(--opg-espacio-6); }
.belico-pagina .belico-sidebar { min-width: 0; padding-right: var(--opg-espacio-6); border-right: 2px solid var(--opg-tinta); }
.belico-pagina .belico-principal { min-width: 0; }
.belico-pagina .belico-tabs { display: flex; align-items: flex-end; gap: var(--opg-espacio-2); margin: 0; border-bottom: 2px solid var(--opg-tinta); overflow-x: auto; scrollbar-width: thin; }
.belico-pagina .belico-tabs button { flex: 0 0 auto; margin: 0; padding: 10px 16px 9px; border: 2px solid var(--opg-tinta); border-bottom: 0; border-radius: var(--opg-radio-sm) var(--opg-radio-sm) 0 0; background: var(--opg-crema-clara); color: var(--opg-morado-texto); font: 800 15px/1.1 Inter, Arial, sans-serif; letter-spacing: 0; cursor: pointer; transition: transform var(--opg-rapido), background var(--opg-rapido), color var(--opg-rapido); }
.belico-pagina .belico-tabs button:hover, .belico-pagina .belico-tabs button:focus-visible { position: relative; z-index: 1; background: #ffe59b; color: var(--opg-tinta); outline: 0; transform: translateY(-2px); }
.belico-pagina .belico-tabs button[aria-selected="true"] { position: relative; z-index: 2; margin-bottom: -2px; padding-bottom: 11px; background: var(--opg-naranja); color: var(--opg-tinta); }
.belico-pagina .belico-tab-panel { min-width: 0; }
.belico-pagina .belico-tab-panel[hidden] { display: none; }
.belico-pagina .belico-modulo { min-width: 0; padding: var(--opg-espacio-5) 0 var(--opg-espacio-6); border-bottom: 2px solid var(--opg-tinta); }
.belico-pagina .belico-modulo:last-child { border-bottom: 0; }
.belico-pagina .belico-modulo h2 {
  margin: 0 0 var(--opg-espacio-4);
  padding-left: var(--opg-espacio-3);
  border-left: 4px solid var(--opg-naranja);
  color: var(--opg-marron);
  font: 19px/1.2 InterMedium, var(--opg-fuente-cuerpo);
}
.belico-pagina .belico-modulo h3 { margin: 0 0 var(--opg-espacio-3); color: var(--opg-morado-texto); font: 15px/1.25 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .af-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--opg-espacio-3); margin: 0 0 var(--opg-espacio-4); }
.belico-pagina .af-field { min-width: 0; flex: 1 1 160px; }
.belico-pagina .af-field label { min-height: 1.35em; letter-spacing: 0; }
.belico-pagina .af-field input, .belico-pagina .af-field select { width: 100%; min-height: 38px; background-color: var(--opg-crema-clara); color: var(--opg-marron); }
.belico-pagina .af-field input[type="checkbox"] { width: auto; min-height: 0; accent-color: var(--opg-naranja); }
.belico-pagina .belico-check { flex-basis: 100%; }
.belico-pagina .belico-check label { display: inline-flex; align-items: center; gap: var(--opg-espacio-2); min-height: 38px; cursor: pointer; }
.belico-pagina .belico-atributos-grid { gap: var(--opg-espacio-2); }
.belico-pagina .belico-atributos-grid .af-field { flex: 1 1 calc(50% - var(--opg-espacio-2)); }
.belico-pagina .belico-atributos-grid .af-field label { display: flex; justify-content: space-between; gap: 4px; }
.belico-pagina .belico-base { color: var(--opg-gris-texto); font: 11px/1.3 var(--opg-fuente-cuerpo); text-transform: none; white-space: nowrap; }
.belico-pagina .belico-atributos-grid small { display: block; text-align: right; }
.belico-pagina .belico-atributos-grid small strong { color: var(--opg-morado-texto); font-size: 14px; }
.belico-pagina .belico-recursos-grid .af-field { flex-basis: 100%; }
.belico-pagina .belico-estadisticas { margin: 0; }
.belico-pagina .belico-estadisticas > div, .belico-pagina .belico-estado-resultado p { display: flex; justify-content: space-between; gap: var(--opg-espacio-2); margin: 0; padding: 7px 0; border-bottom: 1px solid rgba(59, 19, 0, .22); font-size: 13px; }
.belico-pagina .belico-estadisticas > div:last-child, .belico-pagina .belico-estado-resultado p:last-child { border-bottom: 0; }
.belico-pagina .belico-estadistica { position: relative; cursor: help; }
.belico-pagina .belico-estadistica:focus { outline: 2px solid var(--opg-naranja); outline-offset: 2px; }
.belico-pagina .belico-estadistica dt { display: inline-flex; align-items: center; gap: 6px; }
.belico-pagina .belico-estadistica-info { display: inline-grid; place-items: center; width: 15px; height: 15px; border: 1px solid var(--opg-morado-texto); border-radius: 50%; color: var(--opg-morado-texto); font: 11px/1 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-estadistica-ayuda { position: absolute; z-index: 20; left: 0; top: calc(100% + 6px); width: max-content; max-width: min(270px, calc(100vw - 48px)); padding: var(--opg-espacio-2) var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); box-shadow: 3px 3px 0 var(--opg-tinta); color: var(--opg-marron); font-size: 12px; line-height: 1.4; opacity: 0; pointer-events: none; transform: translateY(-3px); transition: opacity var(--opg-rapido), transform var(--opg-rapido); }
.belico-pagina .belico-estadistica:hover .belico-estadistica-ayuda, .belico-pagina .belico-estadistica:focus .belico-estadistica-ayuda { opacity: 1; transform: translateY(0); }
.belico-pagina .belico-estadisticas dt { color: var(--opg-gris-texto); }
.belico-pagina .belico-estadisticas dd { margin: 0; font-family: InterMedium, var(--opg-fuente-cuerpo); text-align: right; white-space: nowrap; }
.belico-pagina .belico-estado-resultado strong { text-align: right; }
.belico-pagina .belico-resultados { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--opg-espacio-3); margin: var(--opg-espacio-4) 0 0; }
.belico-pagina .belico-resultados > div { display: flex; flex-direction: column; justify-content: center; min-width: 0; padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); }
.belico-pagina .belico-resultados > .belico-resultado-clave { border-color: var(--opg-morado-texto); background: var(--opg-morado-seleccion); }
.belico-pagina .belico-resultados span { color: var(--opg-gris-texto); font-size: 12px; }
.belico-pagina .belico-resultados strong { color: var(--opg-marron); font: 26px/1.2 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-resultados small { color: var(--opg-gris-texto); overflow-wrap: anywhere; }
.belico-pagina .btn-op { letter-spacing: 0; }
.belico-pagina .btn-op--sm { font: 14px/1.3 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-tinta); text-shadow: none; padding: 8px 14px; }
.belico-pagina .btn-op--secundario.btn-op--sm { color: #fff; }
.belico-pagina .belico-campo-tecnica { flex: 1 1 100%; }
.belico-pagina .belico-tecnica-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-3); margin-bottom: var(--opg-espacio-2); }
.belico-pagina .belico-tecnica-filtros input[type="search"] { flex: 1 1 220px; width: auto; }
.belico-pagina .belico-tecnica-solo { display: inline-flex; align-items: center; gap: var(--opg-espacio-2); font-size: 13px; cursor: pointer; text-transform: none; }
.belico-pagina .belico-campo-tecnica small { color: var(--opg-gris-texto); }
.belico-pagina .belico-efectos [data-belico-usar-en-turno]:disabled { opacity: .6; cursor: default; }
.belico-pagina .belico-calculo { position: relative; cursor: help; text-decoration: underline dotted var(--opg-morado-texto); text-underline-offset: 4px; }
.belico-pagina .belico-calculo:focus-visible { outline: 3px solid var(--opg-naranja); outline-offset: 3px; }
.belico-pagina .belico-calculo::after { content: attr(data-opg-calculo); position: absolute; z-index: 30; left: 0; bottom: calc(100% + 8px); width: max-content; max-width: 290px; white-space: pre-line; padding: 10px; border: 2px solid var(--opg-tinta); border-radius: 6px; background: var(--opg-crema-clara); box-shadow: 4px 4px 0 var(--opg-tinta); color: var(--opg-marron); font: 400 12px/1.45 var(--opg-fuente-cuerpo); text-decoration: none; text-align: left; opacity: 0; pointer-events: none; transform: translateY(3px); transition: opacity .15s, transform .15s; }
.belico-pagina .belico-calculo:hover::after, .belico-pagina .belico-calculo:focus::after { opacity: 1; transform: translateY(0); }
.belico-pagina .belico-efectos code { color: var(--opg-gris-texto); font-size: 12px; }
/* El valor va dentro de la frase (+55 de [Daño]): en línea y con la tipografía del texto, aunque la tarjeta fuerce strong en bloque. */
.belico-pagina .belico-calculo.belico-calculo--chip { display: inline; font: inherit; font-weight: 700; line-height: inherit; color: var(--opg-morado-texto); }
.belico-pagina .belico-resultado-texto { padding: var(--opg-espacio-3) var(--opg-espacio-4); border-left: 3px solid var(--opg-morado); background: var(--opg-crema-clara); font-size: 13px; line-height: 1.45; }
.belico-pagina .belico-resultado-texto p { margin: 0 0 var(--opg-espacio-2); }
.belico-pagina .belico-resultado-texto p:last-child { margin-bottom: 0; }
.belico-pagina .belico-resultado-destacado { display: flex; align-items: baseline; flex-wrap: wrap; gap: var(--opg-espacio-2); }
.belico-pagina .belico-resultado-destacado strong { color: var(--opg-morado-texto); font-size: 17px; }
.belico-pagina .belico-resultado-destacado em { color: var(--opg-gris-texto); font-style: normal; }
.belico-pagina .belico-utilidades-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: var(--opg-espacio-5); }
.belico-pagina .belico-utilidad { min-width: 0; padding: var(--opg-espacio-4) 0; border-top: 1px solid rgba(59, 19, 0, .28); }
.belico-pagina .belico-utilidad .af-row { margin-bottom: var(--opg-espacio-2); }
.belico-pagina .belico-utilidad p { margin: 0; font-size: 13px; line-height: 1.45; }
.belico-pagina #belico-copiar-textarea:not([hidden]) { display: block; width: 100%; min-height: 100px; margin-top: var(--opg-espacio-3); padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); font: 13px/1.4 var(--opg-fuente-cuerpo); }
.belico-pagina .belico-pasivas-lista { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--opg-espacio-3); padding: var(--opg-espacio-3) 0; }
.belico-pagina .belico-llevas { margin: var(--opg-espacio-3) 0 0; padding: 6px var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); color: var(--opg-marron); font-size: 12px; line-height: 1.4; }
.belico-pagina .belico-llevas:empty { display: none; }
.belico-pagina .belico-avisos-ataque { margin: var(--opg-espacio-3) 0 0; padding: 0; list-style: none; display: grid; gap: 4px; }
.belico-pagina .belico-avisos-ataque li { padding: 6px var(--opg-espacio-3); border-left: 4px solid var(--opg-gris-texto); background: var(--opg-crema-clara); font-size: 13px; line-height: 1.4; }
.belico-pagina .belico-avisos-ataque .belico-aviso-aviso { border-left-color: var(--opg-rojo-error); }
.belico-pagina .belico-avisos-ataque .belico-aviso-info { border-left-color: var(--opg-morado); }
.belico-pagina .belico-armas-elegidas { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--opg-espacio-3); margin: var(--opg-espacio-3) 0 0; }
.belico-pagina .belico-arma-tarjeta { padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); font-size: 13px; line-height: 1.4; }
.belico-pagina .belico-arma-tarjeta h4 { margin: 0 0 2px; font: 15px/1.25 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-marron); }
.belico-pagina .belico-arma-tarjeta p { margin: 4px 0 0; }
.belico-pagina .belico-arma-tarjeta small, .belico-pagina .belico-sin-arma small { color: var(--opg-gris-texto); }
.belico-pagina .belico-resumen { margin: var(--opg-espacio-4) 0 0; padding: var(--opg-espacio-3) var(--opg-espacio-4); border: 2px solid var(--opg-morado-texto); border-radius: var(--opg-radio-md); background: var(--opg-morado-seleccion); }
.belico-pagina .belico-resumen h3 { margin: 0 0 var(--opg-espacio-2); font: 14px/1.2 InterMedium, var(--opg-fuente-cuerpo); text-transform: uppercase; letter-spacing: .5px; color: var(--opg-morado-texto); }
.belico-pagina .belico-resumen-fila { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px var(--opg-espacio-3); padding: 3px 0; font-size: 13px; }
.belico-pagina .belico-resumen-fila[hidden] { display: none; }
.belico-pagina .belico-resumen-fila > span:first-child { min-width: 110px; color: var(--opg-gris-texto); }
.belico-pagina .belico-resumen-fila strong { font: 20px/1.2 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-marron); }
.belico-pagina .belico-resumen-fila strong.belico-resumen-coste { font-size: 14px; }
.belico-pagina .belico-resumen-fila small { color: var(--opg-gris-texto); }
.belico-pagina .belico-resumen .belico-con-pasivas { color: var(--opg-morado-texto); font-weight: 700; }
.belico-pagina .belico-pasos { margin-top: var(--opg-espacio-2); font-size: 12px; color: var(--opg-gris-texto); }
.belico-pagina .belico-pasos summary { cursor: pointer; color: var(--opg-morado-texto); }
.belico-pagina .belico-pasos h4 { margin: var(--opg-espacio-2) 0 2px; font: 700 12px/1.2 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-marron); }
.belico-pagina .belico-pasos ol { margin: 0; padding-left: 20px; }
.belico-pagina .belico-turno-intro { margin: 0 0 var(--opg-espacio-3); font-size: 13px; line-height: 1.5; }
.belico-pagina .belico-t-cabecera { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: var(--opg-espacio-3); margin-bottom: var(--opg-espacio-3); }
.belico-pagina .af-field[hidden] { display: none; }
.belico-pagina .belico-t-listas { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--opg-espacio-3); margin: var(--opg-espacio-3) 0; }
.belico-pagina .belico-t-lista { padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); }
.belico-pagina .belico-t-lista h3 { margin: 0 0 4px; font: 13px/1.2 InterMedium, var(--opg-fuente-cuerpo); text-transform: uppercase; letter-spacing: .5px; color: var(--opg-morado-texto); }
.belico-pagina .belico-t-lista small { display: block; color: var(--opg-gris-texto); font-size: 11px; line-height: 1.3; }
.belico-pagina .belico-t-lista select { width: 100%; margin-top: var(--opg-espacio-2); min-height: 34px; background-color: var(--opg-crema-clara); color: var(--opg-marron); }
.belico-pagina .belico-t-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: var(--opg-espacio-2) 0; }
.belico-pagina .belico-t-inicio { grid-column: 1 / -1; font-size: 12px; color: var(--opg-marron); }
.belico-pagina .belico-t-inicio:empty { display: none; }
.belico-pagina .belico-t-titulo { margin: var(--opg-espacio-4) 0 var(--opg-espacio-2); font: 15px/1.2 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-marron); }
.belico-pagina .belico-t-bloques { display: grid; gap: var(--opg-espacio-3); }
.belico-pagina .belico-t-vacio { margin: 0; color: var(--opg-gris-texto); font-size: 13px; }
.belico-pagina .belico-t-bloque { border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-md); background: var(--opg-crema-clara); box-shadow: 3px 3px 0 var(--opg-tinta); }
.belico-pagina .belico-t-bloque-cab { display: flex; align-items: center; gap: var(--opg-espacio-3); padding: 6px var(--opg-espacio-3); border-bottom: 2px solid var(--opg-tinta); background: var(--opg-morado); color: #fff; }
.belico-pagina .belico-t-bloque-cab h4 { margin: 0; font: 14px/1.2 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-t-tipo-select { min-height: 28px; max-width: 280px; padding: 2px 8px; border: 1px solid #fff; border-radius: 4px; background: #fff; color: var(--opg-marron); font-size: 12px; }
.belico-pagina .belico-t-tipo { font-size: 12px; opacity: .9; }
.belico-pagina .belico-t-controles { margin-left: auto; display: inline-flex; gap: 4px; }
.belico-pagina .belico-t-x { min-height: 0; padding: 2px 8px; border: 1px solid currentColor; border-radius: 4px; background: transparent; color: inherit; cursor: pointer; font-size: 12px; }
.belico-pagina .belico-t-paso .belico-t-x { color: var(--opg-rojo-error); }
.belico-pagina .belico-t-bloque-cuerpo { display: grid; gap: var(--opg-espacio-2); padding: var(--opg-espacio-3); }
.belico-pagina .belico-t-mov { font-size: 12px; color: var(--opg-gris-texto); }
.belico-pagina .belico-t-mov input { width: 80px; min-height: 30px; margin-left: 4px; }
.belico-pagina .belico-t-interrumpido { font-size: 11px; cursor: pointer; }
.belico-pagina .belico-t-interrumpido input { width: auto; min-height: 0; accent-color: var(--opg-naranja); }
.belico-pagina .belico-t-bloque--interrumpido { opacity: .7; border-style: dashed; }
.belico-pagina .belico-t-bloque--reaccion { border-color: var(--opg-morado-texto); }
.belico-pagina .belico-t-bloque--reaccion .belico-t-bloque-cab { background: var(--opg-morado-texto); }
.belico-pagina .belico-t-reaccion { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-2) var(--opg-espacio-3); padding: var(--opg-espacio-2) var(--opg-espacio-3); border: 1px dashed var(--opg-morado-texto); border-radius: var(--opg-radio-sm); background: var(--opg-morado-seleccion); }
.belico-pagina .belico-t-reaccion select { min-height: 30px; background-color: #fff; color: var(--opg-marron); }
.belico-pagina .belico-t-paso { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-2); }
.belico-pagina .belico-t-paso select { flex: 1 1 220px; min-width: 0; min-height: 34px; background-color: #fff; color: var(--opg-marron); }
.belico-pagina .belico-t-paso-tipo { flex: 0 0 auto; min-width: 150px; font: 700 11px/1.2 InterMedium, var(--opg-fuente-cuerpo); text-transform: uppercase; color: var(--opg-morado-texto); }
.belico-pagina .belico-t-bonos { font-size: 11px; color: var(--opg-gris-texto); }
.belico-pagina .belico-t-agregar-paso, .belico-pagina .belico-t-agregar { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-2); }
.belico-pagina .belico-t-agregar { margin: var(--opg-espacio-3) 0; }
.belico-pagina .belico-t-agregar > span { font: 700 12px/1 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-morado-texto); }
.belico-pagina .belico-t-pasivas { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.belico-pagina .belico-t-pasivas select { min-height: 30px; max-width: 220px; font-size: 12px; }
.belico-pagina .belico-chip-sel--off { opacity: .45; text-decoration: line-through; }
.belico-pagina .belico-t-bloque-cuerpo > input[type="text"] { width: 100%; min-height: 34px; background-color: #fff; }
.belico-pagina .belico-t-resultado { padding: var(--opg-espacio-2) var(--opg-espacio-3); border-left: 3px solid var(--opg-morado); background: #fff3e0; font-size: 13px; line-height: 1.5; }
.belico-pagina .belico-t-resultado:empty { display: none; }
.belico-pagina .belico-t-recursos { margin-top: 4px; color: var(--opg-gris-texto); font-size: 11px; }
.belico-pagina .belico-t-aviso { margin: 4px 0 0; padding-left: var(--opg-espacio-3); border-left: 3px solid var(--opg-rojo-error); color: var(--opg-rojo-error); font-size: 12px; line-height: 1.4; }
.belico-pagina .belico-t-avisos { margin: var(--opg-espacio-3) 0; }
.belico-pagina .belico-t-resumen { margin: var(--opg-espacio-3) 0; padding: var(--opg-espacio-3) var(--opg-espacio-4); border: 2px solid var(--opg-morado-texto); border-radius: var(--opg-radio-md); background: var(--opg-morado-seleccion); }
.belico-pagina .belico-t-resumen:empty { display: none; }
.belico-pagina .belico-t-resumen-fila { display: flex; flex-wrap: wrap; gap: 4px var(--opg-espacio-3); padding: 2px 0; font-size: 13px; }
.belico-pagina .belico-t-resumen-fila > span { min-width: 190px; color: var(--opg-gris-texto); }
.belico-pagina .belico-pasivas-previa textarea { display: block; width: 100%; margin-top: var(--opg-espacio-2); padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); color: var(--opg-marron); font: 12px/1.4 var(--opg-fuente-cuerpo); }
.belico-pagina .belico-t-acciones { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-3); margin-top: var(--opg-espacio-3); }
.belico-pagina .belico-pasivas-controles { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-3); margin-bottom: var(--opg-espacio-3); }
.belico-pagina .belico-pasivas-controles .belico-vista { margin: 0; }
.belico-pagina .belico-pasivas-conjuntos { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-2); margin-bottom: var(--opg-espacio-3); }
.belico-pagina .belico-pasivas-conjuntos select { min-height: 34px; padding: 4px 8px; background-color: var(--opg-crema-clara); color: var(--opg-marron); }
.belico-pagina .belico-pasivas-armas .opg-chip[hidden] { display: none; }
.belico-pagina .belico-pasivas-barra { position: sticky; top: 8px; z-index: 5; margin: 0 0 var(--opg-espacio-3); padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-md); background: var(--opg-crema-clara); box-shadow: 3px 3px 0 var(--opg-tinta); }
.belico-pagina .belico-pasivas-barra-fila { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--opg-espacio-3); font: 700 13px/1.2 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-pasivas-aviso { color: var(--opg-verde-exito); font-size: 12px; font-weight: 700; }
.belico-pagina .belico-pasivas-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: var(--opg-espacio-2); }
.belico-pagina .belico-pasivas-chips:empty { display: none; }
.belico-pagina .belico-chip-sel { min-height: 0; padding: 3px 10px; border: 2px solid var(--opg-morado-texto); border-radius: 999px; background: #fff3e0; color: var(--opg-marron); font: 600 12px/1.2 var(--opg-fuente-cuerpo); cursor: pointer; }
.belico-pagina .belico-chip-sel:hover { background: var(--opg-morado); color: #fff; }
.belico-pagina .belico-pasivas-bonos { margin-top: var(--opg-espacio-2); font-size: 13px; color: var(--opg-marron); }
.belico-pagina .belico-pasivas-bonos:empty { display: none; }
.belico-pagina .belico-bono { display: inline-block; margin-right: var(--opg-espacio-3); }
.belico-pagina .belico-bono strong { color: var(--opg-morado-texto); }
.belico-pagina .belico-pasiva-condiciones { margin: var(--opg-espacio-2) 0 0; display: flex; flex-wrap: wrap; gap: 4px; }
.belico-pagina .belico-etiqueta { padding: 1px 8px; border: 1px solid var(--opg-gris-texto); border-radius: 10px; background: #fff; color: var(--opg-gris-texto); font-size: 11px; }
.belico-pagina .belico-pasivas-previa { margin-top: var(--opg-espacio-3); font-size: 12px; color: var(--opg-gris-texto); }
.belico-pagina .belico-pasivas-previa summary { cursor: pointer; color: var(--opg-morado-texto); }
.belico-pagina .belico-pasivas-previa pre { margin: var(--opg-espacio-2) 0 0; padding: var(--opg-espacio-3); white-space: pre-wrap; border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); color: var(--opg-marron); font: 12px/1.4 var(--opg-fuente-cuerpo); }
.belico-pagina .belico-con-pasivas { color: var(--opg-morado-texto); font-weight: 700; }
.belico-pagina .belico-nota-pasivas { margin: var(--opg-espacio-3) 0 0; font-size: 13px; color: var(--opg-marron); }
.belico-pagina .belico-pasivas--compacta .belico-pasivas-lista { grid-template-columns: minmax(0, 1fr); gap: var(--opg-espacio-2); }
.belico-pagina .belico-pasivas--compacta .belico-pasiva-item { padding: var(--opg-espacio-2) var(--opg-espacio-3); }
.belico-pagina .belico-pasivas--compacta .belico-pasiva-item:has(input:checked) { transform: none; box-shadow: none; }
.belico-pagina .belico-pasivas--compacta .belico-pasiva-efecto, .belico-pagina .belico-pasivas--compacta .belico-pasiva-arma, .belico-pagina .belico-pasivas--compacta .belico-pasiva-condiciones { margin-top: 2px; font-size: 12px; }
.belico-pagina .belico-pasivas-armas { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-2); margin-bottom: var(--opg-espacio-3); }
.belico-pagina .belico-pasivas-armas-titulo { font: 700 13px/1.2 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-morado-texto); }
.belico-pagina .belico-pasivas-armas-vacio { font-size: 12px; color: var(--opg-gris-texto); }
.belico-pagina .belico-arma-chip { display: inline-flex; margin: 0; cursor: pointer; }
.belico-pagina .belico-arma-chip input { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); }
.belico-pagina .belico-arma-chip span { padding: 5px 12px; border: 2px solid var(--opg-tinta); border-radius: 999px; background: var(--opg-crema-clara); color: var(--opg-marron); font-size: 13px; text-transform: none; }
.belico-pagina .belico-arma-chip input:checked + span { background: var(--opg-morado); border-color: var(--opg-morado-texto); color: #fff; }
.belico-pagina .belico-arma-chip input:focus-visible + span { outline: 2px solid var(--opg-naranja); outline-offset: 2px; }
.belico-pagina .belico-pasivas-buscador { display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-3); margin-bottom: var(--opg-espacio-3); }
.belico-pagina .belico-pasivas-buscador input[type="search"] { flex: 1 1 240px; width: auto; }
.belico-pagina .belico-pasivas-buscador .belico-tecnica-solo { flex: 0 0 auto; white-space: nowrap; }
.belico-pagina .belico-pasivas-buscador input[type="checkbox"] { width: auto; min-height: 0; accent-color: var(--opg-naranja); }
.belico-pagina .belico-pasiva-arma { margin: var(--opg-espacio-2) 0 0; font-size: 12px; color: var(--opg-gris-texto); }
.belico-pagina .belico-arma-estado { font-weight: 700; }
.belico-pagina .belico-pasiva-item[data-aplica="si"] .belico-arma-estado { color: var(--opg-verde-exito); }
.belico-pagina .belico-pasiva-item[data-aplica="no"] .belico-arma-estado { color: var(--opg-rojo-error); }
.belico-pagina .belico-pasiva-item[data-aplica="no"] { opacity: .75; }
.belico-pagina.belico-modo-codigos .belico-calculo-inline { display: none; }
.belico-pagina:not(.belico-modo-codigos) .belico-codigo { display: none; }
.belico-pagina .belico-vista { display: inline-flex; gap: 4px; margin: 0 0 var(--opg-espacio-3); padding: 3px; border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); }
.belico-pagina .belico-vista button { min-height: 0; padding: 6px 10px; border: 0; border-radius: 4px; background: transparent; color: var(--opg-morado-texto); font: 800 12px/1 InterMedium, var(--opg-fuente-cuerpo); cursor: pointer; }
.belico-pagina .belico-vista button[aria-pressed="true"] { background: var(--opg-morado); color: #fff; }
.belico-pagina .belico-pasivas-buscador input { width: 100%; min-height: 38px; background-color: var(--opg-crema-clara); color: var(--opg-marron); }
.belico-pagina .belico-pasiva-grupo { margin-bottom: var(--opg-espacio-3); }
.belico-pagina .belico-pasiva-grupo > summary { cursor: pointer; padding: 7px var(--opg-espacio-3); background: var(--opg-morado); color: #fff; font: 800 13px/1.2 InterMedium, var(--opg-fuente-cuerpo); text-transform: uppercase; letter-spacing: .5px; border-radius: var(--opg-radio-sm); }
.belico-pagina .belico-pasiva-marca { display: flex; align-items: center; gap: var(--opg-espacio-3); cursor: pointer; }
.belico-pagina .belico-pasiva-efecto { margin: var(--opg-espacio-2) 0 0; font-size: 13px; line-height: 1.45; }
.belico-pagina .belico-pasiva-desc { margin-top: var(--opg-espacio-2); font-size: 12px; color: var(--opg-gris-texto); }
.belico-pagina .belico-pasiva-desc summary { cursor: pointer; color: var(--opg-morado-texto); }
.belico-pagina .belico-pasiva-desc p { margin: var(--opg-espacio-2) 0 0; line-height: 1.45; }
.belico-pagina .belico-pasivas-acciones { display: inline-flex; flex-wrap: wrap; gap: var(--opg-espacio-2); align-items: center; }
.belico-pagina .belico-pasiva-item { --opg-card-acento: var(--opg-morado); display: block; min-width: 0; margin: 0; }
.belico-pagina .belico-pasiva-item input { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); }
.belico-pagina .belico-pasiva-control { display: grid; flex: 0 0 auto; place-items: center; width: 22px; height: 22px; border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); }
.belico-pagina .belico-pasiva-item input:checked + .belico-pasiva-control { border-color: var(--opg-morado-texto); background: var(--opg-morado); }
.belico-pagina .belico-pasiva-item input:checked + .belico-pasiva-control::after { content: "✓"; color: #fff; font: 800 16px/1 Arial, sans-serif; }
.belico-pagina .belico-pasiva-item:has(input:checked) { --opg-card-acento: var(--opg-naranja); background: #fff3e0; transform: translate(-2px, -2px); box-shadow: 4px 4px 0 var(--opg-tinta); }
.belico-pagina .belico-pasiva-item:focus-within { outline: 2px solid var(--opg-naranja); outline-offset: 2px; }
.belico-pagina .belico-pasiva-item strong, .belico-pasiva-item small { display: block; overflow-wrap: anywhere; }
.belico-pagina .belico-pasiva-item strong { color: var(--opg-marron); font: 15px/1.25 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-pasiva-item small { margin-top: 3px; color: var(--opg-gris-texto); font-size: 12px; line-height: 1.3; }
.belico-pagina .belico-pasivas-pie { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--opg-espacio-3); margin-top: var(--opg-espacio-4); }
.belico-pagina .belico-pasivas-pie > span { color: var(--opg-gris-texto); font-size: 13px; }
.belico-pagina #belico-pasivas-textarea:not([hidden]) { display: block; width: 100%; min-height: 100px; margin-top: var(--opg-espacio-3); padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); font: 13px/1.4 var(--opg-fuente-cuerpo); }
.belico-pagina .aviso { margin-top: var(--opg-espacio-3); }
@media (max-width: 850px) {
  .belico-pagina .belico-workspace { grid-template-columns: minmax(0, 1fr); gap: var(--opg-espacio-5); }
  .belico-pagina .belico-sidebar { padding-right: 0; border-right: 0; border-bottom: 2px solid var(--opg-tinta); }
  .belico-pagina .belico-atributos-grid .af-field { flex: 1 1 120px; }
  .belico-pagina .belico-recursos-grid .af-field { flex: 1 1 150px; }
}
@media (max-width: 700px) {
  .belico-pagina .mainBackground { padding: var(--opg-espacio-3); }
  .belico-pagina .belico-cabecera { flex-wrap: wrap; }
  .belico-pagina h1 { font-size: 23px; }
  .belico-pagina .belico-contexto .af-field { flex: 1 1 130px; }
  .belico-pagina .belico-contexto .btn-op { width: 100%; }
  .belico-pagina .belico-resultados { grid-template-columns: minmax(0, 1fr); }
  .belico-pagina .belico-utilidades-grid { grid-template-columns: minmax(0, 1fr); }
  .belico-pagina .belico-pasivas-lista { grid-template-columns: minmax(0, 1fr); }
  .belico-pagina .belico-tabs { gap: var(--opg-espacio-1); }
  .belico-pagina .belico-tabs button { padding: 9px 12px 8px; font-size: 13px; }
}
CSS;
}

function belico_js_base()
{
    return <<<'JS'
function belicoActualizarAtributos() {
    var campos = document.querySelectorAll('#belico-atributos input[data-belico-base]');
    campos.forEach(function (campo) {
        var base = Number(campo.getAttribute('data-belico-base')) || 0;
        var ajuste = Number(campo.value) || 0;
        var final = campo.closest('.af-field').querySelector('[data-belico-final]');
        if (final) {
            final.textContent = base + ajuste;
        }
    });
}

document.addEventListener('input', function (evento) {
    if (evento.target.matches('#belico-atributos input[data-belico-base]')) {
        belicoActualizarAtributos();
    }
});

function belicoMostrarTextarea(texto) {
    var ta = document.getElementById('belico-copiar-textarea');
    ta.hidden = false;
    ta.value = texto;
    ta.focus();
    ta.select();
}
JS;
}

function belico_js_ataque($uid, $fid)
{
    return 'window.belicoClaveAtaque = ' . json_encode((int) $uid . '_' . (int) $fid) . ";\n" . <<<'JS'
function belicoPlano(texto) {
    return String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

// Reconstruye el selector de técnicas según la búsqueda y el filtro (la técnica elegida siempre se conserva).
function belicoFiltrarTecnicas() {
    var select = document.getElementById('belico-tecnica');
    var buscar = document.getElementById('belico-tecnica-buscar');
    var solo = document.getElementById('belico-tecnica-solo-calculo');
    if (!select || !buscar || !solo) { return; }
    var consulta = belicoPlano(buscar.value).trim();
    var actual = select.value;
    while (select.firstChild) { select.removeChild(select.firstChild); }
    var primera = document.createElement('option');
    primera.value = '';
    primera.textContent = 'Sin técnica';
    select.appendChild(primera);
    ['Ofensivas', 'Defensivas', 'Ambientales', 'Utilidad', 'Otras'].forEach(function (grupo) {
        var items = (window.belicoTecnicas || []).filter(function (tecnica) {
            if (tecnica.grupo !== grupo) { return false; }
            if (tecnica.tid === actual) { return true; }
            if (solo.checked && !tecnica.calcula) { return false; }
            return !consulta || belicoPlano(tecnica.nombre + ' ' + tecnica.tid).indexOf(consulta) !== -1;
        });
        if (!items.length) { return; }
        var optgrupo = document.createElement('optgroup');
        optgrupo.label = grupo;
        items.forEach(function (tecnica) {
            var opcion = document.createElement('option');
            opcion.value = tecnica.tid;
            opcion.textContent = tecnica.etiqueta;
            optgrupo.appendChild(opcion);
        });
        select.appendChild(optgrupo);
    });
    select.value = actual;
}

document.addEventListener('input', function (evento) {
    if (evento.target.id === 'belico-tecnica-buscar') { belicoFiltrarTecnicas(); }
});
document.addEventListener('change', function (evento) {
    if (evento.target.id === 'belico-tecnica-solo-calculo') { belicoFiltrarTecnicas(); }
});
// Enter en el buscador (o en cualquier campo) no debe enviar el formulario ni recargar la página.
document.addEventListener('submit', function (evento) {
    if (evento.target.id === 'belico-ataque-form') { evento.preventDefault(); }
});

// Lleva la técnica elegida al tab Turno (como un bloque nuevo, o dentro del último si es instantánea).
document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-belico-usar-en-turno]');
    if (!boton) { return; }
    if (typeof belicoTurnoAgregarTecnica === 'function' && belicoTurnoAgregarTecnica(boton.dataset.belicoUsarEnTurno)) {
        belicoActivarTab('turno');
        var bloques = document.querySelectorAll('.belico-t-bloque');
        if (bloques.length) { bloques[bloques.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    } else {
        boton.textContent = 'Esta técnica no entra en un bloque de acción';
        boton.disabled = true;
    }
});

// ── Pasivas ───────────────────────────────────────────────────────────────────
function belicoPasivasMarcadas() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-belico-pasiva]:checked'));
}

function belicoNumeroBono(valor) {
    var redondeado = Math.round(valor * 100) / 100;
    return (redondeado > 0 ? '+' : '') + String(redondeado).replace('.', ',');
}

function belicoAvisoPasivas(texto) {
    var aviso = document.getElementById('belico-pasivas-aviso');
    if (!aviso) { return; }
    aviso.textContent = texto;
    window.clearTimeout(window.belicoAvisoTemporizador);
    window.belicoAvisoTemporizador = window.setTimeout(function () { aviso.textContent = ''; }, 2500);
}

// Tipos de arma marcados en el propio tab de pasivas (hay = alguno marcado).
function belicoArmasActuales() {
    var actual = { hay: false, tipos: [] };
    document.querySelectorAll('[data-belico-pasivas-tipo]:checked').forEach(function (campo) {
        actual.hay = true;
        actual.tipos.push(campo.value);
    });
    return actual;
}

// Marca cada pasiva con condición de arma según las armas marcadas en este tab.
function belicoEvaluarArmasPasivas() {
    var actual = belicoArmasActuales();
    document.querySelectorAll('.belico-pasiva-item[data-armas]').forEach(function (item) {
        var estado = item.querySelector('.belico-arma-estado');
        var requeridos = item.dataset.armas.split(',');
        // Los Wazamono son una excepción a la regla: cumplen cualquier condición de tipo de arma.
        var aplica = actual.hay && (actual.tipos.indexOf('wazamono') !== -1 || requeridos.some(function (tipo) { return actual.tipos.indexOf(tipo) !== -1; }));
        item.dataset.aplica = actual.hay ? (aplica ? 'si' : 'no') : 'no';
        if (estado) {
            estado.textContent = !actual.hay ? '(marca el tipo de arma arriba para comprobarlo)' : (aplica ? '✓ aplica con tu arma actual' : '✗ no aplica con tu arma actual');
        }
    });
    belicoActualizarPasivas(true);
}

function belicoFiltrarPasivas() {
    var campo = document.getElementById('belico-pasivas-buscar');
    if (!campo) { return; }
    var consulta = belicoPlano(campo.value).trim();
    var marca = function (id) { var c = document.getElementById(id); return !!(c && c.checked); };
    var soloAplican = marca('belico-pasivas-solo-aplican');
    var soloMarcadas = marca('belico-pasivas-solo-marcadas');
    var soloValor = marca('belico-pasivas-solo-valor');
    document.querySelectorAll('.belico-pasiva-item').forEach(function (item) {
        var coincide = !consulta || belicoPlano(item.dataset.buscar).indexOf(consulta) !== -1;
        var oculta = (soloAplican && item.dataset.aplica === 'no')
            || (soloMarcadas && !item.querySelector('[data-belico-pasiva]:checked'))
            || (soloValor && item.dataset.valor !== '1');
        item.style.display = (coincide && !oculta) ? '' : 'none';
    });
    document.querySelectorAll('.belico-pasiva-grupo').forEach(function (grupo) {
        var hay = Array.prototype.some.call(grupo.querySelectorAll('.belico-pasiva-item'), function (item) { return item.style.display !== 'none'; });
        grupo.style.display = hay ? '' : 'none';
        if ((consulta || soloMarcadas) && hay) { grupo.open = true; }
    });
}

// Suma los bonos numéricos de las pasivas marcadas. Las que no aplican con el arma elegida no suman.
function belicoBonosActivos() {
    var suma = {};
    var excluidas = 0;
    belicoPasivasMarcadas().forEach(function (campo) {
        var item = campo.closest('.belico-pasiva-item');
        if (!item) { return; }
        var bonos = [];
        try { bonos = JSON.parse(item.dataset.bonos || '[]'); } catch (e) { bonos = []; }
        if (item.dataset.aplica === 'no') { if (bonos.length) { excluidas++; } return; }
        bonos.forEach(function (bono) {
            var clave = bono.k + (bono.p ? '%' : '');
            if (!suma[clave]) { suma[clave] = { k: bono.k, p: bono.p, l: bono.l + (bono.p ? ' (%)' : ''), v: 0 }; }
            suma[clave].v += bono.v;
        });
    });
    return { suma: suma, excluidas: excluidas };
}

// Texto para el post: agrupado por origen y con los valores ya calculados entre paréntesis.
function belicoTextoPasivas() {
    var marcadas = belicoPasivasMarcadas();
    if (!marcadas.length) { return ''; }
    var texto = '[spoiler=Pasivas del turno]\n';
    var origenActual = null;
    marcadas.forEach(function (pasiva) {
        var origen = pasiva.dataset.belicoPasivaOrigen || 'Otras';
        if (origen !== origenActual) { texto += '[b]' + origen + '[/b]\n'; origenActual = origen; }
        var efecto = pasiva.dataset.belicoPasivaEfecto;
        texto += '[tecnica=' + pasiva.value + ']' + (efecto ? ' (' + efecto + ')' : '') + '\n';
    });
    return texto + '[/spoiler]';
}

// Bonos de las pasivas marcadas: se suman al resumen de la ofensiva (Ataque) y a Reflejos (Reacción).
function belicoAplicarPasivasAtaque() {
    var suma = belicoBonosActivos().suma;
    var valor = function (clave) { return suma[clave] ? suma[clave].v : 0; };
    var resumen = document.getElementById('belico-resumen');
    if (resumen) {
        var baseDano = Number(resumen.dataset.baseDano) || 0;
        var baseVerdadero = Number(resumen.dataset.baseVerdadero) || 0;
        var baseTa = Number(resumen.dataset.baseTa) || 0;
        var poner = function (clave, base, extra, sufijo) {
            var destino = resumen.querySelector('[data-belico-resumen="' + clave + '"]');
            if (!destino) { return; }
            destino.textContent = extra ? '→ ' + (base + extra) + ' con pasivas (' + belicoNumeroBono(extra) + (sufijo || '') + ')' : '';
        };
        poner('dano', baseDano, valor('dano'));
        poner('verdadero', baseVerdadero, valor('dano verdadero'));
        poner('ta', baseTa, valor('tasa de acierto'));
        var filaVerdadero = resumen.querySelector('[data-belico-fila="verdadero"]');
        if (filaVerdadero) { filaVerdadero.hidden = !(baseVerdadero || valor('dano verdadero')); }
    }
    var nota = document.getElementById('belico-nota-reflejos');
    if (!nota) { return; }
    var reflejos = valor('reflejos');
    nota.textContent = '';
    if (!reflejos) { nota.hidden = true; return; }
    var campo = document.getElementById('belico-mod-ref');
    var baseRef = campo ? (Number(campo.dataset.belicoBase) || 0) + (Number(campo.value) || 0) : 0;
    nota.hidden = false;
    nota.appendChild(document.createTextNode('Pasivas marcadas: ' + belicoNumeroBono(reflejos) + ' Reflejos (con pasivas: ' + (baseRef + reflejos) + '). '));
    var usar = document.createElement('button');
    usar.type = 'button';
    usar.className = 'opg-chip';
    usar.dataset.belicoUsarRef = String(baseRef + reflejos);
    usar.textContent = 'Usar este valor';
    nota.appendChild(usar);
}

// Tira bajo las pestañas con lo que llevas: arma, técnica, tipo de arma de las pasivas y pasivas con sus bonos.
function belicoActualizarLlevas() {
    var caja = document.getElementById('belico-llevas');
    if (!caja) { return; }
    var partes = [];
    var formulario = document.getElementById('belico-ataque-form');
    if (formulario && formulario.elements.contexto_arma) {
        var config = formulario.elements.contexto_arma;
        var nombres = [];
        ['arma_uno', 'arma_dos', 'arma_tres'].forEach(function (nombre) {
            var campo = formulario.elements[nombre];
            if (campo && campo.value) { nombres.push(campo.options[campo.selectedIndex].text); }
        });
        partes.push('Arma: ' + config.options[config.selectedIndex].text + (nombres.length ? ' (' + nombres.join(', ') + ')' : ''));
        var tecnica = formulario.elements.tecnica;
        if (tecnica && tecnica.value) { partes.push('Técnica: ' + tecnica.options[tecnica.selectedIndex].text.replace(/^◆\s*/, '')); }
    }
    var tipos = Array.prototype.map.call(document.querySelectorAll('[data-belico-pasivas-tipo]:checked'), function (campo) {
        return campo.nextElementSibling ? campo.nextElementSibling.textContent.replace(/\s*\(\d+\)\s*$/, '') : campo.value;
    });
    if (tipos.length) { partes.push('En mano (pasivas): ' + tipos.join(', ')); }
    var marcadas = belicoPasivasMarcadas();
    if (marcadas.length) {
        var activos = belicoBonosActivos();
        var bonos = Object.keys(activos.suma).sort().map(function (clave) { return activos.suma[clave].l + ' ' + belicoNumeroBono(activos.suma[clave].v) + (activos.suma[clave].p ? '%' : ''); });
        partes.push(marcadas.length + (marcadas.length === 1 ? ' pasiva' : ' pasivas') + (bonos.length ? ' (' + bonos.join(', ') + ')' : ''));
    }
    caja.textContent = partes.length ? 'Llevas: ' + partes.join(' · ') : '';
}

// Contador, chips de lo marcado, bonos sumados, vista previa del texto y recuerdo de la selección.
function belicoActualizarPasivas(sinGuardar) {
    var marcadas = belicoPasivasMarcadas();
    var resumen = document.getElementById('belico-pasivas-resumen');
    if (resumen) { resumen.textContent = marcadas.length + (marcadas.length === 1 ? ' pasiva seleccionada' : ' pasivas seleccionadas'); }

    var chips = document.getElementById('belico-pasivas-chips');
    if (chips) {
        while (chips.firstChild) { chips.removeChild(chips.firstChild); }
        marcadas.forEach(function (campo) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'belico-chip-sel';
            chip.dataset.belicoQuitar = campo.value;
            chip.title = 'Quitar';
            chip.textContent = (campo.dataset.belicoPasivaNombre || campo.value) + ' ✕';
            chips.appendChild(chip);
        });
    }

    var bonosCaja = document.getElementById('belico-pasivas-bonos');
    if (bonosCaja) {
        while (bonosCaja.firstChild) { bonosCaja.removeChild(bonosCaja.firstChild); }
        if (marcadas.length) {
            var activos = belicoBonosActivos();
            var claves = Object.keys(activos.suma).sort();
            if (!claves.length) {
                bonosCaja.appendChild(document.createTextNode('Ninguna de las marcadas suma un bono numérico.'));
            } else {
                bonosCaja.appendChild(document.createTextNode('Bonos: '));
                claves.forEach(function (clave) {
                    var bono = activos.suma[clave];
                    var etiqueta = document.createElement('span');
                    etiqueta.className = 'belico-bono';
                    etiqueta.appendChild(document.createTextNode(bono.l + ' '));
                    var cifra = document.createElement('strong');
                    cifra.textContent = belicoNumeroBono(bono.v) + (bono.p ? '%' : '');
                    etiqueta.appendChild(cifra);
                    bonosCaja.appendChild(etiqueta);
                });
            }
            if (activos.excluidas) {
                var nota = document.createElement('small');
                nota.textContent = ' (' + activos.excluidas + ' con bono no suman porque no aplican con tu arma)';
                bonosCaja.appendChild(nota);
            }
        }
    }

    var previa = document.getElementById('belico-pasivas-previa');
    if (previa) { previa.textContent = belicoTextoPasivas() || 'Selecciona al menos una pasiva.'; }

    belicoAplicarPasivasAtaque();
    belicoActualizarLlevas();
    belicoFiltrarPasivas();
    if (typeof belicoTurnoPasivasCambiaron === 'function') { belicoTurnoPasivasCambiaron(); }
    if (sinGuardar !== true) {
        try {
            localStorage.setItem('opg_belico_pasivas_' + window.belicoClaveAtaque, JSON.stringify(marcadas.map(function (campo) { return campo.value; })));
        } catch (e) {}
    }
}

function belicoCopiarPasivas() {
    var texto = belicoTextoPasivas();
    var salida = document.getElementById('belico-pasivas-textarea');
    var mostrar = function (contenido) {
        if (!salida) { return; }
        salida.hidden = false;
        salida.value = contenido;
        salida.focus();
        salida.select();
    };
    if (!texto) { mostrar('Selecciona al menos una pasiva para copiarla al post.'); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(function () {
            if (salida) { salida.hidden = true; }
            belicoAvisoPasivas('Copiado ✓');
        }).catch(function () { mostrar(texto); });
    } else {
        mostrar(texto);
    }
}

// Conjuntos de pasivas guardados en este navegador.
function belicoClaveConjuntos() { return 'opg_belico_pasivas_conjuntos_' + window.belicoClaveAtaque; }
function belicoLeerConjuntos() {
    try { return JSON.parse(localStorage.getItem(belicoClaveConjuntos()) || '{}') || {}; } catch (e) { return {}; }
}
function belicoPintarConjuntos(seleccionado) {
    var select = document.getElementById('belico-conjuntos');
    if (!select) { return; }
    var conjuntos = belicoLeerConjuntos();
    var nombres = Object.keys(conjuntos).sort();
    while (select.firstChild) { select.removeChild(select.firstChild); }
    var primera = document.createElement('option');
    primera.value = '';
    primera.textContent = nombres.length ? '(elige un conjunto)' : '(ninguno guardado)';
    select.appendChild(primera);
    nombres.forEach(function (nombre) {
        var opcion = document.createElement('option');
        opcion.value = nombre;
        opcion.textContent = nombre + ' (' + conjuntos[nombre].length + ')';
        select.appendChild(opcion);
    });
    select.value = seleccionado && conjuntos[seleccionado] ? seleccionado : '';
}

document.addEventListener('input', function (evento) {
    if (evento.target.id === 'belico-pasivas-buscar') { belicoFiltrarPasivas(); }
});
document.addEventListener('change', function (evento) {
    if (['belico-pasivas-solo-aplican', 'belico-pasivas-solo-marcadas', 'belico-pasivas-solo-valor'].indexOf(evento.target.id) !== -1) { belicoFiltrarPasivas(); }
    if (evento.target.matches && evento.target.matches('[data-belico-pasivas-tipo]')) {
        belicoEvaluarArmasPasivas();
        try {
            var marcados = Array.prototype.map.call(document.querySelectorAll('[data-belico-pasivas-tipo]:checked'), function (campo) { return campo.value; });
            localStorage.setItem('opg_belico_pasivas_tipos_' + window.belicoClaveAtaque, JSON.stringify(marcados));
        } catch (e) {}
    }
});
document.body.addEventListener('htmx:afterSettle', function () { belicoAplicarPasivasAtaque(); belicoActualizarLlevas(); });
document.addEventListener('change', function (evento) {
    if (evento.target.closest && evento.target.closest('#belico-ataque-form')) { belicoActualizarLlevas(); }
});
document.addEventListener('DOMContentLoaded', belicoEvaluarArmasPasivas);

var belicoPasivasAntes = null;
document.addEventListener('click', function (evento) {
    var objetivo = evento.target;
    var quitar = objetivo.closest('[data-belico-quitar]');
    if (quitar) {
        var campo = Array.prototype.filter.call(document.querySelectorAll('[data-belico-pasiva]'), function (c) { return c.value === quitar.dataset.belicoQuitar; })[0];
        if (campo) { campo.checked = false; belicoActualizarPasivas(); }
        return;
    }
    var usarRef = objetivo.closest('[data-belico-usar-ref]');
    if (usarRef) {
        var ref = document.getElementById('belico-ref');
        if (ref) { ref.value = usarRef.dataset.belicoUsarRef; ref.dispatchEvent(new Event('input', { bubbles: true })); }
        return;
    }
    var densidad = objetivo.closest('[data-belico-densidad]');
    if (densidad) {
        var seccion = document.getElementById('belico-pasivas');
        if (seccion) { seccion.classList.toggle('belico-pasivas--compacta', densidad.dataset.belicoDensidad === 'compacta'); }
        document.querySelectorAll('[data-belico-densidad]').forEach(function (boton) {
            boton.setAttribute('aria-pressed', boton.dataset.belicoDensidad === densidad.dataset.belicoDensidad ? 'true' : 'false');
        });
        try { localStorage.setItem('opg_belico_densidad', densidad.dataset.belicoDensidad); } catch (e) {}
        return;
    }
    if (objetivo.closest('#belico-limpiar-pasivas')) {
        belicoPasivasMarcadas().forEach(function (c) { c.checked = false; });
        belicoActualizarPasivas();
        return;
    }
    if (objetivo.closest('#belico-marcar-aplicables')) {
        belicoPasivasAntes = belicoPasivasMarcadas().map(function (c) { return c.value; });
        var nuevas = 0;
        document.querySelectorAll('.belico-pasiva-item[data-aplica="si"] [data-belico-pasiva]').forEach(function (c) { if (!c.checked) { c.checked = true; nuevas++; } });
        document.querySelectorAll('.belico-pasiva-grupo').forEach(function (grupo) { if (grupo.querySelector('[data-belico-pasiva]:checked')) { grupo.open = true; } });
        var deshacer = document.getElementById('belico-deshacer-pasivas');
        if (deshacer) { deshacer.hidden = false; }
        belicoActualizarPasivas();
        belicoAvisoPasivas(nuevas ? nuevas + ' marcadas' : 'No hay pasivas nuevas que apliquen');
        return;
    }
    if (objetivo.closest('#belico-deshacer-pasivas')) {
        if (belicoPasivasAntes) {
            document.querySelectorAll('[data-belico-pasiva]').forEach(function (c) { c.checked = belicoPasivasAntes.indexOf(c.value) !== -1; });
            belicoPasivasAntes = null;
            objetivo.closest('#belico-deshacer-pasivas').hidden = true;
            belicoActualizarPasivas();
        }
        return;
    }
    if (objetivo.closest('#belico-conjunto-guardar')) {
        var marcadas = belicoPasivasMarcadas().map(function (c) { return c.value; });
        if (!marcadas.length) { belicoAvisoPasivas('Marca al menos una pasiva'); return; }
        var nombre = window.prompt('Nombre del conjunto:');
        nombre = nombre ? nombre.trim() : '';
        if (!nombre) { return; }
        var conjuntos = belicoLeerConjuntos();
        conjuntos[nombre] = marcadas;
        try { localStorage.setItem(belicoClaveConjuntos(), JSON.stringify(conjuntos)); } catch (e) {}
        belicoPintarConjuntos(nombre);
        belicoAvisoPasivas('Conjunto guardado ✓');
        return;
    }
    if (objetivo.closest('#belico-conjunto-aplicar')) {
        var elegido = document.getElementById('belico-conjuntos').value;
        var guardado = belicoLeerConjuntos()[elegido];
        if (!guardado) { return; }
        document.querySelectorAll('[data-belico-pasiva]').forEach(function (c) { c.checked = guardado.indexOf(c.value) !== -1; });
        document.querySelectorAll('.belico-pasiva-grupo').forEach(function (grupo) { if (grupo.querySelector('[data-belico-pasiva]:checked')) { grupo.open = true; } });
        belicoActualizarPasivas();
        return;
    }
    if (objetivo.closest('#belico-conjunto-borrar')) {
        var cual = document.getElementById('belico-conjuntos').value;
        if (!cual || !window.confirm('¿Borrar el conjunto "' + cual + '"?')) { return; }
        var todos = belicoLeerConjuntos();
        delete todos[cual];
        try { localStorage.setItem(belicoClaveConjuntos(), JSON.stringify(todos)); } catch (e) {}
        belicoPintarConjuntos('');
        return;
    }
    if (objetivo.closest('#belico-copiar-pasivas')) { belicoCopiarPasivas(); }
});

// Interruptor "Sin cálculo / Con cálculo" (fórmulas tal cual o valores calculados), recordado en el navegador.
(function () {
    var clave = 'opg_belico_vista';
    function aplicar(vista) {
        var raiz = document.querySelector('.belico-pagina');
        if (!raiz) { return; }
        raiz.classList.remove('belico-modo-codigos', 'belico-modo-calculos');
        raiz.classList.add('belico-modo-' + vista);
        document.querySelectorAll('[data-belico-vista]').forEach(function (boton) {
            boton.setAttribute('aria-pressed', boton.dataset.belicoVista === vista ? 'true' : 'false');
        });
    }
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-belico-vista]');
        if (!boton) { return; }
        aplicar(boton.dataset.belicoVista);
        try { localStorage.setItem(clave, boton.dataset.belicoVista); } catch (e) {}
    });
    var guardada = 'calculos';
    try { guardada = localStorage.getItem(clave) || 'calculos'; } catch (e) {}
    if (guardada !== 'codigos' && guardada !== 'calculos') { guardada = 'calculos'; }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { aplicar(guardada); }); } else { aplicar(guardada); }
}());

// La lista se vuelve a dibujar cuando cambian los atributos: se reevalúan las armas y se conserva todo lo demás.
document.body.addEventListener('htmx:afterSwap', function (evento) {
    if (evento.target && evento.target.id === 'belico-pasivas-contenido') { belicoEvaluarArmasPasivas(); }
});

(function () {
    function restaurar() {
        var leer = function (clave) {
            try { return JSON.parse(localStorage.getItem(clave + window.belicoClaveAtaque) || '[]') || []; } catch (e) { return []; }
        };
        var guardadas = leer('opg_belico_pasivas_');
        var tipos = leer('opg_belico_pasivas_tipos_');
        document.querySelectorAll('[data-belico-pasivas-tipo]').forEach(function (campo) {
            if (tipos.indexOf(campo.value) !== -1) { campo.checked = true; }
        });
        document.querySelectorAll('[data-belico-pasiva]').forEach(function (campo) {
            if (guardadas.indexOf(campo.value) !== -1) { campo.checked = true; }
        });
        document.querySelectorAll('.belico-pasiva-grupo').forEach(function (grupo) {
            if (grupo.querySelector('[data-belico-pasiva]:checked')) { grupo.open = true; }
        });
        var densidad = 'tarjetas';
        try { densidad = localStorage.getItem('opg_belico_densidad') || 'tarjetas'; } catch (e) {}
        var seccion = document.getElementById('belico-pasivas');
        if (seccion) { seccion.classList.toggle('belico-pasivas--compacta', densidad === 'compacta'); }
        document.querySelectorAll('[data-belico-densidad]').forEach(function (boton) {
            boton.setAttribute('aria-pressed', boton.dataset.belicoDensidad === densidad ? 'true' : 'false');
        });
        belicoPintarConjuntos('');
        belicoEvaluarArmasPasivas();
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', restaurar); } else { restaurar(); }
}());

// Pone una configuración de armas en el formulario de Ataque (la del tab Turno), esperando a que se dibujen las ranuras de arma.
function belicoAtaqueFijar(config, armas) {
    var f = document.getElementById('belico-ataque-form');
    if (!f || !f.elements.contexto_arma) { return; }
    var poner = function () {
        var actual = document.getElementById('belico-ataque-form');
        ['arma_uno', 'arma_dos', 'arma_tres'].forEach(function (nombre, i) {
            var campo = actual.elements[nombre];
            if (campo && Array.prototype.some.call(campo.options, function (o) { return o.value === (armas[i] || ''); })) { campo.value = armas[i] || ''; }
        });
        actual.dispatchEvent(new Event('change', { bubbles: true }));
    };
    if (f.elements.contexto_arma.value === config) { poner(); return; }
    var anterior = f;
    var alTerminar = function () {
        if (document.getElementById('belico-ataque-form') === anterior) { return; }
        document.body.removeEventListener('htmx:afterSettle', alTerminar);
        poner();
    };
    document.body.addEventListener('htmx:afterSettle', alTerminar);
    f.elements.contexto_arma.value = config;
    f.elements.contexto_arma.dispatchEvent(new Event('change', { bubbles: true }));
}

// Recuerda la configuración de armas y la técnica en este navegador.
(function () {
    var clave = 'opg_belico_ataque_' + window.belicoClaveAtaque;
    var campos = ['contexto_arma', 'arma_uno', 'arma_dos', 'arma_tres', 'tecnica', 'distancia'];
    var restaurando = false;
    var guardado = null;
    try { guardado = JSON.parse(localStorage.getItem(clave) || 'null'); } catch (e) { guardado = null; }

    function formulario() { return document.getElementById('belico-ataque-form'); }

    function guardar() {
        var f = formulario();
        if (!f || restaurando) { return; }
        var datos = {};
        campos.forEach(function (nombre) { if (f.elements[nombre]) { datos[nombre] = f.elements[nombre].value; } });
        try { localStorage.setItem(clave, JSON.stringify(datos)); } catch (e) {}
    }

    document.addEventListener('change', function (evento) {
        if (evento.target.closest && evento.target.closest('#belico-ataque-form')) { guardar(); }
    });

    function ponerValores() {
        var f = formulario();
        if (!f) { return; }
        ['arma_uno', 'arma_dos', 'arma_tres', 'tecnica'].forEach(function (nombre) {
            var campo = f.elements[nombre];
            if (campo && guardado[nombre] !== undefined && Array.prototype.some.call(campo.options, function (o) { return o.value === guardado[nombre]; })) {
                campo.value = guardado[nombre];
            }
        });
        if (f.elements.distancia && guardado.distancia !== undefined) { f.elements.distancia.value = guardado.distancia; }
        restaurando = false;
        f.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function iniciar() {
        var f = formulario();
        if (!f || !guardado || !guardado.contexto_arma) { return; }
        var hayAlgo = campos.some(function (nombre) { return guardado[nombre] && guardado[nombre] !== 'sin_arma'; });
        if (!hayAlgo) { return; }
        restaurando = true;
        var config = f.elements.contexto_arma;
        if (config.value === guardado.contexto_arma) { ponerValores(); return; }
        // Cambiar la configuración vuelve a dibujar el formulario (con sus ranuras de arma): se esperan los valores hasta entonces.
        var anterior = f;
        var alTerminar = function () {
            if (formulario() === anterior) { return; }
            document.body.removeEventListener('htmx:afterSettle', alTerminar);
            ponerValores();
        };
        document.body.addEventListener('htmx:afterSettle', alTerminar);
        config.value = guardado.contexto_arma;
        config.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }
}());
JS;
}

function belico_js_turno($tecnicas)
{
    $lista = array();
    foreach ($tecnicas as $tecnica) {
        $lista[] = array(
            'tid' => (string) $tecnica['tid'],
            'nombre' => (string) $tecnica['nombre'],
            'clase' => (string) $tecnica['clase'],
            'tipo' => (string) $tecnica['tipo'],
            'grupo' => $tecnica['grupo'],
            'calcula' => (bool) $tecnica['calcula'],
            'etiqueta' => belico_etiqueta_tecnica($tecnica),
            'rol' => opg_belico_rol_tecnica($tecnica),
            'instantanea' => opg_belico_es_instantanea($tecnica),
            'mantenida' => opg_belico_es_mantenida($tecnica),
            'pasiva' => belico_es_pasiva($tecnica),
            'enfriamiento' => opg_belico_numero_coste($tecnica['enfriamiento']),
        );
    }
    $json = json_encode($lista, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json === false) { $json = '[]'; }

    return 'window.belicoTecnicas = ' . $json . ";\nwindow.belicoAcciones = " . json_encode(opg_belico_acciones_turno(), JSON_UNESCAPED_UNICODE) . ";\n" . belico_js_base() . <<<'JS'

function belicoActivarTab(nombre) {
    var tabs = document.querySelectorAll('[data-belico-tab]');
    var paneles = document.querySelectorAll('[data-belico-panel]');
    tabs.forEach(function (tab) {
        var activo = tab.dataset.belicoTab === nombre;
        tab.setAttribute('aria-selected', activo ? 'true' : 'false');
        tab.tabIndex = activo ? 0 : -1;
    });
    paneles.forEach(function (panel) {
        panel.hidden = panel.dataset.belicoPanel !== nombre;
    });
}

// ── Turno ─────────────────────────────────────────────────────────────────────
var belicoTurno = null;
var belicoTurnoUltimo = null;
var belicoTurnoTextoEditado = false;
var belicoTurnoSecuencia = 0;
var belicoTurnoTemporizador = null;

function belicoEl(etiqueta, clase, texto) {
    var e = document.createElement(etiqueta);
    if (clase) { e.className = clase; }
    if (texto !== undefined) { e.textContent = texto; }
    return e;
}

function belicoTecnicaPorId(tid) {
    return (window.belicoTecnicas || []).filter(function (t) { return t.tid === tid; })[0];
}

function belicoTecnicasPor(filtro) {
    return (window.belicoTecnicas || []).filter(filtro);
}

function belicoTurnoClave() { return 'opg_belico_turno_' + window.belicoClaveAtaque; }

function belicoPasivasDelTurno() {
    return typeof belicoPasivasMarcadas === 'function' ? belicoPasivasMarcadas().map(function (c) { return c.value; }) : [];
}

function belicoTurnoNuevo() {
    var config = 'sin_arma';
    var armas = ['', '', ''];
    var f = document.getElementById('belico-ataque-form');
    if (f && f.elements.contexto_arma) {
        config = f.elements.contexto_arma.value;
        armas = ['arma_uno', 'arma_dos', 'arma_tres'].map(function (n) { return f.elements[n] ? f.elements[n].value : ''; });
    }
    var recurso = function (id) { var c = document.getElementById(id); return c ? Number(c.defaultValue) || 0 : 0; };
    return {
        nombre: 'Turno',
        equipo: { configuracion: config, armas: armas },
        recursos: { vida: recurso('belico-turno-vida'), energia: recurso('belico-turno-energia'), haki: recurso('belico-turno-haki') },
        adicionales_extra: 0,
        enfriamientos: {},
        continuan: [],
        instantaneas: [],
        bloques: []
    };
}

function belicoTurnoValido(t) {
    return t && typeof t === 'object' && t.equipo && Array.isArray(t.bloques) && Array.isArray(t.continuan) && Array.isArray(t.instantaneas) && t.recursos
        && t.bloques.every(function (b) { return b && ['tecnica', 'sin_tecnica', 'utilidad'].indexOf(b.tipo) !== -1 && Array.isArray(b.acciones) && Array.isArray(b.utilidades); });
}

function belicoTurnoGuardar() {
    try { localStorage.setItem(belicoTurnoClave(), JSON.stringify(belicoTurno)); } catch (e) {}
}

function belicoTurnoCargar() {
    var guardado = null;
    try { guardado = JSON.parse(localStorage.getItem(belicoTurnoClave()) || 'null'); } catch (e) { guardado = null; }
    belicoTurno = belicoTurnoValido(guardado) ? guardado : belicoTurnoNuevo();
    if (!belicoTurno.enfriamientos || typeof belicoTurno.enfriamientos !== 'object' || Array.isArray(belicoTurno.enfriamientos)) { belicoTurno.enfriamientos = {}; }
}

// Cada cambio guarda el turno y pide el cálculo al servidor (con un pequeño retardo).
function belicoTurnoCambio() {
    belicoTurnoGuardar();
    window.clearTimeout(belicoTurnoTemporizador);
    belicoTurnoTemporizador = window.setTimeout(belicoTurnoEnviar, 300);
}

function belicoTurnoEnviar() {
    var atributos = document.getElementById('belico-atributos');
    if (!atributos || !belicoTurno) { return; }
    var datos = new FormData(atributos);
    datos.append('accion', 'turno');
    datos.append('turno', JSON.stringify(belicoTurnoPayload()));
    var secuencia = ++belicoTurnoSecuencia;
    fetch('belico.php', { method: 'POST', body: datos, headers: { 'HX-Request': 'true' }, credentials: 'same-origin' })
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (resultado) { if (secuencia === belicoTurnoSecuencia) { belicoTurnoAplicar(resultado); } })
        .catch(function () {
            var avisos = document.getElementById('belico-turno-avisos');
            if (avisos) { avisos.textContent = 'No se pudo calcular el turno. Revisa tu conexión e inténtalo de nuevo.'; }
        });
}

function belicoTurnoAvisoCopia(texto) {
    var aviso = document.getElementById('belico-turno-aviso');
    if (!aviso) { return; }
    aviso.textContent = texto;
    window.clearTimeout(window.belicoTurnoAvisoTemporizador);
    window.belicoTurnoAvisoTemporizador = window.setTimeout(function () { aviso.textContent = ''; }, 2500);
}

// ── Pintado de la cabecera y las listas ──────────────────────────────────────
function belicoTurnoPintarCabecera() {
    var poner = function (id, valor) { var c = document.getElementById(id); if (c) { c.value = valor; } };
    poner('belico-turno-config', belicoTurno.equipo.configuracion);
    poner('belico-turno-vida', belicoTurno.recursos.vida);
    poner('belico-turno-energia', belicoTurno.recursos.energia);
    poner('belico-turno-haki', belicoTurno.recursos.haki);
    poner('belico-turno-extra', belicoTurno.adicionales_extra);
    var cantidad = { sin_arma: 0, una_mano: 1, dos_manos: 1, dos_armas: 2, tres_armas: 3 }[belicoTurno.equipo.configuracion] || 0;
    ['turno_arma_uno', 'turno_arma_dos', 'turno_arma_tres'].forEach(function (nombre, i) {
        var select = document.getElementById('belico-' + nombre);
        if (!select) { return; }
        var valor = belicoTurno.equipo.armas[i] || '';
        select.value = Array.prototype.some.call(select.options, function (o) { return o.value === valor; }) ? valor : '';
        select.closest('.af-field').hidden = i >= cantidad;
    });
}

function belicoTurnoChips(contenedorId, tids, quitar) {
    var caja = document.getElementById(contenedorId);
    if (!caja) { return; }
    while (caja.firstChild) { caja.removeChild(caja.firstChild); }
    tids.forEach(function (tid) {
        var tec = belicoTecnicaPorId(tid);
        var chip = belicoEl('button', 'belico-chip-sel', (tec ? tec.nombre : tid) + (quitar ? ' ✕' : ''));
        chip.type = 'button';
        if (quitar) { chip.dataset.belicoTurnoQuitar = quitar; chip.dataset.tid = tid; chip.title = 'Quitar'; }
        else { chip.disabled = true; }
        caja.appendChild(chip);
    });
    if (!tids.length) { caja.appendChild(belicoEl('small', '', 'Ninguna')); }
}

function belicoTurnoPintarSelect(id, marcador, tids, filtro) {
    var select = document.getElementById(id);
    if (!select) { return; }
    while (select.firstChild) { select.removeChild(select.firstChild); }
    var primera = belicoEl('option', '', marcador);
    primera.value = '';
    select.appendChild(primera);
    belicoTecnicasPor(filtro).forEach(function (t) {
        if (tids.indexOf(t.tid) !== -1) { return; }
        var op = belicoEl('option', '', t.etiqueta);
        op.value = t.tid;
        select.appendChild(op);
    });
}

function belicoTurnoPintarEnfriamientos() {
    var caja = document.getElementById('belico-turno-enfriamientos');
    if (caja) {
        while (caja.firstChild) { caja.removeChild(caja.firstChild); }
        var tids = Object.keys(belicoTurno.enfriamientos);
        tids.forEach(function (tid) {
            var tec = belicoTecnicaPorId(tid);
            var chip = belicoEl('button', 'belico-chip-sel', (tec ? tec.nombre : tid) + ' (' + belicoTurno.enfriamientos[tid] + ') ✕');
            chip.type = 'button';
            chip.title = 'Quitar del enfriamiento';
            chip.dataset.belicoTurnoQuitarEnf = tid;
            caja.appendChild(chip);
        });
        if (!tids.length) { caja.appendChild(belicoEl('small', '', 'Ninguna')); }
    }
    belicoTurnoPintarSelect('belico-turno-enfriamientos-add', '+ Añadir técnica en enfriamiento...', Object.keys(belicoTurno.enfriamientos), function (t) { return t.enfriamiento > 0 && !t.pasiva; });
}

function belicoTurnoPintarListas() {
    belicoTurnoPintarEnfriamientos();
    belicoTurnoChips('belico-turno-pasivas', belicoPasivasDelTurno(), '');
    belicoTurnoChips('belico-turno-continuan', belicoTurno.continuan, 'continuan');
    belicoTurnoChips('belico-turno-instantaneas', belicoTurno.instantaneas, 'instantaneas');
    belicoTurnoPintarSelect('belico-turno-continuan-add', '+ Añadir mantenida que continúa...', belicoTurno.continuan, function (t) { return t.mantenida && !t.pasiva; });
    belicoTurnoPintarSelect('belico-turno-instantaneas-add', '+ Añadir instantánea...', belicoTurno.instantaneas, function (t) { return t.instantanea && !t.pasiva; });
}

// ── Pintado de los bloques ───────────────────────────────────────────────────
// Cada bloque tiene un tipo y solo muestra los huecos que la guía permite para ese tipo:
//  - con técnica: una técnica ofensiva o defensiva y un único complemento (utilidad, mantenida o acción adicional)
//  - sin técnica: hasta dos acciones
//  - de utilidad: hasta dos técnicas y, opcionalmente, un bloqueo básico
// Las instantáneas (haki y recubrimientos elementales) no ocupan hueco.
var BELICO_TIPOS_BLOQUE = [['tecnica', 'Con técnica ofensiva o defensiva'], ['sin_tecnica', 'Sin técnica (hasta dos acciones)'], ['utilidad', 'De utilidad (hasta dos técnicas)']];

function belicoTurnoOpcionesTecnicas(filtro) {
    return belicoTecnicasPor(filtro).map(function (t) { return { valor: t.tid, texto: t.etiqueta }; });
}

function belicoTurnoOpcionesAcciones() {
    return Object.keys(window.belicoAcciones || {}).map(function (clave) { return { valor: clave, texto: window.belicoAcciones[clave][0] }; });
}

function belicoTurnoSelect(i, campo, indice, valor, opciones, vacio) {
    var select = document.createElement('select');
    select.dataset.turnoSlot = i + ',' + campo + ',' + indice;
    var primera = belicoEl('option', '', vacio);
    primera.value = '';
    select.appendChild(primera);
    opciones.forEach(function (o) {
        var op = belicoEl('option', '', o.texto);
        op.value = o.valor;
        select.appendChild(op);
    });
    select.value = valor || '';
    return select;
}

function belicoTurnoHueco(etiqueta) {
    var fila = belicoEl('div', 'belico-t-paso');
    fila.appendChild(belicoEl('span', 'belico-t-paso-tipo', etiqueta));
    for (var k = 1; k < arguments.length; k++) { fila.appendChild(arguments[k]); }
    return fila;
}

function belicoTurnoMarca(texto, atributo, valor, marcada) {
    var etiqueta = belicoEl('label', 'belico-t-bonos');
    var marca = document.createElement('input');
    marca.type = 'checkbox';
    marca.checked = marcada;
    marca.dataset[atributo] = valor;
    etiqueta.appendChild(marca);
    etiqueta.appendChild(document.createTextNode(' ' + texto));
    return etiqueta;
}

function belicoTurnoSelectComplemento(i, complemento) {
    var select = document.createElement('select');
    select.dataset.turnoSlot = i + ',complemento,0';
    var primera = belicoEl('option', '', 'Sin complemento');
    primera.value = '';
    select.appendChild(primera);
    var grupoUtilidad = belicoEl('optgroup');
    grupoUtilidad.label = 'Técnica de utilidad o mantenida';
    belicoTurnoOpcionesTecnicas(function (t) { return t.rol === 'utilidad' && !t.instantanea && !t.pasiva; }).forEach(function (o) {
        var op = belicoEl('option', '', o.texto);
        op.value = 'u:' + o.valor;
        grupoUtilidad.appendChild(op);
    });
    select.appendChild(grupoUtilidad);
    var grupoAccion = belicoEl('optgroup');
    grupoAccion.label = 'Acción adicional';
    belicoTurnoOpcionesAcciones().forEach(function (o) {
        var op = belicoEl('option', '', o.texto);
        op.value = 'a:' + o.valor;
        grupoAccion.appendChild(op);
    });
    select.appendChild(grupoAccion);
    select.value = complemento ? (complemento.t === 'accion' ? 'a:' + complemento.accion : 'u:' + complemento.tid) : '';
    return select;
}

function belicoTurnoFilaReaccion(i, r) {
    var fila = belicoEl('div', 'belico-t-reaccion');
    var numero = function (campo, etiqueta, valor) {
        var caja = belicoEl('label', 'belico-t-mov', etiqueta + ' ');
        var input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.value = valor;
        input.dataset.turnoReact = i + ',' + campo;
        caja.appendChild(input);
        return caja;
    };
    fila.appendChild(numero('ta', 'TA del rival', r.ta || 0));
    fila.appendChild(numero('distancia', 'Distancia (m)', r.distancia || 0));
    fila.appendChild(numero('ref_extra', 'Reflejos extra', r.ref_extra || 0));
    var sorpresa = document.createElement('select');
    sorpresa.dataset.turnoReact = i + ',sorpresa';
    [['no', 'Sin sorpresa'], ['detectado', 'Sorpresa detectada'], ['sin_detectar', 'Sorpresa sin detectar']].forEach(function (o) {
        var op = belicoEl('option', '', o[1]);
        op.value = o[0];
        sorpresa.appendChild(op);
    });
    sorpresa.value = r.sorpresa || 'no';
    fila.appendChild(sorpresa);
    fila.appendChild(belicoTurnoMarca('Corté su cadena (reinicia el +5 de Reflejos)', 'turnoReact', i + ',corto', !!r.corto));
    return fila;
}

function belicoTurnoCampoNumero(i, campo, etiqueta, valor) {
    var caja = belicoEl('label', 'belico-t-mov', etiqueta + ' ');
    var input = document.createElement('input');
    input.type = 'number';
    input.min = '0';
    input.value = valor;
    input.dataset.turnoCampo = i + ',' + campo;
    caja.appendChild(input);
    return caja;
}

function belicoTurnoPintarBloques() {
    var caja = document.getElementById('belico-turno-bloques');
    if (!caja) { return; }
    while (caja.firstChild) { caja.removeChild(caja.firstChild); }
    var pasivasTurno = belicoPasivasDelTurno();
    var principales = belicoTurnoOpcionesTecnicas(function (t) { return t.rol === 'principal' && !t.instantanea && !t.pasiva; });
    var utilidades = belicoTurnoOpcionesTecnicas(function (t) { return t.rol === 'utilidad' && !t.instantanea && !t.pasiva; });
    var acciones = belicoTurnoOpcionesAcciones();
    var nAnterior = 0;
    var nActual = 0;
    belicoTurno.bloques.forEach(function (bloque, i) {
        var art = belicoEl('article', 'belico-t-bloque' + (bloque.anterior ? ' belico-t-bloque--reaccion' : '') + (bloque.interrumpido ? ' belico-t-bloque--interrumpido' : ''));
        art.dataset.bloque = i;
        var cab = belicoEl('header', 'belico-t-bloque-cab');
        cab.appendChild(belicoEl('h4', '', bloque.anterior ? 'Reacción ' + (++nAnterior) + ' (turno anterior)' : 'Bloque ' + (++nActual)));
        var tipo = document.createElement('select');
        tipo.className = 'belico-t-tipo-select';
        tipo.dataset.turnoSlot = i + ',tipo,0';
        tipo.setAttribute('aria-label', 'Tipo de bloque');
        BELICO_TIPOS_BLOQUE.forEach(function (t) {
            var op = belicoEl('option', '', t[1]);
            op.value = t[0];
            tipo.appendChild(op);
        });
        tipo.value = bloque.tipo;
        cab.appendChild(tipo);
        var interrumpido = belicoEl('label', 'belico-t-interrumpido');
        var marcaInterrumpido = document.createElement('input');
        marcaInterrumpido.type = 'checkbox';
        marcaInterrumpido.checked = !!bloque.interrumpido;
        marcaInterrumpido.dataset.turnoInterrumpido = String(i);
        interrumpido.appendChild(marcaInterrumpido);
        interrumpido.appendChild(document.createTextNode(' Interrumpido'));
        interrumpido.title = 'Si te cortaron este bloque, sus costes se recuperan y no se cuenta';
        cab.appendChild(interrumpido);
        var controles = belicoEl('span', 'belico-t-controles');
        [['subir', '↑', 'Subir'], ['bajar', '↓', 'Bajar'], ['quitar', '✕', 'Quitar bloque']].forEach(function (c) {
            var b = belicoEl('button', 'belico-t-x', c[1]);
            b.type = 'button';
            b.title = c[2];
            b.dataset.turnoBloque = i + ',' + c[0];
            controles.appendChild(b);
        });
        cab.appendChild(controles);
        art.appendChild(cab);

        var cuerpo = belicoEl('div', 'belico-t-bloque-cuerpo');
        if (bloque.anterior) { cuerpo.appendChild(belicoTurnoFilaReaccion(i, bloque.reaccion || {})); }
        cuerpo.appendChild(belicoTurnoCampoNumero(i, 'mov_previo', 'Movimiento previo (m)', bloque.mov_previo || 0));

        if (bloque.tipo === 'tecnica') {
            cuerpo.appendChild(belicoTurnoHueco('Técnica ofensiva o defensiva', belicoTurnoSelect(i, 'principal', 0, bloque.principal, principales, 'Elige una técnica...')));
            var hueco = belicoTurnoHueco('Complemento (opcional, solo uno)', belicoTurnoSelectComplemento(i, bloque.complemento));
            if (bloque.complemento && bloque.complemento.t === 'tecnica') { hueco.appendChild(belicoTurnoMarca('sumar su bono al ataque', 'turnoBonos', String(i), bloque.aplicar_bonos !== false)); }
            cuerpo.appendChild(hueco);
        } else if (bloque.tipo === 'sin_tecnica') {
            cuerpo.appendChild(belicoTurnoHueco('Acción 1', belicoTurnoSelect(i, 'accion', 0, bloque.acciones[0], acciones, 'Elige una acción...')));
            cuerpo.appendChild(belicoTurnoHueco('Acción 2 (opcional)', belicoTurnoSelect(i, 'accion', 1, bloque.acciones[1], acciones, 'Ninguna')));
        } else {
            cuerpo.appendChild(belicoTurnoHueco('Técnica de utilidad 1', belicoTurnoSelect(i, 'utilidad', 0, bloque.utilidades[0], utilidades, 'Elige una técnica...')));
            var segunda = belicoTurnoHueco('Técnica de utilidad 2 (opcional)', belicoTurnoSelect(i, 'utilidad', 1, bloque.utilidades[1], utilidades, 'Ninguna'));
            segunda.appendChild(belicoTurnoMarca('sumar sus bonos', 'turnoBonos', String(i), bloque.aplicar_bonos !== false));
            cuerpo.appendChild(segunda);
            cuerpo.appendChild(belicoTurnoHueco('Bloqueo básico (opcional)', belicoTurnoMarca('añadir un bloqueo básico', 'turnoBloqueo', String(i), !!bloque.bloqueo)));
        }

        // Instantáneas: no ocupan hueco y se pueden poner en cualquier bloque.
        var filaInstantaneas = belicoEl('div', 'belico-t-pasivas');
        filaInstantaneas.appendChild(belicoEl('span', 'belico-t-paso-tipo', 'Instantáneas (no ocupan hueco)'));
        (bloque.instantaneas || []).forEach(function (tid) {
            var tec = belicoTecnicaPorId(tid);
            var chip = belicoEl('button', 'belico-chip-sel', (tec ? tec.nombre : tid) + ' ✕');
            chip.type = 'button';
            chip.title = 'Quitar';
            chip.dataset.turnoInstQuitar = i + ',' + tid;
            filaInstantaneas.appendChild(chip);
        });
        var agregarInstantanea = document.createElement('select');
        agregarInstantanea.dataset.turnoInstAgregar = String(i);
        var marcadorInstantanea = belicoEl('option', '', '+ instantánea...');
        marcadorInstantanea.value = '';
        agregarInstantanea.appendChild(marcadorInstantanea);
        belicoTecnicasPor(function (t) { return t.instantanea && !t.pasiva; }).forEach(function (t) {
            if ((bloque.instantaneas || []).indexOf(t.tid) !== -1) { return; }
            var op = belicoEl('option', '', t.etiqueta);
            op.value = t.tid;
            agregarInstantanea.appendChild(op);
        });
        filaInstantaneas.appendChild(agregarInstantanea);
        cuerpo.appendChild(filaInstantaneas);

        cuerpo.appendChild(belicoTurnoCampoNumero(i, 'mov_posterior', 'Movimiento posterior (m)', bloque.mov_posterior || 0));

        // Pasivas de este bloque: las del turno (se pueden quitar solo aquí) y las que se añaden solo aquí.
        var menos = bloque.pasivas_menos || [];
        var mas = bloque.pasivas_mas || [];
        var fila = belicoEl('div', 'belico-t-pasivas');
        fila.appendChild(belicoEl('span', 'belico-t-paso-tipo', 'Pasivas del bloque'));
        pasivasTurno.forEach(function (tid) {
            var tec = belicoTecnicaPorId(tid);
            var chip = belicoEl('button', 'belico-chip-sel' + (menos.indexOf(tid) !== -1 ? ' belico-chip-sel--off' : ''), (tec ? tec.nombre : tid));
            chip.type = 'button';
            chip.title = menos.indexOf(tid) !== -1 ? 'Desactivada en este bloque: clic para volver a activarla' : 'Clic para quitarla solo en este bloque';
            chip.dataset.turnoPasivaBloque = i + ',' + tid;
            fila.appendChild(chip);
        });
        mas.forEach(function (tid) {
            var tec = belicoTecnicaPorId(tid);
            var chip = belicoEl('button', 'belico-chip-sel', (tec ? tec.nombre : tid) + ' ✕');
            chip.type = 'button';
            chip.title = 'Quitar de este bloque';
            chip.dataset.turnoPasivaMas = i + ',' + tid;
            fila.appendChild(chip);
        });
        var extra = document.createElement('select');
        extra.dataset.turnoPasivaAgregar = String(i);
        var marcador = belicoEl('option', '', '+ pasiva solo aquí...');
        marcador.value = '';
        extra.appendChild(marcador);
        belicoTecnicasPor(function (t) { return t.pasiva; }).forEach(function (t) {
            if (pasivasTurno.indexOf(t.tid) !== -1 || mas.indexOf(t.tid) !== -1) { return; }
            var op = belicoEl('option', '', t.nombre);
            op.value = t.tid;
            extra.appendChild(op);
        });
        fila.appendChild(extra);
        cuerpo.appendChild(fila);

        var detalle = document.createElement('input');
        detalle.type = 'text';
        detalle.maxLength = 300;
        detalle.placeholder = 'Detalle del bloque (objetivo, narración...)';
        detalle.value = bloque.detalle || '';
        detalle.dataset.turnoCampo = i + ',detalle';
        cuerpo.appendChild(detalle);
        var res = belicoEl('div', 'belico-t-resultado');
        res.dataset.turnoResultado = String(i);
        cuerpo.appendChild(res);
        art.appendChild(cuerpo);
        caja.appendChild(art);
    });
    if (!belicoTurno.bloques.length) {
        caja.appendChild(belicoEl('p', 'belico-t-vacio', 'Añade tu primer bloque con los botones de abajo.'));
    }
    if (belicoTurnoUltimo) { belicoTurnoAplicar(belicoTurnoUltimo, true); }
}

// ── Resultado del servidor ───────────────────────────────────────────────────
function belicoTurnoAplicar(resultado, sinTexto) {
    belicoTurnoUltimo = resultado;
    var inicio = document.getElementById('belico-turno-inicio');
    if (inicio) { inicio.innerHTML = resultado.inicio_html || ''; }
    if (resultado.bloques && resultado.bloques.length === belicoTurno.bloques.length) {
        resultado.bloques.forEach(function (bloque, i) {
            var destino = document.querySelector('[data-turno-resultado="' + i + '"]');
            if (!destino) { return; }
            destino.innerHTML = bloque.html;
            (bloque.avisos || []).forEach(function (aviso) {
                var p = belicoEl('p', 'belico-t-aviso', '⚠ ' + aviso);
                destino.appendChild(p);
            });
        });
    }
    var avisos = document.getElementById('belico-turno-avisos');
    if (avisos) {
        while (avisos.firstChild) { avisos.removeChild(avisos.firstChild); }
        (resultado.avisos || []).forEach(function (aviso) { avisos.appendChild(belicoEl('p', 'belico-t-aviso', '⚠ ' + aviso)); });
    }
    var resumen = document.getElementById('belico-turno-resumen');
    if (resumen) { resumen.innerHTML = resultado.resumen_html || ''; }
    var texto = document.getElementById('belico-turno-texto');
    if (texto && !sinTexto && !belicoTurnoTextoEditado) { texto.value = resultado.bbcode || ''; }
}

function belicoTurnoRepintarTodo() {
    belicoTurnoPintarCabecera();
    belicoTurnoPintarListas();
    belicoTurnoPintarBloques();
}

// Se llama desde la pestaña Pasivas cuando cambia la selección.
function belicoTurnoPasivasCambiaron() {
    if (!belicoTurno) { return; }
    belicoTurnoPintarListas();
    belicoTurnoPintarBloques();
    belicoTurnoCambio();
}

// Un bloque nuevo: solo guarda lo que su tipo permite (ver el comentario de "Pintado de los bloques").
function belicoTurnoBloqueNuevo(tipo) {
    var bloque = {
        tipo: 'tecnica', mov_previo: 0, mov_posterior: 0, principal: '', complemento: null, acciones: ['', ''], utilidades: ['', ''],
        bloqueo: false, aplicar_bonos: true, instantaneas: [], pasivas_mas: [], pasivas_menos: [], detalle: ''
    };
    if (tipo === 'sin_tecnica' || tipo === 'golpe' || tipo === 'mover') { bloque.tipo = 'sin_tecnica'; }
    if (tipo === 'utilidad') { bloque.tipo = 'utilidad'; }
    if (tipo === 'golpe') { bloque.acciones[0] = 'golpe'; }
    if (tipo === 'mover') { bloque.mov_previo = 10; }
    if (tipo === 'reaccion') { bloque.anterior = true; bloque.reaccion = { ta: 0, distancia: 0, sorpresa: 'no', ref_extra: 0, corto: false }; }
    return bloque;
}

// Cambiar el tipo reinicia los huecos (instantáneas, movimiento, pasivas y detalle se conservan).
function belicoTurnoCambiarTipo(bloque, tipo) {
    var lleno = bloque.principal || bloque.complemento || bloque.acciones.some(Boolean) || bloque.utilidades.some(Boolean) || bloque.bloqueo;
    if (lleno && !window.confirm('Al cambiar el tipo de bloque se quitan sus técnicas y acciones. ¿Continuar?')) { return false; }
    bloque.tipo = tipo;
    bloque.principal = '';
    bloque.complemento = null;
    bloque.acciones = ['', ''];
    bloque.utilidades = ['', ''];
    bloque.bloqueo = false;
    return true;
}

// Lleva una técnica de otra pestaña (Ataque) al turno, en el hueco que le corresponde.
function belicoTurnoAgregarTecnica(tid) {
    var tec = belicoTecnicaPorId(tid);
    if (!belicoTurno || !tec || tec.pasiva) { return false; }
    var ultimo = belicoTurno.bloques[belicoTurno.bloques.length - 1];
    if (tec.instantanea) {
        if (!ultimo) { ultimo = belicoTurnoBloqueNuevo('sin_tecnica'); belicoTurno.bloques.push(ultimo); }
        ultimo.instantaneas.push(tid);
    } else if (tec.rol === 'principal') {
        var nuevo = belicoTurnoBloqueNuevo('tecnica');
        nuevo.principal = tid;
        belicoTurno.bloques.push(nuevo);
    } else if (tec.rol === 'utilidad') {
        if (ultimo && ultimo.tipo === 'tecnica' && !ultimo.complemento && ultimo.principal) {
            ultimo.complemento = { t: 'tecnica', tid: tid };
        } else if (ultimo && ultimo.tipo === 'utilidad' && !ultimo.utilidades[1]) {
            ultimo.utilidades[ultimo.utilidades[0] ? 1 : 0] = tid;
        } else {
            var bloqueUtilidad = belicoTurnoBloqueNuevo('utilidad');
            bloqueUtilidad.utilidades[0] = tid;
            belicoTurno.bloques.push(bloqueUtilidad);
        }
    } else {
        return false;
    }
    belicoTurnoPintarBloques();
    belicoTurnoCambio();
    return true;
}

// Los huecos del bloque en el orden en que se escriben: instantáneas, complemento de utilidad, principal, acción adicional.
function belicoTurnoPasosDe(b) {
    var pasos = [];
    (b.instantaneas || []).forEach(function (tid) { pasos.push({ tid: tid }); });
    var bonos = b.aplicar_bonos !== false;
    if (b.tipo === 'tecnica') {
        if (b.complemento && b.complemento.t === 'tecnica' && b.complemento.tid) { pasos.push({ tid: b.complemento.tid, aplicar_bonos: bonos }); }
        if (b.principal) { pasos.push({ tid: b.principal }); }
        if (b.complemento && b.complemento.t === 'accion' && b.complemento.accion) { pasos.push({ accion: b.complemento.accion }); }
    } else if (b.tipo === 'sin_tecnica') {
        (b.acciones || []).forEach(function (a) { if (a) { pasos.push({ accion: a }); } });
    } else {
        (b.utilidades || []).forEach(function (u) { if (u) { pasos.push({ tid: u, aplicar_bonos: bonos }); } });
        if (b.bloqueo) { pasos.push({ accion: 'bloqueo' }); }
    }
    return pasos;
}

function belicoTurnoPayload() {
    return {
        nombre: belicoTurno.nombre,
        equipo: belicoTurno.equipo,
        recursos: belicoTurno.recursos,
        adicionales_extra: belicoTurno.adicionales_extra,
        enfriamientos: belicoTurno.enfriamientos,
        continuan: belicoTurno.continuan,
        instantaneas: belicoTurno.instantaneas,
        pasivas: belicoPasivasDelTurno(),
        bloques: belicoTurno.bloques.map(function (b) {
            return {
                anterior: !!b.anterior, reaccion: b.reaccion || {}, interrumpido: !!b.interrumpido,
                mov_previo: b.mov_previo || 0, mov_posterior: b.mov_posterior || 0, pasos: belicoTurnoPasosDe(b),
                pasivas_mas: b.pasivas_mas || [], pasivas_menos: b.pasivas_menos || [], detalle: b.detalle || ''
            };
        })
    };
}

// ── Eventos del turno ────────────────────────────────────────────────────────
function belicoPar(texto) { var p = String(texto).split(','); return [Number(p[0]), p.slice(1).join(',')]; }

document.addEventListener('click', function (evento) {
    var objetivo = evento.target;
    var tab = objetivo.closest('[data-belico-tab]');
    if (tab) { belicoActivarTab(tab.dataset.belicoTab); return; }
    var ir = objetivo.closest('[data-belico-ir]');
    if (ir) { belicoActivarTab(ir.dataset.belicoIr); return; }
    if (!belicoTurno || !objetivo.closest('#belico-turno')) { return; }

    var nuevo = objetivo.closest('[data-belico-turno-nuevo]');
    if (nuevo) {
        var nuevoBloque = belicoTurnoBloqueNuevo(nuevo.dataset.belicoTurnoNuevo);
        if (nuevoBloque.anterior) {
            // Las reacciones al turno anterior van antes de los bloques de este turno.
            var primeroActual = belicoTurno.bloques.findIndex(function (b) { return !b.anterior; });
            belicoTurno.bloques.splice(primeroActual === -1 ? belicoTurno.bloques.length : primeroActual, 0, nuevoBloque);
        } else {
            belicoTurno.bloques.push(nuevoBloque);
        }
        belicoTurnoUltimo = null;
        belicoTurnoPintarBloques();
        belicoTurnoCambio();
        return;
    }
    var quitarEnf = objetivo.closest('[data-belico-turno-quitar-enf]');
    if (quitarEnf) {
        delete belicoTurno.enfriamientos[quitarEnf.dataset.belicoTurnoQuitarEnf];
        belicoTurnoPintarListas();
        belicoTurnoCambio();
        return;
    }
    var quitarLista = objetivo.closest('[data-belico-turno-quitar]');
    if (quitarLista) {
        var lista = belicoTurno[quitarLista.dataset.belicoTurnoQuitar];
        var posicion = lista.indexOf(quitarLista.dataset.tid);
        if (posicion !== -1) { lista.splice(posicion, 1); }
        belicoTurnoPintarListas();
        belicoTurnoCambio();
        return;
    }
    var bloqueAccion = objetivo.closest('[data-turno-bloque]');
    if (bloqueAccion) {
        var datos = belicoPar(bloqueAccion.dataset.turnoBloque);
        var bloques = belicoTurno.bloques;
        if (datos[1] === 'quitar') { bloques.splice(datos[0], 1); }
        if (datos[1] === 'subir' && datos[0] > 0) { bloques.splice(datos[0] - 1, 0, bloques.splice(datos[0], 1)[0]); }
        if (datos[1] === 'bajar' && datos[0] < bloques.length - 1) { bloques.splice(datos[0] + 1, 0, bloques.splice(datos[0], 1)[0]); }
        belicoTurnoUltimo = null;
        belicoTurnoPintarBloques();
        belicoTurnoCambio();
        return;
    }
    var instQuitar = objetivo.closest('[data-turno-inst-quitar]');
    if (instQuitar) {
        var iq = belicoPar(instQuitar.dataset.turnoInstQuitar);
        var listaInst = belicoTurno.bloques[iq[0]].instantaneas;
        var posInst = listaInst.indexOf(iq[1]);
        if (posInst !== -1) { listaInst.splice(posInst, 1); }
        belicoTurnoPintarBloques();
        belicoTurnoCambio();
        return;
    }
    var pasivaBloque = objetivo.closest('[data-turno-pasiva-bloque]');
    if (pasivaBloque) {
        var p = belicoPar(pasivaBloque.dataset.turnoPasivaBloque);
        var menos = belicoTurno.bloques[p[0]].pasivas_menos = belicoTurno.bloques[p[0]].pasivas_menos || [];
        var pos = menos.indexOf(p[1]);
        if (pos === -1) { menos.push(p[1]); } else { menos.splice(pos, 1); }
        belicoTurnoPintarBloques();
        belicoTurnoCambio();
        return;
    }
    var pasivaMas = objetivo.closest('[data-turno-pasiva-mas]');
    if (pasivaMas) {
        var m = belicoPar(pasivaMas.dataset.turnoPasivaMas);
        var mas = belicoTurno.bloques[m[0]].pasivas_mas || [];
        var pm = mas.indexOf(m[1]);
        if (pm !== -1) { mas.splice(pm, 1); }
        belicoTurno.bloques[m[0]].pasivas_mas = mas;
        belicoTurnoPintarBloques();
        belicoTurnoCambio();
        return;
    }
    if (objetivo.closest('#belico-turno-siguiente')) {
        if (!belicoTurnoUltimo || !belicoTurnoUltimo.siguiente) { belicoTurnoAvisoCopia('Espera a que se calcule el turno'); return; }
        if (!window.confirm('Se pasan tus recursos finales y las mantenidas al turno nuevo, y se vacían los bloques. ¿Continuar?')) { return; }
        var siguiente = belicoTurnoUltimo.siguiente;
        belicoTurno.continuan = siguiente.continuan.slice();
        belicoTurno.recursos.energia = siguiente.energia;
        belicoTurno.recursos.haki = siguiente.haki;
        belicoTurno.instantaneas = [];
        belicoTurno.enfriamientos = siguiente.enfriamientos || {};
        belicoTurno.bloques = [];
        belicoTurnoUltimo = null;
        belicoTurnoTextoEditado = false;
        belicoTurnoRepintarTodo();
        belicoTurnoCambio();
        belicoTurnoAvisoCopia('Turno nuevo listo ✓');
        return;
    }
    if (objetivo.closest('#belico-turno-limpiar')) {
        if (!window.confirm('¿Empezar un turno nuevo? Se borran los bloques de este turno.')) { return; }
        belicoTurno = belicoTurnoNuevo();
        belicoTurnoUltimo = null;
        belicoTurnoTextoEditado = false;
        belicoTurnoRepintarTodo();
        belicoTurnoCambio();
        return;
    }
    if (objetivo.closest('#belico-turno-regenerar')) {
        belicoTurnoTextoEditado = false;
        objetivo.closest('#belico-turno-regenerar').hidden = true;
        if (belicoTurnoUltimo) { document.getElementById('belico-turno-texto').value = belicoTurnoUltimo.bbcode || ''; }
        return;
    }
    if (objetivo.closest('#belico-turno-copiar')) {
        var texto = document.getElementById('belico-turno-texto');
        if (!texto || !texto.value) { belicoTurnoAvisoCopia('No hay nada que copiar todavía'); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto.value).then(function () { belicoTurnoAvisoCopia('Copiado ✓'); }).catch(function () { texto.focus(); texto.select(); });
        } else { texto.focus(); texto.select(); }
    }
});

document.addEventListener('change', function (evento) {
    var objetivo = evento.target;
    if (objetivo.matches('[data-belico-pasiva]') && typeof belicoActualizarPasivas === 'function') { belicoActualizarPasivas(); }
    if (!belicoTurno || !objetivo.closest || !objetivo.closest('#belico-turno')) { return; }
    var id = objetivo.id;
    if (id === 'belico-turno-config') {
        belicoTurno.equipo.configuracion = objetivo.value;
        belicoTurnoPintarCabecera();
        if (typeof belicoAtaqueFijar === 'function') { belicoAtaqueFijar(belicoTurno.equipo.configuracion, belicoTurno.equipo.armas); }
    } else if (id === 'belico-turno_arma_uno' || id === 'belico-turno_arma_dos' || id === 'belico-turno_arma_tres') {
        belicoTurno.equipo.armas[['belico-turno_arma_uno', 'belico-turno_arma_dos', 'belico-turno_arma_tres'].indexOf(id)] = objetivo.value;
        if (typeof belicoAtaqueFijar === 'function') { belicoAtaqueFijar(belicoTurno.equipo.configuracion, belicoTurno.equipo.armas); }
    } else if (id === 'belico-turno-enfriamientos-add') {
        if (!objetivo.value) { return; }
        var tecEnf = belicoTecnicaPorId(objetivo.value);
        belicoTurno.enfriamientos[objetivo.value] = tecEnf ? tecEnf.enfriamiento : 1;
        belicoTurnoPintarListas();
    } else if (id === 'belico-turno-continuan-add' || id === 'belico-turno-instantaneas-add') {
        if (!objetivo.value) { return; }
        belicoTurno[id === 'belico-turno-continuan-add' ? 'continuan' : 'instantaneas'].push(objetivo.value);
        belicoTurnoPintarListas();
    } else if (objetivo.dataset.turnoSlot !== undefined) {
        var sl = belicoPar(objetivo.dataset.turnoSlot);
        var partes = sl[1].split(',');
        var bloqueSlot = belicoTurno.bloques[sl[0]];
        var campoSlot = partes[0];
        var indiceSlot = Number(partes[1]) || 0;
        if (campoSlot === 'tipo') {
            if (!belicoTurnoCambiarTipo(bloqueSlot, objetivo.value)) { belicoTurnoPintarBloques(); return; }
            belicoTurnoPintarBloques();
        } else if (campoSlot === 'principal') {
            bloqueSlot.principal = objetivo.value;
        } else if (campoSlot === 'complemento') {
            var valorComp = objetivo.value;
            bloqueSlot.complemento = !valorComp ? null : (valorComp.charAt(0) === 'a' ? { t: 'accion', accion: valorComp.slice(2) } : { t: 'tecnica', tid: valorComp.slice(2) });
            belicoTurnoPintarBloques();
        } else if (campoSlot === 'accion') {
            bloqueSlot.acciones[indiceSlot] = objetivo.value;
        } else if (campoSlot === 'utilidad') {
            bloqueSlot.utilidades[indiceSlot] = objetivo.value;
        }
    } else if (objetivo.dataset.turnoInstAgregar !== undefined) {
        if (!objetivo.value) { return; }
        var bloqueInst = belicoTurno.bloques[Number(objetivo.dataset.turnoInstAgregar)];
        bloqueInst.instantaneas = bloqueInst.instantaneas || [];
        bloqueInst.instantaneas.push(objetivo.value);
        belicoTurnoPintarBloques();
    } else if (objetivo.dataset.turnoBloqueo !== undefined) {
        belicoTurno.bloques[Number(objetivo.dataset.turnoBloqueo)].bloqueo = objetivo.checked;
    } else if (objetivo.dataset.turnoInterrumpido !== undefined) {
        belicoTurno.bloques[Number(objetivo.dataset.turnoInterrumpido)].interrumpido = objetivo.checked;
        belicoTurnoPintarBloques();
    } else if (objetivo.dataset.turnoReact !== undefined) {
        var rc = belicoPar(objetivo.dataset.turnoReact);
        var reaccion = belicoTurno.bloques[rc[0]].reaccion = belicoTurno.bloques[rc[0]].reaccion || {};
        reaccion[rc[1]] = objetivo.type === 'checkbox' ? objetivo.checked : (objetivo.tagName === 'SELECT' ? objetivo.value : Math.max(0, Number(objetivo.value) || 0));
    } else if (objetivo.dataset.turnoBonos !== undefined) {
        belicoTurno.bloques[Number(objetivo.dataset.turnoBonos)].aplicar_bonos = objetivo.checked;
    } else if (objetivo.dataset.turnoPasivaAgregar !== undefined) {
        if (!objetivo.value) { return; }
        var bloque = belicoTurno.bloques[Number(objetivo.dataset.turnoPasivaAgregar)];
        bloque.pasivas_mas = bloque.pasivas_mas || [];
        bloque.pasivas_mas.push(objetivo.value);
        belicoTurnoPintarBloques();
    } else if (id === 'belico-turno-texto') {
        return;
    }
    belicoTurnoCambio();
});

document.addEventListener('input', function (evento) {
    var objetivo = evento.target;
    if (!belicoTurno || !objetivo.closest || !objetivo.closest('#belico-turno')) { return; }
    var id = objetivo.id;
    var numero = function () { return Math.max(0, Number(objetivo.value) || 0); };
    if (id === 'belico-turno-texto') {
        belicoTurnoTextoEditado = true;
        var boton = document.getElementById('belico-turno-regenerar');
        if (boton) { boton.hidden = false; }
        return;
    }
    if (id === 'belico-turno-vida') { belicoTurno.recursos.vida = numero(); }
    else if (id === 'belico-turno-energia') { belicoTurno.recursos.energia = numero(); }
    else if (id === 'belico-turno-haki') { belicoTurno.recursos.haki = numero(); }
    else if (id === 'belico-turno-extra') { belicoTurno.adicionales_extra = numero(); }
    else if (objetivo.dataset.turnoReact !== undefined) {
        var rc = belicoPar(objetivo.dataset.turnoReact);
        var reaccion = belicoTurno.bloques[rc[0]].reaccion = belicoTurno.bloques[rc[0]].reaccion || {};
        reaccion[rc[1]] = numero();
    } else if (objetivo.dataset.turnoCampo !== undefined) {
        var d = belicoPar(objetivo.dataset.turnoCampo);
        belicoTurno.bloques[d[0]][d[1]] = d[1] === 'detalle' ? objetivo.value : numero();
    } else { return; }
    belicoTurnoCambio();
});

document.addEventListener('keydown', function (evento) {
    var tab = evento.target.closest('[data-belico-tab]');
    if (!tab || ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(evento.key) === -1) { return; }
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-belico-tab]'));
    var indice = tabs.indexOf(tab);
    if (evento.key === 'ArrowLeft') { indice = (indice - 1 + tabs.length) % tabs.length; }
    if (evento.key === 'ArrowRight') { indice = (indice + 1) % tabs.length; }
    if (evento.key === 'Home') { indice = 0; }
    if (evento.key === 'End') { indice = tabs.length - 1; }
    evento.preventDefault();
    belicoActivarTab(tabs[indice].dataset.belicoTab);
    tabs[indice].focus();
});

// Plantillas: equipo, mantenidas, instantáneas y bloques de un turno guardados con nombre (sin los recursos actuales).
function belicoTurnoClavePlantillas() { return 'opg_belico_turno_plantillas_' + window.belicoClaveAtaque; }
function belicoTurnoLeerPlantillas() {
    try { return JSON.parse(localStorage.getItem(belicoTurnoClavePlantillas()) || '{}') || {}; } catch (e) { return {}; }
}
function belicoTurnoPintarPlantillas(seleccionada) {
    var select = document.getElementById('belico-turno-plantillas');
    if (!select) { return; }
    var plantillas = belicoTurnoLeerPlantillas();
    var nombres = Object.keys(plantillas).sort();
    while (select.firstChild) { select.removeChild(select.firstChild); }
    var primera = belicoEl('option', '', nombres.length ? '(elige una plantilla)' : '(ninguna guardada)');
    primera.value = '';
    select.appendChild(primera);
    nombres.forEach(function (nombre) {
        var op = belicoEl('option', '', nombre + ' (' + plantillas[nombre].bloques.length + ' bloques)');
        op.value = nombre;
        select.appendChild(op);
    });
    select.value = seleccionada && plantillas[seleccionada] ? seleccionada : '';
}
document.addEventListener('click', function (evento) {
    var objetivo = evento.target;
    if (!belicoTurno || !objetivo.closest('#belico-turno')) { return; }
    if (objetivo.closest('#belico-plantilla-guardar')) {
        if (!belicoTurno.bloques.length) { belicoTurnoAvisoCopia('El turno no tiene bloques'); return; }
        var nombre = window.prompt('Nombre de la plantilla:');
        nombre = nombre ? nombre.trim() : '';
        if (!nombre) { return; }
        var plantillas = belicoTurnoLeerPlantillas();
        plantillas[nombre] = { equipo: belicoTurno.equipo, continuan: belicoTurno.continuan, instantaneas: belicoTurno.instantaneas, adicionales_extra: belicoTurno.adicionales_extra, bloques: belicoTurno.bloques };
        try { localStorage.setItem(belicoTurnoClavePlantillas(), JSON.stringify(plantillas)); } catch (e) {}
        belicoTurnoPintarPlantillas(nombre);
        belicoTurnoAvisoCopia('Plantilla guardada ✓');
    } else if (objetivo.closest('#belico-plantilla-aplicar')) {
        var elegida = document.getElementById('belico-turno-plantillas').value;
        var plantilla = belicoTurnoLeerPlantillas()[elegida];
        if (!plantilla) { return; }
        if (belicoTurno.bloques.length && !window.confirm('Se reemplazan los bloques de este turno por los de la plantilla. ¿Continuar?')) { return; }
        var copia = JSON.parse(JSON.stringify(plantilla));
        if (!belicoTurnoValido({ equipo: copia.equipo, bloques: copia.bloques, continuan: copia.continuan || [], instantaneas: copia.instantaneas || [], recursos: {} })) { belicoTurnoAvisoCopia('Esa plantilla es de una versión anterior'); return; }
        belicoTurno.equipo = copia.equipo;
        belicoTurno.continuan = copia.continuan;
        belicoTurno.instantaneas = copia.instantaneas;
        belicoTurno.adicionales_extra = copia.adicionales_extra || 0;
        belicoTurno.bloques = copia.bloques;
        belicoTurnoUltimo = null;
        belicoTurnoRepintarTodo();
        belicoTurnoCambio();
        if (typeof belicoAtaqueFijar === 'function') { belicoAtaqueFijar(belicoTurno.equipo.configuracion, belicoTurno.equipo.armas); }
    } else if (objetivo.closest('#belico-plantilla-borrar')) {
        var cual = document.getElementById('belico-turno-plantillas').value;
        if (!cual || !window.confirm('¿Borrar la plantilla "' + cual + '"?')) { return; }
        var todas = belicoTurnoLeerPlantillas();
        delete todas[cual];
        try { localStorage.setItem(belicoTurnoClavePlantillas(), JSON.stringify(todas)); } catch (e) {}
        belicoTurnoPintarPlantillas('');
    }
});

function belicoTurnoIniciar() {
    belicoTurnoCargar();
    belicoTurnoPintarPlantillas('');
    belicoTurnoRepintarTodo();
    belicoTurnoEnviar();
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', belicoTurnoIniciar); } else { belicoTurnoIniciar(); }
JS;
}

// ───────────────────────────────────────────────────────────────────────────
// Ruteo principal
// ───────────────────────────────────────────────────────────────────────────

$uid = (int) $mybb->user['uid'];
$tid = (int) $mybb->get_input('tid', MyBB::INPUT_INT);
$fid = (int) $mybb->get_input('fid', MyBB::INPUT_INT);
if ($fid <= 0) { $fid = $uid; }

$accion  = $mybb->get_input('accion', MyBB::INPUT_STRING);
$es_htmx = belico_es_htmx();

function belico_output_pagina($mybb, $headerinclude, $header, $footer, $contenido, $tecnicas = array())
{
    $page = '<html><head><title>Calculadora Bélica</title>' . $headerinclude
          . '<style>' . belico_css() . '</style>'
          . '<script src="' . $mybb->settings['bburl'] . '/jscripts/vendor/htmx-2.0.10.min.js"></script>'
          . '</head><body>' . $header
          . '<div class="indice belico-pagina"><main class="mainBackground">'
          . $contenido
          . '</main></div>'
          . $footer
          . '<script>' . belico_js_turno($tecnicas) . '</script>'
          . '<script>' . belico_js_ataque($GLOBALS['uid'], $GLOBALS['fid']) . '</script>'
          . '</body></html>';
    output_page($page);
}

if ($uid === 0) {
    $msg = 'Debes iniciar sesión para usar la calculadora.';
    if ($es_htmx) { echo belico_render_aviso($msg); exit; }
    belico_output_pagina($mybb, $headerinclude, $header, $footer, belico_render_aviso($msg));
    exit;
}

$ficha = belico_cargar_ficha($db, $fid, $tid);

if (!$ficha) {
    $msg = ($fid === $uid) ? 'Todavía no tienes ficha.' : 'Ese personaje no existe.';
    if ($es_htmx) { echo belico_render_aviso($msg); exit; }
    belico_output_pagina($mybb, $headerinclude, $header, $footer, belico_render_aviso($msg));
    exit;
}

$atributos_base = belico_calc_atributos_finales($ficha['stats']);
$mod            = belico_leer_modificadores($mybb);
$atributos      = belico_aplicar_modificadores($atributos_base, $mod);
$recursos       = belico_calc_recursos_maximos($db, $fid, $ficha['stats']);
$tecnicas       = belico_cargar_tecnicas($db, $fid);
$armas          = belico_cargar_armas($db, $fid);
$hilo           = $tid > 0 ? belico_cargar_hilo($tid) : null;

if ($es_htmx) {
    switch ($accion) {
        case 'turno':
            $crudo = json_decode((string) $mybb->get_input('turno', MyBB::INPUT_STRING), true);
            $estado_turno = belico_normalizar_turno($crudo, $tecnicas);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(belico_calcular_turno($estado_turno, $tecnicas, $armas, $atributos, $recursos), JSON_UNESCAPED_UNICODE);
            break;
        case 'estadisticas': echo belico_render_estadisticas(belico_calc_estadisticas($atributos)); break;
        case 'pasivas':      echo belico_render_lista_pasivas($tecnicas, $atributos, array_flip(array_map('strval', $mybb->get_input('pasivas', MyBB::INPUT_ARRAY)))); break;
        case 'estado':       echo belico_render_resultado_estado($mybb, $atributos); break;
        case 'ataque':       echo belico_render_resultado_ataque($mybb, $armas, $tecnicas, $atributos, $recursos); break;
        case 'ataque_form':  echo belico_render_bloque_ataque($mybb, $armas, $tecnicas, $tid, $fid, $atributos, $recursos); break;
        case 'reaccion':     echo belico_render_resultado_reaccion($mybb); break;
        case 'danio':        echo belico_render_resultado_danio($mybb, $recursos, $atributos); break;
        case 'caida':        echo belico_render_resultado_caida($mybb, $atributos); break;
        case 'asfixia':      echo belico_render_resultado_asfixia($mybb, $atributos); break;
        case 'lanzamiento':  echo belico_render_resultado_lanzamiento($mybb, $atributos); break;
        case 'agarre':       echo belico_render_resultado_agarre($mybb, $atributos); break;
        default:             echo belico_render_aviso('Acción no reconocida.');
    }
    exit;
}

$contenido  = belico_render_cabecera($mybb, $fid, $tid, $ficha, $hilo);
$contenido .= '<div class="belico-workspace"><aside class="belico-sidebar" aria-label="Ficha y recursos">';
$contenido .= belico_render_atributos($atributos_base, $mod, $tid, $fid);
$contenido .= belico_render_bloque_estado($atributos, $recursos, $tid, $fid);
$contenido .= belico_render_bloque_estadisticas($atributos, $tid, $fid);
$contenido .= '</aside><div class="belico-principal">';
$contenido .= '<div class="belico-tabs" role="tablist" aria-label="Herramientas bélicas">';
$contenido .= '<button id="belico-tab-turno" type="button" role="tab" aria-selected="true" aria-controls="belico-panel-turno" data-belico-tab="turno">Turno</button>';
$contenido .= '<button id="belico-tab-ataque" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-ataque" data-belico-tab="ataque" tabindex="-1">Ataque</button>';
$contenido .= '<button id="belico-tab-pasivas" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-pasivas" data-belico-tab="pasivas" tabindex="-1">Pasivas</button>';
$contenido .= '<button id="belico-tab-reaccion" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-reaccion" data-belico-tab="reaccion" tabindex="-1">Reacción</button>';
$contenido .= '<button id="belico-tab-utilidades" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-utilidades" data-belico-tab="utilidades" tabindex="-1">Utilidades</button>';
$contenido .= '</div>';
$contenido .= '<div class="belico-llevas" id="belico-llevas" aria-live="polite"></div>';
$contenido .= '<section id="belico-panel-turno" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-turno" data-belico-panel="turno">';
$contenido .= belico_render_bloque_turno($atributos, $recursos, $armas);
$contenido .= '</section>';
$contenido .= '<section id="belico-panel-ataque" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-ataque" data-belico-panel="ataque" hidden>';
$contenido .= belico_render_bloque_ataque($mybb, $armas, $tecnicas, $tid, $fid, $atributos, $recursos);
$contenido .= '</section>';
$contenido .= '<section id="belico-panel-pasivas" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-pasivas" data-belico-panel="pasivas" hidden>';
$contenido .= belico_render_bloque_pasivas($tecnicas, $atributos, $armas);
$contenido .= '</section>';
$contenido .= '<section id="belico-panel-reaccion" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-reaccion" data-belico-panel="reaccion" hidden>';
$contenido .= belico_render_bloque_reaccion($atributos, $tid, $fid);
$contenido .= belico_render_bloque_danio($atributos, $recursos, $tid, $fid);
$contenido .= '</section>';
$contenido .= '<section id="belico-panel-utilidades" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-utilidades" data-belico-panel="utilidades" hidden>';
$contenido .= belico_render_bloque_utilidades($atributos, $tid, $fid);
$contenido .= '</section>';
$contenido .= '</div></div>';

belico_output_pagina($mybb, $headerinclude, $header, $footer, $contenido, $tecnicas);
