<?php
/** Panel tactico de un tema. */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'panel_tactico.php');
require_once './../global.php';
require_once MYBB_ROOT . 'inc/plugins/lib/panel_tactico.php';

global $mybb, $db, $templates, $header, $headerinclude, $footer;

$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
if ($tid <= 0) {
    error('No se ha indicado un tema valido.');
}

$thread = get_thread($tid);
if (!$thread || !opg_pt_thread_access($thread)) {
    error_no_permission();
}

$fid = (int)$thread['fid'];
$canReindex = opg_pt_can_reindex($fid);
$notice = '';

if ($mybb->request_method === 'post' && $mybb->get_input('action') === 'reindex') {
    if (!$canReindex) {
        error_no_permission();
    }
    verify_post_check($mybb->get_input('my_post_key'));
    if (!opg_pt_tables_ready()) {
        error('El indice del Panel tactico no esta instalado.');
    }
    $result = opg_pt_index_thread_batch($tid, $mybb->get_input('after_pid', MyBB::INPUT_INT), 100);
    $params = array('tid' => $tid, 'reindexed' => (int)$result['processed']);
    if ($result['has_more']) {
        $params['next_pid'] = (int)$result['last_pid'];
    }
    redirect(
        rtrim($mybb->settings['bburl'], '/') . '/op/panel_tactico.php?' . http_build_query($params),
        'El lote del Panel tactico se ha procesado.'
    );
}

if ($mybb->get_input('reindexed', MyBB::INPUT_INT) > 0) {
    $notice = '<div class="pt-notice pt-notice--success"><i class="fa-solid fa-circle-check"></i> '
        . 'Se analizaron ' . (int)$mybb->get_input('reindexed', MyBB::INPUT_INT) . ' posts.</div>';
}

function opg_pt_page_url($tid, $params = array())
{
    global $mybb;
    $params = array_merge(array('tid' => (int)$tid), $params);
    return opg_pt_escape(rtrim($mybb->settings['bburl'], '/') . '/op/panel_tactico.php?' . http_build_query($params));
}

function opg_pt_post_url($tid, $pid)
{
    global $mybb;
    return rtrim($mybb->settings['bburl'], '/') . '/showthread.php?tid=' . (int)$tid
        . '&amp;pid=' . (int)$pid . '#pid' . (int)$pid;
}

function opg_pt_empty($title, $text)
{
    return '<div class="pt-empty"><i class="fa-solid fa-compass-drafting"></i><h3>'
        . opg_pt_escape($title) . '</h3><p>' . opg_pt_escape($text) . '</p></div>';
}

