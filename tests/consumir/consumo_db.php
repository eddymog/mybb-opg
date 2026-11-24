<?php
/**
 * [consumir] con el plugin real y MySQL real (issue #32): crear y editar, contador, descuento atomico, devolucion si
 * falla el guardado, auditoria, escapes, [quote]/[code], borradores y la nota al mostrar en posts viejos.
 *   OPG_TEST_DB_PORT=33332 php tests/consumir/consumo_db.php
 */
require __DIR__ . '/harness_plugins.php';   // carga bootstrap_db.php: necesita el MySQL de pruebas

/** $db que hace fallar las sentencias que coincidan con $patron (para simular un fallo de un paso posterior). */
class CsDbFalla extends OpgNotifTestDb
{
    public $patron = null;
    public function query($sql, $hide_errors = 0, $write_query = 0)
    {
        if ($this->patron !== null && preg_match($this->patron, $sql)) {
            $this->sentencias[] = $sql;
            if (!$hide_errors) {
                fwrite(STDERR, "ERROR SQL simulado sin hide_errors (MyBB detendria la pagina)\n$sql\n");
                exit(3);
            }
            return false;
        }
        return parent::query($sql, $hide_errors, $write_query);
    }
}

$db = cs_preparar('opg_consumir_consumo');
nt_registro_iniciar();
cs_cargar_plugins(array('BBCustom_consumir'));

cs_objeto('POC001', 'Poción de vida');
cs_objeto('POC002', "Té de O'Brien");
cs_objeto('BOM001', 'Bomba <b>"humo"</b>', array('requisitos' => '<i>Nivel 5</i>', 'efecto' => '<script>x</script>', 'imagen_avatar' => "x' onerror='y"));
$autor = 7;
$tid = 50;

// ── Crear: consume, reemplaza y deja todo lo demas ───────────────────────────────────────────────────────────────────
cs_inventario($autor, 'POC001', 3);
$pid = cs_crear_post($autor, $tid, 'Bebo [consumir=POC001] y [consumir=poc001].');
nt_prueba('crear: dos etiquetas, dos contadores', cs_mensaje($pid), 'Bebo [consumido=1] y [consumido=2].');
nt_prueba('crear: dos unidades menos', cs_cantidad($autor, 'POC001'), 1);
nt_prueba('crear: dos filas en mybb_op_consumir', (int)nt_valor("SELECT COUNT(*) FROM mybb_op_consumir WHERE pid = $pid"), 2);
nt_prueba('crear: el objeto se guarda con su codigo de catalogo', nt_valor("SELECT objeto_id FROM mybb_op_consumir WHERE pid = $pid AND counter = 2"), 'POC001');
nt_ok(strpos(nt_valor("SELECT content FROM mybb_op_consumir WHERE pid = $pid AND counter = 2"), 'Cantidad restante: <strong style=\'color: white;\'>1</strong>') !== false, 'crear: la segunda muestra la cantidad restante 1');

// Auditoria
$aud = nt_una("SELECT * FROM mybb_op_audit_general WHERE categoria = '[Consumir]' ORDER BY id DESC LIMIT 1");
nt_ok($aud && $aud['uid'] === (string)$autor && strpos($aud['log'], 'POC001') !== false && strpos($aud['log'], 'Cantidad restante: 1') !== false, 'auditoria: categoria [Consumir], objeto y cantidad restante');
nt_prueba('auditoria: una entrada por consumo', (int)nt_valor("SELECT COUNT(*) FROM mybb_op_audit_general WHERE categoria = '[Consumir]'"), 2);

// ── Ultima unidad: la fila se borra; sin unidades: invalido ──────────────────────────────────────────────────────────
$pid2 = cs_crear_post($autor, $tid, '[consumir=POC001] [consumir=POC001]');
nt_prueba('ultima unidad: una consumida y la otra invalida', cs_mensaje($pid2), '[consumido=1] [consumibleinvalido=POC001]');
nt_prueba('ultima unidad: la fila del inventario se elimina', cs_cantidad($autor, 'POC001'), null);
$pid3 = cs_crear_post($autor, $tid, 'Sin objeto [consumir=NOEXISTE] ni unidades [consumir=POC002]');
nt_prueba('codigo inexistente o sin unidades: invalido (aunque no se consuma nada se guarda el cambio)', cs_mensaje($pid3), 'Sin objeto [consumibleinvalido=NOEXISTE] ni unidades [consumibleinvalido=POC002]');

