<?php
/**
 * Funciones de lectura e indexacion del Panel tactico.
 *
 * El indexador nunca ejecuta efectos de juego: solo observa MyCode ya guardado.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function opg_pt_tables_ready()
{
    global $db;
    return $db->table_exists('op_panel_tactico_posts')
        && $db->table_exists('op_panel_tactico_eventos');
}

function opg_pt_json_encode($value)
{
    $flags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
    $json = json_encode($value, $flags);
    return $json === false ? '{}' : $json;
}

function opg_pt_escape($value)
{
    return htmlspecialchars_uni((string)$value);
}

function opg_pt_normalize_ids($values)
{
    if (!is_array($values)) {
        $values = array($values);
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $values))));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function opg_pt_upsert($table, $data, $where, $idField)
{
    global $db;
    $query = $db->simple_select($table, $idField, $where, array('limit' => 1));
    $existing = $db->fetch_field($query, $idField);
    if ($existing !== false && $existing !== null) {
        $db->update_query($table, $data, $where);
        return (int)$existing;
    }
    return (int)$db->insert_query($table, $data);
}

/**
 * Oculta el contenido de quote, code y hide sin cambiar los offsets.
 */
function opg_pt_mask_ignored_blocks($message)
{
    $message = (string)$message;
    $pattern = '#\[(/?)(quote|code|hide)(?:=[^\]]*)?\]#i';
    if (!preg_match_all($pattern, $message, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return $message;
    }

    $stack = array();
    $ranges = array();
    foreach ($matches as $match) {
        $token = $match[0][0];
        $offset = (int)$match[0][1];
        $closing = $match[1][0] === '/';
        $tag = strtolower($match[2][0]);

        // [hide=N] es un marcador persistido, no la apertura de un bloque.
        if (!$closing && $tag === 'hide' && preg_match('#^\[hide=\d+\]$#i', $token)) {
            continue;
        }

        if (!$closing) {
            $stack[] = array('tag' => $tag, 'start' => $offset);
            continue;
        }

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['tag'] !== $tag) {
                continue;
            }
            $open = $stack[$i];
            array_splice($stack, $i, 1);
            $ranges[] = array($open['start'], $offset + strlen($token));
            break;
        }
    }

    foreach ($stack as $open) {
        $ranges[] = array($open['start'], strlen($message));
    }
    if (empty($ranges)) {
        return $message;
    }

    usort($ranges, function ($a, $b) {
        return $a[0] <=> $b[0];
    });
    foreach ($ranges as $range) {
        $length = max(0, $range[1] - $range[0]);
        if ($length === 0) {
            continue;
        }
        $chunk = substr($message, $range[0], $length);
        $masked = preg_replace('/[^\r\n]/', ' ', $chunk);
        $message = substr_replace($message, $masked, $range[0], $length);
    }
    return $message;
}

function opg_pt_catalog_map($table, $idColumn, $ids, $columns)
{
    global $db;
    if (empty($ids)) {
        return array();
    }
    $quoted = array();
    foreach (array_keys($ids) as $id) {
        $quoted[] = "'" . $db->escape_string($id) . "'";
    }
    $map = array();
    $query = $db->simple_select(
        $table,
        implode(',', $columns),
        "{$idColumn} IN (" . implode(',', $quoted) . ')'
    );
    while ($row = $db->fetch_array($query)) {
        $map[(string)$row[$idColumn]] = $row;
    }
    return $map;
}

/**
 * Extrae solamente eventos deterministas del MVP.
 */
