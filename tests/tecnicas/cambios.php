<?php
define('IN_MYBB', 1);
require __DIR__ . '/../../inc/plugins/lib/tecnicas_cambios.php';

$fallos = 0;
function prueba_cambio($nombre, $actual, $esperado)
{
    global $fallos;
    if ($actual !== $esperado) {
        $fallos++;
        echo "FALLO {$nombre}: " . var_export($actual, true) . ' != ' . var_export($esperado, true) . "\n";
    } else {
        echo "ok {$nombre}\n";
    }
}

$payload = array(
    'version' => 1,
    'tid' => 'KENB603',
    'nombre' => 'Visión del futuro I',
    'accion' => 'modificada',
    'cambios' => array(array('campo' => 'clase', 'antes' => 'Mantenida', 'despues' => 'Activa')),
);
$nuevo = opg_tecnicas_interpretar_log(OPG_TECNICAS_CAMBIO_MARCA . json_encode($payload));
prueba_cambio('formato estructurado', $nuevo['formato'], 'estructurado');
prueba_cambio('tid estructurado', $nuevo['tid'], 'KENB603');
prueba_cambio('valor anterior', $nuevo['cambios'][0]['antes'], 'Mantenida');
prueba_cambio('texto staff', strpos(opg_tecnicas_cambio_a_texto($nuevo), 'Mantenida → Activa') !== false, true);
prueba_cambio('campo estructurado', opg_tecnicas_cambio_campos_detectados($nuevo), array('clase'));
prueba_cambio('grupo clasificación', opg_tecnicas_cambio_coincide_grupo(array('clase'), 'clasificacion'), true);

$estilos_personaje = array('Estilo Canon' => true, 'Haki' => true);
$ramas_personaje = array('Rokushiki' => true);
prueba_cambio(
    'rama específica propia',
    opg_tecnicas_coincide_estilos_personaje(array('estilo' => 'Estilo Canon', 'rama' => 'Rokushiki'), $estilos_personaje, $ramas_personaje),
    true
);
prueba_cambio(
    'rama específica ajena',
    opg_tecnicas_coincide_estilos_personaje(array('estilo' => 'Estilo Canon', 'rama' => 'Sichirin Kaiho'), $estilos_personaje, $ramas_personaje),
    false
);
prueba_cambio(
    'estilo sin rama específica',
    opg_tecnicas_coincide_estilos_personaje(array('estilo' => 'Haki', 'rama' => 'Haki'), $estilos_personaje, $ramas_personaje),
    true
);

$legacy = opg_tecnicas_interpretar_log('Técnica KENB703 (modificada por Gretta): nombre=Visión del futuro II, clase=Activa, tipo=Utilidad.');
prueba_cambio('legacy moderno', $legacy['tid'], 'KENB703');
prueba_cambio('legacy acción', $legacy['accion'], 'modificada');

$antiguo = opg_tecnicas_interpretar_log("Cambio a técnica ID DEPO01 (Senda de la División).\nLos cambios son:\nclase=Pasiva");
prueba_cambio('legacy antiguo', $antiguo['tid'], 'DEPO01');
prueba_cambio('legacy nombre', $antiguo['nombre'], 'Senda de la División');
prueba_cambio('campos legacy', opg_tecnicas_cambio_campos_detectados($antiguo), array('clase'));

prueba_cambio('ignora otros logs', opg_tecnicas_interpretar_log('Objeto ACP001 modificado.'), null);
exit($fallos ? 1 : 0);
