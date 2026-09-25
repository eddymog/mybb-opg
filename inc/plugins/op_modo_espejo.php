<?php
/**
 * OPG - Modo espejo.
 *
 * Permite a FID 315 (y solo a esa cuenta) reflejar la sesion de cualquier
 * usuario para debug, sin tocar su contrasena ni su loginkey, y sin crear
 * ningun vinculo persistente en la base de datos. Todo el estado vive en
 * cookies (ver docs/200_DesignPlan_ModoEspejo.md).
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

define('OP_MODO_ESPEJO_ADMIN_UID', 315);
define('OP_MODO_ESPEJO_TTL', 7200);
define('OP_MODO_ESPEJO_COOKIE_RETORNO', 'modo_espejo_retorno');
define('OP_MODO_ESPEJO_COOKIE_ACTIVO', 'modo_espejo_activo');

$plugins->add_hook('global_intermediate', 'op_modo_espejo_hook_header');
$plugins->add_hook('member_logout_end', 'op_modo_espejo_hook_logout');

function op_modo_espejo_info()
{
    return array(
        'name' => 'OPG - Modo espejo',
        'description' => 'Permite a FID 315 reflejar la sesion de cualquier usuario para debug, sin tocar contrasenas.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.0',
        'compatibility' => '18*',
    );
}

function op_modo_espejo_activate() {}
function op_modo_espejo_deactivate() {}

/**
 * Parsea y valida el formato de la cookie de retorno:
 * "<uid_admin>_<loginkey_admin>|<expira_ts>". Devuelve null si el formato
 * es invalido o si el uid embebido no es el de la cuenta administradora
 * — nunca confia en el contenido crudo de la cookie.
 */
function op_modo_espejo_parsear_retorno($valor)
{
    if (empty($valor) || strpos($valor, '|') === false) {
        return null;
    }

    list($mybbuserOriginal, $expiraTs) = explode('|', $valor, 2);
    list($adminUid, $adminLoginkey) = array_pad(explode('_', $mybbuserOriginal, 2), 2, '');

    if ((int) $adminUid !== OP_MODO_ESPEJO_ADMIN_UID || $adminLoginkey === '') {
        return null;
    }

    return array(
        'mybbuser_original' => $mybbuserOriginal,
        'admin_loginkey' => $adminLoginkey,
        'expira_ts' => (int) $expiraTs,
    );
}

/**
 * Revalida el loginkey embebido en la cookie de retorno contra el valor
 * actual en la base — si la cuenta administradora cambio de contrasena
 * desde que se activo el modo espejo, este chequeo falla y no se restaura
 * una sesion invalida.
 */
function op_modo_espejo_loginkey_admin_valido($adminLoginkey)
{
    global $db;

    $admin = $db->fetch_array($db->simple_select(
        'users', 'loginkey', "uid='" . OP_MODO_ESPEJO_ADMIN_UID . "'", array('limit' => 1)
    ));

    return $admin && hash_equals((string) $admin['loginkey'], (string) $adminLoginkey);
}

/**
 * Hook global_intermediate: arma el aviso del header y fuerza el retorno
 * del lado servidor si ya vencieron las 2 horas, como defensa en
 * profundidad ademas del TTL nativo de las cookies.
 */
function op_modo_espejo_hook_header()
{
    global $mybb, $op_modo_espejo_banner;
    $op_modo_espejo_banner = '';

    $retornoRaw = $mybb->cookies[OP_MODO_ESPEJO_COOKIE_RETORNO] ?? '';
    $activoRaw = $mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO] ?? '';
    if ($retornoRaw === '' || $activoRaw === '') {
        return;
    }

    $retorno = op_modo_espejo_parsear_retorno($retornoRaw);
    if (!$retorno) {
        // Cookie corrupta/manipulada: no confiar, limpiar y salir.
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);
        return;
    }

    if (TIME_NOW > $retorno['expira_ts']) {
        op_modo_espejo_forzar_retorno($retorno);
        return;
    }

    list($objetivoUid, $objetivoUsername) = array_pad(explode('|', $activoRaw, 2), 2, '');
    $usernameEsc = htmlspecialchars((string) $objetivoUsername, ENT_QUOTES, 'UTF-8');
    $postKey = generate_post_check();

    $op_modo_espejo_banner = '<div id="modo_espejo_aviso" style="text-align:center;width:1100px;margin:auto;'
        . 'background-color:#7a1f1f;color:#fff;padding:6px 0;box-shadow:0px 1px 2px black;">'
        . 'Modo espejo activo &mdash; Estas viendo el foro como <strong>' . $usernameEsc . '</strong>. '
        . '<form method="post" action="' . $mybb->settings['bburl'] . '/op/staff/modo_espejo.php" style="display:inline;">'
        . '<input type="hidden" name="my_post_key" value="' . $postKey . '">'
        . '<input type="hidden" name="accion" value="volver">'
        . '<button type="submit">Volver a tu cuenta</button>'
        . '</form></div>';
}

/**
 * Restaura la sesion de la cuenta administradora para el PROXIMO request
 * (este request ya cargo $mybb->user como el objetivo antes de que
 * corriera este hook). Si el loginkey de la cuenta administradora ya no
 * es valido, no deja al usuario logueado como el objetivo.
 */
function op_modo_espejo_forzar_retorno($retorno)
{
    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);

    if (op_modo_espejo_loginkey_admin_valido($retorno['admin_loginkey'])) {
        my_setcookie('mybbuser', $retorno['mybbuser_original'], 0, true);
    } else {
        my_unsetcookie('mybbuser'); // sesion de origen ya no es valida
    }
}

/**
 * Hook member_logout_end: el logout nativo de MyBB limpia "mybbuser" pero
 * no sabe nada de las cookies propias de esta feature — se limpian aca
 * para no dejar cookies huerfanas si se cierra sesion sin usar "Volver".
 */
function op_modo_espejo_hook_logout()
{
    global $mybb;

    if (!empty($mybb->cookies[OP_MODO_ESPEJO_COOKIE_RETORNO]) || !empty($mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO])) {
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);
    }
}
