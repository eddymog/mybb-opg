<?php
/**
 * TEMPORAL — solo lectura, para extraer el texto completo de unos temas sin
 * el recorte de phpMyAdmin. Súbelo, ábrelo una vez logueado como staff,
 * copia el texto y BÓRRALO del servidor (y no lo subas a git).
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'export_temp.php');
require_once "./../global.php";

if (!is_staff((int) $mybb->user['uid'])) {
    die('No autorizado.');
}

// Los TID de los temas a exportar completos
$tids = [3729, 3733, 3736];

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
