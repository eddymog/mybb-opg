<?php
/**
 * Tablón de aventuras: biblioteca de dominio.
 *
 * Única fuente de las reglas de las solicitudes de aventura, compartida por
 * op/tablon_de_aventuras.php (jugadores) y op/staff/consola_aventuras.php (gestión).
 * Ninguna página escribe SQL de negocio.
 *
 * Documentos: docs/101_Requirements_Registro.md y docs/201_DesignPlan_Registro.md.
 *
 * Convenciones:
 *  - Código compatible con PHP 7.4.
 *  - Nada hace exit ni escribe salida HTTP: las funciones devuelven arreglos con
 *    'ok' y, si fallan, 'codigo' (HTTP sugerido) y 'errores' (lista de mensajes).
 *  - Todo valor que llega a SQL pasa por opg_av_sql() o por un cast entero.
 *  - Las tablas se nombran con TABLE_PREFIX, nunca con "mybb_" escrito a mano.
 *  - Requiere op/functions/op_functions.php (is_staff, is_narra, is_mod,
 *    does_ficha_exist, log_audit) ya cargado por la página.
 */

// ── Constantes ───────────────────────────────────────────────────────────────

const OPG_AV_ANO = 725;
const OPG_AV_ESTACION = 'Verano';
const OPG_AV_DIAS = 90;                   // igual a CRON_DIAS en op/cronologia.php
const OPG_AV_ISLA_PADRES = array(15, 16, 17, 18);
const OPG_AV_MAX_COMENTARIO = 1000;
const OPG_AV_MAX_NOTA_STAFF = 200;
const OPG_AV_MAX_MOTIVO = 500;
const OPG_AV_MAX_IDEA = 500;
const OPG_AV_MAX_MENSAJE_OFERTA = 200;
const OPG_AV_MAX_DISCORD = 64;
const OPG_AV_MAX_MOTIVO_OFERTA = 200;
const OPG_AV_MAX_SOLITARIAS = 3;
const OPG_AV_MAX_TOTAL = 4;
const OPG_AV_ESTADOS_ACTIVOS = array(0, 1, 4);
const OPG_AV_ESTADOS_EDITABLES = array(0, 4);
const OPG_AV_POR_PAGINA = 20;
const OPG_AV_POR_PAGINA_SIN_NARRADOR = 10;
const OPG_AV_OFERTA_DIAS = 30;
const OPG_AV_TIER_MIN = 1;
const OPG_AV_TIER_MAX = 10;
const OPG_AV_ANTIRREBOTE_SEG = 60;
const OPG_AV_HOSTS_EXTRA = array();       // hosts de enlace aceptados además del foro

// ── Utilidades de SQL ────────────────────────────────────────────────────────

/** Nombre de tabla con prefijo y comillas: opg_av_t('users') => `mybb_users`. */
function opg_av_t($nombre)
{
    return '`' . TABLE_PREFIX . $nombre . '`';
}

/** Valor literal para SQL: NULL, número o cadena escapada. */
function opg_av_sql($valor)
{
    global $db;
    if ($valor === null) {
        return 'NULL';
    }
    if (is_bool($valor)) {
        return $valor ? '1' : '0';
    }
    if (is_int($valor)) {
        return (string) $valor;
    }
    if (is_float($valor)) {
        return rtrim(rtrim(sprintf('%.4F', $valor), '0'), '.');
    }
    return "'" . $db->escape_string((string) $valor) . "'";
}

/**
 * Ejecuta una consulta de lectura sin que MyBB haga exit ante un error. Dentro de
 * una transacción usa la conexión de escritura, para que SELECT ... FOR UPDATE y
 * las lecturas posteriores vean la misma sesión aunque exista una réplica de lectura.
 */
function opg_av_q($sql)
{
    global $db;
    return $db->query($sql, 1, !empty($GLOBALS['opg_av_en_transaccion']) ? 1 : 0);
}

/** Ejecuta una consulta de escritura sin que MyBB haga exit ante un error. */
function opg_av_w($sql)
{
    global $db;
    return $db->write_query($sql, 1);
}

function opg_av_error_sql()
{
    global $db;
    return $db->error_string();
}

/** Primera fila de una consulta o null. */
function opg_av_uno($sql)
{
    global $db;
    $q = opg_av_q($sql);
    if (!$q) {
        return null;
    }
    $fila = $db->fetch_array($q);
    return $fila ? $fila : null;
}

/** Todas las filas de una consulta. */
function opg_av_todos($sql)
{
    global $db;
    $filas = array();
    $q = opg_av_q($sql);
    if (!$q) {
        return $filas;
    }
    while ($f = $db->fetch_array($q)) {
        $filas[] = $f;
    }
    return $filas;
}

function opg_av_afectadas()
{
    global $db;
    return (int) $db->affected_rows();
}

function opg_av_ahora()
{
    return defined('TIME_NOW') ? TIME_NOW : time();
}

function opg_av_iniciar()
{
    opg_av_w('START TRANSACTION');
    $GLOBALS['opg_av_en_transaccion'] = true;
}

function opg_av_confirmar()
{
    opg_av_w('COMMIT');
    $GLOBALS['opg_av_en_transaccion'] = false;
}

function opg_av_revertir()
{
    opg_av_w('ROLLBACK');
    $GLOBALS['opg_av_en_transaccion'] = false;
}

/** Resultado de error uniforme. */
function opg_av_fallo($codigo, $errores)
{
    return array('ok' => false, 'codigo' => (int) $codigo, 'errores' => (array) $errores);
}

/** Lista de enteros positivos únicos, conservando el orden. */
function opg_av_enteros($valor)
{
    $salida = array();
    if (!is_array($valor)) {
        return $salida;
    }
    foreach ($valor as $v) {
        if (is_int($v) || (is_string($v) && ctype_digit($v))) {
            $n = (int) $v;
            if ($n > 0 && !in_array($n, $salida, true)) {
                $salida[] = $n;
            }
        }
    }
    return $salida;
}

function opg_av_longitud($texto)
{
    return function_exists('mb_strlen') ? mb_strlen((string) $texto, 'UTF-8') : strlen((string) $texto);
}

/** Recorta y valida un texto libre. Devuelve array(texto, error|null). */
function opg_av_texto($valor, $maximo, $nombre, $obligatorio = false)
{
    $t = trim((string) $valor);
    $t = str_replace("\r\n", "\n", $t);
    if ($obligatorio && $t === '') {
        return array('', 'Falta ' . $nombre . '.');
    }
    if (opg_av_longitud($t) > $maximo) {
        return array($t, ucfirst($nombre) . ' supera los ' . $maximo . ' caracteres.');
    }
    return array($t, null);
}

// ── Estados y transiciones ───────────────────────────────────────────────────

function opg_av_estados()
{
    return array(
        0 => array('clave' => 'creado', 'etiqueta' => 'Creado'),
        1 => array('clave' => 'aprobado', 'etiqueta' => 'Aprobado'),
        2 => array('clave' => 'denegado', 'etiqueta' => 'Denegado'),
        3 => array('clave' => 'finalizado', 'etiqueta' => 'Finalizado'),
        4 => array('clave' => 'pendiente-narrador', 'etiqueta' => 'Pendiente narrador'),
        5 => array('clave' => 'pendiente-borrado', 'etiqueta' => 'Pendiente de borrado'),
    );
}

/** Código de estado desde su clave, o null si no existe. */
function opg_av_estado_desde_clave($clave)
{
    foreach (opg_av_estados() as $codigo => $info) {
        if ($info['clave'] === $clave) {
            return $codigo;
        }
    }
    return null;
}

/**
 * Transiciones permitidas entre estados (requisitos 4.1). Quién y por qué puede
 * hacerla lo decide cada función de escritura; esta tabla solo descarta saltos
 * imposibles.
 */
function opg_av_transicion_valida($de, $a)
{
    $mapa = array(
        0 => array(1, 2, 4, 5),
        1 => array(2, 3, 4, 5),
        2 => array(5),
        3 => array(5),
        4 => array(0, 2, 5),
        5 => array(),
    );
    return isset($mapa[(int) $de]) && in_array((int) $a, $mapa[(int) $de], true);
}

// ── Permisos ─────────────────────────────────────────────────────────────────

function opg_av_puede_gestionar($uid)
{
    $uid = (int) $uid;
    return $uid > 0 && (is_mod($uid) || is_staff($uid) || is_narra($uid));
}

function opg_av_puede_eliminar($uid)
{
    $uid = (int) $uid;
    return $uid > 0 && is_staff($uid);
}

/** El usuario es creador, narrador o participante de la solicitud. */
function opg_av_es_parte($av, $uid)
{
    $uid = (int) $uid;
    if ($uid <= 0) {
        return false;
    }
    if ((int) $av['uid'] === $uid || (int) $av['narrador_uid'] === $uid) {
        return true;
    }
    foreach ($av['jugadores'] as $j) {
        if ((int) $j['uid'] === $uid) {
            return true;
        }
    }
    return false;
}

/** Vincular requiere participar o narrar una solicitud aprobada. */
function opg_av_puede_vincular($av, $uid)
{
    $uid = (int) $uid;
    if ($uid <= 0 || (int) $av['estado'] !== 1) {
        return false;
    }
    if ((int) $av['narrador_uid'] === $uid) {
        return true;
    }
    foreach ($av['jugadores'] as $j) {
        if ((int) $j['uid'] === $uid) {
            return true;
        }
    }
    return false;
}

/** Gestion y no ser parte de la solicitud. */
function opg_av_puede_resolver($av, $uid)
{
    return opg_av_puede_gestionar($uid) && !opg_av_es_parte($av, $uid);
}

function opg_av_puede_editar($av, $uid)
{
    return (int) $uid > 0
        && (int) $av['uid'] === (int) $uid
        && in_array((int) $av['estado'], OPG_AV_ESTADOS_EDITABLES, true);
}

// ── Fichas ───────────────────────────────────────────────────────────────────

