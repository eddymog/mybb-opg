<?php
/**
 * OPG - Bitacora de rol: motor compartido.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

if (!defined('OP_TEMAS_OVERRIDE_AUTO')) {
    define('OP_TEMAS_OVERRIDE_AUTO', 'auto');
    define('OP_TEMAS_OVERRIDE_DUE', 'me_toca');
    define('OP_TEMAS_OVERRIDE_WAIT', 'no_me_toca');
}

function op_temas_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function op_temas_template_definitions()
{
    return array(
        'op_temas' => 'op_temas.html',
        'op_temas_tracker' => 'op_temas_tracker.html',
        'op_temas_header' => 'op_temas_header.html',
    );
}

function op_temas_cargar_plantilla($title)
{
    global $templates;

    $definitions = op_temas_template_definitions();
    if (!isset($definitions[$title])) {
        return '';
    }

    $path = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/' . $definitions[$title];
    if (is_file($path)) {
        $source = (string)file_get_contents($path);
        if (trim($source) !== '') {
            return str_replace("\\'", "'", addslashes($source));
        }
    }

    $template = $templates->get($title, 1, 0);
    return trim($template) !== '' ? $template : '';
}

function op_temas_override_valido($value)
{
    return in_array($value, array(
        OP_TEMAS_OVERRIDE_AUTO,
        OP_TEMAS_OVERRIDE_DUE,
        OP_TEMAS_OVERRIDE_WAIT,
    ), true);
}

function op_temas_es_redirect($closed)
{
    return strpos((string)$closed, 'moved|') === 0;
}

function op_temas_es_cerrado($closed)
{
    $closed = (string)$closed;
    return $closed !== '' && $closed !== '0' && !op_temas_es_redirect($closed);
}

function op_temas_tablas_listas()
{
    global $db;
    return $db->table_exists('op_temas_seguidos')
        && $db->table_exists('op_temas_participantes');
}

function op_temas_actualizar_esquema()
{
    global $db;
    if ($db->table_exists('op_temas_seguidos')
        && !$db->field_exists('narrador_uid', 'op_temas_seguidos')) {
        $db->add_column(
            'op_temas_seguidos',
            'narrador_uid',
            'INT UNSIGNED NOT NULL DEFAULT 0 AFTER override_estado'
        );
    }
}

function op_temas_personaje_tiene_ficha($uid)
{
    global $db;
    $uid = (int)$uid;
    if ($uid <= 0) {
        return false;
    }

    $query = $db->simple_select('op_fichas', 'fid', "fid='{$uid}'", array('limit' => 1));
    return (int)$db->fetch_field($query, 'fid') === $uid;
}

function op_temas_es_foro_rol($fid)
{
    global $db;
    $fid = (int)$fid;
    if ($fid <= 0) {
        return false;
    }

    $query = $db->simple_select('forums', 'parentlist', "fid='{$fid}'", array('limit' => 1));
    $parentlist = (string)$db->fetch_field($query, 'parentlist');
    return strpos($parentlist, '10,') === 0;
}

function op_temas_puede_ver_foro($fid)
{
    $permissions = forum_permissions((int)$fid);
    return is_array($permissions)
        && !empty($permissions['canview'])
        && !empty($permissions['canviewthreads']);
}

function op_temas_cargar_tema($tid, $comprobarPermisos = true)
{
    global $db;
    $tid = (int)$tid;
    if ($tid <= 0) {
        return false;
    }

    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $query = $db->query("SELECT t.*, f.name AS forum_name, f.parentlist
        FROM `{$threads}` t
        INNER JOIN `{$forums}` f ON f.fid = t.fid
        WHERE t.tid = {$tid}
        LIMIT 1");
    $thread = $db->fetch_array($query);

    if (!$thread || (int)$thread['visible'] !== 1 || strpos((string)$thread['parentlist'], '10,') !== 0) {
        return false;
    }
    if (op_temas_es_redirect($thread['closed'])) {
        return false;
    }
    if ($comprobarPermisos && !op_temas_puede_ver_foro((int)$thread['fid'])) {
        return false;
    }

    return $thread;
}

function op_temas_cargar_seguimiento($id, $ownerUid)
{
    global $db;
    $id = (int)$id;
    $ownerUid = (int)$ownerUid;
    if ($id <= 0 || $ownerUid <= 0) {
        return false;
    }

    $query = $db->simple_select(
        'op_temas_seguidos',
        '*',
        "id='{$id}' AND personaje_uid='{$ownerUid}'",
        array('limit' => 1)
    );
    return $db->fetch_array($query);
}

function op_temas_resolver_estado($seguimiento)
{
    if (empty($seguimiento['visible_tracker'])) {
        return 'oculto';
    }
    if (!empty($seguimiento['cerrado'])) {
        return 'cerrado';
    }

    $override = (string)($seguimiento['override_estado'] ?? OP_TEMAS_OVERRIDE_AUTO);
    $esperados = (int)($seguimiento['esperados'] ?? 0);
    $respondieron = (int)($seguimiento['respondieron'] ?? 0);
    $narradorUid = (int)($seguimiento['narrador_uid'] ?? 0);
    $completa = $narradorUid > 0
        ? !empty($seguimiento['narrador_respondio'])
        : ($esperados > 0 && $respondieron >= $esperados);

    if ($override === OP_TEMAS_OVERRIDE_DUE) {
        return 'debes_responder_manual';
    }
    if ($override === OP_TEMAS_OVERRIDE_WAIT && !$completa) {
        return 'esperando_manual';
    }
    if ($completa) {
        return 'debes_responder';
    }
    return 'esperando';
}

function op_temas_clasificar_participantes($seguimiento)
{
    $seguimiento['respondieron_lista'] = array();
    $seguimiento['pendientes'] = array();
    $seguimiento['esperados'] = 0;
    $seguimiento['respondieron'] = 0;
    $seguimiento['narrador'] = null;

    $narradorUid = (int)($seguimiento['narrador_uid'] ?? 0);
    $narradorPid = 0;
    foreach ($seguimiento['participantes'] as $participante) {
        if ((int)$participante['participante_uid'] === $narradorUid) {
            $narradorPid = (int)$participante['respuesta_pid'];
            $seguimiento['narrador'] = $participante;
            break;
        }
    }

    $seguimiento['narrador_inicio_pid'] = $narradorPid;
    $seguimiento['narrador_respondio'] = $narradorPid > 0;

    foreach ($seguimiento['participantes'] as $participante) {
        $respuestaPid = (int)$participante['respuesta_pid'];
        $participante['es_narrador'] = (int)$participante['participante_uid'] === $narradorUid;
        if ($narradorUid > 0) {
            $participante['respondio'] = $participante['es_narrador']
                ? $narradorPid > 0
                : ($narradorPid > 0 && $respuestaPid > $narradorPid);
        } else {
            $participante['respondio'] = $respuestaPid > 0;
        }

        $seguimiento['esperados']++;
        if ($participante['respondio']) {
            $seguimiento['respondieron']++;
            $seguimiento['respondieron_lista'][] = $participante;
        } else {
            $seguimiento['pendientes'][] = $participante;
        }
        if ($participante['es_narrador']) {
            $seguimiento['narrador'] = $participante;
        }
    }

    return $seguimiento;
}

function op_temas_agrupar_por_estado($seguimientos)
{
    $resultado = array(
        'debes_responder' => array(),
        'esperando' => array(),
        'conteos' => array(
            'debes_responder' => 0,
            'esperando' => 0,
        ),
    );

    foreach ($seguimientos as $seguimiento) {
        $estado = (string)$seguimiento['estado'];
        if ($estado === 'cerrado') {
            $grupo = 'esperando';
        } elseif ($estado === 'debes_responder' || $estado === 'debes_responder_manual') {
            $grupo = 'debes_responder';
        } elseif ($estado === 'esperando' || $estado === 'esperando_manual') {
            $grupo = 'esperando';
        } else {
            continue;
        }
        $resultado[$grupo][] = $seguimiento;
        $resultado['conteos'][$grupo]++;
    }

    foreach (array('debes_responder', 'esperando') as $grupo) {
        usort($resultado[$grupo], function ($a, $b) {
            return (int)$b['lastpost'] <=> (int)$a['lastpost'];
        });
    }

    return $resultado;
}

function op_temas_sembrar_participantes($seguimientoId, $tid, $ownerUid)
{
    global $db;
    $seguimientoId = (int)$seguimientoId;
    $tid = (int)$tid;
    $ownerUid = (int)$ownerUid;
    if ($seguimientoId <= 0 || $tid <= 0 || $ownerUid <= 0) {
        return;
    }

    $participantes = $db->table_prefix . 'op_temas_participantes';
    $posts = $db->table_prefix . 'posts';
    $fichas = $db->table_prefix . 'op_fichas';
    $db->write_query("INSERT IGNORE INTO `{$participantes}`
        (`seguimiento_id`, `participante_uid`, `origen`, `creado_en`)
        SELECT {$seguimientoId}, p.uid, 'auto', " . TIME_NOW . "
        FROM `{$posts}` p
        INNER JOIN `{$fichas}` f ON f.fid = p.uid
        WHERE p.tid = {$tid}
          AND p.visible = 1
          AND p.uid > 0
          AND p.uid != {$ownerUid}
        GROUP BY p.uid");
}

function op_temas_propagar_participante($tid, $autorUid)
{
    global $db;
    $tid = (int)$tid;
    $autorUid = (int)$autorUid;
    if ($tid <= 0 || $autorUid <= 0) {
        return;
    }

    $participantes = $db->table_prefix . 'op_temas_participantes';
    $seguidos = $db->table_prefix . 'op_temas_seguidos';
    $db->write_query("INSERT IGNORE INTO `{$participantes}`
        (`seguimiento_id`, `participante_uid`, `origen`, `creado_en`)
        SELECT s.id, {$autorUid}, 'auto', " . TIME_NOW . "
        FROM `{$seguidos}` s
        WHERE s.tid = {$tid}
          AND s.personaje_uid != {$autorUid}");
}

function op_temas_procesar_post($pid)
{
    global $db;
    $pid = (int)$pid;
    if ($pid <= 0 || !op_temas_tablas_listas()) {
        return;
    }

    $posts = $db->table_prefix . 'posts';
    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $fichas = $db->table_prefix . 'op_fichas';
    $query = $db->query("SELECT p.pid, p.tid, p.fid, p.uid, p.visible,
            t.visible AS thread_visible, t.closed, f.parentlist
        FROM `{$posts}` p
        INNER JOIN `{$threads}` t ON t.tid = p.tid
        INNER JOIN `{$forums}` f ON f.fid = t.fid
        INNER JOIN `{$fichas}` ficha ON ficha.fid = p.uid
        WHERE p.pid = {$pid}
        LIMIT 1");
    $post = $db->fetch_array($query);

    if (!$post || (int)$post['visible'] !== 1 || (int)$post['thread_visible'] !== 1
        || (int)$post['uid'] <= 0 || strpos((string)$post['parentlist'], '10,') !== 0
        || op_temas_es_redirect($post['closed'])) {
        return;
    }

    $uid = (int)$post['uid'];
    $tid = (int)$post['tid'];
    $db->write_query('START TRANSACTION');

    $query = $db->simple_select(
        'op_temas_seguidos',
        'id,ronda_inicio_pid',
        "personaje_uid='{$uid}' AND tid='{$tid}'",
        array('limit' => 1)
    );
    $seguimiento = $db->fetch_array($query);
    $esAlta = false;
    if (!$seguimiento) {
        $seguidos = $db->table_prefix . 'op_temas_seguidos';
        $db->write_query("INSERT IGNORE INTO `{$seguidos}`
            (`personaje_uid`, `tid`, `ronda_inicio_pid`, `override_estado`, `creado_en`, `actualizado_en`)
            VALUES ({$uid}, {$tid}, {$pid}, 'auto', " . TIME_NOW . ', ' . TIME_NOW . ')');
        $esAlta = $db->affected_rows() > 0;

        $query = $db->simple_select(
            'op_temas_seguidos',
            'id,ronda_inicio_pid',
            "personaje_uid='{$uid}' AND tid='{$tid}'",
            array('limit' => 1)
        );
        $seguimiento = $db->fetch_array($query);
    }

    if (!$seguimiento) {
        $db->write_query('ROLLBACK');
        return;
    }

    $seguimientoId = (int)$seguimiento['id'];
    if (!$esAlta && $pid > (int)$seguimiento['ronda_inicio_pid']) {
        $db->update_query('op_temas_seguidos', array(
            'ronda_inicio_pid' => $pid,
            'override_estado' => OP_TEMAS_OVERRIDE_AUTO,
            'actualizado_en' => TIME_NOW,
        ), "id='{$seguimientoId}'");
    }

    if ($esAlta) {
        op_temas_sembrar_participantes($seguimientoId, $tid, $uid);
    }
    op_temas_propagar_participante($tid, $uid);

    $db->write_query('COMMIT');
}

function op_temas_agregar_tema($ownerUid, $tid, $estadoInicial)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $tid = (int)$tid;
    $estadoInicial = (string)$estadoInicial;

    if ($ownerUid <= 0 || !op_temas_personaje_tiene_ficha($ownerUid)) {
        return array('ok' => false, 'code' => 'sin_ficha');
    }
    if (!op_temas_override_valido($estadoInicial)) {
        return array('ok' => false, 'code' => 'estado_invalido');
    }
    $thread = op_temas_cargar_tema($tid, true);
    if (!$thread) {
        return array('ok' => false, 'code' => 'tema_no_disponible');
    }

    $query = $db->simple_select(
        'op_temas_seguidos',
        'id',
        "personaje_uid='{$ownerUid}' AND tid='{$tid}'",
        array('limit' => 1)
    );
    if ((int)$db->fetch_field($query, 'id') > 0) {
        return array('ok' => false, 'code' => 'ya_seguido');
    }

    $posts = $db->table_prefix . 'posts';
    $query = $db->query("SELECT MAX(pid) AS ultimo_pid
        FROM `{$posts}`
        WHERE tid = {$tid} AND uid = {$ownerUid} AND visible = 1");
    $ultimoPropio = (int)$db->fetch_field($query, 'ultimo_pid');

    if ($estadoInicial === OP_TEMAS_OVERRIDE_AUTO && $ultimoPropio <= 0) {
        return array('ok' => false, 'code' => 'estado_inicial_requerido');
    }

    $rondaInicio = $ultimoPropio;
    if ($estadoInicial === OP_TEMAS_OVERRIDE_WAIT) {
        $query = $db->query("SELECT MAX(pid) AS ultimo_pid
            FROM `{$posts}`
            WHERE tid = {$tid} AND visible = 1");
        $rondaInicio = (int)$db->fetch_field($query, 'ultimo_pid');
    }

    $db->write_query('START TRANSACTION');
    $seguidos = $db->table_prefix . 'op_temas_seguidos';
    $estadoSql = $db->escape_string($estadoInicial);
    $db->write_query("INSERT IGNORE INTO `{$seguidos}`
        (`personaje_uid`, `tid`, `ronda_inicio_pid`, `override_estado`, `creado_en`, `actualizado_en`)
        VALUES ({$ownerUid}, {$tid}, {$rondaInicio}, '{$estadoSql}', " . TIME_NOW . ', ' . TIME_NOW . ')');
    if ($db->affected_rows() === 0) {
        $db->write_query('ROLLBACK');
        return array('ok' => false, 'code' => 'ya_seguido');
    }
    $seguimientoId = (int)$db->insert_id();
    op_temas_sembrar_participantes($seguimientoId, $tid, $ownerUid);
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'tema_agregado', 'id' => $seguimientoId);
}

function op_temas_dejar_seguir($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!$seguimiento) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $seguimientoId = (int)$seguimiento['id'];
    $db->write_query('START TRANSACTION');
    $db->delete_query('op_temas_participantes', "seguimiento_id='{$seguimientoId}'");
    $db->delete_query('op_temas_seguidos', "id='{$seguimientoId}' AND personaje_uid='" . (int)$ownerUid . "'");
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'tema_retirado');
}

function op_temas_seguimiento_administrable($seguimiento)
{
    if (!$seguimiento) {
        return false;
    }
    $thread = op_temas_cargar_tema((int)$seguimiento['tid'], true);
    return $thread && !op_temas_es_cerrado($thread['closed']);
}

function op_temas_marcar_me_toca($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_temas_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $id = (int)$seguimiento['id'];
    $db->update_query('op_temas_seguidos', array(
        'override_estado' => OP_TEMAS_OVERRIDE_DUE,
        'actualizado_en' => TIME_NOW,
    ), "id='{$id}'");
    return array('ok' => true, 'code' => 'turno_actualizado');
}

function op_temas_marcar_no_me_toca($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_temas_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $tid = (int)$seguimiento['tid'];
    $query = $db->simple_select('posts', 'MAX(pid) AS ultimo_pid', "tid='{$tid}' AND visible='1'");
    $ultimoPid = (int)$db->fetch_field($query, 'ultimo_pid');
    if ($ultimoPid <= 0) {
        return array('ok' => false, 'code' => 'tema_sin_posts');
    }

    $id = (int)$seguimiento['id'];
    $db->update_query('op_temas_seguidos', array(
        'ronda_inicio_pid' => $ultimoPid,
        'override_estado' => OP_TEMAS_OVERRIDE_WAIT,
        'actualizado_en' => TIME_NOW,
    ), "id='{$id}'");
    return array('ok' => true, 'code' => 'turno_actualizado');
}

function op_temas_agregar_participante($ownerUid, $seguimientoId, $participanteUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $participanteUid = (int)$participanteUid;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_temas_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }
    if ($participanteUid <= 0 || $participanteUid === $ownerUid
        || !op_temas_personaje_tiene_ficha($participanteUid)) {
        return array('ok' => false, 'code' => 'participante_invalido');
    }

    $id = (int)$seguimiento['id'];
    $tabla = $db->table_prefix . 'op_temas_participantes';
    $db->write_query("INSERT IGNORE INTO `{$tabla}`
        (`seguimiento_id`, `participante_uid`, `origen`, `creado_en`)
        VALUES ({$id}, {$participanteUid}, 'manual', " . TIME_NOW . ")");
    $db->update_query('op_temas_seguidos', array('actualizado_en' => TIME_NOW), "id='{$id}'");

    return array('ok' => true, 'code' => 'participante_agregado');
}

function op_temas_retirar_participante($ownerUid, $seguimientoId, $participanteUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $participanteUid = (int)$participanteUid;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_temas_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $id = (int)$seguimiento['id'];
    $db->write_query('START TRANSACTION');
    $db->delete_query(
        'op_temas_participantes',
        "seguimiento_id='{$id}' AND participante_uid='{$participanteUid}'"
    );
    $actualizacion = array('actualizado_en' => TIME_NOW);
    if ((int)($seguimiento['narrador_uid'] ?? 0) === $participanteUid) {
        $actualizacion['narrador_uid'] = 0;
    }
    $db->update_query('op_temas_seguidos', $actualizacion, "id='{$id}'");
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'participante_retirado');
}

function op_temas_establecer_narrador($ownerUid, $seguimientoId, $narradorUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $narradorUid = (int)$narradorUid;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_temas_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }
    if ($narradorUid <= 0 || $narradorUid === $ownerUid
        || !op_temas_personaje_tiene_ficha($narradorUid)) {
        return array('ok' => false, 'code' => 'narrador_invalido');
    }

    $id = (int)$seguimiento['id'];
    $participantes = $db->table_prefix . 'op_temas_participantes';
    $db->write_query('START TRANSACTION');
    $db->write_query("INSERT IGNORE INTO `{$participantes}`
        (`seguimiento_id`, `participante_uid`, `origen`, `creado_en`)
        VALUES ({$id}, {$narradorUid}, 'manual', " . TIME_NOW . ")");
    $db->update_query('op_temas_seguidos', array(
        'narrador_uid' => $narradorUid,
        'actualizado_en' => TIME_NOW,
    ), "id='{$id}'");
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'narrador_actualizado');
}

function op_temas_quitar_narrador($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_temas_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_temas_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $id = (int)$seguimiento['id'];
    $db->update_query('op_temas_seguidos', array(
        'narrador_uid' => 0,
        'actualizado_en' => TIME_NOW,
    ), "id='{$id}'");

    return array('ok' => true, 'code' => 'narrador_quitado');
}

function op_temas_listar($ownerUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    if ($ownerUid <= 0 || !op_temas_tablas_listas()) {
        return op_temas_agrupar_por_estado(array());
    }

    $seguidos = $db->table_prefix . 'op_temas_seguidos';
    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $users = $db->table_prefix . 'users';
    $fichas = $db->table_prefix . 'op_fichas';

    $query = $db->query("SELECT s.*, t.fid, t.subject, t.prefix, t.lastpost, t.lastposteruid,
            t.closed, t.visible AS thread_visible, f.name AS forum_name,
            f.parentlist, u.username AS last_username,
            ficha.nombre AS last_nombre, ficha.apodo AS last_apodo
        FROM `{$seguidos}` s
        LEFT JOIN `{$threads}` t ON t.tid = s.tid
        LEFT JOIN `{$forums}` f ON f.fid = t.fid
        LEFT JOIN `{$users}` u ON u.uid = t.lastposteruid
        LEFT JOIN `{$fichas}` ficha ON ficha.fid = t.lastposteruid
        WHERE s.personaje_uid = {$ownerUid}
        ORDER BY t.lastpost DESC, s.id DESC");

    $seguimientos = array();
    $idsVisibles = array();
    $permisosForo = array();
    while ($row = $db->fetch_array($query)) {
        $fid = (int)($row['fid'] ?? 0);
        $visible = (int)($row['thread_visible'] ?? 0) === 1
            && strpos((string)($row['parentlist'] ?? ''), '10,') === 0
            && !op_temas_es_redirect($row['closed'] ?? '');

        if ($visible) {
            if (!array_key_exists($fid, $permisosForo)) {
                $permisosForo[$fid] = op_temas_puede_ver_foro($fid);
            }
            $visible = $permisosForo[$fid];
        }
        if (!$visible) {
            continue;
        }

        $id = (int)$row['id'];
        $row['visible_tracker'] = true;
        $row['cerrado'] = op_temas_es_cerrado($row['closed']);
        $row['participantes'] = array();
        $row['respondieron_lista'] = array();
        $row['pendientes'] = array();
        $row['esperados'] = 0;
        $row['respondieron'] = 0;
        $row['narrador'] = null;
        $row['narrador_respondio'] = false;
        $seguimientos[$id] = $row;
        $idsVisibles[] = $id;
    }

    if (!empty($idsVisibles)) {
        $idsSql = implode(',', array_map('intval', $idsVisibles));
        $participantes = $db->table_prefix . 'op_temas_participantes';
        $posts = $db->table_prefix . 'posts';
        $query = $db->query("SELECT tp.seguimiento_id, tp.participante_uid,
                tp.origen, ficha.nombre, ficha.apodo, u.username, u.avatar,
                MAX(p.pid) AS respuesta_pid
            FROM `{$participantes}` tp
            INNER JOIN `{$seguidos}` s ON s.id = tp.seguimiento_id
            LEFT JOIN `{$fichas}` ficha ON ficha.fid = tp.participante_uid
            LEFT JOIN `{$users}` u ON u.uid = tp.participante_uid
            LEFT JOIN `{$posts}` p
                ON p.tid = s.tid
               AND p.uid = tp.participante_uid
               AND p.visible = 1
               AND p.pid > s.ronda_inicio_pid
            WHERE s.personaje_uid = {$ownerUid}
              AND tp.seguimiento_id IN ({$idsSql})
            GROUP BY tp.seguimiento_id, tp.participante_uid, tp.origen,
                     ficha.nombre, ficha.apodo, u.username, u.avatar
            ORDER BY ficha.nombre ASC, u.username ASC");

        while ($participante = $db->fetch_array($query)) {
            $seguimientoId = (int)$participante['seguimiento_id'];
            if (!isset($seguimientos[$seguimientoId])) {
                continue;
            }
            $seguimientos[$seguimientoId]['participantes'][] = $participante;
        }
    }

    foreach ($seguimientos as &$seguimiento) {
        $seguimiento = op_temas_clasificar_participantes($seguimiento);
        $seguimiento['estado'] = op_temas_resolver_estado($seguimiento);
        $seguimiento['estado_es_manual'] = in_array(
            $seguimiento['estado'],
            array('debes_responder_manual', 'esperando_manual'),
            true
        );
    }
    unset($seguimiento);

    return op_temas_agrupar_por_estado(array_values($seguimientos));
}

function op_temas_resumen($ownerUid)
{
    $listado = op_temas_listar((int)$ownerUid);
    return $listado['conteos'];
}

function op_temas_render_header($ownerUid, $conteos = null)
{
    global $templates, $mybb;

    $ownerUid = (int)$ownerUid;
    if ($ownerUid <= 0) {
        return '';
    }

    if (!is_array($conteos)) {
        if (!op_temas_tablas_listas() || !op_temas_personaje_tiene_ficha($ownerUid)) {
            return '';
        }
        $conteos = op_temas_resumen($ownerUid);
    }
    $op_temas_header_debes = (int)$conteos['debes_responder'];
    $op_temas_header_esperando = (int)$conteos['esperando'];
    $op_temas_header_clase = $op_temas_header_debes > 0 ? ' op-temas-header--pendiente' : '';
    $op_temas_header_texto = ($op_temas_header_debes === 0 && $op_temas_header_esperando === 0)
        ? 'Bitácora: Todo al día. No debes ninguna respuesta.'
        : 'Bitácora: Tu turno: ' . $op_temas_header_debes
            . ' | Al día: ' . $op_temas_header_esperando;

    eval("\$html = \"" . op_temas_cargar_plantilla('op_temas_header') . "\";");
    return $html;
}
