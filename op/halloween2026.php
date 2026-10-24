<?php
/** La Cosecha de Calabazas Malditas 2026. */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'halloween2026.php');
require_once './../global.php';
require_once './functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_store.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_stats.php';

global $mybb, $db, $templates, $header, $headerinclude, $footer;

if (!opg_hlw_tables_ready()) {
    error('El evento de Halloween todavía no está disponible.');
}

$uid = (int)$mybb->user['uid'];
$ficha = opg_hlw_eligible_character($uid);
if ($uid <= 0 || !$ficha) {
    error_no_permission();
}

function opg_hlw_page_is_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
}

function opg_hlw_page_url($view, $params = array())
{
    $params = array_merge(array('vista' => $view), $params);
    return '/op/halloween2026.php?' . http_build_query($params, '', '&');
}

function opg_hlw_page_num($value)
{
    return number_format((int)$value, 0, ',', '.');
}

function opg_hlw_page_pagination($view, $page, $perPage, $total)
{
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="hlw-pagination" aria-label="Paginacion">';
    for ($i = 1; $i <= $pages; $i++) {
        $url = opg_hlw_page_url($view, array('pagina' => $i));
        $html .= '<a class="' . ($i === $page ? 'is-active' : '') . '" href="' . $url . '"'
            . ' hx-get="' . $url . '" hx-target="#hlw-panel" hx-swap="outerHTML" hx-push-url="true">' . $i . '</a>';
    }
    return $html . '</nav>';
}

function opg_hlw_render_event($config)
{
    $settings = $config['settings'];
    $main = opg_hlw_escape($settings['main_thread_url'] ?? 'https://onepiecegaiden.com/showthread.php?tid=9471');
    $stories = opg_hlw_escape($settings['stories_thread_url'] ?? 'https://onepiecegaiden.com/showthread.php?tid=9535');
    $monsters = opg_hlw_escape($settings['monsters_thread_url'] ?? 'https://onepiecegaiden.com/showthread.php?tid=9536');
    return '<section class="hlw-event-hero">'
        . '<img src="/images/op/halloween2026/portada-evento.jpg" alt="La Cosecha de Calabazas Malditas" fetchpriority="high" style="object-position:center 38%">'
        . '<div class="hlw-event-hero__shade"></div><div class="hlw-event-hero__content"><p>26 OCT - 16 NOV</p>'
        . '<h2>La cosecha ha comenzado</h2><span>Publica en las zonas de rol y convierte cada historia en parte de la leyenda del evento.</span></div></section>'
        . '<section class="hlw-earn"><div class="hlw-section-heading"><div><p>Recompensas automaticas</p><h2>Como conseguir Calabazas</h2></div>'
        . '<span>El dia cambia a las 06:00, hora de Madrid</span></div><div class="hlw-rules">'
        . '<div><i class="fa-solid fa-feather-pointed"></i><strong>+1</strong><span>Post en zona de rol</span></div>'
        . '<div><i class="fa-solid fa-book-open"></i><strong>+2</strong><span>Tema Comun o Aventura</span></div>'
        . '<div><i class="fa-solid fa-ghost"></i><strong>+3</strong><span>Tema con etiqueta Spooky</span></div>'
        . '<div><i class="fa-solid fa-sun"></i><strong>+1</strong><span>Primer post del dia</span></div></div></section>'
        . '<section class="hlw-event-grid"><div class="hlw-calendar"><div class="hlw-section-heading"><div><p>Calendario</p><h2>Fechas de la travesia</h2></div></div>'
        . '<ol><li><time>26 OCT</time><div><strong>Comienza la cosecha</strong><span>Se activa la obtencion automatica.</span></div></li>'
        . '<li><time>16 NOV</time><div><strong>Ultimo dia</strong><span>Cierra la obtencion y la participacion.</span></div></li>'
        . '<li><time>17-22 NOV</time><div><strong>Votaciones</strong><span>La comunidad elige a sus favoritos.</span></div></li>'
        . '<li><time>23 NOV</time><div><strong>Ganadores</strong><span>Los nombres quedan para la posteridad.</span></div></li></ol></div>'
        . '<aside class="hlw-links"><h3>Cuaderno de bitacora</h3>'
        . '<a href="' . $main . '"><i class="fa-solid fa-scroll" aria-hidden="true"></i><span><strong>Tema principal</strong><small>Reglas, anuncios y novedades</small></span><i class="fa-solid fa-arrow-right"></i></a>'
        . '<a href="' . $stories . '"><i class="fa-solid fa-book-skull" aria-hidden="true"></i><span><strong>Historias de miedo</strong><small>Concurso de relatos siniestros</small></span><i class="fa-solid fa-arrow-right"></i></a>'
        . '<a href="' . $monsters . '"><i class="fa-solid fa-paw" aria-hidden="true"></i><span><strong>Monstruos salvajes</strong><small>Concurso de criaturas aterradoras</small></span><i class="fa-solid fa-arrow-right"></i></a>'
        . '</aside></section>';
}

