<?php
/**
 * Sistema global de notificaciones: libreria de envio y lectura.
 *
 * Documentos: docs/108_Requirements_Notificaciones.md y docs/208_DesignPlan_Notificaciones.md (apartado 5).
 * Tablas: docs/notificaciones_migration.sql (mybb_op_notificaciones y mybb_op_notificaciones_contador).
 * Tipos, textos y enlaces: lib/notificaciones_catalogo.php.
 *
 * Organizacion del archivo:
 *   1. Utilidades comunes (tablas, hora, registro de errores, SQL), compartidas por el envio y la lectura.
 *   2. Envio (issue #3): opg_notificar() y sus ayudantes. Es el UNICO punto de entrada para crear avisos: ningun modulo
 *      hace INSERT directo en las tablas.
 *   3. Lectura (issue #4): contar, listar, marcar como vista y reconciliar. Va al final del archivo.
 *
 * Convenciones:
 *  - Codigo compatible con PHP 7.4. Usa el $db global de MyBB (inc/db_mysqli.php): write_query, escape_string,
 *    affected_rows, insert_id, fetch_array, table_exists y table_prefix.
 *  - Todas las consultas van por write_query(..., 1): por la conexion de escritura (la misma de una transaccion abierta
 *    por quien llama, asi que el aviso entra y se revierte con ella) y sin el error de MyBB que detiene la pagina.
 *  - Ninguna funcion de envio lanza excepciones ni escribe salida: los errores se registran con error_log y se devuelve
 *    false (requisito 7). Tampoco abre transacciones propias: un START TRANSACTION confirmaria la de quien llama.
 *  - Todo valor que llega a SQL es un entero validado o un texto escapado con $db->escape_string().
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/notificaciones_catalogo.php';

/** Destinatarios por sentencia de insercion. Un lote mayor se parte en bloques de este tamano (208, apartado 14). */
const OPG_NOTIF_LOTE = 200;
/** Largo de clave_evento en la tabla. */
const OPG_NOTIF_MAX_CLAVE = 128;
/** Sitio que reserva una agrupable para el sufijo ":leida:ID" (7 + 10 cifras) que le pone "marcar como vista". */
const OPG_NOTIF_SUFIJO_LEIDA = 17;
/** Largo de la columna url. */
const OPG_NOTIF_MAX_URL = 255;
/** Limites de datos_json: claves por aviso y caracteres por valor. */
const OPG_NOTIF_MAX_DATOS = 20;
const OPG_NOTIF_MAX_VALOR = 255;
/** Mayor uid que cabe en INT UNSIGNED. */
const OPG_NOTIF_UID_MAX = 4294967295;

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
// 1. Utilidades comunes
// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════

/** Nombre de tabla con prefijo y comillas: opg_notif_t('op_notificaciones') => `mybb_op_notificaciones`. */
function opg_notif_t($nombre)
{
    global $db;
    $prefijo = (is_object($db) && isset($db->table_prefix) && is_string($db->table_prefix)) ? $db->table_prefix
        : (defined('TABLE_PREFIX') ? TABLE_PREFIX : 'mybb_');
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefijo)) {
        $prefijo = 'mybb_';
    }
    return '`' . $prefijo . $nombre . '`';
}

/** Hora actual (TIME_NOW de MyBB si existe). */
function opg_notif_ahora()
{
    return defined('TIME_NOW') ? (int) TIME_NOW : time();
}

/** Registra un error del sistema de notificaciones (nunca se muestra al usuario). */
function opg_notif_log($mensaje)
{
    error_log('[notificaciones] ' . $mensaje);
}

/**
 * Indica si existen las dos tablas (despliegue a medias: requisito 37). Se comprueba una vez por peticion;
 * $reiniciar = true vuelve a mirar (lo usan las pruebas). Si el $db no sabe comprobarlo (dobles de pruebas), se supone que si.
 */
function opg_notif_tablas_listas($reiniciar = false)
{
    global $db;
    static $listas = null;
    if ($reiniciar) {
        $listas = null;
    }
    if ($listas === null) {
        if (!is_object($db)) {
            return false;
        }
        try {
            $listas = !method_exists($db, 'table_exists')
                || ((bool) $db->table_exists('op_notificaciones') && (bool) $db->table_exists('op_notificaciones_contador'));
        } catch (Throwable $e) {
            $listas = false;
        }
    }
    return $listas;
}

