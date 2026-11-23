<?php
/**
 * Lectura, marcado y reconciliacion con MySQL real (issue #4, docs/208 apartados 5.4, 5.5 y 13).
 *
 * Usa su propia base (opg_notif_n04) en el MySQL 5.7 de pruebas (ver tests/notificaciones/bootstrap_db.php): la crea con
 * docs/notificaciones_migration.sql y la borra al terminar. Cubre el contador (una consulta por clave primaria, cache,
 * autoreparacion de un desfase manual), la lista (orden, cursor sin huecos ni repetidos, filtros, indices sin filesort),
 * marcar como vista (dos veces descuenta una, ajena no cambia nada, clave liberada, contador en cero sin error), marcar
 * todas con filtro, reintentos reales ante espera agotada (1205) e interbloqueo (1213), y dos procesos reales a la vez:
 * marcar mientras otro agrupa y envia, dos marcando las mismas filas, reconciliar mientras otro envia y envios en
 * autocommit.
 *
 *   php tests/notificaciones/lectura_db.php
 *
 * (Con "--trabajador ESCENARIO PROC BARRERA" o "--bloqueo ..." el script hace de proceso concurrente.)
 */
require __DIR__ . '/bootstrap_db.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

const NT_BASE = 'opg_notif_n04';
/** Iteraciones por proceso en cada escenario de concurrencia. */
const NT_CONC_ITER = 150;

// ── Procesos auxiliares ─────────────────────────────────────────────────────────────────────────────────────────────

/** Usuarios de cada escenario de concurrencia. */
function nt_usuarios($esc)
{
    $base = array('agrupa_marca' => 70000, 'marca_doble' => 71000, 'reconcilia_envia' => 72000, 'autocommit' => 73000);
    return range($base[$esc], $base[$esc] + 3);
}

/**
 * Envio de una iteracion, para los cuatro usuarios: agrupables de dos temas distintos (uno de 5 a 7 y otro de 8 a 9) y un
 * aviso importante nuevo. Dos temas en la misma transaccion es el caso que todavia puede interbloquearse con un marcado
 * (el envio ya tiene bloqueada una clave y su contador cuando pide la otra): ahi deben actuar los reintentos.
 */
function nt_enviar($esc, $k, $tx)
{
    global $db;
    $errores = array();
    if ($tx && $db->write_query('START TRANSACTION', 1) === false) {
        $errores[] = "k=$k START " . $db->error_number();
    }
    $uids = nt_usuarios($esc);
    if (opg_notificar($uids, 'bitacora_post', array('tid' => 5 + $k % 3), array('agrupar_por' => 5 + $k % 3, 'actor' => 1)) === false) {
        $errores[] = "k=$k agrupable false";
    }
    if (opg_notificar($uids, 'bitacora_post', array('tid' => 8 + $k % 2), array('agrupar_por' => 8 + $k % 2, 'actor' => 1)) === false) {
        $errores[] = "k=$k agrupable2 false";
    }
    if (opg_notificar(array_reverse($uids), 'aventura_denegada', array('peticion_id' => $k), array('clave' => "$esc:$k", 'actor' => 1)) === false) {
        $errores[] = "k=$k normal false";
    }
    if ($tx && $db->write_query('COMMIT', 1) === false) {
        $errores[] = "k=$k COMMIT " . $db->error_number();
    }
    return $errores;
}

/**
 * Marcado de una iteracion: las dos no leidas mas recientes de cada usuario con "Visto" y, de vez en cuando, "marcar
 * todas" de una categoria o un origen y una reconciliacion. Devuelve las filas que paso a leidas y los errores.
 */
function nt_marcar($k)
{
    global $esc;
    $marcadas = 0;
    $errores = array();
    foreach (nt_usuarios($esc) as $uid) {
        $l = opg_notif_listar($uid, array('estado' => 'sin_leer'), null, 2);
        if ($l === false) {
            $errores[] = "k=$k listar false";
            continue;
        }
        foreach ($l['filas'] as $f) {
            $GLOBALS['nt_ops']++;
            if (opg_notif_marcar_vista($uid, $f['id'])) {
                $marcadas++;
            }
        }
        if ($k % 5 === 0) {
            $GLOBALS['nt_ops']++;
            $r = opg_notif_marcar_todas($uid, $k % 10 === 0 ? array('categoria' => 'aventuras') : array('origen' => 'usuario'));
            $r === false ? $errores[] = "k=$k todas false" : $marcadas += $r;
        }
        if ($k % 7 === 0 && ++$GLOBALS['nt_ops'] && opg_notif_reconciliar($uid) === false) {
            $errores[] = "k=$k reconciliar false";
        }
    }
    return array($marcadas, $errores);
}

