<?php
/**
 * Vistas de la pagina de notificaciones (issues #7 y #8): lib/notificaciones_vistas.php, sin base de datos.
 *
 *   php tests/notificaciones/pagina.php
 *
 * Las pruebas de la pagina como peticion HTTP (uid de la sesion, invitados, cursor, filtros y destinos borrados con MySQL
 * real) estan en pagina_db.php, y las de las acciones Visto y Marcar todas en acciones_db.php.
 */
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_vistas.php';

$fallos = array();
$total = 0;
function np($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos[] = "$nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true);
    }
}
function tiene($html, $texto) { return strpos($html, $texto) !== false; }

/** Fila como la devuelve opg_notif_listar. */
function fila($id, $tipo, $extra = array())
{
    return opg_notif_fila_lista($extra + array(
        'id' => $id, 'tipo' => $tipo, 'categoria' => 'aventuras', 'origen' => 'gestion', 'importancia' => opg_notif_importancia($tipo),
        'agrupable' => 0, 'actor_uid' => 0, 'url' => null, 'cantidad' => 1, 'datos_json' => null, 'created_at' => 1000000, 'leida_en' => null,
    ));
}

$ahora = 1000000 + 7200;
$ctx = array('raiz' => 'https://foro.test', 'ahora' => $ahora, 'borrados' => array());

// ── Filtros de la peticion ───────────────────────────────────────────────────
np('filtros vacios: Todas', opg_notif_v_filtros_peticion(array()), array('categoria' => '', 'origen' => ''));
np('filtros validos', opg_notif_v_filtros_peticion(array('categoria' => 'temas', 'origen' => 'gestion')), array('categoria' => 'temas', 'origen' => 'gestion'));
np('filtro desconocido o manipulado: Todas', opg_notif_v_filtros_peticion(array('categoria' => "x' OR 1=1", 'origen' => 'sistema')), array('categoria' => '', 'origen' => ''));
np('filtro como lista: Todas', opg_notif_v_filtros_peticion(array('categoria' => array('temas'))), array('categoria' => '', 'origen' => ''));
np('el filtro ignora un uid de la peticion', array_keys(opg_notif_v_filtros_peticion(array('uid' => '9', 'categoria' => 'ficha'))), array('categoria', 'origen'));
np('origenes del filtro: Todas, De gestion, De usuarios', array_values(opg_notif_v_origenes()), array('Todas', 'De gestión', 'De usuarios'));
foreach (array_keys(opg_notif_v_categorias()) as $c) {
    np("categoria $c valida para la libreria", $c === '' || in_array($c, opg_notif_categorias(), true), true);
}

// ── Bloques: importantes sin leer, pendientes, historial ─────────────────────
np('orden de los bloques', array_keys(opg_notif_v_bloques()), array('importantes', 'pendientes', 'historial'));
np('filtros del bloque importantes con categoria', opg_notif_v_filtros_bloque('importantes', array('categoria' => 'aventuras', 'origen' => '')),
    array('categoria' => 'aventuras', 'estado' => 'sin_leer', 'importancia' => 'importante'));
np('filtros del bloque historial con origen', opg_notif_v_filtros_bloque('historial', array('categoria' => '', 'origen' => 'usuario')),
    array('origen' => 'usuario', 'estado' => 'leidas'));
np('bloque desconocido', opg_notif_v_filtros_bloque('otro', array('categoria' => '', 'origen' => '')), null);
foreach (array_keys(opg_notif_v_bloques()) as $b) {
    np("los filtros del bloque $b son aceptados por la libreria", is_array(opg_notif_filtros(opg_notif_v_filtros_bloque($b, array('categoria' => 'temas', 'origen' => 'gestion')), array('categoria', 'origen', 'estado', 'importancia'))), true);
}

