<?php
/**
 * Acciones Visto y Marcar todas de op/notificaciones.php con MySQL real (issue #8, docs/208 apartado 8).
 *
 * Usa su propia base (opg_notif_n08) en el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php) y ejecuta la
 * pagina con harness_pagina.php. Cubre: la clave CSRF (sin ella o incorrecta, 403 sin escribir), solo POST y HTMX, `visto`
 * marca una, devuelve la fila leida y la campana fuera de banda (sin su <style>), pulsar dos veces descuenta una vez, una
 * notificacion ajena no se marca, `todas` marca todas o solo las del filtro y devuelve la lista y la campana, y no hay
 * forma de volver a no leida. Que abrir la pagina o seguir un enlace no marca nada lo cubre pagina_db.php.
 *
 *   php tests/notificaciones/acciones_db.php
 */
require __DIR__ . '/bootstrap_db.php';
const NT_BASE = 'opg_notif_n08';
$db = nt_preparar(NT_BASE);
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';

/** POST (u otro metodo) HTMX a la pagina; $clave es la cabecera X-Post-Key (null: sin cabecera). */
function llamar($uid, $get, $clave = 'clave-valida', $metodo = 'POST', $htmx = true)
{
    $server = $htmx ? array('HTTP_HX_REQUEST' => 'true') : array();
    if ($clave !== null) {
        $server['HTTP_X_POST_KEY'] = $clave;
    }
    $json = json_encode(array('base' => NT_BASE, 'uid' => $uid, 'get' => $get, 'metodo' => $metodo, 'server' => $server));
    $salida = shell_exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/harness_pagina.php') . ' ' . escapeshellarg($json) . ' 2>&1');
    $r = json_decode((string) $salida, true);
    return is_array($r) ? $r : array('estado' => -1, 'cabeceras' => array(), 'cuerpo' => (string) $salida, 'sentencias' => array());
}
function tiene($r, $texto) { return strpos($r['cuerpo'], $texto) !== false; }
function filas($sql) { return mysqli_fetch_all(nt_sql($sql), MYSQLI_ASSOC); }
function contador($uid)
{
    $f = filas('SELECT sin_leer, importantes FROM mybb_op_notificaciones_contador WHERE uid = ' . (int) $uid);
    return $f ? array((int) $f[0]['sin_leer'], (int) $f[0]['importantes']) : null;
}
function sin_leer_reales($uid, $extra = '')
{
    return (int) nt_valor('SELECT COUNT(*) FROM mybb_op_notificaciones WHERE uid = ' . (int) $uid . ' AND leida_en IS NULL' . $extra);
}
function id_de($uid, $clave)
{
    return (int) nt_valor('SELECT id FROM mybb_op_notificaciones WHERE uid = ' . (int) $uid . " AND clave_evento = '" . $clave . "'");
}
function escrituras($r)
{
    return preg_match('/^\s*(UPDATE|INSERT|DELETE|START)/mi', implode("\n", $r['sentencias']));
}

// Usuario 3: dos importantes (aventuras, gestion), una normal de aventuras, dos de temas (usuario). Usuario 4: una.
$n = function ($uid, $tipo, $datos, $clave, $actor = 0) {
    $r = opg_notificar($uid, $tipo, $datos, array('clave' => $clave, 'actor' => $actor, 'tema_publico' => true));
    nt_ok($r === 1, "aviso $clave creado");
};
$n(3, 'aventura_denegada', array('peticion_id' => 9, 'tema' => 'Viaje a Alfa'), 'p:9:denegada', 50);
$n(3, 'entrega_rechazada', array('peticion_id' => 9, 'tema' => 'Viaje a Alfa'), 'p:9:rechazada', 50);
$n(3, 'aventura_aprobada', array('peticion_id' => 9, 'tema' => 'Viaje a Alfa'), 'p:9:aprobada', 50);
$n(3, 'bitacora_post', array('tid' => 100, 'tema' => 'Rumbo'), 't:100', 6);
$n(3, 'bitacora_post', array('tid' => 101, 'tema' => 'Otro rumbo'), 't:101', 6);
$n(4, 'aventura_aprobada', array('peticion_id' => 9, 'tema' => 'Secreto de Zoro'), 'p:9:aprobada', 50);
nt_prueba('preparacion: contador del 3', contador(3), array(5, 2));
$importante = id_de(3, 'p:9:denegada');
$normal = id_de(3, 'p:9:aprobada');
$ajena = id_de(4, 'p:9:aprobada');

