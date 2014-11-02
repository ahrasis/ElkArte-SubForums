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

global $boardurl, $user_info, $txt;

// Load the SSI.php
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
{
	function _write($string) { echo $string; }

	require_once(dirname(__FILE__) . '/SSI.php');

	// on manual installation you have to logged in
	if(!$user_info['is_admin'])
	{
		if($user_info['is_guest'])
		{
			echo '<b>', $txt['admin_login'],':</b><br />';
			ssi_login($boardurl.'/removehook.php');
			die();
		}
		else
		{
			loadLanguage('Errors');
			fatal_error($txt['cannot_admin_forum']);
		}
	}
}

// If we are outside ELK and can't find SSI.php, then throw an error
elseif (!defined('ELK'))
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as Elkarte\'s SSI.php.');
else
{
	function _write($string) { return; }
}

// Load the ELK DB Functions
if (ELK == 'SSI') {
	db_extend('packages');
	db_extend('extra');
}

_write('Removing all integration hooks.<br />');

remove_integration_function('integrate_pre_include', 'SOURCEDIR/addons/SubForums/Subforums.php');
remove_integration_function('integrate_admin_areas', 'Subforums_AdminMenu');
remove_integration_function('integrate_register', 'Subforums_Register');

_write('Clear the settings cache.<br />');

// clear the cache
cache_put_data('modSettings', null, 90);

_write('removehook done.<br />');

?>