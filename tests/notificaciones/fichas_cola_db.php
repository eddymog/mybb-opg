<?php
/**
 * Fichas en cola con MySQL real (issue #17): aprobar y rechazar avisan al dueño una sola vez y solo si cambiaron algo.
 *
 *   php tests/notificaciones/fichas_cola_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n17, que crea
 * con docs/notificaciones_migration.sql y unas tablas minimas de fichas, y borra al terminar.
 */

require __DIR__ . '/bootstrap_db.php';
$db = nt_preparar('opg_notif_n17');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/fichas_cola.php';
nt_registro_iniciar();

$db->query("CREATE TABLE mybb_op_fichas (fid INT(10) NOT NULL PRIMARY KEY, nombre VARCHAR(50) NOT NULL DEFAULT '',"
    . " faccion VARCHAR(20) NOT NULL DEFAULT '', aprobada_por VARCHAR(20) NOT NULL DEFAULT 'sin_aprobar',"
    . " tiempo_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_users (uid INT UNSIGNED NOT NULL PRIMARY KEY, usergroup INT NOT NULL DEFAULT 2) ENGINE=InnoDB");
foreach (array('op_fichas_secret' => 'fid', 'op_inventario' => 'uid', 'op_tec_aprendidas' => 'uid', 'op_virtudes_usuarios' => 'uid') as $t => $c) {
    $db->query("CREATE TABLE mybb_$t ($c INT NOT NULL, dato INT NOT NULL DEFAULT 0) ENGINE=InnoDB");
}

const STAFF = 1;

function fc_ficha($fid, $nombre, $creada, $aprobada_por = 'sin_aprobar')
{
    global $db;
    $db->query("INSERT INTO mybb_op_fichas (fid, nombre, aprobada_por, tiempo_creacion) VALUES ($fid, '" . $db->escape_string($nombre)
        . "', '$aprobada_por', FROM_UNIXTIME($creada))");
    $db->query("INSERT IGNORE INTO mybb_users (uid) VALUES ($fid)");
    foreach (array('op_fichas_secret' => 'fid', 'op_inventario' => 'uid', 'op_tec_aprendidas' => 'uid', 'op_virtudes_usuarios' => 'uid') as $t => $c) {
        $db->query("INSERT INTO mybb_$t ($c) VALUES ($fid)");
    }
}
function fc_avisos($uid)
{
    $r = nt_sql("SELECT tipo, clave_evento FROM mybb_op_notificaciones WHERE uid = $uid ORDER BY id");
    $salida = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $salida[] = $f['tipo'] . '|' . $f['clave_evento'];
    }
    return $salida;
}
function fc_contador($uid)
{
    return (int) nt_valor("SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = $uid");
}
function fc_asociados($fid)
{
    return (int) nt_valor("SELECT COUNT(*) FROM mybb_op_inventario WHERE uid = $fid")
        + (int) nt_valor("SELECT COUNT(*) FROM mybb_op_fichas_secret WHERE fid = $fid")
        + (int) nt_valor("SELECT COUNT(*) FROM mybb_op_tec_aprendidas WHERE uid = $fid")
        + (int) nt_valor("SELECT COUNT(*) FROM mybb_op_virtudes_usuarios WHERE uid = $fid");
}

// ── Aprobar ──
fc_ficha(10, 'Nami <i>Test</i>', 1700000000);
nt_prueba('aprobar: cambia la ficha', opg_fichas_cola_aprobar(10, 8, STAFF, 'Staffer'), true);
nt_prueba('aprobar: ficha marcada', nt_valor('SELECT aprobada_por FROM mybb_op_fichas WHERE fid = 10'), 'Staffer');
nt_prueba('aprobar: grupo asignado', (int) nt_valor('SELECT usergroup FROM mybb_users WHERE uid = 10'), 8);
nt_prueba('aprobar: un aviso', fc_avisos(10), array('ficha_aprobada|ficha:10:aprobada'));
$fila = nt_una("SELECT categoria, origen, actor_uid, datos_json FROM mybb_op_notificaciones WHERE uid = 10");
nt_prueba('aprobar: categoria y origen', array($fila['categoria'], $fila['origen'], (int) $fila['actor_uid']), array('ficha', 'gestion', STAFF));
nt_prueba('aprobar: datos sin HTML', json_decode($fila['datos_json'], true), array('fid' => 10, 'nombre' => 'Nami Test'));
nt_prueba('aprobar: contador', fc_contador(10), 1);
nt_prueba('aprobar: reintento no cambia nada', opg_fichas_cola_aprobar(10, 9, STAFF, 'Otro'), false);
nt_prueba('aprobar: reintento no toca el grupo', (int) nt_valor('SELECT usergroup FROM mybb_users WHERE uid = 10'), 8);
nt_prueba('aprobar: reintento no duplica', array(count(fc_avisos(10)), fc_contador(10)), array(1, 1));
nt_prueba('aprobar: el staff no se avisa a si mismo', fc_avisos(STAFF), array());

