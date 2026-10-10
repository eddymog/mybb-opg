<?php
/**
 * Pruebas de los cálculos bélicos y del turno (sin base de datos ni navegador).
 *
 *   php tests/belico/calculos_y_turno.php
 *
 * Carga inc/plugins/lib/belico_calculos.php y las funciones de op/belico.php (todo lo que hay antes del
 * ruteo, sin ejecutar la página). Sale con código 1 si algo falla.
 * Ver docs/303_ImplementationPlan_BelicoMejoras.md y docs/304_ImplementationPlan_TurnoBelico.md.
 */

class MyBB
{
    const INPUT_STRING = 1; const INPUT_INT = 2; const INPUT_ARRAY = 3;
    public $in = array();
    function get_input($k, $t = 1) { return isset($this->in[$k]) ? $this->in[$k] : ''; }
}

$raiz = dirname(__DIR__, 2);
require $raiz . '/inc/plugins/lib/belico_calculos.php';
$fuente = file_get_contents($raiz . '/op/belico.php');
$desde = strpos($fuente, 'function belico_reglas()');
$hasta = strpos($fuente, '// Ruteo principal');
$hasta = strrpos(substr($fuente, 0, $hasta), "\n// ─");
eval(substr($fuente, $desde, $hasta - $desde));

$fallos = 0;
function comprobar($condicion, $mensaje)
{
    global $fallos;
    echo ($condicion ? 'OK    ' : 'FALLA ') . $mensaje . "\n";
    if (!$condicion) { $fallos++; }
}
function igual($obtenido, $esperado, $mensaje)
{
    comprobar($obtenido === $esperado, $mensaje . ($obtenido === $esperado ? '' : ' (obtenido: ' . var_export($obtenido, true) . ', esperado: ' . var_export($esperado, true) . ')'));
}

// ── Fórmulas en texto ──────────────────────────────────────────────────────
$at = array('NIV' => 29, 'FUE' => 80, 'AGI' => 15, 'PUN' => 110, 'DES' => 50, 'RES' => 40, 'REF' => 5, 'VOL' => 85);
$golpe = array('valor' => 40, 'tipo' => 'cortante', 'pasos' => array(), 'configuracion' => '1 arma');
$con_valores = function ($texto) use ($at, $golpe) {
    return opg_belico_aplicar_valores($texto, opg_belico_sustituciones_texto($texto, $at, $golpe, $golpe));
};
echo "== Fórmulas en texto\n";
igual($con_valores('+[Nivel] de Daño y 10 [Tasa de Acierto]'), '+29 de Daño y 10 [Tasa de Acierto]', 'solo se reemplaza la fórmula');
igual($con_valores('+[FUE/3] de [Daño] y +[RES/3] de Defensa Pasiva'), '+27 de [Daño] y +14 de Defensa Pasiva', 'signo dentro de la fórmula en daño y en efectos');
igual($con_valores('+[VOLx0,15] de [Reflejos] | Área: [VOLx7] metros.'), '+13 de [Reflejos] | Área: 595 metros.', 'separador | y unidades');
igual($con_valores('-Nivel/2 [Puntos de Energía]'), '-15 [Puntos de Energía]', 'fórmula sin corchetes');
igual($con_valores('igual a la [VOL/3] del usuario, alcance [Nivel/2] metros'), 'igual a la 29 del usuario, alcance 15 metros', 'fórmulas sueltas en descripciones');
igual($con_valores('Inflige [Golpe Básico] + [2 x Nivel] Daño Cortante.'), 'Inflige 98 Daño Cortante.', 'golpe básico más nivel');
foreach (array('-10 [Voluntad]', '-10 [VOL]', '+3 [FUE] en la comparativa', '+16 de [Tasa de Acierto]', 'Aumenta [FUERZA] de [Daño Sónico]') as $texto) {
    igual($con_valores($texto), $texto, 'sin cálculo: ' . $texto);
}

