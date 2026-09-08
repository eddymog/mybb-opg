<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->plugins, "index.php?module=config-plugins");

$plugins->run_hooks("admin_config_plugins_begin");

if($mybb->input['action'] == "browse")
{
	$page->add_breadcrumb_item($lang->browse_plugins);

	$page->output_header($lang->browse_plugins);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
		'description' => $lang->plugins_desc
	);
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);

	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);
	$sub_tabs['manage_categories'] = array(
		'title' => "Categorías",
		'link' => "index.php?module=config-plugins&amp;action=managecategories",
		'description' => "Asigna cada plugin a una categoría/subcategoría para organizarlos en la lista principal."
	);

	$page->output_nav_tabs($sub_tabs, 'browse_plugins');

	// Process search requests
	$keywords = "";
	if($mybb->get_input('keywords'))
	{
		$keywords = "&keywords=".urlencode($mybb->input['keywords']);
	}

	if($mybb->get_input('page'))
	{
		$url_page = "&page=".$mybb->get_input('page', MyBB::INPUT_INT);
	}
	else
	{
		$mybb->input['page'] = 1;
		$url_page = "";
	}

	// Gets the major version code. i.e. 1410 -> 1400 or 121 -> 1200
	$major_version_code = round($mybb->version_code/100, 0)*100;
	// Convert to mods site version codes
	$search_version = ($major_version_code/100).'x';

	$contents = fetch_remote_file("https://community.mybb.com/xmlbrowse.php?api=2&type=plugins&version={$search_version}{$keywords}{$url_page}");

	if(!$contents)
	{
		$page->output_inline_error($lang->error_communication_problem);
		$page->output_footer();
		exit;
	}

	$table = new Table;
	$table->construct_header($lang->plugin);
	$table->construct_header($lang->latest_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => 125));

	$parser = create_xml_parser($contents);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !isset($tree['results']))
	{
		$page->output_inline_error($lang->error_communication_problem);
		$page->output_footer();
		exit;
	}

	if(!empty($tree['results']['result']))
	{
		if(array_key_exists("tag", $tree['results']['result']))
		{
			$only_plugin = $tree['results']['result'];
			unset($tree['results']['result']);
			$tree['results']['result'][0] = $only_plugin;
		}

		require_once MYBB_ROOT . '/inc/class_parser.php';
		$post_parser = new postParser();

		foreach($tree['results']['result'] as $result)
		{
			$result['name']['value'] = htmlspecialchars_uni($result['name']['value']);
			$result['description']['value'] = htmlspecialchars_uni($result['description']['value']);
			$result['author']['url']['value'] = htmlspecialchars_uni($result['author']['url']['value']);
			$result['author']['name']['value'] = htmlspecialchars_uni($result['author']['name']['value']);
			$result['version']['value'] = htmlspecialchars_uni($result['version']['value']);
			$result['download_url']['value'] = htmlspecialchars_uni(html_entity_decode($result['download_url']['value']));

			$table->construct_cell("<strong>{$result['name']['value']}</strong><br /><small>{$result['description']['value']}</small><br /><i><small>{$lang->created_by} <a href=\"{$result['author']['url']['value']}\" target=\"_blank\" rel=\"noopener\">{$result['author']['name']['value']}</a></small></i>");
			$table->construct_cell($result['version']['value'], array("class" => "align_center"));
			$table->construct_cell("<strong><a href=\"https://community.mybb.com/{$result['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->download}</a></strong>", array("class" => "align_center"));
			$table->construct_row();
		}
	}

	$no_results = false;
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->error_no_results_found, array("colspan" => 3));
		$table->construct_row();
		$no_results = true;
	}

	$search = new Form("index.php?module=config-plugins&amp;action=browse", 'post', 'search_form');
	echo "<div style=\"padding-bottom: 3px; margin-top: -9px; text-align: right;\">";
	if($mybb->get_input('keywords'))
	{
		$default_class = '';
		$value = htmlspecialchars_uni($mybb->input['keywords']);
	}
	else
	{
		$default_class = "search_default";
		$value = $lang->search_for_plugins;
	}
	echo $search->generate_text_box('keywords', $value, array('id' => 'search_keywords', 'class' => "{$default_class} field150 field_small"))."\n";
	echo "<input type=\"submit\" class=\"search_button\" value=\"{$lang->search}\" />\n";
	echo "<script type=\"text/javascript\">
		var form = $(\"#search_form\");
		form.on('submit', function()
		{
			var search = $(\"#search_keywords\");
			if(search.val() == '' || search.val() == '{$lang->search_for_plugins}')
			{
				search.trigger('focus');
				return false;
			}
		});

		var search = $(\"#search_keywords\");
		search.on('focus', function()
		{
			var searched_focus = $(this);
			if(searched_focus.val() == '{$lang->search_for_plugins}')
			{
				searched_focus.removeClass(\"search_default\");
				searched_focus.val(\"\");
			}
		}).on('blur', function()
		{
			var searched_blur = $(this);
			if(searched_blur.val() == \"\")
			{
				searched_blur.addClass('search_default');
				searched_blur.val('{$lang->search_for_plugins}');
			}
		});

		// fix the styling used if we have a different default value
        if(search.val() != '{$lang->search_for_plugins}')
        {
            search.removeClass('search_default');
        }
		</script>\n";
	echo "</div>\n";
	echo $search->end();

	// Recommended plugins = Default; Otherwise search results & pagination
	if($mybb->request_method == "post")
	{
		$table->output("<span style=\"float: right;\"><small><a href=\"https://community.mybb.com/mods.php?action=browse&category=plugins\" target=\"_blank\" rel=\"noopener\">{$lang->browse_all_plugins}</a></small></span>".$lang->sprintf($lang->browse_results_for_mybb, $mybb->version));
	}
	else
	{
		$table->output("<span style=\"float: right;\"><small><a href=\"https://community.mybb.com/mods.php?action=browse&category=plugins\" target=\"_blank\" rel=\"noopener\">{$lang->browse_all_plugins}</a></small></span>".$lang->sprintf($lang->recommended_plugins_for_mybb, $mybb->version));
	}

	if(!$no_results)
	{
		echo "<br />".draw_admin_pagination($mybb->input['page'], 15, $tree['results']['attributes']['total'], "index.php?module=config-plugins&amp;action=browse{$keywords}&amp;page={page}");
	}

	$page->output_footer();
}

if($mybb->input['action'] == "check")
{
	$plugins_list = get_plugins_list();

	$plugins->run_hooks("admin_config_plugins_check");

	$info = array();

	if($plugins_list)
	{
		$active_hooks = $plugins->hooks;
		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";
			if(!function_exists($infofunc))
			{
				continue;
			}
			$plugininfo = $infofunc();
			$plugininfo['guid'] = isset($plugininfo['guid']) ? trim($plugininfo['guid']) : null;
			$plugininfo['codename'] = isset($plugininfo['codename']) ? trim($plugininfo['codename']) : null;

			if($plugininfo['codename'] != "")
			{
				$info[]	= $plugininfo['codename'];
				$names[$plugininfo['codename']] = array('name' => $plugininfo['name'], 'version' => $plugininfo['version']);
			}
			elseif($plugininfo['guid'] != "")
			{
				$info[] =  $plugininfo['guid'];
				$names[$plugininfo['guid']] = array('name' => $plugininfo['name'], 'version' => $plugininfo['version']);
			}
		}
		$plugins->hooks = $active_hooks;
	}

	if(empty($info))
	{
		flash_message($lang->error_vcheck_no_supported_plugins, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$url = "https://community.mybb.com/version_check.php?";
	$url .= http_build_query(array("info" => $info))."&";
	$contents = fetch_remote_file($url);

	if(!$contents)
	{
		flash_message($lang->error_vcheck_communications_problem, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$contents = trim($contents);

	$parser = create_xml_parser($contents);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !isset($tree['plugins']))
	{
		flash_message($lang->error_communication_problem, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	if(array_key_exists('error', $tree['plugins']))
	{
		switch($tree['plugins'][0]['error'])
		{
			case "1":
				$error_msg = $lang->error_no_input;
				break;
			case "2":
				$error_msg = $lang->error_no_pids;
				break;
			default:
				$error_msg = "";
		}
		flash_message($lang->error_communication_problem.$error_msg, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$table = new Table;
	$table->construct_header($lang->plugin);
	$table->construct_header($lang->your_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->latest_version, array("class" => "align_center", 'width' => 125));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => 125));

	if(!is_array($tree['plugins']['plugin']))
	{
		flash_message($lang->success_plugins_up_to_date, 'success');
		admin_redirect("index.php?module=config-plugins");
	}

	if(array_key_exists("tag", $tree['plugins']['plugin']))
	{
		$only_plugin = $tree['plugins']['plugin'];
		unset($tree['plugins']['plugin']);
		$tree['plugins']['plugin'][0] = $only_plugin;
	}

	foreach($tree['plugins']['plugin'] as $plugin)
	{
		$compare_by = array_key_exists("codename", $plugin['attributes']) ? "codename" : "guid";
		$is_vulnerable = array_key_exists("vulnerable", $plugin) ? true : false;

		if(version_compare($names[$plugin['attributes'][$compare_by]]['version'], $plugin['version']['value'], "<"))
		{
			$plugin['download_url']['value'] = htmlspecialchars_uni($plugin['download_url']['value']);
			$plugin['version']['value'] = htmlspecialchars_uni($plugin['version']['value']);

			if(isset($plugin['vulnerable']['value']))
			{
				$plugin['vulnerable']['value'] = htmlspecialchars_uni($plugin['vulnerable']['value']);
			}

			if($is_vulnerable)
			{
				$table->construct_cell("<div class=\"error\" id=\"flash_message\">
										{$lang->error_vcheck_vulnerable} {$names[$plugin['attributes'][$compare_by]]['name']}
										</div>
										<p>	<b>{$lang->error_vcheck_vulnerable_notes}</b> <br /><br /> {$plugin['vulnerable']['value']}</p>");
			}
			else
			{
				$table->construct_cell("<strong>{$names[$plugin['attributes'][$compare_by]]['name']}</strong>");
			}
			$table->construct_cell("{$names[$plugin['attributes'][$compare_by]]['version']}", array("class" => "align_center"));
			$table->construct_cell("<strong><span style=\"color: #C00\">{$plugin['version']['value']}</span></strong>", array("class" => "align_center"));
			if($is_vulnerable)
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins\"><b>{$lang->deactivate}</b></a>", array("class" => "align_center", "width" => 150));
			}
			else
			{
				$table->construct_cell("<strong><a href=\"https://community.mybb.com/{$plugin['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->download}</a></strong>", array("class" => "align_center"));
			}
			$table->construct_row();
		}
	}

	if($table->num_rows() == 0)
	{
		flash_message($lang->success_plugins_up_to_date, 'success');
		admin_redirect("index.php?module=config-plugins");
	}

	$page->add_breadcrumb_item($lang->plugin_updates);

	$page->output_header($lang->plugin_updates);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
	);

	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);

	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);
	$sub_tabs['manage_categories'] = array(
		'title' => "Categorías",
		'link' => "index.php?module=config-plugins&amp;action=managecategories",
		'description' => "Asigna cada plugin a una categoría/subcategoría para organizarlos en la lista principal."
	);

	$page->output_nav_tabs($sub_tabs, 'update_plugins');

	$table->output($lang->plugin_updates);

	$page->output_footer();
}

// Activates or deactivates a specific plugin
if($mybb->input['action'] == "activate" || $mybb->input['action'] == "deactivate")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	if($mybb->input['action'] == "activate")
	{
		$plugins->run_hooks("admin_config_plugins_activate");
	}
	else
	{
		$plugins->run_hooks("admin_config_plugins_deactivate");
	}

	$codename = $mybb->input['plugin'];
	$codename = str_replace(array(".", "/", "\\"), "", $codename);
	$file = basename($codename.".php");

	// Check if the file exists and throw an error if it doesn't
	if(!file_exists(MYBB_ROOT."inc/plugins/$file"))
	{
		flash_message($lang->error_invalid_plugin, 'error');
		admin_redirect("index.php?module=config-plugins");
	}

	$plugins_cache = $cache->read("plugins");
	$active_plugins = isset($plugins_cache['active']) ? $plugins_cache['active'] : array();

	require_once MYBB_ROOT."inc/plugins/$file";

	$installed_func = "{$codename}_is_installed";
	$installed = true;
	if(function_exists($installed_func) && $installed_func() != true)
	{
		$installed = false;
	}

	$install_uninstall = false;

	if($mybb->input['action'] == "activate")
	{
		$message = $lang->success_plugin_activated;

		// Plugin is compatible with this version?
		if($plugins->is_compatible($codename) == false)
		{
			flash_message($lang->sprintf($lang->plugin_incompatible, $mybb->version), 'error');
			admin_redirect("index.php?module=config-plugins");
		}

		// If not installed and there is a custom installation function
		if($installed == false && function_exists("{$codename}_install"))
		{
			call_user_func("{$codename}_install");
			$message = $lang->success_plugin_installed;
			$install_uninstall = true;
		}

		if(function_exists("{$codename}_activate"))
		{
			call_user_func("{$codename}_activate");
		}

		$active_plugins[$codename] = $codename;
		$executed[] = 'activate';
	}
	else if($mybb->input['action'] == "deactivate")
	{
		$message = $lang->success_plugin_deactivated;

		if(function_exists("{$codename}_deactivate"))
		{
			call_user_func("{$codename}_deactivate");
		}

		if($mybb->get_input('uninstall') == 1 && function_exists("{$codename}_uninstall"))
		{
			call_user_func("{$codename}_uninstall");
			$message = $lang->success_plugin_uninstalled;
			$install_uninstall = true;
		}

		unset($active_plugins[$codename]);
	}

	// Update plugin cache
	$plugins_cache['active'] = $active_plugins;
	$cache->update("plugins", $plugins_cache);

	// Log admin action
	log_admin_action($codename, $install_uninstall);

	if($mybb->input['action'] == "activate")
	{
		$plugins->run_hooks("admin_config_plugins_activate_commit");
	}
	else
	{
		$plugins->run_hooks("admin_config_plugins_deactivate_commit");
	}

	flash_message($message, 'success');
	admin_redirect("index.php?module=config-plugins");
}

// Gestión de categorías/subcategorías de plugins (tabla mybb_op_plugin_categories).
// Puramente cosmético para la lista de arriba, no afecta a instalar/activar/etc.
if($mybb->input['action'] == "managecategories")
{
	if($mybb->request_method == "post")
	{
		if(!verify_post_check($mybb->get_input('my_post_key')))
		{
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect("index.php?module=config-plugins&amp;action=managecategories");
		}

		if(!$db->table_exists('op_plugin_categories'))
		{
			flash_message("Primero tienes que instalar y activar el plugin \"OPG - Categorías de plugins (BD)\".", 'error');
			admin_redirect("index.php?module=config-plugins&amp;action=managecategories");
		}

		$submitted = isset($_POST['cats']) && is_array($_POST['cats']) ? $_POST['cats'] : array();

		$db->delete_query('op_plugin_categories');
		foreach($submitted as $codename => $data)
		{
			$codename = preg_replace('/[^a-zA-Z0-9_]/', '', $codename);
			$category = is_array($data) && isset($data['category']) ? trim($data['category']) : '';
			$subcategory = is_array($data) && isset($data['subcategory']) ? trim($data['subcategory']) : '';

			// El desplegable usa "__new__" como valor centinela cuando se
			// elige "+ Nueva categoría/subcategoría..."; en ese caso el
			// nombre real viene en el campo de texto que aparece al lado.
			if($category === '__new__')
			{
				$category = is_array($data) && isset($data['category_new']) ? trim($data['category_new']) : '';
			}
			if($subcategory === '__new__')
			{
				$subcategory = is_array($data) && isset($data['subcategory_new']) ? trim($data['subcategory_new']) : '';
			}

			if($codename == '' || $category == '')
			{
				continue;
			}

			$db->insert_query('op_plugin_categories', array(
				'codename'    => $codename,
				'category'    => $db->escape_string($category),
				'subcategory' => $db->escape_string($subcategory),
			));
		}

		flash_message("Categorías de plugins actualizadas.", 'success');
		admin_redirect("index.php?module=config-plugins&amp;action=managecategories");
	}

	$page->add_breadcrumb_item("Categorías de plugins", "index.php?module=config-plugins&amp;action=managecategories");
	$page->output_header("Categorías de plugins");

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
		'description' => $lang->plugins_desc
	);
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);
	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);
	$sub_tabs['manage_categories'] = array(
		'title' => "Categorías",
		'link' => "index.php?module=config-plugins&amp;action=managecategories",
		'description' => "Asigna cada plugin a una categoría y, opcionalmente, una subcategoría eligiendo de la lista desplegable, o creando una nueva con la opción \"+ Nueva...\"."
	);

	$page->output_nav_tabs($sub_tabs, 'manage_categories');

	if(!$db->table_exists('op_plugin_categories'))
	{
		$page->output_inline_error("La tabla de categorías todavía no existe. Instala y activa el plugin \"OPG - Categorías de plugins (BD)\" (Configuración → Plugins) para poder gestionarlas aquí.");
		$page->output_footer();
		exit;
	}

	$existing = array();
	$query = $db->simple_select('op_plugin_categories', 'codename, category, subcategory');
	while($row = $db->fetch_array($query))
	{
		$existing[$row['codename']] = $row;
	}

	$all_categories = array();
	$all_subcategories = array();
	foreach($existing as $row)
	{
		if($row['category'] != '' && !in_array($row['category'], $all_categories))
		{
			$all_categories[] = $row['category'];
		}
		if($row['subcategory'] != '' && !in_array($row['subcategory'], $all_subcategories))
		{
			$all_subcategories[] = $row['subcategory'];
		}
	}
	sort($all_categories);
	sort($all_subcategories);

	$plugins_list = get_plugins_list();

	$form = new Form("index.php?module=config-plugins&amp;action=managecategories", "post");
	echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

	$table = new Table;
	$table->construct_header("Plugin");
	$table->construct_header("Categoría", array("width" => 260));
	$table->construct_header("Subcategoría (opcional)", array("width" => 260));

	if(!empty($plugins_list))
	{
		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";

			if(!function_exists($infofunc))
			{
				continue;
			}

			$plugininfo = $infofunc();
			$cur_cat = isset($existing[$codename]) ? $existing[$codename]['category'] : '';
			$cur_sub = isset($existing[$codename]) ? $existing[$codename]['subcategory'] : '';

			$table->construct_cell("<strong>".htmlspecialchars_uni($plugininfo['name'])."</strong><br /><small>{$codename}</small>");
			$table->construct_cell(op_plugin_cat_select("cats[{$codename}][category]", "cats[{$codename}][category_new]", "cat_{$codename}", $cur_cat, $all_categories, "-- Sin categoría --", "+ Nueva categoría..."));
			$table->construct_cell(op_plugin_cat_select("cats[{$codename}][subcategory]", "cats[{$codename}][subcategory_new]", "subcat_{$codename}", $cur_sub, $all_subcategories, "-- Sin subcategoría --", "+ Nueva subcategoría..."));
			$table->construct_row();
		}
	}

	$table->output("Categorías de plugins");

	echo "<br />".$form->generate_submit_button("Guardar categorías");
	echo $form->end();

	echo "
	<script type=\"text/javascript\">
	(function() {
		function toggleNewInput(select) {
			var input = document.getElementById(select.id + '_new');
			if(!input) { return; }
			if(select.value === '__new__') {
				input.style.display = '';
				input.focus();
			}
			else {
				input.style.display = 'none';
			}
		}
		var selects = document.querySelectorAll('.op-cat-select');
		for(var i = 0; i < selects.length; i++) {
			selects[i].addEventListener('change', function(e) { toggleNewInput(e.target); });
			toggleNewInput(selects[i]);
		}
	})();
	</script>";

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->plugins);

	$sub_tabs['plugins'] = array(
		'title' => $lang->plugins,
		'link' => "index.php?module=config-plugins",
		'description' => $lang->plugins_desc
	);
	$sub_tabs['update_plugins'] = array(
		'title' => $lang->plugin_updates,
		'link' => "index.php?module=config-plugins&amp;action=check",
		'description' => $lang->plugin_updates_desc
	);

	$sub_tabs['browse_plugins'] = array(
		'title' => $lang->browse_plugins,
		'link' => "index.php?module=config-plugins&amp;action=browse",
		'description' => $lang->browse_plugins_desc
	);
	$sub_tabs['manage_categories'] = array(
		'title' => "Categorías",
		'link' => "index.php?module=config-plugins&amp;action=managecategories",
		'description' => "Asigna cada plugin a una categoría/subcategoría para organizarlos en la lista principal."
	);

	$page->output_nav_tabs($sub_tabs, 'plugins');

	// Let's make things easier for our user - show them active
	// and inactive plugins in different lists
	$plugins_cache = $cache->read("plugins");
	$active_plugins = array();
	if(!empty($plugins_cache['active']))
	{
		$active_plugins = $plugins_cache['active'];
	}

	$plugins_list = get_plugins_list();

	$plugins->run_hooks("admin_config_plugins_plugin_list");

	if(!empty($plugins_list))
	{
		// Categorías visuales (agrupación por "carpetas"): puramente cosmético,
		// no afecta a instalar/activar/etc. Se gestionan desde Configuración →
		// Plugins → Categorías (tabla mybb_op_plugin_categories); si esa tabla
		// no existe todavía (plugin op_plugin_categories no instalado), cae de
		// vuelta al mapa estático de admin/inc/plugin_categories.php.
		$op_uncategorized_label = "Otros / sin categorizar";
		$op_plugin_categories = array();

		if($db->table_exists('op_plugin_categories'))
		{
			$op_cat_query = $db->simple_select('op_plugin_categories', 'codename, category, subcategory');
			while($op_cat_row = $db->fetch_array($op_cat_query))
			{
				$op_plugin_categories[$op_cat_row['codename']] = ($op_cat_row['subcategory'] !== '')
					? $op_cat_row['category'].' > '.$op_cat_row['subcategory']
					: $op_cat_row['category'];
			}
		}
		elseif(file_exists(MYBB_ROOT."admin/inc/plugin_categories.php"))
		{
			require_once MYBB_ROOT."admin/inc/plugin_categories.php";
		}

		$a_plugins = $i_plugins = array();

		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";

			if(!function_exists($infofunc))
			{
				continue;
			}

			$plugininfo = $infofunc();
			$plugininfo['codename'] = $codename;

			$category_raw = isset($op_plugin_categories[$codename]) ? $op_plugin_categories[$codename] : $op_uncategorized_label;
			// "Categoría > Subcategoría" — la subcategoría es opcional; sin
			// ">" en el valor, el plugin va directo bajo la categoría.
			$category_parts = array_map('trim', explode('>', $category_raw, 2));
			$top_category = $category_parts[0];
			$sub_category = isset($category_parts[1]) ? $category_parts[1] : '';

			if(isset($active_plugins[$codename]))
			{
				// This is an active plugin
				$plugininfo['is_active'] = 1;

				$a_plugins[$top_category][$sub_category][] = $plugininfo;
			}
			else
			{
				// Either installed and not active or completely inactive
				$plugininfo['is_active'] = 0;
				$i_plugins[$top_category][$sub_category][] = $plugininfo;
			}
		}

		ksort($a_plugins);
		ksort($i_plugins);

		echo op_plugin_categories_style_and_script();

		if(empty($a_plugins))
		{
			$table = new Table;
			$table->construct_header($lang->plugin);
			$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));
			$table->construct_cell($lang->no_active_plugins, array('colspan' => 3));
			$table->construct_row();
			$table->output($lang->active_plugin);
		}
		else
		{
			foreach($a_plugins as $top_category => $subgroups)
			{
				echo op_render_plugin_category_group($top_category, $subgroups, $lang->active_plugin, "op-cat-active");
			}
		}

		if(empty($i_plugins))
		{
			$table = new Table;
			$table->construct_header($lang->plugin);
			$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));
			$table->construct_cell($lang->no_inactive_plugins, array('colspan' => 3));
			$table->construct_row();
			$table->output($lang->inactive_plugin);
		}
		else
		{
			foreach($i_plugins as $top_category => $subgroups)
			{
				echo op_render_plugin_category_group($top_category, $subgroups, $lang->inactive_plugin, "op-cat-inactive");
			}
		}
	}
	else
	{
		// No plugins
		$table = new Table;
		$table->construct_header($lang->plugin);
		$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));

		$table->construct_cell($lang->no_plugins, array('colspan' => 3));
		$table->construct_row();

		$table->output($lang->plugins);
	}

	$page->output_footer();
}

