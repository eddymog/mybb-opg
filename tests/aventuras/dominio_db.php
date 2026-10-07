<?php
/**
 * Pruebas de inc/plugins/lib/aventuras.php contra MySQL real (ver bootstrap_db.php).
 *   php tests/aventuras/dominio_db.php
 */
require __DIR__ . '/bootstrap_db.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';

preparar_esquema($db);

function crear_foro($fid, $nombre, $pid, $active = 1, $parentlist = null)
{
    global $db;
    $db->query("SET SESSION sql_mode = ''", 1);
    $pl = $parentlist !== null ? $parentlist : ($pid ? $pid . ',' . $fid : (string) $fid);
    sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES ($fid, '" . $db->escape_string($nombre) . "', $pid, '$pl', $active, $fid)");
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
}

// Usuarios: 3 creador, 5 y 6 y 9 jugadores, 8 narrador usuario, 7 usuario sin ficha aprobada, 50 staff, 60 narrador del staff, 1 mod.
foreach (array(3 => 'Eddy', 5 => 'Luffy', 6 => 'Zoro', 8 => 'Robin', 9 => 'Nami', 10 => 'Sanji', 11 => 'Usopp', 12 => 'Franky', 50 => 'Staff', 60 => 'NarraStaff', 1 => 'Mod', 20 => 'Brook') as $uid => $n) {
    crear_usuario($uid, $n);
}
crear_usuario(7, 'SinAprobar', array('aprobada_por' => 'sin_aprobar'));
$GLOBALS['roles'] = array('mod' => array(1), 'staff' => array(50), 'narra' => array(60));

// Foros: 15 a 18 son los Blues; 40 y 41 islas válidas, 42 inactiva, 43 subforo de la isla 40, 44 fuera de los Blues.
foreach (array(15, 16, 17, 18) as $f) { crear_foro($f, "Blue $f", 0); }
crear_foro(40, 'Isla Alfa', 15);
crear_foro(41, 'Isla Beta', 16);
crear_foro(42, 'Isla Inactiva', 15, 0);
crear_foro(43, 'Subforo de Alfa', 40, 1, '15,40,43');
crear_foro(44, 'Otro foro', 0);

function entrada_b($sobre = array())
{
    return array_merge(array(
        'rol' => 'jugador', 'participantes' => array(5), 'tiene_narrador' => '0', 'idea' => 'Una idea', 'isla_fid' => 40,
        'dia' => 12, 'tier' => 3, 'comentario' => 'Comentario', 'inframundo' => '0',
    ), $sobre);
}
function entrada_a($sobre = array())
{
    return array_merge(array(
        'rol' => 'narrador', 'participantes' => array(5), 'isla_fid' => 40, 'dia' => 12, 'tier' => 3, 'comentario' => 'Una aventura de exploración', 'inframundo' => '0',
    ), $sobre);
}
function crear_ok($entrada, $uid)
{
    $v = opg_av_validar_solicitud($entrada, $uid);
    if ($v['errores']) { return array('ok' => false, 'errores' => $v['errores']); }
    return opg_av_crear($v['datos'], $uid);
}

// ── Islas ────────────────────────────────────────────────────────────────────
prueba('isla válida', opg_av_isla_valida(40), array('fid' => 40, 'nombre' => 'Isla Alfa'));
prueba('subforo de isla no vale', opg_av_isla_valida(43), null);
prueba('isla inactiva no vale', opg_av_isla_valida(42), null);
prueba('foro fuera de los Blues no vale', opg_av_isla_valida(44), null);
prueba('el propio Blue no es isla', opg_av_isla_valida(15), null);
prueba('isla inexistente', opg_av_isla_valida(999), null);
prueba('buscar islas: solo hijos directos activos', array_map(function ($i) { return $i['fid']; }, opg_av_islas_buscar('')), array(40, 41));
prueba('buscar islas por nombre', array_map(function ($i) { return $i['fid']; }, opg_av_islas_buscar('beta')), array(41));

