<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ficha_recalcular_pasivas.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid = $mybb->user['uid'];
$username = $mybb->user['username'];

if (!(is_mod($uid) || is_staff($uid))) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    return;
}

// Pasivas otorgadas por técnicas aprendidas (mybb_op_tec_aprendidas.tid).
// Disciplinas: ver op/entrenamiento_tecnicas.php. Razas/tribus: ver op/ficha_crear.php.
$TECNICA_PASIVA_MAP = [
    // Disciplinas (+5 por técnica de iniciado/maestro entrenada)
    'DCO001' => ['fuerza_pasiva' => 5], 'DEP001' => ['fuerza_pasiva' => 5], 'DGU001' => ['fuerza_pasiva' => 5],
    'DAQ002' => ['fuerza_pasiva' => 5], 'DAM001' => ['fuerza_pasiva' => 5],
    'DCO002' => ['resistencia_pasiva' => 5], 'DAT002' => ['resistencia_pasiva' => 5], 'DEC001' => ['resistencia_pasiva' => 5],
    'DAS002' => ['agilidad_pasiva' => 5], 'DAM002' => ['agilidad_pasiva' => 5],
    'DEP002' => ['destreza_pasiva' => 5], 'DGU002' => ['destreza_pasiva' => 5], 'DTC001' => ['destreza_pasiva' => 5],
    'DRT002' => ['destreza_pasiva' => 5], 'DAS001' => ['destreza_pasiva' => 5],
    'DTI001' => ['punteria_pasiva' => 5], 'DAQ001' => ['punteria_pasiva' => 5], 'DAT001' => ['punteria_pasiva' => 5], 'DPI001' => ['punteria_pasiva' => 5],
    'DTI002' => ['reflejos_pasiva' => 5], 'DTC002' => ['reflejos_pasiva' => 5], 'DPI002' => ['reflejos_pasiva' => 5], 'DEC002' => ['reflejos_pasiva' => 5],
    'DRT001' => ['voluntad_pasiva' => 5],

    // Razas / tribus (otorgadas como técnica al crear la ficha)
    'RAC001' => ['voluntad_pasiva' => 15],                                                    // Humano
    'TRI001' => ['fuerza_pasiva' => 5],                                                        // Humano - brazos
    'TRI002' => ['agilidad_pasiva' => 5],                                                      // Humano - piernas
    'TRI003' => ['punteria_pasiva' => 5],                                                      // Humano - tres
    'TRI004' => ['destreza_pasiva' => 5],                                                      // Humano - cuello
    'TRI005' => ['punteria_pasiva' => 5],                                                      // Humano - kuja
    'RAC006' => ['fuerza_pasiva' => 30, 'resistencia_pasiva' => 30, 'destreza_pasiva' => 10, 'agilidad_pasiva' => -10], // Gigante
    'TRI007' => ['fuerza_pasiva' => 5],                                                        // Gigante - ancestral
    'RAC003' => ['reflejos_pasiva' => 5, 'agilidad_pasiva' => 15, 'fuerza_pasiva' => 10],      // Tontatta
    'RAC008' => ['agilidad_pasiva' => 10, 'destreza_pasiva' => 10],                            // Skypian
    'RAC002' => ['fuerza_pasiva' => 15],                                                       // Gyojin
    'RAC009' => ['punteria_pasiva' => 15],                                                     // Ningyo
    'RAC005' => ['fuerza_pasiva' => 15, 'resistencia_pasiva' => 15, 'voluntad_pasiva' => 5],   // Oni
    'RAC004' => ['resistencia_pasiva' => 10, 'voluntad_pasiva' => 10, 'agilidad_pasiva' => 10],// Lunarian
    'RAC007' => ['reflejos_pasiva' => 10],                                                     // Mink
    'TRI010' => ['resistencia_pasiva' => 5],                                                   // Mink - piel
    'TRI009' => ['agilidad_pasiva' => 5],                                                      // Ningyo/Gyojin - anfibia
    'RAC010' => ['fuerza_pasiva' => 10, 'resistencia_pasiva' => 10, 'voluntad_pasiva' => 10],  // Buccaneers
    'RAC013' => ['fuerza_pasiva' => 10, 'resistencia_pasiva' => 10, 'voluntad_pasiva' => 5, 'agilidad_pasiva' => 5], // Kobito
    'RAC018' => ['voluntad_pasiva' => 10],                                                     // Jujin
    'RAC011' => ['fuerza_pasiva' => 10, 'resistencia_pasiva' => 10],                           // Wotan
    'RAC016' => ['punteria_pasiva' => 15, 'agilidad_pasiva' => 15],                            // Woko
    'RAC017' => ['resistencia_pasiva' => -5],                                                  // Solarian
    'RAC014' => ['agilidad_pasiva' => 10],                                                     // Komink
    'RAC015' => ['fuerza_pasiva' => 10, 'resistencia_pasiva' => 10],                           // Daimink
    'RAC019' => ['agilidad_pasiva' => 10, 'destreza_pasiva' => 10],                            // Donsudada
    'RAC012' => ['fuerza_pasiva' => 5, 'voluntad_pasiva' => 10],                               // Hafugyo
    'RAC020' => ['agilidad_pasiva' => 10, 'reflejos_pasiva' => 10, 'resistencia_pasiva' => 10],// Ravnos
    'RAC021' => ['voluntad_pasiva' => 10, 'fuerza_pasiva' => 10, 'resistencia_pasiva' => 10],  // Diablos
    'RAC022' => ['fuerza_pasiva' => 10, 'agilidad_pasiva' => 10],                              // Oceanian
];

