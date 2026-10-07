<?php
/**
 * Pruebas de op/staff/aventuras_recompensas.php como página HTTP (harness + MySQL de pruebas).
 *   php tests/aventuras/pagina_recompensas.php
 */
require __DIR__ . '/bootstrap_db.php';
require __DIR__ . '/http.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
preparar_esquema($db);
$GLOBALS['roles'] = array('mod' => array(1), 'staff' => array(50), 'narra' => array(60));

foreach (array(3 => 'Eddy', 5 => 'Luffy', 6 => 'Zoro', 8 => 'Robin', 9 => 'Nami', 20 => 'Brook', 50 => 'Staff', 60 => 'NarraStaff') as $u => $n) { crear_usuario($u, $n); }
foreach (array(3, 5, 6, 8, 20) as $u) { sql("UPDATE mybb_op_fichas SET nika = 0, berries = 0, reputacion = 0, reputacion_positiva = 0, reputacion_negativa = 0, movidoInframundo = 0, kuro = 0, expNarradorMensualActual = 0, oficios = '', nivelnarrador = 'Aprendiz' WHERE fid = $u"); sql("UPDATE mybb_users SET newpoints = 0 WHERE uid = $u"); }
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (15, 'Blue 15', 0, '15', 1, 15), (40, 'Isla Alfa', 15, '15,40', 1, 40)");
$m = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m');
sql("DELETE FROM mybb_settings WHERE name = 'exp_narrador_ultimo_reset'");
sql("INSERT INTO mybb_settings (name, title, description, optionscode, value) VALUES ('exp_narrador_ultimo_reset', 't', 'd', 'text', '$m')");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);

function msg($n = 320) { return str_repeat('a', $n); }
function tema($tid, $nombre, $posts)
{
    global $db;
    $db->query("SET SESSION sql_mode = ''", 1);
    sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline, year, estacion, day) VALUES ($tid, 40, '" . $db->escape_string($nombre) . "', 3, 0, 1, " . (TIME_NOW - $tid) . ", '725', 'Verano', '12')");
    $pid = $tid * 1000;
    foreach ($posts as $p) {
        sql("INSERT INTO mybb_posts (pid, tid, uid, username, message, dateline) VALUES (" . (++$pid) . ", $tid, {$p[0]}, '" . $db->escape_string($p[1]) . "', '" . $db->escape_string($p[2]) . "', " . (TIME_NOW + $pid) . ")");
    }
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}
function posts_std($narr = 8, $nn = 'Robin')
{
    $p = array();
    for ($i = 0; $i < 8; $i++) { $p[] = array($narr, $nn, '[narrador] ' . msg()); }
    for ($i = 0; $i < 6; $i++) { $p[] = array(3, 'Eddy', msg()); }
    for ($i = 0; $i < 4; $i++) { $p[] = array(5, 'Luffy', msg()); }
    for ($i = 0; $i < 6; $i++) { $p[] = array(6, 'Zoro', msg()); }
    return $p;
}
function entrega($pid, $tid, $nombre, $estado = 'pendiente')
{
    global $db;
    sql("INSERT INTO mybb_op_aventuras_entregas (peticion_id, tid, tid_activo, tema_nombre, enviado_por, resumen, reputacion, estado, enviado_at) VALUES ($pid, $tid, " . ($estado === 'pendiente' ? $tid : 'NULL') . ", '" . $db->escape_string($nombre) . "', 8, '" . $db->escape_string('Resumen <b>x</b> del narrador') . "', 'positiva', '$estado', " . TIME_NOW . ")");
    return (int) $db->insert_id();
}

$PAG = 'op/staff/aventuras_recompensas.php';
$GET_ACC = array('form_rechazar', 'cerrar');
$POST_ACC = array('analizar', 'pagar', 'rechazar', 'completar', 'xp_dudosa');
$todo_html = ''; $GLOBALS['respuestas'] = array();
function g($accion, $get = array(), $uid = 50) { global $PAG, $todo_html; $r = llamar($PAG, array_merge(array('action' => $accion), $get), array(), $uid, true, 'clave-valida', 'GET'); $todo_html .= $r['cuerpo']; $GLOBALS['respuestas'][] = $r['cuerpo']; return $r; }
function p($accion, $post = array(), $uid = 50, $get = array()) { global $PAG, $todo_html; $r = llamar($PAG, array_merge(array('action' => $accion), $get), $post, $uid, true, 'clave-valida', 'POST'); $todo_html .= $r['cuerpo']; $GLOBALS['respuestas'][] = $r['cuerpo']; return $r; }
function pagina($get, $uid = 50) { global $PAG, $todo_html; $r = llamar($PAG, $get, array(), $uid, false, null, 'GET'); $todo_html .= $r['cuerpo']; return $r; }
function contiene($r, $texto) { return strpos($r['cuerpo'], $texto) !== false; }
function ficha_de($uid) { return una("SELECT f.nika, f.berries, f.reputacion, f.expNarradorMensualActual AS exp_mes, u.newpoints FROM mybb_op_fichas f JOIN mybb_users u ON u.uid = f.fid WHERE f.fid = " . (int) $uid); }

