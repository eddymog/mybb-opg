<?php
// php tests/xp/snapshot.php  (logica pura, sin base de datos)
define('IN_MYBB', 1);
define('TABLE_PREFIX', 'mybb_');
require __DIR__ . '/../../inc/plugins/lib/xp_snapshot.php';
$fallos = 0;
function prueba($n, $a, $e) { global $fallos; if ($a !== $e) { $fallos++; echo "FALLO $n: " . var_export($a, true) . " != " . var_export($e, true) . "\n"; } else { echo "ok $n\n"; } }
prueba('ganancia positiva', opg_xp_ganancia('150.50', '100.00'), 50.5);
prueba('ganancia negativa', opg_xp_ganancia('90', '100'), -10.0);
prueba('sin base', opg_xp_ganancia('90', null), null);
prueba('ventanas', OPG_XP_VENTANAS, array(1, 7, 14));
exit($fallos ? 1 : 0);