if (isset($argv[1]) && $argv[1] === '--trabajador') {
    list(, , $esc, $proc, $barrera) = $argv;
    $GLOBALS['nt_base'] = NT_BASE;
    nt_estricto(false);
    $db = nt_conectar();
    nt_registro_iniciar();
    while (!file_exists($barrera)) {
        usleep(200);
    }
    $marcadas = 0;
    $errores = array();
    $GLOBALS['nt_ops'] = 0;
    // Las transacciones propias del envio (A en transaccion) no cuentan como reintentos.
    $propias = in_array($esc . ':' . $proc, array('agrupa_marca:A', 'reconcilia_envia:A'), true) ? NT_CONC_ITER : 0;
    for ($k = 1; $k <= NT_CONC_ITER; $k++) {
        switch ($esc . ':' . $proc) {
            case 'agrupa_marca:A':
            case 'reconcilia_envia:A':
                $errores = array_merge($errores, nt_enviar($esc, $k, true));
                break;
            case 'autocommit:A':
                $errores = array_merge($errores, nt_enviar($esc, $k, false));
                break;
            case 'agrupa_marca:B':
            case 'autocommit:B':
            case 'marca_doble:A':
            case 'marca_doble:B':
                list($m, $e) = nt_marcar($k);
                $marcadas += $m;
                $errores = array_merge($errores, $e);
                break;
            case 'reconcilia_envia:B':
                foreach (nt_usuarios($esc) as $uid) {
                    $GLOBALS['nt_ops']++;
                    if (opg_notif_reconciliar($uid) === false) {
                        $errores[] = "k=$k reconciliar false";
                    }
                }
                break;
        }
    }
    $intentos = count(array_filter($db->sentencias, function ($s) { return $s === 'START TRANSACTION'; }));
    $reintentos = $intentos - $propias - $GLOBALS['nt_ops'];
    echo json_encode(array('marcadas' => $marcadas, 'errores' => $errores, 'operaciones' => $GLOBALS['nt_ops'], 'reintentos' => $reintentos,
        'registro' => nt_registro_leer())), "\n";
    exit(0);
}

// Proceso que retiene bloqueos para las pruebas de reintento:
//   --bloqueo espera UID SEGUNDOS LISTO     bloquea el contador de UID, avisa y confirma tras SEGUNDOS
//   --bloqueo cruce UID ID LISTO            bloquea el contador de UID (transaccion pesada), avisa, espera y pide la fila ID
if (isset($argv[1]) && $argv[1] === '--bloqueo') {
    list(, , $modo, $uid, $x, $listo) = $argv;
    $GLOBALS['nt_base'] = NT_BASE;
    nt_estricto(false);
    $otra = nt_conectar();
    $otra->write_query('START TRANSACTION', 1);
    if ($modo === 'cruce') {
        // Transaccion mas pesada que la del marcado, para que InnoDB elija al marcado como victima del interbloqueo.
        for ($i = 0; $i < 40; $i++) {
            $otra->write_query('INSERT INTO relleno (v) VALUES (' . $i . ')', 1);
        }
    }
    $otra->write_query('UPDATE mybb_op_notificaciones_contador SET sin_leer = sin_leer WHERE uid = ' . (int) $uid, 1);
    // Una sola escritura (touch() crea el archivo y despues cambia su fecha: si el padre lo borra en medio, avisa).
    file_put_contents($listo, '1');
    $res = array();
    if ($modo === 'espera') {
        usleep((int) ((float) $x * 1000000));
    } else {
        usleep(500000);
        $ok = $otra->write_query('UPDATE mybb_op_notificaciones SET cantidad = cantidad WHERE id = ' . (int) $x, 1) !== false;
        $res['fila'] = $ok ? 'ok' : 'error ' . $otra->error_number();
        usleep(300000);
    }
    $res['commit'] = $otra->write_query('COMMIT', 1) !== false;
    echo json_encode($res), "\n";
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
function nt_filas_todas($sql)
{
    $r = nt_sql($sql);
    $out = array();
    while ($f = mysqli_fetch_assoc($r)) {
        $out[] = $f;
    }
    return $out;
}
/** Contadores del rango que no coinciden con el recuento real de sus filas no leidas. */
function nt_desfases($uids, $tolerar_exceso = false)
{
    global $N, $C;
    $lista = implode(',', array_map('intval', $uids));
    $real = array();
    foreach (nt_filas_todas("SELECT uid, COUNT(*) AS n, SUM(importancia = 'importante') AS i FROM $N WHERE uid IN ($lista) AND leida_en IS NULL GROUP BY uid") as $f) {
        $real[(int) $f['uid']] = array((int) $f['n'], (int) $f['i']);
    }
    $cont = array();
    foreach (nt_filas_todas("SELECT uid, sin_leer, importantes FROM $C WHERE uid IN ($lista)") as $f) {
        $cont[(int) $f['uid']] = array((int) $f['sin_leer'], (int) $f['importantes']);
    }
    $mal = array();
    foreach ($uids as $uid) {
        $r = isset($real[$uid]) ? $real[$uid] : array(0, 0);
        $c = isset($cont[$uid]) ? $cont[$uid] : array(0, 0);
        $malo = $tolerar_exceso ? ($c[0] < $r[0] || $c[1] < $r[1]) : $r !== $c;
        if ($malo) {
            $mal[] = "$uid: contador " . json_encode($c) . ' real ' . json_encode($r);
        }
    }
    return $mal;
}
function sentencias_desde($desde)
{
    global $db;
    return array_slice($db->sentencias, $desde);
}

// ── 1. Contador: una consulta por clave primaria, cache y autoreparacion ────────────────────────────────────────────

opg_notificar(10, 'aventura_aprobada', array(), array('clave' => 'c:1'));
opg_notificar(10, 'aventura_denegada', array(), array('clave' => 'c:2'));
opg_notificar(10, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7));
opg_notificar(10, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7));
opg_notif_contar_olvidar();
$antes = count($db->sentencias);
nt_prueba('contar: sin_leer e importantes', opg_notif_contar(10), array('sin_leer' => 3, 'importantes' => 1));
nt_prueba('contar: una sola consulta por clave primaria', sentencias_desde($antes), array("SELECT sin_leer, importantes, actualizado FROM $C WHERE uid = 10"));
$ex = nt_una('EXPLAIN ' . $db->sentencias[$antes]);
nt_prueba('contar: EXPLAIN usa la clave primaria (const)', array($ex['type'], $ex['key']), array('const', 'PRIMARY'));
opg_notif_contar(10);
nt_prueba('contar: la segunda llamada no consulta', count($db->sentencias), $antes + 1);

