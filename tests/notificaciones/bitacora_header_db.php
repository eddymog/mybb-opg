<?php
/**
 * Header de la Bitacora sin el aviso de "novedad pendiente" (issue #13; decision 9 de 108, apartado 9.2 de 208).
 *
 *   php tests/notificaciones/bitacora_header_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n13, que crea con
 * docs/notificaciones_migration.sql, docs/bitacora_migration.sql y unas tablas minimas del foro, y borra al terminar.
 *
 * Comprueba:
 *  - El header ya no consulta mybb_op_bitacora_lecturas ni mybb_op_bitacora_eventos (ni con cache ni sin ella).
 *  - Ya no muestra "Actualizado hace ...", aunque haya publicaciones sin revisar o una fila antigua de la cache con
 *    ultima_novedad_en.
 *  - El estado de turno (debes_responder y esperando) y sus conteos no cambian: coinciden con op_bitacora_resumen y
 *    siguen los cambios manuales de turno.
 *  - Las tablas de la Bitacora (incluido el cursor de lectura) siguen existiendo y el header no escribe en el cursor.
 */

require __DIR__ . '/bootstrap_db.php';

if (!defined('TIME_NOW')) {
    define('TIME_NOW', time());
}

/** El doble de $db con los metodos de MyBB que usa la Bitacora y que no estan en el bootstrap. */
class BitacoraHeaderTestDb extends OpgNotifTestDb
{
    public function update_query($table, $array, $where = '')
    {
        $sets = array();
        foreach ($array as $campo => $valor) {
            $sets[] = '`' . $campo . "` = '" . $this->escape_string($valor) . "'";
        }
        return $this->write_query('UPDATE ' . $this->table_prefix . $table . ' SET ' . implode(', ', $sets) . ($where !== '' ? ' WHERE ' . $where : ''));
    }
    public function delete_query($table, $where = '')
    {
        return $this->write_query('DELETE FROM ' . $this->table_prefix . $table . ($where !== '' ? ' WHERE ' . $where : ''));
    }
    public function replace_query($table, $array, $default_field = '', $insert_id = true)
    {
        $campos = array();
        $valores = array();
        foreach ($array as $campo => $valor) {
            $campos[] = '`' . $campo . '`';
            $valores[] = "'" . $this->escape_string($valor) . "'";
        }
        return $this->write_query('REPLACE INTO ' . $this->table_prefix . $table . ' (' . implode(',', $campos) . ') VALUES (' . implode(',', $valores) . ')');
    }
    public function field_exists($campo, $table)
    {
        $r = mysqli_query($this->link, 'SHOW COLUMNS FROM ' . $this->table_prefix . $table . " LIKE '" . $this->escape_string($campo) . "'");
        return $r && mysqli_num_rows($r) > 0;
    }
}

function forum_permissions($fid = 0, $uid = 0, $gid = 0)
{
    return array('canview' => 1, 'canviewthreads' => 1);
}
function usergroup_permissions($gid)
{
    return array();
}
function fetch_forum_permissions($fid, $gid, $groupperms)
{
    return array('canview' => 1, 'canviewthreads' => 1);
}

nt_preparar('opg_notif_n13');
$db = new BitacoraHeaderTestDb('opg_notif_n13');
$GLOBALS['db'] = $db;
$error = nt_ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/bitacora_migration.sql'));
if ($error !== null) {
    fwrite(STDERR, "La migracion de la Bitacora fallo: $error\n");
    nt_borrar();
    exit(2);
}
nt_sql("ALTER TABLE mybb_threads ADD closed VARCHAR(30) NOT NULL DEFAULT '', ADD prefix INT UNSIGNED NOT NULL DEFAULT 0,"
    . ' ADD lastpost INT UNSIGNED NOT NULL DEFAULT 0, ADD lastposteruid INT UNSIGNED NOT NULL DEFAULT 0, ADD replies INT UNSIGNED NOT NULL DEFAULT 0');
nt_sql('CREATE TABLE mybb_posts (pid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, tid INT UNSIGNED NOT NULL, fid INT UNSIGNED NOT NULL,'
    . ' uid INT UNSIGNED NOT NULL, visible TINYINT(1) NOT NULL DEFAULT 1, dateline INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB');
