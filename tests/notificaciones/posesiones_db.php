<?php
/**
 * Avisos de tecnicas, virtudes y objetos cambiados por el staff, con MySQL real (issue #21).
 *
 *   php tests/notificaciones/posesiones_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n21, que crea con
 * docs/notificaciones_migration.sql y unas tablas minimas del juego, y borra al terminar. Las escrituras imitan las de
 * op/staff/*_ficha.php (escritura, affected_rows e insert_id, y luego una sola llamada al aviso).
 *
 * Comprueba:
 *  - Un lote de tecnicas otorgadas crea UN aviso con el resumen; una sola, con su nombre (o el id si no esta en el catalogo).
 *  - Retirar algo que no tenia no avisa; repetir el mismo hecho con la misma clave no duplica.
 *  - El staff que cambia su propia ficha no se avisa.
 *  - Virtudes y objetos (con la cantidad del inventario) avisan con su tipo; los datos no llevan la razon del staff.
 *  - Accion masiva: cada usuario con alguna tecnica modificada recibe un solo aviso con su propio resumen.
 *  - Sin las tablas de notificaciones, o con el aviso fallando, el cambio se hace igual y las funciones no lanzan.
 */

require __DIR__ . '/bootstrap_db.php';

$db = nt_preparar('opg_notif_n21');
require_once MYBB_ROOT . 'inc/plugins/lib/posesiones_avisos.php';
nt_registro_iniciar();

