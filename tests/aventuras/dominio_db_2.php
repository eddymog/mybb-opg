<?php
/** Parte 2 de dominio_db.php: estados, postular, salirse, editar, vincular, datos del staff, mercado. */
if (empty($GLOBALS['continuar'])) { exit("Ejecuta tests/aventuras/dominio_db.php\n"); }

function crear_tema($tid, $fid, $subject, $prefix = 3, $closed = 0, $visible = 1)
{
    global $db;
    $db->query("SET SESSION sql_mode = ''", 1);
    sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline, year, estacion, day) VALUES ($tid, $fid, '" . $db->escape_string($subject) . "', $prefix, $closed, $visible, " . (TIME_NOW - $tid) . ", '725', 'Verano', '12')");
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}
function crear_post($pid, $tid, $uid, $username, $mensaje)
{
    global $db;
    $db->query("SET SESSION sql_mode = ''", 1);
    sql("INSERT INTO mybb_posts (pid, tid, uid, username, message, dateline) VALUES ($pid, $tid, $uid, '" . $db->escape_string($username) . "', '" . $db->escape_string($mensaje) . "', " . (TIME_NOW + $pid) . ")");
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}
function estado_de($id) { return (int) fila($id)['estado']; }

limpiar();

// ── Cambiar estado ───────────────────────────────────────────────────────────
// Solicitud de 3 con narrador 8 y participantes 3 y 5.
$S = ins(3, 8, 0, array(3, 5));
prueba('invitado no puede aprobar', opg_av_cambiar_estado($S, 1, 0)['codigo'], 403);
prueba('usuario normal no puede aprobar', opg_av_cambiar_estado($S, 1, 9)['codigo'], 403);
prueba('el narrador usuario sin permiso de gestión no puede aprobar', opg_av_cambiar_estado($S, 1, 8)['codigo'], 403);
$GLOBALS['roles']['staff'][] = 3;   // el creador es staff
prueba('el staff creador puede resolverla', opg_av_puede_resolver(opg_av_obtener($S), 3), true);
$GLOBALS['roles']['narra'][] = 5;   // un participante es narrador del staff
prueba('el narrador del staff participante puede resolverla', opg_av_puede_resolver(opg_av_obtener($S), 5), true);
prueba('un estado inválido se rechaza', opg_av_cambiar_estado($S, 9, 50)['codigo'], 422);
prueba('no se puede finalizar una solicitud que no está aprobada', opg_av_cambiar_estado($S, 3, 50)['codigo'], 409);
$r = opg_av_cambiar_estado($S, 1, 3);
prueba('el staff creador aprueba', array($r['ok'], estado_de($S)), array(true, 1));
prueba('aprobar otra vez es un conflicto', opg_av_cambiar_estado($S, 1, 50)['codigo'], 409);
prueba('el narrador del staff participante puede finalizar', array(opg_av_cambiar_estado($S, 3, 5)['ok'], estado_de($S)), array(true, 3));
prueba('una finalizada no vuelve a aprobarse', opg_av_cambiar_estado($S, 1, 50)['codigo'], 409);
prueba('aprobar auditado', (int) una("SELECT COUNT(*) AS n FROM mybb_op_audit_aventuras WHERE accion='cambiar_estado' AND peticion_id = $S")['n'], 2);

$D = ins(3, 8, 0, array(3));
prueba('denegar sin motivo', opg_av_cambiar_estado($D, 2, 50, '')['codigo'], 422);
prueba('denegar con motivo en blanco', opg_av_cambiar_estado($D, 2, 50, "   ")['codigo'], 422);
prueba('denegar con motivo de 501', opg_av_cambiar_estado($D, 2, 50, str_repeat('a', 501))['codigo'], 422);
prueba('la denegación fallida no cambió nada', estado_de($D), 0);
$r = opg_av_cambiar_estado($D, 2, 50, 'No cumple la guía');
prueba('denegar con motivo', array($r['ok'], estado_de($D), fila($D)['motivo_denegacion']), array(true, 2, 'No cumple la guía'));
prueba('una denegada no se reabre ni se aprueba', array(opg_av_cambiar_estado($D, 1, 50)['codigo'], opg_av_cambiar_estado($D, 0, 50)['codigo']), array(409, 422));
$P = ins(3, null, 4, array(3));
prueba('no se puede aprobar sin narrador (estado 4)', opg_av_cambiar_estado($P, 1, 50)['codigo'], 409);
prueba('se puede denegar una pendiente de narrador', opg_av_cambiar_estado($P, 2, 50, 'Nadie la narra')['ok'], true);
$AP = ins(3, 8, 1, array(3));
prueba('se puede denegar una aprobada', opg_av_cambiar_estado($AP, 2, 1, 'Acuerdo falso')['ok'], true);

