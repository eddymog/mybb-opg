<?php
/**
 * OPG - Tareas en segundo plano (op_tasks)
 *
 * Registra las tareas programadas del foro: `op_worldtick` (inc/tasks/op_worldtick.php, rango,
 * fama y nivel de los personajes), `aventurasux` y `op_xp_snapshot`.
 *
 * Los entrenamientos (experiencia, oficios y tecnicas) NO se resuelven en segundo plano: el
 * jugador los reclama a mano en su pagina.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_tasks_info()
{
    return array(
        'name'          => 'OPG - Tareas en segundo plano (worldtick)',
        'description'   => 'Registra las tareas programadas del foro (rango, fama y nivel, mantenimiento de aventuras y snapshot de XP).',
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
                'title'       => 'OPG: Rango, fama y nivel (worldtick)',
                'description' => 'Resuelve rango, fama y nivel de los personajes.',
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
        op_tasks_asegurar_tarea('op_xp_snapshot', array(
            'title'       => 'OPG: Snapshot diario de XP',
            'description' => 'Guarda el newpoints de quienes postearon en rol en las ultimas 2 semanas (op/staff/xp_snapshot.php).',
            'minute'      => '30',
            'hour'        => '5',
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
        $db->delete_query('tasks', "file IN ('op_worldtick','aventurasux','op_xp_snapshot')");
    }

    function op_tasks_activate() { op_tasks_install(); }
    function op_tasks_deactivate() {}
}
