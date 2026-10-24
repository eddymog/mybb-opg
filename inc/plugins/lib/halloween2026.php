<?php
/**
 * Halloween 2026: dominio, libro de movimientos y reconciliacion de actividad.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function opg_hlw_table($name)
{
    global $db;
    return $db->table_prefix . 'op_halloween2026_' . $name;
}

function opg_hlw_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function opg_hlw_tables_ready()
{
    global $db;
    foreach (array('config', 'saldos', 'movimientos', 'publicaciones', 'bonos_diarios') as $table) {
        if (!$db->table_exists('op_halloween2026_' . $table)) {
            return false;
        }
    }
    return true;
}

function opg_hlw_config($refresh = false)
{
    global $db;
    static $config = null;
    if ($config !== null && !$refresh) {
        return $config;
    }
    $config = array();
    if (!opg_hlw_tables_ready()) {
        return $config;
    }
    $query = $db->simple_select('op_halloween2026_config', '*', "id='1'", array('limit' => 1));
    $row = $db->fetch_array($query);
    if (!$row) {
        return $config;
    }
    $extra = json_decode((string)$row['config_json'], true);
    $row['settings'] = is_array($extra) ? $extra : array();
    $config = $row;
    return $config;
}

function opg_hlw_utc_now()
{
    return gmdate('Y-m-d H:i:s');
}

function opg_hlw_timestamp_in_earning_window($timestamp, $config = null)
{
    $config = $config === null ? opg_hlw_config() : $config;
    if (!$config) {
        return false;
    }
    $start = strtotime((string)$config['earn_start_utc'] . ' UTC');
    $end = strtotime((string)$config['earn_end_utc'] . ' UTC');
    return (int)$timestamp >= $start && (int)$timestamp < $end;
}

function opg_hlw_store_is_open($config = null)
{
    $config = $config === null ? opg_hlw_config() : $config;
    if (!$config || (string)$config['status'] !== 'active') {
        return false;
    }
    if (empty($config['settings']['store_enabled'])) {
        return false;
    }
    $now = time();
    $start = empty($config['store_start_utc']) ? 0 : strtotime($config['store_start_utc'] . ' UTC');
    $end = empty($config['store_end_utc']) ? PHP_INT_MAX : strtotime($config['store_end_utc'] . ' UTC');
    return $now >= $start && $now < $end;
}

function opg_hlw_eligible_character($uid, $lock = false)
{
    global $db;
    $uid = (int)$uid;
    if ($uid <= 0) {
        return null;
    }
    $table = $db->table_prefix . 'op_fichas';
    $sql = "SELECT * FROM `{$table}` WHERE fid='{$uid}'"
        . " AND aprobada_por NOT IN ('sin_aprobar','pendiente_reset')"
        . " AND (muerto IS NULL OR muerto='0') LIMIT 1";
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $query = $db->write_query($sql);
    $row = $db->fetch_array($query);
    return $row ?: null;
}

function opg_hlw_forum_is_role($fid, $parentlist, $config = null)
{
    $config = $config === null ? opg_hlw_config() : $config;
    $root = isset($config['settings']['role_root_fid'])
        ? (int)$config['settings']['role_root_fid'] : 10;
    if ((int)$fid === $root) {
        return true;
    }
    $parents = array_filter(array_map('intval', explode(',', (string)$parentlist)));
    return in_array($root, $parents, true);
}

function opg_hlw_event_day($timestamp, $timezone = 'Europe/Madrid')
{
    try {
        $date = new DateTime('@' . (int)$timestamp);
        $date->setTimezone(new DateTimeZone($timezone));
        $date->modify('-6 hours');
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        return gmdate('Y-m-d', (int)$timestamp - 21600);
    }
}

function opg_hlw_thread_reward($prefixId, $config)
{
    $settings = isset($config['settings']) && is_array($config['settings'])
        ? $config['settings'] : array();
    $prefixId = (int)$prefixId;
    if ($prefixId === (int)($settings['spooky_prefix_id'] ?? 15)) {
        return array('amount' => 3, 'type' => 'spooky', 'label' => 'Tema Spooky');
    }
    if (in_array($prefixId, array(
        (int)($settings['common_prefix_id'] ?? 1),
        (int)($settings['adventure_prefix_id'] ?? 3),
    ), true)) {
        return array('amount' => 2, 'type' => 'thread', 'label' => 'Tema nuevo');
    }
    return array('amount' => 0, 'type' => 'thread', 'label' => '');
}

function opg_hlw_live_test_context($publication, $config = null)
{
    $config = $config === null ? opg_hlw_config() : $config;
    $test = $config['settings']['live_test'] ?? array();
    if (($config['status'] ?? '') !== 'draft' || empty($test['enabled'])) {
        return null;
    }
    $uid = (int)($test['uid'] ?? 0);
    $runId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($test['run_id'] ?? ''));
    $startedRaw = trim((string)($test['started_at_utc'] ?? ''));
    $expiresRaw = trim((string)($test['expires_at_utc'] ?? ''));
    $started = $startedRaw === '' ? false : strtotime($startedRaw . ' UTC');
    $expires = $expiresRaw === '' ? false : strtotime($expiresRaw . ' UTC');
    $published = (int)($publication['original_dateline'] ?? 0);
    if ($uid <= 0 || $runId === '' || $started === false || $expires === false
        || (int)($publication['uid'] ?? 0) !== $uid
        || $published < $started || $published >= $expires || time() >= $expires) {
        return null;
    }
    return array(
        'uid' => $uid,
        'run_id' => $runId,
        'key_prefix' => 'halloween2026:live-test:' . $runId,
        'metadata' => array('live_test' => true, 'live_test_run' => $runId),
        'note_prefix' => '[Prueba real] ',
    );
}

function opg_hlw_ensure_balance_row($uid)
{
    global $db;
    $table = opg_hlw_table('saldos');
    $now = opg_hlw_utc_now();
    $db->write_query("INSERT IGNORE INTO `{$table}`
        (`uid`,`ganado_total`,`gastado_total`,`saldo`,`created_at`,`updated_at`)
        VALUES (" . (int)$uid . ",0,0,0,'{$now}','{$now}')");
}

/**
 * Appends one immutable movement. Amount is signed. Buckets: earned or spent.
 */
