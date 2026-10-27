<?php
/** Respuestas para el script del indice. Nombre neutro a proposito. Dos peticiones: c = configuracion, t = reclamo con codigo. */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'ajuste_indice.php');
require_once './../global.php';
require_once './functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_secretos.php';

global $mybb;

$uid = (int)$mybb->user['uid'];

function opg_hlw_secret_out($body, $type, $status = 200)
{
    http_response_code($status);
    header('Content-Type: ' . $type . '; charset=utf-8');
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

function opg_hlw_secret_json($data, $status = 200)
{
    opg_hlw_secret_out(json_encode($data, JSON_UNESCAPED_UNICODE), 'application/json', $status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $uid <= 0 || !verify_post_check($mybb->get_input('my_post_key'), true)) {
    opg_hlw_secret_json(opg_hlw_secret_client_result(array('estado' => 'closed')), 403);
}

// Configuracion: se entrega cifrada con una clave derivada del billete.
$ticket = $mybb->get_input('c');
if ($ticket !== '') {
    $config = opg_hlw_secret_client_config($uid, $ticket);
    opg_hlw_secret_out($config === null ? '' : opg_hlw_secret_config_encode($config, $ticket), 'text/plain');
}

// Reclamo con codigo. El motivo del rechazo es solo para las cuentas de prueba.
$result = opg_hlw_secret_claim_token($uid, $mybb->get_input('t'));
opg_hlw_secret_json(opg_hlw_secret_client_result($result, opg_hlw_secrets_is_tester($uid)));
