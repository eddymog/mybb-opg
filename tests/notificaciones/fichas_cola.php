<?php
/**
 * Avisos ficha_aprobada y ficha_rechazada (issue #17): datos, claves, textos y enlaces. Sin base de datos.
 *
 *   php tests/notificaciones/fichas_cola.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/fichas_cola.php';

$fallos = array();
function fc_igual($obtenido, $esperado, $nombre)
{
    global $fallos;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

// Aprobada
$a = opg_fichas_cola_aviso_aprobada(42, 'Monkey D. Test');
fc_igual($a, array('tipo' => 'ficha_aprobada', 'destinatario' => 42, 'datos' => array('fid' => 42, 'nombre' => 'Monkey D. Test'),
    'clave' => 'ficha:42:aprobada'), 'aprobada: aviso completo');
fc_igual(opg_fichas_cola_aviso_aprobada('42', 'X')['clave'], 'ficha:42:aprobada', 'aprobada: fid en texto');
fc_igual(opg_fichas_cola_aviso_aprobada(0, 'X'), null, 'aprobada: fid 0 no avisa');
fc_igual(opg_notif_texto('ficha_aprobada', $a['datos']), 'Se aprobó la ficha de «Monkey D. Test». Ya puedes empezar a rolear.', 'aprobada: texto');
fc_igual(opg_notif_url('ficha_aprobada', $a['datos']), 'op/personaje.php?uid=42', 'aprobada: enlace a la ficha');
fc_igual(opg_notif_texto('ficha_aprobada', array()), 'Se aprobó la ficha de tu personaje. Ya puedes empezar a rolear.', 'aprobada: texto sin datos');
fc_igual(opg_notif_url('ficha_aprobada', array()), '', 'aprobada: sin fid no hay enlace');

// Rechazada
$r = opg_fichas_cola_aviso_rechazada(42, 'Monkey D. Test', '1700000000');
fc_igual($r, array('tipo' => 'ficha_rechazada', 'destinatario' => 42, 'datos' => array('fid' => 42, 'nombre' => 'Monkey D. Test'),
    'clave' => 'ficha:42:rechazada:1700000000'), 'rechazada: aviso completo');
fc_igual(opg_fichas_cola_aviso_rechazada(42, 'X', 1700000999)['clave'] !== $r['clave'], true, 'rechazada: otra ficha, otra clave');
fc_igual(opg_fichas_cola_aviso_rechazada(42, 'X', 1700000000)['clave'], $r['clave'], 'rechazada: reintento, misma clave');
fc_igual(opg_fichas_cola_aviso_rechazada(42, 'X', null), null, 'rechazada: sin fecha no avisa');
fc_igual(opg_fichas_cola_aviso_rechazada(42, 'X', 'abc'), null, 'rechazada: fecha no numerica no avisa');
fc_igual(opg_fichas_cola_aviso_rechazada(-1, 'X', 1700000000), null, 'rechazada: fid no valido');
fc_igual(opg_notif_texto('ficha_rechazada', $r['datos']), 'Se rechazó la ficha de «Monkey D. Test» y se borró. Puedes crear una nueva.', 'rechazada: texto');
fc_igual(opg_notif_url('ficha_rechazada', $r['datos']), 'op/ficha_crear.php', 'rechazada: enlace a crear ficha');

// Catalogo: categoria, origen e importancia
$cat = opg_notif_catalogo();
fc_igual(array($cat['ficha_aprobada']['categoria'], $cat['ficha_aprobada']['origen'], $cat['ficha_aprobada']['importancia']),
    array('ficha', 'gestion', 'normal'), 'aprobada: definicion del catalogo');
fc_igual(array($cat['ficha_rechazada']['categoria'], $cat['ficha_rechazada']['origen'], $cat['ficha_rechazada']['importancia']),
    array('ficha', 'gestion', 'importante'), 'rechazada: definicion del catalogo');

// Datos sin HTML y texto escapado
$h = opg_fichas_cola_aviso_aprobada(7, '<b>Zoro</b> "&"');
fc_igual($h['datos']['nombre'], 'Zoro "&"', 'datos: el nombre no guarda etiquetas');
fc_igual(opg_notif_texto('ficha_aprobada', array('nombre' => '<script>x</script>')),
    'Se aprobó la ficha de «&lt;script&gt;x&lt;/script&gt;». Ya puedes empezar a rolear.', 'texto: se escapa al mostrar');
fc_igual(opg_fichas_cola_aviso_aprobada(7, '   ')['datos'], array('fid' => 7), 'datos: nombre vacio no se guarda');
fc_igual(mb_strlen(opg_fichas_cola_aviso_aprobada(7, str_repeat('ñ', 300))['datos']['nombre'], 'UTF-8'), 100, 'datos: nombre recortado');

// Avisar sin aviso no hace nada ni falla
fc_igual(opg_fichas_cola_avisar(null, 1), false, 'avisar: sin aviso devuelve false');

if ($fallos) {
    echo "FALLOS fichas_cola:\n" . implode("\n", $fallos) . "\n";
    exit(1);
}
echo "OK fichas_cola\n";
