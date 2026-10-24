<?php
/** Parte 4 de dominio_db.php: entregas, análisis y pago de recompensas. */
if (empty($GLOBALS['continuar'])) { exit("Ejecuta tests/aventuras/dominio_db.php\n"); }

// Doble de PostDataHandler: inserta el post y devuelve su pid (el real necesita todo MyBB).
class PostDataHandler
{
    public $action;
    private $datos;
    public function __construct($accion) {}
    public function set_data($d) { $this->datos = $d; }
    public function validate_post() { return true; }
    public function get_friendly_errors() { return array(); }
    public function insert_post()
    {
        global $db;
        $d = $this->datos;
        $pid = 5000 + (int) mysqli_fetch_assoc(mysqli_query($db->link, 'SELECT COUNT(*) AS n FROM mybb_posts'))['n'];
        crear_post($pid, (int) $d['tid'], (int) $d['uid'], $d['username'], $d['message']);
        return array('pid' => $pid);
    }
}

$GLOBALS['roles'] = array('mod' => array(1), 'staff' => array(50), 'narra' => array(60));
limpiar();
sql("DELETE FROM mybb_posts"); sql("DELETE FROM mybb_threads"); sql("DELETE FROM mybb_op_aventuras_entregas_pagos"); sql("DELETE FROM mybb_op_inventario");
sql("DELETE FROM mybb_op_peticionAventuras_meta"); sql("DELETE FROM mybb_settings");
crear_usuario(5, 'Luffy');       // restablece fichas
foreach (array(3, 5, 6, 8, 20) as $u) { sql("UPDATE mybb_op_fichas SET nika = 0, berries = 0, reputacion = 0, reputacion_positiva = 0, reputacion_negativa = 0, movidoInframundo = 0, kuro = 0, expNarradorMensualActual = 0, oficios = '', nivelnarrador = 'Aprendiz' WHERE fid = $u"); sql("UPDATE mybb_users SET newpoints = 0 WHERE uid = $u"); }
crear_usuario(9, 'Nami');
crear_usuario(10, 'Sanji');
// El usuario 5 también publica los mensajes (OPG_REC_UID_PUBLICACION); el doble de PostDataHandler lo registra con su nombre.

function msg($n = 320) { return str_repeat('a', $n); }
function tema_con_posts($tid, $fid, $nombre, $posts)
{
    crear_tema($tid, $fid, $nombre);
    static $pid = 10;
    foreach ($posts as $p) {
        list($uid, $nombre_u, $mensaje) = $p;
        crear_post(++$pid + $tid * 100, $tid, $uid, $nombre_u, $mensaje);
    }
}
function posts_estandar()
{
    $p = array();
    for ($i = 0; $i < 8; $i++) { $p[] = array(8, 'Robin', msg()); }
    for ($i = 0; $i < 6; $i++) { $p[] = array(3, 'Eddy', msg()); }
    for ($i = 0; $i < 4; $i++) { $p[] = array(5, 'Luffy', msg()); }
    $p[] = array(5, 'Luffy', 'corto'); $p[] = array(5, 'Luffy', '<b>' . str_repeat('x', 100) . '</b>');
    for ($i = 0; $i < 6; $i++) { $p[] = array(6, 'Zoro', msg()); }
    for ($i = 0; $i < 3; $i++) { $p[] = array(20, 'Brook', msg()); }
    return $p;
}
tema_con_posts(200, 40, 'Una aventura de prueba', posts_estandar());
tema_con_posts(201, 40, 'Otra aventura', posts_estandar());
crear_tema(202, 40, 'Aventura sin posts');
tema_con_posts(203, 40, 'Aventura sin narrador', array(array(3, 'Eddy', msg()), array(5, 'Luffy', msg())));

function nueva_sol($tier = 3, $infra = 0, $narr = 8, $jug = array(3, 5, 6))
{
    return ins(3, $narr, 1, $jug, array('tier_seleccionado' => $tier, 'inframundo' => $infra, 'isla_fid' => 40));
}
$S = nueva_sol();

