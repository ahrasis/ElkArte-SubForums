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
* Receive the Posts from Subforums Manager
*/
function SubforumsAdmin()
{
	global $context, $modSettings, $txt;
	
	$db = database();

	if(isset($_GET['action']) && $_GET['action'] == 'admin' && isset($_GET['area']) && $_GET['area'] == 'subforums')
	{
		isAllowedTo('admin_forum');

		// From template ?
		if(!empty($_POST))
		{
			checkSession('post');

			if(isset($_POST['sf_edit']))
				$_POST['sf_edit'] = (int) $_POST['sf_edit'];
			elseif(isset($_POST['sf_save']) && $_POST['sf_save'] != 'main')
				$_POST['sf_save'] = (int) $_POST['sf_save'];
			elseif(isset($_POST['sf_delete']))
				$_POST['sf_delete'] = (int) $_POST['sf_delete'];

			// edit entry?
			if(!empty($_POST['sf_edit']))
			{
				$request = $db->query('', '
						SELECT id, forum_host, forum_name, cat_order, id_theme, language, acs_groups, reg_group
						FROM {db_prefix}subforums
						WHERE id = {int:id}',
					array('id' => $_POST['sf_edit'])
				);
				if($db->num_rows($request) > 0)
				{
					$row = $db->fetch_assoc($request);
					$context['subforums']['data'] = array(
						'id' => $row['id'],
						'host' => $row['forum_host'],
						'name' => $row['forum_name'],
						'cats' => explode(',', $row['cat_order']),
						'theme' => empty($row['id_theme']) ? '' : $row['id_theme'],
						'language' =>  $row['language'],
						'groups' => $row['acs_groups'] === '' ? array() : explode(',', $row['acs_groups']),
						'reg_group'=> $row['reg_group'] === '' ? -1 : $row['reg_group'],
					);
					$db->free_result($request);
				}
				else
					$_POST['sf_edit'] = 0;
			}

			// edit or add new?
			if(!empty($_POST['sf_edit']) || isset($_POST['sf_add']))
			{
				if(isset($_POST['sf_add']))
					$context['subforums']['data'] = array(
						'id' => -1,
						'host' => '',
						'name' => '',
						'cats' => array(),
						'theme' => 0,
						'language' => '',
						'asc_groups' => '',
						'reg_group'=> -1,
					);

				$context['subforums']['action'] = 'edit';
			}

			// remove entry
			if(!empty($_POST['sf_delete']))
			{
				$db->query('', '
					DELETE FROM {db_prefix}subforums
					WHERE id = {int:id}',
					array('id' => $_POST['sf_delete'])
				);

				SubForums_ClearCache();
			}

			// save entry?
			if(!empty($_POST['sf_save']))
			{
				// main forum access ?
				if($_POST['sf_save'] == 'main')
				{
					foreach($_POST as $key => $val)
						if(substr($key, 0, 10) == 'subforums_')
							$db->insert('replace', '
								{db_prefix}settings',
								array(
									'variable' => 'string',
									'value' => 'string',
								),
								array(
									$key,
									$val,
								),
								array()
							);
				}

				elseif(!empty($_POST['SF_host']) && !empty($_POST['SF_category']))
				{
					if(!isset($_POST['SF_groups']))
						$_POST['SF_groups'] = array();

					// get Messages and Topics
					$result = $db->query('', '
						SELECT SUM(num_posts + unapproved_posts) AS total_posts, SUM(num_topics + unapproved_topics) AS total_topics
						FROM {db_prefix}boards
						WHERE id_cat IN ('. implode(',', $_POST['SF_category']) .')',
						array()
					);
					$row = $db->fetch_assoc($result);
					$posts = empty($row['total_posts']) ? 0 : $row['total_posts'];
					$topics = empty($row['total_topics']) ? 0 : $row['total_topics'];
					$db->free_result($request);

					// add new?
					if($_POST['sf_save'] == -1)
					{
						$db->insert('', '
							{db_prefix}subforums',
							array(
								'forum_host' => 'string',
								'forum_name' => 'string',
								'cat_order' => 'string',
								'id_theme' => 'int',
								'language' => 'string',
								'acs_groups' => 'string',
								'reg_group' => 'int',
								'total_posts' => 'int',
								'total_topics' => 'int',
							),
							array(
								$_POST['SF_host'],
								$_POST['SF_name'],
								implode(',', $_POST['SF_category']),
								empty($_POST['SF_idtheme']) ? 0 : $_POST['SF_idtheme'],
								$_POST['SF_language'],
								implode(',', $_POST['SF_groups']),
								$_POST['SF_reggroup'],
								$posts,
								$topics,
							),
							array('id')
						);
					}

					// update...
					else
					{
						$db->query('', '
								UPDATE {db_prefix}subforums
								SET forum_host = {string:forum_host}, forum_name = {string:forum_name},
									cat_order = {string:cat_order}, id_theme = {int:id_theme}, language = {string:language},
									acs_groups = {string:acs_groups}, reg_group = {int:reg_group}, total_posts = {int:posts}, total_topics = {int:topics}
								WHERE id = {int:id}',
							array(
								'id' => $_POST['sf_save'],
								'forum_host' => $_POST['SF_host'],
								'forum_name' => $_POST['SF_name'],
								'cat_order' => implode(',', $_POST['SF_category']),
								'id_theme' => empty($_POST['SF_idtheme']) ? 0 : $_POST['SF_idtheme'],
								'language' => $_POST['SF_language'],
								'acs_groups' => implode(',', $_POST['SF_groups']),
								'reg_group' => $_POST['SF_reggroup'],
								'posts' => $posts,
								'topics' => $topics,
							)
						);
					}
				}

				// clear cache...
				SubForums_ClearCache();

				redirectexit('action=admin;area=subforums;'. $context['session_var'] .'=' .$context['session_id']);
			}
		}
		else
			$context['subforums']['action'] = '';

		if(empty($context['subforums']['action']))
		{
			// get all subforums
			$context['subforums']['data'] = array();
			$request = $db->query('', '
					SELECT id, forum_host, forum_name, cat_order, id_theme, language, acs_groups, total_posts, total_topics
					FROM {db_prefix}subforums
					ORDER BY id',
				array()
			);
			if($db->num_rows($request) > 0)
			{
				while($row = $db->fetch_assoc($request))
					$context['subforums']['data'][] = array(
						'id' => $row['id'],
						'host' => $row['forum_host'],
						'name' => $row['forum_name'],
						'cats' => explode(',', $row['cat_order']),
						'theme' => empty($row['id_theme']) ? '' : $row['id_theme'],
						'language' => $row['language'],
						'groups' => $row['acs_groups'] === '' ? array() : explode(',', $row['acs_groups']),
						'posts' => $row['total_posts'],
						'topics' => $row['total_topics'],
					);
				$db->free_result($request);
			}
		}

		// get known themes
		$context['subforums']['SMF_Themes'] = array();
		$request = $db->query('', '
				SELECT id_theme, value
				FROM {db_prefix}themes
				WHERE variable = {string:varname} AND id_theme IN({array_int:knownThemes})
				ORDER BY id_theme',
			array(
				'varname' => 'name',
				'knownThemes' => explode(',', $modSettings['knownThemes']),
			)
		);
		while($row = $db->fetch_assoc($request))
		{
			if($row['id_theme'] != $modSettings['theme_guests'])
				$context['subforums']['SMF_Themes'][$row['id_theme']] = $row['value'];
		}
		$db->free_result($request);

		// get all categories
		$context['subforums']['SMF_Cats'] = array();
		$request = $db->query('', '
				SELECT id_cat, name
				FROM {db_prefix}categories
				ORDER BY cat_order',
			array()
		);
		if($db->num_rows($request) > 0)
		{
			while($row = $db->fetch_assoc($request))
				$context['subforums']['SMF_Cats'][$row['id_cat']] = $row['name'];
			$db->free_result($request);
		}

		// get all groups
		$context['subforums']['SMF_groups'] = array(
			-1 => $txt['membergroups_guests'],
			0 =>  $txt['membergroups_members'],
		);
		$request = $db->query('', '
				SELECT id_group, group_name
				FROM {db_prefix}membergroups
				WHERE min_posts = -1 and id_group != 1
				ORDER BY id_group',
			array()
		);
		while($row = $db->fetch_assoc($request))
			$context['subforums']['SMF_groups'] += array(
				$row['id_group'] => $row['group_name'],
			);
		$db->free_result($request);

		// get all languages
		$context['subforums']['languages'] = getLanguages();

		// load template
		loadTemplate('addons/SubForums/Subforums');
		loadLanguage('addons/SubForums/Subforums');

		// setup pagetitle
		$context['page_title'] = $txt['admin_subforums_title'];
	}
}

/**
* Clear cached subforums
**/
function SubForums_ClearCache()
{
	global $modSettings, $boardurl, $base_boardurl;
	
	$db = database();

	$org_url = $boardurl;
	$boardurl = $base_boardurl;
	cache_put_data('modSettings', NULL, 120);
	cache_put_data('PMx-SubForums-', NULL, 120);

	$request = $db->query('', '
		SELECT forum_host
		FROM {db_prefix}subforums',
		array()
	);
	while($row = $db->fetch_assoc($request))
	{
		$parts = parse_url($boardurl);
		$boardurl = $parts['scheme'] .'://'. $row['forum_host'] .(!empty($parts['path']) ? $parts['path'] : '');
		cache_put_data('modSettings', NULL, 120);
		cache_put_data('PMx-SubForums-', NULL, 120);
	}
	$db->free_result($request);
	$boardurl = $org_url;
}
?>