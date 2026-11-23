<?php
/**
 * Migracion de las notificaciones antiguas del Tablon al sistema global (issue #10; lib/notificaciones_migracion.php).
 *
 *   php tests/notificaciones/migracion_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n10, que crea con el
 * esquema del tablon mas docs/notificaciones_migration.sql y borra al terminar.
 *
 * Comprueba:
 *  - La simulacion cuenta lo que se copiaria y no escribe nada.
 *  - La copia por lotes de 500: conserva clave, fecha, actor y lectura (read_at a leida_en), toma categoria, origen e
 *    importancia del catalogo (recompensas_entregadas en 'recompensas'; un tipo desconocido en 'aventuras'), reconstruye
 *    datos_json como el envio del Tablon (peticion_id, tema visible, isla, tier, actor, narrador y los datos antiguos),
 *    respeta la fila que el sistema global ya tenia con la misma clave y omite las filas sin usuario.
 *  - Relanzarla no duplica nada (0 copiadas, todas "ya existian").
 *  - La reconciliacion deja el contador de cada usuario igual al recuento real de no leidas (y de importantes).
 *  - La tabla antigua queda intacta, y la consulta de reversion borra exactamente lo copiado.
 */

putenv('OPG_TEST_AV_DB=opg_notif_n10');
if (!defined('IN_MYBB')) {
    define('IN_MYBB', 1);
}
require __DIR__ . '/../aventuras/bootstrap_db.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_migracion.php';

const MG_BASE = 'opg_notif_n10';
const MG_MASIVOS = 1100; // filas de relleno: fuerzan tres lotes de 500

function mg_n($q)
{
    return (int) una($q)['n'];
}
function mg_vieja($clave, $peticion, $uid, $actor, $tipo, $datos, $creada, $leida = null)
{
    global $db;
    sql('INSERT INTO mybb_op_aventuras_notificaciones (clave_evento, peticion_id, uid, actor_uid, tipo, datos_json, created_at, read_at, attempts) VALUES ('
        . "'" . $db->escape_string($clave) . "', " . (int) $peticion . ', ' . (int) $uid . ', ' . (int) $actor . ", '" . $db->escape_string($tipo) . "', "
        . ($datos === null ? 'NULL' : "'" . $db->escape_string($datos) . "'") . ', ' . (int) $creada . ', ' . ($leida === null ? 'NULL' : (int) $leida) . ', 0)');
}
function mg_global($uid, $clave)
{
    global $db;
    return una('SELECT * FROM mybb_op_notificaciones WHERE uid = ' . (int) $uid . " AND clave_evento = '" . $db->escape_string($clave) . "'");
}
function mg_recorrer($simular = false)
{
    $total = array('leidas' => 0, 'copiadas' => 0, 'existian' => 0, 'omitidas' => 0, 'lotes' => 0);
    $desde = 0;
    do {
        $r = opg_notif_mig_lote($desde, OPG_NOTIF_MIG_LOTE, $simular);
        $total['lotes']++;
        foreach (array('leidas', 'copiadas', 'existian', 'omitidas') as $k) {
            $total[$k] += $r[$k];
        }
        $desde = $r['ultimo_id'];
    } while (!$r['fin']);
    return $total;
}
function mg_reconciliar_todo()
{
    $total = 0;
    $desde = 0;
    do {
        $r = opg_notif_mig_reconciliar($desde, 50);
        $total += $r['reconciliados'];
        prueba('reconciliar sin fallos', $r['fallidos'], array());
        $desde = $r['ultimo_uid'];
    } while (!$r['fin']);
    return $total;
}
/** Contadores que no coinciden con el recuento real de no leidas e importantes. */
function mg_contadores_incorrectos()
{
    return opg_av_todos('SELECT x.uid, x.n, x.i, c.sin_leer, c.importantes FROM (SELECT uid, COUNT(*) AS n, SUM(importancia = \'importante\') AS i'
        . ' FROM mybb_op_notificaciones WHERE leida_en IS NULL GROUP BY uid) x LEFT JOIN mybb_op_notificaciones_contador c ON c.uid = x.uid'
        . ' WHERE c.uid IS NULL OR c.sin_leer <> x.n OR c.importantes <> x.i');
}

