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
if (!defined('OP_BITACORA_ATENCION_DIAS')) {
    define('OP_BITACORA_ATENCION_DIAS', 3);
}
if (!defined('OP_BITACORA_ANTIGUO_DIAS')) {
    define('OP_BITACORA_ANTIGUO_DIAS', 7);
}

function op_bitacora_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function op_bitacora_tiempo_transcurrido($timestamp)
{
    $segundos = max(0, TIME_NOW - (int)$timestamp);
    if ($segundos < 60) {
        return 'menos de un minuto';
    }
    $minutos = (int)floor($segundos / 60);
    if ($minutos < 60) {
        return $minutos . ($minutos === 1 ? ' minuto' : ' minutos');
    }
    $horas = (int)floor($minutos / 60);
    if ($horas < 24) {
        return $horas . ($horas === 1 ? ' hora' : ' horas');
    }
    $dias = (int)floor($horas / 24);
    if ($dias < 30) {
        return $dias . ($dias === 1 ? ' dia' : ' dias');
    }
    $meses = (int)floor($dias / 30);
    if ($meses < 12) {
        return $meses . ($meses === 1 ? ' mes' : ' meses');
    }
    $anos = (int)floor($dias / 365);
    return $anos . ($anos === 1 ? ' ano' : ' anos');
}

function op_bitacora_template_definitions()
{
    return array(
        'op_bitacora' => 'op_bitacora.html',
        'op_bitacora_contenido' => 'op_bitacora_contenido.html',
        'op_bitacora_header' => 'op_bitacora_header.html',
    );
}

