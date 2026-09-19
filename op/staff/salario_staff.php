<?php
/**
 * Staff - Salario de staff (kuros por rol)
 *
 * Paga kuros a miembros del staff según su grupo: 500 (admin), 400 (staff/mod)
 * o 200 (narrador) — en lote (checkboxes) o a una UID puntual, con monto
 * personalizado opcional.
 *
 * Reescrito por 3 problemas reales que tenía:
 * 1. CSRF: ninguna de las dos acciones llevaba my_post_key — sobre una
 *    herramienta que acuña kuros para admin/staff, eso es manipulación de
 *    economía al nivel más alto posible.
 * 2. `$is_admin` se recalculaba a mano leyendo `usergroup`/`additionalgroups`
 *    en vez de usar `is_admin($uid)`, que ya existe en op_functions.php y
 *    hace exactamente eso.
 * 3. El log de repartos (`$salarios_kuros`) se pintaba sin escapar.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'salario_staff.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_admin($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('SALARIO_STAFF_MSG_COOKIE', 'salario_staff_msg');
$SALARIO_ADMIN_GROUPS = array('4');
$SALARIO_STAFF_GROUPS = array('3', '6', '14', '16');
$SALARIO_NARRA_GROUPS = array('15');

function salario_staff_monto($row, $admin_groups, $staff_groups, $narra_groups)
{
    $groups = array_map('trim', explode(',', $row['additionalgroups']));
    $groups[] = (string) (int) $row['usergroup'];

    if (array_intersect($admin_groups, $groups)) { return 500; }
    if (array_intersect($staff_groups, $groups)) { return 400; }
    if (array_intersect($narra_groups, $groups)) { return 200; }
    return 0;
}

// ── POST: pagar ───────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
    $error = '';
    $ok = '';

    if ($accion === 'lote') {
        $uids_seleccionados = array_filter(array_map('intval', $mybb->get_input('uids_seleccionados', MyBB::INPUT_ARRAY)));

        if (empty($uids_seleccionados)) {
            $error = 'No se seleccionó a ningún miembro del staff.';
        } else {
            $uids_in = implode(',', $uids_seleccionados);
            $q_roles = $db->query("
                SELECT u.uid, u.usergroup, u.additionalgroups, f.kuro, f.nombre
                FROM mybb_users u INNER JOIN mybb_op_fichas f ON f.fid = u.uid
                WHERE u.uid IN ({$uids_in})
            ");

            $nombres_id = array();
            while ($row = $db->fetch_array($q_roles)) {
                $fid = (int) $row['uid'];
                $amount = salario_staff_monto($row, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS);
                if (!$amount) { continue; }

                $nuevo = intval($row['kuro']) + $amount;
                $db->query("UPDATE `mybb_op_fichas` SET kuro = kuro + {$amount} WHERE fid = {$fid}");
                log_audit_currency($uid, $username, $fid, "[Kuros][Salario Roles +{$amount}]", 'kuros', $nuevo);
                // Nombre escapado a mano porque el log se guarda y se pinta como HTML de
                // verdad (el link es intencional, igual que en log_entregas.php) — sin esto,
                // un nombre de personaje con HTML quedaría sin escapar en el log.
                $nombres_id[] = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8') . " (<a href='/op/personaje.php?uid={$fid}'>{$fid}</a>) +{$amount}";
            }

            $log_texto = "[Salario Kuros Roles] Kuros por rol entregados por {$username} ({$uid}) a las: " . date('Y-m-d H:i:s') . ". Pagos: " . implode(', ', $nombres_id) . '.';
            $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Salario Roles', '" . $db->escape_string($log_texto) . "')");
            $ok = 'Kuros entregados a ' . count($nombres_id) . ' miembros del staff.';
        }
    } elseif ($accion === 'uid') {
        $uid_pagar = (int) $mybb->get_input('uid_pagar', MyBB::INPUT_INT);
        $custom_kuros = (int) $mybb->get_input('custom_kuros', MyBB::INPUT_INT);

        if (!$uid_pagar) {
            $error = 'UID inválida.';
        } else {
            $row = $db->fetch_array($db->query("
                SELECT u.uid, u.usergroup, u.additionalgroups, f.kuro, f.nombre
                FROM mybb_users u INNER JOIN mybb_op_fichas f ON f.fid = u.uid
                WHERE u.uid = {$uid_pagar} LIMIT 1
            "));

            if (!$row) {
                $error = 'No se encontró ficha para esa UID.';
            } else {
                $amount = $custom_kuros > 0 ? $custom_kuros : salario_staff_monto($row, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS);
                if (!$amount) {
                    $error = 'El usuario no pertenece a ningún grupo de staff.';
                } else {
                    $nuevo = intval($row['kuro']) + $amount;
                    $db->query("UPDATE `mybb_op_fichas` SET kuro = kuro + {$amount} WHERE fid = {$uid_pagar}");
                    log_audit_currency($uid, $username, $uid_pagar, "[Kuros][Salario Roles +{$amount}]", 'kuros', $nuevo);

                    // Nombre escapado a mano: el log se guarda y se pinta como HTML de verdad
                    // (ver el mismo criterio en la rama "lote" de arriba y en log_entregas.php).
                    $nombre_esc = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8');
                    $log_texto = "[Salario Kuros Roles UID] +{$amount} kuros entregados a {$nombre_esc} (<a href='/op/personaje.php?uid={$uid_pagar}'>{$uid_pagar}</a>) por {$username} ({$uid}) a las: " . date('Y-m-d H:i:s') . '.';
                    $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                        . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Salario Roles UID', '" . $db->escape_string($log_texto) . "')");
                    $ok = "+{$amount} kuros entregados a " . $nombre_esc . '.';
                }
            }
        }
    } else {
        $error = 'Acción desconocida.';
    }

    my_setcookie(SALARIO_STAFF_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $ok,
    ))), 15, true);
    header('Location: salario_staff.php');
    exit;
}

// ── GET: panel + historial ───────────────────────────────────────────────────

$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[SALARIO_STAFF_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[SALARIO_STAFF_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(SALARIO_STAFF_MSG_COOKIE);
}

$salarios_kuros = '';
$query_salarios = $db->query("
    SELECT * FROM `mybb_op_audit_consola_mod`
    WHERE log LIKE '%[Salario Kuros Roles]%' OR log LIKE '%[Salario Kuros Roles UID]%'
    ORDER BY tiempo DESC LIMIT 30
");
while ($q = $db->fetch_array($query_salarios)) {
    // El campo log trae HTML intencional (el link a la ficha) armado más arriba, con el
    // nombre del personaje ya escapado ahí mismo — no se vuelve a escapar todo el campo
    // acá porque eso convertiría los links reales en texto (mismo criterio que
    // log_entregas.php). Los registros viejos, de antes de este comentario, se guardaron
    // con el mismo patrón (link + nombre sin escapar del todo) por el código original.
    $salarios_kuros .= '<div class="ss-fila">' . $q['log'] . '</div>';
}
if ($salarios_kuros === '') {
    $salarios_kuros = '<p class="opg-vacio">Todavía no se pagó ningún salario de staff.</p>';
}

$q_roles_list = $db->query("
    SELECT u.uid, u.usergroup, u.additionalgroups, u.username, f.nombre
    FROM mybb_users u INNER JOIN mybb_op_fichas f ON f.fid = u.uid
    WHERE u.usergroup IN (3,4,6,14,15,16)
       OR FIND_IN_SET('3',  u.additionalgroups) > 0
       OR FIND_IN_SET('4',  u.additionalgroups) > 0
       OR FIND_IN_SET('6',  u.additionalgroups) > 0
       OR FIND_IN_SET('14', u.additionalgroups) > 0
       OR FIND_IN_SET('15', u.additionalgroups) > 0
       OR FIND_IN_SET('16', u.additionalgroups) > 0
    ORDER BY f.nombre ASC
");

$checkboxes_staff = '';
while ($row = $db->fetch_array($q_roles_list)) {
    $amount = salario_staff_monto($row, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS);
    if (!$amount) { continue; }
    $fid = (int) $row['uid'];
    $nombre = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8');
    $checkboxes_staff .= '<label class="ss-item">'
        . '<input type="checkbox" name="uids_seleccionados[]" value="' . $fid . '" class="chk-staff-rol">'
        . '<span>' . $nombre . ' (UID ' . $fid . ')</span>'
        . '<span class="ss-monto">+' . $amount . ' kuros</span>'
        . '</label>';
}
if ($checkboxes_staff === '') {
    $checkboxes_staff = '<p class="opg-vacio">No hay miembros con rol elegible para salario.</p>';
}

eval("\$page = \"".$templates->get("staff_salario_staff")."\";");
output_page($page);
