<?php
require __DIR__ . '/bootstrap_db.php';
preparar_esquema($db);
foreach (array('mybb_op_peticionAventuras', 'mybb_op_aventuras_entregas', 'mybb_op_narradores_disponibles', 'mybb_op_audit_aventuras', 'mybb_op_aventuras_entregas_pagos') as $t) {
    $r = una("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema='opg_test' AND table_name='$t'");
    echo "$t: " . ($r['n'] ? 'ok' : 'FALTA') . "\n";
}
$r = sql("SHOW INDEX FROM mybb_op_peticionAventuras");
$idx = array(); while ($f = mysqli_fetch_assoc($r)) { $idx[$f['Key_name']] = 1; }
echo "indices: " . implode(', ', array_keys($idx)) . "\n";