function opg_hlw_render_stats()
{
    global $mybb;
    $summary = opg_hlw_stats_summary();
    $page = max(1, (int)($GLOBALS['mybb']->get_input('pagina', MyBB::INPUT_INT)));
    $ranking = opg_hlw_stats_ranking($page, 20);
    $leaders = opg_hlw_stats_ranking(1, 3);
    $personal = opg_hlw_stats_user_position((int)$mybb->user['uid']);
    $cards = array(
        array('fa-solid fa-seedling', 'Calabazas obtenidas', $summary['earned']),
        array('fa-solid fa-users', 'Participantes', $summary['participants']),
        array('fa-solid fa-feather-pointed', 'Posts validos', $summary['posts']),
        array('fa-solid fa-bag-shopping', 'Compras', $summary['purchases']),
    );
    $html = '<img class="hlw-banner" src="https://i.imgur.com/tYndMUv.jpeg" alt="Estadisticas de la Cosecha de Calabazas Malditas">'
        . '<div class="hlw-metrics">';
    foreach ($cards as $card) {
        $html .= '<div><i class="' . $card[0] . '"></i><span>' . opg_hlw_page_num($card[2]) . '</span><small>' . $card[1] . '</small></div>';
    }
    $html .= '</div>';
    if (!empty($leaders['rows'])) {
        $podium = array();
        foreach ($leaders['rows'] as $leader) {
            $name = trim((string)$leader['nombre']) ?: (trim((string)$leader['username']) ?: 'Personaje #' . (int)$leader['uid']);
            $podium[(int)$leader['position']] = '<a class="hlw-podium__place hlw-podium__place--' . (int)$leader['position'] . '" href="/op/personaje.php?uid=' . (int)$leader['uid'] . '">'
                . '<span><i class="fa-solid fa-crown"></i> #' . (int)$leader['position'] . '</span><strong>' . opg_hlw_escape($name) . '</strong>'
                . '<b>' . opg_hlw_page_num($leader['ganado_total']) . ' Calabazas</b></a>';
        }
        $html .= '<section class="hlw-podium"><div class="hlw-section-heading"><div><p>Podio</p><h2>La cosecha mas abundante</h2></div>'
            . ($personal ? '<span>Tu puesto actual: <strong>#' . (int)$personal['position'] . '</strong></span>' : '<span>Aun no has entrado en la clasificacion</span>')
            . '</div><div class="hlw-podium__places">' . ($podium[2] ?? '') . ($podium[1] ?? '') . ($podium[3] ?? '') . '</div></section>';
    }
    $html .= '<section class="hlw-ranking"><div class="hlw-section-heading"><div><p>Clasificacion general</p><h2>Grandes recolectores</h2></div>'
        . '<span>Ordenado por total obtenido</span></div><div class="hlw-table" role="table">'
        . '<div class="hlw-table__head" role="row"><span>Puesto</span><span>Personaje</span><span>Obtenidas</span><span>Saldo</span></div>';
    if ((int)$ranking['total'] <= 3) {
        $html .= '<p class="hlw-empty">Los primeros recolectores ya ocupan el podio. El resto de la clasificacion aparecera aqui.</p>';
    }
    foreach ($ranking['rows'] as $row) {
        if ((int)$row['position'] <= 3) continue;
        $name = trim((string)$row['nombre']) ?: (trim((string)$row['username']) ?: 'Personaje #' . (int)$row['uid']);
        $mine = (int)$row['uid'] === (int)$mybb->user['uid'] ? ' is-current' : '';
        $html .= '<div class="hlw-table__row' . $mine . '" role="row"><strong>#' . (int)$row['position'] . '</strong>'
            . '<a href="/op/personaje.php?uid=' . (int)$row['uid'] . '">' . opg_hlw_escape($name) . '</a>'
            . '<span>' . opg_hlw_page_num($row['ganado_total']) . '</span><span>' . opg_hlw_page_num($row['saldo']) . '</span></div>';
    }
    return $html . '</div>' . opg_hlw_page_pagination('estadisticas', $page, $ranking['per_page'], $ranking['total']) . '</section>';
}

