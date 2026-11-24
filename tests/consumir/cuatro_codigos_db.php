<?php
/**
 * Los cuatro codigos juntos (issue #32): un post con [consumir], [mantenida], [hide] y [XdY], creado y luego editado,
 * conserva TODOS los reemplazos con los plugins reales (BBCustom_dado prioridad 5, BBCustom_consumir y
 * BBCustom_mantenida prioridad 10, BBCustom_hide prioridad 15). Se prueba en los dos ordenes de carga posibles de los
 * dos plugins de la misma prioridad, y sin barras de escape anadidas.
 *   OPG_TEST_DB_PORT=33332 php tests/consumir/cuatro_codigos_db.php
 */
require __DIR__ . '/harness_plugins.php';

$db = cs_preparar('opg_consumir_cuatro');
cs_cargar_plugins();
cs_objeto('POC001', 'Poción de vida');
nt_sql("INSERT INTO mybb_op_tecnicas (tid, nombre) VALUES ('TEC1', 'Técnica mantenida'), ('TEC2', 'Otra técnica')");
$autor = 7;

foreach (array('orden alfabetico', 'orden inverso') as $vuelta => $orden) {
    if ($vuelta === 1) {
        $plugins->invertir_orden();
    }
    $tid = 300 + $vuelta;
    cs_inventario($autor, 'POC001', 5);

    // ── Crear ────────────────────────────────────────────────────────────────────────────────────────────────────────
    $texto = "Inicio de O'Brien \"x\" a\\b\n[consumir=POC001] [mantenida=TEC1]\n[hide]Secreto de O'Brien [2d6] y [consumir=POC001][/hide]\ntiro [1d20] fin";
    $pid = cs_crear_post($autor, $tid, $texto);
    $m = cs_mensaje($pid);
    $esperado = "Inicio de O'Brien \"x\" a\\b\n[consumido=1] [mantenida=TEC1]\n[hide=1]\ntiro [dado_guardado=2] fin\n[mantenida_guardado=$pid]";
    nt_prueba("$orden, crear: el mensaje guardado conserva los cuatro reemplazos", $m, $esperado);
    $hide = nt_valor("SELECT hide_content FROM mybb_op_hide WHERE pid = $pid AND hide_counter = 1");
    nt_prueba("$orden, crear: el contenido oculto lleva el dado y el consumo ya procesados y sin barras", $hide, "Secreto de O'Brien [dado_guardado=1] y [consumido=2]");
    nt_prueba("$orden, crear: dos consumos y dos tiradas", array((int)nt_valor("SELECT COUNT(*) FROM mybb_op_consumir WHERE pid = $pid"), (int)nt_valor("SELECT COUNT(*) FROM mybb_op_dados WHERE pid = $pid")), array(2, 2));
    nt_prueba("$orden, crear: mantenida activa y su HTML guardado", array((int)nt_valor("SELECT COUNT(*) FROM mybb_op_tecnicas_mantenidas WHERE tid = $tid AND activa = 1"), (int)nt_valor("SELECT COUNT(*) FROM mybb_op_mantenidas_html WHERE pid = $pid")), array(1, 1));
    nt_prueba("$orden, crear: inventario", cs_cantidad($autor, 'POC001'), 3);

    // ── Editar: anadir otro [consumir] ───────────────────────────────────────────────────────────────────────────────
    cs_editar_post($pid, $m . "\nAl editar: [consumir=POC001] de O'Brien");
    nt_prueba("$orden, editar: se conservan todos los reemplazos y se procesa la nueva", cs_mensaje($pid), $esperado . "\nAl editar: [consumido=3] de O'Brien");
    nt_prueba("$orden, editar: el contenido oculto no cambia", nt_valor("SELECT hide_content FROM mybb_op_hide WHERE pid = $pid AND hide_counter = 1"), $hide);
    nt_prueba("$orden, editar: una unidad mas", cs_cantidad($autor, 'POC001'), 2);
    nt_prueba("$orden, editar: no se repiten dados ni mantenidas", array((int)nt_valor("SELECT COUNT(*) FROM mybb_op_dados WHERE pid = $pid"), (int)nt_valor("SELECT COUNT(*) FROM mybb_op_mantenidas_html WHERE pid = $pid")), array(2, 1));

    // ── Mostrar: nada queda como codigo ──────────────────────────────────────────────────────────────────────────────
    $v = cs_ver($pid, $autor);
    nt_ok(substr_count($v, 'ha sido consumido') >= 2, "$orden, mostrar: los consumos se muestran");
    nt_prueba("$orden, mostrar: no queda ningun codigo sin procesar", preg_match('/\[(consumir|consumido|consumibleinvalido|hide|dado_guardado|mantenida_guardado)=?[^\]]*\]|\[\d+d\d+\]/', $v), 0);

    // ── Un post sin [consumir] con los otros tres sigue igual que antes ──────────────────────────────────────────────
    $pid2 = cs_crear_post($autor, $tid, "[mantenida=TEC2] [hide]oculto 'x'[/hide] [3d4]");
    nt_prueba("$orden, sin consumir: los otros tres codigos se conservan", cs_mensaje($pid2), "[mantenida=TEC2] [hide=1] [dado_guardado=1]\n[mantenida_guardado=$pid2]");
    nt_prueba("$orden, sin consumir: el hide sin barras", nt_valor("SELECT hide_content FROM mybb_op_hide WHERE pid = $pid2"), "oculto 'x'");

    // ── Solo [consumir] y [mantenida] (los dos de la misma prioridad) ────────────────────────────────────────────────
    nt_sql("UPDATE mybb_op_tecnicas_mantenidas SET activa = 0");
    $pid3 = cs_crear_post($autor, $tid, "[mantenida=TEC1] [consumir=POC001] y O'Brien");
    nt_prueba("$orden, consumir y mantenida: ninguno pisa al otro", cs_mensaje($pid3), "[mantenida=TEC1] [consumido=1] y O'Brien\n[mantenida_guardado=$pid3]");
    nt_sql("UPDATE mybb_op_tecnicas_mantenidas SET activa = 0");
}

nt_borrar();
nt_resumen('consumir cuatro_codigos_db');
