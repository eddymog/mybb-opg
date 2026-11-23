<?php
/**
 * Integracion de la Bitacora con el sistema global de notificaciones (issue #12; 208, apartado 9.2).
 *
 *   php tests/notificaciones/bitacora_db.php
 *
 * Usa el MySQL 5.7 de pruebas (ver tests/aventuras/bootstrap_db.php) con su PROPIA base, opg_notif_n12, que crea con
 * docs/notificaciones_migration.sql, docs/bitacora_migration.sql y unas tablas minimas del foro, y borra al terminar.
 *
 * Comprueba:
 *  - post_publicado sigue en mybb_op_bitacora_eventos y crea el agrupable bitacora_post: diez publicaciones dan una fila
 *    con cantidad 10 y suman uno al contador; el autor no se avisa a si mismo; reprocesar un post no suma.
 *  - Los datos guardados llevan las claves del catalogo (tid, tema solo si el foro es publico, nombre).
 *  - ronda_narrada_iniciada, narrador_asignado, narrador_cambiado y narrador_retirado avisan al destinatario que
 *    corresponde con origen usuario; manual_turno y manual_al_dia no avisan.
 *  - Lote grande: 450 seguidores reciben su aviso con el contador en bloques de 200 (3 sentencias).
 *  - Un fallo del aviso (error SQL, tambien con mysqli en modo estricto) no impide registrar la publicacion.
 */

require __DIR__ . '/bootstrap_db.php';

if (!defined('TIME_NOW')) {
    define('TIME_NOW', time());
}

/** El doble de $db con los metodos de MyBB que usa la Bitacora y que no estan en el bootstrap. */
class BitacoraTestDb extends OpgNotifTestDb
{
    public function update_query($table, $array, $where = '')
    {
        $sets = array();
        foreach ($array as $campo => $valor) {
            $sets[] = '`' . $campo . "` = '" . $this->escape_string($valor) . "'";
        }
        return $this->write_query('UPDATE ' . $this->table_prefix . $table . ' SET ' . implode(', ', $sets) . ($where !== '' ? ' WHERE ' . $where : ''));
    }
    public function delete_query($table, $where = '')
    {
        return $this->write_query('DELETE FROM ' . $this->table_prefix . $table . ($where !== '' ? ' WHERE ' . $where : ''));
    }
    public function field_exists($campo, $table)
    {
        $r = mysqli_query($this->link, 'SHOW COLUMNS FROM ' . $this->table_prefix . $table . " LIKE '" . $this->escape_string($campo) . "'");
        return $r && mysqli_num_rows($r) > 0;
    }
}

// Permisos de MyBB: el foro 2 es publico (lo ven los invitados) y el 3 no. Los usuarios ven todo.
function forum_permissions($fid = 0, $uid = 0, $gid = 0)
{
    return array('canview' => 1, 'canviewthreads' => 1);
}
function usergroup_permissions($gid)
{
    return array();
}
function fetch_forum_permissions($fid, $gid, $groupperms)
{
    $publico = (int) $fid === 2 && (string) $gid === '1';
    return array('canview' => $publico ? 1 : 0, 'canviewthreads' => $publico ? 1 : 0);
}

nt_preparar('opg_notif_n12');
$db = new BitacoraTestDb('opg_notif_n12');
$GLOBALS['db'] = $db;
$error = nt_ejecutar_script($db, file_get_contents(MYBB_ROOT . 'docs/bitacora_migration.sql'));
if ($error !== null) {
    fwrite(STDERR, "La migracion de la Bitacora fallo: $error\n");
    nt_borrar();
    exit(2);
}
nt_sql("ALTER TABLE mybb_threads ADD closed VARCHAR(30) NOT NULL DEFAULT ''");
nt_sql('CREATE TABLE mybb_posts (pid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, tid INT UNSIGNED NOT NULL, fid INT UNSIGNED NOT NULL,'
    . ' uid INT UNSIGNED NOT NULL, visible TINYINT(1) NOT NULL DEFAULT 1, dateline INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB');