// ── Typeahead de temas ───────────────────────────────────────────────────────
$av = opg_av_obtener($S);
$r = opg_ent_temas_buscar('Un', $av, 8);
prueba('typeahead con 2 caracteres no busca', array($r['temas'], $r['mensaje']), array(array(), 'Escribe al menos 3 caracteres.'));
prueba('typeahead con espacios no engaña', count(opg_ent_temas_buscar('  a ', $av, 8)['temas']), 0);
prueba('typeahead con 3 caracteres', array_map(function ($t) { return $t['tid']; }, opg_ent_temas_buscar('ave', $av, 8)['temas']), array(200, 201, 202, 203));   // del más reciente al más antiguo (dateline)
prueba('typeahead: solo prefijo Aventura (los otros temas de pruebas anteriores no salen)', in_array(103, array_map(function ($t) { return $t['tid']; }, opg_ent_temas_buscar('com', $av, 8)['temas'])), false);
prueba('typeahead: nombre y foro del tema', array(opg_ent_temas_buscar('prueba', $av, 8)['temas'][0]['nombre'], opg_ent_temas_buscar('prueba', $av, 8)['temas'][0]['foro']), array('Una aventura de prueba', 'Isla Alfa'));
prueba('typeahead: escapa comodines', count(opg_ent_temas_buscar('%%%', $av, 8)['temas']), 0);
prueba('typeahead: sin resultados explica', opg_ent_temas_buscar('zzzz', $av, 8)['mensaje'], 'No hay temas de aventura con ese nombre.');
// Ya vinculado a otra solicitud o en otra entrega no sale
$otra = nueva_sol(3, 0, 9, array(3));
sql("UPDATE mybb_op_peticionAventuras SET aventura_url = 'https://onepiecegaiden.com/showthread.php?tid=201' WHERE id = $otra");
prueba('typeahead: no ofrece un tema vinculado a otra solicitud', in_array(201, array_map(function ($t) { return $t['tid']; }, opg_ent_temas_buscar('otra', $av, 8)['temas'])), false);
sql("UPDATE mybb_op_peticionAventuras SET aventura_url = NULL WHERE id = $otra");
prueba('typeahead: vuelve a ofrecerlo al soltarlo', in_array(201, array_map(function ($t) { return $t['tid']; }, opg_ent_temas_buscar('otra', $av, 8)['temas'])), true);

