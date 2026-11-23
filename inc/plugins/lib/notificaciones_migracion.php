<?php
/**
 * Migracion de las notificaciones antiguas del Tablon de aventuras al sistema global (issue #10; 208, apartado 9.1;
 * requisitos 42, 45 y 46).
 *
 * Copia mybb_op_aventuras_notificaciones a mybb_op_notificaciones por lotes (500 por defecto), en orden de id:
 *  - INSERT IGNORE multifila: la clave unica (uid, clave_evento) hace que relanzarla no duplique nada, y que una fila
 *    que el Tablon ya envio al sistema global con la misma clave (despues de subir la issue #9) se respete tal cual.
 *  - Conserva clave_evento, uid, actor_uid, tipo, created_at, sent_at y attempts; read_at pasa a leida_en.
 *  - Categoria, origen, importancia y agrupable salen del catalogo para cada tipo (los tipos del Tablon son de la
 *    categoria 'aventuras', salvo recompensas_entregadas, que el catalogo pone en 'recompensas' y que el Tablon tambien
 *    lee desde la issue #9). Un tipo que no este en el catalogo va a 'aventuras', origen 'sistema', importancia normal.
 *  - datos_json se reconstruye como lo hace opg_av_notif_datos() (lib/aventuras.php) al enviar: peticion_id, tema (solo
 *    si el tema de la aventura esta visible), isla, tier, actor y narrador (de la propuesta), mas lo que ya traian los
 *    datos antiguos (motivo, dias, propuesta_id...). El contexto se consulta UNA vez por lote, no por fila.
 *  - La url se deja en NULL, como en los envios del Tablon: el catalogo la construye al mostrar.
 *  - No borra ni modifica la tabla antigua (requisito 45).
 *
 * Al terminar, el script reconcilia (opg_notif_reconciliar) el contador de los usuarios con alguna notificacion antigua
 * sin leer: las filas copiadas no suben el contador al insertarse (208, apartado 5.2).
 *
 * Es la unica excepcion a "solo opg_notificar() inserta en la tabla global": la migracion debe conservar la fecha y el
 * estado de lectura de cada aviso, y no debe avisar a nadie (requisito 41), cosas que opg_notificar no hace.
 *
 * Las funciones que tocan la base lanzan RuntimeException (via opg_notif_w) ante un error de MySQL; las captura la pagina
 * op/staff/migrar_notificaciones.php. Codigo compatible con PHP 7.4.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/notificaciones.php';

/** Filas por lote de copia. */
const OPG_NOTIF_MIG_LOTE = 500;
/** Usuarios por tanda de reconciliacion. */
const OPG_NOTIF_MIG_LOTE_UIDS = 200;
/**
 * Clave de la tabla antigua en la colacion de la global. La tabla antigua se creo sin COLLATE (toma la del servidor, por
 * ejemplo utf8mb4_general_ci) y la global es utf8mb4_unicode_ci: compararlas sin convertir da el error 1267. La conversion
 * va del lado de la tabla antigua, asi la busqueda sigue usando el indice unico de la global.
 */
const OPG_NOTIF_MIG_CLAVE_A = 'CONVERT(a.clave_evento USING utf8mb4) COLLATE utf8mb4_unicode_ci';

// ── Ayudantes puros ─────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Columnas fijas de un tipo para la tabla global: las del catalogo o, si el tipo no esta en el, categoria 'aventuras',
 * origen 'sistema', importancia normal y no agrupable.
 */
function opg_notif_mig_definicion($tipo)
{
    $def = opg_notif_tipo((string) $tipo);
    if (!$def) {
        return array('categoria' => 'aventuras', 'origen' => 'sistema', 'importancia' => 'normal', 'agrupable' => 0);
    }
    return array('categoria' => (string) $def['categoria'], 'origen' => (string) $def['origen'],
        'importancia' => (string) $def['importancia'], 'agrupable' => !empty($def['agrupable']) ? 1 : 0);
}

/**
 * Datos de un aviso migrado, con el mismo orden y las mismas reglas que opg_av_notif_datos(): primero lo calculado
 * (peticion_id, tema, isla, tier, actor, narrador) y despues las claves de los datos antiguos que no esten ya.
 *
 * $fila      fila de la tabla antigua (peticion_id, actor_uid, datos_json).
 * $contexto  array('aventuras' => [peticion_id => [tema, isla, tier]], 'nombres' => [uid => nombre],
 *                  'narradores' => [propuesta_id => nombre]), como lo carga opg_notif_mig_contexto().
 *
 * Devuelve el texto JSON (o null si no hay datos), ya limpio con opg_notif_preparar_datos().
 */