// Desfase manual con reconciliacion reciente: se mantiene hasta reconciliar (la cache tambien).
nt_sql("UPDATE $C SET sin_leer = 40, importantes = 9 WHERE uid = 10");
opg_notif_contar_olvidar();
nt_prueba('desfase reciente: contar lee la fila tal cual', opg_notif_contar(10), array('sin_leer' => 40, 'importantes' => 9));
nt_prueba('reconciliar: repara el desfase', opg_notif_reconciliar(10), array('sin_leer' => 3, 'importantes' => 1));
nt_prueba('reconciliar: fila corregida', contador(10), array(3, 1));
nt_ok(abs((int) nt_valor("SELECT actualizado FROM $C WHERE uid = 10") - time()) < 5, 'reconciliar: actualizado = ahora');
nt_prueba('reconciliar: deja el valor en la cache', opg_notif_contar(10), array('sin_leer' => 3, 'importantes' => 1));

// Desfase con la ultima reconciliacion de hace mas de 24 horas: contar lo repara solo.
nt_sql("UPDATE $C SET sin_leer = 0, importantes = 5, actualizado = UNIX_TIMESTAMP() - 90000 WHERE uid = 10");
opg_notif_contar_olvidar();
nt_prueba('autoreparacion: mas de 24 horas recuenta', opg_notif_contar(10), array('sin_leer' => 3, 'importantes' => 1));
nt_prueba('autoreparacion: fila reescrita', contador(10), array(3, 1));
nt_ok(abs((int) nt_valor("SELECT actualizado FROM $C WHERE uid = 10") - time()) < 5, 'autoreparacion: actualizado renovado');
opg_notif_contar_olvidar();
$antes = count($db->sentencias);
opg_notif_contar(10);
nt_prueba('autoreparacion: la siguiente peticion vuelve a una consulta', count($db->sentencias), $antes + 1);

// Sin fila de contador (por ejemplo filas copiadas del Tablon): contar la crea con el recuento.
nt_sql("INSERT INTO $N (uid, tipo, categoria, origen, importancia, clave_evento, created_at) VALUES (11, 'aventura_denegada', 'aventuras', 'gestion', 'importante', 'm:1', 1), "
    . "(11, 'aventura_aprobada', 'aventuras', 'gestion', 'normal', 'm:2', 2)");
nt_prueba('sin fila: contar recuenta', opg_notif_contar(11), array('sin_leer' => 2, 'importantes' => 1));
nt_prueba('sin fila: la crea', contador(11), array(2, 1));
nt_prueba('usuario sin nada: cero', opg_notif_contar(12), array('sin_leer' => 0, 'importantes' => 0));
nt_prueba('usuario sin nada: fila a cero', contador(12), array(0, 0));

// Los marcados y los envios no tocan `actualizado`.
nt_sql("UPDATE $C SET actualizado = 12345 WHERE uid = 11");
opg_notif_marcar_vista(11, (int) nt_valor("SELECT id FROM $N WHERE uid = 11 AND clave_evento = 'm:2'"));
opg_notif_marcar_todas(11);
nt_prueba('marcados: no tocan actualizado', (int) nt_valor("SELECT actualizado FROM $C WHERE uid = 11"), 12345);
nt_prueba('marcados: contador a cero', contador(11), array(0, 0));

// Tabla ausente: cero, lista vacia, sin registro ni salida.
nt_registro_leer();
nt_sql("RENAME TABLE $C TO mybb_op_contador_aparte, $N TO mybb_op_notif_aparte");
opg_notif_contar_olvidar();
ob_start();
$r = array(opg_notif_contar(10), opg_notif_listar(10), opg_notif_marcar_vista(10, 1), opg_notif_marcar_todas(10), opg_notif_reconciliar(10));
$salida = ob_get_clean();
nt_prueba('tablas ausentes: cero, lista vacia y false', $r, array(array('sin_leer' => 0, 'importantes' => 0), array('filas' => array(), 'hay_mas' => false, 'cursor' => null), false, false, false));
nt_prueba('tablas ausentes: sin salida ni registro', array($salida, nt_registro_leer()), array('', ''));
nt_sql("RENAME TABLE mybb_op_contador_aparte TO $C, mybb_op_notif_aparte TO $N");
opg_notif_contar_olvidar();