nt_sql("CREATE TABLE mybb_forums (fid INT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL DEFAULT '', parentlist VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB");
nt_sql("CREATE TABLE mybb_op_fichas (fid INT UNSIGNED NOT NULL PRIMARY KEY, nombre VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB");
nt_sql("CREATE TABLE mybb_users (uid INT UNSIGNED NOT NULL PRIMARY KEY, username VARCHAR(100) NOT NULL DEFAULT '') ENGINE=InnoDB");
nt_sql("INSERT INTO mybb_forums (fid, parentlist) VALUES (2, '10,2'), (3, '10,3')");
nt_sql("INSERT INTO mybb_threads (tid, fid, subject, visible) VALUES (1, 2, 'Rumbo a Grand Line', 1), (2, 2, 'Tema muy seguido', 1), (3, 3, 'Tema privado', 1)");

// Personajes: 1 a 9 y 1001 a 1450 (para el lote grande). El 7 tiene la ficha sin nombre (se usa su nombre de usuario).
$fichas = array();
$usuarios = array();
foreach (array_merge(range(1, 9), range(1001, 1450)) as $u) {
    $usuarios[] = "($u, 'user$u')";
    $fichas[] = $u === 7 ? "(7, '')" : "($u, 'Pj$u')";
}
nt_sql('INSERT INTO mybb_users VALUES ' . implode(',', $usuarios));
nt_sql('INSERT INTO mybb_op_fichas VALUES ' . implode(',', $fichas));

require_once MYBB_ROOT . 'inc/plugins/op_bitacora/functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
nt_registro_iniciar();

function bt_seguir($uid, $tid, $narrador = 0, $ronda = 0)
{
    nt_sql("INSERT INTO mybb_op_temas_seguidos (personaje_uid, tid, ronda_inicio_pid, narrador_uid, creado_en, actualizado_en) VALUES ($uid, $tid, $ronda, $narrador, 1, 1)");
    return (int) mysqli_insert_id($GLOBALS['db']->link);
}
function bt_publicar($uid, $tid)
{
    $fid = (int) nt_valor("SELECT fid FROM mybb_threads WHERE tid = $tid");
    nt_sql("INSERT INTO mybb_posts (tid, fid, uid, visible, dateline) VALUES ($tid, $fid, $uid, 1, " . TIME_NOW . ')');
    $pid = (int) mysqli_insert_id($GLOBALS['db']->link);
    op_bitacora_procesar_post($pid);
    return $pid;
}
function bt_fila($uid, $tipo)
{
    return nt_una("SELECT * FROM mybb_op_notificaciones WHERE uid = $uid AND tipo = '$tipo' ORDER BY id DESC LIMIT 1");
}
function bt_n($uid, $tipo = null)
{
    return (int) nt_valor("SELECT COUNT(*) FROM mybb_op_notificaciones WHERE uid = $uid" . ($tipo ? " AND tipo = '$tipo'" : ''));
}
function bt_contador($uid)
{
    return (int) nt_valor("SELECT sin_leer FROM mybb_op_notificaciones_contador WHERE uid = $uid");
}
function bt_eventos($tipo)
{
    return (int) nt_valor("SELECT COUNT(*) FROM mybb_op_bitacora_eventos WHERE tipo = '$tipo'");
}

// ── Agrupacion: diez publicaciones en un tema seguido ──────────────────────────────────────────────────────────────
$segB = bt_seguir(2, 1);
$segC = bt_seguir(3, 1);
bt_seguir(1, 1);
$pids = array();
for ($i = 0; $i < 10; $i++) {
    $pids[] = bt_publicar(1, 1);
}
$f = bt_fila(2, 'bitacora_post');
nt_prueba('agrupacion: una sola fila por tema y destinatario', bt_n(2, 'bitacora_post'), 1);
nt_prueba('agrupacion: cantidad 10', (int) $f['cantidad'], 10);
nt_prueba('agrupacion: el contador sube una vez', bt_contador(2), 1);
nt_prueba('agrupacion: el otro seguidor tambien', (int) bt_fila(3, 'bitacora_post')['cantidad'], 10);
nt_prueba('agrupacion: clave por tema', $f['clave_evento'], 'bitacora_post:1');
nt_prueba('agrupacion: origen usuario', $f['origen'], 'usuario');
nt_prueba('agrupacion: actor el autor', (int) $f['actor_uid'], 1);
nt_prueba('agrupacion: el autor no se avisa a si mismo', bt_n(1), 0);
nt_prueba('agrupacion: los eventos siguen en la Bitacora', bt_eventos('post_publicado'), 20);
$d = json_decode($f['datos_json'], true);
nt_prueba('datos: tid y titulo del tema publico', array($d['tid'], $d['tema']), array(1, 'Rumbo a Grand Line'));
nt_prueba('texto del catalogo', opg_notif_texto('bitacora_post', $d, 10), '10 publicaciones nuevas en «Rumbo a Grand Line».');

