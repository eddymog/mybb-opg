<?php
/** php tests/banda_sonora/php.php: imprime, por cada caso, "id" o "" (la misma salida que js.js). */
require __DIR__ . '/../../inc/plugins/lib/banda_sonora.php';
$casos = json_decode(file_get_contents(__DIR__ . '/casos.json'), true);
$salida = array();
foreach ($casos as $c) {
    $r = opg_banda_sonora_normalizar($c[0]);
    $salida[] = $r['ok'] ? $r['id'] . '|' . $r['canonica'] : '';
}
echo json_encode($salida, JSON_UNESCAPED_UNICODE);
