<?php
/**
 * Staff - Crear / modificar técnicas
 *
 * Catálogo de mybb_op_tecnicas, el mismo que lee el BBCode [tecnica=TID]
 * (inc/plugins/BBCustom_tecnica.php) para armar la tarjeta que se ve en los
 * posts. `clase` y `tipo` tienen un catálogo fijo de valores porque
 * op/tecnicas_buscar.php ya los valida así ($clases_validas, $tipos_validos):
 * este formulario usa esos mismos valores en los <select> para no volver a
 * inventar strings sueltos que ese buscador no reconocería.
 *
 * Reescrito por 3 problemas reales que tenía:
 * 1. Inyección SQL: la mayoría de los campos (nombre, tid, estilo, energía,
 *    haki, requisitos...) no pasaban ni por addslashes(), y la búsqueda por
 *    ?tecnica_id= corría antes del chequeo de permisos — cualquiera, sin
 *    sesión, podía inyectar SQL con solo visitar la URL.
 * 2. CSRF: el <form> no llevaba my_post_key y el PHP nunca lo pedía.
 * 3. Variables muertas que referenciaban columnas que ya no existen
 *    (`version`, `rango`, `coste` no están en el schema actual de
 *    mybb_op_tecnicas) — quedaban de un copy-paste viejo, sin usarse nunca.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_modificar.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('TECNICAS_MSG_COOKIE', 'tecnicas_msg');
$TECNICAS_CLASES = array('Activa', 'Pasiva', 'Mantenida', 'Conjunta');
$TECNICAS_TIPOS  = array('Ofensiva', 'Defensiva', 'Ambiental', 'Elusiva', 'Utilidad');
$TECNICAS_CAMPOS = array(
    'estilo', 'clase', 'tier', 'rama', 'tipo', 'energia', 'energia_turno',
    'haki', 'haki_turno', 'enfriamiento', 'efectos', 'requisitos',
);

// ── Typeahead: busca por tid o nombre, de solo lectura ───────────────────────
if ($mybb->get_input('buscar_tecnica', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_tecnica', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $resultados = array();
    $query = $db->query("SELECT tid, nombre FROM `mybb_op_tecnicas` WHERE tid LIKE '%{$like}%' OR nombre LIKE '%{$like}%' ORDER BY nombre LIMIT 20");
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['tid'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

function tecnicas_url($id = '')
{
    return 'tecnicas_modificar.php' . ($id !== '' ? '?tecnica_id=' . rawurlencode($id) : '');
}

// ── POST: crear/modificar ────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $tid_old     = trim($mybb->get_input('tid_old', MyBB::INPUT_STRING));
    $tid_new     = trim($mybb->get_input('tid', MyBB::INPUT_STRING));
    $nombre      = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
    $estilo      = trim($mybb->get_input('estilo', MyBB::INPUT_STRING));
    $clase       = trim($mybb->get_input('clase', MyBB::INPUT_STRING));
    $tipo        = trim($mybb->get_input('tipo', MyBB::INPUT_STRING));
    $descripcion = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));

    $error = '';
    if ($tid_new === '') { $error = 'El técnica ID es obligatorio.'; }
    elseif ($nombre === '') { $error = 'El nombre es obligatorio.'; }
    elseif ($estilo === '') { $error = 'El estilo es obligatorio.'; }
    elseif (!in_array($clase, $TECNICAS_CLASES, true)) { $error = 'La clase debe ser una de: ' . implode(', ', $TECNICAS_CLASES) . '.'; }
    elseif (!in_array($tipo, $TECNICAS_TIPOS, true)) { $error = 'El tipo debe ser uno de: ' . implode(', ', $TECNICAS_TIPOS) . '.'; }
    elseif ($descripcion === '') { $error = 'La descripción es obligatoria.'; }

    if ($error === '') {
        $campos = array('nombre' => $nombre, 'estilo' => $estilo, 'clase' => $clase, 'tipo' => $tipo, 'descripcion' => $descripcion);
        foreach ($TECNICAS_CAMPOS as $campo) {
            if (!isset($campos[$campo])) {
                $campos[$campo] = trim($mybb->get_input($campo, MyBB::INPUT_STRING));
            }
        }

        $existe = (bool) $db->fetch_field($db->query("SELECT tid FROM `mybb_op_tecnicas` WHERE tid='" . $db->escape_string($tid_new) . "'"), 'tid');
        $es_rename = $tid_old !== '' && $tid_old !== $tid_new
            && $db->fetch_field($db->query("SELECT tid FROM `mybb_op_tecnicas` WHERE tid='" . $db->escape_string($tid_old) . "'"), 'tid');

        if ($es_rename) {
            // Los usuarios que ya la aprendieron quedan apuntando al tid nuevo.
            $db->query("UPDATE `mybb_op_tec_aprendidas` SET `tid`='" . $db->escape_string($tid_new) . "' WHERE tid='" . $db->escape_string($tid_old) . "'");
        }

        $set = array();
        foreach ($campos as $col => $val) {
            $set[] = "`{$col}`='" . $db->escape_string($val) . "'";
        }
        $set[] = "`tid`='" . $db->escape_string($tid_new) . "'";

        if ($existe || $es_rename) {
            $id_where = $es_rename ? $tid_old : $tid_new;
            $db->query("UPDATE `mybb_op_tecnicas` SET " . implode(', ', $set) . " WHERE `tid`='" . $db->escape_string($id_where) . "'");
        } else {
            $cols = array('tid');
            $vals = array("'" . $db->escape_string($tid_new) . "'");
            foreach ($campos as $col => $val) {
                $cols[] = $col;
                $vals[] = "'" . $db->escape_string($val) . "'";
            }
            $db->query("INSERT INTO `mybb_op_tecnicas` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ")");
        }

        $log_texto = "Técnica {$tid_new} (" . ($existe || $es_rename ? 'modificada' : 'creada') . " por {$mybb->user['username']}): nombre={$nombre}, clase={$clase}, tipo={$tipo}.";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . (int) $uid . "', '" . $db->escape_string($mybb->user['username']) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(TECNICAS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Técnica guardada.',
    ))), 15, true);
    header('Location: ' . tecnicas_url($error !== '' ? $tid_old : $tid_new));
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$tecnica_id = trim($mybb->get_input('tecnica_id', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[TECNICAS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[TECNICAS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(TECNICAS_MSG_COOKIE);
}

$tecnica = null;
if ($tecnica_id !== '') {
    $tecnica = $db->fetch_array($db->query("SELECT * FROM `mybb_op_tecnicas` WHERE tid='" . $db->escape_string($tecnica_id) . "'"));
}

$TECNICAS_CAMPOS_FORM = array_merge(array('nombre', 'estilo', 'clase', 'tipo', 'descripcion'), $TECNICAS_CAMPOS);
$tecnica_esc = array();
foreach ($TECNICAS_CAMPOS_FORM as $campo) {
    $tecnica_esc[$campo] = htmlspecialchars($tecnica ? (string) $tecnica[$campo] : '', ENT_QUOTES, 'UTF-8');
}
$tecnica_id_esc = htmlspecialchars($tecnica_id, ENT_QUOTES, 'UTF-8');

$tec_opciones_clase = '';
foreach ($TECNICAS_CLASES as $c) {
    $sel = $tecnica && $tecnica['clase'] === $c ? ' selected' : '';
    $tec_opciones_clase .= '<option value="' . $c . '"' . $sel . '>' . $c . '</option>';
}
$tec_opciones_tipo = '';
foreach ($TECNICAS_TIPOS as $t) {
    $sel = $tecnica && $tecnica['tipo'] === $t ? ' selected' : '';
    $tec_opciones_tipo .= '<option value="' . $t . '"' . $sel . '>' . $t . '</option>';
}

eval("\$page = \"".$templates->get("staff_modificar_tecnicas")."\";");
output_page($page);
