<?php
/**
 * Rendimiento con volumen del sistema de notificaciones (issue #15, requisitos 30 y 38).
 *
 * Objetivo del requisito 38: con 10.000 usuarios y 500.000 notificaciones, el header anade menos de 2 ms y la pagina
 * responde en menos de 100 ms en el servidor. Esta prueba lo mide en el MySQL 5.7 de pruebas (Docker: es una referencia,
 * no los tiempos reales del hosting) y comprueba con EXPLAIN que las consultas usan los indices previstos.
 *
 * NO forma parte de la puerta por defecto: tarda alrededor de un minuto (genera 500.000 filas). Sin el argumento
 * --medir (o la variable OPG_NOTIF_RENDIMIENTO=1) termina enseguida con "OK ... omitida". Uso manual:
 *
 *   php tests/notificaciones/rendimiento_db.php --medir
 *
 * Usa su propia base (opg_notif_n15) y la borra al terminar. Datos generados:
 *   - 10.000 usuarios con contador; 500.000 filas con un reparto desigual: 50 usuarios con unas 600 (por encima del
 *     maximo de 500, para la tarea de retencion), 450 con unas 300 y 9.500 con unas 35.
 *   - created_at creciente con el id a lo largo de 100 dias (un 10 % pasa de los 90 dias de retencion).
 *   - 6 categorias, 3 origenes, un 25 % importantes, un 30 % sin leer, un 2 % agrupables y datos_json de unos 100 bytes.
 *
 * Los umbrales de tiempo son holgados (varias veces el objetivo) para que la prueba no sea fragil en una maquina cargada;
 * los numeros reales se imprimen y se publican en la issue. Lo que debe cumplirse siempre es el plan de EXPLAIN. Las
 * escrituras (marcar, enviar, la tarea) dependen sobre todo del coste de cada COMMIT en Docker y varian mucho si hay otras
 * pruebas corriendo a la vez; por eso sus umbrales son aun mas amplios.
 */

$medir = in_array('--medir', isset($argv) ? $argv : array(), true) || getenv('OPG_NOTIF_RENDIMIENTO') === '1';
if (!$medir) {
    echo "OK rendimiento_db: omitida (medicion manual: php tests/notificaciones/rendimiento_db.php --medir)\n";
    exit(0);
}

require __DIR__ . '/bootstrap_db.php';

$db = nt_preparar('opg_notif_n15');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/tasks/notificaciones.php';
nt_registro_iniciar();

$N = TABLE_PREFIX . 'op_notificaciones';
$C = TABLE_PREFIX . 'op_notificaciones_contador';
$ahora = time();
$dia = 86400;
$FILAS = 500000;
$USUARIOS = 10000;

// ── Generacion de datos (en MySQL, con INSERT ... SELECT sobre una tabla de numeros) ─────────────────────────────────

