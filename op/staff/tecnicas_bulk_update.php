<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_bulk_update.php');
require_once "./../../global.php";
require_once "./../../inc/config.php";
require_once "./../functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = $mybb->user['uid'];

if (!is_staff($uid)) {
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || empty($payload)) {
    echo json_encode(['error' => 'Payload vacío o inválido']);
    exit;
}

$campos_permitidos = ['nombre', 'estilo', 'rama', 'clase', 'tipo', 'tier',
                      'energia', 'energia_turno', 'haki', 'haki_turno',
                      'enfriamiento', 'descripcion', 'efectos', 'requisitos'];
$clases_validas    = ['', 'Activa', 'Pasiva', 'Mantenida', 'Conjunta'];
$tipos_validos     = ['', 'Ofensiva', 'Defensiva', 'Ambiental', 'Elusiva', 'Utilidad'];

$actualizadas = 0;
$errores      = [];

foreach ($payload as $row) {
    if (empty($row['tid'])) continue;

    $tid = trim($row['tid']);
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,99}$/i', $tid)) {
        $errores[] = "TID inválido: " . htmlspecialchars($tid);
        continue;
    }
    $tid_safe = $db->escape_string($tid);

    $sets = [];
    foreach ($campos_permitidos as $campo) {
        if (!array_key_exists($campo, $row)) continue;
        $val = trim($row[$campo]);
        if ($campo === 'clase' && !in_array($val, $clases_validas, true)) continue;
        if ($campo === 'tipo'  && !in_array($val, $tipos_validos,  true)) continue;
        $sets[] = "`$campo` = '" . $db->escape_string($val) . "'";
    }

    if (!empty($row['_new'])) {
        $q = $db->query("SELECT 1 FROM mybb_op_tecnicas WHERE tid = '$tid_safe' LIMIT 1");
        if ($db->fetch_array($q)) {
            $errores[] = "TID ya existe: " . htmlspecialchars($tid);
            continue;
        }
        if (empty($sets)) continue;
        $cols = ['`tid`'];
        $vals = ["'$tid_safe'"];
        foreach ($sets as $set) {
            preg_match('/^(`\w+`) = (.+)$/', $set, $pm);
            if ($pm) { $cols[] = $pm[1]; $vals[] = $pm[2]; }
        }
        $db->write_query("INSERT INTO mybb_op_tecnicas (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")");
        $actualizadas++;
        continue;
    }

    if (empty($sets)) continue;

    $db->write_query("UPDATE mybb_op_tecnicas SET " . implode(', ', $sets) . " WHERE tid = '$tid_safe' LIMIT 1");
    $actualizadas++;
}

echo json_encode(['ok' => true, 'actualizadas' => $actualizadas, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
