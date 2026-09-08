<?php
/**
 * OPG - Niveles (BD)
 *
 * Crea la tabla `mybb_op_niveles` (experiencia mínima/máxima y puntos de
 * estadística de bonus por nivel, 1-100), gestionable desde Configuración
 * del foro → OPG Niveles. Antes eran dos arrays hardcodeados dentro de
 * op/personaje.php ($exp_tabla, $puntos_bonus_nivel) — ahora tanto esa
 * página como inc/tasks/op_worldtick.php (subida/bajada de nivel en segundo
 * plano) leen de aquí.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_niveles_info()
{
    return array(
        'name'          => 'OPG - Niveles (BD)',
        'description'   => 'Tabla de experiencia/nivel y puntos de bonus del juego, gestionable desde Configuración del foro → OPG Niveles.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

/**
 * [exp_min, exp_max] por nivel (1-100), tal y como existían hardcodeados en
 * op/personaje.php.
 */
function op_niveles_seed_exp()
{
    return array(
         1 => [0,50],        2 => [50,125],      3 => [125,225],    4 => [225,350],    5 => [350,500],
         6 => [500,675],     7 => [675,875],     8 => [875,1100],   9 => [1100,1360], 10 => [1360,1650],
        11 => [1650,1970],  12 => [1970,2320],  13 => [2320,2700], 14 => [2700,3110], 15 => [3110,3550],
        16 => [3550,4020],  17 => [4020,4520],  18 => [4520,5050], 19 => [5050,5625], 20 => [5625,6240],
        21 => [6240,6895],  22 => [6895,7590],  23 => [7590,8325], 24 => [8325,9100], 25 => [9100,9915],
        26 => [9915,10770], 27 => [10770,11665],28 => [11665,12600],29 => [12600,13590],30 => [13590,14630],
        31 => [14630,15720],32 => [15720,16860],33 => [16860,18050],34 => [18050,19290],35 => [19290,20580],
        36 => [20580,21920],37 => [21920,23310],38 => [23310,24750],39 => [24750,26260],40 => [26260,27830],
        41 => [27830,29460],42 => [29460,31150],43 => [31150,32900],44 => [32900,34710],45 => [34710,36580],
        46 => [36580,38510],47 => [38510,40500],48 => [40500,42550],49 => [42550,44680],50 => [44680,46880],
        51 => [46880,49150],52 => [49150,51490],53 => [51490,53900],54 => [53900,56380],55 => [56380,58930],
        56 => [58930,61550],57 => [61550,64240],58 => [64240,67000],59 => [67000,69850],60 => [69850,72780],
        61 => [72780,75790],62 => [75790,78880],63 => [78880,82050],64 => [82050,85300],65 => [85300,88630],
        66 => [88630,92040],67 => [92040,95530],68 => [95530,99100],69 => [99100,102770],70 => [102770,106530],
        71 => [106530,110380],72 => [110380,114320],73 => [114320,118350],74 => [118350,122470],75 => [122470,126680],
        76 => [126680,130980],77 => [130980,135370],78 => [135370,139850],79 => [139850,144450],80 => [144450,149150],
        81 => [149150,153950],82 => [153950,158850],83 => [158850,163850],84 => [163850,168950],85 => [168950,174150],
        86 => [174150,179450],87 => [179450,184850],88 => [184850,190350],89 => [190350,196000],90 => [196000,201800],
        91 => [201800,207750],92 => [207750,213850],93 => [213850,220100],94 => [220100,226600],95 => [226600,233350],
        96 => [233350,240350],97 => [240350,247650],98 => [247650,255250],99 => [255250,263250],100 => [263250,300000],
    );
}

/**
 * Puntos de estadística extra al subir a cada nivel de decena (10, 20,
 * 30...100); el resto de niveles dan 10 por defecto (NULL en la BD).
 */
function op_niveles_seed_bono()
{
    return array(10 => 20, 20 => 20, 30 => 20, 40 => 20, 50 => 20, 60 => 20, 70 => 20, 80 => 20, 90 => 20, 100 => 20);
}

/**
 * [exp_min, exp_max] por nivel, para op/personaje.php e
 * inc/tasks/op_worldtick.php. Cae al array hardcodeado de siempre si la
 * tabla no existiera todavía por lo que sea.
 */
function op_niveles_tabla()
{
    global $db;

    if ($db->table_exists('op_niveles')) {
        $tabla = array();
        $query = $db->simple_select('op_niveles', 'nivel, exp_min, exp_max', '', array('order_by' => 'nivel'));
        while ($fila = $db->fetch_array($query)) {
            $tabla[(int)$fila['nivel']] = array((int)$fila['exp_min'], (int)$fila['exp_max']);
        }
        if (!empty($tabla)) {
            return $tabla;
        }
    }

    return op_niveles_seed_exp();
}

