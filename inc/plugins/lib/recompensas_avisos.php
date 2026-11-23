<?php
/**
 * Avisos de recompensas y salarios entregados por el staff (sistema global de notificaciones, issue #20).
 *
 * Tipos del catalogo (lib/notificaciones_catalogo.php):
 *   recompensa_entregada  op/staff/entregar_recompensas.php, entregar_recompensas_aventuras.php,
 *                         entregar_recompensas_narradores.php y berries_masivo.php.
 *   salario_recibido      lib/salario_faccion.php (boton del staff y tarea semanal) y op/staff/salario_staff.php.
 * Las rutas del Tablon (recompensas_entregadas) no pasan por aqui: ya avisan por su cuenta.
 *
 * Reglas comunes: se avisa DESPUES de que la accion guardo el cambio (tras el COMMIT si hay transaccion), solo a quien
 * realmente cobro, y un fallo del aviso nunca impide la accion (opg_rec_avisar no lanza nada). Sin las tablas de
 * notificaciones el modulo funciona como antes.
 *
 * Las funciones de datos y claves son puras (sin base de datos): las prueba tests/notificaciones/recompensas.php.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Importes que admite el resumen, en el orden en que se muestran. */
function opg_rec_conceptos_importe()
{
    return array('xp', 'nikas', 'berries', 'kuros', 'reputacion');
}

/** Monedas de un salario. */
function opg_rec_monedas()
{
    return array('Berries', 'Kuros');
}

/** Numero positivo y finito (entero si no tiene decimales), o null si es cero, negativo o no es un numero. */
function opg_rec_importe($valor)
{
    if (is_string($valor)) {
        $valor = trim($valor);
    }
    if (!is_numeric($valor)) {
        return null;
    }
    $num = (float) $valor;
    if (!is_finite($num) || $num <= 0) {
        return null;
    }
    $redondo = round($num, 2);
    return $redondo == floor($redondo) && $redondo < PHP_INT_MAX ? (int) $redondo : $redondo;
}

/**
 * Datos del aviso recompensa_entregada: solo los importes mayores que cero (xp, nikas, berries, kuros, reputacion), el
 * concepto (por ejemplo "Aventura T3"), el tid del tema (sin titulo: requisito 50) y una descripcion corta de los
 * objetos. Devuelve null si no hay nada que avisar.
 */
function opg_rec_datos_recompensa($importes, $concepto = '', $tid = 0, $objetos = '')
{
    $datos = array();
    $importes = is_array($importes) ? $importes : array();
    foreach (opg_rec_conceptos_importe() as $clave) {
        if (isset($importes[$clave])) {
            $valor = opg_rec_importe($importes[$clave]);
            if ($valor !== null) {
                $datos[$clave] = $valor;
            }
        }
    }
    $objetos = trim((string) $objetos);
    if (!$datos && $objetos === '') {
        return null;
    }
    if ($objetos !== '') {
        $datos['objetos'] = $objetos;
    }
    $concepto = trim((string) $concepto);
    if ($concepto !== '') {
        $datos['concepto'] = $concepto;
    }
    if ((int) $tid > 0) {
        $datos['tid'] = (int) $tid;
    }
    return $datos;
}

/** Datos del aviso salario_recibido, o null si el importe no es positivo o la moneda no es Berries ni Kuros. */
function opg_rec_datos_salario($monto, $moneda, $concepto = '')
{
    $monto = opg_rec_importe($monto);
    if ($monto === null || !in_array($moneda, opg_rec_monedas(), true)) {
        return null;
    }
    $datos = array('monto' => $monto, 'moneda' => $moneda);
    $concepto = trim((string) $concepto);
    if ($concepto !== '') {
        $datos['concepto'] = $concepto;
    }
    return $datos;
}

/**
 * Agrupa los pagos de un lote por importe y concepto: cada grupo comparte los mismos datos y se avisa con una sola
 * llamada. $pagos es una lista de array('uid' => , 'monto' => , 'concepto' => ). Devuelve una lista de
 * array('monto' => , 'concepto' => , 'uids' => array(...)) en el orden en que aparece cada grupo; descarta los uid no
 * validos y los importes no positivos.
 */
