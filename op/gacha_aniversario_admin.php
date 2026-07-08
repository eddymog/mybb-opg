<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'gacha_aniversario_admin.php');
require_once "./../global.php";
require_once "./functions/op_functions.php";

$uid_actual = (int)$mybb->user['uid'];
if ($uid_actual !== 850 && $uid_actual !== 10) error_no_permission();

define('GACHA_ANIV_RESET', '2026-07-06 19:05:00'); // 03:05 Madrid = 19:05 hora BD (Madrid −8h)

// ── Conversión de zona horaria ────────────────────────────────────
// La BD almacena tiempo 8 horas atrás respecto a Madrid
function to_dbtime(string $madrid) : string {
    $dt = new DateTime($madrid, new DateTimeZone('Europe/Madrid'));
    $dt->modify('-8 hours');
    return $dt->format('Y-m-d H:i:s');
}
function to_ts(string $madrid) : int {
    return (new DateTime($madrid, new DateTimeZone('Europe/Madrid')))->getTimestamp();
}

// Columnas de mybb_op_fichas que se restauran desde el backup.
// Excluidas: fid (PK), tiempo_creacion, dia/edad/altura/peso/sexo/temporada (perfil físico),
//   apariencia/personalidad/historia/extra/frase/notas (textos de perfil),
//   fisico_de_pj/origen_de_pj/banner/como_nos_conociste/orientacion/aprobada_por (meta),
//   avatar1-4/banda_sonora (media), espacios/equipamiento/equipamiento_espacio/implantes,
//   secret1/rango_inframundo/cronologia/wantedGuardado/fx/movidoInframundo,
//   aventurasActivas/slotAventuras/expNarradorMensualActual/nivelnarrador/wantedcustom,
//   limite_nivel/wanted/reputacion2*/camino/ranuras
const FICHAS_RESTORE_COLS = [
    'nombre', 'apodo', 'faccion', 'raza',
    'berries', 'puntos_estadistica', 'nivel',
    'fuerza', 'fuerza_pasiva', 'resistencia', 'resistencia_pasiva',
    'destreza', 'destreza_pasiva', 'voluntad', 'voluntad_pasiva',
    'punteria', 'punteria_pasiva', 'agilidad', 'agilidad_pasiva',
    'reflejos', 'reflejos_pasiva', 'control_akuma', 'control_akuma_pasiva',
    'vitalidad', 'vitalidad_pasiva', 'energia', 'energia_pasiva',
    'haki', 'haki_pasiva',
    'nika', 'kuro',
    'rasgos_positivos', 'rasgos_negativos',
    'reputacion', 'reputacion_positiva', 'reputacion_negativa',
    'rango', 'fama',
    'belica1', 'belica2', 'belica3', 'belica4', 'belica5', 'belica6',
    'belica7', 'belica8', 'belica9', 'belica10', 'belica11', 'belica12',
    'belicas', 'oficios',
    'oficio1', 'puntos_oficio', 'oficio2', 'oficio2exp', 'oficio1nivel', 'oficio2nivel',
    'estilo1', 'estilo2', 'estilo3', 'estilo4', 'estilos', 'elementos',
    'sangre', 'akuma', 'akuma_subnombre', 'akuma_origen', 'dominio_akuma',
    'hao', 'hao_chance', 'kenbun', 'buso',
    'wanted_repu', 'muerto',
];

// ── Preview / Revert ──────────────────────────────────────────────

// Tokenizador de una fila VALUES (...) de mysqldump.
// Handles 'string' con \' \\ \n \r \", NULL, y numéricos.
function sql_parse_values_row(string $s, int $ncols) : array {
    $vals = [];
    $i    = 0;
    $len  = strlen($s);
    while ($i < $len && count($vals) < $ncols) {
        if ($s[$i] === "'") {
            $i++;
            $val = '';
            while ($i < $len) {
                if ($s[$i] === '\\' && $i + 1 < $len) {
                    $nx = $s[++$i];
                    if     ($nx === "'")  $val .= "'";
                    elseif ($nx === '\\') $val .= '\\';
                    elseif ($nx === 'n')  $val .= "\n";
                    elseif ($nx === 'r')  $val .= "\r";
                    elseif ($nx === '"')  $val .= '"';
                    else                  $val .= $nx;
                    $i++;
                } elseif ($s[$i] === "'") {
                    $i++;
                    break;
                } else {
                    $val .= $s[$i++];
                }
            }
            $vals[] = $val;
        } elseif (substr($s, $i, 4) === 'NULL') {
            $vals[] = null;
            $i += 4;
        } else {
            $val = '';
            while ($i < $len && $s[$i] !== ',' && $s[$i] !== ')') $val .= $s[$i++];
            $vals[] = trim($val);
        }
        if ($i < $len && $s[$i] === ',') $i++;
    }
    return $vals;
}

