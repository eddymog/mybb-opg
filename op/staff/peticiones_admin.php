<?php
/**
 * Staff - Peticiones administrativas (ficha, tema, combate, técnica, otros
 * + solicitudes de tripulación)
 *
 * Antes compartía template (staff_peticiones_mod) con
 * op/staff/peticiones_bugs.php — ahora tiene su propio template dedicado
 * (staff_peticiones_admin), ver el docblock de peticiones_bugs.php para el
 * motivo.
 *
 * Reescrito por varios problemas reales:
 * 1. Inyección SQL: `peti_id` (GET, para "resolver"/"borrar") sin
 *    escapar/castear.
 * 2. "Borrar" borraba por `uid` en vez de por `id` (hubiera borrado TODAS
 *    las peticiones de un usuario, no una puntual) — de todos modos el
 *    botón estaba comentado en el HTML, así que solo era explotable
 *    armando la URL a mano.
 * 3. CSRF: "Resolver"/"Borrar" eran simples `<a href>` (GET) sin token, y
 *    el endpoint AJAX `asignar_mod` tampoco lo validaba (a diferencia de
 *    `guardar_notas_mod`, que sí). Ahora los tres llevan `my_post_key`.
 * 4. XSS: `nombre`, `resumen`, `descripcion`, `url` y `mod_nombre` (texto
 *    libre enviado por el usuario) se mostraban sin escapar.
 * 5. El endpoint `asignar_mod` devolvía el SQL crudo (`'sql' => $sql`) en
 *    la respuesta JSON — resto de debug, se saca.
 * 6. Permisos solo se chequeaban al final; ahora es lo primero.
 * 7. Bloque de código muerto (un `if`/`else if` comentado, duplicado del
 *    que sigue justo debajo) eliminado.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticiones_admin.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid) && !is_user($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

// Normalizador robusto para comparar nombres de moderador contra el select
// (mismo texto puede llegar con NBSP/ZWSP/mayúsculas distintas).
function norm_name($s)
{
    $s = (string) $s;
    $s = str_replace(array("\xC2\xA0", "\xE2\x80\x8B"), ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = trim($s);
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

// --- Asignar moderador (atendidoPor) ---
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'asignar_mod') {
    header('Content-Type: application/json; charset=utf-8');

    if (!is_mod($uid) && !is_staff($uid)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'No autorizado'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!verify_post_check($mybb->get_input('my_post_key', MyBB::INPUT_STRING), true)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'CSRF inválido'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $peti_id = (int) $mybb->get_input('peti_id', MyBB::INPUT_INT);
    $nombre_mod = trim($mybb->get_input('nombre_mod', MyBB::INPUT_STRING));

    if ($peti_id <= 0) {
        echo json_encode(array('success' => false, 'error' => 'peti_id inválido'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($nombre_mod === 'Sin asignar' || $nombre_mod === '—') {
        $nombre_mod = '';
    }

    $db->query("UPDATE `mybb_op_peticiones` SET `atendidoPor`='" . $db->escape_string($nombre_mod) . "' WHERE `id`='{$peti_id}' LIMIT 1");

    echo json_encode(array(
        'success' => true,
        'atendidoPor' => $nombre_mod,
        'peti_id' => $peti_id,
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Guardar notas de moderación (notasMod) ---
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'guardar_notas_mod') {
    header('Content-Type: application/json; charset=utf-8');

    if (!is_mod($uid) && !is_staff($uid)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'No autorizado'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!verify_post_check($mybb->get_input('my_post_key', MyBB::INPUT_STRING), true)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'CSRF inválido'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $peti_id = (int) $mybb->get_input('peti_id', MyBB::INPUT_INT);
    $notas = trim((string) $mybb->get_input('notas', MyBB::INPUT_STRING));

    if ($peti_id <= 0) {
        echo json_encode(array('success' => false, 'error' => 'peti_id inválido'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (mb_strlen($notas, 'UTF-8') > 5000) {
        $notas = mb_substr($notas, 0, 5000, 'UTF-8');
    }

    $db->query("UPDATE `mybb_op_peticiones` SET `notasMod`='" . $db->escape_string($notas) . "' WHERE `id`='{$peti_id}' LIMIT 1");

    echo json_encode(array('success' => true, 'peti_id' => $peti_id, 'notas' => $notas), JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Resolver / Borrar (enlaces con token en la URL) ---
$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
$peti_id_get = (int) $mybb->get_input('peti_id', MyBB::INPUT_INT);

if ($accion !== '' && $peti_id_get > 0 && (is_mod($uid) || is_staff($uid)) && in_array($accion, array('resolver', 'borrar'), true)) {
    verify_post_check($mybb->get_input('my_post_key'));

    if ($accion === 'resolver') {
        $db->query("UPDATE `mybb_op_peticiones` SET `resuelto`=1, `mod_uid`='{$uid}', `mod_nombre`='" . $db->escape_string($username) . "' WHERE `id`='{$peti_id_get}'");
    } else {
        $db->query("DELETE FROM `mybb_op_peticiones` WHERE `id`='{$peti_id_get}'");
    }

    header('Location: /op/staff/peticiones_admin.php');
    exit;
}

// --- Listado ---

// Categorías reales de mybb_op_peticiones que muestra esta página (no
// incluye 'programacion', que es la de op/staff/peticiones_bugs.php).
// 'tripulacion' es un valor especial para el filtro: no es una fila de
// mybb_op_peticiones, cambia a la tabla mybb_op_tripulaciones_solicitudes.
$CATEGORIAS = array(
    'ficha' => 'Ajustes de Ficha y Recursos',
    'tema' => 'Petición de Narración',
    'combate' => 'Moderación de Combate',
    'tecnica' => 'Técnicas, Akumas y Estilos',
    'otros' => 'Otras Moderaciones',
);
define('PA_POR_PAGINA', 20);

$resuelto = $mybb->get_input('resuelto', MyBB::INPUT_STRING) === '1' ? '1' : '0';
$categoria_filtro = $mybb->get_input('categoria', MyBB::INPUT_STRING);
if ($categoria_filtro !== 'tripulacion' && !isset($CATEGORIAS[$categoria_filtro])) {
    $categoria_filtro = '';
}
$atendido_filtro = trim($mybb->get_input('atendido', MyBB::INPUT_STRING));
$pagina = max(1, (int) $mybb->get_input('pagina', MyBB::INPUT_INT));
$post_key = generate_post_check();

// Lista de moderadores/staff/admin para "Asignar a" — sale de los mismos
// criterios que ya usan is_staff()/is_mod()/is_user() en op_functions.php:
// grupo de usuario (4, 6, 14, 16 + additionalgroups) unido a la lista de
// UIDs sueltos que esas funciones tratan como staff aunque su grupo de
// MyBB no lo refleje (copia literal de la de is_staff()).
$mods = array('Sin asignar');
$staff_uids_sueltos = '1,3,4,5,6,7,9,10,16,17,23,25,90,117,118,121,123,154,157,213,252,258,263,304,870';
$mods_query = $db->query("
    SELECT DISTINCT `username` FROM `mybb_users`
    WHERE `usergroup` IN (4,6,14,16)
       OR `additionalgroups` LIKE '%14%'
       OR `uid` IN ({$staff_uids_sueltos})
    ORDER BY `username` ASC
");
while ($m = $db->fetch_array($mods_query)) {
    $mods[] = $m['username'];
}

/**
 * Lista plana (no agrupada por categoría) de mybb_op_peticiones, con
 * filtro opcional de categoría/moderador asignado y paginación real
 * (LIMIT/OFFSET + conteo total). Antes cada categoría se listaba aparte
 * con su propio LIMIT 100 fijo y sin forma de ver más — bien para
 * "pendientes" (pocas a la vez) pero mal para "resueltas", que solo
 * crece. Cada tarjeta ahora lleva su categoría como chip, ya que al no
 * agrupar por categoría hace falta indicarla en cada una.
 */
