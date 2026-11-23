<?php
/**
 * Envio de notificaciones con MySQL real (issue #3, docs/208 apartados 5.2, 5.3, 11 y 13).
 *
 * Usa su propia base (opg_notif_n03) en el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php): la crea con
 * docs/notificaciones_migration.sql y la borra al terminar. Cubre idempotencia, lotes completos y parciales, contador
 * (creacion con recuento, incremento relativo, importantes), agrupacion (tres eventos en una fila con cantidad 3 y el
 * contador +1; tras "Visto" una fila nueva), textos del catalogo con los datos guardados, enlaces, transacciones, fallos
 * de insercion que no se propagan, tablas ausentes, mysqli en modo estricto y dos procesos enviando a la vez.
 *
 *   php tests/notificaciones/envio_db.php
 *
 * (Con "--trabajador NOMBRE BARRERA" el script hace de proceso concurrente para la prueba de concurrencia.)
 */
require __DIR__ . '/bootstrap_db.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

const NT_BASE = 'opg_notif_n03';
/** Iteraciones por proceso en cada escenario de concurrencia. */
const NT_CONC_ITER = 150;

// ── Proceso trabajador de las pruebas de concurrencia ───────────────────────────────────────────────────────────────

/**
 * Destinatarios y avisos de cada escenario. Cada iteracion es una "accion" de quien llama (tabla accion_conc), dentro de
 * una transaccion: INSERT parte 1, uno o dos opg_notificar, INSERT parte 2, COMMIT. Si MySQL revirtiera la transaccion
 * (interbloqueo), la parte 2 se confirmaria sola y el padre veria la accion a medias. El escenario agrupables_ac corre en
 * autocommit (ver la nota de la seccion 7).
 */
function nt_escenario_avisos($esc, $proc, $k)
{
    $a = $proc === 'A';
    switch ($esc) {
        case 'distintos': // usuarios distintos, sin contador previo, uno por iteracion y proceso
            return array(array(20000 + 2 * $k + ($a ? 0 : 1), 'aventura_denegada', array('clave' => "dist:$k")));
        case 'mismo': // el mismo usuario sin contador previo: un aviso propio y otro comun a los dos procesos
            return array(array(30000, 'aventura_denegada', array('clave' => "mismo:$proc:$k")),
                array(30000, 'aventura_aprobada', array('clave' => "mismo:comun:$k")));
        case 'solapados': // lotes que se solapan, pasados en ordenes opuestos; a menudo solo entra una parte
            return array(array($a ? range(40000, 40011) : array_reverse(range(40006, 40017)), 'aventura_denegada', array('clave' => "sol:$k")));
        case 'agrupables': // dos personas publican a la vez en el mismo tema: mismos destinatarios, sin contador previo
            return array(array($a ? range(50000, 50007) : array_reverse(range(50000, 50007)), 'bitacora_post', array('agrupar_por' => 9)));
        case 'agrupables_ac': // el mismo tema y, ademas, un tema propio para los mismos destinatarios (en autocommit)
            $todos = $a ? range(51000, 51007) : array_reverse(range(51000, 51007));
            return array(array($todos, 'bitacora_post', array('agrupar_por' => 9)), array($todos, 'bitacora_post', array('agrupar_por' => $a ? 10 : 11)));
    }
    return array();
}

if (isset($argv[1]) && $argv[1] === '--trabajador') {
    list(, , $esc, $proc, $barrera) = $argv;
    $GLOBALS['nt_base'] = NT_BASE;
    nt_estricto(false); // como MyBB con $hide_errors: los errores se ven en el resultado, no como excepciones
    $db = nt_conectar();
    nt_registro_iniciar();
    $tx = $esc !== 'agrupables_ac';
    while (!file_exists($barrera)) {
        usleep(200);
    }
    $nuevas = 0;
    $errores = array();
    for ($k = 1; $k <= NT_CONC_ITER; $k++) {
        $ok = (!$tx || $db->write_query('START TRANSACTION', 1) !== false)
            && $db->write_query("INSERT INTO accion_conc (esc, proc, k, parte) VALUES ('$esc', '$proc', $k, 1)", 1) !== false;
        foreach (nt_escenario_avisos($esc, $proc, $k) as $av) {
            $r = opg_notificar($av[0], $av[1], array('tid' => 9), $av[2] + array('actor' => 1));
            $r === false ? $errores[] = "k=$k opg_notificar false" : $nuevas += $r;
        }
        $ok = $ok && $db->write_query("INSERT INTO accion_conc (esc, proc, k, parte) VALUES ('$esc', '$proc', $k, 2)", 1) !== false;
        $ok = (!$tx || $db->write_query('COMMIT', 1) !== false) && $ok;
        if (!$ok) {
            $errores[] = "k=$k SQL " . $db->error_number() . ' ' . $db->error_string();
        }
    }
    echo json_encode(array('nuevas' => $nuevas, 'errores' => $errores, 'registro' => nt_registro_leer())), "\n";
    exit(0);
}

// ── Preparacion ─────────────────────────────────────────────────────────────────────────────────────────────────────

