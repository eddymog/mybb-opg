<?php
/**
 * Entorno de pruebas con MySQL real para las bibliotecas del tablón de aventuras.
 *
 * Levanta un MySQL 5.7 desechable (como el de producción) y carga solo las
 * definiciones de las tablas necesarias, sacadas de rovddqmy_op.sql, más la
 * migración docs/aventuras_migration.sql. NO usa datos de producción.
 *
 *   docker run -d --name opg-test-mysql --platform linux/amd64 -e MYSQL_ROOT_PASSWORD=opgtest \
 *     -e MYSQL_DATABASE=opg_test -p 33307:3306 --tmpfs /var/lib/mysql mysql:5.7 \
 *     --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
 *   php tests/aventuras/dominio_db.php
 *   docker rm -f opg-test-mysql
 *
 * Aporta dobles de lo que da MyBB: $db (con los métodos que usan las bibliotecas),
 * is_mod/is_staff/is_narra, does_ficha_exist, get_user, log_audit, etc.
 */

define('TABLE_PREFIX', 'mybb_');
define('MYBB_ROOT', realpath(__DIR__ . '/../../') . '/');
if (!defined('TIME_NOW')) {
    define('TIME_NOW', time());
}

class DbShim
{
    public $link;
    public $consultas = 0;
    public function __construct($host, $port, $user, $pass, $db)
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->link = mysqli_connect($host, $user, $pass, $db, $port);
        if (!$this->link) {
            fwrite(STDERR, "No se pudo conectar a MySQL de pruebas: " . mysqli_connect_error() . "\n");
            exit(2);
        }
        mysqli_set_charset($this->link, 'utf8mb4');
    }
    public function query($sql, $hide_errors = 0, $write = 0)
    {
        $this->consultas++;
        $r = mysqli_query($this->link, $sql);
        if ($r === false && !$hide_errors) {
            fwrite(STDERR, "ERROR SQL: " . mysqli_error($this->link) . "\n$sql\n");
            exit(3);
        }
        return $r;
    }
    public function write_query($sql, $hide_errors = 0)
    {
        return $this->query($sql, $hide_errors, 1);
    }
    public function fetch_array($q) { return mysqli_fetch_assoc($q); }
    public function num_rows($q) { return mysqli_num_rows($q); }
    public function escape_string($s) { return mysqli_real_escape_string($this->link, (string) $s); }
    public function escape_string_like($s) { return $this->escape_string(addcslashes((string) $s, '%_')); }
    public function insert_id() { return mysqli_insert_id($this->link); }
    public function affected_rows() { return mysqli_affected_rows($this->link); }
    public function error_string() { return mysqli_error($this->link); }
}

$db = new DbShim('127.0.0.1', 33307, 'root', 'opgtest', 'opg_test');
$mybb = new stdClass();
$mybb->settings = array('bburl' => 'https://onepiecegaiden.com');
$mybb->user = array('uid' => 0, 'username' => '');

// ── Roles y funciones de MyBB que usa el código ─────────────────────────────
$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(), 'narra' => array());
function is_mod($u) { return in_array((int) $u, $GLOBALS['roles']['mod'], true); }
function is_staff($u) { return in_array((int) $u, $GLOBALS['roles']['staff'], true); }
function is_narra($u) { return in_array((int) $u, $GLOBALS['roles']['narra'], true); }
function does_ficha_exist($uid)
{
    global $db;
    $q = $db->query("SELECT aprobada_por FROM mybb_op_fichas WHERE fid = " . (int) $uid, 1);
    $f = $q ? mysqli_fetch_assoc($q) : null;
    return $f && $f['aprobada_por'] != 'sin_aprobar';
}
function get_user($uid)
{
    global $db;
    $q = $db->query("SELECT uid, username FROM mybb_users WHERE uid = " . (int) $uid, 1);
    return $q ? mysqli_fetch_assoc($q) : null;
}
$GLOBALS['log_general'] = array();
function log_audit($uid, $username, $categoria, $log)
{
    $GLOBALS['log_general'][] = array('uid' => $uid, 'categoria' => $categoria, 'log' => $log);
}
function get_unviewable_forums($pid = false) { return $GLOBALS['foros_inaccesibles'] ?? ''; }
function get_ip() { return '127.0.0.1'; }
function random_str($n = 8) { return bin2hex(random_bytes($n)); }

