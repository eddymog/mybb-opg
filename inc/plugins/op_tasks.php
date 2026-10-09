<?php
/**
 * OPG - Tareas en segundo plano (op_tasks)
 *
 * Registra la tarea programada `op_worldtick` (inc/tasks/op_worldtick.php),
 * que resuelve en segundo plano las colas de entrenamiento de estadística,
 * oficio y técnicas cuyo tiempo ya venció — sin sustituir la resolución al
 * cargar la página correspondiente (que sigue funcionando exactamente igual),
 * solo evita que una recompensa quede "pendiente de reclamar" indefinidamente
 * si el jugador no vuelve a esa página.
 *
 * Alcance de esta primera versión: solo entrenamiento.php, oficios.php y
 * entrenamiento_tecnicas.php. crafteo.php y creacion.php (que clonan objetos
 * dinámicamente) quedan fuera a propósito por ahora — economía de items en
 * vivo, mejor abordarlo aparte con más cuidado.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_tasks_info()
{
    return array(
        'name'          => 'OPG - Tareas en segundo plano (worldtick)',
        'description'   => 'Registra la tarea programada que resuelve en segundo plano entrenamientos/oficios/técnicas vencidos, sin sustituir la resolución al cargar página.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

if (defined('IN_ADMINCP')) {
    function op_tasks_asegurar_tarea($file, $datos)
    {
        global $db;
        $query = $db->simple_select('tasks', 'tid', "file='" . $db->escape_string($file) . "'");
        if (!$db->fetch_field($query, 'tid')) {
            $datos['file'] = $file;
            $datos['nextrun'] = 0;
            $db->insert_query('tasks', $datos);
        }
    }

    function op_tasks_install()
    {
        op_tasks_asegurar_tarea('op_worldtick', array(
                'title'       => 'OPG: Resolver colas (worldtick)',
                'description' => 'Resuelve entrenamientos de estadística, oficio y técnicas cuyo tiempo ya venció.',
                'minute'      => '0,5,10,15,20,25,30,35,40,45,50,55',
                'hour'        => '*',
                'day'         => '*',
                'month'       => '*',
                'weekday'     => '*',
                'enabled'     => 1,
                'logging'     => 1,
        ));
        op_tasks_asegurar_tarea('aventurasux', array(
            'title'       => 'OPG: Mantenimiento de aventuras',
            'description' => 'Normaliza participantes y procesa vencimientos y avisos del tablón de aventuras.',
            'minute'      => '15',
            'hour'        => '*',
            'day'         => '*',
            'month'       => '*',
            'weekday'     => '*',
            'enabled'     => 1,
            'logging'     => 1,
        ));
    }

    function op_tasks_is_installed()
    {
        global $db;
        $query = $db->simple_select('tasks', 'tid', "file='op_worldtick'");
        return (bool)$db->fetch_field($query, 'tid');
    }

    function op_tasks_uninstall()
    {
        global $db;
        $db->delete_query('tasks', "file IN ('op_worldtick','aventurasux')");
    }

    function op_tasks_activate() { op_tasks_install(); }
    function op_tasks_deactivate() {}
}
