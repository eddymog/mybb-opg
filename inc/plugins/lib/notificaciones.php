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
 *    affected_rows, insert_id, fetch_array, error_number, error_string y table_prefix (no table_exists: ver opg_notif_tablas_listas).
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
 * $reiniciar = true vuelve a mirar (lo usan las pruebas).
 *
 * No se usa $db->table_exists(): en inc/db_mysqli.php llama a get_version() y a query() SIN ocultar errores, y con la
 * conexion caida (y mysqli sin excepciones) MyBB mostraria su error SQL y terminaria la pagina. En su lugar se lanza una
 * sola consulta propia por write_query(..., 1), que no devuelve filas (LIMIT 0) y falla con el error 1146 si falta
 * cualquiera de las dos tablas; cualquier fallo (o excepcion de mysqli en modo estricto) se toma como "no estan".
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
            $listas = $db->write_query('SELECT 1 FROM ' . opg_notif_t('op_notificaciones') . ', ' . opg_notif_t('op_notificaciones_contador')
                . ' LIMIT 0', 1) !== false;
        } catch (Throwable $e) {
            $listas = false;
        }
    }
    return $listas;
}

/**
 * Ejecuta una consulta por la conexion de escritura. Si falla, lanza RuntimeException con el numero y el mensaje de MySQL
 * (la captura opg_notificar). Ante un interbloqueo (1213) el mensaje avisa de que MySQL revirtio la transaccion entera de
 * quien llama, si tenia una abierta.
 */
function opg_notif_w($sql)
{
    global $db;
    $r = $db->write_query($sql, 1);
    if ($r === false) {
        $numero = method_exists($db, 'error_number') ? (int) $db->error_number() : 0;
        throw new RuntimeException('error SQL ' . $numero . ': ' . (method_exists($db, 'error_string') ? $db->error_string() : '?'), $numero);
    }
    return $r;
}