function op_bitacora_cargar_plantilla($title)
{
    global $templates;

    $definitions = op_bitacora_template_definitions();
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

function op_bitacora_override_valido($value)
{
    return in_array($value, array(
        OP_TEMAS_OVERRIDE_AUTO,
        OP_TEMAS_OVERRIDE_DUE,
        OP_TEMAS_OVERRIDE_WAIT,
    ), true);
}

function op_bitacora_es_redirect($closed)
{
    return strpos((string)$closed, 'moved|') === 0;
}

function op_bitacora_es_cerrado($closed)
{
    $closed = (string)$closed;
    return $closed !== '' && $closed !== '0' && !op_bitacora_es_redirect($closed);
}

function op_bitacora_tablas_listas()
{
    global $db;
    return $db->table_exists('op_temas_seguidos')
        && $db->table_exists('op_temas_participantes');
}

function op_bitacora_actualizar_esquema()
{
    global $db;
    if (!$db->table_exists('op_bitacora_eventos')) {
        $table = $db->table_prefix . 'op_bitacora_eventos';
        $collation = $db->build_create_table_collation();
        $db->write_query("CREATE TABLE `{$table}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            seguimiento_id INT UNSIGNED NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            actor_uid INT UNSIGNED NOT NULL DEFAULT 0,
            relacionado_uid INT UNSIGNED NOT NULL DEFAULT 0,
            pid INT UNSIGNED NULL,
            datos TEXT NULL,
            creado_en INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY evento_post (seguimiento_id, tipo, pid),
            KEY seguimiento_fecha (seguimiento_id, creado_en)
        ) ENGINE=InnoDB {$collation}");
    }
    if ($db->table_exists('op_temas_seguidos')
        && !$db->field_exists('narrador_uid', 'op_temas_seguidos')) {
        $db->add_column(
            'op_temas_seguidos',
            'narrador_uid',
            'INT UNSIGNED NOT NULL DEFAULT 0 AFTER override_estado'
        );
    }
    if ($db->table_exists('op_temas_seguidos')
        && !$db->field_exists('estado_grupo', 'op_temas_seguidos')) {
        $db->add_column(
            'op_temas_seguidos',
            'estado_grupo',
            "VARCHAR(12) NOT NULL DEFAULT '' AFTER narrador_uid"
        );
    }
    if ($db->table_exists('op_temas_seguidos')
        && !$db->field_exists('estado_desde', 'op_temas_seguidos')) {
        $db->add_column(
            'op_temas_seguidos',
            'estado_desde',
            'INT UNSIGNED NOT NULL DEFAULT 0 AFTER estado_grupo'
        );
    }
}

function op_bitacora_registrar_evento($seguimientoId, $tipo, $actorUid = 0, $relacionadoUid = 0, $pid = null, $datos = null, $creadoEn = null)
{
    global $db;
    $seguimientoId = (int)$seguimientoId;
    if ($seguimientoId <= 0 || !$db->table_exists('op_bitacora_eventos')) {
        return;
    }

    $tipoSql = $db->escape_string((string)$tipo);
    $datosSql = $datos === null ? 'NULL' : "'" . $db->escape_string((string)$datos) . "'";
    $pidSql = $pid === null ? 'NULL' : (string)(int)$pid;
    $creadoEn = $creadoEn === null ? TIME_NOW : max(1, (int)$creadoEn);
    $table = $db->table_prefix . 'op_bitacora_eventos';
    $db->write_query("INSERT IGNORE INTO `{$table}`
        (`seguimiento_id`, `tipo`, `actor_uid`, `relacionado_uid`, `pid`, `datos`, `creado_en`)
        VALUES ({$seguimientoId}, '{$tipoSql}', " . (int)$actorUid . ', '
        . (int)$relacionadoUid . ", {$pidSql}, {$datosSql}, {$creadoEn})");
}

function op_bitacora_registrar_rondas_narradas($tid, $narradorUid, $pid, $fecha)
{
    global $db;
    $tid = (int)$tid;
    $narradorUid = (int)$narradorUid;
    $pid = (int)$pid;
    if ($tid <= 0 || $narradorUid <= 0 || $pid <= 0 || !$db->table_exists('op_bitacora_eventos')) {
        return;
    }

    $query = $db->simple_select(
        'op_temas_seguidos',
        'id',
        "tid='{$tid}' AND narrador_uid='{$narradorUid}' AND personaje_uid!='{$narradorUid}'"
            . " AND ronda_inicio_pid<'{$pid}'"
    );
    while ($seguimientoId = $db->fetch_field($query, 'id')) {
        op_bitacora_registrar_evento(
            (int)$seguimientoId,
            'ronda_narrada_iniciada',
            $narradorUid,
            $narradorUid,
            $pid,
            null,
            $fecha
        );
    }
}

function op_bitacora_estado_persistente_disponible()
{
    global $db;
    static $disponible = null;
    if ($disponible === null) {
        $disponible = $db->table_exists('op_temas_seguidos')
            && $db->field_exists('estado_grupo', 'op_temas_seguidos')
            && $db->field_exists('estado_desde', 'op_temas_seguidos');
    }
    return $disponible;
}

function op_bitacora_personaje_tiene_ficha($uid)
{
    global $db;
    $uid = (int)$uid;
    if ($uid <= 0) {
        return false;
    }

    $query = $db->simple_select('op_fichas', 'fid', "fid='{$uid}'", array('limit' => 1));
    return (int)$db->fetch_field($query, 'fid') === $uid;
}

function op_bitacora_es_foro_rol($fid)
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

function op_bitacora_puede_ver_foro($fid)
{
    $permissions = forum_permissions((int)$fid);
    return is_array($permissions)
        && !empty($permissions['canview'])
        && !empty($permissions['canviewthreads']);
}

function op_bitacora_cargar_tema($tid, $comprobarPermisos = true)
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
    if (op_bitacora_es_redirect($thread['closed'])) {
        return false;
    }
    if ($comprobarPermisos && !op_bitacora_puede_ver_foro((int)$thread['fid'])) {
        return false;
    }

    return $thread;
}

function op_bitacora_cargar_seguimiento($id, $ownerUid)
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

function op_bitacora_resolver_estado($seguimiento)
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

function op_bitacora_grupo_estado($estado)
{
    return in_array((string)$estado, array('debes_responder', 'debes_responder_manual'), true)
        ? 'turno'
        : 'al_dia';
}

function op_bitacora_prioridad_estado($seguimiento)
{
    if (op_bitacora_grupo_estado($seguimiento['estado'] ?? '') !== 'turno') {
        return 'normal';
    }
    $desde = (int)($seguimiento['estado_desde'] ?? 0);
    if ($desde <= 0) {
        return 'normal';
    }
    $dias = (TIME_NOW - $desde) / 86400;
    if ($dias >= OP_BITACORA_ANTIGUO_DIAS) {
        return 'antiguo';
    }
    if ($dias >= OP_BITACORA_ATENCION_DIAS) {
        return 'atencion';
    }
    return 'normal';
}

function op_bitacora_estimar_estado_desde($seguimiento, $grupo, $esInicial)
{
    $manual = !empty($seguimiento['estado_es_manual']);
    $actualizado = (int)($seguimiento['actualizado_en'] ?? 0);
    $estimado = 0;

    if ($manual) {
        $estimado = $actualizado;
    } elseif ($grupo === 'turno') {
        $estimado = (int)($seguimiento['estado_evento_en'] ?? 0);
    } else {
        $estimado = (int)($seguimiento['ronda_inicio_fecha'] ?? 0);
    }

    if (!$esInicial && !empty($seguimiento['cerrado']) && $grupo === 'al_dia') {
        return TIME_NOW;
    }
    if (!$esInicial && $actualizado > $estimado) {
        $estimado = $actualizado;
    }
    if ($estimado <= 0) {
        $estimado = $actualizado > 0 ? $actualizado : (int)($seguimiento['creado_en'] ?? 0);
    }
    if ($estimado <= 0) {
        $estimado = (int)($seguimiento['lastpost'] ?? TIME_NOW);
    }

    return min($estimado, TIME_NOW);
}

/**
 * Conserva el momento en que el tema entro en Tu turno o Al dia. La lectura
 * solo escribe cuando cambia el grupo visible; editar una configuracion que
 * no altera el grupo no reinicia el reloj.
 */
function op_bitacora_sincronizar_estado(&$seguimiento)
{
    global $db;

    $grupo = op_bitacora_grupo_estado($seguimiento['estado'] ?? 'esperando');
    $puedePersistir = array_key_exists('estado_grupo', $seguimiento)
        && array_key_exists('estado_desde', $seguimiento);
    $grupoAnterior = (string)($seguimiento['estado_grupo'] ?? '');
    $desde = (int)($seguimiento['estado_desde'] ?? 0);
    if ($grupoAnterior === $grupo && $desde > 0) {
        return;
    }

    $desde = op_bitacora_estimar_estado_desde($seguimiento, $grupo, $grupoAnterior === '');
    $seguimiento['estado_grupo'] = $grupo;
    $seguimiento['estado_desde'] = $desde;

    // Permite desplegar el codigo antes de ejecutar la actualizacion de
    // esquema: la UI funciona con una estimacion y empieza a persistir al
    // reactivar el plugin o aplicar la migracion.
    if (!$puedePersistir) {
        return;
    }

    $id = (int)($seguimiento['id'] ?? 0);
    if ($id > 0) {
        $db->update_query('op_temas_seguidos', array(
            'estado_grupo' => $grupo,
            'estado_desde' => $desde,
        ), "id='{$id}'");
    }
}

function op_bitacora_clasificar_participantes($seguimiento)
{
    $seguimiento['respondieron_lista'] = array();
    $seguimiento['pendientes'] = array();
    $seguimiento['esperados'] = 0;
    $seguimiento['respondieron'] = 0;
    $seguimiento['narrador'] = null;
    $seguimiento['estado_evento_en'] = 0;

    $narradorUid = (int)($seguimiento['narrador_uid'] ?? 0);
    $narradorPid = 0;
    $narradorFecha = 0;
    foreach ($seguimiento['participantes'] as $participante) {
        if ((int)$participante['participante_uid'] === $narradorUid) {
            $narradorPid = (int)$participante['respuesta_pid'];
            $narradorFecha = (int)($participante['respuesta_fecha'] ?? 0);
            $seguimiento['narrador'] = $participante;
            break;
        }
    }

    $seguimiento['narrador_inicio_pid'] = $narradorPid;
    $seguimiento['narrador_respondio'] = $narradorPid > 0;
    $completaDesde = 0;

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
            if ($narradorUid === 0) {
                $completaDesde = max($completaDesde, (int)($participante['primera_respuesta_fecha'] ?? 0));
            }
        } else {
            $seguimiento['pendientes'][] = $participante;
        }
        if ($participante['es_narrador']) {
            $seguimiento['narrador'] = $participante;
        }
    }

    $seguimiento['estado_evento_en'] = $narradorUid > 0 ? $narradorFecha : $completaDesde;

    return $seguimiento;
}

