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


/**
 * Botón secundario compacto (.opg-chip, ver docs/style.md §7): una tarjeta de
 * banner puede tener varias acciones, así que ninguna es "la" acción de la
 * página — eso es .btn-op, reservado para Subir y Fijar/Cambiar.
 */
function banners_chip($accion, $n, $texto, $confirmar = '', $peligro = false)
{
    global $post_key;
    $onsubmit = $confirmar !== '' ? ' onsubmit="return confirm(\'' . htmlspecialchars($confirmar, ENT_QUOTES) . '\')"' : '';
    $clase = 'opg-chip' . ($peligro ? ' chip-peligro' : '');
    return '<form method="post" class="inline"' . $onsubmit . '>'
        . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($post_key) . '">'
        . '<input type="hidden" name="accion" value="' . $accion . '">'
        . '<input type="hidden" name="n" value="' . (int)$n . '">'
        . '<button type="submit" class="' . $clase . '">' . $texto . '</button></form>';
}

function banners_tarjeta($estado, $n, $actual)
{
    global $banners_protegidos, $fijo_n;
    $es_actual = $estado === 'activo' && banners_nombre($n) === $actual;
    $es_fijo   = $es_actual && (int)$n === $fijo_n;
    $protegido = in_array((int)$n, $banners_protegidos, true);

    // Acento por estado (ver docs/style.md §7): morado = en el header ahora mismo,
    // gris = inactivo, rojo = papelera, naranja = activo normal.
    if ($es_actual) {
        $acento = 'var(--opg-morado)';
    } elseif ($estado === 'papelera') {
        $acento = 'var(--opg-rojo-error)';
    } elseif ($estado === 'inactivo') {
        $acento = 'var(--opg-gris-bloqueado)';
    } else {
        $acento = 'var(--opg-naranja)';
    }

    $badges = '';
    if ($es_actual) {
        $badges .= '<span class="badge badge-actual">' . ($es_fijo ? 'Fijado' : 'En el header') . '</span>';
    }
    if ($protegido) {
        $badges .= '<span class="badge badge-protegido" title="header.html lo usa con ruta fija">Protegido</span>';
    }

    $acciones = '';
    if ($estado === 'activo') {
        if (!$protegido && !$es_fijo) {
            $acciones .= banners_chip('desactivar', $n, 'Desactivar');
            $acciones .= banners_chip('eliminar', $n, 'Eliminar', "¿Enviar el Banner $n a la papelera?", true);
        }
    } elseif ($estado === 'inactivo') {
        $acciones .= banners_chip('activar', $n, 'Activar');
        $acciones .= banners_chip('eliminar', $n, 'Eliminar', "¿Enviar el Banner $n a la papelera?", true);
    } else {
        $acciones .= banners_chip('activar', $n, 'Restaurar');
    }

    return '<div class="banner-tile" style="--tile-acento: ' . $acento . ';">'
        . '<a class="banner-tile-miniatura" href="' . banners_url($estado, $n) . '" target="_blank"><img src="' . banners_url($estado, $n) . '" loading="lazy" alt=""></a>'
        . '<div class="banner-tile-info">'
        . '<div class="banner-tile-cabecera"><span class="banner-tile-nombre">Banner ' . (int)$n . '</span>' . $badges . '</div>'
        . '<div class="banner-tile-acciones">' . $acciones . '</div>'
        . '</div></div>';
}

/**
 * Título de sección con raya de acento (ver docs/style.md §7: "menos cajas
 * anidadas") en vez de otra barra naranja repetida tres veces.
 */