/**
 * @return array
 */
function get_plugins_list()
{
	// Get a list of the plugin files which exist in the plugins directory
	$dir = @opendir(MYBB_ROOT."inc/plugins/");
	if($dir)
	{
		while($file = readdir($dir))
		{
			$ext = get_extension($file);
			if($ext == "php")
			{
				$plugins_list[] = $file;
			}
		}
		@sort($plugins_list);
	}
	@closedir($dir);

	return $plugins_list;
}

/**
 * @param array $plugin_list
 */
function build_plugin_list($plugin_list)
{
	global $lang, $mybb, $plugins, $table;

	foreach($plugin_list as $plugininfo)
	{
		if(!empty($plugininfo['website']))
		{
			$plugininfo['name'] = "<a href=\"".$plugininfo['website']."\">".$plugininfo['name']."</a>";
		}

		if(!empty($plugininfo['authorsite']))
		{
			$plugininfo['author'] = "<a href=\"".$plugininfo['authorsite']."\">".$plugininfo['author']."</a>";
		}

		if($plugins->is_compatible($plugininfo['codename']) == false)
		{
			$compatibility_warning = "<span style=\"color: red;\">".$lang->sprintf($lang->plugin_incompatible, $mybb->version)."</span>";
		}
		else
		{
			$compatibility_warning = "";
		}

		$installed_func = "{$plugininfo['codename']}_is_installed";
		$install_func = "{$plugininfo['codename']}_install";
		$uninstall_func = "{$plugininfo['codename']}_uninstall";

		$installed = true;
		$install_button = false;
		$uninstall_button = false;

		if(function_exists($installed_func) && $installed_func() != true)
		{
			$installed = false;
		}

		if(function_exists($install_func))
		{
			$install_button = true;
		}

		if(function_exists($uninstall_func))
		{
			$uninstall_button = true;
		}

		$table->construct_cell("<strong>{$plugininfo['name']}</strong> ({$plugininfo['version']})<br /><small>{$plugininfo['description']}</small><br /><i><small>{$lang->created_by} {$plugininfo['author']}</small></i>");

		// Plugin is not installed at all
		if($installed == false)
		{
			if($compatibility_warning)
			{
				$table->construct_cell("{$compatibility_warning}", array("class" => "align_center", "colspan" => 2));
			}
			else
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=activate&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->install_and_activate}</a>", array("class" => "align_center", "colspan" => 2));
			}
		}
		// Plugin is activated and installed
		else if($plugininfo['is_active'])
		{
			$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->deactivate}</a>", array("class" => "align_center", "width" => 150));
			if($uninstall_button)
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->uninstall}</a>", array("class" => "align_center", "width" => 150));
			}
			else
			{
				$table->construct_cell("&nbsp;", array("class" => "align_center", "width" => 150));
			}
		}
		// Plugin is installed but not active
		else if($installed == true)
		{
			if($compatibility_warning && !$uninstall_button)
			{
				$table->construct_cell("{$compatibility_warning}", array("class" => "align_center", "colspan" => 2));
			}
			else
			{
				$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=activate&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->activate}</a>", array("class" => "align_center", "width" => 150));
				if($uninstall_button)
				{
					$table->construct_cell("<a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin={$plugininfo['codename']}&amp;my_post_key={$mybb->post_code}\">{$lang->uninstall}</a>", array("class" => "align_center", "width" => 150));
				}
				else
				{
					$table->construct_cell("&nbsp;", array("class" => "align_center", "width" => 150));
				}
			}
		}
		$table->construct_row();
	}
}

