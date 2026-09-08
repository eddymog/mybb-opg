<?php
/**
 * OPG - Ficha de personaje (op_ficha)
 *
 * Funciones y tablas compartidas extraídas de op/personaje.php, como parte
 * de la migración hacia una arquitectura de plugin al estilo de
 * inc/plugins/dg_ficha.php en Danmachi-Gaiden.
 *
 * Todas las funciones de este archivo leen SIEMPRE de base de datos, sin
 * ningún valor hardcodeado de refugio: si las tablas no existen (plugin
 * desactivado/no instalado), personaje.php debe detectarlo con
 * op_ficha_tablas_listas() y bloquear la página en vez de dejar que estas
 * funciones devuelvan datos silenciosamente incorrectos.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_ficha_info()
{
    return array(
        'name'          => 'OPG - Ficha de personaje',
        'description'   => 'Costes de mejoras y catálogos de disciplinas/oficios del juego, gestionables desde Configuración del foro → OPG Costes.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '2.0',
        'compatibility' => '18*',
    );
}

/**
 * Nombres de las tablas propias de este plugin, para comprobar de un
 * vistazo que todas existen (op_ficha_tablas_listas()).
 */
function op_ficha_tablas()
{
    return array('op_costes_haki', 'op_costes_akuma', 'op_costes_belica', 'op_costes_estilo', 'op_disciplinas', 'op_oficios_catalogo', 'op_costes_oficio', 'op_niveles_requeridos', 'op_estilos_catalogo');
}

/**
 * true solo si todas las tablas de este plugin existen. No comprueba las de
 * otros plugins (op_facciones/op_fama/op_niveles) — cada uno expone la
 * suya propia; personaje.php las comprueba todas juntas antes de arrancar.
 */
function op_ficha_tablas_listas()
{
    global $db;
    foreach (op_ficha_tablas() as $tabla) {
        if (!$db->table_exists($tabla)) {
            return false;
        }
    }
    return true;
}

// ─── Datos de siembra (valores que ya existían hardcodeados) ────────────────

function op_ficha_seed_haki()
{
    // nivel => [coste, coste_camino] (coste_camino null = igual que coste)
    return array(
        1 => array(10, 0),
        2 => array(15, null),
        3 => array(25, null),
        4 => array(40, null),
        5 => array(60, null),
        6 => array(150, null),
        7 => array(8, null),
    );
}

function op_ficha_seed_akuma()
{
    // dominio => [coste_normal, coste_camino] (null = ese dominio no existe en esa variante)
    return array(
        0 => array(5, 0),
        1 => array(10, 5),
        2 => array(20, 15),
        3 => array(25, 20),
        4 => array(40, 30),
        5 => array(null, 80),
    );
}

function op_ficha_seed_belica()
{
    // slot => [coste_slot, coste_espe1, coste_espe2, coste_up]
    return array(
        1  => array(0,   0,   15,  45),
        2  => array(10,  20,  30,  60),
        3  => array(20,  30,  40,  75),
        4  => array(35,  45,  60,  100),
        5  => array(50,  60,  75,  125),
        6  => array(65,  75,  90,  150),
        7  => array(80,  90,  105, 175),
        8  => array(95,  105, 120, 200),
        9  => array(110, 120, 135, 225),
        10 => array(125, 135, 150, 250),
        11 => array(140, 150, 165, 275),
        12 => array(155, 165, 180, 300),
    );
}

function op_ficha_seed_estilo()
{
    // slot => [coste_nuevo, coste_reembolso] (null = no aplica)
    return array(
        'estilo2' => array(50, 25),
        'estilo3' => array(100, 50),
        'estilo4' => array(null, 75),
    );
}

function op_ficha_seed_disciplinas()
{
    // nombre => [camino1, camino2] — orden ya estandarizado (ver Fase 1: antes
    // "Artista Marcial" tenía Monje/Acróbata invertido en un tercer sitio).
    return array(
        'Escudero'        => array('Vanguardia', 'Bastión'),
        'Artista Marcial' => array('Acróbata', 'Monje'),
        'Combatiente'     => array('Berserker', 'Campeón'),
        'Artista'         => array('Bardo', 'Trovador'),
        'Asesino'         => array('Sombra', 'Verdugo'),
        'Guerrero'        => array('Castigador', 'Warhammer'),
        'Espadachín'      => array('Samurái', 'Mosquetero'),
        'Tecnicista'      => array('Diletante', 'WeaponMaster'),
        'Artillero'       => array('Destructor', 'Juggernaut'),
        'Arquero'         => array('Ballestero', 'Cazador'),
        'Tirador'         => array('Duelista', 'Francotirador'),
        'Pícaro'          => array('Gambito', 'Trickster'),
    );
}

