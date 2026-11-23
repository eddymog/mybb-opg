<?php
/**
 * Entorno de pruebas con MySQL real para el sistema de notificaciones (lo comparten las pruebas *_db.php de esta carpeta).
 *
 * Usa el MySQL 5.7 desechable de las pruebas (el comando docker esta en el encabezado de tests/aventuras/bootstrap_db.php:
 * puerto 33307, datos en tmpfs, sin datos de produccion). Cada prueba usa su PROPIA base, para poder correr a la vez que
 * otras: la crea desde cero con docs/notificaciones_migration.sql y la borra al terminar.
 *
 * Uso tipico:
 *
 *   require __DIR__ . '/bootstrap_db.php';
 *   $db = nt_preparar('opg_notif_n03');      // crea la base, ejecuta la migracion y deja el $db global listo
 *   require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
 *   nt_prueba('descripcion', $obtenido, $esperado);
 *   nt_borrar();                             // borra la base
 *   nt_resumen();                            // imprime "OK ..." o los fallos y termina con 0 o 1
 *
 * Conexion: variables de entorno OPG_TEST_DB_HOST, OPG_TEST_DB_PORT, OPG_TEST_DB_USER y OPG_TEST_DB_PASS (por defecto
 * las del contenedor de pruebas). Si no hay MySQL, termina con codigo 2 y "Sin MySQL de pruebas".
 *
 * Que aporta:
 *   - OpgNotifTestDb: doble del $db de MyBB (inc/db_mysqli.php) sobre una conexion mysqli real, con los mismos nombres y
 *     argumentos: query, write_query, simple_select, fetch_array, fetch_field, num_rows, escape_string, escape_string_like,
 *     insert_id, affected_rows, error_number, error_string, table_exists, $table_prefix y $database. Como MyBB, una
 *     consulta que falla SIN $hide_errors detiene el script (exit 3): asi se detecta codigo que olvida ocultar el error.
 *     Guarda cada sentencia en $sentencias para contar consultas.
 *   - nt_estricto(true|false): activa el modo de mysqli que lanza excepciones (el de PHP 8.1 en adelante) o lo apaga.
 *   - nt_conectar(): una conexion nueva a la misma base (para pruebas de concurrencia o de dos sesiones).
 *   - nt_registro_*: captura lo que el codigo escribe con error_log, para comprobar que se registran los errores.
 *   - nt_prueba, nt_ok, nt_resumen, nt_sql, nt_una, nt_valor: utilidades de comprobacion y de consulta.
 */

if (!defined('IN_MYBB')) {
    define('IN_MYBB', 1);
}
if (!defined('MYBB_ROOT')) {
    define('MYBB_ROOT', realpath(__DIR__ . '/../../') . '/');
}
if (!defined('TABLE_PREFIX')) {
    define('TABLE_PREFIX', 'mybb_');
}

class OpgNotifTestDb
{
    public $link;
    public $table_prefix = TABLE_PREFIX;
    public $database = '';
    /** Todas las sentencias ejecutadas, en orden. */
    public $sentencias = array();

    public function __construct($base = '')
    {
        $this->link = @mysqli_connect(nt_cfg('host'), nt_cfg('user'), nt_cfg('pass'), '', (int) nt_cfg('port'));
        if (!$this->link) {
            fwrite(STDERR, 'Sin MySQL de pruebas: ' . mysqli_connect_error() . "\n");
            exit(2);
        }
        mysqli_set_charset($this->link, 'utf8mb4');
        if ($base !== '') {
            $this->usar($base);
        }
    }

    /** Cambia a la base indicada (sin el error de MyBB). */
    public function usar($base)
    {
        $this->database = $base;
        return mysqli_select_db($this->link, $base);
    }

    public function query($sql, $hide_errors = 0, $write_query = 0)
    {
        $this->sentencias[] = $sql;
        $r = mysqli_query($this->link, $sql);
        if ($r === false && !$hide_errors) {
            // MyBB muestra la pagina de error SQL y termina.
            fwrite(STDERR, 'ERROR SQL (MyBB detendria la pagina): ' . mysqli_error($this->link) . "\n$sql\n");
            exit(3);
        }
        return $r;
    }
    public function write_query($sql, $hide_errors = 0) { return $this->query($sql, $hide_errors, 1); }

