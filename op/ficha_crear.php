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
define('THIS_SCRIPT', 'ficha_crear.php');
require_once "./../global.php";
require "./../inc/config.php";
global $templates, $mybb;

$fid = $mybb->user['uid'];
$nombre = addslashes($_POST["nombre"]);
$apodo = addslashes($_POST["apodo"]);
$altura = $_POST["altura"];
$peso = $_POST["peso"];
$sexo = $_POST["sexo"];
$edad = $_POST["edad"];

$oficio = $_POST["oficio"];
$maestria = $_POST["maestria"];
$objeto = $_POST["objeto"];

$apariencia = addslashes($_POST["apariencia"]);
$personalidad = addslashes($_POST["personalidad"]);
$extra = addslashes($_POST["extras"]);
$historia = addslashes($_POST["historia"]);
$virtudes = addslashes($_POST["virtudes"]);

$fisico_de_pj = addslashes($_POST["fisico_de_pj"]);
$origen_de_pj = addslashes($_POST["origen_de_pj"]);
$como_nos_conociste = addslashes($_POST["como_nos_conociste"]);

$raza = $_POST["raza"];
$subraza = $_POST["subraza"];
$temporada = $_POST["temporada"];
$faccion = $_POST["faccion"];

$raza1 = $_POST["raza1"];
$sentido1 = $_POST["sentido1"];
$sentido2 = $_POST["sentido2"];
$sentido3 = $_POST["sentido3"];
$tribu = $_POST["tribu"];

$camino = $_POST["camino"];
$dia_cumple = $_POST["dia_cumple"];

$belicas = null;
$oficios = null;

$agilidad_pasiva = 0;

if ($subraza != '') {
    $raza = $subraza;
}

$ficha_existe = false;
$f = null;
$query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$fid' ");
while ($f = $db->fetch_array($query_ficha)) { $ficha_existe = true; }

$has_info = $nombre && $edad && $altura && $sexo && $peso && $faccion && $raza && $apariencia && $personalidad && $historia && $fisico_de_pj && $origen_de_pj;

