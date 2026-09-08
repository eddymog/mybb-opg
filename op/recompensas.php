<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'recompensas.php');
require_once "./../global.php";
require "./../inc/config.php";

global $templates, $mybb;

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$recompensa_accepted = $_POST["rec_ready"];
$reload_js = "<script>window.location.href = window.location.href;</script>";
$reload_script = '';

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


// Usergroups con mínimo 1 post garantizado en la racha de recompensas.
// Añade o elimina IDs de grupo según necesites.
$recompensas_min_post_grupos = [
    3,   // Super Moderadores
    4,   // Administradores
    6,   // Moderadores
    14,  // Staff
    16,  // Programadores
    15,  // Narradores
];

$should_delete_recompensa = false;
$ficha_existe = false;
$aprobada_por = false;
$should_accept = true;
$two_days = 2 * 24 * 3600;
$time_now = time();
$next_two_days = time() + $two_days;
$last_two_days = time() - $two_days;
$time_to_accept = 0;
$days_count = 0;
$days_season_count = 0;

function tiene_min_post_garantizado() {
    global $mybb, $recompensas_min_post_grupos;

    // Mientras el plugin de cierre definitivo del foro esté activo, el foro ya no
    // admite posts nuevos, así que se garantiza el mínimo de post a todo el mundo
    // para que cualquiera pueda reclamar su recompensa antes del cierre.
    if (function_exists('cierre_definitivo_foro_activo') && cierre_definitivo_foro_activo()) {
        return true;
    }

    $user_group   = (int)$mybb->user['usergroup'];
    $extra_groups = array_filter(array_map('intval', explode(',', $mybb->user['additionalgroups'])));
    $all_groups   = array_merge([$user_group], $extra_groups);
    return count(array_intersect($all_groups, $recompensas_min_post_grupos)) > 0;
}

