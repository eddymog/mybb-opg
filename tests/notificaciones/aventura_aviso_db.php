<?php
/**
 * Avisos aventura_finalizada y aventura_editada del Tablon en el sistema global de notificaciones (issue #26).
 *
 *   php tests/notificaciones/aventura_aviso_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n26, que crea con el
 * esquema del tablon mas docs/notificaciones_migration.sql y borra al terminar.
 *
 * Comprueba:
 *  - Finalizar a mano (opg_av_cambiar_estado a 3) avisa a creador, narrador y jugadores con la clave
 *    aventura:ID:finalizada, una sola insercion, sin el actor; reintentar no duplica; un intento rechazado no avisa.
 *  - Finalizar al pagar (opg_ent_cerrar_si_completo) avisa igual, ademas de recompensas_entregadas, y fuera de la
 *    transaccion; si la solicitud ya no estaba aprobada (no se finalizo en ese cierre) no avisa.
 *  - Editar con narrador asignado avisa al narrador y a los jugadores (sin el creador) con aventura:ID:editada:EDITADO_AT
 *    y la lista de cambios; sin narrador no avisa; una edicion sin cambios no avisa.
 *  - Si el aviso falla (tabla global no disponible a mitad), la accion se completa igual.
 */

putenv('OPG_TEST_AV_DB=opg_notif_n26');
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
$db = new DbShimRegistro('127.0.0.1', 33307, 'root', 'opgtest', 'opg_notif_n26');

require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

function aa_inserciones($desde)
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
function aa_uids($clave, $tipo)
{
    return array_map('intval', array_column(opg_av_todos('SELECT uid FROM mybb_op_notificaciones WHERE clave_evento = '
        . opg_av_sql($clave) . ' AND tipo = ' . opg_av_sql($tipo) . ' ORDER BY uid'), 'uid'));
}
function aa_contador($uid)
{
    $f = una('SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = ' . (int) $uid);
    return $f ? (int) $f['sin_leer'] : 0;
}
function aa_estado($id)
{
    return (int) fila($id)['estado'];
}
function aa_entrada($sobre = array())
{
    return array_merge(array(
        'rol' => 'jugador', 'participantes' => array(9, 11), 'tiene_narrador' => '1', 'narrador_fid' => 5, 'confirma_narrador' => '1', 'idea' => '',
        'isla_fid' => 40, 'dia' => 5, 'tier' => 3, 'comentario' => 'Comentario de prueba', 'inframundo' => '0',
    ), $sobre);
}
/** Entrega pendiente con todos sus pagos aplicados y el mensaje de recompensas ya publicado (solo falta cerrar). */
function aa_entrega_lista($peticion, $tid, $uids)
{
    $ahora = time();
    sql("INSERT INTO mybb_op_aventuras_entregas (peticion_id, tid, tid_activo, tema_nombre, resumen, reputacion, estado, narrador_uid, enviado_por, enviado_at)"
        . " VALUES ($peticion, $tid, $tid, 'Tema', 'Resumen', 'positiva', 'pendiente', 5, 5, $ahora)");
    $e = (int) $GLOBALS['db']->insert_id();
    foreach ($uids as $u) {
        sql("INSERT INTO mybb_op_aventuras_entregas_pagos (entrega_id, uid, rol, porcentaje, detalle_json, aplicado_at, xp_estado)"
            . " VALUES ($e, $u, 'jugador', 100, NULL, $ahora, 'aplicado')");
    }
    $GLOBALS['db']->query("SET SESSION sql_mode = ''", 1);
    sql("INSERT INTO mybb_posts (tid, fid, uid, username, subject, message, dateline, visible) VALUES ($tid, 40, 50, 'Staff', 'RE', '" . OPG_REC_MARCADOR . "', $ahora, 1)");
    $GLOBALS['db']->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
    return opg_ent_obtener_por_id($e);
}

// ── Esquema y datos ──────────────────────────────────────────────────────────────────────────────────────────────────
preparar_esquema($db);
// preparar_esquema aun no aplica las columnas ano y estacion que usa opg_av_editar (uno de los fallos previos de #27).
ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/aventuras_autonarrada_fecha_migration.sql'));
ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/notificaciones_migration.sql'));
$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(50), 'narra' => array(5));
foreach (array(3 => 'Eddy', 5 => 'Luffy', 9 => 'Nami', 11 => 'Usopp', 12 => 'Robin', 50 => 'Staff') as $u => $n) {
    crear_usuario($u, $n);
}
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_forums (fid, name, pid, parentlist, active, disporder) VALUES (15, 'Blue', 0, '15', 1, 1), (40, 'Isla Alfa', 15, '15,40', 1, 2)");
$ahora = time();
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES (700, 40, 'La gran aventura', 3, 0, 1, $ahora), (701, 40, 'Aventura pagada', 3, 0, 1, $ahora), (702, 40, 'Pagada tarde', 3, 0, 1, $ahora)");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
opg_notif_tablas_listas(true);
prueba('la tabla global esta disponible', opg_av_notif_global(), true);

