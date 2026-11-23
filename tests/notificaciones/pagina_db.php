<?php
/**
 * op/notificaciones.php como pagina HTTP con MySQL real (issue #7, docs/208 apartado 8).
 *
 * Usa su propia base (opg_notif_n07) en el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) y ejecuta la
 * pagina con harness_pagina.php. Cubre: invitados redirigidos sin consultas, solo las filas del uid de la sesion (un uid en
 * la peticion se ignora), el orden de los bloques, los enlaces a destinos borrados, los filtros sin la campana, cargar mas
 * de 30 en 30 sin huecos ni repetidos, y que abrir la pagina no marca nada ni toca el contador.
 *
 *   php tests/notificaciones/pagina_db.php
 */
require __DIR__ . '/bootstrap_db.php';
const NT_BASE = 'opg_notif_n07';
$db = nt_preparar(NT_BASE);
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';

/** Llama a la pagina y devuelve array(estado, cabeceras, cuerpo, sentencias). */
function llamar($uid, $get = array(), $htmx = false, $metodo = 'GET')
{
    $json = json_encode(array('base' => NT_BASE, 'uid' => $uid, 'get' => $get, 'metodo' => $metodo,
        'server' => $htmx ? array('HTTP_HX_REQUEST' => 'true') : array()));
    $salida = shell_exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/harness_pagina.php') . ' ' . escapeshellarg($json) . ' 2>&1');
    $r = json_decode((string) $salida, true);
    return is_array($r) ? $r : array('estado' => -1, 'cabeceras' => array(), 'cuerpo' => (string) $salida, 'sentencias' => array());
}
function cabecera($r, $nombre)
{
    foreach ($r['cabeceras'] as $h) {
        if (stripos($h, $nombre . ':') === 0) {
            return trim(substr($h, strlen($nombre) + 1));
        }
    }
    return null;
}
/** Filas de una consulta de comprobacion. */
function filas($sql) { return mysqli_fetch_all(nt_sql($sql), MYSQLI_ASSOC); }
function tiene($r, $texto) { return strpos($r['cuerpo'], $texto) !== false; }
/** ids de las filas de una respuesta, en orden. */
function ids($html)
{
    preg_match_all('/id="nt-n-([0-9]+)"/', $html, $m);
    return array_map('intval', $m[1]);
}
/** Cursor del "Cargar más" de una respuesta, o null. */
function cursor_mas($html)
{
    return preg_match('/action=mas&amp;bloque=([a-z]+)&amp;antes=([0-9_]+)"/', $html, $m) ? array($m[1], $m[2]) : null;
}

