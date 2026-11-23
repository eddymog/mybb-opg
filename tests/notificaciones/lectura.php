<?php
/**
 * Lectura de notificaciones: pruebas puras (issue #4, docs/208 apartados 5.4 y 5.5).
 *
 * No usa base de datos: el $db global es un doble que guarda las sentencias y responde lo que la prueba le indica.
 * Cubre los filtros, el cursor y el limite de la lista, la forma de las sentencias (una sola consulta por clave primaria
 * para el contador, una sola consulta indexada para la lista, el marcado con su transaccion corta), la cache por
 * peticion, la autoreparacion y los reintentos ante interbloqueo (1213) y espera agotada (1205).
 *
 *   php tests/notificaciones/lectura.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
define('TABLE_PREFIX', 'mybb_');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

// Los errores registrados con error_log van a un archivo temporal para poder comprobarlos.
$registro = tempnam(sys_get_temp_dir(), 'opg_notif_lectura_');
ini_set('log_errors', '1');
ini_set('error_log', $registro);
function registro_leer()
{
    global $registro;
    clearstatcache();
    $txt = (string) @file_get_contents($registro);
    file_put_contents($registro, '');
    return $txt;
}

$fallos = array();
$total = 0;
function esperar($condicion, $mensaje)
{
    global $fallos, $total;
    $total++;
    if (!$condicion) {
        $fallos[] = $mensaje;
    }
}

/**
 * Doble de $db con los metodos que usa la libreria. Cada sentencia se guarda en $sentencias; $responder decide el
 * resultado: array('filas' => [...], 'afectadas' => n), o array('error' => numero) para simular un error de MySQL
 * (con $estricto = true se lanza como excepcion, igual que mysqli en modo estricto).
 */
class DobleLectura
{
    public $table_prefix = 'mybb_';
    public $sentencias = array();
    public $responder;
    public $estricto = false;
    private $afectadas = 0;
    private $errno = 0;

    public function query($sql, $hide_errors = 0, $write_query = 0)
    {
        if (!$hide_errors) {
            throw new RuntimeException('consulta sin ocultar errores: MyBB detendria la pagina');
        }
        $this->sentencias[] = $sql;
        $r = $this->responder ? call_user_func($this->responder, $sql) : array();
        if (isset($r['error'])) {
            $this->errno = (int) $r['error'];
            $this->afectadas = -1;
            if ($this->estricto) {
                throw new RuntimeException('error simulado ' . $this->errno, $this->errno);
            }
            return false;
        }
        $this->errno = 0;
        $this->afectadas = isset($r['afectadas']) ? $r['afectadas'] : 0;
        return new ArrayIterator(isset($r['filas']) ? $r['filas'] : array());
    }
    public function write_query($sql, $hide_errors = 0) { return $this->query($sql, $hide_errors, 1); }
    public function fetch_array($q)
    {
        if (!$q->valid()) {
            return null;
        }
        $f = $q->current();
        $q->next();
        return $f;
    }
    public function escape_string($s) { return addslashes((string) $s); }
    public function affected_rows() { return $this->afectadas; }
    public function error_number() { return $this->errno; }
    public function error_string() { return $this->errno ? 'error simulado ' . $this->errno : ''; }
}

function doble($responder = null)
{
    global $db;
    $db = new DobleLectura();
    $db->responder = $responder;
    opg_notif_contar_olvidar();
    registro_leer();
    return $db;
}
/** Sentencias que empiezan por $prefijo. */
function sentencias($db, $prefijo)
{
    return array_values(array_filter($db->sentencias, function ($s) use ($prefijo) { return stripos(ltrim($s), $prefijo) === 0; }));
}

// ── 1. Filtros ──────────────────────────────────────────────────────────────────────────────────────────────────────

$todos = array('categoria', 'origen', 'estado', 'importancia');
esperar(opg_notif_filtros(array(), $todos) === array(), 'filtros: vacio');
esperar(opg_notif_filtros(null, $todos) === array(), 'filtros: null es sin filtro');
esperar(opg_notif_filtros(array('categoria' => 'aventuras', 'origen' => 'gestion'), $todos) === array('categoria' => 'aventuras', 'origen' => 'gestion'),
    'filtros: categoria y origen validos');
