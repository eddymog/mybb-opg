<?php
/**
 * Plugin opg_notificaciones con MySQL real (issues #5 y #6, docs/208 apartados 2, 4 y 7).
 *
 * Crea dos bases propias en el MySQL 5.7 de pruebas: opg_notif_n05 (donde corre el
 * plugin) y opg_notif_n05_ref (donde corre docs/notificaciones_migration.sql).
 * Compara columnas, tipos, defectos, indices, motor y collation entre ambas, y
 * comprueba idempotencia, _is_installed, _activate, _deactivate y _uninstall, y
 * que _activate instala la plantilla de la campana y su marcador en el header.
 * Borra las dos bases al terminar. OPG_NOTIF_PLUGIN_DB cambia el nombre de la base
 * (para correr en paralelo con otra copia de la prueba).
 *
 *   php tests/notificaciones/plugin_instalacion_db.php
 */

require __DIR__ . '/plugin_bootstrap_db.php';

$base = preg_replace('/[^a-z0-9_]/', '', (string) getenv('OPG_NOTIF_PLUGIN_DB')) ?: 'opg_notif_n05';
$ref = $base . '_ref';
$raiz = new DbShim('');
$link = $raiz->link;

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
    global $link, $base, $ref;
    mysqli_query($link, "DROP DATABASE IF EXISTS `$base`");
    mysqli_query($link, "DROP DATABASE IF EXISTS `$ref`");
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
function terminar()
{
    global $fallos, $total;
    limpiar();
    if ($fallos) {
        echo "FALLO plugin_instalacion_db: " . count($fallos) . " de $total comprobaciones\n";
        foreach ($fallos as $x) {
            echo " - $x\n";
        }
        exit(1);
    }
    echo "OK plugin_instalacion_db: $total comprobaciones\n";
    exit(0);
}

