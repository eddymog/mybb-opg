<?php
/**
 * Pruebas puras (sin base de datos) de los avisos aventura_finalizada y aventura_editada (issue #26): la clave de evento,
 * los destinatarios y los datos que construye opg_av_aviso_editada, y el texto del catalogo con esos datos.
 *
 *   php tests/notificaciones/aventura_aviso.php
 */

define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
define('TABLE_PREFIX', 'mybb_');
define('TIME_NOW', 1800000000);

class FakeDb
{
    public function escape_string($s) { return addslashes($s); }
}
$db = new FakeDb();

require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';

$fallos = array();
function aa_prueba($nombre, $obtenido, $esperado)
{
    global $fallos;
    if ($obtenido !== $esperado) {
        $fallos[] = $nombre . "\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}
function aa_av($narrador, $jugadores, $editado = 0)
{
    $j = array();
    foreach ($jugadores as $u) {
        $j[] = array('uid' => $u, 'nombre' => 'U' . $u);
    }
    return array('id' => 7, 'uid' => 3, 'narrador_uid' => $narrador, 'jugadores' => $j, 'editado_at' => $editado);
}

// Clave de finalizada: identifica la aventura y el hecho, igual por las dos vias.
aa_prueba('clave de finalizada', opg_av_clave_finalizada('7'), 'aventura:7:finalizada');

// Sin narrador antes de editar: no hay aviso.
aa_prueba('sin narrador: sin aviso', opg_av_aviso_editada(aa_av(0, array(3, 9)), aa_av(0, array(3, 9, 11), 1700), array('participantes')), null);
// Sin cambios: no hay aviso.
aa_prueba('sin cambios: sin aviso', opg_av_aviso_editada(aa_av(5, array(3, 9)), aa_av(5, array(3, 9), 1700), array()), null);

// Con narrador: clave con editado_at, narrador y jugadores de antes y despues, cambios en texto plano.
$r = opg_av_aviso_editada(aa_av(5, array(3, 9)), aa_av(5, array(3, 11), 1700), array('participantes', 'isla_fid', 'dia', 'estado'));
aa_prueba('clave de editada', $r['clave'], 'aventura:7:editada:1700');
$uids = $r['uids'];
sort($uids);
aa_prueba('destinatarios: narrador, jugadores de antes y de despues, sin repetidos', $uids, array(3, 5, 9, 11));
aa_prueba('datos: cambios en orden fijo, sin el estado', $r['datos'], array('cambios' => 'los participantes, la isla y el día'));

// Un solo cambio, y solo el estado (sin nombre que mostrar).
aa_prueba('un solo cambio', opg_av_aviso_editada(aa_av(5, array(3)), aa_av(5, array(3), 9), array('tier'))['datos'], array('cambios' => 'el tier'));
aa_prueba('solo el estado: aviso sin lista de cambios', opg_av_aviso_editada(aa_av(5, array(3)), aa_av(5, array(3), 9), array('estado'))['datos'], array());

// Cambio de narrador: avisa al anterior y al nuevo.
$r = opg_av_aviso_editada(aa_av(5, array(3)), aa_av(12, array(3), 9), array('narrador_uid'));
$uids = $r['uids'];
sort($uids);
aa_prueba('cambio de narrador: el anterior y el nuevo', $uids, array(3, 5, 12));

// Datos guardados: sin HTML ni valores de los campos.
aa_prueba('los datos solo llevan texto plano', json_encode($r['datos'], JSON_UNESCAPED_UNICODE), '{"cambios":"el narrador"}');

// Texto del catalogo con los datos de los avisos.
aa_prueba('texto de aventura_finalizada', opg_notif_texto('aventura_finalizada', array('peticion_id' => 7, 'actor' => 'Staff', 'tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3), 1),
    'Staff dio por finalizada «La gran aventura». (Isla Alfa · Tier 3)');
aa_prueba('texto de aventura_editada con cambios', opg_notif_texto('aventura_editada', array('peticion_id' => 7, 'actor' => 'Eddy', 'cambios' => 'la isla y el día'), 1),
    'Eddy editó la solicitud de la aventura #7: cambió la isla y el día.');
aa_prueba('texto de aventura_editada sin cambios ni actor', opg_notif_texto('aventura_editada', array('peticion_id' => 7), 1),
    'El creador editó la solicitud de la aventura #7.');
$esc = opg_notif_texto('aventura_editada', array('peticion_id' => 7, 'actor' => '<b>x</b>', 'cambios' => '"><img src=x>'), 1);
aa_prueba('el texto escapa actor y cambios', strpos($esc, '<') === false && strpos($esc, '&lt;b&gt;') !== false, true);
aa_prueba('enlace de los dos tipos', array(opg_notif_url('aventura_finalizada', array('peticion_id' => 7)), opg_notif_url('aventura_editada', array('peticion_id' => 7))),
    array('op/tablon_de_aventuras.php?id=7', 'op/tablon_de_aventuras.php?id=7'));

if ($fallos) {
    foreach ($fallos as $m) {
        echo "FALLO: $m\n";
    }
    exit(1);
}
echo "OK aventura_aviso\n";