esperar(opg_notif_filtros(array('categoria' => '', 'origen' => null), $todos) === array(), 'filtros: texto vacio o null es "Todas"');
esperar(opg_notif_filtros(array('estado' => 'sin_leer', 'importancia' => 'importante'), $todos) === array('estado' => 'sin_leer', 'importancia' => 'importante'),
    'filtros: estado e importancia');
esperar(opg_notif_filtros(array('categoria' => "aventuras' OR 1=1 -- "), $todos) === null, 'filtros: valor fuera de la lista fija');
esperar(opg_notif_filtros(array('categoria' => 'Aventuras'), $todos) === null, 'filtros: distingue mayusculas');
esperar(opg_notif_filtros(array('categoria' => array('aventuras')), $todos) === null, 'filtros: valor que no es texto');
esperar(opg_notif_filtros(array('uid' => 5), $todos) === null, 'filtros: clave desconocida');
esperar(opg_notif_filtros(array('estado' => 'sin_leer'), array('categoria', 'origen')) === null, 'filtros: clave no permitida en esa funcion');
esperar(opg_notif_filtros('aventuras', $todos) === null, 'filtros: no es un arreglo');
foreach (opg_notif_catalogo() as $tipo => $def) {
    esperar(in_array($def['categoria'], opg_notif_categorias(), true) && in_array($def['origen'], opg_notif_origenes(), true),
        "filtros: la categoria y el origen del tipo $tipo estan en las listas fijas");
}

// ── 2. Cursor y limite ──────────────────────────────────────────────────────────────────────────────────────────────

esperar(opg_notif_cursor('1760000000_812') === array(1760000000, 812), 'cursor: texto FECHA_ID');
esperar(opg_notif_cursor(array(1760000000, 812)) === array(1760000000, 812), 'cursor: par de enteros');
esperar(opg_notif_cursor(array('5', '7')) === array(5, 7), 'cursor: par de textos de digitos');
esperar(opg_notif_cursor(null) === null && opg_notif_cursor('') === null, 'cursor: vacio es la primera pagina');
foreach (array('abc', '1_0', '-1_2', '1_2_3', '1_2 ', '99999999999_1', '1_4294967296', array(1), array(1, 0), array('x', 2), 12, 1.5) as $malo) {
    esperar(opg_notif_cursor($malo) === false, 'cursor: no valido ' . var_export($malo, true));
}
esperar(opg_notif_cursor_texto(1760000000, 812) === '1760000000_812', 'cursor: texto para la siguiente pagina');
esperar(opg_notif_limite(null) === 30 && opg_notif_limite('x') === 30 && opg_notif_limite(0) === 30 && opg_notif_limite(-3) === 30, 'limite: por defecto 30');
esperar(opg_notif_limite(10) === 10 && opg_notif_limite('20') === 20, 'limite: el indicado');
esperar(opg_notif_limite(5000) === 100, 'limite: como mucho 100');

// ── 3. Contador: una consulta por clave primaria, cache y autoreparacion ────────────────────────────────────────────

$ahora = time();
$db = doble(function ($sql) use ($ahora) {
    if (strpos($sql, 'SELECT sin_leer') === 0) {
        return array('filas' => array(array('sin_leer' => '4', 'importantes' => '1', 'actualizado' => (string) ($ahora - 100))));
    }
    return array();
});
esperar(opg_notif_contar(7) === array('sin_leer' => 4, 'importantes' => 1), 'contar: devuelve sin_leer e importantes');
esperar($db->sentencias === array('SELECT sin_leer, importantes, actualizado FROM `mybb_op_notificaciones_contador` WHERE uid = 7'),
    'contar: una sola consulta por clave primaria');
opg_notif_contar(7);
opg_notif_contar('7');
esperar(count($db->sentencias) === 1, 'contar: la segunda llamada usa la cache de la peticion');
opg_notif_contar(8);
esperar(count($db->sentencias) === 2, 'contar: la cache es por usuario');
opg_notif_contar_olvidar(7);
opg_notif_contar(7);
esperar(count($db->sentencias) === 3, 'contar: olvidar vuelve a leer');

$db = doble();
foreach (array(0, -1, 'x', null, '7 ', 4294967296) as $malo) {
    esperar(opg_notif_contar($malo) === array('sin_leer' => 0, 'importantes' => 0), 'contar: uid no valido da cero ' . var_export($malo, true));
}
esperar($db->sentencias === array(), 'contar: uid no valido no consulta');

