<?php
/**
 * Envio de notificaciones: pruebas puras (issue #3, docs/208 apartados 5.1 a 5.3 y 11).
 *
 * No usa base de datos: el $db global es un doble que guarda las sentencias y responde lo que la prueba le indica.
 * Cubre la normalizacion de destinatarios, la clave de evento, los enlaces, los datos guardados, la forma de las
 * sentencias (una sola insercion multifila, contador relativo, escape) y los caminos de error.
 *
 *   php tests/notificaciones/envio.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
define('TABLE_PREFIX', 'mybb_');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';

// Los errores registrados con error_log van a un archivo temporal para poder comprobarlos.
$registro = tempnam(sys_get_temp_dir(), 'opg_notif_envio_');
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
 * resultado: array('filas' => [...], 'afectadas' => n, 'id' => n) o false para simular un error de MySQL.
 */
class DobleDb
{
    public $table_prefix = 'mybb_';
    public $sentencias = array();
    public $responder;
    public $tablas = true;
    public $lanzar = false;
    public $ocultar = array();
    private $afectadas = 0;
    private $id = 0;
    private $error = '';

    public function query($sql, $hide_errors = 0, $write_query = 0)
    {
        $this->sentencias[] = $sql;
        $this->ocultar[] = (int) $hide_errors;
        if ($this->lanzar) {
            throw new RuntimeException('excepcion simulada de mysqli');
        }
        $r = $this->responder ? call_user_func($this->responder, $sql) : array();
        if ($r === false) {
            $this->error = 'error simulado';
            $this->afectadas = -1;
            return false;
        }
        $this->error = '';
        $this->afectadas = isset($r['afectadas']) ? $r['afectadas'] : 0;
        if (isset($r['id'])) {
            $this->id = $r['id'];
        }
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
    public function insert_id() { return $this->id; }
    public function error_string() { return $this->error; }
    public function table_exists($t) { return is_array($this->tablas) ? in_array($t, $this->tablas, true) : $this->tablas; }
}

function doble_nuevo($responder = null)
{
    global $db;
    $db = new DobleDb();
    $db->responder = $responder;
    opg_notif_tablas_listas(true);
    registro_leer();
    return $db;
}
function inserciones($db)
{
    return array_values(array_filter($db->sentencias, function ($s) { return stripos(ltrim($s), 'INSERT') === 0 && strpos($s, 'op_notificaciones` (') !== false; }));
}

// ── 1. Normalizacion de destinatarios ───────────────────────────────────────────────────────────────────────────────

esperar(opg_notif_normalizar_destinatarios(array(5, '3', 5, 0, -2, 'x', '7abc', 3.0, 3.5, null, true, array(9), '12'), 0, false) === array(3, 5, 12),
    'normalizar: enteros mayores que cero, sin repetidos, ordenados; descarta texto, negativos, decimales y otros tipos');
esperar(opg_notif_normalizar_destinatarios(7, 0, false) === array(7), 'normalizar: acepta un uid suelto');
esperar(opg_notif_normalizar_destinatarios('7', 0, false) === array(7), 'normalizar: acepta un uid suelto como texto');
esperar(opg_notif_normalizar_destinatarios(array(4, 8, 2), 8, false) === array(2, 4), 'normalizar: quita al actor por defecto');
esperar(opg_notif_normalizar_destinatarios(array(4, 8, 2), 8, true) === array(2, 4, 8), 'normalizar: incluir_actor lo conserva');
esperar(opg_notif_normalizar_destinatarios(array(0, '', null), 0, false) === array(), 'normalizar: sin validos devuelve vacio');
esperar(opg_notif_normalizar_destinatarios(null, 0, false) === array(), 'normalizar: null devuelve vacio');
esperar(opg_notif_normalizar_destinatarios(array('4294967296', 4294967295), 0, false) === array(4294967295), 'normalizar: no pasa del maximo de INT UNSIGNED');
esperar(opg_notif_normalizar_destinatarios(array(' 5', '5 ', '+5', '05'), 0, false) === array(), 'normalizar: solo digitos exactos en los textos');

// ── 2. Clave de evento ──────────────────────────────────────────────────────────────────────────────────────────────

$normal = opg_notif_tipo('aventura_aprobada');
$agrup = opg_notif_tipo('bitacora_post');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => 'aventura:12:aprobada')) === 'aventura:12:aprobada', 'clave: se usa la indicada');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array()) === null, 'clave: obligatoria en tipos normales');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => '')) === null, 'clave: vacia no vale');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => "a'b")) === null, 'clave: comilla rechazada');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => 'a b')) === null, 'clave: espacio rechazado');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => 12)) === null, 'clave: debe ser texto');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => str_repeat('a', 128))) === str_repeat('a', 128), 'clave: 128 caracteres caben');
esperar(opg_notif_clave_evento('aventura_aprobada', $normal, array('clave' => str_repeat('a', 129))) === null, 'clave: mas de 128 no');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('agrupar_por' => 812)) === 'bitacora_post:812', 'clave agrupable: tipo y grupo');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('agrupar_por' => '812')) === 'bitacora_post:812', 'clave agrupable: grupo como texto');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array()) === null, 'clave agrupable: sin grupo ni clave es error');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('agrupar_por' => 'a:b')) === null, 'clave agrupable: grupo con caracteres no admitidos');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('agrupar_por' => array(1))) === null, 'clave agrupable: grupo no escalar');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('clave' => 'bitacora:post:9')) === 'bitacora:post:9', 'clave agrupable: admite clave explicita');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('clave' => 'x:leida:3')) === null, 'clave agrupable: no puede imitar una clave ya liberada');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('clave' => str_repeat('a', 112))) === null, 'clave agrupable: deja sitio para el sufijo :leida:ID');
esperar(opg_notif_clave_evento('bitacora_post', $agrup, array('clave' => str_repeat('a', 111))) === str_repeat('a', 111), 'clave agrupable: 111 caben');