tema(300, 'Aventura <i>de</i> prueba', posts_std());
$S = ins(3, 8, 1, array(3, 5, 6), array('tier_seleccionado' => 3, 'isla_fid' => 40));
$E = entrega($S, 300, 'Aventura <i>de</i> prueba');

// ── Acceso ───────────────────────────────────────────────────────────────────
$r = pagina(array('id' => $E), 3);
prueba('usuario sin permiso: pantalla de redirección', array(contiene($r, 'No tienes acceso'), contiene($r, 'ar-panel')), array(true, false));
prueba('usuario sin permiso: las acciones responden 404', llamar($PAG, array('action' => 'analizar', 'id' => $E), array(), 3, true, 'clave-valida', 'POST')['estado'], 404);
foreach (array(1, 50, 60) as $u) {
    prueba("uid $u entra", array(pagina(array('id' => $E), $u)['estado'], contiene(pagina(array('id' => $E), $u), 'id="ar-panel"')), array(200, true));
}

// ── Página completa ──────────────────────────────────────────────────────────
$r = pagina(array('id' => $E));
prueba('muestra la cabecera con el tema y el resumen escapados', array(contiene($r, 'Entrega #' . $E . ' de la solicitud #' . $S), contiene($r, 'Aventura &lt;i&gt;de&lt;/i&gt; prueba'), contiene($r, 'Resumen &lt;b&gt;x&lt;/b&gt; del narrador'), contiene($r, 'Resumen <b>')), array(true, true, true, false));
prueba('muestra los ajustes y a los cuatro destinatarios por posts', array(contiene($r, 'name="tier"'), contiene($r, 'value="3" selected'), contiene($r, 'name="d[8][variacion]"'), contiene($r, 'name="d[3][incluir]"'), contiene($r, 'name="d[5][rep]"'), contiene($r, 'name="d[8][rep]"')), array(true, true, true, true, true, false));
prueba('el tier aparece con sus posts esperados', contiene($r, 'Tier 10 (12 posts por participante)'), true);
prueba('mínimos cumplidos: sin bloqueo y Pagar habilitado', array(contiene($r, 'No se cumplen los posts mínimos'), contiene($r, 'name="confirma_minimos"'), preg_match('/action=pagar[^>]*disabled/', $r['cuerpo']), contiene($r, 'ar-cumple')), array(false, false, 0, true));
tema(303, 'Aventura corta', posts_std());
$S4 = ins(3, 8, 1, array(3, 5, 6), array('tier_seleccionado' => 8));
$E4 = entrega($S4, 303, 'Aventura corta');
$r4 = pagina(array('id' => $E4));
prueba('sin los mínimos: bloqueo, casilla de confirmar y Pagar deshabilitado', array(contiene($r4, 'No se cumplen los posts mínimos'), contiene($r4, 'name="confirma_minimos" value="1"'), preg_match('/action=pagar[^>]*disabled/', $r4['cuerpo']), contiene($r4, 'ar-no-cumple')), array(true, true, 1, true));
prueba('carga HTMX y la clave', array(contiene($r, 'htmx.min.js?v=2.0.4'), contiene($r, '{"X-Post-Key":"clave-valida"}')), array(true, true));
prueba('enlaza de vuelta a la consola', contiene($r, 'consola_aventuras.php?tab=entregas'), true);
$r2 = pagina(array('tid' => 300));
prueba('?tid= abre la misma entrega', contiene($r2, 'Entrega #' . $E . ' de la solicitud'), true);
prueba('?id inexistente', contiene(pagina(array('id' => 9999)), 'No hay ninguna entrega con ese número o ese tema.'), true);
prueba('sin id', contiene(pagina(array()), 'Elige una entrega desde la consola de aventuras.'), true);

// ── Contrato HTMX ────────────────────────────────────────────────────────────
prueba('sin HX-Request: 404', llamar($PAG, array('action' => 'form_rechazar', 'id' => $E), array(), 50, false, null, 'GET')['estado'], 404);
prueba('acción desconocida: 404', g('inventada')['estado'], 404);
foreach (array('cambiar_estado', 'actualizar_datos', 'eliminar', 'crear', 'historial') as $a) {
    prueba("la acción '$a' de otra página responde 404", array(llamar($PAG, array('action' => $a), array(), 50, true, 'clave-valida', 'GET')['estado'], llamar($PAG, array('action' => $a, 'id' => $E), array(), 50, true, 'clave-valida', 'POST')['estado']), array(404, 404));
}
prueba('POST pedida por GET: 404', g('pagar', array('id' => $E))['estado'], 404);
prueba('POST sin clave: 403', llamar($PAG, array('action' => 'pagar', 'id' => $E), array(), 50, true, null, 'POST')['estado'], 403);
prueba('POST con clave inválida: 403', llamar($PAG, array('action' => 'pagar', 'id' => $E), array(), 50, true, 'otra', 'POST')['estado'], 403);
prueba('entrega inexistente en una acción: 404', p('analizar', array(), 50, array('id' => 9999))['estado'], 404);
prueba('sin caché', cabecera(g('form_rechazar', array('id' => $E)), 'Cache-Control'), 'no-store');

