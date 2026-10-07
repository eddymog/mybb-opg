<?php

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'tecnicas_belicas_todas.php');

require_once './../global.php';

$tecnicas = array();
$consulta_tecnicas = $db->query('SELECT tid, nombre, estilo, rama, clase, tipo, tier, efectos, descripcion, requisitos, energia, energia_turno, haki, haki_turno, enfriamiento FROM mybb_op_tecnicas ORDER BY estilo ASC, rama ASC, CAST(tier AS UNSIGNED) ASC, nombre ASC, tid ASC');

while ($tecnica = $db->fetch_array($consulta_tecnicas)) {
    $tecnicas[] = $tecnica;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'total' => count($tecnicas),
    'tecnicas' => $tecnicas,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
