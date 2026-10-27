<?php
/** Halloween 2026: consultas de estadisticas e historiales. */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';

function opg_hlw_stats_summary()
{
    global $db;
    $empty = array(
        'earned' => 0, 'spent' => 0, 'circulation' => 0, 'participants' => 0,
        'posts' => 0, 'threads' => 0, 'spooky' => 0, 'daily' => 0, 'purchases' => 0, 'secrets' => 0,
    );
    if (!opg_hlw_tables_ready()) {
        return $empty;
    }
    $balances = opg_hlw_table('saldos');
    $query = $db->write_query("SELECT COALESCE(SUM(ganado_total),0) earned,
        COALESCE(SUM(gastado_total),0) spent,COALESCE(SUM(saldo),0) circulation,
        SUM(ganado_total>0) participants FROM `{$balances}`");
    $summary = array_merge($empty, $db->fetch_array($query) ?: array());
    $movements = opg_hlw_table('movimientos');
    $query = $db->write_query("SELECT m.source_type,COUNT(*) total FROM `{$movements}` m
        LEFT JOIN `{$movements}` r ON r.reverses_movement_id=m.id
        WHERE m.amount>0 AND m.bucket='earned'
          AND m.source_type IN ('post','thread','spooky','daily') AND r.id IS NULL
        GROUP BY m.source_type");
    while ($row = $db->fetch_array($query)) {
        if ($row['source_type'] === 'post') $summary['posts'] = (int)$row['total'];
        if ($row['source_type'] === 'thread') $summary['threads'] = (int)$row['total'];
        if ($row['source_type'] === 'spooky') $summary['spooky'] = (int)$row['total'];
        if ($row['source_type'] === 'daily') $summary['daily'] = (int)$row['total'];
    }
    if ($db->table_exists('op_halloween2026_compras')) {
        $query = $db->simple_select('op_halloween2026_compras', 'COUNT(*) total', "estado='completed'");
        $summary['purchases'] = (int)$db->fetch_field($query, 'total');
    }
    // Secretos descubiertos (cada descubrimiento de cada usuario). Sus Calabazas ya entran en 'earned' y en el ranking,
    // porque se pagan por el mismo libro de movimientos que los posts.
    if (opg_hlw_table_exists('secretos')) {
        $query = $db->simple_select('op_halloween2026_secretos', 'COUNT(*) total');
        $summary['secrets'] = (int)$db->fetch_field($query, 'total');
    }
    return array_map('intval', $summary);
}

function opg_hlw_stats_store_ready()
{
    foreach (array('tienda_items', 'compras', 'compra_lineas') as $table) {
        if (!opg_hlw_table_exists($table)) {
            return false;
        }
    }
    return true;
}

/**
 * Estadisticas de la tienda (solo compras completadas):
 *  purchases : compras completadas
 *  spent     : Calabazas gastadas en ellas
 *  chests    : Cofres Calabaza vendidos (articulo con slug 'cofre-calabaza')
 *  items     : articulos mas vendidos (nombre, vendidos)
 *  buyers    : quien ha comprado mas Cofres Calabaza (uid, nombre, total)
 */
function opg_hlw_stats_store($limit = 10)
{
    global $db;
    $stats = array('purchases' => 0, 'spent' => 0, 'chests' => 0, 'items' => array(), 'buyers' => array());
    if (!opg_hlw_stats_store_ready()) {
        return $stats;
    }
    $limit = max(1, min(50, (int)$limit));
    $purchases = opg_hlw_table('compras');
    $lines = opg_hlw_table('compra_lineas');
    $items = opg_hlw_table('tienda_items');
    $users = $db->table_prefix . 'users';
    $fichas = $db->table_prefix . 'op_fichas';

    $query = $db->write_query("SELECT COUNT(*) AS purchases, COALESCE(SUM(total),0) AS spent FROM `{$purchases}` WHERE estado='completed'");
    $row = $db->fetch_array($query) ?: array();
    $stats['purchases'] = (int)($row['purchases'] ?? 0);
    $stats['spent'] = (int)($row['spent'] ?? 0);

    $query = $db->write_query("SELECT i.nombre, SUM(l.cantidad) AS vendidos
        FROM `{$lines}` l
        INNER JOIN `{$purchases}` p ON p.id=l.purchase_id AND p.estado='completed'
        INNER JOIN `{$items}` i ON i.id=l.item_id
        GROUP BY i.id, i.nombre ORDER BY vendidos DESC, i.nombre ASC LIMIT 5");
    while ($row = $db->fetch_array($query)) {
        $stats['items'][] = array('nombre' => (string)$row['nombre'], 'vendidos' => (int)$row['vendidos']);
    }

    $query = $db->write_query("SELECT p.uid, SUM(l.cantidad) AS total, MAX(p.completed_at) AS ultimo,
            COALESCE(NULLIF(f.nombre,''), u.username) AS nombre
        FROM `{$lines}` l
        INNER JOIN `{$items}` i ON i.id=l.item_id AND i.slug='cofre-calabaza'
        INNER JOIN `{$purchases}` p ON p.id=l.purchase_id AND p.estado='completed'
        LEFT JOIN `{$users}` u ON u.uid=p.uid
        LEFT JOIN `{$fichas}` f ON f.fid=p.uid
        GROUP BY p.uid, f.nombre, u.username
        ORDER BY total DESC, ultimo ASC, p.uid ASC LIMIT {$limit}");
    while ($row = $db->fetch_array($query)) {
        $stats['buyers'][] = array('uid' => (int)$row['uid'], 'nombre' => (string)$row['nombre'], 'total' => (int)$row['total']);
    }
    // El total de cofres vendidos incluye a todos los compradores, no solo a los del ranking.
    $query = $db->write_query("SELECT COALESCE(SUM(l.cantidad),0) AS total
        FROM `{$lines}` l
        INNER JOIN `{$items}` i ON i.id=l.item_id AND i.slug='cofre-calabaza'
        INNER JOIN `{$purchases}` p ON p.id=l.purchase_id AND p.estado='completed'");
    $stats['chests'] = (int)$db->fetch_field($query, 'total');
    return $stats;
}

function opg_hlw_stats_ranking($page = 1, $perPage = 20)
{
    global $db;
    $page = max(1, (int)$page);
    $perPage = max(1, min(50, (int)$perPage));
    $offset = ($page - 1) * $perPage;
    $balances = opg_hlw_table('saldos');
    $fichas = $db->table_prefix . 'op_fichas';
    $users = $db->table_prefix . 'users';
    $query = $db->write_query("SELECT s.*,f.nombre,u.username
        FROM `{$balances}` s
        LEFT JOIN `{$fichas}` f ON f.fid=s.uid
        LEFT JOIN `{$users}` u ON u.uid=s.uid
        WHERE s.ganado_total>0
        ORDER BY s.ganado_total DESC,s.fecha_ultimo_total ASC,s.uid ASC
        LIMIT {$offset},{$perPage}");
    $rows = array();
    $position = $offset + 1;
    while ($row = $db->fetch_array($query)) {
        $row['position'] = $position++;
        $rows[] = $row;
    }
    $query = $db->write_query("SELECT COUNT(*) total FROM `{$balances}` WHERE ganado_total>0");
    return array('rows' => $rows, 'total' => (int)$db->fetch_field($query, 'total'), 'page' => $page, 'per_page' => $perPage);
}

function opg_hlw_stats_user_position($uid)
{
    global $db;
    $uid = (int)$uid;
    if ($uid <= 0 || !opg_hlw_tables_ready()) {
        return null;
    }
    $balances = opg_hlw_table('saldos');
    $query = $db->simple_select('op_halloween2026_saldos', '*', "uid='{$uid}' AND ganado_total>0", array('limit' => 1));
    $row = $db->fetch_array($query);
    if (!$row) {
        return null;
    }
    $earned = (int)$row['ganado_total'];
    $lastTotal = $db->escape_string((string)$row['fecha_ultimo_total']);
    $query = $db->write_query("SELECT COUNT(*) total FROM `{$balances}`
        WHERE ganado_total>'{$earned}' OR (ganado_total='{$earned}' AND (
            COALESCE(fecha_ultimo_total,'0000-00-00 00:00:00')<COALESCE(NULLIF('{$lastTotal}',''),'0000-00-00 00:00:00')
            OR (COALESCE(fecha_ultimo_total,'0000-00-00 00:00:00')=COALESCE(NULLIF('{$lastTotal}',''),'0000-00-00 00:00:00') AND uid<'{$uid}')
        ))");
    return array(
        'position' => 1 + (int)$db->fetch_field($query, 'total'),
        'earned' => $earned,
        'balance' => (int)$row['saldo'],
    );
}

function opg_hlw_stats_activity($uid, $page = 1, $perPage = 25)
{
    global $db;
    $uid = (int)$uid;
    $page = max(1, (int)$page);
    $perPage = max(1, min(50, (int)$perPage));
    $offset = ($page - 1) * $perPage;
    $query = $db->simple_select('op_halloween2026_movimientos', '*', "uid='{$uid}'", array(
        'order_by' => 'id', 'order_dir' => 'DESC', 'limit_start' => $offset, 'limit' => $perPage,
    ));
    $rows = array();
    while ($row = $db->fetch_array($query)) {
        $rows[] = $row;
    }
    $query = $db->simple_select('op_halloween2026_movimientos', 'COUNT(*) total', "uid='{$uid}'");
    return array('rows' => $rows, 'total' => (int)$db->fetch_field($query, 'total'), 'page' => $page, 'per_page' => $perPage);
}

function opg_hlw_stats_source_label($type)
{
    $labels = array(
        'post' => 'Post de rol', 'thread' => 'Tema nuevo', 'spooky' => 'Tema Spooky',
        'daily' => 'Primer post diario', 'secret' => 'Secreto descubierto', 'preset' => 'Premio del evento',
        'gift' => 'Regalo de Kuro', 'manual' => 'Ajuste de staff',
        'purchase' => 'Compra en la tienda', 'reversal' => 'Correccion',
    );
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', (string)$type));
}

/** Expensive read-only audit. It only runs when staff explicitly requests it. */
function opg_hlw_stats_audit_preview()
{
    global $db;
    $config = opg_hlw_config();
    $start = strtotime($config['earn_start_utc'] . ' UTC');
    $end = strtotime($config['earn_end_utc'] . ' UTC');
    $root = (int)($config['settings']['role_root_fid'] ?? 10);
    $posts = $db->table_prefix . 'posts';
    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $fichas = $db->table_prefix . 'op_fichas';
    $movements = opg_hlw_table('movimientos');
    $base = " FROM `{$posts}` p INNER JOIN `{$threads}` t ON t.tid=p.tid"
        . " INNER JOIN `{$forums}` f ON f.fid=t.fid INNER JOIN `{$fichas}` c ON c.fid=p.uid"
        . " WHERE p.dateline>='{$start}' AND p.dateline<'{$end}' AND p.visible=1 AND t.visible=1"
        . " AND (t.fid='{$root}' OR FIND_IN_SET('{$root}',f.parentlist)>0)"
        . " AND c.aprobada_por NOT IN ('sin_aprobar','pendiente_reset') AND (c.muerto IS NULL OR c.muerto='0')";
    // El primer post de cada tema paga como tema nuevo, no como post.
    $query = $db->write_query('SELECT COUNT(*) total' . $base . ' AND p.pid<>t.firstpost');
    $expectedPosts = (int)$db->fetch_field($query, 'total');
    $query = $db->write_query("SELECT COUNT(DISTINCT p.pid) total{$base}
        AND p.pid<>t.firstpost
        AND EXISTS (SELECT 1 FROM `{$movements}` m LEFT JOIN `{$movements}` r ON r.reverses_movement_id=m.id
            WHERE m.source_pid=p.pid AND m.source_type='post' AND m.amount>0 AND r.id IS NULL)");
    $rewardedPosts = (int)$db->fetch_field($query, 'total');
    $query = $db->write_query("SELECT COUNT(*) total{$base}
        AND p.pid=t.firstpost");
    $expectedThreads = (int)$db->fetch_field($query, 'total');
    $query = $db->write_query("SELECT COUNT(DISTINCT t.tid) total{$base}
        AND p.pid=t.firstpost
        AND EXISTS (SELECT 1 FROM `{$movements}` m LEFT JOIN `{$movements}` r ON r.reverses_movement_id=m.id
            WHERE m.source_tid=t.tid AND m.source_type IN ('thread','spooky') AND m.amount>0 AND r.id IS NULL)");
    $rewardedThreads = (int)$db->fetch_field($query, 'total');
    return array(
        'expected_posts' => $expectedPosts,
        'rewarded_posts' => $rewardedPosts,
        'missing_posts' => max(0, $expectedPosts - $rewardedPosts),
        'expected_threads' => $expectedThreads,
        'rewarded_threads' => $rewardedThreads,
        'missing_threads' => max(0, $expectedThreads - $rewardedThreads),
    );
}