// ── 3. Enlaces ──────────────────────────────────────────────────────────────────────────────────────────────────────

esperar(opg_notif_url_guardable('op/tablon_de_aventuras.php?id=5') === 'op/tablon_de_aventuras.php?id=5', 'url: ruta interna se guarda');
esperar(opg_notif_url_guardable('/showthread.php?tid=7&action=newpost') === '/showthread.php?tid=7&action=newpost', 'url: absoluta del sitio');
esperar(opg_notif_url_guardable('showthread.php?tid=7&amp;action=newpost') === 'showthread.php?tid=7&action=newpost', 'url: &amp; se guarda decodificado');
esperar(opg_notif_url_guardable('showthread.php?tid=7&amp;amp;action=newpost') === 'showthread.php?tid=7&action=newpost', 'url: &amp;amp; se decodifica del todo (no queda &amp;)');
$u = opg_notif_url_guardable('showthread.php?tid=7&amp;amp;action=newpost');
esperar(html_entity_decode(htmlspecialchars($u, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') === 'showthread.php?tid=7&action=newpost', 'url: escapada una vez al imprimir, el navegador lee el parametro correcto');
esperar(opg_notif_url_guardable(opg_notif_url_guardable('a.php?x=1&amp;y=2')) === 'a.php?x=1&y=2', 'url: guardar dos veces da lo mismo');
foreach (array('https://evil.com/x', '//evil.com', 'javascript:alert(1)', '&#106;avascript:alert(1)', 'java&amp;#115;cript:x', "a.php\" onmouseover=\"x",
    'a.php<script>', '', str_repeat('a', 300) . '.php', array('a.php'), 5, null) as $mala) {
    esperar(opg_notif_url_guardable($mala) === null, 'url: se descarta ' . var_export($mala, true));
}

// ── 4. Datos guardados ──────────────────────────────────────────────────────────────────────────────────────────────

$d = opg_notif_preparar_datos(array('peticion_id' => 12, 'tema' => '  La isla  ', 'tier' => '3', 'actor' => 'Rob <3', 'lista' => array(1),
    'html' => '<b>hola</b>', 'otro' => 'a <img src=x>', 'Mal-Clave' => 'x', 'nulo' => null, 'si' => true, 'motivo' => str_repeat('m', 400)));
esperar($d['peticion_id'] === 12 && $d['tema'] === 'La isla' && $d['tier'] === '3' && $d['actor'] === 'Rob <3', 'datos: escalares limpios; "<3" no es HTML');
esperar(!isset($d['lista']) && !isset($d['nulo']) && !isset($d['Mal-Clave']), 'datos: fuera arreglos, nulos y claves con caracteres no admitidos');
esperar(!isset($d['html']) && !isset($d['otro']), 'datos: no se guarda HTML');
esperar($d['si'] === 1, 'datos: booleano como entero');
esperar(mb_strlen($d['motivo']) === 255, 'datos: textos recortados a 255 caracteres');
esperar(opg_notif_preparar_datos('no') === array(), 'datos: lo que no es arreglo queda vacio');
$muchos = array();
for ($i = 0; $i < 40; $i++) {
    $muchos['k' . $i] = $i;
}
esperar(count(opg_notif_preparar_datos($muchos)) === 20, 'datos: como mucho 20 claves');
esperar(opg_notif_datos_json(array()) === null, 'datos_json: vacio se guarda como NULL');
esperar(opg_notif_datos_json(array('tema' => 'Ñandú «x»')) === '{"tema":"Ñandú «x»"}', 'datos_json: unicode sin escapar');
esperar(opg_notif_datos_json(array('tema' => "a\xC3")) === '{"tema":"a' . "\xEF\xBF\xBD" . '"}', 'datos_json: UTF-8 roto se sustituye, no falla');

// El titulo de un tema solo se guarda si quien llama asegura que es publico (requisito 50).
$db = doble_nuevo(function ($sql) { return strpos($sql, 'threads') !== false ? array('filas' => array(array('visible' => 1))) : array(); });
$d = opg_notif_preparar_datos_envio(array('tid' => 7, 'tema' => 'Secreto'), array());
esperar(!isset($d['tema']) && $d['tid'] === 7, 'tema: con tid y sin tema_publico, se guarda el tid y no el titulo');
$d = opg_notif_preparar_datos_envio(array('tid' => 7, 'tema' => 'Publico'), array('tema_publico' => true));
esperar(isset($d['tema']) && $d['tema'] === 'Publico', 'tema: con tema_publico y tema visible, se guarda el titulo');
$db = doble_nuevo(function ($sql) { return strpos($sql, 'threads') !== false ? array('filas' => array(array('visible' => 0))) : array(); });
$d = opg_notif_preparar_datos_envio(array('tid' => 7, 'tema' => 'Borrado'), array('tema_publico' => true));
esperar(!isset($d['tema']), 'tema: tema no visible (moderado o borrado) no guarda el titulo aunque se pida');
$db = doble_nuevo(function ($sql) { return strpos($sql, 'threads') !== false ? false : array(); });
$d = opg_notif_preparar_datos_envio(array('tid' => 7, 'tema' => 'X'), array('tema_publico' => true));
esperar(!isset($d['tema']), 'tema: si no se puede comprobar, no se guarda el titulo');
$d = opg_notif_preparar_datos_envio(array('peticion_id' => 3, 'tema' => 'Aventura'), array());
esperar($d['tema'] === 'Aventura', 'tema: sin tid (aventura del Tablon) lo decide quien llama');

// ── 5. Envio: forma de las sentencias ───────────────────────────────────────────────────────────────────────────────

// Lote de tres destinatarios, todos nuevos: una sola insercion multifila y un UPDATE relativo del contador.
$db = doble_nuevo(function ($sql) {
    if (stripos($sql, 'INSERT IGNORE') === 0) return array('afectadas' => 2, 'id' => 40);
    if (stripos($sql, 'UPDATE') === 0) return array('afectadas' => 2);
    return array();
});
$r = opg_notificar(array(9, 4, 2, 4), 'aventura_denegada', array('peticion_id' => 5, 'actor' => "O'Neil"), array('clave' => 'aventura:5:denegada', 'actor' => 9, 'url' => 'op/x.php?a=1&amp;b=2'));
esperar($r === 2, 'lote: devuelve las filas nuevas (sin el actor)');
$ins = inserciones($db);
esperar(count($ins) === 1, 'lote: una sola insercion');
esperar(isset($ins[0]) && substr_count($ins[0], '),(') === 1, 'lote: insercion multifila con dos filas');
esperar(isset($ins[0]) && stripos($ins[0], 'INSERT IGNORE INTO `mybb_op_notificaciones`') === 0, 'lote: INSERT IGNORE con prefijo');
esperar(isset($ins[0]) && strpos($ins[0], "O\\'Neil") !== false, 'lote: datos escapados');
esperar(isset($ins[0]) && strpos($ins[0], "'op/x.php?a=1&b=2'") !== false, 'lote: enlace guardado decodificado');
esperar(isset($ins[0]) && strpos($ins[0], "'importante'") !== false && strpos($ins[0], "'gestion'") !== false && strpos($ins[0], "'aventuras'") !== false,
    'lote: categoria, origen e importancia copiados del catalogo');
$upd = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'UPDATE `mybb_op_notificaciones_contador`') === 0; }));
esperar(count($upd) === 1 && strpos($upd[0], 'sin_leer = sin_leer + 1') !== false && strpos($upd[0], 'importantes = importantes + 1') !== false
    && strpos($upd[0], 'uid IN (2,4)') !== false, 'lote: contador con incremento relativo, importante +1, solo de los nuevos');