function opg_pt_load_participants($tid, $fid)
{
    global $db;
    $visible = opg_pt_visible_posts_sql($fid, 'p');
    $query = $db->query("SELECT p.uid,
            COALESCE(NULLIF(MAX(p.username),''),MAX(u.username),'Invitado') AS author_name,
            COUNT(*) AS post_count,MIN(p.dateline) AS first_post,MAX(p.dateline) AS last_post
        FROM {$db->table_prefix}posts p
        LEFT JOIN {$db->table_prefix}users u ON u.uid=p.uid
        WHERE p.tid='" . (int)$tid . "' AND {$visible}
        GROUP BY p.uid ORDER BY first_post ASC");
    $rows = array();
    while ($row = $db->fetch_array($query)) {
        $rows[(int)$row['uid']] = $row;
    }
    return $rows;
}

function opg_pt_index_state($tid, $fid)
{
    global $db;
    $visible = opg_pt_visible_posts_sql($fid, 'p');
    $query = $db->query("SELECT COUNT(*) AS total_posts,
            SUM(CASE WHEN ip.pid IS NOT NULL AND ip.index_status='actualizado' THEN 1 ELSE 0 END) AS indexed_posts,
            SUM(CASE WHEN ip.index_status='error' THEN 1 ELSE 0 END) AS error_posts,
            MAX(ip.indexed_at) AS indexed_at
        FROM {$db->table_prefix}posts p
        LEFT JOIN {$db->table_prefix}op_panel_tactico_posts ip ON ip.pid=p.pid
        WHERE p.tid='" . (int)$tid . "' AND {$visible}");
    $row = $db->fetch_array($query);
    return array(
        'total' => (int)($row['total_posts'] ?? 0),
        'indexed' => (int)($row['indexed_posts'] ?? 0),
        'errors' => (int)($row['error_posts'] ?? 0),
        'indexed_at' => (int)($row['indexed_at'] ?? 0),
    );
}

function opg_pt_load_event_counts($tid, $fid)
{
    global $db;
    $visible = opg_pt_visible_posts_sql($fid, 'p');
    $counts = array('tecnica' => 0, 'objeto_mostrado' => 0, 'recurso' => 0, 'posts_con_datos' => 0);
    $query = $db->query("SELECT e.event_type,COUNT(*) AS total
        FROM {$db->table_prefix}posts p
        INNER JOIN {$db->table_prefix}op_panel_tactico_eventos e
            ON e.pid=p.pid AND e.is_current=1 AND e.visibility_scope='publico'
        WHERE p.tid='" . (int)$tid . "' AND {$visible}
        GROUP BY e.event_type");
    while ($row = $db->fetch_array($query)) {
        if (isset($counts[$row['event_type']])) {
            $counts[$row['event_type']] = (int)$row['total'];
        }
    }
    $postQuery = $db->query("SELECT COUNT(DISTINCT e.pid) AS total
        FROM {$db->table_prefix}posts p
        INNER JOIN {$db->table_prefix}op_panel_tactico_eventos e
            ON e.pid=p.pid AND e.is_current=1 AND e.visibility_scope='publico'
        WHERE p.tid='" . (int)$tid . "' AND {$visible}");
    $counts['posts_con_datos'] = (int)$db->fetch_field($postQuery, 'total');
    return $counts;
}

function opg_pt_post_positions($tid, $fid)
{
    global $db;
    $visible = opg_pt_visible_posts_sql($fid, 'p');
    $query = $db->query("SELECT p.pid FROM {$db->table_prefix}posts p
        WHERE p.tid='" . (int)$tid . "' AND {$visible}
        ORDER BY p.dateline ASC,p.pid ASC");
    $positions = array();
    $position = 0;
    while ($row = $db->fetch_array($query)) {
        $positions[(int)$row['pid']] = ++$position;
    }
    return $positions;
}

function opg_pt_render_filters($tid, $participants, $selectedUid, $selectedType)
{
    $html = '<form class="pt-filters" method="get"><input type="hidden" name="tid" value="' . (int)$tid . '">'
        . '<label>Participante<select name="uid"><option value="0">Todos los participantes</option>';
    foreach ($participants as $participant) {
        $selected = (int)$participant['uid'] === (int)$selectedUid ? ' selected' : '';
        $html .= '<option value="' . (int)$participant['uid'] . '"' . $selected . '>'
            . opg_pt_escape($participant['author_name']) . '</option>';
    }
    $html .= '</select></label><label>Informacion mostrada<select name="tipo">';
    $types = array(
        '' => 'Todos los posts',
        'con_datos' => 'Solo posts con datos tacticos',
        'tecnica' => 'Posts con tecnicas',
        'objeto_mostrado' => 'Posts con objetos',
        'recurso' => 'Posts con recursos',
    );
    foreach ($types as $value => $label) {
        $selected = $value === $selectedType ? ' selected' : '';
        $html .= '<option value="' . opg_pt_escape($value) . '"' . $selected . '>' . opg_pt_escape($label) . '</option>';
    }
    $html .= '</select></label><button class="btn-op btn-op--primario" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button>';
    if ($selectedUid > 0 || $selectedType !== '') {
        $html .= '<a class="pt-filter-reset" href="' . opg_pt_page_url($tid) . '"><i class="fa-solid fa-xmark"></i> Limpiar</a>';
    }
    return $html . '</form>';
}

function opg_pt_resource_changes($tid, $fid)
{
    global $db;
    $visible = opg_pt_visible_posts_sql($fid, 'p');
    $query = $db->query("SELECT e.* FROM {$db->table_prefix}posts p
        INNER JOIN {$db->table_prefix}op_panel_tactico_eventos e
            ON e.pid=p.pid AND e.is_current=1 AND e.visibility_scope='publico' AND e.event_type='recurso'
        WHERE p.tid='" . (int)$tid . "' AND {$visible}
        ORDER BY p.dateline ASC,p.pid ASC,e.post_order ASC");
    $previous = array();
    $changes = array();
    while ($row = $db->fetch_array($query)) {
        $event = opg_pt_decode_event($row);
        $uid = (int)$event['uid'];
        $changes[(int)$event['id']] = isset($previous[$uid]) ? $previous[$uid] : null;
        $previous[$uid] = $event['data'];
    }
    return $changes;
}

function opg_pt_resource_delta($event, $previous)
{
    if (!is_array($previous)) {
        return '<small class="pt-resource-delta">Primer registro del personaje</small>';
    }
    $parts = array();
    foreach (array('vida' => 'Vida', 'energia' => 'Energia', 'haki' => 'Haki') as $key => $label) {
        $delta = (int)($event['data'][$key] ?? 0) - (int)($previous[$key] ?? 0);
        if ($delta !== 0) {
            $parts[] = $label . ' ' . ($delta > 0 ? '+' : '') . $delta;
        }
    }
    return '<small class="pt-resource-delta">' . (!empty($parts)
        ? opg_pt_escape('Desde el registro anterior: ' . implode(' · ', $parts))
        : 'Sin cambios desde el registro anterior') . '</small>';
}

function opg_pt_render_post_event($event, $resourceChanges)
{
    $status = $event['event_status'] === 'valido'
        ? ''
        : '<span class="pt-status pt-status--warning">Dato incompleto</span>';
    $delta = '';
    if ($event['event_type'] === 'recurso') {
        $previous = $resourceChanges[(int)$event['id']] ?? null;
        $delta = opg_pt_resource_delta($event, $previous);
    }
    return '<li class="pt-post-event pt-post-event--' . opg_pt_escape($event['event_type']) . '">'
        . '<span class="pt-post-event__icon"><i class="fa-solid ' . opg_pt_event_icon($event['event_type']) . '"></i></span>'
        . '<div><div class="pt-post-event__label"><span>' . opg_pt_escape(opg_pt_event_label($event['event_type']))
        . '</span>' . $status . '</div><div class="pt-event-summary">' . opg_pt_event_summary($event) . $delta . '</div></div></li>';
}

function opg_pt_render_stream($thread, $participants, $positions, $selectedUid, $selectedType, $page)
{
    global $db;
    $tid = (int)$thread['tid'];
    $fid = (int)$thread['fid'];
    $allowedTypes = array('', 'con_datos', 'tecnica', 'objeto_mostrado', 'recurso');
    $selectedType = in_array($selectedType, $allowedTypes, true) ? $selectedType : '';
    $page = max(1, (int)$page);
    $perPage = 30;
    $visible = opg_pt_visible_posts_sql($fid, 'p');
    $where = "p.tid='{$tid}' AND {$visible}";
    if ($selectedUid > 0) {
        $where .= " AND p.uid='" . (int)$selectedUid . "'";
    }
    if ($selectedType !== '') {
        $eventType = $selectedType === 'con_datos' ? '' : " AND ef.event_type='" . $db->escape_string($selectedType) . "'";
        $where .= " AND EXISTS (SELECT 1 FROM {$db->table_prefix}op_panel_tactico_eventos ef
            WHERE ef.pid=p.pid AND ef.is_current=1 AND ef.visibility_scope='publico'{$eventType})";
    }

    $countQuery = $db->query("SELECT COUNT(*) AS total FROM {$db->table_prefix}posts p WHERE {$where}");
    $total = (int)$db->fetch_field($countQuery, 'total');
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $start = ($page - 1) * $perPage;
    $query = $db->query("SELECT p.pid,p.uid,p.username,p.dateline,
            COALESCE(NULLIF(p.username,''),u.username,'Invitado') AS author_name,
            ip.index_status,ip.event_count
        FROM {$db->table_prefix}posts p
        LEFT JOIN {$db->table_prefix}users u ON u.uid=p.uid
        LEFT JOIN {$db->table_prefix}op_panel_tactico_posts ip ON ip.pid=p.pid
        WHERE {$where}
        ORDER BY p.dateline ASC,p.pid ASC LIMIT {$start},{$perPage}");
    $posts = array();
    $pids = array();
    while ($row = $db->fetch_array($query)) {
        $pid = (int)$row['pid'];
        $posts[$pid] = $row;
        $pids[] = $pid;
    }

    $eventsByPost = array();
    if (!empty($pids)) {
        $eventWhere = "e.pid IN (" . implode(',', $pids) . ") AND e.is_current=1 AND e.visibility_scope='publico'";
        $eventQuery = $db->query("SELECT e.* FROM {$db->table_prefix}op_panel_tactico_eventos e
            WHERE {$eventWhere} ORDER BY e.post_dateline ASC,e.pid ASC,e.post_order ASC");
        while ($row = $db->fetch_array($eventQuery)) {
            $event = opg_pt_decode_event($row);
            $eventsByPost[(int)$event['pid']][] = $event;
        }
    }

    $html = opg_pt_render_filters($tid, $participants, $selectedUid, $selectedType)
        . '<section class="pt-section pt-log"><header><div><small>Orden del tema</small><h2>Bitacora tactica</h2></div>'
        . '<span>' . $total . ($total === 1 ? ' post' : ' posts') . '</span></header>';
    if (empty($posts)) {
        $html .= opg_pt_empty('No hay posts que mostrar', 'Cambia los filtros o analiza los posts anteriores del tema.');
    } else {
        $resourceChanges = opg_pt_resource_changes($tid, $fid);
        $html .= '<div class="pt-post-stream">';
        foreach ($posts as $pid => $post) {
            $events = $eventsByPost[$pid] ?? array();
            $position = (int)($positions[$pid] ?? 0);
            $indexStatus = (string)($post['index_status'] ?? '');
            if ($indexStatus === '') {
                $status = '<span class="pt-post-state pt-post-state--pending">Pendiente de analizar</span>';
            } elseif ($indexStatus === 'error') {
                $status = '<span class="pt-post-state pt-post-state--error">Error de lectura</span>';
            } elseif (empty($events)) {
                $status = '<span class="pt-post-state">Sin datos tacticos</span>';
            } else {
                $status = '<span class="pt-post-state pt-post-state--ready">' . count($events)
                    . (count($events) === 1 ? ' dato' : ' datos') . '</span>';
            }
            $html .= '<article class="pt-post-entry"><div class="pt-post-entry__marker"><span>' . $position . '</span></div>'
                . '<div class="pt-post-entry__card"><header><div><strong>' . opg_pt_escape($post['author_name']) . '</strong>'
                . '<time>' . opg_pt_escape(my_date('relative', (int)$post['dateline'])) . '</time></div>'
                . '<div>' . $status . '<a href="' . opg_pt_post_url($tid, $pid) . '">Abrir post <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div></header>';
            if (!empty($events)) {
                $html .= '<ol class="pt-post-events">';
                foreach ($events as $event) {
                    $html .= opg_pt_render_post_event($event, $resourceChanges);
                }
                $html .= '</ol>';
            } else {
                $emptyMessage = $indexStatus === ''
                    ? 'Analiza este post para comprobar si contiene datos tacticos.'
                    : ($indexStatus === 'error'
                        ? 'No se pudo leer la informacion tactica de este post.'
                        : 'Este post no contiene codigos tacticos compatibles.');
                $html .= '<p class="pt-post-entry__empty">' . $emptyMessage . '</p>';
            }
            $html .= '</div></article>';
        }
        $html .= '</div>';
    }
    $html .= '</section>';

    if ($pages > 1) {
        $html .= '<nav class="pt-pagination" aria-label="Paginas de la bitacora tactica">';
        for ($i = 1; $i <= $pages; $i++) {
            $class = $i === $page ? ' class="is-active" aria-current="page"' : '';
            $html .= '<a' . $class . ' href="' . opg_pt_page_url($tid, array(
                'uid' => $selectedUid,
                'tipo' => $selectedType,
                'pagina' => $i,
            )) . '">' . $i . '</a>';
        }
        $html .= '</nav>';
    }
    return $html;
}

$threadSubject = htmlspecialchars_uni($thread['subject']);
$threadUrl = rtrim($mybb->settings['bburl'], '/') . '/showthread.php?tid=' . $tid;
$participants = array();
$state = array('total' => 0, 'indexed' => 0, 'errors' => 0, 'indexed_at' => 0);
$counts = array('tecnica' => 0, 'objeto_mostrado' => 0, 'recurso' => 0, 'posts_con_datos' => 0);

if (!opg_pt_tables_ready()) {
    $pt_summary = '';
    $pt_content = opg_pt_empty('Panel pendiente de instalar', 'Activa el plugin OPG - Panel tactico para crear su indice.');
    $indexStatus = 'No instalado';
    $indexStatusClass = 'is-error';
} else {
    $participants = opg_pt_load_participants($tid, $fid);
    $state = opg_pt_index_state($tid, $fid);
    $counts = opg_pt_load_event_counts($tid, $fid);
    $positions = opg_pt_post_positions($tid, $fid);
    $uidFilter = max(0, $mybb->get_input('uid', MyBB::INPUT_INT));
    $typeFilter = $mybb->get_input('tipo');
    $pt_content = opg_pt_render_stream(
        $thread,
        $participants,
        $positions,
        $uidFilter,
        $typeFilter,
        $mybb->get_input('pagina', MyBB::INPUT_INT)
    );
    $eventTotal = $counts['tecnica'] + $counts['objeto_mostrado'] + $counts['recurso'];
    $pt_summary = '<section class="pt-metrics" aria-label="Resumen del tema">'
        . '<div><i class="fa-solid fa-comments"></i><strong>' . $state['total'] . '</strong><span>Posts visibles</span></div>'
        . '<div><i class="fa-solid fa-list-check"></i><strong>' . $counts['posts_con_datos'] . '</strong><span>Posts con datos</span></div>'
        . '<div><i class="fa-solid fa-users"></i><strong>' . count($participants) . '</strong><span>Participantes</span></div>'
        . '<div><i class="fa-solid fa-bolt"></i><strong>' . $eventTotal . '</strong><span>Datos registrados</span></div></section>';

    if ($state['errors'] > 0) {
        $indexStatus = 'Indice parcial';
        $indexStatusClass = 'is-warning';
    } elseif ($state['indexed'] < $state['total']) {
        $indexStatus = 'Pendiente: ' . ($state['total'] - $state['indexed']) . ' posts';
        $indexStatusClass = 'is-warning';
    } else {
        $indexStatus = 'Actualizado';
        $indexStatusClass = 'is-ready';
    }
}

$pt_reindex = '';
if ($canReindex && opg_pt_tables_ready() && $state['indexed'] < $state['total']) {
    $afterPid = max(0, $mybb->get_input('next_pid', MyBB::INPUT_INT));
    $pending = max(0, $state['total'] - $state['indexed']);
    $batchSize = min(100, $pending);
    $buttonLabel = $afterPid > 0 ? 'Analizar los siguientes ' . $batchSize . ' posts' : 'Analizar posts anteriores';
    $pt_reindex = '<form method="post" class="pt-reindex"><div><strong>Completar la bitacora</strong>'
        . '<small>Se procesaran hasta ' . $batchSize . ' posts antiguos sin modificar su contenido.</small></div>'
        . '<input type="hidden" name="my_post_key" value="' . opg_pt_escape($mybb->post_code) . '">'
        . '<input type="hidden" name="action" value="reindex"><input type="hidden" name="tid" value="' . $tid . '">'
        . '<input type="hidden" name="after_pid" value="' . $afterPid . '"><button type="submit" class="btn-op btn-op--secundario">'
        . '<i class="fa-solid fa-rotate"></i> ' . $buttonLabel . '</button></form>';
}

$pt_notice = $notice;
$pt_title = 'Panel tactico';
$pt_thread_subject = $threadSubject;
$pt_thread_url = opg_pt_escape($threadUrl);
$pt_index_status = opg_pt_escape($indexStatus);
$pt_index_status_class = $indexStatusClass;
$pt_index_detail = $state['indexed'] . ' de ' . $state['total'] . ' posts analizados';
$pt_last_indexed = $state['indexed_at'] > 0 ? my_date('relative', $state['indexed_at']) : 'Todavia no analizado';

add_breadcrumb($threadSubject, $threadUrl);
add_breadcrumb('Panel tactico');

$templatePath = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_panel_tactico.html';
$template = is_file($templatePath)
    ? str_replace("\\'", "'", addslashes(file_get_contents($templatePath)))
    : $templates->get('op_panel_tactico');
eval('$page = "' . $template . '";');
output_page($page);
