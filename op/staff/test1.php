<?php
/**
 * MyBB 1.8
 * Sistema de Pago de Recompensas por Aventuras
 */

// Habilitar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'test1.php');

// REGION CONSTANTES DE RECOMPENSAS POR TIER

    // Recompensas para narradores aprendiz
    define('RECOMPENSAS_NARRADOR_APRENDIZ', array(
        1 => array('experiencia' => 50, 'nikas' => 6, 'cofres' => array('objeto_id' => "CFN002", 'cantidad' => 1)),
        2 => array('experiencia' => 75, 'nikas' => 12, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 1)),
        3 => array('experiencia' => 120, 'nikas' => 22, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 2)),
        4 => array('experiencia' => 150, 'nikas' => 34, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 1)),
        5 => array('experiencia' => 200, 'nikas' => 45, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 2)),
        6 => array('experiencia' => 250, 'nikas' => 56, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 1)),
        7 => array('experiencia' => 300, 'nikas' => 67, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 2)),
        8 => array('experiencia' => 400, 'nikas' => 88, 'cofres' => array('objeto_id' => "CFN006", 'cantidad' => 1)),
        9 => array('experiencia' => 560, 'nikas' => 109, 'cofres' => array('objeto_id' => "CFN007", 'cantidad' => 1)),
        10 => array('experiencia' => 730, 'nikas' => 160, 'cofres' => array('objeto_id' => "CFN008", 'cantidad' => 1))
    ));

    // Recompensas para narradores estudioso
    define('RECOMPENSAS_NARRADOR_ESTUDIOSO', array(
        1 => array('experiencia' => 65, 'nikas' => 7, 'cofres' => array('objeto_id' => "CFN002", 'cantidad' => 1)),
        2 => array('experiencia' => 85, 'nikas' => 13, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 1)),
        3 => array('experiencia' => 140, 'nikas' => 24, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 2)),
        4 => array('experiencia' => 170, 'nikas' => 35, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 1)),
        5 => array('experiencia' => 220, 'nikas' => 46, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 2)),
        6 => array('experiencia' => 265, 'nikas' => 57, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 1)),
        7 => array('experiencia' => 320, 'nikas' => 68, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 2)),
        8 => array('experiencia' => 400, 'nikas' => 89, 'cofres' => array('objeto_id' => "CFN006", 'cantidad' => 1)),
        9 => array('experiencia' => 580, 'nikas' => 110, 'cofres' => array('objeto_id' => "CFN007", 'cantidad' => 1)),
        10 => array('experiencia' => 750, 'nikas' => 161, 'cofres' => array('objeto_id' => "CFN008", 'cantidad' => 1))
    ));

    // Recompensas para narradores ilustre
    define('RECOMPENSAS_NARRADOR_ILUSTRE', array(
        1 => array('experiencia' => 80, 'nikas' => 8, 'cofres' => array('objeto_id' => "CFN002", 'cantidad' => 1)),
        2 => array('experiencia' => 100, 'nikas' => 14, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 1)),
        3 => array('experiencia' => 155, 'nikas' => 25, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 2)),
        4 => array('experiencia' => 180, 'nikas' => 36, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 1)),
        5 => array('experiencia' => 235, 'nikas' => 47, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 2)),
        6 => array('experiencia' => 280, 'nikas' => 58, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 1)),
        7 => array('experiencia' => 350, 'nikas' => 69, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 2)),
        8 => array('experiencia' => 410, 'nikas' => 90, 'cofres' => array('objeto_id' => "CFN006", 'cantidad' => 1)),
        9 => array('experiencia' => 600, 'nikas' => 111, 'cofres' => array('objeto_id' => "CFN007", 'cantidad' => 1)),
        10 => array('experiencia' => 775, 'nikas' => 162, 'cofres' => array('objeto_id' => "CFN008", 'cantidad' => 1))
    ));

    // Recompensas para narradores erudito
    define('RECOMPENSAS_NARRADOR_ERUDITO', array(
        1 => array('experiencia' => 115, 'nikas' => 9, 'cofres' => array('objeto_id' => "CFN002", 'cantidad' => 1)),
        2 => array('experiencia' => 130, 'nikas' => 15, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 1)),
        3 => array('experiencia' => 170, 'nikas' => 26, 'cofres' => array('objeto_id' => "CFN003", 'cantidad' => 2)),
        4 => array('experiencia' => 190, 'nikas' => 37, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 1)),
        5 => array('experiencia' => 250, 'nikas' => 48, 'cofres' => array('objeto_id' => "CFN004", 'cantidad' => 2)),
        6 => array('experiencia' => 300, 'nikas' => 59, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 1)),
        7 => array('experiencia' => 385, 'nikas' => 70, 'cofres' => array('objeto_id' => "CFN005", 'cantidad' => 2)),
        8 => array('experiencia' => 430, 'nikas' => 91, 'cofres' => array('objeto_id' => "CFN006", 'cantidad' => 1)),
        9 => array('experiencia' => 620, 'nikas' => 112, 'cofres' => array('objeto_id' => "CFN007", 'cantidad' => 1)),
        10 => array('experiencia' => 800, 'nikas' => 163, 'cofres' => array('objeto_id' => "CFN008", 'cantidad' => 1))
    ));

    // Recompensas para jugadores
    define('RECOMPENSAS_JUGADOR', array(
        1 => array('experiencia' => 50, 'nikas' => 5, 'reputacion' => 10, 'berries' => 1000000),
        2 => array('experiencia' => 75, 'nikas' => 10, 'reputacion' => 20, 'berries' => 5000000),
        3 => array('experiencia' => 120, 'nikas' => 20, 'reputacion' => 50, 'berries' => 10000000),
        4 => array('experiencia' => 150, 'nikas' => 30, 'reputacion' => 80, 'berries' => 15000000),
        5 => array('experiencia' => 200, 'nikas' => 40, 'reputacion' => 120, 'berries' => 25000000),
        6 => array('experiencia' => 250, 'nikas' => 50, 'reputacion' => 160, 'berries' => 50000000),
        7 => array('experiencia' => 300, 'nikas' => 60, 'reputacion' => 200, 'berries' => 75000000),
        8 => array('experiencia' => 400, 'nikas' => 80, 'reputacion' => 250, 'berries' => 100000000),
        9 => array('experiencia' => 600, 'nikas' => 100, 'reputacion' => 300, 'berries' => 200000000),
        10 => array('experiencia' => 750, 'nikas' => 150, 'reputacion' => 500, 'berries' => 350000000)
    ));

// ENDREGION CONSTANTES DE RECOMPENSAS POR TIER

// REQUIRES
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

// DEFINICIONES GLOBALES
global $db, $mybb, $templates, $headerinclude, $header, $footer;

