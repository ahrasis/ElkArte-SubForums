<?php
/**
 *
 * This software is a derived product, based on:
 * @name     	PortaMX-SubForums
 * @copyright	PortaMx Corp. http://portamx.com (SMF Version)
 *
 * This software is converted to ElkArte:
 * @convertor  	ahrasis http://elkarte.ahrasis.com (ElkArte Version)
 * @license 	BSD http://opensource.org/licenses/BSD-3-Clause
 * @name     	SFA: Sub Forums Addon
 *
 */

if (!defined('ELK'))
	die('Hacking attempt...');

/**
* Init..
**/
global $SubforumFunc, $base_boardurl, $boardurl, $board_language, $language;

// define the url's
$base_boardurl = $boardurl;
$parts = parse_url($boardurl);
$boardurl = $parts['scheme'] .'://'. $_SERVER['SERVER_NAME'] .(!empty($parts['port']) ? ':'. $parts['port'] : '') . (!empty($parts['path']) ? $parts['path'] : '');
$board_language = $language;

// load the setting
SubForums_LoadSettings();

// setup function array
$SubforumFunc = array(
	'isAllowed' => 'Subforums_isAllowed',
	'LoadTheme' => 'Subforums_LoadTheme',
	'getContext' => 'Subforums_GetContext',
	'getScripturl' => 'Subforums_GetScripturl',
	'getMemberurl' => 'Subforums_GetMemberurl',
	'isOwnurl' => 'Subforums_isOwnurl',
	'checkurl' => 'Subforums_checkurl',
	'updateStats' => 'Subforums_updStats',
);

/**
* Handle the function calls
**/
function SubforumsAllocator($func, $value = null)
{
	global $SubforumFunc;

	if(isset($SubforumFunc[$func]) && function_exists($SubforumFunc[$func]))
		return $SubforumFunc[$func]($value);
}

/**
* Access to SubForums
* called from index.php
**/
function Subforums_isAllowed()
{
	global $context, $modSettings, $base_boardurl, $boardurl, $user_info, $maintenance, $txt;

	// Subforum called ?
	if($boardurl != $base_boardurl && isset($modSettings['subforums'][$_SERVER['SERVER_NAME']]))
	{
		// we are on the correct category / board?
		$error = 0;
		if(!empty($_GET['c']) && !in_array($_GET['c'], explode(',', $modSettings['subforums'][$_SERVER['SERVER_NAME']]['cats'])))
			$error = 1;
		elseif(!empty($_GET['topic']) && !empty($context['current_board']) && !in_array($context['current_board'], explode(',', $modSettings['subforums'][$_SERVER['SERVER_NAME']]['boards'])))
			$error = 2;
		elseif(!empty($_GET['board']) && !in_array($_GET['board'], explode(',', $modSettings['subforums'][$_SERVER['SERVER_NAME']]['boards'])))
			$error = 3;

		if(!empty($error))
		{
			$error = sprintf($txt['subforums_error404'], $txt['subforums_404types'][$error], $modSettings['subforums'][$_SERVER['SERVER_NAME']]['name']);
			header('HTTP/1.0 404 '. $error);
			header('Content-Type: text/plain; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
			die('404 - '. $error);
		}

		// check access
		$acs = array_intersect($user_info['groups'], $modSettings['subforums'][$_SERVER['SERVER_NAME']]['groups']);
		if(empty($acs) && !allowedTo('admin_forum'))
		{
			// no access, logout ...
			if(!$user_info['is_guest'])
				Subforums_noAccess($boardurl, $modSettings['subforums'][$_SERVER['SERVER_NAME']]['name']);

				// login...
			elseif(empty($_REQUEST['action']) || (!empty($_REQUEST['action']) && !in_array($_REQUEST['action'], array('login2', 'register', 'register2', 'reminder'))))
				Subforums_Login(false);

			// logout requested ?
			elseif(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'logout')
				$_SESSION['logout_url'] = $boardurl;
		}
	}

	// main forum only for admins ?
	elseif(empty($maintenance) && empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]) && !empty($modSettings['subforums_adminonly']) && !allowedTo('admin_forum'))
		Subforums_Login(true);
}

