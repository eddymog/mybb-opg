<?php
/**
 * Punk Records (RAG): clientes de proveedores — Voyage AI para embeddings,
 * Gemini para generación (redactar la respuesta final).
 *
 * Se migró de Gemini a Voyage (voyage-4) para embeddings por soporte de
 * español documentado explícitamente y mejor cociente calidad/costo — ver
 * conversación de diseño. La generación se queda en Gemini sin cambios.
 *
 * Ver docs/200_DesignPlan_Asistente.md, sección 3.3.
 */

// Reintentos cortos aquí a propósito: el sitio está detrás de Cloudflare,
// que corta cualquier petición a los ~100s. El reintento "de verdad" ante un
// 429 pasa a ser responsabilidad de quien llama (ver punkrecords_test_fase2.php),
// reintentando ese mismo lote en una petición HTTP nueva, no esperando
// adentro de una sola petición larga.
const PR_GEMINI_REINTENTOS_MAX = 1;
const PR_GEMINI_ESPERA_BASE_SEGUNDOS = 3;

/**
 * Petición HTTP genérica a la API de Gemini. Reintenta con espera creciente
 * (backoff exponencial) cuando Gemini devuelve 429 (cuota por minuto
 * agotada) — el límite real por minuto de estos modelos es más bajo de lo
 * que documenta Google, así que un solo intento no basta para un lote.
 * Devuelve ['ok' => bool, 'data' => mixed, 'error' => string].
 */
function pr_gemini_request($modelo, $metodo, array $body)
{
    global $config;

    $api_key = trim($config['punkrecords']['gemini_key'] ?? '');
    if ($api_key === '') {
        return ['ok' => false, 'data' => null, 'error' => 'Gemini no está configurado.'];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:{$metodo}";

    for ($intento = 0; $intento <= PR_GEMINI_REINTENTOS_MAX; $intento++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $api_key,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 60,
        ]);

        $respuesta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error !== '') {
            return ['ok' => false, 'data' => null, 'error' => 'Error de conexión con Gemini: ' . $curl_error];
        }

        if ($http_code === 429) {
            // Se guarda el mensaje real de Gemini (trae qué métrica exacta se
            // agotó: por minuto, por día, tokens, etc. — no asumir cuál es).
            $detalle_429 = substr((string) $respuesta, 0, 800);
            if ($intento < PR_GEMINI_REINTENTOS_MAX) {
                sleep(PR_GEMINI_ESPERA_BASE_SEGUNDOS * (2 ** $intento));
                continue;
            }
            return ['ok' => false, 'data' => null, 'error' => 'Gemini alcanzó su cuota (tras ' . ($intento + 1) . ' intentos): ' . $detalle_429];
        }

        if ($http_code !== 200) {
            return ['ok' => false, 'data' => null, 'error' => "Gemini devolvió {$http_code}: " . substr((string) $respuesta, 0, 500)];
        }

        return ['ok' => true, 'data' => json_decode($respuesta, true), 'error' => ''];
    }

    return ['ok' => false, 'data' => null, 'error' => 'Gemini alcanzó su cuota.'];
}

const PR_VOYAGE_REINTENTOS_MAX = 1;
const PR_VOYAGE_ESPERA_BASE_SEGUNDOS = 3;

/**
 * Petición HTTP genérica a la API de Voyage AI. Mismo motivo que
 * pr_gemini_request() para mantener los reintentos cortos: Cloudflare corta
 * la petición a los ~100s, así que el reintento largo es responsabilidad de
 * quien llama, en una petición HTTP nueva.
 * Devuelve ['ok' => bool, 'data' => mixed, 'error' => string].
 */
function pr_voyage_request(array $body)
{
    global $config;

    $api_key = trim($config['punkrecords']['voyage_key'] ?? '');
    if ($api_key === '') {
        return ['ok' => false, 'data' => null, 'error' => 'Voyage no está configurado.'];
    }

    $url = 'https://api.voyageai.com/v1/embeddings';

    for ($intento = 0; $intento <= PR_VOYAGE_REINTENTOS_MAX; $intento++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 60,
        ]);

        $respuesta = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error !== '') {
            return ['ok' => false, 'data' => null, 'error' => 'Error de conexión con Voyage: ' . $curl_error];
        }

        if ($http_code === 429) {
            $detalle_429 = substr((string) $respuesta, 0, 800);
            if ($intento < PR_VOYAGE_REINTENTOS_MAX) {
                sleep(PR_VOYAGE_ESPERA_BASE_SEGUNDOS * (2 ** $intento));
                continue;
            }
            return ['ok' => false, 'data' => null, 'error' => 'Voyage alcanzó su cuota (tras ' . ($intento + 1) . ' intentos): ' . $detalle_429];
        }

        if ($http_code !== 200) {
            return ['ok' => false, 'data' => null, 'error' => "Voyage devolvió {$http_code}: " . substr((string) $respuesta, 0, 500)];
        }

        return ['ok' => true, 'data' => json_decode($respuesta, true), 'error' => ''];
    }

    return ['ok' => false, 'data' => null, 'error' => 'Voyage alcanzó su cuota.'];
}

