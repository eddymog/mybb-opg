<?php
/**
 * Integracion del Tablon de aventuras con el sistema global de notificaciones (issue #9; 208, apartado 9.1).
 *
 *   php tests/notificaciones/tablon_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n09, que crea con el
 * esquema del tablon mas docs/notificaciones_migration.sql y borra al terminar.
 *
 * Comprueba:
 *  - opg_av_notificar y opg_av_notificar_partes escriben en la tabla global con la clave de evento de siempre, los datos
 *    que lee el catalogo (peticion_id, tema, isla, tier, actor, narrador, motivo) y UNA sola sentencia de insercion por
 *    accion con todos los destinatarios; el contador global sube una vez por destinatario.
 *  - Contar, listar y marcar del tablon leen la tabla global (categoria aventuras y recompensas_entregadas) y bajan el
 *    contador global; no tocan las de otras categorias.
 *  - Sin la tabla global, los envoltorios siguen usando la tabla antigua.
 *  - Dos procesos con acciones cruzadas (los mismos destinatarios en distinto orden, cada envio dentro de la transaccion
 *    de su accion) no se interbloquean: todas las acciones se confirman y los contadores quedan exactos.
 */

putenv('OPG_TEST_AV_DB=opg_notif_n09');
if (!defined('IN_MYBB')) {
    define('IN_MYBB', 1);
}
require __DIR__ . '/../aventuras/bootstrap_db.php';

/** DbShim que guarda cada sentencia, para contar las inserciones de un envio. */
class DbShimRegistro extends DbShim
{
    public $sentencias = array();
    public function query($sql, $hide_errors = 0, $write = 0)
    {
        $this->sentencias[] = $sql;
        return parent::query($sql, $hide_errors, $write);
    }
}
$db = new DbShimRegistro('127.0.0.1', 33307, 'root', 'opgtest', 'opg_notif_n09');

require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_vistas.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

const TB_N = 60;

// ── Proceso trabajador de la prueba de concurrencia ─────────────────────────────────────────────────────────────────
if (isset($argv[1]) && $argv[1] === '--trabajador') {
    list(, , $peticion, $salida) = $argv;
    $av = opg_av_obtener((int) $peticion);
    while (microtime(true) < (float) $salida) {
        usleep(500);
    }
    $errores_conc = array();
    for ($i = 0; $i < TB_N; $i++) {
        opg_av_iniciar();
        opg_av_w('UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET comentario_staff = ' . opg_av_sql('i' . $i) . ' WHERE id = ' . (int) $peticion);
        $ok = opg_av_notificar_partes($av, 50, 'aventura_aprobada', 'aventura:' . (int) $peticion . ':prueba:' . $i, array());
        if (!$ok) {
            $errores_conc[] = $i;
        }
        opg_av_confirmar();
    }
    echo json_encode(array('fallos' => $errores_conc));
    exit(0);
}

function tb_sentencias_insercion($desde)
{
    global $db;
    $n = 0;
    foreach (array_slice($db->sentencias, $desde) as $s) {
        if (stripos($s, 'INSERT IGNORE INTO `mybb_op_notificaciones`') === 0) {
            $n++;
        }
    }
    return $n;
}
function tb_contador($uid)
{
    $f = una('SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = ' . (int) $uid);
    return $f ? (int) $f['sin_leer'] : 0;
}
function tb_datos($fila)
{
    return json_decode((string) $fila['datos_json'], true);
}

// ── Esquema y datos ──────────────────────────────────────────────────────────────────────────────────────────────────
preparar_esquema($db);
ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/notificaciones_migration.sql'));
$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(50), 'narra' => array());
foreach (array(3 => 'Eddy', 5 => 'Luffy', 9 => 'Nami', 11 => 'Usopp', 12 => 'Robin', 50 => 'Staff') as $u => $n) {
    crear_usuario($u, $n);
}
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (15, 'Blue', 0, '15', 1, 1), (40, 'Isla Alfa', 15, '15,40', 1, 2)");
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES (700, 40, 'La gran aventura', 3, 0, 1, " . time() . "), (701, 40, 'Tema oculto', 3, 0, 0, " . time() . ")");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
opg_notif_tablas_listas(true);
prueba('la tabla global esta disponible', opg_av_notif_global(), true);

// Aventura A: creador 3, narrador 5, jugadores 3, 9 y 11, tema visible 700.
$a = ins(3, 5, 0, array(3, 9, 11), array('aventura_url' => 'showthread.php?tid=700'));
$av_a = opg_av_obtener($a);

