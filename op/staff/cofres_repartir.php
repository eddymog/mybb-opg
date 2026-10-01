<?php
/**
 * Staff - Repartir cofre a todos los usuarios
 *
 * Traslado de `action=dar_cofre_masivo` (antes dentro de opg/tirada_cofre.php,
 * restringido a dos UID hardcodeados) a una herramienta propia de la consola
 * de staff, con `is_admin()` real. Ver docs/100_Requirements_Cofres.md (5.7)
 * y docs/200_DesignPlan_Cofres.md (6).
 *
 * La lista de cofres válidos sale de `mybb_op_cofres` (más CFF010, el cofre
 * bromista — es un caso especial fuera de esa tabla, ver
 * docs/200_DesignPlan_Cofres.md §4.1), no de una lista fija en el código.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'cofres_repartir.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if ($uid !== 10) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('COFRES_REPARTIR_MSG_COOKIE', 'cofres_repartir_msg');

// ── POST: repartir ────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $cofre_id = trim($mybb->get_input('cofre_id', MyBB::INPUT_STRING));
    $safe_cofre = $db->escape_string($cofre_id);

    $es_valido = $cofre_id === 'CFF010' || (bool) $db->fetch_field(
        $db->query("SELECT cofre_id FROM `mybb_op_cofres` WHERE cofre_id='{$safe_cofre}' LIMIT 1"),
        'cofre_id'
    );

    if ($cofre_id === '' || !$es_valido) {
        $msg_tipo = 'err';
        $msg_texto = 'Cofre no válido.';
    } else {
        $total = 0;
        $umbral_inactividad = strtotime('-2 months');
        $zona_rol_fid = 10;
        // "Activo" = tiene al menos un post dentro de la zona de rol (fid=10
        // y sus subforos) en los últimos 2 meses — no lastpost global (cuenta
        // posts en cualquier foro, incluido fuera de rol) ni lastvisit (ni
        // siquiera participación). parentlist de un subforo incluye el fid
        // de todos sus ancestros (admin/inc/functions.php::make_parent_list()),
        // así que FIND_IN_SET cubre cualquier nivel de anidado debajo de 10.
        $query_fichas = $db->query("
            SELECT f.fid FROM mybb_op_fichas f
            WHERE EXISTS (
                SELECT 1 FROM mybb_posts p
                INNER JOIN mybb_threads t ON t.tid = p.tid
                INNER JOIN mybb_forums fo ON fo.fid = t.fid
                WHERE p.uid = f.fid
                  AND p.dateline >= {$umbral_inactividad}
                  AND (fo.fid = {$zona_rol_fid} OR FIND_IN_SET({$zona_rol_fid}, fo.parentlist))
            )
        ");
        while ($f = $db->fetch_array($query_fichas)) {
            $fid = (int) $f['fid'];
            $inv = $db->query("SELECT cantidad FROM mybb_op_inventario WHERE uid='{$fid}' AND objeto_id='{$safe_cofre}'");
            $row = $db->fetch_array($inv);
            if ($row) {
                $nueva_cantidad = (int) $row['cantidad'] + 1;
                $db->query("UPDATE mybb_op_inventario SET cantidad='{$nueva_cantidad}' WHERE uid='{$fid}' AND objeto_id='{$safe_cofre}'");
            } else {
                $db->query("INSERT INTO mybb_op_inventario (objeto_id, uid, cantidad) VALUES ('{$safe_cofre}', '{$fid}', '1')");
            }
            $total++;
        }

        log_audit($uid, $username, '[Cofres]', "Repartió {$safe_cofre} a {$total} fichas activas en zona de rol (posteo en los últimos 2 meses).");
        $msg_tipo = 'ok';
        $msg_texto = "Se repartió a {$total} ficha(s) activa(s) en zona de rol (con posteo en los últimos 2 meses).";
    }

    my_setcookie(COFRES_REPARTIR_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $msg_tipo,
        'texto' => $msg_texto,
    ))), 15, true);
    header('Location: cofres_repartir.php');
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[COFRES_REPARTIR_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[COFRES_REPARTIR_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(COFRES_REPARTIR_MSG_COOKIE);
}

$query = $db->query("
    SELECT DISTINCT o.cofre_id, obj.nombre AS obj_nombre
    FROM `mybb_op_cofres` o
    LEFT JOIN `mybb_op_objetos` obj ON obj.objeto_id COLLATE utf8_general_ci = o.cofre_id
    ORDER BY obj.nombre
");
$opciones_html = '';
$cofres_disponibles = 0;
while ($fila = $db->fetch_array($query)) {
    $nombre = $fila['obj_nombre'] !== null ? $fila['obj_nombre'] : $fila['cofre_id'];
    $opciones_html .= '<option value="' . htmlspecialchars($fila['cofre_id'], ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($fila['cofre_id'], ENT_QUOTES, 'UTF-8') . ')</option>';
    $cofres_disponibles++;
}
// CFF010 (cofre bromista): caso especial fuera de mybb_op_cofres, se agrega a mano.
$opciones_html .= '<option value="CFF010">Cofre Bromista (CFF010)</option>';
$cofres_disponibles++;

$total_fichas = (int) $db->fetch_field($db->query("SELECT COUNT(*) AS total FROM mybb_op_fichas"), 'total');
$total_fichas_esc = htmlspecialchars((string) $total_fichas, ENT_QUOTES, 'UTF-8');
$cofres_disponibles_esc = htmlspecialchars((string) $cofres_disponibles, ENT_QUOTES, 'UTF-8');

eval("\$page = \"".$templates->get("staff_cofres_repartir")."\";");
output_page($page);
