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
            'multi_arma'            => array('dos' => 0.75, 'mas' => 0.60, 'ta_dos' => -10, 'ta_por_arma' => -5), // 264-269
            'dos_manos'             => 1.5,                                                                       // 254
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
        SELECT t.tid, t.nombre, t.rama, t.estilo, t.clase, t.tipo, t.tier, t.efectos
        FROM mybb_op_tecnicas t
        INNER JOIN mybb_op_tec_aprendidas ta ON t.tid = ta.tid
        WHERE ta.uid = '{$fid}'
        ORDER BY t.tid, t.rama
    ");
    while ($row = $db->fetch_array($q)) {
        $tecnicas[$row['tid']] = $row;
    }
    return $tecnicas;
}

// Solo armas (categoria='armas'), sin LLST001 — igual que calc.js:474-476.
function belico_cargar_armas($db, $fid)
{
    $fid   = (int) $fid;
    $armas = array();
    $q = $db->query("
        SELECT o.*
        FROM mybb_op_objetos o
        INNER JOIN mybb_op_inventario i ON o.objeto_id = i.objeto_id
        WHERE i.uid = '{$fid}' AND LOWER(o.categoria) = 'armas' AND o.objeto_id != 'LLST001'
        ORDER BY o.nombre
    ");
    while ($row = $db->fetch_array($q)) {
        $armas[$row['objeto_id']] = $row;
    }
    return $armas;
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

// Mismo patrón y mismo fallback que calc.js::_calcularDanoArma().
function belico_calc_danio_texto($texto, $atributos)
{
    if (!$texto) {
        return array('texto' => '', 'valor' => 0, 'calculado' => false);
    }
    $calc = $texto;
    if (preg_match_all('/\[([A-Z]{3})[x×](\d+(?:[,.]?\d+)?)\]/u', $texto, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $attr = $m[1];
            $mult = (float) str_replace(',', '.', $m[2]);
            if (isset($atributos[$attr])) {
                $val  = (int) ceil($atributos[$attr] * $mult);
                $calc = str_replace($m[0], (string) $val, $calc);
            }
        }
    }
    $total     = 0;
    $hay_calc  = false;
    if (preg_match_all('/\b\d+\b/', $calc, $nums)) {
        foreach ($nums[0] as $n) { $total += (int) $n; }
        $hay_calc = true;
    }
    return array('texto' => $calc, 'valor' => $total, 'calculado' => $hay_calc);
}

function belico_tipo_danio_arma($arma)
{
    if (empty($arma)) {
        return 'contundente';
    }
    $t = strtolower(isset($arma['dano']) ? $arma['dano'] : '');
    if (strpos($t, 'cortante') !== false)  { return 'cortante'; }
    if (strpos($t, 'perforante') !== false) { return 'perforante'; }
    if (strpos($t, 'sónico') !== false || strpos($t, 'sonico') !== false) { return 'sónico'; }
    return 'contundente';
}

// "A dos manos" (Guía 254): +50% al daño/bloqueo base del arma, antes de la
// penalización por varias armas.
function belico_calc_danio_arma($arma, $atributos, $modo, $dos_manos)
{
    if (empty($arma)) {
        return array('texto' => 'Sin arma', 'valor' => 0, 'calculado' => false);
    }
    $texto = ($modo === 'defensa' && !empty($arma['bloqueo'])) ? $arma['bloqueo'] : (isset($arma['dano']) ? $arma['dano'] : '');
    if ($texto === '') {
        return array('texto' => 'Sin valor', 'valor' => 0, 'calculado' => false);
    }
    $r = belico_calc_danio_texto($texto, $atributos);
    if ($dos_manos && $r['calculado']) {
        $r['valor'] = (int) ceil($r['valor'] * belico_reglas()['dos_manos']);
    }
    return $r;
}

// Golpe/Bloqueo básico. $armas_seleccionadas: array de ['arma'=>fila|null,
// 'dos_manos'=>bool]. Mismo malus que calc.js::_actualizarGolpeBasico()
// y Guía 264-266 (1 arma 100%, 2 → 75% c/u, 3+ → 60% c/u).
function belico_calc_golpe_basico($armas_seleccionadas, $atributos, $modo)
{
    $reglas  = belico_reglas();
    $activas = array_values(array_filter($armas_seleccionadas, function ($a) { return !empty($a['arma']); }));
    $num     = count($activas);

    if ($num === 0) {
        $base = $modo === 'defensa' ? $atributos['RES'] : $atributos['FUE'];
        return array('valor' => $base, 'tipo' => $modo === 'defensa' ? 'DAÑO MITIGADO' : 'DE DAÑO CONTUNDENTE');
    }

    $mult  = $num === 1 ? 1.0 : ($num === 2 ? $reglas['multi_arma']['dos'] : $reglas['multi_arma']['mas']);
    $total = 0;
    $tipos = array();
    foreach ($activas as $item) {
        $calc = belico_calc_danio_arma($item['arma'], $atributos, $modo, !empty($item['dos_manos']));
        $base = ($calc['calculado'] && $calc['valor'] > 0)
            ? $calc['valor']
            : ($modo === 'defensa' ? $atributos['RES'] : $atributos['FUE']);
        $total += $base * $mult;
        $tipo = belico_tipo_danio_arma($item['arma']);
        if (!in_array($tipo, $tipos, true)) { $tipos[] = $tipo; }
    }

    return array(
        'valor' => (int) ceil($total),
        'tipo'  => $modo === 'defensa' ? 'DAÑO MITIGADO' : ('DE DAÑO ' . strtoupper(implode('/', $tipos))),
    );
}

// Tasa de Acierto final. Guía 264-269: 1 arma = la suya; 2 armas = -10;
// 3+ = -5 por arma; con atributos de TA distintos, se toma el más bajo.
// Sin arma: Agilidad (línea 283).
function belico_calc_tasa_acierto($armas_seleccionadas, $atributos)
{
    $reglas  = belico_reglas();
    $activas = array_values(array_filter($armas_seleccionadas, function ($a) { return !empty($a['arma']); }));
    $num     = count($activas);

    if ($num === 0) {
        return array('valor' => $atributos['AGI'], 'atributo' => 'AGI');
    }

    $valores = array();
    foreach ($activas as $item) {
        $attr      = (!empty($item['ta_atributo']) && isset($atributos[$item['ta_atributo']])) ? $item['ta_atributo'] : 'AGI';
        $valores[] = $atributos[$attr];
    }
    $base = min($valores);

    $penal = 0;
    if ($num === 2) {
        $penal = $reglas['multi_arma']['ta_dos'];
    } elseif ($num > 2) {
        $penal = $reglas['multi_arma']['ta_por_arma'] * $num;
    }

    return array('valor' => $base + $penal, 'atributo' => null);
}

function belico_extraer_tipo_tecnica($efectos)
{
    $texto = strtolower((string) $efectos);
    if (strpos($texto, 'daño cortante') !== false)    { return 'cortante'; }
    if (strpos($texto, 'daño perforante') !== false)  { return 'perforante'; }
    if (strpos($texto, 'daño contundente') !== false) { return 'contundente'; }
    return 'especial';
}

// Portado de calc.js::_extraerDanoTecnica()/_calcularDanoTecnicaCompleto().
function belico_calc_danio_tecnica($efectos, $nivel)
{
    $tipo = belico_extraer_tipo_tecnica($efectos);

    $formula = null;
    if (preg_match('/\[\d+(?:,\d+)?xNivel\]/u', (string) $efectos)) {
        $formula = $efectos;
    } elseif (preg_match('/\[[A-Z]{3}x[\d,.]+\]/u', (string) $efectos)) {
        $formula = $efectos;
    }

    if ($formula === null) {
        return array('texto' => 'Sin daño', 'valor' => 0, 'calculado' => false, 'tipo' => 'especial');
    }

    $calc     = $formula;
    $total    = 0;
    $hay_calc = false;
    if ($nivel > 0 && preg_match_all('/\[(\d+(?:,\d+)?)xNivel\]/u', $formula, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $mult = (float) str_replace(',', '.', $m[1]);
            $val  = (int) ceil($mult * $nivel);
            $total += $val;
            $hay_calc = true;
            $calc = str_replace($m[0], (string) $val, $calc);
        }
    }
    if (!$hay_calc && preg_match_all('/\b\d+\b/', $calc, $nums)) {
        foreach ($nums[0] as $n) { $total += (int) $n; }
    }

    return array('texto' => $calc, 'valor' => $total, 'calculado' => $hay_calc, 'tipo' => $tipo);
}

// Portado de calc.js::_actualizarDanoTotal().
function belico_calc_danio_total($golpe_valor, $tecnica, $modo)
{
    $tecnica_es_defensiva = !empty($tecnica) && !empty($tecnica['es_defensiva']);
    $tecnica_valor        = !empty($tecnica) ? (int) $tecnica['valor'] : 0;

    if ($modo === 'defensa') {
        if ($tecnica_es_defensiva) {
            return array('valor' => $golpe_valor + $tecnica_valor, 'desc' => 'BLOQUEO + TÉCNICA');
        }
        return array('valor' => $golpe_valor, 'desc' => $tecnica_valor > 0 ? 'BLOQUEO (TÉCNICA OFENSIVA)' : 'SOLO BLOQUEO');
    }

    if ($tecnica_es_defensiva) {
        return array('valor' => $golpe_valor, 'desc' => '(TÉCNICA DEFENSIVA)');
    }
    return array('valor' => $golpe_valor + $tecnica_valor, 'desc' => $tecnica_valor > 0 ? 'DAÑO COMBINADO' : 'SOLO GOLPE BÁSICO');
}

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

    $ta_ajustada = $ta - $reglas['distancia_ta']['penalizacion'] * (int) floor($metros / $reglas['distancia_ta']['cada_metros']);

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

// Guía 52: empieza quien tenga el atributo más bajo más alto; si empatan,
// se compara el segundo más bajo; si siguen empatados, 1d100.
function belico_calc_iniciativa($atributos_a, $atributos_b)
{
    $vals_a = array_values($atributos_a);
    $vals_b = array_values($atributos_b);
    sort($vals_a);
    sort($vals_b);
    $n = min(count($vals_a), count($vals_b));
    for ($i = 0; $i < $n; $i++) {
        if ($vals_a[$i] !== $vals_b[$i]) {
            return $vals_a[$i] > $vals_b[$i] ? 'A' : 'B';
        }
    }
    return 'empate';
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

$BELICO_ATRIBUTOS_VALIDOS = array('FUE', 'AGI', 'PUN', 'DES', 'RES', 'REF', 'VOL');

function belico_leer_armas_seleccionadas($mybb, $armas_disponibles, $espacios)
{
    global $BELICO_ATRIBUTOS_VALIDOS;
    $seleccion = array();
    for ($i = 1; $i <= $espacios; $i++) {
        $objeto_id = trim((string) $mybb->get_input('arma' . $i, MyBB::INPUT_STRING));
        $arma      = ($objeto_id !== '' && isset($armas_disponibles[$objeto_id])) ? $armas_disponibles[$objeto_id] : null;
        $dos_manos = ((int) $mybb->get_input('dosmanos' . $i, MyBB::INPUT_INT)) === 1;
        $ta_attr   = trim((string) $mybb->get_input('ta' . $i, MyBB::INPUT_STRING));
        $ta_attr   = in_array($ta_attr, $BELICO_ATRIBUTOS_VALIDOS, true) ? $ta_attr : 'AGI';
        $seleccion[] = array('arma' => $arma, 'dos_manos' => $dos_manos, 'ta_atributo' => $ta_attr);
    }
    return $seleccion;
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

function belico_render_selector_armas($armas, $i, $seleccionado = '', $dos_manos = false, $ta_sel = 'AGI')
{
    global $BELICO_ATRIBUTOS_VALIDOS;
    $html  = '<div class="af-field"><label for="belico-arma-' . $i . '">Arma ' . $i . '</label><select id="belico-arma-' . $i . '" name="arma' . $i . '">';
    $html .= '<option value="">Sin arma</option>';
    foreach ($armas as $objeto_id => $arma) {
        $sel = ($objeto_id === $seleccionado) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($objeto_id, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($arma['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<div class="af-field"><label for="belico-ta-' . $i . '">TA arma ' . $i . '</label><select id="belico-ta-' . $i . '" name="ta' . $i . '">';
    foreach ($BELICO_ATRIBUTOS_VALIDOS as $attr) {
        $sel = ($attr === $ta_sel) ? ' selected' : '';
        $html .= '<option value="' . $attr . '"' . $sel . '>' . $attr . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<div class="af-field belico-check"><label><input type="checkbox" name="dosmanos' . $i . '" value="1"' . ($dos_manos ? ' checked' : '') . '> A dos manos</label></div>';
    return $html;
}

function belico_render_bloque_ataque($mybb, $armas, $tecnicas, $tid, $fid, $atributos = null)
{
    $espacios = (int) $mybb->get_input('espacios', MyBB::INPUT_INT);
    if ($espacios < 1) { $espacios = 1; }
    if ($espacios > 4) { $espacios = 4; } // límite práctico — agregar/quitar dinámico no cambia ningún cálculo (200_DesignPlan §5.6)

    $html  = '<section class="belico-modulo belico-modulo--principal" id="belico-ataque"><h2>Ataque</h2>';
    $html .= '<form id="belico-ataque-form" class="af-row">';
    $html .= belico_render_campos_ocultos($tid, $fid);
    $modo = $mybb->get_input('modo', MyBB::INPUT_STRING) === 'defensa' ? 'defensa' : 'ataque';
    $html .= '<div class="af-field"><label for="belico-modo">Modo</label><select id="belico-modo" name="modo"><option value="ataque"' . ($modo === 'ataque' ? ' selected' : '') . '>Ataque</option><option value="defensa"' . ($modo === 'defensa' ? ' selected' : '') . '>Defensa</option></select></div>';
    $html .= '<div class="af-field"><label for="belico-espacios">Cantidad de armas</label><select id="belico-espacios" name="espacios" hx-get="belico.php" hx-vals=\'{"accion":"ataque_form"}\' hx-include="#belico-ataque-form,#belico-atributos" hx-target="#belico-ataque" hx-trigger="change consume" hx-swap="outerHTML">';
    for ($n = 1; $n <= 4; $n++) {
        $sel = ($n === $espacios) ? ' selected' : '';
        $html .= '<option value="' . $n . '"' . $sel . '>' . $n . '</option>';
    }
    $html .= '</select></div>';
    for ($i = 1; $i <= $espacios; $i++) {
        $arma_sel = trim((string) $mybb->get_input('arma' . $i, MyBB::INPUT_STRING));
        $ta_sel = trim((string) $mybb->get_input('ta' . $i, MyBB::INPUT_STRING));
        $dos_manos = (int) $mybb->get_input('dosmanos' . $i, MyBB::INPUT_INT) === 1;
        $html .= belico_render_selector_armas($armas, $i, $arma_sel, $dos_manos, $ta_sel ?: 'AGI');
    }
    $html .= '<div class="af-field"><label for="belico-tecnica">Técnica</label><select id="belico-tecnica" name="tecnica">';
    $html .= '<option value="">Sin técnica</option>';
    $tecnica_sel = trim((string) $mybb->get_input('tecnica', MyBB::INPUT_STRING));
    foreach ($tecnicas as $tid_tec => $tec) {
        $seleccionada = $tecnica_sel === $tid_tec ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($tid_tec, ENT_QUOTES, 'UTF-8') . '"' . $seleccionada . '>[' . htmlspecialchars($tid_tec, ENT_QUOTES, 'UTF-8') . '] ' . htmlspecialchars($tec['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select></div>';
    $html .= '</form>';
    $html .= '<div id="res-ataque" aria-live="polite" ' . belico_attrs_htmx('ataque', '#belico-ataque-form', 'change from:#belico-ataque-form') . '>';
    $html .= belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos ?: belico_calc_atributos_finales_vacio());
    $html .= '</div>';
    $html .= '</section>';
    return $html;
}

// Placeholder usado solo para el render inicial del formulario (sin
// atributos todavía resueltos en ese punto del armado de la página).
function belico_calc_atributos_finales_vacio()
{
    return array('NIV' => 0, 'FUE' => 0, 'AGI' => 0, 'PUN' => 0, 'DES' => 0, 'RES' => 0, 'REF' => 0, 'VOL' => 0);
}

function belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos)
{
    $espacios = (int) $mybb->get_input('espacios', MyBB::INPUT_INT);
    if ($espacios < 1) { $espacios = 1; }
    $modo = $mybb->get_input('modo', MyBB::INPUT_STRING) === 'defensa' ? 'defensa' : 'ataque';

    $seleccion = belico_leer_armas_seleccionadas($mybb, $armas, $espacios);

    $tid_tec  = trim((string) $mybb->get_input('tecnica', MyBB::INPUT_STRING));
    $tecnica  = null;
    if ($tid_tec !== '' && isset($tecnicas[$tid_tec])) {
        $tec    = $tecnicas[$tid_tec];
        $dt     = belico_calc_danio_tecnica($tec['efectos'], $atributos['NIV']);
        $tipo_s = strtolower($tec['tipo']);
        $tecnica = array('valor' => $dt['valor'], 'es_defensiva' => strpos($tipo_s, 'defensiva') !== false);
    }

    $golpe = belico_calc_golpe_basico($seleccion, $atributos, $modo);
    $ta    = belico_calc_tasa_acierto($seleccion, $atributos);
    $total = belico_calc_danio_total($golpe['valor'], $tecnica, $modo);

    $html  = '<div class="belico-resultados belico-resultados--ataque">';
    $html .= '<div><span>Golpe / bloqueo: </span><strong>' . $golpe['valor'] . '</strong><small> (' . htmlspecialchars($golpe['tipo'], ENT_QUOTES, 'UTF-8') . ')</small></div>';
    $html .= '<div><span>Tasa de acierto: </span><strong>' . $ta['valor'] . '</strong></div>';
    $html .= '<div class="belico-resultado-clave"><span>Daño total: </span><strong>' . $total['valor'] . '</strong><small> (' . htmlspecialchars($total['desc'], ENT_QUOTES, 'UTF-8') . ')</small></div>';
    $html .= '</div>';
    return $html;
}

function belico_render_resultado_ataque($mybb, $armas, $tecnicas, $atributos)
{
    return belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos);
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

    $html .= '<div class="belico-utilidad"><h3>Iniciativa</h3><form class="af-row" hx-get="belico.php" hx-vals=\'{"accion":"iniciativa"}\' hx-target="#res-iniciativa" hx-trigger="input changed delay:300ms" hx-swap="innerHTML">' . $oculto;
    $html .= '<div class="af-field"><label>Iniciativa: Atributos del rival (coma)</label><input type="text" name="rival_attrs" placeholder="50,50,50,50,50,50,50"></div></form>';
    $html .= '<div id="res-iniciativa"></div></div>';

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

function belico_render_bloque_pasivas($tecnicas)
{
    $pasivas = array();
    foreach ($tecnicas as $tecnica) {
        $clasificacion = (string) $tecnica['clase'] . ' ' . (string) $tecnica['tipo'];
        if (stripos($clasificacion, 'pasiva') !== false) {
            $pasivas[] = $tecnica;
        }
    }

    $html = '<section class="belico-modulo belico-pasivas" id="belico-pasivas"><h2>Pasivas del turno</h2>';
    $html .= '<p class="belico-turno-intro">Marca únicamente las pasivas que estén aplicando en este turno y copia el bloque para incluirlo en tu post.</p>';
    if (empty($pasivas)) {
        $html .= '<div class="opg-vacio"><p>No hay técnicas pasivas aprendidas para seleccionar.</p></div>';
    } else {
        $html .= '<div class="belico-pasivas-lista" role="group" aria-label="Pasivas aplicadas este turno">';
        foreach ($pasivas as $tecnica) {
            $tid = htmlspecialchars((string) $tecnica['tid'], ENT_QUOTES, 'UTF-8');
            $nombre = htmlspecialchars((string) $tecnica['nombre'], ENT_QUOTES, 'UTF-8');
            $detalle = htmlspecialchars(trim((string) $tecnica['clase'] . ' · ' . (string) $tecnica['tipo'], ' ·'), ENT_QUOTES, 'UTF-8');
            $html .= '<label class="opg-card belico-pasiva-item"><input type="checkbox" value="' . $tid . '" data-belico-pasiva data-belico-pasiva-nombre="' . $nombre . '"><span class="belico-pasiva-control" aria-hidden="true"></span><span><strong>' . $nombre . '</strong><small>' . $detalle . '</small></span></label>';
        }
        $html .= '</div>';
        $html .= '<div class="belico-pasivas-pie"><span id="belico-pasivas-resumen" aria-live="polite">0 pasivas seleccionadas</span><button type="button" class="btn-op btn-op--secundario btn-op--sm" id="belico-copiar-pasivas">Copiar pasivas</button></div>';
        $html .= '<textarea id="belico-pasivas-textarea" hidden aria-label="Pasivas para copiar al post"></textarea>';
    }
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

function belico_render_resultado_iniciativa($mybb, $atributos)
{
    $rival_raw = (string) $mybb->get_input('rival_attrs', MyBB::INPUT_STRING);
    $partes    = array_filter(array_map('trim', explode(',', $rival_raw)), function ($v) { return $v !== ''; });
    if (count($partes) < 2) {
        return belico_render_aviso('Ingresa los atributos del rival separados por coma (ej. 50,50,50,50,50,50,50).', 'err');
    }
    $rival = array();
    foreach ($partes as $p) { $rival[] = (int) $p; }
    $resultado = belico_calc_iniciativa($atributos, $rival);
    if ($resultado === 'empate') {
        return '<p>Empate completo: se tira un dado de 100, el que saque más alto elige quién empieza.</p>';
    }
    return '<p>Empieza: <strong>' . ($resultado === 'A' ? 'Tu personaje' : 'El rival') . '</strong>.</p>';
}

// ── Constructor del turno y copiar al post ───────────────────────────────

function belico_render_bloque_registro($movimiento)
{
    $html  = '<section class="belico-modulo belico-registro-seccion belico-turno" id="belico-registro-seccion"><h2>Constructor del turno</h2>';
    $html .= '<p class="belico-turno-intro">Define los bloques en orden cronológico. El constructor solo permite las combinaciones previstas por la guía.</p>';
    $html .= '<div class="belico-turno-config af-row">';
    $html .= '<div class="af-field"><label for="belico-turno-nombre">Nombre del turno</label><input type="text" id="belico-turno-nombre" value="Turno" maxlength="60"></div>';
    $html .= '<div class="af-field"><label for="belico-movimiento-limite">Movimiento disponible</label><input type="number" id="belico-movimiento-limite" value="' . (int) $movimiento . '" min="0"><small>Máximo del turno; ajústalo si un efecto lo modifica.</small></div>';
    $html .= '<div class="af-field"><label for="belico-adicionales-limite">Acciones adicionales</label><input type="number" id="belico-adicionales-limite" value="3" min="0"><small>Base: 3; ajústalo si una pasiva concede más.</small></div>';
    $html .= '</div>';
    $html .= '<div class="belico-turno-resumen" aria-live="polite"><span id="belico-movimiento-resumen">Movimiento: 0 / ' . (int) $movimiento . ' m</span><span id="belico-adicionales-resumen">Acciones adicionales: 0 / 3</span></div>';
    $html .= '<div class="belico-tipos-bloque" role="group" aria-label="Añadir bloque de acción">';
    $html .= '<button type="button" class="opg-chip" data-belico-nuevo-bloque="tecnica">Con técnica ofensiva o defensiva</button>';
    $html .= '<button type="button" class="opg-chip" data-belico-nuevo-bloque="sin_tecnica">Sin técnica</button>';
    $html .= '<button type="button" class="opg-chip" data-belico-nuevo-bloque="utilidad">Técnicas de utilidad</button>';
    $html .= '</div>';
    $html .= '<div id="belico-turno-bloques" class="belico-turno-bloques"></div>';
    $html .= '<p id="belico-turno-vacio" class="belico-turno-vacio">Añade el primer bloque para construir la estrategia del turno.</p>';
    $html .= '<div class="belico-turno-avisos" id="belico-turno-avisos" aria-live="polite"></div>';
    $html .= '<div class="belico-modulo-acciones"><button type="button" class="btn-op btn-op--secundario btn-op--sm" id="belico-copiar-turno">Copiar estrategia</button><button type="button" class="opg-chip" id="belico-limpiar-turno">Limpiar turno</button></div>';
    $html .= '<textarea id="belico-copiar-textarea" hidden aria-label="Estrategia para copiar al post"></textarea>';
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
.belico-pagina .belico-modulo-acciones { display: flex; flex-wrap: wrap; gap: var(--opg-espacio-2); margin-top: var(--opg-espacio-4); }
.belico-pagina .btn-op { letter-spacing: 0; }
.belico-pagina .btn-op--sm { font: 14px/1.3 InterMedium, var(--opg-fuente-cuerpo); color: var(--opg-tinta); text-shadow: none; padding: 8px 14px; }
.belico-pagina .btn-op--secundario.btn-op--sm { color: #fff; }
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
.belico-pagina .belico-registro-seccion { border-bottom: 0; }
.belico-pagina .belico-turno-intro { margin: calc(var(--opg-espacio-2) * -1) 0 var(--opg-espacio-4); color: var(--opg-gris-texto); font-size: 13px; line-height: 1.45; }
.belico-pagina .belico-turno-config { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start; gap: var(--opg-espacio-3); margin-bottom: var(--opg-espacio-4); padding: var(--opg-espacio-4); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); }
.belico-pagina .belico-turno-config .af-field { display: flex; flex-direction: column; min-width: 0; margin: 0; }
.belico-pagina .belico-turno-config .af-field small { min-height: 2.8em; }
.belico-pagina .belico-turno-resumen { display: flex; flex-wrap: wrap; gap: var(--opg-espacio-2); margin: var(--opg-espacio-3) 0; }
.belico-pagina .belico-turno-resumen span { padding: 5px 9px; border: 1px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); color: var(--opg-marron); font: 12px/1.2 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-turno-resumen .belico-turno-excedido { border-color: var(--opg-rojo-error); color: var(--opg-rojo-error); }
.belico-pagina .belico-tipos-bloque { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--opg-espacio-2); margin: var(--opg-espacio-4) 0; }
.belico-pagina .belico-tipos-bloque .opg-chip { justify-content: center; min-height: 42px; border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); text-align: center; }
.belico-pagina .belico-tipos-bloque .opg-chip:hover { background: var(--opg-naranja); }
.belico-pagina .belico-turno-bloques { display: flex; flex-direction: column; gap: var(--opg-espacio-4); }
.belico-pagina .belico-turno-vacio { margin: 0; padding: var(--opg-espacio-4); border: 2px dashed var(--opg-tinta); border-radius: var(--opg-radio-sm); color: var(--opg-gris-texto); font-size: 13px; }
.belico-pagina .belico-bloque-turno { --opg-card-acento: var(--opg-morado); margin: 0; }
.belico-pagina .belico-bloque-cabecera { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--opg-espacio-3); }
.belico-pagina .belico-bloque-titulo { margin: 0; color: var(--opg-marron); font: 16px/1.25 InterMedium, var(--opg-fuente-cuerpo); }
.belico-pagina .belico-bloque-descripcion { margin: 4px 0 0; color: var(--opg-gris-texto); font-size: 12px; line-height: 1.4; }
.belico-pagina .belico-bloque-orden { display: inline-flex; flex: none; gap: 4px; }
.belico-pagina .belico-bloque-icono { width: 28px; height: 28px; justify-content: center; padding: 0; background: var(--opg-crema-clara); }
.belico-pagina .belico-bloque-icono:last-child:hover { background: var(--opg-rojo-error); }
.belico-pagina .belico-bloque-cuerpo { margin: var(--opg-espacio-3) 0 0; padding-top: var(--opg-espacio-3); border-top: 1px solid rgba(59, 19, 0, .28); }
.belico-pagina .belico-bloque-cuerpo .af-field { flex: 1 1 170px; }
.belico-pagina .belico-bloque-cuerpo .belico-check { flex-basis: 100%; }
.belico-pagina .belico-turno-avisos { margin-top: var(--opg-espacio-3); }
.belico-pagina .belico-turno-aviso { margin: var(--opg-espacio-2) 0 0; padding-left: var(--opg-espacio-3); border-left: 3px solid var(--opg-rojo-error); color: var(--opg-rojo-error); font-size: 13px; line-height: 1.4; }
.belico-pagina #belico-copiar-textarea:not([hidden]) { display: block; width: 100%; min-height: 100px; margin-top: var(--opg-espacio-3); padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema-clara); font: 13px/1.4 var(--opg-fuente-cuerpo); }
.belico-pagina .belico-pasivas-lista { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--opg-espacio-3); }
.belico-pagina .belico-pasiva-item { --opg-card-acento: var(--opg-morado); display: flex; align-items: center; gap: var(--opg-espacio-3); min-width: 0; margin: 0; cursor: pointer; }
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
  .belico-pagina .belico-tipos-bloque { grid-template-columns: minmax(0, 1fr); }
  .belico-pagina .belico-pasivas-lista { grid-template-columns: minmax(0, 1fr); }
  .belico-pagina .belico-turno-config { grid-template-columns: minmax(0, 1fr); }
  .belico-pagina .belico-tabs { gap: var(--opg-espacio-1); }
  .belico-pagina .belico-tabs button { padding: 9px 12px 8px; font-size: 13px; }
  .belico-pagina .belico-bloque-cabecera { align-items: flex-start; }
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

function belico_js_turno($tecnicas)
{
    $lista = array();
    foreach ($tecnicas as $tecnica) {
        $lista[] = array(
            'tid' => (string) $tecnica['tid'],
            'nombre' => (string) $tecnica['nombre'],
            'clase' => (string) $tecnica['clase'],
            'tipo' => (string) $tecnica['tipo'],
        );
    }
    $json = json_encode($lista, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json === false) { $json = '[]'; }

    return 'window.belicoTecnicas = ' . $json . ";\n" . belico_js_base() . <<<'JS'

function belicoCrearElemento(etiqueta, clase, texto) {
    var elemento = document.createElement(etiqueta);
    if (clase) { elemento.className = clase; }
    if (texto !== undefined) { elemento.textContent = texto; }
    return elemento;
}

function belicoCrearCampo(etiqueta, control, ayuda) {
    var campo = belicoCrearElemento('div', 'af-field');
    var label = belicoCrearElemento('label', '', etiqueta);
    var id = 'belico-turno-' + Math.random().toString(36).slice(2);
    control.id = id;
    label.htmlFor = id;
    campo.appendChild(label);
    campo.appendChild(control);
    if (ayuda) { campo.appendChild(belicoCrearElemento('small', '', ayuda)); }
    return campo;
}

function belicoNumero(valor) {
    return Math.max(0, Number(valor) || 0);
}

function belicoCrearNumero(valor, clase) {
    var campo = document.createElement('input');
    campo.type = 'number';
    campo.min = '0';
    campo.value = valor || 0;
    campo.className = clase || '';
    return campo;
}

function belicoCrearTexto(placeholder) {
    var campo = document.createElement('input');
    campo.type = 'text';
    campo.placeholder = placeholder;
    campo.className = 'belico-detalle';
    return campo;
}

function belicoClasificacionTecnica(tecnica) {
    return ((tecnica.clase || '') + ' ' + (tecnica.tipo || '')).toLowerCase();
}

function belicoEsUtilidad(tecnica) {
    var texto = belicoClasificacionTecnica(tecnica);
    return texto.indexOf('utilidad') !== -1 && (texto.indexOf('activa') !== -1 || texto.indexOf('mantenida') !== -1);
}

function belicoEsPrincipal(tecnica) {
    var texto = belicoClasificacionTecnica(tecnica);
    return texto.indexOf('activa') !== -1 && texto.indexOf('mantenida') === -1 && (texto.indexOf('ofensiva') !== -1 || texto.indexOf('defensiva') !== -1 || texto.indexOf('ambiental') !== -1);
}

function belicoCrearSelectorTecnicas(filtro, vacio, tipo) {
    var select = document.createElement('select');
    select.dataset.belicoTecnica = tipo;
    var primera = document.createElement('option');
    primera.value = '';
    primera.textContent = vacio;
    select.appendChild(primera);
    (window.belicoTecnicas || []).filter(filtro).forEach(function (tecnica) {
        var opcion = document.createElement('option');
        opcion.value = tecnica.tid;
        opcion.textContent = '[' + tecnica.tid + '] ' + tecnica.nombre;
        select.appendChild(opcion);
    });
    return select;
}

function belicoCrearMovimiento(cuerpo) {
    cuerpo.appendChild(belicoCrearCampo('Movimiento previo (m)', belicoCrearNumero(0, 'belico-movimiento-previo')));
    cuerpo.appendChild(belicoCrearCampo('Movimiento posterior (m)', belicoCrearNumero(0, 'belico-movimiento-posterior')));
}

function belicoNombreTipo(tipo) {
    return {
        tecnica: 'Técnica ofensiva o defensiva',
        sin_tecnica: 'Sin técnica',
        utilidad: 'Técnicas de utilidad'
    }[tipo] || 'Acción';
}

function belicoBotonBloque(icono, etiqueta, accion) {
    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'opg-chip belico-bloque-icono';
    boton.dataset.belicoAccionBloque = accion;
    boton.setAttribute('aria-label', etiqueta);
    boton.title = etiqueta;
    boton.innerHTML = '<i class="fa-solid ' + icono + '" aria-hidden="true"></i>';
    return boton;
}

function belicoCrearBloque(tipo) {
    var bloque = belicoCrearElemento('article', 'opg-card belico-bloque-turno');
    bloque.dataset.belicoTipo = tipo;
    var cabecera = belicoCrearElemento('header', 'belico-bloque-cabecera');
    var texto = belicoCrearElemento('div');
    texto.appendChild(belicoCrearElemento('h3', 'belico-bloque-titulo', belicoNombreTipo(tipo)));
    var descripciones = {
        tecnica: 'Una ofensiva o defensa, más una técnica de utilidad opcional que la afecte.',
        sin_tecnica: 'Hasta dos acciones no técnicas; cada una consume una acción adicional.',
        utilidad: 'Hasta dos técnicas de utilidad y, opcionalmente, un bloqueo básico.'
    };
    texto.appendChild(belicoCrearElemento('p', 'belico-bloque-descripcion', descripciones[tipo]));
    cabecera.appendChild(texto);
    var controles = belicoCrearElemento('div', 'belico-bloque-orden');
    controles.appendChild(belicoBotonBloque('fa-arrow-up', 'Subir bloque', 'subir'));
    controles.appendChild(belicoBotonBloque('fa-arrow-down', 'Bajar bloque', 'bajar'));
    controles.appendChild(belicoBotonBloque('fa-xmark', 'Quitar bloque', 'quitar'));
    cabecera.appendChild(controles);
    bloque.appendChild(cabecera);
    var cuerpo = belicoCrearElemento('div', 'belico-bloque-cuerpo af-row');
    cuerpo.appendChild(belicoCrearCampo('Contexto', belicoCrearSelectorContexto()));

    if (tipo === 'tecnica') {
        cuerpo.appendChild(belicoCrearCampo('Técnica principal', belicoCrearSelectorTecnicas(belicoEsPrincipal, 'Elegir ofensiva o defensiva', 'principal')));
        cuerpo.appendChild(belicoCrearCampo('Técnica de utilidad', belicoCrearSelectorTecnicas(belicoEsUtilidad, 'Sin técnica de utilidad', 'utilidad'), 'Opcional; debe afectar a la acción principal.'));
    }
    if (tipo === 'sin_tecnica') {
        cuerpo.appendChild(belicoCrearCampo('Primera acción', belicoCrearSelectorAccion()));
        cuerpo.appendChild(belicoCrearCampo('Segunda acción', belicoCrearSelectorAccion(), 'Opcional.'));
    }
    if (tipo === 'utilidad') {
        cuerpo.appendChild(belicoCrearCampo('Primera técnica', belicoCrearSelectorTecnicas(belicoEsUtilidad, 'Elegir técnica de utilidad', 'utilidad')));
        cuerpo.appendChild(belicoCrearCampo('Segunda técnica', belicoCrearSelectorTecnicas(belicoEsUtilidad, 'Sin segunda técnica', 'utilidad'), 'Opcional.'));
        var check = document.createElement('input');
        check.type = 'checkbox';
        check.dataset.belicoBloqueBasico = '1';
        var campoCheck = belicoCrearElemento('div', 'af-field belico-check');
        var etiqueta = document.createElement('label');
        etiqueta.appendChild(check);
        etiqueta.appendChild(document.createTextNode(' Añadir bloqueo básico'));
        campoCheck.appendChild(etiqueta);
        cuerpo.appendChild(campoCheck);
    }
    belicoCrearMovimiento(cuerpo);
    cuerpo.appendChild(belicoCrearCampo('Detalle del bloque', belicoCrearTexto('Objetivo, objeto o condición'), 'Opcional.'));
    bloque.appendChild(cuerpo);
    return bloque;
}

function belicoCrearSelectorAccion() {
    var opciones = [
        ['', 'Sin acción adicional'], ['golpe', 'Golpe o bloqueo básico'], ['lanzamiento', 'Lanzar un objeto sin técnica'],
        ['consumible', 'Utilizar un consumible'], ['dial', 'Utilizar un dial'], ['agarre', 'Realizar un agarre'],
        ['cambiar_arma', 'Cambiar el tipo de arma dual'], ['equipar_arma', 'Equipar un arma'],
        ['levantarse', 'Levantarse tras derribo'], ['recuperar_arma', 'Recuperar arma tras desarme'],
        ['trampa', 'Poner una trampa'], ['soltarse', 'Soltarse de un agarre']
    ];
    var select = document.createElement('select');
    select.dataset.belicoAccionAdicional = '1';
    opciones.forEach(function (opcion) {
        var item = document.createElement('option');
        item.value = opcion[0];
        item.textContent = opcion[1];
        select.appendChild(item);
    });
    return select;
}

function belicoCrearSelectorContexto() {
    var select = document.createElement('select');
    select.dataset.belicoContexto = '1';
    [['turno', 'Acción de este turno'], ['respuesta', 'Respuesta a un bloque previo']].forEach(function (opcion) {
        var item = document.createElement('option');
        item.value = opcion[0];
        item.textContent = opcion[1];
        select.appendChild(item);
    });
    return select;
}

function belicoActualizarBloques() {
    var bloques = document.querySelectorAll('#belico-turno-bloques .belico-bloque-turno');
    bloques.forEach(function (bloque, indice) {
        bloque.querySelector('.belico-bloque-titulo').textContent = 'Bloque ' + (indice + 1) + ' — ' + belicoNombreTipo(bloque.dataset.belicoTipo);
    });
    var vacio = document.getElementById('belico-turno-vacio');
    if (vacio) { vacio.hidden = bloques.length > 0; }
    belicoRevisarTurno();
}

function belicoRevisarTurno() {
    var movimiento = 0;
    var adicionales = 0;
    var avisos = [];
    var bloques = document.querySelectorAll('#belico-turno-bloques .belico-bloque-turno');
    bloques.forEach(function (bloque, indice) {
        movimiento += belicoNumero(bloque.querySelector('.belico-movimiento-previo').value) + belicoNumero(bloque.querySelector('.belico-movimiento-posterior').value);
        if (bloque.dataset.belicoTipo === 'tecnica' && !bloque.querySelector('[data-belico-tecnica="principal"]').value) { avisos.push('El bloque ' + (indice + 1) + ' necesita una técnica ofensiva o defensiva.'); }
        if (bloque.dataset.belicoTipo === 'sin_tecnica') {
            var acciones = Array.prototype.slice.call(bloque.querySelectorAll('[data-belico-accion-adicional]')).filter(function (campo) { return campo.value; });
            adicionales += acciones.length;
            if (!acciones.length) { avisos.push('El bloque ' + (indice + 1) + ' necesita al menos una acción no técnica.'); }
            var sinMovimiento = acciones.some(function (campo) { return ['levantarse', 'recuperar_arma', 'trampa'].indexOf(campo.value) !== -1; });
            var soltarse = acciones.some(function (campo) { return campo.value === 'soltarse'; });
            var previo = belicoNumero(bloque.querySelector('.belico-movimiento-previo').value);
            var posterior = belicoNumero(bloque.querySelector('.belico-movimiento-posterior').value);
            if (sinMovimiento && (previo || posterior)) { avisos.push('El bloque ' + (indice + 1) + ' incluye una acción sin movimiento; no puede declarar desplazamiento previo ni posterior.'); }
            if (!sinMovimiento && soltarse && previo) { avisos.push('El bloque ' + (indice + 1) + ' incluye soltarse de un agarre; no puede declarar movimiento previo.'); }
        }
        if (bloque.dataset.belicoTipo === 'utilidad') {
            var tecnicas = Array.prototype.slice.call(bloque.querySelectorAll('[data-belico-tecnica="utilidad"]')).filter(function (campo) { return campo.value; });
            if (!tecnicas.length) { avisos.push('El bloque ' + (indice + 1) + ' necesita una técnica de utilidad.'); }
            if (bloque.querySelector('[data-belico-bloque-basico]').checked) { adicionales += 1; }
        }
    });
    var limiteMovimiento = belicoNumero(document.getElementById('belico-movimiento-limite').value);
    var limiteAdicionales = belicoNumero(document.getElementById('belico-adicionales-limite').value);
    var resumenMovimiento = document.getElementById('belico-movimiento-resumen');
    var resumenAdicionales = document.getElementById('belico-adicionales-resumen');
    resumenMovimiento.textContent = 'Movimiento: ' + movimiento + ' / ' + limiteMovimiento + ' m';
    resumenAdicionales.textContent = 'Acciones adicionales: ' + adicionales + ' / ' + limiteAdicionales;
    resumenMovimiento.classList.toggle('belico-turno-excedido', movimiento > limiteMovimiento);
    resumenAdicionales.classList.toggle('belico-turno-excedido', adicionales > limiteAdicionales);
    if (movimiento > limiteMovimiento) { avisos.push('El movimiento declarado supera el disponible para este turno.'); }
    if (adicionales > limiteAdicionales) { avisos.push('Las acciones adicionales superan el límite configurado.'); }
    var destino = document.getElementById('belico-turno-avisos');
    destino.replaceChildren();
    avisos.forEach(function (aviso) { destino.appendChild(belicoCrearElemento('p', 'belico-turno-aviso', aviso)); });
    return avisos;
}

function belicoTextoTecnica(select) {
    return select && select.value ? '[tecnica=' + select.value + ']' : '';
}

function belicoTextoSeguro(texto) {
    return (texto || '').replace(/[\[\]]/g, '').trim();
}

function belicoConstruirBloque(bloque, indice) {
    var tipo = bloque.dataset.belicoTipo;
    var lineas = ['[spoiler=Bloque de acción ' + (indice + 1) + ' — ' + belicoNombreTipo(tipo) + ']'];
    if (bloque.querySelector('[data-belico-contexto]').value === 'respuesta') { lineas.push('Contexto: Respuesta a un bloque del turno anterior.'); }
    var previo = belicoNumero(bloque.querySelector('.belico-movimiento-previo').value);
    var posterior = belicoNumero(bloque.querySelector('.belico-movimiento-posterior').value);
    if (previo) { lineas.push('Movimiento previo: ' + previo + ' m.'); }
    if (tipo === 'tecnica') {
        lineas.push('Técnica principal: ' + belicoTextoTecnica(bloque.querySelector('[data-belico-tecnica="principal"]')) + '.');
        var asociada = bloque.querySelector('[data-belico-tecnica="utilidad"]');
        if (asociada.value) { lineas.push('Técnica de utilidad asociada: ' + belicoTextoTecnica(asociada) + '.'); }
    }
    if (tipo === 'sin_tecnica') {
        Array.prototype.slice.call(bloque.querySelectorAll('[data-belico-accion-adicional]')).forEach(function (campo) {
            if (campo.value) { lineas.push('Acción: ' + campo.options[campo.selectedIndex].text + '.'); }
        });
    }
    if (tipo === 'utilidad') {
        Array.prototype.slice.call(bloque.querySelectorAll('[data-belico-tecnica="utilidad"]')).forEach(function (campo) {
            if (campo.value) { lineas.push('Técnica de utilidad: ' + belicoTextoTecnica(campo) + '.'); }
        });
        if (bloque.querySelector('[data-belico-bloque-basico]').checked) { lineas.push('Acción: Bloqueo básico.'); }
    }
    if (posterior) { lineas.push('Movimiento posterior: ' + posterior + ' m.'); }
    var detalle = belicoTextoSeguro(bloque.querySelector('.belico-detalle').value);
    if (detalle) { lineas.push('Detalle: ' + detalle); }
    lineas.push('[/spoiler]');
    return lineas.join('\n');
}

function belicoCopiarTurno() {
    var bloques = Array.prototype.slice.call(document.querySelectorAll('#belico-turno-bloques .belico-bloque-turno'));
    var avisos = belicoRevisarTurno();
    if (!bloques.length || avisos.length) {
        if (!bloques.length) { document.getElementById('belico-turno-avisos').appendChild(belicoCrearElemento('p', 'belico-turno-aviso', 'Añade al menos un bloque antes de copiar la estrategia.')); }
        return;
    }
    var nombre = belicoTextoSeguro(document.getElementById('belico-turno-nombre').value) || 'Turno';
    var estrategia = '[spoiler=' + nombre + ' — estrategia]\n';
    bloques.forEach(function (bloque, indice) { estrategia += belicoConstruirBloque(bloque, indice) + '\n'; });
    estrategia += '[/spoiler]';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(estrategia).catch(function () { belicoMostrarTextarea(estrategia); });
    } else {
        belicoMostrarTextarea(estrategia);
    }
}

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

function belicoActualizarPasivas() {
    var seleccionadas = document.querySelectorAll('[data-belico-pasiva]:checked');
    var resumen = document.getElementById('belico-pasivas-resumen');
    if (resumen) {
        resumen.textContent = seleccionadas.length + (seleccionadas.length === 1 ? ' pasiva seleccionada' : ' pasivas seleccionadas');
    }
}

function belicoCopiarPasivas() {
    var seleccionadas = Array.prototype.slice.call(document.querySelectorAll('[data-belico-pasiva]:checked'));
    var salida = document.getElementById('belico-pasivas-textarea');
    if (!seleccionadas.length) {
        if (salida) {
            salida.hidden = false;
            salida.value = 'Selecciona al menos una pasiva para copiarla al post.';
            salida.focus();
        }
        return;
    }
    var texto = '[spoiler=Pasivas del turno]\n';
    seleccionadas.forEach(function (pasiva) {
        texto += '[tecnica=' + pasiva.value + ']\n';
    });
    texto += '[/spoiler]';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(function () {
            if (salida) { salida.hidden = true; }
        }).catch(function () {
            if (salida) { salida.hidden = false; salida.value = texto; salida.focus(); salida.select(); }
        });
    } else if (salida) {
        salida.hidden = false;
        salida.value = texto;
        salida.focus();
        salida.select();
    }
}

document.addEventListener('click', function (evento) {
    var tab = evento.target.closest('[data-belico-tab]');
    if (tab) {
        belicoActivarTab(tab.dataset.belicoTab);
        return;
    }
    var nuevo = evento.target.closest('[data-belico-nuevo-bloque]');
    if (nuevo) {
        document.getElementById('belico-turno-bloques').appendChild(belicoCrearBloque(nuevo.dataset.belicoNuevoBloque));
        belicoActualizarBloques();
        return;
    }
    var boton = evento.target.closest('[data-belico-accion-bloque]');
    if (boton) {
        var bloque = boton.closest('.belico-bloque-turno');
        var accion = boton.dataset.belicoAccionBloque;
        if (accion === 'quitar') { bloque.remove(); }
        if (accion === 'subir' && bloque.previousElementSibling) { bloque.parentNode.insertBefore(bloque, bloque.previousElementSibling); }
        if (accion === 'bajar' && bloque.nextElementSibling) { bloque.parentNode.insertBefore(bloque.nextElementSibling, bloque); }
        belicoActualizarBloques();
        return;
    }
    if (evento.target.closest('#belico-copiar-turno')) { belicoCopiarTurno(); }
    if (evento.target.closest('#belico-limpiar-turno')) {
        document.getElementById('belico-turno-bloques').replaceChildren();
        document.getElementById('belico-copiar-textarea').hidden = true;
        belicoActualizarBloques();
    }
    if (evento.target.closest('#belico-copiar-pasivas')) { belicoCopiarPasivas(); }
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

document.addEventListener('input', function (evento) {
    if (evento.target.closest('.belico-turno')) { belicoRevisarTurno(); }
});
document.addEventListener('change', function (evento) {
    if (evento.target.closest('.belico-turno')) { belicoRevisarTurno(); }
    if (evento.target.matches('[data-belico-pasiva]')) { belicoActualizarPasivas(); }
});
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
        case 'estadisticas': echo belico_render_estadisticas(belico_calc_estadisticas($atributos)); break;
        case 'estado':       echo belico_render_resultado_estado($mybb, $atributos); break;
        case 'ataque':       echo belico_render_resultado_ataque($mybb, $armas, $tecnicas, $atributos); break;
        case 'ataque_form':  echo belico_render_bloque_ataque($mybb, $armas, $tecnicas, $tid, $fid, $atributos); break;
        case 'reaccion':     echo belico_render_resultado_reaccion($mybb); break;
        case 'danio':        echo belico_render_resultado_danio($mybb, $recursos, $atributos); break;
        case 'caida':        echo belico_render_resultado_caida($mybb, $atributos); break;
        case 'asfixia':      echo belico_render_resultado_asfixia($mybb, $atributos); break;
        case 'lanzamiento':  echo belico_render_resultado_lanzamiento($mybb, $atributos); break;
        case 'agarre':       echo belico_render_resultado_agarre($mybb, $atributos); break;
        case 'iniciativa':   echo belico_render_resultado_iniciativa($mybb, $atributos); break;
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
$contenido .= '<button id="belico-tab-ataque" type="button" role="tab" aria-selected="true" aria-controls="belico-panel-ataque" data-belico-tab="ataque">Ataque</button>';
$contenido .= '<button id="belico-tab-turno" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-turno" data-belico-tab="turno" tabindex="-1">Constructor de turno</button>';
$contenido .= '<button id="belico-tab-pasivas" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-pasivas" data-belico-tab="pasivas" tabindex="-1">Pasivas</button>';
$contenido .= '<button id="belico-tab-reaccion" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-reaccion" data-belico-tab="reaccion" tabindex="-1">Reacción</button>';
$contenido .= '<button id="belico-tab-utilidades" type="button" role="tab" aria-selected="false" aria-controls="belico-panel-utilidades" data-belico-tab="utilidades" tabindex="-1">Utilidades</button>';
$contenido .= '</div>';
$contenido .= '<section id="belico-panel-ataque" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-ataque" data-belico-panel="ataque">';
$contenido .= belico_render_bloque_ataque($mybb, $armas, $tecnicas, $tid, $fid, $atributos);
$contenido .= '</section>';
$estadisticas_turno = belico_calc_estadisticas($atributos);
$contenido .= '<section id="belico-panel-turno" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-turno" data-belico-panel="turno" hidden>';
$contenido .= belico_render_bloque_registro($estadisticas_turno['movimiento']);
$contenido .= '</section>';
$contenido .= '<section id="belico-panel-pasivas" class="belico-tab-panel" role="tabpanel" aria-labelledby="belico-tab-pasivas" data-belico-panel="pasivas" hidden>';
$contenido .= belico_render_bloque_pasivas($tecnicas);
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
