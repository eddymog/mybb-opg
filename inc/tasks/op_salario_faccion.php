<?php
/** Reparte el salario de facción los lunes a las 06:00 de Madrid. */
function task_op_salario_faccion($task)
{
    require_once MYBB_ROOT . 'inc/plugins/lib/salario_faccion.php';

    if (!opg_sf_ventana_automatica(TIME_NOW)) {
        return;
    }

    $resultado = opg_sf_repartir(0, 'Sistema automático', 'automatico', TIME_NOW);
    $detalle = 'Salario de facción, semana ' . $resultado['semana'] . ': ' . $resultado['mensaje'];
    if (isset($resultado['total'])) {
        $detalle .= ' Total: ' . number_format((int) $resultado['total'], 0, ',', '.') . ' berries.';
    }
    add_task_log($task, $detalle);
}