nt_sql("CREATE TABLE `mybb_op_tecnicas` (`tid` VARCHAR(255) NOT NULL PRIMARY KEY, `nombre` VARCHAR(255) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
nt_sql("CREATE TABLE `mybb_op_tec_aprendidas` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `tid` VARCHAR(255), `uid` INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
nt_sql("CREATE TABLE `mybb_op_virtudes` (`virtud_id` VARCHAR(30) NOT NULL PRIMARY KEY, `nombre` VARCHAR(50) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
nt_sql("CREATE TABLE `mybb_op_virtudes_usuarios` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `virtud_id` VARCHAR(255), `uid` INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
nt_sql("CREATE TABLE `mybb_op_objetos` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `objeto_id` VARCHAR(255), `nombre` TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
nt_sql("CREATE TABLE `mybb_op_inventario` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `objeto_id` VARCHAR(255), `uid` INT, `cantidad` INT,"
    . " UNIQUE KEY `obj_uid` (`objeto_id`, `uid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
nt_sql("INSERT INTO `mybb_op_tecnicas` VALUES ('T1', 'Puño de Hierro'), ('T2', 'Corte Rápido'), ('T3', 'Salto'), ('T4', 'Grito')");
nt_sql("INSERT INTO `mybb_op_virtudes` VALUES ('V001', 'Valiente'), ('D024', 'Cobarde')");
nt_sql("INSERT INTO `mybb_op_objetos` (`objeto_id`, `nombre`) VALUES ('KATANA', 'Katana'), ('ESCUDO', 'Escudo <b>de</b> roble')");

const N21_STAFF = 50;

/** Imita el bucle de op/staff/tecnicas_ficha.php y avisa una vez. */
function n21_tecnicas($uid, $accion_form, $tids, $momento)
{
    global $db;
    $ids = array();
    $huellas = array();
    foreach ($tids as $tid) {
        $t = $db->escape_string($tid);
        if ($accion_form === 'Añadir') {
            $db->query("INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES ('$t', '" . (int) $uid . "')");
            if ((int) $db->affected_rows() > 0) { $ids[] = $tid; $huellas[] = 'i' . (int) $db->insert_id(); }
        } else {
            $db->query("DELETE FROM `mybb_op_tec_aprendidas` WHERE tid='$t' AND uid='" . (int) $uid . "'");
            $n = (int) $db->affected_rows();
            if ($n > 0) { $ids[] = $tid; $huellas[] = 'd' . $tid . ':' . $n; }
        }
    }
    return array(opg_pos_avisar('tecnica', $uid, opg_pos_accion($accion_form), $ids, $huellas, N21_STAFF, '', $momento), $ids, $huellas);
}

function n21_avisos($uid, $tipo = null)
{
    $r = nt_sql("SELECT uid, tipo, categoria, origen, actor_uid, clave_evento, datos_json FROM `mybb_op_notificaciones` WHERE uid = " . (int) $uid
        . ($tipo ? " AND tipo = '" . $tipo . "'" : '') . " ORDER BY id");
    $filas = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $f['datos'] = json_decode($f['datos_json'], true);
        $filas[] = $f;
    }
    return $filas;
}

// ── 1. Lote de tecnicas otorgadas: un aviso con el resumen ───────────────────────────────────────────────────────────
list($r) = n21_tecnicas(5, 'Añadir', array('T1', 'T2', 'T9'), 1000);
$a = n21_avisos(5);
nt_prueba('un solo aviso para el lote', array($r, count($a)), array(1, 1));
nt_prueba('tipo, categoria, origen y actor', array($a[0]['tipo'], $a[0]['categoria'], $a[0]['origen'], (int) $a[0]['actor_uid']),
    array('tecnica_cambio', 'tecnicas', 'gestion', N21_STAFF));
nt_prueba('datos con accion y resumen (id si no esta en el catalogo)', $a[0]['datos'],
    array('accion' => 'otorgada', 'cantidad' => 3, 'nombres' => 'Puño de Hierro, Corte Rápido, T9'));
nt_prueba('texto del aviso', opg_notif_texto('tecnica_cambio', $a[0]['datos']), 'El staff te otorgó 3 técnicas (Puño de Hierro, Corte Rápido, T9).');

// ── 2. Retirar una: aviso con su nombre; retirar otra vez no avisa ───────────────────────────────────────────────────
list($r, $ids, $huellas) = n21_tecnicas(5, 'Remover', array('t1'), 1001);
$a = n21_avisos(5);
nt_prueba('retirar avisa con el nombre', array($r, count($a), $a[1]['datos']), array(1, 2, array('accion' => 'retirada', 'cantidad' => 1, 'nombre' => 'Puño de Hierro')));
list($r) = n21_tecnicas(5, 'Remover', array('T1'), 1002);
nt_prueba('retirar lo que ya no tiene no avisa', array($r, count(n21_avisos(5))), array(0, 2));
nt_prueba('repetir el mismo hecho con la misma clave no duplica',
    array(opg_pos_avisar('tecnica', 5, 'retirada', $ids, $huellas, N21_STAFF, '', 1001), count(n21_avisos(5))), array(0, 2));
nt_prueba('el contador del usuario', (int) nt_valor('SELECT sin_leer FROM `mybb_op_notificaciones_contador` WHERE uid = 5'), 2);

// ── 3. El staff que cambia su propia ficha no se avisa ───────────────────────────────────────────────────────────────
list($r) = n21_tecnicas(N21_STAFF, 'Añadir', array('T3'), 1003);
nt_prueba('sin aviso para el propio actor', array($r, count(n21_avisos(N21_STAFF))), array(0, 0));

// ── 4. Por aprender, virtudes y objetos ──────────────────────────────────────────────────────────────────────────────
nt_prueba('tecnica por aprender', opg_pos_avisar('tecnica', 6, 'otorgada', array('T4'), array('i1'), N21_STAFF, 'por_aprender', 1004), 1);
nt_prueba('datos por aprender', n21_avisos(6)[0]['datos'], array('accion' => 'otorgada', 'cantidad' => 1, 'lista' => 'por_aprender', 'nombre' => 'Grito'));

nt_sql("INSERT INTO `mybb_op_virtudes_usuarios` (`virtud_id`, `uid`) VALUES ('V001', 7)");
nt_prueba('virtud otorgada', opg_pos_avisar('virtud', 7, 'otorgada', array('V001', 'd024'), array('i1', 'i2'), N21_STAFF, '', 1005), 1);
$a = n21_avisos(7, 'virtud_cambio');
nt_prueba('datos de virtudes', $a[0]['datos'], array('accion' => 'otorgada', 'cantidad' => 2, 'nombres' => 'Valiente, Cobarde'));

// Objetos: imita op/staff/objetos_ficha.php (dos unidades de KATANA y un ESCUDO)
$ids = array();
$huellas = array();
foreach (array('KATANA', 'KATANA', 'ESCUDO') as $obj) {
    $actual = $db->fetch_array($db->query("SELECT cantidad FROM `mybb_op_inventario` WHERE uid='8' AND objeto_id='$obj'"));
    if ($actual) {
        $nueva = (int) $actual['cantidad'] + 1;
        $db->query("UPDATE `mybb_op_inventario` SET `cantidad`='$nueva' WHERE objeto_id='$obj' AND uid='8'");
        $huella = 'u' . $obj . ':' . $nueva;
    } else {
        $db->query("INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES ('$obj', '8', '1')");
        $huella = 'i' . (int) $db->insert_id();
    }
    if ((int) $db->affected_rows() > 0) { $ids[] = $obj; $huellas[] = $huella; }
}
nt_prueba('objetos otorgados', opg_pos_avisar('objeto', 8, 'otorgada', $ids, $huellas, N21_STAFF, '', 1006), 1);
$a = n21_avisos(8, 'objeto_cambio');
nt_prueba('datos de objetos sin HTML', $a[0]['datos'], array('accion' => 'otorgada', 'cantidad' => 3, 'nombres' => 'Katana, Escudo bde/b roble'));
nt_ok(strpos($a[0]['datos_json'], 'razon') === false, 'los datos no llevan la razon del staff');
nt_prueba('el inventario quedo como antes', nt_valor("SELECT cantidad FROM `mybb_op_inventario` WHERE uid=8 AND objeto_id='KATANA'"), '2');

// ── 5. Accion masiva del editor de tecnicas ──────────────────────────────────────────────────────────────────────────
nt_sql("INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES ('T1', 20), ('T2', 20), ('T1', 21), ('T1', 22), ('T1', 22), ('T3', 23), ('T1', " . N21_STAFF . ")");
$desde = count($db->sentencias);
$r = opg_pos_avisar_tecnicas_modificadas(array('T1', 'T2', 'T1'), N21_STAFF, 2000);
nt_prueba('avisos nuevos de la accion masiva', $r, 4);
$por_uid = array();
foreach (array(5, 20, 21, 22, 23, N21_STAFF) as $u) {
    $por_uid[$u] = array_map(function ($f) { return $f['datos']; }, n21_avisos($u, 'tecnica_cambio'));
}
nt_prueba('cada usuario recibe un aviso con su propio resumen', array($por_uid[20], $por_uid[21], $por_uid[22], $por_uid[23], count($por_uid[N21_STAFF])), array(
    array(array('accion' => 'modificada', 'cantidad' => 2, 'nombres' => 'Puño de Hierro, Corte Rápido')),
    array(array('accion' => 'modificada', 'cantidad' => 1, 'nombre' => 'Puño de Hierro')),
    array(array('accion' => 'modificada', 'cantidad' => 1, 'nombre' => 'Puño de Hierro')),
    array(),
    0,
));
nt_prueba('el usuario 5 tiene T2 y recibe su aviso de modificada', end($por_uid[5]), array('accion' => 'modificada', 'cantidad' => 1, 'nombre' => 'Corte Rápido'));
$inserciones = 0;
foreach (array_slice($db->sentencias, $desde) as $s) {
    if (stripos($s, 'INSERT IGNORE INTO `mybb_op_notificaciones`') === 0) {
        $inserciones++;
    }
}
nt_prueba('una insercion por grupo de resumen (no por fila)', $inserciones, 3);
nt_prueba('repetir la accion masiva con la misma clave no duplica', opg_pos_avisar_tecnicas_modificadas(array('T2', 'T1'), N21_STAFF, 2000), 0);
nt_prueba('sin tecnicas modificadas no hay nada que avisar', opg_pos_avisar_tecnicas_modificadas(array(), N21_STAFF, 2001), 0);

// ── 6. Sin tablas o con el aviso fallando, el cambio se hace igual ───────────────────────────────────────────────────
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador` TO `mybb_op_notificaciones_contador_fuera`');
opg_notif_tablas_listas(true);
list($r) = n21_tecnicas(9, 'Añadir', array('T1'), 3000);
nt_prueba('sin tablas: la tecnica se otorga y el aviso devuelve false', array($r, nt_valor("SELECT COUNT(*) FROM `mybb_op_tec_aprendidas` WHERE uid = 9")), array(false, '1'));
nt_prueba('sin tablas: la masiva no lanza', opg_pos_avisar_tecnicas_modificadas(array('T1'), N21_STAFF, 3001), false);
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador_fuera` TO `mybb_op_notificaciones_contador`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas: no queda aviso', n21_avisos(9), array());

nt_sql('ALTER TABLE `mybb_op_notificaciones` CHANGE `datos_json` `datos_fuera` TEXT NULL');
nt_estricto(true);
list($r) = n21_tecnicas(10, 'Añadir', array('T2'), 3002);
nt_prueba('con el aviso fallando (mysqli estricto) la tecnica se otorga', array($r, nt_valor("SELECT COUNT(*) FROM `mybb_op_tec_aprendidas` WHERE uid = 10")), array(false, '1'));
nt_prueba('con el aviso fallando la masiva no lanza', opg_pos_avisar_tecnicas_modificadas(array('T2'), N21_STAFF, 3003), false);
nt_estricto(false);
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(tecnica_cambio)') !== false, 'el fallo del aviso queda registrado');

nt_borrar();
nt_resumen('posesiones_db');