$db->table_prefix = 'otro_';
opg_notif_contar(3);
esperar(strpos($db->sentencias[0], '`otro_op_notificaciones_contador`') !== false, 'contar: usa $db->table_prefix');

// Fila con mas de 24 horas desde la ultima reconciliacion: se reconcilia y devuelve el recuento.
$conteo = array('n' => '6', 'i' => '2');
$viejo = function ($sql) use ($ahora, &$conteo) {
    if (strpos($sql, 'SELECT sin_leer') === 0) {
        return array('filas' => array(array('sin_leer' => '9', 'importantes' => '0', 'actualizado' => (string) ($ahora - 86401))));
    }
    if (strpos($sql, 'SELECT COUNT(*)') === 0) {
        return array('filas' => array($conteo));
    }
    return array('afectadas' => 2);
};
$db = doble($viejo);
esperar(opg_notif_contar(5) === array('sin_leer' => 6, 'importantes' => 2), 'autoreparacion: mas de 24 horas recuenta');
$esperadas = array(
    'SELECT sin_leer, importantes, actualizado FROM `mybb_op_notificaciones_contador` WHERE uid = 5',
    'START TRANSACTION',
    'SELECT uid FROM `mybb_op_notificaciones_contador` WHERE uid = 5 FOR UPDATE',
    "SELECT COUNT(*) AS n, COALESCE(SUM(importancia = 'importante'), 0) AS i FROM `mybb_op_notificaciones` WHERE uid = 5 AND leida_en IS NULL",
);
esperar(array_slice($db->sentencias, 0, 4) === $esperadas, 'autoreparacion: bloquea el contador y recuenta por el indice de no leidas');
esperar(isset($db->sentencias[4]) && preg_match('/^INSERT INTO `mybb_op_notificaciones_contador` \(uid, sin_leer, importantes, actualizado\) VALUES \(5, 6, 2, \d+\)'
    . ' ON DUPLICATE KEY UPDATE sin_leer = VALUES\(sin_leer\), importantes = VALUES\(importantes\), actualizado = VALUES\(actualizado\)$/', $db->sentencias[4]),
    'autoreparacion: reescribe sin_leer, importantes y actualizado');
esperar(end($db->sentencias) === 'COMMIT' && count($db->sentencias) === 6, 'autoreparacion: confirma la transaccion corta');
opg_notif_contar(5);
esperar(count($db->sentencias) === 6, 'autoreparacion: el resultado queda en la cache');

// Menos de 24 horas: no se reconcilia todavia.
$db = doble(function ($sql) use ($ahora) {
    return strpos($sql, 'SELECT sin_leer') === 0 ? array('filas' => array(array('sin_leer' => '2', 'importantes' => '0', 'actualizado' => (string) ($ahora - 86000)))) : array();
});
opg_notif_contar(5);
esperar(count($db->sentencias) === 1, 'autoreparacion: menos de 24 horas no recuenta');

// Sin fila: se reconcilia (y se crea).
$db = doble(function ($sql) {
    return strpos($sql, 'SELECT COUNT(*)') === 0 ? array('filas' => array(array('n' => '0', 'i' => null))) : array('afectadas' => 1);
});
esperar(opg_notif_contar(6) === array('sin_leer' => 0, 'importantes' => 0), 'autoreparacion: sin fila recuenta (SUM null es 0)');
esperar(count(sentencias($db, 'INSERT INTO `mybb_op_notificaciones_contador`')) === 1, 'autoreparacion: sin fila la crea');

// Tabla ausente (1146): cero, sin registro, y no se repite la consulta en la peticion.
$db = doble(function ($sql) { return array('error' => 1146); });
esperar(opg_notif_contar(5) === array('sin_leer' => 0, 'importantes' => 0), 'tabla ausente: cero');
esperar(registro_leer() === '', 'tabla ausente: no se registra');
opg_notif_contar(5);
esperar(count($db->sentencias) === 1, 'tabla ausente: una sola consulta por peticion');
$db = doble(function ($sql) { return array('error' => 1146); });
$db->estricto = true;
esperar(opg_notif_contar(5) === array('sin_leer' => 0, 'importantes' => 0), 'tabla ausente en modo estricto: cero sin excepcion');
// Otro error: cero y se registra.
$db = doble(function ($sql) { return array('error' => 2006); });
esperar(opg_notif_contar(5) === array('sin_leer' => 0, 'importantes' => 0), 'error de conexion: cero');
esperar(strpos(registro_leer(), '2006') !== false, 'error de conexion: se registra');
// Sin $db.
$db = null;
opg_notif_contar_olvidar();
esperar(opg_notif_contar(5) === array('sin_leer' => 0, 'importantes' => 0), 'sin $db: cero');