function op_bitacora_agrupar_por_estado($seguimientos)
{
    $resultado = array(
        'debes_responder' => array(),
        'esperando' => array(),
        'conteos' => array(
            'debes_responder' => 0,
            'esperando' => 0,
            'ultima_actividad' => 0,
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
        $resultado['conteos']['ultima_actividad'] = max(
            $resultado['conteos']['ultima_actividad'],
            (int)($seguimiento['lastpost'] ?? 0),
            (int)($seguimiento['actualizado_en'] ?? 0),
            (int)($seguimiento['estado_desde'] ?? 0)
        );
    }

    foreach (array('debes_responder', 'esperando') as $grupo) {
        usort($resultado[$grupo], function ($a, $b) {
            return (int)$b['lastpost'] <=> (int)$a['lastpost'];
        });
    }

    return $resultado;
}

function op_bitacora_sembrar_participantes($seguimientoId, $tid, $ownerUid)
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

function op_bitacora_propagar_participante($tid, $autorUid)
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

function op_bitacora_procesar_post($pid)
{
    global $db;
    $pid = (int)$pid;
    if ($pid <= 0 || !op_bitacora_tablas_listas()) {
        return;
    }

    $posts = $db->table_prefix . 'posts';
    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $fichas = $db->table_prefix . 'op_fichas';
    $query = $db->query("SELECT p.pid, p.tid, p.fid, p.uid, p.visible, p.dateline,
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
        || op_bitacora_es_redirect($post['closed'])) {
        return;
    }

    $uid = (int)$post['uid'];
    $tid = (int)$post['tid'];
    $postFecha = (int)$post['dateline'] > 0 ? (int)$post['dateline'] : TIME_NOW;
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
    $iniciaRondaNormal = $esAlta || $pid > (int)$seguimiento['ronda_inicio_pid'];
    if (!$esAlta && $pid > (int)$seguimiento['ronda_inicio_pid']) {
        $actualizacion = array(
            'ronda_inicio_pid' => $pid,
            'override_estado' => OP_TEMAS_OVERRIDE_AUTO,
            'actualizado_en' => TIME_NOW,
        );
        if (op_bitacora_estado_persistente_disponible()) {
            $actualizacion['estado_grupo'] = 'al_dia';
            $actualizacion['estado_desde'] = $postFecha;
        }
        $db->update_query('op_temas_seguidos', $actualizacion, "id='{$seguimientoId}'");
    }

    if ($esAlta) {
        op_bitacora_sembrar_participantes($seguimientoId, $tid, $uid);
    }
    if ($iniciaRondaNormal) {
        op_bitacora_registrar_evento(
            $seguimientoId,
            'ronda_normal_iniciada',
            $uid,
            0,
            $pid,
            null,
            $postFecha
        );
    }
    op_bitacora_propagar_participante($tid, $uid);
    op_bitacora_registrar_rondas_narradas($tid, $uid, $pid, $postFecha);

    $db->write_query('COMMIT');
}

function op_bitacora_agregar_tema($ownerUid, $tid, $estadoInicial)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $tid = (int)$tid;
    $estadoInicial = (string)$estadoInicial;

    if ($ownerUid <= 0 || !op_bitacora_personaje_tiene_ficha($ownerUid)) {
        return array('ok' => false, 'code' => 'sin_ficha');
    }
    if (!op_bitacora_override_valido($estadoInicial)) {
        return array('ok' => false, 'code' => 'estado_invalido');
    }
    $thread = op_bitacora_cargar_tema($tid, true);
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
    op_bitacora_sembrar_participantes($seguimientoId, $tid, $ownerUid);
    if ($estadoInicial === OP_TEMAS_OVERRIDE_DUE) {
        op_bitacora_registrar_evento($seguimientoId, 'manual_turno', $ownerUid);
    } elseif ($estadoInicial === OP_TEMAS_OVERRIDE_WAIT) {
        op_bitacora_registrar_evento($seguimientoId, 'manual_al_dia', $ownerUid);
    }
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'tema_agregado', 'id' => $seguimientoId);
}

