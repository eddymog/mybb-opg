<?php
/**
 * OPG - Catálogos (panel de gestión)
 *
 * Gestiona los catálogos de nombres de inc/plugins/op_ficha.php: qué
 * disciplinas, oficios y estilos de combate existen, sus caminos/
 * especializaciones, y la imagen que se muestra en la ficha para cada uno.
 * Los costes numéricos asociados (por slot, por especialización, etc.) se
 * gestionan aparte en "OPG Costes".
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('OP_CATALOGOS_IMG_DIR', 'images/op/uploads/');
define('OP_CATALOGOS_IMG_SUFFIX', '_One_Piece_Gaiden_Foro_Rol.webp');

/**
 * Guarda un fichero subido como la imagen de una disciplina/oficio/estilo.
 * Mismo patrón que op_facciones_handle_image_upload() (facciones.php): el
 * nombre de destino lo decide siempre el servidor (nunca el nombre del
 * fichero subido), saneado a alfanumérico + prefijo de categoría, para que
 * las imágenes NUEVAS sigan una convención limpia y predecible (a
 * diferencia de varias imágenes heredadas, que no coinciden con el nombre
 * actual del catálogo — ver inc/plugins/op_ficha.php).
 *
 * @return string '' si no había fichero (no es un error), la ruta guardada
 *                si todo fue bien, o un mensaje de error si algo falló —
 *                el llamador distingue error de éxito con un simple
 *                strpos($resultado, '/') === 0.
 */
function op_catalogos_handle_image_upload($field, $nombre, $prefijo)
{
	if(empty($_FILES[$field]) || $_FILES[$field]['error'] == UPLOAD_ERR_NO_FILE)
	{
		return '';
	}

	if($_FILES[$field]['error'] != UPLOAD_ERR_OK)
	{
		return "ERROR:No se pudo subir la imagen (código de error {$_FILES[$field]['error']}).";
	}

	$ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
	if($ext != 'webp')
	{
		return "ERROR:La imagen tiene que ser un archivo .webp.";
	}

	if(!getimagesize($_FILES[$field]['tmp_name']))
	{
		return "ERROR:El archivo subido no es una imagen válida.";
	}

	$nombre_safe = preg_replace('/[^A-Za-z0-9]/', '', $nombre);
	if($nombre_safe == '')
	{
		return "ERROR:Nombre no válido para guardar la imagen.";
	}

	$archivo = $prefijo.$nombre_safe.OP_CATALOGOS_IMG_SUFFIX;
	$dest = MYBB_ROOT.OP_CATALOGOS_IMG_DIR.$archivo;
	if(!move_uploaded_file($_FILES[$field]['tmp_name'], $dest))
	{
		return "ERROR:No se pudo guardar la imagen en el servidor.";
	}

	return '/'.OP_CATALOGOS_IMG_DIR.$archivo;
}

/**
 * Miniatura de vista previa para una tabla del panel, o "(sin imagen)" si la
 * fila todavía no tiene ninguna asignada.
 */
function op_catalogos_image_preview($imagen)
{
	if($imagen == '')
	{
		return "<small>(sin imagen)</small>";
	}

	return "<img src=\"../".htmlspecialchars_uni(ltrim($imagen, '/'))."\" alt=\"\" style=\"max-height: 40px; max-width: 80px;\" />";
}

function op_catalogos_output_tabs($active)
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

if(!op_catalogos_tablas_listas($db))
{
	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Catálogos", "index.php?module=forumconfig-catalogos");
	$page->output_header("OPG Catálogos");
	op_catalogos_output_tabs('catalogos');
	$page->output_inline_error("Las tablas de catálogos todavía no existen. Instala y activa el plugin \"OPG - Ficha de personaje\" (Configuración → Plugins) para poder gestionarlas aquí.");
	$page->output_footer();
	exit;
}