// ── 4. Lista ────────────────────────────────────────────────────────────────────────────────────────────────────────

$fila = function ($id, $t) {
    return array('id' => (string) $id, 'tipo' => 'bitacora_post', 'categoria' => 'temas', 'origen' => 'usuario', 'importancia' => 'normal',
        'agrupable' => '1', 'actor_uid' => '3', 'url' => 'showthread.php?tid=7', 'cantidad' => '2', 'datos_json' => '{"tid":7}',
        'created_at' => (string) $t, 'leida_en' => null);
};
$db = doble(function ($sql) use ($fila) {
    $filas = array();
    for ($i = 0; $i < 31; $i++) {
        $filas[] = $fila(100 - $i, 5000 - $i);
    }
    return array('filas' => $filas);
});
$r = opg_notif_listar(7);
esperar(count($db->sentencias) === 1, 'listar: una sola consulta');
$sql = $db->sentencias[0];
esperar(stripos($sql, 'JOIN') === false && substr_count($sql, 'SELECT') === 1, 'listar: sin uniones ni subconsultas');
esperar(strpos($sql, 'FROM `mybb_op_notificaciones` WHERE uid = 7 ORDER BY created_at DESC, id DESC LIMIT 31') !== false,
    'listar: por uid, ordena por created_at DESC, id DESC y pide una fila de mas');
esperar(count($r['filas']) === 30 && $r['hay_mas'] === true, 'listar: devuelve 30 y avisa de que hay mas');
esperar($r['cursor'] === '4971_71', 'listar: el cursor es la fecha y el id de la ultima fila mostrada');
$f0 = $r['filas'][0];
esperar($f0['id'] === 100 && $f0['cantidad'] === 2 && $f0['actor_uid'] === 3 && $f0['agrupable'] === true && $f0['leida_en'] === null
    && $f0['leida'] === false && $f0['datos'] === array('tid' => 7) && $f0['created_at'] === 5000, 'listar: filas con tipos convertidos y datos decodificados');
esperar(!isset($f0['datos_json']) && !isset($f0['clave_evento']), 'listar: no devuelve datos_json crudo ni la clave');

$db = doble(function ($sql) use ($fila) { return array('filas' => array($fila(9, 10))); });
$r = opg_notif_listar(7, array('categoria' => 'temas', 'origen' => 'usuario', 'estado' => 'sin_leer', 'importancia' => 'importante'), '5000_70', 10);
$sql = $db->sentencias[0];
esperar(strpos($sql, "WHERE uid = 7 AND categoria = 'temas' AND origen = 'usuario' AND leida_en IS NULL AND importancia = 'importante'"
    . ' AND created_at <= 5000 AND (created_at < 5000 OR id < 70) ORDER BY created_at DESC, id DESC LIMIT 11') !== false,
    'listar: filtros de la lista fija y cursor (created_at, id) por rango');
esperar($r['hay_mas'] === false && $r['cursor'] === null && count($r['filas']) === 1, 'listar: ultima pagina sin cursor');
$db = doble();
opg_notif_listar(7, array('estado' => 'leidas'));
esperar(strpos($db->sentencias[0], 'leida_en IS NOT NULL') !== false, 'listar: estado leidas');
$db = doble();
esperar(opg_notif_listar(7) === array('filas' => array(), 'hay_mas' => false, 'cursor' => null), 'listar: sin filas');
$db = doble();
esperar(opg_notif_listar(7, array('categoria' => 'nada')) === false, 'listar: filtro no valido devuelve false');
esperar(opg_notif_listar(7, array(), 'x_1') === false, 'listar: cursor no valido devuelve false');
esperar(opg_notif_listar(0) === array('filas' => array(), 'hay_mas' => false, 'cursor' => null), 'listar: uid no valido, lista vacia');
esperar($db->sentencias === array(), 'listar: nada de eso consulta');
$db = doble(function ($sql) { return array('error' => 1146); });
esperar(opg_notif_listar(7) === array('filas' => array(), 'hay_mas' => false, 'cursor' => null) && registro_leer() === '', 'listar: tabla ausente, lista vacia sin registro');
$db = doble(function ($sql) { return array('error' => 1064); });
esperar(opg_notif_listar(7) === false && strpos(registro_leer(), '1064') !== false, 'listar: otro error devuelve false y se registra');
$db = doble(function ($sql) use ($fila) { $f = $fila(1, 1); $f['datos_json'] = '{roto'; return array('filas' => array($f)); });
$r = opg_notif_listar(7);
esperar($r['filas'][0]['datos'] === array(), 'listar: datos_json roto da datos vacios');
$db = doble();
opg_notif_listar(7, array(), null, 500);
esperar(strpos($db->sentencias[0], 'LIMIT 101') !== false, 'listar: el limite se acota a 100');

