<?php
/**
 * OPG - Facciones y rangos (panel de gestión)
 *
 * Gestiona las tablas mybb_op_facciones / mybb_op_facciones_rangos (creadas
 * por inc/plugins/op_facciones.php). Permite crear/eliminar facciones y,
 * dentro de cada una, añadir/quitar rangos y subir la imagen que se muestra
 * en la ficha para ese rango.
 *
 * Alcance de esta primera versión: gestiona los DATOS. No reescribe todavía
 * los desplegables fijos de creación de ficha ni del editor de staff (eso
 * sigue funcionando exactamente igual que antes) — lo que sí surte efecto
 * de inmediato es subir/reemplazar la imagen de un rango ya existente,
 * porque se guarda en la misma ruta que la ficha ya lee
 * (/images/op/rangos/{valor}_One_Piece_Gaiden_Foro_Rol.webp).
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('OP_FACCIONES_IMG_DIR', 'images/op/rangos/');
define('OP_FACCIONES_IMG_SUFFIX', '_One_Piece_Gaiden_Foro_Rol.webp');

/**
 * Columnas de color de mybb_op_facciones => etiqueta para el formulario.
 * Definida aquí (no en el plugin op_facciones.php) para que este panel siga
 * funcionando aunque el plugin estuviera desactivado — solo necesita que
 * las columnas ya existan en la tabla, igual que el resto de este archivo.
 */
function op_facciones_color_field_labels()
{
	return array(
		'color_faccion'     => 'Color de facción',
		'color_rombo'       => 'Color de rombo',
		'color_border_tag'  => 'Color de borde (tag)',
		'color_rango'       => 'Color de rango (admite gradient CSS)',
		'color_border'      => 'Color de borde',
		'color_border_pill' => 'Color de borde (pill)',
		'color_texto'       => 'Color de texto (nombre de usuario)',
		'color_chat'        => 'Color de fondo en el chatbox',
	);
}

function op_facciones_output_tabs($active)
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

/**
 * Guarda un fichero subido como la imagen de un rango, en la ruta exacta
 * que la ficha ya lee hoy. $valor se sanea a alfanumérico antes de usarse
 * en el nombre de archivo, así que el destino siempre lo decide el propio
 * servidor, nunca directamente el nombre del fichero subido por el staff.
 *
 * @return string '' si no había fichero (no es un error), o un mensaje de error si algo fue mal.
 */
function op_facciones_handle_image_upload($field, $valor)
{
	if(empty($_FILES[$field]) || $_FILES[$field]['error'] == UPLOAD_ERR_NO_FILE)
	{
		return '';
	}

	if($_FILES[$field]['error'] != UPLOAD_ERR_OK)
	{
		return "No se pudo subir la imagen (código de error {$_FILES[$field]['error']}).";
	}

	$ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
	if($ext != 'webp')
	{
		return "La imagen tiene que ser un archivo .webp (igual que el resto de imágenes de rango ya existentes).";
	}

	if(!getimagesize($_FILES[$field]['tmp_name']))
	{
		return "El archivo subido no es una imagen válida.";
	}

	$valor_safe = preg_replace('/[^A-Za-z0-9]/', '', $valor);
	if($valor_safe == '')
	{
		return "Valor de rango no válido para guardar la imagen.";
	}

	$dest = MYBB_ROOT.OP_FACCIONES_IMG_DIR.$valor_safe.OP_FACCIONES_IMG_SUFFIX;
	if(!move_uploaded_file($_FILES[$field]['tmp_name'], $dest))
	{
		return "No se pudo guardar la imagen en el servidor.";
	}

	return '';
}

function op_facciones_image_preview($valor)
{
	$valor_safe = preg_replace('/[^A-Za-z0-9]/', '', $valor);
	$path = MYBB_ROOT.OP_FACCIONES_IMG_DIR.$valor_safe.OP_FACCIONES_IMG_SUFFIX;

	if(file_exists($path))
	{
		return "<img src=\"../".OP_FACCIONES_IMG_DIR.htmlspecialchars_uni($valor_safe).OP_FACCIONES_IMG_SUFFIX."\" alt=\"\" style=\"max-height: 40px; max-width: 80px;\" />";
	}

	return "<small>(sin imagen)</small>";
}