esperar(!array_filter($db->sentencias, function ($s) { return stripos($s, 'SELECT') === 0 && strpos($s, 'contador') !== false; }),
    'lote: no lee el contador antes de escribirlo');
esperar(!in_array(0, $db->ocultar, true), 'todas las consultas ocultan el error de MyBB (no detienen la pagina)');

// Tipo normal (no importante): importantes + 0.
$db = doble_nuevo(function ($sql) { return array('afectadas' => 1, 'id' => 3); });
opg_notificar(5, 'aventura_aprobada', array(), array('clave' => 'k:1'));
$upd = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'UPDATE') === 0; }));
esperar(count($upd) === 1 && strpos($upd[0], 'importantes = importantes + 0') !== false && strpos($upd[0], 'uid IN (5)') !== false, 'normal: importantes + 0');
$ins = inserciones($db);
esperar(isset($ins[0]) && strpos($ins[0], ', NULL, 1, NULL, ') !== false, 'normal: sin enlace ni datos se guardan NULL');

// Repetido: la insercion no afecta filas, el contador no se toca.
$db = doble_nuevo(function ($sql) { return array('afectadas' => 0, 'id' => 0); });
esperar(opg_notificar(5, 'aventura_aprobada', array(), array('clave' => 'k:1')) === 0, 'repetido: devuelve 0');
esperar(count($db->sentencias) === 1, 'repetido: solo la insercion, sin tocar el contador');