// ── Fechas relativas ─────────────────────────────────────────────────────────
$t = 2000000000;
np('ahora mismo', opg_notif_v_fecha_relativa($t - 10, $t), 'ahora mismo');
np('un minuto', opg_notif_v_fecha_relativa($t - 61, $t), 'hace 1 minuto');
np('minutos', opg_notif_v_fecha_relativa($t - 600, $t), 'hace 10 minutos');
np('horas', opg_notif_v_fecha_relativa($t - 3 * 3600, $t), 'hace 3 horas');
np('ayer', opg_notif_v_fecha_relativa($t - 86400 - 5, $t), 'ayer');
np('dias', opg_notif_v_fecha_relativa($t - 4 * 86400, $t), 'hace 4 días');
np('desde 7 dias: la fecha', opg_notif_v_fecha_relativa($t - 8 * 86400, $t), date('d/m/Y', $t - 8 * 86400));
np('fecha futura: ahora mismo', opg_notif_v_fecha_relativa($t + 50, $t), 'ahora mismo');

// ── Enlaces y destinos ───────────────────────────────────────────────────────
np('enlace del catalogo', opg_notif_v_url_fila(fila(1, 'aventura_aprobada', array('datos_json' => '{"peticion_id":7}'))), 'op/tablon_de_aventuras.php?id=7');
np('el enlace guardado prevalece', opg_notif_v_url_fila(fila(1, 'aventura_aprobada', array('url' => 'op/bitacora.php', 'datos_json' => '{"peticion_id":7}'))), 'op/bitacora.php');
np('un enlace guardado externo se ignora', opg_notif_v_url_fila(fila(1, 'aventura_aprobada', array('url' => 'https://malo.test/x', 'datos_json' => '{"peticion_id":7}'))), 'op/tablon_de_aventuras.php?id=7');
np('href relativo a la raiz del foro', opg_notif_v_href('op/x.php?id=1', 'https://foro.test/'), 'https://foro.test/op/x.php?id=1');
np('href absoluto del sitio', opg_notif_v_href('/op/x.php', 'https://foro.test'), '/op/x.php');
np('destino tema', opg_notif_v_destino('showthread.php?tid=12&action=newpost'), array('tema', 12));
np('destino tema con &amp;', opg_notif_v_destino('showthread.php?action=newpost&amp;tid=13'), array('tema', 13));
np('destino aventura', opg_notif_v_destino('op/tablon_de_aventuras.php?id=9'), array('aventura', 9));
np('destino sin objeto borrable', opg_notif_v_destino('op/tablon_de_aventuras.php?tab=narradores'), null);
np('destino de la bitacora', opg_notif_v_destino('op/bitacora.php'), null);

// ── Fila ─────────────────────────────────────────────────────────────────────
$f = fila(41, 'entrega_rechazada', array('actor_uid' => 5, 'datos_json' => json_encode(array('peticion_id' => 9, 'actor' => 'Smoker <b>', 'tema' => 'Isla & "ruta"'))));
$h = opg_notif_v_fila($f, $ctx);
np('fila: id para las acciones de #8', tiene($h, 'id="nt-n-41"'), true);
np('fila: sin leer e importante destacada', tiene($h, 'class="nt-fila nt-fila--sin-leer nt-fila--importante"'), true);
np('fila: icono del catalogo', tiene($h, '<i class="fa-solid fa-rotate-left"></i>'), true);
np('fila: texto escapado una vez', tiene($h, 'Smoker &lt;b&gt; rechazó la entrega de «Isla &amp; &quot;ruta&quot;»'), true);
np('fila: enlace absoluto al tablon', tiene($h, '<a href="https://foro.test/op/tablon_de_aventuras.php?id=9">'), true);
np('fila: actor', tiene($h, '<span class="nt-fila-actor"><i class="fa-solid fa-user" aria-hidden="true"></i> Smoker &lt;b&gt;</span>'), true);
np('fila: fecha relativa con la completa en title', tiene($h, '>hace 2 horas</time>') && tiene($h, 'datetime="' . gmdate('Y-m-d\TH:i:s\Z', 1000000) . '"'), true);
np('fila: etiqueta Importante', tiene($h, 'nt-etiqueta--importante">Importante<'), true);
np('fila: aviso para lectores de pantalla', tiene($h, '<span class="nt-solo-lector">Sin leer: </span>'), true);
np('fila: sin HTML de usuario sin escapar', tiene($h, '<b>'), false);
np('fila sin leer: boton Visto que reemplaza la fila', tiene($h, '<button type="button" class="opg-chip nt-visto" hx-post="notificaciones.php?action=visto&amp;id=41" hx-target="#nt-n-41" hx-swap="outerHTML"'), true);
np('fila: seguir el enlace no marca (enlace normal, sin hx-*)', preg_match('/<a [^>]*hx-/', $h), 0);

