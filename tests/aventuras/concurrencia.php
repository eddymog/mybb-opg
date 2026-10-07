<?php
/**
 * Concurrencia: varios procesos a la vez sobre la misma entrega o solicitud.
 *   php tests/aventuras/concurrencia.php
 */
require __DIR__ . '/bootstrap_db.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';
preparar_esquema($db);
$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(50), 'narra' => array());

foreach (array(3 => 'Eddy', 5 => 'Luffy', 6 => 'Zoro', 8 => 'Robin', 9 => 'Nami', 10 => 'Sanji', 11 => 'Usopp', 50 => 'Staff') as $u => $n) { crear_usuario($u, $n); }
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (15, 'Blue', 0, '15', 1, 1), (40, 'Isla Alfa', 15, '15,40', 1, 2)");
sql("INSERT INTO mybb_users (uid, username, newpoints) VALUES (5, 'Luffy', 0) ON DUPLICATE KEY UPDATE newpoints = 0");
$msg = str_repeat('a', 320);
function tema($tid, $nombre, $db)
{
    global $msg;
    $db->query("SET SESSION sql_mode = ''", 1);
    sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES ($tid, 40, '$nombre', 3, 0, 1, " . time() . ")");
    $pid = $tid * 1000;
    foreach (array(array(8, 'Robin', '[narrador] ' . $msg), array(3, 'Eddy', $msg), array(5, 'Luffy', $msg)) as $p) {
        for ($i = 0; $i < 6; $i++) { sql("INSERT INTO mybb_posts (pid, tid, uid, username, message, dateline) VALUES (" . (++$pid) . ", $tid, {$p[0]}, '{$p[1]}', '" . $db->escape_string($p[2]) . "', " . time() . ")"); }
    }
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);

function lanzar($acciones)
{
    $salida = microtime(true) + 1.5;
    $procs = array();
    foreach ($acciones as $args) {
        $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/worker.php') . ' ' . implode(' ', array_map('escapeshellarg', array_merge($args, array((string) $salida))));
        $p = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        $procs[] = array($p, $pipes);
    }
    $res = array();
    foreach ($procs as $pp) {
        $out = stream_get_contents($pp[1][1]);
        $err = stream_get_contents($pp[1][2]);
        proc_close($pp[0]);
        $j = json_decode($out, true);
        if (!is_array($j)) { echo "Salida inesperada del proceso: $out $err\n"; $j = array('ok' => false, 'codigo' => -1); }
        $res[] = $j;
    }
    return $res;
}

// ── 1. Cuatro pagos simultáneos de la misma entrega ──────────────────────────
tema(300, 'Pago concurrente', $db);
$S = ins(3, 8, 1, array(3, 5), array('tier_seleccionado' => 3, 'isla_fid' => 40));
$e = opg_ent_enviar($S, array('tema' => '300', 'resumen' => 'x', 'reputacion' => 'positiva'), 8);
$E = $e['id'];
$res = lanzar(array(array('pagar', $E), array('pagar', $E), array('pagar', $E), array('pagar', $E)));
$oks = count(array_filter($res, function ($r) { return $r['ok']; }));
prueba('4 pagos simultáneos: solo uno paga', $oks, 1);
prueba('los otros reciben conflicto', count(array_filter($res, function ($r) { return !$r['ok'] && in_array($r['codigo'], array(409, 422)); })), 3);
$f3 = una("SELECT f.nika, u.newpoints FROM mybb_op_fichas f JOIN mybb_users u ON u.uid = f.fid WHERE f.fid = 3");
$f8 = una("SELECT f.nika, f.expNarradorMensualActual AS exp_mes, u.newpoints FROM mybb_op_fichas f JOIN mybb_users u ON u.uid = f.fid WHERE f.fid = 8");
prueba('el jugador cobró una sola vez (T3, 100%: 20 nikas, 120 de experiencia)', array($f3['nika'], (float) $f3['newpoints']), array('20', 120.0));
prueba('el narrador cobró una sola vez (nikas y contador mensual)', array($f8['nika'], $f8['exp_mes'], (float) $f8['newpoints']), array('22', '120', 120.0));
prueba('un solo mensaje en el tema', (int) una("SELECT COUNT(*) AS n FROM mybb_posts WHERE tid = 300 AND message LIKE '%RECOMPENSAS PROCESADAS%'")['n'], 1);
prueba('tres filas de pago y todas aplicadas', array((int) una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E")['n'], (int) una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E AND aplicado_at IS NOT NULL AND xp_estado = 'aplicado'")['n']), array(3, 3));
prueba('cofre una sola vez', (int) una("SELECT cantidad FROM mybb_op_inventario WHERE uid = 8 AND objeto_id = 'CFN003'")['cantidad'], 2);

// ── 2. Postulaciones simultáneas ─────────────────────────────────────────────
$P = ins(3, null, 4, array(3, 5));
$res = lanzar(array(array('postular', $P, 8), array('postular', $P, 9), array('postular', $P, 10), array('postular', $P, 11)));
prueba('4 narradores a la vez: solo uno se queda la aventura', count(array_filter($res, function ($r) { return $r['ok']; })), 1);
$f = fila($P);
prueba('queda en estado 0 con un único narrador', array((int) $f['estado'], in_array((int) $f['narrador_uid'], array(8, 9, 10, 11), true)), array(0, true));

// ── 3. Aprobaciones simultáneas ──────────────────────────────────────────────
$A = ins(3, 8, 0, array(3, 5));
$res = lanzar(array(array('aprobar', $A), array('aprobar', $A), array('aprobar', $A)));
prueba('3 aprobaciones a la vez: solo una', count(array_filter($res, function ($r) { return $r['ok']; })), 1);
prueba('una sola entrada de auditoría', (int) una("SELECT COUNT(*) AS n FROM mybb_op_audit_aventuras WHERE accion = 'cambiar_estado' AND peticion_id = $A")['n'], 1);

// ── 4. Dos entregas del mismo tema ──────────────────────────────────────────
tema(301, 'Tema disputado', $db);
$S1 = ins(3, 8, 1, array(3), array('isla_fid' => 40));
$S2 = ins(3, 9, 1, array(5), array('isla_fid' => 40));
$res = lanzar(array(array('enviar', $S1, 301, 8), array('enviar', $S2, 301, 9)));
prueba('dos solicitudes que entregan el mismo tema a la vez: solo una lo consigue', count(array_filter($res, function ($r) { return $r['ok']; })), 1);
prueba('el tema queda en una sola entrega vigente', (int) una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_entregas WHERE tid_activo = 301")['n'], 1);

// Dos solicitudes diferentes compiten por el ultimo cupo del mismo jugador.
limpiar();
for ($i = 0; $i < 3; $i++) { ins(3, 8, 1, array(3, 5)); }
$res = lanzar(array(array('crear', 20), array('crear', 21)));
prueba('dos creaciones simultáneas con un cupo: solo una se inserta', count(array_filter($res, function ($r) { return $r['ok']; })), 1);
prueba('la otra creación recibe error de límite', count(array_filter($res, function ($r) { return !$r['ok'] && $r['codigo'] === 422; })), 1);
prueba('ningún jugador supera cuatro activas tras envíos simultáneos', opg_av_contar_activas(3), array(0, 4));
resumen();