$db = nt_preparar(NT_BASE);
nt_registro_iniciar();
$N = '`mybb_op_notificaciones`';
$C = '`mybb_op_notificaciones_contador`';

function contador($uid)
{
    global $C;
    $f = nt_una("SELECT sin_leer, importantes FROM $C WHERE uid = " . (int) $uid);
    return $f ? array((int) $f['sin_leer'], (int) $f['importantes']) : null;
}
function filas($where)
{
    global $N;
    return (int) nt_valor("SELECT COUNT(*) FROM $N WHERE $where");
}
/** Lo que hara "marcar como vista" (issue #4): fecha de lectura, clave liberada en agrupables y contador -1. */
function marcar_vista($id)
{
    global $N, $C;
    $f = nt_una("SELECT uid, importancia FROM $N WHERE id = " . (int) $id);
    nt_sql("UPDATE $N SET leida_en = UNIX_TIMESTAMP(), clave_evento = IF(agrupable = 1, CONCAT(clave_evento, ':leida:', id), clave_evento)"
        . ' WHERE id = ' . (int) $id . ' AND leida_en IS NULL');
    nt_sql("UPDATE $C SET sin_leer = GREATEST(sin_leer - 1, 0), importantes = GREATEST(importantes - " . ($f['importancia'] === 'importante' ? 1 : 0)
        . ', 0) WHERE uid = ' . (int) $f['uid']);
}
function inserciones_desde($desde)
{
    global $db;
    return count(array_filter(array_slice($db->sentencias, $desde), function ($s) {
        return stripos($s, 'INSERT') === 0 && strpos($s, 'op_notificaciones` (') !== false;
    }));
}

// ── 1. Aviso normal, idempotencia y contador ────────────────────────────────────────────────────────────────────────

$r = opg_notificar(10, 'aventura_aprobada', array('peticion_id' => 4, 'actor' => 'Garp', 'tema' => 'La isla', 'isla' => 'Dawn', 'tier' => 2),
    array('clave' => 'aventura:4:aprobada', 'actor' => 3, 'url' => 'op/tablon_de_aventuras.php?id=4'));
nt_prueba('normal: devuelve 1', $r, 1);
$f = nt_una("SELECT * FROM $N WHERE uid = 10");
nt_ok($f && $f['tipo'] === 'aventura_aprobada' && $f['categoria'] === 'aventuras' && $f['origen'] === 'gestion' && $f['importancia'] === 'normal'
    && (int) $f['agrupable'] === 0 && (int) $f['actor_uid'] === 3 && $f['clave_evento'] === 'aventura:4:aprobada'
    && $f['url'] === 'op/tablon_de_aventuras.php?id=4' && (int) $f['cantidad'] === 1 && $f['leida_en'] === null
    && abs((int) $f['created_at'] - time()) < 5, 'normal: columnas copiadas del catalogo y de las opciones');
nt_prueba('normal: contador creado con el recuento (1, 0)', contador(10), array(1, 0));

nt_prueba('idempotencia: repetir devuelve 0', opg_notificar(10, 'aventura_aprobada', array(), array('clave' => 'aventura:4:aprobada', 'actor' => 3)), 0);
nt_prueba('idempotencia: sigue habiendo una fila', filas('uid = 10'), 1);
nt_prueba('idempotencia: el contador no sube', contador(10), array(1, 0));

nt_prueba('importante: devuelve 1', opg_notificar(10, 'aventura_denegada', array('motivo' => 'Falta el tier'), array('clave' => 'aventura:4:denegada')), 1);
nt_prueba('importante: sin_leer e importantes suben', contador(10), array(2, 1));

nt_prueba('actor: no se notifica a si mismo', opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'x:1', 'actor' => 3)), 0);
nt_prueba('actor: ninguna fila', filas('uid = 3'), 0);
nt_prueba('incluir_actor: si se notifica', opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'x:1', 'actor' => 3, 'incluir_actor' => true)), 1);
nt_prueba('silenciosa: no crea nada', opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'x:2', 'silenciosa' => true)), 0);
nt_prueba('silenciosa: ninguna fila', filas("uid = 3 AND clave_evento = 'x:2'"), 0);
nt_prueba('destinatario 0: devuelve 0', opg_notificar(array(0), 'aventura_aprobada', array(), array('clave' => 'x:3')), 0);

