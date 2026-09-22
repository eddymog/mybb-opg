<?php
/**
 * OPG - Solicitudes de creacion: consultas y reglas compartidas.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

if (!defined('OP_SOLICITUDES_CREACION_CATEGORIA_FID')) {
    define('OP_SOLICITUDES_CREACION_CATEGORIA_FID', 8);
}

if (!defined('OP_SOLICITUDES_CREACION_PERFILES')) {
    define('OP_SOLICITUDES_CREACION_PERFILES', array(
        369 => array('nombre' => 'Técnicas', 'completadas' => 447, 'canceladas' => 446),
        373 => array('nombre' => 'Akuma no Mi', 'completadas' => 448, 'canceladas' => 449),
        370 => array('nombre' => 'Estilos y Pasivas', 'completadas' => 450, 'canceladas' => 451),
        371 => array('nombre' => 'Objetos y Crafteos', 'completadas' => 452, 'canceladas' => 453),
        372 => array('nombre' => 'Mascotas y NPC', 'completadas' => 454, 'canceladas' => 455),
    ));
}

function op_solicitudes_creacion_fids_perfiles()
{
    return array_map('intval', array_keys(OP_SOLICITUDES_CREACION_PERFILES));
}

function op_solicitudes_creacion_perfil_de_fid($fid)
{
    $fid = (int)$fid;
    if (!isset(OP_SOLICITUDES_CREACION_PERFILES[$fid])) {
        return null;
    }

    return OP_SOLICITUDES_CREACION_PERFILES[$fid] + array('fid' => $fid);
}

function op_solicitudes_creacion_fid_destino($fidOrigen, $accion)
{
    $perfil = op_solicitudes_creacion_perfil_de_fid($fidOrigen);
    if (!$perfil) {
        return null;
    }

    if ($accion === 'completar') {
        return (int)$perfil['completadas'];
    }
    if ($accion === 'cancelar') {
        return (int)$perfil['canceladas'];
    }

    return null;
}

function op_solicitudes_creacion_es_staff($uid)
{
    $uid = (int)$uid;
    if ($uid <= 0) {
        return false;
    }

    if (!function_exists('is_staff')) {
        require_once MYBB_ROOT . 'op/functions/op_functions.php';
    }

    return is_staff($uid) || is_mod($uid) || is_user($uid);
}

function op_solicitudes_creacion_lista_sql(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    return empty($ids) ? '0' : implode(',', $ids);
}

function op_solicitudes_creacion_contar_pendientes()
{
    global $db;

    $fids = op_solicitudes_creacion_lista_sql(op_solicitudes_creacion_fids_perfiles());
    $query = $db->query("SELECT
            SUM(CASE WHEN replies=0 THEN 1 ELSE 0 END) AS sin_responder,
            SUM(CASE WHEN replies>0 THEN 1 ELSE 0 END) AS reabiertas
        FROM " . TABLE_PREFIX . "threads
        WHERE fid IN ({$fids})
          AND closed=0
          AND uid=lastposteruid
          AND visible='1'");
    $row = $db->fetch_array($query);

    return array(
        'sin_responder' => (int)($row['sin_responder'] ?? 0),
        'reabiertas' => (int)($row['reabiertas'] ?? 0),
    );
}

function op_solicitudes_creacion_distribucion_pendientes()
{
    global $db;

    $resultado = array();
    foreach (op_solicitudes_creacion_fids_perfiles() as $fid) {
        $resultado[$fid] = array('sin_respuesta' => 0, 'usuario_respondio' => 0);
    }
    $fids = op_solicitudes_creacion_lista_sql(array_keys($resultado));
    $query = $db->query("SELECT fid,
            SUM(CASE WHEN replies=0 THEN 1 ELSE 0 END) AS sin_respuesta,
            SUM(CASE WHEN replies>0 THEN 1 ELSE 0 END) AS usuario_respondio
        FROM " . TABLE_PREFIX . "threads
        WHERE fid IN ({$fids})
          AND closed=0
          AND uid=lastposteruid
          AND visible='1'
        GROUP BY fid");
    while ($row = $db->fetch_array($query)) {
        $fid = (int)$row['fid'];
        if (array_key_exists($fid, $resultado)) {
            $resultado[$fid] = array(
                'sin_respuesta' => (int)$row['sin_respuesta'],
                'usuario_respondio' => (int)$row['usuario_respondio'],
            );
        }
    }

    return $resultado;
}

function op_solicitudes_creacion_listar_pendientes($filtroFid = null)
{
    global $db;

    $fids = op_solicitudes_creacion_fids_perfiles();
    $filtroFid = $filtroFid === null ? null : (int)$filtroFid;
    if ($filtroFid !== null && !in_array($filtroFid, $fids, true)) {
        $filtroFid = null;
    }

    $where = 'fid IN (' . op_solicitudes_creacion_lista_sql($fids) . ") AND closed=0 AND visible='1'";
    if ($filtroFid !== null) {
        $where .= " AND fid='{$filtroFid}'";
    }

    $resultado = array(
        'sin_respuesta' => array(),
        'reabiertas' => array(),
        'esperando_usuario' => array(),
    );
    $query = $db->simple_select(
        'threads',
        'tid,subject,fid,uid,username,lastpost,lastposteruid,replies',
        $where,
        array('order_by' => 'lastpost', 'order_dir' => 'ASC')
    );

    while ($tema = $db->fetch_array($query)) {
        $tema['tid'] = (int)$tema['tid'];
        $tema['fid'] = (int)$tema['fid'];
        $tema['uid'] = (int)$tema['uid'];
        $tema['lastposteruid'] = (int)$tema['lastposteruid'];
        $tema['replies'] = (int)$tema['replies'];
        $tema['perfil'] = op_solicitudes_creacion_perfil_de_fid($tema['fid']);

        if ($tema['uid'] !== $tema['lastposteruid']) {
            $resultado['esperando_usuario'][] = $tema;
        } elseif ($tema['replies'] === 0) {
            $resultado['sin_respuesta'][] = $tema;
        } else {
            $resultado['reabiertas'][] = $tema;
        }
    }

    usort($resultado['esperando_usuario'], function ($a, $b) {
        return (int)$b['lastpost'] <=> (int)$a['lastpost'];
    });

    return $resultado;
}

function op_solicitudes_creacion_cargar_posteadores(array $tids, array $autoresPorTid = array())
{
    global $db;

    $tids = array_values(array_unique(array_filter(array_map('intval', $tids))));
    $resultado = array();
    foreach ($tids as $tid) {
        $resultado[$tid] = array();
    }
    if (empty($tids)) {
        return $resultado;
    }

    $query = $db->query("SELECT tid,uid,MAX(username) AS username,MIN(dateline) AS first_post
        FROM " . TABLE_PREFIX . "posts
        WHERE tid IN (" . implode(',', $tids) . ")
          AND uid!='0'
          AND visible='1'
        GROUP BY tid,uid
        ORDER BY first_post ASC");
    while ($post = $db->fetch_array($query)) {
        $tid = (int)$post['tid'];
        $uid = (int)$post['uid'];
        if ($uid <= 0 || $uid === (int)($autoresPorTid[$tid] ?? 0)) {
            continue;
        }
        $resultado[$tid][] = array(
            'uid' => $uid,
            'username' => (string)$post['username'],
        );
    }

    return $resultado;
}

function op_solicitudes_creacion_estadisticas()
{
    global $db;

    $fidsArchivo = array();
    $resultado = array();
    foreach (OP_SOLICITUDES_CREACION_PERFILES as $fid => $perfil) {
        $fid = (int)$fid;
        $fidsArchivo[] = (int)$perfil['completadas'];
        $fidsArchivo[] = (int)$perfil['canceladas'];
        $resultado[$fid] = array('completadas' => 0, 'canceladas' => 0);
    }

    $totalesPorFid = array();
    $query = $db->simple_select(
        'threads',
        'fid,COUNT(*) AS total',
        'fid IN (' . op_solicitudes_creacion_lista_sql($fidsArchivo) . ')',
        array('group_by' => 'fid')
    );
    while ($row = $db->fetch_array($query)) {
        $totalesPorFid[(int)$row['fid']] = (int)$row['total'];
    }

    foreach (OP_SOLICITUDES_CREACION_PERFILES as $fid => $perfil) {
        $resultado[(int)$fid] = array(
            'completadas' => (int)($totalesPorFid[(int)$perfil['completadas']] ?? 0),
            'canceladas' => (int)($totalesPorFid[(int)$perfil['canceladas']] ?? 0),
        );
    }

    return $resultado;
}

function op_solicitudes_creacion_actividad_moderadores()
{
    global $db;

    if (!$db->table_exists('op_solicitudes_creacion_resoluciones')) {
        return array();
    }

    $fids = op_solicitudes_creacion_fids_perfiles();
    $query = $db->query("SELECT r.moderador_uid, MAX(u.username) AS username,
            r.perfil_fid, r.accion, COUNT(*) AS total
        FROM " . TABLE_PREFIX . "op_solicitudes_creacion_resoluciones r
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid=r.moderador_uid
        WHERE r.perfil_fid IN (" . op_solicitudes_creacion_lista_sql($fids) . ")
        GROUP BY r.moderador_uid, r.perfil_fid, r.accion");

    $resultado = array();
    while ($row = $db->fetch_array($query)) {
        $uid = (int)$row['moderador_uid'];
        $fid = (int)$row['perfil_fid'];
        $total = (int)$row['total'];
        if (!isset($resultado[$uid])) {
            $resultado[$uid] = array(
                'uid' => $uid,
                'username' => (string)($row['username'] ?? ''),
                'total' => 0,
                'completadas' => 0,
                'canceladas' => 0,
                'perfiles' => array_fill_keys($fids, 0),
            );
        }
        $resultado[$uid]['total'] += $total;
        if ($row['accion'] === 'completar') {
            $resultado[$uid]['completadas'] += $total;
        } elseif ($row['accion'] === 'cancelar') {
            $resultado[$uid]['canceladas'] += $total;
        }
        if (isset($resultado[$uid]['perfiles'][$fid])) {
            $resultado[$uid]['perfiles'][$fid] += $total;
        }
    }

    $resultado = array_values($resultado);
    usort($resultado, function ($a, $b) {
        if ($a['total'] !== $b['total']) {
            return $b['total'] <=> $a['total'];
        }
        if ($a['completadas'] !== $b['completadas']) {
            return $b['completadas'] <=> $a['completadas'];
        }
        return strcasecmp($a['username'], $b['username']);
    });

    return $resultado;
}

function op_solicitudes_creacion_resolver($tid, $accion, $moderadorUid)
{
    global $db;

    $tid = (int)$tid;
    $moderadorUid = (int)$moderadorUid;
    if ($tid <= 0 || $moderadorUid <= 0 || !in_array($accion, array('completar', 'cancelar'), true)) {
        return array('ok' => false, 'code' => 'solicitud_invalida');
    }
    if (!$db->table_exists('op_solicitudes_creacion_resoluciones')) {
        return array('ok' => false, 'code' => 'registro_no_disponible');
    }

    $query = $db->simple_select(
        'threads',
        'tid,fid,uid,lastposteruid,closed,visible',
        "tid='{$tid}'",
        array('limit' => 1)
    );
    $tema = $db->fetch_array($query);
    if (!$tema) {
        return array('ok' => false, 'code' => 'solicitud_invalida');
    }

    $fidOrigen = (int)$tema['fid'];
    if (!op_solicitudes_creacion_perfil_de_fid($fidOrigen)) {
        return array('ok' => false, 'code' => 'perfil_invalido');
    }
    if ((int)$tema['closed'] !== 0 || (int)$tema['visible'] !== 1) {
        return array('ok' => false, 'code' => 'estado_invalido');
    }
    $fidDestino = op_solicitudes_creacion_fid_destino($fidOrigen, $accion);
    if (!$fidDestino) {
        return array('ok' => false, 'code' => 'destino_invalido');
    }

    $db->update_query(
        'threads',
        array('fid' => (int)$fidDestino, 'closed' => '1'),
        "tid='{$tid}' AND fid='{$fidOrigen}' AND closed=0 AND visible='1'"
    );
    if ($db->affected_rows() === 0) {
        return array('ok' => false, 'code' => 'solicitud_actualizada');
    }

    $db->update_query('posts', array('fid' => (int)$fidDestino), "tid='{$tid}' AND fid='{$fidOrigen}'");
    $db->replace_query('op_solicitudes_creacion_resoluciones', array(
        'tid' => $tid,
        'perfil_fid' => $fidOrigen,
        'accion' => $db->escape_string($accion),
        'moderador_uid' => $moderadorUid,
        'resuelto_en' => TIME_NOW,
    ));

    require_once MYBB_ROOT . 'inc/functions_rebuild.php';
    rebuild_forum_counters($fidOrigen);
    rebuild_forum_counters((int)$fidDestino);

    return array('ok' => true, 'code' => $accion === 'completar' ? 'completada' : 'cancelada');
}
