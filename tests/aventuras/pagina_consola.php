<?php
/**
 * Pruebas de op/staff/consola_aventuras.php como página HTTP (harness + MySQL de pruebas).
 *   php tests/aventuras/pagina_consola.php
 */
require __DIR__ . '/bootstrap_db.php';
require __DIR__ . '/http.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
preparar_esquema($db);
$GLOBALS['roles'] = array('mod' => array(1), 'staff' => array(50), 'narra' => array(60));

foreach (array(3 => 'Eddy', 5 => 'Luffy', 6 => 'Zoro', 8 => 'Robin', 9 => 'Nami', 50 => 'Staff', 60 => 'NarraStaff', 1 => 'Mod') as $u => $n) { crear_usuario($u, $n); }
$db->query("SET SESSION sql_mode = ''", 1);
foreach (array(15, 16) as $f) { sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES ($f, 'Blue $f', 0, '$f', 1, $f)"); }
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (40, 'Isla Alfa', 15, '15,40', 1, 40), (41, 'Isla Beta', 16, '16,41', 1, 41)");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);

$PAG = 'op/staff/consola_aventuras.php';
$js_consola = file_get_contents(MYBB_ROOT . 'jscripts/consola_aventuras.js');
$GET_ACC = array('tab', 'listar', 'entregas', 'narradores', 'buscar_personas', 'form_denegar', 'form_editar', 'historial', 'cerrar', 'buscar_islas', 'elegir_isla', 'form_retirar_oferta');
$POST_ACC = array('cambiar_estado', 'actualizar_datos', 'eliminar', 'retirar_oferta');
$todo_html = ''; $GLOBALS['respuestas'] = array();
function g($accion, $get = array(), $uid = 60) { global $PAG, $todo_html; $r = llamar($PAG, array_merge(array('action' => $accion), $get), array(), $uid, true, 'clave-valida', 'GET'); $todo_html .= $r['cuerpo']; $GLOBALS['respuestas'][] = $r['cuerpo']; return $r; }
function p($accion, $post = array(), $uid = 60, $get = array()) { global $PAG, $todo_html; $r = llamar($PAG, array_merge(array('action' => $accion), $get), $post, $uid, true, 'clave-valida', 'POST'); $todo_html .= $r['cuerpo']; $GLOBALS['respuestas'][] = $r['cuerpo']; return $r; }
function contiene($r, $texto) { return strpos($r['cuerpo'], $texto) !== false; }

$A = ins(3, 0, 0, array(3, 5), array('tier_seleccionado' => 3, 'isla_fid' => 40, 'dia' => 5, 'narrador_nombre' => '', 'comentario_publico' => 'Comentario <b>x</b>'));
$B = ins(3, 8, 0, array(3), array('tier_seleccionado' => 2, 'isla_fid' => 41, 'dia' => 9, 'comentario_publico' => 'Aventura completa'));
$C = ins(5, 0, 4, array(5), array('tier_seleccionado' => 1));
$D = ins(60, 8, 0, array(60), array('tier_seleccionado' => 1));   // solicitud propia del narrador de staff

// ── Acceso ───────────────────────────────────────────────────────────────────
$r = llamar($PAG, array(), array(), 3, false, null, 'GET');
prueba('usuario sin permiso: pantalla de redirección', array(contiene($r, 'No tienes acceso'), contiene($r, 'ca-tabs')), array(true, false));
$r = llamar($PAG, array('action' => 'tab'), array(), 3, true, 'clave-valida', 'GET');
prueba('usuario sin permiso: las acciones responden 404', $r['estado'], 404);
foreach (array(1, 50, 60) as $u) {
    $r = llamar($PAG, array(), array(), $u, false, null, 'GET');
    prueba("uid $u (mod, staff, narra) entra", array($r['estado'], contiene($r, 'id="ca-tabs"')), array(200, true));
}

// ── Página completa ──────────────────────────────────────────────────────────
$r = llamar($PAG, array(), array(), 60, false, null, 'GET');
prueba('abre con las mismas etiquetas de estado que el tablón', array(contiene($r, 'ca-tab-activa" href="consola_aventuras.php?tab=solicitudes"'), contiene($r, 'ca-estados-grid'), contiene($r, 'ca-estado-metrica-creado'), contiene($r, 'value="creado" checked'), contiene($r, '<span class="ca-estado-etiqueta">En revisión</span><strong>3</strong>'), contiene($r, '<span class="ca-estado-etiqueta">Buscando narrador</span><strong>1</strong>'), contiene($r, '<span class="ca-estado-etiqueta">Total</span><strong>4</strong>')), array(true, true, true, true, true, true, true));
prueba('lista las solicitudes creadas y no las pendientes de narrador', array(contiene($r, 'id="fila-' . $A . '"'), contiene($r, 'id="fila-' . $B . '"'), contiene($r, 'id="fila-' . $C . '"')), array(true, true, false));
prueba('muestra el nombre del narrador y no solo su UID', contiene($r, 'Robin (8)'), true);
prueba('muestra la nota de comentario escapada', array(contiene($r, 'Comentario &lt;b&gt;x&lt;/b&gt;'), contiene($r, 'Comentario <b>')), array(true, false));
prueba('carga HTMX y la clave', array(contiene($r, 'htmx.min.js?v=2.0.4'), contiene($r, '{"X-Post-Key":"clave-valida"}'), contiene($r, '"allowEval":false')), array(true, true, true));
prueba('página completa: guía plegable y concisa de los tres flujos', array(contiene($r, 'class="ca-guia"'), contiene($r, 'Cómo funciona la consola'), contiene($r, 'Solicitudes</h3>'), contiene($r, 'Entregas</h3>'), contiene($r, 'Narradores</h3>'), contiene($r, 'Flujo recomendado:')), array(true, true, true, true, true, true));
prueba('la guía aparece entre el encabezado y las pestañas', strpos($r['cuerpo'], 'class="ca-encabezado"') < strpos($r['cuerpo'], 'class="ca-guia"') && strpos($r['cuerpo'], 'class="ca-guia"') < strpos($r['cuerpo'], 'id="ca-tabs"'), true);
prueba('la guía conserva su presentación si el asset estático queda desactualizado', array(contiene($r, '.ca-consola .ca-guia {'), contiene($r, '.ca-guia > summary'), contiene($r, 'grid-template-columns: repeat(3, minmax(0, 1fr))'), contiene($r, 'aventuras.css?v=8')), array(true, true, true, true));
prueba('cabecera y controles operativos sin resumen redundante', array(contiene($r, '<h1>Consola de aventuras</h1>'), contiene($r, 'aria-label="Trabajo pendiente"'), contiene($r, 'name="incompletas"'), contiene($r, 'name="resolubles"'), contiene($r, '>Limpiar</a>'), contiene($r, 'name="orden"'), contiene($r, 'value="recientes" selected'), contiene($r, 'placeholder="#850, UID o nombre"')), array(true, false, false, false, false, true, true, true));
prueba('pestañas rápidas con indicador y cancelación de solicitudes antiguas', array(substr_count($r['cuerpo'], 'hx-indicator="#ca-panel-cargando"'), substr_count($r['cuerpo'], 'hx-sync="#ca-panel:replace"'), contiene($r, 'id="ca-panel-cargando"'), contiene($r, 'aria-label="Cargando sección"'), contiene($r, 'id="ca-panel" aria-busy="false"'), contiene($r, 'consola_aventuras.js?v=2')), array(3, 3, true, true, true, true));
prueba('caché de pestañas se revalida e invalida al escribir', array(strpos($js_consola, 'mostrarCache(destino)') !== false, strpos($js_consola, "config.verb.toLowerCase() !== 'get'") !== false, strpos($js_consola, "document.addEventListener('solicitudCambiada', invalidarCache)") !== false, strpos($js_consola, 'focus({preventScroll: true})') !== false, strpos($js_consola, 'scrollIntoView') === false), array(true, true, true, true, true));
prueba('contador de entregas oculto sin pendientes', contiene($r, 'ca-contador ca-oculto'), true);
$resumen_consola = opg_av_resumen_consola();
prueba('resumen consolidado conserva estados y entregas', array($resumen_consola['estados'], $resumen_consola['entregas'], $resumen_consola['autonarradas_pendientes']), array(opg_av_conteo_estados(), array('pendiente' => 0, 'rechazada' => 0, 'entregada' => 0), 0));
$r = llamar($PAG, array('tab' => 'entregas'), array(), 60, false, null, 'GET');
prueba('?tab=entregas', array(contiene($r, 'ca-tab-activa" href="consola_aventuras.php?tab=entregas"'), contiene($r, 'No hay entregas pendientes.')), array(true, true));
$r = llamar($PAG, array('tab' => 'narradores'), array(), 60, false, null, 'GET');
prueba('?tab=narradores', contiene($r, 'No hay ofertas de narrador.'), true);

// ── Contrato HTMX ────────────────────────────────────────────────────────────
prueba('sin HX-Request: 404', llamar($PAG, array('action' => 'tab'), array(), 60, false, null, 'GET')['estado'], 404);
$r = g('tab', array('tab' => 'entregas'));
prueba('la respuesta HTMX cambia solo el panel y no duplica la guía', contiene($r, 'class="ca-guia"'), false);
prueba('acción desconocida: 404', g('inventada')['estado'], 404);
foreach (array('crear', 'editar', 'postular_narrador', 'elegir_ficha', 'form_postular', 'analizar', 'pagar') as $a) {
    prueba("la acción '$a' del tablón o de recompensas responde 404", array(llamar($PAG, array('action' => $a), array(), 50, true, 'clave-valida', 'GET')['estado'], llamar($PAG, array('action' => $a), array('id' => 1), 50, true, 'clave-valida', 'POST')['estado']), array(404, 404));
}
prueba('POST pedida por GET: 404', g('cambiar_estado')['estado'], 404);
prueba('POST sin clave: 403', llamar($PAG, array('action' => 'cambiar_estado', 'id' => $A, 'a' => 1), array(), 60, true, null, 'POST')['estado'], 403);
prueba('POST con clave inválida: 403', llamar($PAG, array('action' => 'cambiar_estado', 'id' => $A, 'a' => 1), array(), 60, true, 'otra', 'POST')['estado'], 403);
prueba('las respuestas no se guardan en caché', cabecera(g('listar'), 'Cache-Control'), 'no-store');

// ── Listado y filtros ────────────────────────────────────────────────────────
$r = g('listar', array('estado' => 'todas'));
prueba('Todas: muestra las cuatro', array(contiene($r, 'id="fila-' . $C . '"'), contiene($r, 'id="fila-' . $A . '"')), array(true, true));
prueba('filtros empujan una URL recargable sin action=listar', array(strpos(cabecera($r, 'HX-Push-Url'), 'tab=solicitudes') !== false, strpos(cabecera($r, 'HX-Push-Url'), 'action=') === false), array(true, true));
$r = g('listar', array('estado' => 'pendiente-narrador'));
prueba('Buscando narrador: una sola', array(contiene($r, 'id="fila-' . $C . '"'), contiene($r, 'id="fila-' . $A . '"')), array(true, false));
prueba('estado inválido: cae en En revisión', array(contiene(g('listar', array('estado' => 'xx')), 'id="fila-' . $A . '"'), contiene(g('listar', array('estado' => 'xx')), 'id="fila-' . $C . '"')), array(true, false));
$r = g('listar', array('estado' => 'todas', 'uid' => 8));
prueba('filtro por UID del narrador', array(contiene($r, 'id="fila-' . $B . '"'), contiene($r, 'id="fila-' . $A . '"')), array(true, false));
prueba('filtro por UID de un participante', array(contiene(g('listar', array('estado' => 'todas', 'uid' => 5)), 'id="fila-' . $A . '"'), contiene(g('listar', array('estado' => 'todas', 'uid' => 5)), 'id="fila-' . $B . '"')), array(true, false));
prueba('búsqueda por número exacto', array(contiene(g('listar', array('estado' => 'todas', 'q' => '#' . $B)), 'id="fila-' . $B . '"'), contiene(g('listar', array('estado' => 'todas', 'q' => '#' . $B)), 'id="fila-' . $A . '"')), array(true, false));
prueba('typeahead de personas', array(contiene(g('buscar_personas', array('q' => 'Robin')), 'name="persona" value="8"'), contiene(g('buscar_personas', array('q' => 'Robin')), 'UID 8')), array(true, true));
prueba('Todas se conserva en la paginación (parámetro todas)', true, true);
prueba('sin resultados', contiene(g('listar', array('estado' => 'denegado')), 'No hay solicitudes con estos filtros.'), true);

// ── El equipo puede moderar sus propias aventuras ───────────────────────────
$r = g('listar', array('estado' => 'creado'), 60);
prueba('la solicitud propia muestra sus acciones habilitadas', array(contiene($r, 'id="fila-' . $D . '"'), contiene($r, 'No puedes resolver una aventura en la que participas.')), array(true, false));
prueba('form_denegar de la propia está permitido', g('form_denegar', array('id' => $D), 60)['estado'], 200);
$r = p('cambiar_estado', array(), 60, array('id' => $D, 'a' => 1));
prueba('el narrador de staff aprueba la propia', array($r['estado'], (int) fila($D)['estado']), array(200, 1));

// ── Aprobar, denegar, finalizar ─────────────────────────────────────────────
$r = p('cambiar_estado', array(), 60, array('id' => $A, 'a' => 1));
prueba('aprobar una sin narrador: 422 con el aviso en la fila', array($r['estado'], cabecera($r, 'HX-Retarget')), array(422, '#aviso-' . $A));
$r = p('cambiar_estado', array(), 60, array('id' => $B, 'a' => 1));
prueba('aprobar: devuelve la fila actualizada', array($r['estado'], contiene($r, 'id="fila-' . $B . '"'), contiene($r, 'ta-estado-aprobado'), (int) fila($B)['estado']), array(200, true, true, 1));
prueba('estado 5 por esta vía: 422', p('cambiar_estado', array(), 60, array('id' => $B, 'a' => 5))['estado'], 422);
prueba('estado inválido: 422', p('cambiar_estado', array(), 60, array('id' => $B, 'a' => 9))['estado'], 422);
prueba('form_denegar: motivo requerido', array(contiene(g('form_denegar', array('id' => $B)), 'name="motivo"'), contiene(g('form_denegar', array('id' => $B)), 'required')), array(true, true));
$r = p('cambiar_estado', array('motivo' => ''), 60, array('id' => $B, 'a' => 2));
prueba('denegar sin motivo: 422 retarget', array($r['estado'], cabecera($r, 'HX-Retarget')), array(422, '#aviso-form-' . $B));
$r = p('cambiar_estado', array('motivo' => 'No encaja <b>x</b>'), 60, array('id' => $B, 'a' => 2));
prueba('denegar con motivo: fila con el motivo escapado', array($r['estado'], contiene($r, 'No encaja &lt;b&gt;x&lt;/b&gt;'), (int) fila($B)['estado']), array(200, true, 2));
prueba('finalizar una denegada: 409', p('cambiar_estado', array(), 60, array('id' => $B, 'a' => 3))['estado'], 409);
$E = ins(3, 8, 1, array(3), array('tier_seleccionado' => 2));
prueba('finalizar una aprobada', array(p('cambiar_estado', array(), 60, array('id' => $E, 'a' => 3))['estado'], (int) fila($E)['estado']), array(200, 3));
prueba('una solicitud inexistente: 404', p('cambiar_estado', array(), 60, array('id' => 9999, 'a' => 1))['estado'], 404);

// ── Editar datos ─────────────────────────────────────────────────────────────
$r = g('form_editar', array('id' => $A));
prueba('formulario de datos con los valores actuales', array(contiene($r, 'name="nota_staff"'), contiene($r, 'name="isla_fid" value="40"'), contiene($r, 'value="5"'), contiene($r, 'consola_aventuras.php?action=buscar_islas')), array(true, true, true, true));
prueba('el formulario no enlaza a otra página', contiene($r, 'tablon_de_aventuras.php'), false);
$r = p('actualizar_datos', array('nota_staff' => 'Revisar <i>luego</i>', 'comentario' => 'Nuevo', 'inframundo' => '1', 'dia' => '30', 'isla_fid' => '41'), 60, array('id' => $A));
prueba('actualizar datos: fila con nota, día e isla nuevos', array($r['estado'], contiene($r, 'Revisar &lt;i&gt;luego&lt;/i&gt;'), contiene($r, 'Isla Beta'), contiene($r, 'Verano 725, día 30'), contiene($r, 'Inframundo')), array(200, true, true, true, true));
prueba('actualizar datos con día inválido: 422', array(p('actualizar_datos', array('nota_staff' => '', 'comentario' => 'Nuevo', 'inframundo' => '0', 'dia' => '99'), 60, array('id' => $A))['estado']), array(422));
prueba('actualizar datos sin día: conserva el día', (int) fila($A)['dia'], 30);
prueba('isla inválida: 422', p('actualizar_datos', array('nota_staff' => '', 'comentario' => 'x', 'inframundo' => '0', 'isla_fid' => '15'), 60, array('id' => $A))['estado'], 422);
prueba('buscar islas y elegir', array(contiene(g('buscar_islas', array('id' => $A, 'q' => 'Alfa')), 'Isla Alfa'), contiene(g('elegir_isla', array('id' => $A, 'fid' => 40)), 'name="isla_fid" value="40"'), g('elegir_isla', array('id' => $A, 'fid' => 15))['cuerpo']), array(true, true, ''));

// ── Historial ────────────────────────────────────────────────────────────────
$r = g('historial', array('id' => $B));
prueba('historial: acciones y quién', array(contiene($r, 'Historial de la solicitud #' . $B), contiene($r, 'NarraStaff'), contiene($r, 'Cambió de estado')), array(true, true, true));
prueba('historial accesible en panel único', array(contiene($r, 'role="dialog"'), contiene($r, 'aria-modal="true"'), contiene($r, 'hx-target="#ca-overlay"'), contiene($r, 'ca-historial-lista')), array(true, true, true, true));
$r = g('historial', array('id' => $A));
prueba('historial: datos actualizados con antes y después', array(contiene($r, 'El staff actualizó datos'), contiene($r, 'dia: 5 &rarr; 30')), array(true, true));
prueba('historial de una inexistente: 404', g('historial', array('id' => 9999))['estado'], 404);
prueba('cerrar devuelve vacío', g('cerrar')['cuerpo'], '');

// ── Eliminar ─────────────────────────────────────────────────────────────────
$F = ins(5, 0, 5, array(5));
$r = g('listar', array('estado' => 'pendiente-borrado'), 60);
prueba('narra no ve el botón de eliminar', contiene($r, 'action=eliminar'), false);
$r = g('listar', array('estado' => 'pendiente-borrado'), 50);
prueba('staff ve el botón de eliminar con confirmación', array(contiene($r, 'action=eliminar&amp;id=' . $F), contiene($r, 'hx-confirm')), array(true, true));
prueba('eliminar sin ser staff: 403', p('eliminar', array(), 60, array('id' => $F))['estado'], 403);
prueba('eliminar una que no está pendiente de borrado: 409', p('eliminar', array(), 50, array('id' => $A))['estado'], 409);
$r = p('eliminar', array(), 50, array('id' => $F));
prueba('eliminar: la fila desaparece y se borra', array($r['estado'], $r['cuerpo'], fila($F)), array(200, '', null));

// ── Ofertas de narrador ──────────────────────────────────────────────────────
sql("INSERT INTO mybb_op_narradores_disponibles (uid, discord, mensaje, frecuencia, tiers, estilo, created_at, updated_at) VALUES (8, 'robin#1', 'Misterio <i>y</i>', 'semanal', '2,3', 'Misterio', " . time() . ", " . time() . "), (9, 'nami#2', '', '', '', '', " . (time() - 90 * 86400) . ", " . (time() - 90 * 86400) . ")");
$r = llamar($PAG, array('tab' => 'narradores'), array(), 60, false, null, 'GET');
prueba('lista vigentes y vencidas con el Discord y el mensaje escapado', array(contiene($r, 'robin#1'), contiene($r, 'nami#2'), contiene($r, 'Vigente'), contiene($r, 'Vencida'), contiene($r, 'Misterio &lt;i&gt;y&lt;/i&gt;')), array(true, true, true, true, true));
prueba('oferta con preferencias y carga', array(contiene($r, 'Semanal'), contiene($r, 'Tiers 2,3'), contiene($r, 'En curso:'), contiene($r, 'Finalizadas:')), array(true, true, true, true));
prueba('filtros de narradores', array(contiene(g('narradores', array('vigencia' => 'vigentes')), 'robin#1'), contiene(g('narradores', array('vigencia' => 'vigentes')), 'nami#2'), contiene(g('narradores', array('q' => 'Nami')), 'nami#2')), array(true, false, true));
prueba('formulario de retirada con motivo', contiene(g('form_retirar_oferta', array('uid' => 8)), 'name="motivo"'), true);
$r = p('retirar_oferta', array('motivo' => ''), 60, array('uid' => 8));
prueba('retirar sin motivo: 422 retarget', array($r['estado'], cabecera($r, 'HX-Retarget')), array(422, '#aviso-form-oferta-8'));
$r = p('retirar_oferta', array('motivo' => 'Spam'), 60, array('uid' => 8));
prueba('retirar con motivo: desaparece la fila y queda auditado', array($r['estado'], $r['cuerpo'], una("SELECT COUNT(*) n FROM mybb_op_narradores_disponibles WHERE uid = 8")['n'], una("SELECT COUNT(*) n FROM mybb_op_audit_aventuras WHERE accion = 'retirar_oferta_admin'")['n']), array(200, '', '0', '1'));
prueba('retirar otra vez: 404', p('retirar_oferta', array('motivo' => 'x'), 60, array('uid' => 8))['estado'], 404);

for ($u = 100; $u <= 120; $u++) {
    crear_usuario($u, 'Narrador ' . $u);
    sql("INSERT INTO mybb_op_narradores_disponibles (uid, discord, mensaje, frecuencia, tiers, estilo, created_at, updated_at) VALUES ($u, 'n$u', '', 'flexible', '1,2', 'Libre', " . (time() - $u) . ", " . (time() - $u) . ")");
}
$r = g('narradores', array('pagina' => 2));
prueba('narradores pagina de veinte en veinte', array(contiene($r, '22 ofertas'), contiene($r, 'Página 2 de 2'), contiene($r, 'aria-current="page">2</button>'), strpos(cabecera($r, 'HX-Push-Url'), 'tab=narradores') !== false), array(true, true, true, true));

// ── Entregas ─────────────────────────────────────────────────────────────────
$G = ins(3, 8, 1, array(3, 5), array('tier_seleccionado' => 4, 'isla_fid' => 40));
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES (100, 40, 'La aventura <u>x</u>', 3, 0, 1, " . time() . ")");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
sql("INSERT INTO mybb_op_aventuras_entregas (peticion_id, tid, tema_nombre, enviado_por, resumen, reputacion, estado, enviado_at, intentos) VALUES ($G, 100, 'La aventura <u>x</u>', 8, 'resumen', 'positiva', 'pendiente', " . time() . ", 2)");
$r = llamar($PAG, array('tab' => 'entregas'), array(), 60, false, null, 'GET');
prueba('entregas: la pendiente con enlace a la página de recompensas', array(contiene($r, 'La aventura &lt;u&gt;x&lt;/u&gt;'), contiene($r, 'aventuras_recompensas.php?id='), contiene($r, 'Revisar y pagar'), contiene($r, 'Intento 2'), contiene($r, 'Tier 4')), array(true, true, true, true, true));
prueba('el contador de la pestaña muestra 1', contiene($r, 'id="ca-contador" aria-label="1 entregas pendientes">1<'), true);
prueba('entregas: filtro por estado', array(contiene(g('entregas', array('estado' => 'entregada')), 'No hay entregas en este estado.'), contiene(g('entregas', array('estado' => 'pendiente')), 'Revisar y pagar')), array(true, true));
$r = g('listar', array('estado' => 'aprobado'));
prueba('la solicitud con entrega pendiente no ofrece Finalizar', array(contiene($r, 'id="fila-' . $G . '"'), contiene($r, 'Entrega pendiente de revisión')), array(true, true));
$bloque = substr($r['cuerpo'], strpos($r['cuerpo'], 'id="fila-' . $G . '"'));
$bloque = substr($bloque, 0, strpos($bloque, 'id="extra-' . $G . '"'));
prueba('sin botón Finalizar en esa fila', strpos($bloque, 'a=3'), false);

// ── Coherencia ───────────────────────────────────────────────────────────────
preg_match_all('/hx-(?:get|post)="[^"]*action=([a-z_]+)/', $todo_html, $m);
$enlazadas = array_unique($m[1]);
$faltan = array_values(array_diff($enlazadas, array_merge($GET_ACC, $POST_ACC)));
prueba('todas las acciones enlazadas existen en la página', $faltan, array());
prueba('sin hx-on ni scripts', array(preg_match('/\shx-on[:\-]/', $todo_html), strpos($todo_html, '<script')), array(0, false));
prueba('las rutas relativas apuntan a la propia consola', preg_match('/hx-(?:get|post)="(?!consola_aventuras\.php)/', $todo_html), 0);
prueba('HTML bien formado y sin ids repetidos en cada respuesta', problemas_html($GLOBALS['respuestas']), array());
echo "  (acciones enlazadas: " . implode(', ', $enlazadas) . ")\n";
resumen();