/**
 * Renderiza una categoría de nivel superior completa: los plugins sin
 * subcategoría van en una tabla directamente dentro del bloque, y cada
 * subcategoría con nombre se anida como su propio bloque plegable dentro
 * de este. Puramente cosmético, no cambia nada de instalar/activar/etc.
 *
 * @param string $top_category Nombre de la categoría de nivel superior.
 * @param array $subgroups Array [subcategoria => array de $plugininfo], subcategoría '' = sin subcategoría.
 * @param string $status_label $lang->active_plugin o $lang->inactive_plugin.
 * @param string $anchor_prefix Prefijo único (p.ej. "op-cat-active").
 * @return string
 */
/**
 * Desplegable de categoría/subcategoría para la pestaña "Categorías": lista
 * las opciones ya existentes (para no arriesgarse a un typo al reescribir un
 * nombre) más una opción "+ Nueva..." que revela un campo de texto al lado
 * (oculto por JS) para darlas de alta sin tocar código. El valor centinela
 * "__new__" lo reconoce el manejador POST de más arriba en este archivo.
 *
 * @param string $name Nombre del <select> (p.ej. "cats[codename][category]").
 * @param string $name_new Nombre del input de texto para "nueva categoría" (p.ej. "cats[codename][category_new]") — debe ser una clave HERMANA dentro del mismo array, no basta con concatenar "_new" al final de $name.
 * @param string $id ID único del <select>, también usado como base del ID del input de texto ("{$id}_new").
 * @param string $current Valor actualmente asignado (categoría o subcategoría), puede ser ''.
 * @param array $options Lista de nombres ya existentes entre los que elegir.
 * @param string $empty_label Texto de la opción vacía ("-- Sin categoría --").
 * @param string $new_label Texto de la opción para crear una nueva.
 * @return string
 */