/** Texto para el registro de una excepcion; marca los errores que revierten o pueden revertir la transaccion de quien llama. */
function opg_notif_mensaje_error($e)
{
    $codigo = (int) $e->getCode();
    $extra = '';
    if ($codigo === 1213) {
        $extra = ' (interbloqueo: MySQL revirtio la transaccion de quien llama, si habia una abierta)';
    } elseif ($codigo === 1205) {
        $extra = ' (espera de bloqueo agotada: se revirtio la sentencia; la transaccion de quien llama sigue abierta)';
    }
    return $e->getMessage() . $extra;
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
 *
 * Como se compara: la unicidad es la del indice (uid, clave_evento), con la colacion de la tabla (utf8mb4_unicode_ci),
 * asi que NO distingue mayusculas: "Caso:1" y "caso:1" son la misma clave. Y la clave no incluye el tipo: si ya existe
 * una fila con esa clave para ese destinatario, el aviso nuevo se ignora aunque sea de otro tipo (en un agrupable, la
 * fila de otro tipo no se toca). Esos conflictos se registran con error_log (ver opg_notif_envio_conflictos). Para
 * evitarlos, la clave empieza por el modulo o el tipo y lleva el id del objeto ("entrega:812:entregada").
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
        if (is_float($valor) && !is_finite($valor)) {
            // json_encode no sabe codificar INF ni NAN: se descarta ese valor (y solo ese) y se registra.
            opg_notif_log("dato '$clave' descartado: numero no finito");
            continue;
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

/**
 * JSON de los datos (sin escapar unicode; UTF-8 roto se sustituye), null si no hay datos, o false si json_encode no pudo
 * codificarlos (se registra el motivo y el aviso no se envia: nunca se guardan los datos vacios en silencio).
 */
function opg_notif_datos_json($datos)
{
    if (!$datos) {
        return null;
    }
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) {
        opg_notif_log('datos_json no se pudo codificar: ' . json_last_error_msg());
        return false;
    }
    return $json;
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
 * Contador: sube solo por las filas realmente nuevas, con un unico INSERT ... ON DUPLICATE KEY UPDATE relativo por bloque
 * (sin_leer = sin_leer + 1), sin leer antes de escribir: crea la fila con 1 si no existe (ver opg_notif_envio_contador).
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
        if ($json === false) {
            return false;
        }
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
        opg_notif_log('opg_notificar(' . (is_string($tipo) ? $tipo : '?') . '): ' . opg_notif_mensaje_error($e));
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
 *   Si la fila existente con esa clave es de OTRO tipo, el IF(tipo = VALUES(tipo), ...) la deja intacta (0 filas
 *   afectadas) y se registra el conflicto.
 * Los uid llegan ordenados de menor a mayor, asi que todas las sentencias bloquean las filas en el mismo orden.
 *
 * Riesgo que queda (propio de InnoDB, no del contador): si dos transacciones abiertas crean a la vez la MISMA fila nueva
 * (misma clave y destinatario) y la que gana inserta despues, en esa misma transaccion, otra clave del mismo destinatario
 * que queda justo antes en el indice unico, InnoDB interbloquea las dos. Medido en pruebas: 1 de cada 150 acciones, solo
 * al crear el grupo. Se registra con el aviso de transaccion revertida (opg_notif_mensaje_error).
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
                    . ' ON DUPLICATE KEY UPDATE cantidad = IF(tipo = VALUES(tipo), cantidad + 1, cantidad),'
                    . ' actor_uid = IF(tipo = VALUES(tipo), VALUES(actor_uid), actor_uid), url = IF(tipo = VALUES(tipo), VALUES(url), url),'
                    . ' datos_json = IF(tipo = VALUES(tipo), VALUES(datos_json), datos_json),'
                    . ' created_at = IF(tipo = VALUES(tipo), VALUES(created_at), created_at)');
                $afectadas = (int) $db->affected_rows();
                if ($afectadas === 1) {
                    $nuevos[] = (int) $uid;
                } elseif ($afectadas === 0) {
                    opg_notif_log("opg_notificar($tipo): la clave $clave del uid $uid ya la usa un aviso de otro tipo; el evento se ignora");
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
    if ($insertadas === $n) {
        return $uids;
    }
    $nuevos = array();
    if ($insertadas > 0) {
        $primer_id = method_exists($db, 'insert_id') ? (int) $db->insert_id() : 0;
        $nuevos = opg_notif_envio_identificar($uids, $clave, $insertadas, $primer_id);
    }
    opg_notif_envio_conflictos(array_values(array_diff($uids, $nuevos)), $tipo, $clave);
    return $nuevos;
}

/**
 * (Privada del envio.) Solo cuando se ignoraron filas (la clave ya existia): busca si la fila existente es de otro tipo
 * o tiene la clave con otras mayusculas y, si es asi, lo registra. Una consulta por el indice unico, sin bloqueos, que no
 * se ejecuta en el caso normal (todas las filas nuevas). Nunca hace fallar el envio.
 */
function opg_notif_envio_conflictos($ignorados, $tipo, $clave)
{
    if (!$ignorados) {
        return;
    }
    try {
        $filas = opg_notif_filas('SELECT uid, tipo, clave_evento FROM ' . opg_notif_t('op_notificaciones') . ' WHERE clave_evento = '
            . opg_notif_sql_texto($clave) . ' AND uid IN (' . implode(',', array_map('intval', $ignorados)) . ') AND (tipo <> '
            . opg_notif_sql_texto($tipo) . ' OR BINARY clave_evento <> BINARY ' . opg_notif_sql_texto($clave) . ') LIMIT 5');
    } catch (Throwable $e) {
        return;
    }
    foreach ($filas as $f) {
        opg_notif_log("opg_notificar($tipo): la clave $clave del uid " . (int) $f['uid'] . ' ya existe como ' . $f['clave_evento']
            . ' (tipo ' . $f['tipo'] . '); el aviso nuevo se ignora');
    }
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
 * concurrencia: se registra un aviso (una vez por peticion) y el desfase lo corrige la reconciliacion del contador.
 */
function opg_notif_envio_identificar($uids, $clave, $insertadas, $primer_id)
{
    static $modo = null;
    static $avisado = false;
    if ($modo === null) {
        try {
            $f = opg_notif_filas('SELECT @@innodb_autoinc_lock_mode AS modo');
            $modo = $f ? (int) $f[0]['modo'] : -1;
        } catch (Throwable $e) {
            $modo = -1;
        }
    }
    if ($modo !== 0 && $modo !== 1 && !$avisado) {
        $avisado = true;
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
 * (Privada del envio.) Suma un aviso al contador de cada uid con UNA sola sentencia relativa para todo el bloque
 * (requisito 36, 208 apartado 5.2):
 *
 *   INSERT INTO contador (uid, sin_leer, importantes, actualizado) VALUES (u1, 1, imp, ahora), (u2, 1, imp, ahora)...
 *   ON DUPLICATE KEY UPDATE sin_leer = sin_leer + 1, importantes = importantes + VALUES(importantes)
 *
 * Si el contador no existe se crea con este aviso (1) y, si existe, se incrementa; nunca se lee antes. Cada uid aparece
 * una vez (todos reciben el mismo tipo, asi que el incremento es igual) y la lista va ordenada de menor a mayor, para que
 * dos lotes que se solapan bloqueen las filas en el mismo orden y no se interbloqueen.
 *
 * No se usa UPDATE seguido de INSERT ... SELECT COUNT(*): el UPDATE que no encuentra fila deja un bloqueo de hueco y,
 * dentro de transacciones, dos envios a usuarios distintos sin contador se interbloqueaban y bloqueaban a otros usuarios
 * (rechazo del QA de la issue #3). Tampoco se toca `actualizado`: significa creacion o ultima reconciliacion (5.5).
 * Consecuencia aceptada en 208: un contador nuevo no cuenta filas no leidas que ya existieran sin contador (solo las
 * copiadas del Tablon, cuyo script de migracion reconcilia al terminar).
 */
function opg_notif_envio_contador($uids, $imp, $ahora)
{
    $uids = array_values(array_unique(array_map('intval', $uids)));
    sort($uids, SORT_NUMERIC);
    $filas = array();
    foreach ($uids as $uid) {
        $filas[] = '(' . $uid . ', 1, ' . ($imp ? 1 : 0) . ', ' . (int) $ahora . ')';
    }
    opg_notif_w('INSERT INTO ' . opg_notif_t('op_notificaciones_contador') . ' (uid, sin_leer, importantes, actualizado) VALUES '
        . implode(',', $filas) . ' ON DUPLICATE KEY UPDATE sin_leer = sin_leer + 1, importantes = importantes + VALUES(importantes)');
}

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
// 3. Lectura (issue #4): opg_notif_contar, opg_notif_listar, opg_notif_marcar_vista, opg_notif_marcar_todas y
//    opg_notif_reconciliar (208, apartados 5.4 y 5.5).
// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
//
// Convenciones de la lectura:
//  - El uid lo pone quien llama y sale SIEMPRE de la sesion ($mybb->user['uid']); aqui solo se valida. Todas las
//    consultas filtran por ese uid, asi que nadie lee ni marca filas ajenas.
//  - Ninguna funcion lanza excepciones ni escribe salida: los errores se registran con error_log. Con las tablas ausentes
//    (error 1146, despliegue a medias) se devuelve cero o una lista vacia sin registrar nada (requisito 37).
//  - Los marcados y la reconciliacion abren su PROPIA transaccion corta (opg_notif_transaccion). Por eso no deben
//    llamarse con una transaccion abierta: el START TRANSACTION confirmaria la de quien llama. Se llaman desde las
//    acciones de la pagina, que no abren transacciones.
//  - Orden de bloqueo, el mismo que el del envio: primero las filas de notificaciones y despues la del contador. La
//    reconciliacion solo bloquea el contador y lee las filas sin bloquearlas.

/** Tamano de pagina de la lista (requisito 13) y maximo que se admite. */
const OPG_NOTIF_LIMITE = 30;
const OPG_NOTIF_LIMITE_MAX = 100;
/** Reintentos de una transaccion de lectura revertida por interbloqueo (1213) o espera agotada (1205). */
const OPG_NOTIF_REINTENTOS = 2;
/** Antiguedad maxima de la ultima reconciliacion del contador antes de recontar (requisito 33). */
const OPG_NOTIF_RECONCILIAR_CADA = 86400;
/** Largo maximo de la clave de una agrupable antes del sufijo ":leida:ID" (128 - 17). */
const OPG_NOTIF_MAX_CLAVE_AGRUPABLE = 111;

// ── 3.1 Ayudantes puros ─────────────────────────────────────────────────────────────────────────────────────────────

/** Categorias validas (las del catalogo). Es la lista fija contra la que se validan los filtros. */
function opg_notif_categorias()
{
    return array('aventuras', 'ficha', 'recompensas', 'tecnicas', 'peticiones', 'temas');
}

/** Origenes validos. */
function opg_notif_origenes()
{
    return array('gestion', 'usuario', 'sistema');
}

/**
 * Filtros normalizados, o null si alguno no es valido (y entonces no se consulta nada).
 *
 *   categoria    una de opg_notif_categorias()
 *   origen       uno de opg_notif_origenes()
 *   estado       'sin_leer' | 'leidas'
 *   importancia  'importante' | 'normal'
 *
 * Un valor vacio ('' o null) es "Todas" y se omite. $permitidos son las claves que admite la funcion que llama: una
 * clave desconocida o no permitida invalida el filtro entero, para que un filtro mal escrito nunca se convierta en
 * "todas" (en marcar_todas eso marcaria de mas).
 */
function opg_notif_filtros($filtros, $permitidos)
{
    if ($filtros === null) {
        return array();
    }
    if (!is_array($filtros)) {
        return null;
    }
    $listas = array(
        'categoria' => opg_notif_categorias(),
        'origen' => opg_notif_origenes(),
        'estado' => array('sin_leer', 'leidas'),
        'importancia' => array('importante', 'normal'),
    );
    $limpios = array();
    foreach ($filtros as $clave => $valor) {
        if (!is_string($clave) || !isset($listas[$clave]) || !in_array($clave, $permitidos, true)) {
            return null;
        }
        if ($valor === '' || $valor === null) {
            continue;
        }
        if (!is_string($valor) || !in_array($valor, $listas[$clave], true)) {
            return null;
        }
        $limpios[$clave] = $valor;
    }
    return $limpios;
}

/** Condiciones SQL (" AND ...") de unos filtros ya normalizados por opg_notif_filtros. */
function opg_notif_filtros_sql($filtros)
{
    $sql = '';
    foreach (array('categoria', 'origen') as $columna) {
        if (isset($filtros[$columna])) {
            $sql .= ' AND ' . $columna . ' = ' . opg_notif_sql_texto($filtros[$columna]);
        }
    }
    if (isset($filtros['estado'])) {
        $sql .= $filtros['estado'] === 'sin_leer' ? ' AND leida_en IS NULL' : ' AND leida_en IS NOT NULL';
    }
    if (isset($filtros['importancia'])) {
        $sql .= ' AND importancia = ' . opg_notif_sql_texto($filtros['importancia']);
    }
    return $sql;
}

/**
 * Cursor de la lista: el par (created_at, id) de la ultima fila mostrada, como texto "FECHA_ID" (el parametro `antes` de
 * la pagina, por ejemplo "1760000000_812") o como arreglo de dos enteros. Devuelve array(created_at, id), null si esta
 * vacio (primera pagina) o false si no es valido.
 */
function opg_notif_cursor($cursor)
{
    if ($cursor === null || $cursor === '') {
        return null;
    }
    if (is_string($cursor)) {
        if (!preg_match('/^([0-9]{1,10})_([0-9]{1,10})$/D', $cursor, $m)) {
            return false;
        }
        $cursor = array($m[1], $m[2]);
    }
    if (!is_array($cursor) || count($cursor) !== 2) {
        return false;
    }
    $cursor = array_values($cursor);
    $fecha = $cursor[0];
    if (is_string($fecha) && preg_match('/^[0-9]{1,10}$/D', $fecha)) {
        $fecha = (int) $fecha;
    }
    if (!is_int($fecha) || $fecha < 0 || $fecha > OPG_NOTIF_UID_MAX) {
        return false;
    }
    $id = opg_notif_uid($cursor[1]);
    return $id > 0 ? array($fecha, $id) : false;
}

/** Texto del cursor para pedir la pagina siguiente. */
function opg_notif_cursor_texto($fecha, $id)
{
    return (int) $fecha . '_' . (int) $id;
}

/** Filas por pagina: el valor indicado (1 a 100) o 30. */
function opg_notif_limite($limite)
{
    if (is_string($limite) && preg_match('/^[0-9]{1,6}$/D', $limite)) {
        $limite = (int) $limite;
    }
    if (!is_int($limite) || $limite < 1) {
        return OPG_NOTIF_LIMITE;
    }
    return min($limite, OPG_NOTIF_LIMITE_MAX);
}

/** Fila de la lista con los tipos convertidos y los datos decodificados (sin datos_json crudo ni clave de evento). */
function opg_notif_fila_lista($f)
{
    $datos = isset($f['datos_json']) && $f['datos_json'] !== null ? json_decode((string) $f['datos_json'], true) : null;
    return array(
        'id' => (int) $f['id'],
        'tipo' => (string) $f['tipo'],
        'categoria' => (string) $f['categoria'],
        'origen' => (string) $f['origen'],
        'importancia' => (string) $f['importancia'],
        'agrupable' => (int) $f['agrupable'] === 1,
        'actor_uid' => (int) $f['actor_uid'],
        'url' => $f['url'] === null ? null : (string) $f['url'],
        'cantidad' => (int) $f['cantidad'],
        'datos' => is_array($datos) ? $datos : array(),
        'created_at' => (int) $f['created_at'],
        'leida_en' => $f['leida_en'] === null ? null : (int) $f['leida_en'],
        'leida' => $f['leida_en'] !== null,
    );
}

/** Registra un error de lectura salvo el de tabla ausente (1146), que es un despliegue a medias y no se registra. */
function opg_notif_log_lectura($funcion, $e)
{
    if ((int) $e->getCode() !== 1146) {
        opg_notif_log($funcion . ': ' . $e->getMessage());
    }
}

// ── 3.2 Transaccion corta con reintentos ────────────────────────────────────────────────────────────────────────────

/**
 * Ejecuta $trabajo dentro de START TRANSACTION ... COMMIT. Si MySQL la revierte por interbloqueo (1213) o espera agotada
 * (1205), incluso en el COMMIT, se deshace entera (ROLLBACK) y se repite, hasta OPG_NOTIF_REINTENTOS veces, con una pausa
 * corta y aleatoria para que los dos procesos no vuelvan a chocar al mismo tiempo. Con cualquier otro error, o con los
 * reintentos agotados, se deshace, se registra y se da por fallida. Asi nunca queda la fila marcada sin su contador ni al
 * reves: los contadores quedan exactos aunque la operacion falle.
 *
 * Devuelve array(true, resultado del trabajo) o array(false, null). No lanza excepciones.
 */
function opg_notif_transaccion($funcion, $trabajo)
{
    for ($intento = 0; ; $intento++) {
        try {
            opg_notif_w('START TRANSACTION');
            $resultado = $trabajo();
            opg_notif_w('COMMIT');
            return array(true, $resultado);
        } catch (Throwable $e) {
            opg_notif_deshacer();
            $codigo = (int) $e->getCode();
            if (($codigo === 1213 || $codigo === 1205) && $intento < OPG_NOTIF_REINTENTOS) {
                usleep(mt_rand(1000, 10000) * ($intento + 1));
                continue;
            }
            if ($codigo !== 1146) {
                opg_notif_log($funcion . ': ' . $e->getMessage() . ($intento > 0 ? " (tras $intento reintentos)" : '') . '; no se cambio nada');
            }
            return array(false, null);
        }
    }
}

/** ROLLBACK que nunca falla (tras un 1213 MySQL ya revirtio, y el ROLLBACK no hace nada). */
function opg_notif_deshacer()
{
    global $db;
    try {
        $db->write_query('ROLLBACK', 1);
    } catch (Throwable $e) {
        // Nada mas que hacer: la conexion se cerrara con la transaccion revertida.
    }
}

/** SET de "marcar como vista": fecha de lectura y, en las agrupables, la clave liberada con el sufijo ":leida:ID". */
function opg_notif_sql_marcar($ahora)
{
    // LEFT(...) deja sitio al sufijo aunque la clave venga de fuera de opg_notificar (por ejemplo una copia del Tablon);
    // el id hace unica la clave resultante.
    return 'SET leida_en = ' . (int) $ahora . ', clave_evento = IF(agrupable = 1, CONCAT(LEFT(clave_evento, ' . OPG_NOTIF_MAX_CLAVE_AGRUPABLE
        . "), ':leida:', id), clave_evento)";
}

/** Resta $n no leidas y $imp importantes del contador, sin bajar de cero (las columnas son UNSIGNED: nunca se resta de mas). */
function opg_notif_restar_contador($uid, $n, $imp)
{
    opg_notif_w('UPDATE ' . opg_notif_t('op_notificaciones_contador') . ' SET sin_leer = sin_leer - LEAST(sin_leer, ' . (int) $n
        . '), importantes = importantes - LEAST(importantes, ' . (int) $imp . ') WHERE uid = ' . (int) $uid);
}

// ── 3.3 Contador ────────────────────────────────────────────────────────────────────────────────────────────────────

/** Cache por peticion de los contadores (requisito 32): uid => array('sin_leer' => n, 'importantes' => n). */
function &opg_notif_contadores_cache()
{
    static $cache = array();
    return $cache;
}

/** Olvida el contador cacheado de un uid (o de todos), para que la siguiente llamada a opg_notif_contar lo vuelva a leer. */
function opg_notif_contar_olvidar($uid = null)
{
    $cache = &opg_notif_contadores_cache();
    if ($uid === null) {
        $cache = array();
    } else {
        unset($cache[opg_notif_uid($uid)]);
    }
}

/**
 * No leidas del usuario: array('sin_leer' => n, 'importantes' => n) (requisitos 18 y 30 a 33).
 *
 * Una sola consulta por clave primaria del contador, cacheada por peticion (la segunda llamada no consulta). Se
 * autorrepara (208, apartado 5.5): si falta la fila o su ultima reconciliacion (`actualizado`) tiene mas de 24 horas,
 * llama a opg_notif_reconciliar, que recuenta y reescribe la fila; asi un desfase dura como mucho un dia y cada contador
 * se recuenta como mucho una vez al dia. Los envios y los marcados no tocan `actualizado`.
 *
 * Con un uid no valido, sin $db, con las tablas ausentes o ante un error devuelve ceros (el error se registra, salvo el de
 * tabla ausente), y ese valor tambien se cachea para no repetir la consulta en la misma peticion.
 */
function opg_notif_contar($uid)
{
    global $db;
    $vacio = array('sin_leer' => 0, 'importantes' => 0);
    $uid = opg_notif_uid($uid);
    if ($uid <= 0 || !is_object($db)) {
        return $vacio;
    }
    $cache = &opg_notif_contadores_cache();
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }
    try {
        $filas = opg_notif_filas('SELECT sin_leer, importantes, actualizado FROM ' . opg_notif_t('op_notificaciones_contador') . ' WHERE uid = ' . $uid);
    } catch (Throwable $e) {
        opg_notif_log_lectura('opg_notif_contar', $e);
        $cache[$uid] = $vacio;
        return $vacio;
    }
    if (!$filas || (int) $filas[0]['actualizado'] < opg_notif_ahora() - OPG_NOTIF_RECONCILIAR_CADA) {
        $r = opg_notif_reconciliar($uid);
        if ($r !== false) {
            return $r;
        }
    }
    $valor = $filas ? array('sin_leer' => max(0, (int) $filas[0]['sin_leer']), 'importantes' => max(0, (int) $filas[0]['importantes'])) : $vacio;
    $cache[$uid] = $valor;
    return $valor;
}

/**
 * Recuenta las no leidas del usuario y reescribe su contador (sin_leer, importantes y actualizado = ahora). Devuelve el
 * recuento (y lo deja en la cache de la peticion) o false si fallo.
 *
 * Como no pisa un incremento concurrente: en una transaccion corta, primero BLOQUEA la fila del contador
 * (SELECT ... FOR UPDATE) y despues cuenta las filas con una lectura sin bloqueo (la instantanea de la transaccion se toma
 * en ese COUNT, despues de tener el bloqueo). Un envio o un marcado escriben su fila antes que el contador:
 *  - si ya habian confirmado, el COUNT los ve y su cambio del contador ya estaba hecho: el valor nuevo los incluye;
 *  - si aun no habian confirmado, el COUNT no ve su fila y su cambio del contador espera al bloqueo: se aplica despues,
 *    sobre el valor recontado.
 * En los dos casos el resultado es exacto. Como el COUNT no bloquea filas, no se interbloquea con ellos.
 *
 * Limite conocido: un envio en autocommit (sin transaccion de quien llama) confirma la fila y el contador en dos
 * sentencias. Si la reconciliacion cuenta justo entre las dos, el contador queda una por encima (nunca por debajo) hasta
 * la siguiente reconciliacion. Con el envio dentro de una transaccion no ocurre.
 */
function opg_notif_reconciliar($uid)
{
    global $db;
    $uid = opg_notif_uid($uid);
    if ($uid <= 0 || !is_object($db)) {
        return false;
    }
    $ahora = opg_notif_ahora();
    list($ok, $valor) = opg_notif_transaccion('opg_notif_reconciliar', function () use ($uid, $ahora) {
        $C = opg_notif_t('op_notificaciones_contador');
        opg_notif_filas('SELECT uid FROM ' . $C . ' WHERE uid = ' . $uid . ' FOR UPDATE');
        $f = opg_notif_filas("SELECT COUNT(*) AS n, COALESCE(SUM(importancia = 'importante'), 0) AS i FROM " . opg_notif_t('op_notificaciones')
            . ' WHERE uid = ' . $uid . ' AND leida_en IS NULL');
        $n = $f ? (int) $f[0]['n'] : 0;
        $i = $f ? (int) $f[0]['i'] : 0;
        opg_notif_w('INSERT INTO ' . $C . ' (uid, sin_leer, importantes, actualizado) VALUES (' . $uid . ', ' . $n . ', ' . $i . ', ' . (int) $ahora . ')'
            . ' ON DUPLICATE KEY UPDATE sin_leer = VALUES(sin_leer), importantes = VALUES(importantes), actualizado = VALUES(actualizado)');
        return array('sin_leer' => $n, 'importantes' => $i);
    });
    $cache = &opg_notif_contadores_cache();
    if (!$ok) {
        unset($cache[$uid]);
        return false;
    }
    $cache[$uid] = $valor;
    return $valor;
}

// ── 3.4 Lista ───────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Una pagina de notificaciones del usuario (requisitos 13 y 34; 208 apartado 8).
 *
 * $filtros  'categoria', 'origen', 'estado' ('sin_leer' | 'leidas') e 'importancia', de las listas fijas
 *           (opg_notif_filtros). Vacio: todas.
 * $cursor   null para la primera pagina, o el 'cursor' que devolvio la pagina anterior ("FECHA_ID" o array(fecha, id)).
 * $limite   filas por pagina (30 por defecto, 100 como mucho).
 *
 * Una sola consulta, sin uniones, por los indices (uid, created_at, id) o sus equivalentes por categoria, origen y no
 * leidas, ordenada por created_at DESC, id DESC (una agrupable con actividad nueva sube al principio). Se pide una fila de
 * mas para saber si hay otra pagina, y el cursor es un rango sobre (created_at, id), estable aunque lleguen avisos nuevos.
 * Solo lee: abrir la lista no marca nada (requisito 15).
 *
 * Devuelve array('filas' => [...], 'hay_mas' => bool, 'cursor' => "FECHA_ID" de la ultima fila o null), con cada fila
 * como opg_notif_fila_lista. Un uid no valido o las tablas ausentes dan la lista vacia; un filtro o cursor no valido, o un
 * error de MySQL (registrado), dan false.
 */
function opg_notif_listar($uid, $filtros = array(), $cursor = null, $limite = OPG_NOTIF_LIMITE)
{
    global $db;
    $vacio = array('filas' => array(), 'hay_mas' => false, 'cursor' => null);
    $filtros = opg_notif_filtros($filtros, array('categoria', 'origen', 'estado', 'importancia'));
    $cursor = opg_notif_cursor($cursor);
    if ($filtros === null || $cursor === false) {
        return false;
    }
    $uid = opg_notif_uid($uid);
    if ($uid <= 0 || !is_object($db)) {
        return $vacio;
    }
    $limite = opg_notif_limite($limite);
    $sql = 'SELECT id, tipo, categoria, origen, importancia, agrupable, actor_uid, url, cantidad, datos_json, created_at, leida_en FROM '
        . opg_notif_t('op_notificaciones') . ' WHERE uid = ' . $uid . opg_notif_filtros_sql($filtros);
    if ($cursor !== null) {
        // (created_at, id) < (fecha, id) escrito como rango sobre created_at, que MySQL 5.7 si resuelve con el indice.
        $sql .= ' AND created_at <= ' . $cursor[0] . ' AND (created_at < ' . $cursor[0] . ' OR id < ' . $cursor[1] . ')';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . ($limite + 1);
    try {
        $filas = opg_notif_filas($sql);
    } catch (Throwable $e) {
        opg_notif_log_lectura('opg_notif_listar', $e);
        return (int) $e->getCode() === 1146 ? $vacio : false;
    }
    $hay_mas = count($filas) > $limite;
    $filas = array_map('opg_notif_fila_lista', array_slice($filas, 0, $limite));
    $ultima = $filas ? $filas[count($filas) - 1] : null;
    return array('filas' => $filas, 'hay_mas' => $hay_mas, 'cursor' => $hay_mas ? opg_notif_cursor_texto($ultima['created_at'], $ultima['id']) : null);
}

// ── 3.5 Marcar como vista ───────────────────────────────────────────────────────────────────────────────────────────

/**
 * Marca una notificacion del usuario como vista (208, apartado 5.4). Devuelve true solo si paso de no leida a leida.
 *
 * En una transaccion corta: el UPDATE solo afecta a la fila si es de $uid y no estaba leida (una ajena o ya leida da 0
 * filas y no se toca nada mas: pulsar Visto dos veces descuenta una sola vez). En las agrupables reescribe la clave a
 * "...:leida:ID", para que el siguiente evento del grupo cree una fila nueva. Despues lee la importancia por clave primaria
 * y resta uno del contador (y de importantes si lo era). Se reintenta ante 1213 y 1205 (ver opg_notif_transaccion).
 * Una notificacion leida no vuelve a no leida: no hay funcion para eso.
 */
function opg_notif_marcar_vista($uid, $id)
{
    global $db;
    $uid = opg_notif_uid($uid);
    $id = opg_notif_uid($id);
    if ($uid <= 0 || $id <= 0 || !is_object($db)) {
        return false;
    }
    $ahora = opg_notif_ahora();
    list($ok, $cambio) = opg_notif_transaccion('opg_notif_marcar_vista', function () use ($db, $uid, $id, $ahora) {
        $N = opg_notif_t('op_notificaciones');
        opg_notif_w('UPDATE ' . $N . ' ' . opg_notif_sql_marcar($ahora) . ' WHERE id = ' . $id . ' AND uid = ' . $uid . ' AND leida_en IS NULL');
        if ((int) $db->affected_rows() !== 1) {
            return false;
        }
        $f = opg_notif_filas('SELECT importancia FROM ' . $N . ' WHERE id = ' . $id);
        opg_notif_restar_contador($uid, 1, ($f && $f[0]['importancia'] === 'importante') ? 1 : 0);
        return true;
    });
    opg_notif_contar_olvidar($uid);
    return $ok && $cambio === true;
}

/**
 * Marca como vistas todas las no leidas del usuario, o solo las del filtro activo ('categoria' y 'origen', de las listas
 * fijas; cualquier otra clave o valor no valido devuelve false sin marcar nada). Devuelve cuantas marco o false.
 *
 * En una transaccion corta: cuenta (y bloquea, FOR UPDATE) las no leidas del filtro y cuantas son importantes, las marca
 * con el mismo UPDATE que opg_notif_marcar_vista y resta del contador las filas afectadas y las importantes. El bloqueo
 * hace que el recuento y el UPDATE vean las mismas filas aunque otro proceso envie a la vez. Se reintenta ante 1213 y 1205.
 */
function opg_notif_marcar_todas($uid, $filtros = array())
{
    global $db;
    $uid = opg_notif_uid($uid);
    $filtros = opg_notif_filtros($filtros, array('categoria', 'origen'));
    if ($uid <= 0 || $filtros === null || !is_object($db)) {
        return false;
    }
    $ahora = opg_notif_ahora();
    list($ok, $marcadas) = opg_notif_transaccion('opg_notif_marcar_todas', function () use ($db, $uid, $filtros, $ahora) {
        $N = opg_notif_t('op_notificaciones');
        $donde = ' WHERE uid = ' . $uid . ' AND leida_en IS NULL' . opg_notif_filtros_sql($filtros);
        $f = opg_notif_filas("SELECT COUNT(*) AS n, COALESCE(SUM(importancia = 'importante'), 0) AS i FROM " . $N . $donde . ' FOR UPDATE');
        $n = $f ? (int) $f[0]['n'] : 0;
        $imp = $f ? (int) $f[0]['i'] : 0;
        if ($n === 0) {
            return 0;
        }
        opg_notif_w('UPDATE ' . $N . ' ' . opg_notif_sql_marcar($ahora) . $donde);
        $afectadas = (int) $db->affected_rows();
        if ($afectadas !== $n) {
            // No deberia pasar (las filas estan bloqueadas): se resta lo realmente marcado y lo corrige la reconciliacion.
            opg_notif_log("opg_notif_marcar_todas: se contaron $n no leidas y se marcaron $afectadas (uid $uid)");
            $afectadas = max(0, $afectadas);
            $imp = min($imp, $afectadas);
        }
        if ($afectadas > 0) {
            opg_notif_restar_contador($uid, $afectadas, $imp);
        }
        return $afectadas;
    });
    opg_notif_contar_olvidar($uid);
    return $ok ? (int) $marcadas : false;
}