// ── 2. Marcar como vista ────────────────────────────────────────────────────────────────────────────────────────────

$id_imp = (int) nt_valor("SELECT id FROM $N WHERE uid = 10 AND clave_evento = 'c:2'");
$id_grupo = (int) nt_valor("SELECT id FROM $N WHERE uid = 10 AND clave_evento = 'bitacora_post:7'");
nt_prueba('vista: ajena devuelve false', opg_notif_marcar_vista(99, $id_imp), false);
nt_prueba('vista: ajena no marca la fila', nt_valor("SELECT leida_en FROM $N WHERE id = $id_imp"), null);
nt_prueba('vista: ajena no toca ningun contador', array(contador(10), contador(99)), array(array(3, 1), null));
nt_prueba('vista: la primera vez true', opg_notif_marcar_vista(10, $id_imp), true);
nt_prueba('vista: la segunda vez false', opg_notif_marcar_vista(10, $id_imp), false);
nt_prueba('vista: descuenta una sola vez (y de importantes)', contador(10), array(2, 0));
nt_ok(abs((int) nt_valor("SELECT leida_en FROM $N WHERE id = $id_imp") - time()) < 5, 'vista: guarda la fecha de lectura');
nt_prueba('vista: una normal conserva su clave', nt_valor("SELECT clave_evento FROM $N WHERE id = $id_imp"), 'c:2');
nt_prueba('vista: inexistente false', opg_notif_marcar_vista(10, 999999), false);

nt_prueba('vista agrupable: true', opg_notif_marcar_vista(10, $id_grupo), true);
nt_prueba('vista agrupable: clave liberada', nt_valor("SELECT clave_evento FROM $N WHERE id = $id_grupo"), "bitacora_post:7:leida:$id_grupo");
nt_prueba('vista agrupable: contador', contador(10), array(1, 0));
nt_prueba('vista agrupable: el siguiente evento crea una fila nueva', opg_notificar(10, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7)), 1);
nt_prueba('vista agrupable: la vista conserva su cantidad', (int) nt_valor("SELECT cantidad FROM $N WHERE id = $id_grupo"), 2);
nt_prueba('vista agrupable: contador +1', contador(10), array(2, 0));

// Clave de agrupable larga que no paso por opg_notificar (por ejemplo copiada): se recorta para que quepa el sufijo.
$larga = str_repeat('k', 128);
nt_sql("INSERT INTO $N (uid, tipo, categoria, origen, agrupable, clave_evento, created_at) VALUES (13, 'bitacora_post', 'temas', 'usuario', 1, '$larga', 1)");
$id_larga = (int) nt_valor("SELECT id FROM $N WHERE uid = 13");
opg_notif_reconciliar(13);
nt_prueba('vista clave larga: true sin error', opg_notif_marcar_vista(13, $id_larga), true);
nt_prueba('vista clave larga: recortada con el sufijo', nt_valor("SELECT clave_evento FROM $N WHERE id = $id_larga"), str_repeat('k', 111) . ":leida:$id_larga");

// Contador ya en cero (desfasado por debajo): no hay error de resta UNSIGNED y queda en cero.
opg_notificar(14, 'aventura_denegada', array(), array('clave' => 'z:1'));
opg_notificar(14, 'aventura_aprobada', array(), array('clave' => 'z:2'));
nt_sql("UPDATE $C SET sin_leer = 0, importantes = 0 WHERE uid = 14");
nt_registro_leer();
nt_prueba('contador en cero: marcar devuelve true', opg_notif_marcar_vista(14, (int) nt_valor("SELECT id FROM $N WHERE uid = 14 AND clave_evento = 'z:1'")), true);
nt_prueba('contador en cero: marcar todas devuelve 1', opg_notif_marcar_todas(14), 1);
nt_prueba('contador en cero: sigue en cero sin error', array(contador(14), nt_registro_leer()), array(array(0, 0), ''));

// Modo estricto de mysqli: mismo resultado, sin excepciones.
opg_notificar(15, 'aventura_denegada', array(), array('clave' => 'e:1'));
nt_estricto(true);
$excepcion = null;
try {
    $r = array(opg_notif_marcar_vista(15, (int) nt_valor("SELECT id FROM $N WHERE uid = 15")), opg_notif_marcar_vista(15, 1), opg_notif_contar(15));
} catch (Throwable $e) {
    $excepcion = get_class($e) . ' ' . $e->getMessage();
}
nt_estricto(false);
nt_prueba('modo estricto: sin excepciones', $excepcion, null);
nt_prueba('modo estricto: resultados', $r, array(true, false, array('sin_leer' => 0, 'importantes' => 0)));

// ── 3. Marcar todas con filtro ──────────────────────────────────────────────────────────────────────────────────────