function darObjeto($objeto_id) {
    global $db, $uid, $username;
    $cantidadActual = '0';
    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objeto_id'");
    while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }
    $cantidadNueva = intval($cantidadActual) + 1;

    log_audit($uid, $username, '[Recompensa]', "Cofre $objeto_id: $cantidadActual->$cantidadNueva.");
    if ($has_objeto) {
       
        $db->query(" 
            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$objeto_id' AND uid='$uid'
        ");
    } else {
        $db->query(" 
            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('$objeto_id', '$uid', '1');
        ");
    }
}

// Aseguramos variable para el template (evita notices/errores en PHP 8)
if (!isset($log_var)) { $log_var = ''; }

// ACCION MASIVA - REPARTO DE 2x CFR004 A TODAS LAS UIDs (SIN TRANSACCION)
if (
    $mybb->request_method === 'post' &&
    (string)$mybb->user['uid'] === '304' &&
    (int)$mybb->get_input('mass_cfr004') === 1
) {
    // CSRF
    verify_post_check($mybb->get_input('my_post_key'));

    $total_afectados = 0;

    // Guardamos el contexto original del usuario actual
    $oldUid = $uid;
    $oldUsername = $username;

    // Todas las UIDs (si quieres sólo con ficha, cambia el SELECT)
    $resUsers = $db->query("
        SELECT uid, username
        FROM mybb_users
        WHERE uid > 0
    ");

    while ($usr = $db->fetch_array($resUsers)) {
        // Reutilizamos darObjeto cambiando temporalmente $uid / $username
        $uid = (string)$usr['uid'];
        $username = $usr['username'];

        // 2 cofres CFR004 por usuario
        darObjeto('CFR004');
        darObjeto('CFR004');

        $total_afectados++;
    }

    // Restaurar contexto original
    $uid = $oldUid;
    $username = $oldUsername;

    if (function_exists('log_audit')) {
        log_audit($oldUid, $oldUsername, '[Recompensa][Masivo]', "Entregados 2x CFR004 a {$total_afectados} usuarios.");
    }

    // Para alert({$log_var}) en el template:
    $log_var = "'¡Listo! Se entregaron 2 Cofres Cobrizos (CFR004) a {$total_afectados} usuarios.'";
}
// FIN ACCION MASIVA

/* CHECK IF THERE IS A RECOMPENSA THAT EXISTS */
$query_recompensa_actual2 = $db->query("
    SELECT * FROM mybb_op_recompensas_usuarios WHERE uid='$uid'
");

while ($q = $db->fetch_array($query_recompensa_actual2)) {
    $days_count = $q['dia']; 
    $days_season_count = $q['season']; 
    // modify last two days based on last time reward was accepted
    $last_two_days = $q['tiempo'] - $two_days;
    // enough time to accept recompensa
    $should_accept = time() > $q['tiempo'];
    // too much time passed to accept recompensa
    $claimed_after_96_hours = time() > ($q['tiempo'] + $two_days);
    $claimed_after_48_hours = time() > $q['tiempo'];
    // time left to accept before accepting again (so not just right two posts after)
    $time_to_accept = $q['tiempo'] - time();
    // print_r($q['dia']);
}


$recompensas_racha_maxima = 0;
$recompensas_temporada_maxima = 0;

$recompensas_maxima_query = $db->query("
    SELECT a.*
    FROM mybb_op_audit_recompensas a
    INNER JOIN (
        SELECT uid, MAX(dia) dia
        FROM mybb_op_audit_recompensas
        GROUP BY uid
    ) b ON a.uid = b.uid AND a.dia = b.dia
    WHERE a.uid=$uid
    ORDER BY dia DESC
    LIMIT 10
");

$recompensas_temporada_query = $db->query("
SELECT a.*
    FROM mybb_op_audit_recompensas a
    INNER JOIN (
        SELECT uid, MAX(season) season
        FROM mybb_op_audit_recompensas
        GROUP BY uid
    ) b ON a.uid = b.uid AND a.season = b.season
    WHERE a.uid=$uid
    ORDER BY dia DESC
    LIMIT 10;
");

/*$recompensas_temporada_query = $db->query("
SELECT a.*
    FROM mybb_op_audit_recompensas a
    INNER JOIN (
        SELECT uid, MAX(id) season
        FROM mybb_op_audit_recompensas
        GROUP BY uid
    ) b ON a.uid = b.uid AND a.id = b.season
    WHERE a.uid=$uid
    ORDER BY dia DESC
    LIMIT 10;
");*/

while ($q = $db->fetch_array($recompensas_maxima_query)) {
    $recompensas_racha_maxima = $q['dia'];
}

while ($q = $db->fetch_array($recompensas_temporada_query)) {
    $recompensas_temporada_maxima = $q['season'];
}

// recompensa was accepted after time has passed
if ($recompensa_accepted == 'true' && $should_accept) {

    $query_ficha = $db->query("
        SELECT * FROM mybb_op_fichas WHERE fid='$uid'
    ");
    while ($f = $db->fetch_array($query_ficha)) {
        $f_var = $f;
    }
    $query_usuario = $db->query("
        SELECT * FROM mybb_users WHERE uid='$uid'
    ");
    while ($u = $db->fetch_array($query_usuario)) {
        $u_var = $u;
    }

    $puntos_exp = $u_var['newpoints'];
    $nombre = $f_var['nombre'];
    $berries = $f_var['berries'];
    $pe = $f_var['puntos_estadistica'];
    $nika = $f_var['nika'];
    $kuros = $f_var['kuro'];

    $recompensa_items = '';
    $log = '';

    if ($days_count == 0 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '10 Puntos de Experiencia';
        $new_exp = floatval($puntos_exp) + 10;
        $log = "Experiencia: $puntos_exp->$new_exp";
        // $db->query(" 
        //     UPDATE `mybb_users` SET newpoints='$new_exp' WHERE `uid`='$uid';
        // ");

        log_audit_currency($uid, $username, $uid, '[Recompensa][Experiencia]', 'experiencia', $new_exp);
    } else if ($days_count == 1 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '100,000 Berries';
        $new_berries = intval($berries) + 100000;
        $log = "Berries: $berries->$new_berries";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET berries='$new_berries' WHERE `fid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Berries]', 'berries', $new_berries);
    } else if ($days_count == 2 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '1 Nika';
        $new_nika = intval($nika) + 1;
        $log = "Nika: $nika->$new_nika";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET nika='$new_nika' WHERE `fid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Nikas]', 'nikas', $new_nika);
    } else if ($days_count == 3 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '100,000 Berries, 1 Nika y 10 Puntos de Experiencia';
        $new_berries = intval($berries) + 100000;
        $new_nika = intval($nika) + 1;
        $new_exp = floatval($puntos_exp) + 10;

        $log = "Berries: $berries & Nika: $nika->$new_nika & PE: $puntos_exp->$new_exp";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET nika='$new_nika', berries='$new_berries' WHERE `fid`='$uid';
        // ");
        // $db->query(" 
        //     UPDATE `mybb_users` SET newpoints='$new_exp' WHERE `uid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Experiencia]', 'experiencia', $new_exp);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Berries]', 'berries', $new_berries);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Nikas]', 'nikas', $new_nika);
    } else if ($days_count == 4 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '150,000 Berries, 1 Nika y 15 Puntos de Experiencia';
        $new_berries = intval($berries) + 150000;
        $new_nika = intval($nika) + 1;
        $new_exp = floatval($puntos_exp) + 15;

        $log = "Berries: $berries & Nika: $nika->$new_nika & PE: $puntos_exp->$new_exp";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET nika='$new_nika', berries='$new_berries' WHERE `fid`='$uid';
        // ");
        // $db->query(" 
        //     UPDATE `mybb_users` SET newpoints='$new_exp' WHERE `uid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Experiencia]', 'experiencia', $new_exp);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Berries]', 'berries', $new_berries);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Nikas]', 'nikas', $new_nika);
    } else if ($days_count >= 5 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '200,000 Berries, 1 Nika y 20 Puntos de Experiencia';
        $new_berries = intval($berries) + 200000;
        $new_nika = intval($nika) + 1;
        $new_exp = floatval($puntos_exp) + 20;

        $log = "Berries: $berries & Nika: $nika->$new_nika & PE: $puntos_exp->$new_exp";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET nika='$new_nika', berries='$new_berries' WHERE `fid`='$uid';
        // ");
        // $db->query(" 
        //     UPDATE `mybb_users` SET newpoints='$new_exp' WHERE `uid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Experiencia]', 'experiencia', $new_exp);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Berries]', 'berries', $new_berries);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Nikas]', 'nikas', $new_nika);
    } else if ($recompensas_temporada_maxima > 41 && (($recompensas_temporada_maxima + 1) % 5) == 0) {
        $recompensa_items = '800,000 Berries, 4 Nika y 50 Puntos de Experiencia';
        $new_berries = intval($berries) + 800000;
        $new_nika = intval($nika) + 4;
        $new_exp = floatval($puntos_exp) + 50;

        $log = "Berries: $berries & Nika: $nika->$new_nika & PE: $puntos_exp->$new_exp";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET nika='$new_nika', berries='$new_berries' WHERE `fid`='$uid';
        // ");
        // $db->query(" 
        //     UPDATE `mybb_users` SET newpoints='$new_exp' WHERE `uid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Experiencia]', 'experiencia', $new_exp);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Berries]', 'berries', $new_berries);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Nikas]', 'nikas', $new_nika);
    } else if ($recompensas_temporada_maxima >= 40) {
        $recompensa_items = '400,000 Berries, 2 Nika y 30 Puntos de Experiencia';
        $new_berries = intval($berries) + 400000;
        $new_nika = intval($nika) + 2;
        $new_exp = floatval($puntos_exp) + 30;

        $log = "Berries: $berries & Nika: $nika->$new_nika & PE: $puntos_exp->$new_exp";
        // $db->query(" 
        //     UPDATE `mybb_op_fichas` SET nika='$new_nika', berries='$new_berries' WHERE `fid`='$uid';
        // ");
        // $db->query(" 
        //     UPDATE `mybb_users` SET newpoints='$new_exp' WHERE `uid`='$uid';
        // ");
        log_audit_currency($uid, $username, $uid, '[Recompensa][Experiencia]', 'experiencia', $new_exp);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Berries]', 'berries', $new_berries);
        log_audit_currency($uid, $username, $uid, '[Recompensa][Nikas]', 'nikas', $new_nika);
    }
    
    $days_count = intval($days_count) + 1;
    $days_season_count = intval($recompensas_temporada_maxima) + 1;

    if ($days_season_count == 5) {
        darObjeto('CFR002'); darObjeto('CFR001');
    } else if ($days_season_count == 10) {
        darObjeto('CFR003'); darObjeto('CFR001');
    } else if ($days_season_count == 15) {
        darObjeto('CFR004'); darObjeto('CFR001'); 
    } else if ($days_season_count == 20) {
        darObjeto('CFR004'); darObjeto('CFR004'); darObjeto('CFR001');
    } else if ($days_season_count == 25) {
        darObjeto('CFR005'); darObjeto('CFR001'); 
    } else if ($days_season_count == 30) {
        darObjeto('CFR005'); darObjeto('CFR005'); darObjeto('CFR001');
    } else if ($days_season_count == 35) {
        darObjeto('CFR006'); darObjeto('CFR001');
    } else if ($days_season_count == 40) {
        darObjeto('CFR006'); darObjeto('CFR006'); darObjeto('CFR001');
    }
    
    $db->query("
        DELETE FROM mybb_op_recompensas_usuarios WHERE uid='$uid'
    ");

    $db->query(" 
        INSERT INTO `mybb_op_recompensas_usuarios`(`uid`, `nombre`, `dia`, `season`, `tiempo`) VALUES ('$uid','$nombre','$days_count','$days_season_count','$next_two_days')
    ");

    $complete_log = "$nombre ($uid). Has ganado $recompensa_items en esta ronda. $log.";

    $db->query(" 
        INSERT INTO `mybb_op_audit_recompensas`(`tiempo_completado`, `tiempo_nuevo`, `dia`, `season`, `uid`, `nombre`, `audit`) VALUES ($time_now, $next_two_days, $days_count, '$days_season_count', '$uid','$nombre','$complete_log')
    ");

    eval('$log_var = $complete_log;');
    eval('$reload_script = $reload_js;');
} else if ($recompensa_accepted == 'true') {
    // Intento fallido (aún no es hora de reclamar). No recargar para evitar loops.
    // $complete_log = "Hola, tramposill@. Ser pirata off-rol no es lo mismo que ser pirata on-rol. ¡Cuidadito que te coge la Cipher Pol, eh!";
    eval('$log_var = $complete_log;');
}

/* Check if ficha exists */
$query_ficha = $db->query("
    SELECT * FROM mybb_op_fichas WHERE fid='$uid'
");

$nombre = '';

while ($f = $db->fetch_array($query_ficha)) {
    $aprobada_por = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true);
    $ficha_existe = true;
    $nombre = $f['nombre'];
}

/* Render page */
if ($ficha_existe == true) {

    $num_posts = 0;
    $time_left = '';

    $total_recompensas_query = $db->query("SELECT COUNT(*) as total FROM `mybb_op_audit_recompensas` WHERE uid='$uid'");
    while ($q = $db->fetch_array($total_recompensas_query)) { $recompensas_reclamadas = $q['total']; }

    // last_two_days could be last 48 hours posts for the last time that reward was accepted
    // or last 48 hours that there were posts when reward has not been accepted
    $query_posts = $db->query("
        SELECT p.dateline as post_date FROM mybb_posts as p 
        INNER JOIN mybb_threads as t ON p.tid = t.tid 
        INNER JOIN mybb_forums as f ON t.fid = f.fid 
        WHERE p.dateline > $last_two_days
        AND (f.parentlist LIKE '10,%' OR f.parentlist LIKE '%246,%' OR t.tid = '3544')
        AND p.uid = '$uid'
        AND p.visible = 1
        ORDER BY p.dateline ASC
    ");

    $dates_arr = array();

    while ($q = $db->fetch_array($query_posts)) {
        $post_date = $q['post_date'];
        // 48 hours left after this post
        $time_left = $two_days - (time() - $q['post_date']);
        array_push($dates_arr, $time_left);
    }

    $num_posts = count($dates_arr);

    if (tiene_min_post_garantizado() && $num_posts == 0) {
        $num_posts = 1;
    }

    // recompensa is expired either because 96 hours after claim time is expired
    // or because after 48 hours it was claimed, there was less than two posts.
    if ($claimed_after_96_hours || ($num_posts < 1 && $claimed_after_48_hours)) {
        $db->query("
            DELETE FROM mybb_op_recompensas_usuarios WHERE uid='$uid'
        ");

        $last_two_days = time() - $two_days;

        $query_posts = $db->query("
            SELECT p.dateline as post_date FROM mybb_posts as p 
            INNER JOIN mybb_threads as t ON p.tid = t.tid 
            INNER JOIN mybb_forums as f ON t.fid = f.fid 
            WHERE p.dateline > $last_two_days
            AND (f.parentlist LIKE '10,%' OR f.parentlist LIKE '%246,%')
            AND p.uid = '$uid'
            AND p.visible = 1
            ORDER BY p.dateline ASC
        ");

        $dates_arr = array();

        while ($q = $db->fetch_array($query_posts)) {
            $post_date = $q['post_date'];
            // 48 hours left after this post
            $time_left = $two_days - (time() - $q['post_date']);
            array_push($dates_arr, $time_left);
        }

        $num_posts = count($dates_arr);
        if (tiene_min_post_garantizado() && $num_posts == 0) {
            $num_posts = 1;
        }

    }

    if ($num_posts >= 1 && $should_accept && $days_count > 0) {
        $time_left = $two_days + $time_to_accept;
    } else if ($num_posts >= 1 && $should_accept) {
        // if no rewards yet, time left is 48 hours before second to last post
        // $dates_arr puede estar vacío si $num_posts fue forzado a 1 por grupo privilegiado.
        // En ese caso usamos $two_days para que el countdown no llegue a 0 inmediatamente.
        $time_left = !empty($dates_arr) ? $dates_arr[count($dates_arr) - 1] : $two_days;
    } else if ($num_posts >= 1 && !$should_accept) {
        // reward has been claimed, and it has to wait 48 hours after it was claimed
        $time_left = $time_to_accept;
    } else if ($num_posts < 1 && $days_count > 0) {
        // time to write the two posts before it can be claimed again or it expires
        $time_left = $time_to_accept;
    } 

    $recompensa_items = '';

    if ($days_count == 0 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '10 Puntos de Experiencia';
    } else if ($days_count == 1 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '100,000 Berries';
    } else if ($days_count == 2 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '1 Nika';
    } else if ($days_count == 3 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '100,000 Berries, 1 Nika y 10 Puntos de Experiencia';
    } else if ($days_count == 4 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '150,000 Berries, 1 Nika y 15 Puntos de Experiencia';
    } else if ($days_count >= 5 && $recompensas_temporada_maxima < 40) {
        $recompensa_items = '200,000 Berries, 1 Nika y 20 Puntos de Experiencia';
    } else if ($recompensas_temporada_maxima > 41 && (($recompensas_temporada_maxima + 1) % 5) == 0) {
        $recompensa_items = '800,000 Berries, 4 Nika y 50 Puntos de Experiencia';
    } else if ($recompensas_temporada_maxima >= 40) {
        $recompensa_items = '400,000 Berries, 2 Nika y 30 Puntos de Experiencia';
    }

    // if ($days_count == 0 && $recompensas_temporada_maxima < 40) {
    //     $recompensa_items = '10 Kuros';
    // } else if ($days_count == 1 && $recompensas_temporada_maxima < 40) {
    //     $recompensa_items = '100,000 Berries';
    // } else if ($days_count == 2 && $recompensas_temporada_maxima < 40) {
    //     $recompensa_items = '1 Nika';
    // } else if ($days_count == 3 && $recompensas_temporada_maxima < 40) {
    //     $recompensa_items = '100,000 Berries, 1 Nika y 10 Kuros';
    // } else if ($days_count == 4 && $recompensas_temporada_maxima < 40) {
    //     $recompensa_items = '150,000 Berries, 1 Nika y 15 Kuros';
    // } else if ($days_count >= 5 && $recompensas_temporada_maxima < 40) {
    //     $recompensa_items = '200,000 Berries, 1 Nika y 20 Kuros';
    // } else if ($recompensas_temporada_maxima > 41 && (($recompensas_temporada_maxima + 1) % 5) == 0) {
    //     $recompensa_items = '800,000 Berries, 4 Nika y 50 Kuros';
    // } else if ($recompensas_temporada_maxima >= 40) {
    //     $recompensa_items = '400,000 Berries, 2 Nika y 30 Kuros';
    // }

    eval("\$page = \"".$templates->get("op_recompensas")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "Este usuario no ha creado su ficha aún.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}