function pa_listar_peticiones($resuelto, $categoria_filtro, $atendido_filtro, $pagina, $mods, $post_key, $categorias)
{
    global $db, $uid;

    $where = "`resuelto`='" . $db->escape_string($resuelto) . "'";
    if ($categoria_filtro !== '') {
        $where .= " AND `categoria`='" . $db->escape_string($categoria_filtro) . "'";
    } else {
        $where .= " AND `categoria` IN ('" . implode("','", array_map(array($db, 'escape_string'), array_keys($categorias))) . "')";
    }
    if ($atendido_filtro !== '') {
        if ($atendido_filtro === 'Sin asignar') {
            $where .= " AND (`atendidoPor`='' OR `atendidoPor` IS NULL)";
        } else {
            $where .= " AND `atendidoPor`='" . $db->escape_string($atendido_filtro) . "'";
        }
    }

    $total = (int) $db->fetch_field($db->query("SELECT COUNT(*) AS c FROM `mybb_op_peticiones` WHERE {$where}"), 'c');
    $total_paginas = max(1, (int) ceil($total / PA_POR_PAGINA));
    $pagina = min($pagina, $total_paginas);
    $offset = ($pagina - 1) * PA_POR_PAGINA;

    $query = $db->query("SELECT * FROM `mybb_op_peticiones` WHERE {$where} ORDER BY `id` DESC LIMIT " . PA_POR_PAGINA . " OFFSET {$offset}");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        $categoria_esc = htmlspecialchars($categorias[$q['categoria']] ?? $q['categoria'], ENT_QUOTES, 'UTF-8');
        $pid = (int) $q['id'];
        $u_uid = (int) $q['uid'];
        $nombre_esc = htmlspecialchars($q['nombre'], ENT_QUOTES, 'UTF-8');
        $resumen_esc = htmlspecialchars($q['resumen'], ENT_QUOTES, 'UTF-8');
        $descripcion_esc = nl2br(htmlspecialchars($q['descripcion'], ENT_QUOTES, 'UTF-8'));
        $url_esc = htmlspecialchars($q['url'], ENT_QUOTES, 'UTF-8');
        $enviado_esc = htmlspecialchars($q['enviado'], ENT_QUOTES, 'UTF-8');
        $mod_nombre_esc = htmlspecialchars($q['mod_nombre'], ENT_QUOTES, 'UTF-8');

        $fecha = '';
        try {
            $interval = (new DateTime())->diff(new DateTime($q['enviado']));
            $fecha = 'Hace ' . $interval->days . ' días.';
        } catch (Exception $e) {
        }

        $atendidoPor = (string) ($q['atendidoPor'] ?? '');
        $normAtendido = norm_name($atendidoPor);
        $notasMod = (string) ($q['notasMod'] ?? '');
        $select_id = 'sel_mod_' . $pid;
        $notes_id = 'notas_' . $pid;

        $html .= '<div class="pa-item">';

        $html .= '<div class="pa-item-contenido">';
        $html .= '<div class="pa-item-linea"><span class="opg-chip pa-chip-categoria">' . $categoria_esc . '</span></div>';
        $html .= '<div class="pa-item-linea">[<a href="/op/personaje.php?uid=' . $u_uid . '" target="_blank">' . $nombre_esc . ' - ' . $u_uid . '</a>]</div>';
        $html .= '<div class="pa-item-linea"><strong>Resumen</strong>: ' . $resumen_esc . '</div>';
        $html .= '<div class="pa-item-linea"><strong>Descripción</strong>: ' . $descripcion_esc . '</div>';
        $html .= '<div class="pa-item-linea"><strong>URL</strong>: <a href="' . $url_esc . '" target="_blank">' . $url_esc . '</a></div>';
        $html .= '<div class="pa-item-linea"><strong>Fecha</strong>: ' . $enviado_esc . ' - ' . $fecha . '</div>';
        $html .= '</div>';

        $html .= '<div class="pa-item-gestion">';
        $html .= '<div class="pa-item-linea"><strong>Atendido por</strong>: <span class="atendido-por">' . htmlspecialchars($atendidoPor !== '' ? $atendidoPor : 'Sin asignar', ENT_QUOTES, 'UTF-8') . '</span></div>';

        $html .= '<div class="pa-item-linea"><label for="' . $select_id . '"><strong>Asignar a</strong></label> ';
        $html .= '<select id="' . $select_id . '" class="sel-mod" data-pid="' . $pid . '">';
        foreach (array_map('trim', $mods) as $name) {
            $normOpt = norm_name($name);
            $isMatch = ($normOpt === $normAtendido) || ($normAtendido === '' && $normOpt === norm_name('Sin asignar'));
            $name_esc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $html .= '<option value="' . $name_esc . '"' . ($isMatch ? ' selected' : '') . '>' . $name_esc . '</option>';
        }
        $html .= '</select> <button type="button" class="opg-chip save-mod" data-pid="' . $pid . '" data-target-id="' . $select_id . '">Guardar</button></div>';

        $html .= '<div class="pa-item-notas">';
        $html .= '<label for="' . $notes_id . '"><strong>Notas de moderación</strong></label><br>';
        $html .= '<textarea id="' . $notes_id . '" class="notas-text" data-pid="' . $pid . '" rows="3">' . htmlspecialchars($notasMod, ENT_QUOTES, 'UTF-8') . '</textarea>';
        $html .= '<button type="button" class="opg-chip save-notas" data-pid="' . $pid . '" data-target-id="' . $notes_id . '">Guardar notas</button>';
        $html .= '</div>';

        if ($q['mod_nombre'] !== '') {
            $html .= '<div class="pa-item-linea"><strong>Moderador que ha resuelto</strong>: ' . $mod_nombre_esc . '</div>';
        }

        if (is_mod($uid) || is_staff($uid)) {
            $resolver_a = 'peticiones_admin.php?accion=resolver&peti_id=' . $pid . '&my_post_key=' . rawurlencode($post_key);
            $html .= '<div class="pa-item-acciones"><a class="opg-chip" href="' . htmlspecialchars($resolver_a, ENT_QUOTES, 'UTF-8') . '">Resolver</a></div>';
        }
        $html .= '</div>';

        $html .= '</div>';
    }

    return array('html' => $html, 'total' => $total, 'total_paginas' => $total_paginas, 'pagina' => $pagina);
}