function opg_rec_agrupar_pagos($pagos)
{
    $grupos = array();
    foreach (is_array($pagos) ? $pagos : array() as $p) {
        $uid = isset($p['uid']) ? (int) $p['uid'] : 0;
        $monto = isset($p['monto']) ? opg_rec_importe($p['monto']) : null;
        if ($uid <= 0 || $monto === null) {
            continue;
        }
        $concepto = isset($p['concepto']) ? trim((string) $p['concepto']) : '';
        $clave = $monto . '|' . $concepto;
        if (!isset($grupos[$clave])) {
            $grupos[$clave] = array('monto' => $monto, 'concepto' => $concepto, 'uids' => array());
        }
        $grupos[$clave]['uids'][$uid] = $uid;
    }
    $lista = array();
    foreach ($grupos as $g) {
        $g['uids'] = array_values($g['uids']);
        $lista[] = $g;
    }
    return $lista;
}

// ── Claves de evento (identifican el hecho concreto: repetir la llamada no duplica el aviso) ──────────────────────────

/**
 * Entrega de recompensa de una herramienta de una sola ficha: el id de su fila de auditoria. Sin ese id (el INSERT de
 * auditoria no devolvio nada) se usa la ficha, el tema, el tramo y la hora.
 */
function opg_rec_clave_entrega($origen, $audit_id, $fid, $tid, $tramo, $ahora)
{
    $origen = preg_replace('/[^a-z_]/', '', (string) $origen);
    if ((int) $audit_id > 0) {
        return 'recompensa:' . $origen . ':' . (int) $audit_id;
    }
    return 'recompensa:' . $origen . ':' . (int) $fid . ':' . (int) $tid . ':'
        . preg_replace('/[^A-Za-z0-9_]/', '', (string) $tramo) . ':' . (int) $ahora;
}

/** Lote de berries_masivo: quien lo ejecuto y cuando (cada ejecucion paga de nuevo y es un lote distinto). */
function opg_rec_clave_berries_masivo($actor, $ahora)
{
    return 'berries_masivo:' . (int) $actor . ':' . (int) $ahora;
}

/** Salario de faccion: la semana del reparto (solo hay un reparto por semana, ver opg_sf_repartir). */
function opg_rec_clave_salario_faccion($semana)
{
    return 'salario_faccion:' . preg_replace('/[^0-9-]/', '', (string) $semana);
}

/**
 * Salario de staff: el id del primer pago del lote en mybb_op_salario_staff_pagos (en un pago por UID, su propio id).
 * Cada destinatario cobra una sola vez por lote, asi que (uid, clave) identifica su pago real.
 */
function opg_rec_clave_salario_staff($primer_pago_id)
{
    return 'salario_staff:' . (int) $primer_pago_id;
}

// ── Envio ─────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** ¿Esta disponible el sistema global (libreria cargada y tablas creadas)? Nunca lanza. */
function opg_rec_notif_disponible()
{
    try {
        if (!function_exists('opg_notificar') && defined('MYBB_ROOT')
            && is_file(MYBB_ROOT . 'inc/plugins/lib/notificaciones.php')) {
            require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
        }
        return function_exists('opg_notif_tablas_listas') && opg_notif_tablas_listas();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Envia un aviso con UNA llamada a opg_notificar para todos los destinatarios. El actor (quien entrega) no recibe su
 * propio aviso (comportamiento por defecto). Devuelve lo que devuelve opg_notificar, o false si no se pudo; nunca lanza.
 */
function opg_rec_avisar($destinatarios, $tipo, $datos, $clave, $actor)
{
    if (!$datos || !$destinatarios) {
        return false;
    }
    try {
        if (!opg_rec_notif_disponible()) {
            return false;
        }
        return opg_notificar($destinatarios, $tipo, $datos, array(
            'actor' => (int) $actor,
            'clave' => $clave,
            'url'   => 'op/personaje.php',
        ));
    } catch (Throwable $e) {
        if (function_exists('opg_notif_log')) {
            opg_notif_log('aviso ' . $tipo . ' no enviado: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Avisa los pagos de un salario agrupados por importe y concepto (una llamada por grupo, porque los datos del texto
 * cambian con el importe). Devuelve cuantos avisos nuevos se crearon. Nunca lanza.
 */
function opg_rec_avisar_salarios($pagos, $moneda, $clave, $actor)
{
    $creados = 0;
    try {
        foreach (opg_rec_agrupar_pagos($pagos) as $grupo) {
            $datos = opg_rec_datos_salario($grupo['monto'], $moneda, $grupo['concepto']);
            $n = opg_rec_avisar($grupo['uids'], 'salario_recibido', $datos, $clave, $actor);
            $creados += is_int($n) ? $n : 0;
        }
    } catch (Throwable $e) {
        // Nunca debe impedir el pago ya confirmado.
    }
    return $creados;
}
