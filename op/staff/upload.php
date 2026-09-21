<?php
/**
 * Staff - Hosting de imágenes
 *
 * Sube imágenes a images/op/uploads/ conservando el nombre que elige el staff
 * (las plantillas del foro enlazan cientos de ellas por nombre exacto, con
 * espacios y acentos incluidos) y muestra las últimas subidas para copiar su
 * URL. Sobrescribir un archivo existente exige marcarlo explícitamente.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'upload.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . "inc/functions_upload.php";

global $templates, $mybb, $db;
$uid = (int)$mybb->user['uid'];

// El control de acceso va ANTES de procesar nada: antes solo protegía el
// formulario y cualquiera (incluso sin sesión) podía enviar el POST.
// UID 11: acceso puntual solo a esta página (no es_staff en general).
if (!is_mod($uid) && !is_staff($uid) && $uid !== 11) {
    $mensaje_redireccion = "Si no eres Staff, no tienes acceso a esta página.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

define('UPLOAD_DIR',        'images/op/uploads/');
define('UPLOAD_MAX_MB',     5);
define('UPLOAD_POR_PAGINA', 30);
define('UPLOAD_MSG_COOKIE', 'upload_msg');

$upload_extensiones = array('jpg', 'jpeg', 'png', 'gif', 'webp');
$upload_tipos       = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP);

/**
 * Nombre de archivo seguro a partir del que envía el navegador, o '' si no vale.
 * Se conserva tal cual (espacios, paréntesis, acentos) porque las plantillas
 * enlazan las imágenes por su nombre exacto.
 */
function upload_nombre($original, &$error)
{
    global $upload_extensiones;

    $nombre = trim(basename(str_replace('\\', '/', (string)$original)));

    if ($nombre === '' || $nombre[0] === '.' || preg_match('/[\x00-\x1f\x7f]/', $nombre)) {
        $error = 'Nombre de archivo no válido.';
        return '';
    }
    if (mb_strlen($nombre) > 150) {
        $error = 'El nombre del archivo es demasiado largo (máximo 150 caracteres).';
        return '';
    }

    $partes = explode('.', $nombre);
    $extension = strtolower(array_pop($partes));
    if (!in_array($extension, $upload_extensiones, true)) {
        $error = 'Solo se admiten imágenes JPG, JPEG, PNG, GIF o WEBP.';
        return '';
    }
    // Sin dobles extensiones ejecutables (p. ej. "foto.php.jpg").
    foreach ($partes as $parte) {
        if (preg_match('/^(php\d*|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|htaccess|htpasswd)$/i', $parte)) {
            $error = 'El nombre del archivo no puede contener una extensión ejecutable.';
            return '';
        }
    }
    return $nombre;
}

function upload_url($nombre, $absoluta = false)
{
    global $mybb;
    $ruta = '/' . UPLOAD_DIR . rawurlencode($nombre);
    return $absoluta ? rtrim($mybb->settings['bburl'], '/') . $ruta : $ruta;
}

/**
 * Aviso de un solo uso para el Post/Redirect/Get (mismo patrón que op/staff/banners.php).
 * Solo viaja texto plano y, opcionalmente, el nombre del archivo: el HTML se arma
 * al pintarlo, escapado, para que una cookie manipulada no pueda inyectar nada.
 * base64 porque my_setcookie hace urlencode() y PHP no deshace los "+" al leer $_COOKIE.
 */
function upload_aviso($tipo, $texto, $archivo = '')
{
    my_setcookie(UPLOAD_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo' => $tipo, 'texto' => $texto, 'archivo' => $archivo,
    ))), 15, true);
    header('Location: upload.php');
    exit;
}