// ── Esquema ──────────────────────────────────────────────────────────────────

/** Extrae de un volcado las sentencias CREATE TABLE y ALTER TABLE (claves) de unas tablas. */
function extraer_esquema($archivo, $tablas)
{
    $sql = file_get_contents($archivo);
    $salida = array();
    foreach ($tablas as $t) {
        if (!preg_match('/CREATE TABLE `' . preg_quote($t, '/') . '` \(.*?\) ENGINE=[^;]*;/s', $sql, $m)) {
            fwrite(STDERR, "No encontré CREATE TABLE de $t\n");
            exit(2);
        }
        $salida[] = "DROP TABLE IF EXISTS `$t`;";
        // El volcado trae columnas JSON con DEFAULT 'null' (no admitidas por MySQL 5.7 de pruebas).
        $salida[] = preg_replace("/`(\w+)` json NOT NULL DEFAULT 'null'/", '`$1` longtext NOT NULL', $m[0]);
        if (preg_match_all('/ALTER TABLE `' . preg_quote($t, '/') . '`\n(?:  [^\n]*,\n)*  [^\n]*;/', $sql, $alters)) {
            foreach ($alters[0] as $a) {
                $salida[] = $a;
            }
        }
    }
    return implode("\n", $salida);
}

function ejecutar_script($db, $sql)
{
    if (!mysqli_multi_query($db->link, $sql)) {
        fwrite(STDERR, "ERROR en el script: " . mysqli_error($db->link) . "\n");
        exit(2);
    }
    do {
        if ($r = mysqli_store_result($db->link)) {
            mysqli_free_result($r);
        }
    } while (mysqli_more_results($db->link) && mysqli_next_result($db->link));
    if (mysqli_errno($db->link)) {
        fwrite(STDERR, "ERROR en el script: " . mysqli_error($db->link) . "\n");
        exit(2);
    }
}

/** Crea el esquema de pruebas desde cero y aplica la migración del tablón. */
function preparar_esquema($db)
{
    $tablas = array('mybb_op_peticionAventuras', 'mybb_op_peticionAventuras_meta', 'mybb_users', 'mybb_forums', 'mybb_threads',
        'mybb_posts', 'mybb_op_fichas', 'mybb_op_inventario', 'mybb_settings', 'mybb_tasks');
    foreach (array('mybb_op_audit_aventuras', 'mybb_op_narradores_disponibles', 'mybb_op_aventuras_entregas', 'mybb_op_aventuras_entregas_pagos',
        'mybb_op_aventuras_participantes', 'mybb_op_aventuras_propuestas_narrador', 'mybb_op_aventuras_notificaciones',
        'mybb_op_aventuras_autonarradas') as $t) {
        $db->query("DROP TABLE IF EXISTS `$t`", 1);
    }
    $esquema = extraer_esquema(MYBB_ROOT . 'rovddqmy_op.sql', $tablas);
    // El volcado tiene claves de texto largas; en pruebas basta con el esquema.
    ejecutar_script($db, "SET SESSION sql_mode = '';\n" . $esquema);
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
    $migracion = file_get_contents(MYBB_ROOT . 'docs/aventuras_migration.sql');
    ejecutar_script($db, $migracion);
    $migracion_ux = file_get_contents(MYBB_ROOT . 'docs/aventuras_ux_migration.sql');
    ejecutar_script($db, $migracion_ux);
    $migracion_auto = file_get_contents(MYBB_ROOT . 'docs/aventuras_autonarradas_migration.sql');
    ejecutar_script($db, $migracion_auto);
    $migracion_auto_entrega = file_get_contents(MYBB_ROOT . 'docs/aventuras_autonarradas_entrega_migration.sql');
    ejecutar_script($db, $migracion_auto_entrega);
}