/**
* load the Theme
* called from Load.php (LoadTheme)
**/
function Subforums_LoadTheme($themeID)
{
	global $modSettings, $user_info, $board_language, $language;

	if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]['theme']))
		$themeID = (int) $modSettings['subforums'][$_SERVER['SERVER_NAME']]['theme'];

	// to NOT overwrite users language comment or remove the next five lines
	if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]['language']))
	{
		$language = $board_language;
		$user_info['language'] = $modSettings['subforums'][$_SERVER['SERVER_NAME']]['language'];
	}

	return $themeID;
}

/**
* setup the forum name
* called from Load.php (LoadBoard)
**/
function Subforums_GetContext()
{
	global $context, $modSettings;

	if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]['name']))
	{
		$context['forum_name'] = $modSettings['subforums'][$_SERVER['SERVER_NAME']]['name'];
		$context['forum_name_html_safe'] = Utils::htmlspecialchars($modSettings['subforums'][$_SERVER['SERVER_NAME']]['name']);
	}
}

/**
* check if the Scripturl own
* called from News.php xml (fix_possible_url)
**/
function Subforums_isOwnurl($host)
{
	global $modSettings;

	foreach($modSettings['subforums'] as $hostname => $data)
		if($hostname == $host)
			return $host;

	return '';
}

/**
* check if on the current forum
* called from Who and Subs-MemberOnline
**/
function Subforums_checkurl($url)
{
	global $modSettings, $base_boardurl;

	$parts = parse_url($base_boardurl);
	if(!empty($url['host']) && array_key_exists($url['host'], $modSettings['subforums']))
		return $url['host'];

	return $parts['host'];
}

