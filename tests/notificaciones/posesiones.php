<?php
/**
 * Avisos de tecnicas, virtudes y objetos cambiados por el staff (issue #21): accion, datos, resumen de lote, clave,
 * texto y enlace. Logica pura, sin base de datos.
 *
 *   php tests/notificaciones/posesiones.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/posesiones_avisos.php';

$fallos = array();
$total = 0;
function npo($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

// Tipos y acciones
npo('tipo de tecnica', opg_pos_tipo('tecnica'), 'tecnica_cambio');
npo('tipo de virtud', opg_pos_tipo('virtud'), 'virtud_cambio');
npo('tipo de objeto', opg_pos_tipo('objeto'), 'objeto_cambio');
npo('tipo desconocido', opg_pos_tipo('akuma'), null);
npo('Añadir es otorgada', opg_pos_accion('Añadir'), 'otorgada');
npo('Remover es retirada', opg_pos_accion('Remover'), 'retirada');
npo('otra accion no vale', opg_pos_accion('Borrar'), '');

// Datos de un solo elemento
npo('un elemento otorgado', opg_pos_datos_aviso('otorgada', array('Puño de Hierro')),
    array('accion' => 'otorgada', 'cantidad' => 1, 'nombre' => 'Puño de Hierro'));
npo('lista por aprender', opg_pos_datos_aviso('retirada', array('Corte'), 'por_aprender'),
    array('accion' => 'retirada', 'cantidad' => 1, 'lista' => 'por_aprender', 'nombre' => 'Corte'));
npo('otra lista se ignora', opg_pos_datos_aviso('retirada', array('Corte'), 'x'), array('accion' => 'retirada', 'cantidad' => 1, 'nombre' => 'Corte'));
npo('accion no valida', opg_pos_datos_aviso('borrada', array('A')), null);
npo('sin cambios no hay datos', opg_pos_datos_aviso('otorgada', array()), null);
npo('nombres vacios no cuentan', opg_pos_datos_aviso('otorgada', array(' ', null, array())), null);

// Resumen de varios elementos (accion masiva o lote del formulario)
npo('tres elementos', opg_pos_datos_aviso('modificada', array('A', 'B', 'C')),
    array('accion' => 'modificada', 'cantidad' => 3, 'nombres' => 'A, B, C'));
npo('cinco elementos se resumen', opg_pos_datos_aviso('otorgada', array('A', 'B', 'C', 'D', 'E')),
    array('accion' => 'otorgada', 'cantidad' => 5, 'nombres' => 'A, B, C y 2 más'));
npo('unidades repetidas cuentan, los nombres no se repiten', opg_pos_datos_aviso('otorgada', array('Katana', 'Katana')),
    array('accion' => 'otorgada', 'cantidad' => 2, 'nombres' => 'Katana'));

// Nombres limpios: sin HTML, una linea, recortados
npo('sin < ni > y en una linea', opg_pos_datos_aviso('otorgada', array("<b>Golpe</b>\n  fuerte"))['nombre'], 'bGolpe/b fuerte');
$largo = opg_pos_datos_aviso('otorgada', array(str_repeat('é', 100)))['nombre'];
npo('nombre recortado a 60', mb_strlen($largo, 'UTF-8'), 60);

// Clave: estable, ordenada y valida
$k1 = opg_pos_clave_aviso('tecnica_cambio', 'otorgada', array('i5', 'i3'), 1700000000);
$k2 = opg_pos_clave_aviso('tecnica_cambio', 'otorgada', array('i3', 'i5'), 1700000000);
npo('la clave no depende del orden', $k1, $k2);
npo('formato de la clave', preg_match('/^tecnica_cambio:otorgada:1700000000:[0-9a-f]{16}$/', $k1), 1);
npo('otro hecho, otra clave', $k1 === opg_pos_clave_aviso('tecnica_cambio', 'otorgada', array('i6'), 1700000000), false);
npo('otro momento, otra clave', $k1 === opg_pos_clave_aviso('tecnica_cambio', 'otorgada', array('i3', 'i5'), 1700000001), false);
npo('la clave pasa la validacion del envio', opg_notif_clave_evento('tecnica_cambio', opg_notif_tipo('tecnica_cambio'), array('clave' => $k1)), $k1);

// Grupos de la accion masiva: un aviso por destinatario con su propio resumen
$grupos = opg_pos_grupos_masivo(array(5 => array('A'), 6 => array('A'), 7 => array('A', 'B'), 8 => array('B'), 0 => array('A'), 9 => array()), 'modificada');
npo('grupos por resumen', $grupos, array(
    array('uids' => array(5, 6), 'datos' => array('accion' => 'modificada', 'cantidad' => 1, 'nombre' => 'A')),
    array('uids' => array(7), 'datos' => array('accion' => 'modificada', 'cantidad' => 2, 'nombres' => 'A, B')),
    array('uids' => array(8), 'datos' => array('accion' => 'modificada', 'cantidad' => 1, 'nombre' => 'B')),
));

// Textos del catalogo (escapados)
$t = function ($tipo, $d) { return opg_notif_texto($tipo, $d, 1); };
npo('tecnica otorgada', $t('tecnica_cambio', opg_pos_datos_aviso('otorgada', array('Puño'))), 'El staff te otorgó la técnica «Puño».');
npo('tecnica retirada', $t('tecnica_cambio', opg_pos_datos_aviso('retirada', array('Puño'))), 'El staff retiró la técnica «Puño» de tu ficha.');
npo('tecnicas otorgadas', $t('tecnica_cambio', opg_pos_datos_aviso('otorgada', array('A', 'B'))), 'El staff te otorgó 2 técnicas (A, B).');
npo('tecnica por aprender', $t('tecnica_cambio', opg_pos_datos_aviso('otorgada', array('Puño'), 'por_aprender')),
    'El staff añadió la técnica «Puño» a tus técnicas por aprender.');
npo('tecnica quitada de por aprender', $t('tecnica_cambio', opg_pos_datos_aviso('retirada', array('A', 'B'), 'por_aprender')),
    'El staff quitó 2 técnicas (A, B) de tus técnicas por aprender.');
npo('tecnica modificada', $t('tecnica_cambio', opg_pos_datos_aviso('modificada', array('Puño'))), 'El staff modificó la técnica «Puño», que conoces.');
npo('tecnicas modificadas', $t('tecnica_cambio', opg_pos_datos_aviso('modificada', array('A', 'B', 'C', 'D'))),
    'El staff modificó 4 técnicas (A, B, C y 1 más) que conoces.');
npo('virtud otorgada', $t('virtud_cambio', opg_pos_datos_aviso('otorgada', array('Valiente'))), 'El staff añadió la virtud o defecto «Valiente» a tu ficha.');
npo('virtudes retiradas', $t('virtud_cambio', opg_pos_datos_aviso('retirada', array('A', 'B'))), 'El staff retiró 2 virtudes o defectos (A, B) de tu ficha.');
npo('objeto otorgado', $t('objeto_cambio', opg_pos_datos_aviso('otorgada', array('Katana'))), 'El staff añadió el objeto «Katana» a tu inventario.');
npo('objeto retirado', $t('objeto_cambio', opg_pos_datos_aviso('retirada', array('Katana', 'Katana'))), 'El staff retiró 2 objetos (Katana) de tu inventario.');
npo('texto sin datos', $t('objeto_cambio', array()), 'El staff añadió el objeto a tu inventario.');
npo('el nombre se escapa', $t('objeto_cambio', array('accion' => 'otorgada', 'nombre' => 'a & "b"')),
    'El staff añadió el objeto «a &amp; &quot;b&quot;» a tu inventario.');

// Enlaces y definicion
npo('enlace de tecnica otorgada', opg_notif_url('tecnica_cambio', array('accion' => 'otorgada')), 'op/personaje.php');
npo('enlace de tecnica modificada', opg_notif_url('tecnica_cambio', array('accion' => 'modificada')), 'op/historial_tecnicas.php');
npo('enlace de virtud', opg_notif_url('virtud_cambio', array()), 'op/personaje.php');
npo('enlace de objeto', opg_notif_url('objeto_cambio', array()), 'op/inventario.php');
foreach (array('tecnica_cambio', 'virtud_cambio', 'objeto_cambio') as $tipo) {
    $def = opg_notif_tipo($tipo);
    npo("$tipo: categoria, origen, importancia", array($def['categoria'], $def['origen'], $def['importancia'], $def['agrupable']),
        array('tecnicas', 'gestion', 'normal', false));
}

if ($fallos) {
    echo 'FALLA: ' . implode("\nFALLA: ", $fallos) . "\n";
    exit(1);
}
echo "OK posesiones: $total pruebas\n";