function op_ficha_seed_oficios()
{
    return array(
        'Artesano'     => array('Herrero', 'Modista'),
        'Médico'       => array('Farmacólogo', 'Doctor'),
        'Navegante'    => array('Cartógrafo', 'Timonel'),
        'Inventor'     => array('Biólogo', 'Ingeniero'),
        'Carpintero'   => array('Astillero', 'Constructor'),
        'Cocinero'     => array('Chef', 'Aprovisionador'),
        'Mercader'     => array('Comerciante', 'Contrabandista'),
        'Investigador' => array('Periodista', 'Arqueólogo'),
        'Aventurero'   => array('Cazador', 'Domador'),
        'Recolector'   => array('Agreste', 'Mayorista'),
    );
}

/**
 * Costes de oficio (subir el oficio principal a nivel 2, y elegir/subir una
 * especialización) — antes duplicados 5 veces dentro de op/personaje.php.
 * clave => [coste_nikas, coste_puntos_oficio].
 */
function op_ficha_seed_costes_oficio()
{
    return array(
        'nivel2' => array(0, 1000),
        'espe_0' => array(10, 2000),
        'espe_1' => array(25, 3500),
        'espe_2' => array(50, 5000),
    );
}

/**
 * Imagen de cada disciplina (mostrada en la ficha), migrada de los bloques
 * <img> fijos de la plantilla op_ficha_belico.html. Ojo: varias no siguen la
 * fórmula "DisciFicha{nombre}_..." porque el nombre de la disciplina cambió
 * y el archivo de imagen nunca se renombró (Artista era "Músico", Tecnicista
 * era "Especialista", Arquero era "Cazador", y Espadachín usa un archivo con
 * sufijo "2"). No recalcular esta lista por fórmula.
 */
function op_ficha_seed_disciplinas_imagenes()
{
    return array(
        'Escudero'        => '/images/op/uploads/DisciFichaEscudero_One_Piece_Gaiden_Foro_Rol.webp',
        'Artista Marcial' => '/images/op/uploads/DisciFichaArtistaMarcial_One_Piece_Gaiden_Foro_Rol.webp',
        'Combatiente'     => '/images/op/uploads/DisciFichaCombatiente_One_Piece_Gaiden_Foro_Rol.webp',
        'Artista'         => '/images/op/uploads/DisciFichaMusico_One_Piece_Gaiden_Foro_Rol.webp',
        'Asesino'         => '/images/op/uploads/DisciFichaAsesino_One_Piece_Gaiden_Foro_Rol.webp',
        'Guerrero'        => '/images/op/uploads/DisciFichaGuerrero_One_Piece_Gaiden_Foro_Rol.webp',
        'Espadachín'      => '/images/op/uploads/DisciFichaEspadachin2_One_Piece_Gaiden_Foro_Rol.webp',
        'Tecnicista'      => '/images/op/uploads/DisciFichaEspecialista_One_Piece_Gaiden_Foro_Rol.webp',
        'Artillero'       => '/images/op/uploads/DisciFichaArtillero_One_Piece_Gaiden_Foro_Rol.webp',
        'Arquero'         => '/images/op/uploads/DisciFichaCazador_One_Piece_Gaiden_Foro_Rol.webp',
        'Tirador'         => '/images/op/uploads/DisciFichaTirador_One_Piece_Gaiden_Foro_Rol.webp',
        'Pícaro'          => '/images/op/uploads/DisciFichaPicaro_One_Piece_Gaiden_Foro_Rol.webp',
    );
}

/**
 * Imagen de cada oficio (mostrada en la ficha), migrada del array JS
 * oficiosDisponibles (jscripts/ficha_script2.js / jscripts/ficha/oficios.js).
 */
function op_ficha_seed_oficios_imagenes()
{
    return array(
        'Cocinero'     => '/images/op/uploads/OficioFichaCocinero_One_Piece_Gaiden_Foro_Rol.webp',
        'Médico'       => '/images/op/uploads/OficioFichaMedico_One_Piece_Gaiden_Foro_Rol.webp',
        'Navegante'    => '/images/op/uploads/OficioFichaNavegante_One_Piece_Gaiden_Foro_Rol.webp',
        'Artesano'     => '/images/op/uploads/OficioFichaArtesano_One_Piece_Gaiden_Foro_Rol.webp',
        'Carpintero'   => '/images/op/uploads/OficioFichaCarpintero_One_Piece_Gaiden_Foro_Rol.webp',
        'Aventurero'   => '/images/op/uploads/OficioFichaAventurero_One_Piece_Gaiden_Foro_Rol.webp',
        'Inventor'     => '/images/op/uploads/OficioFichaInventor_One_Piece_Gaiden_Foro_Rol.webp',
        'Investigador' => '/images/op/uploads/OficioFichaInvestigador_One_Piece_Gaiden_Foro_Rol.webp',
        'Mercader'     => '/images/op/uploads/OficioFichaMercader_One_Piece_Gaiden_Foro_Rol.webp',
        'Recolector'   => '/images/op/uploads/OficioFichaRecolector_One_Piece_Gaiden_Foro_Rol.webp',
    );
}

