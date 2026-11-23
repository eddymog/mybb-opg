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
require_once MYBB_ROOT . 'inc/plugins/lib/salario_faccion.php';

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('SALARIO_FACCION_MSG_COOKIE', 'salario_faccion_msg');

// ── POST: ejecutar o reintentar la semana vigente ───────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $resultado = opg_sf_repartir($uid, $username, 'manual', TIME_NOW);
    $error = $resultado['ok'] ? '' : $resultado['mensaje'];
    $texto = $resultado['mensaje'];
    if ($resultado['codigo'] === 'repartido') {
        $texto .= ' Total entregado: ' . number_format((int) $resultado['total'], 0, ',', '.') . ' berries.';
    }

    my_setcookie(SALARIO_FACCION_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $texto,
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

$semana_actual = opg_sf_semana_madrid(TIME_NOW);
$proximo_pago = opg_sf_proximo_pago_madrid(TIME_NOW);
$sf_semana_actual = htmlspecialchars(date('d/m/Y', strtotime($semana_actual)), ENT_QUOTES, 'UTF-8');
$sf_proximo_pago = htmlspecialchars($proximo_pago->format('d/m/Y \a \l\a\s H:i'), ENT_QUOTES, 'UTF-8');
$sf_proximo_pago_timestamp = (int) $proximo_pago->getTimestamp();

$tarea = $db->fetch_array($db->simple_select('tasks', 'enabled', "file='op_salario_faccion'", array('limit' => 1)));
$sf_estado_automatico = $tarea && (int) $tarea['enabled'] === 1
    ? 'Automatización activa'
    : 'Automatización pendiente de activar';

$salarios_entregados = '';
if ($db->table_exists('op_salario_faccion_repartos')) {
    $query_salarios = $db->simple_select('op_salario_faccion_repartos', '*', "estado='completado'", array(
        'order_by' => 'semana_inicio', 'order_dir' => 'DESC', 'limit' => 10,
    ));
    while ($q = $db->fetch_array($query_salarios)) {
        $origen = $q['origen'] === 'automatico' ? 'Automático' : 'Manual';
        $salarios_entregados .= '<div class="sf-fila"><strong>Semana del '
            . htmlspecialchars(date('d/m/Y', strtotime($q['semana_inicio'])), ENT_QUOTES, 'UTF-8') . '</strong>'
            . '<span>' . $origen . ' · ' . (int) $q['pagados'] . ' personajes · '
            . number_format((int) $q['total'], 0, ',', '.') . ' berries · '
            . htmlspecialchars($q['ejecutor'], ENT_QUOTES, 'UTF-8') . '</span></div>';
    }
}

// Conserva visibles los repartos anteriores a la automatización.
if (substr_count($salarios_entregados, 'class="sf-fila"') < 10) {
    $limite_legacy = 10 - substr_count($salarios_entregados, 'class="sf-fila"');
    $query_legacy = $db->query("SELECT `log` FROM `mybb_op_audit_consola_mod`
        WHERE `log` LIKE '%[Salario Rangos]%' AND `log` NOT LIKE '%[Salario Rangos][Semana %'
        ORDER BY `tiempo` DESC LIMIT {$limite_legacy}");
    while ($q = $db->fetch_array($query_legacy)) {
        $salarios_entregados .= '<div class="sf-fila sf-fila--legacy"><strong>Registro anterior</strong><span>'
            . htmlspecialchars($q['log'], ENT_QUOTES, 'UTF-8') . '</span></div>';
    }
}
if ($salarios_entregados === '') {
    $salarios_entregados = '<p class="opg-vacio">Todavía no se repartió ningún salario de facción.</p>';
}

eval("\$page = \"".$templates->get("staff_salario_faccion")."\";");
output_page($page);