function opg_notif_mig_datos_json($fila, $contexto)
{
    $antiguos = json_decode((string) $fila['datos_json'], true);
    $antiguos = is_array($antiguos) ? $antiguos : array();
    $d = array();
    $pid = (int) $fila['peticion_id'];
    if ($pid > 0) {
        $d['peticion_id'] = $pid;
        if (isset($contexto['aventuras'][$pid])) {
            $av = $contexto['aventuras'][$pid];
            if ((string) $av['tema'] !== '') {
                $d['tema'] = (string) $av['tema'];
            }
            if ((string) $av['isla'] !== '') {
                $d['isla'] = (string) $av['isla'];
            }
            if ((int) $av['tier'] > 0) {
                $d['tier'] = (int) $av['tier'];
            }
        }
    }
    $actor = (int) $fila['actor_uid'];
    if ($actor > 0 && isset($contexto['nombres'][$actor]) && (string) $contexto['nombres'][$actor] !== '') {
        $d['actor'] = (string) $contexto['nombres'][$actor];
    }
    $prop = !empty($antiguos['propuesta_id']) ? (int) $antiguos['propuesta_id'] : 0;
    if ($prop > 0 && isset($contexto['narradores'][$prop]) && (string) $contexto['narradores'][$prop] !== '') {
        $d['narrador'] = (string) $contexto['narradores'][$prop];
    }
    foreach ($antiguos as $clave => $valor) {
        if (!isset($d[$clave])) {
            $d[$clave] = $valor;
        }
    }
    $json = opg_notif_datos_json(opg_notif_preparar_datos($d));
    if ($json === false) {
        // Muy raro (datos antiguos que json_encode no sabe codificar): se guarda al menos la aventura.
        $json = $pid > 0 ? json_encode(array('peticion_id' => $pid)) : null;
    }
    return $json;
}

/** Literal SQL de un entero sin signo que puede ser NULL. */
function opg_notif_mig_entero_o_null($valor)
{
    return ($valor === null || $valor === '') ? 'NULL' : (string) max(0, (int) $valor);
}

/** Tupla VALUES de una fila antigua para el INSERT en la tabla global (columnas de opg_notif_mig_columnas()). */
function opg_notif_mig_valores($fila, $contexto)
{
    $def = opg_notif_mig_definicion($fila['tipo']);
    return '(' . (int) $fila['uid'] . ', ' . opg_notif_sql_texto((string) $fila['tipo']) . ', ' . opg_notif_sql_texto($def['categoria'])
        . ', ' . opg_notif_sql_texto($def['origen']) . ', ' . opg_notif_sql_texto($def['importancia']) . ', ' . (int) $def['agrupable']
        . ', ' . max(0, (int) $fila['actor_uid']) . ', ' . opg_notif_sql_texto((string) $fila['clave_evento']) . ', NULL, 1, '
        . opg_notif_sql_texto(opg_notif_mig_datos_json($fila, $contexto)) . ', ' . max(0, (int) $fila['created_at']) . ', '
        . opg_notif_mig_entero_o_null($fila['read_at']) . ', ' . opg_notif_mig_entero_o_null($fila['sent_at']) . ', '
        . min(255, max(0, (int) $fila['attempts'])) . ')';
}

function opg_notif_mig_columnas()
{
    return ' (uid, tipo, categoria, origen, importancia, agrupable, actor_uid, clave_evento, url, cantidad, datos_json, created_at,'
        . ' leida_en, sent_at, attempts) VALUES ';
}

/** ¿Se puede copiar la fila? uid valido, clave de evento no vacia que quepa en la columna y tipo no vacio. */
function opg_notif_mig_fila_valida($fila)
{
    $clave = (string) $fila['clave_evento'];
    return opg_notif_uid((int) $fila['uid']) > 0 && $clave !== '' && strlen($clave) <= OPG_NOTIF_MAX_CLAVE
        && (string) $fila['tipo'] !== '' && strlen((string) $fila['tipo']) <= 48;
}

// ── Consultas ───────────────────────────────────────────────────────────────────────────────────────────────────────