function banners_seccion($titulo, $estado, $banners, $actual, $vacio, $acento, $plegable = false, $abierta = true)
{
    $cuerpo = $banners
        ? '<div class="grid">' . implode('', array_map(function ($n) use ($estado, $actual) {
            return banners_tarjeta($estado, $n, $actual);
        }, array_keys($banners))) . '</div>'
        : '<p class="opg-vacio">' . $vacio . '</p>';

    $texto_titulo = htmlspecialchars($titulo) . ' (' . count($banners) . ')';
    $estilo = ' style="--opg-card-acento: ' . $acento . ';"';

    if ($plegable) {
        return '<details class="seccion"' . ($abierta ? ' open' : '') . '>'
            . '<summary class="seccion-titulo"' . $estilo . '>' . $texto_titulo . '<span class="seccion-flecha">&#9660;</span></summary>'
            . $cuerpo . '</details>';
    }
    return '<section class="seccion"><h2 class="seccion-titulo"' . $estilo . '>' . $texto_titulo . '</h2>' . $cuerpo . '</section>';
}

// Se pinta dentro del layout del foro ($headerinclude/$header/$footer de
// global.php) y con las clases de op_global.css, como op_intercambio.
ob_start();
?>
<html>
<head>
<title>Banners del header — <?= htmlspecialchars($mybb->settings['bbname']) ?></title>
<?= $headerinclude ?>
<!-- opg-tokens.css ya lo carga $headerinclude (ver docs/style.md §7) -->
<style>
/* text-align: el body del tema (global.css) centra todo el texto (ver style.md §7) */
.banners-staff .thirdBackground { width: 100%; max-width: 1030px; gap: var(--opg-espacio-5); text-align: left; }

.banners-titulo { padding: var(--opg-espacio-2); }
.banners-titulo .barra-texto-op { font-size: 26px; letter-spacing: 2px; text-shadow: 2px 2px 0 black; }
.banners-descripcion { font-family: var(--opg-fuente-cuerpo); font-size: 13px; color: var(--opg-marron); text-align: left; padding: 10px 14px; line-height: 1.5; }

.aviso {
    font-family: var(--opg-fuente-cuerpo); color: #fff; margin: 0;
    border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-md); padding: 10px 14px;
}
.aviso.ok  { background: var(--opg-verde-exito); }
.aviso.err { background: var(--opg-rojo-error); }

.subir-barra { padding: 3px 8px 4px; line-height: 1.25; }
.panel-cuerpo { padding: var(--opg-espacio-3) var(--opg-espacio-4); display: flex; gap: var(--opg-espacio-2) var(--opg-espacio-4); align-items: center; justify-content: center; flex-wrap: wrap; }

/* El botón Subir solo aparece cuando hay un archivo elegido (input required → :valid). */
.subir-boton { display: none; }
.subir-form:has(input[type=file]:valid) .subir-boton { display: inline-block; }

.fijo-estado { font-family: var(--opg-fuente-cuerpo); font-size: 13px; color: var(--opg-marron); }
.fijo-estado strong { font-family: var(--opg-fuente-titular); letter-spacing: 1px; color: var(--opg-morado-texto); }
.fijo-form { display: flex; gap: var(--opg-espacio-2); align-items: center; margin: 0; }
.fijo-form label { font-family: var(--opg-fuente-cuerpo); font-weight: bold; font-size: 13px; color: var(--opg-marron); }
.fijo-form input[type=number] {
    width: 80px; font-family: var(--opg-fuente-cuerpo); font-size: 13px; color: var(--opg-marron); background: #fff;
    border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); padding: 4px 6px;
}

/* Título de sección con raya de acento, sin caja propia (ver style.md §7: "menos cajas anidadas") */
.seccion-titulo {
    display: flex; align-items: center; gap: var(--opg-espacio-2); margin: 0 0 var(--opg-espacio-4);
    font-family: var(--opg-fuente-titular); font-weight: normal; font-size: 18px; letter-spacing: 1px; text-transform: uppercase;
    color: var(--opg-ciruela); padding-bottom: 4px; border-bottom: 5px solid var(--opg-card-acento, var(--opg-naranja));
}
summary.seccion-titulo { cursor: pointer; list-style: none; }
summary.seccion-titulo::-webkit-details-marker { display: none; }
.seccion-flecha { margin-left: auto; font-size: 11px; transition: transform var(--opg-rapido); }
details[open] > summary.seccion-titulo .seccion-flecha { transform: rotate(180deg); }
details.seccion { margin-bottom: 0; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(220px, 100%), 1fr)); gap: var(--opg-espacio-4); }

