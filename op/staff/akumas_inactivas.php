<?php
/**
 * Staff - Akumas inactivas
 *
 * Reporte de solo lectura: no toma ningún input de la request, así que no hay
 * SQL injection ni CSRF que cerrar acá (a diferencia del resto de /op/staff/).
 * Lo que sí tenía era XSS de verdad: nombre_usuario/nombre/subnombre viajan
 * en el JSON y el template los pintaba con jQuery .append() sin escapar —
 * un nombre de personaje o de akuma con HTML se ejecutaba para cualquier
 * staff que abriera esta página. El escape ahora lo hace el propio JS (esc()
 * antes de cada interpolación), ver staff_akumas_inactivas.html.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'akumas_inactivas.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

$last_two_months = time() - (61 * 24 * 3600);

$query_akumas = $db->query("
    SELECT
        akumas.akuma_id,
        akumas.nombre,
        akumas.subnombre,
        akumas.uid,
        fichas.nombre as nombre_usuario,
        (
            SELECT MAX(p.dateline)
            FROM mybb_posts AS p
            INNER JOIN mybb_threads AS t ON p.tid = t.tid
            INNER JOIN mybb_forums AS f ON t.fid = f.fid
            WHERE p.uid = fichas.fid
            AND (f.parentlist LIKE '10,%' OR f.parentlist LIKE '%246,%')
        ) AS last_post
    FROM mybb_op_akumas AS akumas
    INNER JOIN mybb_op_fichas AS fichas ON akumas.subnombre = fichas.akuma_subnombre
    WHERE akumas.uid != 1
    AND akumas.es_npc = 0
    AND akumas.uid != '0'
    AND akumas.uid NOT LIKE '%NPC%'
    ORDER BY akumas.akuma_id ASC; ");

$akumas = array();
$akumas_expire_15 = array();
$akumas_expire_30 = array();
$akumas_inactive = array();
$threshold = $last_two_months;
while ($q = $db->fetch_array($query_akumas)) {
    $q['last_post'] = intval($q['last_post']);
    if ($q['last_post'] <= $threshold) {
        array_push($akumas_inactive, $q);
    } else if ($q['last_post'] <= ($threshold + (15 * 24 * 3600))) {
        array_push($akumas_expire_15, $q);
    } else if ($q['last_post'] <= ($threshold + (30 * 24 * 3600))) {
        array_push($akumas_expire_30, $q);
    }
    array_push($akumas, $q);
}
$akumas_json = json_encode($akumas_inactive, JSON_UNESCAPED_UNICODE);
$akumas_expire_15_json = json_encode($akumas_expire_15, JSON_UNESCAPED_UNICODE);
$akumas_expire_30_json = json_encode($akumas_expire_30, JSON_UNESCAPED_UNICODE);

$query_akumas_asignar = $db->query("
    SELECT
    akumas.akuma_id, akumas.nombre, akumas.subnombre, fichas.fid, fichas.nombre as nombre_usuario
    FROM mybb_op_akumas AS akumas
    INNER JOIN mybb_op_fichas AS fichas ON akumas.uid = fichas.fid
    WHERE fichas.akuma = ''; ");
$akumas_asignar = array();
while ($q = $db->fetch_array($query_akumas_asignar)) { array_push($akumas_asignar, $q); }
$akumas_asignar_json = json_encode($akumas_asignar, JSON_UNESCAPED_UNICODE);

eval("\$page = \"".$templates->get("staff_akumas_inactivas")."\";");
output_page($page);
