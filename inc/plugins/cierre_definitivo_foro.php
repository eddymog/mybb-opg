<?php
/**
 * Cierre Definitivo del Foro
 *
 * A partir del 15/07/2026 00:30 (hora Europe/Madrid):
 *  - Cualquier usuario que no sea staff o narrador es redirigido al índice
 *    sin importar a qué página intente acceder.
 *  - El Gacha de Aniversario (op/aniversario_gacha.php y aniversario_gacha_spin.php)
 *    cierra permanentemente para TODOS, sin excepción, incluido staff.
 *  - Entrenamiento personaje (entrenamiento.php), entrenamiento de oficio (oficios.php)
 *    y entrenamiento de técnicas (entrenamiento_tecnicas.php) quedan igualmente
 *    bloqueados para TODOS sin excepción, incluido staff/narradores.
 *  - op/crafteo.php también queda bloqueado para TODOS sin excepción. OJO: crafteo.php
 *    define THIS_SCRIPT='viajes.php' (bug de copy-paste ya documentado en CLAUDE.md),
 *    y ese THIS_SCRIPT lo comparte el viajes.php real (que debe seguir siendo
 *    accesible para staff/narradores), así que crafteo.php se detecta por ruta
 *    real de script, no por THIS_SCRIPT.
 *
 * member.php se deja siempre accesible para que staff/narradores puedan
 * seguir iniciando sesión aunque su sesión ya haya expirado.
 *
 * op/recompensas.php también se deja accesible para todo el mundo. OJO: existe
 * otro archivo llamado igual en /opg/recompensas.php (mismo THIS_SCRIPT), por
 * lo que la excepción se hace por ruta real de script, no por THIS_SCRIPT, para
 * no abrir por error también la copia de /opg/.
 *
 * opg/tirada_cofre.php (tirada de cofres) también se deja accesible para todo
 * el mundo. OJO: ese archivo define THIS_SCRIPT='tirada_rey.php' (bug de
 * copy-paste ya conocido en el proyecto, ver CLAUDE.md), y ese mismo THIS_SCRIPT
 * lo comparten los dos tirada_rey.php reales (op/ y opg/), así que la excepción
 * se hace por ruta real de script, no por THIS_SCRIPT, para no abrir por error
 * también las páginas de tirada del rey.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

$plugins->add_hook('global_start', 'cierre_definitivo_foro_check');
$plugins->add_hook('pre_output_page', 'cierre_definitivo_foro_inject_js');
$plugins->add_hook('pre_output_page', 'cierre_definitivo_foro_inject_popup_mantenimiento');

function cierre_definitivo_foro_info()
{
    return array(
        'name'          => 'Cierre Definitivo del Foro',
        'description'   => 'A partir del 15/07/2026 00:30 (hora Madrid) redirige al índice a cualquier usuario que no sea staff o narrador, y cierra permanentemente el Gacha de Aniversario para todos, sin excepción.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

function cierre_definitivo_foro_install() {}
function cierre_definitivo_foro_is_installed() { return true; }
function cierre_definitivo_foro_uninstall() {}
function cierre_definitivo_foro_activate() {}
function cierre_definitivo_foro_deactivate() {}

define('CIERRE_DEFINITIVO_FORO_FECHA', '2026-07-15 00:30:00');

// Páginas que siguen siendo accesibles para todo el mundo aunque el cierre esté activo
$GLOBALS['CIERRE_DEFINITIVO_FORO_PERMITIDAS'] = array('index.php', 'member.php');

// Páginas bloqueadas para TODOS sin excepción (incluido staff/narradores) una vez activo el cierre.
// aniversario_gacha_spin.php no está aquí porque responde JSON, no HTML: se gestiona aparte.
$GLOBALS['CIERRE_DEFINITIVO_FORO_SIN_EXCEPCION'] = array(
    'aniversario_gacha.php',
    'entrenamiento.php',           // Entrenamiento personaje
    'oficios.php',                 // Entrenamiento oficio
    'entrenamiento_tecnicas.php',  // Entrenamiento técnicas
);

/**
 * op/recompensas.php y opg/recompensas.php comparten THIS_SCRIPT ('recompensas.php'),
 * así que hay que distinguirlos por la ruta real del script, no por THIS_SCRIPT.
 */