// ── 5. Marcar como vista ────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Responde como MySQL a las sentencias del marcado. La lectura inicial devuelve la fila (agrupable, clave, importancia)
 * si $visible (por defecto, si $afectadas > 0); el UPDATE afecta a $afectadas filas. $errores: sentencia => lista de
 * errores a devolver en orden.
 */
function responder_marcado($afectadas, $importancia, &$errores, $agrupable = 0, $visible = null)
{
    $visible = $visible === null ? $afectadas > 0 : $visible;
    return function ($sql) use ($afectadas, $importancia, &$errores, $agrupable, $visible) {
        foreach ($errores as $prefijo => &$lista) {
            if (strpos($sql, $prefijo) === 0 && $lista) {
                return array('error' => array_shift($lista));
            }
        }
        unset($lista);
        if (strpos($sql, 'UPDATE `mybb_op_notificaciones` ') === 0) {
            return array('afectadas' => $afectadas);
        }
        if (strpos($sql, 'SELECT agrupable, clave_evento, importancia') === 0) {
            return array('filas' => $visible ? array(array('agrupable' => (string) $agrupable, 'clave_evento' => "bitacora_post:7", 'importancia' => $importancia)) : array());
        }
        if (strpos($sql, 'SELECT id FROM') === 0) {
            return array('filas' => array(array('id' => '55')));
        }
        return array('afectadas' => 1);
    };
}

$sin = array();
$db = doble(responder_marcado(1, 'normal', $sin));
esperar(opg_notif_marcar_vista(7, 55) === true, 'vista: devuelve true si paso de no leida a leida');
esperar($db->sentencias[0] === 'START TRANSACTION' && end($db->sentencias) === 'COMMIT' && count($db->sentencias) === 5, 'vista: transaccion corta');
esperar($db->sentencias[1] === 'SELECT agrupable, clave_evento, importancia FROM `mybb_op_notificaciones` WHERE id = 55 AND uid = 7 AND leida_en IS NULL',
    'vista: lee la fila por clave primaria, solo si es del usuario y no esta leida');
esperar(preg_match("/^UPDATE `mybb_op_notificaciones` SET leida_en = \\d+, clave_evento = IF\\(agrupable = 1, CONCAT\\(LEFT\\(clave_evento, 111\\), ':leida:', id\\), clave_evento\\)"
    . ' WHERE id = 55 AND uid = 7 AND leida_en IS NULL$/', $db->sentencias[2]) === 1, 'vista: el UPDATE repite las condiciones; libera la clave de las agrupables');
esperar($db->sentencias[3] === 'UPDATE `mybb_op_notificaciones_contador` SET sin_leer = sin_leer - LEAST(sin_leer, 1), importantes = importantes - LEAST(importantes, 0) WHERE uid = 7',
    'vista: resta uno sin bajar de cero (sin restar en UNSIGNED por debajo de cero)');
$db = doble(responder_marcado(1, 'importante', $sin));
opg_notif_marcar_vista(7, 55);
esperar(strpos($db->sentencias[3], 'importantes = importantes - LEAST(importantes, 1)') !== false, 'vista: importante resta tambien de importantes');
// Agrupable: primero se bloquea por el indice unico (uid, clave), como el envio, y despues se actualiza.
$db = doble(responder_marcado(1, 'normal', $sin, 1));
esperar(opg_notif_marcar_vista(7, 55) === true, 'vista agrupable: true');
esperar($db->sentencias[2] === "SELECT id FROM `mybb_op_notificaciones` WHERE uid = 7 AND clave_evento = 'bitacora_post:7' FOR UPDATE"
    && strpos($db->sentencias[3], 'UPDATE `mybb_op_notificaciones` SET') === 0 && strpos($db->sentencias[4], 'UPDATE `mybb_op_notificaciones_contador`') === 0,
    'vista agrupable: bloquea por el indice unico antes del UPDATE');

