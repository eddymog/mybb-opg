<?php

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'objetos_armas_todas.php');

require_once './../global.php';

$armas = array();
$consulta_armas = $db->query("SELECT * FROM mybb_op_objetos WHERE LOWER(categoria)='armas' ORDER BY subcategoria ASC, CAST(tier AS UNSIGNED) ASC, nombre ASC, objeto_id ASC");

while ($arma = $db->fetch_array($consulta_armas)) {
    $armas[] = $arma;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'total' => count($armas),
    'armas' => $armas,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
