<?php
/**
 * Staff - Entregar recompensas de aventuras (T1-T10, con variante Infra)
 *
 * Mismos problemas que op/staff/entregar_recompensas.php, a mayor escala
 * (20 bloques T1-T10Infra casi idénticos en vez de 4):
 * 1. `THIS_SCRIPT` ya era correcto acá, pero se deja explícito.
 * 2. Inyección SQL: `ficha_id`, `tid` y `reputacion` (alineación) sin
 *    escapar en los UPDATE.
 * 3. Inyección SQL de segundo orden + XSS almacenado: `log_audit()` no
 *    escapa `$log`, y `log_entregas.php` lo renderiza como HTML crudo a
 *    propósito. `tid` (antes texto libre) se fuerza a entero y el log
 *    completo se escapa para SQL antes de guardarse.
 * 4. CSRF: la petición AJAX no llevaba `my_post_key`.
 * 5. 20 bloques casi idénticos (T1..T10, cada uno + su variante "Infra" que
 *    solo multiplica berries por 1.25 extra) se unifican en una tabla de
 *    10 tramos (`$TRAMOS`) y un único bloque de procesamiento que detecta
 *    el sufijo "Infra" con una expresión regular.
 * 6. No se validaba que el UID tuviera ficha real antes de operar.
 * 7. El chequeo de permisos estaba solo al final; ahora es lo primero.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'entregar_recompensas_aventuras.php');
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

// Tramos base T1-T10. La variante "<tramo>Infra" (detectada por sufijo en
// $accion) reutiliza el mismo tramo y solo multiplica berries x1.25 extra
// (bono de "servicio de inframundo").
$TRAMOS = array(
    'T1'  => array('xp' => 50.0,  'nikas' => 5,   'berries' => 1000000,   'berries_label' => '1.000.000',   'reputacion' => 10),
    'T2'  => array('xp' => 75.0,  'nikas' => 10,  'berries' => 5000000,   'berries_label' => '5.000.000',   'reputacion' => 20),
    'T3'  => array('xp' => 120.0, 'nikas' => 20,  'berries' => 10000000,  'berries_label' => '10.000.000',  'reputacion' => 50),
    'T4'  => array('xp' => 150.0, 'nikas' => 30,  'berries' => 15000000,  'berries_label' => '15.000.000',  'reputacion' => 80),
    'T5'  => array('xp' => 200.0, 'nikas' => 40,  'berries' => 25000000,  'berries_label' => '25.000.000',  'reputacion' => 120),
    'T6'  => array('xp' => 250.0, 'nikas' => 50,  'berries' => 50000000,  'berries_label' => '50.000.000',  'reputacion' => 160),
    'T7'  => array('xp' => 300.0, 'nikas' => 60,  'berries' => 75000000,  'berries_label' => '75.000.000',  'reputacion' => 200),
    'T8'  => array('xp' => 400.0, 'nikas' => 80,  'berries' => 100000000, 'berries_label' => '100.000.000', 'reputacion' => 250),
    'T9'  => array('xp' => 600.0, 'nikas' => 100, 'berries' => 200000000, 'berries_label' => '200.000.000', 'reputacion' => 300),
    'T10' => array('xp' => 750.0, 'nikas' => 150, 'berries' => 350000000, 'berries_label' => '350.000.000', 'reputacion' => 500),
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

    $es_infra = false;
    $tramo_key = $accion;
    if (substr($accion, -5) === 'Infra') {
        $es_infra = true;
        $tramo_key = substr($accion, 0, -5);
    }

    if (!isset($TRAMOS[$tramo_key])) {
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

    $tramo = $TRAMOS[$tramo_key];

    // Bonos por oficio: Mercader/Contrabandista sube el multiplicador de
    // berries, Investigador/Arqueólogo sube el multiplicador de XP o suma
    // nikas extra, según su nivel (sub-especialización).
    $oficios = json_decode($ficha['oficios']);
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

    $berriesMult = $contrabandistaMult * ($es_infra ? 1.25 : 1);
    $berriesMultLabel = $es_infra ? "{$contrabandistaMult} * 1.25" : (string) $contrabandistaMult;

    $newpointsNew = $newpoints + ($tramo['xp'] * $arqueologoExtraXP);
    $nikasNew = $nikas + $tramo['nikas'] + $arqueologoExtraNikas;
    $berriesNew = (int) round($berries + ($tramo['berries'] * $berriesMult));
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
        * <strong><i><u>Berries</u></i></strong>: {$berries} -> {$berriesNew} (+{$tramo['berries_label']} * {$berriesMultLabel}) <br>
        * <strong><i><u>Experiencia</u></i></strong>: {$newpoints} -> {$newpointsNew} (+{$tramo['xp']}) <br>
        * <strong><i><u>Nikas</u></i></strong>: {$nikas} -> {$nikasNew} (+{$tramo['nikas']}) <br>
        * <strong><i><u>Reputación</u></i></strong>: {$reputacion} -> {$reputacionNew} (+{$tramo['reputacion']}) <br>
        {$reputacionTxt} <br>
        {$contrabandistaTxt} <br>
        {$arqueologoTxt} <br>
    ";
    log_audit($uid, $username, '[Entregas][Aventura Usuario]', $db->escape_string($textoLog));

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

eval("\$page = \"".$templates->get("staff_entregar_recompensas_aventuras")."\";");
output_page($page);