// El contador ausente se crea con el aviso nuevo (upsert relativo, 208 apartado 5.2 corregido): no recuenta filas que ya
// existieran sin contador (solo pasa con las migradas del Tablon, cuyo script reconcilia al terminar).
nt_sql("INSERT INTO $N (uid, tipo, categoria, origen, importancia, clave_evento, created_at) VALUES (20, 'aventura_denegada', 'aventuras', 'gestion', 'importante', 'm:1', 1)");
opg_notificar(20, 'aventura_aprobada', array(), array('clave' => 'm:3'));
nt_prueba('contador ausente: se crea con el aviso nuevo (1, 0)', contador(20), array(1, 0));
nt_prueba('contador ausente: actualizado = fecha de creacion', abs((int) nt_valor("SELECT actualizado FROM $C WHERE uid = 20") - time()) < 5, true);
// Los envios no tocan `actualizado` (es la fecha de creacion o de la ultima reconciliacion).
nt_sql("UPDATE $C SET actualizado = 12345 WHERE uid IN (10, 20)");
opg_notificar(array(10, 20), 'aventura_denegada', array(), array('clave' => 'm:4'));
nt_prueba('actualizado: el incremento no lo toca', array((int) nt_valor("SELECT actualizado FROM $C WHERE uid = 10"), (int) nt_valor("SELECT actualizado FROM $C WHERE uid = 20")), array(12345, 12345));
nt_prueba('actualizado: pero si suma', array(contador(10), contador(20)), array(array(3, 2), array(2, 1)));
nt_sql("DELETE FROM $N WHERE uid = 20 AND clave_evento = 'm:1'");

// D7: la clave no distingue mayusculas (colacion _ci) y la misma clave con otro tipo se ignora y se registra.
nt_registro_leer();
opg_notificar(21, 'aventura_aprobada', array(), array('clave' => 'Caso:1'));
nt_prueba('clave: otras mayusculas cuentan como la misma clave', opg_notificar(21, 'aventura_aprobada', array(), array('clave' => 'caso:1')), 0);
nt_ok(strpos(nt_registro_leer(), 'Caso:1') !== false, 'clave: el conflicto de mayusculas se registra');
nt_prueba('clave: la misma clave con otro tipo se ignora', opg_notificar(21, 'aventura_denegada', array(), array('clave' => 'Caso:1')), 0);
nt_ok(strpos(nt_registro_leer(), 'aventura_aprobada') !== false, 'clave: el conflicto de tipo se registra');
nt_prueba('clave: la fila original queda igual', nt_una("SELECT tipo, clave_evento FROM $N WHERE uid = 21"), array('tipo' => 'aventura_aprobada', 'clave_evento' => 'Caso:1'));
nt_prueba('clave: agrupable con la clave de otro tipo no la toca', opg_notificar(21, 'bitacora_post', array(), array('clave' => 'Caso:1')), 0);
nt_ok(strpos(nt_registro_leer(), 'otro tipo') !== false, 'clave: el conflicto del agrupable se registra');
nt_prueba('clave: la fila de otro tipo sigue con cantidad 1', (int) nt_valor("SELECT cantidad FROM $N WHERE uid = 21"), 1);
nt_prueba('clave: contador sin cambios', contador(21), array(1, 0));

// D5: INF o NAN se descartan y se registran; el aviso se envia con el resto de los datos.
nt_prueba('INF/NAN: el aviso se envia', opg_notificar(22, 'aventura_denegada', array('tier' => INF, 'dias' => NAN, 'actor' => 'Garp'), array('clave' => 'nan:1')), 1);
nt_prueba('INF/NAN: datos guardados sin los no finitos', nt_valor("SELECT datos_json FROM $N WHERE uid = 22"), '{"actor":"Garp"}');
nt_ok(strpos(nt_registro_leer(), 'no finito') !== false, 'INF/NAN: se registra');

// ── 2. Lotes ────────────────────────────────────────────────────────────────────────────────────────────────────────

$antes = count($db->sentencias);
nt_prueba('lote: tres nuevas', opg_notificar(array(13, 11, 12, 11), 'aventura_denegada', array('peticion_id' => 7), array('clave' => 'lote:1')), 3);
nt_prueba('lote: una sola insercion multifila', inserciones_desde($antes), 1);
nt_ok(contador(11) === array(1, 1) && contador(12) === array(1, 1) && contador(13) === array(1, 1), 'lote: cada contador +1');

// Lote parcial: 12 ya tiene la clave; 14 y 15 no tienen contador; 11 y 13 si.
opg_notificar(12, 'aventura_aprobada', array(), array('clave' => 'lote:2'));
$antes = count($db->sentencias);
nt_prueba('lote parcial: solo las nuevas', opg_notificar(array(11, 12, 13, 14, 15), 'aventura_aprobada', array(), array('clave' => 'lote:2')), 4);
nt_prueba('lote parcial: una sola insercion', inserciones_desde($antes), 1);
nt_prueba('lote parcial: el que ya la tenia no sube', contador(12), array(2, 1));
nt_ok(contador(11) === array(2, 1) && contador(13) === array(2, 1), 'lote parcial: los que tenian contador suben 1');
nt_ok(contador(14) === array(1, 0) && contador(15) === array(1, 0), 'lote parcial: los que no tenian contador se crean con 1');
// Lote parcial con la fila repetida en medio y al final.
opg_notificar(array(17, 19), 'aventura_aprobada', array(), array('clave' => 'lote:3'));
nt_prueba('lote parcial (huecos): nuevas', opg_notificar(array(16, 17, 18, 19), 'aventura_aprobada', array(), array('clave' => 'lote:3')), 2);
nt_ok(contador(16) === array(1, 0) && contador(17) === array(1, 0) && contador(18) === array(1, 0) && contador(19) === array(1, 0),
    'lote parcial (huecos): cada uno cuenta su unica fila');
