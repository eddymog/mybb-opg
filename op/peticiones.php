<?php
/**
 * Peticiones de moderación
 *
 * Formulario general para pedirle algo a staff (ajustes de ficha, narración,
 * moderación de combate, técnicas/akumas/estilos, errores de programación,
 * otros) — inserta en mybb_op_peticiones, que revisa staff desde
 * op/staff/peticiones_admin.php. También es el punto de entrada hacia los
 * tipos de petición que ya tienen su propia página dedicada (Tripulación,
 * Afiliados), y muestra las peticiones pendientes del usuario actual,
 * incluidas las solicitudes de creación de Tripulación (tabla propia,
 * mybb_op_tripulaciones_solicitudes — ver docs/200_Design_Tripulacion.md).
 *
 * Reescrito por 3 problemas reales que tenía el original:
 * 1. Sin CSRF: cualquier POST a esta URL creaba una petición en nombre del
 *    usuario logueado en ese momento, sin que lo haya pedido.
 * 2. Inyección SQL: `addslashes()` en vez de `$db->escape_string()`.
 * 3. XSS: `$resumen`/`$descripcion` se imprimían crudos en el listado de
 *    pendientes — un resumen con `<script>` se ejecutaba en el navegador de
 *    quien mirara sus propias peticiones.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticiones.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if ($uid === 0) {
    $mensaje_redireccion = "Tienes que estar logueado para enviar peticiones administrativas.";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

define('PETICIONES_MSG_COOKIE', 'peticiones_msg');

// Categorías reales: las mismas que ya filtra op/staff/peticiones_admin.php
// (ficha, tema, combate, tecnica, otros) más "programacion", que se puede
// enviar pero esa consola no la muestra todavía (línea comentada ahí) — no
// se toca ese archivo acá, pero vale saberlo antes de asumir que llega a
// algún lado. "reset" no es una categoría real: el formulario nunca la
// ofreció como opción, era una etiqueta muerta en el código viejo.
$PETICIONES_CATEGORIAS = array(
    'tecnica'     => 'Técnicas, Akumas y Estilos',
    'ficha'       => 'Ajustes de Ficha y Recursos',
    'combate'     => 'Moderación de Combate',
    'tema'        => 'Petición de Narración',
    'programacion' => 'Errores de Programación',
    'otros'       => 'Otras Moderaciones',
);

// ── POST: enviar petición ────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $categoria   = trim($mybb->get_input('categoria', MyBB::INPUT_STRING));
    $resumen     = trim($mybb->get_input('resumen', MyBB::INPUT_STRING));
    $descripcion = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));
    $url         = trim($mybb->get_input('url', MyBB::INPUT_STRING));

    $error = '';
    if (!isset($PETICIONES_CATEGORIAS[$categoria])) {
        $error = 'Elige un tipo de petición válido.';
    } elseif ($resumen === '') {
        $error = 'El resumen es obligatorio.';
    } elseif ($descripcion === '') {
        $error = 'La descripción es obligatoria.';
    }

    if ($error === '') {
        $db->insert_query('op_peticiones', array(
            'uid'         => $uid,
            'nombre'      => $db->escape_string($username),
            'categoria'   => $db->escape_string($categoria),
            'resumen'     => $db->escape_string($resumen),
            'descripcion' => $db->escape_string($descripcion),
            'url'         => $db->escape_string($url),
        ));
        $ok = '¡Petición enviada! La vas a ver en la lista de pendientes mientras se revisa.';
    }

    my_setcookie(PETICIONES_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $ok,
    ))), 15, true);
    header('Location: peticiones.php');
    exit;
}

// ── GET: formulario + listado de pendientes ─────────────────────────────

$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[PETICIONES_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[PETICIONES_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(PETICIONES_MSG_COOKIE);
}

/**
 * "Hace N días", igual que antes, a partir de un timestamp unix.
 */
function peticiones_hace_dias($timestamp)
{
    $dias = (int) floor((TIME_NOW - $timestamp) / 86400);
    return $dias <= 0 ? 'Hoy' : "Hace {$dias} día" . ($dias === 1 ? '' : 's') . '.';
}

$pendientes_html = '';

$query_peticion = $db->query("
    SELECT * FROM `mybb_op_peticiones`
    WHERE uid='" . $uid . "' AND resuelto=0
    ORDER BY enviado ASC
");
while ($q = $db->fetch_array($query_peticion)) {
    $categoria_label = $PETICIONES_CATEGORIAS[$q['categoria']] ?? htmlspecialchars($q['categoria']);
    $fecha = peticiones_hace_dias(strtotime($q['enviado']));
    $pendientes_html .= '<li class="pet-item">'
        . '<span class="pet-categoria">' . htmlspecialchars($categoria_label) . '</span>'
        . '<span class="pet-fecha">' . htmlspecialchars($fecha) . '</span>'
        . '<strong class="pet-resumen">' . htmlspecialchars($q['resumen']) . '</strong>'
        . '<p class="pet-descripcion">' . nl2br(htmlspecialchars($q['descripcion'])) . '</p>'
        . '</li>';
}

// Solicitudes de creación de Tripulación: tabla propia
// (mybb_op_tripulaciones_solicitudes, ver docs/200_Design_Tripulacion.md),
// pero se muestran acá también para que el usuario vea el estado de todas
// sus peticiones pendientes en un solo lugar.
$query_tripulacion = $db->query("
    SELECT * FROM `mybb_op_tripulaciones_solicitudes`
    WHERE uid='" . $uid . "' AND estado=0
    ORDER BY created_at ASC
");
while ($t = $db->fetch_array($query_tripulacion)) {
    $fecha = peticiones_hace_dias((int) $t['created_at']);
    $pendientes_html .= '<li class="pet-item">'
        . '<span class="pet-categoria">Solicitud de Tripulación</span>'
        . '<span class="pet-fecha">' . htmlspecialchars($fecha) . '</span>'
        . '<strong class="pet-resumen">' . htmlspecialchars($t['nombre_propuesto']) . '</strong>'
        . '<p class="pet-descripcion">Pendiente de revisión por staff. <a href="tripulacion_solicitar.php">Editar solicitud &rarr;</a></p>'
        . '</li>';
}

if ($pendientes_html === '') {
    $pendientes_html = '<p class="opg-vacio">No tienes peticiones pendientes.</p>';
} else {
    $pendientes_html = '<ul class="pet-lista">' . $pendientes_html . '</ul>';
}

// ── Variables para el template ────────────────────────────────────────────

$bbname_html = htmlspecialchars($mybb->settings['bbname']);
$post_key_html = htmlspecialchars($post_key);

$msg_html = '';
if ($msg_ok !== '') { $msg_html .= '<p class="aviso ok">' . htmlspecialchars($msg_ok) . '</p>'; }
if ($msg_err !== '') { $msg_html .= '<p class="aviso err">' . htmlspecialchars($msg_err) . '</p>'; }

$categoria_options_html = '';
foreach ($PETICIONES_CATEGORIAS as $valor => $etiqueta) {
    $categoria_options_html .= '<option value="' . htmlspecialchars($valor) . '">' . htmlspecialchars($etiqueta) . '</option>';
}

eval("\$page = \"" . $templates->get("op_peticiones") . "\";");
output_page($page);