// ── Sin doble escapatoria ────────────────────────────────────────────────────────────────────────────────────────────
cs_inventario($autor, 'POC002', 5);
$texto = "O'Brien dijo \"x\" en a\\b\r\nsegunda linea\nTercera [consumir=POC002] fin \\' \\n";
$pid4 = cs_crear_post($autor, $tid, $texto);
nt_prueba('escapes: el texto se guarda identico a lo escrito', cs_mensaje($pid4), str_replace('[consumir=POC002]', '[consumido=1]', $texto));
cs_editar_post($pid4, cs_mensaje($pid4) . "\nY otra: [consumir=POC002] de O'Brien");
nt_prueba('escapes: tambien al editar', cs_mensaje($pid4), str_replace('[consumir=POC002]', '[consumido=1]', $texto) . "\nY otra: [consumido=2] de O'Brien");

// ── Editar ───────────────────────────────────────────────────────────────────────────────────────────────────────────
cs_inventario($autor, 'POC001', 10);
$pid5 = cs_crear_post($autor, $tid, 'Post sin etiquetas');
cs_editar_post($pid5, 'Post editado [consumir=POC001]');
nt_prueba('editar: una etiqueta anadida al editar consume', array(cs_mensaje($pid5), cs_cantidad($autor, 'POC001')), array('Post editado [consumido=1]', 9));
cs_editar_post($pid5, 'Post editado [consumido=1] otra vez sin nuevas');
nt_prueba('reeditar sin etiquetas nuevas: no consume', array(cs_mensaje($pid5), cs_cantidad($autor, 'POC001')), array('Post editado [consumido=1] otra vez sin nuevas', 9));
cs_editar_post($pid5, '[consumido=1] [consumir=POC001] [consumir=POC001]');
nt_prueba('editar: N sigue al mayor contador del post', cs_mensaje($pid5), '[consumido=1] [consumido=2] [consumido=3]');
nt_prueba('editar: dos unidades mas', cs_cantidad($autor, 'POC001'), 7);
cs_editar_post($pid5, '[consumido=3] solo queda esta');
cs_editar_post($pid5, '[consumido=3] [consumir=POC001]');
nt_prueba('editar: aunque se borren las anteriores, el contador sigue al mayor de mybb_op_consumir', cs_mensaje($pid5), '[consumido=3] [consumido=4]');

// Edicion rapida (sin uid en los datos) y edicion de otra persona: consume del autor y lo audita
cs_editar_post($pid5, '[consumido=3] [consumido=4] [consumir=POC001]', 99, true);
nt_prueba('edicion rapida por otra persona: consume del inventario del autor', array(cs_mensaje($pid5), cs_cantidad($autor, 'POC001'), cs_cantidad(99, 'POC001')), array('[consumido=3] [consumido=4] [consumido=5]', 5, null));
$aud = nt_una("SELECT * FROM mybb_op_audit_general WHERE categoria = '[Consumir]' ORDER BY id DESC LIMIT 1");
nt_ok($aud['uid'] === (string)$autor && strpos($aud['log'], 'usuario 99') !== false, 'edicion por otra persona: la auditoria es del autor y nombra a quien edito');

// Post viejo con la etiqueta cruda: editarlo no la consume; anadir otra si
$pid6 = cs_crear_post($autor, $tid, 'viejo');
nt_sql("UPDATE mybb_posts SET message = 'Viejo [consumir=POC001] sin procesar' WHERE pid = $pid6");
cs_editar_post($pid6, 'Viejo [consumir=POC001] sin procesar, corregido');
nt_prueba('post viejo: la etiqueta cruda que ya estaba no consume al editar', array(cs_mensaje($pid6), cs_cantidad($autor, 'POC001')), array('Viejo [consumir=POC001] sin procesar, corregido', 5));
cs_editar_post($pid6, 'Viejo [consumir=POC001] sin procesar, corregido [consumir=POC001]');
nt_prueba('post viejo: una etiqueta mas si consume', array(cs_mensaje($pid6), cs_cantidad($autor, 'POC001')), array('Viejo [consumir=POC001] sin procesar, corregido [consumido=1]', 4));

