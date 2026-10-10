<?php
if(!defined("IN_MYBB"))
{
    die("This file cannot be accessed directly.");
}

$plugins->add_hook('index_start', 'add_onlinetoday', 20);
$plugins->add_hook("fetch_wol_activity_end", "onlinetoday_wol_activity");
$plugins->add_hook("build_friendly_wol_location_end", "onlinetoday_friendly_wol_activity");
$plugins->add_hook("global_start", "online_today_load_templates");
$plugins->add_hook("admin_load", "online_today_admin_load");

function onlinetoday_info()
{
	global $mybb;
	$onlinetoday_opts = "";
	if($mybb->settings['onlinetoday_active'] == 1)
	{
		$onlinetoday_opts = '<div style="float: right;"><a href="index.php?module=config-plugins&action=onlinetoday" style="color:#035488; no-repeat 0px 18px; padding: 21px; text-decoration: none;">Configure</a></div>';		
	}
	return array(
		"name"			=>	"Online Today",
		"description"	=>	"Shows the users online on your forums.".$onlinetoday_opts,
		"website"		=>	"https://mybb.es",
		"author"		=>	"Whiteneo",
		"authorsite"	=>	"https://mybb.es",
		"version"		=>	"2.1",
		"codename"		=>	"online_today",
		"compatibility" =>	"18*",
	);
}

