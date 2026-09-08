<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'opcache_reset.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

$uid = $mybb->user['uid'];
if (!is_staff($uid)) { die("Acceso denegado."); }

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>OPcache</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}";
echo ".ok{color:#4f4;} .warn{color:#fa0;} .err{color:#f44;} .info{color:#8af;} table{border-collapse:collapse;} td,th{border:1px solid #444;padding:4px 10px;text-align:left;}</style></head><body>";
echo "<h2>Diagnóstico y reinicio de OPcache</h2>";

if (!function_exists('opcache_get_status')) {
    echo "<p class='err'>La extensión OPcache no está disponible en este servidor (función opcache_get_status no existe). El problema de la caché no es OPcache.</p>";
    echo "</body></html>";
    return;
}

$status = opcache_get_status(false);

if ($status === false) {
    echo "<p class='err'>opcache_get_status() devolvió false — OPcache está deshabilitado (opcache.enable=0), o deshabilitado para CLI si esto se ejecuta fuera de un request web.</p>";
} else {
    $enabled = !empty($status['opcache_enabled']);
    echo "<p class='" . ($enabled ? 'ok' : 'warn') . "'>opcache_enabled: " . ($enabled ? 'true' : 'false') . "</p>";

    if (isset($status['memory_usage'])) {
        echo "<p class='info'>Memoria usada: " . round($status['memory_usage']['used_memory'] / 1048576, 1) . " MB / libre: " . round($status['memory_usage']['free_memory'] / 1048576, 1) . " MB</p>";
    }
    if (isset($status['opcache_statistics'])) {
        echo "<p class='info'>Scripts cacheados: " . $status['opcache_statistics']['num_cached_scripts'] . " | Hits: " . $status['opcache_statistics']['hits'] . " | Misses: " . $status['opcache_statistics']['misses'] . "</p>";
    }

    // Buscar si creacion.php está en la caché y comparar su timestamp cacheado vs el del disco
    $target = realpath(MYBB_ROOT . 'op/creacion.php');
    if ($target && !empty($status['scripts'][$target])) {
        $cached = $status['scripts'][$target];
        echo "<p class='info'>creacion.php SÍ está en la caché de OPcache.</p>";
        echo "<table><tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>timestamp cacheado</td><td>" . date('Y-m-d H:i:s', $cached['timestamp']) . "</td></tr>";
        echo "<tr><td>última modificación en disco</td><td>" . date('Y-m-d H:i:s', filemtime($target)) . "</td></tr>";
        echo "<tr><td>hits de este script</td><td>" . $cached['hits'] . "</td></tr>";
        echo "</table>";
        if (filemtime($target) > $cached['timestamp']) {
            echo "<p class='err'>⚠️ El archivo en disco es MÁS NUEVO que la versión cacheada — esto confirma que OPcache está sirviendo código viejo (validate_timestamps probablemente desactivado).</p>";
        } else {
            echo "<p class='ok'>La versión cacheada coincide con el archivo en disco (o es más reciente) — OPcache no parece ser el problema para este archivo.</p>";
        }
    } else {
        echo "<p class='warn'>creacion.php no aparece en la lista de scripts cacheados ahora mismo (puede que aún no se haya solicitado, o que ya se haya invalidado).</p>";
    }
}

if (isset($_GET['confirmar']) && function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo "<p class='" . ($result ? 'ok' : 'err') . "'>opcache_reset() " . ($result ? 'ejecutado correctamente. Toda la caché de bytecode se ha invalidado.' : 'ha fallado.') . "</p>";
} else {
    echo "<p><a href='?confirmar=1' style='color:#4f4;font-size:1.2em;'>→ Forzar opcache_reset() ahora</a></p>";
}

echo "</body></html>";