// ── Bonos ──────────────────────────────────────────────────────────────────
echo "== Bonos\n";
$bonos = opg_belico_extraer_bonos('+[FUE/3] de [Daño] y +[RES/3] de Defensa Pasiva', $at);
igual(array($bonos[0]['k'], $bonos[0]['v'], $bonos[1]['k'], $bonos[1]['v']), array('dano', 27.0, 'defensa pasiva', 14.0), 'daño y defensa pasiva');
$bonos = opg_belico_extraer_bonos('+10 [TA] y -5 [Reflejos]', $at);
igual(array($bonos[0]['k'], $bonos[0]['v'], $bonos[1]['v']), array('tasa de acierto', 10.0, -5.0), 'TA unificada y bono negativo');

// ── Armas y TA ─────────────────────────────────────────────────────────────
echo "== Armas y TA\n";
$arma = function ($id, $sub, $escalado = '', $efecto = '') {
    return array('objeto_id' => $id, 'nombre' => $id, 'apodo' => '', 'editado' => 0, 'cantidad' => 1, 'categoria' => 'Armas', 'subcategoria' => $sub, 'tier' => 1,
        'dano' => '20', 'bloqueo' => '10', 'efecto' => $efecto, 'alcance' => '', 'escalado' => $escalado, 'requisitos' => '');
};
$a = array('A' => $arma('A', 'Armas de Filo', '[Tasa de Acierto = Destreza]'), 'B' => $arma('B', 'Armas de Filo', '[Tasa de Acierto = Agilidad]'),
    'C' => $arma('C', 'Armas de Filo', '[Tasa de Acierto = Puntería]', '+5 Tasa de Acierto'), 'AM' => $arma('AM', 'Amuletos'), 'F' => $arma('F', 'Armas de Fuego'));
$sel = opg_belico_seleccionar_armas($a, 'dos_armas', array('A', 'B'));
igual(opg_belico_calcular_tasa_acierto($sel['armas'], $at)['valor'], 15, 'dos armas (Destreza 50 y Agilidad 15): la TA más baja y sin penalización');
$sel = opg_belico_seleccionar_armas($a, 'tres_armas', array('A', 'B', 'C'));
igual(opg_belico_calcular_tasa_acierto($sel['armas'], $at)['valor'], 0, 'tres armas: la más baja (15) menos 5 por arma');
igual(opg_belico_calcular_tasa_acierto(array($a['C']), $at)['valor'], 115, 'escalado de Puntería y modificador del arma');
igual(opg_belico_calcular_tasa_acierto(array(), $at, 45)['valor'], 5, 'sin arma (Agilidad 15) a 45 m: -5 por cada 20 m completos');
$dos = opg_belico_calcular_golpe_basico(array($a['A']), $at, 'dos_manos');
igual($dos['valor'], 30, 'una arma a dos manos: +50%');
$fuego = opg_belico_calcular_golpe_basico(array($a['F']), $at, 'dos_manos');
igual($fuego['valor'], 20, 'armas de fuego no reciben el bono de dos manos');
$avisos = opg_belico_avisos_armas(opg_belico_seleccionar_armas($a, 'dos_armas', array('AM', 'AM')), $at);
comprobar(count($avisos) >= 1, 'misma arma repetida sin copias genera aviso');

