<?php
/**
 * Prueba pura de los avisos de recompensas y salarios (issue #20): datos, resumen, claves y agrupacion.
 * Uso: php tests/notificaciones/recompensas.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/recompensas_avisos.php';

$failures = array();
function rc_igual($obtenido, $esperado, $mensaje)
{
    global $failures;
    if ($obtenido !== $esperado) {
        $failures[] = $mensaje . "\n    esperado: " . var_export($esperado, true) . "\n    obtenido: " . var_export($obtenido, true);
    }
}

// ── Formato de miles ──
rc_igual(opg_notif_numero(1000000), '1.000.000', 'miles con punto');
rc_igual(opg_notif_numero(500), '500', 'sin miles');
rc_igual(opg_notif_numero(33.0), '33', 'float entero sin decimales');
rc_igual(opg_notif_numero(33.5), '33,5', 'decimales con coma');
rc_igual(opg_notif_numero(1234.25), '1.234,25', 'miles y decimales');

// ── Datos de recompensa: importes cero, negativos o no numericos se omiten ──
$d = opg_rec_datos_recompensa(array('xp' => 50.0, 'nikas' => 5, 'berries' => 1000000, 'kuros' => 0, 'reputacion' => 10), 'Aventura T1', 812);
rc_igual($d, array('xp' => 50, 'nikas' => 5, 'berries' => 1000000, 'reputacion' => 10, 'concepto' => 'Aventura T1', 'tid' => 812), 'datos con varios conceptos');
rc_igual(opg_rec_datos_recompensa(array('xp' => 0, 'berries' => -5, 'nikas' => 'abc')), null, 'nada que avisar devuelve null');
rc_igual(opg_rec_datos_recompensa(array('xp' => 55.0 * 1.1)), array('xp' => 60.5), 'xp con decimales');
rc_igual(opg_rec_datos_recompensa(array(), 'Narración T2', 0, 'cofre x2 Gigante'), array('objetos' => 'cofre x2 Gigante', 'concepto' => 'Narración T2'), 'solo objetos');
rc_igual(array_key_exists('tid', opg_rec_datos_recompensa(array('berries' => 1))), false, 'sin tid no se guarda tid');

// ── Texto del aviso ──
rc_igual(opg_notif_texto('recompensa_entregada', $d),
    'Recibiste 50 XP, 5 Nikas, 1.000.000 Berries y 10 de reputación por Aventura T1 (tema #812). Revisa tu ficha.', 'texto de recompensa');
rc_igual(opg_notif_texto('recompensa_entregada', array('berries' => 300)), 'Recibiste 300 Berries. Revisa tu ficha.', 'un solo importe');
rc_igual(opg_notif_texto('recompensa_entregada', array('xp' => 0, 'kuros' => 25)), 'Recibiste 25 Kuros. Revisa tu ficha.', 'cero omitido en el texto');
rc_igual(opg_notif_texto('recompensa_entregada', array()), 'Recibiste una recompensa. Revisa tu ficha.', 'sin datos');
rc_igual(opg_notif_texto('recompensa_entregada', array('nikas' => 3, 'objetos' => 'cofre <b>x</b>', 'concepto' => 'A&B')),
    'Recibiste 3 Nikas y cofre &lt;b&gt;x&lt;/b&gt; por A&amp;B. Revisa tu ficha.', 'el texto se escapa');
rc_igual(opg_notif_url('recompensa_entregada', $d), 'op/personaje.php', 'enlace a la ficha');

// ── Salarios ──
$s = opg_rec_datos_salario(1500000, 'Berries', 'rango de facción Capitán');
rc_igual($s, array('monto' => 1500000, 'moneda' => 'Berries', 'concepto' => 'rango de facción Capitán'), 'datos de salario');
rc_igual(opg_notif_texto('salario_recibido', $s), 'Recibiste 1.500.000 Berries de salario (rango de facción Capitán).', 'texto de salario');
rc_igual(opg_notif_texto('salario_recibido', opg_rec_datos_salario(400, 'Kuros')), 'Recibiste 400 Kuros de salario.', 'salario sin concepto');
rc_igual(opg_rec_datos_salario(0, 'Kuros'), null, 'salario cero no avisa');
rc_igual(opg_rec_datos_salario(10, 'Nikas'), null, 'moneda fuera de la lista');
rc_igual(opg_notif_texto('salario_recibido', array()), 'Recibiste tu salario.', 'salario sin datos');

// ── Agrupacion de pagos de un lote ──
$g = opg_rec_agrupar_pagos(array(
    array('uid' => 3, 'monto' => 500, 'concepto' => 'Administradores'),
    array('uid' => 7, 'monto' => 400, 'concepto' => 'Staff / Mods'),
    array('uid' => 9, 'monto' => 500, 'concepto' => 'Administradores'),
    array('uid' => 0, 'monto' => 500, 'concepto' => 'Administradores'),
    array('uid' => 11, 'monto' => 0, 'concepto' => 'Narradores'),
    array('uid' => 3, 'monto' => 500, 'concepto' => 'Administradores'),
));
rc_igual($g, array(
    array('monto' => 500, 'concepto' => 'Administradores', 'uids' => array(3, 9)),
    array('monto' => 400, 'concepto' => 'Staff / Mods', 'uids' => array(7)),
), 'agrupa por importe y concepto, sin repetidos ni invalidos');

// ── Claves: identifican el hecho y son validas para opg_notificar (letras, numeros y "_ . : -") ──
$claves = array(
    opg_rec_clave_entrega('autonarrada', 4521, 12, 812, 'T1', 1700000000),
    opg_rec_clave_entrega('aventura', 0, 12, 812, 'T3Infra', 1700000000),
    opg_rec_clave_berries_masivo(1, 1700000000),
    opg_rec_clave_salario_faccion('2026-11-23'),
    opg_rec_clave_salario_staff(77),
);
rc_igual($claves, array(
    'recompensa:autonarrada:4521',
    'recompensa:aventura:12:812:T3Infra:1700000000',
    'berries_masivo:1:1700000000',
    'salario_faccion:2026-11-23',
    'salario_staff:77',
), 'formato de las claves');
foreach ($claves as $c) {
    rc_igual(preg_match('/^[A-Za-z0-9_.:-]{1,111}$/', $c), 1, "clave valida: $c");
}
rc_igual(opg_rec_clave_entrega('autonarrada', 1, 0, 0, '', 0) !== opg_rec_clave_entrega('autonarrada', 2, 0, 0, '', 0), true, 'dos entregas distintas, dos claves');

if ($failures) {
    echo "FALLOS (" . count($failures) . "):\n - " . implode("\n - ", $failures) . "\n";
    exit(1);
}
echo "OK recompensas: datos, resumen, textos, agrupacion y claves\n";