function opg_hlw_add_movement($data, $manage_transaction = true)
{
    global $db;
    $uid = (int)($data['uid'] ?? 0);
    $amount = (int)($data['amount'] ?? 0);
    $bucket = ($data['bucket'] ?? 'earned') === 'spent' ? 'spent' : 'earned';
    $key = trim((string)($data['idempotency_key'] ?? ''));
    if ($uid <= 0 || $amount === 0 || $key === '') {
        return array('ok' => false, 'code' => 'invalid_movement');
    }

    if ($manage_transaction) {
        $db->write_query('START TRANSACTION');
    }
    try {
        opg_hlw_ensure_balance_row($uid);
        $balances = opg_hlw_table('saldos');
        $movements = opg_hlw_table('movimientos');
        $query = $db->write_query("SELECT * FROM `{$balances}` WHERE uid='{$uid}' FOR UPDATE");
        $balance = $db->fetch_array($query);

        $baseKey = $key;
        while (true) {
            $keySql = $db->escape_string($key);
            $query = $db->simple_select('op_halloween2026_movimientos', 'id,amount', "idempotency_key='{$keySql}'", array('limit' => 1));
            $existing = $db->fetch_array($query);
            if (!$existing) {
                break;
            }
            $query = $db->simple_select('op_halloween2026_movimientos', 'id',
                "reverses_movement_id='" . (int)$existing['id'] . "'", array('limit' => 1));
            $reversalId = (int)$db->fetch_field($query, 'id');
            if ($reversalId <= 0) {
                if ($manage_transaction) {
                    $db->write_query('COMMIT');
                }
                return array('ok' => true, 'duplicate' => true, 'movement_id' => (int)$existing['id']);
            }
            $key = substr($baseKey, 0, 160) . ':restore:' . $reversalId;
        }

        $sourceType = $db->escape_string(substr((string)($data['source_type'] ?? 'manual'), 0, 32));
        $sourceId = $db->escape_string(substr((string)($data['source_id'] ?? ''), 0, 128));
        $note = isset($data['note']) && $data['note'] !== ''
            ? "'" . $db->escape_string(substr((string)$data['note'], 0, 255)) . "'" : 'NULL';
        $metadata = isset($data['metadata'])
            ? "'" . $db->escape_string(json_encode($data['metadata'], JSON_UNESCAPED_UNICODE)) . "'" : 'NULL';
        $reverse = !empty($data['reverses_movement_id']) ? (int)$data['reverses_movement_id'] : 0;
        $reverseSql = $reverse > 0 ? (string)$reverse : 'NULL';
        $pidSql = !empty($data['source_pid']) ? (string)(int)$data['source_pid'] : 'NULL';
        $tidSql = !empty($data['source_tid']) ? (string)(int)$data['source_tid'] : 'NULL';
        $actorSql = !empty($data['actor_uid']) ? (string)(int)$data['actor_uid'] : 'NULL';
        $now = opg_hlw_utc_now();

        $db->write_query("INSERT INTO `{$movements}`
            (`uid`,`amount`,`bucket`,`source_type`,`source_id`,`source_pid`,`source_tid`,
             `idempotency_key`,`reverses_movement_id`,`actor_uid`,`note`,`metadata_json`,`created_at`)
            VALUES ({$uid},{$amount},'{$bucket}','{$sourceType}','{$sourceId}',{$pidSql},{$tidSql},
             '{$keySql}',{$reverseSql},{$actorSql},{$note},{$metadata},'{$now}')");
        $movementId = (int)$db->insert_id();

        $earned = (int)$balance['ganado_total'];
        $spent = (int)$balance['gastado_total'];
        $newBalance = (int)$balance['saldo'] + $amount;
        if ($bucket === 'earned') {
            $earned += $amount;
        } else {
            $spent += -$amount;
        }
        if ($earned < 0 || $spent < 0) {
            throw new RuntimeException('negative_balance');
        }
        $lastTotal = $bucket === 'earned' ? "'{$now}'" : 'fecha_ultimo_total';
        $db->write_query("UPDATE `{$balances}` SET ganado_total='{$earned}', gastado_total='{$spent}',
            saldo='{$newBalance}', fecha_ultimo_total={$lastTotal}, updated_at='{$now}' WHERE uid='{$uid}'");

        if ($manage_transaction) {
            $db->write_query('COMMIT');
        }
        return array('ok' => true, 'duplicate' => false, 'movement_id' => $movementId, 'saldo' => $newBalance);
    } catch (Exception $e) {
        if ($manage_transaction) {
            $db->write_query('ROLLBACK');
        }
        return array('ok' => false, 'code' => $e->getMessage());
    }
}

function opg_hlw_reverse_movement($movementId, $actorUid = 0, $reason = 'reconcile')
{
    global $db;
    $movementId = (int)$movementId;
    if ($movementId <= 0) {
        return array('ok' => false, 'code' => 'invalid_movement');
    }
    $query = $db->simple_select('op_halloween2026_movimientos', '*', "id='{$movementId}'", array('limit' => 1));
    $movement = $db->fetch_array($query);
    if (!$movement) {
        return array('ok' => false, 'code' => 'not_found');
    }
    if ($movement['bucket'] !== 'earned' || $movement['source_type'] === 'reversal') {
        return array('ok' => false, 'code' => 'not_reversible_here');
    }
    return opg_hlw_add_movement(array(
        'uid' => (int)$movement['uid'],
        'amount' => -(int)$movement['amount'],
        'bucket' => $movement['bucket'],
        'source_type' => 'reversal',
        'source_id' => (string)$movementId,
        'source_pid' => (int)$movement['source_pid'],
        'source_tid' => (int)$movement['source_tid'],
        'idempotency_key' => 'halloween2026:reversal:' . $movementId,
        'reverses_movement_id' => $movementId,
        'actor_uid' => (int)$actorUid,
        'note' => $reason,
    ));
}

function opg_hlw_load_post_context($pid)
{
    global $db;
    $pid = (int)$pid;
    if ($pid <= 0) {
        return null;
    }
    $posts = $db->table_prefix . 'posts';
    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $query = $db->write_query("SELECT p.pid,p.tid,p.uid,p.dateline,p.visible AS post_visible,
        t.fid,t.firstpost,t.prefix,t.visible AS thread_visible,f.parentlist
        FROM `{$posts}` p
        INNER JOIN `{$threads}` t ON t.tid=p.tid
        INNER JOIN `{$forums}` f ON f.fid=t.fid
        WHERE p.pid='{$pid}' LIMIT 1");
    $row = $db->fetch_array($query);
    return $row ?: null;
}

function opg_hlw_observe_post($context)
{
    global $db;
    $pid = (int)$context['pid'];
    $table = opg_hlw_table('publicaciones');
    $query = $db->simple_select('op_halloween2026_publicaciones', '*', "pid='{$pid}'", array('limit' => 1));
    $existing = $db->fetch_array($query);
    $config = opg_hlw_config();
    $now = opg_hlw_utc_now();
    if (!$existing) {
        $role = opg_hlw_forum_is_role($context['fid'], $context['parentlist'], $config) ? 1 : 0;
        $db->write_query("INSERT IGNORE INTO `{$table}`
            (`pid`,`tid`,`uid`,`original_fid`,`original_dateline`,`original_role_eligible`,
             `is_first_post`,`original_prefix_id`,`post_visible`,`thread_visible`,
             `reconcile_status`,`last_reason`,`created_at`,`updated_at`)
            VALUES ({$pid}," . (int)$context['tid'] . ',' . (int)$context['uid'] . ',' . (int)$context['fid'] . ','
            . (int)$context['dateline'] . ",{$role}," . ((int)$context['firstpost'] === $pid ? 1 : 0) . ','
            . (int)$context['prefix'] . ',' . (int)$context['post_visible'] . ',' . (int)$context['thread_visible'] . ",
            'observed',NULL,'{$now}','{$now}')");
        $query = $db->simple_select('op_halloween2026_publicaciones', '*', "pid='{$pid}'", array('limit' => 1));
        return $db->fetch_array($query);
    }
    $db->write_query("UPDATE `{$table}` SET post_visible='" . (int)$context['post_visible']
        . "', thread_visible='" . (int)$context['thread_visible'] . "', updated_at='{$now}' WHERE pid='{$pid}'");
    return $existing;
}

function opg_hlw_assign_daily_bonus($publication, $config, $movementContext = array())
{
    global $db;
    $uid = (int)$publication['uid'];
    $pid = (int)$publication['pid'];
    $day = opg_hlw_event_day((int)$publication['original_dateline'], $config['timezone']);
    $daily = opg_hlw_table('bonos_diarios');
    $now = opg_hlw_utc_now();
    $db->write_query("INSERT IGNORE INTO `{$daily}`
        (`uid`,`event_day`,`pid`,`movement_id`,`status`,`created_at`,`updated_at`)
        VALUES ({$uid},'{$day}',{$pid},NULL,'assigned','{$now}','{$now}')");
    if ($db->affected_rows() === 0) {
        $query = $db->simple_select('op_halloween2026_bonos_diarios', '*',
            "uid='{$uid}' AND event_day='{$day}'", array('limit' => 1));
        $existing = $db->fetch_array($query);
        if (!$existing || $existing['status'] !== 'revoked' || !empty($existing['pid'])) {
            return;
        }
        $db->write_query("UPDATE `{$daily}` SET pid='{$pid}',status='assigned',updated_at='{$now}'
            WHERE uid='{$uid}' AND event_day='{$day}' AND status='revoked' AND pid IS NULL");
        if ($db->affected_rows() === 0) {
            return;
        }
    }
    $keyPrefix = $movementContext['key_prefix'] ?? 'halloween2026';
    $notePrefix = $movementContext['note_prefix'] ?? '';
    $result = opg_hlw_add_movement(array(
        'uid' => $uid,
        'amount' => 1,
        'bucket' => 'earned',
        'source_type' => 'daily',
        'source_id' => $uid . ':' . $day,
        'source_pid' => $pid,
        'source_tid' => (int)$publication['tid'],
        'idempotency_key' => $keyPrefix . ':daily:' . $uid . ':' . $day . ':' . $pid,
        'note' => $notePrefix . 'Primer post diario',
        'metadata' => $movementContext['metadata'] ?? null,
    ));
    if (!empty($result['ok'])) {
        $db->update_query('op_halloween2026_bonos_diarios', array(
            'movement_id' => (int)$result['movement_id'],
            'updated_at' => $now,
        ), "uid='{$uid}' AND event_day='{$day}' AND pid='{$pid}'");
    } else {
        $db->write_query("UPDATE `{$daily}` SET pid=NULL,movement_id=NULL,status='revoked',updated_at='{$now}'
            WHERE uid='{$uid}' AND event_day='{$day}' AND pid='{$pid}'");
    }
}

function opg_hlw_reconcile_post($pid, $reason = 'hook')
{
    global $db;
    if (!opg_hlw_tables_ready()) {
        return;
    }
    $context = opg_hlw_load_post_context($pid);
    if (!$context) {
        opg_hlw_reverse_post($pid, $reason);
        return;
    }
    $publication = opg_hlw_observe_post($context);
    if (!$publication) {
        return;
    }
    $config = opg_hlw_config();
    $liveTest = opg_hlw_live_test_context($publication, $config);
    if ((string)($config['status'] ?? 'draft') === 'draft' && !$liveTest) {
        return;
    }
    $movementContext = $liveTest ?: array(
        'key_prefix' => 'halloween2026',
        'metadata' => null,
        'note_prefix' => '',
    );
    $insideWindow = opg_hlw_timestamp_in_earning_window((int)$publication['original_dateline'], $config);
    $currentRole = opg_hlw_forum_is_role($context['fid'], $context['parentlist'], $config);
    $valid = (int)$publication['original_role_eligible'] === 1
        && $currentRole
        && (int)$context['post_visible'] === 1
        && (int)$context['thread_visible'] === 1
        && ($insideWindow || $liveTest)
        && opg_hlw_eligible_character((int)$publication['uid']) !== null;
    if (!$valid) {
        opg_hlw_reverse_post($pid, $reason);
        $db->update_query('op_halloween2026_publicaciones', array(
            'reconcile_status' => 'ineligible',
            'last_reason' => substr((string)$reason, 0, 64),
            'updated_at' => opg_hlw_utc_now(),
        ), "pid='" . (int)$pid . "'");
        return;
    }

    opg_hlw_add_movement(array(
        'uid' => (int)$publication['uid'], 'amount' => 1, 'bucket' => 'earned',
        'source_type' => 'post', 'source_id' => (string)$pid,
        'source_pid' => (int)$pid, 'source_tid' => (int)$publication['tid'],
        'idempotency_key' => $movementContext['key_prefix'] . ':post:' . (int)$pid,
        'note' => $movementContext['note_prefix'] . 'Publicacion en zona de rol',
        'metadata' => $movementContext['metadata'],
    ));

    if ((int)$publication['is_first_post'] === 1) {
        $threadReward = opg_hlw_thread_reward((int)$publication['original_prefix_id'], $config);
        if ($threadReward['amount'] > 0) {
            opg_hlw_add_movement(array(
                'uid' => (int)$publication['uid'], 'amount' => $threadReward['amount'], 'bucket' => 'earned',
                'source_type' => $threadReward['type'],
                'source_id' => (string)$publication['tid'], 'source_pid' => (int)$pid,
                'source_tid' => (int)$publication['tid'],
                'idempotency_key' => $movementContext['key_prefix'] . ':thread:' . (int)$publication['tid'],
                'note' => $movementContext['note_prefix'] . $threadReward['label'],
                'metadata' => $movementContext['metadata'],
            ));
        }
    }
    opg_hlw_assign_daily_bonus($publication, $config, $movementContext);
    $db->update_query('op_halloween2026_publicaciones', array(
        'reconcile_status' => 'rewarded',
        'last_reason' => substr((string)$reason, 0, 64),
        'updated_at' => opg_hlw_utc_now(),
    ), "pid='" . (int)$pid . "'");
}

function opg_hlw_reverse_post($pid, $reason = 'moderation')
{
    global $db;
    $pid = (int)$pid;
    if ($pid <= 0 || !opg_hlw_tables_ready()) {
        return;
    }
    $movements = opg_hlw_table('movimientos');
    $query = $db->write_query("SELECT m.id,m.source_type FROM `{$movements}` m
        LEFT JOIN `{$movements}` r ON r.reverses_movement_id=m.id
        WHERE m.source_pid='{$pid}' AND m.amount>0 AND m.bucket='earned'
          AND m.source_type IN ('post','thread','spooky','daily') AND r.id IS NULL");
    $dailyReversed = false;
    while ($movement = $db->fetch_array($query)) {
        opg_hlw_reverse_movement((int)$movement['id'], 0, $reason);
        if ($movement['source_type'] === 'daily') {
            $dailyReversed = true;
        }
    }
    $db->update_query('op_halloween2026_publicaciones', array(
        'reconcile_status' => 'reversed',
        'last_reason' => substr((string)$reason, 0, 64),
        'post_visible' => 0,
        'updated_at' => opg_hlw_utc_now(),
    ), "pid='{$pid}'");
    if ($dailyReversed) {
        opg_hlw_reassign_daily_for_pid($pid);
    }
}

function opg_hlw_reassign_daily_for_pid($pid)
{
    global $db;
    $pid = (int)$pid;
    $query = $db->simple_select('op_halloween2026_bonos_diarios', '*', "pid='{$pid}'", array('limit' => 1));
    $daily = $db->fetch_array($query);
    if (!$daily) {
        return;
    }
    $uid = (int)$daily['uid'];
    $day = $db->escape_string($daily['event_day']);
    $publications = opg_hlw_table('publicaciones');
    $start = new DateTime($daily['event_day'] . ' 06:00:00', new DateTimeZone('Europe/Madrid'));
    $end = clone $start;
    $end->modify('+1 day');
    $query = $db->write_query("SELECT pid FROM `{$publications}` WHERE uid='{$uid}'
        AND original_dateline>='" . $start->getTimestamp() . "' AND original_dateline<'" . $end->getTimestamp() . "'
        AND original_role_eligible=1 AND post_visible=1 AND thread_visible=1
        ORDER BY original_dateline ASC,pid ASC LIMIT 1");
    $replacement = (int)$db->fetch_field($query, 'pid');
    if ($replacement > 0) {
        $movement = opg_hlw_add_movement(array(
            'uid' => $uid,
            'amount' => 1,
            'bucket' => 'earned',
            'source_type' => 'daily',
            'source_id' => $uid . ':' . $daily['event_day'],
            'source_pid' => $replacement,
            'idempotency_key' => 'halloween2026:daily:' . $uid . ':' . $daily['event_day'] . ':' . $replacement,
            'note' => 'Primer post diario reasignado',
        ));
        if (!empty($movement['ok'])) {
            $db->update_query('op_halloween2026_bonos_diarios', array(
                'pid' => $replacement,
                'movement_id' => (int)$movement['movement_id'],
                'status' => 'assigned',
                'updated_at' => opg_hlw_utc_now(),
            ), "uid='{$uid}' AND event_day='{$day}'");
        }
    } else {
        $table = opg_hlw_table('bonos_diarios');
        $now = opg_hlw_utc_now();
        $db->write_query("UPDATE `{$table}` SET pid=NULL,movement_id=NULL,status='revoked',updated_at='{$now}'
            WHERE uid='{$uid}' AND event_day='{$day}'");
    }
}

function opg_hlw_reconcile_thread($tid, $reason = 'moderation')
{
    global $db;
    $tid = (int)$tid;
    if ($tid <= 0) {
        return;
    }
    $query = $db->simple_select('posts', 'pid', "tid='{$tid}'", array('order_by' => 'pid', 'order_dir' => 'ASC'));
    $found = false;
    while ($pid = $db->fetch_field($query, 'pid')) {
        $found = true;
        opg_hlw_reconcile_post((int)$pid, $reason);
    }
    if (!$found && opg_hlw_tables_ready()) {
        $query = $db->simple_select('op_halloween2026_publicaciones', 'pid', "tid='{$tid}'");
        while ($pid = $db->fetch_field($query, 'pid')) {
            opg_hlw_reverse_post((int)$pid, $reason);
        }
    }
}

function opg_hlw_balance($uid)
{
    global $db;
    $empty = array('uid' => (int)$uid, 'ganado_total' => 0, 'gastado_total' => 0, 'saldo' => 0);
    if (!opg_hlw_tables_ready()) {
        return $empty;
    }
    $query = $db->simple_select('op_halloween2026_saldos', '*', "uid='" . (int)$uid . "'", array('limit' => 1));
    return $db->fetch_array($query) ?: $empty;
}

function opg_hlw_set_store_enabled($enabled)
{
    global $db;
    $value = $enabled ? 'true' : 'false';
    $table = opg_hlw_table('config');
    $db->write_query("UPDATE `{$table}` SET config_json=JSON_SET(COALESCE(config_json,JSON_OBJECT()),
        '$.store_enabled',{$value}), updated_at=UTC_TIMESTAMP() WHERE id=1");
    opg_hlw_config(true);
}

function opg_hlw_set_status($status)
{
    global $db;
    if (!in_array($status, array('draft', 'active', 'archived'), true)) {
        return false;
    }
    $table = opg_hlw_table('config');
    $statusSql = $db->escape_string($status);
    $db->write_query("UPDATE `{$table}` SET status='{$statusSql}',updated_at=UTC_TIMESTAMP() WHERE id=1");
    opg_hlw_config(true);
    return true;
}