/**
 * Solicitudes de creación de Tripulación (tabla propia,
 * mybb_op_tripulaciones_solicitudes — ver docs/200_Design_Tripulacion.md),
 * no mybb_op_peticiones, así que no puede reusar print_peticion() tal
 * cual. A diferencia de las demás categorías, NO se muestra el contenido
 * completo acá: cada fila es un link a tripulacion_solicitar.php, donde el
 * propio formulario (en modo revisión para staff) muestra todos los
 * campos y tiene los botones de Aceptar/Rechazar.
 */
function print_peticion_tripulacion($resuelto)
{
    global $db;
    $estado_filtro = $resuelto === '1' ? 'estado IN (1,2)' : 'estado = 0';
    $query = $db->query("
        SELECT s.*, f.nombre AS nombre_personaje
        FROM `mybb_op_tripulaciones_solicitudes` s
        LEFT JOIN `mybb_op_fichas` f ON f.fid = s.uid
        WHERE {$estado_filtro}
        ORDER BY s.id DESC
        LIMIT 100
    ");

    $html = '';
    while ($q = $db->fetch_array($query)) {
        $id = (int) $q['id'];
        $nombre_propuesto = htmlspecialchars($q['nombre_propuesto'], ENT_QUOTES, 'UTF-8');
        $nombre_personaje = htmlspecialchars($q['nombre_personaje'] ?: ('UID ' . (int) $q['uid']), ENT_QUOTES, 'UTF-8');
        $u_uid = (int) $q['uid'];
        $fecha = date('Y-m-d H:i', (int) $q['created_at']);
        $estado_label = array('Pendiente', 'Aprobada', 'Rechazada')[(int) $q['estado']] ?? '';

        $html .= '<div class="pa-item pa-item--simple">';
        $html .= '<div class="pa-item-linea">[<a href="/op/personaje.php?uid=' . $u_uid . '" target="_blank">' . $nombre_personaje . ' - ' . $u_uid . '</a>] solicita <strong>' . $nombre_propuesto . '</strong> <span class="atendido-por">(' . htmlspecialchars($estado_label, ENT_QUOTES, 'UTF-8') . ')</span></div>';
        $html .= '<div class="pa-item-linea"><strong>Fecha</strong>: ' . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div class="pa-item-acciones"><a class="opg-chip" href="/op/tripulacion_solicitar.php?tripu_id=' . $id . '">Revisar solicitud &rarr;</a></div>';
        $html .= '</div>';
    }

    if ($html === '') { return ''; }
    return '<div class="pa-seccion"><div class="pa-categoria"><span>Tripulaciones</span></div>' . $html . '</div>';
}

function pa_opciones_categoria($categorias, $seleccionada)
{
    $html = '<option value=""' . ($seleccionada === '' ? ' selected' : '') . '>Todas</option>';
    foreach ($categorias as $key => $label) {
        $sel = ($seleccionada === $key) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '<option value="tripulacion"' . ($seleccionada === 'tripulacion' ? ' selected' : '') . '>Tripulaciones</option>';
    return $html;
}

function pa_opciones_atendido($mods, $seleccionado)
{
    $html = '<option value=""' . ($seleccionado === '' ? ' selected' : '') . '>Todos</option>';
    foreach ($mods as $name) {
        $name = trim($name);
        $name_esc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $html .= '<option value="' . $name_esc . '"' . ($seleccionado === $name ? ' selected' : '') . '>' . $name_esc . '</option>';
    }
    return $html;
}

function pa_paginacion_html($pagina_actual, $total_paginas, $resuelto, $categoria_filtro, $atendido_filtro)
{
    if ($total_paginas <= 1) { return ''; }

    $armar_url = function ($p) use ($resuelto, $categoria_filtro, $atendido_filtro) {
        $params = array('resuelto' => $resuelto, 'pagina' => $p);
        if ($categoria_filtro !== '') { $params['categoria'] = $categoria_filtro; }
        if ($atendido_filtro !== '') { $params['atendido'] = $atendido_filtro; }
        return 'peticiones_admin.php?' . http_build_query($params);
    };

    $html = '<div class="pa-paginacion">';
    if ($pagina_actual > 1) {
        $html .= '<a class="opg-chip" href="' . htmlspecialchars($armar_url($pagina_actual - 1), ENT_QUOTES, 'UTF-8') . '">&laquo; Anterior</a>';
    }
    $html .= '<span class="pa-paginacion-texto">Página ' . $pagina_actual . ' de ' . $total_paginas . '</span>';
    if ($pagina_actual < $total_paginas) {
        $html .= '<a class="opg-chip" href="' . htmlspecialchars($armar_url($pagina_actual + 1), ENT_QUOTES, 'UTF-8') . '">Siguiente &raquo;</a>';
    }
    $html .= '</div>';
    return $html;
}

// Cuántos resets de build siguen pendientes, para el aviso junto al botón
// (sistema aparte de mybb_op_peticiones — vive en mybb_op_fichas_reset).
$resets_build_pendientes = (int) $db->fetch_field(
    $db->query("SELECT COUNT(*) AS c FROM `mybb_op_fichas_reset` WHERE `estado`='pendiente'"),
    'c'
);

// El filtro 'tripulacion' no es una categoría real de mybb_op_peticiones —
// cambia por completo a la lista de solicitudes de tripulación. Con
// 'Todas' se muestran ambas listas (comportamiento previo).
$mostrar_peticiones = $categoria_filtro !== 'tripulacion';
$mostrar_tripulaciones = $categoria_filtro === '' || $categoria_filtro === 'tripulacion';

$peticiones_li = '';
$paginacion_html = '';
if ($mostrar_peticiones) {
    $resultado = pa_listar_peticiones($resuelto, $categoria_filtro, $atendido_filtro, $pagina, $mods, $post_key, $CATEGORIAS);
    if ($resultado['html'] !== '') {
        $peticiones_li .= '<div class="pa-seccion">' . $resultado['html'] . '</div>';
    }
    $paginacion_html = pa_paginacion_html($resultado['pagina'], $resultado['total_paginas'], $resuelto, $categoria_filtro, $atendido_filtro);
}
if ($mostrar_tripulaciones) {
    $peticiones_li .= print_peticion_tripulacion($resuelto);
}

$categoria_opciones = pa_opciones_categoria($CATEGORIAS, $categoria_filtro);
$atendido_opciones = pa_opciones_atendido($mods, $atendido_filtro);

$csrf_script = "<script>window.MYBB_POST_KEY = '" . htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8') . "';</script>";

eval("\$page = \"".$templates->get("staff_peticiones_admin")."\";");
output_page($page);