/** Ejecuta una consulta por la conexion de escritura. Si falla, lanza RuntimeException (la captura quien la llama). */
function opg_notif_w($sql)
{
    global $db;
    $r = $db->write_query($sql, 1);
    if ($r === false) {
        throw new RuntimeException('error SQL: ' . (method_exists($db, 'error_string') ? $db->error_string() : '?'));
    }
    return $r;
}

/** Todas las filas de una consulta (por la conexion de escritura). */
function opg_notif_filas($sql)
{
    global $db;
    $r = opg_notif_w($sql);
    $filas = array();
    if (is_object($r) || $r instanceof mysqli_result) {
        while ($f = $db->fetch_array($r)) {
            $filas[] = $f;
        }
    }
    return $filas;
}

/** Literal SQL de un texto: 'escapado' o NULL. */
function opg_notif_sql_texto($valor)
{
    global $db;
    return $valor === null ? 'NULL' : "'" . $db->escape_string((string) $valor) . "'";
}

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
// 2. Envio (issue #3)
// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════

// ── 2.1 Ayudantes puros ─────────────────────────────────────────────────────────────────────────────────────────────

/** uid valido (entero, o texto solo de digitos, entre 1 y el maximo de INT UNSIGNED) o 0. */
function opg_notif_uid($valor)
{
    if (is_int($valor)) {
        return ($valor > 0 && $valor <= OPG_NOTIF_UID_MAX) ? $valor : 0;
    }
    if (is_string($valor) && preg_match('/^[1-9][0-9]{0,9}$/D', $valor)) {
        $n = (int) $valor;
        return $n <= OPG_NOTIF_UID_MAX ? $n : 0;
    }
    return 0;
}

/**
 * Destinatarios normalizados (requisitos 4 y 5): enteros mayores que cero, sin repetidos y sin el actor (salvo
 * $incluir_actor). Se devuelven ordenados para que dos envios simultaneos bloqueen las filas en el mismo orden.
 */
function opg_notif_normalizar_destinatarios($destinatarios, $actor = 0, $incluir_actor = false)
{
    if (!is_array($destinatarios)) {
        $destinatarios = array($destinatarios);
    }
    $actor = opg_notif_uid($actor);
    $uids = array();
    foreach ($destinatarios as $d) {
        $uid = opg_notif_uid($d);
        if ($uid > 0 && ($incluir_actor || $uid !== $actor)) {
            $uids[$uid] = true;
        }
    }
    $uids = array_keys($uids);
    sort($uids, SORT_NUMERIC);
    return $uids;
}

/**
 * Clave de evento del aviso, o null si no es valida (requisito 3).
 * - Tipos normales: la opcion 'clave' es obligatoria (por ejemplo "entrega:812:entregada").
 * - Agrupables: 'clave' si se da; si no, se genera como "tipo:agrupar_por" (por ejemplo "bitacora_post:812"). Su largo
 *   deja sitio al sufijo ":leida:ID" con el que "marcar como vista" libera la clave (208, apartado 5.4), y no puede
 *   contener ese sufijo.
 * Solo se admiten letras, numeros y "_ . : -": la clave nunca necesita escaparse, aunque se escapa igual.
 */
function opg_notif_clave_evento($tipo, $def, $opciones)
{
    $agrupable = !empty($def['agrupable']);
    $max = $agrupable ? OPG_NOTIF_MAX_CLAVE - OPG_NOTIF_SUFIJO_LEIDA : OPG_NOTIF_MAX_CLAVE;
    if (isset($opciones['clave'])) {
        $clave = $opciones['clave'];
    } elseif ($agrupable && isset($opciones['agrupar_por'])) {
        $grupo = $opciones['agrupar_por'];
        if (is_int($grupo)) {
            $grupo = (string) $grupo;
        }
        if (!is_string($grupo) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $grupo)) {
            return null;
        }
        $clave = $tipo . ':' . $grupo;
    } else {
        return null;
    }
    if (!is_string($clave) || !preg_match('/^[A-Za-z0-9_.:-]{1,' . $max . '}$/D', $clave)) {
        return null;
    }
    if ($agrupable && strpos($clave, ':leida:') !== false) {
        return null;
    }
    return $clave;
}

/**
 * Enlace listo para guardar, o null si no es una ruta interna (requisito 49).
 * Se guarda en su forma DECODIFICADA, una sola vez: "a.php?x=1&amp;amp;y=2" se guarda como "a.php?x=1&y=2", de modo que
 * al imprimirlo con htmlspecialchars una vez el navegador lea el parametro correcto. La validacion es la del catalogo
 * (opg_notif_url_interna), aplicada al original y al resultado.
 */
