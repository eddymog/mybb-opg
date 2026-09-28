<?php
/**
 * TEMPORAL — prueba de la Fase 2 del RAG, versión resumible por peticiones
 * cortas (el sitio está detrás de Cloudflare, que corta cualquier petición
 * a los ~100s — no se puede hacer todo en una sola petición larga).
 *
 * Cada lote de fragmentos se procesa en su propia petición HTTP; una página
 * con JavaScript va llamando al siguiente lote, con reintento si Gemini
 * devuelve cuota agotada (429).
 *
 * Solo staff. Bórralo del servidor cuando termines de probar.
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'punkrecords_test_fase2.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";
require_once "./functions/punkrecords_config.php";
require_once "./functions/punkrecords_rag.php";
require_once "./functions/punkrecords_supabase.php";
require_once "./functions/punkrecords_proveedores.php";

if (!is_staff((int) $mybb->user['uid'])) {
    die('No autorizado.');
}

// Facción de quien prueba, para el tono de la respuesta (personalidad por
// facción). Null si no tiene ficha o no tiene facción — usa el tono neutro.
$pr_faccion_actual = pr_obtener_faccion($db, $mybb->user['uid']);

// 100 (el máximo real de batchEmbedContents): el límite de Gemini es por
// CANTIDAD de peticiones por minuto/día (RPM/RPD), no por tokens — un lote
// más chico solo significa más peticiones para el mismo trabajo, lo que
// empeora el problema en vez de ayudar.
const PR_TEST_LOTE_TAMANO = 100;

function pr_test_lotes()
{
    global $db;
    static $fragmentos = null;
    if ($fragmentos === null) {
        $fragmentos = pr_generar_todos_los_fragmentos($db);
    }
    return array_chunk($fragmentos, PR_TEST_LOTE_TAMANO, true);
}

$accion = $mybb->get_input('accion');

if ($accion === 'info') {
    header('Content-Type: application/json; charset=utf-8');
    $lotes = pr_test_lotes();
    echo json_encode([
        'total_fragmentos' => array_sum(array_map('count', $lotes)),
        'total_lotes' => count($lotes),
        'lote_tamano' => PR_TEST_LOTE_TAMANO,
    ]);
    exit;
}

if ($accion === 'test_voyage') {
    // Una sola llamada mínima a Voyage, sin tocar Supabase para nada — solo
    // para validar la clave y el formato antes de comprometerse al indexado
    // completo de 1.821 fragmentos.
    header('Content-Type: application/json; charset=utf-8');

    $textos_prueba = ['Los Puntos de Oficio se obtienen entrenando el oficio elegido.'];
    $vectores = pr_embeber($textos_prueba, 'RETRIEVAL_DOCUMENT');

    if ($GLOBALS['pr_embeber_ultimo_error'] !== '') {
        echo json_encode(['ok' => false, 'error' => $GLOBALS['pr_embeber_ultimo_error']]);
        exit;
    }

    $vector = $vectores[0] ?? null;
    if ($vector === null) {
        echo json_encode(['ok' => false, 'error' => 'Voyage respondió OK pero sin vector para el texto de prueba.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'dimensiones' => count($vector),
        'primeros_valores' => array_slice($vector, 0, 5),
        'esperado' => PR_EMBEDDING_DIMENSIONES,
    ]);
    exit;
}

if ($accion === 'limpiar') {
    // Borra de Supabase los fragmentos cuyo (fuente, ref, parte) ya no
    // corresponde a nada generado ahora — necesario después de cambiar
    // cómo se fragmentan las guías (más fragmentos, más chicos, distinta
    // numeración de 'parte'); si no, quedan huérfanos con contenido viejo.
    header('Content-Type: application/json; charset=utf-8');

    $lotes = pr_test_lotes();
    $fragmentos = array_merge(...$lotes);

    $por_fuente = [];
    foreach ($fragmentos as $f) {
        $por_fuente[$f['fuente']][$f['ref']][] = $f['parte'];
    }

    $resumen = [];
    foreach ($por_fuente as $fuente => $vigentes) {
        $resultado = pr_supabase_eliminar_obsoletos($fuente, $vigentes);
        $resumen[$fuente] = $resultado['ok'] ? $resultado['eliminados'] : ('error: ' . $resultado['error']);
    }

    echo json_encode(['ok' => true, 'eliminados' => $resumen]);
    exit;
}

if ($accion === 'lote') {
    header('Content-Type: application/json; charset=utf-8');
    $indice = (int) $mybb->get_input('indice');
    $lotes = pr_test_lotes();

    if (!isset($lotes[$indice])) {
        echo json_encode(['ok' => false, 'error' => 'Índice de lote fuera de rango.']);
        exit;
    }

    $lote = $lotes[$indice];

    // Indexado incremental: comparar por hash contra lo que ya hay en
    // Supabase, y solo embeber/subir lo nuevo o modificado. Esto se había
    // quedado afuera al reescribir el script para que fuera resumible por
    // peticiones cortas — sin esto, cada corrida reembebía y resubía TODO,
    // gastando cuota de Voyage de más en fragmentos que no cambiaron.
    static $hashes_por_fuente = [];
    $lote_a_procesar = [];
    $sin_cambios = 0;
    foreach ($lote as $f) {
        $fuente = $f['fuente'];
        if (!isset($hashes_por_fuente[$fuente])) {
            $hashes_por_fuente[$fuente] = pr_supabase_listar_hashes($fuente) ?? [];
        }
        $clave = $f['fuente'] . '|' . $f['ref'] . '|' . $f['parte'];
        if (($hashes_por_fuente[$fuente][$clave] ?? null) === $f['hash']) {
            $sin_cambios++;
            continue;
        }
        $lote_a_procesar[] = $f;
    }

    if (empty($lote_a_procesar)) {
        echo json_encode(['ok' => true, 'subidos' => 0, 'sin_cambios' => $sin_cambios, 'sin_embedding' => 0]);
        exit;
    }

    $textos = array_map(fn($f) => $f['texto'], $lote_a_procesar);
    $vectores = pr_embeber($textos, 'RETRIEVAL_DOCUMENT');

    if ($GLOBALS['pr_embeber_ultimo_error'] !== '') {
        echo json_encode(['ok' => false, 'error' => $GLOBALS['pr_embeber_ultimo_error']]);
        exit;
    }

    $lote_con_embedding = [];
    $sin_embedding = 0;
    $i = 0;
    foreach ($lote_a_procesar as $f) {
        $f['embedding'] = $vectores[$i] ?? null;
        if ($f['embedding'] === null) {
            $sin_embedding++;
        }
        $lote_con_embedding[] = $f;
        $i++;
    }

    $resultado = pr_supabase_subir_lote($lote_con_embedding);
    if (!$resultado['ok']) {
        echo json_encode(['ok' => false, 'error' => 'Error al subir a Supabase: ' . $resultado['error']]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'subidos' => count($lote_con_embedding),
        'sin_cambios' => $sin_cambios,
        'sin_embedding' => $sin_embedding,
    ]);
    exit;
}

if ($accion === 'buscar_texto') {
    // Solo texto, sin embedding: cero peticiones a Gemini. Encuentra
    // cualquier fragmento (con o sin vector) siempre que comparta palabras
    // con la pregunta — la misma búsqueda de la Fase 1.
    header('Content-Type: application/json; charset=utf-8');
    $pregunta = trim($mybb->get_input('q'));
    if ($pregunta === '') {
        echo json_encode(['pregunta' => '', 'error' => 'Pregunta vacía.']);
        exit;
    }

    $resultado = pr_supabase_buscar($pregunta, null, 10);
    if (!$resultado['ok']) {
        echo json_encode(['pregunta' => $pregunta, 'error' => $resultado['error']]);
        exit;
    }

    $filas = [];
    foreach (($resultado['data'] ?? []) as $fila) {
        $filas[] = [
            'fuente' => $fila['fuente'],
            'ref' => $fila['ref'],
            'titulo' => $fila['titulo'],
            'puntaje' => $fila['puntaje'],
            'similitud' => $fila['similitud'],
            'extracto' => mb_substr(str_replace("\n", ' ', $fila['texto']), 0, 150, 'UTF-8'),
        ];
    }
    echo json_encode(['pregunta' => $pregunta, 'resultados' => $filas]);
    exit;
}

if ($accion === 'buscar_una') {
    // Pregunta libre, para probar ad hoc sin tocar el bloque de preguntas
    // fijas de abajo (útil ahora mismo para preguntas de técnicas, que son
    // las que ya tienen embedding real subido).
    header('Content-Type: application/json; charset=utf-8');
    $pregunta = trim($mybb->get_input('q'));
    if ($pregunta === '') {
        echo json_encode(['pregunta' => '', 'error' => 'Pregunta vacía.']);
        exit;
    }

    $embedding_pregunta = pr_embeber_pregunta($pregunta);
    if ($embedding_pregunta === null) {
        echo json_encode(['pregunta' => $pregunta, 'error' => $GLOBALS['pr_embeber_ultimo_error'] ?: 'No se pudo calcular el embedding.']);
        exit;
    }

    $resultado = pr_supabase_buscar($pregunta, $embedding_pregunta, 10);
    if (!$resultado['ok']) {
        echo json_encode(['pregunta' => $pregunta, 'error' => $resultado['error']]);
        exit;
    }

    $filas = [];
    foreach (($resultado['data'] ?? []) as $fila) {
        $filas[] = [
            'fuente' => $fila['fuente'],
            'ref' => $fila['ref'],
            'titulo' => $fila['titulo'],
            'puntaje' => $fila['puntaje'],
            'similitud' => $fila['similitud'],
            'extracto' => mb_substr(str_replace("\n", ' ', $fila['texto']), 0, 150, 'UTF-8'),
        ];
    }
    echo json_encode(['pregunta' => $pregunta, 'resultados' => $filas]);
    exit;
}

if ($accion === 'generar_una') {
    // Pipeline completo: buscar fragmentos, generar la respuesta, calcular
    // las fuentes citadas. Gasta 2 llamadas a Gemini (embedding de la
    // pregunta + generación) — usar con cuidado.
    header('Content-Type: application/json; charset=utf-8');
    $pregunta = trim($mybb->get_input('q'));
    if ($pregunta === '') {
        echo json_encode(['error' => 'Pregunta vacía.']);
        exit;
    }

    $embedding_pregunta = pr_embeber_pregunta($pregunta);
    if ($embedding_pregunta === null) {
        echo json_encode(['error' => 'No se pudo calcular el embedding: ' . ($GLOBALS['pr_embeber_ultimo_error'] ?: 'error desconocido')]);
        exit;
    }

    $resultado_busqueda = pr_supabase_buscar($pregunta, $embedding_pregunta, 10);
    if (!$resultado_busqueda['ok']) {
        echo json_encode(['error' => 'Error al buscar: ' . $resultado_busqueda['error']]);
        exit;
    }

    $fragmentos = $resultado_busqueda['data'] ?? [];
    if (empty($fragmentos)) {
        echo json_encode(['respuesta' => 'No encontré información sobre eso en las fuentes indexadas.', 'fuentes' => []]);
        exit;
    }

    $resultado_generacion = pr_generar($pregunta, $fragmentos, $pr_faccion_actual);
    if (!$resultado_generacion['ok']) {
        echo json_encode(['error' => 'Error al generar: ' . $resultado_generacion['error']]);
        exit;
    }

    $fuentes = pr_extraer_fuentes_citadas($resultado_generacion['texto'], $fragmentos);
    if (!pr_pregunta_pide_fuente($pregunta)) {
        $fuentes = [];
    }

    echo json_encode([
        'respuesta' => pr_limpiar_citas_visibles($resultado_generacion['texto']),
        'fuentes' => $fuentes,
    ]);
    exit;
}

if ($accion === 'generar_texto') {
    // Igual que 'generar_una', pero busca solo por texto (sin embedding de
    // la pregunta): 1 sola llamada a Gemini en total (la generación), no 2.
    // Usa el modelo de generación, que tiene una cuota aparte y casi sin
    // tocar — evita el límite de embeddings que sigue ajustado.
    header('Content-Type: application/json; charset=utf-8');
    $pregunta = trim($mybb->get_input('q'));
    if ($pregunta === '') {
        echo json_encode(['error' => 'Pregunta vacía.']);
        exit;
    }

    $resultado_busqueda = pr_supabase_buscar($pregunta, null, 10);
    if (!$resultado_busqueda['ok']) {
        echo json_encode(['error' => 'Error al buscar: ' . $resultado_busqueda['error']]);
        exit;
    }

    $fragmentos = $resultado_busqueda['data'] ?? [];
    if (empty($fragmentos)) {
        echo json_encode(['respuesta' => 'No encontré información sobre eso en las fuentes indexadas.', 'fuentes' => []]);
        exit;
    }

    $resultado_generacion = pr_generar($pregunta, $fragmentos, $pr_faccion_actual);
    if (!$resultado_generacion['ok']) {
        echo json_encode(['error' => 'Error al generar: ' . $resultado_generacion['error']]);
        exit;
    }

    $fuentes = pr_extraer_fuentes_citadas($resultado_generacion['texto'], $fragmentos);
    if (!pr_pregunta_pide_fuente($pregunta)) {
        // Por defecto no se muestran fuentes, para que se sienta como una
        // respuesta natural — solo si el jugador las pide explícitamente.
        $fuentes = [];
    }

    echo json_encode([
        'respuesta' => pr_limpiar_citas_visibles($resultado_generacion['texto']),
        'fuentes' => $fuentes,
    ]);
    exit;
}

if ($accion === 'buscar') {
    header('Content-Type: application/json; charset=utf-8');
    $preguntas_prueba = [
        '¿Cómo se obtienen los Puntos de Oficio?',
        '¿Qué diferencia hay entre un tema Común y un Diario?',
    ];

    $salida = [];
    foreach ($preguntas_prueba as $pregunta) {
        $embedding_pregunta = pr_embeber_pregunta($pregunta);
        if ($embedding_pregunta === null) {
            $salida[] = ['pregunta' => $pregunta, 'error' => $GLOBALS['pr_embeber_ultimo_error'] ?: 'No se pudo calcular el embedding.'];
            continue;
        }

        $resultado = pr_supabase_buscar($pregunta, $embedding_pregunta, 10);
        if (!$resultado['ok']) {
            $salida[] = ['pregunta' => $pregunta, 'error' => $resultado['error']];
            continue;
        }

        $filas = [];
        foreach (($resultado['data'] ?? []) as $fila) {
            $filas[] = [
                'fuente' => $fila['fuente'],
                'ref' => $fila['ref'],
                'titulo' => $fila['titulo'],
                'puntaje' => $fila['puntaje'],
                'similitud' => $fila['similitud'],
                'extracto' => mb_substr(str_replace("\n", ' ', $fila['texto']), 0, 100, 'UTF-8'),
            ];
        }
        $salida[] = ['pregunta' => $pregunta, 'resultados' => $filas];
    }

    echo json_encode($salida);
    exit;
}

// Sin acción: página HTML con el driver en JS.
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Punk Records — Prueba Fase 2</title>
<style>
body { font-family: monospace; background: #111; color: #ddd; padding: 20px; }
#log { white-space: pre-wrap; line-height: 1.5; }
.ok { color: #6f6; }
.err { color: #f66; }
#pregunta { width: 400px; background: #222; color: #ddd; border: 1px solid #444; padding: 6px; font-family: monospace; }
</style>
</head>
<body>
<h2>Punk Records — Fase 2 (embeddings, resumible)</h2>

<div>
    <button id="btn_test_voyage">Probar Voyage (1 llamada mínima, sin tocar Supabase)</button>
    <span id="test_voyage_resultado" style="margin-left:10px;"></span>
</div>

<hr style="border-color:#333;">

<div>
    <input type="text" id="pregunta" placeholder="Pregunta libre (ej. sobre una técnica)">
    <button id="btn_texto">Buscar solo texto (gratis, sin Gemini)</button>
    <button id="btn_generar_texto">Generar respuesta (solo texto, 1 petición)</button>
    <button id="btn_pregunta">Buscar híbrido (1 petición)</button>
    <button id="btn_generar">Generar respuesta completa (2 peticiones)</button>
    <div style="color:#888; font-size:12px;">Usar con cuidado — quedan pocas peticiones hoy.</div>
</div>
<div id="log_pregunta" style="margin: 10px 0 20px;"></div>

<hr style="border-color:#333;">

<button id="btn">Iniciar indexado completo (1.821 fragmentos)</button>
<div id="log"></div>
<script>
const log = document.getElementById('log');
function linea(texto, clase) {
    const p = document.createElement('div');
    if (clase) p.className = clase;
    p.textContent = texto;
    log.appendChild(p);
    window.scrollTo(0, document.body.scrollHeight);
}

document.getElementById('btn_test_voyage').addEventListener('click', async () => {
    const span = document.getElementById('test_voyage_resultado');
    span.textContent = 'Probando...';
    span.className = '';
    const r = await fetch('?accion=test_voyage');
    const data = await r.json();
    if (!data.ok) {
        span.textContent = 'ERROR: ' + data.error;
        span.className = 'err';
        return;
    }
    const coincide = data.dimensiones === data.esperado ? '✓' : '⚠️ no coincide con PR_EMBEDDING_DIMENSIONES';
    span.textContent = `OK — vector de ${data.dimensiones} dimensiones (esperado ${data.esperado}) ${coincide}. Primeros valores: [${data.primeros_valores.map(v => v.toFixed(4)).join(', ')}...]`;
    span.className = 'ok';
});

const logPregunta = document.getElementById('log_pregunta');
function lineaPregunta(texto, clase) {
    const p = document.createElement('div');
    if (clase) p.className = clase;
    p.textContent = texto;
    logPregunta.appendChild(p);
}

document.getElementById('btn_texto').addEventListener('click', async () => {
    const q = document.getElementById('pregunta').value.trim();
    if (!q) return;
    logPregunta.innerHTML = '';
    lineaPregunta('Buscando (solo texto, gratis): ' + q);
    const r = await fetch('?accion=buscar_texto&q=' + encodeURIComponent(q));
    const data = await r.json();
    if (data.error) {
        lineaPregunta('ERROR: ' + data.error, 'err');
        return;
    }
    if (!data.resultados.length) {
        lineaPregunta('(sin resultados)');
        return;
    }
    for (const f of data.resultados) {
        lineaPregunta(`[${f.fuente}:${f.ref}] ${f.titulo} (puntaje ${f.puntaje}, similitud ${f.similitud}) — ${f.extracto}...`);
    }
});

document.getElementById('btn_pregunta').addEventListener('click', async () => {
    const q = document.getElementById('pregunta').value.trim();
    if (!q) return;
    logPregunta.innerHTML = '';
    lineaPregunta('Buscando: ' + q);
    const r = await fetch('?accion=buscar_una&q=' + encodeURIComponent(q));
    const data = await r.json();
    if (data.error) {
        lineaPregunta('ERROR: ' + data.error, 'err');
        return;
    }
    if (!data.resultados.length) {
        lineaPregunta('(sin resultados)');
        return;
    }
    for (const f of data.resultados) {
        lineaPregunta(`[${f.fuente}:${f.ref}] ${f.titulo} (puntaje ${f.puntaje}, similitud ${f.similitud}) — ${f.extracto}...`);
    }
});

document.getElementById('btn_generar').addEventListener('click', async () => {
    const q = document.getElementById('pregunta').value.trim();
    if (!q) return;
    logPregunta.innerHTML = '';
    lineaPregunta('Generando respuesta para: ' + q);
    const r = await fetch('?accion=generar_una&q=' + encodeURIComponent(q));
    const data = await r.json();
    if (data.error) {
        lineaPregunta('ERROR: ' + data.error, 'err');
        return;
    }
    lineaPregunta('');
    lineaPregunta('Respuesta:', 'ok');
    lineaPregunta(data.respuesta);
    lineaPregunta('');
    lineaPregunta('Fuentes citadas: ' + (data.fuentes.length
        ? data.fuentes.map(f => `[${f.fuente}:${f.ref}] ${f.titulo}`).join(', ')
        : '(ninguna)'));
});

document.getElementById('btn_generar_texto').addEventListener('click', async () => {
    const q = document.getElementById('pregunta').value.trim();
    if (!q) return;
    logPregunta.innerHTML = '';
    lineaPregunta('Generando respuesta (solo texto) para: ' + q);
    const r = await fetch('?accion=generar_texto&q=' + encodeURIComponent(q));
    const data = await r.json();
    if (data.error) {
        lineaPregunta('ERROR: ' + data.error, 'err');
        return;
    }
    lineaPregunta('');
    lineaPregunta('Respuesta:', 'ok');
    lineaPregunta(data.respuesta);
    lineaPregunta('');
    lineaPregunta('Fuentes citadas: ' + (data.fuentes.length
        ? data.fuentes.map(f => `[${f.fuente}:${f.ref}] ${f.titulo}`).join(', ')
        : '(ninguna)'));
});

async function pedir(params) {
    const r = await fetch('?' + params);
    return await r.json();
}

async function procesarLote(indice, intento) {
    linea(`Lote ${indice + 1}: procesando (intento ${intento})...`);
    const r = await pedir(`accion=lote&indice=${indice}`);
    if (r.ok) {
        linea(`Lote ${indice + 1}: ${r.subidos} subidos, ${r.sin_cambios} sin cambios (saltados), ${r.sin_embedding} sin embedding`, 'ok');
        return true;
    }
    linea(`Lote ${indice + 1}: ERROR — ${r.error}`, 'err');
    if (intento >= 4) {
        linea(`Lote ${indice + 1}: se agotaron los reintentos, sigo con el siguiente.`, 'err');
        return false;
    }
    const espera = 8 * Math.pow(2, intento - 1);
    linea(`Reintentando lote ${indice + 1} en ${espera}s...`);
    await new Promise(res => setTimeout(res, espera * 1000));
    return procesarLote(indice, intento + 1);
}

async function buscar() {
    linea('\n=== Búsqueda híbrida de prueba ===\n');
    const r = await pedir('accion=buscar');
    for (const item of r) {
        linea(`Pregunta: ${item.pregunta}`);
        if (item.error) {
            linea(`  ERROR: ${item.error}`, 'err');
            continue;
        }
        if (!item.resultados.length) {
            linea('  (sin resultados)');
            continue;
        }
        for (const f of item.resultados) {
            linea(`  [${f.fuente}:${f.ref}] ${f.titulo} (puntaje ${f.puntaje}, similitud ${f.similitud}) — ${f.extracto}...`);
        }
        linea('');
    }
    linea('=== Fin ===');
}

document.getElementById('btn').addEventListener('click', async () => {
    document.getElementById('btn').disabled = true;
    const info = await pedir('accion=info');
    linea(`Total: ${info.total_fragmentos} fragmentos en ${info.total_lotes} lotes de ${info.lote_tamano}\n`);

    for (let i = 0; i < info.total_lotes; i++) {
        await procesarLote(i, 1);
    }

    linea('\nLimpiando fragmentos obsoletos...');
    const limpieza = await pedir('accion=limpiar');
    linea('Eliminados: ' + JSON.stringify(limpieza.eliminados));

    await buscar();
});
</script>
</body>
</html>