// ── Turno ──────────────────────────────────────────────────────────────────
echo "== Turno\n";
$mk = function ($tid, $nombre, $clase, $tipo, $ef, $en, $en_turno = '', $haki = '', $haki_turno = '', $enf = '0') {
    return array('tid' => $tid, 'nombre' => $nombre, 'clase' => $clase, 'tipo' => $tipo, 'efectos' => $ef, 'energia' => $en, 'energia_turno' => $en_turno, 'haki' => $haki,
        'haki_turno' => $haki_turno, 'enfriamiento' => $enf, 'estilo' => 'Combatiente', 'rama' => '', 'tier' => '1', 'descripcion' => '');
};
$tecnicas = array();
foreach (array(
    $mk('AT1', 'Ataque', 'Activa', 'Ofensiva', '20 Daño Cortante', '30', '', '', '', '2'),
    $mk('UT1', 'Utilidad', 'Activa', 'Utilidad', '+20 de [Daño]', '10'),
    $mk('MA1', 'Mantenida', 'Mantenida', 'Utilidad', '+5 [Reflejos]', '10', '6'),
    $mk('MA2', 'Mantenida sin activación', 'Mantenida', 'Utilidad', 'x', '', '12'),
    $mk('BUSO1', 'Haki', 'Mantenida', 'Utilidad', 'x', '', '', '13', '9'),
    $mk('PASIVA', 'Pasiva', 'Pasiva', '-', '+[FUE/3] de [Daño]', ''),
) as $t) { $t['grupo'] = belico_grupo_tecnica($t); $t['calcula'] = true; $tecnicas[$t['tid']] = $t; }
$calcular = function ($crudo) use ($tecnicas, $at) {
    $estado = belico_normalizar_turno($crudo, $tecnicas);
    return belico_calcular_turno($estado, $tecnicas, array(), $at, array('energia_max' => 100, 'haki_max' => 50));
};
$base = array('recursos' => array('energia' => 100, 'haki' => 50));

