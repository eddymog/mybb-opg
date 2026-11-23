<?php
/**
 * Campana de notificaciones en el header: pruebas puras (issue #6, docs/208 apartado 7).
 *
 * No usa base de datos: $db es un doble que guarda las sentencias, $templates devuelve la plantilla de referencia
 * (templates/One_Piece_Gaiden_Templates/op_notificaciones_header.html) igual que MyBB, y $plugins guarda los hooks.
 * Cubre el formato del numero y la insignia, la etiqueta accesible, el indicador de importantes, el coste por peticion
 * (invitados sin consultas, usuarios con una sola por clave primaria y cacheada), las tablas ausentes, la precarga de la
 * plantilla y el marcador del header.
 *
 *   php tests/notificaciones/header.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
define('TABLE_PREFIX', 'mybb_');

$registro = tempnam(sys_get_temp_dir(), 'opg_notif_header_');
ini_set('log_errors', '1');
ini_set('error_log', $registro);

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

/** Doble de $plugins: solo guarda los hooks registrados. */
class DoblePlugins
{
    public $hooks = array();
    public function add_hook($hook, $funcion)
    {
        $this->hooks[] = array($hook, $funcion);
    }
}

/** Doble de $db: guarda cada sentencia y responde con $responder (filas, o array('error' => numero)). */
class DobleDb
{
    public $table_prefix = 'mybb_';
    public $sentencias = array();
    public $responder;
    private $errno = 0;
    public function write_query($sql, $hide_errors = 0)
    {
        if (!$hide_errors) {
            throw new RuntimeException('consulta sin ocultar errores');
        }
        $this->sentencias[] = $sql;
        $r = $this->responder ? call_user_func($this->responder, $sql) : array();
        if (isset($r['error'])) {
            $this->errno = (int)$r['error'];
            return false;
        }
        $this->errno = 0;
        return new ArrayIterator(isset($r['filas']) ? $r['filas'] : array());
    }
    public function query($sql, $hide_errors = 0)
    {
        return $this->write_query($sql, $hide_errors);
    }
    public function fetch_array($q)
    {
        if (!$q->valid()) {
            return null;
        }
        $f = $q->current();
        $q->next();
        return $f;
    }
    public function escape_string($s)
    {
        return addslashes((string)$s);
    }
    public function affected_rows()
    {
        return 0;
    }
    public function error_number()
    {
        return $this->errno;
    }
    public function error_string()
    {
        return 'error simulado ' . $this->errno;
    }
}

