<?php
/**
 * Pruebas de paridad de inc/plugins/lib/recompensas_aventura.php con
 * op/staff/recompensasAventuras.php (sin base de datos).
 *
 *   php tests/aventuras/recompensas.php
 *
 * Los "oráculos" de abajo son una transcripción de las fórmulas de la página
 * antigua (PHP y JavaScript del template). La comparación con temas reales ya
 * pagados (fase 1 del plan) se hace aparte, con datos de producción.
 */

define('TABLE_PREFIX', 'mybb_');
define('TIME_NOW', 1800000000);
class FakeDb
{
    public function escape_string($s) { return addslashes($s); }
    public function escape_string_like($s) { return addcslashes(addslashes($s), '%_'); }
}
$db = new FakeDb();
function is_mod($u) { return false; }
function is_staff($u) { return false; }
function is_narra($u) { return false; }
require __DIR__ . '/../../inc/plugins/lib/aventuras.php';
require __DIR__ . '/../../inc/plugins/lib/recompensas_aventura.php';

$fallos = 0;
$total = 0;
function prueba($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos++;
        echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
    }
}
function prueba_aprox($nombre, $obtenido, $esperado, $tol = 0.0001)
{
    global $fallos, $total;
    $total++;
    if (abs($obtenido - $esperado) > $tol) {
        $fallos++;
        echo "FALLA: $nombre\n  esperado: $esperado\n  obtenido: $obtenido\n";
    }
}

// ── 1. Las tablas de recompensas son las de la página antigua ────────────────
$vieja = file_get_contents(__DIR__ . '/../../op/staff/recompensasAventuras.php');
$niveles = array(1 => 'APRENDIZ', 2 => 'ESTUDIOSO', 3 => 'ILUSTRE', 4 => 'ERUDITO');
foreach ($niveles as $nivel => $nombre) {
    preg_match("/define\('RECOMPENSAS_NARRADOR_{$nombre}', array\((.*?)\n    \)\);/s", $vieja, $m);
    preg_match_all("/(\d+) => array\('experiencia' => (\d+), 'nikas' => (\d+), 'cofres' => array\('objeto_id' => \"(\w+)\", 'cantidad' => (\d+)\)\)/", $m[1], $filas, PREG_SET_ORDER);
    prueba("narrador nivel $nivel: 10 tiers", count($filas), 10);
    foreach ($filas as $f) {
        $esp = array('experiencia' => (int) $f[2], 'nikas' => (int) $f[3], 'cofre' => $f[4], 'cofre_cantidad' => (int) $f[5]);
        prueba("narrador nivel $nivel tier {$f[1]}", OPG_REC_NARRADOR[$nivel][(int) $f[1]], $esp);
    }
}
preg_match("/define\('RECOMPENSAS_JUGADOR', array\((.*?)\n    \)\);/s", $vieja, $m);
preg_match_all("/(\d+) => array\('experiencia' => (\d+), 'nikas' => (\d+), 'reputacion' => (\d+), 'berries' => (\d+)\)/", $m[1], $filas, PREG_SET_ORDER);
prueba('jugador: 10 tiers', count($filas), 10);
foreach ($filas as $f) {
    prueba("jugador tier {$f[1]}", OPG_REC_JUGADOR[(int) $f[1]], array('experiencia' => (int) $f[2], 'nikas' => (int) $f[3], 'reputacion' => (int) $f[4], 'berries' => (int) $f[5]));
}
preg_match('/\$posts_por_tier = array\((.*?)\);/s', $vieja, $m);
preg_match_all('/(\d+) => (\d+)/', $m[1], $pares, PREG_SET_ORDER);
$esperado_posts = array();
foreach ($pares as $p) { $esperado_posts[(int) $p[1]] = (int) $p[2]; }
prueba('posts esperados por tier', OPG_REC_POSTS_TIER, $esperado_posts);
preg_match('/\$limite_mensual = (\d+);/', $vieja, $m);
prueba('tope mensual igual al de la página antigua', OPG_REC_EXP_NARRADOR_MENSUAL, (int) $m[1]);
prueba('tope mensual es 675', OPG_REC_EXP_NARRADOR_MENSUAL, 675);
preg_match('/\$caracteres >= (\d+)/', $vieja, $m);
prueba('umbral de caracteres igual', OPG_REC_MIN_CARACTERES, (int) $m[1]);

