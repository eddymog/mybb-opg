<?php
/**
 * OPG - Fama (panel de gestión)
 *
 * Gestiona la tabla mybb_op_fama (creada por inc/plugins/op_fama.php),
 * antes un array hardcodeado en op/personaje.php. Cada fila es un tramo de
 * reputación: si la reputación del personaje es >= reputacion_min, se usa
 * ese tramo (el de mayor reputacion_min que cumpla); dentro de él, el % de
 * reputación positiva decide cuál de los 3 nombres se muestra.
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function op_fama_output_tabs($active)
{
	global $page;

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

	$page->output_nav_tabs($sub_tabs, $active);
}

if(!$db->table_exists('op_fama'))
{
	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Fama", "index.php?module=forumconfig-fama");
	$page->output_header("OPG Fama");
	op_fama_output_tabs('fama');
	$page->output_inline_error("La tabla de fama todavía no existe. Instala y activa el plugin \"OPG - Fama (BD)\" (Configuración → Plugins) para poder gestionarla aquí.");
	$page->output_footer();
	exit;
}

// --- Añadir tramo ---
if($mybb->input['action'] == "addfama" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-fama");
	}

	$nombre_bueno = trim($mybb->get_input('nombre_bueno'));
	$nombre_neutral = trim($mybb->get_input('nombre_neutral'));
	$nombre_malo = trim($mybb->get_input('nombre_malo'));

	if($nombre_bueno == '' || $nombre_neutral == '' || $nombre_malo == '')
	{
		flash_message("Los 3 nombres del tramo son obligatorios.", 'error');
		admin_redirect("index.php?module=forumconfig-fama");
	}

	$db->insert_query('op_fama', array(
		'reputacion_min' => $mybb->get_input('reputacion_min', MyBB::INPUT_INT),
		'perc_malo'      => $mybb->get_input('perc_malo', MyBB::INPUT_INT),
		'perc_bueno'     => $mybb->get_input('perc_bueno', MyBB::INPUT_INT),
		'nombre_bueno'   => $db->escape_string($nombre_bueno),
		'nombre_neutral' => $db->escape_string($nombre_neutral),
		'nombre_malo'    => $db->escape_string($nombre_malo),
	));

	flash_message("Tramo de fama añadido.", 'success');
	admin_redirect("index.php?module=forumconfig-fama");
}

// --- Guardar cambios de un tramo (envío del formulario de editfama) ---
if($mybb->input['action'] == "guardarfama" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-fama");
	}

	$id = $mybb->get_input('id', MyBB::INPUT_INT);
	$existe = $db->fetch_field($db->simple_select('op_fama', 'id', "id='{$id}'"), 'id');

	if(!$existe)
	{
		flash_message("Ese tramo de fama no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-fama");
	}

	$nombre_bueno = trim($mybb->get_input('nombre_bueno'));
	$nombre_neutral = trim($mybb->get_input('nombre_neutral'));
	$nombre_malo = trim($mybb->get_input('nombre_malo'));

	if($nombre_bueno == '' || $nombre_neutral == '' || $nombre_malo == '')
	{
		flash_message("Los 3 nombres del tramo son obligatorios.", 'error');
		admin_redirect("index.php?module=forumconfig-fama&amp;action=editfama&amp;id={$id}");
	}

	$db->update_query('op_fama', array(
		'reputacion_min' => $mybb->get_input('reputacion_min', MyBB::INPUT_INT),
		'perc_malo'      => $mybb->get_input('perc_malo', MyBB::INPUT_INT),
		'perc_bueno'     => $mybb->get_input('perc_bueno', MyBB::INPUT_INT),
		'nombre_bueno'   => $db->escape_string($nombre_bueno),
		'nombre_neutral' => $db->escape_string($nombre_neutral),
		'nombre_malo'    => $db->escape_string($nombre_malo),
	), "id='{$id}'");

	flash_message("Tramo de fama actualizado.", 'success');
	admin_redirect("index.php?module=forumconfig-fama");
}

// --- Eliminar tramo ---
if($mybb->input['action'] == "delfama")
{
	$id = $mybb->get_input('id', MyBB::INPUT_INT);
	$fama_row = $db->fetch_array($db->simple_select('op_fama', '*', "id='{$id}'"));

	if(!$fama_row['id'])
	{
		flash_message("Ese tramo de fama no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-fama");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-fama");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query('op_fama', "id='{$id}'");

		flash_message("Tramo de fama \"{$fama_row['nombre_neutral']}\" eliminado.", 'success');
		admin_redirect("index.php?module=forumconfig-fama");
	}
	else
	{
		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Fama", "index.php?module=forumconfig-fama");
		$page->output_confirm_action("index.php?module=forumconfig-fama&amp;action=delfama&amp;id={$id}", "¿Seguro que quieres eliminar el tramo de fama \"{$fama_row['nombre_neutral']}\" (reputación mínima {$fama_row['reputacion_min']})?");
		exit;
	}
}

// --- Vista: editar un tramo ---
if($mybb->input['action'] == "editfama")
{
	$id = $mybb->get_input('id', MyBB::INPUT_INT);
	$fama_row = $db->fetch_array($db->simple_select('op_fama', '*', "id='{$id}'"));

	if(!$fama_row['id'])
	{
		flash_message("Ese tramo de fama no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-fama");
	}

	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Fama", "index.php?module=forumconfig-fama");
	$page->add_breadcrumb_item("Editar tramo");
	$page->output_header("OPG Fama — Editar tramo");

	op_fama_output_tabs('fama');

	echo "<p><a href=\"index.php?module=forumconfig-fama\">&laquo; Volver a la lista de tramos</a></p>";

	$form = new Form("index.php?module=forumconfig-fama&amp;action=guardarfama", "post");
	echo $form->generate_hidden_field('id', $fama_row['id']);

	$form_container = new FormContainer("Editar tramo de fama");
	$form_container->output_row("Reputación mínima", "El personaje necesita al menos esta reputación (ya con los modificadores de virtud/defecto aplicados) para entrar en este tramo.", $form->generate_numeric_field('reputacion_min', $fama_row['reputacion_min'], array('id' => 'reputacion_min')), 'reputacion_min');
	$form_container->output_row("% malo (o menos)", "Si el % de reputación positiva es igual o menor, se usa el nombre malo.", $form->generate_numeric_field('perc_malo', $fama_row['perc_malo'], array('id' => 'perc_malo')), 'perc_malo');
	$form_container->output_row("% bueno (o más)", "Si el % de reputación positiva es igual o mayor, se usa el nombre bueno.", $form->generate_numeric_field('perc_bueno', $fama_row['perc_bueno'], array('id' => 'perc_bueno')), 'perc_bueno');
	$form_container->output_row("Nombre bueno", "", $form->generate_text_box('nombre_bueno', $fama_row['nombre_bueno'], array('id' => 'nombre_bueno')), 'nombre_bueno');
	$form_container->output_row("Nombre neutral", "", $form->generate_text_box('nombre_neutral', $fama_row['nombre_neutral'], array('id' => 'nombre_neutral')), 'nombre_neutral');
	$form_container->output_row("Nombre malo", "", $form->generate_text_box('nombre_malo', $fama_row['nombre_malo'], array('id' => 'nombre_malo')), 'nombre_malo');
	$form_container->end();

	echo $form->generate_submit_button("Guardar cambios");
	echo $form->end();

	$page->output_footer();
	exit;
}

// --- Vista por defecto: lista de tramos ---
$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
$page->add_breadcrumb_item("OPG Fama", "index.php?module=forumconfig-fama");
$page->output_header("OPG Fama");

op_fama_output_tabs('fama');

echo "<p><small>Cada tramo se usa si la reputación del personaje es mayor o igual a \"Reputación mínima\" — se comprueban de mayor a menor y se aplica el primero que cumpla. Dentro del tramo: si el % de reputación positiva es mayor o igual a \"% bueno\" se usa el nombre bueno; si es menor o igual a \"% malo\" se usa el nombre malo; si no, el neutral.</small></p>";

$table = new Table;
$table->construct_header("Reputación mín.", array("width" => 100));
$table->construct_header("% malo", array("width" => 70));
$table->construct_header("% bueno", array("width" => 70));
$table->construct_header("Nombre bueno");
$table->construct_header("Nombre neutral");
$table->construct_header("Nombre malo");
$table->construct_header("Acciones", array("width" => 140, "class" => "align_center"));

$query = $db->simple_select('op_fama', '*', '', array('order_by' => 'reputacion_min', 'order_dir' => 'DESC'));
$any = false;
while($fama_row = $db->fetch_array($query))
{
	$any = true;

	$table->construct_cell($fama_row['reputacion_min']);
	$table->construct_cell($fama_row['perc_malo']);
	$table->construct_cell($fama_row['perc_bueno']);
	$table->construct_cell(htmlspecialchars_uni($fama_row['nombre_bueno']));
	$table->construct_cell(htmlspecialchars_uni($fama_row['nombre_neutral']));
	$table->construct_cell(htmlspecialchars_uni($fama_row['nombre_malo']));
	$table->construct_cell(
		"<a href=\"index.php?module=forumconfig-fama&amp;action=editfama&amp;id={$fama_row['id']}\">Editar</a>"
		." | <a href=\"index.php?module=forumconfig-fama&amp;action=delfama&amp;id={$fama_row['id']}\">Eliminar</a>",
		array("class" => "align_center")
	);
	$table->construct_row();
}

if(!$any)
{
	$table->construct_cell("No hay tramos de fama todavía.", array("colspan" => 7));
	$table->construct_row();
}

$table->output("Tramos de fama");

echo "<br />";

$form = new Form("index.php?module=forumconfig-fama&amp;action=addfama", "post");

$form_container = new FormContainer("Añadir tramo de fama");
$form_container->output_row("Reputación mínima", "El personaje necesita al menos esta reputación (ya con los modificadores de virtud/defecto aplicados) para entrar en este tramo.", $form->generate_numeric_field('reputacion_min', 0, array('id' => 'reputacion_min')), 'reputacion_min');
$form_container->output_row("% malo (o menos)", "Si el % de reputación positiva es igual o menor, se usa el nombre malo.", $form->generate_numeric_field('perc_malo', 20, array('id' => 'perc_malo')), 'perc_malo');
$form_container->output_row("% bueno (o más)", "Si el % de reputación positiva es igual o mayor, se usa el nombre bueno.", $form->generate_numeric_field('perc_bueno', 80, array('id' => 'perc_bueno')), 'perc_bueno');
$form_container->output_row("Nombre bueno", "", $form->generate_text_box('nombre_bueno', '', array('id' => 'nombre_bueno')), 'nombre_bueno');
$form_container->output_row("Nombre neutral", "", $form->generate_text_box('nombre_neutral', '', array('id' => 'nombre_neutral')), 'nombre_neutral');
$form_container->output_row("Nombre malo", "", $form->generate_text_box('nombre_malo', '', array('id' => 'nombre_malo')), 'nombre_malo');
$form_container->end();

echo $form->generate_submit_button("Añadir tramo");
echo $form->end();

$page->output_footer();