function opg_notif_url_guardable($url)
{
    if (!is_string($url) || !opg_notif_url_interna($url)) {
        return null;
    }
    $normal = $url;
    for ($i = 0; $i < 10; $i++) {
        $siguiente = html_entity_decode($normal, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($siguiente === $normal) {
            break;
        }
        $normal = $siguiente;
    }
    if (strlen($normal) > OPG_NOTIF_MAX_URL || !opg_notif_url_interna($normal)) {
        return null;
    }
    return $normal;
}

/**
 * Datos que se guardan en datos_json (requisito 26): solo valores escalares, con claves cortas en minusculas
 * ("peticion_id", "tema", "actor"...), textos limpios y recortados, y nunca HTML (un valor que contiene una etiqueta se
 * descarta y se registra). Los textos se guardan crudos: el catalogo los escapa una vez al mostrarlos.
 */
function opg_notif_preparar_datos($datos)
{
    if (!is_array($datos)) {
        return array();
    }
    $limpios = array();
    foreach ($datos as $clave => $valor) {
        if (count($limpios) >= OPG_NOTIF_MAX_DATOS) {
            break;
        }
        if (!is_string($clave) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $clave)) {
            continue;
        }
        if (is_bool($valor)) {
            $valor = $valor ? 1 : 0;
        }
        if (is_int($valor) || is_float($valor)) {
            $limpios[$clave] = $valor;
            continue;
        }
        if (!is_string($valor)) {
            continue;
        }
        $valor = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $valor));
        if (preg_match('/<\s*\/?\s*[a-z!?]/i', $valor)) {
            opg_notif_log("dato '$clave' descartado: contiene HTML");
            continue;
        }
        if (function_exists('mb_substr')) {
            $valor = mb_substr($valor, 0, OPG_NOTIF_MAX_VALOR, 'UTF-8');
        } else {
            $valor = substr($valor, 0, OPG_NOTIF_MAX_VALOR);
        }
        $limpios[$clave] = $valor;
    }
    return $limpios;
}

/** JSON de los datos (sin escapar unicode; UTF-8 roto se sustituye), o null si no hay datos. */
function opg_notif_datos_json($datos)
{
    if (!$datos) {
        return null;
    }
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    return is_string($json) ? $json : null;
}

// ── 2.2 Datos que dependen de la base ───────────────────────────────────────────────────────────────────────────────