// Aprobar su propia ficha (actor = dueño): no hay aviso
fc_ficha(11, 'Propia', 1700000000);
nt_prueba('aprobar propia: cambia la ficha', opg_fichas_cola_aprobar(11, 8, 11, 'Yo'), true);
nt_prueba('aprobar propia: sin aviso', fc_avisos(11), array());

// Ficha inexistente
nt_prueba('aprobar inexistente: false', opg_fichas_cola_aprobar(99, 8, STAFF, 'Staffer'), false);
nt_prueba('aprobar inexistente: sin aviso', fc_avisos(99), array());

// ── Rechazar ──
fc_ficha(20, 'Usopp', 1700000000);
nt_prueba('rechazar: borra la ficha', opg_fichas_cola_rechazar(20, STAFF), true);
nt_prueba('rechazar: ficha borrada', (int) nt_valor('SELECT COUNT(*) FROM mybb_op_fichas WHERE fid = 20'), 0);
nt_prueba('rechazar: datos asociados borrados', fc_asociados(20), 0);
nt_prueba('rechazar: un aviso con la fecha de la ficha', fc_avisos(20), array('ficha_rechazada|ficha:20:rechazada:1700000000'));
$fila = nt_una("SELECT datos_json, importancia FROM mybb_op_notificaciones WHERE uid = 20");
nt_prueba('rechazar: nombre leido antes de borrar', json_decode($fila['datos_json'], true), array('fid' => 20, 'nombre' => 'Usopp'));
nt_prueba('rechazar: importante', $fila['importancia'], 'importante');
nt_prueba('rechazar: reintento no hace nada', opg_fichas_cola_rechazar(20, STAFF), false);
nt_prueba('rechazar: reintento no duplica', array(count(fc_avisos(20)), fc_contador(20)), array(1, 1));

// Segunda ficha del mismo usuario rechazada: aviso nuevo
fc_ficha(20, 'Usopp II', 1700005000);
nt_prueba('rechazar segunda: borra', opg_fichas_cola_rechazar(20, STAFF), true);
nt_prueba('rechazar segunda: segundo aviso', fc_avisos(20), array('ficha_rechazada|ficha:20:rechazada:1700000000', 'ficha_rechazada|ficha:20:rechazada:1700005000'));
nt_prueba('rechazar segunda: contador', fc_contador(20), 2);

// Ficha ya aprobada: borrar no toca nada (ni la ficha ni sus datos) y no avisa
fc_ficha(30, 'Sanji', 1700000000, 'Staffer');
nt_prueba('rechazar aprobada: false', opg_fichas_cola_rechazar(30, STAFF), false);
nt_prueba('rechazar aprobada: ficha intacta', (int) nt_valor('SELECT COUNT(*) FROM mybb_op_fichas WHERE fid = 30'), 1);
nt_prueba('rechazar aprobada: datos intactos', fc_asociados(30), 4);
nt_prueba('rechazar aprobada: sin aviso', fc_avisos(30), array());

// Restos de un borrado a medias (sin ficha): se limpian y no se avisa
foreach (array('op_inventario' => 'uid', 'op_fichas_secret' => 'fid') as $t => $c) {
    $db->query("INSERT INTO mybb_$t ($c) VALUES (31)");
}
nt_prueba('restos: false', opg_fichas_cola_rechazar(31, STAFF), false);
nt_prueba('restos: limpiados', fc_asociados(31), 0);
nt_prueba('restos: sin aviso', fc_avisos(31), array());

// ── Fallo del aviso: la accion se completa igual ──
$db->query('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_x');
opg_notif_tablas_listas(true);
fc_ficha(40, 'Robin', 1700000000);
fc_ficha(41, 'Franky', 1700000000);
nt_prueba('sin tablas: aprobar funciona', opg_fichas_cola_aprobar(40, 8, STAFF, 'Staffer'), true);
nt_prueba('sin tablas: rechazar funciona', opg_fichas_cola_rechazar(41, STAFF), true);
nt_prueba('sin tablas: ficha 41 borrada', (int) nt_valor('SELECT COUNT(*) FROM mybb_op_fichas WHERE fid = 41'), 0);
$db->query('RENAME TABLE mybb_op_notificaciones_x TO mybb_op_notificaciones');
opg_notif_tablas_listas(true);

// Error a mitad del envio (tabla del contador rota): la accion se completa y el error se registra
$db->query('ALTER TABLE mybb_op_notificaciones_contador DROP COLUMN importantes');
fc_ficha(42, 'Brook', 1700000000);
nt_registro_leer();
nt_prueba('envio roto: aprobar funciona', opg_fichas_cola_aprobar(42, 8, STAFF, 'Staffer'), true);
nt_prueba('envio roto: ficha aprobada', nt_valor('SELECT aprobada_por FROM mybb_op_fichas WHERE fid = 42'), 'Staffer');
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(ficha_aprobada)') !== false, 'envio roto: el error queda registrado');

nt_borrar();
nt_resumen('fichas_cola_db');
