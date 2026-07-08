<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco_cofre.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(['error' => 'No autenticado']); exit; }

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(['error' => 'Token CSRF inválido']); exit;
}

function _cofre_puede_acceder($uid, $barco_id, $owner_uid) {
    global $db;
    if ((int)$uid === (int)$owner_uid) return true;
    $q = $db->simple_select('op_barco_tripulacion', 'rango',
        "barco_id='" . $db->escape_string($barco_id) . "' AND owner_uid='" . (int)$owner_uid . "' AND miembro_uid='" . (int)$uid . "'");
    $row = $db->fetch_array($q);
    if ($row) {
        $rangos_ok = array('Capitan', 'Vicecapitan', 'Tesorero');
        foreach (array_map('trim', explode(',', $row['rango'])) as $r) {
            if (in_array($r, $rangos_ok, true)) return true;
        }
    }
    return false;
}

function _cofre_ensure_hist_table() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;
    $db->write_query("CREATE TABLE IF NOT EXISTS `mybb_op_barco_cofre_historial` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `barco_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
      `owner_uid` int(11) NOT NULL DEFAULT '0',
      `uid` int(11) NOT NULL DEFAULT '0',
      `tipo` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
      `clase` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
      `objeto_id` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
      `nombre` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
      `cantidad` int(11) NOT NULL DEFAULT '0',
      `timestamp` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `idx_barco` (`barco_id`(50),`owner_uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
}

function _cofre_log($barco_id_raw, $owner_uid, $uid, $tipo, $clase, $objeto_id_raw, $nombre, $cantidad) {
    global $db;
    _cofre_ensure_hist_table();
    $bid = $db->escape_string((string)$barco_id_raw);
    $oid = ($objeto_id_raw !== null && $objeto_id_raw !== '')
        ? "'" . $db->escape_string((string)$objeto_id_raw) . "'" : 'NULL';
    $nom = $db->escape_string((string)$nombre);
    $tip = $db->escape_string((string)$tipo);
    $cla = $db->escape_string((string)$clase);
    $db->write_query("INSERT INTO mybb_op_barco_cofre_historial
        (barco_id,owner_uid,uid,tipo,clase,objeto_id,nombre,cantidad,timestamp)
        VALUES ('$bid'," . (int)$owner_uid . "," . (int)$uid . ",'$tip','$cla',$oid,'$nom'," . (int)$cantidad . "," . TIME_NOW . ")");
}

$action        = $mybb->get_input('action');
$barco_id_raw  = trim($mybb->get_input('barco_id'));
$owner_uid     = (int)$mybb->get_input('owner_uid');

$barco_id = $db->escape_string($barco_id_raw);
$q_sub = $db->simple_select('op_objetos', 'subcategoria', "objeto_id='$barco_id'");
$row_sub = $db->fetch_array($q_sub);
if (!$row_sub || strtolower($row_sub['subcategoria']) !== 'barcos') {
    echo json_encode(['error' => 'ID de barco inválido']); exit;
}

if (!$owner_uid) { echo json_encode(['error' => 'Parámetros inválidos']); exit; }
if (!_cofre_puede_acceder($uid, $barco_id, $owner_uid)) {
    echo json_encode(['error' => 'Sin acceso']); exit;
}

// ─── agregar ──────────────────────────────────────────────────────────────────
if ($action === 'agregar') {

    $objeto_id_raw = trim($mybb->get_input('objeto_id'));
    $cantidad      = max(1, (int)$mybb->get_input('cantidad'));
    if (!$objeto_id_raw) { echo json_encode(['error' => 'Objeto inválido']); exit; }
    $objeto_id = $db->escape_string($objeto_id_raw);

    // Check comerciable and tier
    $q_obj = $db->simple_select('op_objetos', 'comerciable, tier', "objeto_id='$objeto_id'");
    $obj   = $db->fetch_array($q_obj);
    if (!$obj || (int)$obj['comerciable'] !== 0) {
        echo json_encode(['error' => 'Este ítem no se puede depositar en el cofre']); exit;
    }
    // Check personal inventory
    $q   = $db->simple_select('op_inventario', 'id, cantidad, apodo, imagen',
               "objeto_id='$objeto_id' AND uid='$uid'");
    $inv = $db->fetch_array($q);
    if (!$inv || (int)$inv['cantidad'] < $cantidad) {
        echo json_encode(['error' => 'No tienes suficiente cantidad']); exit;
    }

    // Reduce from personal inventory
    $nueva_cant = (int)$inv['cantidad'] - $cantidad;
    if ($nueva_cant <= 0) {
        $db->delete_query('op_inventario', "objeto_id='$objeto_id' AND uid='$uid'");
    } else {
        $db->update_query('op_inventario', ['cantidad' => $nueva_cant],
            "objeto_id='$objeto_id' AND uid='$uid'");
    }

    // Add to ship chest (upsert)
    $q  = $db->simple_select('op_barco_cofre', 'id, cantidad',
              "barco_id='$barco_id' AND owner_uid='$owner_uid' AND objeto_id='$objeto_id'");
    $ex = $db->fetch_array($q);
    if ($ex) {
        $db->update_query('op_barco_cofre',
            ['cantidad' => (int)$ex['cantidad'] + $cantidad],
            "id='{$ex['id']}'");
    } else {
        $db->insert_query('op_barco_cofre', [
            'barco_id'  => $barco_id,
            'owner_uid' => $owner_uid,
            'objeto_id' => $objeto_id,
            'cantidad'  => $cantidad,
            'added_by'  => $uid,
            'added_at'  => TIME_NOW,
            'apodo'     => $db->escape_string($inv['apodo']),
            'imagen'    => $db->escape_string($inv['imagen']),
        ]);
    }

    // Return updated cofre item
    $q = $db->query("
        SELECT c.id, c.objeto_id, c.cantidad, c.apodo, c.imagen,
               COALESCE(o.nombre, c.objeto_id) AS obj_nombre,
               COALESCE(o.imagen, '')          AS obj_imagen
        FROM mybb_op_barco_cofre c
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = c.objeto_id
        WHERE c.barco_id='$barco_id' AND c.owner_uid='$owner_uid' AND c.objeto_id='$objeto_id'
        LIMIT 1
    ");
    $cofre_item = $db->fetch_array($q);
    _cofre_log($barco_id_raw, $owner_uid, $uid, 'entrada', 'objeto', $objeto_id_raw, $cofre_item['obj_nombre'], $cantidad);
    echo json_encode(['ok' => true, 'cofre_item' => $cofre_item], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── retirar ──────────────────────────────────────────────────────────────────
if ($action === 'retirar') {

    $cofre_id = (int)$mybb->get_input('cofre_id');
    $cantidad = max(1, (int)$mybb->get_input('cantidad'));

    $q          = $db->simple_select('op_barco_cofre', '*',
                      "id='$cofre_id' AND barco_id='$barco_id' AND owner_uid='$owner_uid'");
    $cofre_item = $db->fetch_array($q);
    if (!$cofre_item) { echo json_encode(['error' => 'Ítem no encontrado en el cofre']); exit; }
    if ((int)$cofre_item['cantidad'] < $cantidad) {
        echo json_encode(['error' => 'Cantidad insuficiente en el cofre']); exit;
    }

    $objeto_id = $db->escape_string($cofre_item['objeto_id']);

    // Get object name for log
    $q_nom = $db->simple_select('op_objetos', 'nombre', "objeto_id='$objeto_id'");
    $row_nom = $db->fetch_array($q_nom);
    $obj_nombre_log = ($row_nom && $row_nom['nombre']) ? $row_nom['nombre'] : ($cofre_item['apodo'] ?: $cofre_item['objeto_id']);

    // Reduce from chest
    $nueva_cant = (int)$cofre_item['cantidad'] - $cantidad;
    if ($nueva_cant <= 0) {
        $db->delete_query('op_barco_cofre', "id='$cofre_id'");
    } else {
        $db->update_query('op_barco_cofre', ['cantidad' => $nueva_cant], "id='$cofre_id'");
    }

    // Add to personal inventory (upsert)
    $q  = $db->simple_select('op_inventario', 'id, cantidad',
              "objeto_id='$objeto_id' AND uid='$uid'");
    $ex = $db->fetch_array($q);
    if ($ex) {
        $db->update_query('op_inventario',
            ['cantidad' => (int)$ex['cantidad'] + $cantidad],
            "objeto_id='$objeto_id' AND uid='$uid'");
    } else {
        $db->insert_query('op_inventario', [
            'objeto_id' => $objeto_id,
            'uid'       => $uid,
            'cantidad'  => $cantidad,
            'imagen'    => $db->escape_string($cofre_item['imagen']),
            'apodo'     => $db->escape_string($cofre_item['apodo']),
            'especial'  => 0,
            'editado'   => 0,
        ]);
    }

    _cofre_log($barco_id_raw, $owner_uid, $uid, 'salida', 'objeto', $cofre_item['objeto_id'], $obj_nombre_log, $cantidad);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── berries ──────────────────────────────────────────────────────────────────
if ($action === 'berries') {

    $tipo     = $mybb->get_input('tipo'); // 'depositar' | 'retirar'
    $cantidad = max(1, (int)$mybb->get_input('cantidad'));

    // Current chest berries
    $q      = $db->simple_select('op_barco_estado', 'berries',
                  "barco_id='$barco_id' AND owner_uid='$owner_uid'");
    $estado = $db->fetch_array($q);
    $berries_cofre = $estado ? (int)$estado['berries'] : 0;

    // Current personal berries
    $q     = $db->simple_select('op_fichas', 'berries', "fid='$uid'");
    $ficha = $db->fetch_array($q);
    if (!$ficha) { echo json_encode(['error' => 'Ficha no encontrada']); exit; }
    $mis_berries = (int)$ficha['berries'];

    if ($tipo === 'depositar') {
        if ($mis_berries < $cantidad) {
            echo json_encode(['error' => 'No tienes suficientes berries']); exit;
        }
        $db->write_query("UPDATE mybb_op_fichas SET berries = berries - $cantidad WHERE fid = '$uid'");
        $nuevo_cofre = $berries_cofre + $cantidad;
    } else {
        if ($berries_cofre < $cantidad) {
            echo json_encode(['error' => 'No hay suficientes berries en el cofre']); exit;
        }
        $db->write_query("UPDATE mybb_op_fichas SET berries = berries + $cantidad WHERE fid = '$uid'");
        $nuevo_cofre = $berries_cofre - $cantidad;
    }

    // Upsert estado
    if ($estado) {
        $db->update_query('op_barco_estado', ['berries' => $nuevo_cofre],
            "barco_id='$barco_id' AND owner_uid='$owner_uid'");
    } else {
        $db->insert_query('op_barco_estado', [
            'barco_id'  => $barco_id,
            'owner_uid' => $owner_uid,
            'berries'   => $nuevo_cofre,
        ]);
    }

    _cofre_log($barco_id_raw, $owner_uid, $uid, $tipo === 'depositar' ? 'entrada' : 'salida', 'berries', null, 'Berries', $cantidad);
    echo json_encode(['ok' => true, 'berries_cofre' => $nuevo_cofre]);
    exit;
}

// ─── historial ────────────────────────────────────────────────────────────────
if ($action === 'historial') {
    _cofre_ensure_hist_table();
    $q = $db->query("
        SELECT h.id, h.uid, h.tipo, h.clase, h.objeto_id, h.nombre, h.cantidad, h.timestamp,
               COALESCE(u.username, CONCAT('UID ', h.uid)) AS username
        FROM mybb_op_barco_cofre_historial h
        LEFT JOIN mybb_users u ON u.uid = h.uid
        WHERE h.barco_id = '$barco_id' AND h.owner_uid = '$owner_uid'
        ORDER BY h.id DESC
        LIMIT 20
    ");
    $rows = [];
    while ($r = $db->fetch_array($q)) {
        $rows[] = $r;
    }
    echo json_encode(['ok' => true, 'historial' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_imagen') {
    $imagen = trim($mybb->get_input('imagen'));
    if ($imagen !== '' && !preg_match('/^https?:\/\/.+/i', $imagen)) {
        echo json_encode(['error' => 'URL de imagen inválida']); exit;
    }
    if (mb_strlen($imagen) > 500) {
        echo json_encode(['error' => 'URL demasiado larga']); exit;
    }
    $imagen_safe = $db->escape_string($imagen);
    $db->update_query('op_inventario', ['imagen' => $imagen_safe], "objeto_id='$barco_id' AND uid='$owner_uid'");
    echo json_encode(['ok' => true, 'imagen' => $imagen]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
