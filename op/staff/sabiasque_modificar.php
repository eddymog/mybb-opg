<?php
/**
 * Staff - Modificar "Sabías qué"
 *
 * Crea/edita las frases de mybb_op_sabiasque que global.php elige al azar y
 * header.html muestra en el header de TODA página del foro ($g_sabiasque).
 *
 * Reescrito por 3 problemas reales que tenía:
 * 1. Inyección SQL: `sabiasque_id` (GET) se usaba crudo en un WHERE, y la
 *    consulta corría ANTES del chequeo de permisos — cualquiera, sin sesión,
 *    podía inyectar SQL con solo visitar la URL. Ahora todo id es (int) y el
 *    acceso se valida antes de tocar la base de datos.
 * 2. CSRF: el <form> de guardado no llevaba my_post_key ni el PHP lo pedía —
 *    un formulario auto-enviado en cualquier otra página, visto por un staff
 *    logueado, alcanzaba para modificar el mensaje que ve todo el foro.
 * 3. La razón por la que esto importaba más que un formulario cualquiera:
 *    global.php ya guarda $g_sabiasque['texto']/['autor'] escapados con
 *    htmlspecialchars() antes de pasarlos a header.html, así que el texto
 *    guardado acá puede seguir siendo el texto "de verdad" (sin escapar) —
 *    el escape pasa en la lectura, una sola vez, no en cada guardado.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'sabiasque_modificar.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('SABIASQUE_MSG_COOKIE', 'sabiasque_msg');
$TIPOS = array(
    1 => 'Sabías qué',
    2 => 'Hay rumores sobre',
    3 => 'Alguien dijo una vez',
    4 => 'En este foro',
);

function sabiasque_url($id = 0)
{
    return 'sabiasque_modificar.php' . ($id > 0 ? '?sabiasque_id=' . (int) $id : '');
}

// ── POST: guardar ────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $id     = (int) $mybb->get_input('sabiasque_id', MyBB::INPUT_INT);
    $tipo   = (int) $mybb->get_input('tipo', MyBB::INPUT_INT);
    $texto  = trim($mybb->get_input('texto', MyBB::INPUT_STRING));
    $autor  = trim($mybb->get_input('autor', MyBB::INPUT_STRING));

    $error = '';
    if (!isset($TIPOS[$tipo])) { $error = 'Tipo inválido.'; }
    elseif ($texto === '' || mb_strlen($texto) > 1000) { $error = 'El texto es obligatorio (máx. 1000).'; }
    elseif (mb_strlen($autor) > 100) { $error = 'El autor no puede superar 100 caracteres.'; }

    if ($error === '') {
        $texto_esc = $db->escape_string($texto);
        $autor_esc = $db->escape_string($autor);
        $existe = $id > 0 && $db->fetch_field($db->query("SELECT id FROM `mybb_op_sabiasque` WHERE id={$id}"), 'id');

        if ($existe) {
            $db->query("UPDATE `mybb_op_sabiasque` SET `tipo`={$tipo}, `texto`='{$texto_esc}', `autor`='{$autor_esc}' WHERE `id`={$id}");
        } else {
            $id = $id > 0 ? $id : (int) $db->fetch_field($db->query("SELECT COALESCE(MAX(id), 0) + 1 AS siguiente FROM `mybb_op_sabiasque`"), 'siguiente');
            $db->query("INSERT INTO `mybb_op_sabiasque` (`id`, `tipo`, `texto`, `autor`) VALUES ({$id}, {$tipo}, '{$texto_esc}', '{$autor_esc}')");
        }
    }

    my_setcookie(SABIASQUE_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : ($existe ? 'Modificado.' : 'Creado.'),
    ))), 15, true);
    header('Location: ' . sabiasque_url($id));
    exit;
}

// ── GET: formulario + listado ────────────────────────────────────────────────

$id = (int) $mybb->get_input('sabiasque_id', MyBB::INPUT_INT);
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[SABIASQUE_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[SABIASQUE_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(SABIASQUE_MSG_COOKIE);
}

$sabiasque = $id > 0 ? $db->fetch_array($db->query("SELECT * FROM `mybb_op_sabiasque` WHERE id={$id}")) : false;

$sq_opciones = '';
foreach ($TIPOS as $valor => $nombre) {
    $sel = $sabiasque && (int) $sabiasque['tipo'] === $valor ? ' selected' : '';
    $sq_opciones .= '<option value="' . $valor . '"' . $sel . '>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</option>';
}

$sq_form = '<form method="post" class="sq-form">'
    . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($post_key, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="sabiasque_id" value="' . ($sabiasque ? (int) $sabiasque['id'] : 0) . '">'
    . '<div class="af-field"><label for="sq-tipo">Tipo</label><select id="sq-tipo" name="tipo">' . $sq_opciones . '</select></div>'
    . '<div class="af-field"><label for="sq-autor">Autor (opcional)</label><input type="text" id="sq-autor" name="autor" maxlength="100" value="' . htmlspecialchars($sabiasque ? $sabiasque['autor'] : '', ENT_QUOTES, 'UTF-8') . '"></div>'
    . '<div class="af-field" style="flex-basis:100%"><label for="sq-texto">Texto</label><textarea id="sq-texto" name="texto" maxlength="1000" required>' . htmlspecialchars($sabiasque ? $sabiasque['texto'] : '', ENT_QUOTES, 'UTF-8') . '</textarea></div>'
    . '<button class="btn-op btn-op--primario">' . ($sabiasque ? 'Guardar cambios' : 'Crear') . '</button>'
    . '</form>';

// Listado completo, para elegir uno para editar o ver ids libres.
$sq_lista = '';
$filas = $db->query("SELECT id, tipo, texto, autor FROM `mybb_op_sabiasque` ORDER BY id");
while ($f = $db->fetch_array($filas)) {
    $es_actual = $sabiasque && (int) $f['id'] === (int) $sabiasque['id'];
    $sq_lista .= '<a class="opg-chip' . ($es_actual ? ' chip-activo' : '') . '" href="' . sabiasque_url($f['id']) . '" title="' . htmlspecialchars($f['texto'], ENT_QUOTES, 'UTF-8') . '">'
        . '#' . (int) $f['id'] . ' · ' . htmlspecialchars($TIPOS[(int) $f['tipo']] ?? $f['tipo'], ENT_QUOTES, 'UTF-8') . '</a>';
}
if ($sq_lista === '') { $sq_lista = '<p class="opg-vacio">Todavía no hay ninguno cargado.</p>'; }

$sq_editando = $sabiasque ? htmlspecialchars('#' . $sabiasque['id'], ENT_QUOTES, 'UTF-8') : '';

eval("\$page = \"".$templates->get("staff_sabiasque_modificar")."\";");
output_page($page);