// ── 1. Finalizar a mano ──────────────────────────────────────────────────────────────────────────────────────────────
$a = ins(3, 5, 1, array(3, 9, 11), array('aventura_url' => 'showthread.php?tid=700'));
$desde = count($db->sentencias);
$r = opg_av_cambiar_estado($a, 3, 50);
prueba('el staff finaliza', array(!empty($r['ok']), aa_estado($a)), array(true, 3));
prueba('finalizar: una sola insercion', aa_inserciones($desde), 1);
prueba('finalizar: avisa a creador, narrador y jugadores', aa_uids("aventura:$a:finalizada", 'aventura_finalizada'), array(3, 5, 9, 11));
$f = una("SELECT * FROM mybb_op_notificaciones WHERE uid = 9 AND clave_evento = 'aventura:$a:finalizada'");
$d = json_decode((string) $f['datos_json'], true);
prueba('finalizar: datos del catalogo', $d, array('peticion_id' => $a, 'tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Staff'));
prueba('finalizar: categoria, origen y actor', array($f['categoria'], $f['origen'], (int) $f['actor_uid']), array('aventuras', 'gestion', 50));
prueba('finalizar: texto del catalogo', opg_notif_texto('aventura_finalizada', $d, 1), 'Staff dio por finalizada «La gran aventura». (Isla Alfa · Tier 3)');
prueba('finalizar: los datos no llevan HTML', strpos((string) $f['datos_json'], '<') === false, true);
prueba('reintentar el aviso no duplica', array(opg_av_avisar_finalizada($a, 50), count(aa_uids("aventura:$a:finalizada", 'aventura_finalizada')), aa_contador(9)), array(true, 4, 1));
$c9 = aa_contador(9);
prueba('finalizar otra vez se rechaza y no avisa', array(opg_av_cambiar_estado($a, 3, 50)['codigo'], aa_contador(9)), array(409, $c9));

// El narrador del staff finaliza su propia aventura: no se avisa a si mismo.
$b = ins(12, 5, 1, array(12, 9), array('aventura_url' => 'showthread.php?tid=700'));
prueba('el narrador finaliza', !empty(opg_av_cambiar_estado($b, 3, 5)['ok']), true);
prueba('el actor no recibe su propio aviso', aa_uids("aventura:$b:finalizada", 'aventura_finalizada'), array(9, 12));

// Otro cambio de estado (aprobar) no envia aventura_finalizada.
$c = ins(3, 5, 0, array(3, 9), array('aventura_url' => 'showthread.php?tid=700'));
opg_av_cambiar_estado($c, 1, 50);
prueba('aprobar no avisa de finalizada', aa_uids("aventura:$c:finalizada", 'aventura_finalizada'), array());

// ── 2. Finalizar al pagar ────────────────────────────────────────────────────────────────────────────────────────────
$p = ins(3, 5, 1, array(3, 9, 11), array('aventura_url' => 'showthread.php?tid=701'));
$entrega = aa_entrega_lista($p, 701, array(3, 9, 11));
$desde = count($db->sentencias);
$r = opg_ent_cerrar_si_completo($entrega, opg_av_obtener($p), array('tier' => 3, 'extra_jugadores' => 0), 50);
prueba('el cierre del pago finaliza la solicitud', array($r['cerrada'], aa_estado($p)), array(true, 3));
prueba('pagar: dos inserciones (recompensas y finalizada), una por aviso', aa_inserciones($desde), 2);
prueba('pagar: aventura_finalizada a creador, narrador y jugadores', aa_uids("aventura:$p:finalizada", 'aventura_finalizada'), array(3, 5, 9, 11));
prueba('pagar: recompensas_entregadas se sigue enviando', aa_uids('entrega:' . (int) $entrega['id'] . ':entregada', 'recompensas_entregadas'), array(3, 5, 9, 11));
// La primera insercion de aviso (recompensas) va dentro de la transaccion; la de finalizada, despues del COMMIT.
$tras = array_slice($db->sentencias, $desde);
$pos_commit = array_search('COMMIT', $tras, true);
$pos_fin = null;
foreach ($tras as $i => $s) {
    if (stripos($s, 'INSERT IGNORE INTO `mybb_op_notificaciones`') === 0 && strpos($s, "'aventura_finalizada'") !== false) {
        $pos_fin = $i;
    }
}
prueba('pagar: el aviso de finalizada va fuera de la transaccion', $pos_commit !== false && $pos_fin !== null && $pos_fin > $pos_commit, true);