/**
 * Puntos de bonus por nivel (solo los niveles con un valor explícito
 * configurado — el resto siguen usando el "?? 10" de cada punto de uso).
 * Cae al array hardcodeado de siempre si la tabla no existiera todavía.
 */
function op_niveles_bono_puntos()
{
    global $db;

    if ($db->table_exists('op_niveles')) {
        $bonos = array();
        $any_row = false;
        $query = $db->simple_select('op_niveles', 'nivel, bono_puntos', '', array('order_by' => 'nivel'));
        while ($fila = $db->fetch_array($query)) {
            $any_row = true;
            if (!is_null($fila['bono_puntos'])) {
                $bonos[(int)$fila['nivel']] = (int)$fila['bono_puntos'];
            }
        }
        if ($any_row) {
            return $bonos;
        }
    }

    return op_niveles_seed_bono();
}

/**
 * Techo temporal de nivel: el nivel marcado como "límite temporal" más bajo
 * (si hay varios marcados, manda el más bajo), o null si no hay ninguno
 * marcado (sin techo, más allá de lo que ya cubra la tabla). Se llega hasta
 * ese nivel pero no se puede pasar de él, aunque el personaje tenga
 * limite_nivel personal comprado por encima — es un techo global, no
 * sustituye al límite personal, lo recorta.
 */
function op_niveles_limite_temporal()
{
    global $db;

    if ($db->table_exists('op_niveles') && $db->field_exists('limite_temporal', 'op_niveles')) {
        $fila = $db->fetch_array($db->simple_select('op_niveles', 'MIN(nivel) AS nivel', 'limite_temporal=1'));
        if ($fila && $fila['nivel'] !== null) {
            return (int)$fila['nivel'];
        }
    }

    return null;
}

if (defined('IN_ADMINCP')) {
    function op_niveles_install()
    {
        global $db;

        if (!$db->table_exists('op_niveles')) {
            $db->write_query("
                CREATE TABLE mybb_op_niveles (
                    nivel INT NOT NULL,
                    exp_min INT NOT NULL DEFAULT 0,
                    exp_max INT NOT NULL DEFAULT 0,
                    bono_puntos INT NULL DEFAULT NULL,
                    limite_temporal TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (nivel)
                ) ENGINE=InnoDB
            ");
        }

        // Columna añadida en una segunda pasada. Si el plugin ya estaba
        // instalado de antes, esto la añade sin tocar lo demás.
        if (!$db->field_exists('limite_temporal', 'op_niveles')) {
            $db->add_column('op_niveles', 'limite_temporal', "TINYINT(1) NOT NULL DEFAULT 0");
        }

        $existing = $db->fetch_field($db->simple_select('op_niveles', 'nivel', '', array('limit' => 1)), 'nivel');

        if (!$existing) {
            $bonos = op_niveles_seed_bono();
            foreach (op_niveles_seed_exp() as $nivel => $rango) {
                $insert = array(
                    'nivel'   => (int)$nivel,
                    'exp_min' => (int)$rango[0],
                    'exp_max' => (int)$rango[1],
                    // El tope de 100 ya existía hardcodeado en op/personaje.php
                    // (min($ficha['limite_nivel'], 100)) — se siembra aquí para
                    // no cambiar el comportamiento actual al activar el plugin.
                    'limite_temporal' => ((int)$nivel === 100) ? 1 : 0,
                );
                if (isset($bonos[$nivel])) {
                    $insert['bono_puntos'] = (int)$bonos[$nivel];
                }
                $db->insert_query('op_niveles', $insert);
            }
        } elseif (op_niveles_limite_temporal() === null) {
            // Backfill para instalaciones que ya tuvieran la tabla sembrada
            // antes de que existiera esta columna: si nadie ha marcado ya un
            // límite temporal, se marca el nivel 100 para no cambiar el
            // comportamiento de golpe (era un tope fijo de 100 hasta ahora).
            $db->update_query('op_niveles', array('limite_temporal' => 1), "nivel='100'");
        }
    }

    function op_niveles_is_installed()
    {
        global $db;
        return $db->table_exists('op_niveles') && $db->field_exists('limite_temporal', 'op_niveles');
    }

    function op_niveles_uninstall()
    {
        global $db;
        $db->drop_table('op_niveles');
    }

    function op_niveles_activate() {}
    function op_niveles_deactivate() {}
}
