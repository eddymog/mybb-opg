<?php
define('IN_MYBB', 1);
require __DIR__ . '/../../inc/plugins/lib/salario_faccion.php';

$fallos = 0;
function sf_prueba($nombre, $actual, $esperado)
{
    global $fallos;
    if ($actual !== $esperado) {
        $fallos++;
        echo "FALLO {$nombre}: " . var_export($actual, true) . ' != ' . var_export($esperado, true) . "\n";
        return;
    }
    echo "ok {$nombre}\n";
}

// Invierno: Madrid es UTC+1.
$antes_invierno = (new DateTimeImmutable('2026-11-23 05:59:59', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$corte_invierno = (new DateTimeImmutable('2026-11-23 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
sf_prueba('invierno antes del corte', opg_sf_ventana_automatica($antes_invierno), false);
sf_prueba('invierno en el corte', opg_sf_ventana_automatica($corte_invierno), true);

// Verano: Madrid es UTC+2.
$antes_verano = (new DateTimeImmutable('2027-06-07 05:59:59', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$corte_verano = (new DateTimeImmutable('2027-06-07 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
sf_prueba('verano antes del corte', opg_sf_ventana_automatica($antes_verano), false);
sf_prueba('verano en el corte', opg_sf_ventana_automatica($corte_verano), true);

sf_prueba('clave antes del lunes', opg_sf_semana_madrid($antes_invierno), '2026-11-16');
sf_prueba('clave desde el lunes', opg_sf_semana_madrid($corte_invierno), '2026-11-23');
sf_prueba(
    'fuera del lunes no abre ventana automática',
    opg_sf_ventana_automatica((new DateTimeImmutable('2026-11-24 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp()),
    false
);

exit($fallos ? 1 : 0);
