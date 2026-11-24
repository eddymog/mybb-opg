<?php
/**
 * Aviso de intercambio recibido (issue #25): datos, clave, texto, enlace y definicion del catalogo. Logica pura, sin base.
 *
 *   php tests/notificaciones/intercambio.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/intercambio_avisos.php';

$fallos = array();
$total = 0;
function nin($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

// Clave: identifica el registro del intercambio
nin('clave del intercambio', opg_int_clave_aviso(42), 'intercambio:42');
nin('clave con id en texto', opg_int_clave_aviso('42x'), 'intercambio:42');

// Datos: solo Berries, solo objetos, ambos, nada
nin('solo Berries', opg_int_datos_aviso(9, 'Luffy', '1500', array(), '77'),
    array('intercambio_id' => 9, 'actor' => 'Luffy', 'berries' => 1500, 'tid' => 77));
nin('solo objetos', opg_int_datos_aviso(9, 'Luffy', 0, array('Espada', 'Escudo'), 77),
    array('intercambio_id' => 9, 'actor' => 'Luffy', 'objetos' => 'Espada, Escudo', 'n_objetos' => 2, 'tid' => 77));
nin('Berries y objetos repetidos agrupados', opg_int_datos_aviso(9, 'Luffy', 10, array('Espada', 'Ron', 'Espada'), 0),
    array('intercambio_id' => 9, 'actor' => 'Luffy', 'berries' => 10, 'objetos' => 'Espada x2, Ron', 'n_objetos' => 3));
nin('sin Berries ni objetos no hay aviso', opg_int_datos_aviso(9, 'Luffy', 0, array('', '  '), 77), null);
nin('Berries negativos o no numericos cuentan como cero', opg_int_datos_aviso(9, 'Luffy', '-5', null, 77), null);
nin('sin nombre de emisor no se guarda actor', opg_int_datos_aviso(9, '', 5, array(), 0), array('intercambio_id' => 9, 'berries' => 5));
nin('los nombres no guardan HTML', opg_int_datos_aviso(9, '<b>Zoro</b>', 0, array('<i>Wado</i> Ichimonji'), 0),
    array('intercambio_id' => 9, 'actor' => 'Zoro', 'objetos' => 'Wado Ichimonji', 'n_objetos' => 1));
$largo = opg_int_datos_aviso(1, 'X', 0, array_map(function ($i) { return 'Objeto ' . $i . ' ' . str_repeat('é', 10); }, range(1, 30)), 0);
nin('la lista de objetos se recorta a 200 caracteres como mucho', mb_strlen($largo['objetos'], 'UTF-8') <= 200 && mb_strlen($largo['objetos'], 'UTF-8') > 190, true);
nin('el recorte termina en puntos suspensivos', mb_substr($largo['objetos'], -1, 1, 'UTF-8'), '…');

// Texto (escapado) y enlace
nin('texto solo Berries', opg_notif_texto('intercambio_recibido', opg_int_datos_aviso(9, 'Luffy', 1500000, array(), 77)),
    'Luffy te envió 1.500.000 Berries en un intercambio (tema #77).');
nin('texto un objeto', opg_notif_texto('intercambio_recibido', opg_int_datos_aviso(9, 'Luffy', 0, array('Espada'), 77)),
    'Luffy te envió el objeto Espada en un intercambio (tema #77).');
nin('texto Berries y varios objetos', opg_notif_texto('intercambio_recibido', opg_int_datos_aviso(9, 'Nami', 300, array('Mapa', 'Mapa'), 0)),
    'Nami te envió 300 Berries y 2 objetos (Mapa x2) en un intercambio.');
nin('texto sin datos', opg_notif_texto('intercambio_recibido', array()), 'Otro personaje te hizo un intercambio.');
nin('el texto se escapa', opg_notif_texto('intercambio_recibido', array('actor' => 'a & "b"', 'berries' => 1)),
    'a &amp; &quot;b&quot; te envió 1 Berries en un intercambio.');
nin('enlace al tema', opg_notif_url('intercambio_recibido', array('tid' => 77)), 'showthread.php?tid=77');
nin('enlace sin tema', opg_notif_url('intercambio_recibido', array()), 'op/inventario.php');

// Definicion en el catalogo
$def = opg_notif_tipo('intercambio_recibido');
nin('categoria, origen, importancia, agrupable, icono', array($def['categoria'], $def['origen'], $def['importancia'], $def['agrupable'], $def['icono']),
    array('recompensas', 'usuario', 'normal', false, 'fa-right-left'));

// opg_int_avisar no llega a la base si no hay nada que avisar
nin('sin receptor', opg_int_avisar(0, 9, 3, 'Luffy', 5, array(), 1), false);
nin('sin id de intercambio', opg_int_avisar(5, 0, 3, 'Luffy', 5, array(), 1), false);
nin('sin Berries ni objetos', opg_int_avisar(5, 9, 3, 'Luffy', 0, array(), 1), false);

if ($fallos) {
    echo 'FALLA: ' . implode("\nFALLA: ", $fallos) . "\n";
    exit(1);
}
echo "OK intercambio: $total pruebas\n";
