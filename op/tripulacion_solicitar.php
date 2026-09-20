<?php
/**
 * Solicitud de creación de Tripulación
 *
 * Formulario público (requiere ficha aprobada) para solicitar la creación de
 * una tripulación nueva. Quien envía el formulario queda automáticamente
 * como Capitán inicial — no hace falta agregarse ni agregar a nadie más acá;
 * el resto de los miembros se suman después desde la página de gestión de la
 * tripulación. La facción de la tripulación es siempre la del personaje que
 * la solicita, no un campo editable. No crea la tripulación directamente:
 * guarda la solicitud en mybb_op_tripulaciones_solicitudes con estado=0
 * (pendiente) para que staff la apruebe/rechace desde la consola de mods
 * (Página 4, todavía sin implementar). Ver docs/200_Design_Tripulacion.md,
 * sección "Página 2".
 *
 * Mientras la solicitud sigue pendiente, el propio creador puede volver acá
 * y editarla: el formulario se precarga con sus datos y el envío hace UPDATE
 * en vez de INSERT. Deja de ser editable en cuanto estado pasa a 1 o 2.
 *
 * Modo revisión de staff: ?tripu_id=X (enlazado desde
 * op/staff/peticiones_admin.php) muestra esa solicitud puntual de solo
 * lectura con botones Aceptar/Rechazar, en vez del formulario de
 * autoservicio. Aceptar crea la fila real en mybb_op_tripulaciones; no crea
 * todavía mybb_op_tripulaciones_miembros porque esa tabla es de la Página 3
 * (gestión de la tripulación), que no está implementada aún — el capitán
 * queda igual registrado vía `lider_fid`.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tripulacion_solicitar.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

$tripu_id = $mybb->get_input('tripu_id', MyBB::INPUT_INT);

if ($tripu_id > 0) {
    // ── Modo revisión de staff ───────────────────────────────────────────

    if (!is_mod($uid) && !is_staff($uid)) {
        $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
        eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
        output_page($page);
        exit;
    }

    if ($mybb->request_method == 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
        $solicitud = $db->fetch_array($db->query("
            SELECT * FROM `mybb_op_tripulaciones_solicitudes` WHERE id='" . $tripu_id . "'
        "));

        $error = '';
        $ok = '';

        if (!$solicitud) {
            $error = 'Esa solicitud no existe.';
        } elseif ((int) $solicitud['estado'] !== 0) {
            $error = 'Esa solicitud ya fue resuelta — no se puede volver a procesar.';
        } elseif ($accion === 'aceptar') {
            $choca = $db->fetch_array($db->query("
                SELECT id FROM `mybb_op_tripulaciones` WHERE nombre='" . $db->escape_string($solicitud['nombre_propuesto']) . "'
            "));
            $ya_en_otra = $db->fetch_array($db->query("
                SELECT id FROM `mybb_op_tripulaciones_miembros` WHERE fid='" . (int) $solicitud['lider_fid'] . "'
            "));
            if ($choca) {
                $error = 'Ya existe una tripulación con ese nombre — no se puede aprobar tal cual.';
            } elseif ($ya_en_otra) {
                $error = 'El personaje que solicitó ya es miembro de otra tripulación — no se puede aprobar tal cual.';
            } else {
                $nueva_tripulacion_id = $db->insert_query('op_tripulaciones', array(
                    'nombre'         => $db->escape_string($solicitud['nombre_propuesto']),
                    'faccion'        => $db->escape_string($solicitud['faccion']),
                    'lider_fid'      => (int) $solicitud['lider_fid'],
                    'link_formacion' => $db->escape_string($solicitud['link']),
                    'ideales'        => $db->escape_string($solicitud['ideales']),
                    'otros'          => $db->escape_string($solicitud['detalles']),
                    'estado'         => 0,
                    'created_at'     => TIME_NOW,
                ));
                // El capitán entra como primer miembro, rango 2. El resto de
                // la tripulación se agrega después desde op/tripulacion.php.
                $db->insert_query('op_tripulaciones_miembros', array(
                    'tripulacion_id' => (int) $nueva_tripulacion_id,
                    'fid'            => (int) $solicitud['lider_fid'],
                    'rango'          => 2,
                    'fecha_ingreso'  => TIME_NOW,
                ));
                $db->update_query('op_tripulaciones_solicitudes', array(
                    'estado'       => 1,
                    'resuelto_at'  => TIME_NOW,
                    'resuelto_por' => $uid,
                ), "id='" . $tripu_id . "'");
                $ok = 'Tripulación creada y solicitud aprobada.';
            }
        } elseif ($accion === 'rechazar') {
            $db->update_query('op_tripulaciones_solicitudes', array(
                'estado'       => 2,
                'resuelto_at'  => TIME_NOW,
                'resuelto_por' => $uid,
            ), "id='" . $tripu_id . "'");
            $ok = 'Solicitud rechazada.';
        } else {
            $error = 'Acción desconocida.';
        }

        my_setcookie('tripu_revisar_msg', base64_encode(json_encode(array(
            'tipo'  => $error !== '' ? 'err' : 'ok',
            'texto' => $error !== '' ? $error : $ok,
        ))), 15, true);
        // Vuelve a la misma solicitud: si se resolvió bien, el GET de abajo
        // ya sabe mostrar "esta solicitud ya fue aprobada/rechazada"; si
        // falló (nombre repetido, ya resuelta por otro mod mientras tanto),
        // el aviso de error explica por qué sigue pendiente.
        header('Location: tripulacion_solicitar.php?tripu_id=' . $tripu_id);
        exit;
    }

    $solicitud = $db->fetch_array($db->query("
        SELECT s.*, f.nombre AS nombre_personaje FROM `mybb_op_tripulaciones_solicitudes` s
        LEFT JOIN `mybb_op_fichas` f ON f.fid = s.uid
        WHERE s.id='" . $tripu_id . "'
    "));

    if (!$solicitud) {
        $mensaje_redireccion = "Esa solicitud no existe.";
        eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
        output_page($page);
        exit;
    }

    $post_key = generate_post_check();

    $r_msg_html = '';
    if (!empty($mybb->cookies['tripu_revisar_msg'])) {
        $msg = json_decode(base64_decode($mybb->cookies['tripu_revisar_msg']), true);
        if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
            $r_msg_html = '<p class="aviso ' . ($msg['tipo'] === 'ok' ? 'ok' : 'err') . '">' . htmlspecialchars($msg['texto']) . '</p>';
        }
        my_unsetcookie('tripu_revisar_msg');
    }

    $bbname_html   = htmlspecialchars($mybb->settings['bbname']);
    $post_key_html = htmlspecialchars($post_key);
    $r_id          = (int) $solicitud['id'];
    $r_estado      = (int) $solicitud['estado'];
    $r_nombre      = htmlspecialchars($solicitud['nombre_propuesto']);
    $r_faccion     = htmlspecialchars($solicitud['faccion']);
    $r_link        = htmlspecialchars($solicitud['link']);
    $r_ideales     = nl2br(htmlspecialchars($solicitud['ideales'] ?? ''));
    $r_detalles    = nl2br(htmlspecialchars($solicitud['detalles'] ?? ''));
    $r_detalles_staff = nl2br(htmlspecialchars($solicitud['detalles_staff'] ?? ''));
    $r_personaje   = htmlspecialchars($solicitud['nombre_personaje'] ?: ('UID ' . (int) $solicitud['uid']));
    $r_uid         = (int) $solicitud['uid'];
    $r_fecha       = date('Y-m-d H:i', (int) $solicitud['created_at']);

    if ($r_estado === 0) {
        $r_acciones_html = '<div class="rev-acciones">'
            . '<form method="post">'
            . '<input type="hidden" name="my_post_key" value="' . $post_key_html . '">'
            . '<input type="hidden" name="accion" value="aceptar">'
            . '<button type="submit" class="btn-op btn-op--exito">Aceptar</button>'
            . '</form>'
            . '<form method="post">'
            . '<input type="hidden" name="my_post_key" value="' . $post_key_html . '">'
            . '<input type="hidden" name="accion" value="rechazar">'
            . '<button type="submit" class="btn-op btn-op--peligro">Rechazar</button>'
            . '</form>'
            . '</div>';
    } else {
        $estado_texto = $r_estado === 1 ? 'aprobada' : 'rechazada';
        $r_acciones_html = '<p class="aviso ' . ($r_estado === 1 ? 'ok' : 'err') . '">Esta solicitud ya fue ' . $estado_texto . '.</p>';
    }

    eval("\$page = \"" . $templates->get("op_tripulacion_revisar") . "\";");
    output_page($page);
    exit;
}

// ── Modo autoservicio: el formulario normal ──────────────────────────────

if (!does_ficha_exist($uid)) {
    $mensaje_redireccion = "Necesitas tener una ficha aprobada para solicitar la creación de una tripulación.";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

define('TRIPU_SOLICITAR_MSG_COOKIE', 'tripu_solicitar_msg');

/**
 * La solicitud pendiente del usuario actual, o null si no tiene ninguna.
 */