$db = doble(responder_marcado(0, 'normal', $sin));
esperar(opg_notif_marcar_vista(7, 56) === false, 'vista: ya leida o ajena devuelve false');
esperar(count(sentencias($db, 'UPDATE')) === 0 && count($db->sentencias) === 3, 'vista: ya leida o ajena no actualiza nada');
// Otro proceso la marco entre la lectura y el UPDATE: 0 filas afectadas, el contador no se toca.
$db = doble(responder_marcado(0, 'importante', $sin, 0, true));
esperar(opg_notif_marcar_vista(7, 56) === false && count(sentencias($db, 'UPDATE `mybb_op_notificaciones_contador`')) === 0,
    'vista: marcada entretanto por otro proceso no descuenta');
$db = doble();
esperar(opg_notif_marcar_vista(0, 5) === false && opg_notif_marcar_vista(7, 0) === false && opg_notif_marcar_vista(7, '5 OR 1=1') === false
    && opg_notif_marcar_vista(7, array(5)) === false, 'vista: ids no validos');
esperar($db->sentencias === array(), 'vista: ids no validos no consultan');

// La cache del contador se olvida tras marcar.
$db = doble(responder_marcado(1, 'normal', $sin));
opg_notif_contar(7);
opg_notif_marcar_vista(7, 55);
$antes = count($db->sentencias);
opg_notif_contar(7);
esperar(count($db->sentencias) > $antes && strpos($db->sentencias[$antes], 'SELECT sin_leer') === 0, 'vista: tras marcar, contar vuelve a leer el contador');

// Reintentos: interbloqueo (1213) en el contador, una vez.
$errores = array('UPDATE `mybb_op_notificaciones_contador`' => array(1213));
$db = doble(responder_marcado(1, 'normal', $errores));
esperar(opg_notif_marcar_vista(7, 55) === true, 'reintento 1213: termina bien al segundo intento');
esperar(count(sentencias($db, 'START TRANSACTION')) === 2 && count(sentencias($db, 'ROLLBACK')) === 1 && count(sentencias($db, 'COMMIT')) === 1,
    'reintento 1213: deshace y repite la transaccion entera');
esperar(registro_leer() === '', 'reintento 1213: si sale bien no se registra');
// Espera agotada (1205) dos veces en la fila: tercer intento.
$errores = array('UPDATE `mybb_op_notificaciones` ' => array(1205, 1205));
$db = doble(responder_marcado(1, 'normal', $errores));
esperar(opg_notif_marcar_vista(7, 55) === true && count(sentencias($db, 'START TRANSACTION')) === 3, 'reintento 1205: hasta 2 reintentos');
// Tres fallos: false, registrado, sin cuarto intento.
$errores = array('UPDATE `mybb_op_notificaciones` ' => array(1205, 1213, 1205, 1205));
$db = doble(responder_marcado(1, 'normal', $errores));
esperar(opg_notif_marcar_vista(7, 55) === false, 'reintentos agotados: false');
esperar(count(sentencias($db, 'START TRANSACTION')) === 3 && count(sentencias($db, 'ROLLBACK')) === 3 && count(sentencias($db, 'COMMIT')) === 0,
    'reintentos agotados: tres intentos, todos deshechos');
esperar(strpos(registro_leer(), '1205') !== false, 'reintentos agotados: se registra');
// Otro error: no se reintenta.
$errores = array('UPDATE `mybb_op_notificaciones_contador`' => array(1062));
$db = doble(responder_marcado(1, 'normal', $errores));
esperar(opg_notif_marcar_vista(7, 55) === false && count(sentencias($db, 'START TRANSACTION')) === 1 && count(sentencias($db, 'ROLLBACK')) === 1,
    'otro error: no se reintenta y se deshace');
esperar(strpos(registro_leer(), '1062') !== false, 'otro error: se registra');
// Modo estricto: la excepcion con el codigo 1213 tambien se reintenta y no se propaga.
$errores = array('UPDATE `mybb_op_notificaciones` ' => array(1213));
$db = doble(responder_marcado(1, 'normal', $errores));
$db->estricto = true;
$excepcion = null;
try {
    $r = opg_notif_marcar_vista(7, 55);
} catch (Throwable $e) {
    $excepcion = $e;
}
esperar($excepcion === null && $r === true && count(sentencias($db, 'START TRANSACTION')) === 2, 'modo estricto: reintenta sin propagar la excepcion');
// Fallo del COMMIT.
$errores = array('COMMIT' => array(1213));
$db = doble(responder_marcado(1, 'normal', $errores));
esperar(opg_notif_marcar_vista(7, 55) === true && count(sentencias($db, 'START TRANSACTION')) === 2, 'commit revertido: se reintenta');

