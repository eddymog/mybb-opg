<?php
/**
 * Avisos del Ticket de Reset de Build: avisar al usuario de que el staff aplico o rechazo su reset (issue #24; 108,
 * requisitos 39 y 41). Lo usa op/staff/reset_peticiones.php (acciones 'aplicar' y 'rechazar'; 'guardar' es un borrador
 * del staff y no avisa).
 *
 * Akuma: aplicar un reset puede reescribir mybb_op_fichas.akuma. En ese caso NO se envia ademas akuma_asignada ni
 * akuma_retirada (issue #18): el mismo hecho tendria dos avisos. El aviso reset_aplicado lleva el cambio de fruta en sus
 * datos ('akuma' => asignada|retirada y 'fruta') y su texto lo menciona. Se reutilizan las funciones de lib/akuma_avisos.php
 * para decidir si la fruta cambio y para su nombre.
 *
 * Reglas: una sola llamada a opg_notificar por accion, despues de las escrituras (el modulo no usa transacciones), solo si
 * la fila del reset cambio de estado, y nunca impide la accion (try/catch; sin la libreria o sin sus tablas, no pasa nada).
 * Los datos no llevan el nombre del staff ni sus notas.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/akuma_avisos.php';

// ── Avisos (puros) ──────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Aviso del resultado de un reset: array('tipo', 'destinatario', 'datos', 'clave'), o null si no hay que avisar
 * (resultado desconocido, id o fid no validos, o el propio usuario es el actor).
 *
 * $resultado es 'aplicado' o 'rechazado'. Solo en 'aplicado' cuentan $akuma_antes y $akuma_despues (valores de la columna
 * akuma antes y despues del reset) y $nombre_fruta (nombre que se muestra, o '' para usar el valor de la columna).
 * La clave identifica el reset y su resultado: reset:ID:aplicado o reset:ID:rechazado.
 */
function opg_reset_aviso($reset_id, $fid, $resultado, $actor_uid, $akuma_antes = '', $akuma_despues = '', $nombre_fruta = '')
{
    $reset_id = (int) $reset_id;
    $fid = (int) $fid;
    if ($reset_id <= 0 || $fid <= 0 || (int) $actor_uid === $fid || !in_array($resultado, array('aplicado', 'rechazado'), true)) {
        return null;
    }
    $datos = array('fid' => $fid, 'reset_id' => $reset_id);
    if ($resultado === 'aplicado') {
        $cambio = opg_akuma_tipo_cambio($akuma_antes, $akuma_despues);
        if ($cambio !== null) {
            $datos['akuma'] = $cambio === 'akuma_asignada' ? 'asignada' : 'retirada';
            $valor = trim((string) ($cambio === 'akuma_asignada' ? $akuma_despues : $akuma_antes));
            $fruta = opg_akuma_nombre_limpio($nombre_fruta !== '' ? $nombre_fruta : $valor);
            if ($fruta !== '') {
                $datos['fruta'] = $fruta;
            }
        }
    }
    return array(
        'tipo' => 'reset_' . $resultado,
        'destinatario' => $fid,
        'datos' => $datos,
        'clave' => 'reset:' . $reset_id . ':' . $resultado,
    );
}

// ── Envio ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Avisa al dueno de la ficha $fid del resultado del reset $reset_id. Llamar despues de las escrituras de la accion y solo
 * si la fila del reset cambio de estado. Una sola llamada a opg_notificar; el actor no recibe el aviso. Nunca lanza:
 * devuelve lo que devuelve opg_notificar, o false si no hay aviso, la libreria no esta o algo falla.
 */
function opg_reset_avisar($reset_id, $fid, $resultado, $actor_uid, $akuma_antes = '', $akuma_despues = '')
{
    try {
        $aviso = opg_reset_aviso($reset_id, $fid, $resultado, $actor_uid, $akuma_antes, $akuma_despues);
        if ($aviso === null) {
            return false;
        }
        if (isset($aviso['datos']['akuma'])) {
            $nombre = opg_akuma_nombre_fruta($aviso['datos']['akuma'] === 'asignada' ? $akuma_despues : $akuma_antes);
            $aviso = opg_reset_aviso($reset_id, $fid, $resultado, $actor_uid, $akuma_antes, $akuma_despues, $nombre);
        }
        if (!function_exists('opg_notificar')) {
            $lib = __DIR__ . '/notificaciones.php';
            if (!is_file($lib)) {
                return false;
            }
            require_once $lib;
        }
        return opg_notificar($aviso['destinatario'], $aviso['tipo'], $aviso['datos'],
            array('actor' => (int) $actor_uid, 'incluir_actor' => true, 'clave' => $aviso['clave']));
    } catch (Throwable $e) {
        error_log('opg_reset_avisar: ' . $e->getMessage());
        return false;
    }
}
