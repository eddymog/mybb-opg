<?php
/**
 * JSON con todas las armas del foro (mybb_op_objetos, categoria 'armas'), igual que tecnicas_belicas_todas.php.
 *
 * Por defecto solo las armas base del catálogo. Las copias creadas por usuarios (objeto_id que empieza
 * por número o lleva "-", ver "Custom item IDs" en CLAUDE.md) se incluyen con ?incluir_custom=1.
 * "subcategorias" resume los valores distintos del campo subcategoria (cuántas armas hay de cada uno).
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'armas_todas.php');

require_once './../global.php';

$incluir_custom = $mybb->get_input('incluir_custom', MyBB::INPUT_INT) === 1;

$armas = array();
$subcategorias = array();
$consulta = $db->query("
    SELECT objeto_id, categoria, subcategoria, nombre, tier, berries, cantidadMaxima, dano, bloqueo, efecto, alcance,
           espacios, requisitos, escalado, descripcion, exclusivo, oficio, nivel, engastes, fusion_tipo, custom, comerciable
    FROM mybb_op_objetos
    WHERE LOWER(categoria) = 'armas'
    ORDER BY subcategoria ASC, CAST(tier AS UNSIGNED) ASC, nombre ASC, objeto_id ASC
");

while ($arma = $db->fetch_array($consulta)) {
    $id = (string) $arma['objeto_id'];
    $es_custom = preg_match('/^[0-9]/', $id) || strpos($id, '-') !== false;
    if ($es_custom && !$incluir_custom) { continue; }

    foreach (array('tier', 'berries', 'cantidadMaxima', 'espacios', 'exclusivo', 'nivel', 'engastes', 'custom', 'comerciable') as $campo) {
        $arma[$campo] = (int) $arma[$campo];
    }
    $armas[] = $arma;

    $sub = trim((string) $arma['subcategoria']);
    $subcategorias[$sub] = isset($subcategorias[$sub]) ? $subcategorias[$sub] + 1 : 1;
}
ksort($subcategorias);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'total' => count($armas),
    'subcategorias' => $subcategorias,
    'armas' => $armas,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
