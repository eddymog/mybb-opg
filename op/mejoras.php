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
define('THIS_SCRIPT', 'mejoras.php');
require_once "./../global.php";
require "./../inc/config.php";
global $templates, $mybb;

$accion = $mybb->get_input("accion");

$user_uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$user_uid' ");

// if (!$g_is_staff) {
//     $mensaje_redireccion = "Esta página está en construcción, disculpen los inconvenietes.";
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


$ficha = null;

while ($q = $db->fetch_array($query_ficha)) {
    $ficha = $q;
}

$nikas = intval($ficha['nika']);
$puntosOficio = intval($ficha['puntos_oficio']);
$nivel = intval($ficha['nivel']);
$limite_nivel = intval($ficha['limite_nivel']);

$is_gyojin = $ficha['raza'] == 'Gyojin';
$is_rokushiki = $ficha['faccion'] == 'CipherPol' && $nivel >= 5;
$is_revo = $ficha['faccion'] == 'Revolucionario' && $nivel >= 5;

$has_full_haki = false; // V029
$has_full_akuma = false; // Camino Akuma
$has_sin_oficio = false; // D024
$has_estudioso = false; // V035
$has_polivalente = false; // V036
$has_erudito = false; // V028

// $has_full_haki_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$user_uid' AND virtud_id='V029'; "); 
$has_sin_oficio_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$user_uid' AND virtud_id='D024'; "); 
$has_estudioso_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$user_uid' AND virtud_id='V035'; "); 
$has_polivalente_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$user_uid' AND virtud_id='V036'; "); 
$has_erudito_query = $db->query(" SELECT * FROM `mybb_op_virtudes_usuarios` WHERE uid='$user_uid' AND virtud_id='V028'; "); 

// while ($q = $db->fetch_array($has_full_haki_query)) { $has_full_haki = true; }
while ($q = $db->fetch_array($has_sin_oficio_query)) { $has_sin_oficio = true; }
while ($q = $db->fetch_array($has_estudioso_query)) { $has_estudioso = true; }
while ($q = $db->fetch_array($has_polivalente_query)) { $has_polivalente = true; }
while ($q = $db->fetch_array($has_erudito_query)) { $has_erudito = true; }

if ($ficha['camino'] == 'Haki') { $has_full_haki = true; }
if ($ficha['camino'] == 'Akuma') { $has_full_akuma = true; }

function getBelicaJson($belica) {
    $belicas = '';

    // if ($belica == "Combatiente") {
    //     $belicas = '{ "sub": { "Acróbata": 0, "Artista Marcial": 0, "Vanguardia": 0 }, "nivel": 1 }';
    // } else if ($belica == "Espadachín") {
    //     $belicas = '{ "sub": { "Asesino": 0, "Samurai": 0, "Berserker": 0 }, "nivel": 1 }';       
    // } else if ($belica == "Contundente") {
    //     $belicas = '{ "sub": { "Bastión": 0, "Bárbaro": 0, "Demoledor": 0 }, "nivel": 1 }';       
    // } else if ($belica == "Tirador") {
    //     $belicas = '{ "sub": { "Arquero": 0, "Asaltante": 0, "Francotirador": 0 }, "nivel": 1 }';
    // } else if ($belica == "Especialista") {
    //     $belicas = '{ "sub": { "Músico": 0, "Pícaro": 0, "Diletante": 0 }, "nivel": 1 }';
    // } else if ($belica == "Artillero") {
    //     $belicas = '{ "sub": { "Balista": 0, "Bombardero": 0, "Juggernaut": 0 }, "nivel": 1 }';
    // } 
    
    if ($belica == "Escudero") {
        $belicas = '{ "sub": { "Vanguardia": 0, "Bastión": 0 }, "nivel": 1 }';
    } else if ($belica == "Artista Marcial") {
        $belicas = '{ "sub": { "Monje": 0, "Acróbata": 0 }, "nivel": 1 }';     
    } else if ($belica == "Combatiente") {
        $belicas = '{ "sub": { "Berserker": 0, "Campeón": 0 }, "nivel": 1 }';       
    } else if ($belica == "Artista") {
        $belicas = '{ "sub": { "Bardo": 0, "Trovador": 0 }, "nivel": 1 }';
    } else if ($belica == "Asesino") {
        $belicas = '{ "sub": { "Sombra": 0, "Verdugo": 0 }, "nivel": 1 }';
    } else if ($belica == "Guerrero") {
        $belicas = '{ "sub": { "Castigador": 0, "Warhammer": 0 }, "nivel": 1 }';
    } else if ($belica == "Espadachín") {
        $belicas = '{ "sub": { "Samurái": 0, "Mosquetero": 0 }, "nivel": 1 }';
    } else if ($belica == "Tecnicista") {
        $belicas = '{ "sub": { "Diletante": 0, "WeaponMaster": 0 }, "nivel": 1 }';
    } else if ($belica == "Artillero") {
        $belicas = '{ "sub": { "Destructor": 0, "Juggernaut": 0 }, "nivel": 1 }';
    } else if ($belica == "Arquero") {
        $belicas = '{ "sub": { "Ballestero": 0, "Cazador": 0 }, "nivel": 1 }';
    } else if ($belica == "Tirador") {
        $belicas = '{ "sub": { "Duelista": 0, "Francotirador": 0 }, "nivel": 1 }';
    } else if ($belica == "Pícaro") {
        $belicas = '{ "sub": { "Gambito": 0, "Trickster": 0 }, "nivel": 1 }';
    }

    return json_decode($belicas);
}

