<?php
/**
 * Fichas en cola: aprobar o rechazar (borrar) un personaje nuevo, y avisar a su dueno (issue #17; 108, apartado 7;
 * 208, apartado 9).
 *
 * La pagina op/staff/fichas_en_cola.php comprueba permisos y CSRF, resuelve el grupo de la faccion y llama a
 * opg_fichas_cola_aprobar() o a opg_fichas_cola_rechazar(). Aqui estan:
 *  - Los avisos (funciones puras, con prueba en tests/notificaciones/fichas_cola.php): tipo, datos y clave de evento.
 *  - Las acciones con base de datos y el envio del aviso (prueba con MySQL en tests/notificaciones/fichas_cola_db.php).
 *
 * Reglas del aviso (requisitos 39 y 40, criterio 11 de 108):
 *  - Solo se avisa si la accion cambio algo: aprobar una ficha que seguia sin aprobar, o borrar una que seguia sin aprobar.
 *  - Una sola llamada a opg_notificar por accion, despues de la escritura (aqui no hay transacciones propias).
 *  - Un fallo al avisar nunca impide la accion: el envio va en try/catch y, si la libreria o sus tablas no estan, la
 *    accion funciona igual que antes.
 *  - El actor (el staff) no recibe el aviso de su propia accion (comportamiento por defecto de opg_notificar).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// ── Avisos (puros) ──────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Aviso ficha_aprobada: array('tipo', 'destinatario', 'datos', 'clave'), o null si el fid no es valido.
 * Clave ficha:FID:aprobada (una ficha solo se aprueba una vez: un reintento no duplica).
 */
function opg_fichas_cola_aviso_aprobada($fid, $nombre)
{
    $fid = (int) $fid;
    if ($fid <= 0) {
        return null;
    }
    return array(
        'tipo' => 'ficha_aprobada',
        'destinatario' => $fid,
        'datos' => opg_fichas_cola_datos($fid, $nombre),
        'clave' => 'ficha:' . $fid . ':aprobada',
    );
}

/**
 * Aviso ficha_rechazada, con el nombre leido ANTES de borrar la ficha. La clave lleva la hora de creacion de la ficha
 * borrada (ficha:FID:rechazada:UNIX), de modo que dos rechazos de fichas distintas se avisan y un reintento no duplica.
 * Sin hora de creacion valida no hay forma de distinguir el rechazo: devuelve null y no se avisa.
 */
function opg_fichas_cola_aviso_rechazada($fid, $nombre, $creada)
{
    $fid = (int) $fid;
    $creada = is_numeric($creada) ? (int) $creada : 0;
    if ($fid <= 0 || $creada <= 0) {
        return null;
    }
    return array(
        'tipo' => 'ficha_rechazada',
        'destinatario' => $fid,
        'datos' => opg_fichas_cola_datos($fid, $nombre),
        'clave' => 'ficha:' . $fid . ':rechazada:' . $creada,
    );
}

/** Datos que guarda el aviso: el fid (para el enlace) y el nombre del personaje, en texto plano y recortado. */
function opg_fichas_cola_datos($fid, $nombre)
{
    $datos = array('fid' => (int) $fid);
    $nombre = is_scalar($nombre) ? trim(strip_tags((string) $nombre)) : '';
    if ($nombre !== '') {
        $datos['nombre'] = function_exists('mb_substr') ? mb_substr($nombre, 0, 100, 'UTF-8') : substr($nombre, 0, 100);
    }
    return $datos;
}

// ── Envio ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Envia un aviso de esta libreria con UNA llamada a opg_notificar. Devuelve lo que devuelve opg_notificar, o false si
 * no hay aviso, la libreria no esta o algo falla. Nunca lanza excepciones.
 */
function opg_fichas_cola_avisar($aviso, $actor_uid)
{
    if (!is_array($aviso)) {
        return false;
    }
    try {
        if (!function_exists('opg_notificar')) {
            $lib = __DIR__ . '/notificaciones.php';
            if (!is_file($lib)) {
                return false;
            }
            require_once $lib;
        }
        return opg_notificar($aviso['destinatario'], $aviso['tipo'], $aviso['datos'],
            array('actor' => (int) $actor_uid, 'clave' => $aviso['clave']));
    } catch (Throwable $e) {
        error_log('opg_fichas_cola_avisar(' . $aviso['tipo'] . '): ' . $e->getMessage());
        return false;
    }
}

// ── Acciones ────────────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Aprueba la ficha $fid si seguia sin aprobar, le pone el grupo $usergroup a la cuenta y avisa al dueno.
 * Devuelve true si la ficha se aprobo ahora (false si no existia o ya estaba aprobada: entonces no se toca nada).
 */
function opg_fichas_cola_aprobar($fid, $usergroup, $staff_uid, $staff_nombre)
{
    global $db;
    $fid = (int) $fid;
    if ($fid <= 0) {
        return false;
    }
    $db->write_query("UPDATE `mybb_op_fichas` SET `aprobada_por`='" . $db->escape_string($staff_nombre) . "' WHERE `aprobada_por`='sin_aprobar' AND `fid`='{$fid}'");
    if ((int) $db->affected_rows() <= 0) {
        return false;
    }
    $db->write_query("UPDATE `mybb_users` SET `usergroup`='" . (int) $usergroup . "' WHERE `uid`='{$fid}'");

    $nombre = $db->fetch_field($db->write_query("SELECT `nombre` FROM `mybb_op_fichas` WHERE `fid`='{$fid}'"), 'nombre');
    opg_fichas_cola_avisar(opg_fichas_cola_aviso_aprobada($fid, $nombre), $staff_uid);
    return true;
}

/**
 * Rechaza (borra) la ficha $fid si seguia sin aprobar, junto con sus datos asociados, y avisa al dueno.
 * El nombre y la hora de creacion se leen antes de borrar. Los datos asociados solo se borran si la cuenta ya no tiene
 * ficha (asi un reintento termina la limpieza, y una ficha aprobada nunca pierde su inventario, tecnicas ni virtudes).
 * Devuelve true si la ficha se borro ahora.
 */
function opg_fichas_cola_rechazar($fid, $staff_uid)
{
    global $db;
    $fid = (int) $fid;
    if ($fid <= 0) {
        return false;
    }
    $ficha = $db->fetch_array($db->write_query("SELECT `nombre`, UNIX_TIMESTAMP(`tiempo_creacion`) AS creada FROM `mybb_op_fichas` WHERE `aprobada_por`='sin_aprobar' AND `fid`='{$fid}'"));

    $db->write_query("DELETE FROM `mybb_op_fichas` WHERE `aprobada_por`='sin_aprobar' AND `fid`='{$fid}'");
    $borrada = (int) $db->affected_rows() > 0;

    $queda = (int) $db->fetch_field($db->write_query("SELECT COUNT(*) AS n FROM `mybb_op_fichas` WHERE `fid`='{$fid}'"), 'n');
    if ($queda === 0) {
        $db->write_query("DELETE FROM `mybb_op_fichas_secret` WHERE `fid`='{$fid}'");
        $db->write_query("DELETE FROM `mybb_op_inventario` WHERE `uid`='{$fid}'");
        $db->write_query("DELETE FROM `mybb_op_tec_aprendidas` WHERE `uid`='{$fid}'");
        $db->write_query("DELETE FROM `mybb_op_virtudes_usuarios` WHERE `uid`='{$fid}'");
    }

    if ($borrada && $ficha) {
        opg_fichas_cola_avisar(opg_fichas_cola_aviso_rechazada($fid, $ficha['nombre'], $ficha['creada']), $staff_uid);
    }
    return $borrada;
}