$r = $calcular($base + array('continuan' => array('MA1'), 'bloques' => array()));
igual($r['energia_final'], 94, 'mantenida que continúa cobra su coste por turno');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'MA1'))))));
igual($r['energia_final'], 90, 'mantenida nueva cobra el primer coste');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'MA2'))))));
igual(array($r['energia_final'], count($r['bloques'][0]['avisos'])), array(88, 1), 'mantenida sin primer coste cobra el coste por turno y avisa');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'AT1'), array('tid' => 'BUSO1'))))));
igual(array($r['energia_final'], $r['haki_final'], $r['bloques'][0]['tipo']), array(70, 37, 'con técnica'), 'instantánea dentro del bloque: cobra y no cuenta para el bloque');
comprobar(count($r['bloques'][0]['avisos']) === 0, 'la instantánea no genera avisos de límite');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('accion' => 'golpe'), array('accion' => 'golpe'), array('accion' => 'golpe'))))));
comprobar(count($r['bloques'][0]['avisos']) === 1, 'tres acciones en un bloque sin técnica avisan');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'AT1'))), array('pasos' => array(array('tid' => 'AT1'))))));
comprobar(count($r['bloques'][1]['avisos']) >= 1, 'técnica repetida con enfriamiento avisa');
$r = $calcular(array('recursos' => array('energia' => 20, 'haki' => 0), 'bloques' => array(array('pasos' => array(array('tid' => 'AT1'))))));
comprobar(count($r['bloques'][0]['avisos']) === 1 && $r['energia_final'] < 0, 'recursos negativos solo avisan');
$r = $calcular($base + array('bloques' => array(array('mov_previo' => 20, 'pasos' => array()))));
igual(array($r['bloques'][0]['tipo'], count($r['bloques'][0]['avisos'])), array('sin técnica', 0), 'moverse solo es un bloque válido sin técnica');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'AT1'), array('tid' => 'UT1'), array('tid' => 'MA1'))))));
comprobar(count($r['bloques'][0]['avisos']) >= 1, 'bloque con técnica admite una sola utilidad o acción adicional');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'UT1'), array('tid' => 'MA1'), array('accion' => 'bloqueo'))))));
igual(count($r['bloques'][0]['avisos']), 0, 'bloque de utilidad: dos técnicas y un bloqueo básico');
$r = $calcular($base + array('bloques' => array(array('pasos' => array(array('tid' => 'UT1'), array('accion' => 'golpe'))))));
comprobar(count($r['bloques'][0]['avisos']) >= 1, 'bloque de utilidad no admite un golpe básico');
$r = $calcular($base + array('bloques' => array(array('interrumpido' => true, 'mov_previo' => 30, 'pasos' => array(array('tid' => 'AT1'))))));
igual(array($r['energia_final'], strpos($r['resumen_html'], '0 de') !== false), array(100, true), 'bloque interrumpido no cuesta recursos ni movimiento');
$r = $calcular($base + array('bloques' => array(
    array('anterior' => true, 'reaccion' => array('ta' => 70), 'pasos' => array()),
    array('anterior' => true, 'reaccion' => array('ta' => 70), 'pasos' => array()),
)));
comprobar(strpos($r['bloques'][1]['html'], '+5 por 1 bloque sin cortar') !== false, 'reacciones: +5 de Reflejos por bloque sin cortar');
$r = $calcular($base + array('bloques' => array(
    array('anterior' => true, 'reaccion' => array('ta' => 70, 'corto' => true), 'pasos' => array()),
    array('anterior' => true, 'reaccion' => array('ta' => 70), 'pasos' => array()),
)));
comprobar(strpos($r['bloques'][1]['html'], 'sin cortar') === false, 'cortar la cadena reinicia el +5');
// Enfriamiento N = la técnica queda bloqueada durante los N turnos siguientes al uso.
$r = $calcular($base + array('enfriamientos' => array('AT1' => 2), 'bloques' => array(array('pasos' => array(array('tid' => 'AT1'))))));
comprobar(strpos(implode(' ', $r['bloques'][0]['avisos']), 'sigue en enfriamiento: faltan 2 turnos') !== false, 'técnica que arrastra enfriamiento avisa al usarla');
$r = $calcular($base + array('enfriamientos' => array('UT1' => 3, 'MA1' => 1), 'bloques' => array(array('pasos' => array(array('tid' => 'AT1'))))));
igual($r['siguiente']['enfriamientos'], array('UT1' => 2, 'AT1' => 2), 'siguiente turno: lo usado queda N turnos, lo arrastrado baja uno y se quita al llegar a 0');
$r = $calcular($base + array('bloques' => array(array('interrumpido' => true, 'pasos' => array(array('tid' => 'AT1'))))));
igual($r['siguiente']['enfriamientos'], array(), 'un bloque interrumpido no deja enfriamiento');
igual(in_array('filo', opg_belico_tipos_con_comodin(array('wazamono')), true) && in_array('tension', opg_belico_tipos_con_comodin(array('wazamono')), true), true, 'Wazamono cumple cualquier requisito de tipo de arma');
igual(opg_belico_tipos_con_comodin(array('filo')), array('filo'), 'un arma normal solo cumple su tipo');
$r = $calcular($base + array('continuan' => array('MA1'), 'bloques' => array(array('pasos' => array(array('tid' => 'MA2'))))));
igual($r['siguiente']['continuan'], array('MA1', 'MA2'), 'siguiente turno: las que continúan más las nuevas');
$r = $calcular(array('nombre' => 'Turno [1]=x', 'bloques' => 'basura', 'continuan' => 7));
igual(strpos($r['bbcode'], '[spoiler=Turno (1)-x]') === 0, true, 'entradas basura y nombre sin corchetes');

// ── Clasificación de técnicas ──────────────────────────────────────────────
echo "== Clasificación\n";
foreach (array('BUSO101', 'KENB201', 'HAO301', 'AER301', 'AQU301', 'CRY301', 'ELE301', 'PIR301') as $tid) {
    comprobar(opg_belico_es_instantanea(array('tid' => $tid)), $tid . ' es instantánea');
}
comprobar(!opg_belico_es_instantanea(array('tid' => 'DEP201')), 'una técnica normal no es instantánea');

echo $fallos ? "\n$fallos prueba(s) fallaron.\n" : "\nTodo en orden.\n";
exit($fallos ? 1 : 0);