// FUNCIÓN PARA DAR OBJETOS AL INVENTARIO
function darObjeto($objeto_id, $cantidadNueva, $fid) {
    global $db;
    $cantidadActual = '0';
    $has_objeto = false;
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$fid' AND objeto_id='$objeto_id'");
    while ($q = $db->fetch_array($inventario_actual)) { 
        $has_objeto = true; 
        $cantidadActual = $q['cantidad']; 
    }

    if ($has_objeto) {
        $cantidadNuevaNueva = intval($cantidadActual) + intval($cantidadNueva);
        $db->query("
            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNuevaNueva' WHERE objeto_id='$objeto_id' AND uid='$fid'
        ");
    } else {
        $db->query(" 
            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('$objeto_id', '$fid', '$cantidadNueva');
        ");
    }
}

// MANEJO DE ACCIÓN AJAX - DEBE ESTAR ANTES DE CUALQUIER SALIDA
if (isset($mybb->input['action']) && $mybb->input['action'] == 'procesar_pago') {
    // Limpiar cualquier salida previa
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // Log de inicio
        error_log("=== INICIO PROCESAMIENTO PAGO ===");
        
        // Leer datos JSON del body
        $input = file_get_contents('php://input');
        error_log("Input recibido: " . substr($input, 0, 200));
        
        $datos = json_decode($input, true);
        
        if (!$datos) {
            throw new Exception('Datos JSON no válidos o vacíos');
        }
        
        // Validar datos requeridos
        $tid = isset($datos['tid']) ? (int)$datos['tid'] : 0;
        $tier = isset($datos['tier']) ? (int)$datos['tier'] : 0;
        $porcentaje_extra = isset($datos['porcentaje_narrador']) ? (float)$datos['porcentaje_narrador'] : 0;
        $narradores_pago = isset($datos['narradores']) ? $datos['narradores'] : array();
        $jugadores_pago = isset($datos['jugadores']) ? $datos['jugadores'] : array();
        
        if ($tid <= 0 || $tier <= 0) {
            throw new Exception('TID o Tier no válidos (TID: ' . $tid . ', Tier: ' . $tier . ')');
        }
        
        // Obtener información del tema
        $query_tema = $db->simple_select("threads", "subject, fid, closed", "tid = {$tid}");
        $tema_info = $db->fetch_array($query_tema);
        
        if (!$tema_info) {
            throw new Exception('No se encontró el tema con TID: ' . $tid);
        }
        
        // Verificar si el tema ya está cerrado
        if ($tema_info['closed'] == 1) {
            throw new Exception('Este tema ya está cerrado. Es posible que ya haya sido pagado previamente.');
        }
        
        // Verificar si ya existe un post de pago en este tema
        $query_check_pago = $db->simple_select(
            "posts", 
            "pid", 
            "tid = {$tid} AND message LIKE '%RECOMPENSAS PROCESADAS%'",
            array('limit' => 1)
        );
        
        if ($db->num_rows($query_check_pago) > 0) {
            throw new Exception('Este tema ya tiene un registro de pago procesado. No se puede pagar dos veces.');
        }
        
        error_log("Tema encontrado y validado: " . $tema_info['subject']);
        
        // Buscar petición asociada
        $url_busqueda = "%tid={$tid}%";
        $query_peticion = $db->simple_select(
            "op_peticionAventuras",
            "*",
            "aventura_url LIKE '{$url_busqueda}'",
            array('limit' => 1)
        );
        $peticion_info = $db->fetch_array($query_peticion);
        $inframundo = $peticion_info['inframundo'] ? true : false;
        
        error_log("Petición encontrada, inframundo: " . ($inframundo ? 'sí' : 'no'));
        
        // Preparar mensaje del post
        if (!file_exists(MYBB_ROOT."inc/datahandlers/post.php")) {
            throw new Exception('No se encuentra el archivo post.php datahandler');
        }
        
        require_once MYBB_ROOT."inc/datahandlers/post.php";
        
        if (!class_exists('PostDataHandler')) {
            throw new Exception('La clase PostDataHandler no está disponible');
        }
        
        $posthandler = new PostDataHandler("insert");
        
        error_log("PostDataHandler inicializado");
        
        // Construir contenido del post
        $mensaje = "[align=center][img]https://en.onepiece-cardgame.com/renewal/images/beginners/index/ico_howto_current.webp[/img][/align]\n\n";
        $mensaje .= "[align=center][size=x-large][b][color=#27ae60]RECOMPENSAS PROCESADAS[/color][/b][/size][/align]\n\n";
        $mensaje .= "[hr]\n\n";
        $mensaje .= "[b][color=#3498db]Información de la Aventura[/color][/b]\n";
        $mensaje .= "[list]\n";
        $mensaje .= "[*][b]Tier:[/b] [color=#ff7b00]Tier {$tier}[/color]\n";
        $mensaje .= "[*][b]Fecha de pago:[/b] " . date('d/m/Y H:i') . "\n";
        $mensaje .= "[*][b]Procesado por:[/b] ID: {$mybb->user['uid']} - {$mybb->user['username']}\n";
        $mensaje .= "[/list]\n\n";
        
        // Resetear contador mensual de experiencia para narradores (una vez por mes)
        date_default_timezone_set('Europe/Madrid');
        $mes_actual = date('Y-m'); // Formato: 2026-02
        
        // Verificar si ya se hizo el reset este mes
        $query_ultimo_reset = $db->simple_select("settings", "value", "name = 'exp_narrador_ultimo_reset'");
        $ultimo_reset = $db->fetch_field($query_ultimo_reset, "value");
        
        // Si no existe el setting o es de un mes anterior, resetear
        if (!$ultimo_reset || $ultimo_reset != $mes_actual) {
            $db->write_query("UPDATE mybb_op_fichas SET expNarradorMensualActual = 0 WHERE expNarradorMensualActual > 0");
            
            // Actualizar o insertar el setting con el mes actual
            if ($ultimo_reset) {
                $db->update_query("settings", array('value' => $mes_actual), "name = 'exp_narrador_ultimo_reset'");
            } else {
                $db->insert_query("settings", array(
                    'name' => 'exp_narrador_ultimo_reset',
                    'title' => 'Último reset de experiencia mensual de narradores',
                    'description' => 'Guarda el mes (YYYY-MM) del último reset automático',
                    'optionscode' => 'text',
                    'value' => $mes_actual,
                    'disporder' => 0,
                    'gid' => 0
                ));
            }
            
            error_log("Reset mensual de experiencia de narradores realizado para el mes: {$mes_actual}");
        }
        
        // Array para almacenar información de pagos
        $pagos_realizados = array();
        
        // Procesar narradores
        if (!empty($narradores_pago)) {
            $mensaje .= "[b][color=#9b59b6]Narradores[/color][/b]\n\n";
            
            foreach ($narradores_pago as $narrador_data) {
                $uid = (int)$narrador_data['uid'];
                $tier_narrador = isset($narrador_data['tier_narrador']) ? (int)$narrador_data['tier_narrador'] : 1;
                $posts_extras = isset($narrador_data['extra_posts']) ? (float)$narrador_data['extra_posts'] : 0;
                $porcentaje_participacion = isset($narrador_data['porcentaje_participacion']) ? (float)$narrador_data['porcentaje_participacion'] : 100;
                
                // Obtener información del usuario
                $query_user = $db->simple_select("users", "username", "uid = {$uid}");
                $user_data = $db->fetch_array($query_user);
                
                if (!$user_data) continue;
                
                // Determinar recompensas según tier del narrador
                $recompensas_tabla = array();
                switch($tier_narrador) {
                    case 1: $recompensas_tabla = RECOMPENSAS_NARRADOR_APRENDIZ; break;
                    case 2: $recompensas_tabla = RECOMPENSAS_NARRADOR_ESTUDIOSO; break;
                    case 3: $recompensas_tabla = RECOMPENSAS_NARRADOR_ILUSTRE; break;
                    case 4: $recompensas_tabla = RECOMPENSAS_NARRADOR_ERUDITO; break;
                    default: $recompensas_tabla = RECOMPENSAS_NARRADOR_APRENDIZ;
                }
                
                if (!isset($recompensas_tabla[$tier])) continue;
                
                $recompensas_base = $recompensas_tabla[$tier];
                
                // Usar el porcentaje calculado por el frontend (ya incluye posts base + extras + porcentaje extra)
                $porcentaje_aplicado = $porcentaje_participacion;
                
                // Calcular recompensas finales
                $exp_final = round($recompensas_base['experiencia'] * ($porcentaje_aplicado / 100));
                $nikas_final = round($recompensas_base['nikas'] * ($porcentaje_aplicado / 100));
                $cofres_cantidad = $recompensas_base['cofres']['cantidad'];
                $cofres_tipo = $recompensas_base['cofres']['objeto_id'];
                
                // Obtener experiencia mensual actual del narrador
                $query_ficha = $db->simple_select("op_fichas", "expNarradorMensualActual", "fid = {$uid}");
                $ficha_narrador = $db->fetch_array($query_ficha);
                $exp_mensual_actual = $ficha_narrador ? (float)$ficha_narrador['expNarradorMensualActual'] : 0;
                
                // Límite mensual de experiencia para narradores: 675 puntos
                $limite_mensual = 675;
                $exp_disponible = max(0, $limite_mensual - $exp_mensual_actual);
                
                // Calcular experiencia que realmente se entregará (limitada por el máximo mensual)
                $exp_a_entregar = min($exp_final, $exp_disponible);
                $exp_excedente = $exp_final - $exp_a_entregar;
                $kuros_por_excedente = ($exp_excedente > 0) ? (int)floor($exp_excedente / 2) : 0;
                $kuro_sql = ($kuros_por_excedente > 0) ? ", kuro = kuro + {$kuros_por_excedente}" : "";
                
                // Aplicar recompensas en la base de datos
                if ($exp_a_entregar > 0) {
                    $db->write_query("UPDATE mybb_users SET newpoints = newpoints + {$exp_a_entregar} WHERE uid = {$uid}");
                    $db->write_query("UPDATE mybb_op_fichas SET nika = nika + {$nikas_final}, expNarradorMensualActual = expNarradorMensualActual + {$exp_a_entregar}{$kuro_sql} WHERE fid = {$uid}");
                } else {
                    // Solo actualizar nikas si no se puede dar más experiencia
                    $db->write_query("UPDATE mybb_op_fichas SET nika = nika + {$nikas_final}{$kuro_sql} WHERE fid = {$uid}");
                }
                
                // Dar cofre al narrador
                $cofres_entregados = 0;
                if (!empty($cofres_tipo) && $cofres_cantidad > 0) {
                    darObjeto($cofres_tipo, $cofres_cantidad, $uid);
                    $cofres_entregados = $cofres_cantidad;
                }
                
                // Registro en audit_general
                $cofres_log = ($cofres_entregados > 0) ? "{$cofres_entregados}x {$cofres_tipo}" : "No entregados";
                $exp_info = ($exp_excedente > 0) ? "{$exp_a_entregar} (límite mensual alcanzado, {$exp_excedente} EXP extra → +{$kuros_por_excedente} kuros)" : "{$exp_a_entregar}";
                $textoLog = "
                    <strong><i><u>Moderador</i></u></strong>: {$mybb->user['username']} ({$mybb->user['uid']}) <br>
                    <strong><i><u>Usuario</i></u></strong>: {$user_data['username']} ({$uid}) <br>
                    <strong><i><u>ID del tema</i></u></strong>: {$tid} <br>
                    * <strong><i><u>Tier</i></u></strong>: {$tier} <br>
                    * <strong><i><u>Tier Narrador</i></u></strong>: {$tier_narrador} <br>
                    * <strong><i><u>Experiencia</i></u></strong>: +{$exp_info} <br>
                    * <strong><i><u>Exp mensual acumulada</i></u></strong>: {$exp_mensual_actual} + {$exp_a_entregar} = " . ($exp_mensual_actual + $exp_a_entregar) . " / {$limite_mensual} <br>
                    * <strong><i><u>Nikas</i></u></strong>: +{$nikas_final} <br>" . ($kuros_por_excedente > 0 ? "
                    * <strong><i><u>Kuros (por excedente)</i></u></strong>: +{$kuros_por_excedente} <br>" : "") . "
                    * <strong><i><u>Cofres</i></u></strong>: {$cofres_log} <br>
                    * <strong><i><u>Porcentaje aplicado</i></u></strong>: {$porcentaje_aplicado}% <br>
                ";
                log_audit($mybb->user['uid'], $mybb->user['username'], '[Entregas][Aventura Narradores]', "$textoLog");
                
                // Agregar al mensaje
                $mensaje .= "[quote]";
                $mensaje .= "[b]ID:{$uid} - {$user_data['username']}[/b] - [i]Tier Narrador: {$tier_narrador}[/i]\n\n";
                $mensaje .= "[b]Recompensas recibidas:[/b]\n";
                $mensaje .= "[list]\n";
                if ($exp_excedente > 0) {
                    $mensaje .= "[*][b]Experiencia:[/b] [color=#3498db]{$exp_a_entregar} EXP[/color] [color=#e74c3c](⚠ Límite mensual: {$exp_excedente} EXP extra → +{$kuros_por_excedente} kuros)[/color]\n";
                    $mensaje .= "[*][b]Exp mensual:[/b] " . ($exp_mensual_actual + $exp_a_entregar) . " / {$limite_mensual}\n";
                    $mensaje .= "[*][b]Kuros:[/b] [color=#8e44ad]+{$kuros_por_excedente} kuros[/color] [i](mitad del excedente)[/i]\n";
                } else {
                    $mensaje .= "[*][b]Experiencia:[/b] [color=#3498db]{$exp_a_entregar} EXP[/color]\n";
                    $mensaje .= "[*][b]Exp mensual:[/b] " . ($exp_mensual_actual + $exp_a_entregar) . " / {$limite_mensual}\n";
                }
                $mensaje .= "[*][b]Nikas:[/b] [color=#f39c12]{$nikas_final} NK[/color]\n";
                $mensaje .= "[*][b]Cofres:[/b] [color=#9b59b6]{$cofres_cantidad}x {$cofres_tipo}[/color]\n";
                $mensaje .= "[/list]\n\n";
                $mensaje .= "[b]Bonificaciones:[/b]\n";
                $mensaje .= "[list]\n";
                if ($porcentaje_extra > 0) {
                    $mensaje .= "[*][b]Jugadores extra:[/b] +{$porcentaje_extra}%\n";
                }
                if ($posts_extras != 0) {
                    $signo = $posts_extras > 0 ? '+' : '';
                    $mensaje .= "[*][b]Variación de post:[/b] {$signo}{$posts_extras}%\n";
                }
                $mensaje .= "[*][b]Porcentaje total aplicado:[/b] [color=#27ae60]{$porcentaje_aplicado}%[/color]\n";
                $mensaje .= "[/list]";
                $mensaje .= "[/quote]\n\n";
                
                $pagos_realizados[] = array(
                    'tipo' => 'narrador',
                    'username' => $user_data['username'],
                    'uid' => $uid
                );
            }
        }
        
        // Procesar jugadores
        if (!empty($jugadores_pago)) {
            $mensaje .= "[b][color=#16a085]Jugadores[/color][/b]\n\n";
            
            foreach ($jugadores_pago as $jugador_data) {
                $uid = (int)$jugador_data['uid'];
                $posts_extras = isset($jugador_data['extra_posts']) ? (float)$jugador_data['extra_posts'] : 0;
                $tipo_reputacion = isset($jugador_data['tipo_reputacion']) ? $jugador_data['tipo_reputacion'] : 'positiva';
                $porcentaje_participacion = isset($jugador_data['porcentaje_participacion']) ? (float)$jugador_data['porcentaje_participacion'] : 100;
                
                // Obtener información del usuario
                $query_user = $db->simple_select("users", "username", "uid = {$uid}");
                $user_data = $db->fetch_array($query_user);
                
                if (!$user_data) continue;
                
                // Obtener recompensas de jugador
                if (!isset(RECOMPENSAS_JUGADOR[$tier])) continue;
                
                $recompensas_base = RECOMPENSAS_JUGADOR[$tier];
                
                // Usar el porcentaje calculado por el frontend (ya incluye posts base + extras)
                $porcentaje_aplicado = $porcentaje_participacion;

                // --- Bonus de Oficios del jugador ---
                $query_ficha_jugador = $db->simple_select("op_fichas", "oficios", "fid = {$uid}");
                $ficha_jugador = $db->fetch_array($query_ficha_jugador);
                $oficios_jugador = ($ficha_jugador && $ficha_jugador['oficios']) ? json_decode($ficha_jugador['oficios']) : null;

                // Contrabandista (berries)
                $nivelContrabandista = -1;
                $contrabandistaMult = 1;
                if ($oficios_jugador && isset($oficios_jugador->{'Mercader'})) {
                    $nivelContrabandista = $oficios_jugador->{'Mercader'}->{'sub'}->{'Contrabandista'};
                }
                if ($nivelContrabandista == 0)      { $contrabandistaMult = 1.25; }
                else if ($nivelContrabandista == 1) { $contrabandistaMult = 1.50; }
                else if ($nivelContrabandista == 2) { $contrabandistaMult = 1.75; }
                else if ($nivelContrabandista == 3) { $contrabandistaMult = 2.00; }

                // Arqueologo (XP y Nikas)
                $nivelArqueologo = -1;
                $arqueologoExtraXP = 1;
                $arqueologoExtraNikas = 0;
                if ($oficios_jugador && isset($oficios_jugador->{'Investigador'})) {
                    $nivelArqueologo = $oficios_jugador->{'Investigador'}->{'sub'}->{'Arqueólogo'};
                }
                if ($nivelArqueologo == 0)      { $arqueologoExtraXP = 1.00; }
                else if ($nivelArqueologo == 1) { $arqueologoExtraXP = 1.10; }
                else if ($nivelArqueologo == 2) { $arqueologoExtraNikas = 1; }
                // --- Fin Bonus de Oficios ---
                
                // Calcular recompensas finales
                $exp_final = round($recompensas_base['experiencia'] * (($porcentaje_aplicado * $arqueologoExtraXP) / 100));
                $nikas_final = round(($recompensas_base['nikas'] + $arqueologoExtraNikas) * (($porcentaje_aplicado) / 100));
                $reputacion_final = ($tipo_reputacion === 'ninguna') ? 0 : round($recompensas_base['reputacion'] * ($porcentaje_aplicado / 100));
                
                // Calcular berries con desglose de bono inframundo y contrabandista
                $berries_base = $recompensas_base['berries'];
                $berries_base_final = round($berries_base * ($porcentaje_aplicado / 100));
                if ($inframundo) {
                    $berries_final = round($berries_base * ($porcentaje_aplicado / 100) * $contrabandistaMult * 1.25);
                } else {
                    $berries_final = round($berries_base * ($porcentaje_aplicado / 100) * $contrabandistaMult);
                }
                
                // Aplicar recompensas en la base de datos
                $db->write_query("UPDATE mybb_users SET newpoints = newpoints + {$exp_final} WHERE uid = {$uid}");

                $inframundo_sql = $inframundo ? ", movidoInframundo = COALESCE(movidoInframundo, 0) + {$berries_final}" : "";

                // Actualizar reputación según el tipo seleccionado
                if ($tipo_reputacion === 'positiva') {
                    $db->write_query("UPDATE mybb_op_fichas SET nika = nika + {$nikas_final}, reputacion = reputacion + {$reputacion_final}, reputacion_positiva = reputacion_positiva + {$reputacion_final}, berries = berries + {$berries_final}{$inframundo_sql} WHERE fid = {$uid}");
                } elseif ($tipo_reputacion === 'negativa') {
                    $db->write_query("UPDATE mybb_op_fichas SET nika = nika + {$nikas_final}, reputacion = reputacion + {$reputacion_final}, reputacion_negativa = reputacion_negativa + {$reputacion_final}, berries = berries + {$berries_final}{$inframundo_sql} WHERE fid = {$uid}");
                } else {
                    // 'ninguna': no se toca la reputación en absoluto
                    $db->write_query("UPDATE mybb_op_fichas SET nika = nika + {$nikas_final}, berries = berries + {$berries_final}{$inframundo_sql} WHERE fid = {$uid}");
                }
                
                // Registro en audit_general
                if ($tipo_reputacion === 'positiva') {
                    $tipo_rep_texto = 'Positiva';
                } elseif ($tipo_reputacion === 'negativa') {
                    $tipo_rep_texto = 'Negativa';
                } else {
                    $tipo_rep_texto = 'Ninguna';
                }
                $contrabandistaTxt = "<strong><i><u>Contrabandista</i></u></strong>: Nivel {$nivelContrabandista}. Multiplicador = {$contrabandistaMult}.";
                $arqueologoTxt = "<strong><i><u>Arqueologo</i></u></strong>: Nivel {$nivelArqueologo}. Multiplicador XP = {$arqueologoExtraXP}. Nikas Extra = {$arqueologoExtraNikas}.";
                $textoLog = "
                    <strong><i><u>Moderador</i></u></strong>: {$mybb->user['username']} ({$mybb->user['uid']}) <br>
                    <strong><i><u>Usuario</i></u></strong>: {$user_data['username']} ({$uid}) <br>
                    <strong><i><u>ID del tema</i></u></strong>: {$tid} <br>
                    * <strong><i><u>Tier</i></u></strong>: {$tier} <br>
                    * <strong><i><u>Experiencia</i></u></strong>: +{$exp_final} <br>
                    * <strong><i><u>Nikas</i></u></strong>: +{$nikas_final} <br>
                    * <strong><i><u>Reputación ({$tipo_rep_texto})</i></u></strong>: +{$reputacion_final} <br>
                    * <strong><i><u>Berries</i></u></strong>: +{$berries_final} ฿ <br>
                    * <strong><i><u>Porcentaje aplicado</i></u></strong>: {$porcentaje_aplicado}% <br>
                    {$contrabandistaTxt} <br>
                    {$arqueologoTxt} <br>
                ";
                log_audit($mybb->user['uid'], $mybb->user['username'], '[Entregas][Aventura Usuario]', "$textoLog");
                
                if ($tipo_reputacion === 'positiva') {
                    $tipo_rep_emoji = '✨';
                    $tipo_rep_texto = 'Positiva';
                } elseif ($tipo_reputacion === 'negativa') {
                    $tipo_rep_emoji = '💀';
                    $tipo_rep_texto = 'Negativa';
                } else {
                    $tipo_rep_emoji = '🚫';
                    $tipo_rep_texto = 'Ninguna';
                }
                
                $mensaje .= "[quote]";
                $mensaje .= "[b]ID:{$uid} - {$user_data['username']}[/b]\n\n";
                $mensaje .= "[b]Recompensas recibidas:[/b]\n";
                $mensaje .= "[list]\n";
                $mensaje .= "[*][b]Experiencia:[/b] [color=#3498db]{$exp_final} EXP[/color]\n";
                $mensaje .= "[*][b]Nikas:[/b] [color=#f39c12]{$nikas_final} NK[/color]\n";
                $mensaje .= "[*][b]Reputación ({$tipo_rep_emoji} {$tipo_rep_texto}):[/b] [color=#e74c3c]{$reputacion_final} REP[/color]\n";
                if ($inframundo && $nivelContrabandista > -1) {
                    $mensaje .= "[*][b]Berries:[/b] [color=#27ae60]{$berries_final} ฿[/color] (Base: {$berries_base_final} + Contrabandista x{$contrabandistaMult} + Inframundo 25%)\n";
                } else if ($inframundo) {
                    $mensaje .= "[*][b]Berries:[/b] [color=#27ae60]{$berries_final} ฿[/color] (Base: {$berries_base_final} + Bono inframundo 25%)\n";
                } else if ($nivelContrabandista > -1) {
                    $mensaje .= "[*][b]Berries:[/b] [color=#27ae60]{$berries_final} ฿[/color] (Base: {$berries_base_final} + Contrabandista x{$contrabandistaMult})\n";
                } else {
                    $mensaje .= "[*][b]Berries:[/b] [color=#27ae60]{$berries_final} ฿[/color]\n";
                }
                $mensaje .= "[/list]\n\n";
                $has_bonificaciones = ($posts_extras != 0 || $nivelContrabandista > -1 || $nivelArqueologo > -1);
                if ($has_bonificaciones) {
                    $signo = $posts_extras > 0 ? '+' : '';
                    $mensaje .= "[b]Bonificaciones:[/b]\n";
                    $mensaje .= "[list]\n";
                    if ($posts_extras != 0) {
                        $mensaje .= "[*][b]Variación de post:[/b] {$signo}{$posts_extras}%\n";
                    }
                    if ($nivelContrabandista > -1) {
                        $mensaje .= "[*][b]Contrabandista (Nivel {$nivelContrabandista}):[/b] x{$contrabandistaMult} berries\n";
                    }
                    if ($nivelArqueologo > -1) {
                        if ($arqueologoExtraNikas > 0) {
                            $mensaje .= "[*][b]Arqueologo (Nivel {$nivelArqueologo}):[/b] +{$arqueologoExtraNikas} nika extra\n";
                        } else {
                            $mensaje .= "[*][b]Arqueologo (Nivel {$nivelArqueologo}):[/b] x{$arqueologoExtraXP} XP\n";
                        }
                    }
                    $mensaje .= "[*][b]Porcentaje total aplicado:[/b] [color=#27ae60]{$porcentaje_aplicado}%[/color]\n";
                    $mensaje .= "[/list]";
                }
                $mensaje .= "[/quote]\n\n";
                
                $pagos_realizados[] = array(
                    'tipo' => 'jugador',
                    'username' => $user_data['username'],
                    'uid' => $uid
                );
            }
        }
        
        $mensaje .= "[hr]\n\n";
        $mensaje .= "[align=center][size=small][i]✅ Pago procesado automáticamente por el sistema de recompensas de One Piece Gaiden[/i][/size][/align]";
        
        error_log("Mensaje del post construido");
        
        // Obtener datos del usuario con UID 5 para publicar el post
        $uid_post = 5;
        $query_user_post = $db->simple_select("users", "username", "uid = {$uid_post}");
        $user_post_data = $db->fetch_array($query_user_post);
        
        if (!$user_post_data) {
            throw new Exception('No se encontró el usuario con UID 5 para publicar el post');
        }
        
        // Crear el post con todos los campos requeridos (usando UID 5)
        $post = array(
            "tid" => $tid,
            "fid" => $tema_info['fid'],
            "uid" => $uid_post,
            "username" => $user_post_data['username'],
            "subject" => "RE: " . $tema_info['subject'],
            "message" => $mensaje,
            "ipaddress" => get_ip(),
            "posthash" => md5($uid_post.random_str()),
            "options" => array(
                "signature" => 0,
                "subscriptionmethod" => "",
                "disablesmilies" => 0
            )
        );
        
        $posthandler->action = "post";
        $posthandler->set_data($post);
        
        error_log("Validando post...");
        
        if ($posthandler->validate_post()) {
            $post_info = $posthandler->insert_post();
            
            error_log("Post creado exitosamente: PID " . $post_info['pid']);
            
            // Cerrar el tema
            $db->update_query("threads", array("closed" => 1), "tid = {$tid}");
            error_log("Tema cerrado: TID " . $tid);
            
            // Limpiar buffer y enviar respuesta
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(array(
                'ok' => true, 
                'message' => 'Pago procesado correctamente',
                'post_id' => $post_info['pid'],
                'pagos' => count($pagos_realizados),
                'tema_cerrado' => true
            ));
        } else {
            $errors = $posthandler->get_friendly_errors();
            error_log("Errores al validar post: " . implode(', ', $errors));
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(array(
                'ok' => false, 
                'error' => 'Error al crear post: ' . implode(', ', $errors)
            ));
        }
        
    } catch (Exception $e) {
        // Capturar cualquier error y devolver JSON
        error_log("EXCEPCIÓN CAPTURADA: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(array(
            'ok' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
    } catch (Error $e) {
        // Capturar errores fatales de PHP 7+
        error_log("ERROR FATAL CAPTURADO: " . $e->getMessage());
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(array(
            'ok' => false,
            'error' => 'Error fatal: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
    }
    
    exit;
}

// SEGURIDAD DE PÁGINA (solo para visualización normal)
if (!is_staff($mybb->user['uid']) && !is_narra($mybb->user['uid'])) {
    error_no_permission();
}

// REGION DEPURADO URL
    
    // Obtener TID de la aventura desde la URL
    $tid = isset($_GET['tid']) ? (int)$_GET['tid'] : 0;

    // Validar que se ha proporcionado un tid válido
    if ($tid <= 0) {
        error("No se ha proporcionado un ID de tema válido.");
    }

// ENDREGION DEPURADO URL

// REGION CONSULTA POSTS

    // Consultar la base de datos para obtener todos los posts del tema
    $query = $db->simple_select("posts", "*", "tid = {$tid}", array(
        "order_by" => "dateline",
        "order_dir" => "ASC"
    ));

    // Almacenar todos los posts en un array
    $posts = array();
    while ($post = $db->fetch_array($query)) {
        $posts[] = $post;
    }

    // Verificar que el tema tiene posts
    if (empty($posts)) {
        error("No se encontraron posts para el tema con ID {$tid}.");
    }

// ENDREGION CONSULTA POSTS

// REGION COMPROBAR NARRADORES

    // Listado de narradores (array de UIDs únicos)
    $narradores = array();
    
    // Recorrer todos los posts para buscar [narrador]
    foreach ($posts as $post) {
        // Buscar si el mensaje contiene literalmente "[narrador]"
        if (stripos($post['message'], '[narrador]') !== false) {
            $uid = (int)$post['uid'];
            
            // Añadir al listado solo si no existe ya (evitar duplicados)
            if (!in_array($uid, $narradores)) {
                $narradores[] = $uid;
            }
        }
    }

    // Validar que se ha encontrado al menos un narrador
    if (empty($narradores)) {
        error("No se encontraron narradores en el tema con ID {$tid}. Asegúrate de que los posts contienen la etiqueta [narrador].");
    }

// ENDREGION COMPROBAR NARRADORES

// REGION CONTAR POSTS/USUARIO Y CARACTERES

    // Listado de usuarios con su cantidad de posts
    $usuarios_posts = array();
    
    // Listado de posts no válidos (menos de 300 caracteres)
    $posts_no_validos = array();
    
    // Recorrer todos los posts
    foreach ($posts as $post) {
        $uid = (int)$post['uid'];
        $message = $post['message'];
        $pid = $post['pid'];
        
        // Contar caracteres del mensaje (sin etiquetas HTML)
        $caracteres = strlen(strip_tags($message));
        
        // Registrar el usuario y contar sus posts
        if (!isset($usuarios_posts[$uid])) {
            $usuarios_posts[$uid] = array(
                'uid' => $uid,
                'username' => $post['username'],
                'posts' => 0
            );
        }
        $usuarios_posts[$uid]['posts']++;
        
        // Si el post tiene menos de 300 caracteres, añadirlo a posts no válidos
        if ($caracteres < 300) {
            $posts_no_validos[] = array(
                'pid' => $pid,
                'uid' => $uid,
                'username' => $post['username'],
                'caracteres' => $caracteres,
                'dateline' => $post['dateline']
            );
        }
    }

// ENDREGION CONTAR POSTS/USUARIO Y CARACTERES

// REGION OBTENER INFO DEL TEMA Y PETICIÓN

    // Obtener información del tema (título y URL)
    $query_tema = $db->simple_select("threads", "subject, fid", "tid = {$tid}");
    $tema_info = $db->fetch_array($query_tema);
    
    if ($tema_info) {
        $aventura_titulo = htmlspecialchars_uni($tema_info['subject']);
        $aventura_url = $mybb->settings['bburl'] . "/showthread.php?tid={$tid}";
    } else {
        $aventura_titulo = "Aventura no encontrada";
        $aventura_url = "#";
    }

    // Buscar petición asociada a esta aventura por URL
    $peticion_info = null;
    $tier_info = array(
        'tier_seleccionado' => null,
        'descripcion_tier' => '',
        'dificultad_texto' => '',
        'dificultad_color' => '#ffffff',
        'ratio_poder' => 0,
        'nivel_promedio' => 0,
        'num_jugadores' => 0,
        'jugadores_peticion' => array(),
        'enemigos_peticion' => array(),
        'detalles_peticion' => array(),
        'inframundo' => 0
    );
    
    // Buscar en la tabla op_peticionAventuras por URL
    $url_busqueda = "%tid={$tid}%";
    $query_peticion = $db->simple_select(
        "op_peticionAventuras", 
        "*", 
        "aventura_url LIKE '" . $db->escape_string($url_busqueda) . "'",
        array('limit' => 1)
    );
    
    if ($db->num_rows($query_peticion) > 0) {
        $peticion_info = $db->fetch_array($query_peticion);
        
        // Extraer información del tier
        $tier_info['tier_seleccionado'] = (int)$peticion_info['tier_seleccionado'];
        $tier_info['descripcion_tier'] = $peticion_info['descripcion_tier'];
        $tier_info['dificultad_texto'] = $peticion_info['dificultad_texto'];
        $tier_info['dificultad_color'] = $peticion_info['dificultad_color'];
        $tier_info['ratio_poder'] = (float)$peticion_info['ratio_poder'];
        $tier_info['nivel_promedio'] = (int)$peticion_info['nivel_promedio'];
        $tier_info['num_jugadores'] = (int)$peticion_info['num_jugadores'];
        $tier_info['inframundo'] = (bool)$peticion_info['inframundo'] ? 1 : 0;
        
        // Decodificar JSON de jugadores
        if (!empty($peticion_info['jugadores_json'])) {
            $jugadores_decoded = json_decode($peticion_info['jugadores_json'], true);
            if (is_array($jugadores_decoded)) {
                $tier_info['jugadores_peticion'] = $jugadores_decoded;
            }
        }
        
        // Decodificar JSON de enemigos
        if (!empty($peticion_info['enemigos_json'])) {
            $enemigos_decoded = json_decode($peticion_info['enemigos_json'], true);
            if (is_array($enemigos_decoded)) {
                $tier_info['enemigos_peticion'] = $enemigos_decoded;
            }
        }
        
        // Decodificar JSON de detalles
        if (!empty($peticion_info['detalles_json'])) {
            $detalles_decoded = json_decode($peticion_info['detalles_json'], true);
            if (is_array($detalles_decoded)) {
                $tier_info['detalles_peticion'] = $detalles_decoded;
            }
        }
    }

// ENDREGION OBTENER INFO DEL TEMA Y PETICIÓN

// REGION CALCULAR PORCENTAJE BASE

    // Catálogo de posts mínimos por tier (posts esperados por personaje)
    $posts_por_tier = array(
        1 => 4, 2 => 5, 3 => 6, 4 => 7, 5 => 8,
        6 => 9, 7 => 10, 8 => 10, 9 => 11, 10 => 12
    );

    // Función para calcular el porcentaje base de un usuario
    // Basado en la relación entre posts válidos y posts esperados por tier
    function calcular_porcentaje_base($posts_validos, $posts_esperados) {
        if ($posts_esperados <= 0) {
            return number_format(100.0, 2);
        }
        
        if ($posts_validos <= $posts_esperados) {
            // Si tiene menos o igual posts que los esperados: proporción directa
            $porcentaje = ($posts_validos / $posts_esperados) * 100;
        } else {
            // Si tiene más posts que los esperados:
            // - Base 100% por completar los posts esperados
            // - Cada post extra suma la mitad del valor de un post normal
            $posts_extra = $posts_validos - $posts_esperados;
            $valor_post_normal = 100 / $posts_esperados;
            $valor_post_extra = $valor_post_normal / 2;
            $porcentaje = 100 + ($posts_extra * $valor_post_extra);
        }
        
        return number_format($porcentaje, 2);
    }

// ENDREGION CALCULAR PORCENTAJE BASE

// REGION SEPARAR NARRADORES Y JUGADORES

    $total_posts = count($posts);
    $posts_validos_jugadores = 0;
    $posts_validos_narradores = 0;
    
    $narradores_info = array();
    $jugadores_info = array();
    
    // Obtener información detallada de cada usuario
    foreach ($usuarios_posts as $uid => $info) {
        $es_narrador = in_array($uid, $narradores);
        
        // Contar posts válidos (≥300 caracteres) para este usuario
        $posts_validos_usuario = 0;
        foreach ($posts as $post) {
            if ($post['uid'] == $uid) {
                $caracteres = strlen(strip_tags($post['message']));
                if ($caracteres >= 300) {
                    $posts_validos_usuario++;
                    
                    if ($es_narrador) {
                        $posts_validos_narradores++;
                    } else {
                        $posts_validos_jugadores++;
                    }
                }
            }
        }
        
        // Obtener información adicional del usuario
        $query_user = $db->simple_select("users", "username, avatar", "uid = {$uid}");
        $user_data = $db->fetch_array($query_user);
        
        // Calcular posts esperados según el tier
        $posts_esperados = 0;
        if ($tier_info['tier_seleccionado'] && isset($posts_por_tier[$tier_info['tier_seleccionado']])) {
            $posts_esperados = $posts_por_tier[$tier_info['tier_seleccionado']];
        }
        
        $datos_usuario = array(
            'uid' => $uid,
            'username' => $user_data ? htmlspecialchars_uni($user_data['username']) : $info['username'],
            'avatar' => $user_data && $user_data['avatar'] ? $user_data['avatar'] : '',
            'total_posts' => $info['posts'],
            'posts_validos' => $posts_validos_usuario,
            'porcentaje_base' => calcular_porcentaje_base($posts_validos_usuario, $posts_esperados)
        );
        
        if ($es_narrador) {
            $narradores_info[] = $datos_usuario;
        } else {
            $jugadores_info[] = $datos_usuario;
        }
    }

// ENDREGION SEPARAR NARRADORES Y JUGADORES

// REGION OBTENER INFO DE REPUTACIÓN DE NARRADORES (Función deprecada, se mantiene solo para mostrar nivel y reputación actual en la plantilla)

    // Para cada narrador, obtener su nivel y reputación actual
    foreach ($narradores_info as &$narrador) {
        $uid = $narrador['uid'];
        
        // Consultar ficha del narrador para obtener nivel y reputación
        $query_ficha = $db->simple_select("op_fichas", "nivel, reputacion", "fid = {$uid}");
        $ficha_data = $db->fetch_array($query_ficha);
        
        if ($ficha_data) {
            $nivel_actual = (int)$ficha_data['nivel'];
            $reputacion_actual = (float)$ficha_data['reputacion'];
            
            // Guardar información de nivel y reputación (solo informativo)
            $narrador['nivel'] = $nivel_actual;
            $narrador['reputacion_actual'] = $reputacion_actual;
        } else {
            // Si no tiene ficha
            $narrador['nivel'] = 0;
            $narrador['reputacion_actual'] = 0;
        }
    }
    unset($narrador); // Romper referencia

// ENDREGION OBTENER INFO DE REPUTACIÓN DE NARRADORES

// REGION PREPARAR CONTENIDO PARA LA PLANTILLA
    
    // Calcular posts mínimos base según el tier
    $posts_minimos_base = 0;
    if ($tier_info['tier_seleccionado'] && isset($posts_por_tier[$tier_info['tier_seleccionado']])) {
        $posts_minimos_base = $posts_por_tier[$tier_info['tier_seleccionado']];
    }
    
    // Calcular posts mínimos totales según participantes (narradores + jugadores)
    $total_participantes = count($narradores_info) + count($jugadores_info);
    $posts_minimos = $posts_minimos_base * $total_participantes;

    if ($tid > 0 && $aventura_titulo != 'Aventura no encontrada') {
        $contenido_aventura = '';
        
        // Panel de información de la aventura
        $contenido_aventura .= '<div class="infoPanel">';
        $contenido_aventura .= '<h3>Información de la Aventura</h3>';
        //$contenido_aventura .= 'tier_info: ' . print_r($tier_info, true); // Línea de depuración de tier_info
        $contenido_aventura .= '<div class="participanteInfo"><strong>Título:</strong> ' . $aventura_titulo . '</div>';
        $contenido_aventura .= '<div class="participanteInfo"><strong>Enlace:</strong> <a href="' . $aventura_url . '" target="_blank">' . $aventura_url . '</a></div>';
        $contenido_aventura .= '<div class="participanteInfo"><strong>TID:</strong> ' . $tid . '</div>';
        
        // Calcular total de posts válidos
        $total_posts_validos = $posts_validos_jugadores + $posts_validos_narradores;
        
        // Mostrar posts válidos totales con mínimos requeridos si hay tier
        if ($posts_minimos > 0) {
            $cumple_minimo = $total_posts_validos >= $posts_minimos ? '✓' : '✗';
            $color_minimo = $total_posts_validos >= $posts_minimos ? '#2ecc71' : '#e74c3c';
            $contenido_aventura .= '<div class="participanteInfo" id="infoPostsValidos" data-posts-validos="' . $total_posts_validos . '"><strong>Total de posts válidos:</strong> <span id="totalPostsValidos">' . $total_posts_validos . '</span> / <span id="postsMinimosRequeridos">' . $posts_minimos . '</span> mínimos <span id="iconoCumpleMinimo" style="color: ' . $color_minimo . '; font-weight: bold;">' . $cumple_minimo . '</span> <span style="font-size: 11px; opacity: 0.7;">(<span id="postsBaseCalculo">' . $posts_minimos_base . '</span> × <span id="participantesCalculo">' . $total_participantes . '</span> participantes)</span></div>';
        } else {
            $contenido_aventura .= '<div class="participanteInfo" id="infoPostsValidos" data-posts-validos="' . $total_posts_validos . '"><strong>Total de posts válidos:</strong> <span id="totalPostsValidos">' . $total_posts_validos . '</span></div>';
        }
        
        $contenido_aventura .= '<div class="participanteInfo"><strong>Posts válidos de jugadores:</strong> ' . $posts_validos_jugadores . ' (≥300 caracteres)</div>';
        $contenido_aventura .= '<div class="participanteInfo"><strong>Posts válidos de narradores:</strong> ' . $posts_validos_narradores . ' (≥300 caracteres)</div>';
        
        // Mostrar recompensas del tier si existe
        if ($tier_info['tier_seleccionado'] && isset(RECOMPENSAS_NARRADOR_ERUDITO[$tier_info['tier_seleccionado']])) {
            $recomp_narrador = RECOMPENSAS_NARRADOR_ERUDITO[$tier_info['tier_seleccionado']];
            $recomp_jugador = RECOMPENSAS_JUGADOR[$tier_info['tier_seleccionado']];
            
            // Aplicar bonus de inframundo solo a berries de jugadores
            if ($tier_info['inframundo'] == 1) {
                $recomp_jugador['berries'] = $recomp_jugador['berries'] * 1.25;
            }
            
            $contenido_aventura .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">';
            
            // Recompensas de narrador
            $contenido_aventura .= '<div class="participanteInfo"><strong>Recompensas Base Narrador (Tier ' . $tier_info['tier_seleccionado'] . '):</strong></div>';
            $contenido_aventura .= '<div class="participanteInfo" style="padding-left: 20px; font-size: 13px;">';
            $contenido_aventura .= '• Experiencia: ' . number_format($recomp_narrador['experiencia']) . ' | ';
            $contenido_aventura .= 'Nikas: ' . $recomp_narrador['nikas'] . ' | ';
            $contenido_aventura .= 'Cofres: ' . $recomp_narrador['cofres']['cantidad'] . 'x (ID: ' . $recomp_narrador['cofres']['objeto_id'] . ')';
            $contenido_aventura .= '</div>';
            
            // Recompensas de jugador
            $contenido_aventura .= '<div class="participanteInfo" style="margin-top: 8px;"><strong>Recompensas Base Jugador (Tier ' . $tier_info['tier_seleccionado'] . '):</strong></div>';
            $contenido_aventura .= '<div class="participanteInfo" style="padding-left: 20px; font-size: 13px;">';
            $contenido_aventura .= '• Experiencia: ' . number_format($recomp_jugador['experiencia']) . ' | ';
            $contenido_aventura .= 'Nikas: ' . $recomp_jugador['nikas'] . ' | ';
            $contenido_aventura .= 'Reputación: ' . $recomp_jugador['reputacion'] . ' | ';
            $contenido_aventura .= 'Berries: ' . number_format($recomp_jugador['berries']) . ' | ';
            $contenido_aventura .= 'Inframundo: ' . ($tier_info['inframundo'] == 1 ? 'Sí (+25% berries)' : 'No');
            $contenido_aventura .= '</div>';
            
            $contenido_aventura .= '</div>';
        }
        
        // Mostrar información del tier si existe
        if ($tier_info['tier_seleccionado']) {
            $contenido_aventura .= '<div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">';
            $contenido_aventura .= '<span class="tierBadge">Tier ' . $tier_info['tier_seleccionado'] . '</span>';
            $contenido_aventura .= '<span class="dificultadBadge" style="background: ' . $tier_info['dificultad_color'] . '20; color: ' . $tier_info['dificultad_color'] . ';">' . $tier_info['dificultad_texto'] . '</span>';
            if ($tier_info['inframundo']) {
                $contenido_aventura .= '<span class="inframundoBadge">🔥 Inframundo</span>';
            }
            if ($tier_info['ratio_poder'] > 0) {
                $contenido_aventura .= '<span class="infoBadge">Ratio poder: ' . number_format($tier_info['ratio_poder'], 2) . '</span>';
            }
            $contenido_aventura .= '</div>';
            if ($tier_info['descripcion_tier']) {
                $contenido_aventura .= '<div class="tierDescripcion">' . htmlspecialchars_uni($tier_info['descripcion_tier']) . '</div>';
            }
        }
        
        $contenido_aventura .= '</div>';
        
        // Panel de información de la petición (si existe)
        if ($tier_info['tier_seleccionado']) {
            $contenido_aventura .= '<div class="infoPanel">';
            $contenido_aventura .= '<h3>Información de la Petición Original</h3>';
            $contenido_aventura .= '<div class="registroListas">';
            
            // Jugadores de la petición
            $contenido_aventura .= '<div class="registroListaWrapper">';
            $contenido_aventura .= '<span class="registroListTitle">Jugadores Solicitados (' . count($tier_info['jugadores_peticion']) . ')</span>';
            $contenido_aventura .= '<ul>';
            if (count($tier_info['jugadores_peticion']) > 0) {
                foreach ($tier_info['jugadores_peticion'] as $jugador) {
                    $contenido_aventura .= '<li>' . htmlspecialchars_uni($jugador['nombre']) . ' (Nv. ' . $jugador['nivel'] . ')</li>';
                }
            } else {
                $contenido_aventura .= '<li>Sin jugadores en la petición</li>';
            }
            $contenido_aventura .= '</ul>';
            $contenido_aventura .= '</div>';
            
            // Enemigos de la petición
            $contenido_aventura .= '<div class="registroListaWrapper">';
            $contenido_aventura .= '<span class="registroListTitle">Enemigos Planificados (' . count($tier_info['enemigos_peticion']) . ')</span>';
            $contenido_aventura .= '<ul>';
            if (count($tier_info['enemigos_peticion']) > 0) {
                foreach ($tier_info['enemigos_peticion'] as $enemigo) {
                    $xp_text = isset($enemigo['xp']) ? ' (XP: ' . $enemigo['xp'] . ')' : '';
                    $contenido_aventura .= '<li>' . htmlspecialchars_uni($enemigo['nombre']) . ' (Nv. ' . $enemigo['nivel'] . ') x' . $enemigo['cantidad'] . $xp_text . '</li>';
                }
            } else {
                $contenido_aventura .= '<li>Sin enemigos en la petición</li>';
            }
            $contenido_aventura .= '</ul>';
            $contenido_aventura .= '</div>';
            
            $contenido_aventura .= '</div>';
            $contenido_aventura .= '</div>';
        }
        
        // Panel de narradores
        $contenido_aventura .= '<div class="infoPanel">';
        $contenido_aventura .= '<h3>Narradores Reales (' . count($narradores_info) . ')</h3>';
        if (count($narradores_info) > 0) {
            $contenido_aventura .= '<div class="participantesGrid">';
            foreach ($narradores_info as $narrador) {
                // Verificar si cumple el mínimo individual
                $cumple_minimo_individual = ($posts_minimos_base > 0) ? ($narrador['posts_validos'] >= $posts_minimos_base) : true;
                $icono_minimo = $cumple_minimo_individual ? '✓' : '✗';
                $color_minimo_ind = $cumple_minimo_individual ? '#2ecc71' : '#e74c3c';
                
                $contenido_aventura .= '<div class="participanteCard narrador">';
                $contenido_aventura .= '<h4>' . $narrador['username'] . '</h4>';
                $contenido_aventura .= '<div class="participanteInfo">UID: ' . $narrador['uid'] . '</div>';
                
                $contenido_aventura .= '<div class="participanteInfo">Posts totales: ' . $narrador['total_posts'] . '</div>';
                if ($posts_minimos_base > 0) {
                    $contenido_aventura .= '<div class="participanteInfo posts-validos-info" data-uid="' . $narrador['uid'] . '" data-posts-validos="' . $narrador['posts_validos'] . '">Posts válidos: <span class="posts-validos-cantidad">' . $narrador['posts_validos'] . '</span> / <span class="posts-minimos-base">' . $posts_minimos_base . '</span> mínimos <span class="icono-cumple-minimo" style="color: ' . $color_minimo_ind . '; font-weight: bold; margin-left: 5px;">' . $icono_minimo . '</span></div>';
                } else {
                    $contenido_aventura .= '<div class="participanteInfo posts-validos-info" data-uid="' . $narrador['uid'] . '" data-posts-validos="' . $narrador['posts_validos'] . '">Posts válidos: <span class="posts-validos-cantidad">' . $narrador['posts_validos'] . '</span></div>';
                }
                
                // Mostrar porcentaje base
                $contenido_aventura .= '<div class="participanteInfo">Porcentaje base: <strong>' . $narrador['porcentaje_base'] . '%</strong></div>';
                
                // Mostrar porcentaje final calculado (se actualiza dinámicamente)
                $contenido_aventura .= '<div class="participanteInfo">Porcentaje final: <strong class="narrador-porcentaje-card" data-uid="' . $narrador['uid'] . '" style="color: #2ecc71;">' . $narrador['porcentaje_base'] . '%</strong></div>';
                
                $contenido_aventura .= '</div>';
            }
            $contenido_aventura .= '</div>';
        } else {
            $contenido_aventura .= '<div class="participanteInfo" style="text-align: center; opacity: 0.7;">No hay narradores registrados</div>';
        }
        $contenido_aventura .= '</div>';
        
        // Panel de jugadores
        $contenido_aventura .= '<div class="infoPanel">';
        $contenido_aventura .= '<h3>Jugadores Reales (' . count($jugadores_info) . ')</h3>';
        if (count($jugadores_info) > 0) {
            $contenido_aventura .= '<div class="participantesGrid">';
            foreach ($jugadores_info as $jugador) {
                // Verificar si cumple el mínimo individual
                $cumple_minimo_individual = ($posts_minimos_base > 0) ? ($jugador['posts_validos'] >= $posts_minimos_base) : true;
                $icono_minimo = $cumple_minimo_individual ? '✓' : '✗';
                $color_minimo_ind = $cumple_minimo_individual ? '#2ecc71' : '#e74c3c';
                
                // Verificar si el jugador está en la lista de solicitados
                $jugador_en_peticion = false;
                if (!empty($tier_info['jugadores_peticion'])) {
                    foreach ($tier_info['jugadores_peticion'] as $jug_solicitado) {
                        if (isset($jug_solicitado['uid']) && $jug_solicitado['uid'] == $jugador['uid']) {
                            $jugador_en_peticion = true;
                            break;
                        }
                    }
                }
                
                $clases_extra = '';
                $aviso_no_solicitado = '';
                if (!$jugador_en_peticion && !empty($tier_info['jugadores_peticion'])) {
                    $clases_extra = ' style="border: 3px solid #e67e22 !important; background: linear-gradient(135deg, #d35400, #e67e22) !important;"';
                    $aviso_no_solicitado = '<div class="participanteInfo" style="background: rgba(230, 126, 34, 0.3); padding: 8px; border-radius: 5px; margin-top: 10px; border-left: 4px solid #e67e22;">⚠️ <strong>Jugador no solicitado</strong><br><span style="font-size: 12px;">Este usuario no estaba en la petición original</span></div>';
                }
                
                $contenido_aventura .= '<div class="participanteCard"' . $clases_extra . '>';
                $contenido_aventura .= '<h4>' . $jugador['username'] . '</h4>';
                $contenido_aventura .= '<div class="participanteInfo">UID: ' . $jugador['uid'] . '</div>';
                $contenido_aventura .= '<div class="participanteInfo">Posts totales: ' . $jugador['total_posts'] . '</div>';
                if ($posts_minimos_base > 0) {
                    $contenido_aventura .= '<div class="participanteInfo posts-validos-info" data-uid="' . $jugador['uid'] . '" data-posts-validos="' . $jugador['posts_validos'] . '">Posts válidos: <span class="posts-validos-cantidad">' . $jugador['posts_validos'] . '</span> / <span class="posts-minimos-base">' . $posts_minimos_base . '</span> mínimos <span class="icono-cumple-minimo" style="color: ' . $color_minimo_ind . '; font-weight: bold; margin-left: 5px;">' . $icono_minimo . '</span></div>';
                } else {
                    $contenido_aventura .= '<div class="participanteInfo posts-validos-info" data-uid="' . $jugador['uid'] . '" data-posts-validos="' . $jugador['posts_validos'] . '">Posts válidos: <span class="posts-validos-cantidad">' . $jugador['posts_validos'] . '</span></div>';
                }
                $contenido_aventura .= $aviso_no_solicitado;
                $contenido_aventura .= '</div>';
            }
            $contenido_aventura .= '</div>';
        } else {
            $contenido_aventura .= '<div class="participanteInfo" style="text-align: center; opacity: 0.7;">No hay jugadores registrados</div>';
        }
        $contenido_aventura .= '</div>';
        
        // Panel de configuración de pago
        $contenido_aventura .= '<div class="infoPanel" style="border-color: #27ae60;">';
        $contenido_aventura .= '<h3 style="color: #2ecc71;">⚙️ Configuración de Pago</h3>';
        
        // Selector de Tier
        $contenido_aventura .= '<div class="configSection">';
        $contenido_aventura .= '<label class="configLabel">Tier de la aventura:</label>';
        $contenido_aventura .= '<select id="tierPago" class="configInput" data-posts-tier="' . htmlspecialchars(json_encode($posts_por_tier)) . '">';
        for ($i = 1; $i <= 10; $i++) {
            $selected = ($tier_info['tier_seleccionado'] == $i) ? ' selected' : '';
            $contenido_aventura .= '<option value="' . $i . '"' . $selected . ' data-posts-base="' . $posts_por_tier[$i] . '">Tier ' . $i . ' (' . $posts_por_tier[$i] . ' posts base)</option>';
        }
        $contenido_aventura .= '</select>';
        $contenido_aventura .= '</div>';
        
        // Número de participantes ajustable
        $contenido_aventura .= '<div class="configSection">';
        $contenido_aventura .= '<label class="configLabel">Número de participantes para cálculo:</label>';
        $contenido_aventura .= '<div style="display: flex; align-items: center; gap: 10px;">';
        $contenido_aventura .= '<input type="number" id="numParticipantes" class="configInput" value="' . $total_participantes . '" min="1" max="20" style="max-width: 100px;">';
        $contenido_aventura .= '<span style="color: rgba(255,255,255,0.8); font-size: 13px;">participantes</span>';
        $contenido_aventura .= '<span style="color: rgba(255,255,255,0.6); font-size: 12px; font-style: italic;">(Detectados: ' . $total_participantes . ' | Ajustar si hay posts de admin/invasión)</span>';
        $contenido_aventura .= '</div>';
        $contenido_aventura .= '</div>';
        
        // Porcentaje extra por jugador para narradores
        $contenido_aventura .= '<div class="configSection">';
        $contenido_aventura .= '<label class="configLabel">Porcentaje extra por jugador (10% por jugador extra hasta 50%):</label>';
        $contenido_aventura .= '<div style="display: flex; align-items: center; gap: 10px;">';
        $contenido_aventura .= '<select id="porcentajeExtraJugador" class="configInput" style="max-width: 100px;">';
        $contenido_aventura .= '<option value="0">0</option>';
        $contenido_aventura .= '<option value="10">10</option>';
        $contenido_aventura .= '<option value="20">20</option>';
        $contenido_aventura .= '<option value="30">30</option>';
        $contenido_aventura .= '<option value="40">40</option>';
        $contenido_aventura .= '<option value="50">50</option>';
        $contenido_aventura .= '</select>';
        $contenido_aventura .= '<span style="color: rgba(255,255,255,0.8); font-size: 13px;">%</span>';
        $contenido_aventura .= '<span style="color: rgba(255,255,255,0.6); font-size: 12px; font-style: italic;">(Se suma al % base de cada narrador)</span>';
        $contenido_aventura .= '</div>';
        $contenido_aventura .= '</div>';
        
        // Selección de narradores que cobran
        $contenido_aventura .= '<div class="configSection">';
        $contenido_aventura .= '<label class="configLabel">Narradores que recibirán pago:</label>';
        $contenido_aventura .= '<div class="participantesCheckList">';
        foreach ($narradores_info as $narrador) {
            $contenido_aventura .= '<div class="participanteCheckItem">';
            $contenido_aventura .= '<input type="checkbox" id="narrador_' . $narrador['uid'] . '" class="participanteCheck" value="' . $narrador['uid'] . '" checked>';
            $contenido_aventura .= '<label for="narrador_' . $narrador['uid'] . '" class="participanteCheckLabel">';
            $contenido_aventura .= '<span class="participanteNombre">' . $narrador['username'] . '</span>';
            $contenido_aventura .= '<span class="participanteStats">(UID: ' . $narrador['uid'] . ' | ' . $narrador['posts_validos'] . ' posts válidos)</span>';
            $contenido_aventura .= '</label>';
            $contenido_aventura .= '<div class="participanteExtras">';
            $contenido_aventura .= '<label style="font-size: 12px; color: rgba(255,255,255,0.7); margin-right: 5px;">Tier narrador:</label>';
            $contenido_aventura .= '<select id="tier_narrador_' . $narrador['uid'] . '" class="configInputSmall tier-narrador-select" data-uid="' . $narrador['uid'] . '" style="width: 100px; margin-right: 15px;">';
            $contenido_aventura .= '<option value="1">Aprendiz</option>';
            $contenido_aventura .= '<option value="2">Estudioso</option>';
            $contenido_aventura .= '<option value="3">Ilustre</option>';
            $contenido_aventura .= '<option value="4" selected>Erudito</option>';
            $contenido_aventura .= '</select>';
            $contenido_aventura .= '<span style="font-size: 12px; color: rgba(255,255,255,0.7); margin-right: 5px;">% Final:</span>';
            $contenido_aventura .= '<span class="narrador-porcentaje" data-uid="' . $narrador['uid'] . '" data-posts-validos="' . $narrador['posts_validos'] . '" data-base-porcentaje="' . $narrador['porcentaje_base'] . '" style="font-size: 13px; color: #2ecc71; font-weight: bold;">' . $narrador['porcentaje_base'] . '%</span>';
            $contenido_aventura .= '<label style="font-size: 12px; color: rgba(255,255,255,0.7); margin-left: 15px;">Variación de post:</label>';
            $contenido_aventura .= '<input type="number" id="extra_narrador_' . $narrador['uid'] . '" class="configInputSmall" value="0" min="-100" max="100" style="width: 70px;">';
            $contenido_aventura .= '</div>';;
            $contenido_aventura .= '</div>';
        }
        $contenido_aventura .= '</div>';
        $contenido_aventura .= '</div>';
        
        // Selección de jugadores que cobran
        $contenido_aventura .= '<div class="configSection">';
        $contenido_aventura .= '<label class="configLabel">Jugadores que recibirán pago:</label>';
        $contenido_aventura .= '<div class="participantesCheckList">';
        foreach ($jugadores_info as $jugador) {
            // Verificar si el jugador está en la lista de solicitados
            $jugador_en_peticion = false;
            if (!empty($tier_info['jugadores_peticion'])) {
                foreach ($tier_info['jugadores_peticion'] as $jug_solicitado) {
                    if (isset($jug_solicitado['uid']) && $jug_solicitado['uid'] == $jugador['uid']) {
                        $jugador_en_peticion = true;
                        break;
                    }
                }
            }
            
            $clases_checkbox = '';
            $aviso_checkbox = '';
            if (!$jugador_en_peticion && !empty($tier_info['jugadores_peticion'])) {
                $clases_checkbox = ' style="background: rgba(230, 126, 34, 0.25); border-color: #e67e22;"';
                $aviso_checkbox = '<span style="color: #e67e22; font-size: 11px; font-weight: bold; margin-left: auto;">⚠️ NO SOLICITADO</span>';
            }
            
            $contenido_aventura .= '<div class="participanteCheckItem"' . $clases_checkbox . '>';
            $contenido_aventura .= '<input type="checkbox" id="jugador_' . $jugador['uid'] . '" class="participanteCheck" value="' . $jugador['uid'] . '" checked>';
            $contenido_aventura .= '<label for="jugador_' . $jugador['uid'] . '" class="participanteCheckLabel">';
            $contenido_aventura .= '<span class="participanteNombre">' . $jugador['username'] . '</span>';
            $contenido_aventura .= '<span class="participanteStats">(UID: ' . $jugador['uid'] . ' | ' . $jugador['posts_validos'] . ' posts válidos)</span>';
            $contenido_aventura .= '</label>';
            $contenido_aventura .= $aviso_checkbox;
            $contenido_aventura .= '<div class="participanteExtras">';
            $contenido_aventura .= '<label style="font-size: 12px; color: rgba(255,255,255,0.7); margin-right: 5px;">Tipo reputación:</label>';
            $contenido_aventura .= '<select id="tipo_reputacion_' . $jugador['uid'] . '" class="configInputSmall" style="width: 100px; margin-right: 15px;">';
            $contenido_aventura .= '<option value="positiva" selected>✨ Positiva</option>';
            $contenido_aventura .= '<option value="negativa">💀 Negativa</option>';
            $contenido_aventura .= '<option value="ninguna">🚫 Ninguna</option>';
            $contenido_aventura .= '</select>';
            $contenido_aventura .= '<span style="font-size: 12px; color: rgba(255,255,255,0.7); margin-right: 5px;">% Base:</span>';
            $contenido_aventura .= '<span class="jugador-porcentaje" data-uid="' . $jugador['uid'] . '" data-posts-validos="' . $jugador['posts_validos'] . '" data-base-porcentaje="' . $jugador['porcentaje_base'] . '" style="font-size: 13px; color: #3498db; font-weight: bold;">' . $jugador['porcentaje_base'] . '%</span>';
            $contenido_aventura .= '<label style="font-size: 12px; color: rgba(255,255,255,0.7); margin-left: 15px;">Variación de post:</label>';
            $contenido_aventura .= '<input type="number" id="extra_jugador_' . $jugador['uid'] . '" class="configInputSmall" value="0" min="-100" max="100" style="width: 70px;">';
            $contenido_aventura .= '</div>';;
            $contenido_aventura .= '</div>';
        }
        $contenido_aventura .= '</div>';
        $contenido_aventura .= '</div>';
        
        // Ajustes adicionales
        $contenido_aventura .= '<div class="configSection">';
        $contenido_aventura .= '<label class="configLabel">Ajustes adicionales:</label>';
        $contenido_aventura .= '<div style="display: flex; flex-direction: column; gap: 10px;">';
        $contenido_aventura .= '<label style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.85); font-size: 13px;">';
        $contenido_aventura .= '<input type="checkbox" id="aplicarBonificacion" checked>';
        $contenido_aventura .= 'Aplicar bonificación por calidad de posts';
        $contenido_aventura .= '</label>';
        $contenido_aventura .= '<label style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.85); font-size: 13px;">';
        $contenido_aventura .= '<input type="checkbox" id="verificarMinimos" checked>';
        $contenido_aventura .= 'Verificar posts mínimos antes de pagar';
        $contenido_aventura .= '</label>';
        $contenido_aventura .= '</div>';
        $contenido_aventura .= '</div>';
        
        // Nota informativa
        $contenido_aventura .= '<div style="margin-top: 15px; padding: 12px; background: rgba(52, 152, 219, 0.15); border: 1px solid rgba(52, 152, 219, 0.4); border-radius: 8px;">';
        $contenido_aventura .= '<p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 13px; line-height: 1.5;">';
        $contenido_aventura .= '<strong style="color: #5dade2;">ℹ️ Nota:</strong> Las recompensas se calcularán basándose en el tier seleccionado, ';
        $contenido_aventura .= 'los posts válidos de cada participante y los ajustes configurados. Solo se procesará el pago para los usuarios seleccionados.';
        $contenido_aventura .= '</p>';
        $contenido_aventura .= '</div>';
        
        $contenido_aventura .= '</div>';
        
        // Botón de pago
        $contenido_aventura .= '<button class="btnPagar" onclick="procesarPagoRecompensas(' . $tid . ', event)">PROCESAR PAGO DE RECOMPENSAS</button>';
        
    } else {
        $contenido_aventura = '<div class="errorMsg">';
        $contenido_aventura .= '<p>⚠️ No se encontró ninguna aventura con el TID especificado.</p>';
        $contenido_aventura .= '<p style="font-size: 14px; margin-top: 10px;">Por favor, verifica que el enlace sea correcto.</p>';
        $contenido_aventura .= '</div>';
    }

// ENDREGION PREPARAR CONTENIDO PARA LA PLANTILLA

add_breadcrumb("Pago de Recompensas");

eval("\$page = \"".$templates->get("op_staff_recompensasAventuras")."\";");
output_page($page);
