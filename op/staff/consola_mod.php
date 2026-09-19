<?php
/**
 * Staff - Consola de herramientas
 *
 * Portada de /op/staff/. La lista de herramientas vive en $consola_grupos (más
 * abajo): para añadir una, agregar una línea ahí. Cada acción puede pedir un
 * permiso más alto que el de la consola ('staff' o 'admin'); si el usuario no
 * lo tiene, no se le muestra. La plantilla staff_consola_mod solo pinta el marco.
 * Iconos: Font Awesome 6 (clases fa-solid fa-*), cargado por la propia plantilla.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'consola_mod.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

// ── Herramientas ────────────────────────────────────────────────────────────
// Grupo => [acento, tarjetas]. Tarjeta: [icono, título, descripción, [[acción, archivo, permiso?], ...]]
// El acento es una variable de opg-tokens.css; da color a la raya del título y a las tarjetas del grupo.

$consola_grupos = array(
    'Recompensas y pagos' => array('dorado', array(
        array('fa-gift', 'Recompensas', 'Entregar las recompensas de temas terminados.', array(
            array('Autonarradas', 'entregar_recompensas.php'),
            array('Aventuras', 'entregar_recompensas_aventuras.php'),
            array('Narradores', 'entregar_recompensas_narradores.php'),
        )),
        array('fa-coins', 'Salarios', 'Pagos periódicos a facciones y al staff.', array(
            array('Facciones', 'salario_faccion.php'),
            array('Staff (kuros)', 'salario_staff.php', 'admin'),
        )),
    )),
    'Personajes' => array('morado', array(
        array('fa-sliders', 'Atributos de ficha', 'Stats, oficios, bélicas y elementos de un personaje.', array(
            array('Modificar', 'ficha_atributos.php'),
        )),
        array('fa-box-open', 'Inventario y habilidades', 'Lo que tiene asignado un personaje concreto.', array(
            array('Objetos', 'objetos_ficha.php'),
            array('Técnicas', 'tecnicas_ficha.php'),
            array('Virtudes', 'virtudes_ficha.php'),
        )),
        array('fa-skull', 'Reencarnaciones', 'Reencarnar un personaje muerto en una ficha nueva.', array(
            array('Gestionar', 'resurreciones.php'),
        )),
        array('fa-user-plus', 'Referidos', 'Registrar quién trajo a un usuario nuevo.', array(
            array('Ingresar', 'referidos.php'),
        )),
    )),
    'Contenido del juego' => array('naranja', array(
        array('fa-gem', 'Objetos', 'Catálogo de objetos del juego.', array(
            array('Crear / modificar', 'objetos_modificar.php'),
        )),
        array('fa-hand-fist', 'Técnicas únicas', 'Catálogo de técnicas únicas.', array(
            array('Crear / modificar', 'tecnicas_modificar.php'),
        )),
        array('fa-apple-whole', 'Akumas', 'Frutas del diablo: alta, edición y caducidad.', array(
            array('Crear / modificar', 'akumas_modificar.php'),
            array('Inactivas', 'akumas_inactivas.php'),
        )),
        array('fa-scale-balanced', 'Virtudes y defectos', 'Catálogo de virtudes y defectos.', array(
            array('Crear / modificar', 'virtudes_modificar.php'),
        )),
        array('fa-users', 'NPCs', 'Personajes no jugadores del mundo.', array(
            array('Crear / modificar', 'npcs_modificar.php'),
            array('Generador', 'generadorpj.php'),
        )),
        array('fa-map-location-dot', 'Islas', 'Datos de las islas del mapa.', array(
            array('Modificar', 'islas_modificar.php'),
        )),
    )),
    'Foro' => array('azul', array(
        array('fa-image', 'Banners del header', 'Rotación y banner fijo de la cabecera.', array(
            array('Gestionar', 'banners.php', 'admin'),
        )),
        array('fa-lightbulb', 'Sabías qué', 'Curiosidades del índice.', array(
            array('Modificar', 'sabiasque_modificar.php'),
        )),
        array('fa-cloud-arrow-up', 'Hosting de imágenes', 'Subir imágenes y copiar su URL.', array(
            array('Subir', 'upload.php'),
        )),
        array('fa-calendar-days', 'Temas', 'Fecha in-game de un tema y temas abiertos por usuario.', array(
            array('Editar fecha', 'editar_tema.php'),
        )),
        array('fa-handshake', 'Afiliados', 'Foros afiliados y sus peticiones.', array(
            array('Gestionar', 'gestionar_afiliados.php'),
        )),
    )),
    'Registros' => array('gris', array(
        array('fa-clipboard-list', 'Log de consola', 'Cambios hechos desde las herramientas de staff.', array(
            array('Ver', 'log_consola_mod.php'),
        )),
        array('fa-receipt', 'Log de entregas', 'Recompensas entregadas.', array(
            array('Ver', 'log_entregas.php'),
        )),
    )),
);

// Acento => [color de fondo, color del icono encima]
$consola_acentos = array(
    'dorado'  => array('var(--opg-dorado)', 'var(--opg-tinta)'),
    'morado'  => array('var(--opg-morado)', '#fff'),
    'naranja' => array('var(--opg-naranja)', '#fff'),
    'azul'    => array('var(--opg-azul)', '#fff'),
    'gris'    => array('var(--opg-gris-bloqueado)', '#fff'),
);

function consola_puede($permiso)
{
    global $uid;
    if ($permiso === 'admin') return is_admin($uid);
    if ($permiso === 'staff') return is_staff($uid);
    return true; // ya pasó el control de acceso de la consola
}

function consola_e($texto)
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function consola_icono($icono)
{
    return '<i class="fa-solid ' . consola_e($icono) . '" aria-hidden="true"></i>';
}

// ── Render ──────────────────────────────────────────────────────────────────

$consola_usuario = consola_e($mybb->user['username']);
$consola_rol     = is_admin($uid) ? 'Admin' : (is_staff($uid) ? 'Staff' : 'Mod');

$consola_secciones = '';
foreach ($consola_grupos as $grupo => $datos) {
    list($acento, $tarjetas) = $datos;
    list($fondo, $texto) = $consola_acentos[$acento];

    $tarjetas_html = '';
    foreach ($tarjetas as $t) {
        list($icono, $titulo, $descripcion, $acciones) = $t;

        $visibles = array();
        foreach ($acciones as $a) {
            if (consola_puede(isset($a[2]) ? $a[2] : '')) {
                $visibles[] = $a;
            }
        }
        if (!$visibles) {
            continue; // ninguna acción visible para este usuario
        }

        $buscar = consola_e($titulo . ' ' . $descripcion . ' ' . implode(' ', array_map(function ($a) { return $a[0]; }, $visibles)));
        $cabecera = '<span class="opg-card__cabecera"><span class="opg-card__icono">' . consola_icono($icono) . '</span>'
            . '<span class="opg-card__titulo">' . consola_e($titulo) . '</span></span>'
            . '<span class="opg-card__desc">' . consola_e($descripcion) . '</span>';

        if (count($visibles) === 1) {
            // Una sola acción: la tarjeta entera es el enlace.
            $tarjetas_html .= '<a class="opg-card herramienta" href="/op/staff/' . consola_e($visibles[0][1]) . '" data-buscar="' . $buscar . '">'
                . $cabecera . '<span class="herramienta-ir">' . consola_e($visibles[0][0]) . ' <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></a>';
        } else {
            $chips = '';
            foreach ($visibles as $a) {
                $chips .= '<a class="opg-chip" href="/op/staff/' . consola_e($a[1]) . '">' . consola_e($a[0]) . '</a>';
            }
            $tarjetas_html .= '<div class="opg-card herramienta" data-buscar="' . $buscar . '">'
                . $cabecera . '<span class="herramienta-acciones">' . $chips . '</span></div>';
        }
    }
    if ($tarjetas_html === '') {
        continue;
    }
    $consola_secciones .= '<section class="grupo" style="--opg-card-acento: ' . $fondo . '; --opg-card-acento-texto: ' . $texto . ';">'
        . '<h2 class="grupo-titulo">' . consola_e($grupo) . '</h2>'
        . '<div class="grid-herramientas">' . $tarjetas_html . '</div>'
        . '</section>';
}

eval("\$page = \"".$templates->get("staff_consola_mod")."\";");
output_page($page);