/** Fichas aprobadas por fid: array(fid => array(fid, nombre, apodo)). */
function opg_av_fichas_por_fids($fids)
{
    $fids = opg_av_enteros($fids);
    $mapa = array();
    if (!$fids) {
        return $mapa;
    }
    $filas = opg_av_todos(
        'SELECT fid, nombre, apodo FROM ' . opg_av_t('op_fichas')
        . " WHERE fid IN (" . implode(',', $fids) . ") AND aprobada_por <> 'sin_aprobar'"
    );
    foreach ($filas as $f) {
        $mapa[(int) $f['fid']] = array('fid' => (int) $f['fid'], 'nombre' => (string) $f['nombre'], 'apodo' => (string) $f['apodo']);
    }
    return $mapa;
}

/** Ficha aprobada de un usuario o null. */
function opg_av_ficha($fid)
{
    $mapa = opg_av_fichas_por_fids(array((int) $fid));
    return isset($mapa[(int) $fid]) ? $mapa[(int) $fid] : null;
}

/** Buscador de fichas por nombre o apodo (máximo 20). */
function opg_av_fichas_buscar($texto)
{
    global $db;
    $texto = trim((string) $texto);
    if ($texto === '') {
        return array();
    }
    $like = $db->escape_string_like($texto);
    $filas = opg_av_todos(
        'SELECT fid, nombre, apodo FROM ' . opg_av_t('op_fichas')
        . " WHERE aprobada_por <> 'sin_aprobar' AND (nombre LIKE '%" . $like . "%' OR apodo LIKE '%" . $like . "%')"
        . ' ORDER BY nombre ASC LIMIT 20'
    );
    $salida = array();
    foreach ($filas as $f) {
        $salida[] = array('fid' => (int) $f['fid'], 'nombre' => (string) $f['nombre'], 'apodo' => (string) $f['apodo']);
    }
    return $salida;
}

// ── Isla, fecha y tier ───────────────────────────────────────────────────────

/** Foros hijos directos de 15, 16, 17 y 18 (los subforos no cuentan). */
function opg_av_islas_buscar($texto)
{
    global $db;
    $texto = trim((string) $texto);
    $cond = 'pid IN (' . implode(',', OPG_AV_ISLA_PADRES) . ') AND active = 1';
    if ($texto !== '') {
        $cond .= " AND name LIKE '%" . $db->escape_string_like($texto) . "%'";
    }
    $filas = opg_av_todos('SELECT fid, name FROM ' . opg_av_t('forums') . ' WHERE ' . $cond . ' ORDER BY disporder ASC, name ASC LIMIT 30');
    $salida = array();
    foreach ($filas as $f) {
        $salida[] = array('fid' => (int) $f['fid'], 'nombre' => (string) $f['name']);
    }
    return $salida;
}

/** Isla válida (foro activo cuyo padre directo es 15 a 18) o null. */
function opg_av_isla_valida($fid)
{
    $fid = (int) $fid;
    if ($fid <= 0) {
        return null;
    }
    $f = opg_av_uno(
        'SELECT fid, name FROM ' . opg_av_t('forums')
        . ' WHERE fid = ' . $fid . ' AND pid IN (' . implode(',', OPG_AV_ISLA_PADRES) . ') AND active = 1'
    );
    return $f ? array('fid' => (int) $f['fid'], 'nombre' => (string) $f['name']) : null;
}

/** Día de la estación: entero de 1 a OPG_AV_DIAS, o null si no es válido. */
function opg_av_validar_dia($valor)
{
    if (is_int($valor)) {
        $n = $valor;
    } elseif (is_string($valor) && ctype_digit(trim($valor))) {
        $n = (int) trim($valor);
    } else {
        return null;
    }
    return ($n >= 1 && $n <= OPG_AV_DIAS) ? $n : null;
}

/** Tier de la aventura: entero de 1 a 10, o null si no es válido. */
function opg_av_validar_tier($valor)
{
    if (is_int($valor)) {
        $n = $valor;
    } elseif (is_string($valor) && ctype_digit(trim($valor))) {
        $n = (int) trim($valor);
    } else {
        return null;
    }
    return ($n >= OPG_AV_TIER_MIN && $n <= OPG_AV_TIER_MAX) ? $n : null;
}

/** Posts esperados por participante según el tier (usa la biblioteca de recompensas si está cargada). */
function opg_av_posts_por_tier($tier)
{
    $tabla = defined('OPG_REC_POSTS_TIER') ? OPG_REC_POSTS_TIER
        : array(1 => 4, 2 => 5, 3 => 6, 4 => 7, 5 => 8, 6 => 9, 7 => 10, 8 => 10, 9 => 11, 10 => 12);
    return isset($tabla[(int) $tier]) ? $tabla[(int) $tier] : 0;
}

function opg_av_fecha_texto($dia)
{
    return $dia ? (OPG_AV_ESTACION . ' ' . OPG_AV_ANO . ', día ' . (int) $dia) : null;
}

// ── Lectura y normalización ─────────────────────────────────────────────────

/** Expresión SQL: el usuario figura en jugadores_json (tolera JSON inválido). */
function opg_av_sql_participa($uid, $alias = 'pa')
{
    $uid = (int) $uid;
    $c = $alias . '.jugadores_json';
    return 'CASE WHEN JSON_VALID(' . $c . ') THEN JSON_CONTAINS(' . $c . ", JSON_OBJECT('uid', " . $uid . ')) ELSE 0 END';
}

/** Predicado "mía": creador, narrador o participante. */
function opg_av_sql_mia($uid, $alias = 'pa')
{
    $uid = (int) $uid;
    return '(' . $alias . '.uid = ' . $uid . ' OR ' . $alias . '.narrador_uid = ' . $uid . ' OR ' . opg_av_sql_participa($uid, $alias) . ')';
}

/** Negación de "mía", cuidando el NULL de narrador_uid. */
function opg_av_sql_no_mia($uid, $alias = 'pa')
{
    $uid = (int) $uid;
    return '(' . $alias . '.uid <> ' . $uid . ' AND (' . $alias . '.narrador_uid IS NULL OR ' . $alias . '.narrador_uid <> ' . $uid . ') AND NOT ' . opg_av_sql_participa($uid, $alias) . ')';
}

/** SELECT común con los datos asociados (creador, isla, entrega, meta). */
function opg_av_select_base()
{
    return 'SELECT pa.*, u.username AS creador_nombre, f.name AS isla_nombre,'
        . ' e.estado AS entrega_estado, e.motivo_rechazo AS entrega_motivo,'
        . ' e.entregado_at AS entrega_entregado_at, e.intentos AS entrega_intentos,'
        . ' m.public_comment AS meta_public_comment, m.staff_note AS meta_staff_note'
        . ' FROM ' . opg_av_t('op_peticionAventuras') . ' pa'
        . ' LEFT JOIN ' . opg_av_t('users') . ' u ON u.uid = pa.uid'
        . ' LEFT JOIN ' . opg_av_t('forums') . ' f ON f.fid = pa.isla_fid'
        . ' LEFT JOIN ' . opg_av_t('op_aventuras_entregas') . ' e ON e.peticion_id = pa.id'
        . ' LEFT JOIN ' . opg_av_t('op_peticionAventuras_meta') . ' m ON m.peticion_id = pa.id';
}

/** Fila cruda de una solicitud; con $bloquear usa SELECT ... FOR UPDATE. */
function opg_av_fila($id, $bloquear = false)
{
    return opg_av_uno(
        'SELECT * FROM ' . opg_av_t('op_peticionAventuras') . ' WHERE id = ' . (int) $id . ' LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '')
    );
}