// ── Esquema y datos ──────────────────────────────────────────────────────────────────────────────────────────────────
// Desde cero, aunque una ejecucion anterior se cortara sin borrar la base.
$db->query('DROP TABLE IF EXISTS mybb_op_notificaciones, mybb_op_notificaciones_contador', 1);
preparar_esquema($db);
ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/notificaciones_migration.sql'));
foreach (array(3 => 'Eddy', 5 => 'Luffy', 9 => 'Nami', 11 => 'Usopp', 50 => 'Staff') as $u => $n) {
    crear_usuario($u, $n);
}
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (40, 'Isla Alfa', 0, '40', 1, 1)");
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES (700, 40, 'La gran aventura', 3, 0, 1, " . time() . "), (701, 40, 'Tema oculto', 3, 0, 0, " . time() . ")");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
$a = ins(3, 5, 0, array(3, 9), array('aventura_url' => 'showthread.php?tid=700'));
$b = ins(11, null, 4, array(11), array('aventura_url' => 'showthread.php?tid=701'));
sql("INSERT INTO mybb_op_aventuras_propuestas_narrador (peticion_id, narrador_uid, iniciada_por_uid, tipo, estado, created_at, expires_at) VALUES ($b, 9, 9, 'candidatura', 'pendiente', 100, 999999999)");
$prop = (int) $db->insert_id();

$t0 = 1700000000;
mg_vieja("aventura:$a:estado:2:1", $a, 9, 50, 'aventura_denegada', '{"motivo":"Falta detalle"}', $t0, null);
mg_vieja("aventura:$a:estado:2:1", $a, 3, 50, 'aventura_denegada', '{"motivo":"Falta detalle"}', $t0, $t0 + 60);
mg_vieja("propuesta:$prop:nueva", $b, 11, 9, 'candidatura_nueva', '{"propuesta_id":' . $prop . '}', $t0 + 10, null);
mg_vieja("aventura:$a:recompensas", $a, 9, 50, 'recompensas_entregadas', '[]', $t0 + 20, null);
mg_vieja("aventura:$a:raro", $a, 9, 0, 'tipo_que_ya_no_existe', null, $t0 + 30, null);
mg_vieja("aventura:$a:sin_usuario", $a, 0, 50, 'aventura_aprobada', '[]', $t0 + 40, null);
// Una fila que el sistema global ya tiene (el Tablon la envio despues de subir #9): no se pisa.
mg_vieja("aventura:$a:estado:1:1", $a, 5, 50, 'aventura_aprobada', '[]', $t0 + 50, null);
sql("INSERT INTO mybb_op_notificaciones (uid, tipo, categoria, origen, importancia, agrupable, actor_uid, clave_evento, cantidad, datos_json, created_at, leida_en)"
    . " VALUES (5, 'aventura_aprobada', 'aventuras', 'gestion', 'normal', 0, 50, 'aventura:$a:estado:1:1', 1, '{\"peticion_id\":$a}', " . ($t0 + 99) . ", " . ($t0 + 100) . ")");
// Relleno: muchas filas repartidas entre 120 usuarios, la mitad leidas.
$valores = array();
for ($i = 1; $i <= MG_MASIVOS; $i++) {
    $valores[] = "('masivo:$i', $a, " . (1000 + $i % 120) . ", 50, 'aventura_aprobada', '[]', " . ($t0 + $i) . ', ' . ($i % 2 ? 'NULL' : $t0 + $i + 5) . ', 0)';
}
sql('INSERT INTO mybb_op_aventuras_notificaciones (clave_evento, peticion_id, uid, actor_uid, tipo, datos_json, created_at, read_at, attempts) VALUES ' . implode(',', $valores));
// Contador previo desfasado de un usuario que ya usaba el sistema global.
sql('INSERT INTO mybb_op_notificaciones_contador (uid, sin_leer, importantes, actualizado) VALUES (9, 7, 7, ' . time() . ')');
$total_viejas = mg_n('SELECT COUNT(*) AS n FROM mybb_op_aventuras_notificaciones');
$validas = $total_viejas - 1;