// ── Enviar ───────────────────────────────────────────────────────────────────
$base = array('tema' => '200', 'resumen' => 'Ganaron los héroes.', 'reputacion' => 'positiva', 'confirmar_grupo' => '1');
// Cualquier jugador o el narrador pueden entregar; un ajeno o un invitado no.
prueba('enviar: un ajeno y un invitado no pueden', array(opg_ent_enviar($S, $base, 9)['codigo'], opg_ent_enviar($S, $base, 0)['codigo']), array(403, 403));
prueba('enviar: los jugadores y el narrador pueden entregar', array(opg_ent_puede_enviar(opg_av_obtener($S), 3), opg_ent_puede_enviar(opg_av_obtener($S), 5), opg_ent_puede_enviar(opg_av_obtener($S), 8), opg_ent_puede_enviar(opg_av_obtener($S), 9)), array(true, true, true, false));
prueba('enviar: sin confirmar con el grupo se rechaza (también un jugador)', array(opg_ent_enviar($S, array_merge($base, array('confirmar_grupo' => '')), 8)['codigo'], opg_ent_enviar($S, array_merge($base, array('confirmar_grupo' => '')), 3)['codigo']), array(422, 422));
// Entrega hecha por un jugador: se guarda quién la envió y el narrador sigue siendo el de la aventura
$SP = nueva_sol(3, 0, 8, array(3, 5));
crear_tema(270, 40, 'Aventura entregada por un jugador');
$rp = opg_ent_enviar($SP, array('tema' => '270', 'resumen' => 'La entrega la hace un jugador.', 'reputacion' => 'positiva', 'confirmar_grupo' => '1'), 3);
$ep = opg_ent_obtener($SP);
prueba('enviar: un jugador entrega y queda registrado', array($rp['ok'], $ep['enviado_por'], $ep['narrador_uid']), array(true, 3, 8));
sql("DELETE FROM mybb_op_aventuras_entregas WHERE peticion_id = $SP");
sql("DELETE FROM mybb_op_peticionAventuras WHERE id = $SP");
prueba('enviar: solicitud inexistente', opg_ent_enviar(99999, $base, 8)['codigo'], 404);
foreach (array('tema' => '', 'resumen' => '   ', 'reputacion' => 'ninguna') as $k => $v) {
    prueba("enviar sin '$k' válido", opg_ent_enviar($S, array_merge($base, array($k => $v)), 8)['codigo'], 422);
}
prueba('enviar: resumen de 2001', opg_ent_enviar($S, array_merge($base, array('resumen' => str_repeat('a', 2001))), 8)['codigo'], 422);
prueba('enviar: tema inexistente', opg_ent_enviar($S, array_merge($base, array('tema' => '999999')), 8)['codigo'], 422);
prueba('enviar: tema por enlace', opg_ent_enviar($S, array_merge($base, array('tema' => 'https://example.com/showthread.php?tid=200')), 8)['codigo'], 422);
sql("UPDATE mybb_op_peticionAventuras SET estado = 0 WHERE id = $S");
prueba('enviar: solo una aprobada', opg_ent_enviar($S, $base, 8)['codigo'], 409);
sql("UPDATE mybb_op_peticionAventuras SET estado = 1 WHERE id = $S");
$r = opg_ent_enviar($S, $base, 8);
$E = $r['id'];
$e = opg_ent_obtener_por_id($E);
prueba('enviar: crea la entrega pendiente y conserva al narrador', array($r['ok'], $e['estado'], $e['tid'], $e['tema_nombre'], $e['reputacion'], $e['intentos'], $e['narrador_uid']), array(true, 'pendiente', 200, 'Una aventura de prueba', 'positiva', 1, 8));
prueba('enviar: la solicitud sigue en 1 y se vincula el tema', array(estado_de($S), fila($S)['aventura_url']), array(1, 'https://onepiecegaiden.com/showthread.php?tid=200'));
prueba('enviar: otra entrega para la misma solicitud', opg_ent_enviar($S, $base, 8)['codigo'], 409);
$S2 = nueva_sol(3, 0, 9, array(3));
prueba('enviar: el mismo tema en otra solicitud', opg_ent_enviar($S2, $base, 9)['codigo'], 422);
prueba('conteo de entregas', opg_ent_conteos(), array('pendiente' => 1, 'rechazada' => 0, 'entregada' => 0));

// ── Rechazar ─────────────────────────────────────────────────────────────────
prueba('rechazar sin motivo', opg_ent_rechazar($E, '', 50)['codigo'], 422);
prueba('rechazar con motivo de 501', opg_ent_rechazar($E, str_repeat('a', 501), 50)['codigo'], 422);
prueba('un usuario normal no rechaza', opg_ent_rechazar($E, 'x', 9)['codigo'], 403);
$GLOBALS['roles']['narra'][] = 5;
prueba('un participante narrador del staff puede resolver su entrega', opg_av_puede_resolver(opg_av_obtener($S), 5), true);
$GLOBALS['roles']['narra'] = array(60);
$r = opg_ent_rechazar($E, 'Falta detalle en el resumen', 50);
$e = opg_ent_obtener_por_id($E);
prueba('rechazar libera el tema', array($r['ok'], $e['estado'], $e['motivo_rechazo'], una("SELECT tid_activo FROM mybb_op_aventuras_entregas WHERE id = $E")['tid_activo']), array(true, 'rechazada', 'Falta detalle en el resumen', null));
prueba('rechazar otra vez', opg_ent_rechazar($E, 'x', 50)['codigo'], 409);
prueba('rechazar libera la entrega pero el tema sigue vinculado a su solicitud', array(opg_ent_tema_valido(200, opg_av_obtener($S2), 9)['ok'], opg_ent_tema_valido(200, opg_av_obtener($S), 8)['ok']), array(false, true));
$f = opg_ent_form($S, 8);
prueba('el formulario de corrección precarga el tema y el resumen', array($f['ok'], $f['tema']['tid'], $f['resumen'], $f['reputacion']), array(true, 200, 'Ganaron los héroes.', 'positiva'));
prueba('formulario: el narrador y los jugadores sí, un ajeno no', array(opg_ent_form($S, 8)['ok'], opg_ent_form($S, 3)['ok'], opg_ent_form($S, 9)['ok']), array(true, true, false));
$r = opg_ent_enviar($S, array('tema' => '200', 'resumen' => 'Ahora con más detalle.', 'reputacion' => 'negativa', 'confirmar_grupo' => '1'), 8);
$e = opg_ent_obtener_por_id($E);
prueba('reenviar: vuelve a pendiente con un intento más y sin motivo', array($r['ok'], $r['id'], $e['estado'], $e['intentos'], $e['motivo_rechazo'], $e['reputacion']), array(true, $E, 'pendiente', 2, '', 'negativa'));

