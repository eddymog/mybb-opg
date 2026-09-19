<?php
/**
 * Staff - Salario semanal de facción
 *
 * Un solo botón: paga el sueldo semanal (configurado en Configuración del
 * foro → OPG Facciones, columna sueldo_semanal de cada rango) a todos los
 * personajes con un rango de facción pagado.
 *
 * Reescrito por 2 problemas reales que tenía:
 * 1. CSRF: el <form> solo llevaba un campo oculto `salario=1` fijo, sin
 *    my_post_key ni chequeo — cualquier POST a esta URL, sin importar de
 *    dónde viniera, disparaba el pago completo a toda la facción.
 * 2. `$user_uid`, `$staff` y `$razon` no estaban definidos en ningún lado:
 *    log_audit_currency() recibía un uid "que paga" vacío (rompía la
 *    atribución en mybb_op_audit_general) y el INSERT a
 *    mybb_op_audit_consola_mod guardaba `staff`/`razon` vacíos.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'salario_faccion.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('SALARIO_FACCION_MSG_COOKIE', 'salario_faccion_msg');

// ── POST: repartir ───────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $error = '';
    $pagados = 0;

    if (!function_exists('op_facciones_sueldos_semanales')) {
        $error = 'No se pudo repartir: el plugin OPG Facciones no está activo.';
    } else {
        $salarios = op_facciones_sueldos_semanales();
        if (empty($salarios)) {
            $error = 'No hay ningún rango con sueldo_semanal configurado (Configuración del foro → OPG Facciones).';
        } else {
            $rangos = array_map(array($db, 'escape_string'), array_keys($salarios));
            $query_rango = $db->query("SELECT * FROM `mybb_op_fichas` WHERE rango IN ('" . implode("','", $rangos) . "')");

            while ($q = $db->fetch_array($query_rango)) {
                $user_fid = (int) $q['fid'];
                $faccion  = $q['faccion'];
                $nuevo    = intval($q['berries']) + $salarios[$q['rango']];
                log_audit_currency($uid, $username, $user_fid, "[Salarios][{$faccion}]", 'berries', $nuevo);
                $pagados++;
            }

            $log_texto = "[Salario Rangos] Última vez entregados por {$username} ({$uid}) a las: " . date('Y-m-d H:i:s') . " — {$pagados} personajes.";
            $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Salario Facción', '" . $db->escape_string($log_texto) . "')");
        }
    }

    my_setcookie(SALARIO_FACCION_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : "Salarios entregados a {$pagados} personajes.",
    ))), 15, true);
    header('Location: salario_faccion.php');
    exit;
}

// ── GET: panel + historial ───────────────────────────────────────────────────

$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[SALARIO_FACCION_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[SALARIO_FACCION_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(SALARIO_FACCION_MSG_COOKIE);
}

$salarios_entregados = '';
$query_salarios = $db->query("SELECT * FROM `mybb_op_audit_consola_mod` WHERE log LIKE '%[Salario Rangos]%' ORDER BY tiempo DESC LIMIT 10");
while ($q = $db->fetch_array($query_salarios)) {
    $salarios_entregados .= '<div class="sf-fila">' . htmlspecialchars($q['log'], ENT_QUOTES, 'UTF-8') . '</div>';
}
if ($salarios_entregados === '') {
    $salarios_entregados = '<p class="opg-vacio">Todavía no se repartió ningún salario de facción.</p>';
}

eval("\$page = \"".$templates->get("staff_salario_faccion")."\";");
output_page($page);