$h = opg_notif_v_fila($f, array('borrados' => array('aventura:9' => true)) + $ctx);
np('fila con destino borrado: texto sin enlace', array(tiene($h, '<a '), tiene($h, 'rechazó la entrega')), array(false, true));

$h = opg_notif_v_fila(fila(42, 'propuesta_vencida', array('actor_uid' => 0, 'leida_en' => 1000100, 'datos_json' => '{"actor":"Nadie"}')), $ctx);
np('fila leida: atenuada, sin etiqueta ni aviso de sin leer', array(tiene($h, 'nt-fila--leida'), tiene($h, 'Importante'), tiene($h, 'Sin leer')), array(true, false, false));
np('fila leida: sin boton Visto ni forma de volver a no leida', array(tiene($h, 'nt-visto'), tiene($h, 'hx-post')), array(false, false));
np('fila sin actor (sistema): no muestra actor', tiene($h, 'nt-fila-actor'), false);

$h = opg_notif_v_fila(fila(43, 'tipo_retirado', array('datos_json' => '{"peticion_id":3}')), $ctx);
np('tipo desconocido: Actualizacion, sin enlace, icono generico', array(tiene($h, 'Actualización'), tiene($h, '<a '), tiene($h, 'fa-bell')), array(true, false, true));

$h = opg_notif_v_fila(fila(44, 'bitacora_post', array('agrupable' => 1, 'cantidad' => 4, 'categoria' => 'temas', 'origen' => 'usuario', 'datos_json' => '{"tid":12,"tema":"Rumbo"}')), $ctx);
np('agrupable: cantidad en el texto y enlace al tema', array(tiene($h, '4 publicaciones nuevas en «Rumbo».'), tiene($h, 'href="https://foro.test/showthread.php?tid=12&amp;action=newpost"')), array(true, true));

// ── Cargar mas ───────────────────────────────────────────────────────────────
$res = array('filas' => array(fila(50, 'aventura_aprobada')), 'hay_mas' => true, 'cursor' => '1000000_50');
$h = opg_notif_v_filas('pendientes', $res, array('categoria' => 'aventuras', 'origen' => ''), $ctx);
np('cargar mas: cursor, bloque y filtro en la peticion', tiene($h, 'hx-get="notificaciones.php?categoria=aventuras&amp;action=mas&amp;bloque=pendientes&amp;antes=1000000_50"'), true);
np('cargar mas: reemplaza su propio elemento', tiene($h, 'hx-target="closest li" hx-swap="outerHTML"'), true);
$res['hay_mas'] = false;
np('sin mas filas: sin boton', tiene(opg_notif_v_filas('pendientes', $res, array('categoria' => '', 'origen' => ''), $ctx), 'Cargar más'), false);

// ── Contenido ────────────────────────────────────────────────────────────────
$vacio = array('filas' => array(), 'hay_mas' => false, 'cursor' => null);
$sin = array('categoria' => '', 'origen' => '');
$h = opg_notif_v_contenido($sin, array('importantes' => $vacio, 'pendientes' => $vacio, 'historial' => $vacio), $ctx);
np('estado vacio', array(tiene($h, 'No tienes notificaciones'), tiene($h, 'nt-bloque')), array(true, false));
np('estado vacio sin filtro: sin sugerencia', tiene($h, 'con este filtro'), false);
$h = opg_notif_v_contenido(array('categoria' => 'ficha', 'origen' => ''), array('importantes' => $vacio, 'pendientes' => $vacio, 'historial' => $vacio), $ctx);
np('estado vacio con filtro: sugiere Todas', tiene($h, 'No tienes notificaciones') && tiene($h, 'con este filtro'), true);