// Carga fichas desde el backup del 06/07. Devuelve [ok, [$fid => [col => val]]].
function load_backup_fichas() : array {
    $file = dirname(__DIR__) . '/admin/backups/restore_fichas_inventario_20260706.sql';
    if (!file_exists($file)) return [false, []];
    $fichas = [];
    $cols   = [];
    $fh     = fopen($file, 'r');
    while (($line = fgets($fh)) !== false) {
        if (strncmp($line, 'INSERT INTO mybb_op_fichas', 26) !== 0) continue;
        if (empty($cols)) {
            preg_match('/INSERT INTO mybb_op_fichas \(([^)]+)\)/', $line, $m);
            $cols = array_map(fn($c) => trim($c, '` '), explode(',', $m[1]));
        }
        $vstart = strpos($line, ' VALUES (') + 9;
        $row    = sql_parse_values_row(substr($line, $vstart), count($cols));
        $data   = array_combine($cols, $row);
        $fichas[(int)$data['fid']] = $data;
    }
    fclose($fh);
    return [true, $fichas];
}

// Carga el inventario desde el backup del 06/07 (pre-gacha).
// Devuelve [ok, [$uid][$objeto_id] = $cantidad].
// Columnas del INSERT: id, objeto_id, uid, cantidad, ...
function load_backup_inv() : array {
    $file = dirname(__DIR__) . '/admin/backups/restore_fichas_inventario_20260706.sql';
    if (!file_exists($file)) return [false, []];
    $inv = [];
    $fh  = fopen($file, 'r');
    while (($line = fgets($fh)) !== false) {
        if (strncmp($line, 'INSERT INTO mybb_op_inventario ', 31) !== 0) continue;
        $vstart = strpos($line, ' VALUES (') + 9;
        // Solo necesitamos las primeras 4 columnas: id, objeto_id, uid, cantidad
        $row = sql_parse_values_row(substr($line, $vstart), 4);
        if (count($row) >= 4 && $row[1] !== null && $row[2] !== null) {
            $inv[(int)$row[2]][$row[1]] = (int)($row[3] ?? 0);
        }
    }
    fclose($fh);
    return [true, $inv];
}

