<?php
/**
 * Ayudante de conexion para la prueba del plugin de notificaciones (issues #5 y #6).
 *
 * Modelado sobre tests/aventuras/bootstrap_db.php (MySQL 5.7 de pruebas en el
 * puerto 33307, ver el encabezado de ese archivo), solo con los metodos de $db
 * que usa inc/plugins/opg_notificaciones.php. Simula lo minimo de MyBB:
 * IN_MYBB, MYBB_ROOT, TABLE_PREFIX, el global $db y un $plugins que solo guarda
 * los hooks (el plugin los registra al incluirse).
 *
 * Uso: require este archivo, luego llamar a plugin_conectar($base).
 */

if (!defined('IN_MYBB')) {
    define('IN_MYBB', 1);
}
if (!defined('TABLE_PREFIX')) {
    define('TABLE_PREFIX', 'mybb_');
}
if (!defined('MYBB_ROOT')) {
    define('MYBB_ROOT', realpath(__DIR__ . '/../../') . '/');
}
if (!defined('TIME_NOW')) {
    define('TIME_NOW', time());
}

/** Sustituto minimo de $plugins: guarda los hooks registrados. */
class PluginsShim
{
    public $hooks = array();
    public function add_hook($hook, $funcion)
    {
        $this->hooks[] = array($hook, $funcion);
    }
}
$plugins = new PluginsShim();

class DbShim
{
    public $link;
    public $table_prefix = TABLE_PREFIX;
    public function __construct($base = '')
    {
        $host = getenv('OPG_TEST_DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('OPG_TEST_DB_PORT') ?: 33307);
        $user = getenv('OPG_TEST_DB_USER') ?: 'root';
        $pass = getenv('OPG_TEST_DB_PASS') ?: 'opgtest';
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->link = mysqli_connect($host, $user, $pass, $base, $port);
        if (!$this->link) {
            fwrite(STDERR, "Sin MySQL de pruebas: " . mysqli_connect_error() . "\n");
            exit(2);
        }
        mysqli_set_charset($this->link, 'utf8mb4');
    }
    public function query($sql, $hide_errors = 0)
    {
        $r = mysqli_query($this->link, $sql);
        if ($r === false && !$hide_errors) {
            fwrite(STDERR, "ERROR SQL: " . mysqli_error($this->link) . "\n$sql\n");
            exit(3);
        }
        return $r;
    }
    public function write_query($sql, $hide_errors = 0)
    {
        return $this->query($sql, $hide_errors);
    }
    public function fetch_array($q)
    {
        return mysqli_fetch_assoc($q);
    }
    public function escape_string($s)
    {
        return mysqli_real_escape_string($this->link, (string) $s);
    }
    /** Como MyBB: tabla sin prefijo; las condiciones y los valores ya vienen escapados por quien llama. */
    public function simple_select($tabla, $campos = '*', $condiciones = '', $opciones = array())
    {
        $sql = "SELECT {$campos} FROM " . $this->table_prefix . $tabla . ($condiciones !== '' ? " WHERE {$condiciones}" : '');
        if (!empty($opciones['limit'])) {
            $sql .= ' LIMIT ' . (int) $opciones['limit'];
        }
        return $this->query($sql);
    }
    public function fetch_field($q, $campo)
    {
        $f = mysqli_fetch_assoc($q);
        return $f ? $f[$campo] : null;
    }
    public function insert_query($tabla, $datos)
    {
        $valores = array_map(function ($v) {
            return "'" . $v . "'";
        }, $datos);
        $this->query('INSERT INTO ' . $this->table_prefix . $tabla . ' (`' . implode('`,`', array_keys($datos)) . '`) VALUES (' . implode(',', $valores) . ')');
        return mysqli_insert_id($this->link);
    }
    public function update_query($tabla, $datos, $donde = '')
    {
        $sets = array();
        foreach ($datos as $k => $v) {
            $sets[] = "`{$k}`='{$v}'";
        }
        return $this->query('UPDATE ' . $this->table_prefix . $tabla . ' SET ' . implode(',', $sets) . ($donde !== '' ? " WHERE {$donde}" : ''));
    }
    /** Igual que MyBB: nombre sin prefijo, se comprueba con SHOW TABLES LIKE. */
    public function table_exists($tabla)
    {
        $like = str_replace(array('_', '%'), array('\_', '\%'), TABLE_PREFIX . $tabla);
        $r = mysqli_query($this->link, "SHOW TABLES LIKE '" . $this->escape_string($like) . "'");
        return $r && mysqli_num_rows($r) > 0;
    }
}

/** Crea el global $db apuntando a la base indicada. */
function plugin_conectar($base)
{
    global $db;
    $db = new DbShim($base);
    return $db;
}