if(!$db->table_exists('op_facciones') || !$db->table_exists('op_facciones_rangos'))
{
	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Facciones", "index.php?module=forumconfig-facciones");
	$page->output_header("OPG Facciones");
	op_facciones_output_tabs('facciones');
	$page->output_inline_error("La tabla de facciones todavía no existe. Instala y activa el plugin \"OPG - Facciones y rangos (BD)\" (Configuración → Plugins) para poder gestionarlas aquí.");
	$page->output_footer();
	exit;
}

// --- Crear facción ---
if($mybb->input['action'] == "addfaccion" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$nombre = preg_replace('/[^A-Za-z0-9]/', '', $mybb->get_input('nombre'));

	if($nombre == '')
	{
		flash_message("El nombre de la facción no puede estar vacío ni tener espacios/símbolos.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$ya_existe = $db->fetch_field($db->simple_select('op_facciones', 'nombre', "nombre='".$db->escape_string($nombre)."'"), 'nombre');
	if($ya_existe)
	{
		flash_message("Ya existe una facción con ese nombre.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$max_orden = (int)$db->fetch_field($db->simple_select('op_facciones', 'MAX(orden) AS m'), 'm');
	$usergroup = $mybb->get_input('usergroup', MyBB::INPUT_INT);

	$db->insert_query('op_facciones', array(
		'nombre'    => $db->escape_string($nombre),
		'orden'     => $max_orden + 10,
		'usergroup' => $usergroup,
	));

	flash_message("Facción \"{$nombre}\" creada. Ya aparece en el desplegable de facciones del editor de staff, pero todavía no tiene rangos — añádelos desde \"Gestionar rangos\" antes de asignársela a alguien.", 'success');
	admin_redirect("index.php?module=forumconfig-facciones");
}

// --- Editar grupo de usuario de una facción ---
if($mybb->input['action'] == "editfaccion" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$nombre = $mybb->get_input('nombre');
	$faccion_existe = $db->fetch_field($db->simple_select('op_facciones', 'nombre', "nombre='".$db->escape_string($nombre)."'"), 'nombre');

	if(!$faccion_existe)
	{
		flash_message("Esa facción no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$usergroup = $mybb->get_input('usergroup', MyBB::INPUT_INT);

	$db->update_query('op_facciones', array('usergroup' => $usergroup), "nombre='".$db->escape_string($nombre)."'");

	flash_message("Grupo de usuario de \"{$nombre}\" actualizado.", 'success');
	admin_redirect("index.php?module=forumconfig-facciones");
}

// --- Guardar colores de una facción ---
if($mybb->input['action'] == "guardarcolores" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$nombre = $mybb->get_input('nombre');
	$faccion_existe = $db->fetch_field($db->simple_select('op_facciones', 'nombre', "nombre='".$db->escape_string($nombre)."'"), 'nombre');

	if(!$faccion_existe)
	{
		flash_message("Esa facción no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$update = array();
	foreach(op_facciones_color_field_labels() as $db_col => $label)
	{
		$update[$db_col] = $db->escape_string(trim($mybb->get_input($db_col)));
	}

	$db->update_query('op_facciones', $update, "nombre='".$db->escape_string($nombre)."'");

	flash_message("Colores de \"{$nombre}\" actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-facciones&amp;action=colores&amp;faccion=".urlencode($nombre));
}

// --- Eliminar facción ---
if($mybb->input['action'] == "delfaccion")
{
	$nombre = $mybb->get_input('nombre');
	$faccion = $db->fetch_array($db->simple_select('op_facciones', '*', "nombre='".$db->escape_string($nombre)."'"));

	if(!$faccion['nombre'])
	{
		flash_message("Esa facción no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	if($mybb->request_method == "post")
	{
		$num_rangos = (int)$db->fetch_field($db->simple_select('op_facciones_rangos', 'COUNT(*) AS c', "faccion='".$db->escape_string($faccion['nombre'])."'"), 'c');

		$db->delete_query('op_facciones_rangos', "faccion='".$db->escape_string($faccion['nombre'])."'");
		$db->delete_query('op_facciones', "nombre='".$db->escape_string($faccion['nombre'])."'");

		flash_message("Facción \"{$faccion['nombre']}\" eliminada, junto con sus {$num_rangos} rango(s). Esto no afecta a personajes que ya tuvieran esta facción asignada.", 'success');
		admin_redirect("index.php?module=forumconfig-facciones");
	}
	else
	{
		$num_fichas = (int)$db->fetch_field($db->simple_select('op_fichas', 'COUNT(*) AS c', "faccion='".$db->escape_string($faccion['nombre'])."'"), 'c');
		$num_rangos = (int)$db->fetch_field($db->simple_select('op_facciones_rangos', 'COUNT(*) AS c', "faccion='".$db->escape_string($faccion['nombre'])."'"), 'c');

		$msg = "¿Seguro que quieres eliminar la facción \"{$faccion['nombre']}\" y sus {$num_rangos} rango(s)?";
		if($num_fichas > 0)
		{
			$msg .= " Ahora mismo hay {$num_fichas} personaje(s) con esta facción asignada — no se les quitará ni cambiará nada, pero dejará de poder gestionarse desde aquí.";
		}

		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Facciones", "index.php?module=forumconfig-facciones");
		$page->output_confirm_action("index.php?module=forumconfig-facciones&amp;action=delfaccion&amp;nombre=".htmlspecialchars_uni($faccion['nombre']), $msg);
		exit;
	}
}

// --- Añadir rango ---
if($mybb->input['action'] == "addrango" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$faccion = $mybb->get_input('faccion');
	$faccion_existe = $db->fetch_field($db->simple_select('op_facciones', 'nombre', "nombre='".$db->escape_string($faccion)."'"), 'nombre');

	if(!$faccion_existe)
	{
		flash_message("Esa facción no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$valor = preg_replace('/[^A-Za-z0-9]/', '', $mybb->get_input('valor'));
	$nombre_visible = trim($mybb->get_input('nombre_visible'));
	$orden = $mybb->get_input('orden', MyBB::INPUT_INT);

	if($valor == '' || $nombre_visible == '')
	{
		flash_message("El valor interno y el nombre visible del rango son obligatorios.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($faccion));
	}

	$ya_existe = $db->fetch_field($db->simple_select('op_facciones_rangos', 'id', "valor='".$db->escape_string($valor)."'"), 'id');
	if($ya_existe)
	{
		flash_message("Ya existe un rango con el valor interno \"{$valor}\" (los valores son únicos entre todas las facciones, porque de ahí sale el nombre de la imagen).", 'error');
		admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($faccion));
	}

	if(!$orden)
	{
		$orden = (int)$db->fetch_field($db->simple_select('op_facciones_rangos', 'MAX(orden) AS m', "faccion='".$db->escape_string($faccion)."'"), 'm') + 10;
	}

	// Opcionales: umbral de reputación/nivel para ASCENDER automáticamente A
	// este rango. Vacío = sin umbral (no asciende solo, requiere cambio manual)
	// — se consigue OMITIENDO la clave del array, no pasando null: insert_query()
	// no sabe convertir null a NULL de SQL, solo lo deja en blanco/0 si se lo pasas.
	$reputacion_min_raw = trim($mybb->get_input('reputacion_min'));
	$nivel_min_raw = trim($mybb->get_input('nivel_min'));

	$insert_rango = array(
		'faccion'        => $db->escape_string($faccion),
		'valor'          => $db->escape_string($valor),
		'nombre_visible' => $db->escape_string($nombre_visible),
		'imagen'         => $db->escape_string($valor),
		'orden'          => $orden,
		'sueldo_semanal' => $mybb->get_input('sueldo_semanal', MyBB::INPUT_INT),
	);
	if($reputacion_min_raw !== '')
	{
		$insert_rango['reputacion_min'] = (int)$reputacion_min_raw;
	}
	if($nivel_min_raw !== '')
	{
		$insert_rango['nivel_min'] = (int)$nivel_min_raw;
	}

	$db->insert_query('op_facciones_rangos', $insert_rango);

	$img_error = op_facciones_handle_image_upload('imagen_file', $valor);

	if($img_error != '')
	{
		flash_message("Rango \"{$nombre_visible}\" creado, pero la imagen no se pudo guardar: {$img_error}", 'error');
	}
	else
	{
		flash_message("Rango \"{$nombre_visible}\" creado.", 'success');
	}
	admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($faccion));
}

// --- Reemplazar solo la imagen de un rango existente ---
if($mybb->input['action'] == "subirimagen" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$id = $mybb->get_input('id', MyBB::INPUT_INT);
	$rango = $db->fetch_array($db->simple_select('op_facciones_rangos', '*', "id='{$id}'"));

	if(!$rango['id'])
	{
		flash_message("Ese rango no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$img_error = op_facciones_handle_image_upload('imagen_file', $rango['valor']);

	if($img_error != '')
	{
		flash_message("No se pudo actualizar la imagen: {$img_error}", 'error');
	}
	else
	{
		flash_message("Imagen del rango \"{$rango['nombre_visible']}\" actualizada.", 'success');
	}
	admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($rango['faccion']));
}

// --- Editar umbral de ascenso / sueldo / orden de un rango ---
// Actualiza solo los campos que vengan realmente en el POST (comprobado con
// isset sobre $_POST, no con get_input) porque hay más de un formulario en
// la tabla de rangos que envían aquí — el de ascenso+sueldo y el de orden —
// y cada uno solo trae sus propios campos. Si leyéramos con un valor por
// defecto ('' o 0) para los que faltan, el formulario de orden pisaría a
// NULL el umbral de ascenso cada vez que se usara solo, y viceversa.
if($mybb->input['action'] == "editrango" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$id = $mybb->get_input('id', MyBB::INPUT_INT);
	$rango = $db->fetch_array($db->simple_select('op_facciones_rangos', '*', "id='{$id}'"));

	if(!$rango['id'])
	{
		flash_message("Ese rango no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$update = array();

	if(isset($_POST['reputacion_min']) || isset($_POST['nivel_min']))
	{
		$reputacion_min_raw = trim($mybb->get_input('reputacion_min'));
		$nivel_min_raw = trim($mybb->get_input('nivel_min'));
		// no_quote=true para poder escribir NULL sin comillas cuando se deja
		// vacío (update_query() no sabe convertir un null de PHP a NULL de SQL).
		$update['reputacion_min'] = ($reputacion_min_raw === '') ? 'NULL' : (int)$reputacion_min_raw;
		$update['nivel_min'] = ($nivel_min_raw === '') ? 'NULL' : (int)$nivel_min_raw;
	}
	if(isset($_POST['sueldo_semanal']))
	{
		$update['sueldo_semanal'] = $mybb->get_input('sueldo_semanal', MyBB::INPUT_INT);
	}
	if(isset($_POST['orden']))
	{
		$update['orden'] = $mybb->get_input('orden', MyBB::INPUT_INT);
	}

	if(!empty($update))
	{
		$db->update_query('op_facciones_rangos', $update, "id='{$id}'", '', true);
	}

	flash_message("Rango \"{$rango['nombre_visible']}\" actualizado.", 'success');
	admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($rango['faccion']));
}

// --- Eliminar rango ---
if($mybb->input['action'] == "delrango")
{
	$id = $mybb->get_input('id', MyBB::INPUT_INT);
	$rango = $db->fetch_array($db->simple_select('op_facciones_rangos', '*', "id='{$id}'"));

	if(!$rango['id'])
	{
		flash_message("Ese rango no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($rango['faccion']));
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query('op_facciones_rangos', "id='{$id}'");

		flash_message("Rango \"{$rango['nombre_visible']}\" eliminado. Esto no afecta a personajes que ya tuvieran este rango asignado.", 'success');
		admin_redirect("index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($rango['faccion']));
	}
	else
	{
		$num_fichas = (int)$db->fetch_field($db->simple_select('op_fichas', 'COUNT(*) AS c', "rango='".$db->escape_string($rango['valor'])."'"), 'c');

		$msg = "¿Seguro que quieres eliminar el rango \"{$rango['nombre_visible']}\" ({$rango['valor']})?";
		if($num_fichas > 0)
		{
			$msg .= " Ahora mismo hay {$num_fichas} personaje(s) con este rango asignado — no se les quitará ni cambiará nada.";
		}

		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Facciones", "index.php?module=forumconfig-facciones");
		$page->output_confirm_action("index.php?module=forumconfig-facciones&amp;action=delrango&amp;id={$id}", $msg);
		exit;
	}
}

// --- Vista: colores de una facción ---
if($mybb->input['action'] == "colores")
{
	$faccion = $mybb->get_input('faccion');
	$faccion_row = $db->fetch_array($db->simple_select('op_facciones', '*', "nombre='".$db->escape_string($faccion)."'"));

	if(!$faccion_row['nombre'])
	{
		flash_message("Esa facción no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Facciones", "index.php?module=forumconfig-facciones");
	$page->add_breadcrumb_item(htmlspecialchars_uni($faccion_row['nombre'])." — Colores");
	$page->output_header("OPG Facciones — Colores de {$faccion_row['nombre']}");

	op_facciones_output_tabs('facciones');

	echo "<p><a href=\"index.php?module=forumconfig-facciones\">&laquo; Volver a la lista de facciones</a></p>";

	$form = new Form("index.php?module=forumconfig-facciones&amp;action=guardarcolores", "post");
	echo $form->generate_hidden_field('nombre', $faccion_row['nombre']);

	$form_container = new FormContainer("Colores de {$faccion_row['nombre']}");
	foreach(op_facciones_color_field_labels() as $db_col => $label)
	{
		$valor = $faccion_row[$db_col];
		$preview = (strpos($valor, 'gradient') === false && $valor !== '') ? "<span style=\"display:inline-block;width:16px;height:16px;vertical-align:middle;margin-right:6px;border:1px solid #888;background:".htmlspecialchars_uni($valor).";\"></span>" : '';
		$form_container->output_row($label, "Columna <code>{$db_col}</code>. Déjalo vacío para heredar el color de Civil.", $preview.$form->generate_text_box($db_col, $valor, array('id' => $db_col, 'style' => 'width: 320px;')), $db_col);
	}
	$form_container->end();

	echo $form->generate_submit_button("Guardar colores");
	echo $form->end();

	$page->output_footer();
	exit;
}

// --- Vista: rangos de una facción ---
if($mybb->input['action'] == "rangos")
{
	$faccion = $mybb->get_input('faccion');
	$faccion_row = $db->fetch_array($db->simple_select('op_facciones', '*', "nombre='".$db->escape_string($faccion)."'"));

	if(!$faccion_row['nombre'])
	{
		flash_message("Esa facción no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-facciones");
	}

	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Facciones", "index.php?module=forumconfig-facciones");
	$page->add_breadcrumb_item(htmlspecialchars_uni($faccion_row['nombre']));
	$page->output_header("OPG Facciones — {$faccion_row['nombre']}");

	op_facciones_output_tabs('facciones');

	echo "<p><a href=\"index.php?module=forumconfig-facciones\">&laquo; Volver a la lista de facciones</a></p>";

	$table = new Table;
	$table->construct_header("Valor interno");
	$table->construct_header("Nombre visible");
	$table->construct_header("Orden", array("width" => 60));
	$table->construct_header("Ascenso automático (rep. / nivel mín.) y sueldo semanal", array("width" => 230));
	$table->construct_header("Imagen", array("width" => 140));
	$table->construct_header("Acciones", array("width" => 200, "class" => "align_center"));

	$query = $db->simple_select('op_facciones_rangos', '*', "faccion='".$db->escape_string($faccion_row['nombre'])."'", array('order_by' => 'orden'));
	$any = false;
	while($rango = $db->fetch_array($query))
	{
		$any = true;

		$upload_form = "<form action=\"index.php?module=forumconfig-facciones&amp;action=subirimagen\" method=\"post\" enctype=\"multipart/form-data\" style=\"margin: 4px 0 0;\">"
			."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
			."<input type=\"hidden\" name=\"id\" value=\"{$rango['id']}\" />"
			."<input type=\"file\" name=\"imagen_file\" accept=\".webp\" style=\"max-width: 130px;\" />"
			."<input type=\"submit\" class=\"button\" value=\"Subir\" />"
			."</form>";

		$orden_form = "<form action=\"index.php?module=forumconfig-facciones&amp;action=editrango\" method=\"post\" style=\"margin: 0; white-space: nowrap;\">"
			."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
			."<input type=\"hidden\" name=\"id\" value=\"{$rango['id']}\" />"
			."<input type=\"text\" name=\"orden\" value=\"".htmlspecialchars_uni((string)$rango['orden'])."\" style=\"width: 40px;\" /> "
			."<input type=\"submit\" class=\"button\" value=\"Guardar\" />"
			."</form>";

		$ascenso_form = "<form action=\"index.php?module=forumconfig-facciones&amp;action=editrango\" method=\"post\" style=\"margin: 0; white-space: nowrap;\">"
			."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
			."<input type=\"hidden\" name=\"id\" value=\"{$rango['id']}\" />"
			."<input type=\"text\" name=\"reputacion_min\" value=\"".htmlspecialchars_uni((string)$rango['reputacion_min'])."\" placeholder=\"rep.\" style=\"width: 55px;\" /> "
			."<input type=\"text\" name=\"nivel_min\" value=\"".htmlspecialchars_uni((string)$rango['nivel_min'])."\" placeholder=\"nivel\" style=\"width: 50px;\" /> "
			."<input type=\"text\" name=\"sueldo_semanal\" value=\"".htmlspecialchars_uni((string)$rango['sueldo_semanal'])."\" placeholder=\"berries/sem.\" style=\"width: 75px;\" /> "
			."<input type=\"submit\" class=\"button\" value=\"Guardar\" />"
			."</form>";

		$table->construct_cell("<code>".htmlspecialchars_uni($rango['valor'])."</code>");
		$table->construct_cell(htmlspecialchars_uni($rango['nombre_visible']));
		$table->construct_cell($orden_form);
		$table->construct_cell($ascenso_form);
		$table->construct_cell(op_facciones_image_preview($rango['valor']).$upload_form);
		$table->construct_cell("<a href=\"index.php?module=forumconfig-facciones&amp;action=delrango&amp;id={$rango['id']}\">Eliminar</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if(!$any)
	{
		$table->construct_cell("No hay rangos todavía para esta facción.", array("colspan" => 6));
		$table->construct_row();
	}

	$table->output("Rangos de {$faccion_row['nombre']}");

	echo "<br />";

	$form = new Form("index.php?module=forumconfig-facciones&amp;action=addrango", "post", "", true);
	echo $form->generate_hidden_field('faccion', $faccion_row['nombre']);

	$next_orden = (int)$db->fetch_field($db->simple_select('op_facciones_rangos', 'MAX(orden) AS m', "faccion='".$db->escape_string($faccion_row['nombre'])."'"), 'm') + 10;

	$form_container = new FormContainer("Añadir rango a {$faccion_row['nombre']}");
	$form_container->output_row("Valor interno", "El valor que se guarda en la ficha (ej. SargentoM). Solo letras y números, sin espacios — también decide el nombre del archivo de imagen.", $form->generate_text_box('valor', '', array('id' => 'valor')), 'valor');
	$form_container->output_row("Nombre visible", "El nombre que verá el staff/jugador (ej. Sargento).", $form->generate_text_box('nombre_visible', '', array('id' => 'nombre_visible')), 'nombre_visible');
	$form_container->output_row("Orden", "Posición dentro de la progresión de la facción (menor = antes).", $form->generate_numeric_field('orden', $next_orden, array('id' => 'orden')), 'orden');
	$form_container->output_row("Reputación mínima (opcional)", "Si se rellena junto con \"Nivel mínimo\", un personaje en el rango ANTERIOR de esta facción ascenderá solo a este en cuanto los cumpla (revisado cada pocos minutos). Vacío = no asciende solo, hace falta que el staff lo cambie a mano.", $form->generate_text_box('reputacion_min', '', array('id' => 'reputacion_min')), 'reputacion_min');
	$form_container->output_row("Nivel mínimo (opcional)", "Ver \"Reputación mínima\" — hacen falta los dos a la vez para que el ascenso automático se active.", $form->generate_text_box('nivel_min', '', array('id' => 'nivel_min')), 'nivel_min');
	$form_container->output_row("Sueldo semanal en berries (opcional)", "Cuánto cobra un personaje en este rango cada vez que el staff reparte sueldos desde \"Salario de facción\". 0 o vacío = no cobra.", $form->generate_numeric_field('sueldo_semanal', 0, array('id' => 'sueldo_semanal')), 'sueldo_semanal');
	$form_container->output_row("Imagen (opcional)", "Archivo .webp que se mostrará en la ficha para este rango. Puedes subirlo ahora o más tarde desde la tabla de arriba.", $form->generate_file_upload_box('imagen_file'), 'imagen_file');
	$form_container->end();

	echo $form->generate_submit_button("Añadir rango");
	echo $form->end();

	$page->output_footer();
	exit;
}

// --- Vista por defecto: lista de facciones ---
$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
$page->add_breadcrumb_item("OPG Facciones", "index.php?module=forumconfig-facciones");
$page->output_header("OPG Facciones");

op_facciones_output_tabs('facciones');

$table = new Table;
$table->construct_header("Facción");
$table->construct_header("Orden", array("width" => 60));
$table->construct_header("Rangos", array("width" => 80, "class" => "align_center"));
$table->construct_header("Grupo de usuario", array("width" => 170));
$table->construct_header("Acciones", array("width" => 220, "class" => "align_center"));

$query = $db->simple_select('op_facciones', '*', '', array('order_by' => 'orden'));
$any = false;
while($faccion = $db->fetch_array($query))
{
	$any = true;
	$num_rangos = (int)$db->fetch_field($db->simple_select('op_facciones_rangos', 'COUNT(*) AS c', "faccion='".$db->escape_string($faccion['nombre'])."'"), 'c');

	$usergroup_form = "<form action=\"index.php?module=forumconfig-facciones&amp;action=editfaccion\" method=\"post\" style=\"margin: 0; white-space: nowrap;\">"
		."<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />"
		."<input type=\"hidden\" name=\"nombre\" value=\"".htmlspecialchars_uni($faccion['nombre'])."\" />"
		."<input type=\"number\" name=\"usergroup\" value=\"{$faccion['usergroup']}\" style=\"width: 50px;\" /> "
		."<input type=\"submit\" class=\"button\" value=\"Guardar\" />"
		."</form>";

	$table->construct_cell("<strong>".htmlspecialchars_uni($faccion['nombre'])."</strong>");
	$table->construct_cell($faccion['orden']);
	$table->construct_cell($num_rangos, array("class" => "align_center"));
	$table->construct_cell($usergroup_form);
	$table->construct_cell(
		"<a href=\"index.php?module=forumconfig-facciones&amp;action=rangos&amp;faccion=".urlencode($faccion['nombre'])."\">Gestionar rangos</a>"
		." | <a href=\"index.php?module=forumconfig-facciones&amp;action=colores&amp;faccion=".urlencode($faccion['nombre'])."\">Colores</a>"
		." | <a href=\"index.php?module=forumconfig-facciones&amp;action=delfaccion&amp;nombre=".urlencode($faccion['nombre'])."\">Eliminar</a>",
		array("class" => "align_center")
	);
	$table->construct_row();
}

if(!$any)
{
	$table->construct_cell("No hay facciones todavía.", array("colspan" => 5));
	$table->construct_row();
}

$table->output("Facciones");

echo "<br />";
echo "<p><small>El desplegable de facciones y el de rangos del editor de staff (\"Modificar atributos de ficha\") se generan ya desde esta tabla. \"Grupo de usuario\" es el ID del grupo de MyBB al que se pasa automáticamente a un personaje cuando se le asigna esa facción (déjalo a 0 para no tocar el grupo). Al crear una facción nueva no se le crea ningún rango — hazlo desde \"Gestionar rangos\" antes de asignársela a nadie.</small></p>";

$form = new Form("index.php?module=forumconfig-facciones&amp;action=addfaccion", "post");

$form_container = new FormContainer("Añadir facción");
$form_container->output_row("Nombre", "El valor exacto que se guardará como facción (ej. Marina). Solo letras y números, sin espacios.", $form->generate_text_box('nombre', '', array('id' => 'nombre')), 'nombre');
$form_container->output_row("Grupo de usuario (opcional)", "ID del grupo de MyBB a asignar automáticamente (0 = no tocar el grupo de usuario).", $form->generate_numeric_field('usergroup', 0, array('id' => 'usergroup')), 'usergroup');
$form_container->end();

echo $form->generate_submit_button("Añadir facción");
echo $form->end();

$page->output_footer();