function onlinetoday_activate()
{
	global $db;

	// Add settings group for this plugin
    $query = $db->simple_select("settinggroups", "COUNT(*) as item_rows");
    $item_rows = $db->fetch_field($query, "item_rows");
	
	$options_group = array(
		"name"			=> "onlinetoday",
		"title"			=> "Users Online",
		"description"	=> "Shows online users nicely",
		"disporder"		=> $item_rows+1,
		"isdefault"		=> 0
	);	
	$db->insert_query("settinggroups", $options_group);
	$gid = $db->insert_id();
	
	// Add every setting to be used by this plugin
	$options[]= array(
		"name"			=> "onlinetoday_active",
		"title"			=> "Enable/Disable Plugin",
		"description"	=> "Set to no if you wish to disable this plugin (enabled by default)",
		"optionscode" 	=> "onoff",
		"value"			=> 1,
		"disporder"		=> 10,
		"gid"			=> (int)$gid,
	);

	$options[] = array(
		"name"			=> "onlinetoday_items_index",
		"title"			=> "Users to display on index page",
		"description"   => "Set ammount of users displayed on index (values between 5 and 25 are the best option)",
		"optionscode" 	=> "text",
		"value"			=> '18',
		"disporder"		=> 20,
		"gid"			=> $db->escape_string($gid),
	);		

	$options[] = array(
		"name"			=> "onlinetoday_items_all",
		"title"			=> "Users to display in onlinetoday.php page",
		"description"   => "Set ammount of users displayed in online.php page (values between 25 and 50 are the best option)",
		"optionscode" 	=> "text",
		"value"			=> '50',
		"disporder"		=> 30,
		"gid"			=> $db->escape_string($gid),
	);		

	$options[] = array(
		"name"			=> "onlinetoday_orderby",
		"title"			=> "Set the order to be showed for users online list",
		"description"   => "Select from the list what order is setting up to display online today data",
		"optionscode" 	=> 'select \n1=Order by Username \n2=Order by Last activity',
		"value"			=> 2,
		"disporder"		=> 40,
		"gid"			=> $db->escape_string($gid),
	);		

	$options[] = array(
		"name"			=> "onlinetoday_formatname",
		"title"			=> "Show formatted name ?",
		"description"   => "Do you want to show formatted names by htmls usergroups configuration",
		"optionscode" 	=> 'yesno',
		"value"			=> 1,
		"disporder"		=> 50,
		"gid"			=> $db->escape_string($gid),
	);		

	$options[] = array(
		"name"			=> "onlinetoday_avatars",
		"title"			=> "Show avatars ?",
		"description"   => "Do you want to display avatars of users",
		"optionscode" 	=> 'yesno',
		"value"			=> 1,
		"disporder"		=> 60,
		"gid"			=> $db->escape_string($gid),
	);		

	$options[]= array(
		"name"			=> "onlinetoday_time",
		"title"			=> "Time to search online stats",
		"description"	=> "Select from the options if you wish to show weekly, monthy or today",
        'optionscode' 	=> 'select \n1=Today \n2=Weekly \n3=Monthly',
		"value"			=> 1,
		"disporder"		=> 70,
		"gid"			=> $db->escape_string($gid),
	);
	
	// Insert settings to db...
	foreach($options as $opt)
	{
		$db->insert_query("settings", $opt);
	}
	
	rebuild_settings();
	
	$template = array(
		"tid"		=> 0,
		"title"		=> "online_today_all",
		"template"	=> "<html>
<head>
<title>{\$mybb->settings[\'bbname\']}</title>
{\$headerinclude}
</head>
<body>
{\$header}
<table border=\"0\" cellspacing=\"{\$theme[\'borderwidth\']}\" cellpadding=\"{\$theme[\'tablespace\']}\" class=\"tborder\">
	<thead>
	<tr>
		<td class=\"thead{\$collapsedthead[\'boardstats\']}\">
			<div class=\"expcolimage\"><img src=\"{\$theme[\'imgdir\']}/collapse{\$collapsedimg[\'boardstats\']}.png\" id=\"onlinetoday_img\" class=\"expander\" alt=\"[-]\" title=\"[-]\" /></div>
			<div><strong>{\$lang->online_title}</strong></div>
		</td>
	</tr>
	</thead>
	<tbody style=\"{\$collapsed[\'boardstats_e\']}\" id=\"onlinetoday_e\">
		{\$online_today}
	</tbody>
</table>
<br />	
{\$multipage}	
{\$footer}
</body>
</html>",
		"sid"		=> "-1"
	);
	$db->insert_query("templates", $template);
	
	$template = array(
		"tid"		=> 0,
		"title"		=> "online_today_index",
		"template"	=> "<tr>
	<td class=\"tcat\"><strong>{\$lang->online_title}</strong> {\$online_today_all}</td>
</tr>
<tr>
	<td class=\"trow1\"><span class=\"smalltext\">{\$lang->online_today_note}<br />{\$onlinemembers}</span></td>
</tr>",
		"sid"		=> "-1"
	);
	$db->insert_query("templates", $template);

	$template = array(
		"tid"		=> 0,
		"title"		=> "online_today_rows",
		"template"	=> "<div class=\"avatarep_online_row\">
	<span><img src=\"{\$user[\'avatar\']}\" alt=\"avatar\" class=\"avatarep_image {\$class}\" /></span>
	<span class=\"avatarep_span\">{\$status}{\$user[\'profilelink\']}{\$invisiblemark}</span>
</div>",
		"sid"		=> "-1"
	);
	
	$db->insert_query("templates", $template);

	$template = array(
		"tid"		=> 0,
		"title"		=> "online_today_rows_nor",
		"template"	=> "<div class=\"avatarep_online_row_nor\">
	<span class=\"avatarep_span_nor\">{\$user[\'profilelink\']}{\$invisiblemark}</span>
</div>",
		"sid"		=> "-1"
	);
	
	$db->insert_query("templates", $template);
	
//Create stylesheet for this plugin...
	$style = '.avatarep_online_row{text-align:center;width:100px;display:inline-block;padding:0px 4px;}
.avatarep_online_row_nor{width:auto;display:inline-block;padding:0px 2px;}
.avatarep_image{display:block;width:80px;height:60px;border: 1px solid #cacaca; background: #fff;padding:8px;border-radius:4px;}
.avatarep_span{text-align:center;}
.ot_offline{border-left: 4px solid #bf5656;}
.ot_online{border-left: 4px solid #399c3a;}
@media screen and (max-width:650px){
.avatarep_span{display:none;}
.avatarep_online_row_nor{width:auto;display:inline-block;}
.avatarep_span_nor{font-size: 8px;}
.avatarep_image{width:28px;height:20px;padding:3px;border-radius:2px;}
.avatarep_online_row{width:30px;}
.ot_offline{border-left: 2px solid #bf5656;}
.ot_online{border-left: 2px solid #399c3a;}
}';	
	$stylesheet = array(
		"name"			=> "onlinetoday.css",
		"tid"			=> 1,
		"attachedto"	=> 0,		
		"stylesheet"	=> $db->escape_string($style),
		"cachefile"		=> "onlinetoday.css",
		"lastmodified"	=> TIME_NOW
	);

	$sid = $db->insert_query("themestylesheets", $stylesheet);
	
	//Archivo requerido para cambios en estilos y plantillas.
	require_once MYBB_ADMIN_DIR.'/inc/functions_themes.php';
	cache_stylesheet($stylesheet['tid'], $stylesheet['cachefile'], $style);
	update_theme_stylesheet_list(1, false, true);
	require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('index_boardstats', '#{\$whosonline}#', "{\$whosonline}\n{\$online_today}");
}

function onlinetoday_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN ('online_today_index','online_today_all','online_today_rows','online_today_rows_nor')");
	$db->delete_query("settings", "name IN ('onlinetoday_active', 'onlinetoday_items_index', 'onlinetoday_items_all', 'onlinetoday_options')");
	$db->delete_query("settinggroups", "name='onlinetoday'");
	rebuild_settings();	
  	$db->delete_query('themestylesheets', "name IN('onlinetoday.css','online_today.css','onlinenow.css')");
	$query = $db->simple_select('themes', 'tid');
	while($style = $db->fetch_array($query))
	{
		require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';
		cache_stylesheet($style['tid'], $style['cachefile'], $style['stylesheet']);
		update_theme_stylesheet_list($style['tid'], false, true);	
	}	
	require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('index_boardstats', '#(\n?){\$online_today}#', '', 0);
}

function online_today_admin_load()
{
	global $mybb;
	if ($mybb->input['action'] == "onlinetoday")
	{
		global $db;
		$query = $db->simple_select('settinggroups', 'gid', "name='onlinetoday'", array('limit' => 1));
		$gid = (int)$db->fetch_field($query, 'gid');
		admin_redirect("index.php?module=config-settings&action=change&gid={$gid}");
	}
}

function online_today_load_templates()
{
	global $mybb, $GLOBALS;
	if($mybb->settings['onlinetoday_active'] == 0)
	{
		return false;
	}
	if(isset($GLOBALS['templatelist']))
	{
		if(THIS_SCRIPT == "index.php" || THIS_SCRIPT == "onlinetoday.php" && $mybb->settings['onlinetoday_avatars'] == 0)
		{	
			$GLOBALS['templatelist'] .= ',online_today_rows_nor, online_today_index';
		}
		else if(THIS_SCRIPT == "index.php" || THIS_SCRIPT == "onlinetoday.php" && $mybb->settings['onlinetoday_avatars'] == 1)
		{	
			$GLOBALS['templatelist'] .= ',online_today_rows, online_today_index';
		}		
	}
}
function add_onlinetoday()
{
	global $db, $mybb, $templates, $online_today, $lang, $theme, $spiders, $lang;
	if($mybb->settings['onlinetoday_active'] == 0)
	{
		return false;
	}
	$lang->load('onlinetoday', false, true);	
	$online_today = '';
	if(!is_array($spiders))
	{
		global $cache;
		$spiders = $cache->read('spiders');
	}	
	if($mybb->settings['showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0)
	{
		$limit = (int)$mybb->settings['onlinetoday_items_index'];
		if($limit == 0)
		{
			$limit = 18;
		}
		if($mybb->settings['onlinetoday_time'] == 1)
		{
			$time_rest = 86400;	
			$lang->online_title = $lang->online_title_today;
		}
		else if($mybb->settings['onlinetoday_time'] == 2)
		{		
			$time_rest = 604800;
			$lang->online_title = $lang->online_title_weekly;
		}
		else if($mybb->settings['onlinetoday_time'] == 3)
		{			
			$time_rest = 2592000;
			$lang->online_title = $lang->online_title_monthly;
		}
		else
		{
			$time_rest = 86400;			
			$lang->online_title = $lang->online_title_today;
		}				
		$timesearch = TIME_NOW - $time_rest;			
		if($mybb->settings['onlinetoday_orderby'] == 1)
			$order_by = " ORDER BY u.username ASC, s.time DESC";
		else if($mybb->settings['onlinetoday_orderby'] == 2)
			$order_by = " ORDER BY u.lastactive DESC, s.time DESC";
		else
			$order_by = " ORDER BY u.username ASC, s.time DESC";		
		$numusers = $db->fetch_field($db->simple_select('users', 'COUNT(*) AS numtot', "uid>0 AND lastactive>'{$timesearch}'"), 'numtot');
		$numguests = $db->fetch_field($db->simple_select('sessions', 'COUNT(DISTINCT ip) AS numguests', "uid=0 AND nopermission != 1 AND time>'{$timesearch}'"), 'numguests');		
		$queries = array();

		$queries[] = $db->simple_select(
			"users u LEFT JOIN ".TABLE_PREFIX."sessions s ON (u.uid=s.uid)", 
			"s.sid, s.ip, s.time, s.location, u.uid, u.username, u.invisible, u.usergroup, u.displaygroup, u.avatar, u.lastactive",
			"u.lastactive>'{$timesearch}'{$order_by} LIMIT {$limit}"
		);
		$queries[] = $db->simple_select(
			"sessions s LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)",
			"s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup, u.avatar, u.lastactive",
			"(s.uid != 0 OR SUBSTR(s.sid,4,1) = '=') AND s.time>'{$timesearch}'{$order_by} LIMIT {$limit}"
		);
		$onlinemembers = $comma = '';
		$membercount = $guestcount = $anoncount = 0;
		$doneusers = $ips = array();
		foreach($queries as $query)
		{
			while($user = $db->fetch_array($query))
			{
				if(isset($user['sid']))
				{
					$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));
				}

				if($user['uid'] > 0)
				{
					if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
					{
						if($user['invisible'] == 1)
						{
							++$anoncount;
						}
						++$membercount;
						if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
						{
							$invisiblemark = ($user['invisible'] == 1) ? "*" : "";
							$compare = TIME_NOW - $mybb->settings['wolcutoffmins']*60;		
							if(isset($user['time']) && $compare <= $user['time'])
							{
								$status = '<img src="images/buddy_online.png" alt="Online" style="position: absolute; margin-left: -20px;" />';
								$class = "ot_online";
							}
							else
							{
								$status = '<img src="images/buddy_offline.png" alt="Offline" style="position: absolute; margin-left: -20px;" />';								
								$class = "ot_offline";								
							}					
							$user['username'] = htmlspecialchars_uni($user['username']);
							$user['lastactive'] = my_date('normal', $user['lastactive']);
							if($mybb->settings['onlinetoday_formatname'] == 1)
							{
								$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);	
								// $user['link'] = $mybb->settings['bburl']."/".get_profile_link($user['uid']);
								$user['link'] = $mybb->settings['bburl']."/op/personaje.php?uid=".$user['uid'];
								$user['profilelink'] = $comma."<a href=\"{$user['link']}\" title=\"{$user['lastactive']}\">{$user['username']}</a>";
							}
							if($mybb->settings['onlinetoday_formatname'] == 0)
							{
								// $user['link'] = $mybb->settings['bburl']."/".get_profile_link($user['uid']);
								$user['link'] = $mybb->settings['bburl']."/op/personaje.php?uid=".$user['uid'];
								$user['profilelink'] = $comma."<a href=\"{$user['link']}\" title=\"{$user['lastactive']}\">{$user['username']}</a>";
							}
							if($mybb->settings['onlinetoday_avatars'] == 0)
								$comma = " ";
							if($mybb->settings['onlinetoday_avatars'] == 1)
							{
								$user['avatar'] = htmlspecialchars_uni($user['avatar']);
								if(empty($user['avatar']))
								$user['avatar'] = "images/default_avatar.png";								
							}
							else
							{
								$user['avatar'] = "";
							}
							if($mybb->settings['onlinetoday_avatars'] == 0)
							eval("\$onlinemembers .= \"".$templates->get("online_today_rows_nor", 1, 0)."\";");
							else if($mybb->settings['onlinetoday_avatars'] == 1)
							eval("\$onlinemembers .= \"".$templates->get("online_today_rows", 1, 0)."\";");								
						}

						if(isset($user['time']))
						{
							$doneusers[$user['uid']] = $user['time'];
						}
						else
						{
							$doneusers[$user['uid']] = $user['lastactive'];
						}
					}
				}
				// Otherwise this session is a bot
				else if(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
				{
					$user['bot'] = $spiders[$botkey]['name'];
					$user['usergroup'] = $spiders[$botkey]['usergroup'];
					$guests[] = $user;
					$user['avatar'] = "images/".$user['bot'].".png";			
					$compare = TIME_NOW - $mybb->settings['wolcutoffmins']*60;		
					if(isset($user['time']) && $compare <= $user['time'])
					{
						$status = '<img src="images/buddy_online.png" alt="Online" style="position: absolute; margin-left: -20px;" />';
						$class = "ot_online";
					}
					else
					{
						$status = '<img src="images/buddy_offline.png" alt="Offline" style="position: absolute; margin-left: -20px;" />';								
						$class = "ot_offline";								
					}	
					$guests[] = $user;
					$uname = htmlspecialchars_uni($user['bot']);
					$uname = trim($user['bot']);
					$uname = strtolower($user['bot']);				
					if($mybb->settings['onlinetoday_formatname'] == 1)					
						$user['username'] .= $comma.format_name($user['bot'], $user['usergroup']);
					else
						$user['username'] .= $comma.$user['bot'];
					if($mybb->settings['onlinetoday_formatname'] == 0 && $mybb->settings['onlinetoday_avatars'] == 0)
						$comma = ", ";
					if($mybb->settings['onlinetoday_avatars'] == 1)
						$template = "<div class=\"avatarep_online_row\"><span><img src=\"images/onlinetoday/".$uname.".png\" class=\"avatarep_image ".$class."\" /></span><span class=\"avatarep_span\">".$user['username']."</span></div>";
					else
						$template = "<span class=\"avatarep_span\">".$user['username']."</span>";
					$onlinemembers .= $template;
					++$botcount;					
				}
			}
		}
		
		$online_today_all = "[<a href=\"onlinetoday.php?my_post_key={$mybb->post_code}\">{$lang->complete_list}</a>]";
		$membercount = (int)$numusers;	
		$guestcount = (int)$numguests;
		$onlinecount = (int)$numtot + (int)$guestcount;
		$numtotal = $membercount + $guestcount;
		$onlinebit = ($onlinecount != 1) ? $lang->online_plural : $lang->online_singular;
		$memberbit = ($membercount != 1) ? $lang->online_member_plural : $lang->online_member_singular;
		$anonbit = ($anoncount != 1) ? $lang->online_anon_plural : $lang->online_anon_singular;
		$guestbit = ($guestcount != 1) ? $lang->online_guest_plural : $lang->online_guest_singular;
		$my_time = dnt_my_time($time_rest);
		$lang->online_today_note = $lang->sprintf($lang->online_today_note, my_number_format($numtotal), $onlinebit, $my_time, my_number_format($membercount), $memberbit, my_number_format($anoncount), $anonbit, my_number_format($guestcount), $guestbit);
		eval("\$online_today = \"".$templates->get("online_today_index")."\";");
	}
}

function dnt_my_time($time){
	global $mybb, $lang;
	if($mybb->settings['onlinetoday_active'] == 0)
	{
		return false;
	}	
	$lang->load('onlinetoday', false, true);	
	if($mybb->settings['onlinetoday_time'] == 1)
	{
		$lang->online_time = $lang->online_time_today;
	}
	else if($mybb->settings['onlinetoday_time'] == 2)
	{		
		$lang->online_time = $lang->online_time_weekly;
	}
	else if($mybb->settings['onlinetoday_time'] == 3)
	{			
		$lang->online_time = $lang->online_time_monthly;
	}
	return $lang->online_time; 
}

// Load location for user...
function onlinetoday_wol_activity($user_activity)
{
	global $mybb, $user, $session;

	if(!$mybb->settings['onlinetoday_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$split_loc = explode(".php", $user_activity['location']);
	if($split_loc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}
	
	if ($filename == "onlinetoday")
	{
		$user_activity['activity'] = "onlinetoday";
	}
	
	return $user_activity;
}
// Set location for user and then show it ...
function onlinetoday_friendly_wol_activity($plugin_array)
{
	global $mybb, $lang, $session;

	if(!$mybb->settings['onlinetoday_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$lang->load('onlinetoday', false, true);
	
	if ($plugin_array['user_activity']['activity'] == "onlinetoday")
	{
		$plugin_array['location_name'] = $lang->sprintf($lang->onlinetoday_wol, "onlinetoday.php?my_post_key={$mybb->post_code}", $lang->whos_online_today);
	}
	
	return $plugin_array;
}