// ── 6. Marcar todas ─────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Responde a marcar todas: $filas son las no leidas (id => importancia) que ve la lectura sin bloqueo; $ya_leidas, las que
 * otro proceso marco entretanto (el UPDATE no las afecta). $errores como en responder_marcado.
 */
function responder_todas($filas, $ya_leidas, &$errores)
{
    return function ($sql) use ($filas, $ya_leidas, &$errores) {
        foreach ($errores as $prefijo => &$lista) {
            if (strpos($sql, $prefijo) === 0 && $lista) {
                return array('error' => array_shift($lista));
            }
        }
        unset($lista);
        if (strpos($sql, 'SELECT id, importancia') === 0) {
            $out = array();
            foreach ($filas as $id => $imp) {
                // "importante*" o "normal*": agrupable, con clave "grupo:ID".
                $agrupable = substr($imp, -1) === '*';
                $out[] = array('id' => (string) $id, 'importancia' => rtrim($imp, '*'), 'agrupable' => $agrupable ? '1' : '0',
                    'clave_evento' => ($agrupable ? 'grupo:' : 'k:') . $id);
            }
            return array('filas' => $out);
        }
        if (strpos($sql, 'UPDATE `mybb_op_notificaciones` ') === 0 && preg_match('/id IN \(([0-9,]+)\)$/', $sql, $m)) {
            return array('afectadas' => count(array_diff(explode(',', $m[1]), $ya_leidas)));
        }
        if (strpos($sql, 'UPDATE `mybb_op_notificaciones` ') === 0 && preg_match('/WHERE id = ([0-9]+) /', $sql, $m)) {
            return array('afectadas' => in_array($m[1], $ya_leidas, true) ? 0 : 1);
        }
        return array('afectadas' => 1);
    };
}

$db = doble(responder_todas(array(3 => 'importante', 9 => 'normal', 12 => 'normal', 20 => 'importante'), array(), $sin));
esperar(opg_notif_marcar_todas(7, array('categoria' => 'aventuras')) === 4, 'todas: devuelve cuantas marco');
esperar($db->sentencias[1] === "SELECT id, importancia, agrupable, clave_evento FROM `mybb_op_notificaciones` WHERE uid = 7 AND leida_en IS NULL AND categoria = 'aventuras' ORDER BY clave_evento",
    'todas: lee sin bloquear las no leidas del filtro (sin FOR UPDATE), en el orden del indice unico');
esperar(preg_match("/^UPDATE `mybb_op_notificaciones` SET leida_en = \\d+, clave_evento = .+ WHERE uid = 7 AND leida_en IS NULL AND id IN \\(3,20\\)$/", $db->sentencias[2]) === 1
    && preg_match('/ WHERE uid = 7 AND leida_en IS NULL AND id IN \(9,12\)$/', $db->sentencias[3]) === 1, 'todas: marca por clave primaria, importantes y normales por separado');
esperar($db->sentencias[4] === 'UPDATE `mybb_op_notificaciones_contador` SET sin_leer = sin_leer - LEAST(sin_leer, 4), importantes = importantes - LEAST(importantes, 2) WHERE uid = 7',
    'todas: resta las afectadas y las importantes');
esperar(count($db->sentencias) === 6 && $db->sentencias[5] === 'COMMIT', 'todas: una transaccion corta');
// Otro proceso marco la 20 (importante) y la 9 entretanto: solo se cuentan las que esta llamada paso a leidas.
$db = doble(responder_todas(array(3 => 'importante', 9 => 'normal', 12 => 'normal', 20 => 'importante'), array('20', '9'), $sin));
esperar(opg_notif_marcar_todas(7) === 2, 'todas: no cuenta las que marco otro proceso');
$restas = sentencias($db, 'UPDATE `mybb_op_notificaciones_contador`');
esperar(strpos(end($restas), 'LEAST(sin_leer, 2), importantes = importantes - LEAST(importantes, 1)') !== false,
    'todas: resta solo lo que esta llamada marco');
