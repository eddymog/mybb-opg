<?php
/**
 * Punk Records (RAG): cliente de Supabase (PostgREST por HTTP).
 *
 * Buscar (buscar_hibrido), subir fragmentos por lotes (upsert), listar
 * hashes existentes (indexado incremental) y eliminar los que ya no existen.
 *
 * Ver docs/200_DesignPlan_Asistente.md, secciones 3.4 y 5.3.
 */

/**
 * Petición HTTP genérica a la API REST de Supabase.
 * Devuelve ['ok' => bool, 'http_code' => int, 'data' => mixed, 'error' => string].
 */
function pr_supabase_request($method, $path, $body = null, array $extra_headers = [])
{
    global $config;

    // trim(): protege contra un salto de línea o espacio colado al copiar la
    // clave/URL a inc/config.php, que Supabase rechaza como "Invalid API key"
    // sin más explicación.
    $url_base = rtrim(trim($config['punkrecords']['supabase_url'] ?? ''), '/');
    $api_key = trim($config['punkrecords']['supabase_key'] ?? '');

    if ($url_base === '' || $api_key === '') {
        return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'Supabase no está configurado.'];
    }

    $headers = array_merge([
        'apikey: ' . $api_key,
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ], $extra_headers);

    $ch = curl_init($url_base . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);

    $respuesta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error !== '') {
        return ['ok' => false, 'http_code' => 0, 'data' => null, 'error' => 'Error de conexión con Supabase: ' . $curl_error];
    }

    if ($http_code >= 400) {
        return ['ok' => false, 'http_code' => $http_code, 'data' => null, 'error' => 'Supabase devolvió ' . $http_code . ': ' . substr((string) $respuesta, 0, 300)];
    }

    $data = $respuesta !== '' ? json_decode($respuesta, true) : null;
    return ['ok' => true, 'http_code' => $http_code, 'data' => $data, 'error' => ''];
}

/**
 * Búsqueda híbrida (texto + similitud + RRF) vía RPC buscar_hibrido.
 * $embedding puede ser null (degrada a solo texto, sección 3.2 del diseño).
 */
function pr_supabase_buscar($consulta, ?array $embedding = null, $k = 10)
{
    $body = [
        'consulta' => $consulta,
        'consulta_emb' => $embedding,
        'k' => $k,
    ];
    return pr_supabase_request('POST', '/rest/v1/rpc/buscar_hibrido', $body);
}

/**
 * Lista fuente/ref/parte/hash de los fragmentos ya subidos de una fuente,
 * para comparar por hash e indexar solo lo que cambió (sección 5.3).
 * Devuelve un array [ "fuente|ref|parte" => hash ] o null si falló.
 */
function pr_supabase_listar_hashes($fuente)
{
    $fuente_esc = rawurlencode($fuente);
    $resultado = pr_supabase_request(
        'GET',
        "/rest/v1/punkrecords_fragmentos?select=fuente,ref,parte,hash&fuente=eq.{$fuente_esc}"
    );

    if (!$resultado['ok'] || !is_array($resultado['data'])) {
        return null;
    }

    $indice = [];
    foreach ($resultado['data'] as $fila) {
        $clave = $fila['fuente'] . '|' . $fila['ref'] . '|' . $fila['parte'];
        $indice[$clave] = $fila['hash'];
    }
    return $indice;
}

/**
 * Sube (inserta o actualiza) un lote de fragmentos con upsert por
 * (fuente, ref, parte). Cada fragmento es un array con las columnas de
 * punkrecords_fragmentos (embedding puede faltar: queda NULL en Supabase).
 */
function pr_supabase_subir_lote(array $fragmentos)
{
    if (empty($fragmentos)) {
        return ['ok' => true, 'http_code' => 0, 'data' => null, 'error' => ''];
    }
    return pr_supabase_request(
        'POST',
        '/rest/v1/punkrecords_fragmentos?on_conflict=fuente,ref,parte',
        $fragmentos,
        ['Prefer: resolution=merge-duplicates']
    );
}

/**
 * Elimina fragmentos de una fuente cuyo (ref, parte) ya no existe en el
 * origen (por ejemplo, una técnica borrada o un fragmento de una guía que
 * desapareció al recortar el texto).
 */
function pr_supabase_eliminar_obsoletos($fuente, array $refs_partes_vigentes)
{
    // Trae lo que hay en Supabase para esa fuente y borra lo que no está
    // en la lista vigente. Se hace en PHP porque PostgREST no admite un
    // "not in" cómodo con pares (ref, parte) en una sola llamada.
    $existentes = pr_supabase_listar_hashes($fuente);
    if ($existentes === null) {
        return ['ok' => false, 'error' => 'No se pudo listar lo existente para comparar.'];
    }

    $vigentes = [];
    foreach ($refs_partes_vigentes as $ref => $partes) {
        foreach ($partes as $parte) {
            $vigentes[$fuente . '|' . $ref . '|' . $parte] = true;
        }
    }

    $eliminados = 0;
    foreach (array_keys($existentes) as $clave) {
        if (isset($vigentes[$clave])) {
            continue;
        }
        [, $ref, $parte] = explode('|', $clave, 3);
        $ref_esc = rawurlencode($ref);
        $resultado = pr_supabase_request(
            'DELETE',
            "/rest/v1/punkrecords_fragmentos?fuente=eq." . rawurlencode($fuente) . "&ref=eq.{$ref_esc}&parte=eq.{$parte}"
        );
        if ($resultado['ok']) {
            $eliminados++;
        }
    }

    return ['ok' => true, 'eliminados' => $eliminados];
}
