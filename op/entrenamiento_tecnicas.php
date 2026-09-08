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
define('THIS_SCRIPT', 'entrenamiento_tecnicas.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once MYBB_ROOT."/inc/plugins/lib/spoiler.php";

// === DEBUG TEMPORAL ===
// ini_set('display_errors', '1');           // mostrar en pantalla
// ini_set('display_startup_errors', '1');
// ini_set('log_errors', '1');               // log a fichero
// ini_set('error_log', __DIR__.'/entrenamiento_debug.log');  // archivo local
// error_reporting(E_ALL);

// set_error_handler(function($errno, $errstr, $errfile, $errline){
//     error_log("PHP[$errno] $errstr en $errfile:$errline");
//     return false; // deja que PHP siga su flujo normal
// });
// set_exception_handler(function($ex){
//     error_log("EXCEPTION: ".$ex->getMessage()." @ ".$ex->getFile().":".$ex->getLine()."\n".$ex->getTraceAsString());
// });
// register_shutdown_function(function(){
//     $e = error_get_last();
//     if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])){
//         error_log("FATAL: {$e['message']} @ {$e['file']}:{$e['line']}");
//         // Opcional: mostrar algo legible en pantalla durante debug:
//         echo "<pre style='white-space:pre-wrap;color:#c00;background:#fee;padding:10px;border:1px solid #caa'>FATAL: {$e['message']} @ {$e['file']}:{$e['line']}</pre>";
//     }
// });

if ($mybb->request_method == 'post') {
    // error_log("[ENTRENAR][RAW POST] ".json_encode($_POST));
    $tecnica_id = $mybb->get_input('tid', MyBB::INPUT_STRING);
    // error_log("[ENTRENAR] POST tid={$tecnica_id} uid={$uid}");
}

// === FIN DEBUG TEMPORAL ===

global $templates, $mybb;

$uid = $mybb->user['uid'];
// if ($uid == '17') { $uid = '29'; }

$ficha = null;

// if ($uid != '10') {
//     $mensaje_redireccion = "Esta página está en construccion.";
//     eval("\$page = \"".$templates->get("op_redireccion")."\";");
//     output_page($page);
//     return;
// }

if ($g_ficha['muerto'] == '1') {
    $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}


$modo_vista_input = $mybb->get_input('modo_vista'); 
$modo_vista = ($modo_vista_input && ($g_is_staff) || $mybb->user['uid'] == $modo_vista_input);
if ($modo_vista) {
    $uid = $modo_vista_input;
}



$tecnica_id = $_POST["tid"];
$tid_en_curso = $_POST["tid_en_curso"];
$tid_completo = $_POST["tid_completo"];
$entrenamiento_en_curso = false;
$ficha = null;
$ficha_existe = false;
$aprobada_por = false;
$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas as f INNER JOIN mybb_users as u ON f.fid = u.uid WHERE f.fid='$uid' ");
$puntos_rol = null;
while ($f = $db->fetch_array($query_ficha)) {
    $aprobada_por = !in_array($f['aprobada_por'], array('sin_aprobar', 'pendiente_reset'), true); $ficha_existe = true; $ficha = $f; $puntos_rol = $f['newpoints'];
}

$reload_js = "<script>window.location.href = window.location.href;</script>";

$has_full_haki = false;
// $has_full_haki_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$uid' AND virtud_id='V029'; "); 
// while ($q = $db->fetch_array($has_full_haki_query)) { $has_full_haki = true; }
if ($ficha['camino'] == 'Haki') { $has_full_haki = true; }

if ($tid_completo) {
    $tiempo_iniciado = '';
    $tiempo_finaliza = '';
    $tecnica = null;
    $query_user_tecnica_entrenada = $db->query("SELECT * FROM mybb_op_tecnicas_usuarios WHERE uid='$uid'");
    while ($q = $db->fetch_array($query_user_tecnica_entrenada)) {
        $tiempo_iniciado = $q['tiempo_iniciado'];
        $tiempo_finaliza = $q['tiempo_finaliza'];
    }

    $db->query(" DELETE FROM mybb_op_tecnicas_usuarios WHERE uid='$uid' ");

    $query_tecnica = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE tid='$tid_completo' ");
    while ($t = $db->fetch_array($query_tecnica)) { $tecnica = $t; }

    $coste_pr = 0;

    $nombre = $ficha['nombre'];
    $old_pe = $ficha['puntos_estadistica'];
    $new_pe = intval($old_pe) + $recompensa;
    $new_pr = floatval($puntos_rol) - $coste_pr;

    // $db->query(" UPDATE `mybb_users` SET `newpoints`='$new_pr' WHERE `uid`='$uid'; ");
    $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES ('$tid_completo', '$uid'); ");
    $db->query(" 
        INSERT INTO `mybb_op_audit_entrenamiento_tecnicas` (`fid`, `nombre`, `tid`, `puntos_estadistica`, `pr`, `tiempo_iniciado`, `tiempo_finaliza`) VALUES 
        ('$uid', '$nombre', '$tid_completo', '$old_pe->$new_pe', '$puntos_rol->$new_pr', '$tiempo_iniciado', '$tiempo_finaliza');
    ");


    if ($tid_completo == 'DCO001' || $tid_completo == 'DEP001' || $tid_completo == 'DGU001' || $tid_completo == 'DAQ002' || $tid_completo == 'DAM001') {
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`=`fuerza_pasiva`+5 WHERE fid=$uid; ");
    }

    if ($tid_completo == 'DCO002' || $tid_completo == 'DAT002' || $tid_completo == 'DEC001') {
        $db->query(" UPDATE `mybb_op_fichas` SET `resistencia_pasiva`=`resistencia_pasiva`+5 WHERE fid=$uid; ");
    }

    if ($tid_completo == 'DAS002' || $tid_completo == 'DAM002') {
        $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`=`agilidad_pasiva`+5 WHERE fid=$uid; ");
    }

    if ($tid_completo == 'DEP002' || $tid_completo == 'DGU002' || $tid_completo == 'DTC001' || $tid_completo == 'DRT002' || $tid_completo == 'DAS001') {
        $db->query(" UPDATE `mybb_op_fichas` SET `destreza_pasiva`=`destreza_pasiva`+5 WHERE fid=$uid; ");
    }

    if ($tid_completo == 'DTI001' || $tid_completo == 'DAQ001' || $tid_completo == 'DAT001' || $tid_completo == 'DPI001') {
        $db->query(" UPDATE `mybb_op_fichas` SET `punteria_pasiva`=`punteria_pasiva`+5 WHERE fid=$uid; ");
    }

    if ($tid_completo == 'DTI002' || $tid_completo == 'DTC002' || $tid_completo == 'DPI002' || $tid_completo == 'DEC002') {
        $db->query(" UPDATE `mybb_op_fichas` SET `reflejos_pasiva`=`reflejos_pasiva`+5 WHERE fid=$uid; ");
    }

    if ($tid_completo == 'DRT001') {
        $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`=`voluntad_pasiva`+5 WHERE fid=$uid; ");
    }

    // if ($tid_completo == 'x') {
    //     $db->query(" UPDATE `mybb_op_fichas` SET `energia_pasiva`=`energia_pasiva`+125 WHERE fid=$uid; ");
    // }

    // if ($tid_completo == 'x') {
    //     $db->query(" UPDATE `mybb_op_fichas` SET `vitalidad_pasiva`=`vitalidad_pasiva`+250 WHERE fid=$uid; ");
    // }

    // if ($tid_completo == 'x') {
    //     $db->query(" UPDATE `mybb_op_fichas` SET `vitalidad_pasiva`=`vitalidad_pasiva`+100 WHERE fid=$uid; ");
    //     $db->query(" UPDATE `mybb_op_fichas` SET `energia_pasiva`=`energia_pasiva`+50 WHERE fid=$uid; ");
    // }

    // if ($tid_completo == 'x') {
    //     $db->query(" UPDATE `mybb_op_fichas` SET `vitalidad_pasiva`=`vitalidad_pasiva`+75 WHERE fid=$uid; ");
    //     $db->query(" UPDATE `mybb_op_fichas` SET `energia_pasiva`=`energia_pasiva`+75 WHERE fid=$uid; ");
    // }

    $log = "Entrenamiento para técnica ID: $tid_completo finalizado \n";
    eval('$log_var = $log;');
    eval('$reload_script = $reload_js;');
}

// cancelar mision
if ($tid_en_curso == 'cancel') {
    $db->query("
        DELETE FROM mybb_op_tecnicas_usuarios WHERE uid='$uid'
    ");
    eval('$reload_script = $reload_js;');
}

// post para entrenar técnica
if ($tecnica_id) {
    $nombre = $ficha['nombre'];
    $query_tecnicas_post = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE tid='$tecnica_id' ");

    $nuevo_tiempo = 0;
    while ($t = $db->fetch_array($query_tecnicas_post)) {

        $tier = $t['tier'];
        $tiempo_de_tecnica = 0;

        if ($tier == '1') {
            $tiempo_de_tecnica = 1 * 3600;
        } else if ($tier == '2') {
            $tiempo_de_tecnica = 4 * 3600;
        } else if ($tier == '3') {
            $tiempo_de_tecnica = 8 * 3600;
        } else if ($tier == '4') {
            $tiempo_de_tecnica = 12 * 3600;
        } else if ($tier == '5') {
            $tiempo_de_tecnica = 24 * 3600;
        } else if ($tier == '6') {
            $tiempo_de_tecnica = 36 * 3600;
        } else if ($tier == '7') {
            $tiempo_de_tecnica = 48 * 3600;
        } else if ($tier == '8') {
            $tiempo_de_tecnica = 60 * 3600;
        } else if ($tier == '9') {
            $tiempo_de_tecnica = 72 * 3600;
        } else if ($tier == '10') {
            $tiempo_de_tecnica = 100 * 3600;
        } else if ($tier == '11') {
            $tiempo_de_tecnica = 125 * 3600;
        } else if ($tier == '12') {
            $tiempo_de_tecnica = 150 * 3600;
        } else if ($tier == '13') {
            $tiempo_de_tecnica = 175 * 3600;
        } else if ($tier == '14') {
            $tiempo_de_tecnica = 200 * 3600;
        } else if ($tier == '15') {
            $tiempo_de_tecnica = 250 * 3600;
        } else if ($tier == 'SSS') {
            $tiempo_de_tecnica = 300 * 3600;
        }

        $tiempo_iniciado = time();
        $tiempo_finaliza = $tiempo_iniciado + $tiempo_de_tecnica;
        if ($tiempo_finaliza != 0) {
            $db->query(" 
                INSERT INTO `mybb_op_tecnicas_usuarios` (`tid`,`nombre`,`uid`,`tiempo_iniciado`,`tiempo_finaliza`,`duracion`) VALUES ('$tecnica_id','$nombre','$uid','$tiempo_iniciado','$tiempo_finaliza','$tiempo_de_tecnica');
            ");
        }
    }
    eval('$reload_script = $reload_js;');
}


if ($ficha_existe == true && $aprobada_por == true) {

    $query_user_entrenamientos = $db->query("
        SELECT * FROM mybb_op_tecnicas_usuarios WHERE uid='$uid'
    ");
    $tecnica_en_curso = false;
    $entrenamiento_completo = false;
    $tid = 0;
    $tiempo = 0;
    $tecnica = null;

    while ($e = $db->fetch_array($query_user_entrenamientos)) {
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
            } else if ($categoria == 'entrenamientoX1.5') {
                $efecto = 1.5;
            } else if ($categoria == 'entrenamientoX1.2') {
                $efecto = 1.2;
            }
        }

        $entrenamiento_duracion = intval($e['duracion']);
        $extra_time = $entrenamiento_duracion - ($entrenamiento_duracion * (1 / $efecto)); 
        $tiempo = intval($e['tiempo_finaliza'] - $extra_time) * 1000;
        $tid = $e['tid'];

        if (time() > (intval($e['tiempo_finaliza']) - $extra_time)) {
            $entrenamiento_completo = true;
        } else {
            $tecnica_en_curso = true;
        }
    }    

    if ($entrenamiento_completo || $tecnica_en_curso) {
        $query_tecnica = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE tid='$tid' ");
        while ($t = $db->fetch_array($query_tecnica)) { $tecnica = $t; }
    }

    $tecnica_card_html = '';
    $duracion_tecnica = intval($entrenamiento_duracion - $extra_time);
    if ($tecnica) {
        $tecnica['descripcion'] = nl2br($tecnica['descripcion']);
        $tecnica_card_html = create_technique_card($tecnica);
    }

    if ($tecnica_en_curso && !$modo_vista) {
        eval("\$page = \"".$templates->get("op_tecnica_en_curso2")."\";");
    } else if ($entrenamiento_completo) {

        if ($tecnica) {
            $tier = $tecnica['tier'];
        }

        eval("\$page = \"".$templates->get("op_tecnica_completa")."\";");
    } else {

        $nombrePersonaje = $ficha['nombre'];
        $nivel = intval($ficha['nivel']);
        $belica1 = '';
        $belica2 = '';
        $belica1_tier = 0;
        $belica2_tier = 0;
        $estilo1 = '';
        $estilo2 = '';
        $estilo3 = '';
        $estilo4 = '';

        $belica1_espe1 = '';
        $belica1_espe2 = '';

        $belica2_espe1 = ''; 
        $belica2_espe2 = ''; 

        $belica3_espe1 = ''; 
        $belica3_espe2 = ''; 
        
        $belica4_espe1 = '';
        $belica4_espe2 = '';
        
        $belica5_espe1 = '';
        $belica5_espe2 = '';
        
        $hao = intval($ficha['hao']);
        $kenbun = intval($ficha['kenbun']);
        $buso = intval($ficha['buso']);


        $nivel_tier = 1;

        if ($nivel >= 100) { $nivel_tier = 16; } // 16 = tier "SSS"
        else if ($nivel >= 90) { $nivel_tier = 15; }
        else if ($nivel >= 80) { $nivel_tier = 14; }
        else if ($nivel >= 70) { $nivel_tier = 13; }
        else if ($nivel >= 60) { $nivel_tier = 12; }
        else if ($nivel >= 50) { $nivel_tier = 11; }
        else if ($nivel >= 40) { $nivel_tier = 10; }
        else if ($nivel >= 35) { $nivel_tier = 9; }
        else if ($nivel >= 30) { $nivel_tier = 8; }
        else if ($nivel >= 25) { $nivel_tier = 7; }
        else if ($nivel >= 20) { $nivel_tier = 6; }
        else if ($nivel >= 16) { $nivel_tier = 5; }
        else if ($nivel >= 12) { $nivel_tier = 4; }
        else if ($nivel >= 8) { $nivel_tier = 3; }
        else if ($nivel >= 4) { $nivel_tier = 2; }

        $belicas = json_decode($ficha['belicas']);

        if ($ficha['belica1']) {
            $belica1 = $ficha['belica1'];
            $belica1_tier = 2;
        }

        if ($ficha['belica2']) {
            $belica2 = $ficha['belica2'];
            $belica2_tier = 2;
        }

        if ($ficha['belica3']) {
            $belica3 = $ficha['belica3'];
            $belica3_tier = 2;
        }

        if ($ficha['belica4']) {
            $belica4 = $ficha['belica4'];
            $belica4_tier = 2;
        }

        if ($ficha['belica5']) {
            $belica5 = $ficha['belica5'];
            $belica5_tier = 2;
        }

        if ($ficha['belica6']) {
            $belica6 = $ficha['belica6'];
            $belica6_tier = 2;
        }

        if ($ficha['belica7']) {
            $belica7 = $ficha['belica7'];
            $belica7_tier = 2;
        }

        if ($ficha['belica8']) {
            $belica8 = $ficha['belica8'];
            $belica8_tier = 2;
        }

        if ($ficha['belica9']) {
            $belica9 = $ficha['belica9'];
            $belica9_tier = 2;
        }

        if ($ficha['belica10']) {
            $belica10 = $ficha['belica10'];
            $belica10_tier = 2;
        }

        if ($ficha['belica11']) {
            $belica11 = $ficha['belica11'];
            $belica11_tier = 2;
        }

        if ($ficha['belica12']) {
            $belica12 = $ficha['belica12'];
            $belica12_tier = 2;
        }

        if (isset($belicas->{$belica1}->{'espe1'})) { 
            $belica1_espe1 = $belicas->{$belica1}->{'espe1'};
            $belica1_espe1_nivel = intval($belicas->{$belica1}->{'sub'}->{$belica1_espe1});
            if ($belica1_espe1_nivel == 1) {
                $belica1_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica1_espe1_nivel == 2) {
                $belica1_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica1}->{'espe2'})) { 
            $belica1_espe2 = $belicas->{$belica1}->{'espe2'};
            $belica1_espe2_nivel = intval($belicas->{$belica1}->{'sub'}->{$belica1_espe2});
            if ($belica1_espe2_nivel == 1) {
                $belica1_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica1_espe2_nivel == 2) {
                $belica1_espe2_tier = $nivel_tier;
            }
        }
        
        if (isset($belicas->{$belica2}->{'espe1'})) { 
            $belica2_espe1 = $belicas->{$belica2}->{'espe1'};
            $belica2_espe1_nivel = intval($belicas->{$belica2}->{'sub'}->{$belica2_espe1});
            if ($belica2_espe1_nivel == 1) {
                $belica2_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica2_espe1_nivel == 2) {
                $belica2_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica2}->{'espe2'})) { 
            $belica2_espe2 = $belicas->{$belica2}->{'espe2'};
            $belica2_espe2_nivel = intval($belicas->{$belica2}->{'sub'}->{$belica2_espe2});
            if ($belica2_espe2_nivel == 1) {
                $belica2_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica2_espe2_nivel == 2) {
                $belica2_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica3}->{'espe1'})) { 
            $belica3_espe1 = $belicas->{$belica3}->{'espe1'};
            $belica3_espe1_nivel = intval($belicas->{$belica3}->{'sub'}->{$belica3_espe1});
            if ($belica3_espe1_nivel == 1) {
                $belica3_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica3_espe1_nivel == 2) {
                $belica3_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica3}->{'espe2'})) { 
            $belica3_espe2 = $belicas->{$belica3}->{'espe2'};
            $belica3_espe2_nivel = intval($belicas->{$belica3}->{'sub'}->{$belica3_espe2});
            if ($belica3_espe2_nivel == 1) {
                $belica3_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica3_espe2_nivel == 2) {
                $belica3_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica4}->{'espe1'})) { 
            $belica4_espe1 = $belicas->{$belica4}->{'espe1'};
            $belica4_espe1_nivel = intval($belicas->{$belica4}->{'sub'}->{$belica4_espe1});
            if ($belica4_espe1_nivel == 1) {
                $belica4_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica4_espe1_nivel == 2) {
                $belica4_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica4}->{'espe2'})) { 
            $belica4_espe2 = $belicas->{$belica4}->{'espe2'};
            $belica4_espe2_nivel = intval($belicas->{$belica4}->{'sub'}->{$belica4_espe2});
            if ($belica4_espe2_nivel == 1) {
                $belica4_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica4_espe2_nivel == 2) {
                $belica4_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica5}->{'espe1'})) { 
            $belica5_espe1 = $belicas->{$belica5}->{'espe1'};
            $belica5_espe1_nivel = intval($belicas->{$belica5}->{'sub'}->{$belica5_espe1});
            if ($belica5_espe1_nivel == 1) {
                $belica5_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica5_espe1_nivel == 2) {
                $belica5_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica5}->{'espe2'})) { 
            $belica5_espe2 = $belicas->{$belica5}->{'espe2'};
            $belica5_espe2_nivel = intval($belicas->{$belica5}->{'sub'}->{$belica5_espe2});
            if ($belica5_espe2_nivel == 1) {
                $belica5_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica5_espe2_nivel == 2) {
                $belica5_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica6}->{'espe1'})) { 
            $belica6_espe1 = $belicas->{$belica6}->{'espe1'};
            $belica6_espe1_nivel = intval($belicas->{$belica6}->{'sub'}->{$belica6_espe1});
            if ($belica6_espe1_nivel == 1) {
                $belica6_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica6_espe1_nivel == 2) {
                $belica6_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica6}->{'espe2'})) { 
            $belica6_espe2 = $belicas->{$belica6}->{'espe2'};
            $belica6_espe2_nivel = intval($belicas->{$belica6}->{'sub'}->{$belica6_espe2});
            if ($belica6_espe2_nivel == 1) {
                $belica6_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica6_espe2_nivel == 2) {
                $belica6_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica7}->{'espe1'})) { 
            $belica7_espe1 = $belicas->{$belica7}->{'espe1'};
            $belica7_espe1_nivel = intval($belicas->{$belica7}->{'sub'}->{$belica7_espe1});
            if ($belica7_espe1_nivel == 1) {
                $belica7_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica7_espe1_nivel == 2) {
                $belica7_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica7}->{'espe2'})) { 
            $belica7_espe2 = $belicas->{$belica7}->{'espe2'};
            $belica7_espe2_nivel = intval($belicas->{$belica7}->{'sub'}->{$belica7_espe2});
            if ($belica7_espe2_nivel == 1) {
                $belica7_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica7_espe2_nivel == 2) {
                $belica7_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica8}->{'espe1'})) { 
            $belica8_espe1 = $belicas->{$belica8}->{'espe1'};
            $belica8_espe1_nivel = intval($belicas->{$belica8}->{'sub'}->{$belica8_espe1});
            if ($belica8_espe1_nivel == 1) {
                $belica8_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica8_espe1_nivel == 2) {
                $belica8_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica8}->{'espe2'})) { 
            $belica8_espe2 = $belicas->{$belica8}->{'espe2'};
            $belica8_espe2_nivel = intval($belicas->{$belica8}->{'sub'}->{$belica8_espe2});
            if ($belica8_espe2_nivel == 1) {
                $belica8_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica8_espe2_nivel == 2) {
                $belica8_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica9}->{'espe1'})) { 
            $belica9_espe1 = $belicas->{$belica9}->{'espe1'};
            $belica9_espe1_nivel = intval($belicas->{$belica9}->{'sub'}->{$belica9_espe1});
            if ($belica9_espe1_nivel == 1) {
                $belica9_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica9_espe1_nivel == 2) {
                $belica9_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica9}->{'espe2'})) { 
            $belica9_espe2 = $belicas->{$belica9}->{'espe2'};
            $belica9_espe2_nivel = intval($belicas->{$belica9}->{'sub'}->{$belica9_espe2});
            if ($belica9_espe2_nivel == 1) {
                $belica9_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica9_espe2_nivel == 2) {
                $belica9_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica10}->{'espe1'})) { 
            $belica10_espe1 = $belicas->{$belica10}->{'espe1'};
            $belica10_espe1_nivel = intval($belicas->{$belica10}->{'sub'}->{$belica10_espe1});
            if ($belica10_espe1_nivel == 1) {
                $belica10_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica10_espe1_nivel == 2) {
                $belica10_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica10}->{'espe2'})) { 
            $belica10_espe2 = $belicas->{$belica10}->{'espe2'};
            $belica10_espe2_nivel = intval($belicas->{$belica10}->{'sub'}->{$belica10_espe2});
            if ($belica10_espe2_nivel == 1) {
                $belica10_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica10_espe2_nivel == 2) {
                $belica10_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica11}->{'espe1'})) { 
            $belica11_espe1 = $belicas->{$belica11}->{'espe1'};
            $belica11_espe1_nivel = intval($belicas->{$belica11}->{'sub'}->{$belica11_espe1});
            if ($belica11_espe1_nivel == 1) {
                $belica11_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica11_espe1_nivel == 2) {
                $belica11_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica11}->{'espe2'})) { 
            $belica11_espe2 = $belicas->{$belica11}->{'espe2'};
            $belica11_espe2_nivel = intval($belicas->{$belica11}->{'sub'}->{$belica11_espe2});
            if ($belica11_espe2_nivel == 1) {
                $belica11_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica11_espe2_nivel == 2) {
                $belica11_espe2_tier = $nivel_tier;
            }
        }

        if (isset($belicas->{$belica12}->{'espe1'})) { 
            $belica12_espe1 = $belicas->{$belica12}->{'espe1'};
            $belica12_espe1_nivel = intval($belicas->{$belica12}->{'sub'}->{$belica12_espe1});
            if ($belica12_espe1_nivel == 1) {
                $belica12_espe1_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
                
            } else if ($belica12_espe1_nivel == 2) {
                $belica12_espe1_tier = $nivel_tier;
            }
        }
        if (isset($belicas->{$belica12}->{'espe2'})) { 
            $belica12_espe2 = $belicas->{$belica12}->{'espe2'};
            $belica12_espe2_nivel = intval($belicas->{$belica12}->{'sub'}->{$belica12_espe2});
            if ($belica12_espe2_nivel == 1) {
                $belica12_espe2_tier = $nivel_tier > 5 ? 5 : $nivel_tier;
            } else if ($belica12_espe2_nivel == 2) {
                $belica12_espe2_tier = $nivel_tier;
            }
        }

        // Valores base siempre definidos
        $belica1       = $ficha['belica1']       ?? '';
        $belica2       = $ficha['belica2']       ?? '';
        $belica3       = $ficha['belica3']       ?? '';
        $belica4       = $ficha['belica4']       ?? '';
        $belica5       = $ficha['belica5']       ?? '';
        $belica6       = $ficha['belica6']       ?? '';
        $belica7       = $ficha['belica7']       ?? '';
        $belica8       = $ficha['belica8']       ?? '';
        $belica9       = $ficha['belica9']       ?? '';
        $belica10      = $ficha['belica10']      ?? '';
        $belica11      = $ficha['belica11']      ?? '';
        $belica12      = $ficha['belica12']      ?? '';

        $belica1_tier = $belica1_tier ?? 0;
        $belica2_tier = $belica2_tier ?? 0;
        $belica3_tier = $belica3_tier ?? 0;
        $belica4_tier = $belica4_tier ?? 0;
        $belica5_tier = $belica5_tier ?? 0;
        $belica6_tier = $belica6_tier ?? 0;
        $belica7_tier = $belica7_tier ?? 0;
        $belica8_tier = $belica8_tier ?? 0;
        $belica9_tier = $belica9_tier ?? 0;
        $belica10_tier = $belica10_tier ?? 0;
        $belica11_tier = $belica11_tier ?? 0;
        $belica12_tier = $belica12_tier ?? 0;

        $belica1_espe1 = $belica1_espe1 ?? '';
        $belica1_espe2 = $belica1_espe2 ?? '';
        $belica2_espe1 = $belica2_espe1 ?? '';
        $belica2_espe2 = $belica2_espe2 ?? '';
        $belica3_espe1 = $belica3_espe1 ?? '';
        $belica3_espe2 = $belica3_espe2 ?? '';
        $belica4_espe1 = $belica4_espe1 ?? '';
        $belica4_espe2 = $belica4_espe2 ?? '';
        $belica5_espe1 = $belica5_espe1 ?? '';
        $belica5_espe2 = $belica5_espe2 ?? '';
        $belica6_espe1 = $belica6_espe1 ?? '';
        $belica6_espe2 = $belica6_espe2 ?? '';
        $belica7_espe1 = $belica7_espe1 ?? '';
        $belica7_espe2 = $belica7_espe2 ?? '';
        $belica8_espe1 = $belica8_espe1 ?? '';
        $belica8_espe2 = $belica8_espe2 ?? '';
        $belica9_espe1 = $belica9_espe1 ?? '';
        $belica9_espe2 = $belica9_espe2 ?? '';
        $belica10_espe1 = $belica10_espe1 ?? '';
        $belica10_espe2 = $belica10_espe2 ?? '';
        $belica11_espe1 = $belica11_espe1 ?? '';
        $belica11_espe2 = $belica11_espe2 ?? '';
        $belica12_espe1 = $belica12_espe1 ?? '';
        $belica12_espe2 = $belica12_espe2 ?? '';

        $belica1_espe1_tier = $belica1_espe1_tier ?? 0;
        $belica1_espe2_tier = $belica1_espe2_tier ?? 0;
        $belica2_espe1_tier = $belica2_espe1_tier ?? 0;
        $belica2_espe2_tier = $belica2_espe2_tier ?? 0;
        $belica3_espe1_tier = $belica3_espe1_tier ?? 0;
        $belica3_espe2_tier = $belica3_espe2_tier ?? 0;
        $belica4_espe1_tier = $belica4_espe1_tier ?? 0;
        $belica4_espe2_tier = $belica4_espe2_tier ?? 0;
        $belica5_espe1_tier = $belica5_espe1_tier ?? 0;
        $belica5_espe2_tier = $belica5_espe2_tier ?? 0;
        $belica6_espe1_tier = $belica6_espe1_tier ?? 0;
        $belica6_espe2_tier = $belica6_espe2_tier ?? 0;
        $belica7_espe1_tier = $belica7_espe1_tier ?? 0;
        $belica7_espe2_tier = $belica7_espe2_tier ?? 0;
        $belica8_espe1_tier = $belica8_espe1_tier ?? 0;
        $belica8_espe2_tier = $belica8_espe2_tier ?? 0;
        $belica9_espe1_tier = $belica9_espe1_tier ?? 0;
        $belica9_espe2_tier = $belica9_espe2_tier ?? 0;
        $belica10_espe1_tier = $belica10_espe1_tier ?? 0;
        $belica10_espe2_tier = $belica10_espe2_tier ?? 0;
        $belica11_espe1_tier = $belica11_espe1_tier ?? 0;
        $belica11_espe2_tier = $belica11_espe2_tier ?? 0;
        $belica12_espe1_tier = $belica12_espe1_tier ?? 0;
        $belica12_espe2_tier = $belica12_espe2_tier ?? 0;

        if ($ficha['estilo1'] != "bloqueado" && $ficha['estilo1'] != "no_bloqueado" && $ficha['estilo1'] != "Estilo Único") { $estilo1 = $ficha['estilo1']; }
        if ($ficha['estilo2'] != "bloqueado" && $ficha['estilo2'] != "no_bloqueado" && $ficha['estilo2'] != "Estilo Único") { $estilo2 = $ficha['estilo2']; }
        if ($ficha['estilo3'] != "bloqueado" && $ficha['estilo3'] != "no_bloqueado" && $ficha['estilo3'] != "Estilo Único") { $estilo3 = $ficha['estilo3']; }
        if ($ficha['estilo4'] != "bloqueado" && $ficha['estilo4'] != "no_bloqueado" && $ficha['estilo4'] != "Estilo Único") { $estilo4 = $ficha['estilo4']; }


        $belica1_query = $belica1 ? "(tier <= $belica1_tier AND rama LIKE '$belica1')" : "";
        $belica2_query = $belica2 ? "OR (tier <= $belica2_tier AND rama LIKE '$belica2')" : "";
        $belica3_query = $belica3 ? "OR (tier <= $belica3_tier AND rama LIKE '$belica3')" : "";
        $belica4_query = $belica4 ? "OR (tier <= $belica4_tier AND rama LIKE '$belica4')" : "";
        $belica5_query = $belica5 ? "OR (tier <= $belica5_tier AND rama LIKE '$belica5')" : "";
        $belica6_query = $belica6 ? "OR (tier <= $belica6_tier AND rama LIKE '$belica6')" : "";
        $belica7_query = $belica7 ? "OR (tier <= $belica7_tier AND rama LIKE '$belica7')" : "";
        $belica8_query = $belica8 ? "OR (tier <= $belica8_tier AND rama LIKE '$belica8')" : "";
        $belica9_query = $belica9 ? "OR (tier <= $belica9_tier AND rama LIKE '$belica9')" : "";
        $belica10_query = $belica10 ? "OR (tier <= $belica10_tier AND rama LIKE '$belica10')" : "";
        $belica11_query = $belica11 ? "OR (tier <= $belica11_tier AND rama LIKE '$belica11')" : "";
        $belica12_query = $belica12 ? "OR (tier <= $belica12_tier AND rama LIKE '$belica12')" : "";

        $belica1_espe1_query = $belica1_espe1 != "" ? "OR (tier <= $belica1_espe1_tier AND rama LIKE '%$belica1_espe1%')" : "";
        $belica1_espe2_query = $belica1_espe2 != "" ? "OR (tier <= $belica1_espe2_tier AND rama LIKE '%$belica1_espe2%')" : "";

        $belica2_espe1_query = $belica2_espe1 != "" ? "OR (tier <= $belica2_espe1_tier AND rama LIKE '%$belica2_espe1%')" : "";
        $belica2_espe2_query = $belica2_espe2 != "" ? "OR (tier <= $belica2_espe2_tier AND rama LIKE '%$belica2_espe2%')" : "";

        $belica3_espe1_query = $belica3_espe1 != "" ? "OR (tier <= $belica3_espe1_tier AND rama LIKE '%$belica3_espe1%')" : "";
        $belica3_espe2_query = $belica3_espe2 != "" ? "OR (tier <= $belica3_espe2_tier AND rama LIKE '%$belica3_espe2%')" : "";

        $belica4_espe1_query = $belica4_espe1 != "" ? "OR (tier <= $belica4_espe1_tier AND rama LIKE '%$belica4_espe1%')" : "";
        $belica4_espe2_query = $belica4_espe2 != "" ? "OR (tier <= $belica4_espe2_tier AND rama LIKE '%$belica4_espe2%')" : "";

        $belica5_espe1_query = $belica5_espe1 != "" ? "OR (tier <= $belica5_espe1_tier AND rama LIKE '%$belica5_espe1%')" : "";
        $belica5_espe2_query = $belica5_espe2 != "" ? "OR (tier <= $belica5_espe2_tier AND rama LIKE '%$belica5_espe2%')" : "";

        $belica6_espe1_query = $belica6_espe1 != "" ? "OR (tier <= $belica6_espe1_tier AND rama LIKE '%$belica6_espe1%')" : "";
        $belica6_espe2_query = $belica6_espe2 != "" ? "OR (tier <= $belica6_espe2_tier AND rama LIKE '%$belica6_espe2%')" : "";

        $belica7_espe1_query = $belica7_espe1 != "" ? "OR (tier <= $belica7_espe1_tier AND rama LIKE '%$belica7_espe1%')" : "";
        $belica7_espe2_query = $belica7_espe2 != "" ? "OR (tier <= $belica7_espe2_tier AND rama LIKE '%$belica7_espe2%')" : ""; 

        $belica8_espe1_query = $belica8_espe1 != "" ? "OR (tier <= $belica8_espe1_tier AND rama LIKE '%$belica8_espe1%')" : "";
        $belica8_espe2_query = $belica8_espe2 != "" ? "OR (tier <= $belica8_espe2_tier AND rama LIKE '%$belica8_espe2%')" : "";

        $belica9_espe1_query = $belica9_espe1 != "" ? "OR (tier <= $belica9_espe1_tier AND rama LIKE '%$belica9_espe1%')" : "";
        $belica9_espe2_query = $belica9_espe2 != "" ? "OR (tier <= $belica9_espe2_tier AND rama LIKE '%$belica9_espe2%')" : "";

        $belica10_espe1_query = $belica10_espe1 != "" ? "OR (tier <= $belica10_espe1_tier AND rama LIKE '%$belica10_espe1%')" : "";
        $belica10_espe2_query = $belica10_espe2 != "" ? "OR (tier <= $belica10_espe2_tier AND rama LIKE '%$belica10_espe2%')" : "";

        $belica11_espe1_query = $belica11_espe1 != "" ? "OR (tier <= $belica11_espe1_tier AND rama LIKE '%$belica11_espe1%')" : "";
        $belica11_espe2_query = $belica11_espe2 != "" ? "OR (tier <= $belica11_espe2_tier AND rama LIKE '%$belica11_espe2%')" : "";

        $belica12_espe1_query = $belica12_espe1 != "" ? "OR (tier <= $belica12_espe1_tier AND rama LIKE '%$belica12_espe1%')" : "";
        $belica12_espe2_query = $belica12_espe2 != "" ? "OR (tier <= $belica12_espe2_tier AND rama LIKE '%$belica12_espe2%')" : "";
        
        $estilo1_query = $estilo1 != "" ? "OR (tier <= $nivel_tier AND rama LIKE '%$estilo1%')" : "";
        $estilo2_query = $estilo2 != "" ? "OR (tier <= $nivel_tier AND rama LIKE '%$estilo2%')" : "";
        $estilo3_query = $estilo3 != "" ? "OR (tier <= $nivel_tier AND rama LIKE '%$estilo3%')" : "";
        $estilo4_query = $estilo4 != "" ? "OR (tier <= $nivel_tier AND rama LIKE '%$estilo4%')" : "";

        $hao_query = '';
        $kenbun_query = '';
        $buso_query = '';

        if ($hao >= 2) {
            $tier_hao = $hao + 1;

            if ($has_full_haki && $hao == 7) {
                $hao_query = "OR (tier <= 10 AND rama LIKE '%Haoshoku%')";
            } else {
                $hao_query = "OR (tier <= $tier_hao AND rama LIKE '%Haoshoku%' AND tid != 'HAOS904')";
            }
        }

        if ($kenbun >= 2) {
            $tier_kenbun = $kenbun + 1;

            if ($has_full_haki && $kenbun == 7) {
                $kenbun_query = "OR (tier <= 10 AND rama LIKE '%Kenbunshoku%')";
            } else {
                $kenbun_query = "OR (tier <= $tier_kenbun AND rama LIKE '%Kenbunshoku%' AND tid != 'KENB904')";
            }
        }

        if ($buso >= 2) {
            // has_full_haki
            $tier_buso = $buso + 1;

            if ($has_full_haki && $buso == 7) {
                $buso_query = "OR (tier <= 10 AND rama LIKE '%Busoshoku%')";
            } else {
                $buso_query = "OR (tier <= $tier_buso AND rama LIKE '%Busoshoku%' AND tid != 'BUSO904')";
            }


        }
        
        $query_tec_aprendidas = $db->query("
            SELECT * FROM `mybb_op_tecnicas` 
            INNER JOIN `mybb_op_tec_aprendidas` 
            ON `mybb_op_tecnicas`.`tid`=`mybb_op_tec_aprendidas`.`tid` 
            WHERE `mybb_op_tec_aprendidas`.`uid`='$uid'
            ORDER BY `mybb_op_tecnicas`.`tid`,`mybb_op_tecnicas`.`clase`
        ");

        $tec_aprendidas = array();
        $tec_aprendidas_html = array();
        $is_staff = isset($mybb->user['usergroup']) && in_array($mybb->user['usergroup'], array(3, 4, 6));
        
        // Array para rastrear qué disciplinas ya tienen técnicas pasivas aprendidas
        $pasivas_aprendidas = array();
        
        while ($tec_aprendida = $db->fetch_array($query_tec_aprendidas)) {
            // Filtrar técnicas de usuarios (tid que empiece por número)
            if (preg_match('/^[0-9]/', $tec_aprendida['tid'])) {
                continue;
            }
            
            // Detectar si es una técnica pasiva (formato D[XX]001 o D[XX]002)
            if (preg_match('/^D([A-Z]{2,3})00[12]$/', $tec_aprendida['tid'], $matches)) {
                $disciplina = $matches[1]; // Extraer código de disciplina (CO, EP, GU, etc.)
                $pasivas_aprendidas[$disciplina] = true;
            }
            
            $tec_aprendida['descripcion'] = nl2br($tec_aprendida['descripcion']);
            if ($tec_aprendida['estilo'] == 'Racial') {
                $key = $tec_aprendida['estilo'];
            } else {
                $key = $tec_aprendida['rama'];
            }

            if (!isset($tec_aprendidas[$key])) { $tec_aprendidas[$key] = []; }
            if (!isset($tec_aprendidas_html[$key])) { $tec_aprendidas_html[$key] = ''; }
            
            array_push($tec_aprendidas[$key], $tec_aprendida);
            $tec_aprendidas_html[$key] .= create_technique_card($tec_aprendida, $is_staff);
        }
        $tec_aprendidas_json = json_encode($tec_aprendidas);
        
        $query_tecs = $db->query("
            SELECT * FROM mybb_op_tecnicas as t1 
            WHERE 
            ($belica1_query $belica2_query $belica3_query $belica4_query $belica5_query $belica6_query $belica7_query $belica8_query $belica9_query $belica10_query $belica11_query $belica12_query
             $belica1_espe1_query $belica1_espe2_query
             $belica2_espe1_query $belica2_espe2_query 
             $belica3_espe1_query $belica3_espe2_query
             $belica4_espe1_query $belica4_espe2_query
             $belica5_espe1_query $belica5_espe2_query
             $belica6_espe1_query $belica6_espe2_query
             $belica7_espe1_query $belica7_espe2_query
             $belica8_espe1_query $belica8_espe2_query
             $belica9_espe1_query $belica9_espe2_query
             $belica10_espe1_query $belica10_espe2_query
             $belica11_espe1_query $belica11_espe2_query
             $belica12_espe1_query $belica12_espe2_query
             $estilo1_query $estilo2_query $estilo3_query $estilo4_query 
             $hao_query $kenbun_query $buso_query
             OR t1.tid IN (SELECT DISTINCT t3.tid from mybb_op_tec_para_aprender as t3 WHERE t3.uid='$uid')
            )
            AND (t1.tid NOT IN (SELECT DISTINCT t2.tid from mybb_op_tec_aprendidas as t2 WHERE t2.uid='$uid'))
            ORDER BY t1.tid ASC
        ");

        // echo("
        //     SELECT * FROM mybb_op_tecnicas as t1 
        //     WHERE 
        //     ($belica1_query $belica2_query $belica3_query $belica4_query $belica5_query $belica6_query $belica7_query $belica8_query $belica9_query $belica10_query $belica11_query $belica12_query
        //      $belica1_espe1_query $belica1_espe2_query
        //      $belica2_espe1_query $belica2_espe2_query 
        //      $belica3_espe1_query $belica3_espe2_query
        //      $belica4_espe1_query $belica4_espe2_query
        //      $belica5_espe1_query $belica5_espe2_query
        //      $belica6_espe1_query $belica6_espe2_query
        //      $belica7_espe1_query $belica7_espe2_query
        //      $belica8_espe1_query $belica8_espe2_query
        //      $belica9_espe1_query $belica9_espe2_query
        //      $belica10_espe1_query $belica10_espe2_query
        //      $belica11_espe1_query $belica11_espe2_query
        //      $belica12_espe1_query $belica12_espe2_query
        //      $estilo1_query $estilo2_query $estilo3_query $estilo4_query 
        //      $hao_query $kenbun_query $buso_query
        //      OR t1.tid IN (SELECT DISTINCT t3.tid from mybb_op_tec_para_aprender as t3 WHERE t3.uid='$uid')
        //     )
        //     AND (t1.tid NOT IN (SELECT DISTINCT t2.tid from mybb_op_tec_aprendidas as t2 WHERE t2.uid='$uid'))
        //     ORDER BY t1.tid ASC
        // ");

        $tecs = array();
        $tecs_html = array();
        while ($tec = $db->fetch_array($query_tecs)) {
            // Filtrar técnicas de usuarios (tid que empiece por número)
            if (preg_match('/^[0-9]/', $tec['tid'])) {
                continue;
            }
            
            // Filtrar técnicas pasivas si ya se tiene una de esa disciplina
            if (preg_match('/^D([A-Z]{2,3})00[12]$/', $tec['tid'], $matches)) {
                $disciplina = $matches[1];
                // Si ya tiene una pasiva de esta disciplina, omitir esta técnica
                // EXCEPTO si el nombre contiene "iniciado" o "maestro" (esas siempre se muestran mientras no estén aprendidas)
                if (isset($pasivas_aprendidas[$disciplina])) {
                    $nombre_lower = mb_strtolower($tec['nombre']);
                    if (strpos($nombre_lower, 'iniciado') === false && strpos($nombre_lower, 'iniciada') === false
                        && strpos($nombre_lower, 'maestro') === false && strpos($nombre_lower, 'maestra') === false) {
                        continue;
                    }
                }
            }

            $key = $tec['rama'];
        
            if (!isset($tecs[$key])) { $tecs[$key] = []; }
            if (!isset($tecs_html[$key])) { $tecs_html[$key] = ''; }
            
            array_push($tecs[$key], $tec);
            // Pasar true como tercer parámetro para mostrar el botón de entrenar
            $tecs_html[$key] .= create_technique_card($tec, $is_staff, true);
        }

        $belicas = json_encode($belicas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tecs_json = json_encode($tecs);
        
        // Generar spoilers para técnicas aprendidas y disponibles
        $container_style = get_technique_container_style();
        $container_style['open'] = true;
        
        $tec_aprendidas_spoilers = array();
        foreach ($tec_aprendidas_html as $rama => $html_content) {
            if (!empty($html_content)) {
                $tec_aprendidas_spoilers[$rama] = create_custom_spoiler($rama, $html_content, $container_style);
            }
        }
        
        $tecs_spoilers = array();
        foreach ($tecs_html as $rama => $html_content) {
            if (!empty($html_content)) {
                $tecs_spoilers[$rama] = create_custom_spoiler($rama, $html_content, $container_style);
            }
        }
        
        // Asegurarse de que siempre hay un JSON válido (aunque sea vacío)
        $tec_aprendidas_spoilers_json = !empty($tec_aprendidas_spoilers) ? json_encode($tec_aprendidas_spoilers) : '{}';
        $tecs_spoilers_json = !empty($tecs_spoilers) ? json_encode($tecs_spoilers) : '{}';
        
        // Asignar variables para el template
        // Variables con HTML de spoilers pregenerados
        $tec_aprendidas_spoilers_json_var = $tec_aprendidas_spoilers_json;
        $tecs_spoilers_json_var = $tecs_spoilers_json;
        
        // Variables con datos JSON originales (para compatibilidad con template antiguo)
        $tecs = $tecs_json;
        $tec_aprendidas = $tec_aprendidas_json;

        eval("\$page = \"".$templates->get("op_tecnicas_entrenar")."\";");
    }

    // if ($uid == '17') {
        output_page($page);
    // }

} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}