/** true si el tema existe y esta visible (ni sin aprobar ni borrado). Ante cualquier error, false. */
function opg_notif_tema_visible($tid)
{
    $tid = opg_notif_uid($tid);
    if ($tid <= 0) {
        return false;
    }
    try {
        $filas = opg_notif_filas('SELECT visible FROM ' . opg_notif_t('threads') . ' WHERE tid = ' . $tid . ' LIMIT 1');
        return $filas && (int) $filas[0]['visible'] === 1;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Datos de un envio: opg_notif_preparar_datos() mas la regla del requisito 50 para los temas del foro. Si los datos
 * llevan 'tid', el titulo ('tema') solo se guarda cuando quien llama indica 'tema_publico' => true (el tema lo puede ver
 * cualquiera) y el tema esta visible; si no, se guarda el tid y el texto dice "el tema #tid". Sin 'tid' (por ejemplo el
 * titulo de una aventura del Tablon, que va con 'peticion_id'), decide quien llama: solo debe pasar titulos que el
 * destinatario pueda ver.
 */
function opg_notif_preparar_datos_envio($datos, $opciones)
{
    $limpios = opg_notif_preparar_datos($datos);
    if (isset($limpios['tid'], $limpios['tema'])) {
        $publico = isset($opciones['tema_publico']) && $opciones['tema_publico'] === true;
        if (!$publico || !opg_notif_tema_visible($limpios['tid'])) {
            unset($limpios['tema']);
        }
    }
    return $limpios;
}

// ── 2.3 Envio ───────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Crea un aviso para uno o varios destinatarios (208, apartado 5).
 *
 * $destinatarios  uid o lista de uid. Se descartan los no validos, los repetidos y el actor.
 * $tipo           clave del catalogo (opg_notif_catalogo).
 * $datos          lo necesario para escribir el texto, calculado por quien llama: 'peticion_id', 'tema', 'isla', 'tier',
 *                 'actor', 'narrador', 'motivo', 'dias', 'tid', 'nombre', segun el tipo. Solo escalares y sin HTML.
 * $opciones       'actor'         uid de quien provoca el cambio (0 = sistema). Por defecto 0.
 *                 'clave'         clave de evento: obligatoria salvo en agrupables. Repetirla no duplica nada.
 *                 'agrupar_por'   en agrupables, el grupo (por ejemplo el tid); la clave sera "tipo:grupo".
 *                 'url'           enlace interno; si no es una ruta interna se descarta (y se registra).
 *                 'incluir_actor' true para que el actor tambien reciba el aviso. Por defecto no.
 *                 'tema_publico'  true si el tema 'tid' de los datos es visible para todos (ver opg_notif_preparar_datos_envio).
 *                 'silenciosa'    true: no crea nada (acciones tecnicas). Cada uso debe justificarse donde se llama.
 *
 * Devuelve cuantas notificaciones nuevas creo (int; una agrupable que solo sumo un evento no cuenta) o false si hubo un
 * error, que queda registrado. Nunca lanza excepciones. Con las tablas ausentes devuelve false sin registrar nada.
 *
 * Contador: sube solo por las filas realmente nuevas, con sentencias relativas (sin_leer = sin_leer + 1), sin leer antes
 * de escribir. Si el contador del destinatario no existe, se crea con un recuento que ya incluye el aviso nuevo.
 */
function opg_notificar($destinatarios, $tipo, $datos = array(), $opciones = array())
{
    global $db;
    try {
        $def = opg_notif_tipo($tipo);
        if (!$def) {
            opg_notif_log('opg_notificar: tipo desconocido ' . var_export(is_scalar($tipo) ? $tipo : gettype($tipo), true));
            return false;
        }
        $opciones = is_array($opciones) ? $opciones : array();
        $clave = opg_notif_clave_evento($tipo, $def, $opciones);
        if ($clave === null) {
            opg_notif_log("opg_notificar: clave de evento ausente o no valida para el tipo $tipo");
            return false;
        }
        if (!empty($opciones['silenciosa'])) {
            return 0;
        }
        $actor = isset($opciones['actor']) ? opg_notif_uid($opciones['actor']) : 0;
        $incluir_actor = isset($opciones['incluir_actor']) && $opciones['incluir_actor'] === true;
        $uids = opg_notif_normalizar_destinatarios($destinatarios, $actor, $incluir_actor);
        if (!$uids) {
            return 0;
        }
        if (!is_object($db)) {
            opg_notif_log('opg_notificar: no hay conexion $db');
            return false;
        }
        if (!opg_notif_tablas_listas()) {
            return false;
        }

        $url = null;
        if (isset($opciones['url']) && $opciones['url'] !== '' && $opciones['url'] !== null) {
            $url = opg_notif_url_guardable($opciones['url']);
            if ($url === null) {
                opg_notif_log("opg_notificar: enlace descartado (no es una ruta interna) en $tipo, clave $clave");
            }
        }
        $json = opg_notif_datos_json(opg_notif_preparar_datos_envio($datos, $opciones));
        $imp = $def['importancia'] === 'importante' ? 1 : 0;
        $ahora = opg_notif_ahora();

        $nuevas = 0;
        foreach (array_chunk($uids, OPG_NOTIF_LOTE) as $bloque) {
            $nuevos = opg_notif_envio_insertar($bloque, $tipo, $def, $actor, $clave, $url, $json, $ahora);
            if ($nuevos) {
                opg_notif_envio_contador($nuevos, $imp, $ahora);
            }
            $nuevas += count($nuevos);
        }
        return $nuevas;
    } catch (Throwable $e) {
        opg_notif_log('opg_notificar(' . (is_string($tipo) ? $tipo : '?') . '): ' . $e->getMessage());
        return false;
    }
}

/**
 * (Privada del envio.) Inserta un bloque y devuelve los uid cuya fila es NUEVA (el contador solo sube por esas).
 *
 * - Normal: un solo INSERT IGNORE multifila para todo el bloque (requisito 35). Una fila que ya existia con esa clave no
 *   se toca. Si entran todas o ninguna no hace falta mas; si entran solo algunas, ver opg_notif_envio_identificar().
 * - Agrupable: INSERT ... ON DUPLICATE KEY UPDATE (cantidad + 1, ultimo actor, datos y fecha renovada), UNA sentencia
 *   atomica POR DESTINATARIO (208, apartado 5.3). MySQL cuenta 1 fila afectada si inserto y 2 si actualizo, asi que cada
 *   sentencia dice con exactitud si su fila es nueva. No se usa la variante multifila porque, con dos procesos sumando a
 *   la vez en grupos vecinos (por ejemplo dos temas con los mismos seguidores), InnoDB la interbloquea (medido en
 *   MySQL 5.7: varios interbloqueos cada 400 sentencias; la de una fila, ninguno), y un interbloqueo revierte la
 *   transaccion entera de quien llama. El incremento del contador sigue siendo una sola sentencia para todo el bloque.
 */
function opg_notif_envio_insertar($uids, $tipo, $def, $actor, $clave, $url, $json, $ahora)
{
    global $db;
    $agrupable = !empty($def['agrupable']);
    $columnas = ' (uid, tipo, categoria, origen, importancia, agrupable, actor_uid, clave_evento, url, cantidad, datos_json, created_at) VALUES ';
    $fijo = opg_notif_sql_texto($tipo) . ', ' . opg_notif_sql_texto($def['categoria']) . ', ' . opg_notif_sql_texto($def['origen'])
        . ', ' . opg_notif_sql_texto($def['importancia']) . ', ' . ($agrupable ? 1 : 0) . ', ' . (int) $actor . ', '
        . opg_notif_sql_texto($clave) . ', ' . opg_notif_sql_texto($url) . ', 1, ' . opg_notif_sql_texto($json) . ', ' . (int) $ahora;

    if ($agrupable) {
        $nuevos = array();
        try {
            foreach ($uids as $uid) {
                opg_notif_w('INSERT INTO ' . opg_notif_t('op_notificaciones') . $columnas . '(' . (int) $uid . ', ' . $fijo . ')'
                    . ' ON DUPLICATE KEY UPDATE cantidad = cantidad + 1, actor_uid = VALUES(actor_uid), url = VALUES(url),'
                    . ' datos_json = VALUES(datos_json), created_at = VALUES(created_at)');
                $afectadas = (int) $db->affected_rows();
                if ($afectadas === 1) {
                    $nuevos[] = (int) $uid;
                } elseif ($afectadas !== 2) {
                    opg_notif_log("opg_notificar($tipo): filas afectadas inesperadas ($afectadas) para el uid $uid; su contador no se toca");
                }
            }
        } catch (Throwable $e) {
            // Las filas ya creadas de este bloque llevan su contador antes de devolver el error.
            if ($nuevos) {
                opg_notif_envio_contador($nuevos, $def['importancia'] === 'importante' ? 1 : 0, $ahora);
            }
            throw $e;
        }
        return $nuevos;
    }

    $filas = array();
    foreach ($uids as $uid) {
        $filas[] = '(' . (int) $uid . ', ' . $fijo . ')';
    }
    opg_notif_w('INSERT IGNORE INTO ' . opg_notif_t('op_notificaciones') . $columnas . implode(',', $filas));
    $insertadas = (int) $db->affected_rows();
    $n = count($uids);
    if ($insertadas < 0 || $insertadas > $n) {
        throw new RuntimeException("filas afectadas inesperadas ($insertadas para $n destinatarios); el contador no se toca");
    }
    if ($insertadas === 0) {
        return array();
    }
    if ($insertadas === $n) {
        return $uids;
    }
    $primer_id = method_exists($db, 'insert_id') ? (int) $db->insert_id() : 0;
    return opg_notif_envio_identificar($uids, $clave, $insertadas, $primer_id);
}

/**
 * (Privada del envio.) En un lote donde solo entraron algunas filas, averigua cuales son las nuevas.
 *
 * Por que no se consulta por clave y fecha (la idea inicial de 208, apartado 5.2): si otro proceso envia el mismo evento
 * en el mismo segundo, las filas que inserto el otro tienen la misma clave y la misma fecha, y las dos llamadas subirian
 * el contador del mismo destinatario.
 *
 * Lo que se hace: InnoDB (innodb_autoinc_lock_mode 0 o 1, el valor por defecto de MySQL 5.7) reserva a cada sentencia de
 * insercion un bloque de ids consecutivo y exclusivo, y insert_id() devuelve el id de la primera fila que la sentencia
 * inserto de verdad. Cualquier otra fila con la misma clave y uno de estos destinatarios tiene un id menor (de una
 * sentencia anterior) o mayor que todos los de este bloque (de una posterior). Por eso las nuevas son exactamente las
 * $insertadas filas de menor id con id >= primer_id. Con el modo 2 (intercalado) el resultado puede no ser exacto bajo
 * concurrencia: se registra un aviso y el desfase lo corrige la reconciliacion del contador.
 */
function opg_notif_envio_identificar($uids, $clave, $insertadas, $primer_id)
{
    static $modo = null;
    if ($modo === null) {
        try {
            $f = opg_notif_filas('SELECT @@innodb_autoinc_lock_mode AS modo');
            $modo = $f ? (int) $f[0]['modo'] : -1;
        } catch (Throwable $e) {
            $modo = -1;
        }
    }
    if ($modo !== 0 && $modo !== 1) {
        opg_notif_log("innodb_autoinc_lock_mode = $modo: las filas nuevas de un lote parcial se identifican de forma aproximada");
    }
    $filas = opg_notif_filas('SELECT id, uid FROM ' . opg_notif_t('op_notificaciones') . ' WHERE clave_evento = ' . opg_notif_sql_texto($clave)
        . ' AND uid IN (' . implode(',', array_map('intval', $uids)) . ') AND id >= ' . max(0, (int) $primer_id)
        . ' ORDER BY id LIMIT ' . (int) $insertadas);
    $nuevos = array();
    foreach ($filas as $f) {
        $nuevos[] = (int) $f['uid'];
    }
    if (count($nuevos) !== $insertadas) {
        opg_notif_log("lote parcial: se esperaban $insertadas filas nuevas con clave $clave y se encontraron " . count($nuevos));
    }
    sort($nuevos, SORT_NUMERIC);
    return $nuevos;
}

/**
 * (Privada del envio.) Suma un aviso al contador de cada uid (todos reciben el mismo tipo, asi que el incremento es igual):
 * una sola sentencia relativa, sin leer antes (requisito 36). Los uid sin fila de contador se crean con un recuento de
 * sus no leidas, que ya incluye el aviso recien insertado (208, apartado 5.2).
 *
 * Caso limite conocido: solo cuando el destinatario aun NO tiene contador y, en el mismo instante, otro proceso le crea
 * otro aviso distinto, el recuento puede incluir la fila del otro antes de que este sume la suya (el contador queda uno
 * por encima). No hay forma exacta sin abrir una transaccion propia (que confirmaria la de quien llama); lo corrige la
 * reconciliacion del contador (opg_notif_reconciliar, issue #4). Con el contador ya creado, todo es exacto.
 */
function opg_notif_envio_contador($uids, $imp, $ahora)
{
    global $db;
    $lista = implode(',', array_map('intval', $uids));
    opg_notif_w('UPDATE ' . opg_notif_t('op_notificaciones_contador') . ' SET sin_leer = sin_leer + 1, importantes = importantes + '
        . ($imp ? 1 : 0) . ', actualizado = ' . (int) $ahora . ' WHERE uid IN (' . $lista . ')');
    $actualizados = (int) $db->affected_rows();
    if ($actualizados >= count($uids)) {
        return;
    }
    if (count($uids) === 1) {
        $faltan = $uids;
    } else {
        $existen = array();
        foreach (opg_notif_filas('SELECT uid FROM ' . opg_notif_t('op_notificaciones_contador') . ' WHERE uid IN (' . $lista . ')') as $f) {
            $existen[(int) $f['uid']] = true;
        }
        $faltan = array();
        foreach ($uids as $uid) {
            if (!isset($existen[(int) $uid])) {
                $faltan[] = (int) $uid;
            }
        }
        if (!$faltan) {
            return;
        }
    }
    opg_notif_w('INSERT INTO ' . opg_notif_t('op_notificaciones_contador') . ' (uid, sin_leer, importantes, actualizado)'
        . " SELECT uid, COUNT(*), COALESCE(SUM(importancia = 'importante'), 0), " . (int) $ahora . ' FROM ' . opg_notif_t('op_notificaciones')
        . ' WHERE uid IN (' . implode(',', $faltan) . ') AND leida_en IS NULL GROUP BY uid'
        . ' ON DUPLICATE KEY UPDATE sin_leer = VALUES(sin_leer), importantes = VALUES(importantes), actualizado = VALUES(actualizado)');
}

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
// 3. Lectura (issue #4): opg_notif_contar, opg_notif_listar, opg_notif_marcar_vista, opg_notif_marcar_todas y
//    opg_notif_reconciliar van a partir de aqui.
// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
