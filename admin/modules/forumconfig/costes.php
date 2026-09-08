<?php
/**
 * OPG - Costes (panel de gestión)
 *
 * Gestiona las tablas numéricas de inc/plugins/op_ficha.php (costes de haki,
 * akuma, disciplinas, estilos y oficio, y los niveles mínimos requeridos),
 * antes arrays hardcodeados dentro de ese mismo plugin. Los catálogos de
 * nombres (disciplinas/oficios) se gestionan en "OPG Catálogos".
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function op_costes_output_tabs($active)
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

if(!op_costes_tablas_listas($db))
{
	$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
	$page->add_breadcrumb_item("OPG Costes", "index.php?module=forumconfig-costes");
	$page->output_header("OPG Costes");
	op_costes_output_tabs('costes');
	$page->output_inline_error("Las tablas de costes todavía no existen. Instala y activa el plugin \"OPG - Ficha de personaje\" (Configuración → Plugins) para poder gestionarlas aquí.");
	$page->output_footer();
	exit;
}

function op_costes_tablas_listas($db)
{
	foreach(array('op_costes_haki', 'op_costes_akuma', 'op_costes_belica', 'op_costes_estilo', 'op_costes_oficio', 'op_niveles_requeridos') as $tabla)
	{
		if(!$db->table_exists($tabla))
		{
			return false;
		}
	}
	return true;
}

// --- Guardar costes de haki ---
if($mybb->input['action'] == "guardarhaki" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-costes");
	}

	$submitted = isset($_POST['niveles']) && is_array($_POST['niveles']) ? $_POST['niveles'] : array();
	foreach($submitted as $nivel => $data)
	{
		$nivel = (int)$nivel;
		if($nivel < 1 || !is_array($data)) { continue; }

		$coste = (int)($data['coste'] ?? 0);
		$camino_raw = trim($data['coste_camino'] ?? '');
		$camino_sql = ($camino_raw === '') ? 'NULL' : (int)$camino_raw;

		$db->update_query('op_costes_haki', array('coste' => $coste, 'coste_camino' => $camino_sql), "nivel='{$nivel}'", '', true);
	}

	flash_message("Costes de haki actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-costes");
}

// --- Guardar costes de akuma ---
if($mybb->input['action'] == "guardarakuma" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-costes");
	}

	$submitted = isset($_POST['dominios']) && is_array($_POST['dominios']) ? $_POST['dominios'] : array();
	foreach($submitted as $dominio => $data)
	{
		$dominio = (int)$dominio;
		if(!is_array($data)) { continue; }

		$normal_raw = trim($data['coste_normal'] ?? '');
		$camino_raw = trim($data['coste_camino'] ?? '');
		$normal_sql = ($normal_raw === '') ? 'NULL' : (int)$normal_raw;
		$camino_sql = ($camino_raw === '') ? 'NULL' : (int)$camino_raw;

		$db->update_query('op_costes_akuma', array('coste_normal' => $normal_sql, 'coste_camino' => $camino_sql), "dominio='{$dominio}'", '', true);
	}

	flash_message("Costes de dominio akuma actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-costes");
}

// --- Guardar costes de disciplinas (por slot) ---
if($mybb->input['action'] == "guardarbelica" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-costes");
	}

	$submitted = isset($_POST['slots']) && is_array($_POST['slots']) ? $_POST['slots'] : array();
	foreach($submitted as $slot => $data)
	{
		$slot = (int)$slot;
		if($slot < 1 || !is_array($data)) { continue; }

		$db->update_query('op_costes_belica', array(
			'coste_slot'  => (int)($data['coste_slot'] ?? 0),
			'coste_espe1' => (int)($data['coste_espe1'] ?? 0),
			'coste_espe2' => (int)($data['coste_espe2'] ?? 0),
			'coste_up'    => (int)($data['coste_up'] ?? 0),
		), "slot='{$slot}'");
	}

	flash_message("Costes de disciplinas actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-costes");
}

// --- Guardar costes de estilos ---
if($mybb->input['action'] == "guardarestilo" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-costes");
	}

	$submitted = isset($_POST['estilos']) && is_array($_POST['estilos']) ? $_POST['estilos'] : array();
	foreach($submitted as $slot => $data)
	{
		$slot = preg_replace('/[^A-Za-z0-9]/', '', $slot);
		if($slot === '' || !is_array($data)) { continue; }

		$nuevo_raw = trim($data['coste_nuevo'] ?? '');
		$reembolso_raw = trim($data['coste_reembolso'] ?? '');
		$nuevo_sql = ($nuevo_raw === '') ? 'NULL' : (int)$nuevo_raw;
		$reembolso_sql = ($reembolso_raw === '') ? 'NULL' : (int)$reembolso_raw;

		$db->update_query('op_costes_estilo', array('coste_nuevo' => $nuevo_sql, 'coste_reembolso' => $reembolso_sql), "slot='".$db->escape_string($slot)."'", '', true);
	}

	flash_message("Costes de estilos actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-costes");
}

// --- Guardar costes de oficio ---
if($mybb->input['action'] == "guardaroficio" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-costes");
	}

	$submitted = isset($_POST['oficio']) && is_array($_POST['oficio']) ? $_POST['oficio'] : array();
	foreach($submitted as $clave => $data)
	{
		$clave = preg_replace('/[^A-Za-z0-9_]/', '', $clave);
		if($clave === '' || !is_array($data)) { continue; }

		$db->update_query('op_costes_oficio', array(
			'coste_nikas'         => (int)($data['coste_nikas'] ?? 0),
			'coste_puntos_oficio' => (int)($data['coste_puntos_oficio'] ?? 0),
		), "clave='".$db->escape_string($clave)."'");
	}

	flash_message("Costes de oficio actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-costes");
}

// --- Guardar niveles requeridos ---
if($mybb->input['action'] == "guardarnivelesreq" && $mybb->request_method == "post")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=forumconfig-costes");
	}

	$submitted = isset($_POST['nivelreq']) && is_array($_POST['nivelreq']) ? $_POST['nivelreq'] : array();
	foreach($submitted as $clave => $valor)
	{
		$clave = preg_replace('/[^A-Za-z0-9_]/', '', $clave);
		if($clave === '') { continue; }

		$db->update_query('op_niveles_requeridos', array('nivel_min' => (int)$valor), "clave='".$db->escape_string($clave)."'");
	}

	flash_message("Niveles requeridos actualizados.", 'success');
	admin_redirect("index.php?module=forumconfig-costes");
}

// --- Vista por defecto: costes numéricos ---
$page->add_breadcrumb_item("Configuración del foro", "index.php?module=forumconfig");
$page->add_breadcrumb_item("OPG Costes", "index.php?module=forumconfig-costes");
$page->output_header("OPG Costes");

op_costes_output_tabs('costes');

echo "<p><small>Los catálogos de nombres de disciplinas/oficios (con sus caminos y especializaciones) se gestionan en <a href=\"index.php?module=forumconfig-catalogos\">OPG Catálogos</a>. Aquí solo los costes en nikas/puntos de oficio.</small></p>";

// Haki
$form_haki = new Form("index.php?module=forumconfig-costes&amp;action=guardarhaki", "post");
$table = new Table;
$table->construct_header("Nivel actual", array("width" => 90));
$table->construct_header("Coste normal", array("width" => 120));
$table->construct_header("Coste camino Haki (vacío = igual)", array("width" => 150));
$query = $db->simple_select('op_costes_haki', '*', '', array('order_by' => 'nivel'));
while($fila = $db->fetch_array($query))
{
	$n = (int)$fila['nivel'];
	$table->construct_cell("<strong>{$n}</strong>");
	$table->construct_cell($form_haki->generate_text_box("niveles[{$n}][coste]", $fila['coste'], array('style' => 'width: 90px;')));
	$table->construct_cell($form_haki->generate_text_box("niveles[{$n}][coste_camino]", (string)$fila['coste_camino'], array('style' => 'width: 90px;')));
	$table->construct_row();
}
$table->output("Costes de Haki (Kenbunshoku/Busoshoku/Haoshoku)");
echo "<br />".$form_haki->generate_submit_button("Guardar costes de haki");
echo $form_haki->end();

echo "<br /><br />";

// Akuma
$form_akuma = new Form("index.php?module=forumconfig-costes&amp;action=guardarakuma", "post");
$table = new Table;
$table->construct_header("Dominio actual", array("width" => 90));
$table->construct_header("Coste normal (vacío = no aplica)", array("width" => 150));
$table->construct_header("Coste camino Akuma (vacío = no aplica)", array("width" => 150));
$query = $db->simple_select('op_costes_akuma', '*', '', array('order_by' => 'dominio'));
while($fila = $db->fetch_array($query))
{
	$d = (int)$fila['dominio'];
	$table->construct_cell("<strong>{$d}</strong>");
	$table->construct_cell($form_akuma->generate_text_box("dominios[{$d}][coste_normal]", (string)$fila['coste_normal'], array('style' => 'width: 90px;')));
	$table->construct_cell($form_akuma->generate_text_box("dominios[{$d}][coste_camino]", (string)$fila['coste_camino'], array('style' => 'width: 90px;')));
	$table->construct_row();
}
$table->output("Costes de Dominio Akuma");
echo "<br />".$form_akuma->generate_submit_button("Guardar costes de akuma");
echo $form_akuma->end();

echo "<br /><br />";

// Belica (slots + especializaciones)
$form_belica = new Form("index.php?module=forumconfig-costes&amp;action=guardarbelica", "post");
$table = new Table;
$table->construct_header("Slot", array("width" => 50));
$table->construct_header("Coste desbloquear", array("width" => 110));
$table->construct_header("Coste 1er camino", array("width" => 110));
$table->construct_header("Coste 2º camino", array("width" => 110));
$table->construct_header("Coste subir a nivel 2", array("width" => 120));
$query = $db->simple_select('op_costes_belica', '*', '', array('order_by' => 'slot'));
while($fila = $db->fetch_array($query))
{
	$s = (int)$fila['slot'];
	$table->construct_cell("<strong>belica{$s}</strong>");
	$table->construct_cell($form_belica->generate_text_box("slots[{$s}][coste_slot]", $fila['coste_slot'], array('style' => 'width: 80px;')));
	$table->construct_cell($form_belica->generate_text_box("slots[{$s}][coste_espe1]", $fila['coste_espe1'], array('style' => 'width: 80px;')));
	$table->construct_cell($form_belica->generate_text_box("slots[{$s}][coste_espe2]", $fila['coste_espe2'], array('style' => 'width: 80px;')));
	$table->construct_cell($form_belica->generate_text_box("slots[{$s}][coste_up]", $fila['coste_up'], array('style' => 'width: 80px;')));
	$table->construct_row();
}
$table->output("Costes de disciplinas (por slot)");
echo "<br />".$form_belica->generate_submit_button("Guardar costes de disciplinas");
echo $form_belica->end();

echo "<br /><br />";

// Estilos
$form_estilo = new Form("index.php?module=forumconfig-costes&amp;action=guardarestilo", "post");
$table = new Table;
$table->construct_header("Slot", array("width" => 80));
$table->construct_header("Coste al comprarlo (vacío = no comprable)", array("width" => 180));
$table->construct_header("Coste de reembolso (vacío = no aplica)", array("width" => 180));
$query = $db->simple_select('op_costes_estilo', '*', '', array('order_by' => 'slot'));
while($fila = $db->fetch_array($query))
{
	$table->construct_cell("<strong>".htmlspecialchars_uni($fila['slot'])."</strong>");
	$table->construct_cell($form_estilo->generate_text_box("estilos[".htmlspecialchars_uni($fila['slot'])."][coste_nuevo]", (string)$fila['coste_nuevo'], array('style' => 'width: 90px;')));
	$table->construct_cell($form_estilo->generate_text_box("estilos[".htmlspecialchars_uni($fila['slot'])."][coste_reembolso]", (string)$fila['coste_reembolso'], array('style' => 'width: 90px;')));
	$table->construct_row();
}
$table->output("Costes de estilos");
echo "<br />".$form_estilo->generate_submit_button("Guardar costes de estilos");
echo $form_estilo->end();

echo "<br /><br />";

// Oficio
$etiquetas_oficio = array(
	'nivel2' => 'Subir el oficio principal a nivel 2',
	'espe_0' => 'Elegir 1ª/2ª especialización (nivel 0 → 1)',
	'espe_1' => 'Subir especialización a nivel 2 (1 → 2)',
	'espe_2' => 'Subir especialización a nivel 3 (2 → 3)',
);
$form_oficio = new Form("index.php?module=forumconfig-costes&amp;action=guardaroficio", "post");
$table = new Table;
$table->construct_header("Concepto");
$table->construct_header("Coste en nikas", array("width" => 120));
$table->construct_header("Coste en puntos de oficio", array("width" => 160));
$query = $db->simple_select('op_costes_oficio', '*', '', array('order_by' => 'clave'));
while($fila = $db->fetch_array($query))
{
	$clave = $fila['clave'];
	$etiqueta = $etiquetas_oficio[$clave] ?? $clave;
	$table->construct_cell(htmlspecialchars_uni($etiqueta)." <small>(<code>{$clave}</code>)</small>");
	$table->construct_cell($form_oficio->generate_text_box("oficio[{$clave}][coste_nikas]", $fila['coste_nikas'], array('style' => 'width: 90px;')));
	$table->construct_cell($form_oficio->generate_text_box("oficio[{$clave}][coste_puntos_oficio]", $fila['coste_puntos_oficio'], array('style' => 'width: 90px;')));
	$table->construct_row();
}
$table->output("Costes de oficio");
echo "<br />".$form_oficio->generate_submit_button("Guardar costes de oficio");
echo $form_oficio->end();

echo "<br /><br />";

// Niveles requeridos
$etiquetas_nivelreq = array(
	'estilo1'       => 'Desbloquear el 1er slot de estilo',
	'estilo2'       => 'Desbloquear el 2º slot de estilo',
	'estilo3'       => 'Desbloquear el 3er slot de estilo',
	'camino_elegir' => 'Elegir un camino de especialización de disciplina',
	'camino_subir'  => 'Subir un camino ya elegido a nivel 2 (Especialización)',
);
$form_nivelreq = new Form("index.php?module=forumconfig-costes&amp;action=guardarnivelesreq", "post");
$table = new Table;
$table->construct_header("Requisito");
$table->construct_header("Nivel mínimo", array("width" => 120));
$query = $db->simple_select('op_niveles_requeridos', '*', '', array('order_by' => 'clave'));
while($fila = $db->fetch_array($query))
{
	$clave = $fila['clave'];
	$etiqueta = $etiquetas_nivelreq[$clave] ?? $clave;
	$table->construct_cell(htmlspecialchars_uni($etiqueta)." <small>(<code>{$clave}</code>)</small>");
	$table->construct_cell($form_nivelreq->generate_text_box("nivelreq[{$clave}]", $fila['nivel_min'], array('style' => 'width: 90px;')));
	$table->construct_row();
}
$table->output("Niveles requeridos (estilos y caminos de disciplina)");
echo "<br />".$form_nivelreq->generate_submit_button("Guardar niveles requeridos");
echo $form_nivelreq->end();

$page->output_footer();
