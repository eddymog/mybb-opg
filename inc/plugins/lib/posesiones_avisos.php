<?php
/**
 * Avisos de cambios del staff en lo que el personaje tiene: tecnicas, virtudes/defectos y objetos (issue #21; 108,
 * apartado 7, requisitos 39, 40 y 41). Lo usan op/staff/tecnicas_ficha.php, tecnicas_aprender.php, virtudes_ficha.php,
 * objetos_ficha.php y tecnicas_bulk_update.php.
 *
 * Tipos: tecnica_cambio, virtud_cambio y objeto_cambio. Datos: accion (otorgada, modificada o retirada), cantidad, y
 * nombre (un solo elemento) o nombres (resumen de hasta OPG_POS_MAX_NOMBRES nombres). En tecnica_cambio, lista =
 * 'por_aprender' cuando el cambio es en las tecnicas por aprender. Nunca la razon del staff ni quien hizo el cambio.
 *
 * Las paginas no usan transacciones: el aviso va despues de la escritura, con UNA llamada a opg_notificar por accion, y
 * solo si la escritura cambio algo (affected_rows). La accion masiva agrupa a los destinatarios que comparten el mismo
 * resumen (una llamada por grupo, cada destinatario recibe un solo aviso). Un fallo del aviso (o las tablas de
 * notificaciones ausentes) no impide la accion: estas funciones nunca lanzan.
 *
 * Clave de evento: tipo:accion:momento:huella. La huella resume los hechos concretos (ids de fila insertados, elementos
 * borrados, cantidad nueva del inventario). Reintentar la misma accion no avisa dos veces: si no cambia nada no hay aviso,
 * y si se repite el mismo hecho en el mismo segundo la clave coincide.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/notificaciones.php';

/** Nombres que se guardan en el resumen de un cambio de varios elementos. */
const OPG_POS_MAX_NOMBRES = 3;
/** Caracteres de cada nombre que se guardan. */
const OPG_POS_MAX_NOMBRE = 60;

/** Tipo de aviso de cada clase de elemento. */
function opg_pos_tipo($clase)
{
    $tipos = array('tecnica' => 'tecnica_cambio', 'virtud' => 'virtud_cambio', 'objeto' => 'objeto_cambio');
    return isset($tipos[$clase]) ? $tipos[$clase] : null;
}

/** Accion del aviso segun la del formulario del staff ('Añadir' o 'Remover'); '' si no es ninguna. */
function opg_pos_accion($accion_formulario)
{
    $acciones = array('Añadir' => 'otorgada', 'Remover' => 'retirada');
    return isset($acciones[$accion_formulario]) ? $acciones[$accion_formulario] : '';
}

/** Nombre limpio para los datos: una linea, sin "<" ni ">" (los datos no llevan HTML) y recortado. */
function opg_pos_limpiar_nombre($nombre)
{
    if (!is_scalar($nombre)) {
        return '';
    }
    $nombre = trim(preg_replace('/\s+/u', ' ', str_replace(array('<', '>'), '', (string) $nombre)));
    if (function_exists('mb_strlen') && mb_strlen($nombre, 'UTF-8') > OPG_POS_MAX_NOMBRE) {
        $nombre = rtrim(mb_substr($nombre, 0, OPG_POS_MAX_NOMBRE - 1, 'UTF-8')) . '…';
    }
    return $nombre;
}

/**
 * Datos del aviso (funcion pura). $nombres tiene un nombre por elemento cambiado (puede repetirse: dos unidades del mismo
 * objeto). Devuelve null si la accion no es valida o no cambio nada.
 */
function opg_pos_datos_aviso($accion, $nombres, $lista = '')
{
    if (!in_array($accion, array('otorgada', 'modificada', 'retirada'), true)) {
        return null;
    }
    $limpios = array();
    foreach ((array) $nombres as $nombre) {
        $nombre = opg_pos_limpiar_nombre($nombre);
        if ($nombre !== '') {
            $limpios[] = $nombre;
        }
    }
    if (!$limpios) {
        return null;
    }
    $datos = array('accion' => $accion, 'cantidad' => count($limpios));
    if ($lista === 'por_aprender') {
        $datos['lista'] = 'por_aprender';
    }
    $distintos = array_values(array_unique($limpios));
    if (count($limpios) === 1) {
        $datos['nombre'] = $limpios[0];
    } else {
        $resumen = implode(', ', array_slice($distintos, 0, OPG_POS_MAX_NOMBRES));
        $resto = count($distintos) - OPG_POS_MAX_NOMBRES;
        $datos['nombres'] = $resto > 0 ? $resumen . ' y ' . $resto . ' más' : $resumen;
    }
    return $datos;
}

/** Clave de evento (funcion pura): tipo:accion:momento:huella de los hechos concretos. Hasta 128 caracteres validos. */
function opg_pos_clave_aviso($tipo, $accion, $huellas, $momento)
{
    $huellas = array_map('strval', (array) $huellas);
    sort($huellas, SORT_STRING);
    return $tipo . ':' . $accion . ':' . (int) $momento . ':' . substr(sha1(implode('|', $huellas)), 0, 16);
}

/**
 * Agrupa a los destinatarios de una accion masiva por su resumen (funcion pura). $por_uid es uid => lista de nombres
 * cambiados de ese usuario. Devuelve una lista de array('uids' => [...], 'datos' => [...]): cada uid en un solo grupo.
 */
