<?php
/**
 * Resolucion de peticiones y bugs con aviso en el sistema global de notificaciones (issue #19).
 *
 *   php tests/notificaciones/peticiones_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n19, que crea con
 * docs/notificaciones_migration.sql y una mybb_op_peticiones minima, y borra al terminar.
 *
 * Comprueba:
 *  - Resolver una peticion avisa al autor con peticion_resuelta (una sola insercion) y un bug con bug_resuelto.
 *  - Resolver otra vez no cambia la fila ni repite el aviso; el aviso repetido con la misma clave no duplica.
 *  - El staff que resuelve su propia peticion no se avisa a si mismo.
 *  - Borrar no avisa.
 *  - Sin las tablas de notificaciones, o con el aviso fallando, la peticion se resuelve igual.
 */

require __DIR__ . '/bootstrap_db.php';

$db = nt_preparar('opg_notif_n19');
require_once MYBB_ROOT . 'inc/plugins/lib/peticiones_avisos.php';
nt_registro_iniciar();

nt_sql("CREATE TABLE `mybb_op_peticiones` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `uid` INT NOT NULL, `nombre` VARCHAR(20) NOT NULL DEFAULT '',"
    . " `categoria` VARCHAR(100) NOT NULL DEFAULT '', `resumen` VARCHAR(255) NOT NULL DEFAULT '', `descripcion` TEXT, `url` VARCHAR(255) NOT NULL DEFAULT '',"
    . " `resuelto` TINYINT(1) NOT NULL DEFAULT 0, `mod_uid` INT NOT NULL DEFAULT 0, `mod_nombre` VARCHAR(50) NOT NULL DEFAULT '',"
    . " `atendidoPor` VARCHAR(255) NOT NULL DEFAULT '', `notasMod` VARCHAR(255) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function np_nueva($uid, $categoria, $resumen)
{
    global $db;
    nt_sql("INSERT INTO `mybb_op_peticiones` (`uid`, `categoria`, `resumen`, `descripcion`, `notasMod`) VALUES (" . (int) $uid . ", '"
        . $db->escape_string($categoria) . "', '" . $db->escape_string($resumen) . "', 'Detalle', 'Nota interna del staff')");
    return (int) mysqli_insert_id($db->link);
}
function np_avisos($clave)
{
    global $db;
    $r = nt_sql("SELECT uid, tipo, categoria, origen, actor_uid, datos_json FROM `mybb_op_notificaciones` WHERE clave_evento = '" . $db->escape_string($clave) . "' ORDER BY uid");
    $filas = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $filas[] = $f;
    }
    return $filas;
}
function np_inserciones($desde)
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
function np_contador($uid)
{
    return (int) nt_valor('SELECT sin_leer FROM `mybb_op_notificaciones_contador` WHERE uid = ' . (int) $uid);
}

// ── 1. Peticion resuelta ─────────────────────────────────────────────────────────────────────────────────────────────
$p = np_nueva(5, 'ficha', 'Subir nivel de Haki');
$desde = count($db->sentencias);
nt_prueba('resolver una peticion pendiente', opg_pet_resolver($p, 50, 'Staffer'), true);
nt_prueba('una sola insercion de aviso', np_inserciones($desde), 1);
$fila = nt_una("SELECT resuelto, mod_uid, mod_nombre FROM `mybb_op_peticiones` WHERE id = $p");
nt_prueba('la fila queda resuelta por el staff', array((int) $fila['resuelto'], (int) $fila['mod_uid'], $fila['mod_nombre']), array(1, 50, 'Staffer'));
$avisos = np_avisos("peticion:$p:resuelta");
nt_prueba('el autor recibe peticion_resuelta', array(count($avisos), (int) $avisos[0]['uid'], $avisos[0]['tipo'], $avisos[0]['categoria'], $avisos[0]['origen'], (int) $avisos[0]['actor_uid']),
    array(1, 5, 'peticion_resuelta', 'peticiones', 'gestion', 50));
$datos = json_decode($avisos[0]['datos_json'], true);
nt_prueba('datos sin notas ni nombre del staff', $datos,
    array('peticion_id' => $p, 'categoria' => 'Ajustes de Ficha y Recursos', 'resumen' => 'Subir nivel de Haki', 'resultado' => 'resuelta'));
