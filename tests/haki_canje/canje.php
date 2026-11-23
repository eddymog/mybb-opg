<?php
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/haki_canje.php';

$failures = array();
function hk_expect($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

function hk_ficha($extra = array())
{
    return array_merge(array('nivel' => 20, 'camino' => 'Aventurero', 'hao' => -1, 'hao_chance' => 1, 'kenbun' => 2, 'buso' => 1), $extra);
}

// Requisitos de nivel (misma regla que la subida con Nikas)
hk_expect(opg_haki_canje_nivel_requerido(1, false) === 15, '1->2 sin camino Haki pide nivel 15.');
hk_expect(opg_haki_canje_nivel_requerido(1, true) === 10, '1->2 con camino Haki pide nivel 10.');
hk_expect(opg_haki_canje_nivel_requerido(6, false) === 50, '6->7 sin camino Haki pide nivel 50.');
hk_expect(opg_haki_canje_nivel_requerido(6, true) === 45, '6->7 con camino Haki pide nivel 45.');
hk_expect(opg_haki_canje_nivel_requerido(7, false) === 666, '7->8 sin camino Haki pide 666 (valor actual).');
hk_expect(opg_haki_canje_nivel_requerido(7, true) === 60, '7->8 con camino Haki pide nivel 60.');
hk_expect(opg_haki_canje_nivel_requerido(8, true) === null, 'El nivel 8 es el maximo.');
hk_expect(opg_haki_canje_nivel_requerido(0, true) === null, 'Un Haki no despertado no sube.');

// Sin Haki del Rey: suma una lanzada
$r = opg_haki_canje_validar(hk_ficha(array('hao' => -1, 'hao_chance' => 2)), '');
hk_expect($r['ok'] && $r['modo'] === 'tirada' && $r['col'] === 'hao_chance' && $r['nuevo'] === 3, 'Sin Haki del Rey suma 1 a hao_chance (2 -> 3).');
$r = opg_haki_canje_validar(hk_ficha(array('hao' => -1, 'hao_chance' => 0)), 'buso');
hk_expect($r['ok'] && $r['modo'] === 'tirada' && $r['nuevo'] === 1, 'Con 0 lanzadas tambien suma, y la eleccion se ignora.');

// Con Haki del Rey: se elige un Haki despertado
$f = hk_ficha(array('hao' => 1, 'nivel' => 20, 'kenbun' => 2, 'buso' => 1));
$info = opg_haki_canje_opciones($f);
hk_expect($info['modo'] === 'subir' && $info['puede'], 'Con Haki del Rey hay que elegir.');
hk_expect($info['opciones']['kenbun']['ok'] && $info['opciones']['kenbun']['siguiente'] === 3, 'Kenbun 2 -> 3 a nivel 20 es valido.');
hk_expect($info['opciones']['buso']['ok'] && $info['opciones']['buso']['siguiente'] === 2, 'Buso 1 -> 2 a nivel 20 es valido.');
hk_expect($info['opciones']['hao']['ok'] && $info['opciones']['hao']['siguiente'] === 2, 'Hao 1 -> 2 a nivel 20 es valido.');
$r = opg_haki_canje_validar($f, 'kenbun');
hk_expect($r['ok'] && $r['col'] === 'kenbun' && $r['actual'] === 2 && $r['nuevo'] === 3, 'Validar kenbun devuelve 2 -> 3.');

// Requisito de nivel no cumplido
$f = hk_ficha(array('hao' => 1, 'nivel' => 12, 'kenbun' => 2, 'buso' => 1));
$info = opg_haki_canje_opciones($f);
hk_expect(!$info['opciones']['kenbun']['ok'] && strpos($info['opciones']['kenbun']['motivo'], '20') !== false, 'Kenbun 2 -> 3 a nivel 12 pide nivel 20.');
hk_expect(!$info['opciones']['buso']['ok'], 'Buso 1 -> 2 a nivel 12 pide nivel 15.');
$r = opg_haki_canje_validar($f, 'kenbun');
hk_expect(!$r['ok'] && $r['error'] !== '', 'No se puede validar un Haki que no cumple el nivel.');

// Camino Haki abarata los requisitos
$f = hk_ficha(array('hao' => 1, 'nivel' => 12, 'camino' => 'Haki', 'kenbun' => 1, 'buso' => 1));
hk_expect(opg_haki_canje_opciones($f)['opciones']['buso']['ok'], 'Con camino Haki, buso 1 -> 2 a nivel 12 es valido (pide 10).');

// No despertados y maximo
$f = hk_ficha(array('hao' => 0, 'nivel' => 70, 'camino' => 'Haki', 'kenbun' => 0, 'buso' => 8));
$info = opg_haki_canje_opciones($f);
hk_expect($info['modo'] === 'subir' && !$info['puede'], 'Hao 0, kenbun 0 y buso 8: no hay nada que subir.');
hk_expect($info['opciones']['hao']['motivo'] === 'No despertado' && $info['opciones']['kenbun']['motivo'] === 'No despertado', 'Los no despertados lo indican.');
hk_expect($info['opciones']['buso']['motivo'] === 'Nivel máximo', 'El nivel 8 indica maximo.');
hk_expect(!opg_haki_canje_validar($f, 'hao')['ok'], 'No se valida un Haki no despertado.');

// Hao 0 (lo tiene sin despertar): puede subir otros despertados
$f = hk_ficha(array('hao' => 0, 'nivel' => 20, 'kenbun' => 2, 'buso' => 1));
hk_expect(opg_haki_canje_validar($f, 'kenbun')['ok'], 'Con hao 0 se puede subir un Haki despertado.');
hk_expect(!opg_haki_canje_validar($f, 'hao')['ok'], 'Con hao 0 no se sube hao.');

// Elecciones invalidas
$f = hk_ficha(array('hao' => 1));
hk_expect(!opg_haki_canje_validar($f, '')['ok'], 'Sin eleccion no es valido.');
hk_expect(!opg_haki_canje_validar($f, 'nika`; DROP')['ok'], 'Una columna inventada no es valida.');
hk_expect(!opg_haki_canje_validar($f, 'dominio_akuma')['ok'], 'Solo kenbun, buso y hao.');

if ($failures) {
    foreach ($failures as $m) echo "FALLO: $m\n";
    exit(1);
}
echo "OK haki_canje\n";