prueba('tablas listas', opg_notif_mig_tablas_listas(), true);

// ── 1. Simulacion: cuenta y no escribe ─────────────────────────────────────────────────────────────────────────────
$e = opg_notif_mig_estado();
prueba('estado: total, ya copiadas, pendientes, invalidas', array($e['total'], $e['ya_copiadas'], $e['pendientes'], $e['invalidas']),
    array($total_viejas, 1, $validas - 1, 1));
$tipos = array();
foreach ($e['por_tipo'] as $t) {
    $tipos[$t['tipo']] = array($t['n'], $t['categoria'], $t['origen'], $t['en_catalogo']);
}
prueba('estado: desglose por tipo', $tipos, array(
    'aventura_aprobada' => array(MG_MASIVOS, 'aventuras', 'gestion', true),
    'aventura_denegada' => array(2, 'aventuras', 'gestion', true),
    'candidatura_nueva' => array(1, 'aventuras', 'usuario', true),
    'recompensas_entregadas' => array(1, 'recompensas', 'gestion', true),
    'tipo_que_ya_no_existe' => array(1, 'aventuras', 'sistema', false),
));
$sim = mg_recorrer(true);
prueba('simulacion: cuenta lo que copiaria', array($sim['copiadas'], $sim['existian'], $sim['omitidas'], $sim['leidas']),
    array($validas - 1, 1, 1, $total_viejas));
prueba('simulacion: no escribe', mg_n('SELECT COUNT(*) AS n FROM mybb_op_notificaciones'), 1);

// ── 2. Copia ─────────────────────────────────────────────────────────────────────────────────────────────────────────
$r = mg_recorrer();
prueba('copia: por lotes de 500', $r['lotes'], (int) ceil($total_viejas / 500));
prueba('copia: copiadas, existian, omitidas', array($r['copiadas'], $r['existian'], $r['omitidas']), array($validas - 1, 1, 1));
prueba('copia: filas en la tabla global', mg_n('SELECT COUNT(*) AS n FROM mybb_op_notificaciones'), $validas);

$f = mg_global(9, "aventura:$a:estado:2:1");
prueba('copia: columnas de una denegada sin leer', array($f['tipo'], $f['categoria'], $f['origen'], $f['importancia'], $f['agrupable'], $f['actor_uid'],
    $f['created_at'], $f['leida_en'], $f['url'], $f['cantidad']), array('aventura_denegada', 'aventuras', 'gestion', 'importante', '0', '50', (string) $t0, null, null, '1'));
prueba('copia: datos como los del envio del Tablon', json_decode($f['datos_json'], true),
    array('peticion_id' => $a, 'tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Staff', 'motivo' => 'Falta detalle'));
prueba('copia: texto del catalogo con esos datos', opg_notif_texto('aventura_denegada', json_decode($f['datos_json'], true), 1),
    'Staff denegó la solicitud de «La gran aventura». El creador puede corregirla y reenviarla desde el tablón. (Isla Alfa · Tier 3) Motivo: Falta detalle');
