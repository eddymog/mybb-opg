<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco_bautizar.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

header('Content-Type: application/json; charset=utf-8');

$uid = (int)$mybb->user['uid'];
if (!$uid) { echo json_encode(array('error' => 'No autenticado')); exit; }

if (!verify_post_check($mybb->get_input('post_code'), true)) {
    echo json_encode(array('error' => 'Token CSRF invalido')); exit;
}

$q     = $db->simple_select('op_fichas', 'oficio1, oficio2', "fid='$uid'");
$ficha = $db->fetch_array($q);
if (!$ficha) { echo json_encode(array('error' => 'Ficha no encontrada')); exit; }

$oficios_ok = array('Carpintero', 'Astillero', 'Constructor');
$puede_bautizar = $uid === 850
    || in_array($ficha['oficio1'], $oficios_ok)
    || in_array($ficha['oficio2'], $oficios_ok);
if (!$puede_bautizar) {
    $q_npc_b = $db->query("
        SELECT 1 FROM mybb_op_npcs
        WHERE npc_id LIKE '" . (int)$uid . "-%' AND oficios LIKE '%Carpintero%'
        LIMIT 1
    ");
    if ($db->fetch_array($q_npc_b)) { $puede_bautizar = true; }
}
if (!$puede_bautizar) {
    echo json_encode(array('error' => 'Solo un carpintero puede bautizar un barco')); exit;
}

$barco_base_raw = trim($mybb->get_input('barco_base_id'));
$nombre         = trim($mybb->get_input('nombre'));

if (!$barco_base_raw || !$nombre) {
    echo json_encode(array('error' => 'Faltan parametros')); exit;
}
if (mb_strlen($nombre) > 100) {
    echo json_encode(array('error' => 'Nombre demasiado largo (max 100 caracteres)')); exit;
}

$base_safe   = $db->escape_string($barco_base_raw);
$nombre_safe = $db->escape_string($nombre);

// Verificar inventario: debe pertenecer al usuario y no estar bautizado
$q   = $db->simple_select('op_inventario', 'id, cantidad', "objeto_id='$base_safe' AND uid='$uid' AND bautizado=0");
$inv = $db->fetch_array($q);
if (!$inv || (int)$inv['cantidad'] < 1) {
    echo json_encode(array('error' => 'No tienes este barco en el inventario')); exit;
}

// Verificar que el objeto existe y es un barco
$q        = $db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id='$base_safe' LIMIT 1");
$orig_obj = $db->fetch_array($q);
if (!$orig_obj) { echo json_encode(array('error' => 'Objeto no encontrado')); exit; }
if (strtolower($orig_obj['subcategoria']) !== 'barcos') { echo json_encode(array('error' => 'El objeto no es un barco')); exit; }

// Buscar stats del barco: primero por el ID completo, luego por el tipo canónico
// (los barcos crafteados únicos tienen el tipo base en mybb_op_barcos sin prefijo uid ni sufijos)
$orig_barco = null;
$q_barco = $db->query("SELECT * FROM mybb_op_barcos WHERE barco_id='$base_safe' LIMIT 1");
$orig_barco = $db->fetch_array($q_barco);
$barco_base_tipo = $barco_base_raw;
if (!$orig_barco) {
    // Extraer tipo canónico: quitar los dos últimos segmentos -uid-N
    if (preg_match('/^(.+)-\d+-\d+$/', $barco_base_raw, $m_tipo)) {
        $barco_base_tipo = $m_tipo[1];
        $tipo_safe = $db->escape_string($barco_base_tipo);
        $q_barco = $db->query("SELECT * FROM mybb_op_barcos WHERE barco_id='$tipo_safe' LIMIT 1");
        $orig_barco = $db->fetch_array($q_barco);
    }
}

// Calcular siguiente Y: buscamos {uid}{tipo}-N (usando el tipo base)
$prefix      = $uid . $barco_base_tipo;
$prefix_safe = $db->escape_string($prefix);
$q           = $db->query("SELECT objeto_id FROM mybb_op_objetos WHERE objeto_id LIKE '$prefix_safe-%' ORDER BY id DESC LIMIT 200");
$max_y = 0;
while ($r = $db->fetch_array($q)) {
    if (preg_match('/-(\d+)$/', $r['objeto_id'], $m)) {
        $y = (int)$m[1];
        if ($y > $max_y) { $max_y = $y; }
    }
}
$nuevo_id      = $prefix . '-' . ($max_y + 1);
$nuevo_id_safe = $db->escape_string($nuevo_id);

// Crear objeto en mybb_op_objetos (copia del original con nuevo ID y nombre)
$db->write_query("
    INSERT INTO mybb_op_objetos
        (objeto_id, categoria, subcategoria, nombre, tier, imagen_id, imagen_avatar, berries,
         cantidadMaxima, dano, efecto, exclusivo, espacios, imagen,
         desbloquear, oficio, nivel, requisitos, escalado, editable, custom, descripcion)
    SELECT '$nuevo_id_safe', categoria, subcategoria, '$nombre_safe', tier, imagen_id, imagen_avatar, berries,
           cantidadMaxima, dano, efecto, exclusivo, espacios, imagen,
           desbloquear, oficio, nivel, requisitos, escalado, editable, 1, descripcion
    FROM mybb_op_objetos
    WHERE objeto_id = '$base_safe'
    LIMIT 1
");

// Crear entrada en mybb_op_barcos (copiar stats o inicializar a 0)
$db->insert_query('op_barcos', array(
    'barco_id'        => $nuevo_id_safe,
    'nombre_barco'    => $nombre_safe,
    'vitalidad'       => $orig_barco ? (int)$orig_barco['vitalidad'] : 0,
    'espacios'        => $orig_barco ? (int)$orig_barco['espacios'] : 0,
    'velocidad'       => $orig_barco ? (int)$orig_barco['velocidad'] : 0,
    'tiempo_viaje'    => $orig_barco ? (int)$orig_barco['tiempo_viaje'] : 0,
    'resistencia'     => $orig_barco ? (int)$orig_barco['resistencia'] + ($barco_base_tipo !== $barco_base_raw ? 50 : 0) : 0,
    'espacios_mejora' => $orig_barco ? (int)$orig_barco['espacios_mejora'] : 0,
    'ruputura'        => $orig_barco ? (int)$orig_barco['ruputura'] : 0,
));

// Agregar barco bautizado al inventario
$db->insert_query('op_inventario', array(
    'objeto_id' => $nuevo_id_safe,
    'uid'       => $uid,
    'cantidad'  => 1,
    'imagen'    => $db->escape_string($orig_obj['imagen']),
    'apodo'     => $nombre_safe,
    'especial'  => 0,
    'editado'   => 0,
    'bautizado' => 1,
));

// Consumir el barco generico del inventario
$nueva_cant = (int)$inv['cantidad'] - 1;
if ($nueva_cant <= 0) {
    $db->delete_query('op_inventario', "objeto_id='$base_safe' AND uid='$uid'");
} else {
    $db->update_query('op_inventario', array('cantidad' => $nueva_cant), "objeto_id='$base_safe' AND uid='$uid'");
}

echo json_encode(array(
    'ok'       => true,
    'nuevo_id' => $nuevo_id,
    'nombre'   => $nombre,
), JSON_UNESCAPED_UNICODE);