function calc_preview(object $db, string $desde_db, int $desde_ts, int $uid_filter = 0) : array {
    $preview  = [];
    $uid_cond = $uid_filter > 0 ? " AND staff='$uid_filter'" : '';

    // Cargar backup completo (una sola vez)
    [$fichas_ok, $bk_fichas] = load_backup_fichas();
    [$inv_ok,    $bk_inv]    = load_backup_inv();
    $backup_ok = $fichas_ok && $inv_ok;

    // Usuarios con actividad de gacha desde la fecha
    $q = $db->query("
        SELECT staff AS uid, MAX(username) AS username,
            SUM(CASE WHEN log LIKE 'Gacha Aniversario [berries x1]%'  THEN 1
                     WHEN log LIKE 'Gacha Aniversario [berries x10]%' THEN 10 ELSE 0 END) AS tb,
            SUM(CASE WHEN log LIKE 'Gacha Aniversario [kuros x1]%'    THEN 1
                     WHEN log LIKE 'Gacha Aniversario [kuros x10]%'   THEN 10 ELSE 0 END) AS tk
        FROM mybb_op_audit_consola_mod
        WHERE razon='gacha_aniversario' AND tiempo >= '$desde_db'$uid_cond
        GROUP BY staff
    ");

    while ($r = $db->fetch_array($q)) {
        $uid = (int)$r['uid'];

        // Primera tirada del usuario en el rango (solo para display)
        $row_primera    = $db->fetch_array($db->query(
            "SELECT MIN(tiempo) AS primera FROM mybb_op_audit_consola_mod
             WHERE staff='$uid' AND razon='gacha_aniversario' AND tiempo >= '$desde_db'"
        ));
        $primera_tirada = $row_primera['primera'] ?? $desde_db;

        // Ficha actual y ficha de backup
        $bak_ficha  = $bk_fichas[$uid] ?? null;
        $curr_ficha = $db->fetch_array($db->query(
            "SELECT " . implode(', ', FICHAS_RESTORE_COLS) . " FROM mybb_op_fichas WHERE fid='$uid' LIMIT 1"
        ));

        // Diff de ficha: backup vs actual
        $diff       = [];
        $json_cols  = ['belicas', 'oficios', 'estilos', 'elementos'];
        $texto_cols = ['rasgos_positivos', 'rasgos_negativos'];
        if ($bak_ficha && $curr_ficha) {
            foreach (FICHAS_RESTORE_COLS as $col) {
                $v_curr = (string)($curr_ficha[$col] ?? '');
                $v_bak  = (string)($bak_ficha[$col]  ?? '');
                if ($v_curr === $v_bak) continue;
                if (in_array($col, $json_cols, true)) {
                    $diff[] = ['col' => $col, 'curr' => '[JSON]', 'snap' => '[JSON]', 'json' => true];
                } elseif (in_array($col, $texto_cols, true)) {
                    $diff[] = ['col' => $col, 'curr' => mb_substr($v_curr, 0, 60) . '…', 'snap' => mb_substr($v_bak, 0, 60) . '…', 'texto' => true];
                } else {
                    $diff[] = ['col' => $col, 'curr' => $v_curr, 'snap' => $v_bak];
                }
            }
        }

        // Diff de inventario: backup vs actual (excluye IDs personalizados -N-N)
        $items      = [];
        if ($inv_ok) {
            $curr_items   = [];
            $iq = $db->query("SELECT objeto_id, cantidad FROM mybb_op_inventario WHERE uid='$uid'");
            while ($ir = $db->fetch_array($iq)) $curr_items[$ir['objeto_id']] = (int)$ir['cantidad'];
            $user_bak_inv = $bk_inv[$uid] ?? [];
            $all_oids     = array_unique(array_merge(array_keys($curr_items), array_keys($user_bak_inv)));
            foreach ($all_oids as $oid) {
                $c        = $curr_items[$oid]   ?? 0;
                $b        = $user_bak_inv[$oid] ?? 0;
                $is_custom = (bool)preg_match('/-\d+-\d+$/', $oid);
                if ($c === $b) continue;
                if ($c > $b && $is_custom) continue; // no eliminar ítems personalizados
                $items[$oid] = ['bak' => $b, 'curr' => $c];
            }
        }

        $preview[] = [
            'uid'              => $uid,
            'username'         => htmlspecialchars($r['username']),
            'tb'               => (int)$r['tb'],
            'tk'               => (int)$r['tk'],
            'bak_ficha'        => $bak_ficha,
            'curr_currencies'  => [
                'berries' => $curr_ficha['berries'] ?? null,
                'nika'    => $curr_ficha['nika']    ?? null,
                'kuro'    => $curr_ficha['kuro']    ?? null,
            ],
            'primera_tirada'   => $primera_tirada,
            'diff'             => $diff,
            'items'            => $items,
            'backup_ok'        => $backup_ok,
            'fichas_ok'        => $fichas_ok,
        ];
    }
    return $preview;
}

// ── Routing de acciones ───────────────────────────────────────────
$action       = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');
$revert_input = trim($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['dt'] ?? '') : ($_GET['dt'] ?? ''));
$uid_filter   = (int)(($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['uid_filter'] ?? 0) : ($_GET['uid_filter'] ?? 0)));
$revert_msg   = '';
$revert_ok    = false;
$preview_data = [];
$desde_madrid = '';
$desde_db     = '';
$desde_ts     = 0;

if (in_array($action, ['preview', 'revert'], true) && $revert_input) {
    $desde_madrid = str_replace('T', ' ', $revert_input);
    if (strlen($desde_madrid) === 16) $desde_madrid .= ':00'; // añadir segundos si falta
    try {
        new DateTime($desde_madrid, new DateTimeZone('Europe/Madrid'));
        $desde_db = to_dbtime($desde_madrid);
        $desde_ts = to_ts($desde_madrid);
        $preview_data = calc_preview($db, $desde_db, $desde_ts, $uid_filter);
    } catch (Exception $e) {
        $revert_msg = 'error:Fecha inválida: ' . htmlspecialchars($e->getMessage());
        $action = '';
    }
}