function op_plugin_cat_select($name, $name_new, $id, $current, $options, $empty_label, $new_label)
{
	$html = "<select name=\"".htmlspecialchars_uni($name)."\" id=\"".htmlspecialchars_uni($id)."\" class=\"op-cat-select\" style=\"width: 95%;\">";
	$html .= "<option value=\"\"".($current == '' ? " selected=\"selected\"" : "").">".htmlspecialchars_uni($empty_label)."</option>";

	foreach($options as $option)
	{
		$selected = ($current !== '' && $current === $option) ? " selected=\"selected\"" : "";
		$html .= "<option value=\"".htmlspecialchars_uni($option)."\"".$selected.">".htmlspecialchars_uni($option)."</option>";
	}

	// Si el valor actual no está entre las opciones conocidas (p.ej. quedó
	// huérfano tras borrar todos los plugins que la usaban), se añade igual
	// para no perderlo silenciosamente al guardar de nuevo.
	if($current !== '' && !in_array($current, $options))
	{
		$html .= "<option value=\"".htmlspecialchars_uni($current)."\" selected=\"selected\">".htmlspecialchars_uni($current)."</option>";
	}

	$html .= "<option value=\"__new__\">".htmlspecialchars_uni($new_label)."</option>";
	$html .= "</select>";
	$html .= " <input type=\"text\" id=\"".htmlspecialchars_uni($id)."_new\" name=\"".htmlspecialchars_uni($name_new)."\" style=\"display: none; width: 95%; margin-top: 4px;\" placeholder=\"Nombre nuevo\" />";

	return $html;
}

