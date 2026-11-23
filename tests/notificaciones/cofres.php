<?php
/**
 * Avisos del reparto de cofres (issue #23): datos, clave, texto, enlace y definicion del catalogo. Logica pura, sin base.
 *
 *   php tests/notificaciones/cofres.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/cofres_avisos.php';

$fallos = array();
$total = 0;
function nco($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

// Clave: identifica el lote y el hecho
nco('clave del reparto', opg_cof_clave_aviso('cofres_repartidos', 12), 'cofres:12:repartidos');
nco('clave de la anulacion', opg_cof_clave_aviso('cofres_revertidos', '12x'), 'cofres:12:revertidos');
nco('las dos claves del mismo lote son distintas', opg_cof_clave_aviso('cofres_repartidos', 3) !== opg_cof_clave_aviso('cofres_revertidos', 3), true);

// Datos: lote, cantidad y nombre visible; nada del staff
nco('datos del reparto', opg_cof_datos_aviso('12', '3', "  Cofre   de\n Oro "), array('reparto_id' => 12, 'cantidad' => 3, 'cofre' => 'Cofre de Oro'));
nco('sin nombre no se guarda cofre', opg_cof_datos_aviso(5, 1, ''), array('reparto_id' => 5, 'cantidad' => 1));
nco('nombre no escalar se ignora', opg_cof_datos_aviso(5, 1, array('x')), array('reparto_id' => 5, 'cantidad' => 1));
nco('el nombre no guarda HTML', opg_cof_datos_aviso(5, 1, '<b>Cofre</b> raro')['cofre'], 'Cofre raro');
nco('valores negativos quedan en cero', opg_cof_datos_aviso(-1, -4, 'C'), array('reparto_id' => 0, 'cantidad' => 0, 'cofre' => 'C'));
$largo = opg_cof_datos_aviso(1, 1, str_repeat('é', 120))['cofre'];
nco('el nombre se recorta a 80 caracteres', mb_strlen($largo, 'UTF-8'), 80);
nco('el nombre recortado termina en puntos suspensivos', mb_substr($largo, -1, 1, 'UTF-8'), '…');

// Texto (escapado) y enlace
nco('texto de un cofre', opg_notif_texto('cofres_repartidos', opg_cof_datos_aviso(7, 1, 'Cofre de Madera'), 1),
    'Recibiste 1 cofre «Cofre de Madera» en un reparto del staff. Está en tu inventario.');
nco('texto de varios cofres', opg_notif_texto('cofres_repartidos', opg_cof_datos_aviso(7, 3, 'Cofre de Oro'), 1),
    'Recibiste 3 cofres «Cofre de Oro» en un reparto del staff. Están en tu inventario.');
nco('texto sin nombre ni cantidad', opg_notif_texto('cofres_repartidos', array(), 1),
    'Recibiste 1 cofre en un reparto del staff. Está en tu inventario.');
nco('texto de la anulacion', opg_notif_texto('cofres_revertidos', opg_cof_datos_aviso(7, 2, 'Cofre de Oro'), 1),
    'El staff anuló el reparto de 2 cofres «Cofre de Oro». Se retiraron de tu inventario los cofres de ese reparto que no habías abierto.');
nco('el nombre se escapa al mostrarse', opg_notif_texto('cofres_repartidos', array('cantidad' => 1, 'cofre' => 'a & "b"'), 1),
    'Recibiste 1 cofre «a &amp; &quot;b&quot;» en un reparto del staff. Está en tu inventario.');
nco('enlace del reparto', opg_notif_url('cofres_repartidos', array('reparto_id' => 7)), 'op/inventario.php');
nco('enlace de la anulacion', opg_notif_url('cofres_revertidos', array()), 'op/inventario.php');

// Definicion en el catalogo
$def = opg_notif_tipo('cofres_repartidos');
nco('cofres_repartidos: categoria, origen, importancia, agrupable', array($def['categoria'], $def['origen'], $def['importancia'], $def['agrupable']),
    array('recompensas', 'gestion', 'normal', false));
$def = opg_notif_tipo('cofres_revertidos');
nco('cofres_revertidos: categoria, origen, importancia, agrupable', array($def['categoria'], $def['origen'], $def['importancia'], $def['agrupable']),
    array('recompensas', 'gestion', 'importante', false));

// opg_cof_avisar no lanza con tipos o destinatarios no validos (no llega a la base)
nco('tipo no valido', opg_cof_avisar('otro', array(5), 1, 'C', 1, 10), false);
nco('sin destinatarios', opg_cof_avisar('cofres_repartidos', array(), 1, 'C', 1, 10), false);

if ($fallos) {
    echo 'FALLA: ' . implode("\nFALLA: ", $fallos) . "\n";
    exit(1);
}
echo "OK cofres: $total pruebas\n";
