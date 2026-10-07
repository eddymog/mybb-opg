<?php
/** Utilidad de pruebas: llama a una página con el harness y devuelve array(estado, cabeceras, cuerpo). */
function llamar($pagina, $get, $post, $uid, $htmx = true, $post_key = 'clave-valida', $metodo = null)
{
    $server = array();
    if ($htmx) { $server['HTTP_HX_REQUEST'] = 'true'; }
    if ($post_key !== null) { $server['HTTP_X_POST_KEY'] = $post_key; }
    $json = json_encode(array('pagina' => $pagina, 'get' => $get, 'post' => $post, 'uid' => $uid, 'server' => $server,
        'metodo' => $metodo ?: ($post ? 'POST' : 'GET'), 'roles' => $GLOBALS['roles']));
    $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/harness_pagina.php') . ' ' . escapeshellarg($json) . ' 2>&1';
    $salida = shell_exec($cmd);
    $r = json_decode((string) $salida, true);
    if (!is_array($r)) {
        return array('estado' => -1, 'cabeceras' => array(), 'cuerpo' => (string) $salida);
    }
    return array('estado' => $r['estado'], 'cabeceras' => $r['cabeceras'], 'cuerpo' => $r['cuerpo']);
}
function cabecera($r, $nombre)
{
    foreach ($r['cabeceras'] as $h) {
        if (stripos($h, $nombre . ':') === 0) { return trim(substr($h, strlen($nombre) + 1)); }
    }
    return null;
}
/** HTML mal formado o con ids repetidos en cada respuesta (por separado: los fragmentos pueden repetir ids entre sí). */
function problemas_html($respuestas)
{
    $problemas = array();
    foreach ($respuestas as $i => $html) {
        if (trim($html) === '') { continue; }
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        foreach (libxml_get_errors() as $e) {
            if (strpos($e->message, 'Tag ') === 0 && strpos($e->message, 'invalid') !== false) { continue; }   // etiquetas HTML5 que libxml no conoce
            if (strpos($e->message, 'htmlParseEntityRef') !== false) { continue; }
            $problemas[] = "respuesta $i: " . trim($e->message);
        }
        libxml_clear_errors();
        $ids = array();
        foreach ((new DOMXPath($doc))->query('//*[@id]') as $n) {
            $id = $n->getAttribute('id');
            if (isset($ids[$id])) { $problemas[] = "respuesta $i: id repetido '$id'"; }
            $ids[$id] = true;
        }
    }
    return $problemas;
}
