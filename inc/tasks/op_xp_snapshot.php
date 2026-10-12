<?php
/**
 * Tarea programada (registrada por inc/plugins/op_tasks.php): una vez al dia guarda el
 * newpoints de quienes postearon en rol en las ultimas 2 semanas y purga lo antiguo.
 * Ver inc/plugins/lib/xp_snapshot.php.
 */
function task_op_xp_snapshot($task)
{
    require_once MYBB_ROOT . 'inc/plugins/lib/xp_snapshot.php';

    $filas = opg_xp_tomar_snapshot();
    $purgadas = opg_xp_purgar();

    if (function_exists('add_task_log')) {
        add_task_log($task, "op_xp_snapshot: $filas usuario(s) guardados, $purgadas fila(s) antigua(s) borradas.");
    }
}
