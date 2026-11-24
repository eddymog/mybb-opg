<?php
/**
 * Aviso ficha_modificada (issue #22): comparacion de valores, resumen de campos, clave, texto y enlace. Sin base de datos.
 *
 *   php tests/notificaciones/ficha_modificada.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/ficha_avisos.php';

$fallos = array();
function fm_igual($obtenido, $esperado, $nombre)
{
    global $fallos;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}

const HORA = 1700000000;
const STAFF = 1;

$campos = opg_ficha_campos_atributos();

// Comparacion antes / despues
$antes = array('nombre' => 'Luffy', 'fuerza' => '10', 'nivel' => '3', 'akuma' => 'gomu', 'secret1' => '0', 'hao_chance' => '1');
$despues = array('nombre' => 'Luffy', 'fuerza' => '12', 'nivel' => '4', 'akuma' => 'mera', 'secret1' => '1', 'hao_chance' => '5');
fm_igual(opg_ficha_cambios($antes, $despues, $campos), array('nivel' => 'Nivel', 'fuerza' => 'Fuerza'),
    'cambios: solo campos visibles, en el orden de la lista');
fm_igual(opg_ficha_cambios($antes, $antes, $campos), array(), 'cambios: nada cambio');
fm_igual(opg_ficha_cambios(array('edad' => ' 20 '), array('edad' => '20'), $campos), array(), 'cambios: espacios no cuentan');
fm_igual(opg_ficha_cambios(array('edad' => null), array('edad' => ''), $campos), array(), 'cambios: nulo y vacio son iguales');
fm_igual(opg_ficha_cambios(array('edad' => '20'), array('fuerza' => '1'), $campos), array('fuerza' => 'Fuerza'),
    'cambios: columna no leida despues no cuenta');
fm_igual(opg_ficha_cambios(array(), array('wanted' => '100'), $campos), array('wanted' => 'Recompensa'), 'cambios: de nada a valor');
fm_igual(opg_ficha_cambios('x', array(), $campos), array(), 'cambios: entrada no valida');

// Campos excluidos: akuma (aviso propio), identidad secreta, internos; notas en modificar_ficha
foreach (array('akuma', 'secret1', 'hao_chance', 'wantedGuardado', 'movidoInframundo', 'temporada', 'dia', 'fx', 'como_nos_conociste') as $c) {
    fm_igual(isset($campos[$c]), false, "atributos: $c no se avisa");
}
fm_igual(isset(opg_ficha_campos_modificar()['notas']), false, 'modificar: notas no se avisa');
fm_igual(opg_ficha_campos_modificar()['espe'], 'Especialización', 'modificar: etiqueta de especialidad');
fm_igual($campos['belica12'] . '|' . $campos['estilo4'], 'Disciplina 12|Estilo 4', 'atributos: belicas y estilos numerados');

// Resumen de campos
fm_igual(opg_ficha_resumen_campos(array('Fuerza', 'Nivel')), array('campos' => 'Fuerza, Nivel', 'mas' => 0), 'resumen: dos campos');
fm_igual(opg_ficha_resumen_campos(array('Fuerza', 'Fuerza', ' ', '<b>Nivel</b>')), array('campos' => 'Fuerza, Nivel', 'mas' => 0),
    'resumen: sin repetidos, vacios ni etiquetas');
$diez = array();
for ($i = 1; $i <= 10; $i++) {
    $diez[] = 'Campo ' . $i;
}
fm_igual(opg_ficha_resumen_campos($diez), array('campos' => 'Campo 1, Campo 2, Campo 3, Campo 4, Campo 5, Campo 6, Campo 7, Campo 8', 'mas' => 2),
    'resumen: hasta 8 nombres y el resto contado');
$largo = opg_ficha_resumen_campos(array(str_repeat('a', 150), str_repeat('b', 100)));
fm_igual($largo, array('campos' => str_repeat('a', 150), 'mas' => 1), 'resumen: no pasa del largo maximo');

// Aviso: un guardado con varios cambios produce UN aviso que los lista todos
$cambios = opg_ficha_cambios($antes, $despues, $campos);
$a = opg_ficha_aviso(42, $cambios, $despues, STAFF, HORA);
fm_igual(array($a['tipo'], $a['destinatario'], $a['datos']), array('ficha_modificada', 42, array('fid' => 42, 'campos' => 'Nivel, Fuerza')),
    'aviso: tipo, destinatario y datos');
fm_igual((bool) preg_match('/^ficha_mod:42:1700000000\.[0-9a-f]{10}$/', $a['clave']), true, 'aviso: clave ficha_mod:UID:MARCA (' . $a['clave'] . ')');
fm_igual(opg_ficha_aviso(42, $cambios, $despues, STAFF, HORA)['clave'], $a['clave'], 'aviso: mismo guardado, misma clave');
$otro = $despues;
$otro['fuerza'] = '13';
fm_igual(opg_ficha_aviso(42, $cambios, $otro, STAFF, HORA)['clave'] !== $a['clave'], true, 'aviso: otro valor en el mismo segundo, otra clave');
fm_igual(opg_ficha_aviso(42, $cambios, $despues, STAFF, HORA + 1)['clave'] !== $a['clave'], true, 'aviso: otra hora, otra clave');
fm_igual(strlen(opg_ficha_aviso(4294967295, $cambios, $despues, STAFF, 9999999999)['clave']) <= 128, true, 'aviso: clave corta');

$muchos = array();
foreach (array_slice($campos, 0, 12, true) as $c => $n) {
    $muchos[$c] = $n;
}
$m = opg_ficha_aviso(42, $muchos, array(), STAFF, HORA);
fm_igual($m['datos']['mas'], 4, 'aviso: doce cambios, ocho nombres y 4 mas');

// No se avisa: sin cambios ni fid invalido. El propio usuario SI recibe su aviso (queda en su historial)
fm_igual(opg_ficha_aviso(42, array(), $despues, STAFF, HORA), null, 'sin cambios: no avisa');
fm_igual(opg_ficha_aviso(0, $cambios, $despues, STAFF, HORA), null, 'fid 0: no avisa');
fm_igual(opg_ficha_aviso(42, $cambios, $despues, 42, HORA)['destinatario'], 42, 'propio usuario: avisa igual');
fm_igual(opg_ficha_avisar_cambios(42, $antes, $antes, $campos, STAFF, HORA), false, 'sin cambios: el envio no hace nada');

// Datos sin valores, razon ni staff (requisitos 26, 27 y 50)
fm_igual(array_keys($a['datos']), array('fid', 'campos'), 'datos: solo ficha y nombres de campo');
fm_igual(strpos(json_encode($a['datos']), '12') === false, true, 'datos: no llevan los valores nuevos');

// Texto y enlace
fm_igual(opg_notif_texto('ficha_modificada', $a['datos']), 'El staff modificó tu ficha: Nivel, Fuerza.', 'texto: lista los campos');
fm_igual(opg_notif_texto('ficha_modificada', $m['datos']), 'El staff modificó tu ficha: ' . $m['datos']['campos'] . ' y 4 más.', 'texto: con resto');
fm_igual(opg_notif_texto('ficha_modificada', array()), 'El staff modificó tu ficha. Revísala.', 'texto: sin datos');
$t = opg_notif_texto('ficha_modificada', array('campos' => '<img src=x> & "a"'));
fm_igual(strpos($t, '<') === false && strpos($t, '"') === false && strpos($t, '&amp;') !== false, true, 'texto: escapado (' . $t . ')');
fm_igual(opg_notif_url('ficha_modificada', $a['datos']), 'op/personaje.php?uid=42', 'enlace: ficha del usuario');

$cat = opg_notif_catalogo();
fm_igual(array($cat['ficha_modificada']['categoria'], $cat['ficha_modificada']['origen'], $cat['ficha_modificada']['importancia'],
    $cat['ficha_modificada']['agrupable']), array('ficha', 'gestion', 'normal', false), 'catalogo: ficha_modificada');

if ($fallos) {
    fwrite(STDERR, "FALLOS (" . count($fallos) . "):\n" . implode("\n", $fallos) . "\n");
    exit(1);
}
echo "OK ficha_modificada\n";
