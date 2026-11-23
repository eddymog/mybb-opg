<?php
/**
 * Avisos de Akuma con MySQL real (issue #18): el editor de ficha del staff avisa al dueño una sola vez, solo si el
 * valor cambió, con el nombre de la fruta de mybb_op_akumas, y nunca falla por el aviso.
 *
 *   php tests/notificaciones/akuma_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n18, que crea
 * con docs/notificaciones_migration.sql y unas tablas minimas de fichas y frutas, y borra al terminar.
 */

require __DIR__ . '/bootstrap_db.php';
$db = nt_preparar('opg_notif_n18');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/akuma_avisos.php';
nt_registro_iniciar();

$db->query("CREATE TABLE mybb_op_fichas (fid INT(10) NOT NULL PRIMARY KEY, nombre VARCHAR(50) NOT NULL DEFAULT '',"
    . " akuma VARCHAR(100) NOT NULL DEFAULT '', akuma_subnombre VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_akumas (akuma_id VARCHAR(20) NOT NULL PRIMARY KEY, nombre VARCHAR(50) NOT NULL,"
    . " subnombre VARCHAR(50) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("INSERT INTO mybb_op_akumas (akuma_id, nombre) VALUES ('gomu', 'Gomu Gomu no Mi'), ('mera', 'Mera Mera no Mi'),"
    . " ('hito', '<b>Hito</b> Hito no Mi')");

const STAFF = 1;
const HORA = 1700000000;

function ak_ficha($fid, $akuma = '')
{
    global $db;
    $db->query("INSERT INTO mybb_op_fichas (fid, nombre, akuma) VALUES ($fid, 'Pj $fid', '" . $db->escape_string($akuma) . "')");
}

/**
 * Lo mismo que hace el POST de op/staff/ficha_atributos.php (y ficha_atributos2.php) con el campo akuma: compara, arma
 * el UPDATE y, justo despues, avisa si el UPDATE cambio la fila. Devuelve lo que devolvio el aviso, o null si no se llamo.
 */
function ak_guardar_como_staff($fid, $valor, $actor = STAFF, $hora = HORA)
{
    global $db;
    $f_var = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . (int) $fid . "'"));
    $cambiados = array();
    $set_parts = array();
    if ($valor !== (string) $f_var['akuma']) {
        $set_parts[] = "`akuma`='" . $db->escape_string($valor) . "'";
        $cambiados['akuma'] = $valor;
    }
    $resultado = null;
    if ($set_parts) {
        $db->query("UPDATE `mybb_op_fichas` SET " . implode(', ', $set_parts) . " WHERE `fid`='" . (int) $fid . "'");
        if (array_key_exists('akuma', $cambiados) && (int) $db->affected_rows() > 0) {
            $resultado = opg_akuma_avisar_cambio((int) $f_var['fid'], $f_var['akuma'], $cambiados['akuma'], $actor, $hora);
        }
    }
    return $resultado;
}
function ak_avisos($uid)
{
    $r = nt_sql("SELECT tipo, clave_evento FROM mybb_op_notificaciones WHERE uid = $uid ORDER BY id");
    $salida = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $salida[] = $f['tipo'] . '|' . $f['clave_evento'];
    }
    return $salida;
}
function ak_datos($uid, $tipo)
{
    return json_decode(nt_valor("SELECT datos_json FROM mybb_op_notificaciones WHERE uid = $uid AND tipo = '$tipo' ORDER BY id DESC LIMIT 1"), true);
}
function ak_contador($uid)
{
    return (int) nt_valor("SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = $uid");
}