function cierre_definitivo_foro_es_recompensas_op()
{
    $script_path = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $needle = '/op/recompensas.php';
    return (substr($script_path, -strlen($needle)) === $needle);
}

/**
 * crafteo.php define THIS_SCRIPT='viajes.php' (bug de copy-paste ya conocido), y ese
 * mismo THIS_SCRIPT lo comparte el viajes.php real, así que hay que distinguirlos
 * por la ruta real del script, no por THIS_SCRIPT.
 */
function cierre_definitivo_foro_es_crafteo()
{
    $script_path = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $needle = '/crafteo.php';
    return (substr($script_path, -strlen($needle)) === $needle);
}

/**
 * opg/tirada_cofre.php define THIS_SCRIPT='tirada_rey.php' (bug de copy-paste ya
 * conocido), y ese mismo THIS_SCRIPT lo comparten op/tirada_rey.php y
 * opg/tirada_rey.php (reales), así que hay que distinguirlo por la ruta real
 * del script, no por THIS_SCRIPT.
 */
function cierre_definitivo_foro_es_tirada_cofre()
{
    $script_path = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $needle = '/opg/tirada_cofre.php';
    return (substr($script_path, -strlen($needle)) === $needle);
}

function cierre_definitivo_foro_activo()
{
    static $activo = null;
    if ($activo === null) {
        $ahora  = new DateTime('now', new DateTimeZone('Europe/Madrid'));
        $cierre = new DateTime(CIERRE_DEFINITIVO_FORO_FECHA, new DateTimeZone('Europe/Madrid'));
        $activo = ($ahora >= $cierre);
    }
    return $activo;
}

function cierre_definitivo_foro_check()
{
    global $mybb;

    if (!cierre_definitivo_foro_activo()) {
        return;
    }

    $current_page = defined('THIS_SCRIPT') ? my_strtolower(basename(THIS_SCRIPT)) : '';

    // El Gacha de Aniversario y los entrenamientos cierran para TODOS sin excepción,
    // incluido staff/narradores.
    if ($current_page === 'aniversario_gacha_spin.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'El Gacha de Aniversario ha cerrado definitivamente.'));
        exit;
    }
    if (in_array($current_page, $GLOBALS['CIERRE_DEFINITIVO_FORO_SIN_EXCEPCION'], true) || cierre_definitivo_foro_es_crafteo()) {
        header('Location: ' . $mybb->settings['bburl'] . '/index.php');
        exit;
    }

    // Resto del foro: solo accesible para staff o equipo de narradores.
    require_once MYBB_ROOT . 'op/functions/op_functions.php';
    $uid = (int)$mybb->user['uid'];
    if (is_staff($uid) || is_mod($uid) || is_user($uid) || is_narra($uid)) {
        return;
    }

    if (in_array($current_page, $GLOBALS['CIERRE_DEFINITIVO_FORO_PERMITIDAS'], true)) {
        return;
    }

    if ($current_page === 'recompensas.php' && cierre_definitivo_foro_es_recompensas_op()) {
        return;
    }

    if (cierre_definitivo_foro_es_tirada_cofre()) {
        return;
    }

    header('Location: ' . $mybb->settings['bburl'] . '/index.php');
    exit;
}

/**
 * El hook global_start solo detiene la SIGUIENTE petición que haga el navegador.
 * Quien ya tenía una pestaña cargada antes del cierre se queda con esa página
 * servida y no la vuelve a pedir hasta que haga clic en algo. Esto inyecta un
 * script que compara la hora del cierre contra el reloj del navegador y saca a
 * esas pestañas ya abiertas sin esperar a la próxima acción del usuario.
 */