limpiar();
q("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
q("CREATE DATABASE `$ref` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// El plugin no debe cargarse sin MyBB (guardia) ni tener efectos al incluirse.
$ruta_plugin = MYBB_ROOT . 'inc/plugins/opg_notificaciones.php';
comprobar(is_file($ruta_plugin), 'existe inc/plugins/opg_notificaciones.php');
if (!is_file($ruta_plugin)) {
    terminar();
}
$salida = [];
exec('php -r ' . escapeshellarg('require ' . var_export($ruta_plugin, true) . ';') . ' 2>&1', $salida);
comprobar(strpos(implode("\n", $salida), 'Direct access not allowed') !== false, 'sin IN_MYBB el plugin se niega a cargarse');

$antes = get_defined_functions()['user'];
ob_start();
require $ruta_plugin;
$ruido = ob_get_clean();
comprobar($ruido === '', 'incluir el plugin no imprime nada');
$nuevas = array_diff(get_defined_functions()['user'], $antes);
$mal = array_filter($nuevas, function ($f) {
    return strpos($f, 'opg_notificaciones_') !== 0;
});
comprobar(!$mal, 'el plugin solo define funciones con el prefijo opg_notificaciones_');
foreach (['info', 'is_installed', 'install', 'uninstall', 'activate', 'deactivate'] as $f) {
    comprobar(function_exists("opg_notificaciones_$f"), "existe opg_notificaciones_$f");
}
$info = opg_notificaciones_info();
comprobar(!empty($info['name']) && !empty($info['version']) && ($info['compatibility'] ?? '') === '18*', 'info devuelve nombre, version y compatibilidad 18*');

// Al incluirse solo registra los hooks del header (issue #6); no lee docs/ ni usa rayas largas.
comprobar($plugins->hooks === [['global_start', 'opg_notificaciones_hook_precargar'], ['global_intermediate', 'opg_notificaciones_hook_header']], 'el plugin registra solo los hooks global_start y global_intermediate');
$fuente = file_get_contents($ruta_plugin);
$codigo = preg_replace('#/\*.*?\*/#s', '', $fuente);
comprobar(strpos($fuente, "\xE2\x80\x94") === false, 'sin rayas largas');
preg_match_all('/\b(?:require|include)(?:_once)?\b[^;]*;/', $codigo, $inclusiones);
comprobar($inclusiones[0] === ["require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';"], 'el plugin solo incluye la libreria de notificaciones');
preg_match_all('/file_get_contents|fopen|\bfile\s*\(/', $codigo, $lecturas);
comprobar(count($lecturas[0]) === 1 && strpos($codigo, "MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/' . \$archivo") !== false, 'el plugin solo lee las plantillas de templates/ (docs/ no se despliega)');
comprobar(strpos($codigo, 'docs/') === false && strpos($codigo, 'TABLE_PREFIX') === false, 'el plugin no usa docs/ ni la constante TABLE_PREFIX');

// Referencia: la migracion de docs/ en la base de referencia.
q("USE `$ref`");
$migracion = file_get_contents(MYBB_ROOT . 'docs/notificaciones_migration.sql');
if (!mysqli_multi_query($link, $migracion)) {
    fwrite(STDERR, mysqli_error($link) . "\n");
    limpiar();
    exit(3);
}
do {
    if ($r = mysqli_store_result($link)) {
        mysqli_free_result($r);
    }
} while (mysqli_more_results($link) && mysqli_next_result($link));
comprobar(mysqli_errno($link) === 0, 'la migracion de referencia corre sin error');

// Plugin en su base
$db = plugin_conectar($base);
q("USE `$base`");
// Tablas de plantillas de MyBB (minimas), que usa _activate; la comparacion de esquemas solo mira las del plugin.
q("CREATE TABLE mybb_templatesets (sid INT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(120) NOT NULL, PRIMARY KEY (sid))");
q("CREATE TABLE mybb_templates (tid INT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(120) NOT NULL, template MEDIUMTEXT NOT NULL, sid SMALLINT NOT NULL DEFAULT -1, version VARCHAR(20) NOT NULL DEFAULT '0', status VARCHAR(10) NOT NULL DEFAULT '', dateline INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (tid))");
comprobar(opg_notificaciones_is_installed() === false, '_is_installed es falso sin tablas');

// Solo existe una de las dos tablas: _install completa la que falta
q("CREATE TABLE `mybb_op_notificaciones_contador` (`uid` INT UNSIGNED NOT NULL, `sin_leer` INT UNSIGNED NOT NULL DEFAULT 0, `importantes` INT UNSIGNED NOT NULL DEFAULT 0, `actualizado` INT UNSIGNED NOT NULL, PRIMARY KEY (`uid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
q("INSERT INTO mybb_op_notificaciones_contador (uid, sin_leer, importantes, actualizado) VALUES (5, 3, 1, 100)");
comprobar(opg_notificaciones_is_installed() === false, '_is_installed es falso si solo existe una tabla');
opg_notificaciones_install();
comprobar(opg_notificaciones_is_installed() === true, '_install completa la tabla que faltaba');
$n = mysqli_fetch_row(q("SELECT COUNT(*) FROM mybb_op_notificaciones_contador"))[0];
comprobar((int) $n === 1, 'completar la instalacion conserva las filas existentes');

// Partir de cero
q("DROP TABLE mybb_op_notificaciones_contador");
q("DROP TABLE mybb_op_notificaciones");
comprobar(opg_notificaciones_is_installed() === false, '_is_installed es falso tras borrar las tablas');
opg_notificaciones_install();
comprobar(opg_notificaciones_is_installed() === true, '_is_installed es verdadero tras _install');

// Comparacion de esquemas
function columnas($bd, $tabla)
{
    $out = [];
    $r = q("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$bd' AND TABLE_NAME='$tabla' ORDER BY ORDINAL_POSITION");
    while ($f = mysqli_fetch_assoc($r)) {
        $out[] = $f;
    }
    return $out;
}
function indices($bd, $tabla)
{
    $out = [];
    $r = q("SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, COLLATION, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$bd' AND TABLE_NAME='$tabla' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
    while ($f = mysqli_fetch_assoc($r)) {
        $out[] = $f;
    }
    return $out;
}
function opciones($bd)
{
    $out = [];
    $r = q("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA='$bd' AND TABLE_NAME LIKE 'mybb\\_op\\_notificaciones%' ORDER BY TABLE_NAME");
    while ($f = mysqli_fetch_assoc($r)) {
        $out[$f['TABLE_NAME']] = $f;
    }
    return $out;
}
function comparar_esquemas($etiqueta)
{
    global $base, $ref;
    $tablas = ['mybb_op_notificaciones', 'mybb_op_notificaciones_contador'];
    $o1 = opciones($base);
    $o2 = opciones($ref);
    comprobar(array_keys($o1) === $tablas, "$etiqueta: el plugin crea exactamente las dos tablas");
    foreach ($tablas as $t) {
        comprobar(columnas($base, $t) === columnas($ref, $t) && count(columnas($ref, $t)) > 0, "$etiqueta: $t columnas, tipos, defectos y orden iguales a la migracion");
        comprobar(indices($base, $t) === indices($ref, $t) && count(indices($ref, $t)) > 0, "$etiqueta: $t indices iguales a la migracion");
        comprobar(($o1[$t] ?? null) === ($o2[$t] ?? null), "$etiqueta: $t motor, collation y formato iguales a la migracion");
        comprobar(($o1[$t]['ENGINE'] ?? '') === 'InnoDB' && ($o1[$t]['TABLE_COLLATION'] ?? '') === 'utf8mb4_unicode_ci', "$etiqueta: $t es InnoDB y utf8mb4_unicode_ci");
    }
    $cols = columnas($base, 'mybb_op_notificaciones_contador');
    comprobar(in_array('importantes', array_column($cols, 'COLUMN_NAME'), true), "$etiqueta: el contador tiene la columna importantes");
}
comparar_esquemas('instalacion');

// Idempotencia con datos
q("INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, clave_evento, created_at) VALUES (5, 't', 'c', 'sistema', 'a:1', 100)");
q("INSERT INTO mybb_op_notificaciones_contador (uid, sin_leer, importantes, actualizado) VALUES (5, 3, 1, 100)");
function resumen_datos()
{
    return [
        mysqli_fetch_all(q("SELECT * FROM mybb_op_notificaciones ORDER BY id"), MYSQLI_ASSOC),
        mysqli_fetch_all(q("SELECT * FROM mybb_op_notificaciones_contador ORDER BY uid"), MYSQLI_ASSOC),
    ];
}
$datos = resumen_datos();
opg_notificaciones_install();
opg_notificaciones_install();
comprobar(resumen_datos() === $datos, '_install repetido no toca los datos');
comparar_esquemas('reinstalacion');

opg_notificaciones_activate();
comprobar(resumen_datos() === $datos, '_activate con las tablas existentes no toca los datos');
comprobar(opg_notificaciones_is_installed() === true, '_activate deja las tablas');
opg_notificaciones_deactivate();
comprobar(resumen_datos() === $datos, '_deactivate no toca los datos');
opg_notificaciones_activate();
opg_notificaciones_activate();
comprobar(resumen_datos() === $datos, '_activate repetido no falla ni toca los datos');

opg_notificaciones_uninstall();
comprobar(opg_notificaciones_is_installed() === true, '_uninstall conserva las tablas');
comprobar(resumen_datos() === $datos, '_uninstall conserva las filas');
comparar_esquemas('tras desinstalar');

// _activate instala la plantilla de la campana y el marcador del header (issue #6), aunque las tablas ya existieran.
q("INSERT INTO mybb_templatesets (sid, title) VALUES (1, 'Default Templates'), (4, 'One Piece Gaiden Templates')");
$header_ref = file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/header.html');
$header_antes = str_replace('<div class="op-barra-usuario">{$op_temas_header}{$op_notificaciones_header}</div>', '{$op_temas_header}', $header_ref);
q("INSERT INTO mybb_templates (title, template, sid) VALUES ('header', '" . mysqli_real_escape_string($link, $header_antes) . "', 4), ('header', '<div>{\$op_temas_header}</div>', -2), ('op_notificaciones_header', 'vieja', 4)");
function plantillas()
{
    return mysqli_fetch_all(q("SELECT title, sid, template FROM mybb_templates ORDER BY title, sid"), MYSQLI_ASSOC);
}
$campana = rtrim(file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_notificaciones_header.html'));
opg_notificaciones_activate();
$p1 = plantillas();
$por_clave = [];
foreach ($p1 as $f) {
    $por_clave[$f['title'] . '/' . $f['sid']] = $f['template'];
}
comprobar(($por_clave['op_notificaciones_header/-2'] ?? null) === $campana, '_activate crea la plantilla de la campana en la maestra');
comprobar(($por_clave['op_notificaciones_header/4'] ?? null) === $campana, '_activate actualiza la plantilla de la campana del tema');
comprobar(count($p1) === 4, '_activate no duplica plantillas');
comprobar(($por_clave['header/4'] ?? null) === $header_ref, '_activate pone el marcador en el header del tema igual que la referencia');
comprobar(($por_clave['header/-2'] ?? null) === '<div><div class="op-barra-usuario">{$op_temas_header}{$op_notificaciones_header}</div></div>', '_activate pone el marcador en el header maestro');
comprobar(resumen_datos() === $datos, '_activate con plantillas no toca los datos');
opg_notificaciones_activate();
comprobar(plantillas() === $p1, '_activate repetido no cambia las plantillas');
opg_notificaciones_deactivate();
comprobar(plantillas() === $p1, '_deactivate deja la plantilla y el marcador (quedan inertes)');

// Activar sin tablas (instalacion nueva) tampoco debe fallar
q("DROP TABLE mybb_op_notificaciones_contador");
q("DROP TABLE mybb_op_notificaciones");
opg_notificaciones_activate();
opg_notificaciones_deactivate();
comprobar(true, '_activate y _deactivate sin tablas no fallan');

terminar();
