<?php
/**
 * One-time migration: add ?v={$GLOBALS['op_img_ver']} to island image URLs in DB templates.
 * Run once from the browser as a staff user. Safe to run multiple times (idempotent).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fix_imgver_templates.php');

require_once "./../../global.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
if (!is_staff($uid)) {
    die("Acceso denegado.");
}

// Each entry: [template_title, old_substring, new_substring]
// Using \$ to get literal $ in the string (prevents PHP from interpolating)
$template_titles = ['forumbit_depth2_forum', 'forumdisplay_threadlist'];

// Debug: mostrar TODOS los registros con ese title (distintos sid) y primeros 800 chars
$debug = [];
foreach ($template_titles as $title) {
    $query = $db->simple_select('templates', 'tid, sid, template', "title = '" . $db->escape_string($title) . "'");
    $rows = [];
    while ($row = $db->fetch_array($query)) { $rows[] = $row; }
    if (!$rows) { $debug[$title] = [['sid' => '—', 'tid' => '—', 'snippet' => 'NO ENCONTRADO EN BD']]; continue; }
    foreach ($rows as $row) {
        preg_match_all('/.{0,40}Isla.{0,80}/u', $row['template'], $m);
        $debug[$title][] = [
            'sid'     => $row['sid'],
            'tid'     => $row['tid'],
            'snippet' => $m[0] ?: ['(sin "Isla")'],
            'head'    => substr($row['template'], 0, 600),
        ];
    }
}

// Buscar títulos que contengan "forumbit" o "forumdisplay" en la BD
$titles_query = $db->simple_select('templates', 'DISTINCT title', "title LIKE 'forumbit%' OR title LIKE 'forumdisplay%'");
$found_titles = [];
while ($r = $db->fetch_array($titles_query)) { $found_titles[] = $r['title']; }

// Parches: buscar solo el nombre de archivo .jpg (sin carácter de cierre)
// Idempotente: si ya contiene "?v=" después del .jpg no se toca
$patches = [
    ['forumbit_depth2_forum',   "Isla{\$forum['fid']}_One_Piece_Gaiden_Foro_Rol.jpg",  "Isla{\$forum['fid']}_One_Piece_Gaiden_Foro_Rol.jpg?v={\$GLOBALS['op_img_ver']}"],
    ['forumdisplay_threadlist', "IslaGrande{\$fid}_One_Piece_Gaiden_Foro_Rol.jpg",      "IslaGrande{\$fid}_One_Piece_Gaiden_Foro_Rol.jpg?v={\$GLOBALS['op_img_ver']}"],
    ['forumdisplay_threadlist', "Isla{\$fid}_One_Piece_Gaiden_Foro_Rol.jpg",             "Isla{\$fid}_One_Piece_Gaiden_Foro_Rol.jpg?v={\$GLOBALS['op_img_ver']}"],
];

$results = [];

foreach ($patches as [$title, $old, $new]) {
    $row = $db->fetch_array($db->simple_select('templates', 'tid, template', "title = '" . $db->escape_string($title) . "'", ['limit' => 1]));
    if (!$row) {
        $results[] = "<b>{$title}</b>: no encontrado en la base de datos.";
        continue;
    }
    if (strpos($row['template'], $old) === false) {
        if (strpos($row['template'], $new) !== false) {
            $results[] = "<b>{$title}</b>: ya aplicado — <code>" . htmlspecialchars($old) . "</code>";
        } else {
            $results[] = "<b style='color:red'>{$title}</b>: fragmento no encontrado — <code>" . htmlspecialchars($old) . "</code>";
        }
        continue;
    }
    $updated = str_replace($old, $new, $row['template']);
    $db->update_query('templates', ['template' => $db->escape_string($updated)], "tid = " . (int)$row['tid']);
    $results[] = "<b style='color:green'>{$title}</b>: actualizado — <code>" . htmlspecialchars($old) . "</code>";
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>fix_imgver_templates</title><style>body{font-family:monospace;padding:20px} pre{background:#f4f4f4;padding:8px;overflow-x:auto}</style></head><body>';
echo '<h2>Resultado</h2><ul>';
foreach ($results as $line) {
    echo '<li>' . $line . '</li>';
}
echo '</ul>';
echo '<h2>Títulos en BD que empiezan por forumbit/forumdisplay</h2><pre>' . htmlspecialchars(implode("\n", $found_titles)) . '</pre>';
echo '<h2>Debug — contenido real en BD</h2>';
foreach ($debug as $title => $rows) {
    echo '<h3>' . htmlspecialchars($title) . '</h3>';
    foreach ($rows as $r) {
        echo '<p><b>tid=' . $r['tid'] . ' sid=' . $r['sid'] . '</b></p>';
        echo '<p>Fragmentos con "Isla":</p>';
        foreach ((array)$r['snippet'] as $f) { echo '<pre>' . htmlspecialchars($f) . '</pre>'; }
        echo '<p>Primeros 600 chars:</p><pre>' . htmlspecialchars($r['head'] ?? '') . '</pre>';
    }
}
echo '<p>Recuerda <strong>limpiar la caché de plantillas de MyBB</strong> en el Admin CP después de ejecutar este script.</p>';
echo '</body></html>';