function getOficioJson($oficio) {
    $oficios = '';

    if ($oficio == "Artesano") {
        $oficios = '{ "sub": { "Herrero": 0, "Modista": 0 }, "nivel": 1 }';
    } else if ($oficio == "Médico") {
        $oficios = '{ "sub": { "Farmacólogo": 0, "Doctor": 0 }, "nivel": 1 }';
    } else if ($oficio == "Navegante") {
        $oficios = '{ "sub": { "Cartógrafo": 0, "Timonel": 0 }, "nivel": 1 }';
    } else if ($oficio == "Inventor") {
        $oficios = '{ "sub": { "Biólogo": 0, "Ingeniero": 0 }, "nivel": 1 }';
    } else if ($oficio == "Carpintero") {
        $oficios = '{ "sub": { "Astillero": 0, "Constructor": 0 }, "nivel": 1 }';
    } else if ($oficio == "Cocinero") {
        $oficios = '{ "sub": { "Chef": 0, "Aprovisionador": 0 }, "nivel": 1 }';
    } else if ($oficio == "Mercader") {
        $oficios = '{ "sub": { "Comerciante": 0, "Contrabandista": 0 }, "nivel": 1 }';
    } else if ($oficio == "Investigador") {
        $oficios = '{ "sub": { "Periodista": 0, "Arqueólogo": 0 }, "nivel": 1 }';
    } else if ($oficio == "Aventurero") {
        $oficios = '{ "sub": { "Cazador": 0, "Domador": 0 }, "nivel": 1 }';
    } else if ($oficio == "Recolector") {
        $oficios = '{ "sub": { "Agreste": 0, "Mayorista": 0 }, "nivel": 1 }';
    }

    return json_decode($oficios);
}

if ($accion == 'limite_nivel') {
    
    // $nikasCosto = 50;
    // if ($limite_nivel < 20) { $nikasCosto = 5 * ($limite_nivel - 9); }

    $nikasCosto = 50;
    if ($limite_nivel >= 20) { $nikasCosto = 5; }
    if ($limite_nivel >= 25) { $nikasCosto = 10; }
    if ($limite_nivel >= 30) { $nikasCosto = 15; }
    if ($limite_nivel >= 35) { $nikasCosto = 20; }
    if ($limite_nivel >= 40) { $nikasCosto = 25; }

    if ($nikas >= $nikasCosto) {
        $nikasNuevo = $nikas - $nikasCosto;
        $limiteNivelNuevo = intval($ficha['limite_nivel']) + 1;
        $db->query(" UPDATE `mybb_op_fichas` SET `limite_nivel`='$limiteNivelNuevo' WHERE `fid`='$user_uid'; ");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Límite Nivel]', 'nikas', $nikasNuevo);
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Nivel]', "Limite de Nivel: $limiteNivelNuevo: $nikas->$nikasNuevo (Gasto: $nikasCosto).");
        echo("<script>alert('¡Has aumentado tu límite de nivel a $limiteNivelNuevo!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }

}