function opg_pt_extract_events($message)
{
    $source = opg_pt_mask_ignored_blocks((string)$message);
    $pattern = '#\[(tecnica|objeto)=([^\]\r\n]+)\]|\[recursos=["\']?([0-9]+)/([0-9]+)/([0-9]+)["\']?\]#i';
    if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return array();
    }

    $rawEvents = array();
    $techniqueIds = array();
    $objectIds = array();
    foreach ($matches as $order => $match) {
        $token = $match[0][0];
        $offset = (int)$match[0][1];
        if (!empty($match[1][0])) {
            $type = strtolower($match[1][0]) === 'tecnica' ? 'tecnica' : 'objeto_mostrado';
            $entityId = trim($match[2][0]);
            if ($entityId === '') {
                continue;
            }
            if ($type === 'tecnica') {
                $techniqueIds[$entityId] = true;
            } else {
                $objectIds[$entityId] = true;
            }
            $rawEvents[] = array(
                'order' => $order + 1,
                'type' => $type,
                'entity_id' => $entityId,
                'source_start' => $offset,
                'source_end' => $offset + strlen($token),
                'token' => $token,
            );
            continue;
        }

        $rawEvents[] = array(
            'order' => $order + 1,
            'type' => 'recurso',
            'entity_id' => '',
            'source_start' => $offset,
            'source_end' => $offset + strlen($token),
            'token' => $token,
            'vida' => (int)$match[3][0],
            'energia' => (int)$match[4][0],
            'haki' => (int)$match[5][0],
        );
    }

    $techniques = opg_pt_catalog_map(
        'op_tecnicas',
        'tid',
        $techniqueIds,
        array('tid', 'nombre', 'tier', 'clase', 'energia', 'energia_turno', 'haki', 'haki_turno')
    );
    $objects = opg_pt_catalog_map(
        'op_objetos',
        'objeto_id',
        $objectIds,
        array('objeto_id', 'nombre', 'apodo', 'tier', 'subcategoria')
    );

    $events = array();
    foreach ($rawEvents as $raw) {
        $data = array(
            'schema' => 1,
            'source' => 'mycode',
            'source_token' => $raw['token'],
        );
        $status = 'valido';

        if ($raw['type'] === 'tecnica') {
            $row = isset($techniques[$raw['entity_id']]) ? $techniques[$raw['entity_id']] : null;
            $data['tecnica_id'] = $raw['entity_id'];
            if ($row) {
                $data['nombre_snapshot'] = (string)$row['nombre'];
                $data['tier_snapshot'] = (int)$row['tier'];
                $data['clase_snapshot'] = (string)$row['clase'];
                $data['energia_snapshot'] = (int)$row['energia'];
                $data['energia_turno_snapshot'] = (int)$row['energia_turno'];
                $data['haki_snapshot'] = (int)$row['haki'];
                $data['haki_turno_snapshot'] = (int)$row['haki_turno'];
            } else {
                $data['nombre_snapshot'] = $raw['entity_id'];
                $status = 'incompleto';
            }
        } elseif ($raw['type'] === 'objeto_mostrado') {
            $row = isset($objects[$raw['entity_id']]) ? $objects[$raw['entity_id']] : null;
            $data['objeto_id'] = $raw['entity_id'];
            if ($row) {
                $data['nombre_snapshot'] = $row['apodo'] !== '' ? (string)$row['apodo'] : (string)$row['nombre'];
                $data['nombre_catalogo_snapshot'] = (string)$row['nombre'];
                $data['tier_snapshot'] = (int)$row['tier'];
                $data['subcategoria_snapshot'] = (string)$row['subcategoria'];
            } else {
                $data['nombre_snapshot'] = $raw['entity_id'];
                $status = 'incompleto';
            }
        } else {
            $data['vida'] = $raw['vida'];
            $data['energia'] = $raw['energia'];
            $data['haki'] = $raw['haki'];
        }

        $events[] = array(
            'post_order' => (int)$raw['order'],
            'type' => $raw['type'],
            'entity_id' => $raw['entity_id'],
            'data' => $data,
            'status' => $status,
            'source_start' => (int)$raw['source_start'],
            'source_end' => (int)$raw['source_end'],
        );
    }
    return $events;
}

