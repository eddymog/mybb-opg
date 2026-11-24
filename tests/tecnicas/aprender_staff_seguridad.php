<?php
// Prueba de seguridad de op/staff/tecnicas_aprender.php (#30). No necesita MyBB ni base de datos:
// usa una base falsa que registra las consultas y escapa como mysqli (comillas y barras).
require __DIR__ . '/../../inc/plugins/lib/tecnicas_aprender_staff.php';

$fallos = [];
$check = function ($ok, $msg) use (&$fallos) { if (!$ok) $fallos[] = $msg; };

class OpgDbFalsaTecAprender
{
    public $consultas = [];
    public $fichas = [];
    private $filas = [];
    private $sig_id = 100;
    public function escape_string($s) { return addslashes((string) $s); }
    public function query($sql)
    {
        $this->consultas[] = $sql;
        if (stripos($sql, 'SELECT') === 0) {
            $this->filas = [];
            foreach ($this->fichas as $fid => $f) { if (strpos($sql, "fid='" . addslashes($fid) . "'") !== false) $this->filas[] = $f; }
        }
        return true;
    }
    public function fetch_array($q) { return array_shift($this->filas); }
    public function affected_rows() { return 1; }
    public function insert_id() { return ++$this->sig_id; }
    public function escrituras() { return array_values(array_filter($this->consultas, function ($s) { return stripos($s, 'SELECT') !== 0; })); }
}

// Una consulta tiene una comilla sin escapar si, quitando las secuencias \x, queda un número impar de comillas.
$comillas_balanceadas = function ($sql) { return substr_count(preg_replace('/\\\\./s', '', $sql), "'") % 2 === 0; };

$post = ['ficha_id' => '7', 'accion' => 'Añadir', 'tecnicas' => 'CAC102, ADF101', 'staff' => '999', 'razon' => 'Premio'];

// 1. Sin token valido: no se escribe nada (la pagina responde con el error de clave invalida).
$db = new OpgDbFalsaTecAprender();
$db->fichas['7'] = ['fid' => 7, 'nombre' => 'Luffy'];
$r = opg_tec_aprender_procesar(opg_tec_aprender_entrada($post), false, 5, $db);
$check($r['estado'] === 'token_invalido', 'sin token debe dar token_invalido');
$check(count($db->consultas) === 0, 'sin token no debe tocar la base');
$r = opg_tec_aprender_procesar(opg_tec_aprender_entrada($post), null, 5, $db);
$check($r['estado'] === 'token_invalido' && count($db->consultas) === 0, 'token nulo tampoco escribe');

// 2. Con token valido: inserta cada tecnica y una auditoria por tecnica.
$db = new OpgDbFalsaTecAprender();
$db->fichas['7'] = ['fid' => 7, 'nombre' => 'Luffy'];
$r = opg_tec_aprender_procesar(opg_tec_aprender_entrada($post), true, 5, $db);
$esc = $db->escrituras();
$check($r['estado'] === 'hecho', 'con token debe procesar');
$check(count($esc) === 4, 'deben ser 2 INSERT de tecnica y 2 de auditoria, hay ' . count($esc));
$check(strpos($esc[0], "VALUES ('CAC102', '7')") !== false, 'insert de CAC102');
$check($r['avisar_ids'] === ['CAC102', 'ADF101'], 'ids a avisar');
$check($r['avisar_huellas'] === ['i101', 'i102'], 'huellas a avisar');
$check($r['f_var']['nombre'] === 'Luffy', 'ficha cargada');
$check(strpos($r['log'], '-- Añadir técnica ID: CAC102') !== false, 'log con la tecnica');

// 3. El staff sale de la sesion: el campo "staff" del formulario se ignora.
foreach ($esc as $sql) {
    if (strpos($sql, 'audit_consola_tec_mod') !== false) {
        $check(strpos($sql, "('5', 'Premio'") !== false, 'auditoria debe usar el uid de sesion 5: ' . $sql);
        $check(strpos($sql, '999') === false, 'auditoria no debe usar el staff del formulario');
    }
}
$r = opg_tec_aprender_procesar(opg_tec_aprender_entrada(['staff' => "x' OR '1"] + $post), true, "12abc", $db = new OpgDbFalsaTecAprender());
foreach ($db->escrituras() as $sql) { if (strpos($sql, 'audit') !== false) $check(strpos($sql, "('12', ") !== false, 'staff se convierte a entero'); }
$check(opg_tec_aprender_entrada(['staff' => ''] + $post) !== null, 'ya no hace falta el campo staff');