// Contador ausente: se crea con un recuento que incluye la fila nueva.
$db = doble_nuevo(function ($sql) {
    if (stripos($sql, 'INSERT IGNORE') === 0) return array('afectadas' => 1, 'id' => 8);
    return array('afectadas' => 0);
});
esperar(opg_notificar(5, 'aventura_aprobada', array(), array('clave' => 'k:2')) === 1, 'contador ausente: devuelve 1');
$crear = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'INSERT INTO `mybb_op_notificaciones_contador`') === 0; }));
esperar(count($crear) === 1 && strpos($crear[0], 'COUNT(*)') !== false && strpos($crear[0], 'leida_en IS NULL') !== false
    && strpos($crear[0], 'ON DUPLICATE KEY UPDATE') !== false, 'contador ausente: INSERT ... SELECT COUNT(*) de las no leidas');

// Lote parcial: se identifican las nuevas por clave y por id desde el primero insertado.
$db = doble_nuevo(function ($sql) {
    if (stripos($sql, 'INSERT IGNORE') === 0) return array('afectadas' => 1, 'id' => 50);
    if (stripos($sql, 'SELECT @@') === 0) return array('filas' => array(array('modo' => 1)));
    if (stripos($sql, 'SELECT') === 0) return array('filas' => array(array('id' => 50, 'uid' => 6)));
    if (stripos($sql, 'UPDATE') === 0) return array('afectadas' => 1);
    return array();
});
esperar(opg_notificar(array(3, 6), 'aventura_aprobada', array(), array('clave' => 'k:3')) === 1, 'lote parcial: devuelve 1');
$sel = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'SELECT id, uid') === 0; }));
esperar(count($sel) === 1 && strpos($sel[0], "clave_evento = 'k:3'") !== false && strpos($sel[0], 'id >= 50') !== false && strpos($sel[0], 'LIMIT 1') !== false,
    'lote parcial: consulta por clave, id >= primer id insertado, limite = filas insertadas');
$upd = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'UPDATE') === 0; }));
esperar(count($upd) === 1 && strpos($upd[0], 'uid IN (6)') !== false, 'lote parcial: el contador sube solo para la nueva');