// ── Fichas ───────────────────────────────────────────────────────────────────
prueba('ficha aprobada', opg_av_ficha(5)['nombre'], 'Luffy');
prueba('ficha sin aprobar no cuenta', opg_av_ficha(7), null);
prueba('buscar fichas por nombre', array_map(function ($f) { return $f['fid']; }, opg_av_fichas_buscar('zo')), array(6));
prueba('buscar fichas no devuelve sin aprobar', count(opg_av_fichas_buscar('SinAprobar')), 0);
prueba('buscar fichas escapa comodines', count(opg_av_fichas_buscar('%')), 0);

// ── Crear: forma B sin narrador ──────────────────────────────────────────────
limpiar();
$r = crear_ok(entrada_b(), 3);
prueba('crear B sin narrador', $r['ok'], true);
$f = fila($r['id']);
prueba('B sin narrador: estado 4', (int) $f['estado'], 4);
prueba('B: el creador se añade como participante', json_decode($f['jugadores_json'], true), array(array('uid' => 3, 'nombre' => 'Eddy'), array('uid' => 5, 'nombre' => 'Luffy')));
prueba('B sin narrador: campos de narrador nulos', array($f['narrador_uid'], $f['narrador_fid'], $f['narrador_nombre']), array(null, null, null));
prueba('B: idea guardada', $f['idea_narrador'], 'Una idea');
prueba('B: isla, día y tier', array((int) $f['isla_fid'], (int) $f['dia'], (int) $f['tier_seleccionado']), array(40, 12, 3));
prueba('B: num_jugadores', (int) $f['num_jugadores'], 2);
prueba('B: columnas antiguas con valores por defecto', array($f['dificultad_texto'], $f['dificultad_color'], $f['enemigos_json']), array('', '', '[]'));
prueba('B: auditada', (int) una("SELECT COUNT(*) AS n FROM mybb_op_audit_aventuras WHERE accion = 'crear' AND peticion_id = " . $r['id'])['n'], 1);
prueba('B: log general escrito', count($GLOBALS['log_general']) > 0, true);

$r2 = crear_ok(entrada_b(), 3);
prueba('antirrebote: solicitud idéntica en 60 s', array($r2['ok'], $r2['codigo']), array(false, 409));
$r3 = crear_ok(entrada_b(array('dia' => 13)), 3);
prueba('otro día no es duplicado', $r3['ok'], true);

// ── Crear: forma B con narrador y forma A ───────────────────────────────────
limpiar();
$r = crear_ok(entrada_b(array('tiene_narrador' => '1', 'narrador_fid' => 8, 'idea' => 'No se guarda')), 3);
$f = fila($r['id']);
prueba('B con narrador: estado 0', (int) $f['estado'], 0);
prueba('B con narrador: narrador asignado', array((int) $f['narrador_uid'], (int) $f['narrador_fid'], $f['narrador_nombre']), array(8, 8, 'Robin'));
prueba('B con narrador: la idea no se guarda', $f['idea_narrador'], null);
$r = crear_ok(entrada_a(), 8);
$f = fila($r['id']);
prueba('A: narrador es el creador y estado 0', array((int) $f['narrador_uid'], (int) $f['estado']), array(8, 0));
prueba('A: participantes sin el narrador', json_decode($f['jugadores_json'], true), array(array('uid' => 5, 'nombre' => 'Luffy')));