/**
 * Catálogo de estilos de combate: nombre => imagen, en el mismo orden que el
 * array JS estilosData de origen (jscripts/ficha_script2.js /
 * jscripts/ficha/belicas.js), migrado íntegro a BD. La disponibilidad por
 * raza/facción (Gyojin/CipherPol/Revolucionario) se queda en JS — no es un
 * dato de catálogo, es una regla de negocio aparte.
 */
function op_ficha_seed_estilos()
{
    return array(
        'Gunkata'            => '/images/op/uploads/FichaGunkata_One_Piece_Gaiden_Foro_Rol.webp',
        'Hasshoken'          => '/images/op/uploads/FichaHasshoken_One_Piece_Gaiden_Foro_Rol.webp',
        'Santoryu'           => '/images/op/uploads/FichaSantoryu_One_Piece_Gaiden_Foro_Rol.webp',
        'Kuroashi'           => '/images/op/uploads/FichaKuroashi_One_Piece_Gaiden_Foro_Rol.webp',
        'Gyojin Karate'      => '/images/op/uploads/FichaGyojin Karate_One_Piece_Gaiden_Foro_Rol.webp',
        'Gyojin Bukijutsu'   => '/images/op/uploads/FichaGyojin Bukijutsu_One_Piece_Gaiden_Foro_Rol.webp',
        'Gyojin Jujutsu'     => '/images/op/uploads/FichaGyojin Jujutsu_One_Piece_Gaiden_Foro_Rol.webp',
        'Rokushiki'          => '/images/op/uploads/FichaRokushiki_One_Piece_Gaiden_Foro_Rol.webp',
        'Okama Kempo'        => '/images/op/uploads/FichaOkama Kempo_One_Piece_Gaiden_Foro_Rol.webp',
        'Sora Yokujin'       => '/images/op/uploads/FichaSora Yokujin_One_Piece_Gaiden_Foro_Rol.webp',
        'Ninjutsu'           => '/images/op/uploads/FichaNinjutsu_One_Piece_Gaiden_Foro_Rol.webp',
        'Ryusoken'           => '/images/op/uploads/FichaRyusoken_One_Piece_Gaiden_Foro_Rol.webp',
        'Jiyuumura Kempo'    => '/images/op/uploads/FichaJiyuumura Kempo_One_Piece_Gaiden_Foro_Rol.webp',
        'Pop Green'          => '/images/op/uploads/FichaPop Green_One_Piece_Gaiden_Foro_Rol.webp',
        'Clima Tact'         => '/images/op/uploads/FichaClima Tact_One_Piece_Gaiden_Foro_Rol.webp',
        'Funekiri'           => '/images/op/uploads/FichaFunekiri_One_Piece_Gaiden_Foro_Rol.webp',
        'Hakai Shin'         => '/images/op/uploads/FichaHakai Shin_One_Piece_Gaiden_Foro_Rol.webp',
        'Railgun Style'      => '/images/op/uploads/FichaRailgun Style_One_Piece_Gaiden_Foro_Rol.webp',
        'Shuron Hakke'       => '/images/op/uploads/FichaShuron Hakke_One_Piece_Gaiden_Foro_Rol.webp',
        'Raqisat Alsahra'    => '/images/op/uploads/FichaRaqisat Alsahra_One_Piece_Gaiden_Foro_Rol.webp',
        'Breeskjold'         => '/images/op/uploads/FichaBreeskjold_One_Piece_Gaiden_Foro_Rol.webp',
        'Impacto Explosivo'  => '/images/op/uploads/FichaImpacto Explosivo_One_Piece_Gaiden_Foro_Rol.webp',
        'Royal Guard'        => '/images/op/uploads/FichaRoyal Guard_One_Piece_Gaiden_Foro_Rol.webp',
        'Shikaku Teikoku'    => '/images/op/uploads/FichaShikaku Teikoku_One_Piece_Gaiden_Foro_Rol.webp',
        'Duelliste de Givre' => '/images/op/uploads/FichaDuelliste de Givre_One_Piece_Gaiden_Foro_Rol.webp',
        'Filo Della Vita'    => '/images/op/uploads/FichaFilo Della Vita_One_Piece_Gaiden_Foro_Rol.webp',
        'Havets Symfoni'     => '/images/op/uploads/FichaHavets Symfoni_One_Piece_Gaiden_Foro_Rol.webp',
        'Bakudai Karin'      => '/images/op/uploads/FichaBakudai Karin_One_Piece_Gaiden_Foro_Rol.webp',
        'Yama Kurai'         => '/images/op/uploads/FichaYama Kurai_One_Piece_Gaiden_Foro_Rol.webp',
        'Shiseiju'           => '/images/op/uploads/FichaShiseiju_One_Piece_Gaiden_Foro_Rol.webp',
        'Sea Corsair'        => '/images/op/uploads/FichaSea Corsair_One_Piece_Gaiden_Foro_Rol.webp',
        'Wano Nitoryu'       => '/images/op/uploads/FichaWano Nitoryu_One_Piece_Gaiden_Foro_Rol.webp',
        'Mano de Tahur'      => '/images/op/uploads/FichaMano de Tahur_One_Piece_Gaiden_Foro_Rol.webp',
        'Kokudan'            => '/images/op/uploads/FichaKokudan_One_Piece_Gaiden_Foro_Rol.webp',
        'Kodai no Bushido'   => '/images/op/uploads/FichaKodai no Bushido_One_Piece_Gaiden_Foro_Rol.webp',
        'Ittoryu Sekai'      => '/images/op/uploads/FichaIttoryu Sekai_One_Piece_Gaiden_Foro_Rol.webp',
        'Global Performer'   => '/images/op/uploads/FichaGlobal Performer_One_Piece_Gaiden_Foro_Rol.webp',
        'Cavalry Warrior'    => '/images/op/uploads/FichaCavalry Warrior_One_Piece_Gaiden_Foro_Rol.webp',
        'Ashigara Dokoi'     => '/images/op/uploads/FichaAshigara Dokoi_One_Piece_Gaiden_Foro_Rol.webp',
        'Kanpo Kenpo'        => '/images/op/uploads/FichaKanpo Kenpo_One_Piece_Gaiden_Foro_Rol.webp',
    );
}