function opg_pt_index_post($pid, $force = false)
{
    global $db;
    $pid = (int)$pid;
    if ($pid <= 0 || !opg_pt_tables_ready()) {
        return false;
    }

    $query = $db->query("SELECT p.pid,p.tid,p.uid,p.username,p.message,p.dateline,p.visible,u.username AS account_username
        FROM {$db->table_prefix}posts p
        LEFT JOIN {$db->table_prefix}users u ON u.uid=p.uid
        WHERE p.pid='{$pid}' LIMIT 1");
    $post = $db->fetch_array($query);
    if (!$post) {
        return false;
    }

    try {
        $hash = hash('sha256', (string)$post['message']);
        if (!$force) {
            $indexQuery = $db->simple_select(
                'op_panel_tactico_posts',
                'source_hash,index_status',
                "pid='{$pid}'",
                array('limit' => 1)
            );
            $indexed = $db->fetch_array($indexQuery);
            if ($indexed && hash_equals((string)$indexed['source_hash'], $hash)
                && $indexed['index_status'] === 'actualizado') {
                return true;
            }
        }
        $events = opg_pt_extract_events((string)$post['message']);
        $now = defined('TIME_NOW') ? TIME_NOW : time();
        $author = trim((string)$post['username']);
        if ($author === '') {
            $author = trim((string)$post['account_username']);
        }

        $db->write_query('START TRANSACTION');
        $db->update_query(
            'op_panel_tactico_eventos',
            array('is_current' => 0),
            "pid='{$pid}' AND is_current=1"
        );

        foreach ($events as $event) {
            $data = array(
                'tid' => (int)$post['tid'],
                'pid' => $pid,
                'uid' => (int)$post['uid'],
                'author_name' => $db->escape_string($author),
                'post_order' => (int)$event['post_order'],
                'event_type' => $db->escape_string($event['type']),
                'entity_id' => $db->escape_string($event['entity_id']),
                'data_json' => $db->escape_string(opg_pt_json_encode($event['data'])),
                'event_status' => $db->escape_string($event['status']),
                'visibility_scope' => 'publico',
                'visibility_ref' => 0,
                'source_start' => (int)$event['source_start'],
                'source_end' => (int)$event['source_end'],
                'source_hash' => $hash,
                'post_dateline' => (int)$post['dateline'],
                'is_current' => 1,
                'indexed_at' => $now,
            );
            $eventWhere = "pid='{$pid}' AND source_hash='" . $db->escape_string($hash)
                . "' AND post_order='" . (int)$event['post_order'] . "'";
            opg_pt_upsert('op_panel_tactico_eventos', $data, $eventWhere, 'id');
        }

        $postData = array(
            'pid' => $pid,
            'tid' => (int)$post['tid'],
            'uid' => (int)$post['uid'],
            'source_hash' => $hash,
            'event_count' => count($events),
            'index_status' => 'actualizado',
            'error_message' => '',
            'indexed_at' => $now,
        );
        opg_pt_upsert('op_panel_tactico_posts', $postData, "pid='{$pid}'", 'pid');
        $db->write_query('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->write_query('ROLLBACK');
        $now = defined('TIME_NOW') ? TIME_NOW : time();
        opg_pt_upsert('op_panel_tactico_posts', array(
            'pid' => $pid,
            'tid' => (int)$post['tid'],
            'uid' => (int)$post['uid'],
            'source_hash' => hash('sha256', (string)$post['message']),
            'event_count' => 0,
            'index_status' => 'error',
            'error_message' => $db->escape_string(substr($e->getMessage(), 0, 500)),
            'indexed_at' => $now,
        ), "pid='{$pid}'", 'pid');
        error_log('[Panel tactico] No se pudo indexar PID ' . $pid . ': ' . $e->getMessage());
        return false;
    }
}

function opg_pt_index_thread_batch($tid, $afterPid, $limit)
{
    global $db;
    $tid = (int)$tid;
    $afterPid = max(0, (int)$afterPid);
    $limit = max(1, min(100, (int)$limit));
    $pids = array();
    $query = $db->simple_select(
        'posts',
        'pid',
        "tid='{$tid}' AND pid>'{$afterPid}'",
        array('order_by' => 'pid', 'order_dir' => 'ASC', 'limit' => $limit)
    );
    while ($pid = $db->fetch_field($query, 'pid')) {
        $pids[] = (int)$pid;
    }
    $processed = 0;
    foreach ($pids as $pid) {
        if (opg_pt_index_post($pid)) {
            $processed++;
        }
    }
    return array(
        'processed' => $processed,
        'last_pid' => empty($pids) ? $afterPid : max($pids),
        'has_more' => count($pids) === $limit,
    );
}

function opg_pt_thread_access($thread)
{
    global $mybb;
    if (!$thread || empty($thread['tid']) || empty($thread['fid'])) {
        return false;
    }
    $permissions = forum_permissions((int)$thread['fid']);
    if (empty($permissions['canview']) || empty($permissions['canviewthreads'])) {
        return false;
    }
    if (!empty($permissions['canonlyviewownthreads']) && (int)$thread['uid'] !== (int)$mybb->user['uid']) {
        return false;
    }

    $visible = (int)$thread['visible'];
    if ($visible === 1) {
        return true;
    }
    if ($visible === 0) {
        if (is_moderator((int)$thread['fid'], 'canviewunapprove')) {
            return true;
        }
        return !empty($mybb->user['uid'])
            && !empty($mybb->settings['showownunapproved'])
            && (int)$thread['uid'] === (int)$mybb->user['uid'];
    }
    if ($visible === -1) {
        return is_moderator((int)$thread['fid'], 'canviewdeleted');
    }
    return false;
}

function opg_pt_visible_posts_sql($fid, $alias)
{
    global $mybb;
    $prefix = $alias === '' ? '' : rtrim($alias, '.') . '.';
    $states = array(1);
    if (is_moderator((int)$fid, 'canviewunapprove')) {
        $states[] = 0;
    }
    if (is_moderator((int)$fid, 'canviewdeleted')) {
        $states[] = -1;
    }
    $parts = array($prefix . 'visible IN (' . implode(',', array_unique($states)) . ')');
    if (!in_array(0, $states, true) && !empty($mybb->user['uid']) && !empty($mybb->settings['showownunapproved'])) {
        $parts[] = '(' . $prefix . 'visible=0 AND ' . $prefix . 'uid=' . (int)$mybb->user['uid'] . ')';
    }
    return '(' . implode(' OR ', $parts) . ')';
}

function opg_pt_can_reindex($fid)
{
    global $mybb;
    return !empty($mybb->user['uid']) && (
        is_moderator((int)$fid, 'caneditposts')
        || (function_exists('is_staff') && is_staff((int)$mybb->user['uid']))
    );
}

function opg_pt_event_label($type)
{
    $labels = array(
        'tecnica' => 'Tecnica registrada',
        'objeto_mostrado' => 'Objeto mostrado',
        'recurso' => 'Recursos declarados',
    );
    return isset($labels[$type]) ? $labels[$type] : 'Evento mecanico';
}

function opg_pt_event_icon($type)
{
    $icons = array(
        'tecnica' => 'fa-bolt',
        'objeto_mostrado' => 'fa-box-open',
        'recurso' => 'fa-gauge-high',
    );
    return isset($icons[$type]) ? $icons[$type] : 'fa-circle-dot';
}

function opg_pt_decode_event($row)
{
    $row['data'] = json_decode((string)$row['data_json'], true);
    if (!is_array($row['data'])) {
        $row['data'] = array();
    }
    return $row;
}

function opg_pt_event_summary($row)
{
    $data = isset($row['data']) ? $row['data'] : array();
    if ($row['event_type'] === 'recurso') {
        return '<span class="pt-resource pt-resource--vida"><i class="fa-solid fa-heart"></i> ' . (int)($data['vida'] ?? 0) . '</span>'
            . '<span class="pt-resource pt-resource--energia"><i class="fa-solid fa-bolt"></i> ' . (int)($data['energia'] ?? 0) . '</span>'
            . '<span class="pt-resource pt-resource--haki"><i class="fa-solid fa-eye"></i> ' . (int)($data['haki'] ?? 0) . '</span>';
    }
    $name = isset($data['nombre_snapshot']) ? $data['nombre_snapshot'] : $row['entity_id'];
    $meta = array();
    if (!empty($data['tier_snapshot'])) {
        $meta[] = 'Tier ' . (int)$data['tier_snapshot'];
    }
    if ($row['event_type'] === 'tecnica') {
        $costs = array();
        if (!empty($data['energia_snapshot'])) $costs[] = (int)$data['energia_snapshot'] . ' energia';
        if (!empty($data['energia_turno_snapshot'])) $costs[] = (int)$data['energia_turno_snapshot'] . ' energia/turno';
        if (!empty($data['haki_snapshot'])) $costs[] = (int)$data['haki_snapshot'] . ' haki';
        if (!empty($data['haki_turno_snapshot'])) $costs[] = (int)$data['haki_turno_snapshot'] . ' haki/turno';
        if (!empty($costs)) $meta[] = 'Coste teorico: ' . implode(' + ', $costs);
    } elseif (!empty($data['subcategoria_snapshot'])) {
        $meta[] = $data['subcategoria_snapshot'];
    }
    return '<strong>' . opg_pt_escape($name) . '</strong>'
        . (!empty($meta) ? '<small>' . opg_pt_escape(implode(' · ', $meta)) . '</small>' : '');
}