function tripu_solicitud_pendiente($uid)
{
    global $db;
    $query = $db->query("
        SELECT * FROM `mybb_op_tripulaciones_solicitudes`
        WHERE uid='" . (int) $uid . "' AND estado=0
        ORDER BY id DESC LIMIT 1
    ");
    return $db->fetch_array($query) ?: null;
}

/**
 * Facción del personaje (ficha aprobada) por uid, o '' si no tiene.
 */
function tripu_faccion_personaje($uid)
{
    global $db;
    $row = $db->fetch_array($db->query("
        SELECT faccion FROM `mybb_op_fichas`
        WHERE fid='" . (int) $uid . "' AND aprobada_por != 'sin_aprobar'
    "));
    return $row ? $row['faccion'] : '';
}

// Foro de formación de tripulaciones. Mismo criterio de "es el foro X o un
// subforo de X" que ya usa op/intercambio.php (parentlist LIKE '10,%'): el
// parentlist de un foro es la cadena de ids ancestro-a-sí-mismo separada por
// comas, así que empieza con "10," para cualquier descendiente de fid=10.
define('TRIPU_FORO_ID', 10);

/**
 * tid extraído del link a un tema, o 0 si el link no trae uno.
 */
function tripu_extraer_tid($url)
{
    $query = parse_url(trim($url), PHP_URL_QUERY);
    if (!$query) {
        return 0;
    }
    parse_str($query, $params);
    return isset($params['tid']) ? (int) $params['tid'] : 0;
}

/**
 * true si el tid existe y su foro es TRIPU_FORO_ID o un subforo de él.
 */
function tripu_link_valido($tid)
{
    global $db;
    if ($tid <= 0) {
        return false;
    }
    $existe = $db->fetch_array($db->query("
        SELECT t.tid FROM `mybb_threads` t
        INNER JOIN `mybb_forums` f ON t.fid = f.fid
        WHERE t.tid='" . $tid . "' AND (f.fid='" . TRIPU_FORO_ID . "' OR f.parentlist LIKE '" . TRIPU_FORO_ID . ",%')
    "));
    return (bool) $existe;
}

// ── POST: crear o actualizar la solicitud pendiente ─────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $solicitud_id_form = $mybb->get_input('solicitud_id', MyBB::INPUT_INT);
    $nombre_propuesto  = trim($mybb->get_input('nombre_propuesto', MyBB::INPUT_STRING));
    $link              = trim($mybb->get_input('link', MyBB::INPUT_STRING));
    $ideales           = trim($mybb->get_input('ideales', MyBB::INPUT_STRING));
    $detalles          = trim($mybb->get_input('detalles', MyBB::INPUT_STRING));
    $detalles_staff    = trim($mybb->get_input('otros', MyBB::INPUT_STRING));

    // La facción nunca se toma del formulario: siempre es la del personaje
    // de quien envía, calculada en el servidor.
    $faccion = tripu_faccion_personaje($uid);

    $error = '';

    // Si el formulario dice que está editando una solicitud puntual, esa
    // solicitud tiene que ser del usuario actual y seguir pendiente — no
    // alcanza con que el POST mande el id correcto.
    $editando = null;
    if ($solicitud_id_form > 0) {
        $editando = $db->fetch_array($db->query("
            SELECT * FROM `mybb_op_tripulaciones_solicitudes`
            WHERE id='" . $solicitud_id_form . "' AND uid='" . $uid . "' AND estado=0
        "));
        if (!$editando) {
            $error = 'Esa solicitud ya no está pendiente o no te pertenece.';
        }
    } else {
        // Si ya tiene una pendiente pero el formulario llegó como "nueva"
        // (por ejemplo, dos pestañas abiertas), se edita esa en vez de crear
        // una segunda — no se permite más de una solicitud pendiente a la vez.
        $editando = tripu_solicitud_pendiente($uid);
    }

    if ($error === '' && $nombre_propuesto === '') {
        $error = 'El nombre de la tripulación es obligatorio.';
    }

    $tid = 0;
    if ($error === '' && $link === '') {
        $error = 'El link del tema es obligatorio.';
    } elseif ($error === '') {
        $tid = tripu_extraer_tid($link);
        if (!tripu_link_valido($tid)) {
            $error = 'El link debe ser el de un tema que exista dentro del foro de formación de tripulaciones o uno de sus subforos.';
        }
    }

    // Nombre único: contra tripulaciones ya creadas y contra otras
    // solicitudes pendientes (sin contar la propia si se está editando).
    if ($error === '') {
        $choca_tripulacion = $db->fetch_array($db->query("
            SELECT id FROM `mybb_op_tripulaciones` WHERE nombre='" . $db->escape_string($nombre_propuesto) . "'
        "));
        $excluir_id = $editando ? (int) $editando['id'] : 0;
        $choca_solicitud = $db->fetch_array($db->query("
            SELECT id FROM `mybb_op_tripulaciones_solicitudes`
            WHERE estado=0 AND id != '{$excluir_id}' AND nombre_propuesto='" . $db->escape_string($nombre_propuesto) . "'
        "));
        if ($choca_tripulacion || $choca_solicitud) {
            $error = 'Ya existe una tripulación o una solicitud pendiente con ese nombre.';
        }
    }

    if ($error === '') {
        $now = TIME_NOW;
        $campos = array(
            'nombre_propuesto'        => $db->escape_string($nombre_propuesto),
            'faccion'                 => $db->escape_string($faccion),
            'link'                    => $db->escape_string($link),
            // Solo el creador, como Capitán inicial. El resto de los
            // miembros se agregan después desde la página de la tripulación.
            'usuarios_iniciales_json' => $db->escape_string(json_encode(array($uid))),
            'lider_fid'               => $uid,
            'ideales'                 => $db->escape_string($ideales),
            'detalles'                => $db->escape_string($detalles),
            'detalles_staff'          => $db->escape_string($detalles_staff),
            'updated_at'              => $now,
        );

        if ($editando) {
            $db->update_query('op_tripulaciones_solicitudes', $campos, "id='" . (int) $editando['id'] . "'");
            $ok = 'Tu solicitud se actualizó correctamente. Sigue pendiente de revisión.';
        } else {
            $campos['uid']        = $uid;
            $campos['estado']     = 0;
            $campos['created_at'] = $now;
            $db->insert_query('op_tripulaciones_solicitudes', $campos);
            $ok = '¡Solicitud enviada! Podrás ver su estado en tus peticiones mientras se revisa.';
        }
    }

    my_setcookie(TRIPU_SOLICITAR_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $ok,
    ))), 15, true);
    header('Location: tripulacion_solicitar.php');
    exit;
}

// ── GET: formulario, precargado si ya hay una solicitud pendiente ─────────

$pendiente = tripu_solicitud_pendiente($uid);
$post_key  = generate_post_check();
$faccion_actual = tripu_faccion_personaje($uid);

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[TRIPU_SOLICITAR_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[TRIPU_SOLICITAR_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(TRIPU_SOLICITAR_MSG_COOKIE);
}

// ── Variables para el template (op_tripulacion_solicitar.html) ─────────────
// Todo lo que sea condicional se arma acá como string ya escapado: el
// template solo hace {$variable}, MyBB no evalúa if/foreach dentro de un
// template.

$bbname_html = htmlspecialchars($mybb->settings['bbname']);
$post_key_html = htmlspecialchars($post_key);
$faccion_actual_html = htmlspecialchars($faccion_actual !== '' ? $faccion_actual : '—');

$msg_html = '';
if ($msg_ok !== '') { $msg_html .= '<p class="aviso ok">' . htmlspecialchars($msg_ok) . '</p>'; }
if ($msg_err !== '') { $msg_html .= '<p class="aviso err">' . htmlspecialchars($msg_err) . '</p>'; }

$pendiente_aviso_html = '';
if ($pendiente && (int) $pendiente['estado'] === 0) {
    $pendiente_aviso_html = '<p class="aviso ok">Ya tienes una solicitud pendiente — este formulario la está editando, no crea una segunda.</p>';
}

$v_solicitud_id  = $pendiente ? (int) $pendiente['id'] : 0;
$v_nombre        = htmlspecialchars($pendiente['nombre_propuesto'] ?? '');
$v_link          = htmlspecialchars($pendiente['link'] ?? '');
$v_ideales       = htmlspecialchars($pendiente['ideales'] ?? '');
$v_detalles      = htmlspecialchars($pendiente['detalles'] ?? '');
$v_otros         = htmlspecialchars($pendiente['detalles_staff'] ?? '');
$boton_texto     = $pendiente ? 'Guardar cambios' : 'Enviar solicitud';

eval("\$page = \"" . $templates->get("op_tripulacion_solicitar") . "\";");
output_page($page);
