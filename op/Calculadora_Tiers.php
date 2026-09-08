<?php
/**
 * MyBB 1.8
 * Calculadora Tiers - Página y endpoint de guardado
 */

// --- DEPURACIÓN TEMPORAL---
ini_set('display_errors', '1');               // mostrar (solo mientras depuras)
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');                   // deja esto en 1 siempre
ini_set('error_log', './php-error.log'); // cámbialo a una ruta fuera del webroot
error_reporting(E_ALL);
// -----------------------------------------------

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'Calculadora_Tiers.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";

global $templates, $mybb, $db;

$uid = (int)$mybb->user['uid'];
$username = $mybb->user['username'] ?? '';
$action = $mybb->get_input('action');

// ---------- Utilidades de seguridad/AJAX ----------
function is_ajax_request(): bool {
    // Verificamos cabecera X-Requested-With y método POST para save
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

/**
 * Devuelve fid (int) buscando por nombre o apodo en op_fichas.
 * Prioriza coincidencia EXACTA por nombre y luego por apodo; si no, intenta LIKE.
 */
function resolve_fid_by_name(string $nombre): int {
    $nombre = trim($nombre);
    if ($nombre === '') return 0;

    $tabla = db_table_name('op_fichas', true);
    $safe = $db->escape_string($nombre);

    // 1) Exacto por nombre
    $q = $db->query("SELECT fid FROM {$tabla} WHERE nombre = '{$safe}' LIMIT 1");
    if ($q && $db->num_rows($q)) {
        $r = $db->fetch_array($q);
        return (int)$r['fid'];
    }

    // 2) Exacto por apodo
    $q = $db->query("SELECT fid FROM {$tabla} WHERE apodo = '{$safe}' LIMIT 1");
    if ($q && $db->num_rows($q)) {
        $r = $db->fetch_array($q);
        return (int)$r['fid'];
    }

    // 3) Fallback: LIKE por nombre o apodo (elige el primero)
    $like = $db->escape_string('%'.$nombre.'%');
    $q = $db->query("SELECT fid FROM {$tabla}
                     WHERE nombre LIKE '{$like}' OR apodo LIKE '{$like}'
                     ORDER BY nombre ASC
                     LIMIT 1");
    if ($q && $db->num_rows($q)) {
        $r = $db->fetch_array($q);
        return (int)$r['fid'];
    }

    return 0;
}

function require_same_origin(): void {
    // Protección básica: origen/referer del mismo host
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    $ok = false;
    if ($origin && stripos($origin, $host) !== false) $ok = true;
    if ($referer && stripos($referer, $host) !== false) $ok = true;

    if (!$ok) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Origen no permitido']);
        exit;
    }
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response($payload, int $status = 200): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Normaliza nombres de tablas para usarlos con las utilidades del core de MyBB.
 * - Para métodos como simple_select/insert/update/delete se debe pasar el nombre SIN prefijo.
 * - Para consultas manuales (query/write_query) se necesita la tabla con prefijo.
 */
function db_table_name(string $table, bool $withPrefix = false): string {
    global $db;
    $prefix = $db->table_prefix ?? (defined('TABLE_PREFIX') ? TABLE_PREFIX : '');
    $normalized = trim($table);
    if ($normalized === '') {
        return '';
    }

    // Elimina prefijos duplicados si el nombre ya viene con ellos
    $candidates = [];
    if (defined('TABLE_PREFIX')) {
        $candidates[] = TABLE_PREFIX;
    }
    if ($prefix && (!defined('TABLE_PREFIX') || $prefix !== TABLE_PREFIX)) {
        $candidates[] = $prefix;
    }
    foreach ($candidates as $candidate) {
        if ($candidate && strpos($normalized, $candidate) === 0) {
            $normalized = substr($normalized, strlen($candidate));
            break;
        }
    }

    if ($withPrefix) {
        return $prefix . $normalized;
    }
    return $normalized;
}

function ensure_meta_table(): void {
    static $metaReady = false;
    if ($metaReady) {
        return;
    }
    global $db;
    $tablaMeta = db_table_name('op_peticionAventuras_meta', true);
    $db->write_query("CREATE TABLE IF NOT EXISTS `{$tablaMeta}` (
        `peticion_id` INT UNSIGNED NOT NULL,
        `staff_note` TEXT NULL,
        `public_comment` TEXT NULL,
        `updated_by` INT UNSIGNED NULL,
        `updated_at` INT UNSIGNED NULL,
        PRIMARY KEY (`peticion_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $metaReady = true;
}

/**
 * Asegura que la tabla `op_peticionAventuras` tenga la columna `inframundo`.
 */
function ensure_inframundo_column_exists(): void {
    static $checked = false;
    if ($checked) return;
    global $db;
    $tabla = db_table_name('op_peticionAventuras', true);
    $q = $db->query("SHOW COLUMNS FROM {$tabla} LIKE 'inframundo'");
    if (!$q || $db->num_rows($q) === 0) {
        // Añadimos la columna si no existe (tinyint 0/1)
        $db->write_query("ALTER TABLE {$tabla} ADD COLUMN `inframundo` TINYINT(1) NOT NULL DEFAULT 0");
    }
    $checked = true;
}

function get_status_catalog(): array {
    return [
        0 => ['key' => 'creado', 'label' => 'Creado'],
        1 => ['key' => 'aprobado', 'label' => 'Aprobado'],
        2 => ['key' => 'denegado', 'label' => 'Denegado'],
        3 => ['key' => 'finalizado', 'label' => 'Finalizado'],
        4 => ['key' => 'pendiente-narrador', 'label' => 'Pendiente narrador'],
        5 => ['key' => 'pendiente-borrado', 'label' => 'Pendiente de borrado'],
    ];
}

function parse_status_code($value): ?int {
    if ($value === null || $value === '') {
        return null;
    }
    $catalog = get_status_catalog();
    if (is_numeric($value)) {
        $code = (int)$value;
        return array_key_exists($code, $catalog) ? $code : null;
    }
    $map = [
        'creado' => 0,
        'creada' => 0,
        'aprobado' => 1,
        'aprobada' => 1,
        'denegado' => 2,
        'denegada' => 2,
        'finalizado' => 3,
        'finalizada' => 3,
        'pendiente narrador' => 4,
        'pendiente-narrador' => 4,
        'pendiente_narrador' => 4,
        'pendiente' => 4,
        'pendiente borrado' => 5,
        'pendiente-borrado' => 5,
        'pendiente_borrado' => 5,
    ];
    $key = strtolower(trim((string)$value));
    return $map[$key] ?? null;
}

function normalize_status_code($value): int {
    $parsed = parse_status_code($value);
    if ($parsed !== null) {
        return $parsed;
    }
    return 0;
}

function get_status_info($value): array {
    $catalog = get_status_catalog();
    $code = normalize_status_code($value);
    $info = $catalog[$code] ?? $catalog[0];
    $info['code'] = $code;
    return $info;
}

// ---------- Reimplementación del cálculo en PHP (paridad con JS) ----------
function obtenerTierPorNivelPHP(int $nivel): int {
    if ($nivel >= 100) return 16; // 16 = tier "SSS"
    if ($nivel >= 90) return 15;
    if ($nivel >= 80) return 14;
    if ($nivel >= 70) return 13;
    if ($nivel >= 60) return 12;
    if ($nivel >= 50) return 11;
    if ($nivel >= 40) return 10;
    if ($nivel >= 35) return 9;
    if ($nivel >= 30) return 8;
    if ($nivel >= 25) return 7;
    if ($nivel >= 20) return 6;
    if ($nivel >= 16) return 5;
    if ($nivel >= 12) return 4;
    if ($nivel >= 8)  return 3;
    if ($nivel >= 4)  return 2;
    return 1;
}

function expandirEnemigosPHP(array $enemigos): array {
    $out = [];
    foreach ($enemigos as $e) {
        $nombre = trim((string)$e['nombre']);
        $nivel  = (int)$e['nivel'];
        $cantidad = max(1, (int)$e['cantidad']);
        for ($i=0; $i<$cantidad; $i++) {
            $suffix = ($cantidad > 1) ? (' #'.($i+1)) : '';
            $out[] = ['nombre' => $nombre.$suffix, 'nivel' => $nivel];
        }
    }
    return $out;
}

function calcularDificultadPHP(int $nivelPromedioJugadores, int $numJugadores, array $enemigos): array {
    if ($numJugadores === 0 || empty($enemigos)) {
        return ['texto' => '-', 'color' => '#888888', 'detalles' => [], 'ratioPoder' => 1.0];
    }

    $expandidos = expandirEnemigosPHP($enemigos);
    $totalEnemigos = count($expandidos);
    if ($totalEnemigos === 0) {
        return ['texto' => '-', 'color' => '#888888', 'detalles' => [], 'ratioPoder' => 1.0];
    }

    $poderJugadores = max(1, $nivelPromedioJugadores) * max(1, $numJugadores); // evita /0
    $poderEnemigos = 0.0;
    $detalles = [];

    foreach ($expandidos as $idx => $enm) {
        $dif = ((int)$enm['nivel']) - $nivelPromedioJugadores;
        if ($dif >= 0) {
            $factorNivel = pow(1.2, $dif);
        } else {
            $factorNivel = pow(0.7, abs($dif));
        }

        $ratioEnemigos = $totalEnemigos / max(1, $numJugadores);
        $factorCantidad = 1.0;

        if ($ratioEnemigos > 1) {
            $base = ($dif >= 0) ? 1.15 : 1.08;
            $posRel = ($idx + 1) / max(1, $numJugadores);
            if ($posRel > 1) {
                $factorCantidad = pow($base, $posRel - 1.0);
            }
        } else if ($ratioEnemigos < 1 && $ratioEnemigos > 0) {
            $factorCantidad = pow(0.85, (1 / $ratioEnemigos) - 1.0);
        }

        $contribBase = max(1, $nivelPromedioJugadores) * $factorNivel;
        $contrib = $contribBase * $factorCantidad;

        $poderEnemigos += $contrib;
        $detalles[] = [
            'nombre' => (string)$enm['nombre'],
            'nivel' => (int)$enm['nivel'],
            'diferenciaNivel' => $dif,
            'factorNivel' => round($factorNivel, 4),
            'factorCantidad' => round($factorCantidad, 4),
            'contribucion' => round($contrib, 4),
        ];
    }

    $ratio = ($poderJugadores > 0) ? ($poderEnemigos / $poderJugadores) : 1.0;

    // Diferencia de nivel equivalente (base 1.2)
    if ($ratio > 1) {
        $difEq = log($ratio) / log(1.2);
    } else if ($ratio < 1 && $ratio > 0) {
        $difEq = -log(1/$ratio) / log(1.2);
    } else {
        $difEq = 0;
    }

    $texto = 'TRIVIAL';
    $color = '#00ffff';
    if ($difEq <= -2) {
        $texto = 'FÁCIL'; $color = '#00ff00';
    } else if ($difEq > -2 && $difEq <= 1) {
        $texto = 'AJUSTADO'; $color = '#ffff00';
    } else if ($difEq > 1 && $difEq <= 3) {
        $texto = 'DIFÍCIL'; $color = '#ff8800';
    } else if ($difEq > 3 && $difEq <= 5) {
        $texto = 'MUY DIFÍCIL'; $color = '#ff4400';
    } else if ($difEq > 5) {
        $texto = 'HARDCORE'; $color = '#ff0000';
    }

    return [
        'texto' => $texto,
        'color' => $color,
        'detalles' => $detalles,
        'ratioPoder' => round($ratio, 4)
    ];
}

// ============== SAVE ENDPOINT (AJAX JSON) ==============
if ($action === 'save') {
    // Permisos
    // if (!($uid && (is_mod($uid) || is_staff($uid) || is_narra($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }

    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();

    // Aseguramos la columna y leemos el flag
    ensure_inframundo_column_exists();
    
    // DEBUG: Ver qué está llegando exactamente
    error_log("DEBUG INFRAMUNDO - Valor recibido: " . var_export($in['inframundo'] ?? 'NO EXISTE', true));
    error_log("DEBUG INFRAMUNDO - Tipo: " . gettype($in['inframundo'] ?? null));
    
    $inframundo = isset($in['inframundo']) && $in['inframundo'] === true;

    $sin_narrador = !empty($in['sin_narrador']) ? true : false;
    $comentario_publico = trim((string)($in['public_comment'] ?? ''));

    $narrador_fid = isset($in['narrador_fid']) ? (int)$in['narrador_fid'] : 0;
    $narrador_nombre = mb_substr(trim((string)($in['narrador_nombre'] ?? '')), 0, 80);

    // Si sin narrador -> todos null
    if ($sin_narrador) {
        $narrador_uid = null;
        $narrador_fid = null;
        $narrador_nombre = null;
    } else {
        // No confiamos en el cliente: el narrador_uid es SIEMPRE el uid del usuario logueado
        $narrador_uid = (int)$uid;

        // Completamos nombre si falta y hay fid
        if ($narrador_fid > 0 && (!$narrador_nombre || $narrador_nombre === '')) {
            $q = $db->simple_select('op_fichas', 'nombre, apodo', 'fid='.(int)$narrador_fid, ['limit' => 1]);
            if ($q && $db->num_rows($q)) {
                $r = $db->fetch_array($q);
                $narrador_nombre = $r['nombre'];
            }
        }
    }

    // Entradas esperadas
    $tierSel = (int)($in['tier'] ?? 0);
    $descripcionTier = trim((string)($in['descripcion_tier'] ?? ''));
    $jugadores = is_array($in['jugadores'] ?? null) ? $in['jugadores'] : [];
    $enemigos  = is_array($in['enemigos'] ?? null) ? $in['enemigos']  : [];

    // Sanitización mínima
    $jugadores = array_values(array_filter(array_map(function($j){
        $nombre = mb_substr(trim((string)($j['nombre'] ?? '')), 0, 80);
        $nivel = max(1, min(100, (int)($j['nivel'] ?? 1)));
        $uidJugador = null;
        if (isset($j['uid'])) {
            $uidTemp = (int)$j['uid'];
            if ($uidTemp > 0) {
                $uidJugador = $uidTemp;
            }
        }
        return [
            'nombre' => $nombre,
            'nivel'  => $nivel,
            'uid'    => $uidJugador
        ];
    }, $jugadores), function($j){ return $j['nombre'] !== ''; }));

    // --- Resolver fid (uid real de ficha) por nombre si no vino del front ---
    foreach ($jugadores as &$j) {
        $uidExistente = isset($j['uid']) ? (int)$j['uid'] : 0;
        if ($uidExistente <= 0) {
            $fid = resolve_fid_by_name((string)$j['nombre']);
            if ($fid > 0) {
                $j['uid'] = $fid; // guarda el fid dentro del propio jugador
            }
        }
    }
    unset($j);

    $enemigos = array_values(array_filter(array_map(function($e){
        return [
            'nombre' => mb_substr(trim((string)($e['nombre'] ?? '')), 0, 60),
            'nivel' => max(1, min(100, (int)($e['nivel'] ?? 1))),
            'cantidad' => max(1, min(100, (int)($e['cantidad'] ?? 1)))
        ];
    }, $enemigos), function($e){ return $e['nombre'] !== ''; }));

    if ($sin_narrador) {
        $enemigos = [];
    }

    // Derivados
    $numJugadores = count($jugadores);
    $nivelPromedio = 0;
    if ($numJugadores > 0) {
        $suma = 0;
        foreach ($jugadores as $j) { $suma += (int)$j['nivel']; }
        $nivelPromedio = (int)round($suma / $numJugadores);
    }

    // Recalcular dificultad en backend
    $dif = calcularDificultadPHP($nivelPromedio, $numJugadores, $enemigos);

    // Persistencia
    $time = TIME_NOW;
    $record = [
        'uid' => $uid,
        'narrador_uid' => $narrador_uid,            // puede ir null
        'narrador_fid' => $narrador_fid ?: null,    // puede ir null
        'narrador_nombre' => $narrador_nombre !== null ? $db->escape_string($narrador_nombre) : null,
        'tier_seleccionado' => $tierSel,
        'nivel_promedio' => $nivelPromedio,
        'num_jugadores' => $numJugadores,
        'dificultad_texto' => $db->escape_string($dif['texto']),
        'dificultad_color' => $db->escape_string($dif['color']),
        'ratio_poder' => (float)$dif['ratioPoder'],
        'descripcion_tier' => $db->escape_string($descripcionTier),
        'jugadores_json' => $db->escape_string(json_encode($jugadores, JSON_UNESCAPED_UNICODE)),
        'enemigos_json' => $db->escape_string(json_encode($enemigos, JSON_UNESCAPED_UNICODE)),
        'detalles_json' => $db->escape_string(json_encode($dif['detalles'], JSON_UNESCAPED_UNICODE)),
        'created_at' => $time,
        'estado' => $sin_narrador ? 4 : 0,
        'comentario_publico' => $db->escape_string($comentario_publico),
        'comentario_staff' => '',
        'inframundo' => $inframundo
    ];
    
    // DEBUG: Confirmar valor antes del INSERT
    error_log("DEBUG INFRAMUNDO - Valor a insertar: " . var_export($inframundo, true) . " (tipo: " . gettype($inframundo) . ")");

    if (!$sin_narrador) {
        if ($narrador_uid !== $narrador_fid) {
            json_response(['ok' => false, 'error' => 'El narrador seleccionado no coincide con tu ficha.'], 400);
        }
    }

    $db->insert_query(db_table_name('op_peticionAventuras'), $record);
    $id = (int)$db->insert_id();

    json_response([
        'ok' => true,
        'id' => $id,
        'message' => 'Encuentro guardado',
        'snapshot' => [
            'tier' => $tierSel,
            'nivel_promedio' => $nivelPromedio,
            'num_jugadores' => $numJugadores,
            'dificultad' => $dif['texto'],
            'color' => $dif['color'],
            'ratio_poder' => $dif['ratioPoder'],
            'inframundo' => $inframundo ? 1 : 0
        ]
    ]);
} 

// ============== LIST ENDPOINT (AJAX JSON) ==============
if ($action === 'list') {
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $limit = (int)$mybb->get_input('limit');
    if ($limit <= 0 || $limit > 100) { $limit = 25; }

    ensure_meta_table();
    ensure_inframundo_column_exists();

    $tabla = db_table_name('op_peticionAventuras', true);
    $tablaUsuarios = db_table_name('users', true);
    $tablaMeta = db_table_name('op_peticionAventuras_meta', true);
    $esStaff = (is_mod($uid) || is_staff($uid) || is_narra($uid));

    $query = $db->query("SELECT pa.*, u.username,
        meta.staff_note AS meta_staff_note,
        meta.public_comment AS meta_public_comment,
        meta.updated_by AS meta_updated_by,
        meta.updated_at AS meta_updated_at
        FROM {$tabla} pa
        LEFT JOIN {$tablaUsuarios} u ON pa.uid = u.uid
        LEFT JOIN {$tablaMeta} meta ON meta.peticion_id = pa.id
        ORDER BY pa.created_at DESC");

    $items = [];
    while ($row = $db->fetch_array($query)) {
        $jugadores = json_decode($row['jugadores_json'] ?? '[]', true);
        if (!is_array($jugadores)) { $jugadores = []; }
        $enemigos = json_decode($row['enemigos_json'] ?? '[]', true);
        if (!is_array($enemigos)) { $enemigos = []; }
        $detalles = json_decode($row['detalles_json'] ?? '[]', true);
        if (!is_array($detalles)) { $detalles = []; }

        $statusInfo = get_status_info(isset($row['estado']) ? (int)$row['estado'] : 0);

        $publicCommentRaw = $row['comentario_publico'] ?? '';
        if ($publicCommentRaw === '' || $publicCommentRaw === null) {
            $publicCommentRaw = $row['meta_public_comment'] ?? '';
        }
        $publicComment = (string)$publicCommentRaw;

        $staffNoteRaw = '';
        if ($esStaff) {
            $staffNoteRaw = $row['comentario_staff'] ?? '';
            if ($staffNoteRaw === '' || $staffNoteRaw === null) {
                $staffNoteRaw = $row['meta_staff_note'] ?? '';
            }
        }

        $items[] = [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'username' => (string)($row['username'] ?? ''),
            'narrador_uid' => isset($row['narrador_uid']) ? (int)$row['narrador_uid'] : null,
            'narrador_fid' => isset($row['narrador_fid']) ? (int)$row['narrador_fid'] : null,
            'narrador_nombre' => $row['narrador_nombre'],
            'tier_seleccionado' => (int)$row['tier_seleccionado'],
            'nivel_promedio' => (int)$row['nivel_promedio'],
            'num_jugadores' => (int)$row['num_jugadores'],
            'dificultad_texto' => $row['dificultad_texto'],
            'dificultad_color' => $row['dificultad_color'],
            'ratio_poder' => (float)$row['ratio_poder'],
            'descripcion_tier' => $row['descripcion_tier'],
            'jugadores' => $jugadores,
            'enemigos' => $enemigos,
            'detalles' => $detalles,
            'created_at' => (int)$row['created_at'],
            'status' => $statusInfo['key'],
            'status_key' => $statusInfo['key'],
            'status_text' => $statusInfo['label'],
            'status_code' => $statusInfo['code'],
            'staff_note' => $esStaff ? (string)$staffNoteRaw : null,
            'public_comment' => $publicComment,
            'meta_updated_by' => isset($row['meta_updated_by']) ? (int)$row['meta_updated_by'] : null,
            'meta_updated_at' => isset($row['meta_updated_at']) ? (int)$row['meta_updated_at'] : null,
            'aventura_url' => isset($row['aventura_url']) ? (string)$row['aventura_url'] : '',
            'inframundo' => !empty($row['inframundo']) ? true : false
        ];
    }

    // === NUEVO: contar aventuras activas del visitante ===
    // Todas las aventuras cuentan para el límite general de 5
    // Las aventuras grupales (2+ jugadores) cuentan además para el límite adicional de 1
    $misActivasIndividual = 0;  // Todas las aventuras (para límite de 5)
    $misActivasGrupo = 0;       // Solo aventuras con 2+ jugadores (para límite adicional de 1)
    if ($uid > 0) {
        $qAventuras = $db->query("
            SELECT jugadores_json
            FROM {$tabla}
            WHERE JSON_CONTAINS(jugadores_json, JSON_OBJECT('uid', {$uid}))
              AND estado IN (0, 1, 4)
        ");
        
        while ($row = $db->fetch_array($qAventuras)) {
            $jugadores = json_decode($row['jugadores_json'] ?? '[]', true);
            if (!is_array($jugadores)) continue;
            
            $numJugadores = count($jugadores);
            // Todas las aventuras cuentan para el límite general
            $misActivasIndividual++;
            
            // Solo las grupales cuentan para el límite adicional
            if ($numJugadores >= 2) {
                $misActivasGrupo++;
            }
        }
    }

    json_response([
        'ok' => true, 
        'items' => $items, 
        'mis_aventuras_individuales' => $misActivasIndividual,  // Total de aventuras (límite 5)
        'mis_aventuras_grupo' => $misActivasGrupo,              // Aventuras grupales (límite adicional 1)
        'limite_individual' => 5,  // Límite de aventuras totales
        'limite_grupo' => 1        // Límite adicional solo para grupales (2+ jugadores)
    ]);
}


if ($action === 'get') {
    // if (!($uid && (is_mod($uid) || is_staff($uid) || is_narra($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }

    // Aseguramos columna de inframundo
    ensure_inframundo_column_exists();

    $peticionId = (int)$mybb->get_input('peticion_id');
    if ($peticionId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de petición inválido'], 400);
    }

    $tablaBase = db_table_name('op_peticionAventuras');
    $consulta = $db->simple_select($tablaBase, '*', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($consulta)) {
        json_response(['ok' => false, 'error' => 'La petición no existe'], 404);
    }
    $row = $db->fetch_array($consulta);

    $jugadores = json_decode($row['jugadores_json'] ?? '[]', true);
    if (!is_array($jugadores)) { $jugadores = []; }
    $enemigos = json_decode($row['enemigos_json'] ?? '[]', true);
    if (!is_array($enemigos)) { $enemigos = []; }
    $detalles = json_decode($row['detalles_json'] ?? '[]', true);
    if (!is_array($detalles)) { $detalles = []; }

    $statusInfo = get_status_info(isset($row['estado']) ? (int)$row['estado'] : 0);
    $esStaff = (is_mod($uid) || is_staff($uid) || is_narra($uid));
    $puedeEditar = $esStaff || ((int)($row['narrador_uid'] ?? 0) === $uid);

    json_response([
        'ok' => true,
        'item' => [
            'id' => (int)$row['id'],
            'uid' => (int)$row['uid'],
            'tier_seleccionado' => (int)$row['tier_seleccionado'],
            'nivel_promedio' => (int)$row['nivel_promedio'],
            'num_jugadores' => (int)$row['num_jugadores'],
            'descripcion_tier' => (string)($row['descripcion_tier'] ?? ''),
            'jugadores' => $jugadores,
            'enemigos' => $enemigos,
            'detalles' => $detalles,
            'dificultad_texto' => (string)($row['dificultad_texto'] ?? ''),
            'dificultad_color' => (string)($row['dificultad_color'] ?? ''),
            'ratio_poder' => (float)($row['ratio_poder'] ?? 0),
            'public_comment' => (string)($row['comentario_publico'] ?? ''),
            'narrador_uid' => isset($row['narrador_uid']) ? (int)$row['narrador_uid'] : null,
            'narrador_fid' => isset($row['narrador_fid']) ? (int)$row['narrador_fid'] : null,
            'narrador_nombre' => (string)($row['narrador_nombre'] ?? ''),
            'estado' => $statusInfo['key'],
            'inframundo' => !empty($row['inframundo']) ? true : false,
            'estado_code' => $statusInfo['code'],
            'aventura_url' => (string)($row['aventura_url'] ?? '')
        ],
        'puede_editar' => $puedeEditar,
        'es_staff' => $esStaff
    ]);
}

if ($action === 'update_meta') {
    // if (!($uid && (is_mod($uid) || is_staff($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();
    $peticionId = (int)($in['peticion_id'] ?? 0);
    if ($peticionId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de petición inválido'], 400);
    }

    $tablaBase = db_table_name('op_peticionAventuras');
    $existe = $db->simple_select($tablaBase, 'id', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($existe)) {
        json_response(['ok' => false, 'error' => 'La petición no existe'], 404);
    }

    ensure_meta_table();

    $maxStaffLen = 20;

    $camposMeta = [];
    $camposBase = [];
    $respuesta = [];
    $hayCambios = false;

    if (array_key_exists('staff_note', $in)) {
        $nota = trim((string)$in['staff_note']);
        $nota = mb_substr($nota, 0, $maxStaffLen);
        $valorMeta = $nota === '' ? null : $db->escape_string($nota);
        $camposMeta['staff_note'] = $valorMeta;
        $camposBase['comentario_staff'] = ($nota === '') ? '' : $db->escape_string($nota);
        $respuesta['staff_note'] = $nota;
    }

    if (array_key_exists('public_comment', $in)) {
        $comentario = trim((string)$in['public_comment']);
        $valorMetaComentario = $comentario === '' ? null : $db->escape_string($comentario);
        $camposMeta['public_comment'] = $valorMetaComentario;
        $camposBase['comentario_publico'] = ($comentario === '') ? '' : $db->escape_string($comentario);
        $respuesta['public_comment'] = $comentario;
    }

    if (array_key_exists('inframundo', $in)) {
        $camposBase['inframundo'] = isset($in['inframundo']) && $in['inframundo'] === true;
        $hayCambios = true;
        $respuesta['inframundo'] = $camposBase['inframundo'];
    }

    if (!empty($camposBase)) {
        $db->update_query($tablaBase, $camposBase, 'id=' . $peticionId);
        $hayCambios = true;
    }

    if (!empty($camposMeta)) {
        $camposMeta['updated_by'] = (int)$uid;
        $camposMeta['updated_at'] = (int)TIME_NOW;

        $tablaMetaNombre = db_table_name('op_peticionAventuras_meta');
        $metaExiste = $db->simple_select($tablaMetaNombre, 'peticion_id', 'peticion_id=' . $peticionId, ['limit' => 1]);

        if ($db->num_rows($metaExiste)) {
            $db->update_query($tablaMetaNombre, $camposMeta, 'peticion_id=' . $peticionId);
        } else {
            $camposMeta['peticion_id'] = $peticionId;
            $db->insert_query($tablaMetaNombre, $camposMeta);
        }
        $hayCambios = true;
    }

    $statusCode = null;
    if (array_key_exists('status_code', $in)) {
        $statusCode = parse_status_code($in['status_code']);
        if ($statusCode === null) {
            json_response(['ok' => false, 'error' => 'Estado inválido'], 400);
        }
    } elseif (array_key_exists('status', $in)) {
        $statusCode = parse_status_code($in['status']);
        if ($statusCode === null) {
            json_response(['ok' => false, 'error' => 'Estado inválido'], 400);
        }
    }

    if ($statusCode !== null) {
        // --- INICIO: Ajuste aventurasActivas según transición de estado (con excepción de grupo) ---
        // Cargar estado previo, jugadores y narrador de la petición
        $tablaBase = db_table_name('op_peticionAventuras');
        $prevQ = $db->simple_select($tablaBase, 'estado, jugadores_json, narrador_fid', 'id=' . $peticionId, ['limit' => 1]);
        if ($db->num_rows($prevQ)) {
            $prevR = $db->fetch_array($prevQ);
            $estadoPrevio = normalize_status_code(isset($prevR['estado']) ? (int)$prevR['estado'] : 0);
            $narradorFid = isset($prevR['narrador_fid']) ? (int)$prevR['narrador_fid'] : 0;

            $jugadores = json_decode($prevR['jugadores_json'] ?? '[]', true);
            if (!is_array($jugadores)) { $jugadores = []; }

            // Reunir fids desde jugadores_json (guardados en 'uid')
            // EXCLUIR al narrador: si es narrador, no cuenta como aventura activa propia
            $fids = [];
            foreach ($jugadores as $j) {
                $fid = isset($j['uid']) ? (int)$j['uid'] : 0;
                if ($fid > 0 && $fid !== $narradorFid) { $fids[] = $fid; }
            }
            $fids = array_values(array_unique($fids));
            $numJugadoresPeticion = count($jugadores);
            $esGrupo = ($numJugadoresPeticion >= 2);  // Grupal si tiene 2+ jugadores

            if (!empty($fids)) {
                $tablaFichas = db_table_name('op_fichas', true);
                $inList = implode(',', array_map('intval', $fids));

                // Transición a APROBADO (1)
                if ($statusCode === 1 && $estadoPrevio !== 1) {
                    // Validar límites: 5 aventuras totales + 1 grupal adicional
                    $q = $db->query("SELECT f.fid, f.nombre, f.apodo FROM {$tablaFichas} f WHERE f.fid IN ({$inList})");
                    $bloqueados = [];
                    $nombresBloqueados = [];
                    
                    $tablaPeticiones = db_table_name('op_peticionAventuras', true);
                    
                    while ($r = $db->fetch_array($q)) {
                        $fidCheck = (int)$r['fid'];
                        
                        // Contar aventuras activas de este jugador (estados 0,1,4) excluyendo las que narra
                        // IMPORTANTE: Excluir la petición actual para no contarla antes de aprobarla
                        $qAventuras = $db->query("
                            SELECT jugadores_json, narrador_fid
                            FROM {$tablaPeticiones}
                            WHERE JSON_CONTAINS(jugadores_json, JSON_OBJECT('uid', {$fidCheck}))
                              AND estado IN (0, 1, 4)
                              AND id != {$peticionId}
                        ");
                        
                        $totalAventuras = 0;      // Todas las aventuras (límite 5)
                        $aventurasGrupales = 0;   // Solo aventuras con 2+ jugadores (límite adicional 1)
                        
                        while ($av = $db->fetch_array($qAventuras)) {
                            // Verificar si esta ficha es el narrador (no cuenta como aventura propia)
                            $narradorFidAventura = isset($av['narrador_fid']) ? (int)$av['narrador_fid'] : 0;
                            if ($narradorFidAventura === $fidCheck) {
                                continue; // Skip si es narrador
                            }
                            
                            $jugadoresAv = json_decode($av['jugadores_json'] ?? '[]', true);
                            if (!is_array($jugadoresAv)) continue;
                            
                            $numJugAv = count($jugadoresAv);
                            // Todas las aventuras cuentan para el límite total
                            $totalAventuras++;
                            
                            // Las grupales cuentan además para el límite adicional
                            if ($numJugAv >= 2) {
                                $aventurasGrupales++;
                            }
                        }
                        
                        // Validar límites: 5 aventuras base + 1 adicional si tiene al menos 1 grupal
                        $excedeLimite = false;
                        $mensajeError = '';
                        
                        // Límite absoluto: 6 aventuras (nunca puede superarse)
                        if ($totalAventuras >= 6) {
                            $excedeLimite = true;
                            $mensajeError = 'ya tiene 6 aventuras activas (máximo absoluto alcanzado)';
                        }
                        // Si tiene 5 aventuras
                        else if ($totalAventuras >= 5) {
                            // Si no tiene ninguna grupal, solo puede agregar una grupal para desbloquear el slot
                            if ($aventurasGrupales === 0) {
                                if (!$esGrupo) {
                                    $excedeLimite = true;
                                    $mensajeError = 'ya tiene 5 aventuras activas sin ninguna grupal. Solo puede agregar una aventura GRUPAL (2+ jugadores) para desbloquear el slot adicional';
                                }
                                // Si es grupal, puede agregarla para desbloquear el sexto slot
                            }
                            // Si ya tiene al menos 1 grupal, el slot adicional está desbloqueado y puede agregar cualquier aventura
                        }
                        // Si tiene menos de 5, siempre puede agregar
                        
                        if ($excedeLimite) {
                            $bloqueados[] = $fidCheck;
                            $nombreFicha = !empty($r['apodo']) ? $r['apodo'] : $r['nombre'];
                            $nombresBloqueados[] = $nombreFicha . ' (' . $mensajeError . ' - total: ' . $totalAventuras . ', grupales: ' . $aventurasGrupales . ')';
                        }
                    }
                    
                    if (!empty($bloqueados)) {
                        $listaNombres = implode(', ', $nombresBloqueados);
                        $msgError = 'No se puede aprobar esta ' . ($esGrupo ? 'aventura GRUPAL' : 'aventura') . ': ' . $listaNombres;
                        json_response([
                            'ok' => false,
                            'error' => $msgError,
                            'fids' => $bloqueados
                        ], 400);
                    }

                    // Incrementar contador (ya no usamos aventurasActivas, pero lo mantenemos por compatibilidad)
                    $db->write_query("UPDATE {$tablaFichas}
                        SET aventurasActivas = LEAST(4, COALESCE(aventurasActivas,0) + 1)
                        WHERE fid IN ({$inList})");
                }

                // Transición a FINALIZADO (3)
                if (($statusCode === 3 && $estadoPrevio !== 3 && $estadoPrevio == 1) || ($statusCode === 5 && $estadoPrevio !== 5 && $estadoPrevio == 1)) {
                    $db->write_query("UPDATE {$tablaFichas}
                        SET aventurasActivas = GREATEST(0, COALESCE(aventurasActivas,0) - 1)
                        WHERE fid IN ({$inList})");
                }
            }
        }
        // --- FIN: Ajuste aventurasActivas ---

        // Guardar el nuevo estado (ya protegido por las validaciones previas)
        $db->update_query(db_table_name('op_peticionAventuras'), ['estado' => $statusCode], 'id=' . $peticionId);
        $statusInfo = get_status_info($statusCode);
        $respuesta['status_code'] = $statusInfo['code'];
        $respuesta['status_key'] = $statusInfo['key'];
        $respuesta['status_text'] = $statusInfo['label'];
        $hayCambios = true;
    }

    if (!$hayCambios) {
        json_response(['ok' => false, 'error' => 'Sin cambios a aplicar'], 400);
    }

    json_response([
        'ok' => true,
        'peticion_id' => $peticionId,
        'meta' => $respuesta
    ]);
}

if ($action === 'claim_narrador') {
    // if (!($uid && (is_mod($uid) || is_staff($uid) || is_narra($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();
    $peticionId = (int)($in['peticion_id'] ?? 0);
    if ($peticionId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de petición inválido'], 400);
    }

    $tablaBase = db_table_name('op_peticionAventuras');
    $consulta = $db->simple_select($tablaBase, 'id, uid, estado, narrador_uid', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($consulta)) {
        json_response(['ok' => false, 'error' => 'La petición no existe'], 404);
    }
    $peticion = $db->fetch_array($consulta);
    if ((int)$peticion['uid'] === $uid) {
        json_response(['ok' => false, 'error' => 'No puedes narrar tu propia petición'], 400);
    }
    if (!empty($peticion['narrador_uid'])) {
        json_response(['ok' => false, 'error' => 'La petición ya tiene narrador asignado'], 400);
    }
    if ((int)$peticion['estado'] !== 4) {
        json_response(['ok' => false, 'error' => 'La petición no está marcada como pendiente de narrador'], 400);
    }

    $narrador_fid = (int)($in['narrador_fid'] ?? 0);
    $narrador_nombre = mb_substr(trim((string)($in['narrador_nombre'] ?? '')), 0, 80);
    if ($narrador_fid <= 0) {
        json_response(['ok' => false, 'error' => 'Selecciona una ficha válida'], 400);
    }

    $narrador_uid = (int)$uid;
    if ($narrador_uid !== $narrador_fid) {
        json_response(['ok' => false, 'error' => 'Solo puedes postular la ficha que te pertenece'], 400);
    }

    if ($narrador_nombre === '') {
        $qNombre = $db->simple_select('op_fichas', 'nombre, apodo', 'fid=' . $narrador_fid, ['limit' => 1]);
        if ($db->num_rows($qNombre)) {
            $datosFicha = $db->fetch_array($qNombre);
            $narrador_nombre = $datosFicha['nombre'] ?? '';
        }
    }

    if ($narrador_nombre === '') {
        json_response(['ok' => false, 'error' => 'No se pudo determinar el nombre de la ficha'], 400);
    }

    $db->update_query($tablaBase, [
        'narrador_uid' => $narrador_uid,
        'narrador_fid' => $narrador_fid,
        'narrador_nombre' => $db->escape_string($narrador_nombre),
        'estado' => 0
    ], 'id=' . $peticionId);

    json_response([
        'ok' => true,
        'peticion_id' => $peticionId,
        'narrador_uid' => $narrador_uid,
        'narrador_fid' => $narrador_fid,
        'estado' => 0,
        'redirect' => 'Calculadora_Tiers.php?peticion=' . $peticionId
    ]);
}

if ($action === 'update_enemigos') {
    // if (!($uid && (is_mod($uid) || is_staff($uid) || is_narra($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();
    $peticionId = (int)($in['peticion_id'] ?? 0);
    if ($peticionId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de petición inválido'], 400);
    }

    $tablaBase = db_table_name('op_peticionAventuras');
    $consulta = $db->simple_select($tablaBase, '*', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($consulta)) {
        json_response(['ok' => false, 'error' => 'La petición no existe'], 404);
    }
    $row = $db->fetch_array($consulta);

    $esStaff = (is_mod($uid) || is_staff($uid) || is_narra($uid));
    $esNarradorAsignado = isset($row['narrador_uid']) && (int)$row['narrador_uid'] === $uid;
    if (!$esStaff && !$esNarradorAsignado) {
        json_response(['ok' => false, 'error' => 'No estás autorizado para modificar esta petición'], 403);
    }

    $jugadores = json_decode($row['jugadores_json'] ?? '[]', true);
    if (!is_array($jugadores)) {
        $jugadores = [];
    }
    $estadoActual = isset($row['estado']) ? (int)$row['estado'] : 0;
    $estadoDespues = ($estadoActual === 2) ? 0 : $estadoActual;

    $enemigosEntrada = is_array($in['enemigos'] ?? null) ? $in['enemigos'] : [];
    $enemigos = array_values(array_filter(array_map(function($e) {
        return [
            'nombre' => mb_substr(trim((string)($e['nombre'] ?? '')), 0, 60),
            'nivel' => max(1, min(100, (int)($e['nivel'] ?? 1))),
            'cantidad' => max(1, min(100, (int)($e['cantidad'] ?? 1)))
        ];
    }, $enemigosEntrada), function($e) {
        return $e['nombre'] !== '';
    }));

    if (empty($enemigos)) {
        json_response(['ok' => false, 'error' => 'Registra al menos un enemigo antes de guardar'], 400);
    }

    $numJugadores = count($jugadores);
    $nivelPromedio = (int)($row['nivel_promedio'] ?? 0);
    if ($numJugadores > 0 && $nivelPromedio <= 0) {
        $suma = 0;
        foreach ($jugadores as $j) {
            $suma += (int)($j['nivel'] ?? 0);
        }
        $nivelPromedio = (int)round($suma / $numJugadores);
    }

    $dif = calcularDificultadPHP($nivelPromedio, $numJugadores, $enemigos);

    $comentario_publico = mb_substr(trim((string)($in['public_comment'] ?? '')), 0, 150);

    $updatePayload = [
        'enemigos_json' => $db->escape_string(json_encode($enemigos, JSON_UNESCAPED_UNICODE)),
        'detalles_json' => $db->escape_string(json_encode($dif['detalles'], JSON_UNESCAPED_UNICODE)),
        'dificultad_texto' => $db->escape_string($dif['texto']),
        'dificultad_color' => $db->escape_string($dif['color']),
        'ratio_poder' => (float)$dif['ratioPoder'],
        'comentario_publico' => $db->escape_string($comentario_publico),
        'estado' => $estadoDespues
    ];
    if (array_key_exists('inframundo', $in)) {
        $updatePayload['inframundo'] = isset($in['inframundo']) && $in['inframundo'] === true;
    }
    $db->update_query($tablaBase, $updatePayload, 'id=' . $peticionId);

    json_response([
        'ok' => true,
        'message' => 'Encuentro actualizado',
        'peticion_id' => $peticionId,
        'estado' => $estadoDespues,
        'snapshot' => [
            'enemigos' => $enemigos,
            'detalles' => $dif['detalles'],
            'dificultad_texto' => $dif['texto'],
            'dificultad_color' => $dif['color'],
            'ratio_poder' => $dif['ratioPoder'],
            'public_comment' => $comentario_publico
        ]
    ]);
}

if ($action === 'hide_request') {
    if (!$uid) {
        json_response(['ok' => false, 'error' => 'Debes iniciar sesión'], 403);
    }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();
    $peticionId = (int)($in['peticion_id'] ?? 0);
    if ($peticionId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de petición inválido'], 400);
    }

    $tablaBase = db_table_name('op_peticionAventuras');
    $consulta = $db->simple_select($tablaBase, 'id, uid, estado', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($consulta)) {
        json_response(['ok' => false, 'error' => 'La petición no existe'], 404);
    }
    $peticion = $db->fetch_array($consulta);
    if ((int)$peticion['uid'] !== $uid) {
        json_response(['ok' => false, 'error' => 'Solo el creador puede ocultar la petición'], 403);
    }

    if ((int)$peticion['estado'] === 5) {
        json_response(['ok' => true, 'peticion_id' => $peticionId, 'estado' => 5]);
    }

    $db->update_query($tablaBase, ['estado' => 5], 'id=' . $peticionId);

    json_response([
        'ok' => true,
        'peticion_id' => $peticionId,
        'estado' => 5
    ]);
}

if ($action === 'delete') {
    // if (!($uid && (is_mod($uid) || is_staff($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();
    $peticionId = (int)($in['peticion_id'] ?? 0);
    if ($peticionId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de petición inválido'], 400);
    }

    $tablaBase      = db_table_name('op_peticionAventuras');
    $tablaBaseFull  = db_table_name('op_peticionAventuras', true);
    $tablaMeta      = db_table_name('op_peticionAventuras_meta');
    $tablaMetaFull  = db_table_name('op_peticionAventuras_meta', true);
    $tablaFichasFull= db_table_name('op_fichas', true);

    // Cargar estado y jugadores para ajustar aventurasActivas si procede
    $q = $db->simple_select($tablaBase, 'estado, jugadores_json', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($q)) {
        json_response(['ok' => false, 'error' => 'La petición no existe'], 404);
    }
    $row          = $db->fetch_array($q);
    $estadoActual = normalize_status_code(isset($row['estado']) ? (int)$row['estado'] : 0);

    // Extraer fids (se guardaron en 'uid' dentro de jugadores_json)
    $jugadores = json_decode($row['jugadores_json'] ?? '[]', true);
    if (!is_array($jugadores)) { $jugadores = []; }
    $fids = [];
    foreach ($jugadores as $j) {
        $fid = isset($j['uid']) ? (int)$j['uid'] : 0;
        if ($fid > 0) { $fids[] = $fid; }
    }
    $fids = array_values(array_unique($fids));

    // Transacción ligera para mantener coherencia
    $db->write_query('START TRANSACTION');

    // Si estaba APROBADO (1) al momento de borrar, decrementa aventurasActivas (mín 0)
    if ($estadoActual === 1 && !empty($fids)) {
        $inList = implode(',', array_map('intval', $fids));
        $db->write_query("
            UPDATE {$tablaFichasFull}
            SET aventurasActivas = GREATEST(0, COALESCE(aventurasActivas,0) - 1)
            WHERE fid IN ({$inList})
        ");
    }

    // Borrar la petición y su meta
    $db->delete_query($tablaBase, 'id=' . $peticionId);
    ensure_meta_table();
    $db->delete_query($tablaMeta, 'peticion_id=' . $peticionId);

    $db->write_query('COMMIT');

    json_response(['ok' => true, 'peticion_id' => $peticionId]);
}

// Vincular aventura (URL) a petición aprobada, acción del narrador
if ($action === 'vincular_aventura') {
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $in = json_input();
    $peticionId = (int)($in['peticion_id'] ?? 0);
    $url = trim((string)($in['aventura_url'] ?? ''));

    if ($peticionId <= 0 || $url === '') {
        json_response(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }

    // Normalizar URL
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (mb_strlen($url) > 255) {
        $url = mb_substr($url, 0, 255);
    }

    $tablaBase = db_table_name('op_peticionAventuras');

    // Solo narrador asignado puede vincular y solo si está aprobada
    $q = $db->simple_select($tablaBase, 'narrador_uid, estado', 'id=' . $peticionId, ['limit' => 1]);
    if (!$db->num_rows($q)) {
        json_response(['ok' => false, 'error' => 'Petición no encontrada'], 404);
    }
    $row = $db->fetch_array($q);
    if (normalize_status_code((int)$row['estado']) !== 1) {
        json_response(['ok' => false, 'error' => 'Solo se puede vincular si está aprobada'], 403);
    }
    if ((int)$row['narrador_uid'] !== (int)$uid) {
        json_response(['ok' => false, 'error' => 'No eres el narrador asignado'], 403);
    }

    $db->update_query($tablaBase, ['aventura_url' => $db->escape_string($url)], 'id=' . $peticionId);

    json_response(['ok' => true, 'peticion_id' => $peticionId, 'aventura_url' => $url]);
}

// === NUEVO: endpoint de búsqueda de fichas (desplegable con buscador) ===
if ($action === 'search_fichas') {
    // if (!($uid && (is_mod($uid) || is_staff($uid) || is_narra($uid)))) {
    //     json_response(['ok' => false, 'error' => 'Sin permisos'], 403);
    // }
    if (!is_ajax_request()) {
        json_response(['ok' => false, 'error' => 'Solicitud inválida'], 400);
    }
    require_same_origin();

    $q = trim((string)$mybb->get_input('q'));
    if ($q === '') {
        json_response(['ok' => true, 'items' => []]);
    }

    // Búsqueda por nombre o apodo, sin joins ni FKs
    $like = $db->escape_string('%'.$q.'%');
    $items = [];
    $query = $db->query("
        SELECT fid, nombre, apodo
        FROM mybb_op_fichas
        WHERE nombre LIKE '{$like}' OR apodo LIKE '{$like}'
        ORDER BY nombre ASC
        LIMIT 20
    ");
    while ($row = $db->fetch_array($query)) {
        $items[] = [
            'fid' => (int)$row['fid'],
            'nombre' => (string)$row['nombre'],
            'apodo' => (string)$row['apodo'],
        ];
    }
    json_response(['ok' => true, 'items' => $items]);
}

$current_uid = (int)$uid;

eval("\$page = \"".$templates->get('op_Calculadora_Tiers')."\";");
output_page($page);
