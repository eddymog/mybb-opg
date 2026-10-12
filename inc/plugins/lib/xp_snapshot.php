<?php
/**
 * Snapshot diario de la experiencia (newpoints) de quienes postearon en rol.
 *
 * Una fila por (fecha, uid) en mybb_op_xp_snapshots. La tarea inc/tasks/op_xp_snapshot.php
 * la llena una vez al dia; op/staff/xp_snapshot.php la muestra. Requiere $db de MyBB.
 */

const OPG_XP_DIAS_ACTIVIDAD = 14;           // quien posteo en rol en los ultimos N dias entra al snapshot
const OPG_XP_DIAS_HISTORIAL = 90;           // los snapshots mas viejos se borran
const OPG_XP_FORO_ROL = '10,';              // parentlist de los foros de rol (igual que aventuras_entregas.php)
const OPG_XP_VENTANAS = array(1, 7, 14);    // dias que compara la pagina

function opg_xp_crear_tabla()
{
    global $db;
    $db->write_query(
        'CREATE TABLE IF NOT EXISTS ' . TABLE_PREFIX . 'op_xp_snapshots ('
        . ' fecha DATE NOT NULL,'
        . ' uid INT UNSIGNED NOT NULL,'
        . ' newpoints DECIMAL(16,2) NOT NULL DEFAULT 0.00,'
        . ' posts INT UNSIGNED NOT NULL DEFAULT 0,'
        . ' PRIMARY KEY (fecha, uid),'
        . ' KEY idx_uid_fecha (uid, fecha)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/**
 * Guarda el newpoints actual de quienes postearon en la zona de rol en los ultimos
 * OPG_XP_DIAS_ACTIVIDAD dias. Repetirlo el mismo dia actualiza las filas (no duplica).
 * Devuelve cuantas filas escribio.
 */
function opg_xp_tomar_snapshot($fecha = null, $ahora = null)
{
    global $db;
    opg_xp_crear_tabla();
    $ahora = $ahora === null ? time() : (int) $ahora;
    $fecha = $fecha === null ? date('Y-m-d', $ahora) : $fecha;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return 0;
    }
    $desde = $ahora - OPG_XP_DIAS_ACTIVIDAD * 86400;
    $t = TABLE_PREFIX;
    $db->write_query(
        "INSERT INTO {$t}op_xp_snapshots (fecha, uid, newpoints, posts)"
        . " SELECT '" . $db->escape_string($fecha) . "', u.uid, u.newpoints, a.posts FROM {$t}users u"
        . " JOIN (SELECT p.uid, COUNT(*) AS posts FROM {$t}posts p JOIN {$t}forums f ON f.fid = p.fid"
        . "  WHERE p.visible = 1 AND p.uid > 0 AND p.dateline >= " . (int) $desde
        . "  AND f.parentlist LIKE '" . $db->escape_string_like(OPG_XP_FORO_ROL) . "%' GROUP BY p.uid) a ON a.uid = u.uid"
        . ' ON DUPLICATE KEY UPDATE newpoints = VALUES(newpoints), posts = VALUES(posts)'
    );
    return (int) $db->affected_rows();
}

/** Borra los snapshots mas viejos que OPG_XP_DIAS_HISTORIAL. Devuelve cuantos borro. */
function opg_xp_purgar($ahora = null)
{
    global $db;
    $ahora = $ahora === null ? time() : (int) $ahora;
    $limite = date('Y-m-d', $ahora - OPG_XP_DIAS_HISTORIAL * 86400);
    $db->delete_query('op_xp_snapshots', "fecha < '" . $db->escape_string($limite) . "'");
    return (int) $db->affected_rows();
}

/** XP ganada entre dos snapshots; null si no hay base con la que comparar. */
function opg_xp_ganancia($actual, $base)
{
    return $base === null ? null : round((float) $actual - (float) $base, 2);
}

/** Fechas con snapshot, de la mas reciente a la mas antigua. */
function opg_xp_fechas()
{
    global $db;
    $fechas = array();
    $q = $db->query('SELECT DISTINCT fecha FROM ' . TABLE_PREFIX . 'op_xp_snapshots ORDER BY fecha DESC LIMIT ' . OPG_XP_DIAS_HISTORIAL);
    while ($f = $db->fetch_array($q)) {
        $fechas[] = $f['fecha'];
    }
    return $fechas;
}

/**
 * Filas de un dia con la XP ganada en cada ventana de OPG_XP_VENTANAS. La base de cada
 * ventana es el snapshot mas reciente de ese usuario que no pase de (fecha - N dias),
 * asi un dia sin tarea no rompe la comparacion. Sin base: null.
 */
function opg_xp_filas($fecha)
{
    global $db;
    $t = TABLE_PREFIX;
    $f = $db->escape_string($fecha);
    $bases = '';
    foreach (OPG_XP_VENTANAS as $n) {
        $bases .= ", (SELECT b.newpoints FROM {$t}op_xp_snapshots b WHERE b.uid = s.uid"
            . " AND b.fecha <= DATE_SUB(s.fecha, INTERVAL " . (int) $n . " DAY) ORDER BY b.fecha DESC LIMIT 1) AS base_" . (int) $n;
    }
    $q = $db->query("SELECT s.uid, u.username, s.newpoints, s.posts{$bases} FROM {$t}op_xp_snapshots s"
        . " LEFT JOIN {$t}users u ON u.uid = s.uid WHERE s.fecha = '{$f}'");
    $filas = array();
    while ($r = $db->fetch_array($q)) {
        $fila = array('uid' => (int) $r['uid'], 'username' => (string) $r['username'],
            'newpoints' => (float) $r['newpoints'], 'posts' => (int) $r['posts']);
        foreach (OPG_XP_VENTANAS as $n) {
            $fila['gan_' . $n] = opg_xp_ganancia($r['newpoints'], $r['base_' . $n] === null ? null : $r['base_' . $n]);
        }
        $filas[] = $fila;
    }
    return $filas;
}