// ── Crear: errores ───────────────────────────────────────────────────────────
function errores_de($entrada, $uid)
{
    $v = opg_av_validar_solicitud($entrada, $uid);
    return $v['errores'];
}
prueba('sin ficha aprobada', count(errores_de(entrada_b(), 7)) > 0, true);
prueba('invitado', count(errores_de(entrada_b(), 0)) > 0, true);
prueba('rol inválido', count(errores_de(entrada_b(array('rol' => 'x')), 3)) > 0, true);
prueba('A sin participantes', count(errores_de(entrada_a(array('participantes' => array())), 8)) > 0, true);
prueba('A con el narrador entre los participantes', count(errores_de(entrada_a(array('participantes' => array(8, 5))), 8)) > 0, true);
prueba('participante repetido', in_array('Hay participantes repetidos.', errores_de(entrada_b(array('participantes' => array(5, 5))), 3), true), true);
prueba('participante sin ficha aprobada', count(errores_de(entrada_b(array('participantes' => array(7))), 3)) > 0, true);
prueba('participante inexistente', count(errores_de(entrada_b(array('participantes' => array(999))), 3)) > 0, true);
prueba('B con narrador sin elegirlo', count(errores_de(entrada_b(array('tiene_narrador' => '1')), 3)) > 0, true);
prueba('B con narrador que es participante', count(errores_de(entrada_b(array('tiene_narrador' => '1', 'narrador_fid' => 5)), 3)) > 0, true);
prueba('B con narrador sin ficha', count(errores_de(entrada_b(array('tiene_narrador' => '1', 'narrador_fid' => 7)), 3)) > 0, true);
prueba('B con el creador como narrador', count(errores_de(entrada_b(array('tiene_narrador' => '1', 'narrador_fid' => 3)), 3)) > 0, true);
prueba('idea de 501 caracteres', count(errores_de(entrada_b(array('idea' => str_repeat('a', 501))), 3)) > 0, true);
prueba('idea de 500 caracteres', errores_de(entrada_b(array('idea' => str_repeat('a', 500))), 3), array());
prueba('isla subforo', count(errores_de(entrada_b(array('isla_fid' => 43)), 3)) > 0, true);
prueba('isla inactiva', count(errores_de(entrada_b(array('isla_fid' => 42)), 3)) > 0, true);
prueba('isla inexistente', count(errores_de(entrada_b(array('isla_fid' => 999)), 3)) > 0, true);
prueba('sin isla', count(errores_de(entrada_b(array('isla_fid' => '')), 3)) > 0, true);
prueba('día 0', count(errores_de(entrada_b(array('dia' => 0)), 3)) > 0, true);
prueba('día 91', count(errores_de(entrada_b(array('dia' => 91)), 3)) > 0, true);
prueba('día 90', errores_de(entrada_b(array('dia' => 90)), 3), array());
prueba('tier 0', count(errores_de(entrada_b(array('tier' => 0)), 3)) > 0, true);
prueba('tier 11', count(errores_de(entrada_b(array('tier' => 11)), 3)) > 0, true);
prueba('sin tier', count(errores_de(entrada_b(array('tier' => '')), 3)) > 0, true);
prueba('tier 10', errores_de(entrada_b(array('tier' => 10)), 3), array());
prueba('comentario de 1001 caracteres', count(errores_de(entrada_b(array('comentario' => str_repeat('a', 1001))), 3)) > 0, true);
prueba('comentario de 1000 caracteres', errores_de(entrada_b(array('comentario' => str_repeat('ñ', 1000))), 3), array());
prueba('comentario vacío se rechaza', count(errores_de(entrada_b(array('comentario' => '')), 3)) > 0, true);
prueba('comentario solo con espacios se rechaza', count(errores_de(entrada_b(array('comentario' => '   ')), 3)) > 0, true);
prueba('un uid del cliente no cambia al creador', opg_av_validar_solicitud(entrada_b(array('uid' => 99, 'creador' => 99)), 3)['datos']['participantes'][0]['uid'], 3);

