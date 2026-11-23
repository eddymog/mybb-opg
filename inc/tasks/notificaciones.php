<?php
/**
 * Tarea diaria del sistema global de notificaciones: retencion y reconciliacion de contadores (issue #14).
 *
 * Documentos: docs/108_Requirements_Notificaciones.md (requisitos 28, 29 y 33) y docs/208_DesignPlan_Notificaciones.md
 * (apartado 10). Se registra en mybb_tasks con docs/notificaciones_tarea_migration.sql.
 *
 * Pasos, en este orden:
 *   1. Borra las notificaciones con mas de OPG_NOTIF_RETENCION_DIAS dias, leidas o no, en lotes de OPG_NOTIF_TAREA_LOTE
 *      filas por clave primaria (sin bloquear la tabla).
 *   2. Deja a cada usuario con OPG_NOTIF_MAX_POR_USUARIO filas como mucho: borra primero las leidas mas antiguas y, si no
 *      basta, las no leidas mas antiguas.
 *   3. Reconcilia el contador de los usuarios afectados por los pasos 1 y 2.
 *   4. Reconcilia los contadores sin recontar desde hace mas de 24 horas (o sin fila) de los usuarios con actividad
 *      reciente: los que recibieron una notificacion en los ultimos OPG_NOTIF_TAREA_ACTIVIDAD_DIAS dias.
 *
 * Nunca se ejecuta durante una peticion de un usuario: este archivo solo lo carga el planificador de tareas de MyBB
 * (run_task de inc/functions_task.php, desde task.php o el cron), y ninguna pagina, plugin ni libreria lo incluye. Cada paso tiene
 * un tope por ejecucion (constantes de abajo) para que una ejecucion no se alargue; lo que quede se hace al dia siguiente.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

/** Dias que se conserva una notificacion, leida o no (requisito 28). */
const OPG_NOTIF_RETENCION_DIAS = 90;
/** Filas maximas por usuario (requisito 28). */
const OPG_NOTIF_MAX_POR_USUARIO = 500;
/** Filas por sentencia de borrado. */
const OPG_NOTIF_TAREA_LOTE = 1000;
/** Lotes de borrado por antiguedad en una ejecucion (tope: LOTE x MAX_LOTES filas al dia). */
const OPG_NOTIF_TAREA_MAX_LOTES = 500;
/** Usuarios por encima del maximo que se recortan en una ejecucion. */
const OPG_NOTIF_TAREA_MAX_USUARIOS = 2000;
/** Dias de "actividad reciente" para el paso 4. */
const OPG_NOTIF_TAREA_ACTIVIDAD_DIAS = 7;
/** Contadores que reconcilia el paso 4 en una ejecucion. */
const OPG_NOTIF_TAREA_MAX_RECONCILIAR = 5000;

/** Lista de enteros para un IN (...). */
function opg_notif_tarea_in($ids)
{
    return implode(',', array_map('intval', $ids));
}

/**
 * Paso 1. Borra por lotes las notificaciones creadas antes de $corte. Devuelve array(filas borradas, uids afectados).
 * Cada lote lee hasta LOTE ids por el indice idx_created y los borra por clave primaria (repitiendo la condicion de fecha,
 * por si una agrupable recibio actividad nueva entre la lectura y el borrado).
 */
function opg_notif_tarea_borrar_antiguas($corte)
{
    global $db;
    $N = opg_notif_t('op_notificaciones');
    $borradas = 0;
    $uids = array();
    for ($lote = 0; $lote < OPG_NOTIF_TAREA_MAX_LOTES; $lote++) {
        $filas = opg_notif_filas('SELECT id, uid FROM ' . $N . ' WHERE created_at < ' . (int) $corte
            . ' ORDER BY created_at, id LIMIT ' . OPG_NOTIF_TAREA_LOTE);
        if (!$filas) {
            break;
        }
        $ids = array();
        foreach ($filas as $f) {
            $ids[] = (int) $f['id'];
            $uids[(int) $f['uid']] = true;
        }
        opg_notif_w('DELETE FROM ' . $N . ' WHERE id IN (' . opg_notif_tarea_in($ids) . ') AND created_at < ' . (int) $corte);
        $borradas += max(0, (int) $db->affected_rows());
        if (count($filas) < OPG_NOTIF_TAREA_LOTE) {
            break;
        }
    }
    return array($borradas, array_keys($uids));
}

/**
 * Paso 2. Recorta a $maximo filas a cada usuario que lo supera: las leidas mas antiguas primero y despues las no leidas mas
 * antiguas. Devuelve array(filas borradas, uids afectados).
 */
