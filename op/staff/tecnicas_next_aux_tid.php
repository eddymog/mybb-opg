<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_next_aux_tid.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

if (!is_staff($mybb->user['uid'])) {
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

$q   = $db->query("SELECT tid FROM mybb_op_tecnicas WHERE tid LIKE 'Aux%' ORDER BY tid");
$max = 0;
while ($r = $db->fetch_array($q)) {
    if (preg_match('/^Aux(\d+)$/i', $r['tid'], $m)) {
        $n = (int) $m[1];
        if ($n > $max) $max = $n;
    }
}

echo json_encode(['tid' => 'Aux' . str_pad($max + 1, 3, '0', STR_PAD_LEFT)]);
