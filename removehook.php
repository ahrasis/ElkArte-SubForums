<?php
/******************************
* file removehook             *
* Remove the PortaMx hooks    *
* Coypright by PortaMx corp.  *
*******************************/
global $sourcedir, $boarddir, $boardurl, $smcFunc, $user_info, $txt;

// Load the SSI.php
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
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
// no SSI.php and no SMF?
elseif (!defined('SMF'))
	die('<b>Error:</b> SSI.php not found. Please verify you put this in the same place as SMF\'s index.php.');
else
{
	function _write($string) { return; }
}

// Load the SMF DB Functions
db_extend('packages');
db_extend('extra');

_write('Removing all PortaMx integration hooks.<br />');

remove_integration_function('integrate_pre_include', '$sourcedir/SubForums/Subforums.php');
remove_integration_function('integrate_admin_areas', 'Subforums_AdminMenu');
remove_integration_function('integrate_register', 'Subforums_Register');

_write('Clear the settings cache.<br />');

// clear the cache
cache_put_data('modSettings', null, 90);

_write('removehook done.<br />');
?>