if ($action === 'revert' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirmed'] ?? '') === '1' && !$revert_msg) {
    $db->query("START TRANSACTION");

    foreach ($preview_data as $u) {
        $uid = (int)$u['uid'];

        // 1. Restaurar ficha desde el backup del 06/07
        if ($u['bak_ficha']) {
            $bak  = $u['bak_ficha'];
            $sets = [];
            foreach (FICHAS_RESTORE_COLS as $col) {
                if (array_key_exists($col, $bak)) {
                    $val    = $db->escape_string((string)($bak[$col] ?? ''));
                    $sets[] = "`$col` = '$val'";
                }
            }
            if ($sets) {
                $db->query("UPDATE mybb_op_fichas SET " . implode(', ', $sets) . " WHERE fid='$uid'");
            }
        }

        // 2. Restaurar inventario al estado del backup
        foreach ($u['items'] as $oid => $data) {
            $oid_esc   = $db->escape_string($oid);
            $bak_cnt   = (int)$data['bak'];
            $is_custom = (bool)preg_match('/-\d+-\d+$/', $oid);
            if ($bak_cnt > 0) {
                // Ítem existía en el backup: establecer cantidad al valor del backup
                $db->query(
                    "INSERT INTO mybb_op_inventario (objeto_id, uid, cantidad, autor, autor_uid, oficios, especial)
                     VALUES ('$oid_esc', '$uid', $bak_cnt, 'Revert', '850', 'null', 0)
                     ON DUPLICATE KEY UPDATE cantidad = $bak_cnt"
                );
            } elseif (!$is_custom) {
                // Ítem no existía en el backup y no es personalizado: eliminar
                $db->query("DELETE FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$oid_esc'");
            }
        }
    }

    // 3. Borrar logs de gacha (filtrado por UID si se especificó)
    if ($uid_filter > 0) {
        $db->query("DELETE FROM mybb_op_gacha_aniversario_log WHERE uid='$uid_filter' AND fecha >= $desde_ts");
        $db->query("DELETE FROM mybb_op_audit_consola_mod WHERE razon='gacha_aniversario' AND staff='$uid_filter' AND tiempo >= '$desde_db'");
    } else {
        $db->query("DELETE FROM mybb_op_gacha_aniversario_log WHERE fecha >= $desde_ts");
        $db->query("DELETE FROM mybb_op_audit_consola_mod WHERE razon='gacha_aniversario' AND tiempo >= '$desde_db'");
    }

    $db->query("COMMIT");

    $revert_ok    = true;
    $suffix       = $uid_filter > 0 ? " (UID $uid_filter)" : '';
    $revert_msg   = 'success:Revert ejecutado' . $suffix . '. ' . count($preview_data) . ' usuario(s) restaurados desde audit.';
    $preview_data = [];
    $action       = '';
}

