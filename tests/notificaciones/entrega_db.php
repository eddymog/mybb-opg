<?php
/**
 * Aviso entrega_enviada del Tablon en el sistema global de notificaciones (issue #11; 208, apartado 9.1).
 *
 *   php tests/notificaciones/entrega_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n11, que crea con el
 * esquema del tablon mas docs/notificaciones_migration.sql y borra al terminar.
 *
 * Comprueba:
 *  - Al registrar una entrega, los demas participantes (creador, narrador y jugadores) reciben entrega_enviada con UNA
 *    sola sentencia de insercion; ni quien envia ni el staff la reciben.
 *  - datos_json lleva las claves que lee el catalogo (peticion_id, tema, isla, tier, actor) y el texto no usa respaldos.
 *  - La clave es entrega:ID:enviada; repetir el aviso no duplica ni vuelve a contar. Un reenvio tras un rechazo es un
 *    evento nuevo (entrega:ID:enviada:2).
 *  - Si el aviso falla, la entrega se guarda igual.
 */

putenv('OPG_TEST_AV_DB=opg_notif_n11');
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
$db = new DbShimRegistro('127.0.0.1', 33307, 'root', 'opgtest', 'opg_notif_n11');

require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

function en_inserciones($desde)
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
function en_contador($uid)
{
    $f = una('SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = ' . (int) $uid);
    return $f ? (int) $f['sin_leer'] : 0;
}
function en_uids($clave)
{
    return array_map('intval', array_column(opg_av_todos('SELECT uid FROM mybb_op_notificaciones WHERE clave_evento = '
        . opg_av_sql($clave) . " AND tipo = 'entrega_enviada' ORDER BY uid"), 'uid'));
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
$ahora = time();
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, closed, visible, dateline) VALUES (700, 40, 'La gran aventura', 3, 0, 1, $ahora), (702, 40, 'Segunda aventura', 3, 0, 1, $ahora)");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
opg_notif_tablas_listas(true);
prueba('la tabla global esta disponible', opg_av_notif_global(), true);

// Aventura A aprobada: creador 3, narrador 5, jugadores 3, 9 y 11, tema 700.
$a = ins(3, 5, 1, array(3, 9, 11), array('aventura_url' => 'showthread.php?tid=700'));
$entrada = array('tema' => '700', 'resumen' => 'Ganaron.', 'reputacion' => 'positiva', 'confirmar_grupo' => '1');

// ── 1. Envio: una sola insercion para los demas participantes ────────────────────────────────────────────────────────
$desde = count($db->sentencias);
$r = opg_ent_enviar($a, $entrada, 9);
prueba('la entrega se registra', !empty($r['ok']), true);
$e = (int) $r['id'];
prueba('una sola sentencia de insercion', en_inserciones($desde), 1);
prueba('destinatarios: creador, narrador y jugadores, sin el autor (9) ni el staff (50)', en_uids("entrega:$e:enviada"), array(3, 5, 11));
prueba('contador global +1 por destinatario y nada para autor ni staff', array(en_contador(3), en_contador(5), en_contador(11), en_contador(9), en_contador(50)), array(1, 1, 1, 0, 0));
$f = una("SELECT * FROM mybb_op_notificaciones WHERE uid = 5 AND clave_evento = 'entrega:$e:enviada'");
$d = json_decode((string) $f['datos_json'], true);
prueba('datos_json con las claves del catalogo', $d, array('peticion_id' => $a, 'tema' => 'La gran aventura', 'isla' => 'Isla Alfa', 'tier' => 3, 'actor' => 'Nami', 'entrega_id' => $e));
prueba('tipo, categoria, importancia y actor', array($f['tipo'], $f['categoria'], $f['importancia'], (int) $f['actor_uid']), array('entrega_enviada', 'aventuras', 'normal', 9));
prueba('texto del catalogo sin respaldos', opg_notif_texto('entrega_enviada', $d, 1),
    'Nami envió la entrega de «La gran aventura» para su revisión. (Isla Alfa · Tier 3)');
prueba('la tabla antigua no recibe nada', (int) una('SELECT COUNT(*) AS n FROM mybb_op_aventuras_notificaciones')['n'], 0);

// ── 2. Reintentar no duplica ─────────────────────────────────────────────────────────────────────────────────────────
prueba('repetir el aviso devuelve true', opg_ent_avisar_enviada(opg_av_obtener($a), $e, 1, 9), true);
prueba('repetir: sin duplicados ni recuento', array(count(en_uids("entrega:$e:enviada")), en_contador(3)), array(3, 1));
prueba('un segundo envio con la entrega pendiente se rechaza y no avisa', array(opg_ent_enviar($a, $entrada, 3)['codigo'], en_contador(5)), array(409, 1));

// ── 3. Reenvio tras un rechazo: evento nuevo ─────────────────────────────────────────────────────────────────────────
prueba('el staff rechaza', !empty(opg_ent_rechazar($e, 'Falta detalle', 50)['ok']), true);
$c5 = en_contador(5);
$r = opg_ent_enviar($a, $entrada, 3);
prueba('el creador reenvia', array(!empty($r['ok']), (int) $r['id']), array(true, $e));
prueba('reenvio: aviso nuevo con el intento en la clave, sin el autor (3)', en_uids("entrega:$e:enviada:2"), array(5, 9, 11));
prueba('reenvio: el contador del narrador sube uno', en_contador(5), $c5 + 1);

// ── 4. Un fallo del aviso no impide la entrega ───────────────────────────────────────────────────────────────────────
$b = ins(12, 5, 1, array(12, 11), array('aventura_url' => 'showthread.php?tid=702'));
sql('RENAME TABLE mybb_op_notificaciones TO mybb_op_notificaciones_fuera');
$r = opg_ent_enviar($b, array_merge($entrada, array('tema' => '702')), 12);
sql('RENAME TABLE mybb_op_notificaciones_fuera TO mybb_op_notificaciones');
prueba('con el aviso fallando la entrega se guarda', array(!empty($r['ok']), opg_ent_obtener($b) ? opg_ent_obtener($b)['estado'] : null), array(true, 'pendiente'));
prueba('con el aviso fallando no queda aviso ni contador', array(en_uids('entrega:' . (int) $r['id'] . ':enviada'), en_contador(12)), array(array(), 0));

$db->query('DROP DATABASE IF EXISTS `opg_notif_n11`', 1);
resumen();