// ── Analizar ─────────────────────────────────────────────────────────────────
$aj = array('tier' => '3', 'extra_jugadores' => '0', 'n_participantes' => '3', 'confirma_minimos' => '1',
    'd' => array(8 => array('incluir' => '1', 'variacion' => '0'), 3 => array('incluir' => '1', 'variacion' => '0', 'rep' => 'positiva'),
        5 => array('incluir' => '1', 'variacion' => '0', 'rep' => 'positiva'), 6 => array('incluir' => '1', 'variacion' => '0', 'rep' => 'positiva')));
$r = p('analizar', $aj, 50, array('id' => $E));
prueba('analizar con la confirmación: Pagar habilitado y vista previa', array($r['estado'], preg_match('/action=pagar[^>]*disabled/', $r['cuerpo']), contiene($r, 'Experiencia 120'), contiene($r, 'Cofre')), array(200, 0, true, true));
$aj2 = $aj; $aj2['d'][6]['incluir'] = '0'; $aj2['tier'] = '5';
$r = p('analizar', $aj2, 50, array('id' => $E));
prueba('analizar con otro tier y un destinatario excluido', array(contiene($r, 'value="5" selected'), contiene($r, 'ar-excluido')), array(true, true));
$aj3 = $aj; $aj3['tier'] = '99';
prueba('analizar con un tier inválido: el panel avisa', contiene(p('analizar', $aj3, 50, array('id' => $E)), 'El tier debe ser un número de 1 a 10'), true);
$aj4 = $aj; $aj4['d'][3]['variacion'] = '5000';
prueba('variación fuera de rango: aviso', contiene(p('analizar', $aj4, 50, array('id' => $E)), 'La variación de posts debe estar entre'), true);
prueba('analizar no escribe nada', array(ficha_de(3)['nika'], (int) una("SELECT COUNT(*) n FROM mybb_op_aventuras_entregas_pagos")['n']), array('0', 0));

// ── Rechazar ─────────────────────────────────────────────────────────────────
tema(301, 'Aventura a rechazar', posts_std());
$S2 = ins(3, 8, 1, array(3, 5, 6), array('tier_seleccionado' => 3));
$E2 = entrega($S2, 301, 'Aventura a rechazar');
prueba('formulario de rechazo con motivo', array(contiene(g('form_rechazar', array('id' => $E2)), 'name="motivo"'), contiene(g('form_rechazar', array('id' => $E2)), 'required')), array(true, true));
$r = p('rechazar', array('motivo' => ''), 50, array('id' => $E2));
prueba('rechazar sin motivo: 422 retarget', array($r['estado'], cabecera($r, 'HX-Retarget')), array(422, '#ar-aviso-rechazo'));
$r = p('rechazar', array('motivo' => 'Falta el cierre <b>x</b>'), 50, array('id' => $E2));
prueba('rechazar: panel con el motivo escapado', array($r['estado'], contiene($r, 'Entrega rechazada. El narrador puede corregirla'), contiene($r, 'Falta el cierre &lt;b&gt;x&lt;/b&gt;'), contiene($r, 'action=pagar')), array(200, true, true, false));
prueba('la entrega quedó rechazada y libre', array(una("SELECT estado FROM mybb_op_aventuras_entregas WHERE id = $E2")['estado'], una("SELECT tid_activo FROM mybb_op_aventuras_entregas WHERE id = $E2")['tid_activo']), array('rechazada', null));
prueba('rechazar otra vez: 409', p('rechazar', array('motivo' => 'x'), 50, array('id' => $E2))['estado'], 409);
prueba('pagar una rechazada: 409', p('pagar', $aj, 50, array('id' => $E2))['estado'], 409);

// ── Permisos: nadie paga su propia aventura ─────────────────────────────────
tema(302, 'Aventura de la narra', posts_std());
$S3 = ins(3, 8, 1, array(3, 60), array('tier_seleccionado' => 3));
$E3 = entrega($S3, 302, 'Aventura de la narra');
$r = p('pagar', $aj, 60, array('id' => $E3));
prueba('un participante narra no paga la suya: 403', array($r['estado'], contiene($r, 'No puedes resolver una aventura en la que participas.')), array(403, true));
prueba('rechazar la propia: 403', p('rechazar', array('motivo' => 'x'), 60, array('id' => $E3))['estado'], 403);