// Límites al aprobar
limpiar();
for ($i = 0; $i < 3; $i++) { ins(5, 8, 1, array(5)); }
$cuarta = ins(5, 8, 0, array(5));
$r = opg_av_cambiar_estado($cuarta, 1, 50);
if (OPG_AV_LIMITES_ACTIVOS) {
    prueba('aprobar una cuarta solitaria se rechaza', array($r['ok'], $r['codigo']), array(false, 422));
    prueba('el rechazo por límite nombra al jugador', isset($r['excedidos'][0]['nombre']) && $r['excedidos'][0]['fid'] === 5, true);
    prueba('sigue en estado 0', estado_de($cuarta), 0);
} else {
    prueba('sin límites: se aprueba una cuarta solitaria', array($r['ok'], estado_de($cuarta)), array(true, 1));
}
sql("DELETE FROM mybb_op_peticionAventuras WHERE id = $cuarta");
$grupal = ins(5, 8, 0, array(5, 6));
prueba('una grupal sí se aprueba con 3 solitarias', opg_av_cambiar_estado($grupal, 1, 50)['ok'], true);

// Pedir borrado
limpiar();
$B1 = ins(3, 8, 1, array(3, 5));
prueba('solo el creador pide el borrado', opg_av_pedir_borrado($B1, 5)['codigo'], 403);
prueba('el creador pide el borrado', array(opg_av_pedir_borrado($B1, 3)['ok'], estado_de($B1)), array(true, 5));
prueba('no se pide el borrado dos veces', opg_av_pedir_borrado($B1, 3)['codigo'], 409);
prueba('borrado definitivo: solo staff', array(opg_av_eliminar($B1, 60)['codigo'], opg_av_eliminar($B1, 1)['codigo']), array(403, 403));
prueba('staff elimina una en estado 5', array(opg_av_eliminar($B1, 50)['ok'], fila($B1)), array(true, null));
$noB = ins(3, 8, 1, array(3));
prueba('no se elimina una que no está en estado 5', opg_av_eliminar($noB, 50)['codigo'], 409);

