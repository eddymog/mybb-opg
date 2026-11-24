<?php
/**
 * Avisos del reset de build con MySQL real (issue #24): aplicar y rechazar avisan al dueño una sola vez, solo si el
 * reset cambió de estado, el reset con cambio de Akuma no envía además akuma_asignada/akuma_retirada, y un fallo del aviso
 * no impide la acción.
 *
 *   php tests/notificaciones/reset_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n24, que crea
 * con docs/notificaciones_migration.sql y unas tablas minimas, y borra al terminar.
 */

require __DIR__ . '/bootstrap_db.php';
$db = nt_preparar('opg_notif_n24');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/reset_avisos.php';
nt_registro_iniciar();

$db->query("CREATE TABLE mybb_op_fichas (fid INT(10) NOT NULL PRIMARY KEY, akuma VARCHAR(100) NOT NULL DEFAULT '',"
    . " akuma_subnombre VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_akumas (akuma_id VARCHAR(20) NOT NULL PRIMARY KEY, nombre VARCHAR(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("INSERT INTO mybb_op_akumas (akuma_id, nombre) VALUES ('gomu', 'Gomu Gomu no Mi'), ('mera', 'Mera Mera no Mi')");
$db->query("CREATE TABLE mybb_op_fichas_reset (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, uid INT NOT NULL,"
    . " estado ENUM('pendiente','aplicado','rechazado') NOT NULL DEFAULT 'pendiente', staff_uid INT NOT NULL DEFAULT 0,"
    . " staff_nombre VARCHAR(100) NOT NULL DEFAULT '', applied_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_inventario (uid INT NOT NULL, objeto_id VARCHAR(20) NOT NULL, cantidad INT NOT NULL DEFAULT 0,"
    . " PRIMARY KEY (uid, objeto_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

const STAFF = 1;

function rs_reset($fid, $akuma = '')
{
    global $db;
    $db->query("INSERT INTO mybb_op_fichas (fid, akuma) VALUES ($fid, '" . $db->escape_string($akuma) . "')");
    $db->query("INSERT INTO mybb_op_fichas_reset (uid) VALUES ($fid)");
    return (int) $db->insert_id();
}

/** Lo mismo que hace 'rechazar' en op/staff/reset_peticiones.php: estado, ticket devuelto y aviso si cambio el estado. */
function rs_rechazar($reset_id, $actor = STAFF)
{
    global $db;
    $reset = $db->fetch_array($db->query("SELECT * FROM mybb_op_fichas_reset WHERE id='$reset_id' AND estado='pendiente' LIMIT 1"));
    if (!$reset) {
        return null;
    }
    $target_uid = (int) $reset['uid'];
    $db->query("UPDATE mybb_op_fichas_reset SET estado='rechazado', staff_uid='$actor', staff_nombre='Staff', applied_at=NOW() WHERE id='$reset_id'");
    $rst_rechazado = (int) $db->affected_rows() > 0;
    $db->query("INSERT INTO mybb_op_inventario (uid, objeto_id, cantidad) VALUES ('$target_uid', 'TNP001', 1) ON DUPLICATE KEY UPDATE cantidad = cantidad + 1");
    return $rst_rechazado ? opg_reset_avisar($reset_id, $target_uid, 'rechazado', $actor) : null;
}

/** Lo mismo que hace 'aplicar': reescribe la Akuma si la propuesta la cambia, marca el estado y avisa si cambio. */
function rs_aplicar($reset_id, $ov_a, $nv_a, $actor = STAFF)
{
    global $db;
    $reset = $db->fetch_array($db->query("SELECT * FROM mybb_op_fichas_reset WHERE id='$reset_id' AND estado='pendiente' LIMIT 1"));
    if (!$reset) {
        return null;
    }
    $target_uid = (int) $reset['uid'];
    $ficha = $db->fetch_array($db->query("SELECT * FROM mybb_op_fichas WHERE fid='$target_uid' LIMIT 1"));
    $ov_as = $nv_as = '';
    if ($nv_a !== $ov_a || $nv_as !== $ov_as) {
        $db->query("UPDATE mybb_op_fichas SET akuma='" . $db->escape_string($nv_a) . "' WHERE fid='$target_uid'");
    }
    $db->query("UPDATE mybb_op_fichas_reset SET estado='aplicado', staff_uid='$actor', staff_nombre='Staff', applied_at=NOW() WHERE id='$reset_id'");
    if ((int) $db->affected_rows() > 0) {
        $rst_akuma_antes = (string) ($ficha['akuma'] ?? '');
        return opg_reset_avisar($reset_id, $target_uid, 'aplicado', $actor, $rst_akuma_antes,
            ($nv_a !== $ov_a || $nv_as !== $ov_as) ? (string) $nv_a : $rst_akuma_antes);
    }
    return null;
}
function rs_avisos($uid)
{
    $r = nt_sql("SELECT tipo, clave_evento FROM mybb_op_notificaciones WHERE uid = $uid ORDER BY id");
    $salida = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $salida[] = $f['tipo'] . '|' . $f['clave_evento'];
    }
    return $salida;
}
function rs_datos($uid)
{
    return json_decode(nt_valor("SELECT datos_json FROM mybb_op_notificaciones WHERE uid = $uid ORDER BY id DESC LIMIT 1"), true);
}

// ── Rechazar ──
$id = rs_reset(10);
rs_rechazar($id);
nt_prueba('rechazar: estado', nt_valor("SELECT estado FROM mybb_op_fichas_reset WHERE id = $id"), 'rechazado');
nt_prueba('rechazar: ticket devuelto', (int) nt_valor("SELECT cantidad FROM mybb_op_inventario WHERE uid = 10 AND objeto_id = 'TNP001'"), 1);
nt_prueba('rechazar: un aviso', rs_avisos(10), array("reset_rechazado|reset:$id:rechazado"));
nt_prueba('rechazar: datos sin staff', rs_datos(10), array('fid' => 10, 'reset_id' => $id));
$fila = nt_una("SELECT categoria, origen, importancia, actor_uid FROM mybb_op_notificaciones WHERE uid = 10");
nt_prueba('rechazar: categoria, origen, importancia, actor', array($fila['categoria'], $fila['origen'], $fila['importancia'], (int) $fila['actor_uid']),
    array('ficha', 'gestion', 'importante', STAFF));
nt_prueba('rechazar: el staff no se avisa', rs_avisos(STAFF), array());
nt_prueba('rechazar otra vez: ya no esta pendiente', rs_rechazar($id), null);
nt_prueba('reintento del aviso: no duplica', array(opg_reset_avisar($id, 10, 'rechazado', STAFF) !== false, count(rs_avisos(10))), array(true, 1));

// ── Aplicar sin cambio de Akuma ──
$id = rs_reset(20, 'gomu');
rs_aplicar($id, 'gomu', 'gomu');
nt_prueba('aplicar: estado', nt_valor("SELECT estado FROM mybb_op_fichas_reset WHERE id = $id"), 'aplicado');
nt_prueba('aplicar: un aviso', rs_avisos(20), array("reset_aplicado|reset:$id:aplicado"));
nt_prueba('aplicar: datos', rs_datos(20), array('fid' => 20, 'reset_id' => $id));
nt_prueba('aplicar otra vez: no avisa', array(rs_aplicar($id, 'gomu', 'gomu'), count(rs_avisos(20))), array(null, 1));

// ── Aplicar con cambio de Akuma: un solo aviso (sin akuma_asignada ni akuma_retirada) ──
$id = rs_reset(30, 'mera');
rs_aplicar($id, 'mera', 'gomu');
nt_prueba('akuma cambiada: ficha', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 30'), 'gomu');
nt_prueba('akuma cambiada: un solo aviso', rs_avisos(30), array("reset_aplicado|reset:$id:aplicado"));
nt_prueba('akuma cambiada: datos con la fruta', rs_datos(30), array('fid' => 30, 'reset_id' => $id, 'akuma' => 'asignada', 'fruta' => 'Gomu Gomu no Mi'));
$id = rs_reset(31, 'mera');
rs_aplicar($id, 'mera', '');
nt_prueba('akuma retirada: datos', rs_datos(31), array('fid' => 31, 'reset_id' => $id, 'akuma' => 'retirada', 'fruta' => 'Mera Mera no Mi'));
nt_prueba('akuma retirada: un solo aviso', count(rs_avisos(31)), 1);

// ── Fallos: la accion se completa igual ──
$db->query('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_x');
opg_notif_tablas_listas(true);
$id = rs_reset(40);
nt_prueba('sin tablas de avisos: no falla', rs_rechazar($id), false);
nt_prueba('sin tablas de avisos: estado', nt_valor("SELECT estado FROM mybb_op_fichas_reset WHERE id = $id"), 'rechazado');
$db->query('RENAME TABLE mybb_op_notificaciones_x TO mybb_op_notificaciones');
opg_notif_tablas_listas(true);

$db->query('ALTER TABLE mybb_op_notificaciones_contador DROP COLUMN importantes');
$id = rs_reset(41, 'mera');
nt_registro_leer();
nt_prueba('envio roto: no falla', rs_aplicar($id, 'mera', ''), false);
nt_prueba('envio roto: ficha y estado', array(nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 41'),
    nt_valor("SELECT estado FROM mybb_op_fichas_reset WHERE id = $id")), array('', 'aplicado'));
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(reset_aplicado)') !== false, 'envio roto: el error queda registrado');

nt_borrar();
nt_resumen('reset_db');
