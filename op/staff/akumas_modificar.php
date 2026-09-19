<?php
/**
 * Staff - Crear / modificar / eliminar akumas
 *
 * Fusiona lo que antes eran dos páginas separadas (akumas_modificar.php y
 * akumas_crear.php): buscar un akuma_id existente para editarlo o eliminarlo,
 * o escribir uno nuevo para crearlo, todo desde acá. akumas_crear.php queda
 * sin usar — el propio usuario la va a borrar.
 *
 * `detalles` es HTML/BBCode de verdad: inc/plugins/BBCustom_akuma.php la pasa
 * por $parser->parse_message() con allow_html=1 para armar la tarjeta del
 * BBCode [akuma=ID] — a propósito no se sanitiza como el resto de los campos.
 * `descripcion` en cambio es texto plano (se usa en listados/registro).
 *
 * Reescrito por 3 problemas reales que tenía cada página original:
 * 1. Inyección SQL: casi ningún campo pasaba por addslashes() siquiera
 *    (nombre, categoria, tier, uid...), y la búsqueda por ?akuma_id= corría
 *    antes del chequeo de permisos — cualquiera, sin sesión, podía inyectar
 *    SQL con solo visitar la URL.
 * 2. CSRF: ningún <form> llevaba my_post_key.
 * 3. El INSERT a mybb_op_audit_consola_mod estaba comentado en las dos
 *    páginas (o roto por variables sin definir) — no quedaba ningún registro
 *    de quién creaba/modificaba/borraba una akuma.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'akumas_modificar.php');
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

define('AKUMAS_MSG_COOKIE', 'akumas_msg');
// Valores reales confirmados en op/akumas.php / op/registro_akumas.php
// (ocupada=0 es lo que esas páginas buscan como "libre para reclamar") y en
// op/staff/akumas_inactivas.php (es_npc=1 excluye la akuma del reporte de
// inactividad de jugadores).
$AKUMAS_OCUPADA = array('0' => 'Libre', '1' => 'Ocupada', '2' => 'Reservada');
$AKUMAS_SINO = array('0' => 'No', '1' => 'Sí');
$AKUMAS_CAMPOS = array(
    'subnombre', 'categoria', 'tier', 'uid', 'es_npc', 'es_oculta', 'ocupada',
    'imagen', 'detalles', 'dominio1', 'dominio2', 'dominio3',
    'pasiva1', 'pasiva2', 'pasiva3', 'reservas', 'reservasFecha',
);

// ── Typeahead: busca por akuma_id o nombre, de solo lectura ──────────────────
if ($mybb->get_input('buscar_akuma', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_akuma', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $resultados = array();
    $query = $db->query("SELECT akuma_id, nombre FROM `mybb_op_akumas` WHERE akuma_id LIKE '%{$like}%' OR nombre LIKE '%{$like}%' ORDER BY nombre LIMIT 20");
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['akuma_id'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

function akumas_url($id = '')
{
    return 'akumas_modificar.php' . ($id !== '' ? '?akuma_id=' . rawurlencode($id) : '');
}

// ── POST: guardar / eliminar ─────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

    if ($accion === 'eliminar') {
        $akuma_id = trim($mybb->get_input('akuma_id', MyBB::INPUT_STRING));
        $error = '';
        if ($akuma_id === '') {
            $error = 'Akuma ID inválido.';
        } else {
            $akuma_previa = $db->fetch_array($db->query("SELECT nombre FROM `mybb_op_akumas` WHERE akuma_id='" . $db->escape_string($akuma_id) . "'"));
            if (!$akuma_previa) {
                $error = 'Esa akuma no existe.';
            } else {
                $db->query("DELETE FROM `mybb_op_akumas` WHERE `akuma_id`='" . $db->escape_string($akuma_id) . "'");
                $log_texto = "Akuma {$akuma_id} ({$akuma_previa['nombre']}) eliminada por {$username} ({$uid}).";
                $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                    . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
            }
        }

        my_setcookie(AKUMAS_MSG_COOKIE, base64_encode(json_encode(array(
            'tipo'  => $error !== '' ? 'err' : 'ok',
            'texto' => $error !== '' ? $error : "Akuma {$akuma_id} eliminada.",
        ))), 15, true);
        header('Location: ' . akumas_url($error !== '' ? $akuma_id : ''));
        exit;
    }

    // accion === 'guardar' (crear o modificar)
    $akuma_id_old = trim($mybb->get_input('akuma_id_old', MyBB::INPUT_STRING));
    $akuma_id_new = trim($mybb->get_input('akuma_id', MyBB::INPUT_STRING));
    $nombre       = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
    $categoria    = trim($mybb->get_input('categoria', MyBB::INPUT_STRING));
    $tier         = trim($mybb->get_input('tier', MyBB::INPUT_STRING));
    $descripcion  = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));

    $error = '';
    if ($akuma_id_new === '') { $error = 'El akuma ID es obligatorio.'; }
    elseif ($nombre === '') { $error = 'El nombre es obligatorio.'; }
    elseif ($categoria === '') { $error = 'La categoría es obligatoria.'; }
    elseif ($tier === '' || !ctype_digit($tier)) { $error = 'El tier tiene que ser un número.'; }
    elseif ($descripcion === '') { $error = 'La descripción es obligatoria.'; }

    if ($error === '') {
        $campos = array('nombre' => $nombre, 'categoria' => $categoria, 'tier' => $tier, 'descripcion' => $descripcion);
        foreach ($AKUMAS_CAMPOS as $campo) {
            $campos[$campo] = trim($mybb->get_input($campo, MyBB::INPUT_STRING));
        }

        $existe = (bool) $db->fetch_field($db->query("SELECT akuma_id FROM `mybb_op_akumas` WHERE akuma_id='" . $db->escape_string($akuma_id_new) . "'"), 'akuma_id');
        $es_rename = $akuma_id_old !== '' && $akuma_id_old !== $akuma_id_new
            && $db->fetch_field($db->query("SELECT akuma_id FROM `mybb_op_akumas` WHERE akuma_id='" . $db->escape_string($akuma_id_old) . "'"), 'akuma_id');

        $set = array();
        foreach ($campos as $col => $val) {
            $set[] = "`{$col}`='" . $db->escape_string($val) . "'";
        }
        $set[] = "`akuma_id`='" . $db->escape_string($akuma_id_new) . "'";

        if ($existe || $es_rename) {
            $id_where = $es_rename ? $akuma_id_old : $akuma_id_new;
            $db->query("UPDATE `mybb_op_akumas` SET " . implode(', ', $set) . " WHERE `akuma_id`='" . $db->escape_string($id_where) . "'");
        } else {
            $cols = array('akuma_id');
            $vals = array("'" . $db->escape_string($akuma_id_new) . "'");
            foreach ($campos as $col => $val) {
                $cols[] = $col;
                $vals[] = "'" . $db->escape_string($val) . "'";
            }
            $db->query("INSERT INTO `mybb_op_akumas` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ")");
        }

        $log_texto = "Akuma {$akuma_id_new} (" . ($existe || $es_rename ? 'modificada' : 'creada') . " por {$username}): nombre={$nombre}, categoria={$categoria}, tier={$tier}.";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(AKUMAS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Akuma guardada.',
    ))), 15, true);
    header('Location: ' . akumas_url($error !== '' ? $akuma_id_old : $akuma_id_new));
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$akuma_id = trim($mybb->get_input('akuma_id', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[AKUMAS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[AKUMAS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(AKUMAS_MSG_COOKIE);
}

$akuma = null;
if ($akuma_id !== '') {
    $akuma = $db->fetch_array($db->query("SELECT * FROM `mybb_op_akumas` WHERE akuma_id='" . $db->escape_string($akuma_id) . "'"));
}

$AKUMAS_CAMPOS_FORM = array_merge(array('nombre', 'categoria', 'tier', 'descripcion'), $AKUMAS_CAMPOS);
$akuma_esc = array();
foreach ($AKUMAS_CAMPOS_FORM as $campo) {
    $akuma_esc[$campo] = htmlspecialchars($akuma ? (string) $akuma[$campo] : '', ENT_QUOTES, 'UTF-8');
}
$akuma_id_esc = htmlspecialchars($akuma_id, ENT_QUOTES, 'UTF-8');

function akumas_opciones($valores, $actual)
{
    $html = '';
    foreach ($valores as $valor => $etiqueta) {
        $sel = $actual !== null && (string) $actual === (string) $valor ? ' selected' : '';
        $html .= '<option value="' . $valor . '"' . $sel . '>' . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

$akuma_opciones_ocupada = akumas_opciones($AKUMAS_OCUPADA, $akuma ? $akuma['ocupada'] : '0');
$akuma_opciones_npc     = akumas_opciones($AKUMAS_SINO, $akuma ? $akuma['es_npc'] : '0');
$akuma_opciones_oculta  = akumas_opciones($AKUMAS_SINO, $akuma ? $akuma['es_oculta'] : '0');
$akuma_existe = $akuma ? 1 : 0;

eval("\$page = \"".$templates->get("staff_akumas_modificar")."\";");
output_page($page);
