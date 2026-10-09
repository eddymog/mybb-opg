<?php
/**
 * Banda sonora de la ficha: validación y normalización del enlace de YouTube.
 *
 * Debe coincidir con analizarEnlaceYoutube() de jscripts/ficha/bandaSonora.js (la misma
 * lista de pruebas la ejecutan los dos: tests/banda_sonora/).
 */

/**
 * Analiza lo que escribió el usuario. Devuelve array('ok', 'vacio', 'id', 'canonica', 'motivo').
 *
 * Acepta, con o sin https:// o http:// y con o sin www., m. o music.:
 * youtube.com/watch?v=ID (el parámetro v puede ir en cualquier posición), youtu.be/ID,
 * youtube.com/embed/ID, /v/ID, /shorts/ID, /live/ID y youtube-nocookie.com/embed/ID.
 * "canonica" es la forma que se guarda: https://www.youtube.com/watch?v=ID.
 */
function opg_banda_sonora_normalizar($texto)
{
    $resultado = function ($ok, $id, $motivo) {
        return array('ok' => $ok, 'vacio' => false, 'id' => $id, 'canonica' => $ok ? 'https://www.youtube.com/watch?v=' . $id : '', 'motivo' => $motivo);
    };

    $t = trim((string) $texto);
    $t = preg_replace('/^[<"\'\s]+|[>"\'\s]+$/u', '', $t);
    if ($t === '') {
        return array('ok' => false, 'vacio' => true, 'id' => '', 'canonica' => '', 'motivo' => 'Escribe un enlace de YouTube.');
    }
    if (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $t)) {
        $t = 'https://' . preg_replace('~^//~', '', $t);
    }
    $partes = parse_url($t);
    if ($partes === false || empty($partes['host'])) {
        return $resultado(false, '', 'No parece un enlace válido.');
    }

    $host = preg_replace('/^(www|m|music)\./', '', strtolower($partes['host']));
    $ruta = isset($partes['path']) ? $partes['path'] : '';
    $consulta = array();
    if (isset($partes['query'])) {
        parse_str($partes['query'], $consulta);
    }

    $id = '';
    if ($host === 'youtu.be') {
        $segmentos = explode('/', $ruta);
        $id = isset($segmentos[1]) ? $segmentos[1] : '';
    } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
        if (preg_match('~^/watch/?$~', $ruta)) {
            $id = isset($consulta['v']) && is_string($consulta['v']) ? $consulta['v'] : '';
        } elseif (preg_match('~^/(?:embed|v|shorts|live)/([^/]+)~', $ruta, $m)) {
            $id = $m[1];
        }
    } else {
        return $resultado(false, '', 'El enlace debe ser de YouTube (youtube.com o youtu.be).');
    }

    if ($id === '') {
        $es_lista = !empty($consulta['list']) || preg_match('~^/(playlist|channel|c|user|@)~', $ruta);
        return $resultado(false, '', $es_lista
            ? 'Ese enlace es de una lista o un canal. Usa el enlace de un video concreto.'
            : 'No se encontró el ID del video en el enlace.');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
        return $resultado(false, '', 'El ID del video no es válido (debe tener 11 letras, números, - o _).');
    }
    return $resultado(true, $id, '');
}

/**
 * Variante que recibe solo el ID del video (11 caracteres) en vez de un enlace. El navegador
 * envía solo el ID al guardar, porque algunos cortafuegos (ModSecurity) bloquean con un 406
 * cualquier parámetro que contenga un enlace con https://. Un valor vacío quita la banda sonora.
 */
function opg_banda_sonora_desde_id($id)
{
    $id = trim((string) $id);
    if ($id === '') {
        return array('ok' => false, 'vacio' => true, 'id' => '', 'canonica' => '', 'motivo' => 'Escribe un enlace de YouTube.');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
        return array('ok' => false, 'vacio' => false, 'id' => '', 'canonica' => '', 'motivo' => 'El ID del video no es válido (debe tener 11 letras, números, - o _).');
    }
    return array('ok' => true, 'vacio' => false, 'id' => $id, 'canonica' => 'https://www.youtube.com/watch?v=' . $id, 'motivo' => '');
}
