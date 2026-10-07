<?php

function opg_periodico_puede_editar($uid)
{
    $uid = (int) $uid;
    return is_staff($uid) || is_narra($uid);
}

function opg_periodico_esc($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function opg_periodico_url_imagen_valida($url)
{
    $url = trim((string) $url);
    if ($url === '') { return true; }
    if (strpos($url, '/images/op/uploads/') === 0) { return true; }
    return filter_var($url, FILTER_VALIDATE_URL) && stripos($url, 'https://') === 0;
}

function opg_periodico_enlace_valido($enlace)
{
    $enlace = trim((string) $enlace);
    if ($enlace === '') { return true; }
    if (ctype_digit($enlace) && (int) $enlace > 0) { return true; }
    return strpos($enlace, '/') === 0 && strpos($enlace, '//') !== 0;
}

function opg_periodico_slug($texto)
{
    $texto = strtolower(trim((string) $texto));
    $texto = strtr($texto, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'));
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim(substr($texto, 0, 100), '-') ?: 'edicion';
}

function opg_periodico_bloque_vacio_portada()
{
    return array('tipo' => 'portada', 'contenido' => array('titular' => '', 'bajada' => '', 'cuerpo' => '', 'imagen' => '', 'pie' => '', 'autor' => '', 'etiqueta' => ''));
}

function opg_periodico_edicion_vacia()
{
    return array('titulo' => '', 'subtitulo' => '', 'numero' => '', 'slug' => '', 'portada' => '', 'tema' => 'clasico', 'firma_visible' => 'Big News Morgans', 'paginas' => array(array('titulo' => '', 'tema' => '', 'bloques' => array(opg_periodico_bloque_vacio_portada()))));
}

function opg_periodico_validar_bloque($bloque, &$errores)
{
    $tipos = array('portada', 'noticia', 'dos_columnas', 'recuadro', 'wanted', 'galeria', 'separador');
    $tipo = isset($bloque['tipo']) ? trim($bloque['tipo']) : '';
    $contenido = isset($bloque['contenido']) && is_array($bloque['contenido']) ? $bloque['contenido'] : array();
    if (!in_array($tipo, $tipos, true)) { $errores[] = 'Tipo de bloque no válido.'; return null; }
    $limites = array('titular' => 255, 'bajada' => 500, 'pie' => 500, 'autor' => 120, 'etiqueta' => 80, 'entradilla' => 500, 'titulo' => 255, 'cuerpo' => 30000, 'nombre' => 120, 'recompensa' => 120, 'enlace' => 500, 'imagen' => 500, 'enlace_relacionado' => 500);
    $normalizado = array();
    foreach ($limites as $clave => $limite) {
        $valor = isset($contenido[$clave]) ? trim((string) $contenido[$clave]) : '';
        if (my_strlen($valor) > $limite) { $errores[] = 'Un campo del bloque ' . $tipo . ' supera su longitud máxima.'; }
        $normalizado[$clave] = $valor;
    }
    if (!opg_periodico_url_imagen_valida($normalizado['imagen'])) { $errores[] = 'La imagen de un bloque no es válida.'; }
    if (!opg_periodico_enlace_valido($normalizado['enlace']) || !opg_periodico_enlace_valido($normalizado['enlace_relacionado'])) { $errores[] = 'Un enlace de bloque no es válido.'; }
    if ($tipo === 'wanted') {
        $normalizado['tipo'] = isset($contenido['tipo']) && $contenido['tipo'] === 'npc' ? 'npc' : 'jugador';
        $normalizado['estado'] = isset($contenido['estado']) && $contenido['estado'] === 'muerto' ? 'muerto' : 'vivo';
        if ($normalizado['nombre'] === '' || $normalizado['recompensa'] === '' || $normalizado['imagen'] === '') { $errores[] = 'Un wanted necesita nombre, recompensa e imagen.'; }
    }
    if ($tipo === 'galeria') {
        $normalizado['imagenes'] = isset($contenido['imagenes']) && is_array($contenido['imagenes']) ? array_values($contenido['imagenes']) : array();
        foreach ($normalizado['imagenes'] as $indice => $imagen) {
            $normalizado['imagenes'][$indice] = trim((string) $imagen);
            if (!opg_periodico_url_imagen_valida($normalizado['imagenes'][$indice])) { $errores[] = 'Una imagen de galería no es válida.'; }
        }
        $normalizado['imagenes'] = array_values(array_filter($normalizado['imagenes'], function ($imagen) { return $imagen !== ''; }));
        if (empty($normalizado['imagenes'])) { $errores[] = 'Una galería necesita al menos una imagen.'; }
    }
    if ($tipo === 'dos_columnas') {
        $normalizado['izquierda'] = isset($contenido['izquierda']) ? trim((string) $contenido['izquierda']) : '';
        $normalizado['derecha'] = isset($contenido['derecha']) ? trim((string) $contenido['derecha']) : '';
        if (my_strlen($normalizado['izquierda']) > 20000 || my_strlen($normalizado['derecha']) > 20000) { $errores[] = 'Una columna supera su longitud máxima.'; }
    }
    return array('tipo' => $tipo, 'contenido' => $normalizado);
}

function opg_periodico_validar_payload($payload, &$errores)
{
    $errores = array();
    if (!is_array($payload)) { $errores[] = 'Datos de edición inválidos.'; return null; }
    $datos = array();
    $datos['numero'] = isset($payload['numero']) ? (int) $payload['numero'] : 0;
    $datos['titulo'] = isset($payload['titulo']) ? trim((string) $payload['titulo']) : '';
    $datos['subtitulo'] = isset($payload['subtitulo']) ? trim((string) $payload['subtitulo']) : '';
    $datos['slug'] = opg_periodico_slug(isset($payload['slug']) && $payload['slug'] !== '' ? $payload['slug'] : $datos['titulo']);
    $datos['portada'] = isset($payload['portada']) ? trim((string) $payload['portada']) : '';
    $temas_permitidos = array('clasico', 'pirata', 'marine', 'revolucionario', 'cipher-pol', 'civil');
    $datos['tema'] = isset($payload['tema']) && in_array($payload['tema'], $temas_permitidos, true) ? $payload['tema'] : 'clasico';
    $datos['firma_visible'] = isset($payload['firma_visible']) ? trim((string) $payload['firma_visible']) : 'Big News Morgans';
    if ($datos['numero'] < 1) { $errores[] = 'El número de edición es obligatorio.'; }
    if ($datos['titulo'] === '' || my_strlen($datos['titulo']) > 255) { $errores[] = 'El título es obligatorio y debe tener menos de 256 caracteres.'; }
    if (my_strlen($datos['subtitulo']) > 500 || my_strlen($datos['firma_visible']) > 120) { $errores[] = 'Un metadato supera su longitud máxima.'; }
    if (!opg_periodico_url_imagen_valida($datos['portada'])) { $errores[] = 'La portada no es una URL válida.'; }
    $paginas = isset($payload['paginas']) && is_array($payload['paginas']) ? $payload['paginas'] : array();
    if (empty($paginas)) { $errores[] = 'La edición necesita al menos una página.'; }
    $datos['paginas'] = array(); $portadas = 0;
    foreach ($paginas as $pagina) {
        $pagina_normalizada = array('titulo' => isset($pagina['titulo']) ? trim((string) $pagina['titulo']) : '', 'tema' => isset($pagina['tema']) ? trim((string) $pagina['tema']) : '', 'bloques' => array());
        $bloques = isset($pagina['bloques']) && is_array($pagina['bloques']) ? $pagina['bloques'] : array();
        foreach ($bloques as $bloque) {
            $normalizado = opg_periodico_validar_bloque($bloque, $errores);
            if ($normalizado) { if ($normalizado['tipo'] === 'portada') { $portadas++; } $pagina_normalizada['bloques'][] = $normalizado; }
        }
        $datos['paginas'][] = $pagina_normalizada;
    }
    if ($portadas !== 1 || empty($datos['paginas'][0]['bloques']) || $datos['paginas'][0]['bloques'][0]['tipo'] !== 'portada') { $errores[] = 'La primera página debe empezar con una única portada.'; }
    return empty($errores) ? $datos : null;
}

function opg_periodico_cargar($db, $id)
{
    $id = (int) $id;
    $q = $db->simple_select('op_periodicos', '*', "id='{$id}'");
    $edicion = $db->fetch_array($q);
    if (!$edicion) { return null; }
    $edicion['paginas'] = array();
    $paginas = $db->simple_select('op_periodico_paginas', '*', "periodico_id='{$id}'", array('order_by' => 'orden'));
    while ($pagina = $db->fetch_array($paginas)) {
        $pagina['bloques'] = array();
        $bloques = $db->simple_select('op_periodico_bloques', '*', "pagina_id='{$pagina['id']}'", array('order_by' => 'orden'));
        while ($bloque = $db->fetch_array($bloques)) { $pagina['bloques'][] = array('tipo' => $bloque['tipo'], 'contenido' => json_decode($bloque['contenido_json'], true) ?: array()); }
        $edicion['paginas'][] = $pagina;
    }
    return $edicion;
}

function opg_periodico_conflicto($db, $datos, $id = 0)
{
    $id = (int) $id;
    $excluir = $id ? " AND id!='{$id}'" : '';
    $numero = (int) $datos['numero'];
    $slug = $db->escape_string($datos['slug']);
    $q = $db->simple_select('op_periodicos', 'id', "(numero='{$numero}' OR slug='{$slug}'){$excluir}", array('limit' => 1));
    return (bool) $db->fetch_array($q);
}

function opg_periodico_auditar($db, $id, $uid, $accion, $antes, $despues)
{
    $datos = $db->escape_string(json_encode(array('antes' => $antes, 'despues' => $despues), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $db->insert_query('op_periodico_auditoria', array('periodico_id' => (int) $id, 'uid' => (int) $uid, 'accion' => $db->escape_string($accion), 'detalle_json' => $datos, 'fecha' => date('Y-m-d H:i:s')));
}

function opg_periodico_borrar_contenido($db, $id)
{
    $id = (int) $id;
    $paginas = $db->simple_select('op_periodico_paginas', 'id', "periodico_id='{$id}'");
    while ($pagina = $db->fetch_array($paginas)) {
        $db->delete_query('op_periodico_bloques', "pagina_id='" . (int) $pagina['id'] . "'");
    }
    $db->delete_query('op_periodico_paginas', "periodico_id='{$id}'");
}

function opg_periodico_guardar($db, $id, $datos, $uid)
{
    $ahora = date('Y-m-d H:i:s'); $id = (int) $id;
    $antes = $id ? opg_periodico_cargar($db, $id) : null;
    $fila = array('slug' => $db->escape_string($datos['slug']), 'numero' => $datos['numero'], 'titulo' => $db->escape_string($datos['titulo']), 'subtitulo' => $db->escape_string($datos['subtitulo']), 'portada' => $db->escape_string($datos['portada']), 'tema' => $db->escape_string($datos['tema']), 'firma_visible' => $db->escape_string($datos['firma_visible']), 'fecha_actualizacion' => $ahora);
    $db->write_query('START TRANSACTION');
    if ($id) { $db->update_query('op_periodicos', $fila, "id='{$id}'"); opg_periodico_borrar_contenido($db, $id); }
    else { $fila['estado'] = 'borrador'; $fila['autor_uid'] = (int) $uid; $fila['fecha_creacion'] = $ahora; $id = (int) $db->insert_query('op_periodicos', $fila); }
    foreach ($datos['paginas'] as $orden_pagina => $pagina) {
        $pagina_id = (int) $db->insert_query('op_periodico_paginas', array('periodico_id' => $id, 'orden' => $orden_pagina + 1, 'titulo' => $db->escape_string($pagina['titulo']), 'tema' => $db->escape_string($pagina['tema'])));
        foreach ($pagina['bloques'] as $orden_bloque => $bloque) { $db->insert_query('op_periodico_bloques', array('pagina_id' => $pagina_id, 'orden' => $orden_bloque + 1, 'tipo' => $db->escape_string($bloque['tipo']), 'contenido_json' => $db->escape_string(json_encode($bloque['contenido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))); }
    }
    opg_periodico_auditar($db, $id, $uid, $id && $antes ? 'edicion_actualizada' : 'edicion_creada', $antes, $datos);
    $db->write_query('COMMIT');
    return $id;
}

function opg_periodico_buscar_publico($db, $numero = 0, $slug = '')
{
    $where = ($numero > 0 || $slug !== '') ? "estado IN ('publicado','archivado')" : "estado='publicado'";
    if ($numero > 0) { $where .= " AND numero='" . (int) $numero . "'"; }
    elseif ($slug !== '') { $where .= " AND slug='" . $db->escape_string($slug) . "'"; }
    $q = $db->simple_select('op_periodicos', 'id', $where, array('order_by' => 'fecha_publicacion', 'order_dir' => 'DESC', 'limit' => 1));
    $fila = $db->fetch_array($q);
    return $fila ? opg_periodico_cargar($db, $fila['id']) : null;
}

function opg_periodico_cambiar_estado($db, $id, $estado, $uid)
{
    $estados = array('borrador', 'publicado', 'archivado');
    $id = (int) $id;
    if (!in_array($estado, $estados, true) || !$id) { return false; }
    $edicion = opg_periodico_cargar($db, $id);
    if (!$edicion) { return false; }
    $ahora = date('Y-m-d H:i:s');
    $fila = array('estado' => $db->escape_string($estado), 'fecha_actualizacion' => $ahora);
    $db->write_query('START TRANSACTION');
    if ($estado === 'publicado') {
        $anteriores = $db->simple_select('op_periodicos', 'id,estado', "estado='publicado' AND id!='{$id}'");
        while ($anterior = $db->fetch_array($anteriores)) {
            $anterior_id = (int) $anterior['id'];
            $db->update_query('op_periodicos', array('estado' => 'archivado', 'fecha_actualizacion' => $ahora), "id='{$anterior_id}'");
            opg_periodico_auditar($db, $anterior_id, $uid, 'estado_archivado', array('estado' => 'publicado'), array('estado' => 'archivado'));
        }
    }
    if ($estado === 'publicado' && empty($edicion['fecha_publicacion'])) { $fila['fecha_publicacion'] = date('Y-m-d H:i:s'); }
    $db->update_query('op_periodicos', $fila, "id='{$id}'");
    opg_periodico_auditar($db, $id, $uid, 'estado_' . $estado, array('estado' => $edicion['estado']), array('estado' => $estado));
    $db->write_query('COMMIT');
    return true;
}

function opg_periodico_listar($db, $filtro = '')
{
    $where = '';
    if (in_array($filtro, array('borrador', 'publicado', 'archivado'), true)) { $where = "estado='" . $db->escape_string($filtro) . "'"; }
    $resultado = $db->simple_select('op_periodicos', '*', $where, array('order_by' => 'numero', 'order_dir' => 'DESC'));
    $ediciones = array();
    while ($fila = $db->fetch_array($resultado)) { $ediciones[] = $fila; }
    return $ediciones;
}

function opg_periodico_archivo_publico($db, $anio = 0)
{
    $where = "estado IN ('publicado','archivado')";
    if ($anio >= 2000 && $anio <= 9999) { $where .= " AND YEAR(fecha_publicacion)='" . (int) $anio . "'"; }
    $resultado = $db->simple_select('op_periodicos', 'numero,slug,titulo,portada,fecha_publicacion', $where, array('order_by' => 'fecha_publicacion', 'order_dir' => 'DESC'));
    $ediciones = array();
    while ($fila = $db->fetch_array($resultado)) { $ediciones[] = $fila; }
    return $ediciones;
}

function opg_periodico_render_bbcode($texto)
{
    global $parser;
    if (!isset($parser) || !is_object($parser)) {
        require_once MYBB_ROOT . 'inc/class_parser.php';
        $parser = new postParser;
    }
    return $parser->parse_message($texto, array('allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 1, 'allow_imgcode' => 1, 'filter_badwords' => 1, 'nl2br' => 1));
}

function opg_periodico_render_bloque($bloque)
{
    $tipo = $bloque['tipo']; $c = $bloque['contenido'];
    if ($tipo === 'separador') { return '<hr class="periodico-separador">'; }
    if ($tipo === 'wanted') { return '<aside class="periodico-wanted"><img src="' . opg_periodico_esc($c['imagen']) . '" alt="' . opg_periodico_esc($c['nombre']) . '"><div><strong>WANTED</strong><h3>' . opg_periodico_esc($c['nombre']) . '</h3><p>' . opg_periodico_esc($c['recompensa']) . '</p></div></aside>'; }
    if ($tipo === 'galeria') {
        $imagenes = '';
        foreach ((array) $c['imagenes'] as $imagen) { if ($imagen !== '') { $imagenes .= '<img src="' . opg_periodico_esc($imagen) . '" alt="' . opg_periodico_esc($c['titulo']) . '">'; } }
        return '<section class="periodico-bloque periodico-bloque--galeria"><h2>' . opg_periodico_esc($c['titulo']) . '</h2><div class="periodico-galeria">' . $imagenes . '</div><div class="periodico-cuerpo">' . opg_periodico_render_bbcode($c['cuerpo']) . '</div></section>';
    }
    if ($tipo === 'dos_columnas') {
        return '<section class="periodico-bloque periodico-bloque--dos-columnas"><h2>' . opg_periodico_esc($c['titulo']) . '</h2><div class="periodico-columnas"><div>' . opg_periodico_render_bbcode($c['izquierda']) . '</div><div>' . opg_periodico_render_bbcode($c['derecha']) . '</div></div></section>';
    }
    $titulo = !empty($c['titular']) ? $c['titular'] : $c['titulo'];
    $imagen = !empty($c['imagen']) ? '<figure><img src="' . opg_periodico_esc($c['imagen']) . '" alt="' . opg_periodico_esc($titulo) . '"><figcaption>' . opg_periodico_esc($c['pie']) . '</figcaption></figure>' : '';
    $enlace = !empty($c['enlace_relacionado']) ? '<a class="periodico-enlace" href="' . (ctype_digit($c['enlace_relacionado']) ? '/showthread.php?tid=' . (int) $c['enlace_relacionado'] : opg_periodico_esc($c['enlace_relacionado'])) . '">Ver tema relacionado</a>' : '';
    return '<article class="periodico-bloque periodico-bloque--' . opg_periodico_esc($tipo) . '"><h2>' . opg_periodico_esc($titulo) . '</h2>' . (!empty($c['bajada']) || !empty($c['entradilla']) ? '<p class="periodico-entradilla">' . opg_periodico_esc(!empty($c['bajada']) ? $c['bajada'] : $c['entradilla']) . '</p>' : '') . $imagen . '<div class="periodico-cuerpo">' . opg_periodico_render_bbcode($c['cuerpo']) . '</div>' . $enlace . '</article>';
}

function opg_periodico_render_edicion($edicion)
{
    $html = '<section class="periodico-edicion tema-' . opg_periodico_esc($edicion['tema']) . '"><header class="periodico-masthead"><span>EL GAVIOTAZO</span><h1>' . opg_periodico_esc($edicion['titulo']) . '</h1><p>' . opg_periodico_esc($edicion['subtitulo']) . '</p><small>Edición ' . (int) $edicion['numero'] . ' · ' . opg_periodico_esc($edicion['firma_visible']) . '</small></header>';
    foreach ($edicion['paginas'] as $indice => $pagina) { $html .= '<section class="periodico-pagina" id="pagina-' . ($indice + 1) . '">'; foreach ($pagina['bloques'] as $bloque) { $html .= opg_periodico_render_bloque($bloque); } $html .= '</section>'; }
    return $html . '</section>';
}
