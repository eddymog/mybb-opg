<?php

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_buscar.php');

require_once "./../global.php";
require_once "./functions/op_functions.php";
$uid        = $mybb->user['uid'];
$g_is_staff = is_staff($uid) ? '1' : '';
$post_code  = generate_post_check();

// ─── Whitelist de valores estáticos ──────────────────────────────────────────
$clases_validas = ['Activa', 'Pasiva', 'Mantenida', 'Conjunta'];
$tipos_validos  = ['Ofensiva', 'Defensiva', 'Ambiental', 'Elusiva', 'Utilidad'];

// ─── Filtros de búsqueda ──────────────────────────────────────────────────────
$f_nombre = $db->escape_string(trim($mybb->get_input('nombre')));

$f_clases = isset($_GET['clase']) && is_array($_GET['clase'])
              ? array_values(array_intersect(array_map('strval', $_GET['clase']), $clases_validas))
              : [];

$f_tipos  = isset($_GET['tipo']) && is_array($_GET['tipo'])
              ? array_values(array_intersect(array_map('strval', $_GET['tipo']), $tipos_validos))
              : [];

$f_estilos_raw = isset($_GET['estilo']) && is_array($_GET['estilo'])
              ? array_filter(array_map('strval', $_GET['estilo']))
              : [];

$f_tiers_raw = isset($_GET['tier']) && is_array($_GET['tier'])
              ? array_filter(array_map('strval', $_GET['tier']))
              : [];

$f_danos = isset($_GET['dano']) && is_array($_GET['dano'])
              ? array_filter(array_map('strval', $_GET['dano']))
              : [];

// Tipos de daño reconocidos (validación de whitelist)
$danos_validos = [
    'contundente' => 'Daño contundente',
    'cortante'    => 'Daño cortante',
    'perforante'  => 'Daño perforante',
    'sonico'      => 'Daño sónico',
    'espiritual'  => 'Daño espiritual',
    'fuego'       => 'Daño de Fuego',
    'viento'      => 'Daño de Viento',
    'agua'        => 'Daño de Agua',
    'rayo'        => 'Daño de Rayo',
    'hielo'       => 'Daño de Hielo',
    'verdadero'   => 'Daño Verdadero',
    'mitigado'    => 'Daño Mitigado',
];
$f_danos = array_intersect_key($danos_validos, array_flip($f_danos));

// ─── Opciones de los selects (valores reales de la BD) ───────────────────────
$opt_clases  = [];
$opt_estilos = [];
$opt_tipos   = [];
$opt_tiers   = [];

$q = $db->query("SELECT DISTINCT clase  FROM mybb_op_tecnicas WHERE clase  != '' ORDER BY clase");
while ($r = $db->fetch_array($q)) { $opt_clases[]  = $r['clase']; }

$q = $db->query("SELECT DISTINCT estilo FROM mybb_op_tecnicas WHERE estilo != '' ORDER BY estilo");
while ($r = $db->fetch_array($q)) { $opt_estilos[] = $r['estilo']; }

$q = $db->query("SELECT DISTINCT tipo  FROM mybb_op_tecnicas WHERE tipo   != '' ORDER BY tipo");
while ($r = $db->fetch_array($q)) { $opt_tipos[]   = $r['tipo']; }

$q = $db->query("SELECT DISTINCT tier  FROM mybb_op_tecnicas WHERE tier   != '' ORDER BY CAST(tier AS UNSIGNED)");
while ($r = $db->fetch_array($q)) { $opt_tiers[]   = $r['tier']; }

// Validar estilos y tiers contra los valores reales de la BD
$f_estilos = array_values(array_intersect($f_estilos_raw, $opt_estilos));
$f_tiers   = array_values(array_intersect($f_tiers_raw,   $opt_tiers));

// ─── Construcción dinámica del WHERE ─────────────────────────────────────────
$hay_busqueda = ($f_nombre !== '' || !empty($f_clases) || !empty($f_tipos)
                 || !empty($f_estilos) || !empty($f_tiers) || !empty($f_danos));

$resultados       = [];
$total_resultados = 0;

if ($hay_busqueda) {
    $where = ['1=1'];

    if ($f_nombre !== '') {
        $where[] = "LOWER(nombre) LIKE LOWER('%$f_nombre%')";
    }
    if (!empty($f_clases)) {
        $quoted  = implode(',', array_map(function($v) { return "'" . $v . "'"; }, $f_clases));
        $where[] = "clase IN ($quoted)";
    }
    if (!empty($f_tipos)) {
        $parts   = array_map(function($v) { return "LOWER(tipo) = LOWER('" . $v . "')"; }, $f_tipos);
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if (!empty($f_estilos)) {
        $parts = [];
        foreach ($f_estilos as $e) {
            $esc     = $db->escape_string($e);
            $parts[] = "(LOWER(estilo) = LOWER('$esc') OR LOWER(rama) = LOWER('$esc'))";
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if (!empty($f_tiers)) {
        $quoted  = implode(',', array_map(function($v) { return "'" . $v . "'"; }, $f_tiers));
        $where[] = "tier IN ($quoted)";
    }
    foreach ($f_danos as $tag) {
        $tag_escaped = $db->escape_string($tag);
        $where[]     = "LOWER(efectos) LIKE LOWER('%$tag_escaped%')";
    }

    $where_sql = implode(' AND ', $where);

    $q = $db->query("
        SELECT tid, nombre, estilo, rama, clase, tipo, tier, efectos, descripcion,
               energia, energia_turno, haki, haki_turno, enfriamiento, requisitos
        FROM mybb_op_tecnicas
        WHERE $where_sql
        ORDER BY estilo, rama, tier, nombre
        LIMIT 200
    ");

    while ($r = $db->fetch_array($q)) {
        $resultados[] = $r;
    }
    $total_resultados = count($resultados);
}

// ─── Serializar para el template ─────────────────────────────────────────────
$opt_estilos_json         = json_encode($opt_estilos, JSON_UNESCAPED_UNICODE);
$opt_tiers_json           = json_encode($opt_tiers,   JSON_UNESCAPED_UNICODE);
$resultados_json          = json_encode($resultados,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$danos_seleccionados_json = json_encode(array_keys($f_danos), JSON_UNESCAPED_UNICODE);
$filtro_clases_json       = json_encode($f_clases,   JSON_UNESCAPED_UNICODE);
$filtro_tipos_json        = json_encode($f_tipos,    JSON_UNESCAPED_UNICODE);
$filtro_estilos_json      = json_encode($f_estilos,  JSON_UNESCAPED_UNICODE);
$filtro_tiers_json        = json_encode($f_tiers,    JSON_UNESCAPED_UNICODE);
$fileVersion              = rand();

// ─── Render ───────────────────────────────────────────────────────────────────
eval("\$page = \"".$templates->get("op_tecnicas_buscar")."\";");
output_page($page);
