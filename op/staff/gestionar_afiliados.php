<?php
/**
 * Consola de Staff — Gestión de Afiliados (One Piece Gaiden)
 * Ver docs/afiliados_implementacion_opg.md §6
 *
 * Acciones AJAX (JSON): agregar, editar, toggle, eliminar. CSRF via my_post_key.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'gestionar_afiliados.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";
require_once "./../functions/afiliados_functions.php";

global $templates, $mybb, $db;
$uid = $mybb->user['uid'];

// --- Gate de acceso ---
if (!is_staff($uid) && !is_mod($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval('$page = "'.$templates->get('op_redireccion').'";');
    output_page($page);
    exit;
}

$TIPOS = array('hermano', 'grande', 'pequeno');

// =====================================================================
// Endpoint AJAX
// =====================================================================
if ($mybb->request_method === 'post' && $mybb->get_input('accion') !== '') {
    header('Content-Type: application/json; charset=utf-8');

    // CSRF
    $post_key = $mybb->get_input('my_post_key', MyBB::INPUT_STRING);
    if (!verify_post_check($post_key, true)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'CSRF inválido'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

    // --- toggle activo ---
    if ($accion === 'toggle') {
        $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
        if ($id <= 0) { echo json_encode(array('success' => false, 'error' => 'ID inválido')); exit; }
        $db->query("UPDATE `mybb_op_afiliados` SET `activo` = 1 - `activo` WHERE `id` = {$id} LIMIT 1");
        $row = $db->fetch_array($db->query("SELECT `activo` FROM `mybb_op_afiliados` WHERE `id` = {$id} LIMIT 1"));
        echo json_encode(array('success' => true, 'id' => $id, 'activo' => (int) $row['activo']), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- eliminar ---
    if ($accion === 'eliminar') {
        $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
        if ($id <= 0) { echo json_encode(array('success' => false, 'error' => 'ID inválido')); exit; }
        $db->query("DELETE FROM `mybb_op_afiliados` WHERE `id` = {$id} LIMIT 1");
        echo json_encode(array('success' => true, 'id' => $id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- agregar / editar (validación compartida) ---
    if ($accion === 'agregar' || $accion === 'editar') {
        $tipo        = $mybb->get_input('tipo', MyBB::INPUT_STRING);
        $nombre      = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
        $url         = trim($mybb->get_input('url', MyBB::INPUT_STRING));
        $imagen      = trim($mybb->get_input('imagen', MyBB::INPUT_STRING));
        $descripcion = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));
        $orden       = (int) $mybb->get_input('orden', MyBB::INPUT_INT);

        // Validaciones server-side
        $errores = array();
        if (!in_array($tipo, $TIPOS, true)) { $errores[] = 'Nivel inválido.'; }
        if ($nombre === '' || mb_strlen($nombre) > 120) { $errores[] = 'El nombre es obligatorio (máx. 120).'; }

        // Esquema http(s) obligatorio: `url` se vuelca tal cual como href en la
        // tarjeta pública del índice (afiliados_functions.php::op_afil_card_html) y
        // `imagen` como src — sin este check, un "javascript:" acá quedaría
        // ejecutándose contra cualquier visitante que haga clic en la tarjeta.
        if ($url === '' || mb_strlen($url) > 255) { $errores[] = 'La URL es obligatoria (máx. 255).'; }
        elseif (!preg_match('#^https?://#i', $url)) { $errores[] = 'La URL debe empezar con http:// o https://.'; }
        if ($imagen === '' || mb_strlen($imagen) > 255) { $errores[] = 'La imagen es obligatoria (máx. 255).'; }
        elseif (!preg_match('#^https?://#i', $imagen)) { $errores[] = 'La imagen debe ser una URL http:// o https://.'; }
        if (mb_strlen($descripcion) > 255) { $errores[] = 'La descripción no puede superar 255.'; }

        if (!empty($errores)) {
            echo json_encode(array('success' => false, 'error' => implode(' ', $errores)), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tipo_esc   = $db->escape_string($tipo);
        $nombre_esc = $db->escape_string($nombre);
        $url_esc    = $db->escape_string($url);
        $imagen_esc = $db->escape_string($imagen);
        $desc_esc   = $db->escape_string($descripcion);

        if ($accion === 'agregar') {
            $agregado = $db->escape_string($mybb->user['username']);
            $db->query("INSERT INTO `mybb_op_afiliados`
                (`tipo`,`nombre`,`url`,`imagen`,`descripcion`,`orden`,`activo`,`agregado_por`)
                VALUES ('{$tipo_esc}','{$nombre_esc}','{$url_esc}','{$imagen_esc}','{$desc_esc}',{$orden},1,'{$agregado}')");
            $id = (int) $db->insert_id();
        } else {
            $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
            if ($id <= 0) { echo json_encode(array('success' => false, 'error' => 'ID inválido')); exit; }
            $db->query("UPDATE `mybb_op_afiliados` SET
                `tipo`='{$tipo_esc}', `nombre`='{$nombre_esc}', `url`='{$url_esc}',
                `imagen`='{$imagen_esc}', `descripcion`='{$desc_esc}', `orden`={$orden}
                WHERE `id` = {$id} LIMIT 1");
        }

        $row = $db->fetch_array($db->query("SELECT * FROM `mybb_op_afiliados` WHERE `id` = {$id} LIMIT 1"));
        echo json_encode(array('success' => true, 'afiliado' => $row), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(array('success' => false, 'error' => 'Acción desconocida'), JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================================
// Render de la consola
// =====================================================================

// Conteo activos vs tope por nivel (aviso de tope duro §6)
$conteos = array('hermano' => 0, 'grande' => 0, 'pequeno' => 0);
$cq = $db->query("SELECT tipo, COUNT(*) AS n FROM `mybb_op_afiliados` WHERE activo=1 GROUP BY tipo");
while ($cr = $db->fetch_array($cq)) {
    if (isset($conteos[$cr['tipo']])) { $conteos[$cr['tipo']] = (int) $cr['n']; }
}
$topes = array('hermano' => 1, 'grande' => OP_AFILIADOS_SLOTS_GRANDE, 'pequeno' => OP_AFILIADOS_SLOTS_PEQUENO);

function af_admin_badge($n, $tope) {
    $color = ($n > $tope) ? 'var(--opg-rojo-error, #dc3545)' : 'var(--opg-verde-exito, #27ae60)';
    return '<span style="font-weight:700;color:' . $color . '">' . (int) $n . '/' . (int) $tope . '</span>';
}

// Filas existentes (todas, activas e inactivas)
$tarjetas = '';
$rows = $db->query("SELECT * FROM `mybb_op_afiliados` ORDER BY tipo, orden, id");
while ($r = $db->fetch_array($rows)) {
    $id     = (int) $r['id'];
    $tipo   = htmlspecialchars($r['tipo'], ENT_QUOTES);
    $nombre = htmlspecialchars($r['nombre'], ENT_QUOTES);
    $url    = htmlspecialchars($r['url'], ENT_QUOTES);
    $img    = htmlspecialchars($r['imagen'], ENT_QUOTES);
    $desc   = htmlspecialchars((string) $r['descripcion'], ENT_QUOTES);
    $orden  = (int) $r['orden'];
    $activo = (int) $r['activo'];
    $ratio  = ($tipo === 'pequeno') ? '1/1' : '16/9';
    $estado_txt = $activo ? 'Activo' : 'Inactivo';
    $estado_col = $activo ? 'var(--opg-verde-exito, #27ae60)' : 'var(--opg-gris-texto, #999)';

    $tarjetas .= "<div class=\"af-item\" id=\"af-item-{$id}\" data-id=\"{$id}\" data-tipo=\"{$tipo}\" data-nombre=\"{$nombre}\" data-url=\"{$url}\" data-imagen=\"{$img}\" data-descripcion=\"{$desc}\" data-orden=\"{$orden}\" data-activo=\"{$activo}\">
        <div class=\"af-thumb\" style=\"aspect-ratio:{$ratio}\"><img src=\"{$img}\" alt=\"\" onerror=\"this.style.opacity=0.2\"></div>
        <div class=\"af-meta\">
            <div class=\"af-name\">{$nombre} <span class=\"af-tag\">{$tipo}</span></div>
            <div class=\"af-sub\">orden: {$orden} · <span style=\"color:{$estado_col}\">{$estado_txt}</span></div>
            <div class=\"af-actions\">
                <button type=\"button\" class=\"af-edit opg-chip\" data-id=\"{$id}\">Editar</button>
                <button type=\"button\" class=\"af-toggle opg-chip\" data-id=\"{$id}\">".($activo ? 'Desactivar' : 'Activar')."</button>
                <button type=\"button\" class=\"af-del opg-chip chip-peligro\" data-id=\"{$id}\">Eliminar</button>
            </div>
        </div>
    </div>";
}
if ($tarjetas === '') { $tarjetas = '<p class="opg-vacio">Todavía no hay afiliados cargados.</p>'; }

$af_conteo_hermano = af_admin_badge($conteos['hermano'], $topes['hermano']);
$af_conteo_grande  = af_admin_badge($conteos['grande'], $topes['grande']);
$af_conteo_pequeno = af_admin_badge($conteos['pequeno'], $topes['pequeno']);
$af_tarjetas = $tarjetas;

eval('$page = "'.$templates->get('staff_gestionar_afiliados').'";');
output_page($page);