// Agrupable: ON DUPLICATE KEY UPDATE en una sentencia; 2 filas afectadas = actualizada, no suma al contador.
$db = doble_nuevo(function ($sql) { return array('afectadas' => 2, 'id' => 1); });
esperar(opg_notificar(5, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7, 'actor' => 3)) === 0, 'agrupable actualizada: devuelve 0');
esperar(count($db->sentencias) === 1, 'agrupable actualizada: una sola sentencia');
esperar(strpos($db->sentencias[0], 'ON DUPLICATE KEY UPDATE cantidad = cantidad + 1') !== false && strpos($db->sentencias[0], 'actor_uid = VALUES(actor_uid)') !== false
    && strpos($db->sentencias[0], 'created_at = VALUES(created_at)') !== false && strpos($db->sentencias[0], "'bitacora_post:7'") !== false,
    'agrupable: suma cantidad, guarda el ultimo actor y renueva la fecha');
$db = doble_nuevo(function ($sql) { return stripos($sql, 'INSERT') === 0 ? array('afectadas' => 1, 'id' => 9) : array('afectadas' => 1); });
esperar(opg_notificar(5, 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7)) === 1, 'agrupable insertada: devuelve 1 y sube el contador');
// Lote agrupable de 3 destinatarios: una sentencia atomica por destinatario (la multifila se interbloquea, ver la
// libreria); el de en medio es nuevo (1 fila afectada) y los otros suman (2). El contador: una sentencia, solo el nuevo.
$db = doble_nuevo(function ($sql) {
    if (stripos($sql, 'INSERT') === 0) return array('afectadas' => strpos($sql, 'VALUES (2,') !== false ? 1 : 2, 'id' => 70);
    return array('afectadas' => 1);
});
esperar(opg_notificar(array(1, 2, 3), 'bitacora_post', array('tid' => 7), array('agrupar_por' => 7)) === 1, 'lote agrupable: 1 nueva');
$ins = inserciones($db);
esperar(count($ins) === 3 && substr_count(implode('', $ins), '),(') === 0, 'lote agrupable: una sentencia de una fila por destinatario');
$upd = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'UPDATE `mybb_op_notificaciones_contador`') === 0; }));
esperar(count($upd) === 1 && strpos($upd[0], 'uid IN (2)') !== false, 'lote agrupable: un solo UPDATE del contador, solo del nuevo');
esperar(!array_filter($db->sentencias, function ($s) { return stripos($s, 'SELECT') === 0; }), 'lote agrupable: no necesita consultas de identificacion');
// affected_rows imposible: se registra y ese destinatario no suma al contador.
$db = doble_nuevo(function ($sql) { return stripos($sql, 'INSERT') === 0 ? array('afectadas' => 3, 'id' => 1) : array('afectadas' => 1); });
esperar(opg_notificar(array(1, 2), 'bitacora_post', array(), array('agrupar_por' => 7)) === 0, 'agrupable: affected_rows fuera de rango no cuenta como nueva');
esperar(strpos(registro_leer(), 'inesperadas') !== false, 'agrupable: affected_rows fuera de rango se registra');
esperar(count($db->sentencias) === 2, 'agrupable: affected_rows fuera de rango no toca el contador');
// Error a mitad de un lote agrupable: las filas ya creadas suben su contador y se devuelve false.
$db = doble_nuevo(function ($sql) {
    if (stripos($sql, 'INSERT') === 0) return strpos($sql, 'VALUES (2,') !== false ? false : array('afectadas' => 1, 'id' => 1);
    return array('afectadas' => 1);
});
esperar(opg_notificar(array(1, 2, 3), 'bitacora_post', array(), array('agrupar_por' => 7)) === false, 'agrupable: error a mitad devuelve false');
$upd = array_values(array_filter($db->sentencias, function ($s) { return stripos($s, 'UPDATE') === 0; }));
esperar(count($upd) === 1 && strpos($upd[0], 'uid IN (1)') !== false, 'agrupable: error a mitad, la fila ya creada lleva su contador');
esperar(strpos(registro_leer(), 'error simulado') !== false, 'agrupable: error a mitad se registra');

// Lotes grandes: bloques de 200.
$db = doble_nuevo(function ($sql) { return stripos($sql, 'INSERT') === 0 ? array('afectadas' => substr_count($sql, '),(') + 1, 'id' => 1) : array('afectadas' => substr_count($sql, ',') + 1); });
esperar(opg_notificar(range(1, 450), 'aventura_aprobada', array(), array('clave' => 'k:9')) === 450, 'lote grande: cuenta todas');
esperar(count(inserciones($db)) === 3, 'lote grande: bloques de 200 (3 inserciones para 450)');

