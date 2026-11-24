<?php
/**
 * op/staff/reset_peticiones.php con MySQL real (issue #31), ejecutando la pagina con harness_reset.php:
 *   - rechazar exige POST y la clave CSRF: por GET o sin clave no escribe nada (ni estado, ni ticket, ni aviso);
 *   - rechazar con clave cambia el estado, devuelve el ticket y avisa una vez; un reset ya aplicado no se rechaza;
 *   - aplicar dos veces a la vez (dos procesos que leen el reset como pendiente) aplica una sola vez: el segundo no
 *     repite los cambios de ficha, no avisa y no deja auditoria.
 *
 *   php tests/notificaciones/reset_pagina_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) con su PROPIA base, opg_notif_n31, que crea
 * con docs/notificaciones_migration.sql y unas tablas minimas, y borra al terminar.
 */

require __DIR__ . '/bootstrap_db.php';
$db = nt_preparar('opg_notif_n31');

$cols = array("fid INT NOT NULL PRIMARY KEY", "nika INT NOT NULL DEFAULT 0", "puntos_oficio INT NOT NULL DEFAULT 0",
    "puntos_estadistica INT NOT NULL DEFAULT 0", "aprobada_por VARCHAR(100) NOT NULL DEFAULT ''", "belicas TEXT", "oficios TEXT",
    "akuma VARCHAR(100) NOT NULL DEFAULT ''", "akuma_subnombre VARCHAR(100) NOT NULL DEFAULT ''", "camino VARCHAR(20) NOT NULL DEFAULT ''",
    "oficio1 VARCHAR(50) NOT NULL DEFAULT ''", "oficio2 VARCHAR(50) NOT NULL DEFAULT ''");