// ── 1. Partes: una sola insercion con todos, sin el actor, con los datos del catalogo ──────────────────────────────
$desde = count($db->sentencias);
prueba('notificar_partes devuelve true', opg_av_notificar_partes($av_a, 50, 'aventura_denegada', 'aventura:' . $a . ':estado:2:1', array('motivo' => 'Falta detalle')), true);
prueba('una sola sentencia de insercion para las cuatro partes', tb_sentencias_insercion($desde), 1);
$filas = array();
foreach (opg_av_todos("SELECT * FROM mybb_op_notificaciones WHERE clave_evento = 'aventura:$a:estado:2:1' ORDER BY uid") as $f) {
    $filas[(int) $f['uid']] = $f;
}
prueba('destinatarios: creador, narrador y jugadores, sin repetidos', array_keys($filas), array(3, 5, 9, 11));
$d = tb_datos($filas[9]);
prueba('datos_json con las claves del catalogo', $d, array('peticion_id' => $a, 'tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Staff', 'motivo' => 'Falta detalle'));
prueba('tipo y categoria', array($filas[9]['tipo'], $filas[9]['categoria'], $filas[9]['actor_uid']), array('aventura_denegada', 'aventuras', '50'));
prueba('texto del catalogo con los datos guardados', opg_notif_texto('aventura_denegada', $d, 1),
    'Staff denegó la solicitud de «La gran aventura». El creador puede corregirla y reenviarla desde el tablón. (Isla Alfa · Tier 3) Motivo: Falta detalle');
prueba('contador global +1 por destinatario', array(tb_contador(3), tb_contador(5), tb_contador(9), tb_contador(11)), array(1, 1, 1, 1));
prueba('la tabla antigua no recibe nada', (int) una('SELECT COUNT(*) AS n FROM mybb_op_aventuras_notificaciones')['n'], 0);

// Repetir la misma clave no duplica ni vuelve a contar; el actor no se avisa a si mismo.
opg_av_notificar_partes($av_a, 50, 'aventura_denegada', 'aventura:' . $a . ':estado:2:1', array('motivo' => 'Falta detalle'));
prueba('misma clave: sin duplicados ni recuento', array(count(opg_av_todos("SELECT id FROM mybb_op_notificaciones WHERE clave_evento = 'aventura:$a:estado:2:1'")), tb_contador(3)), array(4, 1));
opg_av_notificar_partes($av_a, 5, 'narrador_salio', 'aventura:' . $a . ':renuncia:1', array('motivo' => 'Viaje'));
prueba('el narrador que sale no se avisa a si mismo', array_map('intval', array_column(opg_av_todos("SELECT uid FROM mybb_op_notificaciones WHERE clave_evento = 'aventura:$a:renuncia:1' ORDER BY uid"), 'uid')), array(3, 9, 11));

