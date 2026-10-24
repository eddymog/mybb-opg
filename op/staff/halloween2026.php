<?php
/** Consola de staff para Halloween 2026. */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'halloween2026.php');
require_once './../../global.php';
require_once './../functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_store.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_stats.php';

global $mybb, $db, $templates, $header, $headerinclude, $footer;
$staffUid = (int)$mybb->user['uid'];
if ($staffUid <= 0 || !is_staff($staffUid)) {
    error_no_permission();
}
if (!opg_hlw_tables_ready()) {
    error('Las tablas de Halloween 2026 no estan instaladas.');
}

function opg_hlw_staff_url($params = array())
{
    return '/op/staff/halloween2026.php' . ($params ? '?' . http_build_query($params, '', '&') : '');
}

function opg_hlw_staff_message($type, $text)
{
    return '<div class="hlw-alert hlw-alert--' . opg_hlw_escape($type) . '">' . opg_hlw_escape($text) . '</div>';
}

function opg_hlw_staff_presets()
{
    return array(
        'historias' => array('label' => 'Concurso de Historias de Miedo', 'amount' => 10),
        'monstruos' => array('label' => 'Concurso de Monstruos Salvajes', 'amount' => 10),
        'aventura-spooky' => array('label' => 'Aventura Spooky completada', 'amount' => 5),
        'avatar-foro' => array('label' => 'Avatar tematico en el foro', 'amount' => 5),
        'avatar-discord' => array('label' => 'Avatar tematico en Discord', 'amount' => 5),
    );
}

function opg_hlw_staff_target($uid)
{
    global $db;
    $uid = (int)$uid;
    if ($uid <= 0) return null;
    $query = $db->simple_select('op_fichas', 'fid,nombre,apodo,aprobada_por,muerto', "fid='{$uid}'", array('limit' => 1));
    return $db->fetch_array($query) ?: null;
}

