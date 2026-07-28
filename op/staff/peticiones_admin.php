<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticiones_admin.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$accion = $mybb->get_input('accion');
$peti_id = $mybb->get_input('peti_id');

$reload_js = "<script>window.location.href = window.location.pathname;</script>";

$resulto_input = $mybb->get_input('resuelto'); 
$resuelto = '0';

$mods = [
'Sin asignar',
'Satou',
'Teruyoshi',
'Aranagi',
'Juuken',
'Oddy',
'Joker',
'Ubben',
'Darrow',
'Nefta',
'Osten',
'Hazel',
'Gretta',
'Sirius',
'Cuando vea a alguien más le meto'
];

if ($resulto_input == '1') {
    $resuelto = '1';
}

// if ($accion == 'resolver' && $peti_id) {

//     $db->query(" 
//         UPDATE `mybb_op_peticiones` SET `resuelto`=1, `mod_uid`='$uid', `mod_nombre`='$username' WHERE id='$peti_id'
//     ");

//     eval('$reload_script = $reload_js;');
// } else if ($action == 'borrar' && $peti_id) {
//     $db->query("
//         DELETE FROM mybb_op_peticiones WHERE uid='$peti_id';
//     ");
//     eval('$reload_script = $reload_js;');
// }

if ($accion == 'resolver' && $peti_id) {
    $db->query("
        UPDATE `mybb_op_peticiones`
        SET `resuelto`=1, `mod_uid`='{$uid}', `mod_nombre`='{$username}'
        WHERE id='{$peti_id}'
    ");
    header('Location: /op/staff/peticiones_admin.php');
    exit;
} else if ($accion == 'borrar' && $peti_id) { // <- corrige $action -> $accion
    // Borra por `id` (PK única), NO por `uid`: las solicitudes de afiliación
    // entran con uid=0, así que borrar por uid arrasaría con todas de una.
    $peti_id_int = (int) $peti_id;
    $db->query("
        DELETE FROM mybb_op_peticiones
        WHERE id='{$peti_id_int}'
        LIMIT 1
    ");
    header('Location: /op/staff/peticiones_admin.php');
    exit;
}

// Normalizador robusto (fuera o arriba de print_peticion)
if (!function_exists('norm_name')) {
    function norm_name($s) {
        $s = (string)$s;
        // Reemplaza NBSP y ZWSP por espacio normal
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $s);
        // Colapsa espacios
        $s = preg_replace('/\s+/u', ' ', $s);
        // trim y minúsculas
        $s = trim($s);
        if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8'); else $s = strtolower($s);
        return $s;
    }
}


// --- Asignar moderador (atendidoPor) ---
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'asignar_mod') {
    header('Content-Type: application/json; charset=utf-8');

    $peti_id    = (int)$mybb->get_input('peti_id', MyBB::INPUT_INT);
    $nombre_mod = trim($mybb->get_input('nombre_mod', MyBB::INPUT_STRING));

    if ($peti_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'peti_id inválido']); exit;
    }

    // Normaliza "Sin asignar" a vacío
    if ($nombre_mod === 'Sin asignar' || $nombre_mod === '—') {
        $nombre_mod = '';
    }

    $nombre_mod_esc = $db->escape_string($nombre_mod);

    // Ejecuta UPDATE
    $sql = "
        UPDATE `mybb_op_peticiones`
        SET `atendidoPor` = '{$nombre_mod_esc}'
        WHERE `id` = {$peti_id}
        LIMIT 1
    ";
    $db->query($sql);

    // Depuración: filas afectadas y posibles errores
    $affected = (int)$db->affected_rows();
    $error    = method_exists($db, 'error_number') && $db->error_number() ? $db->error() : null;

    echo json_encode([
        'success'     => $error ? false : true,
        'atendidoPor' => $nombre_mod,
        'peti_id'     => $peti_id,
        'affected'    => $affected,
        'sql'         => $sql,
        'error'       => $error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Guardar notas de moderación (notasMod) ---
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'guardar_notas_mod') {
    header('Content-Type: application/json; charset=utf-8');

    // Solo moderadores / staff pueden editar notas
    if (!is_mod($uid) && !is_staff($uid)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // CSRF. En el JS se envía window.MYBB_POST_KEY
    $post_key = $mybb->get_input('my_post_key', MyBB::INPUT_STRING);
    if (!verify_post_check($post_key, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $peti_id = (int)$mybb->get_input('peti_id', MyBB::INPUT_INT);
    // Permite texto largo; sanitiza servidor
    $notas   = trim((string)$mybb->get_input('notas', MyBB::INPUT_STRING));

    if ($peti_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'peti_id inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Limita longitud (5000 chars max)
    if (mb_strlen($notas, 'UTF-8') > 5000) {
        $notas = mb_substr($notas, 0, 5000, 'UTF-8');
    }

    $notas_esc = $db->escape_string($notas);

    $sql = "
        UPDATE `mybb_op_peticiones`
        SET `notasMod` = '{$notas_esc}'
        WHERE `id` = {$peti_id}
        LIMIT 1
    ";
    $db->query($sql);

    $affected = (int)$db->affected_rows();
    $error    = method_exists($db, 'error_number') && $db->error_number() ? $db->error() : null;

    echo json_encode([
        'success'  => $error ? false : true,
        'peti_id'  => $peti_id,
        'affected' => $affected,
        'error'    => $error,
        // Devuelve lo que quedó guardado
        'notas'    => $notas
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_mod($uid) || is_staff($uid) || is_user($uid)) { 
    $peticiones_li = "";

    // $borrar_a = "$url_page?accion=borrar&peti_id=$pid";

    function print_peticion($nombre_categoria, $categoria, $uid, $res) {
        global $db, $mods;
        $query_peticion = $db->query("
            SELECT * FROM mybb_op_peticiones
            WHERE resuelto='$res' AND categoria='$categoria'
            ORDER BY id DESC
            LIMIT 100;
        ");

        $peticiones_li = "";
        while ($q = $db->fetch_array($query_peticion)) {
            $pid = $q['id'];
            $categoria = $q['categoria'];
            $resumen = $q['resumen'];
            $descripcion =  nl2br($q['descripcion']);
            $url = $q['url'];
            $nombre = $q['nombre'];
            $u_uid = $q['uid'];
            $mod_nombre = $q['mod_nombre'];
            $enviado = $q['enviado'];
            $categoria2 = "";
            $givenDate = new DateTime($enviado);
            $currentDate = new DateTime();
            $interval = $currentDate->diff($givenDate);
            $fecha = "Hace " . $interval->days . " días.";
            $atendidoPor = $q['atendidoPor'];
            
            $peticiones_li .= "<li>";
            $peticiones_li .= "[<a target='_blank' href='/op/ficha.php?uid=$u_uid'>$nombre - $u_uid</a>] <br> <strong>Resumen</strong>: $resumen <br> <strong>Descripción</strong>: $descripcion <br> <strong>URL</strong>: <a target='_blank' href='$url'>$url</a><br> <strong>Fecha</strong>: $enviado - $fecha <br>";
            
            // Desplegable de texto fijo (con normalización)
            $atendidoPor = isset($q['atendidoPor']) ? (string)$q['atendidoPor'] : '';
            $normAtendido = norm_name($atendidoPor);

            $select_id = 'sel_mod_' . (int)$pid;

            $notasMod = isset($q['notasMod']) ? (string)$q['notasMod'] : '';
            $notes_id = 'notas_' . (int)$pid;

            // Texto visible
            $peticiones_li .= "<strong>Atendido por</strong>: <span class='atendido-por'>"
                        . htmlspecialchars($atendidoPor !== '' ? $atendidoPor : 'Sin asignar', ENT_QUOTES, 'UTF-8')
                        . "</span><br>";

            // Select con ID único
            $mods_local = array_map('trim', $mods);
            $peticiones_li .= "<label><strong>Asignar a:</strong> ";
            $peticiones_li .= "<select id='{$select_id}' class='sel-mod' data-pid='{$pid}'>";

            foreach ($mods_local as $name) {
                $normOpt = norm_name($name);
                $isMatch = ($normOpt === $normAtendido) || ($normAtendido === '' && $normOpt === norm_name('Sin asignar'));
                $sel = $isMatch ? ' selected="selected"' : '';
                $name_esc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                $peticiones_li .= "<option value=\"{$name_esc}\"{$sel}>{$name_esc}</option>";
            }
            $peticiones_li .= "</select></label> ";

            // Botón que apunta explícitamente al select
            $peticiones_li .= "<button type='button' class='save-mod' data-pid='{$pid}' data-target-id='{$select_id}' style='margin-left:8px'>Guardar</button><br>";

            // Campo de notas para moderadores
            $peticiones_li .= "
                <div class='notas-mod' style='margin-top:8px'>
                    <label for='{$notes_id}'><strong>Notas de moderación</strong></label><br>
                    <textarea id='{$notes_id}' class='notas-text' data-pid='{$pid}'
                        rows='3' style='width:100%;max-width:800px'>"
                        . htmlspecialchars($notasMod, ENT_QUOTES, 'UTF-8') .
                    "</textarea>
                    <button type='button' class='save-notas' data-pid='{$pid}' data-target-id='{$notes_id}' style='margin-top:6px'>Guardar notas</button>
                </div>
            ";

            if ($mod_nombre != '') {
                $peticiones_li .= "<strong>Moderador que ha resuelto</strong>: $mod_nombre<br>";
            }


            if (is_mod($uid) || is_staff($uid)) {
                $url_page = "/op/staff/peticiones_admin.php";
                $resolver_a = "$url_page?accion=resolver&peti_id=$pid";
                $peticiones_li .= "<span><a href='$resolver_a' >Resolver</a></span>";
            }
            // $peticiones_li .= "<span><a href='$borrar_a' target='_blank'>Borrar</a></span>"; // porque me gustan los botones de borrar :P
            $peticiones_li .= "</li><br>";
        
        }

        if ($peticiones_li != "") {
            return "<h3>$nombre_categoria</h3>" . $peticiones_li;
        } else {
            return "";
        }
    }

    $peticiones_li .= print_peticion('Ajustes de Ficha y Recursos', 'ficha', $uid, $resuelto);
    $peticiones_li .= print_peticion('Petición de Narración', 'tema', $uid, $resuelto);
    $peticiones_li .= print_peticion('Moderación de Combate', 'combate', $uid, $resuelto);
    $peticiones_li .= print_peticion('Técnicas, Akumas y Estilos', 'tecnica', $uid, $resuelto);
    $peticiones_li .= print_peticion('Otras Moderaciones', 'otros', $uid, $resuelto);
    $peticiones_li .= print_peticion('Solicitudes de Afiliación', 'afiliados', $uid, $resuelto);
    // $peticiones_li .= print_peticion('Errores de Programación', 'programacion', $uid);

    $csrf_script = "<script>window.MYBB_POST_KEY = '".htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8')."';</script>";

    eval("\$page = \"".$templates->get("staff_peticiones_mod")."\";");
    output_page($page);

} else {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}
