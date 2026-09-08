<?php
/**
 * OPG - Configuración del foro (resumen)
 *
 * Página de aterrizaje de la pestaña "Configuración del foro". De momento
 * es solo un índice explicativo — los paneles concretos (extraídos poco a
 * poco del código de op/*, plantillas, etc.) se van añadiendo aquí mismo,
 * cada uno como su propia pestaña secundaria.
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
$page->output_header("Configuración del foro");

$sub_tabs['index'] = array(
	'title' => "Resumen",
	'link' => "index.php?module=forumconfig",
	'description' => "Paneles para editar datos fundamentales del foro/juego sin tocar código."
);
$sub_tabs['facciones'] = array(
	'title' => "OPG Facciones",
	'link' => "index.php?module=forumconfig-facciones",
	'description' => "Crea o elimina facciones y gestiona sus rangos e imágenes."
);
$sub_tabs['fama'] = array(
	'title' => "OPG Fama",
	'link' => "index.php?module=forumconfig-fama",
	'description' => "Gestiona los tramos de fama según la reputación del personaje."
);
$sub_tabs['niveles'] = array(
	'title' => "OPG Niveles",
	'link' => "index.php?module=forumconfig-niveles",
	'description' => "Experiencia mínima/máxima y puntos de bonus por nivel."
);
$sub_tabs['costes'] = array(
	'title' => "OPG Costes",
	'link' => "index.php?module=forumconfig-costes",
	'description' => "Costes de haki, akuma, disciplinas, estilos, oficio y niveles requeridos."
);
$sub_tabs['catalogos'] = array(
	'title' => "OPG Catálogos",
	'link' => "index.php?module=forumconfig-catalogos",
	'description' => "Catálogos de disciplinas y oficios (nombres, caminos y especializaciones)."
);

$sub_tabs = $plugins->run_hooks("admin_forumconfig_index_tabs", $sub_tabs);

$page->output_nav_tabs($sub_tabs, 'index');

echo "
<div class=\"thin\">
	<p>Aquí se irán añadiendo, poco a poco, paneles para editar directamente desde el panel de administración datos que hoy están fijados en el código de las páginas del foro (bajo <code>/op/</code>, plantillas, etc.) — sin tener que tocar ningún archivo cada vez.</p>
	<p>Cuando se añada un panel nuevo, aparecerá como una pestaña más aquí al lado de \"Resumen\".</p>
</div>";

$page->output_footer();