$ahora = time();
$db->query('CREATE TABLE `mybb_op_peticionAventuras` (id INT UNSIGNED NOT NULL PRIMARY KEY, estado TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
nt_sql('INSERT INTO mybb_op_peticionAventuras (id, estado) VALUES (9, 1), (10, 5)');
nt_sql("INSERT INTO mybb_threads (tid, subject, visible) VALUES (100, 'Rumbo', 1), (102, 'Oculto', -1)");

// Usuario 3: dos importantes sin leer (una sobre una aventura borrada), dos normales sin leer (una sobre un tema que no
// existe) y una leida. Usuario 4: una con un texto que el 3 nunca debe ver.
$n = function ($uid, $tipo, $datos, $clave, $actor = 0) {
    $r = opg_notificar($uid, $tipo, $datos, array('clave' => $clave, 'actor' => $actor, 'agrupar_por' => isset($datos['tid']) ? $datos['tid'] : null, 'tema_publico' => true));
    nt_ok($r === 1, "aviso $clave creado");
};
$n(3, 'aventura_denegada', array('peticion_id' => 9, 'tema' => 'Viaje a Alfa', 'actor' => 'Sengoku'), 'p:9:denegada', 50);
$n(3, 'entrega_rechazada', array('peticion_id' => 10, 'tema' => 'Aventura borrada'), 'p:10:rechazada', 50);
$n(3, 'bitacora_post', array('tid' => 100, 'tema' => 'Rumbo'), null, 6);
$n(3, 'bitacora_post', array('tid' => 101), null, 6);
$n(3, 'aventura_aprobada', array('peticion_id' => 9, 'tema' => 'Ya leida'), 'p:9:aprobada', 50);
$n(4, 'aventura_aprobada', array('peticion_id' => 9, 'tema' => 'Secreto de Zoro'), 'p:9:aprobada', 50);
$leida = (int) nt_valor("SELECT id FROM mybb_op_notificaciones WHERE uid = 3 AND tipo = 'aventura_aprobada'");
nt_ok(opg_notif_marcar_vista(3, $leida) === true, 'preparacion: una leida');
// Fechas distintas para un orden determinista.
nt_sql('UPDATE mybb_op_notificaciones SET created_at = ' . ($ahora - 300) . ' - id WHERE uid IN (3, 4)');

// ── Invitados ────────────────────────────────────────────────────────────────
$r = llamar(0);
nt_prueba('invitado: redirigido al inicio de sesion', cabecera($r, 'Location'), 'https://onepiecegaiden.com/member.php?action=login');
nt_prueba('invitado: sin consultas', $r['sentencias'], array());
nt_prueba('invitado: sin contenido', $r['cuerpo'], '');
$r = llamar(0, array('action' => 'listar'), true);
nt_prueba('invitado en HTMX: HX-Redirect sin consultas', array(cabecera($r, 'HX-Redirect'), $r['sentencias']), array('https://onepiecegaiden.com/member.php?action=login', array()));

// ── Contrato de las acciones ─────────────────────────────────────────────────
nt_prueba('accion sin HTMX: 404', llamar(3, array('action' => 'listar'))['estado'], 404);
nt_prueba('accion desconocida: 404', llamar(3, array('action' => 'visto'), true)['estado'], 404);
nt_prueba('POST: 404 (las acciones de cambio son de #8)', llamar(3, array('action' => 'listar'), true, 'POST')['estado'], 404);
nt_prueba('mas con bloque desconocido: 404', llamar(3, array('action' => 'mas', 'bloque' => 'x', 'antes' => '1_1'), true)['estado'], 404);
nt_prueba('mas sin cursor o con cursor no valido: 404', array(llamar(3, array('action' => 'mas', 'bloque' => 'pendientes'), true)['estado'],
    llamar(3, array('action' => 'mas', 'bloque' => 'pendientes', 'antes' => "1' OR 1"), true)['estado']), array(404, 404));

// ── Pagina completa ──────────────────────────────────────────────────────────
$antes_filas = filas('SELECT id, leida_en, clave_evento FROM mybb_op_notificaciones ORDER BY id');
$antes_contador = filas('SELECT * FROM mybb_op_notificaciones_contador ORDER BY uid');
$r = llamar(3, array('uid' => '4'));
nt_prueba('pagina: 200 con la plantilla', array($r['estado'], tiene($r, '<h1>Notificaciones</h1>'), tiene($r, 'id="nt-contenido"')), array(200, true, true));
nt_prueba('pagina: solo las del usuario de la sesion (el uid de la peticion se ignora)', array(tiene($r, 'Viaje a Alfa'), tiene($r, 'Secreto de Zoro')), array(true, false));
$cuerpo = $r['cuerpo'];
$pos = array(strpos($cuerpo, 'id="nt-t-importantes"'), strpos($cuerpo, 'id="nt-t-pendientes"'), strpos($cuerpo, 'id="nt-t-historial"'));
nt_ok($pos[0] !== false && $pos[0] < $pos[1] && $pos[1] < $pos[2], 'pagina: importantes, pendientes e historial en orden');
nt_prueba('pagina: cinco filas', count(ids($cuerpo)), 5);
nt_prueba('pagina: la leida va en el historial', strpos($cuerpo, 'id="nt-n-' . $leida . '"') > $pos[2], true);
nt_prueba('pagina: enlace a la aventura que existe', tiene($r, 'href="https://onepiecegaiden.com/op/tablon_de_aventuras.php?id=9"'), true);
nt_prueba('pagina: sin enlace a la aventura borrada, con su texto', array(tiene($r, 'tablon_de_aventuras.php?id=10"'), tiene($r, 'Aventura borrada')), array(false, true));
nt_prueba('pagina: enlace al tema visible y no al que no existe', array(tiene($r, 'showthread.php?tid=100&amp;action=newpost'), tiene($r, 'showthread.php?tid=101')), array(true, false));
nt_prueba('pagina: actor guardado en los datos', tiene($r, '</i> Sengoku</span>'), true);
nt_prueba('pagina: post key para las acciones de #8', tiene($r, '{"X-Post-Key":"clave-valida"}'), true);
$sql = implode("\n", $r['sentencias']);
nt_prueba('pagina: tres lecturas de la lista y dos de destinos, sin uniones ni escrituras',
    array(count($r['sentencias']), stripos($sql, 'JOIN'), preg_match('/^\s*(UPDATE|INSERT|DELETE|START)/mi', $sql)), array(5, false, 0));
nt_ok(strpos($sql, 'WHERE uid = 3 ') !== false && strpos($sql, 'uid = 4') === false, 'pagina: las consultas usan el uid de la sesion');
nt_prueba('abrir la pagina no marca nada', filas('SELECT id, leida_en, clave_evento FROM mybb_op_notificaciones ORDER BY id'), $antes_filas);
nt_prueba('abrir la pagina no toca el contador', filas('SELECT * FROM mybb_op_notificaciones_contador ORDER BY uid'), $antes_contador);

$r = llamar(4);
nt_prueba('otro usuario (modo espejo o cambio de personaje: el uid activo) ve solo lo suyo', array(tiene($r, 'Secreto de Zoro'), tiene($r, 'Viaje a Alfa')), array(true, false));
$r = llamar(9);
nt_prueba('usuario sin notificaciones: estado vacio', array(tiene($r, 'No tienes notificaciones'), tiene($r, 'nt-bloque')), array(true, false));

// ── Filtros ──────────────────────────────────────────────────────────────────
$r = llamar(3, array('action' => 'listar', 'categoria' => 'temas'), true);
nt_prueba('filtro temas: solo la Bitacora', array(count(ids($r['cuerpo'])), tiene($r, 'Viaje a Alfa'), tiene($r, 'Rumbo')), array(2, false, true));
nt_prueba('filtro: fragmento sin la campana ni la plantilla', array(tiene($r, 'op-notif-campana'), tiene($r, 'CAMPANA'), tiene($r, '<html')), array(false, false, false));
nt_prueba('filtro: opcion activa', tiene($r, 'nt-filtro--activo" href="notificaciones.php?categoria=temas"'), true);
$r = llamar(3, array('action' => 'listar', 'origen' => 'gestion'), true);
nt_prueba('filtro de gestion', array(count(ids($r['cuerpo'])), tiene($r, 'Rumbo')), array(3, false));
$r = llamar(3, array('action' => 'listar', 'categoria' => 'ficha'), true);
nt_prueba('filtro sin resultados: estado vacio', tiene($r, 'No tienes notificaciones'), true);
$r = llamar(3, array('categoria' => 'temas'));
nt_prueba('pagina completa con filtro en la URL', count(ids($r['cuerpo'])), 2);
$r = llamar(3, array('action' => 'listar', 'categoria' => 'inventada'), true);
nt_prueba('filtro no valido: Todas', count(ids($r['cuerpo'])), 5);

// ── Cargar mas de 30 en 30 ───────────────────────────────────────────────────
$valores = array();
for ($i = 1; $i <= 65; $i++) {
    // Pares de filas con la misma fecha: el cursor debe desempatar por id.
    $valores[] = "(5, 'aventura_aprobada', 'aventuras', 'gestion', 'normal', 0, 0, 'carga:$i', NULL, 1, '{\"peticion_id\":9}', " . ($ahora - 1000 + (int) ($i / 2)) . ', NULL)';
}
nt_sql('INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, importancia, agrupable, actor_uid, clave_evento, url, cantidad, datos_json, created_at, leida_en) VALUES ' . implode(',', $valores));
$r = llamar(5);
$vistos = ids($r['cuerpo']);
$c = cursor_mas($r['cuerpo']);
nt_prueba('primera pagina: 30 y Cargar mas del bloque pendientes', array(count($vistos), $c ? $c[0] : null), array(30, 'pendientes'));
$r = llamar(5, array('action' => 'mas', 'bloque' => $c[0], 'antes' => $c[1]), true);
$pagina = ids($r['cuerpo']);
$c = cursor_mas($r['cuerpo']);
nt_prueba('segunda pagina: 30 mas, solo filas y el boton', array(count($pagina), $c !== null, tiene($r, 'nt-bloque'), substr($r['cuerpo'], 0, 3)), array(30, true, false, '<li'));
$vistos = array_merge($vistos, $pagina);
$r = llamar(5, array('action' => 'mas', 'bloque' => $c[0], 'antes' => $c[1]), true);
$vistos = array_merge($vistos, ids($r['cuerpo']));
nt_prueba('ultima pagina: 5 y sin boton', array(count(ids($r['cuerpo'])), cursor_mas($r['cuerpo'])), array(5, null));
$esperados = array_map('intval', array_column(filas('SELECT id FROM mybb_op_notificaciones WHERE uid = 5 ORDER BY created_at DESC, id DESC'), 'id'));
nt_prueba('las tres paginas: todas, en orden, sin repetidas', $vistos, $esperados);
$r = llamar(5, array('action' => 'mas', 'bloque' => 'pendientes', 'antes' => $c[1], 'categoria' => 'temas'), true);
nt_prueba('cargar mas respeta el filtro', ids($r['cuerpo']), array());
$r = llamar(3, array('action' => 'mas', 'bloque' => 'pendientes', 'antes' => $c[1]), true);
nt_prueba('cargar mas con el cursor de otro usuario: solo filas propias', array_diff(ids($r['cuerpo']), array_map('intval', array_column(filas('SELECT id FROM mybb_op_notificaciones WHERE uid = 3'), 'id'))), array());

nt_borrar();
nt_resumen('pagina de notificaciones con MySQL');