$imp = array('filas' => array(fila(60, 'aventura_denegada')), 'hay_mas' => false, 'cursor' => null);
$pen = array('filas' => array(fila(61, 'aventura_aprobada')), 'hay_mas' => false, 'cursor' => null);
$his = array('filas' => array(fila(62, 'aventura_aprobada', array('leida_en' => 5))), 'hay_mas' => false, 'cursor' => null);
$h = opg_notif_v_contenido($sin, array('importantes' => $imp, 'pendientes' => $pen, 'historial' => $his), $ctx);
$pos = array(strpos($h, 'class="nt-filtros"'), strpos($h, 'Importantes sin leer'), strpos($h, 'id="nt-t-pendientes"'), strpos($h, 'Historial reciente'));
np('orden: filtros, importantes, pendientes, historial', $pos[0] !== false && $pos[0] < $pos[1] && $pos[1] < $pos[2] && $pos[2] < $pos[3], true);
np('con filas: sin estado vacio', tiene($h, 'No tienes notificaciones'), false);
np('con sin leer: Marcar todas sin filtro, con confirmacion', array(tiene($h, 'hx-post="notificaciones.php?action=todas" hx-target="#nt-contenido" hx-swap="innerHTML"'), tiene($h, 'hx-confirm="¿Marcar como vistas todas tus notificaciones?"'), tiene($h, 'solo este filtro')), array(true, true, false));
np('Marcar todas es boton principal, no chip', array(tiene($h, 'class="btn-op btn-op--sm btn-op--primario nt-todas"'), tiene($h, 'opg-chip nt-todas')), array(true, false));
np('Marcar todas va entre los filtros y las listas', strpos($h, 'class="nt-filtros"') < strpos($h, 'nt-todas') && strpos($h, 'nt-todas') < strpos($h, 'class="nt-listas"'), true);
$h = opg_notif_v_contenido(array('categoria' => 'temas', 'origen' => 'usuario'), array('importantes' => $vacio, 'pendientes' => $pen, 'historial' => $vacio), $ctx);
np('Marcar todas con filtro: solo el filtro activo', array(tiene($h, 'hx-post="notificaciones.php?categoria=temas&amp;origen=usuario&amp;action=todas"'), tiene($h, '(solo este filtro)')), array(true, true));
$h = opg_notif_v_contenido($sin, array('importantes' => $vacio, 'pendientes' => $vacio, 'historial' => $his), $ctx);
np('solo leidas: sin Marcar todas', tiene($h, 'nt-todas'), false);
$h = opg_notif_v_contenido($sin, array('importantes' => $vacio, 'pendientes' => $pen, 'historial' => $vacio), $ctx);
np('un bloque sin filas no se muestra', array(tiene($h, 'Importantes sin leer'), tiene($h, 'Historial reciente'), tiene($h, 'id="nt-lista-pendientes"')), array(false, false, true));

// ── Campana fuera de banda ───────────────────────────────────────────────────
$campana = "<style>\n.op-notif-campana { color: red; }\n</style>\n<div id=\"op-notif-campana\" class=\"op-notif-campana\"\n    hx-swap-oob=\"outerHTML:#op-notif-campana\">\n<a href=\"x\">3</a></div>";
$oob = opg_notif_v_campana_oob($campana);
np('campana OOB: sin el bloque style', array(tiene($oob, '<style'), tiene($oob, 'color: red')), array(false, false));
np('campana OOB: empieza por la campana y conserva hx-swap-oob', array(substr($oob, 0, 25), substr_count($oob, 'hx-swap-oob="outerHTML:#op-notif-campana"')), array('<div id="op-notif-campana', 1));
np('campana OOB: anade hx-swap-oob si la plantilla no lo trae', tiene(opg_notif_v_campana_oob('<div id="op-notif-campana" class="x">1</div>'), 'id="op-notif-campana" hx-swap-oob="outerHTML:#op-notif-campana"'), true);
np('campana OOB: sin campana (plantilla vacia o sin el id)', array(opg_notif_v_campana_oob(''), opg_notif_v_campana_oob('<style>a{}</style>'), opg_notif_v_campana_oob('<div>otra</div>')), array('', '', ''));
$tpl_campana = file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_notificaciones_header.html');
np('campana OOB con la plantilla real: sin style y con el destino OOB', array(tiene(opg_notif_v_campana_oob($tpl_campana), '<style'), tiene(opg_notif_v_campana_oob($tpl_campana), 'hx-swap-oob="outerHTML:#op-notif-campana"')), array(false, true));

