<?php
/**
 * OPG - Configuración del foro (módulo contenedor)
 *
 * Pestaña de nivel superior entre "Configuración" y "Foros y mensajes",
 * pensada para ir alojando aquí los paneles que vayamos extrayendo del
 * código de las páginas del foro/juego (op/*, plantillas, etc.) a medida
 * que los vayamos necesitando, de forma que el staff pueda editar datos
 * fundamentales sin tocar archivos.
 *
 * Para añadir un panel nuevo: crear su archivo en este mismo directorio,
 * añadirlo a $sub_menu en forumconfig_meta() y a $actions en
 * forumconfig_action_handler() (y opcionalmente a $admin_permissions en
 * forumconfig_admin_permissions() si necesita permiso propio).
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function forumconfig_meta()
{
	global $page, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "index", "title" => "Resumen", "link" => "index.php?module=forumconfig");
	$sub_menu['20'] = array("id" => "facciones", "title" => "OPG Facciones", "link" => "index.php?module=forumconfig-facciones");
	$sub_menu['30'] = array("id" => "fama", "title" => "OPG Fama", "link" => "index.php?module=forumconfig-fama");
	$sub_menu['40'] = array("id" => "niveles", "title" => "OPG Niveles", "link" => "index.php?module=forumconfig-niveles");
	$sub_menu['50'] = array("id" => "costes", "title" => "OPG Costes", "link" => "index.php?module=forumconfig-costes");
	$sub_menu['60'] = array("id" => "catalogos", "title" => "OPG Catálogos", "link" => "index.php?module=forumconfig-catalogos");

	$sub_menu = $plugins->run_hooks("admin_forumconfig_menu", $sub_menu);

	$page->add_menu_item("Configuración del foro", "forumconfig", "index.php?module=forumconfig", 15, $sub_menu);

	return true;
}

function forumconfig_action_handler($action)
{
	global $page, $plugins;

	$page->active_module = "forumconfig";

	$actions = array(
		'index' => array('active' => 'index', 'file' => 'index.php'),
		'facciones' => array('active' => 'facciones', 'file' => 'facciones.php'),
		'fama' => array('active' => 'fama', 'file' => 'fama.php'),
		'niveles' => array('active' => 'niveles', 'file' => 'niveles.php'),
		'costes' => array('active' => 'costes', 'file' => 'costes.php'),
		'catalogos' => array('active' => 'catalogos', 'file' => 'catalogos.php'),
	);

	$actions = $plugins->run_hooks("admin_forumconfig_action_handler", $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "index";
		return "index.php";
	}
}

function forumconfig_admin_permissions()
{
	global $plugins;

	$admin_permissions = array(
		"index" => "Puede ver el resumen de \"Configuración del foro\"",
		"facciones" => "Puede gestionar facciones y rangos (OPG Facciones)",
		"fama" => "Puede gestionar los tramos de fama (OPG Fama)",
		"niveles" => "Puede gestionar la tabla de niveles (OPG Niveles)",
		"costes" => "Puede gestionar los costes numéricos de mejoras (OPG Costes)",
		"catalogos" => "Puede gestionar los catálogos de disciplinas y oficios (OPG Catálogos)",
	);

	$admin_permissions = $plugins->run_hooks("admin_forumconfig_permissions", $admin_permissions);

	return array("name" => "Configuración del foro", "permissions" => $admin_permissions, "disporder" => 15);
}