function op_bitacora_dejar_seguir($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!$seguimiento) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $seguimientoId = (int)$seguimiento['id'];
    $db->write_query('START TRANSACTION');
    if ($db->table_exists('op_bitacora_eventos')) {
        $db->delete_query('op_bitacora_eventos', "seguimiento_id='{$seguimientoId}'");
    }
    $db->delete_query('op_temas_participantes', "seguimiento_id='{$seguimientoId}'");
    $db->delete_query('op_temas_seguidos', "id='{$seguimientoId}' AND personaje_uid='" . (int)$ownerUid . "'");
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'tema_retirado');
}

function op_bitacora_seguimiento_administrable($seguimiento)
{
    if (!$seguimiento) {
        return false;
    }
    $thread = op_bitacora_cargar_tema((int)$seguimiento['tid'], true);
    return $thread && !op_bitacora_es_cerrado($thread['closed']);
}

function op_bitacora_marcar_me_toca($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_bitacora_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $id = (int)$seguimiento['id'];
    $actualizacion = array(
        'override_estado' => OP_TEMAS_OVERRIDE_DUE,
        'actualizado_en' => TIME_NOW,
    );
    if (op_bitacora_estado_persistente_disponible()) {
        $actualizacion['estado_grupo'] = 'turno';
        $actualizacion['estado_desde'] = TIME_NOW;
    }
    $db->update_query('op_temas_seguidos', $actualizacion, "id='{$id}'");
    op_bitacora_registrar_evento($id, 'manual_turno', $ownerUid);
    return array('ok' => true, 'code' => 'turno_actualizado');
}

