<?php
/**
 * Staff - Entregar recompensas de aventura a narradores (T1-T10)
 *
 * Mismos problemas que las otras páginas "entregar_recompensas_*":
 * 1. Inyección SQL: `ficha_id`, `tid`, `usuarios` y `nivel_narrador` sin
 *    escapar, tanto acá como dentro de `darObjeto()` (`$objeto_id`/`$fid`
 *    tampoco se escapaban ahí).
 * 2. Inyección SQL de segundo orden + XSS almacenado vía `log_audit()`
 *    (no escapa `$log`, y `log_entregas.php` lo renderiza como HTML crudo
 *    a propósito): `tid` se mostraba envuelto en un `<a href>` armado con
 *    el valor crudo. Se fuerza a entero (es un ID de tema) y se escapa el
 *    log completo para SQL antes de guardarlo.
 * 3. CSRF: la petición AJAX no llevaba `my_post_key`.
 * 4. 10 bloques T1-T10 casi idénticos (~500 líneas), más ~280 líneas de
 *    bloques T4-T10 comentados de un borrador viejo ya reemplazado por los
 *    de arriba (muertos, se eliminan). Se unifican en una tabla de tramos
 *    (`$TRAMOS`) y un único bloque de procesamiento. El bono por nivel de
 *    narrador no sigue una fórmula única entre tramos, así que se guarda
 *    como tabla explícita [mult 0..3] por tramo en vez de intentar
 *    aproximarlo con una fórmula (para no arriesgar un cambio de valores).
 * 5. No se validaba que el UID tuviera ficha real antes de operar, ni que
 *    `usuarios` fuera un número razonable (podía dar multiplicador
 *    negativo con 0 o vacío).
 * 6. El chequeo de permisos estaba solo al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'entregar_recompensas_narradores.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid) && !is_narra($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

function darObjeto($objeto_id, $cantidadNueva, $fid)
{
    global $db;
    $objeto_id_esc = $db->escape_string($objeto_id);
    $fid_esc = $db->escape_string($fid);

    $actual = $db->fetch_array($db->query("SELECT `cantidad` FROM `mybb_op_inventario` WHERE `uid`='{$fid_esc}' AND `objeto_id`='{$objeto_id_esc}'"));
    if ($actual) {
        $cantidadNuevaNueva = (int) $actual['cantidad'] + (int) $cantidadNueva;
        $db->query("UPDATE `mybb_op_inventario` SET `cantidad`='" . (int) $cantidadNuevaNueva . "' WHERE `objeto_id`='{$objeto_id_esc}' AND `uid`='{$fid_esc}'");
    } else {
        $db->query("INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES ('{$objeto_id_esc}', '{$fid_esc}', '1')");
    }
}

// Tramos T1-T10. xp/nikas = base; xp_bonus/nikas_bonus = bono según nivel
// de narrador (índice 0-3 = Aprendiz/Estudioso/Ilustre/Erudito), tabla
// explícita porque no sigue una fórmula común entre tramos. El bono por
// cantidad de usuarios en la party (`round(base * usuariosMult)`) siempre
// se calcula sobre la base, nunca sobre el valor ya con bono de narrador.
$TRAMOS = array(
    'T1'  => array('xp' => 50.0,  'nikas' => 6,   'xp_bonus' => array(0, 15, 30, 65), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR002' => 1), 'cofre_label' => 'Decente'),
    'T2'  => array('xp' => 75.0,  'nikas' => 12,  'xp_bonus' => array(0, 10, 25, 55), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR003' => 1), 'cofre_label' => 'Gigante'),
    'T3'  => array('xp' => 120.0, 'nikas' => 22,  'xp_bonus' => array(0, 20, 35, 50), 'nikas_bonus' => array(0, 2, 3, 4), 'objetos' => array('CFR003' => 2), 'cofre_label' => 'x2 Gigante'),
    'T4'  => array('xp' => 150.0, 'nikas' => 34,  'xp_bonus' => array(0, 20, 30, 40), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR004' => 1), 'cofre_label' => 'x1 Cobrizo'),
    'T5'  => array('xp' => 200.0, 'nikas' => 45,  'xp_bonus' => array(0, 20, 35, 50), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR004' => 2), 'cofre_label' => 'x2 Cobrizo'),
    'T6'  => array('xp' => 250.0, 'nikas' => 56,  'xp_bonus' => array(0, 15, 30, 50), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR005' => 1), 'cofre_label' => 'x1 Argénteo'),
    'T7'  => array('xp' => 300.0, 'nikas' => 67,  'xp_bonus' => array(0, 20, 50, 85), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR005' => 2), 'cofre_label' => 'x2 Argénteos'),
    'T8'  => array('xp' => 400.0, 'nikas' => 88,  'xp_bonus' => array(0, 0, 10, 30),  'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR006' => 1), 'cofre_label' => 'x1 Áureo'),
    'T9'  => array('xp' => 560.0, 'nikas' => 109, 'xp_bonus' => array(0, 20, 40, 60), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR007' => 1), 'cofre_label' => 'x1 Épico'),
    'T10' => array('xp' => 730.0, 'nikas' => 160, 'xp_bonus' => array(0, 20, 45, 70), 'nikas_bonus' => array(0, 1, 2, 3), 'objetos' => array('CFR008' => 1), 'cofre_label' => 'x1 Legendario'),
);

$NARRADOR_MULT = array('Aprendiz' => 0, 'Estudioso' => 1, 'Ilustre' => 2, 'Erudito' => 3);

// ── POST (AJAX): entregar recompensa ────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    header('Content-type: application/json');

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
    $ficha_id = trim($mybb->get_input('ficha_id', MyBB::INPUT_STRING));
    $tid = (int) $mybb->get_input('tid', MyBB::INPUT_INT);
    $usuarios = max(1, min(6, (int) $mybb->get_input('usuarios', MyBB::INPUT_INT)));
    $nivel_narrador = $mybb->get_input('nivel_narrador', MyBB::INPUT_STRING);
    $narradorMult = $NARRADOR_MULT[$nivel_narrador] ?? 0;

    if (!isset($TRAMOS[$accion])) {
        echo json_encode(array('error' => 'Tramo inválido.'));
        exit;
    }
    if ($ficha_id === '') {
        echo json_encode(array('error' => 'Falta el UID del personaje.'));
        exit;
    }
    if ($tid <= 0) {
        echo json_encode(array('error' => 'El ID del tema es obligatorio.'));
        exit;
    }

    $ficha = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($ficha_id) . "'"));
    $users = $db->fetch_array($db->query("SELECT * FROM `mybb_users` WHERE uid='" . $db->escape_string($ficha_id) . "'"));
    if (!$ficha || !$users) {
        echo json_encode(array('error' => 'Ese UID no tiene ficha.'));
        exit;
    }

    $tramo = $TRAMOS[$accion];
    $nombre = $ficha['nombre'];
    $nikas = (int) $ficha['nika'];
    $newpoints = (float) $users['newpoints'];

    $usuariosMult = ($usuarios - 1) / 10;
    $usuariosPctTxt = (($usuarios - 1) * 10) . '%';

    $newpointsNew = $newpoints + $tramo['xp'] + $tramo['xp_bonus'][$narradorMult] + round($tramo['xp'] * $usuariosMult);
    $nikasNew = $nikas + $tramo['nikas'] + $tramo['nikas_bonus'][$narradorMult] + round($tramo['nikas'] * $usuariosMult);

    $db->query("UPDATE `mybb_op_fichas` SET `nika`='" . (int) $nikasNew . "' WHERE `fid`='" . $db->escape_string($ficha_id) . "'");
    $db->query("UPDATE `mybb_users` SET `newpoints`='" . $db->escape_string($newpointsNew) . "' WHERE `uid`='" . $db->escape_string($ficha_id) . "'");

    $tidUrl = "<a href='https://onepiecegaiden.com/showthread.php?tid={$tid}' target='_blank'>{$tid}</a>";
    $nivel_narrador_esc = htmlspecialchars($nivel_narrador, ENT_QUOTES, 'UTF-8');

    $textoLog = "
        <strong><i><u>Moderador</u></i></strong>: {$username} ({$uid}) <br>
        <strong><i><u>Usuario</u></i></strong>: {$nombre} ({$ficha_id}) <br>
        <strong><i><u>ID del tema</u></i></strong>: {$tidUrl} <br>
        * <strong><i><u>Experiencia</u></i></strong>: {$newpoints} -> {$newpointsNew} <br>
        * <strong><i><u>Nikas</u></i></strong>: {$nikas} -> {$nikasNew} <br>
        * <strong><i><u>Cofre</u></i></strong>: {$tramo['cofre_label']} <br>
        * <strong><i><u>Usuarios Multiplicador</u></i></strong>: {$usuarios} ({$usuariosPctTxt}) <br>
        * <strong><i><u>Nivel Narrador</u></i></strong>: {$nivel_narrador_esc} <br>
    ";

    foreach ($tramo['objetos'] as $objeto_id => $cantidad) {
        for ($i = 0; $i < $cantidad; $i++) {
            darObjeto($objeto_id, 1, $ficha_id);
        }
    }

    log_audit($uid, $username, '[Entregas][Aventura Narradores]', $db->escape_string($textoLog));

    echo json_encode(array('ok' => true, 'timestamp' => time()));
    exit;
}

// ── GET: formulario ────────────────────────────────────────────────────────────

$fid = trim($mybb->get_input('fid', MyBB::INPUT_STRING));
$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
$accion = isset($TRAMOS[$accion]) ? $accion : '';
$post_key = generate_post_check();

$ficha = null;
if ($fid !== '') {
    $ficha = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($fid) . "'"));
}
$ficha_existe = $ficha ? 1 : 0;
$fid_esc = htmlspecialchars($fid, ENT_QUOTES, 'UTF-8');
$accion_esc = htmlspecialchars($accion, ENT_QUOTES, 'UTF-8');
$ficha_nombre_esc = htmlspecialchars($ficha ? $ficha['nombre'] : '', ENT_QUOTES, 'UTF-8');

eval("\$page = \"".$templates->get("staff_entregar_recompensas_narradores")."\";");
output_page($page);
