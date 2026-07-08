<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'banda.php');
$templatelist = 'op_banda,op_banda_portada,op_banda_crear';
require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid      = (int)$mybb->user['uid'];
$banda_id = (int)$mybb->get_input('id');

$faccion_colors = [
    'Pirata'         => ['#ff0000','#ff0000','#ff0000','linear-gradient(42deg, #950000 20%, #ff0000 50%, #950000 80%)','#f63030','#fd0202'],
    'Marina'         => ['#00bafc','#0039ed','#00bafc','linear-gradient(42deg, #002282 20%, #00b8fa 50%, #002282 80%)','#0055bb','#0038c7'],
    'CipherPol'      => ['#08002c','#6534aa','#08002c','linear-gradient(42deg, #1b1424 20%, #9577ba 50%, #1b1424 80%)','#ac30d9','#861fac'],
    'Cazadores'      => ['#00c200','#00ab00','#00c200','linear-gradient(42deg, #0f2313 20%, #46af70 50%, #0f2313 80%)','#00d506','#007400'],
    'Revolucionario' => ['#be9d6f','#7d6452','#be9d6f','linear-gradient(42deg, #4e3e2c 20%, #e9c696 50%, #4e3e2c 80%)','#9d8771','#937e67'],
    'Civil'          => ['#ff0283','#c6005c','#ff0283','linear-gradient(42deg, #950044 20%, #f40277 50%, #950044 80%)','#e0428d','#c30041'],
];

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
        = $faccion_colors[$faccion] ?? $faccion_colors['Civil'];

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
    = $faccion_colors[$faccion] ?? $faccion_colors['Civil'];

eval("\$op_banda_portada = \"".$templates->get("op_banda_portada")."\";");
eval("\$page             = \"".$templates->get("op_banda")."\";");
output_page($page);