function op_bitacora_marcar_no_me_toca($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_bitacora_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $tid = (int)$seguimiento['tid'];
    $query = $db->simple_select('posts', 'MAX(pid) AS ultimo_pid', "tid='{$tid}' AND visible='1'");
    $ultimoPid = (int)$db->fetch_field($query, 'ultimo_pid');
    if ($ultimoPid <= 0) {
        return array('ok' => false, 'code' => 'tema_sin_posts');
    }

    $id = (int)$seguimiento['id'];
    $actualizacion = array(
        'ronda_inicio_pid' => $ultimoPid,
        'override_estado' => OP_TEMAS_OVERRIDE_WAIT,
        'actualizado_en' => TIME_NOW,
    );
    if (op_bitacora_estado_persistente_disponible()) {
        $actualizacion['estado_grupo'] = 'al_dia';
        $actualizacion['estado_desde'] = TIME_NOW;
    }
    $db->update_query('op_temas_seguidos', $actualizacion, "id='{$id}'");
    op_bitacora_registrar_evento($id, 'manual_al_dia', $ownerUid);
    return array('ok' => true, 'code' => 'turno_actualizado');
}

function op_bitacora_agregar_participante($ownerUid, $seguimientoId, $participanteUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $participanteUid = (int)$participanteUid;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_bitacora_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }
    if ($participanteUid <= 0 || $participanteUid === $ownerUid
        || !op_bitacora_personaje_tiene_ficha($participanteUid)) {
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

function op_bitacora_retirar_participante($ownerUid, $seguimientoId, $participanteUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $participanteUid = (int)$participanteUid;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_bitacora_seguimiento_administrable($seguimiento)) {
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
        op_bitacora_registrar_evento($id, 'narrador_retirado', $ownerUid, $participanteUid);
    }
    $db->update_query('op_temas_seguidos', $actualizacion, "id='{$id}'");
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'participante_retirado');
}