function opg_hlw_reward_text($reward)
{
    $parts = array();
    if (!empty($reward['objects'])) {
        foreach ($reward['objects'] as $object) $parts[] = (int)$object['quantity'] . ' ' . opg_hlw_escape($object['id']);
    }
    if (!empty($reward['nikas'])) $parts[] = opg_hlw_page_num($reward['nikas']) . ' Nikas';
    if (!empty($reward['berries'])) $parts[] = opg_hlw_page_num($reward['berries']) . ' Berries';
    if (!empty($reward['craft_points'])) $parts[] = opg_hlw_page_num($reward['craft_points']) . ' puntos de oficio';
    return implode(' - ', $parts);
}

function opg_hlw_render_store($uid, $message = '')
{
    global $mybb, $db;
    $balance = opg_hlw_balance($uid);
    $items = opg_hlw_store_items();
    $open = opg_hlw_store_is_open();
    $token = opg_hlw_purchase_token();
    $html = '<img class="hlw-banner" src="https://i.imgur.com/kdGOe7n.jpeg" alt="Tienda de Calabazas Malditas">';
    $purchaseId = max(0, $mybb->get_input('compra', MyBB::INPUT_INT));
    if ($purchaseId > 0) {
        $receipt = opg_hlw_store_receipt($purchaseId, $uid, false);
        if ($receipt && $receipt['estado'] === 'completed') {
            $query = $db->simple_select('op_halloween2026_movimientos', 'metadata_json',
                "source_type='purchase' AND source_id='" . (int)$purchaseId . "'", array('limit' => 1));
            $meta = json_decode((string)$db->fetch_field($query, 'metadata_json'), true) ?: array();
            $html .= '<section class="hlw-receipt"><div><p>Compra completada</p><h2>Recibo #' . $purchaseId . '</h2></div>'
                . '<dl><div><dt>Saldo anterior</dt><dd>' . opg_hlw_page_num($meta['saldo_anterior'] ?? 0) . '</dd></div>'
                . '<div><dt>Coste</dt><dd>-' . opg_hlw_page_num($receipt['total']) . '</dd></div>'
                . '<div><dt>Saldo final</dt><dd>' . opg_hlw_page_num($meta['saldo_final'] ?? 0) . '</dd></div></dl><ul>';
            foreach ($receipt['lines'] as $line) {
                $html .= '<li><strong>' . (int)$line['cantidad'] . ' x ' . opg_hlw_escape($line['nombre_snapshot']) . '</strong>'
                    . '<span>' . opg_hlw_reward_text($line['reward']) . '</span></li>';
            }
            $html .= '</ul></section>';
        }
    }
    if ($message !== '') $html .= $message;
    if (!$open) {
        $html .= '<div class="hlw-alert hlw-alert--info"><i class="fa-solid fa-lock"></i><div><strong>La tienda aun esta cerrada</strong>'
            . '<span>La cosecha ya puede avanzar. Las compras se abriran despues de la comprobacion inicial.</span></div></div>';
    }
    $html .= '<form method="post" action="' . opg_hlw_page_url('tienda') . '" class="hlw-store-layout" data-hlw-cart><div class="hlw-catalog">'
        . '<input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
        . '<input type="hidden" name="action" value="purchase"><input type="hidden" name="purchase_token" value="' . opg_hlw_escape($token) . '">';
    foreach ($items as $item) {
        $image = trim((string)$item['imagen']);
        if ($item['slug'] === 'cofre-calabaza') {
            $image = 'https://i.imgur.com/87wW3Xt.jpeg';
        }
        $visual = $image !== '' ? '<img src="' . opg_hlw_escape($image) . '" alt="" loading="lazy">'
            : '<i class="fa-solid fa-coins" aria-hidden="true"></i>';
        $featured = $item['slug'] === 'cofre-calabaza' ? ' hlw-product--featured' : '';
        $html .= '<article class="hlw-product' . $featured . '" data-price="' . (int)$item['coste'] . '"><div class="hlw-product__visual">' . $visual . '</div>'
            . '<div class="hlw-product__body"><small>' . opg_hlw_escape($item['categoria']) . '</small><h3>' . opg_hlw_escape($item['nombre']) . '</h3>'
            . '<p>' . opg_hlw_escape($item['descripcion']) . '</p><span class="hlw-product__reward">' . opg_hlw_reward_text($item['reward']) . '</span></div>'
            . '<div class="hlw-product__buy"><strong><i class="fa-solid fa-seedling"></i> ' . (int)$item['coste'] . '</strong>'
            . '<label><span class="sr-only">Cantidad de ' . opg_hlw_escape($item['nombre']) . '</span>'
            . '<button type="button" data-step="-1" aria-label="Reducir"><i class="fa-solid fa-minus"></i></button>'
            . '<input type="number" name="items[' . (int)$item['id'] . ']" min="0" max="100" value="0" inputmode="numeric">'
            . '<button type="button" data-step="1" aria-label="Aumentar"><i class="fa-solid fa-plus"></i></button></label></div></article>';
    }
    $disabled = $open ? '' : ' disabled data-store-disabled="true"';
    $html .= '</div><div class="hlw-store-sidebar"><img class="hlw-cart__decoration" src="https://i.imgur.com/G5okouN.png" alt="" aria-hidden="true"><aside class="hlw-cart">'
        . '<p>Tu saldo</p><strong>' . opg_hlw_page_num($balance['saldo']) . ' <span>Calabazas</span></strong>'
        . '<div><span>Total de la compra</span><b data-cart-total>0</b></div>'
        . '<div><span>Saldo restante</span><b data-cart-remaining>' . opg_hlw_page_num($balance['saldo']) . '</b></div>'
        . '<button class="hlw-button hlw-button--primary" type="submit" data-cart-submit data-balance="' . (int)$balance['saldo'] . '"' . $disabled . '>'
        . '<i class="fa-solid fa-bag-shopping"></i> Comprar</button><small>Maximo 100 unidades por articulo y operacion.</small></aside></div></form>';
    return $html;
}

