<?php
/**
 * Aviso ficha_modificada con MySQL real (issue #22): un guardado del staff produce exactamente UN aviso con todos los
 * campos visibles que cambiaron, sin cambios no avisa, un reenvio no duplica y un fallo del aviso no impide el guardado.
 *
 *   php tests/notificaciones/ficha_modificada_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n22, que crea
 * con docs/notificaciones_migration.sql y unas tablas minimas de fichas y usuarios, y borra al terminar.
 */

require __DIR__ . '/bootstrap_db.php';
$db = nt_preparar('opg_notif_n22');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/ficha_avisos.php';
nt_registro_iniciar();

$db->query("CREATE TABLE mybb_op_fichas (fid INT(10) NOT NULL PRIMARY KEY, nombre VARCHAR(50) NOT NULL DEFAULT '',"
    . " akuma VARCHAR(100) NOT NULL DEFAULT '', fuerza INT NOT NULL DEFAULT 0, nivel INT NOT NULL DEFAULT 1,"
    . " berries BIGINT NOT NULL DEFAULT 0, secret1 VARCHAR(5) NOT NULL DEFAULT '0', hao_chance INT NOT NULL DEFAULT 0)"
    . " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE IF NOT EXISTS mybb_users (uid INT(10) NOT NULL PRIMARY KEY, newpoints DECIMAL(16,2) NOT NULL DEFAULT 0)"
    . " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_sg_sg_fichas (fid INT(10) NOT NULL PRIMARY KEY, espe VARCHAR(50) NOT NULL DEFAULT '',"
    . " espe_estilo VARCHAR(50) NOT NULL DEFAULT '', rango VARCHAR(50) NOT NULL DEFAULT '', edad VARCHAR(10) NOT NULL DEFAULT '',"
    . " notas TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

const STAFF = 1;
const HORA = 1700000000;

function fm_ficha($fid)
{
    global $db;
    $db->query("INSERT INTO mybb_op_fichas (fid, nombre, fuerza, nivel, berries) VALUES ($fid, 'Pj $fid', 10, 1, 100)");
    $db->query("INSERT INTO mybb_users (uid, newpoints) VALUES ($fid, 50)");
}

/**
 * Lo mismo que hace el POST de op/staff/ficha_atributos.php (y ficha_atributos2.php): lee la ficha y el usuario, aplica
 * los cambios (un UPDATE de la ficha y otro de la experiencia) y, al final, avisa una sola vez.
 */
function fm_guardar_atributos($fid, $cambios_ficha, $experiencia = null, $actor = STAFF, $hora = HORA)
{
    global $db;
    $f_var = $db->fetch_array($db->query("SELECT * FROM mybb_op_fichas WHERE fid='" . (int) $fid . "'"));
    $u_var = $db->fetch_array($db->query("SELECT * FROM mybb_users WHERE uid='" . (int) $fid . "'"));
    $set = array();
    foreach ($cambios_ficha as $c => $v) {
        if ((string) $v !== (string) $f_var[$c]) {
            $set[] = "`$c`='" . $db->escape_string($v) . "'";
        }
    }
    if ($set) {
        $db->query("UPDATE mybb_op_fichas SET " . implode(', ', $set) . " WHERE fid='" . (int) $fid . "'");
    }
    if ($experiencia !== null) {
        $db->query("UPDATE mybb_users SET newpoints='" . $db->escape_string($experiencia) . "' WHERE uid='" . (int) $fid . "'");
    }
    return opg_ficha_avisar_atributos((int) $f_var['fid'], $f_var, $u_var ? $u_var['newpoints'] : null, $actor, $hora);
}

/** Lo mismo que hace op/staff/modificar_ficha.php: foto antes, varias escrituras sueltas, un aviso al final. */
function fm_guardar_modificar($fid, $escrituras, $actor = STAFF, $hora = HORA)
{
    global $db;
    $antes = opg_ficha_leer('mybb_sg_sg_fichas', $fid, opg_ficha_campos_modificar());
    foreach ($escrituras as $c => $v) {
        $db->query("UPDATE mybb_sg_sg_fichas SET `$c`='" . $db->escape_string($v) . "' WHERE fid='" . (int) $fid . "'");
    }
    if ($antes === null) {
        return null;
    }
    return opg_ficha_avisar_cambios($fid, $antes, opg_ficha_leer('mybb_sg_sg_fichas', $fid, opg_ficha_campos_modificar()),
        opg_ficha_campos_modificar(), $actor, $hora);
}

function fm_avisos($uid)
{
    $r = nt_sql("SELECT tipo FROM mybb_op_notificaciones WHERE uid = $uid ORDER BY id");
    $salida = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $salida[] = $f['tipo'];
    }
    return $salida;
}
function fm_datos($uid)
{
    return json_decode(nt_valor("SELECT datos_json FROM mybb_op_notificaciones WHERE uid = $uid ORDER BY id DESC LIMIT 1"), true);
}
function fm_contador($uid)
{
    return (int) nt_valor("SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = $uid");
}