function op_render_plugin_category_group($top_category, $subgroups, $status_label, $anchor_prefix)
{
	// build_plugin_list() hace "global $table;" internamente, asumiendo que
	// quien la llama ya creó $table como variable global de verdad — de ahí
	// que aquí también haga falta declararla global antes de reasignarla,
	// o build_plugin_list() vería null y fallaría con un error fatal.
	global $lang, $table;

	$total = 0;
	foreach($subgroups as $sub_plugins)
	{
		$total += count($sub_plugins);
	}

	$body_html = '';

	// Plugins sin subcategoría: tabla directa, sin bloque anidado propio.
	if(!empty($subgroups['']))
	{
		$table = new Table;
		$table->construct_header($lang->plugin);
		$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));
		build_plugin_list($subgroups['']);
		$body_html .= $table->output('', 1, 'general', true);
	}

	// Subcategorías con nombre: cada una, su propio bloque plegable anidado.
	foreach($subgroups as $sub_category => $sub_plugins)
	{
		if($sub_category === '')
		{
			continue;
		}

		$table = new Table;
		$table->construct_header($lang->plugin);
		$table->construct_header($lang->controls, array("colspan" => 2, "class" => "align_center", "width" => 300));
		build_plugin_list($sub_plugins);

		$sub_heading = htmlspecialchars_uni($sub_category)." (".count($sub_plugins).")";
		$sub_anchor = $anchor_prefix."-sub-".md5($top_category.'>'.$sub_category);
		$body_html .= op_wrap_collapsible_category($table->output('', 1, 'general', true), $sub_heading, $sub_anchor, true);
	}

	$heading = $status_label." &mdash; ".htmlspecialchars_uni($top_category)." (".$total.")";
	$anchor = $anchor_prefix."-".md5($top_category);

	return op_wrap_collapsible_category($body_html, $heading, $anchor, false);
}

