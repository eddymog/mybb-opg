<?php
/**
 * Staff - Repartir cofres a personajes con actividad reciente de rol
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
require_once MYBB_ROOT . "inc/plugins/lib/cofres_avisos.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if ($uid !== 10) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('COFRES_REPARTIR_MSG_COOKIE', 'cofres_repartir_msg');

$umbral_inactividad = strtotime('-1 month');
$zona_rol_fid = 10;

function cofres_repartir_tablas_listas()
{
    global $db;
    return $db->table_exists('op_cofres_repartos')
        && $db->table_exists('op_cofres_repartos_detalle')
        && $db->table_exists('op_cofres_repartos_reversiones')
        && $db->table_exists('op_cofres_repartos_reversiones_detalle')
        && $db->table_exists('op_cofres_repartos_reversiones_incidencias');
}

/** Personajes con al menos un post en la zona de rol durante el ultimo mes. */
function cofres_repartir_destinatarios($umbral, $zonaRolFid)
{
    global $db;
    $umbral = (int) $umbral;
    $zonaRolFid = (int) $zonaRolFid;
    $destinatarios = array();
    $query = $db->query("
        SELECT f.fid, f.nombre, u.username
        FROM mybb_op_fichas f
        LEFT JOIN mybb_users u ON u.uid=f.fid
        WHERE EXISTS (
            SELECT 1 FROM mybb_posts p
            INNER JOIN mybb_threads t ON t.tid=p.tid
            INNER JOIN mybb_forums fo ON fo.fid=t.fid
            WHERE p.uid=f.fid
              AND p.dateline >= {$umbral}
              AND (fo.fid={$zonaRolFid} OR FIND_IN_SET({$zonaRolFid}, fo.parentlist))
        )
        ORDER BY f.nombre, f.fid
    ");
    while ($row = $db->fetch_array($query)) {
        $destinatarios[] = $row;
    }
    return $destinatarios;
}

function cofres_repartir_token()
{
    return function_exists('random_bytes')
        ? bin2hex(random_bytes(16))
        : sha1(uniqid('', true) . mt_rand());
}

// ── POST: repartir ────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $action = $mybb->get_input('action', MyBB::INPUT_STRING);
    if (!cofres_repartir_tablas_listas()) {
        $msg_tipo = 'err';
        $msg_texto = 'Falta ejecutar cofres_repartos_migration.sql.';
    } elseif ($action === 'reverse') {
        $reparto_id = $mybb->get_input('reparto_id', MyBB::INPUT_INT);
        $motivo = trim($mybb->get_input('motivo', MyBB::INPUT_STRING));
        $safe_username = $db->escape_string($username);
        $safe_motivo = $db->escape_string(substr($motivo, 0, 255));
        $created_at = gmdate('Y-m-d H:i:s');
        $db->write_query('START TRANSACTION');
        $reparto = $db->fetch_array($db->query("SELECT * FROM mybb_op_cofres_repartos WHERE id='{$reparto_id}' FOR UPDATE"));
        $ya_revertido = $db->fetch_array($db->query("SELECT id FROM mybb_op_cofres_repartos_reversiones WHERE reparto_id='{$reparto_id}' LIMIT 1"));
        if (!$reparto || $motivo === '') {
            $db->write_query('ROLLBACK');
            $msg_tipo = 'err';
            $msg_texto = 'El reparto o el motivo de la anulación no es válido.';
        } elseif ($ya_revertido) {
            $db->write_query('ROLLBACK');
            $msg_tipo = 'err';
            $msg_texto = 'Este reparto ya había sido anulado. No se retiró nada otra vez.';
        } else {
            $safe_cofre = $db->escape_string($reparto['objeto_id']);
            $reparto_timestamp = strtotime($reparto['created_at'] . ' UTC');
            $detalles = array();
            $detalle_query = $db->query("SELECT * FROM mybb_op_cofres_repartos_detalle WHERE reparto_id='{$reparto_id}' ORDER BY id FOR UPDATE");
            while ($detalle = $db->fetch_array($detalle_query)) {
                $fid = (int) $detalle['uid'];
                $inventario = $db->fetch_array($db->query("SELECT id,cantidad FROM mybb_op_inventario WHERE uid='{$fid}' AND objeto_id='{$safe_cofre}' FOR UPDATE"));
                $actual = $inventario ? (int) $inventario['cantidad'] : 0;
                $aperturas = (int) $db->fetch_field($db->query("SELECT COUNT(*) AS total FROM mybb_op_tirada_cofre
                    WHERE uid='{$fid}' AND tier='{$safe_cofre}' AND timestamp>='" . (int) $reparto_timestamp . "'"), 'total');
                $detalle['inventario_id'] = $inventario ? (int) $inventario['id'] : 0;
                $detalle['cantidad_actual'] = $actual;
                $detalle['aperturas_posteriores'] = $aperturas;
                $detalles[] = $detalle;
            }

            $db->query("INSERT INTO mybb_op_cofres_repartos_reversiones
                (reparto_id,actor_uid,actor_username,motivo,created_at)
                VALUES ('{$reparto_id}','{$uid}','{$safe_username}','{$safe_motivo}','{$created_at}')");
            $reversion_id = (int) $db->insert_id();
            $retirados_completos = 0;
            $casos_manuales = 0;
            $uids_con_retirada = array();
            foreach ($detalles as $detalle) {
                $fid = (int) $detalle['uid'];
                $actual = (int) $detalle['cantidad_actual'];
                $stock_previo = (int) $detalle['cantidad_anterior'];
                $entregada = (int) $detalle['cantidad_entregada'];
                $aperturas = (int) $detalle['aperturas_posteriores'];

                // Las aperturas se atribuyen primero al lote y nunca se toca el stock previo.
                $entrega_sin_abrir = max(0, $entregada - min($entregada, $aperturas));
                $stock_atribuible_al_lote = max(0, $actual - $stock_previo);
                $retirada = min($entrega_sin_abrir, $stock_atribuible_al_lote);
                $pendiente = $entregada - $retirada;
                $final = $actual - $retirada;

                if ($retirada > 0 && (int) $detalle['inventario_id'] > 0) {
                    $db->query("UPDATE mybb_op_inventario SET cantidad='{$final}' WHERE id='" . (int) $detalle['inventario_id'] . "' AND uid='{$fid}'");
                    $uids_con_retirada[] = $fid;
                }
                $db->query("INSERT INTO mybb_op_cofres_repartos_reversiones_detalle
                    (reversion_id,reparto_id,uid,cantidad_anterior,cantidad_retirada,cantidad_final,created_at)
                    VALUES ('{$reversion_id}','{$reparto_id}','{$fid}','{$actual}','{$retirada}','{$final}','{$created_at}')");

                if ($pendiente > 0 || $aperturas > 0) {
                    $razones = array();
                    if ($aperturas > 0) {
                        $razones[] = 'Aperturas posteriores detectadas: ' . $aperturas;
                    }
                    if ($pendiente > $aperturas) {
                        $razones[] = 'No conserva suficiente stock atribuible al lote';
                    }
                    $safe_incidencia = $db->escape_string(implode('. ', $razones));
                    $db->query("INSERT INTO mybb_op_cofres_repartos_reversiones_incidencias
                        (reversion_id,reparto_id,uid,aperturas_detectadas,cantidad_no_retirada,descripcion,created_at)
                        VALUES ('{$reversion_id}','{$reparto_id}','{$fid}','{$aperturas}','{$pendiente}','{$safe_incidencia}','{$created_at}')");
                    $casos_manuales++;
                } else {
                    $retirados_completos++;
                }
            }
            $db->write_query('COMMIT');
            // Aviso fuera de la transaccion, una llamada para todo el lote, solo a quien se le retiro algo (lib/cofres_avisos.php).
            opg_cof_avisar('cofres_revertidos', $uids_con_retirada, $reparto_id, $reparto['objeto_id'], $reparto['cantidad'], $uid);
            log_audit($uid, $username, '[Cofres]', "Anuló el lote #{$reparto_id} ({$reparto['cantidad']}x {$reparto['objeto_id']}). Retirados por completo: {$retirados_completos}. Casos manuales: {$casos_manuales}. Motivo: {$motivo}");
            $msg_tipo = 'ok';
            $msg_texto = "Lote #{$reparto_id} anulado: {$retirados_completos} personaje(s) resueltos automáticamente y {$casos_manuales} caso(s) pendientes de revisión manual.";
        }
    } else {
        $cofre_id = trim($mybb->get_input('cofre_id', MyBB::INPUT_STRING));
        $cantidad = max(1, min(5, $mybb->get_input('cantidad', MyBB::INPUT_INT)));
        $reparto_key = preg_replace('/[^a-f0-9]/', '', strtolower($mybb->get_input('reparto_key', MyBB::INPUT_STRING)));
        $safe_cofre = $db->escape_string($cofre_id);
        $es_valido = $cofre_id === 'CFF010' || (bool) $db->fetch_field(
            $db->query("SELECT cofre_id FROM `mybb_op_cofres` WHERE cofre_id='{$safe_cofre}' LIMIT 1"), 'cofre_id');

        if ($cofre_id === '' || !$es_valido) {
            $msg_tipo = 'err';
            $msg_texto = 'Cofre no válido.';
        } elseif (strlen($reparto_key) < 32) {
            $msg_tipo = 'err';
            $msg_texto = 'El identificador del reparto no es válido. Recarga la página e inténtalo de nuevo.';
        } else {
            $destinatarios = cofres_repartir_destinatarios($umbral_inactividad, $zona_rol_fid);
            $safe_key = $db->escape_string($reparto_key);
            $safe_username = $db->escape_string($username);
            $created_at = gmdate('Y-m-d H:i:s');
            $db->write_query('START TRANSACTION');
            $db->write_query("INSERT IGNORE INTO mybb_op_cofres_repartos
                (reparto_key,objeto_id,cantidad,umbral_dateline,total_destinatarios,actor_uid,actor_username,created_at)
                VALUES ('{$safe_key}','{$safe_cofre}','{$cantidad}','" . (int)$umbral_inactividad . "','0','{$uid}','{$safe_username}','{$created_at}')");

            if ($db->affected_rows() === 0) {
                $db->write_query('ROLLBACK');
                $msg_tipo = 'err';
                $msg_texto = 'Este reparto ya fue procesado. No se entregó ningún cofre adicional.';
            } else {
                $reparto_id = (int) $db->insert_id();
                $total = 0;
                $uids_entregados = array();
                foreach ($destinatarios as $f) {
                    $fid = (int) $f['fid'];
                    $row = $db->fetch_array($db->query("SELECT cantidad FROM mybb_op_inventario WHERE uid='{$fid}' AND objeto_id='{$safe_cofre}' FOR UPDATE"));
                    $anterior = $row ? (int) $row['cantidad'] : 0;
                    $final = $anterior + $cantidad;
                    if ($row) {
                        $db->query("UPDATE mybb_op_inventario SET cantidad='{$final}' WHERE uid='{$fid}' AND objeto_id='{$safe_cofre}'");
                    } else {
                        $db->query("INSERT INTO mybb_op_inventario (objeto_id,uid,cantidad) VALUES ('{$safe_cofre}','{$fid}','{$cantidad}')");
                    }
                    $safe_nombre = $db->escape_string((string) $f['nombre']);
                    $safe_recipient_username = $db->escape_string((string) $f['username']);
                    $db->query("INSERT INTO mybb_op_cofres_repartos_detalle
                        (reparto_id,uid,nombre,username,cantidad_anterior,cantidad_entregada,cantidad_final,created_at)
                        VALUES ('{$reparto_id}','{$fid}','{$safe_nombre}','{$safe_recipient_username}','{$anterior}','{$cantidad}','{$final}','{$created_at}')");
                    $uids_entregados[] = $fid;
                    $total++;
                }
                $db->query("UPDATE mybb_op_cofres_repartos SET total_destinatarios='{$total}' WHERE id='{$reparto_id}'");
                $db->write_query('COMMIT');
                // Aviso fuera de la transaccion: una llamada con todo el lote, un aviso por destinatario (lib/cofres_avisos.php).
                opg_cof_avisar('cofres_repartidos', $uids_entregados, $reparto_id, $cofre_id, $cantidad, $uid);
                log_audit($uid, $username, '[Cofres]', "Repartió {$cantidad}x {$safe_cofre} a {$total} fichas activas en zona de rol. Lote #{$reparto_id}.");
                $msg_tipo = 'ok';
                $msg_texto = "Lote #{$reparto_id}: se repartió {$cantidad}x {$cofre_id} a {$total} personaje(s) con actividad de rol durante el último mes.";
            }
        }
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
$reparto_key = cofres_repartir_token();

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

$destinatarios_actuales = cofres_repartir_destinatarios($umbral_inactividad, $zona_rol_fid);
$total_fichas = count($destinatarios_actuales);
$total_fichas_esc = htmlspecialchars((string) $total_fichas, ENT_QUOTES, 'UTF-8');
$cofres_disponibles_esc = htmlspecialchars((string) $cofres_disponibles, ENT_QUOTES, 'UTF-8');
$reparto_key_esc = htmlspecialchars($reparto_key, ENT_QUOTES, 'UTF-8');

$historial_html = '';
if (cofres_repartir_tablas_listas()) {
    $query = $db->query("SELECT r.*,rv.id AS reversion_id,rv.actor_username AS reversion_actor,
        rv.motivo AS reversion_motivo,rv.created_at AS reversion_fecha,
        (SELECT COUNT(*) FROM mybb_op_cofres_repartos_reversiones_incidencias ri WHERE ri.reparto_id=r.id) AS incidencias
        FROM mybb_op_cofres_repartos r
        LEFT JOIN mybb_op_cofres_repartos_reversiones rv ON rv.reparto_id=r.id
        ORDER BY r.id DESC LIMIT 20");
    while ($reparto = $db->fetch_array($query)) {
        $reparto_id = (int) $reparto['id'];
        $reparto_timestamp = strtotime($reparto['created_at'] . ' UTC');
        $safe_historial_cofre = $db->escape_string($reparto['objeto_id']);
        $detalles = '';
        $detalle_query = $db->query("SELECT d.*,rd.cantidad_retirada,ri.cantidad_no_retirada,ri.aperturas_detectadas,
            (SELECT COUNT(*) FROM mybb_op_tirada_cofre t
             WHERE t.uid=d.uid AND t.tier='{$safe_historial_cofre}' AND t.timestamp>='" . (int) $reparto_timestamp . "') AS aperturas_posteriores
            FROM mybb_op_cofres_repartos_detalle d
            LEFT JOIN mybb_op_cofres_repartos_reversiones_detalle rd ON rd.reparto_id=d.reparto_id AND rd.uid=d.uid
            LEFT JOIN mybb_op_cofres_repartos_reversiones_incidencias ri ON ri.reparto_id=d.reparto_id AND ri.uid=d.uid
            WHERE d.reparto_id='{$reparto_id}' ORDER BY d.nombre,d.uid");
        while ($detalle = $db->fetch_array($detalle_query)) {
            $nombre = $detalle['nombre'] !== '' ? $detalle['nombre'] : $detalle['username'];
            $aperturas = (int) $reparto['reversion_id'] > 0
                ? (int) $detalle['aperturas_detectadas']
                : (int) $detalle['aperturas_posteriores'];
            $retirada = $detalle['cantidad_retirada'] === null ? '—' : (int) $detalle['cantidad_retirada'];
            $pendiente = $detalle['cantidad_no_retirada'] === null ? 0 : (int) $detalle['cantidad_no_retirada'];
            $detalles .= '<tr class="' . ($pendiente > 0 ? 'cf-reparto__fila-pendiente' : '') . '"><td>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>#' . (int) $detalle['uid'] . '</td><td>' . (int) $detalle['cantidad_anterior'] . '</td>'
                . '<td>+' . (int) $detalle['cantidad_entregada'] . '</td><td>' . (int) $detalle['cantidad_final'] . '</td>'
                . '<td class="' . ($aperturas > 0 ? 'cf-reparto__abierto' : '') . '">' . $aperturas . '</td>'
                . '<td>' . $retirada . '</td><td class="' . ($pendiente > 0 ? 'cf-reparto__pendiente' : '') . '">' . ($pendiente > 0 ? $pendiente : '—') . '</td></tr>';
        }
        $estado = (int) $reparto['reversion_id'] > 0
            ? '<span class="cf-reparto__estado cf-reparto__estado--anulado">' . ((int) $reparto['incidencias'] > 0 ? 'Anulado con pendientes' : 'Anulado') . '</span>'
            : '<span class="cf-reparto__estado">Entregado</span>';
        $reversion_html = '';
        if ((int) $reparto['reversion_id'] > 0) {
            $reversion_html = '<div class="cf-reparto__reversion"><strong>Anulado por '
                . htmlspecialchars($reparto['reversion_actor'], ENT_QUOTES, 'UTF-8') . '</strong> · '
                . htmlspecialchars($reparto['reversion_fecha'], ENT_QUOTES, 'UTF-8') . '<br>'
                . htmlspecialchars($reparto['reversion_motivo'], ENT_QUOTES, 'UTF-8')
                . ((int) $reparto['incidencias'] > 0 ? '<br><strong>' . (int) $reparto['incidencias'] . ' caso(s) requieren revisión manual.</strong>' : '') . '</div>';
        } else {
            $reversion_html = '<form method="post" class="cf-reparto__anular" onsubmit="return confirm(\'¿Anular el lote #' . $reparto_id . ' y retirar estos cofres a todos sus destinatarios?\');">'
                . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($post_key, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="action" value="reverse"><input type="hidden" name="reparto_id" value="' . $reparto_id . '">'
                . '<label>Motivo de la anulación<input type="text" name="motivo" maxlength="255" required></label>'
                . '<button type="submit" class="btn-op btn-op--peligro">Anular reparto</button></form>';
        }
        $historial_html .= '<details class="cf-reparto"><summary><span><strong>Lote #' . $reparto_id . '</strong> '
            . htmlspecialchars($reparto['objeto_id'], ENT_QUOTES, 'UTF-8') . ' x' . (int) $reparto['cantidad'] . '</span>'
            . '<span>' . $estado . ' ' . (int) $reparto['total_destinatarios'] . ' destinatarios · ' . htmlspecialchars($reparto['created_at'], ENT_QUOTES, 'UTF-8') . '</span></summary>'
            . '<div class="cf-reparto__meta">Entregado por ' . htmlspecialchars($reparto['actor_username'], ENT_QUOTES, 'UTF-8')
            . ' (UID ' . (int) $reparto['actor_uid'] . ')</div>' . $reversion_html
            . '<div class="cf-reparto__tabla"><table><thead><tr><th>Personaje</th><th>UID</th>'
            . '<th>Antes</th><th>Entrega</th><th>Después</th><th>Aperturas posteriores</th><th>Retirado</th><th>Pendiente manual</th></tr></thead><tbody>' . $detalles . '</tbody></table></div></details>';
    }
}
if ($historial_html === '') {
    $historial_html = '<p class="opg-vacio">Todavía no hay repartos registrados.</p>';
}
$tablas_aviso = cofres_repartir_tablas_listas() ? ''
    : '<p class="aviso err">Ejecuta <code>cofres_repartos_migration.sql</code> antes de realizar el primer reparto.</p>';
$repartir_disabled = cofres_repartir_tablas_listas() ? '' : ' disabled';

eval("\$page = \"".$templates->get("staff_cofres_repartir")."\";");
output_page($page);
