<?php
/**
 * AJAX endpoint para el sistema de mensajes/cartas in-game.
 * Sólo accesible para UIDs autorizados.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ajax_mensajes.php');
require_once "./../global.php";

header('Content-Type: application/json; charset=utf-8');

$UIDS_AUTORIZADOS = [7, 850];

$uid = (int)$mybb->user['uid'];
if (!in_array($uid, $UIDS_AUTORIZADOS)) {
    echo json_encode(['error' => 'Sin acceso']);
    exit;
}

$action = $mybb->get_input('action');

if ($action === 'listar') {
    $mensajes = [];
    $query = $db->query("
        SELECT `id`, `remitente`, `titulo`, `cuerpo`, `leida`, `fecha`, `fecha_juego`
        FROM `mybb_op_mensajes`
        WHERE `uid_dest` = '$uid'
        ORDER BY `leida` ASC, `fecha` DESC
        LIMIT 50
    ");
    while ($row = $db->fetch_array($query)) {
        $mensajes[] = [
            'id'        => (int)$row['id'],
            'remitente' => htmlspecialchars($row['remitente']),
            'titulo'    => htmlspecialchars($row['titulo']),
            'cuerpo'    => $row['cuerpo'],
            'leida'      => (int)$row['leida'],
            'fecha'      => date('d/m/Y', (int)$row['fecha']),
            'fecha_juego'=> htmlspecialchars($row['fecha_juego']),
        ];
    }

    $no_leidas = 0;
    foreach ($mensajes as $m) {
        if ($m['leida'] === 0) $no_leidas++;
    }

    echo json_encode(['mensajes' => $mensajes, 'no_leidas' => $no_leidas]);
    exit;
}

if ($action === 'leer') {
    $id = (int)$mybb->get_input('id');
    $db->query("UPDATE `mybb_op_mensajes` SET `leida`=1 WHERE `id`='$id' AND `uid_dest`='$uid'");
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'enviar') {
    if ($uid !== 850) {
        echo json_encode(['error' => 'Sin permiso para enviar']);
        exit;
    }
    $uid_dest  = (int)$mybb->get_input('uid_dest');
    $titulo      = $db->escape_string($mybb->get_input('titulo'));
    $cuerpo      = $db->escape_string($mybb->get_input('cuerpo'));
    $fecha_juego = $db->escape_string($mybb->get_input('fecha_juego'));

    if (!$uid_dest || $titulo === '' || $cuerpo === '' || $fecha_juego === '') {
        echo json_encode(['error' => 'Faltan campos']);
        exit;
    }

    $db->insert_query('op_mensajes', [
        'uid_dest'   => $uid_dest,
        'remitente'  => '',
        'titulo'     => $titulo,
        'cuerpo'     => $cuerpo,
        'leida'      => 0,
        'fecha'      => TIME_NOW,
        'fecha_juego'=> $fecha_juego,
    ]);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Acción desconocida']);
