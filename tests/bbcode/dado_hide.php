<?php
/**
 * [XdY] dentro de [hide]: simula la creación de un post (hooks datahandler_post_insert_*) y su
 * visualización (hook postbit) con los plugins BBCustom_dado y BBCustom_hide reales, contra el
 * MySQL de pruebas (ver tests/aventuras/bootstrap_db.php para levantarlo).
 *   php tests/bbcode/dado_hide.php [ruta_a_la_carpeta_inc_plugins]
 * Con una ruta, carga los plugins de esa carpeta (útil para comparar con la versión anterior).
 */
mysqli_report(MYSQLI_REPORT_OFF);
$GLOBALS['fallos'] = 0; $GLOBALS['total'] = 0;
function prueba($nombre, $obtenido, $esperado) {
    $GLOBALS['total']++;
    if ($obtenido === $esperado) { echo "  ok  $nombre\n"; return; }
    $GLOBALS['fallos']++;
    echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
}

// ── Entorno mínimo de MyBB ───────────────────────────────────────────────────
$raiz_plugins = isset($argv[1]) ? rtrim($argv[1], '/') : dirname(__DIR__, 2) . '/inc/plugins';
$tmp = sys_get_temp_dir() . '/bbcode_raiz';
@mkdir($tmp . '/inc/plugins/lib', 0777, true);
copy(dirname(__DIR__, 2) . '/inc/plugins/lib/spoiler.php', $tmp . '/inc/plugins/lib/spoiler.php');
file_put_contents($tmp . '/inc/class_parser.php', '<?php class postParser { public function parse_message($m, $o = array()) { return $m; } }');
define('IN_MYBB', 1);
define('MYBB_ROOT', $tmp . '/');

class DbShim {
    public $link;
    public function __construct() { $this->link = mysqli_connect('127.0.0.1', 'root', 'opgtest', 'opg_test', 33307); if (!$this->link) { fwrite(STDERR, "Sin MySQL de pruebas\n"); exit(2); } mysqli_set_charset($this->link, 'utf8mb4'); }
    public function query($sql) { $r = mysqli_query($this->link, $sql); if ($r === false) { fwrite(STDERR, "ERROR SQL: " . mysqli_error($this->link) . "\n" . $sql . "\n"); } return $r; }
    public function fetch_array($q) { return mysqli_fetch_assoc($q); }
    public function escape_string($s) { return mysqli_real_escape_string($this->link, (string) $s); }
}
class PluginsShim {
    public $hooks = array();
    public function add_hook($hook, $fn, $prioridad = 10) { $this->hooks[$hook][] = array($prioridad, count($this->hooks[$hook] ?? array()), $fn); }
    public function run_hooks($hook, &$arg) {
        $lista = $this->hooks[$hook] ?? array();
        usort($lista, function ($a, $b) { return $a[0] <=> $b[0] ?: $a[1] <=> $b[1]; });
        foreach ($lista as $h) { call_user_func_array($h[2], array(&$arg)); }
    }
}
$db = new DbShim(); $plugins = new PluginsShim();
$mybb = new stdClass(); $mybb->user = array('uid' => 3);
$thread = array('closed' => 0);
foreach (array('mybb_posts', 'mybb_op_hide', 'mybb_op_dados') as $t) { $db->query("DROP TABLE IF EXISTS $t"); }
$db->query("CREATE TABLE mybb_posts (pid INT PRIMARY KEY, tid INT, uid INT, username VARCHAR(120) DEFAULT '', message MEDIUMTEXT) CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_hide (hid INT AUTO_INCREMENT PRIMARY KEY, tid INT, pid INT, uid INT, hide_counter INT, show_hide TINYINT DEFAULT 0, hide_content MEDIUMTEXT) CHARSET=utf8mb4");
$db->query("CREATE TABLE mybb_op_dados (id INT AUTO_INCREMENT PRIMARY KEY, tid INT, pid INT, uid INT, dado_counter INT, dado_content MEDIUMTEXT) CHARSET=utf8mb4");

// El orden de carga de los plugins es el alfabético (dado antes que hide), como en MyBB.
require $raiz_plugins . '/BBCustom_dado.php';
require $raiz_plugins . '/BBCustom_hide.php';

/** Crea un post como lo haría MyBB: el mensaje llega ya escapado en post_insert_data. */
function crear_post($pid, $uid, $mensaje) {
    global $db, $plugins;
    $escapado = $db->escape_string($mensaje);
    $db->query("INSERT INTO mybb_posts (pid, tid, uid, username, message) VALUES ($pid, 1, $uid, 'Eddy', '$escapado')");
    $datos = new stdClass();
    $datos->post_insert_data = array('uid' => $uid, 'tid' => 1, 'username' => 'Eddy', 'message' => $escapado);
    $datos->return_values = array('pid' => $pid);
    $plugins->run_hooks('datahandler_post_insert_post_end', $datos);
}
/** Lo que ve un usuario ($lector) del post. */
function ver_post($pid, $lector, $cerrado = 0) {
    global $db, $plugins, $mybb, $thread;
    $mybb->user = array('uid' => $lector); $thread = array('closed' => $cerrado);
    $fila = $db->fetch_array($db->query("SELECT * FROM mybb_posts WHERE pid=$pid"));
    $post = array('pid' => $pid, 'tid' => 1, 'uid' => $fila['uid'], 'message' => $fila['message'], 'edittime' => 0);
    $plugins->run_hooks('postbit', $post);
    return $post['message'];
}
function cuenta($texto, $aguja) { return substr_count($texto, $aguja); }

