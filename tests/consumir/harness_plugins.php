<?php
/**
 * Arnes para probar los plugins BBCustom reales (consumir, dado, hide, mantenida) contra el MySQL de pruebas, simulando
 * lo que hace el PostDataHandler de MyBB (inc/datahandlers/post.php) al crear y al editar un post:
 *
 *   - Crear: escapa el mensaje para SQL, lo inserta en mybb_posts, deja ese valor escapado en post_insert_data y corre
 *     datahandler_post_insert_post_end (o _thread_end) con el pid en return_values.
 *   - Editar: deja en post_update_data['message'] el mensaje escapado, corre datahandler_post_update, guarda
 *     post_update_data con un UPDATE (como update_query) y corre datahandler_post_update_end. La edicion rapida
 *     (xmlhttp.php) no manda uid ni username.
 *   - Mostrar: corre el hook postbit sobre el mensaje guardado (el parser de MyBB se sustituye por la identidad).
 *
 * Los plugins se cargan desde una copia temporal de MYBB_ROOT con las librerias reales (inc/plugins/lib/consumir.php,
 * spoiler.php, op/functions/op_functions.php) y un postParser minimo.
 */

$raiz_repo = realpath(__DIR__ . '/../../');
$tmp = sys_get_temp_dir() . '/opg_consumir_raiz_' . getmypid();
@mkdir($tmp . '/inc/plugins/lib', 0777, true);
@mkdir($tmp . '/op/functions', 0777, true);
foreach (array('inc/plugins/lib/spoiler.php', 'inc/plugins/lib/consumir.php', 'op/functions/op_functions.php') as $f) {
    copy($raiz_repo . '/' . $f, $tmp . '/' . $f);
}
file_put_contents($tmp . '/inc/class_parser.php', '<?php class postParser { public function parse_message($m, $o = array()) { return $m; } }');
define('IN_MYBB', 1);
define('MYBB_ROOT', $tmp . '/');
register_shutdown_function(function () use ($tmp) {
    foreach (array('inc/plugins/lib/spoiler.php', 'inc/plugins/lib/consumir.php', 'op/functions/op_functions.php', 'inc/class_parser.php') as $f) {
        @unlink($tmp . '/' . $f);
    }
    foreach (array('/inc/plugins/lib', '/inc/plugins', '/inc', '/op/functions', '/op', '') as $d) {
        @rmdir($tmp . $d);
    }
});

require_once __DIR__ . '/bootstrap_db.php';

// De inc/functions.php de MyBB (lo usan las versiones anteriores de los plugins)
if (!function_exists('htmlspecialchars_uni')) {
    function htmlspecialchars_uni($message)
    {
        $message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message);
        return str_replace(array("<", ">", "\""), array("&lt;", "&gt;", "&quot;"), $message);
    }
}

class CsPluginsShim
{
    public $hooks = array();
    public function add_hook($hook, $fn, $prioridad = 10)
    {
        $this->hooks[$hook][] = array($prioridad, isset($this->hooks[$hook]) ? count($this->hooks[$hook]) : 0, $fn);
    }
    public function run_hooks($hook, &$arg = '')
    {
        $lista = isset($this->hooks[$hook]) ? $this->hooks[$hook] : array();
        usort($lista, function ($a, $b) { return $a[0] <=> $b[0] ?: $a[1] <=> $b[1]; });
        foreach ($lista as $h) {
            call_user_func_array($h[2], array(&$arg));
        }
        return $arg;
    }
    /** Invierte el orden de registro de los hooks de la misma prioridad (otro orden de carga de los plugins). */
    public function invertir_orden()
    {
        foreach ($this->hooks as $hook => $lista) {
            $n = count($lista);
            foreach ($lista as $i => $h) {
                $this->hooks[$hook][$i][1] = $n - $h[1];
            }
        }
    }
}

/** Doble del PostDataHandler: solo las propiedades que leen los plugins. */
class CsPostHandler
{
    public $data = array();
    public $post_insert_data = array();
    public $post_update_data = array();
    public $return_values = array();
    public $pid = 0;
    public $first_post = false;
}

