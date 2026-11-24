<?php
/**
 * Pruebas puras (sin base de datos) de inc/plugins/lib/consumir.php (issue #32).
 *   php tests/consumir/consumir.php
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', realpath(__DIR__ . '/../../') . '/');
require MYBB_ROOT . 'inc/plugins/lib/consumir.php';

$GLOBALS['fallos'] = 0;
$GLOBALS['total'] = 0;
function prueba($nombre, $obtenido, $esperado)
{
    $GLOBALS['total']++;
    if ($obtenido !== $esperado) {
        $GLOBALS['fallos']++;
        echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
    }
}
function ids($etiquetas)
{
    return array_map(function ($e) { return $e['id']; }, $etiquetas);
}

// ── Buscar etiquetas ─────────────────────────────────────────────────────────────────────────────────────────────────
prueba('una etiqueta', ids(opg_consumir_etiquetas('Bebo [consumir=POC001] ya')), array('POC001'));
prueba('varias, en orden', ids(opg_consumir_etiquetas('[consumir=A1] y [CONSUMIR=b2] y [consumir= C3 ]')), array('A1', 'b2', 'C3'));
prueba('sin etiquetas', opg_consumir_etiquetas('[consumido=1] [consumibleinvalido=X] [consumir=]'), array());
prueba('no cruza saltos de linea ni corchetes', ids(opg_consumir_etiquetas("[consumir=A\nB] [consumir=[x]] [consumir=OK]")), array('OK'));
$e = opg_consumir_etiquetas('ab[consumir=X1]');
prueba('posicion y largo', array($e[0]['inicio'], $e[0]['largo']), array(2, 13));

// ── [quote] y [code] no se procesan ──────────────────────────────────────────────────────────────────────────────────
prueba('dentro de quote no cuenta', ids(opg_consumir_etiquetas('[quote]Dijo [consumir=Q1][/quote] fuera [consumir=F1]')), array('F1'));
prueba("dentro de quote='Nombre' pid=... no cuenta", ids(opg_consumir_etiquetas("[quote='Eddy' pid='5' dateline='1']x [consumir=Q1][/quote][consumir=F1]")), array('F1'));
prueba('quote anidado', ids(opg_consumir_etiquetas('[quote]a [quote]b [consumir=Q2][/quote] c [consumir=Q3][/quote] [consumir=F2]')), array('F2'));
prueba('dentro de code no cuenta', ids(opg_consumir_etiquetas('[code][consumir=C1][/code][consumir=F3]')), array('F3'));
prueba('dentro de php no cuenta', ids(opg_consumir_etiquetas('[php]$x = "[consumir=C2]";[/php] [consumir=F4]')), array('F4'));
prueba('un [/quote] dentro de code no cierra el quote', ids(opg_consumir_etiquetas('[quote][code][/quote][/code] [consumir=Q4][/quote][consumir=F5]')), array('F5'));
prueba('code sin cerrar no protege (MyBB tampoco lo muestra como codigo)', ids(opg_consumir_etiquetas('[code] [consumir=X9]')), array('X9'));
prueba('mayusculas en las etiquetas de bloque', ids(opg_consumir_etiquetas('[QUOTE][consumir=Q5][/Quote][CODE][consumir=C5][/code][consumir=F6]')), array('F6'));

// ── Etiquetas nuevas en una edicion ──────────────────────────────────────────────────────────────────────────────────
prueba('crear: todas son nuevas', ids(opg_consumir_etiquetas_nuevas('[consumir=A][consumir=A]', '')), array('A', 'A'));
prueba('editar sin cambios: ninguna nueva', opg_consumir_etiquetas_nuevas('x [consumir=A] y', 'x [consumir=A]'), array());
prueba('editar: la ya reemplazada no vuelve a contar', opg_consumir_etiquetas_nuevas('[consumido=1] texto nuevo', '[consumido=1]'), array());
$n = opg_consumir_etiquetas_nuevas('[consumir=A] medio [consumir=a] fin [consumir=B]', '[consumir=A]');
prueba('editar: solo las que sobran respecto al mensaje anterior', ids($n), array('a', 'B'));
prueba('editar: la nueva es la segunda (posicion)', $n[0]['inicio'], strlen('[consumir=A] medio '));
prueba('editar: una cruda que estaba citada no cuenta como previa', ids(opg_consumir_etiquetas_nuevas('[consumir=A]', '[quote][consumir=A][/quote]')), array('A'));

// ── Reemplazos ───────────────────────────────────────────────────────────────────────────────────────────────────────
$msg = "Uso [consumir=A] y [consumir=B].";
$et = opg_consumir_etiquetas($msg);
$r = opg_consumir_reemplazar($msg, array(
    array('inicio' => $et[0]['inicio'], 'largo' => $et[0]['largo'], 'texto' => '[consumido=4]'),
    array('inicio' => $et[1]['inicio'], 'largo' => $et[1]['largo'], 'texto' => '[consumibleinvalido=B]'),
));
prueba('reemplazos por posicion', $r, 'Uso [consumido=4] y [consumibleinvalido=B].');
$raro = "O'Brien dijo \"x\" en a\\b\r\nlinea 2\n[consumir=A]";
$et = opg_consumir_etiquetas($raro);
prueba('el resto del texto queda identico', opg_consumir_reemplazar($raro, array(array('inicio' => $et[0]['inicio'], 'largo' => $et[0]['largo'], 'texto' => '[consumido=1]'))),
    "O'Brien dijo \"x\" en a\\b\r\nlinea 2\n[consumido=1]");

// ── Nota al mostrar ──────────────────────────────────────────────────────────────────────────────────────────────────
$nota = opg_consumir_nota_html('sin_consumir', 'Poción <b>"grande"</b>');
prueba('nota sin consumir: texto', strpos($nota, 'Objeto sin consumir: «Poción &lt;b&gt;&quot;grande&quot;&lt;/b&gt;»') !== false, true);
prueba('nota sin consumir: sin HTML del nombre', strpos($nota, '<b>'), false);
$nota = opg_consumir_nota_html('invalido', "O'Brien");
prueba('nota invalido: texto', strpos($nota, 'No se pudo consumir «O&#039;Brien»: no estaba en el inventario') !== false, true);
preg_match('/style="([^"]*)"/', $nota, $estilo);
prueba('nota: cada color es un token --opg- (con su valor como respaldo)', preg_match_all('/#[0-9a-f]{3,6}\b/i', $estilo[1]) === preg_match_all('/var\(--opg-[a-z0-9-]+, #[0-9a-f]{3,6}\)/i', $estilo[1]), true);
prueba('nota: sin clases', strpos($nota, 'class='), false);
prueba('nota: se ajusta en movil', strpos($nota, 'overflow-wrap:anywhere') !== false && strpos($nota, 'max-width:100%') !== false, true);

// HTML ya procesado por el parser de MyBB (los corchetes quedan, el resto escapado)
$html = 'Hola [consumir=POC001] y [consumibleinvalido=X&amp;1] y [consumir=NADA]<div class="codeblock"><code>[consumir=POC001]</code></div>';
prueba('ids en el HTML (fuera de <code>)', opg_consumir_ids_en_html($html), array('POC001', 'X&1', 'NADA'));
$salida = opg_consumir_renderizar_notas($html, array('POC001' => 'Poción', 'X&1' => 'Equis'));
prueba('nota con nombre', strpos($salida, 'Objeto sin consumir: «Poción»') !== false, true);
prueba('invalido con nombre y entidad decodificada', strpos($salida, 'No se pudo consumir «Equis»: no estaba en el inventario') !== false, true);
prueba('sin nombre: se muestra el codigo', strpos($salida, 'Objeto sin consumir: «NADA»') !== false, true);
prueba('dentro de <code> se respeta', strpos($salida, '<code>[consumir=POC001]</code>') !== false, true);
prueba('no queda ninguna etiqueta cruda fuera del codigo', preg_match('/\[(consumir|consumibleinvalido)=/', str_replace('<code>[consumir=POC001]</code>', '', $salida)), 0);
prueba('mayusculas: misma clave', opg_consumir_renderizar_notas('[consumir=poc001]', array('POC001' => 'Poción')) !== '[consumir=poc001]', true);
prueba('sin etiquetas: igual', opg_consumir_renderizar_notas('<p>Nada</p>', array()), '<p>Nada</p>');

// ── HTML del objeto guardado ─────────────────────────────────────────────────────────────────────────────────────────
$obj = array('objeto_id' => 'POC001', 'nombre' => 'Poción <script>', 'subcategoria' => 'Consumibles', 'tier' => 2,
    'descripcion' => "Linea 1\nLinea <i>2</i>", 'imagen_id' => 3, 'imagen_avatar' => "x' onerror='alert(1)",
    'requisitos' => '<b>Req</b>', 'escalado' => '<u>Esc</u>', 'dano' => '<s>Dano</s>', 'efecto' => '<img src=x>');
$h = opg_consumir_html_objeto($obj, "O'Brien", 2);
prueba('html: nombre escapado', strpos($h, '<script>'), false);
prueba('html: requisitos escapados', array(strpos($h, '<b>Req</b>'), strpos($h, '&lt;b&gt;Req&lt;/b&gt;') !== false), array(false, true));
prueba('html: escalado y dano escapados', array(strpos($h, '<u>Esc</u>'), strpos($h, '<s>Dano</s>')), array(false, false));
prueba('html: efecto escapado', array(strpos($h, '<img src=x>'), strpos($h, '&lt;img src=x&gt;') !== false), array(false, true));
prueba('html: imagen no rompe el atributo', strpos($h, "onerror='"), false);
prueba('html: usuario escapado', strpos($h, 'O&#039;Brien') !== false, true);
prueba('html: cantidad restante', strpos($h, '>2</strong>') !== false, true);
prueba('html: descripcion con saltos', strpos($h, 'Linea 1<br />') !== false, true);
$h = opg_consumir_html_objeto(array_merge($obj, array('imagen_avatar' => '', 'nombre' => 'Poción')), 'Eddy', 0);
prueba('html: imagen por defecto del catalogo', strpos($h, "/images/op/iconos/consumibles_3_One_Piece_Gaiden_Foro_Rol.jpg") !== false, true);
$h = opg_consumir_html_objeto(array_merge($obj, array('imagen_avatar' => 'javascript:alert(1)')), 'Eddy', 0);
prueba('html: imagen con esquema no permitido se descarta', strpos($h, 'javascript:'), false);

echo $GLOBALS['fallos'] === 0 ? "OK consumir: {$GLOBALS['total']} pruebas, 0 fallos\n" : "CON FALLOS consumir: {$GLOBALS['total']} pruebas, {$GLOBALS['fallos']} fallos\n";
exit($GLOBALS['fallos'] ? 1 : 0);