nt_prueba('lote repetido entero: 0', opg_notificar(array(16, 17, 18, 19), 'aventura_aprobada', array(), array('clave' => 'lote:3')), 0);
nt_prueba('lote repetido entero: sin cambios', contador(18), array(1, 0));

// ── 3. Agrupables ───────────────────────────────────────────────────────────────────────────────────────────────────

// Prueba de instalacion (208, apartado 5.3): ON DUPLICATE KEY UPDATE da 1 al insertar y 2 al actualizar en esta conexion.
$odku = "INSERT INTO $N (uid, tipo, categoria, origen, agrupable, clave_evento, created_at) VALUES (99, 't', 'c', 'usuario', 1, 'odku:1', 1)"
    . ' ON DUPLICATE KEY UPDATE cantidad = cantidad + 1';
$db->write_query($odku, 1);
$a1 = $db->affected_rows();
$db->write_query($odku, 1);
nt_prueba('affected_rows de ON DUPLICATE KEY UPDATE: 1 y 2', array($a1, $db->affected_rows()), array(1, 2));
nt_sql("DELETE FROM $N WHERE uid = 99");

$r1 = opg_notificar(30, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7, 'actor' => 31));
nt_sql("UPDATE $N SET created_at = created_at - 1000 WHERE uid = 30");
$r2 = opg_notificar(30, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7, 'actor' => 32));
nt_sql("UPDATE $N SET created_at = created_at - 1000 WHERE uid = 30");
$r3 = opg_notificar(30, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7, 'actor' => 33));
nt_prueba('agrupable: 1 nueva y 2 sumadas', array($r1, $r2, $r3), array(1, 0, 0));
$f = nt_una("SELECT * FROM $N WHERE uid = 30");
nt_prueba('agrupable: una sola fila', filas('uid = 30'), 1);
nt_ok($f && (int) $f['cantidad'] === 3 && (int) $f['actor_uid'] === 33 && $f['clave_evento'] === 'bitacora_post:7' && (int) $f['agrupable'] === 1,
    'agrupable: cantidad 3 y el ultimo actor');
nt_ok($f && abs((int) $f['created_at'] - time()) < 5, 'agrupable: la fecha se renueva');
nt_prueba('agrupable: el contador sube una sola vez', contador(30), array(1, 0));
nt_prueba('agrupable: texto con la cantidad', opg_notif_texto('bitacora_post', json_decode($f['datos_json'], true), $f['cantidad']),
    '3 publicaciones nuevas en el tema #7.');

// Tras "Visto" la clave se libera y el siguiente evento crea una fila nueva.
marcar_vista((int) $f['id']);
nt_prueba('visto: contador a 0', contador(30), array(0, 0));
nt_prueba('agrupable tras visto: fila nueva', opg_notificar(30, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7, 'actor' => 34)), 1);
nt_prueba('agrupable tras visto: dos filas', filas('uid = 30'), 2);
nt_prueba('agrupable tras visto: la nueva tiene cantidad 1', (int) nt_valor("SELECT cantidad FROM $N WHERE uid = 30 AND leida_en IS NULL"), 1);
nt_prueba('agrupable tras visto: contador 1', contador(30), array(1, 0));

// Lote agrupable: 30 ya tiene una fila sin leer (suma), 35 y 36 son nuevas.
$antes = count($db->sentencias);
nt_prueba('lote agrupable: 2 nuevas', opg_notificar(array(30, 35, 36), 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7)), 2);
nt_prueba('lote agrupable: una sentencia atomica por destinatario', inserciones_desde($antes), 3);
nt_prueba('lote agrupable: 30 suma a su fila', (int) nt_valor("SELECT cantidad FROM $N WHERE uid = 30 AND leida_en IS NULL"), 2);
nt_ok(contador(30) === array(1, 0) && contador(35) === array(1, 0) && contador(36) === array(1, 0), 'lote agrupable: contador solo de las nuevas');
nt_prueba('lote agrupable repetido: 0 nuevas', opg_notificar(array(30, 35, 36), 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7)), 0);
nt_prueba('lote agrupable repetido: cantidad 2', (int) nt_valor("SELECT cantidad FROM $N WHERE uid = 35"), 2);

// ── 4. Datos y textos del catalogo ──────────────────────────────────────────────────────────────────────────────────

