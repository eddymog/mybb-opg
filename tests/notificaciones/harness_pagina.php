<?php
/**
 * Ejecuta op/notificaciones.php como si fuera una peticion HTTP, contra una base de pruebas ya preparada (la crea
 * pagina_db.php con nt_preparar), y devuelve JSON con estado, cabeceras, cuerpo y las sentencias SQL que lanzo la pagina.
 * Mismo enfoque que tests/aventuras/harness_pagina.php.
 *
 *   php harness_pagina.php '<json>'   json: base, uid, get, metodo, server
 */
$p = json_decode($argv[1], true);
require __DIR__ . '/bootstrap_db.php';
$db = new OpgNotifTestDb($p['base']);
$GLOBALS['db'] = $db;
if (!defined('TIME_NOW')) {
    define('TIME_NOW', isset($p['ahora']) ? (int) $p['ahora'] : time());
}

$_GET = isset($p['get']) ? $p['get'] : array();
$_POST = array();
$_SERVER['REQUEST_METHOD'] = isset($p['metodo']) ? $p['metodo'] : 'GET';
foreach ((isset($p['server']) ? $p['server'] : array()) as $k => $v) {
    $_SERVER[$k] = $v;
}
$mybb = new stdClass();
$mybb->user = array('uid' => (int) $p['uid'], 'username' => 'Test');
$mybb->post_code = 'clave-valida';
$mybb->settings = array('bburl' => 'https://onepiecegaiden.com', 'bbname' => 'One Piece Gaiden');
$headerinclude = '';
$header = '<div id="op-notif-campana">CAMPANA</div>';
$footer = '';

function output_page($html) { echo $html; }
function my_date($f, $ts) { return date($f, $ts); }
class PlantillasFalsas
{
    public function get($n)
    {
        return str_replace("\\'", "'", addslashes(file_get_contents(MYBB_ROOT . "templates/One_Piece_Gaiden_Templates/$n.html")));
    }
}
$templates = new PlantillasFalsas();

// header() no guarda nada en CLI: se reemplaza por una funcion que lo registra.
$GLOBALS['cabeceras'] = array();
function hdr_capturar($h) { $GLOBALS['cabeceras'][] = $h; }
ob_start();
register_shutdown_function(function () {
    $cuerpo = ob_get_clean();
    echo json_encode(array('estado' => http_response_code() ?: 200, 'cabeceras' => $GLOBALS['cabeceras'], 'cuerpo' => $cuerpo,
        'sentencias' => $GLOBALS['db']->sentencias), JSON_UNESCAPED_UNICODE);
});

$fuente = file_get_contents(MYBB_ROOT . 'op/notificaciones.php');
$fuente = preg_replace('/^<\?php/', '', $fuente, 1);
$fuente = preg_replace('/^require_once "\.\/\.\.\/global\.php";\s*$/m', '', $fuente);
$fuente = preg_replace("/^define\('IN_MYBB', 1\);\s*$/m", '', $fuente);
$fuente = str_replace('header(', 'hdr_capturar(', $fuente);
eval($fuente);