nt_sql("CREATE TABLE mybb_forums (fid INT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL DEFAULT '', parentlist VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB");
nt_sql("CREATE TABLE mybb_op_fichas (fid INT UNSIGNED NOT NULL PRIMARY KEY, nombre VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB");
nt_sql("CREATE TABLE mybb_users (uid INT UNSIGNED NOT NULL PRIMARY KEY, username VARCHAR(100) NOT NULL DEFAULT '', avatar VARCHAR(200) NOT NULL DEFAULT '') ENGINE=InnoDB");
nt_sql("INSERT INTO mybb_forums (fid, name, parentlist) VALUES (2, 'Rol', '10,2')");
nt_sql("INSERT INTO mybb_threads (tid, fid, subject, visible) VALUES (1, 2, 'Rumbo a Grand Line', 1), (2, 2, 'Isla Gyojin', 1)");
nt_sql("INSERT INTO mybb_users (uid, username) VALUES (1, 'user1'), (2, 'user2'), (3, 'user3')");
nt_sql("INSERT INTO mybb_op_fichas VALUES (1, 'Pj1'), (2, 'Pj2'), (3, 'Pj3')");

require_once MYBB_ROOT . 'inc/plugins/op_bitacora/functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
nt_registro_iniciar();

$mybb = new stdClass();
$mybb->settings = array('bburl' => 'https://foro.test');
$mybb->user = array('uid' => 2, 'usergroup' => 2, 'additionalgroups' => '');
$GLOBALS['mybb'] = $mybb;
$GLOBALS['templates'] = null;

function bh_seguir($uid, $tid)
{
    nt_sql("INSERT INTO mybb_op_temas_seguidos (personaje_uid, tid, ronda_inicio_pid, narrador_uid, creado_en, actualizado_en) VALUES ($uid, $tid, 0, 0, 1, 1)");
    return (int) mysqli_insert_id($GLOBALS['db']->link);
}
function bh_publicar($uid, $tid)
{
    nt_sql("INSERT INTO mybb_posts (tid, fid, uid, visible, dateline) VALUES ($tid, 2, $uid, 1, " . (TIME_NOW - 600) . ')');
    $pid = (int) mysqli_insert_id($GLOBALS['db']->link);
    nt_sql("UPDATE mybb_threads SET lastpost = " . (TIME_NOW - 600) . ", lastposteruid = $uid WHERE tid = $tid");
    op_bitacora_procesar_post($pid);
    return $pid;
}
/** Sentencias que el header ejecuto y que tocan las tablas del aviso antiguo. */
function bh_consultas_antiguas($desde)
{
    $antiguas = array();
    foreach (array_slice($GLOBALS['db']->sentencias, $desde) as $s) {
        if (stripos($s, 'op_bitacora_lecturas') !== false || stripos($s, 'op_bitacora_eventos') !== false) {
            $antiguas[] = $s;
        }
    }
    return $antiguas;
}
function bh_render($uid)
{
    $desde = count($GLOBALS['db']->sentencias);
    $html = op_bitacora_render_header($uid);
    return array($html, bh_consultas_antiguas($desde));
}
function bh_cifra($html, $clase)
{
    return preg_match('/op-temas-header__contador--' . $clase . '">\s*<small>[^<]*<\/small><b>(\d+)<\/b>/', $html, $m) ? (int) $m[1] : null;
}

// ── Publicaciones sin revisar: antes daban "Actualizado hace ..." ────────────────────────────────────────────────────
$seg1 = bh_seguir(2, 1);
$seg2 = bh_seguir(2, 2);
bh_publicar(1, 1);
bh_publicar(3, 1);
bh_publicar(1, 2);
nt_ok((int) nt_valor("SELECT COUNT(*) FROM mybb_op_bitacora_eventos e INNER JOIN mybb_op_temas_seguidos s ON s.id = e.seguimiento_id WHERE s.personaje_uid = 2 AND e.tipo = 'post_publicado'") === 3,
    'preparacion: hay publicaciones sin revisar para el personaje 2');
nt_sql('DELETE FROM mybb_op_bitacora_header_cache');

list($html, $antiguas) = bh_render(2);
nt_ok($html !== '', 'sin cache: el header se pinta');
nt_prueba('sin cache: no consulta el cursor ni los eventos', $antiguas, array());
nt_ok(strpos($html, 'Actualizado hace') === false, 'sin cache: no muestra la novedad pendiente');
$resumen = op_bitacora_resumen(2);
nt_prueba('sin cache: los conteos coinciden con op_bitacora_resumen', array(bh_cifra($html, 'turno'), bh_cifra($html, 'aldia')),
    array((int) $resumen['debes_responder'], (int) $resumen['esperando']));
