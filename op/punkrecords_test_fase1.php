<?php
/**
 * TEMPORAL — prueba de la Fase 1 del RAG: genera fragmentos (técnicas +
 * guías), los sube a Supabase (sin embeddings todavía) y hace una consulta
 * de prueba de solo texto contra buscar_hibrido.
 *
 * Solo staff. Bórralo del servidor cuando termines de probar.
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'punkrecords_test_fase1.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";
require_once "./functions/punkrecords_config.php";
require_once "./functions/punkrecords_rag.php";
require_once "./functions/punkrecords_supabase.php";

if (!is_staff((int) $mybb->user['uid'])) {
    die('No autorizado.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnóstico de configuración (sin exponer secretos completos) ===\n\n";
$_url = $config['punkrecords']['supabase_url'] ?? '';
$_key = $config['punkrecords']['supabase_key'] ?? '';
echo "  supabase_url: " . ($_url === '' ? '(vacío)' : $_url) . "\n";
echo "  supabase_key sin trim(): longitud " . strlen($_key) . "\n";
echo "  supabase_key con trim(): longitud " . strlen(trim($_key)) . ", empieza con \"" . substr(trim($_key), 0, 12) . "...\", termina con \"..." . substr(trim($_key), -6) . "\"\n";
echo "  bytes en hexadecimal de los primeros y últimos 5 caracteres (para ver espacios/saltos invisibles):\n";
echo "    inicio: " . bin2hex(substr($_key, 0, 5)) . "\n";
echo "    final:  " . bin2hex(substr($_key, -5)) . "\n";
$pos_puntos = strpos($_key, '...');
echo "  ¿contiene '...' literal en algún punto? " . ($pos_puntos === false ? 'NO' : "SÍ, en la posición {$pos_puntos}") . "\n";
echo "  ¿contiene espacios en medio? " . (strpos(trim($_key), ' ') === false ? 'NO' : 'SÍ') . "\n\n";

echo "=== Fase 1: generando fragmentos ===\n\n";

$fragmentos = pr_generar_todos_los_fragmentos($db);
$por_fuente = [];
foreach ($fragmentos as $f) {
    $por_fuente[$f['fuente']] = ($por_fuente[$f['fuente']] ?? 0) + 1;
}
foreach ($por_fuente as $fuente => $total) {
    echo "  {$fuente}: {$total} fragmentos\n";
}
echo "  Total: " . count($fragmentos) . "\n\n";

echo "=== Comparando con lo que ya hay en Supabase (indexado incremental) ===\n\n";

// Agrupar por fuente para comparar hash contra lo ya subido.
$fragmentos_por_fuente = [];
foreach ($fragmentos as $f) {
    $fragmentos_por_fuente[$f['fuente']][] = $f;
}

$a_subir = [];
$sin_cambios = 0;

foreach ($fragmentos_por_fuente as $fuente => $lista) {
    $existentes = pr_supabase_listar_hashes($fuente) ?? [];
    foreach ($lista as $f) {
        $clave = $f['fuente'] . '|' . $f['ref'] . '|' . $f['parte'];
        if (($existentes[$clave] ?? null) === $f['hash']) {
            $sin_cambios++;
            continue;
        }
        $a_subir[] = $f;
    }
}

echo "  Sin cambios (no se resuben): {$sin_cambios}\n";
echo "  Nuevos o modificados a subir: " . count($a_subir) . "\n\n";

echo "=== Subiendo por lotes ===\n\n";

$lote_tamano = 200;
$subidos = 0;
$errores = [];

foreach (array_chunk($a_subir, $lote_tamano) as $i => $lote) {
    $resultado = pr_supabase_subir_lote($lote);
    if ($resultado['ok']) {
        $subidos += count($lote);
        echo "  Lote " . ($i + 1) . ": " . count($lote) . " fragmentos OK\n";
    } else {
        $errores[] = "Lote " . ($i + 1) . ": " . $resultado['error'];
        echo "  Lote " . ($i + 1) . ": ERROR — {$resultado['error']}\n";
    }
}

echo "\n  Subidos: {$subidos} / " . count($a_subir) . "\n";
if (!empty($errores)) {
    echo "  Errores: " . count($errores) . "\n";
}

echo "\n=== Eliminando obsoletos (lo que ya no existe en el origen) ===\n\n";

foreach ($fragmentos_por_fuente as $fuente => $lista) {
    $vigentes = [];
    foreach ($lista as $f) {
        $vigentes[$f['ref']][] = $f['parte'];
    }
    $resultado = pr_supabase_eliminar_obsoletos($fuente, $vigentes);
    if ($resultado['ok']) {
        echo "  {$fuente}: {$resultado['eliminados']} fragmento(s) obsoleto(s) eliminado(s)\n";
    } else {
        echo "  {$fuente}: ERROR — {$resultado['error']}\n";
    }
}

echo "\n=== Consulta de prueba (solo texto, sin embeddings todavía) ===\n\n";

$preguntas_prueba = [
    '¿Cómo se obtienen los Puntos de Oficio?',
    '¿Qué diferencia hay entre un tema Común y un Diario?',
];

foreach ($preguntas_prueba as $pregunta) {
    echo "Pregunta: {$pregunta}\n";
    $resultado = pr_supabase_buscar($pregunta, null, 6);

    if (!$resultado['ok']) {
        echo "  ERROR: {$resultado['error']}\n\n";
        continue;
    }

    if (empty($resultado['data'])) {
        echo "  (sin resultados)\n\n";
        continue;
    }

    foreach ($resultado['data'] as $fila) {
        $extracto = mb_substr(str_replace("\n", ' ', $fila['texto']), 0, 100, 'UTF-8');
        echo "  [{$fila['fuente']}:{$fila['ref']}] {$fila['titulo']} (puntaje {$fila['puntaje']}) — {$extracto}...\n";
    }
    echo "\n";
}

echo "=== Fin de la prueba de Fase 1 ===\n";
exit;