// ── Barra de filtros ─────────────────────────────────────────────────────────
$h = opg_notif_v_barra_filtros(array('categoria' => 'temas', 'origen' => 'usuario'));
np('filtros: opcion activa marcada', tiene($h, 'nt-filtro--activo" href="notificaciones.php?categoria=temas&amp;origen=usuario"') && substr_count($h, 'aria-current="true"') === 2, true);
np('filtros: Todas quita solo su filtro', tiene($h, 'href="notificaciones.php?origen=usuario"') && tiene($h, 'href="notificaciones.php?categoria=temas"'), true);
np('filtros: solo cambian #nt-contenido, no la campana', substr_count($h, 'hx-target="#nt-contenido"'), count(opg_notif_v_categorias()) + count(opg_notif_v_origenes()));
np('filtros: piden la accion listar y guardan la URL', tiene($h, 'hx-get="notificaciones.php?categoria=ficha&amp;origen=usuario&amp;action=listar"') && tiene($h, 'hx-push-url="notificaciones.php?categoria=ficha&amp;origen=usuario"'), true);
np('filtros: sin campana ni hx-swap-oob', tiene($h, 'op-notif-campana') || tiene($h, 'hx-swap-oob'), false);
np('filtros: sin hx-on', tiene($h, 'hx-on'), false);
np('filtros: etiquetas De gestion y De usuarios', tiene($h, '>De gestión<') && tiene($h, '>De usuarios<'), true);

// ── Plantilla y hoja de estilos ──────────────────────────────────────────────
$tpl = file_get_contents(MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/op_notificaciones.html');
np('plantilla: contenedor, avisos y post key', array(tiene($tpl, 'id="nt-contenido"'), tiene($tpl, 'id="nt-avisos"'), tiene($tpl, '{"X-Post-Key":"{$post_key}"}'), tiene($tpl, '{$contenido_html}')), array(true, true, true, true));
np('plantilla: HTMX sin eval y con la hoja de estilos', array(tiene($tpl, '"allowEval":false'), tiene($tpl, '"allowScriptTags":false'), tiene($tpl, 'jscripts/notificaciones.css?v=3'), tiene($tpl, 'name="viewport"')), array(true, true, true, true));
$css = file_get_contents(MYBB_ROOT . 'jscripts/notificaciones.css');
np('estilos: botones Visto y Marcar todas', array(tiene($css, '.nt-visto'), tiene($css, '.nt-todas')), array(true, true));
np('estilos: sin modo oscuro, movil y textos largos', array(tiene($css, 'prefers-color-scheme: dark') === false, tiene($css, '@media (max-width: 700px)'), tiene($css, 'overflow-wrap: anywhere')), array(true, true, true));
np('estilos: sin color-scheme ni reglas del chip para Marcar todas', array(tiene($css, 'color-scheme'), tiene($css, 'opg-chip.nt-todas')), array(false, false));
np('estilos: movil con objetivos de 44px', tiene($css, 'min-height: 44px'), true);
np('aviso de error usa el componente generico', tiene(opg_notif_v_aviso('x'), 'class="aviso err nt-aviso"'), true);
np('estado vacio usa opg-vacio', tiene(opg_notif_v_contenido(array('categoria' => '', 'origen' => ''), array('importantes' => $vacio, 'pendientes' => $vacio, 'historial' => $vacio), $ctx), '<p class="opg-vacio">No tienes notificaciones</p>'), true);

if ($fallos) {
    echo "FALLAS (" . count($fallos) . " de $total):\n" . implode("\n", $fallos) . "\n";
    exit(1);
}
echo "OK pagina de notificaciones (vistas): $total pruebas\n";
