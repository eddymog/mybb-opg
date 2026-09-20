<?php
/**
 * Staff - Entregar recompensas (autonarradas T1-T4)
 *
 * Reescrito por varios problemas reales:
 * 1. `THIS_SCRIPT` decía 'entregar_recompensas_aventuras.php' (error de
 *    copiar/pegar), no el nombre real de este archivo.
 * 2. Inyección SQL: `ficha_id`, `tid` y `reputacion` (alineación) se
 *    interpolaban sin escapar en los UPDATE.
 * 3. Inyección SQL de segundo orden + XSS almacenado: `log_audit()` no
 *    escapa internamente su parámetro `$log`, y `log_entregas.php` lo
 *    renderiza como HTML crudo a propósito (negritas/`<br>`) — el `tid`
 *    (antes texto libre) se metía directo en ese log sin escapar. Ahora
 *    `tid` se fuerza a entero (siempre debería serlo, es un ID de tema) y
 *    el log completo se escapa para SQL antes de guardarse.
 * 4. CSRF: la petición AJAX no llevaba `my_post_key`.
 * 5. Los cuatro bloques T1-T4 eran casi idénticos, copiados y pegados con
 *    solo los números cambiados (~230 líneas). Se unifican en una sola
 *    tabla de tramos (`$TRAMOS`) y un único bloque de procesamiento.
 * 6. No se validaba que el UID buscado tuviera ficha real antes de operar
 *    sobre ella — un UID sin ficha causaba warnings de PHP sobre índices
 *    inexistentes y guardaba valores basura.
 * 7. El chequeo de permisos estaba solo al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'entregar_recompensas.php');
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

// Tramos de recompensa: xp = experiencia (mybb_users.newpoints), nikas y
// berries van a mybb_op_fichas, reputacion se suma a `reputacion` y,
// opcionalmente, a `reputacion_positiva`/`reputacion_negativa` según lo que
// elija el staff. berries_label es solo para el texto del log.
$TRAMOS = array(
    'T1' => array('xp' => 30.0,  'nikas' => 2, 'berries' => 300000,   'berries_label' => '300.000',   'reputacion' => 5),
    'T2' => array('xp' => 40.0,  'nikas' => 3, 'berries' => 500000,   'berries_label' => '500.000',   'reputacion' => 10),
    'T3' => array('xp' => 65.0,  'nikas' => 5, 'berries' => 1000000,  'berries_label' => '1.000.000', 'reputacion' => 20),
    'T4' => array('xp' => 100.0, 'nikas' => 8, 'berries' => 1500000,  'berries_label' => '1.500.000', 'reputacion' => 30),
);

// ── POST (AJAX): entregar recompensa ────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    header('Content-type: application/json');

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
    $ficha_id = trim($mybb->get_input('ficha_id', MyBB::INPUT_STRING));
    $tid = (int) $mybb->get_input('tid', MyBB::INPUT_INT);
    $reputacion_align = $mybb->get_input('reputacion', MyBB::INPUT_STRING);
    $reputacion_align = in_array($reputacion_align, array('positiva', 'negativa'), true) ? $reputacion_align : '';

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
    $oficios = json_decode($ficha['oficios']);

    // Bonos por oficio: Mercader/Contrabandista sube el multiplicador de
    // berries, Investigador/Arqueólogo sube el multiplicador de XP o suma
    // nikas extra, según su nivel (sub-especialización).
    $nivelContrabandista = isset($oficios->{'Mercader'}->{'sub'}->{'Contrabandista'})
        ? (int) $oficios->{'Mercader'}->{'sub'}->{'Contrabandista'} : -1;
    $contrabandistaMult = array(0 => 1.25, 1 => 1.50, 2 => 1.75, 3 => 2.00)[$nivelContrabandista] ?? 1;
    $contrabandistaTxt = "<strong><i><u>Nivel de Contrabandista</u></i></strong>: {$nivelContrabandista}. Multiplicador = {$contrabandistaMult}.";

    $nivelArqueologo = isset($oficios->{'Investigador'}->{'sub'}->{'Arqueologo'})
        ? (int) $oficios->{'Investigador'}->{'sub'}->{'Arqueologo'} : -1;
    $arqueologoExtraXP = ($nivelArqueologo === 1) ? 1.10 : 1.00;
    $arqueologoExtraNikas = ($nivelArqueologo === 2) ? 1 : 0;
    $arqueologoTxt = "<strong><i><u>Nivel de Arqueólogo</u></i></strong>: {$nivelArqueologo}. Multiplicador XP = {$arqueologoExtraXP}. Nikas Extra = {$arqueologoExtraNikas}.";

    $nombre = $ficha['nombre'];
    $nikas = (int) $ficha['nika'];
    $berries = (int) $ficha['berries'];
    $reputacion = (int) $ficha['reputacion'];
    $newpoints = (float) $users['newpoints'];

    $newpointsNew = $newpoints + ($tramo['xp'] * $arqueologoExtraXP);
    $nikasNew = $nikas + $tramo['nikas'] + $arqueologoExtraNikas;
    $berriesNew = (int) round($berries + ($tramo['berries'] * $contrabandistaMult));
    $reputacionNew = $reputacion + $tramo['reputacion'];

    $repuAlignDb = '';
    $reputacionTxt = '';
    if ($reputacion_align !== '') {
        $campo_align = 'reputacion_' . $reputacion_align;
        $reputacionAlign = (int) $ficha[$campo_align];
        $reputacionAlignNew = $reputacionAlign + $tramo['reputacion'];
        $repuAlignDb = ", `{$campo_align}`='" . (int) $reputacionAlignNew . "'";
        $etiqueta_align = ($reputacion_align === 'positiva') ? 'Reputación Positiva' : 'Reputación Negativa';
        $reputacionTxt = "* <strong><i><u>{$etiqueta_align}</u></i></strong>: {$reputacionAlign} -> {$reputacionAlignNew} (+{$tramo['reputacion']})";
    }

    $db->query("UPDATE `mybb_op_fichas` SET `nika`='" . (int) $nikasNew . "', `berries`='" . (int) $berriesNew . "', `reputacion`='" . (int) $reputacionNew . "'{$repuAlignDb} WHERE `fid`='" . $db->escape_string($ficha_id) . "'");
    $db->query("UPDATE `mybb_users` SET `newpoints`='" . $db->escape_string($newpointsNew) . "' WHERE `uid`='" . $db->escape_string($ficha_id) . "'");

    $textoLog = "
        <strong><i><u>Moderador</u></i></strong>: {$username} ({$uid}) <br>
        <strong><i><u>Usuario</u></i></strong>: {$nombre} ({$ficha_id}) <br>
        <strong><i><u>ID del tema</u></i></strong>: {$tid} <br>
        * <strong><i><u>Berries</u></i></strong>: {$berries} -> {$berriesNew} (+{$tramo['berries_label']} * {$contrabandistaMult}) <br>
        * <strong><i><u>Experiencia</u></i></strong>: {$newpoints} -> {$newpointsNew} (+{$tramo['xp']}) <br>
        * <strong><i><u>Nikas</u></i></strong>: {$nikas} -> {$nikasNew} (+{$tramo['nikas']}) <br>
        * <strong><i><u>Reputación</u></i></strong>: {$reputacion} -> {$reputacionNew} (+{$tramo['reputacion']}) <br>
        {$reputacionTxt} <br>
        {$contrabandistaTxt} <br>
        {$arqueologoTxt} <br>
    ";
    log_audit($uid, $username, '[Entregas][Autonarrada]', $db->escape_string($textoLog));

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

eval("\$page = \"".$templates->get("staff_entregar_recompensas")."\";");
output_page($page);