/** Doble de $templates: devuelve la plantilla de referencia escapada como $templates->get() de MyBB. */
class DobleTemplates
{
    public $vacia = false;
    public function get($titulo)
    {
        if ($this->vacia || $titulo !== 'op_notificaciones_header') {
            return '';
        }
        $fuente = rtrim(file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_notificaciones_header.html'));
        return str_replace("\\'", "'", addslashes($fuente));
    }
}

$plugins = new DoblePlugins();
$db = new DobleDb();
$templates = new DobleTemplates();
$mybb = new stdClass();
$mybb->user = array('uid' => 0);
$mybb->settings = array('bburl' => 'https://foro.test');

ob_start();
require MYBB_ROOT . 'inc/plugins/opg_notificaciones.php';
esperar(ob_get_clean() === '', 'incluir el plugin no imprime nada');
esperar($plugins->hooks === array(
    array('global_start', 'opg_notificaciones_hook_precargar'),
    array('global_intermediate', 'opg_notificaciones_hook_header'),
), 'el plugin registra exactamente los hooks global_start y global_intermediate');
esperar(!function_exists('opg_notif_contar'), 'incluir el plugin no carga la libreria (se carga en el hook)');
esperar($db->sentencias === array(), 'incluir el plugin no consulta');

// ── Formato del numero ──────────────────────────────────────────────────────
foreach (array(array(0, ''), array(1, '1'), array(99, '99'), array(100, '99+'), array(250, '99+'), array(-3, '')) as $caso) {
    esperar(opg_notificaciones_formato_numero($caso[0]) === $caso[1], "formato de {$caso[0]} es '{$caso[1]}'");
}

// ── Insignia y etiqueta ─────────────────────────────────────────────────────
$v = opg_notificaciones_campana_variables(0, 0);
esperar($v['insignia'] === '', 'en cero no hay insignia');
esperar($v['clase'] === '', 'en cero no hay indicador de importantes');
esperar(strpos($v['etiqueta'], 'no tienes avisos sin leer') !== false, 'en cero la etiqueta lo dice con texto');
foreach (array(array(1, '1'), array(99, '99'), array(100, '99+'), array(1234, '99+')) as $caso) {
    $v = opg_notificaciones_campana_variables($caso[0], 0);
    esperar(strpos($v['insignia'], '>' . $caso[1] . '</span>') !== false, "con {$caso[0]} la insignia muestra {$caso[1]}");
    esperar(strpos($v['insignia'], 'aria-hidden="true"') !== false, "con {$caso[0]} la insignia es solo visual");
    esperar(strpos($v['etiqueta'], 'Notificaciones: ' . $caso[0] . ' sin leer') !== false, "con {$caso[0]} la etiqueta lleva el numero exacto");
}
$sin = opg_notificaciones_campana_variables(5, 0);
$con = opg_notificaciones_campana_variables(5, 2);
esperar($con['clase'] === ' op-notif-campana--importante' && $sin['clase'] === '', 'con importantes la campana lleva la clase de importante');
esperar($con['insignia'] === $sin['insignia'], 'las importantes no cambian el numero de la insignia');
esperar(strpos($con['etiqueta'], '5 sin leer, 2 importantes') !== false, 'las importantes se dicen tambien con texto');
esperar(strpos(opg_notificaciones_campana_variables(3, 1)['etiqueta'], '1 importante') !== false, 'singular de importante');
esperar(opg_notificaciones_campana_variables(0, 4)['clase'] === '', 'importantes sin no leidas (desfase) no marcan la campana');

// ── HTML de la campana ──────────────────────────────────────────────────────
$h0 = opg_notificaciones_campana_html(0, 0);
esperar(strpos($h0, 'href="https://foro.test/op/notificaciones.php"') !== false, 'la campana enlaza a /op/notificaciones.php');
esperar(strpos($h0, 'fa-bell') !== false && strpos($h0, 'id="op-notif-campana"') !== false, 'la campana se ve aunque no haya no leidas');
esperar(strpos($h0, 'op-notif-campana__insignia"') === false, 'en cero el HTML no lleva insignia');
esperar(strpos($h0, '<span class="op-notif-campana__texto">Notificaciones: no tienes avisos sin leer</span>') !== false, 'el HTML lleva la etiqueta de texto');
$h100 = opg_notificaciones_campana_html(100, 3);
esperar(strpos($h100, '>99+</span>') !== false, 'con 100 el HTML muestra 99+');
esperar(strpos($h100, 'Notificaciones: 100 sin leer, 3 importantes') !== false, 'con 100 la etiqueta dice 100');
esperar(strpos($h100, 'class="op-notif-campana op-notif-campana--importante"') !== false, 'con importantes el HTML lleva la clase');
esperar(strpos($h100, '{$') === false, 'el HTML no deja variables sin sustituir');
$templates->vacia = true;
esperar(opg_notificaciones_campana_html(3, 0) === '', 'sin la plantilla en la base el header sale vacio y sin error');
$templates->vacia = false;

// La plantilla solo usa numeros y la URL del foro: no muestra textos de notificaciones (requisito 23).
$fuente = file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_notificaciones_header.html');
preg_match_all('/\{\$([a-zA-Z_]+)/', $fuente, $m);
$vars = array_values(array_unique($m[1]));
sort($vars);
esperar($vars === array('mybb', 'op_notif_campana_clase', 'op_notif_campana_etiqueta', 'op_notif_campana_insignia'), 'la plantilla solo usa las variables de la campana y bburl');
esperar(strpos($fuente, "\xE2\x80\x94") === false, 'la plantilla no tiene rayas largas');

// ── Hook del header: coste por peticion ─────────────────────────────────────
$ahora = time();
$db->responder = function ($sql) use ($ahora) {
    if (preg_match('/WHERE uid = 7$/', $sql)) {
        return array('filas' => array(array('sin_leer' => 12, 'importantes' => 1, 'actualizado' => $ahora)));
    }
    if (preg_match('/WHERE uid = 8$/', $sql)) {
        return array('error' => 1146);
    }
    return array('filas' => array());
};

$op_notificaciones_header = 'previo';
opg_notificaciones_hook_header();
esperar($op_notificaciones_header === '', 'invitado: el header sale vacio');
esperar($db->sentencias === array(), 'invitado: ninguna consulta');
esperar(!function_exists('opg_notif_contar'), 'invitado: ni siquiera carga la libreria');

$mybb->user = array('uid' => 7);
opg_notificaciones_hook_header();
esperar(count($db->sentencias) === 1, 'usuario: una sola consulta');
esperar(isset($db->sentencias[0]) && $db->sentencias[0] === 'SELECT sin_leer, importantes, actualizado FROM `mybb_op_notificaciones_contador` WHERE uid = 7', 'usuario: la consulta es por clave primaria del contador');
esperar(strpos($op_notificaciones_header, '>12</span>') !== false, 'usuario: la insignia muestra 12');
esperar(strpos($op_notificaciones_header, 'op-notif-campana--importante') !== false, 'usuario: las importantes salen de la misma consulta');
opg_notificaciones_hook_header();
opg_notificaciones_campana_html(opg_notif_contar(7)['sin_leer'], 0);
esperar(count($db->sentencias) === 1, 'usuario: la segunda lectura en la misma peticion no consulta (cache)');

$mybb->user = array('uid' => 8);
opg_notificaciones_hook_header();
esperar(count($db->sentencias) === 2, 'tablas ausentes: una sola consulta, sin recuento');
esperar(strpos($op_notificaciones_header, 'fa-bell') !== false && strpos($op_notificaciones_header, 'op-notif-campana__insignia"') === false, 'tablas ausentes: campana visible y en cero');
clearstatcache();
esperar(trim((string)file_get_contents($registro)) === '', 'tablas ausentes: sin errores registrados');

// ── Precarga de la plantilla ────────────────────────────────────────────────
unset($GLOBALS['templatelist']);
opg_notificaciones_hook_precargar();
esperar($templatelist === 'op_notificaciones_header', 'precarga sin $templatelist previa');
$templatelist = 'index,forumbit';
opg_notificaciones_hook_precargar();
esperar($templatelist === 'index,forumbit,op_notificaciones_header', 'precarga anade la plantilla a la lista de la pagina');

// ── Marcador en header.html ─────────────────────────────────────────────────
$actual = file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/header.html');
$anterior = str_replace('<div class="op-barra-usuario">{$op_temas_header}{$op_notificaciones_header}</div>', '{$op_temas_header}', $actual);
esperar($anterior !== $actual, 'header.html de referencia lleva el marcador de la campana');
esperar(substr_count($actual, '{$op_notificaciones_header}') === 1, 'header.html tiene el marcador una sola vez');
esperar(opg_notificaciones_header_con_marcador($anterior) === $actual, 'el marcador que pone _activate coincide con header.html de referencia');
esperar(opg_notificaciones_header_con_marcador($actual) === $actual, 'poner el marcador es idempotente');
esperar(opg_notificaciones_header_con_marcador('<div>sin bitacora</div>') === '<div>sin bitacora</div>', 'sin el bloque de la Bitacora no se toca el header');

@unlink($registro);
if ($fallos) {
    echo "FALLO header: " . count($fallos) . " de $total comprobaciones\n";
    foreach ($fallos as $x) {
        echo " - $x\n";
    }
    exit(1);
}
echo "OK header: $total comprobaciones\n";
exit(0);
