<?php
/**
 * Tarea de retencion y reconciliacion con MySQL real (issue #14, docs/208 apartado 10).
 *
 * Usa su propia base (opg_notif_n14) en el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php). Cubre:
 * borrado por antiguedad (90 dias, leidas y no leidas, en varios lotes), recorte a 500 por usuario (leidas primero y
 * despues las no leidas mas antiguas), contador correcto de los afectados, reconciliacion de contadores viejos con
 * actividad reciente (y no de los inactivos), una segunda ejecucion sin cambios, tablas ausentes sin error, el registro
 * idempotente en mybb_tasks con su SQL inverso comentado, y que ninguna pagina, plugin ni libreria carga la tarea.
 *
 *   php tests/notificaciones/retencion_db.php
 */
require __DIR__ . '/bootstrap_db.php';

$db = nt_preparar('opg_notif_n14');
require_once MYBB_ROOT . 'inc/tasks/notificaciones.php';

$N = TABLE_PREFIX . 'op_notificaciones';
$C = TABLE_PREFIX . 'op_notificaciones_contador';
$ahora = time();
$dia = 86400;

/** Inserta $n filas para $uid con created_at = $desde + i, leidas o no, en un solo INSERT. */
function nt_filas($uid, $n, $desde, $leidas, $prefijo, $importante = false)
{
    global $N;
    $valores = array();
    for ($i = 0; $i < $n; $i++) {
        $valores[] = '(' . (int) $uid . ", 'aventura_denegada', 'aventuras', 'gestion', '" . ($importante ? 'importante' : 'normal') . "', 0, 0, '"
            . $prefijo . ':' . $i . "', " . ((int) $desde + $i) . ', ' . ($leidas ? (int) $desde + $i + 1 : 'NULL') . ')';
    }
    foreach (array_chunk($valores, 500) as $parte) {
        nt_sql("INSERT INTO `$N` (uid, tipo, categoria, origen, importancia, agrupable, actor_uid, clave_evento, created_at, leida_en) VALUES "
            . implode(',', $parte));
    }
}

function nt_contador($uid)
{
    global $C;
    $f = nt_una("SELECT sin_leer, importantes FROM `$C` WHERE uid = " . (int) $uid);
    return $f ? array((int) $f['sin_leer'], (int) $f['importantes']) : null;
}

function nt_cuantas($uid, $extra = '')
{
    global $N;
    return (int) nt_valor("SELECT COUNT(*) FROM `$N` WHERE uid = " . (int) $uid . ($extra !== '' ? " AND $extra" : ''));
}

// ── Datos ───────────────────────────────────────────────────────────────────────────────────────────────────────────

// Usuario 1: 2300 filas antiguas (91 a 100 dias; mas de dos lotes), la mitad no leidas, y 10 recientes no leidas.
nt_filas(1, 1150, $ahora - 100 * $dia, false, 'viejas_nl', true);
nt_filas(1, 1150, $ahora - 95 * $dia, true, 'viejas_l');
nt_filas(1, 10, $ahora - $dia, false, 'nuevas');
// Contador desfasado a proposito (cuenta tambien las antiguas no leidas) y recien reconciliado: el paso 3 debe corregirlo.
nt_sql("INSERT INTO `$C` (uid, sin_leer, importantes, actualizado) VALUES (1, 1160, 1150, $ahora)");
// Frontera: una fila de exactamente 89 dias se conserva.
nt_filas(2, 1, $ahora - 89 * $dia, false, 'frontera');

// Usuario 3: 600 filas recientes; las 300 mas antiguas no leidas, despues 250 leidas y 50 no leidas mas nuevas.
// Hay que borrar 100: las 100 leidas mas antiguas, aunque haya no leidas mas antiguas que ellas.
nt_filas(3, 300, $ahora - 30 * $dia, false, 'a_nl');
nt_filas(3, 250, $ahora - 20 * $dia, true, 'b_l');
nt_filas(3, 50, $ahora - 10 * $dia, false, 'c_nl');
// Usuario 4: 520 filas, solo 5 leidas: borra las 5 leidas y las 15 no leidas mas antiguas.
nt_filas(4, 515, $ahora - 40 * $dia, false, 'd_nl', true);
nt_filas(4, 5, $ahora - 5 * $dia, true, 'd_l');
// Usuario 5: exactamente 500: no se toca.
nt_filas(5, 500, $ahora - 50 * $dia, false, 'e_nl');
nt_sql("INSERT INTO `$C` (uid, sin_leer, importantes, actualizado) VALUES (5, 500, 0, $ahora)");