// ── Completar sin pagos iniciados ────────────────────────────────────────────
prueba('completar sin pagos iniciados: 409', p('completar', array(), 50, array('id' => $E3))['estado'], 409);

// ── Pagar ────────────────────────────────────────────────────────────────────
$r = p('pagar', array_merge($aj, array('tier' => '8', 'confirma_minimos' => '0')), 50, array('id' => $E4));
prueba('pagar sin confirmar mínimos: 422 retarget y nada cambia', array($r['estado'], cabecera($r, 'HX-Retarget'), ficha_de(3)['nika'], (int) una("SELECT COUNT(*) n FROM mybb_op_aventuras_entregas_pagos")['n']), array(422, '#ar-aviso', '0', 0));
$r = p('pagar', $aj, 50, array('id' => $E));
prueba('pagar: panel con aviso, resultados y estado entregada', array($r['estado'], contiene($r, 'Recompensas entregadas y tema cerrado.'), contiene($r, 'Pagos registrados'), contiene($r, 'Pagado'), contiene($r, 'Entregada')), array(200, true, true, true, true));
prueba('las fichas cobran', array(ficha_de(3)['nika'], ficha_de(8)['exp_mes'], ficha_de(6)['newpoints']), array('20', '140', '120.00'));
prueba('tema cerrado y solicitud finalizada', array((int) una("SELECT closed FROM mybb_threads WHERE tid = 300")['closed'], (int) fila($S)['estado']), array(1, 3));
prueba('un solo mensaje de recompensas', (int) una("SELECT COUNT(*) n FROM mybb_posts WHERE tid = 300 AND message LIKE '%RECOMPENSAS PROCESADAS%'")['n'], 1);
$r = p('pagar', $aj, 50, array('id' => $E));
prueba('pagar de nuevo: 409 y no cobra dos veces', array($r['estado'], ficha_de(3)['nika']), array(409, '20'));
$r = pagina(array('id' => $E));
prueba('la página de una entrega pagada muestra solo el resultado', array(contiene($r, 'Pagos registrados'), contiene($r, 'name="tier"'), contiene($r, 'action=pagar')), array(true, false, false));

// ── Experiencia en duda ──────────────────────────────────────────────────────
sql("UPDATE mybb_op_aventuras_entregas_pagos SET xp_estado = 'dudoso' WHERE entrega_id = $E AND uid = 3");
$r = pagina(array('id' => $E));
prueba('experiencia dudosa: ofrece las dos decisiones', array(contiene($r, 'Experiencia en duda'), contiene($r, 'decision=aplicada'), contiene($r, 'decision=aplicar')), array(true, true, true));
prueba('decisión inválida: 422', p('xp_dudosa', array(), 50, array('id' => $E, 'uid' => 3, 'decision' => 'x'))['estado'], 422);
prueba('un pago sin duda: 409', p('xp_dudosa', array(), 50, array('id' => $E, 'uid' => 5, 'decision' => 'aplicada'))['estado'], 409);
$antes = ficha_de(3)['newpoints'];
$r = p('xp_dudosa', array(), 50, array('id' => $E, 'uid' => 3, 'decision' => 'aplicar'));
prueba('sumarla ahora: suma la experiencia y marca aplicada', array($r['estado'], contiene($r, 'Experiencia resuelta.'), contiene($r, 'Experiencia en duda'), (float) ficha_de(3)['newpoints'] - (float) $antes), array(200, true, false, 120.0));

// ── Coherencia ───────────────────────────────────────────────────────────────
preg_match_all('/hx-(?:get|post)="[^"]*action=([a-z_]+)/', $todo_html, $m);
$enlazadas = array_unique($m[1]);
$faltan = array_values(array_diff($enlazadas, array_merge($GET_ACC, $POST_ACC)));
prueba('todas las acciones enlazadas existen en la página', $faltan, array());
prueba('sin hx-on ni scripts en los fragmentos', array(preg_match('/\shx-on[:\-]/', $todo_html), strpos(str_replace('<script src="https://onepiecegaiden.com/jscripts/htmx/htmx.min.js?v=2.0.4" defer></script>', '', $todo_html), '<script')), array(0, false));
prueba('las rutas relativas apuntan a esta página (o a la consola)', preg_match('/hx-(?:get|post)="(?!aventuras_recompensas\.php)/', $todo_html), 0);
prueba('HTML bien formado y sin ids repetidos en cada respuesta', problemas_html($GLOBALS['respuestas']), array());
echo "  (acciones enlazadas: " . implode(', ', $enlazadas) . ")\n";
resumen();