opg_notificar(20, 'aventura_aprobada', array(), array('clave' => 't:1'));
opg_notificar(20, 'aventura_denegada', array(), array('clave' => 't:2'));
opg_notificar(20, 'aventura_denegada', array(), array('clave' => 't:3'));
opg_notificar(20, 'recompensas_entregadas', array(), array('clave' => 't:4'));
opg_notificar(20, 'bitacora_post', array('tid' => 3), array('agrupar_por' => 3));
opg_notificar(20, 'invitacion_nueva', array(), array('clave' => 't:5'));
opg_notificar(21, 'aventura_denegada', array(), array('clave' => 't:1'));
nt_prueba('todas: punto de partida', contador(20), array(6, 3));
nt_prueba('todas: filtro no valido no marca nada', array(opg_notif_marcar_todas(20, array('categoria' => 'nada')), opg_notif_marcar_todas(20, array('estado' => 'leidas')), contador(20)),
    array(false, false, array(6, 3)));
nt_prueba('todas: categoria aventuras marca 4', opg_notif_marcar_todas(20, array('categoria' => 'aventuras')), 4);
nt_prueba('todas: contador resta las 4 y las 3 importantes', contador(20), array(2, 0));
nt_prueba('todas: las de otras categorias siguen sin leer', filas("uid = 20 AND leida_en IS NULL AND categoria IN ('recompensas', 'temas')"), 2);
nt_prueba('todas: otro usuario intacto', array(contador(21), filas('uid = 21 AND leida_en IS NULL')), array(array(1, 1), 1));
nt_prueba('todas: repetir marca 0', opg_notif_marcar_todas(20, array('categoria' => 'aventuras')), 0);
nt_prueba('todas: origen usuario marca la agrupable', opg_notif_marcar_todas(20, array('origen' => 'usuario')), 1);
nt_prueba('todas: la agrupable libera su clave', filas("uid = 20 AND clave_evento LIKE 'bitacora_post:3:leida:%'"), 1);
nt_prueba('todas: sin filtro marca el resto', opg_notif_marcar_todas(20), 1);
nt_prueba('todas: contador a cero', contador(20), array(0, 0));
nt_prueba('todas: coincide con el recuento', nt_desfases(array(20, 21)), array());

// ── 4. Lista ────────────────────────────────────────────────────────────────────────────────────────────────────────

// 75 filas del usuario 30 con fechas repetidas (para probar el desempate por id) y ruido de otros usuarios.
$valores = array();
$cats = array(array('aventuras', 'gestion', 'aventura_aprobada', 'normal'), array('temas', 'usuario', 'bitacora_ronda_iniciada', 'normal'),
    array('recompensas', 'gestion', 'recompensas_entregadas', 'normal'), array('aventuras', 'usuario', 'invitacion_nueva', 'importante'));
for ($i = 1; $i <= 75; $i++) {
    $c = $cats[$i % 4];
    $leida = $i % 3 === 0 ? (1000 + $i) : 'NULL';
    $valores[] = "(30, '{$c[2]}', '{$c[0]}', '{$c[1]}', '{$c[3]}', 'l:$i', " . (1000 + intdiv($i, 4)) . ", $leida, '{\"peticion_id\":$i}')";
}
for ($u = 31; $u <= 60; $u++) {
    for ($i = 1; $i <= 20; $i++) {
        $valores[] = "($u, 'aventura_aprobada', 'aventuras', 'gestion', 'normal', 'r:$i', " . (900 + $i) . ', NULL, NULL)';
    }
}
nt_sql("INSERT INTO $N (uid, tipo, categoria, origen, importancia, clave_evento, created_at, leida_en, datos_json) VALUES " . implode(',', $valores));
nt_sql("ANALYZE TABLE $N");
opg_notif_reconciliar(30);
$esperado = array_map('intval', array_column(nt_filas_todas("SELECT id FROM $N WHERE uid = 30 ORDER BY created_at DESC, id DESC"), 'id'));

$vistas = array();
$cursor = null;
$paginas = 0;
$antes = count($db->sentencias);
do {
    $r = opg_notif_listar(30, array(), $cursor);
    $paginas++;
    foreach ($r['filas'] as $f) {
        $vistas[] = $f['id'];
    }
    $cursor = $r['cursor'];
} while ($r['hay_mas'] && $paginas < 10);
nt_prueba('lista: tres paginas (30, 30 y 15)', $paginas, 3);
nt_prueba('lista: una consulta por pagina', count($db->sentencias) - $antes, 3);
nt_prueba('lista: recorre todas en orden created_at DESC, id DESC sin huecos ni repetidos', $vistas, $esperado);
$p1 = opg_notif_listar(30);
nt_ok(count($p1['filas']) === 30 && $p1['hay_mas'] && preg_match('/^\d+_\d+$/', $p1['cursor']), 'lista: 30 por pagina con cursor');
$f0 = $p1['filas'][0];
nt_ok($f0['datos'] === array('peticion_id' => 75) && is_int($f0['id']) && is_int($f0['created_at']), 'lista: datos decodificados');
// Un aviso nuevo mientras se pagina no desplaza la pagina siguiente.
opg_notificar(30, 'aventura_aprobada', array(), array('clave' => 'l:nueva'));
$p2 = opg_notif_listar(30, array(), $p1['cursor']);
nt_prueba('lista: un aviso nuevo no mueve la pagina siguiente', array_column($p2['filas'], 'id'), array_slice($esperado, 30, 30));
nt_sql("DELETE FROM $N WHERE uid = 30 AND clave_evento = 'l:nueva'");
opg_notif_reconciliar(30);

