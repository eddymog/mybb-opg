<?php
/** Halloween 2026: catalogo y compras transaccionales. */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';

function opg_hlw_store_tables_ready()
{
    global $db;
    foreach (array('tienda_items', 'compras', 'compra_lineas') as $table) {
        if (!$db->table_exists('op_halloween2026_' . $table)) {
            return false;
        }
    }
    return opg_hlw_tables_ready();
}

function opg_hlw_store_items($include_inactive = false)
{
    global $db;
    if (!opg_hlw_store_tables_ready()) {
        return array();
    }
    $where = $include_inactive ? '1=1' : "activo='1'";
    $now = opg_hlw_utc_now();
    $where .= " AND (starts_at_utc IS NULL OR starts_at_utc<='{$now}')"
        . " AND (ends_at_utc IS NULL OR ends_at_utc>'{$now}')";
    $query = $db->simple_select('op_halloween2026_tienda_items', '*', $where, array(
        'order_by' => 'orden,id',
        'order_dir' => 'ASC',
    ));
    $items = array();
    while ($row = $db->fetch_array($query)) {
        $row['reward'] = json_decode((string)$row['reward_json'], true) ?: array();
        $items[] = $row;
    }
    return $items;
}

function opg_hlw_purchase_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(16));
    }
    return sha1(uniqid((string)mt_rand(), true));
}

function opg_hlw_store_parse_quantities($input)
{
    $result = array();
    if (!is_array($input)) {
        return $result;
    }
    foreach ($input as $itemId => $quantity) {
        $itemId = (int)$itemId;
        $quantity = (int)$quantity;
        if ($itemId > 0 && $quantity > 0) {
            $result[$itemId] = min(100, $quantity);
        }
    }
    return $result;
}

