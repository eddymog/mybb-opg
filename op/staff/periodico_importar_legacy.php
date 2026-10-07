<?php

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'periodico_importar_legacy.php');
require_once './../../global.php';
require_once './../functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/periodico.php';

// Cambiar a false después de una importación revisada para cerrar esta ruta.
define('OPG_PERIODICO_IMPORT_LEGACY_HABILITADO', true);

global $db, $mybb, $templates;

if (!opg_periodico_puede_editar((int) $mybb->user['uid'])) {
    eval("\$page = \"" . $templates->get('sin_permisos') . "\";");
    output_page($page);
    exit;
}
if (!OPG_PERIODICO_IMPORT_LEGACY_HABILITADO) { error('El importador histórico está desactivado.'); }

function opg_periodico_legacy_clase($nodo, $clase)
{
    return strpos(' ' . $nodo->getAttribute('class') . ' ', ' ' . $clase . ' ') !== false;
}

function opg_periodico_legacy_bbcode($nodo)
{
    $texto = '';
    foreach ($nodo->childNodes as $hijo) {
        if ($hijo->nodeType === XML_TEXT_NODE) { $texto .= $hijo->nodeValue; continue; }
        if ($hijo->nodeType !== XML_ELEMENT_NODE) { continue; }
        $contenido = opg_periodico_legacy_bbcode($hijo);
        $tag = strtolower($hijo->nodeName);
        if ($tag === 'strong' || $tag === 'b') { $texto .= '[b]' . $contenido . '[/b]'; }
        elseif ($tag === 'em' || $tag === 'i') { $texto .= '[i]' . $contenido . '[/i]'; }
        elseif ($tag === 'br') { $texto .= "\n"; }
        elseif ($tag === 'p') { $texto .= trim($contenido) . "\n\n"; }
        elseif ($tag === 'h3') { $texto .= '[b]' . trim($contenido) . "[/b]\n\n"; }
        elseif ($tag === 'div' && opg_periodico_legacy_clase($hijo, 'info-box')) { $texto .= '[quote]' . trim($contenido) . "[/quote]\n\n"; }
        else { $texto .= $contenido; }
    }
    return preg_replace("/[ \t]+\n/", "\n", $texto);
}

function opg_periodico_legacy_primero($xpath, $consulta, $contexto)
{
    $nodos = $xpath->query($consulta, $contexto);
    return $nodos && $nodos->length ? $nodos->item(0) : null;
}

function opg_periodico_legacy_payload($numero, $slug, $titulo, $subtitulo)
{
    $ruta = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_periodico.html';
    if (!is_readable($ruta)) { throw new RuntimeException('No se encontró la copia local de op_periodico.html.'); }
    $html = file_get_contents($ruta);
    $anterior = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($anterior);
    $xpath = new DOMXPath($dom);
    $paginas_html = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' newspaper-page ')]");
    if (!$paginas_html || $paginas_html->length !== 10) { throw new RuntimeException('La estructura legacy no contiene las diez páginas esperadas.'); }

    $datos = opg_periodico_edicion_vacia();
    $datos['numero'] = (int) $numero;
    $datos['slug'] = $slug;
    $datos['titulo'] = $titulo;
    $datos['subtitulo'] = $subtitulo;
    $datos['firma_visible'] = 'El Gaviotazo';
    $datos['paginas'] = array();

    foreach ($paginas_html as $indice => $pagina_html) {
        $titulo_nodo = opg_periodico_legacy_primero($xpath, '(.//h2)[1]', $pagina_html);
        $bajada_nodo = opg_periodico_legacy_primero($xpath, "(.//*[contains(concat(' ', normalize-space(@class), ' '), ' byline ') or contains(concat(' ', normalize-space(@class), ' '), ' noticia-subtitulo ')])[1]", $pagina_html);
        $imagen_nodo = opg_periodico_legacy_primero($xpath, "(.//img[contains(@class, 'noticia-imagen')])[1]", $pagina_html);
        $pie_nodo = opg_periodico_legacy_primero($xpath, "(.//*[contains(concat(' ', normalize-space(@class), ' '), ' imagen-caption ')])[1]", $pagina_html);
        $texto_nodo = opg_periodico_legacy_primero($xpath, "(.//*[contains(concat(' ', normalize-space(@class), ' '), ' noticia-texto ')])[1]", $pagina_html);
        $clases = $pagina_html->getAttribute('class');
        preg_match('/\\btheme-([a-z0-9-]+)/', $clases, $coincidencia);
        $contenido = array(
            'titular' => $titulo_nodo ? trim($titulo_nodo->textContent) : '',
            'bajada' => $bajada_nodo ? trim($bajada_nodo->textContent) : '',
            'cuerpo' => $texto_nodo ? trim(opg_periodico_legacy_bbcode($texto_nodo)) : '',
            'imagen' => $imagen_nodo ? trim($imagen_nodo->getAttribute('src')) : '',
            'pie' => $pie_nodo ? trim($pie_nodo->textContent) : '',
            'autor' => '',
            'etiqueta' => $indice === 0 ? 'Noticia de portada' : ''
        );
        if ($indice === 0) { $datos['portada'] = $contenido['imagen']; }
        $datos['paginas'][] = array('titulo' => $contenido['titular'], 'tema' => isset($coincidencia[1]) ? $coincidencia[1] : '', 'bloques' => array(array('tipo' => $indice === 0 ? 'portada' : 'noticia', 'contenido' => $contenido)));
    }
    return $datos;
}