$sin = opg_notif_listar(30, array('estado' => 'sin_leer'), null, 100);
nt_prueba('lista sin_leer: solo no leidas', array(count($sin['filas']), count(array_filter($sin['filas'], function ($f) { return $f['leida']; }))), array(50, 0));
$cat = opg_notif_listar(30, array('categoria' => 'temas'), null, 100);
nt_prueba('lista categoria: solo temas', array_unique(array_column($cat['filas'], 'categoria')), array('temas'));
nt_prueba('lista categoria: todas las de temas', count($cat['filas']), filas("uid = 30 AND categoria = 'temas'"));
$ori = opg_notif_listar(30, array('origen' => 'usuario', 'importancia' => 'importante', 'estado' => 'sin_leer'), null, 100);
nt_prueba('lista origen + importantes no leidas', count($ori['filas']), filas("uid = 30 AND origen = 'usuario' AND importancia = 'importante' AND leida_en IS NULL"));
$leidas = opg_notif_listar(30, array('estado' => 'leidas'), null, 100);
nt_prueba('lista leidas: historial', count($leidas['filas']), 25);
nt_prueba('lista: otro usuario no ve filas ajenas', opg_notif_listar(31, array('categoria' => 'temas')), array('filas' => array(), 'hay_mas' => false, 'cursor' => null));
nt_prueba('lista: listar no cambia el contador', contador(30), array(50, filas("uid = 30 AND leida_en IS NULL AND importancia = 'importante'")));

// Una sola consulta indexada: EXPLAIN de cada forma de la lista (con y sin cursor) usa un indice de uid y no ordena aparte.
$formas = array(
    'todas' => array(array(), null),
    'categoria' => array(array('categoria' => 'temas'), null),
    'origen' => array(array('origen' => 'usuario'), null),
    'sin_leer' => array(array('estado' => 'sin_leer'), null),
    'todas con cursor' => array(array(), '1010_40'),
    'categoria con cursor' => array(array('categoria' => 'aventuras'), '1010_40'),
);
foreach ($formas as $nombre => $forma) {
    $antes = count($db->sentencias);
    opg_notif_listar(30, $forma[0], $forma[1]);
    $sql = $db->sentencias[$antes];
    $ex = nt_filas_todas('EXPLAIN ' . $sql);
    nt_prueba("lista $nombre: una sola tabla en el plan", count($ex), 1);
    nt_ok(in_array($ex[0]['type'], array('ref', 'range'), true) && strpos((string) $ex[0]['key'], 'idx_uid') === 0,
        "lista $nombre: usa un indice de uid ({$ex[0]['type']} {$ex[0]['key']})");
    nt_ok(strpos((string) $ex[0]['Extra'], 'filesort') === false, "lista $nombre: sin filesort ({$ex[0]['Extra']})");
}

// ── 5. Reintentos reales ────────────────────────────────────────────────────────────────────────────────────────────

nt_sql('CREATE TABLE relleno (id INT AUTO_INCREMENT PRIMARY KEY, v INT) ENGINE=InnoDB');

/** Lanza el proceso de bloqueo y espera a que tenga el bloqueo. */
function nt_bloqueo($modo, $uid, $x)
{
    $listo = tempnam(sys_get_temp_dir(), 'opg_nt_listo_');
    unlink($listo);
    $tuberias = array();
    $p = proc_open(array(PHP_BINARY, __FILE__, '--bloqueo', $modo, (string) $uid, (string) $x, $listo), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $tuberias);
    $t0 = microtime(true);
    while (!file_exists($listo) && microtime(true) - $t0 < 10) {
        usleep(1000);
    }
    return array($p, $tuberias, $listo);
}
function nt_bloqueo_fin($b)
{
    $salida = stream_get_contents($b[1][1]);
    $errores = stream_get_contents($b[1][2]);
    $codigo = proc_close($b[0]);
    @unlink($b[2]);
    $r = json_decode(trim($salida), true);
    return is_array($r) ? $r : array('sin respuesta' => "codigo $codigo, salida: $salida, errores: $errores");
}
function nt_transacciones_desde($desde)
{
    return count(array_filter(sentencias_desde($desde), function ($s) { return $s === 'START TRANSACTION'; }));
}

opg_notificar(80, 'aventura_denegada', array(), array('clave' => 'b:1'));
opg_notificar(80, 'aventura_aprobada', array(), array('clave' => 'b:2'));
opg_notificar(80, 'aventura_aprobada', array(), array('clave' => 'b:3'));
$id_b1 = (int) nt_valor("SELECT id FROM $N WHERE uid = 80 AND clave_evento = 'b:1'");
$id_b2 = (int) nt_valor("SELECT id FROM $N WHERE uid = 80 AND clave_evento = 'b:2'");
$id_b3 = (int) nt_valor("SELECT id FROM $N WHERE uid = 80 AND clave_evento = 'b:3'");
$db->write_query('SET SESSION innodb_lock_wait_timeout = 1');