function texto_guardado($uid, $clave)
{
    global $N;
    $f = nt_una("SELECT tipo, datos_json, cantidad FROM $N WHERE uid = " . (int) $uid . " AND clave_evento = '" . $clave . "'");
    return $f ? opg_notif_texto($f['tipo'], json_decode((string) $f['datos_json'], true), $f['cantidad']) : null;
}
$casos = array(
    array('aventura_denegada', array('actor' => 'Sengoku', 'tema' => 'El "tesoro" & más', 'peticion_id' => 9, 'isla' => 'Loguetown', 'tier' => 3, 'motivo' => 'Sin narrador'),
        'Sengoku denegó la solicitud de «El &quot;tesoro&quot; &amp; más». El creador puede corregirla y reenviarla desde el tablón. (Loguetown · Tier 3) Motivo: Sin narrador'),
    array('propuesta_aceptada', array('peticion_id' => 9, 'narrador' => 'Kuzan'),
        'Se aceptó la propuesta de narrador de la aventura #9 (Kuzan). La solicitud pasa a revisión del staff.'),
    array('oferta_por_vencer', array('dias' => 2), 'Tu oferta como narrador vence en 2 días. Renuévala para seguir apareciendo.'),
    array('bitacora_narrador_asignado', array('tid' => 7, 'nombre' => 'Nami'), 'Nami fue establecido como narrador de el tema #7.'),
    array('aventura_aprobada', array(), 'El staff aprobó la solicitud de una aventura. Ya puede jugarse.'),
);
foreach ($casos as $i => $c) {
    opg_notificar(40, $c[0], $c[1], array('clave' => 'texto:' . $i));
    nt_prueba('texto del catalogo con los datos guardados: ' . $c[0], texto_guardado(40, 'texto:' . $i), $c[2]);
}

// Titulo de un tema del foro (requisito 50): sin tema_publico se guarda el tid; con tema_publico y tema visible, el titulo.
nt_sql("INSERT INTO mybb_threads (tid, subject, visible) VALUES (7, 'Rumbo a Grand Line', 1), (8, 'Sala del staff', 1), (9, 'Borrado', -1)");
opg_notificar(41, 'bitacora_ronda_iniciada', array('tid' => 8, 'tema' => 'Sala del staff', 'nombre' => 'Ace'), array('clave' => 'tema:1'));
nt_prueba('tema: sin tema_publico no se guarda el titulo', texto_guardado(41, 'tema:1'), 'Ace inició una ronda narrada en el tema #8.');
nt_prueba('tema: el titulo no queda en datos_json', strpos((string) nt_valor("SELECT datos_json FROM $N WHERE uid = 41 AND clave_evento = 'tema:1'"), 'staff'), false);
opg_notificar(41, 'bitacora_ronda_iniciada', array('tid' => 7, 'tema' => 'Rumbo a Grand Line', 'nombre' => 'Ace'), array('clave' => 'tema:2', 'tema_publico' => true));
nt_prueba('tema: publico y visible guarda el titulo', texto_guardado(41, 'tema:2'), 'Ace inició una ronda narrada en «Rumbo a Grand Line».');
opg_notificar(41, 'bitacora_ronda_iniciada', array('tid' => 9, 'tema' => 'Borrado', 'nombre' => 'Ace'), array('clave' => 'tema:3', 'tema_publico' => true));
nt_prueba('tema: borrado no guarda el titulo aunque se pida', texto_guardado(41, 'tema:3'), 'Ace inició una ronda narrada en el tema #9.');

// Sin HTML en datos_json.
opg_notificar(42, 'aventura_denegada', array('actor' => '<script>alert(1)</script>', 'motivo' => 'ok'), array('clave' => 'html:1'));
nt_prueba('datos: el HTML no se guarda', strpos((string) nt_valor("SELECT datos_json FROM $N WHERE uid = 42"), '<'), false);
nt_ok(strpos(nt_registro_leer(), 'HTML') !== false, 'datos: el descarte de HTML se registra');

// Enlaces: el ya escapado se guarda decodificado una vez; el externo se descarta y el aviso se crea igual.
opg_notificar(43, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7, 'url' => 'showthread.php?tid=7&amp;amp;action=newpost'));
$url = nt_valor("SELECT url FROM $N WHERE uid = 43");
nt_prueba('url: &amp;amp; se guarda decodificado', $url, 'showthread.php?tid=7&action=newpost');
nt_prueba('url: impresa una vez es correcta', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), 'showthread.php?tid=7&amp;action=newpost');
nt_prueba('url externa: el aviso se crea', opg_notificar(43, 'aventura_aprobada', array(), array('clave' => 'url:2', 'url' => 'https://evil.example/x')), 1);
nt_prueba('url externa: se guarda NULL', nt_valor("SELECT url FROM $N WHERE uid = 43 AND clave_evento = 'url:2'"), null);
nt_prueba('url javascript con entidades: se guarda NULL', (opg_notificar(43, 'aventura_aprobada', array(), array('clave' => 'url:3', 'url' => '&#106;avascript:alert(1)')) === 1)
    ? nt_valor("SELECT url FROM $N WHERE uid = 43 AND clave_evento = 'url:3'") : 'no se creo', null);
nt_registro_leer();

// Tipo desconocido.
nt_prueba('tipo desconocido: false', opg_notificar(44, 'no_existe', array(), array('clave' => 'z:1')), false);
nt_ok(strpos(nt_registro_leer(), 'no_existe') !== false, 'tipo desconocido: se registra');
nt_prueba('tipo desconocido: ninguna fila', filas('uid = 44'), 0);

// ── 5. Transacciones ────────────────────────────────────────────────────────────────────────────────────────────────