// ── Análisis ─────────────────────────────────────────────────────────────────
prueba('analizar una inexistente', opg_ent_analizar(99999, array())['codigo'], 404);
$a = opg_ent_analizar($E, array());
prueba('análisis: tier de la solicitud por defecto', $a['ajustes']['tier'], 3);
prueba('análisis: reputación por defecto la del narrador', $a['ajustes']['reputacion_defecto'], 'negativa');
$por = array(); foreach ($a['destinatarios'] as $d) { $por[$d['uid']] = $d; }
prueba('análisis: detecta los 5 usuarios con narrador primero', array_keys($por)[0] === 8 && count($por) === 5, true);
prueba('análisis: narrador tomado de la entrega', array($por[8]['rol'], $por[3]['rol']), array('narrador', 'jugador'));
prueba('análisis: posts válidos (299 caracteres no cuentan, etiquetas se descuentan)', array($por[8]['validos'], $por[3]['validos'], $por[5]['validos'], $por[5]['posts'], $por[6]['validos'], $por[20]['validos']), array(8, 6, 4, 6, 6, 3));
prueba('análisis: porcentajes del tier 3 (6 esperados)', array($por[8]['porcentaje'], $por[3]['porcentaje'], $por[5]['porcentaje'], $por[6]['porcentaje']), array(116.67, 100.0, 66.67, 100.0));
prueba('análisis: incluye por defecto solo a quien está en la solicitud', array($por[8]['incluir'], $por[3]['incluir'], $por[5]['incluir'], $por[6]['incluir'], $por[20]['incluir']), array(true, true, true, true, false));
prueba('análisis: avisa de quien escribió sin estar en la solicitud', in_array('Escribió en el tema sin estar en la solicitud.', $por[20]['avisos'], true), true);
prueba('análisis: mínimos (6 x 5 participantes detectados)', array($a['minimos']['minimo'], $a['minimos']['validos'], $a['minimos']['cumple']), array(30, 27, false));
prueba('análisis: sin confirmar los mínimos no se puede pagar', array($a['requiere_confirmar_minimos'], $a['puede_pagar']), array(true, false));
// Recompensas del tier 3
prueba('análisis: jugador 100% T3', array($por[3]['calculo']['experiencia'], $por[3]['calculo']['nikas'], $por[3]['calculo']['reputacion'], $por[3]['calculo']['berries']), array(120, 20, 50, 10000000));
prueba('análisis: jugador 66.67% T3', array($por[5]['calculo']['experiencia'], $por[5]['calculo']['nikas'], $por[5]['calculo']['reputacion'], $por[5]['calculo']['berries']), array(80, 13, 33, 6667000));
prueba('análisis: narrador Aprendiz T3 116.67%', array($por[8]['calculo']['experiencia'], $por[8]['calculo']['nikas'], $por[8]['calculo']['cofre'], $por[8]['calculo']['cofre_cantidad']), array(140, 26, 'CFN003', 2));
// Ajustes
$a2 = opg_ent_analizar($E, array('tier' => 5, 'extra_jugadores' => '20', 'n_participantes' => '4', 'confirma_minimos' => '0', 'destinatarios' => array(5 => array('variacion' => '2', 'rep' => 'ninguna'), 20 => array('incluir' => '1'))));
$por2 = array(); foreach ($a2['destinatarios'] as $d) { $por2[$d['uid']] = $d; }
prueba('ajustes: tier 5 (8 esperados) y extra de narrador', array($a2['ajustes']['tier'], $por2[8]['porcentaje'], $por2[3]['porcentaje']), array(5, 120.0, 75.0));
prueba('ajustes: variación de +2 posts', $por2[5]['porcentaje'], 75.0);
prueba('ajustes: sin reputación', array($por2[5]['rep'], $por2[5]['calculo']['reputacion']), array('ninguna', 0));
prueba('ajustes: incluir a un tercero', $por2[20]['incluir'], true);
prueba('ajustes: mínimos con 4 participantes y tier 5 (8 x 4)', $a2['minimos']['minimo'], 32);
$a3 = opg_ent_analizar($E, array('tier' => 99));
prueba('ajustes inválidos bloquean el pago', array($a3['puede_pagar'], count($a3['bloqueos']) > 0), array(false, true));
$a4 = opg_ent_analizar($E, array('destinatarios' => array(8 => array('incluir' => '0'), 3 => array('incluir' => '0'), 5 => array('incluir' => '0'), 6 => array('incluir' => '0')), 'confirma_minimos' => '1'));
prueba('sin destinatarios no se puede pagar', in_array('Selecciona al menos un destinatario.', $a4['bloqueos'], true), true);
$a5 = opg_ent_analizar($E, array('confirma_minimos' => '1'));
prueba('con los mínimos confirmados se puede pagar', array($a5['puede_pagar'], $a5['bloqueos']), array(true, array()));
// Un porcentaje enviado por el cliente se ignora
$a6 = opg_ent_analizar($E, array('confirma_minimos' => '1', 'destinatarios' => array(3 => array('porcentaje' => 999, 'variacion' => '0'))));
$por6 = array(); foreach ($a6['destinatarios'] as $d) { $por6[$d['uid']] = $d; }
prueba('el porcentaje del cliente se ignora', $por6[3]['porcentaje'], 100.0);
// Narrador explícito de la entrega sin posts y participantes sin posts.
$S3 = nueva_sol(3, 0, 9, array(3, 10));
$E3 = opg_ent_enviar($S3, array('tema' => '203', 'resumen' => 'x', 'reputacion' => 'positiva', 'confirmar_grupo' => '1'), 9)['id'];
sql("UPDATE mybb_op_peticionAventuras SET narrador_uid = 8, narrador_fid = 8, narrador_nombre = 'Robin' WHERE id = $S3");
$a7 = opg_ent_analizar($E3, array());
$por7 = array(); foreach ($a7['destinatarios'] as $d) { $por7[$d['uid']] = $d; }
prueba('el narrador no depende de etiquetas y conserva el de la entrega', array($por7[9]['rol'], $por7[9]['posts'], $por7[8]['rol'] ?? null), array('narrador', 0, null));
prueba('avisa del narrador y participantes que no escribieron', count(array_filter($a7['advertencias'], function ($x) { return strpos($x, 'no escribió en el tema') !== false; })) >= 2, true);
// Tema sin posts
$S4 = nueva_sol(3, 0, 9, array(3));
sql("UPDATE mybb_op_peticionAventuras SET narrador_uid = 9, narrador_fid = 9 WHERE id = $S4");
crear_tema(204, 40, 'Aventura vacía');
// Un tema sin posts no es un error de elección, pero sí del análisis
$E4 = (int) opg_ent_enviar($S4, array('tema' => '204', 'resumen' => 'x', 'reputacion' => 'positiva', 'confirmar_grupo' => '1'), 9)['id'];
prueba('un tema sin posts no se puede analizar', opg_ent_analizar($E4, array())['codigo'], 422);

echo "  (parte 3a completa)\n";
require __DIR__ . '/entregas_db_2.php';
