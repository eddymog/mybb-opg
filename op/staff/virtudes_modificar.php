<?php
/**
 * Staff - Crear / modificar / eliminar virtudes y defectos
 *
 * Fusiona lo que antes eran dos páginas separadas (virtudes_modificar.php y
 * virtudes_crear.php): buscar un virtud_id existente para editarlo o
 * eliminarlo, o escribir uno nuevo para crearlo, todo desde acá.
 * virtudes_crear.php queda sin usar — el propio usuario la va a borrar.
 *
 * Reescrito por 3 problemas reales que tenía cada página original:
 * 1. Inyección SQL: ningún campo pasaba por addslashes() salvo descripcion,
 *    y la búsqueda por ?virtud_id= corría antes del chequeo de permisos —
 *    cualquiera, sin sesión, podía inyectar SQL con solo visitar la URL.
 * 2. CSRF: ningún <form> llevaba my_post_key.
 * 3. El INSERT a mybb_op_audit_consola_mod estaba comentado en las dos
 *    páginas (o, en modificar.php, escribía $username también en la columna
 *    `staff`, en vez del uid) — no quedaba un registro consistente de quién
 *    creaba/modificaba/borraba una virtud o defecto.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'virtudes_modificar.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('VIRTUDES_MSG_COOKIE', 'virtudes_msg');

// ── Typeahead: busca por virtud_id o nombre, de solo lectura ─────────────────
if ($mybb->get_input('buscar_virtud', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_virtud', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $resultados = array();
    $query = $db->query("SELECT virtud_id, nombre FROM `mybb_op_virtudes` WHERE virtud_id LIKE '%{$like}%' OR nombre LIKE '%{$like}%' ORDER BY nombre LIMIT 20");
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['virtud_id'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

function virtudes_url($id = '')
{
    return 'virtudes_modificar.php' . ($id !== '' ? '?virtud_id=' . rawurlencode($id) : '');
}

// ── POST: guardar / eliminar ─────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

    if ($accion === 'eliminar') {
        $virtud_id = trim($mybb->get_input('virtud_id', MyBB::INPUT_STRING));
        $error = '';
        if ($virtud_id === '') {
            $error = 'Virtud/Defecto ID inválido.';
        } else {
            $previa = $db->fetch_array($db->query("SELECT nombre FROM `mybb_op_virtudes` WHERE virtud_id='" . $db->escape_string($virtud_id) . "'"));
            if (!$previa) {
                $error = 'Esa virtud/defecto no existe.';
            } else {
                $db->query("DELETE FROM `mybb_op_virtudes` WHERE `virtud_id`='" . $db->escape_string($virtud_id) . "'");
                $log_texto = "Virtud/defecto {$virtud_id} ({$previa['nombre']}) eliminada por {$username} ({$uid}).";
                $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                    . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
            }
        }

        my_setcookie(VIRTUDES_MSG_COOKIE, base64_encode(json_encode(array(
            'tipo'  => $error !== '' ? 'err' : 'ok',
            'texto' => $error !== '' ? $error : "Virtud/defecto {$virtud_id} eliminado.",
        ))), 15, true);
        header('Location: ' . virtudes_url($error !== '' ? $virtud_id : ''));
        exit;
    }

    // accion === 'guardar' (crear o modificar)
    $virtud_id_old = trim($mybb->get_input('virtud_id_old', MyBB::INPUT_STRING));
    $virtud_id_new = trim($mybb->get_input('virtud_id', MyBB::INPUT_STRING));
    $nombre        = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
    $puntos        = trim($mybb->get_input('puntos', MyBB::INPUT_STRING));
    $descripcion   = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));

    $error = '';
    if ($virtud_id_new === '') { $error = 'El Virtud/Defecto ID es obligatorio.'; }
    elseif ($nombre === '') { $error = 'El nombre es obligatorio.'; }
    elseif ($puntos === '' || !preg_match('/^-?\d+$/', $puntos)) { $error = 'Los puntos tienen que ser un número entero.'; }
    elseif ($descripcion === '') { $error = 'La descripción es obligatoria.'; }

    if ($error === '') {
        $existe = (bool) $db->fetch_field($db->query("SELECT virtud_id FROM `mybb_op_virtudes` WHERE virtud_id='" . $db->escape_string($virtud_id_new) . "'"), 'virtud_id');
        $es_rename = $virtud_id_old !== '' && $virtud_id_old !== $virtud_id_new
            && $db->fetch_field($db->query("SELECT virtud_id FROM `mybb_op_virtudes` WHERE virtud_id='" . $db->escape_string($virtud_id_old) . "'"), 'virtud_id');

        $nombre_esc = $db->escape_string($nombre);
        $puntos_esc = $db->escape_string($puntos);
        $desc_esc   = $db->escape_string($descripcion);
        $id_esc     = $db->escape_string($virtud_id_new);

        if ($existe || $es_rename) {
            $id_where = $es_rename ? $virtud_id_old : $virtud_id_new;
            $db->query("UPDATE `mybb_op_virtudes` SET `virtud_id`='{$id_esc}', `nombre`='{$nombre_esc}', `puntos`='{$puntos_esc}', `descripcion`='{$desc_esc}' "
                . "WHERE `virtud_id`='" . $db->escape_string($id_where) . "'");
        } else {
            $db->query("INSERT INTO `mybb_op_virtudes` (`virtud_id`, `nombre`, `puntos`, `descripcion`) VALUES ('{$id_esc}', '{$nombre_esc}', '{$puntos_esc}', '{$desc_esc}')");
        }

        $log_texto = "Virtud/defecto {$virtud_id_new} (" . ($existe || $es_rename ? 'modificada' : 'creada') . " por {$username}): nombre={$nombre}, puntos={$puntos}.";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(VIRTUDES_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Virtud/defecto guardado.',
    ))), 15, true);
    header('Location: ' . virtudes_url($error !== '' ? $virtud_id_old : $virtud_id_new));
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$virtud_id = trim($mybb->get_input('virtud_id', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[VIRTUDES_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[VIRTUDES_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(VIRTUDES_MSG_COOKIE);
}

$virtud = null;
if ($virtud_id !== '') {
    $virtud = $db->fetch_array($db->query("SELECT * FROM `mybb_op_virtudes` WHERE virtud_id='" . $db->escape_string($virtud_id) . "'"));
}

$virtud_esc = array(
    'nombre'      => htmlspecialchars($virtud ? $virtud['nombre'] : '', ENT_QUOTES, 'UTF-8'),
    'puntos'      => htmlspecialchars($virtud ? (string) $virtud['puntos'] : '', ENT_QUOTES, 'UTF-8'),
    'descripcion' => htmlspecialchars($virtud ? $virtud['descripcion'] : '', ENT_QUOTES, 'UTF-8'),
);
$virtud_id_esc = htmlspecialchars($virtud_id, ENT_QUOTES, 'UTF-8');
$virtud_existe = $virtud ? 1 : 0;

eval("\$page = \"".$templates->get("staff_virtudes_modificar")."\";");
output_page($page);
