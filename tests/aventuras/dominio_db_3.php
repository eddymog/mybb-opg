<?php
/** Parte 3 de dominio_db.php: vincular, datos del staff, mercado de narradores, historial. */
if (empty($GLOBALS['continuar'])) { exit("Ejecuta tests/aventuras/dominio_db.php\n"); }

$GLOBALS['roles'] = array('mod' => array(1), 'staff' => array(50), 'narra' => array(60));
limpiar();

// ── Vincular el tema ─────────────────────────────────────────────────────────
crear_tema(100, 40, 'La aventura de Alfa');                 // válido
crear_tema(101, 43, 'Aventura en el subforo');              // válido (subforo de la isla 40)
crear_tema(102, 41, 'Aventura de otra isla');               // otra isla
crear_tema(103, 40, 'Tema común', 1);                       // otro prefijo
crear_tema(104, 40, 'Aventura cerrada', 3, 1);              // cerrado
crear_tema(105, 40, 'Aventura oculta', 3, 0, 0);            // no visible
crear_tema(106, 40, 'Aventura ya pagada');                  // con el mensaje de recompensas
crear_post(1, 106, 5, 'U5', "[b]RECOMPENSAS PROCESADAS[/b]");
crear_tema(107, 44, 'Aventura fuera de la zona');           // foro fuera de la isla
crear_tema(108, 40, 'Aventura de otra solicitud');          // vinculada a otra solicitud

$V = ins(3, 8, 1, array(3, 5));
$otraSol = ins(3, 9, 1, array(3), array('aventura_url' => 'https://onepiecegaiden.com/showthread.php?tid=108&pid=77'));
prueba('ajeno e invitado no vinculan', array(opg_av_vincular($V, '100', 9)['codigo'], opg_av_vincular($V, '100', 0)['codigo']), array(403, 403));
prueba('vincular una inexistente', opg_av_vincular(99999, '100', 8)['codigo'], 404);
prueba('vincular sin tema', opg_av_vincular($V, '', 8)['codigo'], 422);
prueba('vincular texto', opg_av_vincular($V, 'hola', 8)['codigo'], 422);
prueba('vincular un enlace de otro host', opg_av_vincular($V, 'https://example.com/showthread.php?tid=100', 8)['codigo'], 422);
prueba('vincular un tema inexistente', opg_av_vincular($V, '99999', 8)['codigo'], 422);
foreach (array(102 => 'otra isla', 103 => 'otro prefijo', 104 => 'cerrado', 105 => 'no visible', 106 => 'con el mensaje de recompensas', 107 => 'fuera de la zona', 108 => 'vinculado a otra solicitud') as $tid => $motivo) {
    prueba("vincular un tema $motivo se rechaza", opg_av_vincular($V, (string) $tid, 8)['codigo'], 422);
}
prueba('la URL no cambió con los rechazos', fila($V)['aventura_url'], null);
prueba('participante puede vincular', opg_av_vincular($V, '100', 3)['ok'], true);
$r = opg_av_vincular($V, '100', 8);
prueba('vincular por tid', array($r['ok'], fila($V)['aventura_url']), array(true, 'https://onepiecegaiden.com/showthread.php?tid=100'));
$r = opg_av_vincular($V, 'https://onepiecegaiden.com/showthread.php?tid=101&pid=5', 8);
prueba('vincular por enlace con subforo; la URL se normaliza', array($r['ok'], fila($V)['aventura_url']), array(true, 'https://onepiecegaiden.com/showthread.php?tid=101'));
$r = opg_av_vincular($V, 'https://onepiecegaiden.com/thread-100.html', 8);
prueba('vincular por enlace SEO', array($r['ok'], opg_av_obtener($V)['tid_vinculado']), array(true, 100));
sql("UPDATE mybb_op_peticionAventuras SET estado = 0 WHERE id = $V");
prueba('solo se vincula una aprobada', opg_av_vincular($V, '100', 8)['codigo'], 409);
sql("UPDATE mybb_op_peticionAventuras SET estado = 1 WHERE id = $V");
// Solicitud antigua sin isla: cualquier foro de rol (parentlist que empieza por 10,)
$antigua = ins(3, 8, 1, array(3), array('isla_fid' => null, 'dia' => null));
crear_foro(10, 'Zona de rol', 0, 1, '10');
crear_foro(45, 'Foro de rol', 10, 1, '10,45');
crear_tema(109, 45, 'Aventura de rol');
prueba('solicitud sin isla acepta un foro de rol', opg_av_vincular($antigua, '109', 8)['ok'], true);
prueba('solicitud sin isla rechaza un foro fuera del rol', opg_av_vincular($antigua, '107', 8)['codigo'], 422);
// Foros inaccesibles
$GLOBALS['foros_inaccesibles'] = '40';
prueba('un foro inaccesible para el usuario se rechaza', opg_av_vincular($V, '100', 8)['codigo'], 422);
$GLOBALS['foros_inaccesibles'] = '';

