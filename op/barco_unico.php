<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco_unico.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid || (!is_staff($uid) && !is_mod($uid))) {
    echo json_encode(['error' => 'Sin permisos']); exit;
}

$action = trim($mybb->get_input('action'));

// ── Listar (no necesita CSRF) ─────────────────────────────────────────────────
if ($action === 'listar') {
    $q = $db->query("
        SELECT b.barco_id, b.nombre_barco, b.vitalidad, b.espacios, b.velocidad,
               b.tiempo_viaje, b.resistencia, b.espacios_mejora, b.ruputura,
               COALESCE(o.imagen, '')            AS imagen,
               COALESCE(o.descripcion, '')       AS descripcion,
               COALESCE(o.oficio, '')            AS oficio,
               COALESCE(o.nivel, 0)              AS nivel,
               COALESCE(o.berriesCrafteo, 0)     AS berriesCrafteo,
               COALESCE(o.crafteo_usuarios, '')  AS crafteo_usuarios
        FROM mybb_op_barcos b
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = b.barco_id COLLATE utf8_unicode_ci
        WHERE b.barco_id NOT REGEXP '-[0-9]+-[0-9]+$'
        ORDER BY b.barco_id ASC
    ");
    $lista = [];
    while ($r = $db->fetch_array($q)) { $lista[] = $r; }
    echo json_encode(['ok' => true, 'barcos' => $lista], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CSRF para acciones de escritura ───────────────────────────────────────────
if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(['error' => 'Token CSRF inválido']); exit;
}

function _bu_int($key) { return max(0, (int)($_POST[$key] ?? 0)); }

// ── Crear ─────────────────────────────────────────────────────────────────────
if ($action === 'crear') {
    $barco_id         = trim($_POST['barco_id']         ?? '');
    $nombre           = trim($_POST['nombre']           ?? '');
    $imagen           = trim($_POST['imagen']           ?? '');
    $descripcion      = trim($_POST['descripcion']      ?? '');
    $oficio           = trim($_POST['oficio']           ?? '');
    $crafteo_usuarios = trim($_POST['crafteo_usuarios'] ?? '');

    if (!$barco_id || !$nombre) {
        echo json_encode(['error' => 'ID y nombre son obligatorios']); exit;
    }
    if (!preg_match('/^[A-Z0-9_\-]{1,60}$/i', $barco_id)) {
        echo json_encode(['error' => 'ID inválido (letras, números, guiones; máx 60 chars)']); exit;
    }
    if (mb_strlen($nombre) > 80) {
        echo json_encode(['error' => 'Nombre demasiado largo (máx 80 chars)']); exit;
    }

    // Barco único sin uid asignada: bloquear crafteo dejando oficio vacío
    if ($crafteo_usuarios === '' && preg_match('/^\d/', $barco_id)) {
        $oficio = '';
    }

    $id_safe  = $db->escape_string($barco_id);
    $nom_safe = $db->escape_string($nombre);
    $img_safe = $db->escape_string($imagen);
    $des_safe = $db->escape_string($descripcion);
    $ofi_safe = $db->escape_string($oficio);
    $cu_safe  = $db->escape_string($crafteo_usuarios);

    $q = $db->simple_select('op_barcos', 'barco_id', "barco_id='$id_safe'");
    if ($db->fetch_array($q)) {
        echo json_encode(['error' => 'Ya existe un barco con ese ID']); exit;
    }

    $db->insert_query('op_barcos', [
        'barco_id'        => $id_safe,
        'nombre_barco'    => $nom_safe,
        'vitalidad'       => _bu_int('vitalidad'),
        'espacios'        => _bu_int('espacios'),
        'velocidad'       => _bu_int('velocidad'),
        'tiempo_viaje'    => _bu_int('tiempo_viaje'),
        'resistencia'     => _bu_int('resistencia'),
        'espacios_mejora' => _bu_int('espacios_mejora'),
        'ruputura'        => _bu_int('ruputura'),
    ]);

    $q = $db->simple_select('op_objetos', 'objeto_id', "objeto_id='$id_safe'");
    if ($db->fetch_array($q)) {
        $db->update_query('op_objetos', [
            'nombre'           => $nom_safe,
            'imagen'           => $img_safe,
            'descripcion'      => $des_safe,
            'oficio'           => $ofi_safe,
            'nivel'            => _bu_int('nivel'),
            'berriesCrafteo'   => _bu_int('berriesCrafteo'),
            'crafteo_usuarios' => $cu_safe,
        ], "objeto_id='$id_safe'");
    } else {
        $db->insert_query('op_objetos', [
            'objeto_id'        => $id_safe,
            'categoria'        => 'Transportes',
            'subcategoria'     => 'barcos',
            'nombre'           => $nom_safe,
            'imagen'           => $img_safe,
            'descripcion'      => $des_safe,
            'oficio'           => $ofi_safe,
            'nivel'            => _bu_int('nivel'),
            'berriesCrafteo'   => _bu_int('berriesCrafteo'),
            'crafteo_usuarios' => $cu_safe,
            'cantidadMaxima'   => 1,
            'custom'           => 0,
            'tier'             => 0,
            'imagen_id'        => 0,
            'imagen_avatar'    => '',
            'berries'          => 0,
            'dano'             => '',
            'bloqueo'          => '',
            'efecto'           => '',
            'alcance'          => '',
            'requisitos'       => '',
            'escalado'         => '',
        ]);
    }

    echo json_encode(['ok' => true, 'barco_id' => $barco_id], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Editar ────────────────────────────────────────────────────────────────────
if ($action === 'editar') {
    $barco_id         = trim($_POST['barco_id']         ?? '');
    $nombre           = trim($_POST['nombre']           ?? '');
    $imagen           = trim($_POST['imagen']           ?? '');
    $descripcion      = trim($_POST['descripcion']      ?? '');
    $oficio           = trim($_POST['oficio']           ?? '');
    $crafteo_usuarios = trim($_POST['crafteo_usuarios'] ?? '');

    // Barco único sin uid asignada: bloquear crafteo dejando oficio vacío
    if ($crafteo_usuarios === '' && preg_match('/^\d/', $barco_id)) {
        $oficio = '';
    }

    if (!$barco_id || !$nombre) {
        echo json_encode(['error' => 'Faltan parámetros']); exit;
    }

    $id_safe  = $db->escape_string($barco_id);
    $nom_safe = $db->escape_string($nombre);
    $img_safe = $db->escape_string($imagen);
    $des_safe = $db->escape_string($descripcion);
    $ofi_safe = $db->escape_string($oficio);
    $cu_safe  = $db->escape_string($crafteo_usuarios);

    $q = $db->simple_select('op_barcos', 'barco_id', "barco_id='$id_safe'");
    if (!$db->fetch_array($q)) {
        echo json_encode(['error' => 'Barco no encontrado']); exit;
    }

    $db->update_query('op_barcos', [
        'nombre_barco'    => $nom_safe,
        'vitalidad'       => _bu_int('vitalidad'),
        'espacios'        => _bu_int('espacios'),
        'velocidad'       => _bu_int('velocidad'),
        'tiempo_viaje'    => _bu_int('tiempo_viaje'),
        'resistencia'     => _bu_int('resistencia'),
        'espacios_mejora' => _bu_int('espacios_mejora'),
        'ruputura'        => _bu_int('ruputura'),
    ], "barco_id='$id_safe'");

    $q = $db->simple_select('op_objetos', 'objeto_id', "objeto_id='$id_safe'");
    if ($db->fetch_array($q)) {
        $db->update_query('op_objetos', [
            'nombre'           => $nom_safe,
            'imagen'           => $img_safe,
            'descripcion'      => $des_safe,
            'oficio'           => $ofi_safe,
            'nivel'            => _bu_int('nivel'),
            'berriesCrafteo'   => _bu_int('berriesCrafteo'),
            'crafteo_usuarios' => $cu_safe,
        ], "objeto_id='$id_safe'");
    } else {
        $db->insert_query('op_objetos', [
            'objeto_id'        => $id_safe,
            'categoria'        => 'Transportes',
            'subcategoria'     => 'barcos',
            'nombre'           => $nom_safe,
            'imagen'           => $img_safe,
            'descripcion'      => $des_safe,
            'oficio'           => $ofi_safe,
            'nivel'            => _bu_int('nivel'),
            'berriesCrafteo'   => _bu_int('berriesCrafteo'),
            'crafteo_usuarios' => $cu_safe,
            'cantidadMaxima'   => 1,
            'custom'           => 0,
            'tier'             => 0,
            'imagen_id'        => 0,
            'imagen_avatar'    => '',
            'berries'          => 0,
            'dano'             => '',
            'bloqueo'          => '',
            'efecto'           => '',
            'alcance'          => '',
            'requisitos'       => '',
            'escalado'         => '',
        ]);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']); exit;