// Caso especial: en la creación de ficha, Gigante+yeti hace un SET agilidad_pasiva='-5'
// que sobreescribe (no suma) el -10 ya aplicado por RAC006.
$TECNICA_PASIVA_OVERRIDE = [
    'TRI006' => ['agilidad_pasiva' => -5],
];

// Pasivas otorgadas por virtud/defecto aprobado (mybb_op_virtudes_usuarios.virtud_id), ver op/ficha_crear.php
$VIRTUD_PASIVA_MAP = [
    'D004' => ['destreza_pasiva' => -20],  // Amputación de Brazo
    'D005' => ['agilidad_pasiva' => -20],  // Amputación de Pierna
    'D029' => ['reflejos_pasiva' => -15],  // Tuerto
    'D046' => ['voluntad_pasiva' => -10],  // Pesimista
    'V054' => ['voluntad_pasiva' => 5],    // Optimista
];

$PASIVA_LABELS = [
    'fuerza_pasiva' => 'Fuerza Pasiva',
    'resistencia_pasiva' => 'Resistencia Pasiva',
    'destreza_pasiva' => 'Destreza Pasiva',
    'voluntad_pasiva' => 'Voluntad Pasiva',
    'punteria_pasiva' => 'Puntería Pasiva',
    'agilidad_pasiva' => 'Agilidad Pasiva',
    'reflejos_pasiva' => 'Reflejos Pasiva',
];

// Razas cuyo bonus de creación (+10 a un atributo elegido en el formulario, vía el campo "raza1")
// no queda registrado en ninguna tabla y por tanto no se puede reconstruir aquí.
$RAZAS_BONUS_NO_RECONSTRUIBLE = ['Jujin', 'Solarian', 'Humano', 'Mink', 'Komink', 'Daimink'];

function op_calcular_pasivas_esperadas($fid, $db, $TECNICA_PASIVA_MAP, $TECNICA_PASIVA_OVERRIDE, $VIRTUD_PASIVA_MAP, $PASIVA_LABELS) {
    $totales = array_fill_keys(array_keys($PASIVA_LABELS), 0);
    $detalle = [];

    $tids = [];
    $query_tecs = $db->query("SELECT tid FROM mybb_op_tec_aprendidas WHERE uid='".(int)$fid."'");
    while ($row = $db->fetch_array($query_tecs)) {
        $tids[] = $row['tid'];
    }

    foreach ($tids as $tid) {
        if (isset($TECNICA_PASIVA_MAP[$tid])) {
            foreach ($TECNICA_PASIVA_MAP[$tid] as $col => $delta) {
                $totales[$col] += $delta;
                $detalle[] = "Técnica $tid: " . $PASIVA_LABELS[$col] . " " . ($delta >= 0 ? "+$delta" : $delta);
            }
        }
    }

    foreach ($TECNICA_PASIVA_OVERRIDE as $tid => $cols) {
        if (in_array($tid, $tids)) {
            foreach ($cols as $col => $valor) {
                $totales[$col] = $valor;
                $detalle[] = "Técnica $tid: " . $PASIVA_LABELS[$col] . " fijado a $valor (sobreescribe bonus de raza)";
            }
        }
    }

    $virtudes = [];
    $query_virt = $db->query("SELECT virtud_id FROM mybb_op_virtudes_usuarios WHERE uid='".(int)$fid."'");
    while ($row = $db->fetch_array($query_virt)) {
        $virtudes[] = $row['virtud_id'];
    }

    foreach ($virtudes as $vid) {
        if (isset($VIRTUD_PASIVA_MAP[$vid])) {
            foreach ($VIRTUD_PASIVA_MAP[$vid] as $col => $delta) {
                $totales[$col] += $delta;
                $detalle[] = "Virtud/Defecto $vid: " . $PASIVA_LABELS[$col] . " " . ($delta >= 0 ? "+$delta" : $delta);
            }
        }
    }

    return ['totales' => $totales, 'detalle' => $detalle];
}

