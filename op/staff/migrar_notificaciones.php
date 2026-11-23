<?php
/**
 * Staff (solo administradores): migrar las notificaciones antiguas del Tablon de aventuras al sistema global (issue #10).
 *
 * Que hace (logica en inc/plugins/lib/notificaciones_migracion.php):
 *  - Al abrir la pagina (GET) solo SIMULA: cuenta las filas de mybb_op_aventuras_notificaciones, cuantas ya estan en
 *    mybb_op_notificaciones y cuantas se copiarian, por tipo. No escribe nada.
 *  - El boton "Ejecutar la migracion" (POST con la clave CSRF) copia por lotes de 500 con INSERT IGNORE y, al terminar,
 *    reconcilia el contador de los usuarios con alguna notificacion antigua sin leer. Cada peticion trabaja como mucho
 *    unos 20 segundos; si queda trabajo, la pagina se reenvia sola para seguir donde se quedo.
 *  - Es idempotente: relanzarla no duplica nada (clave unica uid + clave_evento) y solo vuelve a recontar contadores.
 *  - No borra ni modifica la tabla antigua.
 *
 * Cuando ejecutarla: justo despues de subir la issue #9 (las dos se despliegan juntas). Mejor en un momento de poco
 * movimiento, aunque es segura con el foro abierto.
 *
 * Reversion (solo si hiciera falta volver atras; la tabla antigua sigue intacta). Borra las filas copiadas, que se
 * reconocen por tener la misma uid, clave de evento y fecha que una fila antigua, y deja que los contadores se recuenten:
 *
 *   DELETE n FROM mybb_op_notificaciones n
 *     JOIN mybb_op_aventuras_notificaciones a
 *       ON a.uid = n.uid AND n.clave_evento = CONVERT(a.clave_evento USING utf8mb4) COLLATE utf8mb4_unicode_ci
 *       AND a.created_at = n.created_at;
 *   UPDATE mybb_op_notificaciones_contador SET actualizado = 0
 *     WHERE uid IN (SELECT DISTINCT uid FROM mybb_op_aventuras_notificaciones);
 *
 * (con actualizado = 0, opg_notif_contar recuenta el contador de cada usuario la proxima vez que se lea).
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'migrar_notificaciones.php');
require_once './../../global.php';
require_once MYBB_ROOT . 'op/functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_migracion.php';

global $mybb, $db;

if ((int) $mybb->user['uid'] <= 0 || (int) $mybb->usergroup['cancp'] !== 1) {
    error_no_permission();
}

/** Segundos de trabajo por peticion antes de reenviar la pagina. */
const MIG_PRESUPUESTO = 20;

function mig_h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function mig_n($n)
{
    return number_format((int) $n, 0, ',', '.');
}

$ejecutar = $mybb->request_method === 'post';
if ($ejecutar) {
    verify_post_check($mybb->get_input('my_post_key'));
}

$html = array();
$siguiente = null;   // campos ocultos para continuar, si queda trabajo
$error = null;

