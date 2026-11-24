<?php
/**
 * Avisos reset_aplicado y reset_rechazado (issue #24): datos, claves, textos y enlaces. Sin base de datos.
 *
 *   php tests/notificaciones/reset.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/reset_avisos.php';

$fallos = array();
function rs_igual($obtenido, $esperado, $nombre)
{
    global $fallos;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

const STAFF = 1;

// Aplicado sin cambio de Akuma
$a = opg_reset_aviso(7, 42, 'aplicado', STAFF, 'gomu', 'gomu');
rs_igual($a, array('tipo' => 'reset_aplicado', 'destinatario' => 42, 'datos' => array('fid' => 42, 'reset_id' => 7),
    'clave' => 'reset:7:aplicado'), 'aplicado: aviso completo');
rs_igual(opg_notif_texto('reset_aplicado', $a['datos']), 'El staff aplicó tu reset de build. Revisa tu ficha.', 'aplicado: texto');
rs_igual(opg_notif_url('reset_aplicado', $a['datos']), 'op/personaje.php?uid=42', 'aplicado: enlace a la ficha');

// Aplicado con Akuma asignada, cambiada o retirada
$b = opg_reset_aviso(7, 42, 'aplicado', STAFF, '', 'gomu', 'Gomu Gomu no Mi');
rs_igual($b['datos'], array('fid' => 42, 'reset_id' => 7, 'akuma' => 'asignada', 'fruta' => 'Gomu Gomu no Mi'), 'asignada: datos');
rs_igual(opg_notif_texto('reset_aplicado', $b['datos']),
    'El staff aplicó tu reset de build. Ahora tienes la Akuma «Gomu Gomu no Mi». Revisa tu ficha.', 'asignada: texto');
rs_igual($b['clave'], 'reset:7:aplicado', 'asignada: la clave no cambia');
rs_igual(opg_reset_aviso(7, 42, 'aplicado', STAFF, 'mera', 'gomu')['datos'],
    array('fid' => 42, 'reset_id' => 7, 'akuma' => 'asignada', 'fruta' => 'gomu'), 'cambio de fruta: sin nombre usa el valor');
$c = opg_reset_aviso(7, 42, 'aplicado', STAFF, 'mera', '', 'Mera Mera no Mi');
rs_igual($c['datos'], array('fid' => 42, 'reset_id' => 7, 'akuma' => 'retirada', 'fruta' => 'Mera Mera no Mi'), 'retirada: datos');
rs_igual(opg_notif_texto('reset_aplicado', $c['datos']),
    'El staff aplicó tu reset de build. Se te retiró la Akuma «Mera Mera no Mi». Revisa tu ficha.', 'retirada: texto');
rs_igual(opg_notif_texto('reset_aplicado', array('fid' => 42, 'akuma' => 'asignada')),
    'El staff aplicó tu reset de build. Ahora tienes otra Akuma. Revisa tu ficha.', 'asignada sin fruta: texto');
rs_igual(opg_reset_aviso(7, 42, 'aplicado', STAFF, '', 'x', '<b>Hito</b> Hito')['datos']['fruta'], 'Hito Hito', 'fruta: sin etiquetas');

// Rechazado: no lleva datos de Akuma
$r = opg_reset_aviso(8, 42, 'rechazado', STAFF, '', 'gomu');
rs_igual($r, array('tipo' => 'reset_rechazado', 'destinatario' => 42, 'datos' => array('fid' => 42, 'reset_id' => 8),
    'clave' => 'reset:8:rechazado'), 'rechazado: aviso completo');
rs_igual(opg_notif_texto('reset_rechazado', $r['datos']),
    'El staff rechazó tu petición de reset de build. Se te devolvió el ticket de reset.', 'rechazado: texto');
rs_igual(opg_notif_url('reset_rechazado', $r['datos']), 'op/personaje.php?uid=42', 'rechazado: enlace a la ficha');
rs_igual(opg_notif_importancia('reset_rechazado'), 'importante', 'rechazado: importante');

// Escape: el texto sale escapado una vez
rs_igual(opg_notif_texto('reset_aplicado', array('fid' => 1, 'akuma' => 'asignada', 'fruta' => 'A"<x>')),
    'El staff aplicó tu reset de build. Ahora tienes la Akuma «A&quot;&lt;x&gt;». Revisa tu ficha.', 'texto escapado');

// No se avisa
rs_igual(opg_reset_aviso(7, 42, 'guardado', STAFF), null, 'resultado desconocido: no avisa');
rs_igual(opg_reset_aviso(0, 42, 'aplicado', STAFF), null, 'reset 0: no avisa');
rs_igual(opg_reset_aviso(7, 0, 'aplicado', STAFF), null, 'fid 0: no avisa');
rs_igual(opg_reset_aviso(7, 42, 'aplicado', 42)['destinatario'], 42, 'propio usuario: avisa igual');

// Los datos no llevan nombre del staff ni HTML
foreach (array($a, $b, $c, $r) as $i => $av) {
    rs_igual(array_diff(array_keys($av['datos']), array('fid', 'reset_id', 'akuma', 'fruta')), array(), "datos $i: solo claves permitidas");
}

if ($fallos) {
    echo "FALLOS (" . count($fallos) . "):\n" . implode("\n", $fallos) . "\n";
    exit(1);
}
echo "OK reset\n";