// Empieza a requerir Nikas al 21 en lugar del 11, cuesta 5 Nikas cada nivel y cada 5 Niveles sube 5 Nikas el precio. (Del 21 al 25, 5 Nikas / del 26 al 30, 10 Nikas/ del 31 al 35, 15 Nikas, etc.)

if ($accion == 'kenbun') {
    
    $kenbun = intval($ficha['kenbun']);
    $kenbunNuevo = $kenbun + 1;
    $nikasCosto = 10000;
    
    if ($kenbun == 1 && ($nivel >= 15 || ($has_full_haki && $nivel >= 10))) { $nikasCosto = 10; if ($has_full_haki) { $nikasCosto = 0; } }
    if ($kenbun == 2 && ($nivel >= 20 || ($has_full_haki && $nivel >= 15))) { $nikasCosto = 15; }
    if ($kenbun == 3 && ($nivel >= 25 || ($has_full_haki && $nivel >= 20))) { $nikasCosto = 25; }
    if ($kenbun == 4 && ($nivel >= 30 || ($has_full_haki && $nivel >= 25))) { $nikasCosto = 40; }
    if ($kenbun == 5 && ($nivel >= 35 || ($has_full_haki && $nivel >= 30))) { $nikasCosto = 60; }
    if ($kenbun == 6 && ($nivel >= 40 || ($has_full_haki && $nivel >= 35))) { $nikasCosto = 150; }

    if ($nikas >= $nikasCosto && $kenbunNuevo >= 2 && $kenbunNuevo <= 7) {
        $nikasNuevo = $nikas - $nikasCosto;
        
        $db->query(" UPDATE `mybb_op_fichas` SET `kenbun`='$kenbunNuevo' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Haki]', "Kenbun: $kenbunNuevo: $nikas->$nikasNuevo (Gasto: $nikasCosto).");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Kenbun Mejora]', 'nikas', $nikasNuevo);
        echo("<script>alert('¡Has aumentado tu nivel a Kenbunshoku Haki! $nikasNuevo $nikas $nikasCosto');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'buso') {
    
    $buso = intval($ficha['buso']);
    $busoNuevo = $buso + 1;
    $nikasCosto = 10000;

    if ($buso == 1 && ($nivel >= 15 || ($has_full_haki && $nivel >= 10))) { $nikasCosto = 10; if ($has_full_haki) { $nikasCosto = 0; } }
    if ($buso == 2 && ($nivel >= 20 || ($has_full_haki && $nivel >= 15))) { $nikasCosto = 15; }
    if ($buso == 3 && ($nivel >= 25 || ($has_full_haki && $nivel >= 20))) { $nikasCosto = 25; }
    if ($buso == 4 && ($nivel >= 30 || ($has_full_haki && $nivel >= 25))) { $nikasCosto = 40; }
    if ($buso == 5 && ($nivel >= 35 || ($has_full_haki && $nivel >= 30))) { $nikasCosto = 60; }
    if ($buso == 6 && ($nivel >= 40 || ($has_full_haki && $nivel >= 35))) { $nikasCosto = 150; }

    if ($nikas >= $nikasCosto && $busoNuevo >= 2 && $busoNuevo <= 7) {
        $nikasNuevo = $nikas - $nikasCosto;
        
        $db->query(" UPDATE `mybb_op_fichas` SET `buso`='$busoNuevo' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Haki]', "Buso: $busoNuevo: $nikas->$nikasNuevo (Gasto: $nikasCosto).");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Buso Mejora]', 'nikas', $nikasNuevo);
        echo("<script>alert('¡Has aumentado tu nivel a Busoshoku Haki!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'hao') {

    $has_nivel = false;

    $hao = intval($ficha['hao']);
    $haoNuevo = $hao + 1;
    $nikasCosto = 10000;

    if ($hao == 1 && ($nivel >= 15 || ($has_full_haki && $nivel >= 10))) { $nikasCosto = 10; if ($has_full_haki) { $nikasCosto = 0; } }
    if ($hao == 2 && ($nivel >= 20 || ($has_full_haki && $nivel >= 15))) { $nikasCosto = 15; }
    if ($hao == 3 && ($nivel >= 25 || ($has_full_haki && $nivel >= 20))) { $nikasCosto = 25; }
    if ($hao == 4 && ($nivel >= 30 || ($has_full_haki && $nivel >= 25))) { $nikasCosto = 40; }
    if ($hao == 5 && ($nivel >= 35 || ($has_full_haki && $nivel >= 30))) { $nikasCosto = 60; }
    if ($hao == 6 && ($nivel >= 40 || ($has_full_haki && $nivel >= 35))) { $nikasCosto = 150; }

    if ($nikas >= $nikasCosto && $haoNuevo >= 2 && $haoNuevo <= 7) {
        $nikasNuevo = $nikas - $nikasCosto;
        
        $db->query(" UPDATE `mybb_op_fichas` SET `hao`='$haoNuevo' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Haki]', "Hao: $haoNuevo: $nikas->$nikasNuevo (Gasto: $nikasCosto).");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Hao Mejora]', 'nikas', $nikasNuevo);
        echo("<script>alert('¡Has aumentado tu nivel a Haoshoku Haki!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'control_akuma') {

    $dominio_akuma = intval($ficha['dominio_akuma']);
    $nikasCosto = 10000; // Valor por defecto alto

    // Costes CON camino de Akuma
    if ($has_full_akuma) {
        if ($dominio_akuma == 0) { $nikasCosto = 0; } // Domino básico -> Primera pasiva
        if ($dominio_akuma == 1) { $nikasCosto = 5; } // Primera pasiva -> Dominio intermedio
        if ($dominio_akuma == 2) { $nikasCosto = 15; } // Dominio intermedio -> Segunda pasiva
        if ($dominio_akuma == 3) { $nikasCosto = 20; } // Segunda pasiva -> Dominio Maestro
        if ($dominio_akuma == 4) { $nikasCosto = 30; } // Dominio Maestro -> Tercera pasiva 
        if ($dominio_akuma == 5) { $nikasCosto = 80; } // Tercera pasiva -> Despertar
    } else {
        // Costes SIN camino de Akuma (bloqueado nivel 6)
        if ($dominio_akuma == 0) { $nikasCosto = 5; } // Domino básico -> Primera pasiva
        if ($dominio_akuma == 1) { $nikasCosto = 10; } // Primera pasiva -> Dominio intermedio
        if ($dominio_akuma == 2) { $nikasCosto = 20; } // Dominio intermedio -> Segunda pasiva
        if ($dominio_akuma == 3) { $nikasCosto = 25; } // Segunda pasiva -> Dominio Maestro
        if ($dominio_akuma == 4) { $nikasCosto = 40; } // Dominio Maestro -> Tercera pasiva
        // Nivel 5 -> 6 bloqueado sin camino Akuma
    }

    if ($nikas >= $nikasCosto && $dominio_akuma >= 0 && $dominio_akuma <= 6) {
        $nikasNuevo = $nikas - $nikasCosto;
        $dominio_akuma_nuevo = $dominio_akuma + 1;

        $db->query(" UPDATE `mybb_op_fichas` SET `dominio_akuma`='$dominio_akuma_nuevo' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Haki]', "Dominio: $dominio_akuma_nuevo: $nikas->$nikasNuevo (Gasto: $nikasCosto).");

        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Dominio Akuma Mejora]', 'nikas', $nikasNuevo);

        echo("<script>alert('¡Has aumentado tu nivel a Dominio de Akuma!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'estilo') {
    $estilo1 = $ficha['estilo1'];
    $estilo2 = $ficha['estilo2'];
    $estilo3 = $ficha['estilo3'];
    $estilo4 = $ficha['estilo4'];
    $estilo = $mybb->get_input("estilo");
    $slot = $mybb->get_input("slot");

    if ($slot == 'estilo1' && $estilo1 == 'no_bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo1`='$estilo' WHERE `fid`='$user_uid'; ");
        echo("<script>alert('Estilo $estilo - Asignado');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
        return;
    } else if ($slot == 'estilo2' && $estilo2 == 'no_bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo2`='$estilo' WHERE `fid`='$user_uid'; ");
        echo("<script>alert('Estilo $estilo - Asignado');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
        return;
    } else if ($slot == 'estilo3' && $estilo3 == 'no_bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo3`='$estilo' WHERE `fid`='$user_uid'; ");
        echo("<script>alert('Estilo $estilo - Asignado');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
        return;
    } else if ($slot == 'estilo4' && $estilo4 == 'no_bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo4`='$estilo' WHERE `fid`='$user_uid'; ");
        echo("<script>alert('Estilo $estilo - Asignado');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
        return;
    }
    return;
}

if ($accion == 'estilo1_desbloquear') {
    if ($nivel >= 8 && $ficha['estilo1'] == 'bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo1`='no_bloqueado' WHERE `fid`='$user_uid'; ");
        echo("<script>alert('¡Desbloqueaste el acceso al primer estilo!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    } else {
        echo("<script>alert('No desbloqueaste. Error.');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'estilo2_desbloquear') {
    $nikasNuevo = $nikas - 25;
    if ($nikasNuevo >= 0 && $nivel >= 16 && $ficha['estilo2'] == 'bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo2`='no_bloqueado' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Estilo 2]', "$nikas->$nikasNuevo (Gasto: 25).");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Estilo 2 Mejora]', 'nikas', $nikasNuevo);
        echo("<script>alert('¡Desbloqueaste el acceso al segundo estilo!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    } else {
        echo("<script>alert('No desbloqueaste. Error.');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'estilo3_desbloquear') {
    $nikasNuevo = $nikas - 50;
    if ($nikasNuevo >= 0 && $nivel >= 25 && $ficha['estilo3'] == 'bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo3`='no_bloqueado' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Estilo 3]', "$nikas->$nikasNuevo (Gasto: 50).");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Estilo 3 Mejora]', 'nikas', $nikasNuevo);
        echo("<script>alert('¡Desbloqueaste el acceso al tercer estilo!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    } else {
        echo("<script>alert('No desbloqueaste. Error.');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

if ($accion == 'estilo4_desbloquear') {
    $nikasNuevo = $nikas - 75;
    if ($nikasNuevo >= 0 && $nivel >= 35 && $ficha['estilo4'] == 'bloqueado') {
        $db->query(" UPDATE `mybb_op_fichas` SET `estilo4`='no_bloqueado' WHERE `fid`='$user_uid'; ");
        log_audit($user_uid, $username, $user_uid, '[Mejoras][Estilo 4]', "$nikas->$nikasNuevo (Gasto: 75).");
        log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Estilo 4 Mejora]', 'nikas', $nikasNuevo);
        echo("<script>alert('¡Desbloqueaste el acceso al cuarto estilo!');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    } else {
        echo("<script>alert('No desbloqueaste. Error.');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
    }
}

// if ($accion == 'oficio1') {

//     $oficio1 = $ficha['oficio1'];
//     $oficios = json_decode($ficha['oficios']);
//     $oficios->{$oficio1}->{'nivel'} = 2;
//     $oficios = json_encode($oficios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

//     $nuevosPuntosOficio = intval($ficha['puntos_oficio']) - 1000;

//     $db->query(" UPDATE `mybb_op_fichas` SET `oficios`='$oficios', `puntos_oficio`='$nuevosPuntosOficio' WHERE `fid`='$user_uid'; ");

// }

if ($accion == 'oficio') {
    $puntosOficioNuevo = $puntosOficio;
    $nikasNuevo = $nikas;

    $oficioNumber = $mybb->get_input('oficioNumber'); 
    $oficio = $mybb->get_input("oficio");
    $oficios = json_decode($ficha['oficios']);
    $textoSubir = "";
    
    if (isset($oficios->{$oficio})) {
        $puntosOficioNuevo = $puntosOficio - 1000;
        $oficios->{$oficio}->{'nivel'} = 2;
        if (isset($oficios->{$oficio}->{'sub'}) && is_array($oficios->{$oficio}->{'sub'})) {
            $oficios->{$oficio}->{'sub'} = (object)$oficios->{$oficio}->{'sub'};
        }
        $textoSubir = "¡Has subido {$oficio} a nivel 2, crack!";
    } else {
        $oficios->{$oficio} = getOficioJson($oficio); 
        $textoSubir = "¡Has aprendido el nuevo oficio {$oficio}, crack!";
    }

    $oficios = json_encode($oficios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    log_audit($user_uid, $username, $user_uid, '[Mejoras][Oficio]', "$textoSubir; $oficioNumber=$oficio, puntos_oficio=$puntosOficioNuevo ");
    // echo (" UPDATE `mybb_op_fichas` SET `oficios`='$oficios', `$oficioNumber`='$oficio', `puntos_oficio`='$puntosOficioNuevo', `nika`='$nikasNuevo' WHERE `fid`='$user_uid'; ");
    $db->query(" UPDATE `mybb_op_fichas` SET `oficios`='$oficios', `$oficioNumber`='$oficio', `puntos_oficio`='$puntosOficioNuevo' WHERE `fid`='$user_uid'; ");

    log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Oficio Mejora]', 'puntos_oficio', $puntosOficioNuevo);
    
    echo("<script>alert('$textoSubir');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
}

// if ($accion == 'belica') { 
//     // Falta agregar nivel
//     $nikasNuevo = $nikas;

//     $belicaNumber = $mybb->get_input('belicaNumber');
//     $belica = $mybb->get_input("belica"); 
//     $belicas = json_decode($ficha['belicas']);
//     $textoSubir = "";

//     if (isset($belicas->{$belica})) { 
//         if ($belicaNumber == 'belica2') { $nikasNuevo = $nikas - 10; }
//         if ($belicaNumber == 'belica3') { $nikasNuevo = $nikas - 20; }
//         if ($belicaNumber == 'belica4') { $nikasNuevo = $nikas - 35; }
//         if ($belicaNumber == 'belica5') { $nikasNuevo = $nikas - 60; }
//         if ($belicaNumber == 'belica6') { $nikasNuevo = $nikas - 75; }

//         $belicas->{$belica}->{'nivel'} = 2; 

//         $textoSubir = "¡Has subido {$belica} a nivel 2, crack!";
//     } else { 
//         if ($belicaNumber == 'belica2') { $nikasNuevo = $nikas - 5; }
//         if ($belicaNumber == 'belica3') { $nikasNuevo = $nikas - 10; }
//         if ($belicaNumber == 'belica4') { $nikasNuevo = $nikas - 20; }
//         if ($belicaNumber == 'belica5') { $nikasNuevo = $nikas - 40; }
//         if ($belicaNumber == 'belica6') { $nikasNuevo = $nikas - 50; }

//         $belicas->{$belica} = getBelicaJson($belica); 
//         $textoSubir = "¡Has aprendido la nueva disciplina {$belica}, crack!";
//     }
//     $gasto = $nikas - $nikasNuevo;
//     $belicas = json_encode($belicas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
//     $db->query(" UPDATE `mybb_op_fichas` SET `belicas`='$belicas', `$belicaNumber`='$belica', `nika`='$nikasNuevo' WHERE `fid`='$user_uid'; ");
//     log_audit($user_uid, $username, $user_uid, '[Mejoras][Disciplina]', "$textoSubir; $nikas->$nikasNuevo (Gasto: $gasto).");
//     log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Belica Mejora]', 'nikas', $nikasNuevo);
//     echo("<script>alert('$textoSubir');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
// }

if ($accion == 'belica') { 
    // Falta agregar nivel
    $nikasNuevo = $nikas;

    $belicaNumber = $mybb->get_input('belicaNumber');
    $belica = $mybb->get_input("belica"); 
    $belicas = json_decode($ficha['belicas']);
    $textoSubir = "";

    if ($belicaNumber == 'belica2') { $nikasNuevo = $nikas - 10; }
    if ($belicaNumber == 'belica3') { $nikasNuevo = $nikas - 20; }
    if ($belicaNumber == 'belica4') { $nikasNuevo = $nikas - 35; }
    if ($belicaNumber == 'belica5') { $nikasNuevo = $nikas - 50; }
    if ($belicaNumber == 'belica6') { $nikasNuevo = $nikas - 65; }
    if ($belicaNumber == 'belica7') { $nikasNuevo = $nikas - 80; }
    if ($belicaNumber == 'belica8') { $nikasNuevo = $nikas - 95; }
    if ($belicaNumber == 'belica9') { $nikasNuevo = $nikas - 110; }
    if ($belicaNumber == 'belica10') { $nikasNuevo = $nikas - 125; }
    if ($belicaNumber == 'belica11') { $nikasNuevo = $nikas - 140; }
    if ($belicaNumber == 'belica12') { $nikasNuevo = $nikas - 155; }

    $belicas->{$belica} = getBelicaJson($belica); 
    $textoSubir = "¡Has aprendido la nueva disciplina {$belica}, crack!";
    
    $gasto = $nikas - $nikasNuevo;
    $belicas = json_encode($belicas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $db->query(" UPDATE `mybb_op_fichas` SET `belicas`='$belicas', `$belicaNumber`='$belica' WHERE `fid`='$user_uid'; ");
    log_audit($user_uid, $username, $user_uid, '[Mejoras][Disciplina]', "$textoSubir; $nikas->$nikasNuevo (Gasto: $gasto).");
    log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Belica Mejora]', 'nikas', $nikasNuevo);
    echo("<script>alert('$textoSubir');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
}

/* oficio_espe */
if ($accion == 'oficio_espe') {  
    // Falta agregar nivel
    $puntosOficioNuevo = $puntosOficio;
    $nikasNuevo = $nikas;

    $espe = $mybb->get_input('espe'); 
    $espeNumber = $mybb->get_input('espeNumber');     // espe1
    $oficioNumber = $mybb->get_input('oficioNumber'); // oficio1

    $oficio = $ficha[$oficioNumber];
    $oficios = json_decode($ficha['oficios']);

    if (isset($oficios->{$oficio}->{$espeNumber})) { 
        $espeNivel = $oficios->{$oficio}->{'sub'}->{$espe};

        if ($espeNivel == 1) {
            $nikasNuevo = $nikas - 25;
            $puntosOficioNuevo = $puntosOficio - 3500;
            $oficios->{$oficio}->{'sub'}->{$espe} = 2;
        } else if ($espeNivel == 2) {
            $nikasNuevo = $nikas - 50;
            $puntosOficioNuevo = $puntosOficio - 5000;
            $oficios->{$oficio}->{'sub'}->{$espe} = 3;
        }

        $textoSubir = "¡Has subido de nivel la nueva especialización {$espe}, crack!";
    } else { 

        $nikasNuevo = $nikas - 10;
        $puntosOficioNuevo = $puntosOficio - 2000;

        $oficios->{$oficio}->{'sub'}->{$espe} = 1;
        $oficios->{$oficio}->{$espeNumber} = $espe;

        $textoSubir = "¡Has elegido la nueva especialización {$espe}, crack!";
    } 

    $gasto = $nikas - $nikasNuevo;
    $oficios = json_encode($oficios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $db->query(" UPDATE `mybb_op_fichas` SET `oficios`='$oficios', `$oficioNumber`='$oficio', `puntos_oficio`='$puntosOficioNuevo' WHERE `fid`='$user_uid'; ");
    log_audit($user_uid, $username, $user_uid, '[Mejoras][Oficio Espe]', "$textoSubir; $nikas->$nikasNuevo (Gasto: $gasto); $puntosOficio->$puntosOficioNuevo; ");
    log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Oficio Espe Mejora]', 'puntos_oficio', $puntosOficioNuevo);
    echo("<script>alert('$textoSubir');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");

}  


/* belica_espe */
if ($accion == 'belica_espe') {
    // Falta agregar nivel
    $nikasNuevo = $nikas;

    $espe = $mybb->get_input('espe'); 
    $espeNumber = $mybb->get_input('espeNumber');     // espe1
    $belicaNumber = $mybb->get_input('belicaNumber'); // belica1

    $belica = $ficha[$belicaNumber];
    $belicas = json_decode($ficha['belicas']);

    if (isset($belicas->{$belica}->{$espeNumber})) { 
        $espeNivel = $belicas->{$belica}->{'sub'}->{$espe};

        if ($espeNivel == 1) {

            if ($belicaNumber == 'belica1' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 45; }
            if ($belicaNumber == 'belica2' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 60; }
            if ($belicaNumber == 'belica3' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 75; }
            if ($belicaNumber == 'belica4' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 100; }
            if ($belicaNumber == 'belica5' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 125; }
            if ($belicaNumber == 'belica6' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 150; }
            if ($belicaNumber == 'belica7' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 175; }
            if ($belicaNumber == 'belica8' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 200; }
            if ($belicaNumber == 'belica9' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 225; }
            if ($belicaNumber == 'belica10' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 250; }
            if ($belicaNumber == 'belica11' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 275; }
            if ($belicaNumber == 'belica12' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 300; }
    
            if ($belicaNumber == 'belica1' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 45; }
            if ($belicaNumber == 'belica2' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 60; }
            if ($belicaNumber == 'belica3' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 75; }
            if ($belicaNumber == 'belica4' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 100; }
            if ($belicaNumber == 'belica5' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 125; }
            if ($belicaNumber == 'belica6' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 150; }
            if ($belicaNumber == 'belica7' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 175; }
            if ($belicaNumber == 'belica8' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 200; }
            if ($belicaNumber == 'belica9' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 225; }
            if ($belicaNumber == 'belica10' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 250; }
            if ($belicaNumber == 'belica11' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 275; }
            if ($belicaNumber == 'belica12' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 300; }

            $belicas->{$belica}->{'sub'}->{$espe} = 2;
        } 
        $textoSubir = "¡Has subido de nivel el camino {$espe}, crack!";
    } else {

        if ($belicaNumber == 'belica1' && $espeNumber == 'espe1') { $nikasNuevo = $nikas; }
        if ($belicaNumber == 'belica2' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 20; }
        if ($belicaNumber == 'belica3' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 30; }
        if ($belicaNumber == 'belica4' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 45; }
        if ($belicaNumber == 'belica5' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 60; }
        if ($belicaNumber == 'belica6' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 75; }
        if ($belicaNumber == 'belica7' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 90; }
        if ($belicaNumber == 'belica8' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 105; }
        if ($belicaNumber == 'belica9' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 120; }
        if ($belicaNumber == 'belica10' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 135; }
        if ($belicaNumber == 'belica11' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 150; }
        if ($belicaNumber == 'belica12' && $espeNumber == 'espe1') { $nikasNuevo = $nikas - 165; }

        if ($belicaNumber == 'belica1' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 15; }
        if ($belicaNumber == 'belica2' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 30; }
        if ($belicaNumber == 'belica3' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 40; }
        if ($belicaNumber == 'belica4' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 60; }
        if ($belicaNumber == 'belica5' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 75; }
        if ($belicaNumber == 'belica6' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 90; }
        if ($belicaNumber == 'belica7' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 105; }
        if ($belicaNumber == 'belica8' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 120; }
        if ($belicaNumber == 'belica9' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 135; }
        if ($belicaNumber == 'belica10' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 150; }
        if ($belicaNumber == 'belica11' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 165; }
        if ($belicaNumber == 'belica12' && $espeNumber == 'espe2') { $nikasNuevo = $nikas - 180; }


        $belicas->{$belica}->{'sub'}->{$espe} = 1;
        $belicas->{$belica}->{"$espeNumber"} = $espe;

        $textoSubir = "¡Has elegido el nuevo camino {$espe}, crack!";
    }

    $gasto = $nikas - $nikasNuevo;
    $belicas = json_encode($belicas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $db->query(" UPDATE `mybb_op_fichas` SET `belicas`='$belicas', `$belicaNumber`='$belica' WHERE `fid`='$user_uid'; ");
    log_audit($user_uid, $username, $user_uid, '[Mejoras][Camino]', "$textoSubir; $nikas->$nikasNuevo (Gasto: $gasto); ");
    log_audit_currency($user_uid, $username, $user_uid, '[Mejoras][Belica Espe Mejora]', 'nikas', $nikasNuevo);
    echo("<script>alert('$textoSubir');window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$user_uid';</script>");
}

if ($ficha != null) {

    $belica1 = $ficha['belica1'];
    $belicas = json_decode($ficha['belicas']);

    $oficio1 = $ficha['oficio1'];
    $oficios = json_decode($ficha['oficios']);

    $nivelOficio1 = $oficios->{$oficio1}->{'nivel'};
    $nivelBelica1 = $belicas->{$belica1}->{'nivel'};

    eval('$op_mejoras_script1 .= "'.$templates->get('op_mejoras_script').'";');
    eval('$op_mejoras_script2 .= "'.$templates->get('op_mejoras_script2').'";');
    eval('$op_mejoras_script3 .= "'.$templates->get('op_mejoras_script3').'";');

    eval("\$page = \"".$templates->get("op_mejoras")."\";");
    output_page($page);
}