// innodb_lock_wait_timeout = 1 vence entre 1 y 2 segundos en MySQL 5.7 (lo revisa un hilo una vez por segundo), por eso
// los bloqueos de estas pruebas duran 8 segundos (mas que tres esperas) o 2,5 (mas que una y menos que dos completas).

// 5.1 Espera agotada (1205) en los tres intentos: false, nada cambia y queda registrado.
nt_registro_leer();
$b = nt_bloqueo('espera', 80, 8);
$antes = count($db->sentencias);
$t0 = microtime(true);
$r = opg_notif_marcar_vista(80, $id_b1);
$dt = microtime(true) - $t0;
nt_bloqueo_fin($b);
nt_prueba('1205 agotado: false', $r, false);
nt_prueba('1205 agotado: tres intentos', nt_transacciones_desde($antes), 3);
nt_ok($dt >= 2.9, '1205 agotado: espero en cada intento (' . round($dt, 1) . ' s)');
nt_prueba('1205 agotado: la fila sigue sin leer', nt_valor("SELECT leida_en FROM $N WHERE id = $id_b1"), null);
nt_prueba('1205 agotado: contador intacto', contador(80), array(3, 1));
nt_ok(strpos(nt_registro_leer(), '1205') !== false, '1205 agotado: se registra');

// 5.2 Espera agotada al menos una vez y despues el bloqueo se libera: un reintento termina bien.
$b = nt_bloqueo('espera', 80, 2.5);
$antes = count($db->sentencias);
$r = opg_notif_marcar_vista(80, $id_b1);
$fin = nt_bloqueo_fin($b);
nt_prueba('1205 y reintento: true', $r, true);
$n = nt_transacciones_desde($antes);
nt_ok($n === 2 || $n === 3, "1205 y reintento: reintento tras la espera agotada ($n intentos)");
nt_prueba('1205 y reintento: el otro proceso confirmo', $fin, array('commit' => true));
nt_prueba('1205 y reintento: contador exacto', contador(80), array(2, 0));
nt_prueba('1205 y reintento: nada registrado', nt_registro_leer(), '');

// 5.3 Interbloqueo real (1213): el otro proceso tiene el contador y pide la fila que el marcado acaba de bloquear.
$db->write_query('SET SESSION innodb_lock_wait_timeout = 10');
$b = nt_bloqueo('cruce', 80, $id_b2);
$antes = count($db->sentencias);
$r = opg_notif_marcar_vista(80, $id_b2);
$fin = nt_bloqueo_fin($b);
nt_prueba('1213: el otro proceso obtuvo la fila (el marcado fue la victima)', $fin, array('fila' => 'ok', 'commit' => true));
nt_prueba('1213: el marcado reintenta y devuelve true', array($r, nt_transacciones_desde($antes)), array(true, 2));
nt_prueba('1213: contador exacto', contador(80), array(1, 0));
nt_prueba('1213: nada registrado', nt_registro_leer(), '');

// 5.4 Interbloqueo en marcar todas y en reconciliar (mismo cruce).
$b = nt_bloqueo('cruce', 80, $id_b3);
$antes = count($db->sentencias);
$r = opg_notif_marcar_todas(80);
$fin = nt_bloqueo_fin($b);
nt_prueba('1213 en marcar todas: reintenta y marca 1', array($r, nt_transacciones_desde($antes) >= 2, $fin['commit']), array(1, true, true));
nt_prueba('1213 en marcar todas: contador exacto', array(contador(80), nt_desfases(array(80))), array(array(0, 0), array()));
$db->write_query('SET SESSION innodb_lock_wait_timeout = 50');

// 5.5 La reconciliacion no pisa un incremento: un envio dentro de una transaccion abierta en otra sesion.
$principal = $db;
$otra = nt_conectar();
$GLOBALS['db'] = $otra;
$otra->write_query('START TRANSACTION');
opg_notificar(81, 'aventura_denegada', array(), array('clave' => 'x:1'));
$GLOBALS['db'] = $principal;
$db->write_query('SET SESSION innodb_lock_wait_timeout = 1');
nt_prueba('reconciliar con un envio sin confirmar: espera al bloqueo, falla tras los reintentos y no escribe', opg_notif_reconciliar(81), false);
nt_registro_leer();
$db->write_query('SET SESSION innodb_lock_wait_timeout = 50');
$otra->write_query('COMMIT');
nt_prueba('reconciliar con un envio sin confirmar: el incremento sigue', contador(81), array(1, 1));
$otra->close();

// ── 6. Concurrencia: dos procesos reales ────────────────────────────────────────────────────────────────────────────
//
// agrupa_marca      A envia en transacciones (agrupables de dos temas y un importante nuevo, a 4 usuarios); B marca
//                   las mas recientes con Visto, marca todas por categoria u origen y reconcilia.
// marca_doble       A y B marcan las mismas filas a la vez (Visto y marcar todas).
// reconcilia_envia  A envia en transacciones; B reconcilia sin parar.
// autocommit        A envia sin transaccion (fila y contador en dos sentencias); B marca. Aqui el contador puede quedar
//                   por encima (nunca por debajo) hasta reconciliar: ver opg_notif_reconciliar.

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
        $res[$proc] = is_array($r) ? $r : array('marcadas' => -1000, 'errores' => array("sin respuesta: $salida $errores"), 'operaciones' => 0, 'reintentos' => 0, 'registro' => '');
    }
    @unlink($barrera);
    return $res;
}