// ── Contrato y CSRF ──────────────────────────────────────────────────────────
$antes = filas('SELECT id, leida_en, clave_evento FROM mybb_op_notificaciones ORDER BY id');
$r = llamar(3, array('action' => 'visto', 'id' => (string) $importante), null);
nt_prueba('visto sin clave CSRF: 403 con aviso', array($r['estado'], tiene($r, 'La sesión caducó')), array(403, true));
nt_prueba('visto sin clave CSRF: sin escrituras', escrituras($r), 0);
$r = llamar(3, array('action' => 'todas'), 'clave-falsa');
nt_prueba('todas con clave incorrecta: 403 sin escrituras', array($r['estado'], escrituras($r)), array(403, 0));
nt_prueba('visto por GET: 404 (solo POST)', llamar(3, array('action' => 'visto', 'id' => (string) $importante), 'clave-valida', 'GET')['estado'], 404);
nt_prueba('visto sin HTMX: 404', llamar(3, array('action' => 'visto', 'id' => (string) $importante), 'clave-valida', 'POST', false)['estado'], 404);
nt_prueba('POST de una accion de lectura: 404', llamar(3, array('action' => 'listar'))['estado'], 404);
$r = llamar(0, array('action' => 'visto', 'id' => (string) $importante));
nt_prueba('invitado: HX-Redirect sin consultas', array(in_array('HX-Redirect: https://onepiecegaiden.com/member.php?action=login', $r['cabeceras'], true), $r['sentencias']), array(true, array()));
nt_prueba('nada cambio sin clave, sin POST o sin sesion', filas('SELECT id, leida_en, clave_evento FROM mybb_op_notificaciones ORDER BY id'), $antes);
nt_prueba('el contador tampoco', contador(3), array(5, 2));

// ── Visto ────────────────────────────────────────────────────────────────────
$r = llamar(3, array('action' => 'visto', 'id' => (string) $importante));
nt_prueba('visto: 200', $r['estado'], 200);
nt_prueba('visto: la fila queda leida en la base', (int) nt_valor('SELECT leida_en IS NOT NULL FROM mybb_op_notificaciones WHERE id = ' . $importante), 1);
nt_prueba('visto: descuenta una y una importante', contador(3), array(4, 1));
nt_prueba('visto: devuelve la fila leida, sin boton Visto', array(strpos($r['cuerpo'], '<li class="nt-fila nt-fila--leida nt-fila--importante" id="nt-n-' . $importante . '">'), tiene($r, 'nt-visto')), array(0, false));
nt_prueba('visto: campana fuera de banda con el contador nuevo', array(tiene($r, 'id="op-notif-campana"'), tiene($r, 'hx-swap-oob="outerHTML:#op-notif-campana"'), tiene($r, 'Notificaciones: 4 sin leer, 1 importante')), array(true, true, true));
nt_prueba('visto: la campana llega sin su <style>', tiene($r, '<style'), false);
nt_prueba('visto: no devuelve la pagina ni la lista', array(tiene($r, '<html'), tiene($r, 'nt-filtros')), array(false, false));

$r = llamar(3, array('action' => 'visto', 'id' => (string) $importante));
nt_prueba('visto dos veces: 200 con la misma fila leida', array($r['estado'], tiene($r, 'id="nt-n-' . $importante . '"'), tiene($r, 'nt-fila--leida')), array(200, true, true));
nt_prueba('visto dos veces: no descuenta otra vez', contador(3), array(4, 1));