// ── 2. Porcentaje base (JS del template) ─────────────────────────────────────
function oraculo_pct_base($validos, $esperados)
{
    if ($esperados <= 0) return 100.0;
    if ($validos <= $esperados) { $p = ($validos / $esperados) * 100; }
    else { $p = 100 + (($validos - $esperados) * (100 / $esperados / 2)); }
    return max(0, min(150, $p));
}
foreach (OPG_REC_POSTS_TIER as $tier => $esp) {
    for ($v = -5; $v <= 40; $v++) {
        prueba_aprox("pct base tier $tier validos $v", opg_rec_porcentaje_base($v, $esp), (float) oraculo_pct_base($v, $esp));
    }
}
prueba('base: igual a lo esperado', opg_rec_porcentaje_base(5, 5), 100.0);
prueba('base: 3 de 5', opg_rec_porcentaje_base(3, 5), 60.0);
prueba('base: 7 de 5 (cada extra vale 10)', opg_rec_porcentaje_base(7, 5), 120.0);
prueba('base: tope 150', opg_rec_porcentaje_base(40, 5), 150.0);
prueba('base: cero', opg_rec_porcentaje_base(0, 5), 0.0);
prueba('base: negativos se limitan a 0', opg_rec_porcentaje_base(-4, 5), 0.0);
prueba('base: sin esperados', opg_rec_porcentaje_base(3, 0), 100.0);

// ── 3. Porcentaje final de narradores y jugadores ───────────────────────────
$analisis = array('ok' => true, 'usuarios' => array(
    10 => array('uid' => 10, 'username' => 'Narra', 'posts' => 12, 'validos' => 10, 'es_narrador' => true),
    20 => array('uid' => 20, 'username' => 'Jugador', 'posts' => 8, 'validos' => 4, 'es_narrador' => false),
    30 => array('uid' => 30, 'username' => 'Otro', 'posts' => 9, 'validos' => 9, 'es_narrador' => false),
));
$aj = opg_rec_normalizar_ajustes(array('tier' => 3, 'extra_jugadores' => '20', 'destinatarios' => array(20 => array('variacion' => '2'))), 1, 'positiva', 3);
prueba('ajustes sin errores', $aj['errores'], array());
$pct = opg_rec_calcular_porcentajes($analisis, $aj['ajustes']);   // tier 3: 6 posts esperados
prueba_aprox('narrador: 10 de 6 + extra 20', $pct[10]['porcentaje'], max(0, min(200, oraculo_pct_base(10, 6) + 20)), 0.005);
prueba_aprox('jugador con variación +2: 6 de 6', $pct[20]['porcentaje'], 100.0, 0.005);
prueba_aprox('jugador: 9 de 6 (125, sin extra)', $pct[30]['porcentaje'], oraculo_pct_base(9, 6), 0.005);
prueba('narrador: tope final 200', opg_rec_calcular_porcentajes($analisis, array('tier' => 1, 'extra_jugadores' => 50, 'destinatarios' => array()))[10]['porcentaje'], 200.0);
prueba('el jugador no recibe el extra', opg_rec_calcular_porcentajes($analisis, array('tier' => 3, 'extra_jugadores' => 50, 'destinatarios' => array()))[30]['porcentaje'], 125.0);
$minimos = opg_rec_minimos($analisis, array('tier' => 3, 'n_participantes' => 3));
prueba('mínimos: 6 x 3', $minimos['minimo'], 18);
prueba('mínimos: válidos totales', $minimos['validos'], 23);
prueba('mínimos: cumple', $minimos['cumple'], true);
prueba('mínimos: no cumple', opg_rec_minimos($analisis, array('tier' => 10, 'n_participantes' => 3))['cumple'], false);