function opg_notif_tarea_recortar_usuarios($maximo)
{
    global $db;
    $N = opg_notif_t('op_notificaciones');
    $maximo = max(1, (int) $maximo);
    $excedidos = opg_notif_filas('SELECT uid, COUNT(*) AS n FROM ' . $N . ' GROUP BY uid HAVING COUNT(*) > ' . $maximo
        . ' LIMIT ' . OPG_NOTIF_TAREA_MAX_USUARIOS);
    $borradas = 0;
    $uids = array();
    foreach ($excedidos as $e) {
        $uid = (int) $e['uid'];
        $sobran = (int) $e['n'] - $maximo;
        $uids[] = $uid;
        while ($sobran > 0) {
            $filas = opg_notif_filas('SELECT id FROM ' . $N . ' WHERE uid = ' . $uid
                . ' ORDER BY (leida_en IS NULL), created_at, id LIMIT ' . min($sobran, OPG_NOTIF_TAREA_LOTE));
            if (!$filas) {
                break;
            }
            $ids = array();
            foreach ($filas as $f) {
                $ids[] = (int) $f['id'];
            }
            opg_notif_w('DELETE FROM ' . $N . ' WHERE uid = ' . $uid . ' AND id IN (' . opg_notif_tarea_in($ids) . ')');
            $n = max(0, (int) $db->affected_rows());
            $borradas += $n;
            if ($n === 0) {
                break;
            }
            $sobran -= $n;
        }
    }
    return array($borradas, $uids);
}

/**
 * Paso 4. Uids con actividad reciente (una notificacion creada desde $desde) cuyo contador falta o se reconcilio antes de
 * $viejo. Los reconciliados en el paso 3 ya tienen actualizado = ahora y no salen.
 */
function opg_notif_tarea_contadores_viejos($desde, $viejo)
{
    $uids = array();
    foreach (opg_notif_filas('SELECT DISTINCT n.uid FROM ' . opg_notif_t('op_notificaciones') . ' n LEFT JOIN '
        . opg_notif_t('op_notificaciones_contador') . ' c ON c.uid = n.uid WHERE n.created_at >= ' . (int) $desde
        . ' AND (c.uid IS NULL OR c.actualizado < ' . (int) $viejo . ') LIMIT ' . OPG_NOTIF_TAREA_MAX_RECONCILIAR) as $f) {
        $uids[] = (int) $f['uid'];
    }
    return $uids;
}

/** Reconcilia cada uid de la lista y suma el resultado al resumen $r. */
function opg_notif_tarea_reconciliar($uids, &$r)
{
    foreach ($uids as $uid) {
        if (opg_notif_reconciliar($uid) === false) {
            $r['fallos']++;
        } else {
            $r['reconciliados']++;
        }
        opg_notif_contar_olvidar($uid);
    }
}

/**
 * Ejecuta los cuatro pasos. Devuelve un resumen (array con 'antiguas', 'recortadas', 'usuarios_recortados',
 * 'reconciliados', 'fallos' y 'error'). No lanza excepciones: un error de MySQL detiene la ejecucion, se registra y queda
 * en 'error'; lo hecho hasta ese momento queda hecho (cada borrado es una sentencia autocommit).
 */
function opg_notif_tarea_ejecutar($ahora = null)
{
    $ahora = $ahora === null ? opg_notif_ahora() : (int) $ahora;
    $r = array('antiguas' => 0, 'recortadas' => 0, 'usuarios_recortados' => 0, 'reconciliados' => 0, 'fallos' => 0, 'error' => '');
    if (!opg_notif_tablas_listas(true)) {
        $r['error'] = 'faltan las tablas de notificaciones';
        return $r;
    }
    $afectados = array();
    try {
        list($r['antiguas'], $uids) = opg_notif_tarea_borrar_antiguas($ahora - OPG_NOTIF_RETENCION_DIAS * 86400);
        foreach ($uids as $u) {
            $afectados[$u] = true;
        }
        list($r['recortadas'], $uids) = opg_notif_tarea_recortar_usuarios(OPG_NOTIF_MAX_POR_USUARIO);
        $r['usuarios_recortados'] = count($uids);
        foreach ($uids as $u) {
            $afectados[$u] = true;
        }
    } catch (Throwable $e) {
        $r['error'] = opg_notif_mensaje_error($e);
        opg_notif_log('tarea de retencion: ' . $r['error']);
    }
    // Paso 3: los afectados se reconcilian siempre, aunque un paso anterior fallara a medias.
    opg_notif_tarea_reconciliar(array_keys($afectados), $r);
    if ($r['error'] === '') {
        try {
            opg_notif_tarea_reconciliar(opg_notif_tarea_contadores_viejos($ahora - OPG_NOTIF_TAREA_ACTIVIDAD_DIAS * 86400,
                $ahora - OPG_NOTIF_RECONCILIAR_CADA), $r);
        } catch (Throwable $e) {
            $r['error'] = opg_notif_mensaje_error($e);
            opg_notif_log('tarea de retencion (contadores): ' . $r['error']);
        }
    }
    return $r;
}

/** Punto de entrada del planificador de MyBB (columna file = 'notificaciones' en mybb_tasks). */
function task_notificaciones($task)
{
    $r = opg_notif_tarea_ejecutar();
    if (function_exists('add_task_log')) {
        add_task_log($task, 'Notificaciones: ' . $r['antiguas'] . ' borradas por antiguedad, ' . $r['recortadas'] . ' por exceso ('
            . $r['usuarios_recortados'] . ' usuarios), ' . $r['reconciliados'] . ' contadores reconciliados'
            . ($r['fallos'] ? ', ' . $r['fallos'] . ' fallidos' : '') . ($r['error'] !== '' ? '. Error: ' . $r['error'] : '') . '.');
    }
}