$numero = $mybb->get_input('numero', MyBB::INPUT_INT) ?: 1;
$slug = $mybb->get_input('slug', MyBB::INPUT_STRING) ?: 'edicion-south-blue-725';
$titulo = $mybb->get_input('titulo', MyBB::INPUT_STRING) ?: 'EL CIELO ARDE';
$subtitulo = $mybb->get_input('subtitulo', MyBB::INPUT_STRING) ?: 'Isla Cliff, Westblue — Edición Especial';
$errores = array();
$resumen = '';

try {
    $datos = opg_periodico_legacy_payload($numero, $slug, $titulo, $subtitulo);
    $resumen = count($datos['paginas']) . ' páginas y ' . count($datos['paginas']) . ' bloques editoriales detectados.';
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));
        $validado = opg_periodico_validar_payload($datos, $errores);
        if ($validado && opg_periodico_conflicto($db, $validado)) {
            $errores[] = 'El número o la dirección web ya pertenece a otra edición.';
        } elseif ($validado) {
            $id = opg_periodico_guardar($db, 0, $validado, (int) $mybb->user['uid']);
            opg_periodico_auditar($db, $id, (int) $mybb->user['uid'], 'importacion_legacy', array(), array('origen' => 'op_periodico.html', 'paginas' => count($validado['paginas'])));
            redirect('periodico_editar.php?id=' . $id, 'Edición histórica importada como borrador. Revísala antes de publicarla.');
        }
    }
} catch (RuntimeException $e) {
    $errores[] = $e->getMessage();
}

$errores_html = $errores ? '<div class="periodico-alerta">' . implode('<br>', array_map('opg_periodico_esc', $errores)) . '</div>' : '';
$slug_esc = opg_periodico_esc($slug);
$titulo_esc = opg_periodico_esc($titulo);
$subtitulo_esc = opg_periodico_esc($subtitulo);
$post_key = generate_post_check();
$page = '<!doctype html><html><head><title>Importar periódico histórico</title>'
    . $headerinclude
    . '<link rel="stylesheet" href="' . opg_periodico_esc($mybb->asset_url) . '/jscripts/periodico_editor.css?v=9">'
    . '</head><body>' . $header
    . '<div class="indice periodico-staff"><div class="mainBackground"><div class="secondBackground"><div class="thirdBackground">'
    . '<header class="periodico-staff__cabecera"><p>Herramienta de una sola ejecución</p><h1>Importar edición histórica</h1><span>Lee la copia local del periódico actual y crea un borrador para revisión.</span></header>'
    . $errores_html
    . '<section class="opg-card periodico-nueva"><p class="periodico-editor__ayuda">' . opg_periodico_esc($resumen) . ' No publica ni modifica el template estático.</p>'
    . '<form method="post" action="periodico_importar_legacy.php" class="periodico-nueva__form">'
    . '<input type="hidden" name="my_post_key" value="' . opg_periodico_esc($post_key) . '">'
    . '<label class="af-field"><span>Número histórico</span><input type="number" name="numero" min="1" value="' . (int) $numero . '" required></label>'
    . '<label class="af-field"><span>Dirección web</span><input type="text" name="slug" value="' . $slug_esc . '" required></label>'
    . '<label class="af-field"><span>Titular</span><input type="text" name="titulo" value="' . $titulo_esc . '" required></label>'
    . '<label class="af-field"><span>Subtítulo</span><input type="text" name="subtitulo" value="' . $subtitulo_esc . '"></label>'
    . '<button class="btn-op btn-op--primario" type="submit">Importar como borrador</button></form></section>'
    . '</div></div></div></div>' . $footer . '</body></html>';
output_page($page);
