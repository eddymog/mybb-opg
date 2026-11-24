<?php
/**
 * Proceso auxiliar de tests/consumir/concurrencia_db.php: espera a la hora de salida y crea un post con
 * [consumir=OBJETO] con el plugin real, como lo haria una peticion de MyBB. Imprime el mensaje guardado.
 *   php tests/consumir/worker.php BASE UID TID OBJETO HORA_DE_SALIDA
 */
if (PHP_SAPI !== 'cli' || $argc < 6) {
    echo "Ejecuta tests/consumir/concurrencia_db.php\n";
    exit(2);
}
require __DIR__ . '/harness_plugins.php';

list(, $base, $uid, $tid, $objeto, $salida) = $argv;
nt_estricto(false);
$db = new OpgNotifTestDb($base);
$GLOBALS['db'] = $db;
$GLOBALS['nt_base'] = $base;
cs_cargar_plugins(array('BBCustom_consumir'));

// Los dos procesos ya estan conectados y con el plugin cargado: salen a la vez
while (microtime(true) < (float)$salida) {
    usleep(200);
}
$pid = cs_crear_post((int)$uid, (int)$tid, "[consumir=$objeto]");
echo cs_mensaje($pid);
