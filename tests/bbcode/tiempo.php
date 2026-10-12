<?php
// php tests/bbcode/tiempo.php
define('IN_MYBB', 1);
$plugins = new class { function add_hook() {} };
require __DIR__ . '/../../inc/plugins/BBCustom_tiempo.php';
$fallos = 0;
function prueba($n, $a, $e) { global $fallos; if ($a !== $e) { $fallos++; echo "FALLO $n: " . var_export($a, true) . " != " . var_export($e, true) . "\n"; } else { echo "ok $n\n"; } }

$h = 3600;
$r = 2 * 86400 + 18 * $h + 5 * 60 + 3;
prueba('partes', BBCustom_tiempo_partes($r), array('d' => 2, 'h' => 18, 'm' => 5, 's' => 3, 'expirado' => false));
prueba('72h menos 5h32m', array_values(BBCustom_tiempo_partes(72 * $h - (5 * $h + 32 * 60))), array(2, 18, 28, 0, false));
prueba('cero expira', BBCustom_tiempo_partes(0)['expirado'], true);
prueba('negativo expira', BBCustom_tiempo_partes(-500)['expirado'], true);
prueba('etiqueta singular', array(BBCustom_tiempo_etiqueta('d', 1), BBCustom_tiempo_etiqueta('d', 0), BBCustom_tiempo_etiqueta('s', 2)), array('Día', 'Días', 'Segundos'));

$ahora = time();
$html = BBCustom_tiempo_html($ahora + $r, $ahora);
prueba('cifras con dos digitos', array(strpos($html, 'data-u="d">02<') !== false, strpos($html, 'data-u="h">18<') !== false, strpos($html, 'data-u="s">03<') !== false), array(true, true, true));
prueba('titulo vivo', strpos($html, 'Tiempo de la ronda') !== false, true);
prueba('sin saltos de linea', strpos($html, "\n"), false);
$exp = BBCustom_tiempo_html($ahora - 10, $ahora);
prueba('expirado', array(strpos($exp, 'opg-tiempo-expirado') !== false, strpos($exp, 'La ronda ha terminado') !== false), array(true, true));

$post = array('message' => 'Hola [tiempo=72] y [tiempo=1]', 'dateline' => $ahora - 5 * $h);
BBCustom_tiempo_run($post);
prueba('dos contadores', substr_count($post['message'], 'class="opg-tiempo-caja"'), 2);
prueba('el de 1h ya expiro', substr_count($post['message'], 'opg-tiempo-expirado"'), 1);
prueba('el de 72h: 2 dias 19 horas', strpos($post['message'], 'data-u="d">02<') !== false && strpos($post['message'], 'data-u="h">19<') !== false, true);
prueba('assets una vez', substr_count($post['message'], '<script>'), 1);
$p2 = array('message' => '[tiempo=1]', 'dateline' => $ahora);
BBCustom_tiempo_run($p2);
prueba('assets no se repiten en otro post', strpos($p2['message'], '<script>'), false);
$p3 = array('message' => '[tiempo=0] [tiempo=99999] [tiempo=abc] [tiempo=-5]', 'dateline' => $ahora);
BBCustom_tiempo_run($p3);
prueba('valores no validos quedan igual', $p3['message'], '[tiempo=0] [tiempo=99999] [tiempo=abc] [tiempo=-5]');
$p4 = array('message' => '[tiempo=24]');
BBCustom_tiempo_run($p4);
prueba('vista previa (sin dateline) cuenta desde ahora', strpos($p4['message'], 'data-u="h">00<') !== false && strpos($p4['message'], 'data-u="d">01<') !== false, true);
exit($fallos ? 1 : 0);