/** Carga los plugins reales (en el orden alfabetico, como los lista MyBB) y deja los globales que usan. */
function cs_cargar_plugins(array $nombres = array('BBCustom_consumir', 'BBCustom_dado', 'BBCustom_hide', 'BBCustom_mantenida'))
{
    global $plugins, $mybb, $thread;
    $plugins = new CsPluginsShim();
    $mybb = new stdClass();
    $mybb->user = array('uid' => 0);
    $thread = array('closed' => 0);
    // OPG_PLUGINS_DIR: otra carpeta de plugins (por ejemplo la version anterior, para ver que la prueba la detecta)
    $dir = getenv('OPG_PLUGINS_DIR') ? rtrim(getenv('OPG_PLUGINS_DIR'), '/') : realpath(__DIR__ . '/../../') . '/inc/plugins';
    foreach ($nombres as $n) {
        require_once $dir . '/' . $n . '.php';
    }
}

/** Crea un post como MyBB y devuelve su pid. $visible = -2 para un borrador. */
function cs_crear_post($uid, $tid, $mensaje, $username = 'Eddy', $visible = 1, $primer_post = false)
{
    global $db, $plugins;
    $escapado = $db->escape_string($mensaje);
    $u = $db->escape_string($username);
    nt_sql("INSERT INTO mybb_posts (tid, uid, username, message, visible) VALUES (" . (int)$tid . ", " . (int)$uid . ", '$u', '$escapado', " . (int)$visible . ")");
    $pid = (int)mysqli_insert_id($db->link);
    $h = new CsPostHandler();
    $h->post_insert_data = array('tid' => (int)$tid, 'uid' => (int)$uid, 'username' => $u, 'message' => $escapado, 'visible' => (int)$visible);
    $h->return_values = array('pid' => $pid, 'visible' => (int)$visible);
    $h->pid = $pid;
    $plugins->run_hooks($primer_post ? 'datahandler_post_insert_thread_end' : 'datahandler_post_insert_post_end', $h);
    return $pid;
}

/** Edita un post como MyBB. $rapida: edicion rapida (sin uid ni username en los datos). */
function cs_editar_post($pid, $mensaje, $editor_uid = null, $rapida = false)
{
    global $db, $plugins;
    $fila = nt_una("SELECT * FROM mybb_posts WHERE pid = " . (int)$pid);
    $h = new CsPostHandler();
    $h->data = array('pid' => (int)$pid, 'message' => $mensaje, 'edit_uid' => $editor_uid === null ? (int)$fila['uid'] : (int)$editor_uid);
    if (!$rapida) {
        $h->data['uid'] = (int)$fila['uid'];
        $h->data['username'] = $fila['username'];
    }
    $h->pid = (int)$pid;
    $h->post_update_data = array('message' => $db->escape_string($mensaje), 'edituid' => $h->data['edit_uid']);
    $plugins->run_hooks('datahandler_post_update', $h);
    $sets = array();
    foreach ($h->post_update_data as $k => $v) {
        if ($k === 'message') {
            $sets[] = "`message` = '$v'";
        }
    }
    nt_sql("UPDATE mybb_posts SET " . implode(', ', $sets) . " WHERE pid = " . (int)$pid);
    $plugins->run_hooks('datahandler_post_update_end', $h);
}

/** Mensaje guardado del post. */
function cs_mensaje($pid)
{
    return nt_valor("SELECT message FROM mybb_posts WHERE pid = " . (int)$pid);
}

/** Lo que ve $lector del post (hook postbit). */
function cs_ver($pid, $lector = 0, $mensaje_html = null)
{
    global $plugins, $mybb;
    $fila = nt_una("SELECT * FROM mybb_posts WHERE pid = " . (int)$pid);
    $mybb->user = array('uid' => (int)$lector);
    $post = array('pid' => (int)$pid, 'tid' => (int)$fila['tid'], 'uid' => (int)$fila['uid'], 'username' => $fila['username'],
        'message' => $mensaje_html === null ? $fila['message'] : $mensaje_html, 'edittime' => 0);
    $plugins->run_hooks('postbit', $post);
    return $post['message'];
}