function op_bitacora_establecer_narrador($ownerUid, $seguimientoId, $narradorUid)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    $narradorUid = (int)$narradorUid;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_bitacora_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }
    if ($narradorUid <= 0 || $narradorUid === $ownerUid
        || !op_bitacora_personaje_tiene_ficha($narradorUid)) {
        return array('ok' => false, 'code' => 'narrador_invalido');
    }

    $id = (int)$seguimiento['id'];
    $narradorAnterior = (int)($seguimiento['narrador_uid'] ?? 0);
    $participantes = $db->table_prefix . 'op_temas_participantes';
    $db->write_query('START TRANSACTION');
    $db->write_query("INSERT IGNORE INTO `{$participantes}`
        (`seguimiento_id`, `participante_uid`, `origen`, `creado_en`)
        VALUES ({$id}, {$narradorUid}, 'manual', " . TIME_NOW . ")");
    $db->update_query('op_temas_seguidos', array(
        'narrador_uid' => $narradorUid,
        'actualizado_en' => TIME_NOW,
    ), "id='{$id}'");
    if ($narradorAnterior !== $narradorUid) {
        op_bitacora_registrar_evento(
            $id,
            $narradorAnterior > 0 ? 'narrador_cambiado' : 'narrador_asignado',
            $ownerUid,
            $narradorUid,
            null,
            $narradorAnterior > 0 ? json_encode(array('anterior_uid' => $narradorAnterior)) : null
        );
    }
    $db->write_query('COMMIT');

    return array('ok' => true, 'code' => 'narrador_actualizado');
}

function op_bitacora_quitar_narrador($ownerUid, $seguimientoId)
{
    global $db;
    $seguimiento = op_bitacora_cargar_seguimiento($seguimientoId, $ownerUid);
    if (!op_bitacora_seguimiento_administrable($seguimiento)) {
        return array('ok' => false, 'code' => 'seguimiento_no_disponible');
    }

    $id = (int)$seguimiento['id'];
    $narradorAnterior = (int)($seguimiento['narrador_uid'] ?? 0);
    $db->update_query('op_temas_seguidos', array(
        'narrador_uid' => 0,
        'actualizado_en' => TIME_NOW,
    ), "id='{$id}'");
    if ($narradorAnterior > 0) {
        op_bitacora_registrar_evento($id, 'narrador_retirado', $ownerUid, $narradorAnterior);
    }

    return array('ok' => true, 'code' => 'narrador_quitado');
}