if (!opg_notif_mig_tablas_listas()) {
    $error = 'Faltan tablas: hacen falta mybb_op_aventuras_notificaciones, mybb_op_notificaciones y mybb_op_notificaciones_contador '
        . '(ejecutar antes docs/notificaciones_migration.sql).';
} elseif (!$ejecutar) {
    try {
        $e = opg_notif_mig_estado();
        $html[] = '<p class="aviso">Simulación: no se ha escrito nada.</p>';
        $html[] = '<table><tr><th>Filas en la tabla antigua</th><td>' . mig_n($e['total']) . '</td></tr>'
            . '<tr><th>Ya están en el sistema global</th><td>' . mig_n($e['ya_copiadas']) . '</td></tr>'
            . '<tr><th>Se copiarían</th><td><strong>' . mig_n($e['pendientes']) . '</strong></td></tr>'
            . '<tr><th>No se pueden copiar (sin usuario o sin clave)</th><td>' . mig_n($e['invalidas']) . '</td></tr>'
            . '<tr><th>Usuarios cuyo contador se recontará</th><td>' . mig_n($e['usuarios_sin_leer']) . '</td></tr></table>';
        if ($e['por_tipo']) {
            $filas = '';
            foreach ($e['por_tipo'] as $t) {
                $filas .= '<tr><td>' . mig_h($t['tipo']) . ($t['en_catalogo'] ? '' : ' <span class="aviso">(no está en el catálogo)</span>') . '</td><td>'
                    . mig_n($t['n']) . '</td><td>' . mig_h($t['categoria']) . '</td><td>' . mig_h($t['origen']) . '</td></tr>';
            }
            $html[] = '<h3>Se copiarían, por tipo</h3><table><tr><th>Tipo</th><th>Filas</th><th>Categoría</th><th>Origen</th></tr>' . $filas . '</table>';
        }
        $html[] = '<form method="post"><input type="hidden" name="my_post_key" value="' . mig_h($mybb->post_code) . '">'
            . '<p><button type="submit">Ejecutar la migración</button></p>'
            . '<p class="nota">Se puede relanzar sin riesgo: no duplica nada. La tabla antigua no se borra.</p></form>';
    } catch (Throwable $ex) {
        $error = 'Error al leer la situación: ' . $ex->getMessage();
    }
} else {
    // Estado que viaja entre peticiones (solo enteros).
    $fase = $mybb->get_input('fase') === 'reconciliar' ? 'reconciliar' : 'copiar';
    $desde_id = $mybb->get_input('desde_id', MyBB::INPUT_INT);
    $desde_uid = $mybb->get_input('desde_uid', MyBB::INPUT_INT);
    $acum = array();
    foreach (array('leidas', 'copiadas', 'existian', 'omitidas', 'reconciliados', 'fallidos', 'lotes') as $k) {
        $acum[$k] = max(0, $mybb->get_input($k, MyBB::INPUT_INT));
    }
    $fallidos = array();
    $inicio = microtime(true);
    @set_time_limit(120);
    try {
        while ($fase === 'copiar' && microtime(true) - $inicio < MIG_PRESUPUESTO) {
            $r = opg_notif_mig_lote($desde_id);
            $acum['lotes']++;
            foreach (array('leidas', 'copiadas', 'existian', 'omitidas') as $k) {
                $acum[$k] += $r[$k];
            }
            $desde_id = $r['ultimo_id'];
            if ($r['fin']) {
                $fase = 'reconciliar';
            }
        }
        while ($fase === 'reconciliar' && microtime(true) - $inicio < MIG_PRESUPUESTO) {
            $r = opg_notif_mig_reconciliar($desde_uid);
            $acum['reconciliados'] += $r['reconciliados'];
            $acum['fallidos'] += count($r['fallidos']);
            $fallidos = array_merge($fallidos, $r['fallidos']);
            $desde_uid = $r['ultimo_uid'];
            if ($r['fin']) {
                $fase = 'fin';
            }
        }
    } catch (Throwable $ex) {
        $error = 'Error de base de datos tras el id ' . (int) $desde_id . ': ' . $ex->getMessage()
            . '. Lo ya copiado queda bien; se puede relanzar la migración desde el principio sin duplicar nada.';
    }

    $html[] = '<table><tr><th>Lotes procesados</th><td>' . mig_n($acum['lotes']) . '</td></tr>'
        . '<tr><th>Filas antiguas leídas</th><td>' . mig_n($acum['leidas']) . '</td></tr>'
        . '<tr><th>Copiadas ahora</th><td><strong>' . mig_n($acum['copiadas']) . '</strong></td></tr>'
        . '<tr><th>Ya existían (no se tocan)</th><td>' . mig_n($acum['existian']) . '</td></tr>'
        . '<tr><th>No se pudieron copiar (sin usuario o sin clave)</th><td>' . mig_n($acum['omitidas']) . '</td></tr>'
        . '<tr><th>Contadores recontados</th><td>' . mig_n($acum['reconciliados']) . '</td></tr>'
        . '<tr><th>Contadores que fallaron</th><td>' . mig_n($acum['fallidos']) . '</td></tr></table>';
    if ($fallidos) {
        $html[] = '<p class="aviso">Usuarios cuyo contador no se pudo recontar en esta tanda (se recontará solo en 24 horas, '
            . 'o al relanzar la migración): ' . mig_h(implode(', ', $fallidos)) . '</p>';
    }

    if ($error === null && $fase !== 'fin') {
        $siguiente = array('my_post_key' => $mybb->post_code, 'fase' => $fase, 'desde_id' => (int) $desde_id, 'desde_uid' => (int) $desde_uid) + $acum;
        $html[] = '<p class="aviso">Queda trabajo (' . ($fase === 'copiar' ? 'copiando, último id ' . (int) $desde_id : 'recontando contadores')
            . '). La página sigue sola en un momento; si no, pulsar "Continuar".</p>';
    } elseif ($error === null) {
        $html[] = '<p class="ok">Migración terminada.</p>';
        if (function_exists('log_audit')) {
            log_audit((int) $mybb->user['uid'], $db->escape_string($mybb->user['username']), 'migracion_notificaciones',
                $db->escape_string('Migración del Tablón: ' . $acum['copiadas'] . ' copiadas, ' . $acum['existian'] . ' ya existían, '
                    . $acum['omitidas'] . ' omitidas, ' . $acum['reconciliados'] . ' contadores recontados.'));
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>Migrar notificaciones del Tablón</title><style>'
    . 'body{font-family:system-ui,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;background:#14161a;color:#e8e8e8}'
    . 'table{border-collapse:collapse;margin:12px 0}th,td{border:1px solid #3a3f48;padding:6px 10px;text-align:left}'
    . '.aviso{color:#f0b54a}.ok{color:#6fd46f}.error{color:#ff6b6b}.nota{color:#9aa3ad;font-size:.9em}'
    . 'button{padding:8px 16px;font-size:1em;cursor:pointer}</style></head><body>'
    . '<h2>Migrar las notificaciones del Tablón al sistema global</h2>'
    . '<p class="nota">Copia mybb_op_aventuras_notificaciones a mybb_op_notificaciones conservando la fecha, el estado de lectura y la clave de evento.</p>';
if ($error !== null) {
    echo '<p class="error">' . mig_h($error) . '</p>';
}
echo implode("\n", $html);
if ($siguiente !== null) {
    echo '<form method="post" id="seguir">';
    foreach ($siguiente as $k => $v) {
        echo '<input type="hidden" name="' . mig_h($k) . '" value="' . mig_h($v) . '">';
    }
    echo '<p><button type="submit">Continuar</button></p></form>'
        . '<script>setTimeout(function () { document.getElementById("seguir").submit(); }, 1500);</script>';
}
echo '<p><a href="migrar_notificaciones.php">Volver a la simulación</a></p></body></html>';