function opg_hlw_store_purchase($uid, $quantities, $token)
{
    global $db;
    $uid = (int)$uid;
    $quantities = opg_hlw_store_parse_quantities($quantities);
    $token = preg_replace('/[^a-zA-Z0-9:_-]/', '', (string)$token);
    if ($uid <= 0 || empty($quantities) || $token === '' || count($quantities) > 20) {
        return array('ok' => false, 'code' => 'invalid_cart');
    }
    if (!opg_hlw_store_is_open()) {
        return array('ok' => false, 'code' => 'store_closed');
    }

    $key = 'halloween2026:purchase:' . $uid . ':' . substr($token, 0, 120);
    $keySql = $db->escape_string($key);
    $query = $db->simple_select('op_halloween2026_compras', 'id,estado', "idempotency_key='{$keySql}'", array('limit' => 1));
    $existing = $db->fetch_array($query);
    if ($existing) {
        return array('ok' => $existing['estado'] === 'completed', 'duplicate' => true, 'purchase_id' => (int)$existing['id']);
    }

    $db->write_query('START TRANSACTION');
    try {
        $ficha = opg_hlw_eligible_character($uid, true);
        if (!$ficha) {
            throw new RuntimeException('not_eligible');
        }
        opg_hlw_ensure_balance_row($uid);
        $balances = opg_hlw_table('saldos');
        $query = $db->write_query("SELECT * FROM `{$balances}` WHERE uid='{$uid}' FOR UPDATE");
        $balance = $db->fetch_array($query);

        $ids = array_keys($quantities);
        $itemsTable = opg_hlw_table('tienda_items');
        $query = $db->write_query("SELECT * FROM `{$itemsTable}` WHERE id IN (" . implode(',', $ids) . ") FOR UPDATE");
        $items = array();
        while ($row = $db->fetch_array($query)) {
            $items[(int)$row['id']] = $row;
        }
        if (count($items) !== count($quantities)) {
            throw new RuntimeException('item_unavailable');
        }

        $now = opg_hlw_utc_now();
        $total = 0;
        $rewards = array('objects' => array(), 'nikas' => 0, 'berries' => 0, 'craft_points' => 0);
        foreach ($quantities as $itemId => $quantity) {
            $item = $items[$itemId];
            if ((int)$item['activo'] !== 1
                || (!empty($item['starts_at_utc']) && $item['starts_at_utc'] > $now)
                || (!empty($item['ends_at_utc']) && $item['ends_at_utc'] <= $now)) {
                throw new RuntimeException('item_unavailable');
            }
            if ($item['stock_global'] !== null
                && (int)$item['stock_vendido'] + $quantity > (int)$item['stock_global']) {
                throw new RuntimeException('stock_unavailable');
            }
            $lineTotal = (int)$item['coste'] * $quantity;
            $total += $lineTotal;
            if ($total > 1000000) {
                throw new RuntimeException('cart_too_large');
            }
            $reward = json_decode((string)$item['reward_json'], true);
            if (!is_array($reward)) {
                throw new RuntimeException('invalid_reward');
            }
            $rewards['nikas'] += (int)($reward['nikas'] ?? 0) * $quantity;
            $rewards['berries'] += (int)($reward['berries'] ?? 0) * $quantity;
            $rewards['craft_points'] += (int)($reward['craft_points'] ?? 0) * $quantity;
            foreach ((array)($reward['objects'] ?? array()) as $object) {
                $objectId = trim((string)($object['id'] ?? ''));
                $objectQuantity = (int)($object['quantity'] ?? 0) * $quantity;
                if ($objectId === '' || $objectQuantity <= 0) {
                    throw new RuntimeException('invalid_reward');
                }
                $rewards['objects'][$objectId] = ($rewards['objects'][$objectId] ?? 0) + $objectQuantity;
            }
        }
        if ($total <= 0 || (int)$balance['saldo'] < $total) {
            throw new RuntimeException('insufficient_balance');
        }

        $purchases = opg_hlw_table('compras');
        $db->write_query("INSERT INTO `{$purchases}`
            (`uid`,`total`,`estado`,`idempotency_key`,`created_at`,`updated_at`)
            VALUES ({$uid},{$total},'pending','{$keySql}','{$now}','{$now}')");
        $purchaseId = (int)$db->insert_id();
        $lines = opg_hlw_table('compra_lineas');
        foreach ($quantities as $itemId => $quantity) {
            $item = $items[$itemId];
            $name = $db->escape_string((string)$item['nombre']);
            $rewardJson = $db->escape_string((string)$item['reward_json']);
            $db->write_query("INSERT INTO `{$lines}`
                (`purchase_id`,`item_id`,`cantidad`,`precio_unitario`,`nombre_snapshot`,`reward_json`,`created_at`)
                VALUES ({$purchaseId},{$itemId},{$quantity}," . (int)$item['coste'] . ",'{$name}','{$rewardJson}','{$now}')");
            if ($item['stock_global'] !== null) {
                $db->write_query("UPDATE `{$itemsTable}` SET stock_vendido=stock_vendido+{$quantity},
                    updated_at='{$now}' WHERE id='{$itemId}'");
            }
        }

        foreach ($rewards['objects'] as $objectId => $quantity) {
            $objectSql = $db->escape_string($objectId);
            $query = $db->simple_select('op_objetos', 'objeto_id', "objeto_id='{$objectSql}'", array('limit' => 1));
            if (!$db->fetch_field($query, 'objeto_id')) {
                throw new RuntimeException('missing_object');
            }
            $inventory = $db->table_prefix . 'op_inventario';
            $db->write_query("INSERT INTO `{$inventory}` (`objeto_id`,`uid`,`cantidad`)
                VALUES ('{$objectSql}',{$uid},{$quantity})
                ON DUPLICATE KEY UPDATE cantidad=cantidad+VALUES(cantidad)");
        }

        $nikas = (int)$ficha['nika'] + $rewards['nikas'];
        $berries = (int)$ficha['berries'] + $rewards['berries'];
        $craft = (int)$ficha['puntos_oficio'] + $rewards['craft_points'];
        $fichas = $db->table_prefix . 'op_fichas';
        $db->write_query("UPDATE `{$fichas}` SET nika='{$nikas}',berries='{$berries}',puntos_oficio='{$craft}' WHERE fid='{$uid}'");

        $oldBalance = (int)$balance['saldo'];
        $newBalance = $oldBalance - $total;
        $movement = opg_hlw_add_movement(array(
            'uid' => $uid,
            'amount' => -$total,
            'bucket' => 'spent',
            'source_type' => 'purchase',
            'source_id' => (string)$purchaseId,
            'idempotency_key' => 'halloween2026:purchase-movement:' . $purchaseId,
            'note' => 'Compra de Halloween #' . $purchaseId,
            'metadata' => array(
                'rewards' => $rewards,
                'saldo_anterior' => $oldBalance,
                'saldo_final' => $newBalance,
            ),
        ), false);
        if (empty($movement['ok'])) {
            throw new RuntimeException('movement_failed');
        }

        $users = $db->table_prefix . 'users';
        $query = $db->simple_select('users', 'username', "uid='{$uid}'", array('limit' => 1));
        $username = $db->escape_string((string)$db->fetch_field($query, 'username'));
        $audit = $db->table_prefix . 'op_audit_general';
        $log = $db->escape_string("Compra Halloween #{$purchaseId}. Calabazas: {$oldBalance}->{$newBalance}. "
            . 'Recompensas: ' . json_encode($rewards, JSON_UNESCAPED_UNICODE));
        $db->write_query("INSERT INTO `{$audit}` (`uid`,`username`,`user_uid`,`categoria`,`log`)
            VALUES ({$uid},'{$username}',{$uid},'[Halloween 2026][Tienda]','{$log}')");

        $db->write_query("UPDATE `{$purchases}` SET estado='completed',updated_at='{$now}',completed_at='{$now}'
            WHERE id='{$purchaseId}'");
        $db->write_query('COMMIT');
        return array('ok' => true, 'purchase_id' => $purchaseId, 'saldo_anterior' => $oldBalance, 'saldo_final' => $newBalance);
    } catch (Exception $e) {
        $db->write_query('ROLLBACK');
        return array('ok' => false, 'code' => $e->getMessage());
    }
}

function opg_hlw_store_receipt($purchaseId, $uid = 0, $staff = false)
{
    global $db;
    $purchaseId = (int)$purchaseId;
    $where = "id='{$purchaseId}'";
    if (!$staff) {
        $where .= " AND uid='" . (int)$uid . "'";
    }
    $query = $db->simple_select('op_halloween2026_compras', '*', $where, array('limit' => 1));
    $purchase = $db->fetch_array($query);
    if (!$purchase) {
        return null;
    }
    $purchase['lines'] = array();
    $query = $db->simple_select('op_halloween2026_compra_lineas', '*', "purchase_id='{$purchaseId}'", array('order_by' => 'id'));
    while ($line = $db->fetch_array($query)) {
        $line['reward'] = json_decode((string)$line['reward_json'], true) ?: array();
        $purchase['lines'][] = $line;
    }
    return $purchase;
}
