<?php
/**
 * TEMPORAL — solo lectura. Lista los subforos de fid=34 y todos sus temas,
 * para identificar qué guías faltan por exportar. Bórralo del servidor
 * cuando termines de usarlo (y no lo subas a git).
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'listar_guias_temp.php');
require_once "./../global.php";

if (!is_staff((int) $mybb->user['uid'])) {
    die('No autorizado.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Subforos dentro de fid=34 ===\n\n";

$query = $db->query("
    SELECT fid, name, parentlist
    FROM mybb_forums
    WHERE fid = 34 OR FIND_IN_SET(34, parentlist)
    ORDER BY fid
");
while ($fila = $db->fetch_array($query)) {
    echo "fid {$fila['fid']} — {$fila['name']}  (parentlist: {$fila['parentlist']})\n";
}

echo "\n=== Temas dentro de esos subforos ===\n\n";

$query = $db->query("
    SELECT
        f.fid AS foro_id,
        f.name AS foro_nombre,
        t.tid,
        t.subject AS titulo_tema
    FROM mybb_threads t
    JOIN mybb_forums f ON f.fid = t.fid
    WHERE (f.fid = 34 OR FIND_IN_SET(34, f.parentlist))
      AND t.visible = 1
    ORDER BY f.name, t.subject
");

$foro_actual = null;
while ($fila = $db->fetch_array($query)) {
    if ($fila['foro_nombre'] !== $foro_actual) {
        $foro_actual = $fila['foro_nombre'];
        echo "\n--- {$foro_actual} (fid {$fila['foro_id']}) ---\n";
    }
    echo "  tid {$fila['tid']}: {$fila['titulo_tema']}\n";
}

echo "\n=== Fin ===\n";
exit;
