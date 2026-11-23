<?php
/**
 * Esquema de notificaciones con MySQL real (issue #1, docs/208 apartado 4).
 *
 * Crea una base de datos propia en el MySQL 5.7 de pruebas (ver el encabezado de
 * tests/aventuras/bootstrap_db.php), ejecuta docs/notificaciones_migration.sql dos
 * veces y comprueba columnas, indices, la clave unica (uid, clave_evento) y las
 * filas afectadas de INSERT ... ON DUPLICATE KEY UPDATE. La borra al terminar.
 *
 *   php tests/notificaciones/esquema_db.php
 */

$host = getenv('OPG_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('OPG_TEST_DB_PORT') ?: 33307);
$user = getenv('OPG_TEST_DB_USER') ?: 'root';
$pass = getenv('OPG_TEST_DB_PASS') ?: 'opgtest';
$base = 'opg_notif_n01';

mysqli_report(MYSQLI_REPORT_OFF);
$link = mysqli_connect($host, $user, $pass, '', $port);
if (!$link) {
    fwrite(STDERR, "Sin MySQL de pruebas: " . mysqli_connect_error() . "\n");
    exit(2);
}
mysqli_set_charset($link, 'utf8mb4');

$fallos = [];
$total = 0;
function comprobar($ok, $msg)
{
    global $fallos, $total;
    $total++;
    if (!$ok) {
        $fallos[] = $msg;
    }
}
function limpiar()
{
    global $link, $base;
    mysqli_query($link, "DROP DATABASE IF EXISTS `$base`");
}
function q($sql)
{
    global $link;
    $r = mysqli_query($link, $sql);
    if ($r === false) {
        fwrite(STDERR, "ERROR SQL: " . mysqli_error($link) . "\n$sql\n");
        limpiar();
        exit(3);
    }
    return $r;
}
function ejecutar_migracion($ruta)
{
    global $link;
    $sql = file_get_contents($ruta);
    if (!mysqli_multi_query($link, $sql)) {
        return mysqli_error($link);
    }
    $err = null;
    do {
        if ($res = mysqli_store_result($link)) {
            mysqli_free_result($res);
        }
        if (mysqli_errno($link)) {
            $err = mysqli_error($link);
        }
        if (!mysqli_more_results($link)) {
            break;
        }
        if (!mysqli_next_result($link)) {
            $err = mysqli_error($link);
            break;
        }
    } while (true);
    return $err;
}

limpiar();
q("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
q("USE `$base`");

$ruta = __DIR__ . '/../../docs/notificaciones_migration.sql';
if (!is_file($ruta)) {
    limpiar();
    echo "FALLO: no existe docs/notificaciones_migration.sql\n";
    exit(1);
}

// Primera ejecucion
comprobar(ejecutar_migracion($ruta) === null, 'la migracion corre sin error la primera vez');

// Datos antes de la segunda ejecucion, para comprobar que no se pierden
q("INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, clave_evento, created_at) VALUES (5, 't', 'c', 'sistema', 'a:1', 100)");
q("INSERT INTO mybb_op_notificaciones_contador (uid, sin_leer, importantes, actualizado) VALUES (5, 3, 1, 100)");

// Segunda ejecucion
comprobar(ejecutar_migracion($ruta) === null, 'la migracion corre sin error la segunda vez');
$n = mysqli_fetch_row(q("SELECT COUNT(*) FROM mybb_op_notificaciones"))[0];
$c = mysqli_fetch_row(q("SELECT COUNT(*) FROM mybb_op_notificaciones_contador"))[0];
comprobar((int) $n === 1 && (int) $c === 1, 'la segunda ejecucion no pierde datos');

function columnas($tabla)
{
    global $base;
    $out = [];
    $r = q("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$base' AND TABLE_NAME='$tabla' ORDER BY ORDINAL_POSITION");
    while ($f = mysqli_fetch_assoc($r)) {
        $out[$f['COLUMN_NAME']] = $f;
    }
    return $out;
}
function indices($tabla)
{
    global $base;
    $out = [];
    $r = q("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$base' AND TABLE_NAME='$tabla' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
    while ($f = mysqli_fetch_assoc($r)) {
        $out[$f['INDEX_NAME']]['unico'] = $f['NON_UNIQUE'] == 0;
        $out[$f['INDEX_NAME']]['cols'][] = $f['COLUMN_NAME'];
    }
    return $out;
}
function comprobar_columnas($tabla, $esperadas)
{
    $cols = columnas($tabla);
    comprobar(array_keys($cols) === array_keys($esperadas), "$tabla: columnas y orden");
    foreach ($esperadas as $nombre => $e) {
        $r = $cols[$nombre] ?? null;
        comprobar($r !== null && $r['COLUMN_TYPE'] === $e[0] && $r['IS_NULLABLE'] === $e[1] && $r['COLUMN_DEFAULT'] === $e[2],
            "$tabla.$nombre: tipo/nulo/defecto");
    }
    return $cols;
}

// nombre => [tipo, nulo, defecto]
$cols = comprobar_columnas('mybb_op_notificaciones', [
    'id' => ['int(10) unsigned', 'NO', null],
    'uid' => ['int(10) unsigned', 'NO', null],
    'tipo' => ['varchar(48)', 'NO', null],
    'categoria' => ['varchar(24)', 'NO', null],
    'origen' => ['varchar(8)', 'NO', null],
    'importancia' => ['varchar(10)', 'NO', 'normal'],
    'agrupable' => ['tinyint(3) unsigned', 'NO', '0'],
    'actor_uid' => ['int(10) unsigned', 'NO', '0'],
    'clave_evento' => ['varchar(128)', 'NO', null],
    'url' => ['varchar(255)', 'YES', null],
    'cantidad' => ['int(10) unsigned', 'NO', '1'],
    'datos_json' => ['text', 'YES', null],
    'created_at' => ['int(10) unsigned', 'NO', null],
    'leida_en' => ['int(10) unsigned', 'YES', null],
    'sent_at' => ['int(10) unsigned', 'YES', null],
    'attempts' => ['tinyint(3) unsigned', 'NO', '0'],
]);
comprobar(($cols['id']['EXTRA'] ?? '') === 'auto_increment', 'id es AUTO_INCREMENT');

comprobar_columnas('mybb_op_notificaciones_contador', [
    'uid' => ['int(10) unsigned', 'NO', null],
    'sin_leer' => ['int(10) unsigned', 'NO', '0'],
    'importantes' => ['int(10) unsigned', 'NO', '0'],
    'actualizado' => ['int(10) unsigned', 'NO', null],
]);

$idx = indices('mybb_op_notificaciones');
$esp_idx = [
    'PRIMARY' => [true, ['id']],
    'uq_evento_destinatario' => [true, ['uid', 'clave_evento']],
    'idx_uid_fecha' => [false, ['uid', 'created_at', 'id']],
    'idx_uid_categoria' => [false, ['uid', 'categoria', 'created_at', 'id']],
    'idx_uid_origen' => [false, ['uid', 'origen', 'created_at', 'id']],
    'idx_uid_leida' => [false, ['uid', 'leida_en', 'created_at', 'id']],
    'idx_created' => [false, ['created_at']],
];
comprobar(count($idx) === count($esp_idx), 'mybb_op_notificaciones: numero de indices');
foreach ($esp_idx as $nombre => $e) {
    comprobar(isset($idx[$nombre]) && $idx[$nombre]['unico'] === $e[0] && $idx[$nombre]['cols'] === $e[1], "indice $nombre");
}
$idx = indices('mybb_op_notificaciones_contador');
comprobar(count($idx) === 1 && ($idx['PRIMARY']['cols'] ?? null) === ['uid'], 'contador: clave primaria (uid)');

// Motor y juego de caracteres
$r = q("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='$base'");
$t = 0;
while ($f = mysqli_fetch_assoc($r)) {
    $t++;
    comprobar($f['ENGINE'] === 'InnoDB', $f['TABLE_NAME'] . ' es InnoDB');
    comprobar(strpos($f['TABLE_COLLATION'], 'utf8mb4') === 0, $f['TABLE_NAME'] . ' es utf8mb4');
}
comprobar($t === 2, 'existen exactamente las dos tablas');

// Clave unica (uid, clave_evento)
q("DELETE FROM mybb_op_notificaciones");
$ins = "INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, clave_evento, created_at) VALUES (7, 't', 'c', 'sistema', 'k:1', 100)";
comprobar(mysqli_query($link, $ins) === true, 'primer insert correcto');
comprobar(mysqli_query($link, $ins) === false && mysqli_errno($link) === 1062, 'duplicado (uid, clave_evento) rechazado con 1062');
q("INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, clave_evento, created_at) VALUES (8, 't', 'c', 'sistema', 'k:1', 100)");
q("INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, clave_evento, created_at) VALUES (7, 't', 'c', 'sistema', 'k:2', 100)");
comprobar(mysqli_fetch_row(q("SELECT COUNT(*) FROM mybb_op_notificaciones"))[0] == 3, 'misma clave con otro uid, u otra clave con el mismo uid, se admiten');

// affected_rows de ON DUPLICATE KEY UPDATE (1 al insertar, 2 al actualizar)
q("DELETE FROM mybb_op_notificaciones");
$odku = "INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, agrupable, actor_uid, clave_evento, cantidad, created_at)
 VALUES (9, 't', 'c', 'usuario', 1, %d, 'bitacora:post:1', 1, %d)
 ON DUPLICATE KEY UPDATE cantidad = cantidad + 1, actor_uid = VALUES(actor_uid), created_at = VALUES(created_at)";
q(sprintf($odku, 1, 100));
$a1 = mysqli_affected_rows($link);
comprobar($a1 === 1, "ON DUPLICATE KEY UPDATE: affected_rows 1 al insertar (fue $a1)");
q(sprintf($odku, 2, 200));
$a2 = mysqli_affected_rows($link);
comprobar($a2 === 2, "ON DUPLICATE KEY UPDATE: affected_rows 2 al actualizar (fue $a2)");
$f = mysqli_fetch_assoc(q("SELECT cantidad, actor_uid, created_at FROM mybb_op_notificaciones WHERE uid=9"));
comprobar((int) $f['cantidad'] === 2 && (int) $f['actor_uid'] === 2 && (int) $f['created_at'] === 200, 'la fila actualizada acumula cantidad y toma los valores nuevos');

// El SQL inverso comentado funciona
$sql = file_get_contents($ruta);
preg_match_all('/^-- (DROP TABLE IF EXISTS `[a-z_]+`;)\s*$/m', $sql, $m);
comprobar(count($m[1]) === 2, 'la migracion termina con dos DROP TABLE IF EXISTS comentados');
foreach ($m[1] as $drop) {
    q($drop);
}
$r = q("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$base'");
comprobar(mysqli_fetch_row($r)[0] == 0, 'el SQL inverso borra las dos tablas');

limpiar();

if ($fallos) {
    echo "FALLO esquema_db: " . count($fallos) . " de $total comprobaciones\n";
    foreach ($fallos as $x) {
        echo " - $x\n";
    }
    exit(1);
}
echo "OK esquema_db: $total comprobaciones\n";