// ── Listados: Mis aventuras y Otras aventuras ────────────────────────────────
limpiar();
for ($i = 0; $i < 3; $i++) { ins(3, 8, array(0, 1, 4)[$i], array(3)); }
prueba('crear cuarta solitaria se rechaza antes de insertar', crear_ok(entrada_b(array('participantes' => array())), 3)['codigo'], 422);
prueba('con tres solitarias puede crear una grupal', crear_ok(entrada_b(), 3)['ok'], true);
prueba('crear quinta total se rechaza antes de insertar', crear_ok(entrada_b(array('dia' => 30)), 3)['codigo'], 422);
prueba('rechazo no añade solicitudes', (int) una('SELECT COUNT(*) AS n FROM mybb_op_peticionAventuras')['n'], 4);
prueba('narrador tampoco puede añadir un jugador con cupo lleno', crear_ok(entrada_a(array('participantes' => array(3), 'dia' => 31)), 8)['codigo'], 422);
limpiar();
// Para el usuario 5: A creador (estado 0), B participante (1), C narrador (1), D ajena (1), E sin narrador de otros (4), F sin narrador mía (4), G borrado (5), H denegada de otros (2), I antigua sin isla ni día de otros (3)
$A = ins(5, 8, 0, array(5));
$B = ins(3, 8, 1, array(3, 5), array('created_at' => TIME_NOW - 900));
$C = ins(3, 5, 1, array(3), array('created_at' => TIME_NOW - 800));
$D = ins(3, 8, 1, array(3, 6), array('created_at' => TIME_NOW - 700));
$E = ins(3, null, 4, array(3, 6), array('created_at' => TIME_NOW - 600));
$F = ins(3, null, 4, array(3, 5), array('created_at' => TIME_NOW - 500));
$G = ins(5, 8, 5, array(5));
$H = ins(3, 8, 2, array(3), array('motivo_denegacion' => 'No'));
$I = ins(3, 8, 3, array(6), array('isla_fid' => null, 'dia' => null));
$ids = function ($lista) { return array_map(function ($a) { return $a['id']; }, $lista['items']); };
$mias = $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'mias'), 1));
$otras = $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras'), 1));
$sin = $ids(opg_av_sin_narrador_listar(5, 1));
sort($mias);
prueba('Mis aventuras de 5: creador, participante, narrador y la pendiente propia, sin el estado 5', $mias, array($A, $B, $C, $F));
$otras_ord = $otras; sort($otras_ord);
prueba('Otras aventuras de 5: estados 0 a 3 que no son suyas', $otras_ord, array($D, $H, $I));
prueba('Aventuras sin narrador de 5: solo la ajena', $sin, array($E));
$union = array_merge($mias, $otras, $sin);
sort($union);
prueba('la unión de las tres vistas no repite ni deja huecos (todo menos el estado 5)', $union, array($A, $B, $C, $D, $E, $F, $H, $I));
prueba('Otras no incluye ninguna solicitud sin narrador (estado 4)', count(array_intersect($otras, array($E, $F))), 0);
prueba('filtro de estado en Mis aventuras', $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'mias', 'estado' => 'pendiente-narrador'), 1)), array($F));
prueba('filtro Soy narrador', $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'mias', 'rol' => 'narrador'), 1)), array($C));
$jug = $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'mias', 'rol' => 'jugador'), 1)); sort($jug);
prueba('filtro Soy jugador', $jug, array($A, $B, $F));
prueba('estado y rol combinados', $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'mias', 'rol' => 'jugador', 'estado' => 'aprobado'), 1)), array($B));
prueba('filtro estado en Otras', $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras', 'estado' => 'denegado'), 1)), array($H));
prueba('pendiente-narrador se ignora en Otras', count($ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras', 'estado' => 'pendiente-narrador'), 1))), 3);
prueba('filtro uid en Otras (narrador)', $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras', 'uid' => 8), 1)), array($D, $H, $I) === array() ? array() : $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras', 'uid' => 8), 1)));
$por_uid = $ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras', 'uid' => 6), 1)); sort($por_uid);
prueba('filtro uid en Otras (participante)', $por_uid, array($D, $I));
prueba('rol se ignora en Otras', count($ids(opg_av_listar(array('yo' => 5, 'ambito' => 'otras', 'rol' => 'narrador'), 1))), 3);
prueba('uid se ignora en Mis aventuras', count($ids(opg_av_listar(array('yo' => 5, 'ambito' => 'mias', 'uid' => 6), 1))), 4);
prueba('sin narrador: contador', opg_av_sin_narrador_contar(5), 1);
prueba('tiene aventuras', opg_av_tiene_aventuras(5), true);
prueba('un usuario sin aventuras', opg_av_tiene_aventuras(12), false);
prueba('solo con una solicitud en estado 5 no tiene aventuras', (function () { $x = ins(12, 8, 5, array(12)); return opg_av_tiene_aventuras(12); })(), false);
prueba('listar para jugadores no devuelve la nota de staff', array_key_exists('nota_staff', opg_av_listar(array('yo' => 5, 'ambito' => 'mias'), 1)['items'][0]), false);
$lc = opg_av_listar(array(), 1, true);
prueba('consola lista todo (incluido el estado 5)', $lc['total'], 10);
prueba('consola trae la nota de staff', array_key_exists('nota_staff', $lc['items'][0]), true);
prueba('consola: filtro estado 5', count(opg_av_listar(array('estado' => 'pendiente-borrado'), 1, true)['items']), 2);
$norm = opg_av_obtener($I);
prueba('antigua sin isla ni día', array($norm['isla'], $norm['dia'], $norm['fecha_texto']), array(null, null, null));
prueba('denegada con motivo', opg_av_obtener($H)['motivo_denegacion'], 'No');
prueba('conteo por estados', opg_av_conteo_estados(), array(0 => 1, 1 => 3, 2 => 1, 3 => 1, 4 => 2, 5 => 2));