// ── Actualizar datos desde la consola ────────────────────────────────────────
$U = ins(3, 8, 0, array(3, 5), array('comentario_publico' => '', 'comentario_staff' => ''));
prueba('un usuario normal no edita datos', opg_av_actualizar_datos($U, array('nota_staff' => 'x'), 9)['codigo'], 403);
prueba('sin cambios', opg_av_actualizar_datos($U, array(), 50)['codigo'], 422);
prueba('nota de 201 caracteres', opg_av_actualizar_datos($U, array('nota_staff' => str_repeat('a', 201)), 50)['codigo'], 422);
prueba('comentario de 1001', opg_av_actualizar_datos($U, array('comentario' => str_repeat('a', 1001)), 50)['codigo'], 422);
prueba('isla inválida', opg_av_actualizar_datos($U, array('isla_fid' => 43), 50)['codigo'], 422);
prueba('día inválido', opg_av_actualizar_datos($U, array('dia' => 91), 50)['codigo'], 422);
$r = opg_av_actualizar_datos($U, array('nota_staff' => str_repeat('n', 200), 'comentario' => 'Público', 'inframundo' => '1', 'isla_fid' => 41, 'dia' => 33), 60);
$f = fila($U);
prueba('actualizar datos', array($r['ok'], strlen($f['comentario_staff']), $f['comentario_publico'], (int) $f['inframundo'], (int) $f['isla_fid'], (int) $f['dia']), array(true, 200, 'Público', 1, 41, 33));
$meta = una("SELECT * FROM mybb_op_peticionAventuras_meta WHERE peticion_id = $U");
prueba('se refleja en _meta', array(strlen($meta['staff_note']), $meta['public_comment'], (int) $meta['updated_by']), array(200, 'Público', 60));
opg_av_actualizar_datos($U, array('nota_staff' => 'corta'), 50);
$meta = una("SELECT * FROM mybb_op_peticionAventuras_meta WHERE peticion_id = $U");
prueba('actualizar solo la nota conserva el comentario en _meta', array($meta['staff_note'], $meta['public_comment']), array('corta', 'Público'));
prueba('la nota de staff llega a la consola y no a los jugadores', array(opg_av_obtener($U, true)['nota_staff'], array_key_exists('nota_staff', opg_av_obtener($U, false))), array('corta', false));
// Comentarios que solo están en _meta (filas antiguas)
$vieja = ins(3, 8, 1, array(3), array('comentario_publico' => '', 'comentario_staff' => ''));
sql("INSERT INTO mybb_op_peticionAventuras_meta (peticion_id, staff_note, public_comment) VALUES ($vieja, 'Nota vieja', 'Comentario viejo')");
prueba('el comentario antiguo cae a _meta', opg_av_obtener($vieja)['comentario_publico'], 'Comentario viejo');
prueba('la nota antigua cae a _meta', opg_av_obtener($vieja, true)['nota_staff'], 'Nota vieja');

// ── Historial ────────────────────────────────────────────────────────────────
$h = opg_av_historial($U);
prueba('historial: más reciente primero', $h[0]['accion'], 'actualizar_datos');
prueba('historial: trae el usuario', $h[0]['usuario'] !== '', true);
prueba('historial: una solicitud sin auditoría', opg_av_historial($vieja), array());

