<?php
/**
 * Compatibilidad temporal con la antigua URL de la bitacora.
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require __DIR__ . '/bitacora.php';
    exit;
}

$location = '/op/bitacora.php';
$query = str_replace(array("\r", "\n"), '', trim((string)($_SERVER['QUERY_STRING'] ?? '')));
if ($query !== '') {
    $location .= '?' . $query;
}

header('Location: ' . $location, true, 301);
exit;