/**
* Update the total Posts/Topics
* called from Subs.php
**/
function Subforums_updStats($change)
{
	global $modSettings;
	
	$db = database();

	if(array_key_exists($_SERVER['SERVER_NAME'], $modSettings['subforums']))
	{
		$host = $_SERVER['SERVER_NAME'];

		foreach($change as $variable => $value)
		{
			if($value !== true)
			{
				// Get the number of messages / topics
				$col = str_replace('total', '', $variable);
				$result = $db->query('', '
					SELECT SUM(num'. $col .' + unapproved'. $col .') AS total'. $col .'
					FROM {db_prefix}boards
					WHERE redirect = {string:blank_redirect} AND id_cat IN ({raw:cats})'. (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
						AND id_board != {int:recycle_board}' : ''),
					array(
						'cats' => $modSettings['subforums'][$host]['cats'],
						'recycle_board' => isset($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
						'blank_redirect' => '',
					)
				);
				$row = $db->fetch_assoc($result);
				$db->free_result($result);
				$change[$variable] = $row[$variable] === null ? 0 : $row[$variable];
			}

			foreach($change as $variable => $value)
			{
				$db->query('', '
					UPDATE {db_prefix}subforums
					SET {raw:variable} = {'. ($value === false || $value === true ? 'raw' : 'int') . ':value}
					WHERE forum_host = {string:host}',
					array(
						'value' => $value === true ? $variable .' + 1' : ($value === false ? $variable .' - 1' : $value),
						'variable' => $variable,
						'host' => $host,
					)
				);
				$modSettings['subforums'][$host][$variable] = $value === true ? $modSettings['subforums'][$host][$variable] + 1 : ($value === false ? $modSettings['subforums'][$host][$variable] - 1 : $value);
			}
			cache_put_data('PMx-SubForums-', NULL, 120);
		}
	}
}

/**
* get the Scripturl for subforums
* called from News.php xml (news, recent)
**/
function Subforums_GetScripturl($data)
{
	global $modSettings, $base_boardurl;

	list($type, $board, $url) = $data;
	$newurl = '';

	// find the subforum by cat or board
	foreach($modSettings['subforums'] as $host => $data)
	{
		$boards = explode(',', $data[$type]);
		array_walk($boards, create_function('&$v,$k', '$v = intval(trim($v));'));
		if(in_array(intval($board), $boards))
		{
			// found it.. return the new url
			$tmp = parse_url($url);
			return str_replace($tmp['host'], $host, $url);
		}
	}

	// main RSS not disabled .. return the url
	if(empty($modSettings['subforums_xmlrss']))
		return $url;

	// nothing found
	return false;
}

/**
* check member access subforums
* called from News.php xml (memberlist)
**/
function Subforums_GetMemberurl($url)
{
	global $modSettings, $base_boardurl;

	// main forum accessible .. return the url
	if(empty($modSettings['subforums_adminonly']))
		return $url;

	// find a subforum with guest/member access and return the url
	else
	{
		$base = parse_url($base_boardurl);
		foreach($modSettings['subforums'] as $host => $data)
		{
			if(in_array(0, $data['groups']) || in_array(-1, $data['groups']))
				return str_replace($base['host'], $host, $url);
		}
	}

	// nothing found
	return false;
}

/**
* check hostname
* called from PortaMx LoadData.php (custom actions)
**/
function Subforums_checkhost($itemData, &$bits)
{
	// find the subforum host
	if(preg_match('~\[host\=([a-zA-Z0-9\.\-\_\*\?\,\^\s]+)\]~i', $itemData, $temp) > 0 && isset($temp[1]))
	{
		$stripbits = array('spage', 'art', 'cat', 'child');
		$state = pmx_getBits($bits, $stripbits);
		$found = false;
		$hosts = explode(',', $temp[1]);

		foreach($hosts as $hostname)
		{
			$hostname = trim($hostname);
			$host = preg_replace('/^\^/', '', $hostname);
			$isNot = $host != $hostname;

			// mask out wildcards * and ?
			$host = str_replace(array('*','?'), array('.*','.?'), trim($host));
			if(preg_match('~'. $host .'~i', $_SERVER['SERVER_NAME'], $match) != 0 && $match[0] == $_SERVER['SERVER_NAME'])
			{
				// found it..
				$found = true;
				if(!empty($isNot))
					$bits = pmx_setBits(0);
				elseif((is_null($state) || (empty($bits['action']) && !empty($state))))
					$bits['action'] = intval(empty($isNot));
				break;
			}
		}

		if(empty($found))
		{
			if((is_null($state) || (empty($bits['action']) && !empty($state))))
				$bits['action'] = 1;
		}
	}
}

/**
* Load the settings
**/
function Subforums_LoadSettings()
{
	global $modSettings, $language, $txt;
	
	$db = database();

	// check if cached ..
	if(empty($modSettings['cache_enable']) || ($modSettings['subforums'] = cache_get_data('PMx-SubForums-', 10)) === null)
	{
		// not cached .. get from database
		$modSettings['subforums'] = array();
		$request = $db->query('', '
			SELECT s.id, s.forum_host, s.forum_name, s.cat_order, s.id_theme, s.language, s.acs_groups, s.reg_group, s.total_posts, s.total_topics, b.id_board
			FROM {db_prefix}subforums AS s
			LEFT JOIN {db_prefix}boards as b ON (FIND_IN_SET(b.id_cat, s.cat_order) > 0)
			ORDER BY b.id_cat, b.id_board',
			array()
		);
		if($db->num_rows($request) > 0)
		{
			while($row = $db->fetch_assoc($request))
			{
				if(!isset($modSettings['subforums'][$row['forum_host']]['cats']))
				{
					$modSettings['subforums'][$row['forum_host']]['name'] = $row['forum_name'];
					$modSettings['subforums'][$row['forum_host']]['cats'] = $row['cat_order'];
					$modSettings['subforums'][$row['forum_host']]['theme'] = $row['id_theme'];
					$modSettings['subforums'][$row['forum_host']]['language'] = $row['language'];
					$modSettings['subforums'][$row['forum_host']]['groups'] = explode(',', $row['acs_groups']);
					$modSettings['subforums'][$row['forum_host']]['reggroup'] = $row['reg_group'];
					$modSettings['subforums'][$row['forum_host']]['boards'] = array();
					$modSettings['subforums'][$row['forum_host']]['total_posts'] = $row['total_posts'];
					$modSettings['subforums'][$row['forum_host']]['total_topics'] = $row['total_topics'];
				}
				$modSettings['subforums'][$row['forum_host']]['boards'][] = $row['id_board'];
			}
			$db->free_result($request);

			// convert board
			foreach($modSettings['subforums'] as $host => $d)
				$modSettings['subforums'][$host]['boards'] = implode(',', $modSettings['subforums'][$host]['boards']);
		}

		// save it to cache
		if(!empty($modSettings['cache_enable']))
			cache_put_data('PMx-SubForums-', $modSettings['subforums'], 3600);
	}

	// subforums language set?
	if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]['language']))
		$language = $modSettings['subforums'][$_SERVER['SERVER_NAME']]['language'];

	// load language
	loadLanguage('addons/SubForums/Subforums');
}