function opg_hlw_render_activity($uid)
{
    global $mybb;
    $balance = opg_hlw_balance($uid);
    $page = max(1, (int)$mybb->get_input('pagina', MyBB::INPUT_INT));
    $activity = opg_hlw_stats_activity($uid, $page, 25);
    $html = '<div class="hlw-balance-grid"><div><small>Saldo disponible</small><strong>' . opg_hlw_page_num($balance['saldo']) . '</strong></div>'
        . '<div><small>Total obtenido</small><strong>' . opg_hlw_page_num($balance['ganado_total']) . '</strong></div>'
        . '<div><small>Total gastado</small><strong>' . opg_hlw_page_num($balance['gastado_total']) . '</strong></div></div>'
        . '<section class="hlw-activity"><div class="hlw-section-heading"><div><p>Tu recorrido</p><h2>Historial de Calabazas</h2></div></div>';
    if (empty($activity['rows'])) {
        $html .= '<p class="hlw-empty">Todavia no tienes movimientos en este evento.</p>';
    } else {
        $currentDay = '';
        $icons = array('post' => 'fa-feather-pointed', 'thread' => 'fa-book-open', 'spooky' => 'fa-ghost', 'daily' => 'fa-sun', 'purchase' => 'fa-bag-shopping', 'gift' => 'fa-gift');
        foreach ($activity['rows'] as $row) {
            $positive = (int)$row['amount'] > 0;
            $day = date('d/m/Y', strtotime((string)$row['created_at'] . ' UTC'));
            if ($day !== $currentDay) {
                $currentDay = $day;
                $html .= '<h3 class="hlw-activity__day"><span>' . opg_hlw_escape($day) . '</span></h3>';
            }
            $link = '';
            if ((int)$row['source_pid'] > 0) $link = '<a href="/showthread.php?pid=' . (int)$row['source_pid'] . '#pid' . (int)$row['source_pid'] . '">Ver publicacion</a>';
            elseif ((int)$row['source_tid'] > 0) $link = '<a href="/showthread.php?tid=' . (int)$row['source_tid'] . '">Ver tema</a>';
            $icon = $icons[$row['source_type']] ?? ($positive ? 'fa-circle-plus' : 'fa-circle-minus');
            $html .= '<div class="hlw-movement"><i class="fa-solid ' . $icon . '"></i>'
                . '<div><strong>' . opg_hlw_escape(opg_hlw_stats_source_label($row['source_type'])) . '</strong><small>'
                . opg_hlw_escape($row['note']) . ' - ' . opg_hlw_escape($row['created_at']) . '</small></div>' . $link
                . '<b class="' . ($positive ? 'is-positive' : 'is-negative') . '">' . ($positive ? '+' : '') . (int)$row['amount'] . '</b></div>';
        }
    }
    return $html . opg_hlw_page_pagination('actividad', $page, $activity['per_page'], $activity['total']) . '</section>';
}