/**
 * Envuelve HTML ya construido (una tabla, o el contenido de una categoría
 * con sus subcategorías anidadas dentro) en un contenedor plegable/
 * desplegable. Puramente cosmético, no cambia nada de instalar/activar/etc.
 *
 * @param string $inner_html Contenido ya construido (tabla u otros bloques anidados).
 * @param string $heading Texto ya escapado para mostrar como cabecera.
 * @param string $anchor_id Id único para este bloque (para el toggle y el localStorage).
 * @param bool $nested Si es un bloque de subcategoría (se indenta visualmente).
 * @return string
 */
function op_wrap_collapsible_category($inner_html, $heading, $anchor_id, $nested = false)
{
	$anchor_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $anchor_id);
	$extra_class = $nested ? ' op-plugin-cat-nested' : '';

	return "<div class=\"op-plugin-cat{$extra_class}\">\n"
		."<div class=\"op-plugin-cat-header\" data-target=\"{$anchor_id}\">"
		."<span class=\"op-plugin-cat-arrow\">&#9660;</span> {$heading}"
		."</div>\n"
		."<div class=\"op-plugin-cat-body\" id=\"{$anchor_id}\">\n"
		.$inner_html
		."\n</div>\n"
		."</div>\n";
}

/**
 * CSS + JS del plegado de categorías. Se imprime una sola vez por carga de
 * página (la propia función lleva su guarda estática), aislado a esta
 * página — no toca ningún fichero de tema/JS general del admin.
 *
 * @return string
 */
