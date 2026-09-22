<?php
/**
 * Bitacora personal de temas de rol.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'bitacora.php');
require_once './../global.php';
require_once MYBB_ROOT . 'inc/plugins/op_bitacora/functions.php';

global $mybb, $db, $templates, $header, $headerinclude, $footer;

if (!op_bitacora_tablas_listas()) {
    error('La bitácora de rol todavía no está instalada.');
}
op_bitacora_actualizar_esquema();

$opTemasSessionUid = (int)$mybb->user['uid'];
$opTemasModoVistaUid = $mybb->get_input('modo_vista', MyBB::INPUT_INT);
$opTemasSoloLectura = $opTemasModoVistaUid > 0 && $opTemasModoVistaUid !== $opTemasSessionUid;
$opTemasOwnerUid = $opTemasSoloLectura ? $opTemasModoVistaUid : $opTemasSessionUid;

if ($opTemasOwnerUid <= 0) {
    error_no_permission();
}

$queryFichaVista = $db->simple_select(
    'op_fichas',
    'fid,nombre,apodo',
    "fid='{$opTemasOwnerUid}'",
    array('limit' => 1)
);
$opTemasFichaVista = $db->fetch_array($queryFichaVista);
if (!$opTemasFichaVista) {
    if ($opTemasSoloLectura) {
        error('El personaje solicitado no esta disponible.');
    }
    error('Necesitas una ficha para usar la bitácora de rol.');
}

function op_bitacora_nombre_personaje($row)
{
    $nombre = trim((string)($row['nombre'] ?? ''));
    $apodo = trim((string)($row['apodo'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string)($row['username'] ?? 'Personaje #' . (int)($row['participante_uid'] ?? 0)));
    }
    if ($apodo !== '' && $apodo !== $nombre) {
        $nombre .= ' - ' . $apodo;
    }
    return $nombre;
}

function op_bitacora_mensaje_resultado($code)
{
    $mensajes = array(
        'tema_agregado' => array('ok', 'El tema se agregó a la bitácora.'),
        'tema_retirado' => array('ok', 'El tema dejo de seguirse.'),
        'turno_actualizado' => array('ok', 'El estado del turno se actualizo.'),
        'participante_agregado' => array('ok', 'El participante se agrego a la ronda.'),
        'participante_retirado' => array('ok', 'El participante se retiro de la ronda.'),
        'narrador_actualizado' => array('ok', 'El narrador de la ronda se actualizo.'),
        'narrador_quitado' => array('ok', 'El tema volvio al modo de ronda entre personajes.'),
        'ya_seguido' => array('err', 'Ese tema ya está en tu bitácora.'),
        'tema_no_disponible' => array('err', 'No se pudo agregar ese tema.'),
        'estado_inicial_requerido' => array('err', 'Indica si te toca responder para iniciar el seguimiento.'),
        'estado_invalido' => array('err', 'El estado inicial no es valido.'),
        'participante_invalido' => array('err', 'No se pudo agregar ese participante.'),
        'narrador_invalido' => array('err', 'No se pudo seleccionar ese narrador.'),
        'seguimiento_no_disponible' => array('err', 'Ese seguimiento no esta disponible.'),
        'tema_sin_posts' => array('err', 'El tema no tiene posts visibles.'),
        'csrf' => array('err', 'La sesion del formulario vencio. Recarga la pagina.'),
    );
    return $mensajes[$code] ?? null;
}

function op_bitacora_form_attrs()
{
    return ' method="post" action="/op/bitacora.php" hx-post="/op/bitacora.php"'
        . ' hx-target="#op-temas-tracker" hx-swap="outerHTML"';
}

function op_bitacora_hidden_base($action, $seguimientoId = 0)
{
    global $mybb;
    $html = '<input type="hidden" name="my_post_key" value="' . op_bitacora_escape($mybb->post_code) . '">'
        . '<input type="hidden" name="action" value="' . op_bitacora_escape($action) . '">';
    if ((int)$seguimientoId > 0) {
        $html .= '<input type="hidden" name="seguimiento_id" value="' . (int)$seguimientoId . '">';
    }
    return $html;
}

function op_bitacora_render_participante($participante, $seguimientoId, $removible)
{
    $uid = (int)$participante['participante_uid'];
    $nombre = op_bitacora_escape(op_bitacora_nombre_personaje($participante));
    $avatar = trim((string)($participante['avatar'] ?? ''));
    if ($avatar === '') {
        $avatar = '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png';
    }
    $estado = !empty($participante['respondio']) ? 'Respondio' : 'Pendiente';
    $clase = !empty($participante['respondio']) ? 'respondio' : 'pendiente';
    $origen = ($participante['origen'] ?? 'auto') === 'manual' ? 'Manual' : 'Automatico';
    $esNarrador = !empty($participante['es_narrador']);
    $rol = $esNarrador ? 'Narrador - ' : '';
    $accion = '';

    if ($removible) {
        $accion = '<form class="op-temas-participante__accion"' . op_bitacora_form_attrs() . '>'
            . op_bitacora_hidden_base('retirar_participante', $seguimientoId)
            . '<input type="hidden" name="participante_uid" value="' . $uid . '">'
            . '<button type="submit" class="op-temas-icono op-temas-icono--peligro" title="Retirar de la ronda" aria-label="Retirar a ' . $nombre . ' de la ronda">'
            . '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button></form>';
    }

    return '<div class="op-temas-participante op-temas-participante--' . $clase
        . ($esNarrador ? ' op-temas-participante--narrador' : '') . '">'
        . '<img src="' . op_bitacora_escape($avatar) . '" alt="" loading="lazy" decoding="async">'
        . '<span class="op-temas-participante__datos"><a href="/op/personaje.php?uid=' . $uid . '">' . $nombre . '</a>'
        . '<small>' . $rol . $estado . ' - ' . $origen . '</small></span>' . $accion . '</div>';
}

function op_bitacora_render_narrador($tema, $soloLectura = false)
{
    $id = (int)$tema['id'];
    $narradorUid = (int)($tema['narrador_uid'] ?? 0);
    $narrador = $tema['narrador'] ?? null;
    $actual = '';

    if ($narradorUid > 0) {
        $nombre = $narrador
            ? op_bitacora_nombre_personaje($narrador)
            : 'Personaje #' . $narradorUid;
        $estado = !empty($tema['narrador_respondio']) ? 'Ya inicio tu turno' : 'Esperando su respuesta';
        $quitar = $soloLectura ? ''
            : '<form' . op_bitacora_form_attrs() . '>' . op_bitacora_hidden_base('quitar_narrador', $id)
                . '<button class="btn-op btn-op--sm btn-op--secundario" type="submit">'
                . '<i class="fa-solid fa-user-minus" aria-hidden="true"></i> Quitar</button></form>';
        $actual = '<div class="op-temas-narrador__actual">'
            . '<span><i class="fa-solid fa-book-open" aria-hidden="true"></i> Ronda narrada por '
            . '<a href="/op/personaje.php?uid=' . $narradorUid . '">' . op_bitacora_escape($nombre) . '</a>'
            . '<small>' . $estado . '</small></span>'
            . $quitar . '</div>';
    }

    if ($soloLectura) {
        return $actual === '' ? '' : '<section class="op-temas-narrador op-temas-narrador--lectura">'
            . '<h4><i class="fa-solid fa-book-open" aria-hidden="true"></i> Modo de ronda</h4>'
            . $actual . '</section>';
    }

    $etiqueta = $narradorUid > 0 ? 'Cambiar narrador' : 'Definir narrador del tema';
    $boton = $narradorUid > 0 ? 'Cambiar' : 'Seleccionar';
    $resultadosId = 'narrador-resultados-' . $id;
    $buscador = '<form class="op-temas-agregar-participante op-temas-narrador__buscador"'
        . op_bitacora_form_attrs() . ' autocomplete="off">'
        . op_bitacora_hidden_base('establecer_narrador', $id)
        . '<div class="op-temas-buscador opg-buscar-caja">'
        . '<label for="narrador-buscar-' . $id . '"><span>' . $etiqueta . '</span>'
        . '<small class="op-temas-buscador__ayuda">Nombre o FID &middot; desde 3 caracteres</small></label>'
        . '<input type="search" id="narrador-buscar-' . $id . '" name="q" placeholder="Nombre o FID" aria-autocomplete="list"'
        . ' aria-controls="' . $resultadosId . '" data-op-temas-typeahead data-results-id="' . $resultadosId . '">'
        . '<input class="op-temas-participante-uid" type="hidden" name="narrador_uid" value="">'
        . '<div id="' . $resultadosId . '" class="op-temas-resultados opg-resultados" role="listbox"></div></div>'
        . '<button class="btn-op btn-op--sm btn-op--primario" type="submit">'
        . '<i class="fa-solid fa-book-open-reader" aria-hidden="true"></i> ' . $boton . '</button></form>';

    $resumen = $narradorUid > 0
        ? 'Narrador configurado: ' . op_bitacora_escape($nombre)
        : 'Ronda normal, sin narrador';

    return '<details class="op-temas-narrador">'
        . '<summary><span><i class="fa-solid fa-book-open" aria-hidden="true"></i> Modo de ronda</span>'
        . '<small>' . $resumen . '</small><i class="fa-solid fa-chevron-down op-temas-narrador__flecha" aria-hidden="true"></i></summary>'
        . '<div class="op-temas-narrador__cuerpo">' . $actual . $buscador . '</div></details>';
}

function op_bitacora_render_configuracion($tema, $soloLectura = false)
{
    $id = (int)$tema['id'];
    $respondieron = '';
    foreach ($tema['respondieron_lista'] as $participante) {
        $respondieron .= op_bitacora_render_participante($participante, $id, !$soloLectura);
    }
    if ($respondieron === '') {
        $respondieron = '<p class="op-temas-vacio-corto">Nadie ha respondido todavia.</p>';
    }

    $pendientes = '';
    foreach ($tema['pendientes'] as $participante) {
        $pendientes .= op_bitacora_render_participante($participante, $id, !$soloLectura);
    }
    if ($pendientes === '') {
        $pendientes = '<p class="op-temas-vacio-corto">No quedan participantes pendientes.</p>';
    }

    $turnos = '';
    if (!$soloLectura) {
        $turnos = '<div class="op-temas-turnos">'
            . '<form' . op_bitacora_form_attrs() . '>' . op_bitacora_hidden_base('me_toca', $id)
            . '<button class="btn-op btn-op--sm btn-op--exito" type="submit"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Me toca responder</button></form>'
            . '<form' . op_bitacora_form_attrs() . '>' . op_bitacora_hidden_base('no_me_toca', $id)
            . '<button class="btn-op btn-op--sm btn-op--secundario" type="submit"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> No me toca</button></form>'
            . '</div>';
    }

    $buscador = '';
    if (!$soloLectura) {
        $resultadosId = 'participante-resultados-' . $id;
        $formularioParticipante = '<form class="op-temas-agregar-participante"' . op_bitacora_form_attrs() . ' autocomplete="off">'
            . op_bitacora_hidden_base('agregar_participante', $id)
            . '<div class="op-temas-buscador opg-buscar-caja">'
            . '<label for="participante-buscar-' . $id . '"><span>Buscar personaje</span>'
            . '<small class="op-temas-buscador__ayuda">Nombre o FID &middot; desde 3 caracteres</small></label>'
            . '<input type="search" id="participante-buscar-' . $id . '" name="q" placeholder="Nombre o FID" aria-autocomplete="list"'
            . ' aria-controls="' . $resultadosId . '" data-op-temas-typeahead data-results-id="' . $resultadosId . '">'
            . '<input class="op-temas-participante-uid" type="hidden" name="participante_uid" value="">'
            . '<div id="' . $resultadosId . '" class="op-temas-resultados opg-resultados" role="listbox"></div></div>'
            . '<button class="btn-op btn-op--sm btn-op--primario" type="submit"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Agregar</button>'
            . '</form>';
        $buscador = '<details class="op-temas-agregar-control">'
            . '<summary><span><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Agregar participante</span>'
            . '<small>Incluir otro personaje en la ronda</small>'
            . '<i class="fa-solid fa-chevron-down op-temas-agregar-control__flecha" aria-hidden="true"></i></summary>'
            . '<div class="op-temas-agregar-control__cuerpo">' . $formularioParticipante . '</div></details>';
    }

    return '<details class="op-temas-config">'
        . '<summary><i class="fa-solid fa-' . ($soloLectura ? 'users' : 'users-gear') . '" aria-hidden="true"></i> '
        . ($soloLectura ? 'Ver ronda' : 'Configurar ronda') . '</summary>'
        . '<div class="op-temas-config__cuerpo">' . $turnos
        . '<div class="op-temas-participantes-grid"><section><h4>Ya respondieron</h4>' . $respondieron . '</section>'
        . '<section><h4>Pendientes</h4>' . $pendientes . '</section></div>'
        . op_bitacora_render_narrador($tema, $soloLectura)
        . $buscador . '</div></details>';
}

function op_bitacora_render_tarjeta($tema, $soloLectura = false)
{
    $id = (int)$tema['id'];
    $tid = (int)$tema['tid'];
    $titulo = op_bitacora_escape($tema['subject']);
    $foro = op_bitacora_escape($tema['forum_name']);
    $url = '/showthread.php?tid=' . $tid . '&action=lastpost';
    $prefijo = '';
    $prefijoId = (int)($tema['prefix'] ?? 0);
    if ($prefijoId > 0) {
        $datosPrefijo = build_prefixes($prefijoId);
        if (!empty($datosPrefijo['displaystyle'])) {
            $prefijo = '<span class="op-temas-prefijo">' . $datosPrefijo['displaystyle'] . '</span>';
        }
    }
    $ultimoNombre = op_bitacora_escape(op_bitacora_nombre_personaje(array(
        'nombre' => $tema['last_nombre'] ?? '',
        'apodo' => $tema['last_apodo'] ?? '',
        'username' => $tema['last_username'] ?? 'Desconocido',
    )));
    $fecha = my_date('d/m/Y H:i', (int)$tema['lastpost']);
    $estado = (string)$tema['estado'];
    $cerrado = $estado === 'cerrado';
    $manual = !empty($tema['estado_es_manual']);

    if ($cerrado) {
        $estadoTexto = 'Tema cerrado';
        $estadoClase = 'cerrado';
    } elseif ($estado === 'debes_responder' || $estado === 'debes_responder_manual') {
        $estadoTexto = 'Tu turno';
        $estadoClase = 'debes';
    } else {
        $estadoTexto = !empty($tema['narrador_uid']) ? 'Esperando al narrador' : 'Esperando respuesta';
        $estadoClase = 'esperando';
    }

    $pendientes = '';
    foreach ($tema['pendientes'] as $participante) {
        $pendientes .= '<a class="op-temas-pendiente" href="/op/personaje.php?uid=' . (int)$participante['participante_uid'] . '">'
            . op_bitacora_escape(op_bitacora_nombre_personaje($participante)) . '</a>';
    }
    if ($pendientes === '') {
        $pendientes = '<span class="op-temas-sin-pendientes">Sin participantes pendientes</span>';
    }

    $progreso = (int)$tema['respondieron'] . ' de ' . (int)$tema['esperados'] . ' respondieron';
    $marcaNarrador = '';
    if (!empty($tema['narrador_uid'])) {
        $narrador = $tema['narrador'] ?? array('participante_uid' => (int)$tema['narrador_uid']);
        $narradorNombre = op_bitacora_escape(op_bitacora_nombre_personaje($narrador));
        $marcaNarrador = '<span class="op-temas-narrada"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Ronda narrada</span>';
        if (!empty($tema['narrador_respondio'])) {
            $progreso = 'El narrador inicio la ronda';
            if (empty($tema['pendientes'])) {
                $pendientes = '<span class="op-temas-sin-pendientes">Sin otros participantes pendientes</span>';
            }
        } else {
            $progreso = 'Esperando al narrador';
            $pendientes = '<a class="op-temas-pendiente" href="/op/personaje.php?uid=' . (int)$tema['narrador_uid'] . '">'
                . $narradorNombre . '</a>';
        }
    }
    $marcaManual = $manual ? '<span class="op-temas-manual">Ajuste manual</span>' : '';
    $configuracion = $cerrado ? '' : op_bitacora_render_configuracion($tema, $soloLectura);
    $retirar = '';
    if (!$soloLectura) {
        $retirar = '<form class="op-temas-retirar"' . op_bitacora_form_attrs() . ' onsubmit="return confirm(\'Confirmar: dejar de seguir este tema?\')">'
            . op_bitacora_hidden_base('dejar_seguir', $id)
            . '<button class="op-temas-icono op-temas-icono--peligro" type="submit" title="Dejar de seguir" aria-label="Dejar de seguir ' . $titulo . '">'
            . '<i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></form>';
    }

    return '<article class="op-temas-card op-temas-card--' . $estadoClase . '">'
        . '<header class="op-temas-card__header"><div><span class="op-temas-estado">' . $estadoTexto . '</span>' . $marcaManual . $marcaNarrador
        . '<h3>' . $prefijo . '<a href="' . $url . '" target="_blank" rel="noopener">' . $titulo . '</a></h3></div>' . $retirar . '</header>'
        . '<div class="op-temas-card__meta"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' . $foro . '</span>'
        . '<span>TID ' . $tid . '</span><span>Ultimo post: ' . $ultimoNombre . ' - ' . op_bitacora_escape($fecha) . '</span></div>'
        . ($cerrado ? '<p class="op-temas-cerrado-aviso"><i class="fa-solid fa-lock" aria-hidden="true"></i> Este tema esta cerrado.'
            . ($soloLectura ? '' : ' Retíralo de la bitácora cuando hayas terminado.') . '</p>'
            : '<div class="op-temas-ronda"><strong>' . $progreso . '</strong><div class="op-temas-pendientes">' . $pendientes . '</div></div>' . $configuracion)
        . '</article>';
}

function op_bitacora_render_resultado_busqueda($ficha)
{
    $uid = (int)$ficha['fid'];
    $nombre = trim((string)($ficha['nombre'] ?? ''));
    $label = ($nombre !== '' ? $nombre : 'Personaje #' . $uid) . ' (#' . $uid . ')';
    return '<button type="button" class="opg-resultado op-temas-personaje-opcion" role="option"'
        . ' data-uid="' . $uid . '" data-label="' . op_bitacora_escape($label) . '">'
        . op_bitacora_escape($label) . '</button>';
}

function op_bitacora_render_resultado_vista($ficha)
{
    $uid = (int)$ficha['fid'];
    $nombre = trim((string)($ficha['nombre'] ?? ''));
    $nombre = op_bitacora_escape($nombre !== '' ? $nombre : 'Personaje #' . $uid);
    return '<a class="opg-resultado op-temas-vista-opcion" role="option"'
        . ' href="/op/bitacora.php?modo_vista=' . $uid . '">'
        . $nombre . '<span>FID #' . $uid . '</span></a>';
}

function op_bitacora_render_alta()
{
    return '<form class="op-temas-alta"' . op_bitacora_form_attrs() . '>'
        . op_bitacora_hidden_base('agregar_tema')
        . '<div class="af-field"><label for="op-temas-tid">Agregar tema por TID</label>'
        . '<input type="number" id="op-temas-tid" name="tid" min="1" required placeholder="Ej. 12345"></div>'
        . '<div class="af-field"><label for="op-temas-estado-inicial">Estado inicial</label>'
        . '<select id="op-temas-estado-inicial" name="estado_inicial">'
        . '<option value="me_toca">Me toca responder</option>'
        . '<option value="auto">Calcular desde mi ultimo post</option>'
        . '<option value="no_me_toca">No me toca responder</option>'
        . '</select></div>'
        . '<button type="submit" class="btn-op btn-op--primario">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar</button></form>';
}

function op_bitacora_render_tracker(
    $listado,
    $resultadoCode = '',
    $soloLectura = false,
    $vistaUid = 0,
    $vistaNombre = '',
    $mostrarMisTemas = false
)
{
    global $templates, $mybb;
    $op_temas_conteo_debes = (int)$listado['conteos']['debes_responder'];
    $op_temas_conteo_esperando = (int)$listado['conteos']['esperando'];

    $op_temas_lista_debes = '';
    foreach ($listado['debes_responder'] as $tema) {
        $op_temas_lista_debes .= op_bitacora_render_tarjeta($tema, $soloLectura);
    }
    if ($op_temas_lista_debes === '') {
        $op_temas_lista_debes = '<p class="op-temas-vacio"><i class="fa-solid fa-check" aria-hidden="true"></i> '
            . ($soloLectura ? 'Este personaje no tiene temas en Tu turno.' : 'No tienes temas en Tu turno.') . '</p>';
    }

    $op_temas_lista_esperando = '';
    foreach ($listado['esperando'] as $tema) {
        $op_temas_lista_esperando .= op_bitacora_render_tarjeta($tema, $soloLectura);
    }
    if ($op_temas_lista_esperando === '') {
        $op_temas_lista_esperando = '<p class="op-temas-vacio">'
            . ($soloLectura ? 'Este personaje no tiene temas en Al día.' : 'No tienes temas en Al día.') . '</p>';
    }

    $op_temas_aviso = '';
    $mensaje = op_bitacora_mensaje_resultado($resultadoCode);
    if ($mensaje) {
        $op_temas_aviso = '<p class="aviso ' . $mensaje[0] . '" role="status">' . op_bitacora_escape($mensaje[1]) . '</p>';
    }

    $op_temas_modo_vista = '';
    if ($soloLectura) {
        $volver = $mostrarMisTemas
            ? '<a class="btn-op btn-op--sm btn-op--secundario" href="/op/bitacora.php">'
                . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Mis temas</a>'
            : '';
        $op_temas_modo_vista = '<aside class="op-temas-vista" aria-label="Modo vista">'
            . '<span><i class="fa-solid fa-eye" aria-hidden="true"></i><span>'
            . '<strong>Modo vista</strong> Bitácora de <a href="/op/personaje.php?uid=' . (int)$vistaUid . '">'
            . op_bitacora_escape($vistaNombre) . '</a></span></span>' . $volver . '</aside>';
    }
    $op_temas_alta = $soloLectura ? '' : op_bitacora_render_alta();
    $op_temas_post_key = op_bitacora_escape($mybb->post_code);
    eval("\$html = \"" . op_bitacora_cargar_plantilla('op_bitacora_contenido') . "\";");
    return $html;
}

$action = $mybb->get_input('action', MyBB::INPUT_STRING);

if ($action === 'buscar_modo_vista') {
    $q = trim($mybb->get_input('q', MyBB::INPUT_STRING));
    if (mb_strlen($q) < 3 && !ctype_digit($q)) {
        header('Content-Type: text/html; charset=utf-8');
        exit;
    }

    $like = $db->escape_string(addcslashes($q, '%_'));
    $exact = $db->escape_string($q);
    $idWhere = ctype_digit($q) ? ' OR fid=' . (int)$q : '';
    $exactId = ctype_digit($q) ? (int)$q : 0;
    $query = $db->query("SELECT fid,nombre
        FROM " . TABLE_PREFIX . "op_fichas
        WHERE fid != {$opTemasOwnerUid}
          AND (nombre LIKE '%{$like}%' {$idWhere})
        ORDER BY CASE WHEN fid={$exactId} THEN 0
                      WHEN nombre='{$exact}' THEN 1 ELSE 2 END,
                 nombre ASC
        LIMIT 12");
    $html = '';
    while ($ficha = $db->fetch_array($query)) {
        $html .= op_bitacora_render_resultado_vista($ficha);
    }
    if ($html === '') {
        $html = '<p class="opg-resultado-vacio">No se encontraron personajes.</p>';
    }
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

if ($action === 'buscar_personajes') {
    if ($opTemasSoloLectura || $opTemasSessionUid <= 0) {
        http_response_code(403);
        exit;
    }
    $q = trim($mybb->get_input('q', MyBB::INPUT_STRING));
    if (mb_strlen($q) < 3) {
        header('Content-Type: text/html; charset=utf-8');
        exit;
    }

    $like = $db->escape_string(addcslashes($q, '%_'));
    $exact = $db->escape_string($q);
    $idWhere = ctype_digit($q) ? ' OR fid=' . (int)$q : '';
    $query = $db->query("SELECT fid,nombre
        FROM " . TABLE_PREFIX . "op_fichas
        WHERE fid != {$opTemasOwnerUid}
          AND (nombre LIKE '%{$like}%' {$idWhere})
        ORDER BY CASE WHEN nombre='{$exact}' THEN 0 ELSE 1 END,
                 nombre ASC
        LIMIT 12");
    $html = '';
    while ($ficha = $db->fetch_array($query)) {
        $html .= op_bitacora_render_resultado_busqueda($ficha);
    }
    if ($html === '') {
        $html = '<p class="opg-resultado-vacio">No se encontraron personajes.</p>';
    }
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

$resultadoCode = trim($mybb->get_input('resultado', MyBB::INPUT_STRING));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($opTemasSoloLectura || $opTemasSessionUid <= 0) {
        error_no_permission();
    }
    if (!verify_post_check($mybb->get_input('my_post_key', MyBB::INPUT_STRING), true)) {
        $resultado = array('ok' => false, 'code' => 'csrf');
    } else {
        $seguimientoId = $mybb->get_input('seguimiento_id', MyBB::INPUT_INT);
        switch ($action) {
            case 'agregar_tema':
                $resultado = op_bitacora_agregar_tema(
                    $opTemasOwnerUid,
                    $mybb->get_input('tid', MyBB::INPUT_INT),
                    $mybb->get_input('estado_inicial', MyBB::INPUT_STRING)
                );
                break;
            case 'dejar_seguir':
                $resultado = op_bitacora_dejar_seguir($opTemasOwnerUid, $seguimientoId);
                break;
            case 'me_toca':
                $resultado = op_bitacora_marcar_me_toca($opTemasOwnerUid, $seguimientoId);
                break;
            case 'no_me_toca':
                $resultado = op_bitacora_marcar_no_me_toca($opTemasOwnerUid, $seguimientoId);
                break;
            case 'agregar_participante':
                $resultado = op_bitacora_agregar_participante(
                    $opTemasOwnerUid,
                    $seguimientoId,
                    $mybb->get_input('participante_uid', MyBB::INPUT_INT)
                );
                break;
            case 'retirar_participante':
                $resultado = op_bitacora_retirar_participante(
                    $opTemasOwnerUid,
                    $seguimientoId,
                    $mybb->get_input('participante_uid', MyBB::INPUT_INT)
                );
                break;
            case 'establecer_narrador':
                $resultado = op_bitacora_establecer_narrador(
                    $opTemasOwnerUid,
                    $seguimientoId,
                    $mybb->get_input('narrador_uid', MyBB::INPUT_INT)
                );
                break;
            case 'quitar_narrador':
                $resultado = op_bitacora_quitar_narrador($opTemasOwnerUid, $seguimientoId);
                break;
            default:
                $resultado = array('ok' => false, 'code' => 'seguimiento_no_disponible');
        }
    }

    $resultadoCode = (string)$resultado['code'];
    if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
        $listadoActualizado = op_bitacora_listar($opTemasOwnerUid);
        header('Content-Type: text/html; charset=utf-8');
        echo op_bitacora_render_tracker($listadoActualizado, $resultadoCode);
        echo op_bitacora_render_header($opTemasOwnerUid, $listadoActualizado['conteos']);
        exit;
    }
    header('Location: /op/bitacora.php?resultado=' . rawurlencode($resultadoCode));
    exit;
}

$listado = op_bitacora_listar($opTemasOwnerUid);
$opTemasVistaNombre = op_bitacora_nombre_personaje(array(
    'participante_uid' => $opTemasOwnerUid,
    'nombre' => $opTemasFichaVista['nombre'],
    'apodo' => $opTemasFichaVista['apodo'],
));
$opTemasMostrarMisTemas = $opTemasSessionUid > 0
    && op_bitacora_personaje_tiene_ficha($opTemasSessionUid);
$op_bitacora_contenido = op_bitacora_render_tracker(
    $listado,
    $opTemasSoloLectura ? '' : $resultadoCode,
    $opTemasSoloLectura,
    $opTemasOwnerUid,
    $opTemasVistaNombre,
    $opTemasMostrarMisTemas
);

if ($mybb->get_input('fragmento', MyBB::INPUT_STRING) === 'tracker') {
    header('Content-Type: text/html; charset=utf-8');
    echo $op_bitacora_contenido;
    exit;
}

$op_temas_titulo = $opTemasSoloLectura
    ? 'Bitácora de ' . op_bitacora_escape($opTemasVistaNombre)
    : 'Bitácora de rol';
$op_temas_subtitulo = $opTemasSoloLectura
    ? 'Vista pública de sus temas, rondas y turnos.'
    : 'Tus temas, rondas y turnos pendientes.';
$op_temas_html_title = $opTemasSoloLectura
    ? op_bitacora_escape('Bitácora de ' . $opTemasVistaNombre)
    : 'Bitácora de rol';
$op_temas_vista_uid = (int)$opTemasOwnerUid;
$opTemasBreadcrumbUrl = $opTemasSoloLectura
    ? '/op/bitacora.php?modo_vista=' . $opTemasOwnerUid
    : '/op/bitacora.php';
add_breadcrumb($op_temas_titulo, $opTemasBreadcrumbUrl);
eval("\$page = \"" . op_bitacora_cargar_plantilla('op_bitacora') . "\";");
output_page($page);
