<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'reparar_inventario_oficios.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$dry_run = !isset($_GET['confirmar']);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Reparar oficios de inventario</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;}  .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Reparación de <code>mybb_op_inventario.oficios</code> — clave/sub corruptos</h2>";
echo "<p class='info'>Corrige entradas donde la clave de oficio quedó como una sub-especialización (p.ej. \"Mayorista\") en vez de la familia (\"Recolector\"), y donde <code>sub</code> quedó como <code>[]</code> vacío — el bug de <code>darObjeto()</code> en crafteo.php (ya corregido) que rompía el JS del inventario.</p>";

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Añade <code>?confirmar=1</code> a la URL para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

// Mismo mapa de sub-especializaciones por defecto que reparar_oficios_json.php
$OFICIO_SUBS = [
    'Artesano'     => ['Herrero' => 0, 'Modista' => 0],
    'Médico'       => ['Farmacólogo' => 0, 'Doctor' => 0],
    'Navegante'    => ['Cartógrafo' => 0, 'Timonel' => 0],
    'Inventor'     => ['Biólogo' => 0, 'Ingeniero' => 0],
    'Carpintero'   => ['Astillero' => 0, 'Constructor' => 0],
    'Cocinero'     => ['Chef' => 0, 'Aprovisionador' => 0],
    'Mercader'     => ['Comerciante' => 0, 'Contrabandista' => 0],
    'Investigador' => ['Periodista' => 0, 'Arqueólogo' => 0],
    'Aventurero'   => ['Cazador' => 0, 'Domador' => 0],
    'Recolector'   => ['Agreste' => 0, 'Mayorista' => 0],
];

$q = $db->query("SELECT id, objeto_id, uid, oficios FROM mybb_op_inventario WHERE oficios LIKE '%\"sub\":[]%' OR oficios LIKE '%\"sub\": []%'");

$total = 0;
$fixed = 0;
$skipped = 0;

// Cache de oficios de ficha por uid, para no repetir queries si un mismo dueño tiene varias filas corruptas
$ficha_oficios_cache = [];

while ($row = $db->fetch_array($q)) {
    $total++;
    $inv_id  = (int)$row['id'];
    $obj_id  = htmlspecialchars($row['objeto_id']);
    $owner   = (int)$row['uid'];
    $decoded = json_decode($row['oficios'], true);

    if (!is_array($decoded) || empty($decoded)) {
        echo "<p class='err'>Inv #$inv_id ($obj_id, uid $owner): JSON inválido o vacío, saltado.</p>";
        $skipped++;
        continue;
    }

    if (!array_key_exists($owner, $ficha_oficios_cache)) {
        $f = $db->fetch_array($db->query("SELECT oficios FROM mybb_op_fichas WHERE fid='$owner' LIMIT 1"));
        $ficha_oficios_cache[$owner] = $f ? (json_decode($f['oficios'], true) ?: []) : [];
    }
    $owner_oficios = $ficha_oficios_cache[$owner];

    $nuevo   = [];
    $changed = false;

    foreach ($decoded as $key => $data) {
        if (!is_array($data)) { $nuevo[$key] = $data; continue; }

        $sub_vacio = array_key_exists('sub', $data) && is_array($data['sub']) && empty($data['sub']);
        $familia   = oficioFamilia($key);

        if (!$sub_vacio && $familia === $key) {
            // Esta entrada ya está bien — se conserva tal cual
            $nuevo[$key] = $data;
            continue;
        }

        $changed = true;

        // Preferir los datos actuales de la ficha del dueño si los tiene
        if (isset($owner_oficios[$familia]) && !empty($owner_oficios[$familia]['sub'])) {
            $nivel_restaurado = (int)($owner_oficios[$familia]['nivel'] ?? 1);
            $sub_restaurado   = $owner_oficios[$familia]['sub'];
        } else {
            $nivel_restaurado = max(1, (int)($data['nivel'] ?? 1));
            $sub_restaurado   = $OFICIO_SUBS[$familia] ?? [];
        }

        // Si ya se había restaurado otra entrada de la misma familia en este registro, quedarse con la de mayor nivel
        if (isset($nuevo[$familia]) && $nivel_restaurado <= (int)($nuevo[$familia]['nivel'] ?? 0)) {
            continue;
        }

        $nuevo[$familia] = ['sub' => (object)$sub_restaurado, 'nivel' => $nivel_restaurado];

        echo "<p class='warn'>Inv #$inv_id ($obj_id, uid $owner): <b>$key</b> → <b>$familia</b>, sub:[] → "
            . json_encode($sub_restaurado, JSON_UNESCAPED_UNICODE) . ", nivel: " . ($data['nivel'] ?? 0) . " → $nivel_restaurado</p>";
    }

    if (!$changed) {
        echo "<p class='info'>Inv #$inv_id ($obj_id, uid $owner): sin cambios necesarios.</p>";
        $skipped++;
        continue;
    }

    $new_json = json_encode($nuevo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$dry_run) {
        $db->query("UPDATE mybb_op_inventario SET oficios='" . $db->escape_string($new_json) . "' WHERE id='$inv_id'");
        echo "<p class='ok'>Inv #$inv_id ($obj_id, uid $owner): actualizado.</p>";
    } else {
        echo "<p class='info'>Inv #$inv_id ($obj_id, uid $owner): JSON resultante sería → <code>" . htmlspecialchars($new_json) . "</code></p>";
    }
    $fixed++;
}

echo "<hr><p>Total encontrados: $total | Reparados: $fixed | Sin cambios/saltados: $skipped</p>";

if ($dry_run && $fixed > 0) {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios</a></p>";
} elseif ($fixed === 0 && $total === 0) {
    echo "<p class='ok'>No se encontraron entradas de inventario con oficios corruptos. ¡Todo limpio!</p>";
}

echo "</body></html>";