$allowedViews = array('evento', 'estadisticas', 'tienda', 'actividad');
$view = $mybb->get_input('vista');
if (!in_array($view, $allowedViews, true)) $view = 'evento';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mybb->get_input('action') === 'purchase') {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        $message = '<div class="hlw-alert hlw-alert--error">La sesion del formulario vencio. Recarga la pagina.</div>';
    } else {
        $result = opg_hlw_store_purchase($uid, $_POST['items'] ?? array(), $mybb->get_input('purchase_token'));
        if (!empty($result['ok'])) {
            header('Location: ' . opg_hlw_page_url('tienda', array('compra' => (int)$result['purchase_id'])));
            exit;
        }
        $errors = array(
            'store_closed' => 'La tienda todavia no esta abierta.',
            'invalid_cart' => 'Elige al menos un articulo.',
            'insufficient_balance' => 'No tienes suficientes Calabazas para esta compra.',
            'item_unavailable' => 'Uno de los articulos ya no esta disponible.',
        );
        $message = '<div class="hlw-alert hlw-alert--error"><i class="fa-solid fa-triangle-exclamation"></i> '
            . opg_hlw_escape($errors[$result['code'] ?? ''] ?? 'No se pudo completar la compra.') . '</div>';
    }
}

if ($view === 'evento') $content = opg_hlw_render_event(opg_hlw_config());
elseif ($view === 'estadisticas') $content = opg_hlw_render_stats();
elseif ($view === 'tienda') $content = opg_hlw_render_store($uid, $message);
else $content = opg_hlw_render_activity($uid);

$panel = '<div id="hlw-panel" class="hlw-panel" data-view="' . opg_hlw_escape($view) . '">' . $content . '</div>';
if (opg_hlw_page_is_htmx()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $panel;
    exit;
}

$balance = opg_hlw_balance($uid);
$pageConfig = opg_hlw_config();
$eventStart = strtotime((string)$pageConfig['earn_start_utc'] . ' UTC');
$eventEnd = strtotime((string)$pageConfig['earn_end_utc'] . ' UTC');
$eventNow = time();
$eventDuration = max(1, $eventEnd - $eventStart);
$hlw_event_progress = max(0, min(100, (int)round((($eventNow - $eventStart) / $eventDuration) * 100)));
if ($eventNow < $eventStart) {
    $hlw_event_state = 'Proximamente';
    $hlw_event_timing = 'Comienza el 26 de octubre a las 06:00';
    $hlw_event_state_class = 'is-upcoming';
} elseif ($eventNow < $eventEnd) {
    $daysLeft = max(1, (int)ceil(($eventEnd - $eventNow) / 86400));
    $hlw_event_state = 'Evento activo';
    $hlw_event_timing = $daysLeft === 1 ? 'Queda 1 dia' : 'Quedan ' . $daysLeft . ' dias';
    $hlw_event_state_class = 'is-active';
} else {
    $hlw_event_state = 'Cosecha finalizada';
    $hlw_event_timing = 'Finalizo el 16 de noviembre';
    $hlw_event_state_class = 'is-ended';
}
$hlw_balance = opg_hlw_page_num($balance['saldo']);
$hlw_panel = $panel;
$hlw_view = $view;
$hlw_event_url = opg_hlw_page_url('evento');
$hlw_stats_url = opg_hlw_page_url('estadisticas');
$hlw_store_url = opg_hlw_page_url('tienda');
$hlw_activity_url = opg_hlw_page_url('actividad');
$hlw_event_active = $view === 'evento' ? ' is-active' : '';
$hlw_stats_active = $view === 'estadisticas' ? ' is-active' : '';
$hlw_store_active = $view === 'tienda' ? ' is-active' : '';
$hlw_activity_active = $view === 'actividad' ? ' is-active' : '';
$hlwScriptPath = MYBB_ROOT . 'jscripts/halloween2026.js';
$hlw_js_version = is_file($hlwScriptPath) ? (int)filemtime($hlwScriptPath) : 1;

$templatePath = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_halloween2026.html';
$template = is_file($templatePath) ? str_replace("\\'", "'", addslashes(file_get_contents($templatePath))) : $templates->get('op_halloween2026');
eval('$page = "' . $template . '";');
output_page($page);