    /** Igual que en MyBB: simple_select('tabla', 'campos', 'condicion', array('order_by' => ..., 'order_dir' => ..., 'limit' => ...)). */
    public function simple_select($table, $fields = '*', $conditions = '', $options = array())
    {
        $sql = 'SELECT ' . $fields . ' FROM ' . $this->table_prefix . $table;
        if ($conditions !== '') {
            $sql .= ' WHERE ' . $conditions;
        }
        if (isset($options['order_by'])) {
            $sql .= ' ORDER BY ' . $options['order_by'] . (isset($options['order_dir']) ? ' ' . $options['order_dir'] : '');
        }
        if (isset($options['limit_start'], $options['limit'])) {
            $sql .= ' LIMIT ' . (int) $options['limit_start'] . ', ' . (int) $options['limit'];
        } elseif (isset($options['limit'])) {
            $sql .= ' LIMIT ' . (int) $options['limit'];
        }
        return $this->query($sql);
    }

    public function fetch_array($q) { return $q instanceof mysqli_result ? mysqli_fetch_assoc($q) : null; }
    public function fetch_field($q, $campo, $fila = false)
    {
        if ($fila !== false) {
            mysqli_data_seek($q, $fila);
        }
        $f = $this->fetch_array($q);
        return $f && array_key_exists($campo, $f) ? $f[$campo] : null;
    }
    public function num_rows($q) { return mysqli_num_rows($q); }
    public function escape_string($s) { return mysqli_real_escape_string($this->link, (string) $s); }
    public function escape_string_like($s) { return $this->escape_string(addcslashes((string) $s, '%_')); }
    public function insert_id() { return mysqli_insert_id($this->link); }
    public function affected_rows() { return mysqli_affected_rows($this->link); }
    public function error_number() { return mysqli_errno($this->link); }
    public function error_string() { return mysqli_error($this->link); }

    /** Como en MyBB, $table va sin prefijo. Compara el nombre exacto (sin comodines de LIKE). */
    public function table_exists($table)
    {
        $r = mysqli_query($this->link, "SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
            . $this->escape_string($this->table_prefix . $table) . "'");
        $f = $r ? mysqli_fetch_assoc($r) : null;
        return $f && (int) $f['n'] > 0;
    }

    public function close() { @mysqli_close($this->link); }
}

// ── Configuracion y base de la prueba ────────────────────────────────────────────────────────────────────────────────

function nt_cfg($clave)
{
    $defecto = array('host' => '127.0.0.1', 'port' => '33307', 'user' => 'root', 'pass' => 'opgtest');
    $env = getenv('OPG_TEST_DB_' . strtoupper($clave));
    return $env !== false && $env !== '' ? $env : $defecto[$clave];
}

/** mysqli en modo estricto (lanza mysqli_sql_exception, como PHP 8.1+ por defecto) o apagado (lo habitual en MyBB). */
function nt_estricto($activar)
{
    mysqli_report($activar ? (MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) : MYSQLI_REPORT_OFF);
}

/** Ejecuta un script de varias sentencias (por ejemplo una migracion). Devuelve null o el mensaje de error. */
function nt_ejecutar_script($db, $sql)
{
    if (!mysqli_multi_query($db->link, $sql)) {
        return mysqli_error($db->link);
    }
    $error = null;
    do {
        if ($r = mysqli_store_result($db->link)) {
            mysqli_free_result($r);
        }
        if (mysqli_errno($db->link)) {
            $error = mysqli_error($db->link);
        }
        if (!mysqli_more_results($db->link)) {
            break;
        }
        if (!mysqli_next_result($db->link)) {
            $error = mysqli_error($db->link);
            break;
        }
    } while (true);
    return $error;
}

/**
 * Crea la base $base desde cero, ejecuta docs/notificaciones_migration.sql y una tabla `mybb_threads` minima (tid, fid,
 * subject, visible) para las comprobaciones de visibilidad de temas, y deja el resultado en el $db global.
 */
