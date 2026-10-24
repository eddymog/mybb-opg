<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'entrenamiento.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb;

define('ENTRENO_MSG_COOKIE', 'entrenamiento_msg');

/** HTML del aviso de resultado (tipo 'ok' o 'err'); el texto se escapa aqui. */
function entreno_aviso_html($tipo, $texto)
{
    return '<div class="entreno-aviso entreno-aviso-' . ($tipo === 'ok' ? 'ok' : 'err') . '" role="status">'
        . htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8') . '</div>';
}

/** Numero para mostrar: siempre con dos decimales (18.13), como se guarda en la base de datos. */
function entreno_num($n)
{
    return number_format((float) $n, 2, '.', '');
}

$entrenar = $_POST["entrenar"];
$cancelar = $_POST["cancelar"];
$culminar = $_POST["culminar"];
$reentrenar = !empty($_POST["reentrenar"]); // "Culminar y volver a entrenar"
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];

// Proteccion CSRF: todo envio de este formulario lleva my_post_key.
if (($entrenar || $cancelar || $culminar) && !verify_post_check($mybb->get_input('my_post_key'), true)) {
    $mensaje_redireccion = "La sesión ha caducado. Recarga la página e inténtalo de nuevo.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}
$aviso_pendiente = null; // resultado de culminar: se muestra tras redirigir (cookie de un solo uso)

$tecnica_en_curso = false;
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;
$efecto_coste = 1;
$exp_usuario = '0';
$time_now = time();

$reload_js = "<script>window.location.href = window.location.href;</script>";

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$query_usuario = $db->query("SELECT * FROM mybb_users WHERE uid='$uid'");
while ($u = $db->fetch_array($query_usuario)) { $exp_usuario = $u['newpoints']; }

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($f = $db->fetch_array($query_ficha)) { 
    $ficha = $f;
    $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
    $ficha_existe = true;
}

$nivel = intval($ficha['nivel']);
$experiencia = $nivel / 2;
$desglose = array('Nivel ' . $nivel . ' ÷ 2 = ' . entreno_num($nivel / 2));
$horas = 24;

$has_intensivo = false; // V042

$has_intensivo_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V042'; "); 
while ($q = $db->fetch_array($has_intensivo_query)) { $has_intensivo = true; }

if ($ficha['raza'] == 'Kobito') { $experiencia = $experiencia * 1.1; $desglose[] = 'Kobito ×1.1'; }

if ($has_intensivo) {
    $experiencia = $experiencia * 1.25;
    $desglose[] = 'Entrenamiento Intensivo ×1.25';
}
// La experiencia se redondea a dos decimales al calcularla: lo que se muestra es exactamente lo que se paga.
$experiencia = round($experiencia, 2);
$experiencia_txt = entreno_num($experiencia);
$desglose_html = htmlspecialchars(implode(' · ', $desglose) . ' = ' . entreno_num($experiencia) . ' de experiencia', ENT_QUOTES, 'UTF-8');

if ($g_oficio_en_curso || $g_oficio_completo) {
    echo "<script>alert('¡Vaya! Parece que ya tienes un oficio activo, así que tendrás que esperar a terminarlo antes de realizar este entrenamiento. ¡GANBARE!'); window.location.href = './../index.php';</script>";
    exit;
}

if ($cancelar) {
    $db->query("
        DELETE FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
    ");
    eval('$reload_script = $reload_js;');
}

if ($culminar) {

    $entreno = null;
    $culminado = false;

    $query_entreno_usuario = $db->query("
        SELECT * FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
    ");

    while ($q = $db->fetch_array($query_entreno_usuario)) {
        $entreno = $q;
    }

    // El servidor decide si se puede culminar: tiene que existir el entrenamiento y haber terminado
    // (el formulario solo pide culminar, no demuestra que haya pasado el tiempo).
    // Se borra la fila ANTES de pagar: solo quien consigue borrarla cobra, asi un doble envio no paga dos veces.
    if (!$entreno) {
        $aviso_pendiente = array('tipo' => 'err', 'texto' => 'No tenías ningún entrenamiento para completar.');
    } else if (time() <= intval($entreno['timestamp_end'])) {
        $aviso_pendiente = array('tipo' => 'err', 'texto' => 'Tu entrenamiento aún no ha terminado.');
    } else {
        $db->query("DELETE FROM mybb_op_entrenamientos_usuarios WHERE id='".intval($entreno['id'])."'");
        if ($db->affected_rows() > 0) {
            $culminado = true;

            $nombre = $db->escape_string($ficha['nombre']);
            $old_exp = $exp_usuario;
            $new_exp = round(floatval($old_exp) + floatval($entreno['recompensa']), 2);

            log_audit_currency($uid, $username, $uid, '[Entrenamiento][Experiencia]', 'experiencia', $new_exp);

            $db->query(" 
                INSERT INTO `mybb_op_audit_entrenamientos` (`fid`, `nombre`, `puntos_estadistica`, `pr`) VALUES 
                ('$uid', '$nombre', '2', '$old_exp->$new_exp');
            ");

            $aviso_pendiente = array('tipo' => 'ok', 'texto' => '¡Entrenamiento completado! Ganaste ' . entreno_num($entreno['recompensa']) . ' puntos de experiencia (' . entreno_num($old_exp) . ' → ' . entreno_num($new_exp) . ').');
        } else {
            $aviso_pendiente = array('tipo' => 'err', 'texto' => 'Ese entrenamiento ya se había completado.');
        }
    }

    // "Culminar y volver a entrenar": tras cobrar, se inicia otro entrenamiento (con las mismas comprobaciones de $entrenar).
    if ($culminado && $reentrenar) {
        $entrenar = true;
    }
}

if ($entrenar) {
    $now = time();
    $chk_oficio  = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_oficios_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    $chk_entreno = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    if ($chk_oficio['n'] > 0 || $chk_entreno['n'] > 0) {
        $mensaje_redireccion = "Ya tienes un entrenamiento o lección de oficio activo. Espera a que termine antes de iniciar otro.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        exit;
    }
    $nombre = $db->escape_string($ficha['nombre']);
    $duracion = $horas * 3600; // en segundos
    // $duracion = '86400'; // en segundos
    $timestamp_end = $now + intval($duracion);
    $db->query("
        INSERT INTO `mybb_op_entrenamientos_usuarios` (`uid`,`nombre`,`timestamp_end`, `duracion`, `costo_pr`, `recompensa`) VALUES ('$uid','$nombre','$timestamp_end', '$duracion', '0', '$experiencia');
    ");
    if ($aviso_pendiente !== null && $reentrenar) {
        $aviso_pendiente['texto'] .= ' Ya empezaste otro entrenamiento.';
    }
    eval('$reload_script = $reload_js;');
}

// Tras culminar: se guarda el resultado y se redirige (un F5 no reenvia el formulario).
if ($aviso_pendiente !== null) {
    my_setcookie(ENTRENO_MSG_COOKIE, base64_encode(json_encode($aviso_pendiente)), 15, true);
    header('Location: entrenamiento.php');
    exit;
}

// Aviso de resultado (viene de la cookie que dejo culminar antes de redirigir).
$aviso_html = '';
if (!empty($mybb->cookies[ENTRENO_MSG_COOKIE])) {
    $aviso_cookie = json_decode(base64_decode($mybb->cookies[ENTRENO_MSG_COOKIE]), true);
    if (is_array($aviso_cookie) && isset($aviso_cookie['tipo'], $aviso_cookie['texto'])) {
        $aviso_html = entreno_aviso_html($aviso_cookie['tipo'], $aviso_cookie['texto']);
    }
    my_unsetcookie(ENTRENO_MSG_COOKIE);
}

if ($ficha_existe == true && $ficha_aprobada == true) {
    
    $query_entreno_usuario = $db->query("
        SELECT * FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid'
    ");
    $mision_en_curso = false;
    $mision_completa = false;
    $tiempo_left = 0;

    while ($m = $db->fetch_array($query_entreno_usuario)) {

        $efecto = 1;
        $time_now = time();
        $codigo_usuario = get_obj_from_query($db->query("
            SELECT * FROM mybb_op_codigos_usuarios WHERE uid='$uid' AND expiracion > $time_now
        "));

        $recompensa = $m['recompensa'];
        $recompensa_txt = entreno_num($recompensa);
        $entrenamiento_duracion = intval($m['duracion']); // <--- esto se saca de la base de
        $extra_time = $entrenamiento_duracion - ($entrenamiento_duracion * (1 / $efecto)); 
        $timestamp_end = intval($m['timestamp_end']);
        $tiempo_left = (($timestamp_end) - $extra_time) * 1000; // needed for template
        $hora_servidor = time() * 1000; // el contador del navegador se ajusta a esta hora
        $duracion_ms = intval(($entrenamiento_duracion / $efecto) * 1000); // duracion real, para el porcentaje de la barra

        if (time() > ($timestamp_end - $extra_time)) {
            $mision_completa = true;
        } else {
            $mision_en_curso = true;
        }
    
    }       

    if ($mision_en_curso) {
        // $recompensa = 10;
        eval("\$page = \"".$templates->get("op_entrenamiento_en_curso")."\";");
        output_page($page);
    } else {     

        if ($mision_completa) {
            eval("\$page = \"".$templates->get("op_entrenamiento_completo")."\";");
        } else {            
            eval("\$page = \"".$templates->get("op_entrenamiento")."\";");
        }
        output_page($page);
    }

} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