op_bitacora_procesar_post($pids[0]);
nt_prueba('reprocesar un post ya registrado no suma', (int) bt_fila(2, 'bitacora_post')['cantidad'], 10);

nt_sql('UPDATE mybb_op_notificaciones SET leida_en = 1, clave_evento = CONCAT(clave_evento, \':leida:\', id) WHERE uid = 2');
nt_sql('UPDATE mybb_op_notificaciones_contador SET sin_leer = 0 WHERE uid = 2');
bt_publicar(1, 1);
nt_prueba('tras marcarla vista, el siguiente post crea otra fila', bt_n(2, 'bitacora_post'), 2);
nt_prueba('tras marcarla vista, cantidad 1', (int) bt_fila(2, 'bitacora_post')['cantidad'], 1);

// Tema de un foro no publico: se guarda el tid y no el titulo.
bt_seguir(2, 3);
bt_publicar(3, 3);
$d = json_decode(bt_fila(2, 'bitacora_post')['datos_json'], true);
nt_prueba('foro no publico: sin titulo', array(isset($d['tema']), $d['tid']), array(false, 3));

// ── Ronda narrada ──────────────────────────────────────────────────────────────────────────────────────────────────
bt_seguir(4, 1, 7, 0);
$pidRonda = bt_publicar(7, 1);
$f = bt_fila(4, 'bitacora_ronda_iniciada');
nt_ok($f !== null, 'ronda: el seguidor con ese narrador recibe el aviso');
nt_prueba('ronda: clave por post', $f ? $f['clave_evento'] : null, 'bitacora:ronda:' . $pidRonda);
$d = $f ? json_decode($f['datos_json'], true) : array();
nt_prueba('ronda: nombre del narrador (usuario sin ficha)', $d['nombre'] ?? null, 'user7');
nt_prueba('ronda: solo a ese seguidor', (int) nt_valor("SELECT COUNT(*) FROM mybb_op_notificaciones WHERE tipo = 'bitacora_ronda_iniciada'"), 1);
nt_prueba('ronda: evento en la Bitacora', bt_eventos('ronda_narrada_iniciada'), 1);

// ── Narrador: asignar, cambiar, retirar ────────────────────────────────────────────────────────────────────────────
$r = op_bitacora_establecer_narrador(3, $segC, 5);
nt_prueba('asignar: la accion funciona', $r['ok'], true);
$f = bt_fila(5, 'bitacora_narrador_asignado');
nt_ok($f !== null, 'asignar: avisa al narrador');
$d = $f ? json_decode($f['datos_json'], true) : array();
nt_prueba('asignar: datos', array($d['nombre'] ?? null, $d['tema'] ?? null, (int) $f['actor_uid']), array('Pj5', 'Rumbo a Grand Line', 3));
nt_prueba('asignar: el propietario (actor) no se avisa', bt_n(3, 'bitacora_narrador_asignado'), 0);

op_bitacora_establecer_narrador(3, $segC, 6);
nt_prueba('cambiar: avisa al nuevo', bt_n(6, 'bitacora_narrador_cambiado'), 1);
nt_prueba('cambiar: avisa al anterior', bt_n(5, 'bitacora_narrador_cambiado'), 1);
nt_prueba('cambiar: misma clave para los dos', bt_fila(6, 'bitacora_narrador_cambiado')['clave_evento'], bt_fila(5, 'bitacora_narrador_cambiado')['clave_evento']);
nt_prueba('cambiar: nombre del nuevo', json_decode(bt_fila(5, 'bitacora_narrador_cambiado')['datos_json'], true)['nombre'], 'Pj6');

op_bitacora_quitar_narrador(3, $segC);
nt_prueba('quitar: avisa al retirado', bt_n(6, 'bitacora_narrador_retirado'), 1);
op_bitacora_establecer_narrador(3, $segC, 8);
op_bitacora_retirar_participante(3, $segC, 8);
nt_prueba('retirar participante narrador: avisa', bt_n(8, 'bitacora_narrador_retirado'), 1);
nt_prueba('narrador: eventos en la Bitacora', array(bt_eventos('narrador_asignado'), bt_eventos('narrador_cambiado'), bt_eventos('narrador_retirado')), array(2, 1, 2));
nt_prueba('narrador: ninguna transaccion queda abierta', (int) nt_valor('SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = CONNECTION_ID()'), 0);

