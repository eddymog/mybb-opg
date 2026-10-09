<?php
/** Mantenimiento del tablón: backfill, vencimientos y avisos próximos. */
function task_aventurasux($task)
{
    require_once MYBB_ROOT . 'inc/plugins/lib/aventuras.php';

    $migradas = 0;
    $filas = opg_av_todos('SELECT pa.id, pa.jugadores_json FROM ' . opg_av_t('op_peticionAventuras') . ' pa'
        . ' WHERE NOT EXISTS (SELECT 1 FROM ' . opg_av_t('op_aventuras_participantes')
        . ' ap WHERE ap.peticion_id = pa.id)'
        . " AND CASE WHEN JSON_VALID(pa.jugadores_json) THEN JSON_TYPE(pa.jugadores_json) = 'ARRAY' AND JSON_LENGTH(pa.jugadores_json) > 0 ELSE 0 END"
        . ' ORDER BY pa.id LIMIT 250');
    foreach ($filas as $fila) {
        $jugadores = json_decode((string) $fila['jugadores_json'], true);
        if (is_array($jugadores) && $jugadores && opg_av_sincronizar_participantes($fila['id'], opg_av_decodificar_jugadores($fila['jugadores_json']))) {
            $migradas++;
        }
    }

    $vencidas = opg_av_propuestas_vencer(opg_av_ahora());
    $desde = opg_av_ahora() - (OPG_AV_OFERTA_DIAS - 3) * 86400;
    $hasta = opg_av_ahora() - (OPG_AV_OFERTA_DIAS - 4) * 86400;
    $ofertas = opg_av_todos('SELECT uid, updated_at FROM ' . opg_av_t('op_narradores_disponibles')
        . ' WHERE updated_at >= ' . $desde . ' AND updated_at < ' . $hasta);
    foreach ($ofertas as $oferta) {
        opg_av_notificar('oferta:' . (int) $oferta['uid'] . ':vence:' . (int) $oferta['updated_at'], 0,
            $oferta['uid'], 0, 'oferta_por_vencer', array('dias' => 3));
    }

    if (function_exists('add_task_log')) {
        add_task_log($task, 'Aventuras UX: ' . $migradas . ' solicitudes migradas, ' . $vencidas
            . ' propuestas vencidas y ' . count($ofertas) . ' avisos de oferta.');
    }
}
