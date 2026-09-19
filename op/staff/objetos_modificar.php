<?php
/**
 * Staff - Crear / modificar objetos
 *
 * Catálogo de mybb_op_objetos: ~30 campos repartidos en Identificación,
 * Información básica, comercial, de crafteo y técnica. La cascada de
 * categoría → subcategoría y la validación de esos dos combos siguen
 * viviendo en JS al final del template (staff_objetos_modificar.html), sin
 * tocar — es lógica de datos del juego, no de estilo.
 *
 * Reescrito por 2 problemas reales que tenía:
 * 1. Inyección SQL en casi todos los campos: solo `descripcion` pasaba por
 *    addslashes(); el resto (nombre, categoría, tier, oficio, crafteo_usuarios,
 *    los dos objeto_id...) se armaba directo en el UPDATE/INSERT sin escapar
 *    nada. Ahora todo pasa por $db->escape_string().
 * 2. CSRF: el <form> no llevaba my_post_key y el PHP nunca lo pedía — sobre
 *    una tabla que define atributos/precios de items del juego, eso es
 *    manipulación de economía vía un staff logueado sin que se dé cuenta.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'objetos_modificar.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

define('OBJETOS_MSG_COOKIE', 'objetos_msg');

// ── Typeahead: catálogo con cientos de objetos, así que la búsqueda pega al
// servidor en vez de mandar todo el listado al cliente (datalist/chips no
// escala ahí). Busca por id o nombre, de solo lectura, sin CSRF porque no
// escribe nada — el permiso de arriba ya lo cubre.
if ($mybb->get_input('buscar_objeto', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_objeto', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $resultados = array();
    $query = $db->query("SELECT objeto_id, nombre FROM `mybb_op_objetos` WHERE objeto_id LIKE '%{$like}%' OR nombre LIKE '%{$like}%' ORDER BY nombre LIMIT 20");
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['objeto_id'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Campos de texto/número que se guardan tal cual (todo llega como string desde
// el form; los "numéricos" también, la tabla los castea sola al insertar).
$OBJETOS_CAMPOS = array(
    'categoria', 'subcategoria', 'tier', 'imagen_id', 'imagen_avatar',
    'berries', 'berriesCrafteo', 'cantidadMaxima', 'dano', 'bloqueo', 'efecto',
    'exclusivo', 'invisible', 'espacios', 'crafteo_usuarios', 'desbloquear',
    'oficio', 'tiempo_creacion', 'nivel', 'requisitos', 'escalado', 'editable',
    'comerciable', 'alcance', 'negro', 'negro_berries', 'fusion_tipo',
    'engastes', 'imagen',
);

function objetos_url($id = '')
{
    return 'objetos_modificar.php' . ($id !== '' ? '?objeto_id=' . rawurlencode($id) : '');
}

// ── POST: crear/modificar ────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $objeto_id_old = trim($mybb->get_input('objeto_id_old', MyBB::INPUT_STRING));
    $objeto_id_new = trim($mybb->get_input('objeto_id', MyBB::INPUT_STRING));
    $nombre        = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
    $descripcion   = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));
    $categoria     = trim($mybb->get_input('categoria', MyBB::INPUT_STRING));
    $subcategoria  = trim($mybb->get_input('subcategoria', MyBB::INPUT_STRING));

    $error = '';
    if ($objeto_id_new === '') { $error = 'El objeto ID es obligatorio.'; }
    elseif ($nombre === '') { $error = 'El nombre es obligatorio.'; }
    elseif ($categoria === '') { $error = 'La categoría es obligatoria.'; }
    elseif ($subcategoria === '') { $error = 'La subcategoría es obligatoria.'; }
    elseif ($descripcion === '') { $error = 'La descripción es obligatoria.'; }

    if ($error === '') {
        $campos = array('nombre' => $nombre, 'descripcion' => $descripcion, 'categoria' => $categoria, 'subcategoria' => $subcategoria);
        foreach ($OBJETOS_CAMPOS as $campo) {
            $campos[$campo] = trim($mybb->get_input($campo, MyBB::INPUT_STRING));
        }

        $existe = (bool) $db->fetch_field($db->query("SELECT id FROM `mybb_op_objetos` WHERE objeto_id='" . $db->escape_string($objeto_id_new) . "'"), 'id');
        // Si el ID viejo existe y es distinto del nuevo, es un rename: mismo objeto, ID nuevo.
        $es_rename = $objeto_id_old !== '' && $objeto_id_old !== $objeto_id_new
            && $db->fetch_field($db->query("SELECT id FROM `mybb_op_objetos` WHERE objeto_id='" . $db->escape_string($objeto_id_old) . "'"), 'id');

        if ($es_rename) {
            $db->query("UPDATE `mybb_op_inventario` SET `objeto_id`='" . $db->escape_string($objeto_id_new) . "' WHERE objeto_id='" . $db->escape_string($objeto_id_old) . "'");
        }

        $set = array();
        foreach ($campos as $col => $val) {
            $set[] = "`{$col}`='" . $db->escape_string($val) . "'";
        }
        $set[] = "`objeto_id`='" . $db->escape_string($objeto_id_new) . "'";

        if ($existe || $es_rename) {
            $id_where = $es_rename ? $objeto_id_old : $objeto_id_new;
            $db->query("UPDATE `mybb_op_objetos` SET " . implode(', ', $set) . " WHERE `objeto_id`='" . $db->escape_string($id_where) . "'");
        } else {
            $cols = array('objeto_id');
            $vals = array("'" . $db->escape_string($objeto_id_new) . "'");
            foreach ($campos as $col => $val) {
                $cols[] = $col;
                $vals[] = "'" . $db->escape_string($val) . "'";
            }
            $db->query("INSERT INTO `mybb_op_objetos` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ")");
        }

        $log_texto = "Objeto {$objeto_id_new} (" . ($existe || $es_rename ? 'modificado' : 'creado') . " por {$mybb->user['username']}): "
            . "nombre={$nombre}, categoria={$categoria}, subcategoria={$subcategoria}.";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . (int) $uid . "', '" . $db->escape_string($mybb->user['username']) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(OBJETOS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Objeto guardado.',
    ))), 15, true);
    header('Location: ' . objetos_url($error !== '' ? $objeto_id_old : $objeto_id_new));
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$objeto_id = trim($mybb->get_input('objeto_id', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[OBJETOS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[OBJETOS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(OBJETOS_MSG_COOKIE);
}

$objeto = null;
if ($objeto_id !== '') {
    $objeto = $db->fetch_array($db->query("SELECT * FROM `mybb_op_objetos` WHERE objeto_id='" . $db->escape_string($objeto_id) . "'"));
}

// $objeto_esc: mismos campos, ya escapados con htmlspecialchars para pintar en
// el template (value="{$objeto_esc['x']}"). El original interpolaba {$objeto['x']}
// crudo — un nombre/descripción con comillas rompía el atributo o inyectaba HTML.
$OBJETOS_CAMPOS_FORM = array_merge(array('nombre', 'descripcion', 'categoria', 'subcategoria'), $OBJETOS_CAMPOS);
$objeto_esc = array();
foreach ($OBJETOS_CAMPOS_FORM as $campo) {
    $objeto_esc[$campo] = htmlspecialchars($objeto ? (string) $objeto[$campo] : '', ENT_QUOTES, 'UTF-8');
}
$objeto_id_esc = htmlspecialchars($objeto_id, ENT_QUOTES, 'UTF-8');

eval("\$page = \"".$templates->get("staff_objetos_modificar")."\";");
output_page($page);