// ── 4. Validación de ajustes ─────────────────────────────────────────────────
prueba('ajustes: tier por defecto', opg_rec_normalizar_ajustes(array(), 4, 'negativa', 2)['ajustes']['tier'], 4);
prueba('ajustes: tier inválido', count(opg_rec_normalizar_ajustes(array('tier' => '11'), 4, 'positiva', 2)['errores']) > 0, true);
prueba('ajustes: extra inválido', count(opg_rec_normalizar_ajustes(array('extra_jugadores' => '15'), 4, 'positiva', 2)['errores']) > 0, true);
prueba('ajustes: extra válido', opg_rec_normalizar_ajustes(array('extra_jugadores' => '50'), 4, 'positiva', 2)['ajustes']['extra_jugadores'], 50);
prueba('ajustes: participantes 0', count(opg_rec_normalizar_ajustes(array('n_participantes' => '0'), 4, 'positiva', 2)['errores']) > 0, true);
prueba('ajustes: participantes 21', count(opg_rec_normalizar_ajustes(array('n_participantes' => '21'), 4, 'positiva', 2)['errores']) > 0, true);
prueba('ajustes: participantes detectados', opg_rec_normalizar_ajustes(array(), 4, 'positiva', 7)['ajustes']['n_participantes'], 7);
prueba('ajustes: variación 101', count(opg_rec_normalizar_ajustes(array('destinatarios' => array(5 => array('variacion' => '101'))), 4, 'positiva', 2)['errores']) > 0, true);
prueba('ajustes: variación -100', opg_rec_normalizar_ajustes(array('destinatarios' => array(5 => array('variacion' => '-100'))), 4, 'positiva', 2)['ajustes']['destinatarios'][5]['variacion'], -100);
prueba('ajustes: reputación inválida', count(opg_rec_normalizar_ajustes(array('destinatarios' => array(5 => array('rep' => 'rara'))), 4, 'positiva', 2)['errores']) > 0, true);
prueba('ajustes: reputación por defecto del narrador', opg_rec_normalizar_ajustes(array('destinatarios' => array(5 => array())), 4, 'negativa', 2)['ajustes']['destinatarios'][5]['rep'], 'negativa');
prueba('ajustes: excluir', opg_rec_normalizar_ajustes(array('destinatarios' => array(5 => array('incluir' => '0'))), 4, 'positiva', 2)['ajustes']['destinatarios'][5]['incluir'], false);
prueba('ajustes: un porcentaje del cliente se ignora', array_key_exists('porcentaje', opg_rec_normalizar_ajustes(array('porcentaje' => 999, 'destinatarios' => array(5 => array('porcentaje' => 999))), 4, 'positiva', 2)['ajustes']['destinatarios'][5]), false);

// ── 5. Recompensas de narrador (oráculo = código de la página antigua) ───────
function oraculo_narrador($nivel, $tier, $pct, $exp_mensual)
{
    $tablas = array(1 => OPG_REC_NARRADOR[1], 2 => OPG_REC_NARRADOR[2], 3 => OPG_REC_NARRADOR[3], 4 => OPG_REC_NARRADOR[4]);
    $b = $tablas[$nivel][$tier];
    $exp_final = round($b['experiencia'] * ($pct / 100));
    $nikas_final = round($b['nikas'] * ($pct / 100));
    $limite_mensual = 675;
    $exp_disponible = max(0, $limite_mensual - $exp_mensual);
    $exp_a_entregar = min($exp_final, $exp_disponible);
    $exp_excedente = $exp_final - $exp_a_entregar;
    $kuros = ($exp_excedente > 0) ? (int) floor($exp_excedente / 2) : 0;
    return array((int) $exp_a_entregar, (int) $nikas_final, $kuros, (int) $exp_excedente);
}
$etiquetas = array(1 => 'Aprendiz', 2 => 'Estudioso', 3 => 'Ilustre', 4 => 'Erudito');
$combinaciones = 0;
foreach ($etiquetas as $nivel => $etq) {
    for ($tier = 1; $tier <= 10; $tier++) {
        foreach (array(0.0, 33.33, 50.0, 87.5, 100.0, 125.0, 150.0, 175.0, 200.0) as $pct) {
            foreach (array(0, 300, 600, 625, 650, 675, 700) as $mensual) {
                $r = opg_rec_calcular_narrador(array('nivelnarrador' => $etq), $pct, $tier, $mensual);
                $o = oraculo_narrador($nivel, $tier, $pct, $mensual);
                $combinaciones++;
                prueba("narrador $etq t$tier $pct% mensual $mensual", array($r['experiencia'], $r['nikas'], $r['kuros'], $r['excedente']), $o);
            }
        }
    }
}
echo "  ($combinaciones combinaciones de narrador comparadas)\n";
$r = opg_rec_calcular_narrador(array('nivelnarrador' => 'Erudito'), 100.0, 3, 625);   // exp 170
prueba('tope: 625 acumulados y 170 por pagar entrega 50', $r['experiencia'], 50);
prueba('tope: el excedente son 120', $r['excedente'], 120);
prueba('tope: 60 kuros', $r['kuros'], 60);
$r = opg_rec_calcular_narrador(array('nivelnarrador' => 'Aprendiz'), 100.0, 3, 675);  // exp 120
prueba('tope: con el contador lleno no se entrega experiencia', $r['experiencia'], 0);
prueba('tope: todo el excedente pasa a kuros (mitad)', $r['kuros'], 60);
prueba('tope: no suma expNarradorMensualActual si no hay experiencia', in_array('expNarradorMensualActual = expNarradorMensualActual + 0', $r['sets_ficha'], true), false);
prueba('nivel desconocido cae a Aprendiz', opg_rec_calcular_narrador(array('nivelnarrador' => 'Inventado'), 100.0, 1, 0)['nivel'], 1);
prueba('nivel desconocido se marca', opg_rec_calcular_narrador(array('nivelnarrador' => 'Inventado'), 100.0, 1, 0)['nivel_conocido'], false);
prueba('ficha sin nivel', opg_rec_calcular_narrador(array(), 100.0, 1, 0)['nivel'], 1);
prueba('el cofre no depende del porcentaje', opg_rec_calcular_narrador(array('nivelnarrador' => 'Aprendiz'), 10.0, 3, 0)['cofre_cantidad'], 2);