function cierre_definitivo_foro_inject_js($contents)
{
    global $mybb;

    // Si ya estamos en o después del cierre, global_start ya habrá cortado esta
    // petición para quien no tenga que verla; si llegamos aquí es que sí puede verla.
    $current_page = defined('THIS_SCRIPT') ? my_strtolower(basename(THIS_SCRIPT)) : '';
    if (in_array($current_page, array('index.php', 'member.php', 'aniversario_gacha_spin.php'), true)) {
        return $contents;
    }
    if ($current_page === 'recompensas.php' && cierre_definitivo_foro_es_recompensas_op()) {
        return $contents;
    }

    if (cierre_definitivo_foro_es_tirada_cofre()) {
        return $contents;
    }

    $sin_excepcion = in_array($current_page, $GLOBALS['CIERRE_DEFINITIVO_FORO_SIN_EXCEPCION'], true)
        || cierre_definitivo_foro_es_crafteo();

    // Fuera de las páginas sin excepción, staff/narradores no deben ser expulsados
    // de sus propias pestañas.
    if (!$sin_excepcion) {
        require_once MYBB_ROOT . 'op/functions/op_functions.php';
        $uid = (int)$mybb->user['uid'];
        if (is_staff($uid) || is_mod($uid) || is_user($uid) || is_narra($uid)) {
            return $contents;
        }
    }

    $cierre = new DateTime(CIERRE_DEFINITIVO_FORO_FECHA, new DateTimeZone('Europe/Madrid'));
    $cierre_ts_ms = $cierre->getTimestamp() * 1000;
    $index_url = $mybb->settings['bburl'] . '/index.php';

    $script = '<script>(function(){var t=' . $cierre_ts_ms . ';function chk(){if(Date.now()>=t){window.location.href=' . json_encode($index_url) . ';}}chk();setInterval(chk,20000);})();</script>';

    if (strpos($contents, '</body>') !== false) {
        $contents = str_replace('</body>', $script . '</body>', $contents);
    } else {
        $contents .= $script;
    }

    return $contents;
}

/**
 * Cartelito emergente en el índice avisando del cierre por mantenimiento,
 * mientras el cierre definitivo esté activo. Solo se muestra a quien no
 * sea staff/narrador (ellos ya saben que el foro está cerrado).
 */
function cierre_definitivo_foro_inject_popup_mantenimiento($contents)
{
    global $mybb;

    if (!cierre_definitivo_foro_activo()) {
        return $contents;
    }

    $current_page = defined('THIS_SCRIPT') ? my_strtolower(basename(THIS_SCRIPT)) : '';
    if ($current_page !== 'index.php') {
        return $contents;
    }

    require_once MYBB_ROOT . 'op/functions/op_functions.php';
    $uid = (int)$mybb->user['uid'];
    if (is_staff($uid) || is_mod($uid) || is_user($uid) || is_narra($uid)) {
        return $contents;
    }

    $mensaje = 'El foro se encuentra actualmente cerrado por mantenimiento, pronto volveremos a abrir. ¡Gracias por la paciencia!';

    $popup = '
<div id="cierreDefinitivoForoPopupOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;">
  <div style="background:#1a1a1a;color:#eee;max-width:420px;width:90%;padding:28px 24px;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,0.5);text-align:center;font-family:Arial,sans-serif;border:1px solid #333;">
    <div style="font-size:2.2em;margin-bottom:10px;">&#128736;</div>
    <p style="margin:0 0 20px;font-size:1.05em;line-height:1.5;">' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>
    <button onclick="document.getElementById(\'cierreDefinitivoForoPopupOverlay\').style.display=\'none\';" style="background:#28ce26;color:#000;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:1em;">Entendido</button>
  </div>
</div>';

    if (strpos($contents, '</body>') !== false) {
        $contents = str_replace('</body>', $popup . '</body>', $contents);
    } else {
        $contents .= $popup;
    }

    return $contents;
}
