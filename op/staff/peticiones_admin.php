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

function pa_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pa_enlace_personaje($uid, $nombre)
{
    $uid = (int) $uid;
    $texto = pa_html($nombre !== '' ? $nombre : ('Usuario #' . $uid));
    if ($uid <= 0) {
        return $texto;
    }
    return '<a href="/op/personaje.php?uid=' . $uid . '" target="_blank" rel="noopener">' . $texto . '</a>';
}

/**
 * Resumen global por categoría. No depende de los filtros de la lista para
 * que siempre represente la carga completa del sistema.
 */
function pa_resumen_categorias($categorias)
{
    global $db;

    $resumen = array();
    foreach ($categorias as $key => $label) {
        $resumen[$key] = array('label' => $label, 'pendientes' => 0, 'resueltas' => 0);
    }
    $resumen['tripulacion'] = array('label' => 'Tripulaciones', 'pendientes' => 0, 'resueltas' => 0);

    $keys = array_map(array($db, 'escape_string'), array_keys($categorias));
    $query = $db->query("
        SELECT `categoria`,
               SUM(CASE WHEN `resuelto`=0 THEN 1 ELSE 0 END) AS pendientes,
               SUM(CASE WHEN `resuelto`=1 THEN 1 ELSE 0 END) AS resueltas
        FROM `mybb_op_peticiones`
        WHERE `categoria` IN ('" . implode("','", $keys) . "')
        GROUP BY `categoria`
    ");
    while ($row = $db->fetch_array($query)) {
        $key = (string) $row['categoria'];
        if (!isset($resumen[$key])) {
            continue;
        }
        $resumen[$key]['pendientes'] = (int) $row['pendientes'];
        $resumen[$key]['resueltas'] = (int) $row['resueltas'];
    }

    $tripulaciones = $db->fetch_array($db->query("
        SELECT SUM(CASE WHEN `estado`=0 THEN 1 ELSE 0 END) AS pendientes,
               SUM(CASE WHEN `estado` IN (1,2) THEN 1 ELSE 0 END) AS resueltas
        FROM `mybb_op_tripulaciones_solicitudes`
    "));
    $resumen['tripulacion']['pendientes'] = (int) ($tripulaciones['pendientes'] ?? 0);
    $resumen['tripulacion']['resueltas'] = (int) ($tripulaciones['resueltas'] ?? 0);

    return $resumen;
}

function pa_render_distribucion($resumen)
{
    $filas = '';
    $totalPendientes = 0;
    $totalResueltas = 0;
    foreach ($resumen as $datos) {
        $pendientes = (int) $datos['pendientes'];
        $resueltas = (int) $datos['resueltas'];
        $totalPendientes += $pendientes;
        $totalResueltas += $resueltas;
        $filas .= '<tr><th scope="row">' . pa_html($datos['label']) . '</th>'
            . '<td>' . $pendientes . '</td><td>' . $resueltas . '</td><td>' . ($pendientes + $resueltas) . '</td></tr>';
    }

    return '<div class="pa-tabla-wrap"><table class="pa-tabla"><thead><tr><th>Categoría</th>'
        . '<th>Pendientes</th><th>Resueltas</th><th>Total</th></tr></thead><tbody>' . $filas
        . '</tbody><tfoot><tr><th scope="row">Total</th><td>' . $totalPendientes . '</td><td>' . $totalResueltas
        . '</td><td>' . ($totalPendientes + $totalResueltas) . '</td></tr></tfoot></table></div>';
}

/**
 * Actividad histórica atribuida al usuario que ejecutó cada resolución.
 * Se combinan las peticiones administrativas y las solicitudes de
 * tripulación sin hacer consultas por moderador.
 */
function pa_actividad_equipo($categorias)
{
    global $db;

    $actividad = array();
    $sinAtribuir = 0;
    $keys = array_map(array($db, 'escape_string'), array_keys($categorias));
    $query = $db->query("
        SELECT CAST(p.`mod_uid` AS UNSIGNED) AS uid,
               COALESCE(NULLIF(MAX(u.`username`), ''), NULLIF(MAX(p.`mod_nombre`), '')) AS username,
               COUNT(*) AS cantidad
        FROM `mybb_op_peticiones` p
        LEFT JOIN `mybb_users` u ON u.`uid`=CAST(p.`mod_uid` AS UNSIGNED)
        WHERE p.`resuelto`=1
          AND p.`categoria` IN ('" . implode("','", $keys) . "')
          AND CAST(p.`mod_uid` AS UNSIGNED) > 0
        GROUP BY CAST(p.`mod_uid` AS UNSIGNED)
    ");
    while ($row = $db->fetch_array($query)) {
        $moderadorUid = (int) $row['uid'];
        $actividad[$moderadorUid] = array(
            'uid' => $moderadorUid,
            'username' => (string) ($row['username'] ?: ('Usuario #' . $moderadorUid)),
            'total' => (int) $row['cantidad'],
        );
    }

    $tripulaciones = $db->query("
        SELECT s.`resuelto_por` AS uid, MAX(u.`username`) AS username, COUNT(*) AS cantidad
        FROM `mybb_op_tripulaciones_solicitudes` s
        LEFT JOIN `mybb_users` u ON u.`uid`=s.`resuelto_por`
        WHERE s.`estado` IN (1,2) AND s.`resuelto_por` IS NOT NULL AND s.`resuelto_por` > 0
        GROUP BY s.`resuelto_por`
    ");
    while ($row = $db->fetch_array($tripulaciones)) {
        $moderadorUid = (int) $row['uid'];
        if (!isset($actividad[$moderadorUid])) {
            $actividad[$moderadorUid] = array(
                'uid' => $moderadorUid,
                'username' => (string) ($row['username'] ?: ('Usuario #' . $moderadorUid)),
                'total' => 0,
            );
        }
        $cantidad = (int) $row['cantidad'];
        $actividad[$moderadorUid]['total'] += $cantidad;
    }

    $sinAtribuir += (int) $db->fetch_field($db->query("
        SELECT COUNT(*) AS cantidad FROM `mybb_op_peticiones`
        WHERE `resuelto`=1 AND `categoria` IN ('" . implode("','", $keys) . "')
          AND (`mod_uid` IS NULL OR `mod_uid`='' OR CAST(`mod_uid` AS UNSIGNED)=0)
    "), 'cantidad');
    $sinAtribuir += (int) $db->fetch_field($db->query("
        SELECT COUNT(*) AS cantidad FROM `mybb_op_tripulaciones_solicitudes`
        WHERE `estado` IN (1,2) AND (`resuelto_por` IS NULL OR `resuelto_por`=0)
    "), 'cantidad');

    $actividad = array_values($actividad);
    usort($actividad, function ($a, $b) {
        if ((int) $a['total'] === (int) $b['total']) {
            return strcasecmp((string) $a['username'], (string) $b['username']);
        }
        return (int) $b['total'] <=> (int) $a['total'];
    });

    return array('moderadores' => $actividad, 'sin_atribuir' => $sinAtribuir);
}

function pa_render_actividad($actividad)
{
    if (empty($actividad['moderadores'])) {
        $mensaje = '<p class="opg-vacio">Todavía no hay resoluciones atribuidas a integrantes del equipo.</p>';
        if ((int) $actividad['sin_atribuir'] > 0) {
            $mensaje .= '<p class="pa-actividad__nota"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> '
                . (int) $actividad['sin_atribuir'] . ' resoluciones antiguas no tienen moderador atribuido.</p>';
        }
        return $mensaje;
    }

    $filas = '';
    $totalGeneral = 0;
    foreach ($actividad['moderadores'] as $moderador) {
        $totalGeneral += (int) $moderador['total'];
        $filas .= '<tr><th scope="row">' . pa_enlace_personaje($moderador['uid'], $moderador['username']) . '</th>'
            . '<td><strong>' . (int) $moderador['total'] . '</strong></td></tr>';
    }
    $nota = (int) $actividad['sin_atribuir'] > 0
        ? '<p class="pa-actividad__nota"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> '
            . (int) $actividad['sin_atribuir'] . ' resoluciones antiguas no tienen moderador atribuido y no aparecen en las filas.</p>'
        : '';

    return '<div class="pa-tabla-wrap pa-tabla-wrap--actividad"><table class="pa-tabla pa-tabla--actividad"><thead><tr>'
        . '<th>Moderador</th><th>Resoluciones</th></tr></thead><tbody>' . $filas
        . '</tbody><tfoot><tr><th scope="row">Total atribuido</th><td>' . $totalGeneral
        . '</td></tr></tfoot></table></div>' . $nota;
}

$resuelto = $mybb->get_input('resuelto', MyBB::INPUT_STRING) === '1' ? '1' : '0';

// Los filtros y la paginación solo tienen sentido viendo "resueltas" (que
// solo crece); "pendientes" siempre se ve completa, sin UI para
// filtrar/paginar — así que se ignora cualquier `categoria`/`atendido`
// que llegue igual por la URL en esa vista.
$categoria_filtro = '';
$atendido_filtro = '';
if ($resuelto === '1') {
    $categoria_filtro = $mybb->get_input('categoria', MyBB::INPUT_STRING);
    if ($categoria_filtro !== 'tripulacion' && !isset($CATEGORIAS[$categoria_filtro])) {
        $categoria_filtro = '';
    }
    $atendido_filtro = trim($mybb->get_input('atendido', MyBB::INPUT_STRING));
}
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
 * crece. Cada tarjeta lleva su categoría en la cinta superior, ya que al no
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
        $categoria_key = isset($categorias[$q['categoria']]) ? (string) $q['categoria'] : 'otros';
        $categoria_esc = pa_html($categorias[$q['categoria']] ?? $q['categoria']);
        $pid = (int) $q['id'];
        $u_uid = (int) $q['uid'];
        $nombre = (string) $q['nombre'];
        $resumen_esc = pa_html($q['resumen'] !== '' ? $q['resumen'] : 'Petición sin resumen');
        $descripcion_esc = nl2br(pa_html($q['descripcion']));
        $url = trim((string) $q['url']);
        $url_esc = pa_html($url);
        $url_segura = preg_match('#^(https?://|/)#i', $url) ? $url_esc : '';
        $enviado_esc = pa_html($q['enviado']);
        $mod_nombre_esc = pa_html($q['mod_nombre']);

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

        $enlace_url = $url_segura !== ''
            ? '<a href="' . $url_segura . '" target="_blank" rel="noopener">Abrir enlace relacionado <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>'
            : '<span class="pa-card__sin-dato">No indicado</span>';

        $html .= '<article class="pa-card pa-card--' . pa_html($categoria_key) . '">';
        $html .= '<div class="pa-card__cinta"><span>' . $categoria_esc . '</span><span>Petición #' . $pid . '</span></div>';
        $html .= '<div class="pa-card__cuerpo">';
        $html .= '<div class="pa-card__contenido">';
        $html .= '<h3>' . $resumen_esc . '</h3>';
        $html .= '<dl class="pa-card__datos">';
        $html .= '<div><dt><i class="fa-solid fa-user" aria-hidden="true"></i> Solicitante</dt><dd>' . pa_enlace_personaje($u_uid, $nombre) . ' <span class="pa-card__uid">#' . $u_uid . '</span></dd></div>';
        $html .= '<div><dt><i class="fa-solid fa-clock" aria-hidden="true"></i> Enviada</dt><dd>' . $enviado_esc . ($fecha !== '' ? ' · ' . pa_html($fecha) : '') . '</dd></div>';
        $html .= '<div><dt><i class="fa-solid fa-link" aria-hidden="true"></i> Referencia</dt><dd>' . $enlace_url . '</dd></div>';
        $html .= '</dl>';
        $html .= '<div class="pa-card__descripcion"><strong>Descripción</strong><p>' . ($descripcion_esc !== '' ? $descripcion_esc : 'Sin descripción adicional.') . '</p></div>';
        $html .= '</div>';

        $html .= '<div class="pa-card__gestion">';
        $html .= '<div class="pa-card__gestion-titulo"><span><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Gestión</span><span class="atendido-por">' . pa_html($atendidoPor !== '' ? $atendidoPor : 'Sin asignar') . '</span></div>';

        $html .= '<div class="pa-card__asignacion"><label for="' . $select_id . '">Asignar a</label><div>';
        $html .= '<select id="' . $select_id . '" class="sel-mod" data-pid="' . $pid . '">';
        foreach (array_map('trim', $mods) as $name) {
            $normOpt = norm_name($name);
            $isMatch = ($normOpt === $normAtendido) || ($normAtendido === '' && $normOpt === norm_name('Sin asignar'));
            $name_esc = pa_html($name);
            $html .= '<option value="' . $name_esc . '"' . ($isMatch ? ' selected' : '') . '>' . $name_esc . '</option>';
        }
        $html .= '</select><button type="button" class="btn-op btn-op--sm btn-op--secundario save-mod" data-pid="' . $pid . '" data-target-id="' . $select_id . '" title="Guardar asignación"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar</button></div></div>';

        $html .= '<div class="pa-card__notas">';
        $html .= '<label for="' . $notes_id . '">Notas de moderación</label>';
        $html .= '<textarea id="' . $notes_id . '" class="notas-text" data-pid="' . $pid . '" rows="3">' . pa_html($notasMod) . '</textarea>';
        $html .= '<button type="button" class="btn-op btn-op--sm btn-op--secundario save-notas" data-pid="' . $pid . '" data-target-id="' . $notes_id . '"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar notas</button>';
        $html .= '</div>';

        if ($q['mod_nombre'] !== '') {
            $html .= '<p class="pa-card__resuelta"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Resuelta por <strong>' . $mod_nombre_esc . '</strong></p>';
        }

        if ($resuelto !== '1' && (is_mod($uid) || is_staff($uid))) {
            $resolver_a = 'peticiones_admin.php?accion=resolver&peti_id=' . $pid . '&my_post_key=' . rawurlencode($post_key);
            $html .= '<div class="pa-card__acciones"><a class="btn-op btn-op--sm btn-op--exito" href="' . pa_html($resolver_a) . '" onclick="return confirm(\'¿Marcar esta petición como resuelta?\');"><i class="fa-solid fa-check" aria-hidden="true"></i> Resolver</a></div>';
        }
        $html .= '</div>';
        $html .= '</div></article>';
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
    $total = (int) $db->fetch_field($db->query("
        SELECT COUNT(*) AS cantidad
        FROM `mybb_op_tripulaciones_solicitudes`
        WHERE {$estado_filtro}
    "), 'cantidad');
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
        $nombre_propuesto = pa_html($q['nombre_propuesto']);
        $nombre_personaje = (string) ($q['nombre_personaje'] ?: ('UID ' . (int) $q['uid']));
        $u_uid = (int) $q['uid'];
        $fecha = date('Y-m-d H:i', (int) $q['created_at']);
        $estado_label = array('Pendiente', 'Aprobada', 'Rechazada')[(int) $q['estado']] ?? '';

        $html .= '<article class="pa-card pa-card--tripulacion pa-card--simple">';
        $html .= '<div class="pa-card__cinta"><span>Tripulaciones</span><span>Solicitud #' . $id . '</span></div>';
        $html .= '<div class="pa-card__cuerpo"><div class="pa-card__contenido">';
        $html .= '<h3>' . $nombre_propuesto . '</h3><dl class="pa-card__datos">';
        $html .= '<div><dt><i class="fa-solid fa-user" aria-hidden="true"></i> Solicitante</dt><dd>' . pa_enlace_personaje($u_uid, $nombre_personaje) . ' <span class="pa-card__uid">#' . $u_uid . '</span></dd></div>';
        $html .= '<div><dt><i class="fa-solid fa-clock" aria-hidden="true"></i> Enviada</dt><dd>' . pa_html($fecha) . '</dd></div>';
        $html .= '<div><dt><i class="fa-solid fa-signal" aria-hidden="true"></i> Estado</dt><dd><strong>' . pa_html($estado_label) . '</strong></dd></div>';
        $html .= '</dl></div><div class="pa-card__acciones pa-card__acciones--simple">';
        $html .= '<a class="btn-op btn-op--sm btn-op--primario" href="/op/tripulacion_solicitar.php?tripu_id=' . $id . '"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Revisar solicitud</a>';
        $html .= '</div></div></article>';
    }

    return array('html' => $html, 'total' => $total);
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

$resumen_categorias = pa_resumen_categorias($CATEGORIAS);
$pa_total_pendientes = 0;
$pa_total_resueltas = 0;
foreach ($resumen_categorias as $datos_categoria) {
    $pa_total_pendientes += (int) $datos_categoria['pendientes'];
    $pa_total_resueltas += (int) $datos_categoria['resueltas'];
}
$pa_distribucion = pa_render_distribucion($resumen_categorias);
$pa_actividad = pa_render_actividad(pa_actividad_equipo($CATEGORIAS));

// El filtro 'tripulacion' no es una categoría real de mybb_op_peticiones —
// cambia por completo a la lista de solicitudes de tripulación. Con
// 'Todas' se muestran ambas listas (comportamiento previo).
$mostrar_peticiones = $categoria_filtro !== 'tripulacion';
$mostrar_tripulaciones = $categoria_filtro === '' || $categoria_filtro === 'tripulacion';

$peticiones_li = '';
$paginacion_html = '';
$pa_total_vista = 0;
if ($mostrar_peticiones) {
    $resultado = pa_listar_peticiones($resuelto, $categoria_filtro, $atendido_filtro, $pagina, $mods, $post_key, $CATEGORIAS);
    $peticiones_li .= $resultado['html'];
    $pa_total_vista += (int) $resultado['total'];
    $paginacion_html = pa_paginacion_html($resultado['pagina'], $resultado['total_paginas'], $resuelto, $categoria_filtro, $atendido_filtro);
}
if ($mostrar_tripulaciones) {
    $resultado_tripulaciones = print_peticion_tripulacion($resuelto);
    $peticiones_li .= $resultado_tripulaciones['html'];
    $pa_total_vista += (int) $resultado_tripulaciones['total'];
}

$pa_listado_titulo = $resuelto === '1' ? 'Peticiones resueltas' : 'Peticiones pendientes';
$pa_listado_icono = $resuelto === '1' ? 'box-archive' : 'inbox';
$pa_listado_descripcion = $resuelto === '1'
    ? 'Histórico de peticiones atendidas. Los filtros solo modifican este listado.'
    : 'Cola activa de peticiones que todavía requieren gestión del equipo.';

$categoria_opciones = pa_opciones_categoria($CATEGORIAS, $categoria_filtro);
$atendido_opciones = pa_opciones_atendido($mods, $atendido_filtro);

$csrf_script = "<script>window.MYBB_POST_KEY = '" . htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8') . "';</script>";

eval("\$page = \"".$templates->get("staff_peticiones_admin")."\";");
output_page($page);