// ── 6. Errores y casos limite ───────────────────────────────────────────────────────────────────────────────────────

$db = doble_nuevo();
esperar(opg_notificar(array(5), 'no_existe', array(), array('clave' => 'k')) === false, 'tipo desconocido: false');
esperar(strpos(registro_leer(), 'no_existe') !== false, 'tipo desconocido: se registra');
esperar(count($db->sentencias) === 0, 'tipo desconocido: ninguna consulta');
esperar(opg_notificar(array(5), array('x'), array(), array('clave' => 'k')) === false, 'tipo no texto: false');

$db = doble_nuevo();
esperar(opg_notificar(array(0, -1), 'aventura_aprobada', array(), array('clave' => 'k')) === 0, 'sin destinatarios: 0');
esperar(opg_notificar(array(3), 'aventura_aprobada', array(), array('clave' => 'k', 'actor' => 3)) === 0, 'solo el actor: 0');
esperar(count($db->sentencias) === 0 && registro_leer() === '', 'sin destinatarios: ni consultas ni errores');

$db = doble_nuevo();
esperar(opg_notificar(array(3), 'aventura_aprobada', array(), array()) === false, 'sin clave: false');
esperar(strpos(registro_leer(), 'clave') !== false, 'sin clave: se registra');

$db = doble_nuevo();
esperar(opg_notificar(array(3), 'aventura_aprobada', array(), array('clave' => 'k', 'silenciosa' => true)) === 0, 'silenciosa: 0');
esperar(count($db->sentencias) === 0, 'silenciosa: no crea nada');

// Enlace externo: se descarta y la notificacion se crea igual, sin enlace.
$db = doble_nuevo(function ($sql) { return array('afectadas' => 1, 'id' => 1); });
esperar(opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k', 'url' => 'https://evil.com')) === 1, 'url externa: se crea igual');
$ins = inserciones($db);
esperar(isset($ins[0]) && strpos($ins[0], 'evil') === false, 'url externa: no llega a la base');
esperar(strpos(registro_leer(), 'enlace') !== false, 'url externa: se registra');

// Tablas ausentes: false, sin consultas de escritura y sin error registrado.
$db = doble_nuevo();
$db->tablas = false;
opg_notif_tablas_listas(true);
esperar(opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k')) === false, 'tablas ausentes: false');
esperar(count($db->sentencias) === 0 && registro_leer() === '', 'tablas ausentes: sin consultas ni errores visibles');
$db = doble_nuevo();
$db->tablas = array('op_notificaciones');
opg_notif_tablas_listas(true);
esperar(opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k')) === false, 'falta la tabla del contador: false');

// Error de MySQL al insertar: false y registro, sin excepcion.
$db = doble_nuevo(function ($sql) { return false; });
esperar(opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k')) === false, 'error SQL al insertar: false');
esperar(strpos(registro_leer(), 'error simulado') !== false, 'error SQL al insertar: se registra el mensaje de MySQL');

// Error al actualizar el contador: false y registro.
$db = doble_nuevo(function ($sql) { return stripos($sql, 'INSERT') === 0 ? array('afectadas' => 1, 'id' => 1) : false; });
esperar(opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k')) === false, 'error SQL en el contador: false');
esperar(registro_leer() !== '', 'error SQL en el contador: se registra');

// mysqli en modo estricto (PHP 8.1+) lanza excepciones: se capturan.
$db = doble_nuevo();
$db->lanzar = true;
$excepcion = null;
try {
    $r = opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k'));
} catch (Throwable $e) {
    $excepcion = $e;
}
esperar($excepcion === null && $r === false, 'excepcion de mysqli: no se propaga y devuelve false');
esperar(strpos(registro_leer(), 'excepcion simulada') !== false, 'excepcion de mysqli: se registra');

// Sin $db global: false sin romper.
$db = null;
opg_notif_tablas_listas(true);
esperar(opg_notificar(3, 'aventura_aprobada', array(), array('clave' => 'k')) === false, 'sin $db: false');
registro_leer();

@unlink($registro);
if ($fallos) {
    echo "FALLO envio: " . count($fallos) . " de $total comprobaciones\n";
    foreach ($fallos as $f) {
        echo " - $f\n";
    }
    exit(1);
}
echo "OK envio: $total comprobaciones\n";
