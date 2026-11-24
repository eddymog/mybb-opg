<?php
/**
 * Aviso de intercambio recibido con MySQL real (issue #25).
 *
 *   php tests/notificaciones/intercambio_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n25, que crea con
 * docs/notificaciones_migration.sql y borra al terminar.
 *
 * Comprueba (con la misma llamada que hace op/intercambio.php despues de registrar el intercambio):
 *  - El receptor recibe un aviso intercambio_recibido con clave intercambio:ID, origen usuario y el emisor como actor.
 *  - El emisor no recibe aviso, tampoco si se envia a si mismo.
 *  - Repetir el aviso del mismo intercambio no duplica; otro intercambio entre los mismos usuarios si avisa.
 *  - Sin Berries ni objetos no se crea aviso.
 *  - Sin las tablas de notificaciones, o con el aviso fallando, opg_int_avisar devuelve false sin lanzar.
 */

require __DIR__ . '/bootstrap_db.php';

$db = nt_preparar('opg_notif_n25');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/intercambio_avisos.php';
nt_registro_iniciar();

function nin_avisos($clave)
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

// ── 1. Intercambio con Berries y objetos: aviso al receptor ──────────────────────────────────────────────────────────
nt_prueba('el intercambio avisa al receptor', opg_int_avisar(6, 101, 5, 'Luffy', 2000, array('Espada', 'Espada'), 77), 1);
$avisos = nin_avisos('intercambio:101');
nt_prueba('un solo aviso, para el receptor', array_map('intval', array_column($avisos, 'uid')), array(6));
nt_prueba('tipo, categoria, origen, importancia y actor', array($avisos[0]['tipo'], $avisos[0]['categoria'], $avisos[0]['origen'],
    $avisos[0]['importancia'], (int) $avisos[0]['actor_uid']), array('intercambio_recibido', 'recompensas', 'usuario', 'normal', 5));
nt_prueba('datos: emisor, Berries, objetos y tema, sin razon ni facciones', json_decode($avisos[0]['datos_json'], true),
    array('intercambio_id' => 101, 'actor' => 'Luffy', 'berries' => 2000, 'objetos' => 'Espada x2', 'n_objetos' => 2, 'tid' => 77));
nt_prueba('el emisor no tiene avisos', (int) nt_valor("SELECT COUNT(*) FROM `mybb_op_notificaciones` WHERE uid = 5"), 0);

// ── 2. Reintentar no duplica; otro intercambio si avisa ─────────────────────────────────────────────────────────────
nt_prueba('repetir el aviso del mismo intercambio no crea filas', opg_int_avisar(6, 101, 5, 'Luffy', 2000, array('Espada'), 77), 0);
nt_prueba('sigue un aviso del intercambio', count(nin_avisos('intercambio:101')), 1);
nt_prueba('otro intercambio entre los mismos avisa', opg_int_avisar(6, 102, 5, 'Luffy', 0, array('Ron'), 77), 1);

// ── 3. Casos sin aviso ──────────────────────────────────────────────────────────────────────────────────────────────
nt_prueba('sin Berries ni objetos no avisa', opg_int_avisar(6, 103, 5, 'Luffy', 0, array(), 77), false);
nt_prueba('sin Berries ni objetos no queda aviso', nin_avisos('intercambio:103'), array());
nt_prueba('enviarse a si mismo no avisa', opg_int_avisar(5, 104, 5, 'Luffy', 10, array(), 77), 0);

// ── 4. Tablas ausentes o aviso fallando: false, sin lanzar ──────────────────────────────────────────────────────────
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador` TO `mybb_op_notificaciones_contador_fuera`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas devuelve false', opg_int_avisar(6, 105, 5, 'Luffy', 10, array(), 77), false);
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador_fuera` TO `mybb_op_notificaciones_contador`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas no queda aviso', nin_avisos('intercambio:105'), array());

nt_sql('ALTER TABLE `mybb_op_notificaciones` CHANGE `datos_json` `datos_fuera` TEXT NULL');
nt_estricto(true);
$resultado = 'lanzo';
try {
    $resultado = opg_int_avisar(6, 106, 5, 'Luffy', 10, array(), 77);
} catch (Throwable $e) {
    $resultado = 'lanzo';
}
nt_estricto(false);
nt_prueba('con el aviso fallando (mysqli estricto) devuelve false', $resultado, false);
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(intercambio_recibido)') !== false, 'el fallo del aviso queda registrado');

nt_borrar();
nt_resumen('intercambio_db');