// Usuario 6: contador viejo (2 dias) y desfasado, con actividad reciente: se reconcilia.
nt_filas(6, 3, $ahora - 2 * $dia, false, 'f_nl');
nt_sql("INSERT INTO `$C` (uid, sin_leer, importantes, actualizado) VALUES (6, 99, 7, " . ($ahora - 2 * $dia) . ')');
// Usuario 7: sin fila de contador y con actividad reciente: se crea.
nt_filas(7, 2, $ahora - 3 * $dia, false, 'g_nl');
// Usuario 8: contador viejo pero sin actividad reciente (ultima hace 30 dias): no se toca.
nt_filas(8, 4, $ahora - 30 * $dia, false, 'h_nl');
nt_sql("INSERT INTO `$C` (uid, sin_leer, importantes, actualizado) VALUES (8, 42, 0, " . ($ahora - 10 * $dia) . ')');
// Usuario 9: contador reciente (hace una hora) y desfasado: no se toca (lo repara opg_notif_contar dentro de 24 horas).
nt_filas(9, 1, $ahora - $dia, false, 'i_nl');
nt_sql("INSERT INTO `$C` (uid, sin_leer, importantes, actualizado) VALUES (9, 5, 0, " . ($ahora - 3600) . ')');

// ── Ejecucion ───────────────────────────────────────────────────────────────────────────────────────────────────────

$db->sentencias = array();
$r = opg_notif_tarea_ejecutar();

nt_prueba('sin error', $r['error'], '');
nt_prueba('borradas por antiguedad', $r['antiguas'], 2300);
nt_prueba('recortadas por exceso (100 + 20)', $r['recortadas'], 120);
nt_prueba('usuarios recortados', $r['usuarios_recortados'], 2);
nt_prueba('sin fallos de reconciliacion', $r['fallos'], 0);

// Borrado por lotes de 1000 con LIMIT.
$borrados = array_values(array_filter($db->sentencias, function ($s) {
    return stripos($s, 'DELETE') === 0;
}));
$lecturas_lote = array_values(array_filter($db->sentencias, function ($s) {
    return strpos($s, 'WHERE created_at <') !== false && strpos($s, 'LIMIT ' . OPG_NOTIF_TAREA_LOTE) !== false;
}));
nt_prueba('tres lotes de lectura por antiguedad (1000 + 1000 + 300)', count($lecturas_lote), 3);
nt_ok(count($borrados) >= 5, 'borrados en varias sentencias (3 por antiguedad y al menos 1 por usuario)');
$max_ids = 0;
foreach ($borrados as $s) {
    if (preg_match('/IN \(([0-9,]+)\)/', $s, $m)) {
        $max_ids = max($max_ids, count(explode(',', $m[1])));
    }
}
nt_prueba('ningun borrado pasa de 1000 filas', $max_ids <= 1000, true);

// 90 dias: leidas y no leidas.
nt_prueba('u1: no quedan antiguas no leidas', nt_cuantas(1, "clave_evento LIKE 'viejas_nl:%'"), 0);
nt_prueba('u1: no quedan antiguas leidas', nt_cuantas(1, "clave_evento LIKE 'viejas_l:%'"), 0);
nt_prueba('u1: quedan las 10 recientes', nt_cuantas(1), 10);
nt_prueba('u1: contador corregido (10 no leidas, 0 importantes)', nt_contador(1), array(10, 0));
nt_prueba('u2: la fila de 89 dias se conserva', nt_cuantas(2), 1);
nt_prueba('ninguna fila de mas de 90 dias', (int) nt_valor("SELECT COUNT(*) FROM `$N` WHERE created_at < " . ($ahora - 90 * $dia)), 0);

// 500 por usuario, leidas primero.
nt_prueba('u3: queda en 500', nt_cuantas(3), 500);
nt_prueba('u3: conserva las 300 no leidas antiguas', nt_cuantas(3, "clave_evento LIKE 'a_nl:%'"), 300);
nt_prueba('u3: borra las 100 leidas mas antiguas', nt_cuantas(3, "clave_evento LIKE 'b_l:%'"), 150);
nt_prueba('u3: las leidas que quedan son las mas nuevas', (int) nt_valor("SELECT MIN(created_at) FROM `$N` WHERE uid = 3 AND clave_evento LIKE 'b_l:%'"),
    $ahora - 20 * $dia + 100);
nt_prueba('u3: contador (350 no leidas)', nt_contador(3), array(350, 0));
nt_prueba('u4: queda en 500', nt_cuantas(4), 500);
nt_prueba('u4: no quedan leidas', nt_cuantas(4, 'leida_en IS NOT NULL'), 0);
nt_prueba('u4: borra las 15 no leidas mas antiguas', (int) nt_valor("SELECT MIN(created_at) FROM `$N` WHERE uid = 4"), $ahora - 40 * $dia + 15);
nt_prueba('u4: contador (500 no leidas, 500 importantes)', nt_contador(4), array(500, 500));
nt_prueba('u5: con 500 justas no se toca', nt_cuantas(5), 500);