// ── manual_turno y manual_al_dia no avisan ─────────────────────────────────────────────────────────────────────────
$antes = (int) nt_valor('SELECT COUNT(*) FROM mybb_op_notificaciones');
op_bitacora_marcar_me_toca(2, $segB);
op_bitacora_marcar_no_me_toca(2, $segB);
nt_prueba('manual: eventos registrados', array(bt_eventos('manual_turno'), bt_eventos('manual_al_dia')), array(1, 1));
nt_prueba('manual: ninguna notificacion', (int) nt_valor('SELECT COUNT(*) FROM mybb_op_notificaciones'), $antes);

// ── Lote grande: 450 seguidores ────────────────────────────────────────────────────────────────────────────────────
$valores = array();
foreach (range(1001, 1450) as $u) {
    $valores[] = "($u, 2, 0, 1, 1)";
}
nt_sql('INSERT INTO mybb_op_temas_seguidos (personaje_uid, tid, ronda_inicio_pid, creado_en, actualizado_en) VALUES ' . implode(',', $valores));
$desde = count($db->sentencias);
bt_publicar(9, 2);
$contadores = 0;
foreach (array_slice($db->sentencias, $desde) as $s) {
    if (stripos($s, 'INSERT INTO `mybb_op_notificaciones_contador`') === 0) {
        $contadores++;
    }
}
nt_prueba('lote: todos reciben el aviso', (int) nt_valor("SELECT COUNT(*) FROM mybb_op_notificaciones WHERE clave_evento = 'bitacora_post:2'"), 450);
nt_prueba('lote: contador en bloques de 200 (3 sentencias)', $contadores, 3);
nt_prueba('lote: contadores exactos', (int) nt_valor('SELECT SUM(sin_leer) FROM mybb_op_notificaciones_contador WHERE uid BETWEEN 1001 AND 1450'), 450);
bt_publicar(9, 2);
nt_prueba('lote: el segundo post agrupa (cantidad 2) sin subir el contador', array(
    (int) nt_valor("SELECT MIN(cantidad) FROM mybb_op_notificaciones WHERE clave_evento = 'bitacora_post:2'"),
    (int) nt_valor('SELECT SUM(sin_leer) FROM mybb_op_notificaciones_contador WHERE uid BETWEEN 1001 AND 1450'),
), array(2, 450));

// ── Fallo del aviso: no impide registrar la publicacion ───────────────────────────────────────────────────────────
// Siguen el tema 1 los personajes 1 a 4 y el 7 (al publicar quedo siguiendolo): cada post del 1 deja 4 eventos.
nt_registro_leer();
nt_sql("CREATE TRIGGER bt_falla BEFORE INSERT ON mybb_op_notificaciones FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fallo simulado'");
$eventosAntes = bt_eventos('post_publicado');
$cantidadAntes = (int) bt_fila(3, 'bitacora_post')['cantidad'];
$pidFallo = bt_publicar(1, 1);
nt_prueba('fallo: los eventos de la Bitacora se guardan', bt_eventos('post_publicado'), $eventosAntes + 4);
nt_prueba('fallo: la notificacion no se toca', (int) bt_fila(3, 'bitacora_post')['cantidad'], $cantidadAntes + 0);
nt_ok(strpos(nt_registro_leer(), 'fallo simulado') !== false, 'fallo: el error queda registrado');
nt_prueba('fallo: el seguimiento del autor avanza (la transaccion se confirmo)', (int) nt_valor('SELECT ronda_inicio_pid FROM mybb_op_temas_seguidos WHERE personaje_uid = 1 AND tid = 1'), $pidFallo);

nt_estricto(true);
$eventosAntes = bt_eventos('post_publicado');
$excepcion = null;
try {
    bt_publicar(1, 1);
} catch (Throwable $e) {
    $excepcion = $e->getMessage();
}
nt_estricto(false);
nt_prueba('fallo (mysqli estricto): sin excepcion hacia quien publica', $excepcion, null);
nt_prueba('fallo (mysqli estricto): los eventos se guardan', bt_eventos('post_publicado'), $eventosAntes + 4);
nt_sql('DROP TRIGGER bt_falla');

nt_borrar();
nt_resumen('bitacora_db');
