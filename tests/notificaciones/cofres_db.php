<?php
/**
 * Avisos del reparto de cofres con MySQL real (issue #23).
 *
 *   php tests/notificaciones/cofres_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n23, que crea con
 * docs/notificaciones_migration.sql y una mybb_op_objetos minima, y borra al terminar.
 *
 * Comprueba (con las mismas llamadas que hace op/staff/cofres_repartir.php despues de su COMMIT):
 *  - Un reparto a varios personajes hace UNA insercion de avisos y deja un aviso cofres_repartidos por destinatario,
 *    con el nombre visible del cofre y sin datos del staff.
 *  - Repetir el aviso del mismo lote no duplica; el staff que reparte no se avisa a si mismo.
 *  - La anulacion avisa con cofres_revertidos (otra clave) solo a la lista que recibe.
 *  - Un cofre sin fila en mybb_op_objetos se avisa sin nombre.
 *  - Sin las tablas de notificaciones, o con el aviso fallando, opg_cof_avisar devuelve false sin lanzar.
 */

require __DIR__ . '/bootstrap_db.php';

$db = nt_preparar('opg_notif_n23');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/cofres_avisos.php';
nt_registro_iniciar();

nt_sql("CREATE TABLE `mybb_op_objetos` (`objeto_id` VARCHAR(20) NOT NULL PRIMARY KEY, `nombre` VARCHAR(100) NOT NULL DEFAULT '')"
    . " ENGINE=InnoDB DEFAULT CHARSET=utf8");
nt_sql("INSERT INTO `mybb_op_objetos` (`objeto_id`, `nombre`) VALUES ('CFF003', 'Cofre de Oro'), ('CFF010', 'Cofre Decente')");

function nco_avisos($clave)
{
    global $db;
    $r = nt_sql("SELECT uid, tipo, categoria, origen, importancia, actor_uid, datos_json FROM `mybb_op_notificaciones` WHERE clave_evento = '"
        . $db->escape_string($clave) . "' ORDER BY uid");
    $filas = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $filas[] = $f;
    }
    return $filas;
}
function nco_inserciones($desde)
{
    global $db;
    $n = 0;
    foreach (array_slice($db->sentencias, $desde) as $s) {
        if (stripos($s, 'INSERT IGNORE INTO `mybb_op_notificaciones`') === 0) {
            $n++;
        }
    }
    return $n;
}

// ── 1. Reparto masivo: una insercion, un aviso por destinatario ─────────────────────────────────────────────────────
$uids = array(5, 6, 7, 10);
$desde = count($db->sentencias);
nt_prueba('el reparto crea 3 avisos (el staff 10 queda fuera)', opg_cof_avisar('cofres_repartidos', $uids, 31, 'CFF003', 2, 10), 3);
nt_prueba('una sola insercion de avisos para todo el lote', nco_inserciones($desde), 1);
$avisos = nco_avisos('cofres:31:repartidos');
nt_prueba('destinatarios del reparto', array_map('intval', array_column($avisos, 'uid')), array(5, 6, 7));
nt_prueba('tipo, categoria, origen, importancia y actor', array($avisos[0]['tipo'], $avisos[0]['categoria'], $avisos[0]['origen'],
    $avisos[0]['importancia'], (int) $avisos[0]['actor_uid']), array('cofres_repartidos', 'recompensas', 'gestion', 'normal', 10));
nt_prueba('datos: lote, cantidad y nombre visible, sin staff', json_decode($avisos[0]['datos_json'], true),
    array('reparto_id' => 31, 'cantidad' => 2, 'cofre' => 'Cofre de Oro'));

// ── 2. Reintentar no duplica ─────────────────────────────────────────────────────────────────────────────────────────
nt_prueba('repetir el aviso del mismo lote no crea filas', opg_cof_avisar('cofres_repartidos', $uids, 31, 'CFF003', 2, 10), 0);
nt_prueba('siguen 3 avisos del lote', count(nco_avisos('cofres:31:repartidos')), 3);

// ── 3. Anulacion: otra clave, solo a quien se le retiro algo ────────────────────────────────────────────────────────
nt_prueba('la anulacion avisa a la lista recibida', opg_cof_avisar('cofres_revertidos', array(5, 7), 31, 'CFF003', 2, 10), 2);
$avisos = nco_avisos('cofres:31:revertidos');
nt_prueba('destinatarios de la anulacion', array(array_map('intval', array_column($avisos, 'uid')), $avisos[0]['tipo'], $avisos[0]['importancia']),
    array(array(5, 7), 'cofres_revertidos', 'importante'));
nt_prueba('la anulacion sin destinatarios no avisa', opg_cof_avisar('cofres_revertidos', array(), 32, 'CFF003', 1, 10), false);

// ── 4. Cofre sin fila en mybb_op_objetos ─────────────────────────────────────────────────────────────────────────────
nt_prueba('cofre desconocido', opg_cof_avisar('cofres_repartidos', array(8), 33, 'CFF999', 1, 10), 1);
nt_prueba('se avisa sin nombre', json_decode(nco_avisos('cofres:33:repartidos')[0]['datos_json'], true), array('reparto_id' => 33, 'cantidad' => 1));

// ── 5. Tablas ausentes o aviso fallando: false, sin lanzar ──────────────────────────────────────────────────────────
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador` TO `mybb_op_notificaciones_contador_fuera`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas devuelve false', opg_cof_avisar('cofres_repartidos', array(9), 34, 'CFF003', 1, 10), false);
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador_fuera` TO `mybb_op_notificaciones_contador`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas no queda aviso', nco_avisos('cofres:34:repartidos'), array());

nt_sql('ALTER TABLE `mybb_op_notificaciones` CHANGE `datos_json` `datos_fuera` TEXT NULL');
nt_estricto(true);
$resultado = 'lanzo';
try {
    $resultado = opg_cof_avisar('cofres_repartidos', array(9), 35, 'CFF003', 1, 10);
} catch (Throwable $e) {
    $resultado = 'lanzo';
}
nt_estricto(false);
nt_prueba('con el aviso fallando (mysqli estricto) devuelve false', $resultado, false);
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(cofres_repartidos)') !== false, 'el fallo del aviso queda registrado');

nt_borrar();
nt_resumen('cofres_db');
