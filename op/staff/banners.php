<?php
/**
 * Staff - Banners del header
 *
 * Gestiona los banners que rota el plugin op_banner_rotativo: subir,
 * desactivar, eliminar (a papelera), restaurar y fijar un banner.
 * La carpeta images/op/banners/ es la única fuente de
 * verdad: el plugin solo rota los archivos que hay en su raíz.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'banners.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
// Constantes (DIR, PATRON, CACHE, FIJO, INTERVALO) y op_banner_rotativo_fijo(),
// compartidas con el plugin.
require_once MYBB_ROOT . "inc/plugins/op_banner_rotativo.php";

global $templates, $mybb, $db, $cache;
$uid = (int)$mybb->user['uid'];

if (!is_admin($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

define('BANNERS_ANCHO',   1100);
define('BANNERS_ALTO',    620);
define('BANNERS_MAX_MB',  5);
define('BANNERS_CALIDAD', 88);
// Cookie de un solo uso para el aviso post-acción: así el Post/Redirect/Get
// vuelve a banners.php sin query string, y "Actualizar" no repite el aviso.
define('BANNERS_MSG_COOKIE', 'banners_msg');

// Banners que header.html usa con ruta fija (Banner59 = respaldo del <else>,
// Banner60 = banner fijo de ciertos UIDs): no se pueden sacar de la raíz.
$banners_protegidos = array(59, 60);

// Estado => subcarpeta dentro de images/op/banners/
$banners_carpetas = array(
    'activo'   => '',
    'inactivo' => '_inactivos/',
    'papelera' => '_papelera/',
);

function banners_nombre($n)
{
    return 'Banner' . (int)$n . '_One_Piece_Gaiden_Foro_Rol.jpg';
}

function banners_ruta($estado, $n = null)
{
    global $banners_carpetas;
    $ruta = MYBB_ROOT . OP_BANNER_ROTATIVO_DIR . $banners_carpetas[$estado];
    return $n === null ? $ruta : $ruta . banners_nombre($n);
}

/**
 * [numero => nombre de archivo] de una carpeta, ordenado por número.
 */
function banners_listar($estado)
{
    $archivos = @scandir(banners_ruta($estado)) ?: array();
    $banners = array();
    foreach ($archivos as $archivo) {
        if (preg_match(OP_BANNER_ROTATIVO_PATRON, $archivo, $m)) {
            $banners[(int)$m[1]] = $archivo;
        }
    }
    ksort($banners);
    return $banners;
}

/**
 * Estado en el que está el banner $n, o null si no existe en ninguna carpeta.
 */
function banners_estado($n)
{
    global $banners_carpetas;
    foreach (array_keys($banners_carpetas) as $estado) {
        if (file_exists(banners_ruta($estado, $n))) {
            return $estado;
        }
    }
    return null;
}

/**
 * Siguiente número libre, mirando todas las carpetas para que restaurar un
 * banner nunca choque con uno nuevo.
 */
function banners_siguiente_numero()
{
    global $banners_carpetas;
    $max = 0;
    foreach (array_keys($banners_carpetas) as $estado) {
        $numeros = array_keys(banners_listar($estado));
        if ($numeros) {
            $max = max($max, max($numeros));
        }
    }
    return $max + 1;
}

/**
 * Valida la imagen subida y la guarda recodificada como JPG de
 * BANNERS_ANCHO x BANNERS_ALTO en $destino. Devuelve un mensaje de error o ''.
 */
