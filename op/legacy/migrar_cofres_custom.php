<?php
/**
 * TEMPORAL — migra las filas tipo='Custom' de mybb_op_cofres del código de
 * texto viejo (switch de procesarCustomRecompensa() en opg/tirada_cofre.php)
 * a la columna custom_data (JSON) nueva. Ver docs/100_Requirements_Cofres.md
 * (4.2) y docs/300_ImplementationPlan_Cofres.md (Tarea 2).
 *
 * Se corre UNA SOLA VEZ, antes de poner opg/tirada_cofre2.php en producción.
 * Súbelo, ábrelo una vez logueado como admin, revisá el reporte (sobre todo
 * la lista de "sin mapear", si aparece alguna), y BÓRRALO del servidor (no lo
 * subas a git).
 *
 * Requiere que docs/cofres_custom_data_migration.sql ya se haya ejecutado
 * (la columna custom_data tiene que existir).
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'migrar_cofres_custom.php');
require_once "./../../global.php";

if (!is_admin((int) $mybb->user['uid'])) {
    die('No autorizado.');
}

header('Content-Type: text/plain; charset=utf-8');

// Traducción 1 a 1 de cada case del switch viejo
// (opg/tirada_cofre.php:433-547). Las claves ausentes del array equivalen a
// 0/ninguno en custom_data — no hace falta escribir ceros explícitos.
$mapa = [
    // KTC001
    '1N'                   => ['nikas' => 1],
    '1000B'                => ['berries' => 1000],
    '1E'                   => ['experiencia' => 1],
    '10PO'                 => ['puntos_oficio' => 10],
    '1N1000B1E10PO'        => ['nikas' => 1, 'berries' => 1000, 'experiencia' => 1, 'puntos_oficio' => 10],
    'JACKPOT'              => ['nikas' => 10, 'berries' => 1000000, 'experiencia' => 10, 'puntos_oficio' => 100, 'objeto_bonus' => 'LLST001', 'objeto_bonus_cantidad' => 1],
    // CFR001
    '5N'                   => ['nikas' => 5],
    '200KB'                => ['berries' => 200000],
    '20E'                  => ['experiencia' => 20],
    '100PO'                => ['puntos_oficio' => 100],
    '3N100KB50PO10E'       => ['nikas' => 3, 'berries' => 100000, 'puntos_oficio' => 50, 'experiencia' => 10],
    '10N'                  => ['nikas' => 10],
    '5N200KB100PO20E'      => ['nikas' => 5, 'berries' => 200000, 'puntos_oficio' => 100, 'experiencia' => 20],
    // legacy alias (no es moneda: daba el objeto CFR001 x2)
    'CFR001X2'             => ['objeto_bonus' => 'CFR001', 'objeto_bonus_cantidad' => 2],
    // CFR002
    '500KB'                => ['berries' => 500000],
    '40E'                  => ['experiencia' => 40],
    '250PO'                => ['puntos_oficio' => 250],
    '5N250KB125PO20E'      => ['nikas' => 5, 'berries' => 250000, 'puntos_oficio' => 125, 'experiencia' => 20],
    '15N'                  => ['nikas' => 15],
    '10N500KB250PO40E'     => ['nikas' => 10, 'berries' => 500000, 'puntos_oficio' => 250, 'experiencia' => 40],
    // CFR003
    '1MB'                  => ['berries' => 1000000],
    '75E'                  => ['experiencia' => 75],
    '500PO'                => ['puntos_oficio' => 500],
    '8N500KB250PO40E'      => ['nikas' => 8, 'berries' => 500000, 'puntos_oficio' => 250, 'experiencia' => 40],
    '25N'                  => ['nikas' => 25],
    '15N1MB500PO75E'       => ['nikas' => 15, 'berries' => 1000000, 'puntos_oficio' => 500, 'experiencia' => 75],
    // CFR004
    '20N'                  => ['nikas' => 20],
    '2500KB'               => ['berries' => 2500000],
    '100E'                 => ['experiencia' => 100],
    '750PO'                => ['puntos_oficio' => 750],
    '10N1250KB375PO50E'    => ['nikas' => 10, 'berries' => 1250000, 'puntos_oficio' => 375, 'experiencia' => 50],
    '35N'                  => ['nikas' => 35],
    '20N2500KB750PO100E'   => ['nikas' => 20, 'berries' => 2500000, 'puntos_oficio' => 750, 'experiencia' => 100],
    // CFR005
    '30N'                  => ['nikas' => 30],
    '7MB'                  => ['berries' => 7000000],
    '125E'                 => ['experiencia' => 125],
    '1KPO'                 => ['puntos_oficio' => 1000],
    '15N3500KB500PO70E'    => ['nikas' => 15, 'berries' => 3500000, 'puntos_oficio' => 500, 'experiencia' => 70],
    '45N'                  => ['nikas' => 45],
    '30N7MB1000PO125E'     => ['nikas' => 30, 'berries' => 7000000, 'puntos_oficio' => 1000, 'experiencia' => 125],
    // CFR006
    '40N'                  => ['nikas' => 40],
    '15MB'                 => ['berries' => 15000000],
    '150E'                 => ['experiencia' => 150],
    '1250PO'               => ['puntos_oficio' => 1250],
    '20N7500KB625PO75E'    => ['nikas' => 20, 'berries' => 7500000, 'puntos_oficio' => 625, 'experiencia' => 75],
    '55N'                  => ['nikas' => 55],
    '40N15M1250PO150E'     => ['nikas' => 40, 'berries' => 15000000, 'puntos_oficio' => 1250, 'experiencia' => 150],
    // CFR007
    '50N'                  => ['nikas' => 50],
    '30MB'                 => ['berries' => 30000000],
    '200E'                 => ['experiencia' => 200],
    '1500PO'               => ['puntos_oficio' => 1500],
    '25N15M750PO100E'      => ['nikas' => 25, 'berries' => 15000000, 'puntos_oficio' => 750, 'experiencia' => 100],
    '65N'                  => ['nikas' => 65],
    '50N30MBV1500PO200E'   => ['nikas' => 50, 'berries' => 30000000, 'puntos_oficio' => 1500, 'experiencia' => 200],
    // CFR008
    '75N'                  => ['nikas' => 75],
    '75MB'                 => ['berries' => 75000000],
    '250E'                 => ['experiencia' => 250],
    '2000PO'               => ['puntos_oficio' => 2000],
    '40N40M1000PO125E'     => ['nikas' => 40, 'berries' => 40000000, 'puntos_oficio' => 1000, 'experiencia' => 125],
    '85N'                  => ['nikas' => 85],
    '75N75MB2000PO250E'    => ['nikas' => 75, 'berries' => 75000000, 'puntos_oficio' => 2000, 'experiencia' => 250],
    // CFR009
    '100N'                 => ['nikas' => 100],
    '250MB'                => ['berries' => 250000000],
    '350E'                 => ['experiencia' => 350],
    '2500PO'               => ['puntos_oficio' => 2500],
    '50N125M1250PO175E'    => ['nikas' => 50, 'berries' => 125000000, 'puntos_oficio' => 1250, 'experiencia' => 175],
    '120N'                 => ['nikas' => 120],
    '100N250MB2500PO350E'  => ['nikas' => 100, 'berries' => 250000000, 'puntos_oficio' => 2500, 'experiencia' => 350],
    // CFR010
    '150N'                 => ['nikas' => 150],
    '1000MB'               => ['berries' => 1000000000],
    '500E'                 => ['experiencia' => 500],
    '5000PO'               => ['puntos_oficio' => 5000],
    '150N1000MB500E5000PO' => ['nikas' => 150, 'berries' => 1000000000, 'experiencia' => 500, 'puntos_oficio' => 5000],
    // CFN002
    '5N250KB'              => ['nikas' => 5, 'berries' => 250000],
    '10N500KB'             => ['nikas' => 10, 'berries' => 500000],
    // CFN003
    '8N500KB'              => ['nikas' => 8, 'berries' => 500000],
    '15N1MB'               => ['nikas' => 15, 'berries' => 1000000],
    // CFN004
    '10N1250KB'            => ['nikas' => 10, 'berries' => 1250000],
    '20N2500KB'            => ['nikas' => 20, 'berries' => 2500000],
    // CFN005
    '15N3500KB'            => ['nikas' => 15, 'berries' => 3500000],
    '30N7MB'               => ['nikas' => 30, 'berries' => 7000000],
    // CFN006
    '20N7500KB'            => ['nikas' => 20, 'berries' => 7500000],
    '40N15M'               => ['nikas' => 40, 'berries' => 15000000],
    // CFN007
    '25N15M'               => ['nikas' => 25, 'berries' => 15000000],
    '50N30MB'              => ['nikas' => 50, 'berries' => 30000000],
    // CFN008
    '40N40M'               => ['nikas' => 40, 'berries' => 40000000],
    '75N75MB'              => ['nikas' => 75, 'berries' => 75000000],
];

/**
 * Patrón genérico que cubría el switch viejo por defecto (CFRxxxXN, INVxxxXN,
 * etc. — mismo regex que resolverObjetoId() en opg/tirada_cofre.php): daba
 * un objeto real, no moneda, así que se traduce igual que CFR001X2.
 */
