<?php
// Prueba del escape de op/peticiones_usuario.php (#29). No necesita MyBB ni base de datos.
$src = file_get_contents(__DIR__ . '/../../op/peticiones_usuario.php');
if (!preg_match('#// BEGIN-PURE[^\n]*\n(.*?)// END-PURE#s', $src, $m)) { echo "FALLO: no se encontro el bloque puro\n"; exit(1); }
eval($m[1]);

$fallos = [];
$check = function ($ok, $msg) use (&$fallos) { if (!$ok) $fallos[] = $msg; };

$html = op_peticion_li('Cat <b>x</b>', '<script>alert(1)</script>', "linea <img src=x onerror=alert(2)>\nlinea2", "javascript:alert(3)", '2026-01-01', 'Hace 1 días.');
$check(strpos($html, '<script') === false, 'resumen sin escapar');
$check(strpos($html, '<img') === false, 'descripcion sin escapar');
$check(strpos($html, '<b>x') === false, 'categoria sin escapar');
$check(strpos($html, '&lt;script&gt;') !== false, 'falta resumen escapado');
$check(strpos($html, '<br />') !== false || strpos($html, '<br>') !== false, 'nl2br perdido');
$check(strpos($html, 'href=') === false, 'URL javascript: no debe ser enlace');

$html = op_peticion_li('c', 'r', 'd', "https://x.com/a?b='\"><script>", 'e', 'f');
$check(strpos($html, '<script') === false, 'URL con HTML sin escapar');
$check(strpos($html, "href='https://x.com/a?b=&#039;&quot;&gt;&lt;script&gt;'") !== false, 'href mal escapado');

$check(op_peticion_url_segura('https://foro.com/x') === 'https://foro.com/x', 'https valida');
$check(op_peticion_url_segura('http://foro.com/x') === 'http://foro.com/x', 'http valida');
$check(op_peticion_url_segura('/showthread.php?tid=1') === '/showthread.php?tid=1', 'ruta interna valida');
foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.com', 'vbscript:x', "https://a.com/\n", 'https://a b', '', ' JaVaScRiPt:1', '/\\evil.com'] as $u) {
    $check(op_peticion_url_segura($u) === '', "URL insegura aceptada: " . json_encode($u));
}

if ($fallos) { echo "FALLOS:\n- " . implode("\n- ", $fallos) . "\n"; exit(1); }
echo "OK peticiones escape\n";