function banners_guardar_subida($campo, $destino)
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return 'No se recibió ningún archivo.';
    }
    $tmp = $_FILES[$campo]['tmp_name'];

    if ($_FILES[$campo]['size'] > BANNERS_MAX_MB * 1024 * 1024) {
        return 'El archivo supera los ' . BANNERS_MAX_MB . ' MB.';
    }

    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP), true)) {
        return 'El archivo no es una imagen JPG, PNG o WEBP válida.';
    }

    $proporcion = $info[0] / $info[1];
    if (abs($proporcion - BANNERS_ANCHO / BANNERS_ALTO) > 0.02) {
        return sprintf('Proporción incorrecta (%dx%d). Debe ser %dx%d o equivalente.',
            $info[0], $info[1], BANNERS_ANCHO, BANNERS_ALTO);
    }

    $origen = @imagecreatefromstring(file_get_contents($tmp));
    if (!$origen) {
        return 'No se pudo leer la imagen.';
    }

    // Recodificar siempre: normaliza tamaño y formato y descarta cualquier
    // contenido que no sea la imagen en sí.
    $lienzo = imagecreatetruecolor(BANNERS_ANCHO, BANNERS_ALTO);
    imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, BANNERS_ANCHO, BANNERS_ALTO, $info[0], $info[1]);

    // Escribir a un temporal y renombrar, para que el plugin nunca vea un JPG a medias.
    $temporal = $destino . '.tmp';
    $ok = imagejpeg($lienzo, $temporal, BANNERS_CALIDAD) && rename($temporal, $destino);

    if (!$ok) {
        @unlink($temporal);
        return 'Error al guardar el archivo en el servidor.';
    }
    return '';
}

function banners_mover($n, $de, $a)
{
    $carpeta = banners_ruta($a);
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0755)) {
        return false;
    }
    return @rename(banners_ruta($de, $n), banners_ruta($a, $n));
}

function banners_log($texto)
{
    global $mybb, $db, $uid;
    log_audit($uid, $db->escape_string($mybb->user['username']), 'banners', $db->escape_string($texto));
}

// ── POST: acciones ──────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion');
    $n      = $mybb->get_input('n', MyBB::INPUT_INT);
    $estado = $n > 0 ? banners_estado($n) : null;
    $error  = '';
    $ok     = '';

    switch ($accion) {
        case 'subir':
            $n = banners_siguiente_numero();
            $error = banners_guardar_subida('imagen', banners_ruta('activo', $n));
            $ok = "Banner $n subido y añadido a la rotación.";
            break;

        case 'desactivar':
            if ($estado !== 'activo') { $error = 'Ese banner no está activo.'; break; }
            if (in_array($n, $banners_protegidos, true)) { $error = "El Banner $n lo usa header.html con ruta fija: no se puede desactivar."; break; }
            if (banners_nombre($n) === op_banner_rotativo_fijo()) { $error = "El Banner $n está fijado: quítalo del banner fijo antes de desactivarlo."; break; }
            if (!banners_mover($n, 'activo', 'inactivo')) { $error = 'No se pudo mover el archivo.'; }
            $ok = "Banner $n desactivado.";
            break;

        case 'activar':
            if ($estado !== 'inactivo' && $estado !== 'papelera') { $error = 'Ese banner no se puede activar.'; break; }
            if (!banners_mover($n, $estado, 'activo')) { $error = 'No se pudo mover el archivo.'; }
            $ok = "Banner $n activado.";
            break;

        case 'eliminar':
            if ($estado !== 'activo' && $estado !== 'inactivo') { $error = 'Ese banner no se puede eliminar.'; break; }
            if (in_array($n, $banners_protegidos, true)) { $error = "El Banner $n lo usa header.html con ruta fija: no se puede eliminar."; break; }
            if (banners_nombre($n) === op_banner_rotativo_fijo()) { $error = "El Banner $n está fijado: quítalo del banner fijo antes de eliminarlo."; break; }
            if (!banners_mover($n, $estado, 'papelera')) { $error = 'No se pudo mover el archivo.'; }
            $ok = "Banner $n enviado a la papelera.";
            break;

        case 'fijar':
            if ($estado !== 'activo') { $error = $n > 0 ? "El Banner $n no existe o no está activo." : 'Indica el número de un banner activo.'; break; }
            $cache->update(OP_BANNER_ROTATIVO_FIJO, banners_nombre($n));
            $ok = "Banner $n fijado: el header lo muestra siempre hasta que se quite.";
            break;

        case 'desfijar':
            $cache->delete(OP_BANNER_ROTATIVO_FIJO);
            $ok = 'Banner fijo quitado: el header vuelve a rotar cada ' . (OP_BANNER_ROTATIVO_INTERVALO / 60) . ' minutos.';
            break;

        default:
            $error = 'Acción desconocida.';
    }

    if ($error === '') {
        banners_log($ok);
    }

    // Post/Redirect/Get: recargar la página no repite la acción. El aviso viaja
    // en una cookie de un solo uso (se borra al leerla) en vez de en la URL.
    // base64: my_setcookie hace urlencode() y PHP no deshace el "+" de los
    // espacios al leer $_COOKIE, así que sin esto un mensaje con espacios
    // volvería con "+" literales.
    my_setcookie(BANNERS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $ok,
    ))), 15, true);
    header('Location: banners.php');
    exit;
}