$t0 = microtime(true);
nt_sql('CREATE TABLE seq (i INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$v = array();
for ($i = 0; $i < 1000; $i++) {
    $v[] = "($i)";
}
nt_sql('INSERT INTO seq (i) VALUES ' . implode(',', $v));

// Reparto de usuarios por un hash del numero de fila (h), para que los usuarios intercalen sus filas como en la realidad.
$h = "CRC32(CONCAT('u', n))";
$h2 = "CRC32(CONCAT('v', n))";
$uid = "CASE WHEN $h % 100 < 6 THEN 1 + $h2 % 50 WHEN $h % 100 < 33 THEN 51 + $h2 % 450 ELSE 501 + $h2 % 9500 END";
$creado = ($ahora - 100 * $dia) . ' + FLOOR(n * ' . (100 * $dia) . ' / ' . $FILAS . ')';
$agrupable = "(CRC32(CONCAT('g', n)) % 50 = 0)";
for ($bloque = 0; $bloque < $FILAS / 100000; $bloque++) {
    nt_sql("INSERT INTO `$N` (uid, tipo, categoria, origen, importancia, agrupable, actor_uid, clave_evento, url, cantidad, datos_json, created_at, leida_en)
        SELECT $uid,
               IF($agrupable, 'bitacora_post', 'aventura_aprobada'),
               ELT(1 + CRC32(CONCAT('c', n)) % 6, 'aventuras', 'ficha', 'recompensas', 'tecnicas', 'peticiones', 'temas'),
               ELT(1 + CRC32(CONCAT('o', n)) % 3, 'gestion', 'usuario', 'sistema'),
               IF(CRC32(CONCAT('i', n)) % 4 = 0, 'importante', 'normal'),
               $agrupable, 1 + n % 997, CONCAT('perf:', n), CONCAT('/op/aventuras.php?id=', n), 1,
               CONCAT('{\"actor\":\"Usuario ', n % 997, '\",\"tema\":\"Una aventura en la isla ', n % 50, '\",\"isla\":\"Dawn\",\"tier\":2}'),
               $creado,
               IF(CRC32(CONCAT('l', n)) % 10 < 3, NULL, $creado + 3600)
        FROM (SELECT $bloque * 100000 + a.i * 1000 + b.i AS n FROM seq a, seq b WHERE a.i < 100) x");
}
nt_sql("INSERT INTO `$C` (uid, sin_leer, importantes, actualizado)
    SELECT uid, SUM(leida_en IS NULL), SUM(leida_en IS NULL AND importancia = 'importante'), $ahora FROM `$N` GROUP BY uid");
nt_sql("ANALYZE TABLE `$N`, `$C`");
$t_generar = microtime(true) - $t0;

$total = (int) nt_valor("SELECT COUNT(*) FROM `$N`");
$usuarios = (int) nt_valor("SELECT COUNT(*) FROM `$C`");
nt_prueba('volumen: 500.000 filas', $total, $FILAS);
nt_prueba('volumen: 10.000 usuarios con contador', $usuarios, $USUARIOS);
$max_uid = nt_una("SELECT uid, COUNT(*) AS n FROM `$N` GROUP BY uid ORDER BY n DESC LIMIT 1");
$pesado = (int) $max_uid['uid'];
$medio = 51;
$ligero = 600;
printf("Datos: %d filas, %d usuarios, generados en %.1f s. Usuario con mas filas: %d (%d filas); uid %d tiene %d; uid %d tiene %d.\n",
    $total, $usuarios, $t_generar, $pesado, $max_uid['n'], $medio, nt_valor("SELECT COUNT(*) FROM `$N` WHERE uid = $medio"),
    $ligero, nt_valor("SELECT COUNT(*) FROM `$N` WHERE uid = $ligero"));

// ── Utilidades de medicion ──────────────────────────────────────────────────────────────────────────────────────────

/** Ejecuta $fn $veces (tras una vuelta de calentamiento) y devuelve array(mediana, p95, maximo) en milisegundos. */
function nt_medir($veces, $fn)
{
    $fn(0);
    $t = array();
    for ($i = 0; $i < $veces; $i++) {
        $a = microtime(true);
        $fn($i);
        $t[] = (microtime(true) - $a) * 1000;
    }
    sort($t);
    return array($t[(int) floor(($veces - 1) / 2)], $t[(int) floor(($veces - 1) * 0.95)], $t[$veces - 1]);
}

$GLOBALS['nt_informe'] = array();
/** Guarda e imprime una linea del informe y comprueba la mediana contra el umbral holgado. */
function nt_informar($nombre, $m, $umbral_ms)
{
    $GLOBALS['nt_informe'][] = array($nombre, $m);
    printf("  %-62s mediana %8.2f ms  p95 %8.2f ms  max %8.2f ms  (umbral %d ms)\n", $nombre, $m[0], $m[1], $m[2], $umbral_ms);
    nt_ok($m[0] < $umbral_ms, "tiempo: $nombre (mediana " . round($m[0], 2) . " ms, umbral $umbral_ms ms)");
}

/**
 * EXPLAIN de $sql: comprueba que la primera tabla usa una de $claves, que no hay escaneo completo (type ALL) y, si
 * $sin_orden, que no hay filesort ni tabla temporal. Imprime el plan.
 */
function nt_explicar($nombre, $sql, $claves, $sin_orden = true)
{
    $r = nt_sql('EXPLAIN ' . $sql);
    $filas = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $filas[] = $f;
    }
    $p = $filas[0];
    printf("  %-40s type=%-6s key=%-24s rows=%-7s extra=%s\n", $nombre, $p['type'], (string) $p['key'], $p['rows'], (string) $p['Extra']);
    nt_ok(in_array($p['key'], $claves, true), "EXPLAIN $nombre: usa " . implode(' o ', $claves) . ' (usa ' . var_export($p['key'], true) . ')');
    foreach ($filas as $f) {
        nt_ok($f['type'] !== 'ALL', "EXPLAIN $nombre: sin escaneo completo en {$f['table']}");
        if ($sin_orden) {
            nt_ok(strpos((string) $f['Extra'], 'filesort') === false, "EXPLAIN $nombre: sin filesort");
            nt_ok(strpos((string) $f['Extra'], 'temporary') === false, "EXPLAIN $nombre: sin tabla temporal");
        }
    }
    return $filas;
}

/** Cuantas tablas temporales en disco creo la sesion durante $fn. */
function nt_tmp_disco($fn)
{
    $antes = (int) nt_una("SHOW SESSION STATUS LIKE 'Created_tmp_disk_tables'")['Value'];
    $fn();
    return (int) nt_una("SHOW SESSION STATUS LIKE 'Created_tmp_disk_tables'")['Value'] - $antes;
}

// ── 1. EXPLAIN de las consultas clave ───────────────────────────────────────────────────────────────────────────────

echo "\nEXPLAIN:\n";
$cols = 'id, tipo, categoria, origen, importancia, agrupable, actor_uid, url, cantidad, datos_json, created_at, leida_en';
$orden = ' ORDER BY created_at DESC, id DESC LIMIT 31';
$cur = nt_una("SELECT created_at, id FROM `$N` WHERE uid = $pesado ORDER BY created_at DESC, id DESC LIMIT 1 OFFSET 200");
$cursor = " AND created_at <= {$cur['created_at']} AND (created_at < {$cur['created_at']} OR id < {$cur['id']})";

nt_explicar('contador del header', "SELECT sin_leer, importantes, actualizado FROM `$C` WHERE uid = $pesado", array('PRIMARY'));
nt_explicar('lista sin filtro', "SELECT $cols FROM `$N` WHERE uid = $pesado$orden", array('idx_uid_fecha'));
nt_explicar('lista sin filtro con cursor', "SELECT $cols FROM `$N` WHERE uid = $pesado$cursor$orden", array('idx_uid_fecha'));
nt_explicar('lista por categoria', "SELECT $cols FROM `$N` WHERE uid = $pesado AND categoria = 'tecnicas'$orden", array('idx_uid_categoria'));
nt_explicar('lista por categoria con cursor', "SELECT $cols FROM `$N` WHERE uid = $pesado AND categoria = 'tecnicas'$cursor$orden", array('idx_uid_categoria'));
nt_explicar('lista por origen', "SELECT $cols FROM `$N` WHERE uid = $pesado AND origen = 'sistema'$orden", array('idx_uid_origen'));
nt_explicar('lista sin leer', "SELECT $cols FROM `$N` WHERE uid = $pesado AND leida_en IS NULL$orden", array('idx_uid_leida'));
$una = (int) nt_valor("SELECT id FROM `$N` WHERE uid = $pesado AND leida_en IS NULL ORDER BY id LIMIT 1");
nt_explicar('marcar una: lectura', "SELECT agrupable, clave_evento, importancia FROM `$N` WHERE id = $una AND uid = $pesado AND leida_en IS NULL", array('PRIMARY'));
nt_explicar('marcar una: UPDATE', "UPDATE `$N` SET leida_en = $ahora WHERE id = $una AND uid = $pesado AND leida_en IS NULL", array('PRIMARY'));
// marcar_todas ordena por clave_evento las no leidas del usuario (pocas filas, ordenacion en memoria): se admite filesort.
nt_explicar('marcar todas: lectura', "SELECT id, importancia, agrupable, clave_evento FROM `$N` WHERE uid = $pesado AND leida_en IS NULL ORDER BY clave_evento",
    array('idx_uid_leida', 'uq_evento_destinatario'), false);
nt_explicar('marcar todas: UPDATE por id', "UPDATE `$N` SET leida_en = $ahora WHERE uid = $pesado AND leida_en IS NULL AND id IN (1,2,3,4,5)", array('PRIMARY'));
nt_explicar('reconciliar: COUNT sin leer', "SELECT COUNT(*) AS n, COALESCE(SUM(importancia = 'importante'), 0) AS i FROM `$N` WHERE uid = $pesado AND leida_en IS NULL",
    array('idx_uid_leida'));
$corte = $ahora - 90 * $dia;
nt_explicar('tarea 1: lote antiguo', "SELECT id, uid FROM `$N` WHERE created_at < $corte ORDER BY created_at, id LIMIT 1000", array('idx_created'));
// GROUP BY uid HAVING recorre el indice entero (una vez al dia), pero en el orden del indice y sin tabla temporal.
$plan = nt_explicar('tarea 2: usuarios excedidos', "SELECT uid, COUNT(*) AS n FROM `$N` GROUP BY uid HAVING COUNT(*) > 500 LIMIT 2000",
    array('idx_uid_fecha', 'idx_uid_categoria', 'idx_uid_origen', 'idx_uid_leida', 'uq_evento_destinatario'));
nt_ok(strpos($plan[0]['Extra'], 'Using index') !== false, 'EXPLAIN tarea 2: solo lee el indice (Using index)');
nt_explicar('tarea 2: filas a recortar', "SELECT id FROM `$N` WHERE uid = $pesado ORDER BY (leida_en IS NULL), created_at, id LIMIT 100",
    array('idx_uid_fecha', 'idx_uid_leida', 'idx_uid_categoria', 'idx_uid_origen', 'uq_evento_destinatario'), false);
$desde = $ahora - 7 * $dia;
nt_explicar('tarea 4: contadores viejos', "SELECT DISTINCT n.uid FROM `$N` n LEFT JOIN `$C` c ON c.uid = n.uid WHERE n.created_at >= $desde"
    . ' AND (c.uid IS NULL OR c.actualizado < ' . ($ahora - $dia) . ') LIMIT 5000', array('idx_created'), false);

// Tablas temporales en disco: ninguna consulta de la pagina ni de la tarea debe crear una.
$tmp = nt_tmp_disco(function () use ($N, $C, $pesado, $cols, $orden, $corte, $desde, $ahora, $dia) {
    nt_sql("SELECT $cols FROM `$N` WHERE uid = $pesado$orden");
    nt_sql("SELECT $cols FROM `$N` WHERE uid = $pesado AND categoria = 'tecnicas'$orden");
    nt_sql("SELECT $cols FROM `$N` WHERE uid = $pesado AND origen = 'sistema'$orden");
    nt_sql("SELECT id, importancia, agrupable, clave_evento FROM `$N` WHERE uid = $pesado AND leida_en IS NULL ORDER BY clave_evento");
    nt_sql("SELECT uid, COUNT(*) AS n FROM `$N` GROUP BY uid HAVING COUNT(*) > 500 LIMIT 2000");
    nt_sql("SELECT id FROM `$N` WHERE uid = $pesado ORDER BY (leida_en IS NULL), created_at, id LIMIT 100");
    nt_sql("SELECT DISTINCT n.uid FROM `$N` n LEFT JOIN `$C` c ON c.uid = n.uid WHERE n.created_at >= $desde AND (c.uid IS NULL OR c.actualizado < "
        . ($ahora - $dia) . ') LIMIT 5000');
});
echo "  tablas temporales en disco creadas: $tmp\n";
nt_prueba('ninguna consulta crea tablas temporales en disco', $tmp, 0);

// ── 2. Tiempos (funciones reales de la libreria) ────────────────────────────────────────────────────────────────────

echo "\nTiempos (MySQL 5.7 en Docker, consultas en caliente):\n";
$uids = array();
for ($i = 0; $i < 200; $i++) {
    $uids[] = 1 + ($i * 7919) % $USUARIOS;
}

nt_informar('header: opg_notif_contar (200 usuarios distintos)', nt_medir(200, function ($i) use ($uids) {
    opg_notif_contar_olvidar();
    opg_notif_contar($uids[$i]);
}), 20);
// Requisito 30: con el contador al dia, el header hace UNA consulta (por clave primaria) y ninguna mas en la misma peticion.
opg_notif_contar_olvidar();
$antes = count($db->sentencias);
opg_notif_contar($pesado);
opg_notif_contar($pesado);
nt_prueba('header: una sola consulta por peticion', count($db->sentencias) - $antes, 1);
nt_ok(strpos(end($db->sentencias), 'op_notificaciones_contador') !== false && stripos(end($db->sentencias), 'COUNT(') === false,
    'header: lee el contador, sin COUNT sobre las notificaciones');
nt_informar('header: contar sin fila de contador (reconcilia, uid pesado)', nt_medir(20, function ($i) use ($C, $pesado) {
    nt_sql("DELETE FROM `$C` WHERE uid = $pesado");
    opg_notif_contar_olvidar();
    opg_notif_contar($pesado);
}), 50);

foreach (array(array('sin filtro', array()), array('por categoria', array('categoria' => 'tecnicas')), array('por origen', array('origen' => 'sistema')),
    array('sin leer', array('estado' => 'sin_leer')), array('leidas', array('estado' => 'leidas')), array('importantes', array('importancia' => 'importante'))) as $caso) {
    nt_informar("lista {$caso[0]}: uid pesado", nt_medir(50, function ($i) use ($pesado, $caso) {
        opg_notif_listar($pesado, $caso[1]);
    }), 100);
}
$pag = opg_notif_listar($pesado);
nt_ok(is_array($pag) && count($pag['filas']) === 30 && $pag['hay_mas'], 'lista: 30 filas y hay_mas en el usuario pesado');
nt_informar('lista con cursor (pagina 2 a 8): uid pesado', nt_medir(50, function ($i) use ($pesado) {
    $c = null;
    for ($p = 0; $p < 1 + $i % 7; $p++) {
        $r = opg_notif_listar($pesado, array(), $c);
        $c = $r['cursor'];
    }
    opg_notif_listar($pesado, array(), $c);
}), 400);
nt_informar('lista sin filtro: 200 usuarios distintos', nt_medir(200, function ($i) use ($uids) {
    opg_notif_listar($uids[$i]);
}), 100);
nt_informar('pagina: contar + listar (uid pesado)', nt_medir(50, function ($i) use ($pesado) {
    opg_notif_contar_olvidar();
    opg_notif_contar($pesado);
    opg_notif_listar($pesado);
}), 100);

// Marcar una: cada vuelta marca otra no leida del usuario medio.
$no_leidas = array();
$r = nt_sql("SELECT id FROM `$N` WHERE uid = $medio AND leida_en IS NULL AND agrupable = 0 ORDER BY id LIMIT 60");
while ($f = mysqli_fetch_assoc($r)) {
    $no_leidas[] = (int) $f['id'];
}
nt_informar('marcar una (opg_notif_marcar_vista)', nt_medir(50, function ($i) use ($medio, $no_leidas) {
    opg_notif_marcar_vista($medio, $no_leidas[$i + 1]);
}), 250);

// Marcar todas: cada vuelta, un usuario pesado distinto (unas 180 no leidas cada uno).
$pesados = array();
$r = nt_sql("SELECT uid FROM `$N` WHERE uid <= 50 GROUP BY uid ORDER BY uid LIMIT 21");
while ($f = mysqli_fetch_assoc($r)) {
    $pesados[] = (int) $f['uid'];
}
$sin_leer_antes = (int) nt_valor("SELECT COUNT(*) FROM `$N` WHERE uid = {$pesados[1]} AND leida_en IS NULL");
nt_informar("marcar todas (~$sin_leer_antes no leidas por usuario)", nt_medir(20, function ($i) use ($pesados) {
    opg_notif_marcar_todas($pesados[$i + 1]);
}), 1000);
nt_prueba('marcar todas: deja el usuario sin no leidas', (int) nt_valor("SELECT COUNT(*) FROM `$N` WHERE uid = {$pesados[1]} AND leida_en IS NULL"), 0);

// Envio por lotes: 1.000 destinatarios (5 bloques de OPG_NOTIF_LOTE) con un INSERT IGNORE multifila por bloque.
$dest = range(1, 1000);
nt_informar('envio a 1.000 destinatarios (opg_notificar)', nt_medir(10, function ($i) use ($dest) {
    opg_notificar($dest, 'aventura_aprobada', array('tema' => 'Rendimiento'), array('clave' => 'perf_envio:' . $i . ':' . mt_rand()));
}), 15000);
nt_informar('envio a 1 destinatario (opg_notificar)', nt_medir(50, function ($i) use ($medio) {
    opg_notificar($medio, 'aventura_aprobada', array('tema' => 'Rendimiento'), array('clave' => 'perf_uno:' . $i . ':' . mt_rand()));
}), 50);

// Consultas de la tarea de retencion, sueltas (sin borrar).
nt_informar('tarea 2: GROUP BY uid HAVING COUNT(*) > 500 (todo el indice)', nt_medir(5, function ($i) use ($N) {
    nt_sql("SELECT uid, COUNT(*) AS n FROM `$N` GROUP BY uid HAVING COUNT(*) > 500 LIMIT 2000");
}), 3000);
nt_informar('tarea 4: contadores viejos (DISTINCT ... LEFT JOIN)', nt_medir(5, function ($i) use ($N, $C, $desde, $ahora, $dia) {
    nt_sql("SELECT DISTINCT n.uid FROM `$N` n LEFT JOIN `$C` c ON c.uid = n.uid WHERE n.created_at >= $desde AND (c.uid IS NULL OR c.actualizado < "
        . ($ahora - $dia) . ') LIMIT 5000');
}), 3000);

// La tarea completa, paso a paso como opg_notif_tarea_ejecutar (primera ejecucion con todo el atraso: unas 50.000
// antiguas repartidas entre casi todos los usuarios) y despues una segunda ejecucion, que es la del dia a dia.
$pasos = array();
$a = microtime(true);
list($antiguas, $afectados) = opg_notif_tarea_borrar_antiguas($ahora - OPG_NOTIF_RETENCION_DIAS * $dia);
$pasos['tarea paso 1: borrar antiguas por lotes de 1.000'] = (microtime(true) - $a) * 1000;
$a = microtime(true);
list($recortadas, $recortados) = opg_notif_tarea_recortar_usuarios(OPG_NOTIF_MAX_POR_USUARIO);
$pasos['tarea paso 2: recortar usuarios por encima de 500'] = (microtime(true) - $a) * 1000;
$a = microtime(true);
$res = array('reconciliados' => 0, 'fallos' => 0);
opg_notif_tarea_reconciliar(array_keys(array_flip(array_merge($afectados, $recortados))), $res);
$pasos['tarea paso 3: reconciliar ' . $res['reconciliados'] . ' usuarios afectados'] = (microtime(true) - $a) * 1000;
$a = microtime(true);
$viejos = opg_notif_tarea_contadores_viejos($ahora - OPG_NOTIF_TAREA_ACTIVIDAD_DIAS * $dia, $ahora - OPG_NOTIF_RECONCILIAR_CADA);
opg_notif_tarea_reconciliar($viejos, $res);
$pasos['tarea paso 4: contadores viejos (' . count($viejos) . ' usuarios)'] = (microtime(true) - $a) * 1000;
$a = microtime(true);
$res2 = opg_notif_tarea_ejecutar($ahora);
$pasos['tarea: segunda ejecucion (sin atraso)'] = (microtime(true) - $a) * 1000;
foreach ($pasos as $nombre => $ms) {
    printf("  %-62s %8.0f ms\n", $nombre, $ms);
    $GLOBALS['nt_informe'][] = array($nombre, array($ms, $ms, $ms));
}
printf("  (paso 1: %d antiguas de %d usuarios; paso 2: %d recortadas de %d usuarios; %d fallos)\n", $antiguas, count($afectados), $recortadas,
    count($recortados), $res['fallos']);
nt_ok($antiguas > 40000, 'tarea: borra las antiguas (unas 50.000)');
nt_prueba('tarea: sin fallos al reconciliar', $res['fallos'], 0);
nt_prueba('tarea: la segunda ejecucion no tiene nada que borrar', array($res2['antiguas'], $res2['recortadas'], $res2['error']), array(0, 0, ''));
nt_prueba('tarea: nadie queda por encima de 500', (int) nt_valor("SELECT COUNT(*) FROM (SELECT uid FROM `$N` GROUP BY uid HAVING COUNT(*) > 500) x"), 0);
nt_ok(array_sum($pasos) < 300000, 'tarea: la primera ejecucion termina en menos de 5 minutos');
nt_ok(end($pasos) < 10000, 'tarea: la segunda ejecucion termina en menos de 10 s');
$r = nt_una("SELECT ROUND(data_length / 1048576) AS d, ROUND(index_length / 1048576) AS i FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$N'");
printf("  tamano de la tabla: %d MB de datos y %d MB de indices (buffer pool del contenedor: %d MB)\n", $r['d'], $r['i'],
    nt_valor('SELECT @@innodb_buffer_pool_size') / 1048576);

$log = nt_registro_leer();
nt_prueba('sin errores registrados', $log, '');

nt_borrar();
nt_resumen('rendimiento_db');
