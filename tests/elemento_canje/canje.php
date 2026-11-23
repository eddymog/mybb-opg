<?php
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/elemento_canje.php';

$failures = array();
function el_expect($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$vacio = '{ "Electro": 0, "Piro": 0, "Cryo": 0, "Aqua": 0,  "Aero": 0 }';
$dos = '{"Piro":0,"Aqua":0,"Aero":1,"Electro":1,"Cryo":0}';
$todos = '{"Piro":1,"Aqua":1,"Aero":1,"Electro":1,"Cryo":1}';

el_expect(opg_elemento_canje_disponibles($vacio) === array('Piro', 'Aqua', 'Aero', 'Electro', 'Cryo'), 'Con ninguno, los 5 estan disponibles.');
el_expect(opg_elemento_canje_disponibles($dos) === array('Piro', 'Aqua', 'Cryo'), 'Con Aero y Electro, quedan Piro, Aqua y Cryo.');
el_expect(opg_elemento_canje_disponibles($todos) === array(), 'Con los 5 no queda ninguno.');

// Datos raros de la base de datos
el_expect(count(opg_elemento_canje_disponibles('null')) === 5, 'JSON null: todos disponibles.');
el_expect(count(opg_elemento_canje_disponibles('')) === 5, 'Cadena vacia: todos disponibles.');
el_expect(count(opg_elemento_canje_disponibles('{"Piro":1}')) === 4, 'Faltan claves: cuentan como no desbloqueadas.');
el_expect(count(opg_elemento_canje_disponibles('{"Piro":"1"}')) === 4, 'Un "1" escrito como texto cuenta como desbloqueado (asi no se canjea dos veces).');

// Elegir un elemento libre
$r = opg_elemento_canje_validar($dos, 'Cryo');
el_expect($r['ok'] && $r['tids'] === array('CRY001', 'CRY301'), 'Cryo da CRY001 y CRY301.');
$j = json_decode($r['elementos_json'], true);
el_expect($j === array('Piro' => 0, 'Aqua' => 0, 'Aero' => 1, 'Electro' => 1, 'Cryo' => 1), 'El JSON conserva Aero y Electro y suma Cryo.');
$r = opg_elemento_canje_validar($vacio, 'Electro');
el_expect($r['ok'] && $r['tids'] === array('ELE001', 'ELE301'), 'Electro da ELE001 y ELE301.');
el_expect(json_decode($r['elementos_json'], true)['Electro'] === 1 && array_sum(json_decode($r['elementos_json'], true)) === 1, 'Solo se activa el elegido.');
$r = opg_elemento_canje_validar('null', 'Piro');
el_expect($r['ok'] && $r['tids'] === array('PIR001', 'PIR301'), 'Con JSON null tambien funciona.');

// Rechazos
el_expect(!opg_elemento_canje_validar($dos, 'Aero')['ok'], 'No se puede elegir un elemento que ya tiene.');
el_expect(!opg_elemento_canje_validar($todos, 'Piro')['ok'], 'Con los 5 no se puede canjear.');
el_expect(opg_elemento_canje_validar($todos, 'Piro')['error'] === 'Ya tienes los 5 elementos desbloqueados.', 'Con los 5 explica el motivo.');
el_expect(!opg_elemento_canje_validar($dos, '')['ok'], 'Sin eleccion no es valido.');
el_expect(!opg_elemento_canje_validar($dos, 'Fuego')['ok'], 'Un elemento inventado no es valido.');
el_expect(!opg_elemento_canje_validar($dos, "Piro'; DROP")['ok'], 'Una inyeccion no es valida.');
el_expect(!opg_elemento_canje_validar($dos, array('Piro'))['ok'], 'Una eleccion que no es texto no es valida.');

if ($failures) {
    foreach ($failures as $m) echo "FALLO: $m\n";
    exit(1);
}
echo "OK elemento_canje\n";