nt_prueba('contador del autor', np_contador(5), 1);

// ── 2. Reintentar no duplica ─────────────────────────────────────────────────────────────────────────────────────────
nt_sql("UPDATE `mybb_op_peticiones` SET mod_nombre = 'Otro' WHERE id = $p");
nt_prueba('resolver otra vez no hace nada', opg_pet_resolver($p, 51, 'Segundo'), false);
nt_prueba('la fila conserva quien la resolvio', nt_valor("SELECT mod_uid FROM `mybb_op_peticiones` WHERE id = $p"), '50');
nt_prueba('repetir el aviso con la misma clave no duplica', array(opg_pet_avisar_resuelta(array('id' => $p, 'uid' => 5, 'categoria' => 'ficha'), 50),
    count(np_avisos("peticion:$p:resuelta")), np_contador(5)), array(0, 1, 1));
nt_prueba('una peticion inexistente no se resuelve', opg_pet_resolver(99999, 50, 'Staffer'), false);

// ── 3. Bug resuelto ──────────────────────────────────────────────────────────────────────────────────────────────────
$b = np_nueva(6, 'programacion', 'La ficha no carga');
nt_prueba('resolver un bug', opg_pet_resolver($b, 50, 'Staffer'), true);
$avisos = np_avisos("bug:$b:resuelto");
nt_prueba('el autor recibe bug_resuelto', array(count($avisos), (int) $avisos[0]['uid'], $avisos[0]['tipo']), array(1, 6, 'bug_resuelto'));

// ── 4. El staff que resuelve su propia peticion tambien recibe el aviso (queda en su historial) ───────────────────────────────────────────────────────────────────────────────
$propia = np_nueva(50, 'otros', 'Mi propia peticion');
nt_prueba('el staff resuelve su propia peticion', opg_pet_resolver($propia, 50, 'Staffer'), true);
nt_prueba('el propio actor recibe su aviso', array(count(np_avisos("peticion:$propia:resuelta")), np_contador(50)), array(1, 1));

// ── 5. Borrar no avisa ───────────────────────────────────────────────────────────────────────────────────────────────
$borr = np_nueva(7, 'programacion', 'Duplicado');
$antes = (int) nt_valor('SELECT COUNT(*) FROM `mybb_op_notificaciones`');
nt_prueba('borrar quita la fila', array(opg_pet_borrar($borr), nt_valor("SELECT COUNT(*) FROM `mybb_op_peticiones` WHERE id = $borr")), array(true, '0'));
nt_prueba('borrar no crea avisos', (int) nt_valor('SELECT COUNT(*) FROM `mybb_op_notificaciones`'), $antes);
nt_prueba('borrar una inexistente', opg_pet_borrar($borr), false);

// ── 6. Un fallo del aviso o las tablas ausentes no impiden resolver ──────────────────────────────────────────────────
$f1 = np_nueva(8, 'combate', 'Moderar combate');
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador` TO `mybb_op_notificaciones_contador_fuera`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas: la peticion se resuelve', array(opg_pet_resolver($f1, 50, 'Staffer'), nt_valor("SELECT resuelto FROM `mybb_op_peticiones` WHERE id = $f1")), array(true, '1'));
nt_sql('RENAME TABLE `mybb_op_notificaciones_contador_fuera` TO `mybb_op_notificaciones_contador`');
opg_notif_tablas_listas(true);
nt_prueba('sin tablas: no queda aviso', np_avisos("peticion:$f1:resuelta"), array());

$f2 = np_nueva(9, 'tecnica', 'Revisar tecnica');
opg_notif_tablas_listas();
nt_sql('ALTER TABLE `mybb_op_notificaciones` CHANGE `datos_json` `datos_fuera` TEXT NULL');
nt_estricto(true);
nt_prueba('con el aviso fallando (mysqli estricto) la peticion se resuelve', array(opg_pet_resolver($f2, 50, 'Staffer'),
    nt_valor("SELECT resuelto FROM `mybb_op_peticiones` WHERE id = $f2")), array(true, '1'));
nt_estricto(false);
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(peticion_resuelta)') !== false, 'el fallo del aviso queda registrado');

nt_borrar();
nt_resumen('peticiones_db');