// ── 2. Propuestas: candidatura real (narrador en los datos) y vencimiento con una sola llamada ───────────────────────
$b = ins(12, null, 4, array(12), array('aventura_url' => 'showthread.php?tid=701'));
$r = opg_av_propuesta_crear_candidatura($b, 9, 'Me apunto');
prueba('candidatura creada', !empty($r['ok']), true);
$cand = una("SELECT * FROM mybb_op_notificaciones WHERE uid = 12 AND tipo = 'candidatura_nueva'");
prueba('candidatura: clave de evento de siempre', $cand ? $cand['clave_evento'] : null, 'propuesta:' . $r['id'] . ':nueva');
prueba('candidatura: narrador y actor en los datos, sin el titulo del tema oculto', tb_datos($cand),
    array('peticion_id' => $b, 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Nami', 'narrador' => 'Nami', 'propuesta_id' => (int) $r['id']));
$desde = count($db->sentencias);
prueba('vencer una propuesta', opg_av_propuestas_vencer(opg_av_ahora() + 30 * 86400), 1);
prueba('vencida: una sola insercion para las dos partes', tb_sentencias_insercion($desde), 1);
prueba('vencida: avisa a quien la envio (el narrador) una vez', count(opg_av_todos("SELECT id FROM mybb_op_notificaciones WHERE tipo = 'propuesta_vencida' AND uid = 9")), 1);

// ── 3. Lectura desde la tabla global ────────────────────────────────────────────────────────────────────────────────
// Una de otra categoria (temas) para el usuario 3 y una recompensa del tablon.
opg_notificar(3, 'bitacora_post', array('tid' => 700), array('agrupar_por' => 700));
opg_av_notificar_partes($av_a, 50, 'recompensas_entregadas', 'entrega:77:entregada', array());
prueba('contar: aventuras y recompensas, sin las de temas', opg_av_notificaciones_contar(3), 3);
$lista = opg_av_notificaciones_listar(3);
prueba('listar: solo las del tablon, la mas reciente primero', array_column($lista, 'tipo'), array('recompensas_entregadas', 'narrador_salio', 'aventura_denegada'));
$den = $lista[2];
prueba('listar: forma de la vista', array($den['peticion_id'], $den['aventura'], $den['actor'], $den['leida'], $den['datos']['motivo']),
    array($a, array('tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3), 'Staff', false, 'Falta detalle'));
$html = opg_av_v_novedad_fila($den, true);
prueba('la vista redacta la novedad con los datos guardados', strpos($html, 'Staff denegó la solicitud de «La gran aventura»') !== false && strpos($html, 'Motivo: Falta detalle') !== false, true);
$cand_lista = opg_av_notificaciones_listar(12);
prueba('listar: el narrador de la propuesta', $cand_lista[0]['narrador_propuesta'], 'Nami');

prueba('contador global antes de leer', tb_contador(3), 4);
prueba('otro usuario no puede marcarla', opg_av_notificacion_leer($den['id'], 5)['codigo'], 404);
prueba('el destinatario la marca', opg_av_notificacion_leer($den['id'], 3), array('ok' => true));
prueba('marcar baja el contador del tablon y el global', array(opg_av_notificaciones_contar(3), tb_contador(3)), array(2, 3));
prueba('marcarla otra vez no descuenta', array(opg_av_notificacion_leer($den['id'], 3)['codigo'], tb_contador(3)), array(404, 3));
prueba('leer todas', opg_av_notificaciones_leer_todas(3), array('ok' => true));
prueba('leer todas: marca aventuras y recompensas y deja la de temas', array(opg_av_notificaciones_contar(3), tb_contador(3),
    (int) una("SELECT COUNT(*) AS n FROM mybb_op_notificaciones WHERE uid = 3 AND leida_en IS NULL AND categoria = 'temas'")['n']), array(0, 1, 1));
prueba('el contador global coincide con el recuento', opg_notif_reconciliar(3), array('sin_leer' => 1, 'importantes' => 0));

// ── 4. Respaldo: sin la tabla global, la tabla antigua ──────────────────────────────────────────────────────────────
sql('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_fuera');
opg_notif_tablas_listas(true);
prueba('sin tabla global: respaldo activo', opg_av_notif_global(), false);
opg_av_notificar_partes($av_a, 50, 'aventura_aprobada', 'aventura:' . $a . ':estado:1:9', array());
prueba('sin tabla global: escribe en la tabla antigua', (int) una("SELECT COUNT(*) AS n FROM mybb_op_aventuras_notificaciones WHERE clave_evento = 'aventura:$a:estado:1:9'")['n'], 4);
prueba('sin tabla global: contar y listar leen la tabla antigua', array(opg_av_notificaciones_contar(9), count(opg_av_notificaciones_listar(9))), array(1, 1));
$vieja = opg_av_notificaciones_listar(9);
prueba('sin tabla global: marcar en la tabla antigua', array(opg_av_notificacion_leer($vieja[0]['id'], 9), opg_av_notificaciones_contar(9)), array(array('ok' => true), 0));
sql('RENAME TABLE mybb_op_notificaciones_fuera TO mybb_op_notificaciones');
opg_notif_tablas_listas(true);

// ── 5. Concurrencia: dos procesos con acciones cruzadas ─────────────────────────────────────────────────────────────
// A: creador 3, narrador 5, jugadores 9 y 11. B: creador 11, narrador 9, jugadores 5 y 3. Mismos cuatro destinatarios.
sql('DELETE FROM mybb_op_notificaciones');
sql('DELETE FROM mybb_op_notificaciones_contador');
$pa = ins(3, 5, 0, array(9, 11));
$pb = ins(11, 9, 0, array(5, 3));
$salida = microtime(true) + 1.0;
$procs = array();
foreach (array($pa, $pb) as $p) {
    $h = proc_open(array(PHP_BINARY, __FILE__, '--trabajador', (string) $p, (string) $salida), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $tub);
    $procs[] = array($h, $tub);
}
$errores_conc = array();
foreach ($procs as $i => $pr) {
    $out = stream_get_contents($pr[1][1]);
    $err = stream_get_contents($pr[1][2]);
    proc_close($pr[0]);
    $res = json_decode($out, true);
    if (!is_array($res)) {
        $errores_conc[] = "proceso $i: " . trim($out . ' ' . $err);
        continue;
    }
    foreach ($res['fallos'] as $f) {
        $errores_conc[] = "proceso $i, iteracion $f";
    }
    if (trim($err) !== '') {
        $errores_conc[] = "proceso $i registro: " . substr(trim($err), 0, 300);
    }
}
prueba('concurrencia: 0 interbloqueos ni envios fallidos', $errores_conc, array());
prueba('concurrencia: todas las acciones confirmadas', array(fila($pa)['comentario_staff'], fila($pb)['comentario_staff']), array('i' . (TB_N - 1), 'i' . (TB_N - 1)));
prueba('concurrencia: todas las filas', (int) una('SELECT COUNT(*) AS n FROM mybb_op_notificaciones')['n'], 2 * TB_N * 4);
prueba('concurrencia: contadores exactos', array(tb_contador(3), tb_contador(5), tb_contador(9), tb_contador(11)), array_fill(0, 4, 2 * TB_N));

$db->query('DROP DATABASE IF EXISTS `opg_notif_n09`', 1);
resumen();