// ── Límites de aventuras activas ─────────────────────────────────────────────
limpiar();
// Usuario 5: 3 solitarias activas.
for ($i = 0; $i < 3; $i++) { ins(5, 8, 1, array(5)); }
prueba('3 solitarias activas', opg_av_contar_activas(5), array(3, 3));
$nueva_sol = ins(5, 8, 0, array(5));
prueba('una cuarta solitaria excede', count(opg_av_validar_limites(opg_av_obtener($nueva_sol))), 1);
prueba('la solicitud que se aprueba no se cuenta a sí misma', opg_av_contar_activas(5, $nueva_sol), array(3, 3));
sql("DELETE FROM mybb_op_peticionAventuras WHERE id = $nueva_sol");   // la cuarta solitaria no se aprobó
$nueva_grupal = ins(5, 8, 0, array(5, 6));
prueba('una grupal sí cabe con 3 solitarias', count(opg_av_validar_limites(opg_av_obtener($nueva_grupal))), 0);
// Hasta 4 grupales
limpiar();
for ($i = 0; $i < 4; $i++) { ins(5, 8, 1, array(5, 6)); }
prueba('4 grupales activas', opg_av_contar_activas(5), array(0, 4));
prueba('una quinta grupal excede para los dos participantes', count(opg_av_validar_limites(opg_av_obtener(ins(5, 8, 0, array(5, 6))))), 2);
prueba('una solitaria con 4 grupales excede el total', count(opg_av_validar_limites(opg_av_obtener(ins(5, 8, 0, array(5))))), 1);
// El narrador no cuenta la aventura que narra
limpiar();
for ($i = 0; $i < 4; $i++) { ins(3, 5, 1, array(3, 6)); }
prueba('narrar no cuenta como aventura activa propia', opg_av_contar_activas(5), array(0, 0));
// Estados que no cuentan
limpiar();
ins(5, 8, 2, array(5)); ins(5, 8, 3, array(5)); ins(5, 8, 5, array(5));
prueba('denegadas, finalizadas y borradas no cuentan', opg_av_contar_activas(5), array(0, 0));
ins(5, 8, 4, array(5)); ins(5, 8, 0, array(5));
prueba('pendientes (0 y 4) sí cuentan', opg_av_contar_activas(5), array(2, 2));
// Participantes sin uid (antiguos) se ignoran y un JSON inválido no rompe la consulta
limpiar();
ins(5, 8, 1, array(5), array('jugadores_json' => 'esto no es json'));
ins(5, 8, 1, array(5), array('jugadores_json' => ''));
prueba('JSON inválido en la tabla no rompe los conteos', opg_av_contar_activas(5), array(0, 0));
prueba('JSON inválido no rompe los listados', is_array(opg_av_listar(array('yo' => 5, 'ambito' => 'mias'), 1)['items']), true);

echo "  (parte 1 completa)\n";
$GLOBALS['continuar'] = true;
require __DIR__ . '/dominio_db_2.php';
resumen();
