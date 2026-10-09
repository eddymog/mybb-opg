<?php
/** Parte 4b de dominio_db.php: pago de recompensas, pago único, reanudación y tope mensual. */
if (empty($GLOBALS['continuar'])) { exit("Ejecuta tests/aventuras/dominio_db.php\n"); }

function ficha_de($uid) { $r = una("SELECT f.nika, f.berries, f.reputacion, f.reputacion_positiva, f.reputacion_negativa, f.movidoInframundo, f.kuro, f.expNarradorMensualActual AS exp_mes, u.newpoints FROM mybb_op_fichas f JOIN mybb_users u ON u.uid = f.fid WHERE f.fid = " . (int) $uid); $r['newpoints'] = (string) (float) $r['newpoints']; return $r; }
function inventario($uid, $obj) { $r = una("SELECT cantidad FROM mybb_op_inventario WHERE uid = " . (int) $uid . " AND objeto_id = '$obj'"); return $r ? (int) $r['cantidad'] : 0; }
function marcadores($tid) { return (int) una("SELECT COUNT(*) AS n FROM mybb_posts WHERE tid = " . (int) $tid . " AND message LIKE '%RECOMPENSAS PROCESADAS%'")['n']; }
function tema_cerrado($tid) { return (int) una("SELECT closed FROM mybb_threads WHERE tid = " . (int) $tid)['closed']; }
function resetear_fichas() { foreach (array(3, 5, 6, 8, 20) as $u) { sql("UPDATE mybb_op_fichas SET nika = 0, berries = 0, reputacion = 0, reputacion_positiva = 0, reputacion_negativa = 0, movidoInframundo = 0, kuro = 0, expNarradorMensualActual = 0, oficios = '', nivelnarrador = 'Aprendiz' WHERE fid = $u"); sql("UPDATE mybb_users SET newpoints = 0 WHERE uid = $u"); } sql("DELETE FROM mybb_op_inventario"); }
function mes_actual_madrid() { return (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m'); }
function fijar_reset_al_mes_actual()
{
    $m = mes_actual_madrid();
    $db_ = $GLOBALS['db'];
    $db_->query("SET SESSION sql_mode = ''", 1);
    sql("DELETE FROM mybb_settings WHERE name = 'exp_narrador_ultimo_reset'");
    sql("INSERT INTO mybb_settings (name, title, description, optionscode, value) VALUES ('exp_narrador_ultimo_reset', 't', 'd', 'text', '$m')");
    $db_->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}
$GLOBALS['log_general'] = array();

// ── Pagar: permisos y condiciones ────────────────────────────────────────────
resetear_fichas();
sql("DELETE FROM mybb_op_aventuras_entregas_pagos");
$E = (int) una("SELECT id FROM mybb_op_aventuras_entregas WHERE peticion_id = $S")['id'];
$S_ = $S;
prueba('pagar una entrega inexistente', opg_ent_pagar(99999, array(), 50)['codigo'], 404);
prueba('un usuario normal no paga', opg_ent_pagar($E, array('confirma_minimos' => '1'), 9)['codigo'], 403);
prueba('invitado no paga', opg_ent_pagar($E, array('confirma_minimos' => '1'), 0)['codigo'], 403);
$GLOBALS['roles']['narra'][] = 6;   // un participante narrador del staff
prueba('un participante narrador del staff puede resolver su aventura', opg_av_puede_resolver(opg_av_obtener($S), 6), true);
$GLOBALS['roles']['narra'] = array(60);
$r = opg_ent_pagar($E, array(), 50);
prueba('sin confirmar los mínimos no se paga', array($r['ok'], $r['codigo'], count($r['errores']) > 0), array(false, 422, true));
prueba('un pago rechazado no deja la entrega reclamada', una("SELECT procesando_at FROM mybb_op_aventuras_entregas WHERE id = $E")['procesando_at'], null);
prueba('un pago rechazado no tocó las fichas', ficha_de(3)['nika'], '0');
sql("UPDATE mybb_op_aventuras_entregas SET procesando_por = 99, procesando_at = " . TIME_NOW . " WHERE id = $E");
prueba('otra persona la está procesando', opg_ent_pagar($E, array('confirma_minimos' => '1'), 50)['codigo'], 409);
sql("UPDATE mybb_op_aventuras_entregas SET procesando_por = NULL, procesando_at = NULL WHERE id = $E");
sql("UPDATE mybb_op_aventuras_entregas SET procesando_por = 99, procesando_at = " . (TIME_NOW - 500) . " WHERE id = $E");   // reclamo caducado
fijar_reset_al_mes_actual();

// ── Pagar ────────────────────────────────────────────────────────────────────
$r = opg_ent_pagar($E, array('confirma_minimos' => '1'), 50);
prueba('pagar', array($r['ok'], $r['pagados']), array(true, 4));
$f3 = ficha_de(3); $f5 = ficha_de(5); $f6 = ficha_de(6); $f8 = ficha_de(8); $f20 = ficha_de(20);
prueba('jugador 3 al 100%: nikas, berries, reputación (negativa) y experiencia', array($f3['nika'], $f3['berries'], $f3['reputacion'], $f3['reputacion_negativa'], $f3['reputacion_positiva'], $f3['newpoints']), array('20', '10000000', '50', '50', '0', '120'));
prueba('jugador 5 al 66,67%', array($f5['nika'], $f5['berries'], $f5['reputacion'], $f5['newpoints']), array('13', '6667000', '33', '80'));
prueba('jugador 6 al 100%', array($f6['nika'], $f6['berries'], $f6['newpoints']), array('20', '10000000', '120'));
prueba('narrador Aprendiz T3 al 116,67%: nikas, experiencia mensual y experiencia', array($f8['nika'], $f8['exp_mes'], $f8['newpoints']), array('26', '140', '140'));
prueba('el narrador recibe sus cofres (CFN003 x2)', inventario(8, 'CFN003'), 2);
prueba('el narrador no recibe reputación ni berries', array($f8['reputacion'], $f8['berries']), array('0', '0'));
prueba('quien escribió sin estar en la solicitud no cobra', array($f20['nika'], $f20['newpoints']), array('0', '0'));
$e = opg_ent_obtener_por_id($E);
prueba('la entrega queda entregada con su tier y los ajustes congelados', array($e['estado'], $e['tier'], $e['entregado_at'] !== null, $e['ajustes']['tier'], $e['ajustes']['confirma_minimos']), array('entregada', 3, true, 3, true));
prueba('la solicitud pasa a finalizada', estado_de($S), 3);
prueba('el tema se cierra y lleva un solo mensaje de recompensas', array(tema_cerrado(200), marcadores(200)), array(1, 1));
$msg = una("SELECT message, uid FROM mybb_posts WHERE tid = 200 AND message LIKE '%RECOMPENSAS PROCESADAS%'");
prueba('el mensaje lo publica el usuario 5 y menciona a cada destinatario', array((int) $msg['uid'], strpos($msg['message'], 'Robin') !== false, strpos($msg['message'], 'Eddy') !== false, strpos($msg['message'], 'Zoro') !== false), array(5, true, true, true));
$pagos = opg_av_todos("SELECT uid, rol, aplicado_at, xp_estado FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E ORDER BY uid");
prueba('4 pagos registrados, todos aplicados con experiencia aplicada', array(count($pagos), count(array_filter($pagos, function ($p) { return $p['aplicado_at'] !== null && $p['xp_estado'] === 'aplicado'; }))), array(4, 4));
prueba('el log general usa las categorías de siempre', array_count_values(array_column($GLOBALS['log_general'], 'categoria'))['[Entregas][Aventura Usuario]'] === 3 && array_count_values(array_column($GLOBALS['log_general'], 'categoria'))['[Entregas][Aventura Narradores]'] === 1, true);
prueba('el log escapa el HTML de los nombres', strpos(implode('', array_column($GLOBALS['log_general'], 'log')), '<script>') === false, true);
prueba('pagar auditado', (int) una("SELECT COUNT(*) AS n FROM mybb_op_audit_aventuras WHERE accion = 'pagar_entrega' AND peticion_id = $S")['n'], 1);
prueba('pagar otra vez no paga de nuevo', array(opg_ent_pagar($E, array('confirma_minimos' => '1'), 50)['codigo'], ficha_de(3)['nika'], ficha_de(8)['newpoints']), array(409, '20', '140'));
$res = opg_ent_resultado($E);
prueba('resultado de la entrega', array(count($res), $res[0]['pagado'], $res[0]['username']), array(4, true, 'Robin'));
prueba('conteos tras pagar', opg_ent_conteos()['entregada'], 1);
$li = opg_ent_listar('entregada', 1);
prueba('listar entregas con su solicitud', array($li['total'], $li['items'][0]['entrega']['id'], $li['items'][0]['av']['id']), array(1, $E, $S));

// ── Un tema ya pagado por la herramienta antigua ─────────────────────────────
tema_con_posts(210, 40, 'Aventura pagada con la herramienta antigua', posts_estandar());
sql("UPDATE mybb_threads SET closed = 1 WHERE tid = 210");
$Sx = nueva_sol(3, 0, 8, array(3));
prueba('no se puede entregar un tema cerrado (pagado por la herramienta antigua)', opg_ent_enviar($Sx, array('tema' => '210', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['codigo'], 422);
tema_con_posts(211, 40, 'Aventura con mensaje de recompensas', posts_estandar());
crear_post(9999, 211, 5, 'U5', 'RECOMPENSAS PROCESADAS');
prueba('no se puede entregar un tema con el mensaje de recompensas', opg_ent_enviar($Sx, array('tema' => '211', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['codigo'], 422);
// Pagado por la antigua DESPUÉS de enviar: el análisis lo bloquea
tema_con_posts(212, 40, 'Aventura que se paga por la antigua', posts_estandar());
$Sy = nueva_sol(3, 0, 8, array(3, 5, 6));
$Ey = opg_ent_enviar($Sy, array('tema' => '212', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
sql("UPDATE mybb_threads SET closed = 1 WHERE tid = 212");
$a = opg_ent_analizar($Ey, array('confirma_minimos' => '1'));
prueba('un tema que la herramienta antigua ya cerró bloquea el pago nuevo', array($a['puede_pagar'], strpos(implode(' ', $a['bloqueos']), 'cerrado') !== false), array(false, true));
$antes = ficha_de(3)['nika'];
prueba('y pagar se rechaza sin tocar nada', array(opg_ent_pagar($Ey, array('confirma_minimos' => '1'), 50)['codigo'], ficha_de(3)['nika']), array(422, $antes));
opg_ent_rechazar($Ey, 'Ya pagada con la herramienta antigua', 50);

// ── Pago interrumpido y reanudación sin pagar dos veces ──────────────────────
resetear_fichas();
fijar_reset_al_mes_actual();
tema_con_posts(220, 40, 'Aventura que se interrumpe', posts_estandar());
$S6 = nueva_sol(3, 0, 8, array(3, 5, 6));
$E6 = opg_ent_enviar($S6, array('tema' => '220', 'resumen' => 'Interrumpida.', 'reputacion' => 'positiva'), 8)['id'];
sql("DROP TRIGGER IF EXISTS trg_fallo");
sql("CREATE TRIGGER trg_fallo BEFORE UPDATE ON mybb_op_fichas FOR EACH ROW BEGIN IF NEW.fid = 6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fallo forzado'; END IF; END");
$r = opg_ent_pagar($E6, array('confirma_minimos' => '1', 'extra_jugadores' => '10'), 50);
prueba('el fallo forzado deja un pago parcial', array($r['ok'], !empty($r['parcial']), $r['pagados']), array(false, true, 3));
$e = opg_ent_obtener_por_id($E6);
prueba('la entrega sigue pendiente, sin reclamo y con los ajustes congelados', array($e['estado'], $e['procesando_at'], $e['ajustes']['extra_jugadores']), array('pendiente', null, 10));
prueba('el tema no se cerró ni se publicó nada', array(tema_cerrado(220), marcadores(220)), array(0, 0));
prueba('la solicitud sigue aprobada', estado_de($S6), 1);
$f3 = ficha_de(3); $f8 = ficha_de(8); $f6 = ficha_de(6);
prueba('los destinatarios anteriores sí cobraron y el que falló no', array($f3['nika'], $f8['nika'], $f6['nika'], $f6['newpoints']), array('20', '28', '0', '0'));   // narrador al 126,67% (116,67 + 10 de extra)
$p6 = una("SELECT aplicado_at, xp_estado FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E6 AND uid = 6");
prueba('el destinatario fallido queda registrado sin aplicar', array($p6['aplicado_at'], $p6['xp_estado']), array(null, 'pendiente'));
// Simula que el proceso murió justo después de sumar la experiencia de 3 (queda "aplicando")
sql("UPDATE mybb_op_aventuras_entregas_pagos SET xp_estado = 'aplicando' WHERE entrega_id = $E6 AND uid = 3");
sql("DROP TRIGGER trg_fallo");
$r = opg_ent_completar($E6, 50);
prueba('completar: paga al que faltaba y no cierra mientras haya experiencia dudosa', array($r['ok'], !empty($r['parcial'])), array(false, true));
$f3b = ficha_de(3); $f6b = ficha_de(6); $f8b = ficha_de(8);
prueba('completar no repitió los pagos anteriores', array($f3b['nika'], $f3b['newpoints'], $f8b['nika'], $f8b['newpoints'], $f8b['exp_mes']), array($f3['nika'], $f3['newpoints'], $f8['nika'], $f8['newpoints'], $f8['exp_mes']));
prueba('completar sí pagó al que faltaba', array($f6b['nika'], $f6b['newpoints']), array('20', '120'));
prueba('la experiencia en duda quedó marcada como dudosa', una("SELECT xp_estado FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E6 AND uid = 3")['xp_estado'], 'dudoso');
prueba('entrega pendiente con experiencia dudosa: sigue sin cerrar', array(opg_ent_obtener_por_id($E6)['estado'], tema_cerrado(220)), array('pendiente', 0));
prueba('resolver la experiencia dudosa: opción inválida', opg_ent_xp_dudosa($E6, 3, 'otra', 50)['codigo'], 422);
prueba('resolver la experiencia dudosa: un usuario normal', opg_ent_xp_dudosa($E6, 3, 'aplicada', 9)['codigo'], 403);
prueba('resolver la experiencia dudosa: un pago sin duda', opg_ent_xp_dudosa($E6, 5, 'aplicada', 50)['codigo'], 409);
prueba('resolver "ya se aplicó" no suma de nuevo', array(opg_ent_xp_dudosa($E6, 3, 'aplicada', 50)['ok'], ficha_de(3)['newpoints']), array(true, $f3['newpoints']));
$r = opg_ent_completar($E6, 50);
prueba('completar cierra la entrega', array($r['ok'], opg_ent_obtener_por_id($E6)['estado'], estado_de($S6)), array(true, 'entregada', 3));
prueba('el tema se cerró y tiene un solo mensaje', array(tema_cerrado(220), marcadores(220)), array(1, 1));
prueba('al final cada destinatario cobró exactamente una vez', array(ficha_de(3)['nika'], ficha_de(5)['nika'], ficha_de(6)['nika'], ficha_de(8)['nika'], ficha_de(3)['newpoints'], ficha_de(8)['newpoints']), array('20', '13', '20', '28', '120', '152'));
// Resolver con "aplicar": la experiencia nunca llegó a sumarse
tema_con_posts(221, 40, 'Aventura con experiencia por aplicar', posts_estandar());
$S7 = nueva_sol(3, 0, 8, array(3));
$E7 = opg_ent_enviar($S7, array('tema' => '221', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
$r = opg_ent_pagar($E7, array('confirma_minimos' => '1', 'n_participantes' => '2'), 50);
prueba('pagar una entrega de un solo jugador', array($r['ok'], $r['pagados']), array(true, 2));
$xp3 = (int) una("SELECT xp_cantidad FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E7 AND uid = 3")['xp_cantidad'];
sql("UPDATE mybb_op_aventuras_entregas_pagos SET xp_estado = 'dudoso' WHERE entrega_id = $E7 AND uid = 3");
sql("UPDATE mybb_users SET newpoints = newpoints - $xp3 WHERE uid = 3");
$antes_xp = ficha_de(3)['newpoints'];
prueba('resolver "aplicar" suma la experiencia una vez', array(opg_ent_xp_dudosa($E7, 3, 'aplicar', 50)['ok'], (float) ficha_de(3)['newpoints']), array(true, (float) $antes_xp + $xp3));
prueba('y deja el pago como aplicado', una("SELECT xp_estado FROM mybb_op_aventuras_entregas_pagos WHERE entrega_id = $E7 AND uid = 3")['xp_estado'], 'aplicado');
prueba('resolver dos veces se rechaza', opg_ent_xp_dudosa($E7, 3, 'aplicar', 50)['codigo'], 409);

// ── Tope mensual de experiencia del narrador ────────────────────────────────
resetear_fichas();
fijar_reset_al_mes_actual();
sql("UPDATE mybb_op_fichas SET expNarradorMensualActual = 600 WHERE fid = 8");
tema_con_posts(230, 40, 'Aventura con tope mensual', posts_estandar());
$S8 = nueva_sol(3, 0, 8, array(3, 5, 6));
$E8 = opg_ent_enviar($S8, array('tema' => '230', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
$r = opg_ent_pagar($E8, array('confirma_minimos' => '1'), 50);
$f8 = ficha_de(8);
prueba('tope 675: con 600 acumulados y 140 por pagar se entregan 75 y 32 kuros', array($r['ok'], $f8['newpoints'], $f8['exp_mes'], $f8['kuro'], $f8['nika']), array(true, '75', '675', '32', '26'));
$res = opg_ent_resultado($E8)[0];
prueba('el pago registra el excedente', array($res['calculo']['excedente'], $res['calculo']['kuros']), array(65, 32));
$msg = una("SELECT message FROM mybb_posts WHERE tid = 230 AND message LIKE '%RECOMPENSAS PROCESADAS%'")['message'];
prueba('el mensaje del tema avisa del límite mensual (675)', array(strpos($msg, 'Límite mensual') !== false, strpos($msg, '/ 675') !== false), array(true, true));
// Con el contador lleno no se entrega experiencia
resetear_fichas();
fijar_reset_al_mes_actual();
sql("UPDATE mybb_op_fichas SET expNarradorMensualActual = 675 WHERE fid = 8");
tema_con_posts(231, 40, 'Aventura con el contador lleno', posts_estandar());
$S9 = nueva_sol(3, 0, 8, array(3, 5, 6));
$E9 = opg_ent_enviar($S9, array('tema' => '231', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
opg_ent_pagar($E9, array('confirma_minimos' => '1'), 50);
$f8 = ficha_de(8);
prueba('con el contador lleno no hay experiencia y todo el excedente pasa a kuros', array($f8['newpoints'], $f8['exp_mes'], $f8['kuro']), array('0', '675', '70'));

// ── Reinicio mensual ─────────────────────────────────────────────────────────
sql("DELETE FROM mybb_settings WHERE name = 'exp_narrador_ultimo_reset'");
$GLOBALS['db']->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_settings (name, title, description, optionscode, value) VALUES ('exp_narrador_ultimo_reset', 't', 'd', 'text', '2000-01')");
$GLOBALS['db']->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
sql("UPDATE mybb_op_fichas SET expNarradorMensualActual = 500 WHERE fid IN (3, 8)");
prueba('el primer pago de un mes nuevo reinicia el contador', array(opg_rec_reset_mensual(), ficha_de(8)['exp_mes'], ficha_de(3)['exp_mes'], una("SELECT value FROM mybb_settings WHERE name = 'exp_narrador_ultimo_reset'")['value']), array(true, '0', '0', mes_actual_madrid()));
sql("UPDATE mybb_op_fichas SET expNarradorMensualActual = 100 WHERE fid = 8");
prueba('el segundo pago del mismo mes no lo reinicia', array(opg_rec_reset_mensual(), ficha_de(8)['exp_mes']), array(false, '100'));
sql("DELETE FROM mybb_settings WHERE name = 'exp_narrador_ultimo_reset'");
prueba('sin el ajuste lo crea y reinicia', array(opg_rec_reset_mensual(), (int) una("SELECT COUNT(*) AS n FROM mybb_settings WHERE name = 'exp_narrador_ultimo_reset'")['n'], ficha_de(8)['exp_mes']), array(true, 1, '0'));

// ── Inframundo, oficios y nivel de narrador ─────────────────────────────────
resetear_fichas();
fijar_reset_al_mes_actual();
sql("UPDATE mybb_op_fichas SET oficios = '{\"Mercader\":{\"sub\":{\"Contrabandista\":1}},\"Investigador\":{\"sub\":{\"Arqueólogo\":1}}}' WHERE fid = 3");
sql("UPDATE mybb_op_fichas SET nivelnarrador = 'Erudito' WHERE fid = 8");
$p = array();
for ($i = 0; $i < 5; $i++) { $p[] = array(8, 'Robin', msg()); $p[] = array(3, 'Eddy', msg()); }
tema_con_posts(240, 40, 'Aventura de inframundo', $p);
$S10 = nueva_sol(2, 1, 8, array(3));
$E10 = opg_ent_enviar($S10, array('tema' => '240', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
$a = opg_ent_analizar($E10, array());
prueba('tier 2 (5 posts esperados): 100% y mínimos cumplidos con 2 participantes', array($a['minimos']['cumple'], $a['requiere_confirmar_minimos'], $a['puede_pagar']), array(true, false, true));
$r = opg_ent_pagar($E10, array(), 50);
$f3 = ficha_de(3); $f8 = ficha_de(8);
prueba('jugador T2 inframundo + Contrabandista 1 (x1,5) + Arqueólogo 1 (x1,10 XP): berries 5.000.000 x 1,5 x 1,25', array($r['ok'], $f3['berries'], $f3['movidoInframundo'], $f3['newpoints'], $f3['nika'], $f3['reputacion_positiva']), array(true, '9375000', '9375000', '83', '10', '20'));
prueba('narrador Erudito T2: experiencia 130, nikas 15 y su cofre', array($f8['newpoints'], $f8['nika'], inventario(8, 'CFN003'), $f8['exp_mes']), array('130', '15', 1, '130'));

// ── Efectos sobre la solicitud al denegar, borrar o salirse ──────────────────
tema_con_posts(250, 40, 'Aventura que se deniega', posts_estandar());
$S11 = nueva_sol(3, 0, 8, array(3, 5, 6));
$E11 = opg_ent_enviar($S11, array('tema' => '250', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
prueba('finalizar a mano con una entrega pendiente se rechaza', opg_av_cambiar_estado($S11, 3, 50)['codigo'], 409);
$r = opg_av_cambiar_estado($S11, 2, 50, 'Se deniega con la entrega enviada');
$e = opg_ent_obtener_por_id($E11);
prueba('denegar con una entrega pendiente la rechaza y libera el tema', array($r['ok'], $e['estado'], una("SELECT tid_activo FROM mybb_op_aventuras_entregas WHERE id = $E11")['tid_activo']), array(true, 'rechazada', null));
tema_con_posts(251, 40, 'Aventura de la que se sale', posts_estandar());
$S12 = nueva_sol(3, 0, 8, array(3, 5, 6));
$E12 = opg_ent_enviar($S12, array('tema' => '251', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
$r = opg_av_renunciar_narrador($S12, 8, true, 'Me voy');
prueba('el narrador que se sale con una entrega pendiente la deja rechazada', array($r['ok'], opg_ent_obtener_por_id($E12)['estado'], estado_de($S12)), array(true, 'rechazada', 4));
$S13 = nueva_sol(3, 0, 8, array(3));
tema_con_posts(252, 40, 'Aventura que se borra', posts_estandar());
$E13 = opg_ent_enviar($S13, array('tema' => '252', 'resumen' => 'x', 'reputacion' => 'positiva'), 8)['id'];
opg_av_pedir_borrado($S13, 3);
prueba('pedir el borrado con una entrega pendiente la rechaza', opg_ent_obtener_por_id($E13)['estado'], 'rechazada');
prueba('conteos finales', opg_ent_conteos()['pendiente'], (int) una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_entregas WHERE estado = 'pendiente'")['n']);