// ── ficha_atributos: varios campos en un guardado, un aviso ──
fm_ficha(10);
fm_guardar_atributos(10, array('fuerza' => '15', 'nivel' => '2', 'berries' => '500'), '80');
nt_prueba('atributos: ficha guardada', nt_valor('SELECT CONCAT(fuerza, "/", nivel, "/", berries) FROM mybb_op_fichas WHERE fid = 10'), '15/2/500');
nt_prueba('atributos: un solo aviso', fm_avisos(10), array('ficha_modificada'));
nt_prueba('atributos: lista todos los campos', fm_datos(10), array('fid' => 10, 'campos' => 'Nivel, Experiencia, Berries, Fuerza'));
$fila = nt_una("SELECT categoria, origen, importancia, actor_uid, clave_evento FROM mybb_op_notificaciones WHERE uid = 10");
nt_prueba('atributos: categoria, origen, importancia, actor', array($fila['categoria'], $fila['origen'], $fila['importancia'], (int) $fila['actor_uid']),
    array('ficha', 'gestion', 'normal', STAFF));
nt_ok(strpos($fila['clave_evento'], 'ficha_mod:10:' . HORA . '.') === 0, 'atributos: clave ficha_mod:UID:MARCA (' . $fila['clave_evento'] . ')');
nt_prueba('atributos: contador', fm_contador(10), 1);
nt_prueba('atributos: el staff no se avisa', fm_avisos(STAFF), array());

// Un solo campo: tambien un aviso
fm_guardar_atributos(10, array('fuerza' => '16'), null, STAFF, HORA + 10);
nt_prueba('un campo: un aviso mas', array(count(fm_avisos(10)), fm_datos(10)['campos']), array(2, 'Fuerza'));

// Guardar sin cambios: no avisa
nt_prueba('sin cambios: no avisa', fm_guardar_atributos(10, array('fuerza' => '16'), '80', STAFF, HORA + 20), false);
nt_prueba('sin cambios: sigue igual', array(count(fm_avisos(10)), fm_contador(10)), array(2, 2));

// Solo campos sin aviso (akuma tiene el suyo, identidad secreta, internos): no avisa
nt_prueba('campos excluidos: no avisa', fm_guardar_atributos(10, array('akuma' => 'gomu', 'secret1' => '1', 'hao_chance' => '9'), null, STAFF, HORA + 30), false);
nt_prueba('campos excluidos: ficha guardada', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 10'), 'gomu');
nt_prueba('campos excluidos: sin aviso nuevo', count(fm_avisos(10)), 2);

// Reenvio del mismo guardado (misma marca): la clave evita el duplicado
$antes = array('fuerza' => '16');
$despues = array('fuerza' => '17');
opg_ficha_avisar_cambios(10, $antes, $despues, opg_ficha_campos_atributos(), STAFF, HORA + 40);
opg_ficha_avisar_cambios(10, $antes, $despues, opg_ficha_campos_atributos(), STAFF, HORA + 40);
nt_prueba('reenvio: no duplica', array(count(fm_avisos(10)), fm_contador(10)), array(3, 3));

// El propio usuario: tambien recibe su aviso (queda en su historial)
fm_ficha(30);
fm_guardar_atributos(30, array('nivel' => '5'), null, 30);
nt_prueba('propio usuario: ficha guardada', nt_valor('SELECT nivel FROM mybb_op_fichas WHERE fid = 30'), '5');
nt_prueba('propio usuario: recibe su aviso', fm_avisos(30), array('ficha_modificada'));

// ── modificar_ficha: varias escrituras sueltas, un aviso al final; notas no cuenta ──
$db->query("INSERT INTO mybb_sg_sg_fichas (fid, rango, edad) VALUES (50, 'Genin', '15')");
fm_guardar_modificar(50, array('espe' => 'Taijutsu', 'espe_estilo' => 'Goken', 'rango' => 'Chunin', 'notas' => 'privado'));
nt_prueba('modificar: un solo aviso', fm_avisos(50), array('ficha_modificada'));
nt_prueba('modificar: lista los campos visibles', fm_datos(50), array('fid' => 50, 'campos' => 'Especialización, Estilo de especialización, Rango'));
fm_guardar_modificar(50, array('notas' => 'otra nota'), STAFF, HORA + 5);
nt_prueba('modificar: solo notas no avisa', count(fm_avisos(50)), 1);
nt_prueba('modificar: sin la ficha no avisa', fm_guardar_modificar(51, array('rango' => 'X')), null);

// ── Fallos: el guardado se completa igual ──
$db->query('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_x');
opg_notif_tablas_listas(true);
fm_ficha(41);
nt_prueba('sin tablas de avisos: no falla', fm_guardar_atributos(41, array('nivel' => '3')), false);
nt_prueba('sin tablas de avisos: ficha guardada', nt_valor('SELECT nivel FROM mybb_op_fichas WHERE fid = 41'), '3');
$db->query('RENAME TABLE mybb_op_notificaciones_x TO mybb_op_notificaciones');
opg_notif_tablas_listas(true);

$db->query('ALTER TABLE mybb_op_notificaciones_contador DROP COLUMN importantes');
fm_ficha(42);
nt_registro_leer();
nt_prueba('envio roto: no falla', fm_guardar_atributos(42, array('nivel' => '4')), false);
nt_prueba('envio roto: ficha guardada', nt_valor('SELECT nivel FROM mybb_op_fichas WHERE fid = 42'), '4');
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(ficha_modificada)') !== false, 'envio roto: el error queda registrado');

nt_borrar();
nt_resumen('ficha_modificada_db');