// Si la solicitud ya no estaba aprobada, el cierre no la finaliza y no avisa.
$q = ins(3, 5, 1, array(3, 9), array('aventura_url' => 'showthread.php?tid=702'));
$entrega_q = aa_entrega_lista($q, 702, array(3, 9));
sql("UPDATE mybb_op_peticionAventuras SET estado = 3 WHERE id = $q");
$r = opg_ent_cerrar_si_completo($entrega_q, opg_av_obtener($q), array('tier' => 3, 'extra_jugadores' => 0), 50);
prueba('cierre sin cambio de estado: no avisa de finalizada', array($r['cerrada'], aa_uids("aventura:$q:finalizada", 'aventura_finalizada')), array(true, array()));

// ── 3. Editar ────────────────────────────────────────────────────────────────────────────────────────────────────────
// Sin narrador: no avisa.
$e1 = ins(3, null, 4, array(3, 9), array('aventura_url' => ''));
$r = opg_av_editar($e1, aa_entrada(array('tiene_narrador' => '0', 'idea' => 'Busco narrador', 'participantes' => array(9, 11))), 3);
prueba('editar sin narrador se guarda', !empty($r['ok']), true);
prueba('editar sin narrador: no avisa', count(opg_av_todos("SELECT id FROM mybb_op_notificaciones WHERE tipo = 'aventura_editada' AND peticion_id = $e1")), 0);

// Con narrador asignado: avisa al narrador y a los jugadores, sin el creador.
$e2 = ins(3, 5, 0, array(3, 9), array('aventura_url' => 'showthread.php?tid=700'));
$desde = count($db->sentencias);
$r = opg_av_editar($e2, aa_entrada(array('participantes' => array(9, 11), 'dia' => 20)), 3);
prueba('editar con narrador se guarda', array(!empty($r['ok']), (int) fila($e2)['narrador_uid']), array(true, 5));
$editado = (int) fila($e2)['editado_at'];
$clave = "aventura:$e2:editada:$editado";
prueba('editar: una sola insercion', aa_inserciones($desde), 1);
prueba('editar: avisa al narrador y a los jugadores, sin el creador', aa_uids($clave, 'aventura_editada'), array(5, 9, 11));
$f = una('SELECT * FROM mybb_op_notificaciones WHERE uid = 5 AND clave_evento = ' . opg_av_sql($clave));
$d = json_decode((string) $f['datos_json'], true);
prueba('editar: datos con la lista de cambios', $d, array('peticion_id' => $e2, 'tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Eddy', 'cambios' => 'los participantes y el día'));
prueba('editar: categoria y origen', array($f['categoria'], $f['origen']), array('aventuras', 'usuario'));
prueba('editar: texto del catalogo', opg_notif_texto('aventura_editada', $d, 1),
    'Eddy editó la solicitud de «La gran aventura»: cambió los participantes y el día. (Isla Alfa · Tier 3)');

// Guardar sin cambios: no avisa.
sql("UPDATE mybb_op_peticionAventuras SET editado_at = 1 WHERE id = $e2");
$antes_n = count(opg_av_todos("SELECT id FROM mybb_op_notificaciones WHERE tipo = 'aventura_editada' AND peticion_id = $e2"));
$r = opg_av_editar($e2, aa_entrada(array('participantes' => array(9, 11), 'dia' => 20)), 3);
prueba('editar sin cambios se guarda y no avisa', array(!empty($r['ok']), count(opg_av_todos("SELECT id FROM mybb_op_notificaciones WHERE tipo = 'aventura_editada' AND peticion_id = $e2"))), array(true, $antes_n));

// ── 4. Un fallo del aviso no impide la accion ────────────────────────────────────────────────────────────────────────
$g = ins(3, 5, 1, array(3, 9), array('aventura_url' => 'showthread.php?tid=700'));
$e3 = ins(3, 5, 0, array(3, 9), array('aventura_url' => 'showthread.php?tid=700'));
sql('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_fuera');
$r1 = opg_av_cambiar_estado($g, 3, 50);
$r2 = opg_av_editar($e3, aa_entrada(array('participantes' => array(9), 'dia' => 30)), 3);
sql('RENAME TABLE mybb_op_notificaciones_fuera TO mybb_op_notificaciones');
prueba('con el aviso fallando se finaliza igual', array(!empty($r1['ok']), aa_estado($g)), array(true, 3));
prueba('con el aviso fallando se edita igual', array(!empty($r2['ok']), (int) fila($e3)['dia']), array(true, 30));

$db->query('DROP DATABASE IF EXISTS `opg_notif_n26`', 1);
resumen();