function op_catalogos_tablas_listas($db)
{
	foreach(array('op_disciplinas', 'op_oficios_catalogo', 'op_estilos_catalogo') as $tabla)
	{
		if(!$db->table_exists($tabla))
		{
			return false;
		}
	}
	return true;
}

// --- Añadir disciplina ---
if($mybb->input['action'] == "adddisciplina" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$nombre = trim($mybb->get_input('nombre'));
	$camino1 = trim($mybb->get_input('camino1'));
	$camino2 = trim($mybb->get_input('camino2'));

	if($nombre == '' || $camino1 == '' || $camino2 == '')
	{
		flash_message("El nombre y los dos caminos son obligatorios.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$ya_existe = $db->fetch_field($db->simple_select('op_disciplinas', 'nombre', "nombre='".$db->escape_string($nombre)."'"), 'nombre');
	if($ya_existe)
	{
		flash_message("Ya existe una disciplina con ese nombre.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$max_orden = (int)$db->fetch_field($db->simple_select('op_disciplinas', 'MAX(orden) AS m'), 'm');

	$img_resultado = op_catalogos_handle_image_upload('imagen_file', $nombre, 'DisciFicha');
	$img_error = (strpos($img_resultado, 'ERROR:') === 0) ? substr($img_resultado, 6) : '';
	$imagen = ($img_error == '') ? $img_resultado : '';

	$db->insert_query('op_disciplinas', array(
		'nombre'  => $db->escape_string($nombre),
		'camino1' => $db->escape_string($camino1),
		'camino2' => $db->escape_string($camino2),
		'orden'   => $max_orden + 10,
		'imagen'  => $db->escape_string($imagen),
	));

	$mensaje = "Disciplina \"{$nombre}\" añadida. Recuerda: para que se pueda elegir de verdad hace falta que haya un slot libre con coste configurado en \"OPG Costes\".";
	if($img_error != '')
	{
		$mensaje .= " La imagen no se pudo guardar: {$img_error}";
	}
	flash_message($mensaje, 'success');
	admin_redirect("index.php?module=forumconfig-catalogos");
}

// --- Reemplazar solo la imagen de una disciplina existente ---
if($mybb->input['action'] == "subirimagendisciplina" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$nombre = $mybb->get_input('nombre');
	$fila = $db->fetch_array($db->simple_select('op_disciplinas', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$fila['nombre'])
	{
		flash_message("Esa disciplina no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$img_resultado = op_catalogos_handle_image_upload('imagen_file', $nombre, 'DisciFicha');
	if(strpos($img_resultado, 'ERROR:') === 0)
	{
		flash_message("No se pudo actualizar la imagen: ".substr($img_resultado, 6), 'error');
	}
	else if($img_resultado == '')
	{
		flash_message("No se seleccionó ningún archivo.", 'error');
	}
	else
	{
		$db->update_query('op_disciplinas', array('imagen' => $db->escape_string($img_resultado)), "nombre='".$db->escape_string($nombre)."'");
		flash_message("Imagen de \"{$nombre}\" actualizada.", 'success');
	}
	admin_redirect("index.php?module=forumconfig-catalogos");
}

// --- Eliminar disciplina ---
if($mybb->input['action'] == "deldisciplina")
{
	$nombre = $mybb->get_input('nombre');
	$fila = $db->fetch_array($db->simple_select('op_disciplinas', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$fila['nombre'])
	{
		flash_message("Esa disciplina no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query('op_disciplinas', "nombre='".$db->escape_string($nombre)."'");
		flash_message("Disciplina \"{$nombre}\" eliminada. Esto no afecta a personajes que ya la tuvieran.", 'success');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}
	else
	{
		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Catálogos", "index.php?module=forumconfig-catalogos");
		$page->output_confirm_action("index.php?module=forumconfig-catalogos&amp;action=deldisciplina&amp;nombre=".urlencode($nombre), "¿Seguro que quieres eliminar la disciplina \"{$nombre}\"?");
		exit;
	}
}

// --- Añadir oficio ---
if($mybb->input['action'] == "addoficio" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$nombre = trim($mybb->get_input('nombre'));
	$sub1 = trim($mybb->get_input('sub1'));
	$sub2 = trim($mybb->get_input('sub2'));

	if($nombre == '' || $sub1 == '' || $sub2 == '')
	{
		flash_message("El nombre y las dos especializaciones son obligatorios.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$ya_existe = $db->fetch_field($db->simple_select('op_oficios_catalogo', 'nombre', "nombre='".$db->escape_string($nombre)."'"), 'nombre');
	if($ya_existe)
	{
		flash_message("Ya existe un oficio con ese nombre.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$max_orden = (int)$db->fetch_field($db->simple_select('op_oficios_catalogo', 'MAX(orden) AS m'), 'm');

	$img_resultado = op_catalogos_handle_image_upload('imagen_file', $nombre, 'OficioFicha');
	$img_error = (strpos($img_resultado, 'ERROR:') === 0) ? substr($img_resultado, 6) : '';
	$imagen = ($img_error == '') ? $img_resultado : '';

	$db->insert_query('op_oficios_catalogo', array(
		'nombre' => $db->escape_string($nombre),
		'sub1'   => $db->escape_string($sub1),
		'sub2'   => $db->escape_string($sub2),
		'orden'  => $max_orden + 10,
		'imagen' => $db->escape_string($imagen),
	));

	$mensaje = "Oficio \"{$nombre}\" añadido.";
	if($img_error != '')
	{
		$mensaje .= " La imagen no se pudo guardar: {$img_error}";
	}
	flash_message($mensaje, 'success');
	admin_redirect("index.php?module=forumconfig-catalogos");
}

// --- Reemplazar solo la imagen de un oficio existente ---
if($mybb->input['action'] == "subirimagenoficio" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$nombre = $mybb->get_input('nombre');
	$fila = $db->fetch_array($db->simple_select('op_oficios_catalogo', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$fila['nombre'])
	{
		flash_message("Ese oficio no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$img_resultado = op_catalogos_handle_image_upload('imagen_file', $nombre, 'OficioFicha');
	if(strpos($img_resultado, 'ERROR:') === 0)
	{
		flash_message("No se pudo actualizar la imagen: ".substr($img_resultado, 6), 'error');
	}
	else if($img_resultado == '')
	{
		flash_message("No se seleccionó ningún archivo.", 'error');
	}
	else
	{
		$db->update_query('op_oficios_catalogo', array('imagen' => $db->escape_string($img_resultado)), "nombre='".$db->escape_string($nombre)."'");
		flash_message("Imagen de \"{$nombre}\" actualizada.", 'success');
	}
	admin_redirect("index.php?module=forumconfig-catalogos");
}

// --- Eliminar oficio ---
if($mybb->input['action'] == "deloficio")
{
	$nombre = $mybb->get_input('nombre');
	$fila = $db->fetch_array($db->simple_select('op_oficios_catalogo', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$fila['nombre'])
	{
		flash_message("Ese oficio no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query('op_oficios_catalogo', "nombre='".$db->escape_string($nombre)."'");
		flash_message("Oficio \"{$nombre}\" eliminado. Esto no afecta a personajes que ya lo tuvieran.", 'success');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}
	else
	{
		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Catálogos", "index.php?module=forumconfig-catalogos");
		$page->output_confirm_action("index.php?module=forumconfig-catalogos&amp;action=deloficio&amp;nombre=".urlencode($nombre), "¿Seguro que quieres eliminar el oficio \"{$nombre}\"?");
		exit;
	}
}

// --- Añadir estilo de combate ---
if($mybb->input['action'] == "addestilo" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$nombre = trim($mybb->get_input('nombre'));

	if($nombre == '')
	{
		flash_message("El nombre del estilo no puede estar vacío.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$ya_existe = $db->fetch_field($db->simple_select('op_estilos_catalogo', 'nombre', "nombre='".$db->escape_string($nombre)."'"), 'nombre');
	if($ya_existe)
	{
		flash_message("Ya existe un estilo con ese nombre.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$max_orden = (int)$db->fetch_field($db->simple_select('op_estilos_catalogo', 'MAX(orden) AS m'), 'm');

	$img_resultado = op_catalogos_handle_image_upload('imagen_file', $nombre, 'Ficha');
	$img_error = (strpos($img_resultado, 'ERROR:') === 0) ? substr($img_resultado, 6) : '';
	$imagen = ($img_error == '') ? $img_resultado : '';

	$db->insert_query('op_estilos_catalogo', array(
		'nombre' => $db->escape_string($nombre),
		'imagen' => $db->escape_string($imagen),
		'orden'  => $max_orden + 10,
	));

	$mensaje = "Estilo \"{$nombre}\" añadido. No tiene ninguna disponibilidad especial por raza/facción configurada — aparecerá disponible para todo el mundo salvo que se le añada esa lógica aparte.";
	if($img_error != '')
	{
		$mensaje .= " La imagen no se pudo guardar: {$img_error}";
	}
	flash_message($mensaje, 'success');
	admin_redirect("index.php?module=forumconfig-catalogos");
}

// --- Reemplazar solo la imagen de un estilo existente ---
if($mybb->input['action'] == "subirimagenestilo" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$nombre = $mybb->get_input('nombre');
	$fila = $db->fetch_array($db->simple_select('op_estilos_catalogo', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$fila['nombre'])
	{
		flash_message("Ese estilo no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	$img_resultado = op_catalogos_handle_image_upload('imagen_file', $nombre, 'Ficha');
	if(strpos($img_resultado, 'ERROR:') === 0)
	{
		flash_message("No se pudo actualizar la imagen: ".substr($img_resultado, 6), 'error');
	}
	else if($img_resultado == '')
	{
		flash_message("No se seleccionó ningún archivo.", 'error');
	}
	else
	{
		$db->update_query('op_estilos_catalogo', array('imagen' => $db->escape_string($img_resultado)), "nombre='".$db->escape_string($nombre)."'");
		flash_message("Imagen de \"{$nombre}\" actualizada.", 'success');
	}
	admin_redirect("index.php?module=forumconfig-catalogos");
}

// --- Eliminar estilo ---
if($mybb->input['action'] == "delestilo")
{
	$nombre = $mybb->get_input('nombre');
	$fila = $db->fetch_array($db->simple_select('op_estilos_catalogo', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$fila['nombre'])
	{
		flash_message("Ese estilo no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-catalogos");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query('op_estilos_catalogo', "nombre='".$db->escape_string($nombre)."'");
		flash_message("Estilo \"{$nombre}\" eliminado. Esto no afecta a personajes que ya lo tuvieran aprendido.", 'success');
		admin_redirect("index.php?module=forumconfig-catalogos");
	}
	else
	{
		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Catálogos", "index.php?module=forumconfig-catalogos");
		$page->output_confirm_action("index.php?module=forumconfig-catalogos&amp;action=delestilo&amp;nombre=".urlencode($nombre), "¿Seguro que quieres eliminar el estilo \"{$nombre}\"?");
		exit;
	}
}

// --- Vista por defecto: catálogos ---
$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
$page->add_breadcrumb_item("OPG Catálogos", "index.php?module=forumconfig-catalogos");
$page->output_header("OPG Catálogos");

op_catalogos_output_tabs('catalogos');

echo "<p><small>Los costes en nikas/puntos asociados a cada slot o especialización se gestionan en <a href=\"index.php?module=forumconfig-costes\">OPG Costes</a>. Aquí solo los nombres y caminos/especializaciones disponibles.</small></p>";

// Catálogo de disciplinas
$table = new Table;
$table->construct_header("Nombre");
$table->construct_header("Camino 1");
$table->construct_header("Camino 2");
$table->construct_header("Imagen", array("width" => 100, "class" => "align_center"));
$table->construct_header("Acciones", array("width" => 160, "class" => "align_center"));
$query = $db->simple_select('op_disciplinas', '*', '', array('order_by' => 'orden'));
while($fila = $db->fetch_array($query))
{
	$upload_form = "<form action=\"index.php?module=forumconfig-catalogos&amp;action=subirimagendisciplina\" method=\"post\" enctype=\"multipart/form-data\" style=\"margin: 4px 0 0;\">"
		."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
		."<input type=\"hidden\" name=\"nombre\" value=\"".htmlspecialchars_uni($fila['nombre'])."\" />"
		."<input type=\"file\" name=\"imagen_file\" accept=\".webp\" style=\"max-width: 110px;\" />"
		."<input type=\"submit\" class=\"button\" value=\"Subir\" />"
		."</form>";

	$table->construct_cell(htmlspecialchars_uni($fila['nombre']));
	$table->construct_cell(htmlspecialchars_uni($fila['camino1']));
	$table->construct_cell(htmlspecialchars_uni($fila['camino2']));
	$table->construct_cell(op_catalogos_image_preview($fila['imagen']), array("class" => "align_center"));
	$table->construct_cell($upload_form."<a href=\"index.php?module=forumconfig-catalogos&amp;action=deldisciplina&amp;nombre=".urlencode($fila['nombre'])."\">Eliminar</a>", array("class" => "align_center"));
	$table->construct_row();
}
$table->output("Catálogo de disciplinas");

echo "<br />";
$form = new Form("index.php?module=forumconfig-catalogos&amp;action=adddisciplina", "post", "", true);
$form_container = new FormContainer("Añadir disciplina");
$form_container->output_row("Nombre", "", $form->generate_text_box('nombre', '', array('id' => 'nombre')), 'nombre');
$form_container->output_row("Camino 1", "", $form->generate_text_box('camino1', '', array('id' => 'camino1')), 'camino1');
$form_container->output_row("Camino 2", "", $form->generate_text_box('camino2', '', array('id' => 'camino2')), 'camino2');
$form_container->output_row("Imagen (opcional)", "Archivo .webp. Puedes subirla ahora o más tarde desde la tabla de arriba.", $form->generate_file_upload_box('imagen_file'), 'imagen_file');
$form_container->end();
echo $form->generate_submit_button("Añadir disciplina");
echo $form->end();

echo "<br /><br />";

// Catálogo de oficios
$table = new Table;
$table->construct_header("Nombre");
$table->construct_header("Especialización 1");
$table->construct_header("Especialización 2");
$table->construct_header("Imagen", array("width" => 100, "class" => "align_center"));
$table->construct_header("Acciones", array("width" => 160, "class" => "align_center"));
$query = $db->simple_select('op_oficios_catalogo', '*', '', array('order_by' => 'orden'));
while($fila = $db->fetch_array($query))
{
	$upload_form = "<form action=\"index.php?module=forumconfig-catalogos&amp;action=subirimagenoficio\" method=\"post\" enctype=\"multipart/form-data\" style=\"margin: 4px 0 0;\">"
		."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
		."<input type=\"hidden\" name=\"nombre\" value=\"".htmlspecialchars_uni($fila['nombre'])."\" />"
		."<input type=\"file\" name=\"imagen_file\" accept=\".webp\" style=\"max-width: 110px;\" />"
		."<input type=\"submit\" class=\"button\" value=\"Subir\" />"
		."</form>";

	$table->construct_cell(htmlspecialchars_uni($fila['nombre']));
	$table->construct_cell(htmlspecialchars_uni($fila['sub1']));
	$table->construct_cell(htmlspecialchars_uni($fila['sub2']));
	$table->construct_cell(op_catalogos_image_preview($fila['imagen']), array("class" => "align_center"));
	$table->construct_cell($upload_form."<a href=\"index.php?module=forumconfig-catalogos&amp;action=deloficio&amp;nombre=".urlencode($fila['nombre'])."\">Eliminar</a>", array("class" => "align_center"));
	$table->construct_row();
}
$table->output("Catálogo de oficios");

echo "<br />";
$form = new Form("index.php?module=forumconfig-catalogos&amp;action=addoficio", "post", "", true);
$form_container = new FormContainer("Añadir oficio");
$form_container->output_row("Nombre", "", $form->generate_text_box('nombre', '', array('id' => 'nombre_of')), 'nombre_of');
$form_container->output_row("Especialización 1", "", $form->generate_text_box('sub1', '', array('id' => 'sub1')), 'sub1');
$form_container->output_row("Especialización 2", "", $form->generate_text_box('sub2', '', array('id' => 'sub2')), 'sub2');
$form_container->output_row("Imagen (opcional)", "Archivo .webp. Puedes subirla ahora o más tarde desde la tabla de arriba.", $form->generate_file_upload_box('imagen_file'), 'imagen_file');
$form_container->end();
echo $form->generate_submit_button("Añadir oficio");
echo $form->end();

echo "<br /><br /><hr /><br />";

// Catálogo de estilos de combate
$table = new Table;
$table->construct_header("Nombre");
$table->construct_header("Imagen", array("width" => 100, "class" => "align_center"));
$table->construct_header("Acciones", array("width" => 160, "class" => "align_center"));
$query = $db->simple_select('op_estilos_catalogo', '*', '', array('order_by' => 'orden'));
while($fila = $db->fetch_array($query))
{
	$upload_form = "<form action=\"index.php?module=forumconfig-catalogos&amp;action=subirimagenestilo\" method=\"post\" enctype=\"multipart/form-data\" style=\"margin: 4px 0 0;\">"
		."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
		."<input type=\"hidden\" name=\"nombre\" value=\"".htmlspecialchars_uni($fila['nombre'])."\" />"
		."<input type=\"file\" name=\"imagen_file\" accept=\".webp\" style=\"max-width: 110px;\" />"
		."<input type=\"submit\" class=\"button\" value=\"Subir\" />"
		."</form>";

	$table->construct_cell(htmlspecialchars_uni($fila['nombre']));
	$table->construct_cell(op_catalogos_image_preview($fila['imagen']), array("class" => "align_center"));
	$table->construct_cell($upload_form."<a href=\"index.php?module=forumconfig-catalogos&amp;action=delestilo&amp;nombre=".urlencode($fila['nombre'])."\">Eliminar</a>", array("class" => "align_center"));
	$table->construct_row();
}
$table->output("Catálogo de estilos de combate");

echo "<p><small>La disponibilidad por raza/facción (p. ej. estilos exclusivos de Gyojin, CipherPol o Revolucionario) no se gestiona aquí — es lógica del propio selector de la ficha. Un estilo nuevo añadido desde este panel aparece disponible para todo el mundo salvo que se le añada esa restricción aparte.</small></p>";

echo "<br />";
$form = new Form("index.php?module=forumconfig-catalogos&amp;action=addestilo", "post", "", true);
$form_container = new FormContainer("Añadir estilo de combate");
$form_container->output_row("Nombre", "", $form->generate_text_box('nombre', '', array('id' => 'nombre_es')), 'nombre_es');
$form_container->output_row("Imagen (opcional)", "Archivo .webp. Puedes subirla ahora o más tarde desde la tabla de arriba.", $form->generate_file_upload_box('imagen_file'), 'imagen_file');
$form_container->end();
echo $form->generate_submit_button("Añadir estilo");
echo $form->end();

$page->output_footer();
