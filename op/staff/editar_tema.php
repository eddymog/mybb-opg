<?php
/**
 * Staff - Editar fecha in-game de un tema
 *
 * Cambia year/estacion/day (columnas custom de mybb_threads) de un tema.
 * Antes esto se disparaba con un simple ?tid=..&estacion=..&dia=..&ano= por
 * GET, sin CSRF: un <img> escondido en un post, visto por un staff logueado,
 * alcanzaba para editarle la fecha a cualquier tema. Ahora el cambio real es
 * un POST con my_post_key; el ?tid= por GET se mantiene solo para buscar
 * (no escribe nada).
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'editar_tema.php');
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

define('EDITAR_TEMA_MSG_COOKIE', 'editar_tema_msg');
$ESTACIONES = array('Primavera', 'Verano', 'Otoño', 'Invierno');

function editar_tema_url($tid = 0)
{
    return 'editar_tema.php' . ($tid > 0 ? '?tid=' . (int) $tid : '');
}

// ── POST: guardar la fecha ───────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $tid      = (int) $mybb->get_input('tid', MyBB::INPUT_INT);
    $estacion = $mybb->get_input('estacion', MyBB::INPUT_STRING);
    $dia      = trim($mybb->get_input('dia', MyBB::INPUT_STRING));
    $ano      = trim($mybb->get_input('ano', MyBB::INPUT_STRING));

    $error = '';
    if ($tid <= 0 || !$db->fetch_field($db->query("SELECT tid FROM `mybb_threads` WHERE tid=" . $tid), 'tid')) {
        $error = 'Ese tema no existe.';
    } elseif (!in_array($estacion, $ESTACIONES, true)) {
        $error = 'La estación debe ser una de: ' . implode(', ', $ESTACIONES) . '.';
    } elseif ($dia === '' || !ctype_digit($dia)) {
        $error = 'El día tiene que ser un número.';
    } elseif ($ano === '' || !ctype_digit($ano)) {
        $error = 'El año tiene que ser un número.';
    }

    if ($error === '') {
        $estacion_esc = $db->escape_string($estacion);
        $dia_esc      = $db->escape_string($dia);
        $ano_esc      = $db->escape_string($ano);
        $db->query("UPDATE `mybb_threads` SET `year`='{$ano_esc}', `estacion`='{$estacion_esc}', `day`='{$dia_esc}' WHERE `tid`={$tid}");

        $log_texto = "El staff {$mybb->user['username']} editó el tema #{$tid}: "
            . "estación={$estacion}, día={$dia}, año={$ano}.";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . $db->escape_string($uid) . "', '" . $db->escape_string($mybb->user['username']) . "', 'Apertura', '"
            . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(EDITAR_TEMA_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Fecha actualizada.',
    ))), 15, true);
    header('Location: ' . editar_tema_url($tid));
    exit;
}

// ── GET: buscar/mostrar ──────────────────────────────────────────────────────

$tid = (int) $mybb->get_input('tid', MyBB::INPUT_INT);
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[EDITAR_TEMA_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[EDITAR_TEMA_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(EDITAR_TEMA_MSG_COOKIE);
}

$thread = null;
if ($tid > 0) {
    $thread = $db->fetch_array($db->query("SELECT tid, subject, year, estacion, day FROM `mybb_threads` WHERE tid={$tid}"));
    if (!$thread) { $thread = false; } // distingue "no buscado" de "buscado y no existe"
}

$et_form = '';
if ($tid > 0 && $thread === false) {
    $et_form = '<p class="opg-vacio">No se encontró ningún tema con el ID ' . $tid . '.</p>';
} elseif ($thread) {
    $opciones = '';
    foreach ($ESTACIONES as $e) {
        $sel = $e === $thread['estacion'] ? ' selected' : '';
        $opciones .= '<option value="' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $et_form = '<form method="post" class="et-form">'
        . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($post_key, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="tid" value="' . (int) $thread['tid'] . '">'
        . '<div class="et-row">'
        . '<div class="af-field"><label for="et-dia">Día</label><input type="text" inputmode="numeric" id="et-dia" name="dia" value="' . htmlspecialchars($thread['day'], ENT_QUOTES, 'UTF-8') . '" required></div>'
        . '<div class="af-field"><label for="et-ano">Año</label><input type="text" inputmode="numeric" id="et-ano" name="ano" value="' . htmlspecialchars($thread['year'], ENT_QUOTES, 'UTF-8') . '" required></div>'
        . '<div class="af-field"><label for="et-estacion">Estación</label><select id="et-estacion" name="estacion">' . $opciones . '</select></div>'
        . '</div>'
        . '<button class="btn-op btn-op--primario">Guardar fecha</button>'
        . '</form>';
}

$et_tema_actual = $thread ? htmlspecialchars('#' . $thread['tid'] . ' — ' . $thread['subject'], ENT_QUOTES, 'UTF-8') : '';
$et_tid_valor   = $tid > 0 ? (int) $tid : '';

eval("\$page = \"".$templates->get("staff_editar_tema")."\";");
output_page($page);
