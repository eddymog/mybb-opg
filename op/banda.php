<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'banda.php');
$templatelist = 'op_banda,op_banda_portada,op_banda_crear';
require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid      = (int)$mybb->user['uid'];
$banda_id = (int)$mybb->get_input('id');

// ── No ID: show creator or redirect to existing band ─────────────────────────
if (!$banda_id) {
    if (!$uid) {
        redirect($mybb->settings['bburl']);
        exit;
    }

    $q_propia = $db->simple_select('op_bandas', 'id', "owner_uid='$uid'");
    $banda_propia = $db->fetch_array($q_propia);
    if ($banda_propia) {
        redirect($mybb->settings['bburl'] . '/op/banda.php?id=' . (int)$banda_propia['id']);
        exit;
    }

    $q_miembro = $db->simple_select('op_banda_miembros', 'banda_id', "miembro_uid='$uid'");
    $en_banda  = $db->fetch_array($q_miembro);
    if ($en_banda) {
        redirect($mybb->settings['bburl'] . '/op/banda.php?id=' . (int)$en_banda['banda_id']);
        exit;
    }

    $q_ficha_user = $db->simple_select('op_fichas', 'faccion', "fid='$uid'");
    $ficha_user   = $db->fetch_array($q_ficha_user);
    $faccion      = $ficha_user ? ($ficha_user['faccion'] ?: 'Civil') : 'Civil';
    [$faccionColor, $romboColor, $borderTagColor, $rangoColor, $borderColor, $borderPillColor]
        = op_faccion_colors($faccion);

    $post_code = generate_post_check();
    eval("\$page = \"".$templates->get("op_banda_crear")."\";");
    output_page($page);
    exit;
}

// ── Band view ─────────────────────────────────────────────────────────────────
$q_banda = $db->simple_select('op_bandas', '*', "id='$banda_id'");
$banda   = $db->fetch_array($q_banda);
if (!$banda) {
    redirect($mybb->settings['bburl']);
    exit;
}

$owner_uid = (int)$banda['owner_uid'];

$q_ficha       = $db->simple_select('op_fichas', 'nombre, faccion, avatar1', "fid='$owner_uid'");
$ficha_capitan = $db->fetch_array($q_ficha);

$faccion        = $ficha_capitan ? ($ficha_capitan['faccion'] ?: 'Civil') : 'Civil';
$nombre_capitan = $ficha_capitan ? $ficha_capitan['nombre']  : '';
$avatar_capitan = $ficha_capitan ? $ficha_capitan['avatar1'] : '';

[$faccionColor, $romboColor, $borderTagColor, $rangoColor, $borderColor, $borderPillColor]
    = op_faccion_colors($faccion);

eval("\$op_banda_portada = \"".$templates->get("op_banda_portada")."\";");
eval("\$page             = \"".$templates->get("op_banda")."\";");
output_page($page);