// ── Postularse y salirse ─────────────────────────────────────────────────────
limpiar();
$sn = ins(3, null, 4, array(3, 5), array('idea_narrador' => 'Idea'));
prueba('postularse sin ficha', opg_av_postular_narrador($sn, 7, true)['codigo'], 403);
prueba('el creador no se postula', opg_av_postular_narrador($sn, 3, true)['codigo'], 403);
prueba('un participante no se postula', opg_av_postular_narrador($sn, 5, true)['codigo'], 403);
prueba('postularse a una que no existe', opg_av_postular_narrador(99999, 8, true)['codigo'], 404);
$r = opg_av_postular_narrador($sn, 8, true);
$f = fila($sn);
prueba('postularse crea candidatura sin asignar', array($r['ok'], (int) $f['estado'], $f['narrador_uid']), array(true, 4, null));
$notificacion = una("SELECT * FROM mybb_op_aventuras_notificaciones WHERE peticion_id = $sn AND uid = 3 AND tipo = 'candidatura_nueva'");
prueba('la candidatura notifica al creador una sola vez', array((bool) $notificacion, opg_av_notificaciones_contar(3)), array(true, 1));
prueba('otro usuario no puede marcar la notificación', opg_av_notificacion_leer($notificacion['id'], 5)['codigo'], 404);
prueba('el destinatario puede marcarla y no repetir la acción', array(opg_av_notificacion_leer($notificacion['id'], 3)['ok'], opg_av_notificaciones_contar(3), opg_av_notificacion_leer($notificacion['id'], 3)['codigo']), array(true, 0, 404));
$p8 = una("SELECT * FROM mybb_op_aventuras_propuestas_narrador WHERE peticion_id = $sn AND narrador_uid = 8");
prueba('puede haber otra candidatura pendiente', opg_av_postular_narrador($sn, 9, true)['ok'], true);
prueba('solo el creador acepta candidatura', opg_av_propuesta_aceptar($p8['id'], 5)['codigo'], 403);
prueba('el creador acepta candidatura', opg_av_propuesta_aceptar($p8['id'], 3)['ok'], true);
$f = fila($sn);
prueba('aceptar candidatura asigna narrador', array((int) $f['estado'], (int) $f['narrador_uid'], $f['narrador_nombre']), array(0, 8, 'Robin'));
prueba('las demás propuestas se cancelan', una("SELECT estado FROM mybb_op_aventuras_propuestas_narrador WHERE peticion_id = $sn AND narrador_uid = 9")['estado'], 'cancelada');
prueba('el candidato descartado recibe una notificación', (int) una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_notificaciones WHERE peticion_id = $sn AND uid = 9 AND tipo = 'propuesta_cancelada'")['n'], 1);
prueba('postularse a una con narrador', opg_av_postular_narrador($sn, 10, true)['codigo'], 409);

// Salirse
prueba('salirse sin confirmar', opg_av_renunciar_narrador($sn, 8, false, 'Motivo')['codigo'], 422);
prueba('salirse sin motivo', opg_av_renunciar_narrador($sn, 8, true, '  ')['codigo'], 422);
prueba('salirse con motivo de 501', opg_av_renunciar_narrador($sn, 8, true, str_repeat('a', 501))['codigo'], 422);
prueba('solo el narrador asignado se sale', array(opg_av_renunciar_narrador($sn, 9, true, 'x')['codigo'], opg_av_renunciar_narrador($sn, 3, true, 'x')['codigo']), array(403, 403));
$r = opg_av_renunciar_narrador($sn, 8, true, 'Me quedé sin tiempo');
$f = fila($sn);
prueba('salirse deja la solicitud en estado 4 sin narrador y con motivo', array($r['ok'], (int) $f['estado'], $f['narrador_uid'], $f['narrador_fid'], $f['narrador_nombre'], $f['motivo_renuncia']), array(true, 4, null, null, null, 'Me quedé sin tiempo'));
prueba('conserva la idea', $f['idea_narrador'], 'Idea');
prueba('el motivo de renuncia se expone en estado 4', opg_av_obtener($sn)['motivo_renuncia'], 'Me quedé sin tiempo');
prueba('otro narrador puede enviar candidatura', opg_av_postular_narrador($sn, 9, true)['ok'], true);
$p9 = una("SELECT * FROM mybb_op_aventuras_propuestas_narrador WHERE peticion_id = $sn AND narrador_uid = 9 AND estado = 'pendiente'");
prueba('aceptar al nuevo narrador borra el motivo', array(opg_av_propuesta_aceptar($p9['id'], 3)['ok'], fila($sn)['motivo_renuncia']), array(true, null));
// Salirse de una aprobada con URL: pierde la aprobación y conserva la URL
sql("UPDATE mybb_op_peticionAventuras SET estado = 1, aventura_url = 'https://onepiecegaiden.com/showthread.php?tid=555' WHERE id = $sn");
$r = opg_av_renunciar_narrador($sn, 9, true, 'Otro motivo');
$f = fila($sn);
prueba('salirse de una aprobada: vuelve a 4 y conserva la URL', array($r['ok'], (int) $f['estado'], $f['aventura_url']), array(true, 4, 'https://onepiecegaiden.com/showthread.php?tid=555'));
// No se sale de finalizadas, denegadas ni de las pendientes de narrador
$fin = ins(3, 8, 3, array(3));
prueba('no se sale de una finalizada', opg_av_renunciar_narrador($fin, 8, true, 'x')['codigo'], 409);
prueba('no se sale de una pendiente de narrador (sin narrador)', opg_av_renunciar_narrador($sn, 8, true, 'x')['codigo'], 403);
// Narrador que es también creador
$propia = ins(8, 8, 0, array(5));
$r = opg_av_renunciar_narrador($propia, 8, true, 'No puedo');
prueba('el narrador creador se sale: queda en 4 y sigue siendo suya', array($r['ok'], (int) fila($propia)['estado'], (int) fila($propia)['uid']), array(true, 4, 8));
prueba('el narrador creador no puede postularse a la suya', opg_av_postular_narrador($propia, 8, true)['codigo'], 403);

// ── Editar ───────────────────────────────────────────────────────────────────
limpiar();
$r = crear_ok(entrada_b(), 3);
$E = $r['id'];
prueba('solo el creador edita', opg_av_editar($E, entrada_b(array('participantes' => array(5))), 5)['codigo'], 403);
prueba('editar una inexistente', opg_av_editar(99999, entrada_b(), 3)['codigo'], 404);
prueba('editar con errores', opg_av_editar($E, entrada_b(array('dia' => 99)), 3)['codigo'], 422);
$r = opg_av_editar($E, entrada_b(array('participantes' => array(5, 6), 'tier' => 5, 'dia' => 20, 'comentario' => 'Nuevo', 'inframundo' => '1', 'idea' => 'Otra idea')), 3);
$f = fila($E);
prueba('editar cambia los datos', array($r['ok'], (int) $f['tier_seleccionado'], (int) $f['dia'], $f['comentario_publico'], (int) $f['inframundo'], $f['idea_narrador'], (int) $f['num_jugadores']), array(true, 5, 20, 'Nuevo', 1, 'Otra idea', 3));
prueba('editar guarda editado_at', $f['editado_at'] !== null && (int) $f['editado_at'] >= TIME_NOW, true);
prueba('editar conserva el estado 4 sin narrador', (int) $f['estado'], 4);
$aud = una("SELECT detalle_json FROM mybb_op_audit_aventuras WHERE accion='editar' AND peticion_id = $E ORDER BY id DESC LIMIT 1");
$det = json_decode($aud['detalle_json'], true);
prueba('la auditoría de editar guarda solo lo que cambió', array_keys($det['despues']) === array('participantes', 'idea_narrador', 'dia', 'tier', 'comentario_publico', 'inframundo') || count($det['despues']) >= 5, true);
// Nombrar narrador: crea invitación y se borra el motivo de renuncia
sql("UPDATE mybb_op_peticionAventuras SET motivo_renuncia = 'Antiguo' WHERE id = $E");
$r = opg_av_editar($E, entrada_b(array('participantes' => array(5, 6), 'tiene_narrador' => '1', 'narrador_fid' => 8)), 3);
$f = fila($E);
prueba('nombrar narrador al editar: invita sin asignar y borra el motivo', array($r['ok'], (int) $f['estado'], $f['narrador_uid'], $f['motivo_renuncia'], $f['idea_narrador']), array(true, 4, null, null, null));
$pe = una("SELECT * FROM mybb_op_aventuras_propuestas_narrador WHERE peticion_id = $E AND estado = 'pendiente'");
prueba('aceptar invitación editada asigna narrador', opg_av_propuesta_aceptar($pe['id'], 8)['ok'], true);
// Quitar narrador: 0 -> 4
$r = opg_av_editar($E, entrada_b(array('participantes' => array(5, 6), 'tiene_narrador' => '0', 'idea' => 'Vuelvo a buscar')), 3);
$f = fila($E);
prueba('quitar el narrador al editar: pasa a 4 y limpia el narrador', array($r['ok'], (int) $f['estado'], $f['narrador_uid'], $f['idea_narrador']), array(true, 4, null, 'Vuelvo a buscar'));
// Forma A: el rol no cambia
$A2 = crear_ok(entrada_a(), 8)['id'];
$r = opg_av_editar($A2, entrada_a(array('participantes' => array(5, 6), 'rol' => 'jugador', 'tiene_narrador' => '0')), 8);
$f = fila($A2);
prueba('el rol no se puede cambiar: el narrador creador sigue siéndolo', array($r['ok'], (int) $f['narrador_uid'], (int) $f['estado']), array(true, 8, 0));
prueba('la forma A no admite al narrador entre los participantes al editar', opg_av_editar($A2, entrada_a(array('participantes' => array(8))), 8)['codigo'], 422);
// Estados no editables
foreach (array(1, 2, 3, 5) as $est) {
    sql("UPDATE mybb_op_peticionAventuras SET estado = $est WHERE id = $E");
    prueba("editar en estado $est se rechaza", opg_av_editar($E, entrada_b(array('participantes' => array(5, 6))), 3)['codigo'], 409);
}
prueba('puede_editar solo en 0 y 4', array(opg_av_puede_editar(opg_av_obtener($E), 3)), array(false));
sql("UPDATE mybb_op_peticionAventuras SET estado = 0 WHERE id = $E");
prueba('puede_editar en 0', opg_av_puede_editar(opg_av_obtener($E), 3), true);

echo "  (parte 2a completa)\n";
require __DIR__ . '/dominio_db_3.php';
