<?php
/**
 * Resolucion de peticiones administrativas y de bugs, con su aviso en el sistema global de notificaciones (issue #19;
 * 108, apartado 7, requisitos 39 y 41). Lo usan op/staff/peticiones_admin.php y op/staff/peticiones_bugs.php.
 *
 * Tabla: mybb_op_peticiones (los bugs son las filas con categoria 'programacion'). La herramienta solo registra
 * "resuelta" (resuelto = 1): no distingue aceptada, rechazada ni cerrada. Por eso el aviso lleva resultado 'resuelta'.
 *
 * Sin transacciones propias: el aviso va justo despues del UPDATE, con UNA llamada a opg_notificar, y solo si el UPDATE
 * cambio la fila. Un fallo del aviso (o las tablas de notificaciones ausentes) no impide resolver.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/notificaciones.php';

/** Categoria de mybb_op_peticiones que usan los reportes de bugs (op/staff/peticiones_bugs.php). */
const OPG_PET_CATEGORIA_BUG = 'programacion';
/** Caracteres del resumen que se guardan en el aviso. */
const OPG_PET_MAX_RESUMEN = 80;

/** Nombres de las categorias que ve el usuario (los mismos de op/peticiones.php). */
function opg_pet_categorias()
{
    return array(
        'tecnica' => 'Técnicas, Akumas y Estilos',
        'ficha' => 'Ajustes de Ficha y Recursos',
        'combate' => 'Moderación de Combate',
        'tema' => 'Petición de Narración',
        'programacion' => 'Errores de Programación',
        'otros' => 'Otras Moderaciones',
    );
}

/** Tipo de aviso segun la categoria de la fila (no segun la pagina: las dos pueden resolver cualquier id). */
function opg_pet_tipo_aviso($categoria)
{
    return (string) $categoria === OPG_PET_CATEGORIA_BUG ? 'bug_resuelto' : 'peticion_resuelta';
}

/** Clave de evento: identifica la peticion (o el bug) y su resolucion. Repetirla no duplica el aviso. */
function opg_pet_clave_aviso($tipo, $id)
{
    return $tipo === 'bug_resuelto' ? 'bug:' . (int) $id . ':resuelto' : 'peticion:' . (int) $id . ':resuelta';
}

/**
 * Datos del aviso (funcion pura). Solo lo que el autor ya ve de su propia peticion: id, nombre de la categoria, su
 * resumen (recortado) y el resultado. Nunca las notas del staff ni quien la atendio (requisitos 26 y 50).
 */
function opg_pet_datos_aviso($fila, $resultado = 'resuelta')
{
    $fila = is_array($fila) ? $fila : array();
    $categorias = opg_pet_categorias();
    $categoria = isset($fila['categoria']) ? (string) $fila['categoria'] : '';
    $resumen = isset($fila['resumen']) && is_scalar($fila['resumen']) ? trim(preg_replace('/\s+/u', ' ', (string) $fila['resumen'])) : '';
    if (function_exists('mb_strlen') && mb_strlen($resumen, 'UTF-8') > OPG_PET_MAX_RESUMEN) {
        $resumen = rtrim(mb_substr($resumen, 0, OPG_PET_MAX_RESUMEN - 1, 'UTF-8')) . '…';
    }
    $datos = array('peticion_id' => isset($fila['id']) ? (int) $fila['id'] : 0);
    if (isset($categorias[$categoria])) {
        $datos['categoria'] = $categorias[$categoria];
    }
    if ($resumen !== '') {
        $datos['resumen'] = $resumen;
    }
    $datos['resultado'] = in_array($resultado, array('resuelta', 'aceptada', 'rechazada', 'cerrada'), true) ? $resultado : 'resuelta';
    return $datos;
}

/** Envia el aviso de resolucion al autor. Nunca lanza: devuelve lo mismo que opg_notificar o false. */
function opg_pet_avisar_resuelta($fila, $actor_uid)
{
    try {
        $tipo = opg_pet_tipo_aviso(isset($fila['categoria']) ? $fila['categoria'] : '');
        return opg_notificar((int) $fila['uid'], $tipo, opg_pet_datos_aviso($fila),
            array('actor' => (int) $actor_uid, 'clave' => opg_pet_clave_aviso($tipo, $fila['id'])));
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Marca la peticion como resuelta y avisa al autor. Solo cambia (y solo avisa) si estaba pendiente: resolver otra vez
 * una ya resuelta no toca la fila ni repite el aviso. Devuelve true si la resolvio.
 */
function opg_pet_resolver($peti_id, $uid_staff, $nombre_staff)
{
    global $db;
    $peti_id = (int) $peti_id;
    $uid_staff = (int) $uid_staff;
    if ($peti_id <= 0) {
        return false;
    }
    $fila = $db->fetch_array($db->query("SELECT `id`, `uid`, `categoria`, `resumen` FROM `mybb_op_peticiones` WHERE `id`='{$peti_id}' LIMIT 1"));
    if (!$fila) {
        return false;
    }
    $db->write_query("UPDATE `mybb_op_peticiones` SET `resuelto`=1, `mod_uid`='{$uid_staff}', `mod_nombre`='" . $db->escape_string($nombre_staff)
        . "' WHERE `id`='{$peti_id}' AND `resuelto`=0");
    if ((int) $db->affected_rows() !== 1) {
        return false;
    }
    opg_pet_avisar_resuelta($fila, $uid_staff);
    return true;
}

/**
 * Borra la peticion. No avisa (requisito 41): "borrar" es limpieza interna, no una respuesta al usuario. Ninguna de las
 * dos paginas pinta un boton de borrar (las dos solo pintan "Resolver"), solo se llega armando la URL a mano, y sirve para quitar duplicados, spam o pruebas. Resolver un bug o una peticion ya avisa
 * por su lado.
 */
function opg_pet_borrar($peti_id)
{
    global $db;
    $peti_id = (int) $peti_id;
    if ($peti_id <= 0) {
        return false;
    }
    $db->write_query("DELETE FROM `mybb_op_peticiones` WHERE `id`='{$peti_id}'");
    $borrada = (int) $db->affected_rows() === 1;
    if ($borrada) {
        // Silenciosa a proposito (ver el comentario de la funcion): deja constancia de que la accion se reviso.
        opg_notificar(array(), 'peticion_resuelta', array(), array('clave' => 'peticion:' . $peti_id . ':borrada', 'silenciosa' => true));
    }
    return $borrada;
}