// ── GET: listado ────────────────────────────────────────────────────────────

$activos    = banners_listar('activo');
$inactivos  = banners_listar('inactivo');
$papelera   = banners_listar('papelera');
$fijo       = op_banner_rotativo_fijo();
$fijo_n     = $fijo !== '' && preg_match(OP_BANNER_ROTATIVO_PATRON, $fijo, $m) ? (int)$m[1] : 0;
$rotacion   = $cache->read(OP_BANNER_ROTATIVO_CACHE);
$actual     = $fijo !== '' ? $fijo : (is_array($rotacion) ? $rotacion['actual'] : '');
$post_key   = generate_post_check();

$msg_ok  = '';
$msg_err = '';
if (!empty($mybb->cookies[BANNERS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[BANNERS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(BANNERS_MSG_COOKIE);
}

function banners_url($estado, $n)
{
    global $banners_carpetas;
    $archivo = banners_ruta($estado, $n);
    return '/' . OP_BANNER_ROTATIVO_DIR . $banners_carpetas[$estado] . banners_nombre($n) . '?v=' . @filemtime($archivo);
}


function banners_boton($accion, $n, $texto, $clase, $confirmar = '')
{
    global $post_key;
    $onsubmit = $confirmar !== '' ? ' onsubmit="return confirm(\'' . htmlspecialchars($confirmar, ENT_QUOTES) . '\')"' : '';
    return '<form method="post" class="inline"' . $onsubmit . '>'
        . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($post_key) . '">'
        . '<input type="hidden" name="accion" value="' . $accion . '">'
        . '<input type="hidden" name="n" value="' . (int)$n . '">'
        . '<button class="btn-op ' . $clase . '">' . $texto . '</button></form>';
}

function banners_tarjeta($estado, $n, $actual)
{
    global $banners_protegidos, $fijo_n;
    $es_actual = $estado === 'activo' && banners_nombre($n) === $actual;
    $es_fijo   = $es_actual && (int)$n === $fijo_n;
    $protegido = in_array((int)$n, $banners_protegidos, true);

    $html  = '<div class="banner-card' . ($es_actual ? ' actual' : '') . '">';
    $html .= '<div class="barra-op bbox banner-card-head"><span class="barra-texto-op">Banner ' . (int)$n . '</span>'
        . ($es_actual ? '<span class="badge badge-actual">' . ($es_fijo ? 'Fijado' : 'En el header') . '</span>' : '')
        . ($protegido ? '<span class="badge badge-protegido" title="header.html lo usa con ruta fija">Protegido</span>' : '')
        . '</div>';
    $html .= '<div class="barra-espacio-op bbox banner-card-body">';
    $html .= '<a class="miniatura" href="' . banners_url($estado, $n) . '" target="_blank"><img src="' . banners_url($estado, $n) . '" loading="lazy" alt=""></a>';
    $html .= '<div class="acciones">';

    if ($estado === 'activo') {
        if (!$protegido && !$es_fijo) {
            $html .= banners_boton('desactivar', $n, 'Desactivar', 'btn-naranja');
            $html .= banners_boton('eliminar', $n, 'Eliminar', 'btn-rojo', "¿Enviar el Banner $n a la papelera?");
        }
    } elseif ($estado === 'inactivo') {
        $html .= banners_boton('activar', $n, 'Activar', 'btn-verde');
        $html .= banners_boton('eliminar', $n, 'Eliminar', 'btn-rojo', "¿Enviar el Banner $n a la papelera?");
    } else {
        $html .= banners_boton('activar', $n, 'Restaurar', 'btn-verde');
    }

    $html .= '</div>';
    $html .= '</div></div>';

    return $html;
}

function banners_seccion($titulo, $estado, $banners, $actual, $vacio, $plegable = false, $abierta = true)
{
    $cuerpo = $banners
        ? '<div class="grid">' . implode('', array_map(function ($n) use ($estado, $actual) {
            return banners_tarjeta($estado, $n, $actual);
        }, array_keys($banners))) . '</div>'
        : '<p class="vacio">' . $vacio . '</p>';

    $barra = '<span>' . $titulo . ' (' . count($banners) . ')</span>';

    if ($plegable) {
        return '<details class="seccion"' . ($abierta ? ' open' : '') . '>'
            . '<summary class="seccion-barra">' . $barra . '</summary>'
            . '<div class="seccion-cuerpo">' . $cuerpo . '</div></details>';
    }
    return '<div class="seccion"><div class="seccion-barra">' . $barra . '</div>'
        . '<div class="seccion-cuerpo">' . $cuerpo . '</div></div>';
}

// Se pinta dentro del layout del foro ($headerinclude/$header/$footer de
// global.php) y con las clases de op_global.css, como op_intercambio.
ob_start();
?>
<html>
<head>
<title>Banners del header — <?= htmlspecialchars($mybb->settings['bbname']) ?></title>
<?= $headerinclude ?>
<style>
.banners-staff .thirdBackground { width: 100%; max-width: 1030px; gap: 20px; }

.banners-volver { font-family: moonGetHeavy; color: #6c10ab; text-decoration: none; letter-spacing: 1px; font-size: 13px; }
.banners-volver:hover { color: #8f59f7; }

.banners-titulo { padding: 8px; }
.banners-titulo .barra-texto-op { font-size: 26px; letter-spacing: 2px; text-shadow: 2px 2px 0 black; }
.banners-descripcion { font-family: InterRegular; font-size: 13px; color: #3b1300; text-align: left; padding: 10px 14px; line-height: 1.5; }

.aviso {
    font-family: moonGetHeavy; color: #fff; letter-spacing: 1px; text-shadow: 1px 1px 1px black;
    border: 2px solid black; border-radius: 10px; padding: 10px 14px; margin: 0;
}
.aviso.ok  { background: #27ae60; }
.aviso.err { background: #dc3545; }

.subir-barra { padding: 3px 8px 4px; line-height: 1.25; }
.panel-cuerpo { padding: 12px 14px; display: flex; gap: 10px 16px; align-items: center; justify-content: center; flex-wrap: wrap; }

/* El botón Subir solo aparece cuando hay un archivo elegido (input required → :valid). */
.subir-boton { display: none; }
.subir-form:has(input[type=file]:valid) .subir-boton { display: inline-block; }

.fijo-estado { font-family: InterRegular; font-size: 13px; color: #3b1300; }
.fijo-estado strong { font-family: moonGetHeavy; letter-spacing: 1px; color: #6c10ab; }
.fijo-form { display: flex; gap: 8px; align-items: center; margin: 0; }
.fijo-form label { font-family: moonGetHeavy; letter-spacing: 1px; font-size: 13px; color: #000; }
.fijo-form input[type=number] {
    width: 80px; font-family: InterMedium; font-size: 13px; color: #3b1300; background: #fff;
    border: 2px solid black; border-radius: 6px; padding: 4px 6px;
}

/* Barra de sección: mismo patrón que "Historial de Intercambios" */
.seccion-barra {
    display: block; background: #ff7b00; border: 2px solid black; border-radius: 10px 10px 0 0;
    text-align: center; padding: 5px 10px; list-style: none; cursor: default;
}
.seccion-barra span {
    font-family: moonGetHeavy; color: #fff; text-transform: uppercase; font-size: 14px;
    letter-spacing: 1px; text-shadow: 1px 1px 1px black;
}
summary.seccion-barra { cursor: pointer; transition: background .3s ease; }
summary.seccion-barra::-webkit-details-marker { display: none; }
summary.seccion-barra:hover { background: #ffa600; }
summary.seccion-barra span::after { content: " ▼"; font-size: 10px; }
details[open] > summary.seccion-barra span::after { content: " ▲"; }
.seccion-cuerpo { background: #ffeed2; border: 2px solid black; border-top: 0; padding: 15px; }
details.seccion:not([open]) > summary.seccion-barra { border-radius: 10px; }

.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }

.banner-card { min-width: 0; filter: drop-shadow(0 0 3px rgba(0,0,0,.5)); transition: all .3s ease-out; }
.banner-card:hover { transform: scale(1.02); }
.banner-card-head { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 4px 6px; }
.banner-card-head .barra-texto-op { font-size: 15px; margin-right: auto; }
.banner-card-body { padding: 10px; background: #fcecd2; }
.banner-card.actual .banner-card-head { background: #8f59f7; }
.banner-card.actual .banner-card-body { background: #e8d9ff; }

.miniatura { display: block; overflow: hidden; border: 2px solid black; }
.miniatura img {
    width: 100%; aspect-ratio: 1100 / 620; object-fit: cover; display: block;
    opacity: .9; filter: grayscale(.2); transition: all .25s ease;
}
.banner-card:hover .miniatura img { opacity: 1; filter: grayscale(0); transform: scale(1.05); }

.badge {
    font-family: moonGetHeavy; font-size: 10px; letter-spacing: 1px; color: #fff;
    text-shadow: 1px 1px 0 black; border: 2px solid black; border-radius: 6px; padding: 1px 6px;
}
.badge-actual    { background: #6c10ab; }
.badge-protegido { background: #71706f; }

.acciones { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; margin: 10px 0 0; }
.inline { display: inline; margin: 0; }

.btn-op {
    font-family: moonGetHeavy; font-size: 13px; letter-spacing: 1px; color: #fff;
    text-shadow: 1px 1px 1px black; border: 2px solid black; border-radius: 10px;
    padding: 5px 10px; cursor: pointer; transition: all .3s ease-out;
}
.btn-op:hover { transform: scale(1.05); }
.btn-morado  { background: rgba(70,12,110,1); } .btn-morado:hover  { background: rgba(126,32,191,1); }
.btn-naranja { background: #d26500; }           .btn-naranja:hover { background: #ff9b00; }
.btn-verde   { background: #27ae60; }           .btn-verde:hover   { background: #2ecc71; }
.btn-rojo    { background: #dc3545; }           .btn-rojo:hover    { background: #e74c3c; }

.banners-staff input[type=file] { font-family: InterRegular; font-size: 12px; color: #3b1300; }
.banners-staff input[type=file]::file-selector-button {
    font-family: moonGetHeavy; letter-spacing: 1px; background: #ffe59b; color: #000;
    border: 2px solid black; border-radius: 6px; padding: 3px 8px; margin-right: 8px; cursor: pointer;
}
.banners-staff input[type=file]::file-selector-button:hover { background: #ffa600; }

.vacio { font-family: InterRegular; color: #5e5e5e; font-style: italic; text-align: center; margin: 0; }

@media (max-width: 700px) {
    .banners-staff .secondBackground, .banners-staff .thirdBackground { padding: 10px; }
    .banners-titulo .barra-texto-op { font-size: 20px; }
    .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?= $header ?>
<div class="indice banners-staff">
<div class="mainBackground">
<div class="secondBackground">
<div class="thirdBackground">

    <div>
        <a class="banners-volver" href="/op/staff/consola_mod.php">&larr; Consola</a>
    </div>

    <div>
        <div class="barra-op bbox banners-titulo"><span class="barra-texto-op">Banners del header</span></div>
        <div class="barra-espacio-op bbox banners-descripcion">
            El header cambia de banner cada <?= OP_BANNER_ROTATIVO_INTERVALO / 60 ?> minutos entre los activos, salvo que haya uno fijado.
            Los nuevos se guardan siempre como JPG de <?= BANNERS_ANCHO ?>×<?= BANNERS_ALTO ?>.
        </div>
    </div>

    <?php if ($msg_ok !== ''): ?><p class="aviso ok"><?= htmlspecialchars($msg_ok) ?></p><?php endif; ?>
    <?php if ($msg_err !== ''): ?><p class="aviso err"><?= htmlspecialchars($msg_err) ?></p><?php endif; ?>

    <div>
        <div class="barra-op bbox subir-barra">
            <span class="barra-texto-op" style="font-size: 15px;">Banner fijo</span>
            <br>
            <span class="barra-texto-op" style="font-size: 9px;">(El header muestra siempre ese banner, sin rotar, hasta que se quite)</span>
        </div>
        <div class="barra-espacio-op bbox panel-cuerpo">
            <?php if ($fijo_n): ?>
                <span class="fijo-estado">Fijado ahora: <strong>Banner <?= $fijo_n ?></strong></span>
                <?= banners_boton('desfijar', 0, 'Quitar', 'btn-naranja') ?>
            <?php else: ?>
                <span class="fijo-estado">Ninguno: el header rota cada <?= OP_BANNER_ROTATIVO_INTERVALO / 60 ?> minutos.</span>
            <?php endif; ?>
            <form method="post" class="fijo-form">
                <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">
                <input type="hidden" name="accion" value="fijar">
                <label for="fijo-n">Banner nº</label>
                <input type="number" id="fijo-n" name="n" min="1" required list="banners-activos">
                <datalist id="banners-activos">
                    <?php foreach (array_keys($activos) as $n): ?><option value="<?= $n ?>"><?php endforeach; ?>
                </datalist>
                <button class="btn-op btn-morado"><?= $fijo_n ? 'Cambiar' : 'Fijar' ?></button>
            </form>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="subir-form">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">
        <input type="hidden" name="accion" value="subir">
        <div class="barra-op bbox subir-barra">
            <span class="barra-texto-op" style="font-size: 15px;">Nuevo banner</span>
            <br>
            <span class="barra-texto-op" style="font-size: 9px;">(JPG, PNG o WEBP · proporción <?= BANNERS_ANCHO ?>×<?= BANNERS_ALTO ?> · máximo <?= BANNERS_MAX_MB ?> MB)</span>
        </div>
        <div class="barra-espacio-op bbox panel-cuerpo">
            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" required>
            <button class="btn-op btn-morado subir-boton">Subir</button>
        </div>
    </form>

    <?= banners_seccion('Activos', 'activo', $activos, $actual, 'No hay banners activos: el header muestra el banner de respaldo.') ?>
    <?= banners_seccion('Inactivos', 'inactivo', $inactivos, $actual, 'Ninguno.', true, (bool)$inactivos) ?>
    <?= banners_seccion('Papelera', 'papelera', $papelera, $actual, 'Vacía.', true, false) ?>

</div>
</div>
</div>
</div>
<?= $footer ?>
</body>
</html>
<?php
$page = ob_get_clean();
output_page($page);
