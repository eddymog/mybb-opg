<?php
/**
 * Página de la Tripulación
 *
 * Gestión interna de una tripulación puntual (mybb_op_tripulaciones): ver su
 * identidad y miembros, y si sos Capitán/Vicecapitán, editar nombre/logo y
 * administrar el roster (agregar, quitar, cambiar rango). Ver
 * docs/200_Design_Tripulacion.md, sección "Página 3".
 *
 * Alcance de esta primera versión: identidad + miembros + reputación total.
 * El baúl, los barcos y el historial de auditoría del diseño original
 * todavía no están implementados.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tripulacion.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if ($uid === 0) {
    $mensaje_redireccion = "Tienes que estar logueado para ver esta página.";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

define('TRIPULACION_MSG_COOKIE', 'tripulacion_msg');
define('TRIPULACION_LOGO_DIR', 'images/op/uploads/tripulaciones/');
define('TRIPULACION_LOGO_MAX_MB', 3);

$tripulacion_id = $mybb->get_input('id', MyBB::INPUT_INT);

function tripu_get($id)
{
    global $db;
    return $db->fetch_array($db->query("
        SELECT * FROM `mybb_op_tripulaciones` WHERE id='" . (int) $id . "'
    "));
}

/**
 * Miembros de una tripulación, con nombre y reputación de su ficha. Orden:
 * rango descendente (Capitán primero), después nombre.
 */
