<?php

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("parse_message_end", "BBCustom_spoiler_run");

if(!function_exists('create_custom_spoiler')) {
	require_once MYBB_ROOT.'inc/plugins/lib/spoiler.php';
}

function BBCustom_spoiler_info()
{
global $mybb;
	return array(
		"name"			=> "BBCode Spoiler",
		"description"	=> "BBCode [spoiler] como dios manda",
		"website"		=> "",
		"author"		=> "Cascabelles",
		"authorsite"	=> "",
		"version"		=> "1.0",
		"codename"		=> "BBCustom_spoiler",
		"compatibility"	=> "*"
	);
}


function BBCustom_spoiler_activate()
{
	global $db, $mybb;
	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		$estilo = array(
				'name'         => 'spoiler.css',
				'tid'          => $theme['tid'],
				'attachedto'   => 'showthread.php|newthread.php|newreply.php|editpost.php|private.php|announcements.php',
				'stylesheet'   => '.spoiler {background: #f5f5f5;border: 1px solid #bbb;margin-bottom: 5px;border-radius: 5px}
.spoiler_button {background-color: #bab7b7;border-radius: 4px 4px 0 0;border: 1px solid #c2bfbf;display: block;color: #605d5d;font-family: Tahoma;font-size: 11px;font-weight: bold;padding: 10px;text-align: center;text-shadow: 1px 1px 0px #b4b3b3;margin: auto auto;cursor: pointer}
.spoiler_title {text-align: center}
.spoiler_content_title{font-weight: bold;border-bottom:1px dashed #bab7b7}
.spoiler_content {padding: 5px;height: auto;overflow:hidden;width:95%;background: #f5f5f5;word-wrap: break-word}',
			'lastmodified' => TIME_NOW
		);
		$sid = $db->insert_query('themestylesheets', $estilo);
		$db->update_query('themestylesheets', array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		update_theme_stylesheet_list($theme['tid']);
	}
	
	require MYBB_ROOT.'inc/adminfunctions_templates.php';

    find_replace_templatesets("codebuttons", '#'.preg_quote('<script type="text/javascript">
var partialmode = {$mybb->settings[\'partialmode\']},').'#siU', '<script type="text/javascript" src="{$mybb->asset_url}/jscripts/spoiler.js?ver=1804"></script>
<script type="text/javascript">
var partialmode = {$mybb->settings[\'partialmode\']},');	
    find_replace_templatesets("codebuttons", '#'.preg_quote('{$link}').'#', '{$link},spoiler');
}


function BBCustom_spoiler_deactivate()
{
	global $db;
	$db->delete_query('themestylesheets', "name='spoiler.css'");
	$query = $db->simple_select('themes', 'tid');
	while($theme = $db->fetch_array($query))
	{
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		update_theme_stylesheet_list($theme['tid']);
	}
   	require MYBB_ROOT.'inc/adminfunctions_templates.php';
    find_replace_templatesets("codebuttons", '#'.preg_quote('<script type="text/javascript" src="{$mybb->asset_url}/jscripts/spoiler.js?ver=1804"></script>').'#', '',0);
    find_replace_templatesets("codebuttons", '#'.preg_quote(',spoiler').'#', '',0);
}


function BBCustom_spoiler_run(&$message)
{
	global $lang;

	try {

		if(!function_exists('create_custom_spoiler')) {
			require_once MYBB_ROOT.'inc/plugins/lib/spoiler.php';
		}

		$lang->load("my_spoiler", false, true);

		$make_spoiler = function($title, $content) use ($lang) {
			// Title comes from parse_message_end where & is already &amp; — decode before
			// passing to create_custom_spoiler which will call htmlspecialchars() internally.
			$raw_title = $title ? html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
			$display_title = $raw_title !== '' ? $raw_title : $lang->my_spoiler_show;
			return create_custom_spoiler($display_title, $content);
		};

		// [spoiler]...[/spoiler]
		while (preg_match('#\[spoiler\](.*?)\[\/spoiler\]#si', $message, $matches)) {
			$message = str_replace($matches[0], $make_spoiler('', $matches[1]), $message);
		}

		// [spoiler="title"]...[/spoiler]
		while (preg_match('#\[spoiler="(.*?)"\](.*?)\[\/spoiler\]#si', $message, $matches)) {
			$message = str_replace($matches[0], $make_spoiler($matches[1], $matches[2]), $message);
		}

		// [spoiler=title]...[/spoiler]
		while (preg_match('#\[spoiler=(.*?)\](.*?)\[\/spoiler\]#si', $message, $matches)) {
			$message = str_replace($matches[0], $make_spoiler($matches[1], $matches[2]), $message);
		}

		// [extra]...[/extra]
		while (preg_match('#\[extra\](.*?)\[\/extra\]#si', $message, $matches)) {
			$message = str_replace($matches[0], $make_spoiler('', $matches[1]), $message);
		}

		// [extra="title"]...[/extra]
		while (preg_match('#\[extra="(.*?)"\](.*?)\[\/extra\]#si', $message, $matches)) {
			$message = str_replace($matches[0], $make_spoiler($matches[1], $matches[2]), $message);
		}

		// [extra=title]...[/extra]
		while (preg_match('#\[extra=(.*?)\](.*?)\[\/extra\]#si', $message, $matches)) {
			$message = str_replace($matches[0], $make_spoiler($matches[1], $matches[2]), $message);
		}

	} catch (\Throwable $e) {
		$message = '<div style="background:#ffcccc;border:2px solid red;padding:10px;margin:10px 0;font-family:monospace;font-size:13px;">'
			. '<strong>[myspoiler ERROR]</strong> '
			. htmlspecialchars($e->getMessage())
			. ' in ' . htmlspecialchars($e->getFile()) . ' on line ' . $e->getLine()
			. '<br><pre style="white-space:pre-wrap;word-break:break-all;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
			. '</div>' . $message;
	}

	return $message;
}