$cache = nt_una('SELECT * FROM mybb_op_bitacora_header_cache WHERE personaje_uid = 2');
nt_prueba('sin cache: guarda los conteos en la cache', array((int) $cache['conteo_turno'], (int) $cache['conteo_al_dia']),
    array((int) $resumen['debes_responder'], (int) $resumen['esperando']));
nt_prueba('sin cache: no escribe ultima_novedad_en', (int) $cache['ultima_novedad_en'], 0);

// ── Con cache, incluida una fila antigua que aun trae ultima_novedad_en ──────────────────────────────────────────────
nt_sql('UPDATE mybb_op_bitacora_header_cache SET ultima_novedad_en = ' . (TIME_NOW - 120) . ' WHERE personaje_uid = 2');
list($html, $antiguas) = bh_render(2);
nt_prueba('con cache: no consulta el cursor ni los eventos', $antiguas, array());
nt_ok(strpos($html, 'Actualizado hace') === false, 'con cache antigua: no muestra la novedad pendiente');

// ── Estado de turno: los cambios manuales se reflejan igual que antes ────────────────────────────────────────────────
$r = op_bitacora_marcar_me_toca(2, $seg1);
nt_prueba('turno: marcar "me toca" funciona', $r['ok'] ?? null, true);
op_bitacora_invalidar_cache_header_uid(2); // como hace op/bitacora.php tras cada accion
list($html, $antiguas) = bh_render(2);
$resumen = op_bitacora_resumen(2);
nt_ok((int) $resumen['debes_responder'] >= 1, 'turno: el resumen cuenta el tema como turno propio');
nt_prueba('turno: el header muestra los conteos del resumen', array(bh_cifra($html, 'turno'), bh_cifra($html, 'aldia')),
    array((int) $resumen['debes_responder'], (int) $resumen['esperando']));
nt_ok(strpos($html, 'op-temas-header--pendiente') !== false && strpos($html, 'Tienes respuestas pendientes') !== false,
    'turno: el header marca respuestas pendientes');
nt_prueba('turno: sin consultas antiguas', $antiguas, array());

$turnoAntes = (int) $resumen['debes_responder'];
op_bitacora_marcar_no_me_toca(2, $seg1);
op_bitacora_invalidar_cache_header_uid(2);
list($html, $antiguas) = bh_render(2);
$resumen = op_bitacora_resumen(2);
nt_prueba('al dia: el turno propio baja en uno', (int) $resumen['debes_responder'], $turnoAntes - 1);
nt_prueba('al dia: el header muestra los conteos del resumen', array(bh_cifra($html, 'turno'), bh_cifra($html, 'aldia')),
    array((int) $resumen['debes_responder'], (int) $resumen['esperando']));
if ((int) $resumen['debes_responder'] === 0) {
    nt_ok(strpos($html, 'Todo al día') !== false && strpos($html, 'op-temas-header--pendiente') === false,
        'al dia: el header vuelve a "Todo al dia"');
}

// ── Conteos explicitos (la pagina de la Bitacora los pasa ya calculados) ──────────────────────────────────────────────
$desde = count($db->sentencias);
$html = op_bitacora_render_header(2, array('debes_responder' => 4, 'esperando' => 7));
nt_prueba('conteos explicitos: se pintan tal cual', array(bh_cifra($html, 'turno'), bh_cifra($html, 'aldia')), array(4, 7));
nt_prueba('conteos explicitos: sin consultas antiguas', bh_consultas_antiguas($desde), array());

// ── Las tablas se conservan y el cursor no se toca ───────────────────────────────────────────────────────────────────
foreach (array('op_bitacora_lecturas', 'op_bitacora_eventos', 'op_bitacora_header_cache', 'op_temas_seguidos') as $tabla) {
    nt_ok($db->table_exists($tabla), "tablas: $tabla sigue existiendo");
}
nt_ok($db->field_exists('ultima_novedad_en', 'op_bitacora_header_cache'), 'tablas: la columna ultima_novedad_en se conserva');
nt_prueba('cursor: el header no escribe en mybb_op_bitacora_lecturas', (int) nt_valor('SELECT COUNT(*) FROM mybb_op_bitacora_lecturas'), 0);
nt_ok(!function_exists('op_bitacora_ultima_novedad_pendiente'), 'codigo: la funcion de la novedad pendiente se retiro');

nt_borrar();
nt_resumen('bitacora_header_db');