/**
 * Nivel de personaje mínimo requerido para desbloquear cosas del sistema de
 * disciplinas/estilos — antes duplicado 5+ veces dentro de op/personaje.php.
 */
function op_ficha_seed_niveles_requeridos()
{
    return array(
        'estilo1'       => 8,  // desbloquear el 1er slot de estilo
        'estilo2'       => 20, // desbloquear el 2º slot de estilo
        'estilo3'       => 35, // desbloquear el 3er slot de estilo
        'camino_elegir' => 8,  // elegir un camino de especialización de disciplina
        'camino_subir'  => 20, // subir un camino ya elegido a nivel 2
    );
}

// ─── Lecturas (sin fallback: si la tabla no existe, esto no debe llamarse) ──

/**
 * Coste de subir un nivel de Haki (kenbun/buso/hao), indexado por el nivel
 * ACTUAL (antes de subir).
 */
function op_ficha_haki_step_costs($is_haki_camino)
{
    global $db;
    $costs = array();
    $query = $db->simple_select('op_costes_haki', '*', '', array('order_by' => 'nivel'));
    while ($fila = $db->fetch_array($query)) {
        $nivel = (int)$fila['nivel'];
        $costs[$nivel] = ($is_haki_camino && !is_null($fila['coste_camino'])) ? (int)$fila['coste_camino'] : (int)$fila['coste'];
    }
    return $costs;
}

/**
 * Coste acumulado de Haki desde nivel 1 hasta $nivel_actual (para
 * reembolsos/presupuesto del ticket de reset).
 */
function op_ficha_haki_cumulative_cost($nivel_actual, $is_haki_camino)
{
    if ($nivel_actual <= 1) {
        return 0;
    }
    $costs = op_ficha_haki_step_costs($is_haki_camino);
    $total = 0;
    for ($cur = 1; $cur < $nivel_actual; $cur++) {
        $total += isset($costs[$cur]) ? $costs[$cur] : 0;
    }
    return $total;
}

/**
 * Coste de subir un nivel de Dominio Akuma, indexado por el dominio ACTUAL
 * (antes de subir). Los dominios sin valor configurado para la variante
 * pedida (normal/camino) se omiten del array, igual que antes.
 */
function op_ficha_akuma_step_costs($is_akuma_camino)
{
    global $db;
    $costs = array();
    $columna = $is_akuma_camino ? 'coste_camino' : 'coste_normal';
    $query = $db->simple_select('op_costes_akuma', "dominio, {$columna}", '', array('order_by' => 'dominio'));
    while ($fila = $db->fetch_array($query)) {
        if (!is_null($fila[$columna])) {
            $costs[(int)$fila['dominio']] = (int)$fila[$columna];
        }
    }
    return $costs;
}

/**
 * Coste acumulado de Dominio Akuma desde 0 hasta $dom_actual.
 */