$db->write_query('START TRANSACTION');
nt_prueba('transaccion: el aviso se crea dentro', opg_notificar(50, 'aventura_denegada', array(), array('clave' => 'tx:1')), 1);
$otra = nt_conectar();
nt_prueba('transaccion: otra sesion no lo ve antes del commit', (int) nt_valor("SELECT COUNT(*) FROM $N WHERE uid = 50", $otra), 0);
$db->write_query('ROLLBACK');
nt_prueba('transaccion revertida: no queda la notificacion', filas('uid = 50'), 0);
nt_prueba('transaccion revertida: no queda el contador', contador(50), null);

opg_notificar(51, 'aventura_aprobada', array(), array('clave' => 'tx:0'));
$db->write_query('START TRANSACTION');
opg_notificar(51, 'aventura_denegada', array(), array('clave' => 'tx:2'));
$db->write_query('ROLLBACK');
nt_prueba('transaccion revertida: el contador existente vuelve a su valor', contador(51), array(1, 0));
$db->write_query('START TRANSACTION');
opg_notificar(51, 'aventura_denegada', array(), array('clave' => 'tx:3'));
$db->write_query('COMMIT');
nt_prueba('transaccion confirmada: queda la notificacion', filas('uid = 51'), 2);
nt_prueba('transaccion confirmada: contador al dia', contador(51), array(2, 1));
$otra->close();

// ── 6. Fallos de la base: no se propagan ────────────────────────────────────────────────────────────────────────────

nt_sql("CREATE TABLE accion (id INT PRIMARY KEY) ENGINE=InnoDB");
nt_sql("CREATE TRIGGER falla_insercion BEFORE INSERT ON $N FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'insercion bloqueada por la prueba'");
nt_registro_leer();
$db->write_query('START TRANSACTION');
$db->write_query('INSERT INTO accion VALUES (1)');
$r = opg_notificar(60, 'aventura_aprobada', array(), array('clave' => 'f:1'));
$db->write_query('INSERT INTO accion VALUES (2)');
$db->write_query('COMMIT');
nt_prueba('fallo de insercion: devuelve false', $r, false);
nt_ok(strpos(nt_registro_leer(), 'insercion bloqueada por la prueba') !== false, 'fallo de insercion: se registra el error de MySQL');
nt_prueba('fallo de insercion: la accion original se completa', (int) nt_valor('SELECT COUNT(*) FROM accion'), 2);
nt_prueba('fallo de insercion: sin contador', contador(60), null);

// Igual con mysqli en modo estricto (lanza excepciones, como PHP 8.1+): tampoco se propaga.
nt_estricto(true);
$excepcion = null;
try {
    $r = opg_notificar(61, 'aventura_aprobada', array(), array('clave' => 'f:2'));
} catch (Throwable $e) {
    $excepcion = get_class($e);
}
nt_estricto(false);
nt_prueba('modo estricto: no lanza', $excepcion, null);
nt_prueba('modo estricto: devuelve false', $r, false);
nt_ok(strpos(nt_registro_leer(), 'insercion bloqueada por la prueba') !== false, 'modo estricto: se registra');
nt_sql('DROP TRIGGER falla_insercion');

// Fallo en el contador: la notificacion queda (la reconciliacion lo corregira) y se devuelve false.
nt_sql("CREATE TRIGGER falla_contador BEFORE UPDATE ON $C FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contador bloqueado'");
nt_prueba('fallo del contador: false', opg_notificar(10, 'aventura_aprobada', array(), array('clave' => 'f:3')), false);
nt_ok(strpos(nt_registro_leer(), 'contador bloqueado') !== false, 'fallo del contador: se registra');
nt_sql('DROP TRIGGER falla_contador');

// Tablas ausentes (despliegue a medias): false, sin error visible ni registrado.
nt_sql("RENAME TABLE $C TO mybb_op_contador_aparte");
opg_notif_tablas_listas(true);
ob_start();
$r = opg_notificar(62, 'aventura_aprobada', array(), array('clave' => 'f:4'));
$salida = ob_get_clean();
nt_prueba('tablas ausentes: false', $r, false);
nt_prueba('tablas ausentes: sin salida', $salida, '');
nt_prueba('tablas ausentes: sin registro', nt_registro_leer(), '');
nt_prueba('tablas ausentes: nada insertado', filas('uid = 62'), 0);
nt_sql("RENAME TABLE mybb_op_contador_aparte TO $C");
opg_notif_tablas_listas(true);
nt_prueba('tablas de vuelta: funciona', opg_notificar(62, 'aventura_aprobada', array(), array('clave' => 'f:4')), 1);