// ── 6. Recompensas de jugador (oráculo = código de la página antigua) ────────
function oraculo_jugador($tier, $pct, $infra, $rep, $contra, $arq)
{
    $b = OPG_REC_JUGADOR[$tier];
    $contrabandistaMult = 1;
    if ($contra == 0) { $contrabandistaMult = 1.25; }
    else if ($contra == 1) { $contrabandistaMult = 1.50; }
    else if ($contra == 2) { $contrabandistaMult = 1.75; }
    else if ($contra == 3) { $contrabandistaMult = 2.00; }
    $arqueologoExtraXP = 1; $arqueologoExtraNikas = 0;
    if ($arq == 0) { $arqueologoExtraXP = 1.00; }
    else if ($arq == 1) { $arqueologoExtraXP = 1.10; }
    else if ($arq == 2) { $arqueologoExtraNikas = 1; }
    $exp = round($b['experiencia'] * (($pct * $arqueologoExtraXP) / 100));
    $nikas = round(($b['nikas'] + $arqueologoExtraNikas) * (($pct) / 100));
    $repu = ($rep === 'ninguna') ? 0 : round($b['reputacion'] * ($pct / 100));
    if ($infra) { $berries = round($b['berries'] * ($pct / 100) * $contrabandistaMult * 1.25); }
    else { $berries = round($b['berries'] * ($pct / 100) * $contrabandistaMult); }
    return array((int) $exp, (int) $nikas, (int) $repu, (int) $berries);
}
$combinaciones = 0;
foreach (array(-1, 0, 1, 2, 3) as $contra) {
    foreach (array(-1, 0, 1, 2) as $arq) {
        $oficios = array();
        if ($contra > -1) { $oficios['Mercader'] = array('sub' => array('Contrabandista' => $contra)); }
        if ($arq > -1) { $oficios['Investigador'] = array('sub' => array('Arqueólogo' => $arq)); }
        $ficha = array('oficios' => $oficios ? json_encode($oficios, JSON_UNESCAPED_UNICODE) : '');
        for ($tier = 1; $tier <= 10; $tier++) {
            foreach (array(0.0, 37.5, 66.67, 100.0, 133.33, 200.0) as $pct) {
                foreach (array(false, true) as $infra) {
                    foreach (array('positiva', 'negativa', 'ninguna') as $rep) {
                        $r = opg_rec_calcular_jugador($ficha, $pct, $tier, $infra, $rep);
                        $combinaciones++;
                        prueba("jugador c$contra a$arq t$tier $pct% infra=" . (int) $infra . " $rep",
                            array($r['experiencia'], $r['nikas'], $r['reputacion'], $r['berries']), oraculo_jugador($tier, $pct, $infra, $rep, $contra, $arq));
                    }
                }
            }
        }
    }
}
echo "  ($combinaciones combinaciones de jugador comparadas)\n";
$r = opg_rec_calcular_jugador(array('oficios' => ''), 100.0, 1, true, 'positiva');
prueba('jugador T1 100% infra', array($r['experiencia'], $r['nikas'], $r['reputacion'], $r['berries']), array(50, 5, 10, 1250000));
prueba('jugador: sets de ficha con inframundo', in_array('movidoInframundo = COALESCE(movidoInframundo, 0) + 1250000', $r['sets_ficha'], true), true);
prueba('jugador: reputación positiva suma a las dos columnas', in_array('reputacion_positiva = reputacion_positiva + 10', $r['sets_ficha'], true) && in_array('reputacion = reputacion + 10', $r['sets_ficha'], true), true);
$r = opg_rec_calcular_jugador(array('oficios' => ''), 100.0, 1, false, 'negativa');
prueba('jugador: reputación negativa', in_array('reputacion_negativa = reputacion_negativa + 10', $r['sets_ficha'], true), true);
$r = opg_rec_calcular_jugador(array('oficios' => ''), 100.0, 1, false, 'ninguna');
prueba('jugador: sin reputación no toca las columnas', count(array_filter($r['sets_ficha'], function ($s) { return strpos($s, 'reputacion') === 0; })), 0);
prueba('jugador: sin reputación el monto es 0', $r['reputacion'], 0);
$r = opg_rec_calcular_jugador(array('oficios' => 'no es json'), 100.0, 1, false, 'positiva');
prueba('oficios inválidos no rompen', $r['nivel_contrabandista'], -1);
$r = opg_rec_calcular_jugador(array('oficios' => '{"Mercader":{"sub":{}}}'), 100.0, 1, false, 'positiva');
prueba('Mercader sin Contrabandista cuenta como sin oficio', array($r['nivel_contrabandista'], $r['mult_contrabandista']), array(-1, 1));