function mapearGenerico($obj_id) {
    if (preg_match('/^(.+)X(\d+)$/i', $obj_id, $m)) {
        return ['objeto_bonus' => $m[1], 'objeto_bonus_cantidad' => intval($m[2])];
    }
    return null;
}

$query = $db->query("SELECT id, objeto_id FROM mybb_op_cofres WHERE tipo='Custom' AND custom_data IS NULL");

$migradas = 0;
$sin_mapear = [];

while ($fila = $db->fetch_array($query)) {
    $codigo = $fila['objeto_id'];
    $datos = $mapa[$codigo] ?? mapearGenerico($codigo);

    if ($datos === null) {
        $sin_mapear[] = $fila['id'] . ' (objeto_id=' . $codigo . ')';
        continue;
    }

    $json = $db->escape_string(json_encode($datos, JSON_UNESCAPED_UNICODE));
    $id = (int) $fila['id'];
    $db->query("UPDATE mybb_op_cofres SET custom_data = '{$json}' WHERE id = {$id}");
    $migradas++;
}

echo "Migración de recompensas Custom — mybb_op_cofres\n";
echo "=================================================\n\n";
echo "Filas migradas: {$migradas}\n";

if (count($sin_mapear) > 0) {
    echo "\nFilas SIN MAPEAR (" . count($sin_mapear) . "), revisar a mano:\n";
    foreach ($sin_mapear as $linea) {
        echo "  - {$linea}\n";
    }
} else {
    echo "\nNinguna fila quedó sin mapear.\n";
}

exit;