/* Tarjeta viñeta para miniaturas (ver style.md §7 .opg-card--media, y op_upload ".subida") */
.banner-tile {
    display: flex; flex-direction: column; min-width: 0;
    background:
        linear-gradient(var(--tile-acento, var(--opg-naranja)), var(--tile-acento, var(--opg-naranja))) top / 100% 5px no-repeat,
        var(--opg-crema-clara);
    border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-md); overflow: hidden;
    box-shadow: var(--opg-sombra-offset); transition: transform var(--opg-rapido), box-shadow var(--opg-rapido);
}
.banner-tile:hover { transform: translate(-2px, -2px); box-shadow: var(--opg-sombra-offset-hover); }
.banner-tile-miniatura { display: block; aspect-ratio: 1100 / 620; margin-top: 5px; border-bottom: 2px solid var(--opg-tinta); overflow: hidden; }
.banner-tile-miniatura img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    opacity: .9; filter: grayscale(.15); transition: all var(--opg-rapido);
}
.banner-tile:hover .banner-tile-miniatura img { opacity: 1; filter: grayscale(0); transform: scale(1.05); }
.banner-tile-info { display: flex; flex-direction: column; gap: 6px; padding: var(--opg-espacio-2); }
.banner-tile-cabecera { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.banner-tile-nombre { font-family: var(--opg-fuente-titular); font-size: 13px; letter-spacing: .5px; color: var(--opg-ciruela); margin-right: auto; }
.banner-tile-acciones { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
.banner-tile-acciones .opg-chip { font-size: 11px; padding: 2px 8px; }
.opg-chip.chip-peligro:hover { background: var(--opg-rojo-error); color: #fff; }
.inline { display: inline; margin: 0; }

.badge {
    font-family: InterMedium, var(--opg-fuente-cuerpo); font-size: 10px; letter-spacing: .5px; color: #fff;
    border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); padding: 1px 6px;
}
.badge-actual    { background: var(--opg-morado-texto); }
.badge-protegido { background: var(--opg-gris-bloqueado); }

.banners-staff input[type=file] { font-family: var(--opg-fuente-cuerpo); font-size: 12px; color: var(--opg-marron); }
.banners-staff input[type=file]::file-selector-button {
    font-family: var(--opg-fuente-titular); letter-spacing: 1px; background: var(--opg-crema-dorada); color: #000;
    border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); padding: 3px 8px; margin-right: 8px; cursor: pointer;
}

@media (max-width: 700px) {
    .banners-staff .secondBackground, .banners-staff .thirdBackground { padding: 10px; }
    .banners-titulo .barra-texto-op { font-size: 20px; }
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
        <a class="opg-volver" href="/op/staff/consola_mod.php">&larr; Consola</a>
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
                <?= banners_chip('desfijar', 0, 'Quitar') ?>
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
                <button class="btn-op btn-op--secundario"><?= $fijo_n ? 'Cambiar' : 'Fijar' ?></button>
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
            <button class="btn-op btn-op--secundario subir-boton">Subir</button>
        </div>
    </form>

    <?= banners_seccion('Activos', 'activo', $activos, $actual, 'No hay banners activos: el header muestra el banner de respaldo.', 'var(--opg-naranja)') ?>
    <?= banners_seccion('Inactivos', 'inactivo', $inactivos, $actual, 'Ninguno.', 'var(--opg-gris-bloqueado)', true, (bool)$inactivos) ?>
    <?= banners_seccion('Papelera', 'papelera', $papelera, $actual, 'Vacía.', 'var(--opg-rojo-error)', true, false) ?>

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
