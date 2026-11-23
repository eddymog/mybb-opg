<?php
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/arma_canje.php';

$failures = array();
function ar_expect($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

ar_expect(count(opg_arma_canje_familias()) === 24, 'Hay 24 familias de armas.');
ar_expect(count(array_unique(opg_arma_canje_familias())) === 24, 'No hay familias repetidas.');

for ($t = 1; $t <= 5; $t++) {
    ar_expect(opg_arma_canje_tier('TARM00' . $t) === $t, "TARM00$t es de Tier $t.");
    ar_expect(count(opg_arma_canje_opciones('TARM00' . $t)) === 24, "TARM00$t ofrece 24 armas.");
}
ar_expect(opg_arma_canje_opciones('TARM003')[0] === 'AMUM003', 'La primera arma de Tier 3 es AMUM003.');
ar_expect(in_array('INSG005', opg_arma_canje_opciones('TARM005'), true), 'INSG005 se ofrece con TARM005.');
ar_expect(in_array('ACCP002', opg_arma_canje_opciones('TARM002'), true), 'ACCP002 (cuatro letras) se ofrece con TARM002.');

foreach (array('TARM000', 'TARM006', 'TARM0011', 'tarm001', 'XTARM001', 'TARM001 ', 'THR001', '', null, array('TARM001')) as $malo) {
    ar_expect(opg_arma_canje_tier($malo) === null, 'No es un ticket de arma: ' . var_export($malo, true));
}
ar_expect(opg_arma_canje_opciones('THR001') === array(), 'Un objeto que no es ticket no ofrece armas.');

// Validaciones
$r = opg_arma_canje_validar('TARM003', 'LL003');
ar_expect($r['ok'] && $r['objeto_id'] === 'LL003' && $r['tier'] === 3, 'TARM003 canjea LL003.');
ar_expect(!opg_arma_canje_validar('TARM003', 'LL002')['ok'], 'TARM003 no canjea un arma de Tier 2.');
ar_expect(!opg_arma_canje_validar('TARM001', 'LL003')['ok'], 'TARM001 no canjea un arma de Tier 3.');
ar_expect(!opg_arma_canje_validar('TARM002', 'AMUM001')['ok'], 'TARM002 no canjea un arma de Tier 1.');
ar_expect(!opg_arma_canje_validar('TARM002', 'WAZ002')['ok'], 'No se canjea un arma que no esta en la lista.');
ar_expect(!opg_arma_canje_validar('TARM002', '')['ok'], 'Sin eleccion no es valido.');
ar_expect(!opg_arma_canje_validar('TARM002', "AMUM002'; DROP")['ok'], 'Una inyeccion no es valida.');
ar_expect(!opg_arma_canje_validar('TARM002', array('AMUM002'))['ok'], 'Una eleccion que no es texto no es valida.');
ar_expect(!opg_arma_canje_validar('THR001', 'AMUM001')['ok'], 'Un objeto que no es ticket no canjea nada.');

// Todas las IDs generadas existen en el catalogo exportado (si el archivo esta disponible)
$catalogo = MYBB_ROOT . 'docs/objetos_armas_todas.md';
if (is_readable($catalogo)) {
    $d = json_decode(file_get_contents($catalogo), true);
    $existentes = array();
    foreach ((array)($d['armas'] ?? array()) as $a) {
        $existentes[$a['objeto_id']] = true;
    }
    if ($existentes) {
        for ($t = 1; $t <= 5; $t++) {
            foreach (opg_arma_canje_opciones('TARM00' . $t) as $id) {
                ar_expect(isset($existentes[$id]), "El arma $id existe en el catalogo exportado.");
            }
        }
    }
}

if ($failures) {
    foreach ($failures as $m) echo "FALLO: $m\n";
    exit(1);
}
echo "OK arma_canje\n";
