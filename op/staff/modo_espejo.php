<?php
/**
 * Staff - Modo espejo.
 *
 * Exclusivo de FID 315: permite reflejar la sesion de cualquier usuario
 * para debug, sin tocar su contrasena. Ver docs/100_Requirements_ModoEspejo.md,
 * docs/200_DesignPlan_ModoEspejo.md y docs/300_ImplementationPlan_ModoEspejo.md.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'modo_espejo.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/op_modo_espejo.php';

global $templates, $mybb, $db;

$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

// "volver" se procesa ANTES del gate de uid===315: durante el modo espejo
// el uid activo ya no es 315, es el del usuario objetivo.
if ($mybb->request_method == 'post' && $accion === 'volver') {
    verify_post_check($mybb->get_input('my_post_key'));

    $retorno = op_modo_espejo_parsear_retorno($mybb->cookies[OP_MODO_ESPEJO_COOKIE_RETORNO] ?? '');
    $objetivoUidQueSeDejaba = (int) $mybb->user['uid'];

    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);

    if ($retorno && op_modo_espejo_loginkey_admin_valido($retorno['admin_loginkey'])) {
        my_setcookie('mybbuser', $retorno['mybbuser_original'], 0, true);
        log_audit(
            OP_MODO_ESPEJO_ADMIN_UID,
            'FID ' . OP_MODO_ESPEJO_ADMIN_UID,
            '[ModoEspejo][Finalizado]',
            "Se dejo de reflejar FID {$objetivoUidQueSeDejaba}."
        );
    } else {
        my_unsetcookie('mybbuser');
    }

    header('Location: ' . $mybb->settings['bburl'] . '/index.php');
    exit;
}

if ((int) $mybb->user['uid'] !== OP_MODO_ESPEJO_ADMIN_UID) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

function me_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($mybb->request_method == 'post' && $accion === 'activar') {
    verify_post_check($mybb->get_input('my_post_key'));

    $objetivoFid = (int) $mybb->get_input('objetivo_fid', MyBB::INPUT_INT);
    $error = '';
    if ($objetivoFid <= 0) {
        $error = 'Falta el FID objetivo.';
    } elseif ($objetivoFid === OP_MODO_ESPEJO_ADMIN_UID) {
        $error = 'No se puede activar el modo espejo sobre la propia cuenta.';
    }

    $objetivo = null;
    if ($error === '') {
        $objetivo = $db->fetch_array($db->simple_select(
            'users', 'uid,username,loginkey', "uid='{$objetivoFid}'", array('limit' => 1)
        ));
        if (!$objetivo) {
            $error = 'Ese FID no existe.';
        }
    }

    if ($error === '') {
        $expiraEn = TIME_NOW + OP_MODO_ESPEJO_TTL;
        $retornoValor = $mybb->cookies['mybbuser'] . '|' . $expiraEn;

        my_setcookie(OP_MODO_ESPEJO_COOKIE_RETORNO, $retornoValor, OP_MODO_ESPEJO_TTL, true);
        my_setcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO, $objetivo['uid'] . '|' . $objetivo['username'] . '|' . $expiraEn, OP_MODO_ESPEJO_TTL, true);
        my_setcookie('mybbuser', $objetivo['uid'] . '_' . $objetivo['loginkey'], OP_MODO_ESPEJO_TTL, true);

        log_audit(
            OP_MODO_ESPEJO_ADMIN_UID,
            $mybb->user['username'],
            '[ModoEspejo][Activado]',
            "Objetivo: FID {$objetivo['uid']} ({$objetivo['username']}). Expira: " . my_date('d/m/Y H:i', $expiraEn) . '.'
        );

        header('Location: ' . $mybb->settings['bburl'] . '/index.php');
        exit;
    }
}

$ya_activo = !empty($mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO]);
$me_objetivo_actual = '';
if ($ya_activo) {
    list(, $me_objetivo_actual) = array_pad(explode('|', $mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO], 2), 2, '');
}
$me_objetivo_actual_esc = me_html($me_objetivo_actual);
$me_error_esc = isset($error) && $error !== '' ? me_html($error) : '';
$post_key = generate_post_check();

eval("\$page = \"".$templates->get("staff_modo_espejo")."\";");
output_page($page);