/** true si existen la tabla antigua y las dos tablas globales. */
function opg_notif_mig_tablas_listas()
{
    global $db;
    if (!opg_notif_tablas_listas(true)) {
        return false;
    }
    try {
        return $db->write_query('SELECT 1 FROM ' . opg_notif_t('op_aventuras_notificaciones') . ' LIMIT 0', 1) !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Situacion actual sin escribir nada (la simulacion): filas antiguas, cuantas ya estan en la tabla global (misma uid y
 * clave de evento), cuantas faltan, cuantas no se pueden copiar y cuantos usuarios tienen alguna sin leer. Incluye el
 * desglose de las que faltan por tipo, con la categoria y el origen con que se copiaran.
 */
function opg_notif_mig_estado()
{
    $A = opg_notif_t('op_aventuras_notificaciones');
    $N = opg_notif_t('op_notificaciones');
    $f = opg_notif_filas('SELECT COUNT(*) AS total, COALESCE(SUM(n.id IS NOT NULL), 0) AS ya, COALESCE(SUM(a.uid = 0 OR a.clave_evento = \'\'), 0) AS invalidas'
        . ' FROM ' . $A . ' a LEFT JOIN ' . $N . ' n ON n.uid = a.uid AND n.clave_evento = ' . OPG_NOTIF_MIG_CLAVE_A);
    $u = opg_notif_filas('SELECT COUNT(DISTINCT uid) AS n FROM ' . $A . ' WHERE read_at IS NULL AND uid > 0');
    $tipos = array();
    foreach (opg_notif_filas('SELECT a.tipo, COUNT(*) AS n FROM ' . $A . ' a LEFT JOIN ' . $N . ' n ON n.uid = a.uid AND n.clave_evento = ' . OPG_NOTIF_MIG_CLAVE_A
        . " WHERE n.id IS NULL AND a.uid > 0 AND a.clave_evento <> '' GROUP BY a.tipo ORDER BY a.tipo") as $t) {
        $def = opg_notif_mig_definicion($t['tipo']);
        $tipos[] = array('tipo' => (string) $t['tipo'], 'n' => (int) $t['n'], 'categoria' => $def['categoria'], 'origen' => $def['origen'],
            'en_catalogo' => opg_notif_tipo((string) $t['tipo']) !== null);
    }
    $total = (int) $f[0]['total'];
    $ya = (int) $f[0]['ya'];
    $invalidas = (int) $f[0]['invalidas'];
    return array('total' => $total, 'ya_copiadas' => $ya, 'pendientes' => max(0, $total - $ya - $invalidas), 'invalidas' => $invalidas,
        'usuarios_sin_leer' => (int) $u[0]['n'], 'por_tipo' => $tipos);
}

/** Lista de enteros positivos sin repetir, lista para un IN (...). */
function opg_notif_mig_lista_in($valores)
{
    $limpios = array();
    foreach ($valores as $v) {
        $v = (int) $v;
        if ($v > 0) {
            $limpios[$v] = $v;
        }
    }
    return implode(',', $limpios);
}

/** Contexto de un lote para reconstruir los datos: aventuras, nombres de los actores y narradores de las propuestas. */
function opg_notif_mig_contexto($filas)
{
    $pids = array();
    $actores = array();
    $props = array();
    foreach ($filas as $f) {
        $pids[] = $f['peticion_id'];
        $actores[] = $f['actor_uid'];
        $d = json_decode((string) $f['datos_json'], true);
        if (is_array($d) && !empty($d['propuesta_id']) && is_scalar($d['propuesta_id'])) {
            $props[] = (int) $d['propuesta_id'];
        }
    }
    $ctx = array('aventuras' => array(), 'nombres' => array(), 'narradores' => array());
    if (($in = opg_notif_mig_lista_in($pids)) !== '') {
        // Misma consulta que opg_av_notif_datos(): el titulo del tema solo si el tema esta visible.
        foreach (opg_notif_filas('SELECT pa.id, pa.tier_seleccionado, f.name AS isla, CASE WHEN th.visible = 1 THEN th.subject ELSE NULL END AS tema FROM '
            . opg_notif_t('op_peticionAventuras') . ' pa LEFT JOIN ' . opg_notif_t('forums') . ' f ON f.fid = pa.isla_fid LEFT JOIN '
            . opg_notif_t('threads') . " th ON th.tid = CAST(SUBSTRING_INDEX(pa.aventura_url, 'tid=', -1) AS UNSIGNED) WHERE pa.id IN ($in)") as $r) {
            $ctx['aventuras'][(int) $r['id']] = array('tema' => (string) $r['tema'], 'isla' => (string) $r['isla'], 'tier' => (int) $r['tier_seleccionado']);
        }
    }
    if (($in = opg_notif_mig_lista_in($actores)) !== '') {
        foreach (opg_notif_filas('SELECT u.uid, COALESCE(f.nombre, u.username) AS nombre FROM ' . opg_notif_t('users') . ' u LEFT JOIN '
            . opg_notif_t('op_fichas') . " f ON f.fid = u.uid WHERE u.uid IN ($in)") as $r) {
            $ctx['nombres'][(int) $r['uid']] = (string) $r['nombre'];
        }
    }
    if (($in = opg_notif_mig_lista_in($props)) !== '') {
        foreach (opg_notif_filas('SELECT p.id, COALESCE(f.nombre, u.username) AS nombre FROM ' . opg_notif_t('op_aventuras_propuestas_narrador') . ' p LEFT JOIN '
            . opg_notif_t('users') . ' u ON u.uid = p.narrador_uid LEFT JOIN ' . opg_notif_t('op_fichas') . " f ON f.fid = p.narrador_uid WHERE p.id IN ($in)") as $r) {
            $ctx['narradores'][(int) $r['id']] = (string) $r['nombre'];
        }
    }
    return $ctx;
}

/**
 * Copia (o, con $simular, solo cuenta) el siguiente lote de filas antiguas con id > $desde_id.
 *
 * Devuelve array('leidas', 'copiadas', 'existian', 'omitidas', 'ultimo_id', 'fin'):
 *   copiadas  filas nuevas en la tabla global (en simulacion: las que se copiarian).
 *   existian  filas que ya estaban (misma uid y clave de evento): no se tocan.
 *   omitidas  filas que no se pueden copiar (uid 0, clave vacia o demasiado larga); se dejan en la tabla antigua.
 *   fin       true si no quedan mas filas despues de este lote.
 */
function opg_notif_mig_lote($desde_id, $limite = OPG_NOTIF_MIG_LOTE, $simular = false)
{
    global $db;
    $desde_id = max(0, (int) $desde_id);
    $limite = max(1, min(2000, (int) $limite));
    $filas = opg_notif_filas('SELECT id, clave_evento, peticion_id, uid, actor_uid, tipo, datos_json, created_at, read_at, sent_at, attempts FROM '
        . opg_notif_t('op_aventuras_notificaciones') . ' WHERE id > ' . $desde_id . ' ORDER BY id LIMIT ' . $limite);
    $r = array('leidas' => count($filas), 'copiadas' => 0, 'existian' => 0, 'omitidas' => 0, 'ultimo_id' => $desde_id, 'fin' => count($filas) < $limite);
    if (!$filas) {
        $r['fin'] = true;
        return $r;
    }
    $r['ultimo_id'] = (int) $filas[count($filas) - 1]['id'];

    $validas = array();
    foreach ($filas as $f) {
        if (opg_notif_mig_fila_valida($f)) {
            $validas[] = $f;
        } else {
            $r['omitidas']++;
        }
    }
    if (!$validas) {
        return $r;
    }

    if ($simular) {
        $pares = array();
        foreach ($validas as $f) {
            $pares[] = '(' . (int) $f['uid'] . ', ' . opg_notif_sql_texto((string) $f['clave_evento']) . ')';
        }
        $ya = opg_notif_filas('SELECT COUNT(*) AS n FROM ' . opg_notif_t('op_notificaciones') . ' WHERE (uid, clave_evento) IN (' . implode(',', $pares) . ')');
        $r['existian'] = (int) $ya[0]['n'];
        $r['copiadas'] = count($validas) - $r['existian'];
        return $r;
    }

    // Orden por (uid, clave) para tomar los bloqueos del indice unico en el mismo orden que los envios (por uid).
    usort($validas, function ($a, $b) {
        return ((int) $a['uid'] <=> (int) $b['uid']) ?: strcmp((string) $a['clave_evento'], (string) $b['clave_evento']);
    });
    $contexto = opg_notif_mig_contexto($validas);
    $valores = array();
    foreach ($validas as $f) {
        $valores[] = opg_notif_mig_valores($f, $contexto);
    }
    opg_notif_w('INSERT IGNORE INTO ' . opg_notif_t('op_notificaciones') . opg_notif_mig_columnas() . implode(', ', $valores));
    $insertadas = (int) $db->affected_rows();
    if ($insertadas < 0 || $insertadas > count($validas)) {
        throw new RuntimeException('filas afectadas inesperadas (' . $insertadas . ' de ' . count($validas) . ') en el lote tras el id ' . $desde_id);
    }
    $r['copiadas'] = $insertadas;
    $r['existian'] = count($validas) - $insertadas;
    return $r;
}

/**
 * Reconcilia el contador de la siguiente tanda de usuarios (uid > $desde_uid) con alguna notificacion antigua sin leer.
 * Devuelve array('reconciliados', 'fallidos' => [uid...], 'ultimo_uid', 'fin').
 */
function opg_notif_mig_reconciliar($desde_uid, $limite = OPG_NOTIF_MIG_LOTE_UIDS)
{
    $desde_uid = max(0, (int) $desde_uid);
    $limite = max(1, min(2000, (int) $limite));
    $uids = opg_notif_filas('SELECT DISTINCT uid FROM ' . opg_notif_t('op_aventuras_notificaciones') . ' WHERE read_at IS NULL AND uid > ' . $desde_uid
        . ' ORDER BY uid LIMIT ' . $limite);
    $r = array('reconciliados' => 0, 'fallidos' => array(), 'ultimo_uid' => $desde_uid, 'fin' => count($uids) < $limite);
    foreach ($uids as $f) {
        $uid = (int) $f['uid'];
        $r['ultimo_uid'] = $uid;
        if (opg_notif_reconciliar($uid) === false) {
            $r['fallidos'][] = $uid;
        } else {
            $r['reconciliados']++;
        }
    }
    return $r;
}