function opg_pos_grupos_masivo($por_uid, $accion)
{
    $grupos = array();
    foreach ((array) $por_uid as $uid => $nombres) {
        $uid = (int) $uid;
        $datos = opg_pos_datos_aviso($accion, $nombres);
        if ($uid <= 0 || $datos === null) {
            continue;
        }
        $clave = json_encode($datos);
        if (!isset($grupos[$clave])) {
            $grupos[$clave] = array('uids' => array(), 'datos' => $datos);
        }
        $grupos[$clave]['uids'][] = $uid;
    }
    return array_values($grupos);
}

// ── Con base de datos ───────────────────────────────────────────────────────────────────────────────────────────────

/** Nombres visibles de los elementos: id => nombre. Los que no se encuentran no aparecen. */
function opg_pos_nombres($clase, $ids)
{
    global $db;
    $tablas = array(
        'tecnica' => array('mybb_op_tecnicas', 'tid'),
        'virtud' => array('mybb_op_virtudes', 'virtud_id'),
        'objeto' => array('mybb_op_objetos', 'objeto_id'),
    );
    $ids = array_values(array_unique(array_filter(array_map('strval', (array) $ids), 'strlen')));
    if (!isset($tablas[$clase]) || !$ids) {
        return array();
    }
    list($tabla, $col) = $tablas[$clase];
    $lista = array();
    foreach ($ids as $id) {
        $lista[] = "'" . $db->escape_string($id) . "'";
    }
    $q = $db->query("SELECT `{$col}` AS id, `nombre` FROM `{$tabla}` WHERE `{$col}` IN (" . implode(',', $lista) . ")", 1);
    $nombres = array();
    while ($q && ($f = $db->fetch_array($q))) {
        $clave = strtoupper((string) $f['id']);
        if (!isset($nombres[$clave]) && trim((string) $f['nombre']) !== '') {
            $nombres[$clave] = (string) $f['nombre'];
        }
    }
    return $nombres;
}

/** Nombre de cada id (en el mismo orden, con repeticiones); si no esta en el catalogo, el propio id. */
function opg_pos_nombres_de($clase, $ids)
{
    $mapa = opg_pos_nombres($clase, $ids);
    $nombres = array();
    foreach ((array) $ids as $id) {
        $clave = strtoupper((string) $id);
        $nombres[] = isset($mapa[$clave]) ? $mapa[$clave] : (string) $id;
    }
    return $nombres;
}

/**
 * Avisa al dueño de la ficha de un cambio individual. $ids son los elementos que la escritura realmente cambio (uno por
 * unidad) y $huellas los hechos concretos para la clave. Sin cambios no avisa. Nunca lanza: devuelve lo mismo que
 * opg_notificar, 0 si no habia nada que avisar, o false.
 */
function opg_pos_avisar($clase, $uid_dueno, $accion, $ids, $huellas, $actor_uid, $lista = '', $momento = null)
{
    try {
        $tipo = opg_pos_tipo($clase);
        $ids = (array) $ids;
        if ($tipo === null || !$ids || (int) $uid_dueno <= 0) {
            return 0;
        }
        if (!opg_notif_tablas_listas()) {
            return false;
        }
        $datos = opg_pos_datos_aviso($accion, opg_pos_nombres_de($clase, $ids), $lista);
        if ($datos === null) {
            return 0;
        }
        $momento = $momento === null ? time() : (int) $momento;
        return opg_notificar((int) $uid_dueno, $tipo, $datos, array('actor' => (int) $actor_uid,
            'clave' => opg_pos_clave_aviso($tipo, $accion, $huellas, $momento)));
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Accion masiva del editor de tecnicas: avisa a quienes tienen aprendida alguna de las tecnicas modificadas, con UN aviso
 * por destinatario que resume cuantas de sus tecnicas cambiaron (requisito 40). Una llamada por grupo de destinatarios
 * con el mismo resumen. Nunca lanza: devuelve cuantos avisos nuevos creo o false si algo fallo.
 */
function opg_pos_avisar_tecnicas_modificadas($tids, $actor_uid, $momento = null)
{
    global $db;
    try {
        $tids = array_values(array_unique(array_filter(array_map('strval', (array) $tids), 'strlen')));
        if (!$tids) {
            return 0;
        }
        if (!opg_notif_tablas_listas()) {
            return false;
        }
        $nombres = opg_pos_nombres('tecnica', $tids);
        $lista = array();
        foreach ($tids as $tid) {
            $lista[] = "'" . $db->escape_string($tid) . "'";
        }
        $q = $db->query("SELECT DISTINCT `uid`, `tid` FROM `mybb_op_tec_aprendidas` WHERE `tid` IN (" . implode(',', $lista) . ") ORDER BY `uid`, `tid`", 1);
        $por_uid = array();
        while ($q && ($f = $db->fetch_array($q))) {
            $clave = strtoupper((string) $f['tid']);
            $por_uid[(int) $f['uid']][] = isset($nombres[$clave]) ? $nombres[$clave] : (string) $f['tid'];
        }
        $momento = $momento === null ? time() : (int) $momento;
        $clave_evento = opg_pos_clave_aviso('tecnica_cambio', 'modificada', $tids, $momento);
        $total = 0;
        $fallo = false;
        foreach (opg_pos_grupos_masivo($por_uid, 'modificada') as $grupo) {
            $r = opg_notificar($grupo['uids'], 'tecnica_cambio', $grupo['datos'], array('actor' => (int) $actor_uid, 'clave' => $clave_evento));
            if ($r === false) {
                $fallo = true;
            } else {
                $total += (int) $r;
            }
        }
        return $fallo ? false : $total;
    } catch (Throwable $e) {
        return false;
    }
}
