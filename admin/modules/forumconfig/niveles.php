<?php
/**
 * OPG - Niveles (panel de gestión)
 *
 * Gestiona la tabla mybb_op_niveles (creada por inc/plugins/op_niveles.php),
 * antes dos arrays hardcodeados en op/personaje.php ($exp_tabla,
 * $puntos_bonus_nivel). 100 filas (una por nivel) — se editan todas a la
 * vez con un único formulario, en vez de una página de edición por fila.
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function op_niveles_output_tabs($active)
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

if(!$db->table_exists('op_niveles'))
{
	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Niveles", "index.php?module=forumconfig-niveles");
	$page->output_header("OPG Niveles");
	op_niveles_output_tabs('niveles');
	$page->output_inline_error("La tabla de niveles todavía no existe. Instala y activa el plugin \"OPG - Niveles (BD)\" (Configuración → Plugins) para poder gestionarla aquí.");
	$page->output_footer();
	exit;
}

// --- Guardar cambios en bloque (todas las filas a la vez) ---
if($mybb->input['action'] == "guardarniveles" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-niveles");
	}

	$submitted = isset($_POST['niveles']) && is_array($_POST['niveles']) ? $_POST['niveles'] : array();

	foreach($submitted as $nivel => $data)
	{
		$nivel = (int)$nivel;
		if($nivel < 1 || !is_array($data))
		{
			continue;
		}

		$exp_min = (int)($data['exp_min'] ?? 0);
		$exp_max = (int)($data['exp_max'] ?? 0);
		$bono_raw = trim($data['bono_puntos'] ?? '');
		$bono_sql = ($bono_raw === '') ? 'NULL' : (int)$bono_raw;
		// Un checkbox sin marcar no manda nada en el POST, así que su
		// ausencia en $data ES el valor "desmarcado" (0), no un dato que falte.
		$limite_temporal = !empty($data['limite_temporal']) ? 1 : 0;

		// no_quote=true para poder escribir NULL sin comillas cuando se deja
		// vacío (update_query() no sabe convertir un null de PHP a NULL de SQL).
		$db->update_query(
			'op_niveles',
			array('exp_min' => $exp_min, 'exp_max' => $exp_max, 'bono_puntos' => $bono_sql, 'limite_temporal' => $limite_temporal),
			"nivel='{$nivel}'",
			'',
			true
		);
	}

	flash_message("Tabla de niveles actualizada.", 'success');
	admin_redirect("index.php?module=forumconfig-niveles");
}

// --- Añadir nivel nuevo (p.ej. al subir el tope de nivel más adelante) ---
if($mybb->input['action'] == "addnivel" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-niveles");
	}

	$nivel = $mybb->get_input('nivel', MyBB::INPUT_INT);
	if($nivel < 1)
	{
		flash_message("El número de nivel tiene que ser 1 o mayor.", 'error');
		admin_redirect("index.php?module=forumconfig-niveles");
	}

	$ya_existe = $db->fetch_field($db->simple_select('op_niveles', 'nivel', "nivel='{$nivel}'"), 'nivel');
	if($ya_existe)
	{
		flash_message("El nivel {$nivel} ya existe.", 'error');
		admin_redirect("index.php?module=forumconfig-niveles");
	}

	$bono_raw = trim($mybb->get_input('bono_puntos'));

	$insert = array(
		'nivel'   => $nivel,
		'exp_min' => $mybb->get_input('exp_min', MyBB::INPUT_INT),
		'exp_max' => $mybb->get_input('exp_max', MyBB::INPUT_INT),
	);
	if($bono_raw !== '')
	{
		$insert['bono_puntos'] = (int)$bono_raw;
	}

	$db->insert_query('op_niveles', $insert);

	flash_message("Nivel {$nivel} añadido.", 'success');
	admin_redirect("index.php?module=forumconfig-niveles");
}

// --- Eliminar nivel ---
if($mybb->input['action'] == "delnivel")
{
	$nivel = $mybb->get_input('nivel', MyBB::INPUT_INT);
	$existe = $db->fetch_field($db->simple_select('op_niveles', 'nivel', "nivel='{$nivel}'"), 'nivel');

	if(!$existe)
	{
		flash_message("Ese nivel no existe.", 'error');
		admin_redirect("index.php?module=forumconfig-niveles");
	}

	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=forumconfig-niveles");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query('op_niveles', "nivel='{$nivel}'");

		flash_message("Nivel {$nivel} eliminado.", 'success');
		admin_redirect("index.php?module=forumconfig-niveles");
	}
	else
	{
		$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
		$page->add_breadcrumb_item("OPG Niveles", "index.php?module=forumconfig-niveles");
		$page->output_confirm_action("index.php?module=forumconfig-niveles&amp;action=delnivel&amp;nivel={$nivel}", "¿Seguro que quieres eliminar el nivel {$nivel} de la tabla? Un personaje que ya estuviera en ese nivel no se ve afectado, pero dejará de poder subir/bajar automáticamente a partir de él.");
		exit;
	}
}

// --- Vista por defecto: tabla completa, edición en bloque ---
$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
$page->add_breadcrumb_item("OPG Niveles", "index.php?module=forumconfig-niveles");
$page->output_header("OPG Niveles");

op_niveles_output_tabs('niveles');

echo "<p><small>\"Bono de puntos\" son los puntos de estadística extra que se dan solo al subir A ese nivel — vacío significa 10 (el valor por defecto de cualquier nivel sin configurar aparte, como los que no son de decena). \"Límite temporal\" marca ese nivel como techo actual: se puede llegar a él pero no pasar de ahí, ni siquiera con límite personal comprado por encima — si marcas varios, manda el más bajo; si no marcas ninguno, no hay techo (más allá de lo que cubra la tabla). Guarda toda la tabla de una vez con el botón de abajo.</small></p>";

$form = new Form("index.php?module=forumconfig-niveles&amp;action=guardarniveles", "post");

$table = new Table;
$table->construct_header("Nivel", array("width" => 50));
$table->construct_header("Exp. mínima", array("width" => 120));
$table->construct_header("Exp. máxima", array("width" => 120));
$table->construct_header("Bono de puntos", array("width" => 110));
$table->construct_header("Límite temporal", array("width" => 90, "class" => "align_center"));
$table->construct_header("Acciones", array("width" => 80, "class" => "align_center"));

$query = $db->simple_select('op_niveles', '*', '', array('order_by' => 'nivel'));
$any = false;
while($nivel_row = $db->fetch_array($query))
{
	$any = true;
	$n = (int)$nivel_row['nivel'];

	$table->construct_cell("<strong>{$n}</strong>");
	$table->construct_cell($form->generate_text_box("niveles[{$n}][exp_min]", $nivel_row['exp_min'], array('style' => 'width: 100px;')));
	$table->construct_cell($form->generate_text_box("niveles[{$n}][exp_max]", $nivel_row['exp_max'], array('style' => 'width: 100px;')));
	$table->construct_cell($form->generate_text_box("niveles[{$n}][bono_puntos]", (string)$nivel_row['bono_puntos'], array('style' => 'width: 70px;')));
	$table->construct_cell($form->generate_check_box("niveles[{$n}][limite_temporal]", 1, "", array('checked' => (bool)$nivel_row['limite_temporal'])), array("class" => "align_center"));
	$table->construct_cell("<a href=\"index.php?module=forumconfig-niveles&amp;action=delnivel&amp;nivel={$n}\">Eliminar</a>", array("class" => "align_center"));
	$table->construct_row();
}

if(!$any)
{
	$table->construct_cell("No hay niveles todavía.", array("colspan" => 6));
	$table->construct_row();
}

$table->output("Tabla de niveles");

echo "<br />";
echo $form->generate_submit_button("Guardar toda la tabla");
echo $form->end();

echo "<br />";

$form2 = new Form("index.php?module=forumconfig-niveles&amp;action=addnivel", "post");

$form_container = new FormContainer("Añadir nivel nuevo");
$form_container->output_row("Nivel", "Solo tiene sentido si el nivel no existe todavía (p.ej. al ampliar el tope de nivel más allá de 100).", $form2->generate_numeric_field('nivel', '', array('id' => 'nivel')), 'nivel');
$form_container->output_row("Exp. mínima", "", $form2->generate_numeric_field('exp_min', 0, array('id' => 'exp_min')), 'exp_min');
$form_container->output_row("Exp. máxima", "", $form2->generate_numeric_field('exp_max', 0, array('id' => 'exp_max')), 'exp_max');
$form_container->output_row("Bono de puntos (opcional)", "Vacío = 10 (el valor por defecto).", $form2->generate_text_box('bono_puntos', '', array('id' => 'bono_puntos')), 'bono_puntos');
$form_container->end();

echo $form2->generate_submit_button("Añadir nivel");
echo $form2->end();

$page->output_footer();