// ── 7. Concurrencia: dos procesos reales, dentro de transacciones ───────────────────────────────────────────────────
//
// Escenarios (NT_CONC_ITER acciones por proceso): usuarios distintos sin contador previo, el mismo usuario sin contador
// previo, lotes parciales que se solapan pasados en ordenes opuestos, y agrupables del mismo tema. Se exige 0 errores,
// 0 interbloqueos, 0 acciones a medias, que cada fila nueva la cuente un solo proceso y 0 desfases.
//
// Nota (riesgo de InnoDB que queda fuera del contador): si dos transacciones crean a la vez la MISMA fila agrupable nueva
// y la que gano inserta despues, dentro de la misma transaccion, otra clave del mismo usuario que queda justo antes en el
// indice unico, InnoDB las interbloquea (la insercion choca con el bloqueo que espera la otra). Medido: 1 de cada 150
// acciones, solo en la primera. Por eso tema comun mas tema propio se prueba en autocommit (agrupables_ac).

nt_sql('CREATE TABLE accion_conc (esc VARCHAR(16) NOT NULL, proc CHAR(1) NOT NULL, k INT NOT NULL, parte TINYINT NOT NULL, PRIMARY KEY (esc, proc, k, parte)) ENGINE=InnoDB');

/** Lanza los dos trabajadores del escenario a la vez y devuelve sus resultados. */
function nt_concurrencia($esc)
{
    $barrera = tempnam(sys_get_temp_dir(), 'opg_nt_barrera_');
    unlink($barrera);
    $procesos = array();
    foreach (array('A', 'B') as $proc) {
        $tuberias = array();
        $p = proc_open(array(PHP_BINARY, __FILE__, '--trabajador', $esc, $proc, $barrera), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $tuberias);
        $procesos[$proc] = array($p, $tuberias);
    }
    usleep(300000);
    touch($barrera);
    $res = array();
    foreach ($procesos as $proc => $pr) {
        $salida = stream_get_contents($pr[1][1]);
        $errores = stream_get_contents($pr[1][2]);
        proc_close($pr[0]);
        $r = json_decode(trim($salida), true);
        $res[$proc] = is_array($r) ? $r : array('nuevas' => -1000, 'errores' => array("sin respuesta: $salida $errores"), 'registro' => '');
    }
    @unlink($barrera);
    return $res;
}

/** Compara cada contador del rango con el recuento real de sus filas no leidas. */
function nt_desfases($desde, $hasta)
{
    global $N, $C;
    $real = array();
    foreach (nt_filas_todas("SELECT uid, COUNT(*) AS n, SUM(importancia = 'importante') AS i FROM $N WHERE uid BETWEEN $desde AND $hasta AND leida_en IS NULL GROUP BY uid") as $f) {
        $real[(int) $f['uid']] = array((int) $f['n'], (int) $f['i']);
    }
    $cont = array();
    foreach (nt_filas_todas("SELECT uid, sin_leer, importantes FROM $C WHERE uid BETWEEN $desde AND $hasta") as $f) {
        $cont[(int) $f['uid']] = array((int) $f['sin_leer'], (int) $f['importantes']);
    }
    $mal = array();
    foreach ($real + $cont as $uid => $x) {
        $r = isset($real[$uid]) ? $real[$uid] : array(0, 0);
        $c = isset($cont[$uid]) ? $cont[$uid] : array(0, 0);
        if ($r !== $c) {
            $mal[] = "$uid: contador " . json_encode($c) . ' real ' . json_encode($r);
        }
    }
    return $mal;
}
function nt_filas_todas($sql)
{
    $r = nt_sql($sql);
    $out = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $out[] = $f;
    }
    return $out;
}

// Para el escenario de lotes parciales, algunos avisos ya existen antes (con su contador) para 40008 y 40014.
for ($k = 3; $k <= NT_CONC_ITER; $k += 3) {
    opg_notificar(array(40008, 40014), 'aventura_denegada', array('tid' => 9), array('clave' => "sol:$k", 'actor' => 1));
}
$previas_sol = filas('uid BETWEEN 40000 AND 40017');

$rangos = array('distintos' => array(20000, 29999), 'mismo' => array(30000, 30000), 'solapados' => array(40000, 40017), 'agrupables' => array(50000, 50007),
    'agrupables_ac' => array(51000, 51007));
foreach ($rangos as $esc => $rango) {
    $res = nt_concurrencia($esc);
    $errores = array_merge($res['A']['errores'], $res['B']['errores']);
    nt_prueba("concurrencia $esc: 0 errores e interbloqueos", array_slice($errores, 0, 3), array());
    nt_prueba("concurrencia $esc: nada registrado", trim($res['A']['registro'] . $res['B']['registro']), '');
    $medias = (int) nt_valor("SELECT COUNT(*) FROM (SELECT proc, k FROM accion_conc WHERE esc = '$esc' GROUP BY proc, k HAVING COUNT(*) <> 2) x");
    $completas = (int) nt_valor("SELECT COUNT(*) FROM (SELECT proc, k FROM accion_conc WHERE esc = '$esc' GROUP BY proc, k HAVING COUNT(*) = 2) x");
    nt_prueba("concurrencia $esc: 0 acciones a medias", $medias, 0);
    nt_prueba("concurrencia $esc: todas las acciones completas", $completas, 2 * NT_CONC_ITER);
    $filas = filas("uid BETWEEN {$rango[0]} AND {$rango[1]}") - ($esc === 'solapados' ? $previas_sol : 0);
    nt_prueba("concurrencia $esc: cada fila nueva la conto un solo proceso", $res['A']['nuevas'] + $res['B']['nuevas'], $filas);
    nt_prueba("concurrencia $esc: 0 desfases (sin_leer e importantes = COUNT real)", nt_desfases($rango[0], $rango[1]), array());
}
nt_prueba('concurrencia distintos: una fila y un contador por accion', array(filas('uid BETWEEN 20000 AND 29999'), (int) nt_valor("SELECT COUNT(*) FROM $C WHERE uid BETWEEN 20000 AND 29999")),
    array(2 * NT_CONC_ITER, 2 * NT_CONC_ITER));
