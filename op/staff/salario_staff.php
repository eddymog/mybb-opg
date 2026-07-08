<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'salario_staff.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb;
$uid         = $mybb->user['uid'];
$accion_post = $_POST["accion_post"];
$username    = $mybb->user['username'];
$reload_js   = "<script>window.location.href = window.location.pathname;</script>";

$admin_additional = array_map('trim', explode(',', $mybb->user['additionalgroups']));
$is_admin = ($mybb->user['usergroup'] == 4 || in_array('4', $admin_additional));

// Devuelve el grupo de pago (admin/staff/narra) y el importe correspondiente para un usuario
function op_salario_calcular_monto($row, $admin_groups, $staff_groups, $narra_groups) {
    $groups = array_map('trim', explode(',', $row['additionalgroups']));
    $groups[] = (string)(int)$row['usergroup'];

    if (array_intersect($admin_groups, $groups)) {
        return 500;
    } elseif (array_intersect($staff_groups, $groups)) {
        return 400;
    } elseif (array_intersect($narra_groups, $groups)) {
        return 200;
    }
    return 0;
}

if ($accion_post === 'pagar_salario_roles' && ($is_admin)) {

    $admin_groups = ['4'];
    $staff_groups = ['3', '6', '14', '16'];
    $narra_groups = ['15'];

    $uids_seleccionados = $mybb->get_input('uids_seleccionados', MyBB::INPUT_ARRAY);
    $uids_seleccionados = array_filter(array_map('intval', $uids_seleccionados));

    if (empty($uids_seleccionados)) {
        eval('$log_var = "No se seleccionó a ningún miembro del staff.";');
    } else {
        $uids_in = implode(',', $uids_seleccionados);

        $q_roles = $db->query("
            SELECT u.uid, u.usergroup, u.additionalgroups, f.kuro, f.nombre
            FROM mybb_users u
            INNER JOIN mybb_op_fichas f ON f.fid = u.uid
            WHERE u.uid IN ({$uids_in})
        ");

        $nombres_id = '';
        while ($row = $db->fetch_array($q_roles)) {
            $fid = (int)$row['uid'];
            $amount = op_salario_calcular_monto($row, $admin_groups, $staff_groups, $narra_groups);

            if (!$amount) {
                continue;
            }

            $new_kuro = intval($row['kuro']) + $amount;
            $nombre   = $row['nombre'];

            $db->query("UPDATE mybb_op_fichas SET kuro = kuro + $amount WHERE fid = '$fid'");
            log_audit_currency($uid, $username, $fid, "[Kuros][Salario Roles +{$amount}]", 'kuros', $new_kuro);

            $nombres_id .= $nombre . " (<a href='/op/personaje.php?uid=$fid'>$fid</a>) +{$amount}, ";
        }

        $nombres_id = rtrim($nombres_id, ', ');
        $fechaHora  = date('Y-m-d H:i:s');
        $log        = "[Salario Kuros Roles] Kuros por rol entregados por $username ($uid) a las: {$fechaHora}</br>Pagos: $nombres_id</br>";

        $db->query("
            INSERT INTO mybb_op_audit_consola_mod (staff, username, razon, log) VALUES
            ('$uid', '$username', 'Salario Roles', '" . $db->escape_string($log) . "')
        ");

        eval('$log_var = $log;');
        eval('$reload_script = $reload_js;');
    }
}

if ($accion_post === 'pagar_salario_uid_rol' && ($is_admin)) {

    $admin_groups = ['4'];
    $staff_groups = ['3', '6', '14', '16'];
    $narra_groups = ['15'];
    $custom_kuros = (int)$mybb->get_input('custom_kuros');

    $uid_pagar = (int)$mybb->get_input('uid_pagar');
    if (!$uid_pagar) {
        eval('$log_var = "UID inválida.";');
    } else {
        $q_user = $db->query("
            SELECT u.uid, u.usergroup, u.additionalgroups, f.kuro, f.nombre
            FROM mybb_users u
            INNER JOIN mybb_op_fichas f ON f.fid = u.uid
            WHERE u.uid = '$uid_pagar'
            LIMIT 1
        ");
        $row = $db->fetch_array($q_user);

        if (!$row) {
            eval('$log_var = "No se encontró ficha para esa UID.";');
        } else {
            $groups = array_map('trim', explode(',', $row['additionalgroups']));
            $groups[] = (string)(int)$row['usergroup'];

            if ($custom_kuros > 0) {
                $amount = $custom_kuros;
            } elseif (array_intersect($admin_groups, $groups)) {
                $amount = 500;
            } elseif (array_intersect($staff_groups, $groups)) {
                $amount = 400;
            } elseif (array_intersect($narra_groups, $groups)) {
                $amount = 200;
            } else {
                $amount = 0;
            }

            if (!$amount) {
                eval('$log_var = "El usuario no pertenece a ningún grupo de staff.";');
            } else {
                $new_kuro = intval($row['kuro']) + $amount;
                $nombre   = $row['nombre'];

                $db->query("UPDATE mybb_op_fichas SET kuro = kuro + $amount WHERE fid = '$uid_pagar'");
                log_audit_currency($uid, $username, $uid_pagar, "[Kuros][Salario Roles +{$amount}]", 'kuros', $new_kuro);

                $fechaHora = date('Y-m-d H:i:s');
                $log       = "[Salario Kuros Roles UID] +{$amount} kuros entregados a $nombre (UID: $uid_pagar) por $username ($uid) a las: {$fechaHora}</br>";

                $db->query("
                    INSERT INTO mybb_op_audit_consola_mod (staff, username, razon, log) VALUES
                    ('$uid', '$username', 'Salario Roles UID', '" . $db->escape_string($log) . "')
                ");

                eval('$log_var = $log;');
                eval('$reload_script = $reload_js;');
            }
        }
    }
}

if ($is_admin) {

    $salarios_kuros = '';

    $query_salarios = $db->query("
        SELECT * FROM mybb_op_audit_consola_mod
        WHERE log LIKE '%[Salario Kuros Roles]%' OR log LIKE '%[Salario Kuros Roles UID]%'
        ORDER BY tiempo DESC LIMIT 30
    ");
    while ($q = $db->fetch_array($query_salarios)) {
        $salarios_kuros .= $q['log'] . "</br>";
    }

    // Listado de miembros con rol elegible para checkboxes
    $admin_groups = ['4'];
    $staff_groups = ['3', '6', '14', '16'];
    $narra_groups = ['15'];

    $q_roles_list = $db->query("
        SELECT u.uid, u.usergroup, u.additionalgroups, u.username, f.nombre
        FROM mybb_users u
        INNER JOIN mybb_op_fichas f ON f.fid = u.uid
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
        $amount = op_salario_calcular_monto($row, $admin_groups, $staff_groups, $narra_groups);
        if (!$amount) {
            continue;
        }
        $fid = (int)$row['uid'];
        $nombre = htmlspecialchars_uni($row['nombre']);
        $checkboxes_staff .= "
            <label style='display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px solid #eee;'>
                <input type='checkbox' name='uids_seleccionados[]' value='{$fid}' class='chk-staff-rol'>
                {$nombre} (UID: {$fid}) — <strong>+{$amount} kuros</strong>
            </label>";
    }

    if ($checkboxes_staff === '') {
        $checkboxes_staff = "<p>No hay miembros con rol elegible para salario.</p>";
    }

    eval("\$page = \"".$templates->get("staff_salario_staff")."\";");
    output_page($page);
} else {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
}
