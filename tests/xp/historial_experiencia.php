<?php
/**
 * Historial de experiencia de op/personaje.php (jscripts/ficha/portada.js): las filas con decimales entre parentesis,
 * como las del entrenamiento ("Experiencia: 18682.76->18704.01 (21.25)"), deben leerse bien y no descartarse.
 *
 * Carga las funciones reales de portada.js (extractExperiencia y descripcionHistorial) en Node y las ejecuta. Sin Node
 * disponible termina bien con "omitida", porque la prueba no puede ejecutarse.
 */

$js = file_get_contents(__DIR__ . '/../../jscripts/ficha/portada.js');
if ($js === false) {
    fwrite(STDERR, "No se pudo leer jscripts/ficha/portada.js\n");
    exit(1);
}

/** Texto de la funcion $nombre, desde "function nombre" hasta la llave que la cierra. */
function px_funcion($js, $nombre)
{
    $ini = strpos($js, 'function ' . $nombre);
    if ($ini === false) {
        fwrite(STDERR, "No se encontro la funcion $nombre en portada.js\n");
        exit(1);
    }
    $nivel = 0;
    $abierta = false;
    for ($i = strpos($js, '{', $ini); $i < strlen($js); $i++) {
        if ($js[$i] === '{') {
            $nivel++;
            $abierta = true;
        } elseif ($js[$i] === '}') {
            $nivel--;
            if ($abierta && $nivel === 0) {
                return substr($js, $ini, $i - $ini + 1);
            }
        }
    }
    fwrite(STDERR, "No se pudo delimitar la funcion $nombre\n");
    exit(1);
}

$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node === '') {
    echo "OK historial_experiencia: omitida (Node no esta disponible)\n";
    exit(0);
}

$casos = array(
    // log, categoria, experiencia esperada, antes, despues
    array('Experiencia: 18682.76->18704.01 (21.25)', '[Entrenamiento][Experiencia]', 21.25, 18682.76, 18704.01),
    array('Experiencia: 18704.01->18734.01 (30)', '[Recompensa][Experiencia]', 30, 18704.01, 18734.01),
    array('Experiencia: 100->70 (-30)', '[Modificación de experiencia]', -30, 100, 70),
    array('Experiencia: 100->99.5 (-0.5)', '[Cofre][Experiencia]', -0.5, 100, 99.5),
    array('texto sin formato', '[Cofre][Experiencia]', 0, 0, 0),
);

$prog = px_funcion($js, 'extractExperiencia') . "\n" . px_funcion($js, 'descripcionHistorial') . "\n"
    . 'const casos = ' . json_encode($casos, JSON_UNESCAPED_UNICODE) . ";\n"
    . "const salida = casos.map(c => Object.assign({ desc: descripcionHistorial(c[1], c[0]) }, extractExperiencia(c[0])));\n"
    . "console.log(JSON.stringify(salida));\n";

$archivo = tempnam(sys_get_temp_dir(), 'pxh') . '.js';
file_put_contents($archivo, $prog);
$salida = shell_exec(escapeshellcmd($node) . ' ' . escapeshellarg($archivo) . ' 2>&1');
@unlink($archivo);

$res = json_decode(trim((string) $salida), true);
if (!is_array($res) || count($res) !== count($casos)) {
    fwrite(STDERR, "La ejecucion en Node no devolvio lo esperado:\n$salida\n");
    exit(1);
}

$fallos = 0;
function px_igual($obtenido, $esperado, $nombre)
{
    global $fallos;
    if ($obtenido !== $esperado && !(is_numeric($obtenido) && is_numeric($esperado) && abs($obtenido - $esperado) < 1e-9)) {
        $fallos++;
        echo "FALLA: $nombre\n  esperado: " . json_encode($esperado) . "\n  obtenido: " . json_encode($obtenido) . "\n";
    }
}
foreach ($casos as $i => $c) {
    px_igual($res[$i]['experiencia'], $c[2], "'{$c[0]}': cantidad");
    px_igual($res[$i]['experienciaBefore'], $c[3], "'{$c[0]}': antes");
    px_igual($res[$i]['experienciaAfter'], $c[4], "'{$c[0]}': despues");
}
px_igual($res[0]['desc'], 'Entrenamiento', 'descripcion de un entrenamiento');
px_igual($res[1]['desc'], '[Recompensa][Experiencia]', 'las otras categorias no cambian de descripcion');

if ($fallos > 0) {
    echo "CON FALLOS historial_experiencia: $fallos\n";
    exit(1);
}
echo "OK historial_experiencia: " . count($casos) . " casos\n";