// 4. Comillas en todos los campos no rompen las consultas.
$malo = ['ficha_id' => "7' OR '1'='1", 'accion' => 'Remover', 'tecnicas' => "A'B, C\\', D\"E", 'staff' => "s'", 'razon' => "por 'x' \\"];
$db = new OpgDbFalsaTecAprender();
$db->fichas["7' OR '1'='1"] = ['fid' => 7, 'nombre' => "O'Brien"];
$r = opg_tec_aprender_procesar(opg_tec_aprender_entrada($malo), true, 5, $db);
$check($r['estado'] === 'hecho', 'entrada con comillas se procesa');
$check(count($db->consultas) === 7, 'SELECT + 3 DELETE + 3 auditorias, hay ' . count($db->consultas));
foreach ($db->consultas as $sql) { $check($comillas_balanceadas($sql), 'comilla sin escapar en: ' . $sql); }
$check(strpos($db->consultas[0], "fid='7\\' OR \\'1\\'=\\'1'") !== false, 'ficha_id escapado en SELECT');
$check(strpos($db->consultas[1], "tid='A\\'B'") !== false, 'tecnica escapada en DELETE');
$check($r['avisar_huellas'][0] === "dA'B:1", 'huella de borrado conserva el id original');

// 5. Lista fija de acciones y campos obligatorios.
$check(opg_tec_aprender_entrada(['accion' => 'Borrar todo'] + $post) === null, 'accion fuera de lista');
$check(opg_tec_aprender_entrada(['razon' => '  '] + $post) === null, 'razon vacia');
$check(opg_tec_aprender_entrada(['tecnicas' => ['x']] + $post) === null, 'tecnicas como array');
$check(opg_tec_aprender_entrada([]) === null, 'sin datos');
$db = new OpgDbFalsaTecAprender();
$r = opg_tec_aprender_procesar(null, true, 5, $db);
$check($r['estado'] === 'sin_accion' && count($db->consultas) === 0, 'sin entrada no consulta');

// 6. Salida: log seguro dentro de alert(`...`) y clave CSRF en el formulario.
$js = opg_tec_aprender_js_literal("a`b \${x} \\ </script><img>");
$check(strpos($js, '</script') === false && preg_match('/(?<!\\\\)\$\{/', $js) === 0,'literal JS sin cierre de script ni interpolacion');
$check(preg_match('/(?<!\\\\)`/', $js) === 0, 'comilla invertida sin escapar');
$html = '<div><form method="post" action="/op/staff/tecnicas_aprender.php" id="f"><input name="x"></form><form method="get"></form></div>';
$out = opg_tec_aprender_inyectar_post_key($html, 'abc123');
$check(substr_count($out, 'name="my_post_key" value="abc123"') === 1, 'clave CSRF solo en el form post');
$check(strpos($out, 'id="f"><input type="hidden" name="my_post_key"') !== false, 'clave dentro del form');
$check(opg_tec_aprender_inyectar_post_key($out, 'otra') === $out, 'no duplica la clave');
$check(opg_tec_aprender_inyectar_post_key("<FORM METHOD=POST>", 'k') === '<FORM METHOD=POST><input type="hidden" name="my_post_key" value="k">', 'form en mayusculas');

// 7. La pagina usa la libreria, verify_post_check y el uid de la sesion, y conserva el aviso de #21.
$src = file_get_contents(__DIR__ . '/../../op/staff/tecnicas_aprender.php');
$check(strpos($src, 'verify_post_check(') !== false, 'la pagina debe llamar a verify_post_check');
$check(strpos($src, '$_POST["staff"]') === false && strpos($src, "\$_POST['staff']") === false, 'la pagina no debe leer staff del formulario');
$check(strpos($src, "opg_tec_aprender_procesar(") !== false, 'la pagina usa la libreria');
$check(strpos($src, "opg_pos_avisar('tecnica'") !== false && strpos($src, "'por_aprender'") !== false, 'aviso de #21 conservado');
$check(strpos($src, "fid='\$user_fid'") === false, 'SELECT del GET debe ir escapado');

if ($fallos) { echo "FALLOS:\n- " . implode("\n- ", $fallos) . "\n"; exit(1); }
echo "OK tecnicas aprender staff seguridad\n";