nt_prueba('concurrencia mismo: avisos propios de los dos y comunes una vez', filas('uid = 30000'), 3 * NT_CONC_ITER);
nt_prueba('concurrencia mismo: contador exacto', contador(30000), array(3 * NT_CONC_ITER, 2 * NT_CONC_ITER));
nt_prueba('concurrencia solapados: una fila por destinatario y clave', filas("clave_evento LIKE 'sol:%'"), 18 * NT_CONC_ITER);
nt_prueba('concurrencia solapados: sin duplicados',
    (int) nt_valor("SELECT COUNT(*) FROM (SELECT uid, clave_evento FROM $N WHERE clave_evento LIKE 'sol:%' GROUP BY uid, clave_evento HAVING COUNT(*) > 1) d"), 0);
nt_prueba('concurrencia agrupables: tema comun, una fila por destinatario con la cantidad de los dos',
    nt_valor("SELECT CONCAT(COUNT(*), '/', MIN(cantidad), '/', MAX(cantidad)) FROM $N WHERE uid BETWEEN 50000 AND 50007 AND clave_evento = 'bitacora_post:9'"), '8/' . (2 * NT_CONC_ITER) . '/' . (2 * NT_CONC_ITER));
nt_prueba('concurrencia agrupables: contador 1 por destinatario', contador(50003), array(1, 0));
nt_prueba('concurrencia agrupables_ac: tema comun',
    nt_valor("SELECT CONCAT(COUNT(*), '/', MIN(cantidad), '/', MAX(cantidad)) FROM $N WHERE uid BETWEEN 51000 AND 51007 AND clave_evento = 'bitacora_post:9'"), '8/' . (2 * NT_CONC_ITER) . '/' . (2 * NT_CONC_ITER));
nt_prueba('concurrencia agrupables_ac: temas propios, una fila por destinatario',
    nt_valor("SELECT CONCAT(COUNT(*), '/', MIN(cantidad), '/', MAX(cantidad)) FROM $N WHERE uid BETWEEN 51000 AND 51007 AND clave_evento IN ('bitacora_post:10', 'bitacora_post:11')"), '16/' . NT_CONC_ITER . '/' . NT_CONC_ITER);
nt_prueba('concurrencia agrupables_ac: contador 3 por destinatario', contador(51003), array(3, 0));

// ── 8. Una transaccion abierta que crea un contador no bloquea los envios a otros usuarios (requisito 36) ──────────

$principal = $db;
$principal->write_query('START TRANSACTION');
nt_prueba('bloqueo: la transaccion abierta crea el contador de 60000', opg_notificar(60000, 'aventura_denegada', array(), array('clave' => 'bl:1')), 1);
$otra = nt_conectar();
$otra->write_query('SET SESSION innodb_lock_wait_timeout = 2');
$GLOBALS['db'] = $otra;
$t0 = microtime(true);
$r1 = opg_notificar(60001, 'aventura_denegada', array(), array('clave' => 'bl:2'));
$r2 = opg_notificar(array(59999, 60002, 60003), 'aventura_aprobada', array(), array('clave' => 'bl:3'));
$r3 = opg_notificar(60005, 'bitacora_post', array('tid' => 9), array('agrupar_por' => 9));
$espera = microtime(true) - $t0;
$GLOBALS['db'] = $principal;
nt_prueba('bloqueo: otra sesion envia a usuarios vecinos sin esperar', array($r1, $r2, $r3), array(1, 3, 1));
nt_ok($espera < 1.0, 'bloqueo: los envios de la otra sesion no esperan a la transaccion (' . round($espera * 1000) . ' ms)');
nt_prueba('bloqueo: nada registrado', nt_registro_leer(), '');
$principal->write_query('ROLLBACK');
$otra->close();

// ── 9. Transaccion revertida que creo el contador: no deja ni la notificacion ni el contador ──────────────────────

nt_prueba('revertida: no queda la notificacion', filas('uid = 60000'), 0);
nt_prueba('revertida: no queda el contador', contador(60000), null);
nt_prueba('revertida: lo enviado por la otra sesion se conserva', array(contador(60001), contador(59999), contador(60005)), array(array(1, 1), array(1, 0), array(1, 0)));
nt_prueba('revertida: sin desfases en el rango', nt_desfases(59990, 60010), array());

nt_borrar();
nt_resumen('envio_db');
