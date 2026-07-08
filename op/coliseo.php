<?php

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'coliseo.php');

require_once "./../global.php";
require_once "./functions/op_functions.php";

// ─── Auth ────────────────────────────────────────────────────────────────────
$user_uid = $mybb->user['uid'];

if ($user_uid == 0) {
    redirect($mybb->settings['bburl'] . '/member.php?action=login');
    exit;
}

$pestana = $mybb->get_input('pestana');
$fileVersion = rand();

// ─── Clasificación — top luchadores por victorias ─────────────────────────────
$clasificacion = [];
// TODO: query cuando exista mybb_op_coliseo_combates
// $q = $db->query("
//     SELECT uid, COUNT(*) AS victorias
//     FROM mybb_op_coliseo_combates
//     WHERE ganador_uid = uid
//     GROUP BY uid
//     ORDER BY victorias DESC
//     LIMIT 20
// ");
// while ($r = $db->fetch_array($q)) { $clasificacion[] = $r; }
$clasificacion_json = json_encode($clasificacion);

// ─── Torneos activos ──────────────────────────────────────────────────────────
$torneos = [];
// TODO: query cuando exista mybb_op_coliseo_torneos
// $q = $db->query("SELECT * FROM mybb_op_coliseo_torneos WHERE estado != 'finalizado' ORDER BY fecha_inicio DESC");
// while ($r = $db->fetch_array($q)) { $torneos[] = $r; }
$torneos_json = json_encode($torneos);

// ─── Historial de combates ────────────────────────────────────────────────────
$historial = [];
// TODO: query cuando exista mybb_op_coliseo_combates
// $q = $db->query("SELECT * FROM mybb_op_coliseo_combates ORDER BY fecha DESC LIMIT 50");
// while ($r = $db->fetch_array($q)) { $historial[] = $r; }
$historial_json = json_encode($historial);

// ─── Render templates ─────────────────────────────────────────────────────────
eval("\$op_coliseo_css           = \"".$templates->get("op_coliseo_css")."\";");
eval("\$op_coliseo_portada       = \"".$templates->get("op_coliseo_portada")."\";");
eval("\$op_coliseo_clasificacion = \"".$templates->get("op_coliseo_clasificacion")."\";");
eval("\$op_coliseo_torneos       = \"".$templates->get("op_coliseo_torneos")."\";");
eval("\$op_coliseo_historial     = \"".$templates->get("op_coliseo_historial")."\";");
eval("\$op_coliseo_reglamento    = \"".$templates->get("op_coliseo_reglamento")."\";");

eval("\$page = \"".$templates->get("op_coliseo")."\";");
output_page($page);