// ── Asignar por id ──
ak_ficha(10);
ak_guardar_como_staff(10, 'gomu');
nt_prueba('asignar: ficha cambiada', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 10'), 'gomu');
nt_prueba('asignar: un aviso', ak_avisos(10), array('akuma_asignada|akuma:10:gomu:asignada:' . HORA));
$fila = nt_una("SELECT categoria, origen, importancia, actor_uid FROM mybb_op_notificaciones WHERE uid = 10");
nt_prueba('asignar: categoria, origen, importancia, actor', array($fila['categoria'], $fila['origen'], $fila['importancia'], (int) $fila['actor_uid']),
    array('tecnicas', 'gestion', 'normal', STAFF));
nt_prueba('asignar: nombre de mybb_op_akumas', ak_datos(10, 'akuma_asignada'), array('fid' => 10, 'fruta' => 'Gomu Gomu no Mi'));
nt_prueba('asignar: contador', ak_contador(10), 1);
nt_prueba('asignar: el staff no se avisa', ak_avisos(STAFF), array());

// Guardar otra vez con el mismo valor: no hay UPDATE ni aviso
nt_prueba('mismo valor: no se llama al aviso', ak_guardar_como_staff(10, 'gomu', STAFF, HORA + 60), null);
nt_prueba('mismo valor: sin aviso nuevo', array(count(ak_avisos(10)), ak_contador(10)), array(1, 1));

// Reintento del mismo hecho (misma hora): la clave evita el duplicado
nt_prueba('reintento: no duplica', opg_akuma_avisar_cambio(10, '', 'gomu', STAFF, HORA) !== false, true);
nt_prueba('reintento: sigue un aviso', array(count(ak_avisos(10)), ak_contador(10)), array(1, 1));

// ── Cambio de fruta y retirada ──
ak_guardar_como_staff(10, 'mera', STAFF, HORA + 100);
nt_prueba('cambio de fruta: asignada con la nueva', ak_avisos(10)[1], 'akuma_asignada|akuma:10:mera:asignada:' . (HORA + 100));
nt_prueba('cambio de fruta: datos', ak_datos(10, 'akuma_asignada'), array('fid' => 10, 'fruta' => 'Mera Mera no Mi'));
ak_guardar_como_staff(10, '', STAFF, HORA + 200);
nt_prueba('retirar: aviso con la fruta que tenia', ak_avisos(10)[2], 'akuma_retirada|akuma:10:mera:retirada:' . (HORA + 200));
nt_prueba('retirar: datos', ak_datos(10, 'akuma_retirada'), array('fid' => 10, 'fruta' => 'Mera Mera no Mi'));
nt_prueba('retirar: importante', nt_valor("SELECT importancia FROM mybb_op_notificaciones WHERE uid = 10 AND tipo = 'akuma_retirada'"), 'importante');
nt_prueba('retirar: contador', ak_contador(10), 3);

// ── Valor guardado por nombre (como la tirada) y valor desconocido ──
ak_ficha(20, 'Gomu Gomu no Mi');
ak_guardar_como_staff(20, '');
nt_prueba('por nombre: retirada', count(ak_avisos(20)), 1);
nt_prueba('por nombre: datos', ak_datos(20, 'akuma_retirada'), array('fid' => 20, 'fruta' => 'Gomu Gomu no Mi'));
ak_ficha(21);
ak_guardar_como_staff(21, "Fruta O'Nueva");
nt_prueba('desconocida: usa el valor', ak_datos(21, 'akuma_asignada'), array('fid' => 21, 'fruta' => "Fruta O'Nueva"));
ak_ficha(22);
ak_guardar_como_staff(22, 'hito');
nt_prueba('nombre con HTML: se guarda sin etiquetas', ak_datos(22, 'akuma_asignada'), array('fid' => 22, 'fruta' => 'Hito Hito no Mi'));

// ── El propio usuario (como la tirada): no se avisa ──
ak_ficha(30);
ak_guardar_como_staff(30, 'gomu', 30);
nt_prueba('propio usuario: ficha cambiada', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 30'), 'gomu');
nt_prueba('propio usuario: sin aviso', ak_avisos(30), array());

// ── Fallos: la accion se completa igual ──
$db->query('RENAME TABLE mybb_op_akumas TO mybb_op_akumas_x');
ak_ficha(40);
ak_guardar_como_staff(40, 'gomu');
nt_prueba('sin tabla de frutas: ficha cambiada', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 40'), 'gomu');
nt_prueba('sin tabla de frutas: avisa con el valor', ak_datos(40, 'akuma_asignada'), array('fid' => 40, 'fruta' => 'gomu'));
$db->query('RENAME TABLE mybb_op_akumas_x TO mybb_op_akumas');

$db->query('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_x');
opg_notif_tablas_listas(true);
ak_ficha(41);
nt_prueba('sin tablas de avisos: no falla', ak_guardar_como_staff(41, 'gomu'), false);
nt_prueba('sin tablas de avisos: ficha cambiada', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 41'), 'gomu');
$db->query('RENAME TABLE mybb_op_notificaciones_x TO mybb_op_notificaciones');
opg_notif_tablas_listas(true);

$db->query('ALTER TABLE mybb_op_notificaciones_contador DROP COLUMN importantes');
ak_ficha(42, 'gomu');
nt_registro_leer();
nt_prueba('envio roto: no falla', ak_guardar_como_staff(42, ''), false);
nt_prueba('envio roto: ficha cambiada', nt_valor('SELECT akuma FROM mybb_op_fichas WHERE fid = 42'), '');
nt_ok(strpos(nt_registro_leer(), 'opg_notificar(akuma_retirada)') !== false, 'envio roto: el error queda registrado');

nt_borrar();
nt_resumen('akuma_db');
