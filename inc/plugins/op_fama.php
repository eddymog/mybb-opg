<?php
/**
 * OPG - Fama (BD)
 *
 * Crea la tabla `mybb_op_fama`, que gestiona la pestaña "OPG Fama" de
 * Configuración del foro (admin/modules/forumconfig/fama.php). Antes era
 * un array hardcodeado (op_ficha_fama_tabla(), en inc/plugins/op_ficha.php)
 * usado tanto por op/personaje.php como por inc/tasks/op_worldtick.php —
 * ahora ambos leen de aquí, con la misma tabla ya sembrada con los 8 tramos
 * que existían.
 *
 * Cada fila es un tramo de reputación: si la reputación del personaje es
 * >= reputacion_min, se usa ese tramo (el de mayor reputacion_min que
 * cumpla, comprobados de mayor a menor); dentro del tramo, el % de
 * reputación positiva decide cuál de los 3 nombres se muestra.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_fama_info()
{
    return array(
        'name'          => 'OPG - Fama (BD)',
        'description'   => 'Tabla de tramos de fama del juego, gestionable desde Configuración del foro → OPG Fama.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

/**
 * Tramos de fama tal y como existían hardcodeados en op/personaje.php:
 * [reputación mínima, % negativo o menos, % positivo o más, nombre si es
 * mayoritariamente positivo, nombre neutral, nombre si es mayoritariamente
 * negativo].
 */
function op_fama_seed_data()
{
    return array(
        array(5000, 20, 80, 'Héroe',      'Leyenda',    'Calamidad'),
        array(3001, 20, 80, 'Paladín',    'Ícono',      'Pesadilla'),
        array(1501, 20, 80, 'Santo',      'Famoso',     'Infame'),
        array(601,  20, 80, 'Justiciero', 'Popular',    'Criminal'),
        array(301,  20, 80, 'Noble',      'Aspirante',  'Delincuente'),
        array(101,  20, 80, 'Honorable',  'Rumor',      'Forajido'),
        array(51,   -1, 101, 'Novato',    'Novato',     'Novato'),
        array(26,   -1, 101, 'Iniciado',  'Iniciado',   'Iniciado'),
    );
}

/**
 * Tabla de fama para op/personaje.php e inc/tasks/op_worldtick.php. Cae al
 * array hardcodeado de siempre si la tabla no existiera todavía por lo que
 * sea (plugin desactivado, etc.).
 */
function op_fama_tabla()
{
    global $db;

    if ($db->table_exists('op_fama')) {
        $filas = array();
        $query = $db->simple_select('op_fama', '*', '', array('order_by' => 'reputacion_min', 'order_dir' => 'DESC'));
        while ($fila = $db->fetch_array($query)) {
            $filas[] = array(
                (int)$fila['reputacion_min'],
                (int)$fila['perc_malo'],
                (int)$fila['perc_bueno'],
                $fila['nombre_bueno'],
                $fila['nombre_neutral'],
                $fila['nombre_malo'],
            );
        }
        if (!empty($filas)) {
            return $filas;
        }
    }

    return op_fama_seed_data();
}

if (defined('IN_ADMINCP')) {
    function op_fama_install()
    {
        global $db;

        if (!$db->table_exists('op_fama')) {
            $db->write_query("
                CREATE TABLE mybb_op_fama (
                    id INT NOT NULL AUTO_INCREMENT,
                    reputacion_min INT NOT NULL DEFAULT 0,
                    perc_malo INT NOT NULL DEFAULT 0,
                    perc_bueno INT NOT NULL DEFAULT 0,
                    nombre_bueno VARCHAR(60) NOT NULL DEFAULT '',
                    nombre_neutral VARCHAR(60) NOT NULL DEFAULT '',
                    nombre_malo VARCHAR(60) NOT NULL DEFAULT '',
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB
            ");
        }

        $existing = $db->fetch_field($db->simple_select('op_fama', 'id', '', array('limit' => 1)), 'id');
        if ($existing) {
            return;
        }

        foreach (op_fama_seed_data() as $fila) {
            $db->insert_query('op_fama', array(
                'reputacion_min' => (int)$fila[0],
                'perc_malo'      => (int)$fila[1],
                'perc_bueno'     => (int)$fila[2],
                'nombre_bueno'   => $db->escape_string($fila[3]),
                'nombre_neutral' => $db->escape_string($fila[4]),
                'nombre_malo'    => $db->escape_string($fila[5]),
            ));
        }
    }

    function op_fama_is_installed()
    {
        global $db;
        return $db->table_exists('op_fama');
    }

    function op_fama_uninstall()
    {
        global $db;
        $db->drop_table('op_fama');
    }

    function op_fama_activate() {}
    function op_fama_deactivate() {}
}