// ── Resumen por usuario ───────────────────────────────────────────
$resumen = [];
$q = $db->query("
    SELECT staff AS uid, MAX(username) AS username,
        SUM(CASE WHEN log LIKE 'Gacha Aniversario [berries x1]%'  THEN 1
                 WHEN log LIKE 'Gacha Aniversario [berries x10]%' THEN 10 ELSE 0 END) AS tb,
        SUM(CASE WHEN log LIKE 'Gacha Aniversario [kuros x1]%'    THEN 1
                 WHEN log LIKE 'Gacha Aniversario [kuros x10]%'   THEN 10 ELSE 0 END) AS tk
    FROM mybb_op_audit_consola_mod
    WHERE razon='gacha_aniversario' AND tiempo > '" . GACHA_ANIV_RESET . "'
    GROUP BY staff
    ORDER BY tb DESC
");
while ($r = $db->fetch_array($q)) {
    $tb   = (int)$r['tb'];
    $tier = (int)floor($tb / 10);
    $cx1  = (int)round(10000000 * pow(1.115, $tier));
    $resumen[] = [
        'uid'       => (int)$r['uid'],
        'username'  => htmlspecialchars($r['username']),
        'tb'        => $tb,
        'tk'        => (int)$r['tk'],
        'tier'      => $tier,
        'costo_x1'  => $cx1,
        'costo_x10' => (int)round($cx1 * 9),
        'restantes' => max(0, 500 - $tb),
    ];
}

$total_usuarios = count($resumen);
$total_tb       = array_sum(array_column($resumen, 'tb'));
$total_tk       = array_sum(array_column($resumen, 'tk'));
$usuarios_cap   = count(array_filter($resumen, fn($r) => $r['restantes'] === 0));

// ── Historial de usuario ──────────────────────────────────────────
$view_uid  = ($action === '') ? (isset($_GET['u']) ? (int)$_GET['u'] : 0) : 0;
$view_user = null;
$historial = [];
if ($view_uid) {
    foreach ($resumen as $r) { if ($r['uid'] === $view_uid) { $view_user = $r; break; } }
    if (!$view_user) $view_user = ['uid'=>$view_uid,'username'=>'UID '.$view_uid,'tb'=>0,'tk'=>0,'tier'=>0,'costo_x1'=>10000000,'costo_x10'=>90000000,'restantes'=>500];
    $hq = $db->query("SELECT banner, premio_type, premio_nombre, fecha FROM mybb_op_gacha_aniversario_log WHERE uid='$view_uid' ORDER BY id DESC LIMIT 300");
    while ($hr = $db->fetch_array($hq)) $historial[] = $hr;
}

// ─────────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gacha Aniversario · Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;font-size:13px;background:#111827;color:#d1d5db;padding:24px;line-height:1.5}
a{color:#60a5fa;text-decoration:none}a:hover{text-decoration:underline}
h1{font-size:20px;color:#fbbf24;margin-bottom:18px;font-weight:700}
h2{font-size:13px;color:#93c5fd;margin:22px 0 10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.stat{background:#1f2937;border:1px solid #374151;border-radius:8px;padding:12px 20px;min-width:130px}
.stat-label{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.stat-value{font-size:22px;font-weight:700;color:#fbbf24}
input[type=text],input[type=datetime-local]{background:#1f2937;border:1px solid #374151;color:#d1d5db;padding:7px 12px;border-radius:6px;font-size:13px}
input[type=text]{width:260px}input[type=datetime-local]{width:230px}
input:focus{outline:none;border-color:#60a5fa}
.btn{display:inline-block;padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:background .15s}
.btn-blue{background:#2563eb;color:#fff}.btn-blue:hover{background:#1d4ed8}
.btn-red{background:#dc2626;color:#fff}.btn-red:hover{background:#b91c1c}
.btn-gray{background:#374151;color:#d1d5db}.btn-gray:hover{background:#4b5563}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:680px}
thead tr{background:#1f2937}
th{padding:9px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#9ca3af;white-space:nowrap;cursor:pointer;user-select:none}
th:hover{color:#d1d5db}
th.sorted-asc::after{content:' ▲'}th.sorted-desc::after{content:' ▼'}
td{padding:8px 12px;border-bottom:1px solid #1f2937;white-space:nowrap}
tbody tr{cursor:pointer;transition:background .1s}
tbody tr:hover td{background:#1f2937}tbody tr.active td{background:#1e3a5f}
.uid-cell{color:#6b7280;font-size:11px}.user-cell{font-weight:600;color:#e5e7eb}
.tier-0{color:#6b7280}.tier-low{color:#34d399}.tier-mid{color:#fbbf24}.tier-high{color:#f87171}
.cap{color:#6b7280;font-style:italic}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.bb{background:#92400e;color:#fde68a}.bk{background:#3b0764;color:#c4b5fd}
.panel{background:#1f2937;border:1px solid #374151;border-radius:10px;padding:20px;margin-top:24px}
.panel-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.panel-header h2{margin:0}
.meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:18px}
.meta-item{background:#111827;border-radius:6px;padding:10px 14px}
.meta-label{font-size:11px;color:#6b7280;margin-bottom:3px}
.meta-value{font-size:15px;font-weight:700;color:#f3f4f6}
.hist-table td,.hist-table th{font-size:12px;padding:5px 10px}
.premio-objeto{color:#e5e7eb}.premio-berries{color:#fde68a}.premio-kuro{color:#c4b5fd}.premio-nika{color:#86efac}
.empty{color:#6b7280;font-style:italic;padding:16px 0}
.close-btn{margin-left:auto;background:#374151;border:none;color:#9ca3af;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px}
.close-btn:hover{background:#4b5563;color:#d1d5db}
.revert-box{background:#1f2937;border:1px solid #374151;border-radius:10px;padding:20px;margin-top:32px}
.revert-box h2{margin-top:0}
.form-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:12px}
.form-label{font-size:11px;color:#9ca3af;margin-bottom:4px}
.preview-row{background:#111827;border-radius:8px;padding:14px 16px;margin-bottom:10px}
.preview-row-header{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.preview-username{font-weight:700;color:#e5e7eb;font-size:14px}
.preview-uid{color:#6b7280;font-size:11px}
.snap-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:10px}
.snap-item{background:#1f2937;border-radius:6px;padding:8px 12px;font-size:12px}
.snap-label{color:#6b7280;margin-bottom:2px;font-size:11px}
.snap-before{color:#9ca3af}
.snap-after{color:#34d399;font-weight:600}
.snap-arrow{color:#6b7280;margin:0 4px}
.no-snap{background:#450a0a;border:1px solid #7f1d1d;border-radius:6px;padding:8px 12px;color:#fca5a5;font-size:12px;margin-bottom:10px}
.items-list{font-size:12px;color:#9ca3af}
.confirm-box{background:#1c1917;border:1px solid #dc2626;border-radius:8px;padding:16px;margin-top:16px}
.confirm-box p{margin-bottom:12px;color:#fca5a5}
.alert{border-radius:6px;padding:10px 16px;margin-bottom:16px;font-weight:600}
.alert-ok{background:#14532d;color:#86efac;border:1px solid #166534}
.alert-err{background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d}
.diff-pos{color:#34d399}.diff-neg{color:#f87171}.diff-zero{color:#6b7280}
.note{font-size:11px;color:#6b7280;margin-top:6px}
</style>
</head>
<body>

<h1>Gacha Aniversario · Panel de Administración</h1>

<?php if ($revert_msg): $parts = explode(':', $revert_msg, 2); ?>
<div class="alert <?= $parts[0]==='success'?'alert-ok':'alert-err' ?>"><?= htmlspecialchars($parts[1]??$revert_msg) ?></div>
<?php endif; ?>

<!-- ── Resumen global ─────────────────────────────────────────── -->
<div class="stats">
    <div class="stat"><div class="stat-label">Usuarios</div><div class="stat-value"><?= $total_usuarios ?></div></div>
    <div class="stat"><div class="stat-label">Tiradas Berries</div><div class="stat-value"><?= number_format($total_tb,0,',','.') ?></div></div>
    <div class="stat"><div class="stat-label">Tiradas Kuros</div><div class="stat-value"><?= number_format($total_tk,0,',','.') ?></div></div>
    <div class="stat"><div class="stat-label">En el límite</div><div class="stat-value"><?= $usuarios_cap ?></div></div>
</div>

<h2>Tiradas por usuario</h2>
<div style="margin-bottom:12px"><input type="text" id="filtro" placeholder="Filtrar por UID o nombre…" oninput="filtrar()"></div>
<div class="tbl-wrap"><table id="tabla-usuarios">
<thead><tr>
    <th onclick="ordenar(0)">UID</th><th onclick="ordenar(1)">Usuario</th>
    <th onclick="ordenar(2)">T.Berries</th><th onclick="ordenar(3)">Tier</th>
    <th onclick="ordenar(4)">Coste x1</th><th onclick="ordenar(5)">Coste x10</th>
    <th onclick="ordenar(6)">Restantes</th><th onclick="ordenar(7)">T.Kuros</th>
</tr></thead>
<tbody>
<?php foreach ($resumen as $r):
    $tc=$r['tier']>=30?'tier-high':($r['tier']>=15?'tier-mid':($r['tier']>0?'tier-low':'tier-0'));
?>
<tr onclick="verUsuario(<?=$r['uid']?>)" data-uid="<?=$r['uid']?>" data-nombre="<?=strtolower($r['username'])?>">
    <td class="uid-cell"><?=$r['uid']?></td>
    <td class="user-cell"><?=$r['username']?></td>
    <td><?=number_format($r['tb'],0,',','.')?></td>
    <td class="<?=$tc?>"><?=$r['tier']?></td>
    <td><?=number_format($r['costo_x1'],0,',','.')?></td>
    <td><?=number_format($r['costo_x10'],0,',','.')?></td>
    <td><?=$r['restantes']===0?'<span class="cap">Agotado</span>':$r['restantes']?></td>
    <td><?=number_format($r['tk'],0,',','.')?></td>
</tr>
<?php endforeach; ?>
<?php if(empty($resumen)): ?><tr><td colspan="8" class="empty">Sin tiradas desde el reset.</td></tr><?php endif; ?>
</tbody></table></div>

<!-- ── Detalle de usuario ─────────────────────────────────────── -->
<?php if ($view_uid && $view_user): ?>
<div class="panel" id="panel-detalle">
    <div class="panel-header">
        <h2><?=$view_user['username']?> <span style="color:#6b7280;font-weight:400;font-size:12px">(UID <?=$view_uid?>)</span></h2>
        <button class="close-btn" onclick="cerrarPanel()">✕ Cerrar</button>
    </div>
    <div class="meta-grid">
        <div class="meta-item"><div class="meta-label">Tiradas Berries</div><div class="meta-value"><?=number_format($view_user['tb'],0,',','.')?></div></div>
        <div class="meta-item"><div class="meta-label">Tier actual</div><div class="meta-value <?=$view_user['tier']>=30?'tier-high':($view_user['tier']>=15?'tier-mid':'tier-low')?>"><?=$view_user['tier']?></div></div>
        <div class="meta-item"><div class="meta-label">Coste x1</div><div class="meta-value"><?=number_format($view_user['costo_x1'],0,',','.')?></div></div>
        <div class="meta-item"><div class="meta-label">Coste x10</div><div class="meta-value"><?=number_format($view_user['costo_x10'],0,',','.')?></div></div>
        <div class="meta-item"><div class="meta-label">Restantes</div><div class="meta-value"><?=$view_user['restantes']?></div></div>
        <div class="meta-item"><div class="meta-label">Tiradas Kuros</div><div class="meta-value"><?=number_format($view_user['tk'],0,',','.')?></div></div>
    </div>
    <h2>Últimos <?=count($historial)?> premios</h2>
    <?php if(empty($historial)): ?><p class="empty">Sin premios registrados.</p><?php else: ?>
    <div class="tbl-wrap"><table class="hist-table">
    <thead><tr><th>Banner</th><th>Tipo</th><th>Premio</th><th>Fecha</th></tr></thead>
    <tbody>
    <?php foreach($historial as $h): ?>
    <tr>
        <td><?=$h['banner']==='berries'?'<span class="badge bb">Berries</span>':'<span class="badge bk">Kuros</span>'?></td>
        <td><?=htmlspecialchars($h['premio_type'])?></td>
        <td class="premio-<?=htmlspecialchars($h['premio_type'])?>"><?=htmlspecialchars($h['premio_nombre'])?></td>
        <td style="color:#6b7280"><?=date('d/m/Y H:i:s',(int)$h['fecha'])?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Revert ─────────────────────────────────────────────────── -->
<div class="revert-box">
    <h2>Revertir cambios desde una fecha</h2>
    <p style="color:#9ca3af;font-size:12px;margin-top:6px">
        Introduce la hora en <strong>hora de Madrid</strong>. El revert:<br>
        1. Restaura la ficha de cada usuario al último snapshot de <code>mybb_audit_op_fichas</code> anterior a esa hora (solo columnas compartidas)<br>
        2. Elimina del inventario los ítems recibidos vía gacha desde esa hora<br>
        3. Borra los registros de log de gacha desde esa hora
    </p>
    <form method="get" action="<?=THIS_SCRIPT?>">
        <input type="hidden" name="action" value="preview">
        <div class="form-row" style="margin-top:14px">
            <div>
                <div class="form-label">Desde (hora Madrid)</div>
                <input type="datetime-local" name="dt" value="<?=htmlspecialchars($revert_input)?>" required>
            </div>
            <div>
                <div class="form-label">UID concreto (vacío = todos)</div>
                <input type="number" name="uid_filter" min="1" value="<?=$uid_filter>0?$uid_filter:''?>" placeholder="Opcional" style="width:130px">
            </div>
            <button type="submit" class="btn btn-blue">Vista previa</button>
        </div>
    </form>

    <?php if ($action === 'preview' && !$revert_msg): ?>
    <h2 style="margin-top:24px">Vista previa — <?=count($preview_data)?> usuario(s) afectados<?=$uid_filter>0?" (filtrado: UID $uid_filter)":""?></h2>
    <p style="color:#9ca3af;font-size:12px;margin-bottom:14px">
        Desde <strong><?=htmlspecialchars($desde_madrid)?></strong> (Madrid) → BD: <strong><?=htmlspecialchars($desde_db)?></strong>
        <?php if($uid_filter>0):?> · Solo UID <strong><?=$uid_filter?></strong><?php endif;?>
    </p>

    <?php if (empty($preview_data)): ?>
    <p class="empty">No hay tiradas desde esa fecha y hora.</p>
    <?php else: ?>

    <?php foreach ($preview_data as $u): ?>
    <div class="preview-row">
        <div class="preview-row-header">
            <span class="preview-username"><?=$u['username']?></span>
            <span class="preview-uid">UID <?=$u['uid']?></span>
            <span style="color:#6b7280;font-size:11px">· <?=$u['tb']?> tiradas berries, <?=$u['tk']?> kuros</span>
        </div>

        <?php if (!$u['fichas_ok']): ?>
        <div class="no-snap">⚠ No se pudo cargar el backup de fichas. La ficha NO se restaurará — solo se limpiarán inventario y logs.</div>
        <?php elseif (!$u['bak_ficha']): ?>
        <div class="no-snap">⚠ Esta UID no existe en el backup del 06/07. La ficha NO se restaurará.</div>
        <?php else: ?>
        <p class="note" style="margin-bottom:8px">
            Primera tirada: <strong><?=$u['primera_tirada']?></strong> (hora BD) ·
            Backup: <strong>06/07/2026 00:17</strong> ·
            <?=count($u['diff'])?> campo(s) cambian
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
        <?php foreach (['berries'=>'Berries','nika'=>'Nikas','kuro'=>'Kuros'] as $_fc=>$_label):
            $bak_v  = $u['bak_ficha'][$_fc] ?? '?';
            $curr_v = $u['curr_currencies'][$_fc] ?? '?';
            $changed = (string)$bak_v !== (string)$curr_v;
        ?>
        <div class="snap-item" style="min-width:160px">
            <div class="snap-label"><?=$_label?></div>
            <?php if ($changed): ?>
                <span class="snap-before"><?=number_format((int)$bak_v,0,',','.')?></span>
                <span class="snap-arrow">→</span>
                <span class="snap-after"><?=number_format((int)$curr_v,0,',','.')?></span>
            <?php else: ?>
                <span style="color:#6b7280"><?=number_format((int)$curr_v,0,',','.')?> (sin cambio)</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?></div>
        <?php if (empty($u['diff'])): ?>
        <p class="note" style="color:#34d399">✓ La ficha actual ya coincide con el snapshot (sin cambios en columnas compartidas).</p>
        <?php else: ?>
        <div class="snap-grid">
        <?php foreach ($u['diff'] as $d): ?>
            <div class="snap-item">
                <div class="snap-label"><?=htmlspecialchars($d['col'])?></div>
                <?php if (!empty($d['json'])): ?>
                    <span class="snap-before" style="font-style:italic">JSON actual</span>
                    <span class="snap-arrow">→</span>
                    <span class="snap-after" style="font-style:italic">JSON snapshot</span>
                <?php else: ?>
                    <span class="snap-before" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle"><?=htmlspecialchars($d['curr'])?></span>
                    <span class="snap-arrow">→</span>
                    <span class="snap-after" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle"><?=htmlspecialchars($d['snap'])?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$u['backup_ok']): ?>
        <div class="no-snap">⚠ No se encontró el archivo de backup del inventario (<code>restore_fichas_inventario_20260706.sql</code>). No se puede calcular el diff de ítems.</div>
        <?php else:
            $to_remove  = array_filter($u['items'], fn($d) => $d['curr'] > $d['bak']);
            $to_restore = array_filter($u['items'], fn($d) => $d['curr'] < $d['bak']);
        ?>
        <?php if ($to_remove): ?>
        <div class="items-list" style="margin-bottom:4px">
            <strong style="color:#f87171">A eliminar/reducir:</strong>
            <?=implode(', ', array_map(fn($id,$d)=>"<code>".htmlspecialchars($id)."</code> ".($d['bak']>0?"({$d['curr']}→{$d['bak']})":"×{$d['curr']} (nuevo)"), array_keys($to_remove), $to_remove))?>
        </div>
        <?php endif; ?>
        <?php if ($to_restore): ?>
        <div class="items-list" style="margin-bottom:4px">
            <strong style="color:#34d399">A restaurar/añadir:</strong>
            <?=implode(', ', array_map(fn($id,$d)=>"<code>".htmlspecialchars($id)."</code> ".($d['curr']>0?"({$d['curr']}→{$d['bak']})":"×{$d['bak']} (faltaba)"), array_keys($to_restore), $to_restore))?>
        </div>
        <?php endif; ?>
        <?php if (!$to_remove && !$to_restore): ?>
        <div class="items-list">Inventario igual al backup (sin cambios en ítems no personalizados).</div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="confirm-box">
        <p>¿Confirmas el revert? Esta acción restaurará fichas y limpiará inventario y logs. No se puede deshacer.</p>
        <form method="post" action="<?=THIS_SCRIPT?>">
            <input type="hidden" name="action" value="revert">
            <input type="hidden" name="dt" value="<?=htmlspecialchars($revert_input)?>">
            <input type="hidden" name="uid_filter" value="<?=$uid_filter?>">
            <input type="hidden" name="confirmed" value="1">
            <button type="submit" class="btn btn-red"><?=$uid_filter>0?"Ejecutar revert (UID $uid_filter)":"Ejecutar revert (todos)"?></button>
            <a href="<?=THIS_SCRIPT?>" class="btn btn-gray" style="margin-left:8px;text-decoration:none">Cancelar</a>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
var sortCol=-1,sortAsc=true;
function filtrar(){var q=document.getElementById('filtro').value.toLowerCase();document.querySelectorAll('#tabla-usuarios tbody tr').forEach(function(tr){tr.style.display=(!q||tr.dataset.uid.includes(q)||tr.dataset.nombre.includes(q))?'':'none';});}
function ordenar(col){var tbody=document.querySelector('#tabla-usuarios tbody');var rows=Array.from(tbody.querySelectorAll('tr'));if(sortCol===col)sortAsc=!sortAsc;else{sortCol=col;sortAsc=true;}rows.sort(function(a,b){var va=a.cells[col]?a.cells[col].textContent.replace(/[\.+\,]/g,'').trim():'';var vb=b.cells[col]?b.cells[col].textContent.replace(/[\.+\,]/g,'').trim():'';var na=parseFloat(va),nb=parseFloat(vb);var cmp=(!isNaN(na)&&!isNaN(nb))?(na-nb):va.localeCompare(vb,'es');return sortAsc?cmp:-cmp;});rows.forEach(function(r){tbody.appendChild(r);});document.querySelectorAll('th').forEach(function(th,i){th.className=i===col?(sortAsc?'sorted-asc':'sorted-desc'):'';});}
function verUsuario(uid){document.querySelectorAll('#tabla-usuarios tbody tr').forEach(function(tr){tr.classList.toggle('active',parseInt(tr.dataset.uid)===uid);});window.location.href='<?=THIS_SCRIPT?>?u='+uid;}
function cerrarPanel(){window.location.href='<?=THIS_SCRIPT?>';}
<?php if($view_uid):?>document.querySelectorAll('#tabla-usuarios tbody tr').forEach(function(tr){if(parseInt(tr.dataset.uid)===<?=$view_uid?>)tr.classList.add('active');});<?php endif;?>
</script>
</body>
</html>
<?php exit; ?>
