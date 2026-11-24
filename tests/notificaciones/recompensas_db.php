<?php
/**
 * Avisos de recompensas y salarios del staff con MySQL real (issue #20).
 *
 *   php tests/notificaciones/recompensas_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n20, que crea con
 * docs/notificaciones_migration.sql y docs/salario_faccion_automatico_migration.sql y borra al terminar.
 *
 * Comprueba:
 *  - Salario de faccion (opg_sf_repartir): cada personaje que cobra recibe salario_recibido con su importe, Berries y su
 *    rango; quien no cobra no recibe nada; el actor manual no recibe su propio aviso; repetir la semana no duplica; una
 *    semana nueva avisa de nuevo. Sin tablas de notificaciones, o con el aviso fallando, el reparto se hace igual.
 *  - Salario de staff (opg_rec_avisar_salarios con la clave del primer pago del lote): un aviso por destinatario y pago,
 *    importes por rol, dos lotes distintos avisan los dos y repetir la llamada no duplica.
 *  - Recompensa de un lote (berries_masivo): una sola notificacion por ficha y una sola sentencia de insercion.
 */

require __DIR__ . '/bootstrap_db.php';
$db = nt_preparar('opg_notif_n20');
nt_registro_iniciar();

$error = nt_ejecutar_script($db, "
CREATE TABLE mybb_op_fichas (
    fid int NOT NULL, nombre varchar(50) NOT NULL, faccion varchar(30) NOT NULL,
    rango varchar(255) NOT NULL, berries int NOT NULL DEFAULT 0, PRIMARY KEY (fid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE mybb_op_audit_general (
    id int NOT NULL AUTO_INCREMENT, uid varchar(20) NOT NULL, username varchar(100) NOT NULL,
    user_uid varchar(20) NOT NULL, categoria varchar(255) NOT NULL, log text NOT NULL, PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE mybb_op_audit_consola_mod (
    id int NOT NULL AUTO_INCREMENT, staff varchar(20) NOT NULL, username varchar(100) NOT NULL,
    razon varchar(100) NOT NULL, log text NOT NULL, PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE mybb_tasks (
    tid int unsigned NOT NULL AUTO_INCREMENT, title varchar(120) NOT NULL DEFAULT '', description text NOT NULL,
    file varchar(30) NOT NULL DEFAULT '', minute varchar(200) NOT NULL DEFAULT '', hour varchar(200) NOT NULL DEFAULT '',
    day varchar(100) NOT NULL DEFAULT '', month varchar(30) NOT NULL DEFAULT '', weekday varchar(15) NOT NULL DEFAULT '',
    nextrun int unsigned NOT NULL DEFAULT 0, lastrun int unsigned NOT NULL DEFAULT 0, enabled tinyint NOT NULL DEFAULT 1,
    logging tinyint NOT NULL DEFAULT 0, locked int unsigned NOT NULL DEFAULT 0, PRIMARY KEY (tid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
$error = $error ?: nt_ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/salario_faccion_automatico_migration.sql'));
if ($error !== null) {
    fwrite(STDERR, "No se pudo preparar el esquema: $error\n");
    nt_borrar();
    exit(2);
}

function op_facciones_sueldos_semanales()
{
    return array('ReclutaM' => 10000, 'Capitan' => 1250000);
}

require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/recompensas_avisos.php';
require_once MYBB_ROOT . 'inc/plugins/lib/salario_faccion.php';

function rd_avisos($tipo)
{
    $filas = array();
    $r = nt_sql("SELECT uid, clave_evento, datos_json FROM mybb_op_notificaciones WHERE tipo = '" . $tipo . "' ORDER BY uid, id");
    while ($f = mysqli_fetch_assoc($r)) {
        $filas[] = array((int) $f['uid'], $f['clave_evento'], json_decode($f['datos_json'], true));
    }
    return $filas;
}
function rd_contar($tipo)
{
    return (int) nt_valor("SELECT COUNT(*) FROM mybb_op_notificaciones WHERE tipo = '" . $tipo . "'");
}
function rd_inserciones($desde)
{
    global $db;
    $n = 0;
    foreach (array_slice($db->sentencias, $desde) as $s) {
        if (stripos(ltrim($s), 'INSERT IGNORE INTO `mybb_op_notificaciones`') === 0) {
            $n++;
        }
    }
    return $n;
}

// ── Salario de faccion ──────────────────────────────────────────────────────────────────────────────────────────────
nt_sql("INSERT INTO mybb_op_fichas VALUES
    (10,'Nami','Marina','ReclutaM',500),(11,'Smoker','Marina','Capitan',1000),(12,'Luffy','Pirata','Pirata',900),
    (13,'Koby','Marina','ReclutaM',0),(5,'Garp','Marina','Capitan',0)");
$lunes = (new DateTimeImmutable('2026-11-23 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();

// Ejecutado a mano por el staff con uid 5, que tambien cobra: recibe su propio aviso (queda en su historial).
$r1 = opg_sf_repartir(5, 'Garp', 'manual', $lunes);
nt_prueba('faccion: reparto hecho', array($r1['codigo'], $r1['pagados']), array('repartido', 4));
nt_prueba('faccion: un aviso por personaje que cobro, tambien el del actor', rd_avisos('salario_recibido'), array(
    array(5, 'salario_faccion:2026-11-23', array('monto' => 1250000, 'moneda' => 'Berries', 'concepto' => 'rango de facción Capitan')),
    array(10, 'salario_faccion:2026-11-23', array('monto' => 10000, 'moneda' => 'Berries', 'concepto' => 'rango de facción ReclutaM')),
    array(11, 'salario_faccion:2026-11-23', array('monto' => 1250000, 'moneda' => 'Berries', 'concepto' => 'rango de facción Capitan')),
    array(13, 'salario_faccion:2026-11-23', array('monto' => 10000, 'moneda' => 'Berries', 'concepto' => 'rango de facción ReclutaM')),
));
nt_prueba('faccion: texto del aviso', opg_notif_texto('salario_recibido', rd_avisos('salario_recibido')[2][2]),
    'Recibiste 1.250.000 Berries de salario (rango de facción Capitan).');
nt_prueba('faccion: contador del destinatario', (int) nt_valor('SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = 11'), 1);

$r2 = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunes);
nt_prueba('faccion: repetir la semana no paga ni avisa', array($r2['codigo'], rd_contar('salario_recibido')), array('ya_repartido', 4));
// Reintentar el aviso de la misma semana (por ejemplo tras un fallo) no duplica.
opg_sf_avisar(array(array('uid' => 10, 'monto' => 10000, 'concepto' => 'rango de facción ReclutaM')), '2026-11-23', 0);
nt_prueba('faccion: reintento del aviso sin duplicar', rd_contar('salario_recibido'), 4);

// Semana siguiente, automatica (actor 0): avisa de nuevo, tambien a quien ejecuto a mano la anterior.
$lunes2 = (new DateTimeImmutable('2026-11-30 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$r3 = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunes2);
nt_prueba('faccion: semana nueva avisa a los 4', array($r3['codigo'], (int) nt_valor("SELECT COUNT(*) FROM mybb_op_notificaciones WHERE clave_evento = 'salario_faccion:2026-11-30'")), array('repartido', 4));

// El aviso falla (columna ausente): el reparto se hace y se confirma igual.
nt_sql('ALTER TABLE mybb_op_notificaciones DROP COLUMN datos_json');
$lunes3 = (new DateTimeImmutable('2026-12-07 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$r4 = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunes3);
nt_prueba('faccion: con el aviso fallando el reparto se hace', array($r4['codigo'], (int) nt_valor('SELECT berries FROM mybb_op_fichas WHERE fid = 13')), array('repartido', 30000));
nt_ok(nt_registro_leer() !== '', 'faccion: el fallo del aviso queda registrado');

// Sin tablas de notificaciones el modulo funciona como antes.
nt_sql('DROP TABLE mybb_op_notificaciones');
opg_notif_tablas_listas(true);
$lunes4 = (new DateTimeImmutable('2026-12-14 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$r5 = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunes4);
nt_prueba('faccion: sin tablas el reparto se hace', array($r5['codigo'], (int) nt_valor('SELECT berries FROM mybb_op_fichas WHERE fid = 13')), array('repartido', 40000));
nt_prueba('recompensa: sin tablas opg_rec_avisar devuelve false', opg_rec_avisar(array(10), 'recompensa_entregada', array('berries' => 5), 'x:1', 1), false);

// Vuelve a crear las tablas para el resto.
nt_sql('DROP TABLE mybb_op_notificaciones_contador');
$error = nt_ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/notificaciones_migration.sql'));
nt_prueba('migracion repetida', $error, null);
opg_notif_tablas_listas(true);

// ── Salario de staff ────────────────────────────────────────────────────────────────────────────────────────────────
// Lote 1 pagado por el admin 3 (que tambien cobra: recibe su propio aviso). Primer pago del lote: id 41.
$lote1 = array(
    array('uid' => 3, 'monto' => 500, 'concepto' => 'salario de staff, Administradores'),
    array('uid' => 7, 'monto' => 400, 'concepto' => 'salario de staff, Staff / Mods'),
    array('uid' => 8, 'monto' => 400, 'concepto' => 'salario de staff, Staff / Mods'),
    array('uid' => 9, 'monto' => 200, 'concepto' => 'salario de staff, Narradores'),
);
$n = opg_rec_avisar_salarios($lote1, 'Kuros', opg_rec_clave_salario_staff(41), 3);
nt_prueba('staff: un aviso por destinatario, tambien el pagador', $n, 4);
nt_prueba('staff: importes y concepto', rd_avisos('salario_recibido'), array(
    array(3, 'salario_staff:41', array('monto' => 500, 'moneda' => 'Kuros', 'concepto' => 'salario de staff, Administradores')),
    array(7, 'salario_staff:41', array('monto' => 400, 'moneda' => 'Kuros', 'concepto' => 'salario de staff, Staff / Mods')),
    array(8, 'salario_staff:41', array('monto' => 400, 'moneda' => 'Kuros', 'concepto' => 'salario de staff, Staff / Mods')),
    array(9, 'salario_staff:41', array('monto' => 200, 'moneda' => 'Kuros', 'concepto' => 'salario de staff, Narradores')),
));
nt_prueba('staff: repetir el aviso del mismo lote no duplica', opg_rec_avisar_salarios($lote1, 'Kuros', opg_rec_clave_salario_staff(41), 3), 0);
// Otro pago la misma semana (pago por UID, id 45): avisa aunque el destinatario ya cobro el lote.
$n = opg_rec_avisar_salarios(array(array('uid' => 7, 'monto' => 150, 'concepto' => 'pago de staff')), 'Kuros', opg_rec_clave_salario_staff(45), 3);
nt_prueba('staff: un segundo pago distinto tambien avisa', array($n, (int) nt_valor('SELECT COUNT(*) FROM mybb_op_notificaciones WHERE uid = 7')), array(1, 2));

// ── Recompensa de un lote (berries_masivo) ──────────────────────────────────────────────────────────────────────────
$uids = range(100, 349); // 250 fichas: la libreria parte el lote en bloques de 200
$desde = count($db->sentencias);
$n = opg_rec_avisar($uids, 'recompensa_entregada', opg_rec_datos_recompensa(array('berries' => 50000), 'entrega masiva de Berries'),
    opg_rec_clave_berries_masivo(1, 1700000000), 1);
nt_prueba('masivo: una notificacion por ficha', array($n, rd_contar('recompensa_entregada')), array(250, 250));
nt_prueba('masivo: inserciones por bloques de 200, una sola llamada', rd_inserciones($desde), 2);
nt_prueba('masivo: repetir el lote no duplica', opg_rec_avisar($uids, 'recompensa_entregada', array('berries' => 50000), opg_rec_clave_berries_masivo(1, 1700000000), 1), 0);
nt_prueba('masivo: texto', opg_notif_texto('recompensa_entregada', rd_avisos('recompensa_entregada')[0][2]),
    'Recibiste 50.000 Berries por entrega masiva de Berries. Revisa tu ficha.');
nt_prueba('masivo: enlace guardado', nt_valor("SELECT url FROM mybb_op_notificaciones WHERE tipo = 'recompensa_entregada' LIMIT 1"), 'op/personaje.php');

nt_borrar();
nt_resumen('recompensas_db');