/** tid de una URL guardada (showthread.php?tid=N) o 0. */
function opg_av_tid_de_url($url)
{
    if (preg_match('/[?&]tid=(\d+)/', (string) $url, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/** Participantes de jugadores_json: lista de array(uid|null, nombre). Ignora el nivel. */
function opg_av_decodificar_jugadores($json)
{
    $lista = json_decode((string) $json, true);
    $salida = array();
    if (!is_array($lista)) {
        return $salida;
    }
    foreach ($lista as $j) {
        if (!is_array($j)) {
            continue;
        }
        $uid = isset($j['uid']) ? (int) $j['uid'] : 0;
        $salida[] = array(
            'uid' => $uid > 0 ? $uid : null,
            'nombre' => isset($j['nombre']) ? trim((string) $j['nombre']) : '',
        );
    }
    return $salida;
}

/**
 * Normaliza una fila de opg_av_select_base() (o de opg_av_fila) al arreglo que
 * consumen las vistas. Es el único lugar que conoce el formato antiguo.
 */
function opg_av_normalizar_fila($fila, $es_consola = false)
{
    $estados = opg_av_estados();
    $estado = (int) $fila['estado'];
    $info = isset($estados[$estado]) ? $estados[$estado] : $estados[0];

    $comentario = trim((string) (isset($fila['comentario_publico']) ? $fila['comentario_publico'] : ''));
    if ($comentario === '' && isset($fila['meta_public_comment'])) {
        $comentario = trim((string) $fila['meta_public_comment']);
    }

    $dia = isset($fila['dia']) && $fila['dia'] !== null ? (int) $fila['dia'] : 0;
    $isla_fid = isset($fila['isla_fid']) && $fila['isla_fid'] !== null ? (int) $fila['isla_fid'] : 0;
    $tier = isset($fila['tier_seleccionado']) ? (int) $fila['tier_seleccionado'] : 0;

    $motivo_denegacion = isset($fila['motivo_denegacion']) ? trim((string) $fila['motivo_denegacion']) : '';
    if ($estado === 2 && $motivo_denegacion === '') {
        $motivo_denegacion = 'Sin motivo registrado';
    }
    $motivo_renuncia = ($estado === 4 && isset($fila['motivo_renuncia'])) ? trim((string) $fila['motivo_renuncia']) : '';

    $av = array(
        'id' => (int) $fila['id'],
        'uid' => (int) $fila['uid'],
        'creador_nombre' => isset($fila['creador_nombre']) ? (string) $fila['creador_nombre'] : '',
        'narrador_uid' => isset($fila['narrador_uid']) && $fila['narrador_uid'] !== null ? (int) $fila['narrador_uid'] : 0,
        'narrador_nombre' => isset($fila['narrador_nombre']) ? (string) $fila['narrador_nombre'] : '',
        'jugadores' => opg_av_decodificar_jugadores(isset($fila['jugadores_json']) ? $fila['jugadores_json'] : ''),
        'estado' => $estado,
        'estado_clave' => $info['clave'],
        'estado_etiqueta' => $info['etiqueta'],
        'isla' => $isla_fid > 0 ? array('fid' => $isla_fid, 'nombre' => isset($fila['isla_nombre']) ? (string) $fila['isla_nombre'] : '') : null,
        'dia' => $dia > 0 ? $dia : null,
        'fecha_texto' => opg_av_fecha_texto($dia),
        'tier' => ($tier >= OPG_AV_TIER_MIN && $tier <= OPG_AV_TIER_MAX) ? $tier : null,
        'inframundo' => !empty($fila['inframundo']),
        'comentario_publico' => $comentario,
        'idea_narrador' => isset($fila['idea_narrador']) ? trim((string) $fila['idea_narrador']) : '',
        'motivo_denegacion' => $motivo_denegacion,
        'motivo_renuncia' => $motivo_renuncia,
        'aventura_url' => isset($fila['aventura_url']) ? (string) $fila['aventura_url'] : '',
        'tid_vinculado' => opg_av_tid_de_url(isset($fila['aventura_url']) ? $fila['aventura_url'] : ''),
        'created_at' => (int) $fila['created_at'],
        'editado_at' => isset($fila['editado_at']) && $fila['editado_at'] !== null ? (int) $fila['editado_at'] : 0,
        'entrega' => null,
    );

    if (isset($fila['entrega_estado']) && $fila['entrega_estado'] !== null) {
        $av['entrega'] = array(
            'estado' => (string) $fila['entrega_estado'],
            'motivo_rechazo' => isset($fila['entrega_motivo']) ? (string) $fila['entrega_motivo'] : '',
            'entregado_at' => isset($fila['entrega_entregado_at']) ? (int) $fila['entrega_entregado_at'] : 0,
            'intentos' => isset($fila['entrega_intentos']) ? (int) $fila['entrega_intentos'] : 1,
        );
    }

    if ($es_consola) {
        $nota = isset($fila['comentario_staff']) ? trim((string) $fila['comentario_staff']) : '';
        if ($nota === '' && isset($fila['meta_staff_note'])) {
            $nota = trim((string) $fila['meta_staff_note']);
        }
        $av['nota_staff'] = $nota;
    }
    return $av;
}

/** Una solicitud normalizada por id, o null. */
function opg_av_obtener($id, $es_consola = false)
{
    $fila = opg_av_uno(opg_av_select_base() . ' WHERE pa.id = ' . (int) $id . ' LIMIT 1');
    return $fila ? opg_av_normalizar_fila($fila, $es_consola) : null;
}

/**
 * Condiciones SQL (sobre el alias pa) de un listado.
 *
 * $filtros: yo (uid de quien consulta), ambito (mias|otras), estado (clave),
 * rol (narrador|jugador, solo mias), uid (narrador o participante, solo otras o consola),
 * q (número de solicitud en Mis; número o nombre de ficha en Otras).
 */
function opg_av_condiciones_listado($filtros, $es_consola)
{
    $c = array();
    $yo = isset($filtros['yo']) ? (int) $filtros['yo'] : 0;
    $estado = null;
    if (!empty($filtros['estado'])) {
        $estado = opg_av_estado_desde_clave((string) $filtros['estado']);
    }

    if ($es_consola) {
        if ($estado !== null) {
            $c[] = 'pa.estado = ' . $estado;
        }
        if (!empty($filtros['uid'])) {
            $u = (int) $filtros['uid'];
            $c[] = '(pa.narrador_uid = ' . $u . ' OR ' . opg_av_sql_participa($u) . ')';
        }
        return $c;
    }

    $ambito = (isset($filtros['ambito']) && $filtros['ambito'] === 'otras') ? 'otras' : 'mias';
    $busqueda = isset($filtros['q']) ? trim((string) $filtros['q']) : '';
    $id_buscado = 0;
    if (preg_match('/^#?(\\d+)$/', $busqueda, $coincide)) {
        $id_buscado = (int) $coincide[1];
    }
    if ($ambito === 'mias') {
        $c[] = 'pa.estado <> 5';
        $c[] = opg_av_sql_mia($yo);
        if ($estado !== null && $estado !== 5) {
            $c[] = 'pa.estado = ' . $estado;
        }
        if (isset($filtros['rol']) && $filtros['rol'] === 'narrador') {
            $c[] = 'pa.narrador_uid = ' . $yo;
        } elseif (isset($filtros['rol']) && $filtros['rol'] === 'jugador') {
            $c[] = opg_av_sql_participa($yo);
        }
        if ($busqueda !== '') {
            $c[] = $id_buscado > 0 ? 'pa.id = ' . $id_buscado : '1 = 0';
        }
    } else {
        // Las pendientes de narrador tienen su bloque propio; el estado 5 no se muestra.
        $c[] = 'pa.estado IN (0,1,2,3)';
        $c[] = opg_av_sql_no_mia($yo);
        if ($estado !== null && in_array($estado, array(0, 1, 2, 3), true)) {
            $c[] = 'pa.estado = ' . $estado;
        }
        if (!empty($filtros['uid'])) {
            $u = (int) $filtros['uid'];
            $c[] = '(pa.narrador_uid = ' . $u . ' OR ' . opg_av_sql_participa($u) . ')';
        }
        if ($busqueda !== '') {
            if ($id_buscado > 0) {
                $c[] = 'pa.id = ' . $id_buscado;
            } else {
                $fichas = opg_av_fichas_buscar($busqueda);
                $fids = array();
                foreach ($fichas as $ficha) {
                    $fids[] = (int) $ficha['fid'];
                }
                if (!$fids) {
                    $c[] = '1 = 0';
                } else {
                    $por_ficha = array();
                    foreach (array_unique($fids) as $fid) {
                        $por_ficha[] = '(pa.narrador_uid = ' . $fid . ' OR ' . opg_av_sql_participa($fid) . ')';
                    }
                    $c[] = '(' . implode(' OR ', $por_ficha) . ')';
                }
            }
        }
    }
    return $c;
}

/** Agregados sobre el conjunto filtrado completo, sin paginacion. */
function opg_av_estadisticas($filtros)
{
    $where = opg_av_condiciones_listado($filtros, false);
    $uid = !empty($filtros['uid']) && $filtros['ambito'] === 'otras'
        ? (int) $filtros['uid'] : (int) $filtros['yo'];
    $campos = array('COUNT(*) AS total');
    foreach (array(0, 1, 2, 3, 4) as $estado) {
        $campos[] = 'COALESCE(SUM(pa.estado = ' . $estado . '), 0) AS estado_' . $estado;
    }
    $campos[] = 'COALESCE(SUM(pa.narrador_uid = ' . $uid . '), 0) AS narrador';
    $campos[] = 'COALESCE(SUM(' . opg_av_sql_participa($uid) . '), 0) AS jugador';
    $fila = opg_av_uno('SELECT ' . implode(', ', $campos) . ' FROM '
        . opg_av_t('op_peticionAventuras') . ' pa WHERE ' . implode(' AND ', $where));
    return array_map('intval', $fila ?: array_fill_keys(array('total', 'estado_0', 'estado_1', 'estado_2', 'estado_3', 'estado_4', 'narrador', 'jugador'), 0));
}

function opg_av_activas_listar($uid)
{
    $filas = opg_av_todos(opg_av_select_base()
        . ' WHERE pa.estado IN (0,1,4) AND (' . opg_av_sql_participa($uid)
        . ' OR pa.narrador_fid = ' . (int) $uid . ')'
        . ' ORDER BY pa.created_at DESC, pa.id DESC');
    $items = array_map('opg_av_normalizar_fila', $filas);
    $tids = array();
    foreach ($items as $av) {
        $tid = $av['tid_vinculado'] ?: opg_av_tid_de_entrada($av['aventura_url']);
        if ($tid > 0) {
            $tids[] = $tid;
        }
    }
    $temas = array();
    if ($tids) {
        $condicion = ' WHERE visible = 1 AND tid IN (' . implode(',', array_unique($tids)) . ')';
        if (function_exists('opg_ent_foros_inaccesibles')) {
            $ocultos = opg_ent_foros_inaccesibles();
            if ($ocultos) {
                $condicion .= ' AND fid NOT IN (' . implode(',', $ocultos) . ')';
            }
        }
        foreach (opg_av_todos('SELECT tid, subject FROM ' . opg_av_t('threads') . $condicion) as $tema) {
            $temas[(int) $tema['tid']] = (string) $tema['subject'];
        }
    }
    foreach ($items as &$av) {
        $av['es_narrador'] = (int) $av['narrador_uid'] === (int) $uid;
        $tid = $av['tid_vinculado'] ?: opg_av_tid_de_entrada($av['aventura_url']);
        $av['aventura_nombre'] = isset($temas[$tid]) ? $temas[$tid] : '';
    }
    unset($av);
    return $items;
}

/** Listado paginado; solo la consola recibe notas de staff. */
function opg_av_listar($filtros, $pagina, $es_consola = false)
{
    $pagina = max(1, (int) $pagina);
    $where = opg_av_condiciones_listado($filtros, $es_consola);
    $condicion = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $fila = opg_av_uno('SELECT COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras') . ' pa' . $condicion);
    $total = $fila ? (int) $fila['n'] : 0;
    $paginas = max(1, (int) ceil($total / OPG_AV_POR_PAGINA));
    $pagina = min($pagina, $paginas);
    $offset = ($pagina - 1) * OPG_AV_POR_PAGINA;

    $filas = opg_av_todos(
        opg_av_select_base() . $condicion
        . ' ORDER BY pa.created_at DESC, pa.id DESC LIMIT ' . OPG_AV_POR_PAGINA . ' OFFSET ' . $offset
    );
    $items = array();
    foreach ($filas as $f) {
        $items[] = opg_av_normalizar_fila($f, $es_consola);
    }
    return array('items' => $items, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas);
}

/**
 * Página (1, 2, ...) de un listado en la que cae una solicitud, para abrir un
 * enlace ?id=N en la página correcta. Devuelve 1 si no figura en el listado.
 */
function opg_av_pagina_de($id, $filtros, $es_consola = false)
{
    $fila = opg_av_uno('SELECT id, created_at FROM ' . opg_av_t('op_peticionAventuras') . ' WHERE id = ' . (int) $id . ' LIMIT 1');
    if (!$fila) {
        return 1;
    }
    $where = opg_av_condiciones_listado($filtros, $es_consola);
    $where[] = '(pa.created_at > ' . (int) $fila['created_at'] . ' OR (pa.created_at = ' . (int) $fila['created_at'] . ' AND pa.id > ' . (int) $fila['id'] . '))';
    $f = opg_av_uno('SELECT COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras') . ' pa WHERE ' . implode(' AND ', $where));
    $antes = $f ? (int) $f['n'] : 0;
    return (int) floor($antes / OPG_AV_POR_PAGINA) + 1;
}

/** ¿Tiene el usuario alguna aventura (creador, narrador o participante), sin contar las pendientes de borrado? */
function opg_av_tiene_aventuras($uid)
{
    $fila = opg_av_uno(
        'SELECT COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras') . ' pa WHERE pa.estado <> 5 AND ' . opg_av_sql_mia($uid)
    );
    return $fila && (int) $fila['n'] > 0;
}