// ── POST: subir ─────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $archivo = isset($_FILES['fileToUpload']) ? $_FILES['fileToUpload'] : null;
    if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
        upload_aviso('err', 'No se recibió ningún archivo.');
    }
    if ($archivo['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
        upload_aviso('err', 'El archivo supera los ' . UPLOAD_MAX_MB . ' MB.');
    }

    $error = '';
    $nombre = upload_nombre($archivo['name'], $error);
    if ($nombre === '') {
        upload_aviso('err', $error);
    }

    // El contenido tiene que ser una imagen de verdad, no solo tener la extensión.
    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !in_array($info[2], $upload_tipos, true)) {
        upload_aviso('err', 'El archivo no es una imagen JPG, PNG, GIF o WEBP válida.');
    }

    $existe = file_exists(MYBB_ROOT . UPLOAD_DIR . $nombre);
    if ($existe && $mybb->get_input('reemplazar', MyBB::INPUT_INT) !== 1) {
        upload_aviso('err', 'Ya existe una imagen con ese nombre. Marca «Reemplazar si ya existe» para '
            . 'sobrescribirla, o cambia el nombre del archivo:', $nombre);
    }

    $resultado = upload_file($archivo, MYBB_ROOT . rtrim(UPLOAD_DIR, '/'), $nombre);
    if (!empty($resultado['error'])) {
        upload_aviso('err', 'Error al guardar el archivo en el servidor.');
    }

    // Marca de versión que usan otras páginas para refrescar la caché de imágenes.
    file_put_contents(MYBB_ROOT . UPLOAD_DIR . '_ver', TIME_NOW);

    log_audit($uid, $db->escape_string($mybb->user['username']), 'upload',
        $db->escape_string(($existe ? 'Reemplazó ' : 'Subió ') . UPLOAD_DIR . $nombre));

    upload_aviso('ok', $existe ? 'Imagen reemplazada:' : 'Imagen subida:', $nombre);
}

// ── GET: formulario + últimas subidas ───────────────────────────────────────

$upload_aviso = '';
if (!empty($mybb->cookies[UPLOAD_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[UPLOAD_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        $enlace = '';
        if (!empty($msg['archivo'])) {
            $enlace = ' <a target="_blank" href="' . htmlspecialchars(upload_url($msg['archivo']), ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($msg['archivo'], ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $upload_aviso = '<p class="aviso ' . ($msg['tipo'] === 'ok' ? 'ok' : 'err') . '">'
            . htmlspecialchars($msg['texto'], ENT_QUOTES, 'UTF-8') . $enlace . '</p>';
    }
    my_unsetcookie(UPLOAD_MSG_COOKIE);
}

// Todas las imágenes de la carpeta, de la más reciente a la más antigua.
$imagenes = array();
foreach (@scandir(MYBB_ROOT . UPLOAD_DIR) ?: array() as $f) {
    if ($f[0] === '.' || !in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $upload_extensiones, true)) {
        continue;
    }
    $imagenes[$f] = (int)@filemtime(MYBB_ROOT . UPLOAD_DIR . $f);
}
arsort($imagenes);

$upload_total   = count($imagenes);
$upload_paginas = max(1, (int)ceil($upload_total / UPLOAD_POR_PAGINA));
$pagina         = min(max(1, $mybb->get_input('pagina', MyBB::INPUT_INT)), $upload_paginas);

$upload_galeria = '';
foreach (array_slice($imagenes, ($pagina - 1) * UPLOAD_POR_PAGINA, UPLOAD_POR_PAGINA, true) as $f => $mtime) {
    $e   = htmlspecialchars($f, ENT_QUOTES, 'UTF-8');
    $rel = htmlspecialchars(upload_url($f), ENT_QUOTES, 'UTF-8');
    $abs = htmlspecialchars(upload_url($f, true), ENT_QUOTES, 'UTF-8');
    $upload_galeria .= '<div class="subida">'
        . '<a class="subida-miniatura" href="' . $rel . '?v=' . $mtime . '" target="_blank"><img src="' . $rel . '?v=' . $mtime . '" loading="lazy" alt=""></a>'
        . '<div class="subida-info">'
        . '<span class="subida-nombre" title="' . $e . '">' . $e . '</span>'
        . '<span class="subida-fecha">' . my_date('relative', $mtime) . '</span>'
        . '<div class="subida-acciones">'
        . '<button type="button" class="opg-chip" data-copiar="' . $abs . '">Copiar URL</button>'
        . '<button type="button" class="opg-chip" data-copiar="[img]' . $abs . '[/img]">Copiar [img]</button>'
        . '</div></div></div>';
}
if ($upload_galeria === '') {
    $upload_galeria = '<p class="opg-vacio">Todavía no hay imágenes subidas.</p>';
}

$upload_paginacion = '';
if ($upload_paginas > 1) {
    $upload_paginacion = '<div class="paginacion">'
        . ($pagina > 1 ? '<a class="opg-chip" href="upload.php?pagina=' . ($pagina - 1) . '">&larr; Más recientes</a>' : '<span></span>')
        . '<span class="paginacion-estado">Página ' . $pagina . ' de ' . $upload_paginas . '</span>'
        . ($pagina < $upload_paginas ? '<a class="opg-chip" href="upload.php?pagina=' . ($pagina + 1) . '">Más antiguas &rarr;</a>' : '<span></span>')
        . '</div>';
}

$upload_max_mb = UPLOAD_MAX_MB;

eval("\$page = \"".$templates->get("op_upload")."\";");
output_page($page);