// Filas previas para marca_doble: 60 no leidas por usuario, con su contador.
foreach (nt_usuarios('marca_doble') as $uid) {
    for ($k = 1; $k <= 30; $k++) {
        opg_notificar($uid, $k % 2 ? 'aventura_denegada' : 'invitacion_nueva', array(), array('clave' => "md:$k"));
        opg_notificar($uid, 'bitacora_ronda_iniciada', array(), array('clave' => "md2:$k"));
    }
}

$informe = array();
foreach (array('agrupa_marca', 'marca_doble', 'reconcilia_envia', 'autocommit') as $esc) {
    $uids = nt_usuarios($esc);
    $lista = implode(',', $uids);
    $res = nt_concurrencia($esc);
    // El envio nunca falla. El marcado puede, muy rara vez, agotar sus 2 reintentos (tres interbloqueos seguidos con un
    // envio en bucle sobre los mismos usuarios): entonces no cambia nada, lo registra y los contadores siguen exactos.
    $marcadores = $esc === 'marca_doble' ? array('A', 'B') : array('B');
    if ($esc !== 'marca_doble') {
        nt_prueba("concurrencia $esc: el envio no falla ni registra nada", array(array_slice($res['A']['errores'], 0, 3), trim($res['A']['registro'])), array(array(), ''));
    }
    $agotados = 0;
    $otros = array();
    $fallos_marcado = 0;
    foreach ($marcadores as $p) {
        $fallos_marcado += count($res[$p]['errores']);
        foreach (array_filter(explode("\n", $res[$p]['registro'])) as $linea) {
            if (strpos($linea, 'tras 2 reintentos') !== false && strpos($linea, 'no se cambio nada') !== false) {
                $agotados++;
            } else {
                $otros[] = $linea;
            }
        }
    }
    nt_prueba("concurrencia $esc: el marcado solo registra reintentos agotados", array_slice($otros, 0, 3), array());
    nt_ok($fallos_marcado <= $agotados, "concurrencia $esc: cada fallo del marcado es un reintento agotado ($fallos_marcado fallos, $agotados agotados)");
    $leidas = filas("uid IN ($lista) AND leida_en IS NOT NULL");
    if ($esc !== 'reconcilia_envia') {
        nt_prueba("concurrencia $esc: cada fila leida la marco un solo proceso una sola vez", $res['A']['marcadas'] + $res['B']['marcadas'], $leidas);
    }
    if ($esc === 'autocommit') {
        nt_prueba("concurrencia $esc: nunca por debajo del real ni negativo", nt_desfases($uids, true), array());
        $exceso = count(nt_desfases($uids));
        foreach ($uids as $uid) {
            opg_notif_reconciliar($uid);
        }
        nt_prueba("concurrencia $esc: tras reconciliar, exacto", nt_desfases($uids), array());
    } else {
        $exceso = 0;
        nt_prueba("concurrencia $esc: contadores exactos (sin_leer e importantes = COUNT real)", nt_desfases($uids), array());
    }
    nt_prueba("concurrencia $esc: las agrupables leidas liberaron su clave",
        filas("uid IN ($lista) AND agrupable = 1 AND leida_en IS NOT NULL AND clave_evento NOT LIKE '%:leida:%'"), 0);
    nt_prueba("concurrencia $esc: como mucho una agrupable sin leer por tema y usuario",
        (int) nt_valor("SELECT COUNT(*) FROM (SELECT uid, clave_evento FROM $N WHERE uid IN ($lista) AND leida_en IS NULL GROUP BY uid, clave_evento HAVING COUNT(*) > 1) d"), 0);
    nt_ok($res['A']['reintentos'] >= 0 && $res['B']['reintentos'] >= 0, "concurrencia $esc: cuenta de reintentos coherente");
    $informe[] = "$esc: leidas $leidas, operaciones de lectura A " . $res['A']['operaciones'] . ' B ' . $res['B']['operaciones']
        . ', reintentos A ' . $res['A']['reintentos'] . ' B ' . $res['B']['reintentos'] . ", reintentos agotados $agotados"
        . ($esc === 'autocommit' ? ", contadores por encima antes de reconciliar: $exceso" : '');
}
nt_prueba('concurrencia agrupa_marca: hubo avisos y marcados de verdad', filas('uid IN (' . implode(',', nt_usuarios('agrupa_marca')) . ') AND leida_en IS NOT NULL') > 100, true);
nt_prueba('concurrencia marca_doble: todo quedo leido o casi', filas('uid IN (' . implode(',', nt_usuarios('marca_doble')) . ') AND leida_en IS NULL'), 0);

nt_borrar();
echo '  (' . implode('; ', $informe) . ")\n";
nt_resumen('lectura_db');