// ── Límites de aventuras activas ────────────────────────────────────────────

/**
 * Aventuras activas de un jugador, sin contar $excluir_id ni las que narra.
 * Devuelve array(solitarias, total).
 */
function opg_av_contar_activas($fid, $excluir_id = 0)
{
    $fid = (int) $fid;
    $filas = opg_av_todos(
        'SELECT id, jugadores_json, narrador_fid FROM ' . opg_av_t('op_peticionAventuras') . ' pa'
        . ' WHERE pa.estado IN (' . implode(',', OPG_AV_ESTADOS_ACTIVOS) . ')'
        . ' AND pa.id <> ' . (int) $excluir_id
        . ' AND ' . opg_av_sql_participa($fid)
    );
    $solitarias = 0;
    $total = 0;
    foreach ($filas as $f) {
        if ((int) $f['narrador_fid'] === $fid) {
            continue;
        }
        $lista = json_decode((string) $f['jugadores_json'], true);
        if (!is_array($lista)) {
            continue;
        }
        $total++;
        if (count($lista) === 1) {
            $solitarias++;
        }
    }
    return array($solitarias, $total);
}

/**
 * Jugadores que excederían un límite si se aprueba la solicitud $av
 * (normalizada). Lista de array(fid, nombre, solitarias, total, motivo).
 */
function opg_av_validar_limites($av)
{
    $excedidos = array();
    $es_solitaria = count($av['jugadores']) === 1;
    foreach ($av['jugadores'] as $j) {
        if ($j['uid'] === null) {
            continue;
        }
        list($sol, $total) = opg_av_contar_activas($j['uid'], $av['id']);
        $motivo = '';
        if ($es_solitaria && $sol + 1 > OPG_AV_MAX_SOLITARIAS) {
            $motivo = 'ya tiene ' . $sol . ' aventuras solitarias (máximo ' . OPG_AV_MAX_SOLITARIAS . ')';
        } elseif ($total + 1 > OPG_AV_MAX_TOTAL) {
            $motivo = 'ya tiene ' . $total . ' aventuras activas (máximo ' . OPG_AV_MAX_TOTAL . ')';
        }
        if ($motivo !== '') {
            $excedidos[] = array('fid' => (int) $j['uid'], 'nombre' => $j['nombre'], 'solitarias' => $sol, 'total' => $total, 'motivo' => $motivo);
        }
    }
    return $excedidos;
}

// ── Auditoría ────────────────────────────────────────────────────────────────

/**
 * Registra una acción en mybb_op_audit_aventuras y en el log general.
 * $peticion_id = 0 para las acciones del mercado de narradores.
 */
