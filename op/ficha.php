<?php
/**
 * OBSOLETO — Redirige a personaje.php conservando todos los parámetros GET.
 */

$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/op/personaje.php' . ($qs !== '' ? '?' . $qs : '');
header('Location: ' . $target, true, 301);
exit;