prueba('copia: read_at pasa a leida_en', mg_global(3, "aventura:$a:estado:2:1")['leida_en'], (string) ($t0 + 60));
$c = mg_global(11, "propuesta:$prop:nueva");
prueba('copia: candidatura con narrador y sin el titulo del tema oculto', json_decode($c['datos_json'], true),
    array('peticion_id' => $b, 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Nami', 'narrador' => 'Nami', 'propuesta_id' => $prop));
prueba('copia: recompensas en su categoria del catalogo', mg_global(9, "aventura:$a:recompensas")['categoria'], 'recompensas');
$raro = mg_global(9, "aventura:$a:raro");
prueba('copia: tipo desconocido en aventuras, sistema', array($raro['categoria'], $raro['origen'], $raro['importancia']), array('aventuras', 'sistema', 'normal'));
$ya = mg_global(5, "aventura:$a:estado:1:1");
prueba('copia: la fila que ya existia no se pisa', array($ya['created_at'], $ya['leida_en']), array((string) ($t0 + 99), (string) ($t0 + 100)));
prueba('copia: la fila sin usuario no se copia', mg_n("SELECT COUNT(*) AS n FROM mybb_op_notificaciones WHERE clave_evento = 'aventura:$a:sin_usuario'"), 0);
prueba('copia: estado de lectura de las masivas', array(
    mg_n("SELECT COUNT(*) AS n FROM mybb_op_notificaciones WHERE clave_evento LIKE 'masivo:%' AND leida_en IS NULL"),
    mg_n("SELECT COUNT(*) AS n FROM mybb_op_notificaciones WHERE clave_evento LIKE 'masivo:%' AND leida_en IS NOT NULL")), array(MG_MASIVOS / 2, MG_MASIVOS / 2));
prueba('copia: el Tablon la lee', opg_av_notificaciones_contar(9), 3);

// ── 3. Relanzar: no duplica ─────────────────────────────────────────────────────────────────────────────────────────
$r2 = mg_recorrer();
prueba('relanzar: nada copiado, todo ya existia', array($r2['copiadas'], $r2['existian'], $r2['omitidas']), array(0, $validas, 1));
prueba('relanzar: mismas filas', mg_n('SELECT COUNT(*) AS n FROM mybb_op_notificaciones'), $validas);
prueba('relanzar: el estado ya no tiene pendientes', opg_notif_mig_estado()['pendientes'], 0);

// ── 4. Contadores ───────────────────────────────────────────────────────────────────────────────────────────────────
prueba('antes de reconciliar el contador esta desfasado', count(mg_contadores_incorrectos()) > 0, true);
$usuarios = mg_n('SELECT COUNT(DISTINCT uid) AS n FROM mybb_op_aventuras_notificaciones WHERE read_at IS NULL AND uid > 0');
prueba('reconciliar: un contador por usuario con alguna sin leer', mg_reconciliar_todo(), $usuarios);
prueba('reconciliar: todos los contadores exactos', mg_contadores_incorrectos(), array());
$c9 = una('SELECT sin_leer, importantes FROM mybb_op_notificaciones_contador WHERE uid = 9');
prueba('reconciliar: contador del uid 9 (denegada importante, recompensas y raro)', array((int) $c9['sin_leer'], (int) $c9['importantes']), array(3, 1));
prueba('reconciliar de nuevo: sigue exacto', array(mg_reconciliar_todo(), mg_contadores_incorrectos()), array($usuarios, array()));

// ── 5. La tabla antigua sigue intacta y la reversion borra justo lo copiado ─────────────────────────────────────────
prueba('la tabla antigua no se toca', mg_n('SELECT COUNT(*) AS n FROM mybb_op_aventuras_notificaciones'), $total_viejas);
sql('DELETE n FROM mybb_op_notificaciones n JOIN mybb_op_aventuras_notificaciones a ON a.uid = n.uid AND n.clave_evento = CONVERT(a.clave_evento USING utf8mb4) COLLATE utf8mb4_unicode_ci AND a.created_at = n.created_at');
prueba('reversion: solo queda la fila que ya estaba antes', array_column(opg_av_todos('SELECT clave_evento FROM mybb_op_notificaciones'), 'clave_evento'),
    array("aventura:$a:estado:1:1"));

$db->query('DROP DATABASE IF EXISTS `' . MG_BASE . '`', 1);
resumen();
