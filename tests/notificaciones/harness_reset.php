<?php
/**
 * Ejecuta la parte de acciones de op/staff/reset_peticiones.php (rechazar, guardar, aplicar) como si fuera una peticion
 * HTTP, contra una base de pruebas ya preparada por reset_pagina_db.php, y devuelve JSON con las cabeceras y el aviso de
 * la pagina ($alert). La vista (HTML) no se ejecuta: el codigo se corta antes de "Helper: render option select".
 * Mismo enfoque que harness_pagina.php (issue #31).
 *
 *   php harness_reset.php '<json>'
 *   json: base, uid, get, post, metodo, pausa_tras (texto de una sentencia), pausa_ms
 *
 * pausa_tras/pausa_ms: tras ejecutar la primera sentencia que contenga ese texto, el proceso espera; asi dos procesos
 * pueden leer el reset como pendiente a la vez (prueba de concurrencia).
 */
$p = json_decode($argv[1], true);
require __DIR__ . '/bootstrap_db.php';

class OpgResetPausaDb extends OpgNotifTestDb
{
    public $pausa_tras = '';
    public $pausa_ms = 0;
    public function query($sql, $hide_errors = 0, $write_query = 0)
    {
        $r = parent::query($sql, $hide_errors, $write_query);
        if ($this->pausa_tras !== '' && strpos($sql, $this->pausa_tras) !== false) {
            $this->pausa_tras = '';
            usleep($this->pausa_ms * 1000);
        }
        return $r;
    }
}
$db = new OpgResetPausaDb($p['base']);
$db->pausa_tras = isset($p['pausa_tras']) ? (string) $p['pausa_tras'] : '';
$db->pausa_ms = isset($p['pausa_ms']) ? (int) $p['pausa_ms'] : 0;
$GLOBALS['db'] = $db;
if (!defined('TIME_NOW')) {
    define('TIME_NOW', time());
}

$_GET = isset($p['get']) ? $p['get'] : array();
$_POST = isset($p['post']) ? $p['post'] : array();
$_SERVER['REQUEST_METHOD'] = isset($p['metodo']) ? $p['metodo'] : 'GET';

/** Lo minimo de MyBB que usa la pagina: input (GET y POST juntos), request_method, user y post_code. */
class MybbFalso
{
    public $input = array();
    public $request_method = '';
    public $user = array();
    public $post_code = 'clave-valida';
    public function get_input($k, $tipo = 0)
    {
        $v = isset($this->input[$k]) ? $this->input[$k] : '';
        return $tipo === 1 ? (int) $v : $v;
    }
}
$mybb = new MybbFalso();
$mybb->input = array_merge($_GET, $_POST);
$mybb->request_method = strtolower($_SERVER['REQUEST_METHOD']);
$mybb->user = array('uid' => (int) $p['uid'], 'username' => 'Staff');
$templates = null;

// Lo mismo que MyBB: compara con la post_code de la sesion.
function verify_post_check($clave, $silencioso = false) { global $mybb; return is_string($clave) && $clave !== '' && $clave === $mybb->post_code; }
// op/functions/op_functions.php: el staff es quien diga la prueba; la auditoria se guarda en una tabla minima.
function is_staff($uid) { return (int) $uid === 1; }
function log_audit($uid, $username, $categoria, $log)
{
    global $db;
    $db->query("INSERT INTO mybb_op_audit_general (uid, categoria, log) VALUES ('" . (int) $uid . "', '" . $db->escape_string($categoria)
        . "', '" . $db->escape_string($log) . "')");
}

$GLOBALS['cabeceras'] = array();
function hdr_capturar($h) { $GLOBALS['cabeceras'][] = $h; }
ob_start();
register_shutdown_function(function () {
    $cuerpo = ob_get_clean();
    echo json_encode(array('cabeceras' => $GLOBALS['cabeceras'], 'alert' => isset($GLOBALS['alert']) ? $GLOBALS['alert'] : null,
        'cuerpo' => $cuerpo), JSON_UNESCAPED_UNICODE);
});

$fuente = file_get_contents(MYBB_ROOT . 'op/staff/reset_peticiones.php');
$corte = strpos($fuente, '// ─── Helper: render option select');
if ($corte === false) {
    fwrite(STDERR, "No se encontro el marcador de la vista en reset_peticiones.php\n");
    exit(4);
}
$fuente = substr($fuente, 0, $corte);
$fuente = preg_replace('/^<\?php/', '', $fuente, 1);
$fuente = preg_replace('/^ini_set\(.*$/m', '', $fuente);
$fuente = preg_replace('/^define\("IN_MYBB", 1\);\s*$/m', '', $fuente);
$fuente = preg_replace('/^require_once "\.\/\.\.\/\.\.\/global\.php";\s*$/m', '', $fuente);
$fuente = preg_replace('/^require "\.\/\.\.\/\.\.\/inc\/config\.php";\s*$/m', '', $fuente);
$fuente = preg_replace('/^require_once "\.\/\.\.\/functions\/op_functions\.php";\s*$/m', '', $fuente);
$fuente = str_replace('header(', 'hdr_capturar(', $fuente);
eval($fuente);
