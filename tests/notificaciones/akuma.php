<?php
/**
 * Avisos akuma_asignada y akuma_retirada (issue #18): tipo, datos, claves, textos y enlaces. Sin base de datos.
 *
 *   php tests/notificaciones/akuma.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/akuma_avisos.php';

$fallos = array();
function ak_igual($obtenido, $esperado, $nombre)
{
    global $fallos;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

const HORA = 1700000000;
const STAFF = 1;

// Tipo segun el cambio
ak_igual(opg_akuma_tipo_cambio('', 'gomu'), 'akuma_asignada', 'tipo: de vacio a fruta');
ak_igual(opg_akuma_tipo_cambio('mera', 'gomu'), 'akuma_asignada', 'tipo: de otra fruta a fruta');
ak_igual(opg_akuma_tipo_cambio('gomu', ''), 'akuma_retirada', 'tipo: de fruta a vacio');
ak_igual(opg_akuma_tipo_cambio('gomu', 'gomu'), null, 'tipo: sin cambio');
ak_igual(opg_akuma_tipo_cambio('', ''), null, 'tipo: vacio a vacio');
ak_igual(opg_akuma_tipo_cambio(' gomu ', 'gomu'), null, 'tipo: solo espacios no es un cambio');
ak_igual(opg_akuma_tipo_cambio(null, '  '), null, 'tipo: nulo a espacios no es un cambio');

// Asignada
$a = opg_akuma_aviso(42, '', 'gomu', 'Gomu Gomu no Mi', STAFF, HORA);
ak_igual($a, array('tipo' => 'akuma_asignada', 'destinatario' => 42, 'datos' => array('fid' => 42, 'fruta' => 'Gomu Gomu no Mi'),
    'clave' => 'akuma:42:gomu:asignada:1700000000'), 'asignada: aviso completo');
ak_igual(opg_notif_texto('akuma_asignada', $a['datos']), 'Se te asignó la Akuma «Gomu Gomu no Mi». Revisa tu ficha.', 'asignada: texto');
ak_igual(opg_notif_url('akuma_asignada', $a['datos']), 'op/personaje.php?uid=42', 'asignada: enlace a la ficha');
ak_igual(opg_akuma_aviso(42, 'mera', 'gomu', '', STAFF, HORA)['tipo'], 'akuma_asignada', 'asignada: cambio de fruta');
ak_igual(opg_akuma_aviso(42, 'mera', 'gomu', '', STAFF, HORA)['datos'], array('fid' => 42, 'fruta' => 'gomu'), 'asignada: sin nombre usa el valor');

// Retirada: el nombre es el de la fruta que tenia
$r = opg_akuma_aviso(42, 'gomu', '', 'Gomu Gomu no Mi', STAFF, HORA);
ak_igual($r, array('tipo' => 'akuma_retirada', 'destinatario' => 42, 'datos' => array('fid' => 42, 'fruta' => 'Gomu Gomu no Mi'),
    'clave' => 'akuma:42:gomu:retirada:1700000000'), 'retirada: aviso completo');
ak_igual(opg_notif_texto('akuma_retirada', $r['datos']), 'Se te retiró la Akuma «Gomu Gomu no Mi».', 'retirada: texto');
ak_igual(opg_notif_url('akuma_retirada', $r['datos']), 'op/personaje.php?uid=42', 'retirada: enlace a la ficha');

// No se avisa: sin cambio, fid invalido, o el propio usuario (tirada)
ak_igual(opg_akuma_aviso(42, 'gomu', 'gomu', 'X', STAFF, HORA), null, 'sin cambio: no avisa');
ak_igual(opg_akuma_aviso(0, '', 'gomu', 'X', STAFF, HORA), null, 'fid 0: no avisa');
ak_igual(opg_akuma_aviso(42, '', 'gomu', 'X', 42, HORA), null, 'propio usuario: no avisa');
ak_igual(opg_akuma_avisar_cambio(42, '', 'gomu', 42, HORA), false, 'propio usuario: el envio no hace nada');
ak_igual(opg_akuma_avisar_cambio(42, 'gomu', 'gomu', STAFF, HORA), false, 'sin cambio: el envio no hace nada');

// Clave: usuario, fruta, evento y hora; solo caracteres validos y dentro del largo
ak_igual(opg_akuma_aviso(42, '', 'gomu', '', STAFF, HORA + 5)['clave'], 'akuma:42:gomu:asignada:1700000005', 'clave: lleva la hora de la accion');
ak_igual(opg_akuma_aviso(7, '', 'Gomu', '', STAFF, HORA)['clave'], 'akuma:7:gomu:asignada:1700000000', 'clave: id en minusculas');
$k = opg_akuma_aviso(42, '', 'Gomu Gomu no Mí', '', STAFF, HORA)['clave'];
ak_igual((bool) preg_match('/^akuma:42:h[0-9a-f]{12}:asignada:1700000000$/', $k), true, 'clave: nombre con espacios y tildes se resume (' . $k . ')');
$k2 = opg_akuma_aviso(42, '', 'Mera Mera no Mi', '', STAFF, HORA)['clave'];
ak_igual($k !== $k2, true, 'clave: frutas distintas, claves distintas');
$larga = opg_akuma_aviso(2147483647, str_repeat('x', 300), '', '', STAFF, 9999999999)['clave'];
ak_igual(strlen($larga) <= 128 && (bool) preg_match('/^[A-Za-z0-9_.:-]+$/', $larga), true, 'clave: valida y corta (' . $larga . ')');

// Datos sin HTML (requisitos 26, 27 y 50) y texto escapado una vez
$x = opg_akuma_aviso(42, '', 'x', '<b>Hito</b> "no" Mi & <script>1</script>', STAFF, HORA);
ak_igual($x['datos']['fruta'], 'Hito "no" Mi & 1', 'datos: sin etiquetas');
$t = opg_notif_texto('akuma_asignada', array('fruta' => '<img src=x onerror=1> & "a"'));
ak_igual(strpos($t, '<') === false && strpos($t, '"') === false && strpos($t, '&amp;') !== false, true, 'texto: escapado (' . $t . ')');
ak_igual(mb_strlen(opg_akuma_aviso(42, '', 'x', str_repeat('á', 300), STAFF, HORA)['datos']['fruta'], 'UTF-8'), OPG_AKUMA_MAX_NOMBRE, 'datos: nombre recortado');

// Sin datos: el texto no falla
ak_igual(opg_notif_texto('akuma_asignada', array()), 'Se te asignó una Akuma. Revisa tu ficha.', 'asignada: texto sin datos');
ak_igual(opg_notif_texto('akuma_retirada', array()), 'Se te retiró tu Akuma.', 'retirada: texto sin datos');

// Catalogo: categoria, origen, importancia
$cat = opg_notif_catalogo();
ak_igual(array($cat['akuma_asignada']['categoria'], $cat['akuma_asignada']['origen'], $cat['akuma_asignada']['importancia']),
    array('tecnicas', 'gestion', 'normal'), 'catalogo: akuma_asignada');
ak_igual(array($cat['akuma_retirada']['categoria'], $cat['akuma_retirada']['origen'], $cat['akuma_retirada']['importancia']),
    array('tecnicas', 'gestion', 'importante'), 'catalogo: akuma_retirada');

if ($fallos) {
    echo "FALLOS (" . count($fallos) . "):\n" . implode("\n", $fallos) . "\n";
    exit(1);
}
echo "OK akuma\n";