// ── [quote] y [code] ─────────────────────────────────────────────────────────────────────────────────────────────────
$msg = "[quote='Otro' pid='1' dateline='1']Uso [consumir=POC001][/quote]\n[code][consumir=POC001][/code] y [php]\$a='[consumir=POC001]';[/php]";
$pid7 = cs_crear_post($autor, $tid, $msg);
nt_prueba('quote/code/php: no se procesa ni consume', array(cs_mensaje($pid7), cs_cantidad($autor, 'POC001')), array($msg, 4));
cs_editar_post($pid7, $msg . ' fuera [consumir=POC001]');
nt_prueba('quote/code al editar: solo la de fuera', array(cs_mensaje($pid7), cs_cantidad($autor, 'POC001')), array($msg . ' fuera [consumido=1]', 3));

// ── Borrador: no consume hasta que se publica ────────────────────────────────────────────────────────────────────────
$pid8 = cs_crear_post($autor, $tid, 'Borrador [consumir=POC001]', 'Eddy', -2);
nt_prueba('borrador: no consume', array(cs_mensaje($pid8), cs_cantidad($autor, 'POC001')), array('Borrador [consumir=POC001]', 3));
nt_sql("UPDATE mybb_posts SET visible = 1 WHERE pid = $pid8");
$h = new CsPostHandler();
$h->return_values = array('pid' => $pid8);
$h->pid = $pid8;
$plugins->run_hooks('datahandler_post_insert_post_end', $h);   // al publicarlo MyBB no rellena post_insert_data
nt_prueba('borrador publicado: consume', array(cs_mensaje($pid8), cs_cantidad($autor, 'POC001')), array('Borrador [consumido=1]', 2));

// ── Primer post de un tema ───────────────────────────────────────────────────────────────────────────────────────────
$pid9 = cs_crear_post($autor, 51, 'Tema nuevo [consumir=POC001]', 'Eddy', 1, true);
nt_prueba('primer post de un tema: consume', array(cs_mensaje($pid9), cs_cantidad($autor, 'POC001')), array('Tema nuevo [consumido=1]', 1));

// ── HTML guardado escapado ───────────────────────────────────────────────────────────────────────────────────────────
cs_inventario($autor, 'BOM001', 2);
$pid10 = cs_crear_post($autor, $tid, '[consumir=BOM001]', "Ed'dy <b>");
$html = nt_valor("SELECT content FROM mybb_op_consumir WHERE pid = $pid10");
nt_ok(strpos($html, '<b>"humo"</b>') === false && strpos($html, '&lt;b&gt;&quot;humo&quot;&lt;/b&gt;') !== false, 'html guardado: nombre escapado');
nt_ok(strpos($html, '<i>Nivel 5</i>') === false && strpos($html, '<script>') === false, 'html guardado: requisitos y efecto escapados');
nt_ok(strpos($html, "onerror='") === false, 'html guardado: imagen escapada');
nt_ok(strpos($html, 'Ed&#039;dy &lt;b&gt;') !== false, 'html guardado: usuario escapado');

// ── Fallo al guardar: se devuelve la unidad y la etiqueta no queda consumida ─────────────────────────────────────────
$base = $GLOBALS['nt_base'];
$db_falla = new CsDbFalla($base);
$db_falla->patron = '/INSERT INTO mybb_op_consumir/';
$GLOBALS['db'] = $db_falla;
$db = $db_falla;
cs_inventario($autor, 'POC002', 1);
$pid11 = cs_crear_post($autor, $tid, 'Falla [consumir=POC002]');
nt_prueba('fallo al guardar: la etiqueta queda sin marcar como consumida', cs_mensaje($pid11), 'Falla [consumir=POC002]');
nt_prueba('fallo al guardar: la unidad vuelve al inventario (y la fila no se borra)', cs_cantidad($autor, 'POC002'), 1);
nt_prueba('fallo al guardar: sin auditoria de consumo', (int)nt_valor("SELECT COUNT(*) FROM mybb_op_audit_general WHERE log LIKE '%post $pid11 %'"), 0);
nt_ok(strpos(nt_registro_leer(), 'Se devolvio la unidad') !== false, 'fallo al guardar: queda registrado en el log de errores');
// Devolver cuando la fila ya no existe (otro proceso la borro al quedarse en 0): se recrea con 1
nt_sql("DELETE FROM mybb_op_inventario WHERE uid = $autor AND objeto_id = 'POC002'");
opg_consumir_devolver($db, $autor, 'POC002');
nt_prueba('devolver sin fila: la recrea con una unidad', cs_cantidad($autor, 'POC002'), 1);
$db_falla->patron = null;

