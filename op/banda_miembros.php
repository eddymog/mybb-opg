<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'banda_miembros.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(['error' => 'No autenticado']); exit; }

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(['error' => 'Token CSRF inválido']); exit;
}

$action = $mybb->get_input('action');

// ─── crear ────────────────────────────────────────────────────────────────────
if ($action === 'crear') {

    $nombre_raw = trim($mybb->get_input('nombre'));
    if (!$nombre_raw) { echo json_encode(['error' => 'El nombre de la banda es obligatorio']); exit; }
    if (mb_strlen($nombre_raw) > 100) { echo json_encode(['error' => 'Nombre demasiado largo (máx. 100 caracteres)']); exit; }

    $descripcion_raw = trim($mybb->get_input('descripcion'));
    $bandera_raw     = trim($mybb->get_input('bandera'));
    $nombre      = $db->escape_string($nombre_raw);
    $descripcion = $db->escape_string($descripcion_raw);
    $bandera     = $db->escape_string($bandera_raw);

    // Check name not taken
    $q_exists = $db->simple_select('op_bandas', 'id', "nombre='$nombre'");
    if ($db->fetch_array($q_exists)) { echo json_encode(['error' => 'Ya existe una banda con ese nombre']); exit; }

    // Check user doesn't already lead a band
    $q_own = $db->simple_select('op_bandas', 'id', "owner_uid='$uid'");
    if ($db->fetch_array($q_own)) { echo json_encode(['error' => 'Ya lideras una banda']); exit; }

    $banda_id = $db->insert_query('op_bandas', [
        'nombre'      => $nombre,
        'owner_uid'   => $uid,
        'descripcion' => $descripcion,
        'bandera'     => $bandera,
        'fecha'       => time(),
    ]);

    echo json_encode(['ok' => true, 'banda_id' => (int)$banda_id, 'nombre' => $nombre_raw], JSON_UNESCAPED_UNICODE);
    exit;
}

// All other actions require a valid banda_id
$banda_id = (int)$mybb->get_input('banda_id');
if (!$banda_id) { echo json_encode(['error' => 'ID de banda inválido']); exit; }

$q_banda = $db->simple_select('op_bandas', '*', "id='$banda_id'");
$banda   = $db->fetch_array($q_banda);
if (!$banda) { echo json_encode(['error' => 'Banda no encontrada']); exit; }

$owner_uid = (int)$banda['owner_uid'];
$es_lider  = ($uid === $owner_uid || $uid === 850);

// ─── invitar ──────────────────────────────────────────────────────────────────
if ($action === 'invitar') {

    if (!$es_lider) { echo json_encode(['error' => 'Solo el líder puede invitar miembros']); exit; }

    $miembro_uid = (int)$mybb->get_input('miembro_uid');
    if (!$miembro_uid) { echo json_encode(['error' => 'UID de miembro inválida']); exit; }

    if ($miembro_uid === $owner_uid) { echo json_encode(['error' => 'El líder ya pertenece a su propia banda']); exit; }

    $q_user = $db->simple_select('users', 'uid, username', "uid='$miembro_uid'");
    $target = $db->fetch_array($q_user);
    if (!$target) { echo json_encode(['error' => 'Usuario no encontrado']); exit; }

    $q_member = $db->simple_select('op_banda_miembros', 'id', "banda_id='$banda_id' AND miembro_uid='$miembro_uid'");
    if ($db->fetch_array($q_member)) { echo json_encode(['error' => 'El usuario ya es miembro de esta banda']); exit; }

    $q_inv = $db->simple_select('op_banda_invitaciones', 'id', "banda_id='$banda_id' AND miembro_uid='$miembro_uid'");
    if ($db->fetch_array($q_inv)) { echo json_encode(['error' => 'El usuario ya tiene una invitación pendiente']); exit; }

    $db->insert_query('op_banda_invitaciones', [
        'banda_id'    => $banda_id,
        'owner_uid'   => $owner_uid,
        'miembro_uid' => $miembro_uid,
        'fecha'       => time(),
    ]);

    echo json_encode(['ok' => true, 'username' => $target['username']], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── expulsar ─────────────────────────────────────────────────────────────────
if ($action === 'expulsar') {

    if (!$es_lider) { echo json_encode(['error' => 'Solo el líder puede expulsar miembros']); exit; }

    $miembro_id = (int)$mybb->get_input('miembro_id');
    if (!$miembro_id) { echo json_encode(['error' => 'Parámetros inválidos']); exit; }

    $q_m = $db->simple_select('op_banda_miembros', 'id', "id='$miembro_id' AND banda_id='$banda_id'");
    if (!$db->fetch_array($q_m)) { echo json_encode(['error' => 'Miembro no encontrado en esta banda']); exit; }

    $db->delete_query('op_banda_miembros', "id='$miembro_id'");

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── aceptar ──────────────────────────────────────────────────────────────────
if ($action === 'aceptar') {

    $inv_id = (int)$mybb->get_input('inv_id');
    if (!$inv_id) { echo json_encode(['error' => 'Parámetros inválidos']); exit; }

    $q_inv = $db->simple_select('op_banda_invitaciones', '*', "id='$inv_id' AND miembro_uid='$uid' AND banda_id='$banda_id'");
    $inv   = $db->fetch_array($q_inv);
    if (!$inv) { echo json_encode(['error' => 'Invitación no encontrada']); exit; }

    $db->insert_query('op_banda_miembros', [
        'banda_id'    => $banda_id,
        'owner_uid'   => $owner_uid,
        'miembro_uid' => $uid,
        'fecha'       => time(),
    ]);

    $db->delete_query('op_banda_invitaciones', "id='$inv_id'");

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── rechazar ─────────────────────────────────────────────────────────────────
if ($action === 'rechazar') {

    $inv_id = (int)$mybb->get_input('inv_id');
    if (!$inv_id) { echo json_encode(['error' => 'Parámetros inválidos']); exit; }

    $q_inv = $db->simple_select('op_banda_invitaciones', 'id', "id='$inv_id' AND miembro_uid='$uid' AND banda_id='$banda_id'");
    if (!$db->fetch_array($q_inv)) { echo json_encode(['error' => 'Invitación no encontrada']); exit; }

    $db->delete_query('op_banda_invitaciones', "id='$inv_id'");

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── abandonar ────────────────────────────────────────────────────────────────
if ($action === 'abandonar') {

    if ($uid === $owner_uid) { echo json_encode(['error' => 'El líder no puede abandonar su propia banda']); exit; }

    $q_m = $db->simple_select('op_banda_miembros', 'id', "banda_id='$banda_id' AND miembro_uid='$uid'");
    if (!$db->fetch_array($q_m)) { echo json_encode(['error' => 'No eres miembro de esta banda']); exit; }

    $db->delete_query('op_banda_miembros', "banda_id='$banda_id' AND miembro_uid='$uid'");

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── disolver ─────────────────────────────────────────────────────────────────
if ($action === 'disolver') {

    if (!$es_lider) { echo json_encode(['error' => 'Solo el líder puede disolver la banda']); exit; }

    $db->delete_query('op_banda_miembros',      "banda_id='$banda_id'");
    $db->delete_query('op_banda_invitaciones',  "banda_id='$banda_id'");
    $db->delete_query('op_bandas',              "id='$banda_id'");

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