$r = llamar(3, array('action' => 'visto', 'id' => (string) $ajena));
nt_prueba('visto de una ajena: 404 sin datos de la otra', array($r['estado'], tiene($r, 'La notificación no existe'), tiene($r, 'Zoro')), array(404, true, false));
nt_prueba('visto de una ajena: sigue sin leer y su contador intacto', array(sin_leer_reales(4), contador(4)), array(1, array(1, 0)));
nt_prueba('visto de una ajena: el contador propio no cambia', contador(3), array(4, 1));
$r = llamar(3, array('action' => 'visto', 'id' => (string) $ajena, 'uid' => '4'));
nt_prueba('un uid en la peticion se ignora', array($r['estado'], sin_leer_reales(4)), array(404, 1));
foreach (array('', '0', 'abc', '1 OR 1=1', '-3') as $id) {
    nt_prueba("visto con id no valido ('$id'): 404 sin escrituras", array(llamar(3, array('action' => 'visto', 'id' => $id))['estado'], contador(3)), array(404, array(4, 1)));
}

// ── Marcar todas ─────────────────────────────────────────────────────────────
$r = llamar(3, array('action' => 'todas', 'categoria' => 'temas'));
nt_prueba('todas con filtro: 200', $r['estado'], 200);
nt_prueba('todas con filtro: solo las de temas', array(sin_leer_reales(3, " AND categoria = 'temas'"), sin_leer_reales(3)), array(0, 2));
nt_prueba('todas con filtro: descuenta solo esas', contador(3), array(2, 1));
nt_prueba('todas con filtro: devuelve la lista filtrada y la campana', array(tiene($r, 'nt-filtros'), tiene($r, 'nt-filtro--activo" href="notificaciones.php?categoria=temas"'), tiene($r, 'Nueva publicación'), tiene($r, 'Viaje a Alfa'), tiene($r, 'Notificaciones: 2 sin leer, 1 importante')), array(true, true, true, false, true));
nt_prueba('todas con filtro: ya no ofrece Marcar todas en ese filtro', tiene($r, 'nt-todas'), false);
nt_prueba('todas con filtro: la campana sin <style>', tiene($r, '<style'), false);

$r = llamar(3, array('action' => 'todas', 'categoria' => 'inventada'));
nt_prueba('todas con un filtro no valido es Todas (como la lista)', array($r['estado'], sin_leer_reales(3), contador(3)), array(200, 0, array(0, 0)));
nt_prueba('todas: lista sin pendientes y campana a cero', array(tiene($r, 'id="nt-t-pendientes"'), tiene($r, 'id="nt-t-historial"'), tiene($r, 'no tienes avisos sin leer')), array(false, true, true));
nt_prueba('todas: no toca las del otro usuario', array(sin_leer_reales(4), contador(4)), array(1, array(1, 0)));

$r = llamar(3, array('action' => 'todas'));
nt_prueba('todas sin nada pendiente: 200 y el contador sigue en cero', array($r['estado'], contador(3)), array(200, array(0, 0)));

// ── Leida no vuelve a no leida ───────────────────────────────────────────────
$leidas = filas('SELECT id, leida_en FROM mybb_op_notificaciones WHERE uid = 3 ORDER BY id');
llamar(3, array('action' => 'visto', 'id' => (string) $normal));
llamar(3, array('action' => 'todas'));
nt_prueba('repetir las acciones no cambia leida_en', filas('SELECT id, leida_en FROM mybb_op_notificaciones WHERE uid = 3 ORDER BY id'), $leidas);
foreach (array('desmarcar', 'no_leida', 'marcar_no_leida') as $accion) {
    nt_prueba("no hay accion '$accion'", llamar(3, array('action' => $accion, 'id' => (string) $normal))['estado'], 404);
}
nt_prueba('sigue sin ninguna no leida', sin_leer_reales(3), 0);

nt_borrar();
nt_resumen('acciones Visto y Marcar todas con MySQL');