// Reconciliacion de contadores viejos con actividad reciente.
nt_prueba('u6: contador viejo con actividad reconciliado', nt_contador(6), array(3, 0));
nt_prueba('u6: actualizado renovado', (int) nt_valor("SELECT actualizado FROM `$C` WHERE uid = 6") >= $ahora, true);
nt_prueba('u7: contador creado', nt_contador(7), array(2, 0));
nt_prueba('u8: sin actividad reciente no se toca', nt_contador(8), array(42, 0));
nt_prueba('u9: contador reciente no se toca', nt_contador(9), array(5, 0));
// Reconciliados: 1 (antiguedad), 3 y 4 (recorte), 6 y 7 (viejos). 2 no perdio filas ni tiene actividad reciente, 5 y 9
// tienen contador reciente y 8 no tiene actividad.
nt_prueba('reconciliados', $r['reconciliados'], 5);

// Segunda ejecucion: nada que hacer.
$r2 = opg_notif_tarea_ejecutar();
nt_prueba('segunda ejecucion sin cambios', array($r2['antiguas'], $r2['recortadas'], $r2['reconciliados'], $r2['error']), array(0, 0, 0, ''));

// Punto de entrada del planificador: escribe el registro con add_task_log.
$GLOBALS['nt_task_log'] = array();
function add_task_log($task, $mensaje)
{
    $GLOBALS['nt_task_log'][] = $mensaje;
}
task_notificaciones(array('tid' => 1));
nt_prueba('task_notificaciones registra una linea', count($GLOBALS['nt_task_log']), 1);
nt_ok(strpos($GLOBALS['nt_task_log'][0], 'Notificaciones: 0 borradas') === 0, 'texto del registro de la tarea');

// ── Registro en mybb_tasks ──────────────────────────────────────────────────────────────────────────────────────────

nt_sql('CREATE TABLE `' . TABLE_PREFIX . "tasks` (tid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(120) NOT NULL DEFAULT '',
    description TEXT NOT NULL, file VARCHAR(30) NOT NULL DEFAULT '', minute VARCHAR(200) NOT NULL DEFAULT '', hour VARCHAR(200) NOT NULL DEFAULT '',
    day VARCHAR(100) NOT NULL DEFAULT '', month VARCHAR(30) NOT NULL DEFAULT '', weekday VARCHAR(15) NOT NULL DEFAULT '',
    nextrun INT UNSIGNED NOT NULL DEFAULT 0, lastrun INT UNSIGNED NOT NULL DEFAULT 0, enabled TINYINT(1) NOT NULL DEFAULT 1,
    logging TINYINT(1) NOT NULL DEFAULT 0, locked INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$sql_tarea = file_get_contents(MYBB_ROOT . 'docs/notificaciones_tarea_migration.sql');
nt_prueba('migracion de la tarea: primera vez', nt_ejecutar_script($db, $sql_tarea), null);
nt_prueba('migracion de la tarea: segunda vez', nt_ejecutar_script($db, $sql_tarea), null);
nt_prueba('la tarea queda una sola vez', (int) nt_valor('SELECT COUNT(*) FROM `' . TABLE_PREFIX . "tasks` WHERE file = 'notificaciones'"), 1);
$t = nt_una('SELECT minute, hour, day, month, weekday, enabled FROM `' . TABLE_PREFIX . "tasks` WHERE file = 'notificaciones'");
nt_prueba('diaria a las 04:30 y activa', array($t['minute'], $t['hour'], $t['day'], $t['month'], $t['weekday'], (int) $t['enabled']),
    array('30', '4', '*', '*', '*', 1));
nt_ok(is_file(MYBB_ROOT . 'inc/tasks/' . 'notificaciones' . '.php'), 'el archivo de la tarea existe');
$lineas = array_values(array_filter(array_map('trim', explode("\n", trim($sql_tarea)))));
nt_prueba('termina con el SQL inverso comentado', end($lineas), "-- DELETE FROM `mybb_tasks` WHERE `file` = 'notificaciones';");
nt_ejecutar_script($db, ltrim(end($lineas), '- '));
nt_prueba('el SQL inverso quita la tarea', (int) nt_valor('SELECT COUNT(*) FROM `' . TABLE_PREFIX . "tasks` WHERE file = 'notificaciones'"), 0);

// ── Nunca durante una peticion ──────────────────────────────────────────────────────────────────────────────────────

$cargan = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MYBB_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $archivo) {
    $ruta = str_replace(MYBB_ROOT, '', $archivo->getPathname());
    if (substr($ruta, -4) !== '.php' || preg_match('#^(tests|\.claude|cache|uploads|docs|inc/tasks)/#', $ruta)) {
        continue;
    }
    $txt = file_get_contents($archivo->getPathname());
    if (strpos($txt, 'tasks/notificaciones.php') !== false || strpos($txt, 'opg_notif_tarea_') !== false) {
        $cargan[] = $ruta;
    }
}
nt_prueba('ninguna pagina, plugin ni libreria carga la tarea', $cargan, array());

// ── Tablas ausentes ─────────────────────────────────────────────────────────────────────────────────────────────────

nt_sql("DROP TABLE `$C`");
nt_registro_iniciar();
$r3 = opg_notif_tarea_ejecutar();
nt_prueba('sin tablas: no hace nada y lo indica', array($r3['antiguas'], $r3['error'] !== ''), array(0, true));

nt_borrar();
nt_resumen('retencion_db');