function op_ficha_akuma_cumulative_cost($dom_actual, $is_akuma_camino)
{
    if ($dom_actual <= 0) {
        return 0;
    }
    $costs = op_ficha_akuma_step_costs($is_akuma_camino);
    $total = 0;
    for ($d = 0; $d < $dom_actual; $d++) {
        $total += isset($costs[$d]) ? $costs[$d] : 0;
    }
    return $total;
}

/**
 * Coste de desbloquear cada slot de disciplina. Indexado por nombre de slot
 * ('belicaN'), no por número.
 */
function op_ficha_belica_slot_costs()
{
    global $db;
    $costs = array();
    $query = $db->simple_select('op_costes_belica', 'slot, coste_slot', '', array('order_by' => 'slot'));
    while ($fila = $db->fetch_array($query)) {
        $costs['belica'.(int)$fila['slot']] = (int)$fila['coste_slot'];
    }
    return $costs;
}

/**
 * Coste de especialización de disciplina, indexado por número de slot
 * (1-12). $tier: 'espe1', 'espe2' o 'up'.
 */
function op_ficha_belica_espe_costs($tier)
{
    global $db;
    $columnas = array('espe1' => 'coste_espe1', 'espe2' => 'coste_espe2', 'up' => 'coste_up');
    if (!isset($columnas[$tier])) {
        return array();
    }
    $columna = $columnas[$tier];
    $costs = array();
    $query = $db->simple_select('op_costes_belica', "slot, {$columna}", '', array('order_by' => 'slot'));
    while ($fila = $db->fetch_array($query)) {
        $costs[(int)$fila['slot']] = (int)$fila[$columna];
    }
    return $costs;
}

/**
 * Igual que op_ficha_belica_espe_costs() pero indexado por nombre de slot
 * ('belicaN') en vez de número.
 */
function op_ficha_belica_espe_costs_by_name($tier)
{
    $by_name = array();
    foreach (op_ficha_belica_espe_costs($tier) as $idx => $value) {
        $by_name["belica{$idx}"] = $value;
    }
    return $by_name;
}

/**
 * Coste de desbloquear estilo2/estilo3 al COMPRARLO.
 */
function op_ficha_estilo_unlock_cost_new()
{
    global $db;
    $costs = array();
    $query = $db->simple_select('op_costes_estilo', 'slot, coste_nuevo', 'coste_nuevo IS NOT NULL');
    while ($fila = $db->fetch_array($query)) {
        $costs[$fila['slot']] = (int)$fila['coste_nuevo'];
    }
    return $costs;
}

/**
 * Coste "antiguo" de estilo2/estilo3/estilo4, usado solo para REEMBOLSOS.
 */
function op_ficha_estilo_unlock_cost_refund()
{
    global $db;
    $costs = array();
    $query = $db->simple_select('op_costes_estilo', 'slot, coste_reembolso', 'coste_reembolso IS NOT NULL');
    while ($fila = $db->fetch_array($query)) {
        $costs[$fila['slot']] = (int)$fila['coste_reembolso'];
    }
    return $costs;
}

/**
 * Disciplina → sus dos caminos de especialización válidos.
 */
function op_ficha_belica_caminos()
{
    global $db;
    $caminos = array();
    $query = $db->simple_select('op_disciplinas', '*', '', array('order_by' => 'orden'));
    while ($fila = $db->fetch_array($query)) {
        $caminos[$fila['nombre']] = array($fila['camino1'], $fila['camino2']);
    }
    return $caminos;
}

/**
 * Plantilla JSON usada al aprender una disciplina nueva (mybb_op_fichas.belicas).
 */