function op_bitacora_listar($ownerUid, $incluirHistorial = true)
{
    global $db;
    $ownerUid = (int)$ownerUid;
    if ($ownerUid <= 0 || !op_bitacora_tablas_listas()) {
        return op_bitacora_agrupar_por_estado(array());
    }

    $seguidos = $db->table_prefix . 'op_temas_seguidos';
    $threads = $db->table_prefix . 'threads';
    $forums = $db->table_prefix . 'forums';
    $users = $db->table_prefix . 'users';
    $fichas = $db->table_prefix . 'op_fichas';
    $posts = $db->table_prefix . 'posts';

    $query = $db->query("SELECT s.*, t.fid, t.subject, t.prefix, t.lastpost, t.lastposteruid,
            t.replies, ronda.dateline AS ronda_inicio_fecha,
            t.closed, t.visible AS thread_visible, f.name AS forum_name,
            f.parentlist, u.username AS last_username,
            ficha.nombre AS last_nombre
        FROM `{$seguidos}` s
        LEFT JOIN `{$threads}` t ON t.tid = s.tid
        LEFT JOIN `{$posts}` ronda ON ronda.pid = s.ronda_inicio_pid
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
            && !op_bitacora_es_redirect($row['closed'] ?? '');

        if ($visible) {
            if (!array_key_exists($fid, $permisosForo)) {
                $permisosForo[$fid] = op_bitacora_puede_ver_foro($fid);
            }
            $visible = $permisosForo[$fid];
        }
        if (!$visible) {
            continue;
        }

        $id = (int)$row['id'];
        $row['visible_tracker'] = true;
        $row['cerrado'] = op_bitacora_es_cerrado($row['closed']);
        $row['participantes'] = array();
        $row['respondieron_lista'] = array();
        $row['pendientes'] = array();
        $row['esperados'] = 0;
        $row['respondieron'] = 0;
        $row['narrador'] = null;
        $row['narrador_respondio'] = false;
        $row['historial'] = array();
        $seguimientos[$id] = $row;
        $idsVisibles[] = $id;
    }

    if (!empty($idsVisibles)) {
        $idsSql = implode(',', array_map('intval', $idsVisibles));
        $participantes = $db->table_prefix . 'op_temas_participantes';
        $query = $db->query("SELECT tp.seguimiento_id, tp.participante_uid,
                tp.origen, ficha.nombre, u.username, u.avatar,
                MAX(p.pid) AS respuesta_pid, MAX(p.dateline) AS respuesta_fecha,
                MIN(p.dateline) AS primera_respuesta_fecha
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
                     ficha.nombre, u.username, u.avatar
            ORDER BY ficha.nombre ASC, u.username ASC");

        while ($participante = $db->fetch_array($query)) {
            $seguimientoId = (int)$participante['seguimiento_id'];
            if (!isset($seguimientos[$seguimientoId])) {
                continue;
            }
            $seguimientos[$seguimientoId]['participantes'][] = $participante;
        }

        if ($incluirHistorial && $db->table_exists('op_bitacora_eventos')) {
            $eventos = $db->table_prefix . 'op_bitacora_eventos';
            $query = $db->query("SELECT e.*, ficha.nombre AS relacionado_nombre,
                    u.username AS relacionado_username
                FROM `{$eventos}` e
                LEFT JOIN `{$fichas}` ficha ON ficha.fid = e.relacionado_uid
                LEFT JOIN `{$users}` u ON u.uid = e.relacionado_uid
                WHERE e.seguimiento_id IN ({$idsSql})
                  AND (
                    SELECT COUNT(*)
                    FROM `{$eventos}` posteriores
                    WHERE posteriores.seguimiento_id = e.seguimiento_id
                      AND (posteriores.creado_en > e.creado_en
                        OR (posteriores.creado_en = e.creado_en AND posteriores.id > e.id))
                  ) < 5
                ORDER BY e.seguimiento_id ASC, e.creado_en DESC, e.id DESC");
            while ($evento = $db->fetch_array($query)) {
                $seguimientoId = (int)$evento['seguimiento_id'];
                if (isset($seguimientos[$seguimientoId])) {
                    $seguimientos[$seguimientoId]['historial'][] = $evento;
                }
            }
        }
    }

    foreach ($seguimientos as &$seguimiento) {
        $seguimiento = op_bitacora_clasificar_participantes($seguimiento);
        $seguimiento['estado'] = op_bitacora_resolver_estado($seguimiento);
        $seguimiento['estado_es_manual'] = in_array(
            $seguimiento['estado'],
            array('debes_responder_manual', 'esperando_manual'),
            true
        );
        op_bitacora_sincronizar_estado($seguimiento);
        $seguimiento['prioridad_estado'] = op_bitacora_prioridad_estado($seguimiento);
    }
    unset($seguimiento);

    return op_bitacora_agrupar_por_estado(array_values($seguimientos));
}

function op_bitacora_resumen($ownerUid)
{
    $listado = op_bitacora_listar((int)$ownerUid, false);
    return $listado['conteos'];
}

function op_bitacora_render_header($ownerUid, $conteos = null)
{
    global $templates, $mybb;

    $ownerUid = (int)$ownerUid;
    if ($ownerUid <= 0) {
        return '';
    }

    if (!is_array($conteos)) {
        if (!op_bitacora_tablas_listas() || !op_bitacora_personaje_tiene_ficha($ownerUid)) {
            return '';
        }
        $conteos = op_bitacora_resumen($ownerUid);
    }
    $op_temas_header_debes = (int)$conteos['debes_responder'];
    $op_temas_header_esperando = (int)$conteos['esperando'];
    $ultimaActividad = (int)($conteos['ultima_actividad'] ?? 0);
    $op_temas_header_actualizado = $ultimaActividad > 0
        ? '<small>Actualizado hace ' . op_bitacora_escape(op_bitacora_tiempo_transcurrido($ultimaActividad)) . '</small>'
        : '';
    $op_temas_header_clase = $op_temas_header_debes > 0 ? ' op-temas-header--pendiente' : '';
    $op_temas_header_texto = ($op_temas_header_debes === 0 && $op_temas_header_esperando === 0)
        ? 'Bitácora: Todo al día. No debes ninguna respuesta.'
        : 'Bitácora: Tu turno: ' . $op_temas_header_debes
            . ' | Al día: ' . $op_temas_header_esperando;

    eval("\$html = \"" . op_bitacora_cargar_plantilla('op_bitacora_header') . "\";");
    return $html;
}