// Cada visualización usa un post nuevo: los plugins procesan cada pid una sola vez por petición.
$siguiente_pid = 100;
function post_nuevo($mensaje, $autor = 3, $revelado = false) {
    global $siguiente_pid, $db;
    $pid = ++$siguiente_pid;
    crear_post($pid, $autor, $mensaje);
    if ($revelado) { $db->query("UPDATE mybb_op_hide SET show_hide=1 WHERE pid=$pid"); }
    return $pid;
}
function ver($mensaje, $lector = 3, $revelado = false) {
    $pid = post_nuevo($mensaje, 3, $revelado);
    return ver_post($pid, $lector);
}
function tiradas($pid) { global $db; return (int) $db->fetch_array($db->query("SELECT COUNT(*) n FROM mybb_op_dados WHERE pid=$pid"))['n']; }

// 1) Dado dentro de un hide
$msg = "Antes [hide]Tiro oculto [2d6] fin[/hide] después";
$pid = post_nuevo($msg);
$v = ver_post($pid, 3);
prueba('dado dentro de hide: el autor ve el resultado, no el texto [2d6]', array(strpos($v, '[2d6]') === false, cuenta($v, 'DADO 1') >= 1, cuenta($v, 'DADO 2') >= 1), array(true, true, true));
prueba('dado dentro de hide: se guardó una sola tirada', tiradas($pid), 1);
prueba('el hide sigue funcionando (autor: contenido oculto)', cuenta($v, 'Contenido Oculto') >= 1, true);
$v = ver($msg, 9);
prueba('un lector ajeno no ve ni el hide ni el dado', array(strpos($v, 'DADO 1') === false, strpos($v, '[2d6]') === false), array(true, true));
$v = ver($msg, 9, true);
prueba('revelado el hide, un lector ajeno ve el resultado del dado', array(cuenta($v, 'DADO 1') >= 1, strpos($v, '[2d6]') === false), array(true, true));
prueba('el texto de fuera del hide se conserva', array(strpos($v, 'Antes') !== false, strpos($v, 'después') !== false), array(true, true));

// 2) Dados dentro y fuera del hide en el mismo post
$pid = post_nuevo("Fuera [1d20] y [hide]dentro [3d4][/hide] y otra vez fuera [1d6]", 3, true);
$v = ver_post($pid, 9);
prueba('dados dentro y fuera: ninguno queda como [XdY] ni sin resultado', array(preg_match('/\[\d+d\d+\]/', $v), cuenta($v, 'Resultado de dado no encontrado'), preg_match('/\[dado_guardado=\d+\]/', $v)), array(0, 0, 0));
prueba('se guardaron las tres tiradas', tiradas($pid), 3);

// 3) Dado fuera del hide en un post que tiene hide (la conversión no debe perderse)
$v = ver("Tiro [1d20] visible y [hide]secreto sin dados[/hide]", 3);
prueba('dado fuera del hide en un post con hide: se muestra el resultado', array(strpos($v, '[1d20]') === false, cuenta($v, 'DADO 1') >= 1), array(true, true));

// 4) Comillas y caracteres especiales dentro del hide
$v = ver("[hide]Dijo: \"It's [2d6]\" y \\ barra[/hide]", 3);
prueba('comillas dentro del hide con dado: se conservan y el dado funciona', array(strpos($v, "It's") !== false, strpos($v, '[2d6]') === false, cuenta($v, 'DADO 1') >= 1), array(true, true, true));

// 5) Post sin dados ni hide, solo hide y solo dados
prueba('post sin hide ni dados: igual', ver("Solo texto", 3), 'Solo texto');
$pid = post_nuevo("[hide]Solo hide[/hide]");
prueba('solo hide: funciona y no crea tiradas', array(cuenta(ver_post($pid, 3), 'Solo hide') >= 1, tiradas($pid)), array(true, 0));
$v = ver("Solo dado [2d10]", 3);
prueba('solo dado: funciona', array(strpos($v, '[2d10]') === false, cuenta($v, 'DADO 2') >= 1), array(true, true));

// 6) Dos hides, uno con dado y otro sin
$v = ver("[hide]uno [1d6][/hide] medio [hide]dos[/hide]", 9, true);
prueba('dos hides, uno con dado: ambos se ven y el dado funciona', array(cuenta($v, 'uno') >= 1, cuenta($v, 'dos') >= 1, strpos($v, '[1d6]') === false, cuenta($v, 'DADO 1') >= 1), array(true, true, true, true));

// 7) Comillas simples en un post con hide y sin dados (el SQL no debe romperse)
$pid = post_nuevo("[hide]No lo sabías: it's 'secreto'[/hide]");
prueba('comillas en un hide sin dados: se guarda y se ve', cuenta(ver_post($pid, 3), "it's 'secreto'") >= 1, true);

echo $GLOBALS['fallos'] === 0 ? "OK: {$GLOBALS['total']} pruebas, 0 fallos\n" : "CON FALLOS: {$GLOBALS['total']} pruebas, {$GLOBALS['fallos']} fallos\n";
exit($GLOBALS['fallos'] ? 1 : 0);
