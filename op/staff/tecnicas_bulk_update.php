<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_bulk_update.php');
require_once "./../../global.php";
require_once "./../../inc/config.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/lib/tecnicas_cambios.php';
require_once MYBB_ROOT . 'inc/plugins/lib/posesiones_avisos.php';

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
$modificadas  = []; // tecnicas existentes con algun campo realmente cambiado (aviso de la issue #21)

foreach ($payload as $row) {
    if (empty($row['tid'])) continue;

    $tid = trim($row['tid']);
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,99}$/i', $tid)) {
        $errores[] = "TID inválido: " . htmlspecialchars($tid);
        continue;
    }
    $tid_safe = $db->escape_string($tid);
    $anterior = $db->fetch_array($db->query("SELECT * FROM mybb_op_tecnicas WHERE tid = '$tid_safe' LIMIT 1"));

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
        $nuevo = $db->fetch_array($db->query("SELECT * FROM mybb_op_tecnicas WHERE tid = '$tid_safe' LIMIT 1"));
        opg_tecnicas_registrar_cambio($tid, null, $nuevo, $uid, $mybb->user['username'], 'Edición masiva');
        $actualizadas++;
        continue;
    }

    if (empty($sets)) continue;

    $db->write_query("UPDATE mybb_op_tecnicas SET " . implode(', ', $sets) . " WHERE tid = '$tid_safe' LIMIT 1");
    $nuevo = $db->fetch_array($db->query("SELECT * FROM mybb_op_tecnicas WHERE tid = '$tid_safe' LIMIT 1"));
    if (opg_tecnicas_registrar_cambio($tid, $anterior ?: null, $nuevo, $uid, $mybb->user['username'], 'Edición masiva') && $anterior) {
        $modificadas[] = $tid;
    }
    $actualizadas++;
}

// Un aviso por cada usuario que tiene aprendida alguna tecnica modificada, con el resumen de cuantas cambiaron. Las
// tecnicas nuevas no se avisan (nadie las tiene aun). Si falla, no afecta a la edicion (nunca lanza).
opg_pos_avisar_tecnicas_modificadas($modificadas, (int) $uid);

echo json_encode(['ok' => true, 'actualizadas' => $actualizadas, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
