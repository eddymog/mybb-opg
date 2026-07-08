<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'reparar_oficios_json.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
if (!is_staff($uid)) { die("Acceso denegado."); }

$OFICIO_SUBS = [
    'Artesano'    => ['Herrero' => 0, 'Modista' => 0],
    'Médico'      => ['Farmacólogo' => 0, 'Doctor' => 0],
    'Navegante'   => ['Cartógrafo' => 0, 'Timonel' => 0],
    'Inventor'    => ['Biólogo' => 0, 'Ingeniero' => 0],
    'Carpintero'  => ['Astillero' => 0, 'Constructor' => 0],
    'Cocinero'    => ['Chef' => 0, 'Aprovisionador' => 0],
    'Mercader'    => ['Comerciante' => 0, 'Contrabandista' => 0],
    'Investigador'=> ['Periodista' => 0, 'Arqueólogo' => 0],
    'Aventurero'  => ['Cazador' => 0, 'Domador' => 0],
    'Recolector'  => ['Agreste' => 0, 'Mayorista' => 0],
];

$dry_run = !isset($_GET['confirmar']);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Reparar oficios JSON</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;}  .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;}</style></head><body>";
echo "<h2>Reparación de <code>oficios</code> JSON — sub:[] corrupto</h2>";

if ($dry_run) {
    echo "<p class='warn'>MODO SIMULACIÓN — no se modifica nada. Añade <code>?confirmar=1</code> a la URL para aplicar los cambios.</p>";
} else {
    echo "<p class='ok'>MODO ESCRITURA — aplicando cambios en la base de datos.</p>";
}

$q = $db->query("SELECT fid, nombre, oficios FROM mybb_op_fichas WHERE oficios LIKE '%\"sub\":[]%' OR oficios LIKE '%\"sub\": []%'");

$total = 0;
$fixed = 0;
$skipped = 0;

while ($row = $db->fetch_array($q)) {
    $total++;
    $fid    = (int)$row['fid'];
    $nombre = htmlspecialchars($row['nombre']);
    $decoded = json_decode($row['oficios'], true);

    if (!is_array($decoded)) {
        echo "<p class='err'>FID $fid ($nombre): JSON inválido, saltado.</p>";
        $skipped++;
        continue;
    }

    $changed = false;
    foreach ($decoded as $oficio_name => &$oficio_data) {
        if (!array_key_exists('sub', $oficio_data)) continue;
        if (!is_array($oficio_data['sub']) || !empty($oficio_data['sub'])) continue;

        // sub es [] vacío — restaurar subs por defecto
        if (isset($OFICIO_SUBS[$oficio_name])) {
            $restored = $OFICIO_SUBS[$oficio_name];
            echo "<p class='warn'>FID $fid ($nombre): <b>$oficio_name</b>.sub era [] → restaurado a <code>"
                . json_encode($restored, JSON_UNESCAPED_UNICODE) . "</code></p>";
            $oficio_data['sub'] = (object)$restored;
            $changed = true;
        } else {
            echo "<p class='err'>FID $fid ($nombre): <b>$oficio_name</b>.sub es [] pero oficio no reconocido — saltado.</p>";
            $oficio_data['sub'] = new stdClass();
            $changed = true;
        }
    }
    unset($oficio_data);

    if (!$changed) {
        echo "<p class='info'>FID $fid ($nombre): sin cambios necesarios.</p>";
        $skipped++;
        continue;
    }

    $new_json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$dry_run) {
        $db->query("UPDATE mybb_op_fichas SET oficios='".$db->escape_string($new_json)."' WHERE fid='$fid'");
        echo "<p class='ok'>FID $fid ($nombre): actualizado.</p>";
    } else {
        echo "<p class='info'>FID $fid ($nombre): JSON resultante sería → <code>" . htmlspecialchars($new_json) . "</code></p>";
    }
    $fixed++;
}

echo "<hr><p>Total encontrados: $total | Reparados: $fixed | Saltados: $skipped</p>";

if ($dry_run && $fixed > 0) {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Aplicar cambios</a></p>";
} elseif ($fixed === 0 && $total === 0) {
    echo "<p class='ok'>No se encontraron fichas con <code>sub:[]</code> corrupto. ¡Todo limpio!</p>";
}

echo "</body></html>";
