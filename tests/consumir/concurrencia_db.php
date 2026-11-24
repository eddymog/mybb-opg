<?php
/**
 * Consumo concurrente (issue #32): dos procesos publican a la vez un post con [consumir=OBJ] sobre una sola unidad.
 * Solo uno debe consumirla; el otro queda como [consumibleinvalido=OBJ]. Se repite varias rondas.
 * Tambien con dos unidades: los dos consumen y las cantidades restantes que muestran son 1 y 0 (no 0 y 0).
 *   OPG_TEST_DB_PORT=33332 php tests/consumir/concurrencia_db.php
 */
require __DIR__ . '/harness_plugins.php';

$db = cs_preparar('opg_consumir_concurrencia');
cs_objeto('POC001', 'Poción de vida');
$base = $GLOBALS['nt_base'];
$rondas = 12;

/** Lanza dos workers que salen a la vez y devuelve sus dos mensajes guardados. */
function dos_procesos($base, $uid, $tid)
{
    $salida = sprintf('%.6F', microtime(true) + 1.2);
    $procesos = array();
    $tuberias = array();
    for ($i = 0; $i < 2; $i++) {
        $cmd = array(PHP_BINARY, __DIR__ . '/worker.php', $base, (string)$uid, (string)$tid, 'POC001', $salida);
        $procesos[$i] = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $tuberias[$i]);
    }
    $mensajes = array();
    for ($i = 0; $i < 2; $i++) {
        $mensajes[] = trim(stream_get_contents($tuberias[$i][1]));
        $err = trim(stream_get_contents($tuberias[$i][2]));
        if ($err !== '') {
            echo "  (worker $i) $err\n";
        }
        proc_close($procesos[$i]);
    }
    sort($mensajes);
    return $mensajes;
}

$ok_una = 0;
for ($r = 1; $r <= $rondas; $r++) {
    cs_inventario(7, 'POC001', 1);
    $m = dos_procesos($base, 7, 100 + $r);
    if ($m === array('[consumibleinvalido=POC001]', '[consumido=1]') && cs_cantidad(7, 'POC001') === null) {
        $ok_una++;
    } else {
        echo "  ronda $r: " . var_export($m, true) . ' cantidad: ' . var_export(cs_cantidad(7, 'POC001'), true) . "\n";
    }
}
nt_prueba("una unidad y dos procesos a la vez: solo uno consume ($rondas rondas)", $ok_una, $rondas);
nt_prueba('una unidad: un solo registro de consumo por ronda', (int)nt_valor("SELECT COUNT(*) FROM mybb_op_consumir"), $rondas);
nt_prueba('una unidad: una sola auditoria por ronda', (int)nt_valor("SELECT COUNT(*) FROM mybb_op_audit_general WHERE categoria = '[Consumir]'"), $rondas);

$ok_dos = 0;
for ($r = 1; $r <= 4; $r++) {
    cs_inventario(8, 'POC001', 2);
    $m = dos_procesos($base, 8, 200 + $r);
    $restantes = nt_sql("SELECT content FROM mybb_op_consumir WHERE uid = 8 AND tid = " . (200 + $r));
    $vistos = array();
    while ($f = mysqli_fetch_assoc($restantes)) {
        preg_match("/Cantidad restante: <strong style='color: white;'>(\d+)</", $f['content'], $c);
        $vistos[] = (int)$c[1];
    }
    sort($vistos);
    if ($m === array('[consumido=1]', '[consumido=1]') && $vistos === array(0, 1) && cs_cantidad(8, 'POC001') === null) {
        $ok_dos++;
    } else {
        echo "  ronda dos unidades $r: " . var_export(array($m, $vistos), true) . "\n";
    }
}
nt_prueba('dos unidades y dos procesos: ambos consumen y la cantidad restante es exacta (1 y 0)', $ok_dos, 4);

nt_borrar();
nt_resumen('consumir concurrencia_db');