// Agrupables: una por una, cada una bloqueada antes por el indice unico; la que otro proceso marco entretanto no cuenta.
$db = doble(responder_todas(array(3 => 'normal*', 9 => 'importante', 12 => 'importante*', 20 => 'normal'), array('12'), $sin));
esperar(opg_notif_marcar_todas(7) === 3, 'todas agrupables: marca 3 (la 12 ya la habia marcado otro)');
$s = $db->sentencias;
esperar($s[2] === "SELECT id FROM `mybb_op_notificaciones` WHERE uid = 7 AND clave_evento = 'grupo:3' FOR UPDATE"
    && preg_match('/^UPDATE `mybb_op_notificaciones` SET .+ WHERE id = 3 AND uid = 7 AND leida_en IS NULL$/', $s[3]) === 1
    && $s[4] === "SELECT id FROM `mybb_op_notificaciones` WHERE uid = 7 AND clave_evento = 'grupo:12' FOR UPDATE"
    && preg_match('/ WHERE id = 12 AND uid = 7 AND leida_en IS NULL$/', $s[5]) === 1, 'todas agrupables: bloqueo por el indice unico y UPDATE, una por una');
esperar(preg_match('/ AND id IN \(9\)$/', $s[6]) === 1 && preg_match('/ AND id IN \(20\)$/', $s[7]) === 1, 'todas agrupables: las demas por bloques');
esperar($s[8] === 'UPDATE `mybb_op_notificaciones_contador` SET sin_leer = sin_leer - LEAST(sin_leer, 3), importantes = importantes - LEAST(importantes, 1) WHERE uid = 7',
    'todas agrupables: resta 3 y 1 importante');
// Lotes de 200 por sentencia.
$muchas = array();
for ($i = 1; $i <= 450; $i++) {
    $muchas[$i] = 'normal';
}
$db = doble(responder_todas($muchas, array(), $sin));
esperar(opg_notif_marcar_todas(7) === 450 && count(sentencias($db, 'UPDATE `mybb_op_notificaciones` ')) === 3, 'todas: marca en bloques de 200');
$db = doble(responder_todas(array(), array(), $sin));
esperar(opg_notif_marcar_todas(7) === 0 && count(sentencias($db, 'UPDATE')) === 0, 'todas: sin no leidas no actualiza nada');
$db = doble();
esperar(opg_notif_marcar_todas(7, array('categoria' => 'x')) === false && opg_notif_marcar_todas(7, array('estado' => 'sin_leer')) === false
    && opg_notif_marcar_todas(0) === false, 'todas: filtro o uid no valido');
esperar($db->sentencias === array(), 'todas: filtro o uid no valido no consulta');
$errores = array('UPDATE `mybb_op_notificaciones_contador`' => array(1213));
$db = doble(responder_todas(array(4 => 'normal', 5 => 'normal'), array(), $errores));
esperar(opg_notif_marcar_todas(7, array('origen' => 'gestion')) === 2 && count(sentencias($db, 'START TRANSACTION')) === 2, 'todas: reintento ante interbloqueo');

// ── 7. Reconciliar ──────────────────────────────────────────────────────────────────────────────────────────────────

$conteo = array('n' => '3', 'i' => '1');
$db = doble($viejo);
esperar(opg_notif_reconciliar(5) === array('sin_leer' => 3, 'importantes' => 1), 'reconciliar: devuelve el recuento');
esperar($db->sentencias[0] === 'START TRANSACTION' && end($db->sentencias) === 'COMMIT', 'reconciliar: transaccion corta');
opg_notif_contar(5);
esperar(count($db->sentencias) === 5, 'reconciliar: deja el valor en la cache');
$db = doble();
esperar(opg_notif_reconciliar(0) === false && $db->sentencias === array(), 'reconciliar: uid no valido');
$errores = array('SELECT uid' => array(1205, 1205, 1205));
$db = doble(responder_marcado(0, 'normal', $errores));
esperar(opg_notif_reconciliar(5) === false && count(sentencias($db, 'START TRANSACTION')) === 3, 'reconciliar: reintentos agotados');
registro_leer();

@unlink($registro);
if ($fallos) {
    echo "FALLO lectura: " . count($fallos) . " de $total comprobaciones\n";
    foreach ($fallos as $f) {
        echo " - $f\n";
    }
    exit(1);
}
echo "OK lectura: $total comprobaciones\n";
