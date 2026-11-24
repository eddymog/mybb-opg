<?php
/**
 * Aviso de intercambio recibido en el sistema global de notificaciones (issue #25; 108, requisitos 39 y 41).
 * Lo usa op/intercambio.php.
 *
 * El intercambio se ejecuta de inmediato (no hay oferta ni aceptacion). Cuando queda registrado en mybb_op_intercambios,
 * el receptor (r_uid) recibe UN aviso intercambio_recibido con el nombre del emisor, los Berries y los objetos que
 * realmente recibio, y el tema (tid). El emisor no se avisa (es el actor). Si no se transfirio nada, no hay aviso.
 *
 * El modulo no usa transacciones: se llama despues de la ultima escritura del intercambio, con una sola llamada. Un fallo
 * del aviso (o las tablas de notificaciones ausentes) no impide completar el intercambio. Los datos no llevan HTML, la
 * razon del intercambio, las facciones ni los ids internos de los objetos.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Caracteres maximos del nombre del emisor y de la lista de objetos que se guardan en el aviso. */
const OPG_INT_MAX_NOMBRE = 80;
const OPG_INT_MAX_OBJETOS = 200;

/** Clave de evento: identifica el registro del intercambio. Repetirla no duplica el aviso. */
function opg_int_clave_aviso($intercambio_id)
{
    return 'intercambio:' . (int) $intercambio_id;
}

/** Texto plano de una sola linea, sin etiquetas, recortado a $max caracteres. */
function opg_int_limpiar($texto, $max)
{
    $texto = is_scalar($texto) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $texto))) : '';
    if (function_exists('mb_strlen') && mb_strlen($texto, 'UTF-8') > $max) {
        $texto = rtrim(mb_substr($texto, 0, $max - 1, 'UTF-8')) . '…';
    }
    return $texto;
}

/**
 * Datos del aviso (funcion pura), o null si el intercambio no incluye Berries ni objetos.
 *
 * $berries  Berries que el receptor recibio de verdad (0 si no hubo transferencia de Berries).
 * $objetos  nombres visibles de los objetos recibidos, uno por unidad (los repetidos se agrupan: "Espada x2").
 *
 * Devuelve intercambio_id, actor (nombre del emisor), berries (si > 0), objetos y n_objetos (si los hay) y tid (si > 0).
 */
function opg_int_datos_aviso($intercambio_id, $emisor, $berries, $objetos, $tid)
{
    $berries = is_numeric($berries) ? max(0, (int) $berries) : 0;

    $conteo = array();
    foreach (is_array($objetos) ? $objetos : array() as $obj) {
        $nombre = opg_int_limpiar($obj, OPG_INT_MAX_NOMBRE);
        if ($nombre !== '') {
            $conteo[$nombre] = isset($conteo[$nombre]) ? $conteo[$nombre] + 1 : 1;
        }
    }
    if ($berries === 0 && !$conteo) {
        return null;
    }

    $datos = array('intercambio_id' => max(0, (int) $intercambio_id));
    $actor = opg_int_limpiar($emisor, OPG_INT_MAX_NOMBRE);
    if ($actor !== '') {
        $datos['actor'] = $actor;
    }
    if ($berries > 0) {
        $datos['berries'] = $berries;
    }
    if ($conteo) {
        $partes = array();
        foreach ($conteo as $nombre => $n) {
            $partes[] = $n > 1 ? $nombre . ' x' . $n : $nombre;
        }
        $datos['objetos'] = opg_int_limpiar(implode(', ', $partes), OPG_INT_MAX_OBJETOS);
        $datos['n_objetos'] = array_sum($conteo);
    }
    $tid = (int) $tid;
    if ($tid > 0) {
        $datos['tid'] = $tid;
    }
    return $datos;
}

/**
 * Avisa al receptor de un intercambio ya registrado. Una sola llamada a opg_notificar; el emisor es el actor.
 * Nunca lanza: devuelve lo mismo que opg_notificar, o false si no hay nada que avisar, el sistema no esta instalado
 * o algo falla.
 */
function opg_int_avisar($r_uid, $intercambio_id, $emisor_uid, $emisor_nombre, $berries, $objetos, $tid)
{
    try {
        $r_uid = (int) $r_uid;
        $intercambio_id = (int) $intercambio_id;
        if ($r_uid <= 0 || $intercambio_id <= 0) {
            return false;
        }
        $datos = opg_int_datos_aviso($intercambio_id, $emisor_nombre, $berries, $objetos, $tid);
        if ($datos === null) {
            return false;
        }
        if (!function_exists('opg_notificar') && is_file(__DIR__ . '/notificaciones.php')) {
            require_once __DIR__ . '/notificaciones.php';
        }
        if (!function_exists('opg_notificar') || !opg_notif_tablas_listas()) {
            return false;
        }
        return opg_notificar($r_uid, 'intercambio_recibido', $datos,
            array('actor' => (int) $emisor_uid, 'clave' => opg_int_clave_aviso($intercambio_id)));
    } catch (Throwable $e) {
        return false;
    }
}