/**
 * Último error de pr_embeber(), para que quien llame pueda mostrarlo (no
 * se puede cambiar la firma de pr_embeber() sin romper el diseño ya escrito
 * en 3.3, así que el error queda disponible aparte).
 */
$GLOBALS['pr_embeber_ultimo_error'] = '';

/**
 * Calcula embeddings para varios textos en una sola llamada por lotes
 * (máximo 1.000 textos según la API de Voyage — mucho más margen que el
 * límite de 100 que tenía Gemini).
 *
 * $tarea: 'RETRIEVAL_DOCUMENT' para indexar fragmentos, 'RETRIEVAL_QUERY'
 * para la pregunta del jugador — se mapea internamente al vocabulario de
 * Voyage ('document'/'query', su parámetro input_type) para no tener que
 * tocar ningún lugar que ya llama a pr_embeber() con los valores de Gemini.
 *
 * Nota: a diferencia de Gemini, la API de Voyage usa snake_case (input,
 * input_type, output_dimension) — confirmado en su documentación oficial.
 *
 * Devuelve un array paralelo a $textos: cada posición trae el vector
 * (array de floats) o null si esa posición falló.
 */
function pr_embeber(array $textos, string $tarea)
{
    $GLOBALS['pr_embeber_ultimo_error'] = '';

    if (empty($textos)) {
        return [];
    }
    if (count($textos) > 1000) {
        throw new InvalidArgumentException('pr_embeber: máximo 1.000 textos por lote (límite de Voyage).');
    }

    $input_type = $tarea === 'RETRIEVAL_QUERY' ? 'query' : 'document';

    $body = [
        'model' => PR_MODELO_EMBEDDING,
        'input' => array_values($textos),
        'input_type' => $input_type,
        'output_dimension' => PR_EMBEDDING_DIMENSIONES,
    ];

    $resultado = pr_voyage_request($body);

    if (!$resultado['ok']) {
        $GLOBALS['pr_embeber_ultimo_error'] = $resultado['error'];
        return array_fill(0, count($textos), null);
    }

    $datos = $resultado['data']['data'] ?? [];
    $salida = array_fill(0, count($textos), null);
    foreach ($datos as $item) {
        $indice = $item['index'] ?? null;
        // array_key_exists(), no isset(): $salida[$indice] ya es null antes de
        // asignarle nada, e isset() devuelve false para un valor null aunque
        // la clave exista — con eso el vector real de Voyage nunca se copiaba.
        if (is_int($indice) && array_key_exists($indice, $salida)) {
            $salida[$indice] = $item['embedding'] ?? null;
        }
    }
    return $salida;
}

/**
 * Embedding de una sola pregunta (tarea RETRIEVAL_QUERY), para el momento
 * de la búsqueda. Devuelve el vector o null si falló.
 */
function pr_embeber_pregunta($pregunta)
{
    $resultado = pr_embeber([$pregunta], 'RETRIEVAL_QUERY');
    return $resultado[0] ?? null;
}

/**
 * Genera la respuesta final a partir de los fragmentos ya recuperados y
 * numerados por pr_construir_contexto_numerado(). Un solo generateContent,
 * como pide 6, paso 8, del diseño.
 *
 * $historial: turnos previos de la MISMA pestaña de chat (memoria de
 * conversación efímera, ver pr_parsear_historial()) — se agregan como turnos
 * reales user/model antes del turno actual, para que Gemini entienda
 * referencias tipo "y la otra" sin que el jugador tenga que repetir todo.
 * Ya viene saneado por pr_parsear_historial() (tamaño y cantidad acotados).
 *
 * Devuelve ['ok' => bool, 'texto' => string, 'error' => string].
 */
function pr_generar($pregunta, array $fragmentos, $faccion = null, array $historial = [])
{
    $contexto = pr_construir_contexto_numerado($fragmentos);

    $contents = [];
    foreach ($historial as $turno) {
        $contents[] = ['role' => 'user', 'parts' => [['text' => $turno['pregunta']]]];
        $contents[] = ['role' => 'model', 'parts' => [['text' => $turno['respuesta']]]];
    }
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $contexto . "\n\nPregunta del jugador: " . $pregunta]],
    ];

    $body = [
        // camelCase: es lo que espera la API de Gemini, a diferencia de lo
        // que asumía (mal) el archivo del MVP.
        'systemInstruction' => [
            'parts' => [['text' => pr_instrucciones_sistema($faccion)]],
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 1000,
        ],
    ];

    $resultado = pr_gemini_request(PR_MODELO_GENERACION, 'generateContent', $body);

    if (!$resultado['ok']) {
        return ['ok' => false, 'texto' => '', 'error' => $resultado['error']];
    }

    $texto = $resultado['data']['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($texto === null || trim($texto) === '') {
        return ['ok' => false, 'texto' => '', 'error' => 'Gemini no devolvió una respuesta.'];
    }

    return ['ok' => true, 'texto' => trim($texto), 'error' => ''];
}
