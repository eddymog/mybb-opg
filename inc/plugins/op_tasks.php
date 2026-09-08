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
    function op_tasks_install()
    {
        global $db;
        if (!op_tasks_is_installed()) {
            $new_task = array(
                'title'       => 'OPG: Resolver colas (worldtick)',
                'description' => 'Resuelve entrenamientos de estadística, oficio y técnicas cuyo tiempo ya venció.',
                'file'        => 'op_worldtick',
                'minute'      => '*/5',
                'hour'        => '*',
                'day'         => '*',
                'month'       => '*',
                'weekday'     => '*',
                'enabled'     => 1,
                'logging'     => 1,
            );
            $new_task['nextrun'] = 0;
            $db->insert_query('tasks', $new_task);
        }
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
        $db->delete_query('tasks', "file='op_worldtick'");
    }

    function op_tasks_activate() {}
    function op_tasks_deactivate() {}
}