foreach (array('kenbun', 'buso', 'hao', 'dominio_akuma', 'fuerza', 'resistencia', 'reflejos', 'punteria', 'voluntad', 'agilidad', 'destreza') as $c) {
    $cols[] = "$c INT NOT NULL DEFAULT 0";
}
foreach (array('fuerza', 'resistencia', 'reflejos', 'punteria', 'voluntad', 'agilidad', 'destreza') as $c) {
    $cols[] = "{$c}_pasiva INT NOT NULL DEFAULT 0";
}
for ($i = 1; $i <= 12; $i++) {
    $cols[] = "belica$i VARCHAR(50) NOT NULL DEFAULT ''";
}
for ($i = 1; $i <= 4; $i++) {
    $cols[] = "estilo$i VARCHAR(100) NOT NULL DEFAULT ''";
}
$db->query('CREATE TABLE mybb_op_fichas (' . implode(', ', $cols) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->query("CREATE TABLE mybb_op_fichas_reset (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, peticion_id INT NOT NULL DEFAULT 0, uid INT NOT NULL,"
    . " backup_json LONGTEXT NOT NULL, propuesta_json LONGTEXT NOT NULL,"
    . " estado ENUM('pendiente','aplicado','rechazado') NOT NULL DEFAULT 'pendiente', staff_uid INT NULL, staff_nombre VARCHAR(255) NULL,"
    . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, applied_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_peticiones (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, resuelto TINYINT NOT NULL DEFAULT 0,"
    . " mod_uid INT NOT NULL DEFAULT 0, mod_nombre VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_inventario (objeto_id VARCHAR(20) NOT NULL, uid INT NOT NULL, cantidad INT NOT NULL DEFAULT 0,"
    . " imagen VARCHAR(255) NOT NULL DEFAULT '', apodo VARCHAR(255) NOT NULL DEFAULT '', especial TINYINT NOT NULL DEFAULT 0,"
    . " editado TINYINT NOT NULL DEFAULT 0, bautizado TINYINT NOT NULL DEFAULT 0, PRIMARY KEY (uid, objeto_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_virtudes_usuarios (uid INT NOT NULL, virtud_id VARCHAR(10) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_tecnicas (tid VARCHAR(20) NOT NULL PRIMARY KEY, rama VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_tec_aprendidas (tid VARCHAR(20) NOT NULL, uid INT NOT NULL, PRIMARY KEY (tid, uid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_akumas (akuma_id VARCHAR(20) NOT NULL PRIMARY KEY, nombre VARCHAR(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_audit_general (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, uid INT NOT NULL, categoria VARCHAR(100) NOT NULL,"
    . " log TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

const STAFF = 1;

/** Ficha y reset pendiente. La propuesta gana DCO001 (fuerza_pasiva +5, un cambio relativo que se notaria repetido). */
function rp_reset($fid)
{
    global $db;
    $db->query("INSERT INTO mybb_op_fichas (fid, nika, kenbun, buso, hao, belicas, oficios) VALUES ($fid, 1000, 1, 1, 1, '{}', '{}')");
    $db->query('INSERT INTO mybb_op_peticiones (resuelto) VALUES (0)');
    $pet = (int) $db->insert_id();
    $backup = $db->escape_string(json_encode(array('kenbun' => 1, 'buso' => 1, 'hao' => 1)));
    $prop = $db->escape_string(json_encode(array('tecs_ganar' => array('DCO001'), 'tecs_perder' => array())));
    $db->query("INSERT INTO mybb_op_fichas_reset (peticion_id, uid, backup_json, propuesta_json) VALUES ($pet, $fid, '$backup', '$prop')");
    return (int) $db->insert_id();
}

function rp_json($id, $accion, $metodo, $clave, $extra = array())
{
    $p = array('base' => $GLOBALS['nt_base'], 'uid' => STAFF, 'metodo' => $metodo, 'get' => array('id' => (string) $id));
    $datos = array('accion' => $accion, 'p_kenbun' => '1', 'p_buso' => '1', 'p_hao' => '1');
    if ($clave !== null) {
        $datos['my_post_key'] = $clave;
    }
    if ($metodo === 'POST') {
        $p['post'] = $datos;
    } else {
        $p['get'] = array_merge($p['get'], $datos);
    }
    return json_encode(array_merge($p, $extra));
}

function rp_llamar($id, $accion, $metodo = 'POST', $clave = 'clave-valida')
{
    $salida = shell_exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/harness_reset.php') . ' '
        . escapeshellarg(rp_json($id, $accion, $metodo, $clave)) . ' 2>&1');
    $r = json_decode((string) $salida, true);
    if (!is_array($r)) {
        echo "Salida no valida del harness:\n$salida\n";
        return array('cabeceras' => array(), 'alert' => null);
    }
    return $r;
}

function rp_estado($id) { return nt_valor("SELECT estado FROM mybb_op_fichas_reset WHERE id = $id"); }
function rp_tickets($fid) { return (int) nt_valor("SELECT COALESCE(SUM(cantidad), 0) FROM mybb_op_inventario WHERE uid = $fid AND objeto_id = 'TNP001'"); }
function rp_avisos($fid) { return (int) nt_valor("SELECT COUNT(*) FROM mybb_op_notificaciones WHERE uid = $fid"); }
function rp_auditoria($id) { return (int) nt_valor("SELECT COUNT(*) FROM mybb_op_audit_general WHERE log LIKE 'Reset #$id %'"); }

// ── Rechazar sin POST o sin clave: no escribe ──
$id = rp_reset(10);
$r = rp_llamar($id, 'rechazar', 'GET', null);
nt_prueba('rechazar por GET (el enlace antiguo): sigue pendiente', rp_estado($id), 'pendiente');
nt_prueba('rechazar por GET: sin ticket ni aviso', array(rp_tickets(10), rp_avisos(10), rp_auditoria($id)), array(0, 0, 0));
$r = rp_llamar($id, 'rechazar', 'GET', 'clave-valida');
nt_prueba('rechazar por GET con clave: sigue pendiente', array(rp_estado($id), rp_tickets(10)), array('pendiente', 0));
$r = rp_llamar($id, 'rechazar', 'POST', null);
nt_prueba('rechazar por POST sin clave: sigue pendiente', array(rp_estado($id), rp_tickets(10), rp_avisos(10)), array('pendiente', 0, 0));
nt_ok(is_string($r['alert']) && $r['alert'] !== '', 'rechazar sin clave: la pagina lo explica');
$r = rp_llamar($id, 'rechazar', 'POST', 'otra-clave');
nt_prueba('rechazar con clave falsa: sigue pendiente', array(rp_estado($id), rp_tickets(10)), array('pendiente', 0));
nt_prueba('rechazar sin clave: peticion sin resolver', (int) nt_valor('SELECT resuelto FROM mybb_op_peticiones WHERE id = (SELECT peticion_id FROM mybb_op_fichas_reset WHERE id = ' . $id . ')'), 0);

// ── Rechazar con clave ──
$r = rp_llamar($id, 'rechazar');
nt_prueba('rechazar con clave: estado', rp_estado($id), 'rechazado');
nt_prueba('rechazar con clave: ticket, aviso y auditoria', array(rp_tickets(10), rp_avisos(10), rp_auditoria($id)), array(1, 1, 1));
nt_ok(in_array("Location: /op/staff/reset_peticiones.php?success=rechazado&id=$id", $r['cabeceras'], true), 'rechazar con clave: redirige');
rp_llamar($id, 'rechazar');
nt_prueba('rechazar otra vez: no devuelve otro ticket', array(rp_tickets(10), rp_avisos(10)), array(1, 1));

// ── Aplicar ──
$id = rp_reset(20);
$r = rp_llamar($id, 'aplicar');
nt_prueba('aplicar: estado', rp_estado($id), 'aplicado');
nt_prueba('aplicar: ficha, tecnica, aviso y auditoria', array((int) nt_valor('SELECT fuerza_pasiva FROM mybb_op_fichas WHERE fid = 20'),
    (int) nt_valor("SELECT COUNT(*) FROM mybb_op_tec_aprendidas WHERE uid = 20 AND tid = 'DCO001'"), rp_avisos(20), rp_auditoria($id)),
    array(5, 1, 1, 1));
nt_ok(in_array("Location: /op/staff/reset_peticiones.php?success=aplicado&id=$id", $r['cabeceras'], true), 'aplicar: redirige');
rp_llamar($id, 'aplicar');
nt_prueba('aplicar otra vez: nada cambia', array((int) nt_valor('SELECT fuerza_pasiva FROM mybb_op_fichas WHERE fid = 20'), rp_avisos(20), rp_auditoria($id)),
    array(5, 1, 1));

// Un reset ya aplicado no se puede rechazar (ni devuelve el ticket).
rp_llamar($id, 'rechazar');
nt_prueba('rechazar un reset aplicado: sigue aplicado y sin ticket', array(rp_estado($id), rp_tickets(20)), array('aplicado', 0));

// ── Aplicar dos veces a la vez: los dos procesos leen el reset como pendiente y la ficha antes de escribir ──
$id = rp_reset(30);
$procesos = array();
foreach (array(0, 1) as $n) {
    $json = rp_json($id, 'aplicar', 'POST', 'clave-valida', array('pausa_tras' => 'SELECT * FROM mybb_op_fichas WHERE fid', 'pausa_ms' => 1500));
    $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/harness_reset.php') . ' ' . escapeshellarg($json);
    $tubos = array();
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $tubos);
    $procesos[] = array($proc, $tubos);
}
$salidas = array();
foreach ($procesos as $pp) {
    $salidas[] = json_decode(stream_get_contents($pp[1][1]), true);
    $err = stream_get_contents($pp[1][2]);
    fclose($pp[1][1]);
    fclose($pp[1][2]);
    proc_close($pp[0]);
    if ($err !== '') {
        echo "stderr del proceso: $err\n";
    }
}
$redirigidos = 0;
$rechazados = 0;
foreach ($salidas as $s) {
    if (is_array($s) && in_array("Location: /op/staff/reset_peticiones.php?success=aplicado&id=$id", $s['cabeceras'], true)) {
        $redirigidos++;
    } elseif (is_array($s) && is_string($s['alert']) && $s['alert'] !== '') {
        $rechazados++;
    }
}
nt_prueba('concurrente: uno aplica y el otro lo rechaza', array($redirigidos, $rechazados), array(1, 1));
nt_prueba('concurrente: fuerza_pasiva sumada una sola vez', (int) nt_valor('SELECT fuerza_pasiva FROM mybb_op_fichas WHERE fid = 30'), 5);
nt_prueba('concurrente: un solo aviso y una sola auditoria', array(rp_avisos(30), rp_auditoria($id)), array(1, 1));
nt_prueba('concurrente: estado', rp_estado($id), 'aplicado');

nt_borrar();
nt_resumen('reset_pagina_db');