function tripu_miembros($tripulacion_id)
{
    global $db;
    $miembros = array();
    $query = $db->query("
        SELECT m.*, f.nombre AS nombre_personaje, f.reputacion
        FROM `mybb_op_tripulaciones_miembros` m
        INNER JOIN `mybb_op_fichas` f ON f.fid = m.fid
        WHERE m.tripulacion_id='" . (int) $tripulacion_id . "'
        ORDER BY m.rango DESC, f.nombre ASC
    ");
    while ($m = $db->fetch_array($query)) {
        $miembros[] = $m;
    }
    return $miembros;
}

function tripu_mi_rango($tripulacion_id, $uid)
{
    global $db;
    $row = $db->fetch_array($db->query("
        SELECT rango FROM `mybb_op_tripulaciones_miembros`
        WHERE tripulacion_id='" . (int) $tripulacion_id . "' AND fid='" . (int) $uid . "'
    "));
    return $row ? (int) $row['rango'] : null;
}

/**
 * Cuántos Capitanes/Vicecapitanes (rango 1 o 2) tiene la tripulación —
 * para no dejarla sin ninguno al quitar o degradar a alguien.
 */
function tripu_contar_lideres($tripulacion_id)
{
    global $db;
    return (int) $db->fetch_field($db->query("
        SELECT COUNT(*) AS c FROM `mybb_op_tripulaciones_miembros`
        WHERE tripulacion_id='" . (int) $tripulacion_id . "' AND rango IN (1, 2)
    "), 'c');
}

function tripu_nombre_personaje($fid)
{
    global $db;
    $row = $db->fetch_array($db->query("
        SELECT nombre FROM `mybb_op_fichas`
        WHERE fid='" . (int) $fid . "' AND aprobada_por != 'sin_aprobar'
    "));
    return $row ? $row['nombre'] : null;
}

$tripulacion = tripu_get($tripulacion_id);
if (!$tripulacion) {
    $mensaje_redireccion = "Esa tripulación no existe.";
    eval("\$page = \"" . $templates->get("op_redireccion") . "\";");
    output_page($page);
    exit;
}

// ── POST: acciones ───────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
    $mi_rango = tripu_mi_rango($tripulacion_id, $uid);
    $es_capitan_o_vice = $mi_rango !== null && $mi_rango >= 1;
    $error = '';
    $ok = '';

    switch ($accion) {
        case 'cambiar_identidad':
            if (!$es_capitan_o_vice) { $error = 'Solo el Capitán o Vicecapitán pueden editar la tripulación.'; break; }

            $nombre_nuevo = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
            if ($nombre_nuevo === '') { $error = 'El nombre no puede quedar vacío.'; break; }

            $choca = $db->fetch_array($db->query("
                SELECT id FROM `mybb_op_tripulaciones`
                WHERE nombre='" . $db->escape_string($nombre_nuevo) . "' AND id != '" . $tripulacion_id . "'
            "));
            if ($choca) { $error = 'Ya existe otra tripulación con ese nombre.'; break; }

            $mensaje_publico = trim($mybb->get_input('mensaje_publico', MyBB::INPUT_STRING));
            $buscando_miembros = $mybb->get_input('buscando_miembros', MyBB::INPUT_INT) ? 1 : 0;

            $campos = array(
                'nombre'            => $db->escape_string($nombre_nuevo),
                'mensaje_publico'   => $db->escape_string($mensaje_publico),
                'buscando_miembros' => $buscando_miembros,
            );

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['logo']['size'] > TRIPULACION_LOGO_MAX_MB * 1024 * 1024) {
                    $error = 'El logo supera los ' . TRIPULACION_LOGO_MAX_MB . ' MB.';
                    break;
                }
                $info = @getimagesize($_FILES['logo']['tmp_name']);
                $ext_por_tipo = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif');
                if (!$info || !isset($ext_por_tipo[$info[2]])) {
                    $error = 'El logo debe ser una imagen JPG, PNG, WEBP o GIF válida.';
                    break;
                }
                $dir = MYBB_ROOT . TRIPULACION_LOGO_DIR;
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $nombre_archivo = 'logo_' . $tripulacion_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext_por_tipo[$info[2]];
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $nombre_archivo)) {
                    $error = 'No se pudo guardar el logo.';
                    break;
                }
                $campos['logo'] = $db->escape_string(TRIPULACION_LOGO_DIR . $nombre_archivo);
            }

            $db->update_query('op_tripulaciones', $campos, "id='" . $tripulacion_id . "'");
            $ok = 'Tripulación actualizada.';
            break;

        case 'agregar_miembro':
            if (!$es_capitan_o_vice) { $error = 'Solo el Capitán o Vicecapitán pueden agregar miembros.'; break; }

            $fid_nuevo = $mybb->get_input('fid', MyBB::INPUT_INT);
            if ($fid_nuevo <= 0 || tripu_nombre_personaje($fid_nuevo) === null) {
                $error = 'Ese personaje no existe o no tiene ficha aprobada.';
                break;
            }
            $ya_en_crew = $db->fetch_array($db->query("
                SELECT id FROM `mybb_op_tripulaciones_miembros` WHERE fid='" . $fid_nuevo . "'
            "));
            if ($ya_en_crew) { $error = 'Ese personaje ya pertenece a una tripulación.'; break; }

            $db->insert_query('op_tripulaciones_miembros', array(
                'tripulacion_id' => $tripulacion_id,
                'fid'            => $fid_nuevo,
                'rango'          => 0,
                'fecha_ingreso'  => TIME_NOW,
            ));
            $ok = 'Miembro agregado.';
            break;

        case 'quitar_miembro':
            $fid_quitar = $mybb->get_input('fid', MyBB::INPUT_INT);
            $es_uno_mismo = $fid_quitar === $uid;
            if (!$es_capitan_o_vice && !$es_uno_mismo) { $error = 'No tienes permiso para quitar a ese miembro.'; break; }

            $rango_quitar = tripu_mi_rango($tripulacion_id, $fid_quitar);
            if ($rango_quitar === null) { $error = 'Ese personaje no es miembro de esta tripulación.'; break; }
            if ($rango_quitar >= 1 && tripu_contar_lideres($tripulacion_id) <= 1) {
                $error = 'No se puede quitar al único Capitán/Vicecapitán — asigna el rango a otro miembro primero.';
                break;
            }

            $db->delete_query('op_tripulaciones_miembros', "tripulacion_id='" . $tripulacion_id . "' AND fid='" . $fid_quitar . "'");
            $ok = $es_uno_mismo ? 'Dejaste la tripulación.' : 'Miembro quitado.';
            break;

        case 'cambiar_rango':
            if (!$es_capitan_o_vice) { $error = 'Solo el Capitán o Vicecapitán pueden cambiar rangos.'; break; }

            $fid_rango = $mybb->get_input('fid', MyBB::INPUT_INT);
            $rango_nuevo = $mybb->get_input('rango', MyBB::INPUT_INT);
            if (!in_array($rango_nuevo, array(0, 1, 2), true)) { $error = 'Rango inválido.'; break; }

            $rango_actual = tripu_mi_rango($tripulacion_id, $fid_rango);
            if ($rango_actual === null) { $error = 'Ese personaje no es miembro de esta tripulación.'; break; }
            if ($rango_actual >= 1 && $rango_nuevo === 0 && tripu_contar_lideres($tripulacion_id) <= 1) {
                $error = 'No se puede degradar al único Capitán/Vicecapitán — asigna el rango a otro miembro primero.';
                break;
            }

            $db->update_query('op_tripulaciones_miembros', array('rango' => $rango_nuevo), "tripulacion_id='" . $tripulacion_id . "' AND fid='" . $fid_rango . "'");
            $ok = 'Rango actualizado.';
            break;

        default:
            $error = 'Acción desconocida.';
    }

    my_setcookie(TRIPULACION_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $ok,
    ))), 15, true);
    header('Location: tripulacion.php?id=' . $tripulacion_id);
    exit;
}

// ── GET: mostrar la página ──────────────────────────────────────────────

$post_key = generate_post_check();
$mi_rango = tripu_mi_rango($tripulacion_id, $uid);
$es_capitan_o_vice = $mi_rango !== null && $mi_rango >= 1;

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[TRIPULACION_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[TRIPULACION_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(TRIPULACION_MSG_COOKIE);
}

$miembros = tripu_miembros($tripulacion_id);
$reputacion_total = 0;
foreach ($miembros as $m) {
    $reputacion_total += (int) $m['reputacion'];
}

$ESTADOS = array('Activa', 'Inactiva', 'Disuelta');
$RANGOS = array('Miembro', 'Vicecapitán', 'Capitán');

// ── Variables para el template ────────────────────────────────────────────

$bbname_html = htmlspecialchars($mybb->settings['bbname']);
$post_key_html = htmlspecialchars($post_key);
// Cada form se postea a la misma URL sin action= explícito, pero el id va
// igual como hidden explícito (no depender de que el query string sobreviva
// el submit) — mismo criterio que ya usan los forms de objetos_modificar.php.
$id_hidden = '<input type="hidden" name="id" value="' . $tripulacion_id . '">';

$msg_html = '';
if ($msg_ok !== '') { $msg_html .= '<p class="aviso ok">' . htmlspecialchars($msg_ok) . '</p>'; }
if ($msg_err !== '') { $msg_html .= '<p class="aviso err">' . htmlspecialchars($msg_err) . '</p>'; }

$t_nombre = htmlspecialchars($tripulacion['nombre']);
$t_faccion = htmlspecialchars($tripulacion['faccion']);
$t_estado = htmlspecialchars($ESTADOS[(int) $tripulacion['estado']] ?? '');
$t_reputacion = (int) $reputacion_total;
$t_logo_html = !empty($tripulacion['logo'])
    ? '<img src="/' . htmlspecialchars($tripulacion['logo']) . '" alt="" class="tr-logo">'
    : '<div class="tr-logo tr-logo-vacio"><i class="fa-solid fa-flag"></i></div>';

// Identidad: editable solo por capitán/vicecapitán. Si no tenés permiso, se
// muestra el nombre como texto fijo en vez del formulario.
if ($es_capitan_o_vice) {
    $mensaje_publico_val = htmlspecialchars($tripulacion['mensaje_publico'] ?? '');
    $buscando_checked = !empty($tripulacion['buscando_miembros']) ? ' checked' : '';
    $t_identidad_html = '<form method="post" enctype="multipart/form-data" class="tr-identidad-form">'
        . $id_hidden
        . '<input type="hidden" name="my_post_key" value="' . $post_key_html . '">'
        . '<input type="hidden" name="accion" value="cambiar_identidad">'
        . '<div class="af-field"><label for="tr-nombre">Nombre</label><input type="text" id="tr-nombre" name="nombre" maxlength="80" required value="' . $t_nombre . '"></div>'
        . '<div class="af-field"><label for="tr-logo">Logo</label><input type="file" id="tr-logo" name="logo" accept="image/jpeg,image/png,image/webp,image/gif"></div>'
        . '<div class="af-field"><label for="tr-mensaje">Mensaje público</label><input type="text" id="tr-mensaje" name="mensaje_publico" maxlength="255" placeholder="Banda cerrada, buscamos miembros..." value="' . $mensaje_publico_val . '"></div>'
        . '<label class="tr-check"><input type="checkbox" name="buscando_miembros" value="1"' . $buscando_checked . '> Buscando miembros</label>'
        . '<button type="submit" class="btn-op btn-op--sm btn-op--primario">Guardar</button>'
        . '</form>';
} else {
    $t_identidad_html = '<p class="tr-nombre-fijo">' . $t_nombre . '</p>';
    if (!empty($tripulacion['mensaje_publico'])) {
        $t_identidad_html .= '<p class="tr-mensaje-publico">' . htmlspecialchars($tripulacion['mensaje_publico']) . '</p>';
    }
}

// Miembros: filas de tabla, con acciones si corresponde.
$miembros_html = '';
foreach ($miembros as $m) {
    $fid_m = (int) $m['fid'];
    $rango_m = (int) $m['rango'];
    $nombre_m = htmlspecialchars($m['nombre_personaje']);
    $rol_m = htmlspecialchars($m['rol_decorativo']);
    $puedo_quitar = $es_capitan_o_vice || $fid_m === $uid;

    $acciones_m = '';
    if ($puedo_quitar) {
        $texto_boton = $fid_m === $uid ? 'Dejar tripulación' : 'Quitar';
        $confirmar = $fid_m === $uid ? '¿Seguro que quieres dejar la tripulación?' : "¿Quitar a {$nombre_m}?";
        $acciones_m .= '<form method="post" class="tr-inline" onsubmit="return confirm(\'' . htmlspecialchars($confirmar, ENT_QUOTES) . '\')">'
            . $id_hidden
            . '<input type="hidden" name="my_post_key" value="' . $post_key_html . '">'
            . '<input type="hidden" name="accion" value="quitar_miembro">'
            . '<input type="hidden" name="fid" value="' . $fid_m . '">'
            . '<button type="submit" class="opg-chip chip-peligro">' . $texto_boton . '</button>'
            . '</form>';
    }
    if ($es_capitan_o_vice) {
        $acciones_m .= '<form method="post" class="tr-inline tr-rango-form">'
            . $id_hidden
            . '<input type="hidden" name="my_post_key" value="' . $post_key_html . '">'
            . '<input type="hidden" name="accion" value="cambiar_rango">'
            . '<input type="hidden" name="fid" value="' . $fid_m . '">'
            . '<select name="rango" onchange="this.form.submit()">';
        foreach ($RANGOS as $valor => $etiqueta) {
            $sel = $valor === $rango_m ? ' selected' : '';
            $acciones_m .= '<option value="' . $valor . '"' . $sel . '>' . $etiqueta . '</option>';
        }
        $acciones_m .= '</select></form>';
    }

    $badge_clase = $rango_m === 2 ? 'tr-badge-capitan' : ($rango_m === 1 ? 'tr-badge-vice' : 'tr-badge-miembro');
    $miembros_html .= '<li class="tr-miembro">'
        . '<span class="' . $badge_clase . '">' . htmlspecialchars($RANGOS[$rango_m]) . '</span>'
        . '<a target="_blank" href="/op/personaje.php?uid=' . $fid_m . '" class="tr-miembro-nombre">' . $nombre_m . '</a>'
        . ($rol_m !== '' ? '<span class="tr-miembro-rol">' . $rol_m . '</span>' : '')
        . '<span class="tr-miembro-acciones">' . $acciones_m . '</span>'
        . '</li>';
}
$miembros_html = '<ul class="tr-lista-miembros">' . $miembros_html . '</ul>';

$agregar_html = '';
if ($es_capitan_o_vice) {
    $agregar_html = '<form method="post" class="tr-agregar">'
        . $id_hidden
        . '<input type="hidden" name="my_post_key" value="' . $post_key_html . '">'
        . '<input type="hidden" name="accion" value="agregar_miembro">'
        . '<input type="number" name="fid" min="1" required placeholder="FID del personaje a agregar">'
        . '<button type="submit" class="btn-op btn-op--sm btn-op--primario">Agregar</button>'
        . '</form>';
}

eval("\$page = \"" . $templates->get("op_tripulacion") . "\";");
output_page($page);
