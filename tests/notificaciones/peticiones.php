<?php
/**
 * Avisos de peticiones y bugs resueltos (issue #19): datos, clave, tipo, texto y enlace. Logica pura, sin base de datos.
 *
 *   php tests/notificaciones/peticiones.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/peticiones_avisos.php';

$fallos = array();
$total = 0;
function np($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

// Tipo segun la categoria de la fila
np('programacion es un bug', opg_pet_tipo_aviso('programacion'), 'bug_resuelto');
foreach (array('ficha', 'tema', 'combate', 'tecnica', 'otros', '') as $c) {
    np("$c es una peticion", opg_pet_tipo_aviso($c), 'peticion_resuelta');
}

// Clave: identifica el objeto y su resolucion
np('clave de peticion', opg_pet_clave_aviso('peticion_resuelta', 42), 'peticion:42:resuelta');
np('clave de bug', opg_pet_clave_aviso('bug_resuelto', '7x'), 'bug:7:resuelto');

// Datos: solo lo que el autor ve de su peticion, sin notas ni quien la atendio
$fila = array('id' => '42', 'uid' => '5', 'categoria' => 'ficha', 'resumen' => "  Subir   nivel\n de Haki ", 'notasMod' => 'interno',
    'atendidoPor' => 'Mod', 'mod_nombre' => 'Mod', 'descripcion' => 'larga');
np('datos de una peticion', opg_pet_datos_aviso($fila),
    array('peticion_id' => 42, 'categoria' => 'Ajustes de Ficha y Recursos', 'resumen' => 'Subir nivel de Haki', 'resultado' => 'resuelta'));
np('resultado fuera de la lista se toma como resuelta', opg_pet_datos_aviso($fila, 'borrada')['resultado'], 'resuelta');
np('resultado aceptada se conserva', opg_pet_datos_aviso($fila, 'aceptada')['resultado'], 'aceptada');
np('categoria desconocida y resumen vacio no se guardan', opg_pet_datos_aviso(array('id' => 3, 'categoria' => 'x', 'resumen' => ' ')),
    array('peticion_id' => 3, 'resultado' => 'resuelta'));
$largo = opg_pet_datos_aviso(array('id' => 1, 'resumen' => str_repeat('á', 120)))['resumen'];
np('el resumen se recorta a 80 caracteres', mb_strlen($largo, 'UTF-8'), 80);
np('el resumen recortado termina en puntos suspensivos', mb_substr($largo, -1, 1, 'UTF-8'), '…');
np('datos de una fila no valida', opg_pet_datos_aviso(null), array('peticion_id' => 0, 'resultado' => 'resuelta'));

// Texto y enlace del catalogo
np('texto de peticion_resuelta', opg_notif_texto('peticion_resuelta', opg_pet_datos_aviso($fila), 1),
    'El staff marcó como resuelta tu petición «Subir nivel de Haki» (Ajustes de Ficha y Recursos).');
np('texto de peticion aceptada', opg_notif_texto('peticion_resuelta', array('peticion_id' => 9, 'resultado' => 'aceptada'), 1),
    'El staff aceptó tu petición #9.');
np('texto de peticion sin datos', opg_notif_texto('peticion_resuelta', array(), 1), 'El staff marcó como resuelta tu petición.');
np('texto de bug_resuelto', opg_notif_texto('bug_resuelto', opg_pet_datos_aviso(array('id' => 8, 'categoria' => 'programacion', 'resumen' => 'La ficha no carga')), 1),
    'El staff marcó como resuelto tu reporte de error «La ficha no carga».');
np('texto de bug sin resumen', opg_notif_texto('bug_resuelto', array('peticion_id' => 8), 1), 'El staff marcó como resuelto tu reporte de error #8.');
np('el resumen se escapa al mostrarse', opg_notif_texto('bug_resuelto', array('resumen' => 'a & "b" \'c\''), 1),
    'El staff marcó como resuelto tu reporte de error «a &amp; &quot;b&quot; &#039;c&#039;».');
np('enlace de peticion', opg_notif_url('peticion_resuelta', array('peticion_id' => 9)), 'op/peticiones_usuario.php');
np('enlace de bug', opg_notif_url('bug_resuelto', array()), 'op/peticiones_usuario.php');
$def = opg_notif_tipo('peticion_resuelta');
np('peticion_resuelta: categoria, origen, importancia', array($def['categoria'], $def['origen'], $def['importancia'], $def['agrupable']),
    array('peticiones', 'gestion', 'normal', false));
$def = opg_notif_tipo('bug_resuelto');
np('bug_resuelto: categoria, origen, importancia', array($def['categoria'], $def['origen'], $def['importancia'], $def['agrupable']),
    array('peticiones', 'gestion', 'normal', false));

if ($fallos) {
    echo 'FALLA: ' . implode("\nFALLA: ", $fallos) . "\n";
    exit(1);
}
echo "OK peticiones: $total pruebas\n";