if ($has_info && $ficha_existe == false) {

    $sangre_numero = rand(1, 801);

    if ($sangre_numero >= 1 && $sangre_numero <= 100) { $sangre = 'A+'; }
    if ($sangre_numero >= 101 && $sangre_numero <= 200) { $sangre = 'A-'; }
    if ($sangre_numero >= 201 && $sangre_numero <= 300) { $sangre = 'B+'; }
    if ($sangre_numero >= 301 && $sangre_numero <= 400) { $sangre = 'B-'; }
    if ($sangre_numero >= 401 && $sangre_numero <= 500) { $sangre = 'O+'; }
    if ($sangre_numero >= 501 && $sangre_numero <= 600) { $sangre = 'O-'; }
    if ($sangre_numero >= 601 && $sangre_numero <= 700) { $sangre = 'AB+'; }
    if ($sangre_numero >= 701 && $sangre_numero <= 800) { $sangre = 'AB-'; }
    if ($sangre_numero == 801) { $sangre = 'Rhnull'; }

//    $virtudes_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim($virtudes));

    // if ($maestria == "Combatiente") {
    //     $belicas = '{ "Combatiente": { "sub": { "Acróbata": 0, "Artista Marcial": 0, "Vanguardia": 0 }, "nivel": 1 } }';
    // } else if ($maestria == "Espadachín") {
    //     $belicas = '{ "Espadachín": { "sub": { "Asesino": 0, "Samurai": 0, "Berserker": 0 }, "nivel": 1 } }';       
    // } else if ($maestria == "Contundente") {
    //     $belicas = '{ "Contundente": { "sub": { "Bastión": 0, "Bárbaro": 0, "Demoledor": 0 }, "nivel": 1 } }';       
    // } else if ($maestria == "Tirador") {
    //     $belicas = '{ "Tirador": { "sub": { "Arquero": 0, "Asaltante": 0, "Francotirador": 0 }, "nivel": 1 } }';
    // } else if ($maestria == "Especialista") {
    //     $belicas = '{ "Especialista": { "sub": { "Músico": 0, "Pícaro": 0, "Diletante": 0 }, "nivel": 1 } }';
    // } else if ($maestria == "Artillero") {
    //     $belicas = '{ "Artillero": { "sub": { "Balista": 0, "Bombardero": 0, "Juggernaut": 0 }, "nivel": 1 } }';
    // }

    if ($maestria == "Escudero") {
        $belicas = '{ "Escudero": { "sub": { "Vanguardia": 0, "Bastión": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Artista Marcial") {
        $belicas = '{ "Artista Marcial": { "sub": { "Monje": 0, "Acróbata": 0 }, "nivel": 1 } }';     
    } else if ($maestria == "Combatiente") {
        $belicas = '{ "Combatiente": { "sub": { "Berserker": 0, "Campeón": 0 }, "nivel": 1 } }';       
    } else if ($maestria == "Artista") {
        $belicas = '{ "Artista": { "sub": { "Bardo": 0, "Trovador": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Asesino") {
        $belicas = '{ "Asesino": { "sub": { "Sombra": 0, "Verdugo": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Guerrero") {
        $belicas = '{ "Guerrero": { "sub": { "Castigador": 0, "Warhammer": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Espadachín") {
        $belicas = '{ "Espadachín": { "sub": { "Samurái": 0, "Mosquetero": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Tecnicista") {
        $belicas = '{ "Tecnicista": { "sub": { "Diletante": 0, "WeaponMaster": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Artillero") {
        $belicas = '{ "Artillero": { "sub": { "Destructor": 0, "Juggernaut": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Arquero") {
        $belicas = '{ "Arquero": { "sub": { "Ballestero": 0, "Cazador": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Tirador") {
        $belicas = '{ "Tirador": { "sub": { "Duelista": 0, "Francotirador": 0 }, "nivel": 1 } }';
    } else if ($maestria == "Pícaro") {
        $belicas = '{ "Pícaro": { "sub": { "Gambito": 0, "Trickster": 0 }, "nivel": 1 } }';
    }

    // Escudero (Vanguardia, Bastion) - Marcial (Monje, Acrobata) - Combatiente (Berserker, Campeon) - Musico (Bardo, Trovador) - Asesino (Sombra, Verdugo)
    // Guerrero (Barbaro, Warhammer)  - Espadachín (Samurái, Caballero) - Especialista (Diletante, Lancero) - Artillero (Bombardero, Juggernaut)
    //   - Cazador (Asaltante, Acechador) - Tirador (Arquero, Francotirador) - Picaro (Balista, Malabarista)

    if ($oficio == "Artesano") {
        $oficios = '{ "Artesano": { "sub": { "Herrero": 0, "Modista": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Médico") {
        $oficios = '{ "Médico": { "sub": { "Farmacólogo": 0, "Doctor": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Navegante") {
        $oficios = '{ "Navegante": { "sub": { "Cartógrafo": 0, "Timonel": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Inventor") {
        $oficios = '{ "Inventor": { "sub": { "Biólogo": 0, "Ingeniero": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Carpintero") {
        $oficios = '{ "Carpintero": { "sub": { "Astillero": 0, "Constructor": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Cocinero") {
        $oficios = '{ "Cocinero": { "sub": { "Chef": 0, "Aprovisionador": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Mercader") {
        $oficios = '{ "Mercader": { "sub": { "Comerciante": 0, "Contrabandista": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Investigador") {
        $oficios = '{ "Investigador": { "sub": { "Periodista": 0, "Arqueólogo": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Aventurero") {
        $oficios = '{ "Aventurero": { "sub": { "Cazador": 0, "Domador": 0 }, "nivel": 1 } }';
    } else if ($oficio == "Recolector") {
        $oficios = '{ "Recolector": { "sub": { "Agreste": 0, "Mayorista": 0 }, "nivel": 1 } }';
    }


    $elementos = '{ "Electro": 0, "Piro": 0, "Cryo": 0, "Aqua": 0,  "Aero": 0 }';

    if ($faccion == "Marina") { $rango = "ReclutaM"; }
    if ($faccion == "CipherPol") { $rango = "CP1"; }
    if ($faccion == "Revolucionario") { $rango = "ReclutaR"; }
    if ($faccion == "Pirata") { $rango = "Pirata"; }
    if ($faccion == "Cazadores") { $rango = "Cazador"; }
    if ($faccion == "Civil") { $rango = "Ciudadano"; }

    $espacios = 500.0;
    $alturaF = floatval($altura);

    if ($alturaF >= 0 && $alturaF <= 50) {
        $espacios = 0.25;
    } else if ($alturaF > 50 && $alturaF <= 100) {
        $espacios = 0.50;
    } else if ($alturaF > 100 && $alturaF <= 300) {
        $espacios = 1;
    } else if ($alturaF > 300) {
        $espacios = 2;
        $espacios = $espacios + floor(((floor($alturaF / 100) - 3) / 2));
    }

    $ranuras = "0 / 6";

    $equipamiento = '{"bolsa":null,"ropa":null,"espacios":{}}';


    // echo(" 
    //     INSERT INTO `mybb_op_fichas` 
    //         (`fid`, `nombre`, `apodo`, `edad`, `altura`, `sexo`, `peso`, `faccion`, `raza`, `temporada`, `espacios`, `apariencia`, `personalidad`, `historia`, `extra`, `fisico_de_pj`, `origen_de_pj`, `como_nos_conociste`, `oficio1`, `belica1`, `sangre`, `belicas`, `oficios`, `estilos`, `rango`, `elementos`, `camino`, `dia`, `equipamiento`) VALUES 
    //         ('$fid', '$nombre', '$apodo', $edad, '$altura', '$sexo', '$peso', '$faccion', '$raza', '$temporada', '$espacios', '$apariencia', '$personalidad', '$historia', '$extra', '$fisico_de_pj', '$origen_de_pj', '$como_nos_conociste', '$oficio', '$maestria', '$sangre', '$belicas', '$oficios', '{}', '$rango', '$elementos', '$camino', '$dia_cumple', '$equipamiento');
    // ");

    $db->query(" 
        INSERT INTO `mybb_op_fichas` 
            (`fid`, `nombre`, `apodo`, `edad`, `altura`, `sexo`, `peso`, `faccion`, `raza`, `temporada`, `espacios`, `apariencia`, `personalidad`, `historia`, `extra`, `fisico_de_pj`, `origen_de_pj`, `como_nos_conociste`, `oficio1`, `belica1`, `sangre`, `belicas`, `oficios`, `estilos`, `rango`, `elementos`, `camino`, `dia`, `equipamiento`) VALUES 
            ('$fid', '$nombre', '$apodo', $edad, '$altura', '$sexo', '$peso', '$faccion', '$raza', '$temporada', '$espacios', '$apariencia', '$personalidad', '$historia', '$extra', '$fisico_de_pj', '$origen_de_pj', '$como_nos_conociste', '$oficio', '$maestria', '$sangre', '$belicas', '$oficios', '{}', '$rango', '$elementos', '$camino', '$dia_cumple', '$equipamiento');
    ");

    // Razas

    if ($raza == 'Humano') {
        $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`='15' WHERE `fid`='$fid'; ");
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC001', '$fid'); ");
        if ($tribu == 'brazos') {
            $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI001', '$fid'); ");
        }

        if ($tribu == 'piernas') {
            $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI002', '$fid'); ");
        }

        if ($tribu == 'tres') {
            $db->query(" UPDATE `mybb_op_fichas` SET `punteria_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI003', '$fid'); ");
        }

        if ($tribu == 'cuello') {
            $db->query(" UPDATE `mybb_op_fichas` SET `destreza_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI004', '$fid'); ");
        }

        if ($tribu == 'kuja') {
            $db->query(" UPDATE `mybb_op_fichas` SET `punteria_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI005', '$fid'); ");
        }
    }

    if ($raza == 'Gigante') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC006', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='30',`resistencia_pasiva`='30',`destreza_pasiva`='10',`agilidad_pasiva`='-10' WHERE `fid`='$fid'; ");
        if ($tribu == 'yeti') {
            $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='-5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI006', '$fid'); ");
        }
        if ($tribu == 'ancestral') {
            $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`=`fuerza_pasiva` + 5 WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI007', '$fid'); ");
        }
    }

    if ($raza == 'Tontatta') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC003', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `reflejos_pasiva`='5',`agilidad_pasiva`='15',`fuerza_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Skypian') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC008', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='10',`destreza_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Gyojin') {
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='15' WHERE `fid`='$fid'; ");
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC002', '$fid'); ");
    }

    if ($raza == 'Ningyo') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC009', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `punteria_pasiva`='15' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Oni') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC005', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='15',`resistencia_pasiva`='15',`voluntad_pasiva`='5' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Lunarian') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC004', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `resistencia_pasiva`='10',`voluntad_pasiva`='10',`agilidad_pasiva`='10' WHERE `fid`='$fid'; ");
        if ($tribu == 'garuda') {
            // $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`=`agilidad_pasiva` + 5 WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI008', '$fid'); ");
        }
    }

    if ($raza == 'Mink') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC007', '$fid'); ");
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES ('SUL801', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `reflejos_pasiva`='10' WHERE `fid`='$fid'; ");
        if ($tribu == 'piel') {
            $db->query(" UPDATE `mybb_op_fichas` SET `resistencia_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI010', '$fid'); ");
        }
    }

    if ($raza == 'Ningyo' || $raza == 'Gyojin') {
        if ($tribu == 'anfibia') {
            $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='5' WHERE `fid`='$fid'; ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('TRI009', '$fid'); ");
        }
    }


    /* 
        Razas mal: Humano, 
        Razas bien: Buccaneers, 
    
    */

    // Razas alternativas

    if ($raza == 'Buccaneers') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC010', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='10',`resistencia_pasiva`='10',`voluntad_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Kobito') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC013', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='10',`resistencia_pasiva`='10',`voluntad_pasiva`='5',`agilidad_pasiva`='5' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Jujin') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC018', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Wotan') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC011', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='10',`resistencia_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Woko') {
        $db->query(" UPDATE `mybb_op_fichas` SET `punteria_pasiva`='15',`agilidad_pasiva`='15' WHERE `fid`='$fid'; ");
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC016', '$fid'); ");
    }

    if ($raza == 'Solarian') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC017', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `resistencia_pasiva`='-5' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Komink') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC014', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Daimink') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC015', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='10',`resistencia_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Donsudada') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC019', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='10',`destreza_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Hafugyo') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC012', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='5',`voluntad_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Ravnos') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC020', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`='10',`reflejos_pasiva`='10',`resistencia_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Diablos') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC021', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`='10',`fuerza_pasiva`='10',`resistencia_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    if ($raza == 'Oceanian') {
        $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('RAC022', '$fid'); ");
        $db->query(" UPDATE `mybb_op_fichas` SET `fuerza_pasiva`='10',`agilidad_pasiva`='10' WHERE `fid`='$fid'; ");
    }

    // Pasivas
    if ($raza == 'Jujin' || $raza == 'Solarian' || $raza == 'Humano' || $raza == 'Mink' || $raza == 'Komink' || $raza == 'Daimink') {
        $db->query(" UPDATE `mybb_op_fichas` SET `$raza1`=`$raza1` + 10 WHERE `fid`='$fid'; ");
    }

    //  Virtudes 
    $virtudes_array = explode (",", $virtudes); 
    foreach ($virtudes_array as $virtud) {

        $db->query(" 
            INSERT INTO `mybb_op_virtudes_usuarios` (`virtud_id`, `uid`) VALUES 
            ('$virtud', '$fid');
        ");
    }

    $db->query(" 
        INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('$objeto', '$fid', '1');
    ");

    $db->query(" 
        INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('CFR001', '$fid', '1');
    ");

    $db->query(" 
        INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
            ('TNP001', '$fid', '1');
    ");
    
    foreach ($virtudes_array as $virtud) {
        #code block

        if ($virtud == 'V001') {
            $db->query(" UPDATE `mybb_op_fichas` SET `reputacion`='50',`reputacion_positiva`='50',`fama`='Célebre' WHERE `fid`='$fid'; ");                         // Acto Triunfal
            if ($faccion == 'Marina') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='SoldadoM' WHERE `fid`='$fid'; "); }
            if ($faccion == 'Cazadores') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='CazadorZeta' WHERE `fid`='$fid'; "); }
            if ($faccion == 'CipherPol') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='CP2' WHERE `fid`='$fid'; "); }
            if ($faccion == 'Revolucionario') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='SoldadoR' WHERE `fid`='$fid'; "); }
        }

        if ($virtud == 'V032') {
            $db->query(" UPDATE `mybb_op_fichas` SET `reputacion`='50',`reputacion_negativa`='50',`fama`='Canalla' WHERE `fid`='$fid'; ");                         // Acto Maléfico
            if ($faccion == 'Cazadores') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='CazadorZeta' WHERE `fid`='$fid'; "); }
            if ($faccion == 'CipherPol') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='CP2' WHERE `fid`='$fid'; "); }
            if ($faccion == 'Revolucionario') { $db->query(" UPDATE `mybb_op_fichas` SET `rango`='SoldadoR' WHERE `fid`='$fid'; "); }  
        }

        if ($virtud == 'V022') {
            $db->query(" UPDATE `mybb_op_fichas` SET `reputacion`=`reputacion` + 15,`reputacion_negativa`=`reputacion_negativa` + 15 WHERE `fid`='$fid'; ");                         // Pasado Maldito 1
        }

        if ($virtud == 'V023') {
            $db->query(" UPDATE `mybb_op_fichas` SET `reputacion`=`reputacion` + 30,`reputacion_negativa`=`reputacion_negativa` + 30 WHERE `fid`='$fid'; ");                         // Pasado Maldito 2
        }

        if ($virtud == 'V002') { $db->query(" UPDATE `mybb_op_fichas` SET `berries`='1000000' WHERE `fid`='$fid'; "); } // Adinerado 1
        if ($virtud == 'V003') { $db->query(" UPDATE `mybb_op_fichas` SET `berries`='3000000' WHERE `fid`='$fid'; "); } // Adinerado 2
        if ($virtud == 'V004') { $db->query(" UPDATE `mybb_op_fichas` SET `berries`='10000000' WHERE `fid`='$fid'; "); } // Adinerado 3

        // if ($virtud == 'D021') { $db->query(" UPDATE `mybb_op_fichas` SET `berries`=-1000000 WHERE `fid`='$fid'; "); } // Pobre 1
        // if ($virtud == 'D022') { $db->query(" UPDATE `mybb_op_fichas` SET `berries`=-3000000 WHERE `fid`='$fid'; "); } // Pobre 2
        // if ($virtud == 'D023') { $db->query(" UPDATE `mybb_op_fichas` SET `berries`=-5000000 WHERE `fid`='$fid'; "); } // Pobre 3

        // // Sentidos Aumentados 1
        if ($virtud == 'V008') { 
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('$sentido1', '$fid'); ");
        }

        // // Sentidos Aumentados 2
        if ($virtud == 'V033') { 
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('$sentido1', '$fid'); ");
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('$sentido2', '$fid'); ");
        }

        // // Sentidos Disminuidos 1
        if ($virtud == 'D008') {
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('$sentido3', '$fid'); ");
        }
 
        // Amputación de Brazo
        if ($virtud == 'D004') { $db->query(" UPDATE `mybb_op_fichas` SET `destreza_pasiva`=`destreza_pasiva` - 20 WHERE `fid`='$fid'; "); }

        // Amputación de Pierna
        if ($virtud == 'D005') { $db->query(" UPDATE `mybb_op_fichas` SET `agilidad_pasiva`=`agilidad_pasiva` - 20 WHERE `fid`='$fid'; "); }

        // Tuerto
        if ($virtud == 'D029') { $db->query(" UPDATE `mybb_op_fichas` SET `reflejos_pasiva`=`reflejos_pasiva` - 15 WHERE `fid`='$fid'; "); }

        if ($virtud == 'D046') { $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`=`voluntad_pasiva` - 10 WHERE `fid`='$fid'; "); } // Pesimista

        if ($virtud == 'V054') { $db->query(" UPDATE `mybb_op_fichas` SET `voluntad_pasiva`=`voluntad_pasiva` + 5 WHERE `fid`='$fid'; "); } // Optimista

        if ($virtud == 'D024') { $db->query(" UPDATE `mybb_op_fichas` SET `oficio1`='', `oficios`='{}' WHERE `fid`='$fid'; "); }      // Sin Oficio
        if ($virtud == 'V053') { $db->query(" INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES ('INV001', '$fid', '1'); "); }


        // Iron Heart V051: +3 Ranuras
        // Cuerpo Puro D063: -6 Ranuras
        // Incompatible D056: -3 Ranuras
        if ($virtud == 'V051') { $db->query(" UPDATE `mybb_op_fichas` SET `ranuras`='0 / 9' WHERE `fid`='$fid'; "); } // Iron Heart
        if ($virtud == 'D063') { $db->query(" UPDATE `mybb_op_fichas` SET `ranuras`='0 / 0' WHERE `fid`='$fid'; "); } // Cuerpo Puro
        if ($virtud == 'D056') { $db->query(" UPDATE `mybb_op_fichas` SET `ranuras`='0 / 3' WHERE `fid`='$fid'; "); } // Incompatible

        if ($virtud == 'V034') { 
            $db->query(" UPDATE `mybb_op_fichas` SET `secret1`=`1` WHERE `fid`='$fid'; "); 
            $db->query(" INSERT INTO `mybb_op_fichas_secret` (`fid`) VALUES ('$fid'); ");  
        }

        if ($virtud == 'V019') { 
            $db->query(" UPDATE `mybb_op_fichas` SET `rango_inframundo`='Alimaña' WHERE `fid`='$fid';  "); 
        } 

        
        if ($oficio == "Aventurero") {
            $db->query(" UPDATE `mybb_op_fichas` SET `equipamiento_espacio`='9' WHERE `fid`='$fid';  "); 
        }

        if ($camino == "Oficio") {
            $db->query(" INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES  ('VTC001', '$fid'); ");
        }

       // V029 Full Haki: 2 de 20 en Tirada Haki del Rey
       // V027 Voluntad D: Tiradas de Haki baja a 1 de 19
 
    }

    $mensaje_redireccion = "¡La ficha ha sido enviada! La estaremos revisando y tan pronto podamos, la aceptaremos.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
} else  {
    // Invitado sin sesión
    if ($fid == 0) {
        echo '<script>window.location.href="/member.php?action=login";</script>';
        exit;
    }

    // Ficha ya existe y está en moderación (no guardada)
    if ($ficha_existe && $f['aprobada_por'] != 'guardada') {
        $mensaje_redireccion = "Tu ficha ya está enviada y está siendo revisada por el Staff.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        return;
    }

    // Mostrar formulario de creación
    $razas = [];
    $query_razas = $db->query(" SELECT * FROM mybb_op_razas ");
    while ($q = $db->fetch_array($query_razas)) {
        $q['caracteristicas'] = nl2br($q['caracteristicas']);
        array_push($razas, $q);
    }
    $razas_json = json_encode($razas);

    $virtudes = [];
    $defectos  = [];
    $query_virtudes = $db->query(" SELECT * FROM `mybb_op_virtudes` WHERE virtud_id LIKE 'V%' AND virtud_id != 'V057' ORDER BY `mybb_op_virtudes`.`nombre` ASC; ");
    $query_defectos = $db->query(" SELECT * FROM `mybb_op_virtudes` WHERE virtud_id LIKE 'D%' ORDER BY `mybb_op_virtudes`.`nombre` ASC; ");
    while ($virtud = $db->fetch_array($query_virtudes)) { array_push($virtudes, $virtud); }
    while ($defecto = $db->fetch_array($query_defectos)) { array_push($defectos, $defecto); }
    $virtudes_json = json_encode($virtudes);
    $defectos_json = json_encode($defectos);

    eval("\$op_ficha_crear_script = \"".$templates->get("op_ficha_crear_script")."\";");
    eval("\$page = \"".$templates->get("op_ficha_crear")."\";");
    output_page($page);
}
