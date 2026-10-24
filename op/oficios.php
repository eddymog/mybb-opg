<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'oficios.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

/** Multiplicador de velocidad que da el codigo activo del usuario (1 si no tiene). */
function oficios_efecto_codigo($db, $uid)
{
    $efecto = 1;
    $time_now = time();
    $codigo_usuario = get_obj_from_query($db->query("
        SELECT * FROM mybb_op_codigos_usuarios WHERE uid='$uid' AND expiracion > $time_now
    "));
    if ($codigo_usuario) {
        $codigo_admin = select_one_query_with_id('mybb_op_codigos_admin', 'codigo', $codigo_usuario['codigo']);
        $categoria = $codigo_admin['categoria'];
        if ($categoria == 'entrenamientoX2') {
            $efecto = 2;
        } else if ($categoria == 'entrenamientoX3') {
            $efecto = 3;
        } else if ($categoria == 'entrenamientoX1.2') {
            $efecto = 1.2;
        } else if ($categoria == 'entrenamientoX1.5') {
            $efecto = 1.5;
        }
    }
    return $efecto;
}

global $templates, $mybb;

define('OFICIOS_MSG_COOKIE', 'oficios_msg');

/** HTML del aviso de resultado (tipo 'ok' o 'err'); el texto se escapa aqui. */
function oficios_aviso_html($tipo, $texto)
{
    return '<div class="entreno-aviso entreno-aviso-' . ($tipo === 'ok' ? 'ok' : 'err') . '" role="status">'
        . htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8') . '</div>';
}

/** Numero para mostrar: sin ceros de mas (18.125, no 18.1250000). */
function oficios_num($n)
{
    return rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
}

$entrenar = $_POST["entrenar"];
$cancelar = $_POST["cancelar"];
$culminar = $_POST["culminar"];
$reentrenar = !empty($_POST["reentrenar"]); // "Culminar y seguir con otra leccion"
$oficioNumber = $_POST["oficio"];

// Proteccion CSRF: todo envio de este formulario lleva my_post_key.
if (($entrenar || $cancelar || $culminar) && !verify_post_check($mybb->get_input('my_post_key'), true)) {
    $mensaje_redireccion = "La sesión ha caducado. Recarga la página e inténtalo de nuevo.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}
$aviso_pendiente = null; // resultado de culminar: se muestra tras redirigir (cookie de un solo uso)

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$uid = $mybb->user['uid'];
$tecnica_en_curso = false;
$ficha = null;
$ficha_existe = false;
$ficha_aprobada = false;
$efecto_coste = 1;
$time_now = time();
$oficio1 = '';
$oficio2 = '';
$puntos_oficio = 0;

$has_sin_oficio = false; // D024
$has_estudioso = false; // V035
$has_polivalente = false; // V036
$has_erudito = false; // V028
$has_trabajador = false; 

$has_sin_oficio_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='D024'; "); 
$has_estudioso_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V035'; "); 
$has_polivalente_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V036'; "); 
$has_erudito_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V028'; "); 
$has_trabajador_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V052'; "); 

while ($q = $db->fetch_array($has_sin_oficio_query)) { $has_sin_oficio = true; }
while ($q = $db->fetch_array($has_estudioso_query)) { $has_estudioso = true; }
while ($q = $db->fetch_array($has_polivalente_query)) { $has_polivalente = true; }
while ($q = $db->fetch_array($has_erudito_query)) { $has_erudito = true; }
while ($q = $db->fetch_array($has_trabajador_query)) { $has_trabajador = true; }

$reload_js = "<script>window.location.href = window.location.href;</script>";

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
while ($f = $db->fetch_array($query_ficha)) {
    $ficha = $f;
    $ficha_aprobada = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
    $ficha_existe = true;
    $puntos_oficio = $f['puntos_oficio'];
    $nivel = $ficha['nivel'];
}


if ($has_sin_oficio) {
    $mensaje_redireccion = "Aquellos que poseen el defecto 'Sin Oficio' no tienen la capacidad de ganar puntos de oficio. Haber estudiao.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

$duracion_horas = 24.0;
$puntos_oficio_recompensa = 100 + (5 * intval($nivel));
$desglose = array('Base 100 + 5 × nivel ' . intval($nivel) . ' = ' . oficios_num($puntos_oficio_recompensa));

if ($ficha['raza'] == 'Kobito') { $puntos_oficio_recompensa = $puntos_oficio_recompensa * 1.1; $desglose[] = 'Kobito ×1.1'; }
if ($has_polivalente) { $puntos_oficio_recompensa = $puntos_oficio_recompensa + (5 * intval($nivel)); $desglose[] = 'Polivalente +' . (5 * intval($nivel)); }
if ($has_erudito) { $puntos_oficio_recompensa = $puntos_oficio_recompensa + (5 * intval($nivel)); $desglose[] = 'Erudito +' . (5 * intval($nivel)); }
if ($has_trabajador) { $puntos_oficio_recompensa = $puntos_oficio_recompensa * 1.5; $desglose[] = 'Trabajador ×1.5'; }
$desglose_html = htmlspecialchars(implode(' · ', $desglose) . ' = ' . oficios_num($puntos_oficio_recompensa) . ' puntos de oficio', ENT_QUOTES, 'UTF-8');

$duracion = $duracion_horas * 3600; // en segundos

if ($g_entrenamiento_en_curso || $g_entrenamiento_completo) {
    echo "<script>alert('¡Vaya! Parece que ya tienes un entrenamiento activo, así que tendrás que esperar a terminarlo antes de ejercer un oficio. ¡GANBARE!'); window.location.href = './../index.php';</script>";
    exit;
}

if ($cancelar) {
    $db->query("
        DELETE FROM mybb_op_oficios_usuarios WHERE uid='$uid'
    ");
    eval('$reload_script = $reload_js;');
}

if ($culminar) {

    $entreno = null;
    $culminado = false;

    $query_entreno_usuario = $db->query("
        SELECT * FROM mybb_op_oficios_usuarios WHERE uid='$uid'
    ");

    while ($q = $db->fetch_array($query_entreno_usuario)) {
        $entreno = $q;
    }

    // El servidor decide si se puede culminar: tiene que existir la leccion y haber terminado, con el mismo
    // calculo de tiempo que muestra la pagina (el codigo activo la acorta).
    // Se borra la fila ANTES de pagar: solo quien consigue borrarla cobra, asi un doble envio no paga dos veces.
    if (!$entreno) {
        $aviso_pendiente = array('tipo' => 'err', 'texto' => 'No tenías ninguna lección para completar.');
    } else {
        $efecto_culminar = oficios_efecto_codigo($db, $uid);
        $duracion_entreno = intval($entreno['duracion']);
        $fin_efectivo = intval($entreno['timestamp_end']) - ($duracion_entreno - ($duracion_entreno * (1 / $efecto_culminar)));
        if (time() <= $fin_efectivo) {
            $aviso_pendiente = array('tipo' => 'err', 'texto' => 'Tu lección aún no ha terminado.');
        } else {
            $db->query("DELETE FROM mybb_op_oficios_usuarios WHERE id='".intval($entreno['id'])."'");
            if ($db->affected_rows() > 0) {
                $culminado = true;

                $nombre = $db->escape_string($ficha['nombre']);
                $experienciaDeEntreno = $entreno['experiencia'];
                $old_exp = $ficha['puntos_oficio'];
                $new_exp = floatval($old_exp) + (floatval($experienciaDeEntreno));

                log_audit_currency($uid, $username, $uid, '[Entrenamiento][Puntos oficio]', 'puntos_oficio', $new_exp);

                $db->query(" 
                    INSERT INTO `mybb_op_audit_oficios` (`fid`, `nombre`, `oficio`, `experiencia`, `progreso`) VALUES 
                    ('$uid', '$nombre', 'oficio', '$experienciaDeEntreno', '$old_exp->$new_exp');
                ");

                $aviso_pendiente = array('tipo' => 'ok', 'texto' => '¡Lección completada! Ganaste ' . oficios_num($experienciaDeEntreno) . ' puntos de oficio (' . oficios_num($old_exp) . ' → ' . oficios_num($new_exp) . ').');
            } else {
                $aviso_pendiente = array('tipo' => 'err', 'texto' => 'Esa lección ya se había completado.');
            }
        }
    }

    // "Culminar y seguir con otra leccion": tras cobrar, se inicia otra (con las mismas comprobaciones de $entrenar).
    if ($culminado && $reentrenar) {
        $entrenar = true;
    }
}

if ($entrenar) {
    $now = time();
    $chk_entreno = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_entrenamientos_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    $chk_oficio  = $db->fetch_array($db->query("SELECT COUNT(*) AS n FROM mybb_op_oficios_usuarios WHERE uid='$uid' AND timestamp_end > $now"));
    if ($chk_entreno['n'] > 0 || $chk_oficio['n'] > 0) {
        $mensaje_redireccion = "Ya tienes un entrenamiento o lección de oficio activo. Espera a que termine antes de iniciar otro.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        exit;
    }
    $timestamp_end = $now + floatval($duracion);
    $db->query("
        INSERT INTO `mybb_op_oficios_usuarios` (`uid`,`nombre`,`oficio`,`timestamp_end`, `duracion`, `experiencia`) VALUES ('$uid','oficio','1','$timestamp_end', '$duracion', '$puntos_oficio_recompensa');
    ");
    if ($aviso_pendiente !== null && $reentrenar) {
        $aviso_pendiente['texto'] .= ' Ya empezaste otra lección.';
    }
    eval('$reload_script = $reload_js;');
}

// Tras culminar: se guarda el resultado y se redirige (un F5 no reenvia el formulario).
if ($aviso_pendiente !== null) {
    my_setcookie(OFICIOS_MSG_COOKIE, base64_encode(json_encode($aviso_pendiente)), 15, true);
    header('Location: oficios.php');
    exit;
}

// Aviso de resultado (viene de la cookie que dejo culminar antes de redirigir).
$aviso_html = '';
if (!empty($mybb->cookies[OFICIOS_MSG_COOKIE])) {
    $aviso_cookie = json_decode(base64_decode($mybb->cookies[OFICIOS_MSG_COOKIE]), true);
    if (is_array($aviso_cookie) && isset($aviso_cookie['tipo'], $aviso_cookie['texto'])) {
        $aviso_html = oficios_aviso_html($aviso_cookie['tipo'], $aviso_cookie['texto']);
    }
    my_unsetcookie(OFICIOS_MSG_COOKIE);
}

if ($ficha_existe == true && $ficha_aprobada == true) {
    
    $query_entreno_usuario2 = $db->query("
        SELECT * FROM mybb_op_oficios_usuarios WHERE uid='$uid'
    ");
    $oficio_en_curso = false;
    $oficio_completo = false;
    $tiempo_left = 0;

    while ($m = $db->fetch_array($query_entreno_usuario2)) {

        $efecto = oficios_efecto_codigo($db, $uid);
        $time_now = time();

        $experienciaActual = $ficha['puntos_oficio'];
        $recompensa = $m['experiencia'];
        $oficio_duracion = intval($m['duracion']); // <--- esto se saca de la base de
        $extra_time = $oficio_duracion - ($oficio_duracion * (1 / $efecto)); 
        $timestamp_end = intval($m['timestamp_end']);
        $tiempo_left = (($timestamp_end) - $extra_time) * 1000; // needed for template
        $hora_servidor = time() * 1000; // el contador del navegador se ajusta a esta hora
        $duracion_ms = intval(($oficio_duracion / $efecto) * 1000); // duracion real, para el porcentaje de la barra

        if (time() > ($timestamp_end - $extra_time)) {
            $oficio_completo = true;
        } else {
            $oficio_en_curso = true;
        }
    
    }       

    if ($oficio_en_curso) {
        eval("\$page = \"".$templates->get("op_oficios_en_curso")."\";");
        output_page($page);
    } else {     
        if ($oficio_completo) {
            eval("\$page = \"".$templates->get("op_oficios_completo")."\";");
        } else {            
            eval("\$page = \"".$templates->get("op_oficios")."\";");
        }
        output_page($page);
    }

} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}