// ── Equipamiento: el objeto consumido sale del equipamiento ──────────────────────────────────────────────────────────
cs_inventario($autor, 'POC001', 1);
nt_sql("INSERT INTO mybb_op_equipamiento_personaje (tid, uid, equipamiento) VALUES ($tid, $autor, '{\"ropa\":\"\",\"bolsa\":\"\",\"espacios\":[{\"objetoId\":\"POC001\"},{\"objetoId\":\"POC002\"}]}')");
nt_sql("INSERT INTO mybb_op_fichas (fid, equipamiento) VALUES ($autor, '{\"bolsa\":\"POC001\"}')");
cs_crear_post($autor, $tid, '[consumir=POC001]');
nt_prueba('equipamiento del tema: se quita el objeto', json_decode(nt_valor("SELECT equipamiento FROM mybb_op_equipamiento_personaje WHERE uid = $autor"), true)['espacios'], array(array('objetoId' => 'POC002')));
nt_prueba('equipamiento de la ficha: se quita el objeto', json_decode(nt_valor("SELECT equipamiento FROM mybb_op_fichas WHERE fid = $autor"), true)['bolsa'], '');

// ── Mostrar: nota en vez de codigo, tambien en posts viejos sin tocar datos ──────────────────────────────────────────
nt_sql("INSERT INTO mybb_posts (tid, uid, username, message) VALUES ($tid, $autor, 'Eddy', 'Viejo [consumir=POC002] y [consumibleinvalido=POC001] y [consumir=ZZZ999] [consumido=7]')");
$viejo = (int)mysqli_insert_id($db->link);
$antes = cs_mensaje($viejo);
$v = cs_ver($viejo, 3);
nt_ok(strpos($v, "Objeto sin consumir: «Té de O&#039;Brien»") !== false, 'mostrar: cruda con nombre del objeto (escapado)');
nt_ok(strpos($v, 'No se pudo consumir «Poción de vida»: no estaba en el inventario') !== false, 'mostrar: invalida con nombre');
nt_ok(strpos($v, 'Objeto sin consumir: «ZZZ999»') !== false, 'mostrar: sin objeto en el catalogo, el codigo');
nt_prueba('mostrar: no queda codigo crudo', preg_match('/\[(consumir|consumibleinvalido)=/', $v), 0);
nt_prueba('mostrar: no se modifican los datos', cs_mensaje($viejo), $antes);
nt_prueba('mostrar: no consume', cs_cantidad($autor, 'POC002'), 1);
// El HTML que entrega el parser de MyBB (con el codigo de un [code] dentro de <code>)
nt_sql("INSERT INTO mybb_posts (tid, uid, username, message) VALUES ($tid, $autor, 'Eddy', 'x')");
$p = (int)mysqli_insert_id($db->link);
$v = cs_ver($p, 3, 'Uso [consumir=POC002]<div class="codeblock"><div class="body"><code>[consumir=POC002]</code></div></div>');
nt_ok(strpos($v, '<code>[consumir=POC002]</code>') !== false && substr_count($v, 'Objeto sin consumir') === 1, 'mostrar: dentro de un bloque de codigo se ve el codigo');
// Lo ya consumido se sigue mostrando
$v = cs_ver($pid, 3);
nt_ok(substr_count($v, 'ha sido consumido') === 2 && strpos($v, '[consumido=') === false, 'mostrar: [consumido=N] sigue mostrando el objeto');

nt_prueba('ninguna consulta sin hide_errors fallo (si no, la prueba habria terminado)', true, true);
nt_borrar();
nt_resumen('consumir consumo_db');