// ── 7. Mensaje del tema ──────────────────────────────────────────────────────
$calc_n = opg_rec_calcular_narrador(array('nivelnarrador' => 'Erudito'), 120.0, 3, 625);
$calc_j = opg_rec_calcular_jugador(array('oficios' => ''), 100.0, 3, true, 'positiva');
$msg = opg_rec_mensaje_resumen(array(
    'tier' => 3, 'staff_uid' => 50, 'staff_nombre' => 'Staff', 'extra_jugadores' => 20,
    'narradores' => array(array('uid' => 10, 'username' => 'Narra', 'variacion' => 0, 'calculo' => $calc_n)),
    'jugadores' => array(array('uid' => 20, 'username' => 'Jugador', 'variacion' => 0, 'calculo' => $calc_j)),
));
prueba('mensaje: lleva el marcador que reconoce la herramienta antigua', strpos($msg, 'RECOMPENSAS PROCESADAS') !== false, true);
prueba('mensaje: usa el cierre narrativo y conserva el tier',
    array(strpos($msg, '¡LA TRAVESÍA HA LLEGADO A SU FIN!') !== false, strpos($msg, '[b]Tier:[/b] Tier 3') !== false),
    array(true, true));
prueba('mensaje: avisa del límite mensual', strpos($msg, 'Límite mensual') !== false, true);
prueba('mensaje: usa el tope de 675', strpos($msg, ' / 675') !== false, true);
prueba('mensaje: bono de inframundo', strpos($msg, 'Bono inframundo 25%') !== false, true);
prueba('mensaje: reputación positiva inspira respeto y admiración', strpos($msg, 'inspiran respeto y admiración') !== false, true);
$calc_j_neg = opg_rec_calcular_jugador(array('oficios' => ''), 100.0, 3, false, 'negativa');
$msg_neg = opg_rec_mensaje_resumen(array(
    'tier' => 3, 'staff_uid' => 50, 'staff_nombre' => 'Staff', 'extra_jugadores' => 0,
    'narradores' => array(),
    'jugadores' => array(array('uid' => 20, 'username' => 'Jugador', 'variacion' => 0, 'calculo' => $calc_j_neg)),
));
prueba('mensaje: reputación negativa inspira miedo y respeto', strpos($msg_neg, 'inspiran miedo y respeto') !== false, true);

echo ($fallos === 0 ? 'OK' : 'CON FALLOS') . ": $total pruebas, $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
