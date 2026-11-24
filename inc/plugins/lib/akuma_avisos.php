<?php
/**
 * Avisos de Akuma: avisar al usuario cuando el staff le asigna o le retira una fruta (issue #18; 108, apartado 7,
 * requisitos 39 y 41).
 *
 * Donde se escribe mybb_op_fichas.akuma hoy:
 *  - op/staff/ficha_atributos.php y op/staff/ficha_atributos2.php: el editor de ficha del staff. Es tambien la via para
 *    asignar o retirar una fruta tras revisar op/staff/akumas_inactivas.php y op/staff/akumas_expirar.php, que son
 *    informes de solo lectura (no escriben nada). Estas dos paginas llaman a opg_akuma_avisar_cambio().
 *  - opg/tirada_akuma.php: la tirada real del propio usuario. No avisa: el actor es el dueno de la ficha.
 *
 * El valor de la columna puede ser el akuma_id o el nombre de la fruta (la tirada guarda el nombre). El nombre que se
 * muestra se busca en mybb_op_akumas por cualquiera de los dos; si no esta, se usa el valor tal cual.
 *
 * Reglas del aviso: una sola llamada a opg_notificar por cambio, despues del UPDATE (sin transacciones propias), solo si
 * el valor cambio de verdad, y nunca impide la accion (try/catch; sin la libreria o sin sus tablas, no pasa nada).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Caracteres del nombre de la fruta que se guardan en el aviso. */
const OPG_AKUMA_MAX_NOMBRE = 100;

// ── Avisos (puros) ──────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Tipo de aviso para un cambio de mybb_op_fichas.akuma: 'akuma_asignada' (de vacio o de otra fruta a una fruta),
 * 'akuma_retirada' (de una fruta a vacio) o null si el valor no cambio.
 */
function opg_akuma_tipo_cambio($antes, $despues)
{
    $antes = is_scalar($antes) ? trim((string) $antes) : '';
    $despues = is_scalar($despues) ? trim((string) $despues) : '';
    if ($antes === $despues) {
        return null;
    }
    return $despues !== '' ? 'akuma_asignada' : 'akuma_retirada';
}

/**
 * Parte de la clave de evento que identifica la fruta: el id en minusculas si solo lleva letras, numeros y `_ . -`
 * (hasta 40), o un resumen corto del valor si no (por ejemplo un nombre con espacios o tildes).
 */
function opg_akuma_clave_fruta($fruta)
{
    $fruta = is_scalar($fruta) ? trim((string) $fruta) : '';
    if (preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $fruta)) {
        return strtolower($fruta);
    }
    return 'h' . substr(md5($fruta), 0, 12);
}

/** Nombre de la fruta en texto plano, sin etiquetas y recortado. */
function opg_akuma_nombre_limpio($nombre)
{
    $nombre = is_scalar($nombre) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $nombre))) : '';
    if (function_exists('mb_strlen') && mb_strlen($nombre, 'UTF-8') > OPG_AKUMA_MAX_NOMBRE) {
        $nombre = rtrim(mb_substr($nombre, 0, OPG_AKUMA_MAX_NOMBRE - 1, 'UTF-8')) . '…';
    }
    return $nombre;
}

/**
 * Aviso de un cambio de fruta: array('tipo', 'destinatario', 'datos', 'clave'), o null si no hay que avisar (sin cambio,
 * fid no valido o el propio usuario hizo el cambio).
 *
 * $antes y $despues son los valores de la columna; $nombre es el nombre de la fruta que se muestra (la nueva al asignar,
 * la que tenia al retirar), o '' para usar el valor de la columna. La clave lleva usuario, fruta, evento y hora de la
 * accion: akuma:FID:FRUTA:asignada:UNIX. Un reintento con el valor ya guardado no llega aqui (no hay cambio).
 */
function opg_akuma_aviso($fid, $antes, $despues, $nombre, $actor_uid, $hora)
{
    $fid = (int) $fid;
    $tipo = opg_akuma_tipo_cambio($antes, $despues);
    if ($fid <= 0 || $tipo === null) {
        return null;
    }
    $valor = trim((string) ($tipo === 'akuma_asignada' ? $despues : $antes));
    $nombre = opg_akuma_nombre_limpio($nombre !== '' ? $nombre : $valor);
    $datos = array('fid' => $fid);
    if ($nombre !== '') {
        $datos['fruta'] = $nombre;
    }
    return array(
        'tipo' => $tipo,
        'destinatario' => $fid,
        'datos' => $datos,
        'clave' => 'akuma:' . $fid . ':' . opg_akuma_clave_fruta($valor) . ':'
            . ($tipo === 'akuma_asignada' ? 'asignada' : 'retirada') . ':' . max(0, (int) $hora),
    );
}

// ── Envio ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** Nombre de la fruta en mybb_op_akumas (por akuma_id o por nombre), o '' si no esta o la consulta falla. */
function opg_akuma_nombre_fruta($valor)
{
    global $db;
    $valor = trim((string) $valor);
    if ($valor === '' || !is_object($db)) {
        return '';
    }
    try {
        $v = $db->escape_string($valor);
        $consulta = $db->query("SELECT `nombre` FROM `mybb_op_akumas` WHERE `akuma_id`='{$v}' OR `nombre`='{$v}'"
            . " ORDER BY (`akuma_id`='{$v}') DESC LIMIT 1", true);
        $fila = $consulta ? $db->fetch_array($consulta) : null;
        return $fila && isset($fila['nombre']) ? (string) $fila['nombre'] : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Avisa al dueno de la ficha $fid de que su fruta cambio de $antes a $despues. Llamar justo despues del UPDATE y solo si
 * cambio la fila. Una sola llamada a opg_notificar; el actor no recibe el aviso. Nunca lanza: devuelve lo que devuelve
 * opg_notificar, o false si no hay aviso, la libreria no esta o algo falla.
 */
function opg_akuma_avisar_cambio($fid, $antes, $despues, $actor_uid, $hora = null)
{
    try {
        $tipo = opg_akuma_tipo_cambio($antes, $despues);
        if ($tipo === null) {
            return false;
        }
        $nombre = opg_akuma_nombre_fruta($tipo === 'akuma_asignada' ? $despues : $antes);
        $aviso = opg_akuma_aviso($fid, $antes, $despues, $nombre, $actor_uid, $hora === null ? time() : $hora);
        if ($aviso === null) {
            return false;
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
        error_log('opg_akuma_avisar_cambio: ' . $e->getMessage());
        return false;
    }
}