function nt_preparar($base)
{
    if (!preg_match('/^opg_[a-z0-9_]+$/', $base)) {
        fwrite(STDERR, "Nombre de base de pruebas no valido: $base (debe empezar por opg_)\n");
        exit(2);
    }
    nt_estricto(false);
    $db = new OpgNotifTestDb();
    $db->query("DROP DATABASE IF EXISTS `$base`");
    $db->query("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->usar($base);
    $error = nt_ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/notificaciones_migration.sql'));
    if ($error !== null) {
        fwrite(STDERR, "La migracion de notificaciones fallo: $error\n");
        nt_borrar($db);
        exit(2);
    }
    $db->query('CREATE TABLE `' . TABLE_PREFIX . 'threads` (tid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, fid SMALLINT UNSIGNED NOT NULL DEFAULT 0,'
        . " subject VARCHAR(120) NOT NULL DEFAULT '', visible TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $GLOBALS['nt_base'] = $base;
    $GLOBALS['db'] = $db;
    return $db;
}

/** Otra conexion a la base de la prueba (otra sesion de MySQL). */
function nt_conectar()
{
    return new OpgNotifTestDb($GLOBALS['nt_base']);
}

/** Borra la base de la prueba. */
function nt_borrar($db = null)
{
    $db = $db ?: $GLOBALS['db'];
    if (!empty($GLOBALS['nt_base'])) {
        mysqli_query($db->link, 'DROP DATABASE IF EXISTS `' . $GLOBALS['nt_base'] . '`');
    } elseif ($db->database !== '') {
        mysqli_query($db->link, 'DROP DATABASE IF EXISTS `' . $db->database . '`');
    }
}

// ── Captura de error_log ─────────────────────────────────────────────────────────────────────────────────────────────

/** Desde aqui, error_log escribe en un archivo temporal (no en la salida de la prueba). */
function nt_registro_iniciar()
{
    $GLOBALS['nt_registro'] = tempnam(sys_get_temp_dir(), 'opg_nt_log_');
    ini_set('log_errors', '1');
    ini_set('error_log', $GLOBALS['nt_registro']);
    register_shutdown_function(function () {
        @unlink($GLOBALS['nt_registro']);
    });
}

/** Lo registrado desde la ultima lectura (y lo vacia). */
function nt_registro_leer()
{
    if (empty($GLOBALS['nt_registro'])) {
        return '';
    }
    clearstatcache();
    $txt = (string) @file_get_contents($GLOBALS['nt_registro']);
    file_put_contents($GLOBALS['nt_registro'], '');
    return $txt;
}

// ── Comprobaciones y consultas rapidas ───────────────────────────────────────────────────────────────────────────────

$GLOBALS['nt_fallos'] = 0;
$GLOBALS['nt_total'] = 0;

function nt_prueba($nombre, $obtenido, $esperado)
{
    $GLOBALS['nt_total']++;
    if ($obtenido !== $esperado) {
        $GLOBALS['nt_fallos']++;
        echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
    }
}

function nt_ok($condicion, $nombre)
{
    nt_prueba($nombre, (bool) $condicion, true);
}

/** Imprime el resumen y termina (0 si todo paso). */
function nt_resumen($nombre = 'prueba')
{
    $ok = $GLOBALS['nt_fallos'] === 0;
    echo ($ok ? 'OK' : 'CON FALLOS') . " $nombre: {$GLOBALS['nt_total']} pruebas, {$GLOBALS['nt_fallos']} fallos\n";
    exit($ok ? 0 : 1);
}

/** Consulta directa por la conexion de la prueba (si falla, termina: es un error de la propia prueba). */
function nt_sql($sql, $db = null)
{
    $db = $db ?: $GLOBALS['db'];
    $r = mysqli_query($db->link, $sql);
    if ($r === false) {
        fwrite(STDERR, 'ERROR SQL en la prueba: ' . mysqli_error($db->link) . "\n$sql\n");
        nt_borrar($db);
        exit(3);
    }
    return $r;
}

/** Primera fila de una consulta, o null. */
function nt_una($sql, $db = null)
{
    $r = nt_sql($sql, $db);
    return $r instanceof mysqli_result ? mysqli_fetch_assoc($r) : null;
}

/** Primer valor de la primera fila, o null. */
function nt_valor($sql, $db = null)
{
    $f = nt_una($sql, $db);
    return $f ? reset($f) : null;
}
