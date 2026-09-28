<?php
/**
 * TEMPORAL — solo lectura, para extraer el texto completo de las guías de
 * "Guías Bélicas" (fid 37) y "Otras Guías" (fid 38). Súbelo, ábrelo una vez
 * logueado como staff, copia el texto y BÓRRALO del servidor (y no lo subas
 * a git).
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'export_temp2.php');
require_once "./../../global.php";

if (!is_staff((int) $mybb->user['uid'])) {
    die('No autorizado.');
}

// Guías Bélicas (fid 37) + Otras Guías (fid 38)
$tids = [
    3731, 3730, 3732, 3735, 3740, 3746, 3747, 3748, 3752, // Guías Bélicas
    3737, 3738, 3739, 6127, 3744, 3750, 3751, 3753,       // Otras Guías
];

$ids = implode(',', array_map('intval', $tids));
$query = $db->query("
    SELECT t.tid, t.subject, p.message
    FROM mybb_threads t
    JOIN mybb_posts p ON p.tid = t.tid
    WHERE t.tid IN ($ids)
      AND t.visible = 1
      AND p.visible = 1
    ORDER BY t.tid, p.dateline, p.pid
");

header('Content-Type: text/plain; charset=utf-8');

$actual = null;
while ($fila = $db->fetch_array($query)) {
    if ($fila['tid'] != $actual) {
        $actual = $fila['tid'];
        echo "\n\n========== TID {$fila['tid']} — {$fila['subject']} ==========\n\n";
    } else {
        echo "\n\n----- (siguiente post del mismo tema) -----\n\n";
    }
    echo $fila['message'];
}
exit;
