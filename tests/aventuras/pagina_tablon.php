<?php
/**
 * Pruebas de op/tablon_de_aventuras.php como página HTTP (harness + MySQL de pruebas).
 *   php tests/aventuras/pagina_tablon.php
 */
require __DIR__ . '/bootstrap_db.php';
require __DIR__ . '/http.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
preparar_esquema($db);
$GLOBALS['roles'] = array('mod' => array(1), 'staff' => array(50), 'narra' => array(60));

foreach (array(3 => 'Eddy', 5 => 'Luffy', 6 => 'Zoro', 8 => 'Robin', 9 => 'Nami', 12 => 'Franky', 50 => 'Staff', 60 => 'NarraStaff') as $u => $n) { crear_usuario($u, $n); }
crear_usuario(20, '<img src=x onerror=alert(1)>Mal');
crear_usuario(7, 'SinAprobar', array('aprobada_por' => 'sin_aprobar'));
$db->query("SET SESSION sql_mode = ''", 1);
foreach (array(15, 16, 17, 18) as $f) { sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES ($f, 'Blue $f', 0, '$f', 1, $f)"); }
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (40, 'Isla Alfa', 15, '15,40', 1, 40), (41, 'Isla Beta', 16, '16,41', 1, 41)");
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES (100, 40, 'La aventura de Alfa', 3, 0, 1, " . time() . "), (101, 40, 'Otra de Alfa', 3, 0, 1, " . time() . ")");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);

$PAG = 'op/tablon_de_aventuras.php';
$GET_ACC = array('tab', 'buscar_fichas', 'elegir_ficha', 'buscar_islas', 'elegir_isla', 'mis_activas', 'listar', 'sin_narrador', 'contar_sin_narrador', 'narradores_disponibles', 'form_oferta', 'ver', 'form_editar', 'form_postular', 'form_renunciar', 'form_vincular', 'form_entrega', 'buscar_temas', 'elegir_tema', 'quitar_chip', 'quitar_isla', 'cerrar_modal', 'form_participante', 'quitar_narrador');
$POST_ACC = array('crear', 'editar', 'postular_narrador', 'renunciar_narrador', 'pedir_borrado', 'vincular_aventura', 'enviar_entrega', 'ofrecerme_narrador', 'renovar_oferta', 'retirar_oferta');
$todo_html = '';
$GET_ACC[] = 'quitar_tema';
function g($accion, $get = array(), $uid = 3) { global $PAG, $todo_html; $r = llamar($PAG, array_merge(array('action' => $accion), $get), array(), $uid, true, 'clave-valida', 'GET'); $todo_html .= $r['cuerpo']; return $r; }
function p($accion, $post = array(), $uid = 3, $get = array()) { global $PAG, $todo_html; $r = llamar($PAG, array_merge(array('action' => $accion), $get), $post, $uid, true, 'clave-valida', 'POST'); $todo_html .= $r['cuerpo']; return $r; }
function contiene($r, $texto) { return strpos($r['cuerpo'], $texto) !== false; }

// ── Página completa ──────────────────────────────────────────────────────────
$r = llamar($PAG, array(), array(), 0, false, null, 'GET');
prueba('invitado: sin permiso', $r['cuerpo'], 'SIN_PERMISO');
$r = llamar($PAG, array(), array(), 3, false, null, 'GET');
prueba('página completa: estado 200 y pestañas', array($r['estado'], contiene($r, 'id="ta-tabs"'), contiene($r, 'Mis aventuras'), contiene($r, 'Otras aventuras'), contiene($r, 'Solicitar aventura')), array(200, true, true, true, true));
prueba('página completa: abre en Mis aventuras', contiene($r, 'ta-tab-activa" href="tablon_de_aventuras.php?tab=mias"'), true);
prueba('página completa: carga HTMX con versión y envía el post_code', array(contiene($r, 'jscripts/htmx/htmx.min.js?v=2.0.4'), contiene($r, '{"X-Post-Key":"clave-valida"}')), array(true, true));
prueba('página completa: configuración de HTMX sin eval', contiene($r, '"allowEval":false') && contiene($r, '"allowScriptTags":false'), true);
prueba('página completa: no tiene la pestaña Consola', array(stripos($r['cuerpo'], 'consola') === false), array(true));
prueba('usuario sin aventuras: recomendación de pedir una', array(contiene($r, 'Todavía no participas en ninguna aventura'), contiene($r, 'Solicitar una aventura')), array(true, true));
$r = llamar($PAG, array('tab' => 'solicitud'), array(), 3, false, null, 'GET');
prueba('?tab=solicitud muestra el formulario con tier y día', array(contiene($r, 'name="tier"'), contiene($r, 'name="dia"'), contiene($r, 'Tier 10 (12 posts por participante)')), array(true, true, true));
$r = llamar($PAG, array('tab' => 'otras'), array(), 3, false, null, 'GET');
prueba('?tab=otras muestra solo listado y estadísticas', array(contiene($r, 'Aventuras sin narrador'), contiene($r, 'Narradores disponibles'), contiene($r, 'id="ta-lista-otras"'), contiene($r, 'id="ta-estadisticas-otras"')), array(false, false, true, true));
$r = llamar($PAG, array('tab' => 'narradores'), array(), 3, false, null, 'GET');
prueba('nueva pestaña reúne ofertas y aventuras sin narrador', array(contiene($r, 'Narradores &amp; Narrados'), contiene($r, 'Narradores disponibles'), contiene($r, 'Aventuras sin narrador')), array(true, true, true));
prueba('sin aventuras sin narrador: aviso vacío y no desaparece el bloque', contiene($r, 'Ahora mismo todas las aventuras tienen narrador.'), true);

// ── Contrato HTMX ────────────────────────────────────────────────────────────
$r = llamar($PAG, array('action' => 'tab', 'tab' => 'mias'), array(), 3, false, null, 'GET');
prueba('una acción sin HX-Request responde 404', $r['estado'], 404);
prueba('una acción desconocida responde 404', g('inventada')['estado'], 404);
foreach (array('cambiar_estado', 'actualizar_datos', 'eliminar', 'historial', 'entrega_pagar', 'analizar', 'pagar', 'retirar_oferta_admin', 'form_denegar') as $a) {
    prueba("la acción de gestión '$a' responde 404 incluso a staff", array(llamar($PAG, array('action' => $a), array(), 50, true, 'clave-valida', 'GET')['estado'], llamar($PAG, array('action' => $a), array('id' => 1), 50, true, 'clave-valida', 'POST')['estado']), array(404, 404));
}
prueba('una acción POST pedida por GET responde 404', g('crear')['estado'], 404);
prueba('una acción GET pedida por POST responde 404', p('listar')['estado'], 404);
$r = llamar($PAG, array('action' => 'tab'), array(), 0, true, 'clave-valida', 'GET');
prueba('sin sesión: HX-Redirect al inicio de sesión', array($r['estado'], cabecera($r, 'HX-Redirect')), array(200, 'https://onepiecegaiden.com/member.php?action=login'));
$r = llamar($PAG, array('action' => 'crear'), array('rol' => 'jugador'), 3, true, null, 'POST');
prueba('POST sin X-Post-Key: 403', $r['estado'], 403);
$r = llamar($PAG, array('action' => 'crear'), array('rol' => 'jugador'), 3, true, 'otra', 'POST');
prueba('POST con X-Post-Key inválida: 403', $r['estado'], 403);
$r = g('tab', array('tab' => 'otras'));
prueba('las respuestas no se guardan en caché', array(cabecera($r, 'Cache-Control'), cabecera($r, 'Vary')), array('no-store', 'HX-Request'));
prueba('cambiar de pestaña devuelve el panel y la barra de pestañas fuera de banda', array(contiene($r, 'id="ta-tabs"'), contiene($r, 'hx-swap-oob="true"')), array(true, true));
$r = g('tab', array('tab' => 'otras'));
prueba('Otras aventuras busca reactivo por ID o personaje', array(contiene($r, 'Buscar por ID o personaje'), contiene($r, 'placeholder="#850 o nombre del personaje"')), array(true, true));

// ── Typeaheads ───────────────────────────────────────────────────────────────
$r = g('buscar_fichas', array('q' => 'Zo', 'destino' => 'participante'));
prueba('buscar fichas: resultado con su acción de elegir', array(contiene($r, 'Zoro'), contiene($r, 'action=elegir_ficha&amp;fid=6&amp;destino=participante')), array(true, true));
prueba('buscar fichas con 1 letra pide más', contiene(g('buscar_fichas', array('q' => 'Z', 'destino' => 'participante')), 'Escribe al menos 2 letras.'), true);
prueba('buscar fichas escapa el nombre', array(contiene(g('buscar_fichas', array('q' => 'Mal', 'destino' => 'participante')), '<img'), contiene(g('buscar_fichas', array('q' => 'Mal', 'destino' => 'participante')), '&lt;img')), array(false, true));
prueba('buscar fichas sin ficha aprobada no devuelve nada', array(contiene(g('buscar_fichas', array('q' => 'Zo', 'destino' => 'participante'), 7), 'Zoro')), array(false));
$r = g('elegir_ficha', array('fid' => 6, 'destino' => 'participante'));
prueba('elegir ficha: chip con campo oculto y limpia el buscador', array(contiene($r, 'name="participantes[]" value="6"'), contiene($r, 'hx-swap-oob="true"')), array(true, true));
prueba('elegir ficha ya elegida no la repite', contiene(g('elegir_ficha', array('fid' => 6, 'destino' => 'participante', 'participantes' => array(6))), 'name="participantes[]"'), false);
prueba('elegir ficha sin aprobar no crea chip', contiene(g('elegir_ficha', array('fid' => 7, 'destino' => 'participante')), 'name="participantes[]"'), false);
prueba('elegirse a uno mismo no crea chip', contiene(g('elegir_ficha', array('fid' => 3, 'destino' => 'participante')), 'name="participantes[]"'), false);
prueba('elegir narrador: campo narrador_fid', contiene(g('elegir_ficha', array('fid' => 8, 'destino' => 'narrador')), 'name="narrador_fid" value="8"'), true);
prueba('buscar islas: solo los hijos directos de los Blues', array(contiene(g('buscar_islas', array('q' => 'Isla')), 'Isla Alfa'), contiene(g('buscar_islas', array('q' => 'Blue')), 'Blue 15')), array(true, false));
prueba('elegir isla válida oculta el buscador e inválida no deja campo', array(contiene(g('elegir_isla', array('fid' => 40)), 'name="isla_fid" value="40"'), contiene(g('elegir_isla', array('fid' => 40)), 'name="q"'), contiene(g('elegir_isla', array('fid' => 15)), 'name="isla_fid"')), array(true, false, false));
prueba('quitar isla restaura el buscador', contiene(g('quitar_isla'), 'name="q"'), true);
prueba('quitar chip y cerrar ventana devuelven vacío', array(g('quitar_chip')['cuerpo'], g('cerrar_modal')['cuerpo']), array('', ''));

// ── Solicitar ────────────────────────────────────────────────────────────────
$valida = array('rol' => 'jugador', 'participantes' => array('5'), 'tiene_narrador' => '0', 'idea' => 'Una idea <b>fuerte</b>', 'isla_fid' => '40', 'dia' => '12', 'tier' => '3', 'comentario' => '<script>alert(1)</script>', 'inframundo' => '1');
$r = p('crear', array_merge($valida, array('dia' => '99')));
prueba('crear con un error: 422 con retarget al aviso del formulario', array($r['estado'], cabecera($r, 'HX-Retarget'), cabecera($r, 'HX-Reswap'), contiene($r, 'El día debe ser un número de 1 a 90.')), array(422, '#aviso-form-crear', 'innerHTML', true));
$r = p('crear', array_merge($valida, array('tier' => '0', 'isla_fid' => '999')));
prueba('crear con varios errores los lista todos', array(contiene($r, 'tier'), contiene($r, 'isla')), array(true, true));
$r = p('crear', $valida);
prueba('crear: 200 con el aviso de éxito y el evento aventuraCreada', array($r['estado'], contiene($r, 'Solicitud #1 enviada'), cabecera($r, 'HX-Trigger')), array(200, true, 'aventuraCreada'));
$f = fila(1);
prueba('crear guarda la solicitud con el creador de la sesión', array((int) $f['uid'], (int) $f['estado'], (int) $f['tier_seleccionado'], (int) $f['inframundo']), array(3, 4, 3, 1));
prueba('crear: el mismo envío dos veces seguidas es un conflicto', p('crear', $valida)['estado'], 409);
prueba('crear sin ficha aprobada: 403', p('crear', $valida, 7)['estado'], 403);
prueba('mis_activas: indicador informativo sin reemplazar el botón', array(contiene(g('mis_activas'), 'id="ta-activas"'), contiene(g('mis_activas'), 'id="ta-enviar"')), array(true, false));
prueba('mis_activas cuenta la solicitud pendiente (grupal: 0 solitarias, 1 en total)', array(contiene(g('mis_activas'), '<b>0 / 3</b>'), contiene(g('mis_activas'), '<b>1 / 4</b>')), array(true, true));

// ── Listados y XSS ───────────────────────────────────────────────────────────
$r = g('tab', array('tab' => 'mias'));
prueba('Mis aventuras muestra la solicitud creada, con la idea y el comentario escapados', array(contiene($r, '#1'), contiene($r, '&lt;script&gt;alert(1)&lt;/script&gt;'), contiene($r, '<script>alert'), contiene($r, 'Una idea &lt;b&gt;fuerte&lt;/b&gt;')), array(true, true, false, true));
prueba('tarjeta: acciones del creador (editar y pedir borrado)', array(contiene($r, 'action=form_editar&amp;id=1'), contiene($r, 'action=pedir_borrado&amp;id=1')), array(true, true));
prueba('tarjeta: el creador no ve postularse', contiene($r, 'form_postular'), false);
prueba('tarjeta: muestra día, tier e inframundo', array(contiene($r, 'Verano 725, día 12'), contiene($r, 'Tier 3'), contiene($r, 'Inframundo')), array(true, true, true));
$r = g('listar', array('ambito' => 'mias', 'estado' => 'aprobado'));
prueba('listar con un filtro sin resultados', contiene($r, 'No hay aventuras con estos filtros.'), true);
$r = g('tab', array('tab' => 'narradores'), 9);
prueba('otra usuaria ve la solicitud pendiente en el bloque de sin narrador, con contador y botón de postularse', array(contiene($r, 'Aventuras sin narrador'), contiene($r, 'action=form_postular&amp;id=1'), contiene($r, '<span class="ta-sin-cifra" aria-label="1 esperando narrador">1</span>')), array(true, true, true));
prueba('el contador de la pestaña', contiene(g('contar_sin_narrador', array(), 9), '>1</span>'), true);
prueba('el creador no cuenta su propia solicitud', contiene(g('contar_sin_narrador', array(), 3), 'ta-oculto'), true);
prueba('ver una solicitud ajena en Otras no muestra la nota de staff', contiene(g('ver', array('id' => 1), 9), 'nota_staff'), false);

// ── Postularse ───────────────────────────────────────────────────────────────
$r = g('form_postular', array('id' => 1), 9);
prueba('ventana de postulación: diálogo accesible, casilla requerida y datos de la solicitud', array(contiene($r, 'role="dialog"'), contiene($r, 'aria-modal="true"'), contiene($r, 'name="confirmo"'), contiene($r, 'required'), contiene($r, 'Eddy')), array(true, true, true, true, true));
prueba('ventana de postulación: el creador no puede', contiene(g('form_postular', array('id' => 1), 3), 'No puedes postularte'), true);
$r = p('postular_narrador', array(), 9, array('id' => 1));
prueba('postular sin la casilla: 422 retarget al aviso de la ventana', array($r['estado'], cabecera($r, 'HX-Retarget')), array(422, '#aviso-modal'));
$r = p('postular_narrador', array('confirmo' => '1'), 9, array('id' => 1));
prueba('postular con confirmación: cierra la ventana, quita la tarjeta y avisa', array($r['estado'], contiene($r, 'id="av-1" hx-swap-oob="delete"'), contiene($r, 'Ahora narras la solicitud #1'), cabecera($r, 'HX-Trigger')), array(200, true, true, 'aventuraCambiada'));
prueba('la solicitud pasó a estado 0 con la narradora', array((int) fila(1)['estado'], (int) fila(1)['narrador_uid']), array(0, 9));
prueba('otro intento: conflicto', p('postular_narrador', array('confirmo' => '1'), 12, array('id' => 1))['estado'], 409);

// ── Salirse ──────────────────────────────────────────────────────────────────
$r = g('form_renunciar', array('id' => 1), 9);
prueba('ventana de salida: motivo y casilla requeridos', array(contiene($r, 'name="motivo"'), contiene($r, 'name="confirmo"'), contiene($r, 'volverá a la lista')), array(true, true, true));
prueba('ventana de salida: solo el narrador', contiene(g('form_renunciar', array('id' => 1), 12), 'No puedes dejar de narrar'), true);
prueba('salirse sin motivo: 422', p('renunciar_narrador', array('confirmo' => '1', 'motivo' => ''), 9, array('id' => 1))['estado'], 422);
$r = p('renunciar_narrador', array('confirmo' => '1', 'motivo' => 'Sin tiempo'), 9, array('id' => 1));
prueba('salirse: quita la tarjeta del narrador y avisa', array($r['estado'], contiene($r, 'id="av-1" hx-swap-oob="delete"'), contiene($r, 'Has dejado de narrar')), array(200, true, true));
prueba('la solicitud volvió a 4 con el motivo de renuncia', array((int) fila(1)['estado'], fila(1)['motivo_renuncia']), array(4, 'Sin tiempo'));
prueba('el motivo de renuncia se ve en la tarjeta', contiene(g('tab', array('tab' => 'narradores'), 12), 'El narrador anterior se salió'), true);

// ── Editar ───────────────────────────────────────────────────────────────────
$r = g('form_editar', array('id' => 1), 3);
prueba('formulario de edición con los datos actuales', array(contiene($r, 'Editar la solicitud #1'), contiene($r, 'value="5"'), contiene($r, 'name="isla_fid" value="40"'), contiene($r, 'value="12"')), array(true, true, true, true));
prueba('editar: solo el creador', contiene(g('form_editar', array('id' => 1), 9), 'Solo el creador'), true);
$ed = array_merge($valida, array('participantes' => array('5', '6'), 'dia' => '30', 'tier' => '4', 'comentario' => 'Editado'));
$r = p('editar', $ed, 3, array('id' => 1));
prueba('editar: devuelve la tarjeta actualizada', array($r['estado'], contiene($r, 'Verano 725, día 30'), contiene($r, 'Tier 4'), cabecera($r, 'HX-Trigger')), array(200, true, true, 'aventuraCambiada'));
prueba('editar con error: 422 retarget al formulario', array(p('editar', array_merge($ed, array('dia' => '0')), 3, array('id' => 1))['estado'], cabecera(p('editar', array_merge($ed, array('dia' => '0')), 3, array('id' => 1)), 'HX-Retarget')), array(422, '#aviso-form-1'));
prueba('editar ajena: 403', p('editar', $ed, 9, array('id' => 1))['estado'], 403);

// ── Pedir borrado y ver ──────────────────────────────────────────────────────
$r = p('pedir_borrado', array(), 3, array('id' => 1));
prueba('pedir borrado: quita la tarjeta (respuesta sin tarjeta) y avisa', array($r['estado'], contiene($r, 'Pediste el borrado'), contiene($r, 'id="av-1"')), array(200, true, false));
prueba('estado 5: ya no sale en ninguna pestaña', contiene(g('tab', array('tab' => 'mias'), 3), '#1'), false);
prueba('estado 5: el creador aún puede abrirla, otros no', array(g('ver', array('id' => 1), 3)['estado'], g('ver', array('id' => 1), 9)['estado']), array(200, 404));
prueba('pedir borrado de una ajena: 403', p('pedir_borrado', array(), 9, array('id' => 1))['estado'], 403);

// ── Mercado de narradores ────────────────────────────────────────────────────
$r = g('form_oferta', array(), 8);
prueba('formulario de la oferta', array(contiene($r, 'name="discord"'), contiene($r, 'name="mensaje"'), contiene($r, 'maxlength="64"'), contiene($r, 'maxlength="200"')), array(true, true, true, true));
prueba('formulario de la oferta sin ficha: 403', g('form_oferta', array(), 7)['estado'], 403);
$r = p('ofrecerme_narrador', array('discord' => '', 'mensaje' => 'x'), 8);
prueba('ofrecerse sin Discord: 422 retarget al aviso de la oferta', array($r['estado'], cabecera($r, 'HX-Retarget')), array(422, '#aviso-oferta'));
$r = p('ofrecerme_narrador', array('discord' => 'robin#1', 'mensaje' => 'Misterio <i>y</i> aventura'), 8);
prueba('ofrecerse: devuelve el bloque con la oferta propia, el Discord y el mensaje escapado', array($r['estado'], contiene($r, 'id="ta-narradores"'), contiene($r, 'robin#1'), contiene($r, 'Misterio &lt;i&gt;y&lt;/i&gt; aventura'), contiene($r, 'Vigente')), array(200, true, true, true, true));
$r = g('narradores_disponibles', array(), 9);
prueba('otra usuaria ve la oferta, sin botones de elegir ni de retirar la ajena', array(contiene($r, 'robin#1'), contiene($r, 'retirar_oferta'), contiene($r, 'Contactar'), contiene($r, 'elegir')), array(true, false, false, false));
prueba('renovar y retirar la propia', array(p('renovar_oferta', array(), 8)['estado'], contiene(p('retirar_oferta', array(), 8), 'Nadie se ha ofrecido como narrador todavía.')), array(200, true));
prueba('retirar sin oferta: 404', p('retirar_oferta', array(), 8)['estado'], 404);

// ── Vincular y entregar ──────────────────────────────────────────────────────
$A = ins(3, 8, 1, array(3, 5), array('tier_seleccionado' => 3, 'isla_fid' => 40, 'dia' => 5, 'narrador_nombre' => 'Robin'));
$r = g('tab', array('tab' => 'mias'));
prueba('Mis aventuras ofrece búsqueda reactiva por número de solicitud', array(contiene($r, 'name="q"'), contiene($r, 'placeholder="#850"'), contiene($r, 'input changed delay:250ms')), array(true, true, true));
$r = g('form_vincular', array('id' => $A), 8);
prueba('formulario de vincular: buscador de tema', array(contiene($r, 'name="q"'), contiene($r, 'action=buscar_temas&amp;destino=tema&amp;id=' . $A)), array(true, true));
prueba('vincular: rechaza al ajeno', contiene(g('form_vincular', array('id' => $A), 9), 'Ya no puedes vincular'), true);
prueba('participante puede abrir vinculación pero no entrega', array(contiene(g('form_vincular', array('id' => $A), 5), 'action=vincular_aventura'), contiene(g('form_entrega', array('id' => $A), 5), 'name="resumen"')), array(true, false));
prueba('participante puede buscar y elegir tema', array(contiene(g('buscar_temas', array('id' => $A, 'q' => 'ave'), 5), 'La aventura de Alfa'), contiene(g('elegir_tema', array('id' => $A, 'tid' => 100), 5), 'name="tema" value="100"')), array(true, true));
$r = p('vincular_aventura', array('tema' => '101'), 5, array('id' => $A));
prueba('participante vincula y queda auditado con su UID', array($r['estado'], fila($A)['aventura_url'], (int) opg_av_uno('SELECT uid FROM mybb_op_audit_aventuras WHERE peticion_id = ' . $A . ' AND accion = \'vincular\' ORDER BY id DESC LIMIT 1')['uid']), array(200, opg_av_url_tema(101), 5));
prueba('buscar temas con 2 caracteres', contiene(g('buscar_temas', array('id' => $A, 'q' => 'La'), 8), 'Escribe al menos 3 caracteres.'), true);
prueba('buscar temas con 3 caracteres: resultado de Aventura', array(contiene(g('buscar_temas', array('id' => $A, 'q' => 'ave'), 8), 'La aventura de Alfa'), contiene(g('buscar_temas', array('id' => $A, 'q' => 'ave'), 8), 'action=elegir_tema&amp;id=' . $A . '&amp;tid=100')), array(true, true));
prueba('buscar temas por enlace pegado', contiene(g('buscar_temas', array('id' => $A, 'q' => 'https://onepiecegaiden.com/showthread.php?tid=101'), 8), 'Otra de Alfa'), true);
prueba('buscar temas por tid pegado', contiene(g('buscar_temas', array('id' => $A, 'q' => '100'), 8), 'La aventura de Alfa'), true);
prueba('buscar temas con un enlace de otro host', contiene(g('buscar_temas', array('id' => $A, 'q' => 'https://example.com/showthread.php?tid=100'), 8), 'La aventura de Alfa'), false);
prueba('buscar temas: solo el narrador asignado', g('buscar_temas', array('id' => $A, 'q' => 'ave'), 9)['cuerpo'], '');
$r = g('elegir_tema', array('id' => $A, 'tid' => 100), 8);
prueba('elegir tema: chip con el tid', contiene($r, 'name="tema" value="100"'), true);
prueba('elegir tema devuelve chip sin buscador', contiene($r, 'name="q"'), false);
$r = p('vincular_aventura', array('tema' => '100'), 8, array('id' => $A));
prueba('vincular: devuelve la tarjeta con el enlace y el aviso', array($r['estado'], contiene($r, 'Aventura vinculada'), contiene($r, 'showthread.php?tid=100')), array(200, true, true));
$r = g('form_vincular', array('id' => $A), 5);
prueba('tema vinculado precargado muestra solo chip', array(contiene($r, 'name="tema" value="100"'), contiene($r, 'name="q"')), array(true, false));
$url_guardada = fila($A)['aventura_url'];
$r = g('quitar_tema', array('id' => $A), 5);
prueba('quitar tema restaura buscador sin modificar enlace guardado', array(contiene($r, 'name="q"'), contiene($r, 'name="tema"'), fila($A)['aventura_url']), array(true, false, $url_guardada));
prueba('ajeno no puede quitar el tema del formulario', g('quitar_tema', array('id' => $A), 9)['estado'], 403);
prueba('vincular un tema inválido: 422 retarget', array(p('vincular_aventura', array('tema' => '999'), 8, array('id' => $A))['estado'], cabecera(p('vincular_aventura', array('tema' => '999'), 8, array('id' => $A)), 'HX-Retarget')), array(422, '#aviso-form-' . $A));
$r = g('form_entrega', array('id' => $A), 8);
prueba('formulario de entrega: tema vinculado precargado, resumen, reputación y datos fijos', array(contiene($r, 'name="tema" value="100"'), contiene($r, 'name="resumen"'), contiene($r, 'name="reputacion"'), contiene($r, 'Tier 3'), contiene($r, 'Robin')), array(true, true, true, true, true));
prueba('entrega precargada tampoco muestra buscador de tema', contiene($r, 'name="q"'), false);
prueba('enviar entrega sin resumen: 422', p('enviar_entrega', array('tema' => '100', 'resumen' => '', 'reputacion' => 'positiva'), 8, array('id' => $A))['estado'], 422);
$r = p('enviar_entrega', array('tema' => '100', 'resumen' => 'Todo salió bien <b>x</b>', 'reputacion' => 'positiva'), 8, array('id' => $A));
prueba('enviar entrega: tarjeta con la entrega pendiente', array($r['estado'], contiene($r, 'Entrega pendiente de revisión'), contiene($r, 'Entrega enviada')), array(200, true, true));
prueba('después de enviar no ofrece entregar y conserva vincular mientras está aprobada', array(contiene(g('ver', array('id' => $A), 8), 'form_entrega'), contiene(g('ver', array('id' => $A), 8), 'form_vincular')), array(false, true));
prueba('el resumen no se muestra a los jugadores en la tarjeta (solo el staff lo ve en la página de recompensas)', contiene(g('ver', array('id' => $A), 8), 'Todo salió bien'), false);
prueba('segunda entrega: la tarjeta con aviso y conflicto', contiene(p('enviar_entrega', array('tema' => '100', 'resumen' => 'x', 'reputacion' => 'positiva'), 8, array('id' => $A)), 'Ya hay una entrega pendiente'), true);

// ── Coherencia: toda acción enlazada existe en la lista blanca de la página ──
$nar = ins(3, 3, 1, array(5), array('isla_fid' => 40, 'tier_seleccionado' => 2, 'dia' => 16));
$r = g('mis_activas', array('detalle' => 1));
prueba('activas separan personaje con cupo y narradas sin límite', array(contiene($r, 'Activas de mi personaje'), contiene($r, 'Aventuras que narro'), contiene($r, '1 activa · Sin límite'), contiene($r, '#'.$nar)), array(true, true, true, true));
prueba('narrar no consume el cupo del personaje', opg_av_contar_activas(3), array(0, 1));
sql('DELETE FROM mybb_op_peticionAventuras WHERE id = ' . (int) $nar);
$r = g('mis_activas', array('detalle' => 1));
prueba('activas muestran título vinculado, fecha de registro y fecha de rol', array(contiene($r, 'La aventura de Alfa'), contiene($r, 'Registrada: ' . date('d/m/Y', (int) fila($A)['created_at'])), contiene($r, 'Verano 725, día 5')), array(true, true, true));
sql("UPDATE mybb_threads SET subject = '<img src=x onerror=alert(1)> Aventura' WHERE tid = 100");
$r = g('mis_activas', array('detalle' => 1));
prueba('títulos de activas se escapan', array(contiene($r, '&lt;img src=x onerror=alert(1)&gt; Aventura'), contiene($r, '<img src=x')), array(true, false));
sql('UPDATE mybb_threads SET visible = 0 WHERE tid = 100');
prueba('activas no exponen título de tema oculto', contiene(g('mis_activas', array('detalle' => 1)), 'onerror=alert'), false);
sql("UPDATE mybb_threads SET visible = 1, subject = 'La aventura de Alfa' WHERE tid = 100");
$base = opg_av_estadisticas(array('yo' => 3, 'ambito' => 'mias'));
for ($i = 0; $i < 25; $i++) {
    ins(12, 8, 0, $i % 2 ? array(3, 5) : array(3), array('isla_fid' => 40, 'tier_seleccionado' => 2, 'dia' => 15));
}
$stats = opg_av_estadisticas(array('yo' => 3, 'ambito' => 'mias'));
prueba('estadísticas incluyen todas las páginas y roles', array($stats['total'] - $base['total'], $stats['estado_0'] - $base['estado_0'], $stats['jugador'] - $base['jugador'], $stats['narrador'] - $base['narrador']), array(25, 25, 25, 0));
$r = g('listar', array('ambito' => 'mias', 'estado' => 'creado', 'pagina' => 1));
preg_match_all('/<article[^>]+id="av-/', $r['cuerpo'], $cards);
prueba('primera página tiene 20 tarjetas, total 25 y estadísticas fuera de banda', array(count($cards[0]), contiene($r, '25 aventuras'), contiene($r, 'Página 1 de 2'), contiene($r, 'id="ta-estadisticas-mias"'), cabecera($r, 'HX-Reswap')), array(20, true, true, true, 'outerHTML'));
$r = g('listar', array('ambito' => 'mias', 'estado' => 'creado', 'pagina' => 2));
preg_match_all('/<article[^>]+id="av-/', $r['cuerpo'], $cards);
prueba('segunda página conserva filtro y tiene 5 tarjetas', array(count($cards[0]), contiene($r, 'Página 2 de 2'), contiene($r, 'estado=creado')), array(5, true, true));
$r = g('listar', array('ambito' => 'mias', 'q' => '#' . $A));
prueba('Mis aventuras filtra por número de solicitud con #', array(contiene($r, 'id="av-' . $A . '"'), contiene($r, '1 aventuras')), array(true, true));
$r = g('listar', array('ambito' => 'mias', 'q' => 'no-es-un-id'));
prueba('Mis aventuras no interpreta texto como un número de solicitud', contiene($r, 'No hay aventuras con estos filtros.'), true);
$otras = opg_av_estadisticas(array('yo' => 9, 'ambito' => 'otras', 'uid' => 3, 'estado' => 'creado'));
prueba('estadísticas de Otras filtran por UID y estado en todo el conjunto', array($otras['total'], $otras['estado_0'], $otras['estado_1'], $otras['jugador'], $otras['narrador']), array(25, 25, 0, 25, 0));
$r = g('listar', array('ambito' => 'otras', 'uid' => 3, 'estado' => 'creado', 'pagina' => 2), 9);
prueba('Otras pagina con los mismos filtros y estadísticas', array(contiene($r, 'Página 2 de 2'), contiene($r, 'uid=3'), contiene($r, 'id="ta-estadisticas-otras"')), array(true, true, true));
$r = g('listar', array('ambito' => 'otras', 'q' => 'Eddy', 'estado' => 'creado'), 9);
prueba('Otras aventuras filtra por nombre de personaje', array(contiene($r, '25 aventuras'), contiene($r, 'id="av-' . $A . '"')), array(true, false));
$r = g('listar', array('ambito' => 'otras', 'q' => '#' . $A), 9);
prueba('Otras aventuras filtra por número de solicitud', array(contiene($r, 'id="av-' . $A . '"'), contiene($r, '1 aventuras')), array(true, true));
$r = g('mis_activas', array('detalle' => 1));
prueba('Mis activas enumera solitarias y grupales sin limitar a 20', array(contiene($r, 'Solitarias'), contiene($r, 'Grupales'), substr_count($r['cuerpo'], '<li>')), array(true, true, 26));
prueba('filtrar por denegadas conserva las activas', contiene(g('tab', array('tab' => 'mias', 'estado' => 'denegado')), '26 / 4 en total'), true);
$r = p('crear', array_merge($valida, array('dia' => '24')));
prueba('exceder límite bloquea crear en servidor con aviso de formulario', array($r['estado'], cabecera($r, 'HX-Retarget'), contiene($r, 'No se puede enviar esta solicitud.')), array(422, '#aviso-form-crear', true));
$r = p('crear', array_merge($valida, array('comentario' => '   ')));
prueba('descripción pública es obligatoria en servidor', array($r['estado'], contiene($r, 'Falta el comentario público.')), array(422, true));
$r = g('form_participante');
prueba('Añadir participante abre el buscador', array(contiene($r, 'ta-selector-abierto'), contiene($r, 'name="q"')), array(true, true));
$r = g('elegir_ficha', array('fid' => 8, 'destino' => 'narrador'));
prueba('selección de narrador devuelve solo chip y quitar restaura buscador', array(contiene($r, 'name="q"'), contiene(g('quitar_narrador'), 'Tu narrador')), array(false, true));
prueba('Día tiene placeholder de rango', contiene(g('tab', array('tab' => 'solicitud')), 'placeholder="1-90"'), true);
$ultimo = 0;
for ($i = 0; $i < 12; $i++) { $ultimo = ins(12, null, 4, array(12), array('idea_narrador' => 'Oferta lejana')); }
$r = llamar($PAG, array('id' => $ultimo), array(), 3, false, null, 'GET');
prueba('enlace directo a pendiente fuera de las primeras 10 abre mercado y resalta', array(contiene($r, 'ta-tab-activa" href="tablon_de_aventuras.php?tab=narradores"'), contiene($r, 'ta-resaltada" id="av-' . $ultimo . '"')), array(true, true));
$sinRol = ins(3, 8, 1, array(5));
prueba('creador sin rol de participante ni narrador no vincula', p('vincular_aventura', array('tema' => '101'), 3, array('id' => $sinRol))['estado'], 403);

preg_match_all('/hx-(?:get|post)="[^"]*action=([a-z_]+)/', $todo_html, $m);
$enlazadas = array_unique($m[1]);
$permitidas = array_merge($GET_ACC, $POST_ACC);
$faltan = array_values(array_diff($enlazadas, $permitidas));
prueba('todas las acciones enlazadas en el HTML existen en la página', $faltan, array());
prueba('el HTML producido no contiene hx-on ni scripts', array(preg_match('/\shx-on[:\-]/', $todo_html), strpos($todo_html, '<script')), array(0, false));
echo "  (acciones enlazadas: " . implode(', ', $enlazadas) . ")\n";
resumen();