// ── Eliminar con entrega entregada ───────────────────────────────────────────
$ent = ins(3, 8, 5, array(3));
sql("INSERT INTO mybb_op_aventuras_entregas (peticion_id, tid, tid_activo, tema_nombre, resumen, reputacion, estado, enviado_por, enviado_at) VALUES ($ent, 500, 500, 'T', 'R', 'positiva', 'entregada', 8, " . TIME_NOW . ")");
prueba('no se elimina una con recompensas entregadas', opg_av_eliminar($ent, 50)['codigo'], 409);
sql("UPDATE mybb_op_aventuras_entregas SET estado = 'rechazada', tid_activo = NULL WHERE peticion_id = $ent");
prueba('con una entrega no entregada sí se elimina, junto con la entrega', array(opg_av_eliminar($ent, 50)['ok'], una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_entregas WHERE peticion_id = $ent")['n']), array(true, '0'));

// ── Mercado de narradores ────────────────────────────────────────────────────
sql("DELETE FROM mybb_op_narradores_disponibles");
prueba('sin ficha no se ofrece', opg_av_oferta_guardar(7, 'x#1', '')['codigo'], 403);
prueba('invitado no se ofrece', opg_av_oferta_guardar(0, 'x#1', '')['codigo'], 403);
prueba('sin Discord', opg_av_oferta_guardar(8, '', 'hola')['codigo'], 422);
prueba('Discord de 65 caracteres', opg_av_oferta_guardar(8, str_repeat('a', 65), '')['codigo'], 422);
prueba('mensaje de 201 caracteres', opg_av_oferta_guardar(8, 'robin#1', str_repeat('a', 201))['codigo'], 422);
prueba('Discord con salto de línea', opg_av_oferta_guardar(8, "a\nb", '')['codigo'], 422);
prueba('ofrecerse', opg_av_oferta_guardar(8, 'robin#1', 'Aventuras de misterio')['ok'], true);
$o = opg_av_oferta_obtener(8);
prueba('oferta guardada y vigente', array($o['discord'], $o['mensaje'], $o['vencida'], $o['dias_restantes']), array('robin#1', 'Aventuras de misterio', false, 30));
$creada = $o['created_at'];
sql("UPDATE mybb_op_narradores_disponibles SET created_at = created_at - 5000 WHERE uid = 8");
prueba('ofrecerse otra vez actualiza y deja una sola fila', array(opg_av_oferta_guardar(8, 'robin#2', 'Nuevo')['ok'], (int) una("SELECT COUNT(*) AS n FROM mybb_op_narradores_disponibles WHERE uid = 8")['n']), array(true, 1));
prueba('ofrecerse otra vez conserva created_at', opg_av_oferta_obtener(8)['created_at'], $creada - 5000);
prueba('ofrecerse otra vez cambia el Discord', opg_av_oferta_obtener(8)['discord'], 'robin#2');
// Varias ofertas con distinta carga
opg_av_oferta_guardar(9, 'nami#1', 'Mensaje');
opg_av_oferta_guardar(10, 'sanji#1', '');
opg_av_oferta_guardar(60, 'staff#1', '');
sql("UPDATE mybb_op_narradores_disponibles SET created_at = " . (TIME_NOW - 3000) . " WHERE uid = 9");
sql("UPDATE mybb_op_narradores_disponibles SET created_at = " . (TIME_NOW - 2000) . " WHERE uid = 10");
sql("UPDATE mybb_op_narradores_disponibles SET created_at = " . (TIME_NOW - 1000) . " WHERE uid = 60");
sql("UPDATE mybb_op_narradores_disponibles SET created_at = " . (TIME_NOW - 4000) . " WHERE uid = 8");
limpiar();
ins(3, 8, 1, array(3)); ins(3, 8, 0, array(3)); ins(3, 8, 3, array(3)); ins(3, 8, 3, array(3)); ins(3, 8, 3, array(3));   // 8: 2 en curso, 3 finalizadas
ins(3, 9, 1, array(3));                                                                                                   // 9: 1 en curso
ins(3, 10, 2, array(3)); ins(3, 10, 5, array(3));                                                                         // 10: denegada y borrada no cuentan
$nd = opg_av_narradores_disponibles(1);
prueba('narradores: orden de menos a más aventuras en curso y, a igual número, oferta más antigua', array_map(function ($n) { return $n['uid']; }, $nd['items']), array(10, 60, 9, 8));
$por = array(); foreach ($nd['items'] as $n) { $por[$n['uid']] = $n; }
prueba('narradores: conteos en curso y finalizadas', array($por[8]['en_curso'], $por[8]['finalizadas'], $por[9]['en_curso'], $por[10]['en_curso']), array(2, 3, 1, 0));
prueba('narradores: incluye el Discord', $por[9]['discord'], 'nami#1');
prueba('narradores: nombre de ficha', $por[9]['nombre'], 'Nami');
// Vencimiento
sql("UPDATE mybb_op_narradores_disponibles SET updated_at = " . (TIME_NOW - (31 * 86400)) . " WHERE uid = 9");
prueba('una oferta de 31 días no se muestra', in_array(9, array_map(function ($n) { return $n['uid']; }, opg_av_narradores_disponibles(1)['items'])), false);
$o = opg_av_oferta_obtener(9);
prueba('su autor la ve como vencida', array($o['vencida'], $o['dias_restantes']), array(true, 0));
prueba('la consola ve las vencidas', in_array(9, array_map(function ($n) { return $n['uid']; }, opg_av_ofertas_admin_listar(1)['items'])), true);
prueba('la consola marca la vencida', array_values(array_filter(opg_av_ofertas_admin_listar(1)['items'], function ($n) { return $n['uid'] === 9; }))[0]['vencida'], true);
prueba('renovar', array(opg_av_oferta_renovar(9)['ok'], opg_av_oferta_obtener(9)['vencida']), array(true, false));
prueba('renovar sin oferta', opg_av_oferta_renovar(12)['codigo'], 404);
sql("DELETE FROM mybb_op_narradores_disponibles WHERE uid = 12");
sql("UPDATE mybb_op_fichas SET aprobada_por = 'staff' WHERE fid = 7");
prueba('ofrecerse con ficha aprobada', opg_av_oferta_guardar(7, 'x#1', '')['ok'], true);
sql("DELETE FROM mybb_op_fichas WHERE fid = 7");
prueba('una oferta de un usuario sin ficha no aparece', in_array(7, array_map(function ($n) { return $n['uid']; }, opg_av_narradores_disponibles(1)['items'])), false);
sql("DELETE FROM mybb_op_narradores_disponibles WHERE uid = 7");
// Retirar
prueba('retirar la propia', array(opg_av_oferta_retirar(10)['ok'], opg_av_oferta_obtener(10)), array(true, null));
prueba('retirar sin oferta', opg_av_oferta_retirar(10)['codigo'], 404);
prueba('la consola: un usuario normal no retira', opg_av_oferta_retirar_admin(8, 9, 'x')['codigo'], 403);
prueba('la consola: sin motivo', opg_av_oferta_retirar_admin(8, 50, '')['codigo'], 422);
prueba('la consola: motivo de 201', opg_av_oferta_retirar_admin(8, 50, str_repeat('a', 201))['codigo'], 422);
prueba('la consola: una oferta inexistente', opg_av_oferta_retirar_admin(999, 50, 'x')['codigo'], 404);
$r = opg_av_oferta_retirar_admin(8, 60, 'Discord falso');
prueba('la consola retira con motivo', array($r['ok'], opg_av_oferta_obtener(8)), array(true, null));
$aud = una("SELECT * FROM mybb_op_audit_aventuras WHERE accion = 'retirar_oferta_admin' ORDER BY id DESC LIMIT 1");
prueba('retirar queda auditado con peticion_id 0 y el motivo', array((int) $aud['peticion_id'], (int) $aud['uid'], json_decode($aud['detalle_json'], true)['despues']['motivo']), array(0, 60, 'Discord falso'));
prueba('el usuario puede volver a ofrecerse', opg_av_oferta_guardar(8, 'robin#3', '')['ok'], true);

echo "  (parte 2b completa)\n";
require __DIR__ . '/entregas_db.php';
