<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'barco.php');
$templatelist = 'op_barco_seleccion,op_barco_interior';

require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid = (int)$mybb->user['uid'];
if (!$uid) {
    redirect('../member.php?action=login');
    exit;
}

$post_code = generate_post_check();

function _barco_puede_acceder($uid, $barco_id, $owner_uid) {
    global $db;
    if ((int)$uid === (int)$owner_uid) return true;
    $q = $db->simple_select('op_barco_tripulacion', 'id',
        "barco_id='" . $db->escape_string($barco_id) . "' AND owner_uid='" . (int)$owner_uid . "' AND miembro_uid='" . (int)$uid . "'");
    return (bool)$db->fetch_array($q);
}

$barco_id_raw    = trim($mybb->get_input('barco_id'));
$owner_uid_param = (int)$mybb->get_input('owner');

if ($barco_id_raw && $owner_uid_param) {

    $barco_id_safe  = $db->escape_string($barco_id_raw);
    $owner_uid_safe = (int)$owner_uid_param;

    if (!_barco_puede_acceder($uid, $barco_id_safe, $owner_uid_safe)) {
        error("No tienes acceso a este barco.");
    }

    $q = $db->simple_select('op_inventario', 'id', "objeto_id='" . $barco_id_safe . "' AND uid='" . $owner_uid_safe . "'");
    if (!$db->fetch_array($q)) {
        error("El barco indicado no existe en el inventario.");
    }

    $q = $db->query("
        SELECT o.objeto_id, o.nombre, o.descripcion, o.subcategoria,
               COALESCE(NULLIF(i.imagen, ''), o.imagen, '') AS imagen,
               COALESCE(o.tier, 0)               AS tier,
               COALESCE(b.espacios_mejora, 0)    AS espacios_mejora,
               COALESCE(b.vitalidad, 0)          AS vitalidad,
               COALESCE(b.velocidad, 0)          AS velocidad,
               COALESCE(b.espacios, 0)           AS espacios_carga,
               COALESCE(b.resistencia, 0)        AS resistencia,
               COALESCE(b.ruputura, 0)           AS ruputura,
               COALESCE(b.mejora_tripulacion, 0) AS mejora_tripulacion,
               COALESCE(b.mejora_vitalidad, 0)   AS mejora_vitalidad,
               COALESCE(b.mejora_resistencia, 0) AS mejora_resistencia,
               COALESCE(b.mejora_ruptura, 0)     AS mejora_ruptura
        FROM mybb_op_objetos o
        LEFT JOIN mybb_op_barcos b ON b.barco_id COLLATE utf8_unicode_ci = o.objeto_id
        LEFT JOIN mybb_op_inventario i ON i.objeto_id = o.objeto_id AND i.uid = '$owner_uid_safe'
        WHERE o.objeto_id = '" . $barco_id_safe . "'
        LIMIT 1
    ");
    $barco_data = $db->fetch_array($q);
    if (!$barco_data) {
        error("Datos del barco no encontrados.");
    }
    if (strtolower($barco_data['subcategoria']) !== 'barcos') {
        error("El objeto indicado no es un barco.");
    }

    $max_salas = (int)$barco_data['espacios_mejora'];
    if (!$max_salas && $barco_data['descripcion']) {
        if (preg_match('/Espacios\s+de\s+Mejoras\s*:\s*(\d+)/i', $barco_data['descripcion'], $m)) {
            $max_salas = (int)$m[1];
        }
    }

    $barco_cofre = array();
    $q = $db->query("
        SELECT c.id, c.objeto_id, c.cantidad, c.added_by, c.apodo, c.imagen,
               COALESCE(o.nombre, c.objeto_id) AS obj_nombre,
               COALESCE(o.imagen, '') AS obj_imagen
        FROM mybb_op_barco_cofre c
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = c.objeto_id
        WHERE c.barco_id = '" . $barco_id_safe . "' AND c.owner_uid = '" . $owner_uid_safe . "'
        ORDER BY c.id ASC
    ");
    while ($r = $db->fetch_array($q)) {
        $barco_cofre[] = $r;
    }

    $q = $db->simple_select('op_barco_estado', 'berries, owner_rangos, owner_ausente', "barco_id='" . $barco_id_safe . "' AND owner_uid='" . $owner_uid_safe . "'");
    $estado        = $db->fetch_array($q);
    $barco_berries = $estado ? (int)$estado['berries'] : 0;
    $owner_rangos  = $estado && isset($estado['owner_rangos']) ? $estado['owner_rangos'] : '';
    $owner_ausente = $estado && !empty($estado['owner_ausente']) ? 1 : 0;

    $barco_salas = new stdClass();
    $mejora_usada = 0;
    $TIPOS_SALA_MEJORA = [
        'mapa'=>1,'cocina'=>1,'taller'=>2,'enfermeria'=>1,'quirofano'=>1,
        'archivos'=>2,'invernadero'=>1,'forja'=>1,'corral'=>1,
    ];
    $q = $db->query("SELECT * FROM mybb_op_barco_salas WHERE barco_id='" . $barco_id_safe . "' AND owner_uid='" . $owner_uid_safe . "' ORDER BY slot ASC");
    while ($r = $db->fetch_array($q)) {
        $slot = (int)$r['slot'];
        $barco_salas->$slot = $r;
        if (isset($TIPOS_SALA_MEJORA[$r['tipo']])) {
            $mejora_usada += $TIPOS_SALA_MEJORA[$r['tipo']];
        }
    }

    $mi_inventario = array();
    $q = $db->query("
        SELECT i.objeto_id, i.cantidad, i.apodo, i.imagen,
               COALESCE(o.nombre, i.objeto_id) AS obj_nombre,
               COALESCE(o.imagen, '') AS obj_imagen,
               COALESCE(o.comerciable, 0) AS comerciable
        FROM mybb_op_inventario i
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = i.objeto_id
        WHERE i.uid = '" . $uid . "' AND LOWER(COALESCE(o.subcategoria, '')) != 'barcos'
        ORDER BY obj_nombre ASC
        LIMIT 300
    ");
    while ($r = $db->fetch_array($q)) {
        $mi_inventario[] = $r;
    }

    // Tripulación
    $barco_tripulacion = array();
    $q = $db->query("
        SELECT t.id, t.miembro_uid, COALESCE(t.rango, '') AS rango, COALESCE(u.username, t.miembro_uid) AS username,
               COALESCE(f.altura, 0) AS altura, COALESCE(t.ausente, 0) AS ausente
        FROM mybb_op_barco_tripulacion t
        LEFT JOIN mybb_users u ON u.uid = t.miembro_uid
        LEFT JOIN mybb_op_fichas f ON f.fid = t.miembro_uid
        WHERE t.barco_id='" . $barco_id_safe . "' AND t.owner_uid='" . $owner_uid_safe . "'
        ORDER BY t.id ASC
    ");
    while ($r = $db->fetch_array($q)) {
        $r['ausente'] = (int)$r['ausente'];
        $r['altura']  = (int)$r['altura'];
        $barco_tripulacion[] = $r;
    }
    $q_owner = $db->simple_select('users', 'username', "uid='$owner_uid_safe'");
    $owner_row = $db->fetch_array($q_owner);
    $owner_username_js = $owner_row ? $owner_row['username'] : 'UID ' . $owner_uid_safe;
    $q_alt = $db->simple_select('op_fichas', 'altura', "fid='$owner_uid_safe'");
    $alt_row = $db->fetch_array($q_alt);
    $owner_altura = $alt_row ? max(0, (int)$alt_row['altura']) : 0;

    // NPCs y Mascotas en el barco
    $barco_npcs = array();
    $q = $db->query("
        SELECT n.id, n.ref_id, n.tipo, n.rol, COALESCE(np.nombre, n.ref_id) AS nombre,
               np.altura AS altura_raw, COALESCE(n.ausente, 0) AS ausente
        FROM mybb_op_barco_npcs n
        LEFT JOIN mybb_op_npcs np ON np.npc_id = n.ref_id
        WHERE n.barco_id='" . $barco_id_safe . "' AND n.owner_uid='" . $owner_uid_safe . "'
        ORDER BY n.tipo ASC, np.nombre ASC
    ");
    while ($r = $db->fetch_array($q)) {
        $r['altura']  = max(0, intval((string)$r['altura_raw']));
        $r['ausente'] = (int)$r['ausente'];
        unset($r['altura_raw']);
        $barco_npcs[] = $r;
    }

    $barco_cofre_json       = json_encode($barco_cofre,       JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $barco_salas_json       = json_encode($barco_salas,       JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $mi_inventario_json     = json_encode($mi_inventario,     JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $barco_tripulacion_json = json_encode($barco_tripulacion, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $owner_username_json    = json_encode($owner_username_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $owner_rangos_json      = json_encode($owner_rangos,      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $barco_npcs_json        = json_encode($barco_npcs,        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $viewer_uid_js          = $uid;
    $barco_nombre       = htmlspecialchars_uni($barco_data['nombre']);
    $barco_imagen       = htmlspecialchars_uni($barco_data['imagen']);
    $barco_id_js        = htmlspecialchars_uni($barco_id_safe);
    $owner_uid_js       = (int)$owner_uid_safe;
    $max_salas_js       = (int)$max_salas;
    $mejora_usada_js    = (int)$mejora_usada;
    $barco_berries_js   = (int)$barco_berries;
    $barco_velocidad    = (int)$barco_data['velocidad'];
    $barco_vitalidad    = (int)$barco_data['vitalidad'];
    $barco_resistencia  = (int)$barco_data['resistencia'];
    $barco_espacios_c   = (int)$barco_data['espacios_carga'];
    $tier_barco_js      = (int)$barco_data['tier'];
    $ruputura_js        = (int)$barco_data['ruputura'];
    $mejoras_aplicadas_js = json_encode([
        'tripulacion' => (bool)$barco_data['mejora_tripulacion'],
        'vitalidad'   => (bool)$barco_data['mejora_vitalidad'],
        'resistencia' => (bool)$barco_data['mejora_resistencia'],
        'ruptura'     => (bool)$barco_data['mejora_ruptura'],
    ], JSON_UNESCAPED_UNICODE);
    $espacios_trip_js   = $barco_espacios_c;
    $owner_altura_js    = isset($owner_altura) ? (int)$owner_altura : 0;
    $owner_ausente_js   = isset($owner_ausente) ? (int)$owner_ausente : 0;
    $is_owner_js        = ((int)$uid === $owner_uid_safe) ? '1' : '';
    $is_carpintero_js   = ($uid === 850) ? '1' : '';
    if (!$is_carpintero_js && (int)$uid === $owner_uid_safe) {
        // Capitán: solo necesita oficio Carpintero
        $q_carp = $db->query("
            SELECT 1 FROM mybb_op_fichas f
            WHERE f.fid = '$uid'
              AND (f.oficio1 = 'Carpintero' OR f.oficio2 = 'Carpintero')
            LIMIT 1
        ");
        if ($db->fetch_array($q_carp)) { $is_carpintero_js = '1'; }
    } elseif (!$is_carpintero_js) {
        // Tripulante: necesita rango Carpintero en este barco + oficio Carpintero
        $q_carp = $db->query("
            SELECT 1 FROM mybb_op_barco_tripulacion t
            JOIN mybb_op_fichas f ON f.fid = t.miembro_uid
            WHERE t.barco_id = '$barco_id_safe' AND t.owner_uid = '$owner_uid_safe'
              AND t.miembro_uid = '$uid'
              AND FIND_IN_SET('Carpintero', t.rango) > 0
              AND (f.oficio1 = 'Carpintero' OR f.oficio2 = 'Carpintero')
            LIMIT 1
        ");
        if ($db->fetch_array($q_carp)) { $is_carpintero_js = '1'; }
    }
    // NPC con rol Carpintero y oficio Carpintero perteneciente al usuario actual
    if (!$is_carpintero_js) {
        $q_npc_carp = $db->query("
            SELECT 1 FROM mybb_op_barco_npcs bn
            JOIN mybb_op_npcs n ON n.npc_id = bn.ref_id
            WHERE bn.barco_id='$barco_id_safe' AND bn.owner_uid='$owner_uid_safe'
              AND bn.tipo='npc' AND bn.rol='Carpintero'
              AND bn.ref_id LIKE '" . (int)$uid . "-%'
              AND n.oficios LIKE '%Carpintero%'
            LIMIT 1
        ");
        if ($db->fetch_array($q_npc_carp)) { $is_carpintero_js = '1'; }
    }

    // Check estricto para mejoras: oficio Carpintero + rol Carpintero en el barco
    $is_carpintero_mejora_js = ($uid === 850) ? '1' : '';
    if (!$is_carpintero_mejora_js) {
        $q_ficha = $db->query("SELECT 1 FROM mybb_op_fichas WHERE fid='$uid' AND (oficio1='Carpintero' OR oficio2='Carpintero') LIMIT 1");
        if ($db->fetch_array($q_ficha)) {
            if ((int)$uid === $owner_uid_safe) {
                $q_rango = $db->simple_select('op_barco_estado', 'owner_rangos', "barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe'");
                $rango_row = $db->fetch_array($q_rango);
                if ($rango_row && in_array('Carpintero', array_map('trim', explode(',', $rango_row['owner_rangos'])))) {
                    $is_carpintero_mejora_js = '1';
                }
            } else {
                $q_trip = $db->query("SELECT 1 FROM mybb_op_barco_tripulacion WHERE barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe' AND miembro_uid='$uid' AND FIND_IN_SET('Carpintero', rango) > 0 LIMIT 1");
                if ($db->fetch_array($q_trip)) { $is_carpintero_mejora_js = '1'; }
            }
        }
    }
    if (!$is_carpintero_mejora_js) {
        $q_npc_m = $db->query("
            SELECT 1 FROM mybb_op_barco_npcs bn
            JOIN mybb_op_npcs n ON n.npc_id = bn.ref_id
            WHERE bn.barco_id='$barco_id_safe' AND bn.owner_uid='$owner_uid_safe'
              AND bn.tipo='npc' AND bn.rol='Carpintero'
              AND bn.ref_id LIKE '" . (int)$uid . "-%'
              AND n.oficios LIKE '%Carpintero%'
            LIMIT 1
        ");
        if ($db->fetch_array($q_npc_m)) { $is_carpintero_mejora_js = '1'; }
    }

    // ── Navegante check ──
    $is_navegante_js = '';
    if ((int)$uid === $owner_uid_safe) {
        if ($owner_rangos) {
            $r_parts = array_filter(array_map('trim', explode(',', $owner_rangos)));
            if (in_array('Navegante', $r_parts)) { $is_navegante_js = '1'; }
        }
    } else {
        $q_nav = $db->query("
            SELECT 1 FROM mybb_op_barco_tripulacion t
            WHERE t.barco_id = '$barco_id_safe' AND t.owner_uid = '$owner_uid_safe'
              AND t.miembro_uid = '$uid'
              AND FIND_IN_SET('Navegante', t.rango) > 0
            LIMIT 1
        ");
        if ($db->fetch_array($q_nav)) { $is_navegante_js = '1'; }
    }
    // NPC con rol Navegante perteneciente al usuario actual
    if (!$is_navegante_js) {
        $q_npc_nav = $db->query("
            SELECT 1 FROM mybb_op_barco_npcs
            WHERE barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe'
              AND tipo='npc' AND rol='Navegante'
              AND ref_id LIKE '" . (int)$uid . "-%'
            LIMIT 1
        ");
        if ($db->fetch_array($q_npc_nav)) { $is_navegante_js = '1'; }
    }

    // ── Cofre access: dueño o tripulante con rango Capitan/Vicecapitan/Tesorero ──
    $can_cofre_js = ((int)$uid === $owner_uid_safe) ? '1' : '';
    if (!$can_cofre_js) {
        $q_cofre = $db->query("
            SELECT rango FROM mybb_op_barco_tripulacion
            WHERE barco_id='$barco_id_safe' AND owner_uid='$owner_uid_safe'
              AND miembro_uid='$uid'
            LIMIT 1
        ");
        if ($row_cofre = $db->fetch_array($q_cofre)) {
            $rangos_cofre_ok = array('Capitan', 'Vicecapitan', 'Tesorero');
            foreach (array_map('trim', explode(',', $row_cofre['rango'])) as $r) {
                if (in_array($r, $rangos_cofre_ok, true)) { $can_cofre_js = '1'; break; }
            }
        }
    }

    // ── Crew UIDs for viajes (owner + all crew members) ──
    $viaje_crew_uids = [(int)$owner_uid_safe];
    foreach ($barco_tripulacion as $m) {
        $viaje_crew_uids[] = (int)$m['miembro_uid'];
    }
    $viaje_crew_uids_json = json_encode($viaje_crew_uids);

    // ── Viajes tab button (only for Navegante) ──
    $viajes_tab_html = $is_navegante_js
        ? '<button type="button" class="bai-tab-btn" data-tab="viajes" onclick="baiSwitchTab(\'viajes\')">&#x2388; Viajes</button>'
        : '';

    $fileVersion        = rand();

    $img_inner = $barco_imagen
        ? '<img class="bai-header-img" src="' . $barco_imagen . '" alt="" id="bai-header-img-el" />'
        : '<div class="bai-header-img--ph" id="bai-header-img-el">&#x2693;</div>';
    $edit_btn = ($uid === $owner_uid_safe)
        ? '<button type="button" class="bai-img-edit-btn" onclick="baiAbrirEditarImagen()" title="Cambiar imagen">&#x270F;</button>'
        : '';
    $barco_header_img_html = '<div class="bai-header-img-wrap">' . $img_inner . $edit_btn . '</div>';
    $barco_stat_vitalidad   = $barco_vitalidad   ? '<span class="bai-stat">Vitalidad '   . $barco_vitalidad   . '</span>' : '';
    $barco_stat_velocidad   = $barco_velocidad   ? '<span class="bai-stat">Velocidad '   . $barco_velocidad   . '</span>' : '';
    $barco_stat_resistencia = $barco_resistencia ? '<span class="bai-stat">Resist. '     . $barco_resistencia . '</span>' : '';
    $barco_stat_espacios_c  = $barco_espacios_c  ? '<span class="bai-stat">Carga '       . $barco_espacios_c  . '</span>' : '';
    $barco_stat_mejoras     = $max_salas_js      ? '<span class="bai-stat">Mejoras '     . $max_salas_js      . '</span>' : '';

    // ── Historial de viajes de este barco ──
    $interior_hist_viajes = [];
    $q_ihist = $db->query("
        SELECT v.id, v.uid_viaje, v.mar, v.partida, v.llegada, v.horas,
               v.fecha_salida, v.temporada, v.postViaje, v.dado_naval, v.modificador, v.viajeros,
               u.username AS nav_user
        FROM mybb_op_viajes v
        LEFT JOIN mybb_users u ON u.uid = v.uid_viaje
        WHERE v.barco_id = '$barco_id_safe'
        ORDER BY v.id DESC
        LIMIT 50
    ");
    while ($r = $db->fetch_array($q_ihist)) { $interior_hist_viajes[] = $r; }

    $ihist_uid_map  = [];
    $ihist_all_uids = [];
    foreach ($interior_hist_viajes as $v) {
        if (!empty($v['viajeros'])) {
            foreach (explode(',', $v['viajeros']) as $vid) {
                $vid = (int)trim($vid);
                if ($vid > 0) { $ihist_all_uids[$vid] = true; }
            }
        }
    }
    if (!empty($ihist_all_uids)) {
        $uid_list = implode(',', array_keys($ihist_all_uids));
        $qu = $db->query("SELECT uid, username FROM mybb_users WHERE uid IN ($uid_list)");
        while ($ru = $db->fetch_array($qu)) { $ihist_uid_map[(int)$ru['uid']] = $ru['username']; }
    }

    if (empty($interior_hist_viajes)) {
        $ihist_inner = '<div class="bai-hist-empty">No hay viajes registrados para este barco.</div>';
    } else {
        $ihist_inner = '<div class="bai-hist-list">';
        foreach ($interior_hist_viajes as $v) {
            $ruta  = htmlspecialchars_uni($v['partida']) . ' &rarr; ' . htmlspecialchars_uni($v['llegada']);
            $mar_h = htmlspecialchars_uni($v['mar'] ?? '');
            $nav_h = htmlspecialchars_uni($v['nav_user'] ?? '');
            $fecha = ($v['fecha_salida'] ? 'D&iacute;a ' . (int)$v['fecha_salida'] : '') . ($v['temporada'] ? ' de ' . htmlspecialchars_uni($v['temporada']) : '');
            $horas = (int)($v['horas'] ?? 0);
            $mod   = (int)($v['modificador'] ?? 0);
            $dado  = (int)($v['dado_naval'] ?? 0);
            $post  = htmlspecialchars_uni($v['postViaje'] ?? '');

            $viajeros_names = [];
            if (!empty($v['viajeros'])) {
                foreach (explode(',', $v['viajeros']) as $vid) {
                    $vid = (int)trim($vid);
                    if ($vid > 0 && isset($ihist_uid_map[$vid])) {
                        $viajeros_names[] = htmlspecialchars_uni($ihist_uid_map[$vid]);
                    }
                }
            }

            $ihist_inner .= '<div class="bai-hist-card">';
            $ihist_inner .= '<div class="bai-hist-route">' . $ruta . '</div>';
            $ihist_inner .= '<div class="bai-hist-meta">';
            if ($mar_h) { $ihist_inner .= '<span class="bai-hist-badge bai-hist-badge--mar">' . $mar_h . '</span>'; }
            if ($fecha) { $ihist_inner .= '<span class="bai-hist-badge">' . $fecha . '</span>'; }
            if ($horas) { $ihist_inner .= '<span class="bai-hist-badge">' . $horas . ' h</span>'; }
            if ($mod !== 0) { $ihist_inner .= '<span class="bai-hist-badge">Mod: ' . ($mod > 0 ? '+' : '') . $mod . '</span>'; }
            if ($dado)  { $ihist_inner .= '<span class="bai-hist-badge">D100: ' . $dado . '</span>'; }
            $ihist_inner .= '</div>';
            $all_crew = $nav_h ? array_merge([$nav_h], $viajeros_names) : $viajeros_names;
            if (!empty($all_crew)) {
                $ihist_inner .= '<div class="bai-hist-crew">' . implode(', ', $all_crew) . '</div>';
            }
            if ($post) {
                $ihist_inner .= '<div style="margin-top:6px;"><a href="' . $post . '" class="bai-hist-post" target="_blank">Ver post &#x25B8;</a></div>';
            }
            $ihist_inner .= '</div>';
        }
        $ihist_inner .= '</div>';
    }
    $interior_hist_panel_html = '<div id="bai-historial-panel" class="bai-panel" style="display:none;">' . $ihist_inner . '</div>';
    $hist_interior_tab_btn    = '<button type="button" class="bai-tab-btn" data-tab="historial" onclick="baiSwitchTab(\'historial\')">&#x1F4DC; Historial</button>';

    eval("\$page = \"" . $templates->get("op_barco_interior") . "\";");

} else {


    $mis_barcos = array();
    $q = $db->query("
        SELECT i.objeto_id, i.cantidad,
               COALESCE(o.nombre, i.objeto_id) AS nombre,
               COALESCE(o.descripcion, '') AS descripcion,
               COALESCE(NULLIF(i.imagen, ''), o.imagen, '') AS imagen,
               COALESCE(b.espacios_mejora, 0) AS espacios_mejora,
               COALESCE(b.vitalidad, 0) AS vitalidad,
               COALESCE(b.velocidad, 0) AS velocidad,
               COALESCE(b.espacios, 0) AS espacios_carga
        FROM mybb_op_inventario i
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = i.objeto_id
        LEFT JOIN mybb_op_barcos b ON b.barco_id COLLATE utf8_unicode_ci = i.objeto_id
        WHERE i.uid = '" . $uid . "' AND i.bautizado = 1
        ORDER BY nombre ASC
    ");
    while ($r = $db->fetch_array($q)) {
        $mis_barcos[] = $r;
    }

    $barcos_html = '';
    if (empty($mis_barcos)) {
        $barcos_html = '<div class="ba-empty">No tienes ning&#xFA;n barco en tu inventario.</div>';
    } else {
        foreach ($mis_barcos as $b) {
            $nombre = htmlspecialchars_uni($b['nombre']);
            $obj_id = htmlspecialchars_uni($b['objeto_id']);
            $img    = htmlspecialchars_uni($b['imagen']);
            $desc   = htmlspecialchars_uni(mb_substr($b['descripcion'], 0, 110));
            if (mb_strlen($b['descripcion']) > 110) {
                $desc .= '&hellip;';
            }
            $stats = '';
            if ($b['vitalidad'])       { $stats .= '<span class="ba-stat">Vitalidad ' . (int)$b['vitalidad']      . '</span>'; }
            if ($b['velocidad'])       { $stats .= '<span class="ba-stat">Vel. '      . (int)$b['velocidad']      . '</span>'; }
            if ($b['espacios_carga'])  { $stats .= '<span class="ba-stat">Carga '     . (int)$b['espacios_carga'] . '</span>'; }
            if ($b['espacios_mejora']) { $stats .= '<span class="ba-stat">Mejoras '   . (int)$b['espacios_mejora'] . '</span>'; }

            $img_el   = $img
                ? '<img class="ba-card-img" src="' . $img . '" alt="" />'
                : '<div class="ba-card-img ba-card-img--ph">&#x2693;</div>';
            $img_html = '<div class="ba-card-img-wrap" data-barco="' . $obj_id . '">'
                . $img_el
                . '<button type="button" class="ba-img-edit-btn" onclick="baAbrirEditarImagen(\'' . $obj_id . '\',' . $uid . ')" title="Cambiar imagen">&#x270F;</button>'
                . '</div>';

            $stats_html = $stats ? '<div class="ba-card-stats">' . $stats . '</div>' : '';
            $desc_html  = $desc  ? '<div class="ba-card-desc">'  . $desc  . '</div>' : '';

            $barcos_html .= '<div class="ba-card">'
                . $img_html
                . '<div class="ba-card-body">'
                . '<div class="ba-card-nombre">' . $nombre . '</div>'
                . '<div class="ba-card-id">' . $obj_id . '</div>'
                . $stats_html
                . $desc_html
                . '</div>'
                . '<a href="/op/barco.php?barco_id=' . $obj_id . '&amp;owner=' . $uid . '" class="ba-btn-acceder">Acceder &#x25B8;</a>'
                . '</div>';
        }
    }

    // Invitaciones pendientes
    $invitaciones_html = '';
    $q = $db->query("
        SELECT inv.id, inv.barco_id, inv.owner_uid,
               COALESCE(o.nombre, inv.barco_id) AS nombre,
               COALESCE(o.imagen, '') AS imagen,
               COALESCE(u.username, inv.owner_uid) AS owner_nombre,
               COALESCE(b.vitalidad, 0) AS vitalidad,
               COALESCE(b.velocidad, 0) AS velocidad,
               COALESCE(b.espacios, 0) AS espacios_carga
        FROM mybb_op_barco_invitaciones inv
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = inv.barco_id
        LEFT JOIN mybb_op_barcos b ON b.barco_id COLLATE utf8_unicode_ci = inv.barco_id
        LEFT JOIN mybb_users u ON u.uid = inv.owner_uid
        WHERE inv.miembro_uid = '$uid'
        ORDER BY inv.fecha DESC
    ");
    $inv_pendientes = array();
    while ($r = $db->fetch_array($q)) {
        $inv_pendientes[] = $r;
    }
    if (!empty($inv_pendientes)) {
        $invitaciones_html = '<div class="ba-section-title">&#x2709; Invitaciones pendientes</div><div class="ba-grid">';
        foreach ($inv_pendientes as $inv) {
            $nombre   = htmlspecialchars_uni($inv['nombre']);
            $obj_id   = htmlspecialchars_uni($inv['barco_id']);
            $img      = htmlspecialchars_uni($inv['imagen']);
            $owner_n  = htmlspecialchars_uni($inv['owner_nombre']);
            $inv_id   = (int)$inv['id'];
            $o_uid    = (int)$inv['owner_uid'];
            $stats    = '';
            if ($inv['vitalidad'])     { $stats .= '<span class="ba-stat">Vitalidad ' . (int)$inv['vitalidad']     . '</span>'; }
            if ($inv['velocidad'])     { $stats .= '<span class="ba-stat">Vel. '      . (int)$inv['velocidad']     . '</span>'; }
            if ($inv['espacios_carga']){ $stats .= '<span class="ba-stat">Carga '     . (int)$inv['espacios_carga']. '</span>'; }
            $img_html   = $img ? '<img class="ba-card-img" src="' . $img . '" alt="" />' : '<div class="ba-card-img ba-card-img--ph">&#x2693;</div>';
            $stats_html = $stats ? '<div class="ba-card-stats">' . $stats . '</div>' : '';
            $invitaciones_html .= '<div class="ba-card" id="ba-inv-' . $inv_id . '">'
                . $img_html
                . '<div class="ba-card-body">'
                . '<div class="ba-card-nombre">' . $nombre . '</div>'
                . '<div class="ba-card-id">' . $obj_id . '</div>'
                . '<div class="ba-card-owner">Cap. ' . $owner_n . '</div>'
                . $stats_html
                . '</div>'
                . '<div class="ba-inv-actions">'
                . '<button type="button" class="ba-btn-aceptar" onclick="baAceptarInvitacion(' . $inv_id . ',\'' . $obj_id . '\',' . $o_uid . ')">&#x2713; Aceptar</button>'
                . '<button type="button" class="ba-btn-rechazar" onclick="baRechazarInvitacion(' . $inv_id . ',\'' . $obj_id . '\',' . $o_uid . ')">&#x2715; Rechazar</button>'
                . '</div>'
                . '</div>';
        }
        $invitaciones_html .= '</div>';
    }

    // Barcos en los que el usuario es tripulante (no dueño)
    $barcos_tripulante_html = '';
    $q = $db->query("
        SELECT t.barco_id, t.owner_uid,
               COALESCE(u.username, t.owner_uid) AS owner_nombre,
               COALESCE(o.nombre, t.barco_id) AS nombre,
               COALESCE(o.imagen, '') AS imagen,
               COALESCE(b.vitalidad, 0) AS vitalidad,
               COALESCE(b.velocidad, 0) AS velocidad,
               COALESCE(b.espacios, 0) AS espacios_carga,
               COALESCE(b.espacios_mejora, 0) AS espacios_mejora
        FROM mybb_op_barco_tripulacion t
        LEFT JOIN mybb_op_objetos o ON o.objeto_id = t.barco_id
        LEFT JOIN mybb_op_barcos b ON b.barco_id COLLATE utf8_unicode_ci = t.barco_id
        LEFT JOIN mybb_users u ON u.uid = t.owner_uid
        WHERE t.miembro_uid = '$uid'
        ORDER BY nombre ASC
    ");
    $barcos_como_trip = array();
    while ($r = $db->fetch_array($q)) {
        $barcos_como_trip[] = $r;
    }
    if (!empty($barcos_como_trip)) {
        $barcos_tripulante_html = '<div class="ba-section-title">&#x1F9ED; Tripulante de</div><div class="ba-grid">';
        foreach ($barcos_como_trip as $b) {
            $nombre  = htmlspecialchars_uni($b['nombre']);
            $obj_id  = htmlspecialchars_uni($b['barco_id']);
            $img     = htmlspecialchars_uni($b['imagen']);
            $owner_n = htmlspecialchars_uni($b['owner_nombre']);
            $o_uid   = (int)$b['owner_uid'];
            $stats   = '';
            if ($b['vitalidad'])      { $stats .= '<span class="ba-stat">Vitalidad ' . (int)$b['vitalidad']      . '</span>'; }
            if ($b['velocidad'])      { $stats .= '<span class="ba-stat">Vel. '      . (int)$b['velocidad']      . '</span>'; }
            if ($b['espacios_carga']) { $stats .= '<span class="ba-stat">Carga '     . (int)$b['espacios_carga'] . '</span>'; }
            $img_html   = $img ? '<img class="ba-card-img" src="' . $img . '" alt="" />' : '<div class="ba-card-img ba-card-img--ph">&#x2693;</div>';
            $stats_html = $stats ? '<div class="ba-card-stats">' . $stats . '</div>' : '';
            $barcos_tripulante_html .= '<div class="ba-card">'
                . $img_html
                . '<div class="ba-card-body">'
                . '<div class="ba-card-nombre">' . $nombre . '</div>'
                . '<div class="ba-card-id">' . $obj_id . '</div>'
                . '<div class="ba-card-owner">Cap. ' . $owner_n . '</div>'
                . $stats_html
                . '</div>'
                . '<a href="/op/barco.php?barco_id=' . $obj_id . '&amp;owner=' . $o_uid . '" class="ba-btn-acceder">Acceder &#x25B8;</a>'
                . '</div>';
        }
        $barcos_tripulante_html .= '</div>';
    }

    // Muelle: check carpintero oficio o NPC con rol Carpintero en cualquier barco del usuario
    $q        = $db->simple_select('op_fichas', 'oficio1, oficio2', "fid='$uid'");
    $ficha_sel = $db->fetch_array($q);
    $oficios_carpintero = array('Carpintero', 'Astillero', 'Constructor');
    $es_carpintero = $uid === 850 || ($ficha_sel && (
        in_array($ficha_sel['oficio1'], $oficios_carpintero) ||
        in_array($ficha_sel['oficio2'], $oficios_carpintero)
    ));
    if (!$es_carpintero) {
        $q_npc_m = $db->query("
            SELECT 1 FROM mybb_op_npcs
            WHERE npc_id LIKE '" . (int)$uid . "-%' AND oficios LIKE '%Carpintero%'
            LIMIT 1
        ");
        if ($db->fetch_array($q_npc_m)) { $es_carpintero = true; }
    }

    $fileVersion        = rand();
    $main_tabs_html     = '';
    $main_panel_open    = '';
    $main_panel_close   = '';
    $muelle_panel_html  = '';

    // ── Historial global de viajes ──
    $historial_viajes = [];
    $q_hist = $db->query("
        SELECT v.id, v.uid_viaje, v.nombre AS barco_nombre, v.mar, v.partida, v.llegada, v.horas,
               v.fecha_salida, v.temporada, v.postViaje, v.dado_naval, v.modificador, v.viajeros,
               u.username AS nav_user
        FROM mybb_op_viajes v
        LEFT JOIN mybb_users u ON u.uid = v.uid_viaje
        ORDER BY v.id DESC
        LIMIT 30
    ");
    while ($r = $db->fetch_array($q_hist)) { $historial_viajes[] = $r; }

    // Batch-resolve all viajeros UIDs to usernames
    $all_viajero_uids = [];
    foreach ($historial_viajes as $v) {
        if (!empty($v['viajeros'])) {
            foreach (explode(',', $v['viajeros']) as $vid) {
                $vid = (int)trim($vid);
                if ($vid > 0) { $all_viajero_uids[$vid] = true; }
            }
        }
    }
    $uid_to_username = [];
    if (!empty($all_viajero_uids)) {
        $uid_list = implode(',', array_keys($all_viajero_uids));
        $q_users = $db->query("SELECT uid, username FROM mybb_users WHERE uid IN ($uid_list)");
        while ($ru = $db->fetch_array($q_users)) {
            $uid_to_username[(int)$ru['uid']] = $ru['username'];
        }
    }

    if (empty($historial_viajes)) {
        $hist_inner = '<div class="ba-empty" style="padding:50px 0;">No hay viajes registrados todavía.</div>';
    } else {
        $hist_inner = '<div class="ba-hist-list">';
        foreach ($historial_viajes as $v) {
            $ruta    = htmlspecialchars_uni($v['partida']) . ' &rarr; ' . htmlspecialchars_uni($v['llegada']);
            $mar_h   = htmlspecialchars_uni($v['mar'] ?? '');
            $barco_h = htmlspecialchars_uni($v['barco_nombre'] ?? '');
            $nav_h   = htmlspecialchars_uni($v['nav_user'] ?? '');
            $fecha   = ($v['fecha_salida'] ? 'Día ' . (int)$v['fecha_salida'] : '') . ($v['temporada'] ? ' de ' . htmlspecialchars_uni($v['temporada']) : '');
            $horas   = (int)($v['horas'] ?? 0);
            $mod     = (int)($v['modificador'] ?? 0);
            $dado    = (int)($v['dado_naval'] ?? 0);
            $post    = htmlspecialchars_uni($v['postViaje'] ?? '');

            // Resolve viajeros to usernames
            $viajeros_names = [];
            if (!empty($v['viajeros'])) {
                foreach (explode(',', $v['viajeros']) as $vid) {
                    $vid = (int)trim($vid);
                    if ($vid > 0 && isset($uid_to_username[$vid])) {
                        $viajeros_names[] = htmlspecialchars_uni($uid_to_username[$vid]);
                    }
                }
            }

            $hist_inner .= '<div class="ba-hist-card">';
            $hist_inner .= '<div class="ba-hist-route">' . $ruta . '</div>';
            $hist_inner .= '<div class="ba-hist-meta">';
            if ($mar_h)   { $hist_inner .= '<span class="ba-hist-badge ba-hist-badge--mar">' . $mar_h . '</span>'; }
            if ($barco_h) { $hist_inner .= '<span class="ba-hist-badge">&#x2693; ' . $barco_h . '</span>'; }
            if ($fecha)   { $hist_inner .= '<span class="ba-hist-badge">' . $fecha . '</span>'; }
            if ($horas)   { $hist_inner .= '<span class="ba-hist-badge">' . $horas . ' h</span>'; }
            if ($mod !== 0) { $hist_inner .= '<span class="ba-hist-badge">Mod: ' . ($mod > 0 ? '+' : '') . $mod . '</span>'; }
            if ($dado)    { $hist_inner .= '<span class="ba-hist-badge">D100: ' . $dado . '</span>'; }
            $hist_inner .= '</div>';
            // Crew list
            $all_crew = $nav_h ? array_merge([$nav_h], $viajeros_names) : $viajeros_names;
            if (!empty($all_crew)) {
                $hist_inner .= '<div class="ba-hist-crew">' . implode(', ', $all_crew) . '</div>';
            }
            if ($post) {
                $hist_inner .= '<div style="margin-top:6px;"><a href="' . $post . '" class="ba-hist-post" target="_blank">Ver post &#x25B8;</a></div>';
            }
            $hist_inner .= '</div>';
        }
        $hist_inner .= '</div>';
    }
    $historial_panel_html = '<div id="ba-historial-panel" class="ba-main-panel" style="display:none;">' . $hist_inner . '</div>';

    // ── Historial tab button (shared) ──
    $_tab_style         = 'style="border:2px solid #000;"';
    $_tab_style_active  = 'style="border:2px solid #000;background:#ff8900;color:#fff;"';
    $hist_tab_btn = '<button type="button" class="ba-tab-btn" ' . $_tab_style . ' onclick="baSwitchMainTab(\'historial\')">&#x1F4DC; Historial</button>';

    // ── Staff tab ──
    $is_staff_sel   = (is_staff($uid) || is_mod($uid)) ? 1 : 0;
    $staff_tab_btn  = $is_staff_sel
        ? '<button type="button" class="ba-tab-btn" ' . $_tab_style . ' onclick="baSwitchMainTab(\'staff\')">&#x1F527; Taller de Tom</button>'
        : '';
    $staff_panel_html = $is_staff_sel
        ? '<div id="ba-staff-panel" class="ba-main-panel" style="display:none;"></div>'
        : '';

    if ($es_carpintero) {
        $barcos_muelle = array();
        $q = $db->query("
            SELECT i.objeto_id, i.cantidad,
                   COALESCE(o.nombre, i.objeto_id) AS nombre,
                   COALESCE(o.imagen, '') AS imagen
            FROM mybb_op_inventario i
            INNER JOIN mybb_op_objetos o ON o.objeto_id = i.objeto_id
            WHERE i.uid = '$uid' AND i.bautizado = 0 AND o.subcategoria = 'barcos'
            ORDER BY nombre ASC
        ");
        while ($r = $db->fetch_array($q)) {
            $barcos_muelle[] = $r;
        }
        $barcos_muelle_json = json_encode($barcos_muelle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

        $main_tabs_html = '<div class="ba-tabs">'
            . '<button type="button" class="ba-tab-btn ba-tab-active" ' . $_tab_style_active . ' onclick="baSwitchMainTab(\'barcos\')">&#x2693; Mis Barcos</button>'
            . '<button type="button" class="ba-tab-btn" ' . $_tab_style . ' onclick="baSwitchMainTab(\'muelle\')">&#x1F6A2; Muelle</button>'
            . $hist_tab_btn
            . '<button type="button" class="ba-tab-btn" ' . $_tab_style . ' onclick="baSwitchMainTab(\'viaje\')">&#x2388; Viaje</button>'
            . $staff_tab_btn
            . '</div>';
        $main_panel_open  = '<div id="ba-barcos-panel" class="ba-main-panel">';
        $main_panel_close = '</div>';
        $muelle_panel_html = '<div id="ba-muelle-panel" class="ba-main-panel" style="display:none;"></div>';
    } else {
        $main_tabs_html = '<div class="ba-tabs">'
            . '<button type="button" class="ba-tab-btn ba-tab-active" ' . $_tab_style_active . ' onclick="baSwitchMainTab(\'barcos\')">&#x2693; Mis Barcos</button>'
            . $hist_tab_btn
            . '<button type="button" class="ba-tab-btn" ' . $_tab_style . ' onclick="baSwitchMainTab(\'viaje\')">&#x2388; Viaje</button>'
            . $staff_tab_btn
            . '</div>';
        $main_panel_open  = '<div id="ba-barcos-panel" class="ba-main-panel">';
        $main_panel_close = '</div>';
    }

    // barco.js siempre se carga; BM solo para carpinteros; BS solo para staff
    $muelle_script_html = '<script>var BI={postCode:\'' . htmlspecialchars_uni($post_code) . '\'};</script>';
    if ($es_carpintero) {
        $muelle_script_html .= '<script>var BM={barcos:' . $barcos_muelle_json . ',postCode:\'' . htmlspecialchars_uni($post_code) . '\'};</script>';
    }
    if ($is_staff_sel) {
        $muelle_script_html .= '<script>var BS={postCode:\'' . htmlspecialchars_uni($post_code) . '\'};</script>';
    }
    $muelle_script_html .= '<script type="text/javascript" src="/jscripts/barco.js?v=' . $fileVersion . '"></script>';

    eval("\$page = \"" . $templates->get("op_barco_seleccion") . "\";");
}

output_page($page);