$user_fid = $mybb->get_input('fid');

$ficha_id_post = $_POST["ficha_id"];
$razon = $_POST["razon"];
$accion_post = $_POST["accion_post"];

if ($accion_post == 'aplicar_recalculo' && $ficha_id_post && $razon && (is_mod($uid) || is_staff($uid))) {
    $fid_int = (int)$ficha_id_post;

    $query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$fid_int'");
    $f_var = false;
    while ($f = $db->fetch_array($query_ficha)) {
        $f_var = $f;
    }

    if ($f_var) {
        $resultado = op_calcular_pasivas_esperadas($fid_int, $db, $TECNICA_PASIVA_MAP, $TECNICA_PASIVA_OVERRIDE, $VIRTUD_PASIVA_MAP, $PASIVA_LABELS);
        $totales = $resultado['totales'];

        $log = "Recálculo automático de pasivas para ficha de UID: $fid_int (" . $f_var['nombre'] . "):\n";
        $hubo_cambios = false;

        foreach ($totales as $col => $nuevo_valor) {
            $actual = (int)$f_var[$col];
            if ($actual !== (int)$nuevo_valor) {
                $log .= "-- De $actual a $nuevo_valor " . $PASIVA_LABELS[$col] . ".\n";
                $db->query("UPDATE `mybb_op_fichas` SET `$col`='".(int)$nuevo_valor."' WHERE `fid`='$fid_int'; ");
                $hubo_cambios = true;
            }
        }

        if ($hubo_cambios) {
            $db->query("
                INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES
                ('".(int)$uid."', '".$db->escape_string($username)."', '".$db->escape_string($razon)."', '".$db->escape_string($log)."');
            ");
            eval('$log_var = $log;');
        } else {
            eval('$log_var = "No había diferencias que aplicar: los valores pasivos ya coinciden con el cálculo.";');
        }

        $reload_js = "<script>window.location.href = '/op/staff/ficha_recalcular_pasivas.php?fid=$fid_int';</script>";
        eval('$reload_script = $reload_js;');
    }
}

if (is_mod($uid) || is_staff($uid)) {
    $ficha = null;
    $comparacion_filas = '';
    $detalle_html = '';
    $aviso_raza = '';

    if ($user_fid != '') {
        $fid_int = (int)$user_fid;
        $query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$fid_int'");
        $f = false;
        while ($row = $db->fetch_array($query_ficha)) {
            $f = $row;
        }

        if ($f) {
            $ficha = $f;

            $resultado = op_calcular_pasivas_esperadas($fid_int, $db, $TECNICA_PASIVA_MAP, $TECNICA_PASIVA_OVERRIDE, $VIRTUD_PASIVA_MAP, $PASIVA_LABELS);
            $totales = $resultado['totales'];

            foreach ($totales as $col => $nuevo_valor) {
                $actual = (int)$f[$col];
                $diferencia = $nuevo_valor - $actual;
                $estilo_diferencia = $diferencia == 0 ? '' : ($diferencia > 0 ? 'style="color:green;font-weight:bold;"' : 'style="color:red;font-weight:bold;"');
                $texto_diferencia = ($diferencia > 0 ? '+' : '') . $diferencia;
                $label = $PASIVA_LABELS[$col];

                $comparacion_filas .= "<tr><td>$label</td><td>$actual</td><td>$nuevo_valor</td><td $estilo_diferencia>$texto_diferencia</td></tr>";
            }

            if (!empty($resultado['detalle'])) {
                $detalle_html = '<li>' . implode('</li><li>', array_map('htmlspecialchars', $resultado['detalle'])) . '</li>';
            } else {
                $detalle_html = '<li>No se encontraron técnicas pasivas ni virtudes/defectos con bonus conocido.</li>';
            }

            if (in_array($f['raza'], $RAZAS_BONUS_NO_RECONSTRUIBLE)) {
                $raza_segura = htmlspecialchars($f['raza']);
                $aviso_raza = "Atención: la raza '$raza_segura' pudo recibir en la creación de ficha un bonus de +10 a un atributo elegido libremente en el formulario, que no quedó registrado en ninguna tabla. Ese bonus NO está incluido en este cálculo y se perdería si aplicas el recálculo. Revisa manualmente antes de confirmar.";
            }
        }
    }

    eval('$fid = $user_fid;');
    eval("\$page = \"".$templates->get("staff_ficha_recalcular_pasivas")."\";");
    output_page($page);
} else {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
}