/**
* SubForums Login
**/
function Subforums_Login($adm_mode)
{
	global context, $user_info, $user_profile;
	
	$db = database();

	// save the current url for login
	$_SESSION['login_url'] = htmlspecialchars__recursive($_SERVER['QUERY_STRING']);

	require_once(SUBSDIR . '/Auth.subs.php');

	// normal login ?
	if(empty($adm_mode) && empty($_REQUEST['action']) && empty($_REQUEST['board']) && empty($_REQUEST['topic']))
		$_REQUEST = $_GET = array('action' => 'login');

	// admin login ?
	if($adm_mode)
	{
		if(!empty($_REQUEST['action']) && in_array($_REQUEST['action'], array('reminder', 'login2')))
		{
			// missing hash / username or is reminder ...
			if(empty($_REQUEST['hash_passwrd']) || empty($_REQUEST['user']) || $_REQUEST['action'] == 'reminder')
			{
				if($_REQUEST['action'] == 'reminder')
					$_SESSION['failed_login'] = 0;
				$_REQUEST = $_GET = array();
			}

			// login failed..
			elseif(!empty($_SESSION['subforums_failed_login']))
				$_REQUEST = $_GET = array();

			else
			{
				// get memberdate ...
				if($mem = loadMemberData($_REQUEST['user'], true, 'profile'))
				{
					// get all groups for this member
					$mem = current($mem);
					$groups = array('-1');
					if(empty($user_profile[$mem]['id_group']) && empty($user_profile[$mem]['additional_groups']))
						$_REQUEST = $_GET = array();

					// member have groups..
					else
					{
						$groups = explode(',', $user_profile[$mem]['id_group']);
						if(!empty($user_profile[$mem]['additional_groups']))
							$groups = array_merge($groups, explode(',', $user_profile[$mem]['additional_groups']));

						// get permissions for the group(s)
						$perms = 0;
						$request = $db->query('', '
							SELECT count(permission)
							FROM {db_prefix}permissions
							WHERE id_group IN ({array_int:group}) AND permission = {string:perm} AND add_deny = 1',
							array(
								'group' => $groups,
								'perm' => 'admin_forum'
							)
						);
						list($perms) = $db->fetch_row($request);
						$db->free_result($request);

						// no permissions and not group 1 .. failed admin login
						if(empty($perms) && !in_array(1, $groups))
							$_REQUEST = $_GET = array();
					}
				}

				// member not found .. failed admin login
				else
					$_REQUEST = $_GET = array();
			}
		}

		// put css on header to hide the Maintenace header
		$context['html_headers'] .= '
		<style type="text/css">
		.subforum_hide { display:none }
		</style>';

		// achow the admin login
		$_SESSION['subforums_failed_login'] = false;
		InMaintenance();
	}

	// member login
	else
		KickGuest();
}

/**
* SubForums login failed
**/
function Subforums_noAccess($url, $subfName)
{
	if(!empty($url))
		$_SESSION['logout_url'] = $url;

	$_REQUEST = $_GET = array('action' => 'logout');

	require_once(CONTROLLERDIR . '/Auth.controller.php');
	Logout(true, false);

	// show a error message
	fatal_lang_error('subforums_nopermission', false, $subfName);
}

/**
* Setup membergroup on Register account
* Called from hook integrate_register
**/
function Subforums_Register(&$regOptions, &$theme_vars)
{
	global $modSettings;

	if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]['reggroup']) && $modSettings['subforums'][$_SERVER['SERVER_NAME']]['reggroup'] != -1)
		$regOptions['register_vars']['id_group'] = $modSettings['subforums'][$_SERVER['SERVER_NAME']]['reggroup'];
}

/**
* Add Admin menu context
* Called from hook integrate_admin_areas
**/
function Subforums_AdminMenu(&$menudata)
{
	global $txt;

	// insert Subforum top of 'config - layout'
	$menudata['layout']['areas'] = array_merge(
		array(
				'subforums' => array(
					'label' => $txt['admin_subforums'],
					'file' => 'addons/SubForums/SubforumsAdmin.php',
					'function' => 'SubforumsAdmin',
					'icon' => 'addons/SubForums/subforums.gif',
					'subsections' => array(
					),
				),
		),
		$menudata['layout']['areas']
	);
}
?>