function op_plugin_categories_style_and_script()
{
	static $done = false;
	if($done)
	{
		return '';
	}
	$done = true;

	return <<<'HTML'
<style>
.op-plugin-cat { margin-bottom: 8px; }
.op-plugin-cat-header {
	cursor: pointer;
	background: #3c3c3c;
	color: #fff;
	padding: 6px 10px;
	border-radius: 3px 3px 0 0;
	font-weight: bold;
	-webkit-user-select: none;
	user-select: none;
}
.op-plugin-cat-header:hover { background: #4c4c4c; }
.op-plugin-cat-arrow {
	display: inline-block;
	transition: transform 0.15s;
	margin-right: 4px;
}
.op-plugin-cat.op-collapsed .op-plugin-cat-arrow { transform: rotate(-90deg); }
.op-plugin-cat.op-collapsed .op-plugin-cat-body { display: none; }
.op-plugin-cat-nested {
	margin-left: 20px;
	margin-top: 8px;
	border-left: 3px solid #888;
	padding-left: 8px;
}
.op-plugin-cat-nested .op-plugin-cat-header {
	background: #666;
}
.op-plugin-cat-nested .op-plugin-cat-header:hover { background: #767676; }
</style>
<script>
(function($) {
	$(function() {
		$('.op-plugin-cat-header').each(function() {
			var $header = $(this);
			var target = $header.data('target');
			var $wrap = $header.closest('.op-plugin-cat');
			var key = 'op_plugin_cat_collapsed_' + target;
			try {
				if (localStorage.getItem(key) === '1') {
					$wrap.addClass('op-collapsed');
				}
			} catch (e) {}
			$header.on('click', function() {
				$wrap.toggleClass('op-collapsed');
				try {
					localStorage.setItem(key, $wrap.hasClass('op-collapsed') ? '1' : '0');
				} catch (e) {}
			});
		});
	});
})(jQuery);
</script>
HTML;
}
