<?php
/**
 * Avisos del reparto de cofres en el sistema global de notificaciones (issue #23; 108, apartado 7, requisitos 39, 40 y 41).
 * Lo usa op/staff/cofres_repartir.php.
 *
 * - Reparto: cada destinatario del lote recibe UN aviso cofres_repartidos (cantidad y nombre del cofre).
 * - Anulacion (action=reverse): cofres_revertidos solo a quien se le retiro algo del inventario. Quien ya habia abierto
 *   todos los cofres del lote no cambia (queda como caso manual del staff) y no se avisa.
 *
 * El modulo llama despues de su COMMIT, con UNA llamada por accion y todos los destinatarios. Un fallo del aviso (o las
 * tablas de notificaciones ausentes) no impide repartir ni anular. Los datos no llevan el motivo de la anulacion, el
 * staff que la hizo ni las incidencias (requisitos 26 y 50).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Caracteres del nombre del cofre que se guardan en el aviso. */
const OPG_COF_MAX_NOMBRE = 80;

/** Clave de evento: identifica el lote y el hecho. Repetirla no duplica el aviso. */
function opg_cof_clave_aviso($tipo, $reparto_id)
{
    return 'cofres:' . (int) $reparto_id . ($tipo === 'cofres_revertidos' ? ':revertidos' : ':repartidos');
}

/**
 * Datos del aviso (funcion pura): lote, cantidad entregada por personaje y nombre visible del cofre (el mismo del
 * inventario). Sin el id interno del objeto, el motivo ni el staff.
 */
function opg_cof_datos_aviso($reparto_id, $cantidad, $nombre_cofre)
{
    $nombre = is_scalar($nombre_cofre) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $nombre_cofre))) : '';
    if (function_exists('mb_strlen') && mb_strlen($nombre, 'UTF-8') > OPG_COF_MAX_NOMBRE) {
        $nombre = rtrim(mb_substr($nombre, 0, OPG_COF_MAX_NOMBRE - 1, 'UTF-8')) . '…';
    }
    $datos = array('reparto_id' => max(0, (int) $reparto_id), 'cantidad' => max(0, (int) $cantidad));
    if ($nombre !== '') {
        $datos['cofre'] = $nombre;
    }
    return $datos;
}

/** Nombre del cofre en mybb_op_objetos, o '' si no esta o falla la consulta. */
function opg_cof_nombre_cofre($cofre_id)
{
    global $db;
    try {
        $filas = opg_notif_filas('SELECT nombre FROM ' . opg_notif_t('op_objetos') . " WHERE objeto_id = '"
            . $db->escape_string((string) $cofre_id) . "' LIMIT 1");
        return $filas ? (string) $filas[0]['nombre'] : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Envia el aviso del lote a todos sus destinatarios en UNA llamada. $tipo es cofres_repartidos o cofres_revertidos.
 * Nunca lanza: devuelve lo mismo que opg_notificar, o false si el sistema no esta instalado o algo falla.
 */
function opg_cof_avisar($tipo, $uids, $reparto_id, $cofre_id, $cantidad, $actor_uid)
{
    try {
        if (!in_array($tipo, array('cofres_repartidos', 'cofres_revertidos'), true) || !$uids) {
            return false;
        }
        if (!function_exists('opg_notificar') && is_file(__DIR__ . '/notificaciones.php')) {
            require_once __DIR__ . '/notificaciones.php';
        }
        if (!function_exists('opg_notificar') || !opg_notif_tablas_listas()) {
            return false;
        }
        return opg_notificar($uids, $tipo, opg_cof_datos_aviso($reparto_id, $cantidad, opg_cof_nombre_cofre($cofre_id)),
            array('actor' => (int) $actor_uid, 'clave' => opg_cof_clave_aviso($tipo, $reparto_id)));
    } catch (Throwable $e) {
        return false;
    }
}