function opg_hlw_staff_preflight($uid)
{
    global $db;
    $uid = (int)$uid;
    $config = opg_hlw_config(true);
    if (($config['status'] ?? '') !== 'draft') {
        return array('ok' => false, 'error' => 'La prueba previa solo esta disponible mientras el evento esta en draft.');
    }
    if (!opg_hlw_store_tables_ready()) {
        return array('ok' => false, 'error' => 'Faltan tablas del evento o de la tienda.');
    }

    $before = opg_hlw_balance($uid);
    $token = opg_hlw_purchase_token();
    $keyPrefix = 'halloween2026:preflight:' . $uid . ':' . $token;
    $normalReward = opg_hlw_thread_reward(
        (int)($config['settings']['common_prefix_id'] ?? 1),
        $config
    );
    $spookyReward = opg_hlw_thread_reward(
        (int)($config['settings']['spooky_prefix_id'] ?? 15),
        $config
    );
    $scenarios = array(
        array('type' => 'post', 'amount' => 1, 'label' => 'Post de rol'),
        array('type' => $normalReward['type'], 'amount' => (int)$normalReward['amount'], 'label' => 'Tema Comun o Aventura'),
        array('type' => $spookyReward['type'], 'amount' => (int)$spookyReward['amount'], 'label' => 'Tema Spooky'),
        array('type' => 'daily', 'amount' => 1, 'label' => 'Primer post diario'),
    );
    $expectedDelta = array_sum(array_column($scenarios, 'amount'));
    $rolledBack = false;

    $db->write_query('START TRANSACTION');
    try {
        $character = opg_hlw_eligible_character($uid, true);
        if (!$character) {
            throw new RuntimeException('La ficha 315 no esta activa y aprobada.');
        }

        $query = $db->simple_select('op_halloween2026_tienda_items', 'COUNT(*) total', "activo='1'");
        $storeItems = (int)$db->fetch_field($query, 'total');
        if ($storeItems < 4) {
            throw new RuntimeException('El catalogo no contiene los cuatro articulos activos.');
        }
        $query = $db->simple_select('op_objetos', 'objeto_id', "objeto_id='CFRHLW'", array('limit' => 1));
        if ((string)$db->fetch_field($query, 'objeto_id') !== 'CFRHLW') {
            throw new RuntimeException('No se encontro el objeto CFRHLW.');
        }

        foreach ($scenarios as $index => $scenario) {
            if ($scenario['amount'] <= 0) {
                throw new RuntimeException('La configuracion de recompensas no es valida.');
            }
            $movement = opg_hlw_add_movement(array(
                'uid' => $uid,
                'amount' => $scenario['amount'],
                'bucket' => 'earned',
                'source_type' => $scenario['type'],
                'source_id' => 'preflight-' . $index,
                'idempotency_key' => $keyPrefix . ':' . $index,
                'note' => 'Prueba previa: ' . $scenario['label'],
            ), false);
            if (empty($movement['ok']) || !empty($movement['duplicate'])) {
                throw new RuntimeException('Fallo al simular ' . $scenario['label'] . '.');
            }
        }

        $duplicate = opg_hlw_add_movement(array(
            'uid' => $uid,
            'amount' => 1,
            'bucket' => 'earned',
            'source_type' => 'post',
            'source_id' => 'preflight-0',
            'idempotency_key' => $keyPrefix . ':0',
            'note' => 'Comprobacion de idempotencia',
        ), false);
        if (empty($duplicate['ok']) || empty($duplicate['duplicate'])) {
            throw new RuntimeException('La proteccion contra duplicados no respondio correctamente.');
        }

        $during = opg_hlw_balance($uid);
        if ((int)$during['saldo'] !== (int)$before['saldo'] + $expectedDelta) {
            throw new RuntimeException('El saldo simulado no coincide con el esperado.');
        }

        $db->write_query('ROLLBACK');
        $rolledBack = true;
        $after = opg_hlw_balance($uid);
        $prefixSql = $db->escape_string($keyPrefix);
        $query = $db->simple_select('op_halloween2026_movimientos', 'COUNT(*) total',
            "idempotency_key LIKE '{$prefixSql}:%'");
        $remaining = (int)$db->fetch_field($query, 'total');
        if ((int)$after['saldo'] !== (int)$before['saldo'] || $remaining !== 0) {
            return array('ok' => false, 'error' => 'El rollback no dejo el saldo y el historial en su estado original.');
        }

        return array(
            'ok' => true,
            'uid' => $uid,
            'name' => (string)$character['nombre'],
            'store_items' => $storeItems,
            'before' => (int)$before['saldo'],
            'simulated' => (int)$during['saldo'],
            'after' => (int)$after['saldo'],
            'delta' => $expectedDelta,
            'scenarios' => $scenarios,
        );
    } catch (Exception $e) {
        if (!$rolledBack) {
            $db->write_query('ROLLBACK');
        }
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function opg_hlw_staff_start_live_test($uid)
{
    global $db;
    $uid = (int)$uid;
    $config = opg_hlw_config(true);
    if (($config['status'] ?? '') !== 'draft') {
        return array('ok' => false, 'error' => 'La prueba real solo puede iniciarse mientras el evento esta en draft.');
    }
    if (!opg_hlw_eligible_character($uid)) {
        return array('ok' => false, 'error' => 'La ficha 315 no esta activa y aprobada.');
    }
    if (!empty($config['settings']['live_test']['enabled'])) {
        return array('ok' => false, 'error' => 'Ya hay una prueba real activa.');
    }

    $runId = gmdate('YmdHis') . '-' . substr(opg_hlw_purchase_token(), 0, 8);
    $started = opg_hlw_utc_now();
    $expires = gmdate('Y-m-d H:i:s', time() + 3600);
    $table = opg_hlw_table('config');
    $runSql = $db->escape_string($runId);
    $startedSql = $db->escape_string($started);
    $expiresSql = $db->escape_string($expires);
    $db->write_query("UPDATE `{$table}` SET config_json=JSON_SET(COALESCE(config_json,JSON_OBJECT()),
        '$.live_test',JSON_OBJECT('enabled',true,'uid',{$uid},'run_id','{$runSql}',
        'started_at_utc','{$startedSql}','expires_at_utc','{$expiresSql}')),updated_at=UTC_TIMESTAMP() WHERE id=1");
    opg_hlw_config(true);
    return array('ok' => true, 'run_id' => $runId, 'expires_at_utc' => $expires);
}

function opg_hlw_staff_stop_live_test($actorUid)
{
    global $db;
    $config = opg_hlw_config(true);
    $test = $config['settings']['live_test'] ?? array();
    $runId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($test['run_id'] ?? ''));
    if (empty($test['enabled']) || $runId === '') {
        return array('ok' => false, 'error' => 'No hay una prueba real activa.');
    }

    $table = opg_hlw_table('config');
    $db->write_query("UPDATE `{$table}` SET config_json=JSON_SET(COALESCE(config_json,JSON_OBJECT()),
        '$.live_test.enabled',false,'$.live_test.stopped_at_utc',UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=1");
    opg_hlw_config(true);

    $prefix = $db->escape_string('halloween2026:live-test:' . $runId . ':');
    $movements = opg_hlw_table('movimientos');
    $query = $db->write_query("SELECT m.id,m.source_pid,m.source_type FROM `{$movements}` m
        LEFT JOIN `{$movements}` r ON r.reverses_movement_id=m.id
        WHERE m.idempotency_key LIKE '{$prefix}%' AND m.amount>0 AND m.bucket='earned' AND r.id IS NULL
        ORDER BY m.id ASC");
    $movementIds = array();
    $dailyIds = array();
    $pids = array();
    while ($row = $db->fetch_array($query)) {
        $movementIds[] = (int)$row['id'];
        if ($row['source_type'] === 'daily') {
            $dailyIds[] = (int)$row['id'];
        }
        if ((int)$row['source_pid'] > 0) {
            $pids[] = (int)$row['source_pid'];
        }
    }

    $reversed = 0;
    $errors = 0;
    foreach ($movementIds as $movementId) {
        $result = opg_hlw_reverse_movement($movementId, (int)$actorUid, 'Fin de prueba real ' . $runId);
        if (!empty($result['ok'])) {
            $reversed++;
        } else {
            $errors++;
        }
    }
    if ($dailyIds) {
        $daily = opg_hlw_table('bonos_diarios');
        $db->write_query("UPDATE `{$daily}` SET pid=NULL,movement_id=NULL,status='revoked',updated_at=UTC_TIMESTAMP()
            WHERE movement_id IN (" . implode(',', array_unique($dailyIds)) . ')');
    }
    if ($pids) {
        $publications = opg_hlw_table('publicaciones');
        $db->write_query("UPDATE `{$publications}` SET reconcile_status='test_reversed',
            last_reason='live_test_finished',updated_at=UTC_TIMESTAMP()
            WHERE pid IN (" . implode(',', array_unique($pids)) . ')');
    }

    return array('ok' => $errors === 0, 'reversed' => $reversed, 'errors' => $errors, 'run_id' => $runId);
}

$message = '';
$preflightResult = null;
$targetUid = max(0, $mybb->get_input('uid', MyBB::INPUT_INT));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        $message = opg_hlw_staff_message('error', 'La sesion del formulario vencio.');
    } else {
        $action = $mybb->get_input('action');
        $targetUid = max(0, $mybb->get_input('uid', MyBB::INPUT_INT));
        if ($action === 'preflight') {
            $preflightResult = opg_hlw_staff_preflight(315);
            $message = opg_hlw_staff_message(
                !empty($preflightResult['ok']) ? 'success' : 'error',
                !empty($preflightResult['ok'])
                    ? 'Prueba completada. Todos los cambios fueron revertidos con ROLLBACK.'
                    : ($preflightResult['error'] ?? 'No se pudo completar la prueba previa.')
            );
        } elseif ($action === 'start_live_test') {
            $result = opg_hlw_staff_start_live_test(315);
            $message = opg_hlw_staff_message(!empty($result['ok']) ? 'success' : 'error',
                !empty($result['ok'])
                    ? 'Prueba real activada durante una hora para UID 315. Publica ahora un post nuevo en una zona de rol.'
                    : ($result['error'] ?? 'No se pudo iniciar la prueba real.'));
        } elseif ($action === 'stop_live_test') {
            $result = opg_hlw_staff_stop_live_test($staffUid);
            $message = opg_hlw_staff_message(!empty($result['ok']) ? 'success' : 'error',
                !empty($result['ok'])
                    ? 'Prueba finalizada. Se registraron ' . (int)$result['reversed'] . ' contramovimientos y el saldo neto fue restaurado.'
                    : ($result['error'] ?? ('La prueba se desactivo, pero fallaron ' . (int)($result['errors'] ?? 0) . ' reversiones.')));
        } elseif ($action === 'preset') {
            $presets = opg_hlw_staff_presets();
            $presetKey = $mybb->get_input('preset');
            if (!isset($presets[$presetKey]) || !opg_hlw_eligible_character($targetUid)) {
                $message = opg_hlw_staff_message('error', 'El preset o el personaje no es valido.');
            } else {
                $preset = $presets[$presetKey];
                $result = opg_hlw_add_movement(array(
                    'uid' => $targetUid,
                    'amount' => $preset['amount'],
                    'bucket' => 'earned',
                    'source_type' => 'preset',
                    'source_id' => $presetKey,
                    'idempotency_key' => 'halloween2026:preset:' . $presetKey . ':' . $targetUid,
                    'actor_uid' => $staffUid,
                    'note' => $preset['label'],
                ));
                $message = opg_hlw_staff_message(
                    !empty($result['duplicate']) ? 'info' : (!empty($result['ok']) ? 'success' : 'error'),
                    !empty($result['duplicate']) ? 'Este premio ya habia sido concedido al personaje.'
                        : (!empty($result['ok']) ? 'Premio concedido correctamente.' : 'No se pudo conceder el premio.')
                );
            }
        } elseif ($action === 'gift') {
            $amount = max(1, min(10000, $mybb->get_input('amount', MyBB::INPUT_INT)));
            $distribution = preg_replace('/[^a-zA-Z0-9_-]/', '', $mybb->get_input('distribution'));
            if ($distribution === '' || !opg_hlw_eligible_character($targetUid)) {
                $message = opg_hlw_staff_message('error', 'Indica un personaje y un identificador de reparto.');
            } else {
                $result = opg_hlw_add_movement(array(
                    'uid' => $targetUid, 'amount' => $amount, 'bucket' => 'earned',
                    'source_type' => 'gift', 'source_id' => $distribution,
                    'idempotency_key' => 'halloween2026:gift:' . $distribution . ':' . $targetUid,
                    'actor_uid' => $staffUid, 'note' => 'Regalo impredecible de Kuro',
                ));
                $message = opg_hlw_staff_message(!empty($result['ok']) ? 'success' : 'error',
                    !empty($result['duplicate']) ? 'Este reparto ya se aplico al personaje.'
                        : (!empty($result['ok']) ? 'Regalo registrado.' : 'No se pudo registrar el regalo.'));
            }
        } elseif ($action === 'adjust') {
            $amount = $mybb->get_input('amount', MyBB::INPUT_INT);
            $reason = trim($mybb->get_input('reason'));
            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $mybb->get_input('token'));
            if ($amount === 0 || $reason === '' || !opg_hlw_eligible_character($targetUid)) {
                $message = opg_hlw_staff_message('error', 'Indica personaje, cantidad distinta de cero y motivo.');
            } else {
                $result = opg_hlw_add_movement(array(
                    'uid' => $targetUid, 'amount' => $amount, 'bucket' => 'earned',
                    'source_type' => 'manual', 'source_id' => $token,
                    'idempotency_key' => 'halloween2026:manual:' . $staffUid . ':' . $token,
                    'actor_uid' => $staffUid, 'note' => $reason,
                ));
                $message = opg_hlw_staff_message(!empty($result['ok']) ? 'success' : 'error',
                    !empty($result['ok']) ? 'Ajuste registrado.' : 'El ajuste no pudo aplicarse.');
            }
        } elseif ($action === 'reverse') {
            $movementId = $mybb->get_input('movement_id', MyBB::INPUT_INT);
            $reason = trim($mybb->get_input('reason')) ?: 'Reversion manual de staff';
            $result = opg_hlw_reverse_movement($movementId, $staffUid, $reason);
            $message = opg_hlw_staff_message(!empty($result['ok']) ? 'success' : 'error',
                !empty($result['duplicate']) ? 'Ese movimiento ya estaba revertido.'
                    : (!empty($result['ok']) ? 'Contramovimiento registrado.' : 'No se pudo revertir el movimiento.'));
        } elseif ($action === 'open_store') {
            opg_hlw_set_store_enabled(true);
            $message = opg_hlw_staff_message('success', 'La tienda ha quedado abierta.');
        } elseif ($action === 'schedule_event') {
            $currentConfig = opg_hlw_config(true);
            if (!empty($currentConfig['settings']['live_test']['enabled'])) {
                $message = opg_hlw_staff_message('error', 'Finaliza primero la prueba real de UID 315.');
            } else {
                opg_hlw_set_status('active');
                $message = opg_hlw_staff_message('success', 'Las recompensas automaticas estan activadas y respetaran las fechas del evento.');
            }
        }
    }
}

$queryText = trim($mybb->get_input('q'));
$searchResults = array();
if ($queryText !== '') {
    $where = '';
    if (ctype_digit($queryText)) {
        $where = "fid='" . (int)$queryText . "'";
    } elseif (strlen($queryText) >= 3) {
        $like = $db->escape_string_like($queryText);
        $where = "(nombre LIKE '%{$like}%' OR apodo LIKE '%{$like}%')";
    }
    if ($where !== '') {
        $query = $db->simple_select('op_fichas', 'fid,nombre,apodo,aprobada_por,muerto', $where, array('limit' => 12, 'order_by' => 'nombre'));
        while ($row = $db->fetch_array($query)) $searchResults[] = $row;
    }
}

$target = opg_hlw_staff_target($targetUid);
$targetHtml = '';
if ($target) {
    $eligible = opg_hlw_eligible_character($targetUid) !== null;
    $balance = opg_hlw_balance($targetUid);
    $targetHtml .= '<section class="hlw-staff-target"><div class="hlw-section-heading"><div><p>Personaje seleccionado</p><h2>'
        . opg_hlw_escape($target['nombre']) . ' <small>#' . $targetUid . '</small></h2></div><span>'
        . ($eligible ? 'Ficha activa y aprobada' : 'Ficha no elegible') . '</span></div>'
        . '<div class="hlw-balance-grid"><div><small>Saldo</small><strong>' . number_format($balance['saldo'], 0, ',', '.') . '</strong></div>'
        . '<div><small>Obtenidas</small><strong>' . number_format($balance['ganado_total'], 0, ',', '.') . '</strong></div>'
        . '<div><small>Gastadas</small><strong>' . number_format($balance['gastado_total'], 0, ',', '.') . '</strong></div></div>';
    if ($eligible) {
        $presets = opg_hlw_staff_presets();
        $targetHtml .= '<h3>Premios predefinidos</h3><div class="hlw-preset-grid">';
        foreach ($presets as $key => $preset) {
            $keySql = $db->escape_string('halloween2026:preset:' . $key . ':' . $targetUid);
            $query = $db->simple_select('op_halloween2026_movimientos', 'id', "idempotency_key='{$keySql}'", array('limit' => 1));
            $given = (int)$db->fetch_field($query, 'id') > 0;
            $targetHtml .= '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
                . '<input type="hidden" name="action" value="preset"><input type="hidden" name="uid" value="' . $targetUid . '">'
                . '<input type="hidden" name="preset" value="' . opg_hlw_escape($key) . '"><button type="submit"' . ($given ? ' disabled' : '') . '>'
                . '<span>' . opg_hlw_escape($preset['label']) . '</span><strong>+' . (int)$preset['amount'] . '</strong><small>'
                . ($given ? 'Entregado' : 'Conceder una vez') . '</small></button></form>';
        }
        $token = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : sha1(uniqid('', true));
        $targetHtml .= '</div><div class="hlw-staff-forms"><form method="post"><h3>Regalo de Kuro</h3>'
            . '<input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '"><input type="hidden" name="action" value="gift">'
            . '<input type="hidden" name="uid" value="' . $targetUid . '"><label>Cantidad<input type="number" name="amount" min="1" max="10000" required></label>'
            . '<label>Identificador del reparto<input type="text" name="distribution" maxlength="80" placeholder="kuro-2026-10-31-a" required></label>'
            . '<button class="hlw-button hlw-button--primary" type="submit">Conceder regalo</button></form>'
            . '<form method="post"><h3>Ajuste manual</h3><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
            . '<input type="hidden" name="action" value="adjust"><input type="hidden" name="uid" value="' . $targetUid . '">'
            . '<input type="hidden" name="token" value="' . opg_hlw_escape($token) . '"><label>Cantidad con signo<input type="number" name="amount" required></label>'
            . '<label>Motivo<input type="text" name="reason" maxlength="255" required></label>'
            . '<button class="hlw-button hlw-button--primary" type="submit">Registrar ajuste</button></form></div>';
    }
    $activity = opg_hlw_stats_activity($targetUid, 1, 20);
    $targetHtml .= '<h3>Ultimos movimientos</h3><div class="hlw-staff-movements">';
    foreach ($activity['rows'] as $row) {
        $reverseForm = '';
        if ($row['bucket'] === 'earned' && $row['source_type'] !== 'reversal') {
            $reverseForm = '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
                . '<input type="hidden" name="action" value="reverse"><input type="hidden" name="uid" value="' . $targetUid . '">'
                . '<input type="hidden" name="movement_id" value="' . (int)$row['id'] . '"><input type="hidden" name="reason" value="Reversion manual de staff">'
                . '<button type="submit" title="Crear contramovimiento"><i class="fa-solid fa-rotate-left"></i></button></form>';
        }
        $targetHtml .= '<div><span>#' . (int)$row['id'] . ' ' . opg_hlw_escape(opg_hlw_stats_source_label($row['source_type'])) . '</span>'
            . '<strong>' . ((int)$row['amount'] > 0 ? '+' : '') . (int)$row['amount'] . '</strong><small>' . opg_hlw_escape($row['note']) . '</small>'
            . $reverseForm . '</div>';
    }
    $targetHtml .= '</div></section>';
}

$resultsHtml = '';
foreach ($searchResults as $row) {
    $resultsHtml .= '<a href="' . opg_hlw_staff_url(array('uid' => (int)$row['fid'])) . '"><strong>'
        . opg_hlw_escape($row['nombre']) . '</strong><span>FID #' . (int)$row['fid'] . '</span></a>';
}
if ($queryText !== '' && $resultsHtml === '') $resultsHtml = '<p>No se encontraron personajes.</p>';

$summary = opg_hlw_stats_summary();
$config = opg_hlw_config();
$storeOpen = opg_hlw_store_is_open($config);
$liveTest = $config['settings']['live_test'] ?? array();
$liveTestHtml = '';
if (($config['status'] ?? '') === 'draft') {
    if (!empty($liveTest['enabled'])) {
        $runId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($liveTest['run_id'] ?? ''));
        $prefixSql = $db->escape_string('halloween2026:live-test:' . $runId . ':');
        $query = $db->simple_select('op_halloween2026_movimientos', '*',
            "idempotency_key LIKE '{$prefixSql}%'", array('order_by' => 'id', 'order_dir' => 'ASC'));
        $movementHtml = '';
        while ($row = $db->fetch_array($query)) {
            $postLink = (int)$row['source_pid'] > 0
                ? '<a href="/showthread.php?pid=' . (int)$row['source_pid'] . '#pid' . (int)$row['source_pid'] . '">Ver post</a>' : '';
            $movementHtml .= '<li><span>' . opg_hlw_escape(opg_hlw_stats_source_label($row['source_type']))
                . '<small>' . opg_hlw_escape($row['note']) . '</small></span><strong>+'
                . (int)$row['amount'] . '</strong>' . $postLink . '</li>';
        }
        if ($movementHtml === '') {
            $movementHtml = '<li class="is-empty">Todavia no se ha detectado ningun post nuevo de UID 315.</li>';
        }
        $expired = strtotime((string)($liveTest['expires_at_utc'] ?? '') . ' UTC') <= time();
        $liveTestHtml = '<section class="hlw-live-test is-active"><div class="hlw-live-test__heading"><div><p>Prueba end-to-end</p>'
            . '<h2>' . ($expired ? 'Prueba vencida' : 'Esperando una publicacion de UID 315') . '</h2>'
            . '<span>Ejecucion ' . opg_hlw_escape($runId) . ' · vence ' . opg_hlw_escape($liveTest['expires_at_utc'] ?? '') . ' UTC</span></div>'
            . '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
            . '<input type="hidden" name="action" value="stop_live_test"><button type="submit"><i class="fa-solid fa-stop"></i> Finalizar y revertir</button></form></div>'
            . '<ol>' . $movementHtml . '</ol><small>Publica con la cuenta 315 en una zona de rol y recarga esta consola. Un primer reply valido debe mostrar +1 por el post y +1 por el bono diario.</small></section>';
    } else {
        $liveTestHtml = '<section class="hlw-live-test"><div class="hlw-live-test__heading"><div><p>Prueba end-to-end</p>'
            . '<h2>Comprobar el hook con UID 315</h2><span>Habilita durante una hora solamente las publicaciones nuevas de esta cuenta.</span></div>'
            . '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
            . '<input type="hidden" name="action" value="start_live_test"><button type="submit"><i class="fa-solid fa-play"></i> Iniciar prueba real</button></form></div>'
            . '<small>Al finalizar, los premios de prueba se compensan con contramovimientos. No se elimina el historial.</small></section>';
    }
}
$preflightHtml = '';
if ($preflightResult && !empty($preflightResult['ok'])) {
    $scenarioHtml = '';
    foreach ($preflightResult['scenarios'] as $scenario) {
        $scenarioHtml .= '<li><span>' . opg_hlw_escape($scenario['label']) . '</span><strong>+'
            . (int)$scenario['amount'] . '</strong></li>';
    }
    $preflightHtml = '<section class="hlw-preflight-result"><div><p>Prueba previa superada</p><h2>'
        . opg_hlw_escape($preflightResult['name']) . ' <small>UID #' . (int)$preflightResult['uid'] . '</small></h2></div>'
        . '<ul>' . $scenarioHtml . '</ul><dl><div><dt>Saldo inicial</dt><dd>' . (int)$preflightResult['before'] . '</dd></div>'
        . '<div><dt>Saldo simulado</dt><dd>' . (int)$preflightResult['simulated'] . '</dd></div>'
        . '<div><dt>Saldo final</dt><dd>' . (int)$preflightResult['after'] . '</dd></div></dl>'
        . '<small>Catalogo: ' . (int)$preflightResult['store_items'] . ' articulos activos. CFRHLW encontrado. Idempotencia correcta. Ningun movimiento fue guardado.</small></section>';
}
$auditHtml = '';
if ($mybb->get_input('audit', MyBB::INPUT_INT) === 1) {
    $audit = opg_hlw_stats_audit_preview();
    $auditHtml = '<section class="hlw-audit"><div><p>Auditoria de solo lectura</p><h2>Previsualizacion de reconciliacion</h2></div>'
        . '<span><strong>' . $audit['missing_posts'] . '</strong> posts sin recompensa de ' . $audit['expected_posts'] . '</span>'
        . '<span><strong>' . $audit['missing_threads'] . '</strong> temas sin recompensa de ' . $audit['expected_threads'] . '</span>'
        . '<small>No se ha modificado ningun saldo.</small></section>';
}
$staffContent = $message
    . '<section class="hlw-staff-overview"><div><small>Calabazas obtenidas</small><strong>' . number_format($summary['earned'], 0, ',', '.') . '</strong></div>'
    . '<div><small>Participantes</small><strong>' . number_format($summary['participants'], 0, ',', '.') . '</strong></div>'
    . '<div><small>Compras completadas</small><strong>' . number_format($summary['purchases'], 0, ',', '.') . '</strong></div>'
    . '<div><small>Estado del evento</small><strong>' . opg_hlw_escape($config['status']) . '</strong>'
    . ($config['status'] === 'draft' ? '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '"><input type="hidden" name="action" value="schedule_event">'
        . '<button type="submit">Activar recompensas automaticas</button></form>' : '') . '</div>'
    . '<div><small>Estado de tienda</small><strong>' . ($storeOpen ? 'Abierta' : 'Cerrada') . '</strong>'
    . (!$storeOpen ? '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '"><input type="hidden" name="action" value="open_store">'
        . '<button type="submit">Abrir despues de la prueba</button></form>' : '') . '</div></section>'
    . ($config['status'] === 'draft' ? '<section class="hlw-preflight"><div><p>Comprobacion segura</p><h2>Prueba previa con UID 315</h2>'
        . '<span>Simula las recompensas y valida el catalogo dentro de una transaccion que siempre termina con ROLLBACK.</span></div>'
        . '<form method="post"><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
        . '<input type="hidden" name="action" value="preflight"><button type="submit"><i class="fa-solid fa-flask"></i> Ejecutar prueba segura</button></form></section>' : '')
    . $preflightHtml
    . $liveTestHtml
    . '<div class="hlw-audit-link"><a href="' . opg_hlw_staff_url(array('audit' => 1, 'uid' => $targetUid)) . '"><i class="fa-solid fa-magnifying-glass"></i> Comprobar recompensas sin modificar datos</a></div>'
    . $auditHtml
    . '<section class="hlw-staff-search"><div><h2>Buscar personaje</h2><p>Nombre, apodo o FID.</p></div><form method="get">'
    . '<input type="search" name="q" value="' . opg_hlw_escape($queryText) . '" minlength="3" placeholder="Nombre o FID"><button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button></form>'
    . '<div class="hlw-staff-results">' . $resultsHtml . '</div></section>' . $targetHtml;

$templatePath = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/staff_halloween2026.html';
$template = is_file($templatePath) ? str_replace("\\'", "'", addslashes(file_get_contents($templatePath))) : $templates->get('staff_halloween2026');
eval('$page = "' . $template . '";');
output_page($page);