function op_ficha_belica_template_json($nombre_belica)
{
    $caminos = op_ficha_belica_caminos();
    if (!isset($caminos[$nombre_belica])) {
        return null;
    }
    $sub = array();
    foreach ($caminos[$nombre_belica] as $camino) {
        $sub[$camino] = 0;
    }
    return json_encode(array('sub' => $sub, 'nivel' => 1), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Oficio → sus dos sub-especializaciones válidas.
 */
function op_ficha_oficio_subs()
{
    global $db;
    $subs = array();
    $query = $db->simple_select('op_oficios_catalogo', '*', '', array('order_by' => 'orden'));
    while ($fila = $db->fetch_array($query)) {
        $subs[$fila['nombre']] = array($fila['sub1'], $fila['sub2']);
    }
    return $subs;
}

/**
 * Costes de oficio: 'nivel2' (subir el oficio principal a nivel 2),
 * 'espe_0'/'espe_1'/'espe_2' (elegir/subir una especialización, indexado
 * por el nivel ACTUAL de esa especialización antes de subir). Cada valor es
 * ['nikas' => N, 'pto' => N].
 */
function op_ficha_oficio_costes()
{
    global $db;
    $costes = array();
    $query = $db->simple_select('op_costes_oficio', '*', '', array('order_by' => 'clave'));
    while ($fila = $db->fetch_array($query)) {
        $costes[$fila['clave']] = array('nikas' => (int)$fila['coste_nikas'], 'pto' => (int)$fila['coste_puntos_oficio']);
    }
    return $costes;
}

/**
 * Nivel de personaje mínimo requerido para cada gate del sistema de
 * disciplinas/estilos ('estilo1', 'estilo2', 'estilo3', 'camino_elegir',
 * 'camino_subir'). Devuelve 0 (nunca bloquea) para una clave desconocida.
 */
function op_ficha_nivel_requerido($clave)
{
    global $db;
    $valor = $db->fetch_field($db->simple_select('op_niveles_requeridos', 'nivel_min', "clave='".$db->escape_string($clave)."'"), 'nivel_min');
    return $valor === false ? 0 : (int)$valor;
}

/**
 * Catálogo de estilos de combate: array ORDENADO (no asociativo, para
 * conservar el orden al json_encode()arlo hacia JS) de ['nombre'=>,
 * 'imagen'=>]. La lógica de qué estilos están disponibles según
 * raza/facción se queda en JS, esto es solo el catálogo (qué existe + qué
 * imagen tiene).
 */
function op_ficha_estilos_catalogo()
{
    global $db;
    $catalogo = array();
    $query = $db->simple_select('op_estilos_catalogo', '*', '', array('order_by' => 'orden'));
    while ($fila = $db->fetch_array($query)) {
        $catalogo[] = array('nombre' => $fila['nombre'], 'imagen' => $fila['imagen']);
    }
    return $catalogo;
}

/**
 * Catálogo de oficios con su imagen: array ORDENADO de ['nombre'=>,
 * 'imagen'=>], para inyectar en JS igual que op_ficha_estilos_catalogo().
 */
function op_ficha_oficios_catalogo_imagenes()
{
    global $db;
    $catalogo = array();
    $query = $db->simple_select('op_oficios_catalogo', 'nombre, imagen', '', array('order_by' => 'orden'));
    while ($fila = $db->fetch_array($query)) {
        $catalogo[] = array('nombre' => $fila['nombre'], 'imagen' => $fila['imagen']);
    }
    return $catalogo;
}

/**
 * Imagen de cada disciplina, indexada por nombre (para las plantillas
 * op_ficha_belico.html / op_compas.html, que referencian una disciplina
 * concreta cada vez, no una lista).
 */
function op_ficha_disciplinas_imagenes()
{
    global $db;
    $imagenes = array();
    $query = $db->simple_select('op_disciplinas', 'nombre, imagen');
    while ($fila = $db->fetch_array($query)) {
        $imagenes[$fila['nombre']] = $fila['imagen'];
    }
    return $imagenes;
}

/**
 * Catálogo completo de disciplinas (nombre + caminos + imagen), en el mismo
 * orden que la columna `orden`, para que la ficha genere su rejilla de
 * disciplinas dinámicamente en vez de tener un número fijo de casillas
 * hardcodeadas — así una disciplina añadida desde OPG Catálogos aparece sin
 * tocar plantillas ni JS.
 */
function op_ficha_disciplinas_catalogo()
{
    global $db;
    $catalogo = array();
    $query = $db->simple_select('op_disciplinas', '*', '', array('order_by' => 'orden'));
    while ($fila = $db->fetch_array($query)) {
        $catalogo[] = array(
            'nombre'  => $fila['nombre'],
            'camino1' => $fila['camino1'],
            'camino2' => $fila['camino2'],
            'imagen'  => $fila['imagen'],
        );
    }
    return $catalogo;
}

/**
 * Imagen de UNA disciplina por nombre. Cadena vacía si no existe o no tiene
 * imagen asignada todavía (el <img> simplemente sale sin src).
 */
function op_ficha_disciplina_imagen($nombre)
{
    global $db;
    $valor = $db->fetch_field($db->simple_select('op_disciplinas', 'imagen', "nombre='".$db->escape_string($nombre)."'"), 'imagen');
    return $valor === false ? '' : $valor;
}

// La tabla de fama vive en inc/plugins/op_fama.php (op_fama_tabla()) y la de
// experiencia/nivel en inc/plugins/op_niveles.php (op_niveles_tabla() /
// op_niveles_bono_puntos() / op_niveles_limite_temporal()) — no en este archivo.

if (defined('IN_ADMINCP')) {
    function op_ficha_install()
    {
        global $db;

        if (!$db->table_exists('op_costes_haki')) {
            $db->write_query("
                CREATE TABLE mybb_op_costes_haki (
                    nivel INT NOT NULL,
                    coste INT NOT NULL DEFAULT 0,
                    coste_camino INT NULL DEFAULT NULL,
                    PRIMARY KEY (nivel)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_costes_akuma')) {
            $db->write_query("
                CREATE TABLE mybb_op_costes_akuma (
                    dominio INT NOT NULL,
                    coste_normal INT NULL DEFAULT NULL,
                    coste_camino INT NULL DEFAULT NULL,
                    PRIMARY KEY (dominio)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_costes_belica')) {
            $db->write_query("
                CREATE TABLE mybb_op_costes_belica (
                    slot INT NOT NULL,
                    coste_slot INT NOT NULL DEFAULT 0,
                    coste_espe1 INT NOT NULL DEFAULT 0,
                    coste_espe2 INT NOT NULL DEFAULT 0,
                    coste_up INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (slot)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_costes_estilo')) {
            $db->write_query("
                CREATE TABLE mybb_op_costes_estilo (
                    slot VARCHAR(20) NOT NULL,
                    coste_nuevo INT NULL DEFAULT NULL,
                    coste_reembolso INT NULL DEFAULT NULL,
                    PRIMARY KEY (slot)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_disciplinas')) {
            $db->write_query("
                CREATE TABLE mybb_op_disciplinas (
                    nombre VARCHAR(60) NOT NULL,
                    camino1 VARCHAR(60) NOT NULL DEFAULT '',
                    camino2 VARCHAR(60) NOT NULL DEFAULT '',
                    orden INT NOT NULL DEFAULT 0,
                    imagen VARCHAR(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (nombre)
                ) ENGINE=InnoDB
            ");
        }
        if (!$db->field_exists('imagen', 'op_disciplinas')) {
            $db->add_column('op_disciplinas', 'imagen', "VARCHAR(255) NOT NULL DEFAULT ''");
        }

        if (!$db->table_exists('op_oficios_catalogo')) {
            $db->write_query("
                CREATE TABLE mybb_op_oficios_catalogo (
                    nombre VARCHAR(60) NOT NULL,
                    sub1 VARCHAR(60) NOT NULL DEFAULT '',
                    sub2 VARCHAR(60) NOT NULL DEFAULT '',
                    orden INT NOT NULL DEFAULT 0,
                    imagen VARCHAR(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (nombre)
                ) ENGINE=InnoDB
            ");
        }
        if (!$db->field_exists('imagen', 'op_oficios_catalogo')) {
            $db->add_column('op_oficios_catalogo', 'imagen', "VARCHAR(255) NOT NULL DEFAULT ''");
        }

        if (!$db->table_exists('op_costes_oficio')) {
            $db->write_query("
                CREATE TABLE mybb_op_costes_oficio (
                    clave VARCHAR(20) NOT NULL,
                    coste_nikas INT NOT NULL DEFAULT 0,
                    coste_puntos_oficio INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (clave)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_niveles_requeridos')) {
            $db->write_query("
                CREATE TABLE mybb_op_niveles_requeridos (
                    clave VARCHAR(30) NOT NULL,
                    nivel_min INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (clave)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_estilos_catalogo')) {
            $db->write_query("
                CREATE TABLE mybb_op_estilos_catalogo (
                    nombre VARCHAR(60) NOT NULL,
                    imagen VARCHAR(255) NOT NULL DEFAULT '',
                    orden INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (nombre)
                ) ENGINE=InnoDB
            ");
        }

        // Siembra, tabla por tabla, solo si cada una está vacía (para poder
        // reinstalar sin perder personalizaciones ya hechas desde el panel).
        if (!$db->fetch_field($db->simple_select('op_costes_haki', 'nivel', '', array('limit' => 1)), 'nivel')) {
            foreach (op_ficha_seed_haki() as $nivel => $par) {
                $db->insert_query('op_costes_haki', array(
                    'nivel'        => (int)$nivel,
                    'coste'        => (int)$par[0],
                    'coste_camino' => is_null($par[1]) ? null : (int)$par[1],
                ));
            }
        }

        if (!$db->fetch_field($db->simple_select('op_costes_akuma', 'dominio', '', array('limit' => 1)), 'dominio')) {
            foreach (op_ficha_seed_akuma() as $dominio => $par) {
                $db->insert_query('op_costes_akuma', array(
                    'dominio'      => (int)$dominio,
                    'coste_normal' => is_null($par[0]) ? null : (int)$par[0],
                    'coste_camino' => is_null($par[1]) ? null : (int)$par[1],
                ));
            }
        }

        if (!$db->fetch_field($db->simple_select('op_costes_belica', 'slot', '', array('limit' => 1)), 'slot')) {
            foreach (op_ficha_seed_belica() as $slot => $par) {
                $db->insert_query('op_costes_belica', array(
                    'slot'        => (int)$slot,
                    'coste_slot'  => (int)$par[0],
                    'coste_espe1' => (int)$par[1],
                    'coste_espe2' => (int)$par[2],
                    'coste_up'    => (int)$par[3],
                ));
            }
        }

        if (!$db->fetch_field($db->simple_select('op_costes_estilo', 'slot', '', array('limit' => 1)), 'slot')) {
            foreach (op_ficha_seed_estilo() as $slot => $par) {
                $db->insert_query('op_costes_estilo', array(
                    'slot'            => $db->escape_string($slot),
                    'coste_nuevo'     => is_null($par[0]) ? null : (int)$par[0],
                    'coste_reembolso' => is_null($par[1]) ? null : (int)$par[1],
                ));
            }
        }

        if (!$db->fetch_field($db->simple_select('op_disciplinas', 'nombre', '', array('limit' => 1)), 'nombre')) {
            $orden = 10;
            foreach (op_ficha_seed_disciplinas() as $nombre => $caminos) {
                $db->insert_query('op_disciplinas', array(
                    'nombre'  => $db->escape_string($nombre),
                    'camino1' => $db->escape_string($caminos[0]),
                    'camino2' => $db->escape_string($caminos[1]),
                    'orden'   => $orden,
                ));
                $orden += 10;
            }
        }

        if (!$db->fetch_field($db->simple_select('op_oficios_catalogo', 'nombre', '', array('limit' => 1)), 'nombre')) {
            $orden = 10;
            foreach (op_ficha_seed_oficios() as $nombre => $subs) {
                $db->insert_query('op_oficios_catalogo', array(
                    'nombre' => $db->escape_string($nombre),
                    'sub1'   => $db->escape_string($subs[0]),
                    'sub2'   => $db->escape_string($subs[1]),
                    'orden'  => $orden,
                ));
                $orden += 10;
            }
        }

        if (!$db->fetch_field($db->simple_select('op_costes_oficio', 'clave', '', array('limit' => 1)), 'clave')) {
            foreach (op_ficha_seed_costes_oficio() as $clave => $par) {
                $db->insert_query('op_costes_oficio', array(
                    'clave'               => $db->escape_string($clave),
                    'coste_nikas'         => (int)$par[0],
                    'coste_puntos_oficio' => (int)$par[1],
                ));
            }
        }

        if (!$db->fetch_field($db->simple_select('op_niveles_requeridos', 'clave', '', array('limit' => 1)), 'clave')) {
            foreach (op_ficha_seed_niveles_requeridos() as $clave => $nivel_min) {
                $db->insert_query('op_niveles_requeridos', array(
                    'clave'     => $db->escape_string($clave),
                    'nivel_min' => (int)$nivel_min,
                ));
            }
        }

        if (!$db->fetch_field($db->simple_select('op_estilos_catalogo', 'nombre', '', array('limit' => 1)), 'nombre')) {
            $orden = 10;
            foreach (op_ficha_seed_estilos() as $nombre => $imagen) {
                $db->insert_query('op_estilos_catalogo', array(
                    'nombre' => $db->escape_string($nombre),
                    'imagen' => $db->escape_string($imagen),
                    'orden'  => $orden,
                ));
                $orden += 10;
            }
        }

        // Backfill de imagen para disciplinas/oficios creados antes de que
        // existiera la columna (fila ya existente pero imagen todavía en
        // blanco) — no toca filas que el staff ya haya editado desde el panel.
        foreach (op_ficha_seed_disciplinas_imagenes() as $nombre => $imagen) {
            $db->update_query('op_disciplinas', array('imagen' => $db->escape_string($imagen)), "nombre='".$db->escape_string($nombre)."' AND imagen=''");
        }
        foreach (op_ficha_seed_oficios_imagenes() as $nombre => $imagen) {
            $db->update_query('op_oficios_catalogo', array('imagen' => $db->escape_string($imagen)), "nombre='".$db->escape_string($nombre)."' AND imagen=''");
        }
    }

    function op_ficha_is_installed()
    {
        global $db;
        // op_ficha_tablas_listas() solo comprueba que las tablas EXISTAN, no
        // que tengan tal o cual columna — así que si el día de mañana se
        // añade otra columna a una tabla ya existente, hay que sumarla aquí
        // explícitamente o _install() nunca se volverá a ejecutar para
        // sembrarla (aprendido con el backfill de op_costes_oficio /
        // op_niveles_requeridos de esta misma sesión).
        return op_ficha_tablas_listas()
            && $db->field_exists('imagen', 'op_disciplinas')
            && $db->field_exists('imagen', 'op_oficios_catalogo');
    }

    function op_ficha_uninstall()
    {
        global $db;
        foreach (op_ficha_tablas() as $tabla) {
            $db->drop_table($tabla);
        }
    }

    function op_ficha_activate() {}
    function op_ficha_deactivate() {}
}