function opg_av_auditar($peticion_id, $uid, $accion, $antes, $despues)
{
    global $db;
    $detalle = json_encode(array('antes' => $antes, 'despues' => $despues), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    opg_av_w(
        'INSERT INTO ' . opg_av_t('op_audit_aventuras') . ' (peticion_id, uid, accion, detalle_json, fecha) VALUES ('
        . (int) $peticion_id . ', ' . (int) $uid . ', ' . opg_av_sql((string) $accion) . ', ' . opg_av_sql($detalle) . ', ' . opg_av_ahora() . ')'
    );

    // Log general: log_audit() interpola sin escapar y el log se muestra como HTML.
    if (function_exists('log_audit')) {
        $u = function_exists('get_user') ? get_user((int) $uid) : null;
        $nombre = $u && isset($u['username']) ? $u['username'] : '';
        $texto = htmlspecialchars('Aventuras: ' . $accion . ($peticion_id ? ' (petición #' . (int) $peticion_id . ')' : ''), ENT_QUOTES, 'UTF-8');
        log_audit((int) $uid, $db->escape_string($nombre), '[Aventuras]', $db->escape_string($texto));
    }
}

/** Historial de una solicitud, del más reciente al más antiguo (máximo 100). */
function opg_av_historial($id)
{
    $filas = opg_av_todos(
        'SELECT a.id, a.uid, a.accion, a.detalle_json, a.fecha, u.username FROM ' . opg_av_t('op_audit_aventuras') . ' a'
        . ' LEFT JOIN ' . opg_av_t('users') . ' u ON u.uid = a.uid'
        . ' WHERE a.peticion_id = ' . (int) $id . ' ORDER BY a.id DESC LIMIT 100'
    );
    $salida = array();
    foreach ($filas as $f) {
        $d = json_decode((string) $f['detalle_json'], true);
        $salida[] = array(
            'uid' => (int) $f['uid'],
            'usuario' => (string) $f['username'],
            'accion' => (string) $f['accion'],
            'fecha' => (int) $f['fecha'],
            'antes' => is_array($d) && isset($d['antes']) ? $d['antes'] : null,
            'despues' => is_array($d) && isset($d['despues']) ? $d['despues'] : null,
        );
    }
    return $salida;
}

// ── Validación de una solicitud (crear y editar) ────────────────────────────

/** Casilla o selector verdadero: 1, "1", true, "on", "true", "si". */
function opg_av_verdadero($valor)
{
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 'on' || $valor === 'true' || $valor === 'si';
}

/**
 * Valida la entrada de crear o editar. Con $fila (la fila cruda) se edita: el
 * rol sale de la fila y no de la entrada.
 *
 * Devuelve array('errores' => [...], 'datos' => array|null).
 * La identidad del creador siempre es la de la sesión ($uid).
 */
function opg_av_validar_solicitud($entrada, $uid, $fila = null)
{
    $errores = array();
    $uid = (int) $uid;
    if ($uid <= 0 || !does_ficha_exist($uid)) {
        return array('errores' => array('Necesitas una ficha aprobada para solicitar una aventura.'), 'datos' => null);
    }

    $edicion = is_array($fila);
    if ($edicion) {
        $rol = ((int) $fila['narrador_uid'] === $uid) ? 'narrador' : 'jugador';
    } else {
        $rol = isset($entrada['rol']) ? (string) $entrada['rol'] : '';
        if ($rol !== 'narrador' && $rol !== 'jugador') {
            $errores[] = 'Indica si eres el narrador o un jugador.';
        }
    }

    // Participantes: solo fids de fichas aprobadas, sin repetir.
    $crudos = (isset($entrada['participantes']) && is_array($entrada['participantes'])) ? $entrada['participantes'] : array();
    $participantes = opg_av_enteros($crudos);
    $positivos = 0;
    foreach ($crudos as $v) {
        if ((is_int($v) || (is_string($v) && ctype_digit($v))) && (int) $v > 0) {
            $positivos++;
        }
    }
    if ($positivos > count($participantes)) {
        $errores[] = 'Hay participantes repetidos.';
    }

    $narrador_fid = 0;
    $idea = '';
    $tiene_narrador = false;

    if ($rol === 'narrador') {
        if (in_array($uid, $participantes, true)) {
            $errores[] = 'No puedes ser participante de una aventura que narras tú.';
        }
        if (!$participantes) {
            $errores[] = 'Elige al menos un participante.';
        }
        $narrador_fid = $uid;
        $tiene_narrador = true;
    } elseif ($rol === 'jugador') {
        if (!in_array($uid, $participantes, true)) {
            array_unshift($participantes, $uid);
        }
        $tiene_narrador = isset($entrada['tiene_narrador']) && opg_av_verdadero($entrada['tiene_narrador']);
        if ($tiene_narrador) {
            $narrador_fid = isset($entrada['narrador_fid']) ? (int) $entrada['narrador_fid'] : 0;
            if ($narrador_fid <= 0) {
                $errores[] = 'Elige a tu narrador.';
            } elseif (in_array($narrador_fid, $participantes, true)) {
                $errores[] = 'El narrador no puede ser uno de los participantes.';
            }
        } else {
            list($idea, $e) = opg_av_texto(isset($entrada['idea']) ? $entrada['idea'] : '', OPG_AV_MAX_IDEA, 'la nota de idea');
            if ($e) {
                $errores[] = $e;
            }
        }
    }

    // Fichas existentes y aprobadas.
    $fids = $participantes;
    if ($narrador_fid > 0) {
        $fids[] = $narrador_fid;
    }
    $fichas = opg_av_fichas_por_fids($fids);
    foreach ($participantes as $p) {
        if (!isset($fichas[$p])) {
            $errores[] = 'Hay participantes sin una ficha aprobada.';
            break;
        }
    }
    if ($narrador_fid > 0 && !isset($fichas[$narrador_fid])) {
        $errores[] = 'El narrador no tiene una ficha aprobada.';
    }

    // Isla, día, tier, comentario, inframundo.
    $isla = opg_av_isla_valida(isset($entrada['isla_fid']) ? $entrada['isla_fid'] : 0);
    if (!$isla) {
        $errores[] = 'Elige una isla válida.';
    }
    $dia = opg_av_validar_dia(isset($entrada['dia']) ? $entrada['dia'] : null);
    if ($dia === null) {
        $errores[] = 'El día debe ser un número de 1 a ' . OPG_AV_DIAS . '.';
    }
    $tier = opg_av_validar_tier(isset($entrada['tier']) ? $entrada['tier'] : null);
    if ($tier === null) {
        $errores[] = 'El tier debe ser un número de ' . OPG_AV_TIER_MIN . ' a ' . OPG_AV_TIER_MAX . '.';
    }
    list($comentario, $e) = opg_av_texto(isset($entrada['comentario']) ? $entrada['comentario'] : '', OPG_AV_MAX_COMENTARIO, 'el comentario público', true);
    if ($e) {
        $errores[] = $e;
    }
    $inframundo = isset($entrada['inframundo']) && opg_av_verdadero($entrada['inframundo']);

    if ($errores) {
        return array('errores' => array_values(array_unique($errores)), 'datos' => null);
    }

    $lista = array();
    foreach ($participantes as $p) {
        $lista[] = array('uid' => $p, 'nombre' => $fichas[$p]['nombre']);
    }
    $datos = array(
        'rol' => $rol,
        'participantes' => $lista,
        'narrador' => $tiene_narrador ? array('uid' => $narrador_fid, 'nombre' => $fichas[$narrador_fid]['nombre']) : null,
        'idea' => $tiene_narrador ? '' : $idea,
        'isla_fid' => $isla['fid'],
        'dia' => $dia,
        'tier' => $tier,
        'comentario' => $comentario,
        'inframundo' => $inframundo,
        'estado' => $tiene_narrador ? 0 : 4,
    );
    return array('errores' => array(), 'datos' => $datos);
}

/** ¿El mismo usuario envió una solicitud idéntica en los últimos segundos? */
function opg_av_duplicado_reciente($uid, $datos)
{
    $filas = opg_av_todos(
        'SELECT jugadores_json FROM ' . opg_av_t('op_peticionAventuras')
        . ' WHERE uid = ' . (int) $uid . ' AND created_at >= ' . (opg_av_ahora() - OPG_AV_ANTIRREBOTE_SEG)
        . ' AND isla_fid = ' . (int) $datos['isla_fid'] . ' AND dia = ' . (int) $datos['dia']
    );
    $nuevos = array();
    foreach ($datos['participantes'] as $p) {
        $nuevos[] = (int) $p['uid'];
    }
    sort($nuevos);
    foreach ($filas as $f) {
        $previos = array();
        foreach (opg_av_decodificar_jugadores($f['jugadores_json']) as $j) {
            $previos[] = (int) $j['uid'];
        }
        sort($previos);
        if ($previos === $nuevos) {
            return true;
        }
    }
    return false;
}

// ── Crear y editar ───────────────────────────────────────────────────────────

/** Inserta una solicitud ya validada. Devuelve array('ok'=>true,'id'=>N) o un fallo. */
function opg_av_crear($datos, $uid)
{
    $uid = (int) $uid;
    opg_av_iniciar();
    // Serializa solicitudes que comparten jugadores antes de contar sus activas.
    $fids = array_map(function ($p) { return (int) $p['uid']; }, $datos['participantes']);
    sort($fids);
    if (!opg_av_q('SELECT fid FROM ' . opg_av_t('op_fichas') . ' WHERE fid IN (' . implode(',', $fids) . ') ORDER BY fid FOR UPDATE')) {
        opg_av_revertir();
        return opg_av_fallo(500, array('No se pudieron comprobar los límites de aventuras.'));
    }
    if (opg_av_duplicado_reciente($uid, $datos)) {
        opg_av_revertir();
        return opg_av_fallo(409, array('Ya enviaste una solicitud idéntica hace un momento.'));
    }
    $excedidos = opg_av_validar_limites(array('id' => 0, 'jugadores' => $datos['participantes']));
    if ($excedidos) {
        opg_av_revertir();
        return opg_av_fallo(422, array_map(function ($p) {
            return $p['nombre'] . ' ' . $p['motivo'] . '. No se puede enviar esta solicitud.';
        }, $excedidos));
    }
    $nar = $datos['narrador'];
    $json = json_encode($datos['participantes'], JSON_UNESCAPED_UNICODE);
    $ok = opg_av_w(
        'INSERT INTO ' . opg_av_t('op_peticionAventuras')
        . ' (uid, narrador_uid, narrador_fid, narrador_nombre, isla_fid, dia, tier_seleccionado, nivel_promedio, num_jugadores,'
        . ' dificultad_texto, dificultad_color, ratio_poder, descripcion_tier, jugadores_json, enemigos_json, detalles_json,'
        . ' created_at, estado, comentario_publico, comentario_staff, idea_narrador, inframundo) VALUES ('
        . $uid . ', ' . ($nar ? (int) $nar['uid'] : 'NULL') . ', ' . ($nar ? (int) $nar['uid'] : 'NULL') . ', '
        . ($nar ? opg_av_sql($nar['nombre']) : 'NULL') . ', ' . (int) $datos['isla_fid'] . ', ' . (int) $datos['dia'] . ', '
        . (int) $datos['tier'] . ', 0, ' . count($datos['participantes']) . ", '', '', 0, '', "
        . opg_av_sql($json) . ", '[]', NULL, " . opg_av_ahora() . ', ' . (int) $datos['estado'] . ', '
        . opg_av_sql($datos['comentario']) . ", '', " . opg_av_sql($datos['idea'] !== '' ? $datos['idea'] : null) . ', '
        . ($datos['inframundo'] ? '1' : '0') . ')'
    );
    if (!$ok) {
        opg_av_revertir();
        return opg_av_fallo(500, array('No se pudo guardar la solicitud.'));
    }
    global $db;
    $id = (int) $db->insert_id();
    opg_av_auditar($id, $uid, 'crear', null, array(
        'estado' => (int) $datos['estado'], 'participantes' => array_map(function ($p) { return $p['uid']; }, $datos['participantes']),
        'narrador' => $nar ? $nar['uid'] : null, 'isla_fid' => $datos['isla_fid'], 'dia' => $datos['dia'], 'tier' => $datos['tier'],
    ));
    opg_av_confirmar();
    return array('ok' => true, 'id' => $id);
}

/** Campos que cambiaron entre dos solicitudes normalizadas, para la auditoría. */
function opg_av_diferencias($antes, $despues)
{
    $campos = array('participantes', 'narrador_uid', 'idea_narrador', 'isla_fid', 'dia', 'tier', 'comentario_publico', 'inframundo', 'estado');
    $ant = array();
    $des = array();
    foreach ($campos as $c) {
        $a = opg_av_valor_comparable($antes, $c);
        $d = opg_av_valor_comparable($despues, $c);
        if ($a !== $d) {
            $ant[$c] = $a;
            $des[$c] = $d;
        }
    }
    return array($ant, $des);
}

function opg_av_valor_comparable($av, $campo)
{
    switch ($campo) {
        case 'participantes':
            $uids = array();
            foreach ($av['jugadores'] as $j) {
                $uids[] = (int) $j['uid'];
            }
            return $uids;
        case 'isla_fid':
            return $av['isla'] ? $av['isla']['fid'] : null;
        default:
            return isset($av[$campo]) ? $av[$campo] : null;
    }
}

/** El creador edita su solicitud mientras está en estado 0 o 4. */
function opg_av_editar($id, $entrada, $uid)
{
    $uid = (int) $uid;
    opg_av_iniciar();
    $fila = opg_av_fila($id, true);
    if (!$fila) {
        opg_av_revertir();
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    if ((int) $fila['uid'] !== $uid) {
        opg_av_revertir();
        return opg_av_fallo(403, array('Solo el creador puede editar la solicitud.'));
    }
    if (!in_array((int) $fila['estado'], OPG_AV_ESTADOS_EDITABLES, true)) {
        opg_av_revertir();
        return opg_av_fallo(409, array('La solicitud ya no se puede editar porque está ' . strtolower(opg_av_estados()[(int) $fila['estado']]['etiqueta']) . '.'));
    }

    $v = opg_av_validar_solicitud($entrada, $uid, $fila);
    if ($v['errores']) {
        opg_av_revertir();
        return opg_av_fallo(422, $v['errores']);
    }
    $d = $v['datos'];
    $antes = opg_av_normalizar_fila($fila);

    $nar = $d['narrador'];
    $json = json_encode($d['participantes'], JSON_UNESCAPED_UNICODE);
    $motivo_renuncia = $nar ? ', motivo_renuncia = NULL' : '';
    $ok = opg_av_w(
        'UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET'
        . ' jugadores_json = ' . opg_av_sql($json) . ', num_jugadores = ' . count($d['participantes'])
        . ', narrador_uid = ' . ($nar ? (int) $nar['uid'] : 'NULL') . ', narrador_fid = ' . ($nar ? (int) $nar['uid'] : 'NULL')
        . ', narrador_nombre = ' . ($nar ? opg_av_sql($nar['nombre']) : 'NULL')
        . ', idea_narrador = ' . opg_av_sql($d['idea'] !== '' ? $d['idea'] : null)
        . ', isla_fid = ' . (int) $d['isla_fid'] . ', dia = ' . (int) $d['dia'] . ', tier_seleccionado = ' . (int) $d['tier']
        . ', comentario_publico = ' . opg_av_sql($d['comentario']) . ', inframundo = ' . ($d['inframundo'] ? '1' : '0')
        . ', estado = ' . (int) $d['estado'] . ', editado_at = ' . opg_av_ahora() . $motivo_renuncia
        . ' WHERE id = ' . (int) $id . ' AND estado IN (' . implode(',', OPG_AV_ESTADOS_EDITABLES) . ')'
    );
    if (!$ok) {
        opg_av_revertir();
        return opg_av_fallo(500, array('No se pudo guardar la edición.'));
    }
    if (opg_av_afectadas() === 0) {
        // Sin cambios reales en la misma fila: solo es conflicto si el estado ya no es editable.
        $actual = opg_av_fila($id);
        if (!$actual || !in_array((int) $actual['estado'], OPG_AV_ESTADOS_EDITABLES, true)) {
            opg_av_revertir();
            return opg_av_fallo(409, array('La solicitud cambió de estado mientras la editabas.'));
        }
    }
    $despues = opg_av_normalizar_fila(opg_av_fila($id));
    list($ant, $des) = opg_av_diferencias($antes, $despues);
    opg_av_auditar($id, $uid, 'editar', $ant, $des);
    opg_av_confirmar();
    return array('ok' => true, 'id' => (int) $id);
}

// ── Postular, salirse, cambiar estado y borrar ──────────────────────────────

/** Si la solicitud tiene una entrega pendiente, la devuelve y libera su tema. */
function opg_av_liberar_entrega($peticion_id, $motivo)
{
    opg_av_w(
        'UPDATE ' . opg_av_t('op_aventuras_entregas') . " SET estado = 'rechazada', tid_activo = NULL, motivo_rechazo = "
        . opg_av_sql($motivo) . ', revisado_at = ' . opg_av_ahora()
        . ' WHERE peticion_id = ' . (int) $peticion_id . " AND estado = 'pendiente'"
    );
}

/** Un narrador se postula a una solicitud pendiente de narrador (confirmación obligatoria). */
function opg_av_postular_narrador($id, $uid, $confirmo)
{
    $uid = (int) $uid;
    if (!$confirmo) {
        return opg_av_fallo(422, array('Debes confirmar que te has puesto de acuerdo con el usuario.'));
    }
    $ficha = opg_av_ficha($uid);
    if (!$ficha) {
        return opg_av_fallo(403, array('Necesitas una ficha aprobada para narrar.'));
    }
    $fila = opg_av_fila($id);
    if (!$fila) {
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    $av = opg_av_normalizar_fila($fila);
    if (opg_av_es_parte($av, $uid)) {
        return opg_av_fallo(403, array('No puedes narrar una aventura en la que participas ni una que tú creaste.'));
    }
    if ($av['estado'] !== 4 || $av['narrador_uid'] > 0) {
        return opg_av_fallo(409, array('Esta solicitud ya no está pendiente de narrador.'));
    }
    $ok = opg_av_w(
        'UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET narrador_uid = ' . $uid . ', narrador_fid = ' . $uid
        . ', narrador_nombre = ' . opg_av_sql($ficha['nombre']) . ', estado = 0, motivo_renuncia = NULL'
        . ' WHERE id = ' . (int) $id . ' AND estado = 4 AND narrador_uid IS NULL'
    );
    if (!$ok || opg_av_afectadas() === 0) {
        return opg_av_fallo(409, array('Otro narrador se postuló antes que tú.'));
    }
    opg_av_auditar($id, $uid, 'postular_narrador', array('estado' => 4, 'narrador' => null), array('estado' => 0, 'narrador' => $uid));
    return array('ok' => true, 'id' => (int) $id);
}

/** El narrador asignado deja de narrar (estados 0 y 1), con motivo y confirmación. */
function opg_av_renunciar_narrador($id, $uid, $confirmo, $motivo)
{
    $uid = (int) $uid;
    if (!$confirmo) {
        return opg_av_fallo(422, array('Debes confirmar que quieres dejar de narrar esta aventura.'));
    }
    list($motivo, $e) = opg_av_texto($motivo, OPG_AV_MAX_MOTIVO, 'el motivo', true);
    if ($e) {
        return opg_av_fallo(422, array($e));
    }
    opg_av_iniciar();
    $fila = opg_av_fila($id, true);
    if (!$fila) {
        opg_av_revertir();
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    if ((int) $fila['narrador_uid'] !== $uid || $uid <= 0) {
        opg_av_revertir();
        return opg_av_fallo(403, array('Solo el narrador asignado puede dejar de narrar.'));
    }
    $estado_ant = (int) $fila['estado'];
    if (!in_array($estado_ant, array(0, 1), true)) {
        opg_av_revertir();
        return opg_av_fallo(409, array('Ya no puedes dejar de narrar esta aventura.'));
    }
    $ok = opg_av_w(
        'UPDATE ' . opg_av_t('op_peticionAventuras')
        . ' SET narrador_uid = NULL, narrador_fid = NULL, narrador_nombre = NULL, estado = 4, motivo_renuncia = ' . opg_av_sql($motivo)
        . ' WHERE id = ' . (int) $id . ' AND narrador_uid = ' . $uid . ' AND estado IN (0, 1)'
    );
    if (!$ok || opg_av_afectadas() === 0) {
        opg_av_revertir();
        return opg_av_fallo(409, array('La solicitud cambió mientras la procesábamos.'));
    }
    opg_av_liberar_entrega($id, 'El narrador dejó la aventura.');
    opg_av_auditar($id, $uid, 'renunciar_narrador', array('estado' => $estado_ant, 'narrador' => $uid), array('estado' => 4, 'narrador' => null, 'motivo' => $motivo));
    opg_av_confirmar();
    return array('ok' => true, 'id' => (int) $id);
}

/** ¿Tiene la solicitud una entrega pendiente de revisión? */
function opg_av_tiene_entrega_pendiente($id)
{
    $f = opg_av_uno('SELECT id FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE peticion_id = ' . (int) $id . " AND estado = 'pendiente' LIMIT 1");
    return (bool) $f;
}

/**
 * Cambia el estado: aprobar (1), denegar (2, con motivo), finalizar (3) o pedir el
 * borrado (5, solo el creador). Valida permisos, transición y límites.
 */
function opg_av_cambiar_estado($id, $a, $uid, $motivo = '')
{
    $uid = (int) $uid;
    $a = (int) $a;
    $origenes = array(1 => array(0), 2 => array(0, 1, 4), 3 => array(1), 5 => array(0, 1, 2, 3, 4));
    if (!isset($origenes[$a])) {
        return opg_av_fallo(422, array('Estado no válido.'));
    }
    if ($a === 2) {
        list($motivo, $e) = opg_av_texto($motivo, OPG_AV_MAX_MOTIVO, 'el motivo de la denegación', true);
        if ($e) {
            return opg_av_fallo(422, array($e));
        }
    }

    opg_av_iniciar();
    $fila = opg_av_fila($id, true);
    if (!$fila) {
        opg_av_revertir();
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    $av = opg_av_normalizar_fila($fila);
    $actual = $av['estado'];

    if ($a === 5) {
        if ($uid <= 0 || $av['uid'] !== $uid) {
            opg_av_revertir();
            return opg_av_fallo(403, array('Solo el creador puede pedir el borrado.'));
        }
    } elseif (!opg_av_puede_resolver($av, $uid)) {
        opg_av_revertir();
        $msg = opg_av_puede_gestionar($uid) ? 'No puedes resolver una aventura en la que participas.' : 'Sin permisos.';
        return opg_av_fallo(403, array($msg));
    }
    if (!in_array($actual, $origenes[$a], true) || !opg_av_transicion_valida($actual, $a)) {
        opg_av_revertir();
        return opg_av_fallo(409, array('La solicitud no puede pasar de ' . strtolower($av['estado_etiqueta']) . ' a ese estado.'));
    }

    if ($a === 1) {
        if ($av['narrador_uid'] <= 0) {
            opg_av_revertir();
            return opg_av_fallo(422, array('No se puede aprobar sin narrador.'));
        }
        $excedidos = opg_av_validar_limites($av);
        if ($excedidos) {
            opg_av_revertir();
            $lineas = array();
            foreach ($excedidos as $x) {
                $lineas[] = $x['nombre'] . ': ' . $x['motivo'] . ' (solitarias: ' . $x['solitarias'] . ', total: ' . $x['total'] . ')';
            }
            $r = opg_av_fallo(422, $lineas);
            $r['excedidos'] = $excedidos;
            return $r;
        }
    }
    if ($a === 3 && opg_av_tiene_entrega_pendiente($id)) {
        opg_av_revertir();
        return opg_av_fallo(409, array('Hay una entrega pendiente: págala o recházala en lugar de finalizar a mano.'));
    }

    $extra = ($a === 2) ? ', motivo_denegacion = ' . opg_av_sql($motivo) : '';
    $ok = opg_av_w(
        'UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET estado = ' . $a . $extra
        . ' WHERE id = ' . (int) $id . ' AND estado = ' . $actual
    );
    if (!$ok || opg_av_afectadas() === 0) {
        opg_av_revertir();
        return opg_av_fallo(409, array('La solicitud cambió mientras la procesábamos.'));
    }
    if ($a === 2 || $a === 5) {
        opg_av_liberar_entrega($id, $a === 2 ? 'La solicitud fue denegada.' : 'La solicitud fue marcada para borrado.');
    }
    $despues = array('estado' => $a);
    if ($a === 2) {
        $despues['motivo'] = $motivo;
    }
    opg_av_auditar($id, $uid, 'cambiar_estado', array('estado' => $actual), $despues);
    opg_av_confirmar();
    return array('ok' => true, 'id' => (int) $id, 'estado' => $a);
}

function opg_av_pedir_borrado($id, $uid)
{
    return opg_av_cambiar_estado($id, 5, $uid, '');
}

/** Borrado definitivo de una solicitud en estado 5 (solo staff). */
function opg_av_eliminar($id, $uid)
{
    $uid = (int) $uid;
    if (!opg_av_puede_eliminar($uid)) {
        return opg_av_fallo(403, array('Solo el staff puede eliminar solicitudes.'));
    }
    opg_av_iniciar();
    $fila = opg_av_fila($id, true);
    if (!$fila) {
        opg_av_revertir();
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    if ((int) $fila['estado'] !== 5) {
        opg_av_revertir();
        return opg_av_fallo(409, array('Solo se pueden eliminar solicitudes pendientes de borrado.'));
    }
    $entregada = opg_av_uno('SELECT id FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE peticion_id = ' . (int) $id . " AND estado = 'entregada' LIMIT 1");
    if ($entregada) {
        opg_av_revertir();
        return opg_av_fallo(409, array('No se puede eliminar: ya tiene recompensas entregadas.'));
    }
    opg_av_w('DELETE FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE peticion_id = ' . (int) $id);
    opg_av_w('DELETE FROM ' . opg_av_t('op_peticionAventuras_meta') . ' WHERE peticion_id = ' . (int) $id);
    opg_av_w('DELETE FROM ' . opg_av_t('op_peticionAventuras') . ' WHERE id = ' . (int) $id);
    opg_av_auditar($id, $uid, 'eliminar', $fila, null);
    opg_av_confirmar();
    return array('ok' => true, 'id' => (int) $id);
}

// ── Vincular el tema de la aventura ─────────────────────────────────────────

/**
 * Extrae el tid de lo que escribe o pega el narrador: un entero, o un enlace del
 * propio foro (showthread.php?tid=N o thread-N.html). Devuelve 0 si no es válido.
 */
function opg_av_tid_de_entrada($texto)
{
    global $mybb;
    $t = trim((string) $texto);
    if ($t === '' || strlen($t) > 500) {
        return 0;
    }
    if (ctype_digit($t)) {
        return (int) $t;
    }
    $partes = parse_url($t);
    if (!is_array($partes) || empty($partes['scheme']) || empty($partes['host'])
        || !in_array(strtolower($partes['scheme']), array('http', 'https'), true)) {
        return 0;
    }
    $permitidos = array();
    $foro = parse_url(isset($mybb->settings['bburl']) ? $mybb->settings['bburl'] : '');
    if (is_array($foro) && !empty($foro['host'])) {
        $permitidos[] = strtolower($foro['host']);
    }
    foreach (OPG_AV_HOSTS_EXTRA as $h) {
        $permitidos[] = strtolower($h);
    }
    if (!in_array(strtolower($partes['host']), $permitidos, true)) {
        return 0;
    }
    if (!empty($partes['query'])) {
        parse_str($partes['query'], $q);
        if (isset($q['tid']) && is_string($q['tid']) && ctype_digit($q['tid'])) {
            return (int) $q['tid'];
        }
    }
    if (!empty($partes['path']) && preg_match('~/thread-(\d+)(?:-[^/]*)?\.html$~', $partes['path'], $m)) {
        return (int) $m[1];
    }
    return 0;
}

/** URL normalizada de un tema, la que reconocen la calculadora y recompensasAventuras.php. */
function opg_av_url_tema($tid)
{
    global $mybb;
    return rtrim($mybb->settings['bburl'], '/') . '/showthread.php?tid=' . (int) $tid;
}

/**
 * El narrador asignado vincula el tema (estado 1). Acepta tema elegido, enlace o tid.
 * Requiere opg_ent_tema_valido() (aventuras_entregas.php).
 */
function opg_av_vincular($id, $entrada, $uid)
{
    $uid = (int) $uid;
    $fila = opg_av_fila($id);
    if (!$fila) {
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    $av = opg_av_normalizar_fila($fila);
    if ($av['estado'] !== 1) {
        return opg_av_fallo(409, array('Solo se puede vincular una aventura aprobada.'));
    }
    if (!opg_av_puede_vincular($av, $uid)) {
        return opg_av_fallo(403, array('Solo el narrador asignado o un participante puede vincular la aventura.'));
    }
    $tid = opg_av_tid_de_entrada($entrada);
    if ($tid <= 0) {
        return opg_av_fallo(422, array('Escribe el enlace del tema del foro, su número (tid) o elígelo de la lista.'));
    }
    if (!function_exists('opg_ent_tema_valido')) {
        return opg_av_fallo(500, array('Falta cargar la biblioteca de entregas.'));
    }
    $tema = opg_ent_tema_valido($tid, $av, $uid);
    if (!$tema['ok']) {
        return opg_av_fallo(422, $tema['errores']);
    }
    $url = opg_av_url_tema($tid);
    if (strlen($url) > 255) {
        return opg_av_fallo(422, array('El enlace es demasiado largo.'));
    }
    $ok = opg_av_w(
        'UPDATE ' . opg_av_t('op_peticionAventuras') . ' pa SET aventura_url = ' . opg_av_sql($url)
        . ' WHERE pa.id = ' . (int) $id . ' AND pa.estado = 1 AND (pa.narrador_uid = ' . $uid
        . ' OR ' . opg_av_sql_participa($uid) . ')'
    );
    if (!$ok) {
        return opg_av_fallo(500, array('No se pudo guardar el enlace.'));
    }
    if (opg_av_afectadas() === 0) {
        $actual = opg_av_obtener($id);
        if (!$actual || !opg_av_puede_vincular($actual, $uid) || $actual['aventura_url'] !== $url) {
            return opg_av_fallo(409, array('La solicitud ha cambiado. Recarga antes de vincularla.'));
        }
    }
    opg_av_auditar($id, $uid, 'vincular', array('aventura_url' => $av['aventura_url']), array('aventura_url' => $url, 'tema' => $tema['tema']['nombre']));
    return array('ok' => true, 'id' => (int) $id, 'tid' => $tid, 'tema' => $tema['tema']);
}

// ── Datos que edita el staff desde la consola ───────────────────────────────

/**
 * Nota de staff, comentario público, inframundo, isla y día. Solo los campos
 * presentes en $campos. Refleja nota y comentario en _meta, como la calculadora.
 */
function opg_av_actualizar_datos($id, $campos, $uid)
{
    $uid = (int) $uid;
    if (!opg_av_puede_gestionar($uid)) {
        return opg_av_fallo(403, array('Sin permisos.'));
    }
    $fila = opg_av_fila($id);
    if (!$fila) {
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    $antes = opg_av_normalizar_fila(opg_av_uno(opg_av_select_base() . ' WHERE pa.id = ' . (int) $id . ' LIMIT 1'), true);

    $set = array();
    $meta = array();
    $errores = array();
    $despues = array();
    $anterior = array();

    if (array_key_exists('nota_staff', $campos)) {
        list($t, $e) = opg_av_texto($campos['nota_staff'], OPG_AV_MAX_NOTA_STAFF, 'la nota de staff');
        if ($e) {
            $errores[] = $e;
        } else {
            $set[] = 'comentario_staff = ' . opg_av_sql($t);
            $meta['staff_note'] = $t;
            $anterior['nota_staff'] = $antes['nota_staff'];
            $despues['nota_staff'] = $t;
        }
    }
    if (array_key_exists('comentario', $campos)) {
        list($t, $e) = opg_av_texto($campos['comentario'], OPG_AV_MAX_COMENTARIO, 'el comentario público');
        if ($e) {
            $errores[] = $e;
        } else {
            $set[] = 'comentario_publico = ' . opg_av_sql($t);
            $meta['public_comment'] = $t;
            $anterior['comentario'] = $antes['comentario_publico'];
            $despues['comentario'] = $t;
        }
    }
    if (array_key_exists('inframundo', $campos)) {
        $v = opg_av_verdadero($campos['inframundo']);
        $set[] = 'inframundo = ' . ($v ? '1' : '0');
        $anterior['inframundo'] = $antes['inframundo'];
        $despues['inframundo'] = $v;
    }
    if (array_key_exists('isla_fid', $campos)) {
        $isla = opg_av_isla_valida($campos['isla_fid']);
        if (!$isla) {
            $errores[] = 'Elige una isla válida.';
        } else {
            $set[] = 'isla_fid = ' . (int) $isla['fid'];
            $anterior['isla_fid'] = $antes['isla'] ? $antes['isla']['fid'] : null;
            $despues['isla_fid'] = $isla['fid'];
        }
    }
    if (array_key_exists('dia', $campos)) {
        $d = opg_av_validar_dia($campos['dia']);
        if ($d === null) {
            $errores[] = 'El día debe ser un número de 1 a ' . OPG_AV_DIAS . '.';
        } else {
            $set[] = 'dia = ' . $d;
            $anterior['dia'] = $antes['dia'];
            $despues['dia'] = $d;
        }
    }
    if ($errores) {
        return opg_av_fallo(422, $errores);
    }
    if (!$set) {
        return opg_av_fallo(422, array('No hay cambios que guardar.'));
    }

    opg_av_iniciar();
    opg_av_w('UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET ' . implode(', ', $set) . ' WHERE id = ' . (int) $id);
    if ($meta) {
        $cols = array('peticion_id', 'updated_by', 'updated_at');
        $vals = array((int) $id, $uid, opg_av_ahora());
        $dup = array('updated_by = VALUES(updated_by)', 'updated_at = VALUES(updated_at)');
        foreach ($meta as $col => $val) {
            $cols[] = $col;
            $vals[] = opg_av_sql($val);
            $dup[] = $col . ' = VALUES(' . $col . ')';
        }
        opg_av_w(
            'INSERT INTO ' . opg_av_t('op_peticionAventuras_meta') . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $dup)
        );
    }
    opg_av_auditar($id, $uid, 'actualizar_datos', $anterior, $despues);
    opg_av_confirmar();
    return array('ok' => true, 'id' => (int) $id);
}

/** Conteo de solicitudes por estado: array(estado => n) con los seis estados. */
function opg_av_conteo_estados()
{
    $conteo = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
    foreach (opg_av_todos('SELECT estado, COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras') . ' GROUP BY estado') as $f) {
        $conteo[(int) $f['estado']] = (int) $f['n'];
    }
    return $conteo;
}

// ── Mercado de narradores ───────────────────────────────────────────────────

/** Solicitudes pendientes de narrador que no son del usuario, de la más antigua a la más reciente. */
function opg_av_sin_narrador_listar($uid, $pagina)
{
    $pagina = max(1, (int) $pagina);
    $condicion = ' WHERE pa.estado = 4 AND ' . opg_av_sql_no_mia($uid);
    $total = opg_av_sin_narrador_contar($uid);
    $offset = ($pagina - 1) * OPG_AV_POR_PAGINA_SIN_NARRADOR;
    $filas = opg_av_todos(
        opg_av_select_base() . $condicion . ' ORDER BY pa.created_at ASC, pa.id ASC LIMIT ' . OPG_AV_POR_PAGINA_SIN_NARRADOR . ' OFFSET ' . $offset
    );
    $items = array();
    foreach ($filas as $f) {
        $items[] = opg_av_normalizar_fila($f);
    }
    return array(
        'items' => $items,
        'total' => $total,
        'pagina' => $pagina,
        'hay_mas' => ($offset + count($items)) < $total,
    );
}

function opg_av_sin_narrador_contar($uid)
{
    $f = opg_av_uno(
        'SELECT COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras') . ' pa WHERE pa.estado = 4 AND ' . opg_av_sql_no_mia($uid)
    );
    return $f ? (int) $f['n'] : 0;
}

/** Instante (Unix) a partir del cual una oferta ya no es vigente. */
function opg_av_oferta_limite()
{
    return opg_av_ahora() - (OPG_AV_OFERTA_DIAS * 86400);
}

/**
 * La oferta de narrador de un usuario, vigente o vencida, o null.
 * Incluye 'vencida' y 'dias_restantes'.
 */
function opg_av_oferta_obtener($uid)
{
    $f = opg_av_uno('SELECT * FROM ' . opg_av_t('op_narradores_disponibles') . ' WHERE uid = ' . (int) $uid . ' LIMIT 1');
    if (!$f) {
        return null;
    }
    $vence = (int) $f['updated_at'] + (OPG_AV_OFERTA_DIAS * 86400);
    $restante = $vence - opg_av_ahora();
    return array(
        'uid' => (int) $f['uid'],
        'discord' => (string) $f['discord'],
        'mensaje' => (string) $f['mensaje'],
        'created_at' => (int) $f['created_at'],
        'updated_at' => (int) $f['updated_at'],
        'vencida' => $restante <= 0,
        'dias_restantes' => $restante > 0 ? (int) ceil($restante / 86400) : 0,
    );
}

/** Valida el Discord: de 2 a 64 caracteres, sin saltos de línea ni caracteres de control. */
function opg_av_validar_discord($valor)
{
    $t = trim((string) $valor);
    if ($t === '') {
        return array('', 'Falta tu usuario de Discord.');
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $t)) {
        return array($t, 'El usuario de Discord no puede contener saltos de línea ni caracteres especiales de control.');
    }
    $n = opg_av_longitud($t);
    if ($n < 2) {
        return array($t, 'El usuario de Discord es demasiado corto.');
    }
    if ($n > OPG_AV_MAX_DISCORD) {
        return array($t, 'El usuario de Discord supera los ' . OPG_AV_MAX_DISCORD . ' caracteres.');
    }
    return array($t, null);
}

/** Crea o actualiza (y renueva) la oferta del propio usuario. */
function opg_av_oferta_guardar($uid, $discord, $mensaje)
{
    $uid = (int) $uid;
    if (!opg_av_ficha($uid)) {
        return opg_av_fallo(403, array('Necesitas una ficha aprobada para ofrecerte como narrador.'));
    }
    $errores = array();
    list($discord, $e) = opg_av_validar_discord($discord);
    if ($e) {
        $errores[] = $e;
    }
    list($mensaje, $e) = opg_av_texto($mensaje, OPG_AV_MAX_MENSAJE_OFERTA, 'el mensaje');
    if ($e) {
        $errores[] = $e;
    }
    if ($errores) {
        return opg_av_fallo(422, $errores);
    }
    $antes = opg_av_oferta_obtener($uid);
    $ahora = opg_av_ahora();
    $ok = opg_av_w(
        'INSERT INTO ' . opg_av_t('op_narradores_disponibles') . ' (uid, discord, mensaje, created_at, updated_at) VALUES ('
        . $uid . ', ' . opg_av_sql($discord) . ', ' . opg_av_sql($mensaje) . ', ' . $ahora . ', ' . $ahora . ')'
        . ' ON DUPLICATE KEY UPDATE discord = VALUES(discord), mensaje = VALUES(mensaje), updated_at = VALUES(updated_at)'
    );
    if (!$ok) {
        return opg_av_fallo(500, array('No se pudo guardar tu oferta.'));
    }
    opg_av_auditar(0, $uid, 'ofrecer_narrar', $antes ? array('discord' => $antes['discord'], 'mensaje' => $antes['mensaje']) : null, array('discord' => $discord, 'mensaje' => $mensaje));
    return array('ok' => true);
}

/** Renueva la oferta del propio usuario (solo cambia updated_at). */
function opg_av_oferta_renovar($uid)
{
    $uid = (int) $uid;
    if (!opg_av_oferta_obtener($uid)) {
        return opg_av_fallo(404, array('No tienes una oferta que renovar.'));
    }
    opg_av_w('UPDATE ' . opg_av_t('op_narradores_disponibles') . ' SET updated_at = ' . opg_av_ahora() . ' WHERE uid = ' . $uid);
    opg_av_auditar(0, $uid, 'renovar_oferta', null, null);
    return array('ok' => true);
}

/** Retira la oferta del propio usuario. */
function opg_av_oferta_retirar($uid)
{
    $uid = (int) $uid;
    if (!opg_av_oferta_obtener($uid)) {
        return opg_av_fallo(404, array('No tienes una oferta que retirar.'));
    }
    opg_av_w('DELETE FROM ' . opg_av_t('op_narradores_disponibles') . ' WHERE uid = ' . $uid);
    opg_av_auditar(0, $uid, 'retirar_oferta', null, null);
    return array('ok' => true);
}

/** Cantidad de aventuras en curso (0 y 1) y finalizadas (3) por narrador: array(uid => array(en_curso, finalizadas)). */
function opg_av_conteos_narradores($uids)
{
    $uids = opg_av_enteros($uids);
    $conteos = array();
    foreach ($uids as $u) {
        $conteos[$u] = array('en_curso' => 0, 'finalizadas' => 0);
    }
    if (!$uids) {
        return $conteos;
    }
    $filas = opg_av_todos(
        'SELECT narrador_uid, estado, COUNT(*) AS n FROM ' . opg_av_t('op_peticionAventuras')
        . ' WHERE narrador_uid IN (' . implode(',', $uids) . ') AND estado IN (0, 1, 3) GROUP BY narrador_uid, estado'
    );
    foreach ($filas as $f) {
        $u = (int) $f['narrador_uid'];
        if ((int) $f['estado'] === 3) {
            $conteos[$u]['finalizadas'] += (int) $f['n'];
        } else {
            $conteos[$u]['en_curso'] += (int) $f['n'];
        }
    }
    return $conteos;
}

/**
 * Narradores con oferta vigente, de menos a más aventuras en curso y, a igual
 * número, de la oferta más antigua a la más reciente.
 * Devuelve array(items, total, pagina, paginas).
 */
function opg_av_narradores_disponibles($pagina)
{
    $pagina = max(1, (int) $pagina);
    $filas = opg_av_todos(
        'SELECT nd.uid, nd.discord, nd.mensaje, nd.created_at, nd.updated_at, f.nombre, f.apodo FROM ' . opg_av_t('op_narradores_disponibles') . ' nd'
        . ' JOIN ' . opg_av_t('op_fichas') . ' f ON f.fid = nd.uid'
        . ' WHERE nd.updated_at >= ' . opg_av_oferta_limite()
    );
    $uids = array();
    foreach ($filas as $f) {
        $uids[] = (int) $f['uid'];
    }
    $conteos = opg_av_conteos_narradores($uids);
    $items = array();
    foreach ($filas as $f) {
        $u = (int) $f['uid'];
        $items[] = array(
            'uid' => $u,
            'nombre' => (string) $f['nombre'],
            'apodo' => (string) $f['apodo'],
            'discord' => (string) $f['discord'],
            'mensaje' => (string) $f['mensaje'],
            'desde' => (int) $f['created_at'],
            'renovada' => (int) $f['updated_at'],
            'en_curso' => $conteos[$u]['en_curso'],
            'finalizadas' => $conteos[$u]['finalizadas'],
        );
    }
    usort($items, function ($a, $b) {
        if ($a['en_curso'] !== $b['en_curso']) {
            return $a['en_curso'] < $b['en_curso'] ? -1 : 1;
        }
        return $a['desde'] < $b['desde'] ? -1 : ($a['desde'] > $b['desde'] ? 1 : 0);
    });
    $total = count($items);
    $paginas = max(1, (int) ceil($total / OPG_AV_POR_PAGINA));
    $pagina = min($pagina, $paginas);
    return array('items' => array_slice($items, ($pagina - 1) * OPG_AV_POR_PAGINA, OPG_AV_POR_PAGINA), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas);
}

/** Todas las ofertas (vigentes y vencidas) para la consola, de la más reciente a la más antigua. */
function opg_av_ofertas_admin_listar($pagina)
{
    $pagina = max(1, (int) $pagina);
    $f = opg_av_uno('SELECT COUNT(*) AS n FROM ' . opg_av_t('op_narradores_disponibles'));
    $total = $f ? (int) $f['n'] : 0;
    $paginas = max(1, (int) ceil($total / OPG_AV_POR_PAGINA));
    $pagina = min($pagina, $paginas);
    $filas = opg_av_todos(
        'SELECT nd.uid, nd.discord, nd.mensaje, nd.created_at, nd.updated_at, f.nombre FROM ' . opg_av_t('op_narradores_disponibles') . ' nd'
        . ' LEFT JOIN ' . opg_av_t('op_fichas') . ' f ON f.fid = nd.uid'
        . ' ORDER BY nd.updated_at DESC LIMIT ' . OPG_AV_POR_PAGINA . ' OFFSET ' . (($pagina - 1) * OPG_AV_POR_PAGINA)
    );
    $items = array();
    foreach ($filas as $r) {
        $items[] = array(
            'uid' => (int) $r['uid'],
            'nombre' => (string) $r['nombre'],
            'discord' => (string) $r['discord'],
            'mensaje' => (string) $r['mensaje'],
            'desde' => (int) $r['created_at'],
            'renovada' => (int) $r['updated_at'],
            'vencida' => (int) $r['updated_at'] < opg_av_oferta_limite(),
        );
    }
    return array('items' => $items, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas);
}

/** Un mod, narrador del staff o staff retira la oferta de otro usuario, con motivo. */
function opg_av_oferta_retirar_admin($uid_oferta, $uid_staff, $motivo)
{
    $uid_staff = (int) $uid_staff;
    if (!opg_av_puede_gestionar($uid_staff)) {
        return opg_av_fallo(403, array('Sin permisos.'));
    }
    list($motivo, $e) = opg_av_texto($motivo, OPG_AV_MAX_MOTIVO_OFERTA, 'el motivo', true);
    if ($e) {
        return opg_av_fallo(422, array($e));
    }
    $oferta = opg_av_oferta_obtener((int) $uid_oferta);
    if (!$oferta) {
        return opg_av_fallo(404, array('La oferta ya no existe.'));
    }
    opg_av_w('DELETE FROM ' . opg_av_t('op_narradores_disponibles') . ' WHERE uid = ' . (int) $uid_oferta);
    opg_av_auditar(0, $uid_staff, 'retirar_oferta_admin', array('uid' => (int) $uid_oferta, 'discord' => $oferta['discord'], 'mensaje' => $oferta['mensaje']), array('motivo' => $motivo));
    return array('ok' => true);
}