// ── Utilidades de prueba ────────────────────────────────────────────────────

$GLOBALS['fallos'] = 0;
$GLOBALS['total'] = 0;
function prueba($nombre, $obtenido, $esperado)
{
    $GLOBALS['total']++;
    if ($obtenido !== $esperado) {
        $GLOBALS['fallos']++;
        echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
    }
}
function resumen()
{
    echo ($GLOBALS['fallos'] === 0 ? 'OK' : 'CON FALLOS') . ": {$GLOBALS['total']} pruebas, {$GLOBALS['fallos']} fallos\n";
    exit($GLOBALS['fallos'] === 0 ? 0 : 1);
}
function sql($q)
{
    global $db;
    return $db->query($q, 0, 1);
}
function una($q)
{
    global $db;
    $r = $db->query($q, 0, 1);
    return $r ? mysqli_fetch_assoc($r) : null;
}

/** Ficha de prueba aprobada. */
function crear_usuario($uid, $nombre, $extra = array())
{
    global $db;
    $db->query("SET SESSION sql_mode = ''", 1);
    $ficha = array_merge(array(
        'fid' => $uid, 'nombre' => $nombre, 'apodo' => '', 'aprobada_por' => 'staff', 'oficios' => '', 'nivelnarrador' => 'Aprendiz',
        'expNarradorMensualActual' => 0, 'nika' => 0, 'berries' => 0, 'reputacion' => 0, 'reputacion_positiva' => 0,
        'reputacion_negativa' => 0, 'movidoInframundo' => 0, 'kuro' => 0,
    ), $extra);
    $cols = array();
    $vals = array();
    foreach ($ficha as $c => $v) {
        $cols[] = "`$c`";
        $vals[] = $v === null ? 'NULL' : "'" . $db->escape_string($v) . "'";
    }
    sql("INSERT INTO mybb_users (uid, username, newpoints) VALUES ($uid, '" . $db->escape_string($nombre) . "', 0) ON DUPLICATE KEY UPDATE username = VALUES(username)");
    sql("INSERT INTO mybb_op_fichas (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ") ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}

// ── Solicitudes de prueba ───────────────────────────────────────────────────

function ins($uid, $narr, $estado, $jug, $extra = array())
{
    global $db;
    $nj = array();
    foreach ($jug as $u) { $nj[] = array('uid' => $u, 'nombre' => 'U' . $u); }
    $campos = array_merge(array('uid' => $uid, 'narrador_uid' => $narr, 'narrador_fid' => $narr, 'estado' => $estado, 'isla_fid' => 40, 'dia' => 5, 'tier_seleccionado' => 3,
        'num_jugadores' => count($jug), 'jugadores_json' => json_encode($nj), 'created_at' => TIME_NOW - 1000, 'comentario_publico' => 'Comentario de prueba', 'comentario_staff' => '', 'enemigos_json' => '[]',
        'descripcion_tier' => '', 'dificultad_texto' => '', 'dificultad_color' => '', 'inframundo' => 0), $extra);
    $cols = array(); $vals = array();
    foreach ($campos as $c => $v) { $cols[] = "`$c`"; $vals[] = $v === null ? 'NULL' : "'" . $db->escape_string($v) . "'"; }
    sql("INSERT INTO mybb_op_peticionAventuras (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
    return (int) $db->insert_id();
}

function fila($id) { return una("SELECT * FROM mybb_op_peticionAventuras WHERE id = " . (int) $id); }
function limpiar() {
    sql("DELETE FROM mybb_op_aventuras_autonarradas");
    sql("DELETE FROM mybb_op_aventuras_notificaciones");
    sql("DELETE FROM mybb_op_aventuras_propuestas_narrador");
    sql("DELETE FROM mybb_op_aventuras_participantes");
    sql("DELETE FROM mybb_op_peticionAventuras");
    sql("DELETE FROM mybb_op_audit_aventuras");
    sql("DELETE FROM mybb_op_aventuras_entregas");
}
