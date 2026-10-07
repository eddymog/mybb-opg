<?php
/**
 * Ejecuta una página del tablón (op/tablon_de_aventuras.php, op/staff/*.php) como si
 * fuera una petición HTTP, contra el MySQL de pruebas, y devuelve JSON con
 * estado, cabeceras y cuerpo.   php harness_pagina.php '<json>'
 */
$p = json_decode($argv[1], true);
require __DIR__ . '/bootstrap_db.php';
$GLOBALS['roles'] = $p['roles'] ?? array('mod' => array(), 'staff' => array(50), 'narra' => array(60));

$_GET = $p['get'] ?? array();
$_POST = $p['post'] ?? array();
$_SERVER['REQUEST_METHOD'] = $p['metodo'] ?? 'GET';
foreach (($p['server'] ?? array()) as $k => $v) { $_SERVER[$k] = $v; }
$mybb->user = array('uid' => (int) ($p['uid'] ?? 0), 'username' => 'Test');
$mybb->post_code = 'clave-valida';
$mybb->settings = array('bburl' => 'https://onepiecegaiden.com', 'bbname' => 'One Piece Gaiden');
$headerinclude = ''; $header = ''; $footer = '';

function error_no_permission() { echo "SIN_PERMISO"; exit; }
function verify_post_check($clave, $silent = false) { return $clave === 'clave-valida'; }
function output_page($html) { echo $html; }
function my_date($f, $ts) { return date($f, $ts); }
class PlantillasFalsas { public function get($n) { $c = file_get_contents(MYBB_ROOT . "templates/One_Piece_Gaiden_Templates/$n.html"); return str_replace("\\'", "'", addslashes($c)); } }
$templates = new PlantillasFalsas();
class PostDataHandler { public $action; private $d; public function __construct($a) {} public function set_data($d) { $this->d = $d; } public function validate_post() { return true; } public function get_friendly_errors() { return array(); }
    public function insert_post() { global $db; $d = $this->d; $db->query("SET SESSION sql_mode = ''", 1); $pid = random_int(100000, 999999999); $db->query("INSERT INTO mybb_posts (pid, tid, uid, username, message, dateline) VALUES ($pid, " . (int) $d['tid'] . ", " . (int) $d['uid'] . ", 'U', '" . $db->escape_string($d['message']) . "', " . time() . ")", 1); return array('pid' => $pid); } }

// header() no guarda nada en CLI: se reemplaza por una función que lo registra.
$GLOBALS['cabeceras'] = array();
function hdr_capturar($h) { $GLOBALS['cabeceras'][] = $h; }
ob_start();
register_shutdown_function(function () {
    $cuerpo = ob_get_clean();
    echo json_encode(array('estado' => http_response_code() ?: 200, 'cabeceras' => $GLOBALS['cabeceras'], 'cuerpo' => $cuerpo), JSON_UNESCAPED_UNICODE);
});

$fuente = file_get_contents(MYBB_ROOT . $p['pagina']);
$fuente = preg_replace('/^<\?php/', '', $fuente, 1);
$fuente = preg_replace('/^require(_once)? "\.\/\.\.\/(\.\.\/)?global\.php";\s*$/m', '', $fuente);
$fuente = preg_replace('/^require "\.\/\.\.\/(\.\.\/)?inc\/config\.php";\s*$/m', '', $fuente);
$fuente = preg_replace('/^require_once "\.\/(\.\.\/)?functions\/op_functions\.php";\s*$/m', '', $fuente);
$fuente = preg_replace('/^require_once "\.\/\.\.\/functions\/op_functions\.php";\s*$/m', '', $fuente);
$fuente = str_replace('header(', 'hdr_capturar(', $fuente);
eval($fuente);
